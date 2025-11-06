<?php
/**
 * Z-API HTTP Client - Handles HTTP communication with Z-API
 */

class ZApiClient {
    private $baseUrl;
    private $instanceId;
    private $token;
    private $timeoutMs;
    private $retries;
    
    public function __construct(string $baseUrl, string $instanceId, string $token, int $timeoutMs = 30000, int $retries = 3) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->instanceId = $instanceId;
        $this->token = $token;
        $this->timeoutMs = $timeoutMs;
        $this->retries = max(1, $retries);
    }
    
    /**
     * Make an HTTP request to Z-API
     * 
     * @param string $endpoint API endpoint (e.g., 'send-text')
     * @param array $data Request payload
     * @param string $method HTTP method
     * @return array Response data
     * @throws Exception on failure
     */
    public function request(string $endpoint, array $data = [], string $method = 'POST'): array {
        $url = "{$this->baseUrl}/{$endpoint}";
        
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $this->retries) {
            $attempt++;
            
            try {
                $ch = curl_init();
                
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeoutMs);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 10000);
                
                // Set headers
                $headers = [
                    'Content-Type: application/json',
                    'Client-Token: ' . $this->token
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                // Set method and body
                if ($method === 'POST' && !empty($data)) {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                } elseif ($method === 'GET') {
                    if (!empty($data)) {
                        $url .= '?' . http_build_query($data);
                        curl_setopt($ch, CURLOPT_URL, $url);
                    }
                }
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                
                curl_close($ch);
                
                if ($response === false) {
                    throw new Exception("cURL error: $curlError");
                }
                
                $responseData = json_decode($response, true);
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    return $responseData ?? ['success' => true];
                }
                
                // Log error but retry on 5xx errors
                $errorMsg = isset($responseData['message']) ? $responseData['message'] : $response;
                error_log("Z-API request failed (attempt $attempt/$this->retries): HTTP $httpCode - $errorMsg");
                
                if ($httpCode >= 500 && $attempt < $this->retries) {
                    // Exponential backoff
                    usleep(min(1000000, 100000 * pow(2, $attempt - 1)));
                    continue;
                }
                
                throw new Exception("Z-API error (HTTP $httpCode): $errorMsg", $httpCode);
                
            } catch (Exception $e) {
                $lastError = $e;
                
                if ($attempt < $this->retries && strpos($e->getMessage(), 'timeout') !== false) {
                    error_log("Z-API timeout, retrying (attempt $attempt/$this->retries)");
                    usleep(min(1000000, 100000 * pow(2, $attempt - 1)));
                    continue;
                }
                
                if ($attempt >= $this->retries) {
                    break;
                }
            }
        }
        
        throw $lastError ?? new Exception('Z-API request failed after all retries');
    }
    
    /**
     * Send text message
     */
    public function sendText(string $phone, string $text): array {
        return $this->request('send-text', [
            'phone' => $this->normalizePhone($phone),
            'message' => $text
        ]);
    }
    
    /**
     * Send image message
     */
    public function sendImage(string $phone, string $imageUrl, ?string $caption = null): array {
        $data = [
            'phone' => $this->normalizePhone($phone),
            'image' => $imageUrl
        ];
        
        if ($caption) {
            $data['caption'] = $caption;
        }
        
        return $this->request('send-image', $data);
    }
    
    /**
     * Send document message
     */
    public function sendDocument(string $phone, string $documentUrl, ?string $caption = null): array {
        $data = [
            'phone' => $this->normalizePhone($phone),
            'document' => $documentUrl
        ];
        
        if ($caption) {
            $data['caption'] = $caption;
        }
        
        return $this->request('send-document', $data);
    }
    
    /**
     * Normalize phone number to E.164 format (basic normalization)
     */
    private function normalizePhone(string $phone): string {
        // Remove all non-digit characters except +
        $normalized = preg_replace('/[^\d+]/', '', $phone);
        
        // Ensure it starts with +
        if (!str_starts_with($normalized, '+')) {
            $normalized = '+' . $normalized;
        }
        
        return $normalized;
    }
}
