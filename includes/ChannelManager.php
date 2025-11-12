<?php
/**
 * Channel Manager - Orchestrates channel operations and routing
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/ChannelSessionService.php';
require_once __DIR__ . '/ChannelMessageService.php';
require_once __DIR__ . '/channels/ChannelInterface.php';
require_once __DIR__ . '/channels/WhatsAppZApi.php';

class ChannelManager {
    private $db;
    private $sessionService;
    private $messageService;
    private $channels = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->sessionService = new ChannelSessionService($db);
        $this->messageService = new ChannelMessageService($db);
    }
    
    /**
     * Get channel configuration for an agent
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name (e.g., 'whatsapp')
     * @return array|null Channel configuration or null if not found/disabled
     */
    public function getChannelConfig(string $agentId, string $channel): ?array {
        $sql = "SELECT * FROM agent_channels WHERE agent_id = ? AND channel = ? AND enabled = 1";
        $config = $this->db->getOne($sql, [$agentId, $channel]);
        
        if (!$config) {
            return null;
        }
        
        $configJson = json_decode($config['config_json'] ?? '{}', true);
        
        return array_merge($configJson, [
            'id' => $config['id'],
            'agent_id' => $config['agent_id'],
            'channel' => $config['channel'],
            'enabled' => (bool)$config['enabled']
        ]);
    }
    
    /**
     * Find agent by WhatsApp business number
     * 
     * @param string $businessNumber WhatsApp business number in E.164 format
     * @return string|null Agent ID or null if not found
     */
    public function findAgentByWhatsAppNumber(string $businessNumber): ?string {
        $sql = "SELECT agent_id, config_json FROM agent_channels 
                WHERE channel = 'whatsapp' AND enabled = 1";
        
        $channels = $this->db->query($sql);
        
        foreach ($channels as $channel) {
            $config = json_decode($channel['config_json'] ?? '{}', true);
            $configNumber = $config['whatsapp_business_number'] ?? null;
            
            if ($configNumber && $this->normalizePhone($configNumber) === $this->normalizePhone($businessNumber)) {
                return $channel['agent_id'];
            }
        }
        
        return null;
    }
    
    /**
     * Get channel adapter instance
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name
     * @return ChannelInterface Channel adapter
     * @throws Exception if channel not found or not supported
     */
    public function getChannelAdapter(string $agentId, string $channel): ChannelInterface {
        $cacheKey = "{$agentId}:{$channel}";

        if (isset($this->channels[$cacheKey])) {
            return $this->channels[$cacheKey];
        }

        $config = $this->getChannelConfig($agentId, $channel);

        if (!$config) {
            throw new Exception("Channel '$channel' not configured or disabled for agent '$agentId'", 404);
        }

        $adapter = $this->createAdapter($channel, $config);
        $this->channels[$cacheKey] = $adapter;
        return $adapter;
    }

    private function createAdapter(string $channel, array $config): ChannelInterface {
        switch ($channel) {
            case 'whatsapp':
                return new WhatsAppZApi($config);
            default:
                throw new Exception("Unsupported channel: $channel", 400);
        }
    }
    
    /**
     * Process inbound message from channel webhook
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name
     * @param array $payload Webhook payload
     * @param callable $messageHandler Callback to process message: function($message, $conversationId, $session)
     * @return array Processing result
     */
    public function processInbound(string $agentId, string $channel, array $payload, callable $messageHandler): array {
        try {
            // Get channel adapter
            $adapter = $this->getChannelAdapter($agentId, $channel);
            
            // Normalize inbound message
            $normalized = $adapter->normalizeInbound($payload);
            
            if (!$normalized) {
                return ['success' => true, 'skipped' => true, 'reason' => 'Not a message event'];
            }
            
            $externalUserId = $normalized['from'];
            $externalMessageId = $normalized['message_id'];
            $text = $normalized['text'] ?? '';
            
            // Check for duplicates (idempotency)
            if ($externalMessageId && $this->messageService->messageExists($externalMessageId)) {
                return ['success' => true, 'skipped' => true, 'reason' => 'Duplicate message'];
            }
            
            // Get or create session
            $session = $this->sessionService->getOrCreateSession($agentId, $channel, $externalUserId);
            $conversationId = $session['conversation_id'];
            
            // Record inbound message
            $messageId = $this->messageService->recordInbound(
                $agentId,
                $channel,
                $externalUserId,
                $conversationId,
                $externalMessageId,
                $normalized
            );
            
            // Process message through handler
            try {
                $response = $messageHandler($normalized, $conversationId, $session);
                
                // Update message status
                $this->messageService->updateStatus($messageId, 'processed');
                
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'conversation_id' => $conversationId,
                    'response' => $response
                ];
                
            } catch (Exception $e) {
                // Update message status with error
                $this->messageService->updateStatus($messageId, 'failed', $e->getMessage());
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Channel inbound processing failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send text message to user
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name
     * @param string $externalUserId External user identifier
     * @param string $text Message text
     * @param string $conversationId Conversation ID for tracking
     * @return array Send result
     */
    public function sendText(
        string $agentId,
        string $channel,
        string $externalUserId,
        string $text,
        string $conversationId,
        array $options = []
    ): array {
        try {
            if (!empty($options['configOverride']) && is_array($options['configOverride'])) {
                $adapter = $this->createAdapter($channel, $options['configOverride']);
            } else {
                $adapter = $this->getChannelAdapter($agentId, $channel);
            }

            // Send message
            $adapterOptions = $options['adapterOptions'] ?? [];
            $result = $adapter->sendText($externalUserId, $text, $adapterOptions);

            $messageId = null;
            if (empty($options['skipPersistence'])) {
                $messageId = $this->messageService->recordOutbound(
                    $agentId,
                    $channel,
                    $externalUserId,
                    $conversationId,
                    ['text' => $text],
                    $result['message_id'] ?? null
                );
            }

            return [
                'success' => true,
                'message_id' => $messageId,
                'external_message_id' => $result['message_id'] ?? null,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            error_log("Channel sendText failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send media message to user
     */
    public function sendMedia(
        string $agentId,
        string $channel,
        string $externalUserId,
        string $mediaUrl,
        string $mimeType,
        ?string $caption,
        string $conversationId
    ): array {
        try {
            $adapter = $this->getChannelAdapter($agentId, $channel);
            
            // Send media
            $result = $adapter->sendMedia($externalUserId, $mediaUrl, $mimeType, $caption);
            
            // Record outbound message
            $messageId = $this->messageService->recordOutbound(
                $agentId,
                $channel,
                $externalUserId,
                $conversationId,
                ['media_url' => $mediaUrl, 'mime_type' => $mimeType, 'caption' => $caption],
                $result['message_id'] ?? null
            );
            
            return [
                'success' => true,
                'message_id' => $messageId,
                'external_message_id' => $result['message_id'] ?? null,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            error_log("Channel sendMedia failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get session service
     */
    public function getSessionService(): ChannelSessionService {
        return $this->sessionService;
    }
    
    /**
     * Get message service
     */
    public function getMessageService(): ChannelMessageService {
        return $this->messageService;
    }
    
    /**
     * Normalize phone number
     */
    private function normalizePhone(string $phone): string {
        $normalized = preg_replace('/[^\d+]/', '', $phone);
        if (!str_starts_with($normalized, '+')) {
            $normalized = '+' . $normalized;
        }
        return $normalized;
    }
}
