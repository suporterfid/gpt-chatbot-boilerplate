# Implementation Plan: Visual AI Agent Template + Admin UI

## Overview
This document outlines the detailed implementation tasks for adding a web Admin UI that enables non-technical users to create and manage AI Agents, OpenAI Prompts, and Vector Stores without code changes or redeployments.

## Current State Analysis

### Existing Architecture
- **HTTP Gateway**: `chat-unified.php` - Handles both Chat Completions and Responses API with SSE/JSON responses
- **Core Orchestrator**: `includes/ChatHandler.php` - Validates requests, manages history, merges configs, handles streaming
- **OpenAI Transport**: `includes/OpenAIClient.php` - Wraps OpenAI API calls with streaming support
- **Frontend Widget**: `chatbot-enhanced.js` - Renders chat UI, manages transports (WebSocket→SSE→AJAX)
- **WebSocket Server**: `websocket-server.php` - Optional Ratchet-based streaming relay
- **Configuration**: `config.php` - Loads .env variables, provides defaults for both APIs

### Current Features
- Dual API support (Chat Completions + Responses)
- SSE streaming with fallback to AJAX
- File upload support
- Tool calling (including file_search)
- Prompt ID/version references
- Vector store integration
- Session/file-based conversation storage
- Rate limiting and security validation

### Gaps for Admin UI
- No Agent abstraction or persistence layer
- No CRUD endpoints for managing Agents, Prompts, or Vector Stores
- No OpenAI Admin API client (for Prompts/Vector Stores management)
- No database schema or migrations
- No Admin UI frontend
- No Agent selection mechanism in chat flow
- No authentication/authorization for Admin operations

## Implementation Phases

---

## Phase 1: Database Layer & Agent Model

### 1.1 Database Infrastructure

**Files to Create:**
- `includes/DB.php` - PDO wrapper with prepared statements
- `migrations/001_create_agents_table.sql` - SQLite schema
- `migrations/001_create_agents_table.mysql.sql` - MySQL schema
- `migrations/002_create_audit_log_table.sql` - SQLite schema
- `migrations/002_create_audit_log_table.mysql.sql` - MySQL schema
- `migrations/003_create_admin_users_table.sql` - SQLite/MySQL (optional for v1)

**Tasks:**
- [ ] Create `includes/DB.php` PDO wrapper class
  - Constructor accepts DSN from config
  - Prepared statement helpers (query, execute, insert, update, delete)
  - Transaction support
  - Error handling with detailed logging
  - Connection pooling considerations

- [ ] Design agents table schema (snake_case)
  - id (UUID primary key)
  - name (unique, indexed)
  - description (text, nullable)
  - api_type (enum: 'responses' | 'chat', default 'responses')
  - prompt_id (string, nullable)
  - prompt_version (string, nullable)
  - system_message (text, nullable)
  - model (string, nullable)
  - temperature (float, nullable)
  - top_p (float, nullable)
  - max_output_tokens (int, nullable)
  - tools_json (json/text, nullable)
  - vector_store_ids_json (json/text, nullable)
  - max_num_results (int, nullable)
  - created_at (datetime)
  - updated_at (datetime)
  - is_default (boolean, default false)

- [ ] Design audit_log table schema
  - id (auto-increment primary key)
  - actor (string) - "admin:<email>" or "system"
  - action (string) - e.g., "agent.create", "vector_store.file.add"
  - payload_json (json/text)
  - created_at (datetime)

- [ ] Create migration SQL files for SQLite
  - SQLite uses TEXT for JSON storage
  - UUID as TEXT type
  - Proper indexes on name, is_default, created_at

- [ ] Create migration SQL files for MySQL
  - JSON native type support
  - CHAR(36) for UUID
  - InnoDB engine with proper indexes

- [ ] Add migration runner utility
  - `scripts/migrate.php` - Reads and executes migration files in order
  - Track executed migrations in a `migrations` table
  - Support both SQLite and MySQL

**Configuration Changes:**
- Update `config.php` to add 'admin' section:
  ```php
  'admin' => [
      'enabled' => filter_var($_ENV['ADMIN_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
      'token' => $_ENV['ADMIN_TOKEN'] ?? '',
      'database_type' => $_ENV['ADMIN_DB_TYPE'] ?? 'sqlite', // 'sqlite' | 'mysql'
      'database_path' => $_ENV['ADMIN_DB_PATH'] ?? __DIR__ . '/data/admin.db',
      'database_url' => $_ENV['ADMIN_DATABASE_URL'] ?? '', // for mysql: mysql://user:pass@host/db
  ]
  ```

- Update `.env.example` to add Admin variables:
  ```bash
  # Admin UI Configuration
  ADMIN_ENABLED=true
  ADMIN_TOKEN=your_random_admin_token_here_min_32_chars
  ADMIN_DB_TYPE=sqlite
  ADMIN_DB_PATH=./data/admin.db
  # ADMIN_DATABASE_URL=mysql://user:pass@localhost/chatbot_admin
  ```

**Testing:**
- [ ] Test DB.php with SQLite connection
- [ ] Test DB.php with MySQL connection (if available)
- [ ] Run migrations and verify schema
- [ ] Test CRUD operations on empty tables
- [ ] Verify indexes are created correctly

---

### 1.2 Agent Service Layer

**Files to Create:**
- `includes/AgentService.php` - Agent CRUD and config resolution

**Tasks:**
- [ ] Create `AgentService` class
  - Constructor: accepts DB instance
  - `createAgent(array $data): array` - Insert new agent, return with generated UUID
  - `updateAgent(string $id, array $data): bool` - Update existing agent
  - `deleteAgent(string $id): bool` - Delete agent by ID
  - `getAgent(string $id): ?array` - Fetch single agent by ID
  - `listAgents(array $filters = []): array` - List all agents with optional filtering
  - `getDefaultAgent(): ?array` - Fetch the agent marked as default
  - `setDefaultAgent(string $id): bool` - Unmark all defaults, mark specified as default
  - `resolveAgentConfig(string $agentId): array` - Load agent and return normalized config
  - `validateAgentData(array $data): void` - Validate agent fields before save

- [ ] Implement agent data normalization
  - Parse tools_json from JSON string to array
  - Parse vector_store_ids_json from JSON string to array
  - Validate api_type is 'chat' or 'responses'
  - Validate numeric ranges (temperature 0-2, top_p 0-1, etc.)
  - Generate UUIDs for new agents

- [ ] Implement config resolution logic
  - Load agent from DB
  - Return structured config object:
    ```php
    [
      'api_type' => 'responses',
      'prompt_id' => '...',
      'prompt_version' => '...',
      'model' => '...',
      'temperature' => 0.7,
      'tools' => [...],
      'vector_store_ids' => [...],
      'max_num_results' => 20,
      'system_message' => '...'
    ]
    ```

- [ ] Add audit logging to all mutating operations
  - Log agent.create, agent.update, agent.delete, agent.set_default
  - Include actor (admin token fingerprint or "system")
  - Store full payload in audit_log table

**Testing:**
- [ ] Test createAgent with valid data
- [ ] Test createAgent with invalid data (should throw exceptions)
- [ ] Test updateAgent and verify updated_at changes
- [ ] Test deleteAgent and verify removal
- [ ] Test listAgents with various filters
- [ ] Test setDefaultAgent and verify only one is default
- [ ] Test resolveAgentConfig returns correct structure
- [ ] Verify audit logs are created for each operation

---

## Phase 2: OpenAI Admin Client

### 2.1 OpenAI Admin API Wrapper

**Files to Create:**
- `includes/OpenAIAdminClient.php` - Wrapper for Prompts, Vector Stores, Files APIs

**Tasks:**
- [ ] Create `OpenAIAdminClient` class
  - Constructor: accepts same config as OpenAIClient (api_key, base_url, org)
  - Reuse HTTP request infrastructure from OpenAIClient
  - Implement error handling similar to OpenAIClient::makeRequest

**Prompts API Methods:**
- [ ] `listPrompts(int $limit = 20, string $after = ''): array`
  - GET /prompts with pagination
  - Return: {data: [...], has_more: bool, first_id: string, last_id: string}

- [ ] `getPrompt(string $promptId): array`
  - GET /prompts/{id}
  - Return: {id, name, description, created_at, ...}

- [ ] `listPromptVersions(string $promptId, int $limit = 20, string $after = ''): array`
  - GET /prompts/{id}/versions
  - Return: {data: [...], has_more: bool}

- [ ] `createPrompt(string $name, array $definition, string $description = ''): array`
  - POST /prompts
  - Body: {name, description?, definition: {type: "text", text: "..."}}
  - Return: created prompt object

- [ ] `createPromptVersion(string $promptId, array $definition): array`
  - POST /prompts/{id}/versions
  - Body: {definition: {type: "text", text: "..."}}
  - Return: version object

- [ ] `deletePrompt(string $promptId): bool`
  - DELETE /prompts/{id}
  - Handle 404 gracefully
  - Return: true on success

- [ ] Handle API availability gracefully
  - Catch 404/403 errors if Prompts API unavailable
  - Return empty arrays or null instead of throwing
  - Log warnings when API is unavailable

**Vector Stores API Methods:**
- [ ] `listVectorStores(int $limit = 20, string $after = ''): array`
  - GET /vector_stores
  - Return: paginated list

- [ ] `getVectorStore(string $storeId): array`
  - GET /vector_stores/{id}
  - Return: {id, name, status, file_counts: {...}, created_at, ...}

- [ ] `createVectorStore(string $name, array $metadata = []): array`
  - POST /vector_stores
  - Body: {name, metadata?}
  - Return: created store object

- [ ] `deleteVectorStore(string $storeId): bool`
  - DELETE /vector_stores/{id}
  - Return: true on success

- [ ] `listVectorStoreFiles(string $storeId, int $limit = 20, string $after = ''): array`
  - GET /vector_stores/{id}/files
  - Return: {data: [{id, status, ...}], ...}

- [ ] `addFileToVectorStore(string $storeId, string $fileId): array`
  - POST /vector_stores/{id}/files
  - Body: {file_id}
  - Return: file object with status

- [ ] `removeFileFromVectorStore(string $storeId, string $fileId): bool`
  - DELETE /vector_stores/{id}/files/{file_id}
  - Return: true on success

- [ ] `getVectorStoreFileStatus(string $storeId, string $fileId): array`
  - GET /vector_stores/{id}/files/{file_id}
  - Return: {status: "completed"|"in_progress"|"failed", ...}

**Files API Methods:**
- [ ] `listFiles(string $purpose = 'assistants'): array`
  - GET /files
  - Query: ?purpose={purpose}
  - Return: {data: [{id, filename, purpose, bytes, created_at}]}

- [ ] `uploadFile(string $name, string $mimeType, string $base64Data, string $purpose = 'assistants'): array`
  - POST /files
  - Multipart form: file={binary}, purpose={purpose}
  - Reuse OpenAIClient::uploadFile logic
  - Return: {id, filename, purpose, bytes, created_at}

- [ ] `deleteFile(string $fileId): bool`
  - DELETE /files/{id}
  - Return: true on success

**Error Handling:**
- [ ] Mirror OpenAIClient's makeRequest error handling
- [ ] Parse OpenAI error responses and include message/code
- [ ] Log all requests/responses (with secret redaction)
- [ ] Throw exceptions with HTTP status codes for Admin API to catch

**Testing:**
- [ ] Mock OpenAI responses and test each method
- [ ] Test error handling for 400/404/500 responses
- [ ] Test pagination for list methods
- [ ] Verify logging captures request/response metadata
- [ ] Test graceful degradation when Prompts API unavailable

---

## Phase 3: Admin API Backend

### 3.1 Admin Controller & Routes

**Files to Create:**
- `admin-api.php` - HTTP entrypoint for Admin API
- `includes/AdminController.php` - Routes and orchestration

**Tasks - admin-api.php:**
- [ ] Create thin HTTP entrypoint
  - CORS headers (stricter than chat endpoint - lockdown to specific origins)
  - Content-Type: application/json
  - Parse request method and path
  - Extract Authorization header
  - Validate Bearer token against config['admin']['token']
  - Route to AdminController
  - Return JSON responses with consistent envelope: {data?, error?}
  - Handle OPTIONS preflight
  - Log all admin requests with IP, path, method

- [ ] Implement simple routing
  - Parse PATH_INFO or REQUEST_URI
  - Extract resource and action from URL
  - Support patterns:
    - GET /agents
    - POST /agents
    - GET /agents/{id}
    - PUT /agents/{id}
    - DELETE /agents/{id}
    - POST /agents/{id}/make-default
    - (similar for prompts, vector-stores, files)

**Tasks - AdminController.php:**
- [ ] Create AdminController class
  - Constructor: accepts config, DB, AgentService, OpenAIAdminClient
  - `route(string $method, string $path, array $body): array`
  - Dispatch to handler methods based on path

- [ ] Implement Agent endpoints
  - `getAgents(array $query): array` → AgentService->listAgents()
  - `createAgent(array $body): array` → AgentService->createAgent()
  - `getAgent(string $id): array` → AgentService->getAgent()
  - `updateAgent(string $id, array $body): array` → AgentService->updateAgent()
  - `deleteAgent(string $id): array` → AgentService->deleteAgent()
  - `makeDefaultAgent(string $id): array` → AgentService->setDefaultAgent()

- [ ] Implement Prompts endpoints
  - `getPrompts(array $query): array` → OpenAIAdminClient->listPrompts()
  - `createPrompt(array $body): array` → OpenAIAdminClient->createPrompt()
  - `getPrompt(string $id): array` → OpenAIAdminClient->getPrompt()
  - `listPromptVersions(string $id, array $query): array` → OpenAIAdminClient->listPromptVersions()
  - `createPromptVersion(string $id, array $body): array` → OpenAIAdminClient->createPromptVersion()
  - `deletePrompt(string $id): array` → OpenAIAdminClient->deletePrompt()

- [ ] Implement Vector Stores endpoints
  - `getVectorStores(array $query): array`
  - `createVectorStore(array $body): array`
  - `getVectorStore(string $id): array`
  - `deleteVectorStore(string $id): array`
  - `getVectorStoreFiles(string $id, array $query): array`
  - `addVectorStoreFile(string $id, array $body): array`
  - `removeVectorStoreFile(string $storeId, string $fileId): array`

- [ ] Implement Files endpoints
  - `getFiles(array $query): array`
  - `uploadFile(array $body): array` - Accept {name, type, data: base64}
  - `deleteFile(string $id): array`

- [ ] Implement Health endpoint
  - `getHealth(): array`
  - Test OpenAI API key validity (simple GET /models call)
  - Test database connectivity
  - Return: {status: "ok"|"degraded", openai: bool, database: bool, timestamp}

- [ ] Add authentication middleware
  - Extract Bearer token from Authorization header
  - Compare to config['admin']['token']
  - Throw 401 if missing or invalid
  - Log authentication failures

- [ ] Add rate limiting for admin endpoints
  - Separate rate limiter from chat endpoint
  - More generous limits (e.g., 300 req/min)
  - Use IP-based sliding window

- [ ] Add CSRF protection (optional for v1)
  - Generate CSRF token on first request
  - Store in session or cookie
  - Validate on mutating operations (POST/PUT/DELETE)

**Response Format:**
- Success: `{data: {...}}`
- Error: `{error: {message: "...", code: "...", status: 400}}`

**Error Handling:**
- [ ] Catch exceptions and map to HTTP status codes
  - Validation errors → 400
  - Not found → 404
  - Auth failures → 401
  - OpenAI errors → preserve status or 502
  - Internal errors → 500
- [ ] Log all errors with stack traces
- [ ] Return user-friendly error messages (no stack traces to client)

**Testing:**
- [ ] Test authentication with valid token
- [ ] Test authentication with invalid token (401)
- [ ] Test all Agent CRUD endpoints
- [ ] Test all Prompts endpoints
- [ ] Test all Vector Store endpoints
- [ ] Test all Files endpoints
- [ ] Test Health endpoint
- [ ] Test rate limiting behavior
- [ ] Test CORS headers
- [ ] Test error responses format

---

## Phase 4: Admin UI Frontend

### 4.1 Admin UI Structure

**Files to Create:**
- `public/admin/index.html` - Main Admin SPA
- `public/admin/admin.js` - Admin UI logic
- `public/admin/admin.css` - Admin UI styles

**Tasks - index.html:**
- [ ] Create responsive HTML structure
  - Header with title and logout/settings
  - Sidebar navigation (Agents, Prompts, Vector Stores, Files, Settings)
  - Main content area (dynamic, swapped by JS)
  - Modal/drawer containers for forms

- [ ] Include dependencies
  - No frameworks required - vanilla JS
  - Optional: lightweight markdown renderer for previews
  - Optional: JSON editor component for tools config

- [ ] Add meta tags
  - Viewport for mobile
  - CSP headers for security
  - Favicon

**Tasks - admin.js:**

- [ ] Create API client wrapper
  - `AdminAPI` class with methods for all endpoints
  - Attach Authorization header from stored token
  - Handle errors and show user feedback
  - Retry logic for network failures
  - Request/response logging to console (dev mode)

- [ ] Implement routing/navigation
  - Hash-based routing (#/agents, #/prompts, etc.)
  - Update sidebar active state
  - Swap main content area
  - Handle browser back/forward

- [ ] Implement Agents page
  - List view: table/cards with name, api_type, default badge, updated_at
  - Search/filter by name
  - "Create Agent" button → open drawer/modal
  - Row actions: Edit, Delete, Make Default, Test
  - Pagination if needed

- [ ] Implement Agent form (create/edit)
  - Fields:
    - Name (required)
    - Description (textarea)
    - API Type (select: responses, chat)
    - Mark as Default (checkbox)
    - Model (dropdown or text input)
    - Temperature (slider 0-2, step 0.1)
    - Top P (slider 0-1, step 0.05)
    - Max Output Tokens (number input)
    - System Message (textarea fallback)
  - Prompt Section (if api_type=responses):
    - Prompt ID (dropdown from /prompts or manual input)
    - Version (dropdown from /prompts/{id}/versions or manual input)
    - Preview button (fetch and display prompt text)
  - Tools Section:
    - File Search toggle
    - Max Num Results (number input)
    - Vector Store IDs (multi-select from /vector-stores)
    - Function Tools (JSON editor or form builder)
  - Validation on submit
  - Save button → POST or PUT /agents
  - Success feedback, close drawer, refresh list

- [ ] Implement Agent Test feature
  - Modal with text input for test message
  - Stream test response using Responses API with agent config
  - Display streamed output in real-time
  - Show tool calls if any
  - Display errors if prompt/config invalid

- [ ] Implement Prompts page
  - List view: name, id, created_at, versions count
  - "Create Prompt" button
  - Row actions: View Versions, Create Version, Delete

- [ ] Implement Prompt form (create)
  - Name (required)
  - Description
  - Initial Content (large textarea)
  - Save → POST /prompts

- [ ] Implement Prompt Version form (create)
  - Select existing prompt
  - Content (textarea)
  - Save → POST /prompts/{id}/versions

- [ ] Implement Prompt Preview
  - Fetch prompt by ID
  - Display formatted content (markdown?)
  - Test Run button → quick Responses call with this prompt

- [ ] Implement Vector Stores page
  - List view: name, id, status, file_count
  - "Create Vector Store" button
  - Row actions: View Files, Delete

- [ ] Implement Vector Store form (create)
  - Name (required)
  - Metadata (optional JSON)
  - Save → POST /vector-stores

- [ ] Implement Vector Store detail view
  - Show store metadata
  - List files with status (completed, in_progress, failed)
  - "Add Files" button → file upload
  - Remove file button per row
  - Refresh button to update statuses

- [ ] Implement file upload for Vector Stores
  - Drag & drop or file picker
  - Multi-file selection
  - Validate size/type client-side
  - Convert to base64
  - Upload to /files → get file_id
  - Attach file_id to vector store → POST /vector-stores/{id}/files
  - Show upload progress and ingestion status
  - Poll for status updates if in_progress

- [ ] Implement Files page
  - List all files: filename, purpose, size, created_at
  - Show linked vector stores (if applicable)
  - Delete button → DELETE /files/{id}
  - Upload button → POST /files

- [ ] Implement Settings page
  - Read-only info: OpenAI API key (masked), Admin enabled, Database type
  - Health check widget → GET /health (show status for OpenAI, DB)
  - Option to test API connectivity
  - Warning if ADMIN_TOKEN not set or too short
  - (Future: Token rotation, admin user management)

**Tasks - admin.css:**
- [ ] Create clean, professional styles
  - Color scheme: neutral with accent colors
  - Responsive grid layout
  - Sidebar fixed/collapsible on mobile
  - Form styles: inputs, buttons, labels
  - Table styles with hover states
  - Modal/drawer animations
  - Loading spinners
  - Success/error toast notifications
  - Badge styles (default, api type, status)

**Accessibility:**
- [ ] ARIA labels for interactive elements
- [ ] Keyboard navigation support
- [ ] Focus indicators
- [ ] Screen reader friendly

**Testing:**
- [ ] Test navigation between pages
- [ ] Test Agent CRUD operations via UI
- [ ] Test Prompt CRUD operations via UI
- [ ] Test Vector Store CRUD and file management via UI
- [ ] Test file upload and deletion via UI
- [ ] Test Agent Test feature with streaming
- [ ] Test form validations
- [ ] Test error handling (network failures, API errors)
- [ ] Test on mobile/tablet viewports
- [ ] Test keyboard navigation

---

### 4.2 Static Asset Serving

**Tasks:**
- [ ] Update web server config to serve /admin route
  - Apache: Add rewrite rule to serve public/admin/index.html
  - Nginx: Add location block for /admin

- [ ] Update Dockerfile if needed
  - Ensure public/admin directory is included
  - Set proper permissions

- [ ] Create public/admin/.htaccess for Apache
  - Rewrite all requests to index.html (SPA routing)

**Testing:**
- [ ] Access /admin and verify index.html loads
- [ ] Test hash-based navigation works (#/agents, etc.)
- [ ] Verify assets load correctly (JS, CSS)

---

## Phase 5: Chat Flow Integration

### 5.1 Agent Selection in Chat

**Files to Modify:**
- `chat-unified.php`
- `includes/ChatHandler.php`
- `chatbot-enhanced.js`

**Tasks - chat-unified.php:**
- [ ] Accept agent_id parameter
  - GET: `$_GET['agent_id']`
  - POST: `$input['agent_id']`

- [ ] Pass agent_id to ChatHandler methods
  - Update method signatures:
    - `handleChatCompletion($message, $conversationId, $agentId = null)`
    - `handleResponsesChat($message, $conversationId, $fileData, $promptId, $promptVersion, $tools, $agentId = null)`

- [ ] Include agent_id in SSE start event
  - `sendSSEEvent('start', ['conversation_id' => ..., 'api_type' => ..., 'agent_id' => ...])`

- [ ] Log agent_id in request logging
  - `log_debug("Incoming request ... agentId=$agentId")`

**Tasks - ChatHandler.php:**
- [ ] Inject AgentService in constructor
  - Optional dependency (fallback if admin disabled)
  - Constructor: `__construct($config, $agentService = null)`

- [ ] Add resolveAgentOverrides method
  - `private function resolveAgentOverrides(?string $agentId): array`
  - If agentId is null, return empty array
  - Load agent via AgentService->getAgent()
  - If not found, log warning and return empty array
  - Parse tools_json and vector_store_ids_json
  - Return normalized config:
    ```php
    [
      'api_type' => '...',
      'prompt_id' => '...',
      'prompt_version' => '...',
      'model' => '...',
      'temperature' => ...,
      'top_p' => ...,
      'max_output_tokens' => ...,
      'tools' => [...],
      'vector_store_ids' => [...],
      'max_num_results' => ...,
      'system_message' => '...'
    ]
    ```

- [ ] Update handleResponsesChat
  - Call resolveAgentOverrides at start
  - Merge agent config with request overrides
  - Merging precedence: request > agent > config.php
  - Apply agent prompt_id/version if not provided in request
  - Merge agent tools with request tools using existing mergeTools logic
  - Apply agent vector_store_ids via applyFileSearchDefaults
  - Apply agent model/temperature/etc. to payload

- [ ] Update handleResponsesChatSync
  - Same merging logic as streaming version

- [ ] Update handleChatCompletion (for chat mode agents)
  - Resolve agent config
  - If agent has system_message, use it instead of config default
  - If agent has model/temperature, override config values

- [ ] Update handleChatCompletionSync
  - Same merging logic as streaming version

- [ ] Fallback to default agent if no agent_id provided
  - Check if AgentService is available
  - Call AgentService->getDefaultAgent()
  - If default agent exists, use its config
  - Otherwise fall back to config.php behavior

**Testing:**
- [ ] Test chat with explicit agent_id
- [ ] Test chat with default agent (no agent_id provided)
- [ ] Test chat with invalid agent_id (should log warning and fallback)
- [ ] Test agent overrides apply correctly (prompt, tools, model, etc.)
- [ ] Test merging precedence (request > agent > config)
- [ ] Test both streaming and sync modes
- [ ] Test both Responses and Chat APIs

---

### 5.2 Widget Updates for Agent Selection

**Files to Modify:**
- `chatbot-enhanced.js`

**Tasks - Widget Configuration:**
- [ ] Add agent configuration options
  - `agentId` (string, optional) - Fixed agent to use
  - `enableAgentSelection` (boolean, default false) - Show agent dropdown
  - `agentsEndpoint` (string, default '/admin-api.php/agents') - For fetching agents list

- [ ] Add agent selection UI (if enabled)
  - Dropdown in header or above input
  - Load agents from endpoint (needs public-safe endpoint or filtered response)
  - Store selected agent in widget state
  - Include selected agent_id in requests

- [ ] Update request assembly
  - Include agent_id in request body:
    ```javascript
    {
      message: '...',
      conversation_id: '...',
      api_type: '...',
      agent_id: this.selectedAgentId || this.config.agentId
    }
    ```

- [ ] Update SSE start event handling
  - Display agent name if available (from start event metadata)

**Optional - Public Agents Endpoint:**
- [ ] Create GET /agents-public in admin-api.php
  - No auth required
  - Return only: id, name, api_type
  - Filter out is_default flag and sensitive config
  - Used by widget to populate agent dropdown

**Testing:**
- [ ] Test widget with fixed agentId config
- [ ] Test widget with agent selection dropdown (if implemented)
- [ ] Test request includes agent_id parameter
- [ ] Test widget works without agent (backwards compatibility)

---

## Phase 6: Documentation & Deployment

### 6.1 Documentation Updates

**Files to Update:**
- `README.md`
- `docs/api.md`
- `docs/deployment.md`
- `docs/customization-guide.md`

**Files to Create:**
- `docs/admin-ui-guide.md` - Comprehensive Admin UI usage guide

**Tasks - README.md:**
- [ ] Add Admin UI section
  - Overview of Admin UI features
  - Quick start guide
  - Link to detailed documentation

- [ ] Update Quick Start with Admin setup
  - Step to enable Admin UI
  - Step to set ADMIN_TOKEN
  - Step to run migrations
  - Step to access /admin

- [ ] Update Configuration section
  - Add Admin environment variables
  - Explain agent selection in widget

**Tasks - admin-ui-guide.md:**
- [ ] Create comprehensive guide
  - Installation and setup
  - Running migrations
  - Accessing Admin UI
  - Managing Agents (with screenshots/examples)
  - Managing Prompts (with examples)
  - Managing Vector Stores (with examples)
  - File management
  - Testing Agents
  - Security best practices
  - Troubleshooting common issues

**Tasks - docs/api.md:**
- [ ] Document Admin API endpoints
  - Authentication
  - Agents endpoints with request/response examples
  - Prompts endpoints
  - Vector Stores endpoints
  - Files endpoints
  - Health endpoint

- [ ] Document agent_id parameter in chat endpoint
  - Update POST /chat-unified.php examples
  - Show agent selection in requests

**Tasks - docs/deployment.md:**
- [ ] Add Admin UI deployment section
  - Apache configuration for /admin route
  - Nginx configuration for /admin route
  - Static asset serving
  - Database setup (SQLite vs MySQL)
  - Migration execution
  - Admin token generation
  - CORS configuration

- [ ] Add security considerations
  - Admin token best practices
  - CORS lockdown for admin-api.php
  - HTTPS requirement
  - Database backup strategies

**Tasks - docs/customization-guide.md:**
- [ ] Add agent customization section
  - How to create custom agents
  - Tool configuration examples
  - Vector store integration
  - Prompt template design

**Testing:**
- [ ] Review all documentation for accuracy
- [ ] Test all code examples
- [ ] Verify links work correctly
- [ ] Ensure screenshots are up-to-date

---

### 6.2 Configuration & Environment

**Files to Update:**
- `.env.example`
- `config.php`
- `docker-compose.yml`
- `Dockerfile`

**Tasks - .env.example:**
- [ ] Add all Admin UI variables
  ```bash
  # Admin UI Configuration
  ADMIN_ENABLED=true
  ADMIN_TOKEN=your_random_admin_token_here_min_32_chars
  ADMIN_DB_TYPE=sqlite
  ADMIN_DB_PATH=./data/admin.db
  # For MySQL:
  # ADMIN_DATABASE_URL=mysql://user:pass@localhost/chatbot_admin
  ```

**Tasks - config.php:**
- [ ] Add admin configuration section (already planned in Phase 1.1)

**Tasks - docker-compose.yml:**
- [ ] Add volume for admin database
  - SQLite: mount ./data directory
  - MySQL: add mysql service (optional)

- [ ] Add environment variables for admin
  - Map ADMIN_* variables
  - Ensure migrations can run on container start

- [ ] Add migration init script (optional)
  - Run migrations automatically on first start
  - Or provide manual command in docs

**Tasks - Dockerfile:**
- [ ] Create data directory for SQLite
  - `RUN mkdir -p /var/www/html/data && chmod 755 /var/www/html/data`

- [ ] Ensure public/admin is copied
  - Verify COPY commands include admin assets

- [ ] Add composer dependencies if needed
  - No new dependencies expected for v1

**Testing:**
- [ ] Test Docker build with Admin UI enabled
- [ ] Test migrations run successfully in container
- [ ] Test Admin UI accessible at /admin
- [ ] Test database persists across container restarts (volume)

---

## Phase 7: Testing & Quality Assurance

### 7.1 Unit Tests

**Files to Create:**
- `tests/AgentServiceTest.php`
- `tests/OpenAIAdminClientTest.php`
- `tests/AdminControllerTest.php`
- `tests/DBTest.php`

**Tasks:**
- [ ] Test DB.php
  - Connection handling
  - CRUD operations
  - Transaction rollback
  - Error handling

- [ ] Test AgentService
  - createAgent validation
  - updateAgent
  - deleteAgent
  - resolveAgentConfig merging
  - setDefaultAgent (only one default)

- [ ] Test OpenAIAdminClient (with mocked responses)
  - All Prompts API methods
  - All Vector Stores API methods
  - All Files API methods
  - Error handling

- [ ] Test AdminController
  - Authentication
  - Routing
  - Agent endpoints
  - Error responses

**Testing:**
- [ ] Run PHPUnit tests
- [ ] Achieve >80% code coverage for new code
- [ ] Fix any failing tests

---

### 7.2 Integration Tests

**Tasks:**
- [ ] Test full Admin API flow
  - Create agent via API
  - Update agent via API
  - Fetch agent via API
  - Use agent in chat request
  - Verify agent config applied

- [ ] Test Prompts integration
  - Create prompt via Admin UI
  - Reference prompt in agent
  - Test chat uses correct prompt

- [ ] Test Vector Stores integration
  - Create vector store via Admin UI
  - Upload file to store
  - Reference store in agent
  - Test file_search returns results

- [ ] Test Agent selection in widget
  - Configure widget with agent_id
  - Send message
  - Verify request includes agent_id
  - Verify response uses agent config

**Testing:**
- [ ] Run integration tests against local OpenAI API or mocks
- [ ] Document any manual testing steps

---

### 7.3 End-to-End Tests

**Tasks:**
- [ ] Test complete user workflow
  1. Access Admin UI
  2. Create a new agent with prompt + vector store
  3. Mark as default
  4. Open chat widget (no agent_id specified)
  5. Send message
  6. Verify response uses default agent config
  7. Verify tool calls execute
  8. Verify vector search works

- [ ] Test edge cases
  - Invalid agent_id (should fallback)
  - Missing prompt_id in agent (should work)
  - Deleted vector store (should handle gracefully)
  - API errors from OpenAI (should surface in Admin UI)

**Testing:**
- [ ] Manual QA of entire workflow
- [ ] Document test scenarios and results

---

## Phase 8: Security & Performance

### 8.1 Security Hardening

**Tasks:**
- [ ] Validate Admin token strength
  - Require minimum 32 characters
  - Warn on startup if token too short or default

- [ ] Implement request signing (optional for v1)
  - HMAC signature of request body
  - Prevent replay attacks

- [ ] Add CORS whitelist for admin-api.php
  - Default to localhost only
  - Configurable via ADMIN_CORS_ORIGINS env var

- [ ] Sanitize all inputs in AdminController
  - Use prepared statements (already in DB.php)
  - Validate UUIDs, names, etc.

- [ ] Encrypt sensitive agent data at rest (optional for v2)
  - Encrypt API keys if per-agent keys supported
  - Use libsodium or openssl

- [ ] Add audit log review page in Admin UI
  - View recent actions
  - Filter by actor, action, date

**Testing:**
- [ ] Security audit of admin-api.php
- [ ] Test SQL injection attempts (should fail)
- [ ] Test XSS attempts in Admin UI (should be sanitized)
- [ ] Test CSRF protection (if implemented)

---

### 8.2 Performance Optimization

**Tasks:**
- [ ] Add caching for agent configs
  - Cache resolveAgentConfig results
  - Invalidate on agent update
  - Use APCu or Redis if available

- [ ] Add database indexes
  - Index on agents.name, agents.is_default
  - Index on audit_log.created_at

- [ ] Optimize Admin UI
  - Lazy load large lists
  - Debounce search inputs
  - Minimize API calls (batch where possible)

- [ ] Add pagination to all list endpoints
  - Limit default page size to 20
  - Support cursor-based pagination

**Testing:**
- [ ] Load test Admin API with multiple concurrent requests
- [ ] Measure response times for agent resolution
- [ ] Test UI performance with 100+ agents

---

## Phase 9: Migration & Rollout

### 9.1 Migration Plan

**Tasks:**
- [ ] Create migration guide for existing users
  - Backup existing .env and config.php
  - Run migrations
  - Create default agent from current config
  - Test chat still works
  - Access Admin UI and verify

- [ ] Create seed script (optional)
  - Populate default agent from config.php
  - Script: `scripts/seed-default-agent.php`
  - Run after migrations

**Testing:**
- [ ] Test migration on fresh install
- [ ] Test migration on existing deployment
- [ ] Verify backwards compatibility (no agent_id still works)

---

### 9.2 Rollout Phases

**Phase 1: Read-Only Admin (Week 1)**
- [ ] Deploy database and AgentService
- [ ] Deploy OpenAIAdminClient
- [ ] Deploy read-only Admin UI (list agents, prompts, stores)
- [ ] Enable default agent selection in chat
- [ ] Test and gather feedback

**Phase 2: Agent CRUD (Week 2)**
- [ ] Enable Agent creation/editing in Admin UI
- [ ] Enable agent selection in chat via agent_id
- [ ] Test agent config merging
- [ ] Deploy to staging environment

**Phase 3: Prompts & Vector Stores (Week 3)**
- [ ] Enable Prompt creation/versioning in Admin UI
- [ ] Enable Vector Store creation and file management
- [ ] Test end-to-end agent with custom prompt and store
- [ ] Deploy to production

**Phase 4: Polish & Advanced Features (Week 4)**
- [ ] Add Agent Test feature
- [ ] Add audit log viewer
- [ ] Add health monitoring
- [ ] Performance optimizations
- [ ] Security hardening
- [ ] Documentation finalization

---

## Open Questions & Decisions Needed

1. **Database Choice:**
   - Default to SQLite for simplicity?
   - Provide MySQL migration but not required?
   - **Decision:** Start with SQLite, support MySQL as optional

2. **Agent Selection UI:**
   - Add dropdown to widget now or later?
   - **Decision:** Add config option but implement in Phase 3

3. **Admin Users:**
   - Single token for v1, or basic email/password?
   - **Decision:** Single token for v1, plan upgrade for v2

4. **Per-Agent API Keys:**
   - Support BYO API key per agent?
   - **Decision:** Out of scope for v1

5. **Conversation Persistence to DB:**
   - Move from session/file to database?
   - **Decision:** Out of scope for v1, keep current storage

6. **Prompts API Availability:**
   - How to handle if Prompts API not available?
   - **Decision:** Graceful degradation - allow manual ID entry, show warning

---

## Success Criteria

- [ ] Admin can create an Agent via Admin UI
- [ ] Admin can reference an OpenAI prompt by ID/version in agent
- [ ] Admin can create and manage Vector Stores via Admin UI
- [ ] Admin can upload files to Vector Stores and see ingestion status
- [ ] End-user chat uses agent_id and applies agent config (prompt, tools, model)
- [ ] Chat with file_search tool returns results from configured vector stores
- [ ] Invalid prompt/version in agent triggers retry with fallback (existing logic)
- [ ] Admin API rejects requests without valid Authorization header
- [ ] All Admin actions are logged to audit_log table
- [ ] Documentation updated with Admin UI setup and usage
- [ ] Docker deployment includes Admin UI and database setup
- [ ] Backwards compatibility maintained (existing chats work without agent_id)

---

## Risks & Mitigations

1. **Risk:** Prompts API unavailable in some OpenAI accounts
   - **Mitigation:** Allow manual prompt ID entry, test-run feature validates

2. **Risk:** Vector store ingestion delays cause confusion
   - **Mitigation:** Show status timestamps, refresh button, warning messages

3. **Risk:** Single admin token is security weak
   - **Mitigation:** Enforce strong token, plan upgrade to user accounts in v2

4. **Risk:** Database schema changes break existing deployments
   - **Mitigation:** Versioned migrations, backwards compatibility checks

5. **Risk:** Agent config merging is complex and error-prone
   - **Mitigation:** Comprehensive tests, clear precedence rules, logging

6. **Risk:** Admin UI performance degrades with many agents
   - **Mitigation:** Pagination, search/filter, caching

---

## Next Steps

1. Review and approve this implementation plan
2. Set up project tracking (GitHub issues/projects)
3. Begin Phase 1: Database Layer & Agent Model
4. Implement incrementally with testing at each phase
5. Deploy to staging environment for early feedback
6. Iterate based on feedback
7. Production rollout in phases

---

## Estimated Timeline

- **Phase 1:** 3-4 days (DB layer, migrations, AgentService)
- **Phase 2:** 2-3 days (OpenAIAdminClient)
- **Phase 3:** 3-4 days (Admin API backend)
- **Phase 4:** 5-7 days (Admin UI frontend)
- **Phase 5:** 2-3 days (Chat flow integration)
- **Phase 6:** 2-3 days (Documentation)
- **Phase 7:** 3-4 days (Testing)
- **Phase 8:** 2-3 days (Security & Performance)
- **Phase 9:** 1-2 days (Migration & Rollout)

**Total:** ~25-35 days for full implementation

---

## Conclusion

This implementation plan provides a comprehensive roadmap for adding a Visual AI Agent Template + Admin UI to the GPT Chatbot Boilerplate. The plan maintains backwards compatibility, follows the existing architecture patterns, and implements features incrementally to minimize risk.

The Admin UI will empower non-technical users to create and manage AI agents, prompts, and vector stores without code changes, while the enhanced chat flow will seamlessly integrate agent configurations at runtime.
