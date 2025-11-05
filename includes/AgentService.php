<?php
/**
 * Agent Service - Business logic for Agent CRUD operations
 */

require_once __DIR__ . '/DB.php';

class AgentService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
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
            id, name, description, api_type, prompt_id, prompt_version,
            system_message, model, temperature, top_p, max_output_tokens,
            tools_json, vector_store_ids_json, max_num_results, response_format_json, is_default,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $data['name'],
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
            $now,
            $now
        ];
        
        try {
            $this->db->beginTransaction();
            
            // If this agent is marked as default, unset previous defaults
            if (!empty($data['is_default'])) {
                $this->db->execute("UPDATE agents SET is_default = 0");
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
        // Validate data
        $this->validateAgentData($data, false);
        
        // Check if agent exists
        $existing = $this->getAgent($id);
        if (!$existing) {
            throw new Exception('Agent not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        // Build update query dynamically
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
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
                    // Unset all defaults first
                    $this->db->execute("UPDATE agents SET is_default = 0");
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
        $agent = $this->db->getOne($sql, [$id]);
        
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
        $sql = "SELECT * FROM agents WHERE is_default = 1 LIMIT 1";
        $agent = $this->db->getOne($sql);
        
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
            
            // Unset all defaults
            $this->db->execute("UPDATE agents SET is_default = 0");
            
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
        
        // Convert is_default to boolean
        $agent['is_default'] = (bool)$agent['is_default'];
        
        // Remove JSON fields from response
        unset($agent['tools_json']);
        unset($agent['vector_store_ids_json']);
        unset($agent['response_format_json']);
        
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
}
