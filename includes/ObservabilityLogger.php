<?php
/**
 * Structured Logging Service for Observability
 * 
 * Provides JSON-formatted structured logging with support for:
 * - Multiple log levels (debug, info, warn, error, critical)
 * - Contextual metadata (tenant, agent, user, trace ID)
 * - PII redaction
 * - Log sampling for high-volume events
 * - Asynchronous logging buffer
 */

class ObservabilityLogger {
    private $config;
    private $logFile;
    private $minLevel;
    private $buffer = [];
    private $bufferSize = 50;
    private $sensitiveKeys = [
        'password', 'token', 'api_key', 'secret', 'authorization',
        'openai_api_key', 'admin_token', 'encryption_key'
    ];
    
    // Log levels (ordered by severity)
    const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warn' => 2,
        'error' => 3,
        'critical' => 4
    ];
    
    public function __construct($config = []) {
        $this->config = $config;
        
        // Determine log file location
        $this->logFile = $config['logging']['file'] ?? '/var/log/chatbot/application.log';
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // If directory doesn't exist or isn't writable, fall back to temp
        if (!is_dir($logDir) || !is_writable($logDir)) {
            $this->logFile = sys_get_temp_dir() . '/chatbot_application.log';
        }
        
        // Set minimum log level
        $configLevel = strtolower($config['logging']['level'] ?? 'info');
        $this->minLevel = self::LEVELS[$configLevel] ?? self::LEVELS['info'];
        
        // Register shutdown function to flush buffer
        register_shutdown_function([$this, 'flush']);
    }
    
    /**
     * Log a debug message
     */
    public function debug($component, $event, $context = []) {
        $this->log('debug', $component, $event, $context);
    }
    
    /**
     * Log an info message
     */
    public function info($component, $event, $context = []) {
        $this->log('info', $component, $event, $context);
    }
    
    /**
     * Log a warning message
     */
    public function warn($component, $event, $context = []) {
        $this->log('warn', $component, $event, $context);
    }
    
    /**
     * Log an error message
     */
    public function error($component, $event, $context = []) {
        $this->log('error', $component, $event, $context);
    }
    
    /**
     * Log a critical message
     */
    public function critical($component, $event, $context = []) {
        $this->log('critical', $component, $event, $context);
    }
    
    /**
     * Core logging method
     */
    private function log($level, $component, $event, $context = []) {
        // Check if this log level should be written
        if (self::LEVELS[$level] < $this->minLevel) {
            return;
        }
        
        // Apply sampling for debug logs
        if ($level === 'debug' && !$this->shouldSample('debug', 0.1)) {
            return;
        }
        
        // Build log entry
        $entry = [
            'ts' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $level,
            'component' => $component,
            'event' => $event,
            'trace_id' => $this->getTraceId(),
            'context' => $this->sanitizeContext($context)
        ];
        
        // Add to buffer
        $this->buffer[] = $entry;
        
        // Flush if buffer is full or if critical/error
        if (count($this->buffer) >= $this->bufferSize || in_array($level, ['error', 'critical'])) {
            $this->flush();
        }
    }
    
    /**
     * Flush buffered log entries to file
     */
    public function flush() {
        if (empty($this->buffer)) {
            return;
        }
        
        $entries = $this->buffer;
        $this->buffer = [];
        
        $lines = array_map(function($entry) {
            return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }, $entries);
        
        $content = implode('', $lines);
        
        // Write to file (append mode)
        @file_put_contents($this->logFile, $content, FILE_APPEND | LOCK_EX);
        
        // Also write errors to PHP error log for visibility
        foreach ($entries as $entry) {
            if (in_array($entry['level'], ['error', 'critical'])) {
                error_log(sprintf(
                    '[%s] %s.%s: %s',
                    strtoupper($entry['level']),
                    $entry['component'],
                    $entry['event'],
                    json_encode($entry['context'])
                ));
            }
        }
    }
    
    /**
     * Sanitize context to remove sensitive data
     */
    private function sanitizeContext($context) {
        if (!is_array($context)) {
            return $context;
        }
        
        $sanitized = [];
        foreach ($context as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Check if key is sensitive
            $isSensitive = false;
            foreach ($this->sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get or generate trace ID for request
     */
    private function getTraceId() {
        // Check if trace ID already exists in request
        if (isset($_SERVER['HTTP_X_TRACE_ID'])) {
            return $_SERVER['HTTP_X_TRACE_ID'];
        }
        
        // Check if we've already generated one for this request
        static $traceId = null;
        if ($traceId === null) {
            // Generate new trace ID (format: 32 hex chars)
            $traceId = bin2hex(random_bytes(16));
        }
        
        return $traceId;
    }
    
    /**
     * Determine if a log entry should be sampled (written)
     * 
     * @param string $level Log level
     * @param float $rate Sampling rate (0.0 to 1.0)
     * @return bool True if should be logged
     */
    private function shouldSample($level, $rate) {
        // Always log errors and critical
        if (in_array($level, ['error', 'critical', 'warn'])) {
            return true;
        }
        
        // Sample based on rate
        return (mt_rand(1, 100) / 100) <= $rate;
    }
    
    /**
     * Get current trace ID
     */
    public function getCurrentTraceId() {
        return $this->getTraceId();
    }
    
    /**
     * Set trace ID for current request
     */
    public function setTraceId($traceId) {
        $_SERVER['HTTP_X_TRACE_ID'] = $traceId;
    }
}
