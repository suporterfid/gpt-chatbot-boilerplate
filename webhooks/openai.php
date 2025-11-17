<?php
/**
 * Webhook Endpoint for OpenAI Events
 * Receives and processes webhook events from OpenAI
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookHandler.php';
require_once __DIR__ . '/../includes/WebhookSecurityService.php';
require_once __DIR__ . '/../includes/VectorStoreService.php';
require_once __DIR__ . '/../includes/OpenAIAdminClient.php';
require_once __DIR__ . '/../includes/ObservabilityMiddleware.php';

// Set JSON response header
header('Content-Type: application/json');

// Initialize observability
$observability = null;
if ($config['observability']['enabled'] ?? true) {
    $observability = new ObservabilityMiddleware($config);
    $observability->getLogger()->setContext([
        'service' => 'webhook',
        'endpoint' => 'openai',
    ]);
}

// Logging helper - now uses observability
function logWebhook($message, $level = 'info') {
    global $config, $observability;
    
    if ($observability) {
        $logger = $observability->getLogger();
        $logger->$level($message);
        return;
    }
    
    // Fallback to file logging
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

// Start span for webhook processing
$spanId = null;
if ($observability) {
    $spanId = $observability->handleRequestStart('webhook.openai', [
        'event_id' => $eventId,
        'event_type' => $eventType,
    ]);
}

try {
    // Initialize security service (SPEC §6)
    $securityService = new WebhookSecurityService($config);
    
    // Step 1: Check IP whitelist if configured
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($clientIp) {
        try {
            $whitelist = $config['webhooks']['ip_whitelist'] ?? [];
            if (!empty($whitelist) && !$securityService->checkWhitelist($clientIp, $whitelist)) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: IP not in whitelist']);
                logWebhook("IP not in whitelist: $clientIp", 'error');
                exit;
            }
        } catch (InvalidArgumentException $e) {
            logWebhook("IP whitelist check error: " . $e->getMessage(), 'warn');
        }
    }
    
    // Step 2: Verify signature using WebhookSecurityService
    $signingSecret = $config['webhooks']['openai_signing_secret'] ?? null;
    
    if ($signingSecret) {
        $signature = $_SERVER['HTTP_X_OPENAI_SIGNATURE'] ?? '';
        
        try {
            if (!$securityService->validateSignature($signature, $rawBody, $signingSecret)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid signature']);
                logWebhook("Invalid signature for event $eventId", 'error');
                exit;
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Signature validation error']);
            logWebhook("Signature validation error for event $eventId: " . $e->getMessage(), 'error');
            exit;
        }
    } else {
        logWebhook("Security warning: Webhook signature verification disabled - no signing secret configured", 'warn');
    }
    
    // Step 3: Validate timestamp if present (anti-replay)
    $timestamp = $event['timestamp'] ?? null;
    if ($timestamp !== null && is_numeric($timestamp)) {
        try {
            if (!$securityService->enforceClockSkew((int)$timestamp)) {
                http_response_code(422);
                echo json_encode(['error' => 'Timestamp outside tolerance window']);
                logWebhook("Timestamp outside tolerance for event $eventId", 'warn');
                exit;
            }
        } catch (InvalidArgumentException $e) {
            logWebhook("Timestamp validation error for event $eventId: " . $e->getMessage(), 'warn');
        }
    }
    
    // Initialize services
    $db = new DB($config['database'] ?? []);
    $webhookHandler = new WebhookHandler($db, $signingSecret);
    
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
    
    // Track metrics
    if ($observability) {
        $observability->getMetrics()->incrementCounter('chatbot_webhook_events_processed_total', [
            'event_type' => $eventType,
            'status' => 'success',
        ]);
    }
    
    // End span
    if ($observability && $spanId) {
        $observability->handleRequestEnd($spanId, 'webhook.openai', 200, [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'event_id' => $eventId,
        'result' => $result
    ]);
    
    logWebhook("Successfully processed event $eventId");
    
} catch (Exception $e) {
    // Handle error with observability
    if ($observability && $spanId) {
        $observability->handleError($spanId, $e, [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);
        $observability->handleRequestEnd($spanId, 'webhook.openai', 500, [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);
        
        // Track failure metric
        $observability->getMetrics()->incrementCounter('chatbot_webhook_events_processed_total', [
            'event_type' => $eventType ?? 'unknown',
            'status' => 'error',
        ]);
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    logWebhook("Failed to process event $eventId: " . $e->getMessage(), 'error');
}
