# Billing and Metering System Documentation

## Overview

The GPT Chatbot Boilerplate includes a complete billing and metering system for SaaS deployments. This system provides:

- **Usage Tracking**: Per-tenant tracking of API operations, messages, file uploads, and more
- **Quota Management**: Configurable usage limits with soft and hard enforcement
- **Subscription Management**: Flexible billing plans with multiple cycles
- **Payment Gateway Integration**: Built-in Asaas payment processor support
- **Automated Notifications**: Alerts for quota limits, failed payments, and billing events
- **Admin Dashboard**: Full UI for managing billing, quotas, and invoices

## Architecture

### Database Schema

The billing system uses six core tables:

1. **usage_logs**: Records every billable operation
2. **quotas**: Defines usage limits per tenant
3. **subscriptions**: Manages billing plans and cycles
4. **invoices**: Tracks payment status
5. **payment_methods**: Stores tenant payment information
6. **notifications**: Manages billing alerts

### Core Services

#### UsageTrackingService

Tracks all API usage per tenant for metering and billing.

**Resource Types:**
- `message`: Chat messages
- `completion`: API completions
- `file_upload`: File uploads
- `file_storage`: File storage operations
- `vector_query`: Vector database queries
- `tool_call`: Tool/function calls
- `embedding`: Embedding operations

**Key Methods:**
```php
// Log a usage event
$usageTrackingService->logUsage($tenantId, $resourceType, [
    'quantity' => 1,
    'metadata' => ['additional' => 'data']
]);

// Get usage statistics
$stats = $usageTrackingService->getUsageStats($tenantId, [
    'start_date' => '2024-01-01T00:00:00Z',
    'end_date' => '2024-01-31T23:59:59Z',
    'resource_type' => 'message'
]);

// Get time series data
$timeseries = $usageTrackingService->getUsageTimeSeries($tenantId, [
    'start_date' => '2024-01-01T00:00:00Z',
    'interval' => 'day'
]);
```

#### QuotaService

Manages and enforces usage quotas per tenant.

**Quota Periods:**
- `hourly`: Per-hour limits
- `daily`: Per-day limits
- `monthly`: Per-month limits
- `total`: Lifetime limits

**Key Methods:**
```php
// Set a quota
$quota = $quotaService->setQuota(
    $tenantId,
    'message',
    1000,  // limit
    'daily',
    [
        'is_hard_limit' => true,  // Block requests when exceeded
        'notification_threshold' => 80  // Alert at 80%
    ]
);

// Check quota status
$check = $quotaService->checkQuota($tenantId, 'message', 'daily');
// Returns: ['allowed' => true, 'current' => 500, 'limit' => 1000, 'percentage' => 50]

// Enforce quota (throws exception if exceeded)
$quotaService->enforceQuota($tenantId, 'message', 'daily');
```

#### BillingService

Manages subscriptions, invoices, and payment tracking.

**Plan Types:**
- `free`: Free tier
- `starter`: Starter plan
- `professional`: Professional plan
- `enterprise`: Enterprise plan
- `custom`: Custom pricing

**Billing Cycles:**
- `monthly`: Monthly billing
- `quarterly`: Quarterly billing
- `yearly`: Annual billing
- `lifetime`: One-time payment

**Key Methods:**
```php
// Create a subscription
$subscription = $billingService->createSubscription($tenantId, [
    'plan_type' => BillingService::PLAN_PROFESSIONAL,
    'billing_cycle' => BillingService::CYCLE_MONTHLY,
    'price_cents' => 9900,  // $99.00
    'status' => BillingService::STATUS_ACTIVE
]);

// Create an invoice
$invoice = $billingService->createInvoice($tenantId, [
    'amount_cents' => 9900,
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'line_items' => [
        [
            'description' => 'Professional Plan - Monthly',
            'amount_cents' => 9900,
            'quantity' => 1
        ]
    ]
]);

// Update invoice status
$billingService->updateInvoice($invoiceId, [
    'status' => BillingService::INVOICE_PAID,
    'external_payment_id' => 'pay_xxx'
]);
```

#### AsaasClient

Integrates with Asaas payment gateway (https://docs.asaas.com/).

**Key Methods:**
```php
$asaas = new AsaasClient($apiKey, $isProduction);

// Create a customer
$customer = $asaas->createCustomer([
    'name' => 'John Doe',
    'cpf_cnpj' => '12345678901',
    'email' => 'john@example.com',
    'external_reference' => $tenantId
]);

// Create a payment
$payment = $asaas->createPayment([
    'customer_id' => $customer['id'],
    'billing_type' => 'CREDIT_CARD',  // or 'BOLETO', 'PIX'
    'value' => 99.00,
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'description' => 'Professional Plan - Monthly'
]);

// Create a subscription
$subscription = $asaas->createSubscription([
    'customer_id' => $customer['id'],
    'billing_type' => 'CREDIT_CARD',
    'value' => 99.00,
    'cycle' => 'MONTHLY',
    'next_due_date' => date('Y-m-d', strtotime('+1 month'))
]);
```

#### NotificationService

Manages billing and quota notifications.

**Notification Types:**
- `quota_warning`: Quota threshold reached
- `quota_exceeded`: Quota limit exceeded
- `payment_failed`: Payment failure
- `payment_success`: Successful payment
- `subscription_expiring`: Subscription expiring soon
- `trial_ending`: Trial period ending

**Key Methods:**
```php
// Send quota warning
$notificationService->sendQuotaWarning($tenantId, 'message', 800, 1000, 80);

// Send payment failed notification
$notificationService->sendPaymentFailed($tenantId, $invoiceId, '$99.00');

// List notifications
$notifications = $notificationService->listNotifications($tenantId, [
    'unread_only' => true,
    'limit' => 10
]);

// Mark as read
$notificationService->markAsRead($notificationId);
```

## Admin API Endpoints

All billing endpoints require authentication and proper permissions.

### Usage Tracking

**GET /admin-api.php?action=get_usage_stats**
Get usage statistics for a tenant.

Query Parameters:
- `tenant_id` (optional): Tenant ID (defaults to authenticated user's tenant)
- `start_date`: ISO 8601 date
- `end_date`: ISO 8601 date
- `resource_type`: Filter by resource type

Response:
```json
{
  "tenant_id": "xxx",
  "by_resource_type": [
    {
      "resource_type": "message",
      "event_count": 150,
      "total_quantity": 150
    }
  ],
  "totals": {
    "total_events": 200,
    "total_quantity": 250
  }
}
```

**GET /admin-api.php?action=get_usage_timeseries**
Get time series usage data.

### Quota Management

**GET /admin-api.php?action=list_quotas**
List all quotas for a tenant.

**GET /admin-api.php?action=get_quota_status**
Get current quota status for all resources.

Response:
```json
[
  {
    "id": "xxx",
    "resource_type": "message",
    "limit_value": 1000,
    "period": "daily",
    "current": 250,
    "percentage": 25,
    "allowed": true,
    "is_hard_limit": true
  }
]
```

**POST /admin-api.php?action=set_quota**
Create or update a quota.

Request Body:
```json
{
  "resource_type": "message",
  "limit_value": 1000,
  "period": "daily",
  "is_hard_limit": true,
  "notification_threshold": 80
}
```

**POST /admin-api.php?action=delete_quota**
Delete a quota.

### Subscription Management

**GET /admin-api.php?action=get_subscription**
Get subscription for a tenant.

**POST /admin-api.php?action=create_subscription**
Create a subscription.

Request Body:
```json
{
  "plan_type": "professional",
  "billing_cycle": "monthly",
  "price_cents": 9900,
  "status": "active"
}
```

**POST /admin-api.php?action=update_subscription**
Update a subscription.

**POST /admin-api.php?action=cancel_subscription**
Cancel a subscription.

Request Body:
```json
{
  "immediately": false
}
```

### Invoice Management

**GET /admin-api.php?action=list_invoices**
List invoices for a tenant.

Query Parameters:
- `status`: Filter by status (pending, paid, overdue, etc.)
- `limit`: Results per page
- `offset`: Pagination offset

**GET /admin-api.php?action=get_invoice**
Get a specific invoice.

**POST /admin-api.php?action=create_invoice**
Create an invoice.

**POST /admin-api.php?action=update_invoice**
Update an invoice.

### Notifications

**GET /admin-api.php?action=list_notifications**
List notifications for a tenant.

**POST /admin-api.php?action=mark_notification_read**
Mark a notification as read.

**GET /admin-api.php?action=get_unread_count**
Get count of unread notifications.

## Integration Guide

### Step 1: Configure Environment

Add to `.env`:
```bash
# Enable Asaas integration
ASAAS_ENABLED=true
ASAAS_API_KEY=your_api_key_here
ASAAS_PRODUCTION=false

# Enable usage tracking
USAGE_TRACKING_ENABLED=true
QUOTA_ENFORCEMENT_ENABLED=true
```

### Step 2: Set Up Tenants

Create tenants via Admin API:
```bash
curl -X POST "http://localhost/admin-api.php?action=create_tenant" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Acme Corp",
    "slug": "acme",
    "billing_email": "billing@acme.com"
  }'
```

### Step 3: Configure Quotas

Set usage quotas:
```bash
curl -X POST "http://localhost/admin-api.php?action=set_quota" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "xxx",
    "resource_type": "message",
    "limit_value": 1000,
    "period": "daily",
    "is_hard_limit": true,
    "notification_threshold": 80
  }'
```

### Step 4: Create Subscriptions

Set up billing plans:
```bash
curl -X POST "http://localhost/admin-api.php?action=create_subscription" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "xxx",
    "plan_type": "professional",
    "billing_cycle": "monthly",
    "price_cents": 9900
  }'
```

### Step 5: Monitor Usage

View usage statistics:
```bash
curl "http://localhost/admin-api.php?action=get_usage_stats&tenant_id=xxx&start_date=2024-01-01T00:00:00Z" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Best Practices

### 1. Quota Configuration

- Set **soft limits** with notifications before hard limits
- Use **notification_threshold** at 80% for early warnings
- Configure both **daily** and **monthly** quotas
- Start with generous limits and adjust based on usage patterns

### 2. Usage Tracking

- Log usage **immediately** after operations complete
- Include **metadata** for detailed analysis
- Use appropriate **resource_type** for each operation
- Clean up old logs periodically for compliance

### 3. Billing Management

- Generate invoices **before** the billing period starts
- Send **payment reminders** 7 days before due date
- Implement **grace periods** for failed payments
- Archive paid invoices for compliance

### 4. Notifications

- Don't spam users with repeated notifications
- Implement **debouncing** for quota warnings
- Use appropriate **priority** levels
- Provide clear **action items** in messages

### 5. Security

- Always validate **tenant_id** in API requests
- Use **super-admin** role for cross-tenant operations
- Encrypt sensitive payment data
- Log all billing operations for audit trails

## Testing

Run the billing test suite:
```bash
php tests/test_billing_services.php
```

This validates:
- Usage logging
- Quota enforcement
- Subscription management
- Invoice generation
- Notification system

## Troubleshooting

### Quota Not Enforcing

Check that:
1. `QUOTA_ENFORCEMENT_ENABLED=true` in `.env`
2. Quota has `is_hard_limit` set to `true`
3. Usage is being logged correctly
4. Quota period matches usage tracking period

### Notifications Not Sending

Verify:
1. `BILLING_NOTIFICATIONS_ENABLED=true` in `.env`
2. Notification service is initialized
3. Tenant has valid billing email
4. Check notification status in database

### Asaas Integration Issues

Confirm:
1. Valid API key in `.env`
2. Correct environment (sandbox vs production)
3. Network connectivity to Asaas API
4. Customer created before payment

## Migration from Non-Billing Setup

To migrate an existing deployment:

1. Run new migrations:
```bash
php tests/run_tests.php
```

2. Create tenant for existing users:
```php
$tenant = $tenantService->createTenant([
    'name' => 'Existing Customer',
    'slug' => 'existing-customer'
]);
```

3. Migrate existing resources:
```sql
UPDATE agents SET tenant_id = 'new_tenant_id' WHERE tenant_id IS NULL;
UPDATE prompts SET tenant_id = 'new_tenant_id' WHERE tenant_id IS NULL;
```

4. Set default quotas:
```php
$quotaService->setQuota($tenantId, 'message', 10000, 'monthly');
```

5. Enable tracking in application code (see integration guide)

## Support

For issues or questions:
- Check the [GitHub Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- Review [Asaas Documentation](https://docs.asaas.com/)
- Contact support team

## License

MIT License - See LICENSE file for details
