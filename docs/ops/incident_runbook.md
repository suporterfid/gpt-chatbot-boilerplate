# Incident Response Runbook

## Overview

This runbook provides step-by-step procedures for responding to common production incidents in the GPT Chatbot system.

## Quick Reference

| Issue | Severity | Page |
|-------|----------|------|
| Site completely down | P0 | [Site Down](#site-completely-down) |
| High error rate | P1 | [High Error Rate](#high-error-rate) |
| Worker stopped | P1 | [Worker Stopped](#background-worker-stopped) |
| Database connection issues | P0 | [Database Issues](#database-connection-issues) |
| OpenAI API failures | P1 | [OpenAI Issues](#openai-api-failures) |
| High queue depth | P2 | [Queue Backup](#high-queue-depth) |
| Disk space low | P1 | [Disk Space](#disk-space-critical) |
| Memory issues | P1 | [Memory](#high-memory-usage) |
| Security breach | P0 | [Security](#security-breach-suspected) |

## General Incident Response Process

1. **Assess**: Determine severity and impact
2. **Notify**: Alert relevant stakeholders
3. **Triage**: Gather information and logs
4. **Mitigate**: Apply immediate fix or workaround
5. **Resolve**: Implement permanent solution
6. **Document**: Record incident and lessons learned

## Site Completely Down

**Symptoms:**
- HTTP 502/503 errors
- Site unreachable
- Health check failing

**Immediate Actions:**

1. **Check web server status:**
   ```bash
   sudo systemctl status nginx
   sudo systemctl status apache2
   ```

2. **Check PHP-FPM status:**
   ```bash
   sudo systemctl status php8.2-fpm
   ```

3. **Check recent logs:**
   ```bash
   sudo tail -100 /var/log/nginx/chatbot_error.log
   sudo tail -100 /var/log/php8.2-fpm.log
   ```

4. **Restart services:**
   ```bash
   sudo systemctl restart php8.2-fpm
   sudo systemctl restart nginx
   ```

5. **If still down, check disk space:**
   ```bash
   df -h
   # If full, see Disk Space section below
   ```

6. **Check database connectivity:**
   ```bash
   # PostgreSQL
   psql $ADMIN_DATABASE_URL -c "SELECT 1"
   
   # SQLite
   sqlite3 data/admin.db "SELECT 1"
   ```

## High Error Rate

**Symptoms:**
- Increased 5xx errors in logs
- Alert: AdminAPIHighErrorRate firing
- Users reporting errors

**Investigation:**

1. **Check recent error logs:**
   ```bash
   sudo tail -500 /var/log/chatbot/application.log | grep '"level":"error"'
   ```

2. **Identify error patterns:**
   ```bash
   cat /var/log/chatbot/application.log | jq -r 'select(.level=="error") | .context.error' | sort | uniq -c | sort -nr | head -20
   ```

3. **Check OpenAI API status:**
   ```bash
   curl -I https://api.openai.com/v1/models
   ```

**Common Causes & Solutions:**

### OpenAI API Errors

```bash
# Check recent OpenAI errors
cat /var/log/chatbot/application.log | jq 'select(.event=="openai_error")'

# Solution: Implement circuit breaker or fallback
# If rate limited: wait or upgrade OpenAI plan
# If 5xx: OpenAI outage, enable graceful degradation
```

### Database Connection Pool Exhausted

```bash
# Check active connections
# PostgreSQL:
psql $ADMIN_DATABASE_URL -c "SELECT count(*) FROM pg_stat_activity WHERE datname = 'chatbot_production';"

# Solution: Increase max connections or add connection pooling
sudo nano /etc/postgresql/*/main/postgresql.conf
# Set: max_connections = 200
sudo systemctl restart postgresql
```

### PHP Memory Exhausted

```bash
# Check PHP error log
sudo grep "memory" /var/log/php8.2-fpm.log

# Solution: Increase memory limit
sudo nano /etc/php/8.2/fpm/php.ini
# Set: memory_limit = 256M
sudo systemctl restart php8.2-fpm
```

## Background Worker Stopped

**Symptoms:**
- Alert: WorkerDown firing
- Jobs stuck in pending state
- No recent job completions

**Immediate Actions:**

1. **Check worker status:**
   ```bash
   sudo systemctl status chatbot-worker
   ```

2. **Check worker logs:**
   ```bash
   sudo tail -100 /var/log/chatbot/worker.log
   ```

3. **Restart worker:**
   ```bash
   sudo systemctl restart chatbot-worker
   sudo systemctl status chatbot-worker
   ```

4. **If worker won't start:**
   ```bash
   # Run worker manually to see errors
   cd /var/www/chatbot
   sudo -u www-data php scripts/worker.php --mode=single
   ```

**Common Issues:**

### Worker Crashed Due to Memory

```bash
# Check system memory
free -h
dmesg | grep -i "out of memory"

# Solution: Increase worker memory limit or add more RAM
sudo nano /etc/systemd/system/chatbot-worker.service
# Add: MemoryLimit=1G
sudo systemctl daemon-reload
sudo systemctl restart chatbot-worker
```

### Worker Locked Up

```bash
# Check for long-running jobs
SELECT * FROM jobs WHERE status = 'running' AND updated_at < datetime('now', '-1 hour');

# Force stop and restart
sudo systemctl stop chatbot-worker
sudo pkill -9 -f worker.php
sudo systemctl start chatbot-worker
```

## Database Connection Issues

**Symptoms:**
- Errors: "Connection refused", "Too many connections"
- Slow query performance
- Timeouts

**Investigation:**

1. **Test database connectivity:**
   ```bash
   # PostgreSQL
   psql $ADMIN_DATABASE_URL -c "SELECT version();"
   
   # Check active connections
   psql $ADMIN_DATABASE_URL -c "SELECT count(*) FROM pg_stat_activity;"
   ```

2. **Check PostgreSQL status:**
   ```bash
   sudo systemctl status postgresql
   sudo tail -100 /var/log/postgresql/postgresql-*-main.log
   ```

**Solutions:**

### Too Many Connections

```bash
# Kill idle connections
psql $ADMIN_DATABASE_URL -c "
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = 'chatbot_production'
  AND state = 'idle'
  AND state_change < current_timestamp - interval '5 minutes';
"

# Increase connection limit
sudo nano /etc/postgresql/*/main/postgresql.conf
# Set: max_connections = 200
sudo systemctl restart postgresql
```

### Slow Queries

```bash
# Enable query logging temporarily
psql $ADMIN_DATABASE_URL -c "ALTER SYSTEM SET log_min_duration_statement = 1000;"
psql $ADMIN_DATABASE_URL -c "SELECT pg_reload_conf();"

# Check slow queries
sudo grep "duration" /var/log/postgresql/postgresql-*-main.log

# Identify missing indexes
psql $ADMIN_DATABASE_URL -c "
SELECT schemaname, tablename, attname, n_distinct
FROM pg_stats
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
ORDER BY n_distinct DESC;
"
```

## OpenAI API Failures

**Symptoms:**
- Jobs failing with OpenAI errors
- Chat requests timing out
- 429 (rate limit) or 5xx errors from OpenAI

**Investigation:**

1. **Check OpenAI status:**
   - Visit: https://status.openai.com/

2. **Check recent OpenAI errors:**
   ```bash
   cat /var/log/chatbot/application.log | jq 'select(.component=="openai_client" and .level=="error")' | tail -20
   ```

3. **Check rate limiting:**
   ```bash
   # Count OpenAI requests in last hour
   cat /var/log/chatbot/application.log | jq 'select(.event=="openai_request" and .ts > "'$(date -u -d '1 hour ago' +%Y-%m-%dT%H:%M:%S)'")'
 | wc -l
   ```

**Solutions:**

### Rate Limiting (429)

```bash
# Pause non-critical jobs
UPDATE jobs SET status = 'pending', available_at = datetime('now', '+1 hour')
WHERE type NOT IN ('critical_type_1', 'critical_type_2') AND status = 'pending';

# Check your OpenAI usage and limits at platform.openai.com
```

### API Outage (5xx)

```bash
# Enable graceful degradation
# Return cached responses or error messages to users
# Queue requests for retry when service recovers

# Monitor OpenAI status page for updates
```

### Timeout Issues

```bash
# Increase timeout in config.php
# Update OpenAIClient timeout from 30s to 60s

# Or reduce request complexity
# - Reduce max_tokens
# - Disable non-essential tools
# - Use faster model (gpt-3.5-turbo instead of gpt-4)
```

## High Queue Depth

**Symptoms:**
- Alert: HighQueueDepth firing
- Jobs backing up (1000+ pending)
- Slow job processing

**Investigation:**

1. **Check queue stats:**
   ```bash
   curl -s http://localhost/admin-api.php/job_stats -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
   ```

2. **Identify problematic job types:**
   ```bash
   SELECT type, status, COUNT(*) as count
   FROM jobs
   GROUP BY type, status
   ORDER BY count DESC;
   ```

**Solutions:**

### Scale Up Workers

```bash
# Start additional workers
sudo systemctl start chatbot-worker@2
sudo systemctl start chatbot-worker@3

# Monitor worker activity
ps aux | grep worker.php
```

### Cancel Stuck Jobs

```bash
# Find and cancel old pending jobs
UPDATE jobs
SET status = 'cancelled'
WHERE status = 'pending'
  AND created_at < datetime('now', '-24 hours');
```

### Prioritize Critical Jobs

```bash
# Move critical jobs to front of queue
UPDATE jobs
SET available_at = datetime('now')
WHERE type = 'critical_job_type'
  AND status = 'pending';
```

## Disk Space Critical

**Symptoms:**
- Alert: DiskSpaceLow firing
- Errors writing logs or database
- Application crashes

**Immediate Actions:**

1. **Check disk usage:**
   ```bash
   df -h
   du -sh /var/* | sort -hr | head -20
   ```

2. **Free up space quickly:**
   ```bash
   # Clear old logs
   sudo find /var/log -name "*.log" -mtime +7 -delete
   sudo find /var/log -name "*.gz" -mtime +14 -delete
   
   # Clear old backups
   sudo find /var/backups/chatbot -name "*.gz" -mtime +30 -delete
   
   # Clear APT cache
   sudo apt clean
   
   # Clear journal logs
   sudo journalctl --vacuum-time=7d
   ```

3. **Identify large files:**
   ```bash
   sudo find / -type f -size +100M -exec ls -lh {} \; 2>/dev/null
   ```

## High Memory Usage

**Symptoms:**
- System slow
- OOM (Out of Memory) errors
- Worker crashes

**Investigation:**

1. **Check memory usage:**
   ```bash
   free -h
   ps aux --sort=-%mem | head -20
   ```

2. **Check for memory leaks:**
   ```bash
   # Monitor PHP-FPM memory over time
   watch -n 5 'ps aux | grep php-fpm | awk "{sum+=\$6} END {print sum/1024\" MB\"}"'
   ```

**Solutions:**

### Restart PHP-FPM

```bash
sudo systemctl restart php8.2-fpm
```

### Reduce Worker Memory

```bash
# Reduce max concurrent jobs per worker
# Edit worker.php to process fewer jobs in parallel
```

### Add Swap Space

```bash
# Create 2GB swap file
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Make permanent
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## Security Breach Suspected

**Symptoms:**
- Unusual API requests
- Unauthorized access attempts
- Data exfiltration suspected

**IMMEDIATE ACTIONS:**

1. **Isolate the system:**
   ```bash
   # Block all traffic except from trusted IPs
   sudo ufw default deny incoming
   sudo ufw allow from YOUR_IP to any port 22
   sudo ufw enable
   ```

2. **Rotate all credentials:**
   ```bash
   # Rotate OpenAI API key at platform.openai.com
   # Rotate admin tokens
   # Rotate database passwords
   # Update .env file with new credentials
   ```

3. **Review access logs:**
   ```bash
   # Check nginx access logs for suspicious IPs
   sudo grep -v "200\|301\|302" /var/log/nginx/chatbot_access.log | tail -100
   
   # Check failed authentication attempts
   sudo grep "auth_failed" /var/log/chatbot/application.log
   ```

4. **Check for backdoors:**
   ```bash
   # Check for modified files
   sudo find /var/www/chatbot -name "*.php" -mtime -1 -ls
   
   # Check for unusual processes
   ps aux | grep -v "grep\|www-data\|root"
   ```

5. **Preserve evidence:**
   ```bash
   # Create forensic snapshot
   sudo tar czf /tmp/chatbot-forensics-$(date +%Y%m%d-%H%M%S).tar.gz \
     /var/www/chatbot \
     /var/log/nginx \
     /var/log/chatbot
   ```

6. **Notify stakeholders and authorities if required**

## Emergency Contacts

| Role | Name | Contact |
|------|------|---------|
| On-Call Engineer | [Name] | [Phone/Slack] |
| Database Admin | [Name] | [Phone/Slack] |
| Security Lead | [Name] | [Phone/Slack] |
| CTO/Engineering Manager | [Name] | [Phone/Slack] |

## Post-Incident

After resolving an incident:

1. **Document the incident:**
   - What happened
   - When it was detected
   - Impact and duration
   - Root cause
   - Resolution steps
   - Lessons learned

2. **Create postmortem:**
   - Timeline of events
   - What went well
   - What could be improved
   - Action items

3. **Update runbook:**
   - Add new scenarios
   - Improve existing procedures
   - Update contact information

4. **Implement preventive measures:**
   - Add monitoring/alerts
   - Automate recovery
   - Improve documentation

## Maintenance Tasks

### Cancel Stuck Jobs

```bash
# Cancel jobs stuck in running for >1 hour
cd /var/www/chatbot
php -r "
require 'config.php';
require 'includes/DB.php';
require 'includes/JobQueue.php';

\$db = new DB(\$config);
\$queue = new JobQueue(\$db);

\$result = \$db->execute(\"
    UPDATE jobs
    SET status = 'cancelled', error_text = 'Stuck job - cancelled by admin'
    WHERE status = 'running'
    AND updated_at < datetime('now', '-1 hour')
\");

echo \"Cancelled \$result stuck jobs\n\";
"
```

### Reprocess Failed Jobs from DLQ

```bash
# List failed jobs in DLQ
curl -s http://localhost/admin-api.php/list_dlq \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .

# Requeue specific job
curl -X POST "http://localhost/admin-api.php/requeue_dlq?id=DLQ_ID" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

### Clean Up Old Jobs

```bash
# Delete completed jobs older than 30 days
DELETE FROM jobs
WHERE status = 'completed'
AND updated_at < datetime('now', '-30 days');

# Delete failed jobs older than 90 days (after DLQ archival)
DELETE FROM jobs
WHERE status = 'failed'
AND updated_at < datetime('now', '-90 days');
```

### Rotate Admin Tokens

```bash
# Generate new token
NEW_TOKEN=$(openssl rand -base64 32)

# Update .env
sudo sed -i "s/^ADMIN_TOKEN=.*/ADMIN_TOKEN=$NEW_TOKEN/" /var/www/chatbot/.env

# Restart to pick up new token
sudo systemctl reload php8.2-fpm

# Communicate new token to admin users
```

## See Also

- [Production Deployment Guide](production-deploy.md)
- [Backup & Restore](backup_restore.md)
- [Monitoring Alerts](monitoring/alerts.yml)
- [Logging Guide](logs.md)
