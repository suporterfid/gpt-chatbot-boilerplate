# Multi-Tenancy Architecture Documentation

## Overview

This document describes the multi-tenancy architecture implemented in the GPT Chatbot Boilerplate. The implementation provides complete tenant isolation for SaaS deployments, ensuring that data, configuration, and business logic are segregated across different customers (tenants).

## Architecture Components

### 1. Database Schema

#### Tenants Table

The core table for multi-tenancy:

```sql
CREATE TABLE tenants (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active', 'suspended', 'inactive')),
    plan TEXT NULL,
    billing_email TEXT NULL,
    settings_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

**Fields:**
- `id`: UUID primary key
- `name`: Display name of the tenant
- `slug`: URL-safe unique identifier (lowercase, hyphenated)
- `status`: Tenant status (active, suspended, inactive)
- `plan`: Optional subscription plan identifier
- `billing_email`: Billing contact email
- `settings_json`: Tenant-specific settings in JSON format

#### Tenant-Aware Tables

The following tables include a `tenant_id` column for isolation:

- `agents` - AI agent configurations
- `prompts` - OpenAI prompt references
- `vector_stores` - Vector store configurations
- `admin_users` - Admin users (except super-admins)
- `audit_conversations` - Conversation audit trails
- `channel_sessions` - Channel session mappings
- `leads` - LeadSense commercial opportunities
- `jobs` - Background job queue

### 2. Services

#### TenantService

**Location:** `includes/TenantService.php`

Manages tenant CRUD operations and provides tenant statistics.

**Key Methods:**
- `createTenant($data)` - Create a new tenant
- `getTenant($id)` - Get tenant by ID
- `getTenantBySlug($slug)` - Get tenant by slug
- `listTenants($filters)` - List tenants with optional filters
- `updateTenant($id, $data)` - Update tenant properties
- `deleteTenant($id)` - Delete tenant (cascades to all related data)
- `suspendTenant($id)` - Set tenant status to suspended
- `activateTenant($id)` - Set tenant status to active
- `getTenantStats($id)` - Get resource counts for a tenant

**Usage Example:**
```php
$tenantService = new TenantService($db);

// Create a tenant
$tenant = $tenantService->createTenant([
    'name' => 'Acme Corporation',
    'slug' => 'acme',
    'status' => 'active',
    'billing_email' => 'billing@acme.com',
    'settings' => [
        'max_agents' => 10,
        'features' => ['whatsapp', 'leadsense']
    ]
]);

// Get statistics
$stats = $tenantService->getTenantStats($tenant['id']);
// Returns: ['agents' => 5, 'prompts' => 3, 'vector_stores' => 2, ...]
```

#### Tenant-Scoped Services

All major services support tenant scoping:

**AgentService:**
```php
$agentService = new AgentService($db, $tenantId);

// Or set tenant context after initialization
$agentService->setTenantId($tenantId);

// All queries are now filtered by tenant_id
$agents = $agentService->listAgents();
```

**PromptService:**
```php
$promptService = new PromptService($db, $openaiClient, $tenantId);
```

**VectorStoreService:**
```php
$vectorStoreService = new VectorStoreService($db, $openaiClient, $tenantId);
```

**LeadRepository:**
```php
$leadRepo = new LeadRepository($config, $tenantId);
```

### 3. Authentication & Authorization

#### Admin Users and Tenant Assignment

**User Roles:**
- `viewer` - Read-only access (must have tenant_id)
- `admin` - Full CRUD access within tenant (must have tenant_id)
- `super-admin` - Cross-tenant access, tenant management (tenant_id is NULL)

**Creating Users:**
```php
$adminAuth = new AdminAuth($db, $config);

// Create tenant-specific admin
$admin = $adminAuth->createUser(
    'admin@acme.com',
    'password123',
    'admin',
    $tenantId
);

// Create super-admin (no tenant restriction)
$superAdmin = $adminAuth->createUser(
    'super@platform.com',
    'password123',
    'super-admin',
    null  // Must be null for super-admins
);
```

#### Authentication Flow

When a user authenticates via API key or token:

1. `AdminAuth::authenticate()` retrieves user data including `tenant_id`
2. Services are initialized with the user's `tenant_id`
3. All queries automatically filter by `tenant_id`

**Example:**
```php
// In admin-api.php
$authenticatedUser = $adminAuth->authenticate($token);
$tenantId = $authenticatedUser['tenant_id'] ?? null;

// Initialize services with tenant context
$agentService = new AgentService($db, $tenantId);
$promptService = new PromptService($db, $openaiClient, $tenantId);
```

### 4. API Endpoints

#### Tenant Management (Super-Admin Only)

**List Tenants:**
```
GET /admin-api.php?action=list_tenants
GET /admin-api.php?action=list_tenants&status=active
GET /admin-api.php?action=list_tenants&search=acme
```

**Get Tenant:**
```
GET /admin-api.php?action=get_tenant&id={tenant_id}
```

**Create Tenant:**
```
POST /admin-api.php?action=create_tenant
Content-Type: application/json

{
  "name": "Acme Corporation",
  "slug": "acme",
  "status": "active",
  "billing_email": "billing@acme.com",
  "plan": "enterprise",
  "settings": {
    "max_agents": 20
  }
}
```

**Update Tenant:**
```
POST /admin-api.php?action=update_tenant&id={tenant_id}
Content-Type: application/json

{
  "name": "Acme Corp (Updated)",
  "plan": "pro"
}
```

**Suspend/Activate Tenant:**
```
POST /admin-api.php?action=suspend_tenant&id={tenant_id}
POST /admin-api.php?action=activate_tenant&id={tenant_id}
```

**Delete Tenant:**
```
POST /admin-api.php?action=delete_tenant&id={tenant_id}
```

**Get Tenant Statistics:**
```
GET /admin-api.php?action=get_tenant_stats&id={tenant_id}
```

Response:
```json
{
  "data": {
    "agents": 5,
    "prompts": 3,
    "vector_stores": 2,
    "users": 4,
    "conversations": 120,
    "leads": 15,
    "total_resources": 149
  }
}
```

#### Resource Endpoints (Tenant-Scoped)

All existing resource endpoints automatically scope to the authenticated user's tenant:

```
GET /admin-api.php?action=list_agents
// Returns only agents belonging to the user's tenant

POST /admin-api.php?action=create_agent
// Creates agent assigned to the user's tenant
```

### 5. Data Migration

#### Migrating Existing Single-Tenant Data

**Script:** `scripts/migrate_to_multitenancy.php`

This utility:
1. Creates a default tenant (slug: `default`)
2. Assigns all existing data without `tenant_id` to the default tenant
3. Provides a summary of migrated records

**Usage:**
```bash
cd /home/runner/work/gpt-chatbot-boilerplate/gpt-chatbot-boilerplate
php scripts/migrate_to_multitenancy.php
```

**Interactive Process:**
```
=== Multi-Tenancy Data Migration Utility ===

Running database migrations...
Migrations complete.

Found 0 existing tenant(s).
Creating default tenant...
Default tenant created: {id} (Default Tenant)

Migrating 5 Agents records...
  ✓ Updated 5 records
Migrating 3 Prompts records...
  ✓ Updated 3 records
...

=== Migration Complete ===
All existing data has been assigned to the default tenant.
```

## Testing

### Running Multi-Tenancy Tests

**Test Suite:** `tests/test_multitenancy.php`

The test suite validates:
- Tenant CRUD operations
- Tenant-scoped agent isolation
- Admin user tenant assignment
- Tenant status management
- Validation and constraints

**Run Tests:**
```bash
php tests/test_multitenancy.php
```

**Expected Output:**
```
=== Multi-Tenancy Test Suite ===

Test Suite 1: Tenant Management
✓ PASS: Create tenant 1
✓ PASS: Tenant 1 slug is correct
...

Test Suite 2: Tenant-Scoped Agents
✓ PASS: Create agent for tenant 1
✓ PASS: Tenant 1 cannot access tenant 2 agent
...

=== Test Summary ===
Passed: 25
Failed: 0

✓ All tests passed!
```

## Security Considerations

### Data Isolation

1. **Query-Level Filtering:** All service methods automatically filter by `tenant_id` when context is set
2. **Foreign Key Constraints:** Tenant deletions cascade to all related data
3. **API-Level Enforcement:** Admin API initializes services with authenticated user's tenant context

### Tenant Verification

Example of tenant isolation in practice:

```php
// Tenant 1 user tries to access Tenant 2's agent
$agentService->setTenantId($tenant1Id);
$agent = $agentService->getAgent($tenant2AgentId);
// Returns: null (not found)

// Tenant 1 user lists agents
$agents = $agentService->listAgents();
// Returns: only agents with tenant_id = $tenant1Id
```

### Super-Admin Privileges

Super-admins can:
- Create, update, suspend, and delete tenants
- View tenant statistics
- Access tenant management endpoints

Super-admins **cannot** directly access tenant resources unless they:
- Create a separate admin account within that tenant, or
- Use the API without tenant filtering (requires code modification)

## Best Practices

### Creating New Tenants

1. Use descriptive, unique slugs (e.g., company domain or abbreviation)
2. Set appropriate billing email for invoicing
3. Configure tenant-specific settings upfront
4. Create at least one admin user for the tenant immediately

### Tenant-Specific Configuration

Store tenant-specific configuration in the `settings_json` field:

```php
$tenant = $tenantService->createTenant([
    'name' => 'Acme Corp',
    'slug' => 'acme',
    'settings' => [
        'features' => ['whatsapp', 'leadsense', 'whitelabel'],
        'limits' => [
            'max_agents' => 10,
            'max_conversations_per_month' => 10000
        ],
        'branding' => [
            'logo_url' => 'https://acme.com/logo.png',
            'primary_color' => '#FF6B35'
        ]
    ]
]);
```

Retrieve and use settings:

```php
$tenant = $tenantService->getTenant($tenantId);
$maxAgents = $tenant['settings']['limits']['max_agents'] ?? 5;

if (count($agentService->listAgents()) >= $maxAgents) {
    throw new Exception('Agent limit reached for this tenant');
}
```

### Soft Tenant Suspension

Instead of deleting tenants, suspend them to preserve data:

```php
// Suspend tenant (keeps all data)
$tenantService->suspendTenant($tenantId);

// Check tenant status before allowing operations
$tenant = $tenantService->getTenant($tenantId);
if ($tenant['status'] !== 'active') {
    throw new Exception('Tenant is not active');
}
```

### Adding Tenant Context to New Tables

When creating new tables that should be tenant-aware:

1. **Add Column:**
```sql
ALTER TABLE new_table ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX idx_new_table_tenant_id ON new_table(tenant_id);
```

2. **Update Service:**
```php
class NewService {
    private $tenantId;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }
    
    public function list() {
        $sql = "SELECT * FROM new_table";
        $params = [];
        
        if ($this->tenantId !== null) {
            $sql .= " WHERE tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        return $this->db->query($sql, $params);
    }
}
```

## Troubleshooting

### Common Issues

**Issue:** Agents/resources not visible after login
**Solution:** Verify user's `tenant_id` matches the resources. Check:
```sql
SELECT id, email, tenant_id FROM admin_users WHERE email = 'user@example.com';
SELECT id, name, tenant_id FROM agents LIMIT 10;
```

**Issue:** "Tenant not found" error
**Solution:** Ensure tenant exists and is active:
```sql
SELECT * FROM tenants WHERE id = '{tenant_id}';
```

**Issue:** Cannot create super-admin
**Solution:** Ensure `tenant_id` is `null`:
```php
$adminAuth->createUser('super@example.com', 'pass', 'super-admin', null);
```

### Verifying Multi-Tenancy

Check tenant isolation:

```bash
# Run the test suite
php tests/test_multitenancy.php

# Check database integrity
sqlite3 data/chatbot.db "
  SELECT t.name, COUNT(a.id) as agent_count 
  FROM tenants t 
  LEFT JOIN agents a ON t.id = a.tenant_id 
  GROUP BY t.id;
"
```

## Future Enhancements

Potential improvements for the multi-tenancy system:

1. **Tenant-Specific Rate Limiting:** Different rate limits per tenant based on plan
2. **Cross-Tenant Reporting:** Super-admin dashboard showing aggregate metrics
3. **Tenant Quotas:** Enforce resource limits (agents, conversations, storage)
4. **Tenant Audit Log:** Track all tenant-level operations
5. **Tenant Billing Integration:** Connect to Stripe/payment systems
6. **Tenant Data Export:** Allow tenants to export their data
7. **Tenant Cloning:** Duplicate tenant configuration for testing/staging

## Migration Checklist

When deploying multi-tenancy to an existing installation:

- [ ] Backup database before migration
- [ ] Run database migrations (`020_create_tenants.sql`, `021_add_tenant_id_to_tables.sql`)
- [ ] Run data migration script (`scripts/migrate_to_multitenancy.php`)
- [ ] Verify all existing data is assigned to default tenant
- [ ] Create additional tenants as needed
- [ ] Create tenant-specific admin users
- [ ] Update application code to use tenant-scoped services
- [ ] Test tenant isolation thoroughly
- [ ] Update documentation and user guides
- [ ] Deploy to production

## Support

For questions or issues related to multi-tenancy:

1. Check this documentation
2. Review test suite for examples
3. Examine service implementations
4. Open an issue on GitHub with detailed error messages and context
