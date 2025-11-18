# Task 17: Implement Basic Automation Hooks

## Objective
Enable automation rule execution on CRM events, integrated with existing webhook system.

## Updates Needed

### 1. AutomationService Execution
Complete implementation in `includes/LeadSense/CRM/AutomationService.php`:

```php
public function executeAction($rule, $lead, $eventData) {
    switch ($rule['action_type']) {
        case 'webhook':
            return $this->executeWebhook($rule, $lead, $eventData);
        
        case 'slack':
            return $this->executeSlack($rule, $lead, $eventData);
        
        default:
            return ['error' => 'Unknown action type'];
    }
}

private function executeWebhook($rule, $lead, $eventData) {
    $config = json_decode($rule['action_config'], true);
    $webhookUrl = $config['url'] ?? null;
    
    if (!$webhookUrl) {
        return ['error' => 'No webhook URL configured'];
    }
    
    // Prepare payload
    $payload = [
        'event' => $eventData['event_type'] ?? 'unknown',
        'lead' => $lead,
        'trigger_data' => $eventData,
        'rule_id' => $rule['id'],
        'timestamp' => date('c')
    ];
    
    // Call webhook (reuse existing webhook mechanism)
    require_once __DIR__ . '/../../WebhookService.php';
    $webhookService = new WebhookService();
    return $webhookService->send($webhookUrl, $payload, $config);
}

private function executeSlack($rule, $lead, $eventData) {
    // Reuse existing Notifier
    require_once __DIR__ . '/../Notifier.php';
    $config = json_decode($rule['action_config'], true);
    
    $notifier = new Notifier([
        'notify' => [
            'slack_webhook_url' => $config['webhook_url'] ?? ''
        ]
    ], null);
    
    $message = $this->formatSlackMessage($rule, $lead, $eventData);
    return $notifier->sendSlack($message);
}

private function formatSlackMessage($rule, $lead, $eventData) {
    $eventType = $eventData['event_type'] ?? 'event';
    $stageName = $eventData['new_stage_name'] ?? '';
    
    return [
        'text' => "🔔 CRM Automation: {$rule['name']}",
        'blocks' => [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Lead:* {$lead['name']}\n*Event:* {$eventType}\n*Stage:* {$stageName}"
                ]
            ]
        ]
    ];
}
```

### 2. Event Hooks
Add automation triggers in LeadManagementService:

```php
// In LeadManagementService::moveLead()
// After successful stage change:

if ($this->automationService) {
    $this->automationService->evaluateTriggers('lead.stage_changed', [
        'lead_id' => $leadId,
        'pipeline_id' => $pipelineId,
        'old_stage_id' => $fromStageId,
        'new_stage_id' => $toStageId,
        'old_stage_name' => $oldStage['name'],
        'new_stage_name' => $newStage['name']
    ]);
}
```

## Prerequisites
- Task 7: AutomationService
- Task 6: LeadManagementService
- Existing: Notifier, WebhookService

## Testing
- Create automation rule
- Trigger event (move lead)
- Verify webhook called
- Check automation logs
