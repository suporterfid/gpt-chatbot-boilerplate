<?php
/**
 * Admin API - Protected endpoint for managing Agents
 * Requires Authorization: Bearer <ADMIN_TOKEN>
 */

require_once 'config.php';
require_once 'includes/DB.php';
require_once 'includes/AgentService.php';

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

// Authentication check
function checkAuthentication($config) {
    $adminToken = $config['admin']['token'] ?? '';
    
    if (empty($adminToken)) {
        log_admin('ADMIN_TOKEN not configured', 'error');
        sendError('Admin API not configured', 403);
    }
    
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
    
    if ($token !== $adminToken) {
        log_admin('Invalid admin token provided', 'warn');
        sendError('Invalid admin token', 403);
    }
    
    return true;
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
    // Check authentication
    checkAuthentication($config);
    
    // Initialize database
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
    
    $agentService = new AgentService($db);
    
    // Get action from query parameter
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    log_admin("$method /admin-api.php?action=$action");
    
    // Route to appropriate handler
    switch ($action) {
        case 'list_agents':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
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
            $data = getRequestBody();
            $agent = $agentService->createAgent($data);
            log_admin('Agent created: ' . $agent['id'] . ' (' . $agent['name'] . ')');
            sendResponse($agent, 201);
            break;
            
        case 'update_agent':
            if ($method !== 'POST' && $method !== 'PUT') {
                sendError('Method not allowed', 405);
            }
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
