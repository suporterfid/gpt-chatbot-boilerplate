Instrument the webhook system to emit metrics on delivery success/failure rates, latencies, and retry counts. These metrics should be exposed in a format compatible with common monitoring tools and visualized in admin dashboards.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §10

**Deliverables:**
- Metrics collection for webhook events
- Dashboard visualizations
- Alerting on webhook failures

**Metrics to Track:**
- `webhook_deliveries_total` (counter by event_type, status)
- `webhook_delivery_duration_seconds` (histogram)
- `webhook_retry_count` (counter by attempt_number)
- `webhook_queue_depth` (gauge)