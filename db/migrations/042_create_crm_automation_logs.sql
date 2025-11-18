-- LeadSense CRM: Automation Logs table
-- Tracks execution of automation rules

CREATE TABLE IF NOT EXISTS crm_automation_logs (
    id TEXT PRIMARY KEY,                    -- UUID
    rule_id TEXT NOT NULL,
    lead_id TEXT NULL,
    event_type TEXT NOT NULL,
    status TEXT NOT NULL,                   -- 'success', 'error', 'skipped'
    message TEXT NULL,
    payload_json TEXT NULL,                 -- snapshot of event data
    created_at TEXT DEFAULT (datetime('now')),
    
    FOREIGN KEY (rule_id) REFERENCES crm_automation_rules(id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_automation_logs_rule
    ON crm_automation_logs (rule_id, created_at);

CREATE INDEX IF NOT EXISTS idx_crm_automation_logs_lead
    ON crm_automation_logs (lead_id, created_at);

CREATE INDEX IF NOT EXISTS idx_crm_automation_logs_status
    ON crm_automation_logs (status, created_at);
