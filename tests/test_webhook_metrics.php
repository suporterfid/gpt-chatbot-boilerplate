#!/usr/bin/env php
<?php
/**
 * Test suite for WebhookMetrics
 * Tests metrics collection, Prometheus format, and statistics
 * 
 * Task: wh-008c
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookMetrics.php';

// Test counters
$testsPassed = 0;
$testsFailed = 0;

function assert_true($condition, $message) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message\n";
        $testsFailed++;
    }
}

function assert_equals($expected, $actual, $message) {
    global $testsPassed, $testsFailed;
    if ($expected === $actual) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n";
        $testsFailed++;
    }
}

function assert_greater_than($min, $actual, $message) {
    global $testsPassed, $testsFailed;
    if ($actual > $min) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (expected > {$min}, got: {$actual})\n";
        $testsFailed++;
    }
}

echo "=== Running Webhook Metrics Tests ===\n\n";

// Initialize test database
$dbConfig = [
    'database_path' => '/tmp/test_webhook_metrics_' . time() . '.db'
];

try {
    $db = new DB($dbConfig);
    echo "✓ Database initialized\n";
} catch (Exception $e) {
    echo "✗ Database initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Create metrics service
$metrics = new WebhookMetrics($db);
echo "✓ WebhookMetrics initialized\n";

// Test 1: Record delivery metrics
echo "\n--- Test 1: Record Delivery Metrics ---\n";

$metrics->recordDelivery('ai.response', 'success', 0.123, 1);
$metrics->recordDelivery('ai.response', 'success', 0.456, 1);
$metrics->recordDelivery('ai.response', 'failed', 1.234, 2);
$metrics->recordDelivery('order.created', 'success', 0.789, 1);

echo "✓ Delivery metrics recorded\n";

// Test 2: Increment counters
echo "\n--- Test 2: Counter Metrics ---\n";

$metrics->incrementCounter('test_counter', ['label' => 'value1']);
$metrics->incrementCounter('test_counter', ['label' => 'value1']);
$metrics->incrementCounter('test_counter', ['label' => 'value2']);

// Query to verify counter values
$sql = "SELECT labels, value FROM webhook_metrics WHERE metric_name = 'test_counter' ORDER BY labels";
$counters = $db->query($sql);

assert_equals(2, count($counters), "Two distinct counters created");
assert_equals(2.0, (float)$counters[0]['value'], "Counter1 incremented twice");
assert_equals(1.0, (float)$counters[1]['value'], "Counter2 incremented once");

// Test 3: Set gauge metrics
echo "\n--- Test 3: Gauge Metrics ---\n";

$metrics->setGauge('queue_depth', 10, []);
$metrics->setGauge('queue_depth', 20, []);
$metrics->setGauge('queue_depth', 15, []);

$sql = "SELECT value FROM webhook_metrics WHERE metric_name = 'queue_depth' ORDER BY timestamp DESC LIMIT 1";
$gauge = $db->query($sql);

assert_equals(15.0, (float)$gauge[0]['value'], "Gauge updated to latest value");

// Test 4: Observe histogram metrics
echo "\n--- Test 4: Histogram Metrics ---\n";

$metrics->observeHistogram('request_duration', 0.1, ['endpoint' => '/api']);
$metrics->observeHistogram('request_duration', 0.2, ['endpoint' => '/api']);
$metrics->observeHistogram('request_duration', 0.3, ['endpoint' => '/api']);
$metrics->observeHistogram('request_duration', 0.5, ['endpoint' => '/api']);

$sql = "SELECT COUNT(*) as count FROM webhook_metrics WHERE metric_name = 'request_duration'";
$histogramCount = $db->query($sql);

assert_equals(4, (int)$histogramCount[0]['count'], "Four histogram observations recorded");

// Test 5: Queue depth tracking
echo "\n--- Test 5: Queue Depth Update ---\n";

$metrics->updateQueueDepth(42);

$sql = "SELECT value FROM webhook_metrics WHERE metric_name = 'webhook_queue_depth' ORDER BY timestamp DESC LIMIT 1";
$queueDepth = $db->query($sql);

assert_equals(42.0, (float)$queueDepth[0]['value'], "Queue depth updated");

// Test 6: Get statistics
echo "\n--- Test 6: Get Statistics ---\n";

$stats = $metrics->getStatistics();

assert_true(isset($stats['deliveries']), "Statistics include deliveries");
assert_true(isset($stats['latency']), "Statistics include latency");
assert_true(isset($stats['retries']), "Statistics include retries");
assert_true(isset($stats['queue_depth']), "Statistics include queue_depth");

assert_equals(4, $stats['deliveries']['total'], "Total deliveries counted");
assert_equals(3, $stats['deliveries']['success'], "Successful deliveries counted");
assert_equals(1, $stats['deliveries']['failed'], "Failed deliveries counted");
assert_equals(75.0, $stats['deliveries']['success_rate'], "Success rate calculated");

assert_true(isset($stats['deliveries']['by_event_type']['ai.response']), "Deliveries grouped by event type");
assert_equals(3, $stats['deliveries']['by_event_type']['ai.response'], "ai.response count correct");
assert_equals(1, $stats['deliveries']['by_event_type']['order.created'], "order.created count correct");

// Test 7: Latency statistics
echo "\n--- Test 7: Latency Statistics ---\n";

$latency = $stats['latency'];
assert_true($latency['avg'] > 0, "Average latency calculated");
assert_true($latency['p50'] > 0, "P50 latency calculated");
assert_true($latency['p95'] > 0, "P95 latency calculated");
assert_true($latency['max'] > 0, "Max latency calculated");

// Test 8: Prometheus format
echo "\n--- Test 8: Prometheus Format ---\n";

$prometheus = $metrics->getPrometheusMetrics();

assert_true(strpos($prometheus, '# TYPE') !== false, "Prometheus output includes TYPE hints");
assert_true(strpos($prometheus, 'webhook_deliveries_total') !== false, "Includes deliveries counter");
assert_true(strpos($prometheus, 'webhook_delivery_duration_seconds') !== false, "Includes duration histogram");
assert_true(strpos($prometheus, 'webhook_retry_count') !== false, "Includes retry counter");
assert_true(strpos($prometheus, 'webhook_queue_depth') !== false, "Includes queue depth gauge");

// Verify label formatting
assert_true(strpos($prometheus, 'event_type="ai.response"') !== false, "Labels properly formatted");

echo "\n--- Test 9: Clean Old Metrics ---\n";

// Insert old metric
$oldTimestamp = time() - (40 * 86400); // 40 days ago
$sql = "INSERT INTO webhook_metrics (id, metric_name, metric_type, labels, value, timestamp) 
       VALUES (?, 'old_metric', 'counter', '{}', 1, ?)";
$db->query($sql, [bin2hex(random_bytes(16)), $oldTimestamp]);

// Count before cleanup
$sqlCount = "SELECT COUNT(*) as count FROM webhook_metrics";
$beforeCount = $db->query($sqlCount);

// Clean metrics older than 30 days
$metrics->cleanOldMetrics(30);

// Count after cleanup
$afterCount = $db->query($sqlCount);

assert_true((int)$afterCount[0]['count'] < (int)$beforeCount[0]['count'], "Old metrics cleaned up");

// Verify old metric was removed
$sqlOld = "SELECT COUNT(*) as count FROM webhook_metrics WHERE metric_name = 'old_metric'";
$oldMetricCount = $db->query($sqlOld);
assert_equals(0, (int)$oldMetricCount[0]['count'], "Old metric was removed");

// Test 10: Retry statistics
echo "\n--- Test 10: Retry Statistics ---\n";

$retryStats = $stats['retries'];
assert_true(isset($retryStats['total_retries']), "Retry statistics available");
assert_true(isset($retryStats['by_attempt']), "Retries grouped by attempt number");
assert_equals(1, $retryStats['total_retries'], "One retry recorded (from attempt 2)");

echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

// Cleanup
@unlink($dbConfig['database_path']);

exit($testsFailed > 0 ? 1 : 0);
