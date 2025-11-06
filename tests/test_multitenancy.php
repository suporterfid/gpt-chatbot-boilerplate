#!/usr/bin/env php
<?php
/**
 * Multi-Tenancy Test Suite
 * Tests tenant isolation and multi-tenancy features
 */

echo "=== Multi-Tenancy Test Suite ===\n\n";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/TenantService.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

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
    // Initialize database
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot_test.db'
    ];
    
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
    $adminAuth = new AdminAuth($db, $config);
    
    // ============================================================
    // Test 1: Create Tenants
    // ============================================================
    echo "Test Suite 1: Tenant Management\n";
    
    $tenant1 = $tenantService->createTenant([
        'name' => 'Acme Corp',
        'slug' => 'acme',
        'status' => 'active',
        'billing_email' => 'billing@acme.com'
    ]);
    
    test('Create tenant 1', isset($tenant1['id']), 'Tenant ID not set');
    test('Tenant 1 slug is correct', $tenant1['slug'] === 'acme', 'Expected slug: acme, got: ' . $tenant1['slug']);
    
    $tenant2 = $tenantService->createTenant([
        'name' => 'Beta Inc',
        'slug' => 'beta',
        'status' => 'active'
    ]);
    
    test('Create tenant 2', isset($tenant2['id']), 'Tenant ID not set');
    
    // Test getting tenant by ID
    $fetchedTenant = $tenantService->getTenant($tenant1['id']);
    test('Get tenant by ID', $fetchedTenant['name'] === 'Acme Corp');
    
    // Test getting tenant by slug
    $fetchedBySlug = $tenantService->getTenantBySlug('beta');
    test('Get tenant by slug', $fetchedBySlug['name'] === 'Beta Inc');
    
    // Test list tenants
    $allTenants = $tenantService->listTenants();
    test('List all tenants', count($allTenants) === 2, 'Expected 2 tenants, got: ' . count($allTenants));
    
    echo "\n";
    
    // ============================================================
    // Test 2: Tenant-Scoped Agents
    // ============================================================
    echo "Test Suite 2: Tenant-Scoped Agents\n";
    
    // Create agents for tenant 1
    $agentService->setTenantId($tenant1['id']);
    
    $agent1 = $agentService->createAgent([
        'name' => 'Acme Support Bot',
        'api_type' => 'chat',
        'model' => 'gpt-4o-mini'
    ]);
    
    test('Create agent for tenant 1', isset($agent1['id']));
    test('Agent 1 has correct tenant_id', $agent1['tenant_id'] === $tenant1['id']);
    
    $agent2 = $agentService->createAgent([
        'name' => 'Acme Sales Bot',
        'api_type' => 'responses',
        'model' => 'gpt-4o'
    ]);
    
    test('Create second agent for tenant 1', isset($agent2['id']));
    
    // Create agents for tenant 2
    $agentService->setTenantId($tenant2['id']);
    
    $agent3 = $agentService->createAgent([
        'name' => 'Beta Support Bot',
        'api_type' => 'chat',
        'model' => 'gpt-4o-mini'
    ]);
    
    test('Create agent for tenant 2', isset($agent3['id']));
    test('Agent 3 has correct tenant_id', $agent3['tenant_id'] === $tenant2['id']);
    
    // Test tenant isolation - tenant 1 should only see their agents
    $agentService->setTenantId($tenant1['id']);
    $tenant1Agents = $agentService->listAgents();
    test('Tenant 1 sees only their agents', count($tenant1Agents) === 2, 'Expected 2 agents, got: ' . count($tenant1Agents));
    
    // Test tenant isolation - tenant 2 should only see their agents
    $agentService->setTenantId($tenant2['id']);
    $tenant2Agents = $agentService->listAgents();
    test('Tenant 2 sees only their agents', count($tenant2Agents) === 1, 'Expected 1 agent, got: ' . count($tenant2Agents));
    
    // Test that tenant 1 cannot access tenant 2's agents
    $agentService->setTenantId($tenant1['id']);
    $fetchedAgent = $agentService->getAgent($agent3['id']);
    test('Tenant 1 cannot access tenant 2 agent', $fetchedAgent === null, 'Should not be able to access other tenant\'s agent');
    
    echo "\n";
    
    // ============================================================
    // Test 3: Admin Users and Tenant Assignment
    // ============================================================
    echo "Test Suite 3: Admin Users and Tenant Assignment\n";
    
    // Create tenant-specific admin
    $admin1 = $adminAuth->createUser('admin@acme.com', 'password123', 'admin', $tenant1['id']);
    test('Create tenant-specific admin', isset($admin1['id']));
    test('Admin has correct tenant_id', $admin1['tenant_id'] === $tenant1['id']);
    
    // Try to create super-admin with tenant_id (should fail)
    $superAdminFailed = false;
    try {
        $adminAuth->createUser('super@example.com', 'password123', 'super-admin', $tenant1['id']);
    } catch (Exception $e) {
        $superAdminFailed = true;
    }
    test('Super-admin cannot have tenant_id', $superAdminFailed);
    
    // Create super-admin without tenant_id (should succeed)
    $superAdmin = $adminAuth->createUser('super@example.com', 'password123', 'super-admin', null);
    test('Create super-admin without tenant_id', isset($superAdmin['id']));
    test('Super-admin has null tenant_id', $superAdmin['tenant_id'] === null);
    
    echo "\n";
    
    // ============================================================
    // Test 4: Tenant Status Management
    // ============================================================
    echo "Test Suite 4: Tenant Status Management\n";
    
    // Suspend tenant
    $suspended = $tenantService->suspendTenant($tenant1['id']);
    test('Suspend tenant', $suspended['status'] === 'suspended');
    
    // Activate tenant
    $activated = $tenantService->activateTenant($tenant1['id']);
    test('Activate tenant', $activated['status'] === 'active');
    
    // Get tenant stats
    $stats = $tenantService->getTenantStats($tenant1['id']);
    test('Get tenant stats', isset($stats['agents']));
    test('Tenant stats show correct agent count', $stats['agents'] === 2, 'Expected 2 agents, got: ' . $stats['agents']);
    
    echo "\n";
    
    // ============================================================
    // Test 5: Unique Constraints and Validation
    // ============================================================
    echo "Test Suite 5: Validation and Constraints\n";
    
    // Try to create tenant with duplicate slug
    $duplicateFailed = false;
    try {
        $tenantService->createTenant([
            'name' => 'Duplicate',
            'slug' => 'acme'
        ]);
    } catch (Exception $e) {
        $duplicateFailed = true;
    }
    test('Duplicate slug prevention', $duplicateFailed);
    
    // Try to create tenant with invalid slug
    $invalidSlugFailed = false;
    try {
        $tenantService->createTenant([
            'name' => 'Invalid',
            'slug' => 'Invalid Slug!'
        ]);
    } catch (Exception $e) {
        $invalidSlugFailed = true;
    }
    test('Invalid slug validation', $invalidSlugFailed);
    
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
