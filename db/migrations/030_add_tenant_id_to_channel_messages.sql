-- Migration: Add tenant_id to channel_messages
-- Description: Adds tenant_id column to channel_messages for explicit tenant isolation

-- Add tenant_id to channel_messages
ALTER TABLE channel_messages ADD COLUMN tenant_id TEXT NULL REFERENCES tenants(id) ON DELETE CASCADE;

-- Create index for tenant filtering
CREATE INDEX IF NOT EXISTS idx_channel_messages_tenant_id ON channel_messages(tenant_id);
