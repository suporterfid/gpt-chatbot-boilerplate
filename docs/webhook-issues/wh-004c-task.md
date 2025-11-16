Expose webhook delivery history with attempts, statuses, and latencies.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §§8 & 10

**Deliverables:**  
- Admin/API dashboards  
- Filters by subscriber/event/outcome

**Implementation Guidance:**
New API endpoints will be needed in `admin-api.php` to fetch log data from the `WebhookLogRepository`. The Admin UI (`/public/admin/`) will then be extended with a new view to display this data, likely in a paginated table with filtering options.