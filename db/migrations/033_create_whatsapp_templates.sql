-- Migration: Create WhatsApp template management tables
-- Description: Manages WhatsApp Business message templates and their approvals

-- WhatsApp templates table
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NULL,
    agent_id TEXT NULL,
    template_name TEXT NOT NULL,
    template_category TEXT NOT NULL CHECK(template_category IN ('MARKETING', 'UTILITY', 'AUTHENTICATION', 'SERVICE')),
    language_code TEXT NOT NULL,
    whatsapp_template_id TEXT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft', 'pending', 'approved', 'rejected', 'paused', 'disabled')),
    content_text TEXT NOT NULL,
    header_type TEXT NULL CHECK(header_type IN ('TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'NONE')),
    header_text TEXT NULL,
    header_media_url TEXT NULL,
    footer_text TEXT NULL,
    buttons_json TEXT NULL,
    variables_json TEXT NULL,
    rejection_reason TEXT NULL,
    quality_score TEXT NULL CHECK(quality_score IN ('HIGH', 'MEDIUM', 'LOW', 'PENDING')),
    usage_count INTEGER DEFAULT 0,
    last_used_at TEXT NULL,
    submitted_at TEXT NULL,
    approved_at TEXT NULL,
    rejected_at TEXT NULL,
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
);

-- Create indexes for template lookups
CREATE INDEX IF NOT EXISTS idx_whatsapp_templates_tenant_id ON whatsapp_templates(tenant_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_templates_agent_id ON whatsapp_templates(agent_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_templates_status ON whatsapp_templates(status);
CREATE INDEX IF NOT EXISTS idx_whatsapp_templates_category ON whatsapp_templates(template_category);
CREATE INDEX IF NOT EXISTS idx_whatsapp_templates_language ON whatsapp_templates(language_code);
CREATE UNIQUE INDEX IF NOT EXISTS idx_whatsapp_templates_name_lang ON whatsapp_templates(tenant_id, template_name, language_code) WHERE tenant_id IS NOT NULL;

-- Template usage log
CREATE TABLE IF NOT EXISTS whatsapp_template_usage (
    id TEXT PRIMARY KEY,
    template_id TEXT NOT NULL,
    agent_id TEXT NOT NULL,
    channel TEXT NOT NULL DEFAULT 'whatsapp',
    external_user_id TEXT NOT NULL,
    conversation_id TEXT NULL,
    variables_json TEXT NULL,
    delivery_status TEXT NULL CHECK(delivery_status IN ('sent', 'delivered', 'read', 'failed')),
    error_code TEXT NULL,
    error_message TEXT NULL,
    sent_at TEXT NOT NULL,
    delivered_at TEXT NULL,
    read_at TEXT NULL,
    metadata_json TEXT NULL,
    FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Create indexes for usage tracking
CREATE INDEX IF NOT EXISTS idx_whatsapp_template_usage_template_id ON whatsapp_template_usage(template_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_template_usage_agent_id ON whatsapp_template_usage(agent_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_template_usage_user ON whatsapp_template_usage(external_user_id);
CREATE INDEX IF NOT EXISTS idx_whatsapp_template_usage_sent_at ON whatsapp_template_usage(sent_at);
CREATE INDEX IF NOT EXISTS idx_whatsapp_template_usage_status ON whatsapp_template_usage(delivery_status);
