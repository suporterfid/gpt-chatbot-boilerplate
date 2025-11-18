# LeadSense CRM Extensions - Task Index

Quick navigation for all implementation tasks.

## 📖 Start Here

1. **[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)** - Executive overview, technical specs, design decisions
2. **[README.md](README.md)** - Quick reference guide with configuration and API summary

## 🗂️ Tasks by Phase

### Phase 1: Database Schema
- **[Task 1: Create CRM Tables Migration](task-01-create-crm-tables-migration.md)**
  - Create 5 new tables: pipelines, stages, assignments, automation rules/logs
  - Files: migrations 038-042
  - Time: ~2 hours

- **[Task 2: Extend Leads Table](task-02-extend-leads-table-migration.md)**
  - Add pipeline_id, stage_id, owner fields, deal fields, tags
  - File: migration 043
  - Time: ~1 hour

- **[Task 3: Add New Lead Event Types](task-03-add-new-lead-events-types.md)**
  - Document stage_changed, owner_changed, pipeline_changed, deal_updated events
  - File: includes/LeadSense/LeadEventTypes.php
  - Time: ~2 hours

- **[Task 4: Default Pipeline Seeding](task-04-create-default-pipeline-seeding.md)**
  - Create seeding and backfill scripts
  - Files: migration 045, scripts/seed_default_pipeline.php, scripts/backfill_existing_leads.php
  - Time: ~3 hours

**Phase 1 Total: ~8 hours**

### Phase 2: Backend Services
- **[Task 5: CRM Pipeline Service](task-05-create-crm-pipeline-service.md)**
  - CRUD for pipelines and stages, reordering, validation
  - File: includes/LeadSense/CRM/PipelineService.php (~400 lines)
  - Time: ~8 hours

- **[Task 6: CRM Lead Management Service](task-06-create-lead-management-service.md)**
  - Stage movement, owner assignment, deal tracking, notes
  - File: includes/LeadSense/CRM/LeadManagementService.php (~350 lines)
  - Time: ~6 hours

- **[Task 7: CRM Automation Service](task-07-create-automation-service.md)**
  - Rule CRUD, trigger evaluation, action execution
  - File: includes/LeadSense/CRM/AutomationService.php (~300 lines)
  - Time: ~6 hours

- **[Task 8: Extend LeadRepository](task-08-extend-lead-repository.md)**
  - Add CRM field support, pipeline/stage queries
  - File: includes/LeadSense/LeadRepository.php (extensions)
  - Time: ~4 hours

**Phase 2 Total: ~24 hours**

### Phase 3: Admin API Endpoints
- **[Task 9: Pipeline Management API](task-09-implement-pipeline-api.md)**
  - Endpoints: list, get, create, update, archive pipelines/stages
  - File: admin-api.php additions (~250 lines)
  - Time: ~4 hours

- **[Task 10: Kanban Board API](task-10-implement-kanban-api.md)**
  - Endpoints: list_leads_board, move_lead, update_lead_inline, add_note
  - File: admin-api.php additions (~250 lines)
  - Time: ~4 hours

- **[Task 11: API Authorization](task-11-api-authorization.md)**
  - Add permission checks for all CRM endpoints
  - File: admin-api.php additions
  - Time: ~2 hours

**Phase 3 Total: ~10 hours**

### Phase 4: Admin UI Components
- **[Task 12: LeadSense CRM Page](task-12-create-crm-page.md)**
  - Create admin page entry point and navigation
  - File: public/admin/leadsense_crm.php
  - Time: ~2 hours

- **[Task 13: JavaScript Kanban Board](task-13-create-kanban-javascript.md)**
  - Pipeline selector, board rendering, drag-and-drop
  - File: public/admin/js/leadsense-crm.js (~800 lines)
  - Time: ~12 hours

- **[Task 14: Lead Detail Drawer](task-14-create-lead-drawer.md)**
  - Overview tab, timeline tab, inline editing
  - Part of: public/admin/js/leadsense-crm.js (~200 lines)
  - Time: ~6 hours

- **[Task 15: CRM UI Styling](task-15-create-crm-css.md)**
  - Kanban board, cards, drawer, responsive design
  - File: public/admin/leadsense-crm.css (~400 lines)
  - Time: ~4 hours

**Phase 4 Total: ~24 hours**

### Phase 5: Integration
- **[Task 16: LeadSense Integration](task-16-integrate-with-leadsense.md)**
  - Auto-assign new leads, stage progression, automation triggers
  - File: includes/LeadSense/LeadSenseService.php (extensions)
  - Time: ~4 hours

- **[Task 17: Automation Hooks](task-17-implement-automation-hooks.md)**
  - Complete action execution, webhook/Slack integration
  - File: includes/LeadSense/CRM/AutomationService.php (completions)
  - Time: ~4 hours

**Phase 5 Total: ~8 hours**

### Phase 6: Documentation
- **[Task 18: Documentation](task-18-create-documentation.md)**
  - API reference, user guide, migration guide, architecture docs
  - Files: docs/leadsense-crm-*.md (4 files)
  - Time: ~8 hours

**Phase 6 Total: ~8 hours**

### Phase 7: Testing
- **[Task 19: Test Suite](task-19-create-test-suite.md)**
  - Unit tests, integration tests, API tests
  - Files: tests/test_crm_*.php (6 files, ~750 lines)
  - Time: ~12 hours

**Phase 7 Total: ~12 hours**

## ⏱️ Total Estimated Time

| Phase | Hours |
|-------|-------|
| Phase 1: Database | 8 |
| Phase 2: Backend | 24 |
| Phase 3: API | 10 |
| Phase 4: UI | 24 |
| Phase 5: Integration | 8 |
| Phase 6: Documentation | 8 |
| Phase 7: Testing | 12 |
| **Total** | **94 hours** |

**~2.5 weeks** for a single developer working full-time

## 🎯 Critical Path

Tasks that must be completed sequentially:

1. Task 1 → Task 2 → Task 3 → Task 4 (Database foundation)
2. Task 5 → Task 6 → Task 7 (Services depend on each other)
3. Task 9 → Task 10 (APIs use services)
4. Task 13 → Task 14 (Drawer uses board infrastructure)

## 🔄 Parallel Opportunities

Tasks that can be done in parallel:

- **After Phase 1:** Tasks 5, 6, 7, 8 can be done simultaneously
- **After Phase 2:** Tasks 9, 10, 11 can be done simultaneously
- **After Phase 3:** Tasks 12, 13, 15 can start (14 depends on 13)
- **Anytime:** Task 18 (documentation) can be done alongside implementation
- **Near End:** Task 19 (testing) can start as soon as there's code to test

## 📝 Task File Format

Each task file includes:

```markdown
# Task N: Title

## Objective
What this task accomplishes

## Prerequisites
What must be done first

## File(s) to Create/Modify
Exact file paths

## Implementation Details
Code snippets, specifications, examples

## Testing
How to verify completion

## Related Tasks
What tasks are connected
```

## 🚦 Getting Started

### For AI Agents
1. Start with Task 1
2. Follow sequential order within phases
3. Check prerequisites before starting each task
4. Use provided code examples as templates
5. Test thoroughly after each task

### For Human Developers
1. Read IMPLEMENTATION_SUMMARY.md for context
2. Review README.md for configuration
3. Start with Phase 1 (Database)
4. Work through tasks in order
5. Refer to individual task files for details

## 📊 Progress Tracking

Use this checklist to track completion:

### Phase 1: Database ✅
- ✅ Task 1: CRM Tables
- ✅ Task 2: Extend Leads
- ✅ Task 3: Event Types
- ✅ Task 4: Seeding

### Phase 2: Backend 🔄
- ✅ Task 5: Pipeline Service
- ☐ Task 6: Lead Management
- ☐ Task 7: Automation Service
- ☐ Task 8: LeadRepository

### Phase 3: API ☐
- ☐ Task 9: Pipeline API
- ☐ Task 10: Kanban API
- ☐ Task 11: Authorization

### Phase 4: UI ☐
- ☐ Task 12: CRM Page
- ☐ Task 13: Kanban Board
- ☐ Task 14: Lead Drawer
- ☐ Task 15: CSS Styling

### Phase 5: Integration ☐
- ☐ Task 16: LeadSense Integration
- ☐ Task 17: Automation Hooks

### Phase 6: Documentation ☐
- ☐ Task 18: Documentation

### Phase 7: Testing ☐
- ☐ Task 19: Test Suite

## 🔍 Quick Reference

### Key Services
- **PipelineService** - Pipeline and stage management
- **LeadManagementService** - Lead operations (move, assign, deal)
- **AutomationService** - Rule evaluation and execution

### Key API Endpoints
- `leadsense.crm.list_pipelines` - List all pipelines
- `leadsense.crm.list_leads_board` - Get Kanban board data
- `leadsense.crm.move_lead` - Move lead between stages

### Key UI Components
- Pipeline selector (dropdown)
- Kanban board (columns with cards)
- Lead card (drag-and-drop)
- Lead drawer (side panel with tabs)

### Key Database Tables
- `crm_pipelines` - Pipeline definitions
- `crm_pipeline_stages` - Stage definitions
- `crm_lead_assignments` - Ownership history
- `crm_automation_rules` - Automation rules
- `crm_automation_logs` - Execution logs

## 📚 Additional Resources

- [LeadSense Overview](../../leadsense-overview.md) - Existing LeadSense documentation
- [LeadSense API](../../leadsense-api.md) - Current LeadSense API reference
- [Admin API](../../api.md) - Existing Admin API patterns
- [Project Description](../../PROJECT_DESCRIPTION.md) - Repository overview

## 💬 Questions?

For clarification on:
- **Specific tasks** - See individual task files
- **Overall architecture** - See IMPLEMENTATION_SUMMARY.md
- **Quick reference** - See README.md
- **API details** - See task files 9-10
- **UI implementation** - See task files 12-15

---

**Ready to implement? Start with [Task 1: Create CRM Tables Migration](task-01-create-crm-tables-migration.md)!**
