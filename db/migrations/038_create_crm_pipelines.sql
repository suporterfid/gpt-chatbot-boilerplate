-- LeadSense CRM: Pipelines table
-- Represents CRM boards/pipelines for organizing leads

CREATE TABLE IF NOT EXISTS crm_pipelines (
    id TEXT PRIMARY KEY,                    -- UUID
    client_id TEXT NULL,                    -- tenant/client (multi-tenancy)
    name TEXT NOT NULL,
    description TEXT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,  -- boolean (0/1)
    color TEXT NULL,                        -- hex color or Tailwind token
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    archived_at TEXT NULL
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_pipelines_client_default 
    ON crm_pipelines (client_id, is_default);

CREATE INDEX IF NOT EXISTS idx_crm_pipelines_archived 
    ON crm_pipelines (archived_at);
