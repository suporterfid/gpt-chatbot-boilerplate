# Task 6: Redesign custom tools and tool-call handling

## Goal
Expose tooling that mirrors the WordPress Blog Automation Pro workflow and allow the agent/LLM to interact with queue/config resources programmatically.

## Key Steps
- Overhaul `getCustomTools()` to define JSON schemas for operations such as `queue_article_request`, `update_article_brief`, `run_generation_phase`, `submit_required_action_output`, `fetch_execution_log`, and `list_internal_links`.
- Implement the matching logic in `executeCustomTool()` to validate payloads, call services, and return normalized responses (status, IDs, URLs, log references).
- Ensure tool definitions describe side effects, required params, and error formats so LLM can use them reliably.

## Acceptance Criteria
- Tools cover every spec-defined interaction point (queue, internal links, execution logs, retries).
- Tool invocations succeed/fail with structured metadata for SSE streaming.
- No deprecated tools remain; documentation updated accordingly.

## Relevant Files
- `agents/wordpress/WordPressAgent.php`
- Tool service implementations used inside the handlers
