# Product Specification: Multi-LLM Platform Enhancement with Neuron AI Integration

**Project:** GPT Chatbot Boilerplate → Universal AI Agent Platform  
**Version:** 2.0.0  
**Date:** November 18, 2025  
**Status:** Draft Specification  
**Author:** Technical Architecture Team  

***

## Executive Summary

This specification outlines the transformation of the GPT Chatbot Boilerplate from a single-vendor OpenAI chatbot platform into a **Universal AI Agent Platform** powered by Neuron AI framework. The enhanced platform will support multiple LLM providers, structured agent orchestration, advanced memory management, and multi-agent workflows while preserving existing production features (multi-tenancy, RBAC, observability, WhatsApp integration).

**Key Objectives:**
- Enable multi-LLM provider support (OpenAI, Anthropic, Gemini, Ollama, Mistral, AWS Bedrock, etc.)
- Implement structured agent architecture using Neuron AI framework
- Introduce native memory management and session persistence
- Add multi-agent workflow capabilities with event-driven orchestration
- Maintain backward compatibility with existing API endpoints
- Enhance observability with AI-specific monitoring via Inspector.dev
- Zero downtime migration path from current architecture

***

## Table of Contents

1. [Background & Motivation](#1-background--motivation)
2. [System Architecture](#2-system-architecture)
3. [Feature Specifications](#3-feature-specifications)
4. [API Specifications](#4-api-specifications)
5. [Data Models](#5-data-models)
6. [Integration Requirements](#6-integration-requirements)
7. [Migration Strategy](#7-migration-strategy)
8. [Security & Compliance](#8-security--compliance)
9. [Testing Requirements](#9-testing-requirements)
10. [Documentation Requirements](#10-documentation-requirements)
11. [Timeline & Milestones](#11-timeline--milestones)
12. [Success Metrics](#12-success-metrics)

***

## 1. Background & Motivation

### 1.1 Current State Analysis

**Existing Capabilities:**
- Single LLM provider (OpenAI only)
- Manual agent configuration via database records
- Custom memory management with PHP sessions/file storage
- Basic conversation handling via `ChatHandler.php`
- Production features: multi-tenancy, RBAC, webhooks, WhatsApp integration
- Observability: Prometheus/Grafana metrics, structured logging

**Identified Limitations:**
1. **Vendor Lock-in:** Hard dependency on OpenAI API
2. **Manual Agent Orchestration:** Agent logic scattered across multiple PHP files
3. **Basic Memory Management:** Custom session handling, no cross-session context
4. **Single-Agent Model:** No support for agent collaboration or workflows
5. **Generic Observability:** Lacks AI-specific insights (token usage per agent, decision tracking)

### 1.2 Strategic Goals

**Business Objectives:**
- **Market Differentiation:** Become multi-provider platform competing with hosted AI solutions
- **Cost Optimization:** Enable provider switching based on cost/performance trade-offs
- **Enterprise Readiness:** Structured agent patterns for team development
- **Scalability:** Multi-agent workflows for complex business processes

**Technical Objectives:**
- **Maintainability:** Structured agent classes replacing scattered orchestration logic
- **Type Safety:** Leverage PHP 8.1+ features with 100% type coverage
- **Production Stability:** Native observability and error handling for AI operations
- **Developer Experience:** Clear patterns for creating and deploying new agents

### 1.3 Why Neuron AI Framework

**Selection Rationale:**
1. **Architectural Alignment:** Agent-based model matches existing agent concept in project
2. **Memory Management:** Native ChatHistory/ChatSession eliminates custom implementation
3. **Production Focus:** Built for production with Inspector.dev observability integration
4. **Multi-Provider Native:** Clean abstraction over 10+ LLM providers
5. **Type Safety:** PHPStan Level 9 compliant, matches project quality standards
6. **Multi-Agent Support:** Event-driven workflows for orchestration

***

## 2. System Architecture

### 2.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Presentation Layer                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ Admin UI     │  │ Chat Widget  │  │ WhatsApp     │         │
│  │ (Existing)   │  │ (Enhanced)   │  │ Integration  │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
└─────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│                      API Gateway Layer                          │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  chat-unified.php (Enhanced with Agent Routing)          │  │
│  │  admin-api.php (Extended with Agent CRUD)                │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│                    Agent Orchestration Layer (NEW)              │
│  ┌────────────────────────────────────────────────────────┐    │
│  │  AgentFactory (creates agents based on tenant config)  │    │
│  │  AgentRegistry (maps agent types to classes)           │    │
│  │  WorkflowEngine (orchestrates multi-agent workflows)   │    │
│  └────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│                      Neuron AI Core                             │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ Agent Base  │  │ Memory Mgmt  │  │ Tool System  │          │
│  │ Classes     │  │ ChatSession  │  │ Toolkits     │          │
│  └─────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│                      LLM Provider Layer                         │
│  ┌──────┐ ┌──────────┐ ┌────────┐ ┌────────┐ ┌──────────┐    │
│  │OpenAI│ │Anthropic │ │ Gemini │ │ Ollama │ │AWS Bedrock│    │
│  └──────┘ └──────────┘ └────────┘ └────────┘ └──────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────────┐
│                  Infrastructure Layer (Existing)                │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────┐      │
│  │ Database │ │ Redis    │ │ Metrics  │ │ Inspector.dev│      │
│  │ Multi-DB │ │ Cache    │ │Prometheus│ │ (NEW)        │      │
│  └──────────┘ └──────────┘ └──────────┘ └──────────────┘      │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Component Interactions

**Request Flow Example (Single Agent):**
```
1. User sends message via Chat Widget
   ↓
2. API Gateway (chat-unified.php) validates request
   ↓
3. TenantContext resolves tenant and agent configuration
   ↓
4. AgentFactory creates appropriate Agent instance (Neuron AI)
   ↓
5. Agent executes with:
   - Provider selection based on config
   - Memory retrieval from ChatSession
   - Tool execution if needed
   ↓
6. Response streamed back via SSE
   ↓
7. Inspector.dev records execution details
   ↓
8. Metrics updated (tokens, latency, cost)
```

**Multi-Agent Workflow Example:**
```
1. User requests complex task (e.g., "Research competitors and create report")
   ↓
2. WorkflowEngine receives task
   ↓
3. ResearchAgent executes:
   - Searches web for competitor info
   - Emits DataCollectedEvent
   ↓
4. AnalysisAgent receives event:
   - Analyzes collected data
   - Emits AnalysisCompleteEvent
   ↓
5. ReportAgent receives event:
   - Generates formatted report
   - Emits ReportGeneratedEvent
   ↓
6. Final report returned to user
```

### 2.3 Directory Structure (New Components)

```
/
├── includes/
│   ├── Neuron/                    # NEW: Neuron AI integration
│   │   ├── Agents/                # Agent implementations
│   │   │   ├── BaseAgent.php      # Base class extending Neuron\Agent
│   │   │   ├── SupportAgent.php
│   │   │   ├── SalesAgent.php
│   │   │   ├── ResearchAgent.php
│   │   │   └── CustomAgent.php    # User-definable agents
│   │   ├── Workflows/             # Multi-agent workflows
│   │   │   ├── BaseWorkflow.php
│   │   │   ├── CustomerOnboardingWorkflow.php
│   │   │   └── DeepResearchWorkflow.php
│   │   ├── Toolkits/              # Custom toolkits
│   │   │   ├── DatabaseToolkit.php
│   │   │   ├── WhatsAppToolkit.php
│   │   │   └── CRMToolkit.php
│   │   ├── AgentFactory.php       # Creates agent instances
│   │   ├── AgentRegistry.php      # Maps types to classes
│   │   └── WorkflowEngine.php     # Orchestrates workflows
│   ├── Providers/                 # NEW: Multi-provider abstraction
│   │   ├── ProviderFactory.php
│   │   ├── ProviderConfig.php
│   │   └── ProviderRegistry.php
│   ├── ChatHandler.php            # MODIFIED: Simplified orchestration
│   ├── AgentService.php           # MODIFIED: Extended for Neuron agents
│   └── [existing files...]
├── config/
│   └── neuron.php                 # Neuron AI configuration
├── database/
│   └── migrations/
│       ├── 2025_11_19_add_agent_provider_support.php
│       ├── 2025_11_19_add_workflow_tables.php
│       └── 2025_11_19_add_session_storage.php
└── docs/
    ├── NEURON_INTEGRATION.md      # Integration guide
    ├── AGENT_DEVELOPMENT.md       # Creating custom agents
    └── WORKFLOW_GUIDE.md          # Multi-agent workflows
```

***

## 3. Feature Specifications

### 3.1 Multi-LLM Provider Support

**Feature ID:** F-001  
**Priority:** P0 (Critical Path)  
**Complexity:** Medium  

#### 3.1.1 Provider Configuration

**Supported Providers (Phase 1):**
- OpenAI (GPT-4o, GPT-4o-mini, o1-preview, o1-mini)
- Anthropic (Claude 3.5 Sonnet, Claude 3 Haiku, Claude 3 Opus)
- Google Gemini (Gemini 2.0 Flash, Gemini 1.5 Pro)
- Ollama (Local models: Llama 3.2, Mistral, etc.)

**Supported Providers (Phase 2):**
- Mistral AI
- AWS Bedrock Runtime
- Azure OpenAI
- HuggingFace
- Deepseek
- Grok (xAI)

#### 3.1.2 Provider Selection Logic

**Configuration Levels (Priority Order):**
1. **Agent-Level Config:** Specific agent configured with provider
2. **Tenant-Level Default:** Tenant's default provider
3. **System-Level Fallback:** Platform default provider

**Database Schema Extension:**
```sql
-- Extend agents table
ALTER TABLE agents ADD COLUMN provider VARCHAR(50) DEFAULT 'openai';
ALTER TABLE agents ADD COLUMN provider_model VARCHAR(100);
ALTER TABLE agents ADD COLUMN provider_config JSON; -- API keys, endpoints, etc.

-- Extend tenants table
ALTER TABLE tenants ADD COLUMN default_provider VARCHAR(50) DEFAULT 'openai';
ALTER TABLE tenants ADD COLUMN provider_credentials JSON; -- Encrypted credentials

-- New providers table
CREATE TABLE providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    requires_api_key BOOLEAN DEFAULT TRUE,
    supported_models JSON,
    default_model VARCHAR(100),
    capabilities JSON, -- streaming, function_calling, vision, etc.
    pricing JSON, -- cost per token
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.1.3 Provider Implementation

**ProviderFactory Class:**
```php
class ProviderFactory
{
    public function create(
        string $provider, 
        string $model, 
        array $config
    ): AIProviderInterface {
        return match($provider) {
            'openai' => new OpenAI(
                key: $config['api_key'] ?? $_ENV['OPENAI_API_KEY'],
                model: $model
            ),
            'anthropic' => new Anthropic(
                key: $config['api_key'] ?? $_ENV['ANTHROPIC_API_KEY'],
                model: $model
            ),
            'gemini' => new GeminiOpenAI(
                key: $config['api_key'] ?? $_ENV['GEMINI_API_KEY'],
                model: $model
            ),
            'ollama' => new Ollama(
                url: $config['url'] ?? 'http://localhost:11434',
                model: $model
            ),
            default => throw new UnsupportedProviderException($provider)
        };
    }
}
```

#### 3.1.4 Admin UI Extensions

**New Features:**
1. **Provider Management Page:**
   - Enable/disable providers system-wide
   - Configure default models per provider
   - Set pricing information for cost tracking

2. **Agent Configuration Enhancement:**
   - Provider selection dropdown (enabled providers only)
   - Model selection based on provider capabilities
   - Provider-specific settings (temperature, max_tokens, etc.)

3. **Tenant Provider Settings:**
   - Default provider selection for tenant
   - Provider credentials management (encrypted)
   - Cost allocation tracking per provider

**UI Mockup (Provider Selection):**
```
┌────────────────────────────────────────────┐
│ Agent Configuration                        │
├────────────────────────────────────────────┤
│ Name: Customer Support Agent               │
│ Description: Handles customer inquiries    │
│                                            │
│ LLM Provider:                              │
│ ┌──────────────────────────────────────┐  │
│ │ OpenAI          [Selected]           │  │
│ │ Anthropic       [ ]                  │  │
│ │ Google Gemini   [ ]                  │  │
│ │ Ollama (Local)  [ ]                  │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ Model:                                     │
│ ┌──────────────────────────────────────┐  │
│ │ gpt-4o         $10.00/1M tokens      │  │
│ │ gpt-4o-mini    $0.60/1M tokens ✓     │  │
│ │ o1-preview     $30.00/1M tokens      │  │
│ └──────────────────────────────────────┘  │
│                                            │
│ Temperature: [0.7]  Max Tokens: [4000]    │
│                                            │
│ [Save Configuration]                       │
└────────────────────────────────────────────┘
```

***

### 3.2 Structured Agent System (Neuron AI Integration)

**Feature ID:** F-002  
**Priority:** P0 (Critical Path)  
**Complexity:** High  

#### 3.2.1 Agent Architecture

**Base Agent Class:**
```php
namespace GPTChatbot\Neuron\Agents;

use NeuronAI\Agent\Agent;
use NeuronAI\Provider\AIProviderInterface;

abstract class BaseAgent extends Agent
{
    protected TenantContext $tenantContext;
    protected AgentConfigResolver $configResolver;
    protected array $agentConfig;
    
    public function __construct(
        TenantContext $tenantContext,
        AgentConfigResolver $configResolver,
        array $agentConfig = []
    ) {
        $this->tenantContext = $tenantContext;
        $this->configResolver = $configResolver;
        $this->agentConfig = $agentConfig;
        
        parent::__construct();
    }
    
    protected function provider(): AIProviderInterface
    {
        $providerConfig = $this->configResolver->resolveProvider(
            $this->agentConfig,
            $this->tenantContext
        );
        
        return ProviderFactory::create(
            $providerConfig['provider'],
            $providerConfig['model'],
            $providerConfig['config']
        );
    }
    
    abstract protected function instructions(): string;
    abstract protected function tools(): array;
    
    // Hooks for existing functionality
    protected function beforeChat(UserMessage $message): void
    {
        // Rate limiting check
        if (!$this->checkRateLimit()) {
            throw new RateLimitException();
        }
        
        // Usage tracking
        $this->trackUsageStart();
    }
    
    protected function afterChat($response): void
    {
        // Track tokens and cost
        $this->trackUsageEnd($response);
        
        // Webhook dispatch
        $this->dispatchChatEvent($response);
    }
}
```

**Example Implementation (Support Agent):**
```php
namespace GPTChatbot\Neuron\Agents;

class SupportAgent extends BaseAgent
{
    protected function instructions(): string
    {
        // Load from database or config
        return $this->configResolver->getInstructions($this->agentConfig)
            ?? "You are a helpful customer support assistant.";
    }
    
    protected function tools(): array
    {
        $tools = [];
        
        // Add database toolkit if configured
        if ($this->agentConfig['enable_database_access'] ?? false) {
            $tools[] = DatabaseToolkit::make(
                DB::connection()->getPdo()
            );
        }
        
        // Add CRM toolkit
        if ($crmConfig = $this->tenantContext->getCRMConfig()) {
            $tools[] = new CRMToolkit($crmConfig);
        }
        
        // Add custom tools from config
        foreach ($this->agentConfig['custom_tools'] ?? [] as $toolClass) {
            $tools[] = new $toolClass($this->tenantContext);
        }
        
        return $tools;
    }
}
```

#### 3.2.2 Agent Registry & Factory

**AgentRegistry Class:**
```php
class AgentRegistry
{
    private array $registry = [];
    
    public function register(string $type, string $className): void
    {
        if (!is_subclass_of($className, BaseAgent::class)) {
            throw new InvalidAgentException();
        }
        
        $this->registry[$type] = $className;
    }
    
    public function get(string $type): string
    {
        return $this->registry[$type] 
            ?? throw new AgentNotFoundException($type);
    }
    
    public function listAvailable(): array
    {
        return array_keys($this->registry);
    }
}
```

**AgentFactory Class:**
```php
class AgentFactory
{
    public function __construct(
        private AgentRegistry $registry,
        private TenantContext $tenantContext,
        private AgentConfigResolver $configResolver
    ) {}
    
    public function create(int $agentId): BaseAgent
    {
        // Load agent config from database
        $config = $this->configResolver->loadConfig($agentId);
        
        // Get agent class from registry
        $className = $this->registry->get($config['type']);
        
        // Instantiate with dependencies
        return new $className(
            $this->tenantContext,
            $this->configResolver,
            $config
        );
    }
    
    public function createFromConfig(array $config): BaseAgent
    {
        $className = $this->registry->get($config['type']);
        
        return new $className(
            $this->tenantContext,
            $this->configResolver,
            $config
        );
    }
}
```

#### 3.2.3 Integration with Existing System

**Modified chat-unified.php:**
```php
<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use GPTChatbot\Neuron\AgentFactory;

// Existing tenant resolution
$tenantContext = TenantContext::initialize();

// Legacy mode detection
$useLegacyMode = $_POST['legacy_mode'] ?? false;

if ($useLegacyMode) {
    // Existing ChatHandler flow (backward compatibility)
    $handler = new ChatHandler($config);
    $response = $handler->handleRequest($_POST);
    echo $response;
    exit;
}

// New Neuron AI flow
try {
    $agentId = $_POST['agent_id'] 
        ?? AgentService::getDefaultAgentId($tenantContext);
    
    $factory = new AgentFactory(
        app(AgentRegistry::class),
        $tenantContext,
        app(AgentConfigResolver::class)
    );
    
    $agent = $factory->create($agentId);
    
    // Load or create session
    $sessionId = $_POST['conversation_id'] ?? null;
    if ($sessionId && $sessionData = SessionStorage::load($sessionId)) {
        $agent->withSession(ChatSession::fromArray($sessionData));
    }
    
    // Execute chat
    $message = new UserMessage($_POST['message']);
    $response = $agent->chat($message);
    
    // Persist session
    SessionStorage::save($sessionId, $agent->getSession()->toArray());
    
    // Return response
    header('Content-Type: application/json');
    echo json_encode([
        'response' => $response,
        'tokens' => $agent->getSession()->getTotalTokens(),
        'provider' => $agent->getProviderName(),
        'model' => $agent->getModelName()
    ]);
    
} catch (Exception $e) {
    ErrorHandler::handle($e);
}
```

#### 3.2.4 Custom Agent Development

**Developer Workflow:**

1. **Create Agent Class:**
   ```bash
   php artisan neuron:make-agent CustomerOnboardingAgent
   ```

2. **Implement Required Methods:**
   ```php
   namespace App\Neuron\Agents;
   
   class CustomerOnboardingAgent extends BaseAgent
   {
       protected function instructions(): string
       {
           return <<<INSTRUCTIONS
           You are a customer onboarding specialist.
           
           Your responsibilities:
           1. Welcome new customers
           2. Collect required information
           3. Schedule onboarding calls
           4. Answer product questions
           
           Always be friendly and patient.
           INSTRUCTIONS;
       }
       
       protected function tools(): array
       {
           return [
               new CalendarToolkit($this->tenantContext),
               new CRMToolkit($this->tenantContext),
               DatabaseToolkit::make(DB::getPdo())
           ];
       }
   }
   ```

3. **Register in System:**
   ```php
   // config/neuron.php
   return [
       'agents' => [
           'customer_onboarding' => CustomerOnboardingAgent::class,
           'support' => SupportAgent::class,
           'sales' => SalesAgent::class,
       ]
   ];
   ```

4. **Configure via Admin UI:**
   - Select agent type from registry
   - Configure provider and model
   - Set agent-specific parameters
   - Test with sample conversations

***

### 3.3 Native Memory Management

**Feature ID:** F-003  
**Priority:** P0 (Critical Path)  
**Complexity:** Medium  

#### 3.3.1 Memory Architecture

**Neuron AI ChatSession Integration:**
```php
class SessionStorage
{
    private string $storageDriver; // 'database', 'redis', 'file'
    
    public function save(string $sessionId, array $sessionData): void
    {
        $serialized = json_encode($sessionData);
        
        switch ($this->storageDriver) {
            case 'database':
                DB::table('chat_sessions')->updateOrInsert(
                    ['session_id' => $sessionId],
                    [
                        'data' => $serialized,
                        'updated_at' => now()
                    ]
                );
                break;
                
            case 'redis':
                Redis::setex(
                    "session:{$sessionId}",
                    3600, // 1 hour TTL
                    $serialized
                );
                break;
                
            case 'file':
                file_put_contents(
                    storage_path("sessions/{$sessionId}.json"),
                    $serialized
                );
                break;
        }
    }
    
    public function load(string $sessionId): ?array
    {
        $serialized = match($this->storageDriver) {
            'database' => DB::table('chat_sessions')
                ->where('session_id', $sessionId)
                ->value('data'),
            'redis' => Redis::get("session:{$sessionId}"),
            'file' => @file_get_contents(
                storage_path("sessions/{$sessionId}.json")
            )
        };
        
        return $serialized ? json_decode($serialized, true) : null;
    }
    
    public function delete(string $sessionId): void
    {
        // Implement deletion logic per driver
    }
}
```

#### 3.3.2 Database Schema

```sql
CREATE TABLE chat_sessions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    tenant_id INT NOT NULL,
    agent_id INT NOT NULL,
    user_id INT NULL,
    data JSON NOT NULL,
    message_count INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    INDEX idx_tenant_agent (tenant_id, agent_id),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Session metadata for analytics
CREATE TABLE session_metadata (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL,
    key VARCHAR(100) NOT NULL,
    value TEXT,
    
    INDEX idx_session (session_id),
    FOREIGN KEY (session_id) REFERENCES chat_sessions(session_id) ON DELETE CASCADE
);
```

#### 3.3.3 Session Management Features

**Automatic Features:**
- Message history tracking
- Token usage aggregation
- Conversation summarization (when exceeding token limits)
- Automatic pruning of old sessions

**API Endpoints:**
```
GET    /api/sessions                      # List sessions for current user/tenant
GET    /api/sessions/{id}                 # Get session details
DELETE /api/sessions/{id}                 # Delete session
POST   /api/sessions/{id}/clear           # Clear history but keep session
GET    /api/sessions/{id}/messages        # Get message history
POST   /api/sessions/{id}/summarize       # Generate summary
```

#### 3.3.4 Cross-Session Context

**Implementation:**
```php
class ContextualMemory
{
    public function retrieveRelevantContext(
        string $userId,
        string $currentQuery
    ): array {
        // Get recent sessions for user
        $recentSessions = DB::table('chat_sessions')
            ->where('user_id', $userId)
            ->where('updated_at', '>=', now()->subDays(7))
            ->limit(10)
            ->get();
        
        // Extract key information
        $context = [];
        foreach ($recentSessions as $session) {
            $data = json_decode($session->data, true);
            
            // Extract user preferences, mentioned entities, etc.
            $context[] = $this->extractKeyInfo($data);
        }
        
        // Optionally: Use embeddings for semantic search
        if ($this->useSemanticSearch) {
            $context = $this->rankByRelevance($context, $currentQuery);
        }
        
        return array_slice($context, 0, 5); // Top 5 relevant pieces
    }
}
```

***

### 3.4 Multi-Agent Workflows

**Feature ID:** F-004  
**Priority:** P1 (High Priority)  
**Complexity:** High  

#### 3.4.1 Workflow Architecture

**Event-Driven Workflow System:**
```php
namespace GPTChatbot\Neuron\Workflows;

use NeuronAI\Workflow\Workflow;
use NeuronAI\Workflow\WorkflowState;

abstract class BaseWorkflow extends Workflow
{
    protected TenantContext $tenantContext;
    
    public function __construct(
        TenantContext $tenantContext,
        WorkflowState $initialState
    ) {
        $this->tenantContext = $tenantContext;
        parent::__construct($initialState);
    }
    
    abstract protected function nodes(): array;
    
    // Progress tracking
    protected function emitProgress(string $message, float $percentage): void
    {
        $this->emit(new ProgressEvent($message, $percentage));
    }
    
    // Error handling
    protected function handleError(Exception $e): void
    {
        ErrorHandler::log($e);
        $this->emit(new ErrorEvent($e->getMessage()));
    }
}
```

**Example: Customer Onboarding Workflow:**
```php
class CustomerOnboardingWorkflow extends BaseWorkflow
{
    protected function nodes(): array
    {
        return [
            new WelcomeNode(),
            new InformationCollectionNode(),
            new ValidateInformationNode(),
            new CRMIntegrationNode(),
            new SchedulingNode(),
            new ConfirmationNode()
        ];
    }
}

class WelcomeNode extends Node
{
    public function __invoke(StartEvent $event, WorkflowState $state): Event
    {
        $welcomeAgent = WelcomeAgent::make();
        
        $response = $welcomeAgent->chat(
            new UserMessage("New customer: {$state->get('customer_name')}")
        );
        
        $state->set('welcome_message', $response);
        
        return new InformationCollectionEvent();
    }
}

class InformationCollectionNode extends Node
{
    public function __invoke(
        InformationCollectionEvent $event, 
        WorkflowState $state
    ): Event {
        $dataAgent = DataCollectionAgent::make();
        
        // Interactive data collection
        $questions = [
            'company_name',
            'industry',
            'team_size',
            'use_case'
        ];
        
        $collectedData = [];
        foreach ($questions as $field) {
            $response = $dataAgent->chat(
                new UserMessage("Please collect: {$field}")
            );
            $collectedData[$field] = $response;
        }
        
        $state->set('customer_data', $collectedData);
        
        return new ValidationEvent($collectedData);
    }
}
```

#### 3.4.2 Workflow Management

**Database Schema:**
```sql
CREATE TABLE workflows (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    class_name VARCHAR(255) NOT NULL,
    tenant_id INT NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    configuration JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE workflow_executions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    workflow_id BIGINT NOT NULL,
    execution_id VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    state JSON,
    current_node VARCHAR(255),
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    
    INDEX idx_workflow (workflow_id),
    INDEX idx_status (status),
    INDEX idx_execution_id (execution_id),
    
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE
);

CREATE TABLE workflow_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    execution_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_execution (execution_id)
);
```

#### 3.4.3 Workflow API

**REST Endpoints:**
```
POST   /api/workflows                     # Create workflow
GET    /api/workflows                     # List workflows
GET    /api/workflows/{id}                # Get workflow details
PUT    /api/workflows/{id}                # Update workflow
DELETE /api/workflows/{id}                # Delete workflow

POST   /api/workflows/{id}/execute        # Start workflow execution
GET    /api/workflows/executions/{execId} # Get execution status
POST   /api/workflows/executions/{execId}/cancel # Cancel execution
GET    /api/workflows/executions/{execId}/events # Get execution events
```

**Execution Example:**
```bash
curl -X POST https://api.example.com/api/workflows/123/execute \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "initial_state": {
      "customer_name": "Acme Corp",
      "email": "contact@acme.com"
    }
  }'

# Response
{
  "execution_id": "exec_abc123",
  "status": "running",
  "workflow_id": 123,
  "started_at": "2025-11-18T14:30:00Z",
  "tracking_url": "/api/workflows/executions/exec_abc123"
}
```

#### 3.4.4 Human-in-the-Loop Support

**InterruptEvent Handling:**
```php
class ApprovalNode extends Node
{
    public function __invoke(Event $event, WorkflowState $state): Event
    {
        // Request human approval
        $this->emit(new InterruptEvent(
            type: 'approval_required',
            message: 'Manager approval needed for contract terms',
            data: $state->get('contract_terms'),
            resumeOnApproval: true
        ));
        
        // Workflow pauses here
        // Will resume when approval received via API
        
        if ($state->get('approval_status') === 'approved') {
            return new ContractGenerationEvent();
        } else {
            return new RejectionEvent();
        }
    }
}
```

**Approval API:**
```bash
POST /api/workflows/executions/{execId}/approve
{
  "decision": "approved",
  "notes": "Contract terms are acceptable"
}
```

***

### 3.5 Enhanced Observability (Inspector.dev Integration)

**Feature ID:** F-005  
**Priority:** P1 (High Priority)  
**Complexity:** Medium  

#### 3.5.1 Inspector.dev Setup

**Configuration:**
```php
// config/neuron.php
return [
    'observability' => [
        'enabled' => env('INSPECTOR_ENABLED', true),
        'ingestion_key' => env('INSPECTOR_INGESTION_KEY'),
        'app_name' => env('APP_NAME', 'GPT Chatbot Platform'),
        
        'track' => [
            'agent_executions' => true,
            'workflow_executions' => true,
            'tool_calls' => true,
            'token_usage' => true,
            'errors' => true,
        ],
        
        'sampling' => [
            'rate' => env('INSPECTOR_SAMPLING_RATE', 1.0), // 100%
            'exclude_patterns' => [
                '/health',
                '/metrics'
            ]
        ]
    ]
];
```

#### 3.5.2 Automatic Instrumentation

**Agent Execution Tracking:**
```php
class ObservableAgent extends BaseAgent
{
    protected function beforeChat(UserMessage $message): void
    {
        parent::beforeChat($message);
        
        if (Inspector::isEnabled()) {
            Inspector::startTransaction('agent.chat')
                ->addContext('agent', [
                    'id' => $this->agentConfig['id'],
                    'type' => $this->agentConfig['type'],
                    'provider' => $this->getProviderName(),
                    'model' => $this->getModelName()
                ])
                ->addContext('message', [
                    'length' => strlen($message->content),
                    'user_id' => $this->tenantContext->getUserId(),
                    'tenant_id' => $this->tenantContext->getTenantId()
                ]);
        }
    }
    
    protected function afterChat($response): void
    {
        parent::afterChat($response);
        
        if (Inspector::isEnabled()) {
            Inspector::currentTransaction()
                ->addContext('response', [
                    'length' => strlen($response),
                    'tokens' => $this->getSession()->getTotalTokens(),
                    'cost' => $this->calculateCost()
                ])
                ->end();
        }
    }
}
```

**Workflow Execution Tracking:**
```php
class InspectorWorkflowEngine extends WorkflowEngine
{
    public function execute(Workflow $workflow, array $initialState): string
    {
        $transaction = Inspector::startTransaction('workflow.execute')
            ->addContext('workflow', [
                'name' => $workflow->getName(),
                'nodes' => count($workflow->nodes()),
                'initial_state' => $initialState
            ]);
        
        try {
            $executionId = parent::execute($workflow, $initialState);
            
            $transaction
                ->addContext('result', ['execution_id' => $executionId])
                ->setResult('success')
                ->end();
            
            return $executionId;
            
        } catch (Exception $e) {
            $transaction
                ->setResult('error')
                ->reportException($e)
                ->end();
            
            throw $e;
        }
    }
}
```

#### 3.5.3 Custom Dashboards

**Pre-built Dashboard Metrics:**

1. **Agent Performance Dashboard:**
   - Average response time per agent
   - Token usage trends
   - Cost per conversation
   - Error rates by agent type
   - Provider performance comparison

2. **Workflow Dashboard:**
   - Workflow execution success rate
   - Average workflow duration
   - Node-level performance
   - Human-in-the-loop wait times

3. **Provider Dashboard:**
   - Request distribution by provider
   - Provider latency comparison
   - Provider cost analysis
   - Provider error rates

4. **Tenant Dashboard:**
   - Usage by tenant
   - Cost allocation
   - Rate limit incidents
   - Agent usage patterns

#### 3.5.4 Alerting Rules

**Configured Alerts:**
```yaml
alerts:
  - name: "High Error Rate"
    condition: "error_rate > 0.05" # 5%
    window: "5m"
    channels: ["email", "slack"]
    
  - name: "Provider Timeout"
    condition: "response_time > 30s"
    window: "1m"
    channels: ["pagerduty"]
    
  - name: "Token Budget Exceeded"
    condition: "daily_tokens > tenant_limit"
    window: "1d"
    channels: ["email"]
    
  - name: "Workflow Failure"
    condition: "workflow_status == 'failed'"
    window: "immediate"
    channels: ["slack", "email"]
```

***

## 4. API Specifications

### 4.1 Agent API

#### 4.1.1 List Agents

**Endpoint:** `GET /api/agents`

**Query Parameters:**
- `tenant_id` (optional): Filter by tenant
- `type` (optional): Filter by agent type
- `provider` (optional): Filter by LLM provider
- `is_enabled` (optional): Filter by enabled status
- `page` (default: 1)
- `per_page` (default: 20)

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "name": "Customer Support Agent",
      "type": "support",
      "description": "Handles customer inquiries and support tickets",
      "provider": "openai",
      "model": "gpt-4o-mini",
      "is_enabled": true,
      "is_default": false,
      "configuration": {
        "temperature": 0.7,
        "max_tokens": 4000,
        "tools": ["database", "crm"]
      },
      "usage_stats": {
        "total_conversations": 1543,
        "total_tokens": 3425678,
        "avg_response_time": 1.8
      },
      "created_at": "2025-01-15T10:30:00Z",
      "updated_at": "2025-11-18T14:20:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "total_pages": 3
  }
}
```

#### 4.1.2 Create Agent

**Endpoint:** `POST /api/agents`

**Request Body:**
```json
{
  "name": "Sales Assistant",
  "type": "sales",
  "description": "Helps with sales inquiries and product recommendations",
  "provider": "anthropic",
  "model": "claude-3-5-sonnet-20241022",
  "configuration": {
    "temperature": 0.8,
    "max_tokens": 8000,
    "system_prompt": "You are a sales assistant...",
    "tools": ["database", "calendar", "crm"],
    "enable_memory": true,
    "memory_window": 50
  },
  "tenant_id": 456,
  "is_enabled": true,
  "is_default": false
}
```

**Response:** `201 Created`
```json
{
  "id": 789,
  "name": "Sales Assistant",
  "type": "sales",
  "provider": "anthropic",
  "model": "claude-3-5-sonnet-20241022",
  "is_enabled": true,
  "created_at": "2025-11-18T14:30:00Z"
}
```

#### 4.1.3 Execute Agent (Chat)

**Endpoint:** `POST /api/agents/{id}/chat`

**Request Body:**
```json
{
  "message": "What are your business hours?",
  "conversation_id": "conv_abc123",
  "stream": true,
  "user_id": "user_789",
  "metadata": {
    "source": "web_chat",
    "ip_address": "192.168.1.1"
  }
}
```

**Response (Non-Streaming):**
```json
{
  "response": "Our business hours are Monday-Friday, 9 AM to 6 PM EST.",
  "conversation_id": "conv_abc123",
  "agent_id": 123,
  "provider": "openai",
  "model": "gpt-4o-mini",
  "tokens": {
    "prompt": 45,
    "completion": 18,
    "total": 63
  },
  "cost": 0.00004,
  "latency_ms": 1250,
  "tools_called": [],
  "timestamp": "2025-11-18T14:35:00Z"
}
```

**Response (Streaming via SSE):**
```
event: start
data: {"conversation_id":"conv_abc123","agent_id":123}

event: chunk
data: {"content":"Our business","delta":"Our business"}

event: chunk
data: {"content":"Our business hours","delta":" hours"}

event: chunk
data: {"content":"Our business hours are","delta":" are"}

event: done
data: {"full_content":"Our business hours are Monday-Friday, 9 AM to 6 PM EST.","tokens":{"total":63},"cost":0.00004}
```

***

### 4.2 Workflow API

#### 4.2.1 Execute Workflow

**Endpoint:** `POST /api/workflows/{id}/execute`

**Request Body:**
```json
{
  "initial_state": {
    "customer_name": "Acme Corp",
    "customer_email": "contact@acme.com",
    "tier": "enterprise"
  },
  "webhook_url": "https://example.com/webhooks/workflow-complete",
  "notify_on_interrupt": true
}
```

**Response:** `202 Accepted`
```json
{
  "execution_id": "exec_xyz789",
  "workflow_id": 45,
  "status": "running",
  "started_at": "2025-11-18T14:40:00Z",
  "tracking_url": "/api/workflows/executions/exec_xyz789",
  "estimated_duration_seconds": 120
}
```

#### 4.2.2 Get Workflow Execution Status

**Endpoint:** `GET /api/workflows/executions/{execution_id}`

**Response:**
```json
{
  "execution_id": "exec_xyz789",
  "workflow_id": 45,
  "workflow_name": "Customer Onboarding",
  "status": "running",
  "current_node": "InformationCollectionNode",
  "progress": 0.35,
  "state": {
    "customer_name": "Acme Corp",
    "customer_email": "contact@acme.com",
    "collected_data": {
      "company_size": "500-1000",
      "industry": "Technology"
    }
  },
  "events": [
    {
      "type": "node_started",
      "node": "WelcomeNode",
      "timestamp": "2025-11-18T14:40:05Z"
    },
    {
      "type": "node_completed",
      "node": "WelcomeNode",
      "timestamp": "2025-11-18T14:40:12Z"
    },
    {
      "type": "node_started",
      "node": "InformationCollectionNode",
      "timestamp": "2025-11-18T14:40:12Z"
    }
  ],
  "started_at": "2025-11-18T14:40:00Z",
  "estimated_completion": "2025-11-18T14:42:00Z"
}
```

***

### 4.3 Provider API

#### 4.3.1 List Available Providers

**Endpoint:** `GET /api/providers`

**Response:**
```json
{
  "data": [
    {
      "name": "openai",
      "display_name": "OpenAI",
      "is_enabled": true,
      "requires_api_key": true,
      "capabilities": {
        "streaming": true,
        "function_calling": true,
        "vision": true,
        "structured_output": true
      },
      "models": [
        {
          "id": "gpt-4o",
          "name": "GPT-4o",
          "context_window": 128000,
          "pricing": {
            "prompt": 0.0025,
            "completion": 0.01
          }
        },
        {
          "id": "gpt-4o-mini",
          "name": "GPT-4o Mini",
          "context_window": 128000,
          "pricing": {
            "prompt": 0.00015,
            "completion": 0.0006
          }
        }
      ]
    },
    {
      "name": "anthropic",
      "display_name": "Anthropic",
      "is_enabled": true,
      "requires_api_key": true,
      "capabilities": {
        "streaming": true,
        "function_calling": true,
        "vision": true,
        "structured_output": false
      },
      "models": [
        {
          "id": "claude-3-5-sonnet-20241022",
          "name": "Claude 3.5 Sonnet",
          "context_window": 200000,
          "pricing": {
            "prompt": 0.003,
            "completion": 0.015
          }
        }
      ]
    }
  ]
}
```

***

## 5. Data Models

### 5.1 Enhanced Agent Model

```sql
CREATE TABLE agents (
    -- Existing fields
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    tenant_id INT NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- NEW: Provider configuration
    provider VARCHAR(50) DEFAULT 'openai',
    provider_model VARCHAR(100),
    provider_config JSON, -- API keys, endpoints, custom settings
    
    -- NEW: Agent type and class mapping
    agent_type VARCHAR(50) NOT NULL, -- 'support', 'sales', 'custom', etc.
    agent_class VARCHAR(255), -- Full class name for custom agents
    
    -- NEW: Instructions and tools
    system_prompt TEXT,
    tools_config JSON, -- Array of tool configurations
    
    -- NEW: Memory settings
    enable_memory BOOLEAN DEFAULT TRUE,
    memory_window INT DEFAULT 50,
    session_ttl INT DEFAULT 3600, -- seconds
    
    -- NEW: Performance settings
    temperature DECIMAL(3,2) DEFAULT 0.70,
    max_tokens INT DEFAULT 4000,
    top_p DECIMAL(3,2) DEFAULT 1.00,
    frequency_penalty DECIMAL(3,2) DEFAULT 0.00,
    presence_penalty DECIMAL(3,2) DEFAULT 0.00,
    
    -- Existing relationships
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_provider (provider),
    INDEX idx_type (agent_type)
);
```

### 5.2 Workflow Models

```sql
CREATE TABLE workflows (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    workflow_class VARCHAR(255) NOT NULL,
    tenant_id INT NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    configuration JSON, -- Workflow-specific settings
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id)
);

CREATE TABLE workflow_executions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    execution_id VARCHAR(255) UNIQUE NOT NULL,
    workflow_id BIGINT NOT NULL,
    status ENUM('pending','running','completed','failed','cancelled','interrupted') DEFAULT 'pending',
    state JSON NOT NULL,
    current_node VARCHAR(255),
    error_message TEXT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    estimated_completion TIMESTAMP NULL,
    
    -- User/tenant tracking
    tenant_id INT NOT NULL,
    user_id INT NULL,
    
    -- Resource tracking
    total_tokens INT DEFAULT 0,
    total_cost DECIMAL(10,4) DEFAULT 0,
    
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_execution_id (execution_id),
    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id)
);

CREATE TABLE workflow_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    execution_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    node_name VARCHAR(255),
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_execution (execution_id),
    INDEX idx_type (event_type)
);

CREATE TABLE workflow_interrupts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    execution_id VARCHAR(255) NOT NULL,
    interrupt_type VARCHAR(100) NOT NULL,
    message TEXT,
    data JSON,
    status ENUM('pending','resolved','cancelled') DEFAULT 'pending',
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    resolution_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_execution (execution_id),
    INDEX idx_status (status)
);
```

### 5.3 Session Storage Model

```sql
CREATE TABLE chat_sessions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    tenant_id INT NOT NULL,
    agent_id INT NOT NULL,
    user_id INT NULL,
    
    -- Session data (Neuron AI ChatSession serialized)
    data JSON NOT NULL,
    
    -- Metrics
    message_count INT DEFAULT 0,
    total_tokens INT DEFAULT 0,
    total_cost DECIMAL(10,4) DEFAULT 0,
    
    -- Lifecycle
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_tenant_user (tenant_id, user_id),
    INDEX idx_expires (expires_at)
);

-- Session analytics
CREATE TABLE session_analytics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_session (session_id),
    INDEX idx_metric (metric_name)
);
```

***

## 6. Integration Requirements

### 6.1 Backward Compatibility

**Requirements:**
1. **Existing API Endpoints:** Must remain functional during and after migration
2. **Database Schema:** Additive changes only (no breaking schema changes)
3. **Legacy Mode:** Support flag to use old `ChatHandler` logic
4. **Data Migration:** Existing agent configs must map to new system

**Implementation:**
```php
// Feature flag in config
'legacy_mode' => env('ENABLE_LEGACY_MODE', true),

// Conditional routing
if (config('legacy_mode') && !$request->has('use_neuron')) {
    return $this->legacyHandler->handle($request);
} else {
    return $this->neuronHandler->handle($request);
}
```

### 6.2 Existing Services Integration

#### 6.2.1 Multi-Tenancy
- TenantContext must be injected into all agents
- Agent visibility scoped by tenant
- Provider credentials per-tenant support

#### 6.2.2 RBAC Integration
- Agent CRUD operations respect existing roles
- Workflow execution permissions
- Provider configuration restrictions

#### 6.2.3 Billing Integration
- Token usage tracked per provider
- Cost calculation with provider-specific pricing
- Quota enforcement integration

#### 6.2.4 WhatsApp Integration
- Agents callable via WhatsApp webhook
- Session persistence across WhatsApp conversations
- Media handling through Neuron AI

#### 6.2.5 Webhook System
- Workflow events trigger webhooks
- Agent response webhooks
- Provider switching notifications

### 6.3 External Dependencies

**Required Composer Packages:**
```json
{
  "require": {
    "neuron-core/neuron-ai": "^2.0",
    "inspector-apm/inspector-php": "^4.0",
    // Existing dependencies maintained
  }
}
```

**Environment Variables:**
```bash
# Neuron AI
NEURON_ENABLED=true

# Inspector.dev
INSPECTOR_ENABLED=true
INSPECTOR_INGESTION_KEY=your_key_here

# Additional Provider Keys (optional)
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
MISTRAL_API_KEY=
AWS_BEDROCK_REGION=
AWS_BEDROCK_ACCESS_KEY=
AWS_BEDROCK_SECRET_KEY=
```

***

## 7. Migration Strategy

### 7.1 Phase-Based Rollout

#### Phase 1: Foundation (Weeks 1-2)
**Goal:** Set up infrastructure without breaking existing system

**Tasks:**
1. Install Neuron AI and Inspector.dev SDKs
2. Create database migrations (run but don't enforce)
3. Implement provider abstraction layer
4. Create base agent classes
5. Set up agent registry and factory
6. Add feature flags for gradual rollout

**Deliverables:**
- Neuron AI installed and configured
- New database tables created
- Base classes implemented
- Feature flags operational

**Testing:**
- Unit tests for new classes
- Integration tests with existing system (legacy mode)

#### Phase 2: Single Agent Migration (Weeks 3-4)
**Goal:** Migrate one simple agent to validate approach

**Tasks:**
1. Select lowest-risk agent (e.g., FAQ bot)
2. Create Neuron AI agent class
3. Implement parallel execution (10% traffic)
4. Compare metrics: latency, quality, cost
5. Fix issues identified
6. Increase traffic to 100% for this agent

**Deliverables:**
- One agent fully migrated
- Performance comparison report
- Lessons learned document

**Testing:**
- A/B testing between legacy and Neuron implementations
- Load testing with production traffic patterns

#### Phase 3: Multi-Provider Support (Weeks 5-6)
**Goal:** Enable provider switching for migrated agent

**Tasks:**
1. Implement provider selection UI
2. Add provider credentials management
3. Test migrated agent with 3+ providers
4. Implement cost tracking per provider
5. Create provider switching workflows

**Deliverables:**
- Multi-provider UI in Admin panel
- Provider comparison dashboard
- Provider switching documentation

**Testing:**
- Test each provider with identical prompts
- Validate cost calculations
- Test failover scenarios

#### Phase 4: Memory Management (Weeks 7-8)
**Goal:** Replace custom session management with Neuron AI memory

**Tasks:**
1. Implement SessionStorage with multiple drivers
2. Migrate existing conversation data
3. Test session persistence across requests
4. Implement session analytics
5. Add session management UI

**Deliverables:**
- SessionStorage implementation
- Data migration scripts
- Session management API

**Testing:**
- Test session persistence with Redis, Database, File
- Validate conversation context preservation
- Test session expiration logic

#### Phase 5: Remaining Agent Migration (Weeks 9-12)
**Goal:** Migrate all remaining agents progressively

**Tasks:**
1. Prioritize agents by usage (high to low)
2. Migrate 2-3 agents per week
3. Monitor each migration closely
4. Document agent-specific challenges
5. Deprecate legacy ChatHandler (gradual)

**Deliverables:**
- All agents migrated
- Legacy mode deprecated (optional flag)
- Complete migration report

**Testing:**
- Regression testing after each agent migration
- End-to-end testing of all agents

#### Phase 6: Multi-Agent Workflows (Weeks 13-16)
**Goal:** Introduce workflow capabilities

**Tasks:**
1. Implement workflow engine
2. Create 2-3 example workflows
3. Build workflow management UI
4. Implement human-in-the-loop features
5. Create workflow documentation

**Deliverables:**
- Workflow engine operational
- Example workflows deployed
- Workflow creation guide

**Testing:**
- Test workflow execution end-to-end
- Test interrupt and resume functionality
- Load testing for concurrent workflows

#### Phase 7: Inspector.dev Integration (Weeks 17-18)
**Goal:** Full observability implementation

**Tasks:**
1. Configure Inspector.dev dashboards
2. Set up alerting rules
3. Migrate from Prometheus to Inspector (gradual)
4. Train team on Inspector.dev
5. Document observability practices

**Deliverables:**
- Inspector.dev dashboards configured
- Alerting operational
- Observability documentation

**Testing:**
- Validate metrics accuracy
- Test alerting thresholds
- Verify data retention policies

### 7.2 Rollback Plan

**Rollback Triggers:**
- Error rate > 5% for any agent
- Latency increase > 50%
- Cost increase > 30% unexpectedly
- Critical production issues

**Rollback Procedure:**
1. **Immediate:** Toggle feature flag to enable legacy mode
2. **Database:** Existing tables untouched, rollback safe
3. **Traffic:** Route 100% to legacy handlers
4. **Investigation:** Analyze logs and Inspector data
5. **Fix Forward:** Address issues and re-deploy

**Rollback Testing:**
- Monthly rollback drills during migration
- Automated rollback scripts ready

### 7.3 Data Migration

**Existing Agent Configs → Neuron AI Agents:**
```php
class AgentMigrator
{
    public function migrateAgent(int $agentId): void
    {
        $oldConfig = DB::table('agents')->find($agentId);
        
        // Map old fields to new schema
        DB::table('agents')->where('id', $agentId)->update([
            'provider' => 'openai', // Default
            'provider_model' => $oldConfig->model ?? 'gpt-4o-mini',
            'agent_type' => $this->inferType($oldConfig),
            'system_prompt' => $oldConfig->instructions ?? '',
            'tools_config' => json_encode($this->parseTools($oldConfig)),
            'enable_memory' => true,
            'temperature' => $oldConfig->temperature ?? 0.7,
        ]);
        
        Log::info("Migrated agent {$agentId}");
    }
    
    private function inferType(object $config): string
    {
        // Logic to infer agent type from existing config
        if (str_contains($config->name, 'Support')) return 'support';
        if (str_contains($config->name, 'Sales')) return 'sales';
        return 'custom';
    }
}
```

***

## 8. Security & Compliance

### 8.1 Provider Credentials Security

**Requirements:**
1. **Encryption at Rest:** All API keys encrypted with AES-256
2. **Encryption in Transit:** TLS 1.3 for all provider communications
3. **Access Control:** Only super-admin can configure system providers
4. **Audit Logging:** All credential changes logged

**Implementation:**
```php
class SecureCredentialStorage
{
    private string $encryptionKey;
    
    public function storeCredential(
        int $tenantId, 
        string $provider, 
        string $apiKey
    ): void {
        $encrypted = openssl_encrypt(
            $apiKey,
            'AES-256-GCM',
            $this->encryptionKey,
            0,
            $iv = random_bytes(16),
            $tag
        );
        
        DB::table('provider_credentials')->insert([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'encrypted_key' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'created_at' => now()
        ]);
        
        AuditService::log('credential_stored', [
            'tenant_id' => $tenantId,
            'provider' => $provider
        ]);
    }
}
```

### 8.2 Data Privacy

**Compliance Requirements:**
- LGPD (Brazil)
- GDPR (EU)
- CCPA (California)

**Implementation:**
1. **Session Data Retention:** Configurable TTL (default 30 days)
2. **Right to Erasure:** API endpoint to delete user data
3. **Data Export:** API endpoint to export user conversations
4. **Consent Management:** Integrated with existing consent service

### 8.3 Rate Limiting

**Multi-Level Rate Limiting:**
1. **IP-Based:** 100 requests/minute (existing)
2. **Tenant-Based:** Configurable quota per tenant
3. **Agent-Based:** Prevent runaway agent executions
4. **Provider-Based:** Respect provider rate limits

**Enhanced Implementation:**
```php
class MultiLevelRateLimiter
{
    public function checkLimit(Request $request, Agent $agent): bool
    {
        // IP-based (existing)
        if (!$this->checkIPLimit($request->ip())) {
            throw new IPRateLimitException();
        }
        
        // Tenant-based (existing)
        $tenant = $request->tenant();
        if (!$this->checkTenantLimit($tenant->id)) {
            throw new TenantQuotaException();
        }
        
        // NEW: Agent-based
        if (!$this->checkAgentLimit($agent->id, $tenant->id)) {
            throw new AgentRateLimitException();
        }
        
        // NEW: Provider-based
        $provider = $agent->getProviderName();
        if (!$this->checkProviderLimit($provider, $tenant->id)) {
            throw new ProviderRateLimitException();
        }
        
        return true;
    }
}
```

***

## 9. Testing Requirements

### 9.1 Unit Tests

**Coverage Requirements:** Minimum 80% code coverage

**Test Categories:**
1. **Agent Tests:**
   - Provider initialization
   - Instruction loading
   - Tool registration
   - Memory management

2. **Workflow Tests:**
   - Node execution
   - Event handling
   - State management
   - Error handling

3. **Provider Tests:**
   - Factory creation
   - Configuration validation
   - Error handling
   - Fallback mechanisms

**Example Test:**
```php
class SupportAgentTest extends TestCase
{
    public function test_agent_initializes_with_correct_provider()
    {
        $config = [
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet-20241022'
        ];
        
        $agent = AgentFactory::createFromConfig($config);
        
        $this->assertEquals('anthropic', $agent->getProviderName());
        $this->assertEquals('claude-3-5-sonnet-20241022', $agent->getModelName());
    }
    
    public function test_agent_maintains_conversation_context()
    {
        $agent = SupportAgent::make();
        
        $response1 = $agent->chat(new UserMessage("My name is João"));
        $response2 = $agent->chat(new UserMessage("What's my name?"));
        
        $this->assertStringContainsString('João', $response2);
    }
}
```

### 9.2 Integration Tests

**Test Scenarios:**
1. End-to-end conversation with agent
2. Provider switching mid-conversation
3. Workflow execution with multiple agents
4. Session persistence across requests
5. Multi-tenant isolation
6. Webhook delivery

**Example Integration Test:**
```php
class AgentIntegrationTest extends TestCase
{
    public function test_complete_conversation_flow()
    {
        // Create test tenant and agent
        $tenant = Tenant::factory()->create();
        $agent = Agent::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'openai'
        ]);
        
        // Simulate user conversation
        $conversationId = Str::uuid();
        
        $response1 = $this->postJson('/api/agents/' . $agent->id . '/chat', [
            'message' => 'Hello',
            'conversation_id' => $conversationId
        ]);
        
        $response1->assertStatus(200)
                  ->assertJsonStructure(['response', 'tokens', 'cost']);
        
        // Continue conversation
        $response2 = $this->postJson('/api/agents/' . $agent->id . '/chat', [
            'message' => 'Do you remember what I said?',
            'conversation_id' => $conversationId
        ]);
        
        $response2->assertStatus(200);
        
        // Verify session persisted
        $session = ChatSession::where('session_id', $conversationId)->first();
        $this->assertNotNull($session);
        $this->assertEquals(2, $session->message_count);
    }
}
```

### 9.3 Load Testing

**Requirements:**
- Sustained load: 100 concurrent users
- Peak load: 500 concurrent users
- Average response time: < 2s (p50)
- 95th percentile: < 5s (p95)

**K6 Test Script:**
```javascript
import http from 'k6/http';
import { check } from 'k6';

export let options = {
  stages: [
    { duration: '2m', target: 100 }, // Ramp to 100 users
    { duration: '5m', target: 100 }, // Sustain 100 users
    { duration: '2m', target: 500 }, // Peak to 500 users
    { duration: '3m', target: 500 }, // Sustain peak
    { duration: '2m', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<5000'], // 95% under 5s
    http_req_failed: ['rate<0.05'],    // Error rate < 5%
  },
};

export default function () {
  const payload = JSON.stringify({
    message: 'What are your business hours?',
    conversation_id: `conv_${__VU}_${__ITER}`,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${__ENV.API_TOKEN}`,
    },
  };

  const res = http.post(
    `${__ENV.API_URL}/api/agents/123/chat`,
    payload,
    params
  );

  check(res, {
    'status is 200': (r) => r.status === 200,
    'response time < 5s': (r) => r.timings.duration < 5000,
    'has response field': (r) => r.json('response') !== undefined,
  });
}
```

### 9.4 Provider Testing

**Test Matrix:**
| Provider | Streaming | Function Calling | Vision | Structured Output |
|----------|-----------|------------------|--------|-------------------|
| OpenAI | ✓ | ✓ | ✓ | ✓ |
| Anthropic | ✓ | ✓ | ✓ | ✗ |
| Gemini | ✓ | ✓ | ✓ | ✓ |
| Ollama | ✓ | ✓ | ✗ | ✗ |

**Test Requirements:**
- Verify each capability for each provider
- Test error handling for unsupported capabilities
- Validate cost calculations
- Test failover between providers

***

## 10. Documentation Requirements

### 10.1 User Documentation

**Admin User Guide:**
1. **Provider Management:**
   - Enabling/disabling providers
   - Configuring provider credentials
   - Managing models and pricing

2. **Agent Configuration:**
   - Creating agents with Neuron AI
   - Selecting providers and models
   - Configuring tools and memory
   - Testing agents

3. **Workflow Management:**
   - Creating workflows
   - Monitoring executions
   - Handling interrupts

**End User Guide:**
1. **Using Multi-Provider Agents:**
   - Understanding provider differences
   - When to use which provider

2. **Session Management:**
   - Viewing conversation history
   - Clearing sessions
   - Exporting conversations

### 10.2 Developer Documentation

**Integration Guide:**
1. **Creating Custom Agents:**
   ```markdown
   # Creating Custom Agents

   ## Overview
   Custom agents extend the `BaseAgent` class and implement required methods.

   ## Step 1: Create Agent Class
   ```
   namespace App\Neuron\Agents;

   class CustomAgent extends BaseAgent
   {
       protected function instructions(): string
       {
           return "Your custom instructions...";
       }

       protected function tools(): array
       {
           return [
               new CustomToolkit($this->tenantContext)
           ];
       }
   }
   ```

   ## Step 2: Register Agent
   ```
   // config/neuron.php
   return [
       'agents' => [
           'custom' => CustomAgent::class,
       ]
   ];
   ```
   ```

2. **Developing Toolkits:**
   - Toolkit interface
   - Tool registration
   - Error handling
   - Testing toolkits

3. **Building Workflows:**
   - Workflow structure
   - Node implementation
   - Event types
   - State management

**API Documentation:**
- Complete OpenAPI/Swagger specification
- Interactive API explorer
- Code examples in PHP, JavaScript, Python, cURL

### 10.3 Operations Documentation

**Runbooks:**
1. **Provider Outage Response:**
   - Detection mechanisms
   - Failover procedures
   - User communication templates

2. **Scaling Procedures:**
   - Horizontal scaling guidelines
   - Database optimization
   - Caching strategies

3. **Monitoring & Alerting:**
   - Key metrics to watch
   - Alert response procedures
   - Escalation paths

**Troubleshooting Guide:**
- Common issues and solutions
- Log analysis procedures
- Performance debugging

***

## 11. Timeline & Milestones

### 11.1 Project Schedule

**Total Duration:** 18 weeks (4.5 months)

**Milestones:**

| Week | Phase | Milestone | Deliverables |
|------|-------|-----------|--------------|
| 1-2 | Foundation | Infrastructure Setup | Neuron AI installed, base classes implemented, feature flags operational |
| 3-4 | Pilot | First Agent Migrated | One agent running on Neuron AI in production with 100% traffic |
| 5-6 | Multi-Provider | Provider Support Live | 3+ providers configured, cost tracking operational |
| 7-8 | Memory | Memory System Active | SessionStorage implemented, existing data migrated |
| 9-12 | Scale | All Agents Migrated | Legacy ChatHandler deprecated, all agents on Neuron AI |
| 13-16 | Workflows | Workflow Engine Live | 3 example workflows deployed, workflow UI operational |
| 17-18 | Observability | Inspector.dev Integrated | Full observability, alerting configured |

### 11.2 Resource Requirements

**Team Composition:**
- 1x Technical Lead (full-time, all phases)
- 2x Backend Developers (full-time, weeks 1-16)
- 1x Frontend Developer (part-time, weeks 3-6, 13-16)
- 1x DevOps Engineer (part-time, all phases)
- 1x QA Engineer (part-time, weeks 2-18)
- 1x Technical Writer (part-time, weeks 10-18)

**External Resources:**
- Neuron AI support subscription
- Inspector.dev enterprise plan
- Provider API credits for testing

### 11.3 Budget Estimate

**Development Costs:**
- Personnel: $180,000 (18 weeks × team composition)
- External services: $3,000 (Neuron support, Inspector.dev)
- Provider API testing: $2,000
- Contingency (15%): $27,750

**Total Estimated Cost:** $212,750

**Ongoing Costs (Monthly):**
- Inspector.dev subscription: $150/month
- Provider API usage: Variable (based on traffic distribution)
- Maintenance: 20% of development cost annually

***

## 12. Success Metrics

### 12.1 Technical Metrics

**Performance:**
- ✅ Average response time < 2s (p50)
- ✅ 95th percentile < 5s (p95)
- ✅ Error rate < 1%
- ✅ 99.9% uptime maintained

**Scalability:**
- ✅ Support 500 concurrent users without degradation
- ✅ Handle 10,000+ conversations/day

**Cost:**
- ✅ 20% cost reduction through provider optimization
- ✅ Token usage visibility per agent/tenant/provider

### 12.2 Adoption Metrics

**Developer Adoption:**
- ✅ 3+ custom agents created by team within 3 months
- ✅ 2+ custom workflows deployed
- ✅ Documentation satisfaction > 4/5

**User Adoption:**
- ✅ 100% of agents migrated to Neuron AI
- ✅ Multi-provider usage across tenants
- ✅ Zero critical incidents during migration

### 12.3 Business Metrics

**Platform Differentiation:**
- ✅ Multi-provider support listed as key feature
- ✅ Workflow capabilities enable new use cases
- ✅ Enhanced observability improves sales demos

**Customer Satisfaction:**
- ✅ Maintain or improve response quality
- ✅ Reduced latency improves user experience
- ✅ New capabilities enable tenant growth

### 12.4 Success Criteria

**Go-Live Checklist:**
- [ ] All agents migrated and tested
- [ ] 3+ providers operational
- [ ] Inspector.dev dashboards configured
- [ ] Documentation complete
- [ ] Load testing passed
- [ ] Security audit completed
- [ ] Rollback procedures tested
- [ ] Team trained on new system
- [ ] Customer communication sent

**Post-Launch Review (3 Months):**
- Metrics achievement review
- Lessons learned documentation
- ROI analysis
- Roadmap for future enhancements

***

## Appendices

### Appendix A: Glossary

- **Agent:** A self-contained AI entity with specific instructions, tools, and behavior
- **Provider:** An LLM service (OpenAI, Anthropic, etc.)
- **Workflow:** A coordinated sequence of agent executions
- **Node:** A single step in a workflow
- **Session:** A conversation context spanning multiple messages
- **Toolkit:** A collection of tools an agent can use
- **Interrupt:** A workflow pause requiring human input

### Appendix B: References

- Neuron AI Documentation: https://docs.neuron-ai.dev
- Inspector.dev Documentation: https://docs.inspector.dev
- OpenAI API Reference: https://platform.openai.com/docs
- Anthropic API Reference: https://docs.anthropic.com
- Google Gemini API: https://ai.google.dev/docs

### Appendix C: Risk Register

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Provider API changes breaking integration | Medium | High | Version pinning, monitoring for API updates |
| Migration causing downtime | Low | Critical | Parallel execution, feature flags, rollback plan |
| Cost overruns from provider usage | Medium | Medium | Usage monitoring, budget alerts, quotas |
| Team learning curve delays | Medium | Medium | Training sessions, documentation, pair programming |
| Third-party dependencies (Neuron AI) | Low | High | Contract SLA, support subscription, escape plan |

***

**Document Version:** 1.0  
**Last Updated:** November 18, 2025  
**Next Review:** Upon phase completion or as needed  

***

