<?php
/**
 * PipelineService - Manages CRM pipelines and stages
 * 
 * Handles CRUD operations for pipelines and their associated stages,
 * including validation, ordering, and tenant isolation.
 */

require_once __DIR__ . '/../../DB.php';

class PipelineService {
    private $db;
    private $tenantId;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }
    
    /**
     * Set tenant context for tenant-scoped queries
     */
    public function setTenantId($tenantId) {
        $this->tenantId = $tenantId;
    }
    
    /**
     * Get current tenant ID
     */
    public function getTenantId() {
        return $this->tenantId;
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * List all pipelines
     * 
     * @param bool $includeArchived Whether to include archived pipelines
     * @return array List of pipelines
     */
    public function listPipelines($includeArchived = false) {
        $sql = "SELECT * FROM crm_pipelines WHERE 1=1";
        $params = [];
        
        // Tenant filtering
        if ($this->tenantId !== null) {
            $sql .= " AND (client_id = ? OR client_id IS NULL)";
            $params[] = $this->tenantId;
        }
        
        // Archive filtering
        if (!$includeArchived) {
            $sql .= " AND archived_at IS NULL";
        }
        
        $sql .= " ORDER BY is_default DESC, name ASC";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get a specific pipeline with optional stages
     * 
     * @param string $pipelineId Pipeline UUID
     * @param bool $includeStages Whether to include stages
     * @return array|null Pipeline data or null if not found
     */
    public function getPipeline($pipelineId, $includeStages = true) {
        $sql = "SELECT * FROM crm_pipelines WHERE id = ?";
        $pipeline = $this->db->getOne($sql, [$pipelineId]);
        
        if (!$pipeline) {
            return null;
        }
        
        // Tenant check
        $this->ensureTenantContext($pipelineId);
        
        if ($includeStages) {
            $pipeline['stages'] = $this->listStages($pipelineId);
        }
        
        return $pipeline;
    }
    
    /**
     * Create a new pipeline
     * 
     * @param array $data Pipeline data (name, description, is_default, color, stages)
     * @return array Created pipeline or error array
     */
    public function createPipeline($data) {
        // Validation
        $errors = $this->validatePipelineData($data);
        if (!empty($errors)) {
            return ['error' => implode(', ', $errors)];
        }
        
        $pipelineId = $this->generateUUID();
        
        $this->db->beginTransaction();
        
        try {
            // If this should be default, unset other defaults first
            if ($data['is_default'] ?? false) {
                $this->unsetAllDefaults();
            }
            
            // Insert pipeline
            $sql = "INSERT INTO crm_pipelines 
                (id, client_id, name, description, is_default, color, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))";
            
            $params = [
                $pipelineId,
                $this->tenantId,
                $data['name'],
                $data['description'] ?? null,
                isset($data['is_default']) ? (int)(bool)$data['is_default'] : 0,
                $data['color'] ?? '#8b5cf6'
            ];
            
            $this->db->execute($sql, $params);
            
            // Create stages if provided
            if (!empty($data['stages'])) {
                foreach ($data['stages'] as $index => $stageData) {
                    $stageData['position'] = $index;
                    $this->createStage($pipelineId, $stageData);
                }
            }
            
            $this->db->commit();
            
            return $this->getPipeline($pipelineId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to create pipeline: " . $e->getMessage());
            return ['error' => 'Failed to create pipeline'];
        }
    }
    
    /**
     * Update an existing pipeline
     * 
     * @param string $pipelineId Pipeline UUID
     * @param array $data Update data
     * @return array Updated pipeline or error array
     */
    public function updatePipeline($pipelineId, $data) {
        // Verify pipeline exists and tenant access
        $pipeline = $this->getPipeline($pipelineId, false);
        if (!$pipeline) {
            return ['error' => 'Pipeline not found'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // If setting as default, unset others first
            if (isset($data['is_default']) && $data['is_default']) {
                $this->unsetAllDefaults();
            }
            
            // Build dynamic update query
            $fields = [];
            $params = [];
            
            $allowedFields = ['name', 'description', 'is_default', 'color'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    if ($field === 'is_default') {
                        $fields[] = "$field = ?";
                        $params[] = (int)(bool)$data[$field];
                    } else {
                        $fields[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($fields)) {
                $this->db->rollBack();
                return $pipeline; // No changes
            }
            
            $fields[] = "updated_at = datetime('now')";
            $params[] = $pipelineId;
            
            $sql = "UPDATE crm_pipelines SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->db->execute($sql, $params);
            
            $this->db->commit();
            
            return $this->getPipeline($pipelineId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to update pipeline: " . $e->getMessage());
            return ['error' => 'Failed to update pipeline'];
        }
    }
    
    /**
     * Archive a pipeline (soft delete)
     * 
     * @param string $pipelineId Pipeline UUID
     * @return array Success or error array
     */
    public function archivePipeline($pipelineId) {
        // Check if pipeline exists
        $pipeline = $this->getPipeline($pipelineId, false);
        if (!$pipeline) {
            return ['error' => 'Pipeline not found'];
        }
        
        // Prevent archiving default pipeline
        if ($pipeline['is_default']) {
            return ['error' => 'Cannot archive default pipeline. Set another pipeline as default first.'];
        }
        
        // Check if any leads are in this pipeline
        $sql = "SELECT COUNT(*) as count FROM leads WHERE pipeline_id = ?";
        $result = $this->db->getOne($sql, [$pipelineId]);
        $leadCount = $result['count'] ?? 0;
        
        if ($leadCount > 0) {
            return [
                'error' => "Cannot archive pipeline with active leads. Move {$leadCount} leads to another pipeline first.",
                'lead_count' => $leadCount
            ];
        }
        
        // Archive pipeline and its stages
        $this->db->beginTransaction();
        
        try {
            $sql = "UPDATE crm_pipelines 
                SET archived_at = datetime('now'), updated_at = datetime('now')
                WHERE id = ?";
            $this->db->execute($sql, [$pipelineId]);
            
            // Also archive stages
            $sql = "UPDATE crm_pipeline_stages
                SET archived_at = datetime('now'), updated_at = datetime('now')
                WHERE pipeline_id = ?";
            $this->db->execute($sql, [$pipelineId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Pipeline archived'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to archive pipeline: " . $e->getMessage());
            return ['error' => 'Failed to archive pipeline'];
        }
    }
    
    /**
     * Get the default pipeline
     * 
     * @return array|null Default pipeline with stages or null
     */
    public function getDefaultPipeline() {
        $sql = "SELECT * FROM crm_pipelines 
            WHERE is_default = 1 
            AND archived_at IS NULL
            LIMIT 1";
        
        $pipeline = $this->db->getOne($sql, []);
        
        if ($pipeline) {
            $pipeline['stages'] = $this->listStages($pipeline['id']);
        }
        
        return $pipeline;
    }
    
    /**
     * Set a pipeline as default (atomic operation)
     * 
     * @param string $pipelineId Pipeline UUID
     * @return array Updated pipeline or error array
     */
    public function setDefaultPipeline($pipelineId) {
        $this->db->beginTransaction();
        
        try {
            // Unset all defaults
            $this->unsetAllDefaults();
            
            // Set new default
            $sql = "UPDATE crm_pipelines 
                SET is_default = 1, updated_at = datetime('now')
                WHERE id = ?";
            $this->db->execute($sql, [$pipelineId]);
            
            $this->db->commit();
            
            return $this->getPipeline($pipelineId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to set default pipeline: " . $e->getMessage());
            return ['error' => 'Failed to set default pipeline'];
        }
    }
    
    /**
     * Unset all default flags (helper)
     */
    private function unsetAllDefaults() {
        $sql = "UPDATE crm_pipelines SET is_default = 0 WHERE is_default = 1";
        
        if ($this->tenantId !== null) {
            $sql .= " AND (client_id = ? OR client_id IS NULL)";
            $this->db->execute($sql, [$this->tenantId]);
        } else {
            $this->db->execute($sql, []);
        }
    }
    
    /**
     * List stages for a pipeline
     * 
     * @param string $pipelineId Pipeline UUID
     * @param bool $includeArchived Whether to include archived stages
     * @return array List of stages
     */
    public function listStages($pipelineId, $includeArchived = false) {
        $sql = "SELECT * FROM crm_pipeline_stages WHERE pipeline_id = ?";
        $params = [$pipelineId];
        
        if (!$includeArchived) {
            $sql .= " AND archived_at IS NULL";
        }
        
        $sql .= " ORDER BY position ASC";
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Get a specific stage
     * 
     * @param string $stageId Stage UUID
     * @return array|null Stage data or null
     */
    public function getStage($stageId) {
        $sql = "SELECT * FROM crm_pipeline_stages WHERE id = ?";
        return $this->db->getOne($sql, [$stageId]);
    }
    
    /**
     * Create a stage
     * 
     * @param string $pipelineId Pipeline UUID
     * @param array $data Stage data (name, slug, color, position, etc.)
     * @return array Created stage
     */
    public function createStage($pipelineId, $data) {
        $stageId = $this->generateUUID();
        
        // Auto-assign position if not provided
        if (!isset($data['position'])) {
            $sql = "SELECT COALESCE(MAX(position), -1) + 1 as next_position 
                FROM crm_pipeline_stages 
                WHERE pipeline_id = ?";
            $result = $this->db->getOne($sql, [$pipelineId]);
            $data['position'] = $result['next_position'];
        }
        
        $sql = "INSERT INTO crm_pipeline_stages
            (id, pipeline_id, name, slug, position, color, is_won, is_lost, is_closed, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))";
        
        $params = [
            $stageId,
            $pipelineId,
            $data['name'],
            $data['slug'] ?? $this->slugify($data['name']),
            $data['position'],
            $data['color'] ?? '#6b7280',
            isset($data['is_won']) ? (int)(bool)$data['is_won'] : 0,
            isset($data['is_lost']) ? (int)(bool)$data['is_lost'] : 0,
            isset($data['is_closed']) ? (int)(bool)$data['is_closed'] : 0
        ];
        
        $this->db->execute($sql, $params);
        
        return $this->getStage($stageId);
    }
    
    /**
     * Update an existing stage
     * 
     * @param string $stageId Stage UUID
     * @param array $data Update data
     * @return array Updated stage or error array
     */
    public function updateStage($stageId, $data) {
        $stage = $this->getStage($stageId);
        if (!$stage) {
            return ['error' => 'Stage not found'];
        }
        
        try {
            // Build dynamic update query
            $fields = [];
            $params = [];
            
            $allowedFields = ['name', 'slug', 'position', 'color', 'is_won', 'is_lost', 'is_closed'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    if (in_array($field, ['is_won', 'is_lost', 'is_closed'])) {
                        $fields[] = "$field = ?";
                        $params[] = (int)(bool)$data[$field];
                    } else {
                        $fields[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($fields)) {
                return $stage; // No changes
            }
            
            $fields[] = "updated_at = datetime('now')";
            $params[] = $stageId;
            
            $sql = "UPDATE crm_pipeline_stages SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->db->execute($sql, $params);
            
            return $this->getStage($stageId);
            
        } catch (Exception $e) {
            error_log("Failed to update stage: " . $e->getMessage());
            return ['error' => 'Failed to update stage'];
        }
    }
    
    /**
     * Archive a stage (soft delete)
     * 
     * @param string $stageId Stage UUID
     * @return array Success or error array
     */
    public function archiveStage($stageId) {
        $stage = $this->getStage($stageId);
        if (!$stage) {
            return ['error' => 'Stage not found'];
        }
        
        // Check if any leads are in this stage
        $sql = "SELECT COUNT(*) as count FROM leads WHERE stage_id = ?";
        $result = $this->db->getOne($sql, [$stageId]);
        $leadCount = $result['count'] ?? 0;
        
        if ($leadCount > 0) {
            return [
                'error' => "Cannot archive stage with active leads. Move {$leadCount} leads to another stage first.",
                'lead_count' => $leadCount
            ];
        }
        
        try {
            $sql = "UPDATE crm_pipeline_stages 
                SET archived_at = datetime('now'), updated_at = datetime('now')
                WHERE id = ?";
            $this->db->execute($sql, [$stageId]);
            
            return ['success' => true, 'message' => 'Stage archived'];
            
        } catch (Exception $e) {
            error_log("Failed to archive stage: " . $e->getMessage());
            return ['error' => 'Failed to archive stage'];
        }
    }
    
    /**
     * Reorder stages within a pipeline
     * 
     * @param string $pipelineId Pipeline UUID
     * @param array $stageIds Array of stage UUIDs in new order
     * @return array Updated stages or error array
     */
    public function reorderStages($pipelineId, $stageIds) {
        $this->db->beginTransaction();
        
        try {
            foreach ($stageIds as $position => $stageId) {
                $sql = "UPDATE crm_pipeline_stages 
                    SET position = ?, updated_at = datetime('now')
                    WHERE id = ? AND pipeline_id = ?";
                $this->db->execute($sql, [$position, $stageId, $pipelineId]);
            }
            
            $this->db->commit();
            
            return $this->listStages($pipelineId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to reorder stages: " . $e->getMessage());
            return ['error' => 'Failed to reorder stages'];
        }
    }
    
    /**
     * Save stages in bulk (create/update)
     * 
     * @param string $pipelineId Pipeline UUID
     * @param array $stagesData Array of stage data
     * @return array Updated stages or error array
     */
    public function saveStages($pipelineId, $stagesData) {
        $this->db->beginTransaction();
        
        try {
            $processedIds = [];
            
            foreach ($stagesData as $index => $stageData) {
                $stageData['position'] = $index;
                
                if (!empty($stageData['id'])) {
                    // Update existing stage
                    $result = $this->updateStage($stageData['id'], $stageData);
                    if (isset($result['error'])) {
                        throw new Exception($result['error']);
                    }
                    $processedIds[] = $stageData['id'];
                } else {
                    // Create new stage
                    $stage = $this->createStage($pipelineId, $stageData);
                    $processedIds[] = $stage['id'];
                }
            }
            
            $this->db->commit();
            
            return $this->listStages($pipelineId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to save stages: " . $e->getMessage());
            return ['error' => 'Failed to save stages'];
        }
    }
    
    /**
     * Get lead count by stage for a pipeline
     * 
     * @param string $pipelineId Pipeline UUID
     * @return array Stage ID => count mapping
     */
    public function getLeadCountByStage($pipelineId) {
        $sql = "SELECT stage_id, COUNT(*) as count
            FROM leads
            WHERE pipeline_id = ?
            GROUP BY stage_id";
        
        $results = $this->db->query($sql, [$pipelineId]);
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['stage_id']] = (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Validate pipeline data
     * 
     * @param array $data Pipeline data
     * @return array Array of error messages (empty if valid)
     */
    public function validatePipelineData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Pipeline name is required';
        }
        
        if (isset($data['name']) && strlen($data['name']) > 255) {
            $errors[] = 'Pipeline name too long (max 255 characters)';
        }
        
        return $errors;
    }
    
    /**
     * Validate stage data
     * 
     * @param array $data Stage data
     * @return array Array of error messages (empty if valid)
     */
    public function validateStageData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Stage name is required';
        }
        
        if (isset($data['name']) && strlen($data['name']) > 255) {
            $errors[] = 'Stage name too long (max 255 characters)';
        }
        
        if (isset($data['position']) && !is_numeric($data['position'])) {
            $errors[] = 'Stage position must be a number';
        }
        
        return $errors;
    }
    
    /**
     * Slugify string for stage slug
     * 
     * @param string $text Text to slugify
     * @return string Slugified text
     */
    private function slugify($text) {
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        // Trim
        $text = trim($text, '-');
        
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        
        // Lowercase
        $text = strtolower($text);
        
        return empty($text) ? 'stage' : $text;
    }
    
    /**
     * Ensure tenant context (security check)
     * 
     * @param string $pipelineId Pipeline UUID
     * @throws Exception If access denied
     */
    private function ensureTenantContext($pipelineId) {
        if ($this->tenantId === null) {
            return; // No tenant filtering
        }
        
        $sql = "SELECT client_id FROM crm_pipelines WHERE id = ?";
        $pipeline = $this->db->getOne($sql, [$pipelineId]);
        
        if (!$pipeline) {
            throw new Exception('Pipeline not found');
        }
        
        if ($pipeline['client_id'] !== null && $pipeline['client_id'] !== $this->tenantId) {
            throw new Exception('Access denied to pipeline');
        }
    }
}
