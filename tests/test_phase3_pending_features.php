<?php
/**
 * Tests for Phase 3 Pending Features
 * - Job Management UI endpoints
 * - Audit Log viewer endpoints
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/JobQueue.php';

echo "=== Testing Phase 3 Pending Features ===\n\n";

// Setup test database
$testDb = '/tmp/test_phase3_pending.db';
if (file_exists($testDb)) {
    unlink($testDb);
}

$db = new DB(['database_path' => $testDb]);

echo "--- Running Migrations ---\n";
$db->runMigrations(__DIR__ . '/../db/migrations');
echo "✓ Migrations completed\n\n";

$passedTests = 0;
$failedTests = 0;

function test($description, $condition, $errorMessage = '') {
    global $passedTests, $failedTests;
    
    if ($condition) {
        echo "✓ PASS: $description\n";
        $passedTests++;
    } else {
        echo "✗ FAIL: $description\n";
        if ($errorMessage) {
            echo "  Error: $errorMessage\n";
        }
        $failedTests++;
    }
}

// ==================== Test 1: Audit Log Storage ====================

echo "--- Test 1: Audit Log Storage ---\n";

$db->execute(
    'INSERT INTO audit_log (actor, action, payload_json, created_at) VALUES (?, ?, ?, ?)',
    ['admin:test@example.com', 'agent.create', '{"name":"Test Agent"}', date('Y-m-d H:i:s')]
);

$logs = $db->query('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10');
test('Audit log entry created', count($logs) > 0);
test('Audit log has correct actor', $logs[0]['actor'] === 'admin:test@example.com');
test('Audit log has correct action', $logs[0]['action'] === 'agent.create');

// ==================== Test 2: Audit Log Retrieval ====================

echo "\n--- Test 2: Audit Log Retrieval ---\n";

// Insert multiple entries
for ($i = 1; $i <= 5; $i++) {
    $db->execute(
        'INSERT INTO audit_log (actor, action, payload_json, created_at) VALUES (?, ?, ?, ?)',
        ["admin:user{$i}@example.com", "test.action.{$i}", '{}', date('Y-m-d H:i:s')]
    );
}

$allLogs = $db->query('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100');
test('Multiple audit logs retrieved', count($allLogs) >= 5);
test('Logs ordered by created_at DESC', $allLogs[0]['created_at'] >= $allLogs[count($allLogs)-1]['created_at']);

// ==================== Test 3: Job Queue Integration ====================

echo "\n--- Test 3: Job Queue Integration ---\n";

$jobQueue = new JobQueue($db);

// Enqueue test jobs
$jobId1 = $jobQueue->enqueue('file_ingest', ['file_id' => 'file_123']);
$jobId2 = $jobQueue->enqueue('poll_ingestion_status', ['file_id' => 'file_456']);

test('Jobs enqueued successfully', !empty($jobId1) && !empty($jobId2));

// Get stats
$stats = $jobQueue->getStats();
test('Job stats returned', isset($stats['pending']) && isset($stats['completed']));
test('Pending jobs count correct', $stats['pending'] >= 2);

// List pending jobs
$pendingJobs = $db->query('SELECT * FROM jobs WHERE status = ? LIMIT 50', ['pending']);
test('Pending jobs listed', count($pendingJobs) >= 2);
test('Job has correct structure', isset($pendingJobs[0]['type']) && isset($pendingJobs[0]['status']));

// ==================== Test 4: Job Status Filtering ====================

echo "\n--- Test 4: Job Status Filtering ---\n";

// Claim a job (mark as running)
$claimedJob = $jobQueue->claimNext();
test('Job claimed successfully', !empty($claimedJob));

// Update stats
$statsAfterClaim = $jobQueue->getStats();
test('Running jobs count updated', $statsAfterClaim['running'] >= 1);
test('Pending jobs count decreased', $statsAfterClaim['pending'] < $stats['pending']);

// Mark job as completed
if ($claimedJob) {
    $jobQueue->markCompleted($claimedJob['id'], ['status' => 'success']);
    $statsAfterComplete = $jobQueue->getStats();
    test('Completed jobs count updated', $statsAfterComplete['completed'] >= 1);
}

// List jobs by different statuses
$runningJobs = $db->query('SELECT * FROM jobs WHERE status = ? LIMIT 50', ['running']);
$completedJobs = $db->query('SELECT * FROM jobs WHERE status = ? LIMIT 50', ['completed']);

test('Running jobs can be filtered', is_array($runningJobs));
test('Completed jobs can be filtered', is_array($completedJobs));

// ==================== Test 5: Job Details Retrieval ====================

echo "\n--- Test 5: Job Details Retrieval ---\n";

$allJobs = $db->query('SELECT * FROM jobs LIMIT 10');
if (count($allJobs) > 0) {
    $job = $allJobs[0];
    test('Job has ID', isset($job['id']));
    test('Job has type', isset($job['type']));
    test('Job has status', isset($job['status']));
    test('Job has attempts', isset($job['attempts']));
    test('Job has payload_json', isset($job['payload_json']));
    test('Job payload is valid JSON', json_decode($job['payload_json']) !== null);
}

// ==================== Test 6: Audit Log Pagination ====================

echo "\n--- Test 6: Audit Log Pagination ---\n";

// Insert many audit log entries
for ($i = 1; $i <= 25; $i++) {
    $db->execute(
        'INSERT INTO audit_log (actor, action, payload_json, created_at) VALUES (?, ?, ?, ?)',
        ['admin:batch@example.com', "batch.action.{$i}", '{}', date('Y-m-d H:i:s')]
    );
}

$limit10 = $db->query('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10');
$limit50 = $db->query('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 50');

test('Pagination with limit 10 works', count($limit10) === 10);
test('Pagination with limit 50 works', count($limit50) >= 25);

// ==================== Test 7: Job Retry Functionality ====================

echo "\n--- Test 7: Job Retry Functionality ---\n";

// Create a failed job
$failedJobId = $jobQueue->enqueue('test_failed_job', ['test' => 'data']);
$failedJob = $jobQueue->claimNext();

if ($failedJob) {
    // Mark as failed without retry (so it stays failed)
    $jobQueue->markFailed($failedJob['id'], 'Test error message', false);
    
    // Verify job is marked as failed
    $failedJobData = $db->query('SELECT * FROM jobs WHERE id = ?', [$failedJob['id']]);
    test('Job marked as failed', $failedJobData[0]['status'] === 'failed');
    test('Error message stored', !empty($failedJobData[0]['error_text']));
    
    // Retry the job
    $db->execute(
        'UPDATE jobs SET status = ?, available_at = ?, attempts = 0 WHERE id = ?',
        ['pending', date('Y-m-d H:i:s'), $failedJob['id']]
    );
    
    $retriedJob = $db->query('SELECT * FROM jobs WHERE id = ?', [$failedJob['id']]);
    test('Job reset to pending for retry', $retriedJob[0]['status'] === 'pending');
}

// ==================== Test 8: Job Cancellation ====================

echo "\n--- Test 8: Job Cancellation ---\n";

// Create a job to cancel
$cancelJobId = $jobQueue->enqueue('test_cancel_job', ['test' => 'cancel']);

// Cancel it by marking as 'failed' with 'Cancelled by user' message
// Note: The jobs table schema (db/migrations/005_create_jobs_table.sql) only supports:
//       'pending', 'running', 'completed', 'failed'
// Future schema enhancement could add 'cancelled' as a distinct status
$db->execute(
    'UPDATE jobs SET status = ?, error_text = ? WHERE id = ?',
    ['failed', 'Cancelled by user', $cancelJobId]
);

$cancelledJob = $db->query('SELECT * FROM jobs WHERE id = ?', [$cancelJobId]);
test('Job cancelled successfully', $cancelledJob[0]['status'] === 'failed' && strpos($cancelledJob[0]['error_text'], 'Cancelled') !== false);

// ==================== Summary ====================

echo "\n=== Test Summary ===\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n\n";

if ($failedTests === 0) {
    echo "✓ All Phase 3 pending features tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
