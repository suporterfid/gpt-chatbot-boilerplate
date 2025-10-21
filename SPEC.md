# GPT Chatbot Boilerplate Specification

## 1. Scope and Goals
- Provide a reusable PHP backend and JavaScript widget for embedding GPT-powered assistants that can switch between OpenAI's Chat Completions and Responses APIs with minimal configuration changes.【F:chat-unified.php†L205-L232】【F:chatbot-enhanced.js†L14-L45】
- Deliver real-time messaging over Server-Sent Events (SSE), WebSockets, or AJAX fallback while supporting file uploads, tool calls, and customizable UI themes for white-label deployments.【F:chat-unified.php†L59-L232】【F:chatbot-enhanced.js†L29-L135】【F:chatbot-enhanced.js†L1034-L1334】
- Expose configuration hooks through `.env`/`config.php` and runtime options so operators can tune models, prompts, storage, rate limits, and security policies without modifying core logic.【F:config.php†L6-L275】

## 2. System Architecture
### 2.1 Components
1. **HTTP gateway (`chat-unified.php`)** – Normalizes GET/POST payloads, negotiates streaming headers, sanitizes tool overrides, and routes requests to the appropriate handler method while emitting SSE events or JSON responses.【F:chat-unified.php†L59-L232】
2. **Core orchestrator (`includes/ChatHandler.php`)** – Validates input, manages conversation history, merges configuration with request overrides, uploads files, executes OpenAI streaming loops, and persists responses.【F:includes/ChatHandler.php†L15-L376】【F:includes/ChatHandler.php†L1159-L1247】
3. **Transport wrapper (`includes/OpenAIClient.php`)** – Wraps OpenAI REST calls for streaming and synchronous operations, including error logging and file uploads.【F:includes/OpenAIClient.php†L18-L209】【F:includes/OpenAIClient.php†L211-L299】
4. **Browser widget (`chatbot-enhanced.js`)** – Renders the chat UI, collects messages/files, negotiates WebSocket→SSE→AJAX transports, and renders streamed events and tool calls.【F:chatbot-enhanced.js†L14-L1484】
5. **Optional Ratchet WebSocket relay (`websocket-server.php`)** – Provides a standalone push channel mirroring the chat-completions streaming loop when enabled in configuration.【F:websocket-server.php†L18-L220】【F:websocket-server.php†L223-L268】

### 2.2 Conversation Flow
1. The widget captures user input, normalizes Responses overrides, and prepares file payloads before attempting WebSocket, SSE, then AJAX transports in order of preference.【F:chatbot-enhanced.js†L1003-L1073】【F:chatbot-enhanced.js†L1034-L1334】
2. The HTTP gateway parses the request, applies default API type, validates tool payloads, and ensures a conversation identifier before delegating to `ChatHandler` and sending an initial SSE `start` envelope when streaming.【F:chat-unified.php†L154-L220】
3. `ChatHandler` loads history, injects system prompts, uploads files, assembles OpenAI payloads, and streams responses while persisting assistant messages and returning SSE `message` events (`start`, `chunk`, `tool_call`, `done`, `notice`, or `error`).【F:includes/ChatHandler.php†L122-L376】【F:includes/ChatHandler.php†L201-L339】
4. The widget updates the UI as events arrive, finalizes messages on `done`, renders tool activity, and tears down the active transport when the stream finishes or fails.【F:chatbot-enhanced.js†L1340-L1478】

## 3. Backend Services
### 3.1 `chat-unified.php`
- Applies global CORS headers, handles OPTIONS preflight, and toggles between SSE and JSON headers based on the `stream` flag.【F:chat-unified.php†L24-L77】
- Defines `sendSSEEvent` helper to emit typed events and a `extractToolsConfig` sanitizer that rejects malformed overrides before logging and validation.【F:chat-unified.php†L81-L151】
- Generates conversation IDs when absent, coerces legacy `assistants` requests to the Responses flow, and dispatches to streaming or synchronous handlers while packaging JSON replies for non-streaming clients.【F:chat-unified.php†L189-L232】
- Emits structured error envelopes for both streaming and synchronous failures and always signals `close` for SSE clients during teardown.【F:chat-unified.php†L234-L266】

### 3.2 `ChatHandler`
- Enforces rate limits, message length, conversation ID format, optional input sanitization, and file-upload gating before processing a request.【F:includes/ChatHandler.php†L15-L46】【F:includes/ChatHandler.php†L1159-L1208】
- **Chat Completions flow:** loads prior history, prepends configured system prompts, appends the user message, and streams `/chat/completions` output while persisting assistant replies.【F:includes/ChatHandler.php†L48-L176】【F:includes/ChatHandler.php†L520-L548】
- **Responses flow:** merges prompt overrides, uploads attachments, transforms history into Responses-formatted `input` entries, normalizes tool lists (including automatic file-search defaults), and streams `/responses` events with tool-call execution and resubmission of tool outputs when required.【F:includes/ChatHandler.php†L122-L339】【F:includes/ChatHandler.php†L552-L720】【F:includes/ChatHandler.php†L273-L303】
- Retries Responses requests without prompts or with the `gpt-4o-mini` fallback on client-side API errors, emitting `notice` events to inform the UI.【F:includes/ChatHandler.php†L345-L373】
- Provides synchronous counterparts for both APIs that reuse the same payload assembly, extract assistant text from Responses outputs, and persist history before returning JSON bodies.【F:includes/ChatHandler.php†L70-L110】【F:includes/ChatHandler.php†L390-L433】
- Persists conversation history in PHP sessions or filesystem JSON files with configurable depth limits, enabling stateless front-end refreshes.【F:includes/ChatHandler.php†L1210-L1247】
- Supplies stubbed server-side tools (`get_weather`, `search_knowledge`) to demonstrate Responses tool-call execution, returning structured payloads or errors per tool name.【F:includes/ChatHandler.php†L1250-L1274】

### 3.3 `OpenAIClient`
- Implements streaming helpers for `/chat/completions` and `/responses`, parsing SSE frames, invoking callbacks per chunk, and logging payload metadata for observability.【F:includes/OpenAIClient.php†L18-L132】【F:includes/OpenAIClient.php†L262-L299】
- Provides synchronous wrappers for creating responses, chat completions, and submitting tool outputs, centralizing HTTP error handling in `makeRequest` to surface OpenAI errors with messages and codes.【F:includes/OpenAIClient.php†L134-L209】【F:includes/OpenAIClient.php†L211-L260】
- Uploads base64-encoded files by materializing temporary files and returning the OpenAI-assigned file IDs for later attachment to messages.【F:includes/OpenAIClient.php†L170-L209】

### 3.4 WebSocket Relay (`websocket-server.php`)
- Uses Ratchet to accept persistent connections, validate incoming messages, stream chat-completion responses, and mirror `start`/`chunk`/`done` event semantics for clients that prefer WebSockets.【F:websocket-server.php†L18-L220】
- Requires `WEBSOCKET_ENABLED=true` and the Ratchet dependency; when enabled it spins up `IoServer` on the configured host/port and registers signal handlers for graceful shutdown.【F:websocket-server.php†L223-L268】

## 4. Front-End Widget (`chatbot-enhanced.js`)
- Ships with extensive defaults for layout, theme, accessibility, proactive messaging, and callback hooks so integrators can customize the UI without editing source files.【F:chatbot-enhanced.js†L14-L135】
- Binds UI events for message submission, keyboard shortcuts, floating-window controls, and optional file uploads with size/type validation and preview management.【F:chatbot-enhanced.js†L766-L850】
- Normalizes Responses overrides into snake_case keys, converts selected files to base64 payloads, and attempts WebSocket, SSE, then AJAX transports in sequence, automatically downgrading when transports are unavailable or incompatible with uploads.【F:chatbot-enhanced.js†L1003-L1334】
- Handles streaming updates by creating a live assistant bubble on `start`, appending deltas on `chunk`, finalizing messages on `done`, and surfacing tool-call activity with status badges; `resolveStreamText` normalizes chat-completion and Responses payload shapes.【F:chatbot-enhanced.js†L1340-L1478】

## 5. Configuration & Environment
- `.env` variables hydrate `config.php`, which parses flexible JSON or comma-separated lists for Responses tools/vector stores, validates numeric ranges, and applies defaults for models, prompts, storage, security, logging, and performance knobs.【F:config.php†L6-L275】
- The configuration file enforces valid `API_TYPE`, ensures the OpenAI key is set, and creates storage directories when file persistence is selected.【F:config.php†L278-L297】

## 6. Security, Compliance, and Reliability
- CORS headers allow cross-origin embedding, and request validation enforces sanitized messages, allowed file types, file-size limits, and IP-based rate limiting with sliding windows stored in `/tmp`.【F:chat-unified.php†L24-L77】【F:includes/ChatHandler.php†L15-L46】【F:includes/ChatHandler.php†L1159-L1208】
- Error responses distinguish client vs server faults, with SSE delivering typed `error` events while non-streaming clients receive HTTP status codes and JSON bodies.【F:chat-unified.php†L234-L265】
- Responses streaming proactively retries failed prompt references and models, emitting `notice` events so the UI can inform users about fallbacks.【F:includes/ChatHandler.php†L345-L373】
- The front-end updates connection badges, toggles transports, and surfaces transport failures through its cascading WebSocket→SSE→AJAX strategy, improving resilience against network constraints.【F:chatbot-enhanced.js†L1034-L1334】

## 7. Extensibility Considerations
- New tools or agent modes should extend `ChatHandler`'s normalization and merging helpers to ensure consistent payload shapes and merge semantics for default vs request-scoped configuration.【F:includes/ChatHandler.php†L552-L720】
- Additional transports can plug into the widget by following the existing connection-type contract (`websocket`, `sse`, `ajax`) and calling `handleStreamChunk` with compatible envelopes.【F:chatbot-enhanced.js†L1034-L1478】
- Server-side feature flags or storage strategies can be added by expanding `config.php` and reusing the existing validation/parsing utilities for environment overrides.【F:config.php†L30-L275】
