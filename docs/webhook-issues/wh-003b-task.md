Implement CRUD/query operations for webhook subscribers, enabling dispatcher fan-out logic.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§5 & 8

**Deliverables:**  
- PHP class under `includes/`  
- Methods: `listActiveByEvent($eventType)`, `save($subscriber)`

**Implementation Guidance:**
A repository class should encapsulate all database interactions for the `webhook_subscribers` table. The existing architecture does not appear to have a dedicated repository layer, but the `ChatHandler` performs direct database operations for conversation history. The `WebhookSubscriberRepository` should be a new, dedicated class for this purpose.

```php
// in includes/WebhookSubscriberRepository.php
class WebhookSubscriberRepository {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function listActiveByEvent($eventType) {
        $sql = "SELECT * FROM webhook_subscribers WHERE events LIKE ? AND active = 1";
        return $this->db->query($sql, ['%"' . $eventType . '"%']);
    }

    public function save($subscriber) {
        // ... INSERT or UPDATE logic ...
    }
}
```