<?php
/**
 * Unified Chat Endpoint - Supports both Chat Completions and Responses API
 */

require_once 'config.php';
require_once 'includes/OpenAIClient.php';
require_once 'includes/ChatHandler.php';

// Logging helper
$__CFG = $config; // capture for closures
function log_debug($message, $level = 'info') {
    global $__CFG;
    $logFile = $__CFG['logging']['file'] ?? __DIR__ . '/logs/chatbot.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts][$level] $message\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
}

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
header('X-Accel-Buffering: no');

ignore_user_abort(true);
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

// Main execution
try {
    // Use configuration loaded above
    $chatHandler = new ChatHandler($config);

    // Get request data
    $method = $_SERVER['REQUEST_METHOD'];
    $message = '';
    $conversationId = '';
    $apiType = $config['api_type'];
    $fileData = null;
    $promptId = '';
    $promptVersion = '';

    if ($method === 'GET') {
        $message = $_GET['message'] ?? '';
        $conversationId = $_GET['conversation_id'] ?? '';
        $apiType = $_GET['api_type'] ?? $apiType;
        $promptId = $_GET['prompt_id'] ?? $promptId;
        $promptVersion = $_GET['prompt_version'] ?? $promptVersion;
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? '';
        $apiType = $input['api_type'] ?? $apiType;
        $fileData = $input['file_data'] ?? null;
        $promptId = $input['prompt_id'] ?? $promptId;
        $promptVersion = $input['prompt_version'] ?? $promptVersion;
    }

    log_debug("Incoming request method=$method apiType=$apiType conv=$conversationId msgLen=" . strlen($message));

    if (empty($message)) {
        log_debug('Validation failed: Message is required', 'warn');
        throw new Exception('Message is required');
    }

    if (empty($conversationId)) {
        $conversationId = 'conv_' . uniqid();
    }

    if ($apiType === 'assistants') {
        log_debug('Legacy API type "assistants" detected. Falling back to responses.', 'warn');
        $apiType = 'responses';
    }

    // Validate and sanitize input
    $chatHandler->validateRequest($message, $conversationId, $fileData);

    // Send start event
    sendSSEEvent('start', [
        'conversation_id' => $conversationId,
        'api_type' => $apiType
    ]);

    // Route to appropriate handler
    if ($apiType === 'responses') {
        $chatHandler->handleResponsesChat($message, $conversationId, $fileData, $promptId, $promptVersion);
    } else {
        $chatHandler->handleChatCompletion($message, $conversationId);
    }

} catch (Exception $e) {
    error_log('Chat Error: ' . $e->getMessage());
    log_debug('Chat Error: ' . $e->getMessage(), 'error');

    sendSSEEvent('error', [
        'message' => 'An error occurred while processing your request.',
        'code' => $e->getCode() ?: 'UNKNOWN_ERROR'
    ]);
} finally {
    sendSSEEvent('close', null);
    exit();
}
?>
