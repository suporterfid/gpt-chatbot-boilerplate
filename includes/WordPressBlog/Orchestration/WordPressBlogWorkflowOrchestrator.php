<?php
/**
 * WordPress Blog Workflow Orchestrator
 *
 * High-level orchestrator that polls the queue, manages workflow execution,
 * implements retry logic with exponential backoff, and performs system health checks.
 *
 * Responsibilities:
 * - Poll queue for pending articles
 * - Execute generation workflow
 * - Implement retry logic with exponential backoff
 * - Prevent duplicate processing with database locking
 * - Handle success and failure scenarios
 * - System health monitoring
 *
 * @package WordPressBlog\Orchestration
 */

class WordPressBlogWorkflowOrchestrator {
    private $db;
    private $cryptoAdapter;
    private $openAIClient;
    private $queueService;
    private $generatorService;
    private $maxRetries = 3;
    private $retryDelays = [0, 300, 900]; // 0s, 5m, 15m in seconds

    /**
     * Constructor
     *
     * @param DB $db Database instance
     * @param CryptoAdapter $cryptoAdapter Encryption adapter
     * @param OpenAIClient $openAIClient OpenAI client
     */
    public function __construct($db, $cryptoAdapter, $openAIClient) {
        $this->db = $db;
        $this->cryptoAdapter = $cryptoAdapter;
        $this->openAIClient = $openAIClient;

        $this->queueService = new WordPressBlogQueueService($db);
        $this->generatorService = new WordPressBlogGeneratorService($db, $cryptoAdapter, $openAIClient);
    }

    /**
     * Process queue
     *
     * Fetch and process articles from queue with FIFO ordering.
     *
     * @param int $maxArticles Maximum number of articles to process
     * @param bool $verbose Verbose output
     * @return array Processing results
     */
    public function processQueue($maxArticles = 10, $verbose = false) {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'articles' => []
        ];

        $this->log('Starting queue processing...', $verbose);

        for ($i = 0; $i < $maxArticles; $i++) {
            // Get next queued article with database locking
            $article = $this->queueService->getNextQueuedArticle();

            if (!$article) {
                $this->log('No more articles in queue', $verbose);
                break;
            }

            $results['processed']++;

            $this->log("Processing article: {$article['article_id']}", $verbose);

            try {
                $result = $this->processArticle($article['article_id'], $verbose);

                if ($result['success']) {
                    $results['succeeded']++;
                    $results['articles'][] = [
                        'article_id' => $article['article_id'],
                        'status' => 'success',
                        'post_url' => $result['post_url'] ?? null
                    ];

                    $this->log("✓ Article {$article['article_id']} completed successfully", $verbose);
                } else {
                    $results['failed']++;
                    $results['articles'][] = [
                        'article_id' => $article['article_id'],
                        'status' => 'failed',
                        'error' => $result['error'] ?? 'Unknown error'
                    ];

                    $this->log("✗ Article {$article['article_id']} failed: {$result['error']}", $verbose);
                }

            } catch (Exception $e) {
                $results['failed']++;
                $results['articles'][] = [
                    'article_id' => $article['article_id'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];

                $this->log("✗ Article {$article['article_id']} failed: {$e->getMessage()}", $verbose);
            }
        }

        $this->log("Queue processing complete. Processed: {$results['processed']}, Succeeded: {$results['succeeded']}, Failed: {$results['failed']}", $verbose);

        return $results;
    }

    /**
     * Process single article with retry logic
     *
     * @param string $articleId Article ID
     * @param bool $verbose Verbose output
     * @return array Result
     */
    public function processArticle($articleId, $verbose = false) {
        $article = $this->queueService->getArticle($articleId);

        if (!$article) {
            return [
                'success' => false,
                'error' => 'Article not found'
            ];
        }

        // Check if article is in a processable state
        if (!in_array($article['status'], ['queued', 'failed'])) {
            return [
                'success' => false,
                'error' => "Article status '{$article['status']}' is not processable"
            ];
        }

        // Execute with retry logic
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            $this->log("Attempt {$attempt}/{$this->maxRetries} for article {$articleId}", $verbose);

            try {
                $result = $this->generatorService->generateArticle($articleId);
                $result['attempts'] = $attempt;

                return $result;

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $this->log("Attempt {$attempt} failed: {$lastError}", $verbose);

                // If not the last attempt, wait before retrying
                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt - 1] ?? 0;

                    if ($delay > 0) {
                        $this->log("Waiting {$delay} seconds before retry...", $verbose);
                        sleep($delay);
                    }

                    // Reset article status to queued for retry
                    $this->queueService->requeueArticle($articleId);
                }
            }
        }

        // All retries exhausted
        return [
            'success' => false,
            'error' => $lastError,
            'attempts' => $attempt
        ];
    }

    /**
     * Process specific article by ID
     *
     * @param string $articleId Article ID
     * @param bool $verbose Verbose output
     * @return array Result
     */
    public function processSpecificArticle($articleId, $verbose = false) {
        $this->log("Processing specific article: {$articleId}", $verbose);

        try {
            $result = $this->processArticle($articleId, $verbose);

            if ($result['success']) {
                $this->log("✓ Article processed successfully", $verbose);
            } else {
                $this->log("✗ Article processing failed: {$result['error']}", $verbose);
            }

            return $result;

        } catch (Exception $e) {
            $this->log("✗ Error: {$e->getMessage()}", $verbose);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * System health check
     *
     * Verify all required services and dependencies are operational.
     *
     * @return array Health check results
     */
    public function healthCheck() {
        $results = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Check 1: Database connectivity
        try {
            $this->db->query("SELECT 1");
            $results['checks']['database'] = [
                'status' => 'ok',
                'message' => 'Database connection successful'
            ];
        } catch (Exception $e) {
            $results['checks']['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
            $results['status'] = 'unhealthy';
        }

        // Check 2: Blog tables exist
        try {
            $configResult = $this->db->query("SELECT COUNT(*) as count FROM blog_configurations");
            $configCount = $configResult[0]['count'] ?? 0;

            $queueResult = $this->db->query("SELECT COUNT(*) as count FROM blog_articles_queue");
            $queueCount = $queueResult[0]['count'] ?? 0;

            $results['checks']['blog_tables'] = [
                'status' => 'ok',
                'message' => "Tables accessible (configs: {$configCount}, queue: {$queueCount})"
            ];
        } catch (Exception $e) {
            $results['checks']['blog_tables'] = [
                'status' => 'error',
                'message' => 'Blog tables check failed: ' . $e->getMessage()
            ];
            $results['status'] = 'unhealthy';
        }

        // Check 3: Queue service
        try {
            $stats = $this->queueService->getQueueStats();
            $results['checks']['queue_service'] = [
                'status' => 'ok',
                'message' => 'Queue service operational',
                'statistics' => $stats
            ];
        } catch (Exception $e) {
            $results['checks']['queue_service'] = [
                'status' => 'error',
                'message' => 'Queue service failed: ' . $e->getMessage()
            ];
            $results['status'] = 'unhealthy';
        }

        // Check 4: Log directory writable
        $logDir = __DIR__ . '/../../../logs/wordpress_blog';
        try {
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            if (is_writable($logDir)) {
                $results['checks']['log_directory'] = [
                    'status' => 'ok',
                    'message' => 'Log directory writable',
                    'path' => $logDir
                ];
            } else {
                $results['checks']['log_directory'] = [
                    'status' => 'warning',
                    'message' => 'Log directory not writable',
                    'path' => $logDir
                ];
                if ($results['status'] === 'healthy') {
                    $results['status'] = 'degraded';
                }
            }
        } catch (Exception $e) {
            $results['checks']['log_directory'] = [
                'status' => 'error',
                'message' => 'Log directory check failed: ' . $e->getMessage()
            ];
            if ($results['status'] === 'healthy') {
                $results['status'] = 'degraded';
            }
        }

        // Check 5: Encryption service
        try {
            $testData = $this->cryptoAdapter->encrypt('test');
            $decrypted = $this->cryptoAdapter->decrypt(
                $testData['ciphertext'],
                $testData['nonce'],
                $testData['tag']
            );

            if ($decrypted === 'test') {
                $results['checks']['encryption'] = [
                    'status' => 'ok',
                    'message' => 'Encryption service operational'
                ];
            } else {
                $results['checks']['encryption'] = [
                    'status' => 'error',
                    'message' => 'Encryption test failed'
                ];
                $results['status'] = 'unhealthy';
            }
        } catch (Exception $e) {
            $results['checks']['encryption'] = [
                'status' => 'error',
                'message' => 'Encryption service failed: ' . $e->getMessage()
            ];
            $results['status'] = 'unhealthy';
        }

        return $results;
    }

    /**
     * Get queue statistics
     *
     * @return array Statistics
     */
    public function getQueueStatistics() {
        return $this->queueService->getQueueStats();
    }

    /**
     * Get processing statistics
     *
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public function getProcessingStatistics($days = 7) {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $sql = "
            SELECT
                status,
                COUNT(*) as count,
                AVG(
                    CASE
                        WHEN processing_started_at IS NOT NULL AND processing_completed_at IS NOT NULL
                        THEN (julianday(processing_completed_at) - julianday(processing_started_at)) * 86400
                        ELSE NULL
                    END
                ) as avg_duration_seconds
            FROM blog_articles_queue
            WHERE created_at >= ?
            GROUP BY status
        ";

        $results = $this->db->query($sql, [$startDate]);

        $stats = [
            'period_days' => $days,
            'start_date' => $startDate,
            'by_status' => [],
            'total_articles' => 0
        ];

        while ($row = $results->fetch()) {
            $stats['by_status'][$row['status']] = [
                'count' => intval($row['count']),
                'avg_duration_seconds' => $row['avg_duration_seconds']
                    ? round($row['avg_duration_seconds'], 2)
                    : null
            ];
            $stats['total_articles'] += intval($row['count']);
        }

        return $stats;
    }

    /**
     * Stop all processing
     *
     * Mark all processing articles as queued to allow reprocessing.
     *
     * @return int Number of articles reset
     */
    public function stopProcessing() {
        $sql = "
            UPDATE blog_articles_queue
            SET status = 'queued',
                processing_started_at = NULL
            WHERE status = 'processing'
        ";

        $result = $this->db->execute($sql);

        return $this->db->query("SELECT changes() as count")->fetch()['count'];
    }

    /**
     * Clean up old completed articles
     *
     * @param int $days Days to keep
     * @return int Number of articles deleted
     */
    public function cleanupOldArticles($days = 30) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $sql = "
            DELETE FROM blog_articles_queue
            WHERE status IN ('completed', 'published')
              AND processing_completed_at < ?
        ";

        $this->db->execute($sql, [$cutoffDate]);

        return $this->db->query("SELECT changes() as count")->fetch()['count'];
    }

    /**
     * Log message
     *
     * @param string $message Message
     * @param bool $verbose Output to console
     */
    private function log($message, $verbose = false) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";

        if ($verbose) {
            echo $logMessage . "\n";
        }

        // Also log to file
        $logDir = __DIR__ . '/../../../logs/wordpress_blog';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/orchestrator_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage . "\n", FILE_APPEND);
    }

    /**
     * Get failed articles that can be retried
     *
     * @param int $limit Maximum number to return
     * @return array Failed articles
     */
    public function getRetryableFailedArticles($limit = 10) {
        $sql = "
            SELECT article_id, seed_keyword, configuration_id,
                   retry_count, error_message, processing_started_at
            FROM blog_articles_queue
            WHERE status = 'failed'
              AND retry_count < ?
            ORDER BY processing_started_at DESC
            LIMIT ?
        ";

        $results = $this->db->query($sql, [$this->maxRetries, $limit]);
        $articles = [];

        while ($row = $results->fetch()) {
            $articles[] = $row;
        }

        return $articles;
    }

    /**
     * Retry all failed articles
     *
     * @param bool $verbose Verbose output
     * @return array Results
     */
    public function retryFailedArticles($verbose = false) {
        $failedArticles = $this->getRetryableFailedArticles(100);

        $results = [
            'total' => count($failedArticles),
            'retried' => 0,
            'succeeded' => 0,
            'failed' => 0
        ];

        $this->log("Found {$results['total']} failed articles to retry", $verbose);

        foreach ($failedArticles as $article) {
            $results['retried']++;

            $this->log("Retrying article: {$article['article_id']}", $verbose);

            $result = $this->processArticle($article['article_id'], $verbose);

            if ($result['success']) {
                $results['succeeded']++;
                $this->log("✓ Retry successful", $verbose);
            } else {
                $results['failed']++;
                $this->log("✗ Retry failed", $verbose);
            }
        }

        return $results;
    }
}
