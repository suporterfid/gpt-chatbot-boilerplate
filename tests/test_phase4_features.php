<?php
/**
 * Phase 4 Feature Tests
 * Tests for rate limiting and Files API endpoints
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/OpenAIAdminClient.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

// Test helpers
function assert_true($condition, $message) {
    if (!$condition) {
        throw new Exception("Assertion failed: $message");
    }
    echo "✓ PASS: $message\n";
}

function assert_false($condition, $message) {
    if ($condition) {
        throw new Exception("Assertion failed: $message");
    }
    echo "✓ PASS: $message\n";
}

function assert_equals($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception("Assertion failed: $message (expected: $expected, got: $actual)");
    }
    echo "✓ PASS: $message\n";
}

function assert_not_null($value, $message) {
    if ($value === null) {
        throw new Exception("Assertion failed: $message");
    }
    echo "✓ PASS: $message\n";
}

// Test rate limiting function
function testRateLimit() {
    global $config;
    
    echo "\n--- Test: Rate Limiting ---\n";
    
    // Create a temporary rate limit file
    $clientIP = '127.0.0.1';
    $_SERVER['REMOTE_ADDR'] = $clientIP;
    $requestsFile = sys_get_temp_dir() . '/admin_requests_' . md5($clientIP);
    
    // Clean up any existing test files
    if (file_exists($requestsFile)) {
        unlink($requestsFile);
    }
    
    // Set a low rate limit for testing
    $testConfig = $config;
    $testConfig['admin']['rate_limit_requests'] = 3;
    $testConfig['admin']['rate_limit_window'] = 5; // 5 seconds
    
    // Simulate the rate limit check function
    function checkTestRateLimit($config) {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestsFile = sys_get_temp_dir() . '/admin_requests_' . md5($clientIP);
        
        $currentTime = time();
        $rateLimit = $config['admin']['rate_limit_requests'] ?? 300;
        $window = $config['admin']['rate_limit_window'] ?? 60;
        
        $requests = [];
        if (file_exists($requestsFile)) {
            $requests = json_decode(file_get_contents($requestsFile), true) ?: [];
        }
        
        $requests = array_filter($requests, function($timestamp) use ($currentTime, $window) {
            return $currentTime - $timestamp < $window;
        });
        
        if (count($requests) >= $rateLimit) {
            return false; // Rate limit exceeded
        }
        
        $requests[] = $currentTime;
        file_put_contents($requestsFile, json_encode($requests));
        return true; // OK
    }
    
    // Test 1: First request should succeed
    $result = checkTestRateLimit($testConfig);
    assert_true($result, "First request allowed");
    
    // Test 2: Second request should succeed
    $result = checkTestRateLimit($testConfig);
    assert_true($result, "Second request allowed");
    
    // Test 3: Third request should succeed
    $result = checkTestRateLimit($testConfig);
    assert_true($result, "Third request allowed");
    
    // Test 4: Fourth request should fail (rate limit exceeded)
    $result = checkTestRateLimit($testConfig);
    assert_false($result, "Fourth request blocked by rate limit");
    
    // Test 5: Wait for window to expire and try again
    // (We'll just clean up the file to simulate window expiration)
    unlink($requestsFile);
    $result = checkTestRateLimit($testConfig);
    assert_true($result, "Request allowed after rate limit window reset");
    
    // Cleanup
    if (file_exists($requestsFile)) {
        unlink($requestsFile);
    }
}

// Test Files API functionality (mock-based since we don't want to actually call OpenAI)
function testFilesAPILogic() {
    global $config;
    
    echo "\n--- Test: Files API Logic ---\n";
    
    // Test 1: Verify that OpenAIAdminClient has the required methods
    if (!empty($config['openai']['api_key'])) {
        $client = new OpenAIAdminClient($config['openai']);
        
        assert_true(method_exists($client, 'listFiles'), "OpenAIAdminClient has listFiles method");
        assert_true(method_exists($client, 'uploadFileFromBase64'), "OpenAIAdminClient has uploadFileFromBase64 method");
        assert_true(method_exists($client, 'deleteFile'), "OpenAIAdminClient has deleteFile method");
        assert_true(method_exists($client, 'getFile'), "OpenAIAdminClient has getFile method");
    } else {
        echo "⚠ SKIP: OpenAI API key not configured, skipping client method tests\n";
    }
    
    // Test 2: Verify config has rate limit defaults
    assert_not_null($config['admin']['rate_limit_requests'], "Admin rate limit requests configured");
    assert_not_null($config['admin']['rate_limit_window'], "Admin rate limit window configured");
    
    // Test 3: Verify rate limit defaults are appropriate
    assert_true($config['admin']['rate_limit_requests'] >= 100, "Rate limit is at least 100 req/window");
    assert_true($config['admin']['rate_limit_window'] >= 60, "Rate limit window is at least 60 seconds");
}

// Test admin-api.php has the required endpoints
function testAdminAPIEndpoints() {
    echo "\n--- Test: Admin API Endpoints ---\n";
    
    // Read admin-api.php and verify endpoints exist
    $adminApiContent = file_get_contents(__DIR__ . '/../admin-api.php');
    
    assert_true(strpos($adminApiContent, "case 'list_files':") !== false, "list_files endpoint exists");
    assert_true(strpos($adminApiContent, "case 'upload_file':") !== false, "upload_file endpoint exists");
    assert_true(strpos($adminApiContent, "case 'delete_file':") !== false, "delete_file endpoint exists");
    assert_true(strpos($adminApiContent, 'checkAdminRateLimit') !== false, "Rate limiting function exists");
    assert_true(strpos($adminApiContent, 'function checkAdminRateLimit') !== false, "checkAdminRateLimit is defined");
}

// Main test execution
try {
    echo "=== Phase 4 Feature Tests ===\n";
    
    testRateLimit();
    testFilesAPILogic();
    testAdminAPIEndpoints();
    
    echo "\n=== Test Summary ===\n";
    echo "✅ All Phase 4 tests passed!\n";
    
} catch (Exception $e) {
    echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
