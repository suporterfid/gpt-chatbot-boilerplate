# Operations Guide - GPT Chatbot Boilerplate

## Overview

This guide consolidates all operational procedures for running and maintaining the GPT Chatbot Boilerplate platform in production. It is the authoritative reference for SRE, DevOps, and support teams.

**Target Audience**: Operations engineers, SREs, DevOps teams, on-call engineers  
**Last Updated**: 2025-11-08  
**Version**: 1.0

---

## Table of Contents

1. [Daily Operations](#daily-operations)
2. [Monitoring & Alerting](#monitoring--alerting)
3. [Backup & Recovery](#backup--recovery)
4. [Scaling & Capacity](#scaling--capacity)
5. [Security Operations](#security-operations)
6. [Incident Response](#incident-response)
7. [Maintenance Procedures](#maintenance-procedures)
8. [Troubleshooting](#troubleshooting)

---

## Daily Operations

### Morning Checklist (5 minutes)

```bash
# 1. Check service health
docker-compose ps
# All services should show "Up" status

# 2. Verify backup completion
./scripts/monitor_backups.sh
# Last backup should be < 24 hours old

# 3. Check error rates
curl -s http://localhost:9090/metrics | grep chatbot_api_errors_total

# 4. Review alerts
curl -s http://localhost:9093/api/v1/alerts | jq '.data[] | select(.state=="firing")'

# 5. Check job queue health
php -r 'require "includes/DB.php"; $db = DB::getInstance();
        echo "Pending: " . $db->query("SELECT COUNT(*) as c FROM jobs WHERE status=\"pending\"")[0]["c"] . "\n";
        echo "Failed: " . $db->query("SELECT COUNT(*) as c FROM jobs WHERE status=\"failed\"")[0]["c"] . "\n";'
```

### Key Metrics to Monitor

| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| API Error Rate | >2% | >5% | Check logs, investigate errors |
| Response Time P95 | >2s | >5s | Review slow queries, scale up |
| Job Queue Backlog | >50 | >200 | Scale workers, investigate failures |
| Database Size | >5GB | >10GB | Review retention, archive old data |
| Disk Usage | >70% | >85% | Clean logs, expand storage |
| Memory Usage | >75% | >90% | Investigate leaks, restart services |

### Log Locations

```bash
# Application logs
tail -f logs/chatbot.log

# Error logs
tail -f logs/error.log

# Audit logs (database)
sqlite3 /data/database.sqlite "SELECT * FROM audit_events ORDER BY timestamp DESC LIMIT 20;"

# Web server logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# System logs
journalctl -u chatbot-worker -f
```

---

## Monitoring & Alerting

### Prometheus Metrics

**Access**: http://localhost:9090

**Key Queries**:

```promql
# API request rate
rate(chatbot_api_requests_total[5m])

# Error rate
rate(chatbot_api_errors_total[5m]) / rate(chatbot_api_requests_total[5m])

# Latency P95
histogram_quantile(0.95, rate(chatbot_api_request_duration_seconds_bucket[5m]))

# OpenAI API errors
rate(chatbot_openai_errors_total[5m])

# Token usage
rate(chatbot_tokens_total[1h])

# Job queue depth
chatbot_jobs_queue_depth

# Active tenants
count(chatbot_tenant_requests_total > 0)
```

### Grafana Dashboards

**Access**: http://localhost:3000 (admin/admin)

**Available Dashboards**:
1. **Overview** - System health, API metrics, error rates
2. **Tenant Analytics** - Per-tenant usage, costs, quotas
3. **OpenAI Integration** - API calls, token usage, costs
4. **Job Queue** - Queue depth, processing rate, failures
5. **Infrastructure** - CPU, memory, disk, network

### Alert Rules

See `/observability/alerts/chatbot-alerts.yml` for complete list.

**Critical Alerts**:
- ServiceDown - Service unreachable for 1 minute
- HighErrorRate - Error rate > 5% for 5 minutes
- DatabaseSizeWarning - Database > 10GB
- JobQueueBacklog - >200 pending jobs
- OpenAIAPIFailures - OpenAI errors detected

**Response Procedures**: See [Incident Response](#incident-response)

### Log Aggregation (Loki)

**Access**: http://localhost:3100 (via Grafana)

**Useful Queries**:

```logql
# All errors in last hour
{job="chatbot"} |= "ERROR" | json

# Slow queries
{job="chatbot"} | json | duration > 1s

# Specific tenant logs
{job="chatbot"} | json | tenant_id="tenant_123"

# Failed authentication
{job="chatbot"} | json | level="warning" | message=~".*authentication failed.*"
```

---

## Backup & Recovery

### Automated Backups

**Configuration**: See `/scripts/backup.crontab`

**Schedule**:
- **Full backup**: Daily at 2:00 AM UTC
- **Incremental**: Every 6 hours
- **Database dump**: Hourly
- **Offsite sync**: Daily at 3:00 AM UTC

**Location**: `/data/backups/`

### Manual Backup

```bash
# Full backup (all components)
./scripts/backup_all.sh

# Database only
./scripts/db_backup.sh

# Specific tenant
./scripts/tenant_backup.sh --tenant-id=123

# With offsite copy
./scripts/backup_all.sh --offsite
```

### Verify Backup Integrity

```bash
# Check recent backups
./scripts/monitor_backups.sh

# Verify specific backup
sha256sum /data/backups/latest/backup.tar.gz
cat /data/backups/latest/backup.sha256

# Test restore (non-destructive)
./scripts/test_restore.sh /data/backups/latest/
```

### Restore Procedures

**Full System Restore**: See [DR_RUNBOOK.md](DR_RUNBOOK.md) - Scenario 1

**Database Restore**:
```bash
# Stop services
docker-compose down

# Restore
./scripts/db_restore.sh /data/backups/latest/database.sql.gz

# Restart
docker-compose up -d

# Verify
./scripts/smoke_test.sh
```

**Tenant Data Restore**:
```bash
./scripts/restore_tenant_data.sh --tenant-id=123 --backup=/data/backups/daily/20251108/
```

### Backup Monitoring

```bash
# Check last backup time
ls -lht /data/backups/ | head -5

# Verify backup size is reasonable
du -sh /data/backups/latest/

# Check backup logs
tail -100 /var/log/backup.log
```

---

## Scaling & Capacity

### Horizontal Scaling

#### Application Servers

```bash
# Scale up (Docker Compose)
docker-compose up -d --scale app=3

# Scale up (Kubernetes)
kubectl scale deployment chatbot-app --replicas=5

# Verify
docker-compose ps
# OR
kubectl get pods
```

#### Background Workers

```bash
# Add workers
docker-compose up -d --scale worker=5

# Check worker health
docker-compose logs worker | grep "Worker started"

# Monitor job processing rate
watch 'php -r "require \"includes/DB.php\"; \$db = DB::getInstance(); 
       echo \$db->query(\"SELECT status, COUNT(*) as c FROM jobs GROUP BY status\");"'
```

### Vertical Scaling

**Update docker-compose.yml**:

```yaml
services:
  app:
    deploy:
      resources:
        limits:
          cpus: '2.0'  # Increase from 1.0
          memory: 4G    # Increase from 2G
        reservations:
          memory: 2G
```

Then restart:
```bash
docker-compose up -d
```

### Database Scaling

#### SQLite Limitations
- Single writer limitation
- Recommended for <1000 req/min
- Consider migration to MySQL/PostgreSQL beyond this

#### Migration to MySQL/PostgreSQL

1. **Export data**:
```bash
sqlite3 /data/database.sqlite .dump > /tmp/export.sql
```

2. **Convert schema**:
```bash
# Use conversion tool or manual migration
php scripts/convert_sqlite_to_mysql.php
```

3. **Update configuration**:
```bash
# In .env
DB_TYPE=mysql
DB_HOST=mysql-server
DB_NAME=chatbot
DB_USER=chatbot_user
DB_PASSWORD=secure_password
```

4. **Import data**:
```bash
mysql -u chatbot_user -p chatbot < /tmp/export_mysql.sql
```

### Capacity Planning

**Formulas**:

```
Requests per second = (Peak RPS * Safety Factor)
  where Safety Factor = 2.0 (handle 2x peak)

Memory per instance = (Base + Concurrent Requests * Memory per Request)
  Base = 256MB
  Memory per Request = 50MB (with OpenAI streaming)

Workers needed = (Job Creation Rate / Job Processing Rate) * 1.5
  Example: 100 jobs/min / 30 jobs/min/worker * 1.5 = 5 workers

Database growth = (Messages per Day * 5KB) + (Files per Day * Average Size)
```

**Example Calculation**:
- 10,000 messages/day
- 100 file uploads/day (avg 500KB)
- Daily growth: 10,000 * 5KB + 100 * 500KB = ~100MB/day
- Monthly: ~3GB
- With 7-day retention: ~700MB working set

### Autoscaling (Kubernetes)

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: chatbot-app-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: chatbot-app
  minReplicas: 2
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
```

---

## Security Operations

### Daily Security Checks

```bash
# 1. Review failed authentication attempts
sqlite3 /data/database.sqlite "SELECT COUNT(*) FROM audit_events 
  WHERE event_type='auth_failed' AND timestamp > datetime('now', '-24 hours');"

# 2. Check for suspicious API usage
sqlite3 /data/database.sqlite "SELECT tenant_id, COUNT(*) as req_count 
  FROM usage_logs WHERE created_at > datetime('now', '-1 hour') 
  GROUP BY tenant_id HAVING req_count > 1000;"

# 3. Verify SSL certificate expiration
openssl s_client -connect your-domain.com:443 2>/dev/null | 
  openssl x509 -noout -dates

# 4. Check for CVEs in dependencies
docker scan chatbot-app:latest
# OR
composer audit
```

### Access Management

#### Add Admin User

```bash
php scripts/create_admin_user.php \
  --email=newadmin@example.com \
  --role=admin \
  --tenant-id=1
```

#### Rotate API Keys

```bash
# Rotate all keys for a tenant
php scripts/rotate_tenant_keys.php --tenant-id=123

# Rotate specific key
php scripts/rotate_key.sh --key-id=456
```

#### Review Permissions

```bash
# List super-admins
sqlite3 /data/database.sqlite "SELECT * FROM admin_users WHERE role='super-admin';"

# List resource permissions
sqlite3 /data/database.sqlite "SELECT * FROM resource_permissions WHERE is_revoked=0;"
```

### Security Incident Response

**See [DR_RUNBOOK.md](DR_RUNBOOK.md) - Scenario 5**

Quick actions:
1. Isolate affected systems
2. Preserve evidence
3. Rotate credentials
4. Notify security team
5. Review audit logs

### Compliance Operations

**See [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md)**

#### GDPR/LGPD Data Subject Requests

**Right to Access**:
```bash
# Export user data
php scripts/export_user_data.php --user-id="+5511999999999" --format=json > user_data.json
```

**Right to Erasure**:
```bash
# Delete user data
php scripts/delete_user_data.php --user-id="+5511999999999" --confirm
```

**Right to Portability**:
```bash
# Export in machine-readable format
php scripts/export_user_data.php --user-id="+5511999999999" --format=json
```

#### Consent Management

```bash
# Check consent status
curl "http://localhost/admin-api.php?action=check_consent&agent_id=1&channel=whatsapp&external_user_id=%2B5511999999999" \
  -H "Authorization: Bearer $ADMIN_TOKEN"

# Process opt-out
php scripts/process_optout.php --phone="+5511999999999"
```

#### Audit Log Review

```bash
# Review today's audit events
sqlite3 /data/database.sqlite "SELECT * FROM audit_events 
  WHERE timestamp > date('now') ORDER BY timestamp DESC LIMIT 100;"

# Export audit logs for compliance
php scripts/export_audit_logs.php --start-date=2025-11-01 --end-date=2025-11-30 > audit_november.json
```

---

## Incident Response

### Severity Levels

| Level | Description | Response Time | Escalation |
|-------|-------------|---------------|------------|
| P0 | Complete outage | 15 minutes | Immediate |
| P1 | Severe degradation | 1 hour | If not resolved in 2h |
| P2 | Minor issues | 4 hours | If not resolved in 24h |
| P3 | Cosmetic/minor bugs | Next business day | None |

### Incident Response Flow

1. **Detection** (automated alerts or user report)
2. **Acknowledgment** (update status page, notify team)
3. **Triage** (assess severity, identify affected components)
4. **Investigation** (review logs, metrics, traces)
5. **Mitigation** (apply fix or rollback)
6. **Validation** (run smoke tests, monitor)
7. **Resolution** (close incident, notify users)
8. **Post-mortem** (within 48 hours, document learnings)

### Common Incidents

#### High Error Rate

**Symptoms**: >5% error rate in metrics

**Investigation**:
```bash
# Check recent errors
tail -100 logs/error.log

# Query error distribution
curl -s http://localhost:9090/api/v1/query?query='sum(rate(chatbot_api_errors_total[5m])) by (status_code)'

# Check database connectivity
php tests/test_db_connection.php
```

**Resolution**:
- If code issue: Rollback to previous version
- If database issue: Restore from backup (see DR runbook)
- If OpenAI issue: Check status.openai.com, implement retry logic

#### Job Queue Backlog

**Symptoms**: >100 pending jobs

**Investigation**:
```bash
# Check failed jobs
sqlite3 /data/database.sqlite "SELECT * FROM jobs WHERE status='failed' ORDER BY created_at DESC LIMIT 10;"

# Check worker health
docker-compose logs worker | tail -100
```

**Resolution**:
```bash
# Scale up workers
docker-compose up -d --scale worker=5

# Retry failed jobs
php scripts/retry_failed_jobs.php

# If specific job type failing, disable temporarily
php scripts/pause_job_type.php --type=webhook_delivery
```

#### Database Performance Issues

**Symptoms**: Slow queries, high latency

**Investigation**:
```bash
# Check slow queries (if MySQL)
mysql -e "SELECT * FROM information_schema.PROCESSLIST WHERE Time > 5;"

# Check database size
du -sh /data/database.sqlite

# Analyze query plans
sqlite3 /data/database.sqlite "EXPLAIN QUERY PLAN SELECT * FROM conversations WHERE tenant_id=1;"
```

**Resolution**:
```bash
# Rebuild indexes
sqlite3 /data/database.sqlite "REINDEX;"

# Vacuum database
sqlite3 /data/database.sqlite "VACUUM;"

# Archive old data
php scripts/archive_old_conversations.php --days=180
```

---

## Maintenance Procedures

### Scheduled Maintenance Window

**Recommended**: First Saturday of each month, 2:00-4:00 AM UTC

**Procedure**:

1. **Notify users** (48 hours advance notice)
2. **Enable maintenance mode**:
   ```bash
   touch /var/www/html/.maintenance
   ```
3. **Backup everything**:
   ```bash
   ./scripts/backup_all.sh --offsite
   ```
4. **Perform updates**:
   ```bash
   # Update code
   git pull origin main
   
   # Update dependencies
   composer update
   
   # Run migrations
   php scripts/run_migrations.php
   
   # Rebuild containers
   docker-compose up -d --build
   ```
5. **Run tests**:
   ```bash
   ./scripts/smoke_test.sh
   php tests/run_tests.php
   ```
6. **Disable maintenance mode**:
   ```bash
   rm /var/www/html/.maintenance
   ```
7. **Monitor closely** (1 hour post-maintenance)
8. **Send completion notice**

### Database Maintenance

**Weekly Tasks**:
```bash
# Analyze tables (MySQL)
mysqlcheck --analyze --all-databases

# Update statistics (SQLite)
sqlite3 /data/database.sqlite "ANALYZE;"

# Check fragmentation
sqlite3 /data/database.sqlite "PRAGMA freelist_count;"
```

**Monthly Tasks**:
```bash
# Vacuum database
sqlite3 /data/database.sqlite "VACUUM;"

# Archive old audit logs
php scripts/audit_retention_cleanup.php --days=90

# Review indexes
php scripts/analyze_index_usage.php
```

### Log Rotation

**Configuration**: `/etc/logrotate.d/chatbot`

```
/var/log/chatbot/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data adm
    postrotate
        docker-compose restart app > /dev/null 2>&1 || true
    endscript
}
```

### Certificate Renewal

**Let's Encrypt** (automated):
```bash
# Check expiration
certbot certificates

# Force renewal (if needed)
certbot renew --force-renewal

# Restart web server
systemctl restart nginx
```

**Manual renewal**:
```bash
# Generate new certificate
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365

# Update nginx config
cp cert.pem /etc/ssl/certs/chatbot.crt
cp key.pem /etc/ssl/private/chatbot.key

# Restart
systemctl restart nginx
```

---

## Troubleshooting

### Application Won't Start

**Symptoms**: Docker container exits immediately

**Diagnosis**:
```bash
# Check logs
docker-compose logs app

# Check for port conflicts
netstat -tulpn | grep :80

# Check file permissions
ls -l /data/

# Verify environment
cat .env | grep -v "^#"
```

**Solutions**:
- Fix `.env` configuration errors
- Ensure database file exists and is writable
- Free up conflicting ports
- Check disk space: `df -h`

### Database Connection Errors

**Symptoms**: "Database locked" or "Connection refused"

**Diagnosis**:
```bash
# Check database file
ls -lh /data/database.sqlite

# Check for locks
lsof /data/database.sqlite

# Test connection
php tests/test_db_connection.php
```

**Solutions**:
```bash
# Kill processes holding locks
lsof /data/database.sqlite | awk 'NR>1 {print $2}' | xargs kill

# Increase timeout in config
# In config.php: 'timeout' => 30

# Consider migration to MySQL for high concurrency
```

### OpenAI API Errors

**Symptoms**: 429 Too Many Requests, 500 Internal Server Error

**Diagnosis**:
```bash
# Check OpenAI metrics
curl -s http://localhost:9090/metrics | grep openai_errors

# Review recent API calls
tail -100 logs/chatbot.log | grep "OpenAI"

# Check rate limits
curl https://api.openai.com/v1/usage
```

**Solutions**:
- Implement exponential backoff (already in code)
- Verify API key is valid: `echo $OPENAI_API_KEY`
- Check OpenAI status: https://status.openai.com
- Review quota limits in OpenAI dashboard

### Memory Leaks

**Symptoms**: Memory usage grows over time

**Diagnosis**:
```bash
# Monitor memory
watch -n 5 'docker stats --no-stream'

# Check for zombie processes
ps aux | grep defunct

# Profile PHP memory
php -d memory_profiler.output_dir=/tmp scripts/worker.php
```

**Solutions**:
```bash
# Restart services
docker-compose restart app worker

# Increase memory limits
# In docker-compose.yml: memory: 4G

# Enable memory profiling to find leak
php -d xdebug.profiler_enable=1 -d xdebug.profiler_output_dir=/tmp scripts/worker.php
```

### Slow Performance

**Symptoms**: High latency, slow responses

**Diagnosis**:
```bash
# Check APM metrics
curl -s http://localhost:9090/api/v1/query?query='histogram_quantile(0.95, rate(chatbot_api_request_duration_seconds_bucket[5m]))'

# Profile slow queries
tail -f logs/chatbot.log | grep "duration>"

# Check system resources
top -b -n 1 | head -20
iostat -x 1 5
```

**Solutions**:
- Add database indexes for slow queries
- Scale horizontally (add app instances)
- Enable caching (Redis/Memcached)
- Optimize OpenAI calls (reduce token usage)
- Use CDN for static assets

---

## Additional Resources

### Related Documentation

- [DR_RUNBOOK.md](DR_RUNBOOK.md) - Disaster recovery procedures
- [OBSERVABILITY.md](OBSERVABILITY.md) - Monitoring and logging details
- [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md) - GDPR/LGPD procedures
- [BILLING_METERING.md](BILLING_METERING.md) - Billing operations
- [TENANT_RATE_LIMITING.md](TENANT_RATE_LIMITING.md) - Rate limiting details

### External Links

- OpenAI Status: https://status.openai.com
- Docker Documentation: https://docs.docker.com
- Prometheus Documentation: https://prometheus.io/docs
- Grafana Documentation: https://grafana.com/docs

### Support Contacts

- GitHub Issues: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues
- Internal Wiki: [TBD]
- Slack Channel: #chatbot-ops [TBD]

---

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-08 | System | Initial consolidation |

**Review Cycle**: Quarterly  
**Next Review**: 2026-02-08  
**Owner**: Operations Team
