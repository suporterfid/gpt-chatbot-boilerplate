<?php
/**
 * Admin API - Protected endpoint for managing Agents
 * Requires Authorization: Bearer <ADMIN_TOKEN>
 */

require_once 'config.php';
require_once 'includes/DB.php';
require_once 'includes/AgentService.php';
require_once 'includes/OpenAIAdminClient.php';
require_once 'includes/PromptService.php';
require_once 'includes/VectorStoreService.php';
require_once 'includes/OpenAIClient.php';
require_once 'includes/ChatHandler.php';
require_once 'includes/JobQueue.php';
require_once 'includes/AdminAuth.php';
require_once 'includes/AuditService.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Logging helper
function log_admin($message, $level = 'info') {
    global $config;
    $logFile = $config['logging']['file'] ?? __DIR__ . '/logs/chatbot.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $line = "[$ts][$level][Admin][$ip] $message\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
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

// Authentication check - supports both legacy ADMIN_TOKEN and AdminAuth
function checkAuthentication($config, $adminAuth) {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    } elseif (function_exists('apache_request_headers')) {
        $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
    }

    // Try to get Authorization header from multiple sources
    // Apache/PHP can place it in different locations depending on configuration
    $authHeader = '';

    // Method 1: Direct HTTP_AUTHORIZATION
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // Method 2: REDIRECT_HTTP_AUTHORIZATION (when using mod_rewrite)
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // Method 3: getallheaders()/apache_request_headers()
    elseif (!empty($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }
    // Method 4: PHP_AUTH_* variables (Basic/Digest auth fallback)
    elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $authHeader = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
    }

    $token = null;

    if (!empty($authHeader)) {
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } else {
            log_admin('Invalid Authorization header format', 'warn');
        }
    }

    // Fallback: custom header or explicit admin_token/token parameter
    if (empty($token)) {
        $fallbackToken = null;

        if (isset($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
            $fallbackToken = $_SERVER['HTTP_X_ADMIN_TOKEN'];
        } elseif (!empty($headers['x-admin-token'])) {
            $fallbackToken = $headers['x-admin-token'];
        } elseif (isset($_GET['admin_token'])) {
            $fallbackToken = $_GET['admin_token'];
        } elseif (isset($_POST['admin_token'])) {
            $fallbackToken = $_POST['admin_token'];
        } elseif (isset($_GET['token'])) {
            $fallbackToken = $_GET['token'];
        } elseif (isset($_POST['token'])) {
            $fallbackToken = $_POST['token'];
        }

        if (!empty($fallbackToken)) {
            $token = trim($fallbackToken);
        }
    }

    if (empty($token)) {
        log_admin('Missing admin token', 'warn');
        // Only log server vars in debug mode
        if (isset($config['debug']) && $config['debug']) {
            $serverKeys = array_keys($_SERVER);
            log_admin('Available SERVER keys: ' . implode(', ', $serverKeys), 'debug');
        }
        sendError('Authorization token required', 403);
    }

    // Try to authenticate with AdminAuth (supports both legacy token and API keys)
    try {
        $user = $adminAuth->authenticate($token);
        
        if (!$user) {
            log_admin('Invalid authentication token', 'warn');
            sendError('Invalid authentication token', 403);
        }
        
        return $user; // Return authenticated user data
    } catch (Exception $e) {
        log_admin('Authentication error: ' . $e->getMessage(), 'error');
        sendError('Authentication failed', 403);
    }
}

// Check if authenticated user has required permission
function requirePermission($user, $permission, $adminAuth) {
    try {
        $adminAuth->requirePermission($user, $permission);
    } catch (Exception $e) {
        log_admin("Permission denied for user {$user['email']}: $permission", 'warn');
        sendError($e->getMessage(), 403);
    }
}

// Send JSON response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(['data' => $data]);
    exit();
}

// Send error response
function sendError($message, $statusCode = 400, $code = null) {
    http_response_code($statusCode);
    echo json_encode([
        'error' => [
            'message' => $message,
            'code' => $code ?? 'ERROR',
            'status' => $statusCode
        ]
    ]);
    exit();
}

// Get request body
function getRequestBody() {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        return [];
    }
    
    $decoded = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON in request body', 400);
    }
    
    return $decoded ?? [];
}

// Rate limiting for admin endpoints
function checkAdminRateLimit($config) {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $requestsFile = sys_get_temp_dir() . '/admin_requests_' . md5($clientIP);
    
    $currentTime = time();
    // Admin endpoints get more generous limits: 300 req/min (vs 60 for chat)
    $rateLimit = $config['admin']['rate_limit_requests'] ?? 300;
    $window = $config['admin']['rate_limit_window'] ?? 60;
    
    // Read existing requests
    $requests = [];
    if (file_exists($requestsFile)) {
        $requests = json_decode(file_get_contents($requestsFile), true) ?: [];
    }
    
    // Remove old requests outside the window
    $requests = array_filter($requests, function($timestamp) use ($currentTime, $window) {
        return $currentTime - $timestamp < $window;
    });
    
    // Check if rate limit exceeded
    if (count($requests) >= $rateLimit) {
        log_admin("Rate limit exceeded for IP: $clientIP", 'warn');
        sendError('Rate limit exceeded. Please wait before making another request.', 429);
    }
    
    // Add current request
    $requests[] = $currentTime;
    file_put_contents($requestsFile, json_encode($requests));
}

try {
    // Initialize database first (needed for authentication)
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/data/chatbot.db'
    ];
    
    $db = new DB($dbConfig);
    
    // Run migrations if needed
    try {
        $db->runMigrations(__DIR__ . '/db/migrations');
    } catch (Exception $e) {
        log_admin('Migration error: ' . $e->getMessage(), 'error');
        // Continue anyway - migrations might already be run
    }
    
    // Initialize AdminAuth before authentication check
    $adminAuth = new AdminAuth($db, $config);
    
    // Determine action and request method early so we can decide on authentication requirements
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // Public endpoints that don't require authentication (e.g., health checks for login screen)
    $publicActions = ['health'];
    $requiresAuth = !in_array($action, $publicActions, true);

    // Check authentication and get user when required
    $authenticatedUser = null;
    if ($requiresAuth) {
        $authenticatedUser = checkAuthentication($config, $adminAuth);

        // Check rate limit (after authentication, before processing request)
        checkAdminRateLimit($config);
    }

    // Initialize OpenAI Admin Client
    $openaiClient = null;
    if (!empty($config['openai']['api_key'])) {
        $openaiClient = new OpenAIAdminClient($config['openai']);
    }

    // Initialize services with tenant context
    $tenantId = $authenticatedUser['tenant_id'] ?? null;
    $agentService = new AgentService($db, $tenantId);
    $promptService = new PromptService($db, $openaiClient);
    $vectorStoreService = new VectorStoreService($db, $openaiClient);
    $jobQueue = new JobQueue($db);
    $tenantService = null;
    
    // Only initialize TenantService if user is super-admin
    if ($authenticatedUser && $authenticatedUser['role'] === 'super-admin') {
        require_once __DIR__ . '/includes/TenantService.php';
        $tenantService = new TenantService($db);
    }
    
    // Initialize Audit Service
    $auditService = null;
    if ($config['auditing']['enabled']) {
        try {
            $auditService = new AuditService($config['auditing']);
        } catch (Exception $e) {
            log_admin('Failed to initialize AuditService: ' . $e->getMessage(), 'warn');
        }
    }

    $logUser = $authenticatedUser['email'] ?? 'anonymous';
    log_admin("$method /admin-api.php?action=$action [user: $logUser]");
    
    // Route to appropriate handler
    switch ($action) {
        case 'list_agents':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            // Read permission required (all roles have this)
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            $filters = [];
            if (isset($_GET['name'])) {
                $filters['name'] = $_GET['name'];
            }
            if (isset($_GET['api_type'])) {
                $filters['api_type'] = $_GET['api_type'];
            }
            $agents = $agentService->listAgents($filters);
            sendResponse($agents);
            break;
            
        case 'get_agent':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            $agent = $agentService->getAgent($id);
            if (!$agent) {
                sendError('Agent not found', 404);
            }
            sendResponse($agent);
            break;
            
        case 'create_agent':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'create', $adminAuth);
            
            $data = getRequestBody();
            $agent = $agentService->createAgent($data);
            log_admin('Agent created: ' . $agent['id'] . ' (' . $agent['name'] . ')');
            sendResponse($agent, 201);
            break;
            
        case 'update_agent':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            $data = getRequestBody();
            $agent = $agentService->updateAgent($id, $data);
            log_admin('Agent updated: ' . $id);
            sendResponse($agent);
            break;
            
        case 'delete_agent':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'delete', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            $agentService->deleteAgent($id);
            log_admin('Agent deleted: ' . $id);
            sendResponse(['success' => true, 'message' => 'Agent deleted']);
            break;
            
        case 'make_default':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            $agentService->setDefaultAgent($id);
            log_admin('Default agent set: ' . $id);
            sendResponse(['success' => true, 'message' => 'Default agent set']);
            break;
        
        // ==================== Whitelabel Publishing Endpoints ====================
        
        case 'enable_whitelabel':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            
            $data = getRequestBody();
            $agent = $agentService->enableWhitelabel($id, $data);
            log_admin('Whitelabel enabled for agent: ' . $id);
            sendResponse($agent);
            break;
            
        case 'disable_whitelabel':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            
            $agent = $agentService->disableWhitelabel($id);
            log_admin('Whitelabel disabled for agent: ' . $id);
            sendResponse($agent);
            break;
            
        case 'rotate_whitelabel_secret':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            
            $agent = $agentService->rotateHmacSecret($id);
            log_admin('Whitelabel secret rotated for agent: ' . $id);
            sendResponse($agent);
            break;
            
        case 'update_whitelabel_config':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            
            $data = getRequestBody();
            $agent = $agentService->updateWhitelabelConfig($id, $data);
            log_admin('Whitelabel config updated for agent: ' . $id);
            sendResponse($agent);
            break;
            
        case 'get_whitelabel_url':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            
            $agent = $agentService->getAgent($id);
            if (!$agent) {
                sendError('Agent not found', 404);
            }
            
            if (!$agent['whitelabel_enabled'] || !$agent['agent_public_id']) {
                sendError('Whitelabel not enabled for this agent', 400);
            }
            
            // Build the whitelabel URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = $protocol . '://' . $host;
            
            $url = $baseUrl . '/public/whitelabel.php?id=' . urlencode($agent['agent_public_id']);
            
            if (!empty($agent['vanity_path'])) {
                $vanityUrl = $baseUrl . '/public/whitelabel.php?path=' . urlencode($agent['vanity_path']);
            } else {
                $vanityUrl = null;
            }
            
            if (!empty($agent['custom_domain'])) {
                $customUrl = 'https://' . $agent['custom_domain'];
            } else {
                $customUrl = null;
            }
            
            sendResponse([
                'url' => $url,
                'vanity_url' => $vanityUrl,
                'custom_domain_url' => $customUrl,
                'agent_public_id' => $agent['agent_public_id']
            ]);
            break;
        
        // ==================== Agent Channels Endpoints ====================
        
        case 'list_agent_channels':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            $agentId = $_GET['agent_id'] ?? '';
            if (empty($agentId)) {
                sendError('Agent ID required', 400);
            }
            
            $channels = $agentService->listAgentChannels($agentId);
            sendResponse($channels);
            break;
        
        case 'get_agent_channel':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            $agentId = $_GET['agent_id'] ?? '';
            $channel = $_GET['channel'] ?? '';
            
            if (empty($agentId) || empty($channel)) {
                sendError('Agent ID and channel are required', 400);
            }
            
            $channelConfig = $agentService->getAgentChannel($agentId, $channel);
            if (!$channelConfig) {
                sendError('Channel configuration not found', 404);
            }
            
            sendResponse($channelConfig);
            break;
        
        case 'upsert_agent_channel':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            $agentId = $_GET['agent_id'] ?? '';
            $channel = $_GET['channel'] ?? '';
            
            if (empty($agentId) || empty($channel)) {
                sendError('Agent ID and channel are required', 400);
            }
            
            $data = getRequestBody();
            $channelConfig = $agentService->upsertAgentChannel($agentId, $channel, $data);
            log_admin("Channel configuration updated: agent=$agentId, channel=$channel");
            sendResponse($channelConfig, 200);
            break;
        
        case 'delete_agent_channel':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'delete', $adminAuth);
            
            $agentId = $_GET['agent_id'] ?? '';
            $channel = $_GET['channel'] ?? '';
            
            if (empty($agentId) || empty($channel)) {
                sendError('Agent ID and channel are required', 400);
            }
            
            $agentService->deleteAgentChannel($agentId, $channel);
            log_admin("Channel configuration deleted: agent=$agentId, channel=$channel");
            sendResponse(['success' => true, 'message' => 'Channel configuration deleted']);
            break;
        
        case 'test_channel_send':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            $agentId = $_GET['agent_id'] ?? '';
            $channel = $_GET['channel'] ?? '';
            
            if (empty($agentId) || empty($channel)) {
                sendError('Agent ID and channel are required', 400);
            }
            
            $data = getRequestBody();
            $to = $data['to'] ?? '';
            $message = $data['message'] ?? 'Test message from chatbot admin';
            
            if (empty($to)) {
                sendError('Recipient (to) is required', 400);
            }
            
            // Load channel manager
            require_once __DIR__ . '/includes/ChannelManager.php';
            $channelManager = new ChannelManager($db);
            
            try {
                $result = $channelManager->sendText(
                    $agentId,
                    $channel,
                    $to,
                    $message,
                    'test_' . uniqid()
                );
                
                log_admin("Test message sent: agent=$agentId, channel=$channel, to=$to");
                sendResponse($result);
            } catch (Exception $e) {
                log_admin("Test message failed: " . $e->getMessage(), 'error');
                sendError('Failed to send test message: ' . $e->getMessage(), 500);
            }
            break;
        
        case 'list_channel_sessions':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            $agentId = $_GET['agent_id'] ?? '';
            if (empty($agentId)) {
                sendError('Agent ID required', 400);
            }
            
            $channel = $_GET['channel'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            require_once __DIR__ . '/includes/ChannelManager.php';
            $channelManager = new ChannelManager($db);
            
            $sessions = $channelManager->getSessionService()->listSessions($agentId, $channel, $limit, $offset);
            sendResponse($sessions);
            break;
        
        // ==================== Prompts Endpoints ====================
        
        case 'list_prompts':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            $filters = [];
            if (isset($_GET['name'])) {
                $filters['name'] = $_GET['name'];
            }
            if (isset($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            $prompts = $promptService->listPrompts($filters);
            sendResponse($prompts);
            break;
            
        case 'get_prompt':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Prompt ID required', 400);
            }
            $prompt = $promptService->getPrompt($id);
            if (!$prompt) {
                sendError('Prompt not found', 404);
            }
            sendResponse($prompt);
            break;
            
        case 'create_prompt':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $data = getRequestBody();
            $prompt = $promptService->createPrompt($data);
            log_admin('Prompt created: ' . $prompt['id'] . ' (' . $prompt['name'] . ')');
            sendResponse($prompt, 201);
            break;
            
        case 'update_prompt':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Prompt ID required', 400);
            }
            $data = getRequestBody();
            $prompt = $promptService->updatePrompt($id, $data);
            log_admin('Prompt updated: ' . $id);
            sendResponse($prompt);
            break;
            
        case 'delete_prompt':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Prompt ID required', 400);
            }
            $promptService->deletePrompt($id);
            log_admin('Prompt deleted: ' . $id);
            sendResponse(['success' => true, 'message' => 'Prompt deleted']);
            break;
            
        case 'list_prompt_versions':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Prompt ID required', 400);
            }
            $filters = [];
            if (isset($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            $versions = $promptService->listPromptVersions($id, $filters);
            sendResponse($versions);
            break;
            
        case 'create_prompt_version':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Prompt ID required', 400);
            }
            $data = getRequestBody();
            $version = $promptService->createPromptVersion($id, $data);
            log_admin('Prompt version created: ' . $version['id']);
            sendResponse($version, 201);
            break;
            
        case 'sync_prompts':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $synced = $promptService->syncPromptsFromOpenAI();
            log_admin('Synced ' . $synced . ' prompts from OpenAI');
            sendResponse(['synced' => $synced, 'message' => "Synced $synced prompts from OpenAI"]);
            break;
        
        // ==================== Vector Stores Endpoints ====================
        
        case 'list_vector_stores':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            $filters = [];
            if (isset($_GET['name'])) {
                $filters['name'] = $_GET['name'];
            }
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            $stores = $vectorStoreService->listVectorStores($filters);
            sendResponse($stores);
            break;
            
        case 'get_vector_store':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Vector store ID required', 400);
            }
            $store = $vectorStoreService->getVectorStore($id);
            if (!$store) {
                sendError('Vector store not found', 404);
            }
            sendResponse($store);
            break;
            
        case 'create_vector_store':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $data = getRequestBody();
            $store = $vectorStoreService->createVectorStore($data);
            log_admin('Vector store created: ' . $store['id'] . ' (' . $store['name'] . ')');
            sendResponse($store, 201);
            break;
            
        case 'update_vector_store':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Vector store ID required', 400);
            }
            $data = getRequestBody();
            $store = $vectorStoreService->updateVectorStore($id, $data);
            log_admin('Vector store updated: ' . $id);
            sendResponse($store);
            break;
            
        case 'delete_vector_store':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Vector store ID required', 400);
            }
            $vectorStoreService->deleteVectorStore($id);
            log_admin('Vector store deleted: ' . $id);
            sendResponse(['success' => true, 'message' => 'Vector store deleted']);
            break;
            
        case 'list_vector_store_files':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Vector store ID required', 400);
            }
            $filters = [];
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            $files = $vectorStoreService->listFiles($id, $filters);
            sendResponse($files);
            break;
            
        case 'add_vector_store_file':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Vector store ID required', 400);
            }
            $data = getRequestBody();
            $file = $vectorStoreService->addFile($id, $data);
            log_admin('File added to vector store: ' . $file['id']);
            sendResponse($file, 201);
            break;
            
        case 'delete_vector_store_file':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            $id = $_GET['id'] ?? '';
            $fileId = $_GET['file_id'] ?? '';
            if (empty($id) || empty($fileId)) {
                sendError('Vector store ID and file ID required', 400);
            }
            $vectorStoreService->deleteFile($fileId);
            log_admin('File deleted from vector store: ' . $fileId);
            sendResponse(['success' => true, 'message' => 'File deleted']);
            break;
            
        case 'poll_file_status':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            $fileId = $_GET['file_id'] ?? '';
            if (empty($fileId)) {
                sendError('File ID required', 400);
            }
            $file = $vectorStoreService->pollFileStatus($fileId);
            sendResponse($file);
            break;
            
        case 'sync_vector_stores':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            $synced = $vectorStoreService->syncVectorStoresFromOpenAI();
            log_admin('Synced ' . $synced . ' vector stores from OpenAI');
            sendResponse(['synced' => $synced, 'message' => "Synced $synced vector stores from OpenAI"]);
            break;
        
        // ==================== Files Endpoints ====================
        
        case 'list_files':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            if (!$openaiClient) {
                sendError('OpenAI client not configured', 500);
            }
            
            // Validate purpose parameter against OpenAI allowed values
            $purpose = $_GET['purpose'] ?? 'assistants';
            $allowedPurposes = ['assistants', 'fine-tune', 'batch'];
            if (!in_array($purpose, $allowedPurposes)) {
                sendError('Invalid purpose. Allowed values: ' . implode(', ', $allowedPurposes), 400);
            }
            
            try {
                $files = $openaiClient->listFiles($purpose);
                sendResponse($files);
            } catch (Exception $e) {
                log_admin('Failed to list files: ' . $e->getMessage(), 'error');
                sendError('Failed to list files: ' . $e->getMessage(), 500);
            }
            break;
            
        case 'upload_file':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'create', $adminAuth);
            
            if (!$openaiClient) {
                sendError('OpenAI client not configured', 500);
            }
            
            $data = getRequestBody();
            if (empty($data['name']) || empty($data['file_data'])) {
                sendError('File name and file_data (base64) are required', 400);
            }
            
            // Validate purpose parameter
            $purpose = $data['purpose'] ?? 'assistants';
            $allowedPurposes = ['assistants', 'fine-tune', 'batch'];
            if (!in_array($purpose, $allowedPurposes)) {
                sendError('Invalid purpose. Allowed values: ' . implode(', ', $allowedPurposes), 400);
            }
            
            try {
                $file = $openaiClient->uploadFileFromBase64(
                    $data['name'],
                    $data['file_data'],
                    $purpose
                );
                log_admin('File uploaded: ' . $file['id'] . ' (' . $data['name'] . ')');
                sendResponse($file, 201);
            } catch (Exception $e) {
                log_admin('Failed to upload file: ' . $e->getMessage(), 'error');
                sendError('Failed to upload file: ' . $e->getMessage(), 500);
            }
            break;
            
        case 'delete_file':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'delete', $adminAuth);
            
            if (!$openaiClient) {
                sendError('OpenAI client not configured', 500);
            }
            
            $fileId = $_GET['id'] ?? '';
            if (empty($fileId)) {
                sendError('File ID required', 400);
            }
            
            try {
                $result = $openaiClient->deleteFile($fileId);
                log_admin('File deleted: ' . $fileId);
                sendResponse(['success' => true, 'message' => 'File deleted']);
            } catch (Exception $e) {
                log_admin('Failed to delete file: ' . $e->getMessage(), 'error');
                sendError('Failed to delete file: ' . $e->getMessage(), 500);
            }
            break;
        
        // ==================== Models Endpoints ====================
        
        case 'list_models':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            if (!$openaiClient) {
                sendError('OpenAI client not configured', 500);
            }
            
            try {
                $models = $openaiClient->listModels();
                sendResponse($models);
            } catch (Exception $e) {
                log_admin('Failed to list models: ' . $e->getMessage(), 'error');
                sendError('Failed to list models: ' . $e->getMessage(), 500);
            }
            break;
        
        // ==================== Health & Utility Endpoints ====================
        
        case 'health':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            $health = [
                'status' => 'ok',
                'timestamp' => date('c'),
                'checks' => [
                    'database' => ['status' => 'unknown', 'message' => null],
                    'openai' => ['status' => 'unknown', 'message' => null],
                    'worker' => ['status' => 'unknown', 'message' => null],
                    'queue' => ['status' => 'unknown', 'message' => null]
                ],
                'metrics' => [
                    'queue_depth' => 0,
                    'worker_last_seen' => null,
                    'pending_jobs' => 0,
                    'running_jobs' => 0,
                    'failed_jobs_24h' => 0
                ],
                'worker' => [
                    'enabled' => $config['admin']['jobs_enabled'] ?? true,
                    'stats' => []
                ]
            ];
            
            // Test database
            try {
                $db->query("SELECT 1");
                $health['checks']['database']['status'] = 'healthy';
                $health['checks']['database']['message'] = 'Database connection successful';
                
                // Get worker stats if database is healthy
                try {
                    $stats = $jobQueue->getStats();
                    $health['worker']['stats'] = $stats;
                    $health['metrics']['queue_depth'] = $stats['pending'] + $stats['running'];
                    $health['metrics']['pending_jobs'] = $stats['pending'];
                    $health['metrics']['running_jobs'] = $stats['running'];
                    
                    // Check queue depth threshold
                    if ($health['metrics']['queue_depth'] > 100) {
                        $health['checks']['queue']['status'] = 'warning';
                        $health['checks']['queue']['message'] = 'High queue depth: ' . $health['metrics']['queue_depth'];
                        if ($health['status'] === 'ok') {
                            $health['status'] = 'degraded';
                        }
                    } else {
                        $health['checks']['queue']['status'] = 'healthy';
                        $health['checks']['queue']['message'] = 'Queue depth normal';
                    }
                    
                    // Get failed jobs in last 24 hours
                    $result = $db->query("
                        SELECT COUNT(*) as count 
                        FROM jobs 
                        WHERE status = 'failed' 
                        AND created_at > datetime('now', '-1 day')
                    ");
                    $row = $result[0] ?? null;
                    $health['metrics']['failed_jobs_24h'] = $row ? (int)$row['count'] : 0;
                    
                    // Get worker last seen timestamp
                    $result = $db->query("
                        SELECT MAX(updated_at) as last_update 
                        FROM jobs 
                        WHERE status IN ('running', 'completed', 'failed')
                    ");
                    $row = $result[0] ?? null;
                    if ($row && $row['last_update']) {
                        $health['metrics']['worker_last_seen'] = $row['last_update'];
                        $lastUpdate = strtotime($row['last_update']);
                        $secondsSinceLastJob = time() - $lastUpdate;
                        
                        // Check worker health (stale if no activity in 5 minutes)
                        if ($secondsSinceLastJob > 300 && $health['worker']['enabled']) {
                            $health['checks']['worker']['status'] = 'unhealthy';
                            $health['checks']['worker']['message'] = 'Worker inactive for ' . round($secondsSinceLastJob / 60) . ' minutes';
                            $health['status'] = 'degraded';
                        } else {
                            $health['checks']['worker']['status'] = 'healthy';
                            $health['checks']['worker']['message'] = 'Worker active';
                        }
                    } else if ($health['worker']['enabled']) {
                        $health['checks']['worker']['status'] = 'unknown';
                        $health['checks']['worker']['message'] = 'No worker activity recorded';
                    }
                    
                } catch (Exception $e) {
                    log_admin('Failed to get worker stats: ' . $e->getMessage(), 'warn');
                    $health['checks']['worker']['status'] = 'unhealthy';
                    $health['checks']['worker']['message'] = 'Failed to query worker stats';
                    $health['status'] = 'degraded';
                }
            } catch (Exception $e) {
                $health['checks']['database']['status'] = 'unhealthy';
                $health['checks']['database']['message'] = 'Database connection failed';
                $health['status'] = 'unhealthy';
            }
            
            // Test OpenAI (optional, non-critical)
            if ($openaiClient) {
                try {
                    $result = $openaiClient->listVectorStores(1);
                    $health['checks']['openai']['status'] = 'healthy';
                    $health['checks']['openai']['message'] = 'OpenAI API accessible';
                } catch (Exception $e) {
                    $health['checks']['openai']['status'] = 'unhealthy';
                    $health['checks']['openai']['message'] = 'OpenAI API not accessible';
                    // OpenAI failure is degraded but not critical
                    if ($health['status'] === 'ok') {
                        $health['status'] = 'degraded';
                    }
                }
            }
            
            sendResponse($health);
            break;
            
        case 'test_agent':
            if ($method !== 'POST' && $method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            // This endpoint streams a test response
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            
            // Support both POST body and GET/POST parameters for message
            $message = 'Hello, this is a test message.';
            if ($method === 'POST') {
                $data = getRequestBody();
                $message = $data['message'] ?? $message;
            } elseif (isset($_GET['message'])) {
                $message = $_GET['message'];
            } elseif (isset($_POST['message'])) {
                $message = $_POST['message'];
            }
            
            // Get agent
            $agent = $agentService->getAgent($id);
            if (!$agent) {
                sendError('Agent not found', 404);
            }
            
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            
            // Create ChatHandler with agent config
            $chatHandler = new ChatHandler($config, $agentService);
            
            // Send start event
            echo "event: message\n";
            echo "data: " . json_encode([
                'type' => 'start',
                'agent_id' => $id,
                'agent_name' => $agent['name']
            ]) . "\n\n";
            flush();
            
            try {
                // Determine which API to use
                $apiType = $agent['api_type'] ?? 'responses';
                
                if ($apiType === 'responses') {
                    // Use Responses API
                    $chatHandler->handleResponsesChat(
                        $message,
                        'test-' . time(),
                        [],
                        null,
                        null,
                        null,
                        $id
                    );
                } else {
                    // Use Chat Completions API
                    $chatHandler->handleChatCompletion(
                        $message,
                        'test-' . time(),
                        $id
                    );
                }
            } catch (Exception $e) {
                echo "event: message\n";
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]) . "\n\n";
                flush();
            }
            
            exit();
            break;
        
        // ==================== Metrics Endpoint ====================
        
        case 'metrics':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            // Prometheus text format
            header('Content-Type: text/plain; version=0.0.4');
            
            try {
                $stats = $jobQueue->getStats();
                
                echo "# HELP jobs_pending_total Number of pending jobs\n";
                echo "# TYPE jobs_pending_total gauge\n";
                echo "jobs_pending_total " . $stats['pending'] . "\n";
                
                echo "# HELP jobs_running_total Number of running jobs\n";
                echo "# TYPE jobs_running_total gauge\n";
                echo "jobs_running_total " . $stats['running'] . "\n";
                
                echo "# HELP jobs_completed_total Number of completed jobs\n";
                echo "# TYPE jobs_completed_total counter\n";
                echo "jobs_completed_total " . $stats['completed'] . "\n";
                
                echo "# HELP jobs_failed_total Number of failed jobs\n";
                echo "# TYPE jobs_failed_total counter\n";
                echo "jobs_failed_total " . $stats['failed'] . "\n";
                
                // Database connectivity
                try {
                    $db->query("SELECT 1");
                    $dbUp = 1;
                } catch (Exception $e) {
                    $dbUp = 0;
                }
                
                echo "# HELP database_up Database connectivity status\n";
                echo "# TYPE database_up gauge\n";
                echo "database_up $dbUp\n";
                
            } catch (Exception $e) {
                echo "# ERROR: " . $e->getMessage() . "\n";
            }
            
            exit();
            break;
        
        // ==================== Job Management Endpoints ====================
        
        case 'list_jobs':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            $filters = [];
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['type'])) {
                $filters['type'] = $_GET['type'];
            }
            if (isset($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            if (isset($_GET['offset'])) {
                $filters['offset'] = (int)$_GET['offset'];
            }
            
            $jobs = $jobQueue->listJobs($filters);
            sendResponse($jobs);
            break;
        
        case 'get_job':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            $jobId = $_GET['id'] ?? '';
            if (empty($jobId)) {
                sendError('Job ID required', 400);
            }
            
            $job = $jobQueue->getJob($jobId);
            if (!$job) {
                sendError('Job not found', 404);
            }
            
            sendResponse($job);
            break;
        
        case 'retry_job':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            $jobId = $_GET['id'] ?? '';
            if (empty($jobId)) {
                sendError('Job ID required', 400);
            }
            
            $jobQueue->retryJob($jobId);
            log_admin("Job retried: $jobId");
            sendResponse(['success' => true, 'message' => 'Job retried']);
            break;
        
        case 'cancel_job':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            $jobId = $_GET['id'] ?? '';
            if (empty($jobId)) {
                sendError('Job ID required', 400);
            }
            
            $jobQueue->cancelJob($jobId);
            log_admin("Job cancelled: $jobId");
            sendResponse(['success' => true, 'message' => 'Job cancelled']);
            break;
        
        case 'job_stats':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            $stats = $jobQueue->getStats();
            sendResponse($stats);
            break;
        
        // ==================== Dead Letter Queue Endpoints ====================
        
        case 'list_dlq':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            requirePermission($authenticatedUser, 'manage_jobs', $adminAuth);
            
            $filters = [];
            if (isset($_GET['type'])) {
                $filters['type'] = $_GET['type'];
            }
            if (isset($_GET['include_requeued'])) {
                $filters['include_requeued'] = filter_var($_GET['include_requeued'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($_GET['limit'])) {
                $filters['limit'] = (int)$_GET['limit'];
            }
            if (isset($_GET['offset'])) {
                $filters['offset'] = (int)$_GET['offset'];
            }
            
            $dlqEntries = $jobQueue->listDLQ($filters);
            sendResponse($dlqEntries);
            break;
        
        case 'get_dlq_entry':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            requirePermission($authenticatedUser, 'manage_jobs', $adminAuth);
            
            $dlqId = $_GET['id'] ?? '';
            if (empty($dlqId)) {
                sendError('DLQ entry ID required', 400);
            }
            
            $entry = $jobQueue->getDLQEntry($dlqId);
            if (!$entry) {
                sendError('DLQ entry not found', 404);
            }
            
            sendResponse($entry);
            break;
        
        case 'requeue_dlq':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            requirePermission($authenticatedUser, 'manage_jobs', $adminAuth);
            
            $dlqId = $_GET['id'] ?? '';
            if (empty($dlqId)) {
                sendError('DLQ entry ID required', 400);
            }
            
            $resetAttempts = isset($_GET['reset_attempts']) 
                ? filter_var($_GET['reset_attempts'], FILTER_VALIDATE_BOOLEAN) 
                : true;
            
            try {
                $newJobId = $jobQueue->requeueFromDLQ($dlqId, $resetAttempts);
                log_admin("DLQ entry requeued: $dlqId -> new job: $newJobId by {$authenticatedUser['email']}");
                sendResponse([
                    'success' => true, 
                    'message' => 'Job requeued from DLQ',
                    'job_id' => $newJobId
                ]);
            } catch (Exception $e) {
                sendError($e->getMessage(), 400);
            }
            break;
        
        case 'delete_dlq_entry':
            if ($method !== 'DELETE' && $method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            requirePermission($authenticatedUser, 'manage_jobs', $adminAuth);
            
            $dlqId = $_GET['id'] ?? '';
            if (empty($dlqId)) {
                sendError('DLQ entry ID required', 400);
            }
            
            try {
                $jobQueue->deleteDLQEntry($dlqId);
                log_admin("DLQ entry deleted: $dlqId by {$authenticatedUser['email']}");
                sendResponse(['success' => true, 'message' => 'DLQ entry deleted']);
            } catch (Exception $e) {
                sendError($e->getMessage(), 400);
            }
            break;
        
        // ==================== User Management Endpoints (RBAC) ====================
        
        case 'list_users':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            // Require manage_users permission (super-admin only)
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            $users = $adminAuth->listUsers();
            sendResponse($users);
            break;
        
        case 'create_user':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            // Require manage_users permission
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            $data = getRequestBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? AdminAuth::ROLE_ADMIN;
            
            if (empty($email) || empty($password)) {
                sendError('Email and password are required', 400);
            }
            
            try {
                $user = $adminAuth->createUser($email, $password, $role);
                log_admin("User created: $email (role: $role)");
                sendResponse($user, 201);
            } catch (Exception $e) {
                if ($e->getCode() === 409) {
                    sendError($e->getMessage(), 409);
                }
                throw $e;
            }
            break;
        
        case 'get_user':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            // Users can view their own profile, super-admins can view all
            $userId = $_GET['id'] ?? '';
            if (empty($userId)) {
                sendError('User ID required', 400);
            }
            
            // Check if viewing own profile or has manage_users permission
            if ($userId !== $authenticatedUser['id']) {
                requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            }
            
            $user = $adminAuth->getUser($userId);
            if (!$user) {
                sendError('User not found', 404);
            }
            
            sendResponse($user);
            break;
        
        case 'update_user_role':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendError('Method not allowed', 405);
            }
            
            // Require manage_users permission
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            $userId = $_GET['id'] ?? '';
            $data = getRequestBody();
            $role = $data['role'] ?? '';
            
            if (empty($userId) || empty($role)) {
                sendError('User ID and role are required', 400);
            }
            
            $adminAuth->updateUserRole($userId, $role);
            log_admin("User role updated: $userId -> $role");
            sendResponse(['success' => true, 'message' => 'User role updated']);
            break;
        
        case 'deactivate_user':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            // Require manage_users permission
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            $userId = $_GET['id'] ?? '';
            if (empty($userId)) {
                sendError('User ID required', 400);
            }
            
            // Prevent deactivating self
            if ($userId === $authenticatedUser['id']) {
                sendError('Cannot deactivate your own account', 400);
            }
            
            $adminAuth->deactivateUser($userId);
            log_admin("User deactivated: $userId");
            sendResponse(['success' => true, 'message' => 'User deactivated']);
            break;
        
        case 'generate_api_key':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            $data = getRequestBody();
            $userId = $data['user_id'] ?? $authenticatedUser['id'];
            $name = $data['name'] ?? 'API Key';
            $expiresInDays = isset($data['expires_in_days']) ? (int)$data['expires_in_days'] : null;
            
            // Users can generate keys for themselves, super-admins for anyone
            if ($userId !== $authenticatedUser['id']) {
                requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            }
            
            $apiKey = $adminAuth->generateApiKey($userId, $name, $expiresInDays);
            log_admin("API key generated for user: $userId");
            
            // Return the key (only time it's visible!)
            sendResponse($apiKey, 201);
            break;
        
        case 'list_api_keys':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            $userId = $_GET['user_id'] ?? $authenticatedUser['id'];
            
            // Users can list their own keys, super-admins can list all
            if ($userId !== $authenticatedUser['id']) {
                requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            }
            
            $keys = $adminAuth->listApiKeys($userId);
            sendResponse($keys);
            break;
        
        case 'revoke_api_key':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            $keyId = $_GET['id'] ?? '';
            if (empty($keyId)) {
                sendError('API key ID required', 400);
            }
            
            // Check ownership: users can only revoke their own keys unless super-admin
            $key = $adminAuth->getApiKey($keyId);
            if (!$key) {
                sendError('API key not found', 404);
            }
            
            // Allow if user owns the key OR has manage_users permission
            if ($key['user_id'] !== $authenticatedUser['id']) {
                requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            }
            
            $adminAuth->revokeApiKey($keyId);
            log_admin("API key revoked: $keyId");
            sendResponse(['success' => true, 'message' => 'API key revoked']);
            break;
        
        case 'rotate_admin_token':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            // Require super-admin permission
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            try {
                // Generate new secure token
                $newToken = bin2hex(random_bytes(32));
                
                // Update .env file
                $envPath = __DIR__ . '/.env';
                if (!file_exists($envPath)) {
                    sendError('.env file not found', 500);
                }
                
                $envContent = file_get_contents($envPath);
                if ($envContent === false) {
                    sendError('Failed to read .env file', 500);
                }
                
                // Replace ADMIN_TOKEN value
                $updatedEnv = preg_replace(
                    '/^ADMIN_TOKEN=.*/m',
                    'ADMIN_TOKEN=' . $newToken,
                    $envContent
                );
                
                if ($updatedEnv === null || $updatedEnv === $envContent) {
                    sendError('Failed to update ADMIN_TOKEN in .env', 500);
                }
                
                // Write back to .env
                if (file_put_contents($envPath, $updatedEnv) === false) {
                    sendError('Failed to write .env file', 500);
                }
                
                // Reload config to use new token
                $config['admin']['token'] = $newToken;
                
                log_admin("ADMIN_TOKEN rotated by {$authenticatedUser['email']}");
                
                sendResponse([
                    'success' => true,
                    'new_token' => $newToken,
                    'message' => 'ADMIN_TOKEN rotated successfully. Old token is now invalid. Update your .env file and notify administrators.',
                    'warning' => 'Please reload PHP-FPM for changes to take effect: sudo systemctl reload php-fpm'
                ], 200);
            } catch (Exception $e) {
                log_admin("Failed to rotate ADMIN_TOKEN: " . $e->getMessage(), 'error');
                sendError('Failed to rotate token: ' . $e->getMessage(), 500);
            }
            break;
        
        case 'migrate_legacy_token':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            // Require super-admin permission
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            try {
                $result = $adminAuth->migrateLegacyToken();
                log_admin("Legacy ADMIN_TOKEN migrated to user: " . $result['user']['email']);
                sendResponse($result, 201);
            } catch (Exception $e) {
                sendError($e->getMessage(), 500);
            }
            break;
        
        // ==================== Audit Log ====================
        
        case 'list_audit_log':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $limit = min($limit, 1000); // Cap at 1000
            
            try {
                $logs = $db->query(
                    "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ?",
                    [$limit]
                );
                sendResponse($logs);
            } catch (Exception $e) {
                sendError('Failed to list audit logs: ' . $e->getMessage(), 500);
            }
            break;
        
        // ==================== Audit Trails (Conversations) ====================
        
        case 'list_audit_conversations':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            if (!$auditService || !$auditService->isEnabled()) {
                sendError('Audit service is not enabled', 503);
            }
            
            $filters = [];
            if (isset($_GET['agent_id'])) {
                $filters['agent_id'] = $_GET['agent_id'];
            }
            if (isset($_GET['channel'])) {
                $filters['channel'] = $_GET['channel'];
            }
            if (isset($_GET['from'])) {
                $filters['from'] = $_GET['from'];
            }
            if (isset($_GET['to'])) {
                $filters['to'] = $_GET['to'];
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $limit = min($limit, 500); // Cap at 500
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $conversations = $auditService->listConversations($filters, $limit, $offset);
            sendResponse(['conversations' => $conversations]);
            break;
        
        case 'get_audit_conversation':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            if (!$auditService || !$auditService->isEnabled()) {
                sendError('Audit service is not enabled', 503);
            }
            
            $conversationId = $_GET['conversation_id'] ?? '';
            if (empty($conversationId)) {
                sendError('conversation_id required', 400);
            }
            
            $conversation = $auditService->getConversation($conversationId);
            if (!$conversation) {
                sendError('Conversation not found', 404);
            }
            
            $decryptContent = isset($_GET['decrypt']) && $_GET['decrypt'] === 'true';
            if ($decryptContent) {
                requirePermission($authenticatedUser, 'read_sensitive_audit', $adminAuth);
            }
            
            $messages = $auditService->getMessages($conversationId, $decryptContent);
            $events = $auditService->getEvents($conversationId);
            
            sendResponse([
                'conversation' => $conversation,
                'messages' => $messages,
                'events' => $events
            ]);
            break;
        
        case 'get_audit_message':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            if (!$auditService || !$auditService->isEnabled()) {
                sendError('Audit service is not enabled', 503);
            }
            
            $messageId = $_GET['message_id'] ?? '';
            if (empty($messageId)) {
                sendError('message_id required', 400);
            }
            
            $decryptContent = isset($_GET['decrypt']) && $_GET['decrypt'] === 'true';
            if ($decryptContent) {
                requirePermission($authenticatedUser, 'read_sensitive_audit', $adminAuth);
            }
            
            $sql = "SELECT * FROM audit_messages WHERE id = ?";
            $result = $db->query($sql, [$messageId]);
            
            if (empty($result)) {
                sendError('Message not found', 404);
            }
            
            $message = $result[0];
            
            // Decrypt content if requested
            if ($decryptContent && !empty($message['content_enc'])) {
                $message['content'] = $auditService->decryptContent($message['content_enc']);
            } else {
                $message['content'] = '[ENCRYPTED]';
            }
            
            sendResponse(['message' => $message]);
            break;
        
        case 'export_audit_data':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            if (!$auditService || !$auditService->isEnabled()) {
                sendError('Audit service is not enabled', 503);
            }
            
            $filters = [];
            if (isset($_GET['agent_id'])) {
                $filters['agent_id'] = $_GET['agent_id'];
            }
            if (isset($_GET['from'])) {
                $filters['from'] = $_GET['from'];
            }
            if (isset($_GET['to'])) {
                $filters['to'] = $_GET['to'];
            }
            
            $conversations = $auditService->listConversations($filters, 10000, 0);
            
            // Build CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="audit_export_' . date('Y-m-d_H-i-s') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Conversation ID', 'Agent ID', 'Channel', 'Started At', 'Last Activity', 'User Fingerprint']);
            
            foreach ($conversations as $conv) {
                fputcsv($output, [
                    $conv['conversation_id'],
                    $conv['agent_id'],
                    $conv['channel'],
                    $conv['started_at'],
                    $conv['last_activity_at'],
                    $conv['user_fingerprint']
                ]);
            }
            
            fclose($output);
            exit();
            break;
        
        case 'delete_audit_data':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'delete', $adminAuth);
            
            if (!$auditService || !$auditService->isEnabled()) {
                sendError('Audit service is not enabled', 503);
            }
            
            $data = getRequestBody();
            $conversationId = $data['conversation_id'] ?? null;
            $retentionDays = $data['retention_days'] ?? null;
            
            if ($conversationId) {
                // Delete specific conversation
                $sql = "DELETE FROM audit_conversations WHERE conversation_id = ?";
                $deleted = $db->execute($sql, [$conversationId]);
                log_admin("Deleted audit conversation: $conversationId");
                sendResponse(['deleted' => $deleted]);
            } elseif ($retentionDays !== null) {
                // Delete by retention period
                $deleted = $auditService->deleteExpired((int)$retentionDays);
                log_admin("Deleted $deleted expired audit conversations");
                sendResponse(['deleted' => $deleted]);
            } else {
                sendError('conversation_id or retention_days required', 400);
            }
            break;
        
        // LeadSense - Lead Management Endpoints
        case 'list_leads':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            // Check if LeadSense is enabled
            if (!isset($config['leadsense']) || !($config['leadsense']['enabled'] ?? false)) {
                sendError('LeadSense is not enabled', 503);
            }
            
            require_once __DIR__ . '/includes/LeadSense/LeadRepository.php';
            require_once __DIR__ . '/includes/LeadSense/Redactor.php';
            
            $leadRepo = new LeadRepository($config['leadsense']);
            $redactor = new Redactor($config['leadsense']);
            
            // Build filters from query parameters
            $filters = [];
            if (isset($_GET['agent_id'])) $filters['agent_id'] = $_GET['agent_id'];
            if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
            if (isset($_GET['qualified'])) $filters['qualified'] = filter_var($_GET['qualified'], FILTER_VALIDATE_BOOLEAN);
            if (isset($_GET['min_score'])) $filters['min_score'] = (int)$_GET['min_score'];
            if (isset($_GET['from'])) $filters['from_date'] = $_GET['from'];
            if (isset($_GET['to'])) $filters['to_date'] = $_GET['to'];
            if (isset($_GET['q'])) $filters['q'] = $_GET['q'];
            if (isset($_GET['limit'])) $filters['limit'] = (int)$_GET['limit'];
            if (isset($_GET['offset'])) $filters['offset'] = (int)$_GET['offset'];
            
            $leads = $leadRepo->list($filters);
            
            // Redact PII in list view
            $redactedLeads = array_map(function($lead) use ($redactor) {
                return $redactor->redactLead($lead);
            }, $leads);
            
            log_admin("Listed leads with filters: " . json_encode($filters));
            sendResponse([
                'leads' => $redactedLeads,
                'count' => count($redactedLeads)
            ]);
            break;
            
        case 'get_lead':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            if (!isset($config['leadsense']) || !($config['leadsense']['enabled'] ?? false)) {
                sendError('LeadSense is not enabled', 503);
            }
            
            require_once __DIR__ . '/includes/LeadSense/LeadRepository.php';
            require_once __DIR__ . '/includes/LeadSense/Redactor.php';
            
            $leadId = $_GET['id'] ?? null;
            if (!$leadId) {
                sendError('Lead ID required', 400);
            }
            
            $leadRepo = new LeadRepository($config['leadsense']);
            $redactor = new Redactor($config['leadsense']);
            
            $lead = $leadRepo->getById($leadId);
            if (!$lead) {
                sendError('Lead not found', 404);
            }
            
            // Get events and score history
            $events = $leadRepo->getEvents($leadId);
            $scores = $leadRepo->getScoreHistory($leadId);
            
            // Don't redact in detail view (admin has full access)
            log_admin("Retrieved lead: $leadId");
            sendResponse([
                'lead' => $lead,
                'events' => $events,
                'score_history' => $scores
            ]);
            break;
            
        case 'update_lead':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            if (!isset($config['leadsense']) || !($config['leadsense']['enabled'] ?? false)) {
                sendError('LeadSense is not enabled', 503);
            }
            
            require_once __DIR__ . '/includes/LeadSense/LeadRepository.php';
            
            $data = getRequestBody();
            $leadId = $data['id'] ?? ($_GET['id'] ?? null);
            
            if (!$leadId) {
                sendError('Lead ID required', 400);
            }
            
            $leadRepo = new LeadRepository($config['leadsense']);
            
            // Verify lead exists
            $existingLead = $leadRepo->getById($leadId);
            if (!$existingLead) {
                sendError('Lead not found', 404);
            }
            
            // Allowed update fields
            $updateData = ['id' => $leadId];
            if (isset($data['status'])) $updateData['status'] = $data['status'];
            if (isset($data['qualified'])) $updateData['qualified'] = $data['qualified'];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['company'])) $updateData['company'] = $data['company'];
            if (isset($data['role'])) $updateData['role'] = $data['role'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
            
            $leadRepo->createOrUpdateLead(array_merge(
                ['conversation_id' => $existingLead['conversation_id']],
                $updateData
            ));
            
            // Add update event
            $leadRepo->addEvent($leadId, 'updated', [
                'updated_by' => 'admin',
                'changes' => $updateData
            ]);
            
            log_admin("Updated lead: $leadId");
            sendResponse([
                'success' => true,
                'lead_id' => $leadId
            ]);
            break;
            
        case 'add_lead_note':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            if (!isset($config['leadsense']) || !($config['leadsense']['enabled'] ?? false)) {
                sendError('LeadSense is not enabled', 503);
            }
            
            require_once __DIR__ . '/includes/LeadSense/LeadRepository.php';
            
            $data = getRequestBody();
            $leadId = $data['id'] ?? null;
            $note = $data['note'] ?? null;
            
            if (!$leadId || !$note) {
                sendError('Lead ID and note required', 400);
            }
            
            $leadRepo = new LeadRepository($config['leadsense']);
            
            $eventId = $leadRepo->addEvent($leadId, 'note', [
                'note' => $note,
                'added_by' => 'admin'
            ]);
            
            log_admin("Added note to lead: $leadId");
            sendResponse([
                'success' => true,
                'event_id' => $eventId
            ]);
            break;
            
        case 'rescore_lead':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            if (!isset($config['leadsense']) || !($config['leadsense']['enabled'] ?? false)) {
                sendError('LeadSense is not enabled', 503);
            }
            
            require_once __DIR__ . '/includes/LeadSense/LeadRepository.php';
            require_once __DIR__ . '/includes/LeadSense/LeadScorer.php';
            require_once __DIR__ . '/includes/LeadSense/IntentDetector.php';
            
            $data = getRequestBody();
            $leadId = $data['id'] ?? null;
            
            if (!$leadId) {
                sendError('Lead ID required', 400);
            }
            
            $leadRepo = new LeadRepository($config['leadsense']);
            $lead = $leadRepo->getById($leadId);
            
            if (!$lead) {
                sendError('Lead not found', 404);
            }
            
            // Re-score the lead
            $scorer = new LeadScorer($config['leadsense']);
            $entities = [
                'name' => $lead['name'],
                'company' => $lead['company'],
                'role' => $lead['role'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
                'industry' => $lead['industry'],
                'company_size' => $lead['company_size'],
                'urgency' => $lead['extras']['urgency'] ?? null
            ];
            
            $intent = [
                'intent' => $lead['intent_level'],
                'confidence' => $lead['extras']['intent_confidence'] ?? 0.5
            ];
            
            $scoreResult = $scorer->score($entities, $intent);
            
            // Update lead with new score
            $leadRepo->createOrUpdateLead([
                'conversation_id' => $lead['conversation_id'],
                'id' => $leadId,
                'score' => $scoreResult['score'],
                'qualified' => $scoreResult['qualified']
            ]);
            
            // Add score snapshot
            $leadRepo->addScoreSnapshot($leadId, $scoreResult['score'], $scoreResult['rationale']);
            
            log_admin("Re-scored lead: $leadId - New score: {$scoreResult['score']}");
            sendResponse([
                'success' => true,
                'score' => $scoreResult['score'],
                'qualified' => $scoreResult['qualified'],
                'rationale' => $scoreResult['rationale']
            ]);
            break;
        
        // ==================== Prompt Builder Endpoints ====================
        
        case 'prompt_builder_generate':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'create', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            $agentId = $_GET['agent_id'] ?? '';
            if (empty($agentId)) {
                sendError('Agent ID required', 400);
            }
            
            $data = getRequestBody();
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $userId = $authenticatedUser['id'] ?? $authenticatedUser['email'] ?? null;
            
            $result = $controller->route('POST', [$agentId, 'prompt-builder', 'generate'], $data, $userId);
            sendResponse($result);
            break;
        
        case 'prompt_builder_list':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            $agentId = $_GET['agent_id'] ?? '';
            if (empty($agentId)) {
                sendError('Agent ID required', 400);
            }
            
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $result = $controller->route('GET', [$agentId, 'prompts'], [], null);
            sendResponse($result);
            break;
        
        case 'prompt_builder_get':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            $agentId = $_GET['agent_id'] ?? '';
            $version = $_GET['version'] ?? '';
            if (empty($agentId) || empty($version)) {
                sendError('Agent ID and version required', 400);
            }
            
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $result = $controller->route('GET', [$agentId, 'prompts', $version], [], null);
            sendResponse($result);
            break;
        
        case 'prompt_builder_activate':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            $agentId = $_GET['agent_id'] ?? '';
            $version = $_GET['version'] ?? '';
            if (empty($agentId) || empty($version)) {
                sendError('Agent ID and version required', 400);
            }
            
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $userId = $authenticatedUser['id'] ?? $authenticatedUser['email'] ?? null;
            
            $result = $controller->route('POST', [$agentId, 'prompts', $version, 'activate'], [], $userId);
            sendResponse($result);
            break;
        
        case 'prompt_builder_deactivate':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'update', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            $agentId = $_GET['agent_id'] ?? '';
            if (empty($agentId)) {
                sendError('Agent ID required', 400);
            }
            
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $userId = $authenticatedUser['id'] ?? $authenticatedUser['email'] ?? null;
            
            $result = $controller->route('POST', [$agentId, 'prompts', 'deactivate'], [], $userId);
            sendResponse($result);
            break;
        
        case 'prompt_builder_save_manual':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'create', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            $agentId = $_GET['agent_id'] ?? '';
            if (empty($agentId)) {
                sendError('Agent ID required', 400);
            }
            
            $data = getRequestBody();
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $userId = $authenticatedUser['id'] ?? $authenticatedUser['email'] ?? null;
            
            $result = $controller->route('POST', [$agentId, 'prompts', 'manual'], $data, $userId);
            sendResponse($result);
            break;
        
        case 'prompt_builder_delete':
            if ($method !== 'POST' && $method !== 'DELETE') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'delete', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            $agentId = $_GET['agent_id'] ?? '';
            $version = $_GET['version'] ?? '';
            if (empty($agentId) || empty($version)) {
                sendError('Agent ID and version required', 400);
            }
            
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $result = $controller->route('DELETE', [$agentId, 'prompts', $version], [], null);
            sendResponse($result);
            break;
        
        case 'prompt_builder_catalog':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'read', $adminAuth);
            
            require_once __DIR__ . '/includes/PromptBuilder/AdminPromptBuilderController.php';
            
            // Note: catalog doesn't need agent_id but we use a dummy for routing
            $controller = new AdminPromptBuilderController($db->getPdo(), $config, $auditService);
            $result = $controller->route('GET', ['dummy', 'prompt-builder', 'catalog'], [], null);
            sendResponse($result);
            break;
        
        // ============================================================
        // Tenant Management Actions (Super-Admin Only)
        // ============================================================
        
        case 'list_tenants':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $filters = [];
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            
            $tenants = $tenantService->listTenants($filters);
            sendResponse($tenants);
            break;
        
        case 'get_tenant':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Tenant ID required', 400);
            }
            
            $tenant = $tenantService->getTenant($id);
            if (!$tenant) {
                sendError('Tenant not found', 404);
            }
            
            sendResponse($tenant);
            break;
        
        case 'create_tenant':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $body = getRequestBody();
            
            try {
                $tenant = $tenantService->createTenant($body);
                sendResponse($tenant, 201);
            } catch (Exception $e) {
                $statusCode = $e->getCode();
                if (!is_int($statusCode) || $statusCode < 400) {
                    $statusCode = 400;
                }
                sendError($e->getMessage(), $statusCode);
            }
            break;
        
        case 'update_tenant':
            if ($method !== 'PUT' && $method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Tenant ID required', 400);
            }
            
            $body = getRequestBody();
            
            try {
                $tenant = $tenantService->updateTenant($id, $body);
                sendResponse($tenant);
            } catch (Exception $e) {
                $statusCode = $e->getCode();
                if (!is_int($statusCode) || $statusCode < 400) {
                    $statusCode = 400;
                }
                sendError($e->getMessage(), $statusCode);
            }
            break;
        
        case 'delete_tenant':
            if ($method !== 'DELETE' && $method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Tenant ID required', 400);
            }
            
            try {
                $tenantService->deleteTenant($id);
                sendResponse(['success' => true, 'message' => 'Tenant deleted']);
            } catch (Exception $e) {
                $statusCode = $e->getCode();
                if (!is_int($statusCode) || $statusCode < 400) {
                    $statusCode = 400;
                }
                sendError($e->getMessage(), $statusCode);
            }
            break;
        
        case 'suspend_tenant':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Tenant ID required', 400);
            }
            
            try {
                $tenant = $tenantService->suspendTenant($id);
                sendResponse($tenant);
            } catch (Exception $e) {
                $statusCode = $e->getCode();
                if (!is_int($statusCode) || $statusCode < 400) {
                    $statusCode = 400;
                }
                sendError($e->getMessage(), $statusCode);
            }
            break;
        
        case 'activate_tenant':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Tenant ID required', 400);
            }
            
            try {
                $tenant = $tenantService->activateTenant($id);
                sendResponse($tenant);
            } catch (Exception $e) {
                $statusCode = $e->getCode();
                if (!is_int($statusCode) || $statusCode < 400) {
                    $statusCode = 400;
                }
                sendError($e->getMessage(), $statusCode);
            }
            break;
        
        case 'get_tenant_stats':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            requirePermission($authenticatedUser, 'manage_users', $adminAuth);
            
            if (!$tenantService) {
                sendError('Tenant management requires super-admin privileges', 403);
            }
            
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Tenant ID required', 400);
            }
            
            try {
                $stats = $tenantService->getTenantStats($id);
                sendResponse($stats);
            } catch (Exception $e) {
                $statusCode = $e->getCode();
                if (!is_int($statusCode) || $statusCode < 400) {
                    $statusCode = 400;
                }
                sendError($e->getMessage(), $statusCode);
            }
            break;
            
        default:
            sendError('Unknown action: ' . $action, 400);
    }
    
} catch (Exception $e) {
    $statusCode = $e->getCode();
    if (!is_int($statusCode) || $statusCode < 400 || $statusCode > 599) {
        $statusCode = 500;
    }
    
    $message = $e->getMessage();
    
    // Don't expose internal errors to clients
    if ($statusCode >= 500) {
        log_admin('Internal error: ' . $message, 'error');
        $message = 'Internal server error';
    } else {
        log_admin('Client error: ' . $message, 'warn');
    }
    
    sendError($message, $statusCode);
}
