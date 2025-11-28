# [CRITICAL] Add Multi-Tenancy Support to WordPress Blog Tables

## Priority
🔴 **Critical** - Blocking for production deployment

## Type
- [x] Security Issue
- [x] Bug
- [ ] Feature Request
- [ ] Enhancement

## Description
The WordPress Blog automation system lacks multi-tenancy support. All WordPress blog tables are missing `tenant_id` columns, allowing potential cross-tenant data access. This is a **critical security vulnerability** that violates data isolation requirements and LGPD/GDPR compliance.

## Current State
WordPress blog tables don't filter by tenant:
- `blog_configurations`
- `blog_articles_queue`
- `blog_article_categories`
- `blog_article_tags`
- `blog_internal_links`

## Security Impact
- **Severity**: Critical (CWE-639: Authorization Bypass Through User-Controlled Key)
- Tenant A can access Tenant B's configurations
- Mixed article queue processing across tenants
- LGPD/GDPR compliance violations
- Incomplete audit trails

## Affected Files
- `db/migrations/048_add_wordpress_blog_tables.sql`
- `includes/WordPressBlog/Services/WordPressBlogConfigurationService.php` (~15 methods)
- `includes/WordPressBlog/Services/WordPressBlogQueueService.php` (~20 methods)
- `admin-api.php` (WordPress blog endpoint handlers)

## Implementation Tasks

### Task 1: Create Migration to Add tenant_id Columns
```sql
-- Create: db/migrations/049_add_tenant_id_to_blog_tables.sql

BEGIN TRANSACTION;

-- Add tenant_id to all WordPress blog tables
ALTER TABLE blog_configurations ADD COLUMN tenant_id TEXT NOT NULL DEFAULT 'default';
ALTER TABLE blog_articles_queue ADD COLUMN tenant_id TEXT NOT NULL DEFAULT 'default';
ALTER TABLE blog_article_categories ADD COLUMN tenant_id TEXT NOT NULL DEFAULT 'default';
ALTER TABLE blog_article_tags ADD COLUMN tenant_id TEXT NOT NULL DEFAULT 'default';
ALTER TABLE blog_internal_links ADD COLUMN tenant_id TEXT NOT NULL DEFAULT 'default';

-- Add indexes for performance
CREATE INDEX idx_blog_configs_tenant_id ON blog_configurations(tenant_id);
CREATE INDEX idx_blog_articles_tenant_id ON blog_articles_queue(tenant_id);
CREATE INDEX idx_blog_categories_tenant_id ON blog_article_categories(tenant_id);
CREATE INDEX idx_blog_tags_tenant_id ON blog_article_tags(tenant_id);
CREATE INDEX idx_blog_links_tenant_id ON blog_internal_links(tenant_id);

-- Add composite indexes for common queries
CREATE INDEX idx_blog_articles_tenant_status ON blog_articles_queue(tenant_id, status);
CREATE INDEX idx_blog_links_tenant_active ON blog_internal_links(tenant_id, is_active);

-- Add foreign key constraints (if tenants table exists)
-- ALTER TABLE blog_configurations
--     ADD CONSTRAINT fk_blog_configs_tenant
--     FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE;

COMMIT;
```

### Task 2: Update WordPressBlogConfigurationService
Add tenant_id filtering to all methods:

```php
// includes/WordPressBlog/Services/WordPressBlogConfigurationService.php

// Import TenantContext at top
require_once __DIR__ . '/../TenantContext.php';

// Update all query methods to include tenant_id filter
public function getConfiguration($configurationId, $includeCredentials = false) {
    $tenantId = TenantContext::getCurrentTenantId();

    $sql = "SELECT * FROM blog_configurations
            WHERE configuration_id = :configuration_id
            AND tenant_id = :tenant_id";

    $results = $this->db->query($sql, [
        'configuration_id' => $configurationId,
        'tenant_id' => $tenantId
    ]);
    // ... rest of method
}

// Update createConfiguration to include tenant_id
public function createConfiguration(array $configData) {
    $tenantId = TenantContext::getCurrentTenantId();

    // Add tenant_id to insert
    $sql = "INSERT INTO blog_configurations (
        configuration_id, tenant_id, config_name, ...
    ) VALUES (
        :configuration_id, :tenant_id, :config_name, ...
    )";

    $this->db->execute($sql, array_merge(
        ['tenant_id' => $tenantId],
        $params
    ));
}

// Apply same pattern to:
// - listConfigurations()
// - updateConfiguration()
// - deleteConfiguration()
// - getConfigurationByName()
```

### Task 3: Update WordPressBlogQueueService
Add tenant_id filtering to all queue operations:

```php
// includes/WordPressBlog/Services/WordPressBlogQueueService.php

require_once __DIR__ . '/../TenantContext.php';

public function queueArticle(array $articleData) {
    $tenantId = TenantContext::getCurrentTenantId();

    // Add tenant_id to insert
    $sql = "INSERT INTO blog_articles_queue (
        article_id, tenant_id, configuration_id, ...
    ) VALUES (
        :article_id, :tenant_id, :configuration_id, ...
    )";

    $this->db->execute($sql, array_merge(
        ['tenant_id' => $tenantId],
        $params
    ));
}

public function getNextQueuedArticle() {
    $tenantId = TenantContext::getCurrentTenantId();

    $this->db->execute("BEGIN IMMEDIATE TRANSACTION");

    try {
        $sql = "SELECT * FROM blog_articles_queue
                WHERE status = 'queued'
                  AND tenant_id = :tenant_id
                  AND (scheduled_date IS NULL OR scheduled_date <= datetime('now'))
                ORDER BY /* ... */
                LIMIT 1";

        $results = $this->db->query($sql, ['tenant_id' => $tenantId]);
        // ... rest of method
    }
}

// Apply to all methods:
// - getArticle()
// - listArticles()
// - listArticlesByStatus()
// - updateArticleStatus()
// - addCategories()
// - addTags()
// - etc.
```

### Task 4: Update admin-api.php Endpoint Handlers
Add tenant validation to all WordPress blog endpoints:

```php
// admin-api.php

case 'wordpress_blog_create_config':
    // Get tenant from authenticated user
    $tenantId = $user['tenant_id'] ?? null;

    if (!$tenantId) {
        http_response_code(403);
        echo json_encode(['error' => 'Tenant not found']);
        exit;
    }

    // Set tenant context
    TenantContext::setCurrentTenantId($tenantId);

    // Proceed with operation (service will use tenant context)
    $result = $configService->createConfiguration($data);
    break;

// Apply to all WordPress blog actions:
// - wordpress_blog_update_config
// - wordpress_blog_delete_config
// - wordpress_blog_list_configs
// - wordpress_blog_queue_article
// - wordpress_blog_list_queue
// - etc.
```

### Task 5: Create Tests for Multi-Tenancy
```php
// tests/WordPressBlog/MultiTenancyTest.php

final class MultiTenancyTest extends TestCase
{
    public function testTenantIsolationForConfigurations(): void
    {
        // Setup two tenants
        TenantContext::setCurrentTenantId('tenant-a');
        $configA = $this->service->createConfiguration([/* ... */]);

        TenantContext::setCurrentTenantId('tenant-b');
        $configB = $this->service->createConfiguration([/* ... */]);

        // Tenant A should only see their config
        TenantContext::setCurrentTenantId('tenant-a');
        $configs = $this->service->listConfigurations();
        $this->assertCount(1, $configs);
        $this->assertEquals($configA['configuration_id'], $configs[0]['configuration_id']);

        // Tenant A should NOT be able to access Tenant B's config
        $result = $this->service->getConfiguration($configB['configuration_id']);
        $this->assertNull($result);
    }

    public function testQueueIsolation(): void
    {
        // Test queue operations are isolated by tenant
    }

    public function testCrossTenatAccessDenied(): void
    {
        // Test that tenant A cannot update/delete tenant B's resources
    }
}
```

## Acceptance Criteria
- [ ] Migration #049 created and tested
- [ ] All 5 WordPress blog tables have `tenant_id` column
- [ ] All indexes created for `tenant_id`
- [ ] `WordPressBlogConfigurationService` filters by tenant in all 15+ methods
- [ ] `WordPressBlogQueueService` filters by tenant in all 20+ methods
- [ ] All admin-api.php WordPress blog handlers validate tenant
- [ ] Multi-tenancy tests pass with 100% isolation
- [ ] Tenant A cannot access Tenant B's data (verified)
- [ ] Documentation updated with multi-tenancy notes
- [ ] CHANGELOG updated

## Testing Steps
1. Create two test tenants (tenant-a, tenant-b)
2. Create configurations for both tenants
3. Queue articles for both tenants
4. Switch to tenant-a context, verify only tenant-a data visible
5. Switch to tenant-b context, verify only tenant-b data visible
6. Attempt cross-tenant access, verify 403/404 responses
7. Test queue processing respects tenant isolation
8. Verify audit logs include tenant_id

## Related Issues
- Depends on: #TBD (Create TenantContext service if not exists)
- Blocks: Production deployment
- Related to: LGPD/GDPR compliance requirements

## Estimated Effort
**8-12 hours**
- Migration creation: 1 hour
- Service updates: 5-6 hours
- Admin API updates: 2 hours
- Testing: 3-4 hours
- Documentation: 1 hour

## Additional Context
This issue was identified during comprehensive code review (commit: 1148cae). See full code review report for additional context.

**Code Review Reference**: Step 8 - Multi-Tenancy and Compliance, Issue #1
