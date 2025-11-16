Update `scripts/worker.php` to support per-subscriber secrets, attempts, and logs.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**  
- Enhanced job payload schema  
- Standardized headers  
- Log integration

**Implementation Guidance:**
A worker script will be needed to process jobs from the queue. This script will be responsible for the actual HTTP POST to the subscriber's URL, handling signing, and logging the outcome. The `websocket-server.php` logic for making outbound calls can serve as a reference for the HTTP client implementation.

```php
// In scripts/worker.php - add webhook_delivery job handler
case 'webhook_delivery':
    $subscriber = $job['payload']['subscriber'];
    $payload = $job['payload']['payload'];
    
    // 1. Log attempt in WebhookLogRepository
    // 2. Sign payload with subscriber's secret
    // 3. Make HTTP POST request
    // 4. Log response and status
    // 5. If failed, schedule retry
    break;
```