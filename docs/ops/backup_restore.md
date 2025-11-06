# Backup & Restore Guide

## Overview

This guide covers comprehensive backup and restore procedures for the GPT Chatbot platform. It includes automated backups for all persistent data: database, uploaded files, configuration, and application data.

## Quick Reference

```bash
# Create a full backup (all data)
./scripts/backup_all.sh

# Create a database-only backup
./scripts/db_backup.sh

# Create a backup with custom retention
./scripts/backup_all.sh --retention-days 30

# Create a backup with off-site sync
./scripts/backup_all.sh --offsite

# Restore from a full backup
./scripts/restore_all.sh /data/backups/full_backup_20251104_120000.tar.gz

# Restore database only
./scripts/db_restore.sh /data/backups/admin_sqlite_20251104_120000.db.gz

# Monitor backup health
./scripts/monitor_backups.sh

# Test backup restore capability
./scripts/test_restore.sh
```

## Backup Types

### 1. Full System Backup (`backup_all.sh`)

Backs up all persistent data:
- Database (SQLite or PostgreSQL)
- Uploaded files
- Configuration files (.env, config.php, etc.)
- Application data directory
- Creates a backup manifest

**Recommended for:** Daily backups, disaster recovery

### 2. Database-Only Backup (`db_backup.sh`)

Backs up only the database.

**Recommended for:** Frequent recovery points between full backups

## Backup Script

### Features

- **Automatic database type detection** from `ADMIN_DB_TYPE` environment variable
- **Compression** using gzip to save storage space
- **Automatic rotation** to remove old backups beyond retention period
- **Consistent backups** using database-specific tools (sqlite3 `.backup`, pg_dump)
- **Configurable retention** (default: 7 days)

### Configuration

The backup script uses environment variables from `.env`:

```bash
# SQLite
ADMIN_DB_TYPE=sqlite
ADMIN_DB_PATH=./data/admin.db

# PostgreSQL
ADMIN_DB_TYPE=postgres
ADMIN_DATABASE_URL=postgresql://user:password@localhost:5432/chatbot_admin
```

Additional configuration via environment variables:

```bash
# Override backup directory (default: /data/backups)
export BACKUP_DIR=/path/to/backups

# Override retention days (default: 7)
export RETENTION_DAYS=30
```

### Usage

#### Basic Backup

```bash
./scripts/db_backup.sh
```

This will:
1. Detect database type from environment
2. Create timestamped backup file
3. Compress the backup with gzip
4. Rotate old backups beyond retention period
5. Display backup summary

#### Custom Retention

```bash
./scripts/db_backup.sh --retention-days 30
```

Keep backups for 30 days instead of the default 7 days.

### Backup Location

Backups are stored in `/data/backups/` by default with the following naming convention:

- **SQLite**: `admin_sqlite_YYYYMMDD_HHMMSS.db.gz`
- **PostgreSQL**: `admin_postgres_YYYYMMDD_HHMMSS.sql.gz`

## Automated Backups

### Backup Schedule Recommendations

The platform supports multiple retention tiers:

| Tier | Frequency | Retention | Script | Use Case |
|------|-----------|-----------|--------|----------|
| **Hourly** | Every 6 hours | 48 hours | `db_backup.sh` | Frequent recovery points |
| **Daily** | Every day at 2 AM | 7 days | `backup_all.sh` | Standard backups |
| **Weekly** | Sundays at 3 AM | 30 days | `backup_all.sh --offsite` | Off-site DR |
| **Monthly** | 1st of month at 4 AM | 365 days | `backup_all.sh --offsite` | Long-term retention |

### Using Cron

Add to your crontab (`crontab -e`):

```bash
# Daily full backup at 2:00 AM
0 2 * * * cd /var/www/chatbot && ./scripts/backup_all.sh --retention-days 7 >> /var/log/chatbot/backup.log 2>&1

# Weekly backup with off-site sync
0 3 * * 0 cd /var/www/chatbot && ./scripts/backup_all.sh --retention-days 30 --offsite >> /var/log/chatbot/backup-weekly.log 2>&1

# Database backup every 6 hours
0 */6 * * * cd /var/www/chatbot && ./scripts/db_backup.sh --retention-days 2 >> /var/log/chatbot/backup-db.log 2>&1

# Backup monitoring every hour
0 * * * * cd /var/www/chatbot && ./scripts/monitor_backups.sh >> /var/log/chatbot/backup-monitor.log 2>&1
```

See `scripts/backup.crontab` for complete examples.

### Using Systemd Timers

1. **Copy service files:**
   ```bash
   sudo cp scripts/chatbot-backup.service /etc/systemd/system/
   sudo cp scripts/chatbot-backup.timer /etc/systemd/system/
   sudo cp scripts/chatbot-backup-monitor.service /etc/systemd/system/
   sudo cp scripts/chatbot-backup-monitor.timer /etc/systemd/system/
   ```

2. **Enable and start timers:**
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable chatbot-backup.timer
   sudo systemctl enable chatbot-backup-monitor.timer
   sudo systemctl start chatbot-backup.timer
   sudo systemctl start chatbot-backup-monitor.timer
   ```

3. **Check timer status:**
   ```bash
   sudo systemctl list-timers chatbot-*
   sudo systemctl status chatbot-backup.timer
   sudo systemctl status chatbot-backup-monitor.timer
   ```

4. **View logs:**
   ```bash
   sudo journalctl -u chatbot-backup.service -f
   sudo journalctl -u chatbot-backup-monitor.service -f
   ```

## Restore Script

### Features

- **Interactive confirmation** before overwriting current database
- **Automatic backup** of current database before restore
- **Decompression** of gzipped backup files
- **Database integrity checks** (SQLite only)
- **Safe restore** with pre-restore snapshot

### Usage

#### Basic Restore

```bash
./scripts/db_restore.sh /data/backups/admin_sqlite_20251104_120000.db.gz
```

This will:
1. Prompt for confirmation (type "yes" to proceed)
2. Create backup of current database
3. Decompress the backup file (if gzipped)
4. Restore the database
5. Verify integrity (SQLite only)

#### PostgreSQL Restore

```bash
./scripts/db_restore.sh /data/backups/admin_postgres_20251104_120000.sql.gz
```

For PostgreSQL, the restore process:
1. Backs up current database to `/tmp/admin_postgres_before_restore_*.sql`
2. Drops all tables (clean restore)
3. Restores from backup file
4. Reports completion and location of pre-restore backup

### Safety Features

#### Pre-Restore Backup

Before overwriting the database, a backup is created:

- **SQLite**: `{DB_PATH}.before_restore.YYYYMMDD_HHMMSS`
- **PostgreSQL**: `/tmp/admin_postgres_before_restore_YYYYMMDD_HHMMSS.sql`

#### Interactive Confirmation

The script will prompt:

```
⚠️  WARNING: This will OVERWRITE the current database!

Are you sure you want to continue? (yes/no):
```

Type "yes" (case-insensitive) to proceed.

## Backup Monitoring

### Automated Monitoring Script

The `monitor_backups.sh` script performs comprehensive health checks:

**Checks performed:**
- Backup directory exists
- Latest backup age (alerts if > 25 hours)
- Backup size (alerts if suspiciously small)
- Archive integrity
- Backup count (minimum 3 recommended)
- Backup rotation is working
- Disk space availability
- Backup scripts are executable

**Usage:**
```bash
./scripts/monitor_backups.sh
```

**Exit codes:**
- `0` - All checks passed
- `1` - Warnings detected
- `2` - Critical issues detected

### Alerting

Configure alerts via environment variables:

```bash
# Email alerts (requires mail command)
export ALERT_EMAIL="ops@example.com"

# Slack webhook
export ALERT_SLACK_WEBHOOK="https://hooks.slack.com/services/YOUR/WEBHOOK/URL"

# Generic webhook
export ALERT_WEBHOOK="https://your-monitoring.example.com/webhook"
```

Alerts are sent when:
- No backup in last 24 hours
- Backup size is anomalous
- Archive integrity check fails
- Disk space is low
- Backup count is below minimum

### Prometheus Metrics

Backup metrics are exposed for monitoring (requires instrumentation):

```yaml
# Metrics available
chatbot_last_backup_timestamp_seconds     # Unix timestamp of last backup
chatbot_backup_status                     # 1 = success, 0 = failure
chatbot_last_backup_size_bytes           # Size of last backup
chatbot_backup_count                      # Number of backups retained
chatbot_offsite_backup_status            # Off-site backup status
```

See `docs/ops/monitoring/alerts.yml` for Prometheus alert rules.

## Testing and Validation

### Automated Restore Testing

The `test_restore.sh` script validates backup integrity:

**Tests performed:**
1. Backup file exists and is accessible
2. Archive integrity (tar can read it)
3. Backup extraction
4. Manifest verification
5. Database backup presence and integrity
6. Configuration files presence
7. Uploaded files backup integrity
8. Application data backup integrity
9. (Optional) Staging server restore test

**Usage:**
```bash
# Test latest backup locally
./scripts/test_restore.sh

# Test specific backup
./scripts/test_restore.sh --backup-file /data/backups/full_backup_20251104.tar.gz

# Test with staging server restore
./scripts/test_restore.sh --staging-server user@staging.example.com
```

**Recommended schedule:** Run monthly or quarterly

### Quarterly DR Drills

Conduct full disaster recovery drills quarterly:

1. **Preparation (1 week before)**
   - Schedule drill with team
   - Choose scenario to test
   - Verify recent backups exist
   - Prepare staging environment

2. **Execution (2-4 hours)**
   - Follow disaster recovery runbook
   - Document time for each step
   - Note any issues encountered
   - Verify full functionality

3. **Validation (1 hour)**
   - Verify data integrity
   - Test critical workflows
   - Confirm RTO/RPO met
   - Document results

4. **Post-Drill (1 week after)**
   - Conduct debrief meeting
   - Update runbook based on findings
   - Address issues discovered
   - Schedule next drill

See `docs/ops/disaster_recovery.md` for complete DR procedures.

### Manual Verification

Weekly verification checklist:

```bash
# 1. List recent backups
ls -lth /data/backups/

# 2. Test archive integrity
tar -tzf $(ls -t /data/backups/full_backup_*.tar.gz | head -1) > /dev/null

# 3. Verify backup size consistency
du -h /data/backups/full_backup_*.tar.gz | tail -5

# 4. Check backup manifest
tar -xzf $(ls -t /data/backups/full_backup_*.tar.gz | head -1) -O */MANIFEST.txt

# 5. Verify database in latest backup
./scripts/test_restore.sh
```

## Recovery Objectives

### RPO (Recovery Point Objective)

Maximum acceptable data loss:

| Data Class | RPO | Backup Frequency |
|-----------|-----|------------------|
| Database | 6 hours | Every 6 hours |
| Configuration | 24 hours | Daily |
| Uploaded Files | 24 hours | Daily |
| Logs | 7 days | Weekly |

### RTO (Recovery Time Objective)

Maximum acceptable downtime:

| Scenario | RTO | Priority |
|----------|-----|----------|
| Complete System Failure | 4 hours | P0 |
| Database Corruption | 2 hours | P0 |
| Partial Data Loss | 4 hours | P1 |
| Configuration Error | 1 hour | P1 |

See `docs/ops/disaster_recovery.md` for detailed recovery procedures.

## Off-Site Backup Procedures

Off-site backups are critical for disaster recovery. The `backup_all.sh` script supports automatic off-site synchronization.

### Configuration

Set the off-site destination in your environment:

```bash
# In .env or export before running
export OFFSITE_DESTINATION="user@backup-server:/backups/chatbot"
# OR for S3:
export OFFSITE_DESTINATION="s3://my-bucket/chatbot-backups"
# OR for local/mounted path:
export OFFSITE_DESTINATION="/mnt/backup-nas/chatbot"
```

### Supported Methods

#### 1. Rsync to Remote Server

**Setup:**
```bash
# Generate SSH key for automated backups
ssh-keygen -t ed25519 -f ~/.ssh/backup_key -N ""

# Copy key to backup server
ssh-copy-id -i ~/.ssh/backup_key backup@backup-server

# Configure SSH for passwordless access
cat >> ~/.ssh/config << EOF
Host backup-server
    HostName backup-server.example.com
    User backup
    IdentityFile ~/.ssh/backup_key
EOF
```

**Usage:**
```bash
# Set destination
export OFFSITE_DESTINATION="backup@backup-server:/backups/chatbot"

# Run backup with off-site sync
./scripts/backup_all.sh --offsite
```

**Manual sync:**
```bash
rsync -avz --delete \
  -e "ssh -i ~/.ssh/backup_key" \
  /data/backups/ \
  backup@backup-server:/backups/chatbot/
```

#### 2. AWS S3

**Setup:**
```bash
# Install AWS CLI
pip install awscli

# Configure credentials
aws configure
# Enter: Access Key ID, Secret Access Key, Region, Output format

# Create S3 bucket (if needed)
aws s3 mb s3://my-chatbot-backups --region us-east-1

# Set lifecycle policy for cost optimization
aws s3api put-bucket-lifecycle-configuration \
  --bucket my-chatbot-backups \
  --lifecycle-configuration file://s3-lifecycle.json
```

**s3-lifecycle.json:**
```json
{
  "Rules": [
    {
      "Id": "MoveToGlacier",
      "Status": "Enabled",
      "Transitions": [
        {
          "Days": 30,
          "StorageClass": "GLACIER"
        }
      ],
      "Expiration": {
        "Days": 365
      }
    }
  ]
}
```

**Usage:**
```bash
# Set destination
export OFFSITE_DESTINATION="s3://my-chatbot-backups"

# Run backup with off-site sync
./scripts/backup_all.sh --offsite
```

**Manual sync:**
```bash
aws s3 sync /data/backups/ s3://my-chatbot-backups/ \
  --storage-class STANDARD_IA \
  --exclude "*" \
  --include "full_backup_*.tar.gz"
```

#### 3. Azure Blob Storage

**Setup:**
```bash
# Install Azure CLI
pip install azure-cli

# Login
az login

# Create storage account and container
az storage account create --name mychatbotbackups --resource-group mygroup
az storage container create --name chatbot-backups --account-name mychatbotbackups
```

**Manual sync:**
```bash
az storage blob upload-batch \
  --account-name mychatbotbackups \
  --destination chatbot-backups \
  --source /data/backups/ \
  --pattern "full_backup_*.tar.gz"
```

#### 4. Google Cloud Storage

**Setup:**
```bash
# Install gsutil
pip install gsutil

# Authenticate
gcloud auth login

# Create bucket
gsutil mb gs://my-chatbot-backups
```

**Manual sync:**
```bash
gsutil -m rsync -r /data/backups/ gs://my-chatbot-backups/
```

### Off-Site Backup Best Practices

1. **Encrypt backups in transit and at rest**
   - Use SSL/TLS for transfers
   - Enable server-side encryption
   - Consider client-side encryption for sensitive data

2. **Verify off-site backups regularly**
   ```bash
   # For S3
   aws s3 ls s3://my-chatbot-backups/ --recursive --human-readable
   
   # For rsync
   ssh backup-server "ls -lh /backups/chatbot/"
   ```

3. **Test restores from off-site location**
   ```bash
   # Download backup from S3
   aws s3 cp s3://my-chatbot-backups/full_backup_20251104.tar.gz /tmp/
   
   # Test restore
   ./scripts/test_restore.sh --backup-file /tmp/full_backup_20251104.tar.gz
   ```

4. **Monitor off-site sync failures**
   - Configure alerts for sync failures
   - Review sync logs regularly
   - Maintain multiple off-site locations for redundancy

5. **Implement 3-2-1 backup strategy**
   - **3** copies of your data
   - **2** different media types
   - **1** copy off-site

## Disaster Recovery

### Complete Loss Scenario

If the database is completely lost or corrupted:

1. **Stop the application**:
   ```bash
   # Docker
   docker-compose down
   
   # Systemd
   sudo systemctl stop chatbot
   ```

2. **Identify the latest backup**:
   ```bash
   ls -lt /data/backups/admin_*
   ```

3. **Restore from backup**:
   ```bash
   ./scripts/db_restore.sh /data/backups/admin_sqlite_20251104_120000.db.gz
   ```

4. **Verify the restore**:
   ```bash
   # SQLite
   sqlite3 ./data/admin.db "SELECT COUNT(*) FROM agents;"
   
   # PostgreSQL
   psql $ADMIN_DATABASE_URL -c "SELECT COUNT(*) FROM agents;"
   ```

5. **Restart the application**:
   ```bash
   # Docker
   docker-compose up -d
   
   # Systemd
   sudo systemctl start chatbot
   ```

### Partial Data Loss

If only some data is corrupted:

1. **Create a backup of current state** (for forensics):
   ```bash
   ./scripts/db_backup.sh
   ```

2. **Restore from the most recent good backup**:
   ```bash
   ./scripts/db_restore.sh /data/backups/admin_sqlite_20251103_020000.db.gz
   ```

3. **Manually recover missing data** from the pre-restore backup created in step 1

## Best Practices

### Backup Strategy

1. **Multiple retention tiers**:
   - Daily backups: 7 days retention
   - Weekly backups: 30 days retention
   - Monthly backups: 365 days retention

2. **Off-site backups**: Copy backups to a different location
   ```bash
   # Example: rsync to remote server
   rsync -avz /data/backups/ backup-server:/backups/chatbot/
   
   # Example: Upload to S3
   aws s3 sync /data/backups/ s3://my-bucket/chatbot-backups/
   ```

3. **Test restores regularly**:
   ```bash
   # Monthly restore test in staging environment
   ./scripts/db_restore.sh /data/backups/admin_sqlite_latest.db.gz
   ```

### Monitoring

Monitor backup success:

```bash
# Check latest backup age
find /data/backups -name "admin_*" -type f -mtime -1 | wc -l

# Alert if no backup in last 24 hours
if [ $(find /data/backups -name "admin_*" -type f -mtime -1 | wc -l) -eq 0 ]; then
    echo "ALERT: No backup found in last 24 hours"
fi
```

### PostgreSQL Production Recommendations

For production PostgreSQL deployments:

1. **Enable WAL archiving** for point-in-time recovery
2. **Use pg_basebackup** for physical backups
3. **Configure continuous archiving** with `archive_command`
4. **Set up streaming replication** for high availability

See PostgreSQL documentation for details: https://www.postgresql.org/docs/current/backup.html

## Troubleshooting

### "Database locked" Error (SQLite)

If you get a "database locked" error:

1. **Stop all processes** accessing the database:
   ```bash
   # Check for processes
   lsof ./data/admin.db
   
   # Stop worker
   pkill -f worker.php
   ```

2. **Try the backup again**:
   ```bash
   ./scripts/db_backup.sh
   ```

### "Permission denied" Error

Ensure proper file permissions:

```bash
# Make scripts executable
chmod +x scripts/db_backup.sh scripts/db_restore.sh

# Ensure backup directory is writable
mkdir -p /data/backups
chmod 755 /data/backups
```

### Backup File Not Found

Check the backup directory:

```bash
ls -lh /data/backups/
```

If backups are missing, check if the retention period deleted them.

### PostgreSQL Connection Error

Verify the `ADMIN_DATABASE_URL`:

```bash
# Test connection
psql $ADMIN_DATABASE_URL -c "SELECT version();"
```

Ensure:
- Hostname is reachable
- Port is correct (usually 5432)
- Username and password are correct
- Database exists

## Migration from SQLite to PostgreSQL

To migrate from SQLite to PostgreSQL in production:

1. **Create final SQLite backup**:
   ```bash
   ./scripts/db_backup.sh
   ```

2. **Set up PostgreSQL database**:
   ```bash
   createdb chatbot_admin
   ```

3. **Export SQLite data to SQL**:
   ```bash
   sqlite3 ./data/admin.db .dump > sqlite_export.sql
   ```

4. **Convert SQLite SQL to PostgreSQL** (remove SQLite-specific syntax):
   ```bash
   # Remove SQLite-specific commands
   sed -i '/BEGIN TRANSACTION;/d' sqlite_export.sql
   sed -i '/COMMIT;/d' sqlite_export.sql
   sed -i '/PRAGMA/d' sqlite_export.sql
   ```

5. **Import to PostgreSQL**:
   ```bash
   psql $ADMIN_DATABASE_URL < sqlite_export.sql
   ```

6. **Update `.env`**:
   ```bash
   ADMIN_DB_TYPE=postgres
   ADMIN_DATABASE_URL=postgresql://user:password@localhost:5432/chatbot_admin
   ```

7. **Verify migration**:
   ```bash
   psql $ADMIN_DATABASE_URL -c "SELECT COUNT(*) FROM agents;"
   ```

8. **Test application** with PostgreSQL

9. **Set up PostgreSQL backups**:
   ```bash
   ./scripts/db_backup.sh
   ```

## See Also

- [Production Deployment Guide](production-deploy.md)
- [Incident Runbook](incident_runbook.md)
- [Monitoring Guide](logs.md)
