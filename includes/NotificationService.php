<?php
/**
 * Notification Service - Manage billing and quota notifications
 * Handles alerts for quota limits, payment failures, and billing events
 */

require_once __DIR__ . '/DB.php';

class NotificationService {
    private $db;
    
    // Notification types
    const TYPE_QUOTA_WARNING = 'quota_warning';
    const TYPE_QUOTA_EXCEEDED = 'quota_exceeded';
    const TYPE_PAYMENT_FAILED = 'payment_failed';
    const TYPE_PAYMENT_SUCCESS = 'payment_success';
    const TYPE_SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    const TYPE_SUBSCRIPTION_EXPIRED = 'subscription_expired';
    const TYPE_TRIAL_ENDING = 'trial_ending';
    const TYPE_INVOICE_DUE = 'invoice_due';
    
    // Statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_READ = 'read';
    
    // Priorities
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create a notification
     */
    public function createNotification($tenantId, $type, $subject, $message, $options = []) {
        if (empty($tenantId)) {
            throw new Exception('Tenant ID is required', 400);
        }
        
        $id = $this->generateUUID();
        $now = date('c');
        
        $sql = "INSERT INTO notifications (
            id, tenant_id, type, status, priority, subject, message,
            metadata_json, sent_at, read_at, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $tenantId,
            $type,
            $options['status'] ?? self::STATUS_PENDING,
            $options['priority'] ?? self::PRIORITY_NORMAL,
            $subject,
            $message,
            isset($options['metadata']) ? json_encode($options['metadata']) : null,
            $options['sent_at'] ?? null,
            $options['read_at'] ?? null,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getNotificationById($id);
    }
    
    /**
     * Get notification by ID
     */
    public function getNotificationById($id) {
        $sql = "SELECT * FROM notifications WHERE id = ?";
        $notification = $this->db->queryOne($sql, [$id]);
        
        return $notification ? $this->normalizeNotification($notification) : null;
    }
    
    /**
     * List notifications for a tenant
     */
    public function listNotifications($tenantId, $filters = []) {
        $params = [$tenantId];
        $conditions = ['tenant_id = ?'];
        
        if (!empty($filters['type'])) {
            $conditions[] = 'type = ?';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $conditions[] = 'priority = ?';
            $params[] = $filters['priority'];
        }
        
        if (isset($filters['unread_only']) && $filters['unread_only']) {
            $conditions[] = 'read_at IS NULL';
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT * FROM notifications WHERE $whereClause ORDER BY created_at DESC";
        
        // Add pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$filters['limit'];
            $params[] = (int)($filters['offset'] ?? 0);
        }
        
        $notifications = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizeNotification'], $notifications);
    }
    
    /**
     * Mark notification as sent
     */
    public function markAsSent($id) {
        $sql = "UPDATE notifications SET status = ?, sent_at = ? WHERE id = ?";
        $this->db->execute($sql, [self::STATUS_SENT, date('c'), $id]);
        
        return $this->getNotificationById($id);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($id) {
        $sql = "UPDATE notifications SET status = ?, read_at = ? WHERE id = ?";
        $this->db->execute($sql, [self::STATUS_READ, date('c'), $id]);
        
        return $this->getNotificationById($id);
    }
    
    /**
     * Mark notification as failed
     */
    public function markAsFailed($id) {
        $sql = "UPDATE notifications SET status = ? WHERE id = ?";
        $this->db->execute($sql, [self::STATUS_FAILED, $id]);
        
        return $this->getNotificationById($id);
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($id) {
        $sql = "DELETE FROM notifications WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return true;
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($tenantId) {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE tenant_id = ? AND read_at IS NULL";
        $result = $this->db->queryOne($sql, [$tenantId]);
        
        return (int)$result['count'];
    }
    
    /**
     * Send quota warning notification
     */
    public function sendQuotaWarning($tenantId, $resourceType, $current, $limit, $percentage) {
        $subject = "Quota Warning: {$resourceType}";
        $message = "Your {$resourceType} usage is at {$percentage}% ({$current}/{$limit}). Consider upgrading your plan.";
        
        return $this->createNotification($tenantId, self::TYPE_QUOTA_WARNING, $subject, $message, [
            'priority' => self::PRIORITY_HIGH,
            'metadata' => [
                'resource_type' => $resourceType,
                'current' => $current,
                'limit' => $limit,
                'percentage' => $percentage
            ]
        ]);
    }
    
    /**
     * Send quota exceeded notification
     */
    public function sendQuotaExceeded($tenantId, $resourceType, $current, $limit) {
        $subject = "Quota Exceeded: {$resourceType}";
        $message = "Your {$resourceType} quota has been exceeded ({$current}/{$limit}). Please upgrade your plan to continue.";
        
        return $this->createNotification($tenantId, self::TYPE_QUOTA_EXCEEDED, $subject, $message, [
            'priority' => self::PRIORITY_URGENT,
            'metadata' => [
                'resource_type' => $resourceType,
                'current' => $current,
                'limit' => $limit
            ]
        ]);
    }
    
    /**
     * Send payment failed notification
     */
    public function sendPaymentFailed($tenantId, $invoiceId, $amount) {
        $subject = "Payment Failed";
        $message = "Your payment of {$amount} failed. Please update your payment method.";
        
        return $this->createNotification($tenantId, self::TYPE_PAYMENT_FAILED, $subject, $message, [
            'priority' => self::PRIORITY_URGENT,
            'metadata' => [
                'invoice_id' => $invoiceId,
                'amount' => $amount
            ]
        ]);
    }
    
    /**
     * Send payment success notification
     */
    public function sendPaymentSuccess($tenantId, $invoiceId, $amount) {
        $subject = "Payment Successful";
        $message = "Your payment of {$amount} was processed successfully.";
        
        return $this->createNotification($tenantId, self::TYPE_PAYMENT_SUCCESS, $subject, $message, [
            'priority' => self::PRIORITY_NORMAL,
            'metadata' => [
                'invoice_id' => $invoiceId,
                'amount' => $amount
            ]
        ]);
    }
    
    /**
     * Send subscription expiring notification
     */
    public function sendSubscriptionExpiring($tenantId, $subscriptionId, $expiresAt) {
        $subject = "Subscription Expiring Soon";
        $message = "Your subscription will expire on {$expiresAt}. Please renew to continue service.";
        
        return $this->createNotification($tenantId, self::TYPE_SUBSCRIPTION_EXPIRING, $subject, $message, [
            'priority' => self::PRIORITY_HIGH,
            'metadata' => [
                'subscription_id' => $subscriptionId,
                'expires_at' => $expiresAt
            ]
        ]);
    }
    
    /**
     * Batch mark notifications as read
     */
    public function markAllAsRead($tenantId) {
        $sql = "UPDATE notifications SET status = ?, read_at = ? WHERE tenant_id = ? AND read_at IS NULL";
        $this->db->execute($sql, [self::STATUS_READ, date('c'), $tenantId]);
        
        return true;
    }
    
    /**
     * Normalize notification data
     */
    private function normalizeNotification($notification) {
        if (isset($notification['metadata_json'])) {
            $notification['metadata'] = json_decode($notification['metadata_json'], true);
            unset($notification['metadata_json']);
        }
        
        return $notification;
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
