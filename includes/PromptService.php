<?php
/**
 * Prompt Service - Manages OpenAI prompts with local DB persistence
 * Provides CRUD operations and OpenAI API integration
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/OpenAIAdminClient.php';

class PromptService {
    private $db;
    private $openaiClient;
    private $tenantId;
    
    public function __construct($db, $openaiClient = null, $tenantId = null) {
        $this->db = $db;
        $this->openaiClient = $openaiClient;
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
     * Generate UUID v4
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Create a new prompt
     */
    public function createPrompt($data) {
        $id = $this->generateUUID();
        $now = date('c');
        
        // Validate required fields
        if (empty($data['name'])) {
            throw new Exception('Prompt name is required');
        }
        
        // Try to create prompt on OpenAI if client available
        $openaiPromptId = null;
        if ($this->openaiClient && !empty($data['content'])) {
            try {
                $definition = [
                    'type' => 'text',
                    'text' => $data['content']
                ];
                
                $result = $this->openaiClient->createPrompt(
                    $data['name'],
                    $definition,
                    $data['description'] ?? ''
                );
                
                if ($result && isset($result['id'])) {
                    $openaiPromptId = $result['id'];
                }
            } catch (Exception $e) {
                // Continue with local record even if OpenAI creation fails
                error_log('Failed to create prompt on OpenAI: ' . $e->getMessage());
            }
        }
        
        $sql = "INSERT INTO prompts (id, name, openai_prompt_id, description, meta_json, tenant_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $metaJson = null;
        if (isset($data['content'])) {
            $metaJson = json_encode(['content' => $data['content']]);
        }
        
        $params = [
            $id,
            $data['name'],
            $openaiPromptId,
            $data['description'] ?? null,
            $metaJson,
            $data['tenant_id'] ?? $this->tenantId,
            $now,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getPrompt($id);
    }
    
    /**
     * Get a prompt by ID
     */
    public function getPrompt($id) {
        $sql = "SELECT * FROM prompts WHERE id = ?";
        $params = [$id];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        $result = $this->db->query($sql, $params);
        
        if (empty($result)) {
            return null;
        }
        
        return $this->normalizePrompt($result[0]);
    }
    
    /**
     * Get a prompt by OpenAI ID
     */
    public function getPromptByOpenAIId($openaiId) {
        $sql = "SELECT * FROM prompts WHERE openai_prompt_id = ?";
        $params = [$openaiId];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        $result = $this->db->query($sql, $params);
        
        if (empty($result)) {
            return null;
        }
        
        return $this->normalizePrompt($result[0]);
    }
    
    /**
     * List all prompts
     */
    public function listPrompts($filters = []) {
        $sql = "SELECT * FROM prompts";
        $params = [];
        $where = [];
        
        // Add tenant filter if tenant context is set
        if ($this->tenantId !== null) {
            $where[] = "tenant_id = ?";
            $params[] = $this->tenantId;
        }
        
        if (!empty($filters['name'])) {
            $where[] = "name LIKE ?";
            $params[] = '%' . $filters['name'] . '%';
        }
        
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        if (isset($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
        }
        
        $results = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizePrompt'], $results);
    }
    
    /**
     * Update a prompt
     */
    public function updatePrompt($id, $data) {
        $existing = $this->getPrompt($id);
        if (!$existing) {
            throw new Exception('Prompt not found');
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }
        
        if (isset($data['meta_json'])) {
            $updates[] = 'meta_json = ?';
            $params[] = is_array($data['meta_json']) ? json_encode($data['meta_json']) : $data['meta_json'];
        }
        
        if (empty($updates)) {
            return $existing;
        }
        
        $updates[] = 'updated_at = ?';
        $params[] = date('c');
        $params[] = $id;
        
        $sql = "UPDATE prompts SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $params);
        
        return $this->getPrompt($id);
    }
    
    /**
     * Delete a prompt
     */
    public function deletePrompt($id) {
        $prompt = $this->getPrompt($id);
        if (!$prompt) {
            throw new Exception('Prompt not found');
        }
        
        // Try to delete from OpenAI if ID exists
        if ($this->openaiClient && !empty($prompt['openai_prompt_id'])) {
            try {
                $this->openaiClient->deletePrompt($prompt['openai_prompt_id']);
            } catch (Exception $e) {
                error_log('Failed to delete prompt from OpenAI: ' . $e->getMessage());
            }
        }
        
        $sql = "DELETE FROM prompts WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return true;
    }
    
    /**
     * Create a prompt version
     */
    public function createPromptVersion($promptId, $data) {
        $prompt = $this->getPrompt($promptId);
        if (!$prompt) {
            throw new Exception('Prompt not found');
        }
        
        $id = $this->generateUUID();
        $now = date('c');
        
        // Validate version
        if (empty($data['version'])) {
            throw new Exception('Version is required');
        }
        
        $openaiVersionId = null;
        
        // Try to create version on OpenAI if prompt has OpenAI ID
        if ($this->openaiClient && !empty($prompt['openai_prompt_id']) && !empty($data['content'])) {
            try {
                $definition = [
                    'type' => 'text',
                    'text' => $data['content']
                ];
                
                $result = $this->openaiClient->createPromptVersion(
                    $prompt['openai_prompt_id'],
                    $definition
                );
                
                if ($result && isset($result['id'])) {
                    $openaiVersionId = $result['id'];
                }
            } catch (Exception $e) {
                error_log('Failed to create prompt version on OpenAI: ' . $e->getMessage());
            }
        }
        
        $sql = "INSERT INTO prompt_versions (id, prompt_id, version, openai_version_id, summary, created_at)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $promptId,
            $data['version'],
            $openaiVersionId,
            $data['summary'] ?? null,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getPromptVersion($id);
    }
    
    /**
     * Get a prompt version by ID
     */
    public function getPromptVersion($id) {
        $sql = "SELECT * FROM prompt_versions WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        
        if (empty($result)) {
            return null;
        }
        
        return $result[0];
    }
    
    /**
     * List versions for a prompt
     */
    public function listPromptVersions($promptId, $filters = []) {
        $sql = "SELECT * FROM prompt_versions WHERE prompt_id = ? ORDER BY created_at DESC";
        $params = [$promptId];
        
        if (isset($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
        }
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Sync prompts from OpenAI
     */
    public function syncPromptsFromOpenAI() {
        if (!$this->openaiClient) {
            throw new Exception('OpenAI client not configured');
        }
        
        $result = $this->openaiClient->listPrompts(100);
        $synced = 0;
        
        if (isset($result['data'])) {
            foreach ($result['data'] as $openaiPrompt) {
                // Check if we already have this prompt
                $existing = $this->getPromptByOpenAIId($openaiPrompt['id']);
                
                if (!$existing) {
                    // Create new local record
                    $this->createPrompt([
                        'name' => $openaiPrompt['name'] ?? $openaiPrompt['id'],
                        'openai_prompt_id' => $openaiPrompt['id'],
                        'description' => $openaiPrompt['description'] ?? '',
                    ]);
                    $synced++;
                }
            }
        }
        
        return $synced;
    }
    
    /**
     * Normalize prompt data
     */
    private function normalizePrompt($prompt) {
        if (isset($prompt['meta_json']) && !empty($prompt['meta_json'])) {
            $meta = json_decode($prompt['meta_json'], true);
            if ($meta) {
                $prompt['meta'] = $meta;
            }
        }
        
        return $prompt;
    }
}
