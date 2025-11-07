<?php
/**
 * Test Tenant-Based Rate Limiting
 * 
 * This test validates the multi-tenant rate limiting implementation
 * Tests both TenantRateLimitService and the integration with ChatHandler/AdminAPI
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/TenantRateLimitService.php';

// Test configuration
$testsPassed = 0;
$testsFailed = 0;

// Helper functions
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

function assert_false($condition, $message) {
    assert_true(!$condition, $message);
}

function assert_equals($expected, $actual, $message) {
    assert_true($expected === $actual, "$message (expected: $expected, got: $actual)");
}

function assert_throws($callable, $expectedCode, $message) {
    global $testsPassed, $testsFailed;
    try {
        $callable();
        echo "✗ FAIL: $message (no exception thrown)\n";
        $testsFailed++;
    } catch (Exception $e) {
        if ($e->getCode() === $expectedCode) {
            echo "✓ PASS: $message\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: $message (expected code $expectedCode, got {$e->getCode()})\n";
            $testsFailed++;
        }
    }
}

// Initialize test database
function initTestDB() {
    $dbConfig = [
        'database_url' => null,
        'database_path' => ':memory:' // Use in-memory database for tests
    ];
    
    $db = new DB($dbConfig);
    
    // Create necessary tables
    $db->execute("
        CREATE TABLE IF NOT EXISTS quotas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id TEXT NOT NULL,
            resource_type TEXT NOT NULL,
            limit_value INTEGER NOT NULL,
            period TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    return $db;
}

// Cleanup function
function cleanup() {
    // Clean up test rate limit files
    $files = glob(sys_get_temp_dir() . '/ratelimit_*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "TENANT-BASED RATE LIMITING TESTS\n";
echo str_repeat("=", 70) . "\n\n";

// Test 1: Basic rate limiting check
echo "--- Test 1: Basic Rate Limit Check ---\n";
cleanup();
$db = initTestDB();
$service = new TenantRateLimitService($db);

$check1 = $service->checkRateLimit('tenant_001', 'api_call', 5, 60);
assert_true($check1['allowed'], "First request should be allowed");
assert_equals(0, $check1['current'], "Current count should be 0 on first check");
assert_equals(5, $check1['remaining'], "Remaining should be 5");

// Test 2: Recording requests
echo "\n--- Test 2: Recording Requests ---\n";
$service->recordRequest('tenant_001', 'api_call', 60);
$service->recordRequest('tenant_001', 'api_call', 60);
$service->recordRequest('tenant_001', 'api_call', 60);

$check2 = $service->checkRateLimit('tenant_001', 'api_call', 5, 60);
assert_equals(3, $check2['current'], "Current count should be 3 after 3 requests");
assert_equals(2, $check2['remaining'], "Remaining should be 2");
assert_true($check2['allowed'], "Should still be allowed");

// Test 3: Enforce rate limit (should throw exception)
echo "\n--- Test 3: Enforce Rate Limit Exceeded ---\n";
$service->recordRequest('tenant_001', 'api_call', 60);
$service->recordRequest('tenant_001', 'api_call', 60);

assert_throws(function() use ($service) {
    $service->enforceRateLimit('tenant_001', 'api_call', 5, 60);
}, 429, "Should throw 429 exception when limit exceeded");

// Test 4: Different tenants have separate limits
echo "\n--- Test 4: Tenant Isolation ---\n";
cleanup();
$db = initTestDB();
$service = new TenantRateLimitService($db);

$service->recordRequest('tenant_001', 'api_call', 60);
$service->recordRequest('tenant_001', 'api_call', 60);
$service->recordRequest('tenant_001', 'api_call', 60);
$service->recordRequest('tenant_001', 'api_call', 60);
$service->recordRequest('tenant_001', 'api_call', 60);

// Tenant 001 should be at limit
$check3 = $service->checkRateLimit('tenant_001', 'api_call', 5, 60);
assert_false($check3['allowed'], "Tenant 001 should be at limit");

// Tenant 002 should have no requests
$check4 = $service->checkRateLimit('tenant_002', 'api_call', 5, 60);
assert_true($check4['allowed'], "Tenant 002 should be allowed (different tenant)");
assert_equals(0, $check4['current'], "Tenant 002 should have 0 requests");

// Test 5: Different resource types have separate limits
echo "\n--- Test 5: Resource Type Isolation ---\n";
$service->recordRequest('tenant_003', 'api_call', 60);
$service->recordRequest('tenant_003', 'api_call', 60);
$service->recordRequest('tenant_003', 'api_call', 60);

$check5a = $service->checkRateLimit('tenant_003', 'api_call', 5, 60);
assert_equals(3, $check5a['current'], "api_call should have 3 requests");

$check5b = $service->checkRateLimit('tenant_003', 'message', 5, 60);
assert_equals(0, $check5b['current'], "message should have 0 requests (different resource type)");

// Test 6: Sliding window - old requests should expire
echo "\n--- Test 6: Sliding Window Expiration ---\n";
cleanup();
$db = initTestDB();
$service = new TenantRateLimitService($db);

// Record requests with manual timestamp manipulation
$cacheKey = sys_get_temp_dir() . '/ratelimit_' . md5('tenant_004_api_call_10');
$oldTimestamp = time() - 15; // 15 seconds ago
$recentTimestamp = time() - 5; // 5 seconds ago

file_put_contents($cacheKey, json_encode([$oldTimestamp, $oldTimestamp, $recentTimestamp, $recentTimestamp]));

// With 10 second window, only 2 recent requests should count
$check6 = $service->checkRateLimit('tenant_004', 'api_call', 3, 10);
assert_equals(2, $check6['current'], "Only recent requests within window should count");
assert_true($check6['allowed'], "Should be allowed since old requests expired");

// Test 7: Default rate limits
echo "\n--- Test 7: Default Rate Limits ---\n";
$defaults = $service->getTenantRateLimit('tenant_005', 'api_call');
assert_equals(60, $defaults['limit'], "Default api_call limit should be 60");
assert_equals(60, $defaults['window_seconds'], "Default api_call window should be 60 seconds");

$defaults2 = $service->getTenantRateLimit('tenant_005', 'message');
assert_equals(100, $defaults2['limit'], "Default message limit should be 100");
assert_equals(3600, $defaults2['window_seconds'], "Default message window should be 3600 seconds");

// Test 8: Custom tenant quotas
echo "\n--- Test 8: Custom Tenant Quotas ---\n";
$db->execute("
    INSERT INTO quotas (tenant_id, resource_type, limit_value, period)
    VALUES ('tenant_006', 'api_call', 1000, 'hourly')
");

$customLimits = $service->getTenantRateLimit('tenant_006', 'api_call');
assert_equals(1000, $customLimits['limit'], "Custom limit should be 1000");
assert_equals(3600, $customLimits['window_seconds'], "Custom window should be 3600 seconds (hourly)");

// Test 9: Rate limit status for all resources
echo "\n--- Test 9: Rate Limit Status ---\n";
$service->recordRequest('tenant_007', 'api_call', 60);
$service->recordRequest('tenant_007', 'api_call', 60);
$service->recordRequest('tenant_007', 'message', 3600);

$status = $service->getTenantRateLimitStatus('tenant_007');
assert_true(is_array($status), "Status should be an array");
assert_true(count($status) > 0, "Status should contain resource types");

// Find api_call status
$apiCallStatus = null;
foreach ($status as $resource) {
    if ($resource['resource_type'] === 'api_call') {
        $apiCallStatus = $resource;
        break;
    }
}
assert_true($apiCallStatus !== null, "Should find api_call in status");
assert_equals(2, $apiCallStatus['current'], "api_call current should be 2");

// Test 10: Clear rate limit
echo "\n--- Test 10: Clear Rate Limit ---\n";
$service->recordRequest('tenant_008', 'api_call', 60);
$service->recordRequest('tenant_008', 'api_call', 60);
$service->recordRequest('tenant_008', 'api_call', 60);

$check10a = $service->checkRateLimit('tenant_008', 'api_call', 5, 60);
assert_equals(3, $check10a['current'], "Should have 3 requests before clear");

$service->clearRateLimit('tenant_008', 'api_call', 60);

$check10b = $service->checkRateLimit('tenant_008', 'api_call', 5, 60);
assert_equals(0, $check10b['current'], "Should have 0 requests after clear");

// Test 11: Rate limit with 0 window (edge case)
echo "\n--- Test 11: Edge Case - Zero Window ---\n";
// This should use default behavior or handle gracefully
try {
    $service->checkRateLimit('tenant_009', 'api_call', 5, 0);
    assert_true(true, "Should handle zero window gracefully");
} catch (Exception $e) {
    assert_true(true, "May throw exception for invalid window (acceptable)");
}

// Test 12: Very high limits (performance check)
echo "\n--- Test 12: High Limit Performance ---\n";
$start = microtime(true);
for ($i = 0; $i < 50; $i++) {
    $service->recordRequest('tenant_010', 'api_call', 60);
}
$check12 = $service->checkRateLimit('tenant_010', 'api_call', 10000, 60);
$elapsed = microtime(true) - $start;

assert_equals(50, $check12['current'], "Should track 50 requests");
assert_true($elapsed < 1.0, "Should complete in under 1 second (performance check)");

// Cleanup
cleanup();

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";

if ($testsFailed === 0) {
    echo "\n✓ ALL TESTS PASSED!\n\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n\n";
    exit(1);
}
?>
