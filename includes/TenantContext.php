<?php
/**
 * Tenant Context Manager
 * Provides a centralized way to manage tenant context across the application
 * Ensures consistent tenant filtering in queries and operations
 */

class TenantContext {
    private static $instance = null;
    private $currentTenantId = null;
    private $currentUser = null;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new TenantContext();
        }
        return self::$instance;
    }
    
    /**
     * Set tenant context from authenticated user
     * 
     * @param array $user User data from AdminAuth::authenticate()
     */
    public function setFromUser($user) {
        $this->currentUser = $user;
        $this->currentTenantId = $user['tenant_id'] ?? null;
    }
    
    /**
     * Set tenant context directly
     * 
     * @param string|null $tenantId Tenant ID
     */
    public function setTenantId($tenantId) {
        $this->currentTenantId = $tenantId;
    }
    
    /**
     * Get current tenant ID
     * 
     * @return string|null Current tenant ID or null if super-admin
     */
    public function getTenantId() {
        return $this->currentTenantId;
    }
    
    /**
     * Get current user
     * 
     * @return array|null Current user data
     */
    public function getUser() {
        return $this->currentUser;
    }
    
    /**
     * Check if current context is super-admin (no tenant restriction)
     * 
     * @return bool True if super-admin (tenant_id is null)
     */
    public function isSuperAdmin() {
        return $this->currentTenantId === null && 
               $this->currentUser !== null && 
               ($this->currentUser['role'] ?? '') === 'super-admin';
    }
    
    /**
     * Check if current context has a tenant
     * 
     * @return bool True if tenant context is set
     */
    public function hasTenant() {
        return $this->currentTenantId !== null;
    }
    
    /**
     * Apply tenant filter to SQL query
     * Adds tenant_id filtering if tenant context is set
     * 
     * @param string $sql Base SQL query
     * @param array $params Query parameters (will be modified by reference)
     * @param string $tableAlias Optional table alias (e.g., 'a' for 'a.tenant_id')
     * @return string Modified SQL with tenant filter
     */
    public function applyFilter($sql, &$params, $tableAlias = '') {
        if ($this->currentTenantId === null) {
            return $sql;
        }
        
        $tenantColumn = empty($tableAlias) ? 'tenant_id' : $tableAlias . '.tenant_id';
        
        // Determine if we need WHERE or AND
        $hasWhere = stripos($sql, 'WHERE') !== false;
        $conjunction = $hasWhere ? 'AND' : 'WHERE';
        
        // Insert the filter before ORDER BY, GROUP BY, LIMIT, etc.
        $keywords = ['ORDER BY', 'GROUP BY', 'LIMIT', 'OFFSET', 'HAVING'];
        $insertPos = strlen($sql);
        
        foreach ($keywords as $keyword) {
            $pos = stripos($sql, $keyword);
            if ($pos !== false && $pos < $insertPos) {
                $insertPos = $pos;
            }
        }
        
        $filter = " $conjunction $tenantColumn = ? ";
        $sql = substr($sql, 0, $insertPos) . $filter . substr($sql, $insertPos);
        $params[] = $this->currentTenantId;
        
        return $sql;
    }
    
    /**
     * Build WHERE conditions array with tenant filter
     * 
     * @param array $conditions Existing WHERE conditions
     * @param string $tableAlias Optional table alias
     * @return array Conditions array with tenant filter added
     */
    public function buildConditions($conditions = [], $tableAlias = '') {
        if ($this->currentTenantId !== null) {
            $tenantColumn = empty($tableAlias) ? 'tenant_id' : $tableAlias . '.tenant_id';
            $conditions[] = "$tenantColumn = ?";
        }
        return $conditions;
    }
    
    /**
     * Add tenant_id to data array for INSERT/UPDATE operations
     * 
     * @param array $data Data array
     * @param bool $override Override existing tenant_id if present
     * @return array Data array with tenant_id added
     */
    public function addTenantId($data, $override = false) {
        if ($this->currentTenantId !== null && (!isset($data['tenant_id']) || $override)) {
            $data['tenant_id'] = $this->currentTenantId;
        }
        return $data;
    }
    
    /**
     * Validate that resource belongs to current tenant
     * 
     * @param string|null $resourceTenantId Resource's tenant_id
     * @throws Exception if resource doesn't belong to current tenant
     */
    public function validateAccess($resourceTenantId) {
        // Super-admins can access all resources
        if ($this->isSuperAdmin()) {
            return;
        }
        
        // If no tenant context, deny access (shouldn't happen in normal flow)
        if ($this->currentTenantId === null) {
            throw new Exception('Access denied: no tenant context', 403);
        }
        
        // Check if resource belongs to current tenant
        if ($resourceTenantId !== $this->currentTenantId) {
            throw new Exception('Access denied: resource not accessible', 403);
        }
    }
    
    /**
     * Clear tenant context (useful for testing or special operations)
     */
    public function clear() {
        $this->currentTenantId = null;
        $this->currentUser = null;
    }
    
    /**
     * Create a new instance for testing purposes
     * 
     * @return TenantContext New instance
     */
    public static function createForTesting() {
        return new TenantContext();
    }
}
