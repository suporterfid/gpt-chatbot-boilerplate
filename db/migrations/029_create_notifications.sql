-- Migration: Create notifications table
-- Description: Store billing and quota notifications

CREATE TABLE IF NOT EXISTS notifications (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('quota_warning', 'quota_exceeded', 'payment_failed', 'payment_success', 'subscription_expiring', 'subscription_expired', 'trial_ending', 'invoice_due')),
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'sent', 'failed', 'read')),
    priority TEXT NOT NULL DEFAULT 'normal' CHECK(priority IN ('low', 'normal', 'high', 'urgent')),
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    metadata_json TEXT NULL,
    sent_at TEXT NULL,
    read_at TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_notifications_tenant_id ON notifications(tenant_id);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);
CREATE INDEX IF NOT EXISTS idx_notifications_status ON notifications(status);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_notifications_tenant_status ON notifications(tenant_id, status);
