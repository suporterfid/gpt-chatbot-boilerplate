#!/usr/bin/env php
<?php
/**
 * Phase 3 Tests - Background Workers, Webhooks, and RBAC
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/JobQueue.php';
require_once __DIR__ . '/../includes/AdminAuth.php';
require_once __DIR__ . '/../includes/WebhookHandler.php';

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

echo "=== Running Phase 3 Tests ===\n\n";

// Initialize test database
$dbConfig = [
    'database_path' => '/tmp/test_phase3_' . time() . '.db'
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

// Test 1: JobQueue - Enqueue Job
echo "\n--- Test 1: JobQueue - Enqueue Job ---\n";
try {
    $jobQueue = new JobQueue($db);
    
    $jobId = $jobQueue->enqueue('test_job', ['param1' => 'value1'], 3, 0);
    assert_not_null($jobId, "Job enqueued successfully");
    
    $job = $jobQueue->getJob($jobId);
    assert_not_null($job, "Job retrieved");
    assert_equals('test_job', $job['type'], "Job type is correct");
    assert_equals('pending', $job['status'], "Job status is pending");
    assert_equals('value1', $job['payload']['param1'], "Job payload is correct");
} catch (Exception $e) {
    assert_true(false, "JobQueue enqueue failed: " . $e->getMessage());
}

// Test 2: JobQueue - Claim Job
echo "\n--- Test 2: JobQueue - Claim Job ---\n";
try {
    $claimed = $jobQueue->claimNext();
    assert_not_null($claimed, "Job claimed");
    assert_equals('running', $claimed['status'], "Job status is running");
    assert_equals($jobId, $claimed['id'], "Claimed job is the enqueued job");
} catch (Exception $e) {
    assert_true(false, "JobQueue claim failed: " . $e->getMessage());
}

// Test 3: JobQueue - Mark Completed
echo "\n--- Test 3: JobQueue - Mark Completed ---\n";
try {
    $jobQueue->markCompleted($jobId, ['result' => 'success']);
    
    $job = $jobQueue->getJob($jobId);
    assert_equals('completed', $job['status'], "Job marked as completed");
    assert_equals('success', $job['result']['result'], "Job result stored");
} catch (Exception $e) {
    assert_true(false, "JobQueue markCompleted failed: " . $e->getMessage());
}

// Test 4: JobQueue - Retry Logic
echo "\n--- Test 4: JobQueue - Retry Logic ---\n";
try {
    $retryJobId = $jobQueue->enqueue('retry_job', ['test' => 'retry'], 3, 0);
    $retryJob = $jobQueue->claimNext();
    
    // Mark as failed with retry
    $jobQueue->markFailed($retryJobId, "Test failure", true);
    
    $job = $jobQueue->getJob($retryJobId);
    assert_equals('pending', $job['status'], "Failed job marked for retry");
    assert_equals(1, $job['attempts'], "Attempt count incremented");
} catch (Exception $e) {
    assert_true(false, "JobQueue retry failed: " . $e->getMessage());
}

// Test 5: JobQueue - Max Attempts
echo "\n--- Test 5: JobQueue - Max Attempts ---\n";
try {
    $failJobId = $jobQueue->enqueue('fail_job', ['test' => 'fail'], 1, 0);
    
    // First failure (max attempts = 1, so this should mark as failed)
    $failJob = $jobQueue->claimNext();
    if ($failJob && $failJob['id'] === $failJobId) {
        $jobQueue->markFailed($failJobId, "Failure 1", true);
    }
    
    $job = $jobQueue->getJob($failJobId);
    assert_equals('failed', $job['status'], "Job failed after max attempts");
    assert_true($job['attempts'] >= 1, "Attempts count is at least 1");
} catch (Exception $e) {
    assert_true(false, "JobQueue max attempts test failed: " . $e->getMessage());
}

// Test 6: JobQueue - Stats
echo "\n--- Test 6: JobQueue - Stats ---\n";
try {
    $stats = $jobQueue->getStats();
    assert_true($stats['completed'] >= 1, "Stats show completed jobs");
    assert_true($stats['failed'] >= 1, "Stats show failed jobs");
} catch (Exception $e) {
    assert_true(false, "JobQueue stats failed: " . $e->getMessage());
}

// Test 7: AdminAuth - Create Tenant and User
echo "\n--- Test 7: AdminAuth - Create Tenant and User ---\n";
try {
    $config = [];
    $adminAuth = new AdminAuth($db, $config);

    // First create a tenant for multi-tenancy support
    $tenantId = 'tenant_' . bin2hex(random_bytes(8));
    $now = date('Y-m-d H:i:s');
    $db->insert(
        "INSERT INTO tenants (id, name, slug, status, created_at, updated_at) VALUES (?, ?, ?, 'active', ?, ?)",
        [$tenantId, 'Test Tenant', 'test-tenant', $now, $now]
    );
    assert_true(true, "Test tenant created");

    // Now create user with tenant_id
    $user = $adminAuth->createUser('test@example.com', 'password123', AdminAuth::ROLE_ADMIN, $tenantId);
    assert_not_null($user, "User created");
    assert_equals('test@example.com', $user['email'], "User email is correct");
    assert_equals(AdminAuth::ROLE_ADMIN, $user['role'], "User role is correct");
    assert_equals($tenantId, $user['tenant_id'], "User tenant_id is correct");
} catch (Exception $e) {
    assert_true(false, "AdminAuth createUser failed: " . $e->getMessage());
}

// Test 8: AdminAuth - Generate API Key
echo "\n--- Test 8: AdminAuth - Generate API Key ---\n";
try {
    $keyData = $adminAuth->generateApiKey($user['id'], 'Test Key', null);
    assert_not_null($keyData, "API key generated");
    assert_not_null($keyData['key'], "API key has plain-text key");
    assert_true(strpos($keyData['key'], 'chatbot_') === 0, "API key has correct prefix");
} catch (Exception $e) {
    assert_true(false, "AdminAuth generateApiKey failed: " . $e->getMessage());
}

// Test 9: AdminAuth - Authenticate with API Key
echo "\n--- Test 9: AdminAuth - Authenticate with API Key ---\n";
try {
    $authenticatedUser = $adminAuth->authenticate($keyData['key']);
    assert_not_null($authenticatedUser, "User authenticated with API key");
    assert_equals($user['email'], $authenticatedUser['email'], "Authenticated user email matches");
    assert_equals(AdminAuth::ROLE_ADMIN, $authenticatedUser['role'], "Authenticated user role matches");
} catch (Exception $e) {
    assert_true(false, "AdminAuth authenticate failed: " . $e->getMessage());
}

// Test 10: AdminAuth - Permissions
echo "\n--- Test 10: AdminAuth - Permissions ---\n";
try {
    $hasRead = $adminAuth->hasPermission($authenticatedUser, 'read');
    assert_true($hasRead, "Admin has read permission");
    
    $hasCreate = $adminAuth->hasPermission($authenticatedUser, 'create');
    assert_true($hasCreate, "Admin has create permission");
    
    $hasManageUsers = $adminAuth->hasPermission($authenticatedUser, 'manage_users');
    assert_true(!$hasManageUsers, "Admin does not have manage_users permission");
} catch (Exception $e) {
    assert_true(false, "AdminAuth permissions test failed: " . $e->getMessage());
}

// Test 11: AdminAuth - Viewer Role
echo "\n--- Test 11: AdminAuth - Viewer Role ---\n";
try {
    // Create viewer with same tenant
    $viewer = $adminAuth->createUser('viewer@example.com', 'password123', AdminAuth::ROLE_VIEWER, $tenantId);
    assert_not_null($viewer, "Viewer user created");

    $viewerKey = $adminAuth->generateApiKey($viewer['id'], 'Viewer Key');
    $viewerAuth = $adminAuth->authenticate($viewerKey['key']);

    $hasRead = $adminAuth->hasPermission($viewerAuth, 'read');
    assert_true($hasRead, "Viewer has read permission");

    $hasCreate = $adminAuth->hasPermission($viewerAuth, 'create');
    assert_true(!$hasCreate, "Viewer does not have create permission");
} catch (Exception $e) {
    assert_true(false, "AdminAuth viewer role test failed: " . $e->getMessage());
}

// Test 11b: AdminAuth - Super-Admin Role (No Tenant Required)
echo "\n--- Test 11b: AdminAuth - Super-Admin Role ---\n";
try {
    // Create super-admin without tenant (should succeed)
    $superAdmin = $adminAuth->createUser('superadmin@example.com', 'password123', AdminAuth::ROLE_SUPER_ADMIN, null);
    assert_not_null($superAdmin, "Super-admin user created");
    assert_true($superAdmin['tenant_id'] === null, "Super-admin has no tenant_id");
    assert_equals(AdminAuth::ROLE_SUPER_ADMIN, $superAdmin['role'], "Super-admin role is correct");

    $superAdminKey = $adminAuth->generateApiKey($superAdmin['id'], 'Super Admin Key');
    $superAdminAuth = $adminAuth->authenticate($superAdminKey['key']);

    $hasManageUsers = $adminAuth->hasPermission($superAdminAuth, 'manage_users');
    assert_true($hasManageUsers, "Super-admin has manage_users permission");

    $hasRotateTokens = $adminAuth->hasPermission($superAdminAuth, 'rotate_tokens');
    assert_true($hasRotateTokens, "Super-admin has rotate_tokens permission");
} catch (Exception $e) {
    assert_true(false, "AdminAuth super-admin role test failed: " . $e->getMessage());
}

// Test 12: WebhookHandler - Store Event
echo "\n--- Test 12: WebhookHandler - Store Event ---\n";
try {
    $webhookHandler = new WebhookHandler($db);
    
    $eventId = 'evt_test_' . time();
    $storedId = $webhookHandler->storeEvent($eventId, 'test.event', ['data' => 'test']);
    assert_not_null($storedId, "Event stored");
} catch (Exception $e) {
    assert_true(false, "WebhookHandler storeEvent failed: " . $e->getMessage());
}

// Test 13: WebhookHandler - Idempotency
echo "\n--- Test 13: WebhookHandler - Idempotency ---\n";
try {
    $isProcessed = $webhookHandler->isEventProcessed($eventId);
    assert_true(!$isProcessed, "Event not yet processed");
    
    $webhookHandler->markEventProcessed($eventId);
    
    $isProcessed = $webhookHandler->isEventProcessed($eventId);
    assert_true($isProcessed, "Event marked as processed");
    
    // Try to store duplicate event
    $duplicateAttempt = false;
    try {
        $webhookHandler->storeEvent($eventId, 'test.event', ['data' => 'test']);
    } catch (Exception $e) {
        $duplicateAttempt = strpos($e->getMessage(), 'Duplicate') !== false;
    }
    assert_true($duplicateAttempt, "Duplicate event rejected");
} catch (Exception $e) {
    assert_true(false, "WebhookHandler idempotency test failed: " . $e->getMessage());
}

// Test 14: WebhookHandler - Signature Verification
echo "\n--- Test 14: WebhookHandler - Signature Verification ---\n";
try {
    $secret = 'test_secret_key';
    $webhookWithSecret = new WebhookHandler($db, $secret);
    
    $payload = json_encode(['test' => 'data']);
    $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    
    $isValid = $webhookWithSecret->verifySignature($payload, $signature);
    assert_true($isValid, "Valid signature verified");
    
    $invalidSig = 'sha256=invalid_signature';
    $isInvalid = $webhookWithSecret->verifySignature($payload, $invalidSig);
    assert_true(!$isInvalid, "Invalid signature rejected");
} catch (Exception $e) {
    assert_true(false, "WebhookHandler signature verification failed: " . $e->getMessage());
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

if ($testsFailed > 0) {
    exit(1);
}

echo "\n✓ All Phase 3 tests passed!\n";
exit(0);
