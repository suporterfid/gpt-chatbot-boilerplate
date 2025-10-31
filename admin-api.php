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

// Authentication check - supports both legacy ADMIN_TOKEN and AdminAuth
function checkAuthentication($config, $adminAuth) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (empty($authHeader)) {
        log_admin('Missing Authorization header', 'warn');
        sendError('Authorization header required', 403);
    }
    
    // Extract Bearer token
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        log_admin('Invalid Authorization header format', 'warn');
        sendError('Invalid Authorization header format', 403);
    }
    
    $token = $matches[1];
    
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
    
    // Check authentication and get user
    $authenticatedUser = checkAuthentication($config, $adminAuth);
    
    // Initialize OpenAI Admin Client
    $openaiClient = null;
    if (!empty($config['openai']['api_key'])) {
        $openaiClient = new OpenAIAdminClient($config['openai']);
    }
    
    // Initialize services
    $agentService = new AgentService($db);
    $promptService = new PromptService($db, $openaiClient);
    $vectorStoreService = new VectorStoreService($db, $openaiClient);
    $jobQueue = new JobQueue($db);
    
    // Get action from query parameter
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    log_admin("$method /admin-api.php?action=$action [user: {$authenticatedUser['email']}]");
    
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
        
        // ==================== Health & Utility Endpoints ====================
        
        case 'health':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            
            $health = [
                'status' => 'ok',
                'timestamp' => date('c'),
                'database' => false,
                'openai' => false,
                'worker' => [
                    'enabled' => $config['admin']['jobs_enabled'] ?? true,
                    'queue_depth' => 0,
                    'stats' => []
                ]
            ];
            
            // Test database
            try {
                $db->query("SELECT 1");
                $health['database'] = true;
                
                // Get worker stats if database is healthy
                try {
                    $stats = $jobQueue->getStats();
                    $health['worker']['stats'] = $stats;
                    $health['worker']['queue_depth'] = $stats['pending'] + $stats['running'];
                } catch (Exception $e) {
                    log_admin('Failed to get worker stats: ' . $e->getMessage(), 'warn');
                }
            } catch (Exception $e) {
                $health['status'] = 'degraded';
            }
            
            // Test OpenAI (optional)
            if ($openaiClient) {
                try {
                    $result = $openaiClient->listVectorStores(1);
                    $health['openai'] = true;
                } catch (Exception $e) {
                    $health['status'] = 'degraded';
                }
            }
            
            sendResponse($health);
            break;
            
        case 'test_agent':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            
            // This endpoint streams a test response
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                sendError('Agent ID required', 400);
            }
            
            $data = getRequestBody();
            $message = $data['message'] ?? 'Hello, this is a test message.';
            
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
