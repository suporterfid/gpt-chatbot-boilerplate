# Enhanced GPT Chatbot Web Integration Boilerplate

An advanced open-source boilerplate for embedding GPT-powered chatbots on any website with **dual API support** (Chat Completions + Responses API), real-time streaming, white-label customization, and easy deployment.

## 🚀 New Features in v2.1

### 🔥 **Dual API Support**
- **Chat Completions API**: Traditional stateless chat interface.
- **Responses API**: Prompt-template aware streaming with tool calling and file attachments.
- **One toggle** via configuration to switch between APIs.
- **Automatic fallback** and robust error handling.

### 🧠 **Responses API Enhancements**
- **Prompt Templates**: Reference saved prompts via `RESPONSES_PROMPT_ID` and optional versioning.
- **Inline + referenced prompts**: Mix local context with reusable system instructions.
- **Tool Calls**: Stream tool call deltas and auto-submit outputs when enabled.
- **File Attachments**: Upload user files directly with the appropriate `user_data` purpose.

### 📎 **File Upload Support**
- Multiple file types (PDF, DOC, images, text files).
- File size validation and type restrictions.
- Visual file preview before sending.

### 🎯 **Enhanced UI/UX**
- API type indicators showing the active workflow.
- Tool execution visualization for Responses API events.
- File attachment display in messages.
- Improved responsive design.

### 🗄️ **Phase 1: Database Layer & Agent Model**
- **Agent Management**: Persistent storage for AI agent configurations.
- **Admin API**: Token-protected REST API for CRUD operations on agents.
- **Dynamic Configuration**: Override prompts, tools, models, and parameters per agent.
- **Default Agent**: Set a default agent for requests without explicit `agent_id`.
- **SQLite Support**: Zero-config database with optional MySQL compatibility.
- See [docs/PHASE1_DB_AGENT.md](docs/PHASE1_DB_AGENT.md) for details.

### 🎨 **Phase 2: Admin UI, Prompts & Vector Store Management (NEW)**
- **Visual Admin Interface**: Comprehensive web UI for managing all resources.
- **Prompt Management**: Create, version, and sync OpenAI prompts.
- **Vector Store Management**: Upload files, manage stores, monitor ingestion.
- **Agent Testing**: Test agents with streaming responses directly from Admin UI.
- **Health Monitoring**: Real-time system health and API connectivity checks.
- **Audit Logging**: Complete audit trail of all admin operations.
- See [docs/PHASE2_ADMIN_UI.md](docs/PHASE2_ADMIN_UI.md) for details.

## 📋 Requirements

- PHP 8.0+ with cURL extension.
- Apache or Nginx web server.
- OpenAI API key.
- Optional: Docker for containerized deployment.

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

### Option 2: Responses API (Advanced)

1. Configure for Responses API in `.env`:
```bash
API_TYPE=responses
OPENAI_API_KEY=your_openai_api_key_here
RESPONSES_MODEL=gpt-4.1-mini
RESPONSES_PROMPT_ID=pmpt_your_prompt_id   # optional, reference a saved prompt
RESPONSES_PROMPT_VERSION=1                # optional, defaults to latest
RESPONSES_TEMPERATURE=0.7
RESPONSES_MAX_OUTPUT_TOKENS=1024
# Tools & file search defaults (JSON or comma-separated values)
RESPONSES_TOOLS=[{"type":"file_search"}]      # JSON array or comma-separated tool types
RESPONSES_VECTOR_STORE_IDS=vs_1234567890,vs_0987654321
RESPONSES_MAX_NUM_RESULTS=20
```

2. Enable file uploads (optional):
```bash
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png
```

### Option 3: Admin API & Agent Management (Phase 1)

1. Enable admin features in `.env`:
```bash
# Enable admin API
ADMIN_ENABLED=true
ADMIN_TOKEN=generate_a_secure_random_token_min_32_chars

# Database configuration (SQLite by default)
DATABASE_PATH=./data/chatbot.db
# Or use MySQL:
# DATABASE_URL=mysql://user:password@localhost/chatbot_db
```

2. Run migrations (automatic on first request, or manually):
```bash
# Migrations run automatically when admin-api.php or chat-unified.php is accessed
# To verify:
php -r "require 'includes/DB.php'; \$db = new DB(['database_path' => './data/chatbot.db']); echo \$db->runMigrations('./db/migrations') . ' migrations executed';"
```

3. Create your first agent via Admin API:
```bash
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support",
    "api_type": "responses",
    "prompt_id": "pmpt_abc123",
    "model": "gpt-4o",
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_knowledge_base"],
    "is_default": true
  }'
```

4. Use the agent in chat requests:
```bash
curl -X POST "http://localhost/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What is your return policy?",
    "conversation_id": "conv_123",
    "agent_id": "agent-uuid-from-create-response",
    "stream": false
  }'
```

For complete Admin API documentation, see [docs/PHASE1_DB_AGENT.md](docs/PHASE1_DB_AGENT.md).

### Option 4: Admin UI for Visual Management (Phase 2)

1. Complete Phase 1 setup (database and admin token).

2. Access the Admin UI:
```
http://localhost/public/admin/
```

3. Enter your admin token when prompted (stored in localStorage).

4. Create and manage resources visually:
   - **Agents**: Full CRUD with test capability
   - **Prompts**: Create, version, sync from OpenAI
   - **Vector Stores**: Upload files, monitor ingestion
   - **Settings**: Health checks and system status

5. Test an agent:
   - Navigate to Agents page
   - Click "Test" button on any agent
   - Enter a test message
   - View streaming response in real-time

For complete Admin UI documentation, see [docs/PHASE2_ADMIN_UI.md](docs/PHASE2_ADMIN_UI.md).


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

### Advanced Responses API Integration

```html
<script src="chatbot-enhanced.js"></script>
<script>
ChatBot.init({
    mode: 'inline',
    apiType: 'responses',
    apiEndpoint: '/chat-unified.php',
    enableFileUpload: true,

    assistant: {
        name: 'AI Guide',
        welcomeMessage: 'Hello! I can help with questions, analyze documents, and trigger tools.'
    },

    responsesConfig: {
        promptId: 'pmpt_your_prompt_id',
        promptVersion: '1',
        defaultTools: [
            { type: 'file_search' }
        ],
        defaultVectorStoreIds: ['vs_1234567890'],
        defaultMaxNumResults: 20
    },

    onToolCall: function(toolData) {
        console.log('Tool executed:', toolData);
    },

    onFileUpload: function(files) {
        console.log('Files uploaded:', files);
    }
});
</script>
```

> **Tip:** Any values supplied in `responsesConfig` are forwarded with each `/chat-unified.php` request using snake_case keys. For example, `defaultVectorStoreIds` automatically becomes `default_vector_store_ids` so the PHP endpoint can merge them with server defaults from `RESPONSES_VECTOR_STORE_IDS`.

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
    'api_type' => 'responses', // 'chat' or 'responses'

    // Chat Completions settings
    'chat' => [
        'model' => 'gpt-3.5-turbo',
        'temperature' => 0.7,
        'system_message' => 'You are a helpful assistant.'
    ],

    // Responses API settings
    'responses' => [
        'model' => 'gpt-4.1-mini',
        'temperature' => 0.7,
        'max_output_tokens' => 1024,
        'prompt_id' => 'pmpt_your_prompt_id',
        'prompt_version' => '1',
        'default_tools' => [
            ['type' => 'file_search']
        ],
        'default_vector_store_ids' => ['vs_1234567890'],
        'default_max_num_results' => 20
    ]
];
```

The `RESPONSES_TOOLS`, `RESPONSES_VECTOR_STORE_IDS`, and `RESPONSES_MAX_NUM_RESULTS` environment variables hydrate these defaults automatically. Provide JSON arrays (e.g., `[{"type":"file_search"}]`) or comma-separated lists (`vs_123,vs_456`) and the backend merges them with any request-level overrides (config → request → final payload).

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
│   ├── ChatHandler.php       # Unified chat handler for chat & responses
│   └── OpenAIClient.php      # OpenAI client with streaming helpers
├── config.php                # Unified configuration loader
└── .env.example              # Environment template
```

### API Flow Comparison

#### Chat Completions API Flow
1. User sends message.
2. Add to conversation history.
3. Stream response from OpenAI.
4. Save to session/storage.

#### Responses API Flow
1. User sends message (+ optional files).
2. Build payload from local history + optional prompt reference.
3. Stream `/responses` events (text deltas, tool calls, completions).
4. Auto-submit tool outputs when required.
5. Persist conversation locally for context reuse.

## 🎨 Enhanced UI Features

### API Type Indicators
- Visual indicators showing the active API.
- Distinct styling cues for Chat vs Responses modes.
- Response ID tracking for saved prompt runs.

### File Upload Interface
- Drag & drop file selection.
- File preview with size and type.
- Upload progress indicators.
- File attachment display in messages.

### Tool Execution Visualization
- Real-time tool execution indicators.
- Function call parameter display.
- Tool result integration in the stream.

## 🔐 Security Enhancements

- **File upload validation**: Type, size, and content checking.
- **Conversation isolation**: Local session/file storage per conversation.
- **Function call sandboxing**: Safe custom function execution examples.
- **Enhanced rate limiting**: Request throttling per client.

## 📚 API Reference

### Enhanced JavaScript API

#### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiType` | string | `'responses'` | API to use: `'chat'` or `'responses'` |
| `enableFileUpload` | boolean | `false` | Enable file upload functionality |
| `responsesConfig` | object | `{}` | Responses API defaults forwarded as snake_case (e.g. `defaultVectorStoreIds` → `default_vector_store_ids`) |

#### Callbacks

```javascript
ChatBot.init({
    onToolCall: function(toolData) {
        // Handle tool execution
    },
    onFileUpload: function(files) {
        // Handle file upload
    }
});
```

### Enhanced PHP Endpoint

#### POST /chat-unified.php

Unified endpoint supporting both APIs.

**Request:**
```json
{
    "message": "Hello",
    "conversation_id": "conv_123",
    "api_type": "responses",
    "prompt_id": "pmpt_your_prompt_id",
    "prompt_version": "1",
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
- `start`: Stream started (includes optional `response_id`).
- `chunk`: Content delta.
- `tool_call`: Tool call details and status updates.
- `done`: Conversation finished with status metadata.
- `error`: Error occurred.

## 🚀 Deployment

### Environment Variables

```bash
# API Selection
API_TYPE=responses

# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_BASE_URL=https://api.openai.com/v1

# Responses API
RESPONSES_MODEL=gpt-4.1-mini
RESPONSES_PROMPT_ID=pmpt_your_prompt_id
RESPONSES_PROMPT_VERSION=1
RESPONSES_MAX_OUTPUT_TOKENS=1024
RESPONSES_TEMPERATURE=0.7
# Tools & file search defaults (JSON or comma-separated values)
RESPONSES_TOOLS=[{"type":"file_search"}]
RESPONSES_VECTOR_STORE_IDS=vs_1234567890,vs_0987654321
RESPONSES_MAX_NUM_RESULTS=20

# File Upload
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png

# Storage
STORAGE_TYPE=file
STORAGE_PATH=/var/chatbot/data
```

### Production Considerations

- **File storage**: Configure appropriate storage for uploaded files.
- **Prompt management**: Version control saved prompts and fallback strategies.
- **Resource limits**: Monitor API usage and costs.
- **Backup strategy**: Backup conversation history if persisted to disk.

## 🔄 Migration from v2.0 (Assistants API)

1. **Update configuration**: Replace `API_TYPE=assistants` with `API_TYPE=responses` and remove assistant-specific env vars.
2. **Set prompt IDs**: If you previously relied on assistant instructions, save them as a prompt via `/v1/prompts` and set `RESPONSES_PROMPT_ID`.
3. **Deploy updated code**: This release removes `AssistantManager` and `ThreadManager`—ensure custom code no longer references them.
4. **Test both modes**: Verify chat completions and responses streaming with your prompt templates.

## 🧪 Testing

```bash
# Test Chat Completions API
curl -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "api_type": "chat"}' \
  http://localhost:8080/chat-unified.php

# Test Responses API
curl -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "api_type": "responses"}' \
  http://localhost:8080/chat-unified.php

# Test file upload
./test-file-upload.sh
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
