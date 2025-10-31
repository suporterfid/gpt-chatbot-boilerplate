<?php
/**
 * Webhook Handler - Process and verify incoming webhooks from OpenAI
 */

require_once __DIR__ . '/DB.php';

class WebhookHandler {
    private $db;
    private $signingSecret;
    
    public function __construct($db, $signingSecret = null) {
        $this->db = $db;
        $this->signingSecret = $signingSecret;
    }
    
    /**
     * Verify webhook signature
     * 
     * @param string $payload Raw request body
     * @param string $signature Signature from header
     * @return bool True if signature is valid
     */
    public function verifySignature($payload, $signature) {
        if (!$this->signingSecret) {
            // No signing secret configured, skip verification
            // In production, this should log a warning
            error_log("[WEBHOOK] Warning: No signing secret configured, skipping signature verification");
            return true;
        }
        
        // OpenAI uses HMAC-SHA256 for webhook signatures
        // Format: sha256=<hex_digest>
        if (!preg_match('/^sha256=([a-f0-9]+)$/i', $signature, $matches)) {
            error_log("[WEBHOOK] Invalid signature format: $signature");
            return false;
        }
        
        $expectedHash = $matches[1];
        $computedHash = hash_hmac('sha256', $payload, $this->signingSecret);
        
        // Constant-time comparison to prevent timing attacks
        return hash_equals($computedHash, $expectedHash);
    }
    
    /**
     * Check if event has already been processed (idempotency)
     * 
     * @param string $eventId Event ID from webhook
     * @return bool True if event was already processed
     */
    public function isEventProcessed($eventId) {
        $sql = "SELECT id FROM webhook_events WHERE event_id = ? AND processed = 1";
        $result = $this->db->queryOne($sql, [$eventId]);
        
        return $result !== null;
    }
    
    /**
     * Store webhook event for idempotency tracking
     * 
     * @param string $eventId Event ID
     * @param string $eventType Event type
     * @param array $payload Event payload
     * @return string Stored event ID
     */
    public function storeEvent($eventId, $eventType, $payload) {
        $id = $this->generateUUID();
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO webhook_events (
            id, event_id, event_type, payload_json, processed, created_at
        ) VALUES (?, ?, ?, ?, 0, ?)";
        
        try {
            $this->db->insert($sql, [
                $id,
                $eventId,
                $eventType,
                json_encode($payload),
                $now
            ]);
            
            return $id;
        } catch (Exception $e) {
            // Check if duplicate event_id (race condition)
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                error_log("[WEBHOOK] Duplicate event ID: $eventId (already being processed)");
                throw new Exception("Duplicate event", 409);
            }
            throw $e;
        }
    }
    
    /**
     * Mark event as processed
     * 
     * @param string $eventId Event ID
     */
    public function markEventProcessed($eventId) {
        $now = date('Y-m-d H:i:s');
        
        $sql = "UPDATE webhook_events 
                SET processed = 1, processed_at = ?
                WHERE event_id = ?";
        
        $this->db->execute($sql, [$now, $eventId]);
    }
    
    /**
     * Process webhook event based on type
     * 
     * @param array $event Parsed webhook event
     * @param object $vectorStoreService VectorStoreService instance (optional)
     * @return array Processing result
     */
    public function processEvent($event, $vectorStoreService = null) {
        $eventType = $event['type'] ?? 'unknown';
        $data = $event['data'] ?? [];
        
        switch ($eventType) {
            case 'vector_store.file.completed':
                return $this->handleVectorStoreFileCompleted($data, $vectorStoreService);
                
            case 'vector_store.file.failed':
                return $this->handleVectorStoreFileFailed($data, $vectorStoreService);
                
            case 'vector_store.completed':
                return $this->handleVectorStoreCompleted($data, $vectorStoreService);
                
            case 'file.uploaded':
                return $this->handleFileUploaded($data);
                
            default:
                error_log("[WEBHOOK] Unknown event type: $eventType");
                return ['status' => 'ignored', 'reason' => 'unknown_event_type'];
        }
    }
    
    /**
     * Handle vector_store.file.completed event
     */
    private function handleVectorStoreFileCompleted($data, $vectorStoreService) {
        $vectorStoreId = $data['vector_store_id'] ?? null;
        $fileId = $data['file_id'] ?? null;
        
        if (!$vectorStoreId || !$fileId) {
            throw new Exception("Missing vector_store_id or file_id in event data");
        }
        
        // Update file status in DB
        if ($vectorStoreService) {
            // Find our DB file ID by OpenAI file ID
            $dbFile = $vectorStoreService->findFileByOpenAIId($fileId);
            if ($dbFile) {
                $vectorStoreService->updateFileIngestionStatus($dbFile['id'], 'completed');
            }
        }
        
        // Log to audit log
        $this->logAuditEvent('webhook.vector_store_file.completed', [
            'vector_store_id' => $vectorStoreId,
            'file_id' => $fileId
        ]);
        
        return ['status' => 'processed', 'file_status' => 'completed'];
    }
    
    /**
     * Handle vector_store.file.failed event
     */
    private function handleVectorStoreFileFailed($data, $vectorStoreService) {
        $vectorStoreId = $data['vector_store_id'] ?? null;
        $fileId = $data['file_id'] ?? null;
        $error = $data['last_error'] ?? 'Unknown error';
        
        if (!$vectorStoreId || !$fileId) {
            throw new Exception("Missing vector_store_id or file_id in event data");
        }
        
        // Update file status in DB
        if ($vectorStoreService) {
            $dbFile = $vectorStoreService->findFileByOpenAIId($fileId);
            if ($dbFile) {
                $vectorStoreService->updateFileIngestionStatus($dbFile['id'], 'failed', $error);
            }
        }
        
        // Log to audit log
        $this->logAuditEvent('webhook.vector_store_file.failed', [
            'vector_store_id' => $vectorStoreId,
            'file_id' => $fileId,
            'error' => $error
        ]);
        
        return ['status' => 'processed', 'file_status' => 'failed'];
    }
    
    /**
     * Handle vector_store.completed event
     */
    private function handleVectorStoreCompleted($data, $vectorStoreService) {
        $vectorStoreId = $data['id'] ?? null;
        
        if (!$vectorStoreId) {
            throw new Exception("Missing vector_store_id in event data");
        }
        
        // Update vector store status in DB
        if ($vectorStoreService) {
            $dbStore = $vectorStoreService->findStoreByOpenAIId($vectorStoreId);
            if ($dbStore) {
                $vectorStoreService->updateStoreStatus($dbStore['id'], 'completed');
            }
        }
        
        // Log to audit log
        $this->logAuditEvent('webhook.vector_store.completed', [
            'vector_store_id' => $vectorStoreId
        ]);
        
        return ['status' => 'processed', 'store_status' => 'completed'];
    }
    
    /**
     * Handle file.uploaded event
     */
    private function handleFileUploaded($data) {
        $fileId = $data['id'] ?? null;
        
        // Log to audit log
        $this->logAuditEvent('webhook.file.uploaded', [
            'file_id' => $fileId
        ]);
        
        return ['status' => 'logged'];
    }
    
    /**
     * Log event to audit log
     */
    private function logAuditEvent($action, $payload) {
        $sql = "INSERT INTO audit_log (actor, action, payload_json, created_at) 
                VALUES ('system', ?, ?, ?)";
        
        try {
            $this->db->execute($sql, [
                $action,
                json_encode($payload),
                date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("[WEBHOOK] Failed to log audit event: " . $e->getMessage());
        }
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
