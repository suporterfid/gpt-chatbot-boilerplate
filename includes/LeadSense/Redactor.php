<?php
/**
 * Redactor - Masks PII (Personally Identifiable Information) for privacy
 * 
 * Used to redact sensitive data before logging or sending notifications
 */

class Redactor {
    private $config;
    
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    /**
     * Redact PII from text
     * 
     * @param string $text Text to redact
     * @param array $options Redaction options
     * @return string Redacted text
     */
    public function redact($text, $options = []) {
        if (!$this->shouldRedact()) {
            return $text;
        }
        
        $redacted = $text;
        
        // Redact emails
        if (!isset($options['skip_email']) || !$options['skip_email']) {
            $redacted = $this->redactEmail($redacted);
        }
        
        // Redact phone numbers
        if (!isset($options['skip_phone']) || !$options['skip_phone']) {
            $redacted = $this->redactPhone($redacted);
        }
        
        return $redacted;
    }
    
    /**
     * Redact a lead object for notifications/logs
     * 
     * @param array $lead Lead data
     * @return array Redacted lead data
     */
    public function redactLead($lead) {
        if (!$this->shouldRedact()) {
            return $lead;
        }
        
        $redacted = $lead;
        
        // Mask email
        if (isset($redacted['email']) && !empty($redacted['email'])) {
            $redacted['email'] = $this->maskEmail($redacted['email']);
        }
        
        // Mask phone
        if (isset($redacted['phone']) && !empty($redacted['phone'])) {
            $redacted['phone'] = $this->maskPhone($redacted['phone']);
        }
        
        return $redacted;
    }
    
    /**
     * Check if redaction is enabled
     * 
     * @return bool
     */
    private function shouldRedact() {
        return $this->config['pii_redaction'] ?? true;
    }
    
    /**
     * Redact email addresses from text
     * 
     * @param string $text
     * @return string
     */
    private function redactEmail($text) {
        $pattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';
        return preg_replace_callback($pattern, function($matches) {
            return $this->maskEmail($matches[0]);
        }, $text);
    }
    
    /**
     * Redact phone numbers from text
     * 
     * @param string $text
     * @return string
     */
    private function redactPhone($text) {
        $patterns = [
            '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',
            '/\b\(\d{3}\)\s?\d{3}[-.\s]?\d{4}\b/',
            '/\b\+\d{1,3}[\s.-]?\d{1,14}\b/',
            '/\b\d{10,11}\b/'
        ];
        
        foreach ($patterns as $pattern) {
            $text = preg_replace_callback($pattern, function($matches) {
                return $this->maskPhone($matches[0]);
            }, $text);
        }
        
        return $text;
    }
    
    /**
     * Mask an email address
     * 
     * @param string $email
     * @return string
     */
    private function maskEmail($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        // Show first 2 chars of username, rest as asterisks
        $usernameLen = strlen($username);
        $visibleChars = min(2, $usernameLen);
        $maskedUsername = substr($username, 0, $visibleChars) . str_repeat('*', max(0, $usernameLen - $visibleChars));
        
        // Show first char of domain
        $domainParts = explode('.', $domain);
        $maskedDomain = substr($domainParts[0], 0, 1) . '***';
        if (count($domainParts) > 1) {
            $maskedDomain .= '.' . end($domainParts);
        }
        
        return $maskedUsername . '@' . $maskedDomain;
    }
    
    /**
     * Mask a phone number
     * 
     * @param string $phone
     * @return string
     */
    private function maskPhone($phone) {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);
        
        if ($len < 4) {
            return '***';
        }
        
        // Show last 4 digits, mask the rest
        $lastFour = substr($digits, -4);
        $masked = str_repeat('*', $len - 4) . $lastFour;
        
        // Try to preserve original format
        if (strpos($phone, '+') === 0) {
            return '+' . $masked;
        } elseif (strpos($phone, '(') !== false) {
            return '(***) ***-' . $lastFour;
        } elseif (strpos($phone, '-') !== false) {
            return '***-***-' . $lastFour;
        }
        
        return $masked;
    }
}
