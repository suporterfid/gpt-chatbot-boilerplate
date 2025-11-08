-- Migration 034: Add Compliance Features
-- Adds support for data retention, PII redaction, and compliance reporting

-- Add PII redaction flag to tenants table
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS pii_redaction_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE tenants ADD INDEX IF NOT EXISTS idx_pii_redaction (pii_redaction_enabled);

-- Create usage aggregates table for long-term storage after retention cleanup
CREATE TABLE IF NOT EXISTS usage_aggregates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    count INT NOT NULL DEFAULT 0,
    total_units BIGINT NOT NULL DEFAULT 0,
    total_cost DECIMAL(10,4) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_aggregate (tenant_id, event_type, date),
    INDEX idx_tenant_date (tenant_id, date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add data deletion audit event type if not exists
-- (audit_events table already exists, just documenting the new event type)
-- Event types: data_export, data_deletion

-- Add indexes for better performance on compliance queries
ALTER TABLE audit_events ADD INDEX IF NOT EXISTS idx_event_type_date (event_type, created_at);
ALTER TABLE user_consents ADD INDEX IF NOT EXISTS idx_expires_at (expires_at);
ALTER TABLE audit_conversations ADD INDEX IF NOT EXISTS idx_tenant_created (tenant_id, created_at);
ALTER TABLE channel_messages ADD INDEX IF NOT EXISTS idx_tenant_created (tenant_id, created_at);
ALTER TABLE usage_logs ADD INDEX IF NOT EXISTS idx_tenant_created (tenant_id, created_at);
