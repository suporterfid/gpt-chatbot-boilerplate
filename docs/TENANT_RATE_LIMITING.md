# Tenant-Based Rate Limiting

## Overview

This document describes the tenant-based rate limiting implementation that replaces the legacy IP-based rate limiting system. The new system provides fair resource allocation across multiple tenants while preventing individual tenants from monopolizing system resources.

## Why Tenant-Based Rate Limiting?

### Problems with IP-Based Rate Limiting

1. **No tenant isolation**: Multiple tenants behind the same IP (e.g., corporate proxy, NAT) share the same rate limit
2. **Unfair resource allocation**: A single high-traffic tenant can exhaust limits for all users
3. **Security risk**: Malicious actors can exhaust resources for legitimate users
4. **No accountability**: Cannot track or bill individual tenants accurately

### Benefits of Tenant-Based Rate Limiting

✅ **Fair resource allocation**: Each tenant gets their own rate limit quota  
✅ **Better cost control**: Prevent runaway API costs per tenant  
✅ **Improved security**: Isolate abuse to specific tenants  
✅ **Accurate tracking**: Monitor and bill per-tenant usage  
✅ **Flexible configuration**: Different limits for different tenant tiers

## Architecture

### Components

1. **TenantRateLimitService** (`includes/TenantRateLimitService.php`)
   - Core rate limiting logic using sliding window algorithm
   - Supports multiple resource types (api_call, message, completion, etc.)
   - Configurable per-tenant limits via database quotas

2. **ChatHandler** (`includes/ChatHandler.php`)
   - Integrates rate limiting into chat endpoints
   - Checks both rate limits and quotas before processing
   - Supports legacy IP-based fallback for backwards compatibility

3. **Admin API** (`admin-api.php`)
   - Uses tenant/user-based rate limiting for admin operations
   - No longer relies on IP addresses

4. **Chat Unified** (`chat-unified.php`)
   - Extracts tenant ID from multiple sources (headers, API keys, parameters)
   - Initializes multi-tenant services and passes context to handlers

### Rate Limiting Strategy

```
┌─────────────────────────────────────────────────────────────┐
│                    Request Arrives                           │
└────────────────────────────┬────────────────────────────────┘
                             │
                ┌────────────▼────────────┐
                │  Extract Tenant ID      │
                │  (API Key, Header, etc) │
                └────────────┬────────────┘
                             │
                ┌────────────▼────────────────────┐
                │  Tenant ID Found?               │
                └─────┬──────────────────┬────────┘
                      │ YES              │ NO
            ┌─────────▼────────┐  ┌─────▼──────────────┐
            │ Tenant-Based     │  │ Legacy IP-Based    │
            │ Rate Limiting    │  │ (Backwards Compat) │
            └─────────┬────────┘  └─────┬──────────────┘
                      │                 │
                      └────────┬────────┘
                               │
                    ┌──────────▼───────────┐
                    │  Check Rate Limit    │
                    │  (Sliding Window)    │
                    └──────────┬───────────┘
                               │
                    ┌──────────▼───────────┐
                    │  Within Limit?       │
                    └──┬────────────────┬──┘
                       │ YES            │ NO
                ┌──────▼──────┐  ┌─────▼────────┐
                │ Allow       │  │ Return 429   │
                │ Request     │  │ (Rate Limit  │
                │             │  │  Exceeded)   │
                └─────────────┘  └──────────────┘
```

## Implementation Details

### Tenant ID Extraction

The system extracts tenant ID from multiple sources (in order of priority):

1. **X-Tenant-ID Header**: Explicit tenant identification
   ```http
   X-Tenant-ID: tenant_12345
   ```

2. **API Key Lookup**: Bearer token in Authorization header
   ```http
   Authorization: Bearer sk_live_abc123...
   ```
   The system looks up the API key in the `admin_api_keys` table to find the associated tenant.

3. **X-API-Key Header**: Alternative API key header
   ```http
   X-API-Key: sk_live_abc123...
   ```

4. **Request Parameter**: tenant_id in GET/POST parameters
   ```
   ?tenant_id=tenant_12345
   ```

5. **Fallback**: If no tenant ID is found, uses legacy IP-based rate limiting for backwards compatibility.

### Rate Limit Configuration

#### Default Limits (Per Tenant)

| Resource Type   | Limit | Window  | Description                |
|----------------|-------|---------|----------------------------|
| `api_call`     | 60    | 60s     | General API requests       |
| `message`      | 100   | 3600s   | Chat messages per hour     |
| `completion`   | 100   | 3600s   | AI completions per hour    |
| `file_upload`  | 10    | 3600s   | File uploads per hour      |
| `vector_query` | 1000  | 3600s   | Vector store queries/hour  |
| `tool_call`    | 200   | 3600s   | Tool executions per hour   |
| `embedding`    | 500   | 3600s   | Embedding generations/hour |
| `admin_api`    | 300   | 60s     | Admin API requests/minute  |

#### Custom Tenant Limits

Tenants can have custom limits defined in the `quotas` table:

```sql
INSERT INTO quotas (tenant_id, resource_type, limit_value, period)
VALUES ('tenant_premium', 'api_call', 1000, 'hourly');
```

Supported periods:
- `hourly`: 3600 seconds
- `daily`: 86400 seconds
- `monthly`: 2592000 seconds (30 days)

### Sliding Window Algorithm

The system uses a **sliding window** algorithm for fair rate limiting:

1. **Record timestamps**: Each request timestamp is stored
2. **Filter expired**: On each check, remove timestamps outside the current window
3. **Count remaining**: Compare count against limit
4. **Allow or deny**: Grant access if within limit, return 429 if exceeded

**Example**:
```
Limit: 5 requests per 60 seconds
Window: [-----------------------------60s-----------------------------]

Time 0:   Request 1  ✓ (1/5)
Time 10:  Request 2  ✓ (2/5)
Time 20:  Request 3  ✓ (3/5)
Time 30:  Request 4  ✓ (4/5)
Time 40:  Request 5  ✓ (5/5)
Time 50:  Request 6  ✗ (6/5) RATE LIMIT EXCEEDED

Time 65:  Request 7  ✓ (1/5) - Request 1 expired, window moved
```

## Usage Examples

### Checking Rate Limits Programmatically

```php
require_once 'includes/TenantRateLimitService.php';

$db = new DB($config);
$rateLimitService = new TenantRateLimitService($db);

// Check if tenant can make a request
$check = $rateLimitService->checkRateLimit(
    'tenant_12345',      // Tenant ID
    'api_call',          // Resource type
    60,                  // Limit
    60                   // Window in seconds
);

if ($check['allowed']) {
    // Process request
    $rateLimitService->recordRequest('tenant_12345', 'api_call', 60);
} else {
    // Return 429 error
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limit exceeded',
        'limit' => $check['limit'],
        'current' => $check['current'],
        'reset_at' => $check['reset_at']
    ]);
}
```

### Enforcing Rate Limits (Automatic)

```php
// Throws exception if limit exceeded
try {
    $rateLimitService->enforceRateLimit(
        'tenant_12345',
        'message',
        100,   // 100 messages
        3600   // per hour
    );
    
    // Process the message
    // ...
} catch (Exception $e) {
    if ($e->getCode() == 429) {
        // Handle rate limit exceeded
        header('HTTP/1.1 429 Too Many Requests');
        echo json_encode(['error' => $e->getMessage()]);
    }
}
```

### Getting Tenant Rate Limit Status

```php
// Get current rate limit status for all resource types
$status = $rateLimitService->getTenantRateLimitStatus('tenant_12345');

foreach ($status as $resource) {
    echo "{$resource['resource_type']}: ";
    echo "{$resource['current']}/{$resource['limit']} ";
    echo "({$resource['percentage']}% used)\n";
}

// Output:
// api_call: 45/60 (75% used)
// message: 23/100 (23% used)
// completion: 18/100 (18% used)
// ...
```

### Admin API: Setting Custom Rate Limits

```bash
# Set custom rate limit for a tenant
curl -X POST "https://api.example.com/admin-api.php?action=set_quota" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "tenant_premium",
    "resource_type": "api_call",
    "limit_value": 1000,
    "period": "hourly",
    "is_hard_limit": true,
    "notification_threshold": 80
  }'
```

### Client: Including Tenant ID in Requests

**Using Header (Recommended)**:
```javascript
fetch('/chat-unified.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Tenant-ID': 'tenant_12345'
  },
  body: JSON.stringify({
    message: 'Hello!',
    conversation_id: 'conv_abc123'
  })
});
```

**Using API Key**:
```javascript
fetch('/chat-unified.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer sk_live_abc123...'
  },
  body: JSON.stringify({
    message: 'Hello!',
    conversation_id: 'conv_abc123'
  })
});
```

## Error Handling

### Rate Limit Exceeded (429)

**SSE Response**:
```json
{
  "event": "error",
  "data": {
    "code": 429,
    "message": "Rate limit exceeded. Limit: 60/60s. Try again in 15s",
    "type": "rate_limit_exceeded"
  }
}
```

**JSON Response**:
```json
{
  "error": {
    "message": "Rate limit exceeded. Limit: 60/60s. Try again in 15s",
    "code": 429,
    "status": 429
  }
}
```

### Client-Side Handling

```javascript
async function sendMessage(message) {
  try {
    const response = await fetch('/chat-unified.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Tenant-ID': getTenantId()
      },
      body: JSON.stringify({
        message: message,
        conversation_id: getConversationId()
      })
    });
    
    if (response.status === 429) {
      const error = await response.json();
      // Show user-friendly message
      showNotification(
        'Rate limit exceeded. Please wait before sending another message.',
        'warning'
      );
      
      // Parse retry time from error message
      const retryMatch = error.error.message.match(/Try again in (\d+)s/);
      if (retryMatch) {
        const retryAfter = parseInt(retryMatch[1]);
        setTimeout(() => {
          enableSendButton();
        }, retryAfter * 1000);
      }
      return;
    }
    
    // Handle success
    const data = await response.json();
    displayMessage(data.response);
    
  } catch (error) {
    console.error('Error sending message:', error);
  }
}
```

## Monitoring and Metrics

### Admin Dashboard Integration

The rate limiting status can be displayed in the admin dashboard:

```php
// Get rate limit status for dashboard
$status = $rateLimitService->getTenantRateLimitStatus($tenantId);

// Example output:
[
  {
    "resource_type": "api_call",
    "limit": 60,
    "current": 45,
    "remaining": 15,
    "window_seconds": 60,
    "reset_at": 1699564800,
    "percentage": 75.00
  },
  // ... more resource types
]
```

### Alerts and Notifications

Set up alerts when tenants approach their limits:

```php
$status = $rateLimitService->getTenantRateLimitStatus($tenantId);

foreach ($status as $resource) {
    if ($resource['percentage'] > 80) {
        // Send warning notification
        sendNotification($tenantId, 
            "Warning: {$resource['resource_type']} usage at {$resource['percentage']}%"
        );
    }
    
    if ($resource['percentage'] >= 100) {
        // Send critical notification
        sendNotification($tenantId, 
            "Critical: {$resource['resource_type']} limit exceeded!"
        );
    }
}
```

## Performance Considerations

### Caching Strategy

- Rate limit data is stored in `/tmp/ratelimit_*` files
- Each tenant + resource type combination has its own cache file
- Files are cleaned up automatically when windows expire

### Cleanup

Old rate limit cache files can be cleaned up periodically:

```php
// Clean up cache files older than 24 hours
$cleaned = $rateLimitService->cleanupCache(86400);
echo "Cleaned up $cleaned cache files\n";
```

Run this via cron:
```bash
# Clean up rate limit cache daily
0 0 * * * php /path/to/scripts/cleanup_rate_limits.php
```

### Performance Tips

1. **Use database quotas**: Define tenant-specific limits in the database for persistence
2. **Monitor cache directory**: Ensure `/tmp` has sufficient space and inodes
3. **Adjust window sizes**: Larger windows = more timestamps to store (trade-off between precision and storage)
4. **Use Redis** (future): For high-traffic deployments, consider Redis backend instead of file-based storage

## Migration Guide

### From IP-Based to Tenant-Based

1. **Enable usage tracking** in `.env`:
   ```
   USAGE_TRACKING_ENABLED=true
   ```

2. **Assign tenant IDs** to existing users/API keys:
   ```sql
   UPDATE admin_api_keys 
   SET tenant_id = 'tenant_default'
   WHERE tenant_id IS NULL;
   ```

3. **Set up default quotas** for all tenants:
   ```sql
   INSERT INTO quotas (tenant_id, resource_type, limit_value, period)
   VALUES 
     ('tenant_default', 'api_call', 60, 'hourly'),
     ('tenant_default', 'message', 100, 'hourly');
   ```

4. **Update client integrations** to include tenant ID or API keys in requests

5. **Monitor and adjust** limits based on actual usage patterns

## Backwards Compatibility

### Legacy IP-Based Fallback

The system maintains backwards compatibility with IP-based rate limiting:

- **When tenant ID is present**: Uses tenant-based rate limiting
- **When tenant ID is missing**: Falls back to IP-based rate limiting
- **Whitelabel agents**: Use agent_public_id as tenant identifier

### Deprecation Notice

The IP-based rate limiting (`checkRateLimitLegacy` method) is deprecated and will be removed in a future version. All integrations should migrate to tenant-based authentication.

## Troubleshooting

### Rate limit not working

1. **Check if usage tracking is enabled**:
   ```php
   var_dump($config['usage_tracking']['enabled']);
   ```

2. **Verify tenant ID is being extracted**:
   ```php
   $tenantId = extractTenantId($db);
   error_log("Tenant ID: " . ($tenantId ?? 'NULL'));
   ```

3. **Check cache file permissions**:
   ```bash
   ls -la /tmp/ratelimit_*
   ```

### Rate limit too strict/lenient

1. **Check default limits** in `TenantRateLimitService::getDefaultRateLimit()`
2. **Verify custom quotas** in database:
   ```sql
   SELECT * FROM quotas WHERE tenant_id = 'your_tenant_id';
   ```
3. **Adjust window size** for more/less granular control

### High cache file count

1. **Run cleanup** to remove old files:
   ```php
   $rateLimitService->cleanupCache(86400); // 24 hours
   ```

2. **Set up automated cleanup** via cron job

## Best Practices

1. ✅ **Always include tenant ID**: Use X-Tenant-ID header or API keys
2. ✅ **Set appropriate limits**: Balance between usability and cost control
3. ✅ **Monitor usage**: Track per-tenant usage patterns
4. ✅ **Notify users**: Send alerts before limits are reached
5. ✅ **Document limits**: Make rate limits clear in API documentation
6. ✅ **Implement retry logic**: Handle 429 errors gracefully on client side
7. ✅ **Use exponential backoff**: When retrying after rate limit
8. ✅ **Test limits**: Verify rate limiting works in development

## References

- [TenantRateLimitService.php](../includes/TenantRateLimitService.php) - Core implementation
- [ChatHandler.php](../includes/ChatHandler.php) - Integration with chat endpoints
- [admin-api.php](../admin-api.php) - Admin API rate limiting
- [chat-unified.php](../chat-unified.php) - Tenant ID extraction
- [Test Suite](../tests/test_tenant_rate_limiting.php) - Comprehensive tests

## Support

For questions or issues related to rate limiting:
- Check the logs for rate limit errors
- Review the test suite for usage examples
- Consult the admin API documentation for managing quotas
