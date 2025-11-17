<?php
/**
 * Webhook Subscriber Repository - Data access layer for webhook subscribers
 * Reference: docs/SPEC_WEBHOOK.md §§5 & 8
 */

require_once __DIR__ . '/DB.php';

class WebhookSubscriberRepository {
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
     * List all active subscribers for a specific event type
     * Used by webhook dispatcher for fan-out logic
     * 
     * @param string $eventType The event type to filter by
     * @return array List of active subscribers
     */
    public function listActiveByEvent($eventType) {
        // Use LIKE to search within JSON array string
        // Events are stored as JSON: ["event1", "event2"]
        $sql = "SELECT * FROM webhook_subscribers 
                WHERE active = 1 
                AND (events LIKE ? OR events LIKE ? OR events LIKE ?)";
        
        // Match patterns: ["eventType"], ["eventType", or ,"eventType"]
        $params = [
            '%"' . $eventType . '"%',
            '%[' . $eventType . '%',
            '%,' . $eventType . '%'
        ];
        
        $results = $this->db->query($sql, $params);
        
        // Parse JSON events field for each result
        foreach ($results as &$result) {
            $result['events'] = json_decode($result['events'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Save a subscriber (insert or update)
     * 
     * @param array $subscriber Subscriber data
     * @return array The saved subscriber
     * @throws Exception on validation or database errors
     */
    public function save($subscriber) {
        // Check if this is an update or insert
        $isUpdate = !empty($subscriber['id']);
        
        if ($isUpdate) {
            // For updates, validate only if required fields are provided
            return $this->update($subscriber);
        } else {
            // For inserts, validate all required fields
            $this->validateSubscriber($subscriber);
            return $this->insert($subscriber);
        }
    }
    
    /**
     * Insert a new subscriber
     */
    private function insert($subscriber) {
        // Generate UUID if not provided
        $id = $subscriber['id'] ?? $this->generateUUID();
        $now = date('c'); // ISO 8601 format
        
        // Ensure events is a JSON string
        $events = is_array($subscriber['events']) 
            ? json_encode($subscriber['events']) 
            : $subscriber['events'];
        
        $sql = "INSERT INTO webhook_subscribers (
            id, client_id, url, secret, events, active, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $subscriber['client_id'],
            $subscriber['url'],
            $subscriber['secret'],
            $events,
            isset($subscriber['active']) ? (int)(bool)$subscriber['active'] : 1,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getById($id);
    }
    
    /**
     * Update an existing subscriber
     */
    private function update($subscriber) {
        $id = $subscriber['id'];
        
        // Check if subscriber exists
        $existing = $this->getById($id);
        if (!$existing) {
            throw new Exception('Subscriber not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        // Build update query dynamically
        if (isset($subscriber['client_id'])) {
            $updates[] = 'client_id = ?';
            $params[] = $subscriber['client_id'];
        }
        if (isset($subscriber['url'])) {
            // Validate URL format
            if (!filter_var($subscriber['url'], FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL format', 400);
            }
            $updates[] = 'url = ?';
            $params[] = $subscriber['url'];
        }
        if (isset($subscriber['secret'])) {
            $updates[] = 'secret = ?';
            $params[] = $subscriber['secret'];
        }
        if (isset($subscriber['events'])) {
            // Validate events
            if (is_string($subscriber['events'])) {
                $decoded = json_decode($subscriber['events'], true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    throw new Exception('Events must be a valid JSON array', 400);
                }
                if (empty($decoded)) {
                    throw new Exception('Events array cannot be empty', 400);
                }
            } elseif (is_array($subscriber['events']) && empty($subscriber['events'])) {
                throw new Exception('Events array cannot be empty', 400);
            }
            
            $updates[] = 'events = ?';
            $events = is_array($subscriber['events']) 
                ? json_encode($subscriber['events']) 
                : $subscriber['events'];
            $params[] = $events;
        }
        if (isset($subscriber['active'])) {
            $updates[] = 'active = ?';
            $params[] = (int)(bool)$subscriber['active'];
        }
        
        if (empty($updates)) {
            return $existing;
        }
        
        $params[] = $id;
        $sql = "UPDATE webhook_subscribers SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $this->db->execute($sql, $params);
        
        return $this->getById($id);
    }
    
    /**
     * Get a subscriber by ID
     * 
     * @param string $id Subscriber ID
     * @return array|null The subscriber or null if not found
     */
    public function getById($id) {
        $sql = "SELECT * FROM webhook_subscribers WHERE id = ?";
        $result = $this->db->getOne($sql, [$id]);
        
        if ($result) {
            // Parse JSON events field
            $result['events'] = json_decode($result['events'], true) ?? [];
        }
        
        return $result;
    }
    
    /**
     * List all subscribers (optionally filtered by active status)
     * 
     * @param bool|null $active Filter by active status (null = all)
     * @return array List of subscribers
     */
    public function listAll($active = null) {
        $sql = "SELECT * FROM webhook_subscribers";
        $params = [];
        
        if ($active !== null) {
            $sql .= " WHERE active = ?";
            $params[] = (int)(bool)$active;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $results = $this->db->query($sql, $params);
        
        // Parse JSON events field for each result
        foreach ($results as &$result) {
            $result['events'] = json_decode($result['events'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Delete a subscriber
     * 
     * @param string $id Subscriber ID
     * @return bool Success status
     */
    public function delete($id) {
        $sql = "DELETE FROM webhook_subscribers WHERE id = ?";
        $rowCount = $this->db->execute($sql, [$id]);
        
        return $rowCount > 0;
    }
    
    /**
     * Deactivate a subscriber (soft delete)
     * 
     * @param string $id Subscriber ID
     * @return array|null The updated subscriber
     */
    public function deactivate($id) {
        $sql = "UPDATE webhook_subscribers SET active = 0 WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return $this->getById($id);
    }
    
    /**
     * Activate a subscriber
     * 
     * @param string $id Subscriber ID
     * @return array|null The updated subscriber
     */
    public function activate($id) {
        $sql = "UPDATE webhook_subscribers SET active = 1 WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return $this->getById($id);
    }
    
    /**
     * Validate subscriber data
     * 
     * @param array $subscriber Subscriber data
     * @throws Exception on validation errors
     */
    private function validateSubscriber($subscriber) {
        $required = ['client_id', 'url', 'secret', 'events'];
        
        foreach ($required as $field) {
            if (empty($subscriber[$field])) {
                throw new Exception("Missing required field: $field", 400);
            }
        }
        
        // Validate URL format
        if (!filter_var($subscriber['url'], FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid URL format', 400);
        }
        
        // Validate events is an array or valid JSON array string
        if (is_string($subscriber['events'])) {
            $decoded = json_decode($subscriber['events'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new Exception('Events must be a valid JSON array', 400);
            }
        } elseif (!is_array($subscriber['events'])) {
            throw new Exception('Events must be an array', 400);
        }
        
        // Validate events array is not empty
        $events = is_array($subscriber['events']) 
            ? $subscriber['events'] 
            : json_decode($subscriber['events'], true);
        
        if (empty($events)) {
            throw new Exception('Events array cannot be empty', 400);
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
