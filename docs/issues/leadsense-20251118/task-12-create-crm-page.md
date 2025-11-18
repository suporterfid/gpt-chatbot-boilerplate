# Task 12: Create LeadSense CRM Page Structure

## Objective
Create the admin page entry point for LeadSense CRM module.

## Files to Create

### 1. `public/admin/leadsense_crm.php`
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/AdminAuth.php';

// Check authentication
$adminAuth = new AdminAuth($config);
session_start();
$user = $adminAuth->getCurrentUser();

if (!$user) {
    header('Location: /public/admin/');
    exit;
}

// Get default pipeline for initialization
require_once __DIR__ . '/../../includes/LeadSense/CRM/PipelineService.php';
$db = Database::getInstance();
$pipelineService = new PipelineService($db, $user['tenant_id'] ?? null);
$defaultPipeline = $pipelineService->getDefaultPipeline();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LeadSense CRM - Admin</title>
    <link rel="stylesheet" href="/public/admin/admin.css">
    <link rel="stylesheet" href="/public/admin/leadsense-crm.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar - reuse from existing admin -->
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div id="leadsense-crm-root" 
                 data-default-pipeline-id="<?= htmlspecialchars($defaultPipeline['id'] ?? '') ?>"
                 data-user-id="<?= htmlspecialchars($user['id']) ?>">
                <div class="loading">Loading LeadSense CRM...</div>
            </div>
        </div>
    </div>
    
    <script src="/public/admin/js/leadsense-crm.js"></script>
</body>
</html>
```

### 2. Update Admin Navigation
In `public/admin/includes/sidebar.php` (or wherever nav is):
```html
<nav class="sidebar-nav">
    <a href="/public/admin/">Dashboard</a>
    <a href="/public/admin/?page=agents">Agents</a>
    <a href="/public/admin/leadsense_crm.php" class="nav-item">
        <i class="icon-kanban"></i>
        LeadSense CRM
    </a>
    <!-- ... other items ... -->
</nav>
```

## Prerequisites
- Existing admin page structure
- Task 5: PipelineService

## Next Steps
- Task 13: JavaScript implementation
- Task 15: CSS styling
