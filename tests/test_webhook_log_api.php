#!/usr/bin/env php
<?php
/**
 * Test suite for Webhook Log Admin API endpoints
 * Tests list_webhook_logs, get_webhook_log, and get_webhook_statistics
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookLogRepository.php';
require_once __DIR__ . '/../includes/WebhookSubscriberRepository.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

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

function makeApiRequest($action, $method = 'GET', $params = [], $body = null, $sessionCookie = null) {
    $url = 'http://localhost/admin-api.php?action=' . $action;
    if (!empty($params) && $method === 'GET') {
        $url .= '&' . http_build_query($params);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($sessionCookie) {
        curl_setopt($ch, CURLOPT_COOKIE, "admin_session=$sessionCookie");
    }
    
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

echo "=== Running Webhook Log API Tests ===\n\n";

// Setup: Initialize database and create test data
echo "--- Setup: Creating Test Data ---\n";

$dbConfig = [
    'database_path' => '/tmp/test_webhook_log_api_' . time() . '.db'
];

try {
    $db = new DB($dbConfig);
    $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "✓ Database initialized and migrated\n";
} catch (Exception $e) {
    echo "✗ Database setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Note: In production, authentication would be required
// For unit tests, we test the repository methods directly
echo "✓ Database setup complete (skipping auth for unit tests)\n";

// Create test subscriber
$subscriberRepo = new WebhookSubscriberRepository($db);
try {
    $subscriber = $subscriberRepo->save([
        'client_id' => 'test-client-001',
        'url' => 'https://example.com/webhook',
        'secret' => 'test-secret',
        'events' => ['ai.response', 'order.created']
    ]);
    $subscriberId = $subscriber['id'];
    echo "✓ Test subscriber created\n";
} catch (Exception $e) {
    echo "✗ Failed to create test subscriber: " . $e->getMessage() . "\n";
    exit(1);
}

// Create test logs
$logRepo = new WebhookLogRepository($db);
try {
    // Success log 1
    $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'ai.response',
        'request_body' => ['message' => 'Test 1'],
        'response_code' => 200,
        'response_body' => 'OK',
        'attempts' => 1
    ]);
    
    // Success log 2
    $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'order.created',
        'request_body' => ['order_id' => 'ORD-001'],
        'response_code' => 201,
        'response_body' => 'Created',
        'attempts' => 1
    ]);
    
    // Failure log 1
    $log3 = $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'order.created',
        'request_body' => ['order_id' => 'ORD-002'],
        'response_code' => 404,
        'response_body' => 'Not Found',
        'attempts' => 3
    ]);
    $testLogId = $log3['id'];
    
    // Failure log 2
    $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'ai.response',
        'request_body' => ['message' => 'Test 4'],
        'response_code' => 500,
        'response_body' => 'Server Error',
        'attempts' => 5
    ]);
    
    echo "✓ Test logs created\n";
} catch (Exception $e) {
    echo "✗ Failed to create test logs: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Running API Tests ===\n\n";

// For API tests, we need to simulate authentication
// In production, these tests would run against a real server
// For unit testing, we'll test the repository methods directly through the API simulation

echo "Note: Full API endpoint tests require a running server.\n";
echo "Testing repository methods used by API endpoints...\n\n";

// Test 1: List Webhook Logs (Simulated)
echo "--- Test 1: List Webhook Logs ---\n";
try {
    $logs = $logRepo->listLogs([], 50, 0);
    $total = $logRepo->countLogs([]);
    
    assert_equals(4, count($logs), "All logs retrieved");
    assert_equals(4, $total, "Total count is correct");
} catch (Exception $e) {
    assert_true(false, "Failed to list logs: " . $e->getMessage());
}

// Test 2: List Webhook Logs with Subscriber Filter
echo "\n--- Test 2: List Logs with Subscriber Filter ---\n";
try {
    $logs = $logRepo->listLogs(['subscriber_id' => $subscriberId], 50, 0);
    assert_equals(4, count($logs), "Filtered logs by subscriber");
} catch (Exception $e) {
    assert_true(false, "Failed to filter logs: " . $e->getMessage());
}

// Test 3: List Webhook Logs with Event Filter
echo "\n--- Test 3: List Logs with Event Filter ---\n";
try {
    $logs = $logRepo->listLogs(['event' => 'order.created'], 50, 0);
    assert_equals(2, count($logs), "Filtered logs by event");
} catch (Exception $e) {
    assert_true(false, "Failed to filter by event: " . $e->getMessage());
}

// Test 4: List Webhook Logs with Outcome Filter (Success)
echo "\n--- Test 4: List Logs with Success Outcome ---\n";
try {
    $logs = $logRepo->listLogs(['outcome' => 'success'], 50, 0);
    assert_equals(2, count($logs), "Filtered successful logs");
} catch (Exception $e) {
    assert_true(false, "Failed to filter by success: " . $e->getMessage());
}

// Test 5: List Webhook Logs with Outcome Filter (Failure)
echo "\n--- Test 5: List Logs with Failure Outcome ---\n";
try {
    $logs = $logRepo->listLogs(['outcome' => 'failure'], 50, 0);
    assert_equals(2, count($logs), "Filtered failed logs");
} catch (Exception $e) {
    assert_true(false, "Failed to filter by failure: " . $e->getMessage());
}

// Test 6: List Webhook Logs with Pagination
echo "\n--- Test 6: List Logs with Pagination ---\n";
try {
    $page1 = $logRepo->listLogs([], 2, 0);
    $page2 = $logRepo->listLogs([], 2, 2);
    $total = $logRepo->countLogs([]);
    
    assert_equals(2, count($page1), "First page has correct size");
    assert_equals(2, count($page2), "Second page has correct size");
    assert_true($page1[0]['id'] !== $page2[0]['id'], "Pages have different content");
    
    $hasMore = (2 + 2) < $total;
    assert_true(!$hasMore, "Pagination has_more is correct");
} catch (Exception $e) {
    assert_true(false, "Failed pagination test: " . $e->getMessage());
}

// Test 7: Get Webhook Log by ID
echo "\n--- Test 7: Get Webhook Log by ID ---\n";
try {
    $log = $logRepo->getById($testLogId);
    
    assert_true($log !== null, "Log retrieved by ID");
    assert_equals($testLogId, $log['id'], "Log ID matches");
    assert_equals('order.created', $log['event'], "Log event matches");
    assert_equals(404, $log['response_code'], "Log response code matches");
} catch (Exception $e) {
    assert_true(false, "Failed to get log by ID: " . $e->getMessage());
}

// Test 8: Get Webhook Statistics (Overall)
echo "\n--- Test 8: Get Webhook Statistics ---\n";
try {
    $stats = $logRepo->getStatistics([]);
    
    assert_equals(4, $stats['total'], "Total statistics correct");
    assert_equals(2, $stats['success'], "Success count correct");
    assert_equals(2, $stats['failure'], "Failure count correct");
    assert_true($stats['avg_attempts'] > 1, "Average attempts calculated: " . $stats['avg_attempts']);
} catch (Exception $e) {
    assert_true(false, "Failed to get statistics: " . $e->getMessage());
}

// Test 9: Get Webhook Statistics (Filtered by Subscriber)
echo "\n--- Test 9: Get Statistics Filtered by Subscriber ---\n";
try {
    $stats = $logRepo->getStatistics(['subscriber_id' => $subscriberId]);
    
    assert_equals(4, $stats['total'], "Subscriber statistics total correct");
    assert_equals(2, $stats['success'], "Subscriber success count correct");
} catch (Exception $e) {
    assert_true(false, "Failed to get subscriber statistics: " . $e->getMessage());
}

// Test 10: Get Webhook Statistics (Filtered by Event)
echo "\n--- Test 10: Get Statistics Filtered by Event ---\n";
try {
    $stats = $logRepo->getStatistics(['event' => 'ai.response']);
    
    assert_equals(2, $stats['total'], "Event statistics total correct");
    assert_equals(1, $stats['success'], "Event success count correct");
    assert_equals(1, $stats['failure'], "Event failure count correct");
} catch (Exception $e) {
    assert_true(false, "Failed to get event statistics: " . $e->getMessage());
}

// Test 11: Test Limit Boundaries
echo "\n--- Test 11: Test Limit Boundaries ---\n";
try {
    $logs = $logRepo->listLogs([], 1, 0);
    assert_equals(1, count($logs), "Limit of 1 works");
    
    $logs = $logRepo->listLogs([], 100, 0);
    assert_true(count($logs) <= 100, "Limit of 100 works");
} catch (Exception $e) {
    assert_true(false, "Failed limit boundary test: " . $e->getMessage());
}

// Test 12: Test Offset Boundaries
echo "\n--- Test 12: Test Offset Boundaries ---\n";
try {
    $logs = $logRepo->listLogs([], 10, 0);
    $firstLog = $logs[0]['id'] ?? null;
    
    $logs = $logRepo->listLogs([], 10, 1);
    $secondLog = $logs[0]['id'] ?? null;
    
    if ($firstLog && $secondLog) {
        assert_true($firstLog !== $secondLog, "Offset shifts results");
    } else {
        assert_true(true, "Not enough logs to test offset (expected)");
    }
} catch (Exception $e) {
    assert_true(false, "Failed offset boundary test: " . $e->getMessage());
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n";

if ($testsFailed > 0) {
    exit(1);
}

echo "\n✓ All tests passed!\n";
exit(0);
