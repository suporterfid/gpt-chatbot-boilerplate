<?php
/**
 * Queue Driver Interface
 * 
 * Defines the contract for pluggable queue drivers in the webhook system.
 * Implementations can use different backends (RabbitMQ, Redis, SQS, etc.)
 * 
 * Reference: docs/SPEC_WEBHOOK.md §10 - Extensibility
 * Task: wh-008a
 */

interface QueueDriverInterface {
    /**
     * Enqueue a job for processing
     * 
     * @param string $jobType Type of job (e.g., 'webhook_delivery')
     * @param array $payload Job payload data
     * @param int $maxAttempts Maximum number of retry attempts
     * @param int $delay Delay in seconds before first execution
     * @return string Job ID for tracking
     * @throws Exception on enqueue failure
     */
    public function enqueue($jobType, $payload, $maxAttempts = 3, $delay = 0);
    
    /**
     * Get job status
     * 
     * @param string $jobId Job identifier
     * @return array|null Job information or null if not found
     */
    public function getJobStatus($jobId);
}
