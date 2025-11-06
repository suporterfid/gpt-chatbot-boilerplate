<?php
/**
 * WhatsApp Z-API Channel Adapter
 */

require_once __DIR__ . '/ChannelInterface.php';
require_once __DIR__ . '/../ZApiClient.php';

class WhatsAppZApi implements ChannelInterface {
    private $client;
    private $config;
    
    public function __construct(array $config) {
        $this->config = $config;
        
        $baseUrl = $config['zapi_base_url'] ?? 'https://api.z-api.io';
        $instanceId = $config['zapi_instance_id'] ?? '';
        $token = $config['zapi_token'] ?? '';
        $timeoutMs = $config['zapi_timeout_ms'] ?? 30000;
        $retries = $config['zapi_retries'] ?? 3;
        
        if (empty($instanceId) || empty($token)) {
            throw new Exception('Z-API instance_id and token are required');
        }
        
        $this->client = new ZApiClient($baseUrl, $instanceId, $token, $timeoutMs, $retries);
    }
    
    /**
     * Send text message via Z-API
     */
    public function sendText(string $to, string $text, array $options = []): array {
        try {
            // Check message length and split if necessary
            $chunkSize = $this->config['reply_chunk_size'] ?? 4000;
            
            if (strlen($text) <= $chunkSize) {
                $response = $this->client->sendText($to, $text);
                return [
                    'success' => true,
                    'message_id' => $response['messageId'] ?? null,
                    'response' => $response
                ];
            }
            
            // Split long messages
            $chunks = $this->splitMessage($text, $chunkSize);
            $messageIds = [];
            
            foreach ($chunks as $index => $chunk) {
                // Add continuation indicator
                if ($index > 0) {
                    usleep(500000); // 500ms delay between chunks
                }
                
                $response = $this->client->sendText($to, $chunk);
                $messageIds[] = $response['messageId'] ?? null;
            }
            
            return [
                'success' => true,
                'message_id' => $messageIds[0] ?? null,
                'message_ids' => $messageIds,
                'chunks_sent' => count($chunks)
            ];
            
        } catch (Exception $e) {
            error_log("WhatsApp sendText failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send media message via Z-API
     */
    public function sendMedia(string $to, string $mediaUrl, string $mimeType, ?string $caption = null, array $options = []): array {
        try {
            // Determine media type from MIME type
            if (strpos($mimeType, 'image/') === 0) {
                $response = $this->client->sendImage($to, $mediaUrl, $caption);
            } elseif (strpos($mimeType, 'application/') === 0 || strpos($mimeType, 'text/') === 0) {
                $response = $this->client->sendDocument($to, $mediaUrl, $caption);
            } else {
                throw new Exception("Unsupported media type: $mimeType");
            }
            
            return [
                'success' => true,
                'message_id' => $response['messageId'] ?? null,
                'response' => $response
            ];
            
        } catch (Exception $e) {
            error_log("WhatsApp sendMedia failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verify webhook signature (Z-API basic implementation)
     */
    public function verifySignature(array $headers, string $body, ?string $secret = null): bool {
        // Z-API typically doesn't use webhook signatures by default
        // If webhook_secret is configured, we can implement basic verification
        if (empty($secret)) {
            return true; // No secret configured, skip verification
        }
        
        // Check for X-Webhook-Secret header if provided
        $headerSecret = $headers['x-webhook-secret'] ?? $headers['X-Webhook-Secret'] ?? null;
        
        if ($headerSecret === $secret) {
            return true;
        }
        
        error_log("Webhook signature verification failed");
        return false;
    }
    
    /**
     * Normalize Z-API webhook payload to standard format
     */
    public function normalizeInbound(array $payload): ?array {
        // Z-API webhook structure varies by event type
        // Common structure: { "event": "message.received", "data": {...} }
        
        // Skip non-message events
        $event = $payload['event'] ?? '';
        if ($event !== 'message.received' && !isset($payload['message'])) {
            return null;
        }
        
        // Extract message data
        $data = $payload['data'] ?? $payload;
        $message = $data['message'] ?? $data;
        
        // Get sender info
        $from = $message['from'] ?? $message['phone'] ?? null;
        if (!$from) {
            error_log("WhatsApp inbound: missing sender info");
            return null;
        }
        
        // Get message ID
        $messageId = $message['messageId'] ?? $message['id'] ?? uniqid('msg_', true);
        
        // Get timestamp
        $timestamp = $message['timestamp'] ?? $message['messageTimestamp'] ?? time();
        
        // Extract text content
        $text = null;
        $mediaUrl = null;
        $mimeType = null;
        
        if (isset($message['text'])) {
            $text = $message['text'];
        } elseif (isset($message['body'])) {
            $text = $message['body'];
        } elseif (isset($message['conversation'])) {
            $text = $message['conversation'];
        }
        
        // Extract media if present
        if (isset($message['image'])) {
            $mediaUrl = $message['image']['url'] ?? $message['image'];
            $mimeType = $message['image']['mimetype'] ?? 'image/jpeg';
            $text = $message['image']['caption'] ?? '';
        } elseif (isset($message['document'])) {
            $mediaUrl = $message['document']['url'] ?? $message['document'];
            $mimeType = $message['document']['mimetype'] ?? 'application/octet-stream';
            $text = $message['document']['caption'] ?? '';
        }
        
        return [
            'message_id' => $messageId,
            'from' => $this->normalizePhone($from),
            'timestamp' => $timestamp,
            'text' => $text,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'raw_payload' => $payload
        ];
    }
    
    /**
     * Get channel name
     */
    public function getChannelName(): string {
        return 'whatsapp';
    }
    
    /**
     * Split message into chunks
     */
    private function splitMessage(string $text, int $maxLength): array {
        $chunks = [];
        $remaining = $text;
        
        while (strlen($remaining) > 0) {
            if (strlen($remaining) <= $maxLength) {
                $chunks[] = $remaining;
                break;
            }
            
            // Try to find a natural break point (newline, space) near maxLength
            $chunk = substr($remaining, 0, $maxLength);
            $breakPos = max(
                strrpos($chunk, "\n"),
                strrpos($chunk, ". "),
                strrpos($chunk, "! "),
                strrpos($chunk, "? "),
                strrpos($chunk, " ")
            );
            
            if ($breakPos !== false && $breakPos > $maxLength * 0.7) {
                $chunk = substr($remaining, 0, $breakPos + 1);
            }
            
            $chunks[] = trim($chunk);
            $remaining = substr($remaining, strlen($chunk));
        }
        
        return $chunks;
    }
    
    /**
     * Normalize phone number
     */
    private function normalizePhone(string $phone): string {
        // Remove all non-digit characters except +
        $normalized = preg_replace('/[^\d+]/', '', $phone);
        
        // Ensure it starts with +
        if (!str_starts_with($normalized, '+')) {
            $normalized = '+' . $normalized;
        }
        
        return $normalized;
    }
}
