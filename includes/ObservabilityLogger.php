<?php
/**
 * Structured Logging Service with Context Propagation
 * 
 * Provides JSON-formatted logging with trace IDs, tenant context,
 * and integration with centralized logging systems (ELK, Loki, Datadog).
 */

class ObservabilityLogger {
    private $logLevel;
    private $logFile;
    private $context = [];
    private $traceId;
    
    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;
    
    private static $levels = [
        'emergency' => self::EMERGENCY,
        'alert' => self::ALERT,
        'critical' => self::CRITICAL,
        'error' => self::ERROR,
        'warning' => self::WARNING,
        'notice' => self::NOTICE,
        'info' => self::INFO,
        'debug' => self::DEBUG,
    ];
    
    public function __construct(array $config = []) {
        $this->logLevel = self::$levels[$config['level'] ?? 'info'] ?? self::INFO;
        $this->logFile = $config['file'] ?? 'php://stderr';
        
        // Generate trace ID if not provided
        $this->traceId = $this->generateTraceId();
    }
    
    /**
     * Set global context that will be included in all log entries
     */
    public function setContext(array $context): void {
        $this->context = array_merge($this->context, $context);
    }
    
    /**
     * Set trace ID for distributed tracing
     */
    public function setTraceId(string $traceId): void {
        $this->traceId = $traceId;
    }
    
    /**
     * Get current trace ID
     */
    public function getTraceId(): string {
        return $this->traceId;
    }
    
    /**
     * Generate a unique trace ID
     */
    private function generateTraceId(): string {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Log a message with specified level and context
     */
    private function log(int $level, string $message, array $context = []): void {
        if ($level > $this->logLevel) {
            return;
        }
        
        $levelName = array_search($level, self::$levels);
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => strtoupper($levelName),
            'message' => $message,
            'trace_id' => $this->traceId,
            'context' => array_merge($this->context, $context),
        ];
        
        // Add exception details if present
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $logEntry['exception'] = [
                'class' => get_class($context['exception']),
                'message' => $context['exception']->getMessage(),
                'code' => $context['exception']->getCode(),
                'file' => $context['exception']->getFile(),
                'line' => $context['exception']->getLine(),
                'trace' => $context['exception']->getTraceAsString(),
            ];
            unset($logEntry['context']['exception']);
        }
        
        // Add request context if available
        if (!isset($logEntry['context']['request']) && !empty($_SERVER)) {
            $logEntry['context']['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ];
        }
        
        $jsonLog = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        // Write to log file
        if ($this->logFile === 'php://stderr' || $this->logFile === 'php://stdout') {
            file_put_contents($this->logFile, $jsonLog, FILE_APPEND);
        } else {
            error_log($jsonLog, 3, $this->logFile);
        }
    }
    
    public function emergency(string $message, array $context = []): void {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    public function alert(string $message, array $context = []): void {
        $this->log(self::ALERT, $message, $context);
    }
    
    public function critical(string $message, array $context = []): void {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    public function error(string $message, array $context = []): void {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function warning(string $message, array $context = []): void {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function notice(string $message, array $context = []): void {
        $this->log(self::NOTICE, $message, $context);
    }
    
    public function info(string $message, array $context = []): void {
        $this->log(self::INFO, $message, $context);
    }
    
    public function debug(string $message, array $context = []): void {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log an API request with timing
     */
    public function logApiRequest(string $endpoint, string $method, float $duration, int $statusCode, array $context = []): void {
        $this->info("API request completed", array_merge($context, [
            'endpoint' => $endpoint,
            'method' => $method,
            'duration_ms' => round($duration * 1000, 2),
            'status_code' => $statusCode,
        ]));
    }
    
    /**
     * Log OpenAI API call
     */
    public function logOpenAICall(string $apiType, string $model, float $duration, bool $success, array $context = []): void {
        $level = $success ? 'info' : 'error';
        $this->$level("OpenAI API call", array_merge($context, [
            'api_type' => $apiType,
            'model' => $model,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
        ]));
    }
}
