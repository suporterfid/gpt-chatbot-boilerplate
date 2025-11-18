# Task 10: Implement Kanban Board API

## Objective
Add admin-api.php endpoints for Kanban board operations.

## Admin API Actions

### Board View
- `leadsense.crm.list_leads_board` - GET
  - Params: pipeline_id, stage_ids, owner_id, q, min_score
  - Returns: stages with leads grouped

### Lead Operations
- `leadsense.crm.move_lead` - POST
  - Body: lead_id, from_stage_id, to_stage_id, pipeline_id
- `leadsense.crm.update_lead_inline` - POST
  - Body: id, owner_id, deal_value, probability, etc.
- `leadsense.crm.add_note` - POST
  - Body: lead_id, text

### Response Format
```json
{
  "pipeline": {...},
  "stages": [
    {
      "id": "stage_1",
      "name": "Lead Capture",
      "position": 0,
      "lead_count": 49,
      "leads": [
        {
          "id": "lead_1",
          "name": "John Doe",
          "company": "Acme",
          "score": 85,
          "owner": {...},
          "last_activity_at": "..."
        }
      ]
    }
  ]
}
```

## Prerequisites
- Task 6: LeadManagementService
- Task 8: Extended LeadRepository

## Testing
- Test board loading with multiple stages
- Test lead movement
- Test inline updates
