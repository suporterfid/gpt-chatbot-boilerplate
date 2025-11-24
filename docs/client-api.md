# JavaScript Client API Documentation

**Version:** 1.0.1
**Last Updated:** January 2025

This document provides comprehensive documentation for the JavaScript client library used to integrate the chatbot widget into websites.

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [EnhancedChatBot Class](#enhancedchatbot-class)
- [Configuration Options](#configuration-options)
- [Methods](#methods)
- [Events & Callbacks](#events--callbacks)
- [Connection Management](#connection-management)
- [File Upload](#file-upload)
- [Customization](#customization)
- [Advanced Usage](#advanced-usage)
- [TypeScript Support](#typescript-support)
- [Examples](#examples)

## Overview

The JavaScript client provides a feature-rich chatbot widget that can be embedded in any website. It supports:

- **Multiple display modes:** Floating widget or inline integration
- **Streaming responses:** Real-time message delivery via SSE or WebSocket
- **File uploads:** Attach documents, images, and other files
- **Markdown rendering:** Rich text formatting in messages
- **Custom theming:** Full visual customization
- **Accessibility:** WCAG 2.1 AA compliant
- **Responsive design:** Mobile-friendly interface
- **Proactive messaging:** Auto-open with welcome message
- **Connection resilience:** Automatic reconnection and message queuing

## Installation

### CDN (Recommended)

```html
<!-- CSS -->
<link rel="stylesheet" href="https://your-domain.com/chatbot.css">

<!-- JavaScript -->
<script src="https://your-domain.com/chatbot-enhanced.js"></script>
```

### NPM

```bash
npm install @your-company/chatbot-widget
```

```javascript
import ChatBot from '@your-company/chatbot-widget';
import '@your-company/chatbot-widget/dist/chatbot.css';
```

### Self-Hosted

Download the files and host them yourself:

```html
<link rel="stylesheet" href="/path/to/chatbot.css">
<script src="/path/to/chatbot-enhanced.js"></script>
```

## Quick Start

### Floating Widget

```html
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="https://your-domain.com/chatbot.css">
</head>
<body>
  <!-- Your content -->

  <script src="https://your-domain.com/chatbot-enhanced.js"></script>
  <script>
    const chatbot = ChatBot.init({
      mode: 'floating',
      position: 'bottom-right',
      apiEndpoint: '/chat-unified.php',
      ui: {
        title: 'Chat Assistant',
        theme: {
          primaryColor: '#007bff'
        }
      }
    });
  </script>
</body>
</html>
```

### Inline Widget

```html
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="https://your-domain.com/chatbot.css">
</head>
<body>
  <div id="chat-container"></div>

  <script src="https://your-domain.com/chatbot-enhanced.js"></script>
  <script>
    const chatbot = ChatBot.init('#chat-container', {
      mode: 'inline',
      layout: {
        height: '600px',
        width: '100%'
      },
      apiEndpoint: '/chat-unified.php'
    });
  </script>
</body>
</html>
```

### Auto-Initialization

Use data attributes for zero-config initialization:

```html
<div id="chatbot" data-chatbot='{
  "mode": "inline",
  "apiEndpoint": "/chat-unified.php",
  "ui": {
    "title": "Support Chat",
    "theme": {
      "primaryColor": "#1FB8CD"
    }
  }
}'></div>

<script src="https://your-domain.com/chatbot-enhanced.js"></script>
```

The widget will automatically initialize when the page loads.

## EnhancedChatBot Class

The main class for creating and managing chatbot instances.

### Static Methods

#### ChatBot.init(container, options)

Initialize a new chatbot instance.

**Parameters:**
- `container` (string|Element|null): CSS selector, DOM element, or null for floating mode
- `options` (object): Configuration options

**Returns:** EnhancedChatBot instance

**Example:**

```javascript
// Floating mode
const chatbot1 = ChatBot.init({
  mode: 'floating'
});

// Inline mode with selector
const chatbot2 = ChatBot.init('#chat-container', {
  mode: 'inline'
});

// Inline mode with element
const element = document.getElementById('chat-container');
const chatbot3 = ChatBot.init(element, {
  mode: 'inline'
});
```

---

#### ChatBot.destroyAll()

Destroy all active chatbot instances.

**Example:**

```javascript
ChatBot.destroyAll();
```

---

## Configuration Options

### Core Settings

```javascript
{
  // API Configuration
  apiEndpoint: '/chat-unified.php',          // Chat API endpoint
  apiType: 'responses',                       // 'responses' or 'chat'
  streamingMode: 'auto',                      // 'auto', 'websocket', 'sse', 'ajax'
  websocketEndpoint: null,                    // WebSocket URL (auto-detected if null)

  // Agent Configuration
  agent: {
    id: null,                                 // Agent UUID
    publicId: null,                           // Public agent ID (whitelabel)
    name: 'Assistant'                         // Display name
  },

  // Display Mode
  mode: 'inline',                             // 'inline' or 'floating'

  // Layout (Floating Mode)
  layout: {
    position: 'bottom-right',                 // 'bottom-right' or 'bottom-left'
    width: '380px',
    height: '600px',
    offsetX: '20px',
    offsetY: '20px'
  },

  // UI Configuration
  ui: {
    title: 'Chat Assistant',
    placeholder: 'Type your message...',
    sendButtonText: 'Send',
    theme: {
      primaryColor: '#007bff',
      backgroundColor: '#ffffff',
      textColor: '#333333',
      userMessageColor: '#007bff',
      assistantMessageColor: '#f0f0f0',
      fontFamily: 'system-ui, -apple-system, sans-serif',
      fontSize: '14px',
      borderRadius: '8px'
    }
  },

  // Timeline Features
  timeline: {
    showTimestamps: true,
    showApiBadges: true,
    showRoleAvatars: true,
    dateFormat: 'relative'                    // 'relative' or 'absolute'
  },

  // File Upload
  enableFileUpload: false,
  maxFileSize: 10485760,                      // 10 MB in bytes
  allowedFileTypes: [
    'application/pdf',
    'text/plain',
    'image/png',
    'image/jpeg'
  ],

  // Accessibility
  accessibility: {
    enabled: true,
    announceMessages: true,
    keyboardShortcuts: true,
    ariaLabels: {
      chatContainer: 'Chat conversation',
      messageInput: 'Type your message',
      sendButton: 'Send message',
      closeButton: 'Close chat'
    }
  },

  // Proactive Messaging
  proactive: {
    enabled: false,
    message: 'Hello! How can I help you today?',
    delay: 3000,                              // ms delay before showing
    autoOpen: true                            // Auto-open widget
  },

  // Advanced Features
  enableMarkdown: true,
  enableCodeHighlight: true,
  enableAutoScroll: true,
  enableTypingIndicator: true,
  maxMessages: 100,                           // Max messages in memory

  // OpenAI Responses API Configuration
  responsesConfig: {
    promptId: null,
    promptVersion: null,
    tools: [{ type: 'file_search' }],
    vectorStoreIds: [],
    maxNumResults: 5,
    responseFormat: null                      // For structured output
  },

  // Callbacks
  callbacks: {
    onMessage: null,
    onError: null,
    onConnect: null,
    onDisconnect: null,
    onTyping: null,
    onFileUpload: null,
    onToolCall: null,
    onOpen: null,
    onClose: null
  }
}
```

### Configuration Details

#### API Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiEndpoint` | string | '/chat-unified.php' | Chat API endpoint URL |
| `apiType` | string | 'responses' | OpenAI API type: 'responses' or 'chat' |
| `streamingMode` | string | 'auto' | Connection mode (auto-selects best available) |
| `websocketEndpoint` | string | null | WebSocket server URL (auto-detected if null) |

**streamingMode Options:**
- `'auto'`: Automatically select WebSocket > SSE > AJAX
- `'websocket'`: Force WebSocket connection
- `'sse'`: Force Server-Sent Events
- `'ajax'`: Force AJAX polling (no streaming)

---

#### Agent Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `agent.id` | string | null | Agent UUID (overrides default) |
| `agent.publicId` | string | null | Public agent ID for whitelabel access |
| `agent.name` | string | 'Assistant' | Display name for the agent |

**Example:**

```javascript
ChatBot.init({
  agent: {
    id: 'agent_abc123',
    name: 'Support Bot'
  }
});
```

---

#### Display Mode

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `mode` | string | 'inline' | Display mode: 'inline' or 'floating' |

- **'inline'**: Embedded in page (requires container)
- **'floating'**: Fixed position overlay (no container needed)

---

#### Layout (Floating Mode)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `layout.position` | string | 'bottom-right' | Position: 'bottom-right' or 'bottom-left' |
| `layout.width` | string | '380px' | Widget width |
| `layout.height` | string | '600px' | Widget height |
| `layout.offsetX` | string | '20px' | Horizontal offset from edge |
| `layout.offsetY` | string | '20px' | Vertical offset from edge |

---

#### UI Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `ui.title` | string | 'Chat Assistant' | Widget header title |
| `ui.placeholder` | string | 'Type your message...' | Input placeholder text |
| `ui.sendButtonText` | string | 'Send' | Send button label |
| `ui.theme` | object | {...} | Theme colors and styles |

**Theme Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `theme.primaryColor` | string | '#007bff' | Primary brand color |
| `theme.backgroundColor` | string | '#ffffff' | Background color |
| `theme.textColor` | string | '#333333' | Text color |
| `theme.userMessageColor` | string | '#007bff' | User message bubble color |
| `theme.assistantMessageColor` | string | '#f0f0f0' | Assistant message bubble color |
| `theme.fontFamily` | string | 'system-ui, ...' | Font family |
| `theme.fontSize` | string | '14px' | Base font size |
| `theme.borderRadius` | string | '8px' | Border radius for elements |

---

#### Timeline Features

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `timeline.showTimestamps` | boolean | true | Show message timestamps |
| `timeline.showApiBadges` | boolean | true | Show API type badges (Chat/Responses) |
| `timeline.showRoleAvatars` | boolean | true | Show avatars for user/assistant |
| `timeline.dateFormat` | string | 'relative' | 'relative' (2m ago) or 'absolute' (10:30 AM) |

---

#### File Upload

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enableFileUpload` | boolean | false | Enable file upload feature |
| `maxFileSize` | number | 10485760 | Max file size in bytes (10 MB) |
| `allowedFileTypes` | array | [...] | Allowed MIME types |

**Common MIME Types:**

```javascript
allowedFileTypes: [
  'application/pdf',
  'text/plain',
  'text/markdown',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'image/png',
  'image/jpeg',
  'image/gif'
]
```

---

#### Accessibility

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `accessibility.enabled` | boolean | true | Enable accessibility features |
| `accessibility.announceMessages` | boolean | true | Announce messages to screen readers |
| `accessibility.keyboardShortcuts` | boolean | true | Enable keyboard shortcuts |
| `accessibility.ariaLabels` | object | {...} | Custom ARIA labels |

**Keyboard Shortcuts:**
- `Ctrl/Cmd + K`: Focus message input
- `Escape`: Close floating widget
- `Enter`: Send message
- `Shift + Enter`: New line in message

---

#### Proactive Messaging

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `proactive.enabled` | boolean | false | Enable proactive messages |
| `proactive.message` | string | 'Hello! ...' | Welcome message to show |
| `proactive.delay` | number | 3000 | Delay before showing (ms) |
| `proactive.autoOpen` | boolean | true | Auto-open widget when triggered |

**Example:**

```javascript
ChatBot.init({
  mode: 'floating',
  proactive: {
    enabled: true,
    message: 'Need help? I'm here to assist!',
    delay: 5000,
    autoOpen: true
  }
});
```

---

#### Responses API Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `responsesConfig.promptId` | string | null | OpenAI prompt ID |
| `responsesConfig.promptVersion` | string | null | Prompt version |
| `responsesConfig.tools` | array | [{type:'file_search'}] | Tool definitions |
| `responsesConfig.vectorStoreIds` | array | [] | Vector store IDs |
| `responsesConfig.maxNumResults` | number | 5 | Max file search results |
| `responsesConfig.responseFormat` | object | null | Structured output format |

**Structured Output Example:**

```javascript
responsesConfig: {
  responseFormat: {
    type: 'json_schema',
    json_schema: {
      name: 'product_recommendation',
      schema: {
        type: 'object',
        properties: {
          product: { type: 'string' },
          price: { type: 'number' },
          confidence: { type: 'number' }
        },
        required: ['product', 'price']
      }
    }
  }
}
```

---

## Methods

### Instance Methods

#### init()

Initialize the widget (called automatically by `ChatBot.init()`).

```javascript
const chatbot = ChatBot.init({ mode: 'floating' });
// Widget is now initialized and ready
```

---

#### sendMessage(message, files)

Send a message programmatically.

**Parameters:**
- `message` (string): Message text
- `files` (File[]): Optional array of File objects

**Returns:** Promise<void>

**Example:**

```javascript
// Text only
await chatbot.sendMessage('Hello, how are you?');

// With files
const fileInput = document.getElementById('file-input');
const files = Array.from(fileInput.files);
await chatbot.sendMessage('Please analyze these documents', files);
```

---

#### open()

Open the chat widget (floating mode only).

**Example:**

```javascript
chatbot.open();
```

---

#### close()

Close the chat widget (floating mode only).

**Example:**

```javascript
chatbot.close();
```

---

#### toggle()

Toggle chat widget visibility (floating mode only).

**Example:**

```javascript
chatbot.toggle();
```

---

#### resetConversation()

Clear conversation history and start fresh.

**Example:**

```javascript
chatbot.resetConversation();
```

---

#### setTyping(isTyping)

Manually control typing indicator.

**Parameters:**
- `isTyping` (boolean): Show/hide typing indicator

**Example:**

```javascript
chatbot.setTyping(true);
setTimeout(() => chatbot.setTyping(false), 2000);
```

---

#### updateTheme(theme)

Update theme colors dynamically.

**Parameters:**
- `theme` (object): Theme options to update

**Example:**

```javascript
chatbot.updateTheme({
  primaryColor: '#ff0000',
  backgroundColor: '#000000',
  textColor: '#ffffff'
});
```

---

#### destroy()

Destroy the chatbot instance and remove from DOM.

**Example:**

```javascript
chatbot.destroy();
```

---

#### getConversationId()

Get current conversation ID.

**Returns:** string|null

**Example:**

```javascript
const conversationId = chatbot.getConversationId();
console.log('Current conversation:', conversationId);
```

---

#### setConversationId(id)

Set conversation ID (to resume previous conversation).

**Parameters:**
- `id` (string): Conversation ID

**Example:**

```javascript
const savedId = localStorage.getItem('conversation_id');
if (savedId) {
  chatbot.setConversationId(savedId);
}
```

---

#### getMessages()

Get all messages in current conversation.

**Returns:** Array<Message>

**Example:**

```javascript
const messages = chatbot.getMessages();
console.log(`${messages.length} messages in conversation`);
```

---

#### addMessage(role, content, metadata)

Manually add a message to the conversation.

**Parameters:**
- `role` (string): 'user' or 'assistant'
- `content` (string): Message content
- `metadata` (object): Optional metadata

**Example:**

```javascript
chatbot.addMessage('assistant', 'Hello! How can I help?', {
  timestamp: new Date(),
  model: 'gpt-4'
});
```

---

## Events & Callbacks

### onMessage

Called when a new message is added to the conversation.

**Parameters:**
- `message` (object): Message object

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onMessage: function(message) {
      console.log('New message:', message);

      // Message object structure:
      // {
      //   role: 'user' | 'assistant',
      //   content: 'Message text',
      //   timestamp: Date,
      //   metadata: { ... }
      // }

      // Track analytics
      if (message.role === 'user') {
        analytics.track('chat_user_message', {
          length: message.content.length
        });
      }
    }
  }
});
```

---

### onError

Called when an error occurs.

**Parameters:**
- `error` (object): Error object

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onError: function(error) {
      console.error('Chat error:', error);

      // Error object structure:
      // {
      //   message: 'Error description',
      //   code: 'ERROR_CODE',
      //   details: { ... }
      // }

      // Handle specific errors
      switch (error.code) {
        case 'RATE_LIMIT_EXCEEDED':
          showNotification('Please slow down');
          break;
        case 'MESSAGE_TOO_LONG':
          showNotification('Message too long');
          break;
        default:
          showNotification('An error occurred');
      }
    }
  }
});
```

---

### onConnect

Called when connection is established.

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onConnect: function() {
      console.log('Connected to chat server');
      updateConnectionStatus('online');
    }
  }
});
```

---

### onDisconnect

Called when connection is lost.

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onDisconnect: function() {
      console.log('Disconnected from chat server');
      updateConnectionStatus('offline');
    }
  }
});
```

---

### onTyping

Called when typing status changes.

**Parameters:**
- `isTyping` (boolean): Whether assistant is typing

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onTyping: function(isTyping) {
      console.log('Assistant typing:', isTyping);

      if (isTyping) {
        updateStatus('Assistant is typing...');
      } else {
        updateStatus('Online');
      }
    }
  }
});
```

---

### onFileUpload

Called when file upload status changes.

**Parameters:**
- `event` (object): Upload event object

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onFileUpload: function(event) {
      console.log('File upload event:', event);

      // Event types:
      // - 'start': Upload started
      // - 'progress': Upload progress
      // - 'complete': Upload completed
      // - 'error': Upload failed

      switch (event.type) {
        case 'progress':
          updateProgressBar(event.progress);
          break;
        case 'complete':
          console.log('File uploaded:', event.file.name);
          break;
        case 'error':
          showNotification('Upload failed: ' + event.error);
          break;
      }
    }
  }
});
```

---

### onToolCall

Called when the assistant uses a tool/function.

**Parameters:**
- `toolCall` (object): Tool call object

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onToolCall: function(toolCall) {
      console.log('Tool call:', toolCall);

      // Tool call object:
      // {
      //   id: 'call_abc123',
      //   name: 'file_search',
      //   arguments: { query: '...' },
      //   result: '...'
      // }

      if (toolCall.name === 'file_search') {
        console.log('Searching files for:', toolCall.arguments.query);
      }
    }
  }
});
```

---

### onOpen

Called when widget is opened (floating mode).

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onOpen: function() {
      console.log('Chat widget opened');
      analytics.track('chat_opened');
    }
  }
});
```

---

### onClose

Called when widget is closed (floating mode).

**Example:**

```javascript
ChatBot.init({
  callbacks: {
    onClose: function() {
      console.log('Chat widget closed');
      analytics.track('chat_closed');
    }
  }
});
```

---

## Connection Management

The client includes a sophisticated connection manager that handles:
- Automatic mode selection (WebSocket > SSE > AJAX)
- Connection failure recovery
- Automatic reconnection with exponential backoff
- Message queuing during disconnection
- Heartbeat/ping-pong for connection health

### ConnectionManager Class

Accessible via `chatbot.connectionManager`.

#### Methods

**reconnect()**

Manually trigger reconnection.

```javascript
chatbot.connectionManager.reconnect();
```

**disconnect()**

Disconnect from server.

```javascript
chatbot.connectionManager.disconnect();
```

**getConnectionState()**

Get current connection state.

**Returns:** 'connected' | 'connecting' | 'disconnected'

```javascript
const state = chatbot.connectionManager.getConnectionState();
console.log('Connection state:', state);
```

**getMode()**

Get current connection mode.

**Returns:** 'websocket' | 'sse' | 'ajax'

```javascript
const mode = chatbot.connectionManager.getMode();
console.log('Connection mode:', mode);
```

---

### Handling Connection Issues

```javascript
const chatbot = ChatBot.init({
  callbacks: {
    onConnect: function() {
      hideReconnectingMessage();
    },
    onDisconnect: function() {
      showReconnectingMessage();
    },
    onError: function(error) {
      if (error.code === 'CONNECTION_FAILED') {
        showOfflineMessage();

        // Manual reconnection
        setTimeout(() => {
          chatbot.connectionManager.reconnect();
        }, 5000);
      }
    }
  }
});
```

---

## File Upload

### Enable File Upload

```javascript
const chatbot = ChatBot.init({
  enableFileUpload: true,
  maxFileSize: 10 * 1024 * 1024, // 10 MB
  allowedFileTypes: [
    'application/pdf',
    'text/plain',
    'image/png',
    'image/jpeg'
  ]
});
```

### Upload via UI

Users can click the attachment button in the widget to select files.

### Upload Programmatically

```javascript
// Get file from input
const fileInput = document.getElementById('my-file-input');
const file = fileInput.files[0];

// Send with message
await chatbot.sendMessage('Please analyze this document', [file]);
```

### Multiple Files

```javascript
const fileInput = document.getElementById('my-file-input');
const files = Array.from(fileInput.files);

await chatbot.sendMessage('Analyze these documents', files);
```

### File Upload Events

```javascript
ChatBot.init({
  enableFileUpload: true,
  callbacks: {
    onFileUpload: function(event) {
      switch (event.type) {
        case 'start':
          console.log('Upload started:', event.file.name);
          break;

        case 'progress':
          console.log(`Upload progress: ${event.progress}%`);
          break;

        case 'complete':
          console.log('Upload complete:', event.file.name);
          break;

        case 'error':
          console.error('Upload error:', event.error);
          break;
      }
    }
  }
});
```

---

## Customization

### Custom Styling

Override default styles with CSS:

```css
/* Custom theme */
.chatbot-container {
  --primary-color: #ff6b6b;
  --background-color: #2c3e50;
  --text-color: #ecf0f1;
}

/* Custom message bubbles */
.chatbot-message.user {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.chatbot-message.assistant {
  background: #34495e;
  color: #ecf0f1;
}

/* Custom header */
.chatbot-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Custom input */
.chatbot-input {
  border: 2px solid #667eea;
  border-radius: 24px;
}
```

### Custom HTML

Inject custom HTML into the widget:

```javascript
const chatbot = ChatBot.init({
  callbacks: {
    onMessage: function(message) {
      if (message.role === 'assistant') {
        // Add custom action buttons
        const messageElement = document.querySelector('.chatbot-message:last-child');
        const buttonsHTML = `
          <div class="custom-actions">
            <button onclick="copyMessage('${message.content}')">Copy</button>
            <button onclick="shareMessage('${message.content}')">Share</button>
          </div>
        `;
        messageElement.insertAdjacentHTML('beforeend', buttonsHTML);
      }
    }
  }
});
```

### Custom Components

Replace default components:

```javascript
// Custom send button
const chatbot = ChatBot.init({ mode: 'inline' });

const defaultSendButton = document.querySelector('.chatbot-send-button');
defaultSendButton.innerHTML = '<i class="fas fa-paper-plane"></i> Send';

// Custom input placeholder with animation
const input = document.querySelector('.chatbot-input');
const placeholders = [
  'Ask me anything...',
  'How can I help?',
  'What would you like to know?'
];

let index = 0;
setInterval(() => {
  input.placeholder = placeholders[index];
  index = (index + 1) % placeholders.length;
}, 3000);
```

---

## Advanced Usage

### Multi-Agent Switching

Switch between different agents dynamically:

```javascript
const chatbot = ChatBot.init({
  agent: {
    id: 'agent_support',
    name: 'Support Bot'
  }
});

// Switch to sales agent
function switchToSales() {
  chatbot.resetConversation();
  chatbot.agent.id = 'agent_sales';
  chatbot.agent.name = 'Sales Bot';
  chatbot.addMessage('assistant', 'Switched to Sales Bot. How can I help with your purchase?');
}

// Switch to technical agent
function switchToTechnical() {
  chatbot.resetConversation();
  chatbot.agent.id = 'agent_technical';
  chatbot.agent.name = 'Technical Support';
  chatbot.addMessage('assistant', 'Switched to Technical Support. What issue are you experiencing?');
}
```

### Conversation Context

Maintain context across page navigation:

```javascript
// Save conversation before navigation
window.addEventListener('beforeunload', () => {
  const conversationId = chatbot.getConversationId();
  const messages = chatbot.getMessages();

  localStorage.setItem('conversation_id', conversationId);
  localStorage.setItem('conversation_messages', JSON.stringify(messages));
});

// Restore conversation on page load
window.addEventListener('load', () => {
  const chatbot = ChatBot.init({ mode: 'floating' });

  const savedId = localStorage.getItem('conversation_id');
  const savedMessages = localStorage.getItem('conversation_messages');

  if (savedId && savedMessages) {
    chatbot.setConversationId(savedId);

    const messages = JSON.parse(savedMessages);
    messages.forEach(msg => {
      chatbot.addMessage(msg.role, msg.content, msg.metadata);
    });
  }
});
```

### Custom Message Rendering

Transform messages before display:

```javascript
const chatbot = ChatBot.init({
  callbacks: {
    onMessage: function(message) {
      if (message.role === 'assistant') {
        // Extract and render code blocks
        const codeBlockRegex = /```(\w+)?\n([\s\S]*?)```/g;
        message.content = message.content.replace(codeBlockRegex, (match, lang, code) => {
          return `<pre><code class="language-${lang || 'text'}">${escapeHtml(code)}</code></pre>`;
        });

        // Render links as cards
        const linkRegex = /https?:\/\/[^\s]+/g;
        message.content = message.content.replace(linkRegex, (url) => {
          return `<a href="${url}" target="_blank" class="link-card">${url}</a>`;
        });
      }
    }
  }
});
```

### Analytics Integration

Track chat interactions:

```javascript
const chatbot = ChatBot.init({
  callbacks: {
    onMessage: function(message) {
      // Google Analytics
      if (window.gtag) {
        gtag('event', 'chat_message', {
          role: message.role,
          length: message.content.length,
          agent: chatbot.agent.name
        });
      }

      // Mixpanel
      if (window.mixpanel) {
        mixpanel.track('Chat Message', {
          role: message.role,
          agent: chatbot.agent.name
        });
      }
    },

    onOpen: function() {
      if (window.gtag) {
        gtag('event', 'chat_opened');
      }
    },

    onError: function(error) {
      if (window.gtag) {
        gtag('event', 'chat_error', {
          error_code: error.code,
          error_message: error.message
        });
      }
    }
  }
});
```

### Rate Limit Handling

Display rate limit warnings:

```javascript
const chatbot = ChatBot.init({
  callbacks: {
    onError: function(error) {
      if (error.code === 'RATE_LIMIT_EXCEEDED') {
        const retryAfter = error.details.retry_after || 60;

        showWarning(`You're sending messages too quickly. Please wait ${retryAfter} seconds.`);

        // Disable input temporarily
        const input = document.querySelector('.chatbot-input');
        input.disabled = true;

        setTimeout(() => {
          input.disabled = false;
          hideWarning();
        }, retryAfter * 1000);
      }
    }
  }
});
```

---

## TypeScript Support

### Type Definitions

```typescript
import ChatBot, {
  ChatBotConfig,
  Message,
  ConnectionMode,
  ChatBotInstance
} from '@your-company/chatbot-widget';

const config: ChatBotConfig = {
  mode: 'floating',
  apiEndpoint: '/chat-unified.php',
  ui: {
    title: 'Support Chat',
    theme: {
      primaryColor: '#007bff'
    }
  },
  callbacks: {
    onMessage: (message: Message) => {
      console.log('New message:', message);
    }
  }
};

const chatbot: ChatBotInstance = ChatBot.init(config);
```

### Interface Definitions

```typescript
interface ChatBotConfig {
  apiEndpoint: string;
  apiType?: 'responses' | 'chat';
  streamingMode?: 'auto' | 'websocket' | 'sse' | 'ajax';
  websocketEndpoint?: string | null;

  agent?: {
    id?: string | null;
    publicId?: string | null;
    name?: string;
  };

  mode?: 'inline' | 'floating';

  layout?: {
    position?: 'bottom-right' | 'bottom-left';
    width?: string;
    height?: string;
    offsetX?: string;
    offsetY?: string;
  };

  ui?: {
    title?: string;
    placeholder?: string;
    theme?: Theme;
  };

  callbacks?: {
    onMessage?: (message: Message) => void;
    onError?: (error: ChatError) => void;
    onConnect?: () => void;
    onDisconnect?: () => void;
    onTyping?: (isTyping: boolean) => void;
    onFileUpload?: (event: FileUploadEvent) => void;
    onToolCall?: (toolCall: ToolCall) => void;
    onOpen?: () => void;
    onClose?: () => void;
  };

  // ... other options
}

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: Date;
  metadata?: Record<string, any>;
}

interface ChatError {
  message: string;
  code: string;
  details: Record<string, any>;
}

interface FileUploadEvent {
  type: 'start' | 'progress' | 'complete' | 'error';
  file?: File;
  progress?: number;
  error?: string;
}

interface ToolCall {
  id: string;
  name: string;
  arguments: Record<string, any>;
  result?: any;
}

interface ChatBotInstance {
  init(): void;
  sendMessage(message: string, files?: File[]): Promise<void>;
  open(): void;
  close(): void;
  toggle(): void;
  resetConversation(): void;
  destroy(): void;
  getConversationId(): string | null;
  setConversationId(id: string): void;
  getMessages(): Message[];
  addMessage(role: 'user' | 'assistant', content: string, metadata?: Record<string, any>): void;
}
```

---

## Examples

### Complete Integration Example

```html
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat Example</title>
  <link rel="stylesheet" href="https://your-domain.com/chatbot.css">
  <style>
    .status-indicator {
      position: fixed;
      top: 10px;
      right: 10px;
      padding: 8px 16px;
      border-radius: 4px;
      background: #28a745;
      color: white;
      font-size: 14px;
    }
    .status-indicator.offline {
      background: #dc3545;
    }
  </style>
</head>
<body>
  <div class="status-indicator" id="status">Connected</div>

  <script src="https://your-domain.com/chatbot-enhanced.js"></script>
  <script>
    // Initialize chatbot
    const chatbot = ChatBot.init({
      mode: 'floating',
      position: 'bottom-right',
      apiEndpoint: '/chat-unified.php',

      agent: {
        id: 'agent_support',
        name: 'Support Assistant'
      },

      ui: {
        title: 'Customer Support',
        placeholder: 'Ask us anything...',
        theme: {
          primaryColor: '#007bff',
          fontFamily: 'Inter, sans-serif'
        }
      },

      enableFileUpload: true,
      maxFileSize: 10 * 1024 * 1024,

      proactive: {
        enabled: true,
        message: 'Hi! Need any help?',
        delay: 5000,
        autoOpen: false
      },

      callbacks: {
        onConnect: function() {
          updateStatus('Connected', true);
        },

        onDisconnect: function() {
          updateStatus('Disconnected', false);
        },

        onMessage: function(message) {
          console.log('Message:', message);

          // Track in analytics
          if (window.gtag) {
            gtag('event', 'chat_message', {
              role: message.role
            });
          }

          // Save conversation
          saveConversation();
        },

        onError: function(error) {
          console.error('Error:', error);

          if (error.code === 'RATE_LIMIT_EXCEEDED') {
            alert('Please slow down. You are sending messages too quickly.');
          }
        },

        onFileUpload: function(event) {
          if (event.type === 'complete') {
            console.log('File uploaded:', event.file.name);
          }
        }
      }
    });

    // Helper functions
    function updateStatus(text, online) {
      const status = document.getElementById('status');
      status.textContent = text;
      status.className = 'status-indicator' + (online ? '' : ' offline');
    }

    function saveConversation() {
      const conversationId = chatbot.getConversationId();
      const messages = chatbot.getMessages();

      localStorage.setItem('conversation_id', conversationId);
      localStorage.setItem('messages', JSON.stringify(messages));
    }

    function restoreConversation() {
      const conversationId = localStorage.getItem('conversation_id');
      const messages = localStorage.getItem('messages');

      if (conversationId && messages) {
        chatbot.setConversationId(conversationId);

        JSON.parse(messages).forEach(msg => {
          chatbot.addMessage(msg.role, msg.content, msg.metadata);
        });
      }
    }

    // Restore previous conversation
    restoreConversation();

    // Custom trigger button
    document.addEventListener('DOMContentLoaded', () => {
      const triggerBtn = document.createElement('button');
      triggerBtn.textContent = 'Chat with us';
      triggerBtn.onclick = () => chatbot.open();
      document.body.appendChild(triggerBtn);
    });
  </script>
</body>
</html>
```

---

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Opera 76+

**Mobile:**
- iOS Safari 14+
- Chrome Android 90+
- Samsung Internet 14+

## Performance

- **Initial Load:** < 50 KB gzipped
- **Time to Interactive:** < 100ms
- **Memory Usage:** < 10 MB
- **CPU Usage:** < 1% idle

## Security

- **XSS Protection:** DOMPurify sanitization
- **CSP Compatible:** No inline scripts required
- **HTTPS Required:** For production deployments
- **Token Validation:** HMAC authentication support

---

## Support

For additional help:

- **Documentation:** [docs/](../README.md)
- **API Reference:** [api.md](api.md)
- **Public API:** [public-api.md](public-api.md)
- **Admin API:** [admin-api.md](admin-api.md)
- **Issue Tracker:** GitHub Issues
- **Email Support:** support@your-domain.com

---

**Version:** 1.0.1
**Last Updated:** January 2025
**License:** MIT
