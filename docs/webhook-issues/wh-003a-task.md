Create migrations defining the subscriber schema for all supported DBs exactly as required in SPEC §8.

**Specification Reference:**  
`docs/SPEC_WEBHOOK.md` §8 (subscriber table)

**Deliverables:**
- Three dialect-specific SQL migration files
- Stored under `db/migrations/`

**Implementation Guidance:**
The project already contains a `db/migrations` directory with SQL files for different database dialects. The new migrations for `webhook_subscribers` should follow the existing file naming convention and structure. The schema should adhere strictly to SPEC §8.

**Example (conceptual):**
```sql
-- In db/migrations/036_create_webhook_subscribers.sql (SQLite)
CREATE TABLE IF NOT EXISTS webhook_subscribers (
  id TEXT PRIMARY KEY,
  client_id TEXT NOT NULL,
  url TEXT NOT NULL,
  secret TEXT NOT NULL,
  events TEXT NOT NULL, -- JSON string
  active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```