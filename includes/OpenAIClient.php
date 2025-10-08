<?php
/**
 * Enhanced OpenAI Client with support for both Chat Completions and Assistants API
 */

class OpenAIClient {
    private $apiKey;
    private $organization;
    private $baseUrl;

    public function __construct($config) {
        $this->apiKey = $config['api_key'];
        $this->organization = $config['organization'] ?? '';
        $this->baseUrl = $config['base_url'];
    }

    // Chat Completions API Methods
    public function streamChatCompletion($payload, $callback) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/chat/completions',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $this->getHeaders(),
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
    }

    // Assistants API Methods
    public function createAssistant($config) {
        $payload = [
            'name' => $config['name'],
            'description' => $config['description'],
            'instructions' => $config['instructions'],
            'model' => $config['model'],
            'temperature' => $config['temperature']
        ];

        if (!empty($config['tools'])) {
            $payload['tools'] = $config['tools'];
        }

        return $this->makeRequest('POST', '/assistants', $payload);
    }

    public function getAssistant($assistantId) {
        return $this->makeRequest('GET', '/assistants/' . $assistantId);
    }

    public function createThread($metadata = []) {
        $payload = ['metadata' => $metadata];
        return $this->makeRequest('POST', '/threads', $payload);
    }

    public function getThread($threadId) {
        return $this->makeRequest('GET', '/threads/' . $threadId);
    }

    public function addMessageToThread($threadId, $message) {
        return $this->makeRequest('POST', '/threads/' . $threadId . '/messages', $message);
    }

    public function getThreadMessages($threadId, $params = []) {
        $query = http_build_query($params);
        $url = '/threads/' . $threadId . '/messages' . ($query ? '?' . $query : '');
        return $this->makeRequest('GET', $url);
    }

    public function streamAssistantRun($threadId, $runConfig, $callback) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/threads/' . $threadId . '/runs',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($runConfig),
            CURLOPT_HTTPHEADER => $this->getHeaders(),
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
            CURLOPT_TIMEOUT => 120,
        ]);

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
    }

    public function submitToolOutputs($threadId, $runId, $toolOutputs) {
        $payload = ['tool_outputs' => $toolOutputs];
        return $this->makeRequest('POST', '/threads/' . $threadId . '/runs/' . $runId . '/submit_tool_outputs', $payload);
    }

    public function uploadFile($fileData) {
        $ch = curl_init();

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'chatbot_upload_');
        file_put_contents($tempFile, base64_decode($fileData['data']));

        $postFields = [
            'purpose' => 'assistants',
            'file' => new CURLFile($tempFile, $fileData['type'], $fileData['name'])
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/files',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'OpenAI-Organization: ' . $this->organization
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Clean up temp file
        unlink($tempFile);

        if ($result === false) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('File upload error: HTTP ' . $httpCode);
        }

        $response = json_decode($result, true);
        return $response['id'] ?? null;
    }

    private function makeRequest($method, $endpoint, $data = null) {
        $ch = curl_init();

        $url = $this->baseUrl . $endpoint;
        $headers = $this->getHeaders();

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

        if ($httpCode < 200 || $httpCode >= 300) {
            $response = json_decode($result, true);
            $errorMessage = $response['error']['message'] ?? 'Unknown API error';
            throw new Exception("OpenAI API error ({$httpCode}): " . $errorMessage);
        }

        return json_decode($result, true);
    }

    private function getHeaders() {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if (!empty($this->organization)) {
            $headers[] = 'OpenAI-Organization: ' . $this->organization;
        }

        return $headers;
    }
}
?>