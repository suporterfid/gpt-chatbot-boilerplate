# Customization Guide - Enhanced GPT Chatbot Boilerplate

This guide covers extensive customization options for both Chat Completions API and Responses API implementations, with practical examples and use cases.

## Table of Contents

- [API Selection & Configuration](#api-selection--configuration)
- [Agent-Based Configuration](#agent-based-configuration)
- [Chat Completions Customization](#chat-completions-customization)
- [Responses API Customization](#responses-api-customization)
- [UI/UX Customization](#uiux-customization)
- [File Upload Customization](#file-upload-customization)
- [Custom Function Calling](#custom-function-calling)
- [Advanced Integration Examples](#advanced-integration-examples)
- [Industry-Specific Configurations](#industry-specific-configurations)

## API Selection & Configuration

### Choosing Between APIs

**Use Chat Completions API when:**
- You need simple conversational AI.
- Stateless interactions are sufficient.
- You want lower latency and costs.
- Simple question-answer scenarios.

**Use Responses API when:**
- You want to reuse prompt templates stored via `/v1/prompts`.
- File processing capabilities are required.
- Tool execution/interpretation is needed.
- Complex multi-turn conversations with function calls.
- You need explicit control over prompt versions.

### Dynamic API Switching

```javascript
// Allow users to switch between APIs dynamically
class AdaptiveChatBot {
    constructor(container, options) {
        this.chatbot = null;
        this.currentAPI = options.apiType || 'responses';
        this.options = options;
        this.initialize(container, options);
    }

    initialize(container, options) {
        this.chatbot = ChatBot.init(container, options);
    }

    switchAPI(newAPIType) {
        if (this.currentAPI === newAPIType) return;

        const messages = this.chatbot.messages;
        this.chatbot.destroy();

        this.currentAPI = newAPIType;
        const newOptions = { ...this.options, apiType: newAPIType };

        this.chatbot = ChatBot.init(this.chatbot.container, newOptions);
        this.restoreMessages(messages);
    }

    restoreMessages(messages) {
        if (!this.chatbot || !this.chatbot.messageContainer) return;

        this.chatbot.messages = [];
        this.chatbot.messageContainer.innerHTML = '';

        messages.forEach(msg => {
            this.chatbot.addMessage(msg);
        });
    }
}
```

## Agent-Based Configuration

The chatbot supports dynamic agent selection, allowing you to create multiple AI personalities with different configurations that can be selected at runtime. Agents are managed via the Admin UI or Admin API and can override any configuration parameter.

### Why Use Agents?

**Benefits:**
- **No Code Deployments**: Update prompts, models, and tools without redeploying
- **Multi-Personality Bots**: Different agents for different use cases
- **A/B Testing**: Test different configurations without code changes
- **User Segmentation**: Route different users to different agents
- **Centralized Management**: Manage all configurations from Admin UI

### Creating an Agent

#### Via Admin UI

1. Navigate to `/public/admin/`
2. Go to "Agents" section
3. Click "Create Agent"
4. Configure:
   - **Name**: Customer Support Bot
   - **API Type**: Responses
   - **Model**: gpt-4
   - **Prompt**: Select from OpenAI prompts
   - **Tools**: Enable file_search, add vector stores
   - **Temperature**: 0.7
5. Click "Save"
6. Optionally click "Make Default" to use this agent for all requests without explicit agent_id

#### Via Admin API

```bash
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Bot",
    "description": "Handles customer inquiries with knowledge base access",
    "api_type": "responses",
    "prompt_id": "prompt_abc123",
    "prompt_version": "v1",
    "model": "gpt-4",
    "temperature": 0.7,
    "top_p": 0.9,
    "max_output_tokens": 1000,
    "tools": [
      {
        "type": "file_search"
      }
    ],
    "vector_store_ids": ["vs_xyz789"],
    "max_num_results": 20
  }'
```

### Using Agents in Chat Requests

#### Option 1: Explicit Agent Selection

```javascript
// JavaScript widget - floating mode
const chatbot = ChatBot.init({
    mode: 'floating',
    apiEndpoint: '/chat-unified.php',
    // Pass agent_id with every request
    requestModifier: (payload) => {
        payload.agent_id = 'agent_uuid_here';
        return payload;
    }
});
```

```bash
# Direct API call
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is your return policy?",
    "agent_id": "agent_uuid_here",
    "conversation_id": "conv_123"
  }'
```

#### Option 2: Default Agent (No agent_id)

If you've marked an agent as default in the Admin UI, all requests without an explicit `agent_id` will use that agent:

```bash
# Uses default agent automatically
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is your return policy?",
    "conversation_id": "conv_123"
  }'
```

#### Option 3: Dynamic Agent Selection

```javascript
// Allow users to select agents dynamically
class MultiAgentChatBot {
    constructor(container, agents) {
        this.agents = agents; // Array of {id, name, description}
        this.currentAgentId = agents[0]?.id;
        this.chatbot = ChatBot.init(container, {
            requestModifier: (payload) => {
                payload.agent_id = this.currentAgentId;
                return payload;
            }
        });
        this.renderAgentSelector(container);
    }

    renderAgentSelector(container) {
        const selector = document.createElement('select');
        selector.className = 'agent-selector';
        
        this.agents.forEach(agent => {
            const option = document.createElement('option');
            option.value = agent.id;
            option.textContent = agent.name;
            selector.appendChild(option);
        });

        selector.addEventListener('change', (e) => {
            this.switchAgent(e.target.value);
        });

        container.prepend(selector);
    }

    switchAgent(agentId) {
        this.currentAgentId = agentId;
        console.log(`Switched to agent: ${agentId}`);
        // Note: Agent selection takes effect on next message
        // The updated agent_id will be sent with the next request
    }
}

// Usage
fetch('/admin-api.php?action=list_agents')
    .then(r => r.json())
    .then(data => {
        const chatbot = new MultiAgentChatBot('#chat', data.data);
    });
```

### Agent Configuration Examples

#### Example 1: Technical Support Agent

```json
{
    "name": "Technical Support",
    "api_type": "responses",
    "prompt_id": "tech_support_prompt",
    "model": "gpt-4",
    "temperature": 0.3,
    "tools": [
        {"type": "file_search"}
    ],
    "vector_store_ids": ["vs_tech_docs"],
    "max_num_results": 10
}
```

**Use Case**: Precise, factual responses based on technical documentation.

#### Example 2: Creative Writing Assistant

```json
{
    "name": "Creative Writer",
    "api_type": "chat",
    "system_message": "You are a creative writing assistant. Help users brainstorm ideas, develop characters, and craft compelling narratives.",
    "model": "gpt-4",
    "temperature": 1.2,
    "top_p": 0.95,
    "max_output_tokens": 2000
}
```

**Use Case**: High creativity for brainstorming and storytelling.

#### Example 3: Sales Assistant with Product Catalog

```json
{
    "name": "Sales Assistant",
    "api_type": "responses",
    "prompt_id": "sales_prompt",
    "model": "gpt-3.5-turbo",
    "temperature": 0.7,
    "tools": [
        {"type": "file_search"}
    ],
    "vector_store_ids": ["vs_product_catalog", "vs_sales_materials"],
    "max_num_results": 20
}
```

**Use Case**: Answer product questions using catalog and sales materials.

### Configuration Merging Priority

When using agents, configuration values are merged with the following priority:

1. **Request Parameters** (highest priority)
2. **Agent Configuration**
3. **config.php Defaults** (lowest priority)

**Example:**
```javascript
// Agent has: model = "gpt-4", temperature = 0.7
// Request overrides model
const chatbot = ChatBot.init('#chat-container', {
    requestModifier: (payload) => {
        payload.agent_id = 'agent_123';
        payload.model = 'gpt-3.5-turbo'; // Overrides agent's model
        return payload;
    }
});
// Result: Uses gpt-3.5-turbo with temperature 0.7
```

### Testing Agents

#### Via Admin UI
1. Navigate to Agents page
2. Click "Test" button on any agent
3. Enter a test message
4. See streaming response with agent configuration applied

#### Via API
```bash
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Test message",
    "agent_id": "agent_uuid",
    "stream": false
  }'
```

### Best Practices

1. **Use Descriptive Names**: Name agents clearly (e.g., "Customer Support - English", "Sales Bot - EU")
2. **Set Appropriate Temperatures**: Lower (0.1-0.4) for factual, higher (0.7-1.5) for creative
3. **Leverage Default Agent**: Set a sensible default for backward compatibility
4. **Version Prompts**: Use versioned prompts to track changes and roll back if needed
5. **Monitor Performance**: Use audit logs to track which agents are used and their performance
6. **Test Thoroughly**: Use the Test feature before deploying agents to production
7. **Document Agents**: Use the description field to document each agent's purpose

### Troubleshooting

**Agent not found error:**
- Verify agent_id exists via Admin UI
- Check that Admin is enabled in .env (ADMIN_ENABLED=true)
- Ensure database is accessible

**Agent changes not applying:**
- Agents are loaded per-request (no caching by default)
- Verify request includes correct agent_id
- Check server logs for agent resolution messages

**Fallback to config.php:**
- Normal behavior when no agent_id provided and no default agent set
- To use agents, either pass agent_id or set a default agent

## Chat Completions Customization

### Custom System Prompts

```php
// config.php - Dynamic system prompts
'chat' => [
    'system_message_templates' => [
        'support' => 'You are a helpful customer support representative for {company_name}. Be professional and empathetic.',
        'sales' => 'You are a knowledgeable sales assistant for {company_name}. Focus on helping customers find the right products.',
        'technical' => 'You are a technical expert specializing in {domain}. Provide accurate, detailed technical information.',
        'casual' => 'You are a friendly AI assistant. Be conversational and helpful while maintaining a casual tone.'
    ]
]
```

```javascript
// Frontend - Dynamic system prompt selection
ChatBot.init({
    apiType: 'chat',
    systemPromptType: 'support',
    companyName: 'Acme Corp',

    preprocessMessage(message) {
        return {
            message,
            context: {
                page_url: window.location.href,
                time_of_day: new Date().getHours()
            }
        };
    }
});
```

### Conversation Memory & Context

```javascript
class ContextualChatBot extends ChatBot {
    constructor(container, options) {
        super(container, options);
        this.conversationContext = {
            userPreferences: {},
            sessionData: {},
            previousTopics: []
        };
    }

    enhanceMessage(message) {
        return {
            content: message,
            context: {
                previous_topics: this.conversationContext.previousTopics,
                user_preferences: this.conversationContext.userPreferences,
                session_data: this.conversationContext.sessionData,
                timestamp: new Date().toISOString(),
                page_context: {
                    url: window.location.href,
                    referrer: document.referrer
                }
            }
        };
    }
}
```

## Responses API Customization

### Referencing Saved Prompts

```php
// config.php
'responses' => [
    'model' => 'gpt-4.1-mini',
    'prompt_id' => getenv('RESPONSES_PROMPT_ID'),
    'prompt_version' => getenv('RESPONSES_PROMPT_VERSION') ?: '1',
    'temperature' => 0.7,
    'max_output_tokens' => 1024
];
```

```javascript
// Frontend usage
ChatBot.init({
    apiType: 'responses',
    responsesConfig: {
        promptId: 'pmpt_shared_instruction_set',
        promptVersion: 'latest',
        defaultTools: [
            { type: 'file_search' }
        ],
        defaultVectorStoreIds: ['vs_1234567890'],
        defaultMaxNumResults: 12
    }
});
```

> **Server defaults:** The PHP config automatically hydrates these settings from `RESPONSES_TOOLS`, `RESPONSES_VECTOR_STORE_IDS`, and `RESPONSES_MAX_NUM_RESULTS`. Supply either JSON arrays (`[{"type":"file_search"}]`) or comma-separated lists (`vs_123,vs_456`), and request overrides merge on top of the configured defaults.

### Mixing Prompt Templates with Inline Context

```php
// ChatHandler snippet
$messages[] = [
    'role' => 'user',
    'content' => "Summarize the meeting notes",
    'file_ids' => [$uploadedFileId]
];

$payload = [
    'model' => $config['responses']['model'],
    'prompt' => [
        'id' => $config['responses']['prompt_id'],
        'version' => $config['responses']['prompt_version']
    ],
    'input' => $this->formatMessagesForResponses($messages)
];
```

### Handling Tool Calls

```php
// ChatHandler::executeTool
private function executeTool($toolCall) {
    $function = $toolCall['function'] ?? [];
    $name = $function['name'] ?? '';
    $args = json_decode($function['arguments'] ?? '{}', true) ?: [];

    switch ($name) {
        case 'lookup_order':
            return $this->orderService->lookup($args['order_id'] ?? null);
        case 'get_weather':
            return $this->weatherClient->current($args['location'] ?? '');
        default:
            return ['error' => 'Unknown function: ' . $name];
    }
}
```

### Streaming Event Handling

```javascript
handleStreamChunk(event) {
    if (event.type === 'chunk') {
        const content = this.resolveStreamText(event);
        this.appendToMessage(this.currentMessageElement, content);
    }

    if (event.type === 'tool_call') {
        console.info('Tool call in progress', event);
    }
}
```

## UI/UX Customization

- Customize colors via the CSS variables defined on `.chatbot-widget`.
- Provide custom avatars using `assistant.avatar`.
- Toggle timestamps, animations, and sounds through initialization options.

```javascript
ChatBot.init({
    theme: {
        primaryColor: '#0F172A',
        backgroundColor: '#F1F5F9',
        surfaceColor: '#FFFFFF',
        textColor: '#0F172A'
    },
    assistant: {
        name: 'Navigator',
        avatar: '/assets/navigator.png',
        welcomeMessage: 'Need help exploring our docs? I am here for you.'
    },
    timestamps: true,
    showTypingIndicator: true
});
```

## File Upload Customization

- Enable uploads via `ENABLE_FILE_UPLOAD=true`.
- Restrict file types using `ALLOWED_FILE_TYPES`.
- Extend the frontend to show thumbnails or icons based on file type.

```javascript
ChatBot.init({
    enableFileUpload: true,
    maxFileSize: 5 * 1024 * 1024,
    allowedFileTypes: ['pdf', 'docx', 'pptx'],

    onFileUpload(files) {
        files.forEach(file => console.log('Uploaded:', file.name));
    }
});
```

## Custom Function Calling

Define custom tools in your Responses prompt template and implement the same functions server-side.

```php
private function getKnowledgeBaseSummary(string $topic) {
    return $this->knowledgeBase->summarize($topic);
}
```

## Advanced Integration Examples

### Customer Support Playbook

```javascript
ChatBot.init({
    apiType: 'responses',
    mode: 'floating',
    assistant: {
        name: 'Support Pro',
        welcomeMessage: 'Hi! I can assist with orders, billing, and technical issues.'
    },
    responsesConfig: {
        promptId: 'pmpt_customer_support_v2'
    },
    onToolCall(tool) {
        if (tool.name === 'lookup_order') {
            analytics.track('order_lookup', tool.arguments);
        }
    }
});
```

### Technical Troubleshooting

```javascript
ChatBot.init({
    apiType: 'responses',
    assistant: {
        name: 'Diagnostics Bot',
        welcomeMessage: 'Upload logs or describe the issue. I will help you troubleshoot.'
    },
    enableFileUpload: true,
    allowedFileTypes: ['txt', 'log'],
    responsesConfig: {
        promptId: 'pmpt_troubleshooting_guide'
    }
});
```

## Industry-Specific Configurations

- **Healthcare**: Use prompt templates focused on compliance guidance; restrict uploads to PDF/text.
- **Finance**: Integrate custom tools for account lookup with strict audit logging.
- **Education**: Provide lesson plan prompts and allow file uploads for assignments.

---

## Additional Resources

For comprehensive documentation on the system architecture and implementation:

- **[Implementation Report](IMPLEMENTATION_REPORT.md)** - Complete implementation details and production readiness assessment
- **[Implementation Plan](IMPLEMENTATION_PLAN.md)** - Detailed phase-by-phase implementation with status
- **[API Documentation](api.md)** - Complete API reference
- **[Deployment Guide](deployment.md)** - Deployment and operations guide

---

For more examples and updates, check the [`docs/`](./) directory and the main [README](../README.md).
