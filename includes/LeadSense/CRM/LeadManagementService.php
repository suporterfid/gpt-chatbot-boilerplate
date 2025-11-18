<?php
/**
 * LeadManagementService - Manages CRM operations for leads
 * 
 * Handles stage movement, owner assignment, deal tracking, and notes.
 * Integrates with LeadRepository for event recording.
 */

require_once __DIR__ . '/../../DB.php';
require_once __DIR__ . '/../LeadRepository.php';
require_once __DIR__ . '/../LeadEventTypes.php';
require_once __DIR__ . '/PipelineService.php';

class LeadManagementService {
    private $db;
    private $tenantId;
    private $leadRepo;
    private $pipelineService;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
        
        // Initialize dependencies
        $config = [
            'database_path' => null, // Will use existing DB connection
            'database_url' => null
        ];
        $this->leadRepo = new LeadRepository($config, $tenantId);
        $this->pipelineService = new PipelineService($db, $tenantId);
    }
    
    /**
     * Set tenant context
     */
    public function setTenantId($tenantId) {
        $this->tenantId = $tenantId;
        $this->leadRepo->setTenantId($tenantId);
        $this->pipelineService->setTenantId($tenantId);
    }
    
    /**
     * Move a lead to a different stage
     * 
     * @param string $leadId Lead UUID
     * @param string $toStageId Target stage UUID
     * @param array $options Additional options (from_stage_id, pipeline_id, changed_by, position)
     * @return array Updated lead or error
     */
    public function moveLead($leadId, $toStageId, $options = []) {
        // Get lead
        $lead = $this->leadRepo->getById($leadId);
        if (!$lead) {
            return ['error' => 'Lead not found'];
        }
        
        // Get target stage
        $toStage = $this->pipelineService->getStage($toStageId);
        if (!$toStage) {
            return ['error' => 'Target stage not found'];
        }
        
        // Verify pipeline consistency if provided
        if (!empty($options['pipeline_id']) && $toStage['pipeline_id'] !== $options['pipeline_id']) {
            return ['error' => 'Stage does not belong to specified pipeline'];
        }
        
        // Verify from_stage matches if provided
        if (!empty($options['from_stage_id']) && $lead['stage_id'] !== $options['from_stage_id']) {
            return ['error' => 'Lead is not in the specified source stage'];
        }
        
        $oldStageId = $lead['stage_id'];
        $oldPipelineId = $lead['pipeline_id'];
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // Update lead stage and pipeline
            $sql = "UPDATE leads 
                SET stage_id = ?, 
                    pipeline_id = ?,
                    updated_at = datetime('now')
                WHERE id = ?";
            
            $this->db->execute($sql, [$toStageId, $toStage['pipeline_id'], $leadId]);
            
            // Record stage_changed event
            $eventPayload = [
                'old_stage_id' => $oldStageId,
                'new_stage_id' => $toStageId,
                'old_pipeline_id' => $oldPipelineId,
                'new_pipeline_id' => $toStage['pipeline_id'],
                'changed_by' => $options['changed_by'] ?? null,
                'changed_by_type' => $options['changed_by_type'] ?? 'system'
            ];
            
            // Determine event type
            $eventType = LeadEventTypes::STAGE_CHANGED;
            if ($oldPipelineId !== $toStage['pipeline_id']) {
                $eventType = LeadEventTypes::PIPELINE_CHANGED;
            }
            
            $this->leadRepo->addEvent($leadId, $eventType, $eventPayload);
            
            $this->db->commit();
            
            // Return updated lead
            return $this->leadRepo->getById($leadId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to move lead: " . $e->getMessage());
            return ['error' => 'Failed to move lead'];
        }
    }
    
    /**
     * Assign owner to a lead
     * 
     * @param string $leadId Lead UUID
     * @param string $ownerId Owner UUID
     * @param string $ownerType Owner type (admin_user, agent, external)
     * @param array $options Additional options (assigned_by, note)
     * @return array Updated lead or error
     */
    public function assignOwner($leadId, $ownerId, $ownerType, $options = []) {
        // Validate owner type
        $validTypes = ['admin_user', 'agent', 'external'];
        if (!in_array($ownerType, $validTypes)) {
            return ['error' => 'Invalid owner type'];
        }
        
        // Get lead
        $lead = $this->leadRepo->getById($leadId);
        if (!$lead) {
            return ['error' => 'Lead not found'];
        }
        
        $oldOwnerId = $lead['owner_id'];
        $oldOwnerType = $lead['owner_type'];
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // Update lead owner
            $sql = "UPDATE leads 
                SET owner_id = ?,
                    owner_type = ?,
                    updated_at = datetime('now')
                WHERE id = ?";
            
            $this->db->execute($sql, [$ownerId, $ownerType, $leadId]);
            
            // Create assignment record
            $assignmentId = $this->generateUUID();
            $sql = "INSERT INTO crm_lead_assignments 
                (id, lead_id, owner_id, owner_type, assigned_by, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'))";
            
            $this->db->execute($sql, [
                $assignmentId,
                $leadId,
                $ownerId,
                $ownerType,
                $options['assigned_by'] ?? null,
                $options['note'] ?? null
            ]);
            
            // End previous assignment if exists
            if ($oldOwnerId) {
                $sql = "UPDATE crm_lead_assignments 
                    SET ended_at = datetime('now')
                    WHERE lead_id = ? 
                    AND owner_id = ?
                    AND owner_type = ?
                    AND ended_at IS NULL";
                
                $this->db->execute($sql, [$leadId, $oldOwnerId, $oldOwnerType]);
            }
            
            // Record owner_changed event
            $eventPayload = [
                'old_owner_id' => $oldOwnerId,
                'old_owner_type' => $oldOwnerType,
                'new_owner_id' => $ownerId,
                'new_owner_type' => $ownerType,
                'assigned_by' => $options['assigned_by'] ?? null,
                'note' => $options['note'] ?? null
            ];
            
            $this->leadRepo->addEvent($leadId, LeadEventTypes::OWNER_CHANGED, $eventPayload);
            
            $this->db->commit();
            
            // Return updated lead
            return $this->leadRepo->getById($leadId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to assign owner: " . $e->getMessage());
            return ['error' => 'Failed to assign owner'];
        }
    }
    
    /**
     * Update deal fields on a lead
     * 
     * @param string $leadId Lead UUID
     * @param array $dealData Deal data (deal_value, currency, probability, expected_close_date)
     * @param array $options Additional options (changed_by)
     * @return array Updated lead or error
     */
    public function updateDeal($leadId, $dealData, $options = []) {
        // Get lead
        $lead = $this->leadRepo->getById($leadId);
        if (!$lead) {
            return ['error' => 'Lead not found'];
        }
        
        // Build update query dynamically
        $fields = [];
        $params = [];
        $changes = [];
        
        $allowedFields = ['deal_value', 'currency', 'probability', 'expected_close_date'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $dealData)) {
                $fields[] = "$field = ?";
                $params[] = $dealData[$field];
                $changes[$field] = [
                    'old' => $lead[$field] ?? null,
                    'new' => $dealData[$field]
                ];
            }
        }
        
        if (empty($fields)) {
            return $lead; // No changes
        }
        
        // Add updated_at
        $fields[] = "updated_at = datetime('now')";
        $params[] = $leadId;
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // Update lead
            $sql = "UPDATE leads SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->db->execute($sql, $params);
            
            // Record deal_updated event
            $eventPayload = [
                'changes' => $changes,
                'changed_by' => $options['changed_by'] ?? null
            ];
            
            $this->leadRepo->addEvent($leadId, LeadEventTypes::DEAL_UPDATED, $eventPayload);
            
            $this->db->commit();
            
            // Return updated lead
            return $this->leadRepo->getById($leadId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to update deal: " . $e->getMessage());
            return ['error' => 'Failed to update deal'];
        }
    }
    
    /**
     * Update lead inline (multiple fields at once)
     * 
     * @param string $leadId Lead UUID
     * @param array $data Field updates
     * @return array Updated lead or error
     */
    public function updateLeadInline($leadId, $data) {
        // Get lead
        $lead = $this->leadRepo->getById($leadId);
        if (!$lead) {
            return ['error' => 'Lead not found'];
        }
        
        $this->db->beginTransaction();
        
        try {
            $result = $lead;
            
            // Handle owner assignment
            if (isset($data['owner_id']) && isset($data['owner_type'])) {
                $ownerResult = $this->assignOwner(
                    $leadId,
                    $data['owner_id'],
                    $data['owner_type'],
                    [
                        'assigned_by' => $data['changed_by'] ?? null
                    ]
                );
                
                if (isset($ownerResult['error'])) {
                    throw new Exception($ownerResult['error']);
                }
                
                $result = $ownerResult;
            }
            
            // Handle deal updates
            $dealFields = ['deal_value', 'currency', 'probability', 'expected_close_date'];
            $dealData = [];
            foreach ($dealFields as $field) {
                if (array_key_exists($field, $data)) {
                    $dealData[$field] = $data[$field];
                }
            }
            
            if (!empty($dealData)) {
                $dealResult = $this->updateDeal(
                    $leadId,
                    $dealData,
                    ['changed_by' => $data['changed_by'] ?? null]
                );
                
                if (isset($dealResult['error'])) {
                    throw new Exception($dealResult['error']);
                }
                
                $result = $dealResult;
            }
            
            // Handle status and tags
            $simpleFields = [];
            $simpleParams = [];
            
            if (array_key_exists('status', $data)) {
                $simpleFields[] = "status = ?";
                $simpleParams[] = $data['status'];
            }
            
            if (array_key_exists('tags', $data)) {
                $simpleFields[] = "tags = ?";
                $simpleParams[] = is_array($data['tags']) ? json_encode($data['tags']) : $data['tags'];
            }
            
            if (!empty($simpleFields)) {
                $simpleFields[] = "updated_at = datetime('now')";
                $simpleParams[] = $leadId;
                
                $sql = "UPDATE leads SET " . implode(', ', $simpleFields) . " WHERE id = ?";
                $this->db->execute($sql, $simpleParams);
                
                // Record updated event
                $this->leadRepo->addEvent($leadId, LeadEventTypes::UPDATED, [
                    'fields' => array_keys($data),
                    'changed_by' => $data['changed_by'] ?? null
                ]);
            }
            
            $this->db->commit();
            
            // Return fresh lead data
            return $this->leadRepo->getById($leadId);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to update lead inline: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Add a note to a lead
     * 
     * @param string $leadId Lead UUID
     * @param string $text Note text
     * @param array $options Additional options (created_by)
     * @return array Event data or error
     */
    public function addNote($leadId, $text, $options = []) {
        // Validate
        if (empty(trim($text))) {
            return ['error' => 'Note text is required'];
        }
        
        // Get lead to verify it exists
        $lead = $this->leadRepo->getById($leadId);
        if (!$lead) {
            return ['error' => 'Lead not found'];
        }
        
        try {
            // Add note event
            $eventPayload = [
                'text' => $text,
                'created_by' => $options['created_by'] ?? null,
                'created_by_type' => $options['created_by_type'] ?? 'admin_user'
            ];
            
            $eventId = $this->leadRepo->addEvent($leadId, LeadEventTypes::NOTE, $eventPayload);
            
            return [
                'success' => true,
                'event_id' => $eventId,
                'message' => 'Note added'
            ];
            
        } catch (Exception $e) {
            error_log("Failed to add note: " . $e->getMessage());
            return ['error' => 'Failed to add note'];
        }
    }
    
    /**
     * Get leads for Kanban board grouped by stage
     * 
     * @param string $pipelineId Pipeline UUID
     * @param array $filters Filters (stage_ids, owner_id, min_score, q, limit)
     * @return array Pipeline with stages and leads
     */
    public function getLeadsBoard($pipelineId, $filters = []) {
        // Get pipeline with stages
        $pipeline = $this->pipelineService->getPipeline($pipelineId, true);
        if (!$pipeline) {
            return ['error' => 'Pipeline not found'];
        }
        
        // Get lead counts by stage
        $leadCounts = $this->pipelineService->getLeadCountByStage($pipelineId);
        
        // Build filters
        $whereConditions = ["pipeline_id = ?"];
        $params = [$pipelineId];
        
        // Filter by specific stages
        if (!empty($filters['stage_ids'])) {
            $stageIds = is_array($filters['stage_ids']) ? $filters['stage_ids'] : explode(',', $filters['stage_ids']);
            $placeholders = implode(',', array_fill(0, count($stageIds), '?'));
            $whereConditions[] = "stage_id IN ($placeholders)";
            $params = array_merge($params, $stageIds);
        }
        
        // Filter by owner
        if (!empty($filters['owner_id'])) {
            $whereConditions[] = "owner_id = ?";
            $params[] = $filters['owner_id'];
        }
        
        if (!empty($filters['owner_type'])) {
            $whereConditions[] = "owner_type = ?";
            $params[] = $filters['owner_type'];
        }
        
        // Filter by score
        if (isset($filters['min_score'])) {
            $whereConditions[] = "score >= ?";
            $params[] = (int)$filters['min_score'];
        }
        
        // Search query
        if (!empty($filters['q'])) {
            $searchTerm = '%' . $filters['q'] . '%';
            $whereConditions[] = "(name LIKE ? OR email LIKE ? OR company LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Tenant filtering
        if ($this->tenantId !== null) {
            $whereConditions[] = "(tenant_id = ? OR tenant_id IS NULL)";
            $params[] = $this->tenantId;
        }
        
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        
        // Get leads
        $sql = "SELECT * FROM leads 
            WHERE " . implode(' AND ', $whereConditions) . "
            ORDER BY updated_at DESC
            LIMIT ?";
        
        $params[] = $limit;
        
        $leads = $this->db->query($sql, $params);
        
        // Parse tags JSON
        foreach ($leads as &$lead) {
            if ($lead['tags']) {
                $lead['tags'] = json_decode($lead['tags'], true) ?? [];
            } else {
                $lead['tags'] = [];
            }
        }
        
        // Group leads by stage
        $leadsByStage = [];
        foreach ($leads as $lead) {
            $stageId = $lead['stage_id'];
            if (!isset($leadsByStage[$stageId])) {
                $leadsByStage[$stageId] = [];
            }
            $leadsByStage[$stageId][] = $lead;
        }
        
        // Attach leads to stages
        foreach ($pipeline['stages'] as &$stage) {
            $stage['lead_count'] = $leadCounts[$stage['id']] ?? 0;
            $stage['leads'] = $leadsByStage[$stage['id']] ?? [];
        }
        
        return [
            'pipeline' => $pipeline,
            'stages' => $pipeline['stages']
        ];
    }
    
    /**
     * Get assignment history for a lead
     * 
     * @param string $leadId Lead UUID
     * @return array List of assignments
     */
    public function getAssignmentHistory($leadId) {
        $sql = "SELECT * FROM crm_lead_assignments 
            WHERE lead_id = ?
            ORDER BY created_at DESC";
        
        return $this->db->query($sql, [$leadId]);
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
}
