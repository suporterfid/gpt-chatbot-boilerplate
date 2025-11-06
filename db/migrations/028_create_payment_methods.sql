-- Migration: Create payment_methods table
-- Description: Store tenant payment method information

CREATE TABLE IF NOT EXISTS payment_methods (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('credit_card', 'boleto', 'pix', 'bank_transfer', 'other')),
    is_default INTEGER NOT NULL DEFAULT 0,
    external_method_id TEXT NULL,
    card_last4 TEXT NULL,
    card_brand TEXT NULL,
    expires_at TEXT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active', 'expired', 'deleted')),
    metadata_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_payment_methods_tenant_id ON payment_methods(tenant_id);
CREATE INDEX IF NOT EXISTS idx_payment_methods_status ON payment_methods(status);
CREATE INDEX IF NOT EXISTS idx_payment_methods_is_default ON payment_methods(is_default);
CREATE INDEX IF NOT EXISTS idx_payment_methods_external_id ON payment_methods(external_method_id);
