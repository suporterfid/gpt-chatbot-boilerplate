-- Migration: Create webhook_logs table
-- Description: Creates the webhook_logs table for recording webhook delivery attempts
-- Reference: docs/SPEC_WEBHOOK.md §8

CREATE TABLE IF NOT EXISTS webhook_logs (
    id TEXT PRIMARY KEY,
    subscriber_id TEXT NOT NULL,
    event TEXT NOT NULL,
    request_body TEXT NOT NULL,
    response_code INTEGER,
    response_body TEXT,
    attempts INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (subscriber_id) REFERENCES webhook_subscribers(id)
);

-- Create indexes for performance and common queries
CREATE INDEX IF NOT EXISTS idx_webhook_logs_subscriber_id ON webhook_logs(subscriber_id);
CREATE INDEX IF NOT EXISTS idx_webhook_logs_event ON webhook_logs(event);
CREATE INDEX IF NOT EXISTS idx_webhook_logs_created_at ON webhook_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_webhook_logs_response_code ON webhook_logs(response_code);
