# Enhanced GPT Chatbot Web Integration Boilerplate

An advanced open-source boilerplate for embedding GPT-powered chatbots on any website with **dual API support** (Chat Completions + Responses API), real-time streaming, white-label customization, and easy deployment.

## 🚀 New Features in v2.1

### 🔥 **Dual API Support**
- **Chat Completions API**: Traditional stateless chat interface.
- **Responses API**: Prompt-template aware streaming with tool calling and file attachments.
- **One toggle** via configuration to switch between APIs.
- **Automatic fallback** and robust error handling.

### 🧠 **Responses API Enhancements**
- **Prompt Templates**: Reference saved prompts via `RESPONSES_PROMPT_ID` and optional versioning.
- **Inline + referenced prompts**: Mix local context with reusable system instructions.
- **Tool Calls**: Stream tool call deltas and auto-submit outputs when enabled.
- **File Attachments**: Upload user files directly with the appropriate `user_data` purpose.

### 📎 **File Upload Support**
- Multiple file types (PDF, DOC, images, text files).
- File size validation and type restrictions.
- Visual file preview before sending.

### 🎯 **Enhanced UI/UX**
- API type indicators showing the active workflow.
- Tool execution visualization for Responses API events.
- File attachment display in messages.
- Improved responsive design.

### 🗄️ **Phase 1: Database Layer & Agent Model**
- **Agent Management**: Persistent storage for AI agent configurations.
- **Admin API**: Token-protected REST API for CRUD operations on agents.
- **Dynamic Configuration**: Override prompts, tools, models, and parameters per agent.
- **Default Agent**: Set a default agent for requests without explicit `agent_id`.
- **SQLite Support**: Zero-config database with optional MySQL compatibility.
- See [docs/PHASE1_DB_AGENT.md](docs/PHASE1_DB_AGENT.md) for details.

### 🎨 **Phase 2: Admin UI, Prompts & Vector Store Management**
- **Visual Admin Interface**: Comprehensive web UI for managing all resources.
- **Prompt Management**: Create, version, and sync OpenAI prompts.
- **Vector Store Management**: Upload files, manage stores, monitor ingestion.
- **Agent Testing**: Test agents with streaming responses directly from Admin UI.
- **Health Monitoring**: Real-time system health and API connectivity checks.
- **Audit Logging**: Complete audit trail of all admin operations.
- See [docs/PHASE2_ADMIN_UI.md](docs/PHASE2_ADMIN_UI.md) for details.

### 🔐 **Phase 3: Background Workers, Webhooks & RBAC**
- **Background Job Processing**: Asynchronous file ingestion and long-running operations.
- **Webhook Support**: Real-time event notifications from OpenAI with signature verification.
- **Role-Based Access Control**: Multi-user admin access with three permission levels (viewer, admin, super-admin).
- **API Key Authentication**: Per-user API keys with expiration and revocation support.
- **Production Observability**: Health checks, Prometheus metrics, enhanced audit logging.
- **Worker Architecture**: Scalable CLI worker with exponential backoff and retry logic.
- **Job Management**: Monitor, retry, and cancel background jobs via Admin API.
- **Resource-Level Authorization**: Per-resource access control with tenant isolation and explicit permission grants.
- See [docs/PHASE3_WORKERS_WEBHOOKS.md](docs/PHASE3_WORKERS_WEBHOOKS.md) and [docs/RESOURCE_AUTHORIZATION.md](docs/RESOURCE_AUTHORIZATION.md) for details.

### 🛡️ **Phase 4: Admin API Completion & Security**
- **Rate Limiting**: IP-based rate limiting for admin endpoints (300 req/min default).
- **Files API**: Standalone file management endpoints (list, upload, delete).
- **Complete API Coverage**: 37 endpoints across all resources (agents, prompts, vector stores, jobs, users).
- **Enhanced Security**: Comprehensive error handling, audit logging, and permission checks.
- **Production Ready**: Full test coverage (14 tests in phase 4, 155 total at completion), static analysis (PHPStan), documented configuration, backward compatible.

### 🏢 **Multi-Tenancy Architecture**
- **Complete Tenant Isolation**: Full multi-tenant architecture with tenant-scoped queries across all services.
- **Tenant Management**: Create, update, suspend, and delete tenants via Admin API.
- **Tenant-Aware Services**: All core services (AgentService, PromptService, VectorStoreService, AuditService, ChannelSessionService, ChannelMessageService) support tenant scoping.
- **Admin User Mapping**: Admin users can be assigned to specific tenants or have super-admin access across all tenants.
- **Resource Statistics**: Get resource counts and usage statistics per tenant.
- **Data Migration**: Migration script to convert single-tenant deployments to multi-tenant.
- **Comprehensive Testing**: 48 tests validating tenant isolation and cross-tenant security.
- See [docs/MULTI_TENANCY.md](docs/MULTI_TENANCY.md) for complete documentation.

### 🎯 **Phase 5: Agent Integration & Testing**
- **Agent Integration**: Full agent support in chat interface with configuration merging.
- **Widget Integration**: JavaScript widget supports agent selection.
- **Configuration Priority**: Request → Agent → Config defaults.
- **Comprehensive Testing**: 33 integration tests validating agent functionality.
- **Backward Compatible**: Existing code works unchanged, agents are optional.

### 🚀 **Phase 10: Production Readiness & Operations**
- **CI/CD Pipeline**: Automated testing (183 tests), static analysis (PHPStan), and linting (ESLint).
- **Backup & Disaster Recovery**: 
  - Comprehensive backup system for all persistent data (database, files, config, application data)
  - Automated multi-tier backup strategy (hourly DB, daily full, weekly off-site, monthly long-term)
  - Off-site backup support (rsync, AWS S3, Azure Blob, Google Cloud Storage)
  - Complete disaster recovery runbook with RPO/RTO definitions and tested procedures
  - Automated backup monitoring with email/Slack/webhook alerts
  - Quarterly DR drill procedures and backup integrity testing
- **Observability**: Prometheus metrics endpoint (`/metrics.php`) and enhanced health checks.
- **Dead Letter Queue**: Failed job management with retry and requeue capabilities.
- **Secrets Management**: Admin token rotation and comprehensive secrets documentation.
- **Security Hardening**: Production-ready Nginx configuration with security headers and rate limiting.
- **Load Testing**: K6 scripts for capacity testing and performance validation.
- **Operational Docs**: Complete runbooks for incident response, monitoring, log aggregation, and disaster recovery.

### 🛡️ **Hybrid Guardrails Integration**
- **Response Format Support**: Enforce structured outputs using JSON schemas with the Responses API.
- **Hybrid Configuration**: Combine OpenAI stored prompts (`prompt_id`) with local system messages and response format guardrails.
- **Three Format Types**: Text (default), JSON Object (flexible), and JSON Schema (strict validation).
- **Configuration Precedence**: Request > Agent > Config > Defaults for maximum flexibility.

### 📊 **Private Audit Trails**
- **Comprehensive Tracking**: Capture every user-assistant interaction with metadata for analysis and compliance.
- **Security & Privacy**: PII redaction, AES-256-GCM encryption at rest, content hashing, and RBAC access control.
- **Hallucination Detection**: Track fallbacks, errors, tool consistency, and retrieval coverage for quality monitoring.
- **Admin Query API**: Filter, search, and export audit data; decrypt content with elevated permissions.
- **Automated Retention**: Configurable retention periods with automated cleanup and legal hold support.
- **Event Tracking**: Captures request lifecycle, streaming events, tool calls, fallbacks, and errors.
- See [docs/AUDIT_TRAILS.md](docs/AUDIT_TRAILS.md) for complete documentation.
- **Database Storage**: Store response_format configurations in agent records for reusability.
- **Practical Examples**: Bedtime story generator, research assistant with citations, data extraction, and more.
- See [docs/HYBRID_GUARDRAILS.md](docs/HYBRID_GUARDRAILS.md) for detailed guide and examples.

### 📱 **WhatsApp Channel Integration (Z-API)**
- **Multi-Channel Support**: Connect agents to WhatsApp Business via Z-API for omnichannel communication.
- **Per-Agent Configuration**: Each agent can have its own WhatsApp channel with independent settings.
- **Session Management**: Automatic conversation tracking and context preservation across messages.
- **Media Support**: Handle images, documents, and other file types with configurable size and type limits.
- **Message Chunking**: Automatically split long responses to fit WhatsApp message limits.
- **Idempotency**: Built-in duplicate message detection to prevent processing the same message twice.
- **Opt-out Support**: Handle STOP/START commands for user privacy compliance.
- **Admin UI Integration**: Configure and test WhatsApp channels directly from the Admin panel.
- **Webhook Security**: Support for signature verification and HTTPS-only webhooks.
- **Audit Trail**: Complete message history and session tracking for compliance.
- See [docs/WHATSAPP_INTEGRATION.md](docs/WHATSAPP_INTEGRATION.md) for setup guide and API reference.

## 📋 Requirements

- PHP 8.0+ with cURL, JSON, and PDO extensions
- Apache or Nginx web server
- OpenAI API key
- Composer (for dependency management)
- Database: SQLite (included) or MySQL 8.0+
- Optional: Docker for containerized deployment
- Optional: Node.js and npm (for frontend development and linting)

## 🚀 Quick Start

### Option 0: Web-Based Installation (Recommended for First-Time Users)

The easiest way to get started is using our web-based installation wizard:

1. Clone and start the application:
```bash
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate

# Start with Docker (includes MySQL)
docker-compose up -d

# Or start with PHP built-in server
php -S localhost:8000
```

2. Open the installation wizard in your browser:
```
http://localhost:8088/setup/install.php
# or http://localhost:8000/setup/install.php (if using PHP built-in server)
```

3. Follow the step-by-step wizard to:
   - Verify system requirements
   - Configure OpenAI API settings
   - Choose database type (SQLite or MySQL)
   - Set up admin credentials
   - Enable optional features
   - Initialize the database

4. After installation, access:
   - **Admin Panel**: `http://localhost:8088/public/admin/`
   - **Chatbot**: `http://localhost:8088/`

The installation wizard will generate a `.env` file with all your settings and create a `.install.lock` file to prevent accidental re-installation.

### Option 1: Chat Completions API (Simple)

1. Clone and configure:
```bash
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate
cp .env.example .env
```

2. Edit `.env` for Chat Completions:
```bash
API_TYPE=chat
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4o-mini
```

3. Start with Docker:
```bash
docker-compose up -d
```

### Option 2: Responses API (Advanced)

1. Configure for Responses API in `.env`:
```bash
API_TYPE=responses
OPENAI_API_KEY=your_openai_api_key_here
RESPONSES_MODEL=gpt-4o-mini
RESPONSES_PROMPT_ID=pmpt_your_prompt_id   # optional, reference a saved prompt
RESPONSES_PROMPT_VERSION=1                # optional, defaults to latest
RESPONSES_TEMPERATURE=0.7
RESPONSES_MAX_OUTPUT_TOKENS=1024
# Tools & file search defaults (JSON or comma-separated values)
RESPONSES_TOOLS=[{"type":"file_search"}]      # JSON array or comma-separated tool types
RESPONSES_VECTOR_STORE_IDS=vs_1234567890,vs_0987654321
RESPONSES_MAX_NUM_RESULTS=20
```

2. Enable file uploads (optional):
```bash
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png
```

### Option 3: MySQL Database Deployment

For production environments or when you need a robust database, use MySQL:

1. Configure MySQL in `.env`:
```bash
# Database Configuration
DATABASE_URL=mysql:host=mysql;port=3306;dbname=chatbot;charset=utf8mb4
DB_HOST=mysql
DB_PORT=3306
DB_NAME=chatbot
DB_USER=chatbot
DB_PASSWORD=your_secure_password
MYSQL_ROOT_PASSWORD=your_root_password

# Leave DATABASE_PATH empty when using MySQL
DATABASE_PATH=
```

2. Start with Docker (includes MySQL service):
```bash
docker-compose up -d
```

The docker-compose.yml includes:
- **MySQL 8.0** service with persistent storage
- Automatic database initialization
- Health checks for both services
- Volume mounting for data persistence

3. Access MySQL directly (optional):
```bash
# Connect to MySQL container
docker-compose exec mysql mysql -u chatbot -p

# Or from host (if port 3306 is exposed)
mysql -h 127.0.0.1 -P 3306 -u chatbot -p chatbot
```

### Option 4: Admin API & Agent Management (Phase 1)

1. Enable admin features in `.env`:
```bash
# Enable admin API
ADMIN_ENABLED=true
ADMIN_TOKEN=generate_a_secure_random_token_min_32_chars

# Database configuration (SQLite by default)
DATABASE_PATH=./data/chatbot.db
# Or use MySQL:
# DATABASE_URL=mysql://user:password@localhost/chatbot_db
```

2. Run migrations (automatic on first request, or manually):
```bash
# Migrations run automatically when admin-api.php or chat-unified.php is accessed
# To verify:
php -r "require 'includes/DB.php'; \$db = new DB(['database_path' => './data/chatbot.db']); echo \$db->runMigrations('./db/migrations') . ' migrations executed';"
```

3. Create your first agent via Admin API:
```bash
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support",
    "api_type": "responses",
    "prompt_id": "pmpt_abc123",
    "model": "gpt-4o",
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_knowledge_base"],
    "is_default": true
  }'
```

4. Use the agent in chat requests:
```bash
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is your return policy?",
    "conversation_id": "conv_123",
    "agent_id": "agent-uuid-from-create-response",
    "stream": false
  }'
```

For complete Admin API documentation, see [docs/PHASE1_DB_AGENT.md](docs/PHASE1_DB_AGENT.md).

### Option 5: Hybrid Guardrails for Structured Outputs

1. Create an agent with JSON schema response format:
```bash
curl -X POST "http://localhost/admin-api.php" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "create_agent",
    "data": {
      "name": "Bedtime Story Generator",
      "api_type": "responses",
      "model": "gpt-4.1",
      "system_message": "Always respond according to the JSON schema. If unsure, use empty strings.",
      "response_format": {
        "type": "json_schema",
        "json_schema": {
          "name": "bedtime_story",
          "schema": {
            "type": "object",
            "properties": {
              "title": {"type": "string"},
              "story": {"type": "string"},
              "moral": {"type": "string"}
            },
            "required": ["title", "story", "moral"]
          }
        }
      }
    }
  }'
```

2. Use the agent in chat:
```bash
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Create a bedtime story about a unicorn that learns to share.",
    "conversation_id": "story_001",
    "api_type": "responses",
    "agent_id": "YOUR_AGENT_ID"
  }'
```

3. Expected response format (enforced by JSON schema):
```json
{
  "title": "Luna the Unicorn Learns to Share",
  "story": "Once upon a time...",
  "moral": "Sharing brings joy to everyone."
}
```

For complete Hybrid Guardrails documentation and examples, see [docs/HYBRID_GUARDRAILS.md](docs/HYBRID_GUARDRAILS.md).

### Option 6: Admin UI for Visual Management (Phase 2)

1. Complete Phase 1 setup (database and admin token).

2. Access the Admin UI:
```
http://localhost/public/admin/
```

3. Enter your admin token when prompted (stored in localStorage).

4. Create and manage resources visually:
   - **Agents**: Full CRUD with test capability
   - **Prompts**: Create, version, sync from OpenAI
   - **Vector Stores**: Upload files, monitor ingestion
   - **Settings**: Health checks and system status

5. Test an agent:
   - Navigate to Agents page
   - Click "Test" button on any agent
   - Enter a test message
   - View streaming response in real-time

For complete Admin UI documentation, see [docs/PHASE2_ADMIN_UI.md](docs/PHASE2_ADMIN_UI.md).

### Option 7: Background Workers, Webhooks & RBAC (Phase 3)

1. Complete Phase 1 and Phase 2 setup.

2. Configure Phase 3 features in `.env`:
```bash
# Background Jobs (optional but recommended for production)
ADMIN_JOBS_ENABLED=true

# Webhook Configuration (optional)
WEBHOOK_OPENAI_SIGNING_SECRET=generate_secure_secret_here
```

3. Create admin users and API keys:
```bash
# Create a super-admin user
curl -X POST "http://localhost/admin-api.php?action=create_user" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secure_password",
    "role": "super-admin"
  }'

# Generate an API key for the user
curl -X POST "http://localhost/admin-api.php?action=generate_api_key" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user_id_from_above",
    "name": "Admin Dashboard Key",
    "expires_in_days": 365
  }'
```

4. Start the background worker:
```bash
# Run continuously
php scripts/worker.php --daemon --verbose

# Or use systemd (production)
sudo systemctl enable chatbot-worker
sudo systemctl start chatbot-worker

# Or with Docker
docker-compose up -d worker
```

5. Configure OpenAI webhooks (optional):
   - URL: `https://yourdomain.com/webhooks/openai.php`
   - Events: `vector_store.*`, `file.*`
   - Signing secret: Use the value from `WEBHOOK_OPENAI_SIGNING_SECRET`

6. Monitor system health:
```bash
# Health check
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "http://localhost/admin-api.php?action=health"

# Prometheus metrics
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "http://localhost/admin-api.php?action=metrics"

# Job queue status
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "http://localhost/admin-api.php?action=job_stats"
```

For complete Phase 3 documentation, see [docs/PHASE3_WORKERS_WEBHOOKS.md](docs/PHASE3_WORKERS_WEBHOOKS.md).


3. Start the application:
```bash
docker-compose up -d
```

## 💻 Integration Examples

### Basic Chat Completions Integration

```html
<script src="chatbot-enhanced.js"></script>
<script>
ChatBot.init({
    mode: 'floating',
    apiType: 'chat',
    apiEndpoint: '/chat-unified.php',
    title: 'Support Chat',
    assistant: {
        name: 'ChatBot',
        welcomeMessage: 'Hi! How can I help you today?'
    }
});
</script>
```

### Advanced Responses API Integration

```html
<script src="chatbot-enhanced.js"></script>
<script>
ChatBot.init({
    mode: 'inline',
    apiType: 'responses',
    apiEndpoint: '/chat-unified.php',
    enableFileUpload: true,

    assistant: {
        name: 'AI Guide',
        welcomeMessage: 'Hello! I can help with questions, analyze documents, and trigger tools.'
    },

    responsesConfig: {
        promptId: 'pmpt_your_prompt_id',
        promptVersion: '1',
        defaultTools: [
            { type: 'file_search' }
        ],
        defaultVectorStoreIds: ['vs_1234567890'],
        defaultMaxNumResults: 20
    },

    onToolCall: function(toolData) {
        console.log('Tool executed:', toolData);
    },

    onFileUpload: function(files) {
        console.log('Files uploaded:', files);
    }
});
</script>
```

> **Tip:** Any values supplied in `responsesConfig` are forwarded with each `/chat-unified.php` request using snake_case keys. For example, `defaultVectorStoreIds` automatically becomes `default_vector_store_ids` so the PHP endpoint can merge them with server defaults from `RESPONSES_VECTOR_STORE_IDS`.

### File Upload Configuration

```javascript
ChatBot.init({
    enableFileUpload: true,
    maxFileSize: 10485760, // 10MB
    allowedFileTypes: ['txt', 'pdf', 'doc', 'docx', 'jpg', 'png'],

    assistant: {
        welcomeMessage: 'Hi! You can ask questions or upload files for analysis.',
        processingFile: 'Processing your file...'
    }
});
```

## 🔧 Configuration

### Dual API Configuration

The enhanced version supports both APIs through a single configuration:

```php
// config.php
return [
    'api_type' => 'responses', // 'chat' or 'responses'

    // Chat Completions settings
    'chat' => [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7,
        'system_message' => 'You are a helpful assistant.'
    ],

    // Responses API settings
    'responses' => [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7,
        'max_output_tokens' => 1024,
        'prompt_id' => 'pmpt_your_prompt_id',
        'prompt_version' => '1',
        'default_tools' => [
            ['type' => 'file_search']
        ],
        'default_vector_store_ids' => ['vs_1234567890'],
        'default_max_num_results' => 20
    ]
];
```

The `RESPONSES_TOOLS`, `RESPONSES_VECTOR_STORE_IDS`, and `RESPONSES_MAX_NUM_RESULTS` environment variables hydrate these defaults automatically. Provide JSON arrays (e.g., `[{"type":"file_search"}]`) or comma-separated lists (`vs_123,vs_456`) and the backend merges them with any request-level overrides (config → request → final payload).

### Agent-Based Configuration (Phase 5)

In addition to static config.php settings, you can create dynamic agents that override configuration at runtime:

#### Using Agents in Requests

```bash
# Use a specific agent
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Help me with my order",
    "agent_id": "agent_uuid_here",
    "conversation_id": "conv_123"
  }'

# Or use the default agent (no agent_id needed)
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Help me with my order",
    "conversation_id": "conv_123"
  }'
```

#### Configuration Priority

When using agents, configuration values merge with this priority:

1. **Request parameters** (highest)
2. **Agent configuration**
3. **config.php defaults** (lowest)

This allows you to:
- Create multiple AI personalities without code changes
- Override prompts, models, tools per agent
- Set a default agent for all requests
- Override agent settings per request if needed

For complete agent documentation, see [docs/customization-guide.md](docs/customization-guide.md#agent-based-configuration).

### File Upload Configuration

```php
'chat_config' => [
    'enable_file_upload' => true,
    'max_file_size' => 10485760, // 10MB
    'allowed_file_types' => ['txt', 'pdf', 'doc', 'docx', 'jpg', 'png']
]
```

## 🏗️ Enhanced Architecture

### File Structure

```
├── chat-unified.php          # Unified endpoint for both APIs
├── chatbot-enhanced.js       # Enhanced JavaScript widget (2,176 lines)
├── admin-api.php             # Admin API endpoint (1,336 lines)
├── metrics.php               # Prometheus metrics endpoint
├── websocket-server.php      # Optional WebSocket relay
├── includes/
│   ├── ChatHandler.php       # Chat orchestration & conversation management (1,410 lines)
│   ├── OpenAIClient.php      # OpenAI API transport layer (300 lines)
│   ├── AgentService.php      # Agent CRUD & configuration (394 lines)
│   ├── OpenAIAdminClient.php # OpenAI admin APIs (prompts, stores, files) (437 lines)
│   ├── PromptService.php     # Prompt management (341 lines)
│   ├── VectorStoreService.php # Vector store management (486 lines)
│   ├── JobQueue.php          # Background job queue (663 lines)
│   ├── WebhookHandler.php    # OpenAI webhook processing (156 lines)
│   ├── AdminAuth.php         # RBAC & API key auth (345 lines)
│   └── DB.php                # Database abstraction layer (265 lines)
├── public/admin/             # Admin UI (SPA)
│   ├── index.html
│   ├── admin.js              # Admin interface logic (1,661 lines)
│   └── admin.css
├── scripts/
│   ├── worker.php            # Background job worker
│   ├── db_backup.sh          # Database backup automation
│   ├── db_restore.sh         # Database restore tool
│   └── smoke_test.sh         # Production readiness checks
├── db/migrations/            # 9 database migrations
├── tests/                    # 183 automated tests
├── webhooks/
│   └── openai.php            # OpenAI webhook receiver
├── config.php                # Unified configuration loader
├── .env.example              # Environment template
└── docs/                     # Comprehensive documentation
```

### API Flow Comparison

#### Chat Completions API Flow
1. User sends message.
2. Add to conversation history.
3. Stream response from OpenAI.
4. Save to session/storage.

#### Responses API Flow
1. User sends message (+ optional files).
2. Build payload from local history + optional prompt reference.
3. Stream `/responses` events (text deltas, tool calls, completions).
4. Auto-submit tool outputs when required.
5. Persist conversation locally for context reuse.

## 🎨 Enhanced UI Features

### API Type Indicators
- Visual indicators showing the active API.
- Distinct styling cues for Chat vs Responses modes.
- Response ID tracking for saved prompt runs.

### File Upload Interface
- Drag & drop file selection.
- File preview with size and type.
- Upload progress indicators.
- File attachment display in messages.

### Tool Execution Visualization
- Real-time tool execution indicators.
- Function call parameter display.
- Tool result integration in the stream.

## 🔐 Security Enhancements

- **File upload validation**: Type, size, and content checking.
- **Conversation isolation**: Local session/file storage per conversation.
- **Function call sandboxing**: Safe custom function execution examples.
- **Enhanced rate limiting**: Request throttling per client.

## 📚 API Reference

### Enhanced JavaScript API

#### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiType` | string | `'responses'` | API to use: `'chat'` or `'responses'` |
| `enableFileUpload` | boolean | `false` | Enable file upload functionality |
| `responsesConfig` | object | `{}` | Responses API defaults forwarded as snake_case (e.g. `defaultVectorStoreIds` → `default_vector_store_ids`) |

#### Callbacks

```javascript
ChatBot.init({
    onToolCall: function(toolData) {
        // Handle tool execution
    },
    onFileUpload: function(files) {
        // Handle file upload
    }
});
```

### Enhanced PHP Endpoint

#### POST /chat-unified.php

Unified endpoint supporting both APIs.

**Request:**
```json
{
    "message": "Hello",
    "conversation_id": "conv_123",
    "api_type": "responses",
    "agent_id": "agent_uuid",  // optional, uses default agent if not specified
    "prompt_id": "pmpt_your_prompt_id",
    "prompt_version": "1",
    "file_data": [
        {
            "name": "document.pdf",
            "type": "application/pdf",
            "data": "base64_encoded_data"
        }
    ]
}
```

**SSE Response Events:**
- `start`: Stream started (includes optional `response_id`).
- `chunk`: Content delta.
- `tool_call`: Tool call details and status updates.
- `done`: Conversation finished with status metadata.
- `error`: Error occurred.

## 🚀 Deployment

### Environment Variables

```bash
# API Selection
API_TYPE=responses

# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_BASE_URL=https://api.openai.com/v1

# Responses API
RESPONSES_MODEL=gpt-4o-mini
RESPONSES_PROMPT_ID=pmpt_your_prompt_id
RESPONSES_PROMPT_VERSION=1
RESPONSES_MAX_OUTPUT_TOKENS=1024
RESPONSES_TEMPERATURE=0.7
# Tools & file search defaults (JSON or comma-separated values)
RESPONSES_TOOLS=[{"type":"file_search"}]
RESPONSES_VECTOR_STORE_IDS=vs_1234567890,vs_0987654321
RESPONSES_MAX_NUM_RESULTS=20

# Chat Completions API
OPENAI_MODEL=gpt-4o-mini
OPENAI_TEMPERATURE=0.7
OPENAI_MAX_TOKENS=1000

# Admin API & Database
ADMIN_ENABLED=true
ADMIN_TOKEN=your_random_admin_token_here_min_32_chars
DATABASE_PATH=./data/chatbot.db
# Or use MySQL/PostgreSQL:
# DATABASE_URL=mysql://user:password@localhost/chatbot_db

# Admin API Rate Limiting
ADMIN_RATE_LIMIT_REQUESTS=300
ADMIN_RATE_LIMIT_WINDOW=60

# Background Jobs
JOBS_ENABLED=true

# File Upload
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png

# Storage
STORAGE_TYPE=file
STORAGE_PATH=/var/chatbot/data
```

### Production Considerations

- **Database**: SQLite for development, PostgreSQL/MySQL for production. See [docs/ops/backup_restore.md](docs/ops/backup_restore.md).
- **File storage**: Configure appropriate storage for uploaded files and backups.
- **Prompt management**: Version control saved prompts and fallback strategies.
- **Resource limits**: Monitor API usage and costs via `/metrics.php` endpoint.
- **Backup strategy**: Use `scripts/db_backup.sh` with automated rotation (7-day default).
- **Observability**: Configure Prometheus scraping of `/metrics.php` and set up alerts.
- **Security**: Use production Nginx config from `docs/ops/nginx-production.conf` with HTTPS, security headers, and rate limiting.
- **Worker Process**: Run `scripts/worker.php --daemon` for background job processing.
- **Webhooks**: Configure OpenAI webhooks to point to `https://yourdomain.com/webhooks/openai.php`.
- **Load Testing**: Run K6 scripts from `tests/load/` to validate capacity before production deployment.

For complete operational documentation, see `docs/ops/` directory.

## 🔄 Migration from v2.0 (Assistants API)

1. **Update configuration**: Replace `API_TYPE=assistants` with `API_TYPE=responses` and remove assistant-specific env vars.
2. **Set prompt IDs**: If you previously relied on assistant instructions, save them as a prompt via `/v1/prompts` and set `RESPONSES_PROMPT_ID`.
3. **Deploy updated code**: This release removes `AssistantManager` and `ThreadManager`—ensure custom code no longer references them.
4. **Test both modes**: Verify chat completions and responses streaming with your prompt templates.

## 🧪 Testing & Validation

### Quick API Testing

```bash
# Test Chat Completions API
curl -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "api_type": "chat"}' \
  http://localhost:8080/chat-unified.php

# Test Responses API
curl -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "api_type": "responses"}' \
  http://localhost:8080/chat-unified.php

# Test with agent
curl -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "agent_id": "your_agent_id"}' \
  http://localhost:8080/chat-unified.php
```

### Running Tests

The project includes comprehensive test coverage (183 tests total):

```bash
# Run all test suites
php tests/run_tests.php                       # Phase 1: Database & Agents (28 tests)
php tests/run_phase2_tests.php                # Phase 2: Prompts & Vector Stores (44 tests)
php tests/run_phase3_tests.php                # Phase 3: Jobs, Webhooks, RBAC (36 tests)
php tests/test_phase4_features.php            # Phase 4: Production features (14 tests)
php tests/test_phase5_agent_integration.php   # Phase 5: Agent integration (33 tests)
# Additional specialized tests
php tests/test_admin_auth.php                 # Admin authentication tests
php tests/test_rbac_integration.php           # RBAC integration tests
```

### Static Analysis

```bash
# Install dependencies
composer install --dev

# Run PHPStan static analysis
composer run analyze

# Or use vendor bin directly
./vendor/bin/phpstan analyse
```

### Frontend Linting

```bash
# Install Node.js dependencies
npm install

# Run ESLint
npm run lint
```

### Production Smoke Tests

Before deploying to production, run the comprehensive smoke test suite:

```bash
# Run all smoke tests (37 checks + 183 unit tests)
bash scripts/smoke_test.sh
```

The smoke test verifies:
- ✅ File structure and scripts
- ✅ Documentation completeness
- ✅ Code quality (syntax, static analysis)
- ✅ Database migrations
- ✅ Feature implementations
- ✅ Configuration validity
- ✅ All 183 unit tests

**Exit code 0** means production-ready!

### Load Testing

Validate system capacity before production deployment:

```bash
# Install K6
# On macOS: brew install k6
# On Ubuntu: snap install k6

# Run load tests
k6 run tests/load/chat_api.js
```

See `tests/load/README.md` for detailed load testing documentation and scenarios.

### CI/CD Pipeline

All tests run automatically on every PR via GitHub Actions:
- PHP linting and syntax validation
- PHPStan static analysis (level 5)
- ESLint JavaScript validation
- All 183 unit tests (Phases 1-5)
- Automated build and deployment

See `.github/workflows/cicd.yml` for details.

## 🤝 Contributing

We welcome contributions! Here's how to get started:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes and add tests
4. Run the test suite to ensure everything passes
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to your branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate

# Install PHP dependencies
composer install --dev

# Install Node.js dependencies for linting
npm install

# Copy environment file and configure
cp .env.example .env
# Edit .env with your settings

# Start with Docker
docker-compose up -d

# Or run locally with PHP built-in server
php -S localhost:8080
```

### Running Tests & Quality Checks

```bash
# Run all test suites
php tests/run_tests.php
php tests/run_phase2_tests.php
php tests/run_phase3_tests.php
php tests/test_phase4_features.php
php tests/test_phase5_agent_integration.php

# Run static analysis
composer run analyze

# Run linting
npm run lint

# Run smoke tests
bash scripts/smoke_test.sh
```

## 📚 Documentation

> **📑 Full Documentation Index**: See [docs/README.md](docs/README.md) for a complete, categorized list of all documentation.

### Implementation & Feature Documentation

For a comprehensive overview of the complete implementation across all phases:
- 📋 [Implementation Report](docs/IMPLEMENTATION_REPORT.md) - Complete implementation details, metrics, and production readiness assessment
- 📝 [Implementation Plan](docs/IMPLEMENTATION_PLAN.md) - Detailed phase-by-phase implementation plan with status updates
- 🔐 [Phase 1: Database & Agent Model](docs/PHASE1_DB_AGENT.md) - Agent management and Admin API
- 🎨 [Phase 2: Admin UI](docs/PHASE2_ADMIN_UI.md) - Visual interface, prompts, and vector stores
- 🔧 [Phase 3: Workers & Webhooks](docs/PHASE3_WORKERS_WEBHOOKS.md) - Background jobs, webhooks, and RBAC

### Operational Documentation

Production operations guides in `docs/ops/`:
- 🔧 [Backup & Restore](docs/ops/backup_restore.md) - Database backup, rotation, and disaster recovery
- 📊 [Monitoring](docs/ops/monitoring/) - Prometheus metrics, alerts, and health checks
- 📝 [Logging](docs/ops/logs.md) - Structured logging, aggregation (ELK, CloudWatch, LogDNA)
- 🔐 [Secrets Management](docs/ops/secrets_management.md) - Token rotation, key management, vault integration
- 🛡️ [Security](docs/ops/nginx-production.conf) - Production Nginx config with HTTPS and security headers
- 🚨 [Incident Response](docs/ops/incident_runbook.md) - Runbook for common production issues
- 🚀 [Production Deployment](docs/ops/production-deploy.md) - Step-by-step production deployment guide

### API & Customization Guides

- 📖 [API Reference](docs/api.md) - Complete endpoint documentation
- 🎨 [Customization Guide](docs/customization-guide.md) - Styling, configuration, and extension
- 🚀 [Deployment Guide](docs/deployment.md) - Docker, cloud deployment, and scaling
- 🤖 [Agent Creation Guide](docs/GUIA_CRIACAO_AGENTES.md) - 🇧🇷 Guia completo de criação e publicação de agentes (Portuguese)

## 📊 Observability & Monitoring

The platform includes a comprehensive observability framework with structured logging, distributed tracing, and metrics collection for production-grade monitoring and troubleshooting.

### Features

- **Structured JSON Logging**: Rich context with trace IDs, tenant/agent info, and request metadata
- **Distributed Tracing**: W3C Trace Context propagation across services and OpenAI API calls
- **Prometheus Metrics**: 30+ metrics for API, OpenAI, agents, jobs, tokens, and system health
- **Pre-configured Dashboards**: Grafana dashboards for service overview and performance
- **Automated Alerting**: 15+ alert rules for errors, latency, queue health, and SLO breaches
- **Log Aggregation**: Loki integration for centralized log search and analysis
- **One-Command Deployment**: Docker Compose stack with Prometheus, Grafana, Loki, and AlertManager

### Quick Start

```bash
# Start observability stack
cd observability/docker
docker-compose up -d

# Access services
# Grafana: http://localhost:3000 (admin/admin)
# Prometheus: http://localhost:9090
# Loki: http://localhost:3100
```

### Metrics Endpoint

The application exposes Prometheus-compatible metrics at `/metrics.php`:

```bash
curl http://localhost/metrics.php
```

**Available Metrics:**
- **API Metrics**: Request rate, error rate, latency (P95/P99)
- **OpenAI Metrics**: API calls, failures, latency by model
- **Agent Metrics**: Requests and performance by agent
- **Token Usage**: Prompt, completion, and total tokens for billing
- **Job Metrics**: Queue depth, processed/failed jobs, job types
- **System Metrics**: Agents, prompts, vector stores, users, database size
- **Worker Metrics**: Health status, last job timestamp

### Structured Logging

All logs are output as JSON with trace IDs and rich context:

```json
{
  "timestamp": "2024-11-06T18:00:00.000Z",
  "level": "INFO",
  "message": "API request completed",
  "trace_id": "a1b2c3d4e5f6g7h8",
  "context": {
    "endpoint": "/chat-unified.php",
    "method": "POST",
    "duration_ms": 1234.56,
    "status_code": 200,
    "tenant_id": "tenant-123",
    "agent_id": "agent-456"
  }
}
```

### Distributed Tracing

Trace IDs are automatically:
- Generated for each request
- Propagated to OpenAI API calls
- Included in all logs and metrics
- Compatible with W3C Trace Context standard

### Alerting

Pre-configured alerts for critical conditions:
- High error rate (>5%)
- OpenAI API failures
- High latency (P95 > 5s)
- Job queue backlog (>100 jobs)
- Worker unhealthy (>5 min inactive)
- Token usage spikes
- Database size warnings

Configure notifications in `observability/docker/alertmanager.yml` for:
- Slack
- Email
- PagerDuty
- Custom webhooks

### Documentation

- 📖 **[Full Observability Guide](docs/OBSERVABILITY.md)** - Architecture, configuration, integration
- 🚀 **[Quick Start Guide](observability/QUICKSTART.md)** - Get up and running in minutes
- 📊 **[Observability Stack](observability/README.md)** - Infrastructure details
- 🚨 **[Alert Rules](observability/alerts/chatbot-alerts.yml)** - Alert definitions and thresholds

### Health Checks

Enhanced health endpoint at `/admin-api.php?action=health`:

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://localhost/admin-api.php?action=health
```

Returns detailed status for:
- Database connectivity
- OpenAI API accessibility
- Worker process health
- Job queue depth
- Failed jobs in last 24 hours

## 📝 License

MIT License - feel free to use this in commercial and personal projects.

## 📞 Support

- 📖 [Documentation](docs/)
- 🐛 [Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- 💬 [Discussions](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)
- 📧 [Email Support](mailto:support@example.com)

---

**⭐ If this project helps you, please give it a star!**

Made with ❤️ by the open source community
