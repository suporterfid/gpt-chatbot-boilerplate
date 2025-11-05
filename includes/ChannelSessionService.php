<?php
/**
 * Channel Session Service - Manages channel sessions and conversation mappings
 */

require_once __DIR__ . '/DB.php';

class ChannelSessionService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get or create a channel session
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name (e.g., 'whatsapp')
     * @param string $externalUserId External user identifier (e.g., phone number)
     * @return array Session data with conversation_id
     */
    public function getOrCreateSession(string $agentId, string $channel, string $externalUserId): array {
        // Try to find existing session
        $sql = "SELECT * FROM channel_sessions 
                WHERE agent_id = ? AND channel = ? AND external_user_id = ?";
        $session = $this->db->getOne($sql, [$agentId, $channel, $externalUserId]);
        
        if ($session) {
            // Update last_seen_at
            $this->updateLastSeen($session['id']);
            
            return [
                'id' => $session['id'],
                'conversation_id' => $session['conversation_id'],
                'metadata' => json_decode($session['metadata_json'] ?? '{}', true),
                'is_new' => false
            ];
        }
        
        // Create new session
        $id = $this->generateUUID();
        $conversationId = $this->generateConversationId($agentId, $channel, $externalUserId);
        $now = date('c');
        
        $insertSql = "INSERT INTO channel_sessions 
                      (id, agent_id, channel, external_user_id, conversation_id, last_seen_at, metadata_json, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->insert($insertSql, [
            $id,
            $agentId,
            $channel,
            $externalUserId,
            $conversationId,
            $now,
            '{}',
            $now,
            $now
        ]);
        
        return [
            'id' => $id,
            'conversation_id' => $conversationId,
            'metadata' => [],
            'is_new' => true
        ];
    }
    
    /**
     * Update last seen timestamp
     */
    public function updateLastSeen(string $sessionId): void {
        $now = date('c');
        $sql = "UPDATE channel_sessions SET last_seen_at = ?, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [$now, $now, $sessionId]);
    }
    
    /**
     * Update session metadata
     */
    public function updateMetadata(string $sessionId, array $metadata): void {
        $now = date('c');
        $sql = "UPDATE channel_sessions SET metadata_json = ?, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [json_encode($metadata), $now, $sessionId]);
    }
    
    /**
     * Get session by conversation ID
     */
    public function getSessionByConversationId(string $conversationId): ?array {
        $sql = "SELECT * FROM channel_sessions WHERE conversation_id = ?";
        $session = $this->db->getOne($sql, [$conversationId]);
        
        if (!$session) {
            return null;
        }
        
        return [
            'id' => $session['id'],
            'agent_id' => $session['agent_id'],
            'channel' => $session['channel'],
            'external_user_id' => $session['external_user_id'],
            'conversation_id' => $session['conversation_id'],
            'metadata' => json_decode($session['metadata_json'] ?? '{}', true),
            'last_seen_at' => $session['last_seen_at']
        ];
    }
    
    /**
     * List sessions for an agent
     */
    public function listSessions(string $agentId, string $channel = null, int $limit = 50, int $offset = 0): array {
        $params = [$agentId];
        $sql = "SELECT * FROM channel_sessions WHERE agent_id = ?";
        
        if ($channel) {
            $sql .= " AND channel = ?";
            $params[] = $channel;
        }
        
        $sql .= " ORDER BY last_seen_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $sessions = $this->db->query($sql, $params);
        
        return array_map(function($session) {
            return [
                'id' => $session['id'],
                'agent_id' => $session['agent_id'],
                'channel' => $session['channel'],
                'external_user_id' => $session['external_user_id'],
                'conversation_id' => $session['conversation_id'],
                'metadata' => json_decode($session['metadata_json'] ?? '{}', true),
                'last_seen_at' => $session['last_seen_at'],
                'created_at' => $session['created_at']
            ];
        }, $sessions);
    }
    
    /**
     * Generate conversation ID from agent, channel, and user
     */
    private function generateConversationId(string $agentId, string $channel, string $externalUserId): string {
        // Create a deterministic conversation ID
        $hash = substr(hash('sha256', $agentId . ':' . $channel . ':' . $externalUserId), 0, 16);
        return "conv_{$channel}_{$hash}";
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
