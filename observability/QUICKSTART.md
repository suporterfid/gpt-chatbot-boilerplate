# Observability Quick Start Guide

This guide will help you get the observability stack up and running quickly.

## Prerequisites

- Docker and Docker Compose installed
- Chatbot application running
- Port 3000, 3100, 9090, and 9093 available

## Quick Start

### 1. Start the Observability Stack

```bash
cd observability/docker
docker-compose up -d
```

This will start:
- **Prometheus** on port 9090 - Metrics collection
- **Grafana** on port 3000 - Dashboards and visualization
- **Loki** on port 3100 - Log aggregation
- **AlertManager** on port 9093 - Alert routing

### 2. Configure Environment Variables

Add to your `.env` file:

```bash
# Enable observability
OBSERVABILITY_ENABLED=true
LOG_FORMAT=json
LOG_FILE=php://stderr

# Tracing
TRACING_ENABLED=true
TRACING_EXPORT=false

# Metrics
METRICS_ENABLED=true
METRICS_STORAGE_PATH=/tmp/chatbot_metrics
```

### 3. Access the Dashboards

#### Grafana (Dashboards)
- URL: http://localhost:3000
- Default credentials: admin / admin
- Pre-configured dashboards in the "Dashboards" menu

#### Prometheus (Metrics)
- URL: http://localhost:9090
- Query metrics directly using PromQL
- View targets: http://localhost:9090/targets
- View alerts: http://localhost:9090/alerts

#### AlertManager (Alerts)
- URL: http://localhost:9093
- View and silence alerts

### 4. Verify Setup

#### Check Metrics Endpoint

```bash
curl http://localhost/metrics.php
```

Should return Prometheus-formatted metrics.

#### Check Logs

If using Docker:
```bash
docker logs chatbot-app | tail -20
```

You should see JSON-formatted logs with trace IDs.

#### Check Prometheus Targets

Navigate to http://localhost:9090/targets and verify the "chatbot" target is UP.

## Common Tasks

### View Application Logs in Grafana

1. Go to Grafana → Explore
2. Select "Loki" data source
3. Query: `{job="chatbot"}`
4. Filter by level: `{job="chatbot"} |= "ERROR"`

### Query Metrics in Prometheus

Example queries:
```promql
# Request rate
rate(chatbot_api_requests_total[5m])

# Error rate
rate(chatbot_api_errors_total[5m]) / rate(chatbot_api_requests_total[5m])

# P95 latency
histogram_quantile(0.95, rate(chatbot_api_request_duration_seconds_bucket[5m]))

# OpenAI API calls
sum(rate(chatbot_openai_requests_total[5m])) by (api_type, model)
```

### Create Custom Alerts

Edit `observability/alerts/chatbot-alerts.yml` and reload:

```bash
cd observability/docker
docker-compose restart prometheus
```

### Configure Slack Notifications

Edit `observability/docker/alertmanager.yml`:

```yaml
global:
  slack_api_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
```

Restart AlertManager:
```bash
docker-compose restart alertmanager
```

## Troubleshooting

### Metrics not showing up

1. Check the metrics endpoint is accessible:
   ```bash
   curl http://localhost/metrics.php
   ```

2. Check Prometheus is scraping:
   - Go to http://localhost:9090/targets
   - Verify "chatbot" target is UP

3. Check Prometheus logs:
   ```bash
   docker logs chatbot-prometheus
   ```

### Logs not appearing in Loki

1. Check Promtail is running:
   ```bash
   docker ps | grep promtail
   ```

2. Check log file paths in `observability/docker/promtail-config.yml`

3. Check Promtail logs:
   ```bash
   docker logs chatbot-promtail
   ```

### Grafana shows no data

1. Verify data sources are configured:
   - Go to Configuration → Data Sources
   - Check Prometheus and Loki connections

2. Try a simple query in Explore view

## Next Steps

- Read the full [Observability Guide](../docs/OBSERVABILITY.md)
- Customize dashboards for your use case
- Set up proper notification channels (email, PagerDuty, etc.)
- Configure log retention policies
- Set up metric aggregation for long-term storage

## Support

For issues or questions:
- Check logs: `docker-compose logs`
- Review documentation: `docs/OBSERVABILITY.md`
- GitHub Issues: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues
