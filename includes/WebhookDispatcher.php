<?php
/**
 * Webhook Dispatcher - Core outbound webhook fan-out service
 * 
 * This service is responsible for:
 * - Loading active subscribers for a given event
 * - Creating webhook delivery jobs for each subscriber
 * - Applying payload transformations
 * - Generating HMAC signatures
 * - Queueing jobs for async processing
 * 
 * Reference: docs/SPEC_WEBHOOK.md §5
 * Tasks: wh-005a (WebhookDispatcher class)
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/JobQueue.php';
require_once __DIR__ . '/WebhookSubscriberRepository.php';
require_once __DIR__ . '/WebhookLogRepository.php';

class WebhookDispatcher {
    private $db;
    private $jobQueue;
    private $subscriberRepo;
    private $logRepo;
    private $config;
    private $agentId;
    private $transformHooks = [];
    private $queueDriver;
    
    /**
     * @param DB $db Database connection
     * @param array $config Configuration array
     * @param string|null $agentId Optional agent identifier
     */
    public function __construct($db, $config = [], $agentId = null) {
        $this->db = $db;
        $this->config = $config;
        $this->agentId = $agentId ?? ($config['agent_id'] ?? 'default');
        
        // Initialize dependencies
        $this->jobQueue = new JobQueue($db);
        $this->subscriberRepo = new WebhookSubscriberRepository($db);
        $this->logRepo = new WebhookLogRepository($db);
        
        // Initialize queue driver (default to JobQueue)
        $this->queueDriver = $this->jobQueue;
    }
    
    /**
     * Register a payload transformation hook for a specific event type
     * 
     * Hooks are applied in the order they are registered.
     * Multiple hooks can be registered for the same event type.
     * Use '*' as event type to register a global hook for all events.
     * 
     * @param string $eventType Event type (e.g., 'ai.response', 'order.created') or '*' for all
     * @param callable $transformer Function that receives and returns the payload array
     * @return self For method chaining
     * 
     * @example
     * $dispatcher->registerTransform('ai.response', function($payload) {
     *     $payload['data']['processed_by'] = 'custom_handler';
     *     return $payload;
     * });
     */
    public function registerTransform($eventType, callable $transformer) {
        if (!isset($this->transformHooks[$eventType])) {
            $this->transformHooks[$eventType] = [];
        }
        $this->transformHooks[$eventType][] = $transformer;
        return $this;
    }
    
    /**
     * Set a custom queue driver
     * 
     * This allows swapping the default JobQueue with alternative implementations
     * such as RabbitMQ, Redis, SQS, etc.
     * 
     * The driver must implement the enqueue() method with the same signature as JobQueue.
     * 
     * @param object $driver Queue driver instance
     * @return self For method chaining
     * 
     * @example
     * $dispatcher->setQueueDriver(new RabbitMQDriver($config));
     */
    public function setQueueDriver($driver) {
        if (!method_exists($driver, 'enqueue')) {
            throw new Exception("Queue driver must implement enqueue() method");
        }
        $this->queueDriver = $driver;
        return $this;
    }
    
    /**
     * Get registered transformation hooks
     * 
     * @return array Array of hooks by event type
     */
    public function getTransformHooks() {
        return $this->transformHooks;
    }
    
    /**
     * Clear all registered transformation hooks
     * 
     * @param string|null $eventType Optional event type to clear, or null to clear all
     * @return self For method chaining
     */
    public function clearTransformHooks($eventType = null) {
        if ($eventType === null) {
            $this->transformHooks = [];
        } else {
            unset($this->transformHooks[$eventType]);
        }
        return $this;
    }
    
    /**
     * Dispatch a webhook event to all active subscribers
     * 
     * This is the main entry point for sending webhooks. It will:
     * 1. Find all active subscribers for the event type
     * 2. Apply payload transformations if needed
     * 3. Generate HMAC signatures for each subscriber
     * 4. Create delivery jobs for async processing
     * 5. Return job IDs for tracking
     * 
     * @param string $eventType Event type (e.g., 'ai.response', 'order.created')
     * @param array $payload Event payload data
     * @param string|null $agentId Optional agent ID override
     * @return array Results with job_ids and subscriber_ids
     * @throws Exception on validation or database errors
     */
    public function dispatch($eventType, $payload, $agentId = null) {
        // Validate inputs
        if (empty($eventType)) {
            throw new Exception("Event type is required");
        }
        
        if (!is_array($payload)) {
            throw new Exception("Payload must be an array");
        }
        
        // Use provided agent ID or fall back to default
        $agentId = $agentId ?? $this->agentId;
        
        // Find all active subscribers for this event
        $subscribers = $this->subscriberRepo->listActiveByEvent($eventType);
        
        if (empty($subscribers)) {
            // No subscribers for this event - not an error, just log and return
            error_log("WebhookDispatcher: No active subscribers for event '{$eventType}'");
            return [
                'event' => $eventType,
                'subscribers_found' => 0,
                'jobs_created' => 0,
                'job_ids' => [],
                'subscriber_ids' => []
            ];
        }
        
        $jobIds = [];
        $subscriberIds = [];
        $timestamp = time();
        
        // Build the webhook payload with metadata
        $webhookPayload = [
            'event' => $eventType,
            'timestamp' => $timestamp,
            'agent_id' => $agentId,
            'data' => $payload
        ];
        
        // Apply any configured payload transformations
        $webhookPayload = $this->applyTransformations($webhookPayload, $eventType);
        
        // Create a delivery job for each subscriber
        foreach ($subscribers as $subscriber) {
            try {
                // Create initial log entry
                $logId = $this->logRepo->createLog([
                    'subscriber_id' => $subscriber['id'],
                    'event' => $eventType,
                    'request_body' => $webhookPayload,
                    'attempts' => 0, // Will be incremented by worker
                ]);
                
                // Prepare job payload for the worker
                $jobPayload = [
                    'log_id' => $logId['id'],
                    'subscriber_id' => $subscriber['id'],
                    'subscriber_url' => $subscriber['url'],
                    'subscriber_secret' => $subscriber['secret'],
                    'event_type' => $eventType,
                    'webhook_payload' => $webhookPayload,
                    'agent_id' => $agentId,
                    'timestamp' => $timestamp,
                ];
                
                // Enqueue the delivery job using the configured queue driver
                $jobId = $this->queueDriver->enqueue(
                    'webhook_delivery',
                    $jobPayload,
                    $this->getMaxAttempts(), // Max retry attempts
                    0 // No delay for initial attempt
                );
                
                $jobIds[] = $jobId;
                $subscriberIds[] = $subscriber['id'];
                
            } catch (Exception $e) {
                // Log error but continue processing other subscribers
                error_log("WebhookDispatcher: Failed to create job for subscriber {$subscriber['id']}: " . $e->getMessage());
                
                // Try to log the failure
                try {
                    $this->logRepo->createLog([
                        'subscriber_id' => $subscriber['id'],
                        'event' => $eventType,
                        'request_body' => $webhookPayload,
                        'response_code' => null,
                        'response_body' => 'Failed to enqueue: ' . $e->getMessage(),
                        'attempts' => 0,
                    ]);
                } catch (Exception $logError) {
                    error_log("WebhookDispatcher: Failed to log error: " . $logError->getMessage());
                }
            }
        }
        
        return [
            'event' => $eventType,
            'subscribers_found' => count($subscribers),
            'jobs_created' => count($jobIds),
            'job_ids' => $jobIds,
            'subscriber_ids' => $subscriberIds,
            'timestamp' => $timestamp
        ];
    }
    
    /**
     * Apply configured transformations to the webhook payload
     * 
     * This method applies transformations in the following order:
     * 1. Registered global hooks (event type '*')
     * 2. Registered event-specific hooks
     * 3. Config-based transformations (for backward compatibility)
     * 
     * @param array $payload Original payload
     * @param string $eventType Event type
     * @return array Transformed payload
     */
    private function applyTransformations($payload, $eventType) {
        // Apply global transformation hooks first
        if (isset($this->transformHooks['*'])) {
            foreach ($this->transformHooks['*'] as $hook) {
                $payload = $hook($payload);
            }
        }
        
        // Apply event-specific transformation hooks
        if (isset($this->transformHooks[$eventType])) {
            foreach ($this->transformHooks[$eventType] as $hook) {
                $payload = $hook($payload);
            }
        }
        
        // Apply legacy config-based transformations for backward compatibility
        $transformations = $this->config['webhook_transformations'] ?? [];
        
        if (!empty($transformations)) {
            // Apply event-specific transformations if configured
            if (isset($transformations[$eventType]) && is_callable($transformations[$eventType])) {
                $payload = $transformations[$eventType]($payload);
            }
            
            // Apply global transformations if configured
            if (isset($transformations['*']) && is_callable($transformations['*'])) {
                $payload = $transformations['*']($payload);
            }
        }
        
        return $payload;
    }
    
    /**
     * Get maximum retry attempts from config
     * 
     * @return int Maximum attempts (default: 6)
     */
    private function getMaxAttempts() {
        return $this->config['webhook_max_attempts'] ?? 6;
    }
    
    /**
     * Generate HMAC signature for webhook payload
     * 
     * @param array $payload Webhook payload
     * @param string $secret Subscriber secret
     * @return string HMAC signature with prefix (e.g., "sha256=...")
     */
    public static function generateSignature($payload, $secret) {
        $body = is_array($payload) ? json_encode($payload) : $payload;
        $hash = hash_hmac('sha256', $body, $secret);
        return 'sha256=' . $hash;
    }
    
    /**
     * Dispatch multiple events in batch
     * 
     * Useful for sending multiple related events efficiently
     * 
     * @param array $events Array of ['event' => string, 'payload' => array]
     * @param string|null $agentId Optional agent ID
     * @return array Results for each event
     */
    public function dispatchBatch($events, $agentId = null) {
        $results = [];
        
        foreach ($events as $event) {
            $eventType = $event['event'] ?? null;
            $payload = $event['payload'] ?? [];
            
            if (!$eventType) {
                $results[] = ['error' => 'Missing event type'];
                continue;
            }
            
            try {
                $results[] = $this->dispatch($eventType, $payload, $agentId);
            } catch (Exception $e) {
                $results[] = [
                    'error' => $e->getMessage(),
                    'event' => $eventType
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get dispatcher statistics
     * 
     * @return array Statistics about webhook dispatching
     */
    public function getStatistics() {
        // Get delivery statistics from log repository
        $stats = $this->logRepo->getStatistics();
        
        // Add subscriber count
        $subscriberSql = "SELECT COUNT(*) as total, 
                          SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active
                          FROM webhook_subscribers";
        $subscriberStats = $this->db->query($subscriberSql);
        
        return [
            'subscribers' => $subscriberStats[0] ?? ['total' => 0, 'active' => 0],
            'deliveries' => $stats
        ];
    }
}
