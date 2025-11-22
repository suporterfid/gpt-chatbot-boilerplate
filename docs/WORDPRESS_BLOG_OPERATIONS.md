# WordPress Blog Automation - Operational Runbook

## Table of Contents

1. [Setup & Configuration](#setup--configuration)
2. [Daily Operations](#daily-operations)
3. [Monitoring & Alerts](#monitoring--alerts)
4. [Troubleshooting Guide](#troubleshooting-guide)
5. [Maintenance Tasks](#maintenance-tasks)
6. [Emergency Procedures](#emergency-procedures)
7. [Performance Optimization](#performance-optimization)
8. [Security Operations](#security-operations)

---

## Setup & Configuration

### Initial System Setup

#### Prerequisites

```bash
# Verify PHP version (7.4+ required)
php --version

# Verify required PHP extensions
php -m | grep -E 'pdo|pdo_sqlite|curl|json|mbstring|openssl'

# Verify database connection
# Check config.php for database settings
```

#### Database Schema Installation

```bash
# Run migration script
cd db/
php run_migration.php 048_add_wordpress_blog_tables.sql

# Validate schema
php validate_blog_schema.php
```

Expected output:
```
✓ wp_blog_configurations table exists
✓ wp_blog_internal_links table exists
✓ wp_blog_article_queue table exists
✓ wp_blog_execution_log table exists
Schema validation: PASSED
```

### Configuration Management

#### Creating a New Blog Configuration

**Via Admin UI:**
1. Navigate to Admin Panel → Blog Configurations
2. Click "Create New Configuration"
3. Fill in required fields:
   - Configuration Name
   - WordPress Site URL
   - WordPress Username
   - WordPress API Key (Application Password)
   - OpenAI API Key
   - Replicate API Key (optional)
   - Target Word Count (default: 2000)
   - Max Internal Links (default: 5)

**Via API:**
```bash
curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_create_configuration \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_name": "My Blog Config",
    "wordpress_site_url": "https://myblog.com",
    "wordpress_username": "admin",
    "wordpress_api_key": "xxxx xxxx xxxx xxxx xxxx xxxx",
    "openai_api_key": "sk-xxxxxxxxxxxxxxxx",
    "replicate_api_key": "r8_xxxxxxxxxxxxxxxx",
    "target_word_count": 2000,
    "max_internal_links": 5,
    "google_drive_folder_id": "folder-id-here"
  }'
```

#### Managing Internal Links Repository

**Adding Internal Links:**
```bash
curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_add_internal_link \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "url": "https://myblog.com/article-1",
    "anchor_text": "Related Article Title"
  }'
```

**Best Practices:**
- Maintain 10-20 internal links per configuration
- Use descriptive anchor text
- Regularly audit and update broken links
- Categorize links by topic for better distribution

### API Key Management

#### WordPress Application Password Setup

1. **Log into WordPress Admin**
2. **Navigate to**: Users → Profile
3. **Scroll to**: Application Passwords section
4. **Generate New Password**:
   - Application Name: "Blog Automation"
   - Click "Add New Application Password"
   - Copy the generated password (format: `xxxx xxxx xxxx xxxx xxxx xxxx`)

5. **Store Securely**: Paste into configuration (will be encrypted automatically)

#### OpenAI API Key

1. **Visit**: https://platform.openai.com/api-keys
2. **Create New Secret Key**
3. **Copy immediately** (shown only once)
4. **Set Usage Limits** (recommended):
   - Hard limit: $50/month
   - Soft limit: $40/month
   - Email alerts enabled

#### Replicate API Token

1. **Visit**: https://replicate.com/account/api-tokens
2. **Create Token**
3. **Copy token** (format: `r8_xxxxxxxxxxxx`)

---

## Daily Operations

### Morning Checklist

**1. System Health Check (5 minutes)**
```bash
# Check system health endpoint
curl https://your-domain.com/admin-api.php?action=wordpress_blog_system_health \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Expected response:
```json
{
  "status": "healthy",
  "database": "connected",
  "disk_space": "85% free",
  "api_keys": "valid",
  "queue_size": 12
}
```

**2. Review Queue Status**
- Navigate to: Admin Panel → Article Queue
- Check for:
  - Articles stuck in "processing" status >2 hours
  - High retry counts (>3)
  - Failed articles with error messages

**3. Check Processing Metrics**
- Navigate to: Admin Panel → Blog Metrics
- Review:
  - Success rate (target: >95%)
  - Average processing time (target: <15 minutes)
  - API costs (track daily spending)

### Queuing New Articles

**Via Admin UI:**
1. Go to: Admin Panel → Article Queue
2. Click "Queue New Article"
3. Select configuration
4. Enter topic/title
5. Click "Add to Queue"

**Via API:**
```bash
curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_queue_article \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "topic": "The Future of Web Development"
  }'
```

**Bulk Queuing:**
```bash
# Create topics.txt file
# One topic per line

while IFS= read -r topic; do
  curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_queue_article \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"config_id\": 1, \"topic\": \"$topic\"}"
  sleep 1
done < topics.txt
```

### Processing Articles

**Manual Processing (CLI):**
```bash
# Process next pending article
php scripts/wordpress_blog_processor.php --mode=single

# Process all pending articles
php scripts/wordpress_blog_processor.php --mode=all

# Process specific article
php scripts/wordpress_blog_processor.php --article-id=abc123

# Dry run (validation only)
php scripts/wordpress_blog_processor.php --dry-run
```

**Scheduled Processing (Cron):**
```bash
# Add to crontab
# Process every 30 minutes
*/30 * * * * cd /path/to/project && php scripts/wordpress_blog_processor.php --mode=all >> /var/log/wp-blog-processor.log 2>&1
```

### End of Day Review

**1. Review Processing Statistics**
```bash
curl https://your-domain.com/admin-api.php?action=wordpress_blog_get_metrics&days=1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**2. Check Error Logs**
```bash
# Review execution log for errors
tail -100 /var/log/wp-blog-processor.log | grep -i error

# Check database error log
SELECT * FROM wp_blog_execution_log
WHERE status = 'failed'
AND created_at >= DATE('now', '-1 day')
ORDER BY created_at DESC;
```

**3. Verify WordPress Publications**
- Log into WordPress admin
- Check recent posts are published correctly
- Verify featured images are displaying
- Confirm internal links are working

---

## Monitoring & Alerts

### Key Metrics to Monitor

#### 1. Queue Health
```sql
-- Pending articles count
SELECT COUNT(*) FROM wp_blog_article_queue WHERE status = 'pending';

-- Processing articles older than 2 hours
SELECT id, topic, processing_started_at
FROM wp_blog_article_queue
WHERE status = 'processing'
AND processing_started_at < datetime('now', '-2 hours');

-- Failed articles in last 24 hours
SELECT COUNT(*) FROM wp_blog_article_queue
WHERE status = 'failed'
AND updated_at >= datetime('now', '-1 day');
```

#### 2. Success Rate
```sql
-- Overall success rate
SELECT
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM wp_blog_article_queue
GROUP BY status;
```

#### 3. Processing Performance
```sql
-- Average processing time by stage
SELECT
    stage,
    AVG(execution_time_ms) as avg_time_ms,
    COUNT(*) as count
FROM wp_blog_execution_log
WHERE status = 'completed'
AND created_at >= datetime('now', '-7 days')
GROUP BY stage
ORDER BY avg_time_ms DESC;
```

#### 4. API Cost Tracking
```sql
-- Estimated OpenAI costs (approximate)
SELECT
    COUNT(*) as articles_processed,
    COUNT(*) * 0.15 as estimated_cost_usd
FROM wp_blog_article_queue
WHERE status = 'completed'
AND processing_completed_at >= datetime('now', '-1 day');
```

### Setting Up Alerts

#### Email Alerts for Critical Issues

**config.php additions:**
```php
// Email alert configuration
define('WP_BLOG_ALERT_EMAIL', 'admin@yourdomain.com');
define('WP_BLOG_ALERT_ENABLED', true);
define('WP_BLOG_ALERT_THRESHOLD_FAILURES', 5); // Alert after 5 failures in 1 hour
```

#### Monitoring Script
```bash
#!/bin/bash
# monitor_wp_blog.sh

# Check for stuck articles
STUCK_COUNT=$(sqlite3 database.db "SELECT COUNT(*) FROM wp_blog_article_queue WHERE status = 'processing' AND processing_started_at < datetime('now', '-2 hours');")

if [ $STUCK_COUNT -gt 0 ]; then
    echo "WARNING: $STUCK_COUNT articles stuck in processing" | mail -s "WP Blog Alert: Stuck Articles" admin@yourdomain.com
fi

# Check for high failure rate
FAILED_COUNT=$(sqlite3 database.db "SELECT COUNT(*) FROM wp_blog_article_queue WHERE status = 'failed' AND updated_at >= datetime('now', '-1 hour');")

if [ $FAILED_COUNT -ge 5 ]; then
    echo "CRITICAL: $FAILED_COUNT articles failed in the last hour" | mail -s "WP Blog Alert: High Failure Rate" admin@yourdomain.com
fi
```

**Add to crontab:**
```bash
# Run every 15 minutes
*/15 * * * * /path/to/monitor_wp_blog.sh
```

---

## Troubleshooting Guide

### Problem 1: Articles Stuck in "Processing" Status

**Symptoms:**
- Articles remain in "processing" status for >2 hours
- No execution log updates

**Diagnosis:**
```bash
# Check for running processes
ps aux | grep wordpress_blog_processor

# Check execution log
SELECT * FROM wp_blog_execution_log
WHERE article_id = 'ARTICLE_ID'
ORDER BY created_at DESC LIMIT 10;
```

**Solutions:**

1. **Process died unexpectedly:**
```bash
# Reset article to pending
sqlite3 database.db "UPDATE wp_blog_article_queue SET status = 'pending', processing_started_at = NULL WHERE id = 'ARTICLE_ID';"

# Retry processing
php scripts/wordpress_blog_processor.php --article-id=ARTICLE_ID
```

2. **API timeout:**
```php
// Check error handler max retries
// In includes/WordPressBlog/ErrorHandling/WordPressBlogErrorHandler.php
// Increase max retries temporarily
$errorHandler->setMaxRetries(5);
```

### Problem 2: OpenAI API Rate Limit Errors

**Symptoms:**
- Error message: "Rate limit exceeded"
- HTTP 429 status codes in logs

**Diagnosis:**
```bash
# Check recent API errors
SELECT message, error_details FROM wp_blog_execution_log
WHERE stage = 'content'
AND status = 'failed'
AND message LIKE '%rate limit%'
ORDER BY created_at DESC LIMIT 5;
```

**Solutions:**

1. **Immediate:** Reduce processing rate
```bash
# Stop automated processing
crontab -e
# Comment out wp_blog_processor cron job

# Process manually with delays
for id in $(sqlite3 database.db "SELECT id FROM wp_blog_article_queue WHERE status = 'pending' LIMIT 5"); do
    php scripts/wordpress_blog_processor.php --article-id=$id
    sleep 300  # Wait 5 minutes between articles
done
```

2. **Long-term:** Implement rate limiting
```php
// Add to ConfigurationService
private $lastApiCall = 0;
private $minDelay = 60; // seconds

public function rateLimit() {
    $now = time();
    $elapsed = $now - $this->lastApiCall;

    if ($elapsed < $this->minDelay) {
        sleep($this->minDelay - $elapsed);
    }

    $this->lastApiCall = time();
}
```

### Problem 3: WordPress Publishing Fails

**Symptoms:**
- Error: "Failed to publish to WordPress"
- HTTP 401 or 403 errors

**Diagnosis:**
```bash
# Test WordPress connectivity
curl -X POST https://myblog.com/wp-json/wp/v2/posts \
  -H "Authorization: Basic $(echo -n 'username:api_key' | base64)" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","content":"Test","status":"draft"}'
```

**Solutions:**

1. **Invalid credentials:**
```bash
# Regenerate WordPress application password
# Update configuration
curl -X PUT https://your-domain.com/admin-api.php?action=wordpress_blog_update_configuration \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "wordpress_api_key": "NEW_API_KEY"
  }'
```

2. **WordPress plugin blocking API:**
```bash
# Check WordPress error logs
ssh wordpress-server
tail -100 /var/www/html/wp-content/debug.log
```

### Problem 4: Image Generation Fails

**Symptoms:**
- Error: "Failed to generate image"
- Replicate API errors

**Diagnosis:**
```bash
# Check Replicate API status
curl https://api.replicate.com/v1/models \
  -H "Authorization: Token YOUR_API_TOKEN"

# Check execution log
SELECT * FROM wp_blog_execution_log
WHERE stage = 'image'
AND status = 'failed'
ORDER BY created_at DESC LIMIT 10;
```

**Solutions:**

1. **Fallback to placeholder:**
```php
// In ImageGenerator.php
try {
    $imageUrl = $this->generateImage(...);
} catch (ImageGenerationException $e) {
    // Use placeholder
    $imageUrl = 'https://via.placeholder.com/1200x630.png?text=Article+Image';
    error_log("Using placeholder image for article: " . $e->getMessage());
}
```

2. **Check API quota:**
```bash
# Visit Replicate dashboard
# Check API usage and billing
```

### Problem 5: Database Lock Errors

**Symptoms:**
- Error: "database is locked"
- SQLite timeout errors

**Diagnosis:**
```bash
# Check for long-running queries
lsof database.db

# Check database integrity
sqlite3 database.db "PRAGMA integrity_check;"
```

**Solutions:**

1. **Increase timeout:**
```php
// In DB configuration
$this->db->setAttribute(PDO::ATTR_TIMEOUT, 30); // 30 seconds
```

2. **Add WAL mode:**
```bash
# Enable Write-Ahead Logging
sqlite3 database.db "PRAGMA journal_mode=WAL;"
```

### Problem 6: Content Quality Issues

**Symptoms:**
- Generated content is too short/long
- Poor structure or readability
- Missing internal links

**Diagnosis:**
```bash
# Check validation results
SELECT * FROM wp_blog_execution_log
WHERE stage = 'validation'
AND status = 'warning'
ORDER BY created_at DESC LIMIT 10;

# Analyze content
php scripts/analyze_content.php --article-id=ARTICLE_ID
```

**Solutions:**

1. **Adjust target word count:**
```sql
UPDATE wp_blog_configurations
SET target_word_count = 2500
WHERE id = 1;
```

2. **Review prompts:**
```php
// In ChapterContentWriter.php
// Enhance system prompt for better quality
$systemPrompt = "You are an expert content writer specializing in {topic}.
Write comprehensive, well-researched content with clear structure,
examples, and actionable insights. Aim for {word_count} words.";
```

### Problem 7: High API Costs

**Symptoms:**
- Unexpected high OpenAI bills
- Cost per article >$0.50

**Diagnosis:**
```bash
# Calculate average cost per article
SELECT
    AVG(total_tokens) as avg_tokens,
    AVG(total_tokens) * 0.00001 as estimated_cost_usd
FROM (
    SELECT article_id, SUM(execution_time_ms) as total_tokens
    FROM wp_blog_execution_log
    WHERE stage = 'content'
    AND status = 'completed'
    GROUP BY article_id
);
```

**Solutions:**

1. **Switch to cheaper model:**
```sql
UPDATE wp_blog_configurations
SET openai_model = 'gpt-3.5-turbo'
WHERE id = 1;
```

2. **Optimize prompts:**
```php
// Reduce system prompt length
// Use more concise instructions
// Avoid redundant context
```

### Problem 8: Execution Log Growth

**Symptoms:**
- Database file size increasing rapidly
- Slow query performance

**Diagnosis:**
```bash
# Check database size
ls -lh database.db

# Count log entries
sqlite3 database.db "SELECT COUNT(*) FROM wp_blog_execution_log;"
```

**Solutions:**

1. **Archive old logs:**
```bash
# Export logs older than 90 days
sqlite3 database.db "SELECT * FROM wp_blog_execution_log WHERE created_at < date('now', '-90 days');" > archived_logs.csv

# Delete old logs
sqlite3 database.db "DELETE FROM wp_blog_execution_log WHERE created_at < date('now', '-90 days');"

# Vacuum database
sqlite3 database.db "VACUUM;"
```

### Problem 9: Configuration Validation Fails

**Symptoms:**
- Error: "Configuration validation failed"
- Unable to save configuration

**Diagnosis:**
```bash
# Test configuration
curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_validate_configuration \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...configuration data...}'
```

**Solutions:**

1. **Check API connectivity:**
```bash
# Test each API separately
# WordPress
curl https://myblog.com/wp-json/wp/v2/posts

# OpenAI
curl https://api.openai.com/v1/models \
  -H "Authorization: Bearer sk-..."

# Replicate
curl https://api.replicate.com/v1/models \
  -H "Authorization: Token r8_..."
```

2. **Review validation errors:**
```javascript
// Check browser console for detailed errors
// Fix each validation error individually
```

### Problem 10: Permission Errors

**Symptoms:**
- Error: "Permission denied"
- Cannot write to Google Drive
- Cannot create files

**Diagnosis:**
```bash
# Check file permissions
ls -la /path/to/storage/

# Check Google Drive API status
# Review OAuth token expiration
```

**Solutions:**

1. **Fix file permissions:**
```bash
# Set correct ownership
chown -R www-data:www-data /path/to/storage/

# Set correct permissions
chmod -R 755 /path/to/storage/
```

2. **Refresh Google Drive credentials:**
```bash
# Re-authenticate with Google Drive
# Update OAuth tokens in configuration
```

---

## Maintenance Tasks

### Daily Maintenance (5-10 minutes)

**Tasks:**
- [ ] Review queue status and metrics
- [ ] Check for failed articles and retry
- [ ] Monitor API costs
- [ ] Review error logs for new issues

**Commands:**
```bash
# Daily health check script
#!/bin/bash
echo "=== Daily WordPress Blog Automation Health Check ==="
echo "Date: $(date)"
echo ""

# Queue status
echo "Queue Status:"
sqlite3 database.db "SELECT status, COUNT(*) FROM wp_blog_article_queue GROUP BY status;"
echo ""

# Recent failures
echo "Recent Failures (last 24h):"
sqlite3 database.db "SELECT id, topic, error_message FROM wp_blog_article_queue WHERE status = 'failed' AND updated_at >= datetime('now', '-1 day');"
echo ""

# Processing stats
echo "Processing Stats (last 24h):"
sqlite3 database.db "SELECT COUNT(*) as completed FROM wp_blog_article_queue WHERE status = 'completed' AND processing_completed_at >= datetime('now', '-1 day');"
```

### Weekly Maintenance (30-60 minutes)

**Tasks:**
- [ ] Review and update internal links repository
- [ ] Analyze content quality metrics
- [ ] Check WordPress site health
- [ ] Review API usage and costs
- [ ] Update broken or outdated links
- [ ] Backup database

**Backup Script:**
```bash
#!/bin/bash
# weekly_backup.sh

BACKUP_DIR="/backups/wp-blog"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
cp database.db "$BACKUP_DIR/database_$TIMESTAMP.db"

# Backup configuration files
tar -czf "$BACKUP_DIR/config_$TIMESTAMP.tar.gz" config.php .env

# Remove backups older than 30 days
find $BACKUP_DIR -name "*.db" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

echo "Backup completed: $TIMESTAMP"
```

### Monthly Maintenance (2-4 hours)

**Tasks:**
- [ ] Review and optimize database performance
- [ ] Audit API key security
- [ ] Review and update documentation
- [ ] Analyze processing metrics trends
- [ ] Clean up old execution logs (>90 days)
- [ ] Test disaster recovery procedures
- [ ] Update dependencies

**Database Optimization:**
```bash
# Analyze database statistics
sqlite3 database.db "ANALYZE;"

# Rebuild indexes
sqlite3 database.db "REINDEX;"

# Vacuum to reclaim space
sqlite3 database.db "VACUUM;"

# Check integrity
sqlite3 database.db "PRAGMA integrity_check;"
```

### Quarterly Maintenance (4-8 hours)

**Tasks:**
- [ ] Full system audit
- [ ] Review and update security policies
- [ ] Performance benchmarking
- [ ] Disaster recovery test
- [ ] API key rotation
- [ ] Dependency updates
- [ ] Capacity planning review

**Performance Benchmarking:**
```bash
# Run full test suite
./vendor/bin/phpunit tests/

# Profile article processing
time php scripts/wordpress_blog_processor.php --article-id=test-article

# Database performance test
sqlite3 database.db ".timer on" "SELECT * FROM wp_blog_article_queue WHERE status = 'completed' LIMIT 1000;"
```

---

## Emergency Procedures

### Emergency 1: Complete System Outage

**Immediate Actions:**

1. **Check system status:**
```bash
# Verify server is running
systemctl status apache2  # or nginx

# Check database accessibility
sqlite3 database.db "SELECT 1;"

# Check disk space
df -h
```

2. **Review error logs:**
```bash
tail -100 /var/log/apache2/error.log
tail -100 /var/log/wp-blog-processor.log
```

3. **Restart services:**
```bash
sudo systemctl restart apache2
# or
sudo systemctl restart nginx
sudo systemctl restart php-fpm
```

### Emergency 2: Database Corruption

**Immediate Actions:**

1. **Stop all processing:**
```bash
# Kill running processors
killall php

# Disable cron jobs
crontab -e
# Comment out all wp-blog related jobs
```

2. **Restore from backup:**
```bash
# Find latest backup
ls -lt /backups/wp-blog/database_*.db | head -1

# Restore
cp /backups/wp-blog/database_YYYYMMDD_HHMMSS.db database.db
```

3. **Verify integrity:**
```bash
sqlite3 database.db "PRAGMA integrity_check;"
```

### Emergency 3: API Key Compromise

**Immediate Actions:**

1. **Revoke compromised keys:**
   - OpenAI: https://platform.openai.com/api-keys
   - Replicate: https://replicate.com/account/api-tokens
   - WordPress: Users → Profile → Application Passwords

2. **Generate new keys**

3. **Update all configurations:**
```bash
# Update via API
curl -X PUT https://your-domain.com/admin-api.php?action=wordpress_blog_update_configuration \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "openai_api_key": "NEW_KEY",
    "replicate_api_key": "NEW_KEY",
    "wordpress_api_key": "NEW_KEY"
  }'
```

4. **Review access logs:**
```bash
# Check for suspicious activity
grep "wordpress_blog" /var/log/apache2/access.log | grep -v "200\|201"
```

### Emergency 4: High Processing Costs

**Immediate Actions:**

1. **Stop all automated processing:**
```bash
crontab -e
# Comment out processor cron job
```

2. **Review API usage:**
```bash
# Check OpenAI usage
# Visit: https://platform.openai.com/usage

# Calculate current month costs
sqlite3 database.db "SELECT COUNT(*) * 0.15 as estimated_cost FROM wp_blog_article_queue WHERE status = 'completed' AND processing_completed_at >= date('now', 'start of month');"
```

3. **Implement cost limits:**
```php
// Add to config.php
define('WP_BLOG_MAX_DAILY_COST', 10.00); // $10/day
define('WP_BLOG_MAX_MONTHLY_COST', 200.00); // $200/month
```

### Emergency 5: WordPress Site Down

**Immediate Actions:**

1. **Pause article processing:**
```bash
# Update all processing articles to pending
sqlite3 database.db "UPDATE wp_blog_article_queue SET status = 'pending', processing_started_at = NULL WHERE status = 'processing';"
```

2. **Verify WordPress status:**
```bash
curl -I https://myblog.com
curl https://myblog.com/wp-json/
```

3. **Queue articles for retry:**
```sql
UPDATE wp_blog_article_queue
SET status = 'pending',
    retry_count = retry_count + 1
WHERE status = 'failed'
AND error_message LIKE '%WordPress%';
```

---

## Performance Optimization

### Database Optimization

**Enable WAL Mode:**
```bash
sqlite3 database.db "PRAGMA journal_mode=WAL;"
```

**Optimize Query Performance:**
```sql
-- Create indexes
CREATE INDEX idx_article_status ON wp_blog_article_queue(status);
CREATE INDEX idx_article_created ON wp_blog_article_queue(created_at);
CREATE INDEX idx_exec_log_article ON wp_blog_execution_log(article_id);
CREATE INDEX idx_exec_log_stage ON wp_blog_execution_log(stage, status);
```

### Processing Optimization

**Batch Processing:**
```bash
# Process in batches instead of one-by-one
php scripts/wordpress_blog_processor.php --mode=batch --batch-size=5
```

**Parallel Processing:**
```bash
# Process multiple articles in parallel (careful with API limits!)
for i in {1..3}; do
    php scripts/wordpress_blog_processor.php --mode=single &
done
wait
```

### Caching Strategies

**Cache API Responses:**
```php
// Implement Redis caching
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Cache configuration
$cacheKey = "wp_blog_config_{$configId}";
$cached = $redis->get($cacheKey);

if ($cached) {
    return json_decode($cached, true);
}

$config = $this->loadConfiguration($configId);
$redis->setex($cacheKey, 3600, json_encode($config)); // 1 hour cache
```

---

## Security Operations

### Regular Security Audits

**Monthly Security Checklist:**
- [ ] Review API key usage and rotate if necessary
- [ ] Check for unauthorized access attempts
- [ ] Verify encryption is working correctly
- [ ] Review user permissions
- [ ] Update dependencies for security patches
- [ ] Audit execution logs for anomalies

**Security Audit Script:**
```bash
#!/bin/bash
echo "=== WordPress Blog Security Audit ==="

# Check for plaintext credentials
echo "Checking for plaintext credentials..."
grep -r "sk-" config.php .env || echo "✓ No plaintext OpenAI keys found"

# Check file permissions
echo "Checking file permissions..."
find . -type f -perm 0777 || echo "✓ No world-writable files"

# Check for recent failed access attempts
echo "Recent failed access attempts:"
grep "403\|401" /var/log/apache2/access.log | tail -20

echo "Audit complete: $(date)"
```

### Credential Rotation

**Quarterly Rotation:**
```bash
# 1. Generate new API keys
# 2. Update all configurations
# 3. Test with new credentials
# 4. Revoke old credentials
# 5. Monitor for any issues
```

---

## Appendix

### Useful SQL Queries

```sql
-- Most common error messages
SELECT error_message, COUNT(*) as count
FROM wp_blog_article_queue
WHERE status = 'failed'
GROUP BY error_message
ORDER BY count DESC
LIMIT 10;

-- Processing time by configuration
SELECT
    c.config_name,
    AVG(JULIANDAY(q.processing_completed_at) - JULIANDAY(q.processing_started_at)) * 24 * 60 as avg_minutes
FROM wp_blog_article_queue q
JOIN wp_blog_configurations c ON q.config_id = c.id
WHERE q.status = 'completed'
GROUP BY c.config_name;

-- Articles published per day (last 30 days)
SELECT
    DATE(processing_completed_at) as date,
    COUNT(*) as articles_published
FROM wp_blog_article_queue
WHERE status = 'completed'
AND processing_completed_at >= date('now', '-30 days')
GROUP BY DATE(processing_completed_at)
ORDER BY date DESC;
```

### Contact Information

**For Technical Support:**
- Email: tech-support@yourdomain.com
- On-call: +1-555-123-4567
- Slack: #wordpress-blog-automation

**For API Issues:**
- OpenAI: https://help.openai.com
- Replicate: https://replicate.com/docs
- WordPress: https://wordpress.org/support/

**Escalation Path:**
1. Level 1: Check troubleshooting guide
2. Level 2: Review logs and attempt emergency procedures
3. Level 3: Contact technical support
4. Level 4: Escalate to system administrator

---

**Document Version:** 1.0
**Last Updated:** November 21, 2025
**Next Review:** February 21, 2026
