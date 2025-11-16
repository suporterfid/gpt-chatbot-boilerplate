Write tests covering the outbound webhook flow. This should include testing the `WebhookDispatcher`'s fan-out logic, ensuring the retry scheduler correctly calculates backoff periods, and verifying that all delivery attempts are accurately recorded by the `WebhookLogRepository`.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md`

**Deliverables:**
- Test suite for WebhookDispatcher
- Test suite for retry logic
- Test suite for WebhookLogRepository

**Test Cases:**
- Fan-out to multiple subscribers
- Exponential backoff calculation
- Maximum retry limit
- DLQ processing
- Log persistence
- Delivery success/failure handling