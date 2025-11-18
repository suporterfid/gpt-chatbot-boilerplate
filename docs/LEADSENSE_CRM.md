# LeadSense CRM - Complete Guide

## Overview

LeadSense CRM extends the existing LeadSense lead detection system with enterprise-grade CRM capabilities including:

- **Visual Kanban Board** - Drag-and-drop lead management across pipeline stages
- **Pipeline Management** - Multiple customizable pipelines with ordered stages
- **Lead Ownership** - Assign leads to team members with historical tracking
- **Deal Tracking** - Monitor opportunity value, probability, and expected close dates
- **Automation Rules** - Event-driven webhooks and notifications
- **Activity Timeline** - Complete audit trail of all lead interactions

## Table of Contents

1. [Quick Start](#quick-start)
2. [Configuration](#configuration)
3. [Database Schema](#database-schema)
4. [Admin API](#admin-api)
5. [Admin UI](#admin-ui)
6. [Automation](#automation)
7. [Integration](#integration)
8. [Security](#security)
9. [Troubleshooting](#troubleshooting)

---

## Quick Start

### 1. Enable LeadSense CRM

Add to your `config.php`:

```php
'leadsense' => [
    'enabled' => true,
    // ... existing LeadSense config ...
    
    'crm' => [
        'enabled' => true,
        'auto_assign_new_leads' => true,
        'default_stage_slug' => 'lead_capture',
        'automation_enabled' => true
    ]
]
```

### 2. Run Migrations

```bash
php scripts/run_migrations.php
```

This creates:
- 5 new CRM tables
- Extends the leads table with CRM fields
- Seeds a default pipeline with 6 stages

### 3. Access the CRM Board

Navigate to **Admin → LeadSense CRM** in the admin interface.

---

## Configuration

### LeadSense CRM Options

```php
'leadsense' => [
    'enabled' => true,
    'crm' => [
        // Enable CRM features
        'enabled' => true,
        
        // Auto-assign new leads to default pipeline
        'auto_assign_new_leads' => true,
        
        // Initial stage slug for new leads
        'default_stage_slug' => 'lead_capture',
        
        // Enable automation rules
        'automation_enabled' => true,
        
        // Slack webhook for notifications (optional)
        'slack_webhook_url' => 'https://hooks.slack.com/services/...'
    ]
]
```

---

## Database Schema

### New Tables

#### `crm_pipelines`

Stores pipeline definitions.

| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| client_id | CHAR(36) | Tenant ID (nullable) |
| name | VARCHAR(255) | Pipeline name |
| description | TEXT | Optional description |
| is_default | BOOLEAN | Default pipeline flag |
| color | VARCHAR(32) | UI accent color |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update |
| archived_at | TIMESTAMP | Soft delete timestamp |

#### `crm_pipeline_stages`

Ordered stages within pipelines.

| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| pipeline_id | CHAR(36) | FK to crm_pipelines |
| name | VARCHAR(255) | Stage name |
| slug | VARCHAR(255) | Unique slug per pipeline |
| position | INTEGER | Display order |
| color | VARCHAR(32) | Stage color |
| is_won | BOOLEAN | Marks "Closed Won" stages |
| is_lost | BOOLEAN | Marks "Closed Lost" stages |
| is_closed | BOOLEAN | Generic closed indicator |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update |
| archived_at | TIMESTAMP | Soft delete timestamp |

#### `crm_lead_assignments`

Historical ownership tracking.

| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| lead_id | CHAR(36) | FK to leads |
| owner_id | CHAR(36) | Owner identifier |
| owner_type | VARCHAR(50) | admin_user, agent, external |
| assigned_by | CHAR(36) | Who made assignment |
| note | TEXT | Optional assignment note |
| created_at | TIMESTAMP | Assignment start |
| ended_at | TIMESTAMP | Assignment end (nullable) |

#### `crm_automation_rules`

Event-driven automation rules.

| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| client_id | CHAR(36) | Tenant ID (nullable) |
| name | VARCHAR(255) | Rule name |
| is_active | BOOLEAN | Active status |
| trigger_event | VARCHAR(100) | Event type to trigger on |
| trigger_filter | TEXT | JSON filter conditions |
| action_type | VARCHAR(100) | Action to execute |
| action_config | TEXT | JSON action configuration |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update |
| archived_at | TIMESTAMP | Soft delete timestamp |

#### `crm_automation_logs`

Execution tracking for automation.

| Column | Type | Description |
|--------|------|-------------|
| id | CHAR(36) | UUID primary key |
| rule_id | CHAR(36) | FK to crm_automation_rules |
| lead_id | CHAR(36) | Related lead (nullable) |
| event_type | VARCHAR(100) | Event that triggered |
| status | VARCHAR(50) | success or error |
| message | TEXT | Execution message |
| payload_json | TEXT | Event payload snapshot |
| created_at | TIMESTAMP | Execution timestamp |

### Extended `leads` Table

New CRM fields added:

| Column | Type | Description |
|--------|------|-------------|
| pipeline_id | CHAR(36) | Current pipeline |
| stage_id | CHAR(36) | Current stage |
| owner_id | CHAR(36) | Current owner ID |
| owner_type | VARCHAR(50) | Owner type |
| deal_value | DECIMAL(18,2) | Opportunity value |
| currency | VARCHAR(10) | Currency code |
| probability | INTEGER | Win probability (0-100) |
| expected_close_date | DATE | Target close date |
| tags | TEXT | JSON array of tags |

---

## Admin API

### Pipeline Management

#### List Pipelines

```http
GET /admin-api.php?action=leadsense.crm.list_pipelines
```

**Query Parameters:**
- `include_archived` (optional, boolean) - Include archived pipelines

**Response:**
```json
{
  "pipelines": [
    {
      "id": "pipe_123",
      "name": "Default",
      "is_default": true,
      "color": "#8b5cf6",
      "created_at": "2024-01-15T10:00:00Z"
    }
  ]
}
```

#### Get Pipeline with Stages

```http
GET /admin-api.php?action=leadsense.crm.get_pipeline&id={pipeline_id}
```

**Response:**
```json
{
  "id": "pipe_123",
  "name": "Default",
  "stages": [
    {
      "id": "stage_1",
      "name": "Lead Capture",
      "slug": "lead_capture",
      "position": 0,
      "color": "#a855f7"
    }
  ]
}
```

#### Create Pipeline

```http
POST /admin-api.php?action=leadsense.crm.create_pipeline
Content-Type: application/json

{
  "name": "Enterprise Sales",
  "is_default": false,
  "stages": [
    { "name": "Initial Contact", "slug": "initial_contact" },
    { "name": "Qualification", "slug": "qualification" },
    { "name": "Proposal", "slug": "proposal" },
    { "name": "Negotiation", "slug": "negotiation" },
    { "name": "Closed Won", "slug": "closed_won", "is_won": true, "is_closed": true },
    { "name": "Closed Lost", "slug": "closed_lost", "is_lost": true, "is_closed": true }
  ]
}
```

#### Update Pipeline

```http
POST /admin-api.php?action=leadsense.crm.update_pipeline
Content-Type: application/json

{
  "id": "pipe_123",
  "name": "Updated Name",
  "color": "#6366f1"
}
```

#### Archive Pipeline

```http
POST /admin-api.php?action=leadsense.crm.archive_pipeline
Content-Type: application/json

{
  "id": "pipe_123"
}
```

### Kanban Board Operations

#### Get Board Data

```http
GET /admin-api.php?action=leadsense.crm.list_leads_board&pipeline_id={id}
```

**Query Parameters:**
- `pipeline_id` (required) - Pipeline UUID
- `stage_ids` (optional) - Comma-separated stage IDs to filter
- `owner_id` (optional) - Filter by owner
- `min_score` (optional) - Minimum lead score
- `q` (optional) - Search term
- `limit` (optional, default 50) - Max leads per stage

**Response:**
```json
{
  "pipeline": { ... },
  "stages": [
    {
      "id": "stage_1",
      "name": "Lead Capture",
      "lead_count": 15,
      "leads": [
        {
          "id": "lead_1",
          "name": "John Doe",
          "company": "Acme Inc",
          "score": 85,
          "status": "open",
          "owner_id": "admin_1",
          "deal_value": 10000,
          "tags": ["hot", "enterprise"]
        }
      ]
    }
  ]
}
```

#### Move Lead Between Stages

```http
POST /admin-api.php?action=leadsense.crm.move_lead
Content-Type: application/json

{
  "lead_id": "lead_1",
  "to_stage_id": "stage_2",
  "from_stage_id": "stage_1",
  "pipeline_id": "pipe_123"
}
```

#### Update Lead Inline

```http
POST /admin-api.php?action=leadsense.crm.update_lead_inline
Content-Type: application/json

{
  "id": "lead_1",
  "owner_id": "admin_2",
  "owner_type": "admin_user",
  "deal_value": 15000,
  "currency": "USD",
  "probability": 75,
  "expected_close_date": "2024-06-30",
  "status": "open",
  "tags": ["enterprise", "priority"]
}
```

#### Add Note

```http
POST /admin-api.php?action=leadsense.crm.add_note
Content-Type: application/json

{
  "lead_id": "lead_1",
  "text": "Follow-up call scheduled for next week"
}
```

### Automation

#### List Automation Rules

```http
GET /admin-api.php?action=leadsense.crm.list_automation_rules
```

**Query Parameters:**
- `is_active` (optional, boolean) - Filter by active status
- `trigger_event` (optional) - Filter by event type

#### Create Automation Rule

```http
POST /admin-api.php?action=leadsense.crm.create_automation_rule
Content-Type: application/json

{
  "name": "Notify Sales on Qualified Lead",
  "is_active": true,
  "trigger_event": "lead.qualified",
  "trigger_filter": {
    "min_score": 80,
    "pipeline_id": "pipe_123"
  },
  "action_type": "slack",
  "action_config": {
    "message_template": "🔥 New qualified lead: {lead_name} from {lead_company}",
    "use_blocks": true
  }
}
```

---

## Admin UI

### Kanban Board

The Kanban board provides a visual interface for managing leads:

**Features:**
- **Drag-and-drop** - Move leads between stages by dragging cards
- **Pipeline selector** - Switch between different pipelines
- **Column counts** - See lead counts per stage
- **Lead cards** - Display key information at a glance
- **Quick actions** - Edit button to open lead details

**Lead Card Contents:**
- Avatar with initials
- Lead name and company
- Owner and source channel
- Status badge (Open/Resolved)
- Score badge (color-coded by score)
- Deal value (if set)
- Tags

### Lead Detail Drawer

Click any lead card to open a side panel with detailed information.

**Overview Tab:**
- Contact information (name, email, company, phone)
- Deal information (value, currency, probability, close date)
- Status and score
- Tags management (add/remove)
- Save changes button

**Timeline Tab:**
- Complete activity history
- Event icons and descriptions
- Formatted timestamps
- Add note button

---

## Automation

### Event Types

Automation rules can trigger on these events:

- `lead.created` - New lead detected
- `lead.qualified` - Lead score crosses threshold
- `lead.stage_changed` - Lead moved between stages
- `lead.pipeline_changed` - Lead moved to different pipeline
- `lead.owner_changed` - Ownership reassigned
- `lead.deal_updated` - Deal value/probability changed

### Action Types

#### Webhook

Send lead data to external URL.

```json
{
  "action_type": "webhook",
  "action_config": {
    "url": "https://api.example.com/leads",
    "headers": {
      "Authorization": "Bearer YOUR_TOKEN"
    }
  }
}
```

**Payload sent:**
```json
{
  "event": "lead.qualified",
  "lead": { ... },
  "pipeline_id": "pipe_123",
  "stage_id": "stage_2",
  "timestamp": "2024-01-15T10:00:00Z"
}
```

#### Slack

Send notification to Slack channel.

```json
{
  "action_type": "slack",
  "action_config": {
    "webhook_url": "https://hooks.slack.com/services/...",
    "message_template": "New lead: {lead_name} from {lead_company}",
    "username": "LeadSense CRM",
    "icon": ":bell:",
    "use_blocks": true
  }
}
```

### Filter Conditions

Rules can filter which leads trigger actions:

```json
{
  "trigger_filter": {
    "pipeline_id": "pipe_123",
    "stage_id": "stage_2",
    "min_score": 70,
    "qualified": true,
    "status": "open",
    "intent_level": "high",
    "tags": ["enterprise", "hot"]
  }
}
```

All conditions are AND-ed together. Lead must match all specified conditions.

---

## Integration

### LeadSense Detection Flow

When LeadSense detects a new lead:

1. **Extract entities** - Name, company, email, etc.
2. **Score lead** - Calculate qualification score
3. **Persist to database** - Create lead record
4. **Auto-assign to pipeline** (if CRM enabled)
   - Get default pipeline
   - Assign to initial stage (configurable via `default_stage_slug`)
   - Record pipeline assignment event
5. **Trigger automation** on `lead.created`
6. **Send notifications** if qualified
7. **Trigger automation** on `lead.qualified` (if qualified)

### Manual Integration

You can also manually integrate CRM features:

```php
require_once 'includes/LeadSense/CRM/LeadManagementService.php';

$leadMgmt = new LeadManagementService($db, $tenantId);

// Assign owner
$leadMgmt->assignOwner('lead_123', 'admin_456', 'admin_user', [
    'assigned_by' => 'system',
    'note' => 'Auto-assigned based on territory'
]);

// Update deal
$leadMgmt->updateDeal('lead_123', [
    'deal_value' => 25000,
    'currency' => 'USD',
    'probability' => 80,
    'expected_close_date' => '2024-12-31'
]);

// Move to next stage
$leadMgmt->moveLead('lead_123', 'stage_negotiation', [
    'from_stage_id' => 'stage_qualification',
    'pipeline_id' => 'pipe_default',
    'changed_by' => 'admin_456'
]);
```

---

## Security

### Authentication

All CRM API endpoints require authentication via the admin API:

- Session-based auth (recommended for web UI)
- API key-based auth (for integrations)

### Authorization

Endpoints check permissions:

- `read` - View pipelines, boards, leads
- `create` - Create pipelines, rules
- `update` - Modify leads, move stages
- `delete` - Archive pipelines, stages, rules

### Multi-Tenancy

All CRM data is tenant-isolated:

- Pipelines are scoped to `client_id`
- Leads are scoped to `tenant_id`
- Automation rules respect tenant context
- Users can only access data from their tenant

### Data Privacy

- PII redaction rules from LeadSense apply
- Automation webhooks can be configured to exclude sensitive fields
- Audit logs track all CRM operations
- Assignment history provides accountability

---

## Troubleshooting

### Leads Not Auto-Assigned to Pipeline

**Check:**
1. CRM is enabled: `leadsense.crm.enabled = true`
2. Auto-assign is enabled: `leadsense.crm.auto_assign_new_leads = true`
3. Default pipeline exists: Check `crm_pipelines` table for `is_default = 1`
4. Pipeline has stages: Check `crm_pipeline_stages` table

**Debug:**
```bash
# Check logs for errors
tail -f error.log | grep "LeadSense CRM"

# Verify default pipeline
sqlite3 data/chatbot.db "SELECT * FROM crm_pipelines WHERE is_default = 1"

# Check unassigned leads
sqlite3 data/chatbot.db "SELECT COUNT(*) FROM leads WHERE pipeline_id IS NULL"
```

### Automation Rules Not Firing

**Check:**
1. Automation is enabled: `leadsense.crm.automation_enabled = true`
2. Rule is active: `is_active = 1` in `crm_automation_rules`
3. Event type matches: `trigger_event` should be correct
4. Filters are not too restrictive: Review `trigger_filter` JSON

**Debug:**
```bash
# Check automation logs
sqlite3 data/chatbot.db "SELECT * FROM crm_automation_logs ORDER BY created_at DESC LIMIT 10"

# Test webhook manually
curl -X POST https://your-webhook-url \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

### Drag-and-Drop Not Working

**Check:**
1. JavaScript loaded: Check browser console for errors
2. CSS loaded: Verify `leadsense-crm.css` is included
3. Permissions: User must have `update` permission
4. Network: Check Network tab for failed API calls

**Common Issues:**
- CORS errors: Ensure API is on same domain
- Session expired: Re-authenticate
- Browser compatibility: Use modern browser (Chrome, Firefox, Safari)

### Performance Issues with Large Boards

**Optimization:**
1. Reduce `limit` in API calls (default 50 per stage)
2. Use search/filters to narrow results
3. Consider archiving old pipelines/stages
4. Add database indexes (already included in migrations)

**Check Query Performance:**
```bash
# Analyze query plan
sqlite3 data/chatbot.db
EXPLAIN QUERY PLAN SELECT * FROM leads WHERE pipeline_id = 'pipe_123';
```

---

## API Integration Examples

### JavaScript

```javascript
// Get board data
async function loadBoard(pipelineId) {
  const response = await fetch(`/admin-api.php?action=leadsense.crm.list_leads_board&pipeline_id=${pipelineId}`, {
    credentials: 'include'
  });
  return await response.json();
}

// Move lead
async function moveLead(leadId, toStageId) {
  const response = await fetch('/admin-api.php?action=leadsense.crm.move_lead', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({
      lead_id: leadId,
      to_stage_id: toStageId
    })
  });
  return await response.json();
}
```

### Python

```python
import requests

session = requests.Session()

def get_board(pipeline_id):
    url = f"https://your-domain.com/admin-api.php?action=leadsense.crm.list_leads_board&pipeline_id={pipeline_id}"
    response = session.get(url)
    return response.json()

def create_automation_rule(rule_data):
    url = "https://your-domain.com/admin-api.php?action=leadsense.crm.create_automation_rule"
    response = session.post(url, json=rule_data)
    return response.json()
```

### curl

```bash
# List pipelines
curl -X GET "https://your-domain.com/admin-api.php?action=leadsense.crm.list_pipelines" \
  -H "Cookie: PHPSESSID=your-session-id"

# Create pipeline
curl -X POST "https://your-domain.com/admin-api.php?action=leadsense.crm.create_pipeline" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your-session-id" \
  -d '{
    "name": "Sales Pipeline",
    "stages": [
      {"name": "Lead", "slug": "lead"},
      {"name": "Qualified", "slug": "qualified"},
      {"name": "Won", "slug": "won", "is_won": true, "is_closed": true}
    ]
  }'
```

---

## Support

For issues or questions:

1. Check this documentation
2. Review the implementation spec in `docs/issues/leadsense-20251118/`
3. Check error logs for diagnostic information
4. Open an issue on GitHub with:
   - Clear description of the problem
   - Steps to reproduce
   - Relevant log excerpts
   - Environment details (PHP version, database type)

---

## Changelog

### v1.0.0 (2024-01-15)

- Initial release
- Complete CRM implementation (Tasks 1-17)
- Kanban board UI
- Pipeline management
- Lead ownership tracking
- Deal tracking
- Automation rules with webhooks and Slack
- Multi-tenant support
- Backward compatible with existing LeadSense
