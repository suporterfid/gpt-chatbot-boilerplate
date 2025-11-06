<?php
/**
 * Quota Service - Manage and enforce usage quotas per tenant
 * Provides quota checking, enforcement, and notifications
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/UsageTrackingService.php';

class QuotaService {
    private $db;
    private $usageTrackingService;
    
    // Period types
    const PERIOD_HOURLY = 'hourly';
    const PERIOD_DAILY = 'daily';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_TOTAL = 'total';
    
    public function __construct($db, $usageTrackingService = null) {
        $this->db = $db;
        $this->usageTrackingService = $usageTrackingService ?? new UsageTrackingService($db);
    }
    
    /**
     * Create or update a quota
     */
    public function setQuota($tenantId, $resourceType, $limitValue, $period, $options = []) {
        if (empty($tenantId)) {
            throw new Exception('Tenant ID is required', 400);
        }
        
        $validPeriods = [self::PERIOD_HOURLY, self::PERIOD_DAILY, self::PERIOD_MONTHLY, self::PERIOD_TOTAL];
        if (!in_array($period, $validPeriods)) {
            throw new Exception('Invalid period', 400);
        }
        
        // Check if quota exists
        $existing = $this->getQuota($tenantId, $resourceType, $period);
        
        if ($existing) {
            // Update existing quota
            return $this->updateQuota($existing['id'], [
                'limit_value' => $limitValue,
                'is_hard_limit' => $options['is_hard_limit'] ?? $existing['is_hard_limit'],
                'notification_threshold' => $options['notification_threshold'] ?? $existing['notification_threshold']
            ]);
        }
        
        // Create new quota
        $id = $this->generateUUID();
        $now = date('c');
        
        $sql = "INSERT INTO quotas (
            id, tenant_id, resource_type, limit_value, period, 
            is_hard_limit, notification_threshold, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $tenantId,
            $resourceType,
            $limitValue,
            $period,
            $options['is_hard_limit'] ?? 0,
            $options['notification_threshold'] ?? null,
            $now,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getQuotaById($id);
    }
    
    /**
     * Get a specific quota
     */
    public function getQuota($tenantId, $resourceType, $period) {
        $sql = "SELECT * FROM quotas WHERE tenant_id = ? AND resource_type = ? AND period = ?";
        $quota = $this->db->queryOne($sql, [$tenantId, $resourceType, $period]);
        
        return $quota ? $this->normalizeQuota($quota) : null;
    }
    
    /**
     * Get quota by ID
     */
    public function getQuotaById($id) {
        $sql = "SELECT * FROM quotas WHERE id = ?";
        $quota = $this->db->queryOne($sql, [$id]);
        
        return $quota ? $this->normalizeQuota($quota) : null;
    }
    
    /**
     * List all quotas for a tenant
     */
    public function listQuotas($tenantId) {
        $sql = "SELECT * FROM quotas WHERE tenant_id = ? ORDER BY resource_type, period";
        $quotas = $this->db->query($sql, [$tenantId]);
        
        return array_map([$this, 'normalizeQuota'], $quotas);
    }
    
    /**
     * Update a quota
     */
    public function updateQuota($id, $data) {
        $existing = $this->getQuotaById($id);
        if (!$existing) {
            throw new Exception('Quota not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['limit_value'])) {
            $updates[] = 'limit_value = ?';
            $params[] = $data['limit_value'];
        }
        
        if (isset($data['is_hard_limit'])) {
            $updates[] = 'is_hard_limit = ?';
            $params[] = $data['is_hard_limit'] ? 1 : 0;
        }
        
        if (array_key_exists('notification_threshold', $data)) {
            $updates[] = 'notification_threshold = ?';
            $params[] = $data['notification_threshold'];
        }
        
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        
        if (empty($updates)) {
            return $existing;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE quotas SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $params);
        
        return $this->getQuotaById($id);
    }
    
    /**
     * Delete a quota
     */
    public function deleteQuota($id) {
        $sql = "DELETE FROM quotas WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return true;
    }
    
    /**
     * Check if usage is within quota
     * Returns: ['allowed' => bool, 'current' => int, 'limit' => int, 'percentage' => float]
     */
    public function checkQuota($tenantId, $resourceType, $period = self::PERIOD_DAILY) {
        $quota = $this->getQuota($tenantId, $resourceType, $period);
        
        // No quota set = unlimited
        if (!$quota) {
            return [
                'allowed' => true,
                'current' => 0,
                'limit' => null,
                'percentage' => 0,
                'has_quota' => false
            ];
        }
        
        // Get current usage for the period
        $usage = $this->usageTrackingService->getUsageForPeriod($tenantId, $period, $resourceType);
        $currentUsage = 0;
        
        if (!empty($usage['by_resource_type'])) {
            foreach ($usage['by_resource_type'] as $stat) {
                if ($stat['resource_type'] === $resourceType) {
                    $currentUsage = (int)$stat['total_quantity'];
                    break;
                }
            }
        }
        
        $limit = (int)$quota['limit_value'];
        $percentage = $limit > 0 ? ($currentUsage / $limit) * 100 : 0;
        
        return [
            'allowed' => $currentUsage < $limit,
            'current' => $currentUsage,
            'limit' => $limit,
            'percentage' => round($percentage, 2),
            'has_quota' => true,
            'is_hard_limit' => (bool)$quota['is_hard_limit'],
            'notification_threshold' => $quota['notification_threshold']
        ];
    }
    
    /**
     * Enforce quota - throws exception if hard limit exceeded
     */
    public function enforceQuota($tenantId, $resourceType, $period = self::PERIOD_DAILY) {
        $check = $this->checkQuota($tenantId, $resourceType, $period);
        
        if (!$check['allowed'] && $check['is_hard_limit']) {
            throw new Exception(
                "Quota exceeded for $resourceType. Current: {$check['current']}, Limit: {$check['limit']}",
                429
            );
        }
        
        return $check;
    }
    
    /**
     * Get quota status for all resources
     */
    public function getQuotaStatus($tenantId) {
        $quotas = $this->listQuotas($tenantId);
        $status = [];
        
        foreach ($quotas as $quota) {
            $check = $this->checkQuota(
                $tenantId,
                $quota['resource_type'],
                $quota['period']
            );
            
            $status[] = array_merge($quota, $check);
        }
        
        return $status;
    }
    
    /**
     * Check if notification threshold is reached
     */
    public function shouldNotify($tenantId, $resourceType, $period = self::PERIOD_DAILY) {
        $check = $this->checkQuota($tenantId, $resourceType, $period);
        
        if (!$check['has_quota']) {
            return false;
        }
        
        $threshold = $check['notification_threshold'];
        
        if ($threshold === null) {
            return false;
        }
        
        return $check['percentage'] >= $threshold;
    }
    
    /**
     * Normalize quota data
     */
    private function normalizeQuota($quota) {
        if (isset($quota['is_hard_limit'])) {
            $quota['is_hard_limit'] = (bool)$quota['is_hard_limit'];
        }
        
        if (isset($quota['notification_threshold'])) {
            $quota['notification_threshold'] = $quota['notification_threshold'] !== null 
                ? (int)$quota['notification_threshold'] 
                : null;
        }
        
        return $quota;
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
