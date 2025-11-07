-- Migration: Create tenant_usage aggregation table
-- Description: Pre-aggregated usage statistics per tenant for efficient billing queries

CREATE TABLE IF NOT EXISTS tenant_usage (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    resource_type TEXT NOT NULL CHECK(resource_type IN ('message', 'completion', 'file_upload', 'file_storage', 'vector_query', 'tool_call', 'embedding', 'api_call')),
    period_type TEXT NOT NULL CHECK(period_type IN ('hourly', 'daily', 'monthly', 'total')),
    period_start TEXT NOT NULL,
    period_end TEXT NOT NULL,
    event_count INTEGER NOT NULL DEFAULT 0,
    total_quantity INTEGER NOT NULL DEFAULT 0,
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(tenant_id, resource_type, period_type, period_start),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_tenant_usage_tenant_id ON tenant_usage(tenant_id);
CREATE INDEX IF NOT EXISTS idx_tenant_usage_resource_type ON tenant_usage(resource_type);
CREATE INDEX IF NOT EXISTS idx_tenant_usage_period ON tenant_usage(period_type, period_start);
CREATE INDEX IF NOT EXISTS idx_tenant_usage_tenant_period ON tenant_usage(tenant_id, period_type, period_start);
CREATE INDEX IF NOT EXISTS idx_tenant_usage_lookup ON tenant_usage(tenant_id, resource_type, period_type, period_start);
