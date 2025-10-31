-- Migration: Create agents table
-- Description: Creates the agents table for storing AI agent configurations

CREATE TABLE IF NOT EXISTS agents (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    description TEXT NULL,
    api_type TEXT NOT NULL DEFAULT 'responses' CHECK(api_type IN ('responses','chat')),
    prompt_id TEXT NULL,
    prompt_version TEXT NULL,
    system_message TEXT NULL,
    model TEXT NULL,
    temperature REAL NULL,
    top_p REAL NULL,
    max_output_tokens INTEGER NULL,
    tools_json TEXT NULL,
    vector_store_ids_json TEXT NULL,
    max_num_results INTEGER NULL,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_agents_name ON agents(name);
CREATE INDEX IF NOT EXISTS idx_agents_is_default ON agents(is_default);
CREATE INDEX IF NOT EXISTS idx_agents_created_at ON agents(created_at);
