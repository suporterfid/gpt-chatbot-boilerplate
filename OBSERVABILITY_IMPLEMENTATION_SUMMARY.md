# Observability and Monitoring Implementation Summary

## Overview

This document summarizes the comprehensive observability and monitoring framework implemented for the GPT Chatbot platform, meeting production-ready, SaaS, and enterprise requirements.

## Implementation Status: ✅ COMPLETE

All acceptance criteria from the issue have been met:
- ✅ All critical components have structured logs and metrics
- ✅ Dashboards and alerting rules are available
- ✅ Incident response and troubleshooting guide is included

## Components Delivered

### 1. Structured Logging (`ObservabilityLogger`)

**File**: `includes/ObservabilityLogger.php`

**Features**:
- JSON-formatted log output with consistent schema
- Log levels: debug, info, warn, error, critical
- Automatic PII redaction for sensitive fields
- Buffered logging for performance (flush on errors or buffer full)
- Trace ID propagation from HTTP headers
- Log sampling for high-volume debug logs (10% sampling rate)
- Fallback to temp directory if log path not writable

**Usage**:
```php
$logger = new ObservabilityLogger($config);
$logger->info('component', 'event_name', ['key' => 'value']);
```

**Log Format**:
```json
{
  "ts": "2025-11-06T18:00:00.123Z",
  "level": "info",
  "component": "chat_handler",
  "event": "message_received",
  "trace_id": "a1b2c3d4e5f67890abcdef1234567890",
  "context": {
    "conversation_id": "conv-123",
    "agent_id": "agent-456"
  }
}
```

### 2. Distributed Tracing (`TracingService`)

**File**: `includes/TracingService.php`

**Features**:
- OpenTelemetry-compatible span tracking
- W3C Trace Context propagation (traceparent header)
- Support for Zipkin B3 format
- Nested span hierarchies
- Span events and error recording
- Trace export to file or external systems
- Helper method for wrapping callables with spans

**Usage**:
```php
$tracing = new TracingService($logger, $config);
$spanId = $tracing->startSpan('operation_name', ['attribute' => 'value']);
// ... do work ...
$tracing->endSpan($spanId, 'ok');
```

**Trace Context Headers**:
- `X-Trace-Id`: Custom trace ID
- `traceparent`: W3C standard (version-trace_id-parent_id-flags)
- `X-B3-TraceId`: Zipkin format (optional)

### 3. Enhanced Metrics (`metrics.php`)

**File**: `metrics.php`

**New Metrics Added**:
- `chatbot_usage_tokens_24h{tenant_id}` - Token usage by tenant (last 24h)
- `chatbot_usage_requests_24h{tenant_id}` - Request count by tenant (last 24h)
- `chatbot_channel_messages_1h{channel,direction}` - Channel messages (last hour)
- `chatbot_audit_events_1h{action}` - Audit events (last hour)
- `chatbot_tenants_active` - Active tenant count
- `chatbot_response_time_ms_avg` - Average response time (5m window)
- `chatbot_response_time_ms_max` - Max response time (5m window)
- `chatbot_response_time_ms_min` - Min response time (5m window)
- `chatbot_errors_total{component}` - Error count by component (from logs)

**Existing Metrics Enhanced**:
- All job metrics with status breakdowns
- Worker health metrics
- Agent, vector store, and prompt counts
- Webhook processing status
- Database size (SQLite)

### 4. Health Check Endpoint (`health.php`)

**File**: `health.php`

**Health Checks**:
- Database connectivity (SQLite/MySQL)
- OpenAI API reachability
- Worker liveness (last job timestamp)
- Queue depth (pending jobs)
- Disk space usage
- Memory usage

**Response Format**:
```json
{
  "status": "healthy|degraded|unhealthy",
  "timestamp": "2025-11-06T18:00:00Z",
  "version": "2.1.0",
  "checks": {
    "database": {"status": "healthy", ...},
    "openai_api": {"status": "healthy", ...},
    "worker": {"status": "healthy", ...},
    "queue": {"status": "healthy", ...},
    "disk_space": {"status": "healthy", ...},
    "memory": {"status": "healthy", ...}
  }
}
```

**HTTP Status Codes**:
- `200 OK` - Healthy or degraded
- `503 Service Unavailable` - Unhealthy (critical check failed)

### 5. Alert Manager (`AlertManager`)

**File**: `includes/AlertManager.php`

**Integrations**:
- **Slack**: Webhook notifications with colored attachments
- **Generic Webhook**: HMAC-signed POST requests
- **Email**: PHP mail() for critical alerts
- **PagerDuty**: Events API v2 for critical alerts

**Severities**:
- `INFO` - Informational messages (green/good)
- `WARNING` - Warning conditions (yellow/warning)
- `CRITICAL` - Critical conditions (red/danger)

**Helper Methods**:
- `alertQueueDepth($depth, $threshold)`
- `alertWorkerDown($secondsSinceLastJob)`
- `alertHighErrorRate($errorRate, $component)`
- `alertDiskSpace($usedPercent, $freeBytes)`

### 6. Monitoring Check Script

**File**: `scripts/monitoring_check.php`

**Checks Performed**:
1. Worker health (last job timestamp)
2. Queue depth (pending jobs)
3. Job failure rate (last hour)
4. Disk space usage
5. Database size (SQLite)
6. OpenAI API connectivity (optional)

**Usage**:
```bash
# Run checks manually
php scripts/monitoring_check.php

# Run quietly (no output)
php scripts/monitoring_check.php --quiet

# Skip external checks
php scripts/monitoring_check.php --skip-external

# Run from cron (every 5 minutes)
*/5 * * * * cd /var/www/chatbot && php scripts/monitoring_check.php --quiet
```

### 7. Grafana Dashboard

**File**: `docs/ops/monitoring/grafana-dashboard-system.json`

**Panels** (12 total):
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

**Import to Grafana**:
```bash
curl -X POST http://grafana:3000/api/dashboards/db \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GRAFANA_API_KEY" \
  -d @docs/ops/monitoring/grafana-dashboard-system.json
```

### 8. Alert Rules

**File**: `docs/ops/monitoring/alerts.yml`

**Critical Alerts**:
- `WorkerDown` - Worker hasn't processed jobs in 5+ minutes
- `CriticalJobFailureRate` - >50% jobs failing
- `CriticalQueueDepth` - >500 jobs pending
- `DiskSpaceLow` - <10% disk space remaining
- `MetricsScrapeFailure` - Cannot reach metrics endpoint

**Warning Alerts**:
- `HighJobFailureRate` - >10% jobs failing
- `HighQueueDepth` - >100 jobs pending
- `HighOpenAIErrorRate` - >20% OpenAI API errors
- `HighMemoryUsage` - >80% memory used
- `DatabaseGrowthHigh` - Database growing >10MB/hour

### 9. Documentation

**Files**:
- `docs/OBSERVABILITY.md` (16KB) - Complete observability guide
  - Architecture overview
  - Setup instructions
  - Usage examples
  - Operational runbooks
  - Troubleshooting guide
  - Best practices
- `docs/ops/logs.md` - Existing log aggregation guide
- `docs/ops/incident_runbook.md` - Existing incident response runbook
- `docs/ops/monitoring/alerts.yml` - Existing alert rules
- `examples/README.md` - Examples documentation
- `examples/observability_integration.php` - Working integration examples

## Configuration

### Environment Variables (`.env.example`)

```env
# Observability & Monitoring
OBSERVABILITY_ENABLED=true
STRUCTURED_LOGGING_ENABLED=true
DISTRIBUTED_TRACING_ENABLED=false

# Distributed Tracing
TRACING_ENABLED=false
TRACING_EXPORT_FILE=/var/log/chatbot/traces.log
TRACING_SAMPLE_RATE=0.1
JAEGER_ENDPOINT=

# Alerting
ALERTING_ENABLED=false
ALERT_SLACK_WEBHOOK_URL=
ALERT_WEBHOOK_URL=
ALERT_WEBHOOK_SECRET=
ALERT_EMAIL_TO=
ALERT_EMAIL_FROM=alerts@chatbot.local
ALERT_EMAIL_ALL_SEVERITIES=false
PAGERDUTY_ROUTING_KEY=
```

### Config.php

New configuration sections added:
- `observability` - Enable/disable observability features
- `tracing` - Distributed tracing configuration
- `alerting` - Alert delivery configuration

## Integration Points

### ChatHandler Integration

**File**: `includes/ChatHandler.php`

The `ChatHandler` constructor now accepts optional `$logger` and `$tracing` parameters. If not provided and observability is enabled in config, they are auto-initialized.

**Constructor Signature**:
```php
public function __construct(
    $config, 
    $agentService = null, 
    $auditService = null, 
    $logger = null, 
    $tracing = null
)
```

### Example Integration

See `examples/observability_integration.php` for complete working examples of:
- Logging chat requests with context
- Creating trace spans for operations
- Propagating trace IDs across services
- Error handling with logging and tracing

## Testing

All core components have been tested:

1. **Structured Logging**: ✅ Verified
   - Logs written to `logs/chatbot.log`
   - JSON format validated
   - Trace IDs propagated correctly

2. **Distributed Tracing**: ✅ Verified
   - Spans created and nested
   - Trace context headers generated
   - Trace data exported

3. **Health Check**: ✅ Verified
   - Returns JSON with all checks
   - Handles missing database tables gracefully
   - Correct HTTP status codes

4. **Example Script**: ✅ Verified
   - Runs without errors
   - Demonstrates all features
   - Produces expected output

## Operational Procedures

### 1. Setup Structured Logging

```bash
# Create log directory
sudo mkdir -p /var/log/chatbot
sudo chown www-data:www-data /var/log/chatbot

# Configure log rotation
sudo cp docs/ops/logrotate.conf /etc/logrotate.d/chatbot
```

### 2. Setup Prometheus Scraping

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'chatbot'
    scrape_interval: 30s
    metrics_path: '/metrics.php'
    static_configs:
      - targets: ['chatbot.example.com']
```

### 3. Deploy Alert Rules

```bash
sudo cp docs/ops/monitoring/alerts.yml /etc/prometheus/rules/
sudo systemctl reload prometheus
```

### 4. Import Grafana Dashboard

```bash
# Via provisioning
sudo cp docs/ops/monitoring/grafana-dashboard-system.json \
  /etc/grafana/provisioning/dashboards/
sudo systemctl reload grafana-server
```

### 5. Configure Cron Monitoring

```bash
# Add to crontab
*/5 * * * * cd /var/www/chatbot && php scripts/monitoring_check.php --quiet
```

## SLI/SLO Tracking

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

**Monitoring via Prometheus**:
```promql
# Availability SLI
up{job="chatbot"}

# Latency SLI (p95)
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))

# Error Rate SLI
rate(chatbot_errors_total[5m]) / rate(chatbot_admin_api_requests_total[5m])

# Job Success Rate SLI
rate(chatbot_jobs_total{status="completed"}[5m]) / rate(chatbot_jobs_processed_total[5m])
```

## Context Propagation

Trace IDs are automatically propagated:
1. **Incoming**: From `X-Trace-Id` or `traceparent` HTTP headers
2. **Internal**: Through `ObservabilityLogger` and `TracingService`
3. **Outgoing**: Via `getContextHeaders()` method on `TracingService`

Example:
```php
// Get trace context for outgoing HTTP request
$headers = $tracing->getContextHeaders();
// Returns: ['X-Trace-Id' => '...', 'traceparent' => '...']
```

## Performance Considerations

1. **Log Buffering**: Logs buffered (50 entries) to reduce I/O
2. **Debug Sampling**: Debug logs sampled at 10% to reduce volume
3. **Metrics Caching**: Consider caching metrics query results
4. **Trace Sampling**: Traces can be sampled (configurable rate)

## Security Considerations

1. **PII Redaction**: Automatic redaction of sensitive fields
2. **Log Sanitization**: Sensitive keys replaced with `[REDACTED]`
3. **Access Control**: Metrics and health endpoints should be protected
4. **Alert Delivery**: HMAC signatures for webhook security

## Next Steps (Optional Enhancements)

1. **Log Aggregation**: Deploy ELK stack or CloudWatch
2. **Trace Backend**: Deploy Jaeger or Zipkin
3. **Additional Dashboards**: Create dashboards for specific use cases
4. **Alert Tuning**: Adjust thresholds based on production data
5. **SLO Monitoring**: Implement error budget tracking
6. **Custom Metrics**: Add application-specific business metrics

## Support

For questions or issues:
1. Check `docs/OBSERVABILITY.md` for detailed documentation
2. Review `examples/observability_integration.php` for integration patterns
3. Consult `docs/ops/incident_runbook.md` for operational procedures

## Conclusion

The comprehensive observability and monitoring framework is **production-ready** and meets all requirements for:
- ✅ Proactive issue detection
- ✅ Troubleshooting support
- ✅ Customer SLA tracking
- ✅ SaaS/enterprise compliance

All critical components now have:
- ✅ Structured logging with trace IDs
- ✅ Prometheus-compatible metrics
- ✅ Distributed tracing capability
- ✅ Health checks and alerting
- ✅ Operational dashboards and runbooks
