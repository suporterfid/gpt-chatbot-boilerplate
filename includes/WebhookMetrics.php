<?php
/**
 * Webhook Metrics Service
 * 
 * Collects and exposes metrics for webhook delivery monitoring.
 * Supports Prometheus-compatible metrics format.
 * 
 * Reference: docs/SPEC_WEBHOOK.md §10 - Extensibility
 * Task: wh-008c
 * 
 * Metrics tracked:
 * - webhook_deliveries_total: Counter by event_type and status
 * - webhook_delivery_duration_seconds: Histogram of delivery times
 * - webhook_retry_count: Counter by attempt_number
 * - webhook_queue_depth: Gauge of pending jobs
 */

require_once __DIR__ . '/DB.php';

class WebhookMetrics {
    private $db;
    private $metricsTable = 'webhook_metrics';
    
    /**
     * @param DB $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
        $this->initializeMetricsTable();
    }
    
    /**
     * Initialize metrics table if it doesn't exist
     */
    private function initializeMetricsTable() {
        // Create metrics table for aggregated statistics
        $sql = "CREATE TABLE IF NOT EXISTS {$this->metricsTable} (
            id TEXT PRIMARY KEY,
            metric_name TEXT NOT NULL,
            metric_type TEXT NOT NULL,
            labels TEXT NOT NULL,
            value REAL NOT NULL,
            timestamp INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";
        
        try {
            $this->db->execute($sql);
            
            // Create indexes for efficient queries
            $this->db->execute("CREATE INDEX IF NOT EXISTS idx_webhook_metrics_name 
                            ON {$this->metricsTable}(metric_name)");
            $this->db->execute("CREATE INDEX IF NOT EXISTS idx_webhook_metrics_timestamp 
                            ON {$this->metricsTable}(timestamp)");
        } catch (Exception $e) {
            error_log("Failed to initialize webhook metrics table: " . $e->getMessage());
        }
    }
    
    /**
     * Record a webhook delivery metric
     * 
     * @param string $eventType Event type
     * @param string $status Status (success, failed, pending)
     * @param float $duration Duration in seconds
     * @param int $attemptNumber Attempt number (1-6)
     */
    public function recordDelivery($eventType, $status, $duration = null, $attemptNumber = 1) {
        // Increment delivery counter
        $this->incrementCounter('webhook_deliveries_total', [
            'event_type' => $eventType,
            'status' => $status
        ]);
        
        // Record duration if provided
        if ($duration !== null) {
            $this->observeHistogram('webhook_delivery_duration_seconds', $duration, [
                'event_type' => $eventType
            ]);
        }
        
        // Record retry if not first attempt
        if ($attemptNumber > 1) {
            $this->incrementCounter('webhook_retry_count', [
                'attempt_number' => $attemptNumber
            ]);
        }
    }
    
    /**
     * Update queue depth gauge
     * 
     * @param int $depth Number of pending jobs
     */
    public function updateQueueDepth($depth) {
        $this->setGauge('webhook_queue_depth', $depth, []);
    }
    
    /**
     * Increment a counter metric
     * 
     * @param string $name Metric name
     * @param array $labels Label key-value pairs
     * @param float $value Increment value (default: 1)
     */
    public function incrementCounter($name, $labels = [], $value = 1.0) {
        $labelsJson = json_encode($labels);
        $timestamp = time();
        
        // Check if metric exists
        $existingSql = "SELECT id, value FROM {$this->metricsTable} 
                       WHERE metric_name = ? AND labels = ? 
                       ORDER BY timestamp DESC LIMIT 1";
        $existing = $this->db->query($existingSql, [$name, $labelsJson]);
        
        if (!empty($existing)) {
            // Increment existing counter
            $newValue = $existing[0]['value'] + $value;
            $updateSql = "UPDATE {$this->metricsTable} 
                         SET value = ?, timestamp = ? 
                         WHERE id = ?";
            $this->db->query($updateSql, [$newValue, $timestamp, $existing[0]['id']]);
        } else {
            // Create new counter
            $id = $this->generateId();
            $insertSql = "INSERT INTO {$this->metricsTable} 
                         (id, metric_name, metric_type, labels, value, timestamp) 
                         VALUES (?, ?, 'counter', ?, ?, ?)";
            $this->db->query($insertSql, [$id, $name, $labelsJson, $value, $timestamp]);
        }
    }
    
    /**
     * Observe a histogram metric (stores individual observations)
     * 
     * @param string $name Metric name
     * @param float $value Observed value
     * @param array $labels Label key-value pairs
     */
    public function observeHistogram($name, $value, $labels = []) {
        $id = $this->generateId();
        $labelsJson = json_encode($labels);
        $timestamp = time();
        
        $sql = "INSERT INTO {$this->metricsTable} 
               (id, metric_name, metric_type, labels, value, timestamp) 
               VALUES (?, ?, 'histogram', ?, ?, ?)";
        $this->db->query($sql, [$id, $name, $labelsJson, $value, $timestamp]);
    }
    
    /**
     * Set a gauge metric
     * 
     * @param string $name Metric name
     * @param float $value Gauge value
     * @param array $labels Label key-value pairs
     */
    public function setGauge($name, $value, $labels = []) {
        $labelsJson = json_encode($labels);
        $timestamp = time();
        
        // Check if gauge exists
        $existingSql = "SELECT id FROM {$this->metricsTable} 
                       WHERE metric_name = ? AND labels = ? 
                       ORDER BY timestamp DESC LIMIT 1";
        $existing = $this->db->query($existingSql, [$name, $labelsJson]);
        
        if (!empty($existing)) {
            // Update existing gauge
            $updateSql = "UPDATE {$this->metricsTable} 
                         SET value = ?, timestamp = ? 
                         WHERE id = ?";
            $this->db->query($updateSql, [$value, $timestamp, $existing[0]['id']]);
        } else {
            // Create new gauge
            $id = $this->generateId();
            $insertSql = "INSERT INTO {$this->metricsTable} 
                         (id, metric_name, metric_type, labels, value, timestamp) 
                         VALUES (?, ?, 'gauge', ?, ?, ?)";
            $this->db->query($insertSql, [$id, $name, $labelsJson, $value, $timestamp]);
        }
    }
    
    /**
     * Get metrics in Prometheus format
     * 
     * @param int $since Unix timestamp to filter metrics (default: last 24 hours)
     * @return string Prometheus-formatted metrics
     */
    public function getPrometheusMetrics($since = null) {
        if ($since === null) {
            $since = time() - 86400; // Last 24 hours
        }
        
        $sql = "SELECT metric_name, metric_type, labels, value, timestamp 
               FROM {$this->metricsTable} 
               WHERE timestamp >= ? 
               ORDER BY metric_name, timestamp";
        $metrics = $this->db->query($sql, [$since]);
        
        $output = [];
        $currentMetric = null;
        
        foreach ($metrics as $metric) {
            $name = $metric['metric_name'];
            $type = $metric['metric_type'];
            $labels = json_decode($metric['labels'], true);
            $value = $metric['value'];
            
            // Add type hint if starting new metric
            if ($name !== $currentMetric) {
                $output[] = "# TYPE {$name} {$type}";
                $currentMetric = $name;
            }
            
            // Format labels
            $labelStr = '';
            if (!empty($labels)) {
                $labelPairs = [];
                foreach ($labels as $key => $val) {
                    $labelPairs[] = $key . '="' . addslashes($val) . '"';
                }
                $labelStr = '{' . implode(',', $labelPairs) . '}';
            }
            
            $output[] = "{$name}{$labelStr} {$value}";
        }
        
        return implode("\n", $output) . "\n";
    }
    
    /**
     * Get aggregated statistics for dashboard
     * 
     * @param int $since Unix timestamp to filter metrics (default: last 24 hours)
     * @return array Statistics summary
     */
    public function getStatistics($since = null) {
        if ($since === null) {
            $since = time() - 86400; // Last 24 hours
        }
        
        $stats = [
            'deliveries' => $this->getDeliveryStats($since),
            'latency' => $this->getLatencyStats($since),
            'retries' => $this->getRetryStats($since),
            'queue_depth' => $this->getCurrentQueueDepth()
        ];
        
        return $stats;
    }
    
    /**
     * Get delivery statistics
     */
    private function getDeliveryStats($since) {
        $sql = "SELECT labels, SUM(value) as total 
               FROM {$this->metricsTable} 
               WHERE metric_name = 'webhook_deliveries_total' 
               AND timestamp >= ? 
               GROUP BY labels";
        $results = $this->db->query($sql, [$since]);
        
        $stats = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'by_event_type' => []
        ];
        
        foreach ($results as $row) {
            $labels = json_decode($row['labels'], true);
            $total = (int)$row['total'];
            $stats['total'] += $total;
            
            if (isset($labels['status'])) {
                if ($labels['status'] === 'success') {
                    $stats['success'] += $total;
                } elseif ($labels['status'] === 'failed') {
                    $stats['failed'] += $total;
                }
            }
            
            if (isset($labels['event_type'])) {
                $eventType = $labels['event_type'];
                if (!isset($stats['by_event_type'][$eventType])) {
                    $stats['by_event_type'][$eventType] = 0;
                }
                $stats['by_event_type'][$eventType] += $total;
            }
        }
        
        $stats['success_rate'] = $stats['total'] > 0 
            ? round(($stats['success'] / $stats['total']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Get latency statistics
     */
    private function getLatencyStats($since) {
        $sql = "SELECT value 
               FROM {$this->metricsTable} 
               WHERE metric_name = 'webhook_delivery_duration_seconds' 
               AND timestamp >= ? 
               ORDER BY value";
        $results = $this->db->query($sql, [$since]);
        
        if (empty($results)) {
            return [
                'avg' => 0,
                'p50' => 0,
                'p95' => 0,
                'p99' => 0,
                'max' => 0
            ];
        }
        
        $values = array_column($results, 'value');
        $count = count($values);
        
        return [
            'avg' => round(array_sum($values) / $count, 3),
            'p50' => $this->percentile($values, 50),
            'p95' => $this->percentile($values, 95),
            'p99' => $this->percentile($values, 99),
            'max' => max($values)
        ];
    }
    
    /**
     * Get retry statistics
     */
    private function getRetryStats($since) {
        $sql = "SELECT labels, SUM(value) as total 
               FROM {$this->metricsTable} 
               WHERE metric_name = 'webhook_retry_count' 
               AND timestamp >= ? 
               GROUP BY labels";
        $results = $this->db->query($sql, [$since]);
        
        $stats = [
            'total_retries' => 0,
            'by_attempt' => []
        ];
        
        foreach ($results as $row) {
            $labels = json_decode($row['labels'], true);
            $total = (int)$row['total'];
            $stats['total_retries'] += $total;
            
            if (isset($labels['attempt_number'])) {
                $attempt = $labels['attempt_number'];
                $stats['by_attempt'][$attempt] = $total;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get current queue depth
     */
    private function getCurrentQueueDepth() {
        $sql = "SELECT value 
               FROM {$this->metricsTable} 
               WHERE metric_name = 'webhook_queue_depth' 
               ORDER BY timestamp DESC 
               LIMIT 1";
        $result = $this->db->query($sql);
        
        return !empty($result) ? (int)$result[0]['value'] : 0;
    }
    
    /**
     * Calculate percentile from sorted array
     */
    private function percentile($values, $percentile) {
        $count = count($values);
        $index = ($percentile / 100) * ($count - 1);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower === $upper) {
            return $values[$lower];
        }
        
        $fraction = $index - $lower;
        return $values[$lower] + ($values[$upper] - $values[$lower]) * $fraction;
    }
    
    /**
     * Clean old metrics
     * 
     * @param int $retentionDays Number of days to keep metrics
     */
    public function cleanOldMetrics($retentionDays = 30) {
        $cutoff = time() - ($retentionDays * 86400);
        $sql = "DELETE FROM {$this->metricsTable} WHERE timestamp < ?";
        return $this->db->query($sql, [$cutoff]);
    }
    
    /**
     * Generate unique ID
     */
    private function generateId() {
        return bin2hex(random_bytes(16));
    }
}
