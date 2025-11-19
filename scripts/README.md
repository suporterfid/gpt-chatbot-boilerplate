# Scripts Directory

This directory contains operational scripts for the GPT Chatbot Boilerplate application, including comprehensive backup, restore, monitoring, disaster recovery tools, and **deployment package builders**.

## Quick Reference

```bash
# Deployment Package Creation ⭐ NEW
./scripts/build-deployment.sh             # Build deployment ZIP (Linux/macOS)
scripts\build-deployment.bat              # Build deployment ZIP (Windows)

# Backup Operations
./scripts/backup_all.sh              # Full system backup
./scripts/db_backup.sh               # Database-only backup
./scripts/backup_all.sh --offsite    # Backup with off-site sync

# Restore Operations
./scripts/restore_all.sh <backup>    # Full system restore
./scripts/db_restore.sh <backup>     # Database-only restore

# Monitoring & Testing
./scripts/monitor_backups.sh         # Check backup health
./scripts/test_restore.sh            # Validate backup integrity
```

## Available Scripts

### Deployment & Release Management

#### `build-deployment.sh` / `build-deployment.bat` ⭐ NEW
**Purpose**: Creates production-ready deployment packages for cloud hosting

**Usage**:
```bash
# Linux/macOS
./scripts/build-deployment.sh [output-filename]
./scripts/build-deployment.sh chatbot-deploy.zip

# Windows
scripts\build-deployment.bat [output-filename]
scripts\build-deployment.bat chatbot-deploy.zip
```

**Features**:
- Creates optimized ZIP packages for cloud hosting (Hostinger, cPanel, etc.)
- Includes only production files (excludes dev files, tests, docs)
- Automatically runs `composer install --no-dev --optimize-autoloader`
- Generates deployment metadata and checksums (SHA256)
- Works identically locally and in CI/CD (GitHub Actions)
- Cross-platform (Windows batch + Unix bash scripts)

**Package Contents**:
- Application PHP files and frontend assets
- Production dependencies (`vendor/`)
- Database migrations
- `.env.example` template
- Configuration files (`.htaccess`, `composer.json`)
- `DEPLOYMENT_INFO.txt` with deployment instructions

**Output**:
- `chatbot-deploy.zip` - Deployment package
- `chatbot-deploy.zip.sha256` - Checksum file
- `build/deployment/` - Staging directory (auto-cleaned)

**See Also**: [DEPLOYMENT_PACKAGE_GUIDE.md](../docs/DEPLOYMENT_PACKAGE_GUIDE.md)

---

### Backup & Disaster Recovery

#### `backup_all.sh` ⭐
**Purpose**: Comprehensive backup of all persistent data

**Usage**:
```bash
# Full system backup (database, files, config, data)
./scripts/backup_all.sh

# With custom retention
./scripts/backup_all.sh --retention-days 30

# With off-site synchronization
./scripts/backup_all.sh --offsite
```

**Features**:
- Backs up database, uploaded files, configuration, and application data
- Creates compressed tar archive with manifest
- Supports off-site sync (rsync, S3, Azure, GCS)
- Automatic rotation
- Error tracking and reporting

**Backup Contents**:
- Database (SQLite or PostgreSQL)
- Uploaded files
- Configuration files (.env, config.php, etc.)
- Application data directory
- Backup manifest with metadata

---

#### `restore_all.sh` ⭐ NEW
**Purpose**: Complete system restoration from backup

**Usage**:
```bash
# Restore from full backup
./scripts/restore_all.sh /data/backups/full_backup_20251104_120000.tar.gz
```

**Features**:
- Interactive confirmation
- Pre-restore safety backups
- Validates archive integrity
- Restores all components
- Verification steps

---

#### `monitor_backups.sh` ⭐ NEW
**Purpose**: Automated backup health monitoring with alerting

**Usage**:
```bash
# Run monitoring check
./scripts/monitor_backups.sh

# With alerts configured
export ALERT_EMAIL="ops@example.com"
export ALERT_SLACK_WEBHOOK="https://hooks.slack.com/..."
./scripts/monitor_backups.sh
```

**Checks**:
- Backup directory exists
- Latest backup age (< 25 hours)
- Backup size anomalies
- Archive integrity
- Backup count (≥ 3 recommended)
- Disk space
- Script permissions

**Exit Codes**:
- `0` - All checks passed
- `1` - Warnings detected
- `2` - Critical issues

---

#### `test_restore.sh` ⭐ NEW
**Purpose**: Validate backup integrity and restore capability

**Usage**:
```bash
# Test latest backup
./scripts/test_restore.sh

# Test specific backup
./scripts/test_restore.sh --backup-file /data/backups/full_backup_latest.tar.gz

# Test with staging server restore
./scripts/test_restore.sh --staging-server user@staging.example.com
```

**Tests**:
- Archive integrity
- Backup extraction
- Manifest validation
- Database integrity
- Configuration files
- File archives
- (Optional) Staging restore

---

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

#### `tenant_backup.sh`
**Purpose**: Export all records for a single tenant (GDPR requests, migrations, or partial restores)

**Usage**:
```bash
# SQLite (default)
./scripts/tenant_backup.sh tenant_123

# PostgreSQL using DATABASE_URL (password automatically scoped to psql/pg_dump commands)
ADMIN_DB_TYPE=postgres \
DATABASE_URL="postgres://chatbot:secret@db.example.com:5432/chatbot" \
./scripts/tenant_backup.sh tenant_123 --export-only

# PostgreSQL using discrete PG* variables
ADMIN_DB_TYPE=postgres \
PGHOST=db.internal \
PGPORT=5432 \
PGUSER=chatbot \
PGPASSWORD=secret \
PGDATABASE=chatbot \
./scripts/tenant_backup.sh tenant_123
```

**Features**:
- Detects `ADMIN_DB_TYPE=sqlite` **or** `ADMIN_DB_TYPE=postgres`
- Parses `DATABASE_URL` (including optional `schema`/`sslmode` query params) or falls back to discrete `PG*` env vars
- Verifies tenant existence before exporting
- Generates `COPY ... FROM STDIN WITH CSV` `.sql` files for each tenant-scoped table
- Writes manifest entries with accurate record counts for SQLite and PostgreSQL
- Archives the export into `tenants/tenant_<tenant>_<timestamp>.tar.gz`

**Manual validation**:
```bash
# SQLite smoke test (uses bundled demo DB)
./scripts/tenant_backup.sh demo-tenant --export-only

# PostgreSQL connectivity check (requires running database)
ADMIN_DB_TYPE=postgres DATABASE_URL=postgres://... ./scripts/tenant_backup.sh demo-tenant

# Inspect the manifest
tar -tzf /data/backups/tenants/tenant_demo-tenant_*.tar.gz | grep MANIFEST
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

## Automation Templates

### `backup.crontab` ⭐ NEW
**Purpose**: Cron schedule examples for automated backups

**Contains**:
- Daily full backups (7-day retention)
- Weekly backups with off-site sync (30-day retention)
- Database-only backups every 6 hours (2-day retention)
- Hourly backup monitoring
- Monthly long-term backups (365-day retention)

**Installation**:
```bash
# Add to your crontab
crontab -e
# Then copy relevant lines from backup.crontab
```

---

### Systemd Service Files ⭐ NEW

**Files**:
- `chatbot-backup.service` - Backup execution service
- `chatbot-backup.timer` - Daily backup schedule
- `chatbot-backup-monitor.service` - Monitoring execution service
- `chatbot-backup-monitor.timer` - Hourly monitoring schedule

**Installation**:
```bash
# Copy service files
sudo cp scripts/chatbot-backup.service /etc/systemd/system/
sudo cp scripts/chatbot-backup.timer /etc/systemd/system/
sudo cp scripts/chatbot-backup-monitor.service /etc/systemd/system/
sudo cp scripts/chatbot-backup-monitor.timer /etc/systemd/system/

# Enable and start
sudo systemctl daemon-reload
sudo systemctl enable chatbot-backup.timer
sudo systemctl enable chatbot-backup-monitor.timer
sudo systemctl start chatbot-backup.timer
sudo systemctl start chatbot-backup-monitor.timer

# Check status
sudo systemctl list-timers chatbot-*
```

**View Logs**:
```bash
sudo journalctl -u chatbot-backup.service -f
sudo journalctl -u chatbot-backup-monitor.service -f
```

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
