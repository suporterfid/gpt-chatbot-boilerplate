#!/bin/bash
# Script to create GitHub issues for Neuron AI Integration Specification

set -e

# Color codes for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Creating GitHub Issues for Neuron AI Integration...${NC}\n"

# Phase 1: Foundation Issues
echo -e "${BLUE}=== Phase 1: Foundation (Weeks 1-2) ===${NC}"

gh issue create \
  --title "Install Neuron AI SDK and Dependencies" \
  --body "## Overview
Install Neuron AI framework and required dependencies to establish foundation for multi-LLM platform.

## Tasks
- [ ] Install neuron-core/neuron-ai ^2.0 via Composer
- [ ] Add inspector-apm/inspector-php ^4.0 dependency
- [ ] Update composer.lock and commit
- [ ] Verify package installation with tests
- [ ] Document dependency versions in README

## Acceptance Criteria
- All packages installed successfully
- No conflicts with existing dependencies
- Tests pass: \`composer run analyze\`
- Documentation updated

## Labels
framework, infrastructure, phase-1" \
  --label "framework,infrastructure,phase-1" \
  --assignee "@me" || true

gh issue create \
  --title "Set up Inspector.dev Integration Configuration" \
  --body "## Overview
Configure Inspector.dev for AI-specific observability and monitoring.

## Tasks
- [ ] Create config/neuron.php configuration file
- [ ] Add INSPECTOR_ENABLED and INSPECTOR_INGESTION_KEY to .env
- [ ] Implement Inspector client initialization
- [ ] Create Inspector::enable/disable toggles
- [ ] Add sampling configuration
- [ ] Document Inspector.dev setup in README

## Acceptance Criteria
- Inspector.dev configured and accessible
- Environment variables properly documented
- Configuration can be toggled via feature flag
- No errors on application startup

## Labels
infrastructure, observability, phase-1" \
  --label "infrastructure,observability,phase-1" || true

gh issue create \
  --title "Create Database Migrations for Multi-LLM Support" \
  --body "## Overview
Add database schema extensions to support multiple LLM providers, workflows, and session management.

## Database Changes Required

### 1. Extend agents table
\`\`\`sql
ALTER TABLE agents ADD COLUMN provider VARCHAR(50) DEFAULT 'openai';
ALTER TABLE agents ADD COLUMN provider_model VARCHAR(100);
ALTER TABLE agents ADD COLUMN provider_config JSON;
ALTER TABLE agents ADD COLUMN agent_type VARCHAR(50) NOT NULL;
ALTER TABLE agents ADD COLUMN agent_class VARCHAR(255);
ALTER TABLE agents ADD COLUMN enable_memory BOOLEAN DEFAULT TRUE;
ALTER TABLE agents ADD COLUMN memory_window INT DEFAULT 50;
ALTER TABLE agents ADD COLUMN session_ttl INT DEFAULT 3600;
ALTER TABLE agents ADD COLUMN temperature DECIMAL(3,2) DEFAULT 0.70;
ALTER TABLE agents ADD COLUMN max_tokens INT DEFAULT 4000;
\`\`\`

### 2. Create providers table
- Provider registry with capabilities
- Supported models and pricing

### 3. Create chat_sessions table
- Session storage and history
- Token and cost tracking

### 4. Create workflows and workflow_executions tables
- Workflow definitions and executions
- Workflow events and interrupts

## Acceptance Criteria
- All migrations run successfully
- Schema aligns with specification
- Backward compatible with existing data
- Migrations can be rolled back

## See Also
- Section 5 in NEURON_AI_INTEGRATION_SPEC.md

## Labels
database, schema, phase-1" \
  --label "database,schema,phase-1" || true

gh issue create \
  --title "Implement ProviderFactory and Multi-Provider Abstraction" \
  --body "## Overview
Create provider abstraction layer to support multiple LLM providers.

## Tasks
- [ ] Create includes/Providers/ProviderFactory.php
- [ ] Implement factory method pattern for provider instantiation
- [ ] Support OpenAI, Anthropic, Gemini, Ollama providers (Phase 1)
- [ ] Add provider validation and error handling
- [ ] Create includes/Providers/ProviderConfig.php
- [ ] Add unit tests for factory
- [ ] Document provider configuration

## Acceptance Criteria
- ProviderFactory creates correct provider instances
- Supports configuration via array or config file
- Proper error handling for unsupported providers
- 80%+ unit test coverage
- Type hints for all methods

## See Also
- Section 3.1.3 in NEURON_AI_INTEGRATION_SPEC.md

## Labels
backend, providers, phase-1" \
  --label "backend,providers,phase-1" || true

gh issue create \
  --title "Create BaseAgent Class Extending Neuron\\Agent" \
  --body "## Overview
Implement base agent class integrating Neuron AI framework with existing project patterns.

## Tasks
- [ ] Create includes/Neuron/Agents/BaseAgent.php
- [ ] Extend Neuron\\Agent class
- [ ] Integrate TenantContext for multi-tenancy
- [ ] Implement provider() method using ProviderFactory
- [ ] Add beforeChat() and afterChat() hooks
- [ ] Implement tracking/logging utilities
- [ ] Add rate limiting checks
- [ ] Create comprehensive unit tests
- [ ] Document agent development patterns

## Requirements
- PSR-12 compliance
- PHPStan Level 9 compliance
- Full type hints
- Comprehensive docblocks

## Acceptance Criteria
- BaseAgent extends Neuron\\Agent correctly
- All abstract methods documented
- Multi-tenancy properly scoped
- Unit tests pass
- 80%+ code coverage

## See Also
- Section 3.2.1 in NEURON_AI_INTEGRATION_SPEC.md

## Labels
backend, agents, phase-1" \
  --label "backend,agents,phase-1" || true

gh issue create \
  --title "Implement AgentRegistry and AgentFactory" \
  --body "## Overview
Create registry and factory for agent instantiation and management.

## Tasks
- [ ] Create includes/Neuron/AgentRegistry.php
  - [ ] register(type, className) method
  - [ ] get(type) method  
  - [ ] listAvailable() method
- [ ] Create includes/Neuron/AgentFactory.php
  - [ ] create(agentId) method loading from DB
  - [ ] createFromConfig(array) method
  - [ ] Proper dependency injection
- [ ] Add initial built-in agents to registry
  - [ ] SupportAgent
  - [ ] SalesAgent
  - [ ] ResearchAgent
- [ ] Create unit tests for both classes
- [ ] Document agent registration process

## Acceptance Criteria
- AgentRegistry stores and retrieves agents correctly
- AgentFactory creates instances with proper dependencies
- Built-in agents auto-registered on startup
- Type-safe implementation
- 80%+ unit test coverage

## See Also
- Section 3.2.2 in NEURON_AI_INTEGRATION_SPEC.md

## Labels
backend, agents, phase-1" \
  --label "backend,agents,phase-1" || true

gh issue create \
  --title "Add Neuron AI Feature Flags and Configuration" \
  --body "## Overview
Implement feature flags to enable gradual rollout of Neuron AI components.

## Configuration Structure
\`\`\`php
'neuron' => [
    'enabled' => env('NEURON_ENABLED', false),
    'legacy_mode' => env('LEGACY_MODE', true),
    'agents' => [...], // Agent registry
    'observability' => [...] // Inspector.dev config
]
\`\`\`

## Tasks
- [ ] Create config/neuron.php with all feature flags
- [ ] Add environment variables to .env template
- [ ] Implement configuration validation on startup
- [ ] Add feature flag checks in request handlers
- [ ] Document configuration options

## Acceptance Criteria
- Neuron AI can be enabled/disabled independently
- Legacy mode works alongside Neuron AI
- Configuration validated on startup
- No errors when flags are disabled

## Labels
configuration, phase-1" \
  --label "configuration,phase-1" || true

# Phase 2: Single Agent Migration
echo -e "\n${BLUE}=== Phase 2: Single Agent Migration (Weeks 3-4) ===${NC}"

gh issue create \
  --title "Migrate First Agent to Neuron AI (Pilot)" \
  --body "## Overview
Migrate a low-risk agent (FAQ/Support) to Neuron AI as proof of concept.

## Tasks
- [ ] Select target agent (recommend FAQ or basic support)
- [ ] Create Neuron AI implementation of agent
- [ ] Set up parallel execution with feature flag
- [ ] Route 10% traffic initially
- [ ] Monitor metrics compared to legacy version
- [ ] Collect performance data (latency, accuracy, cost)
- [ ] Gradually increase traffic to 100%
- [ ] Document lessons learned

## Metrics to Track
- Response latency (p50, p95, p99)
- Error rate
- Cost per request
- User satisfaction scores
- Token usage

## Acceptance Criteria
- Agent fully operational on Neuron AI
- Performance equivalent or better than legacy
- Error rate < 1%
- Cost acceptable (within 10% of legacy)
- Traffic successfully increased to 100%

## Labels
pilot, phase-2, testing" \
  --label "pilot,phase-2,testing" || true

gh issue create \
  --title "Implement Agent Parallel Execution (A/B Testing)" \
  --body "## Overview
Set up infrastructure for running legacy and Neuron AI agents in parallel for comparison.

## Tasks
- [ ] Create parallel execution handler
- [ ] Implement metrics collection for both paths
- [ ] Add traffic splitting logic (feature flag based)
- [ ] Compare response quality and performance
- [ ] Create comparison dashboard/report
- [ ] Document A/B testing methodology

## Acceptance Criteria
- Both implementations execute for comparison
- Metrics collected and comparable
- No impact on user experience
- Traffic splitting works correctly
- Reports generated successfully

## Labels
testing, phase-2" \
  --label "testing,phase-2" || true

# Phase 3: Multi-Provider Support
echo -e "\n${BLUE}=== Phase 3: Multi-Provider Support (Weeks 5-6) ===${NC}"

gh issue create \
  --title "Implement Admin UI Provider Management Page" \
  --body "## Overview
Create admin interface for managing LLM providers and their configurations.

## UI Components Needed
1. **Provider List**
   - Enable/disable toggles
   - Supported models
   - Capabilities display
   
2. **Provider Configuration**
   - API key input (with masking)
   - Default model selection
   - Custom settings per provider
   
3. **Pricing Configuration**
   - Token pricing per provider/model
   - Display in agent selection

## Tasks
- [ ] Create provider management page template
- [ ] Implement provider listing with filters
- [ ] Add enable/disable functionality
- [ ] Build credentials input with validation
- [ ] Add model selection for each provider
- [ ] Implement pricing configuration UI
- [ ] Add success/error notifications
- [ ] Create responsive design

## Acceptance Criteria
- All providers can be managed from UI
- Credentials securely handled (no logging)
- Pricing information configurable
- UI responsive on mobile/tablet
- Form validation working

## Labels
frontend, admin-ui, phase-3" \
  --label "frontend,admin-ui,phase-3" || true

gh issue create \
  --title "Implement Encrypted Credentials Storage" \
  --body "## Overview
Implement secure storage for provider API keys and credentials.

## Requirements
- AES-256-GCM encryption at rest
- Proper key management
- Audit logging of access
- Credential rotation support

## Tasks
- [ ] Create SecureCredentialStorage class
- [ ] Implement encryption/decryption logic
- [ ] Add database table for credentials
- [ ] Implement access audit logging
- [ ] Create credential rotation utilities
- [ ] Add security tests
- [ ] Document credential management

## Acceptance Criteria
- API keys encrypted with AES-256-GCM
- Keys never logged or exposed
- Access audit trails maintained
- Rotation supported
- Security audit passed

## Labels
backend, security, phase-3" \
  --label "backend,security,phase-3" || true

gh issue create \
  --title "Add Provider Selection to Agent Configuration" \
  --body "## Overview
Extend agent configuration UI to support provider and model selection.

## UI Changes
- Provider dropdown (showing only enabled providers)
- Model dropdown (filtered by selected provider)
- Provider-specific settings (temperature, max_tokens, etc.)
- Model pricing display
- Preview of cost per 1K tokens

## Tasks
- [ ] Update agent form to include provider selector
- [ ] Populate models based on provider
- [ ] Add model-specific settings
- [ ] Implement cost calculation preview
- [ ] Add validation for provider/model compatibility
- [ ] Create responsive design

## Acceptance Criteria
- Provider selection works correctly
- Model list updates based on provider
- Settings validated before save
- Cost preview displays accurately
- Mobile responsive

## Labels
frontend, admin-ui, phase-3" \
  --label "frontend,admin-ui,phase-3" || true

gh issue create \
  --title "Implement Cost Tracking Per Provider" \
  --body "## Overview
Add cost tracking and analytics for multi-provider usage.

## Tracking Requirements
- Tokens (prompt + completion) per request
- Provider-specific pricing applied
- Cost aggregation per agent/tenant/provider
- Cost trends and reporting

## Tasks
- [ ] Create cost calculation service
- [ ] Track tokens per provider/model
- [ ] Store costs in database
- [ ] Implement cost aggregation queries
- [ ] Create cost analytics endpoints
- [ ] Add cost to agent execution responses
- [ ] Document pricing configuration

## Database Changes
- Add cost columns to relevant tables
- Add cost tracking events

## Acceptance Criteria
- Costs accurately calculated
- Queryable by provider/agent/tenant/date
- No performance impact
- Costs included in API responses

## Labels
backend, billing, phase-3" \
  --label "backend,billing,phase-3" || true

# Phase 4: Memory Management
echo -e "\n${BLUE}=== Phase 4: Memory Management (Weeks 7-8) ===${NC}"

gh issue create \
  --title "Implement SessionStorage with Multiple Drivers" \
  --body "## Overview
Implement Neuron AI ChatSession storage with support for multiple backends.

## Supported Drivers
1. **Database** (default)
   - Persistent storage in chat_sessions table
   - Suitable for all use cases
   
2. **Redis**
   - Fast in-memory caching
   - TTL support
   - Good for high-traffic scenarios
   
3. **File System**
   - Local file storage
   - Good for development/testing

## Tasks
- [ ] Create SessionStorage base class
- [ ] Implement database driver
- [ ] Implement Redis driver
- [ ] Implement file driver
- [ ] Add driver selection via config
- [ ] Create session expiration logic
- [ ] Add unit tests for each driver
- [ ] Document storage options

## Acceptance Criteria
- All drivers functional
- Session data persists correctly
- TTL/expiration working
- Driver switching seamless
- 80%+ unit test coverage

## Labels
backend, memory, phase-4" \
  --label "backend,memory,phase-4" || true

gh issue create \
  --title "Migrate Existing Conversation Data to ChatSession Format" \
  --body "## Overview
Migrate existing conversation history to new ChatSession storage format.

## Tasks
- [ ] Create migration utility class
- [ ] Extract existing conversation data
- [ ] Transform to ChatSession format
- [ ] Handle legacy session structures
- [ ] Verify data integrity
- [ ] Backfill session metadata
- [ ] Create rollback procedure
- [ ] Add data validation tests

## Acceptance Criteria
- All conversation data migrated
- No data loss
- Rollback tested and working
- Performance acceptable (< 5min for typical installation)
- Data integrity verified

## Labels
backend, migration, phase-4" \
  --label "backend,migration,phase-4" || true

gh issue create \
  --title "Create Session Management API Endpoints" \
  --body "## Overview
Implement REST API endpoints for session management and history retrieval.

## Endpoints Required
- \`GET /api/sessions\` - List user sessions
- \`GET /api/sessions/{id}\` - Get session details
- \`DELETE /api/sessions/{id}\` - Delete session
- \`POST /api/sessions/{id}/clear\` - Clear history
- \`GET /api/sessions/{id}/messages\` - Get message history
- \`POST /api/sessions/{id}/summarize\` - Generate summary

## Tasks
- [ ] Implement endpoint routes
- [ ] Add RBAC permission checks
- [ ] Implement filtering and pagination
- [ ] Add rate limiting
- [ ] Create request validation
- [ ] Add response formatting
- [ ] Create integration tests
- [ ] Document endpoints

## Acceptance Criteria
- All endpoints functional
- Proper authentication/authorization
- Pagination working
- Error handling correct
- Integration tests pass

## Labels
backend, api, phase-4" \
  --label "backend,api,phase-4" || true

gh issue create \
  --title "Implement Cross-Session Context and Memory Retrieval" \
  --body "## Overview
Implement contextual memory retrieval across multiple user sessions.

## Feature Requirements
- Retrieve relevant context from recent sessions
- Optional semantic search using embeddings
- User preference extraction
- Entity recognition and tracking

## Tasks
- [ ] Create ContextualMemory class
- [ ] Implement recent session retrieval
- [ ] Add semantic search option
- [ ] Extract key information from conversations
- [ ] Implement context ranking
- [ ] Add to agent context before execution
- [ ] Create unit tests
- [ ] Document configuration

## Acceptance Criteria
- Relevant context retrieved accurately
- No significant performance impact
- Configurable depth/breadth
- Works with multiple user sessions
- Unit tests pass

## Labels
backend, memory, phase-4" \
  --label "backend,memory,phase-4" || true

# Phase 6: Workflows
echo -e "\n${BLUE}=== Phase 6: Multi-Agent Workflows (Weeks 13-16) ===${NC}"

gh issue create \
  --title "Implement WorkflowEngine for Multi-Agent Orchestration" \
  --body "## Overview
Create workflow engine for orchestrating multi-agent workflows.

## Architecture
- Event-driven workflow execution
- Node-based workflow structure  
- State management across nodes
- Progress tracking and reporting
- Error handling and recovery

## Tasks
- [ ] Create includes/Neuron/WorkflowEngine.php
- [ ] Create base Workflow class
- [ ] Create base Node class
- [ ] Implement event emission
- [ ] Add state management
- [ ] Implement error recovery
- [ ] Add execution tracking
- [ ] Create comprehensive unit tests
- [ ] Document workflow patterns

## Example Workflow
- WelcomeNode
- InformationCollectionNode
- ValidationNode
- CRMIntegrationNode
- SchedulingNode

## Acceptance Criteria
- Workflows execute correctly
- Events handled properly
- State persisted and retrieved
- Error handling robust
- 80%+ unit test coverage

## Labels
backend, workflows, phase-6" \
  --label "backend,workflows,phase-6" || true

gh issue create \
  --title "Create Workflow Database Tables and Schema" \
  --body "## Overview
Create database schema for workflow definitions, executions, events, and interrupts.

## Tables Required
1. **workflows**
   - Workflow definitions
   - Configuration
   
2. **workflow_executions**
   - Execution tracking
   - Status and state
   
3. **workflow_events**
   - Event history
   - Audit trail
   
4. **workflow_interrupts**
   - Human-in-the-loop requests
   - Approval tracking

## Tasks
- [ ] Create migration with all tables
- [ ] Add appropriate indexes
- [ ] Add foreign key constraints
- [ ] Create test fixtures
- [ ] Document schema

## Acceptance Criteria
- All tables created with proper structure
- Relationships defined correctly
- Indexes optimize query performance
- Migration tested and rollbackable

## Labels
database, workflows, phase-6" \
  --label "database,workflows,phase-6" || true

gh issue create \
  --title "Implement Workflow Management REST API" \
  --body "## Overview
Create REST API for workflow CRUD operations and execution management.

## Endpoints Required
- \`POST /api/workflows\` - Create workflow
- \`GET /api/workflows\` - List workflows
- \`GET /api/workflows/{id}\` - Get details
- \`PUT /api/workflows/{id}\` - Update workflow
- \`DELETE /api/workflows/{id}\` - Delete workflow
- \`POST /api/workflows/{id}/execute\` - Execute workflow
- \`GET /api/workflows/executions/{execId}\` - Get status
- \`POST /api/workflows/executions/{execId}/cancel\` - Cancel
- \`GET /api/workflows/executions/{execId}/events\` - Get events

## Tasks
- [ ] Implement API routes
- [ ] Add request validation
- [ ] Add RBAC checks
- [ ] Implement response formatting
- [ ] Add error handling
- [ ] Create integration tests
- [ ] Document API
- [ ] Add rate limiting

## Acceptance Criteria
- All endpoints functional
- Proper error responses
- Pagination working
- Integration tests pass

## Labels
backend, api, workflows, phase-6" \
  --label "backend,api,workflows,phase-6" || true

gh issue create \
  --title "Implement Human-in-the-Loop Workflow Support" \
  --body "## Overview
Implement workflow interrupts for human approval and decision points.

## Features Required
- Interrupt/pause workflow at decision points
- Store approval request in database
- Resume workflow with decision
- Timeout handling
- Approval API endpoints

## Tasks
- [ ] Create InterruptEvent class
- [ ] Implement approval workflow nodes
- [ ] Add interrupt storage
- [ ] Create approval API endpoints
- [ ] Add approval notifications (webhook)
- [ ] Implement timeout logic
- [ ] Create integration tests
- [ ] Document interrupt patterns

## Endpoints
- \`POST /api/workflows/interrupts/{id}/approve\` - Approve
- \`POST /api/workflows/interrupts/{id}/reject\` - Reject
- \`GET /api/workflows/interrupts\` - List pending

## Acceptance Criteria
- Workflows can pause for approval
- Approvals resume correctly
- Timeouts handled properly
- Audit trail maintained

## Labels
backend, workflows, phase-6" \
  --label "backend,workflows,phase-6" || true

# Phase 7: Observability
echo -e "\n${BLUE}=== Phase 7: Inspector.dev Integration (Weeks 17-18) ===${NC}"

gh issue create \
  --title "Configure Inspector.dev Dashboards and Alerts" \
  --body "## Overview
Set up Inspector.dev dashboards and alerting for production observability.

## Dashboards to Create
1. **Agent Performance Dashboard**
   - Response time trends
   - Token usage per agent
   - Error rates by agent
   - Cost comparison

2. **Workflow Dashboard**
   - Execution success rate
   - Node-level performance
   - Human-in-the-loop wait times

3. **Provider Dashboard**
   - Request distribution
   - Provider latency
   - Cost analysis
   - Error rates per provider

## Alerting Rules
- Error rate > 5%
- Response time > 30s
- Daily tokens > tenant limit
- Workflow failures

## Tasks
- [ ] Create custom dashboards in Inspector.dev
- [ ] Configure alert thresholds
- [ ] Set up alert channels (email, Slack, PagerDuty)
- [ ] Document dashboard usage
- [ ] Create runbooks for alerts
- [ ] Train team on dashboards

## Acceptance Criteria
- All dashboards functional
- Alerts trigger correctly
- Data accurate and up-to-date
- Team trained on usage

## Labels
infrastructure, observability, phase-7" \
  --label "infrastructure,observability,phase-7" || true

gh issue create \
  --title "Implement Automatic Inspector.dev Instrumentation" \
  --body "## Overview
Add automatic instrumentation throughout codebase for Inspector.dev observability.

## Instrumentation Points
1. **Agent Execution**
   - Start/end transactions
   - Add context (agent type, provider, model)
   - Track token usage
   - Record errors

2. **Workflow Execution**
   - Track node execution
   - Record state transitions
   - Monitor performance
   - Log errors

3. **Tool Execution**
   - Track tool calls
   - Record parameters
   - Monitor latency

## Tasks
- [ ] Create ObservableAgent base class
- [ ] Add transaction tracking to agents
- [ ] Add instrumentation to WorkflowEngine
- [ ] Create custom metrics/spans
- [ ] Implement context propagation
- [ ] Add error tracking
- [ ] Create unit tests
- [ ] Document instrumentation patterns

## Acceptance Criteria
- All major operations tracked
- Metrics accurate
- No significant performance impact
- Data properly formatted for Inspector.dev

## Labels
backend, observability, phase-7" \
  --label "backend,observability,phase-7" || true

# Cross-cutting tasks
echo -e "\n${BLUE}=== Cross-Cutting Tasks ===${NC}"

gh issue create \
  --title "Create Comprehensive Unit Tests (80%+ Coverage)" \
  --body "## Overview
Implement unit tests for all Neuron AI components to meet 80%+ coverage requirement.

## Test Coverage Required
1. **Provider Tests**
   - Factory instantiation
   - Provider capabilities
   - Error handling
   - Configuration validation

2. **Agent Tests**
   - Agent initialization
   - Provider assignment
   - Tool registration
   - Message processing
   - Memory integration

3. **Workflow Tests**
   - Node execution
   - Event handling
   - State management
   - Error recovery

4. **Storage Tests**
   - Session persistence
   - Multi-driver support
   - Expiration logic

## Tasks
- [ ] Create test structure and fixtures
- [ ] Implement provider tests
- [ ] Implement agent tests
- [ ] Implement workflow tests
- [ ] Implement storage tests
- [ ] Run coverage analysis
- [ ] Achieve 80%+ coverage
- [ ] Document testing approach

## Acceptance Criteria
- 80%+ overall code coverage
- All critical paths tested
- Tests pass: \`php tests/run_tests.php\`
- PHPStan passes
- Tests documented

## Labels
testing, quality" \
  --label "testing,quality" || true

gh issue create \
  --title "Create Integration Tests for End-to-End Flows" \
  --body "## Overview
Create integration tests validating end-to-end workflows with real components.

## Test Scenarios
1. **Single Agent Conversation**
   - Create agent
   - Send message
   - Verify response
   - Check session persistence

2. **Multi-Provider Switching**
   - Create agent with Provider A
   - Execute conversation
   - Switch to Provider B
   - Verify behavior

3. **Workflow Execution**
   - Create workflow
   - Execute workflow
   - Verify node execution
   - Check final state

4. **Session Persistence**
   - Create session
   - Persist across requests
   - Retrieve context
   - Verify accuracy

## Tasks
- [ ] Set up integration test environment
- [ ] Create test fixtures and factories
- [ ] Implement agent conversation tests
- [ ] Implement provider switching tests
- [ ] Implement workflow execution tests
- [ ] Implement session tests
- [ ] Document test scenarios
- [ ] Add to CI/CD pipeline

## Acceptance Criteria
- All scenarios tested
- Tests pass: \`composer test\`
- Performance acceptable
- Results reproducible

## Labels
testing, integration" \
  --label "testing,integration" || true

gh issue create \
  --title "Implement Load Testing with k6" \
  --body "## Overview
Create load tests to validate performance under sustained and peak loads.

## Load Test Scenarios
1. **Sustained Load Test**
   - 100 concurrent users
   - 5 minute duration
   - Target: p95 latency < 5s

2. **Peak Load Test**
   - Ramp to 500 concurrent users
   - 3 minute sustained
   - Verify no failures

3. **Provider Stress Test**
   - Test each provider with typical load
   - Verify provider limits respected
   - Test failover

## Tasks
- [ ] Create k6 load test script
- [ ] Define performance thresholds
- [ ] Set up test environment
- [ ] Run baseline tests
- [ ] Run provider tests
- [ ] Analyze results
- [ ] Document findings
- [ ] Create performance report

## Baseline Targets
- p50 latency: < 2s
- p95 latency: < 5s
- Error rate: < 1%
- Throughput: > 50 req/s

## Acceptance Criteria
- Load tests pass targets
- All providers handle load
- Error handling works under stress
- Performance acceptable for production

## Labels
testing, performance" \
  --label "testing,performance" || true

gh issue create \
  --title "Implement Security Audit and Compliance Review" \
  --body "## Overview
Complete security and compliance audit of Neuron AI integration.

## Security Review Items
1. **Credential Management**
   - [ ] AES-256 encryption verified
   - [ ] No credentials in logs
   - [ ] Access audit trails maintained

2. **Data Privacy**
   - [ ] LGPD compliance
   - [ ] GDPR compliance
   - [ ] CCPA compliance
   - [ ] Data retention policies

3. **Rate Limiting**
   - [ ] IP-based limits working
   - [ ] Tenant-based quotas enforced
   - [ ] Provider limits respected

4. **Access Control**
   - [ ] RBAC properly scoped
   - [ ] Multi-tenant isolation verified
   - [ ] Admin operations secured

## Tasks
- [ ] Schedule security audit
- [ ] Review credential storage
- [ ] Verify rate limiting
- [ ] Test access controls
- [ ] Validate data privacy measures
- [ ] Create security report
- [ ] Document findings
- [ ] Fix issues identified

## Acceptance Criteria
- Security audit passed
- No critical findings
- Compliance verified
- Issues resolved or mitigated

## Labels
security, compliance" \
  --label "security,compliance" || true

gh issue create \
  --title "Create Neuron AI Integration Documentation" \
  --body "## Overview
Create comprehensive documentation for Neuron AI integration.

## Documentation Required

### 1. Developer Guide (docs/NEURON_INTEGRATION.md)
- Architecture overview
- Agent development patterns
- Toolkit creation guide
- Workflow creation guide
- Testing approach

### 2. Agent Development Guide (docs/AGENT_DEVELOPMENT.md)
- Creating custom agents
- Agent lifecycle
- Tool integration
- Configuration options
- Testing agents

### 3. Workflow Guide (docs/WORKFLOW_GUIDE.md)
- Workflow concepts
- Node types and patterns
- Creating workflows
- Managing executions
- Human-in-the-loop

### 4. Admin User Guide
- Provider management
- Agent configuration
- Workflow management
- Monitoring and observability

### 5. API Documentation
- OpenAPI/Swagger spec
- Agent API endpoints
- Workflow API endpoints
- Provider API endpoints

## Tasks
- [ ] Write developer guide
- [ ] Write agent development guide
- [ ] Write workflow guide
- [ ] Update admin user guide
- [ ] Create API documentation
- [ ] Add code examples
- [ ] Create migration guide
- [ ] Publish documentation

## Acceptance Criteria
- All sections complete
- Code examples tested
- Clear and comprehensive
- Reviewed and approved

## Labels
documentation" \
  --label "documentation" || true

gh issue create \
  --title "Create Migration Guide for Legacy to Neuron AI" \
  --body "## Overview
Create comprehensive guide for migrating from legacy ChatHandler to Neuron AI.

## Guide Sections
1. **Overview**
   - What's changing
   - Why migrate
   - Benefits

2. **Backward Compatibility**
   - Legacy mode operation
   - Feature flag usage
   - Gradual migration approach

3. **Data Migration**
   - Agent configuration migration
   - Conversation history migration
   - Session data migration

4. **Testing**
   - Parallel execution
   - A/B testing
   - Performance comparison

5. **Deployment**
   - Rollout strategy
   - Rollback procedures
   - Monitoring

6. **Troubleshooting**
   - Common issues
   - How to debug
   - When to rollback

## Tasks
- [ ] Write migration guide
- [ ] Create step-by-step procedures
- [ ] Add troubleshooting section
- [ ] Create runbooks
- [ ] Add code examples
- [ ] Document rollback procedures
- [ ] Create checklists

## Acceptance Criteria
- Guide is comprehensive
- All steps documented
- Examples are clear
- Reviewed and approved

## Labels
documentation, migration" \
  --label "documentation,migration" || true

echo -e "\n${GREEN}✓ GitHub issues created successfully!${NC}\n"
echo "View all issues at: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues"
