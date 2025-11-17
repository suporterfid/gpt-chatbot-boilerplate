# Webhook Inbound Endpoint Documentation

## Overview

The webhook inbound endpoint (`public/webhook/inbound.php`) provides a canonical HTTP entrypoint for receiving webhook events from external systems, following the specification defined in [SPEC_WEBHOOK.md §4](SPEC_WEBHOOK.md#-4-inbound-webhook-specification).

## Endpoint Details

**URL:** `POST /webhook/inbound` (via `public/webhook/inbound.php`)  
**Content-Type:** `application/json` (required)  
**Method:** `POST` only

## Request Format

### Required Fields

```json
{
  "event": "order.created",
  "timestamp": 1731602712,
  "data": { "order_id": "A12345" },
  "signature": "sha256=xxxx" // Optional, required only if WEBHOOK_GATEWAY_SECRET is set
}
```

### Field Descriptions

- **event** (string, required): Event type identifier (e.g., `order.created`, `user.updated`)
- **timestamp** (integer, required): Unix timestamp of the event
- **data** (object, required): Event payload data (can be empty object `{}`)
- **signature** (string, optional): HMAC SHA256 signature for request validation

## Response Format

### Success Response (200 OK)

```json
{
  "status": "received",
  "event": "order.created",
  "received_at": 1731603333
}
```

### Error Responses

#### 405 Method Not Allowed
```json
{
  "error": "method_not_allowed",
  "message": "Only POST requests are accepted"
}
```

#### 415 Unsupported Media Type
```json
{
  "error": "unsupported_media_type",
  "message": "Content-Type must be application/json"
}
```

#### 400 Bad Request (Empty Body)
```json
{
  "error": "empty_body",
  "message": "Request body cannot be empty"
}
```

#### 400 Bad Request (Invalid JSON)
```json
{
  "error": "invalid_json",
  "message": "Request body must be valid JSON"
}
```

#### 400 Bad Request (Missing Event)
```json
{
  "error": "invalid_event",
  "message": "Event is required"
}
```

#### 400 Bad Request (Missing Timestamp)
```json
{
  "error": "invalid_timestamp",
  "message": "Timestamp is required"
}
```

#### 422 Unprocessable Entity (Timestamp Out of Tolerance)
```json
{
  "error": "invalid_timestamp",
  "message": "Timestamp outside tolerated window"
}
```

#### 401 Unauthorized (Missing Signature)
```json
{
  "error": "missing_signature",
  "message": "Signature is required"
}
```

#### 401 Unauthorized (Invalid Signature)
```json
{
  "error": "invalid_signature",
  "message": "Invalid signature"
}
```

#### 500 Internal Server Error
```json
{
  "error": "internal_error",
  "message": "Unable to process webhook"
}
```

## Security

### HMAC Signature Validation

When `WEBHOOK_GATEWAY_SECRET` is configured in the environment, all requests must include a valid HMAC signature.

#### Signature Generation

```php
$secret = getenv('WEBHOOK_GATEWAY_SECRET');
$body = json_encode($payload);
$signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
```

#### Signature Placement

The signature can be provided in two ways:

1. **HTTP Header** (recommended):
   ```
   X-Agent-Signature: sha256=abc123...
   ```

2. **Payload Field**:
   ```json
   {
     "signature": "sha256=abc123...",
     "event": "order.created",
     "timestamp": 1731602712,
     "data": {}
   }
   ```

### Timestamp Tolerance

To prevent replay attacks, the endpoint validates that the request timestamp is within an acceptable time window. The default tolerance is 300 seconds (5 minutes).

Configure the tolerance via environment variable:
```bash
WEBHOOK_GATEWAY_TOLERANCE=300
```

Set to `0` to disable timestamp validation.

## Configuration

### Environment Variables

#### Legacy Webhook Gateway Variables
- **WEBHOOK_GATEWAY_SECRET**: Secret key for HMAC signature validation (optional)
- **WEBHOOK_GATEWAY_TOLERANCE**: Timestamp tolerance in seconds (default: 300)
- **WEBHOOK_GATEWAY_LOG_PAYLOADS**: Log full payloads for debugging (default: false)

#### Inbound Webhook Configuration (SPEC_WEBHOOK.md §9)
- **WEBHOOK_INBOUND_ENABLED**: Enable/disable inbound webhook processing (default: true)
- **WEBHOOK_INBOUND_PATH**: Endpoint path for inbound webhooks (default: /webhook/inbound)
- **WEBHOOK_VALIDATE_SIGNATURE**: Enable HMAC signature validation (default: true)
- **WEBHOOK_MAX_CLOCK_SKEW**: Maximum allowed time difference in seconds (default: 120)
- **WEBHOOK_IP_WHITELIST**: Comma-separated list of allowed IPs or CIDR ranges (optional)

#### Outbound Webhook Configuration (SPEC_WEBHOOK.md §9)
- **WEBHOOK_OUTBOUND_ENABLED**: Enable/disable outbound webhook dispatching (default: true)
- **WEBHOOK_MAX_ATTEMPTS**: Maximum retry attempts for failed deliveries (default: 6)
- **WEBHOOK_TIMEOUT**: Request timeout in seconds (default: 5)
- **WEBHOOK_CONCURRENCY**: Maximum concurrent outbound requests (default: 10)

### Example .env Configuration

```bash
# Legacy webhook gateway settings
WEBHOOK_GATEWAY_SECRET=your-secret-key-here
WEBHOOK_GATEWAY_TOLERANCE=300
WEBHOOK_GATEWAY_LOG_PAYLOADS=false

# Inbound webhook configuration
WEBHOOK_INBOUND_ENABLED=true
WEBHOOK_INBOUND_PATH=/webhook/inbound
WEBHOOK_VALIDATE_SIGNATURE=true
WEBHOOK_MAX_CLOCK_SKEW=120
WEBHOOK_IP_WHITELIST=

# Outbound webhook configuration
WEBHOOK_OUTBOUND_ENABLED=true
WEBHOOK_MAX_ATTEMPTS=6
WEBHOOK_TIMEOUT=5
WEBHOOK_CONCURRENCY=10
```

## Testing

### Test Files

- `tests/test_webhook_inbound.php`: Comprehensive endpoint validation tests
- `tests/test_webhook_signature.php`: HMAC signature validation tests

### Running Tests

```bash
# Start PHP development server
php -S localhost:8888 -t .

# In another terminal, run tests
php tests/test_webhook_inbound.php
php tests/test_webhook_signature.php
```

### Example cURL Requests

#### Basic Request (No Signature)
```bash
curl -X POST http://localhost:8888/public/webhook/inbound.php \
  -H "Content-Type: application/json" \
  -d '{
    "event": "order.created",
    "timestamp": '$(date +%s)',
    "data": {"order_id": "A12345"}
  }'
```

#### Request with Signature
```bash
SECRET="your-secret-key"
PAYLOAD='{"event":"order.created","timestamp":'$(date +%s)',"data":{"order_id":"A12345"}}'
SIGNATURE="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')"

curl -X POST http://localhost:8888/public/webhook/inbound.php \
  -H "Content-Type: application/json" \
  -H "X-Agent-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

## Implementation Details

### Architecture

The endpoint follows a clean architecture pattern similar to `chat-unified.php`:

1. **Request Validation** (`inbound.php`):
   - Method validation (POST only)
   - Content-Type validation
   - Body validation (non-empty, valid JSON)

2. **Business Logic** (`WebhookGatewayService`):
   - Event extraction and validation
   - Timestamp validation with tolerance window
   - HMAC signature verification
   - Payload logging (if enabled)

3. **Observability** (`ObservabilityMiddleware`):
   - Request/response logging
   - Metrics collection
   - Error tracking

### Class Responsibilities

- **`inbound.php`**: HTTP entrypoint, request validation, response rendering
- **`WebhookGatewayService`**: Business logic, validation, signature verification
- **`ObservabilityMiddleware`**: Logging, metrics, tracing

## Monitoring

### Metrics

The endpoint emits the following metrics (when observability is enabled):

- `chatbot_webhook_inbound_total{event, status}`: Counter of received webhooks

### Logs

Logs are structured and include:
- Event type
- Timestamp
- Remote IP address
- Processing status
- Errors (if any)

## Troubleshooting

### Common Issues

1. **401 Invalid Signature**
   - Verify `WEBHOOK_GATEWAY_SECRET` matches between sender and receiver
   - Ensure signature is calculated on the exact raw request body
   - Check signature format: `sha256=<64 hex chars>`

2. **422 Timestamp Outside Window**
   - Ensure sender and receiver clocks are synchronized
   - Adjust `WEBHOOK_GATEWAY_TOLERANCE` if needed
   - Check that timestamp is in Unix seconds (not milliseconds)

3. **415 Unsupported Media Type**
   - Verify `Content-Type: application/json` header is set
   - Some HTTP clients may not set this automatically

## Security Best Practices

1. **Always use HTTPS** in production to prevent man-in-the-middle attacks
2. **Set WEBHOOK_GATEWAY_SECRET** to enable signature validation
3. **Use a strong, random secret** (at least 32 characters)
4. **Keep timestamp tolerance** as low as practical (300s default)
5. **Enable payload logging** only for debugging, not in production
6. **Monitor failed requests** to detect potential attacks

## Related Documentation

- [SPEC_WEBHOOK.md](SPEC_WEBHOOK.md): Complete webhook specification
- [API Documentation](api.md): General API documentation
- [Deployment Guide](deployment.md): Production deployment guidelines
