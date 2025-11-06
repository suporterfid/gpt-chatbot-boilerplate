-- Migration: Add tenant_id to existing tables
-- Description: Adds tenant_id column to all tenant-aware tables for multi-tenancy support

-- Add tenant_id to agents
ALTER TABLE agents ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_agents_tenant_id ON agents(tenant_id);

-- Add tenant_id to prompts
ALTER TABLE prompts ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_prompts_tenant_id ON prompts(tenant_id);

-- Add tenant_id to vector_stores
ALTER TABLE vector_stores ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_vector_stores_tenant_id ON vector_stores(tenant_id);

-- Add tenant_id to admin_users
ALTER TABLE admin_users ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_admin_users_tenant_id ON admin_users(tenant_id);

-- Add tenant_id to audit_conversations
ALTER TABLE audit_conversations ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_audit_conversations_tenant_id ON audit_conversations(tenant_id);

-- Add tenant_id to channel_sessions
ALTER TABLE channel_sessions ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_channel_sessions_tenant_id ON channel_sessions(tenant_id);

-- Add tenant_id to leads
ALTER TABLE leads ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_leads_tenant_id ON leads(tenant_id);

-- Add tenant_id to jobs
ALTER TABLE jobs ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_jobs_tenant_id ON jobs(tenant_id);
