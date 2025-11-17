# Webhook Extensibility, Testing & Monitoring

This document describes the extensibility features, testing tools, and monitoring capabilities for the webhook system.

**Reference:** SPEC_WEBHOOK.md §10  
**Implementation:** wh-008a, wh-008b, wh-008c

## Table of Contents

1. [Hook System for Payload Transformations](#hook-system)
2. [Pluggable Queue Drivers](#queue-drivers)
3. [Testing Tools](#testing-tools)
4. [Metrics and Monitoring](#metrics)

---

## Hook System for Payload Transformations {#hook-system}

The webhook dispatcher supports registering custom transformation hooks that modify webhook payloads before delivery.

### Registering Hooks

```php
require_once 'includes/WebhookDispatcher.php';

$dispatcher = new WebhookDispatcher($db, $config);

// Register a global hook (applies to all events)
$dispatcher->registerTransform('*', function($payload) {
    // Add custom metadata
    $payload['data']['processed_by'] = 'my_system';
    $payload['data']['version'] = '1.0';
    return $payload;
});

// Register event-specific hook
$dispatcher->registerTransform('ai.response', function($payload) {
    // Sanitize sensitive data for this event type
    if (isset($payload['data']['user_email'])) {
        $payload['data']['user_email_hash'] = hash('sha256', $payload['data']['user_email']);
        unset($payload['data']['user_email']);
    }
    return $payload;
});

// Dispatch webhook with transformations applied
$result = $dispatcher->dispatch('ai.response', [
    'message' => 'Hello',
    'user_email' => 'user@example.com'
]);
```

### Hook Execution Order

Hooks are applied in the following order:

1. Global hooks (`event_type = '*'`) in registration order
2. Event-specific hooks in registration order
3. Legacy config-based transformations (for backward compatibility)

### Managing Hooks

```php
// Method chaining is supported
$dispatcher
    ->registerTransform('order.created', $hook1)
    ->registerTransform('order.created', $hook2)
    ->clearTransformHooks('old.event');

// Get all registered hooks
$hooks = $dispatcher->getTransformHooks();

// Clear specific event hooks
$dispatcher->clearTransformHooks('ai.response');

// Clear all hooks
$dispatcher->clearTransformHooks();
```

### Use Cases

**1. Data Enrichment**
```php
$dispatcher->registerTransform('*', function($payload) {
    $payload['data']['server_hostname'] = gethostname();
    $payload['data']['environment'] = getenv('APP_ENV');
    return $payload;
});
```

**2. Tenant-Specific Transformations**
```php
$dispatcher->registerTransform('*', function($payload) use ($tenantId) {
    $payload['tenant_id'] = $tenantId;
    $payload['data']['tenant_metadata'] = getTenantMetadata($tenantId);
    return $payload;
});
```

**3. Data Sanitization**
```php
$dispatcher->registerTransform('user.created', function($payload) {
    // Remove sensitive fields
    unset($payload['data']['password']);
    unset($payload['data']['credit_card']);
    return $payload;
});
```

**4. Format Conversion**
```php
$dispatcher->registerTransform('legacy.event', function($payload) {
    // Convert to legacy format for backward compatibility
    return [
        'event' => $payload['event'],
        'timestamp' => $payload['timestamp'],
        'payload' => $payload['data'] // Renamed field
    ];
});
```

---

## Pluggable Queue Drivers {#queue-drivers}

The webhook system supports pluggable queue backends, allowing you to use Redis, RabbitMQ, SQS, or any custom implementation.

### Queue Driver Interface

All queue drivers must implement the `QueueDriverInterface`:

```php
interface QueueDriverInterface {
    public function enqueue($jobType, $payload, $maxAttempts = 3, $delay = 0);
    public function getJobStatus($jobId);
}
```

### Using Redis Driver

```php
require_once 'includes/RedisQueueDriver.php';

// Initialize Redis connection
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Create Redis queue driver
$redisDriver = new RedisQueueDriver($redis, 'webhook_queue');

// Set as queue driver for dispatcher
$dispatcher->setQueueDriver($redisDriver);

// Webhooks will now be queued to Redis
$dispatcher->dispatch('ai.response', ['message' => 'test']);
```

### Creating Custom Queue Drivers

Example: RabbitMQ driver

```php
require_once 'includes/QueueDriverInterface.php';

class RabbitMQDriver implements QueueDriverInterface {
    private $connection;
    private $channel;
    
    public function __construct($host, $port, $user, $pass) {
        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare('webhooks', false, true, false, false);
    }
    
    public function enqueue($jobType, $payload, $maxAttempts = 3, $delay = 0) {
        $jobId = uniqid('rmq_', true);
        
        $message = json_encode([
            'id' => $jobId,
            'type' => $jobType,
            'payload' => $payload,
            'max_attempts' => $maxAttempts,
            'delay' => $delay
        ]);
        
        $msg = new AMQPMessage($message, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);
        
        $this->channel->basic_publish($msg, '', 'webhooks');
        
        return $jobId;
    }
    
    public function getJobStatus($jobId) {
        // Implementation depends on your tracking mechanism
        return ['id' => $jobId, 'status' => 'queued'];
    }
}

// Usage
$rabbitMQ = new RabbitMQDriver('localhost', 5672, 'user', 'pass');
$dispatcher->setQueueDriver($rabbitMQ);
```

### Example: AWS SQS Driver

```php
require_once 'vendor/autoload.php';
use Aws\Sqs\SqsClient;

class SQSDriver implements QueueDriverInterface {
    private $client;
    private $queueUrl;
    
    public function __construct($region, $queueUrl) {
        $this->client = new SqsClient([
            'region' => $region,
            'version' => 'latest'
        ]);
        $this->queueUrl = $queueUrl;
    }
    
    public function enqueue($jobType, $payload, $maxAttempts = 3, $delay = 0) {
        $jobId = uniqid('sqs_', true);
        
        $message = [
            'id' => $jobId,
            'type' => $jobType,
            'payload' => $payload,
            'max_attempts' => $maxAttempts
        ];
        
        $result = $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => json_encode($message),
            'DelaySeconds' => $delay
        ]);
        
        return $jobId;
    }
    
    public function getJobStatus($jobId) {
        // Query SQS or DynamoDB for job status
        return ['id' => $jobId, 'status' => 'queued'];
    }
}
```

---

## Testing Tools {#testing-tools}

### CLI Testing Tool

The CLI tool provides comprehensive webhook testing capabilities.

#### Send Test Webhook

```bash
php scripts/test_webhook.php send \
  --url "https://example.com/webhook" \
  --event "ai.response" \
  --data '{"message":"test","user_id":123}' \
  --secret "my-webhook-secret"
```

Output:
```
=== Sending Test Webhook ===

URL: https://example.com/webhook
Event: ai.response
Data: {
    "message": "test",
    "user_id": 123
}

Signature: sha256=abc123...

=== Response ===

HTTP Status: 200
Duration: 45.23 ms

Headers:
Content-Type: application/json
...

Body:
{"status":"received","timestamp":1234567890}
```

#### Validate Signature

```bash
php scripts/test_webhook.php validate-signature \
  --body '{"event":"test","timestamp":123}' \
  --secret "my-secret" \
  --signature "sha256=abc123..."
```

Output:
```
=== Validating Webhook Signature ===

Body: {"event":"test","timestamp":123}
Secret: *********
Provided Signature: sha256=abc123...

Expected Signature: sha256=abc123...

✓ Signature is VALID
```

#### Inspect Logs

```bash
# View recent logs
php scripts/test_webhook.php inspect-logs --limit 20

# Filter by subscriber
php scripts/test_webhook.php inspect-logs \
  --subscriber-id "abc-123" \
  --limit 10
```

#### Mock Webhook Server

Start a development webhook receiver:

```bash
php scripts/test_webhook.php mock-server --port 8080
```

The mock server will:
- Accept all webhook requests
- Log request details to console and file
- Respond with 200 OK
- Useful for development and debugging

### Admin UI Testing Interface

Access the webhook testing tools at: `/admin/#webhook-testing`

Features:
- **Send Test Webhooks**: Interactive form to send test webhooks with custom payloads
- **Validate Signatures**: Test HMAC signature validation
- **View Metrics**: Real-time webhook delivery statistics
- **Inspect Logs**: Browse recent delivery attempts

---

## Metrics and Monitoring {#metrics}

### Collected Metrics

The webhook system tracks the following metrics:

1. **webhook_deliveries_total** (counter)
   - Labels: `event_type`, `status`
   - Total number of webhook deliveries

2. **webhook_delivery_duration_seconds** (histogram)
   - Labels: `event_type`
   - Delivery latency in seconds

3. **webhook_retry_count** (counter)
   - Labels: `attempt_number`
   - Number of retries by attempt

4. **webhook_queue_depth** (gauge)
   - Current number of pending delivery jobs

### Recording Metrics

Metrics are automatically recorded by the webhook worker, but you can also record manually:

```php
require_once 'includes/WebhookMetrics.php';

$metrics = new WebhookMetrics($db);

// Record a delivery
$metrics->recordDelivery(
    'ai.response',      // event type
    'success',          // status: success, failed, pending
    0.123,              // duration in seconds
    1                   // attempt number
);

// Update queue depth
$metrics->updateQueueDepth(42);

// Increment custom counter
$metrics->incrementCounter('custom_metric', [
    'label' => 'value'
]);

// Record histogram observation
$metrics->observeHistogram('request_size', 1024.5, [
    'endpoint' => '/api/webhook'
]);

// Set gauge value
$metrics->setGauge('active_connections', 10, []);
```

### Prometheus Endpoint

Metrics are exposed in Prometheus format at `/webhook/metrics`:

```bash
curl http://localhost/webhook/metrics

# Output:
# TYPE webhook_deliveries_total counter
webhook_deliveries_total{event_type="ai.response",status="success"} 150
webhook_deliveries_total{event_type="ai.response",status="failed"} 5

# TYPE webhook_delivery_duration_seconds histogram
webhook_delivery_duration_seconds{event_type="ai.response"} 0.123
webhook_delivery_duration_seconds{event_type="ai.response"} 0.456
...
```

### JSON Statistics API

Get aggregated statistics in JSON format:

```bash
curl http://localhost/webhook/metrics?format=json

{
  "deliveries": {
    "total": 155,
    "success": 150,
    "failed": 5,
    "success_rate": 96.77,
    "by_event_type": {
      "ai.response": 100,
      "order.created": 55
    }
  },
  "latency": {
    "avg": 0.234,
    "p50": 0.200,
    "p95": 0.500,
    "p99": 0.800,
    "max": 1.200
  },
  "retries": {
    "total_retries": 10,
    "by_attempt": {
      "2": 7,
      "3": 3
    }
  },
  "queue_depth": 5
}
```

### Prometheus Configuration

Add to your `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'webhook_metrics'
    scrape_interval: 30s
    static_configs:
      - targets: ['localhost:80']
    metrics_path: /webhook/metrics
```

### Grafana Dashboard

Example queries for Grafana:

```promql
# Success rate
rate(webhook_deliveries_total{status="success"}[5m]) 
/ 
rate(webhook_deliveries_total[5m]) * 100

# P95 latency
histogram_quantile(0.95, webhook_delivery_duration_seconds)

# Error rate by event type
sum(rate(webhook_deliveries_total{status="failed"}[5m])) by (event_type)

# Queue depth over time
webhook_queue_depth
```

### Alerting

Example Prometheus alerts:

```yaml
groups:
  - name: webhook_alerts
    rules:
      - alert: WebhookHighFailureRate
        expr: |
          rate(webhook_deliveries_total{status="failed"}[5m]) 
          / 
          rate(webhook_deliveries_total[5m]) > 0.1
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High webhook failure rate"
          description: "Webhook failure rate is {{ $value | humanizePercentage }}"
      
      - alert: WebhookHighLatency
        expr: |
          histogram_quantile(0.95, 
            rate(webhook_delivery_duration_seconds[5m])
          ) > 2.0
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High webhook delivery latency"
          description: "P95 latency is {{ $value }}s"
      
      - alert: WebhookQueueBacklog
        expr: webhook_queue_depth > 1000
        for: 10m
        labels:
          severity: critical
        annotations:
          summary: "Webhook queue backlog"
          description: "Queue depth is {{ $value }} jobs"
```

### Maintenance

Clean up old metrics:

```php
$metrics = new WebhookMetrics($db);

// Clean metrics older than 30 days (default)
$metrics->cleanOldMetrics(30);

// Clean metrics older than 7 days
$metrics->cleanOldMetrics(7);
```

Consider setting up a cron job:

```bash
# Clean old metrics daily
0 2 * * * cd /path/to/app && php -r "require 'includes/DB.php'; require 'includes/WebhookMetrics.php'; \$db = new DB(); \$m = new WebhookMetrics(\$db); \$m->cleanOldMetrics(30);"
```

---

## Complete Example

Putting it all together:

```php
<?php
require_once 'includes/DB.php';
require_once 'includes/WebhookDispatcher.php';
require_once 'includes/WebhookMetrics.php';
require_once 'includes/RedisQueueDriver.php';

// Initialize
$config = require 'config.php';
$db = new DB($config['database']);
$dispatcher = new WebhookDispatcher($db, $config);
$metrics = new WebhookMetrics($db);

// Setup Redis queue driver
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redisDriver = new RedisQueueDriver($redis);
$dispatcher->setQueueDriver($redisDriver);

// Register transformation hooks
$dispatcher->registerTransform('*', function($payload) {
    $payload['data']['server'] = gethostname();
    return $payload;
});

$dispatcher->registerTransform('ai.response', function($payload) {
    // Sanitize PII
    if (isset($payload['data']['email'])) {
        $payload['data']['email'] = hash('sha256', $payload['data']['email']);
    }
    return $payload;
});

// Dispatch webhook
$startTime = microtime(true);
$result = $dispatcher->dispatch('ai.response', [
    'message' => 'Hello, World!',
    'email' => 'user@example.com'
]);
$duration = microtime(true) - $startTime;

// Record metrics
foreach ($result['job_ids'] as $i => $jobId) {
    $metrics->recordDelivery(
        'ai.response',
        'success',
        $duration,
        1
    );
}

$metrics->updateQueueDepth(
    count($redis->lRange('webhook_queue:pending', 0, -1))
);

echo "Dispatched to {$result['jobs_created']} subscribers\n";
echo "Job IDs: " . implode(', ', $result['job_ids']) . "\n";
```

---

## Best Practices

1. **Hook Performance**: Keep transformation hooks lightweight - they run synchronously during dispatch
2. **Queue Selection**: Choose queue backend based on scale and requirements
3. **Metrics Retention**: Clean old metrics regularly to prevent database growth
4. **Testing**: Use CLI tools and mock server during development
5. **Monitoring**: Set up alerts for high failure rates and latency
6. **Security**: Always validate signatures when testing with real endpoints

---

## Troubleshooting

### Hooks Not Applied

Check hook registration:
```php
$hooks = $dispatcher->getTransformHooks();
var_dump($hooks);
```

### Queue Driver Errors

Verify driver implements interface:
```php
if ($driver instanceof QueueDriverInterface) {
    echo "Driver is valid\n";
}
```

### Metrics Not Recording

Check database connection and table creation:
```php
$sql = "SELECT COUNT(*) FROM webhook_metrics";
$result = $db->query($sql);
var_dump($result);
```

### Missing Prometheus Metrics

Ensure endpoint is accessible:
```bash
curl -v http://localhost/webhook/metrics
```

---

For more information, see:
- `docs/SPEC_WEBHOOK.md` - Webhook system specification
- `docs/api.md` - API documentation
- `includes/WebhookDispatcher.php` - Implementation details
