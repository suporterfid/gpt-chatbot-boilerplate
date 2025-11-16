# Changelog

All notable changes to the GPT Chatbot Boilerplate project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Whitelabel Enhancements (November 2025)

#### Added
- **Pretty URL support**: `get_whitelabel_url` now returns `pretty_url`, a `/chat/@{vanity_path}` short link generated whenever a vanity path is configured. The endpoint response and supporting fixtures/tests ensure the new field is always validated.

#### Documentation
- Updated `docs/WHITELABEL_API.md` and `docs/WHITELABEL_PUBLISHING.md` to describe the `/chat/@{vanity_path}` entry point, including request examples and field descriptions so teams know the short link depends on a configured slug.

### Web-Based Installation & MySQL Support (November 6, 2025)

#### Added
- **Web Installation Wizard**: User-friendly setup interface at `/setup/install.php`
  - Step-by-step configuration wizard (4 steps)
  - System requirements validation (PHP version, extensions, permissions)
  - Interactive configuration forms with collapsible sections
  - Automatic `.env` file generation
  - Database initialization and migration execution
  - Installation lock mechanism (`.install.lock`) to prevent re-installation
  - Beautiful gradient UI with responsive design
  - Support for both SQLite and MySQL configuration
  
- **MySQL Database Support**: Production-ready database option
  - Added MySQL service to `docker-compose.yml` with health checks
  - MySQL 8.0 image with persistent volume storage
  - PDO MySQL extension in Dockerfile
  - Comprehensive MySQL configuration in `.env.example`
  - Database connection string generation in installation wizard
  - Automatic database and user creation via Docker environment variables
  
- **Enhanced Documentation**
  - Created `docs/INSTALLATION_WIZARD.md` - Complete installation guide
  - Updated `docs/deployment.md` - MySQL deployment section with backup/restore procedures
  - Updated `docs/GUIA_CRIACAO_AGENTES.md` - Portuguese guide with installation wizard instructions
  - Added `setup/README.md` - Setup directory documentation
  - Updated `README.md` - Quick start with installation wizard (Option 0)
  
- **Docker Enhancements**
  - Added `depends_on` for MySQL service
  - Volume mounting for persistent data (`./data`)
  - MySQL environment variables in docker-compose
  - Network configuration for service communication
  - Health checks for both chatbot and MySQL services

#### Changed
- **README.md**: Reorganized quick start options, added web installation as Option 0
- **Dockerfile**: Now installs PDO and PDO MySQL extensions by default
- **docker-compose.yml**: Enhanced with MySQL service and proper dependency management
- **.env.example**: Added comprehensive MySQL configuration examples

#### Screenshots
- Installation Wizard Step 1: System requirements validation
- Installation Wizard Step 2: Configuration settings with collapsible sections

### Phase 4 Enhancements (November 4, 2025)

#### Added
- **Static Analysis**: PHPStan integration with level 5 analysis
  - Added `phpstan.neon` configuration file
  - Updated `composer.json` with PHPStan dev dependency
  - Enhanced CI workflow to run static analysis on every build
  - Added `composer run analyze` script for local development
- **Frontend Linting**: ESLint integration for JavaScript validation
  - Created `package.json` with ESLint dependency
  - Enhanced CI workflow with ESLint checks (non-blocking)
  - Automated JavaScript syntax and quality validation
- **Comprehensive Smoke Testing**: Production readiness verification
  - Created `scripts/smoke_test.sh` with 37 automated checks
  - Verifies file structure, documentation, code quality, migrations, features
  - Runs all 155 unit tests automatically
  - Exit code 0 indicates production-ready state
  - Added `scripts/README.md` documenting all operational scripts
- **Enhanced .gitignore**: Improved exclusion patterns
  - Added node_modules/, package-lock.json
  - Added build artifacts (dist/, build/)
  - Added IDE files (.vscode/, .idea/, *.swp)
  - Added OS files (.DS_Store, Thumbs.db)
- **Documentation**: Enhanced testing documentation
  - Created `PHASE4_ENHANCEMENTS_REPORT.md` with detailed enhancement summary
  - Updated `README.md` with comprehensive testing section
  - Documented static analysis, linting, and smoke test procedures

#### Changed
- CI/CD pipeline now includes PHPStan and ESLint in addition to existing PHP linting
- All tests (unit + smoke) total 192 checks with 100% pass rate

### Documentation Updates (November 2025)

#### Added
- **Implementation Report**: Created comprehensive `docs/IMPLEMENTATION_REPORT.md`
  - Executive summary with key achievements
  - Detailed phase-by-phase implementation (Phases 1-10)
  - Complete code metrics (~16,700 lines production code, 155 tests)
  - Feature completion matrix
  - Production readiness assessment
  - Deployment status and next steps
- Updated `README.md` with links to implementation documentation
- Added cross-references in deployment, API, and customization guides

### Phase 10 - Production, Scale & Observability

#### Added

**CI/CD & Quality:**
- Enhanced GitHub Actions CI pipeline with comprehensive test execution (Phases 1-5)
- Added JavaScript syntax validation to CI workflow
- Updated .gitignore to exclude vendor directory and composer.lock from repository

**Backup & Disaster Recovery:**
- Created `scripts/db_backup.sh` with support for SQLite and PostgreSQL
- Created `scripts/db_restore.sh` for database restoration
- Implemented configurable backup rotation (default: 7 days retention)
- Added comprehensive documentation in `docs/ops/backup_restore.md`
- Tested backup/restore functionality with SQLite

**Observability & Monitoring:**
- Implemented `/metrics.php` endpoint with Prometheus-compatible metrics
  - Job metrics: processed, failed, queue depth
  - Agent metrics: total agents, default agent status
  - Worker metrics: last seen timestamp, health status
  - Database metrics: size tracking (SQLite)
  - Admin API metrics: requests by resource
  - Webhook metrics: processed vs pending events
- Enhanced `/admin-api.php/health` endpoint with detailed status checks
  - Database connectivity check
  - OpenAI API accessibility check
  - Queue depth monitoring with thresholds
  - Worker health with last-seen tracking
  - Failed jobs in last 24 hours
- Created `docs/ops/monitoring/alerts.yml` with 15+ Prometheus alert rules
  - High job failure rate alerts
  - Queue depth warnings (100+) and critical (500+)
  - Worker down detection
  - OpenAI API error rate monitoring
  - Database growth tracking
  - SSL certificate expiration warnings

**Dead Letter Queue (DLQ):**
- Implemented DLQ infrastructure via migration `009_create_dead_letter_queue.sql`
- Enhanced JobQueue class with DLQ functionality
  - Automatic move to DLQ after max_attempts exceeded
  - `listDLQ()` - list failed jobs with filtering
  - `getDLQEntry()` - retrieve specific DLQ entry
  - `requeueFromDLQ()` - retry failed jobs with optional attempt reset
  - `deleteDLQEntry()` - remove DLQ entries
- Added DLQ management endpoints to Admin API
  - `GET /admin-api.php/list_dlq` - list DLQ entries
  - `GET /admin-api.php/get_dlq_entry` - get specific entry
  - `POST /admin-api.php/requeue_dlq` - requeue failed job
  - `DELETE /admin-api.php/delete_dlq_entry` - remove entry
- DLQ operations require `manage_jobs` permission

**Operational Documentation:**
- Created `docs/ops/logs.md` - Comprehensive logging and log aggregation guide
  - Structured JSON logging format specification
  - Integration guides for ELK Stack, AWS CloudWatch, and LogDNA
  - Log rotation configuration with logrotate
  - Security considerations and PII handling
  - GDPR compliance guidelines
- Created `docs/ops/nginx-production.conf` - Production-ready Nginx configuration
  - HTTPS enforcement with TLS 1.2/1.3
  - Security headers (HSTS, CSP, X-Frame-Options, etc.)
  - Rate limiting zones for different endpoints
  - CORS configuration for admin and chat APIs
  - SSL/TLS best practices
  - Admin subdomain configuration
- Created `docs/ops/production-deploy.md` - Complete deployment guide
  - System requirements and prerequisites
  - Step-by-step deployment procedure
  - PostgreSQL and SQLite setup instructions
  - Background worker systemd service configuration
  - Automated backup setup with cron
  - Performance tuning guidelines
  - Horizontal scaling strategies
  - Security checklist
- Created `docs/ops/incident_runbook.md` - Incident response procedures
  - Quick reference for common incidents
  - Detailed procedures for P0/P1 issues
  - Site down recovery steps
  - Database connection troubleshooting
  - Worker failure diagnosis and recovery
  - OpenAI API failure handling
  - Queue management procedures
  - Security breach response
  - Post-incident review process
- Created `docs/ops/secrets_management.md` - Secrets and token management
  - Token rotation procedures
  - AWS Secrets Manager integration guide
  - HashiCorp Vault integration guide
  - Database credential rotation
  - Security best practices
  - Compliance guidelines (GDPR, SOC2, PCI DSS)

#### Changed
- Enhanced health endpoint response format with detailed status checks
- Improved JobQueue error handling with automatic DLQ routing
- Updated CI/CD pipeline to run all test suites (122+ tests)

#### Fixed
- Resolved vendor directory tracking issues in git

## [1.0.0] - Phase 1-3 Implementation

### Phase 3 - Background Workers, Webhooks & RBAC

#### Added
- Background job queue system with retry logic
- Webhook handling for OpenAI events
- RBAC system with three roles (viewer, admin, super-admin)
- Per-user API key management
- Audit logging for all administrative actions
- Jobs management UI with real-time monitoring
- Dead letter queue for failed jobs (enhanced in Phase 4)

### Phase 2 - OpenAI Admin Client & Admin UI

#### Added
- OpenAI Admin API wrapper (`OpenAIAdminClient.php`)
- Prompt management with versioning
- Vector store management
- Files API integration
- Complete Admin UI single-page application
- Real-time agent testing
- Responsive design with mobile support

### Phase 1 - Database Layer & Agent Model

#### Added
- SQLite and PostgreSQL support
- Agent model with CRUD operations
- Database migration system
- Audit logging infrastructure
- AgentService with validation

## Versioning Notes

- **Phase 4** focuses on production readiness, observability, and operational excellence
- **Phase 3** added background processing, webhooks, and security enhancements
- **Phase 2** delivered the admin interface and OpenAI integration
- **Phase 1** established the data layer and agent abstraction

## Upgrade Notes

### Migrating to Phase 4

1. **Run new migration:**
   ```bash
   # DLQ table will be created automatically
   php -r "require 'includes/DB.php'; \$db = new DB(require 'config.php'); \$db->runMigrations();"
   ```

2. **Set up backups:**
   ```bash
   # Test backup script
   BACKUP_DIR=/tmp/test_backups ./scripts/db_backup.sh
   
   # Set up automated backups (see docs/ops/backup_restore.md)
   crontab -e -u www-data
   ```

3. **Configure monitoring:**
   - Add `/metrics.php` to Prometheus scraping configuration
   - Deploy alert rules from `docs/ops/monitoring/alerts.yml`
   - Set up log aggregation (see `docs/ops/logs.md`)

4. **Review security configuration:**
   - Apply production Nginx configuration from `docs/ops/nginx-production.conf`
   - Enforce HTTPS
   - Configure rate limiting

5. **Test DLQ functionality:**
   - Force a job to fail multiple times
   - Verify it appears in DLQ
   - Test requeue operation

### Breaking Changes

None. Phase 4 is fully backward compatible with Phases 1-3.

## Security Advisories

None at this time.

## Deprecation Notices

- **Legacy ADMIN_TOKEN**: While still supported, individual user API keys are recommended for production. The ADMIN_TOKEN should be reserved for emergency access only.

## Contributors

- Development team
- Community contributors
- Security reviewers

---

For detailed implementation notes, see:
- [Implementation Plan](docs/IMPLEMENTATION_PLAN.md)
- [Production Deployment Guide](docs/ops/production-deploy.md)
