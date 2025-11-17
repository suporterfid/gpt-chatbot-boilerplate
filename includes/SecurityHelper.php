<?php
/**
 * Security Helper - Cryptographic and timing-safe operations
 * 
 * Provides secure implementations for authentication operations to prevent
 * timing attacks, token enumeration, and other security vulnerabilities.
 */

class SecurityHelper
{
    /**
     * Constant-time string comparison to prevent timing attacks
     * 
     * Uses PHP's built-in hash_equals which implements constant-time comparison
     * to prevent attackers from determining correct values through timing analysis.
     * 
     * @param string $knownString The known token/password from storage
     * @param string $userInput The user-provided value to compare
     * @return bool True if strings match exactly
     */
    public static function timingSafeEquals(string $knownString, string $userInput): bool
    {
        // PHP's hash_equals is implemented in constant time
        return hash_equals($knownString, $userInput);
    }
    
    /**
     * Verify token with additional hashing to mask length differences
     * 
     * Hashes both inputs before comparison to ensure constant-time comparison
     * even if the strings have different lengths. This prevents length-based
     * timing attacks.
     * 
     * @param string $providedToken Token from request
     * @param string $validToken Token from database/config
     * @return bool True if tokens match
     */
    public static function verifyToken(string $providedToken, string $validToken): bool
    {
        // Hash both tokens to normalize length and add another layer of security
        $providedHash = hash('sha256', $providedToken);
        $validHash = hash('sha256', $validToken);
        
        return hash_equals($validHash, $providedHash);
    }
    
    /**
     * Verify hashed token (when token is already hashed in database)
     * 
     * This is used when tokens are stored as SHA256 hashes in the database.
     * Hashes the provided token and compares it in constant time.
     * 
     * @param string $providedToken Plain token from request
     * @param string $storedHash SHA256 hash from database
     * @return bool True if hashes match
     */
    public static function verifyHashedToken(string $providedToken, string $storedHash): bool
    {
        $providedHash = hash('sha256', $providedToken);
        
        return hash_equals($storedHash, $providedHash);
    }
    
    /**
     * Generate secure random token
     * 
     * Generates cryptographically secure random tokens suitable for
     * authentication, session management, and API keys.
     * 
     * @param int $length Number of random bytes (output will be hex-encoded, so 2x length)
     * @param string $prefix Optional prefix for the token
     * @return string Secure random token
     */
    public static function generateSecureToken(int $length = 32, string $prefix = ''): string
    {
        if ($length < 16) {
            throw new InvalidArgumentException('Token length must be at least 16 bytes');
        }
        
        $randomBytes = random_bytes($length);
        $token = bin2hex($randomBytes);
        
        if ($prefix !== '') {
            return $prefix . '_' . $token;
        }
        
        return $token;
    }
    
    /**
     * Ensure minimum execution time to prevent timing analysis
     * 
     * Adds artificial delay to ensure operations take a minimum amount of time,
     * masking timing differences between success and failure paths.
     * 
     * @param float $startTime Microtime when operation started (from microtime(true))
     * @param float $minimumTime Minimum time in seconds (default: 0.1 = 100ms)
     */
    public static function ensureMinimumTime(float $startTime, float $minimumTime = 0.1): void
    {
        $elapsed = microtime(true) - $startTime;
        
        if ($elapsed < $minimumTime) {
            $sleepMicroseconds = (int)(($minimumTime - $elapsed) * 1000000);
            usleep($sleepMicroseconds);
        }
    }
    
    /**
     * Check if rate limit is exceeded
     * 
     * Uses APCu cache to track authentication attempts and implements
     * exponential backoff for repeated failures.
     * 
     * @param string $identifier Client identifier (IP address, user ID, etc.)
     * @param int $maxAttempts Maximum attempts before rate limiting kicks in
     * @param int $windowSeconds Time window for tracking attempts
     * @return array ['allowed' => bool, 'attempts' => int, 'retry_after' => int|null]
     */
    public static function checkRateLimit(
        string $identifier,
        int $maxAttempts = 5,
        int $windowSeconds = 3600
    ): array {
        // Check if APCu is available
        if (!function_exists('apcu_fetch')) {
            // Rate limiting not available, allow request but log warning
            error_log('APCu not available for rate limiting. Install/enable APCu extension.');
            return [
                'allowed' => true,
                'attempts' => 0,
                'retry_after' => null
            ];
        }
        
        $cacheKey = "rate_limit:" . hash('sha256', $identifier);
        $attempts = apcu_fetch($cacheKey);
        
        if ($attempts === false) {
            $attempts = 0;
        }
        
        if ($attempts >= $maxAttempts) {
            // Calculate exponential backoff: 2^(attempts - maxAttempts) seconds, max 300s (5 min)
            $backoffTime = min(pow(2, $attempts - $maxAttempts + 1), 300);
            
            return [
                'allowed' => false,
                'attempts' => $attempts,
                'retry_after' => (int)$backoffTime
            ];
        }
        
        return [
            'allowed' => true,
            'attempts' => $attempts,
            'retry_after' => null
        ];
    }
    
    /**
     * Record authentication attempt
     * 
     * Increments the attempt counter for rate limiting.
     * 
     * @param string $identifier Client identifier
     * @param int $windowSeconds Time window for tracking attempts
     * @return int Number of attempts
     */
    public static function recordAttempt(string $identifier, int $windowSeconds = 3600): int
    {
        if (!function_exists('apcu_fetch')) {
            return 0;
        }
        
        $cacheKey = "rate_limit:" . hash('sha256', $identifier);
        $attempts = apcu_fetch($cacheKey);
        
        if ($attempts === false) {
            $attempts = 0;
        }
        
        $attempts++;
        apcu_store($cacheKey, $attempts, $windowSeconds);
        
        return $attempts;
    }
    
    /**
     * Clear rate limit for identifier
     * 
     * Called after successful authentication to reset the counter.
     * 
     * @param string $identifier Client identifier
     */
    public static function clearRateLimit(string $identifier): void
    {
        if (!function_exists('apcu_fetch')) {
            return;
        }
        
        $cacheKey = "rate_limit:" . hash('sha256', $identifier);
        apcu_delete($cacheKey);
    }
    
    /**
     * Validate token format without revealing exact requirements
     * 
     * Performs basic format validation without giving attackers information
     * about the exact token format.
     * 
     * @param string $token Token to validate
     * @return bool True if format is valid
     */
    public static function isValidTokenFormat(string $token): bool
    {
        $length = strlen($token);
        
        // Accept reasonable token lengths (20-256 chars)
        if ($length < 20 || $length > 256) {
            return false;
        }
        
        // Check for obviously invalid tokens (only whitespace, null bytes, etc.)
        if (trim($token) === '' || strpos($token, "\0") !== false) {
            return false;
        }
        
        return true;
    }
}
