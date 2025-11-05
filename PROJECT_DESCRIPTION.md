# GPT Chatbot Boilerplate - Project Description

## Overview

This is an advanced, production-ready open-source boilerplate for embedding GPT-powered chatbots into any website. The project provides a complete PHP backend and JavaScript widget system with **dual OpenAI API support**, enabling seamless switching between Chat Completions API (stateless) and Responses API (prompt-template aware with tool calling).

## Core Purpose

Enable developers to quickly deploy intelligent chatbot interfaces with:
- Minimal configuration required
- Enterprise-grade features out of the box
- White-label customization capabilities
- Multiple deployment options (Docker, bare-metal, cloud)
- Production-ready security, monitoring, and operations

## Key Technologies

- **Backend**: PHP 8.0+ with object-oriented architecture
- **Frontend**: Vanilla JavaScript (no framework dependencies)
- **Database**: SQLite (default) or MySQL/PostgreSQL support
- **Real-time**: Server-Sent Events (SSE), WebSockets, AJAX fallback
- **Deployment**: Docker, Docker Compose, Nginx/Apache
- **Testing**: PHPUnit-based test suite (183+ tests)
- **Monitoring**: Prometheus metrics, health checks, audit logging

## Architecture Components

### 1. Backend Services

#### `chat-unified.php` - HTTP Gateway
- Single endpoint handling both API types
- SSE streaming negotiation
- CORS support for cross-origin embedding
- Request normalization and validation
- Conversation ID management

#### `ChatHandler` - Core Orchestrator
- Input validation and sanitization
- Rate limiting (IP-based sliding window)
- Conversation history management
- File upload processing
- OpenAI API payload assembly
- Streaming event emission
- Response persistence

#### `OpenAIClient` - Transport Layer
- Streaming wrappers for `/chat/completions` and `/responses`
- Synchronous request handlers
- File upload management
- HTTP error handling and retry logic
- Logging and observability hooks

#### `AgentService` - Agent Management
- CRUD operations for AI agent configurations
- Dynamic configuration overrides
- Default agent selection
- Configuration priority merging (Request → Agent → Config)

#### `OpenAIAdminClient` - Admin API Integration
- Prompt management (create, version, sync)
- Vector store operations
- File management
- Direct OpenAI API integration

#### `JobQueue` - Background Processing
- Asynchronous job execution
- File ingestion processing
- Retry logic with exponential backoff
- Dead letter queue management

#### `WebhookHandler` - Event Processing
- OpenAI webhook signature verification
- Real-time event notifications
- Vector store ingestion status updates

#### `AdminAuth` - Security Layer
- Role-Based Access Control (RBAC)
- API key authentication
- User management
- Permission validation

### 2. Frontend Components

#### `chatbot-enhanced.js` - Widget System
- Floating and inline display modes
- WebSocket → SSE → AJAX transport negotiation
- File upload with preview
- Tool execution visualization
- Message streaming rendering
- Customizable themes and branding
- Event callback hooks

#### Admin UI - Visual Management Interface
- Agent configuration interface
- Prompt creation and versioning
- Vector store file management
- Job monitoring and retry controls
- System health dashboard
- Audit log viewer

### 3. Optional Services

#### `websocket-server.php` - WebSocket Relay
- Ratchet-based persistent connections
- Chat-completions streaming mirror
- Alternative transport for real-time messaging

## Dual API Support

### Chat Completions API Mode
**Use case**: Simple, stateless conversational interfaces

**Features**:
- Traditional message-based chat
- Configurable system prompts
- Temperature and model settings
- Conversation history management
- Streaming responses via SSE

**Configuration**:
```bash
API_TYPE=chat
OPENAI_MODEL=gpt-4o-mini
OPENAI_TEMPERATURE=0.7
```

### Responses API Mode
**Use case**: Advanced workflows with prompt templates, tools, and file search

**Features**:
- Prompt template references (`RESPONSES_PROMPT_ID`)
- Tool calling (file_search, code_interpreter, custom functions)
- Vector store integration for knowledge retrieval
- File attachments in messages
- Tool output submission and resubmission
- Automatic fallback and retry logic

**Configuration**:
```bash
API_TYPE=responses
RESPONSES_MODEL=gpt-4o-mini
RESPONSES_PROMPT_ID=pmpt_abc123
RESPONSES_TOOLS=[{"type":"file_search"}]
RESPONSES_VECTOR_STORE_IDS=vs_123,vs_456
```

## Multi-Phase Architecture

### Phase 1: Database & Agent Model
- SQLite/MySQL database support
- Agent persistence and CRUD operations
- Admin API with token authentication
- Default agent selection
- Configuration override system

### Phase 2: Admin UI & Resource Management
- Visual admin interface (SPA)
- Prompt creation and versioning
- Vector store file upload and management
- Agent testing interface
- Health monitoring dashboard

### Phase 3: Workers, Webhooks & RBAC
- Background job processing worker
- OpenAI webhook integration
- Multi-user RBAC (viewer, admin, super-admin)
- API key management with expiration
- Prometheus metrics endpoint
- Audit logging

### Phase 4: API Completion & Security
- Rate limiting for admin endpoints
- Files API endpoints
- 37 total admin endpoints
- Enhanced error handling
- Static analysis integration (PHPStan)
- Full test coverage

### Phase 5: Agent Integration
- Widget support for agent selection
- Configuration merging priority
- Backward compatibility
- 33 integration tests

### Phase 10: Production Readiness
- CI/CD pipeline (GitHub Actions)
- Automated backup and restore scripts
- Dead letter queue for failed jobs
- Secrets rotation procedures
- Production Nginx configuration
- K6 load testing scripts
- Operational runbooks

## Key Features

### 🔄 Real-Time Communication
- Server-Sent Events (SSE) for streaming
- WebSocket support for persistent connections
- AJAX fallback for restricted environments
- Automatic transport negotiation

### 📎 File Handling
- Multi-file upload support
- Type validation (PDF, DOC, images, text)
- Size limit enforcement
- Base64 encoding for transport
- Visual preview before sending
- OpenAI file API integration

### 🛠️ Tool Execution
- File search with vector stores
- Code interpreter (when enabled)
- Custom function calling
- Tool output visualization
- Automatic tool result submission

### 🔐 Security
- Token-based admin authentication
- Role-Based Access Control
- API key authentication with expiration
- Rate limiting (IP-based)
- Input sanitization
- File type/size validation
- CORS configuration
- Webhook signature verification

### 📊 Observability
- Prometheus metrics (`/metrics.php`)
- Health check endpoints
- Audit logging for admin operations
- Job queue statistics
- Error logging and tracking
- Performance monitoring

### 🎨 Customization
- White-label branding support
- Customizable color schemes
- Floating or inline display modes
- Multiple theme options
- Proactive messaging
- Custom welcome messages
- Event callback hooks

### 🗄️ Data Persistence
- PHP session storage
- File-based JSON storage
- SQLite database (default)
- MySQL/PostgreSQL support
- Configurable history depth
- Conversation isolation

## Configuration Priority

The system uses a three-tier configuration merge strategy:

1. **Request Parameters** (highest priority) - Per-request overrides
2. **Agent Configuration** - Stored agent settings
3. **Config Defaults** (lowest priority) - Environment and config.php values

This allows maximum flexibility:
- Create multiple AI personalities without code changes
- Override prompts, models, tools per agent
- Set default agents for requests
- Request-level configuration overrides

## Storage Architecture

### Conversation Storage
- **Session-based**: PHP sessions (development)
- **File-based**: JSON files in configured directory
- Configurable history depth (`max_messages`)
- Automatic trimming on persistence

### Database Storage
- **Agents**: AI personality configurations
- **Prompts**: Versioned prompt templates
- **Vector Stores**: Knowledge base metadata
- **Jobs**: Background task queue
- **Users**: Admin user accounts
- **API Keys**: Authentication tokens
- **Audit Logs**: Operation history

## Deployment Options

### Docker (Recommended)
```bash
docker-compose up -d
```
- Automated environment setup
- Apache with SSE headers enabled
- PHP configured for streaming
- Optional worker service
- Volume mounts for persistence

### Bare Metal
- Apache or Nginx web server
- PHP 8.0+ with required extensions
- Manual `.env` configuration
- Composer dependency installation
- Optional systemd worker service

### Cloud Platforms
- AWS (EC2, ECS, Lightsail)
- Google Cloud Platform
- DigitalOcean Droplets
- Heroku
- Azure App Service

## Testing Infrastructure

### Unit Tests (183+ tests)
- Phase 1: Database & Agents (28 tests)
- Phase 2: Prompts & Vector Stores (44 tests)
- Phase 3: Jobs, Webhooks, RBAC (36 tests)
- Phase 4: Production features (14 tests)
- Phase 5: Agent integration (33 tests)
- Additional: Auth, RBAC integration tests

### Static Analysis
- PHPStan (level 5)
- ESLint for JavaScript
- Automated via CI/CD

### Load Testing
- K6 scripts for capacity validation
- API endpoint stress testing
- Concurrent user simulation

### Smoke Testing
- 37 production readiness checks
- File structure validation
- Documentation completeness
- Configuration validity
- All unit tests execution

## API Endpoints

### Chat Endpoint
- **POST** `/chat-unified.php` - Unified chat interface
  - Supports both `api_type=chat` and `api_type=responses`
  - SSE streaming or JSON responses
  - File upload support
  - Agent selection via `agent_id`

### Admin API (37 endpoints)
- **Agents**: create, read, update, delete, list, set default
- **Prompts**: create, version, sync, list, delete
- **Vector Stores**: create, list, delete, upload files, list files, delete files
- **Jobs**: list, stats, retry, cancel, dead letter queue operations
- **Users**: create, list, update, delete, change password
- **API Keys**: generate, list, revoke
- **Files**: list, upload, delete
- **System**: health, metrics

### Webhooks
- **POST** `/webhooks/openai.php` - OpenAI event receiver
  - Vector store events
  - File ingestion status
  - Signature verification

### Metrics
- **GET** `/metrics.php` - Prometheus-compatible metrics
  - Job queue depth
  - System resource counts
  - Worker health status
  - API request metrics

## Integration Example

### Basic JavaScript Integration
```javascript
ChatBot.init({
    mode: 'floating',
    apiType: 'chat',
    apiEndpoint: '/chat-unified.php',
    title: 'Support Chat',
    assistant: {
        name: 'Assistant',
        welcomeMessage: 'How can I help you today?'
    }
});
```

### Advanced Agent-Based Integration
```javascript
ChatBot.init({
    mode: 'inline',
    apiType: 'responses',
    apiEndpoint: '/chat-unified.php',
    agentId: 'agent-uuid-here',
    enableFileUpload: true,
    responsesConfig: {
        defaultTools: [{ type: 'file_search' }]
    }
});
```

## Use Cases

### Customer Support
- Agent configured with support knowledge base
- File search enabled for policy documents
- Vector store with FAQs and documentation
- Proactive greeting messages

### Document Analysis
- Responses API with file upload
- Code interpreter for data analysis
- Multi-file conversation context
- Tool visualization for transparency

### Multi-Tenant Platforms
- Agent per customer/department
- Dynamic configuration without code changes
- RBAC for admin access separation
- Per-agent analytics and monitoring

### Educational Applications
- Subject-specific agents (math, science, history)
- Customized prompts per topic
- File upload for homework help
- Progress tracking via audit logs

## Operational Features

### Backup & Restore
- Automated database backup script
- 7-day rotation policy
- Disaster recovery procedures
- Point-in-time restore capability

### Monitoring
- Prometheus metrics scraping
- Alert rules for common issues
- Health check endpoints
- Log aggregation support (ELK, CloudWatch)

### Secrets Management
- Admin token rotation procedures
- API key expiration and renewal
- Environment variable documentation
- Vault integration guidelines

### High Availability
- Stateless design for horizontal scaling
- Shared database backend
- Load balancer compatibility
- Session storage options

## Development Workflow

### Local Development
1. Clone repository
2. Copy `.env.example` to `.env`
3. Configure OpenAI API key
4. Run `docker-compose up -d`
5. Access at `http://localhost:8080`

### Testing Changes
1. Make code modifications
2. Run unit tests: `php tests/run_tests.php`
3. Run static analysis: `composer run analyze`
4. Run linting: `npm run lint`
5. Execute smoke tests: `bash scripts/smoke_test.sh`

### Production Deployment
1. Configure production `.env`
2. Build Docker image or prepare server
3. Run database migrations
4. Configure Nginx/Apache with SSL
5. Set up monitoring and alerts
6. Start background worker
7. Configure OpenAI webhooks
8. Run load tests to validate capacity
9. Execute production smoke tests

## File Structure Overview

```
├── chat-unified.php           # Main chat endpoint
├── admin-api.php              # Admin REST API
├── metrics.php                # Prometheus metrics
├── chatbot-enhanced.js        # Frontend widget (2,176 lines)
├── includes/
│   ├── ChatHandler.php        # Core orchestration (1,410 lines)
│   ├── OpenAIClient.php       # API transport (300 lines)
│   ├── AgentService.php       # Agent CRUD (394 lines)
│   ├── OpenAIAdminClient.php  # Admin APIs (437 lines)
│   ├── PromptService.php      # Prompt management (341 lines)
│   ├── VectorStoreService.php # Vector stores (486 lines)
│   ├── JobQueue.php           # Background jobs (663 lines)
│   ├── WebhookHandler.php     # Event processing (156 lines)
│   ├── AdminAuth.php          # RBAC & auth (345 lines)
│   └── DB.php                 # Database layer (265 lines)
├── public/admin/              # Admin UI (SPA)
├── scripts/                   # Operational scripts
├── db/migrations/             # Database migrations
├── tests/                     # Test suites (183 tests)
├── webhooks/                  # Webhook handlers
├── docs/                      # Comprehensive documentation
└── config.php                 # Configuration loader
```

## Documentation Structure

- **README.md** - Quick start and feature overview
- **SPEC.md** - Technical specification and architecture
- **AGENTS.md** - AI agent integration guide (this file's source)
- **docs/api.md** - Complete API reference
- **docs/deployment.md** - Deployment and scaling guide
- **docs/customization-guide.md** - Theming and extension guide
- **docs/PHASE*.md** - Feature phase documentation
- **docs/ops/** - Operational runbooks and procedures

## Performance Characteristics

### Scalability
- Stateless architecture supports horizontal scaling
- Shared database for multi-instance deployments
- Load balancer compatible
- Configurable rate limiting

### Resource Usage
- Minimal memory footprint (PHP 8.0+)
- Efficient SSE streaming
- Background worker for long-running tasks
- Optional caching layers

### Throughput
- Configurable rate limits (300 req/min default for admin)
- Client-side rate limiting for chat
- K6 load testing validated
- Production-ready under load

## Security Model

### Authentication Layers
1. **Admin Token** - Global admin access (legacy)
2. **API Keys** - Per-user authentication tokens
3. **RBAC** - Role-based permissions (viewer, admin, super-admin)

### Authorization Checks
- Permission validation on all admin endpoints
- Resource ownership verification
- Audit logging for accountability

### Data Protection
- Input sanitization and validation
- File type/size restrictions
- SQL injection prevention (prepared statements)
- XSS protection
- CORS configuration
- HTTPS enforcement (production)

## Extensibility Points

### Adding Custom Tools
1. Define tool schema in agent configuration
2. Implement handler in `ChatHandler::executeServerSideTools()`
3. Return structured JSON response
4. Tool appears in Responses API workflows

### Creating Custom Agents
1. Use Admin UI or API to create agent
2. Configure prompts, models, tools
3. Set vector store associations
4. Test via Admin UI
5. Deploy with widget using `agentId`

### Implementing New Transports
1. Add connection type to widget
2. Implement streaming handler
3. Call `handleStreamChunk()` with events
4. Update fallback chain

### Custom Storage Backends
1. Extend `ChatHandler` storage methods
2. Implement load/save conversation logic
3. Update `config.php` with new type
4. Add migration path if needed

## Common Patterns

### Configuration Override Flow
```
Environment Variables (.env)
    ↓
config.php Defaults
    ↓
Agent Configuration (if agent_id provided)
    ↓
Request Parameters
    ↓
Final OpenAI API Payload
```

### Streaming Event Flow
```
User Message → chat-unified.php
    ↓
ChatHandler Validation
    ↓
OpenAIClient Stream Request
    ↓
SSE Events: start → chunk → chunk → ... → done
    ↓
Widget Rendering
    ↓
Conversation Persistence
```

### Tool Execution Flow
```
Responses API with Tools Enabled
    ↓
OpenAI Returns required_action
    ↓
ChatHandler Executes Server-Side Tool
    ↓
Tool Output Submission to OpenAI
    ↓
Streaming Continues with Tool Results
    ↓
Final Response Rendered
```

## Migration & Compatibility

### From v2.0 (Assistants API)
- Replace `API_TYPE=assistants` with `API_TYPE=responses`
- Migrate assistant instructions to prompts
- Remove `AssistantManager` and `ThreadManager` references
- Update environment variables
- Test both chat and responses modes

### Backward Compatibility
- Existing chat-completions code works unchanged
- Agents are optional (defaults apply if not specified)
- Configuration priority maintains expected behavior
- API endpoints remain stable

## Support & Resources

- **Documentation**: Comprehensive in `docs/` directory
- **Examples**: Integration examples in README and docs
- **Tests**: Reference implementations in `tests/`
- **Issues**: GitHub issue tracker for bugs and features
- **Community**: GitHub discussions for questions

## License

MIT License - Open source and free for commercial and personal use.

---

**Summary**: This is a production-grade, highly configurable GPT chatbot platform that bridges OpenAI's APIs with web applications through a robust PHP backend and flexible JavaScript widget, offering enterprise features like agent management, RBAC, background processing, and comprehensive monitoring out of the box.
