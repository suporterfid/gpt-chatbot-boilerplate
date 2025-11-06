<?php
/**
 * Usage Tracking Service - Track API usage per tenant
 * Records all billable operations for metering and billing
 */

require_once __DIR__ . '/DB.php';

class UsageTrackingService {
    private $db;
    
    // Resource types
    const RESOURCE_MESSAGE = 'message';
    const RESOURCE_COMPLETION = 'completion';
    const RESOURCE_FILE_UPLOAD = 'file_upload';
    const RESOURCE_FILE_STORAGE = 'file_storage';
    const RESOURCE_VECTOR_QUERY = 'vector_query';
    const RESOURCE_TOOL_CALL = 'tool_call';
    const RESOURCE_EMBEDDING = 'embedding';
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Log a usage event
     */
    public function logUsage($tenantId, $resourceType, $options = []) {
        if (empty($tenantId)) {
            throw new Exception('Tenant ID is required for usage logging', 400);
        }
        
        $validResourceTypes = [
            self::RESOURCE_MESSAGE,
            self::RESOURCE_COMPLETION,
            self::RESOURCE_FILE_UPLOAD,
            self::RESOURCE_FILE_STORAGE,
            self::RESOURCE_VECTOR_QUERY,
            self::RESOURCE_TOOL_CALL,
            self::RESOURCE_EMBEDDING
        ];
        
        if (!in_array($resourceType, $validResourceTypes)) {
            throw new Exception('Invalid resource type', 400);
        }
        
        $id = $this->generateUUID();
        $now = date('c');
        
        $sql = "INSERT INTO usage_logs (
            id, tenant_id, resource_type, resource_id, quantity, metadata_json, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $tenantId,
            $resourceType,
            $options['resource_id'] ?? null,
            $options['quantity'] ?? 1,
            isset($options['metadata']) ? json_encode($options['metadata']) : null,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return [
            'id' => $id,
            'tenant_id' => $tenantId,
            'resource_type' => $resourceType,
            'resource_id' => $options['resource_id'] ?? null,
            'quantity' => $options['quantity'] ?? 1,
            'metadata' => $options['metadata'] ?? null,
            'created_at' => $now
        ];
    }
    
    /**
     * Get usage statistics for a tenant
     */
    public function getUsageStats($tenantId, $filters = []) {
        $params = [$tenantId];
        $conditions = ['tenant_id = ?'];
        
        // Date range filters
        if (!empty($filters['start_date'])) {
            $conditions[] = 'created_at >= ?';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = 'created_at <= ?';
            $params[] = $filters['end_date'];
        }
        
        // Resource type filter
        if (!empty($filters['resource_type'])) {
            $conditions[] = 'resource_type = ?';
            $params[] = $filters['resource_type'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        // Get total usage by resource type
        $sql = "SELECT 
                    resource_type,
                    COUNT(*) as event_count,
                    SUM(quantity) as total_quantity
                FROM usage_logs
                WHERE $whereClause
                GROUP BY resource_type";
        
        $results = $this->db->query($sql, $params);
        
        // Get overall totals
        $totalSql = "SELECT 
                        COUNT(*) as total_events,
                        SUM(quantity) as total_quantity
                     FROM usage_logs
                     WHERE $whereClause";
        
        $totals = $this->db->queryOne($totalSql, $params);
        
        return [
            'tenant_id' => $tenantId,
            'by_resource_type' => $results,
            'totals' => $totals,
            'period' => [
                'start_date' => $filters['start_date'] ?? null,
                'end_date' => $filters['end_date'] ?? null
            ]
        ];
    }
    
    /**
     * Get usage for a specific period
     */
    public function getUsageForPeriod($tenantId, $period, $resourceType = null) {
        $now = new DateTime();
        
        switch ($period) {
            case 'hourly':
                $startDate = (clone $now)->modify('-1 hour')->format('c');
                break;
            case 'daily':
                $startDate = (clone $now)->modify('-1 day')->format('c');
                break;
            case 'monthly':
                $startDate = (clone $now)->modify('-30 days')->format('c');
                break;
            default:
                throw new Exception('Invalid period', 400);
        }
        
        $filters = ['start_date' => $startDate];
        
        if ($resourceType) {
            $filters['resource_type'] = $resourceType;
        }
        
        return $this->getUsageStats($tenantId, $filters);
    }
    
    /**
     * Get usage history with time series data
     */
    public function getUsageTimeSeries($tenantId, $filters = []) {
        $params = [$tenantId];
        $conditions = ['tenant_id = ?'];
        
        if (!empty($filters['start_date'])) {
            $conditions[] = 'created_at >= ?';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = 'created_at <= ?';
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['resource_type'])) {
            $conditions[] = 'resource_type = ?';
            $params[] = $filters['resource_type'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        // Group by date
        $groupBy = $filters['interval'] ?? 'day';
        
        $sql = "SELECT 
                    DATE(created_at) as date,
                    resource_type,
                    COUNT(*) as event_count,
                    SUM(quantity) as total_quantity
                FROM usage_logs
                WHERE $whereClause
                GROUP BY DATE(created_at), resource_type
                ORDER BY date ASC";
        
        $results = $this->db->query($sql, $params);
        
        return [
            'tenant_id' => $tenantId,
            'interval' => $groupBy,
            'data' => $results
        ];
    }
    
    /**
     * List recent usage events
     */
    public function listUsageEvents($tenantId, $filters = []) {
        $params = [$tenantId];
        $conditions = ['tenant_id = ?'];
        
        if (!empty($filters['resource_type'])) {
            $conditions[] = 'resource_type = ?';
            $params[] = $filters['resource_type'];
        }
        
        if (!empty($filters['resource_id'])) {
            $conditions[] = 'resource_id = ?';
            $params[] = $filters['resource_id'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT * FROM usage_logs WHERE $whereClause ORDER BY created_at DESC";
        
        // Add pagination
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $results = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizeUsageLog'], $results);
    }
    
    /**
     * Delete old usage logs (for cleanup/archival)
     */
    public function cleanupOldLogs($beforeDate) {
        $sql = "DELETE FROM usage_logs WHERE created_at < ?";
        $this->db->execute($sql, [$beforeDate]);
        
        return true;
    }
    
    /**
     * Normalize usage log data
     */
    private function normalizeUsageLog($log) {
        if (isset($log['metadata_json'])) {
            $log['metadata'] = json_decode($log['metadata_json'], true);
            unset($log['metadata_json']);
        }
        
        return $log;
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
