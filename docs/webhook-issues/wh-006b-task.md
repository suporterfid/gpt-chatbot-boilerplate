Implement the retry logic within the webhook worker. When a delivery fails, the worker should calculate the next attempt's delay based on the current attempt number (1s, 5s, 30s, etc.) and re-enqueue the job with an updated `scheduled_at` timestamp.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**
- Exponential backoff calculation: 1s, 5s, 30s, 2min, 10min, 30min
- Re-enqueue failed jobs with correct delay
- Maximum 6 attempts before moving to DLQ

**Implementation Guidance:**
```php
// Backoff schedule
$backoffSchedule = [1, 5, 30, 120, 600, 1800]; // seconds
$attemptNumber = $job['attempts'];
$delay = $backoffSchedule[$attemptNumber - 1] ?? 1800;
```