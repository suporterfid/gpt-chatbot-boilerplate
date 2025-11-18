# Task 16: Integrate CRM with LeadSense Pipeline

## Objective
Connect CRM functionality with existing LeadSense lead detection and qualification.

## File Updates
`includes/LeadSense/LeadSenseService.php`

## Integration Points

### 1. Auto-Assign New Leads to Pipeline
```php
// In LeadSenseService::processTurn() after lead creation

private function assignToPipeline($leadId) {
    if (!isset($this->config['crm']['enabled']) || !$this->config['crm']['enabled']) {
        return; // CRM not enabled
    }
    
    // Get default pipeline
    require_once __DIR__ . '/CRM/PipelineService.php';
    $pipelineService = new PipelineService($this->leadRepository->getDb(), $this->tenantId);
    $defaultPipeline = $pipelineService->getDefaultPipeline();
    
    if (!$defaultPipeline) {
        return; // No pipeline configured
    }
    
    // Get first stage (lead_capture)
    $stages = $defaultPipeline['stages'];
    $firstStage = $stages[0] ?? null;
    
    if (!$firstStage) {
        return;
    }
    
    // Update lead
    $this->leadRepository->updateLead($leadId, [
        'pipeline_id' => $defaultPipeline['id'],
        'stage_id' => $firstStage['id']
    ]);
    
    // Record event
    $this->leadRepository->addEvent($leadId, 'stage_changed', [
        'old_stage_id' => null,
        'new_stage_id' => $firstStage['id'],
        'pipeline_id' => $defaultPipeline['id'],
        'changed_by' => 'system',
        'changed_by_type' => 'system'
    ]);
}
```

### 2. Progressive Stage Movement
```php
// Move qualified leads to next stage

if ($qualified && $this->config['crm']['auto_progress_stages']) {
    $this->progressToNextStage($leadId);
}

private function progressToNextStage($leadId) {
    $lead = $this->leadRepository->getLead($leadId);
    if (!$lead['stage_id']) return;
    
    $pipelineService = new PipelineService($this->leadRepository->getDb());
    $currentStage = $pipelineService->getStage($lead['stage_id']);
    
    // Get next stage
    $stages = $pipelineService->listStages($lead['pipeline_id']);
    $nextStage = null;
    foreach ($stages as $stage) {
        if ($stage['position'] === $currentStage['position'] + 1) {
            $nextStage = $stage;
            break;
        }
    }
    
    if ($nextStage) {
        $leadMgmtService = new LeadManagementService($this->leadRepository->getDb(), $this->leadRepository);
        $leadMgmtService->moveLead($leadId, $currentStage['id'], $nextStage['id'], [
            'id' => 'system',
            'type' => 'system'
        ]);
    }
}
```

### 3. Trigger Automation on Qualification
```php
// In processTurn() after qualification

if ($qualified) {
    $this->triggerAutomation('lead.qualified', $lead);
}

private function triggerAutomation($eventType, $lead) {
    if (!isset($this->config['crm']['automation_enabled']) || !$this->config['crm']['automation_enabled']) {
        return;
    }
    
    require_once __DIR__ . '/CRM/AutomationService.php';
    $automationService = new AutomationService($this->leadRepository->getDb());
    $automationService->evaluateTriggers($eventType, [
        'lead_id' => $lead['id'],
        'pipeline_id' => $lead['pipeline_id'],
        'stage_id' => $lead['stage_id'],
        'score' => $lead['score'],
        'qualified' => $lead['qualified']
    ]);
}
```

## Configuration
Add to `config.php`:
```php
'leadsense' => [
    'enabled' => true,
    // ... existing config ...
    
    'crm' => [
        'enabled' => true,
        'auto_assign_new_leads' => true,
        'auto_progress_stages' => false,  // Manual stage movement
        'automation_enabled' => true
    ]
]
```

## Prerequisites
- Task 5: PipelineService
- Task 6: LeadManagementService
- Task 7: AutomationService

## Testing
- Test new lead creation assigns to pipeline
- Test qualification triggers automation
- Test stage progression (if enabled)
