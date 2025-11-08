-- Migration 034: Add Compliance Features
-- Adds support for data retention, PII redaction, and compliance reporting

-- Add PII redaction flag to tenants table (SQLite compatible)
-- SQLite doesn't support IF NOT EXISTS, but ADD COLUMN is idempotent if column doesn't exist
-- If it fails, the column likely already exists which is fine
ALTER TABLE tenants ADD COLUMN pii_redaction_enabled BOOLEAN DEFAULT FALSE;

-- Create usage aggregates table for long-term storage after retention cleanup
CREATE TABLE IF NOT EXISTS usage_aggregates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    count INTEGER NOT NULL DEFAULT 0,
    total_units INTEGER NOT NULL DEFAULT 0,
    total_cost DECIMAL(10,4) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create indexes for usage aggregates
CREATE UNIQUE INDEX IF NOT EXISTS uk_aggregate ON usage_aggregates(tenant_id, event_type, date);
CREATE INDEX IF NOT EXISTS idx_tenant_date ON usage_aggregates(tenant_id, date);

-- Add data deletion audit event type if not exists
-- (audit_events table already exists, just documenting the new event type)
-- Event types: data_export, data_deletion

-- Add indexes for better performance on compliance queries (SQLite compatible)
-- Note: audit_events uses 'type' column, not 'event_type'
-- Some indexes may already exist from earlier migrations, IF NOT EXISTS handles that
CREATE INDEX IF NOT EXISTS idx_expires_at ON user_consents(expires_at);
CREATE INDEX IF NOT EXISTS idx_tenant_created_conversations ON audit_conversations(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_tenant_created_messages ON channel_messages(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_tenant_created_usage ON usage_logs(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_pii_redaction ON tenants(pii_redaction_enabled);
