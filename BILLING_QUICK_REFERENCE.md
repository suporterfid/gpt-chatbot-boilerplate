# Multi-Tenant Billing & Metering - Quick Reference

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         API REQUEST                              │
│  (message, conversation_id, agent_id, tenant_id)                │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │   TenantRateLimitService      │ ◄─── File Cache
         │   Check: 60 req/min           │
         └───────────┬───────────────────┘
                     │ ✓ Allowed
                     ▼
         ┌───────────────────────────────┐
         │      QuotaService             │ ◄─── tenant_usage table
         │   Check: 1000 msg/day         │      (fast lookup)
         └───────────┬───────────────────┘
                     │ ✓ Within quota
                     ▼
         ┌───────────────────────────────┐
         │       ChatHandler             │
         │   Process AI Request          │
         │   (OpenAI API call)           │
         └───────────┬───────────────────┘
                     │ ✓ Completed
                     ▼
         ┌───────────────────────────────┐
         │   UsageTrackingService        │ ──┐
         │   Log: 1 completion           │   │
         │        500 tokens             │   │
         └───────────┬───────────────────┘   │
                     │                        │
                     ├─────────────────┬──────┘
                     ▼                 ▼
         ┌──────────────────┐  ┌─────────────────────┐
         │   usage_logs     │  │   tenant_usage      │
         │   (raw events)   │  │   (aggregated)      │
         └──────────────────┘  └─────────────────────┘
                                        │
                                        │
         ┌──────────────────────────────┴──────────────┐
         │         BACKGROUND JOBS (Cron)              │
         └─────────────────────────────────────────────┘
                     │
        ┌────────────┼────────────┬────────────────┐
        │            │            │                │
        ▼            ▼            ▼                ▼
  ┌─────────┐  ┌─────────┐  ┌──────────┐   ┌──────────┐
  │Aggregate│  │ Check   │  │Generate  │   │  Send    │
  │ Usage   │  │ Quotas  │  │ Invoice  │   │  Alerts  │
  │ Hourly  │  │ Every   │  │ Monthly  │   │  Email   │
  │ Daily   │  │ 5 min   │  │ 1st day  │   │  Webhook │
  └─────────┘  └─────────┘  └──────────┘   └──────────┘
```

## Data Flow

### 1. Real-time Request Flow (< 10ms overhead)

```
Request → Rate Limit (1ms) → Quota (5ms) → Process → Track (1ms) → Response
```

### 2. Usage Tracking (Dual Mode)

```
Real-time Mode:
API Call → UsageTrackingService → usage_logs
                                 → TenantUsageService.incrementUsage()
                                 → tenant_usage (immediate update)

Batch Mode (Cron):
TenantUsageService.aggregateUsage()
    → SELECT FROM usage_logs (aggregate)
    → UPSERT INTO tenant_usage (bulk update)
```

### 3. Quota Checking (Fast Path)

```
Before Request:
QuotaService.checkQuota()
    → SELECT FROM tenant_usage WHERE period = 'daily'  (< 5ms)
    → Compare current vs limit
    → Allow or Block

Old Way (Slow):
    → SELECT COUNT(*) FROM usage_logs WHERE created_at > today  (> 100ms)
```

## Database Tables

### tenant_usage (NEW - The Secret Sauce)

```sql
┌──────────┬──────────────┬───────────────┬─────────────┬───────────┬─────────────┐
│ tenant_id│ resource_type│ period_type   │period_start │event_count│total_quantity│
├──────────┼──────────────┼───────────────┼─────────────┼───────────┼─────────────┤
│ tenant-1 │ message      │ daily         │ 2024-11-07  │    150    │     150     │
│ tenant-1 │ completion   │ daily         │ 2024-11-07  │    150    │     150     │
│ tenant-1 │ file_upload  │ daily         │ 2024-11-07  │     10    │      10     │
│ tenant-1 │ message      │ monthly       │ 2024-11-01  │   4500    │    4500     │
└──────────┴──────────────┴───────────────┴─────────────┴───────────┴─────────────┘
```

**Benefits:**
- ✅ 20x faster quota checks (no aggregation needed)
- ✅ Instant dashboard loading (pre-computed)
- ✅ Efficient invoice generation (monthly totals ready)
- ✅ Historical trends (no expensive JOINs)

### quotas (Existing - Enhanced)

```sql
┌──────────┬──────────────┬────────┬────────┬─────────────┬───────────────┐
│ tenant_id│ resource_type│ limit  │ period │is_hard_limit│threshold      │
├──────────┼──────────────┼────────┼────────┼─────────────┼───────────────┤
│ tenant-1 │ message      │  1000  │ daily  │    true     │      80%      │
│ tenant-1 │ completion   │  1000  │ daily  │    true     │      80%      │
│ tenant-1 │ api_call     │   100  │ hourly │    true     │      90%      │
└──────────┴──────────────┴────────┴────────┴─────────────┴───────────────┘
```

## Resource Types

```
┌─────────────────┬──────────────────────────────────────┐
│ Resource Type   │ What It Tracks                       │
├─────────────────┼──────────────────────────────────────┤
│ message         │ User messages sent                   │
│ completion      │ AI completions generated             │
│ file_upload     │ Files uploaded                       │
│ file_storage    │ File storage (MB-months)             │
│ vector_query    │ Vector database queries              │
│ tool_call       │ Function/tool executions             │
│ embedding       │ Embedding generations                │
│ api_call        │ General API requests                 │
└─────────────────┴──────────────────────────────────────┘
```

## Period Types

```
┌──────────┬────────────────┬──────────────────────────────┐
│ Period   │ Window         │ Use Case                     │
├──────────┼────────────────┼──────────────────────────────┤
│ hourly   │ Last 60 min    │ Rate limiting, spike detect  │
│ daily    │ Last 24 hours  │ Daily quotas, dashboards     │
│ monthly  │ Last 30 days   │ Billing, invoices            │
│ total    │ All time       │ Lifetime usage stats         │
└──────────┴────────────────┴──────────────────────────────┘
```

## Configuration Quick Start

### 1. Environment Variables (.env)

```bash
# Enable features
USAGE_TRACKING_ENABLED=true
QUOTA_ENFORCEMENT_ENABLED=true
QUOTA_WARNING_THRESHOLD=80

# Billing
BILLING_NOTIFICATIONS_ENABLED=true
BILLING_ADMIN_EMAIL=billing@example.com

# Payment gateway (optional)
ASAAS_ENABLED=false
ASAAS_API_KEY=your_key
ASAAS_PRODUCTION=false
```

### 2. Cron Jobs (crontab)

```bash
# Every hour: Aggregate hourly data
0 * * * * cd /var/www/app && php scripts/aggregate_usage.php --period=hourly

# Daily at 1 AM: Aggregate daily data
0 1 * * * cd /var/www/app && php scripts/aggregate_usage.php --period=daily

# Every 5 minutes: Check quotas and alert
*/5 * * * * cd /var/www/app && php scripts/check_quotas.php

# Monthly at 3 AM on 1st: Generate invoices
0 3 1 * * cd /var/www/app && php scripts/generate_invoices.php
```

### 3. Set Initial Quotas (curl or Admin UI)

```bash
# Create daily message quota
curl -X POST "https://api.example.com/admin-api.php?action=set_quota" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "tenant_id": "tenant-123",
    "resource_type": "message",
    "limit_value": 1000,
    "period": "daily",
    "is_hard_limit": true,
    "notification_threshold": 80
  }'
```

## API Endpoints

### Get Current Usage

```bash
GET /admin-api.php?action=get_tenant_usage_summary&period_type=daily

Response:
{
  "tenant_id": "tenant-123",
  "period_type": "daily",
  "period_start": "2024-11-07T00:00:00Z",
  "period_end": "2024-11-08T00:00:00Z",
  "by_resource_type": [
    {
      "resource_type": "completion",
      "event_count": 150,
      "total_quantity": 150
    }
  ],
  "totals": {
    "total_events": 150,
    "total_quantity": 150
  }
}
```

### Get Rate Limit Status

```bash
GET /admin-api.php?action=get_rate_limit_status

Response:
[
  {
    "resource_type": "api_call",
    "limit": 60,
    "current": 45,
    "remaining": 15,
    "percentage": 75.0,
    "window_seconds": 60,
    "reset_at": 1699315200
  }
]
```

### Get Usage Trends

```bash
GET /admin-api.php?action=get_tenant_usage_trends&period_type=daily&limit=7

Response:
{
  "tenant_id": "tenant-123",
  "period_type": "daily",
  "data": [
    {
      "period_start": "2024-11-01T00:00:00Z",
      "resource_type": "completion",
      "event_count": 100,
      "total_quantity": 100
    },
    // ... 6 more days
  ]
}
```

## Pricing Configuration

Edit `scripts/generate_invoices.php`:

```php
$pricing = [
    'message' => 0.01,        // $0.01 per message
    'completion' => 0.02,     // $0.02 per completion
    'file_upload' => 0.10,    // $0.10 per upload
    'file_storage' => 0.001,  // $0.001 per MB/month
    'vector_query' => 0.005,  // $0.005 per query
    'tool_call' => 0.01,      // $0.01 per tool call
    'embedding' => 0.001,     // $0.001 per embedding
];
```

## Default Rate Limits

From `TenantRateLimitService`:

```php
'api_call'     => 60/min      (General API requests)
'message'      => 100/hour    (User messages)
'completion'   => 100/hour    (AI completions)
'file_upload'  => 10/hour     (File uploads)
'vector_query' => 1000/hour   (Vector searches)
'tool_call'    => 200/hour    (Tool executions)
'embedding'    => 500/hour    (Embeddings)
```

Override via quotas table per tenant.

## Testing

```bash
# Run comprehensive tests
php tests/test_multi_tenant_billing.php

# Test aggregation manually
php scripts/aggregate_usage.php --period=daily --verbose

# Test quota checking
php scripts/check_quotas.php --verbose

# Test invoice generation (dry run)
php scripts/generate_invoices.php --dry-run --verbose
```

## Monitoring Queries

```sql
-- Top tenants by usage (last 7 days)
SELECT tenant_id, SUM(total_quantity) as usage
FROM tenant_usage
WHERE period_type = 'daily' 
  AND period_start >= date('now', '-7 days')
GROUP BY tenant_id
ORDER BY usage DESC
LIMIT 10;

-- Quota utilization (all tenants)
SELECT t.name, q.resource_type,
       CAST(u.total_quantity AS FLOAT) / q.limit_value * 100 as pct
FROM quotas q
JOIN tenant_usage u ON q.tenant_id = u.tenant_id 
  AND q.resource_type = u.resource_type
JOIN tenants t ON q.tenant_id = t.id
WHERE u.period_type = 'daily'
  AND q.period = 'daily'
  AND pct > 80
ORDER BY pct DESC;

-- Revenue forecast (this month)
SELECT 
  SUM(CASE resource_type 
    WHEN 'message' THEN total_quantity * 0.01
    WHEN 'completion' THEN total_quantity * 0.02
    WHEN 'file_upload' THEN total_quantity * 0.10
    ELSE 0 END
  ) as estimated_revenue
FROM tenant_usage
WHERE period_type = 'monthly'
  AND period_start = date('now', 'start of month');
```

## Troubleshooting

### Usage not tracked?
```bash
# Check config
grep USAGE_TRACKING_ENABLED .env

# Check if services initialized
php -r "require 'includes/ChatHandler.php'; echo 'OK';"

# Check logs
tail -f /var/log/php_errors.log
```

### Quota not enforced?
```bash
# Verify quota exists
curl "https://api.example.com/admin-api.php?action=list_quotas&tenant_id=XXX" \
  -H "Authorization: Bearer TOKEN"

# Check enforcement enabled
grep QUOTA_ENFORCEMENT_ENABLED .env
```

### Aggregation not working?
```bash
# Test manually
php scripts/aggregate_usage.php --verbose

# Check cron
crontab -l | grep aggregate

# View cron logs
grep aggregate /var/log/syslog
```

## Files Overview

```
New Files:
├── db/migrations/031_create_tenant_usage.sql
├── includes/TenantUsageService.php
├── includes/TenantRateLimitService.php
├── scripts/aggregate_usage.php
├── scripts/check_quotas.php
├── scripts/generate_invoices.php
├── tests/test_multi_tenant_billing.php
└── docs/MULTI_TENANT_BILLING.md

Modified Files:
├── includes/ChatHandler.php (added tracking)
├── admin-api.php (5 new endpoints)
└── config.php (billing settings)
```

## Performance Tips

1. **Run aggregation during off-peak hours**
   - Schedule at 1-3 AM when traffic is low

2. **Monitor tenant_usage table size**
   - Grows ~1KB per tenant per day per resource type
   - Archive old data after 12-24 months

3. **Use indexes effectively**
   - All created by migrations
   - Monitor slow queries with EXPLAIN

4. **Rate limit cache cleanup**
   - Runs automatically every 24 hours
   - Or manually: `TenantRateLimitService::cleanupCache()`

5. **Database tuning**
   - SQLite: `PRAGMA journal_mode=WAL;`
   - MySQL: Enable query cache for tenant_usage reads

---

**Quick Start:** Configure .env → Set quotas → Schedule cron → Done! 🚀
