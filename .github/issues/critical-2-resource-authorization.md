# [CRITICAL] Add Resource Authorization to WordPress Blog Endpoints

## Priority
🔴 **Critical** - Security vulnerability

## Type
- [x] Security Issue
- [x] Bug
- [ ] Feature Request

## Description
WordPress Blog endpoints in `admin-api.php` don't verify resource ownership before operations. Users can potentially access or modify configurations and articles belonging to other users within the same tenant.

## Security Impact
- **Severity**: High (CWE-862: Missing Authorization)
- User A can modify User B's configurations
- No permission checks before sensitive operations
- Incomplete access control implementation

## Current State
```php
// admin-api.php - Current implementation (NO authorization check)
case 'wordpress_blog_update_config':
    $configId = $data['configuration_id'];
    $result = $configService->updateConfiguration($configId, $data);
    // ❌ No check if user owns this configuration!
    break;
```

## Affected Endpoints
All WordPress blog endpoints in `admin-api.php`:
- `wordpress_blog_create_config` (should record creator)
- `wordpress_blog_update_config` (should check ownership)
- `wordpress_blog_delete_config` (should check ownership)
- `wordpress_blog_get_config` (should check read permission)
- `wordpress_blog_queue_article` (should check config ownership)
- `wordpress_blog_update_article` (should check article ownership)
- All other 18+ WordPress blog endpoints

## Implementation Tasks

### Task 1: Integrate ResourceAuthService
```php
// admin-api.php (add at top with other requires)
require_once 'includes/ResourceAuthService.php';

// Initialize service
$resourceAuth = new ResourceAuthService($db);
```

### Task 2: Add Permission Checks to Configuration Endpoints

**Update Configuration:**
```php
case 'wordpress_blog_update_config':
    $configId = $data['configuration_id'] ?? null;

    if (!$configId) {
        http_response_code(400);
        echo json_encode(['error' => 'configuration_id required']);
        exit;
    }

    // ✅ Check ownership/permission
    $hasPermission = $resourceAuth->canAccessResource(
        $user['user_id'],
        'wordpress_blog_configuration',
        $configId,
        'write'
    );

    if (!$hasPermission) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied: You do not own this configuration']);
        log_admin("Unauthorized access attempt: user {$user['user_id']} tried to update config {$configId}", 'security');
        exit;
    }

    // Proceed with update
    $result = $configService->updateConfiguration($configId, $data);
    break;
```

**Delete Configuration:**
```php
case 'wordpress_blog_delete_config':
    $configId = $_GET['configuration_id'] ?? null;

    if (!$configId) {
        http_response_code(400);
        echo json_encode(['error' => 'configuration_id required']);
        exit;
    }

    // ✅ Check ownership
    $hasPermission = $resourceAuth->canAccessResource(
        $user['user_id'],
        'wordpress_blog_configuration',
        $configId,
        'delete'
    );

    if (!$hasPermission) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $result = $configService->deleteConfiguration($configId);
    break;
```

**Get Configuration:**
```php
case 'wordpress_blog_get_config':
    $configId = $_GET['configuration_id'] ?? null;

    // ✅ Check read permission
    $hasPermission = $resourceAuth->canAccessResource(
        $user['user_id'],
        'wordpress_blog_configuration',
        $configId,
        'read'
    );

    if (!$hasPermission) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $result = $configService->getConfiguration($configId, true);
    break;
```

### Task 3: Record Resource Ownership on Creation
```php
case 'wordpress_blog_create_config':
    // Create configuration
    $configId = $configService->createConfiguration($data);

    // ✅ Record ownership in resource_permissions table
    $resourceAuth->grantAccess(
        $user['user_id'],
        'wordpress_blog_configuration',
        $configId,
        'owner' // Grant full ownership
    );

    // Log creation
    log_admin("User {$user['user_id']} created WordPress blog config {$configId}");

    echo json_encode([
        'success' => true,
        'configuration_id' => $configId
    ]);
    break;
```

### Task 4: Add Permission Checks to Queue Endpoints
```php
case 'wordpress_blog_queue_article':
    $configId = $data['configuration_id'] ?? null;

    // ✅ Verify user owns the configuration before queueing articles
    $hasPermission = $resourceAuth->canAccessResource(
        $user['user_id'],
        'wordpress_blog_configuration',
        $configId,
        'write'
    );

    if (!$hasPermission) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied: Cannot queue articles for this configuration']);
        exit;
    }

    // Queue the article
    $articleId = $queueService->queueArticle($data);

    // Record article ownership
    $resourceAuth->grantAccess(
        $user['user_id'],
        'wordpress_blog_article',
        $articleId,
        'owner'
    );

    echo json_encode(['success' => true, 'article_id' => $articleId]);
    break;
```

### Task 5: Add Shared Access Support (Optional)
For team collaboration features:

```php
case 'wordpress_blog_share_config':
    $configId = $data['configuration_id'] ?? null;
    $shareWithUserId = $data['user_id'] ?? null;
    $permission = $data['permission'] ?? 'read'; // read, write, admin

    // Check if current user is owner
    $isOwner = $resourceAuth->canAccessResource(
        $user['user_id'],
        'wordpress_blog_configuration',
        $configId,
        'owner'
    );

    if (!$isOwner) {
        http_response_code(403);
        echo json_encode(['error' => 'Only owners can share configurations']);
        exit;
    }

    // Grant access to other user
    $resourceAuth->grantAccess(
        $shareWithUserId,
        'wordpress_blog_configuration',
        $configId,
        $permission
    );

    echo json_encode(['success' => true]);
    break;
```

### Task 6: Create Tests for Authorization
```php
// tests/WordPressBlog/AuthorizationTest.php

final class AuthorizationTest extends TestCase
{
    public function testUserCanOnlyAccessOwnConfigurations(): void
    {
        // User A creates configuration
        $configIdA = $this->createConfigAs('user-a');

        // User B creates configuration
        $configIdB = $this->createConfigAs('user-b');

        // User A can access their own
        $this->assertTrue($this->canAccess('user-a', $configIdA));

        // User A cannot access User B's
        $this->assertFalse($this->canAccess('user-a', $configIdB));
    }

    public function testUnauthorizedUpdateReturns403(): void
    {
        $configId = $this->createConfigAs('user-a');

        // Try to update as user-b (should fail)
        $response = $this->updateConfigAs('user-b', $configId, [
            'config_name' => 'Hacked'
        ]);

        $this->assertEquals(403, $response['status']);
        $this->assertStringContainsString('Access denied', $response['body']['error']);
    }

    public function testOwnerCanShareConfiguration(): void
    {
        $configId = $this->createConfigAs('user-a');

        // User A shares with User B
        $this->shareConfig('user-a', $configId, 'user-b', 'write');

        // User B can now access
        $this->assertTrue($this->canAccess('user-b', $configId));
    }
}
```

## Acceptance Criteria
- [ ] `ResourceAuthService` integrated into admin-api.php
- [ ] All configuration CRUD operations check permissions
- [ ] All queue operations verify configuration ownership
- [ ] Resource ownership recorded on creation
- [ ] 403 responses for unauthorized access attempts
- [ ] Security events logged for unauthorized attempts
- [ ] Authorization tests pass
- [ ] Manual testing: User A cannot modify User B's resources
- [ ] Documentation updated

## Testing Steps
1. Create User A and User B in same tenant
2. User A creates a configuration
3. User B attempts to:
   - View User A's configuration (should fail with 403)
   - Update User A's configuration (should fail with 403)
   - Delete User A's configuration (should fail with 403)
   - Queue article using User A's configuration (should fail with 403)
4. User A can perform all operations on their own configuration
5. Test sharing feature (if implemented)
6. Verify audit logs contain unauthorized access attempts

## Related Issues
- Depends on: #TBD (Multi-tenancy support must be implemented first)
- Blocks: Production deployment
- Related to: Security audit compliance

## Estimated Effort
**6-8 hours**
- Integration with ResourceAuthService: 2 hours
- Update all endpoints: 3-4 hours
- Testing: 2-3 hours
- Documentation: 1 hour

## Additional Context
Identified in code review as Critical Issue #2. The existing codebase has `ResourceAuthService` implemented for other features (LeadSense CRM) - we need to apply the same pattern to WordPress Blog endpoints.

**Files to Reference**:
- `includes/ResourceAuthService.php` (existing implementation)
- LeadSense endpoints in `admin-api.php` (reference implementation)
