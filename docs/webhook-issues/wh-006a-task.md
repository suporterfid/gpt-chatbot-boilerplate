The job queuing system needs to be enhanced to support retry logic. This involves adding metadata to job payloads, such as `attempt_count` and `scheduled_at`, to enable exponential backoff as defined in the spec.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §5

**Deliverables:**
- Extended job schema with retry metadata
- Support for scheduling jobs in the future

**Implementation Guidance:**
Review `includes/JobQueue.php` and add fields to the jobs table schema if needed. The existing `available_at` field should support delayed execution. The `attempts` field already exists and tracks retry count.