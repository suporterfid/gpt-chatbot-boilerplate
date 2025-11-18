# LeadSense CRM Extensions - Implementation Tasks

This directory contains detailed implementation tasks for adding CRM capabilities to LeadSense, as specified in the LeadSense CRM Extensions Functional & Technical Specification.

## Task Overview

### Phase 1: Database Schema (Tasks 1-4) ✅

**Task 1: Create CRM Tables Migration** - [task-01-create-crm-tables-migration.md](task-01-create-crm-tables-migration.md)
- Create 5 new tables: crm_pipelines, crm_pipeline_stages, crm_lead_assignments, crm_automation_rules, crm_automation_logs
- Files: migrations 038-042
- Status: Ready for implementation

**Task 2: Extend Leads Table** - [task-02-extend-leads-table-migration.md](task-02-extend-leads-table-migration.md)
- Add CRM fields to leads table: pipeline_id, stage_id, owner_id, owner_type, deal fields, tags
- File: migration 043
- Status: Ready for implementation

**Task 3: Add New Lead Events Types** - [task-03-add-new-lead-events-types.md](task-03-add-new-lead-events-types.md)
- Document new event types: stage_changed, owner_changed, pipeline_changed, deal_updated
- Update LeadRepository with event recording methods
- File: includes/LeadSense/LeadEventTypes.php
- Status: Ready for implementation

**Task 4: Default Pipeline Seeding** - [task-04-create-default-pipeline-seeding.md](task-04-create-default-pipeline-seeding.md)
- Create seeding scripts for default pipeline with 6 standard stages
- Files: migration 045, scripts/seed_default_pipeline.php, scripts/backfill_existing_leads.php
- Status: Ready for implementation

### Phase 2: Backend Services (Tasks 5-8)

**Task 5: CRM Pipeline Service** - [task-05-create-crm-pipeline-service.md](task-05-create-crm-pipeline-service.md)
- Create PipelineService for managing pipelines and stages
- File: includes/LeadSense/CRM/PipelineService.php
- Methods: CRUD for pipelines, CRUD for stages, reordering, validation
- Status: Ready for implementation

**Task 6: CRM Lead Management Service** - Details below
- Create LeadManagementService for CRM operations on leads
- File: includes/LeadSense/CRM/LeadManagementService.php
- Methods: Move leads between stages, assign owners, update deal fields, add notes
- Status: Task brief created

**Task 7: CRM Automation Service** - Details below
- Create AutomationService for rule evaluation and execution
- File: includes/LeadSense/CRM/AutomationService.php
- Methods: Rule CRUD, trigger evaluation, action execution stub
- Status: Task brief created

**Task 8: Extend LeadRepository** - Details below
- Update LeadRepository for CRM field support
- File: includes/LeadSense/LeadRepository.php
- Updates: Add CRM field queries, pipeline/stage filters, owner queries
- Status: Task brief created

### Phase 3: Admin API Endpoints (Tasks 9-11)

**Task 9: Pipeline Management API** - Details below
- Add admin-api.php endpoints for pipeline operations
- Actions: leadsense.crm.list_pipelines, get_pipeline, create_pipeline, update_pipeline, archive_pipeline, save_stages
- Status: Task brief created

**Task 10: Kanban Board API** - Details below
- Add admin-api.php endpoints for Kanban board operations
- Actions: leadsense.crm.list_leads_board, move_lead, update_lead_inline, add_note
- Status: Task brief created

**Task 11: API Authentication & Authorization** - Details below
- Add permission checks for CRM endpoints
- Use existing AdminAuth and ResourceAuthService
- Status: Task brief created

### Phase 4: Admin UI Components (Tasks 12-15)

**Task 12: LeadSense CRM Page Structure** - Details below
- Create admin CRM page entry point
- Files: public/admin/leadsense_crm.php, navigation updates
- Status: Task brief created

**Task 13: JavaScript Kanban Board** - Details below
- Create Kanban board JavaScript component
- File: public/admin/js/leadsense-crm.js
- Components: Pipeline selector, board columns, lead cards, drag-and-drop
- Status: Task brief created

**Task 14: Lead Detail Drawer** - Details below
- Create lead detail side panel/modal
- Components: Overview tab, Timeline tab, inline editing
- Status: Task brief created

**Task 15: CRM UI Styling** - Details below
- Create CSS for CRM components
- File: public/admin/leadsense-crm.css
- Styles: Kanban board, cards, modal, responsive design
- Status: Task brief created

### Phase 5: Integration (Tasks 16-17)

**Task 16: LeadSense Pipeline Integration** - Details below
- Integrate CRM with existing LeadSense detection
- Update: includes/LeadSense/LeadSenseService.php
- Features: Auto-assign new leads to pipeline/stage
- Status: Task brief created

**Task 17: Basic Automation Hooks** - Details below
- Implement automation trigger evaluation
- Update: LeadSenseService to call AutomationService
- Features: Webhook integration for automation
- Status: Task brief created

### Phase 6: Documentation (Task 18)

**Task 18: Comprehensive Documentation** - Details below
- Create user and developer documentation
- Files: docs/leadsense-crm-api.md, docs/leadsense-crm-guide.md, docs/leadsense-crm-migration.md
- Status: Task brief created

### Phase 7: Testing (Task 19)

**Task 19: Test Suite** - Details below
- Create comprehensive test suite for CRM features
- Files: tests/test_crm_*.php
- Coverage: Services, API endpoints, integration tests
- Status: Task brief created

## Implementation Order

Follow this sequence for smooth implementation:

1. **Phase 1** (Tasks 1-4): Database foundation
   - Run all migrations
   - Verify schema
   - Seed default data

2. **Phase 2** (Tasks 5-8): Backend services
   - Build from bottom up: PipelineService → LeadManagementService → AutomationService
   - Test each service independently

3. **Phase 3** (Tasks 9-11): API layer
   - Implement endpoints using services
   - Add authentication checks
   - Test with curl/Postman

4. **Phase 4** (Tasks 12-15): UI components
   - Build page structure first
   - Add JavaScript interactivity
   - Style and polish

5. **Phase 5** (Tasks 16-17): Integration
   - Connect CRM to LeadSense detection
   - Enable automation hooks

6. **Phase 6-7** (Tasks 18-19): Documentation and Testing
   - Document as you go
   - Add tests throughout
   - Final comprehensive test pass

## Quick Reference

### Key Files Created
```
db/migrations/
  038_create_crm_pipelines.sql
  039_create_crm_pipeline_stages.sql
  040_create_crm_lead_assignments.sql
  041_create_crm_automation_rules.sql
  042_create_crm_automation_logs.sql
  043_extend_leads_with_crm_fields.sql
  045_seed_default_pipeline.sql

scripts/
  seed_default_pipeline.php
  backfill_existing_leads.php

includes/LeadSense/
  LeadEventTypes.php
  CRM/
    PipelineService.php
    LeadManagementService.php
    AutomationService.php

public/admin/
  leadsense_crm.php
  js/
    leadsense-crm.js
    lead-timeline.js
  css/
    leadsense-crm.css

tests/
  test_pipeline_service.php
  test_lead_management_service.php
  test_crm_api.php
  test_kanban_board.php
```

### Configuration Updates

Add to `config.php`:
```php
'leadsense' => [
    'enabled' => true,
    // ... existing config ...
    
    'crm' => [
        'enabled' => true,
        'default_pipeline_name' => 'Default',
        'auto_assign_new_leads' => true,
        'default_stage_slug' => 'lead_capture',
        'automation_enabled' => true
    ]
]
```

### Admin API Endpoints Summary

#### Pipeline Management
- `GET  /admin-api.php?action=leadsense.crm.list_pipelines`
- `GET  /admin-api.php?action=leadsense.crm.get_pipeline&id={id}`
- `POST /admin-api.php?action=leadsense.crm.create_pipeline`
- `POST /admin-api.php?action=leadsense.crm.update_pipeline`
- `POST /admin-api.php?action=leadsense.crm.archive_pipeline`
- `POST /admin-api.php?action=leadsense.crm.save_stages`

#### Kanban Board
- `GET  /admin-api.php?action=leadsense.crm.list_leads_board&pipeline_id={id}`
- `POST /admin-api.php?action=leadsense.crm.move_lead`
- `POST /admin-api.php?action=leadsense.crm.update_lead_inline`
- `POST /admin-api.php?action=leadsense.crm.add_note`

#### Automation
- `GET  /admin-api.php?action=leadsense.crm.list_automation_rules`
- `POST /admin-api.php?action=leadsense.crm.create_automation_rule`
- `POST /admin-api.php?action=leadsense.crm.update_automation_rule`

## Testing Strategy

### Unit Tests
- PipelineService methods
- LeadManagementService methods
- Event recording logic
- Validation functions

### Integration Tests
- API endpoint responses
- Database transactions
- Multi-tenant isolation
- Event chain execution

### UI Tests
- Drag-and-drop functionality
- Pipeline switching
- Lead card updates
- Timeline rendering

### Load Tests
- Board with 100+ leads per stage
- Concurrent stage movements
- Automation rule execution

## Dependencies

### Existing Systems
- LeadSense (leads, lead_events, lead_scores tables)
- Admin API (admin-api.php)
- Admin UI (public/admin/)
- Database abstraction (includes/DB.php)
- Authentication (includes/AdminAuth.php)

### New Dependencies
- None! All using existing PHP/JS stack

## Migration Path

### For New Installations
1. Run all migrations (038-045)
2. Seeding happens automatically
3. CRM ready to use immediately

### For Existing LeadSense Users
1. Run migrations (extends existing tables)
2. Backfill existing leads to default pipeline
3. No disruption to existing functionality
4. CRM features opt-in via UI

## Success Criteria

- [ ] All migrations run without errors
- [ ] Default pipeline with 6 stages created
- [ ] Existing leads assigned to pipeline
- [ ] All API endpoints functional
- [ ] Kanban board loads and displays leads
- [ ] Drag-and-drop moves leads between stages
- [ ] Lead events recorded correctly
- [ ] Timeline displays event history
- [ ] Automation rules can be created
- [ ] Webhook integration works
- [ ] All tests pass
- [ ] Documentation complete

## Support & Resources

- **Specification**: Main spec document in problem statement
- **Existing Docs**: docs/leadsense-*.md
- **Similar Code**: includes/AgentService.php, includes/PromptService.php
- **UI Patterns**: public/admin/admin.js, chatbot-enhanced.js

## Notes

- Maintain backward compatibility with existing LeadSense
- Use existing authentication/authorization patterns
- Follow PSR-12 coding standards
- Keep DB queries portable (SQLite/MySQL/PostgreSQL)
- Prioritize MVP features over advanced automation
- Focus on clean, maintainable code
- Document as you implement
