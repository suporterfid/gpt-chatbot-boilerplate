<?php
/**
 * Webhook Log Repository - Data access layer for webhook delivery logs
 * Reference: docs/SPEC_WEBHOOK.md §§5 & 8
 */

require_once __DIR__ . '/DB.php';

class WebhookLogRepository {
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
     * Create a new log entry for a webhook delivery attempt
     * 
     * @param array $logData Log data (subscriber_id, event, request_body, etc.)
     * @return array The created log entry
     * @throws Exception on validation or database errors
     */
    public function createLog($logData) {
        $this->validateLogData($logData);
        
        // Generate UUID if not provided
        $id = $logData['id'] ?? $this->generateUUID();
        $now = date('c'); // ISO 8601 format
        
        // Ensure request_body is a JSON string
        $requestBody = is_array($logData['request_body']) 
            ? json_encode($logData['request_body']) 
            : $logData['request_body'];
        
        $sql = "INSERT INTO webhook_logs (
            id, subscriber_id, event, request_body, response_code, 
            response_body, attempts, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $logData['subscriber_id'],
            $logData['event'],
            $requestBody,
            $logData['response_code'] ?? null,
            $logData['response_body'] ?? null,
            $logData['attempts'] ?? 1,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getById($id);
    }
    
    /**
     * Update an existing log entry (e.g., after retry)
     * 
     * @param string $logId Log entry ID
     * @param array $updateData Data to update (response_code, response_body, attempts)
     * @return array|null The updated log entry
     * @throws Exception on validation or database errors
     */
    public function updateLog($logId, $updateData) {
        // Check if log exists
        $existing = $this->getById($logId);
        if (!$existing) {
            throw new Exception('Log entry not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        // Build update query dynamically
        if (isset($updateData['response_code'])) {
            $updates[] = 'response_code = ?';
            $params[] = $updateData['response_code'];
        }
        if (isset($updateData['response_body'])) {
            $updates[] = 'response_body = ?';
            $params[] = $updateData['response_body'];
        }
        if (isset($updateData['attempts'])) {
            $updates[] = 'attempts = ?';
            $params[] = (int)$updateData['attempts'];
        }
        
        if (empty($updates)) {
            return $existing;
        }
        
        $params[] = $logId;
        $sql = "UPDATE webhook_logs SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $this->db->execute($sql, $params);
        
        return $this->getById($logId);
    }
    
    /**
     * Get a log entry by ID
     * 
     * @param string $id Log entry ID
     * @return array|null The log entry or null if not found
     */
    public function getById($id) {
        $sql = "SELECT * FROM webhook_logs WHERE id = ?";
        $result = $this->db->getOne($sql, [$id]);
        
        if ($result) {
            // Parse JSON request_body field if needed
            $decoded = json_decode($result['request_body'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result['request_body'] = $decoded;
            }
        }
        
        return $result;
    }
    
    /**
     * List logs with optional filtering and pagination
     * 
     * @param array $filters Optional filters (subscriber_id, event, response_code)
     * @param int $limit Number of results per page (default: 50)
     * @param int $offset Offset for pagination (default: 0)
     * @return array List of log entries
     */
    public function listLogs($filters = [], $limit = 50, $offset = 0) {
        $sql = "SELECT * FROM webhook_logs";
        $params = [];
        $where = [];
        
        // Apply filters
        if (!empty($filters['subscriber_id'])) {
            $where[] = 'subscriber_id = ?';
            $params[] = $filters['subscriber_id'];
        }
        
        if (!empty($filters['event'])) {
            $where[] = 'event = ?';
            $params[] = $filters['event'];
        }
        
        if (isset($filters['response_code'])) {
            $where[] = 'response_code = ?';
            $params[] = (int)$filters['response_code'];
        }
        
        // Filter by outcome (success/failure)
        if (!empty($filters['outcome'])) {
            if ($filters['outcome'] === 'success') {
                $where[] = 'response_code >= 200 AND response_code < 300';
            } elseif ($filters['outcome'] === 'failure') {
                $where[] = '(response_code < 200 OR response_code >= 300 OR response_code IS NULL)';
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $results = $this->db->query($sql, $params);
        
        // Parse JSON request_body field for each result
        foreach ($results as &$result) {
            $decoded = json_decode($result['request_body'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result['request_body'] = $decoded;
            }
        }
        
        return $results;
    }
    
    /**
     * Get total count of logs (for pagination)
     * 
     * @param array $filters Optional filters (same as listLogs)
     * @return int Total count
     */
    public function countLogs($filters = []) {
        $sql = "SELECT COUNT(*) as count FROM webhook_logs";
        $params = [];
        $where = [];
        
        // Apply same filters as listLogs
        if (!empty($filters['subscriber_id'])) {
            $where[] = 'subscriber_id = ?';
            $params[] = $filters['subscriber_id'];
        }
        
        if (!empty($filters['event'])) {
            $where[] = 'event = ?';
            $params[] = $filters['event'];
        }
        
        if (isset($filters['response_code'])) {
            $where[] = 'response_code = ?';
            $params[] = (int)$filters['response_code'];
        }
        
        if (!empty($filters['outcome'])) {
            if ($filters['outcome'] === 'success') {
                $where[] = 'response_code >= 200 AND response_code < 300';
            } elseif ($filters['outcome'] === 'failure') {
                $where[] = '(response_code < 200 OR response_code >= 300 OR response_code IS NULL)';
            }
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $result = $this->db->getOne($sql, $params);
        
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * Get logs for a specific subscriber
     * 
     * @param string $subscriberId Subscriber ID
     * @param int $limit Number of results (default: 50)
     * @param int $offset Offset for pagination (default: 0)
     * @return array List of log entries
     */
    public function getLogsBySubscriber($subscriberId, $limit = 50, $offset = 0) {
        return $this->listLogs(['subscriber_id' => $subscriberId], $limit, $offset);
    }
    
    /**
     * Get logs for a specific event type
     * 
     * @param string $event Event type
     * @param int $limit Number of results (default: 50)
     * @param int $offset Offset for pagination (default: 0)
     * @return array List of log entries
     */
    public function getLogsByEvent($event, $limit = 50, $offset = 0) {
        return $this->listLogs(['event' => $event], $limit, $offset);
    }
    
    /**
     * Get delivery statistics
     * 
     * @param array $filters Optional filters (subscriber_id, event)
     * @return array Statistics (total, success, failure, avg_latency)
     */
    public function getStatistics($filters = []) {
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN response_code < 200 OR response_code >= 300 OR response_code IS NULL THEN 1 ELSE 0 END) as failure,
                AVG(attempts) as avg_attempts
            FROM webhook_logs";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['subscriber_id'])) {
            $where[] = 'subscriber_id = ?';
            $params[] = $filters['subscriber_id'];
        }
        
        if (!empty($filters['event'])) {
            $where[] = 'event = ?';
            $params[] = $filters['event'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $result = $this->db->getOne($sql, $params);
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'success' => (int)($result['success'] ?? 0),
            'failure' => (int)($result['failure'] ?? 0),
            'avg_attempts' => round((float)($result['avg_attempts'] ?? 0), 2)
        ];
    }
    
    /**
     * Validate log data
     * 
     * @param array $logData Log data
     * @throws Exception on validation errors
     */
    private function validateLogData($logData) {
        $required = ['subscriber_id', 'event', 'request_body'];
        
        foreach ($required as $field) {
            if (empty($logData[$field])) {
                throw new Exception("Missing required field: $field", 400);
            }
        }
        
        // Validate request_body is valid JSON if string
        if (is_string($logData['request_body'])) {
            $decoded = json_decode($logData['request_body'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('request_body must be valid JSON', 400);
            }
        }
    }
    
    /**
     * Generate a UUID v4
     * 
     * @return string UUID
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
