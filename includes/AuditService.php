<?php
/**
 * Audit Service for Conversation Tracking
 * Handles all audit trail persistence with encryption and PII redaction
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/PIIRedactor.php';
require_once __DIR__ . '/CryptoAdapter.php';

class AuditService {
    private $db;
    private $config;
    private $redactor;
    private $crypto;
    private $enabled;
    private $encryptAtRest;
    
    public function __construct($config = []) {
        $this->config = $config;
        $this->enabled = $config['enabled'] ?? true;
        $this->encryptAtRest = $config['encrypt_at_rest'] ?? true;
        
        if (!$this->enabled) {
            return;
        }
        
        // Initialize database
        $dbConfig = [
            'database_url' => $config['database_url'] ?? null,
            'database_path' => $config['database_path'] ?? __DIR__ . '/../data/chatbot.db'
        ];
        $this->db = new DB($dbConfig);
        
        // Initialize PII redactor
        $this->redactor = new PIIRedactor([
            'pii_redaction_patterns' => $config['pii_redaction_patterns'] ?? ''
        ]);
        
        // Initialize crypto adapter if encryption is enabled
        if ($this->encryptAtRest && !empty($config['encryption_key'])) {
            try {
                $this->crypto = new CryptoAdapter([
                    'encryption_key' => $config['encryption_key']
                ]);
            } catch (Exception $e) {
                error_log('AuditService: Failed to initialize crypto: ' . $e->getMessage());
                $this->encryptAtRest = false;
            }
        } else {
            $this->encryptAtRest = false;
        }
    }
    
    /**
     * Check if audit service is enabled
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Generate a UUID v4
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Start a new conversation audit trail
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel (web, whatsapp, etc.)
     * @param string $conversationId Conversation ID
     * @param string|null $userFingerprint User fingerprint (hashed IP, user ID, etc.)
     * @param array $meta Additional metadata
     * @return string Audit conversation ID
     */
    public function startConversation($agentId, $channel, $conversationId, $userFingerprint = null, array $meta = []) {
        if (!$this->enabled) {
            return '';
        }
        
        try {
            $id = $this->generateUUID();
            $now = gmdate('Y-m-d\TH:i:s\Z');
            
            $sql = "INSERT INTO audit_conversations 
                    (id, agent_id, channel, conversation_id, user_fingerprint, started_at, last_activity_at, meta_json, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [
                $id,
                $agentId,
                $channel,
                $conversationId,
                $userFingerprint,
                $now,
                $now,
                json_encode($meta),
                $now
            ]);
            
            return $id;
        } catch (Exception $e) {
            error_log('AuditService: Failed to start conversation: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Update conversation last activity timestamp
     */
    public function touchConversation($conversationId) {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $now = gmdate('Y-m-d\TH:i:s\Z');
            $sql = "UPDATE audit_conversations SET last_activity_at = ? WHERE conversation_id = ?";
            $this->db->execute($sql, [$now, $conversationId]);
        } catch (Exception $e) {
            error_log('AuditService: Failed to touch conversation: ' . $e->getMessage());
        }
    }
    
    /**
     * Append a message to the audit trail
     * 
     * @param string $conversationId Conversation ID
     * @param array $message Message data
     * @return string Message ID
     */
    public function appendMessage($conversationId, array $message) {
        if (!$this->enabled) {
            return '';
        }
        
        try {
            $id = $this->generateUUID();
            $now = gmdate('Y-m-d\TH:i:s\Z');
            
            // Get next sequence number for this conversation
            $sequence = $this->getNextSequence($conversationId);
            
            // Extract and process content
            $content = $message['content'] ?? '';
            $role = $message['role'] ?? 'user';
            
            // Redact PII
            $redactedContent = $this->redactor->redact($content);
            
            // Hash the redacted content
            $contentHash = hash('sha256', $redactedContent);
            
            // Encrypt content if enabled
            $contentEnc = '';
            if ($this->encryptAtRest && $this->crypto) {
                $encrypted = $this->crypto->encrypt($redactedContent);
                $contentEnc = $this->crypto->encodeForStorage($encrypted);
            } else {
                $contentEnc = $redactedContent;
            }
            
            // Prepare attachments
            $attachmentsJson = isset($message['attachments']) ? json_encode($message['attachments']) : null;
            
            // Prepare request metadata
            $requestMetaJson = isset($message['request_meta']) ? json_encode($message['request_meta']) : null;
            
            $sql = "INSERT INTO audit_messages 
                    (id, conversation_id, sequence, role, content_enc, content_hash, 
                     attachments_json, request_meta_json, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [
                $id,
                $conversationId,
                $sequence,
                $role,
                $contentEnc,
                $contentHash,
                $attachmentsJson,
                $requestMetaJson,
                $now
            ]);
            
            // Touch conversation
            $this->touchConversation($conversationId);
            
            return $id;
        } catch (Exception $e) {
            error_log('AuditService: Failed to append message: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get next sequence number for a conversation
     */
    private function getNextSequence($conversationId) {
        try {
            $sql = "SELECT MAX(sequence) as max_seq FROM audit_messages WHERE conversation_id = ?";
            $result = $this->db->query($sql, [$conversationId]);
            
            if (!empty($result) && isset($result[0]['max_seq'])) {
                return $result[0]['max_seq'] + 1;
            }
            
            return 1;
        } catch (Exception $e) {
            error_log('AuditService: Failed to get next sequence: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Record an event in the audit trail
     * 
     * @param string $conversationId Conversation ID
     * @param string $type Event type
     * @param array $payload Event payload
     * @param string|null $messageId Associated message ID
     */
    public function recordEvent($conversationId, $type, array $payload = [], $messageId = null) {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $id = $this->generateUUID();
            $now = gmdate('Y-m-d\TH:i:s\Z');
            
            $sql = "INSERT INTO audit_events 
                    (id, conversation_id, message_id, type, payload_json, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [
                $id,
                $conversationId,
                $messageId,
                $type,
                json_encode($payload),
                $now
            ]);
        } catch (Exception $e) {
            error_log('AuditService: Failed to record event: ' . $e->getMessage());
        }
    }
    
    /**
     * Attach artifacts to a message
     * 
     * @param string $messageId Message ID
     * @param array $artifacts Array of artifacts
     */
    public function attachArtifacts($messageId, array $artifacts) {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $now = gmdate('Y-m-d\TH:i:s\Z');
            
            foreach ($artifacts as $artifact) {
                $id = $this->generateUUID();
                $kind = $artifact['kind'] ?? 'unknown';
                $data = $artifact['data'] ?? [];
                
                $sql = "INSERT INTO audit_artifacts 
                        (id, message_id, kind, data_json, created_at) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $this->db->execute($sql, [
                    $id,
                    $messageId,
                    $kind,
                    json_encode($data),
                    $now
                ]);
            }
        } catch (Exception $e) {
            error_log('AuditService: Failed to attach artifacts: ' . $e->getMessage());
        }
    }
    
    /**
     * Finalize a message with response metadata and risk scores
     * 
     * @param string $messageId Message ID
     * @param array $responseMeta Response metadata
     * @param array $riskScores Risk scores
     */
    public function finalizeMessage($messageId, array $responseMeta, array $riskScores = []) {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $sql = "UPDATE audit_messages 
                    SET response_meta_json = ?, risk_scores_json = ? 
                    WHERE id = ?";
            
            $this->db->execute($sql, [
                json_encode($responseMeta),
                json_encode($riskScores),
                $messageId
            ]);
        } catch (Exception $e) {
            error_log('AuditService: Failed to finalize message: ' . $e->getMessage());
        }
    }
    
    /**
     * Get conversation by conversation_id
     */
    public function getConversation($conversationId) {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $sql = "SELECT * FROM audit_conversations WHERE conversation_id = ?";
            $result = $this->db->query($sql, [$conversationId]);
            
            return !empty($result) ? $result[0] : null;
        } catch (Exception $e) {
            error_log('AuditService: Failed to get conversation: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * List conversations with filters
     */
    public function listConversations(array $filters = [], $limit = 50, $offset = 0) {
        if (!$this->enabled) {
            return [];
        }
        
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['agent_id'])) {
                $where[] = 'agent_id = ?';
                $params[] = $filters['agent_id'];
            }
            
            if (!empty($filters['channel'])) {
                $where[] = 'channel = ?';
                $params[] = $filters['channel'];
            }
            
            if (!empty($filters['from'])) {
                $where[] = 'started_at >= ?';
                $params[] = $filters['from'];
            }
            
            if (!empty($filters['to'])) {
                $where[] = 'started_at <= ?';
                $params[] = $filters['to'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT * FROM audit_conversations 
                    $whereClause 
                    ORDER BY started_at DESC 
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            return $this->db->query($sql, $params);
        } catch (Exception $e) {
            error_log('AuditService: Failed to list conversations: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get messages for a conversation
     */
    public function getMessages($conversationId, $decryptContent = false) {
        if (!$this->enabled) {
            return [];
        }
        
        try {
            $sql = "SELECT * FROM audit_messages WHERE conversation_id = ? ORDER BY sequence ASC";
            $messages = $this->db->query($sql, [$conversationId]);
            
            // Decrypt content if requested and encryption is enabled
            if ($decryptContent && $this->encryptAtRest && $this->crypto) {
                foreach ($messages as &$message) {
                    if (!empty($message['content_enc'])) {
                        try {
                            $encrypted = $this->crypto->decodeFromStorage($message['content_enc']);
                            $message['content'] = $this->crypto->decrypt(
                                $encrypted['ciphertext'],
                                $encrypted['nonce'],
                                $encrypted['tag']
                            );
                        } catch (Exception $e) {
                            error_log('AuditService: Failed to decrypt message: ' . $e->getMessage());
                            $message['content'] = '[DECRYPTION_FAILED]';
                        }
                    } else {
                        $message['content'] = $message['content_enc'];
                    }
                }
            }
            
            return $messages;
        } catch (Exception $e) {
            error_log('AuditService: Failed to get messages: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get events for a conversation
     */
    public function getEvents($conversationId, $type = null) {
        if (!$this->enabled) {
            return [];
        }
        
        try {
            $sql = "SELECT * FROM audit_events WHERE conversation_id = ?";
            $params = [$conversationId];
            
            if ($type) {
                $sql .= " AND type = ?";
                $params[] = $type;
            }
            
            $sql .= " ORDER BY created_at ASC";
            
            return $this->db->query($sql, $params);
        } catch (Exception $e) {
            error_log('AuditService: Failed to get events: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete conversations older than retention period
     * 
     * @param int $retentionDays Number of days to retain
     * @return int Number of conversations deleted
     */
    public function deleteExpired($retentionDays) {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $secondsPerDay = 86400;
            $cutoffDate = gmdate('Y-m-d\TH:i:s\Z', time() - ($retentionDays * $secondsPerDay));
            
            $sql = "DELETE FROM audit_conversations WHERE started_at < ? AND (meta_json IS NULL OR json_extract(meta_json, '$.hold_until') IS NULL OR json_extract(meta_json, '$.hold_until') < ?)";
            
            return $this->db->execute($sql, [$cutoffDate, gmdate('Y-m-d\TH:i:s\Z')]);
        } catch (Exception $e) {
            error_log('AuditService: Failed to delete expired: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Decrypt a single message content
     * 
     * @param string $contentEnc Encrypted content from database
     * @return string Decrypted content or error message
     */
    public function decryptContent($contentEnc) {
        if (!$this->encryptAtRest || !$this->crypto || empty($contentEnc)) {
            return $contentEnc;
        }
        
        try {
            $encrypted = $this->crypto->decodeFromStorage($contentEnc);
            return $this->crypto->decrypt(
                $encrypted['ciphertext'],
                $encrypted['nonce'],
                $encrypted['tag']
            );
        } catch (Exception $e) {
            error_log('AuditService: Failed to decrypt content: ' . $e->getMessage());
            return '[DECRYPTION_FAILED]';
        }
    }
    
    /**
     * Log a generic event (not tied to a specific conversation)
     * Used for system events like access denied, authentication failures, etc.
     * 
     * @param array $eventData Event data (event_type, user_id, etc.)
     * @return string Event ID or empty string on failure
     */
    public function logEvent(array $eventData) {
        if (!$this->enabled) {
            return '';
        }
        
        try {
            $id = $this->generateUUID();
            $now = gmdate('Y-m-d\TH:i:s\Z');
            
            // Store in audit_events with null conversation_id for system events
            $sql = "INSERT INTO audit_events 
                    (id, conversation_id, message_id, type, payload_json, created_at) 
                    VALUES (?, NULL, NULL, ?, ?, ?)";
            
            $eventType = $eventData['event_type'] ?? 'system_event';
            unset($eventData['event_type']); // Remove from payload
            
            $this->db->execute($sql, [
                $id,
                $eventType,
                json_encode($eventData),
                $now
            ]);
            
            return $id;
        } catch (Exception $e) {
            error_log('AuditService: Failed to log event: ' . $e->getMessage());
            return '';
        }
    }
}
