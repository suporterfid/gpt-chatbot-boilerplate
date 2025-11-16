Create unit and integration tests for the new inbound webhook components. This includes testing the `WebhookSecurityService` with mock secrets and headers, and ensuring the `WebhookGateway` correctly validates and processes incoming requests.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md`

**Deliverables:**
- Test suite for WebhookSecurityService
- Test suite for WebhookGateway
- Integration tests for inbound endpoint

**Test Cases:**
- Valid signature verification
- Invalid signature rejection
- Clock skew enforcement
- IP whitelist validation
- Malformed JSON handling
- Duplicate event detection