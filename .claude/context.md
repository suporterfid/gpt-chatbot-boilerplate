# GPT Chatbot Boilerplate - Project Context

## Project Overview

**GPT Chatbot Boilerplate** is a production-ready, enterprise-grade SaaS platform for deploying GPT-powered chatbots. This is a comprehensive multi-tenant application with advanced CRM, multi-channel support, and complete operational features.

- **Repository**: https://github.com/suporterfid/gpt-chatbot-boilerplate
- **License**: MIT
- **Language**: PHP 8.0+
- **Test Coverage**: 183+ passing tests
- **Status**: Production-ready, actively maintained

## Technology Stack

### Backend
- **PHP 8.0+** (Object-Oriented Architecture)
- **Database**: SQLite (default), MySQL/PostgreSQL support
- **Web Server**: Apache or Nginx
- **Dependencies**: Composer (Ratchet for WebSockets)

### Frontend
- **Vanilla JavaScript** (ES6+, no frameworks)
- **Real-time**: Server-Sent Events (SSE), WebSocket support
- **Admin UI**: Single Page Application (SPA)

### DevOps & Monitoring
- **Containerization**: Docker & Docker Compose
- **Orchestration**: Kubernetes/Helm charts
- **IaC**: Terraform (AWS)
- **Monitoring**: Prometheus + Grafana + Loki
- **CI/CD**: GitHub Actions
- **Load Testing**: K6

## Key Features

### Core Chatbot
1. **Dual OpenAI API Support**: Chat Completions + Responses API
2. **Real-Time Communication**: SSE streaming, WebSocket, AJAX fallback
3. **AI Agent Management**: Multiple agents with custom configs
4. **File Handling**: Multi-file upload (PDF, DOC, images)
5. **Vector Stores**: Knowledge base integration

### Enterprise Features
1. **Multi-Tenancy**: Complete SaaS isolation and billing
2. **LeadSense CRM**: AI-powered lead detection and pipeline management
3. **Multi-Channel**: Web + WhatsApp Business integration
4. **Security**: RBAC, API keys, audit logging, encryption
5. **Billing**: Usage tracking, quota enforcement, payment integration
6. **Observability**: Metrics, tracing, structured logging
7. **Admin UI**: Visual configuration, testing workspace

## Project Structure

```
.
├── Root Files                   # Main endpoints
│   ├── chat-unified.php         # Unified chat API
│   ├── admin-api.php            # RESTful admin API (201KB)
│   ├── chatbot-enhanced.js      # Frontend widget (122KB)
│   └── config.php               # Configuration loader
│
├── includes/                    # Backend Services (~60 classes)
│   ├── ChatHandler.php          # Core orchestration (86KB)
│   ├── AgentService.php         # Agent management (37KB)
│   ├── AdminAuth.php            # RBAC & auth (24KB)
│   ├── LeadSense/               # CRM services
│   ├── PromptBuilder/           # AI prompt generation
│   └── channels/                # Multi-channel adapters
│
├── public/admin/                # Admin SPA
│   ├── admin.js                 # Admin logic (237KB)
│   ├── agent-workspace.js       # Agent testing (67KB)
│   ├── leadsense-crm.html/js    # CRM Kanban board
│   └── prompt-builder.js        # Prompt generation UI
│
├── db/migrations/               # 46 database migrations
├── scripts/                     # Operational tools
├── tests/                       # 183+ tests
├── docs/                        # 85+ MD files
├── observability/               # Monitoring stack
├── helm/                        # Kubernetes deployment
└── terraform/                   # AWS infrastructure
```

## Architecture Patterns

- **Layered Architecture**: Presentation → API → Service → Data
- **Service-Oriented**: Independent, reusable services
- **Repository Pattern**: Data access abstraction
- **Strategy Pattern**: Pluggable channel adapters
- **Observer Pattern**: Event-driven webhooks
- **Factory Pattern**: Service creation
- **Middleware Pattern**: Request/response wrapping
- **Command Pattern**: Background job queue

## Key Configuration Files

1. **config.php**: Central configuration hub (540 lines)
2. **.env.example**: Environment variable reference (228 lines)
3. **docker-compose.yml**: Container orchestration (153 lines)
4. **composer.json**: PHP dependencies and scripts
5. **observability/**: Prometheus/Grafana configurations
6. **helm/chatbot/**: Kubernetes deployment charts

## Important Directories

- **includes/**: Backend PHP services and classes
- **public/admin/**: Frontend admin interface
- **db/migrations/**: Database schema evolution
- **scripts/**: Operational and maintenance scripts
- **tests/**: Unit and integration tests
- **docs/**: Comprehensive documentation
- **observability/**: Monitoring and alerting
- **channels/**: Multi-channel integrations

## Development Workflow

### Running Tests
```bash
composer test                    # Run all tests
composer test:unit              # Unit tests only
composer test:integration       # Integration tests
```

### Code Quality
```bash
composer analyse                # PHPStan static analysis
composer lint                   # Code style check
composer lint:fix               # Auto-fix style issues
```

### Database Migrations
```bash
php scripts/run_migrations.php  # Run pending migrations
```

### Background Worker
```bash
php scripts/worker.php          # Start job queue worker
```

### Monitoring
```bash
cd observability/docker && docker-compose up  # Start monitoring stack
```

## Common Tasks

### Adding a New Agent
1. Use Admin UI: `/public/admin/index.html`
2. Or via API: `POST /admin-api.php?action=create_agent`
3. Configure: prompts, model, tools, vector stores

### Adding a New Channel
1. Create adapter in `includes/channels/` implementing `ChannelInterface`
2. Register in `ChannelManager.php`
3. Add webhook endpoint in `channels/`
4. Configure in `.env`

### Adding a New Service
1. Create class in `includes/`
2. Follow single responsibility principle
3. Add dependency injection via constructor
4. Write tests in `tests/`

### Database Changes
1. Create migration in `db/migrations/`
2. Follow naming: `XXX_description.sql`
3. Test with `run_migrations.php`

## Security Considerations

- All API calls require authentication (API key or session)
- RBAC enforced at service level
- Input validation on all endpoints
- SQL injection prevention (prepared statements)
- XSS prevention (output escaping)
- CSRF protection for admin UI
- Audit logging for all operations
- PII encryption with AES-256-GCM
- Webhook signature verification

## API Endpoints

### Chat API
- `POST /chat-unified.php` - Unified chat endpoint
- Supports: SSE streaming, WebSocket, AJAX

### Admin API
- `GET /admin-api.php?action=get_agents` - List agents
- `POST /admin-api.php?action=create_agent` - Create agent
- `PUT /admin-api.php?action=update_agent` - Update agent
- `DELETE /admin-api.php?action=delete_agent` - Delete agent
- See `docs/leadsense-api.md` for complete API reference

### Metrics
- `GET /metrics.php` - Prometheus metrics endpoint

## Environment Variables

Key variables to configure:

- `OPENAI_API_KEY` - OpenAI API authentication
- `DATABASE_TYPE` - sqlite|mysql|pgsql
- `ENABLE_MULTI_TENANCY` - true|false
- `ENABLE_LEADSENSE` - true|false
- `ENABLE_WHATSAPP` - true|false
- `WEBHOOK_SECRET` - For signature verification
- `ADMIN_AUTH_ENABLED` - true|false

See `.env.example` for complete list.

## Recent Changes

Latest commits (November 18, 2024):
- Added agent `slug` field support
- Neuron AI integration specifications
- Documentation updates
- Multi-tenant improvements

## Documentation

Comprehensive documentation in `docs/`:

- **README.md** - Quick start guide
- **PROJECT_DESCRIPTION.md** - Architecture overview
- **MULTI_TENANCY.md** - Multi-tenant architecture
- **WHATSAPP_INTEGRATION.md** - Channel integration
- **SECURITY_MODEL.md** - Security architecture
- **OBSERVABILITY.md** - Monitoring setup
- **LEADSENSE_QUICKSTART.md** - CRM guide
- **OPERATIONS_GUIDE.md** - Daily operations
- **ops/** - Operational runbooks

## Testing

Test suite includes:
- Unit tests for all services
- Integration tests for API endpoints
- Load tests with K6
- Smoke tests for production readiness

Run with: `composer test`

## Deployment

### Docker (Recommended)
```bash
docker-compose up -d
```

### Kubernetes
```bash
helm install chatbot ./helm/chatbot
```

### AWS (Terraform)
```bash
cd terraform/aws
terraform init
terraform apply
```

## Troubleshooting

Common issues and solutions in `docs/`:
- Installation issues: `setup/README.md`
- Database issues: `docs/ops/disaster_recovery.md`
- Performance issues: `docs/OBSERVABILITY.md`
- Security issues: `docs/ops/incident_runbook.md`

## Contributing

1. Follow PHP-FIG PSR-12 coding standards
2. Write tests for new features
3. Update documentation
4. Run code quality checks before committing
5. Follow semantic versioning

## Support

- GitHub Issues: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues
- Documentation: `docs/` directory
- Examples: See test files and documentation

## License

MIT License - See LICENSE file for details
