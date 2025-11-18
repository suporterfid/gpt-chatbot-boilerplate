-- LeadSense CRM: Pipeline Stages table
-- Represents columns/stages within a pipeline (Kanban columns)

CREATE TABLE IF NOT EXISTS crm_pipeline_stages (
    id TEXT PRIMARY KEY,                    -- UUID
    pipeline_id TEXT NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,                     -- unique per pipeline
    position INTEGER NOT NULL DEFAULT 0,    -- column ordering
    color TEXT NULL,                        -- stage color (header)
    is_won INTEGER NOT NULL DEFAULT 0,      -- marks "Closed Won" type stages
    is_lost INTEGER NOT NULL DEFAULT 0,     -- marks "Closed Lost" type stages
    is_closed INTEGER NOT NULL DEFAULT 0,   -- generic "closed" indicator
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    archived_at TEXT NULL,
    
    FOREIGN KEY (pipeline_id) REFERENCES crm_pipelines(id) ON DELETE CASCADE
);

-- Indexes
CREATE UNIQUE INDEX IF NOT EXISTS idx_crm_stages_pipeline_slug
    ON crm_pipeline_stages (pipeline_id, slug);

CREATE INDEX IF NOT EXISTS idx_crm_stages_pipeline_position
    ON crm_pipeline_stages (pipeline_id, position);

CREATE INDEX IF NOT EXISTS idx_crm_stages_archived
    ON crm_pipeline_stages (archived_at);
