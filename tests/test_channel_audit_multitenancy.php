#!/usr/bin/env php
<?php
/**
 * Test Suite for Channel and Audit Service Tenant Scoping
 * Tests tenant isolation for ChannelSessionService, ChannelMessageService, and AuditService
 */

echo "=== Channel and Audit Service Tenant Scoping Test Suite ===\n\n";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/TenantService.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/ChannelSessionService.php';
require_once __DIR__ . '/../includes/ChannelMessageService.php';
require_once __DIR__ . '/../includes/AuditService.php';

$testsPassed = 0;
$testsFailed = 0;

function test($name, $condition, $errorMsg = '') {
    global $testsPassed, $testsFailed;
    
    if ($condition) {
        echo "✓ PASS: $name\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $name";
        if ($errorMsg) {
            echo " - $errorMsg";
        }
        echo "\n";
        $testsFailed++;
    }
}

try {
    // Initialize database - force test database path
    $dbConfig = [
        'database_url' => null,
        'database_path' => __DIR__ . '/../data/chatbot_test_channel.db'
    ];
    
    // Safety check: ensure we're using a test database
    if (strpos($dbConfig['database_path'], '_test') === false) {
        echo "ERROR: Database path must contain '_test' for safety. Got: {$dbConfig['database_path']}\n";
        exit(1);
    }
    
    // Clean up test database if it exists
    if (file_exists($dbConfig['database_path'])) {
        unlink($dbConfig['database_path']);
    }
    
    $db = new DB($dbConfig);
    
    // Run migrations
    echo "Setting up test database...\n";
    $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "\n";
    
    // Initialize services
    $tenantService = new TenantService($db);
    $agentService = new AgentService($db);
    
    // ============================================================
    // Setup: Create Tenants and Agents
    // ============================================================
    echo "Setup: Creating Tenants and Agents\n";
    
    $tenant1 = $tenantService->createTenant([
        'name' => 'Tenant Alpha',
        'slug' => 'alpha',
        'status' => 'active'
    ]);
    
    $tenant2 = $tenantService->createTenant([
        'name' => 'Tenant Beta',
        'slug' => 'beta',
        'status' => 'active'
    ]);
    
    // Create agents for each tenant
    $agentService->setTenantId($tenant1['id']);
    $agent1 = $agentService->createAgent([
        'name' => 'Alpha Support Bot',
        'api_type' => 'chat',
        'model' => 'gpt-4o-mini'
    ]);
    
    $agentService->setTenantId($tenant2['id']);
    $agent2 = $agentService->createAgent([
        'name' => 'Beta Support Bot',
        'api_type' => 'chat',
        'model' => 'gpt-4o-mini'
    ]);
    
    echo "✓ Created 2 tenants and 2 agents\n\n";
    
    // ============================================================
    // Test 1: ChannelSessionService Tenant Isolation
    // ============================================================
    echo "Test Suite 1: ChannelSessionService Tenant Isolation\n";
    
    $sessionService1 = new ChannelSessionService($db, $tenant1['id']);
    $sessionService2 = new ChannelSessionService($db, $tenant2['id']);
    
    // Create session for tenant 1
    $session1 = $sessionService1->getOrCreateSession(
        $agent1['id'],
        'whatsapp',
        '+5511999999999'
    );
    
    test('Create session for tenant 1', isset($session1['id']));
    test('Session 1 is new', $session1['is_new'] === true);
    
    // Create session for tenant 2
    $session2 = $sessionService2->getOrCreateSession(
        $agent2['id'],
        'whatsapp',
        '+5511888888888'
    );
    
    test('Create session for tenant 2', isset($session2['id']));
    
    // Try to get tenant 1's session using tenant 2's service (should not find)
    $session1Conv = $session1['conversation_id'];
    $fetchedSession = $sessionService2->getSessionByConversationId($session1Conv);
    test('Tenant 2 cannot access tenant 1 session', $fetchedSession === null);
    
    // Tenant 1 should be able to access their own session
    $fetchedSession = $sessionService1->getSessionByConversationId($session1Conv);
    test('Tenant 1 can access their own session', $fetchedSession !== null);
    
    // List sessions should be tenant-scoped
    $sessions1 = $sessionService1->listSessions($agent1['id']);
    test('Tenant 1 sees only their sessions', count($sessions1) === 1);
    
    $sessions2 = $sessionService2->listSessions($agent2['id']);
    test('Tenant 2 sees only their sessions', count($sessions2) === 1);
    
    echo "\n";
    
    // ============================================================
    // Test 2: ChannelMessageService Tenant Isolation
    // ============================================================
    echo "Test Suite 2: ChannelMessageService Tenant Isolation\n";
    
    $messageService1 = new ChannelMessageService($db, $tenant1['id']);
    $messageService2 = new ChannelMessageService($db, $tenant2['id']);
    
    // Record inbound message for tenant 1
    $msg1 = $messageService1->recordInbound(
        $agent1['id'],
        'whatsapp',
        '+5511999999999',
        $session1['conversation_id'],
        'msg_ext_001',
        ['text' => 'Hello from tenant 1']
    );
    
    test('Record message for tenant 1', !empty($msg1));
    
    // Record inbound message for tenant 2
    $msg2 = $messageService2->recordInbound(
        $agent2['id'],
        'whatsapp',
        '+5511888888888',
        $session2['conversation_id'],
        'msg_ext_002',
        ['text' => 'Hello from tenant 2']
    );
    
    test('Record message for tenant 2', !empty($msg2));
    
    // Get messages should be tenant-scoped
    $messages1 = $messageService1->getMessages($session1['conversation_id']);
    test('Tenant 1 sees only their messages', count($messages1) === 1);
    
    $messages2 = $messageService2->getMessages($session2['conversation_id']);
    test('Tenant 2 sees only their messages', count($messages2) === 1);
    
    // Tenant 2 should not see tenant 1's messages
    $messages1From2 = $messageService2->getMessages($session1['conversation_id']);
    test('Tenant 2 cannot see tenant 1 messages', count($messages1From2) === 0);
    
    // Get stats should be tenant-scoped
    $stats1 = $messageService1->getStats($agent1['id']);
    test('Tenant 1 stats shows 1 message', $stats1['total'] === 1);
    
    $stats2 = $messageService2->getStats($agent2['id']);
    test('Tenant 2 stats shows 1 message', $stats2['total'] === 1);
    
    echo "\n";
    
    // ============================================================
    // Test 3: AuditService Tenant Isolation
    // ============================================================
    echo "Test Suite 3: AuditService Tenant Isolation\n";
    
    $auditConfig = [
        'enabled' => true,
        'encrypt_at_rest' => false,
        'database_path' => $dbConfig['database_path']
    ];
    
    $auditService1 = new AuditService($auditConfig, $tenant1['id']);
    $auditService2 = new AuditService($auditConfig, $tenant2['id']);
    
    // Start conversation for tenant 1
    $auditConv1 = $auditService1->startConversation(
        $agent1['id'],
        'web',
        'conv_001',
        'user_fingerprint_1',
        ['source' => 'website']
    );
    
    test('Start audit conversation for tenant 1', !empty($auditConv1));
    
    // Start conversation for tenant 2
    $auditConv2 = $auditService2->startConversation(
        $agent2['id'],
        'web',
        'conv_002',
        'user_fingerprint_2',
        ['source' => 'website']
    );
    
    test('Start audit conversation for tenant 2', !empty($auditConv2));
    
    // Verify tenant_id is set correctly in database
    $checkSql1 = "SELECT tenant_id FROM audit_conversations WHERE id = ?";
    $result1 = $db->getOne($checkSql1, [$auditConv1]);
    test('Audit conversation 1 has correct tenant_id', $result1['tenant_id'] === $tenant1['id']);
    
    $result2 = $db->getOne($checkSql1, [$auditConv2]);
    test('Audit conversation 2 has correct tenant_id', $result2['tenant_id'] === $tenant2['id']);
    
    echo "\n";
    
    // ============================================================
    // Test 4: Cross-Tenant Isolation Verification
    // ============================================================
    echo "Test Suite 4: Cross-Tenant Isolation Verification\n";
    
    // Verify that queries are properly filtered by tenant_id
    
    // Count sessions per tenant
    $sql = "SELECT tenant_id, COUNT(*) as count FROM channel_sessions GROUP BY tenant_id";
    $sessionCounts = $db->query($sql);
    test('2 tenants have sessions', count($sessionCounts) === 2);
    
    // Count messages per tenant
    $sql = "SELECT tenant_id, COUNT(*) as count FROM channel_messages GROUP BY tenant_id";
    $messageCounts = $db->query($sql);
    test('2 tenants have messages', count($messageCounts) === 2);
    
    // Count audit conversations per tenant
    $sql = "SELECT tenant_id, COUNT(*) as count FROM audit_conversations GROUP BY tenant_id";
    $auditCounts = $db->query($sql);
    test('2 tenants have audit conversations', count($auditCounts) === 2);
    
    // Verify no cross-contamination
    foreach ($sessionCounts as $count) {
        test('Each tenant has exactly 1 session', (int)$count['count'] === 1);
    }
    
    echo "\n";
    
    // ============================================================
    // Summary
    // ============================================================
    echo "=== Test Summary ===\n";
    echo "Passed: $testsPassed\n";
    echo "Failed: $testsFailed\n";
    
    if ($testsFailed === 0) {
        echo "\n✓ All tests passed!\n";
        exit(0);
    } else {
        echo "\n✗ Some tests failed.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
