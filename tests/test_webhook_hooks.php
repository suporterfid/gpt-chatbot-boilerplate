#!/usr/bin/env php
<?php
/**
 * Test suite for WebhookDispatcher Hook System
 * Tests payload transformation hooks and pluggable queue drivers
 * 
 * Task: wh-008a
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookDispatcher.php';
require_once __DIR__ . '/../includes/QueueDriverInterface.php';

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

echo "=== Running Webhook Hook System Tests ===\n\n";

// Initialize test database
$dbConfig = [
    'database_path' => '/tmp/test_webhook_hooks_' . time() . '.db'
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

// Create test dispatcher
$dispatcher = new WebhookDispatcher($db, []);

// Test 1: Register and retrieve transformation hooks
echo "\n--- Test 1: Register Transformation Hooks ---\n";

$hook1Called = false;
$hook1 = function($payload) use (&$hook1Called) {
    $hook1Called = true;
    $payload['data']['hook1'] = 'applied';
    return $payload;
};

$hook2Called = false;
$hook2 = function($payload) use (&$hook2Called) {
    $hook2Called = true;
    $payload['data']['hook2'] = 'applied';
    return $payload;
};

$dispatcher->registerTransform('test.event', $hook1);
$dispatcher->registerTransform('test.event', $hook2);

$hooks = $dispatcher->getTransformHooks();
assert_true(isset($hooks['test.event']), "Hooks registered for test.event");
assert_equals(2, count($hooks['test.event']), "Two hooks registered");

// Test 2: Register global transformation hook
echo "\n--- Test 2: Register Global Hook ---\n";

$globalHookCalled = false;
$globalHook = function($payload) use (&$globalHookCalled) {
    $globalHookCalled = true;
    $payload['data']['global'] = 'applied';
    return $payload;
};

$dispatcher->registerTransform('*', $globalHook);

$hooks = $dispatcher->getTransformHooks();
assert_true(isset($hooks['*']), "Global hook registered");

// Test 3: Method chaining
echo "\n--- Test 3: Method Chaining ---\n";

$result = $dispatcher
    ->registerTransform('chain.test', function($p) { return $p; })
    ->clearTransformHooks('chain.test')
    ->registerTransform('chain.test', function($p) { return $p; });

assert_true($result instanceof WebhookDispatcher, "Methods return dispatcher instance for chaining");

// Test 4: Clear hooks
echo "\n--- Test 4: Clear Hooks ---\n";

$dispatcher->clearTransformHooks('test.event');
$hooks = $dispatcher->getTransformHooks();
assert_true(!isset($hooks['test.event']), "Event-specific hooks cleared");
assert_true(isset($hooks['*']), "Global hooks still present after clearing specific event");

$dispatcher->clearTransformHooks();
$hooks = $dispatcher->getTransformHooks();
assert_equals(0, count($hooks), "All hooks cleared");

// Test 5: Pluggable queue driver
echo "\n--- Test 5: Pluggable Queue Driver ---\n";

// Create mock queue driver
class MockQueueDriver implements QueueDriverInterface {
    public $enqueueCalls = [];
    
    public function enqueue($jobType, $payload, $maxAttempts = 3, $delay = 0) {
        $this->enqueueCalls[] = [
            'jobType' => $jobType,
            'payload' => $payload,
            'maxAttempts' => $maxAttempts,
            'delay' => $delay
        ];
        return 'mock_job_' . count($this->enqueueCalls);
    }
    
    public function getJobStatus($jobId) {
        return ['id' => $jobId, 'status' => 'mock'];
    }
}

$mockDriver = new MockQueueDriver();
$dispatcher->setQueueDriver($mockDriver);

assert_equals(0, count($mockDriver->enqueueCalls), "No jobs enqueued initially");

// Test 6: Invalid queue driver
echo "\n--- Test 6: Invalid Queue Driver Validation ---\n";

class InvalidDriver {
    // Missing enqueue method
}

try {
    $dispatcher->setQueueDriver(new InvalidDriver());
    assert_true(false, "Should throw exception for invalid driver");
} catch (Exception $e) {
    assert_true(true, "Exception thrown for invalid driver");
    assert_true(strpos($e->getMessage(), 'enqueue') !== false, "Error message mentions enqueue method");
}

// Test 7: Hooks applied during dispatch
echo "\n--- Test 7: Hooks Applied During Dispatch ---\n";

// Setup fresh dispatcher with mock driver
$dispatcher = new WebhookDispatcher($db, []);
$mockDriver = new MockQueueDriver();
$dispatcher->setQueueDriver($mockDriver);

// Create test subscriber
require_once __DIR__ . '/../includes/WebhookSubscriberRepository.php';
$subscriberRepo = new WebhookSubscriberRepository($db);

$subscriber = $subscriberRepo->save([
    'client_id' => 'test-client',
    'url' => 'https://example.com/webhook',
    'secret' => 'test-secret',
    'events' => ['hook.test']
]);

// Register transformation hooks
$transformLog = [];

$dispatcher->registerTransform('*', function($payload) use (&$transformLog) {
    $transformLog[] = 'global_hook';
    $payload['data']['transformed_by_global'] = true;
    return $payload;
});

$dispatcher->registerTransform('hook.test', function($payload) use (&$transformLog) {
    $transformLog[] = 'event_hook';
    $payload['data']['transformed_by_event'] = true;
    return $payload;
});

// Dispatch event
$result = $dispatcher->dispatch('hook.test', ['original' => 'data']);

assert_true($result['jobs_created'] > 0, "Job created for dispatch");
assert_equals(2, count($transformLog), "Both hooks were called");
assert_equals('global_hook', $transformLog[0], "Global hook called first");
assert_equals('event_hook', $transformLog[1], "Event hook called second");

// Check that mock driver received the job
assert_equals(1, count($mockDriver->enqueueCalls), "Job enqueued to mock driver");

$enqueuedPayload = $mockDriver->enqueueCalls[0]['payload'];
assert_true(isset($enqueuedPayload['webhook_payload']['data']['transformed_by_global']), 
    "Global transformation applied to dispatched payload");
assert_true(isset($enqueuedPayload['webhook_payload']['data']['transformed_by_event']), 
    "Event transformation applied to dispatched payload");
assert_true(isset($enqueuedPayload['webhook_payload']['data']['original']), 
    "Original data preserved");

// Test 8: Multiple hooks on same event
echo "\n--- Test 8: Multiple Hooks Chain ---\n";

$dispatcher = new WebhookDispatcher($db, []);
$mockDriver = new MockQueueDriver();
$dispatcher->setQueueDriver($mockDriver);

$callOrder = [];

$dispatcher
    ->registerTransform('chain.test', function($payload) use (&$callOrder) {
        $callOrder[] = 1;
        $payload['data']['step1'] = true;
        return $payload;
    })
    ->registerTransform('chain.test', function($payload) use (&$callOrder) {
        $callOrder[] = 2;
        $payload['data']['step2'] = true;
        return $payload;
    })
    ->registerTransform('chain.test', function($payload) use (&$callOrder) {
        $callOrder[] = 3;
        $payload['data']['step3'] = true;
        return $payload;
    });

// Create subscriber for chain.test
$subscriber = $subscriberRepo->save([
    'client_id' => 'test-client-2',
    'url' => 'https://example.com/webhook2',
    'secret' => 'test-secret-2',
    'events' => ['chain.test']
]);

$result = $dispatcher->dispatch('chain.test', ['initial' => true]);

assert_equals([1, 2, 3], $callOrder, "Hooks executed in registration order");

$enqueuedPayload = $mockDriver->enqueueCalls[0]['payload'];
$webhookData = $enqueuedPayload['webhook_payload']['data'];
assert_true($webhookData['step1'] && $webhookData['step2'] && $webhookData['step3'], 
    "All transformations applied in sequence");

echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

// Cleanup
@unlink($dbConfig['database_path']);

exit($testsFailed > 0 ? 1 : 0);
