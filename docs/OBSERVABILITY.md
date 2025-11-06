# Observability and Monitoring Guide

## Overview

This guide covers the comprehensive observability and monitoring framework for the GPT Chatbot platform, designed for production-ready operations, proactive issue detection, and enterprise SaaS requirements.

## Table of Contents

- [Architecture](#architecture)
- [Structured Logging](#structured-logging)
- [Distributed Tracing](#distributed-tracing)
- [Metrics and Monitoring](#metrics-and-monitoring)
- [Dashboards](#dashboards)
- [Alerting](#alerting)
- [Setup Instructions](#setup-instructions)
- [Operational Runbooks](#operational-runbooks)
- [Troubleshooting](#troubleshooting)

## Architecture

The observability stack consists of three pillars:

### 1. Logs (Structured JSON Logging)
- **Component**: `ObservabilityLogger` class
- **Format**: JSON with consistent schema
- **Storage**: File-based with rotation
- **Aggregation**: ELK, CloudWatch, or similar

### 2. Metrics (Prometheus-compatible)
- **Endpoint**: `/metrics.php`
- **Format**: Prometheus exposition format
- **Collection**: Prometheus, Grafana Cloud, or compatible
- **Dashboards**: Pre-built Grafana dashboards

### 3. Traces (Distributed Tracing)
- **Component**: `TracingService` class
- **Format**: OpenTelemetry-compatible spans
- **Propagation**: W3C Trace Context headers
- **Visualization**: Jaeger, Zipkin, or similar

## Structured Logging

### Using the Logger

```php
require_once 'includes/ObservabilityLogger.php';

$logger = new ObservabilityLogger($config);

// Log at different levels
$logger->info('chat_handler', 'message_received', [
    'conversation_id' => $conversationId,
    'message_length' => strlen($message),
    'agent_id' => $agentId,
    'tenant_id' => $tenantId
]);

$logger->error('openai_client', 'api_request_failed', [
    'error' => $error->getMessage(),
    'status_code' => $statusCode,
    'retry_attempt' => $attempt
]);
```

### Log Entry Schema

All log entries follow this structure:

```json
{
  "ts": "2025-11-06T18:00:00.123Z",
  "level": "info",
  "component": "chat_handler",
  "event": "message_received",
  "trace_id": "a1b2c3d4e5f67890abcdef1234567890",
  "context": {
    "conversation_id": "conv-123",
    "message_length": 150,
    "agent_id": "agent-456",
    "tenant_id": "tenant-789"
  }
}
```

### Log Levels

- **debug**: Detailed diagnostic information (sampled at 10%)
- **info**: General informational messages
- **warn**: Warning messages for potentially harmful situations
- **error**: Error events that might still allow the application to continue
- **critical**: Critical conditions requiring immediate attention

### Automatic PII Redaction

The logger automatically redacts sensitive fields:
- `password`, `token`, `api_key`, `secret`
- `authorization`, `openai_api_key`, `admin_token`
- `encryption_key`

### Log Buffering

Logs are buffered for performance:
- Buffer size: 50 entries
- Immediate flush on errors/critical events
- Automatic flush on shutdown

## Distributed Tracing

### Using the Tracing Service

```php
require_once 'includes/TracingService.php';

$tracing = new TracingService($logger, $config);

// Start a span
$spanId = $tracing->startSpan('openai.chat_completion', [
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7,
    'agent_id' => $agentId
]);

try {
    // Your code here
    $result = $openai->createChatCompletion($params);
    
    // End span successfully
    $tracing->endSpan($spanId, 'ok', [
        'tokens_used' => $result['usage']['total_tokens']
    ]);
    
} catch (Exception $e) {
    // Record error in span
    $tracing->recordError($spanId, $e->getMessage(), [
        'exception_class' => get_class($e)
    ]);
    $tracing->endSpan($spanId, 'error');
    throw $e;
}

// Flush trace data at end of request
$tracing->flush();
```

### Span Hierarchy

Spans can be nested to represent call hierarchies:

```
Request Span (chat_handler.process_message)
├─ OpenAI API Call (openai.chat_completion)
│  ├─ HTTP Request (http.post)
│  └─ Response Processing (openai.parse_response)
├─ Database Write (db.insert)
└─ Audit Log (audit.record)
```

### Trace Context Propagation

Trace IDs are propagated via HTTP headers:
- `X-Trace-Id`: Custom trace ID header
- `traceparent`: W3C Trace Context standard
- `X-B3-TraceId`: Zipkin B3 format (optional)

### Helper: Wrap with Trace

```php
$result = $tracing->trace('database.query', function() use ($db, $sql) {
    return $db->query($sql);
}, ['query' => 'SELECT * FROM users']);
```

## Metrics and Monitoring

### Available Metrics

#### Application Info
```
chatbot_info{version="2.1.0", php_version="8.2.0", db_type="sqlite"} 1
```

#### Job Metrics
```
chatbot_jobs_total{status="completed"} 1250
chatbot_jobs_total{status="failed"} 25
chatbot_jobs_queue_depth 42
chatbot_jobs_processed_total 1275
chatbot_jobs_by_type{type="file_ingest", status="completed"} 500
```

#### Worker Health
```
chatbot_worker_healthy 1
chatbot_worker_last_job_seconds 45
```

#### Agent Metrics
```
chatbot_agents_total 5
chatbot_agents_default 1
```

#### Response Time Metrics
```
chatbot_response_time_ms_avg 250.5
chatbot_response_time_ms_max 1200.0
chatbot_response_time_ms_min 50.0
```

#### Usage Metrics (if enabled)
```
chatbot_usage_tokens_24h{tenant_id="tenant-123"} 50000
chatbot_usage_requests_24h{tenant_id="tenant-123"} 250
```

#### Error Metrics
```
chatbot_errors_total{component="chat_handler"} 5
chatbot_errors_total{component="openai_client"} 12
```

### Health Check Endpoint

**Endpoint**: `/health.php`

Returns JSON with detailed health status:

```json
{
  "status": "healthy",
  "timestamp": "2025-11-06T18:00:00Z",
  "version": "2.1.0",
  "checks": {
    "database": {
      "status": "healthy",
      "response_time_ms": 5.2,
      "type": "sqlite"
    },
    "openai_api": {
      "status": "healthy",
      "response_time_ms": 150.5,
      "http_code": 200
    },
    "worker": {
      "status": "healthy",
      "last_job_seconds_ago": 45
    },
    "queue": {
      "status": "healthy",
      "pending_jobs": 42
    },
    "disk_space": {
      "status": "healthy",
      "free_bytes": 50000000000,
      "total_bytes": 100000000000,
      "used_percent": 50.0
    },
    "memory": {
      "status": "healthy",
      "used_bytes": 134217728,
      "limit_bytes": 268435456,
      "used_percent": 50.0
    }
  }
}
```

**Status Codes**:
- `200 OK`: All checks healthy or degraded
- `503 Service Unavailable`: One or more critical checks failed

## Dashboards

### Grafana Dashboard - System Overview

Location: `docs/ops/monitoring/grafana-dashboard-system.json`

**Panels**:
1. Request Rate (by resource)
2. Job Queue Depth (with alert threshold)
3. Job Success Rate (percentage)
4. Worker Health Status
5. Active Agents Count
6. Database Size
7. Job Processing Duration (p50, p95, p99)
8. Error Rate by Component
9. Response Time (avg and max)
10. Webhook Processing Status
11. Jobs by Type (pie chart)
12. System Health Summary (table)

### Import Dashboard to Grafana

```bash
# Via API
curl -X POST http://grafana:3000/api/dashboards/db \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GRAFANA_API_KEY" \
  -d @docs/ops/monitoring/grafana-dashboard-system.json

# Or via UI: Configuration → Dashboards → Import → Upload JSON
```

## Alerting

### Alert Configuration

Location: `docs/ops/monitoring/alerts.yml`

**Critical Alerts**:
- `WorkerDown`: Worker hasn't processed jobs in 5+ minutes
- `CriticalJobFailureRate`: >50% jobs failing
- `CriticalQueueDepth`: >500 jobs pending
- `DiskSpaceLow`: <10% disk space remaining
- `MetricsScrapeFailure`: Cannot reach metrics endpoint

**Warning Alerts**:
- `HighJobFailureRate`: >10% jobs failing
- `HighQueueDepth`: >100 jobs pending
- `HighOpenAIErrorRate`: >20% OpenAI API errors
- `HighMemoryUsage`: >80% memory used
- `DatabaseGrowthHigh`: Database growing >10MB/hour

### Alerting Integrations

#### Slack Webhook

Add to Prometheus AlertManager configuration:

```yaml
receivers:
  - name: 'slack-chatbot'
    slack_configs:
      - api_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
        channel: '#chatbot-alerts'
        title: 'ChatBot Alert: {{ .GroupLabels.alertname }}'
        text: '{{ range .Alerts }}{{ .Annotations.description }}{{ end }}'
```

#### PagerDuty

```yaml
receivers:
  - name: 'pagerduty-chatbot'
    pagerduty_configs:
      - service_key: 'YOUR_PAGERDUTY_SERVICE_KEY'
        description: '{{ .GroupLabels.alertname }}: {{ .CommonAnnotations.summary }}'
```

## Setup Instructions

### 1. Configure Structured Logging

Update `.env`:

```env
LOG_LEVEL=info
LOG_FILE=/var/log/chatbot/application.log
LOG_MAX_SIZE=10485760
LOG_MAX_FILES=5
```

Create log directory:

```bash
sudo mkdir -p /var/log/chatbot
sudo chown www-data:www-data /var/log/chatbot
sudo chmod 755 /var/log/chatbot
```

### 2. Set Up Prometheus Scraping

Add to `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'chatbot'
    scrape_interval: 30s
    scrape_timeout: 10s
    metrics_path: '/metrics.php'
    static_configs:
      - targets: ['chatbot.example.com']
        labels:
          environment: 'production'
```

### 3. Deploy Alert Rules

```bash
# Copy alert rules to Prometheus
sudo cp docs/ops/monitoring/alerts.yml /etc/prometheus/rules/chatbot.yml

# Update prometheus.yml
rule_files:
  - "rules/chatbot.yml"

# Reload Prometheus
sudo systemctl reload prometheus
```

### 4. Import Grafana Dashboard

```bash
# Using provisioning (recommended)
sudo cp docs/ops/monitoring/grafana-dashboard-system.json \
  /etc/grafana/provisioning/dashboards/

sudo systemctl reload grafana-server
```

### 5. Configure Log Aggregation (Optional)

#### Option A: Filebeat to Elasticsearch

```bash
# Install Filebeat
curl -L -O https://artifacts.elastic.co/downloads/beats/filebeat/filebeat-8.11.0-amd64.deb
sudo dpkg -i filebeat-8.11.0-amd64.deb

# Configure filebeat.yml
sudo nano /etc/filebeat/filebeat.yml
```

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
  hosts: ["https://elasticsearch:9200"]
  index: "chatbot-%{+yyyy.MM.dd}"
```

#### Option B: CloudWatch Logs

```bash
# Install CloudWatch agent
wget https://s3.amazonaws.com/amazoncloudwatch-agent/ubuntu/amd64/latest/amazon-cloudwatch-agent.deb
sudo dpkg -i amazon-cloudwatch-agent.deb

# Configure
sudo nano /opt/aws/amazon-cloudwatch-agent/etc/config.json
```

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
            "timezone": "UTC"
          }
        ]
      }
    }
  }
}
```

### 6. Enable Distributed Tracing (Optional)

For full distributed tracing with Jaeger:

```bash
# Run Jaeger all-in-one
docker run -d --name jaeger \
  -e COLLECTOR_ZIPKIN_HOST_PORT=:9411 \
  -p 5775:5775/udp \
  -p 6831:6831/udp \
  -p 6832:6832/udp \
  -p 5778:5778 \
  -p 16686:16686 \
  -p 14250:14250 \
  -p 14268:14268 \
  -p 14269:14269 \
  -p 9411:9411 \
  jaegertracing/all-in-one:latest
```

Update `config.php`:

```php
'tracing' => [
    'enabled' => true,
    'export_file' => '/var/log/chatbot/traces.log',
    'jaeger_endpoint' => 'http://localhost:14268/api/traces'
]
```

## Operational Runbooks

See also: [Incident Response Runbook](incident_runbook.md)

### Common Monitoring Queries

#### Find All Errors in Last Hour

Using structured logs:

```bash
tail -n 10000 /var/log/chatbot/application.log | \
  jq 'select(.level == "error" and (.ts | fromdateiso8601) > (now - 3600))'
```

#### Check Error Rate by Component

```bash
cat /var/log/chatbot/application.log | \
  jq -r 'select(.level == "error") | .component' | \
  sort | uniq -c | sort -nr
```

#### Find Slow Traces

```bash
cat /var/log/chatbot/traces.log | \
  jq 'select(.total_duration_ms > 5000)' | \
  jq -r '[.trace_id, .total_duration_ms, .span_count] | @csv'
```

#### Prometheus Queries

```promql
# Error rate by component (5m window)
rate(chatbot_errors_total[5m])

# Job success rate
(rate(chatbot_jobs_total{status="completed"}[5m]) / 
 rate(chatbot_jobs_processed_total[5m])) * 100

# Queue growth rate
deriv(chatbot_jobs_queue_depth[30m])

# Average response time
avg_over_time(chatbot_response_time_ms_avg[5m])

# Worker uptime
time() - chatbot_worker_last_job_seconds
```

### SLI/SLO Tracking

**Service Level Indicators (SLIs)**:
- Availability: % of time health check returns 200
- Latency: p95 response time < 2 seconds
- Error Rate: < 1% of requests result in errors
- Job Success Rate: > 99% jobs complete successfully

**Service Level Objectives (SLOs)**:
- 99.9% availability (8.76 hours downtime/year)
- 99% of requests < 2s response time
- < 1% error rate
- 99% job success rate

**Prometheus Recording Rules**:

```yaml
groups:
  - name: sli_recording
    interval: 60s
    rules:
      - record: sli:availability:ratio
        expr: up{job="chatbot"}
      
      - record: sli:latency:p95
        expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))
      
      - record: sli:error_rate:ratio
        expr: |
          (
            rate(chatbot_errors_total[5m]) /
            rate(chatbot_admin_api_requests_total[5m])
          )
      
      - record: sli:job_success_rate:ratio
        expr: |
          (
            rate(chatbot_jobs_total{status="completed"}[5m]) /
            rate(chatbot_jobs_processed_total[5m])
          )
```

## Troubleshooting

### Logs Not Appearing

1. Check file permissions:
   ```bash
   ls -l /var/log/chatbot/
   # Should be writable by www-data
   ```

2. Check disk space:
   ```bash
   df -h /var/log
   ```

3. Test logger directly:
   ```bash
   cd /var/www/chatbot
   sudo -u www-data php -r "
   require 'config.php';
   require 'includes/ObservabilityLogger.php';
   \$logger = new ObservabilityLogger(\$config);
   \$logger->info('test', 'manual_test', ['timestamp' => time()]);
   \$logger->flush();
   "
   ```

### Metrics Endpoint Errors

1. Check database connectivity:
   ```bash
   curl -s http://localhost/health.php | jq .checks.database
   ```

2. Test metrics endpoint:
   ```bash
   curl http://localhost/metrics.php
   ```

3. Check PHP error log:
   ```bash
   sudo tail -50 /var/log/php8.2-fpm.log
   ```

### Trace IDs Not Propagating

1. Verify headers are set:
   ```bash
   curl -H "X-Trace-Id: test123" -v http://localhost/health.php
   ```

2. Check logs for trace_id field:
   ```bash
   tail /var/log/chatbot/application.log | jq .trace_id
   ```

### High Metrics Scrape Duration

If `chatbot_metrics_scrape_duration_seconds` > 5:

1. Optimize database queries (add indexes)
2. Reduce metric cardinality
3. Sample less frequently
4. Consider separate metrics service

## Best Practices

### 1. Log Context Enrichment

Always include relevant context:

```php
$logger->info('chat_handler', 'message_received', [
    'conversation_id' => $conversationId,
    'agent_id' => $agentId,
    'tenant_id' => $tenantId,
    'user_id' => $userId,
    'message_length' => strlen($message),
    'api_type' => $apiType
]);
```

### 2. Trace Critical Paths

Trace expensive operations:

```php
// Database queries
$tracing->trace('db.query', function() use ($db, $sql) {
    return $db->query($sql);
});

// OpenAI API calls
$tracing->trace('openai.chat_completion', function() use ($openai) {
    return $openai->createChatCompletion($params);
});

// Background jobs
$tracing->trace('job.execute', function() use ($job) {
    return $job->execute();
});
```

### 3. Use Meaningful Metric Names

Follow Prometheus naming conventions:
- `chatbot_<component>_<metric>_<unit>`
- Use `_total` suffix for counters
- Use `_bytes`, `_seconds`, `_percent` for units

### 4. Alert on SLOs, Not Symptoms

Alert on what matters to users:
- High error rate (impacts user experience)
- Slow response time (impacts user experience)
- Service unavailable (impacts availability SLO)

Not on:
- High CPU (symptom, not user impact)
- High memory (symptom, not user impact)

### 5. Regular Dashboard Reviews

- Weekly: Review dashboard for trends
- Monthly: Analyze error patterns
- Quarterly: Review and update SLOs

## See Also

- [Incident Response Runbook](incident_runbook.md)
- [Log Aggregation Guide](logs.md)
- [Alert Rules](monitoring/alerts.yml)
- [Production Deployment](production-deploy.md)
