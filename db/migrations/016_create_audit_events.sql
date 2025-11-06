-- Migration: Create audit_events table
-- Description: Stores event-level audit data for tracking request lifecycle

CREATE TABLE IF NOT EXISTS audit_events (
    id TEXT PRIMARY KEY,
    conversation_id TEXT NOT NULL,
    message_id TEXT NULL,
    type TEXT NOT NULL,
    payload_json TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (conversation_id) REFERENCES audit_conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES audit_messages(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_audit_evt_conversation_id ON audit_events(conversation_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_evt_type ON audit_events(type, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_evt_message_id ON audit_events(message_id);
