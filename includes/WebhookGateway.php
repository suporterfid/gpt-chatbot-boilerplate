<?php
/**
 * WebhookGateway - Orchestration service for inbound webhook processing
 * 
 * Implements SPEC_WEBHOOK.md §4 requirements:
 * - JSON parsing and validation
 * - Schema validation (event, timestamp, data)
 * - Signature verification
 * - Payload normalization
 * - Downstream event routing (JobQueue for async, direct processing for sync)
 * - Idempotency checking
 * - Consistent response structure
 * 
 * Architecture pattern follows ChatHandler as the primary orchestration service.
 */

declare(strict_types=1);

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/WebhookHandler.php';
require_once __DIR__ . '/JobQueue.php';

class WebhookGatewayException extends Exception {
    private string $errorCode;
    private int $statusCode;

    public function __construct(string $message, string $errorCode = 'gateway_error', int $statusCode = 400, ?Throwable $previous = null) {
        parent::__construct($message, $statusCode, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string {
        return $this->errorCode;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }
}

class WebhookGateway {
    private array $config;
    private DB $db;
    private WebhookHandler $webhookHandler;
    private JobQueue $jobQueue;
    private $logger;
    private $metrics;
    private bool $asyncProcessing;

    /**
     * Constructor
     * 
     * @param array $config Configuration array (must include 'webhooks' section)
     * @param DB|null $db Database connection (optional, will create if not provided)
     * @param object|null $logger Logger instance (optional)
     * @param object|null $metrics Metrics collector instance (optional)
     * @param bool $asyncProcessing Whether to use async processing via JobQueue (default: true)
     */
    public function __construct(
        array $config, 
        ?DB $db = null, 
        $logger = null, 
        $metrics = null,
        bool $asyncProcessing = true
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->metrics = $metrics;
        $this->asyncProcessing = $asyncProcessing;
        
        // Initialize database
        $this->db = $db ?? new DB($config['database'] ?? []);
        
        // Initialize WebhookHandler for idempotency and event storage
        $signingSecret = $config['webhooks']['openai_signing_secret'] ?? null;
        $this->webhookHandler = new WebhookHandler($this->db, $signingSecret);
        
        // Initialize JobQueue for async processing
        $this->jobQueue = new JobQueue($this->db);
    }

    /**
     * Main orchestration method - handles incoming webhook requests
     * 
     * This method coordinates the entire webhook processing flow:
     * 1. Parse and validate JSON
     * 2. Validate schema (event, timestamp, data fields)
     * 3. Verify signature if configured
     * 4. Check idempotency (prevent duplicate processing)
     * 5. Store event for tracking
     * 6. Route to downstream handlers
     * 7. Return structured response
     * 
     * @param array $headers Request headers
     * @param string $body Raw request body
     * @return array Structured response with status, event, and metadata
     * @throws WebhookGatewayException On validation or processing errors
     */
    public function handleRequest(array $headers, string $body): array {
        $startTime = microtime(true);
        
        // Step 1: Parse JSON
        $payload = $this->parseJson($body);
        
        // Step 2: Extract and validate required fields per SPEC §4
        $event = $this->extractEvent($payload);
        $timestamp = $this->extractTimestamp($payload);
        $data = $this->extractData($payload);
        $signature = $this->extractSignature($payload, $headers);
        
        // Step 3: Validate timestamp tolerance (anti-replay)
        $this->validateTimestamp($timestamp);
        
        // Step 4: Verify HMAC signature if configured
        $this->verifySignature($body, $signature);
        
        // Step 5: Check idempotency - prevent duplicate event processing
        $eventId = $this->generateEventId($payload);
        if ($this->webhookHandler->isEventProcessed($eventId)) {
            $this->log('Duplicate event ignored', 'info', [
                'event' => $event,
                'event_id' => $eventId
            ]);
            
            return [
                'status' => 'received',
                'event' => $event,
                'event_id' => $eventId,
                'received_at' => time(),
                'note' => 'duplicate_event'
            ];
        }
        
        // Step 6: Store event for idempotency tracking
        try {
            $this->webhookHandler->storeEvent($eventId, $event, $payload);
        } catch (Exception $e) {
            // If this is a duplicate from race condition, return success
            if ($e->getCode() === 409) {
                return [
                    'status' => 'received',
                    'event' => $event,
                    'event_id' => $eventId,
                    'received_at' => time(),
                    'note' => 'duplicate_event'
                ];
            }
            throw new WebhookGatewayException(
                'Failed to store event: ' . $e->getMessage(),
                'storage_error',
                500,
                $e
            );
        }
        
        // Step 7: Normalize payload for downstream processing
        $normalizedPayload = $this->normalizePayload($event, $timestamp, $data, $eventId);
        
        // Step 8: Route to downstream handlers
        $processingResult = $this->routeEvent($normalizedPayload);
        
        // Step 9: Mark event as processed
        $this->webhookHandler->markEventProcessed($eventId);
        
        // Step 10: Log and collect metrics
        $processingTime = (microtime(true) - $startTime) * 1000;
        $this->log('Webhook processed successfully', 'info', [
            'event' => $event,
            'event_id' => $eventId,
            'processing_time_ms' => round($processingTime, 2),
            'async' => $this->asyncProcessing
        ]);
        
        if ($this->metrics) {
            $this->metrics->incrementCounter('chatbot_webhook_inbound_total', [
                'event' => $event,
                'status' => 'processed'
            ]);
            $this->metrics->observeHistogram('chatbot_webhook_processing_duration_ms', $processingTime, [
                'event' => $event
            ]);
        }
        
        // Step 11: Return structured response per SPEC §4
        return [
            'status' => 'received',
            'event' => $event,
            'event_id' => $eventId,
            'received_at' => time(),
            'processing' => $this->asyncProcessing ? 'async' : 'sync',
            'job_id' => $processingResult['job_id'] ?? null
        ];
    }

    /**
     * Parse JSON body
     * 
     * @param string $body Raw request body
     * @return array Parsed payload
     * @throws WebhookGatewayException If JSON is invalid
     */
    private function parseJson(string $body): array {
        if (trim($body) === '') {
            throw new WebhookGatewayException('Request body cannot be empty', 'empty_body', 400);
        }
        
        $payload = json_decode($body, true);
        
        if (!is_array($payload)) {
            throw new WebhookGatewayException('Request body must be valid JSON', 'invalid_json', 400);
        }
        
        return $payload;
    }

    /**
     * Extract and validate event field per SPEC §4
     * 
     * @param array $payload
     * @return string Event type
     * @throws WebhookGatewayException If event is missing or invalid
     */
    private function extractEvent(array $payload): string {
        $event = $payload['event'] ?? null;
        
        if (!is_string($event) || trim($event) === '') {
            throw new WebhookGatewayException('Event field is required and must be a non-empty string', 'invalid_event', 400);
        }
        
        return trim($event);
    }

    /**
     * Extract and validate timestamp field per SPEC §4
     * 
     * @param array $payload
     * @return int Unix timestamp
     * @throws WebhookGatewayException If timestamp is missing or invalid
     */
    private function extractTimestamp(array $payload): int {
        $timestamp = $payload['timestamp'] ?? null;
        
        // Support both integer and string timestamps
        if (is_string($timestamp) && is_numeric($timestamp)) {
            $timestamp = (int)$timestamp;
        }
        
        if (!is_int($timestamp) || $timestamp <= 0) {
            throw new WebhookGatewayException('Timestamp field is required and must be a valid Unix timestamp', 'invalid_timestamp', 400);
        }
        
        return $timestamp;
    }

    /**
     * Extract data field per SPEC §4
     * 
     * @param array $payload
     * @return array Data object
     * @throws WebhookGatewayException If data is not an object/array
     */
    private function extractData(array $payload): array {
        $data = $payload['data'] ?? [];
        
        if (!is_array($data)) {
            throw new WebhookGatewayException('Data field must be an object', 'invalid_data', 400);
        }
        
        return $data;
    }

    /**
     * Extract signature from payload or headers per SPEC §4
     * 
     * @param array $payload
     * @param array $headers
     * @return string|null Signature or null if not present
     */
    private function extractSignature(array $payload, array $headers): ?string {
        // Check payload first (SPEC §4 format)
        if (isset($payload['signature']) && is_string($payload['signature'])) {
            return $payload['signature'];
        }
        
        // Check headers for X-Agent-Signature or X-Signature
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if ($lowerKey === 'x-agent-signature' || $lowerKey === 'x-signature') {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }
        
        return null;
    }

    /**
     * Validate timestamp is within tolerance window (anti-replay protection)
     * 
     * @param int $timestamp
     * @throws WebhookGatewayException If timestamp is outside tolerance
     */
    private function validateTimestamp(int $timestamp): void {
        $tolerance = max(0, (int)($this->config['webhooks']['timestamp_tolerance'] ?? 300));
        
        // If tolerance is 0, skip validation
        if ($tolerance === 0) {
            return;
        }
        
        $now = time();
        $diff = abs($now - $timestamp);
        
        if ($diff > $tolerance) {
            throw new WebhookGatewayException(
                "Timestamp is outside tolerance window (±{$tolerance}s)",
                'invalid_timestamp',
                422
            );
        }
    }

    /**
     * Verify HMAC signature per SPEC §4
     * 
     * @param string $body Raw request body
     * @param string|null $signature Provided signature
     * @throws WebhookGatewayException If signature is invalid or missing when required
     */
    private function verifySignature(string $body, ?string $signature): void {
        $secret = $this->config['webhooks']['gateway_secret'] ?? '';
        
        // If no secret configured, skip verification
        if ($secret === '') {
            return;
        }
        
        // If secret is configured, signature is required
        if ($signature === null || trim($signature) === '') {
            throw new WebhookGatewayException('Signature is required', 'missing_signature', 401);
        }
        
        // Compute expected signature per SPEC §4
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        
        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expected, $signature)) {
            $this->log('Invalid signature detected', 'warning', [
                'expected_prefix' => substr($expected, 0, 15),
                'received_prefix' => substr($signature, 0, 15)
            ]);
            
            throw new WebhookGatewayException('Invalid signature', 'invalid_signature', 401);
        }
    }

    /**
     * Generate unique event ID for idempotency
     * 
     * Uses event type, timestamp, and data hash to generate deterministic ID
     * 
     * @param array $payload
     * @return string Event ID
     */
    private function generateEventId(array $payload): string {
        // If payload includes an ID field, use it
        if (isset($payload['id']) && is_string($payload['id'])) {
            return $payload['id'];
        }
        
        // Otherwise generate deterministic ID from payload
        $event = $payload['event'] ?? 'unknown';
        $timestamp = $payload['timestamp'] ?? time();
        $data = $payload['data'] ?? [];
        
        $hash = hash('sha256', json_encode([
            'event' => $event,
            'timestamp' => $timestamp,
            'data' => $data
        ]));
        
        return substr($hash, 0, 32);
    }

    /**
     * Normalize payload for downstream processing
     * 
     * @param string $event Event type
     * @param int $timestamp Unix timestamp
     * @param array $data Event data
     * @param string $eventId Event ID
     * @return array Normalized payload
     */
    private function normalizePayload(string $event, int $timestamp, array $data, string $eventId): array {
        return [
            'event_id' => $eventId,
            'event_type' => $event,
            'timestamp' => $timestamp,
            'data' => $data,
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
    }

    /**
     * Route event to downstream handlers
     * 
     * Routes to JobQueue for async processing or processes directly for sync.
     * Can be extended to support different event types and routing strategies.
     * 
     * @param array $normalizedPayload
     * @return array Processing result with job_id or status
     */
    private function routeEvent(array $normalizedPayload): array {
        $event = $normalizedPayload['event_type'];
        
        if ($this->asyncProcessing) {
            // Enqueue job for async processing
            $jobId = $this->jobQueue->enqueue(
                'webhook_event',
                $normalizedPayload,
                3, // max attempts
                0  // no delay
            );
            
            $this->log('Event queued for async processing', 'debug', [
                'event' => $event,
                'job_id' => $jobId
            ]);
            
            return [
                'status' => 'queued',
                'job_id' => $jobId
            ];
        } else {
            // Process synchronously
            $result = $this->processEventSync($normalizedPayload);
            
            $this->log('Event processed synchronously', 'debug', [
                'event' => $event,
                'result' => $result
            ]);
            
            return [
                'status' => 'processed',
                'result' => $result
            ];
        }
    }

    /**
     * Process event synchronously
     * 
     * This method handles direct processing of webhook events.
     * Override or extend this method to add custom event processing logic.
     * 
     * @param array $normalizedPayload
     * @return array Processing result
     */
    protected function processEventSync(array $normalizedPayload): array {
        $event = $normalizedPayload['event_type'];
        $data = $normalizedPayload['data'];
        
        // Basic processing - can be extended based on event type
        $this->log('Processing webhook event', 'info', [
            'event' => $event,
            'event_id' => $normalizedPayload['event_id']
        ]);
        
        // TODO: Add event-specific processing logic here
        // For now, just log and return success
        
        return [
            'processed' => true,
            'event' => $event,
            'timestamp' => time()
        ];
    }

    /**
     * Log message with context
     * 
     * @param string $message
     * @param string $level
     * @param array $context
     */
    private function log(string $message, string $level = 'info', array $context = []): void {
        $context['component'] = 'WebhookGateway';
        
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->{$level}($message, $context);
            return;
        }
        
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        error_log(sprintf('[WebhookGateway] [%s] %s%s', strtoupper($level), $message, $contextString));
    }
}
