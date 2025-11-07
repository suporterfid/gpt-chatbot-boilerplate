<?php
/**
 * Tenant Usage Service - Aggregated usage statistics per tenant
 * Provides efficient pre-aggregated data for billing and dashboard queries
 */

require_once __DIR__ . '/DB.php';

class TenantUsageService {
    private $db;
    
    // Period types
    const PERIOD_HOURLY = 'hourly';
    const PERIOD_DAILY = 'daily';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_TOTAL = 'total';
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Aggregate usage data from usage_logs into tenant_usage
     * Should be called periodically (e.g., hourly via cron)
     */
    public function aggregateUsage($tenantId = null, $periodType = self::PERIOD_DAILY) {
        $validPeriods = [self::PERIOD_HOURLY, self::PERIOD_DAILY, self::PERIOD_MONTHLY, self::PERIOD_TOTAL];
        if (!in_array($periodType, $validPeriods)) {
            throw new Exception('Invalid period type. Valid options: hourly, daily, monthly, total', 400);
        }
        
        // Determine the period range
        $now = new DateTime();
        list($periodStart, $periodEnd) = $this->getPeriodRange($periodType, $now);
        
        // Build WHERE clause
        $whereConditions = ['created_at >= ? AND created_at < ?'];
        $params = [$periodStart, $periodEnd];
        
        if ($tenantId) {
            $whereConditions[] = 'tenant_id = ?';
            $params[] = $tenantId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Aggregate from usage_logs
        $sql = "SELECT 
                    tenant_id,
                    resource_type,
                    COUNT(*) as event_count,
                    SUM(quantity) as total_quantity
                FROM usage_logs
                WHERE $whereClause
                GROUP BY tenant_id, resource_type";
        
        $aggregates = $this->db->query($sql, $params);
        
        // Upsert into tenant_usage
        foreach ($aggregates as $agg) {
            $this->upsertAggregation(
                $agg['tenant_id'],
                $agg['resource_type'],
                $periodType,
                $periodStart,
                $periodEnd,
                (int)$agg['event_count'],
                (int)$agg['total_quantity']
            );
        }
        
        return count($aggregates);
    }
    
    /**
     * Increment usage in aggregation (real-time update)
     */
    public function incrementUsage($tenantId, $resourceType, $quantity = 1, $periodType = self::PERIOD_DAILY) {
        $now = new DateTime();
        list($periodStart, $periodEnd) = $this->getPeriodRange($periodType, $now);
        
        // Try to find existing aggregation
        $existing = $this->getAggregation($tenantId, $resourceType, $periodType, $periodStart);
        
        if ($existing) {
            // Update existing
            $sql = "UPDATE tenant_usage 
                    SET event_count = event_count + 1,
                        total_quantity = total_quantity + ?,
                        updated_at = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$quantity, date('c'), $existing['id']]);
        } else {
            // Insert new
            $this->upsertAggregation(
                $tenantId,
                $resourceType,
                $periodType,
                $periodStart,
                $periodEnd,
                1,
                $quantity
            );
        }
        
        return true;
    }
    
    /**
     * Get aggregated usage for a tenant
     */
    public function getTenantUsage($tenantId, $periodType = self::PERIOD_DAILY, $filters = []) {
        $params = [$tenantId, $periodType];
        $conditions = ['tenant_id = ?', 'period_type = ?'];
        
        if (!empty($filters['resource_type'])) {
            $conditions[] = 'resource_type = ?';
            $params[] = $filters['resource_type'];
        }
        
        if (!empty($filters['start_date'])) {
            $conditions[] = 'period_start >= ?';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = 'period_end <= ?';
            $params[] = $filters['end_date'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT * FROM tenant_usage 
                WHERE $whereClause 
                ORDER BY period_start DESC, resource_type ASC";
        
        $results = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizeUsage'], $results);
    }
    
    /**
     * Get current period usage summary
     */
    public function getCurrentUsageSummary($tenantId, $periodType = self::PERIOD_DAILY) {
        $now = new DateTime();
        list($periodStart, $periodEnd) = $this->getPeriodRange($periodType, $now);
        
        $sql = "SELECT 
                    resource_type,
                    event_count,
                    total_quantity,
                    period_start,
                    period_end
                FROM tenant_usage
                WHERE tenant_id = ? 
                  AND period_type = ? 
                  AND period_start = ?
                ORDER BY resource_type";
        
        $results = $this->db->query($sql, [$tenantId, $periodType, $periodStart]);
        
        // Calculate totals
        $totalEvents = 0;
        $totalQuantity = 0;
        
        foreach ($results as $row) {
            $totalEvents += (int)$row['event_count'];
            $totalQuantity += (int)$row['total_quantity'];
        }
        
        return [
            'tenant_id' => $tenantId,
            'period_type' => $periodType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'by_resource_type' => $results,
            'totals' => [
                'total_events' => $totalEvents,
                'total_quantity' => $totalQuantity
            ]
        ];
    }
    
    /**
     * Get usage trends over time
     */
    public function getUsageTrends($tenantId, $periodType = self::PERIOD_DAILY, $limit = 30) {
        $sql = "SELECT 
                    period_start,
                    period_end,
                    resource_type,
                    event_count,
                    total_quantity
                FROM tenant_usage
                WHERE tenant_id = ? AND period_type = ?
                ORDER BY period_start DESC
                LIMIT ?";
        
        $results = $this->db->query($sql, [$tenantId, $periodType, $limit]);
        
        return [
            'tenant_id' => $tenantId,
            'period_type' => $periodType,
            'data' => array_reverse($results) // Oldest to newest
        ];
    }
    
    /**
     * Cleanup old aggregations
     */
    public function cleanupOldAggregations($beforeDate) {
        $sql = "DELETE FROM tenant_usage WHERE period_start < ?";
        $this->db->execute($sql, [$beforeDate]);
        
        return true;
    }
    
    /**
     * Get aggregation by criteria
     */
    private function getAggregation($tenantId, $resourceType, $periodType, $periodStart) {
        $sql = "SELECT * FROM tenant_usage 
                WHERE tenant_id = ? 
                  AND resource_type = ? 
                  AND period_type = ? 
                  AND period_start = ?";
        
        $result = $this->db->queryOne($sql, [$tenantId, $resourceType, $periodType, $periodStart]);
        
        return $result ? $this->normalizeUsage($result) : null;
    }
    
    /**
     * Upsert aggregation data
     */
    private function upsertAggregation($tenantId, $resourceType, $periodType, $periodStart, $periodEnd, $eventCount, $totalQuantity) {
        $existing = $this->getAggregation($tenantId, $resourceType, $periodType, $periodStart);
        
        if ($existing) {
            // Update
            $sql = "UPDATE tenant_usage 
                    SET event_count = ?,
                        total_quantity = ?,
                        updated_at = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [$eventCount, $totalQuantity, date('c'), $existing['id']]);
            
            return $existing['id'];
        } else {
            // Insert
            $id = $this->generateUUID();
            $now = date('c');
            
            $sql = "INSERT INTO tenant_usage (
                id, tenant_id, resource_type, period_type, period_start, period_end,
                event_count, total_quantity, metadata_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->insert($sql, [
                $id, $tenantId, $resourceType, $periodType, $periodStart, $periodEnd,
                $eventCount, $totalQuantity, null, $now, $now
            ]);
            
            return $id;
        }
    }
    
    /**
     * Get period range for aggregation
     */
    private function getPeriodRange($periodType, DateTime $referenceDate) {
        $start = clone $referenceDate;
        $end = clone $referenceDate;
        
        switch ($periodType) {
            case self::PERIOD_HOURLY:
                $start->setTime((int)$start->format('H'), 0, 0);
                $end = (clone $start)->modify('+1 hour');
                break;
                
            case self::PERIOD_DAILY:
                $start->setTime(0, 0, 0);
                $end = (clone $start)->modify('+1 day');
                break;
                
            case self::PERIOD_MONTHLY:
                $start->modify('first day of this month')->setTime(0, 0, 0);
                $end = (clone $start)->modify('+1 month');
                break;
                
            case self::PERIOD_TOTAL:
                $start = new DateTime('2000-01-01 00:00:00');
                $end = new DateTime('2099-12-31 23:59:59');
                break;
        }
        
        return [$start->format('c'), $end->format('c')];
    }
    
    /**
     * Normalize usage data
     */
    private function normalizeUsage($usage) {
        if (isset($usage['metadata_json']) && $usage['metadata_json']) {
            $usage['metadata'] = json_decode($usage['metadata_json'], true);
            unset($usage['metadata_json']);
        }
        
        if (isset($usage['event_count'])) {
            $usage['event_count'] = (int)$usage['event_count'];
        }
        
        if (isset($usage['total_quantity'])) {
            $usage['total_quantity'] = (int)$usage['total_quantity'];
        }
        
        return $usage;
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
