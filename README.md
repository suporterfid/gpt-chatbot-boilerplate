# Enhanced GPT Chatbot Web Integration Boilerplate

An advanced open-source boilerplate for embedding GPT-powered chatbots on any website with **dual API support** (Chat Completions + Assistants API), real-time streaming, white-label customization, and easy deployment.

## 🚀 New Features in v2.0

### 🔥 **Dual API Support**
- **Chat Completions API**: Traditional stateless chat interface
- **Assistants API**: Advanced features with persistent threads, tools, and file processing
- **Easy switching** between APIs via configuration
- **Automatic fallback** and error handling

### 🛠️ **Assistants API Features**
- **Persistent Conversations**: Threads managed server-side
- **File Upload & Processing**: Support for documents, images, and more
- **Built-in Tools**: Code Interpreter, File Search
- **Custom Functions**: Define your own function calling
- **Advanced Reasoning**: Enhanced AI capabilities

### 📎 **File Upload Support**
- **Multiple file types**: PDF, DOC, images, text files
- **File size validation** and type restrictions
- **Visual file preview** before sending
- **Drag & drop interface** (coming soon)

### 🎯 **Enhanced UI/UX**
- **API type indicators** showing which API is active
- **Tool execution visualization** for Assistants API
- **File attachment display** in messages
- **Improved responsive design**

## 📋 Requirements

- PHP 8.0+ with cURL extension
- Apache or Nginx web server
- OpenAI API key
- Optional: Docker for containerized deployment

## 🚀 Quick Start

### Option 1: Chat Completions API (Simple)

1. Clone and configure:
```bash
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate
cp .env.example .env
```

2. Edit `.env` for Chat Completions:
```bash
API_TYPE=chat
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-3.5-turbo
```

3. Start with Docker:
```bash
docker-compose up -d
```

### Option 2: Assistants API (Advanced)

1. Configure for Assistants API in `.env`:
```bash
API_TYPE=assistants
OPENAI_API_KEY=your_openai_api_key_here

# Option A: Use existing assistant
ASSISTANT_ID=asst_your_assistant_id_here

# Option B: Auto-create assistant
CREATE_ASSISTANT=true
ASSISTANT_NAME=My Website Assistant
ASSISTANT_INSTRUCTIONS=You are a helpful assistant for website visitors
ASSISTANT_CODE_INTERPRETER=true
ASSISTANT_FILE_SEARCH=true
```

2. Enable file uploads (optional):
```bash
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png
```

3. Start the application:
```bash
docker-compose up -d
```

## 💻 Integration Examples

### Basic Chat Completions Integration

```html
<script src="chatbot-enhanced.js"></script>
<script>
ChatBot.init({
    mode: 'floating',
    apiType: 'chat',
    apiEndpoint: '/chat-unified.php',
    title: 'Support Chat',
    assistant: {
        name: 'ChatBot',
        welcomeMessage: 'Hi! How can I help you today?'
    }
});
</script>
```

### Advanced Assistants API Integration

```html
<script src="chatbot-enhanced.js"></script>
<script>
ChatBot.init({
    mode: 'inline',
    apiType: 'assistants',
    apiEndpoint: '/chat-unified.php',
    enableFileUpload: true,

    assistant: {
        name: 'AI Assistant',
        welcomeMessage: 'Hello! I can help with questions, analyze documents, and run code. What would you like to do?'
    },

    assistantConfig: {
        enableTools: true,
        enableCodeInterpreter: true,
        enableFileSearch: true
    },

    // Callbacks for advanced features
    onToolCall: function(toolData) {
        console.log('Tool executed:', toolData);
    },

    onFileUpload: function(files) {
        console.log('Files uploaded:', files);
    }
});
</script>
```

### File Upload Configuration

```javascript
ChatBot.init({
    enableFileUpload: true,
    maxFileSize: 10485760, // 10MB
    allowedFileTypes: ['txt', 'pdf', 'doc', 'docx', 'jpg', 'png'],

    assistant: {
        welcomeMessage: 'Hi! You can ask questions or upload files for analysis.',
        processingFile: 'Processing your file...'
    }
});
```

## 🔧 Configuration

### Dual API Configuration

The enhanced version supports both APIs through a single configuration:

```php
// config.php
return [
    'api_type' => 'assistants', // 'chat' or 'assistants'

    // Chat Completions settings
    'chat' => [
        'model' => 'gpt-3.5-turbo',
        'temperature' => 0.7,
        'system_message' => 'You are a helpful assistant.'
    ],

    // Assistants API settings
    'assistants' => [
        'assistant_id' => 'asst_your_id',
        'create_assistant' => false,
        'tools' => ['code_interpreter', 'file_search'],
        'custom_functions' => ['get_weather', 'search_knowledge']
    ]
];
```

### File Upload Configuration

```php
'chat_config' => [
    'enable_file_upload' => true,
    'max_file_size' => 10485760, // 10MB
    'allowed_file_types' => ['txt', 'pdf', 'doc', 'docx', 'jpg', 'png']
]
```

## 🏗️ Enhanced Architecture

### File Structure

```
├── chat-unified.php          # Unified endpoint for both APIs
├── chatbot-enhanced.js       # Enhanced JavaScript client
├── includes/
│   ├── ChatHandler.php       # Unified chat handler
│   ├── OpenAIClient.php      # Enhanced OpenAI client
│   ├── AssistantManager.php  # Assistant management
│   └── ThreadManager.php     # Thread management
├── config.php               # Enhanced configuration
└── .env.example            # Updated environment template
```

### API Flow Comparison

#### Chat Completions API Flow
1. User sends message
2. Add to conversation history
3. Stream response from OpenAI
4. Save to session/storage

#### Assistants API Flow  
1. User sends message (+ files)
2. Create/get thread
3. Add message to thread
4. Create run with streaming
5. Handle tool calls if needed
6. Update thread mapping

## 🎨 Enhanced UI Features

### API Type Indicators
- Visual indicators showing which API is active
- Different styling for Chat vs Assistants modes
- Run ID tracking for Assistants API

### File Upload Interface
- Drag & drop file selection
- File preview with size and type
- Upload progress indicators
- File attachment display in messages

### Tool Execution Visualization
- Real-time tool execution indicators
- Function call parameter display  
- Tool result integration

## 🔐 Security Enhancements

- **File upload validation**: Type, size, and content checking
- **Thread isolation**: Secure thread-to-conversation mapping
- **Function call sandboxing**: Safe custom function execution
- **Enhanced rate limiting**: Per-API-type limits

## 📚 API Reference

### Enhanced JavaScript API

#### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiType` | string | `'chat'` | API to use: `'chat'` or `'assistants'` |
| `enableFileUpload` | boolean | `false` | Enable file upload functionality |
| `assistantConfig` | object | `{}` | Assistants API specific settings |

#### New Callbacks

```javascript
ChatBot.init({
    onToolCall: function(toolData) {
        // Handle tool execution
    },
    onFileUpload: function(files) {
        // Handle file upload
    },
    onThreadCreate: function(threadId) {
        // Handle thread creation (Assistants API)
    }
});
```

### Enhanced PHP Endpoints

#### POST /chat-unified.php

Unified endpoint supporting both APIs.

**Request:**
```json
{
    "message": "Hello",
    "conversation_id": "conv_123",
    "api_type": "assistants",
    "file_data": [
        {
            "name": "document.pdf",
            "type": "application/pdf",
            "data": "base64_encoded_data"
        }
    ]
}
```

**SSE Response Events:**
- `start`: Chat/run started
- `run_created`: Assistant run created (Assistants API)
- `chunk`: Content chunk
- `tool_call`: Function call execution (Assistants API)
- `done`: Chat/run completed
- `error`: Error occurred

## 🚀 Deployment

### Environment Variables

```bash
# API Selection
API_TYPE=assistants

# Assistants API
ASSISTANT_ID=asst_your_assistant_id
CREATE_ASSISTANT=false
ASSISTANT_TOOLS=code_interpreter,file_search
THREAD_CLEANUP_HOURS=24

# File Upload
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png

# Storage
STORAGE_TYPE=file
STORAGE_PATH=/var/chatbot/data
```

### Production Considerations

- **File storage**: Configure appropriate storage for uploaded files
- **Thread cleanup**: Set up cron job for old thread cleanup
- **Resource limits**: Monitor API usage and costs
- **Backup strategy**: Backup thread mappings and conversation data

## 🔄 Migration from v1.0

### Updating Existing Installations

1. **Backup existing data**:
```bash
cp -r . ../chatbot-backup
```

2. **Update files**:
```bash
git pull origin main
```

3. **Update configuration**:
```bash
# Add new environment variables to .env
echo "API_TYPE=chat" >> .env
```

4. **Test both APIs**:
```bash
# Test Chat Completions
curl -X POST -H "Content-Type: application/json"   -d '{"message": "Hello", "api_type": "chat"}'   http://localhost:8080/chat-unified.php

# Test Assistants API (if configured)
curl -X POST -H "Content-Type: application/json"   -d '{"message": "Hello", "api_type": "assistants"}'   http://localhost:8080/chat-unified.php
```

### JavaScript Client Updates

Use `chatbot-enhanced.js` (see `default.php` demo):

```html
<!-- Include enhanced widget -->
<script src="chatbot-enhanced.js"></script>

<script>
// Configuration remains compatible
ChatBot.init({
    // Existing options work as before
    // New options available for enhanced features
    apiType: 'assistants',
    apiEndpoint: '/chat-unified.php',
    enableFileUpload: true  // New option
});
</script>
```

## 🧪 Testing

### API Testing

```bash
# Test Chat Completions API
./test-chat-api.sh

# Test Assistants API  
./test-assistants-api.sh

# Test file upload
./test-file-upload.sh
```

### Load Testing

```bash
# Test with multiple concurrent users
ab -n 1000 -c 50 -p test-message.json   -T application/json   http://localhost:8080/chat-unified.php
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

```bash
# Install development dependencies
composer install --dev

# Run tests
./vendor/bin/phpunit

# Start development server
docker-compose -f docker-compose.dev.yml up
```

## 📝 License

MIT License - feel free to use this in commercial and personal projects.

## 📞 Support

- 📖 [Documentation](docs/)
- 🐛 [Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)  
- 💬 [Discussions](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)
- 📧 [Email Support](mailto:support@example.com)

---

**⭐ If this project helps you, please give it a star!**

Made with ❤️ by the open source community
