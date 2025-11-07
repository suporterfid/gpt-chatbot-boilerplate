<?php
/**
 * Resource-Level Authorization Service
 * Extends RBAC with per-resource access control for multi-tenant SaaS scenarios
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/AdminAuth.php';
require_once __DIR__ . '/AuditService.php';

class ResourceAuthService {
    private $db;
    private $adminAuth;
    private $auditService;
    
    // Resource types
    const RESOURCE_AGENT = 'agent';
    const RESOURCE_PROMPT = 'prompt';
    const RESOURCE_VECTOR_STORE = 'vector_store';
    const RESOURCE_CONVERSATION = 'conversation';
    const RESOURCE_FILE = 'file';
    const RESOURCE_WEBHOOK = 'webhook';
    const RESOURCE_JOB = 'job';
    const RESOURCE_LEAD = 'lead';
    
    // Actions
    const ACTION_READ = 'read';
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_EXECUTE = 'execute';
    
    public function __construct($db, $adminAuth, $auditService = null) {
        $this->db = $db;
        $this->adminAuth = $adminAuth;
        $this->auditService = $auditService;
    }
    
    /**
     * Check if user can access a specific resource
     * 
     * @param array $user Authenticated user data
     * @param string $resourceType Resource type (agent, prompt, etc.)
     * @param string $resourceId Resource ID
     * @param string $action Action being attempted (read, create, update, delete)
     * @return bool True if user has access
     */
    public function canAccessResource($user, $resourceType, $resourceId, $action) {
        // Super-admins have access to all resources
        if ($user['role'] === AdminAuth::ROLE_SUPER_ADMIN) {
            return true;
        }
        
        // Check if user has RBAC permission for the action
        if (!$this->adminAuth->hasPermission($user, $action)) {
            return false;
        }
        
        // For create action, check tenant-level permission only
        if ($action === self::ACTION_CREATE) {
            return true; // Already passed RBAC check
        }
        
        // Check resource ownership/permission
        return $this->checkResourceOwnership($user, $resourceType, $resourceId, $action);
    }
    
    /**
     * Require resource access or throw exception
     * 
     * @param array $user Authenticated user data
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @param string $action Action being attempted
     * @throws Exception if access denied
     */
    public function requireResourceAccess($user, $resourceType, $resourceId, $action) {
        if (!$this->canAccessResource($user, $resourceType, $resourceId, $action)) {
            $this->logAccessDenied($user, $resourceType, $resourceId, $action);
            
            throw new Exception(
                "Access denied: You do not have permission to {$action} this {$resourceType}",
                403
            );
        }
    }
    
    /**
     * Check if user owns or has explicit permission to access a resource
     * 
     * @param array $user Authenticated user data
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @param string $action Action being attempted
     * @return bool True if user has access
     */
    private function checkResourceOwnership($user, $resourceType, $resourceId, $action) {
        // First check for explicit resource permissions
        if ($this->hasExplicitPermission($user, $resourceType, $resourceId, $action)) {
            return true;
        }
        
        // Then check tenant-level ownership (backward compatibility)
        if ($this->isResourceInUserTenant($user, $resourceType, $resourceId)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if resource belongs to user's tenant
     * 
     * @param array $user Authenticated user data
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @return bool True if resource is in user's tenant
     */
    private function isResourceInUserTenant($user, $resourceType, $resourceId) {
        $tenantId = $user['tenant_id'] ?? null;
        
        // Users without tenant cannot access tenant-scoped resources
        if ($tenantId === null) {
            return false;
        }
        
        $tableName = $this->getTableName($resourceType);
        if (!$tableName) {
            return false;
        }
        
        $sql = "SELECT tenant_id FROM {$tableName} WHERE id = ?";
        $result = $this->db->queryOne($sql, [$resourceId]);
        
        if (!$result) {
            return false; // Resource not found
        }
        
        // Check if resource belongs to user's tenant
        return $result['tenant_id'] === $tenantId;
    }
    
    /**
     * Check if user has explicit permission for a resource
     * 
     * @param array $user Authenticated user data
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @param string $action Action being attempted
     * @return bool True if explicit permission exists for this action
     */
    private function hasExplicitPermission($user, $resourceType, $resourceId, $action) {
        $sql = "SELECT permissions_json FROM resource_permissions 
                WHERE user_id = ? 
                AND resource_type = ? 
                AND resource_id = ? 
                AND is_active = 1";
        
        $result = $this->db->queryOne($sql, [$user['id'], $resourceType, $resourceId]);
        
        if (!$result) {
            return false;
        }
        
        // Check if the specific action is in the granted permissions
        $permissions = json_decode($result['permissions_json'], true);
        return in_array($action, $permissions, true);
    }
    
    /**
     * Grant explicit permission to a user for a resource
     * 
     * @param string $userId User ID to grant permission to
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @param array $permissions Array of permissions (read, update, delete, execute)
     * @param string $grantedBy User ID who granted the permission
     * @return array Created permission record
     */
    public function grantResourcePermission($userId, $resourceType, $resourceId, array $permissions, $grantedBy) {
        $id = $this->generateUUID();
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO resource_permissions (
            id, user_id, resource_type, resource_id, 
            permissions_json, granted_by, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)";
        
        $this->db->insert($sql, [
            $id,
            $userId,
            $resourceType,
            $resourceId,
            json_encode($permissions),
            $grantedBy,
            $now,
            $now
        ]);
        
        return [
            'id' => $id,
            'user_id' => $userId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'permissions' => $permissions,
            'granted_by' => $grantedBy,
            'created_at' => $now
        ];
    }
    
    /**
     * Revoke resource permission
     * 
     * @param string $permissionId Permission ID
     */
    public function revokeResourcePermission($permissionId) {
        $sql = "UPDATE resource_permissions SET is_active = 0, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [date('Y-m-d H:i:s'), $permissionId]);
    }
    
    /**
     * List all permissions for a resource
     * 
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @return array List of permissions
     */
    public function listResourcePermissions($resourceType, $resourceId) {
        $sql = "SELECT rp.*, au.email as user_email 
                FROM resource_permissions rp
                LEFT JOIN admin_users au ON rp.user_id = au.id
                WHERE rp.resource_type = ? 
                AND rp.resource_id = ? 
                AND rp.is_active = 1
                ORDER BY rp.created_at DESC";
        
        $permissions = $this->db->query($sql, [$resourceType, $resourceId]);
        
        return array_map(function($perm) {
            $perm['permissions'] = json_decode($perm['permissions_json'], true);
            unset($perm['permissions_json']);
            return $perm;
        }, $permissions);
    }
    
    /**
     * List all resources accessible by a user
     * 
     * @param string $userId User ID
     * @param string $resourceType Optional resource type filter
     * @return array List of accessible resource IDs
     */
    public function listUserAccessibleResources($userId, $resourceType = null) {
        $sql = "SELECT DISTINCT resource_id, resource_type 
                FROM resource_permissions 
                WHERE user_id = ? 
                AND is_active = 1";
        
        $params = [$userId];
        
        if ($resourceType) {
            $sql .= " AND resource_type = ?";
            $params[] = $resourceType;
        }
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Log access denied event to audit trail
     * 
     * @param array $user User who was denied
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @param string $action Action attempted
     */
    private function logAccessDenied($user, $resourceType, $resourceId, $action) {
        if (!$this->auditService || !$this->auditService->isEnabled()) {
            return;
        }
        
        try {
            $this->auditService->logEvent([
                'event_type' => 'access_denied',
                'user_id' => $user['id'],
                'user_email' => $user['email'],
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'action' => $action,
                'tenant_id' => $user['tenant_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log('ResourceAuthService: Failed to log access denied: ' . $e->getMessage());
        }
    }
    
    /**
     * Get table name for resource type
     * 
     * @param string $resourceType Resource type
     * @return string|null Table name or null if invalid type
     */
    private function getTableName($resourceType) {
        $mapping = [
            self::RESOURCE_AGENT => 'agents',
            self::RESOURCE_PROMPT => 'prompts',
            self::RESOURCE_VECTOR_STORE => 'vector_stores',
            self::RESOURCE_CONVERSATION => 'audit_conversations',
            self::RESOURCE_FILE => 'vector_store_files',
            self::RESOURCE_WEBHOOK => 'webhook_events',
            self::RESOURCE_JOB => 'jobs',
            self::RESOURCE_LEAD => 'leads'
        ];
        
        return $mapping[$resourceType] ?? null;
    }
    
    /**
     * Generate UUID v4
     * Note: Duplicated from AuditService for independence. Consider extracting to shared utility class.
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
