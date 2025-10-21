# AI Agents and Integration Guide

## Overview
- This project provides a dual-mode PHP backend and JavaScript widget that can switch between OpenAI's Chat Completions and Responses APIs, including streaming, file uploads, and optional WebSocket transport.【F:README.md†L1-L110】【F:chat-unified.php†L1-L265】
- `ChatHandler` orchestrates validation, rate limiting, conversation storage, and agent-specific flows while delegating OpenAI calls to `OpenAIClient`. Both classes are reusable across HTTP SSE, AJAX fallbacks, and the optional Ratchet WebSocket server.【F:includes/ChatHandler.php†L15-L1276】【F:includes/OpenAIClient.php†L17-L299】【F:websocket-server.php†L18-L220】

## Architecture Map
- **HTTP entrypoint** – `chat-unified.php` normalizes GET/POST payloads, negotiates SSE headers, exposes the `sendSSEEvent` helper, and routes to chat or responses handlers with JSON fallbacks when `stream=false`. All API errors are funneled into SSE `error` events or JSON responses.【F:chat-unified.php†L35-L268】
- **Core orchestration** – `includes/ChatHandler.php` validates input, injects system prompts, merges Responses tools/prompt overrides, handles streaming callbacks, manages storage, and executes rate limiting/file validation utilities.【F:includes/ChatHandler.php†L15-L1276】
- **OpenAI transport** – `includes/OpenAIClient.php` wraps streaming for `/chat/completions` and `/responses`, retries Responses failures with non-streaming requests for richer errors, and uploads files before attaching IDs to messages.【F:includes/OpenAIClient.php†L17-L207】
- **Front-end widget** – `chatbot-enhanced.js` renders the UI, assembles requests (including Responses overrides and file payloads), negotiates WebSocket→SSE→AJAX fallbacks, and interprets streamed SSE/WebSocket chunks (`start`/`chunk`/`done`/`tool_call`).【F:chatbot-enhanced.js†L13-L1484】
- **Optional WebSocket relay** – `websocket-server.php` mirrors the chat-completions streaming loop over Ratchet, emitting JSON `start`/`chunk`/`done`/`error` events to clients while maintaining per-connection history.【F:websocket-server.php†L31-L220】
- **Configuration** – `config.php` hydrates environment variables (including prompt IDs, tool defaults, vector store IDs, upload limits, and WebSocket toggles) and materializes them for both agents.【F:config.php†L185-L297】

## Agent Profiles
### Chat Completions Agent (`api_type=chat`)
- Loads prior messages from storage, prepends the configured system message to new conversations, and streams completions over SSE `message` events (`type: start|chunk|done`).【F:includes/ChatHandler.php†L48-L109】【F:includes/ChatHandler.php†L504-L548】
- Synchronous fallback returns JSON bodies with the assistant reply; conversation history is trimmed before persisting.【F:includes/ChatHandler.php†L70-L109】【F:includes/ChatHandler.php†L529-L547】【F:includes/ChatHandler.php†L1210-L1247】
- Tunables (model, temperature, penalties, system prompt) are sourced from the `chat` section of `config.php`; respect `max_message_length`, rate limits, and upload toggles defined under `chat_config`/`security`.【F:config.php†L196-L248】【F:includes/ChatHandler.php†L15-L46】【F:includes/ChatHandler.php†L1159-L1207】

### Responses Agent (`api_type=responses`)
- Normalizes prompt overrides (request vs. config), uploads files before appending `file_ids`, and builds Responses-formatted message arrays (including attachments).【F:includes/ChatHandler.php†L122-L188】【F:includes/ChatHandler.php†L141-L156】【F:includes/ChatHandler.php†L552-L585】
- Merges default and request-scoped tools, auto-applies vector store defaults, and executes streamed tool deltas (`type: tool_call`) while submitting outputs back to OpenAI when `response.required_action` arrives.【F:includes/ChatHandler.php†L166-L305】【F:includes/ChatHandler.php†L590-L760】
- Streams SSE envelopes via repeated `sendSSEEvent('message', {...})` calls for `start`, text `chunk`, `tool_call`, `notice`, and terminal `done`, then retries with prompt removal or `gpt-4o-mini` on client-side failures.【F:includes/ChatHandler.php†L203-L371】
- Sync requests reuse the same payload assembly and apply identical prompt/model fallback rules before persisting the assistant message.【F:includes/ChatHandler.php†L390-L501】
- Defaults for prompts, models, tool lists, vector stores, and max results come from the `responses` block in `config.php`; keep these in sync when adding new overrides.【F:config.php†L207-L221】

### WebSocket Relay (`websocket-server.php`)
- Accepts JSON `{message, conversation_id}` payloads, reuses chat-completions streaming, and emits the same `start`/`chunk`/`done` lifecycle expected by the widget's WebSocket fallback path.【F:websocket-server.php†L43-L220】
- Requires Ratchet dependencies declared in `composer.json` and a `.env` flag (`WEBSOCKET_ENABLED=true`) before the server starts.【F:composer.json†L13-L36】【F:websocket-server.php†L223-L268】

## Front-End Contract
- `chatbot-enhanced.js` builds request bodies with `conversation_id`, `api_type`, normalized Responses overrides (camelCase → snake_case), and base64-encoded `file_data` arrays before attempting WebSocket, SSE, then AJAX transport.【F:chatbot-enhanced.js†L991-L1055】【F:chatbot-enhanced.js†L1003-L1024】【F:chatbot-enhanced.js†L1027-L1049】
- SSE is skipped when files are present (EventSource cannot POST); WebSocket/SSE handlers reuse `handleStreamChunk` so backend event shapes (`type` and optional `response_id`/`tool_name`) must stay stable across transports.【F:chatbot-enhanced.js†L1190-L1284】【F:chatbot-enhanced.js†L1340-L1384】
- Tool call payloads are surfaced inside the active assistant bubble with completion status, so backend `tool_call` events should continue to provide `tool_name`, `arguments`, `call_id`, and optional `status` fields.【F:chatbot-enhanced.js†L1424-L1479】【F:includes/ChatHandler.php†L238-L305】
- When backend logic sends `notice` events, the widget treats them as assistant system messages; ensure any new stream metadata is either mapped to existing `type` values or mirrored in the UI logic under `handleStreamChunk`/`resolveStreamText`.【F:includes/ChatHandler.php†L345-L371】【F:chatbot-enhanced.js†L1340-L1422】

## Storage, Rate Limiting, and Uploads
- Requests are throttled per client IP using a sliding window stored under `/tmp`; update `rate_limit_requests` and `rate_limit_window` via `config.php` if adjusting throughput.【F:includes/ChatHandler.php†L1159-L1187】【F:config.php†L230-L239】
- Conversation history is stored in PHP sessions or JSON files depending on `storage.type`, capped at `chat_config.max_messages`; remember to maintain trimming when extending history schemas.【F:includes/ChatHandler.php†L1210-L1247】【F:config.php†L223-L239】
- File uploads are validated against size/type lists before being forwarded to OpenAI, and the client converts selected files to base64 prior to submission; keep these validators synchronized when supporting new formats.【F:includes/ChatHandler.php†L1189-L1207】【F:includes/OpenAIClient.php†L160-L207】【F:chatbot-enhanced.js†L1027-L1073】

## Deployment & Operations
- Environment variables in `.env` feed directly into `config.php`; document any new keys and ensure defaults exist so Docker and bare-metal deployments behave predictably.【F:config.php†L185-L297】
- The Dockerfile enables Apache headers for SSE, disables PHP output buffering, installs Composer, and exposes port 80; `docker-compose.yml` maps `.env`, mounts logs, and includes a commented WebSocket service block.【F:Dockerfile†L1-L63】【F:docker-compose.yml†L1-L40】
- Composer scripts include `composer run websocket` for the relay server; run `composer install` after dependency changes and update the Ratchet configuration if you alter WebSocket ports.【F:composer.json†L33-L36】【F:websocket-server.php†L240-L268】

## Extensibility Guidelines
- To add a new agent, create a dedicated handler in `ChatHandler`, route to it from `chat-unified.php`, and extend `OpenAIClient` if a different transport is required. Mirror any new stream event types in the widget (and update `docs/api.md`) to avoid breaking front-end fallbacks.【F:chat-unified.php†L205-L233】【F:includes/ChatHandler.php†L48-L501】【F:includes/OpenAIClient.php†L17-L299】【F:chatbot-enhanced.js†L1320-L1422】【F:docs/api.md†L1-L160】
- When modifying Responses tooling, keep the merge/normalization helpers aligned with new schema requirements and validate incoming overrides before dispatching to OpenAI.【F:includes/ChatHandler.php†L166-L760】
- Any changes to persistence or rate limiting must honor the trimming and validation utilities already present; adjust both client- and server-side expectations when altering history depth or upload policies.【F:includes/ChatHandler.php†L1159-L1247】【F:chatbot-enhanced.js†L970-L1055】

## Reference Material
- **README** – feature tour, setup instructions, and dual-API quick starts.【F:README.md†L1-L110】
- **docs/** – API usage, deployment, and customization details; keep these documents updated alongside behavioral changes.【F:docs/api.md†L1-L160】【F:docs/deployment.md†L1-L160】【F:docs/customization-guide.md†L1-L200】
