# Neuron AI Integration - Implementation Tasks

**Specification:** NEURON_AI_INTEGRATION_SPEC.md  
**Project:** Multi-LLM Platform Enhancement  
**Status:** Ready for GitHub Issue Creation  
**Last Updated:** November 18, 2025

---

## Overview

This document breaks down the comprehensive Neuron AI Integration Specification into actionable GitHub issues organized by phase and component. All issues are designed to be specific, measurable, and linked to the specification requirements.

**Total Issues:** 31  
**Timeline:** 18 weeks  
**Organized by:** Implementation phase and feature area

---

## Phase 1: Foundation (Weeks 1-2)

### Core Dependency Installation

#### [P1-001] Install Neuron AI SDK and Dependencies
- **Epic:** Foundation  
- **Complexity:** Low  
- **Effort:** 2-3 days  
- **Dependencies:** None

**Description:**  
Install Neuron AI framework and required dependencies to establish foundation for multi-LLM platform.

**Tasks:**
- Install neuron-core/neuron-ai ^2.0 via Composer
- Add inspector-apm/inspector-php ^4.0 dependency
- Update composer.lock and commit
- Verify package installation with tests
- Document dependency versions in README

**Acceptance Criteria:**
- All packages installed successfully
- No conflicts with existing dependencies
- Tests pass: `composer run analyze`
- Documentation updated
- Installation reproducible on clean environment

**Related:**
- Section 6.3 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P1-002] Set up Inspector.dev Integration Configuration
- **Epic:** Foundation  
- **Complexity:** Low  
- **Effort:** 2-3 days  
- **Dependencies:** [P1-001]

**Description:**  
Configure Inspector.dev for AI-specific observability and monitoring throughout the system.

**Tasks:**
- Create config/neuron.php configuration file
- Add INSPECTOR_ENABLED and INSPECTOR_INGESTION_KEY to .env
- Implement Inspector client initialization
- Create Inspector::enable/disable toggles
- Add sampling configuration
- Document Inspector.dev setup in README

**Acceptance Criteria:**
- Inspector.dev configured and accessible
- Environment variables properly documented
- Configuration can be toggled via feature flag
- No errors on application startup
- Sample transactions captured in Inspector.dev dashboard

**Related:**
- Section 3.5.1 in NEURON_AI_INTEGRATION_SPEC.md

---

### Database Schema

#### [P1-003] Create Database Migrations for Multi-LLM Support
- **Epic:** Foundation  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** None

**Description:**  
Add database schema extensions to support multiple LLM providers, workflows, and session management.

**Database Changes Required:**

**1. Extend agents table**
```sql
ALTER TABLE agents ADD COLUMN provider VARCHAR(50) DEFAULT 'openai';
ALTER TABLE agents ADD COLUMN provider_model VARCHAR(100);
ALTER TABLE agents ADD COLUMN provider_config JSON;
ALTER TABLE agents ADD COLUMN agent_type VARCHAR(50) NOT NULL;
ALTER TABLE agents ADD COLUMN agent_class VARCHAR(255);
ALTER TABLE agents ADD COLUMN system_prompt TEXT;
ALTER TABLE agents ADD COLUMN tools_config JSON;
ALTER TABLE agents ADD COLUMN enable_memory BOOLEAN DEFAULT TRUE;
ALTER TABLE agents ADD COLUMN memory_window INT DEFAULT 50;
ALTER TABLE agents ADD COLUMN session_ttl INT DEFAULT 3600;
ALTER TABLE agents ADD COLUMN temperature DECIMAL(3,2) DEFAULT 0.70;
ALTER TABLE agents ADD COLUMN max_tokens INT DEFAULT 4000;
ALTER TABLE agents ADD COLUMN top_p DECIMAL(3,2) DEFAULT 1.00;
ALTER TABLE agents ADD COLUMN frequency_penalty DECIMAL(3,2) DEFAULT 0.00;
ALTER TABLE agents ADD COLUMN presence_penalty DECIMAL(3,2) DEFAULT 0.00;
```

**2. Create providers table**
- Provider registry with capabilities
- Supported models and pricing
- Enable/disable status

**3. Create chat_sessions table**
- Session storage and history
- Token and cost tracking
- Expiration management

**4. Create workflows and related tables**
- workflows: Workflow definitions
- workflow_executions: Workflow executions and events
- workflow_events: Audit trail of events
- workflow_interrupts: Human-in-the-loop requests

**Acceptance Criteria:**
- All migrations run successfully
- Schema aligns with specification (Section 5)
- Backward compatible with existing data
- Migrations can be rolled back
- No data loss during migration
- Indexes created for query optimization

**Related:**
- Section 5 in NEURON_AI_INTEGRATION_SPEC.md

---

### Provider Framework

#### [P1-004] Implement ProviderFactory and Multi-Provider Abstraction
- **Epic:** Foundation  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** [P1-001], [P1-003]

**Description:**  
Create provider abstraction layer supporting multiple LLM providers (OpenAI, Anthropic, Gemini, Ollama).

**Architecture:**
- Factory pattern for provider instantiation
- Support for configuration via array or environment
- Type-safe interfaces
- Proper error handling for unsupported providers

**Tasks:**
- Create includes/Providers/ProviderFactory.php
- Create includes/Providers/ProviderConfig.php
- Create includes/Providers/ProviderRegistry.php
- Implement factory method for all Phase 1 providers
- Add provider validation
- Create comprehensive unit tests (80%+ coverage)
- Document provider configuration

**Supported Providers (Phase 1):**
- OpenAI (GPT-4o, GPT-4o-mini, o1-preview, o1-mini)
- Anthropic (Claude 3.5 Sonnet, Claude 3 Haiku, Claude 3 Opus)
- Google Gemini (Gemini 2.0 Flash, Gemini 1.5 Pro)
- Ollama (Local models: Llama 3.2, Mistral, etc.)

**Acceptance Criteria:**
- ProviderFactory creates correct provider instances
- Supports configuration via array or config file
- Proper error handling for unsupported providers
- 80%+ unit test coverage
- Type hints for all methods
- PSR-12 and PHPStan Level 9 compliant
- Documentation complete

**Related:**
- Section 3.1.3 in NEURON_AI_INTEGRATION_SPEC.md

---

### Agent Framework

#### [P1-005] Create BaseAgent Class Extending Neuron\\Agent
- **Epic:** Foundation  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** [P1-001], [P1-004]

**Description:**  
Implement base agent class integrating Neuron AI framework with existing project patterns.

**Architecture:**
- Extends Neuron\Agent
- Integrates TenantContext for multi-tenancy
- Implements provider selection via ProviderFactory
- Includes before/after hooks for extensibility
- Supports rate limiting and usage tracking

**Tasks:**
- Create includes/Neuron/Agents/BaseAgent.php
- Extend Neuron\Agent class
- Integrate TenantContext for multi-tenancy
- Implement provider() method using ProviderFactory
- Add beforeChat() and afterChat() hooks
- Implement rate limiting checks
- Implement usage tracking
- Add comprehensive unit tests
- Document agent development patterns

**Requirements:**
- PSR-12 compliance
- PHPStan Level 9 compliance
- Full type hints
- Comprehensive docblocks

**Acceptance Criteria:**
- BaseAgent extends Neuron\Agent correctly
- All abstract methods documented
- Multi-tenancy properly scoped
- Rate limiting works
- Usage tracking accurate
- Unit tests pass (80%+ coverage)
- Type-safe implementation

**Related:**
- Section 3.2.1 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P1-006] Implement AgentRegistry and AgentFactory
- **Epic:** Foundation  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** [P1-005]

**Description:**  
Create registry and factory for agent instantiation and management.

**Components:**

**AgentRegistry:**
- Stores mapping of agent types to classes
- register(type, className) method
- get(type) method with error handling
- listAvailable() method

**AgentFactory:**
- create(agentId) method loading from DB
- createFromConfig(array) method
- Proper dependency injection
- Resolves provider configuration

**Tasks:**
- Create includes/Neuron/AgentRegistry.php
- Create includes/Neuron/AgentFactory.php
- Implement agent registration on startup
- Add initial built-in agents to registry
  - SupportAgent
  - SalesAgent
  - ResearchAgent
- Create unit tests for both classes
- Document agent registration process

**Acceptance Criteria:**
- AgentRegistry stores and retrieves agents correctly
- AgentFactory creates instances with proper dependencies
- Built-in agents auto-registered on startup
- Type-safe implementation
- 80%+ unit test coverage
- Error handling for unknown agents

**Related:**
- Section 3.2.2 in NEURON_AI_INTEGRATION_SPEC.md

---

### Configuration Management

#### [P1-007] Add Neuron AI Feature Flags and Configuration
- **Epic:** Foundation  
- **Complexity:** Low  
- **Effort:** 2-3 days  
- **Dependencies:** [P1-002]

**Description:**  
Implement feature flags to enable gradual rollout of Neuron AI components.

**Configuration Structure:**
```php
'neuron' => [
    'enabled' => env('NEURON_ENABLED', false),
    'legacy_mode' => env('LEGACY_MODE', true),
    'agents' => [...], // Agent registry
    'observability' => [...] // Inspector.dev config
]
```

**Environment Variables:**
- NEURON_ENABLED (default: false)
- LEGACY_MODE (default: true)
- INSPECTOR_ENABLED (default: true)
- INSPECTOR_INGESTION_KEY

**Tasks:**
- Create config/neuron.php with all feature flags
- Add environment variables to .env.example
- Implement configuration validation on startup
- Add feature flag checks in request handlers
- Create function to check feature flags
- Document configuration options
- Add tests for configuration loading

**Acceptance Criteria:**
- Neuron AI can be enabled/disabled independently
- Legacy mode works alongside Neuron AI
- Configuration validated on startup
- No errors when flags are disabled
- Clear error messages for missing config

**Related:**
- Section 7.1 in NEURON_AI_INTEGRATION_SPEC.md

---

## Phase 2: Single Agent Migration (Weeks 3-4)

### Pilot Migration

#### [P2-001] Migrate First Agent to Neuron AI (Pilot)
- **Epic:** Pilot Agent Migration  
- **Complexity:** High  
- **Effort:** 5-7 days  
- **Dependencies:** [P1-001] through [P1-007]

**Description:**  
Migrate a low-risk agent (FAQ/Support) to Neuron AI as proof of concept.

**Approach:**
- Select lowest-risk agent (e.g., FAQ bot or basic support)
- Create parallel implementation
- Route 10% traffic to new implementation initially
- Monitor metrics compared to legacy version
- Gradually increase traffic to 100% if successful

**Metrics to Track:**
- Response latency (p50, p95, p99)
- Error rate and error types
- Cost per request (tokens × pricing)
- User satisfaction/quality
- Token usage patterns

**Tasks:**
- Select target agent
- Create Neuron AI implementation
- Set up parallel execution with feature flag
- Start with 10% traffic routing
- Collect baseline metrics (1 week)
- Compare with legacy implementation
- Document performance differences
- Gradually increase traffic (10% → 25% → 50% → 100%)
- Verify system stability at each stage
- Document lessons learned

**Success Criteria:**
- Agent fully operational on Neuron AI
- Performance equivalent or better than legacy
- Error rate < 1%
- Cost within 10% of legacy
- Traffic successfully increased to 100%
- No critical issues reported

**Related:**
- Section 7.1 Phase 2 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P2-002] Implement Agent Parallel Execution (A/B Testing)
- **Epic:** Pilot Agent Migration  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** [P1-007]

**Description:**  
Set up infrastructure for running legacy and Neuron AI agents in parallel for comparison.

**Architecture:**
- Clone incoming requests to both handlers
- Collect metrics from both
- Compare results (quality, performance, cost)
- No impact on user (only use Neuron AI response when ready)

**Tasks:**
- Create parallel execution middleware
- Implement metrics collection for both paths
- Add traffic splitting logic (feature flag based)
- Create metrics comparison utilities
- Build comparison report/dashboard
- Document A/B testing methodology

**Metrics to Compare:**
- Response latency
- Token usage
- Cost calculation
- Error rates
- Response quality (if applicable)

**Acceptance Criteria:**
- Both implementations execute for comparison
- Metrics collected and comparable
- No user-facing impact
- Traffic splitting works correctly
- Reports generated successfully
- Performance acceptable (< 50ms overhead)

**Related:**
- Section 7.1 Phase 2 in NEURON_AI_INTEGRATION_SPEC.md

---

## Phase 3: Multi-Provider Support (Weeks 5-6)

### Admin UI Enhancements

#### [P3-001] Implement Admin UI Provider Management Page
- **Epic:** Multi-Provider Support  
- **Complexity:** Medium  
- **Effort:** 4-5 days  
- **Dependencies:** [P1-003], [P1-004]

**Description:**  
Create admin interface for managing LLM providers and their configurations.

**UI Components:**

**Provider List:**
- List all available providers
- Enable/disable toggles
- Capabilities display
- Default model indicator

**Provider Configuration:**
- API key input (with secure masking)
- Default model selection
- Custom settings per provider
- Model pricing information

**Pricing Configuration:**
- Token pricing per provider/model
- Cost display formatting
- Update and save functionality

**Tasks:**
- Create provider management page template
- Implement provider listing with filters
- Add enable/disable functionality
- Build credentials input with validation
- Add model selection for each provider
- Implement pricing configuration UI
- Add success/error notifications
- Create responsive design (mobile, tablet, desktop)
- Add form validation

**Acceptance Criteria:**
- All providers can be managed from UI
- Credentials securely handled (no logging)
- Pricing information configurable
- UI responsive on all screen sizes
- Form validation working
- Error messages clear and helpful

**Related:**
- Section 3.1.4 in NEURON_AI_INTEGRATION_SPEC.md

---

### Security Implementation

#### [P3-002] Implement Encrypted Credentials Storage
- **Epic:** Multi-Provider Support  
- **Complexity:** High  
- **Effort:** 4-5 days  
- **Dependencies:** [P1-003], [P3-001]

**Description:**  
Implement secure storage for provider API keys and credentials.

**Security Requirements:**
- AES-256-GCM encryption at rest
- Proper key management and rotation
- Audit logging of all credential access
- Credential rotation support
- No credentials in logs or error messages

**Architecture:**
- Create credentials encryption service
- Store in database with proper indexing
- Generate audit trails
- Support credential rotation

**Tasks:**
- Create SecureCredentialStorage class
- Implement AES-256-GCM encryption
- Add database table for credentials
- Implement access audit logging
- Create credential retrieval methods
- Implement credential rotation utilities
- Add security tests
- Document credential management

**Database Table:**
- tenant_id
- provider name
- encrypted_key (AES-256)
- iv (initialization vector)
- tag (authentication tag)
- created_at, updated_at, rotated_at

**Acceptance Criteria:**
- API keys encrypted with AES-256-GCM
- Keys never logged or exposed
- Access audit trails maintained
- Rotation supported
- Security audit passed
- No sensitive data in error messages

**Related:**
- Section 8.1 in NEURON_AI_INTEGRATION_SPEC.md

---

### Provider Configuration

#### [P3-003] Add Provider Selection to Agent Configuration
- **Epic:** Multi-Provider Support  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** [P1-004], [P1-006]

**Description:**  
Extend agent configuration UI to support provider and model selection.

**UI Enhancements:**

**Provider Selection:**
- Dropdown of enabled providers only
- Display provider capabilities
- Show supported features (streaming, vision, etc.)

**Model Selection:**
- Populated based on selected provider
- Display model pricing
- Show context window size
- Cost preview: tokens × pricing

**Settings:**
- Provider-specific parameters (temperature, max_tokens, etc.)
- Tool availability by provider
- Capability warnings (e.g., "Ollama doesn't support vision")

**Tasks:**
- Update agent form with provider selector
- Implement dynamic model population
- Add model-specific settings fields
- Implement cost calculation preview
- Add validation for provider/model compatibility
- Create responsive design
- Add help text and tooltips

**Validation Rules:**
- Provider must be enabled
- Model must be valid for provider
- Temperature: 0-2
- Max tokens: > 0
- Settings must match provider capabilities

**Acceptance Criteria:**
- Provider selection works correctly
- Model list updates based on provider
- Settings validated before save
- Cost preview displays accurately
- Mobile responsive
- Error messages helpful

**Related:**
- Section 3.1.4 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P3-004] Implement Cost Tracking Per Provider
- **Epic:** Multi-Provider Support  
- **Complexity:** Medium  
- **Effort:** 4-5 days  
- **Dependencies:** [P1-003], [P1-005]

**Description:**  
Add cost tracking and analytics for multi-provider usage.

**Tracking Components:**

**Token Counting:**
- Track prompt tokens and completion tokens
- Store per provider/model/agent
- Aggregate by tenant/user

**Cost Calculation:**
- Apply provider-specific pricing
- Calculate per-request cost
- Track daily/monthly costs

**Reporting:**
- Cost by provider
- Cost by agent
- Cost by tenant
- Trends and comparisons

**Database Changes:**
- Add cost columns to chat_sessions
- Add cost columns to workflow_executions
- Create cost_tracking table for aggregation
- Add indexes for reporting queries

**Tasks:**
- Create CostCalculationService
- Implement token counting per provider
- Store costs in database
- Create cost aggregation queries
- Implement cost analytics API
- Add cost to agent execution responses
- Add to workflow cost tracking
- Create cost reports
- Document pricing configuration

**Acceptance Criteria:**
- Costs accurately calculated
- Queryable by provider/agent/tenant/date
- No performance impact
- Costs included in API responses
- Reports accurate and useful

**Related:**
- Section 3.1.2 and 8.2 in NEURON_AI_INTEGRATION_SPEC.md

---

## Phase 4: Memory Management (Weeks 7-8)

### Session Storage

#### [P4-001] Implement SessionStorage with Multiple Drivers
- **Epic:** Memory Management  
- **Complexity:** High  
- **Effort:** 5-6 days  
- **Dependencies:** [P1-001], [P1-003], [P1-005]

**Description:**  
Implement Neuron AI ChatSession storage with support for multiple backends.

**Supported Drivers:**

**Database Driver:**
- Persistent storage in chat_sessions table
- Suitable for all use cases
- Replicate across DB servers

**Redis Driver:**
- Fast in-memory caching
- TTL support (configurable)
- Good for high-traffic scenarios
- Automatic expiration

**File System Driver:**
- Local file storage
- Good for development/testing
- Suitable for single-server deployments

**Configuration:**
```php
'session_storage' => [
    'driver' => env('SESSION_STORAGE_DRIVER', 'database'),
    'redis_prefix' => 'session:',
    'ttl' => 3600, // 1 hour
    'file_path' => storage_path('sessions')
]
```

**Tasks:**
- Create SessionStorage base class
- Implement database driver
- Implement Redis driver
- Implement file system driver
- Add driver selection via config
- Create session expiration logic
- Add TTL management
- Add unit tests for each driver
- Document storage options

**Acceptance Criteria:**
- All drivers functional
- Session data persists correctly
- TTL/expiration working
- Driver switching seamless
- 80%+ unit test coverage
- Performance acceptable

**Related:**
- Section 3.3 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P4-002] Migrate Existing Conversation Data to ChatSession Format
- **Epic:** Memory Management  
- **Complexity:** High  
- **Effort:** 4-5 days  
- **Dependencies:** [P4-001], [P1-003]

**Description:**  
Migrate existing conversation history to new ChatSession storage format.

**Migration Process:**

**Data Extraction:**
- Query existing conversation tables
- Extract message history
- Extract metadata and context

**Transformation:**
- Convert to ChatSession format
- Handle legacy session structures
- Preserve timestamps and user data

**Loading:**
- Create new chat_sessions records
- Create corresponding session_metadata records
- Verify data integrity

**Verification:**
- Compare record counts
- Spot-check data accuracy
- Verify date ranges

**Tasks:**
- Create AgentMigrator utility class
- Extract existing conversation data
- Implement transformation logic
- Handle legacy session structures
- Create session records
- Verify data integrity
- Create rollback procedure
- Test with sample data
- Add dry-run mode
- Document migration process

**Acceptance Criteria:**
- All conversation data migrated
- No data loss
- Rollback tested and working
- Performance acceptable (< 5min for typical install)
- Data integrity verified
- User experience unaffected

**Related:**
- Section 7.3 in NEURON_AI_INTEGRATION_SPEC.md

---

### Session APIs

#### [P4-003] Create Session Management API Endpoints
- **Epic:** Memory Management  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** [P4-001]

**Description:**  
Implement REST API endpoints for session management and history retrieval.

**Endpoints:**

**Session Operations:**
- GET /api/sessions - List user sessions
- GET /api/sessions/{id} - Get session details
- DELETE /api/sessions/{id} - Delete session
- POST /api/sessions/{id}/clear - Clear history

**Message History:**
- GET /api/sessions/{id}/messages - Get message history
- GET /api/sessions/{id}/messages?limit=50&offset=0 - Paginated

**Analytics:**
- POST /api/sessions/{id}/summarize - Generate summary
- GET /api/sessions/{id}/stats - Get session stats

**Response Format:**
```json
{
  "session_id": "session_123",
  "agent_id": 1,
  "created_at": "2025-01-15T10:30:00Z",
  "message_count": 15,
  "total_tokens": 2450,
  "messages": [...]
}
```

**Tasks:**
- Implement session list endpoint
- Implement session detail endpoint
- Implement session delete endpoint
- Implement message retrieval endpoint
- Implement session summary endpoint
- Add RBAC permission checks
- Implement filtering and pagination
- Add rate limiting
- Create request validation
- Add response formatting
- Create integration tests

**Acceptance Criteria:**
- All endpoints functional
- Proper authentication/authorization
- Pagination working correctly
- Error handling correct
- Integration tests pass
- Performance acceptable

**Related:**
- Section 3.3 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P4-004] Implement Cross-Session Context and Memory Retrieval
- **Epic:** Memory Management  
- **Complexity:** Medium  
- **Effort:** 3-4 days  
- **Dependencies:** [P4-001], [P4-003]

**Description:**  
Implement contextual memory retrieval across multiple user sessions.

**Features:**

**Recent Session Retrieval:**
- Fetch recent sessions for user
- Extract key information
- Rank by relevance

**Semantic Search (Optional Phase 2):**
- Use embeddings for similarity
- Find contextually relevant sessions
- Weight by recency

**Context Extraction:**
- Extract user preferences
- Identify mentioned entities
- Track conversation topics

**Architecture:**
```
User Query
    ↓
Recent Sessions (last 7 days)
    ↓
Key Info Extraction
    ↓
Optional: Semantic Ranking
    ↓
Top N Most Relevant (default: 5)
    ↓
Add to Agent Context
```

**Tasks:**
- Create ContextualMemory class
- Implement recent session retrieval
- Add key information extraction
- Implement context ranking
- Add optional semantic search
- Integrate with agent context
- Create unit tests
- Document configuration
- Add performance optimization

**Configuration:**
- Max sessions to consider: 10 (default)
- Time window: 7 days (default)
- Max context pieces to return: 5 (default)
- Enable semantic search: false (default)

**Acceptance Criteria:**
- Relevant context retrieved accurately
- No significant performance impact
- Configurable depth/breadth
- Works with multiple sessions
- Unit tests pass
- Semantic search optional

**Related:**
- Section 3.3.4 in NEURON_AI_INTEGRATION_SPEC.md

---

## Phase 6: Multi-Agent Workflows (Weeks 13-16)

### Workflow Engine

#### [P6-001] Implement WorkflowEngine for Multi-Agent Orchestration
- **Epic:** Multi-Agent Workflows  
- **Complexity:** High  
- **Effort:** 6-8 days  
- **Dependencies:** [P1-001], [P1-005], [P1-006]

**Description:**  
Create workflow engine for orchestrating multi-agent workflows.

**Architecture:**

**Workflow Components:**
- Event-driven workflow execution
- Node-based workflow structure
- State management across nodes
- Progress tracking
- Error handling and recovery

**Node Types:**
- StartNode (workflow entry point)
- Agent execution nodes
- Decision nodes
- Approval nodes (human-in-the-loop)
- End nodes

**Events:**
- NodeStarted
- NodeCompleted
- ProgressEvent
- ErrorEvent
- InterruptEvent (human approval needed)

**Tasks:**
- Create includes/Neuron/WorkflowEngine.php
- Create base Workflow class
- Create base Node class
- Implement event emission system
- Add state management
- Implement error recovery
- Add execution tracking
- Create comprehensive unit tests
- Document workflow patterns
- Add example workflows

**Example Workflow Pattern:**
- WelcomeNode
- InformationCollectionNode
- ValidationNode
- CRMIntegrationNode
- SchedulingNode
- ConfirmationNode

**Acceptance Criteria:**
- Workflows execute correctly
- Events handled properly
- State persisted and retrieved
- Error handling robust
- Parallel node execution supported
- 80%+ unit test coverage

**Related:**
- Section 3.4 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P6-002] Create Workflow Database Tables and Schema
- **Epic:** Multi-Agent Workflows  
- **Complexity:** Medium  
- **Effort:** 2-3 days  
- **Dependencies:** [P1-003]

**Description:**  
Create database schema for workflow definitions, executions, events, and interrupts.

**Tables:**

**workflows**
- id, name, description
- workflow_class (PHP class name)
- tenant_id (multi-tenancy)
- is_enabled
- configuration (JSON)
- created_by, created_at, updated_at

**workflow_executions**
- id, execution_id (UUID)
- workflow_id, status
- state (JSON - complete workflow state)
- current_node
- error_message (if failed)
- started_at, completed_at
- estimated_completion
- tenant_id, user_id

**workflow_events**
- id, execution_id
- event_type (started, completed, progress, error, etc.)
- node_name
- event_data (JSON)
- created_at

**workflow_interrupts**
- id, execution_id
- interrupt_type (approval_required, etc.)
- message, data (JSON)
- status (pending, resolved, cancelled)
- resolved_by, resolved_at
- resolution_data (JSON)

**Tasks:**
- Create migration with all tables
- Add appropriate indexes for queries
- Add foreign key constraints
- Add create test fixtures
- Document schema relationships

**Indexes Required:**
- workflow_id on executions
- status on executions
- execution_id on events
- execution_id on interrupts
- created_at for time-based queries

**Acceptance Criteria:**
- All tables created correctly
- Relationships defined properly
- Indexes optimize query performance
- Migration tested and rollbackable
- Constraints enforced

**Related:**
- Section 3.4.2 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P6-003] Implement Workflow Management REST API
- **Epic:** Multi-Agent Workflows  
- **Complexity:** High  
- **Effort:** 5-6 days  
- **Dependencies:** [P6-001], [P6-002]

**Description:**  
Create REST API for workflow CRUD operations and execution management.

**Endpoints:**

**Workflow Management:**
- POST /api/workflows - Create workflow
- GET /api/workflows - List workflows (paginated, filtered)
- GET /api/workflows/{id} - Get workflow details
- PUT /api/workflows/{id} - Update workflow
- DELETE /api/workflows/{id} - Delete workflow

**Execution Management:**
- POST /api/workflows/{id}/execute - Start execution
- GET /api/workflows/executions/{execId} - Get execution status
- POST /api/workflows/executions/{execId}/cancel - Cancel execution
- GET /api/workflows/executions/{execId}/events - Get execution events
- POST /api/workflows/executions/{execId}/approve - Approve interrupt
- POST /api/workflows/executions/{execId}/reject - Reject interrupt

**Request/Response Examples:**
- Create workflow: class_name, configuration
- Execute workflow: initial_state, webhook_url
- Response: execution_id, status, tracking_url

**Tasks:**
- Implement API routes
- Add request validation
- Add RBAC permission checks
- Implement response formatting
- Add error handling
- Add rate limiting
- Create integration tests
- Document API with examples
- Add to OpenAPI/Swagger spec

**Acceptance Criteria:**
- All endpoints functional
- Proper error responses (400, 401, 403, 404, 500)
- Pagination working correctly
- Integration tests pass
- Rate limiting effective
- Documentation complete

**Related:**
- Section 4.2 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P6-004] Implement Human-in-the-Loop Workflow Support
- **Epic:** Multi-Agent Workflows  
- **Complexity:** High  
- **Effort:** 4-5 days  
- **Dependencies:** [P6-001], [P6-002]

**Description:**  
Implement workflow interrupts for human approval and decision points.

**Features:**

**Interrupt Types:**
- approval_required: Manager approval needed
- decision_required: Operator decision needed
- review_required: Manual review needed
- escalation_needed: Escalation to higher level

**Interrupt Handling:**
- Pause workflow at decision point
- Store request with context data
- Notify stakeholders
- Wait for resolution
- Resume with decision
- Timeout handling (configurable)

**Tasks:**
- Create InterruptEvent class
- Create approval workflow nodes
- Add interrupt storage logic
- Create interrupt notification system
- Add approval/rejection API endpoints
- Implement timeout logic
- Add resume logic after approval
- Create integration tests
- Document interrupt patterns

**Interrupt API Endpoints:**
- GET /api/workflows/interrupts - List pending
- POST /api/workflows/interrupts/{id}/approve - Approve
- POST /api/workflows/interrupts/{id}/reject - Reject
- GET /api/workflows/interrupts/{id} - Get details

**Approval Node Pattern:**
```php
class ApprovalNode extends Node {
    public function __invoke(Event $event, WorkflowState $state): Event {
        $this->emit(new InterruptEvent(
            type: 'approval_required',
            message: 'Approval needed for: ...',
            data: $state->get('context'),
            resumeOnApproval: true,
            timeout: 3600 // 1 hour
        ));
    }
}
```

**Acceptance Criteria:**
- Workflows pause for approval
- Approvals resume correctly
- Timeouts handled properly
- Audit trail maintained
- Notifications sent
- Integration tests pass

**Related:**
- Section 3.4.4 in NEURON_AI_INTEGRATION_SPEC.md

---

## Phase 7: Inspector.dev Integration (Weeks 17-18)

### Observability

#### [P7-001] Configure Inspector.dev Dashboards and Alerts
- **Epic:** Observability  
- **Complexity:** Medium  
- **Effort:** 4-5 days  
- **Dependencies:** [P1-002]

**Description:**  
Set up Inspector.dev dashboards and alerting for production observability.

**Dashboards to Create:**

**1. Agent Performance Dashboard:**
- Average response time by agent (p50, p95, p99)
- Token usage trends per agent
- Error rates by agent type
- Cost comparison by agent
- Provider performance for each agent

**2. Workflow Dashboard:**
- Workflow execution success rate
- Average workflow duration
- Node-level performance
- Human-in-the-loop wait times
- Most common interruption types

**3. Provider Dashboard:**
- Request distribution across providers
- Provider latency comparison
- Provider cost analysis
- Error rates per provider
- Token usage per provider

**4. Tenant Dashboard:**
- Usage trends by tenant
- Cost allocation by tenant
- Rate limit incidents
- Agent usage patterns
- Revenue impact

**Alerting Rules:**

**Performance Alerts:**
- Error rate > 5%
- Response time p95 > 30s
- Provider unavailable

**Resource Alerts:**
- Daily tokens > tenant limit
- Cost anomaly (> 2x average)

**Workflow Alerts:**
- Workflow failure
- Interrupt timeout exceeded
- Workflow stuck (no progress)

**Tasks:**
- Create custom dashboards in Inspector.dev
- Configure alert thresholds
- Set up alert channels (email, Slack, PagerDuty)
- Create dashboard documentation
- Create runbooks for alerts
- Train team on dashboard usage
- Set up alert escalation paths

**Dashboard Metrics:**
- P50, P95, P99 latencies
- Request rate and volume
- Error rates and types
- Token usage and costs
- Provider comparison
- Workflow execution stats

**Acceptance Criteria:**
- All dashboards functional
- Alerts trigger correctly
- Data accurate and timely
- Team trained on usage
- Runbooks documented

**Related:**
- Section 3.5 and 9 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [P7-002] Implement Automatic Inspector.dev Instrumentation
- **Epic:** Observability  
- **Complexity:** High  
- **Effort:** 5-6 days  
- **Dependencies:** [P1-002], [P1-005], [P6-001]

**Description:**  
Add automatic instrumentation throughout codebase for Inspector.dev observability.

**Instrumentation Points:**

**Agent Execution:**
- Start transaction on agent.chat()
- Add context: agent type, provider, model
- Track token usage (prompt + completion)
- Record response time
- Log errors with full context
- End transaction with result

**Workflow Execution:**
- Start transaction for each workflow
- Track node execution start/end
- Record state transitions
- Log interrupts and approvals
- Track total duration
- Capture errors

**Tool Execution:**
- Track tool calls
- Record parameters (sanitized)
- Monitor latency
- Log tool errors

**Tasks:**
- Create ObservableAgent extending BaseAgent
- Add transaction tracking to agents
- Add instrumentation to WorkflowEngine
- Implement node execution tracking
- Create custom metrics/spans
- Implement context propagation
- Add error tracking with context
- Create instrumentation utilities
- Add unit tests
- Document instrumentation patterns

**Observable Metrics:**
- Agent execution time
- Token usage (prompt + completion)
- Cost per execution
- Error rates and types
- Provider latency
- Workflow node duration
- Interrupt duration

**Acceptance Criteria:**
- All major operations tracked
- Metrics accurate
- No significant performance impact
- Data properly formatted
- Sampling working correctly
- Sensitive data filtered

**Related:**
- Section 3.5.2 in NEURON_AI_INTEGRATION_SPEC.md

---

## Testing and Quality (Cross-Cutting)

### Testing Infrastructure

#### [QA-001] Create Comprehensive Unit Tests (80%+ Coverage)
- **Epic:** Quality Assurance  
- **Complexity:** High  
- **Effort:** 8-10 days  
- **Dependencies:** [P1-004], [P1-005], [P1-006], [P4-001], [P6-001]

**Description:**  
Implement unit tests for all Neuron AI components to meet 80%+ coverage requirement.

**Test Coverage Areas:**

**1. Provider Tests:**
- Factory instantiation for each provider
- Provider capabilities
- Configuration validation
- Error handling for invalid config
- Fallback logic

**2. Agent Tests:**
- Agent initialization
- Provider assignment
- Tool registration
- Memory integration
- Message processing
- Rate limiting
- Usage tracking

**3. Workflow Tests:**
- Node execution
- Event handling
- State management
- Error recovery
- Timeout handling
- Interrupt creation

**4. Storage Tests:**
- Session persistence (all drivers)
- Multi-driver support
- Expiration logic
- Data serialization

**5. Cost Tests:**
- Cost calculation accuracy
- Token counting
- Provider pricing
- Aggregation logic

**Test Framework:**
- Use existing test structure
- Follow project testing patterns
- Mock external services
- Use fixtures for test data

**Tasks:**
- Create test infrastructure
- Create fixtures and factories
- Implement provider tests
- Implement agent tests
- Implement workflow tests
- Implement storage tests
- Implement cost tests
- Run coverage analysis
- Achieve 80%+ coverage
- Document testing approach
- Add tests to CI/CD

**Coverage Requirements:**
- Overall: 80%+ (requirement)
- Critical paths: 100%
- Error handling: 90%+
- Happy paths: 95%+

**Acceptance Criteria:**
- 80%+ code coverage
- All critical paths tested
- Tests pass: php tests/run_tests.php
- PHPStan passes
- Tests documented
- Tests run in CI/CD

**Related:**
- Section 9.1 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [QA-002] Create Integration Tests for End-to-End Flows
- **Epic:** Quality Assurance  
- **Complexity:** High  
- **Effort:** 6-8 days  
- **Dependencies:** All components in previous phases

**Description:**  
Create integration tests validating end-to-end workflows with real components.

**Test Scenarios:**

**1. Single Agent Conversation:**
- Create agent with specific provider
- Send message
- Verify response format
- Check session persistence
- Retrieve session later

**2. Multi-Provider Switching:**
- Create agent with Provider A
- Execute conversation
- Switch to Provider B
- Verify agent works with new provider
- Compare responses

**3. Multi-Tenant Isolation:**
- Create two tenants
- Create agents in each
- Execute conversations in each
- Verify data isolation
- Check cost tracking per tenant

**4. Workflow Execution:**
- Create multi-agent workflow
- Execute workflow
- Verify node transitions
- Check state updates
- Validate final result

**5. Session Persistence:**
- Create conversation session
- Add messages
- Close session
- Retrieve and verify
- Test across requests

**6. Interrupt and Resume:**
- Create workflow with approval node
- Execute and hit interrupt
- Verify interrupt storage
- Submit approval
- Verify workflow resumes

**Tasks:**
- Set up integration test environment
- Create test factories
- Implement agent conversation tests
- Implement provider switching tests
- Implement multi-tenant tests
- Implement workflow tests
- Implement session tests
- Implement interrupt tests
- Add tests to CI/CD pipeline
- Document test scenarios

**Test Environment:**
- Use SQLite or dedicated MySQL instance
- Reset database between tests
- Mock external providers (optional)
- Real Redis for session tests

**Acceptance Criteria:**
- All scenarios tested
- Tests pass: composer test
- No flaky tests
- Performance acceptable
- Results reproducible

**Related:**
- Section 9.2 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [QA-003] Implement Load Testing with k6
- **Epic:** Quality Assurance  
- **Complexity:** High  
- **Effort:** 5-6 days  
- **Dependencies:** Complete implementation

**Description:**  
Create load tests to validate performance under sustained and peak loads.

**Load Test Scenarios:**

**1. Sustained Load Test:**
- 100 concurrent users
- 5 minute duration
- Target p50 < 2s, p95 < 5s
- Error rate < 1%
- Expected throughput: > 50 req/s

**2. Peak Load Test:**
- Ramp to 500 concurrent users
- Sustain for 3 minutes
- Verify no errors during peak
- Monitor resource usage

**3. Provider Stress Test:**
- Test each provider individually
- Respect provider rate limits
- Test failover scenarios
- Verify cost calculations

**4. Workflow Load Test:**
- Execute workflows under load
- Multi-node workflows
- Parallel workflow executions
- Interrupt handling under load

**Test Metrics:**
- Response time (p50, p95, p99)
- Throughput (requests/second)
- Error rate
- Resource usage (CPU, memory)
- Database query performance

**Tasks:**
- Create k6 load test script
- Define performance thresholds
- Set up test environment
- Run baseline tests
- Run provider tests
- Analyze results
- Document findings
- Create performance report
- Identify bottlenecks
- Recommend optimizations

**Performance Targets:**
- p50 latency: < 2s
- p95 latency: < 5s
- p99 latency: < 10s
- Error rate: < 1%
- Throughput: > 50 req/s

**Acceptance Criteria:**
- Load tests pass targets
- All providers handle load
- Error handling works under stress
- Performance acceptable for production
- No memory leaks
- Database performs well

**Related:**
- Section 9.3 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [QA-004] Implement Security Audit and Compliance Review
- **Epic:** Quality Assurance  
- **Complexity:** High  
- **Effort:** 6-7 days  
- **Dependencies:** All security-related components

**Description:**  
Complete security and compliance audit of Neuron AI integration.

**Security Review Items:**

**1. Credential Management:**
- Verify AES-256 encryption
- Check no credentials in logs
- Validate access audit trails
- Test credential rotation

**2. Data Privacy:**
- LGPD compliance (Brazil)
- GDPR compliance (EU)
- CCPA compliance (California)
- Data retention policies
- Right to erasure implementation
- Data export functionality

**3. Rate Limiting:**
- IP-based limits working
- Tenant-based quotas enforced
- Provider rate limit handling
- Agent-level rate limiting

**4. Access Control:**
- RBAC properly scoped
- Multi-tenant isolation verified
- Admin operations secured
- API authentication working

**5. Input Validation:**
- Provider configuration validation
- Workflow definition validation
- Message content validation

**Tasks:**
- Schedule security audit
- Review credential storage implementation
- Verify encryption mechanisms
- Test rate limiting
- Validate access controls
- Check data privacy measures
- Review audit logging
- Test compliance features
- Document findings
- Create security report
- Fix issues identified
- Verify fixes

**Compliance Checklist:**
- [ ] Credentials properly encrypted
- [ ] No secrets in logs
- [ ] Access controlled and audited
- [ ] Rate limiting effective
- [ ] Multi-tenant isolation working
- [ ] Data privacy measures in place
- [ ] Encryption in transit (TLS)
- [ ] Input validation comprehensive

**Acceptance Criteria:**
- Security audit passed
- No critical findings
- Compliance verified
- Issues resolved or mitigated
- Security report signed off
- Audit trail complete

**Related:**
- Section 8 in NEURON_AI_INTEGRATION_SPEC.md

---

## Documentation (Cross-Cutting)

### User and Developer Documentation

#### [DOC-001] Create Neuron AI Integration Documentation
- **Epic:** Documentation  
- **Complexity:** Medium  
- **Effort:** 5-6 days  
- **Dependencies:** All implementation tasks

**Description:**  
Create comprehensive documentation for Neuron AI integration.

**Documentation Required:**

**1. Developer Guide (docs/NEURON_INTEGRATION.md):**
- Architecture overview with diagrams
- Component interactions
- Agent development patterns
- Toolkit creation guide
- Workflow creation guide
- Testing approach
- Performance optimization

**2. Agent Development Guide (docs/AGENT_DEVELOPMENT.md):**
- Creating custom agents
- Extending BaseAgent
- Agent lifecycle and hooks
- Tool integration patterns
- Configuration options
- Testing agents
- Code examples

**3. Workflow Guide (docs/WORKFLOW_GUIDE.md):**
- Workflow concepts
- Node types and patterns
- State management
- Creating workflows
- Managing executions
- Human-in-the-loop support
- Error handling
- Code examples

**4. Admin User Guide (Update docs/README.md):**
- Provider management
- Agent configuration
- Workflow management
- Monitoring and observability
- Troubleshooting

**5. API Documentation:**
- OpenAPI/Swagger specification
- Agent API endpoints with examples
- Workflow API endpoints
- Provider API endpoints
- Session management API
- Error codes and responses

**Tasks:**
- Write developer guide
- Write agent development guide
- Write workflow guide
- Update admin user guide
- Create API documentation (OpenAPI)
- Add code examples (PHP, JavaScript, cURL)
- Create migration guide from legacy
- Create troubleshooting section
- Add performance tuning guide
- Publish documentation

**Documentation Standards:**
- Clear and comprehensive
- Code examples tested and working
- Multiple language examples
- Visual diagrams where helpful
- Reviewed and approved
- Easy to search and navigate

**Acceptance Criteria:**
- All sections complete
- Code examples tested
- Clear and comprehensive
- Reviewed and approved by tech lead
- Easy to find information
- Multiple examples per concept

**Related:**
- Section 10 in NEURON_AI_INTEGRATION_SPEC.md

---

#### [DOC-002] Create Migration Guide for Legacy to Neuron AI
- **Epic:** Documentation  
- **Complexity:** Medium  
- **Effort:** 4-5 days  
- **Dependencies:** All implementation tasks

**Description:**  
Create comprehensive guide for migrating from legacy ChatHandler to Neuron AI.

**Guide Sections:**

**1. Overview:**
- What's changing and why
- Benefits of migration
- Timeline and phases
- Risks and mitigation

**2. Backward Compatibility:**
- Legacy mode operation
- Feature flag usage
- Running both systems in parallel
- Gradual migration approach

**3. Data Migration:**
- Agent configuration migration
- Conversation history migration
- Session data migration
- Validation procedures

**4. Testing Strategy:**
- Pre-migration testing
- Parallel execution testing
- A/B testing approach
- Metrics comparison
- Performance validation

**5. Deployment:**
- Rollout strategy (10% → 25% → 50% → 100%)
- Monitoring during rollout
- Rollback procedures
- Communication plan

**6. Troubleshooting:**
- Common issues and solutions
- Log analysis procedures
- Performance debugging
- When to escalate
- When to rollback

**Checklists:**

**Pre-Migration:**
- [ ] Database backups
- [ ] Feature flags ready
- [ ] Monitoring configured
- [ ] Team trained
- [ ] Rollback plan tested

**Per-Agent Migration:**
- [ ] Neuron AI implementation complete
- [ ] Tests passing
- [ ] Parallel execution ready
- [ ] Metrics baseline collected
- [ ] Traffic routing tested

**Post-Migration:**
- [ ] Agent fully migrated
- [ ] Traffic 100% on Neuron AI
- [ ] Metrics stable/improved
- [ ] Legacy code deprecated
- [ ] Documentation updated

**Tasks:**
- Write migration guide
- Create step-by-step procedures
- Add troubleshooting section
- Create pre/post checklists
- Document rollback procedures
- Add code examples
- Create runbooks
- Add metrics comparison template

**Acceptance Criteria:**
- Guide is comprehensive
- All steps documented
- Examples are clear and tested
- Checklists complete
- Reviewed and approved

**Related:**
- Section 7 in NEURON_AI_INTEGRATION_SPEC.md

---

## Summary

| Phase | Component | Count | Total Effort |
|-------|-----------|-------|-------------|
| 1: Foundation | Core Setup | 7 | 18-24 days |
| 2: Pilot | Agent Migration | 2 | 8-11 days |
| 3: Multi-Provider | Provider Support | 4 | 13-17 days |
| 4: Memory | Session Management | 4 | 13-16 days |
| 6: Workflows | Multi-Agent | 4 | 18-22 days |
| 7: Observability | Monitoring | 2 | 9-11 days |
| Cross-Cutting | Testing & Quality | 4 | 25-31 days |
| Documentation | User & Dev Docs | 2 | 9-11 days |
| **TOTAL** | **All Components** | **31** | **113-143 days** |

---

## Next Steps

1. **Create GitHub Issues:** Use the shell script or Python script provided
   - File: `create_issues.py` (Python 3)
   - File: `CREATE_NEURON_ISSUES.sh` (Bash)

2. **Organize by Milestone:** Group issues into GitHub Milestones by phase
   - Milestone: Foundation (Weeks 1-2)
   - Milestone: Pilot (Weeks 3-4)
   - Milestone: Multi-Provider (Weeks 5-6)
   - Milestone: Memory (Weeks 7-8)
   - Milestone: Agent Migration (Weeks 9-12)
   - Milestone: Workflows (Weeks 13-16)
   - Milestone: Observability (Weeks 17-18)

3. **Assign Resources:** Assign issues to team members based on expertise
   - Backend developers: P1, P3, P4, P6, P7, QA
   - Frontend developers: P3-001, P3-003
   - DevOps: P1-001, P1-002, P7, QA-003
   - QA: QA-001, QA-002, QA-003, QA-004

4. **Set Dependencies:** Link issues to track dependencies and blockers

5. **Communicate Timeline:** Share roadmap with stakeholders and team

---

## References

- **Specification:** `docs/specs/NEURON_AI_INTEGRATION_SPEC.md`
- **Project Structure:** `.github/copilot-instructions.md`
- **Contributing Guide:** `docs/CONTRIBUTING.md`
- **Architecture:** `docs/PROJECT_DESCRIPTION.md`

---

**Document Generated:** November 18, 2025  
**Specification Version:** 2.0.0  
**Status:** Ready for Implementation
