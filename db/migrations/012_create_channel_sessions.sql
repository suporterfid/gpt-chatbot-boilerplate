-- Migration: Create channel_sessions table
-- Description: Stores channel session mappings (user identity to conversation)

CREATE TABLE IF NOT EXISTS channel_sessions (
    id TEXT PRIMARY KEY,
    agent_id TEXT NOT NULL,
    channel TEXT NOT NULL CHECK(channel IN ('whatsapp')),
    external_user_id TEXT NOT NULL,
    conversation_id TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE(agent_id, channel, external_user_id)
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_channel_sessions_agent_id ON channel_sessions(agent_id);
CREATE INDEX IF NOT EXISTS idx_channel_sessions_channel ON channel_sessions(channel);
CREATE INDEX IF NOT EXISTS idx_channel_sessions_conversation_id ON channel_sessions(conversation_id);
CREATE INDEX IF NOT EXISTS idx_channel_sessions_external_user_id ON channel_sessions(external_user_id);
CREATE INDEX IF NOT EXISTS idx_channel_sessions_last_seen_at ON channel_sessions(last_seen_at);
