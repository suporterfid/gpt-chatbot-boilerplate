<?php
/**
 * Unified Chat Endpoint - Supports both Chat Completions and Assistants API
 */

require_once 'config.php';
require_once 'includes/OpenAIClient.php';
require_once 'includes/AssistantManager.php';
require_once 'includes/ThreadManager.php';
require_once 'includes/ChatHandler.php';

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
    $config = require_once 'config.php';
    $chatHandler = new ChatHandler($config);

    // Get request data
    $method = $_SERVER['REQUEST_METHOD'];
    $message = '';
    $conversationId = '';
    $apiType = $config['api_type'];
    $fileData = null;

    if ($method === 'GET') {
        $message = $_GET['message'] ?? '';
        $conversationId = $_GET['conversation_id'] ?? '';
        $apiType = $_GET['api_type'] ?? $apiType;
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? '';
        $apiType = $input['api_type'] ?? $apiType;
        $fileData = $input['file_data'] ?? null;
    }

    if (empty($message)) {
        throw new Exception('Message is required');
    }

    if (empty($conversationId)) {
        $conversationId = 'conv_' . uniqid();
    }

    // Validate and sanitize input
    $chatHandler->validateRequest($message, $conversationId, $fileData);

    // Send start event
    sendSSEEvent('start', [
        'conversation_id' => $conversationId,
        'api_type' => $apiType
    ]);

    // Route to appropriate handler
    if ($apiType === 'assistants') {
        $chatHandler->handleAssistantChat($message, $conversationId, $fileData);
    } else {
        $chatHandler->handleChatCompletion($message, $conversationId);
    }

} catch (Exception $e) {
    error_log('Chat Error: ' . $e->getMessage());

    sendSSEEvent('error', [
        'message' => 'An error occurred while processing your request.',
        'code' => $e->getCode() ?: 'UNKNOWN_ERROR'
    ]);
} finally {
    sendSSEEvent('close', null);
    exit();
}
?>