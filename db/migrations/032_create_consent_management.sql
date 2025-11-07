-- Migration: Create consent management tables
-- Description: Tracks user consent for GDPR/LGPD compliance in WhatsApp channels

-- Consent records table
CREATE TABLE IF NOT EXISTS user_consents (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NULL,
    agent_id TEXT NOT NULL,
    channel TEXT NOT NULL,
    external_user_id TEXT NOT NULL,
    consent_type TEXT NOT NULL CHECK(consent_type IN ('marketing', 'service', 'analytics', 'all')),
    consent_status TEXT NOT NULL CHECK(consent_status IN ('granted', 'denied', 'pending', 'withdrawn')),
    consent_method TEXT NOT NULL CHECK(consent_method IN ('explicit_opt_in', 'implicit', 'first_contact', 'web_form', 'voice', 'other')),
    consent_text TEXT NULL,
    consent_language TEXT NOT NULL DEFAULT 'en',
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    granted_at TEXT NULL,
    withdrawn_at TEXT NULL,
    expires_at TEXT NULL,
    legal_basis TEXT NULL,
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Create indexes for consent lookups
CREATE INDEX IF NOT EXISTS idx_user_consents_tenant_id ON user_consents(tenant_id);
CREATE INDEX IF NOT EXISTS idx_user_consents_agent_id ON user_consents(agent_id);
CREATE INDEX IF NOT EXISTS idx_user_consents_external_user_id ON user_consents(external_user_id);
CREATE INDEX IF NOT EXISTS idx_user_consents_channel ON user_consents(channel);
CREATE INDEX IF NOT EXISTS idx_user_consents_status ON user_consents(consent_status);
CREATE INDEX IF NOT EXISTS idx_user_consents_type ON user_consents(consent_type);
CREATE UNIQUE INDEX IF NOT EXISTS idx_user_consents_unique ON user_consents(agent_id, channel, external_user_id, consent_type);

-- Consent audit log
CREATE TABLE IF NOT EXISTS consent_audit_log (
    id TEXT PRIMARY KEY,
    consent_id TEXT NOT NULL,
    action TEXT NOT NULL CHECK(action IN ('granted', 'denied', 'withdrawn', 'renewed', 'expired', 'modified')),
    previous_status TEXT NULL,
    new_status TEXT NOT NULL,
    reason TEXT NULL,
    triggered_by TEXT NULL CHECK(triggered_by IN ('user', 'system', 'admin', 'automated')),
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (consent_id) REFERENCES user_consents(id) ON DELETE CASCADE
);

-- Create indexes for audit trail
CREATE INDEX IF NOT EXISTS idx_consent_audit_log_consent_id ON consent_audit_log(consent_id);
CREATE INDEX IF NOT EXISTS idx_consent_audit_log_action ON consent_audit_log(action);
CREATE INDEX IF NOT EXISTS idx_consent_audit_log_created_at ON consent_audit_log(created_at);
