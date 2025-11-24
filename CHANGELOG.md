# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-01-24

### 🚀 Major Feature Release

This release introduces enterprise-grade content automation with WordPress Blog integration, enhanced agent capabilities with specialized agent types, and comprehensive documentation improvements. The platform now includes 190+ API endpoints (up from 40 in v1.0.0), representing a 375% growth in functionality.

### Added

#### WordPress Blog Automation (24 new endpoints)
- **Complete blog automation system** with AI-powered content generation
  - Multi-chapter article generation with SEO optimization
  - Automated image generation using DALL-E 3 (featured and inline images)
  - Internal link management for SEO
  - Queue-based publishing with scheduling and retry logic
  - Category and tag management with automatic assignment
  - Performance metrics and health monitoring
  - Error tracking and debugging tools
- **REST API Endpoints** for full blog workflow automation
  - Configuration Management: 5 endpoints (create, update, list, get, delete)
  - Article Queue: 6 endpoints (add, list, update status, retry, bulk operations)
  - Internal Links: 4 endpoints (generate, list, update, validate)
  - Categories: 3 endpoints (fetch, sync, map to topics)
  - Tags: 3 endpoints (fetch, sync, map to keywords)
  - Monitoring: 3 endpoints (health check, metrics, process)
- **Database Schema** - Migration #048: `add_wordpress_blog_tables.sql`
  - 5 new tables: configs, articles, queue, internal_links, metrics
- **Backend Implementation**
  - 11 PHP service classes in `includes/WordPressBlog/`
  - Background processor: `scripts/wordpress_blog_processor.php`
  - Service classes: ConfigurationService, ChapterContentWriter, ImageGenerator, Publisher, QueueService, AssetOrganizer, ContentStructureBuilder, ExecutionLogger, ErrorAnalyzer, HealthChecker, MetricsCollector
- **Admin UI Modules**
  - WordPress Blog Configuration Manager (`public/admin/wordpress-blog-config.js`)
  - WordPress Blog Queue Manager (`public/admin/wordpress-blog-queue.js`)
  - WordPress Blog Metrics Dashboard (`public/admin/wordpress-blog-metrics.js`)
  - Styling: `public/admin/wordpress-blog.css`
- **Documentation** (6 comprehensive guides)
  - [WORDPRESS_BLOG_SETUP.md](docs/WORDPRESS_BLOG_SETUP.md) - Complete setup guide
  - [WORDPRESS_BLOG_API.md](docs/WORDPRESS_BLOG_API.md) - Full API reference with examples
  - [WORDPRESS_BLOG_OPERATIONS.md](docs/WORDPRESS_BLOG_OPERATIONS.md) - Daily operations guide
  - [WORDPRESS_BLOG_IMPLEMENTATION.md](docs/WORDPRESS_BLOG_IMPLEMENTATION.md) - Technical architecture
  - [WORDPRESS_BLOG_RELEASE_CHECKLIST.md](docs/WORDPRESS_BLOG_RELEASE_CHECKLIST.md) - Deployment checklist
  - [specs/WORDPRESS_BLOG_AUTOMATION_PRO_AGENTE_SPEC.md](docs/specs/WORDPRESS_BLOG_AUTOMATION_PRO_AGENTE_SPEC.md) - Complete specification

#### Specialized Agent Types (7 new endpoints)
- **Agent type discovery and management system**
  - Type-specific capabilities and configuration
  - Agent filtering by type and capability
  - Default agent management per type
  - Metadata storage for type-specific information
- **REST API Endpoints**
  - `list_agent_types` - List all available agent types with capabilities
  - `get_agent_type` - Get specific agent type details
  - `list_agents_by_type` - Filter agents by type
  - `get_specialized_config` - Get type-specific configuration
  - `update_specialized_config` - Update type-specific configuration
  - `get_default_agent` - Get default agent for a type
  - `set_default_agent` - Set default agent for a type
- **Database Schema** - Migration #047: `add_specialized_agent_support.sql`
  - Extended `agents` table with `agent_type` column (default: 'generic')
  - New table: `specialized_agent_configs` for type-specific settings
  - New table: `agent_type_metadata` for type information
- **Documentation**
  - [specs/SPECIALIZED_AGENTS_SPECIFICATION.md](docs/specs/SPECIALIZED_AGENTS_SPECIFICATION.md) - Complete specification
  - [BACKWARD_COMPATIBILITY.md](docs/BACKWARD_COMPATIBILITY.md) - Compatibility report

#### Documentation Overhaul
- **Comprehensive API documentation** - All 190+ endpoints now documented with examples
  - Added WordPress Blog Management section to [docs/admin-api.md](docs/admin-api.md) (865 lines)
  - Added Agent Types & Discovery section to [docs/admin-api.md](docs/admin-api.md) (319 lines)
  - Updated [docs/api.md](docs/api.md) with accurate endpoint count (190+) and architecture diagram
  - Updated [docs/client-api.md](docs/client-api.md) to v1.0.1
  - Updated [docs/public-api.md](docs/public-api.md) to v1.0.1
- **Feature documentation updates**
  - Added WordPress Blog Automation to [README.md](README.md) key features
  - Added WordPress Blog section to [docs/README.md](docs/README.md) with 6 doc links
  - Updated [docs/FEATURES.md](docs/FEATURES.md) with WordPress Blog in Enterprise tier
  - Updated [docs/PROJECT_DESCRIPTION.md](docs/PROJECT_DESCRIPTION.md) with Content Marketing use case
  - Added WordPress Blog to [docs/OPERATIONS_GUIDE.md](docs/OPERATIONS_GUIDE.md) cross-references
  - Updated [docs/GUIA_CRIACAO_AGENTES.md](docs/GUIA_CRIACAO_AGENTES.md) (Portuguese) with accurate endpoint count
- **Historical documentation**
  - Added "Post-Implementation Features" section to [docs/IMPLEMENTATION_REPORT.md](docs/IMPLEMENTATION_REPORT.md)
  - Documented features added after v2.1 (WordPress Blog, enhanced LeadSense, Multi-Tenancy, Agent Types)
- **Architecture diagrams**
  - Updated architecture diagram in [docs/api.md](docs/api.md) to show WordPress Blog, LeadSense, Multi-Tenancy
- **Version standardization**
  - Updated all API documentation headers to v1.0.1
  - Updated [README.md](README.md) version badge to v1.0.1
  - Updated [docs/BACKWARD_COMPATIBILITY.md](docs/BACKWARD_COMPATIBILITY.md) to v1.0.1

#### Database Migrations
- **Migration #048**: WordPress Blog tables (5 tables for complete workflow)
- **Migration #047**: Specialized agent support (agent_type column, metadata tables)
- **Migration #046**: Added `slug` column to agents table for URL-friendly identifiers
- **Migration #045**: Seeded default LeadSense pipeline
- **Migration #044**: Relaxed lead events constraint for flexibility

### Changed

#### API Expansion
- **Endpoint count**: 40 → 190+ endpoints (+375% growth)
- **Admin API**: Now comprehensively covers all platform features
- **Documentation accuracy**: Corrected endpoint count from 37-90+ to accurate 190+

#### Admin UI Enhancements
- Added WordPress Blog configuration interface
- Added WordPress Blog queue management interface
- Added WordPress Blog metrics dashboard
- Enhanced agent management with type indicators
- Improved visual hierarchy and navigation

#### Code Organization
- Added `includes/WordPressBlog/` namespace with 11 service classes
- Improved separation of concerns in Admin API
- Enhanced error handling and logging
- Better type safety with enhanced type hints

### Documentation

#### New Files (15+ files)
- WordPress Blog documentation suite (6 comprehensive guides)
- Specialized agent specification
- Release notes for v1.0.1
- Updated CHANGELOG.md

#### Updated Files (10+ files)
- All core API documentation (admin-api.md, api.md, client-api.md, public-api.md)
- README.md and docs/README.md
- Feature documentation and comparison matrices
- Architecture diagrams and implementation reports
- Operations guides and cross-references

### Fixed

- Corrected endpoint count documentation (was 37-90+, now accurately 190+)
- Fixed missing API documentation for 31 endpoints (WordPress Blog: 24, Agent Types: 7)
- Improved discoverability of WordPress Blog feature across documentation
- Enhanced cross-referencing between related documentation

### Security

**No security vulnerabilities or breaking changes** in this release.

- ✅ All changes are fully backward compatible
- ✅ No changes to existing endpoint signatures
- ✅ WordPress Blog feature is opt-in
- ✅ Proper encryption and credential handling in WordPress Blog configs
- ✅ Maintained all existing security controls (RBAC, rate limiting, audit trails)

### Migration

**100% Backward Compatible** - No breaking changes.

#### Upgrade Steps:
1. Backup database: `./scripts/db_backup.sh`
2. Pull latest code: `git pull origin main`
3. Run migrations: `php db/run_migration.php` (migrations 044-048)
4. Restart services: `docker-compose restart`

#### Migration Safety:
- ✅ Existing agents automatically get `agent_type = 'generic'`
- ✅ New tables are independent and don't affect existing functionality
- ✅ No data migration required
- ✅ Can upgrade without downtime

### Platform Statistics

| Metric | v1.0.0 | v1.0.1 | Growth |
|--------|--------|--------|--------|
| **Total API Endpoints** | ~40 | 190+ | +375% |
| **Documentation Files** | ~30 | 180+ | +500% |
| **Database Tables** | ~35 | ~45 | +29% |
| **PHP Service Classes** | ~25 | ~40 | +60% |
| **Admin UI Modules** | ~15 | ~20 | +33% |
| **Code Size (Lines)** | ~19,250 | ~25,000+ | +30% |

### Notes

**WordPress Blog Feature:**
- Requires WordPress 5.0+ with REST API enabled
- Requires WordPress Application Password for API authentication
- Requires OpenAI API key for content and image generation
- See [WORDPRESS_BLOG_SETUP.md](docs/WORDPRESS_BLOG_SETUP.md) for complete setup instructions

**Specialized Agent Types:**
- Fully backward compatible with existing generic agents
- New specialized types include: `wordpress_blog`, `customer_support`, `lead_qualifier`, etc.
- See [specs/SPECIALIZED_AGENTS_SPECIFICATION.md](docs/specs/SPECIALIZED_AGENTS_SPECIFICATION.md) for details

**Complete Release Notes:**
- See [RELEASE_NOTES_v1.0.1.md](RELEASE_NOTES_v1.0.1.md) for comprehensive release documentation

---

## [1.0.0] - 2025-01-19

### 🎉 Production Release

This is the first production-ready release of GPT Chatbot Boilerplate, featuring comprehensive security hardening, production deployment configurations, and extensive documentation.

### Added

#### Production Infrastructure
- **Environment-aware configuration** - New `APP_ENV` variable to distinguish development from production
  - Automatic error reporting configuration based on environment
  - Production mode hides sensitive error details (file paths, database schema, stack traces)
  - Development mode shows full error details for debugging
- **Production Docker configuration** - New `docker-compose.prod.yml` with production-grade settings
  - Required environment variable validation
  - Security hardening (no-new-privileges, resource limits)
  - Background worker service for async job processing
  - Proper health checks and monitoring
  - Localhost-only port binding (requires reverse proxy)
- **Production environment template** - New `.env.production` file with secure defaults
  - Production-safe configurations
  - Comprehensive security warnings
  - Secret generation commands
  - Clear separation of required vs optional variables

#### Security Enhancements
- **Error message sanitization** - Production-safe error handling in database layer
  - Generic error messages for users in production
  - Detailed errors logged server-side for debugging
  - Prevents information disclosure through error messages
- **Enhanced `.gitignore`** - Comprehensive exclusions to prevent credential exposure
  - All `.env*` variants excluded
  - Logs, secrets, certificates, and backup files protected
  - Upload directories excluded
- **Security warnings in configurations** - Critical warnings added throughout
  - Docker Compose files marked as development-only
  - Database credential warnings
  - CORS configuration reminders
  - Bootstrap credential lifecycle documentation

#### Documentation
- **Production Security Checklist** - Comprehensive 100+ point checklist ([PRODUCTION_SECURITY_CHECKLIST.md](PRODUCTION_SECURITY_CHECKLIST.md))
  - Pre-deployment critical tasks
  - Post-deployment verification
  - Regular maintenance schedules
  - Emergency response procedures
  - Compliance notes (GDPR, CCPA, HIPAA)
- **Production Release Notes** - Complete deployment guide ([PRODUCTION_RELEASE_NOTES.md](PRODUCTION_RELEASE_NOTES.md))
  - All changes summarized
  - Quick start deployment guide (5 minutes)
  - Migration guide from development
  - Security testing procedures
  - Monitoring recommendations
- **Changelog** - This file, following Keep a Changelog format
- **Release Notes** - Version-specific release documentation

#### Configuration Improvements
- **Enhanced `.env.example`** with production warnings
  - CORS security warnings and examples
  - Secret generation commands (openssl)
  - Database security recommendations
  - Webhook security configuration
  - Credential rotation procedures
- **Production-appropriate rate limits**
  - Chat: 30 requests/minute (down from 60)
  - Admin: 100 requests/minute (down from 300)
  - Configurable per environment

### Changed

#### Breaking Changes
**None** - All changes are backward compatible. Existing development environments continue to work without modifications.

#### Configuration
- **CORS default documentation** - Added warnings to change `CORS_ORIGINS=*` to specific domains in production
- **Database recommendations** - SQLite discouraged for production, managed database services recommended
- **Docker Compose** - Development file now includes prominent security warnings
- **Error reporting** - Now configured automatically based on `APP_ENV` instead of separate settings

#### Error Handling
- Database errors now environment-aware
  - Production: Generic error messages
  - Development: Detailed error information
- Error messages sanitized to prevent sensitive data exposure
  - File paths redacted
  - Database schema details hidden
  - SQL queries not exposed to users

### Security

#### Critical Security Fixes
- **Production error disclosure** - Fixed potential information leakage through verbose error messages
- **Credential protection** - Enhanced gitignore and documentation to prevent accidental credential exposure
- **Environment separation** - Clear distinction between development and production configurations

#### Security Enhancements
- **CORS enforcement** - Better documentation and warnings for production CORS configuration
- **Secret management** - Comprehensive documentation for generating and rotating secrets
- **Database security** - Strong password requirements and SSL/TLS recommendations
- **File upload security** - Documented secure upload directory configuration

### Infrastructure

#### Docker
- **Production configuration** (`docker-compose.prod.yml`)
  - Security hardening with `no-new-privileges`
  - Resource limits (CPU: 2 cores, Memory: 2GB)
  - Proper logging (JSON format, size limits)
  - Health checks for all services
  - Internal networking for database
- **Development warnings** - Clear markers in `docker-compose.yml` indicating development-only use

#### Database
- **Production-safe error messages** - DB class now respects `APP_ENV` setting
- **Managed database recommendations** - Documentation updated to recommend AWS RDS, Google Cloud SQL, etc.
- **Connection security** - SSL/TLS configuration documented

### Documentation Updates

#### New Documentation
- [PRODUCTION_SECURITY_CHECKLIST.md](PRODUCTION_SECURITY_CHECKLIST.md) - Comprehensive security checklist
- [PRODUCTION_RELEASE_NOTES.md](PRODUCTION_RELEASE_NOTES.md) - Deployment guide and migration instructions
- [.env.production](.env.production) - Production environment template
- [CHANGELOG.md](CHANGELOG.md) - This changelog

#### Updated Documentation
- [.env.example](.env.example) - Added security warnings and generation commands
- [.gitignore](.gitignore) - Enhanced to protect sensitive files
- [docker-compose.yml](docker-compose.yml) - Added development-only warnings
- [config.php](config.php) - Added APP_ENV support and auto error configuration
- [includes/DB.php](includes/DB.php) - Production-safe error handling

### Deployment

#### Quick Start (Development)
```bash
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate
cp .env.example .env
# Edit .env with your OpenAI API key
docker-compose up -d
```

#### Quick Start (Production)
```bash
# 1. Copy production template
cp .env.production .env

# 2. Generate secrets
openssl rand -base64 32  # For AUDIT_ENC_KEY
openssl rand -hex 32     # For WEBHOOK_GATEWAY_SECRET

# 3. Configure .env with production values
# 4. Deploy with production configuration
docker-compose -f docker-compose.prod.yml up -d

# 5. Follow PRODUCTION_SECURITY_CHECKLIST.md
```

### Migration Guide

#### From Development to Production

1. **Set environment to production:**
   ```bash
   echo "APP_ENV=production" >> .env
   echo "DEBUG=false" >> .env
   ```

2. **Update CORS to specific domains:**
   ```bash
   # Change from:
   CORS_ORIGINS=*
   # To:
   CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
   ```

3. **Generate and set encryption keys:**
   ```bash
   echo "AUDIT_ENC_KEY=$(openssl rand -base64 32)" >> .env
   echo "WEBHOOK_GATEWAY_SECRET=$(openssl rand -hex 32)" >> .env
   ```

4. **Configure managed database:**
   - Set up AWS RDS, Google Cloud SQL, or similar
   - Update `DATABASE_URL` with connection string
   - Use strong passwords (min 20 characters)

5. **Deploy with production configuration:**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

6. **Complete security checklist:**
   - Follow [PRODUCTION_SECURITY_CHECKLIST.md](PRODUCTION_SECURITY_CHECKLIST.md)
   - Verify all critical items
   - Run security tests

### Testing

#### Automated Tests
- All existing 183 tests pass
- No breaking changes introduced
- Backward compatibility maintained

#### Security Testing
```bash
# Test error disclosure prevention
curl https://yourdomain.com/chat-unified.php -d '{"invalid":"data"}'
# Should NOT expose PHP errors or stack traces

# Test CORS restrictions
curl -H "Origin: https://unauthorized.com" \
     -X OPTIONS https://yourdomain.com/chat-unified.php
# Should reject unauthorized origins

# Test rate limiting
# Should enforce configured limits
```

### Known Issues

None. This release is production-ready.

### Upgrade Instructions

#### From Beta/RC Versions

1. **Backup your data:**
   ```bash
   ./scripts/db_backup.sh
   ```

2. **Pull latest changes:**
   ```bash
   git pull origin main
   ```

3. **Update environment:**
   ```bash
   # Review .env.example for new variables
   # Add APP_ENV=production to your .env
   ```

4. **Restart services:**
   ```bash
   docker-compose -f docker-compose.prod.yml down
   docker-compose -f docker-compose.prod.yml up -d
   ```

5. **Verify deployment:**
   - Check all services are running
   - Verify chatbot responds correctly
   - Review logs for errors
   - Run smoke tests

### Contributors

This release includes contributions from the open source community. Special thanks to all who reported issues, suggested features, and contributed code.

### Support

- 📖 [Documentation](docs/)
- 🐛 [Issue Tracker](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- 💬 [Discussions](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)
- 📧 [Email Support](mailto:support@example.com)

---

## [0.9.0] - 2025-01-10 (Pre-release)

### Added
- Agent management with public access via slugs
- WhatsApp integration via Z-API
- LeadSense CRM for lead detection and scoring
- Multi-tenancy support with tenant isolation
- RBAC and resource-level authorization
- Audit trails with encryption
- Billing and metering integration (Asaas)
- Observability with Prometheus and Grafana
- Background job queue
- Webhook system for event processing
- Prompt Builder with AI-powered guardrails
- Compliance features (GDPR, CCPA)

### Changed
- Migrated to Composer for dependency management
- Enhanced security model
- Improved error handling
- Updated documentation

### Fixed
- Various bug fixes and performance improvements

---

## Version History

- **1.0.0** (2025-01-19) - Production Release
- **0.9.0** (2025-01-10) - Pre-release Beta
- **0.1.0** (2024-12-01) - Initial Development Release

---

**Note**: For detailed information about each release, see [PRODUCTION_RELEASE_NOTES.md](PRODUCTION_RELEASE_NOTES.md) and individual feature documentation in the [docs/](docs/) directory.
