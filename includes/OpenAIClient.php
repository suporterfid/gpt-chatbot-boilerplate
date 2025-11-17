<?php
/**
 * Enhanced OpenAI Client with support for both Chat Completions and Responses API
 */

class OpenAIClient {
    private $apiKey;
    private $organization;
    private $baseUrl;
    private $auditService;
    private $observability;

    public function __construct($config, $auditService = null, $observability = null) {
        $this->apiKey = $config['api_key'];
        $this->organization = $config['organization'] ?? '';
        $this->baseUrl = $config['base_url'];
        $this->auditService = $auditService;
        $this->observability = $observability;
    }

    // Chat Completions API Methods
    public function streamChatCompletion($payload, $callback) {
        $startTime = microtime(true);
        $spanId = null;
        
        if ($this->observability) {
            $spanId = $this->observability->createSpan('openai.chat_completion', [
                'api.type' => 'chat_completions',
                'model' => $payload['model'] ?? 'unknown',
                'stream' => true,
            ]);
        }
        
        $this->logRequestPayload('POST', '/chat/completions', $payload);

        $ch = curl_init();
        
        // Add trace propagation headers
        $extraHeaders = ['Accept: text/event-stream'];
        if ($this->observability) {
            $traceHeaders = $this->observability->getTracePropagationHeaders();
            foreach ($traceHeaders as $key => $value) {
                $extraHeaders[] = "$key: $value";
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/chat/completions',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $this->getHeaders($extraHeaders),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($callback) {
                static $buffer = '';

                $buffer .= $data;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    if (strpos($line, 'data: ') === 0) {
                        $json = substr($line, 6);

                        if ($json === '[DONE]') {
                            return strlen($data);
                        }

                        $decoded = json_decode($json, true);
                        if ($decoded) {
                            $callback($decoded);
                        }
                    }
                }

                return strlen($data);
            },
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $success = false;
        try {
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                throw new Exception('cURL error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new Exception('OpenAI API error: HTTP ' . $httpCode);
            }
            
            $success = true;
        } finally {
            $duration = microtime(true) - $startTime;
            
            if ($this->observability) {
                $this->observability->endSpan($spanId, [
                    'http.status_code' => $httpCode ?? 0,
                    'success' => $success,
                ]);
                
                $this->observability->trackOpenAICall(
                    'chat_completions',
                    $payload['model'] ?? 'unknown',
                    $duration,
                    $success
                );
            }
        }
    }

    public function streamResponse(array $payload, callable $callback) {
        $startTime = microtime(true);
        $spanId = null;
        
        if ($this->observability) {
            $spanId = $this->observability->createSpan('openai.responses', [
                'api.type' => 'responses',
                'model' => $payload['model'] ?? 'unknown',
                'stream' => true,
            ]);
        }
        
        $payload['stream'] = true;
        $this->logRequestPayload('POST', '/responses', $payload);

        $ch = curl_init();
        
        // Add trace propagation headers
        $extraHeaders = ['Accept: text/event-stream'];
        if ($this->observability) {
            $traceHeaders = $this->observability->getTracePropagationHeaders();
            foreach ($traceHeaders as $key => $value) {
                $extraHeaders[] = "$key: $value";
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/responses',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $this->getHeaders($extraHeaders),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($callback) {
                static $buffer = '';

                $buffer .= $data;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    if (strpos($line, 'data: ') === 0) {
                        $json = substr($line, 6);

                        if ($json === '[DONE]') {
                            return strlen($data);
                        }

                        $decoded = json_decode($json, true);
                        if ($decoded !== null) {
                            $callback($decoded);
                        }
                    }
                }

                return strlen($data);
            },
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $success = false;
        $httpCode = null;
        try {
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                throw new Exception('cURL error: ' . $error);
            }

            if ($httpCode !== 200) {
                // Attempt to retrieve detailed error by reissuing a non-streaming request
                try {
                    $retryPayload = $payload;
                    $retryPayload['stream'] = false;
                    // Make a direct request to capture error body
                    $response = $this->makeRequest('POST', '/responses', $retryPayload);
                // If API returned 2xx unexpectedly, treat as error-less
                // but still signal upstream with a generic message
                throw new Exception('OpenAI API error: HTTP ' . $httpCode);
            } catch (Exception $e) {
                // Enrich message with detailed context
                throw new Exception('OpenAI API error: HTTP ' . $httpCode . ' - ' . $e->getMessage());
            }
            }
            
            $success = true;
        } finally {
            $duration = microtime(true) - $startTime;
            
            if ($this->observability) {
                $this->observability->endSpan($spanId, [
                    'http.status_code' => $httpCode ?? 0,
                    'success' => $success,
                ]);
                
                $this->observability->trackOpenAICall(
                    'responses',
                    $payload['model'] ?? 'unknown',
                    $duration,
                    $success
                );
            }
        }
    }

    public function createResponse(array $payload) {
        return $this->makeRequest('POST', '/responses', $payload);
    }

    public function createChatCompletion(array $payload) {
        return $this->makeRequest('POST', '/chat/completions', $payload);
    }

    public function submitResponseToolOutputs($responseId, array $toolOutputs) {
        return $this->makeRequest('POST', '/responses/' . $responseId . '/submit_tool_outputs', [
            'tool_outputs' => $toolOutputs,
        ]);
    }

    public function uploadFile($fileData, $purpose = 'user_data') {
        // Load required classes
        if (!class_exists('FileValidator')) {
            require_once __DIR__ . '/FileValidator.php';
        }
        if (!class_exists('SecureFileUpload')) {
            require_once __DIR__ . '/SecureFileUpload.php';
        }
        
        // Validate file first
        $validator = new FileValidator();
        $content = $validator->validateFile($fileData, $this->config);
        
        // Determine upload directory (use config or fallback to system temp)
        $uploadDir = $this->config['chat_config']['upload_dir'] ?? sys_get_temp_dir() . '/chatbot_uploads';
        $secureUpload = new SecureFileUpload($uploadDir);
        
        // Create secure temporary file
        $tempFile = $secureUpload->createTempFile($content, $fileData['name']);
        
        try {
            // Get actual MIME type from file content
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actualMimeType = finfo_file($finfo, $tempFile);
            finfo_close($finfo);
            
            if ($actualMimeType === false) {
                $actualMimeType = $fileData['type']; // Fallback to declared type
            }
            
            // Upload to OpenAI
            $ch = curl_init();
            
            $postFields = [
                'purpose' => $purpose,
                'file' => new CURLFile($tempFile, $actualMimeType, basename($fileData['name']))
            ];

            $headers = [
                'Authorization: Bearer ' . $this->apiKey,
            ];

            if (!empty($this->organization)) {
                $headers[] = 'OpenAI-Organization: ' . $this->organization;
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/files',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                throw new Exception('cURL error: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new Exception('File upload error: HTTP ' . $httpCode);
            }

            $response = json_decode($result, true);
            return $response['id'] ?? null;
            
        } finally {
            // Always cleanup, even on exception
            $secureUpload->cleanupTempFile($tempFile);
        }
    }

    private function makeRequest($method, $endpoint, $data = null) {
        $ch = curl_init();

        $url = $this->baseUrl . $endpoint;
        $headers = $this->getHeaders();

        if ($data !== null) {
            $this->logRequestPayload($method, $endpoint, $data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new Exception('cURL error: ' . $error);
        }

        $this->logResponsePayload($method, $endpoint, $httpCode, $result);

        if ($httpCode < 200 || $httpCode >= 300) {
            $response = json_decode($result, true);
            $errorMessage = $response['error']['message'] ?? 'Unknown API error';
            throw new Exception("OpenAI API error ({$httpCode}): " . $errorMessage);
        }

        return json_decode($result, true);
    }

    private function getHeaders(array $additionalHeaders = []) {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if (!empty($this->organization)) {
            $headers[] = 'OpenAI-Organization: ' . $this->organization;
        }

        return array_merge($headers, $additionalHeaders);
    }

    private function logRequestPayload(string $method, string $endpoint, $payload): void {
        if (!function_exists('log_debug')) {
            return;
        }

        $encoded = $this->encodeJsonForLog($payload);
        log_debug(sprintf('OpenAI request %s %s payload=%s', $method, $endpoint, $encoded), 'debug');
    }

    private function logResponsePayload(string $method, string $endpoint, int $status, string $body): void {
        if (!function_exists('log_debug')) {
            return;
        }

        $decoded = json_decode($body, true);
        $encoded = $this->encodeJsonForLog($decoded ?? $body);
        log_debug(sprintf('OpenAI response %s %s status=%d body=%s', $method, $endpoint, $status, $encoded), 'debug');
    }

    private function encodeJsonForLog($data): string {
        if ($data === null) {
            return 'null';
        }

        if (is_string($data)) {
            return $data;
        }

        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return '"<unserializable>"';
        }

        return $encoded;
    }
}
?>
