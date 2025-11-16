<?php
/**
 * Webhook Gateway Service
 *
 * Normalizes inbound webhook requests, enforces the SPEC §4 contract,
 * and emits consistent responses for integrators.
 */

declare(strict_types=1);

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

class WebhookGatewayService {
    private array $config;
    private array $webhookConfig;
    private $logger;
    private $metrics;

    public function __construct(array $config, $logger = null, $metrics = null) {
        $this->config = $config;
        $this->webhookConfig = $config['webhooks'] ?? [];
        $this->logger = $logger;
        $this->metrics = $metrics;
    }

    /**
     * Handle inbound webhook payloads.
     *
     * @param array $payload Parsed JSON payload
     * @param array $context Additional context (raw_body, headers, remote_ip)
     */
    public function handleInboundEvent(array $payload, array $context = []): array {
        $event = $this->extractEvent($payload);
        $timestamp = $this->extractTimestamp($payload);
        $data = $this->extractData($payload);
        $rawBody = (string)($context['raw_body'] ?? json_encode($payload));
        $signature = $payload['signature'] ?? $this->extractHeaderSignature($context['headers'] ?? []);

        $this->enforceTimestampTolerance($timestamp);
        $this->validateSignature($rawBody, $signature);

        $this->log('Inbound webhook received', 'info', [
            'event' => $event,
            'timestamp' => $timestamp,
            'remote_ip' => $context['remote_ip'] ?? null,
        ]);

        if (($this->webhookConfig['log_payloads'] ?? false) && !empty($data)) {
            $this->log('Inbound webhook payload', 'debug', [
                'event' => $event,
                'payload' => $data,
            ]);
        }

        if ($this->metrics) {
            $this->metrics->incrementCounter('chatbot_webhook_inbound_total', [
                'event' => $event,
                'status' => 'received',
            ]);
        }

        return [
            'status' => 'received',
            'event' => $event,
            'received_at' => time(),
        ];
    }

    private function extractEvent(array $payload): string {
        $event = $payload['event'] ?? null;
        if (!is_string($event) || trim($event) === '') {
            throw new WebhookGatewayException('Event is required', 'invalid_event', 400);
        }

        return trim($event);
    }

    private function extractTimestamp(array $payload): int {
        $timestamp = $payload['timestamp'] ?? null;
        if (is_string($timestamp) && is_numeric($timestamp)) {
            $timestamp = (int)$timestamp;
        }

        if (!is_int($timestamp)) {
            throw new WebhookGatewayException('Timestamp is required', 'invalid_timestamp', 400);
        }

        return $timestamp;
    }

    private function extractData(array $payload): array {
        $data = $payload['data'] ?? [];
        if (!is_array($data)) {
            throw new WebhookGatewayException('Data payload must be an object', 'invalid_data', 400);
        }

        return $data;
    }

    private function extractHeaderSignature(array $headers): ?string {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-agent-signature') {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return null;
    }

    private function enforceTimestampTolerance(int $timestamp): void {
        $tolerance = max(0, (int)($this->webhookConfig['timestamp_tolerance'] ?? 300));
        if ($tolerance === 0) {
            return;
        }

        $now = time();
        if (abs($now - $timestamp) > $tolerance) {
            throw new WebhookGatewayException('Timestamp outside tolerated window', 'invalid_timestamp', 422);
        }
    }

    private function validateSignature(string $rawBody, ?string $signature): void {
        $secret = $this->webhookConfig['gateway_secret'] ?? '';
        if ($secret === '') {
            return;
        }

        if ($signature === null || trim($signature) === '') {
            throw new WebhookGatewayException('Signature is required', 'missing_signature', 401);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new WebhookGatewayException('Invalid signature', 'invalid_signature', 401);
        }
    }

    private function log(string $message, string $level = 'info', array $context = []): void {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->{$level}($message, $context);
            return;
        }

        $contextString = $context ? ' ' . json_encode($context) : '';
        error_log(sprintf('[WebhookGateway] %s%s', $message, $contextString));
    }
}
