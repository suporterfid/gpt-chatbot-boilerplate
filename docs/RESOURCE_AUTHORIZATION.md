# Resource-Level Authorization

## Overview

This document describes the resource-level authorization system that extends the existing RBAC (Role-Based Access Control) model with per-resource access controls for true multi-tenant SaaS scenarios.

## Problem Statement

Traditional role-based authorization (admin, viewer, etc.) is insufficient for multi-tenant SaaS applications where:
- Users should only access resources explicitly available to them
- Cross-tenant access should be denied by default
- Explicit resource sharing must be possible in controlled scenarios
- All access attempts should be audited

## Architecture

### Components

1. **ResourceAuthService** (`includes/ResourceAuthService.php`)
   - Core authorization logic for resource-level checks
   - Integrates with existing AdminAuth for RBAC
   - Manages resource permissions and audit logging

2. **Database Schema**
   - `resource_permissions` table for explicit per-user/per-resource grants
   - Modified `audit_events` to support system-level events

3. **Admin API Integration**
   - All protected endpoints enforce resource-level checks
   - New endpoints for managing permissions

## Resource Types

The system supports the following resource types:

- `agent` - AI agent configurations
- `prompt` - OpenAI prompt references
- `vector_store` - Vector store configurations
- `conversation` - Conversation audit trails
- `file` - Uploaded files
- `webhook` - Webhook events

## Actions

- `read` - View resource details
- `create` - Create new resources
- `update` - Modify existing resources
- `delete` - Remove resources
- `execute` - Execute/use resources (e.g., run an agent)

## Authorization Flow

### 1. Role-Based Check (RBAC)
First, the system verifies the user has the required permission based on their role:

```php
// Roles and their permissions
const PERMISSIONS = [
    'viewer' => ['read'],
    'admin' => ['read', 'create', 'update', 'delete'],
    'super-admin' => ['read', 'create', 'update', 'delete', 'manage_users', 'rotate_tokens']
];
```

### 2. Resource-Level Check
If RBAC passes, the system checks resource-level access:

**Tenant Isolation (Default)**
- Users can only access resources in their tenant
- Super-admins can access resources across all tenants

**Explicit Permissions (Optional)**
- Super-admins can grant cross-tenant access
- Permissions stored in `resource_permissions` table
- Override tenant boundaries for controlled sharing

## Usage Examples

### PHP API Usage

```php
// Initialize services
$resourceAuth = new ResourceAuthService($db, $adminAuth, $auditService);

// Check if user can access a resource
$canAccess = $resourceAuth->canAccessResource(
    $user,                                  // Authenticated user
    ResourceAuthService::RESOURCE_AGENT,    // Resource type
    $agentId,                              // Resource ID
    ResourceAuthService::ACTION_READ        // Action
);

// Require access or throw exception
$resourceAuth->requireResourceAccess(
    $user,
    ResourceAuthService::RESOURCE_AGENT,
    $agentId,
    ResourceAuthService::ACTION_UPDATE
);

// Grant explicit permission to user
$permission = $resourceAuth->grantResourcePermission(
    $userId,                               // User to grant access to
    ResourceAuthService::RESOURCE_AGENT,   // Resource type
    $agentId,                             // Resource ID
    ['read', 'update'],                   // Permissions
    $grantedByUserId                      // Who granted it
);

// Revoke permission
$resourceAuth->revokeResourcePermission($permissionId);

// List permissions for a resource
$permissions = $resourceAuth->listResourcePermissions(
    ResourceAuthService::RESOURCE_AGENT,
    $agentId
);
```

### REST API Endpoints

#### Grant Resource Permission

```bash
curl -X POST "http://localhost/admin-api.php?action=grant_resource_permission" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user-uuid",
    "resource_type": "agent",
    "resource_id": "agent-uuid",
    "permissions": ["read", "update"]
  }'
```

Response:
```json
{
  "data": {
    "id": "permission-uuid",
    "user_id": "user-uuid",
    "resource_type": "agent",
    "resource_id": "agent-uuid",
    "permissions": ["read", "update"],
    "granted_by": "admin-uuid",
    "created_at": "2025-01-01T12:00:00Z"
  }
}
```

#### Revoke Resource Permission

```bash
curl -X POST "http://localhost/admin-api.php?action=revoke_resource_permission&permission_id=PERMISSION_ID" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

#### List Resource Permissions

```bash
curl -X GET "http://localhost/admin-api.php?action=list_resource_permissions&resource_type=agent&resource_id=AGENT_ID" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

Response:
```json
{
  "data": [
    {
      "id": "permission-uuid",
      "user_id": "user-uuid",
      "user_email": "user@example.com",
      "resource_type": "agent",
      "resource_id": "agent-uuid",
      "permissions": ["read", "update"],
      "granted_by": "admin-uuid",
      "is_active": true,
      "created_at": "2025-01-01T12:00:00Z"
    }
  ]
}
```

## Integration with Existing Code

### Admin API

All protected endpoints automatically enforce resource-level checks:

```php
case 'get_agent':
    requirePermission($authenticatedUser, 'read', $adminAuth);
    
    // Resource-level authorization check
    $resourceAuth->requireResourceAccess(
        $authenticatedUser, 
        ResourceAuthService::RESOURCE_AGENT, 
        $agentId, 
        ResourceAuthService::ACTION_READ
    );
    
    $agent = $agentService->getAgent($agentId);
    sendResponse($agent);
    break;
```

### Audit Logging

Access denied events are automatically logged:

```php
// Event logged when access is denied
{
  "event_type": "access_denied",
  "user_id": "user-uuid",
  "user_email": "user@example.com",
  "resource_type": "agent",
  "resource_id": "agent-uuid",
  "action": "update",
  "tenant_id": "tenant-uuid",
  "ip_address": "192.168.1.1",
  "user_agent": "Mozilla/5.0..."
}
```

## Security Considerations

### Tenant Isolation
- Users can only access resources in their tenant by default
- Cross-tenant access requires explicit permission grants
- Super-admins are the only ones who can grant cross-tenant access

### Permission Escalation Prevention
- Role permissions are checked before resource permissions
- A viewer with explicit resource access still cannot update (RBAC blocks it)
- Only admins+ can grant permissions

### Audit Trail
- All denied access attempts are logged
- Includes user identity, resource, action, and context
- Use for security monitoring and compliance

## Database Schema

### resource_permissions Table

```sql
CREATE TABLE resource_permissions (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    resource_type TEXT NOT NULL CHECK(resource_type IN (
        'agent', 'prompt', 'vector_store', 'conversation', 'file', 'webhook'
    )),
    resource_id TEXT NOT NULL,
    permissions_json TEXT NOT NULL, -- JSON array: ["read", "update", ...]
    granted_by TEXT NOT NULL,       -- User ID who granted permission
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

### Indexes

- `idx_resource_permissions_user_id` - Fast user lookup
- `idx_resource_permissions_resource` - Fast resource lookup
- `idx_resource_permissions_active` - Filter active permissions
- `idx_resource_permissions_lookup` - Composite for permission checks

## Common Scenarios

### Scenario 1: Cross-Tenant Collaboration
A super-admin wants to allow a user from Tenant A to access a specific agent from Tenant B:

```php
$resourceAuth->grantResourcePermission(
    $userFromTenantA,
    ResourceAuthService::RESOURCE_AGENT,
    $tenantBAgentId,
    ['read', 'execute'],
    $superAdminId
);
```

### Scenario 2: Temporary Access
Grant temporary access to a consultant:

```php
// Grant access
$permission = $resourceAuth->grantResourcePermission(
    $consultantId,
    ResourceAuthService::RESOURCE_VECTOR_STORE,
    $vectorStoreId,
    ['read'],
    $adminId
);

// Later, revoke when work is done
$resourceAuth->revokeResourcePermission($permission['id']);
```

### Scenario 3: Audit Review
Review who has been denied access to sensitive resources:

```php
$deniedEvents = $db->query(
    "SELECT * FROM audit_events 
     WHERE type = 'access_denied' 
     AND json_extract(payload_json, '$.resource_type') = ? 
     AND json_extract(payload_json, '$.resource_id') = ?
     ORDER BY created_at DESC",
    [ResourceAuthService::RESOURCE_AGENT, $sensitiveAgentId]
);
```

## Testing

Run the comprehensive test suite:

```bash
php tests/test_resource_authorization.php
```

Tests cover:
- Tenant isolation
- Same-tenant access control
- Explicit permission grants and revokes
- RBAC integration
- Audit logging
- Cross-tenant access scenarios

## Migration Guide

Existing deployments will maintain backward compatibility:
- All existing users retain access to resources in their tenant
- No explicit permissions needed for same-tenant access
- Super-admins continue to have full access

To enable cross-tenant sharing:
1. Identify resources to be shared
2. Use `grant_resource_permission` API
3. Monitor audit logs for access patterns

## Performance Considerations

- Resource checks add minimal overhead (single DB query)
- Indexes optimize permission lookups
- Tenant-level checks short-circuit for same-tenant access
- Super-admins bypass resource checks entirely

## Future Enhancements

Potential improvements:
- Group-based permissions (share with team, not individual users)
- Time-limited permissions (auto-expire after duration)
- Permission templates (preset permission sets)
- UI for permission management in admin panel
- Resource ownership transfer between tenants

## Related Documentation

- [Multi-Tenancy Architecture](MULTI_TENANCY.md)
- [Admin API Reference](api.md)
- [Audit Implementation](../AUDIT_IMPLEMENTATION.md)
