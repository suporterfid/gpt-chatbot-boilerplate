-- Migration: Create whitelabel_tokens table for nonce replay protection
-- Description: Stores used nonces to prevent replay attacks

CREATE TABLE IF NOT EXISTS whitelabel_tokens (
    nonce TEXT PRIMARY KEY,
    agent_public_id TEXT NOT NULL,
    used_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    client_ip TEXT NULL,
    created_at TEXT NOT NULL
);

-- Index for cleanup of expired tokens
CREATE INDEX IF NOT EXISTS idx_whitelabel_tokens_expires ON whitelabel_tokens(expires_at);

-- Index for agent lookups
CREATE INDEX IF NOT EXISTS idx_whitelabel_tokens_agent ON whitelabel_tokens(agent_public_id);
