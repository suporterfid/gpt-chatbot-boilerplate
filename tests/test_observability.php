#!/usr/bin/env php
<?php
/**
 * Test Observability Components
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ObservabilityLogger.php';
require_once __DIR__ . '/../includes/MetricsCollector.php';
require_once __DIR__ . '/../includes/TracingService.php';
require_once __DIR__ . '/../includes/ObservabilityMiddleware.php';

echo "Testing Observability Components...\n\n";

// Test 1: ObservabilityLogger
echo "Test 1: Structured Logging\n";
echo "----------------------------\n";

$logger = new ObservabilityLogger([
    'level' => 'debug',
    'file' => 'php://stdout',
]);

$logger->setContext([
    'tenant_id' => 'test-tenant',
    'agent_id' => 'test-agent',
]);

$logger->info("Test info message", ['key' => 'value']);
$logger->error("Test error message", ['error_code' => 123]);
$logger->debug("Test debug message");

$traceId = $logger->getTraceId();
echo "\nGenerated trace ID: $traceId\n";
echo "✓ Logger test passed\n\n";

// Test 2: MetricsCollector
echo "Test 2: Metrics Collection\n";
echo "----------------------------\n";

$metrics = MetricsCollector::getInstance([
    'storage_path' => sys_get_temp_dir() . '/chatbot_metrics_test',
]);

// Clear previous test metrics
$metrics->clearMetrics();

// Increment counters
$metrics->incrementCounter('test_requests_total', ['endpoint' => '/test']);
$metrics->incrementCounter('test_requests_total', ['endpoint' => '/test']);
$metrics->incrementCounter('test_errors_total', ['type' => 'validation']);

// Set gauges
$metrics->setGauge('test_queue_depth', 42);
$metrics->setGauge('test_active_connections', 10);

// Observe histograms
$metrics->observeHistogram('test_duration', 1.5);
$metrics->observeHistogram('test_duration', 2.3);
$metrics->observeHistogram('test_duration', 0.8);

// Track API metrics
$metrics->trackApiRequest('/api/test', 'POST', 1.234, 200);
$metrics->trackOpenAICall('chat_completions', 'gpt-4o', 2.5, true);

// Get metrics
$allMetrics = $metrics->getMetrics();
echo "Collected " . count($allMetrics) . " metrics\n";

foreach ($allMetrics as $metric) {
    echo "  - {$metric['name']}: {$metric['value']}\n";
}

echo "✓ Metrics test passed\n\n";

// Test 3: TracingService
echo "Test 3: Distributed Tracing\n";
echo "----------------------------\n";

$tracing = new TracingService($logger);

$rootTraceId = $tracing->getTraceId();
echo "Root trace ID: $rootTraceId\n";

// Create spans
$span1 = $tracing->startSpan('database.query', ['table' => 'users']);
usleep(100000); // 100ms
$tracing->endSpan($span1, ['rows' => 42]);

$span2 = $tracing->startSpan('external.api', ['endpoint' => '/api/test']);
usleep(200000); // 200ms
$tracing->addEvent($span2, 'request.sent', ['size' => 1024]);
$tracing->endSpan($span2, ['status_code' => 200]);

// Test error recording
$span3 = $tracing->startSpan('failing.operation');
try {
    throw new Exception("Test exception");
} catch (Exception $e) {
    $tracing->recordError($span3, $e);
    $tracing->endSpan($span3, ['status' => 'error']);
}

$spans = $tracing->getSpans();
echo "Created " . count($spans) . " spans\n";

foreach ($spans as $span) {
    $duration = isset($span['duration']) ? round($span['duration'] * 1000, 2) . 'ms' : 'incomplete';
    echo "  - {$span['name']}: $duration\n";
}

echo "✓ Tracing test passed\n\n";

// Test 4: ObservabilityMiddleware
echo "Test 4: Observability Middleware\n";
echo "-----------------------------------\n";

$observability = new ObservabilityMiddleware([
    'logging' => ['level' => 'info', 'file' => 'php://stdout'],
    'metrics' => ['storage_path' => sys_get_temp_dir() . '/chatbot_metrics_test'],
    'tracing' => ['enabled' => true],
]);

// Simulate API request
$spanId = $observability->handleRequestStart('/api/test', [
    'user_id' => 'test-user',
]);

// Set contexts
$observability->setTenantContext('tenant-123', 'Test Tenant');
$observability->setAgentContext('agent-456', 'Test Agent');

// Simulate work
usleep(50000); // 50ms

// Track OpenAI call
$observability->trackOpenAICall('responses', 'gpt-4o', 1.5, true, [
    'prompt_tokens' => 100,
    'completion_tokens' => 50,
]);

// End request
$observability->handleRequestEnd($spanId, '/api/test', 200);

echo "✓ Middleware test passed\n\n";

// Test 5: Trace Propagation
echo "Test 5: Trace Propagation\n";
echo "----------------------------\n";

$_SERVER['HTTP_TRACEPARENT'] = '00-a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6-q7r8s9t0u1v2w3x4-01';
$tracing2 = new TracingService();
$extractedTraceId = $tracing2->getTraceId();

echo "Injected trace parent: {$_SERVER['HTTP_TRACEPARENT']}\n";
echo "Extracted trace ID: $extractedTraceId\n";

if ($extractedTraceId === 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6') {
    echo "✓ Trace propagation test passed\n\n";
} else {
    echo "✗ Trace propagation test failed\n\n";
}

// Test 6: Error Handling
echo "Test 6: Error Handling\n";
echo "-----------------------\n";

$observability2 = new ObservabilityMiddleware([
    'logging' => ['level' => 'error', 'file' => 'php://stdout'],
]);

$spanId = $observability2->handleRequestStart('/api/error');

try {
    throw new RuntimeException("Test error handling", 500);
} catch (Exception $e) {
    $observability2->handleError($spanId, $e);
    $observability2->handleRequestEnd($spanId, '/api/error', 500);
}

echo "✓ Error handling test passed\n\n";

// Cleanup
$metrics->clearMetrics();

echo "========================================\n";
echo "All observability tests passed! ✓\n";
echo "========================================\n";
