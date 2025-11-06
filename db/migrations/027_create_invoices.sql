-- Migration: Create invoices table
-- Description: Track billing invoices and payment status

CREATE TABLE IF NOT EXISTS invoices (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    subscription_id TEXT NULL,
    invoice_number TEXT NOT NULL UNIQUE,
    amount_cents INTEGER NOT NULL,
    currency TEXT NOT NULL DEFAULT 'BRL',
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('draft', 'pending', 'paid', 'overdue', 'cancelled', 'refunded')),
    due_date TEXT NOT NULL,
    paid_at TEXT NULL,
    external_invoice_id TEXT NULL,
    external_payment_id TEXT NULL,
    payment_method TEXT NULL,
    billing_details_json TEXT NULL,
    line_items_json TEXT NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_invoices_tenant_id ON invoices(tenant_id);
CREATE INDEX IF NOT EXISTS idx_invoices_subscription_id ON invoices(subscription_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON invoices(due_date);
CREATE INDEX IF NOT EXISTS idx_invoices_external_id ON invoices(external_invoice_id);
CREATE INDEX IF NOT EXISTS idx_invoices_created_at ON invoices(created_at);
