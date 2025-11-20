# Task 8: Enforce error handling, retries, and queue status transitions

## Goal
Map all handler failures to the spec''s exception model, update queue statuses accordingly, and surface retry guidance.

## Key Steps
- Wrap service calls in try/catch blocks that translate to domain exceptions (ConfigurationException, ContentGenerationException, ImageGenerationException, WordPressPublishException, etc.).
- Update queue records with `processing`, `failed`, `completed`, `published`, or `retry_scheduled` states plus timestamps/log links.
- Provide operator-facing messages suggesting next actions (retry, edit config, contact admin) while keeping sensitive details in logs.

## Acceptance Criteria
- Queue status always matches the latest phase outcome; no stuck `processing` rows.
- Failures include execution log references and optional retry metadata.
- Tests cover transition scenarios (success, soft failure with retry, hard failure).

## Relevant Files
- `agents/wordpress/WordPressAgent.php`
- Queue service implementation
