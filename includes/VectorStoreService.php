<?php
/**
 * Vector Store Service - Manages vector stores and files with OpenAI integration
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/OpenAIAdminClient.php';

class VectorStoreService {
    private $db;
    private $openaiClient;
    
    public function __construct($db, $openaiClient = null) {
        $this->db = $db;
        $this->openaiClient = $openaiClient;
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
     * Create a new vector store
     */
    public function createVectorStore($data) {
        $id = $this->generateUUID();
        $now = date('c');
        
        if (empty($data['name'])) {
            throw new Exception('Vector store name is required');
        }
        
        $openaiStoreId = null;
        
        // Try to create on OpenAI if client available
        if ($this->openaiClient) {
            try {
                $metadata = $data['metadata'] ?? [];
                $result = $this->openaiClient->createVectorStore($data['name'], $metadata);
                
                if ($result && isset($result['id'])) {
                    $openaiStoreId = $result['id'];
                }
            } catch (Exception $e) {
                error_log('Failed to create vector store on OpenAI: ' . $e->getMessage());
            }
        }
        
        $sql = "INSERT INTO vector_stores (id, name, openai_store_id, status, meta_json, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $metaJson = null;
        if (isset($data['metadata'])) {
            $metaJson = json_encode($data['metadata']);
        }
        
        $params = [
            $id,
            $data['name'],
            $openaiStoreId,
            'ready',
            $metaJson,
            $now,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getVectorStore($id);
    }
    
    /**
     * Get a vector store by ID
     */
    public function getVectorStore($id) {
        $sql = "SELECT * FROM vector_stores WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        
        if (empty($result)) {
            return null;
        }
        
        return $this->normalizeVectorStore($result[0]);
    }
    
    /**
     * Get vector store by OpenAI ID
     */
    public function getVectorStoreByOpenAIId($openaiId) {
        $sql = "SELECT * FROM vector_stores WHERE openai_store_id = ?";
        $result = $this->db->query($sql, [$openaiId]);
        
        if (empty($result)) {
            return null;
        }
        
        return $this->normalizeVectorStore($result[0]);
    }
    
    /**
     * List all vector stores
     */
    public function listVectorStores($filters = []) {
        $sql = "SELECT * FROM vector_stores";
        $params = [];
        $where = [];
        
        if (!empty($filters['name'])) {
            $where[] = "name LIKE ?";
            $params[] = '%' . $filters['name'] . '%';
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        if (isset($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
        }
        
        $results = $this->db->query($sql, $params);
        
        return array_map([$this, 'normalizeVectorStore'], $results);
    }
    
    /**
     * Update a vector store
     */
    public function updateVectorStore($id, $data) {
        $existing = $this->getVectorStore($id);
        if (!$existing) {
            throw new Exception('Vector store not found');
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        if (isset($data['status'])) {
            $updates[] = 'status = ?';
            $params[] = $data['status'];
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
        
        $sql = "UPDATE vector_stores SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $params);
        
        return $this->getVectorStore($id);
    }
    
    /**
     * Delete a vector store
     */
    public function deleteVectorStore($id) {
        $store = $this->getVectorStore($id);
        if (!$store) {
            throw new Exception('Vector store not found');
        }
        
        // Try to delete from OpenAI if ID exists
        if ($this->openaiClient && !empty($store['openai_store_id'])) {
            try {
                $this->openaiClient->deleteVectorStore($store['openai_store_id']);
            } catch (Exception $e) {
                error_log('Failed to delete vector store from OpenAI: ' . $e->getMessage());
            }
        }
        
        $sql = "DELETE FROM vector_stores WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return true;
    }
    
    /**
     * Add a file to a vector store
     */
    public function addFile($storeId, $data) {
        $store = $this->getVectorStore($storeId);
        if (!$store) {
            throw new Exception('Vector store not found');
        }
        
        $id = $this->generateUUID();
        $now = date('c');
        
        if (empty($data['name'])) {
            throw new Exception('File name is required');
        }
        
        $openaiFileId = null;
        $ingestionStatus = 'pending';
        
        // Upload file to OpenAI if base64 data provided
        if ($this->openaiClient && !empty($data['file_data'])) {
            try {
                // Upload file
                $uploadResult = $this->openaiClient->uploadFileFromBase64(
                    $data['name'],
                    $data['file_data'],
                    'assistants'
                );
                
                if ($uploadResult && isset($uploadResult['id'])) {
                    $openaiFileId = $uploadResult['id'];
                    
                    // Attach to vector store if we have OpenAI store ID
                    if (!empty($store['openai_store_id'])) {
                        $attachResult = $this->openaiClient->addFileToVectorStore(
                            $store['openai_store_id'],
                            $openaiFileId
                        );
                        
                        if ($attachResult && isset($attachResult['status'])) {
                            $ingestionStatus = $attachResult['status'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Failed to upload file to OpenAI: ' . $e->getMessage());
                throw $e;
            }
        } elseif (!empty($data['openai_file_id'])) {
            // Use provided OpenAI file ID
            $openaiFileId = $data['openai_file_id'];
            
            if ($this->openaiClient && !empty($store['openai_store_id'])) {
                try {
                    $attachResult = $this->openaiClient->addFileToVectorStore(
                        $store['openai_store_id'],
                        $openaiFileId
                    );
                    
                    if ($attachResult && isset($attachResult['status'])) {
                        $ingestionStatus = $attachResult['status'];
                    }
                } catch (Exception $e) {
                    error_log('Failed to attach file to vector store: ' . $e->getMessage());
                    throw $e;
                }
            }
        }
        
        $sql = "INSERT INTO vector_store_files 
                (id, vector_store_id, name, openai_file_id, size, mime_type, ingestion_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $id,
            $storeId,
            $data['name'],
            $openaiFileId,
            $data['size'] ?? null,
            $data['mime_type'] ?? null,
            $ingestionStatus,
            $now,
            $now
        ];
        
        $this->db->insert($sql, $params);
        
        return $this->getFile($id);
    }
    
    /**
     * Get a file by ID
     */
    public function getFile($id) {
        $sql = "SELECT * FROM vector_store_files WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        
        if (empty($result)) {
            return null;
        }
        
        return $result[0];
    }
    
    /**
     * List files in a vector store
     */
    public function listFiles($storeId, $filters = []) {
        $sql = "SELECT * FROM vector_store_files WHERE vector_store_id = ?";
        $params = [$storeId];
        
        if (!empty($filters['status'])) {
            $sql .= " AND ingestion_status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        if (isset($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
        }
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Update file ingestion status
     */
    public function updateFileStatus($id, $status, $errorMessage = null) {
        $file = $this->getFile($id);
        if (!$file) {
            throw new Exception('File not found');
        }
        
        $sql = "UPDATE vector_store_files 
                SET ingestion_status = ?, error_message = ?, updated_at = ? 
                WHERE id = ?";
        
        $this->db->execute($sql, [
            $status,
            $errorMessage,
            date('c'),
            $id
        ]);
        
        return $this->getFile($id);
    }
    
    /**
     * Delete a file
     */
    public function deleteFile($id) {
        $file = $this->getFile($id);
        if (!$file) {
            throw new Exception('File not found');
        }
        
        // Try to remove from vector store and delete from OpenAI
        if ($this->openaiClient && !empty($file['openai_file_id'])) {
            $store = $this->getVectorStore($file['vector_store_id']);
            
            if ($store && !empty($store['openai_store_id'])) {
                try {
                    $this->openaiClient->removeFileFromVectorStore(
                        $store['openai_store_id'],
                        $file['openai_file_id']
                    );
                } catch (Exception $e) {
                    error_log('Failed to remove file from vector store: ' . $e->getMessage());
                }
            }
            
            try {
                $this->openaiClient->deleteFile($file['openai_file_id']);
            } catch (Exception $e) {
                error_log('Failed to delete file from OpenAI: ' . $e->getMessage());
            }
        }
        
        $sql = "DELETE FROM vector_store_files WHERE id = ?";
        $this->db->execute($sql, [$id]);
        
        return true;
    }
    
    /**
     * Poll and update file ingestion status
     */
    public function pollFileStatus($id) {
        $file = $this->getFile($id);
        if (!$file) {
            throw new Exception('File not found');
        }
        
        if (!$this->openaiClient || empty($file['openai_file_id'])) {
            return $file;
        }
        
        $store = $this->getVectorStore($file['vector_store_id']);
        if (!$store || empty($store['openai_store_id'])) {
            return $file;
        }
        
        try {
            $status = $this->openaiClient->getVectorStoreFileStatus(
                $store['openai_store_id'],
                $file['openai_file_id']
            );
            
            if ($status && isset($status['status'])) {
                $this->updateFileStatus($id, $status['status']);
            }
        } catch (Exception $e) {
            error_log('Failed to poll file status: ' . $e->getMessage());
        }
        
        return $this->getFile($id);
    }
    
    /**
     * Sync vector stores from OpenAI
     */
    public function syncVectorStoresFromOpenAI() {
        if (!$this->openaiClient) {
            throw new Exception('OpenAI client not configured');
        }
        
        $result = $this->openaiClient->listVectorStores(100);
        $synced = 0;
        
        if (isset($result['data'])) {
            foreach ($result['data'] as $openaiStore) {
                $existing = $this->getVectorStoreByOpenAIId($openaiStore['id']);
                
                if (!$existing) {
                    $this->createVectorStore([
                        'name' => $openaiStore['name'] ?? $openaiStore['id'],
                        'openai_store_id' => $openaiStore['id'],
                    ]);
                    $synced++;
                }
            }
        }
        
        return $synced;
    }
    
    /**
     * Normalize vector store data
     */
    private function normalizeVectorStore($store) {
        if (isset($store['meta_json']) && !empty($store['meta_json'])) {
            $meta = json_decode($store['meta_json'], true);
            if ($meta) {
                $store['meta'] = $meta;
            }
        }
        
        // Get file count
        $sql = "SELECT COUNT(*) as count FROM vector_store_files WHERE vector_store_id = ?";
        $result = $this->db->query($sql, [$store['id']]);
        $store['file_count'] = $result[0]['count'] ?? 0;
        
        return $store;
    }
}
