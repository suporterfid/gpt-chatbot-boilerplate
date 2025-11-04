# Logging & Log Aggregation Guide

## Overview

This guide covers structured logging implementation and log aggregation setup for the GPT Chatbot system. The application uses structured JSON logging to facilitate monitoring, debugging, and compliance.

## Structured Logging Format

All application logs follow a structured JSON format:

```json
{
  "ts": "2025-11-04T12:30:00.000Z",
  "level": "info",
  "component": "admin_api",
  "event": "agent_created",
  "context": {
    "agent_id": "abc-123",
    "agent_name": "Customer Support Agent",
    "actor": "admin@example.com",
    "ip": "192.168.1.1"
  }
}
```

### Log Levels

- **debug**: Detailed diagnostic information
- **info**: General informational messages
- **warn**: Warning messages for potentially harmful situations
- **error**: Error events that might still allow the application to continue
- **critical**: Critical conditions requiring immediate attention

### Standard Fields

All log entries include:

- `ts`: ISO 8601 timestamp with timezone
- `level`: Log level (debug, info, warn, error, critical)
- `component`: Application component (admin_api, worker, chat_handler, etc.)
- `event`: Event type or action name
- `context`: Additional contextual data (varies by event)

## Log File Locations

```bash
# Application logs
/var/log/chatbot/application.log

# Access logs (when using nginx/apache)
/var/log/nginx/chatbot_access.log
/var/log/nginx/chatbot_error.log

# Worker logs
/var/log/chatbot/worker.log

# Database query logs (optional, for debugging)
/var/log/chatbot/queries.log
```

## Security Considerations

### Sensitive Data Exclusion

The following data is NEVER logged:

- OpenAI API keys
- Admin tokens
- User passwords
- Database credentials
- Raw authentication tokens
- Credit card information
- Any PII unless explicitly required

### Log Sanitization

Before logging:

```php
// Example: Sanitize user input
function sanitizeForLog($data) {
    // Remove sensitive keys
    $sensitiveKeys = ['password', 'token', 'api_key', 'secret'];
    
    if (is_array($data)) {
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }
    }
    
    return $data;
}
```

## Log Aggregation

### Option 1: Elastic Stack (ELK)

**Components:**
- Elasticsearch: Log storage and search
- Logstash or Filebeat: Log shipping
- Kibana: Visualization and dashboards

**Setup with Filebeat:**

1. **Install Filebeat:**

```bash
# Ubuntu/Debian
curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-8.11.0-amd64.deb
sudo dpkg -i filebeat-8.11.0-amd64.deb
```

2. **Configure Filebeat** (`/etc/filebeat/filebeat.yml`):

```yaml
filebeat.inputs:
  - type: log
    enabled: true
    paths:
      - /var/log/chatbot/*.log
    json.keys_under_root: true
    json.add_error_key: true
    fields:
      app: chatbot
      environment: production

output.elasticsearch:
  hosts: ["https://elasticsearch.example.com:9200"]
  username: "filebeat_internal"
  password: "${ELASTICSEARCH_PASSWORD}"
  index: "chatbot-%{+yyyy.MM.dd}"

setup.kibana:
  host: "https://kibana.example.com:5601"

processors:
  - add_host_metadata: ~
  - add_cloud_metadata: ~
```

3. **Start Filebeat:**

```bash
sudo systemctl enable filebeat
sudo systemctl start filebeat
```

4. **Create Kibana Dashboard:**

Access Kibana and create visualizations for:
- Request rate by endpoint
- Error rate over time
- Job processing throughput
- Queue depth
- Top error messages

### Option 2: AWS CloudWatch

**Setup with CloudWatch Agent:**

1. **Install CloudWatch Agent:**

```bash
wget https://s3.amazonaws.com/amazoncloudwatch-agent/ubuntu/amd64/latest/amazon-cloudwatch-agent.deb
sudo dpkg -i amazon-cloudwatch-agent.deb
```

2. **Configure CloudWatch Agent** (`/opt/aws/amazon-cloudwatch-agent/etc/config.json`):

```json
{
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          {
            "file_path": "/var/log/chatbot/application.log",
            "log_group_name": "/chatbot/production/application",
            "log_stream_name": "{instance_id}",
            "timezone": "UTC",
            "timestamp_format": "%Y-%m-%dT%H:%M:%S"
          },
          {
            "file_path": "/var/log/chatbot/worker.log",
            "log_group_name": "/chatbot/production/worker",
            "log_stream_name": "{instance_id}",
            "timezone": "UTC"
          }
        ]
      }
    }
  }
}
```

3. **Start CloudWatch Agent:**

```bash
sudo /opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl \
  -a fetch-config \
  -m ec2 \
  -s \
  -c file:/opt/aws/amazon-cloudwatch-agent/etc/config.json
```

4. **Set up CloudWatch Insights queries:**

```
# Find all errors
fields @timestamp, level, component, event, context.error
| filter level = "error"
| sort @timestamp desc
| limit 100

# Job processing metrics
fields @timestamp, event, context.job_id, context.duration_ms
| filter event = "job_completed"
| stats avg(context.duration_ms) as avg_duration by bin(5m)

# API error rate
fields @timestamp, event, context.status_code
| filter component = "admin_api" and context.status_code >= 400
| stats count() by bin(1m)
```

### Option 3: LogDNA / Mezmo

**Setup:**

1. **Install LogDNA Agent:**

```bash
echo "deb https://repo.logdna.com stable main" | sudo tee /etc/apt/sources.list.d/logdna.list
wget -O- https://repo.logdna.com/logdna.gpg | sudo apt-key add -
sudo apt-get update
sudo apt-get install logdna-agent
```

2. **Configure LogDNA:**

```bash
sudo logdna-agent -k YOUR_INGESTION_KEY
sudo logdna-agent -d /var/log/chatbot
sudo logdna-agent -t chatbot,production
sudo systemctl start logdna-agent
```

## Log Rotation

Configure log rotation to prevent disk space issues:

### Using logrotate

Create `/etc/logrotate.d/chatbot`:

```
/var/log/chatbot/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    missingok
    create 0644 www-data www-data
    sharedscripts
    postrotate
        # Reload application if needed
        # systemctl reload chatbot
    endscript
}
```

Test log rotation:

```bash
sudo logrotate -d /etc/logrotate.d/chatbot
sudo logrotate -f /etc/logrotate.d/chatbot
```

## Example Log Entries

### Successful Agent Creation

```json
{
  "ts": "2025-11-04T12:30:00.123Z",
  "level": "info",
  "component": "admin_api",
  "event": "agent_created",
  "context": {
    "agent_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "agent_name": "Customer Support Agent",
    "actor": "admin@example.com",
    "ip": "192.168.1.100",
    "api_type": "responses"
  }
}
```

### Job Failure

```json
{
  "ts": "2025-11-04T12:31:15.456Z",
  "level": "error",
  "component": "worker",
  "event": "job_failed",
  "context": {
    "job_id": "job-12345",
    "job_type": "file_ingest",
    "attempts": 3,
    "error": "OpenAI API timeout after 30s",
    "duration_ms": 30042,
    "will_retry": false
  }
}
```

### Authentication Failure

```json
{
  "ts": "2025-11-04T12:32:00.789Z",
  "level": "warn",
  "component": "admin_api",
  "event": "auth_failed",
  "context": {
    "reason": "invalid_token",
    "ip": "203.0.113.42",
    "user_agent": "Mozilla/5.0...",
    "endpoint": "/admin-api.php/agents"
  }
}
```

### Rate Limit Exceeded

```json
{
  "ts": "2025-11-04T12:33:00.012Z",
  "level": "warn",
  "component": "admin_api",
  "event": "rate_limit_exceeded",
  "context": {
    "ip": "198.51.100.50",
    "user": "admin@example.com",
    "requests_in_window": 305,
    "window_seconds": 60,
    "limit": 300
  }
}
```

## Monitoring Queries

### Common Search Patterns

#### Find all errors in last hour

```bash
# Using jq
tail -n 10000 /var/log/chatbot/application.log | \
  jq 'select(.level == "error" and (.ts | fromdateiso8601) > (now - 3600))'
```

#### Count events by type

```bash
cat /var/log/chatbot/application.log | \
  jq -r '.event' | \
  sort | uniq -c | sort -nr
```

#### Find slow jobs

```bash
cat /var/log/chatbot/worker.log | \
  jq 'select(.event == "job_completed" and .context.duration_ms > 5000)'
```

## Performance Considerations

### Asynchronous Logging

For high-traffic applications, use asynchronous logging:

```php
// Use a queue for log writes
function logAsync($entry) {
    $queue = new SplQueue();
    $queue->enqueue(json_encode($entry));
    
    // Flush queue periodically or on shutdown
    register_shutdown_function(function() use ($queue) {
        while (!$queue->isEmpty()) {
            file_put_contents(
                '/var/log/chatbot/application.log',
                $queue->dequeue() . "\n",
                FILE_APPEND
            );
        }
    });
}
```

### Log Sampling

For very high-volume events, implement sampling:

```php
function shouldLog($event, $level) {
    // Always log errors and warnings
    if (in_array($level, ['error', 'warn', 'critical'])) {
        return true;
    }
    
    // Sample debug logs at 10%
    if ($level === 'debug') {
        return (mt_rand(1, 100) <= 10);
    }
    
    return true;
}
```

## Compliance & Retention

### Data Retention Policy

- **Application logs**: 30 days
- **Audit logs**: 1 year
- **Error logs**: 90 days
- **Access logs**: 30 days

### GDPR Compliance

For GDPR compliance:

1. **Log only necessary PII**
2. **Provide data export capability**
3. **Implement right-to-erasure** for user-specific logs
4. **Encrypt logs at rest** if they contain sensitive data

```bash
# Example: Delete logs containing specific user email
grep -v "user@example.com" /var/log/chatbot/application.log > /tmp/cleaned.log
mv /tmp/cleaned.log /var/log/chatbot/application.log
```

## Troubleshooting

### Logs not appearing

1. Check file permissions:
   ```bash
   ls -l /var/log/chatbot/
   # Should be writable by web server user (www-data)
   ```

2. Check disk space:
   ```bash
   df -h /var/log
   ```

3. Verify log rotation:
   ```bash
   sudo logrotate -d /etc/logrotate.d/chatbot
   ```

### High log volume

1. Enable log sampling for debug logs
2. Increase log rotation frequency
3. Consider using log levels more selectively
4. Monitor disk I/O and adjust accordingly

## See Also

- [Production Deployment Guide](production-deploy.md)
- [Monitoring Guide](monitoring/alerts.yml)
- [Incident Runbook](incident_runbook.md)
