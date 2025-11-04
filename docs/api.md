# API Documentation - GPT Chatbot Boilerplate

This document provides comprehensive API documentation for the GPT Chatbot Boilerplate, covering both client-side JavaScript API and server-side HTTP endpoints.

## Table of Contents

- [JavaScript Client API](#javascript-client-api)
- [HTTP API Endpoints](#http-api-endpoints)
- [WebSocket API](#websocket-api)
- [Configuration API](#configuration-api)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Examples](#examples)

## JavaScript Client API

### ChatBot Class

The main `ChatBot` class provides the interface for initializing and controlling the chatbot widget.

#### Static Methods

##### `ChatBot.init(container, options)`

Initializes a new chatbot instance.

**Parameters:**
- `container` (string|Element|null): CSS selector, DOM element, or null for floating mode
- `options` (object): Configuration options

**Returns:** ChatBot instance

**Example:**
```javascript
// Floating mode
const chatbot = ChatBot.init({
    mode: 'floating',
    apiEndpoint: '/chat-unified.php'
});

// Inline mode
const chatbot = ChatBot.init('#chat-container', {
    mode: 'inline',
    height: '500px'
});
```

##### `ChatBot.destroyAll()`

Destroys all active chatbot instances.

**Example:**
```javascript
ChatBot.destroyAll();
```

### Configuration Options

#### Basic Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `mode` | string | `'inline'` | Widget mode: `'inline'` or `'floating'` |
| `position` | string | `'bottom-right'` | Floating position: `'bottom-right'` or `'bottom-left'` |
| `title` | string | `'Chat Assistant'` | Widget title |
| `height` | string | `'400px'` | Widget height |
| `width` | string | `'350px'` | Widget width |
| `show` | boolean | `false` | Auto-show floating widget |

#### API Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiEndpoint` | string | `'/chat-unified.php'` | SSE/AJAX endpoint URL |
| `websocketEndpoint` | string | `'ws://localhost:8080'` | WebSocket server URL |
| `streamingMode` | string | `'auto'` | Connection mode: `'auto'`, `'sse'`, `'websocket'`, `'ajax'` |
| `maxMessages` | number | `50` | Maximum messages in memory |
| `enableMarkdown` | boolean | `true` | Enable markdown formatting |

#### Theme Customization

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `theme.primaryColor` | string | `'#1FB8CD'` | Primary brand color |
| `theme.backgroundColor` | string | `'#F5F5F5'` | Background color |
| `theme.surfaceColor` | string | `'#FFFFFF'` | Surface/card color |
| `theme.textColor` | string | `'#333333'` | Text color |
| `theme.mutedColor` | string | `'#666666'` | Muted text color |
| `theme.fontFamily` | string | `'inherit'` | Font family |
| `theme.fontSize` | string | `'14px'` | Base font size |
| `theme.borderRadius` | string | `'8px'` | Border radius |
| `theme.shadow` | string | `'0 4px 6px -1px rgba(0, 0, 0, 0.1)'` | Box shadow |

#### Assistant Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `assistant.name` | string | `'Assistant'` | Assistant display name |
| `assistant.avatar` | string | `null` | Avatar image URL |
| `assistant.welcomeMessage` | string | `'Hello! How can I help you today?'` | Welcome message |
| `assistant.placeholder` | string | `'Type your message...'` | Input placeholder |
| `assistant.thinking` | string | `'Assistant is thinking...'` | Typing indicator text |

#### UI Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `animations` | boolean | `true` | Enable animations |
| `sound` | boolean | `false` | Enable sound effects |
| `timestamps` | boolean | `false` | Show message timestamps |
| `autoScroll` | boolean | `true` | Auto-scroll to new messages |

#### Callback Functions

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `onMessage` | function | `null` | Called when message is added |
| `onError` | function | `null` | Called when error occurs |
| `onConnect` | function | `null` | Called when connected |
| `onDisconnect` | function | `null` | Called when disconnected |
| `onTyping` | function | `null` | Called when typing status changes |

### Instance Methods

#### `show()`

Shows the chatbot widget (floating mode only).

**Example:**
```javascript
chatbot.show();
```

#### `hide()`

Hides the chatbot widget (floating mode only).

**Example:**
```javascript
chatbot.hide();
```

#### `toggle()`

Toggles chatbot visibility (floating mode only).

**Example:**
```javascript
chatbot.toggle();
```

#### `sendMessageProgrammatically(message)`

Sends a message programmatically.

**Parameters:**
- `message` (string): Message to send

**Example:**
```javascript
chatbot.sendMessageProgrammatically('Hello, how are you?');
```

#### `clearHistory()`

Clears the conversation history.

**Example:**
```javascript
chatbot.clearHistory();
```

#### `destroy()`

Destroys the chatbot instance and removes it from DOM.

**Example:**
```javascript
chatbot.destroy();
```

### Events and Callbacks

#### onMessage

Called when a new message is added to the conversation.

```javascript
ChatBot.init({
    onMessage: function(message) {
        console.log('New message:', message);
        // message object contains: role, content, timestamp
    }
});
```

#### onError

Called when an error occurs.

```javascript
ChatBot.init({
    onError: function(error) {
        console.error('Chat error:', error);
        // Handle error (show notification, log, etc.)
    }
});
```

#### onConnect

Called when connection is established.

```javascript
ChatBot.init({
    onConnect: function() {
        console.log('Connected to chat server');
    }
});
```

#### onDisconnect

Called when connection is lost.

```javascript
ChatBot.init({
    onDisconnect: function() {
        console.log('Disconnected from chat server');
    }
});
```

#### onTyping

Called when typing status changes.

```javascript
ChatBot.init({
    onTyping: function(isTyping) {
        console.log('Assistant typing:', isTyping);
    }
});
```

### Auto-initialization

The widget can be auto-initialized using data attributes:

```html
<div id="chatbot" data-chatbot='{"mode": "inline", "height": "600px"}'></div>
```

## HTTP API Endpoints

### POST /chat-unified.php

Main chat endpoint that handles both SSE streaming and regular AJAX requests.

#### Request

**Headers:**
```
Content-Type: application/json
Accept: text/event-stream (for SSE) or application/json (for AJAX)
```

**Body:**
```json
{
    "message": "Hello, how are you?",
    "conversation_id": "conv_abc123",
    "agent_id": "agent-uuid-here",
    "stream": true
}
```

**Parameters:**
- `message` (string, required): User's message
- `conversation_id` (string, optional): Unique conversation identifier
- `agent_id` (string, optional): UUID of the agent to use for this request. If not provided, the default agent (if set) will be used, otherwise falls back to config.php settings
- `stream` (boolean, optional): Enable streaming response (default: true for SSE, false for AJAX)

#### Response (SSE)

Server-Sent Events stream with the following event types:

**Start Event:**
```
event: start
data: {"conversation_id": "conv_abc123", "agent_id": "agent-uuid-here", "api_type": "responses"}
```

**Message Events:**
```
event: message
data: {"type": "start"}

event: message
data: {"type": "chunk", "content": "Hello"}

event: message
data: {"type": "chunk", "content": "! How"}

event: message
data: {"type": "done"}
```

**Error Event:**
```
event: error
data: {"message": "An error occurred"}
```

**Close Event:**
```
event: close
data: null
```

#### Response (AJAX)

**Success (200):**
```json
{
    "response": "Hello! I'm doing well, thank you for asking. How can I help you today?",
    "conversation_id": "conv_abc123",
    "tokens_used": 25,
    "model": "gpt-3.5-turbo"
}
```

**Error (400/500):**
```json
{
    "error": "Message is required",
    "code": "MISSING_MESSAGE",
    "details": {}
}
```

### GET /chat-unified.php

Alternative GET endpoint for SSE streaming (useful for EventSource API).

#### Request

**Query Parameters:**
- `message` (string, required): User's message (URL encoded)
- `conversation_id` (string, optional): Conversation identifier
- `agent_id` (string, optional): UUID of the agent to use

**Example:**
```
GET /chat-unified.php?message=Hello&conversation_id=conv_abc123&agent_id=agent-uuid-here
```

#### Response

Same SSE format as POST endpoint.

### Agent Selection Behavior

The chat endpoint supports dynamic agent selection via the `agent_id` parameter. This allows you to override the default chat behavior with agent-specific configurations.

#### Agent Selection Priority

The system follows this priority order when determining which configuration to use:

1. **Explicit agent_id**: If provided in the request, the agent configuration is loaded
2. **Default agent**: If no agent_id is provided, the system uses the agent marked as default
3. **Config fallback**: If no default agent exists, falls back to config.php settings

#### Configuration Merging

When an agent is selected, its configuration overrides config.php defaults. The merging precedence is:

**Request parameters > Agent configuration > config.php defaults**

For example:
- If request includes `prompt_id`, it overrides agent's prompt_id
- If agent specifies `model: "gpt-4"`, it overrides config.php model
- If neither request nor agent specify a value, config.php default is used

#### Agent Configuration Fields

An agent can override the following configuration fields:

- `api_type`: 'chat' or 'responses'
- `prompt_id`: OpenAI prompt identifier
- `prompt_version`: Specific prompt version
- `model`: OpenAI model (e.g., 'gpt-4', 'gpt-3.5-turbo')
- `temperature`: Randomness (0.0 - 2.0)
- `top_p`: Nucleus sampling parameter
- `max_output_tokens`: Maximum response length
- `system_message`: System prompt (for chat API)
- `tools`: Array of tool definitions
- `vector_store_ids`: Array of vector store IDs for file_search
- `max_num_results`: Maximum file search results

#### Example: Using an Agent

```bash
# Create an agent via Admin API
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Bot",
    "api_type": "responses",
    "prompt_id": "prompt_abc123",
    "model": "gpt-4",
    "temperature": 0.7,
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_xyz789"]
  }'

# Response includes agent UUID
# {"data": {"id": "agent_uuid_here", ...}}

# Use the agent in chat
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is your return policy?",
    "agent_id": "agent_uuid_here",
    "conversation_id": "conv_123"
  }'
```

#### Error Handling

- **Invalid agent_id**: Logs warning and falls back to default agent
- **Agent not found**: Falls back to default agent or config.php
- **No AgentService**: Falls back to config.php (backwards compatible)

### GET /health

Health check endpoint for monitoring.

#### Response

**Success (200):**
```json
{
    "status": "ok",
    "timestamp": "2024-01-15T10:30:00Z",
    "version": "1.0.0",
    "services": {
        "openai": "connected",
        "database": "connected"
    }
}
```

**Error (503):**
```json
{
    "status": "error",
    "timestamp": "2024-01-15T10:30:00Z",
    "errors": [
        "OpenAI API key not configured"
    ]
}
```

## WebSocket API

### Connection

Connect to the WebSocket server:

```javascript
const ws = new WebSocket('ws://localhost:8080');
```

### Events

#### Connection Events

**Connected:**
```json
{
    "type": "connected",
    "message": "Connected to ChatBot WebSocket server"
}
```

#### Message Events

**Send Message:**
```json
{
    "message": "Hello, how are you?",
    "conversation_id": "conv_abc123"
}
```

**Receive Response:**
```json
{
    "type": "start",
    "conversation_id": "conv_abc123"
}
```

```json
{
    "type": "chunk",
    "content": "Hello! I'm doing well."
}
```

```json
{
    "type": "done"
}
```

**Error:**
```json
{
    "type": "error",
    "message": "Message too long"
}
```

### Example WebSocket Client

```javascript
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = function() {
    console.log('Connected to WebSocket');
    
    // Send message
    ws.send(JSON.stringify({
        message: 'Hello!',
        conversation_id: 'conv_123'
    }));
};

ws.onmessage = function(event) {
    const data = JSON.parse(event.data);
    
    switch(data.type) {
        case 'connected':
            console.log('WebSocket connected');
            break;
        case 'start':
            console.log('Response starting');
            break;
        case 'chunk':
            console.log('Received chunk:', data.content);
            break;
        case 'done':
            console.log('Response complete');
            break;
        case 'error':
            console.error('Error:', data.message);
            break;
    }
};

ws.onerror = function(error) {
    console.error('WebSocket error:', error);
};

ws.onclose = function() {
    console.log('WebSocket disconnected');
};
```

## Configuration API

### Environment Variables

The application can be configured using environment variables:

#### OpenAI Configuration

- `OPENAI_API_KEY`: OpenAI API key (required)
- `OPENAI_MODEL`: Model to use (default: gpt-3.5-turbo)
- `OPENAI_TEMPERATURE`: Temperature setting (default: 0.7)
- `OPENAI_MAX_TOKENS`: Maximum tokens per response (default: 1000)

#### Responses Configuration

- `RESPONSES_MODEL`: Responses API model (default: gpt-4.1-mini)
- `RESPONSES_PROMPT_ID`: Saved prompt identifier (optional)
- `RESPONSES_PROMPT_VERSION`: Prompt version (optional, defaults to latest)
- `RESPONSES_MAX_OUTPUT_TOKENS`: Maximum output tokens (default: 1024)
- `RESPONSES_TEMPERATURE`: Temperature setting (default: 0.7)
- `RESPONSES_TOOLS`: Tools definition (JSON array or comma-separated list, e.g. `[{"type":"file_search"}]` or `file_search`)
- `RESPONSES_VECTOR_STORE_IDS`: Default vector store IDs (JSON array or comma-separated list)
- `RESPONSES_MAX_NUM_RESULTS`: Default `file_search.max_num_results` (1–200)

#### Chat Configuration

- `CHAT_MAX_MESSAGES`: Maximum messages to keep in session (default: 50)
- `CHAT_SESSION_TIMEOUT`: Session timeout in seconds (default: 3600)
- `CHAT_RATE_LIMIT`: Requests per minute limit (default: 60)

#### Security Configuration

- `CORS_ORIGINS`: Allowed CORS origins (default: *)
- `VALIDATE_REFERER`: Enable referer validation (default: false)
- `MAX_MESSAGE_LENGTH`: Maximum message length (default: 4000)

#### WebSocket Configuration

- `WEBSOCKET_ENABLED`: Enable WebSocket server (default: false)
- `WEBSOCKET_HOST`: WebSocket host (default: 0.0.0.0)
- `WEBSOCKET_PORT`: WebSocket port (default: 8080)

### Runtime Configuration

Configuration can be modified at runtime using the config.php file:

```php
$config = [
    'openai' => [
        'api_key' => 'your_key_here',
        'model' => 'gpt-4',
        'temperature' => 0.8,
    ],
    'chat' => [
        'max_messages' => 100,
        'rate_limit_requests' => 30,
    ],
    'security' => [
        'allowed_origins' => ['https://yourdomain.com'],
        'sanitize_input' => true,
    ]
];
```

## Error Handling

### Error Codes

The API uses standard HTTP status codes and custom error codes:

#### HTTP Status Codes

- `200`: Success
- `400`: Bad Request (invalid parameters)
- `401`: Unauthorized (invalid API key)
- `429`: Too Many Requests (rate limited)
- `500`: Internal Server Error
- `503`: Service Unavailable

#### Custom Error Codes

| Code | Description |
|------|-------------|
| `MISSING_MESSAGE` | Message parameter is required |
| `MESSAGE_TOO_LONG` | Message exceeds maximum length |
| `INVALID_CONVERSATION_ID` | Invalid conversation ID format |
| `RATE_LIMIT_EXCEEDED` | Rate limit exceeded |
| `API_KEY_MISSING` | OpenAI API key not configured |
| `API_KEY_INVALID` | OpenAI API key is invalid |
| `MODEL_NOT_AVAILABLE` | Requested model is not available |
| `QUOTA_EXCEEDED` | OpenAI API quota exceeded |
| `SERVICE_UNAVAILABLE` | OpenAI API service unavailable |

### Error Response Format

```json
{
    "error": "Human readable error message",
    "code": "ERROR_CODE",
    "details": {
        "field": "Additional error details"
    },
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "req_abc123"
}
```

### Client-side Error Handling

```javascript
ChatBot.init({
    onError: function(error) {
        switch(error.code) {
            case 'RATE_LIMIT_EXCEEDED':
                showNotification('Please wait a moment before sending another message');
                break;
            case 'MESSAGE_TOO_LONG':
                showNotification('Your message is too long. Please shorten it.');
                break;
            case 'SERVICE_UNAVAILABLE':
                showNotification('Service is temporarily unavailable. Please try again later.');
                break;
            default:
                showNotification('An error occurred. Please try again.');
        }
    }
});
```

## Rate Limiting

### Default Limits

- **Requests per minute**: 60
- **Requests per hour**: 1000
- **Concurrent connections**: 10 per IP
- **Message length**: 4000 characters

### Rate Limit Headers

The API includes rate limit information in response headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1642248600
X-RateLimit-Retry-After: 60
```

### Rate Limit Response

When rate limited, the API returns:

**Status:** 429 Too Many Requests

```json
{
    "error": "Rate limit exceeded",
    "code": "RATE_LIMIT_EXCEEDED",
    "details": {
        "limit": 60,
        "window": 60,
        "retry_after": 30
    }
}
```

### Handling Rate Limits

```javascript
fetch('/chat-unified.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'Hello' })
})
.then(response => {
    if (response.status === 429) {
        const retryAfter = response.headers.get('X-RateLimit-Retry-After');
        setTimeout(() => {
            // Retry the request
        }, retryAfter * 1000);
    }
    return response.json();
})
.then(data => {
    // Handle response
})
.catch(error => {
    console.error('Error:', error);
});
```

## Examples

### Basic Implementation

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="chatbot.css">
</head>
<body>
    <div id="chat-container"></div>
    
    <script src="chatbot-enhanced.js"></script>
    <script>
        ChatBot.init('#chat-container', {
            mode: 'inline',
            height: '500px',
            apiEndpoint: '/chat-unified.php',
            assistant: {
                name: 'Assistant',
                welcomeMessage: 'Hello! How can I help you today?'
            }
        });
    </script>
</body>
</html>
```

### Advanced Configuration

```javascript
const chatbot = ChatBot.init({
    mode: 'floating',
    position: 'bottom-left',
    streamingMode: 'auto',
    
    theme: {
        primaryColor: '#007bff',
        backgroundColor: '#f8f9fa',
        fontFamily: 'Inter, sans-serif',
        borderRadius: '12px'
    },
    
    assistant: {
        name: 'Sarah',
        avatar: '/assets/sarah-avatar.png',
        welcomeMessage: 'Hi! I'm Sarah, your AI assistant. How can I help?',
        placeholder: 'Ask me anything...',
        thinking: 'Sarah is thinking...'
    },
    
    // Callbacks
    onMessage: function(message) {
        // Track message for analytics
        analytics.track('chat_message', {
            role: message.role,
            length: message.content.length
        });
    },
    
    onError: function(error) {
        // Log error to monitoring service
        errorTracker.captureException(error);
        
        // Show user-friendly message
        if (error.code === 'RATE_LIMIT_EXCEEDED') {
            showToast('Please wait a moment before sending another message');
        }
    },
    
    onConnect: function() {
        console.log('Chat connected');
        updateConnectionStatus('connected');
    },
    
    onDisconnect: function() {
        console.log('Chat disconnected');
        updateConnectionStatus('disconnected');
    }
});

// Programmatic control
document.getElementById('clear-chat').addEventListener('click', () => {
    chatbot.clearHistory();
});

document.getElementById('send-predefined').addEventListener('click', () => {
    chatbot.sendMessageProgrammatically('Tell me about your features');
});
```

### Custom Integration

```javascript
// Custom chatbot with specific business logic
class CustomChatBot extends ChatBot {
    constructor(container, options) {
        super(container, options);
        this.setupCustomHandlers();
    }
    
    setupCustomHandlers() {
        // Override message handling
        this.options.onMessage = (message) => {
            if (message.role === 'user') {
                this.trackUserIntent(message.content);
            }
            
            if (message.role === 'assistant') {
                this.extractActionables(message.content);
            }
        };
    }
    
    trackUserIntent(message) {
        // Custom analytics or intent detection
        fetch('/api/track-intent', {
            method: 'POST',
            body: JSON.stringify({ message }),
            headers: { 'Content-Type': 'application/json' }
        });
    }
    
    extractActionables(response) {
        // Extract actionable items from assistant response
        const actionables = this.parseActionables(response);
        if (actionables.length > 0) {
            this.showActionableButtons(actionables);
        }
    }
    
    showActionableButtons(actionables) {
        // Add custom UI for actionable items
        const buttonContainer = document.createElement('div');
        buttonContainer.className = 'chatbot-actionables';
        
        actionables.forEach(action => {
            const button = document.createElement('button');
            button.textContent = action.label;
            button.onclick = () => this.executeAction(action);
            buttonContainer.appendChild(button);
        });
        
        this.messageContainer.appendChild(buttonContainer);
    }
}

// Use custom implementation
const customChatbot = new CustomChatBot('#chat-container', {
    mode: 'inline',
    // ... other options
});
```

### Server-side PHP Integration

```php
<?php
// Custom endpoint extending the base functionality
require_once 'chat-unified.php';

class CustomChatHandler extends ChatHandler {
    public function preprocessMessage($message, $context) {
        // Custom preprocessing
        $message = $this->sanitizeMessage($message);
        $message = $this->addContextualInfo($message, $context);
        
        return parent::preprocessMessage($message, $context);
    }
    
    public function postprocessResponse($response, $context) {
        // Custom postprocessing
        $response = $this->addCustomFormatting($response);
        $response = $this->injectBusinessLogic($response, $context);
        
        return parent::postprocessResponse($response, $context);
    }
    
    private function addContextualInfo($message, $context) {
        // Add user context, preferences, history, etc.
        if (isset($context['user_id'])) {
            $userProfile = $this->getUserProfile($context['user_id']);
            $message = "User context: {$userProfile['name']}, {$userProfile['role']}. Message: {$message}";
        }
        
        return $message;
    }
}

// Use custom handler
$handler = new CustomChatHandler();
$handler->handleRequest();
?>
```

### WebSocket Server Customization

```php
<?php
// Custom WebSocket server with additional features
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class CustomChatBotWebSocket extends ChatBotWebSocket {
    protected $rooms = [];
    protected $userConnections = [];
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        // Handle different message types
        switch ($data['type'] ?? 'message') {
            case 'join_room':
                $this->handleJoinRoom($from, $data);
                break;
            case 'leave_room':
                $this->handleLeaveRoom($from, $data);
                break;
            case 'broadcast':
                $this->handleBroadcast($from, $data);
                break;
            default:
                parent::onMessage($from, $msg);
        }
    }
    
    private function handleJoinRoom($connection, $data) {
        $roomId = $data['room_id'];
        $this->rooms[$roomId][] = $connection;
        
        $connection->send(json_encode([
            'type' => 'room_joined',
            'room_id' => $roomId
        ]));
    }
    
    private function handleBroadcast($from, $data) {
        $roomId = $data['room_id'];
        if (isset($this->rooms[$roomId])) {
            foreach ($this->rooms[$roomId] as $connection) {
                if ($connection !== $from) {
                    $connection->send(json_encode([
                        'type' => 'broadcast',
                        'message' => $data['message'],
                        'from' => $data['user_id'] ?? 'anonymous'
                    ]));
                }
            }
        }
    }
}
?>
```

## Testing

### Unit Testing

```javascript
// Test chatbot initialization
describe('ChatBot', () => {
    test('should initialize with default config', () => {
        const chatbot = ChatBot.init('#test-container');
        expect(chatbot).toBeDefined();
        expect(chatbot.options.mode).toBe('inline');
    });
    
    test('should handle custom configuration', () => {
        const chatbot = ChatBot.init('#test-container', {
            mode: 'floating',
            theme: { primaryColor: '#ff0000' }
        });
        
        expect(chatbot.options.mode).toBe('floating');
        expect(chatbot.options.theme.primaryColor).toBe('#ff0000');
    });
});
```

### Integration Testing

```bash
# Test SSE endpoint
curl -N -H "Accept: text/event-stream" \
  -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "conversation_id": "test"}' \
  http://localhost:8080/chat-unified.php

# Test health endpoint
curl http://localhost:8080/health

# Test with rate limiting
for i in {1..70}; do
  curl -X POST -H "Content-Type: application/json" \
    -d '{"message": "test"}' \
    http://localhost:8080/chat-unified.php
done
```

### Load Testing

```bash
# Using Apache Bench
ab -n 1000 -c 10 -p post_data.json -T application/json \
  http://localhost:8080/chat-unified.php

# Using wrk
wrk -t12 -c400 -d30s --script=post.lua \
  http://localhost:8080/chat-unified.php
```

---

## Additional Resources

For more comprehensive documentation:

- **[Implementation Report](IMPLEMENTATION_REPORT.md)** - Complete implementation details and architecture
- **[Customization Guide](customization-guide.md)** - Extensive customization options
- **[Deployment Guide](deployment.md)** - Deployment and operations
- **[Phase Documentation](IMPLEMENTATION_PLAN.md)** - Detailed phase-by-phase implementation

This API documentation covers all the main interfaces and usage patterns for the GPT Chatbot Boilerplate. For additional examples and use cases, see the [main README](../README.md) or check the demo implementation in `default.php`.

