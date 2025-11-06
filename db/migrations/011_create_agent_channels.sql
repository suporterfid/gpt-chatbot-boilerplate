-- Migration: Create agent_channels table
-- Description: Stores channel configurations for each agent (WhatsApp via Z-API, etc.)

CREATE TABLE IF NOT EXISTS agent_channels (
    id TEXT PRIMARY KEY,
    agent_id TEXT NOT NULL,
    channel TEXT NOT NULL CHECK(channel IN ('whatsapp')),
    enabled INTEGER NOT NULL DEFAULT 0,
    config_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE(agent_id, channel)
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_agent_channels_agent_id ON agent_channels(agent_id);
CREATE INDEX IF NOT EXISTS idx_agent_channels_channel ON agent_channels(channel);
CREATE INDEX IF NOT EXISTS idx_agent_channels_enabled ON agent_channels(enabled);
