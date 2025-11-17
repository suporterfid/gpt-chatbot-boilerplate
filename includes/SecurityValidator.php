<?php
/**
 * SecurityValidator
 * 
 * Centralized input validation and sanitization for security purposes.
 * Prevents SQL injection, XSS, and other injection attacks.
 * 
 * @package GPT_Chatbot
 */

class SecurityValidator {
    /**
     * Validate tenant ID format
     * 
     * Ensures tenant_id contains only safe characters to prevent SQL injection
     * and other attacks. Valid format: alphanumeric, underscores, and hyphens.
     * 
     * @param string|null $tenantId Tenant ID to validate
     * @return string|null Validated tenant ID or null if invalid/empty
     * @throws Exception If tenant_id format is invalid
     */
    public static function validateTenantId(?string $tenantId): ?string {
        if ($tenantId === null || $tenantId === '') {
            return null;
        }
        
        $tenantId = trim($tenantId);
        
        // Empty after trim
        if ($tenantId === '') {
            return null;
        }
        
        // Check format: alphanumeric, underscore, hyphen only
        // Length: 1-64 characters
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tenantId)) {
            throw new Exception('Invalid tenant ID format', 400);
        }
        
        return $tenantId;
    }
    
    /**
     * Validate API key format
     * 
     * Ensures API key matches expected format to prevent injection attacks.
     * Returns null (instead of throwing) for invalid keys to prevent enumeration.
     * 
     * @param string|null $apiKey API key to validate
     * @return string|null Validated API key or null if invalid
     */
    public static function validateApiKey(?string $apiKey): ?string {
        if ($apiKey === null || $apiKey === '') {
            return null;
        }
        
        $apiKey = trim($apiKey);
        
        // Empty after trim
        if ($apiKey === '') {
            return null;
        }
        
        // API keys should be alphanumeric with underscores/hyphens
        // Length: 20-128 characters (reasonable range)
        // Return null instead of throwing to prevent key enumeration
        if (!preg_match('/^[a-zA-Z0-9_-]{20,128}$/', $apiKey)) {
            return null;
        }
        
        return $apiKey;
    }
    
    /**
     * Validate conversation ID format
     * 
     * @param string|null $conversationId Conversation ID to validate
     * @return string|null Validated conversation ID or null if invalid
     * @throws Exception If conversation_id format is invalid
     */
    public static function validateConversationId(?string $conversationId): ?string {
        if ($conversationId === null || $conversationId === '') {
            return null;
        }
        
        $conversationId = trim($conversationId);
        
        if ($conversationId === '') {
            return null;
        }
        
        // Allow alphanumeric, underscore, hyphen
        // Length: 1-128 characters
        if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $conversationId)) {
            throw new Exception('Invalid conversation ID format', 400);
        }
        
        return $conversationId;
    }
    
    /**
     * Validate agent slug format
     * 
     * @param string|null $agentSlug Agent slug to validate
     * @return string|null Validated agent slug or null if invalid
     * @throws Exception If agent slug format is invalid
     */
    public static function validateAgentSlug(?string $agentSlug): ?string {
        if ($agentSlug === null || $agentSlug === '') {
            return null;
        }
        
        $agentSlug = trim($agentSlug);
        
        if ($agentSlug === '') {
            return null;
        }
        
        // Agent slugs: lowercase alphanumeric, hyphen
        // Length: 1-64 characters
        if (!preg_match('/^[a-z0-9-]{1,64}$/', $agentSlug)) {
            throw new Exception('Invalid agent slug format', 400);
        }
        
        return $agentSlug;
    }
    
    /**
     * Validate integer ID
     * 
     * @param mixed $id ID to validate
     * @return int|null Validated integer ID or null if invalid
     * @throws Exception If ID is invalid
     */
    public static function validateId($id): ?int {
        if ($id === null || $id === '') {
            return null;
        }
        
        // Filter as integer
        $validated = filter_var($id, FILTER_VALIDATE_INT);
        
        if ($validated === false || $validated < 1) {
            throw new Exception('Invalid ID format', 400);
        }
        
        return $validated;
    }
    
    /**
     * Sanitize message content
     * 
     * Removes potentially dangerous content from user messages while preserving
     * legitimate text. This is a defense-in-depth measure; the frontend should
     * also sanitize output.
     * 
     * @param string $message Message to sanitize
     * @return string Sanitized message
     */
    public static function sanitizeMessage(string $message): string {
        // Remove script tags
        $message = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $message);
        
        // Remove event handlers (onclick, onerror, etc.)
        $message = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $message);
        
        // Remove javascript: protocol
        $message = preg_replace('/javascript:/i', '', $message);
        
        // Remove data: protocol with HTML content
        $message = preg_replace('/data:text\/html[^,]*,/i', '', $message);
        
        return $message;
    }
    
    /**
     * Validate email format
     * 
     * @param string|null $email Email to validate
     * @return string|null Validated email or null if invalid
     * @throws Exception If email format is invalid
     */
    public static function validateEmail(?string $email): ?string {
        if ($email === null || $email === '') {
            return null;
        }
        
        $email = trim($email);
        
        if ($email === '') {
            return null;
        }
        
        $validated = filter_var($email, FILTER_VALIDATE_EMAIL);
        
        if ($validated === false) {
            throw new Exception('Invalid email format', 400);
        }
        
        return $validated;
    }
    
    /**
     * Validate filename for security
     * 
     * Prevents path traversal and other filename-based attacks
     * 
     * @param string|null $filename Filename to validate
     * @return string|null Validated filename or null if invalid
     * @throws Exception If filename is invalid
     */
    public static function validateFilename(?string $filename): ?string {
        if ($filename === null || $filename === '') {
            return null;
        }
        
        $originalFilename = $filename;
        
        // Check for path traversal attempts in original filename
        if (strpos($originalFilename, '/') !== false || strpos($originalFilename, '\\') !== false) {
            throw new Exception('Invalid filename: path separators not allowed', 400);
        }
        
        if (strpos($originalFilename, '..') !== false) {
            throw new Exception('Invalid filename: path traversal detected', 400);
        }
        
        // Remove any path components (defense in depth)
        $filename = basename($filename);
        $filename = trim($filename);
        
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new Exception('Invalid filename', 400);
        }
        
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            throw new Exception('Invalid filename: null byte detected', 400);
        }
        
        // Validate characters (alphanumeric, dot, hyphen, underscore)
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            throw new Exception('Invalid filename: contains invalid characters', 400);
        }
        
        // Validate length
        if (strlen($filename) > 255) {
            throw new Exception('Filename too long', 400);
        }
        
        // Check for multiple extensions (potential double extension attack)
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            throw new Exception('Invalid filename: multiple extensions not allowed', 400);
        }
        
        return $filename;
    }
}
