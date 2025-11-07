# Disaster Recovery Runbook

## Overview

This runbook provides step-by-step procedures for recovering from various disaster scenarios in the GPT Chatbot platform. It defines Recovery Point Objectives (RPO) and Recovery Time Objectives (RTO) for different data classes and provides tested procedures for complete system restoration.

> **Multi-Tenant Deployments:** For tenant-specific disaster recovery procedures, tier-based RPO/RTO objectives, and selective tenant restore operations, see [Multi-Tenant Backup & DR Guide](multi_tenant_backup_dr.md).

## Recovery Objectives

### RPO (Recovery Point Objective) - Maximum Acceptable Data Loss

| Data Class | RPO | Backup Frequency | Justification |
|-----------|-----|------------------|---------------|
| **Database** | 6 hours | Every 6 hours | Critical agent configurations, prompts, user data |
| **Configuration Files** | 24 hours | Daily | Changes infrequently, low risk |
| **Uploaded Files** | 24 hours | Daily | User-uploaded content, moderate risk |
| **Application Logs** | 7 days | Weekly | Diagnostic data, acceptable loss |
| **Metrics/Analytics** | 7 days | Weekly | Historical data, can be regenerated |

### RTO (Recovery Time Objective) - Maximum Acceptable Downtime

| Scenario | RTO | Priority |
|----------|-----|----------|
| **Complete System Failure** | 4 hours | P0 |
| **Database Corruption** | 2 hours | P0 |
| **Database Server Failure** | 1 hour | P0 |
| **Application Server Failure** | 30 minutes | P0 |
| **Partial Data Loss** | 4 hours | P1 |
| **Configuration Error** | 1 hour | P1 |

## Backup Strategy

### Automated Backups

The platform implements a multi-tier backup strategy:

1. **Hourly Database Snapshots** (48-hour retention)
   - Database only
   - Quick recovery points
   - Minimal storage impact

2. **Daily Full Backups** (7-day retention)
   - Complete system backup
   - All persistent data
   - Local storage

3. **Weekly Full Backups** (30-day retention)
   - Complete system backup
   - Off-site replication
   - Disaster recovery tier

4. **Monthly Full Backups** (365-day retention)
   - Complete system backup
   - Long-term retention
   - Compliance/audit tier

### Backup Contents

Each full backup includes:
- Database (SQLite or PostgreSQL)
- Uploaded files
- Configuration files (.env, config.php, etc.)
- Application data directory
- Backup manifest with metadata

## Pre-Disaster Preparation

### Essential Information to Document

Before a disaster occurs, ensure you have documented:

1. **Server Access**
   - SSH credentials and keys
   - Cloud console access
   - Database credentials
   - Admin API tokens

2. **Backup Locations**
   - Local backup path: `/data/backups`
   - Off-site backup location
   - Access credentials for off-site storage

3. **External Dependencies**
   - OpenAI API keys
   - Third-party service credentials
   - DNS configuration
   - SSL certificates

4. **Contact Information**
   - On-call engineer
   - System administrator
   - Escalation contacts

### Pre-Disaster Checklist

- [ ] Automated backups running successfully
- [ ] Backup monitoring alerts configured
- [ ] Off-site backups enabled and verified
- [ ] Restore procedures tested quarterly
- [ ] Documentation reviewed and up-to-date
- [ ] Team trained on recovery procedures
- [ ] Emergency contact list current

## Disaster Scenarios

### Scenario 1: Complete System Failure

**Symptoms:**
- Server unresponsive
- Cannot SSH to server
- Application completely down
- Database inaccessible

**Recovery Procedure:**

1. **Assess Situation** (5 minutes)
   ```bash
   # Try to ping the server
   ping your-server.com
   
   # Check cloud console/hosting panel
   # Verify if server is running
   ```

2. **Provision New Server** (15-30 minutes)
   ```bash
   # If server is unrecoverable, provision new server
   # Use same specifications or better
   # Follow initial setup from docs/ops/production-deploy.md
   ```

3. **Install Dependencies** (15 minutes)
   ```bash
   sudo apt update && sudo apt upgrade -y
   sudo apt install -y nginx php8.2-fpm php8.2-cli php8.2-curl \
       php8.2-mbstring php8.2-xml php8.2-pgsql php8.2-sqlite3 \
       postgresql postgresql-contrib git unzip
   ```

4. **Clone Repository** (5 minutes)
   ```bash
   cd /var/www
   git clone https://github.com/your-org/gpt-chatbot-boilerplate.git chatbot
   cd chatbot
   composer install --no-dev
   ```

5. **Retrieve Latest Backup** (10 minutes)
   ```bash
   # From off-site location
   # Example for rsync:
   rsync -avz backup-server:/backups/chatbot/full_backup_*.tar.gz /tmp/
   
   # Example for S3:
   aws s3 cp s3://my-bucket/chatbot-backups/ /tmp/ --recursive --exclude "*" --include "full_backup_*.tar.gz"
   
   # Get the latest backup
   LATEST_BACKUP=$(ls -t /tmp/full_backup_*.tar.gz | head -1)
   ```

6. **Restore Complete System** (20-40 minutes)
   ```bash
   cd /var/www/chatbot
   ./scripts/restore_all.sh "$LATEST_BACKUP"
   ```

7. **Verify Configuration** (10 minutes)
   ```bash
   # Review .env file
   nano .env
   
   # Ensure critical variables are set:
   # - OPENAI_API_KEY
   # - ADMIN_TOKEN
   # - Database connection
   
   # Verify database connectivity
   php -r "require 'config.php'; echo 'Config loaded successfully\n';"
   ```

8. **Configure Web Server** (10 minutes)
   ```bash
   # Copy nginx configuration
   sudo cp docs/ops/nginx-production.conf /etc/nginx/sites-available/chatbot
   sudo ln -s /etc/nginx/sites-available/chatbot /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl restart nginx
   ```

9. **Set File Permissions** (5 minutes)
   ```bash
   sudo chown -R www-data:www-data /var/www/chatbot
   sudo chmod -R 755 /var/www/chatbot
   sudo chmod -R 770 /var/www/chatbot/data
   sudo chmod 600 /var/www/chatbot/.env
   ```

10. **Start Services** (5 minutes)
    ```bash
    # Start PHP-FPM
    sudo systemctl restart php8.2-fpm
    
    # Start background worker
    sudo systemctl restart chatbot-worker
    
    # Verify services are running
    sudo systemctl status php8.2-fpm
    sudo systemctl status nginx
    sudo systemctl status chatbot-worker
    ```

11. **Verify System Health** (10 minutes)
    ```bash
    # Test web server
    curl -I http://localhost/
    
    # Test admin API
    curl -H "Authorization: Bearer $ADMIN_TOKEN" http://localhost/admin-api.php?action=health
    
    # Test database
    sqlite3 /var/www/chatbot/data/admin.db "SELECT COUNT(*) FROM agents;"
    # OR for PostgreSQL:
    psql $ADMIN_DATABASE_URL -c "SELECT COUNT(*) FROM agents;"
    
    # Test chat functionality
    curl -X POST http://localhost/chat-unified.php \
      -H "Content-Type: application/json" \
      -d '{"message":"Hello","conversation_id":"test-123"}'
    ```

12. **Restore DNS/SSL** (15 minutes)
    ```bash
    # Update DNS to point to new server IP
    # Wait for DNS propagation (can take up to 1 hour)
    
    # Install SSL certificate
    sudo certbot --nginx -d your-domain.com
    ```

13. **Monitor and Validate** (30 minutes)
    ```bash
    # Monitor logs for errors
    tail -f /var/log/chatbot/application.log
    
    # Monitor metrics
    curl http://localhost/metrics.php
    
    # Test critical workflows:
    # - User chat interactions
    # - Admin API operations
    # - Agent functionality
    # - File uploads (if enabled)
    ```

**Total Estimated Recovery Time: 2-4 hours**

### Scenario 2: Database Corruption

**Symptoms:**
- Database integrity errors
- SQL syntax errors
- Data inconsistencies
- Application crashes on database queries

**Recovery Procedure:**

1. **Stop Application** (2 minutes)
   ```bash
   sudo systemctl stop nginx
   sudo systemctl stop chatbot-worker
   ```

2. **Backup Current State** (5 minutes)
   ```bash
   # Even if corrupted, backup for forensics
   ./scripts/db_backup.sh
   mv /data/backups/admin_*.gz /data/backups/corrupted_$(date +%Y%m%d_%H%M%S).gz
   ```

3. **Identify Latest Good Backup** (5 minutes)
   ```bash
   # List available backups
   ls -lth /data/backups/admin_*.gz
   
   # Choose the most recent backup before corruption occurred
   BACKUP_FILE="/data/backups/admin_sqlite_20251104_120000.db.gz"
   ```

4. **Restore Database** (5 minutes)
   ```bash
   ./scripts/db_restore.sh "$BACKUP_FILE"
   ```

5. **Verify Database Integrity** (5 minutes)
   ```bash
   # SQLite
   sqlite3 data/admin.db "PRAGMA integrity_check;"
   sqlite3 data/admin.db "SELECT COUNT(*) FROM agents;"
   
   # PostgreSQL
   psql $ADMIN_DATABASE_URL -c "SELECT version();"
   psql $ADMIN_DATABASE_URL -c "SELECT COUNT(*) FROM agents;"
   ```

6. **Restart Application** (2 minutes)
   ```bash
   sudo systemctl start nginx
   sudo systemctl start chatbot-worker
   ```

7. **Verify Functionality** (10 minutes)
   ```bash
   # Test admin API
   curl -H "Authorization: Bearer $ADMIN_TOKEN" \
     http://localhost/admin-api.php?action=list_agents
   
   # Test chat
   curl -X POST http://localhost/chat-unified.php \
     -H "Content-Type: application/json" \
     -d '{"message":"Test","conversation_id":"test-dr"}'
   ```

8. **Assess Data Loss** (15 minutes)
   - Compare backup timestamp with current time
   - Identify missing data since last backup
   - Communicate data loss to stakeholders
   - Document incident for post-mortem

**Total Estimated Recovery Time: 45 minutes - 1 hour**

### Scenario 3: Accidental Data Deletion

**Symptoms:**
- User reports missing agents, prompts, or data
- Database queries return fewer results than expected
- Audit logs show delete operations

**Recovery Procedure:**

1. **Assess Impact** (10 minutes)
   ```bash
   # Check audit logs
   sqlite3 data/admin.db "SELECT * FROM audit_events WHERE action LIKE '%delete%' ORDER BY created_at DESC LIMIT 20;"
   
   # Determine what was deleted and when
   # Identify affected records
   ```

2. **Stop Further Changes** (2 minutes)
   ```bash
   # Temporarily disable write access
   # Put application in read-only mode if possible
   ```

3. **Create Safety Backup** (5 minutes)
   ```bash
   ./scripts/db_backup.sh
   ```

4. **Identify Recovery Point** (10 minutes)
   ```bash
   # Find backup from before deletion
   ls -lth /data/backups/
   
   # Choose backup that contains the deleted data
   ```

5. **Selective Restore** (20-30 minutes)
   
   For SQLite:
   ```bash
   # Extract deleted records from backup
   BACKUP_FILE="/data/backups/admin_sqlite_20251104_020000.db.gz"
   gunzip -c "$BACKUP_FILE" > /tmp/backup.db
   
   # Query specific records from backup
   sqlite3 /tmp/backup.db "SELECT * FROM agents WHERE id IN (1, 2, 3);" > /tmp/deleted_records.sql
   
   # Manually restore specific records
   sqlite3 data/admin.db < /tmp/deleted_records.sql
   
   # Clean up
   rm /tmp/backup.db /tmp/deleted_records.sql
   ```
   
   For PostgreSQL:
   ```bash
   # Restore to temporary database
   gunzip -c "$BACKUP_FILE" > /tmp/backup.sql
   createdb chatbot_recovery
   psql chatbot_recovery < /tmp/backup.sql
   
   # Extract deleted records
   psql chatbot_recovery -c "COPY (SELECT * FROM agents WHERE id IN (1,2,3)) TO '/tmp/deleted_records.csv' CSV HEADER;"
   
   # Import to production
   psql $ADMIN_DATABASE_URL -c "COPY agents FROM '/tmp/deleted_records.csv' CSV HEADER;"
   
   # Clean up
   dropdb chatbot_recovery
   rm /tmp/backup.sql /tmp/deleted_records.csv
   ```

6. **Verify Restoration** (10 minutes)
   ```bash
   # Verify restored data
   sqlite3 data/admin.db "SELECT * FROM agents WHERE id IN (1, 2, 3);"
   
   # Test functionality
   curl -H "Authorization: Bearer $ADMIN_TOKEN" \
     http://localhost/admin-api.php?action=get_agent&id=1
   ```

7. **Document and Review** (15 minutes)
   - Document what was deleted and restored
   - Review deletion permissions
   - Consider implementing soft deletes
   - Update procedures to prevent recurrence

**Total Estimated Recovery Time: 1-2 hours**

### Scenario 4: Configuration Error

**Symptoms:**
- Application not starting
- Authentication failures
- API connection errors
- Misconfigured features

**Recovery Procedure:**

1. **Identify Configuration Issue** (5 minutes)
   ```bash
   # Check application logs
   tail -100 /var/log/chatbot/application.log
   
   # Test configuration syntax
   php -l config.php
   php -r "require 'config.php'; print_r(\$config);"
   ```

2. **Restore Configuration from Backup** (5 minutes)
   ```bash
   # Get latest backup
   LATEST_BACKUP=$(ls -t /data/backups/full_backup_*.tar.gz | head -1)
   
   # Extract only configuration files
   mkdir -p /tmp/restore
   tar -xzf "$LATEST_BACKUP" -C /tmp/restore
   
   # Find config directory
   CONFIG_DIR=$(find /tmp/restore -name "config" -type d | head -1)
   
   # Restore specific config files
   cp "$CONFIG_DIR/.env" /var/www/chatbot/.env.restored
   cp "$CONFIG_DIR/config.php" /var/www/chatbot/config.php.restored
   
   # Review before applying
   diff .env .env.restored
   
   # Apply if correct
   mv .env.restored .env
   mv config.php.restored config.php
   ```

3. **Verify Configuration** (5 minutes)
   ```bash
   # Test configuration
   php -r "require 'config.php'; echo 'Config valid\n';"
   
   # Verify critical values
   grep -E "OPENAI_API_KEY|ADMIN_TOKEN|DATABASE" .env
   ```

4. **Restart Services** (2 minutes)
   ```bash
   sudo systemctl restart php8.2-fpm
   sudo systemctl restart nginx
   ```

5. **Test Functionality** (10 minutes)
   ```bash
   # Health check
   curl http://localhost/admin-api.php?action=health \
     -H "Authorization: Bearer $ADMIN_TOKEN"
   
   # Test chat
   curl -X POST http://localhost/chat-unified.php \
     -H "Content-Type: application/json" \
     -d '{"message":"Test","conversation_id":"test"}'
   ```

**Total Estimated Recovery Time: 30 minutes**

### Scenario 5: Server Compromise / Security Breach

**Symptoms:**
- Unauthorized access detected
- Suspicious activity in logs
- Data exfiltration alerts
- Modified files

**Recovery Procedure:**

1. **Immediate Response** (5 minutes)
   ```bash
   # Isolate the system
   sudo ufw deny in
   
   # Stop application
   sudo systemctl stop nginx
   sudo systemctl stop chatbot-worker
   
   # Preserve evidence
   sudo tar -czf /tmp/forensics_$(date +%Y%m%d_%H%M%S).tar.gz \
     /var/log \
     /var/www/chatbot \
     ~/.bash_history
   ```

2. **Assess Breach Scope** (30 minutes)
   ```bash
   # Check for modified files
   find /var/www/chatbot -type f -mtime -1
   
   # Review access logs
   grep -E "POST|PUT|DELETE" /var/log/nginx/access.log | tail -100
   
   # Check for backdoors
   find /var/www/chatbot -name "*.php" -mtime -7 -exec grep -l "eval\|base64_decode\|system\|exec" {} \;
   
   # Review database for unauthorized changes
   sqlite3 data/admin.db "SELECT * FROM audit_events ORDER BY created_at DESC LIMIT 50;"
   ```

3. **Rotate All Credentials** (15 minutes)
   ```bash
   # Generate new admin token
   NEW_ADMIN_TOKEN=$(openssl rand -base64 32)
   
   # Update .env with new credentials
   # - New ADMIN_TOKEN
   # - New database password
   # - Rotate OpenAI API key (if compromised)
   ```

4. **Clean Installation** (Follow Scenario 1)
   - Provision new server
   - Fresh installation from repository
   - Restore data from **pre-breach** backup
   - Do NOT restore configuration files (may contain backdoors)

5. **Security Hardening** (30 minutes)
   ```bash
   # Update all packages
   sudo apt update && sudo apt upgrade -y
   
   # Configure firewall
   sudo ufw default deny incoming
   sudo ufw default allow outgoing
   sudo ufw allow 22/tcp
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw enable
   
   # Enable fail2ban
   sudo apt install fail2ban
   sudo systemctl enable fail2ban
   
   # Review SSH configuration
   sudo nano /etc/ssh/sshd_config
   # Ensure: PermitRootLogin no, PasswordAuthentication no
   ```

6. **Notify Stakeholders** (Immediate)
   - Inform management
   - Notify affected users
   - Report to authorities if required
   - Document breach timeline

7. **Post-Breach Actions**
   - Complete security audit
   - Review access logs
   - Update security procedures
   - Implement additional monitoring
   - Conduct post-mortem

**Total Estimated Recovery Time: 4-8 hours**

## Testing and Validation

### Quarterly DR Drills

Conduct disaster recovery drills at least quarterly to ensure procedures work and team is prepared.

#### DR Drill Checklist

1. **Preparation** (1 week before)
   - [ ] Schedule drill with team
   - [ ] Identify scenario to test
   - [ ] Ensure recent backups exist
   - [ ] Prepare staging environment
   - [ ] Document current system state

2. **Execution** (2-4 hours)
   - [ ] Announce drill start time
   - [ ] Follow DR runbook procedures
   - [ ] Document any issues encountered
   - [ ] Time each step
   - [ ] Verify full functionality

3. **Validation** (1 hour)
   - [ ] Verify all data restored correctly
   - [ ] Test critical workflows
   - [ ] Check data integrity
   - [ ] Confirm RTO/RPO met
   - [ ] Document results

4. **Post-Drill** (1 week after)
   - [ ] Conduct debrief meeting
   - [ ] Update runbook based on findings
   - [ ] Address any issues discovered
   - [ ] Share results with stakeholders
   - [ ] Schedule next drill

### Automated Testing

Run automated restore tests monthly:

```bash
#!/bin/bash
# Monthly automated restore test

# Create test backup
./scripts/backup_all.sh

# Get latest backup
LATEST_BACKUP=$(ls -t /data/backups/full_backup_*.tar.gz | head -1)

# Restore to staging environment
ssh staging-server "cd /var/www/chatbot && ./scripts/restore_all.sh $LATEST_BACKUP"

# Verify staging environment
ssh staging-server "cd /var/www/chatbot && ./scripts/smoke_test.sh"

# Report results
if [ $? -eq 0 ]; then
  echo "✅ Restore test passed"
else
  echo "❌ Restore test failed - requires investigation"
  # Send alert
fi
```

## Backup Verification

### Daily Backup Verification

Automated monitoring runs hourly (see `scripts/monitor_backups.sh`):
- Verifies backup exists
- Checks backup age
- Validates archive integrity
- Monitors disk space
- Sends alerts on issues

### Manual Verification (Weekly)

```bash
# 1. List recent backups
ls -lth /data/backups/

# 2. Test archive integrity
tar -tzf /data/backups/full_backup_latest.tar.gz > /dev/null

# 3. Verify backup size (should be consistent)
du -h /data/backups/full_backup_*.tar.gz | tail -5

# 4. Check backup manifest
tar -xzf /data/backups/full_backup_latest.tar.gz -O */MANIFEST.txt

# 5. Test database restoration in staging
./scripts/db_restore.sh /data/backups/admin_sqlite_latest.db.gz
```

## Off-Site Backup Procedures

### Rsync to Remote Server

```bash
# Setup (one-time)
ssh-keygen -t ed25519 -f ~/.ssh/backup_key
ssh-copy-id -i ~/.ssh/backup_key backup@backup-server

# Automated sync (add to cron)
rsync -avz --delete \
  -e "ssh -i ~/.ssh/backup_key" \
  /data/backups/ \
  backup@backup-server:/backups/chatbot/
```

### AWS S3 Sync

```bash
# Setup (one-time)
pip install awscli
aws configure

# Automated sync (add to cron)
aws s3 sync /data/backups/ s3://my-bucket/chatbot-backups/ \
  --storage-class STANDARD_IA \
  --exclude "*" \
  --include "full_backup_*.tar.gz"

# Set lifecycle policy for cost optimization
# - Move to Glacier after 30 days
# - Delete after 365 days
```

### Azure Blob Storage

```bash
# Setup (one-time)
pip install azure-cli
az login

# Automated sync (add to cron)
az storage blob upload-batch \
  --account-name mystorageaccount \
  --destination chatbot-backups \
  --source /data/backups/ \
  --pattern "full_backup_*.tar.gz"
```

## Escalation Contacts

| Role | Contact | Escalation Level |
|------|---------|------------------|
| On-Call Engineer | [EMAIL/PHONE] | Level 1 |
| System Administrator | [EMAIL/PHONE] | Level 2 |
| Technical Lead | [EMAIL/PHONE] | Level 3 |
| VP Engineering | [EMAIL/PHONE] | Executive |

## Post-Disaster Actions

After any disaster recovery:

1. **Document the Incident**
   - What happened
   - When it was detected
   - How it was resolved
   - Actual RTO/RPO achieved
   - Data loss incurred

2. **Conduct Post-Mortem**
   - Root cause analysis
   - Timeline of events
   - What went well
   - What went poorly
   - Action items for improvement

3. **Update Procedures**
   - Update runbook with lessons learned
   - Fix any gaps in procedures
   - Update automation scripts
   - Improve monitoring

4. **Communicate**
   - Inform stakeholders of resolution
   - Provide transparency on data loss
   - Explain preventive measures
   - Set expectations for future

## Compliance and Audit

### Documentation Requirements

Maintain records of:
- All backup jobs (success/failure)
- DR drill results
- Actual disaster recoveries
- RTO/RPO metrics
- Changes to procedures

### Compliance Standards

Ensure backup procedures meet:
- GDPR (data protection)
- SOC 2 (system reliability)
- HIPAA (if applicable)
- Industry-specific requirements

## References

- [Backup & Restore Guide](backup_restore.md) - General backup procedures
- [Multi-Tenant Backup & DR](multi_tenant_backup_dr.md) - Tenant-specific procedures
- [Production Deployment Guide](production-deploy.md)
- [Incident Response Runbook](incident_runbook.md)
- [Monitoring Guide](monitoring/README.md)
- [Secrets Management](secrets_management.md)

## Revision History

| Date | Version | Changes | Author |
|------|---------|---------|--------|
| 2025-11-06 | 1.0 | Initial disaster recovery runbook | System |
| 2025-11-07 | 1.1 | Added multi-tenant backup & DR reference | System |

## Approval

This runbook should be reviewed and approved by:
- [ ] Technical Lead
- [ ] Operations Manager
- [ ] Security Team
- [ ] Compliance Officer

**Next Review Date:** [DATE + 90 days]
