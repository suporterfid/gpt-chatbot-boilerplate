# Phase 3: Background Workers, Webhooks & RBAC

## Overview

Phase 3 implements production-ready features for the GPT Chatbot Admin system:

- **Background Job Processing**: Asynchronous handling of long-running operations
- **Webhook Support**: Real-time event notifications from OpenAI
- **Role-Based Access Control (RBAC)**: Multi-user admin access with permissions
- **Observability**: Health checks, metrics, and enhanced audit logging
- **Production Hardening**: Security best practices and deployment guidance

## Architecture

### Background Job System

The job system uses a database-backed queue with a PHP CLI worker for processing asynchronous tasks.

#### Components

1. **JobQueue Service** (`includes/JobQueue.php`)
   - Enqueue jobs with retry logic
   - Atomic job claiming to prevent race conditions
   - Exponential backoff for failed jobs
   - Job lifecycle management (pending → running → completed/failed)

2. **Worker Process** (`scripts/worker.php`)
   - Polls queue for available jobs
   - Processes jobs based on type
   - Handles retries and failures
   - Supports single-run, loop, and daemon modes

3. **Job Types**
   - `file_ingest`: Upload files to OpenAI and attach to vector stores
   - `attach_file_to_store`: Attach existing OpenAI files to vector stores
   - `poll_ingestion_status`: Poll for file ingestion completion
   - `prompt_version_create`: Create new prompt versions asynchronously
   - `send_webhook_event`: Send outgoing webhooks

### Webhook System

Receives and processes events from OpenAI (or other providers) in real-time.

#### Components

1. **Webhook Endpoint** (`webhooks/openai.php`)
   - Receives POST requests from OpenAI
   - Verifies HMAC signatures (if configured)
   - Returns 200 quickly to prevent timeouts

2. **WebhookHandler Service** (`includes/WebhookHandler.php`)
   - Signature verification using HMAC-SHA256
   - Idempotency tracking to prevent duplicate processing
   - Event-to-database mapping
   - Audit logging for all webhook events

3. **Supported Events**
   - `vector_store.file.completed`: File ingestion completed
   - `vector_store.file.failed`: File ingestion failed
   - `vector_store.completed`: Vector store processing completed
   - `file.uploaded`: File uploaded to OpenAI

### RBAC System

Multi-user authentication and authorization with three permission levels.

#### Components

1. **AdminAuth Service** (`includes/AdminAuth.php`)
   - User management (create, update, deactivate)
   - API key generation and authentication
   - Role-based permission checks
   - Legacy ADMIN_TOKEN support

2. **Roles**

   | Role | Permissions | Use Case |
   |------|-------------|----------|
   | **viewer** | Read-only access | Monitoring, auditing |
   | **admin** | Create, update, delete agents/prompts/stores | Day-to-day management |
   | **super-admin** | All permissions + user management + token rotation | System administration |

3. **Permission Model**
   ```php
   const PERMISSIONS = [
       'viewer' => ['read'],
       'admin' => ['read', 'create', 'update', 'delete'],
       'super-admin' => ['read', 'create', 'update', 'delete', 'manage_users', 'rotate_tokens']
   ];
   ```

### Observability

#### Health Endpoint

**Endpoint**: `GET /admin-api.php?action=health`

**Response**:
```json
{
  "status": "ok",
  "timestamp": "2024-10-31T01:00:00+00:00",
  "database": true,
  "openai": true,
  "worker": {
    "enabled": true,
    "queue_depth": 5,
    "stats": {
      "pending": 3,
      "running": 2,
      "completed": 150,
      "failed": 5
    }
  }
}
```

#### Metrics Endpoint

**Endpoint**: `GET /admin-api.php?action=metrics`

**Format**: Prometheus text format

**Metrics**:
- `jobs_pending_total`: Number of pending jobs
- `jobs_running_total`: Number of running jobs
- `jobs_completed_total`: Total completed jobs
- `jobs_failed_total`: Total failed jobs
- `database_up`: Database connectivity (0 or 1)

**Example**:
```
# HELP jobs_pending_total Number of pending jobs
# TYPE jobs_pending_total gauge
jobs_pending_total 3

# HELP jobs_completed_total Number of completed jobs
# TYPE jobs_completed_total counter
jobs_completed_total 150
```

## Running the Worker

### Command Line Options

```bash
# Process one batch of jobs and exit
php scripts/worker.php

# Run continuously with 5-second sleep between batches
php scripts/worker.php --loop

# Run as daemon with graceful shutdown on SIGTERM/SIGINT
php scripts/worker.php --daemon

# Custom sleep interval (in seconds)
php scripts/worker.php --loop --sleep=10

# Verbose logging
php scripts/worker.php --daemon --verbose
```

### Production Deployment

#### Systemd Service (Linux)

Create `/etc/systemd/system/chatbot-worker.service`:

```ini
[Unit]
Description=GPT Chatbot Background Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/chatbot
ExecStart=/usr/bin/php /var/www/chatbot/scripts/worker.php --daemon --verbose
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable chatbot-worker
sudo systemctl start chatbot-worker
sudo systemctl status chatbot-worker
```

#### Supervisor (Alternative)

Create `/etc/supervisor/conf.d/chatbot-worker.conf`:

```ini
[program:chatbot-worker]
command=/usr/bin/php /var/www/chatbot/scripts/worker.php --daemon --verbose
directory=/var/www/chatbot
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/chatbot-worker.log
```

Reload supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start chatbot-worker
```

#### Docker

Add to `docker-compose.yml`:

```yaml
services:
  worker:
    build: .
    command: php scripts/worker.php --daemon --verbose
    volumes:
      - .:/var/www/html
      - ./data:/var/www/html/data
    environment:
      - OPENAI_API_KEY=${OPENAI_API_KEY}
      - ADMIN_TOKEN=${ADMIN_TOKEN}
    restart: unless-stopped
```

### Scaling Workers

You can run multiple worker processes to increase throughput:

```bash
# Run 3 workers in parallel
php scripts/worker.php --daemon --verbose &
php scripts/worker.php --daemon --verbose &
php scripts/worker.php --daemon --verbose &
```

The atomic job claiming mechanism (using database transactions) prevents race conditions when multiple workers are running.

## Webhook Setup

### OpenAI Webhook Configuration

1. **Generate a signing secret** (optional but recommended):
   ```bash
   openssl rand -hex 32
   ```

2. **Add to `.env`**:
   ```env
   WEBHOOK_OPENAI_SIGNING_SECRET=your_generated_secret
   ```

3. **Configure OpenAI webhook** (via OpenAI dashboard or API):
   - URL: `https://yourdomain.com/webhooks/openai.php`
   - Events: Select relevant events (vector_store.*, file.*)
   - Signing secret: Use the generated secret

4. **Test webhook**:
   ```bash
   curl -X POST https://yourdomain.com/webhooks/openai.php \
     -H "Content-Type: application/json" \
     -H "X-OpenAI-Signature: sha256=..." \
     -d '{"id":"evt_test","type":"vector_store.file.completed","data":{"vector_store_id":"vs_123","file_id":"file_456"}}'
   ```

### Webhook Security

- **Signature Verification**: Always configure a signing secret in production
- **HTTPS Only**: Never expose webhook endpoints over HTTP
- **IP Allowlist**: Optionally restrict to OpenAI IP ranges
- **Idempotency**: Webhook handler automatically prevents duplicate processing

## User Management

### Creating Admin Users

#### Via PHP Script

```php
require_once 'includes/DB.php';
require_once 'includes/AdminAuth.php';

$db = new DB(['database_path' => 'data/chatbot.db']);
$adminAuth = new AdminAuth($db, []);

// Create super-admin
$user = $adminAuth->createUser(
    'admin@example.com',
    'secure_password_here',
    AdminAuth::ROLE_SUPER_ADMIN
);

// Generate API key
$apiKey = $adminAuth->generateApiKey($user['id'], 'Admin Key');

echo "User created: {$user['email']}\n";
echo "API Key: {$apiKey['key']}\n"; // Save this - it won't be shown again!
```

#### Via Admin API

```bash
# Create user (requires super-admin token)
curl -X POST https://yourdomain.com/admin-api.php?action=create_user \
  -H "Authorization: Bearer $SUPER_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newadmin@example.com",
    "password": "secure_password",
    "role": "admin"
  }'

# Generate API key
curl -X POST https://yourdomain.com/admin-api.php?action=generate_api_key \
  -H "Authorization: Bearer $SUPER_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user_id_from_above",
    "name": "Admin Dashboard Key",
    "expires_in_days": 365
  }'
```

### Migrating from Legacy ADMIN_TOKEN

The system supports both legacy `ADMIN_TOKEN` and per-user API keys:

```php
// Migrate to user-based auth
$migration = $adminAuth->migrateLegacyToken();

echo "Legacy token migrated to user: {$migration['user']['email']}\n";
echo "New API key: {$migration['api_key']['key']}\n";
```

After migration, update your Admin UI to use the new API key.

## API Endpoints

### Job Management

#### List Jobs
```bash
GET /admin-api.php?action=list_jobs&status=pending&limit=50
```

#### Get Job Details
```bash
GET /admin-api.php?action=get_job&id=job_123
```

#### Retry Failed Job
```bash
POST /admin-api.php?action=retry_job&id=job_123
```

#### Cancel Job
```bash
POST /admin-api.php?action=cancel_job&id=job_123
```

#### Job Statistics
```bash
GET /admin-api.php?action=job_stats
```

### Audit Log

#### List Audit Logs
```bash
GET /admin-api.php?action=list_audit_log&limit=100
```

Returns a list of admin actions with timestamps, actors, and payloads.

## Admin UI Features

### Job Management Page

Access the Jobs page from the Admin UI to:

- **View Statistics**: Real-time dashboard showing pending, running, completed, and failed job counts
- **Monitor Jobs**: Auto-refreshing tables for pending and running jobs
- **Job Actions**:
  - View detailed job information (ID, type, payload, result, error messages)
  - Retry failed jobs
  - Cancel pending or running jobs
- **Auto-Refresh**: Jobs page refreshes every 5 seconds automatically

### Audit Log Viewer

Access the Audit Log page from the Admin UI to:

- **View History**: Chronological list of all admin actions
- **Inspect Details**: Click any log entry to view full payload and metadata
- **Export Data**: Export audit logs to CSV format for compliance and reporting
- **Filter & Search**: Browse through administrative actions by actor and action type

### Settings Page Enhancements

The Settings page now includes:

- **Worker Statistics**: View background job queue depth and processing stats
- **Quick Access**: Direct link to the Jobs page from Settings
- **Health Monitoring**: Real-time database, OpenAI, and worker status

## Configuration

Add to `.env`:

```env
# Job System
ADMIN_JOBS_ENABLED=true

# Webhooks
WEBHOOK_OPENAI_SIGNING_SECRET=your_secret_here

# Optional: Disable workers for sync-only operation
# ADMIN_JOBS_ENABLED=false
```

Update `config.php`:

```php
$config['admin']['jobs_enabled'] = getEnvValue('ADMIN_JOBS_ENABLED') !== 'false';
$config['webhooks']['openai_signing_secret'] = getEnvValue('WEBHOOK_OPENAI_SIGNING_SECRET');
```

## Incident Runbook

### Stuck Jobs

**Symptom**: Jobs stuck in "running" status

**Diagnosis**:
```bash
# Check running jobs
curl -H "Authorization: Bearer $TOKEN" \
  "https://yourdomain.com/admin-api.php?action=list_jobs&status=running"
```

**Resolution**:
```bash
# Cancel stuck job
curl -X POST -H "Authorization: Bearer $TOKEN" \
  "https://yourdomain.com/admin-api.php?action=cancel_job&id=job_123"

# Or manually reset in database
sqlite3 data/chatbot.db "UPDATE jobs SET status='failed' WHERE locked_at < datetime('now', '-1 hour')"
```

### Failed Ingestions

**Symptom**: Files stuck in "in_progress" status

**Diagnosis**:
```bash
# Check file status
curl -H "Authorization: Bearer $TOKEN" \
  "https://yourdomain.com/admin-api.php?action=list_vector_store_files&id=store_123&status=in_progress"
```

**Resolution**:
1. Check OpenAI dashboard for file status
2. Retry the file ingestion job
3. If failed permanently, remove and re-upload file

### Worker Not Processing Jobs

**Symptom**: Queue depth increasing, no jobs completing

**Diagnosis**:
```bash
# Check worker is running
ps aux | grep worker.php

# Check logs
tail -f /var/log/chatbot-worker.log

# Check health
curl "https://yourdomain.com/admin-api.php?action=health"
```

**Resolution**:
```bash
# Restart worker
sudo systemctl restart chatbot-worker

# Or if using supervisor
sudo supervisorctl restart chatbot-worker
```

### Webhook Not Receiving Events

**Symptom**: OpenAI events not updating database

**Diagnosis**:
```bash
# Check webhook endpoint
curl -X POST https://yourdomain.com/webhooks/openai.php \
  -H "Content-Type: application/json" \
  -d '{"id":"test","type":"test.event","data":{}}'

# Check webhook_events table
sqlite3 data/chatbot.db "SELECT * FROM webhook_events ORDER BY created_at DESC LIMIT 10"
```

**Resolution**:
1. Verify webhook URL is accessible from internet
2. Check HTTPS certificate is valid
3. Verify signing secret matches OpenAI configuration
4. Check server logs for errors

## Security Best Practices

### 1. Token Management

- **Rotate tokens regularly**: Generate new API keys every 90 days
- **Use per-user tokens**: Avoid sharing the legacy ADMIN_TOKEN
- **Revoke unused keys**: Remove API keys for inactive users

### 2. Webhook Security

- **Always use HTTPS**: Never expose webhooks over HTTP
- **Verify signatures**: Configure signing secret in production
- **Rate limiting**: Implement rate limits on webhook endpoint

### 3. Worker Security

- **Run as dedicated user**: Don't run workers as root
- **Limit file access**: Workers only need read access to code, write to data/logs
- **Monitor resource usage**: Set memory and CPU limits

### 4. Database Security

- **Use transactions**: All critical operations wrapped in transactions
- **Regular backups**: Backup `data/chatbot.db` regularly
- **Encryption at rest**: Encrypt database file in production

## Performance Tuning

### Worker Concurrency

Adjust based on workload:

```bash
# Light workload: 1-2 workers
php scripts/worker.php --daemon

# Medium workload: 3-5 workers
for i in {1..5}; do
  php scripts/worker.php --daemon &
done

# Heavy workload: 10+ workers (use systemd template)
```

### Database Optimization

For SQLite in production:

```sql
-- Enable WAL mode for better concurrency
PRAGMA journal_mode=WAL;

-- Increase cache size
PRAGMA cache_size=10000;
```

Consider migrating to PostgreSQL/MySQL for high-traffic deployments.

### Job Cleanup

Run periodic cleanup to remove old completed/failed jobs:

```bash
# Add to crontab
0 2 * * * cd /var/www/chatbot && php -r "require 'includes/DB.php'; require 'includes/JobQueue.php'; \$db = new DB(['database_path' => 'data/chatbot.db']); \$jq = new JobQueue(\$db); \$jq->cleanup(30);"
```

## Monitoring

### Metrics Collection

Integrate with Prometheus:

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'chatbot'
    static_configs:
      - targets: ['chatbot.example.com']
    metrics_path: '/admin-api.php?action=metrics'
    bearer_token: 'your_admin_token_here'
```

### Alerting Rules

Example Prometheus alerts:

```yaml
groups:
  - name: chatbot
    rules:
      - alert: HighFailureRate
        expr: rate(jobs_failed_total[5m]) > 0.1
        annotations:
          summary: "High job failure rate"
      
      - alert: QueueBacklog
        expr: jobs_pending_total > 100
        annotations:
          summary: "Job queue backlog"
      
      - alert: DatabaseDown
        expr: database_up == 0
        annotations:
          summary: "Database connection failed"
```

## Troubleshooting

### Common Issues

1. **"UNIQUE constraint failed" in jobs table**
   - This is normal - indicates job was already claimed by another worker
   - No action needed

2. **Jobs not retrying after failure**
   - Check `max_attempts` in job record
   - Verify `available_at` is in the future (exponential backoff)

3. **Webhook signature verification failing**
   - Verify signing secret matches OpenAI configuration
   - Check webhook payload is not modified in transit
   - Ensure using raw request body for signature

4. **Permission denied errors**
   - Check user role has required permission
   - Verify API key is active and not expired
   - Check user account is active

## Migration Guide

### From Phase 2 to Phase 3

1. **Run new migrations**:
   ```bash
   php -r "require 'includes/DB.php'; \$db = new DB(['database_path' => 'data/chatbot.db']); \$db->runMigrations('db/migrations');"
   ```

2. **Create initial super-admin user**:
   ```bash
   php -r "require 'includes/DB.php'; require 'includes/AdminAuth.php'; \$db = new DB(['database_path' => 'data/chatbot.db']); \$auth = new AdminAuth(\$db, []); \$result = \$auth->migrateLegacyToken(); echo 'API Key: ' . \$result['api_key']['key'] . PHP_EOL;"
   ```

3. **Update Admin UI** to use new API key

4. **Start worker** (optional):
   ```bash
   php scripts/worker.php --daemon --verbose
   ```

5. **Configure webhooks** (optional) in OpenAI dashboard

## Next Steps

The following features from Phase 3 have been **implemented**:
- ✅ **Admin UI for job management** - View, retry, and cancel jobs via the Admin UI
- ✅ **Real-time job updates** - Auto-refresh every 5 seconds on the Jobs page
- ✅ **Audit log export functionality** - Export audit logs to CSV format
- ✅ **Audit log viewer** - Browse and search through admin actions

The following features remain as **future enhancements** (optional):
- Add WebSocket-based real-time job updates (currently using polling)
- Add more sophisticated rate limiting (token bucket, per-user limits)
- Implement job priority queuing
