Create `includes/WebhookGateway.php` to encapsulate JSON parsing, schema validation, payload normalization, downstream event routing, and consistent responses per SPEC §4.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §4

**Deliverables:**
- Class with `handleRequest($headers, $body)`
- Returns structured arrays/errors
- Reusable across HTTP entrypoints

**Implementation Guidance:**
The `ChatHandler` class is the primary orchestration service in the existing architecture. The new `WebhookGateway` should adopt a similar design, with a main public method (`handleRequest`) that coordinates validation, data processing, and calls to other services. It should manage the flow and return structured data, similar to how `ChatHandler::handleChatRequest` does.

```php
// From: includes/ChatHandler.php
// The new WebhookGateway should have a similar structure.
class WebhookGateway {
    public function __construct($config) {
        // ...
    }

    public function handleRequest($headers, $body) {
        // 1. Validate JSON schema
        // 2. Call WebhookSecurityService
        // 3. Normalize payload
        // 4. Route to downstream handlers (e.g., JobQueue)
        // 5. Return structured response
    }
}
```