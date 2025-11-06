<?php
/**
 * Tenant Service - Multi-tenancy management
 * Handles CRUD operations for tenants and tenant context
 */

require_once __DIR__ . '/DB.php';

class TenantService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create a new tenant
     */
    public function createTenant($data) {
        // Validate required fields
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new Exception('Tenant name is required', 400);
        }
        
        // Generate UUID
        $id = $this->generateUUID();
        
        // Generate slug from name if not provided
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);
        
        // Validate slug
        $this->validateSlug($slug);
        
        // Prepare data
        $now = date('c'); // ISO 8601 format
        
        $sql = "INSERT INTO tenants (
            id, name, slug, status, plan, billing_email, settings_json,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $data['name'],
            $slug,
            $data['status'] ?? 'active',
            $data['plan'] ?? null,
            $data['billing_email'] ?? null,
            isset($data['settings']) ? json_encode($data['settings']) : null,
            $now,
            $now
        ];
        
        try {
            $this->db->insert($sql, $params);
            return $this->getTenant($id);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                throw new Exception('Tenant slug already exists', 409);
            }
            throw $e;
        }
    }
    
    /**
     * Update an existing tenant
     */
    public function updateTenant($id, $data) {
        // Check if tenant exists
        $existing = $this->getTenant($id);
        if (!$existing) {
            throw new Exception('Tenant not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        // Build update query dynamically
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        if (isset($data['slug'])) {
            $this->validateSlug($data['slug']);
            $updates[] = 'slug = ?';
            $params[] = $data['slug'];
        }
        
        if (isset($data['status'])) {
            if (!in_array($data['status'], ['active', 'suspended', 'inactive'])) {
                throw new Exception('Invalid status', 400);
            }
            $updates[] = 'status = ?';
            $params[] = $data['status'];
        }
        
        if (array_key_exists('plan', $data)) {
            $updates[] = 'plan = ?';
            $params[] = $data['plan'];
        }
        
        if (array_key_exists('billing_email', $data)) {
            if ($data['billing_email'] !== null && !filter_var($data['billing_email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid billing email', 400);
            }
            $updates[] = 'billing_email = ?';
            $params[] = $data['billing_email'];
        }
        
        if (array_key_exists('settings', $data)) {
            $updates[] = 'settings_json = ?';
            $params[] = isset($data['settings']) ? json_encode($data['settings']) : null;
        }
        
        // Always update updated_at
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        
        if (empty($updates)) {
            return $existing;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE tenants SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $params);
        
        return $this->getTenant($id);
    }
    
    /**
     * Get a single tenant by ID
     */
    public function getTenant($id) {
        $sql = "SELECT * FROM tenants WHERE id = ?";
        $tenant = $this->db->getOne($sql, [$id]);
        
        if ($tenant) {
            return $this->normalizeTenant($tenant);
        }
        
        return null;
    }
    
    /**
     * Get a tenant by slug
     */
    public function getTenantBySlug($slug) {
        $sql = "SELECT * FROM tenants WHERE slug = ?";
        $tenant = $this->db->getOne($sql, [$slug]);
        
        if ($tenant) {
            return $this->normalizeTenant($tenant);
        }
        
        return null;
    }
    
    /**
     * List all tenants
     */
    public function listTenants($filters = []) {
        $sql = "SELECT * FROM tenants";
        $params = [];
        $conditions = [];
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['plan'])) {
            $conditions[] = "plan = ?";
            $params[] = $filters['plan'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(name LIKE ? OR slug LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        // Add pagination if specified
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        $tenants = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizeTenant'], $tenants);
    }
    
    /**
     * Delete a tenant
     * WARNING: This will cascade delete all related data
     */
    public function deleteTenant($id) {
        // Check if tenant exists
        $tenant = $this->getTenant($id);
        if (!$tenant) {
            throw new Exception('Tenant not found', 404);
        }
        
        // Get counts of related resources for safety
        $stats = $this->getTenantStats($id);
        
        if ($stats['total_resources'] > 0) {
            // For safety, require explicit confirmation in the request
            // This is handled at the API level
        }
        
        $sql = "DELETE FROM tenants WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return true;
    }
    
    /**
     * Suspend a tenant (set status to suspended)
     */
    public function suspendTenant($id) {
        return $this->updateTenant($id, ['status' => 'suspended']);
    }
    
    /**
     * Activate a tenant (set status to active)
     */
    public function activateTenant($id) {
        return $this->updateTenant($id, ['status' => 'active']);
    }
    
    /**
     * Get statistics for a tenant (resource counts)
     */
    public function getTenantStats($id) {
        $stats = [
            'agents' => 0,
            'prompts' => 0,
            'vector_stores' => 0,
            'users' => 0,
            'conversations' => 0,
            'leads' => 0,
            'total_resources' => 0
        ];
        
        // Count agents
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM agents WHERE tenant_id = ?", [$id]);
        $stats['agents'] = (int)$result['count'];
        
        // Count prompts
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM prompts WHERE tenant_id = ?", [$id]);
        $stats['prompts'] = (int)$result['count'];
        
        // Count vector stores
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM vector_stores WHERE tenant_id = ?", [$id]);
        $stats['vector_stores'] = (int)$result['count'];
        
        // Count admin users
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM admin_users WHERE tenant_id = ?", [$id]);
        $stats['users'] = (int)$result['count'];
        
        // Count conversations
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM audit_conversations WHERE tenant_id = ?", [$id]);
        $stats['conversations'] = (int)$result['count'];
        
        // Count leads (if table exists)
        if ($this->db->tableExists('leads')) {
            $result = $this->db->queryOne("SELECT COUNT(*) as count FROM leads WHERE tenant_id = ?", [$id]);
            $stats['leads'] = (int)$result['count'];
        }
        
        $stats['total_resources'] = array_sum(array_values($stats)) - $stats['total_resources'];
        
        return $stats;
    }
    
    /**
     * Validate slug format
     */
    private function validateSlug($slug) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new Exception('Slug must contain only lowercase letters, numbers, and hyphens', 400);
        }
        
        if (strlen($slug) < 2 || strlen($slug) > 50) {
            throw new Exception('Slug must be between 2 and 50 characters', 400);
        }
    }
    
    /**
     * Generate slug from name
     */
    private function generateSlug($name) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness by appending number if needed
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->getTenantBySlug($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Normalize tenant data (parse JSON fields)
     */
    private function normalizeTenant($tenant) {
        if ($tenant['settings_json']) {
            $tenant['settings'] = json_decode($tenant['settings_json'], true);
        } else {
            $tenant['settings'] = null;
        }
        
        unset($tenant['settings_json']);
        
        return $tenant;
    }
    
    /**
     * Generate a UUID v4
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
