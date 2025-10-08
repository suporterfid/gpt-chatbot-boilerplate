# GPT Chatbot Web Integration Boilerplate

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
