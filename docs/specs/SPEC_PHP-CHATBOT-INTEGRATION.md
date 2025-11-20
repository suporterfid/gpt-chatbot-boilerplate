# Integration Specification: php-chatbot Framework for GPT Chatbot Boilerplate

## Overview

This document provides a technical integration plan for migrating advanced features from the **php-chatbot** framework (by RumenDamyanov) into the **gpt-chatbot-boilerplate** project. The goal is to enable multi-provider AI support, enhanced conversation memory, advanced token/cost tracking, and additional enterprise-grade functionality in a modular, future-proof fashion.[1][2]

## 1. Comparative Analysis

### 1.1 php-chatbot (Source Framework)

**Key Features:**
- **Token & Cost Tracking:** Detailed API usage and spend analytics
- **Conversation Memory:** Persistent context with file/Redis/database backends
- **Streaming Responses:** Real-time output via Server-Sent Events (SSE)
- **AI Model Abstraction:** Unified interface for OpenAI, Anthropic, xAI, Google, Meta, Ollama
- **Multi-provider Support:** Dynamic ModelFactory, modular adapters
- **Framework Adapters:** Laravel and Symfony integration points
- **Message Filtering Middleware:** Profanity and aggression pattern detection[1]

### 1.2 gpt-chatbot-boilerplate (Target Project)

**Current Features:**
- **Dual API Support:** OpenAI Chat Completions and Responses API (Assistants)
- **Agent Management:** Multi-agent configuration and administration UI
- **File Uploads:** PDF, doc, image processing via OpenAI
- **Multi-Tenancy & Billing:** Tenant isolation, custom quotas, tracking
- **Security:** RBAC, API keys, rate limiting, full audit trail
- **Observability:** Prometheus/Grafana, structured logs, tracing
- **WhatsApp Integration:** Omnichannel chatbot via Z-API[2]

## 2. Features To Be Integrated

### 2.1 High Priority

#### 2.1.1 Multi-Provider AI Support

**Goal:** Enable agent-level configuration for different providers (OpenAI, Anthropic, Google, xAI, Meta, Ollama), leveraging php-chatbot's abstraction and ModelFactory.

- Extend the agent database schema for provider and model fields.
- Create `AIProviderInterface`, implement provider-specific clients.
- Refactor agent creation and management to set provider/model/config per agent.
- Enable runtime provider/model switching for advanced routing.

#### 2.1.2 Enhanced Conversation Memory

**Goal:** Modularize conversation memory with support for file, database, Redis, or in-memory backends.

- Define `MemoryInterface` for memory backends.
- Implement File, Database, Redis, and InMemory variants.
- Add automatic context window management.
- Integrate session and cross-session recall.

#### 2.1.3 Advanced Token & Cost Tracking

**Goal:** Track input/output tokens, costs per request, and provide analytics by provider/model.

- Store usage/cost per agent/provider/model/session.
- Estimate costs before requests (provider-rate aware).
- Enable detailed cost dashboards in admin UI.

#### 2.1.4 Message Filtering Middleware

**Goal:** Add plugin for filtering inappropriate content, profanity, and aggression according to configurable rules.

- Layer filtering checks as middleware pre-AI call.
- Log and flag filtered events for audit trail.

### 2.2 Medium Priority

#### 2.2.1 Improved Streaming

- Upgrade streaming features to support all providers capable of SSE, chunked, or streaming responses.

#### 2.2.2 Provider Abstraction Layer

- Centralize configuration, error handling, and fallback strategies for all supported model providers.

### 2.3 Required Architectural Changes

- Add `AIProviderInterface` and provider clients.
- Refactor `ChatHandler` to handle multiple providers.
- Add/extend agent and tracking schema for new fields.
- Implement modular memory backends.
- Integrate enhanced cost tracking and analytics services.

## 3. Directory Structure

```
includes/
├── AI/
│   ├── AIProviderInterface.php
│   ├── ModelFactory.php
│   ├── Providers/
│   └── TokenCounter.php
├── Memory/
│   └── (Memory backends and context manager)
├── Tracking/
│   └── TokenUsage.php, CostTrackingService.php
├── Middleware/
│   └── MessageFilterMiddleware.php
└── ...
```

## 4. Database Migrations

### 4.1 Agents Table

```sql
ALTER TABLE agents ADD COLUMN ai_provider VARCHAR(50) DEFAULT 'openai';
ALTER TABLE agents ADD COLUMN provider_config TEXT;
ALTER TABLE agents ADD COLUMN memory_backend VARCHAR(50) DEFAULT 'database';
ALTER TABLE agents ADD COLUMN context_window INTEGER DEFAULT 50000;
```

### 4.2 Cost Tracking Table

```sql
CREATE TABLE IF NOT EXISTS cost_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER,
    agent_id INTEGER,
    session_id VARCHAR(255),
    provider VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    input_tokens INTEGER NOT NULL,
    output_tokens INTEGER NOT NULL,
    total_tokens INTEGER NOT NULL,
    cost DECIMAL(10, 6) NOT NULL,
    request_type VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (agent_id) REFERENCES agents(id)
);
```

## 5. Environment Variables

```
# AI Providers
AI_PROVIDER=openai     
ANTHROPIC_API_KEY=...         
GOOGLE_API_KEY=...            
XAI_API_KEY=...               
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama2
MEMORY_BACKEND=database
MEMORY_CONTEXT_WINDOW=50000
# Cost tracking
COST_TRACKING_ENABLED=true
MESSAGE_FILTERING_ENABLED=true
MESSAGE_FILTERING_PROFANITIES=word1,word2,...
MESSAGE_FILTERING_AGGRESSION_PATTERNS=hate,kill,stupid
MESSAGE_FILTERING_REMOVE_LINKS=true
```

## 6. Admin Interface

- Add agent-level configuration/selection for provider, model, and memory backend.
- Visual analytics for cost/tracking across tenants/providers/models.
- Message filtering dashboard for security admin.

## 7. API Endpoints

- `GET /api/ai-providers` — List available providers/models.
- `POST /api/agents/{id}/provider` — Update agent provider config.
- `GET /api/cost-analytics` — Query cost analytics by tenant/agent/provider/model.

## 8. Unit/Integration Tests

- Test ModelFactory creation for supported providers.
- Validate cost tracking for all models/providers.
- Verify memory backend switching logic.

## 9. Documentation

### Usage Guide — Multi-Provider & Memory

```
1. Open Admin Panel > Agents > Edit Agent
2. Select AI provider/model (OpenAI, Claude, Gemini etc)
3. Enter API credentials as needed
4. Choose memory backend (session, file, database, redis)
5. Save config, test agent endpoint
```

### Cost Comparison Table

| Provider | Model           | Input ($/1K) | Output ($/1K) |
|----------|-----------------|--------------|---------------|
| OpenAI   | gpt-4o-mini     | $0.00015     | $0.0006       |
| Anthropic| claude-sonnet   | $0.003       | $0.015        |
| Google   | gemini-flash    | $0.000075    | $0.0003       |
| xAI      | grok-beta       | $0.001       | $0.004        |
| Ollama   | llama2          | Free         | Free          |

## 10. Security Notes

- API keys encrypted in database and never exposed on frontend.
- Provider validation for supported values.
- Full audit logging for cost/filtering/memory events.

## 11. Roadmap Summary

**Phase 1:** Core abstraction/schema changes  
**Phase 2:** Provider clients, ModelFactory  
**Phase 3:** Memory system improvements  
**Phase 4:** Cost tracking  
**Phase 5:** Filtering/security  
**Phase 6:** UI and documentation

## References

 php-chatbot by RumenDamyanov[1]
 gpt-chatbot-boilerplate[2]
 Inspector.dev — PHP memory/context window best practices[3]

***

**End of Spec**

[1](https://github.com/RumenDamyanov/php-chatbot)
[2](https://github.com)
[3](https://inspector.dev/ai-agents-memory-and-context-window-in-php/)
