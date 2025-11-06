<?php
/**
 * Distributed Tracing Service
 * 
 * Implements OpenTelemetry-compatible distributed tracing.
 * Propagates trace IDs across API calls, background jobs, and webhooks.
 */

class TracingService {
    private $traceId;
    private $spanId;
    private $parentSpanId;
    private $spans = [];
    private $logger;
    
    public function __construct($logger = null) {
        $this->logger = $logger;
        
        // Try to extract trace context from headers
        $this->extractTraceContext();
        
        // Generate new trace ID if not found
        if (!$this->traceId) {
            $this->traceId = $this->generateId(16);
        }
        
        // Generate root span ID
        if (!$this->spanId) {
            $this->spanId = $this->generateId(8);
        }
    }
    
    /**
     * Extract trace context from HTTP headers (W3C Trace Context format)
     */
    private function extractTraceContext(): void {
        // Check for W3C Trace Context header (traceparent)
        if (isset($_SERVER['HTTP_TRACEPARENT'])) {
            $parts = explode('-', $_SERVER['HTTP_TRACEPARENT']);
            if (count($parts) >= 4) {
                $this->traceId = $parts[1];
                $this->parentSpanId = $parts[2];
            }
        }
        
        // Fallback to X-Trace-Id header
        if (!$this->traceId && isset($_SERVER['HTTP_X_TRACE_ID'])) {
            $this->traceId = $_SERVER['HTTP_X_TRACE_ID'];
        }
        
        // Fallback to X-Request-Id header
        if (!$this->traceId && isset($_SERVER['HTTP_X_REQUEST_ID'])) {
            $this->traceId = $_SERVER['HTTP_X_REQUEST_ID'];
        }
    }
    
    /**
     * Generate a random ID
     */
    private function generateId(int $bytes): string {
        return bin2hex(random_bytes($bytes));
    }
    
    /**
     * Get current trace ID
     */
    public function getTraceId(): string {
        return $this->traceId;
    }
    
    /**
     * Get current span ID
     */
    public function getSpanId(): string {
        return $this->spanId;
    }
    
    /**
     * Create a new span
     */
    public function startSpan(string $name, array $attributes = []): string {
        $spanId = $this->generateId(8);
        
        $span = [
            'span_id' => $spanId,
            'trace_id' => $this->traceId,
            'parent_span_id' => $this->spanId,
            'name' => $name,
            'start_time' => microtime(true),
            'attributes' => $attributes,
        ];
        
        $this->spans[$spanId] = $span;
        
        if ($this->logger) {
            $this->logger->debug("Started span: $name", [
                'trace_id' => $this->traceId,
                'span_id' => $spanId,
                'parent_span_id' => $this->spanId,
            ]);
        }
        
        return $spanId;
    }
    
    /**
     * End a span
     */
    public function endSpan(string $spanId, array $attributes = []): void {
        if (!isset($this->spans[$spanId])) {
            return;
        }
        
        $span = &$this->spans[$spanId];
        $span['end_time'] = microtime(true);
        $span['duration'] = $span['end_time'] - $span['start_time'];
        $span['attributes'] = array_merge($span['attributes'], $attributes);
        
        if ($this->logger) {
            $this->logger->debug("Ended span: {$span['name']}", [
                'trace_id' => $this->traceId,
                'span_id' => $spanId,
                'duration_ms' => round($span['duration'] * 1000, 2),
            ]);
        }
    }
    
    /**
     * Add event to current span
     */
    public function addEvent(string $spanId, string $name, array $attributes = []): void {
        if (!isset($this->spans[$spanId])) {
            return;
        }
        
        if (!isset($this->spans[$spanId]['events'])) {
            $this->spans[$spanId]['events'] = [];
        }
        
        $this->spans[$spanId]['events'][] = [
            'name' => $name,
            'timestamp' => microtime(true),
            'attributes' => $attributes,
        ];
    }
    
    /**
     * Set error on span
     */
    public function recordError(string $spanId, Throwable $exception): void {
        if (!isset($this->spans[$spanId])) {
            return;
        }
        
        $this->spans[$spanId]['error'] = true;
        $this->spans[$spanId]['error_message'] = $exception->getMessage();
        $this->spans[$spanId]['error_type'] = get_class($exception);
        $this->spans[$spanId]['error_stack'] = $exception->getTraceAsString();
    }
    
    /**
     * Get trace context for propagation (W3C format)
     */
    public function getTraceContext(): string {
        // W3C Trace Context format: version-trace_id-span_id-trace_flags
        return sprintf('00-%s-%s-01', $this->traceId, $this->spanId);
    }
    
    /**
     * Get headers for trace propagation
     */
    public function getPropagationHeaders(): array {
        return [
            'traceparent' => $this->getTraceContext(),
            'X-Trace-Id' => $this->traceId,
            'X-Span-Id' => $this->spanId,
        ];
    }
    
    /**
     * Execute a function within a span
     */
    public function trace(string $name, callable $fn, array $attributes = []) {
        $spanId = $this->startSpan($name, $attributes);
        
        try {
            $result = $fn($spanId);
            $this->endSpan($spanId, ['status' => 'ok']);
            return $result;
        } catch (Throwable $e) {
            $this->recordError($spanId, $e);
            $this->endSpan($spanId, ['status' => 'error']);
            throw $e;
        }
    }
    
    /**
     * Get all spans for export
     */
    public function getSpans(): array {
        return array_values($this->spans);
    }
    
    /**
     * Export spans to OpenTelemetry format
     */
    public function exportSpans(): array {
        $export = [];
        
        foreach ($this->spans as $span) {
            $export[] = [
                'traceId' => $span['trace_id'],
                'spanId' => $span['span_id'],
                'parentSpanId' => $span['parent_span_id'] ?? null,
                'name' => $span['name'],
                'startTimeUnixNano' => (int)($span['start_time'] * 1e9),
                'endTimeUnixNano' => isset($span['end_time']) ? (int)($span['end_time'] * 1e9) : null,
                'attributes' => $span['attributes'] ?? [],
                'events' => $span['events'] ?? [],
                'status' => [
                    'code' => isset($span['error']) ? 'ERROR' : 'OK',
                    'message' => $span['error_message'] ?? '',
                ],
            ];
        }
        
        return $export;
    }
    
    /**
     * Log all spans (useful for debugging)
     */
    public function logSpans(): void {
        if (!$this->logger) {
            return;
        }
        
        foreach ($this->spans as $span) {
            $this->logger->info("Trace span", [
                'trace_id' => $span['trace_id'],
                'span_id' => $span['span_id'],
                'name' => $span['name'],
                'duration_ms' => isset($span['duration']) ? round($span['duration'] * 1000, 2) : null,
                'attributes' => $span['attributes'] ?? [],
            ]);
        }
    }
}
