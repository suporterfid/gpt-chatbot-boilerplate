<?php
/**
 * Distributed Tracing Service
 * 
 * Provides distributed tracing capabilities for tracking requests across
 * multiple components and services. Tracks:
 * - Request spans (API calls, database queries, external API calls)
 * - Timing information
 * - Context propagation via trace IDs
 * - Error tracking within spans
 */

class TracingService {
    private $logger;
    private $spans = [];
    private $currentSpan = null;
    private $traceId;
    private $config;
    
    public function __construct($logger, $config = []) {
        $this->logger = $logger;
        $this->config = $config;
        
        // Get or generate trace ID
        $this->traceId = $this->getOrGenerateTraceId();
    }
    
    /**
     * Start a new span
     * 
     * @param string $name Span name (e.g., "openai.chat_completion")
     * @param array $attributes Additional attributes for the span
     * @return string Span ID
     */
    public function startSpan($name, $attributes = []) {
        $spanId = $this->generateSpanId();
        
        $span = [
            'span_id' => $spanId,
            'trace_id' => $this->traceId,
            'name' => $name,
            'start_time' => microtime(true),
            'parent_span_id' => $this->currentSpan,
            'attributes' => $attributes,
            'events' => [],
            'status' => 'ok'
        ];
        
        $this->spans[$spanId] = $span;
        $this->currentSpan = $spanId;
        
        // Log span start
        $this->logger->debug('tracing', 'span_started', [
            'span_id' => $spanId,
            'trace_id' => $this->traceId,
            'span_name' => $name,
            'parent_span_id' => $span['parent_span_id']
        ]);
        
        return $spanId;
    }
    
    /**
     * End a span
     * 
     * @param string $spanId Span ID to end
     * @param string $status Status: 'ok', 'error'
     * @param array $finalAttributes Additional attributes to add at end
     */
    public function endSpan($spanId, $status = 'ok', $finalAttributes = []) {
        if (!isset($this->spans[$spanId])) {
            return;
        }
        
        $span = &$this->spans[$spanId];
        $span['end_time'] = microtime(true);
        $span['duration_ms'] = ($span['end_time'] - $span['start_time']) * 1000;
        $span['status'] = $status;
        
        // Merge final attributes
        if (!empty($finalAttributes)) {
            $span['attributes'] = array_merge($span['attributes'], $finalAttributes);
        }
        
        // Log span completion
        $this->logger->info('tracing', 'span_completed', [
            'span_id' => $spanId,
            'trace_id' => $this->traceId,
            'span_name' => $span['name'],
            'duration_ms' => round($span['duration_ms'], 2),
            'status' => $status
        ]);
        
        // If this was current span, go back to parent
        if ($this->currentSpan === $spanId) {
            $this->currentSpan = $span['parent_span_id'];
        }
    }
    
    /**
     * Add an event to a span
     * 
     * @param string $spanId Span ID
     * @param string $name Event name
     * @param array $attributes Event attributes
     */
    public function addSpanEvent($spanId, $name, $attributes = []) {
        if (!isset($this->spans[$spanId])) {
            return;
        }
        
        $this->spans[$spanId]['events'][] = [
            'name' => $name,
            'timestamp' => microtime(true),
            'attributes' => $attributes
        ];
    }
    
    /**
     * Record an error in a span
     * 
     * @param string $spanId Span ID
     * @param string $errorMessage Error message
     * @param array $errorContext Additional error context
     */
    public function recordError($spanId, $errorMessage, $errorContext = []) {
        if (!isset($this->spans[$spanId])) {
            return;
        }
        
        $this->spans[$spanId]['error'] = [
            'message' => $errorMessage,
            'timestamp' => microtime(true),
            'context' => $errorContext
        ];
        
        $this->spans[$spanId]['status'] = 'error';
        
        // Log error
        $this->logger->error('tracing', 'span_error', [
            'span_id' => $spanId,
            'trace_id' => $this->traceId,
            'span_name' => $this->spans[$spanId]['name'],
            'error' => $errorMessage
        ]);
    }
    
    /**
     * Get trace ID for current request
     * 
     * @return string Trace ID
     */
    public function getTraceId() {
        return $this->traceId;
    }
    
    /**
     * Get current span ID
     * 
     * @return string|null Current span ID or null if no active span
     */
    public function getCurrentSpanId() {
        return $this->currentSpan;
    }
    
    /**
     * Get all spans for the trace
     * 
     * @return array All spans
     */
    public function getSpans() {
        return $this->spans;
    }
    
    /**
     * Export trace data in a format suitable for external systems
     * 
     * @return array Trace export data
     */
    public function exportTrace() {
        return [
            'trace_id' => $this->traceId,
            'spans' => array_values($this->spans),
            'span_count' => count($this->spans)
        ];
    }
    
    /**
     * Flush trace data to logger
     */
    public function flush() {
        if (empty($this->spans)) {
            return;
        }
        
        // Log complete trace
        $this->logger->info('tracing', 'trace_completed', [
            'trace_id' => $this->traceId,
            'span_count' => count($this->spans),
            'total_duration_ms' => $this->calculateTotalDuration()
        ]);
        
        // Export to file if configured
        if (isset($this->config['tracing']['export_file'])) {
            $this->exportToFile($this->config['tracing']['export_file']);
        }
    }
    
    /**
     * Calculate total duration of trace
     * 
     * @return float Duration in milliseconds
     */
    private function calculateTotalDuration() {
        if (empty($this->spans)) {
            return 0;
        }
        
        $minStart = PHP_FLOAT_MAX;
        $maxEnd = 0;
        
        foreach ($this->spans as $span) {
            $minStart = min($minStart, $span['start_time']);
            if (isset($span['end_time'])) {
                $maxEnd = max($maxEnd, $span['end_time']);
            }
        }
        
        return ($maxEnd - $minStart) * 1000;
    }
    
    /**
     * Export trace to file
     * 
     * @param string $filePath File path
     */
    private function exportToFile($filePath) {
        $traceData = $this->exportTrace();
        $line = json_encode($traceData, JSON_UNESCAPED_SLASHES) . "\n";
        
        @file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get or generate trace ID
     * 
     * @return string Trace ID
     */
    private function getOrGenerateTraceId() {
        // Check for incoming trace ID from HTTP headers
        if (isset($_SERVER['HTTP_X_TRACE_ID'])) {
            return $_SERVER['HTTP_X_TRACE_ID'];
        }
        
        // Check for B3 trace header (Zipkin style)
        if (isset($_SERVER['HTTP_X_B3_TRACEID'])) {
            return $_SERVER['HTTP_X_B3_TRACEID'];
        }
        
        // Check for W3C Trace Context
        if (isset($_SERVER['HTTP_TRACEPARENT'])) {
            // Format: version-trace_id-parent_id-flags
            $parts = explode('-', $_SERVER['HTTP_TRACEPARENT']);
            if (count($parts) >= 2) {
                return $parts[1];
            }
        }
        
        // Generate new trace ID (32 hex characters)
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Generate a span ID
     * 
     * @return string Span ID (16 hex characters)
     */
    private function generateSpanId() {
        return bin2hex(random_bytes(8));
    }
    
    /**
     * Create trace context headers for propagation
     * 
     * @return array Headers for HTTP requests
     */
    public function getContextHeaders() {
        return [
            'X-Trace-Id' => $this->traceId,
            'X-Span-Id' => $this->currentSpan ?? '',
            'traceparent' => $this->generateW3CTraceContext()
        ];
    }
    
    /**
     * Generate W3C Trace Context header
     * 
     * @return string Traceparent header value
     */
    private function generateW3CTraceContext() {
        // Format: version-trace_id-parent_id-flags
        $version = '00';
        $flags = '01'; // Sampled
        $parentId = $this->currentSpan ? str_pad($this->currentSpan, 16, '0') : str_pad('', 16, '0');
        
        return sprintf('%s-%s-%s-%s', $version, $this->traceId, $parentId, $flags);
    }
    
    /**
     * Helper: Wrap a callable with a span
     * 
     * @param string $spanName Span name
     * @param callable $callback Function to execute
     * @param array $attributes Initial span attributes
     * @return mixed Return value from callback
     */
    public function trace($spanName, callable $callback, $attributes = []) {
        $spanId = $this->startSpan($spanName, $attributes);
        
        try {
            $result = $callback();
            $this->endSpan($spanId, 'ok');
            return $result;
        } catch (Exception $e) {
            $this->recordError($spanId, $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->endSpan($spanId, 'error');
            throw $e;
        }
    }
}
