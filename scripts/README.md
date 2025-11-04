# Scripts Directory

This directory contains operational scripts for the GPT Chatbot Boilerplate application.

## Available Scripts

### Production Operations

#### `db_backup.sh`
**Purpose**: Automated database backup with rotation

**Usage**:
```bash
# Basic backup
./scripts/db_backup.sh

# Custom backup directory
BACKUP_DIR=/path/to/backups ./scripts/db_backup.sh

# Custom retention (days)
RETENTION_DAYS=14 ./scripts/db_backup.sh
```

**Features**:
- Supports SQLite and PostgreSQL
- Automatic compression (gzip)
- Rotation based on retention period (default: 7 days)
- Timestamped backups
- Status reporting

**Automation** (crontab example):
```bash
# Daily backup at 2 AM
0 2 * * * /path/to/scripts/db_backup.sh >> /var/log/chatbot-backup.log 2>&1
```

---

#### `db_restore.sh`
**Purpose**: Safe database restoration from backup

**Usage**:
```bash
# Restore from specific backup
./scripts/db_restore.sh /path/to/backup.sql.gz

# Interactive mode (will prompt for confirmation)
./scripts/db_restore.sh
```

**Features**:
- Interactive confirmation before restore
- Pre-restore safety backup
- Decompression support (gzip)
- Database integrity checks (SQLite)
- Works with both SQLite and PostgreSQL

**Safety**: Always creates a backup before restoring!

---

#### `worker.php`
**Purpose**: Background job worker for processing async tasks

**Usage**:
```bash
# Single run (process available jobs once)
php scripts/worker.php --once

# Loop mode (process jobs continuously)
php scripts/worker.php --loop

# Daemon mode (persistent background process)
php scripts/worker.php --daemon
```

**Features**:
- Processes jobs from the queue
- Exponential backoff for retries
- Dead Letter Queue (DLQ) for failed jobs
- Atomic job claiming (prevents race conditions)
- Supports multiple job types

**Production Deployment**: See `docs/ops/production-deploy.md` for systemd service configuration.

---

### Testing & Validation

#### `smoke_test.sh`
**Purpose**: Comprehensive production readiness verification

**Usage**:
```bash
# Run all smoke tests
./scripts/smoke_test.sh
```

**What it tests**:
1. **File Structure** (6 tests)
   - CI workflow existence
   - Backup/restore scripts
   - Metrics and config files

2. **Documentation** (8 tests)
   - Operational docs completeness
   - Runbooks and guides
   - Configuration examples

3. **Code Quality** (4 tests)
   - PHP syntax validation
   - Configuration validity
   - Critical file parsing

4. **Database Migrations** (3 tests)
   - All migrations present
   - DLQ migration exists
   - Migration count

5. **Feature Implementation** (6 tests)
   - Rate limiting
   - DLQ endpoints
   - Token rotation
   - Health checks
   - Metrics

6. **Configuration** (3 tests)
   - Environment variables
   - Admin configuration
   - Jobs configuration

7. **Load Testing** (2 tests)
   - k6 scripts
   - Documentation

8. **PHP Unit Tests** (5 test suites)
   - Phase 1: Database & Agents (28 tests)
   - Phase 2: Prompts & Vector Stores (44 tests)
   - Phase 3: Jobs, Webhooks, RBAC (36 tests)
   - Phase 4: Production features (14 tests)
   - Phase 5: Agent integration (33 tests)

**Exit Codes**:
- `0` - All tests passed
- `1` - One or more tests failed

**Use in CI/CD**:
```yaml
- name: Run smoke tests
  run: bash scripts/smoke_test.sh
```

**Before Deployment**:
Always run smoke tests before deploying to production to ensure all features are functional.

---

## Directory Structure

```
scripts/
├── README.md              # This file
├── db_backup.sh           # Database backup script
├── db_restore.sh          # Database restore script
├── worker.php             # Background job worker
└── smoke_test.sh          # Production smoke tests
```

## Environment Variables

Scripts respect the following environment variables:

### Backup Scripts
- `BACKUP_DIR` - Backup directory (default: `./backups`)
- `RETENTION_DAYS` - Days to keep backups (default: 7)
- `ADMIN_DB_TYPE` - Database type: sqlite or postgres
- `ADMIN_DB_PATH` - SQLite database path
- `DATABASE_URL` - PostgreSQL connection URL

### Worker Script
- `JOBS_ENABLED` - Enable background jobs (default: true)
- `WORKER_SLEEP` - Sleep duration between checks in seconds
- `MAX_ATTEMPTS` - Maximum retry attempts before DLQ

## Logging

All scripts log to:
- **stdout** - Normal operation messages
- **stderr** - Errors and warnings

For production, redirect output to log files:
```bash
./scripts/db_backup.sh >> /var/log/chatbot/backup.log 2>&1
php scripts/worker.php --daemon >> /var/log/chatbot/worker.log 2>&1
```

## Permissions

Ensure scripts are executable:
```bash
chmod +x scripts/*.sh
```

Database directories must be writable by the script user:
```bash
chown -R www-data:www-data data/
chmod 750 data/
```

## Related Documentation

- **Backup & Restore Guide**: `docs/ops/backup_restore.md`
- **Production Deployment**: `docs/ops/production-deploy.md`
- **Incident Runbook**: `docs/ops/incident_runbook.md`
- **Monitoring Setup**: `docs/ops/monitoring/alerts.yml`

## Support

For issues or questions:
1. Check the documentation in `docs/`
2. Review the incident runbook
3. Check application logs
4. Verify environment variables

## Security Notes

- Never commit `.env` files with secrets
- Backup files may contain sensitive data - secure accordingly
- Use secure channels for backup transfers
- Implement backup encryption for production
- Rotate database credentials regularly (see `docs/ops/secrets_management.md`)
