<?php
/**
 * Error Handler
 * 
 * Sanitizes error messages and context to prevent accidental exposure
 * of sensitive information like API keys, passwords, file paths, etc.
 * 
 * @package GPT_Chatbot
 */

class ErrorHandler
{
    /**
     * Sensitive keys to redact in context arrays
     * 
     * @var array<string>
     */
    private static array $sensitiveKeys = [
        'password',
        'api_key',
        'apikey',
        'token',
        'secret',
        'authorization',
        'cookie',
        'session',
        'bearer',
        'key',
        'passphrase',
        'credentials',
    ];
    
    /**
     * Sanitize error message to remove sensitive information
     * 
     * @param string $message Error message to sanitize
     * @return string Sanitized message
     */
    public static function sanitize(string $message): string
    {
        // Remove API keys (sk-... format)
        $message = preg_replace('/sk-[a-zA-Z0-9_-]{32,}/', '[API_KEY_REDACTED]', $message);
        
        // Remove other potential API keys (long alphanumeric strings)
        $message = preg_replace('/\b[a-zA-Z0-9_-]{32,}\b/', '[TOKEN_REDACTED]', $message);
        
        // Remove Bearer tokens
        $message = preg_replace('/Bearer\s+[a-zA-Z0-9_-]+/', 'Bearer [REDACTED]', $message);
        
        // Remove passwords from various formats
        $message = preg_replace('/password["\'\s:=]+[^"\'\s]+/i', 'password=[REDACTED]', $message);
        $message = preg_replace('/pwd["\'\s:=]+[^"\'\s]+/i', 'pwd=[REDACTED]', $message);
        
        // Remove database connection strings
        $message = preg_replace('/mysql:\/\/[^@]+@/', 'mysql://[REDACTED]@', $message);
        $message = preg_replace('/postgres:\/\/[^@]+@/', 'postgres://[REDACTED]@', $message);
        
        // Remove absolute file paths (common Linux/Unix paths)
        $message = preg_replace('/\/var\/www\/[^\s"\'\)]+/', '[PATH_REDACTED]', $message);
        $message = preg_replace('/\/home\/[^\s"\'\)]+/', '[PATH_REDACTED]', $message);
        $message = preg_replace('/\/usr\/[^\s"\'\)]+/', '[PATH_REDACTED]', $message);
        $message = preg_replace('/\/opt\/[^\s"\'\)]+/', '[PATH_REDACTED]', $message);
        
        // Remove Windows paths
        $message = preg_replace('/[C-Z]:\\\\[^\s"\'\)]+/', '[PATH_REDACTED]', $message);
        
        // Remove email addresses (might contain sensitive info)
        $message = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL_REDACTED]', $message);
        
        // Remove IP addresses (might expose infrastructure)
        $message = preg_replace('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', '[IP_REDACTED]', $message);
        
        // Remove JWT tokens (three parts separated by dots)
        $message = preg_replace('/eyJ[a-zA-Z0-9_-]+\.eyJ[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+/', '[JWT_REDACTED]', $message);
        
        return $message;
    }
    
    /**
     * Log error safely with sanitized message and context
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public static function logError(string $message, array $context = []): void
    {
        // Sanitize message
        $safeMessage = self::sanitize($message);
        
        // Sanitize context
        $safeContext = self::sanitizeContext($context);
        
        // Log with context if provided
        if (!empty($safeContext)) {
            error_log($safeMessage . ' ' . json_encode($safeContext));
        } else {
            error_log($safeMessage);
        }
    }
    
    /**
     * Sanitize context array recursively
     * 
     * @param array $context Context array to sanitize
     * @return array Sanitized context
     */
    public static function sanitizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string)$key);
            
            // Check if key contains sensitive keywords
            $isSensitive = false;
            foreach (self::$sensitiveKeys as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                // Redact sensitive values
                if (is_string($value) && strlen($value) > 8) {
                    $context[$key] = substr($value, 0, 4) . '***' . substr($value, -4);
                } else {
                    $context[$key] = '[REDACTED]';
                }
                continue;
            }
            
            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $context[$key] = self::sanitizeContext($value);
            }
            
            // Sanitize string values
            if (is_string($value)) {
                $context[$key] = self::sanitize($value);
            }
        }
        
        return $context;
    }
    
    /**
     * Get user-friendly error message (hides technical details in production)
     * 
     * @param string $message Original error message
     * @param bool $isProduction Whether in production environment
     * @return string User-friendly error message
     */
    public static function getUserMessage(string $message, bool $isProduction = true): string
    {
        if (!$isProduction) {
            return self::sanitize($message);
        }
        
        // In production, return generic message
        return 'An error occurred. Please try again later or contact support if the problem persists.';
    }
    
    /**
     * Format exception for logging
     * 
     * @param Throwable $e Exception to format
     * @param bool $includeSensitiveInfo Whether to include potentially sensitive info (dev only)
     * @return string Formatted exception message
     */
    public static function formatException(Throwable $e, bool $includeSensitiveInfo = false): string
    {
        $message = self::sanitize($e->getMessage());
        
        if ($includeSensitiveInfo) {
            $file = self::sanitize($e->getFile());
            $line = $e->getLine();
            return "$message (in $file:$line)";
        }
        
        return $message;
    }
}
