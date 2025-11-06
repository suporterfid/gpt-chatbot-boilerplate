-- Migration: Create audit_conversations table
-- Description: Stores conversation-level audit data for all agent interactions

CREATE TABLE IF NOT EXISTS audit_conversations (
    id TEXT PRIMARY KEY,
    agent_id TEXT NULL,
    channel TEXT NOT NULL DEFAULT 'web',
    conversation_id TEXT NOT NULL UNIQUE,
    user_fingerprint TEXT NULL,
    started_at TEXT NOT NULL,
    last_activity_at TEXT NOT NULL,
    meta_json TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_audit_conv_agent_id ON audit_conversations(agent_id);
CREATE INDEX IF NOT EXISTS idx_audit_conv_channel ON audit_conversations(channel, last_activity_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_conv_conversation_id ON audit_conversations(conversation_id);
CREATE INDEX IF NOT EXISTS idx_audit_conv_started_at ON audit_conversations(started_at DESC);
