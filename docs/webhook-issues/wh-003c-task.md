Allow operators to create, update, list, and deactivate subscribers via admin UI/API.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§2 & 8

**Deliverables:**  
- API endpoints  
- Form validation  
- UI components using the repository

**Implementation Guidance:**
The `admin-api.php` already provides a RESTful interface for managing agents. New actions for `create_subscriber`, `update_subscriber`, etc., should be added here, following the existing pattern of using a switch on the `action` parameter. The Admin UI will then use these new API endpoints.

```php
// In admin-api.php
switch ($_GET['action']) {
    case 'create_agent':
        // ... existing logic
        break;
    case 'create_subscriber':
        // 1. Get data from POST body
        // 2. Validate input
        // 3. Call WebhookSubscriberRepository->save()
        // 4. Return JSON response
        break;
    // ... other subscriber actions
}
```
The front-end changes will be in `/public/admin/` to add a new section for webhook subscriber management.