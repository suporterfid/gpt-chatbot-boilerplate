# Quick Start Guide

This guide provides step-by-step instructions for getting started with the GPT Chatbot Boilerplate using different installation methods.

## Table of Contents

- [Requirements](#requirements)
- [Installation Methods](#installation-methods)
- [Basic Configuration](#basic-configuration)
- [Testing Your Installation](#testing-your-installation)

## Requirements

- PHP 8.0+ with cURL, JSON, and PDO extensions
- Apache or Nginx web server
- OpenAI API key
- Composer (for dependency management)
- Database: SQLite (included) or MySQL 8.0+
- Optional: Docker for containerized deployment
- Optional: Node.js and npm (for frontend development and linting)

## Installation Methods

### Method 1: Web-Based Installation (Recommended for First-Time Users)

The easiest way to get started is using our web-based installation wizard:

1. Clone and start the application:
```bash
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate

# Start with Docker (includes MySQL)
docker-compose up -d

# Or start with PHP built-in server
php -S localhost:8000
```

2. Open the installation wizard in your browser:
```
http://localhost:8088/setup/install.php
# or http://localhost:8000/setup/install.php (if using PHP built-in server)
```

3. Follow the step-by-step wizard to:
   - Verify system requirements
   - Configure OpenAI API settings
   - Choose database type (SQLite or MySQL)
   - Set up admin credentials
   - Enable optional features
   - Initialize the database

4. After installation, access:
   - **Admin Panel**: `http://localhost:8088/public/admin/`
   - **Chatbot**: `http://localhost:8088/`

The installation wizard will generate a `.env` file with all your settings and create a `.install.lock` file to prevent accidental re-installation.

### Method 2: Chat Completions API (Simple)

For basic chat functionality without advanced features:

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
OPENAI_MODEL=gpt-4o-mini
```

3. Start with Docker:
```bash
docker-compose up -d
```

4. Access the chatbot at `http://localhost:8088/`

### Method 3: Responses API (Advanced)

For advanced features including prompt templates, tool calling, and file attachments:

1. Configure for Responses API in `.env`:
```bash
API_TYPE=responses
OPENAI_API_KEY=your_openai_api_key_here
RESPONSES_MODEL=gpt-4o-mini
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

3. Start the application:
```bash
docker-compose up -d
```

### Method 4: MySQL Database Deployment

For production environments or when you need a robust database:

1. Configure MySQL in `.env`:
```bash
# Database Configuration
DATABASE_URL=mysql:host=mysql;port=3306;dbname=chatbot;charset=utf8mb4
DB_HOST=mysql
DB_PORT=3306
DB_NAME=chatbot
DB_USER=chatbot
DB_PASSWORD=your_secure_password
MYSQL_ROOT_PASSWORD=your_root_password

# Leave DATABASE_PATH empty when using MySQL
DATABASE_PATH=
```

2. Start with Docker (includes MySQL service):
```bash
docker-compose up -d
```

The docker-compose.yml includes:
- **MySQL 8.0** service with persistent storage
- Automatic database initialization
- Health checks for both services
- Volume mounting for data persistence

3. Access MySQL directly (optional):
```bash
# Connect to MySQL container
docker-compose exec mysql mysql -u chatbot -p

# Or from host (if port 3306 is exposed)
mysql -h 127.0.0.1 -P 3306 -u chatbot -p chatbot
```

## Basic Configuration

### Environment Variables

The `.env` file controls all major configuration options. Key settings include:

```bash
# API Selection
API_TYPE=responses              # 'chat' or 'responses'

# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_BASE_URL=https://api.openai.com/v1

# Admin Features
ADMIN_ENABLED=true
ADMIN_TOKEN=generate_a_secure_random_token_min_32_chars

# Database
DATABASE_PATH=./data/chatbot.db  # SQLite
# OR
DATABASE_URL=mysql://user:password@localhost/chatbot_db  # MySQL

# File Upload
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png

# Storage
STORAGE_TYPE=file
STORAGE_PATH=/var/chatbot/data
```

### Widget Integration

Add the chatbot to your website:

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

## Testing Your Installation

### Quick API Testing

Test the Chat Completions API:
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "api_type": "chat"}' \
  http://localhost:8088/chat-unified.php
```

Test the Responses API:
```bash
curl -X POST -H "Content-Type: application/json" \
  -d '{"message": "Hello", "api_type": "responses"}' \
  http://localhost:8088/chat-unified.php
```

### Accessing the Admin Panel

1. Navigate to `http://localhost:8088/public/admin/`
2. Enter your admin token (from `.env`)
3. Create and test agents, prompts, and vector stores

### Health Check

Verify system health:
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "http://localhost:8088/admin-api.php?action=health"
```

## Next Steps

After completing the quick start:

- **Learn about agents**: See [GUIA_CRIACAO_AGENTES.md](GUIA_CRIACAO_AGENTES.md) or [PHASE1_DB_AGENT.md](PHASE1_DB_AGENT.md)
- **Customize the UI**: Read [customization-guide.md](customization-guide.md)
- **Deploy to production**: Follow [deployment.md](deployment.md)
- **Set up monitoring**: Configure [observability](OBSERVABILITY.md)
- **Explore advanced features**: Check [FEATURES.md](FEATURES.md)

## Troubleshooting

### Common Issues

**Database connection errors:**
- Verify database credentials in `.env`
- Check that database service is running
- Ensure migrations have run (automatic on first request)

**OpenAI API errors:**
- Verify your API key is correct
- Check your OpenAI account has available credits
- Ensure API_TYPE matches your configuration

**File upload issues:**
- Check file size limits in `.env`
- Verify file types are in ALLOWED_FILE_TYPES
- Ensure upload directory has write permissions

For more troubleshooting help, see [ops/incident_runbook.md](ops/incident_runbook.md).

## Support

- 📖 [Full Documentation](README.md)
- 🐛 [Report Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- 💬 [Discussions](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)
