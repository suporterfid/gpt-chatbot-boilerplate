<?php
/**
 * Webhook Endpoint for OpenAI Events
 * Receives and processes webhook events from OpenAI
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookHandler.php';
require_once __DIR__ . '/../includes/VectorStoreService.php';
require_once __DIR__ . '/../includes/OpenAIAdminClient.php';

// Set JSON response header
header('Content-Type: application/json');

// Logging helper
function logWebhook($message, $level = 'info') {
    global $config;
    $logFile = $config['logging']['file'] ?? __DIR__ . '/../logs/chatbot.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $line = "[$ts][$level][Webhook][$ip] $message\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    logWebhook('Invalid method: ' . $_SERVER['REQUEST_METHOD'], 'warn');
    exit;
}

// Get raw request body
$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    logWebhook('Empty request body', 'warn');
    exit;
}

// Parse JSON payload
$event = json_decode($rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    logWebhook('Invalid JSON: ' . json_last_error_msg(), 'warn');
    exit;
}

// Extract event metadata
$eventId = $event['id'] ?? null;
$eventType = $event['type'] ?? null;

if (!$eventId || !$eventType) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event id or type']);
    logWebhook('Missing event id or type', 'warn');
    exit;
}

logWebhook("Received event: $eventId (type: $eventType)");

try {
    // Initialize services
    $db = new DB($config['database'] ?? []);
    $signingSecret = $config['webhooks']['openai_signing_secret'] ?? null;
    
    // Security warning if no signing secret
    if (!$signingSecret) {
        logWebhook("Security warning: Webhook signature verification disabled - no signing secret configured", 'warn');
    }
    
    $webhookHandler = new WebhookHandler($db, $signingSecret);
    
    // Verify signature if configured
    $signature = $_SERVER['HTTP_X_OPENAI_SIGNATURE'] ?? '';
    if ($signingSecret && !$webhookHandler->verifySignature($rawBody, $signature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        logWebhook("Invalid signature for event $eventId", 'error');
        exit;
    }
    
    // Check for duplicate event (idempotency)
    if ($webhookHandler->isEventProcessed($eventId)) {
        // Event already processed, return success
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Event already processed']);
        logWebhook("Duplicate event $eventId, skipping");
        exit;
    }
    
    // Store event for idempotency tracking
    try {
        $webhookHandler->storeEvent($eventId, $eventType, $event);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            // Race condition - another worker is processing this
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Event being processed']);
            logWebhook("Event $eventId being processed by another worker");
            exit;
        }
        throw $e;
    }
    
    // Initialize VectorStoreService if needed for processing
    $vectorStoreService = null;
    if (strpos($eventType, 'vector_store') !== false) {
        $openaiClient = new OpenAIAdminClient($config['openai'] ?? []);
        $vectorStoreService = new VectorStoreService($db, $openaiClient);
    }
    
    // Process the event
    $result = $webhookHandler->processEvent($event, $vectorStoreService);
    
    // Mark event as processed
    $webhookHandler->markEventProcessed($eventId);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'event_id' => $eventId,
        'result' => $result
    ]);
    
    logWebhook("Successfully processed event $eventId");
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    logWebhook("Failed to process event $eventId: " . $e->getMessage(), 'error');
}
