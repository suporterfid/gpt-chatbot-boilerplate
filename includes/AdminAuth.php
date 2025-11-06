<?php
/**
 * Admin Authentication and Authorization Service
 * Handles user authentication, API key management, and role-based permissions
 */

require_once __DIR__ . '/DB.php';

class AdminAuth {
    private $db;
    private $config;
    
    // Permission levels
    const ROLE_VIEWER = 'viewer';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPER_ADMIN = 'super-admin';
    
    // Permission matrix
    const PERMISSIONS = [
        self::ROLE_VIEWER => ['read'],
        self::ROLE_ADMIN => ['read', 'create', 'update', 'delete'],
        self::ROLE_SUPER_ADMIN => ['read', 'create', 'update', 'delete', 'manage_users', 'rotate_tokens']
    ];
    
    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Authenticate using Bearer token
     * Supports both legacy ADMIN_TOKEN and per-user API keys
     * 
     * @param string $token Bearer token
     * @return array|null User data with role, or null if invalid
     */
    public function authenticate($token) {
        // Check legacy ADMIN_TOKEN first
        $legacyToken = $this->config['admin']['token'] ?? null;
        if ($legacyToken && $token === $legacyToken) {
            // Return super-admin user for legacy token
            return [
                'id' => 'legacy',
                'email' => 'admin@legacy',
                'role' => self::ROLE_SUPER_ADMIN,
                'is_active' => true,
                'auth_method' => 'legacy_token'
            ];
        }
        
        // Check API keys
        $keyHash = hash('sha256', $token);
        
        $sql = "SELECT ak.*, au.email, au.role, au.is_active, au.tenant_id 
                FROM admin_api_keys ak
                JOIN admin_users au ON ak.user_id = au.id
                WHERE ak.key_hash = ? 
                AND ak.is_active = 1
                AND au.is_active = 1
                AND (ak.expires_at IS NULL OR ak.expires_at > ?)";
        
        $now = date('Y-m-d H:i:s');
        $result = $this->db->queryOne($sql, [$keyHash, $now]);
        
        if (!$result) {
            return null;
        }
        
        // Update last_used_at
        $this->updateKeyLastUsed($result['id']);
        
        return [
            'id' => $result['user_id'],
            'email' => $result['email'],
            'role' => $result['role'],
            'is_active' => (bool)$result['is_active'],
            'tenant_id' => $result['tenant_id'],
            'api_key_id' => $result['id'],
            'auth_method' => 'api_key'
        ];
    }
    
    /**
     * Check if user has permission
     * 
     * @param array $user User data from authenticate()
     * @param string $permission Permission to check
     * @return bool True if user has permission
     */
    public function hasPermission($user, $permission) {
        if (!$user || !isset($user['role'])) {
            return false;
        }
        
        $role = $user['role'];
        $permissions = self::PERMISSIONS[$role] ?? [];
        
        return in_array($permission, $permissions, true);
    }
    
    /**
     * Require permission or throw exception
     * 
     * @param array $user User data
     * @param string $permission Permission to require
     * @throws Exception if user lacks permission
     */
    public function requirePermission($user, $permission) {
        if (!$this->hasPermission($user, $permission)) {
            throw new Exception("Insufficient permissions: $permission required", 403);
        }
    }
    
    /**
     * Create a new admin user
     * 
     * @param string $email User email
     * @param string $password Password (will be hashed)
     * @param string $role User role
     * @param string|null $tenantId Tenant ID (null for super-admin)
     * @return array Created user
     */
    public function createUser($email, $password, $role = self::ROLE_ADMIN, $tenantId = null) {
        // Validate role
        if (!in_array($role, [self::ROLE_VIEWER, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true)) {
            throw new Exception("Invalid role: $role");
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }
        
        // Super-admins should not be tied to a specific tenant
        if ($role === self::ROLE_SUPER_ADMIN && $tenantId !== null) {
            throw new Exception("Super-admins cannot be assigned to a specific tenant", 400);
        }
        
        // Regular admins and viewers must have a tenant_id
        if ($role !== self::ROLE_SUPER_ADMIN && $tenantId === null) {
            throw new Exception("Non-super-admin users must be assigned to a tenant", 400);
        }
        
        $id = $this->generateUUID();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO admin_users (
            id, email, password_hash, role, tenant_id, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, 1, ?, ?)";
        
        try {
            $this->db->insert($sql, [$id, $email, $passwordHash, $role, $tenantId, $now, $now]);
            
            return $this->getUser($id);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                throw new Exception("Email already exists", 409);
            }
            throw $e;
        }
    }
    
    /**
     * Get user by ID
     * 
     * @param string $userId User ID
     * @return array|null User data
     */
    public function getUser($userId) {
        $sql = "SELECT id, email, role, tenant_id, is_active, created_at, updated_at 
                FROM admin_users WHERE id = ?";
        
        return $this->db->queryOne($sql, [$userId]);
    }
    
    /**
     * List all users
     * 
     * @return array List of users
     */
    public function listUsers() {
        $sql = "SELECT id, email, role, is_active, created_at, updated_at 
                FROM admin_users 
                ORDER BY created_at DESC";
        
        return $this->db->query($sql);
    }
    
    /**
     * Update user role
     * 
     * @param string $userId User ID
     * @param string $role New role
     */
    public function updateUserRole($userId, $role) {
        if (!in_array($role, [self::ROLE_VIEWER, self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true)) {
            throw new Exception("Invalid role: $role");
        }
        
        $sql = "UPDATE admin_users SET role = ?, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [$role, date('Y-m-d H:i:s'), $userId]);
    }
    
    /**
     * Deactivate user
     * 
     * @param string $userId User ID
     */
    public function deactivateUser($userId) {
        $sql = "UPDATE admin_users SET is_active = 0, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [date('Y-m-d H:i:s'), $userId]);
    }
    
    /**
     * Generate API key for user
     * 
     * @param string $userId User ID
     * @param string $name Key name/description
     * @param int $expiresInDays Days until expiration (null = never)
     * @return array Generated key data (includes plain-text key - only shown once!)
     */
    public function generateApiKey($userId, $name = null, $expiresInDays = null) {
        // Generate random API key
        $key = 'chatbot_' . bin2hex(random_bytes(32));
        $keyHash = hash('sha256', $key);
        $keyPrefix = substr($key, 0, 16) . '...';
        
        $id = $this->generateUUID();
        $now = date('Y-m-d H:i:s');
        
        $expiresAt = null;
        if ($expiresInDays) {
            $expiresDate = new DateTime();
            $expiresDate->modify("+{$expiresInDays} days");
            $expiresAt = $expiresDate->format('Y-m-d H:i:s');
        }
        
        $sql = "INSERT INTO admin_api_keys (
            id, user_id, key_hash, key_prefix, name, expires_at, 
            is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)";
        
        $this->db->insert($sql, [
            $id, $userId, $keyHash, $keyPrefix, $name, $expiresAt, $now, $now
        ]);
        
        return [
            'id' => $id,
            'key' => $key,  // Only returned once!
            'key_prefix' => $keyPrefix,
            'name' => $name,
            'expires_at' => $expiresAt,
            'created_at' => $now
        ];
    }
    
    /**
     * List API keys for user
     * 
     * @param string $userId User ID
     * @return array List of API keys (without hashes)
     */
    public function listApiKeys($userId) {
        $sql = "SELECT id, user_id, key_prefix, name, last_used_at, expires_at, is_active, created_at 
                FROM admin_api_keys 
                WHERE user_id = ?
                ORDER BY created_at DESC";
        
        return $this->db->query($sql, [$userId]);
    }
    
    /**
     * Get API key by ID
     * 
     * @param string $keyId API key ID
     * @return array|null API key data (without hash)
     */
    public function getApiKey($keyId) {
        $sql = "SELECT id, user_id, key_prefix, name, last_used_at, expires_at, is_active, created_at 
                FROM admin_api_keys 
                WHERE id = ?";
        
        return $this->db->queryOne($sql, [$keyId]);
    }
    
    /**
     * Revoke API key
     * 
     * @param string $keyId API key ID
     */
    public function revokeApiKey($keyId) {
        $sql = "UPDATE admin_api_keys SET is_active = 0, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [date('Y-m-d H:i:s'), $keyId]);
    }
    
    /**
     * Delete API key
     * 
     * @param string $keyId API key ID
     */
    public function deleteApiKey($keyId) {
        $sql = "DELETE FROM admin_api_keys WHERE id = ?";
        $this->db->execute($sql, [$keyId]);
    }
    
    /**
     * Update last_used_at timestamp for API key
     * 
     * @param string $keyId API key ID
     */
    private function updateKeyLastUsed($keyId) {
        $sql = "UPDATE admin_api_keys SET last_used_at = ? WHERE id = ?";
        try {
            $this->db->execute($sql, [date('Y-m-d H:i:s'), $keyId]);
        } catch (Exception $e) {
            // Ignore errors - this is non-critical
            error_log("Failed to update key last_used_at: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate legacy ADMIN_TOKEN to super-admin user
     * Creates a super-admin user and API key
     * 
     * @return array Created user and key data
     */
    public function migrateLegacyToken() {
        $email = 'admin@system.local';
        $password = bin2hex(random_bytes(16)); // Random password (won't be used)
        
        try {
            $user = $this->createUser($email, $password, self::ROLE_SUPER_ADMIN);
            $key = $this->generateApiKey($user['id'], 'Migrated from ADMIN_TOKEN');
            
            return [
                'user' => $user,
                'api_key' => $key
            ];
        } catch (Exception $e) {
            // User might already exist
            if (strpos($e->getMessage(), 'already exists') !== false) {
                $sql = "SELECT id FROM admin_users WHERE email = ?";
                $existing = $this->db->queryOne($sql, [$email]);
                if ($existing) {
                    $key = $this->generateApiKey($existing['id'], 'Migration token');
                    return [
                        'user' => $this->getUser($existing['id']),
                        'api_key' => $key
                    ];
                }
            }
            throw $e;
        }
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
