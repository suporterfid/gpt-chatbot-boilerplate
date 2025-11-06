# Observability and Monitoring Guide

## Overview

This chatbot platform includes a comprehensive observability framework with:
- **Structured JSON logging** with distributed tracing
- **Prometheus metrics** for performance monitoring
- **Distributed tracing** with W3C Trace Context propagation
- **Pre-configured dashboards** for Grafana
- **Automated alerting** for critical conditions
- **Log aggregation** with Loki

## Architecture

### Components

1. **ObservabilityLogger** - Structured JSON logging with context propagation
2. **MetricsCollector** - Real-time metrics collection and aggregation
3. **TracingService** - Distributed tracing with OpenTelemetry-compatible format
4. **ObservabilityMiddleware** - Unified interface integrating all components

### Data Flow

```
API Request → ObservabilityMiddleware → Logger/Metrics/Tracing
                                      ↓
                                   Services
                                      ↓
                                OpenAI API (with trace headers)
                                      ↓
                                   Response
                                      ↓
                            Metrics/Logs/Traces
                                      ↓
                        Prometheus/Loki/Grafana
```

## Configuration

### Environment Variables

```bash
# Observability
OBSERVABILITY_ENABLED=true
LOG_LEVEL=info  # debug, info, warning, error, critical
LOG_FORMAT=json  # json or text
LOG_FILE=php://stderr  # or path to log file

# Tracing
TRACING_ENABLED=true
TRACING_EXPORT=false  # Set to true to export spans to logs
TRACING_SAMPLE_RATE=1.0  # Sample rate (0.0 to 1.0)

# Metrics
METRICS_ENABLED=true
METRICS_STORAGE_PATH=/tmp/chatbot_metrics
```

### Configuration in config.php

```php
'observability' => [
    'enabled' => true,
    'tracing' => [
        'enabled' => true,
        'export' => false,
        'sample_rate' => 1.0,
    ],
    'metrics' => [
        'enabled' => true,
        'storage_path' => '/tmp/chatbot_metrics',
    ],
],
```

## Structured Logging

### Log Format

All logs are output as JSON with the following structure:

```json
{
  "timestamp": "2024-11-06T17:55:00.000Z",
  "level": "INFO",
  "message": "API request completed",
  "trace_id": "a1b2c3d4e5f6g7h8",
  "context": {
    "endpoint": "/chat-unified.php",
    "method": "POST",
    "duration_ms": 1234.56,
    "status_code": 200,
    "agent_id": "agent-123",
    "tenant_id": "tenant-456"
  }
}
```

### Using the Logger

```php
// Initialize observability
require_once 'includes/ObservabilityMiddleware.php';
$observability = new ObservabilityMiddleware($config);
$logger = $observability->getLogger();

// Set context
$logger->setContext([
    'tenant_id' => 'tenant-123',
    'agent_id' => 'agent-456',
]);

// Log messages
$logger->info("Processing request", ['user_id' => $userId]);
$logger->error("Failed to process", ['exception' => $exception]);
$logger->debug("Debug information", ['data' => $debugData]);
```

## Distributed Tracing

### Trace ID Propagation

Trace IDs are automatically:
1. Extracted from incoming HTTP headers (`traceparent`, `X-Trace-Id`)
2. Generated if not present
3. Propagated to all logs
4. Added to outgoing OpenAI API calls
5. Included in response headers

### Using Tracing

```php
$tracing = $observability->getTracing();

// Create a span
$spanId = $tracing->startSpan('database.query', [
    'query_type' => 'SELECT',
    'table' => 'agents',
]);

try {
    // Execute operation
    $result = $db->query($sql);
    $tracing->endSpan($spanId, ['rows_returned' => count($result)]);
} catch (Exception $e) {
    $tracing->recordError($spanId, $e);
    $tracing->endSpan($spanId, ['status' => 'error']);
    throw $e;
}

// Or use the trace helper
$result = $tracing->trace('operation.name', function($spanId) {
    // Your code here
    return $result;
}, ['attribute' => 'value']);
```

### Trace Context Format

Headers follow W3C Trace Context specification:

```
traceparent: 00-a1b2c3d4e5f6g7h8-i9j0k1l2m3n4-01
X-Trace-Id: a1b2c3d4e5f6g7h8
X-Span-Id: i9j0k1l2m3n4
```

## Metrics

### Available Metrics

#### API Metrics
- `chatbot_api_requests_total` - Total API requests by endpoint, method, status
- `chatbot_api_errors_total` - Total API errors by status code
- `chatbot_api_request_duration_seconds` - API request latency histogram

#### OpenAI Metrics
- `chatbot_openai_requests_total` - Total OpenAI API calls
- `chatbot_openai_errors_total` - Failed OpenAI API calls
- `chatbot_openai_request_duration_seconds` - OpenAI API latency

#### Agent Metrics
- `chatbot_agent_requests_total` - Requests per agent
- `chatbot_agent_request_duration_seconds` - Agent response time

#### Token Usage (Billing)
- `chatbot_tokens_total` - Total tokens consumed
- `chatbot_tokens_prompt_total` - Prompt tokens
- `chatbot_tokens_completion_total` - Completion tokens

#### Job Queue Metrics
- `chatbot_jobs_total` - Total jobs by status
- `chatbot_jobs_queue_depth` - Current queue depth
- `chatbot_jobs_processed_total` - Successfully processed jobs
- `chatbot_jobs_failed_total` - Failed jobs

#### System Metrics
- `chatbot_info` - Application information
- `chatbot_database_size_bytes` - Database file size
- `chatbot_worker_healthy` - Worker health status

### Using Metrics

```php
$metrics = $observability->getMetrics();

// Track API request
$metrics->trackApiRequest(
    endpoint: '/api/chat',
    method: 'POST',
    duration: 1.234,
    statusCode: 200,
    extraLabels: ['tenant_id' => 'tenant-123']
);

// Track OpenAI call
$metrics->trackOpenAICall(
    apiType: 'responses',
    model: 'gpt-4o',
    duration: 2.5,
    success: true
);

// Track token usage
$metrics->trackTokenUsage(
    promptTokens: 150,
    completionTokens: 75,
    model: 'gpt-4o',
    extraLabels: ['agent_id' => 'agent-123']
);

// Custom counters and gauges
$metrics->incrementCounter('custom_metric', ['label' => 'value']);
$metrics->setGauge('queue_size', 42, ['queue' => 'default']);
$metrics->observeHistogram('operation_duration', 1.5, ['operation' => 'parse']);
```

### Accessing Metrics

Metrics are exposed at `/metrics.php` in Prometheus format:

```bash
curl http://localhost/metrics.php
```

## Dashboards

### Available Dashboards

1. **Overview Dashboard** (`observability/dashboards/overview.json`)
   - API request rate and errors
   - Latency percentiles (P95, P99)
   - OpenAI API status
   - Job queue health
   - Token usage
   - Active alerts

### Importing Dashboards

1. Access Grafana at `http://localhost:3000`
2. Login (default: admin/admin)
3. Go to Dashboards → Import
4. Upload JSON files from `observability/dashboards/`

## Alerts

### Alert Rules

All alerting rules are defined in `observability/alerts/chatbot-alerts.yml`:

- **HighErrorRate** - API error rate > 5% for 5 minutes
- **OpenAIAPIFailures** - OpenAI API errors detected
- **HighAPILatency** - P95 latency > 5 seconds
- **JobQueueBacklog** - More than 100 pending jobs
- **WorkerUnhealthy** - Worker inactive for 5+ minutes
- **HighFailedJobRate** - Job failure rate > 10%
- **HighRateLimitHits** - Excessive rate limiting
- **DatabaseSizeWarning** - Database > 1GB
- **TokenUsageSpike** - Unusual token consumption
- **WebhookProcessingLag** - Webhook processing delays
- **ServiceDown** - Service unavailable
- **HighMemoryUsage** - Memory usage > 80%

### Configuring Alerts

Edit `observability/docker/alertmanager.yml` to configure notification channels:

```yaml
receivers:
  - name: 'critical-alerts'
    slack_configs:
      - channel: '#chatbot-critical'
        api_url: 'YOUR_SLACK_WEBHOOK_URL'
    email_configs:
      - to: 'oncall@example.com'
    pagerduty_configs:
      - service_key: 'YOUR_PAGERDUTY_KEY'
```

## Deployment

### Option 1: Docker Compose (Recommended)

```bash
cd observability/docker
docker-compose up -d
```

This starts:
- Prometheus (`:9090`)
- Grafana (`:3000`)
- Loki (`:3100`)
- AlertManager (`:9093`)
- Promtail (log shipper)

### Option 2: Manual Setup

#### Install Prometheus

```bash
# Download and install Prometheus
wget https://github.com/prometheus/prometheus/releases/download/v2.45.0/prometheus-2.45.0.linux-amd64.tar.gz
tar xvfz prometheus-*.tar.gz
cd prometheus-*

# Copy configuration
cp observability/docker/prometheus.yml .
cp -r observability/alerts .

# Start Prometheus
./prometheus --config.file=prometheus.yml
```

#### Install Grafana

```bash
# Ubuntu/Debian
sudo apt-get install -y software-properties-common
sudo add-apt-repository "deb https://packages.grafana.com/oss/deb stable main"
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
sudo apt-get update
sudo apt-get install grafana

# Start Grafana
sudo systemctl start grafana-server
sudo systemctl enable grafana-server
```

### Verifying Deployment

```bash
# Check Prometheus targets
curl http://localhost:9090/api/v1/targets

# Check metrics endpoint
curl http://localhost/metrics.php

# Access Grafana
open http://localhost:3000
```

## Operational Runbooks

### Investigating High Error Rate

1. **Check the logs for error patterns:**
   ```bash
   # View recent errors
   docker logs chatbot-app | grep ERROR | tail -50
   ```

2. **Query Prometheus for error distribution:**
   ```promql
   sum(rate(chatbot_api_errors_total[5m])) by (endpoint, status)
   ```

3. **Find trace IDs from logs and trace the request flow**

4. **Check OpenAI API status dashboard**

### Resolving Job Queue Backlog

1. **Check worker health:**
   ```promql
   chatbot_worker_healthy
   ```

2. **Inspect failed jobs:**
   ```sql
   SELECT * FROM jobs WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 10;
   ```

3. **Check worker logs:**
   ```bash
   docker logs chatbot-worker
   ```

4. **Scale workers if needed:**
   ```bash
   docker-compose up -d --scale worker=3
   ```

### Debugging High Latency

1. **Check P95/P99 latency by endpoint:**
   ```promql
   histogram_quantile(0.95, rate(chatbot_api_request_duration_seconds_bucket[5m]))
   ```

2. **Trace slow requests using trace IDs from logs**

3. **Check OpenAI API latency:**
   ```promql
   histogram_quantile(0.95, rate(chatbot_openai_request_duration_seconds_bucket[5m]))
   ```

4. **Review database queries and indexes**

### Troubleshooting Token Usage Spikes

1. **Identify agents with high usage:**
   ```promql
   topk(5, sum(rate(chatbot_tokens_total[1h])) by (agent_id))
   ```

2. **Check recent conversations in database**

3. **Review audit logs for unusual activity**

4. **Consider implementing token budgets per tenant**

## Integration Examples

### Adding Observability to New Endpoints

```php
<?php
require_once 'includes/ObservabilityMiddleware.php';

// Initialize
$observability = new ObservabilityMiddleware($config);
$spanId = $observability->handleRequestStart('/api/new-endpoint');

try {
    // Your endpoint logic
    $result = processRequest($data);
    
    // Log success
    $observability->log('info', 'Request processed successfully', [
        'result_count' => count($result),
    ]);
    
    // End request
    $observability->handleRequestEnd($spanId, '/api/new-endpoint', 200);
    
    echo json_encode($result);
} catch (Exception $e) {
    $observability->handleError($spanId, $e);
    $observability->handleRequestEnd($spanId, '/api/new-endpoint', 500);
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Adding Tracing to Background Jobs

```php
class JobProcessor {
    private $observability;
    
    public function processJob($job) {
        $spanId = $this->observability->createSpan('job.process', [
            'job_id' => $job['id'],
            'job_type' => $job['type'],
        ]);
        
        try {
            // Process job
            $result = $this->doWork($job);
            
            $this->observability->endSpan($spanId, [
                'status' => 'completed',
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->observability->getTracing()->recordError($spanId, $e);
            $this->observability->endSpan($spanId, ['status' => 'failed']);
            throw $e;
        }
    }
}
```

## Best Practices

1. **Always use structured logging** - Include relevant context in all log entries
2. **Propagate trace IDs** - Pass trace IDs through all service calls
3. **Set meaningful context** - Add tenant, agent, and user context early
4. **Use appropriate log levels** - Debug for development, Info for production
5. **Monitor alert fatigue** - Tune thresholds to avoid false positives
6. **Set SLOs** - Define service level objectives and track them
7. **Regular dashboard reviews** - Review metrics weekly for trends
8. **Test alerting** - Verify alerts fire correctly in staging

## Troubleshooting

### Logs not appearing in Loki

- Check Promtail is running: `docker ps | grep promtail`
- Verify log file permissions
- Check Promtail logs: `docker logs chatbot-promtail`

### Metrics not showing in Prometheus

- Verify metrics endpoint: `curl http://localhost/metrics.php`
- Check Prometheus targets: http://localhost:9090/targets
- Review Prometheus logs: `docker logs chatbot-prometheus`

### High metrics storage disk usage

- Reduce retention period in Prometheus configuration
- Increase scrape interval
- Reduce cardinality of labels

## Support

For issues and questions:
- Check logs: `docker logs <container>`
- Review metrics: http://localhost:9090
- Grafana dashboards: http://localhost:3000
- GitHub Issues: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues
