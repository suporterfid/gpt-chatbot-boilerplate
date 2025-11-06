<?php
/**
 * Observability Middleware
 * 
 * Integrates logging, metrics, and tracing for API requests.
 * Automatically tracks requests, records metrics, and propagates trace context.
 */

require_once __DIR__ . '/ObservabilityLogger.php';
require_once __DIR__ . '/MetricsCollector.php';
require_once __DIR__ . '/TracingService.php';

class ObservabilityMiddleware {
    private $logger;
    private $metrics;
    private $tracing;
    private $startTime;
    private $config;
    
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->startTime = microtime(true);
        
        // Initialize observability components
        $loggingConfig = $config['logging'] ?? [];
        $this->logger = new ObservabilityLogger($loggingConfig);
        
        $metricsConfig = $config['metrics'] ?? [];
        $this->metrics = MetricsCollector::getInstance($metricsConfig);
        
        $this->tracing = new TracingService($this->logger);
        
        // Set trace ID in logger
        $this->logger->setTraceId($this->tracing->getTraceId());
        
        // Set global context
        $this->logger->setContext([
            'service' => 'chatbot',
            'environment' => $config['environment'] ?? 'production',
        ]);
    }
    
    /**
     * Get logger instance
     */
    public function getLogger(): ObservabilityLogger {
        return $this->logger;
    }
    
    /**
     * Get metrics collector instance
     */
    public function getMetrics(): MetricsCollector {
        return $this->metrics;
    }
    
    /**
     * Get tracing service instance
     */
    public function getTracing(): TracingService {
        return $this->tracing;
    }
    
    /**
     * Handle request start
     */
    public function handleRequestStart(string $endpoint, array $context = []): string {
        // Start root span
        $spanId = $this->tracing->startSpan('http.request', array_merge([
            'http.method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'http.url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'http.endpoint' => $endpoint,
        ], $context));
        
        // Log request start
        $this->logger->info("Request started", array_merge([
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        ], $context));
        
        return $spanId;
    }
    
    /**
     * Handle request end
     */
    public function handleRequestEnd(string $spanId, string $endpoint, int $statusCode, array $context = []): void {
        $duration = microtime(true) - $this->startTime;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        
        // End span
        $this->tracing->endSpan($spanId, array_merge([
            'http.status_code' => $statusCode,
        ], $context));
        
        // Log request completion
        $this->logger->logApiRequest($endpoint, $method, $duration, $statusCode, $context);
        
        // Track metrics
        $this->metrics->trackApiRequest($endpoint, $method, $duration, $statusCode, $context);
        
        // Export traces if configured
        if ($this->config['tracing']['export'] ?? false) {
            $this->tracing->logSpans();
        }
    }
    
    /**
     * Handle error
     */
    public function handleError(string $spanId, Throwable $exception, array $context = []): void {
        // Record error in span
        $this->tracing->recordError($spanId, $exception);
        
        // Log error
        $this->logger->error("Request error: " . $exception->getMessage(), array_merge([
            'exception' => $exception,
        ], $context));
        
        // Track error metric
        $this->metrics->incrementCounter('chatbot_errors_total', array_merge([
            'error_type' => get_class($exception),
        ], $context));
    }
    
    /**
     * Track OpenAI API call
     */
    public function trackOpenAICall(string $apiType, string $model, float $duration, bool $success, array $context = []): void {
        // Log the call
        $this->logger->logOpenAICall($apiType, $model, $duration, $success, $context);
        
        // Track metrics
        $this->metrics->trackOpenAICall($apiType, $model, $duration, $success, $context);
    }
    
    /**
     * Create child span
     */
    public function createSpan(string $name, array $attributes = []): string {
        return $this->tracing->startSpan($name, $attributes);
    }
    
    /**
     * End span
     */
    public function endSpan(string $spanId, array $attributes = []): void {
        $this->tracing->endSpan($spanId, $attributes);
    }
    
    /**
     * Execute function with tracing
     */
    public function trace(string $name, callable $fn, array $attributes = []) {
        return $this->tracing->trace($name, $fn, $attributes);
    }
    
    /**
     * Get trace propagation headers for external calls
     */
    public function getTracePropagationHeaders(): array {
        return $this->tracing->getPropagationHeaders();
    }
    
    /**
     * Set tenant context
     */
    public function setTenantContext(string $tenantId, string $tenantName = null): void {
        $context = ['tenant_id' => $tenantId];
        if ($tenantName) {
            $context['tenant_name'] = $tenantName;
        }
        $this->logger->setContext($context);
    }
    
    /**
     * Set agent context
     */
    public function setAgentContext(string $agentId, string $agentName = null): void {
        $context = ['agent_id' => $agentId];
        if ($agentName) {
            $context['agent_name'] = $agentName;
        }
        $this->logger->setContext($context);
    }
    
    /**
     * Set user context
     */
    public function setUserContext(string $userId, array $userData = []): void {
        $this->logger->setContext(array_merge([
            'user_id' => $userId,
        ], $userData));
    }
    
    /**
     * Log structured message
     */
    public function log(string $level, string $message, array $context = []): void {
        $this->logger->$level($message, $context);
    }
}
