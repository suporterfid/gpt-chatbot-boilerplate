# LeadSense CRM Extension

## Overview

The LeadSense CRM extension adds visual pipeline and Kanban board capabilities to the existing LeadSense lead detection system. It provides CRM functionality similar to FluxCRM/FluxVolt CRM, enabling teams to manage leads through visual stages with drag-and-drop operations.

## Key Features

### 1. Visual Pipelines (Kanban Boards)
- Multiple pipelines for different workflows
- Visual stage columns (Lead Capture, Support, Commercial Lead, etc.)
- Drag-and-drop leads between stages
- Real-time board updates
- Stage-specific lead counts and colors

### 2. Lead Stage Management
- Automatic assignment of new leads to default pipeline
- Move leads between stages with full event tracking
- Stage properties: won, lost, closed indicators
- Customizable stage colors and ordering

### 3. Lead Attributes
- **Owner Assignment**: Assign leads to specific users/agents
- **Deal Tracking**: Value, currency, probability, expected close date
- **Tags**: Flexible tagging system for categorization
- **Timeline Events**: Full history of stage changes, updates, notes

### 4. Board Operations
- Search leads by name, email, company
- Filter by score threshold
- Inline editing from Kanban cards
- Add notes to lead timeline
- View lead details with full history

## Architecture

### Database Schema

#### Core Tables
1. **crm_pipelines** - Named pipelines (e.g., "Default", "Onboarding")
2. **crm_pipeline_stages** - Ordered stages within pipelines
3. **crm_lead_assignments** - Historical owner assignments
4. **crm_automation_rules** - Event-driven automation (placeholder)
5. **crm_automation_logs** - Automation execution history

#### Extended Tables
- **leads** - Extended with pipeline_id, stage_id, owner, deal fields

### Services

#### PipelineService
Manages pipelines and stages with tenant isolation.

```php
$pipelineService = new PipelineService($db, $tenantId);

// Create pipeline with stages
$pipeline = $pipelineService->createPipeline([
    'name' => 'Sales Pipeline',
    'description' => 'Main sales workflow',
    'is_default' => true,
    'color' => '#8b5cf6',
    'stages' => [
        ['name' => 'New', 'slug' => 'new', 'color' => '#a855f7'],
        ['name' => 'Qualified', 'slug' => 'qualified', 'color' => '#22c55e'],
        ['name' => 'Won', 'slug' => 'won', 'is_won' => true, 'is_closed' => true]
    ]
]);

// List pipelines
$pipelines = $pipelineService->listPipelines();

// Get default pipeline
$default = $pipelineService->getDefaultPipeline();
```

#### BoardService
Manages Kanban board operations.

```php
$boardService = new BoardService($db, $tenantId);

// Get board with leads
$board = $boardService->getLeadsBoard($pipelineId, [
    'min_score' => 80,
    'q' => 'enterprise'
]);

// Move lead between stages
$result = $boardService->moveLead($leadId, $fromStageId, $toStageId, $pipelineId, [
    'changed_by' => $userId,
    'changed_by_type' => 'admin_user'
]);

// Update lead inline
$result = $boardService->updateLeadInline($leadId, [
    'owner_id' => 'user123',
    'owner_type' => 'admin_user',
    'deal_value' => 50000.00,
    'currency' => 'USD',
    'probability' => 75,
    'tags' => ['hot', 'enterprise']
]);

// Add note
$result = $boardService->addNote($leadId, 'Customer requested demo', [
    'created_by' => $userId
]);
```

### Admin API Endpoints

All endpoints require authentication and are under the `leadsense.crm.*` namespace.

#### Pipeline Endpoints

**List Pipelines**
```
GET /admin-api.php?action=leadsense.crm.list_pipelines
Query: include_archived=true|false
Response: { data: { pipelines: [...] } }
```

**Get Pipeline**
```
GET /admin-api.php?action=leadsense.crm.get_pipeline&id={pipeline_id}
Response: { data: { pipeline: {...}, stages: [...] } }
```

**Create Pipeline**
```
POST /admin-api.php?action=leadsense.crm.create_pipeline
Body: {
  name: "Pipeline Name",
  description: "Description",
  is_default: true,
  color: "#8b5cf6",
  stages: [...]
}
Response: { data: { pipeline: {...}, stages: [...] } }
```

**Update Pipeline**
```
POST /admin-api.php?action=leadsense.crm.update_pipeline
Body: { id: "pipeline_id", name: "New Name", ... }
Response: { data: { pipeline: {...} } }
```

**Archive Pipeline**
```
POST /admin-api.php?action=leadsense.crm.archive_pipeline
Body: { id: "pipeline_id" }
Response: { data: { success: true } }
```

#### Stage Endpoints

**Save Stages (Bulk)**
```
POST /admin-api.php?action=leadsense.crm.save_stages
Body: {
  pipeline_id: "pipeline_id",
  stages: [
    { id: "stage_1", name: "Updated Name", position: 0 },
    { id: null, name: "New Stage", position: 1 }
  ]
}
Response: { data: { stages: [...] } }
```

**Archive Stage**
```
POST /admin-api.php?action=leadsense.crm.archive_stage
Body: { id: "stage_id" }
Response: { data: { success: true } }
```

#### Board Endpoints

**List Leads Board**
```
GET /admin-api.php?action=leadsense.crm.list_leads_board
Query:
  - pipeline_id: required
  - stage_ids: comma-separated (optional)
  - owner_id: filter by owner (optional)
  - owner_type: owner type (optional)
  - min_score: minimum score (optional)
  - q: search term (optional)
  - page, page_size: pagination (optional)
Response: {
  data: {
    pipeline: {...},
    stages: [
      {
        id: "stage_id",
        name: "Stage Name",
        lead_count: 49,
        leads: [...]
      }
    ]
  }
}
```

**Move Lead**
```
POST /admin-api.php?action=leadsense.crm.move_lead
Body: {
  lead_id: "lead_id",
  from_stage_id: "stage_1",
  to_stage_id: "stage_2",
  pipeline_id: "pipeline_id"
}
Response: { data: { lead: {...} } }
```

**Update Lead Inline**
```
POST /admin-api.php?action=leadsense.crm.update_lead_inline
Body: {
  id: "lead_id",
  owner_id: "user_id",
  owner_type: "admin_user",
  deal_value: 10000,
  currency: "BRL",
  probability: 70,
  expected_close_date: "2025-07-10",
  tags: ["hot", "enterprise"]
}
Response: { data: { lead: {...} } }
```

**Add Note**
```
POST /admin-api.php?action=leadsense.crm.add_note
Body: {
  lead_id: "lead_id",
  text: "Customer asked for follow-up demo"
}
Response: { data: { success: true } }
```

## Admin UI

### Accessing the CRM Board

1. Log in to Admin UI
2. Click "LeadSense CRM" in the sidebar navigation
3. Select pipeline from dropdown
4. View leads organized by stage

### UI Features

#### Pipeline Selector
- Dropdown to switch between pipelines
- "Create Pipeline" button for new pipelines
- Shows default pipeline by default

#### Board Filters
- **Search**: Filter leads by name, email, company
- **Score Filter**: Show only high/medium/low score leads
- Real-time filtering as you type

#### Kanban Columns
- Visual stage columns with custom colors
- Lead count per stage
- Scrollable when many leads
- Drag-and-drop enabled

#### Lead Cards
- Avatar with initials
- Lead name and owner
- Company and email
- Status and score badges
- Tags
- Timestamp (last activity or created)
- Click to view details (future enhancement)

#### Drag and Drop
1. Click and hold a lead card
2. Drag to target stage column
3. Release to drop
4. Board automatically updates via API

### Responsive Design

- **Desktop**: Full multi-column board view
- **Tablet**: Optimized column widths
- **Mobile**: Vertical stacked columns

## Integration with LeadSense

### Automatic Pipeline Assignment

When LeadSense detects a new qualified lead, it's automatically assigned to:
1. Default pipeline (where `is_default = 1`)
2. First stage (lowest position) in that pipeline

```php
// In LeadRepository::createLead()
$defaultPipelineAndStage = $this->getDefaultPipelineAndStage();
$leadData['pipeline_id'] = $leadData['pipeline_id'] ?? $defaultPipelineAndStage['pipeline_id'];
$leadData['stage_id'] = $leadData['stage_id'] ?? $defaultPipelineAndStage['stage_id'];
```

### Event Tracking

All CRM operations create events in the `lead_events` table:

- `stage_changed` - Lead moved between stages
- `owner_changed` - Owner assigned/changed
- `deal_updated` - Deal value/probability updated
- `note` - Note added to timeline
- `detected` - Lead initially created
- `qualified` - Lead qualified based on score

Event payload includes full context (old/new values, changed_by, etc.)

## Migration and Setup

### Running Migrations

```bash
php scripts/run_migrations.php
```

This will execute:
- 038_create_crm_pipelines.sql
- 039_create_crm_pipeline_stages.sql
- 040_create_crm_lead_assignments.sql
- 041_create_crm_automation_rules.sql
- 042_create_crm_automation_logs.sql
- 043_extend_leads_with_crm_fields.sql
- 044_relax_lead_events_constraint.sql
- 045_seed_default_pipeline.sql

### Seed Default Pipeline

The last migration (045) automatically creates a default pipeline with standard stages:
1. Lead Capture
2. Support
3. Commercial Lead
4. Negotiation
5. Closed Won
6. Closed Lost

## Testing

### Run All CRM Tests

```bash
# PipelineService tests
php tests/test_crm_pipeline_service.php

# BoardService tests
php tests/test_crm_board_service.php

# LeadSense integration tests
php tests/test_leadsense_crm_integration.php

# All tests
php tests/run_tests.php
```

### Test Coverage

- **PipelineService**: 20 test cases covering CRUD, validation, defaults
- **BoardService**: 15 test cases covering board views, moves, updates, notes
- **LeadSense Integration**: 9 test cases covering auto-assignment, updates

## Security Considerations

### Authentication
All CRM endpoints require authentication via:
- Session cookie (admin_session)
- API key (Bearer token)

### Authorization
- RBAC permissions enforced via `requirePermission()`
- `read` - View pipelines and boards
- `create` - Create pipelines and stages
- `update` - Modify pipelines, stages, move leads
- `delete` - Archive pipelines and stages

### Tenant Isolation
- All services support tenant context
- Queries filtered by `client_id` or `tenant_id`
- Cross-tenant access prevented

### Input Validation
- All user inputs sanitized
- SQL injection prevented via prepared statements
- XSS prevented by escaping output in UI

## Future Enhancements (Not in MVP)

### Phase 2
- Task management (meetings, follow-ups)
- Lead detail drawer with full timeline
- Bulk operations (bulk move, bulk assign)
- Advanced filters (date ranges, custom fields)

### Phase 3
- Visual automation builder
- Analytics dashboard (funnel metrics, conversion rates)
- Multi-channel view (WhatsApp, Email conversations)
- Activity reminders and notifications

### Phase 4
- Forecasting and revenue predictions
- Team collaboration features
- Integration with external CRMs (HubSpot, Salesforce)
- Advanced reporting

## Troubleshooting

### Leads not appearing in board
- Check if lead has `pipeline_id` and `stage_id` set
- Verify pipeline is not archived
- Check tenant context matches

### Cannot move lead
- Verify both stages belong to same pipeline
- Check user has `update` permission
- Ensure lead exists and is in from_stage

### Drag and drop not working
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify API endpoint is accessible

### Pipeline not showing as default
- Only one pipeline can be default per tenant
- Use `setDefaultPipeline()` to change default

## API Rate Limits

Admin API endpoints follow standard rate limits:
- 300 requests per minute per tenant
- 60 second window
- Rate limit tracked per tenant_id or user_id

## Performance Considerations

### Board Loading
- Pagination supported (50 leads per stage by default)
- Indexes on `pipeline_id`, `stage_id` for fast queries
- Consider lazy loading for large datasets

### Database Indexes
All critical paths indexed:
- `leads(pipeline_id, stage_id)`
- `leads(owner_id, owner_type)`
- `crm_pipeline_stages(pipeline_id, position)`

### Caching (Future)
- Pipeline metadata can be cached
- Board data should remain fresh for real-time updates

## Monitoring

### Metrics to Track
- Number of pipelines per tenant
- Average leads per stage
- Lead movement frequency
- API response times for board operations

### Logs
All operations logged via `log_admin()`:
- Pipeline creation/updates
- Lead movements
- Errors and exceptions

## Conclusion

The LeadSense CRM extension provides production-ready visual pipeline management while maintaining the simplicity and flexibility of the existing LeadSense system. It follows the project's architectural patterns and integrates seamlessly with existing authentication, multi-tenancy, and API infrastructure.
