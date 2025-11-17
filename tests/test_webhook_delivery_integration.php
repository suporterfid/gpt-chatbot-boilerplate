#!/usr/bin/env php
<?php
/**
 * Integration test for webhook delivery via worker
 * Tests the full flow: dispatch -> job queue -> worker processing -> delivery logging
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

echo "=== Webhook Delivery Integration Test ===\n\n";

// Initialize test database
$dbConfig = [
    'database_path' => '/tmp/test_webhook_integration_' . time() . '.db'
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

// Setup: Create a test subscriber
echo "\n--- Setup: Creating Test Subscriber ---\n";
$subscriberRepo = new WebhookSubscriberRepository($db);

try {
    $subscriber = $subscriberRepo->save([
        'client_id' => 'test-client',
        'url' => 'https://httpbin.org/post', // Public testing endpoint
        'secret' => 'test-secret-key',
        'events' => ['test.event']
    ]);
    echo "✓ Test subscriber created (ID: {$subscriber['id']})\n";
} catch (Exception $e) {
    echo "✗ Failed to create test subscriber: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize services
$config = ['agent_id' => 'test-agent'];
$dispatcher = new WebhookDispatcher($db, $config);
$jobQueue = new JobQueue($db);
$logRepo = new WebhookLogRepository($db);

// Test 1: Dispatch creates job and log entry
echo "\n--- Test 1: Dispatch Creates Job and Log ---\n";
try {
    $result = $dispatcher->dispatch('test.event', [
        'test_data' => 'integration test',
        'timestamp' => time()
    ]);
    
    assert_equals(1, $result['subscribers_found'], "Found 1 subscriber");
    assert_equals(1, $result['jobs_created'], "Created 1 job");
    
    $jobId = $result['job_ids'][0];
    echo "✓ Job created with ID: $jobId\n";
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 2: Verify job is in pending status
echo "\n--- Test 2: Verify Job Status ---\n";
try {
    $sql = "SELECT * FROM jobs WHERE id = ?";
    $jobs = $db->query($sql, [$jobId]);
    
    assert_true(!empty($jobs), "Job found in database");
    
    if (!empty($jobs)) {
        $job = $jobs[0];
        assert_equals('webhook_delivery', $job['type'], "Job type is webhook_delivery");
        assert_equals('pending', $job['status'], "Job status is pending");
        
        $payload = json_decode($job['payload_json'], true);
        assert_equals($subscriber['id'], $payload['subscriber_id'], "Payload has correct subscriber_id");
        assert_equals($subscriber['url'], $payload['subscriber_url'], "Payload has correct URL");
    }
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 3: Simulate worker processing (mock delivery)
echo "\n--- Test 3: Simulate Worker Processing ---\n";
try {
    // Claim the job
    $claimedJob = $jobQueue->claimNext();
    
    assert_true($claimedJob !== null, "Job was claimed successfully");
    
    if ($claimedJob) {
        assert_equals($jobId, $claimedJob['id'], "Claimed job ID matches");
        assert_equals('webhook_delivery', $claimedJob['type'], "Claimed job type is correct");
        
        $payload = $claimedJob['payload'];
        
        // Simulate delivery (we'll just check the payload structure)
        assert_true(isset($payload['subscriber_id']), "Payload has subscriber_id");
        assert_true(isset($payload['subscriber_url']), "Payload has subscriber_url");
        assert_true(isset($payload['subscriber_secret']), "Payload has subscriber_secret");
        assert_true(isset($payload['webhook_payload']), "Payload has webhook_payload");
        assert_true(isset($payload['log_id']), "Payload has log_id");
        
        $logId = $payload['log_id'];
        
        // Simulate successful delivery by updating the log
        $logRepo->updateLog($logId, [
            'response_code' => 200,
            'response_body' => 'Mock success response',
            'attempts' => 1
        ]);
        
        // Mark job as completed
        $jobQueue->markCompleted($claimedJob['id'], [
            'http_code' => 200,
            'success' => true
        ]);
        
        echo "✓ Job processed and marked as completed\n";
    }
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 4: Verify log was updated
echo "\n--- Test 4: Verify Log Update ---\n";
try {
    if (isset($logId)) {
        $log = $logRepo->getById($logId);
        
        assert_equals(200, $log['response_code'], "Log has response code 200");
        assert_equals(1, $log['attempts'], "Log shows 1 attempt");
        assert_true(str_contains($log['response_body'], 'Mock success'), "Log has response body");
    }
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 5: Verify job status is completed
echo "\n--- Test 5: Verify Job Completion ---\n";
try {
    $sql = "SELECT * FROM jobs WHERE id = ?";
    $jobs = $db->query($sql, [$jobId]);
    
    if (!empty($jobs)) {
        $job = $jobs[0];
        assert_equals('completed', $job['status'], "Job status is completed");
        
        $result = json_decode($job['result_json'], true);
        assert_equals(200, $result['http_code'], "Result has HTTP 200");
        assert_equals(true, $result['success'], "Result indicates success");
    }
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 6: Test signature generation matches expected format
echo "\n--- Test 6: Verify Signature Format ---\n";
try {
    $testPayload = ['event' => 'test', 'data' => 'value'];
    $testSecret = 'my-secret';
    
    $signature = WebhookDispatcher::generateSignature($testPayload, $testSecret);
    
    // Manually compute expected signature
    $body = json_encode($testPayload);
    $expectedHash = hash_hmac('sha256', $body, $testSecret);
    $expectedSignature = 'sha256=' . $expectedHash;
    
    assert_equals($expectedSignature, $signature, "Signature matches expected HMAC format");
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 7: Test failure scenario - simulate failed delivery
echo "\n--- Test 7: Test Failure and Retry Logic ---\n";
try {
    // Dispatch another event
    $result2 = $dispatcher->dispatch('test.event', [
        'test' => 'retry scenario'
    ]);
    
    $jobId2 = $result2['job_ids'][0];
    $logId2 = null;
    
    // Claim and process with failure
    $claimedJob2 = $jobQueue->claimNext();
    
    if ($claimedJob2) {
        $payload2 = $claimedJob2['payload'];
        $logId2 = $payload2['log_id'];
        
        // Simulate failed delivery
        $logRepo->updateLog($logId2, [
            'response_code' => 500,
            'response_body' => 'Internal Server Error',
            'attempts' => 1
        ]);
        
        // Mark job as failed
        $jobQueue->markFailed($claimedJob2['id'], 'HTTP 500 error', true);
        
        echo "✓ Failed job scenario simulated\n";
        
        // Verify failure was logged
        $log2 = $logRepo->getById($logId2);
        assert_equals(500, $log2['response_code'], "Log has response code 500");
        assert_equals(1, $log2['attempts'], "Log shows 1 attempt");
    }
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 8: Test statistics
echo "\n--- Test 8: Verify Statistics ---\n";
try {
    $stats = $dispatcher->getStatistics();
    
    assert_true(isset($stats['subscribers']), "Statistics has subscribers");
    assert_true(isset($stats['deliveries']), "Statistics has deliveries");
    assert_true($stats['subscribers']['total'] >= 1, "At least 1 subscriber");
    assert_true($stats['subscribers']['active'] >= 1, "At least 1 active subscriber");
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

echo "\n✓ All integration tests passed!\n";
exit(0);
