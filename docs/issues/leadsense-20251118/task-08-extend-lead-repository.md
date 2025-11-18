# Task 8: Extend LeadRepository for CRM

## Objective
Update LeadRepository to support CRM fields and queries.

## File
`includes/LeadSense/LeadRepository.php`

## Updates Needed

### New Query Methods
- `getLeadsByPipeline($pipelineId, $filters = [])`
- `getLeadsByStage($stageId, $filters = [])`
- `getLeadsByOwner($ownerId, $ownerType, $filters = [])`
- `searchLeads($query, $filters = [])` - extended for tags

### Field Support
- Update `createLead()` to accept CRM fields
- Update `updateLead()` to handle CRM fields
- Update `getLead()` to return CRM fields
- Update `listLeads()` to filter by CRM fields

### Tag Support
- `addTag($leadId, $tag)`
- `removeTag($leadId, $tag)`
- `getLeadsByTag($tag)`

## Prerequisites
- Task 2: Extended leads table
- Task 3: Event types

## Testing
Add to existing tests/test_leadsense_*.php
