<?php
/**
 * Channel Interface - Abstraction for communication channels (WhatsApp, Telegram, etc.)
 */

interface ChannelInterface {
    /**
     * Send a text message to a user
     * 
     * @param string $to Destination user identifier (e.g., phone number)
     * @param string $text Message text
     * @param array $options Additional options (e.g., quoted message, etc.)
     * @return array Response with status and message_id
     * @throws Exception on failure
     */
    public function sendText(string $to, string $text, array $options = []): array;
    
    /**
     * Send a media message to a user
     * 
     * @param string $to Destination user identifier
     * @param string $mediaUrl URL to the media file
     * @param string $mimeType MIME type of the media
     * @param string|null $caption Optional caption for the media
     * @param array $options Additional options
     * @return array Response with status and message_id
     * @throws Exception on failure
     */
    public function sendMedia(string $to, string $mediaUrl, string $mimeType, ?string $caption = null, array $options = []): array;
    
    /**
     * Verify webhook signature/authenticity (if supported by provider)
     * 
     * @param array $headers Request headers
     * @param string $body Request body
     * @param string|null $secret Webhook secret
     * @return bool True if signature is valid
     */
    public function verifySignature(array $headers, string $body, ?string $secret = null): bool;
    
    /**
     * Normalize incoming webhook payload to standard format
     * 
     * @param array $payload Raw webhook payload
     * @return array|null Normalized message data or null if not a message event
     */
    public function normalizeInbound(array $payload): ?array;
    
    /**
     * Get channel name identifier
     * 
     * @return string Channel name (e.g., 'whatsapp', 'telegram')
     */
    public function getChannelName(): string;
}
