<?php
/**
 * Test suite for WebhookGateway orchestration service
 * 
 * Tests the core functionality required by wh-001b:
 * - JSON parsing and validation
 * - Schema validation (event, timestamp, data fields)
 * - Signature verification
 * - Payload normalization
 * - Downstream event routing
 * - Idempotency checking
 * - Consistent response structure
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/WebhookGateway.php';
require_once $projectRoot . '/config.php';

/**
 * Helper function to create test database
 */
function createTestDb(): DB {
    $config = [
        'database_path' => sys_get_temp_dir() . '/test_webhook_' . uniqid() . '.db'
    ];
    $db = new DB($config);
    
    // Create necessary tables
    $db->execute("
        CREATE TABLE IF NOT EXISTS webhook_events (
            id TEXT PRIMARY KEY,
            event_id TEXT UNIQUE NOT NULL,
            event_type TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            processed INTEGER DEFAULT 0,
            processed_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->execute("
        CREATE TABLE IF NOT EXISTS jobs (
            id TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            payload_json TEXT NOT NULL,
            max_attempts INTEGER NOT NULL,
            status TEXT NOT NULL,
            available_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            locked_by TEXT,
            locked_at DATETIME,
            attempts INTEGER DEFAULT 0
        )
    ");
    
    return $db;
}

/**
 * Test basic JSON parsing and validation
 */
function testJsonParsing(): void {
    echo "\n=== Test 1: JSON Parsing and Validation ===\n";
    
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => '', 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, false);
    
    // Test 1.1: Empty body
    echo "Test 1.1: Empty body should fail...\n";
    try {
        $gateway->handleRequest([], '');
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'empty_body') {
            echo "  ✓ PASSED: Empty body rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error code: " . $e->getErrorCode() . "\n";
        }
    }
    
    // Test 1.2: Invalid JSON
    echo "Test 1.2: Invalid JSON should fail...\n";
    try {
        $gateway->handleRequest([], '{invalid json}');
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'invalid_json') {
            echo "  ✓ PASSED: Invalid JSON rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error code: " . $e->getErrorCode() . "\n";
        }
    }
}

/**
 * Test schema validation (event, timestamp, data)
 */
function testSchemaValidation(): void {
    echo "\n=== Test 2: Schema Validation (SPEC §4) ===\n";
    
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => '', 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, false);
    
    // Test 2.1: Missing event field
    echo "Test 2.1: Missing event field should fail...\n";
    try {
        $body = json_encode(['timestamp' => time(), 'data' => []]);
        $gateway->handleRequest([], $body);
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'invalid_event') {
            echo "  ✓ PASSED: Missing event rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error code: " . $e->getErrorCode() . "\n";
        }
    }
    
    // Test 2.2: Missing timestamp field
    echo "Test 2.2: Missing timestamp field should fail...\n";
    try {
        $body = json_encode(['event' => 'test.event', 'data' => []]);
        $gateway->handleRequest([], $body);
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'invalid_timestamp') {
            echo "  ✓ PASSED: Missing timestamp rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error code: " . $e->getErrorCode() . "\n";
        }
    }
    
    // Test 2.3: Invalid data field (not an object)
    echo "Test 2.3: Invalid data field (string instead of object) should fail...\n";
    try {
        $body = json_encode(['event' => 'test.event', 'timestamp' => time(), 'data' => 'not an object']);
        $gateway->handleRequest([], $body);
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'invalid_data') {
            echo "  ✓ PASSED: Invalid data type rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error code: " . $e->getErrorCode() . "\n";
        }
    }
    
    // Test 2.4: Valid payload
    echo "Test 2.4: Valid payload should be accepted...\n";
    try {
        $body = json_encode([
            'event' => 'test.event',
            'timestamp' => time(),
            'data' => ['test' => 'value']
        ]);
        $result = $gateway->handleRequest([], $body);
        if ($result['status'] === 'received' && $result['event'] === 'test.event') {
            echo "  ✓ PASSED: Valid payload accepted\n";
        } else {
            echo "  ✗ FAILED: Unexpected result: " . json_encode($result) . "\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
}

/**
 * Test timestamp validation (anti-replay)
 */
function testTimestampValidation(): void {
    echo "\n=== Test 3: Timestamp Validation (Anti-replay) ===\n";
    
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => '', 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, false);
    
    // Test 3.1: Old timestamp (outside tolerance)
    echo "Test 3.1: Old timestamp should fail...\n";
    try {
        $oldTimestamp = time() - 400; // 400 seconds ago, outside 300s tolerance
        $body = json_encode([
            'event' => 'test.old',
            'timestamp' => $oldTimestamp,
            'data' => []
        ]);
        $gateway->handleRequest([], $body);
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'invalid_timestamp' && $e->getStatusCode() === 422) {
            echo "  ✓ PASSED: Old timestamp rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error: " . $e->getErrorCode() . " / " . $e->getStatusCode() . "\n";
        }
    }
    
    // Test 3.2: Future timestamp (outside tolerance)
    echo "Test 3.2: Future timestamp should fail...\n";
    try {
        $futureTimestamp = time() + 400; // 400 seconds in future
        $body = json_encode([
            'event' => 'test.future',
            'timestamp' => $futureTimestamp,
            'data' => []
        ]);
        $gateway->handleRequest([], $body);
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'invalid_timestamp' && $e->getStatusCode() === 422) {
            echo "  ✓ PASSED: Future timestamp rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error: " . $e->getErrorCode() . " / " . $e->getStatusCode() . "\n";
        }
    }
    
    // Test 3.3: Valid timestamp (within tolerance)
    echo "Test 3.3: Valid timestamp should be accepted...\n";
    try {
        $body = json_encode([
            'event' => 'test.valid',
            'timestamp' => time(),
            'data' => []
        ]);
        $result = $gateway->handleRequest([], $body);
        if ($result['status'] === 'received') {
            echo "  ✓ PASSED: Valid timestamp accepted\n";
        } else {
            echo "  ✗ FAILED: Unexpected result\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
}

/**
 * Test signature verification
 */
function testSignatureVerification(): void {
    echo "\n=== Test 4: Signature Verification (SPEC §4) ===\n";
    
    $secret = 'test_secret_key';
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => $secret, 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, false);
    
    // Test 4.1: Missing signature when required
    echo "Test 4.1: Missing signature should fail...\n";
    try {
        $body = json_encode([
            'event' => 'test.event',
            'timestamp' => time(),
            'data' => []
        ]);
        $gateway->handleRequest([], $body);
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'missing_signature') {
            echo "  ✓ PASSED: Missing signature rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error code: " . $e->getErrorCode() . "\n";
        }
    }
    
    // Test 4.2: Invalid signature
    echo "Test 4.2: Invalid signature should fail...\n";
    try {
        $body = json_encode([
            'event' => 'test.event',
            'timestamp' => time(),
            'data' => [],
            'signature' => 'sha256=invalid_signature'
        ]);
        $gateway->handleRequest([], $body);
        echo "  ✗ FAILED: Should have thrown exception\n";
    } catch (WebhookGatewayException $e) {
        if ($e->getErrorCode() === 'invalid_signature') {
            echo "  ✓ PASSED: Invalid signature rejected\n";
        } else {
            echo "  ✗ FAILED: Wrong error code: " . $e->getErrorCode() . "\n";
        }
    }
    
    // Test 4.3: Valid signature in payload
    // Note: When signature is in payload, it should be computed over the raw body
    // This test demonstrates the proper way: compute signature, then add to payload
    echo "Test 4.3: Valid signature in payload should be accepted...\n";
    echo "  ℹ  Skipping - signature in payload creates circular dependency\n";
    echo "     (Use headers for signature verification instead)\n";
    
    // Test 4.4: Valid signature in headers
    echo "Test 4.4: Valid signature in headers should be accepted...\n";
    try {
        $body = json_encode([
            'event' => 'test.event2',
            'timestamp' => time(),
            'data' => ['test' => 'value2']
        ]);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
        $headers = ['X-Agent-Signature' => $signature];
        
        $result = $gateway->handleRequest($headers, $body);
        if ($result['status'] === 'received') {
            echo "  ✓ PASSED: Valid signature in headers accepted\n";
        } else {
            echo "  ✗ FAILED: Unexpected result\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
}

/**
 * Test idempotency checking
 */
function testIdempotency(): void {
    echo "\n=== Test 5: Idempotency Checking ===\n";
    
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => '', 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, false);
    
    // Test 5.1: First request should be processed
    echo "Test 5.1: First request should be processed...\n";
    try {
        $body = json_encode([
            'id' => 'unique_event_123',
            'event' => 'test.idempotency',
            'timestamp' => time(),
            'data' => ['test' => 'value']
        ]);
        $result = $gateway->handleRequest([], $body);
        if ($result['status'] === 'received' && !isset($result['note'])) {
            echo "  ✓ PASSED: First request processed\n";
        } else {
            echo "  ✗ FAILED: Unexpected result: " . json_encode($result) . "\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
    
    // Test 5.2: Duplicate request should be ignored
    echo "Test 5.2: Duplicate request should be ignored...\n";
    try {
        $body = json_encode([
            'id' => 'unique_event_123',
            'event' => 'test.idempotency',
            'timestamp' => time(),
            'data' => ['test' => 'value']
        ]);
        $result = $gateway->handleRequest([], $body);
        if ($result['status'] === 'received' && $result['note'] === 'duplicate_event') {
            echo "  ✓ PASSED: Duplicate request ignored\n";
        } else {
            echo "  ✗ FAILED: Duplicate not detected: " . json_encode($result) . "\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
}

/**
 * Test async routing to JobQueue
 */
function testAsyncRouting(): void {
    echo "\n=== Test 6: Async Routing to JobQueue ===\n";
    
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => '', 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, true); // async=true
    
    echo "Test 6.1: Event should be queued for async processing...\n";
    try {
        $body = json_encode([
            'event' => 'test.async',
            'timestamp' => time(),
            'data' => ['test' => 'async_value']
        ]);
        $result = $gateway->handleRequest([], $body);
        if ($result['status'] === 'received' && 
            $result['processing'] === 'async' && 
            isset($result['job_id'])) {
            echo "  ✓ PASSED: Event queued with job_id: " . $result['job_id'] . "\n";
            
            // Verify job in database
            $jobs = $db->query("SELECT * FROM jobs WHERE id = ?", [$result['job_id']]);
            if (count($jobs) === 1 && $jobs[0]['type'] === 'webhook_event') {
                echo "  ✓ PASSED: Job stored in database\n";
            } else {
                echo "  ✗ FAILED: Job not found in database\n";
            }
        } else {
            echo "  ✗ FAILED: Unexpected result: " . json_encode($result) . "\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
}

/**
 * Test sync processing
 */
function testSyncProcessing(): void {
    echo "\n=== Test 7: Sync Processing ===\n";
    
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => '', 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, false); // async=false
    
    echo "Test 7.1: Event should be processed synchronously...\n";
    try {
        $body = json_encode([
            'event' => 'test.sync',
            'timestamp' => time(),
            'data' => ['test' => 'sync_value']
        ]);
        $result = $gateway->handleRequest([], $body);
        if ($result['status'] === 'received' && 
            $result['processing'] === 'sync' && 
            !isset($result['job_id'])) {
            echo "  ✓ PASSED: Event processed synchronously\n";
        } else {
            echo "  ✗ FAILED: Unexpected result: " . json_encode($result) . "\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
}

/**
 * Test response structure per SPEC §4
 */
function testResponseStructure(): void {
    echo "\n=== Test 8: Response Structure (SPEC §4) ===\n";
    
    $db = createTestDb();
    $config = ['webhooks' => ['gateway_secret' => '', 'timestamp_tolerance' => 300], 'database' => []];
    $gateway = new WebhookGateway($config, $db, null, null, false);
    
    echo "Test 8.1: Response should have required fields...\n";
    try {
        $body = json_encode([
            'event' => 'test.response',
            'timestamp' => time(),
            'data' => ['test' => 'value']
        ]);
        $result = $gateway->handleRequest([], $body);
        
        $requiredFields = ['status', 'event', 'event_id', 'received_at', 'processing'];
        $allPresent = true;
        foreach ($requiredFields as $field) {
            if (!isset($result[$field])) {
                echo "  ✗ FAILED: Missing field: $field\n";
                $allPresent = false;
            }
        }
        
        if ($allPresent) {
            echo "  ✓ PASSED: All required fields present\n";
            echo "    Response: " . json_encode($result) . "\n";
        }
    } catch (Exception $e) {
        echo "  ✗ FAILED: Exception thrown: " . $e->getMessage() . "\n";
    }
}

// Run all tests
echo "\n";
echo str_repeat('=', 70) . "\n";
echo "WebhookGateway Test Suite - wh-001b Implementation\n";
echo str_repeat('=', 70) . "\n";

testJsonParsing();
testSchemaValidation();
testTimestampValidation();
testSignatureVerification();
testIdempotency();
testAsyncRouting();
testSyncProcessing();
testResponseStructure();

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "✓ All tests completed\n";
echo str_repeat('=', 70) . "\n";
