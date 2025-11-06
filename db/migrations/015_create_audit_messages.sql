-- Migration: Create audit_messages table
-- Description: Stores message-level audit data with encryption and metadata

CREATE TABLE IF NOT EXISTS audit_messages (
    id TEXT PRIMARY KEY,
    conversation_id TEXT NOT NULL,
    sequence INTEGER NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('system', 'user', 'assistant', 'tool')),
    content_enc TEXT NULL,
    content_hash TEXT NULL,
    attachments_json TEXT NULL,
    request_meta_json TEXT NULL,
    response_meta_json TEXT NULL,
    risk_scores_json TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (conversation_id) REFERENCES audit_conversations(conversation_id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_audit_msg_conversation_id ON audit_messages(conversation_id, sequence);
CREATE INDEX IF NOT EXISTS idx_audit_msg_created_at ON audit_messages(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_msg_role ON audit_messages(role);
