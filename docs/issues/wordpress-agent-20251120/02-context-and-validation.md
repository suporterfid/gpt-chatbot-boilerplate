# Task 2: Enhance context building and validation for blog workflow

## Goal
Teach `WordPressAgent` to load queued article payloads, active configuration metadata, and prior execution status into the runtime context, then validate all prerequisites before processing.

## Key Steps
- Update `buildContext()` to pull latest queue entry plus configuration details, internal link repositories, and attach them under `blog_workflow` keys.
- Persist user intent plus queue metadata (article_id, config_id, last_status, retry_count) for later phases.
- Extend `validateInput()` to ensure required IDs and credentials exist, that optional features (assets, Google Drive, execution logging) have the necessary config, and to emit actionable validation errors.

## Acceptance Criteria
- Context contains a normalized structure consumed by later handlers (config, queue entry, internal links, execution log pointers).
- Validation fails fast for missing configs/credentials or incompatible feature toggles.
- Logging indicates when context enrichment succeeds/fails without leaking secrets.

## Relevant Files
- `agents/wordpress/WordPressAgent.php`
- Related services used to fetch configs/queues/internal links
