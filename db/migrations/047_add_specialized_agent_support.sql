-- Migration: Add Specialized Agent Support
-- Description: Adds specialized agent type system with configuration management
-- Version: 1.0
-- Date: 2025-11-20

BEGIN TRANSACTION;

-- ============================================================
-- 1. Add agent_type column to existing agents table
-- ============================================================

-- Add agent_type column with default value for backward compatibility
ALTER TABLE agents ADD COLUMN agent_type TEXT DEFAULT 'generic' NOT NULL;

-- Create index for agent type lookups
CREATE INDEX IF NOT EXISTS idx_agents_agent_type ON agents(agent_type);

-- Set all existing agents to 'generic' type (redundant but explicit)
UPDATE agents SET agent_type = 'generic' WHERE agent_type IS NULL OR agent_type = '';

-- ============================================================
-- 2. Create specialized_agent_configs table
-- ============================================================

-- Stores agent-specific configurations for specialized agents
CREATE TABLE IF NOT EXISTS specialized_agent_configs (
    id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    agent_id TEXT NOT NULL,
    agent_type TEXT NOT NULL,
    config_json TEXT,  -- Agent-specific configuration (JSON)
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    UNIQUE(agent_id)
);

-- Index for quick lookups by agent_id
CREATE INDEX IF NOT EXISTS idx_specialized_configs_agent_id
    ON specialized_agent_configs(agent_id);

-- Index for lookups by agent type
CREATE INDEX IF NOT EXISTS idx_specialized_configs_agent_type
    ON specialized_agent_configs(agent_type);

-- ============================================================
-- 3. Create agent_type_metadata table
-- ============================================================

-- Stores metadata about available agent types (auto-populated by registry)
CREATE TABLE IF NOT EXISTS agent_type_metadata (
    agent_type TEXT PRIMARY KEY,
    display_name TEXT NOT NULL,
    description TEXT,
    version TEXT,
    enabled INTEGER DEFAULT 1,  -- SQLite uses INTEGER for boolean
    config_schema_json TEXT,  -- JSON Schema for configuration validation
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Index for enabled agent types
CREATE INDEX IF NOT EXISTS idx_agent_type_metadata_enabled
    ON agent_type_metadata(enabled);

-- ============================================================
-- 4. Insert default 'generic' agent type metadata
-- ============================================================

INSERT OR IGNORE INTO agent_type_metadata (
    agent_type,
    display_name,
    description,
    version,
    enabled,
    config_schema_json
) VALUES (
    'generic',
    'Generic Agent',
    'Standard agent using default ChatHandler processing pipeline',
    '1.0.0',
    1,
    '{
        "type": "object",
        "properties": {},
        "additionalProperties": false
    }'
);

-- ============================================================
-- 5. Create trigger to update updated_at timestamp
-- ============================================================

-- Trigger for specialized_agent_configs
CREATE TRIGGER IF NOT EXISTS update_specialized_agent_configs_timestamp
AFTER UPDATE ON specialized_agent_configs
BEGIN
    UPDATE specialized_agent_configs
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.id;
END;

-- Trigger for agent_type_metadata
CREATE TRIGGER IF NOT EXISTS update_agent_type_metadata_timestamp
AFTER UPDATE ON agent_type_metadata
BEGIN
    UPDATE agent_type_metadata
    SET updated_at = CURRENT_TIMESTAMP
    WHERE agent_type = NEW.agent_type;
END;

COMMIT;

-- ============================================================
-- Rollback Instructions (for reference)
-- ============================================================
-- To rollback this migration, execute the following:
--
-- BEGIN TRANSACTION;
--
-- -- Drop triggers
-- DROP TRIGGER IF EXISTS update_specialized_agent_configs_timestamp;
-- DROP TRIGGER IF EXISTS update_agent_type_metadata_timestamp;
--
-- -- Drop new tables
-- DROP TABLE IF EXISTS specialized_agent_configs;
-- DROP TABLE IF EXISTS agent_type_metadata;
--
-- -- Remove agent_type column (SQLite limitation: requires table recreation)
-- -- This is complex in SQLite, so ensure you have a backup before migration
--
-- COMMIT;
