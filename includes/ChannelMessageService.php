<?php
/**
 * Channel Message Service - Manages channel message tracking, audit, and idempotency
 */

require_once __DIR__ . '/DB.php';

class ChannelMessageService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Check if a message has already been processed (idempotency check)
     * 
     * @param string $externalMessageId External message ID from provider
     * @return bool True if message already exists
     */
    public function messageExists(string $externalMessageId): bool {
        $sql = "SELECT id FROM channel_messages WHERE external_message_id = ?";
        $result = $this->db->getOne($sql, [$externalMessageId]);
        return $result !== null;
    }
    
    /**
     * Record an inbound message
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name
     * @param string $externalUserId External user identifier
     * @param string $conversationId Conversation ID
     * @param string|null $externalMessageId External message ID
     * @param array $payload Message payload
     * @return string Message ID
     */
    public function recordInbound(
        string $agentId,
        string $channel,
        string $externalUserId,
        string $conversationId,
        ?string $externalMessageId,
        array $payload
    ): string {
        $id = $this->generateUUID();
        $now = date('c');
        
        $sql = "INSERT INTO channel_messages 
                (id, agent_id, channel, direction, external_message_id, external_user_id, 
                 conversation_id, payload_json, status, created_at, updated_at)
                VALUES (?, ?, ?, 'inbound', ?, ?, ?, ?, 'received', ?, ?)";
        
        try {
            $this->db->insert($sql, [
                $id,
                $agentId,
                $channel,
                $externalMessageId,
                $externalUserId,
                $conversationId,
                json_encode($payload),
                $now,
                $now
            ]);
            
            return $id;
        } catch (Exception $e) {
            // Check if it's a duplicate
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || 
                strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                error_log("Duplicate message detected: $externalMessageId");
                throw new Exception('Message already processed', 409);
            }
            throw $e;
        }
    }
    
    /**
     * Record an outbound message
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name
     * @param string $externalUserId External user identifier
     * @param string $conversationId Conversation ID
     * @param array $payload Message payload
     * @param string|null $externalMessageId External message ID (if known)
     * @return string Message ID
     */
    public function recordOutbound(
        string $agentId,
        string $channel,
        string $externalUserId,
        string $conversationId,
        array $payload,
        ?string $externalMessageId = null
    ): string {
        $id = $this->generateUUID();
        $now = date('c');
        
        $sql = "INSERT INTO channel_messages 
                (id, agent_id, channel, direction, external_message_id, external_user_id, 
                 conversation_id, payload_json, status, created_at, updated_at)
                VALUES (?, ?, ?, 'outbound', ?, ?, ?, ?, 'sent', ?, ?)";
        
        $this->db->insert($sql, [
            $id,
            $agentId,
            $channel,
            $externalMessageId,
            $externalUserId,
            $conversationId,
            json_encode($payload),
            $now,
            $now
        ]);
        
        return $id;
    }
    
    /**
     * Update message status
     */
    public function updateStatus(string $messageId, string $status, ?string $errorText = null): void {
        $now = date('c');
        
        if ($errorText) {
            $sql = "UPDATE channel_messages SET status = ?, error_text = ?, updated_at = ? WHERE id = ?";
            $this->db->execute($sql, [$status, $errorText, $now, $messageId]);
        } else {
            $sql = "UPDATE channel_messages SET status = ?, updated_at = ? WHERE id = ?";
            $this->db->execute($sql, [$status, $now, $messageId]);
        }
    }
    
    /**
     * Get messages for a conversation
     */
    public function getMessages(string $conversationId, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT * FROM channel_messages 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        return $this->db->query($sql, [$conversationId, $limit, $offset]);
    }
    
    /**
     * Get message statistics for an agent
     */
    public function getStats(string $agentId, string $channel = null): array {
        $params = [$agentId];
        $whereClauses = ["agent_id = ?"];
        
        if ($channel) {
            $whereClauses[] = "channel = ?";
            $params[] = $channel;
        }
        
        $whereClause = implode(' AND ', $whereClauses);
        
        // Total messages
        $totalSql = "SELECT COUNT(*) as count FROM channel_messages WHERE $whereClause";
        $total = $this->db->getOne($totalSql, $params);
        
        // By direction
        $directionSql = "SELECT direction, COUNT(*) as count 
                        FROM channel_messages 
                        WHERE $whereClause 
                        GROUP BY direction";
        $byDirection = $this->db->query($directionSql, $params);
        
        // By status
        $statusSql = "SELECT status, COUNT(*) as count 
                     FROM channel_messages 
                     WHERE $whereClause 
                     GROUP BY status";
        $byStatus = $this->db->query($statusSql, $params);
        
        return [
            'total' => (int)($total['count'] ?? 0),
            'by_direction' => $byDirection,
            'by_status' => $byStatus
        ];
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUUID(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
