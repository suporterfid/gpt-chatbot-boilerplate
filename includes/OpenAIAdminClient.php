<?php
/**
 * OpenAI Admin Client - Handles Prompts, Vector Stores, and Files API calls
 * Follows patterns from OpenAIClient.php with robust error handling
 */

class OpenAIAdminClient {
    private $apiKey;
    private $organization;
    private $baseUrl;

    public function __construct($config) {
        $this->apiKey = $config['api_key'];
        $this->organization = $config['organization'] ?? '';
        $this->baseUrl = $config['base_url'];
    }

    /**
     * Make authenticated request to OpenAI API
     */
    private function makeRequest($method, $endpoint, $data = null, $isMultipart = false) {
        $url = $this->baseUrl . $endpoint;
        
        $this->logRequest($method, $endpoint, $data);
        
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
        ];
        
        if (!empty($this->organization)) {
            $headers[] = 'OpenAI-Organization: ' . $this->organization;
        }
        
        if ($isMultipart) {
            // For file uploads - let curl set Content-Type with boundary
        } else {
            $headers[] = 'Content-Type: application/json';
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $isMultipart ? $data : json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET') {
            // GET is default
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception('cURL error: ' . $error);
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = 'OpenAI API error: HTTP ' . $httpCode;
            if (isset($decoded['error']['message'])) {
                $errorMsg .= ' - ' . $decoded['error']['message'];
            }
            $this->logError($method, $endpoint, $httpCode, $decoded);
            
            // Return null for 404 to allow graceful handling
            if ($httpCode === 404) {
                return null;
            }
            
            throw new Exception($errorMsg, $httpCode);
        }
        
        return $decoded;
    }

    // ==================== Prompts API Methods ====================

    /**
     * List prompts (if API available)
     */
    public function listPrompts($limit = 20, $after = '') {
        try {
            $query = '?limit=' . $limit;
            if (!empty($after)) {
                $query .= '&after=' . $after;
            }
            
            $result = $this->makeRequest('GET', '/prompts' . $query);
            return $result;
        } catch (Exception $e) {
            $this->logWarning('Prompts API may not be available: ' . $e->getMessage());
            return ['data' => [], 'has_more' => false];
        }
    }

    /**
     * Get a specific prompt
     */
    public function getPrompt($promptId) {
        try {
            return $this->makeRequest('GET', '/prompts/' . $promptId);
        } catch (Exception $e) {
            $this->logWarning('Failed to get prompt: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * List prompt versions
     */
    public function listPromptVersions($promptId, $limit = 20, $after = '') {
        try {
            $query = '?limit=' . $limit;
            if (!empty($after)) {
                $query .= '&after=' . $after;
            }
            
            $result = $this->makeRequest('GET', '/prompts/' . $promptId . '/versions' . $query);
            return $result;
        } catch (Exception $e) {
            $this->logWarning('Failed to list prompt versions: ' . $e->getMessage());
            return ['data' => [], 'has_more' => false];
        }
    }

    /**
     * Create a new prompt
     */
    public function createPrompt($name, $definition, $description = '') {
        try {
            $data = [
                'name' => $name,
                'definition' => $definition,
            ];
            
            if (!empty($description)) {
                $data['description'] = $description;
            }
            
            return $this->makeRequest('POST', '/prompts', $data);
        } catch (Exception $e) {
            $this->logWarning('Failed to create prompt: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new prompt version
     */
    public function createPromptVersion($promptId, $definition) {
        try {
            $data = [
                'definition' => $definition,
            ];
            
            return $this->makeRequest('POST', '/prompts/' . $promptId . '/versions', $data);
        } catch (Exception $e) {
            $this->logWarning('Failed to create prompt version: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a prompt
     */
    public function deletePrompt($promptId) {
        try {
            $result = $this->makeRequest('DELETE', '/prompts/' . $promptId);
            return true;
        } catch (Exception $e) {
            $this->logWarning('Failed to delete prompt: ' . $e->getMessage());
            return false;
        }
    }

    // ==================== Vector Stores API Methods ====================

    /**
     * List vector stores
     */
    public function listVectorStores($limit = 20, $after = '') {
        try {
            $query = '?limit=' . $limit;
            if (!empty($after)) {
                $query .= '&after=' . $after;
            }
            
            $result = $this->makeRequest('GET', '/vector_stores' . $query);
            return $result ?? ['data' => [], 'has_more' => false];
        } catch (Exception $e) {
            $this->logWarning('Failed to list vector stores: ' . $e->getMessage());
            return ['data' => [], 'has_more' => false];
        }
    }

    /**
     * Get a specific vector store
     */
    public function getVectorStore($storeId) {
        try {
            return $this->makeRequest('GET', '/vector_stores/' . $storeId);
        } catch (Exception $e) {
            $this->logWarning('Failed to get vector store: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new vector store
     */
    public function createVectorStore($name, $metadata = []) {
        try {
            $data = [
                'name' => $name,
            ];
            
            if (!empty($metadata)) {
                $data['metadata'] = $metadata;
            }
            
            return $this->makeRequest('POST', '/vector_stores', $data);
        } catch (Exception $e) {
            $this->logWarning('Failed to create vector store: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a vector store
     */
    public function deleteVectorStore($storeId) {
        try {
            $result = $this->makeRequest('DELETE', '/vector_stores/' . $storeId);
            return true;
        } catch (Exception $e) {
            $this->logWarning('Failed to delete vector store: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List files in a vector store
     */
    public function listVectorStoreFiles($storeId, $limit = 20, $after = '') {
        try {
            $query = '?limit=' . $limit;
            if (!empty($after)) {
                $query .= '&after=' . $after;
            }
            
            $result = $this->makeRequest('GET', '/vector_stores/' . $storeId . '/files' . $query);
            return $result ?? ['data' => [], 'has_more' => false];
        } catch (Exception $e) {
            $this->logWarning('Failed to list vector store files: ' . $e->getMessage());
            return ['data' => [], 'has_more' => false];
        }
    }

    /**
     * Add a file to a vector store
     */
    public function addFileToVectorStore($storeId, $fileId) {
        try {
            $data = [
                'file_id' => $fileId,
            ];
            
            return $this->makeRequest('POST', '/vector_stores/' . $storeId . '/files', $data);
        } catch (Exception $e) {
            $this->logWarning('Failed to add file to vector store: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove a file from a vector store
     */
    public function removeFileFromVectorStore($storeId, $fileId) {
        try {
            $result = $this->makeRequest('DELETE', '/vector_stores/' . $storeId . '/files/' . $fileId);
            return true;
        } catch (Exception $e) {
            $this->logWarning('Failed to remove file from vector store: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file status in vector store
     */
    public function getVectorStoreFileStatus($storeId, $fileId) {
        try {
            return $this->makeRequest('GET', '/vector_stores/' . $storeId . '/files/' . $fileId);
        } catch (Exception $e) {
            $this->logWarning('Failed to get file status: ' . $e->getMessage());
            return null;
        }
    }

    // ==================== Files API Methods ====================

    /**
     * List files
     */
    public function listFiles($purpose = 'assistants') {
        try {
            $query = '?purpose=' . $purpose;
            $result = $this->makeRequest('GET', '/files' . $query);
            return $result ?? ['data' => []];
        } catch (Exception $e) {
            $this->logWarning('Failed to list files: ' . $e->getMessage());
            return ['data' => []];
        }
    }

    /**
     * Upload a file
     */
    public function uploadFile($filePath, $purpose = 'assistants') {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('File not found: ' . $filePath);
            }
            
            $cFile = curl_file_create($filePath);
            
            $data = [
                'file' => $cFile,
                'purpose' => $purpose,
            ];
            
            return $this->makeRequest('POST', '/files', $data, true);
        } catch (Exception $e) {
            $this->logWarning('Failed to upload file: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload file from base64 data
     */
    public function uploadFileFromBase64($name, $base64Data, $purpose = 'assistants') {
        try {
            // Decode base64
            $fileData = base64_decode($base64Data);
            if ($fileData === false) {
                throw new Exception('Invalid base64 data');
            }
            
            // Create temporary file
            $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
            file_put_contents($tmpFile, $fileData);
            
            // Rename to preserve extension
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $tmpFileWithExt = $tmpFile . '.' . $ext;
            rename($tmpFile, $tmpFileWithExt);
            
            try {
                $result = $this->uploadFile($tmpFileWithExt, $purpose);
                unlink($tmpFileWithExt);
                return $result;
            } catch (Exception $e) {
                unlink($tmpFileWithExt);
                throw $e;
            }
        } catch (Exception $e) {
            $this->logWarning('Failed to upload file from base64: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile($fileId) {
        try {
            $result = $this->makeRequest('DELETE', '/files/' . $fileId);
            return true;
        } catch (Exception $e) {
            $this->logWarning('Failed to delete file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file details
     */
    public function getFile($fileId) {
        try {
            return $this->makeRequest('GET', '/files/' . $fileId);
        } catch (Exception $e) {
            $this->logWarning('Failed to get file: ' . $e->getMessage());
            return null;
        }
    }

    // ==================== Logging Methods ====================

    private function logRequest($method, $endpoint, $data) {
        if (function_exists('log_debug')) {
            $msg = "OpenAIAdminClient: $method $endpoint";
            if ($data && !is_array($data)) {
                $msg .= " (multipart)";
            } elseif ($data) {
                $msg .= " " . json_encode($data);
            }
            log_debug($msg);
        }
    }

    private function logError($method, $endpoint, $httpCode, $response) {
        if (function_exists('log_debug')) {
            log_debug("OpenAIAdminClient ERROR: $method $endpoint - HTTP $httpCode - " . json_encode($response));
        }
    }

    private function logWarning($message) {
        if (function_exists('log_debug')) {
            log_debug("OpenAIAdminClient WARNING: $message");
        }
    }
}
