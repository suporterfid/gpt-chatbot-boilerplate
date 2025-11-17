-- Migration: Create webhook_subscribers table
-- Description: Creates the webhook_subscribers table for storing webhook subscriber configurations
-- Reference: docs/SPEC_WEBHOOK.md §8

CREATE TABLE IF NOT EXISTS webhook_subscribers (
    id TEXT PRIMARY KEY,
    client_id TEXT NOT NULL,
    url TEXT NOT NULL,
    secret TEXT NOT NULL,
    events TEXT NOT NULL, -- JSON string array of event types
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_webhook_subscribers_client_id ON webhook_subscribers(client_id);
CREATE INDEX IF NOT EXISTS idx_webhook_subscribers_active ON webhook_subscribers(active);
CREATE INDEX IF NOT EXISTS idx_webhook_subscribers_created_at ON webhook_subscribers(created_at);
