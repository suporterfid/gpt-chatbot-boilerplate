# Task 9: Implement Pipeline Management API

## Objective
Add admin-api.php endpoints for pipeline management using PipelineService.

## Admin API Actions

### Pipelines
- `leadsense.crm.list_pipelines` - GET
- `leadsense.crm.get_pipeline` - GET (id param)
- `leadsense.crm.create_pipeline` - POST
- `leadsense.crm.update_pipeline` - POST
- `leadsense.crm.archive_pipeline` - POST
- `leadsense.crm.set_default_pipeline` - POST

### Stages
- `leadsense.crm.save_stages` - POST (bulk update)
- `leadsense.crm.archive_stage` - POST

## Implementation Pattern
```php
case 'leadsense.crm.list_pipelines':
    requirePermission('leadsense.crm.read');
    $pipelineService = new PipelineService($db, $tenantId);
    $pipelines = $pipelineService->listPipelines();
    echo json_encode(['pipelines' => $pipelines]);
    break;
```

## Authentication
All endpoints require admin authentication (existing pattern).

## Prerequisites
- Task 5: PipelineService

## Testing
- curl tests for each endpoint
- Add to tests/test_admin_api.php
