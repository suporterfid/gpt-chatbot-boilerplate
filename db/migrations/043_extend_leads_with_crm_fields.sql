-- LeadSense CRM: Extend leads table with pipeline and deal fields
-- This migration adds CRM capabilities to existing LeadSense leads

-- Add pipeline and stage tracking
ALTER TABLE leads ADD COLUMN pipeline_id TEXT NULL;
ALTER TABLE leads ADD COLUMN stage_id TEXT NULL;

-- Add ownership tracking
ALTER TABLE leads ADD COLUMN owner_id TEXT NULL;
ALTER TABLE leads ADD COLUMN owner_type TEXT NULL;

-- Add deal/opportunity fields
ALTER TABLE leads ADD COLUMN deal_value REAL NULL;
ALTER TABLE leads ADD COLUMN currency TEXT NULL;
ALTER TABLE leads ADD COLUMN probability INTEGER NULL;
ALTER TABLE leads ADD COLUMN expected_close_date TEXT NULL;

-- Add tags (JSON array)
ALTER TABLE leads ADD COLUMN tags TEXT NULL;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_leads_pipeline_stage
    ON leads (pipeline_id, stage_id);

CREATE INDEX IF NOT EXISTS idx_leads_owner
    ON leads (owner_id, owner_type);

CREATE INDEX IF NOT EXISTS idx_leads_deal_value
    ON leads (deal_value);

CREATE INDEX IF NOT EXISTS idx_leads_expected_close
    ON leads (expected_close_date);

-- Note: Foreign key constraints added separately for compatibility
-- SQLite requires table recreation for FK constraints, which we'll handle in app logic
-- For MySQL/PostgreSQL, uncomment the following:
-- ALTER TABLE leads ADD CONSTRAINT fk_leads_pipeline
--     FOREIGN KEY (pipeline_id) REFERENCES crm_pipelines(id) ON DELETE SET NULL;
-- ALTER TABLE leads ADD CONSTRAINT fk_leads_stage
--     FOREIGN KEY (stage_id) REFERENCES crm_pipeline_stages(id) ON DELETE SET NULL;
