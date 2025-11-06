-- Migration: Allow NULL conversation_id in audit_events for system events
-- Description: Modify audit_events to support system-level events not tied to conversations

-- SQLite doesn't support ALTER COLUMN, so we need to recreate the table
-- Step 1: Rename existing table
ALTER TABLE audit_events RENAME TO audit_events_old;

-- Step 2: Create new table with NULL-able conversation_id
CREATE TABLE IF NOT EXISTS audit_events (
    id TEXT PRIMARY KEY,
    conversation_id TEXT NULL,  -- Changed to NULL to support system events
    message_id TEXT NULL,
    type TEXT NOT NULL,
    payload_json TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (conversation_id) REFERENCES audit_conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES audit_messages(id) ON DELETE CASCADE
);

-- Step 3: Copy data from old table
INSERT INTO audit_events (id, conversation_id, message_id, type, payload_json, created_at)
SELECT id, conversation_id, message_id, type, payload_json, created_at
FROM audit_events_old;

-- Step 4: Drop old table
DROP TABLE audit_events_old;

-- Step 5: Recreate indexes
CREATE INDEX IF NOT EXISTS idx_audit_evt_conversation_id ON audit_events(conversation_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_evt_type ON audit_events(type, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_evt_message_id ON audit_events(message_id);
