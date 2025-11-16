Refactor `public/webhook/inbound.php`, `webhooks/openai.php`, and `channels/whatsapp/webhook.php` to use the new centralized security service.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §6

**Deliverables:**
- Dependency injection or helper usage
- Unified signature/whitelist/skew enforcement
- Harmonized error responses

**Implementation Guidance:**
The `WebhookGateway` (and other inbound routes) should instantiate and use the `WebhookSecurityService` at the beginning of the request handling process. This is similar to how `ChatHandler` is used in `chat-unified.php`. Early termination with a standardized error response is critical if security checks fail.

```php
// In public/webhook/inbound.php and other entrypoints
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/WebhookSecurityService.php';
require_once __DIR__ . '/../includes/WebhookGateway.php';

$security = new WebhookSecurityService($config);

// Perform security checks before processing
if (!$security->validateSignature(...) || !$security->checkWhitelist(...)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed']);
    exit;
}

$gateway = new WebhookGateway($config);
$response = $gateway->handleRequest($headers, $body);
// ... return response
```