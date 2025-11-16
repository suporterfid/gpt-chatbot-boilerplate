Provide tools in the admin UI or via a CLI script to help developers test and debug webhooks. This should include a way to manually trigger events, inspect dispatched payloads, and view delivery logs.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §10

**Deliverables:**
- Admin UI webhook testing interface
- CLI tool for webhook testing
- Mock webhook receiver for testing

**Features:**
- Send test webhook to a URL
- Inspect request/response
- Validate webhook signatures
- Mock webhook endpoint for development