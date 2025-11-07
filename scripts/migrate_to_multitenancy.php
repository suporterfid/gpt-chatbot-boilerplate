#!/usr/bin/env php
<?php
/**
 * Migration utility to convert single-tenant data to multi-tenant
 * This script creates a default tenant and assigns all existing data to it
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/TenantService.php';

echo "=== Multi-Tenancy Data Migration Utility ===\n\n";

try {
    // Initialize database
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db'
    ];
    
    $db = new DB($dbConfig);
    
    // Run migrations to ensure tenant tables exist
    echo "Running database migrations...\n";
    $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "Migrations complete.\n\n";
    
    // Check if tenants table exists
    if (!$db->tableExists('tenants')) {
        echo "ERROR: Tenants table does not exist. Please ensure migrations have run successfully.\n";
        exit(1);
    }
    
    // Check if there are any tenants
    $existingTenants = $db->query("SELECT COUNT(*) as count FROM tenants");
    $tenantCount = $existingTenants[0]['count'];
    
    if ($tenantCount > 0) {
        echo "Found $tenantCount existing tenant(s).\n";
        echo "Do you want to proceed with migration? This will assign all data without tenant_id to a default tenant. (yes/no): ";
        $confirm = trim(fgets(STDIN));
        
        if (strtolower($confirm) !== 'yes') {
            echo "Migration cancelled.\n";
            exit(0);
        }
    }
    
    // Initialize TenantService
    $tenantService = new TenantService($db);
    
    // Create or get default tenant
    $defaultTenant = null;
    $defaultTenantSlug = 'default';
    
    try {
        $defaultTenant = $tenantService->getTenantBySlug($defaultTenantSlug);
    } catch (Exception $e) {
        // Tenant doesn't exist
    }
    
    if (!$defaultTenant) {
        echo "Creating default tenant...\n";
        $defaultTenant = $tenantService->createTenant([
            'name' => 'Default Tenant',
            'slug' => $defaultTenantSlug,
            'status' => 'active',
            'plan' => 'legacy'
        ]);
        echo "Default tenant created: {$defaultTenant['id']} ({$defaultTenant['name']})\n\n";
    } else {
        echo "Using existing default tenant: {$defaultTenant['id']} ({$defaultTenant['name']})\n\n";
    }
    
    $defaultTenantId = $defaultTenant['id'];
    
    // Migrate data for each table
    $tables = [
        'agents' => 'Agents',
        'prompts' => 'Prompts',
        'vector_stores' => 'Vector Stores',
        'admin_users' => 'Admin Users',
        'audit_conversations' => 'Audit Conversations',
        'channel_sessions' => 'Channel Sessions',
        'leads' => 'Leads',
        'jobs' => 'Jobs'
    ];
    
    $db->beginTransaction();
    
    try {
        foreach ($tables as $table => $label) {
            if (!$db->tableExists($table)) {
                echo "Skipping $label (table doesn't exist)\n";
                continue;
            }
            
            // Check if table has tenant_id column
            $columns = $db->query("PRAGMA table_info($table)");
            $hasTenantId = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'tenant_id') {
                    $hasTenantId = true;
                    break;
                }
            }
            
            if (!$hasTenantId) {
                echo "Skipping $label (no tenant_id column)\n";
                continue;
            }
            
            // Count records without tenant_id
            $result = $db->query("SELECT COUNT(*) as count FROM $table WHERE tenant_id IS NULL");
            $count = $result[0]['count'];
            
            if ($count === 0) {
                echo "Skipping $label (no records to migrate)\n";
                continue;
            }
            
            echo "Migrating $count $label records...\n";
            
            // Update records to assign them to default tenant
            $sql = "UPDATE $table SET tenant_id = ? WHERE tenant_id IS NULL";
            $updated = $db->execute($sql, [$defaultTenantId]);
            
            echo "  ✓ Updated $updated records\n";
        }
        
        $db->commit();
        
        echo "\n=== Migration Complete ===\n";
        echo "All existing data has been assigned to the default tenant.\n";
        echo "Tenant ID: {$defaultTenantId}\n";
        echo "Tenant Name: {$defaultTenant['name']}\n";
        echo "\nYou can now:\n";
        echo "1. Create additional tenants using the Admin API\n";
        echo "2. Create tenant-specific admin users\n";
        echo "3. Assign resources to different tenants\n";
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
