-- Migration: Add slug column to agents table
-- Description: Adds a unique slug field for agents to allow custom URL-friendly identifiers

-- Add slug column to agents table
ALTER TABLE agents ADD COLUMN slug TEXT NULL;

-- Create unique index on slug (only for non-null values)
CREATE UNIQUE INDEX IF NOT EXISTS idx_agents_slug ON agents(slug) WHERE slug IS NOT NULL;
