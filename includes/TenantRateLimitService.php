<?php
/**
 * Tenant Rate Limiting Service
 * Provides per-tenant rate limiting to prevent abuse and manage API costs
 */

require_once __DIR__ . '/DB.php';

class TenantRateLimitService {
    private $db;
    private $cacheDir;
    
    public function __construct($db, $cacheDir = '/tmp') {
        $this->db = $db;
        $this->cacheDir = $cacheDir;
    }
    
    /**
     * Check if tenant is within rate limit
     * 
     * @param string $tenantId
     * @param string $resourceType Resource being accessed (e.g., 'api_call', 'message', 'completion')
     * @param int $limit Number of requests allowed in the window
     * @param int $windowSeconds Time window in seconds
     * @return array ['allowed' => bool, 'current' => int, 'limit' => int, 'reset_at' => timestamp]
     */
    public function checkRateLimit($tenantId, $resourceType = 'api_call', $limit = 60, $windowSeconds = 60) {
        $cacheKey = $this->getCacheKey($tenantId, $resourceType, $windowSeconds);
        $cacheFile = $this->cacheDir . '/' . $cacheKey;
        
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Load existing requests from cache
        $requests = [];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && is_array($data)) {
                // Filter out requests outside the current window
                $requests = array_filter($data, function($timestamp) use ($windowStart) {
                    return $timestamp > $windowStart;
                });
            }
        }
        
        $currentCount = count($requests);
        $allowed = $currentCount < $limit;
        
        // Calculate reset time (when the oldest request will expire)
        $resetAt = !empty($requests) ? min($requests) + $windowSeconds : $now + $windowSeconds;
        
        return [
            'allowed' => $allowed,
            'current' => $currentCount,
            'limit' => $limit,
            'remaining' => max(0, $limit - $currentCount),
            'reset_at' => $resetAt,
            'window_seconds' => $windowSeconds
        ];
    }
    
    /**
     * Record a request for rate limiting
     */
    public function recordRequest($tenantId, $resourceType = 'api_call', $windowSeconds = 60) {
        $cacheKey = $this->getCacheKey($tenantId, $resourceType, $windowSeconds);
        $cacheFile = $this->cacheDir . '/' . $cacheKey;
        
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Load existing requests
        $requests = [];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && is_array($data)) {
                $requests = array_filter($data, function($timestamp) use ($windowStart) {
                    return $timestamp > $windowStart;
                });
            }
        }
        
        // Add current request
        $requests[] = $now;
        
        // Save back to cache
        file_put_contents($cacheFile, json_encode(array_values($requests)));
        
        return true;
    }
    
    /**
     * Enforce rate limit - throws exception if limit exceeded
     */
    public function enforceRateLimit($tenantId, $resourceType = 'api_call', $limit = 60, $windowSeconds = 60) {
        $check = $this->checkRateLimit($tenantId, $resourceType, $limit, $windowSeconds);
        
        if (!$check['allowed']) {
            $resetIn = $check['reset_at'] - time();
            throw new Exception(
                "Rate limit exceeded. Limit: {$check['limit']}/{$windowSeconds}s. Try again in {$resetIn}s",
                429
            );
        }
        
        // Record this request
        $this->recordRequest($tenantId, $resourceType, $windowSeconds);
        
        return $check;
    }
    
    /**
     * Get rate limit configuration for a tenant
     * Checks if tenant has custom rate limits defined in quotas table
     */
    public function getTenantRateLimit($tenantId, $resourceType = 'api_call') {
        // Check for tenant-specific quota
        $sql = "SELECT limit_value, period FROM quotas 
                WHERE tenant_id = ? 
                  AND resource_type = ? 
                  AND period IN ('hourly', 'daily')
                ORDER BY period ASC
                LIMIT 1";
        
        $quota = $this->db->queryOne($sql, [$tenantId, $resourceType]);
        
        if ($quota) {
            // Convert period to window seconds
            $windowSeconds = $this->periodToSeconds($quota['period']);
            return [
                'limit' => (int)$quota['limit_value'],
                'window_seconds' => $windowSeconds
            ];
        }
        
        // Return default limits
        return $this->getDefaultRateLimit($resourceType);
    }
    
    /**
     * Get default rate limits by resource type
     */
    private function getDefaultRateLimit($resourceType) {
        $defaults = [
            'api_call' => ['limit' => 60, 'window_seconds' => 60], // 60 req/min
            'message' => ['limit' => 100, 'window_seconds' => 3600], // 100 messages/hour
            'completion' => ['limit' => 100, 'window_seconds' => 3600], // 100 completions/hour
            'file_upload' => ['limit' => 10, 'window_seconds' => 3600], // 10 uploads/hour
            'vector_query' => ['limit' => 1000, 'window_seconds' => 3600], // 1000 queries/hour
            'tool_call' => ['limit' => 200, 'window_seconds' => 3600], // 200 tool calls/hour
            'embedding' => ['limit' => 500, 'window_seconds' => 3600], // 500 embeddings/hour
        ];
        
        return $defaults[$resourceType] ?? ['limit' => 60, 'window_seconds' => 60];
    }
    
    /**
     * Convert period to seconds
     */
    private function periodToSeconds($period) {
        switch ($period) {
            case 'hourly':
                return 3600;
            case 'daily':
                return 86400;
            case 'monthly':
                return 2592000; // 30 days
            default:
                return 60;
        }
    }
    
    /**
     * Generate cache key for rate limiting
     */
    private function getCacheKey($tenantId, $resourceType, $windowSeconds) {
        return 'ratelimit_' . md5($tenantId . '_' . $resourceType . '_' . $windowSeconds);
    }
    
    /**
     * Clear rate limit for tenant (for testing or manual reset)
     */
    public function clearRateLimit($tenantId, $resourceType = 'api_call', $windowSeconds = 60) {
        $cacheKey = $this->getCacheKey($tenantId, $resourceType, $windowSeconds);
        $cacheFile = $this->cacheDir . '/' . $cacheKey;
        
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * Get all rate limit statuses for a tenant
     */
    public function getTenantRateLimitStatus($tenantId) {
        $resourceTypes = ['api_call', 'message', 'completion', 'file_upload', 'vector_query', 'tool_call', 'embedding'];
        $status = [];
        
        foreach ($resourceTypes as $resourceType) {
            $config = $this->getTenantRateLimit($tenantId, $resourceType);
            $check = $this->checkRateLimit($tenantId, $resourceType, $config['limit'], $config['window_seconds']);
            
            $status[] = [
                'resource_type' => $resourceType,
                'limit' => $check['limit'],
                'current' => $check['current'],
                'remaining' => $check['remaining'],
                'window_seconds' => $check['window_seconds'],
                'reset_at' => $check['reset_at'],
                'percentage' => $check['limit'] > 0 ? round(($check['current'] / $check['limit']) * 100, 2) : 0
            ];
        }
        
        return $status;
    }
    
    /**
     * Cleanup old rate limit cache files
     */
    public function cleanupCache($olderThanSeconds = 86400) {
        $files = glob($this->cacheDir . '/ratelimit_*');
        $now = time();
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $olderThanSeconds) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}
