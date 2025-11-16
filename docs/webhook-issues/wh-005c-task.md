Ensure all components (e.g., `includes/LeadSense/Notifier.php`) that currently send webhooks directly are refactored to use the `WebhookDispatcher` service. This will centralize outbound webhook logic, ensuring consistent signing, logging, and retries across the application.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**
- Refactored existing webhook sending code
- All outbound webhooks use the dispatcher
- Deprecated direct webhook sending