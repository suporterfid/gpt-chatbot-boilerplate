# Task 5: Create CRM Pipeline Service

## Objective
Create a service class for managing CRM pipelines and stages, providing CRUD operations and business logic for pipeline management.

## Prerequisites
- Task 1 completed (CRM tables exist)
- Task 4 completed (default pipeline seeded)
- Review existing service patterns (AgentService, PromptService)
- Review LeadRepository structure

## File to Create

### `includes/LeadSense/CRM/PipelineService.php`

## Class Structure

```php
<?php
/**
 * PipelineService - Manages CRM pipelines and stages
 * 
 * Handles CRUD operations for pipelines and their associated stages,
 * including validation, ordering, and tenant isolation.
 */

class PipelineService {
    private $db;
    private $tenantId;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }
    
    // Pipeline Operations
    public function listPipelines($includeArchived = false);
    public function getPipeline($pipelineId, $includeStages = true);
    public function createPipeline($data);
    public function updatePipeline($pipelineId, $data);
    public function archivePipeline($pipelineId);
    public function getDefaultPipeline();
    public function setDefaultPipeline($pipelineId);
    
    // Stage Operations
    public function listStages($pipelineId, $includeArchived = false);
    public function getStage($stageId);
    public function createStage($pipelineId, $data);
    public function updateStage($stageId, $data);
    public function archiveStage($stageId);
    public function reorderStages($pipelineId, $stageIds);
    public function saveStages($pipelineId, $stagesData);
    
    // Utility Methods
    public function getLeadCountByStage($pipelineId);
    public function validatePipelineData($data);
    public function validateStageData($data);
    
    // Private helpers
    private function generateUUID();
    private function ensureTenantContext($pipelineId);
}
```

## Detailed Implementation

```php
<?php
/**
 * PipelineService - Manages CRM pipelines and stages
 */

class PipelineService {
    private $db;
    private $tenantId;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
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
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a specific pipeline with optional stages
     */
    public function getPipeline($pipelineId, $includeStages = true) {
        $stmt = $this->db->prepare("
            SELECT * FROM crm_pipelines WHERE id = ?
        ");
        $stmt->execute([$pipelineId]);
        $pipeline = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
            $stmt = $this->db->prepare("
                INSERT INTO crm_pipelines 
                (id, client_id, name, description, is_default, color, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ");
            
            $stmt->execute([
                $pipelineId,
                $this->tenantId,
                $data['name'],
                $data['description'] ?? null,
                $data['is_default'] ?? 0,
                $data['color'] ?? '#8b5cf6'
            ]);
            
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
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            $fields[] = "updated_at = datetime('now')";
            $params[] = $pipelineId;
            
            $sql = "UPDATE crm_pipelines SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
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
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM leads WHERE pipeline_id = ?");
        $stmt->execute([$pipelineId]);
        $leadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($leadCount > 0) {
            return [
                'error' => "Cannot archive pipeline with active leads. Move {$leadCount} leads to another pipeline first.",
                'lead_count' => $leadCount
            ];
        }
        
        // Archive pipeline and its stages
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare("
                UPDATE crm_pipelines 
                SET archived_at = datetime('now'), updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$pipelineId]);
            
            // Also archive stages
            $stmt = $this->db->prepare("
                UPDATE crm_pipeline_stages
                SET archived_at = datetime('now'), updated_at = datetime('now')
                WHERE pipeline_id = ?
            ");
            $stmt->execute([$pipelineId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Pipeline archived'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['error' => 'Failed to archive pipeline'];
        }
    }
    
    /**
     * Get the default pipeline
     */
    public function getDefaultPipeline() {
        $stmt = $this->db->prepare("
            SELECT * FROM crm_pipelines 
            WHERE is_default = 1 
            AND archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute();
        $pipeline = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pipeline) {
            $pipeline['stages'] = $this->listStages($pipeline['id']);
        }
        
        return $pipeline;
    }
    
    /**
     * Set a pipeline as default (atomic operation)
     */
    public function setDefaultPipeline($pipelineId) {
        $this->db->beginTransaction();
        
        try {
            // Unset all defaults
            $this->unsetAllDefaults();
            
            // Set new default
            $stmt = $this->db->prepare("
                UPDATE crm_pipelines 
                SET is_default = 1, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$pipelineId]);
            
            $this->db->commit();
            
            return $this->getPipeline($pipelineId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
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
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->tenantId]);
        } else {
            $this->db->exec($sql);
        }
    }
    
    /**
     * List stages for a pipeline
     */
    public function listStages($pipelineId, $includeArchived = false) {
        $sql = "SELECT * FROM crm_pipeline_stages WHERE pipeline_id = ?";
        $params = [$pipelineId];
        
        if (!$includeArchived) {
            $sql .= " AND archived_at IS NULL";
        }
        
        $sql .= " ORDER BY position ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a stage
     */
    public function createStage($pipelineId, $data) {
        $stageId = $this->generateUUID();
        
        // Auto-assign position if not provided
        if (!isset($data['position'])) {
            $stmt = $this->db->prepare("
                SELECT COALESCE(MAX(position), -1) + 1 as next_position 
                FROM crm_pipeline_stages 
                WHERE pipeline_id = ?
            ");
            $stmt->execute([$pipelineId]);
            $data['position'] = $stmt->fetch(PDO::FETCH_ASSOC)['next_position'];
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO crm_pipeline_stages
            (id, pipeline_id, name, slug, position, color, is_won, is_lost, is_closed, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        
        $stmt->execute([
            $stageId,
            $pipelineId,
            $data['name'],
            $data['slug'] ?? $this->slugify($data['name']),
            $data['position'],
            $data['color'] ?? '#6b7280',
            $data['is_won'] ?? 0,
            $data['is_lost'] ?? 0,
            $data['is_closed'] ?? 0
        ]);
        
        return $this->getStage($stageId);
    }
    
    /**
     * Save stages in bulk (create/update/delete)
     */
    public function saveStages($pipelineId, $stagesData) {
        $this->db->beginTransaction();
        
        try {
            $processedIds = [];
            
            foreach ($stagesData as $index => $stageData) {
                $stageData['position'] = $index;
                
                if (!empty($stageData['id'])) {
                    // Update existing stage
                    $this->updateStage($stageData['id'], $stageData);
                    $processedIds[] = $stageData['id'];
                } else {
                    // Create new stage
                    $stage = $this->createStage($pipelineId, $stageData);
                    $processedIds[] = $stage['id'];
                }
            }
            
            // Archive stages not in the list (optional - commented out for safety)
            // $this->archiveUnprocessedStages($pipelineId, $processedIds);
            
            $this->db->commit();
            
            return $this->listStages($pipelineId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to save stages: " . $e->getMessage());
            return ['error' => 'Failed to save stages'];
        }
    }
    
    /**
     * Get lead count by stage
     */
    public function getLeadCountByStage($pipelineId) {
        $stmt = $this->db->prepare("
            SELECT stage_id, COUNT(*) as count
            FROM leads
            WHERE pipeline_id = ?
            GROUP BY stage_id
        ");
        $stmt->execute([$pipelineId]);
        
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[$row['stage_id']] = (int)$row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Validate pipeline data
     */
    public function validatePipelineData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Pipeline name is required';
        }
        
        if (strlen($data['name'] ?? '') > 255) {
            $errors[] = 'Pipeline name too long (max 255 characters)';
        }
        
        return $errors;
    }
    
    /**
     * Slugify string for stage slug
     */
    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        
        return empty($text) ? 'stage' : $text;
    }
    
    /**
     * Ensure tenant context (security check)
     */
    private function ensureTenantContext($pipelineId) {
        if ($this->tenantId === null) {
            return; // No tenant filtering
        }
        
        $stmt = $this->db->prepare("
            SELECT client_id FROM crm_pipelines WHERE id = ?
        ");
        $stmt->execute([$pipelineId]);
        $pipeline = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pipeline) {
            throw new Exception('Pipeline not found');
        }
        
        if ($pipeline['client_id'] !== null && $pipeline['client_id'] !== $this->tenantId) {
            throw new Exception('Access denied to pipeline');
        }
    }
}
```

## Usage Examples

```php
// In admin-api.php

// Initialize service
$pipelineService = new PipelineService($db, $tenantId);

// List pipelines
$pipelines = $pipelineService->listPipelines();

// Create pipeline
$newPipeline = $pipelineService->createPipeline([
    'name' => 'Enterprise Sales',
    'description' => 'High-value enterprise deals',
    'is_default' => false,
    'color' => '#8b5cf6',
    'stages' => [
        ['name' => 'Discovery', 'slug' => 'discovery', 'color' => '#3b82f6'],
        ['name' => 'Proposal', 'slug' => 'proposal', 'color' => '#22c55e'],
        ['name' => 'Closed Won', 'slug' => 'won', 'color' => '#10b981', 'is_won' => true, 'is_closed' => true]
    ]
]);

// Get pipeline with stages
$pipeline = $pipelineService->getPipeline($pipelineId, true);

// Update pipeline
$updated = $pipelineService->updatePipeline($pipelineId, [
    'name' => 'Enterprise Sales - Updated',
    'color' => '#6366f1'
]);
```

## Testing

Create unit tests in `tests/test_pipeline_service.php`:

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/LeadSense/CRM/PipelineService.php';

$db = Database::getInstance();
$service = new PipelineService($db);

echo "\n=== Testing PipelineService ===\n";

// Test: Create pipeline
$pipeline = $service->createPipeline([
    'name' => 'Test Pipeline',
    'description' => 'For testing',
    'stages' => [
        ['name' => 'Stage 1', 'slug' => 'stage-1'],
        ['name' => 'Stage 2', 'slug' => 'stage-2']
    ]
]);

assert(!isset($pipeline['error']));
assert($pipeline['name'] === 'Test Pipeline');
echo "✓ PASS: Pipeline created\n";

// Test: List pipelines
$pipelines = $service->listPipelines();
assert(count($pipelines) > 0);
echo "✓ PASS: Pipelines listed\n";

// Test: Get pipeline with stages
$fetched = $service->getPipeline($pipeline['id']);
assert(count($fetched['stages']) === 2);
echo "✓ PASS: Pipeline fetched with stages\n";

echo "\n=== All PipelineService tests passed ===\n";
```

## Related Tasks
- Task 1: CRM tables (prerequisite)
- Task 4: Default pipeline seeding (prerequisite)
- Task 9: Pipeline API endpoints (uses this service)

## References
- Spec Section 3.2: Pipelines API
- `includes/AgentService.php` - Similar service pattern
