<?php
/**
 * LeadRepository - Database operations for leads
 * 
 * Handles CRUD operations for leads, events, and scores
 */

require_once __DIR__ . '/../DB.php';

class LeadRepository {
    private $db;
    private $config;
    private $tenantId;
    
    public function __construct($config = [], $tenantId = null) {
        $this->config = $config;
        $this->tenantId = $tenantId;
        $dbConfig = [
            'database_url' => $config['database_url'] ?? null,
            'database_path' => $config['database_path'] ?? __DIR__ . '/../../data/chatbot.db'
        ];
        $this->db = new DB($dbConfig);
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
     * Create or update a lead
     * 
     * @param array $leadData Lead attributes
     * @return string Lead ID
     */
    public function createOrUpdateLead($leadData) {
        // Check if lead exists for this conversation
        $existingLead = $this->findByConversation(
            $leadData['agent_id'] ?? null,
            $leadData['conversation_id']
        );
        
        if ($existingLead) {
            // Update existing lead
            return $this->updateLead($existingLead['id'], $leadData);
        } else {
            // Create new lead
            return $this->createLead($leadData);
        }
    }
    
    /**
     * Create a new lead
     * 
     * @param array $leadData
     * @return string Lead ID
     */
    private function createLead($leadData) {
        $id = $this->generateId();
        
        $sql = "INSERT INTO leads (
            id, agent_id, conversation_id, name, company, role, 
            email, phone, industry, company_size, interest, 
            intent_level, score, qualified, status, source_channel, tenant_id, extras_json
        ) VALUES (
            :id, :agent_id, :conversation_id, :name, :company, :role,
            :email, :phone, :industry, :company_size, :interest,
            :intent_level, :score, :qualified, :status, :source_channel, :tenant_id, :extras_json
        )";
        
        $this->db->execute($sql, [
            'id' => $id,
            'agent_id' => $leadData['agent_id'] ?? null,
            'conversation_id' => $leadData['conversation_id'],
            'name' => $leadData['name'] ?? null,
            'company' => $leadData['company'] ?? null,
            'role' => $leadData['role'] ?? null,
            'email' => $leadData['email'] ?? null,
            'phone' => $leadData['phone'] ?? null,
            'industry' => $leadData['industry'] ?? null,
            'company_size' => $leadData['company_size'] ?? null,
            'interest' => $leadData['interest'] ?? null,
            'intent_level' => $leadData['intent_level'] ?? 'none',
            'score' => $leadData['score'] ?? 0,
            'qualified' => $leadData['qualified'] ? 1 : 0,
            'status' => $leadData['status'] ?? 'new',
            'source_channel' => $leadData['source_channel'] ?? 'web',
            'tenant_id' => $leadData['tenant_id'] ?? $this->tenantId,
            'extras_json' => isset($leadData['extras']) ? json_encode($leadData['extras']) : null
        ]);
        
        return $id;
    }
    
    /**
     * Update an existing lead
     * 
     * @param string $leadId
     * @param array $leadData
     * @return string Lead ID
     */
    private function updateLead($leadId, $leadData) {
        $updates = [];
        $params = ['id' => $leadId];
        
        // Build dynamic update query
        $updateFields = [
            'name', 'company', 'role', 'email', 'phone',
            'industry', 'company_size', 'interest', 'intent_level',
            'score', 'qualified', 'status'
        ];
        
        foreach ($updateFields as $field) {
            if (array_key_exists($field, $leadData)) {
                $updates[] = "$field = :$field";
                $value = $leadData[$field];
                if ($field === 'qualified') {
                    $value = $value ? 1 : 0;
                }
                $params[$field] = $value;
            }
        }
        
        if (isset($leadData['extras'])) {
            $updates[] = "extras_json = :extras_json";
            $params['extras_json'] = json_encode($leadData['extras']);
        }
        
        if (!empty($updates)) {
            $updates[] = "updated_at = datetime('now')";
            $sql = "UPDATE leads SET " . implode(', ', $updates) . " WHERE id = :id";
            $this->db->execute($sql, $params);
        }
        
        return $leadId;
    }
    
    /**
     * Find lead by conversation ID
     * 
     * @param string|null $agentId
     * @param string $conversationId
     * @return array|null
     */
    public function findByConversation($agentId, $conversationId) {
        $sql = "SELECT * FROM leads WHERE conversation_id = :conversation_id";
        $params = ['conversation_id' => $conversationId];
        
        if ($agentId) {
            $sql .= " AND agent_id = :agent_id";
            $params['agent_id'] = $agentId;
        }
        
        $sql .= " LIMIT 1";
        
        $results = $this->db->query($sql, $params);
        return !empty($results) ? $this->hydrateExtras($results[0]) : null;
    }
    
    /**
     * Get lead by ID
     * 
     * @param string $leadId
     * @return array|null
     */
    public function getById($leadId) {
        $sql = "SELECT * FROM leads WHERE id = :id LIMIT 1";
        $results = $this->db->query($sql, ['id' => $leadId]);
        return !empty($results) ? $this->hydrateExtras($results[0]) : null;
    }
    
    /**
     * List leads with filters
     * 
     * @param array $filters
     * @return array
     */
    public function list($filters = []) {
        $sql = "SELECT * FROM leads WHERE 1=1";
        $params = [];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $this->tenantId;
        }
        
        if (isset($filters['agent_id'])) {
            $sql .= " AND agent_id = :agent_id";
            $params['agent_id'] = $filters['agent_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['qualified'])) {
            $sql .= " AND qualified = :qualified";
            $params['qualified'] = $filters['qualified'] ? 1 : 0;
        }
        
        if (isset($filters['min_score'])) {
            $sql .= " AND score >= :min_score";
            $params['min_score'] = $filters['min_score'];
        }
        
        if (isset($filters['from_date'])) {
            $sql .= " AND created_at >= :from_date";
            $params['from_date'] = $filters['from_date'];
        }
        
        if (isset($filters['to_date'])) {
            $sql .= " AND created_at <= :to_date";
            $params['to_date'] = $filters['to_date'];
        }
        
        // Search query
        if (isset($filters['q']) && !empty($filters['q'])) {
            $sql .= " AND (name LIKE :q OR company LIKE :q OR email LIKE :q OR interest LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }
        
        // Ordering
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = ($filters['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderBy $orderDir";
        
        // Pagination
        $limit = min((int)($filters['limit'] ?? 50), 100);
        $offset = (int)($filters['offset'] ?? 0);
        $sql .= " LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;
        
        $results = $this->db->query($sql, $params);
        return array_map([$this, 'hydrateExtras'], $results);
    }
    
    /**
     * Add an event to a lead
     * 
     * @param string $leadId
     * @param string $type Event type
     * @param array|null $payload Event data
     * @return string Event ID
     */
    public function addEvent($leadId, $type, $payload = null) {
        $id = $this->generateId();
        
        $sql = "INSERT INTO lead_events (id, lead_id, type, payload_json) 
                VALUES (:id, :lead_id, :type, :payload_json)";
        
        $this->db->execute($sql, [
            'id' => $id,
            'lead_id' => $leadId,
            'type' => $type,
            'payload_json' => $payload ? json_encode($payload) : null
        ]);
        
        return $id;
    }

    /**
     * Count notified events created since the start of the current UTC day
     *
     * @param string|null $tenantId Optional tenant scope override
     * @return int
     */
    public function countDailyNotifiedEvents($tenantId = null) {
        $effectiveTenantId = $tenantId ?? $this->tenantId;

        $startOfDay = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTime(0, 0, 0)
            ->format('Y-m-d H:i:s');

        $sql = "SELECT COUNT(*) AS notification_count"
             . " FROM lead_events le"
             . " INNER JOIN leads l ON l.id = le.lead_id"
             . " WHERE le.type = :type"
             . " AND le.created_at >= :start_of_day";

        $params = [
            'type' => 'notified',
            'start_of_day' => $startOfDay
        ];

        if ($effectiveTenantId !== null) {
            $sql .= " AND l.tenant_id = :tenant_id";
            $params['tenant_id'] = $effectiveTenantId;
        }

        $result = $this->db->getOne($sql, $params);

        return (int)($result['notification_count'] ?? 0);
    }

    /**
     * Add a score snapshot
     * 
     * @param string $leadId
     * @param int $score
     * @param array|null $rationale
     * @return string Score ID
     */
    public function addScoreSnapshot($leadId, $score, $rationale = null) {
        $id = $this->generateId();
        
        $sql = "INSERT INTO lead_scores (id, lead_id, score, rationale_json) 
                VALUES (:id, :lead_id, :score, :rationale_json)";
        
        $this->db->execute($sql, [
            'id' => $id,
            'lead_id' => $leadId,
            'score' => $score,
            'rationale_json' => $rationale ? json_encode($rationale) : null
        ]);
        
        return $id;
    }
    
    /**
     * Get events for a lead
     * 
     * @param string $leadId
     * @return array
     */
    public function getEvents($leadId) {
        $sql = "SELECT * FROM lead_events WHERE lead_id = :lead_id ORDER BY created_at DESC";
        $results = $this->db->query($sql, ['lead_id' => $leadId]);
        
        return array_map(function($event) {
            if (isset($event['payload_json'])) {
                $event['payload'] = json_decode($event['payload_json'], true);
            }
            return $event;
        }, $results);
    }
    
    /**
     * Get score history for a lead
     * 
     * @param string $leadId
     * @return array
     */
    public function getScoreHistory($leadId) {
        $sql = "SELECT * FROM lead_scores WHERE lead_id = :lead_id ORDER BY created_at DESC";
        $results = $this->db->query($sql, ['lead_id' => $leadId]);
        
        return array_map(function($score) {
            if (isset($score['rationale_json'])) {
                $score['rationale'] = json_decode($score['rationale_json'], true);
            }
            return $score;
        }, $results);
    }
    
    /**
     * Generate a UUID v4
     * 
     * @return string
     */
    private function generateId() {
        // Use random_bytes for cryptographically secure UUID generation
        $data = random_bytes(16);
        
        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Hydrate extras_json field
     * 
     * @param array $lead
     * @return array
     */
    private function hydrateExtras($lead) {
        if (isset($lead['extras_json']) && !empty($lead['extras_json'])) {
            $lead['extras'] = json_decode($lead['extras_json'], true);
        }
        return $lead;
    }
}
