<?php
/**
 * WebhookEventProcessor - Adapter for mapping normalized webhook events to agent jobs
 * 
 * This class bridges the WebhookGateway and the agent processing system by:
 * - Receiving normalized webhook events from the JobQueue
 * - Mapping event types to appropriate agent handlers
 * - Dispatching to ChatHandler or other specialized processors
 * - Providing extensible event-to-action mapping
 * 
 * Implements requirements from SPEC_WEBHOOK.md §4 and issue wh-001c:
 * - Maps normalized events to agent jobs
 * - Supports synchronous/async processing
 * - Integrates with existing idempotency via webhook_events table
 * 
 * Architecture follows the pattern established in ChatHandler as the primary
 * orchestration service, with this class acting as a specialized adapter
 * for webhook-triggered agent interactions.
 */

declare(strict_types=1);

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/ChatHandler.php';

class WebhookEventProcessorException extends Exception {
    private string $errorCode;

    public function __construct(string $message, string $errorCode = 'processor_error', ?Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string {
        return $this->errorCode;
    }
}

class WebhookEventProcessor {
    private array $config;
    private DB $db;
    private ChatHandler $chatHandler;
    private $logger;
    private $observability;

    /**
     * Constructor
     * 
     * @param array $config Configuration array
     * @param DB $db Database connection
     * @param ChatHandler|null $chatHandler Optional ChatHandler instance
     * @param object|null $logger Logger instance
     * @param object|null $observability Observability middleware
     */
    public function __construct(
        array $config,
        DB $db,
        ?ChatHandler $chatHandler = null,
        $logger = null,
        $observability = null
    ) {
        $this->config = $config;
        $this->db = $db;
        $this->chatHandler = $chatHandler ?? new ChatHandler($config, $db);
        $this->logger = $logger;
        $this->observability = $observability;
    }

    /**
     * Process a normalized webhook event
     * 
     * This is the main entry point called by the worker script.
     * It receives a normalized event payload and routes it to the appropriate handler.
     * 
     * @param array $normalizedEvent Normalized event from WebhookGateway
     * @return array Processing result
     * @throws WebhookEventProcessorException On processing errors
     */
    public function processEvent(array $normalizedEvent): array {
        $eventType = $normalizedEvent['event_type'] ?? 'unknown';
        $eventId = $normalizedEvent['event_id'] ?? 'unknown';
        $data = $normalizedEvent['data'] ?? [];

        $this->log("Processing webhook event", 'info', [
            'event_type' => $eventType,
            'event_id' => $eventId,
            'source' => $normalizedEvent['source'] ?? 'unknown'
        ]);

        // Start observability span if available
        $spanId = null;
        if ($this->observability) {
            $spanId = $this->observability->createSpan('webhook.event.process', [
                'event_type' => $eventType,
                'event_id' => $eventId
            ]);
        }

        try {
            // Route to appropriate handler based on event type
            $result = $this->routeEventToHandler($eventType, $data, $normalizedEvent);

            // Track success metric
            if ($this->observability) {
                $this->observability->endSpan($spanId, ['status' => 'success']);
                $this->observability->getMetrics()->incrementCounter('chatbot_webhook_events_processed_total', [
                    'event_type' => $eventType,
                    'status' => 'success'
                ]);
            }

            $this->log("Webhook event processed successfully", 'info', [
                'event_type' => $eventType,
                'event_id' => $eventId
            ]);

            return [
                'status' => 'processed',
                'event_type' => $eventType,
                'event_id' => $eventId,
                'result' => $result
            ];

        } catch (Exception $e) {
            // Track failure metric
            if ($this->observability) {
                $this->observability->handleError($spanId, $e, [
                    'event_type' => $eventType,
                    'event_id' => $eventId
                ]);
                $this->observability->endSpan($spanId, ['status' => 'error']);
                $this->observability->getMetrics()->incrementCounter('chatbot_webhook_events_processed_total', [
                    'event_type' => $eventType,
                    'status' => 'error'
                ]);
            }

            $this->log("Failed to process webhook event", 'error', [
                'event_type' => $eventType,
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);

            throw new WebhookEventProcessorException(
                "Failed to process event type '$eventType': " . $e->getMessage(),
                'processing_failed',
                $e
            );
        }
    }

    /**
     * Route event to appropriate handler based on event type
     * 
     * This method implements the event-to-action mapping strategy.
     * Extend this method to add support for new event types.
     * 
     * @param string $eventType Event type from normalized payload
     * @param array $data Event data
     * @param array $fullEvent Full normalized event for context
     * @return array Handler result
     * @throws WebhookEventProcessorException For unknown or unsupported event types
     */
    private function routeEventToHandler(string $eventType, array $data, array $fullEvent): array {
        // Map event types to handlers
        // This mapping can be extended or made configurable
        
        switch ($eventType) {
            case 'message.created':
            case 'chat.message':
                return $this->handleChatMessage($data, $fullEvent);

            case 'conversation.created':
                return $this->handleConversationCreated($data, $fullEvent);

            case 'file.uploaded':
                return $this->handleFileUploaded($data, $fullEvent);

            case 'vector_store.file.completed':
            case 'vector_store.file.failed':
            case 'vector_store.completed':
                // These events are handled by WebhookHandler directly
                // No additional processing needed here
                return [
                    'handled_by' => 'WebhookHandler',
                    'note' => 'Vector store events processed by dedicated handler'
                ];

            case 'agent.trigger':
                return $this->handleAgentTrigger($data, $fullEvent);

            case 'test.event':
            case 'ping':
                // Test/ping events for health checks
                return [
                    'status' => 'acknowledged',
                    'event_type' => $eventType,
                    'timestamp' => time()
                ];

            default:
                // Unknown event type - log and return without error
                // This allows for graceful handling of new event types
                $this->log("Unknown event type received", 'warning', [
                    'event_type' => $eventType
                ]);

                return [
                    'status' => 'ignored',
                    'reason' => 'unknown_event_type',
                    'event_type' => $eventType
                ];
        }
    }

    /**
     * Handle chat message events
     * 
     * Routes chat messages to the ChatHandler for AI processing.
     * 
     * @param array $data Event data
     * @param array $fullEvent Full event context
     * @return array Processing result
     */
    private function handleChatMessage(array $data, array $fullEvent): array {
        $message = $data['message'] ?? $data['text'] ?? '';
        $conversationId = $data['conversation_id'] ?? $data['session_id'] ?? null;
        $agentId = $data['agent_id'] ?? null;
        $tenantId = $data['tenant_id'] ?? null;

        if (empty($message)) {
            throw new WebhookEventProcessorException(
                'Message content is required for chat events',
                'invalid_event_data'
            );
        }

        // Generate conversation ID if not provided
        if (!$conversationId) {
            $conversationId = 'webhook_' . bin2hex(random_bytes(8));
        }

        $this->log("Processing chat message from webhook", 'debug', [
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'message_length' => strlen($message)
        ]);

        // Process via ChatHandler synchronously (webhook context)
        // For streaming, the webhook would need to provide a callback URL
        $result = $this->chatHandler->handleChatCompletionSync(
            $message,
            $conversationId,
            $agentId,
            $tenantId
        );

        return [
            'conversation_id' => $conversationId,
            'response' => $result['message'] ?? null,
            'processing_time_ms' => $result['processing_time_ms'] ?? null
        ];
    }

    /**
     * Handle conversation created events
     * 
     * Initializes a new conversation session.
     * 
     * @param array $data Event data
     * @param array $fullEvent Full event context
     * @return array Processing result
     */
    private function handleConversationCreated(array $data, array $fullEvent): array {
        $conversationId = $data['conversation_id'] ?? null;
        $agentId = $data['agent_id'] ?? null;
        $tenantId = $data['tenant_id'] ?? null;

        $this->log("Conversation created via webhook", 'info', [
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'tenant_id' => $tenantId
        ]);

        // Initialize conversation in storage if needed
        // For now, just acknowledge the event
        return [
            'conversation_id' => $conversationId,
            'status' => 'initialized'
        ];
    }

    /**
     * Handle file uploaded events
     * 
     * Processes file upload notifications.
     * 
     * @param array $data Event data
     * @param array $fullEvent Full event context
     * @return array Processing result
     */
    private function handleFileUploaded(array $data, array $fullEvent): array {
        $fileId = $data['file_id'] ?? $data['id'] ?? null;
        $filename = $data['filename'] ?? $data['name'] ?? 'unknown';

        $this->log("File uploaded via webhook", 'info', [
            'file_id' => $fileId,
            'filename' => $filename
        ]);

        // File processing logic can be added here
        // For now, just acknowledge the upload
        return [
            'file_id' => $fileId,
            'filename' => $filename,
            'status' => 'acknowledged'
        ];
    }

    /**
     * Handle agent trigger events
     * 
     * Processes requests to trigger specific agent actions.
     * 
     * @param array $data Event data
     * @param array $fullEvent Full event context
     * @return array Processing result
     */
    private function handleAgentTrigger(array $data, array $fullEvent): array {
        $action = $data['action'] ?? null;
        $agentId = $data['agent_id'] ?? null;
        $payload = $data['payload'] ?? [];

        if (!$action) {
            throw new WebhookEventProcessorException(
                'Action is required for agent trigger events',
                'invalid_event_data'
            );
        }

        $this->log("Agent trigger via webhook", 'info', [
            'action' => $action,
            'agent_id' => $agentId
        ]);

        // Route to specific agent action handler
        switch ($action) {
            case 'process_message':
                return $this->handleChatMessage($payload, $fullEvent);

            default:
                return [
                    'status' => 'ignored',
                    'reason' => 'unknown_action',
                    'action' => $action
                ];
        }
    }

    /**
     * Log message with context
     * 
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     */
    private function log(string $message, string $level = 'info', array $context = []): void {
        $context['component'] = 'WebhookEventProcessor';

        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->{$level}($message, $context);
            return;
        }

        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        error_log(sprintf('[WebhookEventProcessor] [%s] %s%s', strtoupper($level), $message, $contextString));
    }
}
