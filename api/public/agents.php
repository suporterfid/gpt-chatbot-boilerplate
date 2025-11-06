<?php
/**
 * Public Agent Configuration API
 * Returns sanitized configuration for whitelabel agents
 * 
 * Route: /api/public/agents.php?id={agent_public_id}
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/DB.php';
require_once __DIR__ . '/../../includes/AgentService.php';

// Validate origin for CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
$allowedOrigin = '*'; // Default to allow all for public read-only endpoint

// If origin is provided, validate it's from same domain or allowed domains
if ($origin) {
    $parsedOrigin = parse_url($origin);
    $parsedServer = parse_url($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    // Allow same-origin or localhost for development
    if (
        ($parsedOrigin['host'] ?? '') === ($parsedServer['host'] ?? '') ||
        strpos($origin, 'localhost') !== false ||
        strpos($origin, '127.0.0.1') !== false
    ) {
        $allowedOrigin = $origin;
    }
}

// CORS headers - restrictive but functional for public read-only endpoint
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get agent public ID from URL
$agentPublicId = $_GET['id'] ?? null;

if (!$agentPublicId) {
    http_response_code(400);
    echo json_encode([
        'error' => [
            'code' => 'MISSING_AGENT_ID',
            'message' => 'Agent public ID is required'
        ]
    ]);
    exit();
}

// Initialize database and service
try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../../data/chatbot.db'
    ];
    $db = new DB($dbConfig);
    $agentService = new AgentService($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Service temporarily unavailable'
        ]
    ]);
    exit();
}

// Get public configuration
try {
    $publicConfig = $agentService->getPublicWhitelabelConfig($agentPublicId);
    
    if (!$publicConfig) {
        http_response_code(404);
        echo json_encode([
            'error' => [
                'code' => 'AGENT_NOT_FOUND',
                'message' => 'Agent not found or not published'
            ]
        ]);
        exit();
    }
    
    // Set caching headers (short cache for dynamic content)
    $etag = md5(json_encode($publicConfig));
    header('ETag: "' . $etag . '"');
    header('Cache-Control: public, max-age=300'); // 5 minutes
    
    // Check if client has cached version
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
        http_response_code(304);
        exit();
    }
    
    echo json_encode($publicConfig, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    error_log('Error fetching public agent config: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to retrieve configuration'
        ]
    ]);
}
