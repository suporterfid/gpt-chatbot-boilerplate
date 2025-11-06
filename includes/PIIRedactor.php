<?php
/**
 * PII Redactor Service
 * Handles redaction of personally identifiable information
 */

class PIIRedactor {
    private $patterns = [];
    
    public function __construct($config = []) {
        $this->loadPatterns($config);
    }
    
    /**
     * Load redaction patterns from configuration
     */
    private function loadPatterns($config) {
        $patternsEnv = $config['pii_redaction_patterns'] ?? '';
        
        // Default patterns
        $this->patterns = [
            // Email addresses
            'email' => [
                'pattern' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
                'replacement' => '[EMAIL_REDACTED]'
            ],
            // Phone numbers (various formats)
            'phone' => [
                'pattern' => '/\b(?:\+?1[-.]?)?\(?\d{3}\)?[-.]?\d{3}[-.]?\d{4}\b/',
                'replacement' => '[PHONE_REDACTED]'
            ],
            // Credit card numbers
            'credit_card' => [
                'pattern' => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
                'replacement' => '[CARD_REDACTED]'
            ],
            // SSN (US Social Security Numbers)
            'ssn' => [
                'pattern' => '/\b\d{3}-\d{2}-\d{4}\b/',
                'replacement' => '[SSN_REDACTED]'
            ],
            // IP addresses
            'ip' => [
                'pattern' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
                'replacement' => '[IP_REDACTED]'
            ]
        ];
        
        // Parse custom patterns from ENV if provided
        if (!empty($patternsEnv)) {
            $customPatterns = $this->parseCustomPatterns($patternsEnv);
            $this->patterns = array_merge($this->patterns, $customPatterns);
        }
    }
    
    /**
     * Parse custom patterns from CSV or JSON format
     */
    private function parseCustomPatterns($patternsEnv) {
        $custom = [];
        
        // Try JSON first
        $decoded = json_decode($patternsEnv, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        // Fall back to CSV format: name:pattern:replacement
        $lines = explode(',', $patternsEnv);
        foreach ($lines as $line) {
            $parts = explode(':', trim($line), 3);
            if (count($parts) === 3) {
                $custom[$parts[0]] = [
                    'pattern' => $parts[1],
                    'replacement' => $parts[2]
                ];
            }
        }
        
        return $custom;
    }
    
    /**
     * Redact PII from text
     * 
     * @param string $text Text to redact
     * @return string Redacted text
     */
    public function redact($text) {
        if (empty($text)) {
            return $text;
        }
        
        $redacted = $text;
        
        foreach ($this->patterns as $name => $config) {
            $redacted = preg_replace(
                $config['pattern'],
                $config['replacement'],
                $redacted
            );
        }
        
        return $redacted;
    }
    
    /**
     * Check if text contains PII
     * 
     * @param string $text Text to check
     * @return array Array of detected PII types
     */
    public function detectPII($text) {
        if (empty($text)) {
            return [];
        }
        
        $detected = [];
        
        foreach ($this->patterns as $name => $config) {
            if (preg_match($config['pattern'], $text)) {
                $detected[] = $name;
            }
        }
        
        return $detected;
    }
}
