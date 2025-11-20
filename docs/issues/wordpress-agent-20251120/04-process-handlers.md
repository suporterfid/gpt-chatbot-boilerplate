# Task 4: Implement workflow-specific process handlers

## Goal
Add private handler methods for each new action so `process()` can orchestrate queue management, generation phases, and publishing according to the spec.

## Key Steps
- Create handlers such as `handleQueueArticle`, `handleGenerateStructure`, `handleWriteChapters`, `handleGenerateAssets`, `handleAssembleArticle`, `handlePublishArticle`, `handleMonitorStatus`, and `handleGetExecutionLog`.
- Each handler should call the corresponding service, update queue status, and capture structured responses (phase, output URLs, errors, retry hints).
- Ensure exceptions map to domain-specific ones (ConfigurationException, ContentGenerationException, etc.) and propagate through `AgentProcessingException` with context metadata.

## Acceptance Criteria
- `process()` switch routes to new handlers based on detected action.
- Queue status transitions and execution logs are written for each phase.
- Errors trigger retries/status changes consistent with spec (e.g., move to `failed` with log reference).

## Relevant Files
- `agents/wordpress/WordPressAgent.php`
- Service classes under `includes/WordPressBlog/*`
