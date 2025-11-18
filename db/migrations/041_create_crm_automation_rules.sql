-- LeadSense CRM: Automation Rules table
-- Stores event-driven automation rules

CREATE TABLE IF NOT EXISTS crm_automation_rules (
    id TEXT PRIMARY KEY,                    -- UUID
    client_id TEXT NULL,                    -- tenant/client
    name TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,   -- boolean
    -- Trigger configuration
    trigger_event TEXT NOT NULL,            -- 'lead.created', 'lead.stage_changed', etc.
    trigger_filter TEXT NULL,               -- JSON: conditions for triggering
    -- Action configuration
    action_type TEXT NOT NULL,              -- 'webhook', 'slack', 'email', 'whatsapp'
    action_config TEXT NOT NULL,            -- JSON: action parameters
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    archived_at TEXT NULL
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_automation_client_active
    ON crm_automation_rules (client_id, is_active, trigger_event);

CREATE INDEX IF NOT EXISTS idx_crm_automation_trigger
    ON crm_automation_rules (trigger_event, is_active);
