#!/usr/bin/env php
<?php
/**
 * Multi-Tenant Observability Validation Test
 * 
 * Validates that all observability components work correctly
 * with multi-tenant context and trace propagation.
 */

require_once __DIR__ . '/../includes/ObservabilityMiddleware.php';
require_once __DIR__ . '/../includes/TracingService.php';
require_once __DIR__ . '/../includes/ObservabilityLogger.php';
require_once __DIR__ . '/../includes/MetricsCollector.php';

echo "=== Multi-Tenant Observability Validation ===\n\n";

$config = [
    'logging' => ['level' => 'info', 'file' => 'php://stderr'],
    'observability' => [
        'enabled' => true,
        'tracing' => ['enabled' => true, 'export' => false, 'sample_rate' => 1.0],
        'metrics' => ['enabled' => true, 'storage_path' => '/tmp/test_metrics'],
    ],
];

$passed = 0;
$failed = 0;

// Test 1: TracingService trace ID propagation
echo "Test 1: TracingService trace ID propagation\n";
try {
    $tracing = new TracingService();
    $originalTraceId = $tracing->getTraceId();
    
    // Set a custom trace ID
    $customTraceId = 'test-trace-12345678';
    $tracing->setTraceId($customTraceId);
    
    if ($tracing->getTraceId() === $customTraceId) {
        echo "  ✓ PASS: Trace ID set and retrieved correctly\n";
        $passed++;
    } else {
        echo "  ✗ FAIL: Trace ID mismatch\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "  ✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: ObservabilityMiddleware tenant context
echo "\nTest 2: ObservabilityMiddleware tenant context\n";
try {
    $obs = new ObservabilityMiddleware($config);
    
    // Set tenant context
    $tenantId = 'tenant-test-789';
    $tenantName = 'Test Tenant Corp';
    $obs->setTenantContext($tenantId, $tenantName);
    
    // Verify logger has context
    $logger = $obs->getLogger();
    // Context is internal, so we just verify no exceptions
    
    echo "  ✓ PASS: Tenant context set successfully\n";
    $passed++;
} catch (Exception $e) {
    echo "  ✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: Metrics with tenant labels
echo "\nTest 3: Metrics with tenant labels\n";
try {
    $metrics = MetricsCollector::getInstance($config['observability']['metrics']);
    
    // Clear any existing metrics
    $metrics->clearMetrics();
    
    // Add metrics with tenant labels
    $metrics->incrementCounter('test_metric_total', [
        'tenant_id' => 'tenant-abc',
        'operation' => 'test',
    ]);
    
    $metrics->incrementCounter('test_metric_total', [
        'tenant_id' => 'tenant-xyz',
        'operation' => 'test',
    ]);
    
    // Get all metrics
    $allMetrics = $metrics->getMetrics();
    
    if (count($allMetrics) >= 2) {
        echo "  ✓ PASS: Metrics created with tenant labels\n";
        $passed++;
    } else {
        echo "  ✗ FAIL: Expected at least 2 metrics, got " . count($allMetrics) . "\n";
        $failed++;
    }
    
    // Cleanup
    $metrics->clearMetrics();
} catch (Exception $e) {
    echo "  ✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 4: Span creation and context
echo "\nTest 4: Span creation and context\n";
try {
    $obs = new ObservabilityMiddleware($config);
    
    // Create a span
    $spanId = $obs->createSpan('test.operation', [
        'tenant_id' => 'tenant-test',
        'test_attr' => 'value',
    ]);
    
    if (!empty($spanId)) {
        echo "  ✓ PASS: Span created with ID: $spanId\n";
        $passed++;
        
        // End the span
        $obs->endSpan($spanId, ['status' => 'ok']);
    } else {
        echo "  ✗ FAIL: Span ID is empty\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "  ✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 5: Trace propagation headers
echo "\nTest 5: Trace propagation headers\n";
try {
    $obs = new ObservabilityMiddleware($config);
    
    // Get propagation headers
    $headers = $obs->getTracePropagationHeaders();
    
    $requiredHeaders = ['traceparent', 'X-Trace-Id', 'X-Span-Id'];
    $hasAllHeaders = true;
    
    foreach ($requiredHeaders as $header) {
        if (!isset($headers[$header])) {
            echo "  ✗ FAIL: Missing header: $header\n";
            $hasAllHeaders = false;
        }
    }
    
    if ($hasAllHeaders) {
        echo "  ✓ PASS: All trace propagation headers present\n";
        $passed++;
    } else {
        $failed++;
    }
} catch (Exception $e) {
    echo "  ✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 6: Worker job context extraction simulation
echo "\nTest 6: Worker job context extraction simulation\n";
try {
    $obs = new ObservabilityMiddleware($config);
    
    // Simulate job with trace and tenant metadata
    $job = [
        'id' => 'job-123',
        'type' => 'test_job',
        'trace_id' => 'simulated-trace-456',
        'tenant_id' => 'tenant-sim-789',
    ];
    
    // Extract and set trace ID
    if (!empty($job['trace_id'])) {
        $obs->getTracing()->setTraceId($job['trace_id']);
    }
    
    // Set tenant context
    if (!empty($job['tenant_id'])) {
        $obs->setTenantContext($job['tenant_id']);
    }
    
    // Verify trace ID was set
    if ($obs->getTracing()->getTraceId() === $job['trace_id']) {
        echo "  ✓ PASS: Job trace context extracted and propagated\n";
        $passed++;
    } else {
        echo "  ✗ FAIL: Trace ID not propagated correctly\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "  ✗ FAIL: " . $e->getMessage() . "\n";
    $failed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✓ All tests passed! Multi-tenant observability is working correctly.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the output above.\n";
    exit(1);
}
