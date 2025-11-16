Create `public/webhook/inbound.php` to expose the POST JSON contract mandated by SPEC §4, replacing ad-hoc listeners with a canonical endpoint for agents and integrators.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §4

**Deliverables:**
- New script loading config/autoloaders
- Validates HTTP method
- Forwards requests to the gateway service
- Returns standardized JSON responses

**Implementation Guidance:**
The existing `chat-unified.php` script serves as a good architectural precedent for an HTTP entrypoint. It handles request validation, content type negotiation, and routing to a handler class. The new `inbound.php` should follow a similar pattern: validate the request is `POST`, load `config.php`, instantiate the `WebhookGateway`, pass the request body and headers to it, and render the JSON response.

```php
// From: chat-unified.php
// This pattern of loading config, validating, and routing to a handler is a good model.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ChatHandler.php';

$chatHandler = new ChatHandler($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... similar validation and routing logic ...
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ...
}
```