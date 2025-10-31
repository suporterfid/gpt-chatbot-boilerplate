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

/**
 * Decode and validate tools payload from request input.
 *
 * @param mixed $rawTools
 * @param string $source
 * @return array|null
 * @throws Exception
 */
function extractToolsConfig($rawTools, string $source = 'request') {
    if ($rawTools === null) {
        return null;
    }

    if (is_string($rawTools)) {
        $trimmed = trim($rawTools);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();
            log_debug("Failed to decode tools payload from {$source}: {$error}", 'warn');
            throw new Exception('Invalid tools payload: expected JSON array', 400);
        }

        $rawTools = $decoded;
    }

    if ($rawTools === null) {
        return null;
    }

    if (!is_array($rawTools)) {
        log_debug("Tools payload from {$source} must decode to an array", 'warn');
        throw new Exception('Invalid tools payload: expected array', 400);
    }

    $validTools = [];
    foreach ($rawTools as $index => $tool) {
        if (!is_array($tool)) {
            log_debug("Ignoring non-object tools entry at index {$index} from {$source}", 'warn');
            continue;
        }

        $type = $tool['type'] ?? null;
        if (!is_string($type) || trim($type) === '') {
            log_debug("Ignoring tools entry missing type at index {$index} from {$source}", 'warn');
            continue;
        }

        $validTools[] = $tool;
    }

    return $validTools;
}

// Main execution
try {
    // Initialize AgentService if admin is enabled
    $agentService = null;
    if ($config['admin']['enabled'] && !empty($config['admin']['token'])) {
        require_once 'includes/DB.php';
        require_once 'includes/AgentService.php';
        
        try {
            $dbConfig = [
                'database_url' => $config['admin']['database_url'] ?? null,
                'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/data/chatbot.db'
            ];
            
            $db = new DB($dbConfig);
            
            // Run migrations if needed
            $db->runMigrations(__DIR__ . '/db/migrations');
            
            $agentService = new AgentService($db);
        } catch (Exception $e) {
            // Log but continue - admin features will be disabled
            log_debug('Failed to initialize AgentService: ' . $e->getMessage(), 'warn');
        }
    }
    
    // Use configuration loaded above
    $chatHandler = new ChatHandler($config, $agentService);

    // Get request data
    $message = '';
    $conversationId = '';
    $apiType = $config['api_type'];
    $fileData = null;
    $tools = null;
    $promptId = '';
    $promptVersion = '';
    $agentId = '';

    if ($method === 'GET') {
        $message = $_GET['message'] ?? '';
        $conversationId = $_GET['conversation_id'] ?? '';
        $apiType = $_GET['api_type'] ?? $apiType;
        $promptId = $_GET['prompt_id'] ?? $promptId;
        $promptVersion = $_GET['prompt_version'] ?? $promptVersion;
        $agentId = $_GET['agent_id'] ?? '';
        if (isset($_GET['tools'])) {
            $tools = extractToolsConfig($_GET['tools'], 'query');
        }
    } elseif ($method === 'POST') {
        $message = $input['message'] ?? '';
        $conversationId = $input['conversation_id'] ?? '';
        $apiType = $input['api_type'] ?? $apiType;
        $fileData = $input['file_data'] ?? null;
        $promptId = $input['prompt_id'] ?? $promptId;
        $promptVersion = $input['prompt_version'] ?? $promptVersion;
        $agentId = $input['agent_id'] ?? '';
        if (array_key_exists('tools', $input)) {
            $tools = extractToolsConfig($input['tools'], 'body');
        }
    }

    log_debug("Incoming request method=$method apiType=$apiType conv=$conversationId agentId=$agentId msgLen=" . strlen($message));

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
            'api_type' => $apiType,
            'agent_id' => $agentId
        ]);

        // Route to appropriate handler
        if ($apiType === 'responses') {
            $chatHandler->handleResponsesChat($message, $conversationId, $fileData, $promptId, $promptVersion, $tools, $agentId);
        } else {
            $chatHandler->handleChatCompletion($message, $conversationId, $agentId);
        }
    } else {
        if ($apiType === 'responses') {
            $result = $chatHandler->handleResponsesChatSync($message, $conversationId, $fileData, $promptId, $promptVersion, $tools, $agentId);
        } else {
            $result = $chatHandler->handleChatCompletionSync($message, $conversationId, $agentId);
        }

        $result['conversation_id'] = $conversationId;
        $result['api_type'] = $apiType;
        if ($agentId) {
            $result['agent_id'] = $agentId;
        }

        echo json_encode($result);
    }

} catch (Exception $e) {
    error_log('Chat Error: ' . $e->getMessage());
    log_debug('Chat Error: ' . $e->getMessage(), 'error');

    if ($shouldStream) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage() ?: 'An error occurred while processing your request.';
        $isClientError = is_int($errorCode) && $errorCode >= 400 && $errorCode < 500;

        sendSSEEvent('error', [
            'message' => $isClientError ? $errorMessage : 'An error occurred while processing your request.',
            'code' => $errorCode ?: 'UNKNOWN_ERROR'
        ]);
    } else {
        $statusCode = $e->getCode();
        if (!is_int($statusCode) || $statusCode < 400 || $statusCode > 599) {
            $statusCode = 500;
        }
        $errorMessage = $e->getMessage() ?: 'An error occurred while processing your request.';
        $isClientError = $statusCode >= 400 && $statusCode < 500;

        http_response_code($statusCode);
        echo json_encode([
            'error' => [
                'message' => $isClientError ? $errorMessage : 'An error occurred while processing your request.',
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
