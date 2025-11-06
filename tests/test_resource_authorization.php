#!/usr/bin/env php
<?php
/**
 * Resource-Level Authorization Integration Test
 * Tests per-resource access control for multi-tenant SaaS scenarios
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AdminAuth.php';
require_once __DIR__ . '/../includes/AuditService.php';
require_once __DIR__ . '/../includes/ResourceAuthService.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/TenantService.php';

// Test configuration
$dbConfig = [
    'database_path' => '/tmp/test_resource_auth_' . time() . '.db'
];

$auditConfig = [
    'enabled' => true,
    'database_path' => $dbConfig['database_path'],
    'encrypt_at_rest' => false
];

echo "=== Resource-Level Authorization Integration Test ===\n\n";

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
    // Initialize
    $db = new DB($dbConfig);
    $db->runMigrations(__DIR__ . '/../db/migrations');
    
    $config = ['admin' => ['token' => 'test_legacy_token']];
    $adminAuth = new AdminAuth($db, $config);
    $auditService = new AuditService($auditConfig);
    $resourceAuth = new ResourceAuthService($db, $adminAuth, $auditService);
    $tenantService = new TenantService($db);
    
    echo "✓ Database and services initialized\n\n";
    
    // ============================================================
    // Test Suite 1: Create Test Tenants and Users
    // ============================================================
    echo "--- Test Suite 1: Setup Tenants and Users ---\n";
    
    // Create tenants
    $tenant1 = $tenantService->createTenant([
        'name' => 'Acme Corp',
        'slug' => 'acme',
        'status' => 'active'
    ]);
    test('Create tenant 1 (Acme)', isset($tenant1['id']));
    
    $tenant2 = $tenantService->createTenant([
        'name' => 'Beta Inc',
        'slug' => 'beta',
        'status' => 'active'
    ]);
    test('Create tenant 2 (Beta)', isset($tenant2['id']));
    
    // Create users in different tenants
    $acmeAdmin = $adminAuth->createUser('admin@acme.com', 'password123', AdminAuth::ROLE_ADMIN, $tenant1['id']);
    $acmeAdminKey = $adminAuth->generateApiKey($acmeAdmin['id'], 'Acme Admin Key');
    test('Create admin user for Acme', $acmeAdmin['tenant_id'] === $tenant1['id']);
    
    $betaAdmin = $adminAuth->createUser('admin@beta.com', 'password123', AdminAuth::ROLE_ADMIN, $tenant2['id']);
    $betaAdminKey = $adminAuth->generateApiKey($betaAdmin['id'], 'Beta Admin Key');
    test('Create admin user for Beta', $betaAdmin['tenant_id'] === $tenant2['id']);
    
    $acmeViewer = $adminAuth->createUser('viewer@acme.com', 'password123', AdminAuth::ROLE_VIEWER, $tenant1['id']);
    $acmeViewerKey = $adminAuth->generateApiKey($acmeViewer['id'], 'Acme Viewer Key');
    test('Create viewer user for Acme', $acmeViewer['role'] === AdminAuth::ROLE_VIEWER);
    
    // Create super-admin
    $superAdmin = $adminAuth->createUser('super@admin.com', 'password123', AdminAuth::ROLE_SUPER_ADMIN);
    $superAdminKey = $adminAuth->generateApiKey($superAdmin['id'], 'Super Admin Key');
    test('Create super-admin user', $superAdmin['role'] === AdminAuth::ROLE_SUPER_ADMIN);
    
    echo "\n";
    
    // ============================================================
    // Test Suite 2: Create Resources
    // ============================================================
    echo "--- Test Suite 2: Create Resources ---\n";
    
    // Create agents in different tenants
    $agentService = new AgentService($db, $tenant1['id']);
    $acmeAgent = $agentService->createAgent([
        'name' => 'Acme Agent',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini'
    ]);
    test('Create agent for Acme tenant', $acmeAgent['tenant_id'] === $tenant1['id']);
    
    $agentService->setTenantId($tenant2['id']);
    $betaAgent = $agentService->createAgent([
        'name' => 'Beta Agent',
        'api_type' => 'chat',
        'model' => 'gpt-4o-mini'
    ]);
    test('Create agent for Beta tenant', $betaAgent['tenant_id'] === $tenant2['id']);
    
    echo "\n";
    
    // ============================================================
    // Test Suite 3: Tenant Isolation - Cross-Tenant Access Denied
    // ============================================================
    echo "--- Test Suite 3: Tenant Isolation ---\n";
    
    // Acme admin should NOT be able to access Beta's agent
    $acmeAdminUser = $adminAuth->authenticate($acmeAdminKey['key']);
    $canAccessBetaAgent = $resourceAuth->canAccessResource(
        $acmeAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $betaAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Acme admin CANNOT access Beta agent', !$canAccessBetaAgent);
    
    // Beta admin should NOT be able to access Acme's agent
    $betaAdminUser = $adminAuth->authenticate($betaAdminKey['key']);
    $canAccessAcmeAgent = $resourceAuth->canAccessResource(
        $betaAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Beta admin CANNOT access Acme agent', !$canAccessAcmeAgent);
    
    // Super-admin should be able to access both
    $superAdminUser = $adminAuth->authenticate($superAdminKey['key']);
    $canAccessAcme = $resourceAuth->canAccessResource(
        $superAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    $canAccessBeta = $resourceAuth->canAccessResource(
        $superAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $betaAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Super-admin CAN access Acme agent', $canAccessAcme);
    test('Super-admin CAN access Beta agent', $canAccessBeta);
    
    echo "\n";
    
    // ============================================================
    // Test Suite 4: Same-Tenant Access Control
    // ============================================================
    echo "--- Test Suite 4: Same-Tenant Access Control ---\n";
    
    // Acme admin should be able to access Acme agent
    $canAccess = $resourceAuth->canAccessResource(
        $acmeAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Acme admin CAN read Acme agent', $canAccess);
    
    $canUpdate = $resourceAuth->canAccessResource(
        $acmeAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_UPDATE
    );
    test('Acme admin CAN update Acme agent', $canUpdate);
    
    // Acme viewer should be able to read but NOT update
    $acmeViewerUser = $adminAuth->authenticate($acmeViewerKey['key']);
    $canRead = $resourceAuth->canAccessResource(
        $acmeViewerUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    $canUpdate = $resourceAuth->canAccessResource(
        $acmeViewerUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_UPDATE
    );
    test('Acme viewer CAN read Acme agent', $canRead);
    test('Acme viewer CANNOT update Acme agent (RBAC)', !$canUpdate);
    
    echo "\n";
    
    // ============================================================
    // Test Suite 5: Require Resource Access (Exception Throwing)
    // ============================================================
    echo "--- Test Suite 5: Access Enforcement ---\n";
    
    // Should throw exception when accessing cross-tenant resource
    $exceptionThrown = false;
    try {
        $resourceAuth->requireResourceAccess(
            $acmeAdminUser,
            ResourceAuthService::RESOURCE_AGENT,
            $betaAgent['id'],
            ResourceAuthService::ACTION_READ
        );
    } catch (Exception $e) {
        $exceptionThrown = true;
        test('Cross-tenant access throws exception', $e->getCode() === 403);
    }
    
    if (!$exceptionThrown) {
        test('Cross-tenant access throws exception', false, 'No exception thrown');
    }
    
    // Should NOT throw exception when accessing same-tenant resource
    $exceptionThrown = false;
    try {
        $resourceAuth->requireResourceAccess(
            $acmeAdminUser,
            ResourceAuthService::RESOURCE_AGENT,
            $acmeAgent['id'],
            ResourceAuthService::ACTION_READ
        );
        test('Same-tenant access does NOT throw exception', true);
    } catch (Exception $e) {
        test('Same-tenant access does NOT throw exception', false, $e->getMessage());
    }
    
    echo "\n";
    
    // ============================================================
    // Test Suite 6: Explicit Resource Permissions
    // ============================================================
    echo "--- Test Suite 6: Explicit Resource Permissions ---\n";
    
    // Grant Beta admin explicit access to Acme agent
    $permission = $resourceAuth->grantResourcePermission(
        $betaAdmin['id'],
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ['read', 'update'],
        $superAdmin['id']
    );
    test('Grant explicit permission', isset($permission['id']));
    
    // Now Beta admin should be able to access Acme agent
    $canAccessNow = $resourceAuth->canAccessResource(
        $betaAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Beta admin CAN access Acme agent after explicit grant', $canAccessNow);
    
    // List permissions for the resource
    $permissions = $resourceAuth->listResourcePermissions(
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id']
    );
    test('List resource permissions', count($permissions) === 1);
    test('Permission has correct user', $permissions[0]['user_id'] === $betaAdmin['id']);
    
    // Revoke permission
    $resourceAuth->revokeResourcePermission($permission['id']);
    
    // Beta admin should no longer have access
    $canAccessAfterRevoke = $resourceAuth->canAccessResource(
        $betaAdminUser,
        ResourceAuthService::RESOURCE_AGENT,
        $acmeAgent['id'],
        ResourceAuthService::ACTION_READ
    );
    test('Beta admin CANNOT access Acme agent after revoke', !$canAccessAfterRevoke);
    
    echo "\n";
    
    // ============================================================
    // Test Suite 7: Audit Logging
    // ============================================================
    echo "--- Test Suite 7: Audit Logging ---\n";
    
    // Trigger an access denied event
    try {
        $resourceAuth->requireResourceAccess(
            $betaAdminUser,
            ResourceAuthService::RESOURCE_AGENT,
            $acmeAgent['id'],
            ResourceAuthService::ACTION_UPDATE
        );
    } catch (Exception $e) {
        // Expected
    }
    
    // Check that the access denied event was logged
    $events = $db->query(
        "SELECT * FROM audit_events WHERE type = 'access_denied' ORDER BY created_at DESC LIMIT 1"
    );
    test('Access denied event logged', count($events) > 0);
    
    if (count($events) > 0) {
        $eventData = json_decode($events[0]['payload_json'], true);
        test('Event has correct user_id', $eventData['user_id'] === $betaAdmin['id']);
        test('Event has correct resource_id', $eventData['resource_id'] === $acmeAgent['id']);
        test('Event has correct action', $eventData['action'] === ResourceAuthService::ACTION_UPDATE);
    }
    
    echo "\n";
    
    // ============================================================
    // Test Summary
    // ============================================================
    echo "=== Test Summary ===\n";
    echo "Passed: $testsPassed\n";
    echo "Failed: $testsFailed\n";
    
    if ($testsFailed === 0) {
        echo "\n✓ All Resource-Level Authorization Tests Passed!\n";
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
