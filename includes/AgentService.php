<?php
/**
 * Agent Service - Business logic for Agent CRUD operations
 */

require_once __DIR__ . '/DB.php';

class AgentService {
    private $db;
    private $tenantId;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }
    
    /**
     * Set tenant context for tenant-scoped queries
     */
    public function setTenantId($tenantId) {
        $this->tenantId = $tenantId;
    }
    
    /**
     * Get current tenant ID
     */
    public function getTenantId() {
        return $this->tenantId;
    }
    
    /**
     * Create a new agent
     */
    public function createAgent($data) {
        // Validate required fields
        $this->validateAgentData($data, true);
        
        // Generate UUID
        $id = $this->generateUUID();
        
        // Prepare data
        $now = date('c'); // ISO 8601 format
        
        $sql = "INSERT INTO agents (
            id, name, slug, description, api_type, prompt_id, prompt_version,
            system_message, model, temperature, top_p, max_output_tokens,
            tools_json, vector_store_ids_json, max_num_results, response_format_json, is_default,
            tenant_id, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $data['name'],
            $data['slug'] ?? null,
            $data['description'] ?? null,
            $data['api_type'] ?? 'responses',
            $data['prompt_id'] ?? null,
            $data['prompt_version'] ?? null,
            $data['system_message'] ?? null,
            $data['model'] ?? null,
            isset($data['temperature']) ? (float)$data['temperature'] : null,
            isset($data['top_p']) ? (float)$data['top_p'] : null,
            isset($data['max_output_tokens']) ? (int)$data['max_output_tokens'] : null,
            isset($data['tools']) ? json_encode($data['tools']) : null,
            isset($data['vector_store_ids']) ? json_encode($data['vector_store_ids']) : null,
            isset($data['max_num_results']) ? (int)$data['max_num_results'] : null,
            isset($data['response_format']) ? json_encode($data['response_format']) : null,
            isset($data['is_default']) ? (int)(bool)$data['is_default'] : 0,
            $data['tenant_id'] ?? $this->tenantId,
            $now,
            $now
        ];
        
        try {
            $this->db->beginTransaction();
            
            // If this agent is marked as default, unset previous defaults for this tenant
            if (!empty($data['is_default'])) {
                $tenantFilter = $this->tenantId ? " WHERE tenant_id = ?" : " WHERE tenant_id IS NULL";
                $updateParams = $this->tenantId ? [$this->tenantId] : [];
                $this->db->execute("UPDATE agents SET is_default = 0" . $tenantFilter, $updateParams);
            }
            
            $this->db->insert($sql, $params);
            $this->db->commit();
            
            return $this->getAgent($id);
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update an existing agent
     */
    public function updateAgent($id, $data) {
        // Check if agent exists
        $existing = $this->getAgent($id);
        if (!$existing) {
            throw new Exception('Agent not found', 404);
        }
        
        // Add agent ID to data for validation
        $data['id'] = $id;
        
        // Validate data
        $this->validateAgentData($data, false);
        
        $updates = [];
        $params = [];
        
        // Build update query dynamically
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (array_key_exists('slug', $data)) {
            $updates[] = 'slug = ?';
            $params[] = $data['slug'];
        }
        if (array_key_exists('description', $data)) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }
        if (isset($data['api_type'])) {
            $updates[] = 'api_type = ?';
            $params[] = $data['api_type'];
        }
        if (array_key_exists('prompt_id', $data)) {
            $updates[] = 'prompt_id = ?';
            $params[] = $data['prompt_id'];
        }
        if (array_key_exists('prompt_version', $data)) {
            $updates[] = 'prompt_version = ?';
            $params[] = $data['prompt_version'];
        }
        if (array_key_exists('system_message', $data)) {
            $updates[] = 'system_message = ?';
            $params[] = $data['system_message'];
        }
        if (array_key_exists('model', $data)) {
            $updates[] = 'model = ?';
            $params[] = $data['model'];
        }
        if (array_key_exists('temperature', $data)) {
            $updates[] = 'temperature = ?';
            $params[] = isset($data['temperature']) ? (float)$data['temperature'] : null;
        }
        if (array_key_exists('top_p', $data)) {
            $updates[] = 'top_p = ?';
            $params[] = isset($data['top_p']) ? (float)$data['top_p'] : null;
        }
        if (array_key_exists('max_output_tokens', $data)) {
            $updates[] = 'max_output_tokens = ?';
            $params[] = isset($data['max_output_tokens']) ? (int)$data['max_output_tokens'] : null;
        }
        if (array_key_exists('tools', $data)) {
            $updates[] = 'tools_json = ?';
            $params[] = isset($data['tools']) ? json_encode($data['tools']) : null;
        }
        if (array_key_exists('vector_store_ids', $data)) {
            $updates[] = 'vector_store_ids_json = ?';
            $params[] = isset($data['vector_store_ids']) ? json_encode($data['vector_store_ids']) : null;
        }
        if (array_key_exists('max_num_results', $data)) {
            $updates[] = 'max_num_results = ?';
            $params[] = isset($data['max_num_results']) ? (int)$data['max_num_results'] : null;
        }
        if (array_key_exists('response_format', $data)) {
            $updates[] = 'response_format_json = ?';
            $params[] = isset($data['response_format']) ? json_encode($data['response_format']) : null;
        }
        
        // Always update updated_at
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        
        if (empty($updates)) {
            // No fields to update
            return $existing;
        }
        
        $params[] = $id;
        
        try {
            $this->db->beginTransaction();
            
            // Handle is_default separately to ensure atomicity
            if (isset($data['is_default'])) {
                if ($data['is_default']) {
                    // Unset all defaults first for this tenant
                    $tenantFilter = $this->tenantId ? " WHERE tenant_id = ?" : " WHERE tenant_id IS NULL";
                    $updateParams = $this->tenantId ? [$this->tenantId] : [];
                    $this->db->execute("UPDATE agents SET is_default = 0" . $tenantFilter, $updateParams);
                    $updates[] = 'is_default = 1';
                } else {
                    $updates[] = 'is_default = 0';
                }
            }
            
            $sql = "UPDATE agents SET " . implode(', ', $updates) . " WHERE id = ?";
            $this->db->execute($sql, $params);
            
            $this->db->commit();
            
            return $this->getAgent($id);
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get a single agent by ID
     */
    public function getAgent($id) {
        $sql = "SELECT * FROM agents WHERE id = ?";
        $params = [$id];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        $agent = $this->db->getOne($sql, $params);
        
        if ($agent) {
            return $this->normalizeAgent($agent);
        }
        
        return null;
    }
    
    /**
     * Get a single agent by slug
     */
    public function getAgentBySlug($slug) {
        if (empty($slug)) {
            return null;
        }
        
        $sql = "SELECT * FROM agents WHERE slug = ?";
        $params = [$slug];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        $agent = $this->db->getOne($sql, $params);
        
        if ($agent) {
            return $this->normalizeAgent($agent);
        }
        
        return null;
    }
    
    /**
     * List all agents
     */
    public function listAgents($filters = []) {
        $sql = "SELECT * FROM agents";
        $params = [];
        $conditions = [];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $conditions[] = "tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        if (!empty($filters['name'])) {
            $conditions[] = "name LIKE ?";
            $params[] = '%' . $filters['name'] . '%';
        }
        
        if (!empty($filters['api_type'])) {
            $conditions[] = "api_type = ?";
            $params[] = $filters['api_type'];
        }
        
        if (isset($filters['is_default'])) {
            $conditions[] = "is_default = ?";
            $params[] = (int)(bool)$filters['is_default'];
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $agents = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizeAgent'], $agents);
    }
    
    /**
     * Get the default agent
     */
    public function getDefaultAgent() {
        $sql = "SELECT * FROM agents WHERE is_default = 1";
        $params = [];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        $sql .= " LIMIT 1";
        
        $agent = $this->db->getOne($sql, $params);
        
        if ($agent) {
            return $this->normalizeAgent($agent);
        }
        
        return null;
    }
    
    /**
     * Set an agent as default (unsets all others)
     */
    public function setDefaultAgent($id) {
        // Check if agent exists
        $agent = $this->getAgent($id);
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }
        
        try {
            $this->db->beginTransaction();
            
            // Unset all defaults for this tenant
            $tenantFilter = $this->tenantId ? " WHERE tenant_id = ?" : " WHERE tenant_id IS NULL";
            $updateParams = $this->tenantId ? [$this->tenantId] : [];
            $this->db->execute("UPDATE agents SET is_default = 0" . $tenantFilter, $updateParams);
            
            // Set this one as default
            $this->db->execute("UPDATE agents SET is_default = 1, updated_at = ? WHERE id = ?", [
                date('c'),
                $id
            ]);
            
            $this->db->commit();
            
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Delete an agent
     */
    public function deleteAgent($id) {
        $sql = "DELETE FROM agents WHERE id = ?";
        $rowCount = $this->db->execute($sql, [$id]);
        
        if ($rowCount === 0) {
            throw new Exception('Agent not found', 404);
        }
        
        return true;
    }
    
    /**
     * Validate agent data
     */
    private function validateAgentData($data, $isCreate = false) {
        if ($isCreate) {
            // Name is required for create
            if (empty($data['name']) || !is_string($data['name'])) {
                throw new Exception('Agent name is required', 400);
            }
        }
        
        // Validate name if provided
        if (isset($data['name'])) {
            if (!is_string($data['name']) || trim($data['name']) === '') {
                throw new Exception('Agent name must be a non-empty string', 400);
            }
            if (strlen($data['name']) > 255) {
                throw new Exception('Agent name is too long (max 255 characters)', 400);
            }
        }
        
        // Validate slug if provided
        if (isset($data['slug']) && $data['slug'] !== null && $data['slug'] !== '') {
            // Use SecurityValidator for slug validation
            require_once __DIR__ . '/SecurityValidator.php';
            try {
                SecurityValidator::validateAgentSlug($data['slug']);
            } catch (Exception $e) {
                throw new Exception('Invalid slug format. Use only lowercase letters, numbers, and hyphens (1-64 characters)', 400);
            }
            
            // Check for slug uniqueness
            $checkSql = "SELECT COUNT(*) as count FROM agents WHERE slug = ?";
            $checkParams = [$data['slug']];
            
            // If updating, exclude the current agent
            if (!$isCreate && isset($data['id'])) {
                $checkSql .= " AND id != ?";
                $checkParams[] = $data['id'];
            }
            
            // Add tenant filter if tenant context is set
            if ($this->tenantId !== null) {
                $checkSql .= " AND tenant_id = ?";
                $checkParams[] = $this->tenantId;
            }
            
            $result = $this->db->getOne($checkSql, $checkParams);
            if ($result && $result['count'] > 0) {
                throw new Exception('This slug is already in use', 400);
            }
        }
        
        // Validate api_type
        if (isset($data['api_type'])) {
            if (!in_array($data['api_type'], ['responses', 'chat'])) {
                throw new Exception('Invalid api_type: must be "responses" or "chat"', 400);
            }
        }
        
        // Validate temperature
        if (isset($data['temperature']) && $data['temperature'] !== null) {
            $temp = (float)$data['temperature'];
            if ($temp < 0 || $temp > 2) {
                throw new Exception('Temperature must be between 0 and 2', 400);
            }
        }
        
        // Validate top_p
        if (isset($data['top_p']) && $data['top_p'] !== null) {
            $topP = (float)$data['top_p'];
            if ($topP < 0 || $topP > 1) {
                throw new Exception('top_p must be between 0 and 1', 400);
            }
        }
        
        // Validate max_num_results
        if (isset($data['max_num_results']) && $data['max_num_results'] !== null) {
            $maxResults = (int)$data['max_num_results'];
            if ($maxResults < 1 || $maxResults > 200) {
                throw new Exception('max_num_results must be between 1 and 200', 400);
            }
        }
        
        // Validate vector_store_ids
        if (isset($data['vector_store_ids'])) {
            if (!is_array($data['vector_store_ids'])) {
                throw new Exception('vector_store_ids must be an array', 400);
            }
            foreach ($data['vector_store_ids'] as $id) {
                if (!is_string($id) || trim($id) === '') {
                    throw new Exception('vector_store_ids must contain non-empty strings', 400);
                }
            }
        }
        
        // Validate tools
        if (isset($data['tools'])) {
            if (!is_array($data['tools'])) {
                throw new Exception('tools must be an array', 400);
            }
        }
        
        // Validate response_format
        if (isset($data['response_format'])) {
            if (!is_array($data['response_format'])) {
                throw new Exception('response_format must be an array', 400);
            }
            
            // Basic validation of response_format structure
            if (isset($data['response_format']['type'])) {
                $validTypes = ['text', 'json_object', 'json_schema'];
                if (!in_array($data['response_format']['type'], $validTypes, true)) {
                    throw new Exception('response_format type must be one of: ' . implode(', ', $validTypes), 400);
                }
                
                // If type is json_schema, validate required fields
                if ($data['response_format']['type'] === 'json_schema') {
                    if (!isset($data['response_format']['json_schema'])) {
                        throw new Exception('response_format with type json_schema must include json_schema field', 400);
                    }
                    if (!isset($data['response_format']['json_schema']['name'])) {
                        throw new Exception('response_format json_schema must include name field', 400);
                    }
                    if (!isset($data['response_format']['json_schema']['schema'])) {
                        throw new Exception('response_format json_schema must include schema field', 400);
                    }
                }
            }
        }
    }
    
    /**
     * Normalize agent data (parse JSON fields)
     */
    private function normalizeAgent($agent) {
        if ($agent['tools_json']) {
            $agent['tools'] = json_decode($agent['tools_json'], true);
        } else {
            $agent['tools'] = null;
        }
        
        if ($agent['vector_store_ids_json']) {
            $agent['vector_store_ids'] = json_decode($agent['vector_store_ids_json'], true);
        } else {
            $agent['vector_store_ids'] = null;
        }
        
        if (isset($agent['response_format_json']) && $agent['response_format_json']) {
            $agent['response_format'] = json_decode($agent['response_format_json'], true);
        } else {
            $agent['response_format'] = null;
        }
        
        // Parse whitelabel JSON fields
        if (isset($agent['allowed_origins_json']) && $agent['allowed_origins_json']) {
            $agent['allowed_origins'] = json_decode($agent['allowed_origins_json'], true);
        } else {
            $agent['allowed_origins'] = null;
        }
        
        if (isset($agent['wl_theme_json']) && $agent['wl_theme_json']) {
            $agent['wl_theme'] = json_decode($agent['wl_theme_json'], true);
        } else {
            $agent['wl_theme'] = null;
        }
        
        // Convert boolean fields
        $agent['is_default'] = (bool)$agent['is_default'];
        if (isset($agent['whitelabel_enabled'])) {
            $agent['whitelabel_enabled'] = (bool)$agent['whitelabel_enabled'];
        }
        if (isset($agent['wl_require_signed_requests'])) {
            $agent['wl_require_signed_requests'] = (bool)$agent['wl_require_signed_requests'];
        }
        if (isset($agent['wl_enable_file_upload'])) {
            $agent['wl_enable_file_upload'] = (bool)$agent['wl_enable_file_upload'];
        }
        
        // Remove JSON fields from response
        unset($agent['tools_json']);
        unset($agent['vector_store_ids_json']);
        unset($agent['response_format_json']);
        unset($agent['allowed_origins_json']);
        unset($agent['wl_theme_json']);
        
        return $agent;
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
    
    // ============================================================
    // Channel Management Methods
    // ============================================================
    
    /**
     * Get channel configuration for an agent
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name (e.g., 'whatsapp')
     * @return array|null Channel configuration or null if not found
     */
    public function getAgentChannel($agentId, $channel) {
        $sql = "SELECT * FROM agent_channels WHERE agent_id = ? AND channel = ?";
        $channelConfig = $this->db->getOne($sql, [$agentId, $channel]);
        
        if (!$channelConfig) {
            return null;
        }
        
        return $this->normalizeChannel($channelConfig);
    }
    
    /**
     * List all channels for an agent
     * 
     * @param string $agentId Agent ID
     * @return array Array of channel configurations
     */
    public function listAgentChannels($agentId) {
        $sql = "SELECT * FROM agent_channels WHERE agent_id = ? ORDER BY channel";
        $channels = $this->db->query($sql, [$agentId]);
        
        return array_map([$this, 'normalizeChannel'], $channels);
    }
    
    /**
     * Create or update channel configuration for an agent
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name
     * @param array $config Channel configuration
     * @return array Created/updated channel
     */
    public function upsertAgentChannel($agentId, $channel, $config) {
        // Validate agent exists
        $agent = $this->getAgent($agentId);
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }
        
        // Validate channel
        if (!in_array($channel, ['whatsapp'])) {
            throw new Exception('Invalid channel type', 400);
        }
        
        // Validate channel configuration
        $this->validateChannelConfig($channel, $config);
        
        // Check if channel already exists
        $existing = $this->getAgentChannel($agentId, $channel);
        
        $now = date('c');
        
        if ($existing) {
            // Update existing channel
            $sql = "UPDATE agent_channels 
                    SET enabled = ?, config_json = ?, updated_at = ?
                    WHERE agent_id = ? AND channel = ?";
            
            $this->db->execute($sql, [
                isset($config['enabled']) ? (int)(bool)$config['enabled'] : $existing['enabled'],
                json_encode($config),
                $now,
                $agentId,
                $channel
            ]);
        } else {
            // Create new channel
            $id = $this->generateUUID();
            $sql = "INSERT INTO agent_channels (id, agent_id, channel, enabled, config_json, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->insert($sql, [
                $id,
                $agentId,
                $channel,
                isset($config['enabled']) ? (int)(bool)$config['enabled'] : 0,
                json_encode($config),
                $now,
                $now
            ]);
        }
        
        return $this->getAgentChannel($agentId, $channel);
    }
    
    /**
     * Delete a channel configuration
     * 
     * @param string $agentId Agent ID
     * @param string $channel Channel name
     * @return bool True if deleted
     */
    public function deleteAgentChannel($agentId, $channel) {
        $sql = "DELETE FROM agent_channels WHERE agent_id = ? AND channel = ?";
        $rowCount = $this->db->execute($sql, [$agentId, $channel]);
        
        if ($rowCount === 0) {
            throw new Exception('Channel configuration not found', 404);
        }
        
        return true;
    }
    
    /**
     * Validate channel configuration
     */
    private function validateChannelConfig($channel, $config) {
        if ($channel === 'whatsapp') {
            // Required fields for WhatsApp
            if (empty($config['zapi_instance_id'])) {
                throw new Exception('zapi_instance_id is required for WhatsApp channel', 400);
            }
            if (empty($config['zapi_token'])) {
                throw new Exception('zapi_token is required for WhatsApp channel', 400);
            }
            
            // Validate business number format if provided
            if (!empty($config['whatsapp_business_number'])) {
                $number = $config['whatsapp_business_number'];
                if (!preg_match('/^\+\d{10,15}$/', $number)) {
                    throw new Exception('whatsapp_business_number must be in E.164 format (e.g., +5511999999999)', 400);
                }
            }
            
            // Validate numeric fields
            if (isset($config['zapi_timeout_ms']) && (!is_numeric($config['zapi_timeout_ms']) || $config['zapi_timeout_ms'] < 1000)) {
                throw new Exception('zapi_timeout_ms must be at least 1000', 400);
            }
            
            if (isset($config['reply_chunk_size']) && (!is_numeric($config['reply_chunk_size']) || $config['reply_chunk_size'] < 100)) {
                throw new Exception('reply_chunk_size must be at least 100', 400);
            }
            
            if (isset($config['max_media_size_bytes']) && (!is_numeric($config['max_media_size_bytes']) || $config['max_media_size_bytes'] < 1)) {
                throw new Exception('max_media_size_bytes must be greater than 0', 400);
            }
        }
    }
    
    /**
     * Normalize channel data for API responses
     */
    private function normalizeChannel($channel) {
        if (!$channel) {
            return null;
        }
        
        $config = json_decode($channel['config_json'] ?? '{}', true);
        
        return [
            'id' => $channel['id'],
            'agent_id' => $channel['agent_id'],
            'channel' => $channel['channel'],
            'enabled' => (bool)$channel['enabled'],
            'config' => $config,
            'created_at' => $channel['created_at'],
            'updated_at' => $channel['updated_at']
        ];
    }
    
    // ============================================================
    // Whitelabel Publishing Methods
    // ============================================================
    
    /**
     * Get agent by public ID
     * 
     * @param string $publicId Agent public ID
     * @return array|null Agent data or null if not found
     */
    public function getAgentByPublicId($publicId) {
        $sql = "SELECT * FROM agents WHERE agent_public_id = ? AND whitelabel_enabled = 1";
        $agent = $this->db->getOne($sql, [$publicId]);
        
        if ($agent) {
            return $this->normalizeAgent($agent);
        }
        
        return null;
    }
    
    /**
     * Get agent by vanity path
     * 
     * @param string $vanityPath Vanity path
     * @return array|null Agent data or null if not found
     */
    public function getAgentByVanityPath($vanityPath) {
        $sql = "SELECT * FROM agents WHERE vanity_path = ? AND whitelabel_enabled = 1";
        $agent = $this->db->getOne($sql, [$vanityPath]);
        
        if ($agent) {
            return $this->normalizeAgent($agent);
        }
        
        return null;
    }
    
    /**
     * Get agent by custom domain
     * 
     * @param string $customDomain Custom domain
     * @return array|null Agent data or null if not found
     */
    public function getAgentByCustomDomain($customDomain) {
        $sql = "SELECT * FROM agents WHERE custom_domain = ? AND whitelabel_enabled = 1";
        $agent = $this->db->getOne($sql, [$customDomain]);
        
        if ($agent) {
            return $this->normalizeAgent($agent);
        }
        
        return null;
    }
    
    /**
     * Enable whitelabel for an agent
     * 
     * @param string $id Agent ID
     * @param array $whitelabelConfig Whitelabel configuration
     * @return array Updated agent
     */
    public function enableWhitelabel($id, $whitelabelConfig = []) {
        require_once __DIR__ . '/WhitelabelTokenService.php';
        
        $agent = $this->getAgent($id);
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }
        
        // Generate public ID if not provided
        $publicId = $whitelabelConfig['agent_public_id'] ?? WhitelabelTokenService::generatePublicId();
        
        // Generate HMAC secret if not provided
        $hmacSecret = $whitelabelConfig['wl_hmac_secret'] ?? WhitelabelTokenService::generateHmacSecret();
        
        // Build update array
        $updates = ['whitelabel_enabled = 1'];
        $params = [];
        
        if (!$agent['agent_public_id']) {
            $updates[] = 'agent_public_id = ?';
            $params[] = $publicId;
        }
        
        if (!$agent['wl_hmac_secret']) {
            $updates[] = 'wl_hmac_secret = ?';
            $params[] = $hmacSecret;
        }
        
        // Apply other whitelabel config
        if (isset($whitelabelConfig['wl_title'])) {
            $updates[] = 'wl_title = ?';
            $params[] = $whitelabelConfig['wl_title'];
        }
        
        if (isset($whitelabelConfig['wl_logo_url'])) {
            $updates[] = 'wl_logo_url = ?';
            $params[] = $whitelabelConfig['wl_logo_url'];
        }
        
        if (isset($whitelabelConfig['wl_theme'])) {
            $updates[] = 'wl_theme_json = ?';
            $params[] = json_encode($whitelabelConfig['wl_theme']);
        }
        
        if (isset($whitelabelConfig['wl_welcome_message'])) {
            $updates[] = 'wl_welcome_message = ?';
            $params[] = $whitelabelConfig['wl_welcome_message'];
        }
        
        if (isset($whitelabelConfig['wl_placeholder'])) {
            $updates[] = 'wl_placeholder = ?';
            $params[] = $whitelabelConfig['wl_placeholder'];
        }
        
        if (isset($whitelabelConfig['wl_enable_file_upload'])) {
            $updates[] = 'wl_enable_file_upload = ?';
            $params[] = (int)(bool)$whitelabelConfig['wl_enable_file_upload'];
        }
        
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        $params[] = $id;
        
        $sql = "UPDATE agents SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $params);
        
        return $this->getAgent($id);
    }
    
    /**
     * Disable whitelabel for an agent
     * 
     * @param string $id Agent ID
     * @return array Updated agent
     */
    public function disableWhitelabel($id) {
        $agent = $this->getAgent($id);
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }
        
        $sql = "UPDATE agents SET whitelabel_enabled = 0, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [date('c'), $id]);
        
        return $this->getAgent($id);
    }
    
    /**
     * Rotate HMAC secret for an agent
     * 
     * @param string $id Agent ID
     * @return array Updated agent with new secret
     */
    public function rotateHmacSecret($id) {
        require_once __DIR__ . '/WhitelabelTokenService.php';
        
        $agent = $this->getAgent($id);
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }
        
        $newSecret = WhitelabelTokenService::generateHmacSecret();
        
        $sql = "UPDATE agents SET wl_hmac_secret = ?, updated_at = ? WHERE id = ?";
        $this->db->execute($sql, [$newSecret, date('c'), $id]);
        
        return $this->getAgent($id);
    }
    
    /**
     * Update whitelabel configuration
     * 
     * @param string $id Agent ID
     * @param array $config Whitelabel configuration
     * @return array Updated agent
     */
    public function updateWhitelabelConfig($id, $config) {
        $agent = $this->getAgent($id);
        if (!$agent) {
            throw new Exception('Agent not found', 404);
        }
        
        $updates = [];
        $params = [];
        $vanityPathProvided = false;

        if (array_key_exists('vanity_path', $config)) {
            $vanityPathProvided = true;
            $rawVanityPath = $config['vanity_path'];

            if ($rawVanityPath === null || (is_string($rawVanityPath) && trim($rawVanityPath) === '')) {
                $updates[] = 'vanity_path = ?';
                $params[] = null;
            } else {
                $sanitizedVanityPath = $this->sanitizeVanityPath($rawVanityPath);

                if ($sanitizedVanityPath === '' || !preg_match('/^[a-z0-9-]{3,64}$/', $sanitizedVanityPath)) {
                    throw new Exception('Invalid vanity_path: must match /^[a-z0-9-]{3,64}$/', 400);
                }

                $updates[] = 'vanity_path = ?';
                $params[] = $sanitizedVanityPath;
            }
        }

        // Whitelabel branding fields
        $stringFields = [
            'wl_title', 'wl_logo_url', 'wl_welcome_message', 'wl_placeholder',
            'wl_legal_disclaimer_md', 'wl_footer_brand_md', 'custom_domain'
        ];
        
        foreach ($stringFields as $field) {
            if (array_key_exists($field, $config)) {
                $updates[] = "$field = ?";
                $params[] = $config[$field];
            }
        }
        
        // Boolean fields
        $boolFields = [
            'wl_require_signed_requests', 'wl_enable_file_upload'
        ];
        
        foreach ($boolFields as $field) {
            if (isset($config[$field])) {
                $updates[] = "$field = ?";
                $params[] = (int)(bool)$config[$field];
            }
        }
        
        // Integer fields
        $intFields = [
            'wl_token_ttl_seconds', 'wl_rate_limit_requests', 'wl_rate_limit_window_seconds'
        ];
        
        foreach ($intFields as $field) {
            if (isset($config[$field])) {
                $updates[] = "$field = ?";
                $params[] = (int)$config[$field];
            }
        }
        
        // JSON fields
        if (isset($config['wl_theme'])) {
            $updates[] = 'wl_theme_json = ?';
            $params[] = json_encode($config['wl_theme']);
        }
        
        if (isset($config['allowed_origins'])) {
            $updates[] = 'allowed_origins_json = ?';
            $params[] = json_encode($config['allowed_origins']);
        }
        
        if (empty($updates)) {
            return $agent;
        }
        
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        $params[] = $id;
        
        $sql = "UPDATE agents SET " . implode(', ', $updates) . " WHERE id = ?";

        try {
            $this->db->execute($sql, $params);
        } catch (Exception $e) {
            if ($vanityPathProvided && (int)$e->getCode() === 409) {
                throw new Exception('Vanity path already in use', 409, $e);
            }

            throw $e;
        }

        return $this->getAgent($id);
    }

    private function sanitizeVanityPath($value) {
        $slug = trim((string)$value);

        if ($slug === '') {
            return '';
        }

        $slug = preg_replace_callback('/[A-Z]/', function ($matches) {
            return strtolower($matches[0]);
        }, $slug);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
    
    /**
     * Get sanitized public configuration for whitelabel page
     * Never returns secrets or internal IDs
     * 
     * @param string $publicId Agent public ID
     * @return array|null Public configuration or null if not found
     */
    public function getPublicWhitelabelConfig($publicId) {
        $agent = $this->getAgentByPublicId($publicId);
        
        if (!$agent) {
            return null;
        }
        
        return [
            'title' => $agent['wl_title'] ?? $agent['name'],
            'logo_url' => $agent['wl_logo_url'] ?? null,
            'theme' => $agent['wl_theme'] ?? [],
            'welcome_message' => $agent['wl_welcome_message'] ?? 'Hello! How can I help you today?',
            'placeholder' => $agent['wl_placeholder'] ?? 'Type your message...',
            'enable_file_upload' => $agent['wl_enable_file_upload'] ?? false,
            'legal_disclaimer_md' => $agent['wl_legal_disclaimer_md'] ?? null,
            'footer_brand_md' => $agent['wl_footer_brand_md'] ?? null,
            'api_type' => $agent['api_type'] ?? 'responses'
        ];
    }
}
