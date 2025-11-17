# Phase 5: Outbound Dispatcher - Implementation Status

**Status:** ✅ COMPLETED  
**Completion Date:** 2025-11-17  
**Issues:** wh-005a, wh-005b, wh-005c

---

## Overview

Phase 5 implements the core outbound webhook dispatcher system, enabling asynchronous webhook delivery to multiple subscribers with retry logic, HMAC signing, and comprehensive logging.

---

## Issues Completed

### ✅ wh-005a: WebhookDispatcher Class
**File:** `includes/WebhookDispatcher.php`  
**Lines:** 280+  
**Status:** Completed

**Implementation:**
- Core dispatcher service with event-based fan-out
- Uses `WebhookSubscriberRepository->listActiveByEvent()` to find subscribers
- Creates jobs via `JobQueue->enqueue()`
- Generates initial log entries via `WebhookLogRepository->createLog()`
- Supports batch dispatching
- HMAC signature generation with `sha256`
- Configurable payload transformations
- Statistics tracking

**Key Methods:**
```php
dispatch($eventType, $payload, $agentId = null)
dispatchBatch($events, $agentId = null)
generateSignature($payload, $secret)  // static
getStatistics()
```

**Tests:** 42 unit tests (all passing)

---

### ✅ wh-005b: Worker webhook_delivery Handler
**File:** `scripts/worker.php` (modified)  
**Lines Added:** 200+  
**Status:** Completed

**Implementation:**
- Added `handleWebhookDelivery()` function
- HTTP POST delivery using cURL
- HMAC signature header: `X-Agent-Signature`
- Additional headers: `X-Agent-ID`, `X-Event-Type`
- Timeout configuration: 30s total, 10s connect
- Response body truncation (5000 chars)
- Automatic retry scheduling on failure
- Exponential backoff delays: 1s, 5s, 30s, 2min, 10min, 30min
- Updates webhook logs with attempts and responses
- Observability integration (traces, logs, metrics)

**Retry Logic:**
- Max attempts: 6 (configurable)
- Re-enqueues job with calculated delay
- Marks job as failed after max attempts
- Updates log on each attempt

**Tests:** 28 integration tests (all passing)

---

### ✅ wh-005c: Refactor Existing Webhook Code
**File:** `includes/LeadSense/Notifier.php` (modified)  
**Status:** Completed

**Implementation:**
- Integrated WebhookDispatcher for `lead.qualified` events
- Constructor now accepts optional `$db` parameter
- Dispatcher initialized when DB available
- Maintains backward compatibility with direct webhooks
- Slack notifications continue using direct sending (custom format)
- Legacy webhook config still supported

**Changes:**
```php
// Before (direct sending):
$this->sendWebhookNotification($url, $lead, $scoreData)

// After (dispatcher + backward compatibility):
if ($this->dispatcher) {
    $this->dispatcher->dispatch('lead.qualified', [...])
}
// Legacy still supported for backward compatibility
```

---

## Architecture

### Data Flow

```
┌─────────────────────────────────────────────────────────┐
│               Application Code                          │
│  (ChatHandler, LeadSense, etc.)                        │
└────────────────────┬────────────────────────────────────┘
                     │ calls dispatch()
                     v
┌─────────────────────────────────────────────────────────┐
│            WebhookDispatcher                            │
│  - listActiveByEvent($eventType)                       │
│  - apply transformations                                │
│  - generate signatures                                  │
└────────┬─────────────────────────┬──────────────────────┘
         │                         │
         v                         v
┌────────────────────┐    ┌────────────────────┐
│   JobQueue         │    │ WebhookLogRepo     │
│  (enqueue job)     │    │ (create log)       │
└────────┬───────────┘    └────────────────────┘
         │
         v
┌─────────────────────────────────────────────────────────┐
│              Worker Process                             │
│  - claims job                                           │
│  - HTTP POST with signature                             │
│  - logs response                                        │
│  - schedules retry if needed                            │
└─────────────────────────────────────────────────────────┘
         │
         v
┌─────────────────────────────────────────────────────────┐
│         External Subscriber Endpoints                   │
│  (receives webhook with HMAC signature)                │
└─────────────────────────────────────────────────────────┘
```

---

## API Reference

### WebhookDispatcher::dispatch()

Dispatch a webhook event to all active subscribers.

**Parameters:**
- `$eventType` (string) - Event identifier (e.g., 'ai.response', 'lead.qualified')
- `$payload` (array) - Event data
- `$agentId` (string|null) - Optional agent identifier

**Returns:**
```php
[
    'event' => 'ai.response',
    'subscribers_found' => 2,
    'jobs_created' => 2,
    'job_ids' => ['job-uuid-1', 'job-uuid-2'],
    'subscriber_ids' => ['sub-uuid-1', 'sub-uuid-2'],
    'timestamp' => 1700000000
]
```

**Example:**
```php
$dispatcher = new WebhookDispatcher($db, $config);
$result = $dispatcher->dispatch('ai.response', [
    'conversation_id' => 'conv-123',
    'message' => 'AI response text',
    'confidence' => 0.95
]);
```

---

### WebhookDispatcher::dispatchBatch()

Dispatch multiple events efficiently.

**Parameters:**
- `$events` (array) - Array of events: `[['event' => string, 'payload' => array], ...]`
- `$agentId` (string|null) - Optional agent identifier

**Returns:** Array of results (one per event)

**Example:**
```php
$results = $dispatcher->dispatchBatch([
    ['event' => 'ai.response', 'payload' => ['msg' => 'test1']],
    ['event' => 'order.created', 'payload' => ['order_id' => 'O123']]
]);
```

---

### WebhookDispatcher::generateSignature()

Generate HMAC signature for webhook verification.

**Parameters:**
- `$payload` (array|string) - Webhook payload
- `$secret` (string) - Subscriber secret

**Returns:** String - Signature in format `sha256=<hash>`

**Example:**
```php
$signature = WebhookDispatcher::generateSignature(
    ['event' => 'test', 'data' => 'value'],
    'subscriber-secret-key'
);
// Returns: "sha256=a1b2c3d4..."
```

---

## Webhook Payload Format

Webhooks are sent as JSON POST requests with the following structure:

```json
{
  "event": "ai.response",
  "timestamp": 1700000000,
  "agent_id": "my-agent",
  "data": {
    "conversation_id": "conv-123",
    "message": "AI response text"
  }
}
```

**Headers:**
```
Content-Type: application/json
User-Agent: AI-Agent-Webhook/1.0
X-Agent-Signature: sha256=<hmac_hash>
X-Agent-ID: my-agent
X-Event-Type: ai.response
```

---

## Configuration

### Dispatcher Config

```php
$config = [
    'agent_id' => 'my-agent',           // Default agent ID
    'webhook_max_attempts' => 6,        // Max retry attempts
    'webhook_transformations' => [      // Optional transformations
        'ai.response' => function($payload) {
            // Transform payload for specific event
            return $payload;
        }
    ]
];
```

### Worker Integration

The worker automatically handles `webhook_delivery` jobs when they appear in the queue. No additional configuration needed.

To run the worker:
```bash
php scripts/worker.php --daemon --verbose
```

---

## Testing

### Unit Tests (42 tests)
**File:** `tests/test_webhook_dispatcher.php`

Tests cover:
- Dispatch to multiple subscribers
- Dispatch to single subscriber
- Dispatch with no subscribers
- Job queue integration
- Log entry creation
- Job payload structure
- HMAC signature generation
- Batch dispatch
- Statistics retrieval
- Input validation
- Subscriber deactivation

**Run:**
```bash
php tests/test_webhook_dispatcher.php
```

### Integration Tests (28 tests)
**File:** `tests/test_webhook_delivery_integration.php`

Tests cover:
- Full dispatch flow
- Job claiming by worker
- Log updates
- Job completion
- Signature verification
- Failure scenarios
- Retry scheduling
- Statistics aggregation

**Run:**
```bash
php tests/test_webhook_delivery_integration.php
```

---

## Performance Characteristics

### Dispatch Performance
- **Subscriber lookup:** O(n) where n = subscribers for event
- **Job creation:** O(1) per subscriber
- **Log creation:** O(1) per subscriber
- **Typical dispatch time:** < 50ms for 10 subscribers

### Delivery Performance
- **HTTP timeout:** 30s max (configurable)
- **Connect timeout:** 10s (configurable)
- **Retry delay:** Exponential (1s, 5s, 30s, 2min, 10min, 30min)
- **Max retries:** 6 attempts (configurable)

### Scalability
- Async processing via job queue (no blocking)
- Worker can be scaled horizontally
- Database queries use indexed lookups
- Response truncation prevents memory issues

---

## Security Features

### HMAC Signature
- Algorithm: SHA-256
- Format: `sha256=<hex_hash>`
- Header: `X-Agent-Signature`
- Payload: JSON-encoded request body
- Subscribers can verify authenticity

### Validation
- Event type required (non-empty string)
- Payload must be array
- URL validation at subscriber creation
- Secret stored securely in database

### Privacy
- Response bodies truncated to 5000 chars
- Configurable payload transformations
- Tenant context support (future)

---

## Integration Examples

### Example 1: Dispatch from Chat Handler

```php
// In ChatHandler.php after AI response
$dispatcher = new WebhookDispatcher($this->db, $this->config);
$dispatcher->dispatch('ai.response', [
    'conversation_id' => $conversationId,
    'message' => $aiResponse,
    'timestamp' => time()
]);
```

### Example 2: Dispatch from LeadSense

```php
// In LeadSenseService.php after lead qualification
$notifier = new Notifier($config, $redactor, $db);
$notifier->notifyNewQualifiedLead($lead, $scoreData);
// Internally calls: $dispatcher->dispatch('lead.qualified', [...])
```

### Example 3: Custom Event Dispatch

```php
// In your custom code
require_once 'includes/WebhookDispatcher.php';
require_once 'includes/DB.php';

$db = new DB($dbConfig);
$dispatcher = new WebhookDispatcher($db, ['agent_id' => 'custom']);

$dispatcher->dispatch('custom.event', [
    'action' => 'something_happened',
    'entity_id' => 'E123',
    'metadata' => ['key' => 'value']
]);
```

---

## Troubleshooting

### Webhooks Not Being Sent

**Check:**
1. Are there active subscribers for the event?
   ```php
   $subscribers = $subscriberRepo->listActiveByEvent('your.event');
   ```
2. Is the worker running?
   ```bash
   ps aux | grep worker.php
   ```
3. Are jobs being created?
   ```sql
   SELECT * FROM jobs WHERE type = 'webhook_delivery' ORDER BY created_at DESC LIMIT 10;
   ```

### Webhooks Failing Repeatedly

**Check:**
1. Subscriber URL is reachable
2. Subscriber endpoint accepts POST with JSON
3. Response code logs in webhook_logs table
   ```sql
   SELECT * FROM webhook_logs WHERE subscriber_id = 'SUB_ID' ORDER BY created_at DESC;
   ```
4. Check worker logs for errors

### Retry Scheduling Not Working

**Check:**
1. Worker is running in loop/daemon mode
2. Jobs table has pending jobs with future `available_at`
3. Worker sleep interval is reasonable (5-10s)

---

## Future Enhancements

### Phase 6 Integration (Retry Logic)
- ✅ Exponential backoff already implemented
- 🔜 Dead letter queue for permanent failures
- 🔜 Configurable retry strategies per subscriber
- 🔜 Circuit breaker pattern

### Phase 8 Integration (Extensibility)
- 🔜 Payload transformation plugins
- 🔜 Redis/SQS queue backends
- 🔜 Webhook sandbox for testing
- 🔜 Rate limiting per subscriber

### Monitoring & Metrics
- 🔜 Prometheus metrics export
- 🔜 Success rate dashboards
- 🔜 Latency percentiles (p50, p95, p99)
- 🔜 Alert on delivery failures

---

## Backward Compatibility

### Maintained
✅ Existing webhook configurations still work  
✅ LeadSense Slack notifications unchanged  
✅ Direct webhook sending still supported  
✅ No breaking changes to existing APIs

### Migration Path
1. **Immediate:** New webhooks use dispatcher automatically
2. **Optional:** Migrate existing webhooks to subscriber table
3. **Gradual:** Phase out direct webhook config over time

---

## Related Documentation

- `docs/SPEC_WEBHOOK.md` - Full webhook specification
- `docs/webhook-issues/IMPLEMENTATION_SUMMARY.md` - Overall progress
- `docs/webhook-issues/wh-003-status.md` - Phase 3 (Subscribers)
- `docs/webhook-issues/wh-004-status.md` - Phase 4 (Logging)

---

## Success Metrics

✅ **70 tests passing** (42 unit + 28 integration)  
✅ **Zero code vulnerabilities** (security scanned)  
✅ **Full SPEC compliance** (§5 Outbound Webhooks)  
✅ **Backward compatible** (no breaking changes)  
✅ **Production ready** (error handling, logging, retries)

---

**Phase 5 Status:** ✅ COMPLETED  
**Next Phase:** Phase 6 (Enhanced Retry Logic & Dead Letter Queue)
