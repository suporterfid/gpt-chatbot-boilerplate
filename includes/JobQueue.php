<?php
/**
 * Job Queue Service - DB-backed background job processing
 * Handles job enqueueing, locking, execution, and retry logic
 */

require_once __DIR__ . '/DB.php';

class JobQueue {
    private $db;
    private $workerId;
    
    public function __construct($db, $workerId = null) {
        $this->db = $db;
        $this->workerId = $workerId ?? gethostname() . ':' . getmypid();
    }
    
    /**
     * Enqueue a new job
     * 
     * @param string $type Job type (e.g., 'file_ingest', 'poll_ingestion_status')
     * @param array $payload Job data
     * @param int $maxAttempts Maximum retry attempts
     * @param int $delaySeconds Delay before job becomes available
     * @return string Job ID
     */
    public function enqueue($type, $payload, $maxAttempts = 3, $delaySeconds = 0) {
        $id = $this->generateUUID();
        $now = new DateTime();
        $availableAt = (clone $now)->modify("+{$delaySeconds} seconds");
        
        $sql = "INSERT INTO jobs (
            id, type, payload_json, max_attempts, status, 
            available_at, created_at, updated_at
        ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)";
        
        $params = [
            $id,
            $type,
            json_encode($payload),
            $maxAttempts,
            $availableAt->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s')
        ];
        
        try {
            $this->db->insert($sql, $params);
            return $id;
        } catch (Exception $e) {
            error_log("Failed to enqueue job: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Claim and lock the next available job
     * Uses atomic UPDATE to prevent race conditions
     * 
     * @return array|null Job data or null if no jobs available
     */
    public function claimNext() {
        $now = new DateTime();
        $nowStr = $now->format('Y-m-d H:i:s');
        
        // Start transaction for atomic claim
        $this->db->beginTransaction();
        
        try {
            // Find next available job
            $sql = "SELECT * FROM jobs 
                    WHERE status = 'pending' 
                    AND available_at <= ? 
                    ORDER BY available_at ASC, created_at ASC 
                    LIMIT 1";
            
            $jobs = $this->db->query($sql, [$nowStr]);
            
            if (empty($jobs)) {
                $this->db->commit();
                return null;
            }
            
            $job = $jobs[0];
            
            // Lock the job atomically
            $updateSql = "UPDATE jobs 
                         SET status = 'running', 
                             locked_by = ?, 
                             locked_at = ?,
                             updated_at = ?
                         WHERE id = ? 
                         AND status = 'pending'";
            
            $affected = $this->db->execute($updateSql, [
                $this->workerId,
                $nowStr,
                $nowStr,
                $job['id']
            ]);
            
            if ($affected === 0) {
                // Job was claimed by another worker
                $this->db->rollback();
                return null;
            }
            
            $this->db->commit();
            
            // Return job with updated status
            $job['status'] = 'running';
            $job['locked_by'] = $this->workerId;
            $job['locked_at'] = $nowStr;
            $job['payload'] = json_decode($job['payload_json'], true);
            
            return $job;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Failed to claim job: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Mark job as completed with optional result data
     * 
     * @param string $jobId Job ID
     * @param array $result Result data to store
     */
    public function markCompleted($jobId, $result = []) {
        $now = new DateTime();
        
        $sql = "UPDATE jobs 
                SET status = 'completed',
                    result_json = ?,
                    updated_at = ?
                WHERE id = ?";
        
        $this->db->execute($sql, [
            json_encode($result),
            $now->format('Y-m-d H:i:s'),
            $jobId
        ]);
    }
    
    /**
     * Mark job as failed and optionally retry with exponential backoff
     * 
     * @param string $jobId Job ID
     * @param string $error Error message
     * @param bool $retry Whether to retry the job
     */
    public function markFailed($jobId, $error, $retry = true) {
        $now = new DateTime();
        
        // Get current job state
        $job = $this->db->queryOne("SELECT * FROM jobs WHERE id = ?", [$jobId]);
        
        if (!$job) {
            throw new Exception("Job not found: $jobId");
        }
        
        $attempts = (int)$job['attempts'] + 1;
        $maxAttempts = (int)$job['max_attempts'];
        
        // Determine if we should retry
        if ($retry && $attempts < $maxAttempts) {
            // Calculate exponential backoff with jitter
            // Base delay: 2^attempts minutes, max 60 minutes
            $baseDelay = min(pow(2, $attempts) * 60, 3600);
            $jitter = random_int(0, (int)($baseDelay / 10));
            $delaySeconds = $baseDelay + $jitter;
            
            $availableAt = (clone $now)->modify("+{$delaySeconds} seconds");
            
            $sql = "UPDATE jobs 
                    SET status = 'pending',
                        attempts = ?,
                        error_text = ?,
                        available_at = ?,
                        locked_by = NULL,
                        locked_at = NULL,
                        updated_at = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [
                $attempts,
                $error,
                $availableAt->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
                $jobId
            ]);
        } else {
            // Max attempts reached or retry disabled
            $sql = "UPDATE jobs 
                    SET status = 'failed',
                        attempts = ?,
                        error_text = ?,
                        updated_at = ?
                    WHERE id = ?";
            
            $this->db->execute($sql, [
                $attempts,
                $error,
                $now->format('Y-m-d H:i:s'),
                $jobId
            ]);
        }
    }
    
    /**
     * Get job by ID
     * 
     * @param string $jobId Job ID
     * @return array|null Job data
     */
    public function getJob($jobId) {
        $job = $this->db->queryOne("SELECT * FROM jobs WHERE id = ?", [$jobId]);
        
        if ($job && $job['payload_json']) {
            $job['payload'] = json_decode($job['payload_json'], true);
        }
        
        if ($job && $job['result_json']) {
            $job['result'] = json_decode($job['result_json'], true);
        }
        
        return $job;
    }
    
    /**
     * List jobs with optional filtering
     * 
     * @param array $filters Filters (status, type, limit, offset)
     * @return array Jobs list
     */
    public function listJobs($filters = []) {
        $conditions = [];
        $params = [];
        
        if (isset($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['type'])) {
            $conditions[] = "type = ?";
            $params[] = $filters['type'];
        }
        
        $where = empty($conditions) ? "" : "WHERE " . implode(" AND ", $conditions);
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        
        $sql = "SELECT * FROM jobs 
                $where
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $jobs = $this->db->query($sql, $params);
        
        // Parse JSON fields
        foreach ($jobs as &$job) {
            if ($job['payload_json']) {
                $job['payload'] = json_decode($job['payload_json'], true);
            }
            if ($job['result_json']) {
                $job['result'] = json_decode($job['result_json'], true);
            }
        }
        
        return $jobs;
    }
    
    /**
     * Cancel a job (mark as failed without retry)
     * 
     * @param string $jobId Job ID
     */
    public function cancelJob($jobId) {
        $now = new DateTime();
        
        $sql = "UPDATE jobs 
                SET status = 'failed',
                    error_text = 'Job cancelled by admin',
                    updated_at = ?
                WHERE id = ?";
        
        $this->db->execute($sql, [
            $now->format('Y-m-d H:i:s'),
            $jobId
        ]);
    }
    
    /**
     * Retry a failed job
     * 
     * @param string $jobId Job ID
     */
    public function retryJob($jobId) {
        $now = new DateTime();
        
        $sql = "UPDATE jobs 
                SET status = 'pending',
                    available_at = ?,
                    error_text = NULL,
                    locked_by = NULL,
                    locked_at = NULL,
                    updated_at = ?
                WHERE id = ?";
        
        $this->db->execute($sql, [
            $now->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
            $jobId
        ]);
    }
    
    /**
     * Get queue statistics
     * 
     * @return array Stats (pending, running, completed, failed counts)
     */
    public function getStats() {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM jobs 
                GROUP BY status";
        
        $rows = $this->db->query($sql);
        
        $stats = [
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        foreach ($rows as $row) {
            $stats[$row['status']] = (int)$row['count'];
        }
        
        return $stats;
    }
    
    /**
     * Clean up old completed/failed jobs
     * 
     * @param int $olderThanDays Delete jobs older than this many days
     * @return int Number of jobs deleted
     */
    public function cleanup($olderThanDays = 30) {
        $cutoff = new DateTime();
        $cutoff->modify("-{$olderThanDays} days");
        
        $sql = "DELETE FROM jobs 
                WHERE status IN ('completed', 'failed') 
                AND created_at < ?";
        
        return $this->db->execute($sql, [$cutoff->format('Y-m-d H:i:s')]);
    }
    
    /**
     * Generate UUID v4
     * 
     * @return string UUID
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
