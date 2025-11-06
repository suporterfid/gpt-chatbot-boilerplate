<?php
/**
 * Metrics Collection Service
 * 
 * Collects and aggregates metrics for Prometheus exposition.
 * Tracks API requests, latency, errors, agent performance, and usage.
 */

class MetricsCollector {
    private static $instance = null;
    private $metrics = [];
    private $storagePath;
    
    const METRIC_TYPE_COUNTER = 'counter';
    const METRIC_TYPE_GAUGE = 'gauge';
    const METRIC_TYPE_HISTOGRAM = 'histogram';
    const METRIC_TYPE_SUMMARY = 'summary';
    
    private function __construct(array $config = []) {
        $this->storagePath = $config['storage_path'] ?? sys_get_temp_dir() . '/chatbot_metrics';
        
        // Create storage directory if needed
        if (!file_exists($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(array $config = []): self {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    /**
     * Increment a counter metric
     */
    public function incrementCounter(string $name, array $labels = [], float $value = 1.0): void {
        $key = $this->getMetricKey($name, $labels);
        $this->updateMetric($key, [
            'type' => self::METRIC_TYPE_COUNTER,
            'name' => $name,
            'labels' => $labels,
            'value' => $value,
            'operation' => 'increment',
        ]);
    }
    
    /**
     * Set a gauge metric
     */
    public function setGauge(string $name, float $value, array $labels = []): void {
        $key = $this->getMetricKey($name, $labels);
        $this->updateMetric($key, [
            'type' => self::METRIC_TYPE_GAUGE,
            'name' => $name,
            'labels' => $labels,
            'value' => $value,
            'operation' => 'set',
        ]);
    }
    
    /**
     * Observe a histogram value (for latency/duration tracking)
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void {
        $key = $this->getMetricKey($name, $labels);
        $this->updateMetric($key, [
            'type' => self::METRIC_TYPE_HISTOGRAM,
            'name' => $name,
            'labels' => $labels,
            'value' => $value,
            'operation' => 'observe',
        ]);
    }
    
    /**
     * Track API request
     */
    public function trackApiRequest(string $endpoint, string $method, float $duration, int $statusCode, array $extraLabels = []): void {
        $labels = array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
            'status' => (string)$statusCode,
        ], $extraLabels);
        
        // Increment request counter
        $this->incrementCounter('chatbot_api_requests_total', $labels);
        
        // Track duration
        $this->observeHistogram('chatbot_api_request_duration_seconds', $duration, array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
        ], $extraLabels));
        
        // Track errors
        if ($statusCode >= 400) {
            $this->incrementCounter('chatbot_api_errors_total', $labels);
        }
    }
    
    /**
     * Track OpenAI API call
     */
    public function trackOpenAICall(string $apiType, string $model, float $duration, bool $success, array $extraLabels = []): void {
        $labels = array_merge([
            'api_type' => $apiType,
            'model' => $model,
            'status' => $success ? 'success' : 'failure',
        ], $extraLabels);
        
        // Increment call counter
        $this->incrementCounter('chatbot_openai_requests_total', $labels);
        
        // Track duration
        $this->observeHistogram('chatbot_openai_request_duration_seconds', $duration, array_merge([
            'api_type' => $apiType,
            'model' => $model,
        ], $extraLabels));
        
        // Track failures
        if (!$success) {
            $this->incrementCounter('chatbot_openai_errors_total', array_merge([
                'api_type' => $apiType,
                'model' => $model,
            ], $extraLabels));
        }
    }
    
    /**
     * Track agent usage
     */
    public function trackAgentUsage(string $agentId, string $agentName, float $duration): void {
        $labels = [
            'agent_id' => $agentId,
            'agent_name' => $agentName,
        ];
        
        $this->incrementCounter('chatbot_agent_requests_total', $labels);
        $this->observeHistogram('chatbot_agent_request_duration_seconds', $duration, $labels);
    }
    
    /**
     * Track token usage for billing
     */
    public function trackTokenUsage(int $promptTokens, int $completionTokens, string $model, array $extraLabels = []): void {
        $labels = array_merge(['model' => $model], $extraLabels);
        
        $this->incrementCounter('chatbot_tokens_prompt_total', $labels, $promptTokens);
        $this->incrementCounter('chatbot_tokens_completion_total', $labels, $completionTokens);
        $this->incrementCounter('chatbot_tokens_total', $labels, $promptTokens + $completionTokens);
    }
    
    /**
     * Track rate limit hit
     */
    public function trackRateLimitHit(string $endpoint, string $clientIp): void {
        $this->incrementCounter('chatbot_rate_limit_hits_total', [
            'endpoint' => $endpoint,
            'client_ip' => $this->hashIp($clientIp),
        ]);
    }
    
    /**
     * Track conversation metrics
     */
    public function trackConversationMetrics(int $messageCount, float $conversationDuration): void {
        $this->observeHistogram('chatbot_conversation_messages', $messageCount);
        $this->observeHistogram('chatbot_conversation_duration_seconds', $conversationDuration);
    }
    
    /**
     * Track file upload
     */
    public function trackFileUpload(string $fileType, int $fileSize, bool $success): void {
        $labels = [
            'file_type' => $fileType,
            'status' => $success ? 'success' : 'failure',
        ];
        
        $this->incrementCounter('chatbot_file_uploads_total', $labels);
        $this->observeHistogram('chatbot_file_upload_size_bytes', $fileSize, ['file_type' => $fileType]);
    }
    
    /**
     * Get metric key for storage
     */
    private function getMetricKey(string $name, array $labels): string {
        ksort($labels);
        $labelString = json_encode($labels);
        return md5($name . $labelString);
    }
    
    /**
     * Update metric in storage
     */
    private function updateMetric(string $key, array $data): void {
        $file = $this->storagePath . '/' . $key . '.json';
        
        // Use file locking for atomic updates
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return;
        }
        
        if (flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $existing = $content ? json_decode($content, true) : null;
            
            if ($existing) {
                // Update existing metric
                switch ($data['operation']) {
                    case 'increment':
                        $data['value'] = ($existing['value'] ?? 0) + $data['value'];
                        break;
                    case 'observe':
                        // For histograms, store samples
                        if (!isset($existing['samples'])) {
                            $existing['samples'] = [];
                        }
                        $existing['samples'][] = [
                            'value' => $data['value'],
                            'timestamp' => time(),
                        ];
                        // Keep only last 1000 samples
                        if (count($existing['samples']) > 1000) {
                            $existing['samples'] = array_slice($existing['samples'], -1000);
                        }
                        $data['samples'] = $existing['samples'];
                        $data['value'] = array_sum(array_column($existing['samples'], 'value')) / count($existing['samples']);
                        break;
                    case 'set':
                        // Just use new value
                        break;
                }
            }
            
            $data['updated_at'] = time();
            
            // Write updated data
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            flock($fp, LOCK_UN);
        }
        
        fclose($fp);
    }
    
    /**
     * Get all metrics for exposition
     */
    public function getMetrics(): array {
        $metrics = [];
        
        if (!is_dir($this->storagePath)) {
            return $metrics;
        }
        
        $files = glob($this->storagePath . '/*.json');
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = json_decode($content, true);
                if ($data) {
                    $metrics[] = $data;
                }
            }
        }
        
        return $metrics;
    }
    
    /**
     * Hash IP for privacy
     */
    private function hashIp(string $ip): string {
        return substr(md5($ip), 0, 8);
    }
    
    /**
     * Clear all metrics (for testing)
     */
    public function clearMetrics(): void {
        if (is_dir($this->storagePath)) {
            $files = glob($this->storagePath . '/*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
}
