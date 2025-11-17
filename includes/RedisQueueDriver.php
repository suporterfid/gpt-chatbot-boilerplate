<?php
/**
 * Redis Queue Driver - Example implementation
 * 
 * This is an example implementation of a custom queue driver using Redis.
 * It demonstrates how to create alternative queue backends for the webhook system.
 * 
 * Reference: docs/SPEC_WEBHOOK.md §10 - Extensibility
 * Task: wh-008a
 * 
 * @example
 * $redis = new Redis();
 * $redis->connect('127.0.0.1', 6379);
 * $driver = new RedisQueueDriver($redis);
 * $dispatcher->setQueueDriver($driver);
 */

require_once __DIR__ . '/QueueDriverInterface.php';

class RedisQueueDriver implements QueueDriverInterface {
    private $redis;
    private $queuePrefix;
    
    /**
     * @param Redis $redis Redis connection
     * @param string $queuePrefix Prefix for queue keys
     */
    public function __construct($redis, $queuePrefix = 'webhook_queue') {
        if (!$redis instanceof Redis) {
            throw new Exception("RedisQueueDriver requires a Redis instance");
        }
        $this->redis = $redis;
        $this->queuePrefix = $queuePrefix;
    }
    
    /**
     * Enqueue a job for processing
     * 
     * @param string $jobType Type of job (e.g., 'webhook_delivery')
     * @param array $payload Job payload data
     * @param int $maxAttempts Maximum number of retry attempts
     * @param int $delay Delay in seconds before first execution
     * @return string Job ID for tracking
     */
    public function enqueue($jobType, $payload, $maxAttempts = 3, $delay = 0) {
        $jobId = $this->generateJobId();
        
        $job = [
            'id' => $jobId,
            'type' => $jobType,
            'payload' => $payload,
            'max_attempts' => $maxAttempts,
            'attempts' => 0,
            'created_at' => time(),
            'scheduled_at' => time() + $delay,
            'status' => 'pending'
        ];
        
        // Store job data
        $jobKey = $this->queuePrefix . ':job:' . $jobId;
        $this->redis->setex($jobKey, 86400 * 7, json_encode($job)); // Keep for 7 days
        
        // Add to appropriate queue
        if ($delay > 0) {
            // Add to delayed queue with score = execution time
            $this->redis->zAdd(
                $this->queuePrefix . ':delayed',
                time() + $delay,
                $jobId
            );
        } else {
            // Add to immediate queue
            $this->redis->rPush(
                $this->queuePrefix . ':pending',
                $jobId
            );
        }
        
        return $jobId;
    }
    
    /**
     * Get job status
     * 
     * @param string $jobId Job identifier
     * @return array|null Job information or null if not found
     */
    public function getJobStatus($jobId) {
        $jobKey = $this->queuePrefix . ':job:' . $jobId;
        $jobData = $this->redis->get($jobKey);
        
        if ($jobData === false) {
            return null;
        }
        
        return json_decode($jobData, true);
    }
    
    /**
     * Generate a unique job ID
     * 
     * @return string Job ID
     */
    private function generateJobId() {
        return uniqid('redis_job_', true);
    }
    
    /**
     * Process delayed jobs (to be called by worker)
     * 
     * Moves jobs from delayed queue to pending queue when their time comes
     * 
     * @return int Number of jobs moved
     */
    public function processDelayedJobs() {
        $now = time();
        $moved = 0;
        
        // Get all delayed jobs that are ready
        $readyJobs = $this->redis->zRangeByScore(
            $this->queuePrefix . ':delayed',
            0,
            $now
        );
        
        foreach ($readyJobs as $jobId) {
            // Move to pending queue
            $this->redis->rPush($this->queuePrefix . ':pending', $jobId);
            $this->redis->zRem($this->queuePrefix . ':delayed', $jobId);
            $moved++;
        }
        
        return $moved;
    }
    
    /**
     * Get next job from queue (for worker)
     * 
     * @param int $timeout Timeout in seconds
     * @return array|null Job data or null if no jobs available
     */
    public function dequeue($timeout = 0) {
        $jobId = $this->redis->blPop(
            [$this->queuePrefix . ':pending'],
            $timeout
        );
        
        if ($jobId === false || empty($jobId[1])) {
            return null;
        }
        
        return $this->getJobStatus($jobId[1]);
    }
}
