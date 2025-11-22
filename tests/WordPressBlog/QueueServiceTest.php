<?php
/**
 * Unit Tests for WordPressBlogQueueService
 *
 * Tests queue management, status transitions, locking, and category/tag operations
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/DB.php';
require_once __DIR__ . '/../../includes/WordPressBlog/Services/WordPressBlogConfigurationService.php';
require_once __DIR__ . '/../../includes/WordPressBlog/Services/WordPressBlogQueueService.php';

final class QueueServiceTest extends TestCase
{
    private $db;
    private $configService;
    private $queueService;
    private $testConfigId;

    protected function setUp(): void
    {
        // Use in-memory SQLite database for testing
        $this->db = new DB([
            'database_url' => 'sqlite::memory:',
            'app_env' => 'testing'
        ]);

        // Run migration to create tables
        $this->runMigration();

        // Initialize services
        $this->configService = new WordPressBlogConfigurationService($this->db, [
            'encryption_key' => 'test-encryption-key-for-unit-tests-12345'
        ]);

        $this->queueService = new WordPressBlogQueueService($this->db, $this->configService);

        // Create a test configuration for use in tests
        $this->testConfigId = $this->configService->createConfiguration([
            'config_name' => 'Test Config',
            'website_url' => 'https://test.com',
            'wordpress_api_url' => 'https://test.com/wp-json',
            'wordpress_api_key' => 'test_wordpress_key_12345',
            'openai_api_key' => 'sk-test-openai-key-12345'
        ]);
    }

    protected function tearDown(): void
    {
        $this->db = null;
        $this->configService = null;
        $this->queueService = null;
    }

    /**
     * Run database migration for blog tables
     */
    private function runMigration(): void
    {
        $migrationSql = file_get_contents(__DIR__ . '/../../db/migrations/048_add_wordpress_blog_tables.sql');

        $reflection = new ReflectionClass($this->db);
        $property = $reflection->getProperty('pdo');
        $property->setAccessible(true);
        $pdo = $property->getValue($this->db);
        $pdo->exec($migrationSql);
    }

    /**
     * Helper: Create test article data
     */
    private function createTestArticleData(array $overrides = []): array
    {
        return array_merge([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Test Article Topic',
            'target_audience' => 'Developers',
            'writing_style' => 'technical'
        ], $overrides);
    }

    // ========================================
    // Queue Management Tests
    // ========================================

    public function testQueueArticle(): void
    {
        $articleData = $this->createTestArticleData();
        $articleId = $this->queueService->queueArticle($articleData);

        $this->assertNotEmpty($articleId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $articleId);
    }

    public function testQueueArticleWithMissingConfigurationId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('configuration_id and seed_keyword are required');

        $this->queueService->queueArticle([
            'seed_keyword' => 'Test Topic'
            // Missing configuration_id
        ]);
    }

    public function testQueueArticleWithMissingSeedKeyword(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('configuration_id and seed_keyword are required');

        $this->queueService->queueArticle([
            'configuration_id' => $this->testConfigId
            // Missing seed_keyword
        ]);
    }

    public function testQueueArticleWithNonExistentConfiguration(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configuration not found');

        $this->queueService->queueArticle([
            'configuration_id' => 'non-existent-uuid',
            'seed_keyword' => 'Test Topic'
        ]);
    }

    public function testGetArticle(): void
    {
        $articleData = $this->createTestArticleData();
        $articleId = $this->queueService->queueArticle($articleData);

        $article = $this->queueService->getArticle($articleId);

        $this->assertNotNull($article);
        $this->assertEquals($articleId, $article['article_id']);
        $this->assertEquals($articleData['seed_keyword'], $article['seed_keyword']);
        $this->assertEquals('queued', $article['status']);
    }

    public function testGetArticleWithConfiguration(): void
    {
        $articleData = $this->createTestArticleData();
        $articleId = $this->queueService->queueArticle($articleData);

        $article = $this->queueService->getArticle($articleId, true);

        $this->assertArrayHasKey('configuration', $article);
        $this->assertEquals($this->testConfigId, $article['configuration']['configuration_id']);
    }

    public function testGetNonExistentArticle(): void
    {
        $article = $this->queueService->getArticle('non-existent-uuid');

        $this->assertNull($article);
    }

    public function testGetNextQueuedArticle(): void
    {
        $articleData = $this->createTestArticleData();
        $articleId = $this->queueService->queueArticle($articleData);

        $nextArticle = $this->queueService->getNextQueuedArticle();

        $this->assertNotNull($nextArticle);
        $this->assertEquals($articleId, $nextArticle['article_id']);
        $this->assertEquals('queued', $nextArticle['status']);
        $this->assertArrayHasKey('configuration', $nextArticle);
    }

    public function testGetNextQueuedArticleEmptyQueue(): void
    {
        $nextArticle = $this->queueService->getNextQueuedArticle();

        $this->assertNull($nextArticle);
    }

    public function testGetNextQueuedArticleFIFOOrdering(): void
    {
        // Queue 3 articles
        $id1 = $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Article 1']));
        sleep(1); // Ensure different timestamps
        $id2 = $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Article 2']));
        sleep(1);
        $id3 = $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Article 3']));

        // First article should be the oldest
        $next = $this->queueService->getNextQueuedArticle();
        $this->assertEquals($id1, $next['article_id']);
    }

    public function testGetNextQueuedArticleWithScheduledDate(): void
    {
        $futureDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 hour'));

        // Queue article scheduled in the future
        $futureId = $this->queueService->queueArticle($this->createTestArticleData([
            'seed_keyword' => 'Future Article',
            'scheduled_date' => $futureDate
        ]));

        // Queue article scheduled in the past
        $pastId = $this->queueService->queueArticle($this->createTestArticleData([
            'seed_keyword' => 'Past Article',
            'scheduled_date' => $pastDate
        ]));

        // Should get the past article, not the future one
        $next = $this->queueService->getNextQueuedArticle();
        $this->assertEquals($pastId, $next['article_id']);
    }

    public function testDeleteArticle(): void
    {
        $articleData = $this->createTestArticleData();
        $articleId = $this->queueService->queueArticle($articleData);

        $success = $this->queueService->deleteArticle($articleId);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertNull($article);
    }

    public function testCancelQueuedArticle(): void
    {
        $articleData = $this->createTestArticleData();
        $articleId = $this->queueService->queueArticle($articleData);

        $success = $this->queueService->cancelArticle($articleId);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertNull($article);
    }

    public function testCancelProcessingArticleFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot cancel article with status: processing');

        $articleData = $this->createTestArticleData();
        $articleId = $this->queueService->queueArticle($articleData);

        // Mark as processing
        $this->queueService->markAsProcessing($articleId);

        // Attempt to cancel should fail
        $this->queueService->cancelArticle($articleId);
    }

    // ========================================
    // Status Management Tests
    // ========================================

    public function testUpdateStatusQueuedToProcessing(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $success = $this->queueService->updateStatus($articleId, 'processing');
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('processing', $article['status']);
        $this->assertNotNull($article['processing_started_at']);
    }

    public function testUpdateStatusProcessingToCompleted(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->updateStatus($articleId, 'processing');

        $success = $this->queueService->updateStatus($articleId, 'completed');
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('completed', $article['status']);
        $this->assertNotNull($article['processing_completed_at']);
    }

    public function testUpdateStatusCompletedToPublished(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->updateStatus($articleId, 'processing');
        $this->queueService->updateStatus($articleId, 'completed');

        $success = $this->queueService->markAsPublished($articleId);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('published', $article['status']);
        $this->assertNotNull($article['publication_date']);
    }

    public function testUpdateStatusProcessingToFailed(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->updateStatus($articleId, 'processing');

        $errorMessage = 'OpenAI API Error';
        $success = $this->queueService->updateStatus($articleId, 'failed', $errorMessage);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('failed', $article['status']);
        $this->assertEquals($errorMessage, $article['error_message']);
        $this->assertEquals(1, $article['retry_count']);
    }

    public function testUpdateStatusFailedToQueued(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->updateStatus($articleId, 'processing');
        $this->queueService->updateStatus($articleId, 'failed', 'Error');

        $success = $this->queueService->updateStatus($articleId, 'queued');
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('queued', $article['status']);
        $this->assertNull($article['error_message']); // Error should be cleared
    }

    public function testUpdateStatusInvalidTransition(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid status transition: queued => published');

        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        // Cannot go directly from queued to published
        $this->queueService->updateStatus($articleId, 'published');
    }

    public function testUpdateStatusPublishedIsTerminal(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid status transition: published => queued');

        $articleId = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->updateStatus($articleId, 'processing');
        $this->queueService->updateStatus($articleId, 'completed');
        $this->queueService->markAsPublished($articleId);

        // Cannot transition from published
        $this->queueService->updateStatus($articleId, 'queued');
    }

    public function testMarkAsProcessing(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $success = $this->queueService->markAsProcessing($articleId);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('processing', $article['status']);
    }

    public function testMarkAsCompleted(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->markAsProcessing($articleId);

        $wpPostId = 12345;
        $wpPostUrl = 'https://test.com/article-slug';

        $success = $this->queueService->markAsCompleted($articleId, $wpPostId, $wpPostUrl);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('completed', $article['status']);
        $this->assertEquals($wpPostId, $article['wordpress_post_id']);
        $this->assertEquals($wpPostUrl, $article['wordpress_post_url']);
    }

    public function testMarkAsFailed(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->markAsProcessing($articleId);

        $errorMessage = 'Generation failed due to API timeout';
        $success = $this->queueService->markAsFailed($articleId, $errorMessage);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('failed', $article['status']);
        $this->assertEquals($errorMessage, $article['error_message']);
        $this->assertEquals(1, $article['retry_count']);
    }

    public function testRetryCountIncrementsOnFailure(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        // First failure
        $this->queueService->markAsProcessing($articleId);
        $this->queueService->markAsFailed($articleId, 'Error 1');
        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals(1, $article['retry_count']);

        // Second failure
        $this->queueService->updateStatus($articleId, 'queued');
        $this->queueService->markAsProcessing($articleId);
        $this->queueService->markAsFailed($articleId, 'Error 2');
        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals(2, $article['retry_count']);
    }

    // ========================================
    // Query Methods Tests
    // ========================================

    public function testListQueuedArticles(): void
    {
        $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Article 1']));
        $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Article 2']));

        $queued = $this->queueService->listQueuedArticles();

        $this->assertCount(2, $queued);
    }

    public function testListByStatus(): void
    {
        $id1 = $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Queued']));
        $id2 = $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Processing']));
        $this->queueService->markAsProcessing($id2);

        $queued = $this->queueService->listByStatus('queued');
        $processing = $this->queueService->listByStatus('processing');

        $this->assertCount(1, $queued);
        $this->assertCount(1, $processing);
        $this->assertEquals($id1, $queued[0]['article_id']);
        $this->assertEquals($id2, $processing[0]['article_id']);
    }

    public function testListByConfiguration(): void
    {
        // Create another configuration
        $config2Id = $this->configService->createConfiguration([
            'config_name' => 'Config 2',
            'website_url' => 'https://test2.com',
            'wordpress_api_url' => 'https://test2.com/wp-json',
            'wordpress_api_key' => 'test_key_2_12345678901',
            'openai_api_key' => 'sk-test-key-2-12345'
        ]);

        // Queue articles for both configurations
        $this->queueService->queueArticle($this->createTestArticleData(['configuration_id' => $this->testConfigId]));
        $this->queueService->queueArticle($this->createTestArticleData(['configuration_id' => $this->testConfigId]));
        $this->queueService->queueArticle($this->createTestArticleData(['configuration_id' => $config2Id]));

        $config1Articles = $this->queueService->listByConfiguration($this->testConfigId);
        $config2Articles = $this->queueService->listByConfiguration($config2Id);

        $this->assertCount(2, $config1Articles);
        $this->assertCount(1, $config2Articles);
    }

    public function testGetQueueStats(): void
    {
        // Create articles with different statuses
        $id1 = $this->queueService->queueArticle($this->createTestArticleData());
        $id2 = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->markAsProcessing($id2);

        $id3 = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->markAsProcessing($id3);
        $this->queueService->markAsCompleted($id3, 123, 'https://test.com/article');

        $stats = $this->queueService->getQueueStats();

        $this->assertEquals(1, $stats['total_queued']);
        $this->assertEquals(1, $stats['total_processing']);
        $this->assertEquals(1, $stats['total_completed']);
        $this->assertEquals(0, $stats['total_failed']);
    }

    public function testCountByStatus(): void
    {
        $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->queueArticle($this->createTestArticleData());
        $id3 = $this->queueService->queueArticle($this->createTestArticleData());
        $this->queueService->markAsProcessing($id3);

        $queuedCount = $this->queueService->countByStatus('queued');
        $processingCount = $this->queueService->countByStatus('processing');

        $this->assertEquals(2, $queuedCount);
        $this->assertEquals(1, $processingCount);
    }

    // ========================================
    // Category & Tag Tests
    // ========================================

    public function testAddCategories(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $success = $this->queueService->addCategories($articleId, [
            ['category_name' => 'Technology'],
            ['category_name' => 'Programming', 'category_id' => 5]
        ]);

        $this->assertTrue($success);

        $categories = $this->queueService->getCategories($articleId);
        $this->assertCount(2, $categories);
        $this->assertEquals('Programming', $categories[0]['category_name']);
    }

    public function testAddCategoriesAsStrings(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $success = $this->queueService->addCategories($articleId, [
            'Category 1',
            'Category 2'
        ]);

        $this->assertTrue($success);

        $categories = $this->queueService->getCategories($articleId);
        $this->assertCount(2, $categories);
    }

    public function testAddTags(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $success = $this->queueService->addTags($articleId, [
            ['tag_name' => 'php'],
            ['tag_name' => 'tutorial', 'tag_id' => 10]
        ]);

        $this->assertTrue($success);

        $tags = $this->queueService->getTags($articleId);
        $this->assertCount(2, $tags);
    }

    public function testGetCategories(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $this->queueService->addCategories($articleId, ['Cat 1', 'Cat 2', 'Cat 3']);

        $categories = $this->queueService->getCategories($articleId);

        $this->assertCount(3, $categories);
        $this->assertArrayHasKey('category_name', $categories[0]);
    }

    public function testGetTags(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $this->queueService->addTags($articleId, ['tag1', 'tag2']);

        $tags = $this->queueService->getTags($articleId);

        $this->assertCount(2, $tags);
        $this->assertArrayHasKey('tag_name', $tags[0]);
    }

    public function testRemoveCategory(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $this->queueService->addCategories($articleId, ['Cat 1', 'Cat 2']);
        $categories = $this->queueService->getCategories($articleId);

        $categoryDbId = $categories[0]['id'];
        $success = $this->queueService->removeCategory($articleId, $categoryDbId);

        $this->assertTrue($success);

        $remainingCategories = $this->queueService->getCategories($articleId);
        $this->assertCount(1, $remainingCategories);
    }

    public function testRemoveTag(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $this->queueService->addTags($articleId, ['tag1', 'tag2']);
        $tags = $this->queueService->getTags($articleId);

        $tagDbId = $tags[0]['id'];
        $success = $this->queueService->removeTag($articleId, $tagDbId);

        $this->assertTrue($success);

        $remainingTags = $this->queueService->getTags($articleId);
        $this->assertCount(1, $remainingTags);
    }

    // ========================================
    // Cascade Delete Tests
    // ========================================

    public function testCascadeDeleteCategoriesWhenArticleDeleted(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $this->queueService->addCategories($articleId, ['Cat 1', 'Cat 2']);

        // Delete article
        $this->queueService->deleteArticle($articleId);

        // Categories should be gone
        $categories = $this->queueService->getCategories($articleId);
        $this->assertCount(0, $categories);
    }

    public function testCascadeDeleteTagsWhenArticleDeleted(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData());

        $this->queueService->addTags($articleId, ['tag1', 'tag2']);

        // Delete article
        $this->queueService->deleteArticle($articleId);

        // Tags should be gone
        $tags = $this->queueService->getTags($articleId);
        $this->assertCount(0, $tags);
    }

    // ========================================
    // Edge Cases and Security Tests
    // ========================================

    public function testEmptyKeywordHandling(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData([
            'seed_keyword' => 'Valid Keyword',
            'target_audience' => '',
            'writing_style' => null
        ]));

        $article = $this->queueService->getArticle($articleId);
        $this->assertNotNull($article);
    }

    public function testSpecialCharactersInKeywords(): void
    {
        $articleId = $this->queueService->queueArticle($this->createTestArticleData([
            'seed_keyword' => 'Test "keyword" with <special> & characters',
            'target_audience' => 'Audience with\nnewlines'
        ]));

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('Test "keyword" with <special> & characters', $article['seed_keyword']);
    }

    public function testPaginationLimits(): void
    {
        // Create 10 articles
        for ($i = 1; $i <= 10; $i++) {
            $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => "Article $i"]));
        }

        $page1 = $this->queueService->listQueuedArticles(3, 0);
        $page2 = $this->queueService->listQueuedArticles(3, 3);
        $page3 = $this->queueService->listQueuedArticles(3, 6);

        $this->assertCount(3, $page1);
        $this->assertCount(3, $page2);
        $this->assertCount(3, $page3);
    }

    public function testConcurrentAccessSimulation(): void
    {
        // Queue multiple articles
        $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Article 1']));
        $this->queueService->queueArticle($this->createTestArticleData(['seed_keyword' => 'Article 2']));

        // Simulate two workers trying to get next article
        $article1 = $this->queueService->getNextQueuedArticle();
        $article2 = $this->queueService->getNextQueuedArticle();

        // Both should get different articles (FIFO)
        $this->assertNotEquals($article1['article_id'], $article2['article_id']);
    }
}
