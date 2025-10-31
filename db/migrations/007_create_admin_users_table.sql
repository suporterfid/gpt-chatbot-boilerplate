-- Admin users table for RBAC
CREATE TABLE IF NOT EXISTS admin_users (
    id TEXT PRIMARY KEY,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT,
    role TEXT DEFAULT 'admin' CHECK(role IN ('viewer', 'admin', 'super-admin')),
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Index for email lookups
CREATE INDEX IF NOT EXISTS idx_admin_users_email ON admin_users(email);

-- Index for role
CREATE INDEX IF NOT EXISTS idx_admin_users_role ON admin_users(role);

-- Index for active users
CREATE INDEX IF NOT EXISTS idx_admin_users_active ON admin_users(is_active);
