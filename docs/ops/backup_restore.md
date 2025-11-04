# Database Backup & Restore Guide

## Overview

This guide covers database backup and restore procedures for the GPT Chatbot Admin system. Both SQLite and PostgreSQL databases are supported.

## Quick Reference

```bash
# Create a backup
./scripts/db_backup.sh

# Create a backup with custom retention
./scripts/db_backup.sh --retention-days 30

# Restore from a backup
./scripts/db_restore.sh /data/backups/admin_sqlite_20251104_120000.db.gz
```

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

### Automated Backups

#### Using Cron

Add to your crontab (`crontab -e`):

```bash
# Daily backup at 2 AM
0 2 * * * cd /path/to/chatbot && ./scripts/db_backup.sh >> /var/log/chatbot_backup.log 2>&1

# Weekly backup on Sundays at 3 AM with 30-day retention
0 3 * * 0 cd /path/to/chatbot && ./scripts/db_backup.sh --retention-days 30 >> /var/log/chatbot_backup.log 2>&1
```

#### Using Systemd Timer

Create `/etc/systemd/system/chatbot-backup.service`:

```ini
[Unit]
Description=GPT Chatbot Database Backup
After=network.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/var/www/chatbot
ExecStart=/var/www/chatbot/scripts/db_backup.sh
StandardOutput=journal
StandardError=journal
```

Create `/etc/systemd/system/chatbot-backup.timer`:

```ini
[Unit]
Description=GPT Chatbot Daily Backup Timer

[Timer]
OnCalendar=daily
OnCalendar=02:00
Persistent=true

[Install]
WantedBy=timers.target
```

Enable and start the timer:

```bash
sudo systemctl daemon-reload
sudo systemctl enable chatbot-backup.timer
sudo systemctl start chatbot-backup.timer

# Check timer status
sudo systemctl list-timers chatbot-backup.timer
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
