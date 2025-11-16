#!/bin/bash
# Script to create GitHub issues for webhook infrastructure
# Prerequisites: GitHub CLI (gh) must be installed and authenticated
# Usage: ./create_webhook_issues.sh

REPO="suporterfid/gpt-chatbot-boilerplate"

# Creating issue: Bootstrap /webhook/inbound entrypoint
gh issue create \
  --repo "$REPO" \
  --title "Bootstrap /webhook/inbound entrypoint" \
  --label "task,webhook,phase-1" \
  --body-file "docs/webhook-issues/wh-001a-task.md"

# Creating issue: Implement WebhookGateway orchestration service
gh issue create \
  --repo "$REPO" \
  --title "Implement WebhookGateway orchestration service" \
  --label "task,webhook,phase-1" \
  --body-file "docs/webhook-issues/wh-001b-task.md"

# Creating issue: Connect gateway to agent/queue pipeline
gh issue create \
  --repo "$REPO" \
  --title "Connect gateway to agent/queue pipeline" \
  --label "task,webhook,phase-1" \
  --body-file "docs/webhook-issues/wh-001c-task.md"

# Creating issue: Build WebhookSecurityService
gh issue create \
  --repo "$REPO" \
  --title "Build WebhookSecurityService" \
  --label "task,webhook,security,phase-2" \
  --body-file "docs/webhook-issues/wh-002a-task.md"

# Creating issue: Adopt shared security in all inbound routes
gh issue create \
  --repo "$REPO" \
  --title "Adopt shared security in all inbound routes" \
  --label "task,webhook,security,phase-2" \
  --body-file "docs/webhook-issues/wh-002b-task.md"

# Creating issue: Author webhook_subscribers migrations (SQLite/MySQL/PostgreSQL)
gh issue create \
  --repo "$REPO" \
  --title "Author webhook_subscribers migrations (SQLite/MySQL/PostgreSQL)" \
  --label "task,webhook,database,phase-3" \
  --body-file "docs/webhook-issues/wh-003a-task.md"

# Creating issue: Implement WebhookSubscriberRepository
gh issue create \
  --repo "$REPO" \
  --title "Implement WebhookSubscriberRepository" \
  --label "task,webhook,repository,phase-3" \
  --body-file "docs/webhook-issues/wh-003b-task.md"

# Creating issue: Extend admin API/UI for subscriber management
gh issue create \
  --repo "$REPO" \
  --title "Extend admin API/UI for subscriber management" \
  --label "task,webhook,admin,ui,phase-3" \
  --body-file "docs/webhook-issues/wh-003c-task.md"

# Creating issue: Add webhook_logs migrations (SQLite/MySQL/PostgreSQL)
gh issue create \
  --repo "$REPO" \
  --title "Add webhook_logs migrations (SQLite/MySQL/PostgreSQL)" \
  --label "task,webhook,database,phase-4" \
  --body-file "docs/webhook-issues/wh-004a-task.md"

# Creating issue: Implement WebhookLogRepository
gh issue create \
  --repo "$REPO" \
  --title "Implement WebhookLogRepository" \
  --label "task,webhook,repository,phase-4" \
  --body-file "docs/webhook-issues/wh-004b-task.md"

# Creating issue: Surface delivery history in observability/admin UI
gh issue create \
  --repo "$REPO" \
  --title "Surface delivery history in observability/admin UI" \
  --label "task,webhook,admin,ui,observability,phase-4" \
  --body-file "docs/webhook-issues/wh-004c-task.md"

# Creating issue: Implement WebhookDispatcher core service
gh issue create \
  --repo "$REPO" \
  --title "Implement WebhookDispatcher core service" \
  --label "task,webhook,dispatcher,phase-5" \
  --body-file "docs/webhook-issues/wh-005a-task.md"

# Creating issue: Refactor worker job handler for subscriber-aware deliveries
gh issue create \
  --repo "$REPO" \
  --title "Refactor worker job handler for subscriber-aware deliveries" \
  --label "task,webhook,worker,phase-5" \
  --body-file "docs/webhook-issues/wh-005b-task.md"

# Creating issue: Migrate existing webhook callers to dispatcher
gh issue create \
  --repo "$REPO" \
  --title "Migrate existing webhook callers to dispatcher" \
  --label "task,webhook,refactor,phase-5" \
  --body-file "docs/webhook-issues/wh-005c-task.md"

# Creating issue: Extend JobQueue for attempt/backoff metadata
gh issue create \
  --repo "$REPO" \
  --title "Extend JobQueue for attempt/backoff metadata" \
  --label "task,webhook,queue,phase-6" \
  --body-file "docs/webhook-issues/wh-006a-task.md"

# Creating issue: Implement six-step exponential retry scheduler
gh issue create \
  --repo "$REPO" \
  --title "Implement six-step exponential retry scheduler" \
  --label "task,webhook,retry,phase-6" \
  --body-file "docs/webhook-issues/wh-006b-task.md"

# Creating issue: Add webhooks configuration block
gh issue create \
  --repo "$REPO" \
  --title "Add webhooks configuration block" \
  --label "task,webhook,config,phase-7" \
  --body-file "docs/webhook-issues/wh-007a-task.md"

# Creating issue: Document new config in .env.example and deployment guides
gh issue create \
  --repo "$REPO" \
  --title "Document new config in .env.example and deployment guides" \
  --label "task,webhook,documentation,phase-7" \
  --body-file "docs/webhook-issues/wh-007b-task.md"

# Creating issue: Introduce payload-transform and queue hooks
gh issue create \
  --repo "$REPO" \
  --title "Introduce payload-transform and queue hooks" \
  --label "enhancement,webhook,extensibility,phase-8" \
  --body-file "docs/webhook-issues/wh-008a-task.md"

# Creating issue: Build webhook sandbox/testing utilities
gh issue create \
  --repo "$REPO" \
  --title "Build webhook sandbox/testing utilities" \
  --label "enhancement,webhook,testing,phase-8" \
  --body-file "docs/webhook-issues/wh-008b-task.md"

# Creating issue: Enhance observability dashboards for webhook metrics
gh issue create \
  --repo "$REPO" \
  --title "Enhance observability dashboards for webhook metrics" \
  --label "enhancement,webhook,observability,phase-8" \
  --body-file "docs/webhook-issues/wh-008c-task.md"

# Creating issue: Add PHPUnit tests for inbound gateway & security
gh issue create \
  --repo "$REPO" \
  --title "Add PHPUnit tests for inbound gateway & security" \
  --label "task,webhook,testing,phase-9" \
  --body-file "docs/webhook-issues/wh-009a-task.md"

# Creating issue: Add PHPUnit tests for dispatcher, retries, and logging
gh issue create \
  --repo "$REPO" \
  --title "Add PHPUnit tests for dispatcher, retries, and logging" \
  --label "task,webhook,testing,phase-9" \
  --body-file "docs/webhook-issues/wh-009b-task.md"

