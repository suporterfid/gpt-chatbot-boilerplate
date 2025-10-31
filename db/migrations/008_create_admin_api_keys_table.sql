-- Admin API keys table for token-based authentication
CREATE TABLE IF NOT EXISTS admin_api_keys (
    id TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    key_hash TEXT NOT NULL,
    key_prefix TEXT NOT NULL,
    name TEXT,
    last_used_at DATETIME,
    expires_at DATETIME,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);

-- Index for key_hash lookups
CREATE INDEX IF NOT EXISTS idx_admin_api_keys_hash ON admin_api_keys(key_hash);

-- Index for user_id
CREATE INDEX IF NOT EXISTS idx_admin_api_keys_user ON admin_api_keys(user_id);

-- Index for active keys
CREATE INDEX IF NOT EXISTS idx_admin_api_keys_active ON admin_api_keys(is_active);

-- Index for key_prefix (for display purposes)
CREATE INDEX IF NOT EXISTS idx_admin_api_keys_prefix ON admin_api_keys(key_prefix);
