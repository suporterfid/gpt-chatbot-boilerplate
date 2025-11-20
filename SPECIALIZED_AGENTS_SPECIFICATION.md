# Specialized Agents System Specification

**Version:** 1.0
**Last Updated:** 2025-11-20
**Status:** Draft

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [System Overview](#system-overview)
3. [Architecture Design](#architecture-design)
4. [Agent Interface Definition](#agent-interface-definition)
5. [Directory Structure](#directory-structure)
6. [Agent Lifecycle](#agent-lifecycle)
7. [Communication Protocol](#communication-protocol)
8. [Configuration Management](#configuration-management)
9. [Error Handling & Logging](#error-handling--logging)
10. [Testing Strategy](#testing-strategy)
11. [Agent Template & Skeleton](#agent-template--skeleton)
12. [Migration Plan](#migration-plan)
13. [Security Considerations](#security-considerations)
14. [Developer Guide](#developer-guide)
15. [Appendix](#appendix)

---

## 1. Executive Summary

This specification defines a **plugin-based specialized agent system** for the PHP Chatbot Boilerplate platform that enables developers to create domain-specific agents without modifying core application code.

### Key Objectives

- **Extensibility:** Support unlimited specialized agents (WordPress, LinkedIn, Biblical Writer, Marketing, Technical Support, etc.)
- **Backward Compatibility:** Maintain 100% compatibility with existing agent functionality
- **Developer Experience:** Minimize boilerplate code and provide clear conventions
- **Maintainability:** Isolate agent-specific logic from core system logic

### Design Philosophy

The specialized agent system builds upon the **existing service-oriented architecture** by introducing:

1. **Agent Type System** - Classification and discovery mechanism
2. **Specialized Agent Interface** - Standardized contract for custom behavior
3. **Agent Registry** - Automatic discovery and registration
4. **Processing Pipeline** - Extensible request/response processing
5. **Configuration Layer** - Agent-specific settings management

---

## 2. System Overview

### 2.1 Current Architecture Summary

The platform currently provides:

- **Generic Agent Management**: Database-backed agents with configurable prompts, tools, and models
- **Service Layer**: 25+ specialized services (AgentService, ChatHandler, PromptBuilderService, etc.)
- **Multi-Tenancy**: Complete tenant isolation with quota enforcement
- **Channel System**: Pluggable channel adapters (WhatsApp, with extensibility for Telegram, Slack, etc.)
- **Admin API**: RESTful API for agent CRUD operations
- **Webhook System**: Event-driven notifications

### 2.2 Limitations of Current System

While powerful, the current implementation treats all agents identically:

- **No Domain Specialization**: All agents use the same ChatHandler processing logic
- **Limited Customization**: Behavior customization restricted to prompts and tools
- **No Agent-Specific Workflows**: Complex multi-step reasoning requires manual orchestration
- **Rigid Processing Pipeline**: Single code path for all agent interactions

### 2.3 Specialized Agent System Vision

The specialized agent system introduces:

```
┌─────────────────────────────────────────────────────────┐
│                     Core Platform                        │
│  (AgentService, ChatHandler, Database, Auth, etc.)      │
└────────────────┬────────────────────────────────────────┘
                 │
                 │ Uses
                 ▼
┌─────────────────────────────────────────────────────────┐
│              Agent Type Registry                         │
│         (Discovers & Manages Agent Types)                │
└────────┬────────────────────────────────────────────────┘
         │
         │ Loads
         ▼
┌─────────────────────────────────────────────────────────┐
│                 Specialized Agents                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │  WordPress   │  │   LinkedIn   │  │   Biblical   │  │
│  │    Agent     │  │    Agent     │  │    Writer    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │  Marketing   │  │   Support    │  │  Consultant  │  │
│  │    Agent     │  │    Agent     │  │    Agent     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Architecture Design

### 3.1 Plugin Architecture Pattern

The system uses a **Service Provider Pattern** where each specialized agent is a self-contained plugin that:

1. **Declares its type** via a unique identifier (e.g., `wordpress`, `linkedin`)
2. **Implements a standardized interface** (`SpecializedAgentInterface`)
3. **Registers capabilities** (custom tools, processing hooks, validators)
4. **Provides metadata** (name, description, configuration schema)

### 3.2 Core Components

#### 3.2.1 Agent Type System

**Database Schema Extension** (new migration):

```sql
-- Add agent_type column to agents table
ALTER TABLE agents ADD COLUMN agent_type TEXT DEFAULT 'generic' NOT NULL;
CREATE INDEX idx_agents_agent_type ON agents(agent_type);

-- Specialized agent configurations table
CREATE TABLE specialized_agent_configs (
    id TEXT PRIMARY KEY,
    agent_id TEXT NOT NULL,
    agent_type TEXT NOT NULL,
    config_json TEXT, -- Agent-type-specific configuration
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE(agent_id)
);

-- Agent type capabilities (optional metadata)
CREATE TABLE agent_type_metadata (
    agent_type TEXT PRIMARY KEY,
    display_name TEXT NOT NULL,
    description TEXT,
    version TEXT,
    enabled BOOLEAN DEFAULT 1,
    config_schema_json TEXT, -- JSON Schema for validation
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

#### 3.2.2 Agent Registry Service

**Location:** `/includes/AgentRegistry.php`

```php
<?php

/**
 * Agent Registry Service
 *
 * Discovers, registers, and manages specialized agent types.
 * Uses auto-discovery to find agent classes in the /agents/ directory.
 */
class AgentRegistry
{
    private array $registeredTypes = [];
    private array $instances = [];
    private DB $db;
    private string $agentsPath;
    private LoggerInterface $logger;

    public function __construct(DB $db, string $agentsPath, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->agentsPath = $agentsPath;
        $this->logger = $logger;
    }

    /**
     * Discover and register all available agent types
     *
     * Scans /agents/ directory for PHP files implementing SpecializedAgentInterface
     */
    public function discoverAgents(): void;

    /**
     * Register a specific agent type
     *
     * @param string $agentType Unique identifier (e.g., 'wordpress')
     * @param string $className Fully qualified class name
     */
    public function registerType(string $agentType, string $className): void;

    /**
     * Get instance of specialized agent for a given type
     *
     * @param string $agentType
     * @return SpecializedAgentInterface|null
     */
    public function getAgent(string $agentType): ?SpecializedAgentInterface;

    /**
     * Check if agent type is registered
     */
    public function hasAgentType(string $agentType): bool;

    /**
     * Get all registered agent types with metadata
     *
     * @return array Array of agent type metadata
     */
    public function listAvailableTypes(): array;

    /**
     * Validate agent type configuration
     *
     * @param string $agentType
     * @param array $config
     * @return array Validation errors (empty if valid)
     */
    public function validateConfig(string $agentType, array $config): array;
}
```

#### 3.2.3 Enhanced Chat Handler Integration

**Location:** `/includes/ChatHandler.php` (modifications)

```php
// In ChatHandler class - add specialized agent processing

private ?AgentRegistry $agentRegistry = null;

public function setAgentRegistry(AgentRegistry $registry): void
{
    $this->agentRegistry = $registry;
}

/**
 * Enhanced message processing with specialized agent hooks
 */
private function processMessageWithSpecialization(
    array $messages,
    string $agentId,
    array $agentConfig
): array {
    $agentType = $agentConfig['agent_type'] ?? 'generic';

    // Use generic processing if no specialization
    if ($agentType === 'generic' || !$this->agentRegistry) {
        return $this->processStandardMessage($messages, $agentConfig);
    }

    // Get specialized agent instance
    $specializedAgent = $this->agentRegistry->getAgent($agentType);
    if (!$specializedAgent) {
        $this->logger->warning("Agent type not found: {$agentType}, falling back to generic");
        return $this->processStandardMessage($messages, $agentConfig);
    }

    try {
        // Execute specialized agent pipeline
        return $this->executeSpecializedPipeline($specializedAgent, $messages, $agentConfig);
    } catch (Exception $e) {
        $this->logger->error("Specialized agent error: " . $e->getMessage());
        // Graceful fallback to standard processing
        return $this->processStandardMessage($messages, $agentConfig);
    }
}

/**
 * Execute specialized agent processing pipeline
 */
private function executeSpecializedPipeline(
    SpecializedAgentInterface $agent,
    array $messages,
    array $agentConfig
): array {
    // Phase 1: Pre-processing
    $context = $agent->buildContext($messages, $agentConfig);

    // Phase 2: Input validation & transformation
    $validatedInput = $agent->validateInput($messages, $context);

    // Phase 3: Custom processing (agent-specific logic)
    $processedData = $agent->process($validatedInput, $context);

    // Phase 4: LLM interaction (if needed)
    if ($agent->requiresLLM($processedData, $context)) {
        $llmMessages = $agent->prepareLLMMessages($processedData, $context);
        $llmResponse = $this->callOpenAI($llmMessages, $agentConfig);
        $processedData['llm_response'] = $llmResponse;
    }

    // Phase 5: Post-processing & formatting
    $output = $agent->formatOutput($processedData, $context);

    // Phase 6: Validation
    $validatedOutput = $agent->validateOutput($output, $context);

    return $validatedOutput;
}
```

### 3.3 Backward Compatibility Strategy

All changes are **additive** and **opt-in**:

1. **Database Changes**: New columns with default values (`agent_type` defaults to `'generic'`)
2. **Service Initialization**: AgentRegistry is optional; system works without it
3. **Processing Logic**: Falls back to standard processing if specialized agent unavailable
4. **API Compatibility**: Existing endpoints unchanged; new optional parameters added
5. **Configuration**: Existing agents continue working without any changes

**Migration Safety**:
- Existing agents automatically receive `agent_type = 'generic'`
- Generic agents use current ChatHandler logic (zero changes)
- Specialized agents only activate when explicitly configured
- No breaking changes to API contracts

---

## 4. Agent Interface Definition

### 4.1 SpecializedAgentInterface

**Location:** `/includes/Interfaces/SpecializedAgentInterface.php`

```php
<?php

namespace ChatbotBoilerplate\Interfaces;

/**
 * Specialized Agent Interface
 *
 * All specialized agent types must implement this interface.
 * Defines the contract for custom agent behavior and processing logic.
 */
interface SpecializedAgentInterface
{
    /**
     * Get unique agent type identifier
     *
     * @return string Unique identifier (e.g., 'wordpress', 'linkedin')
     */
    public function getAgentType(): string;

    /**
     * Get human-readable agent type name
     *
     * @return string Display name (e.g., 'WordPress Content Manager')
     */
    public function getDisplayName(): string;

    /**
     * Get agent type description
     *
     * @return string Brief description of agent capabilities
     */
    public function getDescription(): string;

    /**
     * Get agent version
     *
     * @return string Semantic version (e.g., '1.0.0')
     */
    public function getVersion(): string;

    /**
     * Get configuration schema
     *
     * @return array JSON Schema definition for agent-specific configuration
     */
    public function getConfigSchema(): array;

    /**
     * Initialize agent with dependencies
     *
     * @param array $dependencies Injected dependencies (DB, logger, config, etc.)
     */
    public function initialize(array $dependencies): void;

    /**
     * Build processing context from conversation messages
     *
     * @param array $messages Conversation history
     * @param array $agentConfig Agent configuration from database
     * @return array Context object for downstream processing
     */
    public function buildContext(array $messages, array $agentConfig): array;

    /**
     * Validate and transform input messages
     *
     * @param array $messages Input messages
     * @param array $context Processing context
     * @return array Validated and transformed messages
     * @throws ValidationException if input is invalid
     */
    public function validateInput(array $messages, array $context): array;

    /**
     * Core processing logic (agent-specific behavior)
     *
     * @param array $input Validated input
     * @param array $context Processing context
     * @return array Processed data (structure determined by agent)
     */
    public function process(array $input, array $context): array;

    /**
     * Determine if LLM interaction is required
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return bool True if LLM call needed
     */
    public function requiresLLM(array $processedData, array $context): bool;

    /**
     * Prepare messages for LLM API call
     *
     * @param array $processedData Output from process()
     * @param array $context Processing context
     * @return array Messages array for OpenAI API
     */
    public function prepareLLMMessages(array $processedData, array $context): array;

    /**
     * Format output for response
     *
     * @param array $processedData Output from process() (may include llm_response)
     * @param array $context Processing context
     * @return array Formatted response data
     */
    public function formatOutput(array $processedData, array $context): array;

    /**
     * Validate output before sending to user
     *
     * @param array $output Formatted output
     * @param array $context Processing context
     * @return array Validated output
     * @throws ValidationException if output is invalid
     */
    public function validateOutput(array $output, array $context): array;

    /**
     * Handle errors during processing
     *
     * @param \Throwable $error Exception/error that occurred
     * @param array $context Processing context
     * @return array Error response to send to user
     */
    public function handleError(\Throwable $error, array $context): array;

    /**
     * Get custom tools/functions for this agent type
     *
     * @return array OpenAI function definitions
     */
    public function getCustomTools(): array;

    /**
     * Execute custom tool call
     *
     * @param string $toolName Tool name from function call
     * @param array $arguments Tool arguments
     * @param array $context Processing context
     * @return array Tool execution result
     */
    public function executeCustomTool(string $toolName, array $arguments, array $context): array;

    /**
     * Cleanup resources (called at end of request)
     */
    public function cleanup(): void;
}
```

### 4.2 Abstract Base Class

**Location:** `/includes/Agents/AbstractSpecializedAgent.php`

Provides default implementations and helper methods to reduce boilerplate:

```php
<?php

namespace ChatbotBoilerplate\Agents;

use ChatbotBoilerplate\Interfaces\SpecializedAgentInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract Specialized Agent
 *
 * Provides common functionality for specialized agents.
 * Agents can extend this class instead of implementing interface directly.
 */
abstract class AbstractSpecializedAgent implements SpecializedAgentInterface
{
    protected DB $db;
    protected LoggerInterface $logger;
    protected array $config;

    // Default implementations

    public function initialize(array $dependencies): void
    {
        $this->db = $dependencies['db'] ?? null;
        $this->logger = $dependencies['logger'] ?? null;
        $this->config = $dependencies['config'] ?? [];
    }

    public function buildContext(array $messages, array $agentConfig): array
    {
        return [
            'conversation_id' => $agentConfig['conversation_id'] ?? null,
            'tenant_id' => $agentConfig['tenant_id'] ?? null,
            'agent_id' => $agentConfig['id'] ?? null,
            'agent_type' => $this->getAgentType(),
            'message_count' => count($messages),
            'timestamp' => time(),
            'agent_config' => $agentConfig
        ];
    }

    public function validateInput(array $messages, array $context): array
    {
        // Basic validation - override for custom logic
        if (empty($messages)) {
            throw new \InvalidArgumentException('Messages array cannot be empty');
        }
        return $messages;
    }

    public function requiresLLM(array $processedData, array $context): bool
    {
        // Default: always use LLM
        return true;
    }

    public function prepareLLMMessages(array $processedData, array $context): array
    {
        // Default: pass through original messages
        return $processedData['messages'] ?? [];
    }

    public function formatOutput(array $processedData, array $context): array
    {
        // Default: extract LLM response
        return [
            'message' => $processedData['llm_response']['choices'][0]['message'] ?? [],
            'metadata' => [
                'agent_type' => $this->getAgentType(),
                'processed_at' => date('c')
            ]
        ];
    }

    public function validateOutput(array $output, array $context): array
    {
        // Basic validation - override for custom logic
        if (!isset($output['message'])) {
            throw new \RuntimeException('Output must contain message field');
        }
        return $output;
    }

    public function handleError(\Throwable $error, array $context): array
    {
        $this->logger->error('Agent error: ' . $error->getMessage(), [
            'agent_type' => $this->getAgentType(),
            'context' => $context,
            'trace' => $error->getTraceAsString()
        ]);

        return [
            'error' => true,
            'message' => [
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error processing your request. Please try again.'
            ]
        ];
    }

    public function getCustomTools(): array
    {
        // Default: no custom tools
        return [];
    }

    public function executeCustomTool(string $toolName, array $arguments, array $context): array
    {
        throw new \BadMethodCallException("Tool '{$toolName}' not implemented");
    }

    public function cleanup(): void
    {
        // Default: no cleanup needed
    }

    // Helper methods

    protected function logInfo(string $message, array $data = []): void
    {
        $this->logger->info("[{$this->getAgentType()}] {$message}", $data);
    }

    protected function logError(string $message, array $data = []): void
    {
        $this->logger->error("[{$this->getAgentType()}] {$message}", $data);
    }
}
```

---

## 5. Directory Structure

### 5.1 Proposed File Organization

```
/home/user/gpt-chatbot-boilerplate/
│
├── agents/                              # NEW: Specialized agent plugins
│   ├── README.md                        # Agent development guide
│   ├── .gitkeep
│   │
│   ├── wordpress/                       # WordPress agent
│   │   ├── WordPressAgent.php           # Main agent class
│   │   ├── config.schema.json           # Configuration JSON schema
│   │   ├── README.md                    # Agent-specific documentation
│   │   ├── tools/                       # WordPress-specific tools
│   │   │   ├── CreatePostTool.php
│   │   │   ├── UpdatePostTool.php
│   │   │   └── SearchContentTool.php
│   │   ├── prompts/                     # Prompt templates
│   │   │   ├── system_message.md
│   │   │   └── guardrails.md
│   │   └── tests/                       # Agent-specific tests
│   │       ├── WordPressAgentTest.php
│   │       └── fixtures/
│   │
│   ├── linkedin/                        # LinkedIn agent
│   │   ├── LinkedInAgent.php
│   │   ├── config.schema.json
│   │   ├── tools/
│   │   │   ├── PostContentTool.php
│   │   │   └── NetworkSearchTool.php
│   │   └── tests/
│   │
│   ├── biblical-writer/                 # Biblical writer agent
│   │   ├── BiblicalWriterAgent.php
│   │   ├── config.schema.json
│   │   ├── services/
│   │   │   ├── ScriptureReferenceService.php
│   │   │   └── TheologicalValidatorService.php
│   │   └── tests/
│   │
│   └── _template/                       # Agent skeleton template
│       ├── TemplateAgent.php
│       ├── config.schema.json
│       ├── README.md
│       └── tests/
│
├── includes/                            # Existing core services
│   ├── AgentRegistry.php                # NEW: Agent discovery & registration
│   ├── SpecializedAgentService.php      # NEW: Specialized agent management
│   ├── ChatHandler.php                  # MODIFIED: Add specialized agent support
│   ├── AgentService.php                 # MODIFIED: Add agent_type support
│   │
│   ├── Interfaces/                      # NEW: Interface definitions
│   │   ├── SpecializedAgentInterface.php
│   │   └── AgentToolInterface.php
│   │
│   ├── Agents/                          # NEW: Base classes
│   │   ├── AbstractSpecializedAgent.php
│   │   └── AgentContext.php
│   │
│   └── [existing services...]
│
├── db/migrations/                       # Database migrations
│   └── 047_add_specialized_agent_support.sql  # NEW: Schema changes
│
├── tests/                               # Test suite
│   ├── Unit/
│   │   └── AgentRegistryTest.php        # NEW: Registry tests
│   └── Integration/
│       └── SpecializedAgentTest.php     # NEW: Integration tests
│
├── config.php                           # MODIFIED: Add agent registry config
└── admin-api.php                        # MODIFIED: Add specialized agent endpoints
```

### 5.2 Agent Plugin Structure

Each agent plugin follows this standard structure:

```
agents/{agent-type}/
├── {AgentType}Agent.php       # Main agent class (implements interface)
├── config.schema.json         # JSON Schema for configuration validation
├── README.md                  # Agent documentation
├── tools/                     # Optional: Custom tools
│   └── *.php
├── services/                  # Optional: Supporting services
│   └── *.php
├── prompts/                   # Optional: Prompt templates
│   └── *.md
└── tests/                     # Agent-specific tests
    └── *Test.php
```

---

## 6. Agent Lifecycle

### 6.1 Lifecycle Phases

```
┌─────────────────────────────────────────────────────────┐
│                   1. DISCOVERY                          │
│  AgentRegistry scans /agents/ directory                 │
│  Finds classes implementing SpecializedAgentInterface   │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│                   2. REGISTRATION                       │
│  Registry validates agent class                         │
│  Loads metadata (type, name, version, schema)           │
│  Stores in-memory registry                              │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│                   3. INITIALIZATION                     │
│  Agent instance created on first request                │
│  Dependencies injected (DB, logger, config)             │
│  Agent's initialize() method called                     │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│                   4. EXECUTION                          │
│  For each incoming message:                             │
│    - buildContext()                                     │
│    - validateInput()                                    │
│    - process()                                          │
│    - prepareLLMMessages() (if needed)                   │
│    - formatOutput()                                     │
│    - validateOutput()                                   │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│                   5. CLEANUP                            │
│  At end of request:                                     │
│    - cleanup() method called                            │
│    - Resources released                                 │
│  Agent instance reused for subsequent requests          │
└─────────────────────────────────────────────────────────┘
```

### 6.2 Initialization Sequence

**On Application Startup** (or first agent-related request):

1. `AgentRegistry` instantiated with dependencies
2. `discoverAgents()` scans `/agents/` directory
3. Each agent class validated and registered
4. Metadata stored in registry

**On Agent-Specific Request**:

1. ChatHandler receives message with `agent_id`
2. AgentService retrieves agent record (includes `agent_type`)
3. ChatHandler requests specialized agent from registry
4. Registry instantiates agent (if not cached) and calls `initialize()`
5. Agent processes request through pipeline
6. Response returned to user

**On Application Shutdown/Request End**:

1. ChatHandler calls `cleanup()` on active agents
2. Resources released (connections, temp files, etc.)

---

## 7. Communication Protocol

### 7.1 Core System → Specialized Agent

**Data Flow:**

```php
// ChatHandler prepares context for specialized agent
$agentConfig = [
    'id' => 'agent-123',
    'agent_type' => 'wordpress',
    'tenant_id' => 'tenant-456',
    'conversation_id' => 'conv-789',
    'system_message' => '...',
    'model' => 'gpt-4o',
    'temperature' => 0.7,
    'tools_json' => [...],
    'specialized_config' => [
        // Agent-specific configuration from specialized_agent_configs table
        'wp_site_url' => 'https://example.com',
        'wp_api_key' => 'encrypted-key',
        'default_category' => 'Blog'
    ]
];

$messages = [
    ['role' => 'user', 'content' => 'Create a blog post about AI'],
    // ... conversation history
];

// ChatHandler invokes specialized agent
$context = $agent->buildContext($messages, $agentConfig);
$validatedInput = $agent->validateInput($messages, $context);
$processedData = $agent->process($validatedInput, $context);
// ... pipeline continues
```

### 7.2 Specialized Agent → Core System

**Callbacks & Hooks:**

Specialized agents can interact with core system via dependency injection:

```php
// In specialized agent's process() method

// 1. Database queries
$conversationHistory = $this->db->query(
    "SELECT * FROM audit_messages WHERE conversation_id = ?",
    [$context['conversation_id']]
);

// 2. Logging
$this->logger->info('Processing WordPress post creation', [
    'agent_id' => $context['agent_id'],
    'action' => 'create_post'
]);

// 3. Audit events
$this->auditService->logEvent([
    'event_type' => 'wordpress.post_created',
    'conversation_id' => $context['conversation_id'],
    'metadata' => ['post_id' => $postId]
]);

// 4. Webhooks
$this->webhookDispatcher->dispatch('agent.action_completed', [
    'agent_type' => 'wordpress',
    'action' => 'create_post',
    'result' => $result
]);
```

### 7.3 Message Contract

**Input Message Format:**

```php
[
    'role' => 'user' | 'assistant' | 'system',
    'content' => 'message text',
    'name' => 'optional function name',
    'tool_calls' => [...],  // Optional: OpenAI tool calls
    'tool_call_id' => '...', // Optional: for tool responses
]
```

**Output Format:**

```php
[
    'message' => [
        'role' => 'assistant',
        'content' => 'response text',
        'tool_calls' => [...] // Optional
    ],
    'metadata' => [
        'agent_type' => 'wordpress',
        'processing_time_ms' => 1234,
        'custom_data' => [...] // Agent-specific metadata
    ],
    'audit' => [
        'tokens_used' => 500,
        'model' => 'gpt-4o'
    ]
]
```

---

## 8. Configuration Management

### 8.1 Configuration Layers

Specialized agents support **multi-layered configuration**:

1. **Global Defaults** - `config.php`
2. **Agent Type Defaults** - Agent class defaults
3. **Agent Instance Config** - `specialized_agent_configs` table
4. **Runtime Overrides** - Request parameters

### 8.2 Configuration Schema

Each agent defines its configuration schema using JSON Schema:

**Example:** `agents/wordpress/config.schema.json`

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "title": "WordPress Agent Configuration",
  "required": ["wp_site_url", "wp_api_key"],
  "properties": {
    "wp_site_url": {
      "type": "string",
      "format": "uri",
      "description": "WordPress site URL (e.g., https://example.com)"
    },
    "wp_api_key": {
      "type": "string",
      "description": "WordPress Application Password or JWT token",
      "minLength": 10
    },
    "wp_username": {
      "type": "string",
      "description": "WordPress username for authentication"
    },
    "default_category": {
      "type": "string",
      "description": "Default category for posts",
      "default": "Uncategorized"
    },
    "default_status": {
      "type": "string",
      "enum": ["draft", "publish", "pending"],
      "default": "draft",
      "description": "Default post status"
    },
    "auto_publish": {
      "type": "boolean",
      "default": false,
      "description": "Automatically publish posts without review"
    },
    "content_filters": {
      "type": "array",
      "items": {
        "type": "string"
      },
      "description": "Content filters to apply (e.g., ['shortcode', 'wpautop'])"
    }
  }
}
```

### 8.3 Configuration API

**Admin API Endpoints** (additions to `admin-api.php`):

```
GET    /admin-api.php?action=list_agent_types
       - List all available specialized agent types

GET    /admin-api.php?action=get_agent_type&type=wordpress
       - Get metadata and schema for specific agent type

POST   /admin-api.php?action=configure_specialized_agent
       - Set specialized configuration for an agent
       Body: {
           "agent_id": "agent-123",
           "agent_type": "wordpress",
           "config": { ... }  // Validated against schema
       }

GET    /admin-api.php?action=get_specialized_config&agent_id=agent-123
       - Retrieve specialized configuration for agent

DELETE /admin-api.php?action=delete_specialized_config&agent_id=agent-123
       - Remove specialized configuration (revert to generic)
```

### 8.4 Environment Variable Support

Sensitive configuration (API keys, credentials) support environment variables:

```php
// In specialized agent config
[
    'wp_api_key' => '${WP_API_KEY}',  // References env variable
    'wp_site_url' => '${WP_SITE_URL}'
]

// AgentRegistry resolves environment variables before passing to agent
$resolvedConfig = $this->resolveEnvironmentVariables($config);
```

---

## 9. Error Handling & Logging

### 9.1 Error Handling Strategy

**Hierarchical Error Handling:**

1. **Agent-Level Errors** - Handled by agent's `handleError()` method
2. **Pipeline Errors** - Caught by ChatHandler, logged, user-friendly message returned
3. **Critical Errors** - Escalated to global exception handler

**Graceful Degradation:**

- If specialized agent fails to load → fall back to generic agent
- If agent processing throws exception → return error via `handleError()`
- If LLM call fails → return cached response or error message

### 9.2 Error Types

**Custom Exception Classes:**

```php
// includes/Exceptions/AgentException.php
class AgentException extends \Exception {}
class AgentNotFoundException extends AgentException {}
class AgentConfigurationException extends AgentException {}
class AgentValidationException extends AgentException {}
class AgentProcessingException extends AgentException {}
```

### 9.3 Logging Standards

**Structured Logging:**

All agents must use structured logging with context:

```php
$this->logger->info('WordPress post created', [
    'agent_id' => $context['agent_id'],
    'agent_type' => 'wordpress',
    'conversation_id' => $context['conversation_id'],
    'tenant_id' => $context['tenant_id'],
    'action' => 'create_post',
    'post_id' => $postId,
    'processing_time_ms' => $elapsed
]);
```

**Log Levels:**

- **DEBUG**: Detailed diagnostic information
- **INFO**: Normal agent operations (tool calls, API requests)
- **WARNING**: Recoverable errors, fallback scenarios
- **ERROR**: Agent processing failures
- **CRITICAL**: System-level failures

### 9.4 Audit Trail

**Agent Actions Logging:**

```sql
-- Extension to audit_events table
INSERT INTO audit_events (
    conversation_id,
    event_type,
    event_data_json,
    tenant_id,
    created_at
) VALUES (
    'conv-789',
    'agent.wordpress.post_created',
    '{"post_id": 123, "title": "...", "status": "draft"}',
    'tenant-456',
    CURRENT_TIMESTAMP
);
```

**Metrics Collection:**

- Agent processing time
- Success/failure rates per agent type
- Tool call frequency
- LLM token usage per agent type

---

## 10. Testing Strategy

### 10.1 Test Levels

#### Unit Tests

Test individual agent methods in isolation:

```php
// tests/Unit/Agents/WordPressAgentTest.php

class WordPressAgentTest extends TestCase
{
    public function testValidateInputWithValidData()
    {
        $agent = new WordPressAgent();
        $messages = [['role' => 'user', 'content' => 'Create post']];
        $context = ['agent_id' => '123'];

        $result = $agent->validateInput($messages, $context);

        $this->assertEquals($messages, $result);
    }

    public function testValidateInputThrowsExceptionForEmptyMessages()
    {
        $agent = new WordPressAgent();

        $this->expectException(ValidationException::class);
        $agent->validateInput([], []);
    }
}
```

#### Integration Tests

Test agent interaction with core system:

```php
// tests/Integration/SpecializedAgentIntegrationTest.php

class SpecializedAgentIntegrationTest extends TestCase
{
    public function testWordPressAgentProcessesMessageEndToEnd()
    {
        $db = $this->createTestDatabase();
        $registry = new AgentRegistry($db, './agents', $logger);
        $registry->discoverAgents();

        $chatHandler = new ChatHandler($config);
        $chatHandler->setAgentRegistry($registry);

        $response = $chatHandler->handleChatCompletion(
            'Create a blog post about AI',
            'conv-123',
            'agent-wordpress-1',
            'tenant-456'
        );

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('assistant', $response['message']['role']);
    }
}
```

#### End-to-End Tests

Test complete request/response cycle via HTTP:

```php
// tests/E2E/WordPressAgentE2ETest.php

class WordPressAgentE2ETest extends TestCase
{
    public function testCreatePostViaAPI()
    {
        $response = $this->postJson('/chat-unified.php', [
            'message' => 'Create a post about Laravel',
            'conversation_id' => 'test-conv-1',
            'agent_id' => 'wp-agent-1'
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message' => ['role', 'content'],
            'metadata' => ['agent_type']
        ]);
    }
}
```

### 10.2 Test Data & Fixtures

**Test Fixtures Structure:**

```
tests/fixtures/
├── agents/
│   └── wordpress/
│       ├── messages.json           # Sample conversation messages
│       ├── agent_config.json       # Test agent configuration
│       └── api_responses.json      # Mock WordPress API responses
└── database/
    └── seed_specialized_agents.sql # Test database seed
```

### 10.3 Mocking Strategy

**Mock External Dependencies:**

```php
// Mock WordPress API calls
$mockWpClient = $this->createMock(WordPressApiClient::class);
$mockWpClient->method('createPost')
    ->willReturn(['id' => 123, 'status' => 'draft']);

$agent->setWordPressClient($mockWpClient);
```

### 10.4 Continuous Integration

**GitHub Actions Workflow:**

```yaml
name: Specialized Agents CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Install dependencies
        run: composer install

      - name: Run agent unit tests
        run: vendor/bin/phpunit tests/Unit/Agents/

      - name: Run agent integration tests
        run: vendor/bin/phpunit tests/Integration/
```

---

## 11. Agent Template & Skeleton

### 11.1 Template Agent Class

**Location:** `agents/_template/TemplateAgent.php`

```php
<?php

namespace ChatbotBoilerplate\Agents;

use ChatbotBoilerplate\Agents\AbstractSpecializedAgent;

/**
 * Template Specialized Agent
 *
 * This is a skeleton agent that developers can copy and customize.
 * Replace "Template" with your agent type name (e.g., WordPress, LinkedIn).
 */
class TemplateAgent extends AbstractSpecializedAgent
{
    // ==================== REQUIRED METADATA ====================

    public function getAgentType(): string
    {
        return 'template'; // CHANGE THIS: unique identifier (lowercase, no spaces)
    }

    public function getDisplayName(): string
    {
        return 'Template Agent'; // CHANGE THIS: human-readable name
    }

    public function getDescription(): string
    {
        return 'A template agent for creating new specialized agents.'; // CHANGE THIS
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getConfigSchema(): array
    {
        // CUSTOMIZE THIS: Define your agent's configuration schema
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'example_setting' => [
                    'type' => 'string',
                    'description' => 'An example configuration setting'
                ]
            ]
        ];
    }

    // ==================== CORE PROCESSING ====================

    /**
     * Initialize agent with dependencies
     *
     * Override this if you need custom initialization.
     */
    public function initialize(array $dependencies): void
    {
        parent::initialize($dependencies);

        // TODO: Add custom initialization logic here
        // Example: $this->apiClient = new YourApiClient();
    }

    /**
     * Build processing context
     *
     * Override to add custom context data.
     */
    public function buildContext(array $messages, array $agentConfig): array
    {
        $context = parent::buildContext($messages, $agentConfig);

        // TODO: Add agent-specific context
        // $context['custom_data'] = $this->extractCustomData($messages);

        return $context;
    }

    /**
     * Validate input messages
     *
     * Override to add custom validation rules.
     */
    public function validateInput(array $messages, array $context): array
    {
        // Basic validation from parent class
        $validatedMessages = parent::validateInput($messages, $context);

        // TODO: Add custom validation
        // Example: Check for required fields, content type, etc.

        return $validatedMessages;
    }

    /**
     * Core processing logic
     *
     * IMPLEMENT THIS: Your agent's main processing logic.
     */
    public function process(array $input, array $context): array
    {
        $this->logInfo('Processing started', ['context' => $context]);

        // TODO: Implement your agent's processing logic here
        // Example steps:
        // 1. Extract information from input
        // 2. Call external APIs
        // 3. Transform data
        // 4. Prepare for LLM (if needed)

        $processedData = [
            'messages' => $input,
            'custom_data' => [
                // Your processed data here
            ]
        ];

        return $processedData;
    }

    /**
     * Determine if LLM interaction is required
     *
     * Override if your agent has conditional LLM usage.
     */
    public function requiresLLM(array $processedData, array $context): bool
    {
        // TODO: Add custom logic
        // Return false if you can respond without LLM

        return true; // Default: always use LLM
    }

    /**
     * Prepare messages for LLM
     *
     * Override to customize LLM messages.
     */
    public function prepareLLMMessages(array $processedData, array $context): array
    {
        // TODO: Customize messages sent to LLM
        // Example: Add system message, inject context, format user input

        $messages = $processedData['messages'];

        // Add custom system message
        array_unshift($messages, [
            'role' => 'system',
            'content' => 'You are a specialized assistant. ' .
                         $context['agent_config']['system_message']
        ]);

        return $messages;
    }

    /**
     * Format output for response
     *
     * Override to customize response formatting.
     */
    public function formatOutput(array $processedData, array $context): array
    {
        $output = parent::formatOutput($processedData, $context);

        // TODO: Add custom formatting
        // Example: Add metadata, transform response, inject links

        $output['metadata']['custom_field'] = 'custom_value';

        return $output;
    }

    // ==================== CUSTOM TOOLS (OPTIONAL) ====================

    /**
     * Get custom tools for this agent
     *
     * Override to add agent-specific tools/functions.
     */
    public function getCustomTools(): array
    {
        // TODO: Define custom tools using OpenAI function schema
        return [
            // Example:
            // [
            //     'type' => 'function',
            //     'function' => [
            //         'name' => 'example_tool',
            //         'description' => 'An example custom tool',
            //         'parameters' => [
            //             'type' => 'object',
            //             'properties' => [
            //                 'param1' => ['type' => 'string']
            //             ],
            //             'required' => ['param1']
            //         ]
            //     ]
            // ]
        ];
    }

    /**
     * Execute custom tool
     *
     * Override to implement tool execution logic.
     */
    public function executeCustomTool(string $toolName, array $arguments, array $context): array
    {
        // TODO: Implement tool execution

        switch ($toolName) {
            case 'example_tool':
                return $this->handleExampleTool($arguments, $context);

            default:
                return parent::executeCustomTool($toolName, $arguments, $context);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Example helper method for custom tool
     */
    private function handleExampleTool(array $arguments, array $context): array
    {
        // TODO: Implement tool logic

        return [
            'success' => true,
            'result' => 'Tool executed successfully'
        ];
    }

    /**
     * Cleanup resources
     *
     * Override if you need to release resources (connections, temp files, etc.)
     */
    public function cleanup(): void
    {
        parent::cleanup();

        // TODO: Add cleanup logic
        // Example: Close API connections, delete temp files
    }
}
```

### 11.2 Configuration Schema Template

**Location:** `agents/_template/config.schema.json`

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "title": "Template Agent Configuration",
  "description": "Configuration schema for Template Agent",
  "required": [],
  "properties": {
    "example_setting": {
      "type": "string",
      "description": "An example configuration setting",
      "default": "default_value"
    },
    "api_endpoint": {
      "type": "string",
      "format": "uri",
      "description": "API endpoint URL"
    },
    "api_key": {
      "type": "string",
      "description": "API authentication key (supports env variables: ${VAR_NAME})",
      "minLength": 10
    },
    "enable_feature_x": {
      "type": "boolean",
      "description": "Enable feature X",
      "default": false
    },
    "options": {
      "type": "array",
      "items": {
        "type": "string"
      },
      "description": "List of options"
    }
  }
}
```

### 11.3 README Template

**Location:** `agents/_template/README.md`

```markdown
# Template Agent

## Overview

Brief description of what this agent does.

## Features

- Feature 1
- Feature 2
- Feature 3

## Configuration

This agent requires the following configuration:

| Setting | Type | Required | Description |
|---------|------|----------|-------------|
| `example_setting` | string | No | Description of setting |
| `api_endpoint` | string (URI) | Yes | API endpoint URL |
| `api_key` | string | Yes | API authentication key |

### Example Configuration

```json
{
  "example_setting": "value",
  "api_endpoint": "https://api.example.com",
  "api_key": "${EXAMPLE_API_KEY}"
}
```

## Custom Tools

This agent provides the following custom tools:

### `example_tool`

Description of what this tool does.

**Parameters:**
- `param1` (string, required): Description

**Returns:**
- `result` (string): Description

## Usage Example

```php
// Create agent in admin panel
POST /admin-api.php?action=create_agent
{
  "name": "My Template Agent",
  "agent_type": "template",
  "system_message": "You are a helpful assistant."
}

// Configure specialized settings
POST /admin-api.php?action=configure_specialized_agent
{
  "agent_id": "agent-123",
  "agent_type": "template",
  "config": {
    "example_setting": "custom_value",
    "api_endpoint": "https://api.example.com",
    "api_key": "${EXAMPLE_API_KEY}"
  }
}
```

## Development

### Running Tests

```bash
vendor/bin/phpunit tests/Unit/Agents/TemplateAgentTest.php
```

### Adding Custom Tools

1. Define tool in `getCustomTools()` method
2. Implement execution logic in `executeCustomTool()`
3. Add tests for tool functionality

## License

MIT
```

---

## 12. Migration Plan

### 12.1 Migration Phases

#### Phase 1: Foundation (Non-Breaking)

**Goal:** Add infrastructure without affecting existing functionality

**Tasks:**
1. Create database migration for specialized agent tables
2. Add `AgentRegistry` service
3. Add `SpecializedAgentInterface` and `AbstractSpecializedAgent`
4. Create `/agents/` directory structure
5. Update `config.php` with agent registry settings
6. Run migration on staging environment
7. Test existing agents (ensure no regressions)

**Database Migration:** `db/migrations/047_add_specialized_agent_support.sql`

```sql
BEGIN TRANSACTION;

-- Add agent_type column to existing agents table
ALTER TABLE agents ADD COLUMN agent_type TEXT DEFAULT 'generic' NOT NULL;
CREATE INDEX idx_agents_agent_type ON agents(agent_type);

-- Specialized agent configurations table
CREATE TABLE IF NOT EXISTS specialized_agent_configs (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    agent_id TEXT NOT NULL,
    agent_type TEXT NOT NULL,
    config_json TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE(agent_id)
);

-- Agent type metadata table
CREATE TABLE IF NOT EXISTS agent_type_metadata (
    agent_type TEXT PRIMARY KEY,
    display_name TEXT NOT NULL,
    description TEXT,
    version TEXT,
    enabled BOOLEAN DEFAULT 1,
    config_schema_json TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Set all existing agents to 'generic' type (redundant but explicit)
UPDATE agents SET agent_type = 'generic' WHERE agent_type IS NULL OR agent_type = '';

COMMIT;
```

**Rollback Plan:**

```sql
BEGIN TRANSACTION;

-- Remove new tables
DROP TABLE IF EXISTS specialized_agent_configs;
DROP TABLE IF EXISTS agent_type_metadata;

-- Remove agent_type column (SQLite limitation: requires table recreation)
CREATE TABLE agents_backup AS SELECT
    id, name, slug, description, api_type, prompt_id, prompt_version,
    system_message, model, temperature, top_p, max_output_tokens,
    tools_json, vector_store_ids_json, max_num_results, response_format_json,
    is_default, tenant_id, active_prompt_version, created_at, updated_at
FROM agents;

DROP TABLE agents;
ALTER TABLE agents_backup RENAME TO agents;

COMMIT;
```

#### Phase 2: ChatHandler Integration (Additive)

**Goal:** Add specialized agent support to ChatHandler with fallback

**Tasks:**
1. Modify `ChatHandler` to accept `AgentRegistry` dependency
2. Add `processMessageWithSpecialization()` method
3. Add `executeSpecializedPipeline()` method
4. Implement fallback logic (use generic processing if specialized fails)
5. Add logging for specialized agent invocations
6. Test with mock specialized agent

**Changes to** `includes/ChatHandler.php`:

- Add optional `AgentRegistry` property
- Add `setAgentRegistry()` method
- Modify message processing to check for `agent_type !== 'generic'`
- Wrap specialized processing in try-catch with fallback

**Testing:**
- Verify existing agents still work (no registry = generic processing)
- Verify new agents with `agent_type = 'generic'` use standard flow
- Verify specialized agents are invoked when available

#### Phase 3: Admin API Extensions (Additive)

**Goal:** Add endpoints for specialized agent management

**Tasks:**
1. Add `list_agent_types` endpoint
2. Add `get_agent_type` endpoint
3. Add `configure_specialized_agent` endpoint
4. Add `get_specialized_config` endpoint
5. Add config validation using JSON Schema
6. Update admin UI to show agent types dropdown
7. Update agent creation form with agent type selection

**New Endpoints:**

```php
// In admin-api.php

case 'list_agent_types':
    requireAuth();
    $types = $agentRegistry->listAvailableTypes();
    sendJsonResponse(['agent_types' => $types]);
    break;

case 'configure_specialized_agent':
    requireAuth();
    $agentId = $_POST['agent_id'] ?? null;
    $agentType = $_POST['agent_type'] ?? null;
    $config = $_POST['config'] ?? [];

    // Validate config against schema
    $errors = $agentRegistry->validateConfig($agentType, $config);
    if (!empty($errors)) {
        sendJsonResponse(['error' => 'Invalid configuration', 'details' => $errors], 400);
    }

    $specializedAgentService->saveConfig($agentId, $agentType, $config);
    sendJsonResponse(['success' => true]);
    break;
```

#### Phase 4: First Specialized Agent (WordPress)

**Goal:** Implement and deploy WordPress agent as proof of concept

**Tasks:**
1. Create `/agents/wordpress/` directory
2. Implement `WordPressAgent` class
3. Create configuration schema
4. Add WordPress-specific tools (create post, update post, search)
5. Write unit tests
6. Write integration tests
7. Deploy to staging
8. Test end-to-end
9. Document agent usage

#### Phase 5: Documentation & Developer Onboarding

**Goal:** Enable external developers to create agents

**Tasks:**
1. Write comprehensive developer guide
2. Create video walkthrough
3. Publish agent template to repository
4. Create example agents (LinkedIn, Biblical Writer)
5. Set up community support (Discord, GitHub Discussions)

### 12.2 Migration Timeline

| Phase | Duration | Milestones |
|-------|----------|-----------|
| Phase 1: Foundation | 1 week | Database migration, core services |
| Phase 2: ChatHandler Integration | 1 week | Specialized pipeline, fallback logic |
| Phase 3: Admin API | 1 week | CRUD endpoints, UI updates |
| Phase 4: WordPress Agent | 2 weeks | Implementation, testing, deployment |
| Phase 5: Documentation | 1 week | Guides, examples, community setup |
| **Total** | **6 weeks** | |

### 12.3 Rollback Strategy

**At Each Phase:**
- Feature flags in `config.php` to enable/disable specialized agents
- Database migrations reversible
- Code changes backward compatible
- A/B testing for gradual rollout

**Emergency Rollback:**

1. Set `config['specialized_agents']['enabled'] = false`
2. Revert ChatHandler changes (use git)
3. Run rollback migration (if needed)
4. Restart application

---

## 13. Security Considerations

### 13.1 Agent Isolation

**Tenant Isolation:**
- All agent operations scoped to `tenant_id`
- Specialized configs stored with tenant association
- Prevent cross-tenant data access in agent code

**Sandboxing:**
- Agents cannot access file system outside designated paths
- No direct database access (must use service layer)
- Network requests validated and logged

### 13.2 Configuration Security

**Sensitive Data:**
- API keys and credentials encrypted at rest
- Environment variable substitution for secrets
- Credentials never logged or returned in API responses

**Encryption:**

```php
// In SpecializedAgentService

private function encryptSensitiveConfig(array $config, array $schema): array
{
    foreach ($schema['properties'] as $key => $property) {
        if (isset($property['sensitive']) && $property['sensitive'] === true) {
            if (isset($config[$key])) {
                $config[$key] = $this->encrypt($config[$key]);
            }
        }
    }
    return $config;
}
```

### 13.3 Input Validation

**Schema Validation:**
- All agent configurations validated against JSON Schema
- User inputs sanitized before passing to agents
- Tool parameters validated before execution

**SQL Injection Prevention:**
- All database queries use parameterized statements
- No raw SQL in agent code

**XSS Prevention:**
- Agent outputs sanitized before rendering
- Content-Type headers set correctly

### 13.4 Rate Limiting

**Agent-Specific Limits:**

```php
// In config.php
'specialized_agents' => [
    'rate_limits' => [
        'wordpress' => [
            'max_posts_per_hour' => 10,
            'max_api_calls_per_minute' => 60
        ],
        'linkedin' => [
            'max_posts_per_day' => 5
        ]
    ]
]
```

**Implementation:**

```php
// In specialized agent
if (!$this->rateLimiter->allowAction('create_post', $context['tenant_id'])) {
    throw new RateLimitException('Rate limit exceeded for post creation');
}
```

### 13.5 Code Execution Prevention

**No Dynamic Code:**
- Agents cannot execute arbitrary PHP code
- No `eval()`, `exec()`, `system()` calls
- Tools defined declaratively

**Tool Whitelisting:**
- Only registered tools executable
- Tool execution monitored and logged

### 13.6 Audit & Compliance

**GDPR Compliance:**
- Agent actions logged in audit trail
- User data processed according to consent settings
- Data deletion extends to specialized agent data

**Audit Logging:**
- All specialized agent actions logged
- API calls to external services logged
- Configuration changes tracked

### 13.7 Dependency Security

**Third-Party Libraries:**
- Agents declare dependencies in `composer.json`
- Automated vulnerability scanning (Dependabot)
- Regular security updates

**API Client Security:**
- HTTPS enforced for external API calls
- Certificate validation enabled
- Timeout limits set

---

## 14. Developer Guide

### 14.1 Creating Your First Specialized Agent

#### Step 1: Choose Agent Type Identifier

Pick a unique, lowercase identifier (e.g., `wordpress`, `linkedin`, `biblical-writer`).

#### Step 2: Copy Template

```bash
cp -r agents/_template agents/your-agent-type
cd agents/your-agent-type
mv TemplateAgent.php YourAgentTypeAgent.php
```

#### Step 3: Implement Core Methods

Edit `YourAgentTypeAgent.php`:

```php
class YourAgentTypeAgent extends AbstractSpecializedAgent
{
    public function getAgentType(): string
    {
        return 'your-agent-type';
    }

    public function getDisplayName(): string
    {
        return 'Your Agent Type';
    }

    public function process(array $input, array $context): array
    {
        // Your logic here
        $result = $this->doSomething($input);

        return [
            'messages' => $input,
            'custom_data' => $result
        ];
    }
}
```

#### Step 4: Define Configuration Schema

Edit `config.schema.json`:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["api_key"],
  "properties": {
    "api_key": {
      "type": "string",
      "description": "Your API key",
      "minLength": 10,
      "sensitive": true
    }
  }
}
```

#### Step 5: Add Tests

Create `tests/YourAgentTypeAgentTest.php`:

```php
class YourAgentTypeAgentTest extends TestCase
{
    public function testProcessReturnsExpectedStructure()
    {
        $agent = new YourAgentTypeAgent();
        $agent->initialize([
            'db' => $this->createMock(DB::class),
            'logger' => $this->createMock(LoggerInterface::class),
            'config' => []
        ]);

        $result = $agent->process(
            [['role' => 'user', 'content' => 'test']],
            ['agent_id' => '123']
        );

        $this->assertArrayHasKey('messages', $result);
    }
}
```

#### Step 6: Test Your Agent

```bash
# Run unit tests
vendor/bin/phpunit agents/your-agent-type/tests/

# Test in development environment
# 1. Create agent in admin panel with agent_type = 'your-agent-type'
# 2. Configure specialized settings
# 3. Send test message via chat API
```

### 14.2 Best Practices

#### Code Organization

- Keep agent class focused on orchestration
- Extract complex logic into separate service classes
- Use dependency injection for external dependencies

#### Error Handling

- Always catch and log exceptions
- Return user-friendly error messages
- Implement graceful fallbacks

#### Performance

- Cache expensive operations
- Use async processing for slow external APIs
- Implement timeout limits

#### Testing

- Write tests first (TDD)
- Mock external dependencies
- Test error scenarios
- Test with real data in staging

#### Documentation

- Document all configuration options
- Provide usage examples
- Keep README updated
- Add inline code comments for complex logic

### 14.3 Common Patterns

#### Pattern: External API Integration

```php
public function process(array $input, array $context): array
{
    $apiClient = new YourApiClient($this->config['api_key']);

    try {
        $response = $apiClient->callApi([
            'data' => $this->extractData($input)
        ]);

        return [
            'messages' => $input,
            'api_response' => $response
        ];
    } catch (ApiException $e) {
        $this->logError('API call failed: ' . $e->getMessage());
        throw new AgentProcessingException('External API unavailable');
    }
}
```

#### Pattern: Multi-Step Workflow

```php
public function process(array $input, array $context): array
{
    // Step 1: Extract intent
    $intent = $this->detectIntent($input);

    // Step 2: Gather data
    $data = $this->gatherData($intent, $context);

    // Step 3: Transform
    $transformed = $this->transformData($data);

    // Step 4: Validate
    $this->validateData($transformed);

    return [
        'messages' => $input,
        'workflow_result' => $transformed
    ];
}
```

#### Pattern: Conditional LLM Usage

```php
public function requiresLLM(array $processedData, array $context): bool
{
    // If we have a cached response, skip LLM
    if (isset($processedData['cached_response'])) {
        return false;
    }

    // If user query is FAQ, use template response
    if ($this->isFaqQuestion($processedData)) {
        return false;
    }

    return true;
}
```

---

## 15. Appendix

### 15.1 Glossary

| Term | Definition |
|------|------------|
| **Specialized Agent** | An agent with custom processing logic for a specific domain (e.g., WordPress, LinkedIn) |
| **Generic Agent** | Standard agent using default ChatHandler processing |
| **Agent Type** | Unique identifier for an agent class (e.g., `wordpress`, `linkedin`) |
| **Agent Registry** | Service that discovers, registers, and manages specialized agent types |
| **Agent Pipeline** | Sequence of processing steps (validate → process → format → validate output) |
| **Configuration Schema** | JSON Schema defining valid configuration for an agent type |
| **Custom Tool** | Agent-specific function callable by LLM during processing |

### 15.2 Configuration Reference

**config.php Additions:**

```php
'specialized_agents' => [
    'enabled' => true,
    'agents_path' => __DIR__ . '/agents',
    'auto_discover' => true,
    'cache_registry' => true,
    'cache_ttl' => 3600,
    'fallback_to_generic' => true,
    'rate_limits' => [
        'default' => [
            'max_requests_per_minute' => 60
        ]
    ],
    'security' => [
        'encrypt_sensitive_config' => true,
        'allowed_network_hosts' => ['api.wordpress.org', 'api.linkedin.com'],
        'max_tool_execution_time' => 30
    ]
]
```

### 15.3 Database Schema Reference

**Complete Schema for Specialized Agents:**

```sql
-- Agents table (modified)
CREATE TABLE agents (
    id TEXT PRIMARY KEY,
    name TEXT UNIQUE,
    slug TEXT UNIQUE,
    description TEXT,
    api_type TEXT,
    agent_type TEXT DEFAULT 'generic' NOT NULL,  -- NEW COLUMN
    prompt_id TEXT,
    prompt_version TEXT,
    system_message TEXT,
    model TEXT,
    temperature REAL,
    top_p REAL,
    max_output_tokens INTEGER,
    tools_json TEXT,
    vector_store_ids_json TEXT,
    max_num_results INTEGER,
    response_format_json TEXT,
    is_default BOOLEAN DEFAULT 0,
    tenant_id TEXT,
    active_prompt_version INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX idx_agents_agent_type ON agents(agent_type);

-- Specialized agent configurations
CREATE TABLE specialized_agent_configs (
    id TEXT PRIMARY KEY,
    agent_id TEXT NOT NULL,
    agent_type TEXT NOT NULL,
    config_json TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE(agent_id)
);

-- Agent type metadata
CREATE TABLE agent_type_metadata (
    agent_type TEXT PRIMARY KEY,
    display_name TEXT NOT NULL,
    description TEXT,
    version TEXT,
    enabled BOOLEAN DEFAULT 1,
    config_schema_json TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

### 15.4 API Reference

**Admin API Endpoints:**

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin-api.php?action=list_agent_types` | GET | List available agent types |
| `/admin-api.php?action=get_agent_type&type={type}` | GET | Get agent type metadata |
| `/admin-api.php?action=configure_specialized_agent` | POST | Configure specialized agent |
| `/admin-api.php?action=get_specialized_config&agent_id={id}` | GET | Get agent configuration |
| `/admin-api.php?action=delete_specialized_config&agent_id={id}` | DELETE | Remove specialized config |

**Request/Response Examples:**

```http
POST /admin-api.php?action=configure_specialized_agent
Authorization: Bearer {api_key}
Content-Type: application/json

{
  "agent_id": "agent-123",
  "agent_type": "wordpress",
  "config": {
    "wp_site_url": "https://example.com",
    "wp_api_key": "${WP_API_KEY}",
    "default_category": "Blog"
  }
}

Response 200 OK:
{
  "success": true,
  "agent_id": "agent-123",
  "config_id": "config-456"
}
```

### 15.5 Example Agents

**WordPress Agent Use Cases:**
- Create blog posts from conversation
- Update existing content
- Search site content
- Manage categories and tags

**LinkedIn Agent Use Cases:**
- Draft professional posts
- Analyze engagement metrics
- Schedule content
- Network recommendations

**Biblical Writer Agent Use Cases:**
- Generate devotionals with Scripture references
- Theological content validation
- Citation formatting
- Doctrinal alignment checking

### 15.6 Troubleshooting

| Issue | Possible Cause | Solution |
|-------|---------------|----------|
| Agent not discovered | File naming mismatch | Ensure class name matches file name |
| Configuration validation fails | Invalid JSON Schema | Check schema syntax, required fields |
| Agent fallback to generic | Exception in process() | Check logs, implement error handling |
| Tool not executing | Tool not registered | Add to getCustomTools(), implement executeCustomTool() |
| Rate limit errors | Too many requests | Adjust rate limits in config |

### 15.7 Performance Benchmarks

**Expected Performance (measured on PHP 8.1, single-core):**

| Operation | Time (avg) | Notes |
|-----------|-----------|-------|
| Agent discovery (startup) | 50-100ms | One-time cost, cached |
| Agent instantiation | 5-10ms | Per request |
| Pipeline execution | 200-500ms | Depends on agent logic |
| LLM call | 1000-3000ms | Network latency |
| Total request (with LLM) | 1500-4000ms | End-to-end |

### 15.8 References

- [PHP PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)
- [JSON Schema Specification](https://json-schema.org/)
- [OpenAI API Documentation](https://platform.openai.com/docs/api-reference)
- [SQLite Documentation](https://www.sqlite.org/docs.html)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)

---

## Document Change Log

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | 2025-11-20 | Initial specification | Claude Code |

---

**End of Specification**
