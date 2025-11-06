-- Migration: Create audit_artifacts table
-- Description: Stores retrieval context and tool artifacts for audit messages

CREATE TABLE IF NOT EXISTS audit_artifacts (
    id TEXT PRIMARY KEY,
    message_id TEXT NOT NULL,
    kind TEXT NOT NULL,
    data_json TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (message_id) REFERENCES audit_messages(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_audit_art_message_id ON audit_artifacts(message_id);
CREATE INDEX IF NOT EXISTS idx_audit_art_kind ON audit_artifacts(kind);
CREATE INDEX IF NOT EXISTS idx_audit_art_created_at ON audit_artifacts(created_at DESC);
