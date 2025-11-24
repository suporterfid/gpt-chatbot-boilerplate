# API Documentation - GPT Chatbot Boilerplate

**Version:** 1.0.1
**Last Updated:** January 2025

Welcome to the comprehensive API documentation for the GPT Chatbot Boilerplate platform - a production-ready, enterprise-grade chatbot solution with multi-tenancy, whitelabel publishing, and advanced AI capabilities.

## Documentation Overview

This platform provides three main APIs for different audiences:

### 🔧 [Admin API Documentation](admin-api.md)

Complete backend administration API for managing the platform.

**Audience:** System administrators, backend developers, DevOps engineers

**Key Features:**
- 190+ administrative endpoints
- User and authentication management
- Agent configuration and management
- Whitelabel publishing and branding
- Channel integrations (WhatsApp, etc.)
- Multi-tenancy management
- Usage tracking and billing
- Prompt and vector store management
- LeadSense CRM integration
- WordPress Blog automation (24 endpoints for AI-powered content generation)
- AI-powered prompt builder
- Audit logging and compliance (GDPR/CCPA)
- Job queue and webhook management
- Specialized agent types and discovery

**[View Complete Admin API Documentation →](admin-api.md)**

---

### 🌐 [Public API Documentation](public-api.md)

Public-facing APIs for chat interactions and agent discovery.

**Audience:** Frontend developers, client application developers, integration partners

**Key Features:**
- Chat API with streaming support (SSE/WebSocket)
- Public agent discovery and listing
- Whitelabel agent access with HMAC authentication
- File upload and attachment support
- Real-time WebSocket communication
- Multi-turn conversation management
- Structured output and tool calling
- Rate limiting and error handling

**[View Complete Public API Documentation →](public-api.md)**

---

### 📱 [JavaScript Client API Documentation](client-api.md)

JavaScript widget library for embedding chatbots in websites.

**Audience:** Frontend developers, web designers, website owners

**Key Features:**
- Easy drop-in widget (inline or floating)
- Rich customization and theming
- File upload support
- Markdown rendering
- Accessibility features (WCAG 2.1 AA)
- Responsive mobile-friendly design
- Connection resilience and auto-reconnection
- Proactive messaging
- TypeScript support
- Event callbacks and hooks

**[View Complete JavaScript Client API Documentation →](client-api.md)**

---

## Quick Start Guide

### For End Users (Website Integration)

Add the chatbot widget to your website in 3 simple steps:

```html
<!-- 1. Include CSS -->
<link rel="stylesheet" href="https://your-domain.com/chatbot.css">

<!-- 2. Include JavaScript -->
<script src="https://your-domain.com/chatbot-enhanced.js"></script>

<!-- 3. Initialize -->
<script>
  ChatBot.init({
    mode: 'floating',
    apiEndpoint: '/chat-unified.php',
    ui: {
      title: 'Chat Assistant',
      theme: {
        primaryColor: '#007bff'
      }
    }
  });
</script>
```

**[Learn more in the JavaScript Client API docs →](client-api.md#quick-start)**

---

### For Developers (API Integration)

Send a chat message via the REST API:

```bash
curl -X POST "https://your-domain.com/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Hello, how can you help me?",
    "conversation_id": "conv_abc123",
    "agent_id": "agent_xyz789"
  }'
```

**[Learn more in the Public API docs →](public-api.md#chat-api)**

---

### For Administrators (Platform Management)

Create a new agent via the Admin API:

```bash
curl -X POST "https://your-domain.com/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Bot",
    "slug": "customer-support",
    "api_type": "responses",
    "model": "gpt-4",
    "temperature": 0.7
  }'
```

**[Learn more in the Admin API docs →](admin-api.md#agent-management)**

---

## Architecture Overview

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Client Applications                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Web Widget │  │ Mobile Apps  │  │  Custom Apps │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
└─────────┼──────────────────┼──────────────────┼─────────────┘
          │                  │                  │
          └──────────────────┼──────────────────┘
                             │
          ┌──────────────────▼──────────────────┐
          │         Public API Layer            │
          │  ┌────────────┐  ┌────────────┐    │
          │  │  Chat API  │  │ Agent API  │    │
          │  └─────┬──────┘  └─────┬──────┘    │
          └────────┼────────────────┼───────────┘
                   │                │
          ┌────────▼────────────────▼───────────┐
          │      Application Core               │
          │  ┌───────────────────────────┐      │
          │  │   Agent Service           │      │
          │  │   Connection Manager      │      │
          │  │   Session Manager         │      │
          │  │   File Upload Handler     │      │
          │  └───────────┬───────────────┘      │
          └──────────────┼──────────────────────┘
                         │
          ┌──────────────▼──────────────────────┐
          │       Backend Services              │
          │  ┌──────────┐  ┌──────────┐        │
          │  │ OpenAI   │  │ Database │        │
          │  │ API      │  │ (SQLite) │        │
          │  └──────────┘  └──────────┘        │
          └─────────────────────────────────────┘
                         │
          ┌──────────────▼──────────────────────┐
          │         Admin API Layer             │
          │  ┌────────────────────────────┐     │
          │  │  Management & Analytics    │     │
          │  │  User & Agent CRUD         │     │
          │  │  Billing & Usage Tracking  │     │
          │  │  Audit & Compliance        │     │
          │  │  WordPress Blog Automation │     │
          │  │  LeadSense CRM             │     │
          │  │  Multi-Tenancy             │     │
          │  └────────────────────────────┘     │
          └─────────────────────────────────────┘
```

### API Types

The platform supports two OpenAI API types:

1. **Chat Completions API** (`api_type: 'chat'`)
   - Traditional chat-based interactions
   - Message history management
   - System prompts
   - Function/tool calling

2. **Responses API** (`api_type: 'responses'`)
   - Saved prompts with versioning
   - Built-in vector store integration
   - File search capabilities
   - Structured outputs
   - Enhanced tool support

**Default:** Responses API (recommended for most use cases)

---

## Key Features

### 🤖 Agent Management

Create and manage multiple AI agents with different configurations:

- **Custom Models:** GPT-4, GPT-4 Turbo, GPT-3.5 Turbo
- **Personality & Behavior:** Temperature, top_p, system messages
- **Knowledge Base:** Vector stores for document search
- **Tool Integration:** File search, custom functions
- **Versioning:** Prompt versions and rollback
- **Slugs:** URL-friendly agent identifiers

**[Learn more →](admin-api.md#agent-management)**

---

### 🎨 Whitelabel Publishing

Deploy branded chat interfaces on custom domains:

- **Custom Domains:** host agents on your own domain
- **Vanity Paths:** SEO-friendly URLs (`/a/support`)
- **Full Branding:** Logo, colors, welcome messages
- **HMAC Authentication:** Secure token-based access
- **CORS Configuration:** Whitelist specific origins
- **Embed Options:** Iframe or direct integration

**[Learn more →](admin-api.md#whitelabel-publishing)**

---

### 💬 Channel Integrations

Extend your chatbot to external messaging platforms:

- **WhatsApp:** Via Z-API integration
- **Business Phone Numbers:** Official WhatsApp Business API
- **Message Chunking:** Automatic long message splitting
- **Media Support:** Images, documents, audio
- **Session Management:** Track active conversations
- **Webhook Events:** Real-time message notifications

**[Learn more →](admin-api.md#channel-management)**

---

### 🏢 Multi-Tenancy

Host multiple organizations on a single platform:

- **Complete Isolation:** Data segregation at database level
- **Tenant Management:** CRUD operations, suspension, activation
- **Quotas & Limits:** Per-tenant resource controls
- **Usage Tracking:** Message, token, and cost monitoring
- **Billing Integration:** Subscriptions, invoices, payment methods
- **Custom Settings:** Per-tenant feature flags

**[Learn more →](admin-api.md#multi-tenancy)**

---

### 📊 LeadSense CRM

AI-powered lead detection and qualification:

- **Intent Detection:** Identify purchase intent from conversations
- **Lead Scoring:** 0-100 score based on multiple factors
- **Contact Extraction:** Email, phone, company information
- **Interest Tracking:** Detected products and features
- **Pipeline Management:** Sales stages and automation
- **Notes & History:** Full conversation context
- **Webhook Notifications:** Real-time lead alerts

**[Learn more →](admin-api.md#leadsense-crm)**

---

### 🔒 Compliance & Security

Enterprise-grade security and compliance features:

- **GDPR/CCPA Compliant:** Consent management and data deletion
- **Audit Logging:** Full conversation and event tracking
- **PII Redaction:** Automatic sensitive data masking
- **Data Encryption:** At-rest encryption for audit logs
- **Retention Policies:** Configurable data lifecycle
- **Access Control:** Role-based permissions (RBAC)
- **Rate Limiting:** Multi-level request throttling

**[Learn more →](admin-api.md#consent-management)**

---

### 📝 Prompt Builder

AI-assisted prompt creation with safety guardrails:

- **AI Generation:** Describe intent, get optimized prompt
- **Guardrails:** Prevent hallucinations, scope restrictions
- **Safety Controls:** No medical/legal/financial advice
- **Template Library:** Pre-built industry prompts
- **Version Control:** Save and activate prompt versions
- **Testing:** Test prompts before deployment

**[Learn more →](admin-api.md#prompt-builder)**

---

### 📈 Usage Tracking & Analytics

Comprehensive monitoring and analytics:

- **Real-time Metrics:** Messages, tokens, costs
- **Time-series Data:** Usage trends and forecasting
- **Per-tenant Stats:** Isolated usage tracking
- **Per-agent Analytics:** Performance by agent
- **Quota Enforcement:** Automatic limit enforcement
- **Export Options:** JSON, CSV, JSONL formats
- **Prometheus Metrics:** Integration with monitoring tools

**[Learn more →](admin-api.md#usage-tracking--quotas)**

---

### 🔄 Background Jobs & Webhooks

Asynchronous processing and event-driven integrations:

- **Job Queue:** Reliable background task processing
- **Dead Letter Queue:** Failed job management
- **Retry Logic:** Exponential backoff
- **Webhook Delivery:** Event notifications to external systems
- **Delivery Logs:** Track webhook success/failure
- **Signature Verification:** HMAC-signed payloads

**[Learn more →](admin-api.md#job-queue-management)**

---

## API Comparison

| Feature | Public API | Admin API | JavaScript Client |
|---------|-----------|-----------|-------------------|
| **Chat Messaging** | ✅ | ❌ | ✅ |
| **Agent Discovery** | ✅ | ✅ | ✅ |
| **Agent Management** | ❌ | ✅ | ❌ |
| **User Management** | ❌ | ✅ | ❌ |
| **File Upload** | ✅ | ✅ | ✅ |
| **Streaming (SSE)** | ✅ | ❌ | ✅ |
| **WebSocket** | ✅ | ❌ | ✅ |
| **Authentication** | Optional | Required | Optional |
| **Rate Limiting** | 60/min | 300/min | 60/min |
| **CORS** | Enabled | Restricted | N/A |

---

## Authentication

### Public API

- **Default:** No authentication required
- **Whitelabel:** HMAC token for signed requests
- **Tenant:** Optional tenant_id header

### Admin API

Three authentication methods:

1. **Session Cookies** (Recommended)
   - Email/password login
   - 24-hour session TTL
   - Secure cookies (HttpOnly, SameSite)

2. **API Keys**
   - Generated per user
   - Bearer token authentication
   - Configurable expiration

3. **Legacy Token** (Deprecated)
   - Single ADMIN_TOKEN
   - Being phased out

### JavaScript Client

- **Inherits:** Uses Public API authentication
- **Configuration:** Pass credentials via options

**[Learn more about authentication →](admin-api.md#authentication)**

---

## Rate Limits

### Public API

| Endpoint | Limit | Window |
|----------|-------|--------|
| Chat API | 60 requests | per minute |
| Agent API | 100 requests | per minute |
| File Upload | 5 uploads | per minute |

### Admin API

| Endpoint | Limit | Window |
|----------|-------|--------|
| Login | 5 attempts | per 5 minutes |
| General | 300 requests | per minute |
| Export | 10 requests | per hour |

### Per-Tenant Limits

Configurable per tenant:
- Message quotas (monthly)
- Token quotas (monthly)
- Concurrent connections
- Storage limits

**[Learn more about rate limiting →](public-api.md#rate-limiting)**

---

## Error Handling

All APIs use consistent error responses:

```json
{
  "error": "Human-readable error message",
  "code": "ERROR_CODE",
  "details": {},
  "timestamp": "2025-01-20T10:30:00Z",
  "request_id": "req_abc123"
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `MISSING_MESSAGE` | 400 | Message parameter required |
| `MESSAGE_TOO_LONG` | 400 | Message exceeds max length |
| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |
| `QUOTA_EXCEEDED` | 429 | Usage quota exceeded |
| `UNAUTHORIZED` | 401 | Invalid/missing credentials |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `TENANT_SUSPENDED` | 403 | Tenant account suspended |
| `SERVICE_UNAVAILABLE` | 503 | OpenAI API unavailable |

**[View complete error codes →](public-api.md#error-handling)**

---

## Migration from v0.x

If you're upgrading from a previous version, please note these breaking changes:

### API Changes

1. **Unified Endpoint:** `/chat.php` renamed to `/chat-unified.php`
2. **Agent System:** New agent-based configuration (replaces global config)
3. **API Types:** Explicit `api_type` parameter (default: "responses")
4. **Whitelabel:** New HMAC authentication for published agents

### Configuration Changes

1. **Environment Variables:** Many new variables for features
2. **Database Schema:** 46 migrations (run `php migrate.php`)
3. **File Structure:** New service classes and utilities

### Migration Guide

```bash
# 1. Backup your database
cp data/chatbot.db data/chatbot.db.backup

# 2. Pull latest code
git pull origin main

# 3. Run migrations
php migrate.php

# 4. Update environment variables
# Review .env.example for new variables

# 5. Test agents
# Use Admin API to verify agent configurations

# 6. Update client code
# Replace old endpoint with /chat-unified.php
```

**Need help?** Contact support@your-domain.com

---

## SDKs & Libraries

### Official SDKs

- **JavaScript/TypeScript:** [@your-company/chatbot-widget](https://www.npmjs.com/package/@your-company/chatbot-widget)
- **PHP:** [composer require your-company/chatbot-sdk](https://packagist.org/packages/your-company/chatbot-sdk)
- **Python:** Coming soon
- **Ruby:** Coming soon

### Community Libraries

Check our [GitHub repository](https://github.com/your-company/chatbot-boilerplate) for community-contributed libraries.

---

## Examples & Tutorials

### Basic Examples

- [Simple Chat Widget](client-api.md#quick-start)
- [Streaming Messages](public-api.md#streaming-chat-sse)
- [File Upload](public-api.md#file-upload)
- [Whitelabel Integration](public-api.md#whitelabel-agent-access)

### Advanced Examples

- [Multi-Agent Switching](client-api.md#multi-agent-switching)
- [Custom Message Rendering](client-api.md#custom-message-rendering)
- [Analytics Integration](client-api.md#analytics-integration)
- [WebSocket Chat](public-api.md#websocket-chat)

### Integration Guides

- WordPress Plugin (coming soon)
- Shopify App (coming soon)
- React Component Library (coming soon)
- Vue.js Plugin (coming soon)

---

## Performance & Scalability

### Benchmarks

- **Response Time:** < 1.5s average (with GPT-4)
- **Throughput:** 1000+ requests/minute per instance
- **Concurrent Users:** 500+ simultaneous connections
- **Memory Usage:** ~100 MB per 1000 active conversations

### Optimization Tips

1. **Use Streaming:** Improve perceived latency
2. **Enable Caching:** Redis for session management
3. **CDN Integration:** Static assets via CDN
4. **Database Indexing:** Ensure proper indexes
5. **Connection Pooling:** Reuse database connections

### Scaling Strategies

- **Horizontal Scaling:** Multiple PHP-FPM instances
- **Load Balancing:** Nginx/HAProxy for distribution
- **Database Replication:** Read replicas for queries
- **Queue Workers:** Separate job processing servers

**[View deployment guide →](deployment.md)**

---

## Security Best Practices

### API Security

1. ✅ **Use HTTPS** for all API requests
2. ✅ **Validate Input** on both client and server
3. ✅ **Rate Limiting** to prevent abuse
4. ✅ **CORS Configuration** to restrict origins
5. ✅ **API Key Rotation** every 90 days
6. ✅ **Monitor Audit Logs** for suspicious activity

### Client Security

1. ✅ **CSP Headers** to prevent XSS
2. ✅ **DOMPurify** for HTML sanitization
3. ✅ **No Inline Scripts** in production
4. ✅ **SRI Hashes** for CDN resources
5. ✅ **Secure Cookies** (HttpOnly, Secure, SameSite)

### Data Security

1. ✅ **Encryption at Rest** for audit logs
2. ✅ **PII Redaction** in logs and exports
3. ✅ **Data Retention** policies
4. ✅ **GDPR Compliance** tools
5. ✅ **Regular Backups** with encryption

**[View security checklist →](../SECURITY.md)**

---

## Monitoring & Observability

### Available Metrics

- **Request Metrics:** Count, latency, error rate
- **Token Metrics:** Input/output tokens, costs
- **Connection Metrics:** Active connections, reconnections
- **Queue Metrics:** Job backlog, processing time
- **Error Metrics:** Error rates by type

### Prometheus Integration

```yaml
# Sample Prometheus config
scrape_configs:
  - job_name: 'chatbot'
    static_configs:
      - targets: ['your-domain.com:80']
    metrics_path: '/admin-api.php?action=metrics'
```

### Logging

- **Structured Logs:** JSON format for parsing
- **Log Levels:** DEBUG, INFO, WARNING, ERROR, CRITICAL
- **Correlation IDs:** Track requests across services
- **Log Aggregation:** Compatible with ELK, Splunk, etc.

**[View metrics endpoint →](admin-api.md#get-metrics)**

---

## Support & Resources

### Documentation

- **[Admin API](admin-api.md)** - Backend management
- **[Public API](public-api.md)** - Client integrations
- **[JavaScript Client](client-api.md)** - Widget embedding
- **[Deployment Guide](deployment.md)** - Production setup
- **[Security Guide](../SECURITY.md)** - Security best practices

### Community

- **GitHub:** [your-company/chatbot-boilerplate](https://github.com/your-company/chatbot-boilerplate)
- **Discord:** [Join our community](https://discord.gg/your-invite)
- **Stack Overflow:** Tag `chatbot-boilerplate`

### Commercial Support

- **Email:** support@your-domain.com
- **Priority Support:** Available for enterprise customers
- **Custom Development:** Contact sales@your-domain.com

---

## Changelog

### v1.0.0 (January 2025)

**Major Features:**
- ✨ Multi-tenancy with complete isolation
- ✨ Whitelabel publishing with HMAC auth
- ✨ LeadSense CRM integration
- ✨ AI-powered prompt builder
- ✨ Channel integrations (WhatsApp)
- ✨ GDPR/CCPA compliance tools
- ✨ Advanced audit logging
- ✨ Job queue and webhooks

**API Changes:**
- 🔧 New unified chat endpoint
- 🔧 Agent-based configuration
- 🔧 90+ admin endpoints
- 🔧 Structured output support

**Improvements:**
- 🚀 Performance optimizations
- 🚀 Connection resilience
- 🚀 Enhanced error handling
- 🚀 TypeScript definitions

**Bug Fixes:**
- 🐛 Fixed SSE reconnection issues
- 🐛 Fixed file upload race conditions
- 🐛 Fixed memory leaks in long sessions

**[View full changelog →](../CHANGELOG.md)**

---

## License

MIT License - See [LICENSE](../LICENSE) for details.

---

## Contributing

We welcome contributions! Please see our [Contributing Guide](../CONTRIBUTING.md) for details.

---

---

## Specialized Agents System

### Overview

The Specialized Agents System allows you to create domain-specific AI agents with custom behaviors, tools, and processing logic. Agents can integrate with external APIs, manage specialized tasks, and provide tailored responses.

### Available Agent Types

- **Generic Agent** (`generic`) - Standard LLM-based agent (default)
- **WordPress Agent** (`wordpress`) - WordPress content management via REST API
- **Template Agent** (`template`) - Reference implementation for developers

### Admin API Endpoints

#### List Agent Types

Get all available agent types with metadata.

```bash
GET /admin-api.php?action=list_agent_types
Authorization: Bearer <token>
```

**Response:**
```json
{
  "agent_types": [
    {
      "agent_type": "wordpress",
      "display_name": "WordPress Content Manager",
      "description": "Manages WordPress content...",
      "version": "1.0.0",
      "config_schema": {...},
      "custom_tools": [...]
    }
  ],
  "count": 1
}
```

#### Get Agent Type Metadata

Get detailed metadata for a specific agent type.

```bash
GET /admin-api.php?action=get_agent_type&agent_type=wordpress
Authorization: Bearer <token>
```

#### Validate Agent Configuration

Validate configuration against agent's schema.

```bash
POST /admin-api.php?action=validate_agent_config
Authorization: Bearer <token>
Content-Type: application/json

{
  "agent_type": "wordpress",
  "config": {
    "wp_site_url": "https://example.com",
    "wp_username": "admin",
    "wp_app_password": "${WP_PASSWORD}"
  }
}
```

#### Save Agent Configuration

Save specialized configuration for an agent.

```bash
POST /admin-api.php?action=save_agent_config
Authorization: Bearer <token>
Content-Type: application/json

{
  "agent_id": "agent-123",
  "agent_type": "wordpress",
  "config": {
    "wp_site_url": "https://myblog.com",
    "wp_username": "bot-user",
    "wp_app_password": "${WP_APP_PASSWORD}",
    "default_status": "draft",
    "auto_publish": false
  }
}
```

#### Get Agent Configuration

Retrieve specialized configuration for an agent.

```bash
GET /admin-api.php?action=get_agent_config&agent_id=agent-123
Authorization: Bearer <token>
```

#### Delete Agent Configuration

Delete specialized configuration (resets to generic).

```bash
DELETE /admin-api.php?action=delete_agent_config&agent_id=agent-123
Authorization: Bearer <token>
```

#### Discover Agents

Force re-discovery of available agent types.

```bash
GET /admin-api.php?action=discover_agents
Authorization: Bearer <token>
```

### Creating Custom Agents

See [Specialized Agents Specification](../SPECIALIZED_AGENTS_SPECIFICATION.md) for detailed information on creating custom agents.

**Quick Start:**
1. Copy `agents/_template/` to `agents/your-type/`
2. Implement `YourTypeAgent.php`
3. Define `config.schema.json`
4. Run `discover_agents` endpoint
5. Configure via `save_agent_config`

---

**Version:** 1.0.1
**Last Updated:** January 2025
**API Stability:** Stable (no breaking changes in v1.x)

**Ready to get started?** Choose your documentation:
- 🔧 [Admin API →](admin-api.md)
- 🌐 [Public API →](public-api.md)
- 📱 [JavaScript Client →](client-api.md)
- 🤖 [Specialized Agents →](../SPECIALIZED_AGENTS_SPECIFICATION.md)
