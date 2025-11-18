-- LeadSense CRM: Relax lead_events type constraint
-- Remove CHECK constraint to allow new CRM event types
-- Migration for Task 3: Add New Lead Events Types

-- SQLite requires table recreation to modify constraints

BEGIN TRANSACTION;

-- Create new table without CHECK constraint
CREATE TABLE lead_events_new (
    id TEXT PRIMARY KEY,
    lead_id TEXT NOT NULL,
    type TEXT NOT NULL,  -- No CHECK constraint - allow any event type
    payload_json TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

-- Copy all existing data
INSERT INTO lead_events_new SELECT * FROM lead_events;

-- Drop old table
DROP TABLE lead_events;

-- Rename new table to original name
ALTER TABLE lead_events_new RENAME TO lead_events;

-- Recreate indexes
CREATE INDEX IF NOT EXISTS idx_lead_events_lead_id ON lead_events(lead_id);
CREATE INDEX IF NOT EXISTS idx_lead_events_type ON lead_events(type);
CREATE INDEX IF NOT EXISTS idx_lead_events_created_at ON lead_events(created_at);

COMMIT;
