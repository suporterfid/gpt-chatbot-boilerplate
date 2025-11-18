-- LeadSense CRM: Lead Assignments table
-- Tracks historical ownership assignments

CREATE TABLE IF NOT EXISTS crm_lead_assignments (
    id TEXT PRIMARY KEY,                    -- UUID
    lead_id TEXT NOT NULL,
    owner_id TEXT NOT NULL,                 -- admin user id or agent id
    owner_type TEXT NOT NULL,               -- 'admin_user', 'agent', 'external'
    assigned_by TEXT NULL,                  -- who made the assignment
    note TEXT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    ended_at TEXT NULL,
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_lead_assignments_lead
    ON crm_lead_assignments (lead_id);

CREATE INDEX IF NOT EXISTS idx_crm_lead_assignments_owner
    ON crm_lead_assignments (owner_id, owner_type);

CREATE INDEX IF NOT EXISTS idx_crm_lead_assignments_active
    ON crm_lead_assignments (lead_id, ended_at);
