#!/usr/bin/env php
<?php
/**
 * Admin API Integration Test for Resource Authorization
 * Tests the admin-api.php endpoints with resource-level authorization
 */

echo "=== Admin API Resource Authorization Integration Test ===\n\n";

// Test configuration
$baseUrl = 'http://localhost'; // Will be simulated via direct PHP inclusion

// Set up test environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Test Client';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AdminAuth.php';
require_once __DIR__ . '/../includes/ResourceAuthService.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/TenantService.php';

// Initialize test database
$dbConfig = [
    'database_path' => '/tmp/test_admin_api_resource_' . time() . '.db'
];

$db = new DB($dbConfig);
$db->runMigrations(__DIR__ . '/../db/migrations');

$config['admin']['database_path'] = $dbConfig['database_path'];
$adminAuth = new AdminAuth($db, $config);
$tenantService = new TenantService($db);

echo "✓ Test environment initialized\n\n";

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
    // ============================================================
    // Setup: Create Tenants and Users
    // ============================================================
    echo "--- Setup: Create Test Data ---\n";
    
    $tenant1 = $tenantService->createTenant([
        'name' => 'Company A',
        'slug' => 'company-a'
    ]);
    
    $tenant2 = $tenantService->createTenant([
        'name' => 'Company B',
        'slug' => 'company-b'
    ]);
    
    $adminA = $adminAuth->createUser('admin@companya.com', 'pass123', AdminAuth::ROLE_ADMIN, $tenant1['id']);
    $adminAKey = $adminAuth->generateApiKey($adminA['id'], 'Admin A Key');
    
    $adminB = $adminAuth->createUser('admin@companyb.com', 'pass123', AdminAuth::ROLE_ADMIN, $tenant2['id']);
    $adminBKey = $adminAuth->generateApiKey($adminB['id'], 'Admin B Key');
    
    $superAdmin = $adminAuth->createUser('super@admin.com', 'pass123', AdminAuth::ROLE_SUPER_ADMIN);
    $superAdminKey = $adminAuth->generateApiKey($superAdmin['id'], 'Super Admin Key');
    
    // Create agents
    $agentService = new AgentService($db, $tenant1['id']);
    $agentA = $agentService->createAgent([
        'name' => 'Agent A',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini'
    ]);
    
    $agentService->setTenantId($tenant2['id']);
    $agentB = $agentService->createAgent([
        'name' => 'Agent B',
        'api_type' => 'chat',
        'model' => 'gpt-4o-mini'
    ]);
    
    echo "✓ Test data created\n\n";
    
    // ============================================================
    // Test 1: Cross-Tenant Access Denial
    // ============================================================
    echo "--- Test 1: Cross-Tenant Access Denial ---\n";
    
    // Simulate Admin A trying to access Agent B (should fail)
    $adminAUser = $adminAuth->authenticate($adminAKey['key']);
    $resourceAuth = new ResourceAuthService($db, $adminAuth, null);
    
    $canAccess = $resourceAuth->canAccessResource(
        $adminAUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentB['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Admin A cannot access Agent B (cross-tenant)', !$canAccess);
    
    try {
        $resourceAuth->requireResourceAccess(
            $adminAUser,
            ResourceAuthService::RESOURCE_AGENT,
            $agentB['id'],
            ResourceAuthService::ACTION_UPDATE
        );
        test('requireResourceAccess throws for cross-tenant', false, 'No exception thrown');
    } catch (Exception $e) {
        test('requireResourceAccess throws for cross-tenant', $e->getCode() === 403);
    }
    
    echo "\n";
    
    // ============================================================
    // Test 2: Same-Tenant Access Allowed
    // ============================================================
    echo "--- Test 2: Same-Tenant Access Allowed ---\n";
    
    $canAccess = $resourceAuth->canAccessResource(
        $adminAUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Admin A can access Agent A (same-tenant)', $canAccess);
    
    $canUpdate = $resourceAuth->canAccessResource(
        $adminAUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id'],
        ResourceAuthService::ACTION_UPDATE
    );
    test('Admin A can update Agent A (same-tenant)', $canUpdate);
    
    echo "\n";
    
    // ============================================================
    // Test 3: Super-Admin Universal Access
    // ============================================================
    echo "--- Test 3: Super-Admin Universal Access ---\n";
    
    $superAdminUser = $adminAuth->authenticate($superAdminKey['key']);
    
    $canAccessA = $resourceAuth->canAccessResource(
        $superAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id'],
        ResourceAuthService::ACTION_READ
    );
    
    $canAccessB = $resourceAuth->canAccessResource(
        $superAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentB['id'],
        ResourceAuthService::ACTION_READ
    );
    
    test('Super-admin can access Agent A', $canAccessA);
    test('Super-admin can access Agent B', $canAccessB);
    
    echo "\n";
    
    // ============================================================
    // Test 4: Grant Explicit Permission
    // ============================================================
    echo "--- Test 4: Grant Explicit Permission ---\n";
    
    // Super-admin grants Admin B access to Agent A
    $permission = $resourceAuth->grantResourcePermission(
        $adminB['id'],
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id'],
        ['read'],
        $superAdmin['id']
    );
    
    test('Permission granted successfully', isset($permission['id']));
    
    // Now Admin B should be able to access Agent A
    $adminBUser = $adminAuth->authenticate($adminBKey['key']);
    $canAccess = $resourceAuth->canAccessResource(
        $adminBUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id'],
        ResourceAuthService::ACTION_READ
    );
    
    test('Admin B can access Agent A after permission grant', $canAccess);
    
    // But Admin B should NOT be able to update (only granted read)
    $canUpdate = $resourceAuth->canAccessResource(
        $adminBUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id'],
        ResourceAuthService::ACTION_UPDATE
    );
    
    test('Admin B cannot update Agent A (only has read)', !$canUpdate);
    
    echo "\n";
    
    // ============================================================
    // Test 5: List Resource Permissions
    // ============================================================
    echo "--- Test 5: List Resource Permissions ---\n";
    
    $permissions = $resourceAuth->listResourcePermissions(
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id']
    );
    
    test('List permissions returns results', count($permissions) === 1);
    test('Permission has correct user', $permissions[0]['user_id'] === $adminB['id']);
    test('Permission has correct permissions', in_array('read', $permissions[0]['permissions']));
    
    echo "\n";
    
    // ============================================================
    // Test 6: Revoke Permission
    // ============================================================
    echo "--- Test 6: Revoke Permission ---\n";
    
    $resourceAuth->revokeResourcePermission($permission['id']);
    
    // Admin B should no longer have access
    $canAccess = $resourceAuth->canAccessResource(
        $adminBUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id'],
        ResourceAuthService::ACTION_READ
    );
    
    test('Admin B cannot access Agent A after revoke', !$canAccess);
    
    $permissions = $resourceAuth->listResourcePermissions(
        ResourceAuthService::RESOURCE_AGENT,
        $agentA['id']
    );
    
    test('No active permissions after revoke', count($permissions) === 0);
    
    echo "\n";
    
    // ============================================================
    // Test Summary
    // ============================================================
    echo "=== Test Summary ===\n";
    echo "Passed: $testsPassed\n";
    echo "Failed: $testsFailed\n";
    
    if ($testsFailed === 0) {
        echo "\n✓ All Admin API Resource Authorization Tests Passed!\n";
        exit(0);
    } else {
        echo "\n✗ Some tests failed\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
