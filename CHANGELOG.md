# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
