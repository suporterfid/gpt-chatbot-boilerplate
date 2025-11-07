# Multi-Tenant Billing & Metering - Complete Guide

## Overview

This document describes the complete multi-tenant billing and metering system implemented for the GPT Chatbot Boilerplate. The system provides comprehensive usage tracking, quota management, rate limiting, and invoice generation capabilities required for commercial SaaS deployments.

## Architecture

### Core Components

1. **Usage Tracking** (`usage_logs` table)
   - Records every billable API operation
   - Supports: messages, completions, file uploads, storage, vector queries, tool calls, embeddings
   - Includes metadata and timestamps for detailed analysis

2. **Usage Aggregation** (`tenant_usage` table) **[NEW]**
   - Pre-aggregated usage statistics for efficient queries
   - Supports hourly, daily, monthly, and total aggregations
   - Real-time incremental updates for dashboard display
   - Batch aggregation via cron for historical data

3. **Quota Management** (`quotas` table)
   - Configurable soft and hard limits per tenant
   - Supports hourly, daily, monthly, and lifetime periods
   - Notification thresholds for proactive alerts
   - Automatic enforcement at API request level

4. **Rate Limiting** (file-based cache) **[NEW]**
   - Per-tenant, per-resource-type rate limiting
   - Sliding window algorithm for accurate enforcement
   - Configurable limits from quotas table or defaults
   - Protection against API abuse and cost overruns

5. **Billing & Invoicing** (`invoices`, `subscriptions` tables)
   - Automated monthly invoice generation
   - Line item detail with usage breakdown
   - Asaas payment gateway integration
   - Multiple payment methods support

6. **Notifications** (`notifications` table)
   - Quota warnings at configurable thresholds
   - Payment failure alerts
   - Subscription updates
   - Priority levels and read status tracking

## Database Schema

### New Table: tenant_usage

```sql
CREATE TABLE tenant_usage (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    resource_type TEXT NOT NULL,
    period_type TEXT NOT NULL,  -- hourly, daily, monthly, total
    period_start TEXT NOT NULL,
    period_end TEXT NOT NULL,
    event_count INTEGER NOT NULL DEFAULT 0,
    total_quantity INTEGER NOT NULL DEFAULT 0,
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(tenant_id, resource_type, period_type, period_start)
);
```

**Purpose:** Provides fast, pre-aggregated usage data for:
- Real-time dashboard displays
- Quota checking without scanning usage_logs
- Historical trend analysis
- Invoice generation

**Indexes:**
- `idx_tenant_usage_tenant_id` - Fast tenant lookups
- `idx_tenant_usage_lookup` - Composite index for quota checks

## Services

### TenantUsageService (NEW)

Manages aggregated usage data with two modes of operation:

**Real-time Mode:**
```php
// Called after each API operation
$tenantUsageService->incrementUsage($tenantId, 'completion', $quantity);
```

**Batch Mode:**
```php
// Called periodically via cron
$tenantUsageService->aggregateUsage($tenantId, 'daily');
```

**Key Methods:**
- `aggregateUsage($tenantId, $periodType)` - Aggregate from usage_logs
- `incrementUsage($tenantId, $resourceType, $quantity, $periodType)` - Real-time increment
- `getCurrentUsageSummary($tenantId, $periodType)` - Get current period stats
- `getUsageTrends($tenantId, $periodType, $limit)` - Time series data

### TenantRateLimitService (NEW)

Provides per-tenant rate limiting to prevent abuse and manage API costs.

**Features:**
- Sliding window rate limiting
- Per-resource-type limits
- Tenant-specific or default limits
- Automatic cleanup of old cache files

**Key Methods:**
- `checkRateLimit($tenantId, $resourceType, $limit, $windowSeconds)` - Check status
- `enforceRateLimit($tenantId, $resourceType, $limit, $windowSeconds)` - Enforce and record
- `getTenantRateLimit($tenantId, $resourceType)` - Get configured limits from quotas
- `getTenantRateLimitStatus($tenantId)` - Status for all resource types

**Default Limits:**
```php
'api_call' => 60 req/min
'message' => 100 msg/hour
'completion' => 100 completions/hour
'file_upload' => 10 uploads/hour
'vector_query' => 1000 queries/hour
'tool_call' => 200 calls/hour
'embedding' => 500 embeddings/hour
```

### Updated: ChatHandler

The `ChatHandler` now includes integrated usage tracking, quota checking, and rate limiting:

**New Methods:**
- `trackUsage($tenantId, $resourceType, $metadata)` - Track billable events
- `checkRateLimit($tenantId, $resourceType)` - Enforce rate limits
- `checkQuota($tenantId, $resourceType, $period)` - Enforce quotas
- `getTenantId($conversationId)` - Extract tenant ID (customizable)

**Request Flow:**
```
Request → Rate Limit Check → Quota Check → Process → Track Usage → Response
```

**Updated Method Signatures:**
```php
handleChatCompletion($message, $conversationId, $agentId = null, $tenantId = null)
streamChatCompletion($messages, $conversationId, $agentOverrides = [], $tenantId = null)
```

## Admin API Endpoints

### Tenant Usage Aggregation

**GET `/admin-api.php?action=get_tenant_usage_summary`**

Get current period aggregated usage summary.

Parameters:
- `tenant_id` (optional) - Defaults to authenticated user's tenant
- `period_type` (optional) - Default: `daily`, Options: `hourly`, `daily`, `monthly`, `total`

Response:
```json
{
  "tenant_id": "tenant-123",
  "period_type": "daily",
  "period_start": "2024-11-07T00:00:00+00:00",
  "period_end": "2024-11-08T00:00:00+00:00",
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

**GET `/admin-api.php?action=get_tenant_usage_trends`**

Get usage trends over time.

Parameters:
- `tenant_id` (optional)
- `period_type` (optional) - Default: `daily`
- `limit` (optional) - Default: 30

Response:
```json
{
  "tenant_id": "tenant-123",
  "period_type": "daily",
  "data": [
    {
      "period_start": "2024-11-06T00:00:00+00:00",
      "period_end": "2024-11-07T00:00:00+00:00",
      "resource_type": "completion",
      "event_count": 100,
      "total_quantity": 100
    }
  ]
}
```

**POST `/admin-api.php?action=aggregate_tenant_usage`**

Manually trigger usage aggregation (super-admin only).

Parameters:
- `tenant_id` (optional) - Aggregate specific tenant or all
- `period_type` (optional) - Default: `daily`

Response:
```json
{
  "success": true,
  "aggregated_records": 15,
  "period_type": "daily"
}
```

### Rate Limiting

**GET `/admin-api.php?action=get_rate_limit_status`**

Get rate limit status for all resource types.

Parameters:
- `tenant_id` (optional)

Response:
```json
[
  {
    "resource_type": "api_call",
    "limit": 60,
    "current": 45,
    "remaining": 15,
    "window_seconds": 60,
    "reset_at": 1699315200,
    "percentage": 75.0
  }
]
```

**POST `/admin-api.php?action=clear_rate_limit`**

Clear rate limit for a tenant (super-admin only).

Parameters:
- `tenant_id` (required)
- `resource_type` (optional) - Default: `api_call`

Response:
```json
{
  "success": true,
  "message": "Rate limit cleared",
  "tenant_id": "tenant-123",
  "resource_type": "api_call"
}
```

## Automated Scripts (Cron Jobs)

### 1. Usage Aggregation

**File:** `scripts/aggregate_usage.php`

**Purpose:** Aggregate usage_logs into tenant_usage table for efficient queries.

**Usage:**
```bash
php scripts/aggregate_usage.php [--period=daily] [--tenant-id=xxx]
```

**Cron Schedule:**
```bash
# Aggregate hourly data every hour
0 * * * * cd /path/to/app && php scripts/aggregate_usage.php --period=hourly

# Aggregate daily data once per day
0 1 * * * cd /path/to/app && php scripts/aggregate_usage.php --period=daily

# Aggregate monthly data once per month
0 2 1 * * cd /path/to/app && php scripts/aggregate_usage.php --period=monthly
```

### 2. Quota Checking & Alerting

**File:** `scripts/check_quotas.php`

**Purpose:** Check tenant quotas and send notifications when thresholds are reached.

**Usage:**
```bash
php scripts/check_quotas.php [--tenant-id=xxx] [--verbose]
```

**Features:**
- Checks all active tenants
- Sends quota warning notifications at threshold
- Creates alerts for hard limit violations
- Prevents spam (max 1 notification per hour per quota)

**Cron Schedule:**
```bash
# Check quotas every 5 minutes
*/5 * * * * cd /path/to/app && php scripts/check_quotas.php
```

### 3. Invoice Generation

**File:** `scripts/generate_invoices.php`

**Purpose:** Generate monthly invoices based on usage.

**Usage:**
```bash
php scripts/generate_invoices.php [--tenant-id=xxx] [--month=YYYY-MM] [--dry-run] [--verbose]
```

**Features:**
- Generates invoices for the previous month
- Calculates charges based on configurable pricing
- Creates line items for each resource type
- Sends notification to tenant
- Supports dry-run mode for testing

**Pricing Configuration (in script):**
```php
$pricing = [
    'message' => 0.01,        // $0.01 per message
    'completion' => 0.02,     // $0.02 per completion
    'file_upload' => 0.10,    // $0.10 per file upload
    'file_storage' => 0.001,  // $0.001 per MB per month
    'vector_query' => 0.005,  // $0.005 per query
    'tool_call' => 0.01,      // $0.01 per tool call
    'embedding' => 0.001      // $0.001 per embedding
];
```

**Cron Schedule:**
```bash
# Generate invoices on the 1st of each month at 3 AM
0 3 1 * * cd /path/to/app && php scripts/generate_invoices.php
```

## Configuration

### Environment Variables

Add to `.env`:

```bash
# Usage Tracking
USAGE_TRACKING_ENABLED=true
QUOTA_ENFORCEMENT_ENABLED=true
QUOTA_WARNING_THRESHOLD=80

# Billing Notifications
BILLING_NOTIFICATIONS_ENABLED=true
BILLING_ADMIN_EMAIL=billing@example.com

# Auto-Billing
AUTO_INVOICE_GENERATION=false
AUTO_PAYMENT_RETRY=true
PAYMENT_RETRY_ATTEMPTS=3
PAYMENT_RETRY_INTERVAL_HOURS=24

# Asaas Payment Gateway
ASAAS_ENABLED=false
ASAAS_API_KEY=your_api_key_here
ASAAS_PRODUCTION=false
```

### config.php

The following sections are automatically configured from environment variables:

```php
'usage_tracking' => [
    'enabled' => true,
],

'quota_enforcement' => [
    'enabled' => true,
    'warning_threshold' => 80,
],

'billing' => [
    'notifications_enabled' => true,
    'admin_email' => 'billing@example.com',
    'auto_invoice_generation' => false,
    'auto_payment_retry' => true,
    'payment_retry_attempts' => 3,
    'payment_retry_interval_hours' => 24,
],

'asaas' => [
    'enabled' => false,
    'api_key' => '',
    'production' => false,
]
```

## Integration Guide

### Step 1: Enable Services in chat-unified.php

```php
// Initialize billing services
require_once __DIR__ . '/includes/UsageTrackingService.php';
require_once __DIR__ . '/includes/QuotaService.php';
require_once __DIR__ . '/includes/TenantUsageService.php';
require_once __DIR__ . '/includes/TenantRateLimitService.php';

$db = new DB($config['database']['path']);
$usageTrackingService = new UsageTrackingService($db);
$quotaService = new QuotaService($db, $usageTrackingService);
$tenantUsageService = new TenantUsageService($db);
$rateLimitService = new TenantRateLimitService($db);

// Pass to ChatHandler
$chatHandler = new ChatHandler(
    $config,
    $agentService,
    $auditService,
    $observability,
    $usageTrackingService,
    $quotaService,
    $rateLimitService,
    $tenantUsageService
);
```

### Step 2: Extract Tenant ID

Implement tenant ID extraction based on your authentication:

```php
// Option 1: From API key
$tenantId = $apiKeyService->getTenantIdFromApiKey($_SERVER['HTTP_AUTHORIZATION']);

// Option 2: From user session
$tenantId = $_SESSION['tenant_id'] ?? null;

// Option 3: From conversation metadata
$tenantId = $chatHandler->getTenantId($conversationId);
```

### Step 3: Pass Tenant ID to Handler

```php
if ($apiType === 'chat') {
    $chatHandler->handleChatCompletion(
        $message,
        $conversationId,
        $agentId,
        $tenantId  // Add tenant ID
    );
}
```

### Step 4: Set Up Cron Jobs

Add to your crontab:

```bash
# Usage aggregation
0 * * * * cd /var/www/app && php scripts/aggregate_usage.php --period=hourly
0 1 * * * cd /var/www/app && php scripts/aggregate_usage.php --period=daily
0 2 1 * * cd /var/www/app && php scripts/aggregate_usage.php --period=monthly

# Quota monitoring
*/5 * * * * cd /var/www/app && php scripts/check_quotas.php

# Invoice generation
0 3 1 * * cd /var/www/app && php scripts/generate_invoices.php
```

### Step 5: Configure Quotas

Use Admin UI or API to set quotas:

```bash
curl -X POST "https://your-domain.com/admin-api.php?action=set_quota" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "tenant-123",
    "resource_type": "message",
    "limit_value": 1000,
    "period": "daily",
    "is_hard_limit": true,
    "notification_threshold": 80
  }'
```

## Testing

### Run Test Suite

```bash
php tests/test_multi_tenant_billing.php
```

### Manual Testing

```bash
# Test usage aggregation
php scripts/aggregate_usage.php --period=daily --verbose

# Test quota checking
php scripts/check_quotas.php --verbose

# Test invoice generation (dry run)
php scripts/generate_invoices.php --dry-run --verbose

# Check via API
curl "https://your-domain.com/admin-api.php?action=get_tenant_usage_summary&period_type=daily" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Performance Considerations

### tenant_usage Table Benefits

1. **Fast Quota Checks:** No need to scan usage_logs
2. **Efficient Dashboards:** Pre-aggregated data for charts
3. **Quick Invoice Generation:** Summary data readily available
4. **Reduced Database Load:** Aggregate once, query many times

### Rate Limiting

- File-based cache for high performance
- No database queries during rate limit checks
- Automatic cleanup of old cache files
- Configurable per-tenant limits

### Recommended Indexes

All necessary indexes are created by migrations:
- `idx_usage_logs_tenant_date` - For raw usage queries
- `idx_tenant_usage_lookup` - For aggregated queries
- `idx_quotas_tenant_resource` - For quota lookups

## Security Considerations

1. **Tenant Isolation:** All queries filter by tenant_id
2. **Authorization:** Admin API enforces role-based access
3. **Rate Limiting:** Prevents abuse and DDoS
4. **Quota Enforcement:** Hard limits block requests
5. **Audit Logging:** All billing operations are auditable

## Monitoring

### Key Metrics to Monitor

1. **Usage Growth:** Track total events per tenant
2. **Quota Violations:** Monitor hard limit blocks
3. **Rate Limit Hits:** Identify tenants hitting limits
4. **Invoice Amounts:** Track revenue trends
5. **Payment Failures:** Monitor collection issues

### Admin Dashboard Queries

```sql
-- Top tenants by usage
SELECT tenant_id, SUM(event_count) as total
FROM tenant_usage
WHERE period_type = 'daily' AND period_start >= date('now', '-30 days')
GROUP BY tenant_id
ORDER BY total DESC
LIMIT 10;

-- Quota utilization
SELECT t.name, q.resource_type, 
       CAST(tu.total_quantity AS FLOAT) / q.limit_value * 100 as percentage
FROM quotas q
JOIN tenant_usage tu ON q.tenant_id = tu.tenant_id 
  AND q.resource_type = tu.resource_type
JOIN tenants t ON q.tenant_id = t.id
WHERE tu.period_type = 'daily';
```

## Troubleshooting

### Issue: Usage not being tracked

**Check:**
1. `USAGE_TRACKING_ENABLED=true` in `.env`
2. Services initialized in ChatHandler
3. `tenant_id` passed to handler methods
4. No exceptions in error logs

### Issue: Quotas not enforced

**Check:**
1. `QUOTA_ENFORCEMENT_ENABLED=true` in `.env`
2. Quotas created for tenant
3. `is_hard_limit` set correctly
4. Period type matches usage pattern

### Issue: Aggregation not working

**Check:**
1. Cron jobs configured and running
2. Database permissions for writing
3. Error logs in cron output
4. Run manually with `--verbose` flag

### Issue: Rate limits too strict

**Solution:**
1. Update quota limits in database
2. Or clear rate limit: `clear_rate_limit` API
3. Or adjust default limits in `TenantRateLimitService`

## Migration from Existing System

If you have existing usage data:

```bash
# Aggregate historical data
php scripts/aggregate_usage.php --period=total
php scripts/aggregate_usage.php --period=monthly
php scripts/aggregate_usage.php --period=daily
```

## Next Steps

1. **Configure Asaas:** Set up payment gateway integration
2. **Customize Pricing:** Update pricing in `generate_invoices.php`
3. **Set Quotas:** Define limits for each tenant
4. **Enable Cron:** Schedule automated jobs
5. **Monitor Usage:** Check Admin UI regularly
6. **Test Alerts:** Verify notifications are working

## Support

For issues or questions:
- Check error logs
- Run tests with verbose output
- Review Admin API responses
- Monitor database queries

---

**Version:** 1.0  
**Last Updated:** 2024-11-07  
**Maintainer:** GPT Chatbot Boilerplate Team
