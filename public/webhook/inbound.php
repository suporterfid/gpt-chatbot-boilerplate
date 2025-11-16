<?php
/**
 * Canonical inbound webhook entrypoint (SPEC §4)
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

$vendorAutoload = $projectRoot . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

$config = require $projectRoot . '/config.php';
require_once $projectRoot . '/includes/WebhookGatewayService.php';
require_once $projectRoot . '/includes/ObservabilityMiddleware.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    header('Allow: POST');
    sendJsonResponse(405, [
        'error' => 'method_not_allowed',
        'message' => 'Only POST requests are accepted',
    ]);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') === false) {
    sendJsonResponse(415, [
        'error' => 'unsupported_media_type',
        'message' => 'Content-Type must be application/json',
    ]);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || trim($rawBody) === '') {
    sendJsonResponse(400, [
        'error' => 'empty_body',
        'message' => 'Request body cannot be empty',
    ]);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    sendJsonResponse(400, [
        'error' => 'invalid_json',
        'message' => 'Request body must be valid JSON',
    ]);
}

$observability = null;
if ($config['observability']['enabled'] ?? true) {
    $observability = new ObservabilityMiddleware($config);
    $observability->getLogger()->setContext([
        'service' => 'webhook_gateway',
        'endpoint' => '/webhook/inbound',
    ]);
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$gatewayService = new WebhookGatewayService(
    $config,
    $observability ? $observability->getLogger() : null,
    $observability ? $observability->getMetrics() : null
);

$spanId = null;
if ($observability) {
    $spanId = $observability->handleRequestStart('webhook.gateway.inbound', [
        'event' => $payload['event'] ?? 'unknown',
    ]);
}

$responseStatus = 200;
$responseBody = [];

try {
    $responseBody = $gatewayService->handleInboundEvent($payload, [
        'raw_body' => $rawBody,
        'headers' => $headers,
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
} catch (WebhookGatewayException $e) {
    $responseStatus = $e->getStatusCode();
    $responseBody = [
        'error' => $e->getErrorCode(),
        'message' => $e->getMessage(),
    ];

    if ($observability && $spanId) {
        $observability->handleError($spanId, $e, [
            'event' => $payload['event'] ?? 'unknown',
        ]);
    }
} catch (Throwable $e) {
    $responseStatus = 500;
    $responseBody = [
        'error' => 'internal_error',
        'message' => 'Unable to process webhook',
    ];

    if ($observability && $spanId) {
        $observability->handleError($spanId, $e, [
            'event' => $payload['event'] ?? 'unknown',
        ]);
    }
}

if ($observability && $spanId) {
    $observability->handleRequestEnd($spanId, 'webhook.gateway.inbound', $responseStatus, [
        'event' => $payload['event'] ?? 'unknown',
    ]);
}

sendJsonResponse($responseStatus, $responseBody);

function sendJsonResponse(int $statusCode, array $body): void {
    http_response_code($statusCode);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}
