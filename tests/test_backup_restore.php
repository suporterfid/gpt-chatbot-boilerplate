#!/usr/bin/env php
<?php
/**
 * Backup and Restore Test Suite
 * Tests backup scripts, monitoring, and restore functionality
 */

require_once __DIR__ . '/../config.php';

// Test configuration
$testDir = '/tmp/backup_test_' . time();
$testBackupDir = $testDir . '/backups';
$testDataDir = $testDir . '/data';
$testDbPath = $testDataDir . '/test.db';

// Test results
$tests = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
];

$failures = [];

function testAssert($condition, $message, $critical = true) {
    global $tests, $failures;
    
    if ($condition) {
        $tests['passed']++;
        echo "✓ PASS: $message\n";
        return true;
    } else {
        if ($critical) {
            $tests['failed']++;
            $failures[] = $message;
            echo "✗ FAIL: $message\n";
        } else {
            $tests['warnings']++;
            echo "⚠ WARN: $message\n";
        }
        return false;
    }
}

function setupTestEnvironment() {
    global $testDir, $testBackupDir, $testDataDir, $testDbPath;
    
    // Create test directories
    mkdir($testDir, 0755, true);
    mkdir($testBackupDir, 0755, true);
    mkdir($testDataDir, 0755, true);
    
    // Create test database
    $db = new SQLite3($testDbPath);
    $db->exec('CREATE TABLE test_table (id INTEGER PRIMARY KEY, data TEXT)');
    $db->exec("INSERT INTO test_table (data) VALUES ('test data 1')");
    $db->exec("INSERT INTO test_table (data) VALUES ('test data 2')");
    $db->close();
    
    // Create test config file
    file_put_contents($testDir . '/.env', "TEST_VAR=test_value\n");
    
    // Create test uploaded files
    $uploadDir = $testDir . '/uploads';
    mkdir($uploadDir, 0755, true);
    file_put_contents($uploadDir . '/test_file.txt', "Test upload content\n");
    
    return true;
}

function cleanupTestEnvironment() {
    global $testDir;
    
    if (is_dir($testDir)) {
        exec("rm -rf " . escapeshellarg($testDir));
    }
}

echo "===========================================\n";
echo "Backup and Restore Test Suite\n";
echo "===========================================\n\n";

// Setup
echo "Setting up test environment...\n";
if (!setupTestEnvironment()) {
    echo "Failed to set up test environment\n";
    exit(1);
}
echo "✓ Test environment ready\n\n";

// Test 1: Verify backup scripts exist and are executable
echo "Test Group 1: Script Existence and Permissions\n";
echo "------------------------------------------------\n";

$scripts = [
    'scripts/db_backup.sh',
    'scripts/backup_all.sh',
    'scripts/db_restore.sh',
    'scripts/restore_all.sh',
    'scripts/monitor_backups.sh',
    'scripts/test_restore.sh',
];

foreach ($scripts as $script) {
    testAssert(
        file_exists($script),
        "Script exists: $script"
    );
    
    testAssert(
        is_executable($script),
        "Script is executable: $script"
    );
}

// Test 2: Verify automation templates exist
echo "\nTest Group 2: Automation Templates\n";
echo "------------------------------------------------\n";

$templates = [
    'scripts/backup.crontab',
    'scripts/chatbot-backup.service',
    'scripts/chatbot-backup.timer',
    'scripts/chatbot-backup-monitor.service',
    'scripts/chatbot-backup-monitor.timer',
];

foreach ($templates as $template) {
    testAssert(
        file_exists($template),
        "Template exists: $template"
    );
}

// Test 3: Verify documentation exists
echo "\nTest Group 3: Documentation\n";
echo "------------------------------------------------\n";

$docs = [
    'docs/ops/backup_restore.md',
    'docs/ops/disaster_recovery.md',
    'docs/ops/monitoring/alerts.yml',
];

foreach ($docs as $doc) {
    testAssert(
        file_exists($doc),
        "Documentation exists: $doc"
    );
}

// Test 4: Verify documentation content
echo "\nTest Group 4: Documentation Content\n";
echo "------------------------------------------------\n";

$backupRestoreDoc = file_get_contents('docs/ops/backup_restore.md');
testAssert(
    strpos($backupRestoreDoc, 'backup_all.sh') !== false,
    "Backup restore guide mentions backup_all.sh"
);
testAssert(
    strpos($backupRestoreDoc, 'RPO') !== false && strpos($backupRestoreDoc, 'RTO') !== false,
    "Backup restore guide includes RPO/RTO definitions"
);
testAssert(
    strpos($backupRestoreDoc, 'Off-Site') !== false || strpos($backupRestoreDoc, 'off-site') !== false,
    "Backup restore guide includes off-site backup procedures"
);

$drDoc = file_get_contents('docs/ops/disaster_recovery.md');
testAssert(
    strpos($drDoc, 'Recovery Point Objective') !== false,
    "DR runbook includes RPO definition"
);
testAssert(
    strpos($drDoc, 'Recovery Time Objective') !== false,
    "DR runbook includes RTO definition"
);
testAssert(
    strpos($drDoc, 'Complete System Failure') !== false,
    "DR runbook includes complete system failure scenario"
);
testAssert(
    strpos($drDoc, 'Database Corruption') !== false,
    "DR runbook includes database corruption scenario"
);
testAssert(
    strpos($drDoc, 'Quarterly') !== false || strpos($drDoc, 'quarterly') !== false,
    "DR runbook includes quarterly DR drill procedures"
);

// Test 5: Test database backup script (dry run)
echo "\nTest Group 5: Database Backup Script\n";
echo "------------------------------------------------\n";

// Set up environment for backup test
putenv("BACKUP_DIR=$testBackupDir");
putenv("RETENTION_DAYS=7");
putenv("ADMIN_DB_TYPE=sqlite");
putenv("ADMIN_DB_PATH=$testDbPath");

$backupOutput = shell_exec("./scripts/db_backup.sh 2>&1");
testAssert(
    $backupOutput !== null,
    "Database backup script executes"
);

// Check if backup was created
$backupFiles = glob("$testBackupDir/admin_*.db.gz");
testAssert(
    count($backupFiles) > 0,
    "Database backup file created"
);

if (count($backupFiles) > 0) {
    $backupFile = $backupFiles[0];
    testAssert(
        file_exists($backupFile),
        "Backup file exists: " . basename($backupFile)
    );
    
    testAssert(
        filesize($backupFile) > 0,
        "Backup file has content"
    );
}

// Test 6: Test full backup script
echo "\nTest Group 6: Full Backup Script\n";
echo "------------------------------------------------\n";

$fullBackupOutput = shell_exec("cd " . escapeshellarg($testDir) . " && " . getcwd() . "/scripts/backup_all.sh 2>&1");
testAssert(
    $fullBackupOutput !== null,
    "Full backup script executes"
);

// Check if full backup was created
$fullBackupFiles = glob("$testBackupDir/full_backup_*.tar.gz");
testAssert(
    count($fullBackupFiles) > 0,
    "Full backup archive created"
);

if (count($fullBackupFiles) > 0) {
    $fullBackupFile = $fullBackupFiles[0];
    
    // Test archive integrity
    exec("tar -tzf " . escapeshellarg($fullBackupFile) . " > /dev/null 2>&1", $output, $returnCode);
    testAssert(
        $returnCode === 0,
        "Full backup archive is valid"
    );
    
    // Check for manifest
    exec("tar -tzf " . escapeshellarg($fullBackupFile) . " | grep MANIFEST.txt", $manifestOutput);
    testAssert(
        count($manifestOutput) > 0,
        "Full backup includes manifest"
    );
}

// Test 7: Test backup monitoring script
echo "\nTest Group 7: Backup Monitoring\n";
echo "------------------------------------------------\n";

$monitorOutput = shell_exec("BACKUP_DIR=$testBackupDir ./scripts/monitor_backups.sh 2>&1");
testAssert(
    $monitorOutput !== null,
    "Backup monitoring script executes"
);

testAssert(
    strpos($monitorOutput, 'Backup Monitoring Report') !== false,
    "Monitoring script produces report"
);

// Test 8: Test restore script (with safety checks)
echo "\nTest Group 8: Restore Functionality\n";
echo "------------------------------------------------\n";

if (count($backupFiles) > 0) {
    // Test database restore (dry run - just check script logic)
    $restoreScript = file_get_contents('scripts/db_restore.sh');
    testAssert(
        strpos($restoreScript, 'WARNING') !== false,
        "Restore script includes safety warning"
    );
    
    testAssert(
        strpos($restoreScript, 'before_restore') !== false,
        "Restore script creates safety backup"
    );
}

// Test 9: Test restore testing script
echo "\nTest Group 9: Restore Testing\n";
echo "------------------------------------------------\n";

if (count($fullBackupFiles) > 0) {
    $testRestoreOutput = shell_exec("./scripts/test_restore.sh --backup-file " . escapeshellarg($fullBackupFiles[0]) . " 2>&1");
    testAssert(
        $testRestoreOutput !== null,
        "Restore testing script executes"
    );
    
    testAssert(
        strpos($testRestoreOutput, 'Test Summary') !== false,
        "Restore testing produces summary report"
    );
}

// Test 10: Verify Prometheus alerts
echo "\nTest Group 10: Monitoring Alerts\n";
echo "------------------------------------------------\n";

$alertsYml = file_get_contents('docs/ops/monitoring/alerts.yml');
testAssert(
    strpos($alertsYml, 'chatbot_backup_alerts') !== false,
    "Prometheus alerts include backup monitoring group"
);

testAssert(
    strpos($alertsYml, 'BackupMissing') !== false,
    "Alerts include BackupMissing rule"
);

testAssert(
    strpos($alertsYml, 'BackupFailed') !== false,
    "Alerts include BackupFailed rule"
);

testAssert(
    strpos($alertsYml, 'BackupStorageLow') !== false,
    "Alerts include storage monitoring"
);

// Test 11: Verify systemd timer configuration
echo "\nTest Group 11: Systemd Configuration\n";
echo "------------------------------------------------\n";

$timerContent = file_get_contents('scripts/chatbot-backup.timer');
testAssert(
    strpos($timerContent, 'OnCalendar') !== false,
    "Backup timer includes schedule"
);

$serviceContent = file_get_contents('scripts/chatbot-backup.service');
testAssert(
    strpos($serviceContent, 'ExecStart') !== false,
    "Backup service includes execution command"
);

testAssert(
    strpos($serviceContent, 'backup_all.sh') !== false,
    "Backup service references backup script"
);

// Test 12: Verify crontab examples
echo "\nTest Group 12: Cron Configuration\n";
echo "------------------------------------------------\n";

$crontabContent = file_get_contents('scripts/backup.crontab');
testAssert(
    strpos($crontabContent, 'backup_all.sh') !== false,
    "Crontab includes full backup schedule"
);

testAssert(
    strpos($crontabContent, 'monitor_backups.sh') !== false,
    "Crontab includes monitoring schedule"
);

testAssert(
    strpos($crontabContent, '--offsite') !== false,
    "Crontab includes off-site backup examples"
);

// Test 13: Verify disaster recovery scenarios
echo "\nTest Group 13: Disaster Recovery Procedures\n";
echo "------------------------------------------------\n";

$scenarios = [
    'Complete System Failure',
    'Database Corruption',
    'Accidental Data Deletion',
    'Configuration Error',
    'Security Breach',
];

foreach ($scenarios as $scenario) {
    testAssert(
        strpos($drDoc, $scenario) !== false,
        "DR runbook includes scenario: $scenario"
    );
}

// Test 14: Verify RPO/RTO definitions
echo "\nTest Group 14: Recovery Objectives\n";
echo "------------------------------------------------\n";

testAssert(
    preg_match('/\*\*Database\*\*.*6 hours/s', $drDoc) || preg_match('/Database.*\|.*6 hours/s', $drDoc),
    "Database RPO defined as 6 hours"
);

testAssert(
    preg_match('/Complete System Failure.*4 hours/s', $drDoc) || preg_match('/\*\*Complete System Failure\*\*.*4 hours/s', $drDoc),
    "Complete system failure RTO defined"
);

// Test 15: Verify off-site backup documentation
echo "\nTest Group 15: Off-Site Backup Procedures\n";
echo "------------------------------------------------\n";

$offsiteMethods = ['rsync', 'S3', 'Azure', 'Google Cloud'];
foreach ($offsiteMethods as $method) {
    testAssert(
        stripos($backupRestoreDoc, $method) !== false,
        "Documentation includes off-site method: $method",
        false // non-critical
    );
}

testAssert(
    strpos($backupRestoreDoc, 'OFFSITE_DESTINATION') !== false,
    "Documentation includes off-site configuration"
);

// Cleanup
echo "\nCleaning up test environment...\n";
cleanupTestEnvironment();
echo "✓ Cleanup complete\n";

// Summary
echo "\n===========================================\n";
echo "Test Summary\n";
echo "===========================================\n";
echo "Passed:   " . $tests['passed'] . "\n";
echo "Failed:   " . $tests['failed'] . "\n";
echo "Warnings: " . $tests['warnings'] . "\n";
echo "\n";

if ($tests['failed'] > 0) {
    echo "❌ FAILED TESTS:\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "✅ All critical tests passed!\n";
    if ($tests['warnings'] > 0) {
        echo "⚠️  Some warnings detected - review output\n";
    }
    echo "\n";
    exit(0);
}
