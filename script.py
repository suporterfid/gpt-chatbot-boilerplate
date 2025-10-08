import os

# Create the directory structure for the boilerplate
project_structure = {
    'README.md': """# GPT Chatbot Web Integration Boilerplate

An open-source boilerplate for embedding a GPT-powered chatbot on any website with real-time streaming, white-label customization, and easy deployment.

## 🚀 Features

- **Real-Time Streaming**: Support for both Server-Sent Events (SSE) and WebSockets with automatic fallback
- **White-Label Ready**: Completely customizable UI with no hardcoded branding
- **Easy Integration**: Drop-in script tag for any website
- **Mobile Responsive**: Works perfectly on all devices
- **Secure**: API keys protected on server-side with input validation
- **Docker Support**: Complete containerized setup for easy deployment
- **Multi-Turn Conversations**: Maintains conversation context
- **Connection Recovery**: Auto-reconnection and error handling

## 📋 Requirements

- PHP 8.0+ with cURL extension
- Apache or Nginx web server
- OpenAI API key
- Optional: Docker for containerized deployment

## 🚀 Quick Start

### Option 1: Docker (Recommended)

1. Clone the repository:
```bash
git clone https://github.com/your-repo/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate
```

2. Copy environment variables:
```bash
cp .env.example .env
```

3. Edit `.env` and add your OpenAI API key:
```
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-3.5-turbo
```

4. Start with Docker:
```bash
docker-compose up -d
```

5. Open http://localhost:8080 in your browser

### Option 2: Manual Setup

1. Clone the repository to your web server directory
2. Copy `config.php.example` to `config.php` and add your OpenAI API key
3. Ensure PHP has cURL extension enabled
4. For WebSocket support, install Composer dependencies:
```bash
composer install
```

## 💻 Integration

### Basic Integration

Add the chatbot to any website with a simple script tag:

```html
<!-- Include the chatbot CSS and JS -->
<link rel="stylesheet" href="path/to/chatbot.css">
<script src="path/to/chatbot.js"></script>

<!-- Floating chatbot (auto-positioned) -->
<script>
ChatBot.init({
    mode: 'floating',
    apiEndpoint: '/chat.php',
    title: 'Support Chat',
    assistant: {
        name: 'Sarah',
        welcomeMessage: 'Hi! How can I help you today?'
    }
});
</script>
```

### Inline Integration

```html
<!-- Container for inline chatbot -->
<div id="chatbot-container"></div>

<script>
ChatBot.init('#chatbot-container', {
    mode: 'inline',
    height: '500px',
    theme: {
        primaryColor: '#007bff',
        backgroundColor: '#f8f9fa'
    }
});
</script>
```

## 🎨 Customization

### Theme Configuration

```javascript
ChatBot.init({
    theme: {
        primaryColor: '#1FB8CD',        // Primary brand color
        backgroundColor: '#F5F5F5',     // Background color
        fontFamily: 'Arial, sans-serif', // Font family
        borderRadius: '8px',            // Border radius
        shadow: '0 4px 12px rgba(0,0,0,0.15)' // Box shadow
    },
    assistant: {
        name: 'Your Assistant',         // Assistant name
        avatar: '/path/to/avatar.png',  // Avatar image URL
        welcomeMessage: 'Hello!',       // Welcome message
        placeholder: 'Ask me anything...' // Input placeholder
    }
});
```

### Advanced Configuration

```javascript
ChatBot.init({
    // Connection settings
    streamingMode: 'auto',    // 'sse', 'websocket', 'ajax', 'auto'
    apiEndpoint: '/chat.php', // API endpoint URL
    maxMessages: 100,         // Max messages to keep in memory
    
    // UI settings
    mode: 'floating',         // 'inline' or 'floating'
    position: 'bottom-right', // 'bottom-right' or 'bottom-left'
    width: '400px',
    height: '600px',
    
    // Callbacks
    onMessage: function(message) {
        console.log('New message:', message);
    },
    onError: function(error) {
        console.error('Chat error:', error);
    },
    onConnect: function() {
        console.log('Connected to chat');
    }
});
```

## 🔧 Configuration

### Server Configuration (config.php)

```php
<?php
return [
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: 'your_api_key_here',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-3.5-turbo',
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ],
    'chat' => [
        'max_messages' => 50,
        'session_timeout' => 3600, // 1 hour
        'rate_limit' => 60, // requests per minute
    ],
    'security' => [
        'allowed_origins' => ['*'], // CORS origins
        'validate_referer' => false,
        'api_key_validation' => true,
    ]
];
```

### Environment Variables (.env)

```bash
# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-3.5-turbo

# Server Configuration
DEBUG=false
LOG_LEVEL=info
CORS_ORIGINS=*

# WebSocket Configuration (optional)
WEBSOCKET_PORT=8080
WEBSOCKET_HOST=0.0.0.0
```

## 🏗️ Architecture

### File Structure

```
├── README.md              # This file
├── index.html            # Demo page
├── chatbot.js            # Main JavaScript widget
├── chatbot.css           # Default styling
├── chat.php              # SSE streaming endpoint
├── chat_websocket.php    # WebSocket handler
├── websocket-server.php  # Standalone WebSocket server
├── config.php            # Configuration file
├── composer.json         # PHP dependencies
├── docker-compose.yml    # Docker Compose setup
├── Dockerfile            # Docker container
├── .env.example          # Environment variables template
└── docs/                 # Additional documentation
    ├── deployment.md     # Deployment guide
    ├── customization.md  # Customization examples
    └── api.md           # API documentation
```

### Connection Flow

1. **Auto-Detection**: Client tries WebSocket first, falls back to SSE, then AJAX
2. **SSE Mode**: Long-lived HTTP connection with text/event-stream
3. **WebSocket Mode**: Full-duplex communication (requires websocket-server.php)
4. **AJAX Mode**: Traditional request/response (fallback)

## 🚀 Deployment

### Production Deployment

1. **Server Requirements**:
   - PHP 8.0+ with cURL and session support
   - Web server (Apache/Nginx) with SSE support
   - SSL certificate recommended

2. **Apache Configuration** (if using SSE):
   ```apache
   # Enable SSE streaming
   <Location "/chat.php">
       SetEnv no-gzip 1
       SetEnv no-buffer 1
   </Location>
   ```

3. **Nginx Configuration** (if using SSE):
   ```nginx
   location /chat.php {
       proxy_buffering off;
       proxy_cache off;
       add_header X-Accel-Buffering no;
   }
   ```

### Docker Production Setup

```yaml
version: '3.8'
services:
  chatbot:
    build: .
    ports:
      - "80:80"
    environment:
      - OPENAI_API_KEY=${OPENAI_API_KEY}
      - OPENAI_MODEL=gpt-3.5-turbo
    volumes:
      - ./logs:/var/log/apache2
    restart: unless-stopped
```

## 🔐 Security

This boilerplate implements several security best practices:

- **API Key Protection**: OpenAI API key is kept server-side only
- **Input Validation**: All user inputs are validated and sanitized
- **Rate Limiting**: Configurable rate limiting to prevent abuse
- **CORS Control**: Configurable CORS origins
- **Session Security**: Secure session handling for conversation history
- **Error Handling**: Proper error messages without sensitive information disclosure

## 📚 API Reference

### JavaScript API

#### ChatBot.init(container, options)
Initializes the chatbot widget.

**Parameters:**
- `container` (string|Element): CSS selector or DOM element (optional for floating mode)
- `options` (object): Configuration options

**Returns:** ChatBot instance

#### Instance Methods

- `sendMessage(message)`: Send a message programmatically
- `clearHistory()`: Clear conversation history
- `show()`: Show the chatbot (floating mode)
- `hide()`: Hide the chatbot (floating mode)
- `destroy()`: Remove the chatbot from DOM

### PHP API Endpoints

#### POST /chat.php
Main chat endpoint with SSE streaming.

**Request:**
```json
{
    "message": "Hello, how are you?",
    "conversation_id": "optional_conversation_id"
}
```

**Response:** Server-Sent Events stream

#### WebSocket /websocket-server.php
WebSocket endpoint for real-time communication.

## 🐛 Troubleshooting

### Common Issues

1. **SSE Not Working**:
   - Check server configuration for output buffering
   - Verify Content-Type: text/event-stream header
   - Disable gzip compression for SSE endpoints

2. **WebSocket Connection Failed**:
   - Ensure websocket-server.php is running
   - Check firewall settings for WebSocket port
   - Verify Ratchet dependencies are installed

3. **CORS Errors**:
   - Configure allowed origins in config.php
   - Add proper CORS headers

4. **High Server Load**:
   - Implement proper connection limits
   - Use WebSocket mode for high-traffic sites
   - Configure appropriate rate limiting

## 📝 License

MIT License - feel free to use this in commercial and personal projects.

## 🤝 Contributing

Contributions are welcome! Please read our contributing guidelines and submit pull requests.

## 📞 Support

- 📖 Documentation: [Link to docs]
- 🐛 Issues: [GitHub Issues]
- 💬 Discussions: [GitHub Discussions]

---

Made with ❤️ by the open source community
""",
    
    'chatbot.js': """/**
 * GPT Chatbot Widget - Open Source Boilerplate
 * Real-time streaming chatbot with SSE and WebSocket support
 * 
 * @author Open Source Community
 * @version 1.0.0
 * @license MIT
 */

(function(window, document) {
    'use strict';

    // Default configuration
    const DEFAULT_CONFIG = {
        // Basic settings
        mode: 'inline', // 'inline' or 'floating'
        position: 'bottom-right', // 'bottom-right' or 'bottom-left'
        title: 'Chat Assistant',
        height: '400px',
        width: '350px',
        show: false,
        
        // API settings
        apiEndpoint: '/chat.php',
        websocketEndpoint: 'ws://localhost:8080',
        streamingMode: 'auto', // 'sse', 'websocket', 'ajax', 'auto'
        maxMessages: 50,
        enableMarkdown: true,
        
        // Theme customization
        theme: {
            primaryColor: '#1FB8CD',
            backgroundColor: '#F5F5F5',
            surfaceColor: '#FFFFFF',
            textColor: '#333333',
            mutedColor: '#666666',
            fontFamily: 'inherit',
            fontSize: '14px',
            borderRadius: '8px',
            shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
        },
        
        // Assistant settings
        assistant: {
            name: 'Assistant',
            avatar: null,
            welcomeMessage: 'Hello! How can I help you today?',
            placeholder: 'Type your message...',
            thinking: 'Assistant is thinking...'
        },
        
        // UI settings
        animations: true,
        sound: false,
        timestamps: false,
        autoScroll: true,
        
        // Callbacks
        onMessage: null,
        onError: null,
        onConnect: null,
        onDisconnect: null,
        onTyping: null
    };

    /**
     * Main ChatBot class
     */
    class ChatBot {
        constructor(container, options = {}) {
            this.container = typeof container === 'string' ? 
                document.querySelector(container) : container;
            this.options = this.mergeConfig(DEFAULT_CONFIG, options);
            
            // State
            this.messages = [];
            this.conversationId = this.generateConversationId();
            this.isConnected = false;
            this.isTyping = false;
            this.connectionType = null;
            this.eventSource = null;
            this.websocket = null;
            this.currentMessageElement = null;
            
            // UI Elements
            this.widget = null;
            this.chatContainer = null;
            this.messageContainer = null;
            this.inputField = null;
            this.sendButton = null;
            
            this.init();
        }

        /**
         * Initialize the chatbot
         */
        init() {
            this.createWidget();
            this.applyTheme();
            this.bindEvents();
            this.showWelcomeMessage();
            
            if (this.options.mode === 'floating' && this.options.show) {
                this.show();
            }
        }

        /**
         * Create the chatbot widget HTML structure
         */
        createWidget() {
            const widgetHTML = this.options.mode === 'floating' ? 
                this.createFloatingWidget() : this.createInlineWidget();
            
            if (this.options.mode === 'floating') {
                document.body.appendChild(widgetHTML);
                this.widget = widgetHTML;
            } else {
                if (!this.container) {
                    throw new Error('Container element required for inline mode');
                }
                this.container.appendChild(widgetHTML);
                this.widget = widgetHTML;
            }
            
            this.cacheElements();
        }

        /**
         * Create floating widget structure
         */
        createFloatingWidget() {
            const widget = document.createElement('div');
            widget.className = 'chatbot-widget chatbot-floating';
            widget.innerHTML = `
                <div class="chatbot-toggle" title="Open Chat">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 13.54 2.36 14.99 3.01 16.28L2.1 21.9L7.72 20.99C9.01 21.64 10.46 22 12 22C17.52 22 22 17.52 22 12S17.52 2 12 2Z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="chatbot-container" style="display: none;">
                    ${this.getWidgetContent()}
                </div>
            `;
            return widget;
        }

        /**
         * Create inline widget structure
         */
        createInlineWidget() {
            const widget = document.createElement('div');
            widget.className = 'chatbot-widget chatbot-inline';
            widget.innerHTML = `
                <div class="chatbot-container">
                    ${this.getWidgetContent()}
                </div>
            `;
            return widget;
        }

        /**
         * Get widget content HTML
         */
        getWidgetContent() {
            return `
                <div class="chatbot-header">
                    <div class="chatbot-header-info">
                        ${this.options.assistant.avatar ? 
                            `<img src="${this.options.assistant.avatar}" alt="Avatar" class="chatbot-avatar">` : 
                            '<div class="chatbot-avatar-placeholder"></div>'
                        }
                        <div class="chatbot-header-text">
                            <h3 class="chatbot-title">${this.options.title}</h3>
                            <p class="chatbot-status">
                                <span class="chatbot-status-indicator"></span>
                                <span class="chatbot-status-text">Online</span>
                            </p>
                        </div>
                    </div>
                    ${this.options.mode === 'floating' ? 
                        '<button class="chatbot-close" title="Close Chat">✕</button>' : ''
                    }
                </div>
                <div class="chatbot-messages" id="chatbot-messages-${this.conversationId}">
                    <!-- Messages will be added here -->
                </div>
                <div class="chatbot-typing" id="chatbot-typing-${this.conversationId}" style="display: none;">
                    <div class="chatbot-typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="chatbot-typing-text">${this.options.assistant.thinking}</span>
                </div>
                <div class="chatbot-input-container">
                    <div class="chatbot-input-wrapper">
                        <textarea 
                            class="chatbot-input" 
                            placeholder="${this.options.assistant.placeholder}"
                            rows="1"
                            id="chatbot-input-${this.conversationId}"></textarea>
                        <button class="chatbot-send" title="Send Message" id="chatbot-send-${this.conversationId}">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M2.01 21L23 12L2.01 3L2 10L17 12L2 14L2.01 21Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                    <div class="chatbot-footer">
                        <small class="chatbot-powered-by">
                            Powered by <a href="#" target="_blank">Your Brand</a>
                        </small>
                    </div>
                </div>
            `;
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            this.chatContainer = this.widget.querySelector('.chatbot-container');
            this.messageContainer = this.widget.querySelector('.chatbot-messages');
            this.typingContainer = this.widget.querySelector('.chatbot-typing');
            this.inputField = this.widget.querySelector('.chatbot-input');
            this.sendButton = this.widget.querySelector('.chatbot-send');
            this.toggleButton = this.widget.querySelector('.chatbot-toggle');
            this.closeButton = this.widget.querySelector('.chatbot-close');
            this.statusIndicator = this.widget.querySelector('.chatbot-status-indicator');
            this.statusText = this.widget.querySelector('.chatbot-status-text');
        }

        /**
         * Apply theme configuration
         */
        applyTheme() {
            const { theme } = this.options;
            const widget = this.widget;
            
            // Create CSS custom properties
            const style = document.createElement('style');
            style.textContent = `
                .chatbot-widget {
                    --chatbot-primary-color: ${theme.primaryColor};
                    --chatbot-background-color: ${theme.backgroundColor};
                    --chatbot-surface-color: ${theme.surfaceColor};
                    --chatbot-text-color: ${theme.textColor};
                    --chatbot-muted-color: ${theme.mutedColor};
                    --chatbot-font-family: ${theme.fontFamily};
                    --chatbot-font-size: ${theme.fontSize};
                    --chatbot-border-radius: ${theme.borderRadius};
                    --chatbot-shadow: ${theme.shadow};
                }
            `;
            document.head.appendChild(style);
            
            // Apply dimensions
            if (this.options.mode === 'inline') {
                widget.style.height = this.options.height;
                widget.style.width = this.options.width;
            } else {
                this.chatContainer.style.height = this.options.height;
                this.chatContainer.style.width = this.options.width;
            }
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Toggle button (floating mode)
            if (this.toggleButton) {
                this.toggleButton.addEventListener('click', () => this.toggle());
            }
            
            // Close button (floating mode)
            if (this.closeButton) {
                this.closeButton.addEventListener('click', () => this.hide());
            }
            
            // Send button
            this.sendButton.addEventListener('click', () => this.handleSend());
            
            // Input field
            this.inputField.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.handleSend();
                }
            });
            
            // Auto-resize textarea
            this.inputField.addEventListener('input', () => this.autoResizeTextarea());
        }

        /**
         * Show welcome message
         */
        showWelcomeMessage() {
            if (this.options.assistant.welcomeMessage) {
                this.addMessage({
                    role: 'assistant',
                    content: this.options.assistant.welcomeMessage,
                    timestamp: new Date()
                });
            }
        }

        /**
         * Handle send message
         */
        handleSend() {
            const message = this.inputField.value.trim();
            if (!message) return;
            
            // Add user message
            this.addMessage({
                role: 'user',
                content: message,
                timestamp: new Date()
            });
            
            // Clear input
            this.inputField.value = '';
            this.autoResizeTextarea();
            
            // Send message
            this.sendMessage(message);
        }

        /**
         * Send message to API
         */
        async sendMessage(message) {
            this.setTyping(true);
            
            try {
                // Try connection types in order of preference
                if (this.options.streamingMode === 'auto' || this.options.streamingMode === 'websocket') {
                    if (await this.tryWebSocket(message)) {
                        return;
                    }
                }
                
                if (this.options.streamingMode === 'auto' || this.options.streamingMode === 'sse') {
                    if (await this.trySSE(message)) {
                        return;
                    }
                }
                
                // Fallback to AJAX
                await this.tryAjax(message);
                
            } catch (error) {
                this.handleError(error);
            } finally {
                this.setTyping(false);
            }
        }

        /**
         * Try WebSocket connection
         */
        async tryWebSocket(message) {
            return new Promise((resolve) => {
                try {
                    if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                        this.websocket.send(JSON.stringify({
                            message: message,
                            conversation_id: this.conversationId
                        }));
                        resolve(true);
                        return;
                    }
                    
                    const ws = new WebSocket(this.options.websocketEndpoint);
                    
                    ws.onopen = () => {
                        this.websocket = ws;
                        this.connectionType = 'websocket';
                        this.setConnectionStatus(true);
                        
                        ws.send(JSON.stringify({
                            message: message,
                            conversation_id: this.conversationId
                        }));
                        
                        resolve(true);
                    };
                    
                    ws.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        this.handleStreamChunk(data);
                    };
                    
                    ws.onerror = () => {
                        resolve(false);
                    };
                    
                    ws.onclose = () => {
                        this.setConnectionStatus(false);
                        this.websocket = null;
                    };
                    
                    // Timeout fallback
                    setTimeout(() => resolve(false), 2000);
                    
                } catch (error) {
                    resolve(false);
                }
            });
        }

        /**
         * Try SSE connection
         */
        async trySSE(message) {
            return new Promise((resolve) => {
                try {
                    if (this.eventSource) {
                        this.eventSource.close();
                    }
                    
                    const url = new URL(this.options.apiEndpoint, window.location.origin);
                    url.searchParams.append('message', message);
                    url.searchParams.append('conversation_id', this.conversationId);
                    
                    const eventSource = new EventSource(url);
                    this.eventSource = eventSource;
                    this.connectionType = 'sse';
                    
                    eventSource.onopen = () => {
                        this.setConnectionStatus(true);
                        resolve(true);
                    };
                    
                    eventSource.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        this.handleStreamChunk(data);
                    };
                    
                    eventSource.onerror = () => {
                        eventSource.close();
                        this.setConnectionStatus(false);
                        resolve(false);
                    };
                    
                    // Timeout fallback
                    setTimeout(() => {
                        if (eventSource.readyState === EventSource.CONNECTING) {
                            eventSource.close();
                            resolve(false);
                        }
                    }, 3000);
                    
                } catch (error) {
                    resolve(false);
                }
            });
        }

        /**
         * Try AJAX fallback
         */
        async tryAjax(message) {
            this.connectionType = 'ajax';
            
            const response = await fetch(this.options.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    conversation_id: this.conversationId,
                    stream: false
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            this.addMessage({
                role: 'assistant',
                content: data.response,
                timestamp: new Date()
            });
        }

        /**
         * Handle streaming response chunks
         */
        handleStreamChunk(data) {
            if (data.type === 'start') {
                this.currentMessageElement = this.addMessage({
                    role: 'assistant',
                    content: '',
                    timestamp: new Date(),
                    streaming: true
                });
            } else if (data.type === 'chunk') {
                if (this.currentMessageElement) {
                    this.appendToMessage(this.currentMessageElement, data.content);
                }
            } else if (data.type === 'done') {
                if (this.currentMessageElement) {
                    this.finalizeMessage(this.currentMessageElement);
                    this.currentMessageElement = null;
                }
                this.setTyping(false);
                if (this.eventSource) {
                    this.eventSource.close();
                }
            } else if (data.type === 'error') {
                this.handleError(new Error(data.message));
            }
        }

        /**
         * Add message to conversation
         */
        addMessage(message) {
            this.messages.push(message);
            
            // Limit message history
            if (this.messages.length > this.options.maxMessages) {
                this.messages = this.messages.slice(-this.options.maxMessages);
            }
            
            const messageElement = this.createMessageElement(message);
            this.messageContainer.appendChild(messageElement);
            
            if (this.options.autoScroll) {
                this.scrollToBottom();
            }
            
            // Callback
            if (this.options.onMessage) {
                this.options.onMessage(message);
            }
            
            return messageElement;
        }

        /**
         * Create message element
         */
        createMessageElement(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chatbot-message chatbot-message-${message.role}`;
            
            const content = document.createElement('div');
            content.className = 'chatbot-message-content';
            
            if (message.role === 'assistant' && this.options.assistant.avatar) {
                const avatar = document.createElement('img');
                avatar.src = this.options.assistant.avatar;
                avatar.className = 'chatbot-message-avatar';
                messageDiv.appendChild(avatar);
            }
            
            const bubble = document.createElement('div');
            bubble.className = 'chatbot-message-bubble';
            
            if (this.options.enableMarkdown) {
                bubble.innerHTML = this.formatMessage(message.content);
            } else {
                bubble.textContent = message.content;
            }
            
            if (this.options.timestamps && message.timestamp) {
                const timestamp = document.createElement('div');
                timestamp.className = 'chatbot-message-timestamp';
                timestamp.textContent = this.formatTimestamp(message.timestamp);
                bubble.appendChild(timestamp);
            }
            
            content.appendChild(bubble);
            messageDiv.appendChild(content);
            
            if (this.options.animations) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    messageDiv.style.transition = 'all 0.3s ease';
                    messageDiv.style.opacity = '1';
                    messageDiv.style.transform = 'translateY(0)';
                }, 10);
            }
            
            return messageDiv;
        }

        /**
         * Append content to streaming message
         */
        appendToMessage(messageElement, content) {
            const bubble = messageElement.querySelector('.chatbot-message-bubble');
            const currentContent = bubble.textContent || bubble.innerHTML;
            
            if (this.options.enableMarkdown) {
                bubble.innerHTML = this.formatMessage(currentContent + content);
            } else {
                bubble.textContent = currentContent + content;
            }
            
            if (this.options.autoScroll) {
                this.scrollToBottom();
            }
        }

        /**
         * Finalize streaming message
         */
        finalizeMessage(messageElement) {
            messageElement.classList.remove('chatbot-streaming');
        }

        /**
         * Format message content (basic markdown support)
         */
        formatMessage(content) {
            if (!this.options.enableMarkdown) {
                return this.escapeHtml(content);
            }
            
            return content
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/```([\\s\\S]*?)```/g, '<pre><code>$1</code></pre>');
        }

        /**
         * Format timestamp
         */
        formatTimestamp(timestamp) {
            return timestamp.toLocaleTimeString([], { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Set typing indicator
         */
        setTyping(typing) {
            this.isTyping = typing;
            this.typingContainer.style.display = typing ? 'block' : 'none';
            
            if (this.options.autoScroll) {
                this.scrollToBottom();
            }
            
            if (this.options.onTyping) {
                this.options.onTyping(typing);
            }
        }

        /**
         * Set connection status
         */
        setConnectionStatus(connected) {
            this.isConnected = connected;
            
            if (this.statusIndicator) {
                this.statusIndicator.className = `chatbot-status-indicator ${connected ? 'connected' : 'disconnected'}`;
            }
            
            if (this.statusText) {
                this.statusText.textContent = connected ? 'Online' : 'Connecting...';
            }
            
            if (connected && this.options.onConnect) {
                this.options.onConnect();
            } else if (!connected && this.options.onDisconnect) {
                this.options.onDisconnect();
            }
        }

        /**
         * Handle errors
         */
        handleError(error) {
            console.error('ChatBot error:', error);
            
            this.addMessage({
                role: 'assistant',
                content: 'Sorry, I encountered an error. Please try again.',
                timestamp: new Date(),
                error: true
            });
            
            this.setTyping(false);
            
            if (this.options.onError) {
                this.options.onError(error);
            }
        }

        /**
         * Auto-resize textarea
         */
        autoResizeTextarea() {
            const textarea = this.inputField;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        /**
         * Scroll to bottom
         */
        scrollToBottom() {
            setTimeout(() => {
                this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
            }, 10);
        }

        /**
         * Show widget (floating mode)
         */
        show() {
            if (this.options.mode === 'floating') {
                this.chatContainer.style.display = 'block';
                this.toggleButton.style.display = 'none';
                
                if (this.options.animations) {
                    this.chatContainer.style.opacity = '0';
                    this.chatContainer.style.transform = 'translateY(20px) scale(0.95)';
                    setTimeout(() => {
                        this.chatContainer.style.transition = 'all 0.3s ease';
                        this.chatContainer.style.opacity = '1';
                        this.chatContainer.style.transform = 'translateY(0) scale(1)';
                    }, 10);
                }
                
                // Focus input
                setTimeout(() => this.inputField.focus(), 300);
            }
        }

        /**
         * Hide widget (floating mode)
         */
        hide() {
            if (this.options.mode === 'floating') {
                this.chatContainer.style.display = 'none';
                this.toggleButton.style.display = 'flex';
            }
        }

        /**
         * Toggle widget visibility (floating mode)
         */
        toggle() {
            if (this.chatContainer.style.display === 'none') {
                this.show();
            } else {
                this.hide();
            }
        }

        /**
         * Clear conversation history
         */
        clearHistory() {
            this.messages = [];
            this.messageContainer.innerHTML = '';
            this.showWelcomeMessage();
        }

        /**
         * Send message programmatically
         */
        sendMessageProgrammatically(message) {
            this.addMessage({
                role: 'user',
                content: message,
                timestamp: new Date()
            });
            
            this.sendMessage(message);
        }

        /**
         * Destroy widget
         */
        destroy() {
            if (this.eventSource) {
                this.eventSource.close();
            }
            
            if (this.websocket) {
                this.websocket.close();
            }
            
            if (this.widget && this.widget.parentNode) {
                this.widget.parentNode.removeChild(this.widget);
            }
        }

        /**
         * Generate unique conversation ID
         */
        generateConversationId() {
            return 'conv_' + Math.random().toString(36).substr(2, 9);
        }

        /**
         * Merge configuration objects
         */
        mergeConfig(defaults, options) {
            const result = { ...defaults };
            
            for (const key in options) {
                if (typeof options[key] === 'object' && !Array.isArray(options[key]) && options[key] !== null) {
                    result[key] = { ...result[key], ...options[key] };
                } else {
                    result[key] = options[key];
                }
            }
            
            return result;
        }
    }

    // Static methods
    ChatBot.instances = [];

    /**
     * Initialize chatbot with container or floating mode
     */
    ChatBot.init = function(container, options) {
        // If first parameter is not a string or element, treat it as options for floating mode
        if (typeof container === 'object' && !container.nodeType) {
            options = container;
            container = null;
        }
        
        const instance = new ChatBot(container, options);
        ChatBot.instances.push(instance);
        return instance;
    };

    /**
     * Destroy all instances
     */
    ChatBot.destroyAll = function() {
        ChatBot.instances.forEach(instance => instance.destroy());
        ChatBot.instances = [];
    };

    // Export to global scope
    window.ChatBot = ChatBot;

    // Auto-initialize from data attributes
    document.addEventListener('DOMContentLoaded', function() {
        const autoElements = document.querySelectorAll('[data-chatbot]');
        autoElements.forEach(element => {
            try {
                const config = JSON.parse(element.dataset.chatbot || '{}');
                ChatBot.init(element, config);
            } catch (error) {
                console.error('Error auto-initializing chatbot:', error);
            }
        });
    });

})(window, document);
""",

    'chatbot.css': """/* GPT Chatbot Widget Styles */

/* Reset and base styles */
.chatbot-widget {
    --chatbot-primary-color: #1FB8CD;
    --chatbot-background-color: #F5F5F5;
    --chatbot-surface-color: #FFFFFF;
    --chatbot-text-color: #333333;
    --chatbot-muted-color: #666666;
    --chatbot-font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --chatbot-font-size: 14px;
    --chatbot-border-radius: 8px;
    --chatbot-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    
    font-family: var(--chatbot-font-family);
    font-size: var(--chatbot-font-size);
    line-height: 1.5;
    color: var(--chatbot-text-color);
    box-sizing: border-box;
}

.chatbot-widget *,
.chatbot-widget *::before,
.chatbot-widget *::after {
    box-sizing: border-box;
}

/* Floating widget styles */
.chatbot-floating {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 10000;
}

.chatbot-floating.position-left {
    left: 20px;
    right: auto;
}

/* Toggle button (floating mode) */
.chatbot-toggle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--chatbot-primary-color);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--chatbot-shadow);
    transition: all 0.3s ease;
}

.chatbot-toggle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

/* Widget container */
.chatbot-container {
    width: 350px;
    height: 500px;
    background: var(--chatbot-surface-color);
    border-radius: var(--chatbot-border-radius);
    box-shadow: var(--chatbot-shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.chatbot-inline .chatbot-container {
    width: 100%;
    height: 100%;
}

/* Header */
.chatbot-header {
    background: var(--chatbot-primary-color);
    color: white;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chatbot-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chatbot-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.chatbot-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.chatbot-avatar-placeholder::before {
    content: '👤';
    font-size: 20px;
}

.chatbot-header-text {
    flex: 1;
}

.chatbot-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.chatbot-status {
    margin: 4px 0 0;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    opacity: 0.9;
}

.chatbot-status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4ade80;
    animation: pulse 2s infinite;
}

.chatbot-status-indicator.disconnected {
    background: #f87171;
    animation: none;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.chatbot-close {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.chatbot-close:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Messages container */
.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: var(--chatbot-background-color);
    scroll-behavior: smooth;
}

.chatbot-messages::-webkit-scrollbar {
    width: 6px;
}

.chatbot-messages::-webkit-scrollbar-track {
    background: transparent;
}

.chatbot-messages::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.chatbot-messages::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Message styles */
.chatbot-message {
    margin-bottom: 16px;
    display: flex;
    gap: 8px;
}

.chatbot-message-user {
    flex-direction: row-reverse;
}

.chatbot-message-content {
    flex: 1;
    max-width: 80%;
}

.chatbot-message-user .chatbot-message-content {
    display: flex;
    justify-content: flex-end;
}

.chatbot-message-bubble {
    padding: 12px 16px;
    border-radius: var(--chatbot-border-radius);
    word-wrap: break-word;
    position: relative;
}

.chatbot-message-assistant .chatbot-message-bubble {
    background: white;
    color: var(--chatbot-text-color);
    border: 1px solid #e5e7eb;
}

.chatbot-message-user .chatbot-message-bubble {
    background: var(--chatbot-primary-color);
    color: white;
}

.chatbot-message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    margin-top: 2px;
}

.chatbot-message-timestamp {
    font-size: 11px;
    opacity: 0.7;
    margin-top: 4px;
}

/* Code blocks */
.chatbot-message-bubble pre {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 12px;
    margin: 8px 0;
    overflow-x: auto;
    font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, monospace;
    font-size: 13px;
}

.chatbot-message-bubble code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, monospace;
    font-size: 13px;
}

.chatbot-message-bubble pre code {
    background: none;
    padding: 0;
}

/* Typing indicator */
.chatbot-typing {
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--chatbot-background-color);
    color: var(--chatbot-muted-color);
    font-size: 13px;
}

.chatbot-typing-indicator {
    display: flex;
    gap: 4px;
}

.chatbot-typing-indicator span {
    width: 6px;
    height: 6px;
    background: var(--chatbot-muted-color);
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out;
}

.chatbot-typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.chatbot-typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

@keyframes typing {
    0%, 80%, 100% {
        transform: scale(0.8);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Input container */
.chatbot-input-container {
    background: var(--chatbot-surface-color);
    border-top: 1px solid #e5e7eb;
    padding: 16px;
}

.chatbot-input-wrapper {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    background: var(--chatbot-background-color);
    border: 1px solid #e5e7eb;
    border-radius: var(--chatbot-border-radius);
    padding: 8px;
    transition: border-color 0.2s;
}

.chatbot-input-wrapper:focus-within {
    border-color: var(--chatbot-primary-color);
}

.chatbot-input {
    flex: 1;
    border: none;
    background: none;
    resize: none;
    outline: none;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.5;
    min-height: 20px;
    max-height: 100px;
}

.chatbot-input::placeholder {
    color: var(--chatbot-muted-color);
}

.chatbot-send {
    background: var(--chatbot-primary-color);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.chatbot-send:hover:not(:disabled) {
    background: color-mix(in srgb, var(--chatbot-primary-color) 90%, black);
    transform: scale(1.05);
}

.chatbot-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.chatbot-footer {
    text-align: center;
    margin-top: 8px;
}

.chatbot-powered-by {
    color: var(--chatbot-muted-color);
    font-size: 11px;
}

.chatbot-powered-by a {
    color: var(--chatbot-primary-color);
    text-decoration: none;
}

.chatbot-powered-by a:hover {
    text-decoration: underline;
}

/* Error message styles */
.chatbot-message-error .chatbot-message-bubble {
    background: #fee2e2;
    border-color: #fecaca;
    color: #dc2626;
}

/* Streaming message styles */
.chatbot-message.chatbot-streaming .chatbot-message-bubble::after {
    content: '|';
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}

/* Mobile responsive styles */
@media (max-width: 480px) {
    .chatbot-floating {
        bottom: 10px;
        right: 10px;
        left: 10px;
        width: auto;
    }
    
    .chatbot-container {
        width: 100%;
        height: 70vh;
        max-height: 600px;
    }
    
    .chatbot-toggle {
        width: 50px;
        height: 50px;
        position: fixed;
        bottom: 20px;
        right: 20px;
        left: auto;
        width: auto;
    }
    
    .chatbot-message-content {
        max-width: 90%;
    }
    
    .chatbot-header {
        padding: 12px;
    }
    
    .chatbot-messages {
        padding: 12px;
    }
    
    .chatbot-input-container {
        padding: 12px;
    }
}

/* Dark theme support */
@media (prefers-color-scheme: dark) {
    .chatbot-widget {
        --chatbot-background-color: #1f2937;
        --chatbot-surface-color: #374151;
        --chatbot-text-color: #f9fafb;
        --chatbot-muted-color: #9ca3af;
    }
    
    .chatbot-message-assistant .chatbot-message-bubble {
        background: #4b5563;
        color: #f9fafb;
        border-color: #6b7280;
    }
    
    .chatbot-input-wrapper {
        background: #374151;
        border-color: #6b7280;
    }
    
    .chatbot-messages::-webkit-scrollbar-thumb {
        background: #6b7280;
    }
    
    .chatbot-message-bubble pre {
        background: #1f2937;
        border-color: #374151;
        color: #f9fafb;
    }
    
    .chatbot-message-bubble code {
        background: #1f2937;
        color: #f9fafb;
    }
}

/* Print styles */
@media print {
    .chatbot-widget {
        display: none !important;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .chatbot-widget {
        --chatbot-border-radius: 4px;
    }
    
    .chatbot-message-bubble {
        border: 2px solid currentColor;
    }
    
    .chatbot-input-wrapper {
        border-width: 2px;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .chatbot-widget *,
    .chatbot-widget *::before,
    .chatbot-widget *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    
    .chatbot-typing-indicator span {
        animation: none;
        opacity: 0.7;
    }
    
    .chatbot-status-indicator {
        animation: none;
    }
}
""",

    'chat.php': """<?php
/**
 * GPT Chatbot - Server-Sent Events Endpoint
 * Handles real-time streaming communication with OpenAI API
 */

require_once 'config.php';

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Configure for streaming
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// Send headers to prevent nginx buffering
header('X-Accel-Buffering: no');

/**
 * Send SSE event to client
 */
function sendSSEEvent($type, $data = null, $id = null) {
    if ($id !== null) {
        echo "id: $id\\n";
    }
    
    echo "event: $type\\n";
    
    if ($data !== null) {
        $jsonData = json_encode($data);
        echo "data: $jsonData\\n";
    }
    
    echo "\\n";
    flush();
}

/**
 * Log errors
 */
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] ERROR: $message";
    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context);
    }
    error_log($logMessage);
}

/**
 * Validate request
 */
function validateRequest() {
    global $config;
    
    // Check if API key is configured
    if (empty($config['openai']['api_key'])) {
        throw new Exception('OpenAI API key not configured');
    }
    
    // Basic rate limiting (you may want to use Redis or database for production)
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = sys_get_temp_dir() . '/chatbot_rate_limit_' . md5($clientIP);
    
    if (file_exists($rateLimitFile)) {
        $lastRequest = filemtime($rateLimitFile);
        if (time() - $lastRequest < 2) { // 2 second rate limit
            throw new Exception('Rate limit exceeded. Please wait before sending another message.');
        }
    }
    
    touch($rateLimitFile);
}

/**
 * Get conversation history from session
 */
function getConversationHistory($conversationId) {
    session_start();
    $sessionKey = 'chatbot_conversation_' . $conversationId;
    return $_SESSION[$sessionKey] ?? [];
}

/**
 * Save conversation history to session
 */
function saveConversationHistory($conversationId, $messages) {
    global $config;
    
    session_start();
    $sessionKey = 'chatbot_conversation_' . $conversationId;
    
    // Limit conversation history
    $maxMessages = $config['chat']['max_messages'] ?? 50;
    if (count($messages) > $maxMessages) {
        $messages = array_slice($messages, -$maxMessages);
    }
    
    $_SESSION[$sessionKey] = $messages;
}

/**
 * Stream response from OpenAI
 */
function streamOpenAIResponse($messages) {
    global $config;
    
    $apiKey = $config['openai']['api_key'];
    $model = $config['openai']['model'] ?? 'gpt-3.5-turbo';
    $temperature = $config['openai']['temperature'] ?? 0.7;
    $maxTokens = $config['openai']['max_tokens'] ?? 1000;
    
    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'stream' => true
    ];
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            static $buffer = '';
            static $messageStarted = false;
            
            $buffer .= $data;
            $lines = explode("\\n", $buffer);
            $buffer = array_pop($lines); // Keep incomplete line in buffer
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    
                    if ($json === '[DONE]') {
                        sendSSEEvent('message', [
                            'type' => 'done'
                        ]);
                        return strlen($data);
                    }
                    
                    $decoded = json_decode($json, true);
                    if ($decoded && isset($decoded['choices'][0]['delta'])) {
                        $delta = $decoded['choices'][0]['delta'];
                        
                        if (isset($delta['content'])) {
                            if (!$messageStarted) {
                                sendSSEEvent('message', [
                                    'type' => 'start'
                                ]);
                                $messageStarted = true;
                            }
                            
                            sendSSEEvent('message', [
                                'type' => 'chunk',
                                'content' => $delta['content']
                            ]);
                        }
                        
                        if (isset($delta['finish_reason']) && $delta['finish_reason'] === 'stop') {
                            sendSSEEvent('message', [
                                'type' => 'done'
                            ]);
                        }
                    }
                }
            }
            
            // Check if client disconnected
            if (connection_aborted()) {
                return 0;
            }
            
            return strlen($data);
        },
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($result === false) {
        throw new Exception('cURL error: ' . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('OpenAI API error: HTTP ' . $httpCode);
    }
}

// Main execution
try {
    // Validate request
    validateRequest();
    
    // Get request data
    $method = $_SERVER['REQUEST_METHOD'];
    $message = '';
    $conversationId = '';
    
    if ($method === 'GET') {
        $message = $_GET['message'] ?? '';
        $conversationId = $_GET['conversation_id'] ?? '';
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? '';
    }
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    if (empty($conversationId)) {
        $conversationId = 'default';
    }
    
    // Get conversation history
    $messages = getConversationHistory($conversationId);
    
    // Add user message
    $messages[] = [
        'role' => 'user',
        'content' => $message
    ];
    
    // Send start event
    sendSSEEvent('start', [
        'conversation_id' => $conversationId
    ]);
    
    // Stream response from OpenAI
    streamOpenAIResponse($messages);
    
    // Note: We can't save the assistant's response here since it's streamed
    // The client should send another request to save the conversation if needed
    
} catch (Exception $e) {
    logError($e->getMessage(), [
        'file' => __FILE__,
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    sendSSEEvent('error', [
        'message' => 'An error occurred while processing your request.'
    ]);
} finally {
    // Close SSE connection
    sendSSEEvent('close', null);
    exit();
}
?>""",

    'config.php': """<?php
/**
 * GPT Chatbot Configuration
 */

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $env = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$config = [
    // OpenAI Configuration
    'openai' => [
        'api_key' => $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '',
        'model' => $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-3.5-turbo',
        'temperature' => (float)($_ENV['OPENAI_TEMPERATURE'] ?? getenv('OPENAI_TEMPERATURE') ?: 0.7),
        'max_tokens' => (int)($_ENV['OPENAI_MAX_TOKENS'] ?? getenv('OPENAI_MAX_TOKENS') ?: 1000),
        'top_p' => (float)($_ENV['OPENAI_TOP_P'] ?? getenv('OPENAI_TOP_P') ?: 1.0),
        'frequency_penalty' => (float)($_ENV['OPENAI_FREQUENCY_PENALTY'] ?? getenv('OPENAI_FREQUENCY_PENALTY') ?: 0.0),
        'presence_penalty' => (float)($_ENV['OPENAI_PRESENCE_PENALTY'] ?? getenv('OPENAI_PRESENCE_PENALTY') ?: 0.0),
    ],
    
    // Chat Configuration
    'chat' => [
        'max_messages' => (int)($_ENV['CHAT_MAX_MESSAGES'] ?? getenv('CHAT_MAX_MESSAGES') ?: 50),
        'session_timeout' => (int)($_ENV['CHAT_SESSION_TIMEOUT'] ?? getenv('CHAT_SESSION_TIMEOUT') ?: 3600),
        'rate_limit_requests' => (int)($_ENV['CHAT_RATE_LIMIT'] ?? getenv('CHAT_RATE_LIMIT') ?: 60),
        'rate_limit_window' => (int)($_ENV['CHAT_RATE_WINDOW'] ?? getenv('CHAT_RATE_WINDOW') ?: 60),
        'enable_logging' => filter_var($_ENV['CHAT_ENABLE_LOGGING'] ?? getenv('CHAT_ENABLE_LOGGING') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ],
    
    // Security Configuration
    'security' => [
        'allowed_origins' => explode(',', $_ENV['CORS_ORIGINS'] ?? getenv('CORS_ORIGINS') ?: '*'),
        'validate_referer' => filter_var($_ENV['VALIDATE_REFERER'] ?? getenv('VALIDATE_REFERER') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'api_key_validation' => filter_var($_ENV['API_KEY_VALIDATION'] ?? getenv('API_KEY_VALIDATION') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'sanitize_input' => filter_var($_ENV['SANITIZE_INPUT'] ?? getenv('SANITIZE_INPUT') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'max_message_length' => (int)($_ENV['MAX_MESSAGE_LENGTH'] ?? getenv('MAX_MESSAGE_LENGTH') ?: 4000),
    ],
    
    // WebSocket Configuration
    'websocket' => [
        'enabled' => filter_var($_ENV['WEBSOCKET_ENABLED'] ?? getenv('WEBSOCKET_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'host' => $_ENV['WEBSOCKET_HOST'] ?? getenv('WEBSOCKET_HOST') ?: '0.0.0.0',
        'port' => (int)($_ENV['WEBSOCKET_PORT'] ?? getenv('WEBSOCKET_PORT') ?: 8080),
        'ssl' => filter_var($_ENV['WEBSOCKET_SSL'] ?? getenv('WEBSOCKET_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'ssl_cert' => $_ENV['WEBSOCKET_SSL_CERT'] ?? getenv('WEBSOCKET_SSL_CERT') ?: '',
        'ssl_key' => $_ENV['WEBSOCKET_SSL_KEY'] ?? getenv('WEBSOCKET_SSL_KEY') ?: '',
    ],
    
    // Logging Configuration
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? getenv('LOG_LEVEL') ?: 'info',
        'file' => $_ENV['LOG_FILE'] ?? getenv('LOG_FILE') ?: 'logs/chatbot.log',
        'max_size' => (int)($_ENV['LOG_MAX_SIZE'] ?? getenv('LOG_MAX_SIZE') ?: 10485760), // 10MB
        'max_files' => (int)($_ENV['LOG_MAX_FILES'] ?? getenv('LOG_MAX_FILES') ?: 5),
    ],
    
    // Performance Configuration
    'performance' => [
        'cache_enabled' => filter_var($_ENV['CACHE_ENABLED'] ?? getenv('CACHE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'cache_ttl' => (int)($_ENV['CACHE_TTL'] ?? getenv('CACHE_TTL') ?: 3600),
        'compression_enabled' => filter_var($_ENV['COMPRESSION_ENABLED'] ?? getenv('COMPRESSION_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ]
];

// Validate critical configuration
if (empty($config['openai']['api_key'])) {
    error_log('WARNING: OpenAI API key not configured. Please set OPENAI_API_KEY environment variable.');
}

return $config;
?>""",

    '.env.example': """# OpenAI Configuration
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-3.5-turbo
OPENAI_TEMPERATURE=0.7
OPENAI_MAX_TOKENS=1000
OPENAI_TOP_P=1.0
OPENAI_FREQUENCY_PENALTY=0.0
OPENAI_PRESENCE_PENALTY=0.0

# Chat Configuration
CHAT_MAX_MESSAGES=50
CHAT_SESSION_TIMEOUT=3600
CHAT_RATE_LIMIT=60
CHAT_RATE_WINDOW=60
CHAT_ENABLE_LOGGING=true

# Security Configuration
CORS_ORIGINS=*
VALIDATE_REFERER=false
API_KEY_VALIDATION=true
SANITIZE_INPUT=true
MAX_MESSAGE_LENGTH=4000

# WebSocket Configuration (Optional)
WEBSOCKET_ENABLED=false
WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=8080
WEBSOCKET_SSL=false
WEBSOCKET_SSL_CERT=
WEBSOCKET_SSL_KEY=

# Logging Configuration
LOG_LEVEL=info
LOG_FILE=logs/chatbot.log
LOG_MAX_SIZE=10485760
LOG_MAX_FILES=5

# Performance Configuration
CACHE_ENABLED=false
CACHE_TTL=3600
COMPRESSION_ENABLED=true

# Development Configuration
DEBUG=false
""",

    'websocket-server.php': """<?php
/**
 * GPT Chatbot - WebSocket Server
 * Standalone WebSocket server for real-time communication
 * 
 * Usage: php websocket-server.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Ratchet\\MessageComponentInterface;
use Ratchet\\ConnectionInterface;
use Ratchet\\Server\\IoServer;
use Ratchet\\Http\\HttpServer;
use Ratchet\\WebSocket\\WsServer;

class ChatBotWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $conversations;
    private $config;

    public function __construct($config) {
        $this->clients = new \\SplObjectStorage;
        $this->conversations = [];
        $this->config = $config;
        
        echo "ChatBot WebSocket Server initialized\\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        
        echo "New connection: {$conn->resourceId}\\n";
        
        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'Connected to ChatBot WebSocket server'
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Message from {$from->resourceId}: {$msg}\\n";
        
        try {
            $data = json_decode($msg, true);
            
            if (!isset($data['message'])) {
                throw new Exception('Message field is required');
            }
            
            $message = $data['message'];
            $conversationId = $data['conversation_id'] ?? 'default';
            
            // Validate message
            if (empty(trim($message))) {
                throw new Exception('Message cannot be empty');
            }
            
            if (strlen($message) > ($this->config['security']['max_message_length'] ?? 4000)) {
                throw new Exception('Message too long');
            }
            
            // Get conversation history
            if (!isset($this->conversations[$conversationId])) {
                $this->conversations[$conversationId] = [];
            }
            
            // Add user message
            $this->conversations[$conversationId][] = [
                'role' => 'user',
                'content' => $message
            ];
            
            // Send start event
            $from->send(json_encode([
                'type' => 'start',
                'conversation_id' => $conversationId
            ]));
            
            // Stream response from OpenAI
            $this->streamOpenAIResponse($from, $this->conversations[$conversationId], $conversationId);
            
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\\n";
            
            $from->send(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ]));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\\n";
    }

    public function onError(ConnectionInterface $conn, \\Exception $e) {
        echo "Error on connection {$conn->resourceId}: {$e->getMessage()}\\n";
        $conn->close();
    }

    private function streamOpenAIResponse(ConnectionInterface $conn, $messages, $conversationId) {
        $apiKey = $this->config['openai']['api_key'];
        $model = $this->config['openai']['model'] ?? 'gpt-3.5-turbo';
        
        if (empty($apiKey)) {
            throw new Exception('OpenAI API key not configured');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->config['openai']['temperature'] ?? 0.7,
            'max_tokens' => $this->config['openai']['max_tokens'] ?? 1000,
            'stream' => true
        ];

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($conn, $conversationId) {
                static $buffer = '';
                static $messageStarted = false;
                static $assistantMessage = '';
                
                $buffer .= $data;
                $lines = explode("\\n", $buffer);
                $buffer = array_pop($lines);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strpos($line, 'data: ') === 0) {
                        $json = substr($line, 6);
                        
                        if ($json === '[DONE]') {
                            // Save assistant message to conversation
                            if (!empty($assistantMessage)) {
                                $this->conversations[$conversationId][] = [
                                    'role' => 'assistant',
                                    'content' => $assistantMessage
                                ];
                                
                                // Limit conversation history
                                $maxMessages = $this->config['chat']['max_messages'] ?? 50;
                                if (count($this->conversations[$conversationId]) > $maxMessages) {
                                    $this->conversations[$conversationId] = array_slice(
                                        $this->conversations[$conversationId], 
                                        -$maxMessages
                                    );
                                }
                            }
                            
                            $conn->send(json_encode([
                                'type' => 'done'
                            ]));
                            return strlen($data);
                        }
                        
                        $decoded = json_decode($json, true);
                        if ($decoded && isset($decoded['choices'][0]['delta'])) {
                            $delta = $decoded['choices'][0]['delta'];
                            
                            if (isset($delta['content'])) {
                                if (!$messageStarted) {
                                    $conn->send(json_encode([
                                        'type' => 'start'
                                    ]));
                                    $messageStarted = true;
                                }
                                
                                $content = $delta['content'];
                                $assistantMessage .= $content;
                                
                                $conn->send(json_encode([
                                    'type' => 'chunk',
                                    'content' => $content
                                ]));
                            }
                            
                            if (isset($delta['finish_reason']) && $delta['finish_reason'] === 'stop') {
                                $conn->send(json_encode([
                                    'type' => 'done'
                                ]));
                            }
                        }
                    }
                }
                
                return strlen($data);
            },
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($result === false) {
            throw new Exception('cURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('OpenAI API error: HTTP ' . $httpCode);
        }
    }
}

// Check if Ratchet is installed
if (!class_exists('Ratchet\\Server\\IoServer')) {
    echo "Error: Ratchet WebSocket library not installed.\\n";
    echo "Please run: composer install\\n";
    exit(1);
}

// Load configuration
$config = require_once 'config.php';

// Check WebSocket configuration
if (!($config['websocket']['enabled'] ?? false)) {
    echo "WebSocket server is disabled in configuration.\\n";
    echo "Set WEBSOCKET_ENABLED=true in your .env file to enable.\\n";
    exit(1);
}

// Start WebSocket server
$host = $config['websocket']['host'] ?? '0.0.0.0';
$port = $config['websocket']['port'] ?? 8080;

echo "Starting ChatBot WebSocket Server on {$host}:{$port}\\n";
echo "Press Ctrl+C to stop the server\\n\\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatBotWebSocket($config)
        )
    ),
    $port,
    $host
);

// Handle graceful shutdown
pcntl_signal(SIGTERM, function() use ($server) {
    echo "\\nShutting down WebSocket server...\\n";
    $server->loop->stop();
});

pcntl_signal(SIGINT, function() use ($server) {
    echo "\\nShutting down WebSocket server...\\n";
    $server->loop->stop();
});

$server->run();
?>""",

    'composer.json': """{
    "name": "chatbot/gpt-boilerplate",
    "description": "Open-source GPT chatbot web integration boilerplate",
    "type": "project",
    "keywords": ["chatbot", "gpt", "openai", "websocket", "sse", "php"],
    "license": "MIT",
    "authors": [
        {
            "name": "Open Source Community",
            "email": "info@example.com"
        }
    ],
    "require": {
        "php": ">=8.0",
        "ext-curl": "*",
        "ext-json": "*",
        "ratchet/pawl": "^0.4",
        "ratchet/ratchet": "^0.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0"
    },
    "autoload": {
        "psr-4": {
            "ChatBot\\\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "ChatBot\\\\Tests\\\\": "tests/"
        }
    },
    "scripts": {
        "websocket": "php websocket-server.php",
        "test": "phpunit"
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}""",

    'Dockerfile': """FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \\
    libcurl4-openssl-dev \\
    pkg-config \\
    libssl-dev \\
    git \\
    unzip \\
    && docker-php-ext-install curl \\
    && docker-php-ext-install sockets \\
    && apt-get clean \\
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# Configure Apache for SSE
RUN echo "LoadModule headers_module modules/mod_headers.so" >> /etc/apache2/apache2.conf
RUN echo "<Location \"/chat.php\">" >> /etc/apache2/apache2.conf
RUN echo "    SetEnv no-gzip 1" >> /etc/apache2/apache2.conf
RUN echo "    SetEnv no-buffer 1" >> /etc/apache2/apache2.conf
RUN echo "</Location>" >> /etc/apache2/apache2.conf

# Configure PHP for streaming
RUN echo "output_buffering = Off" >> /usr/local/etc/php/php.ini
RUN echo "zlib.output_compression = Off" >> /usr/local/etc/php/php.ini
RUN echo "implicit_flush = On" >> /usr/local/etc/php/php.ini
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Create logs directory
RUN mkdir -p logs && chown www-data:www-data logs

# Install PHP dependencies (if composer.json exists)
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \\
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
""",

    'docker-compose.yml': """version: '3.8'

services:
  chatbot:
    build: .
    ports:
      - "8080:80"
    environment:
      - OPENAI_API_KEY=${OPENAI_API_KEY}
      - OPENAI_MODEL=${OPENAI_MODEL:-gpt-3.5-turbo}
      - DEBUG=${DEBUG:-false}
      - LOG_LEVEL=${LOG_LEVEL:-info}
      - CORS_ORIGINS=${CORS_ORIGINS:-*}
    volumes:
      - ./logs:/var/www/html/logs
      - ./.env:/var/www/html/.env:ro
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s

  # Optional WebSocket server (uncomment if needed)
  # websocket:
  #   build: .
  #   command: php websocket-server.php
  #   ports:
  #     - "8081:8080"
  #   environment:
  #     - OPENAI_API_KEY=${OPENAI_API_KEY}
  #     - WEBSOCKET_ENABLED=true
  #     - WEBSOCKET_HOST=0.0.0.0
  #     - WEBSOCKET_PORT=8080
  #   volumes:
  #     - ./logs:/var/www/html/logs
  #     - ./.env:/var/www/html/.env:ro
  #   restart: unless-stopped
  #   depends_on:
  #     - chatbot

volumes:
  logs:
    driver: local

networks:
  default:
    driver: bridge
""",

    'index.html': """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPT Chatbot Boilerplate - Demo</title>
    <link rel="stylesheet" href="chatbot.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
            color: #333;
        }
        
        .demo-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .demo-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .demo-header h1 {
            margin: 0 0 16px;
            font-size: 2.5rem;
            color: #1FB8CD;
        }
        
        .demo-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin: 0 0 24px;
        }
        
        .demo-badges {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .badge {
            background: #e7f6f8;
            color: #1FB8CD;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .demo-section {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .demo-section h2 {
            margin: 0 0 20px;
            color: #333;
        }
        
        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 32px;
        }
        
        .demo-box {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            background: #fafafa;
        }
        
        .demo-box h3 {
            margin: 0 0 16px;
            color: #1FB8CD;
        }
        
        .demo-controls {
            margin: 20px 0;
        }
        
        .demo-controls label {
            display: block;
            margin: 8px 0;
        }
        
        .demo-controls input,
        .demo-controls select {
            margin-left: 8px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            background: #1FB8CD;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #178a9b;
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 0.9em;
        }
        
        pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 16px;
            overflow-x: auto;
            font-size: 0.9em;
        }
        
        .status {
            padding: 12px;
            border-radius: 6px;
            margin: 16px 0;
        }
        
        .status.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .status.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .status.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        @media (max-width: 768px) {
            .demo-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .demo-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <!-- Header -->
        <div class="demo-header">
            <h1>🤖 GPT Chatbot Boilerplate</h1>
            <p class="demo-subtitle">Open-source, white-label GPT-powered chatbot for any website</p>
            <div class="demo-badges">
                <span class="badge">🚀 Real-time Streaming</span>
                <span class="badge">🎨 White-label Ready</span>
                <span class="badge">📱 Mobile Responsive</span>
                <span class="badge">🔒 Secure</span>
                <span class="badge">🐳 Docker Support</span>
            </div>
        </div>

        <!-- Configuration Status -->
        <div class="demo-section">
            <h2>Configuration Status</h2>
            <div id="config-status">
                <div class="status warning">
                    <strong>⚠️ Checking configuration...</strong>
                    <p>Please wait while we verify your setup.</p>
                </div>
            </div>
        </div>

        <!-- Live Demos -->
        <div class="demo-section">
            <h2>Live Demos</h2>
            <div class="demo-grid">
                <!-- Inline Demo -->
                <div class="demo-box">
                    <h3>Inline Chatbot</h3>
                    <p>Embedded directly in the page content</p>
                    
                    <div class="demo-controls">
                        <label>
                            <strong>Height:</strong>
                            <select id="inline-height">
                                <option value="400px">400px</option>
                                <option value="500px" selected>500px</option>
                                <option value="600px">600px</option>
                            </select>
                        </label>
                        <label>
                            <strong>Theme:</strong>
                            <select id="inline-theme">
                                <option value="default" selected>Default</option>
                                <option value="blue">Blue</option>
                                <option value="green">Green</option>
                                <option value="purple">Purple</option>
                            </select>
                        </label>
                        <button class="btn" onclick="updateInlineDemo()">Update Demo</button>
                        <button class="btn btn-secondary" onclick="clearInlineChat()">Clear Chat</button>
                    </div>
                    
                    <!-- Inline chatbot will be inserted here -->
                    <div id="inline-chatbot" style="margin-top: 20px;"></div>
                </div>

                <!-- Floating Demo -->
                <div class="demo-box">
                    <h3>Floating Chatbot</h3>
                    <p>Floating widget that appears on top of content</p>
                    
                    <div class="demo-controls">
                        <label>
                            <strong>Position:</strong>
                            <select id="floating-position">
                                <option value="bottom-right" selected>Bottom Right</option>
                                <option value="bottom-left">Bottom Left</option>
                            </select>
                        </label>
                        <label>
                            <strong>Theme:</strong>
                            <select id="floating-theme">
                                <option value="default" selected>Default</option>
                                <option value="dark">Dark</option>
                                <option value="minimal">Minimal</option>
                            </select>
                        </label>
                        <button class="btn" onclick="showFloatingDemo()">Show Floating</button>
                        <button class="btn btn-secondary" onclick="hideFloatingDemo()">Hide Floating</button>
                    </div>
                    
                    <p><em>Click "Show Floating" to see the floating chatbot in action!</em></p>
                </div>
            </div>
        </div>

        <!-- Integration Examples -->
        <div class="demo-section">
            <h2>Integration Examples</h2>
            
            <h3>Basic Integration</h3>
            <pre><code>&lt;!-- Include the chatbot files --&gt;
&lt;link rel="stylesheet" href="chatbot.css"&gt;
&lt;script src="chatbot.js"&gt;&lt;/script&gt;

&lt;!-- Initialize floating chatbot --&gt;
&lt;script&gt;
ChatBot.init({
    mode: 'floating',
    apiEndpoint: '/chat.php',
    title: 'Support Chat',
    assistant: {
        name: 'Assistant',
        welcomeMessage: 'Hi! How can I help you today?'
    }
});
&lt;/script&gt;</code></pre>

            <h3>Inline Integration</h3>
            <pre><code>&lt;!-- Container for inline chatbot --&gt;
&lt;div id="chatbot-container"&gt;&lt;/div&gt;

&lt;script&gt;
ChatBot.init('#chatbot-container', {
    mode: 'inline',
    height: '500px',
    theme: {
        primaryColor: '#007bff',
        backgroundColor: '#f8f9fa'
    }
});
&lt;/script&gt;</code></pre>

            <h3>Advanced Customization</h3>
            <pre><code>ChatBot.init({
    mode: 'floating',
    position: 'bottom-left',
    theme: {
        primaryColor: '#1FB8CD',
        backgroundColor: '#F5F5F5',
        fontFamily: 'Inter, sans-serif',
        borderRadius: '12px'
    },
    assistant: {
        name: 'Sarah',
        avatar: '/assets/avatar.png',
        welcomeMessage: 'Hello! I'm Sarah, your AI assistant.',
        placeholder: 'Ask me anything...'
    },
    onMessage: function(message) {
        console.log('New message:', message);
    },
    onError: function(error) {
        console.error('Chat error:', error);
    }
});
&lt;/script&gt;</code></pre>
        </div>

        <!-- Features -->
        <div class="demo-section">
            <h2>Key Features</h2>
            <div class="demo-grid">
                <div>
                    <h3>🚀 Real-time Streaming</h3>
                    <ul>
                        <li>Server-Sent Events (SSE) support</li>
                        <li>WebSocket support with fallback</li>
                        <li>Automatic connection recovery</li>
                        <li>Typing indicators</li>
                    </ul>
                </div>
                <div>
                    <h3>🎨 Customization</h3>
                    <ul>
                        <li>White-label ready</li>
                        <li>Custom themes and colors</li>
                        <li>Flexible layouts</li>
                        <li>Mobile responsive</li>
                    </ul>
                </div>
                <div>
                    <h3>🔒 Security</h3>
                    <ul>
                        <li>Server-side API key protection</li>
                        <li>Input validation and sanitization</li>
                        <li>Rate limiting</li>
                        <li>CORS configuration</li>
                    </ul>
                </div>
                <div>
                    <h3>⚡ Performance</h3>
                    <ul>
                        <li>Lightweight vanilla JavaScript</li>
                        <li>Efficient PHP backend</li>
                        <li>Connection pooling</li>
                        <li>Caching support</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Quick Setup Guide -->
        <div class="demo-section">
            <h2>Quick Setup</h2>
            
            <h3>1. Environment Setup</h3>
            <pre><code># Copy environment template
cp .env.example .env

# Edit .env and add your OpenAI API key
OPENAI_API_KEY=your_api_key_here</code></pre>

            <h3>2. Docker Setup (Recommended)</h3>
            <pre><code># Start with Docker
docker-compose up -d

# View logs
docker-compose logs -f</code></pre>

            <h3>3. Manual Setup</h3>
            <pre><code># Install PHP dependencies (optional)
composer install

# Set permissions
chmod -R 755 .
chmod -R 777 logs/

# Start PHP development server
php -S localhost:8080</code></pre>
        </div>
    </div>

    <!-- Load the chatbot -->
    <script src="chatbot.js"></script>
    
    <script>
        // Demo variables
        let inlineChatBot = null;
        let floatingChatBot = null;

        // Theme configurations
        const themes = {
            default: {
                primaryColor: '#1FB8CD',
                backgroundColor: '#F5F5F5',
                surfaceColor: '#FFFFFF'
            },
            blue: {
                primaryColor: '#3B82F6',
                backgroundColor: '#EFF6FF',
                surfaceColor: '#FFFFFF'
            },
            green: {
                primaryColor: '#10B981',
                backgroundColor: '#ECFDF5',
                surfaceColor: '#FFFFFF'
            },
            purple: {
                primaryColor: '#8B5CF6',
                backgroundColor: '#F5F3FF',
                surfaceColor: '#FFFFFF'
            },
            dark: {
                primaryColor: '#1FB8CD',
                backgroundColor: '#1F2937',
                surfaceColor: '#374151'
            },
            minimal: {
                primaryColor: '#6B7280',
                backgroundColor: '#F9FAFB',
                surfaceColor: '#FFFFFF'
            }
        };

        // Check configuration status
        function checkConfiguration() {
            fetch('/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: 'test', test: true })
            })
            .then(response => {
                const statusEl = document.getElementById('config-status');
                if (response.ok) {
                    statusEl.innerHTML = `
                        <div class="status success">
                            <strong>✅ Configuration OK</strong>
                            <p>Your chatbot is properly configured and ready to use!</p>
                        </div>
                    `;
                } else {
                    throw new Error('Configuration error');
                }
            })
            .catch(error => {
                const statusEl = document.getElementById('config-status');
                statusEl.innerHTML = `
                    <div class="status error">
                        <strong>❌ Configuration Error</strong>
                        <p>Please check your OpenAI API key configuration in config.php or .env file.</p>
                    </div>
                `;
            });
        }

        // Initialize inline demo
        function initInlineDemo() {
            const height = document.getElementById('inline-height').value;
            const themeKey = document.getElementById('inline-theme').value;
            
            if (inlineChatBot) {
                inlineChatBot.destroy();
            }
            
            inlineChatBot = ChatBot.init('#inline-chatbot', {
                mode: 'inline',
                height: height,
                theme: themes[themeKey],
                assistant: {
                    name: 'Demo Bot',
                    welcomeMessage: `Hello! I'm your ${themeKey} themed assistant. Try asking me something!`,
                    placeholder: 'Type your message here...'
                },
                onError: function(error) {
                    console.error('Inline chat error:', error);
                }
            });
        }

        // Update inline demo
        function updateInlineDemo() {
            initInlineDemo();
        }

        // Clear inline chat
        function clearInlineChat() {
            if (inlineChatBot) {
                inlineChatBot.clearHistory();
            }
        }

        // Show floating demo
        function showFloatingDemo() {
            const position = document.getElementById('floating-position').value;
            const themeKey = document.getElementById('floating-theme').value;
            
            if (floatingChatBot) {
                floatingChatBot.destroy();
            }
            
            floatingChatBot = ChatBot.init({
                mode: 'floating',
                position: position,
                theme: themes[themeKey],
                show: true,
                assistant: {
                    name: 'Floating Bot',
                    welcomeMessage: `Hi! I'm your ${themeKey} floating assistant. Click the close button to hide me.`,
                    placeholder: 'Ask me anything...'
                },
                onError: function(error) {
                    console.error('Floating chat error:', error);
                }
            });
        }

        // Hide floating demo
        function hideFloatingDemo() {
            if (floatingChatBot) {
                floatingChatBot.hide();
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Check configuration
            checkConfiguration();
            
            // Initialize inline demo
            initInlineDemo();
            
            // Add event listeners for demo controls
            document.getElementById('inline-height').addEventListener('change', updateInlineDemo);
            document.getElementById('inline-theme').addEventListener('change', updateInlineDemo);
            document.getElementById('floating-position').addEventListener('change', function() {
                if (floatingChatBot) {
                    showFloatingDemo();
                }
            });
            document.getElementById('floating-theme').addEventListener('change', function() {
                if (floatingChatBot) {
                    showFloatingDemo();
                }
            });
        });
    </script>
</body>
</html>
"""
}

# Write all files to individual files for download
for filename, content in project_structure.items():
    with open(filename, 'w', encoding='utf-8') as f:
        f.write(content)

print("✅ GPT Chatbot Boilerplate files created successfully!")
print("\nFiles created:")
for filename in sorted(project_structure.keys()):
    print(f"  📄 {filename}")
    
print(f"\nTotal files: {len(project_structure)}")