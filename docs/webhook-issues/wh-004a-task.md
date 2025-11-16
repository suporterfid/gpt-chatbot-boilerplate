Create log table migrations matching SPEC §8 for recording delivery attempts.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §8 (logs)

**Deliverables:**  
- Three SQL migrations  
- Capture request/response/attempt metadata

**Implementation Guidance:**
Similar to the `webhook_subscribers` table, create dialect-specific migration files in `db/migrations/` for the `webhook_logs` table. The schema must align with SPEC §8 to capture all necessary details for logging and retries.