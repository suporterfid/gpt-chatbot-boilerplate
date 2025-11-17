#!/usr/bin/env php
<?php
/**
 * Test suite for WebhookDispatcher
 * Tests dispatch logic, job creation, and integration with repositories
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookDispatcher.php';
require_once __DIR__ . '/../includes/WebhookSubscriberRepository.php';
require_once __DIR__ . '/../includes/WebhookLogRepository.php';
require_once __DIR__ . '/../includes/JobQueue.php';

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

echo "=== Running WebhookDispatcher Tests ===\n\n";

// Initialize test database
$dbConfig = [
    'database_path' => '/tmp/test_webhook_dispatcher_' . time() . '.db'
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

// Setup test data
echo "\n--- Setup: Creating Test Subscribers ---\n";
$subscriberRepo = new WebhookSubscriberRepository($db);
$subscriber1 = null;
$subscriber2 = null;
$subscriber3 = null;

try {
    $subscriber1 = $subscriberRepo->save([
        'client_id' => 'client-001',
        'url' => 'https://example.com/webhook1',
        'secret' => 'secret-001',
        'events' => ['ai.response', 'lead.qualified']
    ]);
    echo "✓ Subscriber 1 created (ID: {$subscriber1['id']})\n";
    
    $subscriber2 = $subscriberRepo->save([
        'client_id' => 'client-002',
        'url' => 'https://example.com/webhook2',
        'secret' => 'secret-002',
        'events' => ['ai.response']
    ]);
    echo "✓ Subscriber 2 created (ID: {$subscriber2['id']})\n";
    
    $subscriber3 = $subscriberRepo->save([
        'client_id' => 'client-003',
        'url' => 'https://example.com/webhook3',
        'secret' => 'secret-003',
        'events' => ['order.created']
    ]);
    echo "✓ Subscriber 3 created (ID: {$subscriber3['id']})\n";
} catch (Exception $e) {
    echo "✗ Failed to create test subscribers: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize WebhookDispatcher
$config = [
    'agent_id' => 'test-agent',
    'webhook_max_attempts' => 3
];
$dispatcher = new WebhookDispatcher($db, $config);

// Test 1: Dispatch to Multiple Subscribers
echo "\n--- Test 1: Dispatch to Multiple Subscribers ---\n";
try {
    $result = $dispatcher->dispatch('ai.response', [
        'message' => 'Test response',
        'confidence' => 0.95
    ]);
    
    assert_true(isset($result['event']), "Result has event field");
    assert_equals('ai.response', $result['event'], "Event type matches");
    assert_equals(2, $result['subscribers_found'], "Found 2 subscribers for ai.response");
    assert_equals(2, $result['jobs_created'], "Created 2 jobs");
    assert_equals(2, count($result['job_ids']), "Job IDs array has 2 entries");
    assert_equals(2, count($result['subscriber_ids']), "Subscriber IDs array has 2 entries");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 2: Dispatch to Single Subscriber
echo "\n--- Test 2: Dispatch to Single Subscriber ---\n";
try {
    $result = $dispatcher->dispatch('lead.qualified', [
        'lead_id' => 'L123',
        'score' => 85
    ]);
    
    assert_equals('lead.qualified', $result['event'], "Event type matches");
    assert_equals(1, $result['subscribers_found'], "Found 1 subscriber for lead.qualified");
    assert_equals(1, $result['jobs_created'], "Created 1 job");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 3: Dispatch with No Subscribers
echo "\n--- Test 3: Dispatch with No Subscribers ---\n";
try {
    $result = $dispatcher->dispatch('unknown.event', ['data' => 'test']);
    
    assert_equals('unknown.event', $result['event'], "Event type matches");
    assert_equals(0, $result['subscribers_found'], "Found 0 subscribers");
    assert_equals(0, $result['jobs_created'], "Created 0 jobs");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 4: Verify Job Queue Entries
echo "\n--- Test 4: Verify Job Queue Entries ---\n";
try {
    $jobQueue = new JobQueue($db);
    $sql = "SELECT COUNT(*) as count FROM jobs WHERE type = 'webhook_delivery'";
    $result = $db->query($sql);
    $jobCount = $result[0]['count'];
    
    assert_true($jobCount >= 3, "At least 3 webhook_delivery jobs created (found $jobCount)");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 5: Verify Log Entries Created
echo "\n--- Test 5: Verify Log Entries Created ---\n";
try {
    $logRepo = new WebhookLogRepository($db);
    $logs = $logRepo->listLogs([], 100, 0);
    $logCount = count($logs);
    
    assert_true($logCount >= 3, "At least 3 log entries created (found $logCount)");
    
    if ($logCount > 0) {
        $firstLog = $logs[0];
        assert_not_null($firstLog['subscriber_id'], "Log has subscriber_id");
        assert_not_null($firstLog['event'], "Log has event");
        assert_equals(0, $firstLog['attempts'], "Initial log has 0 attempts");
    }
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 6: Verify Job Payload Structure
echo "\n--- Test 6: Verify Job Payload Structure ---\n";
try {
    $sql = "SELECT * FROM jobs WHERE type = 'webhook_delivery' LIMIT 1";
    $jobs = $db->query($sql);
    
    if (!empty($jobs)) {
        $job = $jobs[0];
        $payload = json_decode($job['payload_json'], true);
        
        assert_not_null($payload['subscriber_id'], "Payload has subscriber_id");
        assert_not_null($payload['subscriber_url'], "Payload has subscriber_url");
        assert_not_null($payload['subscriber_secret'], "Payload has subscriber_secret");
        assert_not_null($payload['event_type'], "Payload has event_type");
        assert_not_null($payload['webhook_payload'], "Payload has webhook_payload");
        assert_not_null($payload['log_id'], "Payload has log_id");
        
        $webhookPayload = $payload['webhook_payload'];
        assert_not_null($webhookPayload['event'], "Webhook payload has event");
        assert_not_null($webhookPayload['timestamp'], "Webhook payload has timestamp");
        assert_not_null($webhookPayload['data'], "Webhook payload has data");
    }
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 7: HMAC Signature Generation
echo "\n--- Test 7: HMAC Signature Generation ---\n";
try {
    $payload = ['test' => 'data'];
    $secret = 'test-secret-123';
    
    $signature = WebhookDispatcher::generateSignature($payload, $secret);
    
    assert_true(str_starts_with($signature, 'sha256='), "Signature has sha256 prefix");
    assert_equals(71, strlen($signature), "Signature has correct length (sha256= + 64 hex chars)");
    
    // Verify signature is consistent
    $signature2 = WebhookDispatcher::generateSignature($payload, $secret);
    assert_equals($signature, $signature2, "Signature is deterministic");
    
    // Verify different payload produces different signature
    $payload2 = ['test' => 'different'];
    $signature3 = WebhookDispatcher::generateSignature($payload2, $secret);
    assert_true($signature !== $signature3, "Different payload produces different signature");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 8: Dispatch Batch
echo "\n--- Test 8: Dispatch Batch ---\n";
try {
    $events = [
        ['event' => 'ai.response', 'payload' => ['msg' => 'test1']],
        ['event' => 'lead.qualified', 'payload' => ['lead' => 'L001']],
        ['event' => 'order.created', 'payload' => ['order' => 'O001']],
    ];
    
    $results = $dispatcher->dispatchBatch($events);
    
    assert_equals(3, count($results), "Batch returned 3 results");
    assert_equals('ai.response', $results[0]['event'], "First result is ai.response");
    assert_equals('lead.qualified', $results[1]['event'], "Second result is lead.qualified");
    assert_equals('order.created', $results[2]['event'], "Third result is order.created");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 9: Get Statistics
echo "\n--- Test 9: Get Statistics ---\n";
try {
    $stats = $dispatcher->getStatistics();
    
    assert_not_null($stats['subscribers'], "Statistics has subscribers");
    assert_not_null($stats['deliveries'], "Statistics has deliveries");
    assert_true($stats['subscribers']['total'] >= 3, "Statistics shows at least 3 subscribers");
    assert_true($stats['subscribers']['active'] >= 3, "Statistics shows at least 3 active subscribers");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 10: Dispatch with Invalid Input
echo "\n--- Test 10: Dispatch with Invalid Input ---\n";
try {
    $dispatcher->dispatch('', ['data' => 'test']);
    echo "✗ FAIL: Should have thrown exception for empty event type\n";
    $testsFailed++;
} catch (Exception $e) {
    assert_true(str_contains($e->getMessage(), 'Event type is required'), "Exception message mentions event type");
}

try {
    $dispatcher->dispatch('test.event', 'not-an-array');
    echo "✗ FAIL: Should have thrown exception for non-array payload\n";
    $testsFailed++;
} catch (Exception $e) {
    assert_true(str_contains($e->getMessage(), 'Payload must be an array'), "Exception message mentions payload type");
}

// Test 11: Deactivate Subscriber and Verify No Dispatch
echo "\n--- Test 11: Deactivate Subscriber and Verify No Dispatch ---\n";
try {
    // Deactivate subscriber 1
    $subscriberRepo->deactivate($subscriber1['id']);
    
    // Dispatch event
    $result = $dispatcher->dispatch('ai.response', ['test' => 'after deactivation']);
    
    // Should only find 1 subscriber now (subscriber2), not subscriber1
    assert_equals(1, $result['subscribers_found'], "Found only 1 active subscriber after deactivation");
    assert_equals(1, $result['jobs_created'], "Created only 1 job");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Cleanup
echo "\n--- Cleanup ---\n";
unlink($dbConfig['database_path']);
echo "✓ Test database deleted\n";

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";
echo "Total:  " . ($testsPassed + $testsFailed) . "\n";

if ($testsFailed > 0) {
    exit(1);
}

echo "\n✓ All tests passed!\n";
exit(0);
