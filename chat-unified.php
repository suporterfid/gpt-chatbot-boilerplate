<?php
/**
 * Unified Chat Endpoint - Supports both Chat Completions and Responses API
 */

require_once 'config.php';
require_once 'includes/OpenAIClient.php';
require_once 'includes/ChatHandler.php';
require_once 'includes/AuditService.php';

// Initialize observability if enabled
$observability = null;
if ($config['observability']['enabled'] ?? true) {
    require_once 'includes/ObservabilityMiddleware.php';
    $observability = new ObservabilityMiddleware($config);
}

// Logging helper - now uses observability if available
$__CFG = $config; // capture for closures
$__OBS = $observability; // capture for closures
function log_debug($message, $level = 'info') {
    global $__CFG, $__OBS;
    
    // Use observability logger if available
    if ($__OBS) {
        $logger = $__OBS->getLogger();
        $logger->$level($message);
        return;
    }
    
    // Fallback to file logging
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
 * Extract tenant ID from request headers, API keys, or tokens
 * Returns tenant ID or null if not found
 * 
 * @param object|null $db Database connection for API key lookup
 * @return string|null Tenant ID
 */
function extractTenantId($db = null) {
    // Method 1: Check for X-Tenant-ID header (explicit tenant identification)
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    }
    
    if (!empty($headers['x-tenant-id'])) {
        return trim($headers['x-tenant-id']);
    }
    
    // Method 2: Check for API key in Authorization header or X-API-Key header
    $apiKey = null;
    
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $apiKey = trim($matches[1]);
        }
    } elseif (!empty($headers['authorization'])) {
        if (preg_match('/^Bearer\s+(.+)$/i', $headers['authorization'], $matches)) {
            $apiKey = trim($matches[1]);
        }
    } elseif (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = trim($_SERVER['HTTP_X_API_KEY']);
    } elseif (!empty($headers['x-api-key'])) {
        $apiKey = trim($headers['x-api-key']);
    }
    
    // If we have an API key and database connection, look up the tenant
    if ($apiKey && $db) {
        try {
            // Look up API key in admin_api_keys table
            $sql = "SELECT tenant_id FROM admin_api_keys WHERE api_key = ? AND (expires_at IS NULL OR expires_at > datetime('now'))";
            $result = $db->query($sql, [$apiKey]);
            
            if (!empty($result) && isset($result[0]['tenant_id'])) {
                return $result[0]['tenant_id'];
            }
        } catch (Exception $e) {
            error_log("Failed to lookup tenant from API key: " . $e->getMessage());
        }
    }
    
    // Method 3: Check for tenant_id in request parameters (GET/POST)
    if (!empty($_GET['tenant_id'])) {
        return trim($_GET['tenant_id']);
    }
    
    if (!empty($_POST['tenant_id'])) {
        return trim($_POST['tenant_id']);
    }
    
    // No tenant ID found
    return null;
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
    
    // Extract tenant ID from request (for multi-tenant rate limiting)
    $tenantId = extractTenantId($db ?? null);
    
    // Initialize Audit Service (with tenant context if available)
    $auditService = null;
    if ($config['auditing']['enabled']) {
        try {
            $auditService = new AuditService($config['auditing'], $tenantId);
        } catch (Exception $e) {
            log_debug('Failed to initialize AuditService: ' . $e->getMessage(), 'warn');
        }
    }
    
    // Initialize multi-tenant services
    $usageTrackingService = null;
    $quotaService = null;
    $rateLimitService = null;
    $tenantUsageService = null;
    
    if ($config['usage_tracking']['enabled'] ?? false) {
        try {
            require_once __DIR__ . '/includes/UsageTrackingService.php';
            require_once __DIR__ . '/includes/QuotaService.php';
            require_once __DIR__ . '/includes/TenantRateLimitService.php';
            require_once __DIR__ . '/includes/TenantUsageService.php';
            
            $usageTrackingService = new UsageTrackingService($db);
            $quotaService = new QuotaService($db, $usageTrackingService);
            $rateLimitService = new TenantRateLimitService($db);
            $tenantUsageService = new TenantUsageService($db);
        } catch (Exception $e) {
            log_debug('Failed to initialize usage tracking services: ' . $e->getMessage(), 'warn');
        }
    }
    
    // Initialize ChatHandler with observability and multi-tenant services
    $chatHandler = new ChatHandler($config, $agentService, $auditService, $observability, $usageTrackingService, $quotaService, $rateLimitService, $tenantUsageService);
    
    // Start request span if observability is enabled
    $requestSpanId = null;
    if ($observability) {
        $requestSpanId = $observability->handleRequestStart('/chat-unified.php', [
            'api_type' => $config['api_type'],
        ]);
    }

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

    // Extract response_format from request
    $responseFormat = null;
    if ($method === 'GET' && isset($_GET['response_format'])) {
        $rawFormat = $_GET['response_format'];
        if (is_string($rawFormat)) {
            $decoded = json_decode($rawFormat, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $responseFormat = $decoded;
            }
        }
    } elseif ($method === 'POST' && array_key_exists('response_format', $input)) {
        if (is_array($input['response_format'])) {
            $responseFormat = $input['response_format'];
        }
    }

    // Extract whitelabel parameters
    $agentPublicId = null;
    $wlToken = null;
    
    if ($method === 'GET') {
        $agentPublicId = $_GET['agent_public_id'] ?? null;
        $wlToken = $_GET['wl_token'] ?? null;
    } elseif ($method === 'POST') {
        $agentPublicId = $input['agent_public_id'] ?? null;
        $wlToken = $input['wl_token'] ?? null;
    }
    
    // If whitelabel mode (agent_public_id present), enforce strict validation
    $whitelabelMode = !empty($agentPublicId);
    $whitelabelAgent = null;
    
    if ($whitelabelMode) {
        require_once 'includes/WhitelabelTokenService.php';
        
        // Validate whitelabel token
        if (empty($wlToken)) {
            log_debug('Whitelabel request missing wl_token', 'error');
            
            if ($shouldStream) {
                sendSSEEvent('error', [
                    'code' => 'WL_TOKEN_MISSING',
                    'message' => 'Unauthorized: token required. Please reload the page.'
                ]);
                exit();
            } else {
                http_response_code(403);
                echo json_encode([
                    'error' => [
                        'code' => 'WL_TOKEN_MISSING',
                        'message' => 'Unauthorized: token required. Please reload the page.'
                    ]
                ]);
                exit();
            }
        }
        
        // Resolve agent by public ID
        $whitelabelAgent = $agentService ? $agentService->getAgentByPublicId($agentPublicId) : null;
        
        if (!$whitelabelAgent) {
            log_debug("Whitelabel agent not found: {$agentPublicId}", 'error');
            
            if ($shouldStream) {
                sendSSEEvent('error', [
                    'code' => 'WL_AGENT_NOT_FOUND',
                    'message' => 'Agent not found or not published'
                ]);
                exit();
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => [
                        'code' => 'WL_AGENT_NOT_FOUND',
                        'message' => 'Agent not found or not published'
                    ]
                ]);
                exit();
            }
        }
        
        // Verify whitelabel is enabled
        if (!$whitelabelAgent['whitelabel_enabled']) {
            log_debug("Whitelabel not enabled for agent: {$whitelabelAgent['id']}", 'error');
            
            if ($shouldStream) {
                sendSSEEvent('error', [
                    'code' => 'WL_NOT_ENABLED',
                    'message' => 'Agent not published'
                ]);
                exit();
            } else {
                http_response_code(403);
                echo json_encode([
                    'error' => [
                        'code' => 'WL_NOT_ENABLED',
                        'message' => 'Agent not published'
                    ]
                ]);
                exit();
            }
        }
        
        // Validate token if required
        if ($whitelabelAgent['wl_require_signed_requests']) {
            $dbConfig = [
                'database_url' => $config['admin']['database_url'] ?? null,
                'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/data/chatbot.db'
            ];
            $db = new DB($dbConfig);
            $tokenService = new WhitelabelTokenService($db, $config);
            
            $validatedPayload = $tokenService->validateToken(
                $wlToken,
                $agentPublicId,
                $whitelabelAgent['wl_hmac_secret']
            );
            
            if (!$validatedPayload) {
                log_debug("Whitelabel token validation failed for agent: {$agentPublicId}", 'error');
                
                if ($shouldStream) {
                    sendSSEEvent('error', [
                        'code' => 'WL_TOKEN_INVALID',
                        'message' => 'Unauthorized or expired link. Please reload the page.'
                    ]);
                    exit();
                } else {
                    http_response_code(403);
                    echo json_encode([
                        'error' => [
                            'code' => 'WL_TOKEN_INVALID',
                            'message' => 'Unauthorized or expired link. Please reload the page.'
                        ]
                    ]);
                    exit();
                }
            }
            
            log_debug("Whitelabel token validated for agent: {$agentPublicId}");
        }
        
        // Override agentId to enforce whitelabel agent (ignore user-provided agent_id)
        $agentId = $whitelabelAgent['id'];
        
        // Apply CORS for whitelabel
        $allowedOrigins = $whitelabelAgent['allowed_origins'] ?? [];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        
        if (!empty($allowedOrigins) && $origin) {
            if (in_array($origin, $allowedOrigins, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            } else {
                log_debug("CORS denied for origin: {$origin}", 'warn');
                // Still allow same-origin
            }
        }
        
        log_debug("Whitelabel mode: agent={$whitelabelAgent['name']} ({$agentPublicId})");
    }
    
    log_debug("Incoming request method=$method apiType=$apiType conv=$conversationId agentId=$agentId msgLen=" . strlen($message));
    
    // Generate correlation ID for audit trail
    $correlationId = uniqid('req_', true);

    if (empty($message)) {
        log_debug('Validation failed: Message is required', 'warn');
        
        // Audit validation error
        if ($auditService && $auditService->isEnabled() && !empty($conversationId)) {
            $auditService->recordEvent($conversationId, 'validation_error', [
                'correlation_id' => $correlationId,
                'error' => 'Message is required',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        throw new Exception('Message is required');
    }

    if (empty($conversationId)) {
        $conversationId = 'conv_' . uniqid();
    }

    if ($apiType === 'assistants') {
        log_debug('Legacy API type "assistants" detected. Falling back to responses.', 'warn');
        $apiType = 'responses';
    }
    
    // Start audit conversation if enabled
    if ($auditService && $auditService->isEnabled()) {
        $userFingerprint = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $auditService->startConversation(
            $agentId ?: 'default',
            'web',
            $conversationId,
            $userFingerprint,
            [
                'correlation_id' => $correlationId,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'api_type' => $apiType
            ]
        );
    }

    // Validate and sanitize input
    try {
        $chatHandler->validateRequest($message, $conversationId, $fileData, $whitelabelAgent);
    } catch (Exception $e) {
        // Audit validation error
        if ($auditService && $auditService->isEnabled()) {
            $auditService->recordEvent($conversationId, 'validation_error', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        throw $e;
    }

    if ($shouldStream) {
        // Send start event
        sendSSEEvent('start', [
            'conversation_id' => $conversationId,
            'api_type' => $apiType,
            'agent_id' => $agentId
        ]);

        // Route to appropriate handler
        if ($apiType === 'responses') {
            $chatHandler->handleResponsesChat($message, $conversationId, $fileData, $promptId, $promptVersion, $tools, $responseFormat, $agentId, $tenantId);
        } else {
            $chatHandler->handleChatCompletion($message, $conversationId, $agentId, $tenantId);
        }
    } else {
        if ($apiType === 'responses') {
            $result = $chatHandler->handleResponsesChatSync($message, $conversationId, $fileData, $promptId, $promptVersion, $tools, $responseFormat, $agentId, $tenantId);
        } else {
            $result = $chatHandler->handleChatCompletionSync($message, $conversationId, $agentId, $tenantId);
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
    
    // Record error in observability
    if ($observability && isset($requestSpanId)) {
        $observability->handleError($requestSpanId, $e);
    }
    
    // Audit error event
    if (isset($auditService) && $auditService && $auditService->isEnabled() && isset($conversationId)) {
        $auditService->recordEvent($conversationId, 'error', [
            'correlation_id' => $correlationId ?? 'unknown',
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'http_status' => $shouldStream ? null : ($e->getCode() ?: 500),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

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
    
    // End request span with error status
    if ($observability && isset($requestSpanId)) {
        $observability->handleRequestEnd($requestSpanId, '/chat-unified.php', $statusCode ?? 500);
    }
} finally {
    // End successful request span if not already ended
    if ($observability && isset($requestSpanId) && !isset($statusCode)) {
        $observability->handleRequestEnd($requestSpanId, '/chat-unified.php', 200);
    }
    
    if ($shouldStream) {
        sendSSEEvent('close', null);
    }
    exit();
}
?>
