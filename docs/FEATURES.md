# Features Overview

This document provides a comprehensive overview of all features available in the GPT Chatbot Boilerplate.

## Table of Contents

- [Core Features](#core-features)
- [API Support](#api-support)
- [Agent Management](#agent-management)
- [Admin Interface](#admin-interface)
- [Background Processing](#background-processing)
- [Multi-Tenancy](#multi-tenancy)
- [Security Features](#security-features)
- [Observability](#observability)
- [Integration Features](#integration-features)
- [Compliance Features](#compliance-features)

## Core Features

### Dual API Support

The platform supports both OpenAI Chat Completions and Responses APIs:

- **Chat Completions API**: Traditional stateless chat interface
- **Responses API**: Prompt-template aware streaming with tool calling and file attachments
- **One toggle** via configuration to switch between APIs
- **Automatic fallback** and robust error handling

### File Upload Support

- Multiple file types (PDF, DOC, images, text files)
- File size validation and type restrictions
- Visual file preview before sending
- Integration with OpenAI file processing

### Real-Time Streaming

- Server-Sent Events (SSE) for real-time responses
- Optional WebSocket support for bidirectional communication
- AJAX fallback for compatibility
- Streaming tool call execution visualization

### Enhanced UI/UX

- API type indicators showing the active workflow
- Tool execution visualization for Responses API events
- File attachment display in messages
- Improved responsive design
- Floating and inline chat modes

## API Support

### Chat Completions API

**Features:**
- Simple conversational interface
- Stateless interactions
- Lower latency and costs
- Session-based conversation history
- Configurable temperature and max tokens

**Use Cases:**
- Customer support chatbots
- FAQ assistants
- Simple Q&A interfaces

### Responses API

**Features:**
- Prompt template references (`prompt_id` and versioning)
- Tool calling with function execution
- File attachments and processing
- Vector store integration for file search
- Structured output with JSON schemas (Hybrid Guardrails)

**Use Cases:**
- Document analysis and search
- Complex workflows with tool integration
- Research assistants with citations
- Data extraction and transformation

## Agent Management

### Database-Backed Agents

- **Persistent storage** for AI agent configurations
- **Dynamic configuration** without code changes
- **Multiple agents** for different use cases
- **Default agent** support for backwards compatibility

### Agent Configuration

Each agent can define:
- API type (chat or responses)
- Model selection
- Temperature and token limits
- System messages/instructions
- Prompt IDs and versions
- Tool configurations
- Vector store IDs
- Response format guardrails

### Configuration Priority

Merging strategy:
1. **Request parameters** (highest priority)
2. **Agent configuration** (from database)
3. **config.php defaults** (lowest priority)

See [PHASE1_DB_AGENT.md](PHASE1_DB_AGENT.md) for details.

## Admin Interface

### Visual Management UI

Located at `/public/admin/`, the Admin UI provides:

- **Agent Management**: Full CRUD operations with testing capability
- **Prompt Management**: Create, version, and sync from OpenAI
- **Vector Store Management**: Upload files, monitor ingestion status
- **User Management**: RBAC with three permission levels
- **Health Monitoring**: Real-time system health checks
- **Audit Logging**: Complete audit trail of all operations

### Admin API

Token-protected REST API for programmatic access:
- 37 endpoints across all resources
- Rate limiting (300 req/min default)
- Complete CRUD operations
- Job management and monitoring

See [PHASE2_ADMIN_UI.md](PHASE2_ADMIN_UI.md) for details.

## Background Processing

### Job Queue System

- **Asynchronous processing** for long-running operations
- **Exponential backoff** and retry logic
- **Job types**: File ingestion, vector store updates, cleanup tasks
- **Dead Letter Queue** for failed job management
- **CLI worker** with daemon mode

### Webhook Support

- **Real-time notifications** from OpenAI
- **Signature verification** for security
- **Event types**: `vector_store.*`, `file.*`
- **Automatic job creation** for async operations

See [PHASE3_WORKERS_WEBHOOKS.md](PHASE3_WORKERS_WEBHOOKS.md) for details.

## Multi-Tenancy

### Complete Tenant Isolation

- **Full multi-tenant architecture** with tenant-scoped queries
- **Tenant management** via Admin API
- **Resource isolation** across all services
- **Per-tenant statistics** and usage tracking

### Tenant Features

- Create, update, suspend, and delete tenants
- Tenant-aware services (agents, prompts, vector stores, etc.)
- Admin user mapping to specific tenants
- Super-admin access across all tenants
- Data migration tools for single→multi-tenant conversion

See [MULTI_TENANCY.md](MULTI_TENANCY.md) for details.

## Security Features

### Authentication & Authorization

- **Admin token authentication** for API access
- **API key system** with expiration and revocation
- **Role-Based Access Control (RBAC)**: viewer, admin, super-admin
- **Resource-level authorization** with tenant isolation
- **Per-resource access control** with explicit permission grants

### Security Hardening

- **Rate limiting**: IP-based and tenant-specific
- **File upload validation**: Type, size, content checking
- **Conversation isolation**: Per-session storage
- **Input sanitization**: Protection against injection attacks
- **HTTPS enforcement** in production configurations
- **Security headers**: CSP, HSTS, X-Frame-Options

See [SECURITY_MODEL.md](SECURITY_MODEL.md) for details.

## Observability

### Monitoring & Metrics

- **Prometheus metrics endpoint** (`/metrics.php`)
- **30+ metrics**: API, OpenAI, agents, jobs, tokens, system health
- **Pre-configured Grafana dashboards**
- **Automated alerting** (15+ alert rules)
- **Health checks**: Database, OpenAI API, worker status

### Logging

- **Structured JSON logging** with trace IDs
- **Distributed tracing**: W3C Trace Context propagation
- **Log aggregation**: Loki integration
- **Audit trails**: Complete operation history

### Performance

- **Request/response tracking**
- **Latency percentiles** (P95, P99)
- **Token usage monitoring** for billing
- **Job queue metrics**
- **Worker health monitoring**

See [OBSERVABILITY.md](OBSERVABILITY.md) for details.

## Integration Features

### WhatsApp Integration

- **Multi-channel support** via Z-API
- **Per-agent configuration**
- **Session management** with context preservation
- **Media support**: Images, documents, etc.
- **Message chunking** for long responses
- **Idempotency** for duplicate prevention
- **Opt-out support**: STOP/START commands
- **Webhook security** with signature verification

See [WHATSAPP_INTEGRATION.md](WHATSAPP_INTEGRATION.md) for details.

### LeadSense (AI Lead Detection & CRM)

**Commercial Opportunity Detection:**
- **Intent Detection**: Automatically identifies buying signals (pricing, trials, integrations)
- **Entity Extraction**: Captures contact info, roles, company data from conversations
- **Lead Scoring**: Rules-based scoring (0-100) with configurable weights and thresholds
- **Qualification**: Automatic lead qualification based on score and intent level

**CRM Capabilities:**
- **Visual Pipeline Management**: Kanban boards with drag-and-drop functionality
- **Multiple Pipelines**: Different workflows (Sales, Support, Onboarding)
- **Stage Management**: Customizable stages with colors and status indicators (won/lost/closed)
- **Lead Attributes**: Owner assignment, deal tracking (value, probability, close date), tags
- **Timeline Events**: Full history of stage changes, updates, and notes

**Notifications & Integration:**
- **Slack Notifications**: Real-time alerts for qualified leads with PII redaction
- **Webhook Support**: Send leads to external CRMs (HubSpot, Salesforce, etc.)
- **HMAC Signatures**: Secure webhook payload verification
- **Retry Logic**: Exponential backoff for failed notifications

**Admin Interface:**
- **Kanban Board UI**: Visual lead management at `/public/admin/leadsense-crm.html`
- **Pipeline Builder**: Create and manage pipelines with stages
- **Lead Cards**: Display name, company, score, tags, owner with inline editing
- **Search & Filters**: Find leads by name, company, score, or stage
- **Bulk Operations**: Move multiple leads, assign owners, update statuses

**Security & Privacy:**
- **PII Redaction**: Mask emails/phones in notifications and logs
- **RBAC**: Role-based access to lead data and CRM operations
- **Tenant Isolation**: Complete data separation in multi-tenant deployments
- **Audit Trails**: Track all lead events and CRM operations
- **GDPR/CCPA Ready**: Data export, deletion, and consent management

**Configuration:**
- **Per-Agent Settings**: Override global config per chatbot agent
- **Configurable Thresholds**: Intent confidence, lead score, and qualification
- **Scoring Weights**: Customize point values for different signals
- **Rate Limiting**: Debounce window and daily notification limits

**Use Cases:**
- Sales teams identifying prospects from support conversations
- Inbound marketing lead capture and qualification
- Enterprise sales pipeline management
- Support to sales handoff automation

See [LEADSENSE_QUICKSTART.md](LEADSENSE_QUICKSTART.md) for quick setup, [leadsense-overview.md](leadsense-overview.md) for architecture, [leadsense-api.md](leadsense-api.md) for API reference, and [LEADSENSE_CRM.md](LEADSENSE_CRM.md) for CRM details.

### Prompt Builder

- **AI-powered prompt generation**
- **Template library** with best practices
- **Guardrail integration** for structured outputs
- **Version control** for prompts

See [prompt_builder_overview.md](prompt_builder_overview.md) for details.

## Compliance Features

### GDPR/LGPD Compliance

- **Data export API**: Export user data in portable formats
- **Data deletion API**: Complete data removal
- **Consent management**: Track and manage user consent
- **PII redaction**: Automatic sensitive data handling
- **Audit trails**: Complete compliance tracking
- **Retention policies**: Automated cleanup with legal hold support

### Privacy Features

- **AES-256-GCM encryption** at rest
- **Content hashing** for integrity verification
- **Configurable retention periods**
- **Data Processing Agreements** (DPA templates)
- **Privacy controls** per feature

See [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md) for details.

## Advanced Features

### Hybrid Guardrails

- **Response format enforcement** using JSON schemas
- **Three format types**: Text, JSON Object, JSON Schema
- **Configuration storage** in agent records
- **Practical examples**: Story generation, data extraction

See [HYBRID_GUARDRAILS.md](HYBRID_GUARDRAILS.md) for details.

### Billing & Metering

- **Usage tracking**: Tokens, requests, storage
- **Quota management**: Per-tenant limits
- **Cost calculation**: Based on OpenAI pricing
- **Billing reports**: Usage aggregation and export
- **Overage alerts**: Automated notifications

See [BILLING_METERING.md](BILLING_METERING.md) for details.

### Whitelabel Publishing

- **Public API** for published agents
- **Standalone chatbot** deployment
- **Custom branding** and styling
- **Rate limiting** per published agent
- **Usage analytics** and monitoring

See [WHITELABEL_PUBLISHING.md](WHITELABEL_PUBLISHING.md) for details.

## Production Features

### Backup & Disaster Recovery

- **Automated backup system** for all persistent data
- **Multi-tier backup strategy**: hourly, daily, weekly, monthly
- **Off-site backup support**: rsync, AWS S3, Azure, GCS
- **Disaster recovery runbook** with RPO/RTO definitions
- **Automated monitoring** with alerts
- **Quarterly DR drills**

See [ops/backup_restore.md](ops/backup_restore.md) and [ops/disaster_recovery.md](ops/disaster_recovery.md) for details.

### CI/CD Pipeline

- **Automated testing**: 183 tests across all phases
- **Static analysis**: PHPStan level 5
- **Linting**: ESLint for JavaScript
- **GitHub Actions** workflow
- **Automated deployment** support

### Load Testing

- **K6 scripts** for capacity testing
- **Performance validation** before production
- **Scenario-based testing**: normal load, spike, stress

See [deployment.md](deployment.md) for details.

## Feature Comparison by Edition

| Feature | Basic | Advanced | Enterprise |
|---------|-------|----------|------------|
| Chat Completions API | ✅ | ✅ | ✅ |
| Responses API | ✅ | ✅ | ✅ |
| File Uploads | ✅ | ✅ | ✅ |
| Agent Management | ✅ | ✅ | ✅ |
| Admin UI | ✅ | ✅ | ✅ |
| Background Jobs | ❌ | ✅ | ✅ |
| Webhooks | ❌ | ✅ | ✅ |
| RBAC | ❌ | ✅ | ✅ |
| Multi-Tenancy | ❌ | ❌ | ✅ |
| WhatsApp Integration | ❌ | ❌ | ✅ |
| LeadSense | ❌ | ❌ | ✅ |
| Compliance Features | ❌ | ❌ | ✅ |
| Whitelabel Publishing | ❌ | ❌ | ✅ |

## Getting Started with Features

To enable specific features, see:

- **Quick Start**: [QUICK_START.md](QUICK_START.md)
- **Configuration**: [customization-guide.md](customization-guide.md)
- **Deployment**: [deployment.md](deployment.md)
- **API Reference**: [api.md](api.md)

## Feature Requests

Have an idea for a new feature? Open an issue on [GitHub](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues) with the `feature-request` label.
