# Task 6: Create CRM Lead Management Service

## Objective
Create a service for CRM-specific operations on leads: stage movement, owner assignment, deal tracking, and note management.

## File to Create
`includes/LeadSense/CRM/LeadManagementService.php`

## Core Functionality

### Class Structure
```php
class LeadManagementService {
    private $db;
    private $leadRepository;
    private $tenantId;
    
    public function __construct($db, $leadRepository, $tenantId = null);
    
    // Stage Management
    public function moveLead($leadId, $fromStageId, $toStageId, $changedBy);
    public function moveLeadToPipeline($leadId, $toPipelineId, $toStageId, $changedBy);
    
    // Ownership
    public function assignOwner($leadId, $ownerId, $ownerType, $assignedBy, $note = null);
    public function unassignOwner($leadId, $changedBy);
    public function getLeadOwner($leadId);
    public function getOwnershipHistory($leadId);
    
    // Deal Management
    public function updateDeal($leadId, $dealData, $changedBy);
    public function calculateWeightedValue($leadId);
    
    // Notes
    public function addNote($leadId, $text, $author, $context = []);
    public function getLeadNotes($leadId);
    
    // Queries
    public function getLeadsByOwner($ownerId, $ownerType, $filters = []);
    public function getLeadsByStage($stageId, $filters = []);
    public function getLeadsForBoard($pipelineId, $filters = []);
}
```

### Key Methods Implementation

#### moveLead - Stage Transition
```php
public function moveLead($leadId, $fromStageId, $toStageId, $changedBy) {
    // 1. Validate lead exists and belongs to fromStageId
    // 2. Get stage details for both stages
    // 3. Verify stages belong to same pipeline
    // 4. Begin transaction
    // 5. Update lead.stage_id
    // 6. Record stage_changed event
    // 7. Trigger automation rules
    // 8. Commit transaction
    // Return: updated lead
}
```

#### assignOwner - Owner Assignment
```php
public function assignOwner($leadId, $ownerId, $ownerType, $assignedBy, $note = null) {
    // 1. Get current owner
    // 2. End current assignment (if exists)
    // 3. Create new assignment record
    // 4. Update lead.owner_id and lead.owner_type
    // 5. Record owner_changed event
    // 6. Trigger automation rules
    // Return: assignment record
}
```

#### updateDeal - Deal Fields Update
```php
public function updateDeal($leadId, $dealData, $changedBy) {
    // 1. Get current deal values
    // 2. Calculate changes
    // 3. Update lead deal fields
    // 4. Record deal_updated event with changes
    // Return: updated lead
    
    // dealData can include:
    // - deal_value (REAL)
    // - currency (TEXT)
    // - probability (INTEGER 0-100)
    // - expected_close_date (TEXT ISO date)
}
```

#### getLeadsForBoard - Board Query
```php
public function getLeadsForBoard($pipelineId, $filters = []) {
    // Returns leads grouped by stage for Kanban view
    // Filters: owner_id, min_score, q (search), status
    // Returns: [stage_id => [leads...]]
    
    // Optimized query:
    // SELECT l.*, s.name as stage_name, s.position
    // FROM leads l
    // JOIN crm_pipeline_stages s ON l.stage_id = s.id
    // WHERE l.pipeline_id = ?
    // ORDER BY s.position, l.created_at DESC
}
```

## Integration with LeadRepository

Extend existing LeadRepository or use composition:

```php
// In LeadManagementService constructor
$this->leadRepository = $leadRepository;

// Use LeadRepository for:
// - addEvent() - recording events
// - getLead() - fetching lead data
// - updateLead() - updating lead fields
// - getLeadEvents() - fetching timeline
```

## Event Recording

Use LeadEventTypes and LeadRepository methods:

```php
// Record stage change
$this->leadRepository->recordStageChange(
    $leadId,
    $oldStage,
    $newStage,
    $changedBy
);

// Record owner change
$this->leadRepository->recordOwnerChange(
    $leadId,
    $oldOwner,
    $newOwner,
    $changedBy
);

// Record deal update
$this->leadRepository->recordDealUpdate(
    $leadId,
    $changes,
    $changedBy
);
```

## Automation Trigger Points

Call automation service at these events:
1. After stage change → 'lead.stage_changed'
2. After owner assignment → 'lead.owner_changed'
3. After deal update → 'lead.deal_updated'

```php
// Example in moveLead
if ($this->automationService) {
    $this->automationService->evaluateTriggers('lead.stage_changed', [
        'lead_id' => $leadId,
        'pipeline_id' => $pipelineId,
        'old_stage_id' => $fromStageId,
        'new_stage_id' => $toStageId
    ]);
}
```

## Testing

```php
// tests/test_lead_management_service.php
// Test stage movement
// Test owner assignment
// Test deal updates
// Test board queries
// Test multi-tenant isolation
```

## Prerequisites
- Task 5: PipelineService
- Task 3: LeadEventTypes
- Task 2: Extended leads table

## Related Tasks
- Task 10: Kanban Board API (uses this service)
- Task 16: LeadSense integration (calls this service)
