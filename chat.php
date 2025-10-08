<?php
/**
 * GPT Chatbot - Server-Sent Events Endpoint
 * Handles real-time streaming communication with OpenAI API
 */

require_once 'config.php';

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Configure for streaming
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// Send headers to prevent nginx buffering
header('X-Accel-Buffering: no');

/**
 * Send SSE event to client
 */
function sendSSEEvent($type, $data = null, $id = null) {
    if ($id !== null) {
        echo "id: $id\n";
    }

    echo "event: $type\n";

    if ($data !== null) {
        $jsonData = json_encode($data);
        echo "data: $jsonData\n";
    }

    echo "\n";
    flush();
}

/**
 * Log errors
 */
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] ERROR: $message";
    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context);
    }
    error_log($logMessage);
}

/**
 * Validate request
 */
function validateRequest() {
    global $config;

    // Check if API key is configured
    if (empty($config['openai']['api_key'])) {
        throw new Exception('OpenAI API key not configured');
    }

    // Basic rate limiting (you may want to use Redis or database for production)
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitFile = sys_get_temp_dir() . '/chatbot_rate_limit_' . md5($clientIP);

    if (file_exists($rateLimitFile)) {
        $lastRequest = filemtime($rateLimitFile);
        if (time() - $lastRequest < 2) { // 2 second rate limit
            throw new Exception('Rate limit exceeded. Please wait before sending another message.');
        }
    }

    touch($rateLimitFile);
}

/**
 * Get conversation history from session
 */
function getConversationHistory($conversationId) {
    session_start();
    $sessionKey = 'chatbot_conversation_' . $conversationId;
    return $_SESSION[$sessionKey] ?? [];
}

/**
 * Save conversation history to session
 */
function saveConversationHistory($conversationId, $messages) {
    global $config;

    session_start();
    $sessionKey = 'chatbot_conversation_' . $conversationId;

    // Limit conversation history
    $maxMessages = $config['chat']['max_messages'] ?? 50;
    if (count($messages) > $maxMessages) {
        $messages = array_slice($messages, -$maxMessages);
    }

    $_SESSION[$sessionKey] = $messages;
}

/**
 * Stream response from OpenAI
 */
function streamOpenAIResponse($messages) {
    global $config;

    $apiKey = $config['openai']['api_key'];
    $model = $config['openai']['model'] ?? 'gpt-3.5-turbo';
    $temperature = $config['openai']['temperature'] ?? 0.7;
    $maxTokens = $config['openai']['max_tokens'] ?? 1000;

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'stream' => true
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_WRITEFUNCTION => function($ch, $data) {
            static $buffer = '';
            static $messageStarted = false;

            $buffer .= $data;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // Keep incomplete line in buffer

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);

                    if ($json === '[DONE]') {
                        sendSSEEvent('message', [
                            'type' => 'done'
                        ]);
                        return strlen($data);
                    }

                    $decoded = json_decode($json, true);
                    if ($decoded && isset($decoded['choices'][0]['delta'])) {
                        $delta = $decoded['choices'][0]['delta'];

                        if (isset($delta['content'])) {
                            if (!$messageStarted) {
                                sendSSEEvent('message', [
                                    'type' => 'start'
                                ]);
                                $messageStarted = true;
                            }

                            sendSSEEvent('message', [
                                'type' => 'chunk',
                                'content' => $delta['content']
                            ]);
                        }

                        if (isset($delta['finish_reason']) && $delta['finish_reason'] === 'stop') {
                            sendSSEEvent('message', [
                                'type' => 'done'
                            ]);
                        }
                    }
                }
            }

            // Check if client disconnected
            if (connection_aborted()) {
                return 0;
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

// Main execution
try {
    // Validate request
    validateRequest();

    // Get request data
    $method = $_SERVER['REQUEST_METHOD'];
    $message = '';
    $conversationId = '';

    if ($method === 'GET') {
        $message = $_GET['message'] ?? '';
        $conversationId = $_GET['conversation_id'] ?? '';
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? '';
    }

    if (empty($message)) {
        throw new Exception('Message is required');
    }

    if (empty($conversationId)) {
        $conversationId = 'default';
    }

    // Get conversation history
    $messages = getConversationHistory($conversationId);

    // Add user message
    $messages[] = [
        'role' => 'user',
        'content' => $message
    ];

    // Send start event
    sendSSEEvent('start', [
        'conversation_id' => $conversationId
    ]);

    // Stream response from OpenAI
    streamOpenAIResponse($messages);

    // Note: We can't save the assistant's response here since it's streamed
    // The client should send another request to save the conversation if needed

} catch (Exception $e) {
    logError($e->getMessage(), [
        'file' => __FILE__,
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    sendSSEEvent('error', [
        'message' => 'An error occurred while processing your request.'
    ]);
} finally {
    // Close SSE connection
    sendSSEEvent('close', null);
    exit();
}
?>