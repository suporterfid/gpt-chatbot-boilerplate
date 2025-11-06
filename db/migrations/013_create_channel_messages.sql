-- Migration: Create channel_messages table
-- Description: Stores channel messages for audit, idempotency, and debugging

CREATE TABLE IF NOT EXISTS channel_messages (
    id TEXT PRIMARY KEY,
    agent_id TEXT NOT NULL,
    channel TEXT NOT NULL CHECK(channel IN ('whatsapp')),
    direction TEXT NOT NULL CHECK(direction IN ('inbound','outbound')),
    external_message_id TEXT NULL,
    external_user_id TEXT NOT NULL,
    conversation_id TEXT NOT NULL,
    payload_json TEXT NULL,
    status TEXT NOT NULL DEFAULT 'received' CHECK(status IN ('received','processed','sent','failed')),
    error_text TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Create indexes for performance and uniqueness
CREATE UNIQUE INDEX IF NOT EXISTS idx_channel_messages_external_id ON channel_messages(external_message_id) WHERE external_message_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_channel_messages_agent_id ON channel_messages(agent_id);
CREATE INDEX IF NOT EXISTS idx_channel_messages_channel ON channel_messages(channel);
CREATE INDEX IF NOT EXISTS idx_channel_messages_conversation_id ON channel_messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_channel_messages_status ON channel_messages(status);
CREATE INDEX IF NOT EXISTS idx_channel_messages_created_at ON channel_messages(created_at);
