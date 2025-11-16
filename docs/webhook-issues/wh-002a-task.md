Centralize HMAC validation, timestamp skew enforcement, and IP/ASN whitelist checks in `includes/WebhookSecurityService.php`, replacing scattered signature logic.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §6

**Deliverables:**
- Methods: `validateSignature`, `enforceClockSkew`, `checkWhitelist`
- Configurable via webhooks config entries

**Implementation Guidance:**
The current system centralizes configuration in `config.php`, which is then used by services like `ChatHandler` for rate limiting. The `WebhookSecurityService` should follow this pattern, consuming security-related settings (e.g., secrets, whitelist IPs) from the global config. The methods in this service will encapsulate security logic that is currently absent or would otherwise be scattered.

```php
// From: includes/ChatHandler.php
// The security service should be instantiated with config, similar to ChatHandler.
class WebhookSecurityService {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function validateSignature($header, $body, $secret) {
        // ... HMAC validation logic ...
    }

    public function enforceClockSkew($timestamp) {
        // ... Timestamp validation logic ...
    }

    public function checkWhitelist($ip) {
        // ... IP whitelist check logic from config ...
    }
}
```