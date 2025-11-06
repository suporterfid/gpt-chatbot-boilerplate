<?php
/**
 * Billing Service - Manage subscriptions, invoices, and payment methods
 * Core service for tenant billing operations
 */

require_once __DIR__ . '/DB.php';

class BillingService {
    private $db;
    
    // Plan types
    const PLAN_FREE = 'free';
    const PLAN_STARTER = 'starter';
    const PLAN_PROFESSIONAL = 'professional';
    const PLAN_ENTERPRISE = 'enterprise';
    const PLAN_CUSTOM = 'custom';
    
    // Billing cycles
    const CYCLE_MONTHLY = 'monthly';
    const CYCLE_QUARTERLY = 'quarterly';
    const CYCLE_YEARLY = 'yearly';
    const CYCLE_LIFETIME = 'lifetime';
    
    // Subscription statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_PAST_DUE = 'past_due';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_EXPIRED = 'expired';
    const STATUS_TRIAL = 'trial';
    
    // Invoice statuses
    const INVOICE_DRAFT = 'draft';
    const INVOICE_PENDING = 'pending';
    const INVOICE_PAID = 'paid';
    const INVOICE_OVERDUE = 'overdue';
    const INVOICE_CANCELLED = 'cancelled';
    const INVOICE_REFUNDED = 'refunded';
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create a subscription for a tenant
     */
    public function createSubscription($tenantId, $data) {
        if (empty($tenantId)) {
            throw new Exception('Tenant ID is required', 400);
        }
        
        // Check if tenant already has a subscription
        $existing = $this->getSubscription($tenantId);
        if ($existing) {
            throw new Exception('Tenant already has a subscription', 409);
        }
        
        $id = $this->generateUUID();
        $now = date('c');
        
        // Calculate period dates based on billing cycle
        $currentPeriodStart = $now;
        $currentPeriodEnd = $this->calculatePeriodEnd($now, $data['billing_cycle']);
        
        $sql = "INSERT INTO subscriptions (
            id, tenant_id, plan_type, billing_cycle, status,
            trial_ends_at, current_period_start, current_period_end,
            cancel_at_period_end, external_subscription_id,
            price_cents, currency, metadata_json, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $tenantId,
            $data['plan_type'],
            $data['billing_cycle'],
            $data['status'] ?? self::STATUS_ACTIVE,
            $data['trial_ends_at'] ?? null,
            $currentPeriodStart,
            $currentPeriodEnd,
            $data['cancel_at_period_end'] ?? 0,
            $data['external_subscription_id'] ?? null,
            $data['price_cents'] ?? 0,
            $data['currency'] ?? 'BRL',
            isset($data['metadata']) ? json_encode($data['metadata']) : null,
            $now,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getSubscription($tenantId);
    }
    
    /**
     * Get subscription for a tenant
     */
    public function getSubscription($tenantId) {
        $sql = "SELECT * FROM subscriptions WHERE tenant_id = ?";
        $subscription = $this->db->queryOne($sql, [$tenantId]);
        
        return $subscription ? $this->normalizeSubscription($subscription) : null;
    }
    
    /**
     * Get subscription by ID
     */
    public function getSubscriptionById($id) {
        $sql = "SELECT * FROM subscriptions WHERE id = ?";
        $subscription = $this->db->queryOne($sql, [$id]);
        
        return $subscription ? $this->normalizeSubscription($subscription) : null;
    }
    
    /**
     * Update subscription
     */
    public function updateSubscription($tenantId, $data) {
        $existing = $this->getSubscription($tenantId);
        if (!$existing) {
            throw new Exception('Subscription not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['plan_type'])) {
            $updates[] = 'plan_type = ?';
            $params[] = $data['plan_type'];
        }
        
        if (isset($data['billing_cycle'])) {
            $updates[] = 'billing_cycle = ?';
            $params[] = $data['billing_cycle'];
        }
        
        if (isset($data['status'])) {
            $updates[] = 'status = ?';
            $params[] = $data['status'];
        }
        
        if (array_key_exists('trial_ends_at', $data)) {
            $updates[] = 'trial_ends_at = ?';
            $params[] = $data['trial_ends_at'];
        }
        
        if (isset($data['current_period_start'])) {
            $updates[] = 'current_period_start = ?';
            $params[] = $data['current_period_start'];
        }
        
        if (isset($data['current_period_end'])) {
            $updates[] = 'current_period_end = ?';
            $params[] = $data['current_period_end'];
        }
        
        if (isset($data['cancel_at_period_end'])) {
            $updates[] = 'cancel_at_period_end = ?';
            $params[] = $data['cancel_at_period_end'] ? 1 : 0;
        }
        
        if (array_key_exists('external_subscription_id', $data)) {
            $updates[] = 'external_subscription_id = ?';
            $params[] = $data['external_subscription_id'];
        }
        
        if (isset($data['price_cents'])) {
            $updates[] = 'price_cents = ?';
            $params[] = $data['price_cents'];
        }
        
        if (isset($data['currency'])) {
            $updates[] = 'currency = ?';
            $params[] = $data['currency'];
        }
        
        if (array_key_exists('metadata', $data)) {
            $updates[] = 'metadata_json = ?';
            $params[] = isset($data['metadata']) ? json_encode($data['metadata']) : null;
        }
        
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        
        if (empty($updates)) {
            return $existing;
        }
        
        $params[] = $tenantId;
        
        $sql = "UPDATE subscriptions SET " . implode(', ', $updates) . " WHERE tenant_id = ?";
        $this->db->execute($sql, $params);
        
        return $this->getSubscription($tenantId);
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription($tenantId, $immediately = false) {
        if ($immediately) {
            return $this->updateSubscription($tenantId, [
                'status' => self::STATUS_CANCELLED,
                'cancel_at_period_end' => 0
            ]);
        } else {
            return $this->updateSubscription($tenantId, [
                'cancel_at_period_end' => 1
            ]);
        }
    }
    
    /**
     * Create an invoice
     */
    public function createInvoice($tenantId, $data) {
        $id = $this->generateUUID();
        $now = date('c');
        
        // Generate invoice number
        $invoiceNumber = $data['invoice_number'] ?? $this->generateInvoiceNumber();
        
        $sql = "INSERT INTO invoices (
            id, tenant_id, subscription_id, invoice_number,
            amount_cents, currency, status, due_date, paid_at,
            external_invoice_id, external_payment_id, payment_method,
            billing_details_json, line_items_json, notes,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $tenantId,
            $data['subscription_id'] ?? null,
            $invoiceNumber,
            $data['amount_cents'],
            $data['currency'] ?? 'BRL',
            $data['status'] ?? self::INVOICE_PENDING,
            $data['due_date'],
            $data['paid_at'] ?? null,
            $data['external_invoice_id'] ?? null,
            $data['external_payment_id'] ?? null,
            $data['payment_method'] ?? null,
            isset($data['billing_details']) ? json_encode($data['billing_details']) : null,
            isset($data['line_items']) ? json_encode($data['line_items']) : null,
            $data['notes'] ?? null,
            $now,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getInvoiceById($id);
    }
    
    /**
     * Get invoice by ID
     */
    public function getInvoiceById($id) {
        $sql = "SELECT * FROM invoices WHERE id = ?";
        $invoice = $this->db->queryOne($sql, [$id]);
        
        return $invoice ? $this->normalizeInvoice($invoice) : null;
    }
    
    /**
     * List invoices for a tenant
     */
    public function listInvoices($tenantId, $filters = []) {
        $params = [$tenantId];
        $conditions = ['tenant_id = ?'];
        
        if (!empty($filters['status'])) {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT * FROM invoices WHERE $whereClause ORDER BY created_at DESC";
        
        // Add pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$filters['limit'];
            $params[] = (int)($filters['offset'] ?? 0);
        }
        
        $invoices = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizeInvoice'], $invoices);
    }
    
    /**
     * Update invoice
     */
    public function updateInvoice($id, $data) {
        $existing = $this->getInvoiceById($id);
        if (!$existing) {
            throw new Exception('Invoice not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['status'])) {
            $updates[] = 'status = ?';
            $params[] = $data['status'];
            
            // If marking as paid, set paid_at timestamp
            if ($data['status'] === self::INVOICE_PAID && empty($existing['paid_at'])) {
                $updates[] = 'paid_at = ?';
                $params[] = date('c');
            }
        }
        
        if (array_key_exists('external_invoice_id', $data)) {
            $updates[] = 'external_invoice_id = ?';
            $params[] = $data['external_invoice_id'];
        }
        
        if (array_key_exists('external_payment_id', $data)) {
            $updates[] = 'external_payment_id = ?';
            $params[] = $data['external_payment_id'];
        }
        
        if (array_key_exists('payment_method', $data)) {
            $updates[] = 'payment_method = ?';
            $params[] = $data['payment_method'];
        }
        
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        
        if (empty($updates)) {
            return $existing;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE invoices SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $params);
        
        return $this->getInvoiceById($id);
    }
    
    /**
     * Calculate period end date based on billing cycle
     */
    private function calculatePeriodEnd($startDate, $billingCycle) {
        $date = new DateTime($startDate);
        
        switch ($billingCycle) {
            case self::CYCLE_MONTHLY:
                $date->modify('+1 month');
                break;
            case self::CYCLE_QUARTERLY:
                $date->modify('+3 months');
                break;
            case self::CYCLE_YEARLY:
                $date->modify('+1 year');
                break;
            case self::CYCLE_LIFETIME:
                $date->modify('+100 years');
                break;
            default:
                throw new Exception('Invalid billing cycle', 400);
        }
        
        return $date->format('c');
    }
    
    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber() {
        $prefix = 'INV';
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return "{$prefix}-{$timestamp}-{$random}";
    }
    
    /**
     * Normalize subscription data
     */
    private function normalizeSubscription($subscription) {
        if (isset($subscription['cancel_at_period_end'])) {
            $subscription['cancel_at_period_end'] = (bool)$subscription['cancel_at_period_end'];
        }
        
        if (isset($subscription['metadata_json'])) {
            $subscription['metadata'] = json_decode($subscription['metadata_json'], true);
            unset($subscription['metadata_json']);
        }
        
        return $subscription;
    }
    
    /**
     * Normalize invoice data
     */
    private function normalizeInvoice($invoice) {
        if (isset($invoice['billing_details_json'])) {
            $invoice['billing_details'] = json_decode($invoice['billing_details_json'], true);
            unset($invoice['billing_details_json']);
        }
        
        if (isset($invoice['line_items_json'])) {
            $invoice['line_items'] = json_decode($invoice['line_items_json'], true);
            unset($invoice['line_items_json']);
        }
        
        return $invoice;
    }
    
    /**
     * Generate UUID
     */
    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
