-- Migration: Create quotas table
-- Description: Define usage limits per tenant

CREATE TABLE IF NOT EXISTS quotas (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    resource_type TEXT NOT NULL CHECK(resource_type IN ('message', 'completion', 'file_upload', 'file_storage', 'vector_query', 'tool_call', 'embedding', 'api_call')),
    limit_value INTEGER NOT NULL,
    period TEXT NOT NULL CHECK(period IN ('hourly', 'daily', 'monthly', 'total')),
    is_hard_limit INTEGER NOT NULL DEFAULT 0,
    notification_threshold INTEGER NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(tenant_id, resource_type, period),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_quotas_tenant_id ON quotas(tenant_id);
CREATE INDEX IF NOT EXISTS idx_quotas_resource_type ON quotas(resource_type);
CREATE INDEX IF NOT EXISTS idx_quotas_tenant_resource ON quotas(tenant_id, resource_type);
