# Task 11: API Authentication & Authorization

## Objective
Add proper authentication and permission checks for CRM endpoints.

## Permissions to Add

### Resource Permissions
Using existing ResourceAuthService pattern:
- `leadsense.crm.read` - View pipelines and leads
- `leadsense.crm.write` - Create/update pipelines and leads
- `leadsense.crm.admin` - Delete/archive, manage automation

### Role Mapping
- `viewer`: read only
- `editor`: read + write
- `admin`: full access
- `super-admin`: full access all tenants

## Implementation
```php
// In admin-api.php before each CRM action
function requireCRMPermission($action) {
    global $authenticatedUser, $resourceAuthService;
    
    $permission = match($action) {
        'list_pipelines', 'get_pipeline', 'list_leads_board' => 'leadsense.crm.read',
        'create_pipeline', 'update_pipeline', 'move_lead' => 'leadsense.crm.write',
        'archive_pipeline', 'delete_rule' => 'leadsense.crm.admin',
        default => 'leadsense.crm.read'
    };
    
    if (!$resourceAuthService->hasPermission($authenticatedUser['id'], $permission)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
}
```

## Prerequisites
- Existing: AdminAuth, ResourceAuthService
- Tasks 9-10: API endpoints

## Testing
- Test with different roles
- Test tenant isolation
