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

// Shared CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Determine request type before sending response headers
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = '';
$input = [];

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

$shouldStream = true;

if ($method === 'POST' && array_key_exists('stream', $input)) {
    $streamFlag = $input['stream'];
    if ($streamFlag === false || $streamFlag === 'false' || $streamFlag === 0 || $streamFlag === '0') {
        $shouldStream = false;
    }
}

if ($shouldStream) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    // Disable output buffering for streaming
    if (ob_get_level()) {
        ob_end_clean();
    }

    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);

    ignore_user_abort(true);
} else {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
}
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
    $message = '';
    $conversationId = '';
    $apiType = $config['api_type'];
    $fileData = null;
    $tools = null;
    $promptId = '';
    $promptVersion = '';

    if ($method === 'GET') {
        $message = $_GET['message'] ?? '';
        $conversationId = $_GET['conversation_id'] ?? '';
        $apiType = $_GET['api_type'] ?? $apiType;
        $promptId = $_GET['prompt_id'] ?? $promptId;
        $promptVersion = $_GET['prompt_version'] ?? $promptVersion;
        if (isset($_GET['tools'])) {
            $decodedTools = json_decode($_GET['tools'], true);
            if (is_array($decodedTools)) {
                $tools = $decodedTools;
            }
        }
    } elseif ($method === 'POST') {
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? '';
        $apiType = $input['api_type'] ?? $apiType;
        $fileData = $input['file_data'] ?? null;
        $promptId = $input['prompt_id'] ?? $promptId;
        $promptVersion = $input['prompt_version'] ?? $promptVersion;
        if (array_key_exists('tools', $input)) {
            $postedTools = $input['tools'];
            if (is_string($postedTools)) {
                $decodedTools = json_decode($postedTools, true);
                if (is_array($decodedTools)) {
                    $tools = $decodedTools;
                }
            } elseif (is_array($postedTools)) {
                $tools = $postedTools;
            }
        }
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

    if ($shouldStream) {
        // Send start event
        sendSSEEvent('start', [
            'conversation_id' => $conversationId,
            'api_type' => $apiType
        ]);

        // Route to appropriate handler
        if ($apiType === 'responses') {
            $chatHandler->handleResponsesChat($message, $conversationId, $fileData, $promptId, $promptVersion, $tools);
        } else {
            $chatHandler->handleChatCompletion($message, $conversationId);
        }
    } else {
        if ($apiType === 'responses') {
            $result = $chatHandler->handleResponsesChatSync($message, $conversationId, $fileData, $promptId, $promptVersion, $tools);
        } else {
            $result = $chatHandler->handleChatCompletionSync($message, $conversationId);
        }

        $result['conversation_id'] = $conversationId;
        $result['api_type'] = $apiType;

        echo json_encode($result);
    }

} catch (Exception $e) {
    error_log('Chat Error: ' . $e->getMessage());
    log_debug('Chat Error: ' . $e->getMessage(), 'error');

    if ($shouldStream) {
        sendSSEEvent('error', [
            'message' => 'An error occurred while processing your request.',
            'code' => $e->getCode() ?: 'UNKNOWN_ERROR'
        ]);
    } else {
        $statusCode = $e->getCode();
        if (!is_int($statusCode) || $statusCode < 400 || $statusCode > 599) {
            $statusCode = 500;
        }
        http_response_code($statusCode);
        echo json_encode([
            'error' => [
                'message' => 'An error occurred while processing your request.',
                'code' => $e->getCode() ?: 'UNKNOWN_ERROR'
            ]
        ]);
    }
} finally {
    if ($shouldStream) {
        sendSSEEvent('close', null);
    }
    exit();
}
?>
