# Documentation Index

Welcome to the GPT Chatbot Boilerplate documentation! This directory contains comprehensive guides for developers, operators, and users.

## 📚 Quick Start

- **New to the project?** Start with the [main README](../README.md)
- **Want to get started quickly?** See [QUICK_START.md](QUICK_START.md) for installation guide
- **Curious about features?** Check [FEATURES.md](FEATURES.md) for complete feature list
- **Want to deploy?** See [deployment.md](deployment.md) or use the [Installation Wizard](INSTALLATION_WIZARD.md)
- **Need API reference?** Check [api.md](api.md)
- **Want to customize?** Read [customization-guide.md](customization-guide.md)

## 📖 Core Documentation

### Getting Started
- [QUICK_START.md](QUICK_START.md) - **NEW:** Get up and running in 5 minutes (all installation methods)
- [FEATURES.md](FEATURES.md) - **NEW:** Complete feature overview with descriptions
- [deployment.md](deployment.md) - Complete deployment guide (Docker, cloud, production)
- [INSTALLATION_WIZARD.md](INSTALLATION_WIZARD.md) - Web-based installation wizard guide
- [api.md](api.md) - Complete API reference (HTTP, WebSocket, JavaScript)
- [customization-guide.md](customization-guide.md) - UI customization, configuration, and extension

### Implementation Phases
- [PHASE1_DB_AGENT.md](PHASE1_DB_AGENT.md) - Database layer & Agent model
- [PHASE2_ADMIN_UI.md](PHASE2_ADMIN_UI.md) - Visual Admin interface
- [PHASE3_WORKERS_WEBHOOKS.md](PHASE3_WORKERS_WEBHOOKS.md) - Background workers, webhooks & RBAC
- [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) - Complete implementation plan (all phases)
- [IMPLEMENTATION_REPORT.md](IMPLEMENTATION_REPORT.md) - Implementation report & production readiness assessment

### Architecture & Security
- [SECURITY_MODEL.md](SECURITY_MODEL.md) - Security architecture and best practices
- [RESOURCE_AUTHORIZATION.md](RESOURCE_AUTHORIZATION.md) - Per-resource access control & tenant isolation
- [MULTI_TENANCY.md](MULTI_TENANCY.md) - Multi-tenant architecture guide
- [HYBRID_GUARDRAILS.md](HYBRID_GUARDRAILS.md) - Response format & JSON schema guardrails
- [AUDIT_TRAILS.md](AUDIT_TRAILS.md) - Comprehensive audit logging & compliance tracking

### Observability & Operations
- [OBSERVABILITY.md](OBSERVABILITY.md) - Monitoring, metrics, and distributed tracing
- [OPERATIONS_GUIDE.md](OPERATIONS_GUIDE.md) - Daily operations, maintenance, and troubleshooting
- [ops/](ops/) - Detailed operational procedures (see [Ops Documentation](#ops-documentation) below)

## 🚀 Advanced Features

### Agent Management
- [GUIA_CRIACAO_AGENTES.md](GUIA_CRIACAO_AGENTES.md) - 🇧🇷 Complete agent creation guide (Portuguese, with screenshots)
- [PHASE1_DB_AGENT.md](PHASE1_DB_AGENT.md) - Agent model and Admin API reference
- [customization-guide.md#agent-based-configuration](customization-guide.md#agent-based-configuration) - Agent configuration guide

### WhatsApp Integration
- [WHATSAPP_INTEGRATION.md](WHATSAPP_INTEGRATION.md) - Multi-channel WhatsApp integration via Z-API
- [WHATSAPP_CONSENT_API.md](WHATSAPP_CONSENT_API.md) - Consent management API for GDPR/LGPD compliance
- [WHATSAPP_ONBOARDING_PLAYBOOK.md](WHATSAPP_ONBOARDING_PLAYBOOK.md) - Complete onboarding procedures
- [templates/WHATSAPP_DPA_TEMPLATE.md](templates/WHATSAPP_DPA_TEMPLATE.md) - Data Processing Agreement template

### LeadSense (AI Lead Detection & CRM)
- [LEADSENSE_QUICKSTART.md](LEADSENSE_QUICKSTART.md) - 5-minute setup guide
- [leadsense-overview.md](leadsense-overview.md) - Feature overview and architecture
- [leadsense-api.md](leadsense-api.md) - Complete API reference
- [LEADSENSE_CRM.md](LEADSENSE_CRM.md) - CRM extension with visual pipelines
- [leadsense-privacy.md](leadsense-privacy.md) - Privacy controls and PII handling

### WordPress Blog Automation
- [WORDPRESS_BLOG_SETUP.md](WORDPRESS_BLOG_SETUP.md) - Setup and configuration guide
- [WORDPRESS_BLOG_API.md](WORDPRESS_BLOG_API.md) - Complete API reference (24 endpoints)
- [WORDPRESS_BLOG_OPERATIONS.md](WORDPRESS_BLOG_OPERATIONS.md) - Daily operations and monitoring
- [WORDPRESS_BLOG_IMPLEMENTATION.md](WORDPRESS_BLOG_IMPLEMENTATION.md) - Technical implementation details
- [WORDPRESS_BLOG_RELEASE_CHECKLIST.md](WORDPRESS_BLOG_RELEASE_CHECKLIST.md) - Pre-deployment checklist
- [specs/WORDPRESS_BLOG_AUTOMATION_PRO_AGENTE_SPEC.md](specs/WORDPRESS_BLOG_AUTOMATION_PRO_AGENTE_SPEC.md) - Complete technical specification

### Billing & Metering
- [BILLING_METERING.md](BILLING_METERING.md) - Usage tracking, quotas, and billing system
- [MULTI_TENANT_BILLING.md](MULTI_TENANT_BILLING.md) - Per-tenant billing and usage aggregation

### Compliance & Privacy
- [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md) - GDPR/LGPD compliance procedures
- [COMPLIANCE_API.md](COMPLIANCE_API.md) - Data export, deletion, and consent APIs
- [COMPLIANCE_IMPLEMENTATION_STATUS.md](COMPLIANCE_IMPLEMENTATION_STATUS.md) - Implementation status and quick start

### Whitelabel Publishing
- [WHITELABEL_API.md](WHITELABEL_API.md) - Public API reference for whitelabel agents
- [WHITELABEL_PUBLISHING.md](WHITELABEL_PUBLISHING.md) - Publishing agents as standalone chatbots

### Prompt Builder
- [prompt_builder_overview.md](prompt_builder_overview.md) - AI-powered prompt generation
- [prompt_builder_api.md](prompt_builder_api.md) - Prompt Builder API reference
- [prompt_builder_guardrails.md](prompt_builder_guardrails.md) - Guardrail templates and examples

### Multi-Tenant Features
- [MULTI_TENANT_OBSERVABILITY.md](MULTI_TENANT_OBSERVABILITY.md) - Per-tenant monitoring and metrics
- [TENANT_RATE_LIMITING.md](TENANT_RATE_LIMITING.md) - Tenant-specific rate limiting

## 🔧 Ops Documentation

Located in [ops/](ops/):

### Production Operations
- [ops/production-deploy.md](ops/production-deploy.md) - Step-by-step production deployment
- [ops/backup_restore.md](ops/backup_restore.md) - Backup automation and restore procedures
- [ops/disaster_recovery.md](ops/disaster_recovery.md) - Disaster recovery runbook with RPO/RTO
- [ops/incident_runbook.md](ops/incident_runbook.md) - Incident response procedures

### Configuration & Security
- [ops/nginx-production.conf](ops/nginx-production.conf) - Production-ready Nginx configuration
- [ops/secrets_management.md](ops/secrets_management.md) - Token rotation and secrets management
- [ops/logs.md](ops/logs.md) - Structured logging and log aggregation (ELK, CloudWatch, LogDNA)

### Monitoring
- [ops/monitoring/](ops/monitoring/) - Prometheus alert rules and monitoring configuration

### Multi-Tenant Operations
- [ops/multi_tenant_backup_dr.md](ops/multi_tenant_backup_dr.md) - Multi-tenant backup and DR procedures

## 📋 Documentation by Use Case

### I want to...
- **Get started quickly** → [QUICK_START.md](QUICK_START.md) - **START HERE**
- **See all features** → [FEATURES.md](FEATURES.md)
- **Deploy the chatbot** → [deployment.md](deployment.md)
- **Create and manage agents** → [GUIA_CRIACAO_AGENTES.md](GUIA_CRIACAO_AGENTES.md) or [PHASE1_DB_AGENT.md](PHASE1_DB_AGENT.md)
- **Integrate with WhatsApp** → [WHATSAPP_INTEGRATION.md](WHATSAPP_INTEGRATION.md)
- **Customize the UI** → [customization-guide.md](customization-guide.md)
- **Set up monitoring** → [OBSERVABILITY.md](OBSERVABILITY.md)
- **Implement multi-tenancy** → [MULTI_TENANCY.md](MULTI_TENANCY.md)
- **Handle GDPR/LGPD compliance** → [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md)
- **Track leads automatically** → [LEADSENSE_QUICKSTART.md](LEADSENSE_QUICKSTART.md)
- **Automate WordPress blog content** → [WORDPRESS_BLOG_SETUP.md](WORDPRESS_BLOG_SETUP.md)
- **Bill customers** → [BILLING_METERING.md](BILLING_METERING.md)
- **Publish whitelabel agents** → [WHITELABEL_PUBLISHING.md](WHITELABEL_PUBLISHING.md)
- **Troubleshoot issues** → [ops/incident_runbook.md](ops/incident_runbook.md)
- **Backup and restore** → [ops/backup_restore.md](ops/backup_restore.md)

## 🌍 Language-Specific Documentation

- 🇧🇷 **Portuguese**: [GUIA_CRIACAO_AGENTES.md](GUIA_CRIACAO_AGENTES.md) - Complete agent creation and publishing guide

## 📸 Visual Assets

The [images/](images/) directory contains screenshots used in documentation:
- Admin UI interfaces
- Agent creation workflows
- Configuration examples

## 🔗 External Resources

- **Main Project README**: [../README.md](../README.md)
- **GitHub Repository**: https://github.com/suporterfid/gpt-chatbot-boilerplate
- **OpenAI API Documentation**: https://platform.openai.com/docs

## 📝 Contributing to Documentation

When updating documentation:
1. Keep information accurate and current
2. Include code examples where applicable
3. Update this index if adding new documents
4. Cross-reference related documentation
5. Test all command examples before committing

## 📅 Documentation Maintenance

- **Last Major Update**: November 2025
- **Review Cycle**: Quarterly
- **Owners**: Development team & Operations team

---

For questions or suggestions about documentation, please open an issue on GitHub.
