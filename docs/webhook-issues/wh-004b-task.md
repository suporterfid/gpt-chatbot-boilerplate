Provide helpers to persist and query webhook logs, enabling analytics and retries.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§5 & 8

**Deliverables:**  
- PHP class for log writes/queries  
- Support for pagination and history lookups

**Implementation Guidance:**
Create a `WebhookLogRepository` class under `includes/`. This class will be responsible for all interactions with the `webhook_logs` table. It will be used by the `WebhookDispatcher` to log each delivery attempt.

```php
// in includes/WebhookLogRepository.php
class WebhookLogRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createLog($logData) {
        // Logic to INSERT a new log entry
    }

    public function updateLog($logId, $updateData) {
        // Logic to UPDATE a log entry (e.g., with response)
    }
}
```