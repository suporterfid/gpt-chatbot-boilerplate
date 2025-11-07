-- Migration: Create resource_permissions table
-- Description: Per-resource access control lists for fine-grained authorization

CREATE TABLE IF NOT EXISTS resource_permissions (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    resource_type TEXT NOT NULL CHECK(resource_type IN (
        'agent', 'prompt', 'vector_store', 'conversation', 'file', 'webhook', 'job', 'lead'
    )),
    resource_id TEXT NOT NULL,
    permissions_json TEXT NOT NULL, -- JSON array of permissions: ["read", "update", "delete", "execute"]
    granted_by TEXT NOT NULL, -- User ID who granted this permission
    is_active INTEGER DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_resource_permissions_user_id ON resource_permissions(user_id);
CREATE INDEX IF NOT EXISTS idx_resource_permissions_resource ON resource_permissions(resource_type, resource_id);
CREATE INDEX IF NOT EXISTS idx_resource_permissions_active ON resource_permissions(is_active);

-- Composite index for fast lookups
CREATE INDEX IF NOT EXISTS idx_resource_permissions_lookup 
    ON resource_permissions(user_id, resource_type, resource_id, is_active);
