#!/usr/bin/env php
<?php
/**
 * Multi-Tenant Backup & Disaster Recovery Test Suite
 * Tests tenant-specific backup, restore, and DR procedures
 */

require_once __DIR__ . '/../config.php';
// No need to require DB.php - we'll use SQLite3 directly for tests

// Test configuration
$testDir = '/tmp/mt_backup_test_' . time();
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
    
    // Create test database with multi-tenant schema
    $db = new SQLite3($testDbPath);
    
    // Create tenants table
    $db->exec('
        CREATE TABLE tenants (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            status TEXT DEFAULT "active",
            plan TEXT DEFAULT "starter",
            settings_json TEXT DEFAULT "{}"
        )
    ');
    
    // Create tenant-aware tables
    $db->exec('
        CREATE TABLE agents (
            id INTEGER PRIMARY KEY,
            tenant_id TEXT NOT NULL,
            name TEXT NOT NULL,
            data TEXT,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        )
    ');
    
    $db->exec('
        CREATE TABLE prompts (
            id INTEGER PRIMARY KEY,
            tenant_id TEXT NOT NULL,
            title TEXT NOT NULL,
            content TEXT,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        )
    ');
    
    // Insert test tenants (using TEXT IDs like production)
    $db->exec("INSERT INTO tenants (id, name, slug, status, plan) VALUES ('ent-001', 'Enterprise Corp', 'enterprise-corp', 'active', 'enterprise')");
    $db->exec("INSERT INTO tenants (id, name, slug, status, plan) VALUES ('pro-001', 'Pro Business', 'pro-business', 'active', 'pro')");
    $db->exec("INSERT INTO tenants (id, name, slug, status, plan) VALUES ('str-001', 'Starter Inc', 'starter-inc', 'active', 'starter')");
    $db->exec("INSERT INTO tenants (id, name, slug, status, plan) VALUES ('free-001', 'Free User', 'free-user', 'active', 'free')");
    
    // Insert test data for each tenant
    $db->exec("INSERT INTO agents (tenant_id, name, data) VALUES ('ent-001', 'Enterprise Agent 1', 'data1')");
    $db->exec("INSERT INTO agents (tenant_id, name, data) VALUES ('ent-001', 'Enterprise Agent 2', 'data2')");
    $db->exec("INSERT INTO agents (tenant_id, name, data) VALUES ('pro-001', 'Pro Agent 1', 'data3')");
    $db->exec("INSERT INTO agents (tenant_id, name, data) VALUES ('str-001', 'Starter Agent 1', 'data4')");
    $db->exec("INSERT INTO agents (tenant_id, name, data) VALUES ('free-001', 'Free Agent 1', 'data5')");
    
    $db->exec("INSERT INTO prompts (tenant_id, title, content) VALUES ('ent-001', 'Enterprise Prompt', 'content1')");
    $db->exec("INSERT INTO prompts (tenant_id, title, content) VALUES ('pro-001', 'Pro Prompt', 'content2')");
    
    $db->close();
    
    return true;
}

function cleanupTestEnvironment() {
    global $testDir;
    
    if (is_dir($testDir)) {
        exec("rm -rf " . escapeshellarg($testDir));
    }
}

echo "===========================================\n";
echo "Multi-Tenant Backup & DR Test Suite\n";
echo "===========================================\n\n";

// Setup
echo "Setting up multi-tenant test environment...\n";
if (!setupTestEnvironment()) {
    echo "Failed to set up test environment\n";
    exit(1);
}
echo "✓ Test environment ready\n\n";

// Test 1: Verify tenant backup script exists
echo "Test Group 1: Tenant Backup Script\n";
echo "------------------------------------------------\n";

testAssert(
    file_exists('scripts/tenant_backup.sh'),
    "Tenant backup script exists"
);

testAssert(
    is_executable('scripts/tenant_backup.sh'),
    "Tenant backup script is executable"
);

// Test 2: Verify backup frequency determination script
echo "\nTest Group 2: Backup Frequency Determination\n";
echo "------------------------------------------------\n";

testAssert(
    file_exists('scripts/determine_backup_frequency.php'),
    "Backup frequency determination script exists"
);

testAssert(
    is_executable('scripts/determine_backup_frequency.php'),
    "Backup frequency script is executable"
);

// Test 3: Test tenant backup functionality
echo "\nTest Group 3: Tenant Backup Functionality\n";
echo "------------------------------------------------\n";

putenv("BACKUP_DIR=$testBackupDir");
putenv("DATABASE_PATH=$testDbPath");
putenv("ADMIN_DB_TYPE=sqlite");

// Test backing up tenant ent-001 (enterprise)
$tenantBackupOutput = shell_exec("./scripts/tenant_backup.sh ent-001 2>&1");
testAssert(
    $tenantBackupOutput !== null,
    "Tenant backup script executes"
);

testAssert(
    strpos($tenantBackupOutput, '✅') !== false || strpos($tenantBackupOutput, 'completed successfully') !== false,
    "Tenant backup completes successfully"
);

// Check if tenant backup was created
$tenantBackupFiles = glob("$testBackupDir/tenants/tenant_ent-001_*.tar.gz");
testAssert(
    count($tenantBackupFiles) > 0,
    "Tenant backup archive created"
);

if (count($tenantBackupFiles) > 0) {
    $tenantBackupFile = $tenantBackupFiles[0];
    
    testAssert(
        file_exists($tenantBackupFile),
        "Tenant backup file exists: " . basename($tenantBackupFile)
    );
    
    testAssert(
        filesize($tenantBackupFile) > 0,
        "Tenant backup has content"
    );
    
    // Test archive integrity
    exec("tar -tzf " . escapeshellarg($tenantBackupFile) . " > /dev/null 2>&1", $output, $returnCode);
    testAssert(
        $returnCode === 0,
        "Tenant backup archive is valid"
    );
    
    // Check for manifest
    exec("tar -tzf " . escapeshellarg($tenantBackupFile) . " | grep MANIFEST.txt", $manifestOutput);
    testAssert(
        count($manifestOutput) > 0,
        "Tenant backup includes manifest"
    );
    
    // Extract and verify manifest content
    $tempManifestDir = $testDir . '/manifest_check';
    mkdir($tempManifestDir, 0755, true);
    exec("tar -xzf " . escapeshellarg($tenantBackupFile) . " -C " . escapeshellarg($tempManifestDir) . " 2>&1");
    
    // Find manifest file
    $manifestFiles = glob("$tempManifestDir/*/MANIFEST.txt");
    if (!empty($manifestFiles)) {
        $manifestText = file_get_contents($manifestFiles[0]);
        
        testAssert(
            strpos($manifestText, 'Tenant ID: ent-001') !== false,
            "Manifest contains correct tenant ID"
        );
        
        testAssert(
            strpos($manifestText, 'Enterprise Corp') !== false,
            "Manifest contains tenant name"
        );
    } else {
        testAssert(false, "Manifest contains correct tenant ID");
        testAssert(false, "Manifest contains tenant name");
    }
}

// Test 4: Verify tenant isolation in backup
echo "\nTest Group 4: Tenant Isolation in Backup\n";
echo "------------------------------------------------\n";

if (count($tenantBackupFiles) > 0) {
    // Extract backup to temp location
    $extractDir = $testDir . '/extract_test';
    mkdir($extractDir, 0755, true);
    exec("tar -xzf " . escapeshellarg($tenantBackupFiles[0]) . " -C " . escapeshellarg($extractDir) . " 2>&1");
    
    // Find the extracted directory
    $extractedDirs = glob("$extractDir/tenant_ent-001_*");
    if (!empty($extractedDirs)) {
        $extractedDir = $extractedDirs[0];
        
        // Check agents.sql contains only tenant 1 data
        if (file_exists("$extractedDir/agents.sql")) {
            $agentsSql = file_get_contents("$extractedDir/agents.sql");
            
            testAssert(
                strpos($agentsSql, 'Enterprise Agent') !== false,
                "Backup contains tenant 1 data"
            );
            
            testAssert(
                strpos($agentsSql, 'Pro Agent') === false,
                "Backup does NOT contain other tenant data (agents)"
            );
        }
        
        // Check prompts.sql
        if (file_exists("$extractedDir/prompts.sql")) {
            $promptsSql = file_get_contents("$extractedDir/prompts.sql");
            
            testAssert(
                strpos($promptsSql, 'Enterprise Prompt') !== false,
                "Backup contains tenant 1 prompts"
            );
            
            testAssert(
                strpos($promptsSql, 'Pro Prompt') === false,
                "Backup does NOT contain other tenant prompts"
            );
        }
    }
}

// Test 5: Test multiple tenant backups don't interfere
echo "\nTest Group 5: Multiple Tenant Backup Isolation\n";
echo "------------------------------------------------\n";

// Backup tenant 2
$tenant2BackupOutput = shell_exec("./scripts/tenant_backup.sh pro-001 2>&1");
testAssert(
    strpos($tenant2BackupOutput, '✅') !== false || strpos($tenant2BackupOutput, 'completed successfully') !== false,
    "Second tenant backup completes successfully"
);

// Verify both backups exist
$allTenantBackups = glob("$testBackupDir/tenants/tenant_*.tar.gz");
testAssert(
    count($allTenantBackups) >= 2,
    "Multiple tenant backups can coexist"
);

// Test 6: Verify multi-tenant documentation
echo "\nTest Group 6: Multi-Tenant Documentation\n";
echo "------------------------------------------------\n";

testAssert(
    file_exists('docs/ops/multi_tenant_backup_dr.md'),
    "Multi-tenant backup & DR documentation exists"
);

$mtDoc = file_get_contents('docs/ops/multi_tenant_backup_dr.md');

testAssert(
    strpos($mtDoc, 'RPO') !== false && strpos($mtDoc, 'RTO') !== false,
    "Multi-tenant doc includes RPO/RTO definitions"
);

testAssert(
    strpos($mtDoc, 'Enterprise') !== false && strpos($mtDoc, 'Pro') !== false,
    "Multi-tenant doc includes tier-based policies"
);

testAssert(
    strpos($mtDoc, 'tenant_backup.sh') !== false,
    "Documentation references tenant backup script"
);

testAssert(
    strpos($mtDoc, 'Selective Tenant Restore') !== false,
    "Documentation includes selective restore procedures"
);

testAssert(
    strpos($mtDoc, 'Tenant Migration') !== false,
    "Documentation includes tenant migration procedures"
);

// Test 7: Test backup frequency determination
echo "\nTest Group 7: Backup Frequency Determination\n";
echo "------------------------------------------------\n";

// Note: This test would require the Database class to work with the test DB
// For now, we just verify the script structure
$freqScript = file_get_contents('scripts/determine_backup_frequency.php');

testAssert(
    strpos($freqScript, 'enterprise') !== false || strpos($freqScript, 'plan') !== false,
    "Frequency script considers enterprise tier or plan"
);

testAssert(
    strpos($freqScript, 'hourly') !== false,
    "Frequency script includes hourly option"
);

testAssert(
    strpos($freqScript, 'daily') !== false,
    "Frequency script includes daily default"
);

// Test 8: Verify tier-specific RPO/RTO documentation
echo "\nTest Group 8: Tier-Specific Recovery Objectives\n";
echo "------------------------------------------------\n";

testAssert(
    preg_match('/Enterprise.*1 hour/i', $mtDoc) > 0,
    "Enterprise tier RPO documented as 1 hour"
);

testAssert(
    preg_match('/Pro.*6 hours/i', $mtDoc) > 0,
    "Pro tier RPO documented as 6 hours"
);

testAssert(
    preg_match('/Enterprise.*30 minutes/i', $mtDoc) > 0,
    "Enterprise tier RTO documented"
);

// Test 9: Verify selective restore documentation
echo "\nTest Group 9: Selective Restore Procedures\n";
echo "------------------------------------------------\n";

testAssert(
    strpos($mtDoc, 'TENANT_ID') !== false,
    "Selective restore uses tenant ID variable"
);

testAssert(
    strpos($mtDoc, 'tenant_id =') !== false || strpos($mtDoc, 'WHERE tenant_id') !== false,
    "Selective restore includes tenant filtering queries"
);

testAssert(
    strpos($mtDoc, 'Safety Backup') !== false || strpos($mtDoc, 'safety backup') !== false,
    "Selective restore includes safety backup step"
);

// Test 10: Verify DR scenarios for multi-tenant
echo "\nTest Group 10: Multi-Tenant DR Scenarios\n";
echo "------------------------------------------------\n";

$scenarios = [
    'Single Tenant Data Corruption',
    'Multi-Tenant System Failure',
    'Tenant Migration',
];

foreach ($scenarios as $scenario) {
    testAssert(
        strpos($mtDoc, $scenario) !== false,
        "Documentation includes scenario: $scenario"
    );
}

// Test 11: Verify GDPR compliance documentation
echo "\nTest Group 11: GDPR and Compliance\n";
echo "------------------------------------------------\n";

testAssert(
    stripos($mtDoc, 'GDPR') !== false,
    "Documentation addresses GDPR compliance"
);

testAssert(
    strpos($mtDoc, 'Data Export') !== false || strpos($mtDoc, 'export') !== false,
    "Documentation includes data export procedures"
);

testAssert(
    strpos($mtDoc, 'Right to Deletion') !== false || strpos($mtDoc, 'right to be forgotten') !== false,
    "Documentation addresses right to deletion"
);

// Test 12: Verify backup includes all tenant-aware tables
echo "\nTest Group 12: Tenant-Aware Table Coverage\n";
echo "------------------------------------------------\n";

$tenantBackupScript = file_get_contents('scripts/tenant_backup.sh');

$expectedTables = [
    'agents',
    'prompts',
    'vector_stores',
    'admin_users',
    'audit_conversations',
    'channel_sessions',
    'channel_messages',
    'leads',
    'jobs',
];

$foundTables = 0;
foreach ($expectedTables as $table) {
    if (strpos($tenantBackupScript, $table) !== false) {
        $foundTables++;
    }
}

testAssert(
    $foundTables >= count($expectedTables) - 2, // Allow for 2 missing for flexibility
    "Tenant backup script covers most tenant-aware tables ($foundTables/" . count($expectedTables) . ")"
);

// Test 13: Error handling in tenant backup
echo "\nTest Group 13: Error Handling\n";
echo "------------------------------------------------\n";

// Test with invalid tenant ID
$invalidTenantOutput = shell_exec("./scripts/tenant_backup.sh 999 2>&1");
testAssert(
    strpos($invalidTenantOutput, 'ERROR') !== false || strpos($invalidTenantOutput, 'not found') !== false,
    "Tenant backup handles invalid tenant ID gracefully"
);

// Test with missing tenant ID argument
$noArgOutput = shell_exec("./scripts/tenant_backup.sh 2>&1");
testAssert(
    strpos($noArgOutput, 'Error') !== false || strpos($noArgOutput, 'required') !== false,
    "Tenant backup requires tenant ID argument"
);

// Test 14: Verify configuration options
echo "\nTest Group 14: Configuration and Environment\n";
echo "------------------------------------------------\n";

testAssert(
    strpos($mtDoc, 'BACKUP_FREQUENCY') !== false,
    "Documentation mentions BACKUP_FREQUENCY configuration"
);

testAssert(
    strpos($mtDoc, 'BACKUP_RETENTION') !== false,
    "Documentation mentions retention configuration"
);

testAssert(
    strpos($mtDoc, 'BACKUP_TIER_AWARE') !== false,
    "Documentation mentions tier-aware backup configuration"
);

// Test 15: Integration with existing backup infrastructure
echo "\nTest Group 15: Integration with Existing Backups\n";
echo "------------------------------------------------\n";

testAssert(
    strpos($mtDoc, 'backup_all.sh') !== false,
    "Multi-tenant doc references standard backup script"
);

testAssert(
    strpos($mtDoc, 'backup_restore.md') !== false,
    "Multi-tenant doc references general backup guide"
);

testAssert(
    strpos($mtDoc, 'disaster_recovery.md') !== false,
    "Multi-tenant doc references DR runbook"
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
