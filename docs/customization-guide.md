# Customization Guide - Enhanced GPT Chatbot Boilerplate

This guide covers extensive customization options for both Chat Completions API and Responses API implementations, with practical examples and use cases.

## Table of Contents

- [API Selection & Configuration](#api-selection--configuration)
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

For more examples and updates, check the [`docs/`](./) directory and the main [README](../README.md).
