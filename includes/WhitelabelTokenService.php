<?php
/**
 * Whitelabel Token Service
 * Handles HMAC-based token generation and validation for whitelabel agent publishing
 */

class WhitelabelTokenService {
    private $db;
    private $config;
    
    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Generate a secure whitelabel token for an agent
     * 
     * @param string $agentPublicId The public ID of the agent
     * @param string $hmacSecret The HMAC secret for this agent
     * @param int $ttl Token TTL in seconds (default: 600)
     * @return string The signed token
     */
    public function generateToken($agentPublicId, $hmacSecret, $ttl = 600) {
        $nonce = $this->generateNonce();
        $timestamp = time();
        
        $payload = [
            'aid' => $agentPublicId,
            'ts' => $timestamp,
            'nonce' => $nonce,
            'exp' => $timestamp + $ttl
        ];
        
        $payloadJson = json_encode($payload);
        $payloadBase64 = $this->base64UrlEncode($payloadJson);
        
        $signature = hash_hmac('sha256', $payloadBase64, $hmacSecret, true);
        $signatureBase64 = $this->base64UrlEncode($signature);
        
        return $payloadBase64 . '.' . $signatureBase64;
    }
    
    /**
     * Validate a whitelabel token
     * 
     * @param string $token The token to validate
     * @param string $expectedAgentPublicId Expected agent public ID
     * @param string $hmacSecret The HMAC secret for verification
     * @return array|false Returns payload array on success, false on failure
     */
    public function validateToken($token, $expectedAgentPublicId, $hmacSecret) {
        try {
            // Split token into parts
            $parts = explode('.', $token);
            if (count($parts) !== 2) {
                error_log("Invalid token format: expected 2 parts, got " . count($parts));
                return false;
            }
            
            list($payloadBase64, $signatureBase64) = $parts;
            
            // Verify signature with timing-safe comparison
            $expectedSignature = hash_hmac('sha256', $payloadBase64, $hmacSecret, true);
            $expectedSignatureBase64 = $this->base64UrlEncode($expectedSignature);
            
            if (!hash_equals($expectedSignatureBase64, $signatureBase64)) {
                error_log("Token signature verification failed");
                return false;
            }
            
            // Decode payload
            $payloadJson = $this->base64UrlDecode($payloadBase64);
            $payload = json_decode($payloadJson, true);
            
            if (!$payload || !is_array($payload)) {
                error_log("Invalid token payload");
                return false;
            }
            
            // Validate required fields
            if (!isset($payload['aid']) || !isset($payload['ts']) || !isset($payload['nonce'])) {
                error_log("Missing required token fields");
                return false;
            }
            
            // Validate agent ID matches
            if ($payload['aid'] !== $expectedAgentPublicId) {
                error_log("Token agent ID mismatch: expected {$expectedAgentPublicId}, got {$payload['aid']}");
                return false;
            }
            
            // Validate timestamp (within TTL)
            $now = time();
            $tokenAge = $now - $payload['ts'];
            $maxAge = $payload['exp'] ?? ($payload['ts'] + 600);
            
            if ($now > $maxAge) {
                error_log("Token expired: issued at {$payload['ts']}, now {$now}, max age {$maxAge}");
                return false;
            }
            
            // Check for nonce replay (if enabled)
            if ($this->isNonceUsed($payload['nonce'], $expectedAgentPublicId)) {
                error_log("Nonce replay detected: {$payload['nonce']}");
                return false;
            }
            
            // Mark nonce as used
            $this->markNonceUsed($payload['nonce'], $expectedAgentPublicId, $maxAge);
            
            return $payload;
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a cryptographically secure nonce
     * 
     * @return string 16-character base62 nonce
     */
    private function generateNonce() {
        $bytes = random_bytes(12);
        return substr(bin2hex($bytes), 0, 16);
    }
    
    /**
     * Check if a nonce has been used
     * 
     * @param string $nonce The nonce to check
     * @param string $agentPublicId Agent public ID
     * @return bool True if nonce was already used
     */
    private function isNonceUsed($nonce, $agentPublicId) {
        if (!$this->db) {
            return false; // Skip replay protection if no DB
        }
        
        try {
            $sql = "SELECT COUNT(*) as count FROM whitelabel_tokens 
                    WHERE nonce = ? AND agent_public_id = ?";
            $result = $this->db->query($sql, [$nonce, $agentPublicId]);
            return ($result[0]['count'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("Error checking nonce: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark a nonce as used
     * 
     * @param string $nonce The nonce to mark
     * @param string $agentPublicId Agent public ID
     * @param int $expiresAt Unix timestamp when this nonce expires
     */
    private function markNonceUsed($nonce, $agentPublicId, $expiresAt) {
        if (!$this->db) {
            return; // Skip if no DB
        }
        
        try {
            $sql = "INSERT INTO whitelabel_tokens (nonce, agent_public_id, used_at, expires_at, client_ip, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $params = [
                $nonce,
                $agentPublicId,
                time(),
                $expiresAt,
                $_SERVER['REMOTE_ADDR'] ?? null,
                date('c')
            ];
            $this->db->insert($sql, $params);
        } catch (Exception $e) {
            error_log("Error marking nonce as used: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up expired nonces
     * Should be called periodically (e.g., via cron)
     */
    public function cleanupExpiredNonces() {
        if (!$this->db) {
            return;
        }
        
        try {
            $sql = "DELETE FROM whitelabel_tokens WHERE expires_at < ?";
            $deleted = $this->db->execute($sql, [time()]);
            error_log("Cleaned up {$deleted} expired whitelabel nonces");
            return $deleted;
        } catch (Exception $e) {
            error_log("Error cleaning up nonces: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Base64 URL-safe encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL-safe decode
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Generate a secure HMAC secret for an agent
     * 
     * @return string 64-character hex secret
     */
    public static function generateHmacSecret() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generate a unique public ID for an agent
     * 
     * @return string 24-character base62 public ID
     */
    public static function generatePublicId() {
        $bytes = random_bytes(18);
        $hex = bin2hex($bytes);
        
        // Convert to base62-like representation for URL friendliness
        $base62 = self::hexToBase62($hex);
        
        return 'PUB_' . substr($base62, 0, 20);
    }
    
    /**
     * Convert hex string to base62-like representation
     */
    private static function hexToBase62($hex) {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($alphabet);
        
        // Convert hex to decimal (as string to handle large numbers)
        $decimal = gmp_init($hex, 16);
        
        if (gmp_cmp($decimal, 0) === 0) {
            return $alphabet[0];
        }
        
        $result = '';
        while (gmp_cmp($decimal, 0) > 0) {
            $remainder = gmp_mod($decimal, $base);
            $result = $alphabet[gmp_intval($remainder)] . $result;
            $decimal = gmp_div($decimal, $base);
        }
        
        return $result;
    }
}
