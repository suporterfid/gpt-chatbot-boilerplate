<?php
/**
 * WordPress Blog Queue Service
 *
 * Manages article queue with database-level locking, status transitions,
 * and category/tag management.
 *
 * @package WordPressBlog\Services
 */

require_once __DIR__ . '/../../DB.php';
require_once __DIR__ . '/WordPressBlogConfigurationService.php';

class WordPressBlogQueueService {
    private $db;
    private $configService;

    /**
     * Valid status transitions
     * Maps current status => allowed next statuses
     */
    private $validTransitions = [
        'queued' => ['processing', 'failed'],
        'processing' => ['completed', 'failed'],
        'completed' => ['published', 'failed'],
        'failed' => ['queued'], // Allow retry
        'published' => [] // Terminal state
    ];

    /**
     * Constructor
     *
     * @param DB $db Database instance
     * @param WordPressBlogConfigurationService $configService Configuration service
     */
    public function __construct($db, $configService = null) {
        $this->db = $db;
        $this->configService = $configService;
    }

    // ============================================================
    // Queue Management
    // ============================================================

    /**
     * Queue a new article
     *
     * @param array $articleData Article data
     * @return string Article ID (UUID)
     * @throws Exception
     */
    public function queueArticle(array $articleData) {
        // Validate required fields
        if (empty($articleData['configuration_id']) || empty($articleData['seed_keyword'])) {
            throw new Exception("configuration_id and seed_keyword are required");
        }

        // Verify configuration exists
        if ($this->configService) {
            $config = $this->configService->getConfiguration($articleData['configuration_id']);
            if (!$config) {
                throw new Exception("Configuration not found: {$articleData['configuration_id']}");
            }
        }

        // Generate UUID for article_id
        $articleId = $this->generateUUID();

        $sql = "INSERT INTO blog_articles_queue (
            article_id, configuration_id, status, seed_keyword,
            target_audience, writing_style, publication_date, scheduled_date
        ) VALUES (
            :article_id, :configuration_id, :status, :seed_keyword,
            :target_audience, :writing_style, :publication_date, :scheduled_date
        )";

        $this->db->execute($sql, [
            'article_id' => $articleId,
            'configuration_id' => $articleData['configuration_id'],
            'status' => 'queued',
            'seed_keyword' => $articleData['seed_keyword'],
            'target_audience' => $articleData['target_audience'] ?? null,
            'writing_style' => $articleData['writing_style'] ?? null,
            'publication_date' => $articleData['publication_date'] ?? null,
            'scheduled_date' => $articleData['scheduled_date'] ?? null
        ]);

        return $articleId;
    }

    /**
     * Get next queued article with row-level locking
     * Uses FIFO ordering (scheduled_date, then created_at)
     *
     * @return array|null Article data with configuration, or null if queue is empty
     */
    public function getNextQueuedArticle() {
        // Start transaction for row locking
        $this->db->execute("BEGIN IMMEDIATE TRANSACTION");

        try {
            // Get next queued article with row lock
            // SQLite doesn't support SELECT FOR UPDATE, but BEGIN IMMEDIATE gives us exclusive write lock
            $sql = "SELECT * FROM blog_articles_queue
                    WHERE status = 'queued'
                      AND (scheduled_date IS NULL OR scheduled_date <= datetime('now'))
                    ORDER BY
                        CASE WHEN scheduled_date IS NULL THEN 1 ELSE 0 END,
                        scheduled_date ASC,
                        created_at ASC
                    LIMIT 1";

            $results = $this->db->query($sql);

            if (empty($results)) {
                $this->db->execute("COMMIT");
                return null;
            }

            $article = $results[0];

            // Get configuration data
            if ($this->configService) {
                $config = $this->configService->getConfiguration(
                    $article['configuration_id'],
                    true // Include credentials
                );

                if ($config) {
                    $article['configuration'] = $config;
                }
            }

            // Parse JSON keywords if present
            if (!empty($article['relevance_keywords'])) {
                $article['relevance_keywords'] = json_decode($article['relevance_keywords'], true);
            }

            $this->db->execute("COMMIT");

            return $article;

        } catch (Exception $e) {
            $this->db->execute("ROLLBACK");
            throw $e;
        }
    }

    /**
     * Get article by ID
     *
     * @param string $articleId Article ID
     * @param bool $includeConfig Whether to include configuration data
     * @return array|null Article data or null if not found
     */
    public function getArticle(string $articleId, bool $includeConfig = false) {
        $sql = "SELECT * FROM blog_articles_queue WHERE article_id = :article_id";
        $results = $this->db->query($sql, ['article_id' => $articleId]);

        if (empty($results)) {
            return null;
        }

        $article = $results[0];

        // Include configuration if requested
        if ($includeConfig && $this->configService) {
            $config = $this->configService->getConfiguration(
                $article['configuration_id'],
                true
            );

            if ($config) {
                $article['configuration'] = $config;
            }
        }

        return $article;
    }

    /**
     * Requeue a failed article for retry
     *
     * @param string $articleId Article ID
     * @return bool Success status
     * @throws Exception
     */
    public function requeueArticle(string $articleId) {
        $article = $this->getArticle($articleId);

        if (!$article) {
            throw new Exception("Article not found: $articleId");
        }

        // Can only requeue failed articles
        if ($article['status'] !== 'failed') {
            throw new Exception("Can only requeue failed articles. Current status: {$article['status']}");
        }

        // Reset status to queued and clear error message
        return $this->updateStatus($articleId, 'queued');
    }

    /**
     * Cancel a queued or processing article
     *
     * @param string $articleId Article ID
     * @return bool Success status
     */
    public function cancelArticle(string $articleId) {
        $article = $this->getArticle($articleId);

        if (!$article) {
            return false;
        }

        // Can only cancel queued or failed articles
        if (!in_array($article['status'], ['queued', 'failed'])) {
            throw new Exception("Cannot cancel article with status: {$article['status']}");
        }

        return $this->deleteArticle($articleId);
    }

    /**
     * Delete an article from queue
     *
     * @param string $articleId Article ID
     * @return bool Success status
     */
    public function deleteArticle(string $articleId) {
        $sql = "DELETE FROM blog_articles_queue WHERE article_id = :article_id";
        $rowsAffected = $this->db->execute($sql, ['article_id' => $articleId]);

        return $rowsAffected > 0;
    }

    // ============================================================
    // Status Management
    // ============================================================

    /**
     * Update article status with validation
     *
     * @param string $articleId Article ID
     * @param string $newStatus New status
     * @param string|null $errorMessage Error message (for failed status)
     * @return bool Success status
     * @throws Exception
     */
    public function updateStatus(string $articleId, string $newStatus, ?string $errorMessage = null) {
        // Get current article
        $article = $this->getArticle($articleId);

        if (!$article) {
            throw new Exception("Article not found: $articleId");
        }

        $currentStatus = $article['status'];

        // Validate status transition
        if (!$this->isValidTransition($currentStatus, $newStatus)) {
            throw new Exception("Invalid status transition: $currentStatus => $newStatus");
        }

        // Update status
        $updates = ['status' => $newStatus];

        // Set timestamps based on status
        if ($newStatus === 'processing') {
            $updates['processing_started_at'] = date('Y-m-d H:i:s');
        } elseif (in_array($newStatus, ['completed', 'published', 'failed'])) {
            $updates['processing_completed_at'] = date('Y-m-d H:i:s');
        }

        // Add error message if failed
        if ($newStatus === 'failed' && $errorMessage) {
            $updates['error_message'] = $errorMessage;
            $updates['retry_count'] = $article['retry_count'] + 1;
        }

        // Reset error message if recovering from failed state
        if ($currentStatus === 'failed' && $newStatus === 'queued') {
            $updates['error_message'] = null;
        }

        return $this->updateArticleFields($articleId, $updates);
    }

    /**
     * Mark article as processing
     *
     * @param string $articleId Article ID
     * @return bool Success status
     */
    public function markAsProcessing(string $articleId) {
        return $this->updateStatus($articleId, 'processing');
    }

    /**
     * Mark article as completed (generated but not yet published)
     *
     * @param string $articleId Article ID
     * @param int $wpPostId WordPress post ID
     * @param string $wpPostUrl WordPress post URL
     * @return bool Success status
     */
    public function markAsCompleted(string $articleId, int $wpPostId, string $wpPostUrl) {
        $success = $this->updateStatus($articleId, 'completed');

        if ($success) {
            $this->updateArticleFields($articleId, [
                'wordpress_post_id' => $wpPostId,
                'wordpress_post_url' => $wpPostUrl
            ]);
        }

        return $success;
    }

    /**
     * Mark article as failed
     *
     * @param string $articleId Article ID
     * @param string $errorMessage Error message
     * @return bool Success status
     */
    public function markAsFailed(string $articleId, string $errorMessage) {
        return $this->updateStatus($articleId, 'failed', $errorMessage);
    }

    /**
     * Mark article as published
     *
     * @param string $articleId Article ID
     * @return bool Success status
     */
    public function markAsPublished(string $articleId) {
        $article = $this->getArticle($articleId);

        if (!$article) {
            throw new Exception("Article not found: $articleId");
        }

        // Set publication_date to now if not already set
        $updates = ['status' => 'published'];

        if (empty($article['publication_date'])) {
            $updates['publication_date'] = date('Y-m-d H:i:s');
        }

        return $this->updateArticleFields($articleId, $updates);
    }

    /**
     * Check if status transition is valid
     *
     * @param string $currentStatus Current status
     * @param string $newStatus New status
     * @return bool True if transition is valid
     */
    private function isValidTransition(string $currentStatus, string $newStatus) {
        if (!isset($this->validTransitions[$currentStatus])) {
            return false;
        }

        return in_array($newStatus, $this->validTransitions[$currentStatus]);
    }

    // ============================================================
    // Query Methods
    // ============================================================

    /**
     * List queued articles
     *
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array Array of articles
     */
    public function listQueuedArticles(int $limit = 50, int $offset = 0) {
        return $this->listByStatus('queued', $limit, $offset);
    }

    /**
     * List articles by status
     *
     * @param string $status Status filter
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array Array of articles
     */
    public function listByStatus(string $status, int $limit = 50, int $offset = 0) {
        $sql = "SELECT a.*, c.config_name
                FROM blog_articles_queue a
                LEFT JOIN blog_configurations c ON a.configuration_id = c.configuration_id
                WHERE a.status = :status
                ORDER BY a.created_at DESC
                LIMIT :limit OFFSET :offset";

        return $this->db->query($sql, [
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * List articles by configuration
     *
     * @param string $configId Configuration ID
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array Array of articles
     */
    public function listByConfiguration(string $configId, int $limit = 50, int $offset = 0) {
        $sql = "SELECT * FROM blog_articles_queue
                WHERE configuration_id = :config_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        return $this->db->query($sql, [
            'config_id' => $configId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Get queue statistics
     *
     * @return array Statistics
     */
    public function getQueueStats() {
        $sql = "SELECT
                    status,
                    COUNT(*) as count,
                    AVG(CASE
                        WHEN processing_completed_at IS NOT NULL AND processing_started_at IS NOT NULL
                        THEN (julianday(processing_completed_at) - julianday(processing_started_at)) * 86400
                        ELSE NULL
                    END) as avg_processing_seconds
                FROM blog_articles_queue
                GROUP BY status";

        $results = $this->db->query($sql);

        $stats = [
            'total_queued' => 0,
            'total_processing' => 0,
            'total_completed' => 0,
            'total_failed' => 0,
            'total_published' => 0,
            'avg_processing_time_seconds' => 0
        ];

        foreach ($results as $row) {
            $status = $row['status'];
            $count = (int)$row['count'];

            $stats["total_$status"] = $count;

            if ($row['avg_processing_seconds']) {
                $stats['avg_processing_time_seconds'] = round($row['avg_processing_seconds'], 2);
            }
        }

        return $stats;
    }

    /**
     * Count articles by status
     *
     * @param string $status Status to count
     * @return int Count
     */
    public function countByStatus(string $status) {
        $sql = "SELECT COUNT(*) as count FROM blog_articles_queue WHERE status = :status";
        $result = $this->db->query($sql, ['status' => $status]);

        return (int)($result[0]['count'] ?? 0);
    }

    // ============================================================
    // Category & Tag Management
    // ============================================================

    /**
     * Add categories to article
     *
     * @param string $articleId Article ID
     * @param array $categories Array of categories (each with optional category_id and category_name)
     * @return bool Success status
     */
    public function addCategories(string $articleId, array $categories) {
        if (empty($categories)) {
            return true;
        }

        foreach ($categories as $category) {
            $categoryName = is_array($category) ? $category['category_name'] : $category;
            $categoryId = is_array($category) ? ($category['category_id'] ?? null) : null;

            $sql = "INSERT INTO blog_article_categories (article_id, category_id, category_name)
                    VALUES (:article_id, :category_id, :category_name)";

            $this->db->execute($sql, [
                'article_id' => $articleId,
                'category_id' => $categoryId,
                'category_name' => $categoryName
            ]);
        }

        return true;
    }

    /**
     * Add tags to article
     *
     * @param string $articleId Article ID
     * @param array $tags Array of tags (each with optional tag_id and tag_name)
     * @return bool Success status
     */
    public function addTags(string $articleId, array $tags) {
        if (empty($tags)) {
            return true;
        }

        foreach ($tags as $tag) {
            $tagName = is_array($tag) ? $tag['tag_name'] : $tag;
            $tagId = is_array($tag) ? ($tag['tag_id'] ?? null) : null;

            $sql = "INSERT INTO blog_article_tags (article_id, tag_id, tag_name)
                    VALUES (:article_id, :tag_id, :tag_name)";

            $this->db->execute($sql, [
                'article_id' => $articleId,
                'tag_id' => $tagId,
                'tag_name' => $tagName
            ]);
        }

        return true;
    }

    /**
     * Get categories for article
     *
     * @param string $articleId Article ID
     * @return array Array of categories
     */
    public function getCategories(string $articleId) {
        $sql = "SELECT * FROM blog_article_categories WHERE article_id = :article_id ORDER BY category_name";
        return $this->db->query($sql, ['article_id' => $articleId]);
    }

    /**
     * Get tags for article
     *
     * @param string $articleId Article ID
     * @return array Array of tags
     */
    public function getTags(string $articleId) {
        $sql = "SELECT * FROM blog_article_tags WHERE article_id = :article_id ORDER BY tag_name";
        return $this->db->query($sql, ['article_id' => $articleId]);
    }

    /**
     * Remove category from article
     *
     * @param string $articleId Article ID
     * @param int $categoryDbId Database ID from blog_article_categories
     * @return bool Success status
     */
    public function removeCategory(string $articleId, int $categoryDbId) {
        $sql = "DELETE FROM blog_article_categories WHERE article_id = :article_id AND id = :id";
        $rowsAffected = $this->db->execute($sql, [
            'article_id' => $articleId,
            'id' => $categoryDbId
        ]);

        return $rowsAffected > 0;
    }

    /**
     * Remove tag from article
     *
     * @param string $articleId Article ID
     * @param int $tagDbId Database ID from blog_article_tags
     * @return bool Success status
     */
    public function removeTag(string $articleId, int $tagDbId) {
        $sql = "DELETE FROM blog_article_tags WHERE article_id = :article_id AND id = :id";
        $rowsAffected = $this->db->execute($sql, [
            'article_id' => $articleId,
            'id' => $tagDbId
        ]);

        return $rowsAffected > 0;
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Update article fields
     *
     * @param string $articleId Article ID
     * @param array $updates Fields to update
     * @return bool Success status
     */
    private function updateArticleFields(string $articleId, array $updates) {
        if (empty($updates)) {
            return true;
        }

        $setClause = [];
        $params = ['article_id' => $articleId];

        foreach ($updates as $field => $value) {
            $setClause[] = "$field = :$field";
            $params[$field] = $value;
        }

        $sql = "UPDATE blog_articles_queue SET " . implode(', ', $setClause) .
               " WHERE article_id = :article_id";

        $rowsAffected = $this->db->execute($sql, $params);

        return $rowsAffected > 0;
    }

    /**
     * Generate a UUID v4
     *
     * @return string UUID
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
