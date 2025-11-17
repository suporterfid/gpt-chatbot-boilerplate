# Webhook Event Processing Documentation

## Overview

This document describes the webhook event processing system implemented in issue **wh-001c**, which integrates normalized webhook events from the `WebhookGateway` into the `JobQueue` system and routes them to appropriate agent handlers.

## Architecture

### Components

1. **WebhookGateway** (`includes/WebhookGateway.php`)
   - Receives and validates incoming webhook requests
   - Normalizes event payloads to a standard format
   - Enqueues events as jobs for async processing
   - Implements idempotency via `webhook_events` table

2. **JobQueue** (`includes/JobQueue.php`)
   - Database-backed job queue system
   - Handles job enqueueing, claiming, and lifecycle management
   - Supports retry with exponential backoff
   - Includes Dead Letter Queue (DLQ) for failed jobs

3. **WebhookEventProcessor** (`includes/WebhookEventProcessor.php`)
   - **NEW**: Adapter class that maps webhook events to agent actions
   - Routes events to appropriate handlers based on event type
   - Integrates with ChatHandler for AI agent interactions
   - Provides observability (logging, metrics, tracing)

4. **Worker** (`scripts/worker.php`)
   - Background job processor
   - Claims jobs from the queue and executes them
   - **UPDATED**: Now includes `webhook_event` handler
   - Supports daemon mode for continuous processing

### Data Flow

```
External System
    ↓
POST /webhook/inbound
    ↓
WebhookGateway::handleRequest()
    ↓ validates & normalizes
    ↓ checks idempotency
    ↓
JobQueue::enqueue('webhook_event', $normalizedPayload)
    ↓
[Job stored in database]
    ↓
Worker::claimNext()
    ↓
handleWebhookEvent()
    ↓
WebhookEventProcessor::processEvent()
    ↓ routes based on event_type
    ↓
ChatHandler / Other Handlers
    ↓
JobQueue::markCompleted()
```

## Event Types and Routing

The `WebhookEventProcessor` supports the following event types:

### Chat Events

| Event Type | Description | Handler |
|------------|-------------|---------|
| `message.created` | New chat message received | `handleChatMessage()` → `ChatHandler::handleChatCompletionSync()` |
| `chat.message` | Alternative format for chat messages | Same as above |

### Conversation Events

| Event Type | Description | Handler |
|------------|-------------|---------|
| `conversation.created` | New conversation initiated | `handleConversationCreated()` |

### File Events

| Event Type | Description | Handler |
|------------|-------------|---------|
| `file.uploaded` | File uploaded notification | `handleFileUploaded()` |

### Vector Store Events

| Event Type | Description | Handler |
|------------|-------------|---------|
| `vector_store.file.completed` | File ingestion completed | Handled by `WebhookHandler` |
| `vector_store.file.failed` | File ingestion failed | Handled by `WebhookHandler` |
| `vector_store.completed` | Vector store processing complete | Handled by `WebhookHandler` |

### Agent Events

| Event Type | Description | Handler |
|------------|-------------|---------|
| `agent.trigger` | Trigger specific agent action | `handleAgentTrigger()` |

### System Events

| Event Type | Description | Handler |
|------------|-------------|---------|
| `ping` | Health check / connectivity test | Returns acknowledgment |
| `test.event` | Test event for validation | Returns acknowledgment |

### Unknown Events

Events with unrecognized types are gracefully handled and logged but not processed. This allows for forward compatibility with new event types.

## Configuration

### WebhookGateway Configuration

```php
// config.php
$config['webhooks'] = [
    'gateway_secret' => 'your-hmac-secret',
    'timestamp_tolerance' => 300,  // seconds
    'openai_signing_secret' => 'openai-webhook-secret',
];
```

### Async vs Sync Processing

```php
// Async processing (default) - enqueues job
$webhookGateway = new WebhookGateway($config, $db, null, null, true);

// Sync processing - processes immediately
$webhookGateway = new WebhookGateway($config, $db, null, null, false);
```

## Usage Examples

### Processing a Chat Message via Webhook

**Request:**
```bash
curl -X POST http://your-domain/webhook/inbound \
  -H "Content-Type: application/json" \
  -d '{
    "event": "message.created",
    "timestamp": 1731602712,
    "data": {
      "message": "Hello, I need help with my order",
      "conversation_id": "conv_12345",
      "agent_id": "support_agent",
      "tenant_id": "tenant_abc"
    }
  }'
```

**Response:**
```json
{
  "status": "received",
  "event": "message.created",
  "event_id": "7aea966c04785aef59ecc73ed21f8f35",
  "received_at": 1731602712,
  "processing": "async",
  "job_id": "22e25e9d-d617-4e4c-9ad6-1752f672f3a4"
}
```

**Processing Flow:**
1. WebhookGateway validates and normalizes the event
2. Creates job with type `webhook_event` in JobQueue
3. Returns immediately with job ID
4. Worker claims and processes the job asynchronously
5. WebhookEventProcessor routes to `handleChatMessage()`
6. ChatHandler processes message and generates AI response
7. Job marked as completed with result

### Running the Worker

**Single job processing:**
```bash
php scripts/worker.php
```

**Continuous processing:**
```bash
php scripts/worker.php --loop
```

**Daemon mode with graceful shutdown:**
```bash
php scripts/worker.php --daemon
```

**With verbose logging:**
```bash
php scripts/worker.php --loop --verbose
```

## Idempotency

The system implements idempotency to prevent duplicate event processing:

1. Each event has a unique `event_id` (provided or generated)
2. Before processing, checks `webhook_events` table for existing entry
3. If found and `processed = 1`, returns success without reprocessing
4. After successful processing, marks event as processed
5. Duplicate submissions return success with `note: 'duplicate_event'`

**Example:**
```json
// First submission - processed
{
  "id": "event_12345",
  "event": "message.created",
  "timestamp": 1731602712,
  "data": {...}
}
// Response: {"status": "received", "job_id": "..."}

// Second submission - rejected as duplicate
{
  "id": "event_12345",  // Same ID
  "event": "message.created",
  "timestamp": 1731602712,
  "data": {...}
}
// Response: {"status": "received", "note": "duplicate_event"}
```

## Error Handling

### Validation Errors

The WebhookGateway validates all incoming requests:

- **empty_body**: Request body is empty (HTTP 400)
- **invalid_json**: Request body is not valid JSON (HTTP 400)
- **invalid_event**: Missing or invalid `event` field (HTTP 400)
- **invalid_timestamp**: Missing or invalid `timestamp` field (HTTP 400)
- **invalid_signature**: HMAC signature verification failed (HTTP 401)

### Processing Errors

The WebhookEventProcessor handles errors during event processing:

- **invalid_event_data**: Required data fields missing (e.g., message for chat events)
- **processing_failed**: General processing error (wrapped exception)

Failed jobs are automatically retried with exponential backoff. After max attempts, jobs move to the Dead Letter Queue (DLQ).

## Testing

### Unit Tests

Test the WebhookEventProcessor in isolation:

```bash
php tests/test_webhook_event_processor.php
```

**Tests:**
- Chat message processing
- Conversation creation
- File upload events
- Ping/test events
- Unknown event handling
- Agent triggers
- Error validation

### Integration Tests

Test the complete end-to-end flow:

```bash
php tests/test_webhook_integration.php
```

**Tests:**
- Event submission and job enqueueing
- Job claiming and processing
- Event routing to handlers
- Idempotency enforcement
- Multiple event types
- Job completion tracking

## Extending the System

### Adding New Event Types

To add support for a new event type:

1. **Add handler method** to `WebhookEventProcessor`:

```php
private function handleMyNewEvent(array $data, array $fullEvent): array {
    // Extract required data
    $customField = $data['custom_field'] ?? null;
    
    // Validate
    if (!$customField) {
        throw new WebhookEventProcessorException(
            'custom_field is required',
            'invalid_event_data'
        );
    }
    
    // Process
    $result = $this->processCustomLogic($customField);
    
    // Return result
    return [
        'status' => 'processed',
        'custom_field' => $customField,
        'result' => $result
    ];
}
```

2. **Add routing** in `routeEventToHandler()`:

```php
switch ($eventType) {
    // ... existing cases ...
    
    case 'my.new.event':
        return $this->handleMyNewEvent($data, $fullEvent);
    
    // ... rest of cases ...
}
```

3. **Add tests** in `test_webhook_event_processor.php`:

```php
echo "\nTest N: Process my.new.event...\n";
try {
    $normalizedEvent = [
        'event_id' => 'test_new_' . bin2hex(random_bytes(8)),
        'event_type' => 'my.new.event',
        'timestamp' => time(),
        'data' => [
            'custom_field' => 'test_value'
        ],
        'received_at' => time(),
        'source' => 'webhook_gateway'
    ];
    
    $result = $processor->processEvent($normalizedEvent);
    
    if ($result['status'] === 'processed') {
        echo "  ✓ New event processed successfully\n";
    }
} catch (Exception $e) {
    echo "  ✗ Exception: " . $e->getMessage() . "\n";
}
```

## Observability

The system includes comprehensive observability:

### Logging

All components log to `error_log` or custom logger:

```php
[WebhookGateway] [INFO] Webhook processed successfully
[WebhookEventProcessor] [DEBUG] Processing chat message from webhook
[WORKER] Processing job webhook_event (attempt: 1)
```

### Metrics

If observability middleware is enabled, metrics are tracked:

- `chatbot_webhook_inbound_total{event, status}` - Webhook requests received
- `chatbot_webhook_processing_duration_ms{event}` - Processing time
- `chatbot_webhook_events_processed_total{event_type, status}` - Events processed
- `chatbot_worker_jobs_completed_total{job_type}` - Jobs completed
- `chatbot_worker_jobs_failed_total{job_type}` - Jobs failed

### Tracing

Distributed tracing spans are created for:

- `webhook.gateway.request` - Webhook request processing
- `webhook.event.process` - Event processing by processor
- `worker.job.process` - Job execution by worker

## Database Schema

### webhook_events

Tracks processed events for idempotency:

```sql
CREATE TABLE webhook_events (
    id TEXT PRIMARY KEY,
    event_id TEXT UNIQUE NOT NULL,
    event_type TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    processed INTEGER DEFAULT 0,
    processed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### jobs

Stores pending and processed jobs:

```sql
CREATE TABLE jobs (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    max_attempts INTEGER NOT NULL,
    attempts INTEGER DEFAULT 0,
    status TEXT NOT NULL,
    available_at DATETIME NOT NULL,
    locked_by TEXT,
    locked_at DATETIME,
    result_json TEXT,
    error_text TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

## Security Considerations

1. **HMAC Signature Verification**: All webhook requests should be signed with HMAC-SHA256
2. **Timestamp Validation**: Reject events outside the tolerance window (default 300s)
3. **Idempotency**: Prevents replay attacks and duplicate processing
4. **Rate Limiting**: Consider implementing rate limiting at the gateway level
5. **Input Validation**: All event data is validated before processing

## Performance Considerations

1. **Async Processing**: Default mode enqueues jobs and returns immediately
2. **Worker Scaling**: Run multiple worker instances for higher throughput
3. **Database Indexes**: Ensure indexes exist on `event_id`, `status`, `available_at`
4. **Job Cleanup**: Periodically clean up old completed jobs
5. **DLQ Monitoring**: Monitor and handle failed jobs in the Dead Letter Queue

## Troubleshooting

### Jobs not being processed

1. Check if worker is running: `ps aux | grep worker.php`
2. Check job status: Query `jobs` table for pending jobs
3. Check worker logs for errors
4. Verify database connection

### Duplicate events being processed

1. Check `webhook_events` table for event_id
2. Verify idempotency checks are not disabled
3. Check for race conditions with multiple webhook submissions

### Events being rejected

1. Check webhook signature configuration
2. Verify timestamp is within tolerance
3. Check required fields are present
4. Review WebhookGateway logs for validation errors

## References

- **Specification**: `docs/SPEC_WEBHOOK.md` §§2-4
- **Issue**: `docs/webhook-issues/wh-001c-task.md`
- **Related Issues**: wh-001a (inbound endpoint), wh-001b (gateway service)
- **Code**: `includes/WebhookEventProcessor.php`, `scripts/worker.php`
- **Tests**: `tests/test_webhook_event_processor.php`, `tests/test_webhook_integration.php`
