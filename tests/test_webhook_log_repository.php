#!/usr/bin/env php
<?php
/**
 * Test suite for WebhookLogRepository
 * Tests all CRUD operations and query methods
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookLogRepository.php';
require_once __DIR__ . '/../includes/WebhookSubscriberRepository.php';

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

function assert_not_null($value, $message) {
    global $testsPassed, $testsFailed;
    if ($value !== null) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (value is null)\n";
        $testsFailed++;
    }
}

echo "=== Running WebhookLogRepository Tests ===\n\n";

// Initialize test database
$dbConfig = [
    'database_path' => '/tmp/test_webhook_logs_' . time() . '.db'
];

try {
    $db = new DB($dbConfig);
    echo "✓ Database initialized\n";
} catch (Exception $e) {
    echo "✗ Database initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Run migrations
echo "\n--- Running Migrations ---\n";
try {
    $count = $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "✓ Migrations executed (count: $count)\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Create test subscriber first (needed for foreign key)
echo "\n--- Setup: Creating Test Subscriber ---\n";
$subscriberRepo = new WebhookSubscriberRepository($db);
try {
    $subscriber = $subscriberRepo->save([
        'client_id' => 'test-client-001',
        'url' => 'https://example.com/webhook',
        'secret' => 'test-secret',
        'events' => ['ai.response', 'order.created']
    ]);
    $subscriberId = $subscriber['id'];
    echo "✓ Test subscriber created (ID: $subscriberId)\n";
} catch (Exception $e) {
    echo "✗ Failed to create test subscriber: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize WebhookLogRepository
$logRepo = new WebhookLogRepository($db);

// Test 1: Create Log Entry
echo "\n--- Test 1: Create Log Entry ---\n";
try {
    $logData = [
        'subscriber_id' => $subscriberId,
        'event' => 'ai.response',
        'request_body' => ['message' => 'Test webhook', 'timestamp' => time()],
        'response_code' => 200,
        'response_body' => 'OK',
        'attempts' => 1
    ];
    
    $log = $logRepo->createLog($logData);
    $logId = $log['id'];
    
    assert_not_null($logId, "Log entry created successfully");
    assert_equals($subscriberId, $log['subscriber_id'], "Subscriber ID is correct");
    assert_equals('ai.response', $log['event'], "Event type is correct");
    assert_equals(200, $log['response_code'], "Response code is correct");
    assert_equals(1, $log['attempts'], "Attempts count is correct");
} catch (Exception $e) {
    assert_true(false, "Failed to create log entry: " . $e->getMessage());
}

// Test 2: Get Log by ID
echo "\n--- Test 2: Get Log by ID ---\n";
try {
    $retrieved = $logRepo->getById($logId);
    assert_not_null($retrieved, "Log entry retrieved");
    assert_equals($logId, $retrieved['id'], "Retrieved log ID matches");
    assert_equals('ai.response', $retrieved['event'], "Event type matches");
} catch (Exception $e) {
    assert_true(false, "Failed to get log by ID: " . $e->getMessage());
}

// Test 3: Update Log Entry
echo "\n--- Test 3: Update Log Entry ---\n";
try {
    $updated = $logRepo->updateLog($logId, [
        'response_code' => 500,
        'response_body' => 'Internal Server Error',
        'attempts' => 2
    ]);
    
    assert_equals(500, $updated['response_code'], "Response code updated");
    assert_equals(2, $updated['attempts'], "Attempts count updated");
} catch (Exception $e) {
    assert_true(false, "Failed to update log entry: " . $e->getMessage());
}

// Test 4: Create Multiple Logs
echo "\n--- Test 4: Create Multiple Logs ---\n";
try {
    // Create logs with different outcomes
    $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'order.created',
        'request_body' => ['order_id' => 'ORD-001'],
        'response_code' => 200,
        'attempts' => 1
    ]);
    
    $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'order.created',
        'request_body' => ['order_id' => 'ORD-002'],
        'response_code' => 404,
        'attempts' => 3
    ]);
    
    $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'ai.response',
        'request_body' => ['message' => 'Another test'],
        'response_code' => 201,
        'attempts' => 1
    ]);
    
    assert_true(true, "Multiple log entries created");
} catch (Exception $e) {
    assert_true(false, "Failed to create multiple logs: " . $e->getMessage());
}

// Test 5: List All Logs
echo "\n--- Test 5: List All Logs ---\n";
try {
    $logs = $logRepo->listLogs();
    assert_true(count($logs) >= 4, "All logs retrieved (count: " . count($logs) . ")");
} catch (Exception $e) {
    assert_true(false, "Failed to list logs: " . $e->getMessage());
}

// Test 6: Filter by Subscriber
echo "\n--- Test 6: Filter by Subscriber ---\n";
try {
    $logs = $logRepo->listLogs(['subscriber_id' => $subscriberId]);
    assert_true(count($logs) >= 4, "Filtered logs by subscriber");
} catch (Exception $e) {
    assert_true(false, "Failed to filter by subscriber: " . $e->getMessage());
}

// Test 7: Filter by Event
echo "\n--- Test 7: Filter by Event ---\n";
try {
    $logs = $logRepo->listLogs(['event' => 'order.created']);
    assert_equals(2, count($logs), "Filtered logs by event (order.created)");
} catch (Exception $e) {
    assert_true(false, "Failed to filter by event: " . $e->getMessage());
}

// Test 8: Filter by Outcome (Success)
echo "\n--- Test 8: Filter by Outcome (Success) ---\n";
try {
    $logs = $logRepo->listLogs(['outcome' => 'success']);
    // Should have 2 successful (200, 201)
    assert_true(count($logs) >= 2, "Filtered successful logs (count: " . count($logs) . ")");
} catch (Exception $e) {
    assert_true(false, "Failed to filter by success outcome: " . $e->getMessage());
}

// Test 9: Filter by Outcome (Failure)
echo "\n--- Test 9: Filter by Outcome (Failure) ---\n";
try {
    $logs = $logRepo->listLogs(['outcome' => 'failure']);
    // Should have at least 2 failures (404, 500)
    assert_true(count($logs) >= 2, "Filtered failed logs (count: " . count($logs) . ")");
} catch (Exception $e) {
    assert_true(false, "Failed to filter by failure outcome: " . $e->getMessage());
}

// Test 10: Pagination
echo "\n--- Test 10: Pagination ---\n";
try {
    $page1 = $logRepo->listLogs([], 2, 0);
    $page2 = $logRepo->listLogs([], 2, 2);
    
    assert_equals(2, count($page1), "First page has 2 results");
    assert_equals(2, count($page2), "Second page has 2 results");
    assert_true($page1[0]['id'] !== $page2[0]['id'], "Pages contain different results");
} catch (Exception $e) {
    assert_true(false, "Failed to test pagination: " . $e->getMessage());
}

// Test 11: Count Logs
echo "\n--- Test 11: Count Logs ---\n";
try {
    $total = $logRepo->countLogs();
    assert_true($total >= 4, "Total log count is correct (count: $total)");
    
    $eventCount = $logRepo->countLogs(['event' => 'order.created']);
    assert_equals(2, $eventCount, "Event filter count is correct");
} catch (Exception $e) {
    assert_true(false, "Failed to count logs: " . $e->getMessage());
}

// Test 12: Get Logs by Subscriber
echo "\n--- Test 12: Get Logs by Subscriber ---\n";
try {
    $logs = $logRepo->getLogsBySubscriber($subscriberId);
    assert_true(count($logs) >= 4, "Retrieved logs by subscriber");
} catch (Exception $e) {
    assert_true(false, "Failed to get logs by subscriber: " . $e->getMessage());
}

// Test 13: Get Logs by Event
echo "\n--- Test 13: Get Logs by Event ---\n";
try {
    $logs = $logRepo->getLogsByEvent('ai.response');
    assert_true(count($logs) >= 2, "Retrieved logs by event");
} catch (Exception $e) {
    assert_true(false, "Failed to get logs by event: " . $e->getMessage());
}

// Test 14: Get Statistics
echo "\n--- Test 14: Get Statistics ---\n";
try {
    $stats = $logRepo->getStatistics();
    
    assert_not_null($stats, "Statistics retrieved");
    assert_true($stats['total'] >= 4, "Total count in stats: " . $stats['total']);
    assert_true($stats['success'] >= 2, "Success count in stats: " . $stats['success']);
    assert_true($stats['failure'] >= 2, "Failure count in stats: " . $stats['failure']);
    assert_true($stats['avg_attempts'] > 0, "Average attempts > 0: " . $stats['avg_attempts']);
} catch (Exception $e) {
    assert_true(false, "Failed to get statistics: " . $e->getMessage());
}

// Test 15: Get Statistics with Filter
echo "\n--- Test 15: Get Statistics with Filter ---\n";
try {
    $stats = $logRepo->getStatistics(['event' => 'order.created']);
    
    assert_equals(2, $stats['total'], "Filtered stats total");
    // One success (200), one failure (404)
    assert_equals(1, $stats['success'], "Filtered stats success");
    assert_equals(1, $stats['failure'], "Filtered stats failure");
} catch (Exception $e) {
    assert_true(false, "Failed to get filtered statistics: " . $e->getMessage());
}

// Test 16: Validation - Missing Required Fields
echo "\n--- Test 16: Validation - Missing Required Fields ---\n";
try {
    $logRepo->createLog([
        'event' => 'test.event',
        'request_body' => ['test' => 'data']
        // Missing subscriber_id
    ]);
    assert_true(false, "Should have thrown exception for missing subscriber_id");
} catch (Exception $e) {
    assert_true(str_contains($e->getMessage(), 'subscriber_id'), "Validation caught missing subscriber_id");
}

// Test 17: Validation - Invalid JSON
echo "\n--- Test 17: Validation - Invalid JSON ---\n";
try {
    $logRepo->createLog([
        'subscriber_id' => $subscriberId,
        'event' => 'test.event',
        'request_body' => 'invalid json string'
    ]);
    assert_true(false, "Should have thrown exception for invalid JSON");
} catch (Exception $e) {
    assert_true(str_contains($e->getMessage(), 'valid JSON'), "Validation caught invalid JSON");
}

// Test 18: Update Non-existent Log
echo "\n--- Test 18: Update Non-existent Log ---\n";
try {
    $logRepo->updateLog('non-existent-id', ['response_code' => 200]);
    assert_true(false, "Should have thrown exception for non-existent log");
} catch (Exception $e) {
    assert_true(str_contains($e->getMessage(), 'not found'), "Caught non-existent log error");
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
