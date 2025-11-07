# Observability Stack

This directory contains the complete observability and monitoring infrastructure for the GPT Chatbot Platform.

## Contents

### `/alerts`
Prometheus alerting rules for critical conditions and SLO/SLA monitoring.

- `chatbot-alerts.yml` - 18+ pre-configured alert rules for:
  - API errors and latency
  - OpenAI API failures
  - Job queue health
  - Worker status
  - Resource usage
  - Token consumption
  - Webhook processing
  - **Multi-tenant specific alerts:**
    - TenantHighErrorRate - Tenant with >20% error rate
    - TenantInactive - Tenant without activity for 24h
    - NoActiveTenants - No active tenants on platform

### `/dashboards`
Grafana dashboard configurations for visualization and monitoring.

- `overview.json` - Main platform dashboard showing:
  - Request rates and errors
  - API latency (P95, P99)
  - OpenAI API status
  - Job queue metrics
  - Token usage by model
  - Agent performance
  - Active alerts

- `multi-tenant.json` - Multi-tenant monitoring dashboard showing:
  - Active tenants (24h)
  - Job processing rate per tenant
  - Error rate per tenant
  - Jobs by status and tenant
  - Conversations per tenant
  - Worker job duration by tenant
  - API requests per tenant
  - Tenant resource usage table
  - Webhook events per tenant
  - OpenAI API calls per tenant

### `/docker`
Docker Compose stack for one-command deployment of the entire observability infrastructure.

**Services:**
- **Prometheus** (`:9090`) - Metrics collection and alerting
- **Grafana** (`:3000`) - Dashboards and visualization
- **Loki** (`:3100`) - Log aggregation
- **Promtail** - Log shipping agent
- **AlertManager** (`:9093`) - Alert routing and notifications

**Configuration files:**
- `docker-compose.yml` - Complete stack definition
- `prometheus.yml` - Prometheus scrape and alert configuration
- `grafana-datasources.yml` - Grafana data source provisioning
- `loki-config.yml` - Loki log aggregation configuration
- `promtail-config.yml` - Log collection configuration
- `alertmanager.yml` - Alert notification routing

## Quick Start

### 1. Start the Stack

```bash
cd docker
docker-compose up -d
```

### 2. Access Services

- **Grafana**: http://localhost:3000 (admin/admin)
- **Prometheus**: http://localhost:9090
- **AlertManager**: http://localhost:9093

### 3. Import Dashboards

Dashboards are automatically provisioned from `/dashboards` directory.

### 4. Configure Notifications

Edit `docker/alertmanager.yml` to add your notification channels:

```yaml
receivers:
  - name: 'critical-alerts'
    slack_configs:
      - channel: '#alerts'
        api_url: 'YOUR_WEBHOOK_URL'
    email_configs:
      - to: 'oncall@example.com'
```

## Documentation

- **[Quick Start Guide](QUICKSTART.md)** - Get up and running in minutes
- **[Full Documentation](../docs/OBSERVABILITY.md)** - Comprehensive guide with examples
- **[Multi-Tenant Guide](../docs/MULTI_TENANT_OBSERVABILITY.md)** - Multi-tenant observability and tracing
- **[Alert Descriptions](alerts/chatbot-alerts.yml)** - Details on all alerts

## Multi-Tenant Support

The observability stack fully supports multi-tenant operations with:

### Tenant Context Propagation
- **Trace IDs** propagate across all services (API → Worker → Webhooks)
- **Tenant ID** included in all logs, metrics, and traces
- **W3C Trace Context** standard for distributed tracing

### Tenant-Aware Monitoring
- Separate metrics per tenant for resource tracking
- Per-tenant error rates and SLO monitoring
- Tenant-specific alerts for degraded performance
- Multi-tenant dashboard in Grafana

### Structured Logging
All logs include tenant context:
```json
{
  "trace_id": "a1b2c3...",
  "tenant_id": "tenant-123",
  "level": "INFO",
  "message": "Job completed"
}
```

Query logs by tenant in Loki:
```logql
{job="chatbot"} | json | tenant_id="tenant-123"
```

See **[Multi-Tenant Observability Guide](../docs/MULTI_TENANT_OBSERVABILITY.md)** for detailed implementation guide.

## Architecture

```
┌─────────────┐
│  Chatbot    │
│  Services   │ ──▶ Metrics ──▶ Prometheus ──▶ Grafana
│             │ ──▶ Logs ────▶ Loki ──────────▶ Grafana
│             │ ──▶ Traces ──▶ (spans in logs)
└─────────────┘      │
                     │
                     ▼
               AlertManager ──▶ Notifications
                                 (Slack, Email, etc.)
```

## Metrics Exposed

The chatbot platform exposes metrics at `/metrics.php`:

### API Metrics
- `chatbot_api_requests_total` - Request count by endpoint
- `chatbot_api_errors_total` - Error count by status code
- `chatbot_api_request_duration_seconds` - Request latency histogram

### OpenAI Metrics
- `chatbot_openai_requests_total` - OpenAI API calls
- `chatbot_openai_errors_total` - OpenAI API failures
- `chatbot_openai_request_duration_seconds` - OpenAI latency

### Agent Metrics
- `chatbot_agent_requests_total` - Requests per agent
- `chatbot_agent_request_duration_seconds` - Agent performance

### Token Usage
- `chatbot_tokens_total` - Total tokens consumed
- `chatbot_tokens_prompt_total` - Prompt tokens
- `chatbot_tokens_completion_total` - Completion tokens

### Job Queue
- `chatbot_jobs_total` - Jobs by status
- `chatbot_jobs_queue_depth` - Pending jobs
- `chatbot_jobs_processed_total` - Completed jobs
- `chatbot_jobs_failed_total` - Failed jobs

### Worker Metrics
- `chatbot_worker_jobs_completed_total` - Worker completed jobs by tenant
- `chatbot_worker_jobs_failed_total` - Worker failed jobs by tenant

### Multi-Tenant Metrics
- `chatbot_tenant_jobs_total` - Jobs by tenant and status
- `chatbot_active_tenants_24h` - Active tenants in last 24 hours
- `chatbot_tenant_conversations_24h` - Conversations per tenant
- `chatbot_webhook_events_processed_total` - Webhook events by tenant

### System
- `chatbot_info` - Application version info
- `chatbot_database_size_bytes` - Database size
- `chatbot_worker_healthy` - Worker health status

## Alerting

Alerts are defined in `alerts/chatbot-alerts.yml` and include:

- **Critical**: High error rate, API failures, service down
- **Warning**: High latency, queue backlog, resource usage

Alerts are routed through AlertManager to configured notification channels.

## Log Format

All logs are output as structured JSON:

```json
{
  "timestamp": "2024-11-06T18:00:00.000Z",
  "level": "INFO",
  "message": "API request completed",
  "trace_id": "a1b2c3d4...",
  "context": {
    "endpoint": "/chat-unified.php",
    "duration_ms": 1234.56,
    "status_code": 200,
    "tenant_id": "tenant-123",
    "agent_id": "agent-456"
  }
}
```

## Customization

### Add Custom Metrics

```php
$metrics = MetricsCollector::getInstance();
$metrics->incrementCounter('custom_metric_total', ['label' => 'value']);
```

### Add Custom Alerts

Edit `alerts/chatbot-alerts.yml`:

```yaml
- alert: CustomAlert
  expr: custom_metric_total > 100
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "Custom condition detected"
```

### Add Custom Dashboards

Create JSON files in `/dashboards` directory. They will be automatically imported by Grafana.

## Troubleshooting

### No data in Grafana
1. Check data sources are configured correctly
2. Verify Prometheus is scraping: http://localhost:9090/targets
3. Check application metrics endpoint: `curl http://localhost/metrics.php`

### Alerts not firing
1. Verify alert rules are loaded in Prometheus
2. Check AlertManager is configured correctly
3. Test alert with: http://localhost:9090/alerts

### Logs not appearing
1. Check Promtail is running: `docker ps | grep promtail`
2. Verify log file paths in `promtail-config.yml`
3. Check Loki is receiving data: http://localhost:3100/ready

## Production Considerations

1. **Security**: Secure Grafana with proper authentication
2. **Retention**: Configure appropriate data retention policies
3. **Backup**: Backup Prometheus data and Grafana dashboards
4. **Scaling**: Consider using remote storage for high-volume metrics
5. **High Availability**: Deploy multiple Prometheus and AlertManager instances
6. **Network**: Use proper network segmentation and firewall rules

## Support

- Full documentation: [docs/OBSERVABILITY.md](../docs/OBSERVABILITY.md)
- Quick start: [QUICKSTART.md](QUICKSTART.md)
- Issues: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues
