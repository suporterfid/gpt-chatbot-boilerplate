<?php
/**
 * BoardService - Manages CRM Kanban board operations
 * 
 * Handles lead board views, moving leads between stages, and inline updates
 */

require_once __DIR__ . '/../../DB.php';
require_once __DIR__ . '/../LeadRepository.php';
require_once __DIR__ . '/../LeadEventTypes.php';

class BoardService {
    private $db;
    private $leadRepository;
    private $tenantId;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
        $this->leadRepository = new LeadRepository(['database_path' => null], $tenantId);
        $this->leadRepository->setDb($db);
    }
    
    /**
     * Set tenant context for tenant-scoped queries
     */
    public function setTenantId($tenantId) {
        $this->tenantId = $tenantId;
        $this->leadRepository->setTenantId($tenantId);
    }
    
    /**
     * Get leads organized by pipeline and stages (Kanban board view)
     * 
     * @param string $pipelineId Pipeline UUID
     * @param array $filters Optional filters (stage_ids, owner_id, owner_type, min_score, q)
     * @param array $pagination Page and page_size for each stage
     * @return array Board data with pipeline, stages, and leads
     */
    public function getLeadsBoard($pipelineId, $filters = [], $pagination = []) {
        // Get pipeline details
        $sql = "SELECT * FROM crm_pipelines WHERE id = ? AND archived_at IS NULL";
        $pipeline = $this->db->getOne($sql, [$pipelineId]);
        
        if (!$pipeline) {
            return ['error' => 'Pipeline not found'];
        }
        
        // Get stages for this pipeline
        $stageSql = "SELECT * FROM crm_pipeline_stages 
                     WHERE pipeline_id = ? AND archived_at IS NULL 
                     ORDER BY position ASC";
        $stages = $this->db->query($stageSql, [$pipelineId]);
        
        $page = (int)($pagination['page'] ?? 1);
        $pageSize = (int)($pagination['page_size'] ?? 50);
        $offset = ($page - 1) * $pageSize;
        
        // For each stage, get leads
        $boardStages = [];
        foreach ($stages as $stage) {
            // Build lead query
            $leadSql = "SELECT l.*, 
                        (SELECT COUNT(*) FROM lead_events WHERE lead_id = l.id) as conversation_count,
                        (SELECT MAX(created_at) FROM lead_events WHERE lead_id = l.id) as last_activity_at
                        FROM leads l
                        WHERE l.pipeline_id = ? AND l.stage_id = ?";
            
            $params = [$pipelineId, $stage['id']];
            
            // Apply filters
            if (!empty($filters['owner_id'])) {
                $leadSql .= " AND l.owner_id = ?";
                $params[] = $filters['owner_id'];
            }
            
            if (!empty($filters['owner_type'])) {
                $leadSql .= " AND l.owner_type = ?";
                $params[] = $filters['owner_type'];
            }
            
            if (isset($filters['min_score'])) {
                $leadSql .= " AND l.score >= ?";
                $params[] = (int)$filters['min_score'];
            }
            
            if (!empty($filters['q'])) {
                $searchTerm = '%' . $filters['q'] . '%';
                $leadSql .= " AND (l.name LIKE ? OR l.email LIKE ? OR l.company LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Order and pagination
            $leadSql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $pageSize;
            $params[] = $offset;
            
            $leads = $this->db->query($leadSql, $params);
            
            // Get total count for this stage
            $countSql = "SELECT COUNT(*) as total FROM leads l 
                         WHERE l.pipeline_id = ? AND l.stage_id = ?";
            $countParams = [$pipelineId, $stage['id']];
            
            if (!empty($filters['owner_id'])) {
                $countSql .= " AND l.owner_id = ?";
                $countParams[] = $filters['owner_id'];
            }
            
            if (!empty($filters['owner_type'])) {
                $countSql .= " AND l.owner_type = ?";
                $countParams[] = $filters['owner_type'];
            }
            
            if (isset($filters['min_score'])) {
                $countSql .= " AND l.score >= ?";
                $countParams[] = (int)$filters['min_score'];
            }
            
            if (!empty($filters['q'])) {
                $searchTerm = '%' . $filters['q'] . '%';
                $countSql .= " AND (l.name LIKE ? OR l.email LIKE ? OR l.company LIKE ?)";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }
            
            $countResult = $this->db->getOne($countSql, $countParams);
            $totalCount = (int)($countResult['total'] ?? 0);
            
            // Format leads for board display
            $formattedLeads = array_map(function($lead) {
                // Parse tags if JSON
                if (!empty($lead['tags'])) {
                    $tags = json_decode($lead['tags'], true);
                    $lead['tags'] = is_array($tags) ? $tags : [];
                } else {
                    $lead['tags'] = [];
                }
                
                // Get owner info if available
                $lead['owner'] = null;
                if ($lead['owner_id']) {
                    $lead['owner'] = [
                        'id' => $lead['owner_id'],
                        'type' => $lead['owner_type'],
                        'name' => $this->getOwnerName($lead['owner_id'], $lead['owner_type'])
                    ];
                }
                
                return $lead;
            }, $leads);
            
            $boardStages[] = [
                'id' => $stage['id'],
                'name' => $stage['name'],
                'slug' => $stage['slug'],
                'position' => (int)$stage['position'],
                'color' => $stage['color'],
                'is_won' => (bool)$stage['is_won'],
                'is_lost' => (bool)$stage['is_lost'],
                'is_closed' => (bool)$stage['is_closed'],
                'lead_count' => $totalCount,
                'leads' => $formattedLeads
            ];
        }
        
        return [
            'pipeline' => $pipeline,
            'stages' => $boardStages
        ];
    }
    
    /**
     * Move a lead from one stage to another
     * 
     * @param string $leadId Lead UUID
     * @param string $fromStageId Current stage UUID
     * @param string $toStageId Target stage UUID
     * @param string $pipelineId Pipeline UUID (for validation)
     * @param array $metadata Additional metadata (e.g., changed_by, position)
     * @return array Updated lead or error
     */
    public function moveLead($leadId, $fromStageId, $toStageId, $pipelineId, $metadata = []) {
        // Validate lead exists and is in the correct stage
        $leadSql = "SELECT * FROM leads WHERE id = ? AND pipeline_id = ? AND stage_id = ?";
        $lead = $this->db->getOne($leadSql, [$leadId, $pipelineId, $fromStageId]);
        
        if (!$lead) {
            return ['error' => 'Lead not found or stage mismatch'];
        }
        
        // Validate target stage exists and belongs to the pipeline
        $stageSql = "SELECT * FROM crm_pipeline_stages WHERE id = ? AND pipeline_id = ?";
        $targetStage = $this->db->getOne($stageSql, [$toStageId, $pipelineId]);
        
        if (!$targetStage) {
            return ['error' => 'Target stage not found or does not belong to pipeline'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // Update lead stage
            $updateSql = "UPDATE leads SET stage_id = ?, updated_at = datetime('now') WHERE id = ?";
            $this->db->execute($updateSql, [$toStageId, $leadId]);
            
            // Create stage_changed event
            $eventData = [
                'lead_id' => $leadId,
                'type' => LeadEventTypes::STAGE_CHANGED,
                'payload_json' => json_encode([
                    'old_stage_id' => $fromStageId,
                    'new_stage_id' => $toStageId,
                    'pipeline_id' => $pipelineId,
                    'changed_by' => $metadata['changed_by'] ?? null,
                    'changed_by_type' => $metadata['changed_by_type'] ?? 'admin_user'
                ])
            ];
            
            $this->createLeadEvent($eventData);
            
            $this->db->commit();
            
            // Fetch updated lead
            $updatedLead = $this->db->getOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
            
            return ['lead' => $updatedLead];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to move lead: " . $e->getMessage());
            return ['error' => 'Failed to move lead'];
        }
    }
    
    /**
     * Update lead inline (from Kanban card)
     * 
     * @param string $leadId Lead UUID
     * @param array $data Update data
     * @return array Updated lead or error
     */
    public function updateLeadInline($leadId, $data) {
        // Verify lead exists
        $lead = $this->db->getOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
        
        if (!$lead) {
            return ['error' => 'Lead not found'];
        }
        
        $this->db->beginTransaction();
        
        try {
            $updates = [];
            $params = [];
            $events = [];
            
            // Handle owner change
            if (isset($data['owner_id']) || isset($data['owner_type'])) {
                $oldOwnerId = $lead['owner_id'];
                $oldOwnerType = $lead['owner_type'];
                $newOwnerId = $data['owner_id'] ?? $oldOwnerId;
                $newOwnerType = $data['owner_type'] ?? $oldOwnerType;
                
                if ($oldOwnerId !== $newOwnerId || $oldOwnerType !== $newOwnerType) {
                    $updates[] = 'owner_id = ?';
                    $params[] = $newOwnerId;
                    $updates[] = 'owner_type = ?';
                    $params[] = $newOwnerType;
                    
                    $events[] = [
                        'type' => LeadEventTypes::OWNER_CHANGED,
                        'payload' => [
                            'old_owner_id' => $oldOwnerId,
                            'old_owner_type' => $oldOwnerType,
                            'new_owner_id' => $newOwnerId,
                            'new_owner_type' => $newOwnerType
                        ]
                    ];
                }
            }
            
            // Handle deal updates
            $dealFields = ['deal_value', 'currency', 'probability', 'expected_close_date'];
            $dealChanged = false;
            $dealPayload = [];
            
            foreach ($dealFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                    $dealPayload['old_' . $field] = $lead[$field];
                    $dealPayload['new_' . $field] = $data[$field];
                    $dealChanged = true;
                }
            }
            
            if ($dealChanged) {
                $events[] = [
                    'type' => LeadEventTypes::DEAL_UPDATED,
                    'payload' => $dealPayload
                ];
            }
            
            // Handle status change
            if (isset($data['status']) && $data['status'] !== $lead['status']) {
                $updates[] = 'status = ?';
                $params[] = $data['status'];
            }
            
            // Handle tags
            if (array_key_exists('tags', $data)) {
                $updates[] = 'tags = ?';
                $params[] = is_array($data['tags']) ? json_encode($data['tags']) : $data['tags'];
            }
            
            // If no updates, return current lead
            if (empty($updates)) {
                $this->db->rollBack();
                return ['lead' => $lead];
            }
            
            // Add updated_at
            $updates[] = "updated_at = datetime('now')";
            $params[] = $leadId;
            
            // Execute update
            $updateSql = "UPDATE leads SET " . implode(', ', $updates) . " WHERE id = ?";
            $this->db->execute($updateSql, $params);
            
            // Create events
            foreach ($events as $event) {
                $this->createLeadEvent([
                    'lead_id' => $leadId,
                    'type' => $event['type'],
                    'payload_json' => json_encode($event['payload'])
                ]);
            }
            
            $this->db->commit();
            
            // Fetch updated lead
            $updatedLead = $this->db->getOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
            
            return ['lead' => $updatedLead];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to update lead: " . $e->getMessage());
            return ['error' => 'Failed to update lead'];
        }
    }
    
    /**
     * Add a note to a lead
     * 
     * @param string $leadId Lead UUID
     * @param string $text Note text
     * @param array $metadata Optional metadata (created_by, created_by_type)
     * @return array Success or error
     */
    public function addNote($leadId, $text, $metadata = []) {
        // Verify lead exists
        $lead = $this->db->getOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
        
        if (!$lead) {
            return ['error' => 'Lead not found'];
        }
        
        try {
            $this->createLeadEvent([
                'lead_id' => $leadId,
                'type' => LeadEventTypes::NOTE,
                'payload_json' => json_encode([
                    'text' => $text,
                    'created_by' => $metadata['created_by'] ?? null,
                    'created_by_type' => $metadata['created_by_type'] ?? 'admin_user'
                ])
            ]);
            
            return ['success' => true, 'message' => 'Note added'];
            
        } catch (Exception $e) {
            error_log("Failed to add note: " . $e->getMessage());
            return ['error' => 'Failed to add note'];
        }
    }
    
    /**
     * Create a lead event
     * 
     * @param array $data Event data
     * @return void
     */
    private function createLeadEvent($data) {
        $id = $this->generateId();
        
        $sql = "INSERT INTO lead_events (id, lead_id, type, payload_json, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))";
        
        $this->db->execute($sql, [
            $id,
            $data['lead_id'],
            $data['type'],
            $data['payload_json']
        ]);
    }
    
    /**
     * Get owner name by ID and type
     * 
     * @param string $ownerId
     * @param string $ownerType
     * @return string|null
     */
    private function getOwnerName($ownerId, $ownerType) {
        if ($ownerType === 'admin_user') {
            $sql = "SELECT email, name FROM admin_users WHERE id = ?";
            $user = $this->db->getOne($sql, [$ownerId]);
            if ($user) {
                return $user['name'] ?? $user['email'];
            }
        }
        
        // For other types (agent, external), return ID for now
        return $ownerId;
    }
    
    /**
     * Generate UUID v4
     */
    private function generateId() {
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
