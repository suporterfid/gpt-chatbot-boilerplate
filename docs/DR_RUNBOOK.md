# Disaster Recovery Runbook

## Overview

This runbook provides step-by-step procedures for recovering from various disaster scenarios affecting the GPT Chatbot Boilerplate platform.

**Recovery Time Objective (RTO)**: ≤ 60 minutes  
**Recovery Point Objective (RPO)**: ≤ 15 minutes  
**Last Updated**: 2025-11-08  
**Version**: 1.0

## Table of Contents

1. [Emergency Contacts](#emergency-contacts)
2. [Backup Strategy](#backup-strategy)
3. [Recovery Scenarios](#recovery-scenarios)
4. [Step-by-Step Recovery Procedures](#step-by-step-recovery-procedures)
5. [Validation & Testing](#validation--testing)
6. [Post-Recovery Actions](#post-recovery-actions)

---

## Emergency Contacts

| Role | Name | Contact | Escalation |
|------|------|---------|------------|
| Primary On-Call Engineer | TBD | TBD | Immediate |
| Secondary On-Call | TBD | TBD | If primary unavailable |
| DevOps Lead | TBD | TBD | For infrastructure issues |
| Database Administrator | TBD | TBD | For data corruption |
| Security Lead | TBD | TBD | For security incidents |
| Product Manager | TBD | TBD | For business decisions |

**Escalation Path**: On-Call → Lead → Director → VP Engineering

---

## Backup Strategy

### Automated Backups

**Location**: `/data/backups/` (configurable via `BACKUP_DIR`)

**Schedule**:
- **Full Backups**: Daily at 2:00 AM UTC
- **Incremental Backups**: Every 6 hours
- **Database Dumps**: Hourly
- **Configuration Backups**: On change + daily

**Retention Policy**:
- Daily backups: 7 days
- Weekly backups: 4 weeks
- Monthly backups: 12 months
- Critical snapshots: Indefinite

### Backup Components

1. **Database** (Critical - RPO: 15 min)
   - SQLite/MySQL database files
   - Full dump + incremental logs
   - Stored: Local + Offsite

2. **File Storage** (Critical - RPO: 1 hour)
   - Uploaded files
   - Agent configurations
   - Vector stores
   - Stored: Local + S3/GCS

3. **Configuration** (Critical - RPO: On-change)
   - `.env` file
   - `config.php`
   - Nginx/Apache configs
   - SSL certificates
   - Stored: Version control + encrypted backup

4. **Logs** (Important - RPO: 24 hours)
   - Application logs
   - Access logs
   - Audit trails
   - Stored: Loki/S3

5. **Metrics** (Nice-to-have - RPO: 1 hour)
   - Prometheus data
   - Grafana dashboards
   - Stored: Remote write storage

### Backup Verification

**Automated Checks** (every backup):
- ✓ File size validation (not zero, within expected range)
- ✓ Checksum verification (SHA256)
- ✓ Compression integrity check
- ✓ Metadata file creation

**Manual Validation** (weekly):
- □ Test restore to staging environment
- □ Data integrity validation
- □ Application functionality verification
- □ Performance benchmark

---

## Recovery Scenarios

### Scenario 1: Complete Infrastructure Loss (RTO: 60 min)
**Trigger**: Data center failure, total server loss  
**Impact**: Complete service outage  
**Priority**: P0 - Critical

### Scenario 2: Database Corruption (RTO: 30 min)
**Trigger**: Database file corruption, disk failure  
**Impact**: Data read/write failures  
**Priority**: P0 - Critical

### Scenario 3: Application Failure (RTO: 15 min)
**Trigger**: Code bug, configuration error  
**Impact**: HTTP 500 errors, service degradation  
**Priority**: P1 - High

### Scenario 4: Data Loss/Deletion (RTO: 45 min)
**Trigger**: Accidental deletion, malicious activity  
**Impact**: Specific tenant data loss  
**Priority**: P1 - High

### Scenario 5: Security Breach (RTO: Variable)
**Trigger**: Unauthorized access, data breach  
**Impact**: Potential data exposure  
**Priority**: P0 - Critical

---

## Step-by-Step Recovery Procedures

### Scenario 1: Complete Infrastructure Loss

#### Phase 1: Assessment (5 minutes)

1. **Confirm Disaster Scope**
   ```bash
   # Check service availability
   curl -I https://your-domain.com/health
   
   # Check database connectivity
   mysql -h your-db-host -u root -p -e "SELECT 1"
   
   # Check backup availability
   ls -lh /data/backups/ | tail -20
   ```

2. **Declare Disaster Recovery**
   - Notify team via emergency channel
   - Update status page: "Major outage - Recovery in progress"
   - Log incident in ticketing system

#### Phase 2: Infrastructure Provisioning (15 minutes)

1. **Provision New Infrastructure**
   
   **Using Terraform** (if available):
   ```bash
   cd terraform/
   terraform init
   terraform plan -out=disaster-recovery.tfplan
   terraform apply disaster-recovery.tfplan
   ```
   
   **Using Docker** (quick recovery):
   ```bash
   # On new server
   docker-compose -f docker-compose.yml up -d
   ```

2. **Verify Network Connectivity**
   ```bash
   # Test DNS
   nslookup your-domain.com
   
   # Test SSL certificates
   openssl s_client -connect your-domain.com:443
   ```

#### Phase 3: Data Restoration (30 minutes)

1. **Identify Latest Valid Backup**
   ```bash
   # List available backups
   ./scripts/backup_all.sh --list
   
   # Verify backup integrity
   sha256sum /data/backups/latest/backup.tar.gz
   cat /data/backups/latest/backup.sha256
   ```

2. **Restore Database**
   ```bash
   # Stop application services
   docker-compose down
   
   # Restore database
   ./scripts/db_restore.sh /data/backups/latest/database.sql.gz
   
   # Verify restoration
   sqlite3 /data/database.sqlite "SELECT COUNT(*) FROM tenants;"
   ```

3. **Restore Files**
   ```bash
   # Restore uploaded files
   tar -xzf /data/backups/latest/files.tar.gz -C /data/
   
   # Restore configuration
   tar -xzf /data/backups/latest/config.tar.gz -C /
   
   # Verify permissions
   chown -R www-data:www-data /data/uploads
   chmod -R 755 /data/uploads
   ```

4. **Restore Vector Stores**
   ```bash
   # Restore vector store data
   ./scripts/restore_all.sh --component vector-stores
   
   # Verify vector store integrity
   php scripts/verify_vector_stores.php
   ```

#### Phase 4: Service Validation (10 minutes)

1. **Start Services**
   ```bash
   # Start database
   docker-compose up -d db
   sleep 10
   
   # Start application
   docker-compose up -d app
   
   # Start workers
   docker-compose up -d worker
   ```

2. **Run Smoke Tests**
   ```bash
   # Test database connectivity
   php tests/test_db_connection.php
   
   # Test admin API
   ./scripts/smoke_test.sh
   
   # Test chat endpoint
   curl -X POST https://your-domain.com/chat-unified.php \
     -H "Content-Type: application/json" \
     -d '{"message":"test","conversation_id":"smoke-test"}'
   ```

3. **Verify Data Integrity**
   ```bash
   # Check tenant count
   php -r 'require "includes/DB.php"; $db = DB::getInstance(); 
           echo $db->query("SELECT COUNT(*) as c FROM tenants")[0]["c"];'
   
   # Check agent count
   php -r 'require "includes/DB.php"; $db = DB::getInstance(); 
           echo $db->query("SELECT COUNT(*) as c FROM agents")[0]["c"];'
   
   # Verify audit logs
   php tests/test_audit_service.php
   ```

---

### Scenario 2: Database Corruption

#### Phase 1: Assessment (5 minutes)

1. **Identify Corruption**
   ```bash
   # Check database integrity
   sqlite3 /data/database.sqlite "PRAGMA integrity_check;"
   # OR for MySQL
   mysqlcheck -u root -p --all-databases
   ```

2. **Estimate Data Loss Window**
   ```bash
   # Check last successful backup
   ls -lht /data/backups/ | head -5
   
   # Check application logs for last successful writes
   grep "INSERT\|UPDATE" logs/chatbot.log | tail -20
   ```

#### Phase 2: Isolation (5 minutes)

1. **Stop Write Operations**
   ```bash
   # Enable maintenance mode
   touch /var/www/html/.maintenance
   
   # Stop workers to prevent background writes
   docker-compose stop worker
   
   # Stop application
   docker-compose stop app
   ```

2. **Backup Corrupted Database**
   ```bash
   # Preserve corrupted state for forensics
   cp /data/database.sqlite /data/corrupted_$(date +%Y%m%d_%H%M%S).sqlite
   ```

#### Phase 3: Recovery (15 minutes)

1. **Restore from Backup**
   ```bash
   # Restore latest valid backup
   ./scripts/db_restore.sh /data/backups/hourly/latest.sql.gz
   ```

2. **Replay Transactions** (if applicable)
   ```bash
   # If using MySQL with binary logs
   mysqlbinlog /var/log/mysql/mysql-bin.000001 \
     --start-datetime="2025-11-08 12:00:00" \
     --stop-datetime="2025-11-08 14:00:00" \
     | mysql -u root -p
   ```

3. **Verify Restoration**
   ```bash
   # Run integrity check
   php tests/test_db_integrity.php
   
   # Compare record counts
   php scripts/compare_backup_counts.php
   ```

#### Phase 4: Resume Operations (5 minutes)

1. **Restart Services**
   ```bash
   docker-compose up -d app worker
   rm /var/www/html/.maintenance
   ```

2. **Monitor for Issues**
   ```bash
   # Watch logs
   tail -f logs/chatbot.log
   
   # Monitor metrics
   curl http://localhost:9090/metrics | grep error
   ```

---

### Scenario 3: Application Failure

#### Quick Recovery (5-10 minutes)

1. **Rollback Deployment**
   ```bash
   # Rollback to previous version
   git checkout <previous-stable-tag>
   docker-compose up -d --build
   ```

2. **Clear Application Cache**
   ```bash
   rm -rf /tmp/chatbot_*
   docker-compose restart app
   ```

3. **Restart Services**
   ```bash
   docker-compose restart app worker
   ```

---

### Scenario 4: Data Loss/Deletion

#### Targeted Recovery (30-45 minutes)

1. **Identify Affected Tenant**
   ```bash
   # Find tenant ID
   php scripts/find_tenant.php --email="customer@example.com"
   ```

2. **Restore Tenant Data**
   ```bash
   # Restore specific tenant from backup
   ./scripts/restore_tenant_data.sh --tenant-id=123 \
     --backup=/data/backups/daily/20251108_020000/
   ```

3. **Verify Restoration**
   ```bash
   # Check tenant data
   php scripts/verify_tenant_data.php --tenant-id=123
   ```

---

### Scenario 5: Security Breach

#### Immediate Actions (0-15 minutes)

1. **Isolate Affected Systems**
   ```bash
   # Block all traffic except from known IPs
   iptables -A INPUT -s <trusted-ip> -j ACCEPT
   iptables -A INPUT -j DROP
   
   # OR disable application entirely
   docker-compose down
   ```

2. **Preserve Evidence**
   ```bash
   # Capture current state
   tar -czf /tmp/forensics_$(date +%Y%m%d_%H%M%S).tar.gz \
     /data/database.sqlite \
     /var/log/ \
     /tmp/chatbot_*
   
   # Copy to secure location
   scp /tmp/forensics_*.tar.gz forensics-server:/secure/
   ```

3. **Notify Security Team**
   - Follow incident response plan
   - Contact legal/compliance if data exposure suspected

#### Recovery Actions (15-60 minutes)

1. **Rotate All Credentials**
   ```bash
   # Rotate API keys
   php scripts/rotate_all_keys.php
   
   # Update database passwords
   php scripts/update_db_password.php
   
   # Regenerate JWT secrets
   php scripts/regenerate_secrets.php
   ```

2. **Apply Security Patches**
   ```bash
   # Pull latest security patches
   git pull origin main
   
   # Rebuild with security updates
   docker-compose up -d --build
   ```

3. **Restore Clean State**
   ```bash
   # If compromise is severe, restore from known-clean backup
   ./scripts/restore_all.sh --backup=/data/backups/pre-breach/
   ```

---

## Validation & Testing

### Post-Recovery Validation Checklist

After any recovery, complete this checklist:

#### Application Health
- [ ] Application responds to HTTP requests (200 OK)
- [ ] Admin API accessible and functional
- [ ] Chat endpoint accepting and processing messages
- [ ] WebSocket connections working (if applicable)
- [ ] Static assets loading correctly

#### Data Integrity
- [ ] Tenant count matches expected
- [ ] Agent configurations intact
- [ ] Conversation history accessible
- [ ] Audit logs present and sequential
- [ ] No orphaned records (foreign key integrity)

#### Service Integration
- [ ] OpenAI API integration working
- [ ] WhatsApp webhook receiving messages
- [ ] Background workers processing jobs
- [ ] Metrics collection operational
- [ ] Logging to centralized system

#### Security
- [ ] All API endpoints require authentication
- [ ] Rate limiting functional
- [ ] Tenant isolation enforced
- [ ] SSL certificates valid
- [ ] Security headers present

#### Performance
- [ ] Response times within acceptable range (<1s P95)
- [ ] Database query performance normal
- [ ] No memory leaks detected
- [ ] CPU usage normal (<70%)

### DR Test Schedule

**Quarterly Full DR Test**:
- Schedule: First Saturday of each quarter, 2:00 AM UTC
- Duration: 4 hours
- Environment: Staging (mirror of production)
- Participants: On-call engineer + DBA + DevOps lead

**Monthly Partial Test**:
- Database restore only
- Verify data integrity
- Test transaction replay

**Weekly Backup Verification**:
- Automated backup integrity check
- Sample file restoration
- Checksum validation

### Test Report Template

```markdown
# DR Test Report - [Date]

## Test Details
- **Date**: YYYY-MM-DD
- **Duration**: HH:MM
- **Scenario**: [Complete Loss / Database Corruption / etc.]
- **Tester**: [Name]

## Objectives
- [ ] Verify backup integrity
- [ ] Test restoration procedures
- [ ] Measure RTO/RPO
- [ ] Validate runbook accuracy

## Results
- **RTO Achieved**: XX minutes (Target: 60 min)
- **RPO Achieved**: XX minutes (Target: 15 min)
- **Data Loss**: [None / XX records]
- **Issues Found**: [List any problems]

## Action Items
1. [Issue 1] - [Owner] - [Due Date]
2. [Issue 2] - [Owner] - [Due Date]

## Recommendations
- [Recommendation 1]
- [Recommendation 2]

## Sign-off
- Tester: _________________ Date: _______
- Reviewer: _______________ Date: _______
```

---

## Post-Recovery Actions

### Immediate (Within 1 hour)

1. **Update Status Page**
   - Change status to "Operational" or "Degraded Performance"
   - Provide brief explanation of incident

2. **Notify Stakeholders**
   - Email all affected customers
   - Post on social media (if appropriate)
   - Update internal team via Slack/Teams

3. **Monitor Closely**
   ```bash
   # Watch error rates
   watch -n 5 'curl -s http://localhost:9090/metrics | grep error'
   
   # Monitor database performance
   watch -n 10 'mysql -e "SHOW PROCESSLIST"'
   ```

### Short-term (Within 24 hours)

1. **Conduct Incident Review**
   - Schedule post-mortem meeting
   - Document timeline of events
   - Identify root cause

2. **Update Documentation**
   - Refine runbook based on learnings
   - Update contact information if needed
   - Document any deviations from plan

3. **Improve Monitoring**
   - Add alerts for detected gaps
   - Enhance metrics collection
   - Configure better early warning

### Long-term (Within 1 week)

1. **Implement Preventive Measures**
   - Apply fixes to prevent recurrence
   - Enhance backup frequency if needed
   - Improve high availability setup

2. **Review DR Strategy**
   - Assess if RTO/RPO targets are appropriate
   - Consider multi-region deployment
   - Evaluate backup storage redundancy

3. **Train Team**
   - Conduct DR training session
   - Update on-call rotation
   - Practice specific recovery procedures

---

## Appendix

### Useful Commands Reference

```bash
# Check backup status
./scripts/monitor_backups.sh

# List available backups
ls -lht /data/backups/ | head -20

# Verify backup integrity
./scripts/verify_backup.sh /data/backups/latest/

# Restore specific component
./scripts/restore_all.sh --component=database --backup=/path/to/backup

# Check service health
docker-compose ps
docker-compose logs --tail=100 app

# Database queries
sqlite3 /data/database.sqlite ".tables"
sqlite3 /data/database.sqlite "SELECT * FROM tenants;"

# Verify tenant isolation
php tests/test_multitenancy.php

# Run smoke tests
./scripts/smoke_test.sh
```

### Configuration Files Backup

Critical files to backup (already included in automated backups):
- `/var/www/html/.env`
- `/var/www/html/config.php`
- `/etc/nginx/sites-available/chatbot`
- `/etc/ssl/certs/chatbot.crt`
- `/etc/ssl/private/chatbot.key`
- `/etc/systemd/system/chatbot-*.service`

### Offsite Backup Configuration

```bash
# Configure S3 for offsite backups
export OFFSITE_DESTINATION="s3://your-bucket/backups/"
export AWS_ACCESS_KEY_ID="your-key"
export AWS_SECRET_ACCESS_KEY="your-secret"

# Run backup with offsite copy
./scripts/backup_all.sh --offsite
```

---

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-08 | System | Initial creation |

**Review Cycle**: Quarterly  
**Next Review**: 2026-02-08  
**Owner**: DevOps Team
