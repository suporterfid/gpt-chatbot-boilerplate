# Task 7: Create CRM Automation Service

## Objective
Create AutomationService for managing and executing automation rules (webhook, Slack, email notifications).

## File
`includes/LeadSense/CRM/AutomationService.php`

## Core Methods
- `listRules($clientId = null, $includeArchived = false)`
- `getRule($ruleId)`
- `createRule($data)`
- `updateRule($ruleId, $data)`
- `archiveRule($ruleId)`
- `evaluateTriggers($eventType, $eventData)`
- `executeAction($rule, $lead, $eventData)`
- `logExecution($ruleId, $leadId, $status, $message, $payload)`

## MVP Scope
- Rule CRUD operations
- Simple trigger matching (event type + basic filters)
- Webhook action execution
- Execution logging

## Trigger Evaluation
```php
public function evaluateTriggers($eventType, $eventData) {
    // 1. Get active rules for event type
    // 2. For each rule, check trigger_filter
    // 3. If matched, execute action
    // 4. Log result
}
```

## Action Types (MVP)
- `webhook` - POST to URL with lead data
- `slack` - Reuse existing Notifier integration
- Future: email, whatsapp, etc.

## Prerequisites
- Task 1: Automation tables
- Existing: Notifier class (for Slack)

## Related
- Task 10: API endpoints
- Task 17: Integration with LeadSense
