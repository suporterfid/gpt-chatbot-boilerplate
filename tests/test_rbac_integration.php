#!/usr/bin/env php
<?php
/**
 * Test RBAC Integration with Admin API
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AdminAuth.php';

// Test configuration
$dbConfig = [
    'database_path' => '/tmp/test_rbac_integration_' . time() . '.db'
];

echo "=== RBAC Integration Test ===\n\n";

try {
    // Initialize
    $db = new DB($dbConfig);
    $db->runMigrations(__DIR__ . '/../db/migrations');
    
    $config = ['admin' => ['token' => 'test_legacy_token']];
    $adminAuth = new AdminAuth($db, $config);
    
    echo "✓ Database and migrations initialized\n\n";

    // Create tenant for multi-tenancy support
    echo "--- Setup: Create Test Tenant ---\n";
    $tenantId = 'tenant_' . bin2hex(random_bytes(8));
    $now = date('Y-m-d H:i:s');
    $db->insert(
        "INSERT INTO tenants (id, name, slug, status, created_at, updated_at) VALUES (?, ?, ?, 'active', ?, ?)",
        [$tenantId, 'Test Tenant', 'test-tenant-rbac', $now, $now]
    );
    echo "✓ Test tenant created\n\n";

    // Test 1: Legacy token authentication
    echo "--- Test 1: Legacy Token Authentication ---\n";
    $legacyUser = $adminAuth->authenticate('test_legacy_token');
    if ($legacyUser && $legacyUser['role'] === AdminAuth::ROLE_SUPER_ADMIN) {
        echo "✓ Legacy token authenticates as super-admin\n";
    } else {
        echo "✗ Legacy token authentication failed\n";
        exit(1);
    }

    // Test 2: Create viewer user (with tenant)
    echo "\n--- Test 2: Create Viewer User ---\n";
    $viewer = $adminAuth->createUser('viewer@test.com', 'password123', AdminAuth::ROLE_VIEWER, $tenantId);
    $viewerKey = $adminAuth->generateApiKey($viewer['id'], 'Viewer Key');
    echo "✓ Viewer user created\n";
    echo "  API Key: {$viewerKey['key_prefix']}\n";

    // Test 3: Create admin user (with tenant)
    echo "\n--- Test 3: Create Admin User ---\n";
    $admin = $adminAuth->createUser('admin@test.com', 'password123', AdminAuth::ROLE_ADMIN, $tenantId);
    $adminKey = $adminAuth->generateApiKey($admin['id'], 'Admin Key');
    echo "✓ Admin user created\n";
    echo "  API Key: {$adminKey['key_prefix']}\n";
    
    // Test 4: Test permissions
    echo "\n--- Test 4: Permission Checks ---\n";
    
    // Viewer permissions
    $viewerAuth = $adminAuth->authenticate($viewerKey['key']);
    $hasRead = $adminAuth->hasPermission($viewerAuth, 'read');
    $hasCreate = $adminAuth->hasPermission($viewerAuth, 'create');
    $hasManageUsers = $adminAuth->hasPermission($viewerAuth, 'manage_users');
    
    if ($hasRead && !$hasCreate && !$hasManageUsers) {
        echo "✓ Viewer has correct permissions (read only)\n";
    } else {
        echo "✗ Viewer permissions incorrect\n";
        exit(1);
    }
    
    // Admin permissions
    $adminAuthUser = $adminAuth->authenticate($adminKey['key']);
    $hasRead = $adminAuth->hasPermission($adminAuthUser, 'read');
    $hasCreate = $adminAuth->hasPermission($adminAuthUser, 'create');
    $hasUpdate = $adminAuth->hasPermission($adminAuthUser, 'update');
    $hasDelete = $adminAuth->hasPermission($adminAuthUser, 'delete');
    $hasManageUsers = $adminAuth->hasPermission($adminAuthUser, 'manage_users');
    
    if ($hasRead && $hasCreate && $hasUpdate && $hasDelete && !$hasManageUsers) {
        echo "✓ Admin has correct permissions (CRUD, no user management)\n";
    } else {
        echo "✗ Admin permissions incorrect\n";
        exit(1);
    }
    
    // Super-admin permissions
    $hasManageUsers = $adminAuth->hasPermission($legacyUser, 'manage_users');
    $hasRotateTokens = $adminAuth->hasPermission($legacyUser, 'rotate_tokens');
    
    if ($hasManageUsers && $hasRotateTokens) {
        echo "✓ Super-admin has correct permissions (all permissions)\n";
    } else {
        echo "✗ Super-admin permissions incorrect\n";
        exit(1);
    }
    
    // Test 5: Require permission throws exception
    echo "\n--- Test 5: Permission Enforcement ---\n";
    
    try {
        $adminAuth->requirePermission($viewerAuth, 'create');
        echo "✗ requirePermission should have thrown exception for viewer+create\n";
        exit(1);
    } catch (Exception $e) {
        echo "✓ requirePermission correctly blocks viewer from create\n";
    }
    
    try {
        $adminAuth->requirePermission($adminAuthUser, 'manage_users');
        echo "✗ requirePermission should have thrown exception for admin+manage_users\n";
        exit(1);
    } catch (Exception $e) {
        echo "✓ requirePermission correctly blocks admin from manage_users\n";
    }
    
    try {
        $adminAuth->requirePermission($legacyUser, 'manage_users');
        echo "✓ requirePermission allows super-admin to manage_users\n";
    } catch (Exception $e) {
        echo "✗ requirePermission should allow super-admin to manage_users\n";
        exit(1);
    }
    
    // Test 6: User management
    echo "\n--- Test 6: User Management ---\n";
    
    $users = $adminAuth->listUsers();
    if (count($users) === 2) {
        echo "✓ listUsers returns correct count (2 users)\n";
    } else {
        echo "✗ listUsers count incorrect: " . count($users) . "\n";
        exit(1);
    }
    
    // Update role
    $adminAuth->updateUserRole($viewer['id'], AdminAuth::ROLE_ADMIN);
    $updatedViewer = $adminAuth->getUser($viewer['id']);
    if ($updatedViewer['role'] === AdminAuth::ROLE_ADMIN) {
        echo "✓ updateUserRole successfully changed viewer to admin\n";
    } else {
        echo "✗ updateUserRole failed\n";
        exit(1);
    }
    
    // Deactivate user
    $adminAuth->deactivateUser($admin['id']);
    $deactivatedAdmin = $adminAuth->getUser($admin['id']);
    if ($deactivatedAdmin['is_active'] == 0) {
        echo "✓ deactivateUser successfully deactivated admin\n";
    } else {
        echo "✗ deactivateUser failed\n";
        exit(1);
    }
    
    // Verify deactivated user cannot authenticate
    $authAttempt = $adminAuth->authenticate($adminKey['key']);
    if (!$authAttempt) {
        echo "✓ Deactivated user cannot authenticate\n";
    } else {
        echo "✗ Deactivated user should not be able to authenticate\n";
        exit(1);
    }
    
    // Test 7: API Key management
    echo "\n--- Test 7: API Key Management ---\n";
    
    $keys = $adminAuth->listApiKeys($viewer['id']);
    if (count($keys) === 1) {
        echo "✓ listApiKeys returns correct count\n";
    } else {
        echo "✗ listApiKeys count incorrect\n";
        exit(1);
    }
    
    // Revoke key
    $adminAuth->revokeApiKey($viewerKey['id']);
    $authAfterRevoke = $adminAuth->authenticate($viewerKey['key']);
    if (!$authAfterRevoke) {
        echo "✓ Revoked API key cannot authenticate\n";
    } else {
        echo "✗ Revoked API key should not authenticate\n";
        exit(1);
    }
    
    // Test 8: Migration
    echo "\n--- Test 8: Legacy Token Migration ---\n";
    
    $migration = $adminAuth->migrateLegacyToken();
    if ($migration['user']['email'] === 'admin@system.local' && 
        $migration['user']['role'] === AdminAuth::ROLE_SUPER_ADMIN &&
        isset($migration['api_key']['key'])) {
        echo "✓ Legacy token migration creates super-admin user with API key\n";
    } else {
        echo "✗ Legacy token migration failed\n";
        exit(1);
    }
    
    echo "\n=== All RBAC Integration Tests Passed! ===\n";
    exit(0);
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
