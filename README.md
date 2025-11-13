# GPT Chatbot Boilerplate

An open-source, production-ready boilerplate for embedding GPT-powered chatbots on any website. Features dual API support (Chat Completions + Responses API), real-time streaming, agent management, multi-tenancy, and comprehensive admin tools.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://www.php.net/)
[![Tests](https://img.shields.io/badge/tests-183%20passing-green)](tests/)

## ✨ Key Features

- **🤖 Dual API Support**: Switch between Chat Completions and Responses API with one toggle
- **🎯 Agent Management**: Create and manage multiple AI agents with different configurations
- **📎 File Upload**: Support for PDFs, documents, images with OpenAI file processing
- **🎨 Admin UI**: Complete visual interface for managing agents, prompts, and vector stores
- **🏢 Multi-Tenancy**: Full tenant isolation with per-tenant billing and resource control
- **🔐 Security**: RBAC, API keys, rate limiting, and comprehensive audit trails
- **📊 Observability**: Prometheus metrics, structured logging, distributed tracing
- **📱 WhatsApp Integration**: Connect agents to WhatsApp Business for omnichannel support
- **🚀 Production Ready**: CI/CD, backup/restore, load testing, and operational runbooks

👉 **[See all features](docs/FEATURES.md)** for a complete overview.

## 🚀 Quick Start

Get up and running in 5 minutes:

### 1. Clone the Repository

```bash
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate
```

### 2. Web-Based Installation (Recommended)

```bash
# Start with Docker
docker-compose up -d

# Or use PHP built-in server
php -S localhost:8000
```

Open in browser: `http://localhost:8088/setup/install.php`

The wizard will guide you through:
- ✅ System requirements verification
- ⚙️ OpenAI API configuration
- 🗄️ Database setup (SQLite or MySQL)
- 🔐 Admin credentials
- 🎯 Optional features

### 3. Manual Installation

```bash
# Copy environment file
cp .env.example .env

# Edit .env with your settings
nano .env

# Required settings:
# OPENAI_API_KEY=your_key_here
# ADMIN_ENABLED=true
# DEFAULT_ADMIN_EMAIL=super.admin@example.com
# DEFAULT_ADMIN_PASSWORD=generate_a_secure_password

# Start services
docker-compose up -d
```

### 4. Access Your Chatbot

- **Chatbot**: `http://localhost:8088/`
- **Admin Panel**: `http://localhost:8088/public/admin/`

👉 **[Full Quick Start Guide](docs/QUICK_START.md)** for all installation methods and configuration options.

### 🔑 Super-Admin Login & Credential Rotation

- **Bootstrap credentials:** Set `ADMIN_ENABLED=true` along with `DEFAULT_ADMIN_EMAIL` and `DEFAULT_ADMIN_PASSWORD` in `.env` for the first run only. The install wizard writes these automatically for you.
- **Sign in:** Visit `http://localhost:8088/public/admin/` and enter the super-admin email/password. The platform issues an encrypted session cookie.
- **Create durable API keys:** From the Admin UI (or `POST /admin-api.php?action=generate_api_key`), mint user-specific API keys for automation. Store the plain-text key securely—it's shown only once.
- **Rotate credentials:**
  - To rotate passwords, create a new super-admin via `POST /admin-api.php?action=create_user` (role `super-admin`), sign in with the new user, then deactivate the previous account using `POST /admin-api.php?action=deactivate_user`.
  - To rotate API keys, generate a replacement key, update dependent services, and call `POST /admin-api.php?action=revoke_api_key&id={key_id}` to retire the old key.
- **Clean up:** Remove `DEFAULT_ADMIN_EMAIL` and `DEFAULT_ADMIN_PASSWORD` from `.env` once a permanent account exists. Legacy `ADMIN_TOKEN` headers still function but are strictly deprecated—migrate any remaining clients to session login or per-user API keys.

## 💻 Basic Integration

Add the chatbot to your website in seconds:

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

For advanced integration examples including Responses API, file uploads, and custom callbacks, see:
- **[Customization Guide](docs/customization-guide.md)** - UI styling, configuration, and extension
- **[API Reference](docs/api.md)** - Complete API documentation

## ⚙️ Configuration

Configure the chatbot via environment variables in `.env`:

```bash
# API Selection
API_TYPE=responses              # 'chat' or 'responses'

# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini

# Admin Features (optional)
ADMIN_ENABLED=true
DEFAULT_ADMIN_EMAIL=super.admin@example.com
DEFAULT_ADMIN_PASSWORD=change_me_securely

# Database (SQLite by default)
DATABASE_PATH=./data/chatbot.db
# Or MySQL:
# DATABASE_URL=mysql://user:password@localhost/chatbot_db

# File Upload (optional)
ENABLE_FILE_UPLOAD=true
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=txt,pdf,doc,docx,jpg,png
```

For complete configuration options and agent-based configuration, see:
- **[Customization Guide](docs/customization-guide.md)** - All configuration options
- **[Deployment Guide](docs/deployment.md)** - Production configuration

## 🏗️ Architecture

The boilerplate follows a modular architecture:

- **chat-unified.php**: Unified endpoint for both Chat Completions and Responses APIs
- **chatbot-enhanced.js**: Feature-rich JavaScript widget with streaming support
- **admin-api.php**: RESTful API for agent/prompt/vector store management
- **includes/**: Core services (ChatHandler, OpenAIClient, AgentService, etc.)
- **public/admin/**: Single-page application for visual administration
- **scripts/**: CLI tools for workers, backups, and maintenance
- **db/migrations/**: Database schema migrations
- **tests/**: Comprehensive test suite (183 tests)

For detailed architecture documentation, see:
- **[Customization Guide](docs/customization-guide.md)** - Extending the platform
- **[API Reference](docs/api.md)** - Endpoint specifications
- **[Implementation Report](docs/IMPLEMENTATION_REPORT.md)** - Complete implementation details

## 🔐 Security

Built-in security features:
- ✅ **RBAC**: Role-based access control (viewer, admin, super-admin)
- ✅ **API Keys**: Per-user authentication with expiration
- ✅ **Rate Limiting**: IP-based and tenant-specific throttling
- ✅ **File Validation**: Type, size, and content checking
- ✅ **Audit Trails**: Complete operation logging
- ✅ **Encryption**: AES-256-GCM for sensitive data

See [SECURITY_MODEL.md](docs/SECURITY_MODEL.md) for complete security documentation.

## 🚀 Deployment

### Production Checklist

- ✅ Use MySQL/PostgreSQL (not SQLite) for production
- ✅ Configure automated backups with `scripts/db_backup.sh`
- ✅ Set up monitoring with Prometheus/Grafana
- ✅ Enable HTTPS with production Nginx config
- ✅ Run background worker for async jobs
- ✅ Configure rate limiting and security headers
- ✅ Set up log aggregation (ELK, CloudWatch, etc.)
- ✅ Test with load testing scripts (`tests/load/`)

### Quick Deploy

```bash
# Docker deployment
docker-compose -f docker-compose.prod.yml up -d

# Or with Kubernetes/Helm
helm install chatbot ./helm/chatbot
```

For detailed deployment instructions, see:
- **[Deployment Guide](docs/deployment.md)** - Complete deployment documentation
- **[Operations Guide](docs/OPERATIONS_GUIDE.md)** - Daily operations and maintenance
- **[Backup & Restore](docs/ops/backup_restore.md)** - Backup automation
- **[Disaster Recovery](docs/ops/disaster_recovery.md)** - DR procedures

## 🧪 Testing

Comprehensive test suite with 183 tests:

```bash
# Run all tests
php tests/run_tests.php

# Run smoke tests (production readiness)
bash scripts/smoke_test.sh

# Static analysis
composer run analyze

# Frontend linting
npm run lint

# Load testing
k6 run tests/load/chat_api.js
```

All tests run automatically via GitHub Actions on every PR.

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new features
4. Ensure all tests pass
5. Submit a Pull Request

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

## 📚 Documentation

Comprehensive documentation organized by topic:

### Getting Started
- 📖 [Quick Start Guide](docs/QUICK_START.md) - Get up and running quickly
- ✨ [Features Overview](docs/FEATURES.md) - Complete feature list
- 🎨 [Customization Guide](docs/customization-guide.md) - UI and configuration
- 🚀 [Deployment Guide](docs/deployment.md) - Production deployment

### API & Development
- 📖 [API Reference](docs/api.md) - Complete endpoint documentation
- 🔧 [Phase 1: Agents & Database](docs/PHASE1_DB_AGENT.md)
- 🎨 [Phase 2: Admin UI](docs/PHASE2_ADMIN_UI.md)
- 🔐 [Phase 3: Workers & RBAC](docs/PHASE3_WORKERS_WEBHOOKS.md)

### Operations & Security
- 🔧 [Operations Guide](docs/OPERATIONS_GUIDE.md) - Daily operations
- 🔐 [Security Model](docs/SECURITY_MODEL.md) - Security architecture
- 📊 [Observability](docs/OBSERVABILITY.md) - Monitoring and metrics
- 💾 [Backup & Restore](docs/ops/backup_restore.md) - Data protection

### Advanced Features
- 🏢 [Multi-Tenancy](docs/MULTI_TENANCY.md) - Tenant isolation
- 📱 [WhatsApp Integration](docs/WHATSAPP_INTEGRATION.md) - Multi-channel support
- 🛡️ [Hybrid Guardrails](docs/HYBRID_GUARDRAILS.md) - Structured outputs
- 🇧🇷 [Agent Guide (Portuguese)](docs/GUIA_CRIACAO_AGENTES.md)

👉 **[Full Documentation Index](docs/README.md)** - Complete list of all documentation

## 📊 Monitoring

Built-in observability with Prometheus, Grafana, and structured logging:

```bash
# Start monitoring stack
cd observability/docker
docker-compose up -d

# Access dashboards
# Grafana: http://localhost:3000
# Prometheus: http://localhost:9090
```

**Included Metrics:**
- API performance (latency, errors, throughput)
- OpenAI API usage and costs
- Agent performance and usage
- Job queue health
- System resources

See [OBSERVABILITY.md](docs/OBSERVABILITY.md) for complete monitoring documentation.

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
