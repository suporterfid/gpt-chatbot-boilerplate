-- Migration: Create usage_logs table
-- Description: Track API usage per tenant for billing and metering

CREATE TABLE IF NOT EXISTS usage_logs (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    resource_type TEXT NOT NULL CHECK(resource_type IN ('message', 'completion', 'file_upload', 'file_storage', 'vector_query', 'tool_call', 'embedding')),
    resource_id TEXT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_usage_logs_tenant_id ON usage_logs(tenant_id);
CREATE INDEX IF NOT EXISTS idx_usage_logs_resource_type ON usage_logs(resource_type);
CREATE INDEX IF NOT EXISTS idx_usage_logs_created_at ON usage_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_usage_logs_tenant_date ON usage_logs(tenant_id, created_at);
