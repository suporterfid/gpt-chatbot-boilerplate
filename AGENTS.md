# AI Agents and Integration Guide

## Overview
This project ships a dual-mode chatbot that can talk to OpenAI's **Chat Completions** and **Responses** APIs through a unified PHP backend and a customizable JavaScript widget. The backend exposes two agent profiles:

- **Chat Completion Assistant** – streams conversational replies using the traditional `chat.completions` endpoint. Designed for lightweight, stateless question answering with configurable persona instructions.
- **Responses Agent** – targets the newer Responses API with prompt-template awareness, tool calling, and file attachment support for richer workflows.

An optional Ratchet-powered WebSocket relay complements the primary Server-Sent Events (SSE) transport so front-ends can choose the best real-time channel.

## Architecture
- **HTTP entrypoint**: `chat-unified.php` accepts GET/POST payloads, negotiates SSE headers, and dispatches work to `ChatHandler` after validating the request. It emits lifecycle events such as `start`, `message` chunks, `tool_call`, `notice`, `error`, and `close` over SSE.【F:chat-unified.php†L1-L123】【F:chat-unified.php†L125-L188】
- **Business logic**: `includes/ChatHandler.php` encapsulates validation, rate limiting, conversation storage, and the branching logic between Chat Completions and Responses flows.【F:includes/ChatHandler.php†L1-L236】
- **OpenAI transport**: `includes/OpenAIClient.php` wraps cURL streaming for both `/chat/completions` and `/responses`, handles file uploads, and retries non-streaming calls to surface API errors.【F:includes/OpenAIClient.php†L1-L158】
- **Front-end widget**: `chatbot-enhanced.js` renders the UI, queues messages/files, negotiates between WebSocket, SSE, or AJAX fallbacks, and normalizes streamed deltas for display.【F:chatbot-enhanced.js†L1-L197】【F:chatbot-enhanced.js†L604-L757】
- **Optional WebSocket server**: `websocket-server.php` reuses the OpenAI streaming pattern inside a Ratchet `MessageComponentInterface` to serve clients that prefer persistent sockets.【F:websocket-server.php†L1-L152】
- **Configuration**: `config.php` hydrates defaults from environment variables, covering model selection, prompt text, storage backend, rate limits, and upload policy.【F:config.php†L1-L146】

## Agent Specifications
### Chat Completion Assistant
- **Identifier**: `api_type=chat`
- **Purpose**: Streamlined conversational assistant without tool calls; ideal for simple Q&A or support prompts.
- **Prompt strategy**: Injects a configurable system message (`chat.system_message`) into empty histories and applies temperature, top-p, and penalty tunables from config/environment.【F:includes/ChatHandler.php†L28-L70】【F:config.php†L26-L39】
- **Input/Output**:
  - Input payload: `{ message, conversation_id, api_type: "chat" }`, augmented with session history pulled from storage.【F:chat-unified.php†L68-L118】【F:includes/ChatHandler.php†L152-L198】
  - Output stream: SSE events `start`, repeated `chunk` deltas, then `done` with `finish_reason` via `streamChatCompletion`. Non-stream fallback returns JSON with `response` text.【F:includes/ChatHandler.php†L237-L312】【F:chatbot-enhanced.js†L699-L744】
- **APIs/Services**: OpenAI Chat Completions (`/chat/completions`), invoked through `OpenAIClient::streamChatCompletion` with SSE headers.【F:includes/OpenAIClient.php†L17-L81】
- **Dependencies & Env Vars**: Requires `OPENAI_API_KEY`, `OPENAI_MODEL`, and optional tuning variables (`OPENAI_TEMPERATURE`, etc.) defined in `.env`/server env.【F:config.php†L18-L47】
- **Execution flow**:
  1. Front-end posts user text; SSE connection established by `chat-unified.php`.
  2. `ChatHandler` validates rate limits, message length, and conversation ID; loads history from session/file storage.【F:includes/ChatHandler.php†L12-L149】【F:includes/ChatHandler.php†L324-L372】
  3. System and user messages are compiled; streaming call initiated.
  4. Chunks are relayed downstream; upon completion history is trimmed and persisted.【F:includes/ChatHandler.php†L260-L310】

### Responses Agent
- **Identifier**: `api_type=responses`
- **Purpose**: Rich assistant leveraging Responses API features—prompt referencing, native file attachments, and automatic tool-call execution.
- **Prompt strategy**: Seeds optional `responses.system_message`; merges configured and request-level `prompt_id`/`prompt_version` overrides, retrying without prompt or on a fallback model when OpenAI returns client errors.【F:includes/ChatHandler.php†L72-L141】【F:includes/ChatHandler.php†L198-L259】
- **Input/Output**:
  - Input payload: `{ message, conversation_id, api_type: "responses", [prompt_id], [prompt_version], [file_data] }`. File attachments are validated server-side and uploaded to OpenAI with `purpose='user_data'` to yield `file_ids` appended to the user message.【F:chat-unified.php†L68-L123】【F:includes/ChatHandler.php†L142-L214】【F:includes/ChatHandler.php†L338-L368】
  - Output stream: SSE events describing text deltas, notices, tool call deltas, completion status, and errors, normalized by the widget for display.【F:includes/ChatHandler.php†L198-L259】【F:chatbot-enhanced.js†L699-L758】
- **APIs/Services**: OpenAI Responses API (`/responses` streaming, tool output submission, file uploads). Tools `get_weather` and `search_knowledge` are local PHP stubs executed when required actions arrive.【F:includes/OpenAIClient.php†L83-L206】【F:includes/ChatHandler.php†L260-L394】
- **Dependencies & Env Vars**: `RESPONSES_MODEL`, `RESPONSES_TEMPERATURE`, `RESPONSES_MAX_OUTPUT_TOKENS`, optional prompt identifiers, and upload toggles (`ENABLE_FILE_UPLOAD`, `MAX_FILE_SIZE`, `ALLOWED_FILE_TYPES`).【F:config.php†L41-L106】
- **Execution flow**:
  1. Incoming request validated; attachments optionally uploaded.
  2. Message history formatted to Responses schema (`input_text`, attachments) before streaming request is made.【F:includes/ChatHandler.php†L214-L236】【F:includes/ChatHandler.php†L312-L336】
  3. Streaming callback interprets event types, relays tool-call progress, automatically executes whitelisted tools, and resubmits outputs to OpenAI when required.【F:includes/ChatHandler.php†L198-L303】
  4. Completion event persists assistant reply and resets stream state.【F:includes/ChatHandler.php†L208-L252】

### WebSocket Relay Service
- **Identifier**: `websocket-server.php`
- **Purpose**: Optional Ratchet server for scenarios where SSE is unsuitable; mirrors Chat Completion behavior over persistent sockets.
- **Prompt strategy**: Uses conversation history and default `openai` settings from `config.php`; system prompts align with Chat Completion defaults.【F:websocket-server.php†L44-L120】【F:config.php†L18-L72】
- **Input/Output**: Expects JSON messages with `message` and `conversation_id`, returns JSON events (`connected`, `start`, `chunk`, `done`, `error`).【F:websocket-server.php†L28-L118】
- **APIs/Services**: Calls OpenAI Chat Completions directly via cURL within the server loop.【F:websocket-server.php†L84-L146】
- **Dependencies**: Requires Composer packages `cboden/ratchet` and `ratchet/pawl` plus PHP sockets extension; enabled via `composer install` and optional Docker service stanza.【F:composer.json†L13-L30】【F:Dockerfile†L4-L34】【F:docker-compose.yml†L1-L34】
- **Execution flow**: Accept connection → validate payload → append to in-memory history → stream completion → emit events and trim stored history.【F:websocket-server.php†L28-L146】

## Integration Points
- **Front-end ↔ Backend**: `chatbot-enhanced.js` negotiates streaming transport (WebSocket → SSE → AJAX fallback) and maps SSE `message` payloads to DOM updates. Each outbound request includes `conversation_id`, API type, prompt overrides, and serialized file data.【F:chatbot-enhanced.js†L604-L757】
- **Conversation persistence**: `ChatHandler` stores transcripts in PHP sessions or filesystem depending on `storage.type`, trimming to `chat_config.max_messages` to avoid runaway context.【F:includes/ChatHandler.php†L324-L372】
- **Rate limiting & security**: `ChatHandler::checkRateLimit` throttles requests per IP; file uploads validated against configured size/type limits before hitting OpenAI.【F:includes/ChatHandler.php†L338-L368】【F:includes/ChatHandler.php†L304-L336】
- **Tool execution**: When Responses API triggers `response.required_action`, server-side handlers execute whitelisted functions and submit outputs back to OpenAI automatically.【F:includes/ChatHandler.php†L198-L303】
- **Transport protocols**: HTTP SSE is the default; WebSocket is optional; AJAX fallback handles environments that block streaming. Dockerfile/Apache config disables buffering to keep SSE responsive.【F:chat-unified.php†L23-L62】【F:Dockerfile†L15-L36】

## Deployment & Configuration
- **Environment variables**: Define API type, model, prompt, temperature, storage, logging, upload policy, and WebSocket toggles via `.env` or server env (see `config.php`).【F:config.php†L18-L146】
- **Docker**: `Dockerfile` prepares Apache/PHP for streaming, installs Composer dependencies, and exposes port 80. `docker-compose.yml` maps `.env`, mounts logs, and includes an optional WebSocket service configuration block.【F:Dockerfile†L1-L54】【F:docker-compose.yml†L1-L34】
- **Local setup**: Provide `OPENAI_API_KEY` in environment, ensure PHP cURL and sockets extensions, and optionally run `composer install` to enable WebSocket server.
- **Testing agents**: Use the front-end widget (`default.php` or custom page) pointing `apiEndpoint` to `/chat-unified.php` with appropriate `apiType`. For WebSockets, run `composer run websocket` and configure `websocketEndpoint` in the widget.【F:chatbot-enhanced.js†L1-L197】【F:composer.json†L21-L29】

## Extensibility Guidelines
- **Adding a new agent**:
  1. Create a handler method in `ChatHandler` (e.g., `handleMyAgent`) and route requests from `chat-unified.php` based on a new `api_type` flag.
  2. Extend `OpenAIClient` or add a sibling client for the target service.
  3. Update `config.php` to expose tunables and document required environment variables.
  4. Expose front-end switches in `chatbot-enhanced.js` so clients can opt into the agent.
- **Prompt customization**: Override system prompts via environment variables (`SYSTEM_MESSAGE`, `RESPONSES_SYSTEM_MESSAGE`) or supply prompt IDs/versions per request; sanitize and validate user overrides before forwarding.【F:config.php†L26-L64】【F:includes/ChatHandler.php†L72-L141】
- **Tooling & file handling**: Add new tool functions in `ChatHandler::executeTool` and guard them with input validation. Mirror client-side UI affordances (buttons, indicators) when exposing new tool categories.【F:includes/ChatHandler.php†L358-L394】【F:chatbot-enhanced.js†L699-L758】
- **Storage strategies**: Implement additional persistence layers by extending `getConversationHistory`/`saveConversationHistory` branches (e.g., database) and ensuring concurrency control if used across workers.【F:includes/ChatHandler.php†L324-L372】

## References
- [README](README.md) – feature overview, integration examples, and environment setup.【F:README.md†L1-L110】
- [docs/api.md](docs/api.md) – JavaScript API, HTTP endpoints, and transport details.【F:docs/api.md†L1-L120】
- [docs/deployment.md](docs/deployment.md) & [docs/customization-guide.md](docs/customization-guide.md) – additional deployment and UI guidance.
- OpenAI API dashboards for managing prompt IDs, tool outputs, and uploaded files referenced in the Responses workflow.
