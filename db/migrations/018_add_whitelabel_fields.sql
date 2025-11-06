-- Migration: Add whitelabel fields to agents table
-- Description: Adds fields for whitelabel agent publishing with strict scoping

-- Add whitelabel publishing fields
ALTER TABLE agents ADD COLUMN whitelabel_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE agents ADD COLUMN agent_public_id TEXT NULL;
ALTER TABLE agents ADD COLUMN vanity_path TEXT NULL;
ALTER TABLE agents ADD COLUMN custom_domain TEXT NULL;

-- Add whitelabel security fields
ALTER TABLE agents ADD COLUMN wl_require_signed_requests INTEGER NOT NULL DEFAULT 1;
ALTER TABLE agents ADD COLUMN wl_hmac_secret TEXT NULL;
ALTER TABLE agents ADD COLUMN wl_token_ttl_seconds INTEGER NOT NULL DEFAULT 600;
ALTER TABLE agents ADD COLUMN allowed_origins_json TEXT NULL;

-- Add whitelabel branding fields
ALTER TABLE agents ADD COLUMN wl_title TEXT NULL;
ALTER TABLE agents ADD COLUMN wl_logo_url TEXT NULL;
ALTER TABLE agents ADD COLUMN wl_theme_json TEXT NULL;
ALTER TABLE agents ADD COLUMN wl_welcome_message TEXT NULL;
ALTER TABLE agents ADD COLUMN wl_placeholder TEXT NULL;
ALTER TABLE agents ADD COLUMN wl_enable_file_upload INTEGER NOT NULL DEFAULT 0;
ALTER TABLE agents ADD COLUMN wl_legal_disclaimer_md TEXT NULL;
ALTER TABLE agents ADD COLUMN wl_footer_brand_md TEXT NULL;

-- Add whitelabel rate limiting fields
ALTER TABLE agents ADD COLUMN wl_rate_limit_requests INTEGER NULL;
ALTER TABLE agents ADD COLUMN wl_rate_limit_window_seconds INTEGER NULL;

-- Create unique indexes for whitelabel identifiers
CREATE UNIQUE INDEX IF NOT EXISTS idx_agents_public_id ON agents(agent_public_id) WHERE agent_public_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_agents_vanity_path ON agents(vanity_path) WHERE vanity_path IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_agents_custom_domain ON agents(custom_domain) WHERE custom_domain IS NOT NULL;

-- Create index for whitelabel enabled agents
CREATE INDEX IF NOT EXISTS idx_agents_whitelabel_enabled ON agents(whitelabel_enabled);
