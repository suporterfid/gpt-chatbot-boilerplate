# Multi-Tenant Observability Guide

## Overview

This guide explains how observability is implemented for multi-tenant operations in the GPT Chatbot Platform, including distributed tracing, tenant-aware metrics, and monitoring best practices.

## Architecture

### Trace ID Propagation Flow

```
User Request → chat-unified.php (trace ID generated/extracted)
    ↓
ChatHandler (trace ID in context)
    ↓
OpenAIClient (trace ID in headers)
    ↓
Job Queue (trace ID in job metadata)
    ↓
Worker (trace ID extracted and propagated)
    ↓
Webhook (trace ID in outgoing requests)
```

### Components with Observability

1. **chat-unified.php** - Request entry point
2. **ChatHandler** - Conversation processing
3. **OpenAIClient** - External API calls
4. **scripts/worker.php** - Background job processing
5. **webhooks/openai.php** - Webhook event handling

## Tenant Context Propagation

### How It Works

1. **Request Identification**: Tenant ID is extracted from:
   - HTTP headers (`X-Tenant-ID`)
   - JWT token claims
   - Request payload
   - Session data

2. **Context Injection**: ObservabilityMiddleware automatically adds tenant context to:
   - Log entries (structured JSON)
   - Metrics labels
   - Trace span attributes

3. **Cross-Service Propagation**: Trace context is propagated via:
   - W3C Trace Context headers (traceparent)
   - Custom headers (X-Trace-ID, X-Tenant-ID)
   - Job metadata in database

### Implementation Example

```php
// Initialize observability
$observability = new ObservabilityMiddleware($config);

// Set tenant context
$tenantId = getTenantIdFromRequest();
$observability->setTenantContext($tenantId, $tenantName);

// Process request - all logs/metrics will include tenant_id
$spanId = $observability->handleRequestStart('api.chat', [
    'tenant_id' => $tenantId,
]);

try {
    // Your business logic
    $result = processRequest($request);
    
    $observability->handleRequestEnd($spanId, 'api.chat', 200);
} catch (Exception $e) {
    $observability->handleError($spanId, $e);
    $observability->handleRequestEnd($spanId, 'api.chat', 500);
}
```

## Structured Logging

### Log Format

All logs are in JSON format with standard fields:

```json
{
  "timestamp": "2024-01-15T10:30:45.123Z",
  "level": "INFO",
  "message": "Processing job",
  "trace_id": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
  "context": {
    "tenant_id": "tenant_123",
    "job_id": "job_456",
    "job_type": "file_ingest",
    "service": "worker"
  }
}
```

### Log Levels

- **EMERGENCY** (0): System is unusable
- **ALERT** (1): Immediate action required
- **CRITICAL** (2): Critical conditions
- **ERROR** (3): Error conditions
- **WARNING** (4): Warning conditions
- **NOTICE** (5): Normal but significant
- **INFO** (6): Informational messages
- **DEBUG** (7): Debug-level messages

### Querying Logs in Loki

```logql
# All logs for a specific tenant
{job="chatbot"} | json | tenant_id="tenant_123"

# Error logs for a tenant
{job="chatbot"} | json | level="ERROR" | tenant_id="tenant_123"

# Jobs by type for a tenant
{job="chatbot"} | json | service="worker" | tenant_id="tenant_123" | job_type="file_ingest"

# Trace all operations for a request
{job="chatbot"} | json | trace_id="a1b2c3d4..."
```

## Metrics

### Tenant-Aware Metrics

All metrics support tenant labeling:

#### Job Metrics

```promql
# Job processing rate per tenant
sum by (tenant_id) (rate(chatbot_tenant_jobs_total[5m]))

# Job error rate per tenant
sum by (tenant_id) (rate(chatbot_tenant_jobs_total{status="failed"}[5m])) 
  / sum by (tenant_id) (rate(chatbot_tenant_jobs_total[5m]))

# Jobs by type and status for a tenant
chatbot_tenant_jobs_total{tenant_id="tenant_123"}
```

#### Worker Metrics

```promql
# Worker job completion rate per tenant
sum by (tenant_id) (rate(chatbot_worker_jobs_completed_total[5m]))

# Worker job failure rate per tenant
sum by (tenant_id) (rate(chatbot_worker_jobs_failed_total[5m]))
```

#### API Metrics

```promql
# API request rate per tenant
sum by (tenant_id) (rate(chatbot_api_requests_total{tenant_id!=""}[5m]))

# OpenAI API calls per tenant
sum by (tenant_id) (rate(chatbot_openai_requests_total{tenant_id!=""}[5m]))
```

#### Tenant Activity

```promql
# Active tenants in last 24h
chatbot_active_tenants_24h

# Conversations per tenant in last 24h
chatbot_tenant_conversations_24h

# Total jobs per tenant
sum by (tenant_id) (chatbot_tenant_jobs_total)
```

## Distributed Tracing

### Trace Context Format

The platform uses W3C Trace Context standard:

```
traceparent: 00-<trace-id>-<span-id>-<trace-flags>
```

Example:
```
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
```

### Creating Spans

```php
// In worker
$spanId = $observability->createSpan('worker.file_ingest', [
    'file_id' => $fileId,
    'tenant_id' => $tenantId,
]);

try {
    // Process file
    $result = ingestFile($fileId);
    
    $observability->endSpan($spanId, [
        'status' => 'success',
        'bytes_processed' => $result['size'],
    ]);
} catch (Exception $e) {
    $observability->handleError($spanId, $e);
    $observability->endSpan($spanId, ['status' => 'error']);
    throw $e;
}
```

### Trace Propagation in Jobs

When enqueueing jobs, include trace metadata:

```php
// Get current trace context
$traceId = $observability->getTracing()->getTraceId();
$tenantId = getCurrentTenantId();

// Enqueue job with trace context
$jobQueue->enqueue('file_ingest', [
    'file_id' => $fileId,
    'vector_store_id' => $storeId,
], [
    'trace_id' => $traceId,
    'tenant_id' => $tenantId,
]);
```

The worker will automatically extract and use this trace ID:

```php
// In worker.php processJob()
$traceId = $job['trace_id'] ?? null;
$tenantId = $job['tenant_id'] ?? null;

if ($observability && $traceId) {
    $observability->getTracing()->setTraceId($traceId);
}

if ($observability && $tenantId) {
    $observability->setTenantContext($tenantId);
}
```

### Trace Propagation in Webhooks

When sending webhooks, include trace headers:

```php
// Get trace propagation headers
$headers = $observability->getTracePropagationHeaders();

// Add to HTTP request
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
    'Content-Type: application/json',
], array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers)));
```

## Dashboards

### Multi-Tenant Dashboard

Location: `observability/dashboards/multi-tenant.json`

Key panels:
- **Active Tenants**: Number of tenants with activity in last 24h
- **Job Processing Rate**: Jobs/sec by tenant
- **Error Rate**: Job failure rate by tenant
- **Resource Usage**: Table showing resource consumption per tenant
- **API Calls**: OpenAI API usage by tenant

### Accessing Dashboards

1. Open Grafana: http://localhost:3000
2. Default credentials: admin/admin
3. Navigate to: Dashboards → Multi-Tenant Observability

## Alerting

### Multi-Tenant Specific Alerts

#### High Error Rate for Tenant

Triggers when a tenant has >20% error rate for 10 minutes.

```yaml
- alert: TenantHighErrorRate
  expr: |
    (
      sum by (tenant_id) (rate(chatbot_tenant_jobs_total{status="failed"}[10m]))
      / sum by (tenant_id) (rate(chatbot_tenant_jobs_total[10m]))
    ) > 0.2
  for: 10m
  labels:
    severity: warning
    component: multi-tenant
```

**Response**:
1. Check logs: `{tenant_id="<id>"} | json | level="ERROR"`
2. Review failed jobs in database
3. Check tenant quota and limits
4. Investigate OpenAI API errors for that tenant

#### Tenant Inactive

Triggers when a tenant hasn't processed jobs for 24h.

```yaml
- alert: TenantInactive
  expr: |
    (time() - max by (tenant_id) (chatbot_tenant_jobs_total{status="completed"})) > 86400
  for: 1h
  labels:
    severity: info
    component: multi-tenant
```

**Response**:
1. Verify if tenant is still active
2. Check if this is expected (e.g., trial ended)
3. Review tenant's last activity
4. Contact tenant if unexpected

#### No Active Tenants

Critical alert when platform has no active tenants.

```yaml
- alert: NoActiveTenants
  expr: chatbot_active_tenants_24h == 0
  for: 1h
  labels:
    severity: critical
    component: multi-tenant
```

**Response**:
1. Check if platform is accessible
2. Review API error rates
3. Check database connectivity
4. Verify worker health
5. Investigate authentication/authorization issues

## Troubleshooting

### Scenario: Missing Trace IDs in Logs

**Symptoms**: Logs don't have trace_id field

**Causes**:
- Observability not initialized
- Trace context not propagated
- Job metadata missing trace_id

**Solutions**:
1. Verify observability is enabled in config
2. Check ObservabilityMiddleware initialization
3. Ensure trace_id is added to job metadata
4. Verify worker extracts trace_id from jobs

### Scenario: Metrics Missing Tenant Labels

**Symptoms**: tenant_id label is empty or missing

**Causes**:
- Tenant context not set
- Tenant ID not extracted from request
- Database lacks tenant_id column

**Solutions**:
1. Add tenant ID extraction in request handler
2. Call `setTenantContext()` after extracting tenant ID
3. Verify database schema includes tenant_id
4. Check TenantContext service is working

### Scenario: Broken Trace Chains

**Symptoms**: Can't trace request across all services

**Causes**:
- Trace headers not propagated
- Async jobs missing trace metadata
- Worker not setting trace ID

**Solutions**:
1. Use `getTracePropagationHeaders()` for HTTP calls
2. Store trace_id in job metadata
3. Call `setTraceId()` in worker before processing
4. Verify W3C Trace Context headers are sent

## Best Practices

### 1. Always Set Tenant Context

```php
// At request start
$tenantId = extractTenantId($request);
if ($tenantId && $observability) {
    $observability->setTenantContext($tenantId);
}
```

### 2. Propagate Trace Context to Background Jobs

```php
// When enqueueing
$metadata = [
    'trace_id' => $observability->getTracing()->getTraceId(),
    'tenant_id' => $currentTenantId,
];
$jobQueue->enqueue($type, $payload, $metadata);
```

### 3. Use Structured Context in Logs

```php
// Good
$logger->info("File uploaded", [
    'file_id' => $fileId,
    'file_size' => $size,
    'tenant_id' => $tenantId,
]);

// Bad
$logger->info("File $fileId uploaded with size $size");
```

### 4. Create Spans for Long Operations

```php
// Any operation >100ms should have a span
$spanId = $observability->createSpan('database.query', [
    'query_type' => 'SELECT',
    'table' => 'jobs',
]);

$result = $db->query($sql);

$observability->endSpan($spanId, [
    'rows_returned' => count($result),
]);
```

### 5. Track Custom Metrics

```php
// Track business metrics with tenant context
$observability->getMetrics()->incrementCounter(
    'chatbot_files_uploaded_total',
    [
        'tenant_id' => $tenantId,
        'file_type' => $fileType,
    ]
);
```

## Configuration

### Environment Variables

```bash
# Enable observability
OBSERVABILITY_ENABLED=true

# Logging
LOG_FORMAT=json
LOG_LEVEL=info

# Tracing
TRACING_ENABLED=true
TRACING_EXPORT=true
TRACING_SAMPLE_RATE=1.0

# Metrics
METRICS_ENABLED=true
METRICS_STORAGE_PATH=/var/lib/chatbot/metrics
```

### Config File

```php
'observability' => [
    'enabled' => true,
    'tracing' => [
        'enabled' => true,
        'export' => true,
        'sample_rate' => 1.0,
    ],
    'metrics' => [
        'enabled' => true,
        'storage_path' => '/var/lib/chatbot/metrics',
    ],
],
```

## Production Considerations

### 1. Log Retention

Configure Loki with appropriate retention:

```yaml
# loki-config.yml
limits_config:
  retention_period: 168h  # 7 days
```

### 2. Metrics Storage

Use persistent volume for Prometheus:

```yaml
# docker-compose.yml
volumes:
  - prometheus-data:/prometheus
```

### 3. Sampling

For high-volume environments, use sampling:

```bash
TRACING_SAMPLE_RATE=0.1  # Sample 10% of requests
```

### 4. Alert Routing

Configure AlertManager with proper notification channels:

```yaml
# alertmanager.yml
receivers:
  - name: 'tenant-alerts'
    slack_configs:
      - channel: '#tenant-alerts'
        api_url: 'https://hooks.slack.com/...'
```

### 5. Dashboard Permissions

In Grafana, set up team-based access:
- Admin team: Full access
- Operations team: View dashboards, edit alerts
- Tenant team: View only their tenant's metrics

## Integration Examples

### Adding Observability to New Endpoints

```php
<?php
require_once 'includes/ObservabilityMiddleware.php';

// Initialize
$observability = new ObservabilityMiddleware($config);

// Extract tenant
$tenantId = $_SERVER['HTTP_X_TENANT_ID'] ?? null;
if ($tenantId) {
    $observability->setTenantContext($tenantId);
}

// Start request span
$spanId = $observability->handleRequestStart('api.new_endpoint', [
    'tenant_id' => $tenantId,
]);

try {
    // Your logic
    $result = handleRequest();
    
    $observability->handleRequestEnd($spanId, 'api.new_endpoint', 200);
    
    echo json_encode($result);
} catch (Exception $e) {
    $observability->handleError($spanId, $e);
    $observability->handleRequestEnd($spanId, 'api.new_endpoint', 500);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
```

### Adding Custom Metrics

```php
// Track custom business metric
$observability->getMetrics()->incrementCounter(
    'chatbot_custom_action_total',
    [
        'tenant_id' => $tenantId,
        'action_type' => $actionType,
        'status' => $success ? 'success' : 'failure',
    ]
);

// Track duration
$observability->getMetrics()->observeHistogram(
    'chatbot_custom_action_duration_seconds',
    $duration,
    [
        'tenant_id' => $tenantId,
        'action_type' => $actionType,
    ]
);
```

## Resources

- [W3C Trace Context](https://www.w3.org/TR/trace-context/)
- [Prometheus Best Practices](https://prometheus.io/docs/practices/)
- [Grafana Dashboards](https://grafana.com/docs/grafana/latest/dashboards/)
- [Loki Query Language](https://grafana.com/docs/loki/latest/logql/)

## Support

For issues or questions about observability:

1. Check logs in Grafana/Loki
2. Review metrics in Prometheus
3. Consult this documentation
4. Open an issue in the repository
