<?php
/**
 * Webhook Metrics Endpoint
 * 
 * Exposes webhook metrics in Prometheus format or JSON format.
 * 
 * Reference: docs/SPEC_WEBHOOK.md §10 - Extensibility
 * Task: wh-008c
 * 
 * Usage:
 * - GET /webhook/metrics?format=prometheus (default)
 * - GET /webhook/metrics?format=json
 * - GET /webhook/metrics?format=json&since=<timestamp>
 */

require_once __DIR__ . '/../../includes/DB.php';
require_once __DIR__ . '/../../includes/WebhookMetrics.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Initialize database
    $config = require __DIR__ . '/../../config.php';
    $db = new DB($config['database'] ?? []);
    
    // Initialize metrics service
    $metrics = new WebhookMetrics($db);
    
    // Get query parameters
    $format = $_GET['format'] ?? 'prometheus';
    $since = isset($_GET['since']) ? (int)$_GET['since'] : null;
    
    if ($format === 'json') {
        // Return JSON statistics
        header('Content-Type: application/json');
        $stats = $metrics->getStatistics($since);
        echo json_encode($stats, JSON_PRETTY_PRINT);
    } else {
        // Return Prometheus format (default)
        header('Content-Type: text/plain; version=0.0.4');
        echo $metrics->getPrometheusMetrics($since);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
    error_log("Webhook metrics endpoint error: " . $e->getMessage());
}
