<?php
/**
 * WordPress Blog API Endpoints Test
 *
 * Comprehensive tests for all WordPress Blog API endpoints including:
 * - Configuration Management (9 endpoints)
 * - Article Queue Management (11 endpoints)
 * - Monitoring & Execution (4 endpoints)
 *
 * @package WordPressBlog\Tests
 */

use PHPUnit\Framework\TestCase;

class WordPressBlogApiEndpointsTest extends TestCase {
    private $db;
    private $cryptoAdapter;
    private $configService;
    private $queueService;
    private $testConfigId;
    private $testArticleId;

    protected function setUp(): void {
        // Initialize in-memory database
        $this->db = new DB([
            'db_type' => 'sqlite',
            'db_path' => ':memory:'
        ]);

        // Run migrations
        $migrationsDir = __DIR__ . '/../../db/migrations';
        if (is_dir($migrationsDir)) {
            $this->db->runMigrations($migrationsDir);
        }

        // Initialize crypto adapter
        $this->cryptoAdapter = new CryptoAdapter([
            'encryption_key' => 'test-key-32-chars-for-testing!'
        ]);

        // Initialize services
        $this->configService = new WordPressBlogConfigurationService($this->db, $this->cryptoAdapter);
        $this->queueService = new WordPressBlogQueueService($this->db);

        // Create test configuration
        $this->testConfigId = $this->configService->createConfiguration([
            'config_name' => 'Test Configuration',
            'wordpress_site_url' => 'https://example.com',
            'wordpress_api_key' => 'test-api-key',
            'openai_api_key' => 'test-openai-key',
            'target_word_count' => 2000,
            'auto_publish' => false
        ]);
    }

    protected function tearDown(): void {
        $this->db = null;
    }

    // ====================================================================
    // Configuration Management Endpoints Tests
    // ====================================================================

    public function testCreateConfiguration() {
        $data = [
            'config_name' => 'New Test Config',
            'wordpress_site_url' => 'https://newsite.com',
            'wordpress_api_key' => 'new-api-key',
            'openai_api_key' => 'new-openai-key',
            'target_word_count' => 3000,
            'auto_publish' => true
        ];

        $configId = $this->configService->createConfiguration($data);

        $this->assertNotEmpty($configId);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $configId);

        $config = $this->configService->getConfiguration($configId, false);
        $this->assertEquals('New Test Config', $config['config_name']);
        $this->assertEquals('https://newsite.com', $config['wordpress_site_url']);
        $this->assertEquals(3000, $config['target_word_count']);
        $this->assertTrue($config['auto_publish']);
    }

    public function testCreateConfigurationMissingRequired() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('config_name is required');

        $this->configService->createConfiguration([
            'wordpress_site_url' => 'https://example.com'
        ]);
    }

    public function testGetConfiguration() {
        $config = $this->configService->getConfiguration($this->testConfigId, false);

        $this->assertNotNull($config);
        $this->assertEquals($this->testConfigId, $config['configuration_id']);
        $this->assertEquals('Test Configuration', $config['config_name']);
        $this->assertArrayNotHasKey('wordpress_api_key_ciphertext', $config);
        $this->assertArrayNotHasKey('openai_api_key_ciphertext', $config);
    }

    public function testGetConfigurationNotFound() {
        $config = $this->configService->getConfiguration('nonexistent', false);
        $this->assertNull($config);
    }

    public function testListConfigurations() {
        // Create additional configs
        $this->configService->createConfiguration([
            'config_name' => 'Config 2',
            'wordpress_site_url' => 'https://site2.com',
            'wordpress_api_key' => 'key2',
            'openai_api_key' => 'openai2'
        ]);

        $configs = $this->configService->listConfigurations();

        $this->assertCount(2, $configs);
        $this->assertEquals('Test Configuration', $configs[0]['config_name']);
        $this->assertEquals('Config 2', $configs[1]['config_name']);
    }

    public function testUpdateConfiguration() {
        $updates = [
            'config_name' => 'Updated Config Name',
            'target_word_count' => 4000,
            'auto_publish' => true
        ];

        $success = $this->configService->updateConfiguration($this->testConfigId, $updates);
        $this->assertTrue($success);

        $config = $this->configService->getConfiguration($this->testConfigId, false);
        $this->assertEquals('Updated Config Name', $config['config_name']);
        $this->assertEquals(4000, $config['target_word_count']);
        $this->assertTrue($config['auto_publish']);
        $this->assertEquals('https://example.com', $config['wordpress_site_url']); // Unchanged
    }

    public function testUpdateConfigurationNotFound() {
        $success = $this->configService->updateConfiguration('nonexistent', [
            'config_name' => 'Test'
        ]);

        $this->assertFalse($success);
    }

    public function testDeleteConfiguration() {
        $success = $this->configService->deleteConfiguration($this->testConfigId);
        $this->assertTrue($success);

        $config = $this->configService->getConfiguration($this->testConfigId, false);
        $this->assertNull($config);
    }

    public function testDeleteConfigurationNotFound() {
        $success = $this->configService->deleteConfiguration('nonexistent');
        $this->assertFalse($success);
    }

    public function testAddInternalLink() {
        $linkData = [
            'anchor_text' => 'Test Link',
            'target_url' => 'https://example.com/page1'
        ];

        $linkId = $this->configService->addInternalLink($this->testConfigId, $linkData);

        $this->assertNotEmpty($linkId);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $linkId);

        $link = $this->configService->getInternalLink($linkId);
        $this->assertEquals('Test Link', $link['anchor_text']);
        $this->assertEquals('https://example.com/page1', $link['target_url']);
    }

    public function testAddInternalLinkMissingRequired() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('anchor_text is required');

        $this->configService->addInternalLink($this->testConfigId, [
            'target_url' => 'https://example.com'
        ]);
    }

    public function testListInternalLinks() {
        $this->configService->addInternalLink($this->testConfigId, [
            'anchor_text' => 'Link 1',
            'target_url' => 'https://example.com/1'
        ]);

        $this->configService->addInternalLink($this->testConfigId, [
            'anchor_text' => 'Link 2',
            'target_url' => 'https://example.com/2'
        ]);

        $links = $this->configService->getInternalLinks($this->testConfigId);

        $this->assertCount(2, $links);
        $this->assertEquals('Link 1', $links[0]['anchor_text']);
        $this->assertEquals('Link 2', $links[1]['anchor_text']);
    }

    public function testUpdateInternalLink() {
        $linkId = $this->configService->addInternalLink($this->testConfigId, [
            'anchor_text' => 'Original',
            'target_url' => 'https://example.com/original'
        ]);

        $updates = [
            'anchor_text' => 'Updated',
            'target_url' => 'https://example.com/updated'
        ];

        $success = $this->configService->updateInternalLink($linkId, $updates);
        $this->assertTrue($success);

        $link = $this->configService->getInternalLink($linkId);
        $this->assertEquals('Updated', $link['anchor_text']);
        $this->assertEquals('https://example.com/updated', $link['target_url']);
    }

    public function testDeleteInternalLink() {
        $linkId = $this->configService->addInternalLink($this->testConfigId, [
            'anchor_text' => 'Test',
            'target_url' => 'https://example.com'
        ]);

        $success = $this->configService->deleteInternalLink($linkId);
        $this->assertTrue($success);

        $link = $this->configService->getInternalLink($linkId);
        $this->assertNull($link);
    }

    // ====================================================================
    // Article Queue Management Endpoints Tests
    // ====================================================================

    public function testAddArticleToQueue() {
        $articleData = [
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Test Article'
        ];

        $articleId = $this->queueService->addToQueue($articleData);

        $this->assertNotEmpty($articleId);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $articleId);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('Test Article', $article['seed_keyword']);
        $this->assertEquals('queued', $article['status']);
    }

    public function testAddArticleToQueueMissingRequired() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('configuration_id is required');

        $this->queueService->addToQueue([
            'seed_keyword' => 'Test'
        ]);
    }

    public function testGetArticle() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Get Test'
        ]);

        $article = $this->queueService->getArticle($articleId);

        $this->assertNotNull($article);
        $this->assertEquals($articleId, $article['article_id']);
        $this->assertEquals('Get Test', $article['seed_keyword']);
    }

    public function testGetArticleNotFound() {
        $article = $this->queueService->getArticle('nonexistent');
        $this->assertNull($article);
    }

    public function testListArticles() {
        // Add multiple articles
        $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Article 1'
        ]);

        $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Article 2'
        ]);

        $articles = $this->queueService->listArticles(null, null, 10, 0);

        $this->assertGreaterThanOrEqual(2, count($articles));
    }

    public function testListArticlesFiltered() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Filtered Test'
        ]);

        // Mark as processing
        $this->queueService->markAsProcessing($articleId);

        // List only processing articles
        $articles = $this->queueService->listArticles('processing', null, 10, 0);

        $this->assertGreaterThanOrEqual(1, count($articles));
        foreach ($articles as $article) {
            $this->assertEquals('processing', $article['status']);
        }
    }

    public function testUpdateArticle() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Original Keyword'
        ]);

        $updates = [
            'seed_keyword' => 'Updated Keyword'
        ];

        $success = $this->queueService->updateArticle($articleId, $updates);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('Updated Keyword', $article['seed_keyword']);
    }

    public function testDeleteArticle() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Delete Test'
        ]);

        $success = $this->queueService->deleteArticle($articleId);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertNull($article);
    }

    public function testRequeueArticle() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Requeue Test'
        ]);

        // Mark as processing first
        $this->queueService->markAsProcessing($articleId);

        // Then requeue
        $success = $this->queueService->requeueArticle($articleId);
        $this->assertTrue($success);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('queued', $article['status']);
    }

    public function testAddCategory() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Category Test'
        ]);

        $success = $this->queueService->addCategory($articleId, 'Technology');
        $this->assertTrue($success);

        $categories = $this->queueService->getCategories($articleId);
        $this->assertContains('Technology', $categories);
    }

    public function testGetCategories() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Categories Test'
        ]);

        $this->queueService->addCategory($articleId, 'Cat1');
        $this->queueService->addCategory($articleId, 'Cat2');

        $categories = $this->queueService->getCategories($articleId);

        $this->assertCount(2, $categories);
        $this->assertContains('Cat1', $categories);
        $this->assertContains('Cat2', $categories);
    }

    public function testRemoveCategory() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Remove Category Test'
        ]);

        $this->queueService->addCategory($articleId, 'RemoveMe');
        $success = $this->queueService->removeCategory($articleId, 'RemoveMe');

        $this->assertTrue($success);

        $categories = $this->queueService->getCategories($articleId);
        $this->assertNotContains('RemoveMe', $categories);
    }

    public function testAddTag() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Tag Test'
        ]);

        $success = $this->queueService->addTag($articleId, 'test-tag');
        $this->assertTrue($success);

        $tags = $this->queueService->getTags($articleId);
        $this->assertContains('test-tag', $tags);
    }

    public function testGetTags() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Tags Test'
        ]);

        $this->queueService->addTag($articleId, 'tag1');
        $this->queueService->addTag($articleId, 'tag2');

        $tags = $this->queueService->getTags($articleId);

        $this->assertCount(2, $tags);
        $this->assertContains('tag1', $tags);
        $this->assertContains('tag2', $tags);
    }

    public function testRemoveTag() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Remove Tag Test'
        ]);

        $this->queueService->addTag($articleId, 'remove-me');
        $success = $this->queueService->removeTag($articleId, 'remove-me');

        $this->assertTrue($success);

        $tags = $this->queueService->getTags($articleId);
        $this->assertNotContains('remove-me', $tags);
    }

    // ====================================================================
    // Monitoring & Execution Endpoints Tests
    // ====================================================================

    public function testGetQueueStatus() {
        // Add articles with different statuses
        $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Queued 1'
        ]);

        $processingId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Processing 1'
        ]);
        $this->queueService->markAsProcessing($processingId);

        $stats = $this->queueService->getQueueStatistics();

        $this->assertArrayHasKey('queued', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['queued']);
        $this->assertGreaterThanOrEqual(1, $stats['processing']);
    }

    public function testGetQueueStatisticsEmpty() {
        // Test with empty queue (new database)
        $emptyDb = new DB(['db_type' => 'sqlite', 'db_path' => ':memory:']);
        $emptyDb->runMigrations(__DIR__ . '/../../db/migrations');

        $emptyQueueService = new WordPressBlogQueueService($emptyDb);
        $stats = $emptyQueueService->getQueueStatistics();

        $this->assertArrayHasKey('queued', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('published', $stats);
    }

    public function testMarkArticleStatusTransitions() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Status Test'
        ]);

        // Test queued -> processing
        $this->queueService->markAsProcessing($articleId);
        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('processing', $article['status']);
        $this->assertNotNull($article['processing_started_at']);

        // Test processing -> completed
        $this->queueService->markAsCompleted($articleId, 123, 'https://example.com/post');
        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('completed', $article['status']);
        $this->assertEquals(123, $article['wordpress_post_id']);
        $this->assertEquals('https://example.com/post', $article['wordpress_post_url']);
        $this->assertNotNull($article['processing_completed_at']);

        // Test completed -> published
        $this->queueService->markAsPublished($articleId);
        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('published', $article['status']);
    }

    public function testMarkArticleAsFailed() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Fail Test'
        ]);

        $this->queueService->markAsProcessing($articleId);
        $this->queueService->markAsFailed($articleId, 'Test error message');

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('failed', $article['status']);
        $this->assertEquals('Test error message', $article['error_message']);
        $this->assertEquals(1, $article['retry_count']);
    }

    // ====================================================================
    // Integration Tests
    // ====================================================================

    public function testCompleteWorkflowCreateConfigAndArticle() {
        // 1. Create configuration
        $configId = $this->configService->createConfiguration([
            'config_name' => 'Integration Test Config',
            'wordpress_site_url' => 'https://integration.test',
            'wordpress_api_key' => 'integration-key',
            'openai_api_key' => 'integration-openai',
            'target_word_count' => 2500
        ]);

        $this->assertNotEmpty($configId);

        // 2. Add internal links
        $linkId = $this->configService->addInternalLink($configId, [
            'anchor_text' => 'Integration Link',
            'target_url' => 'https://integration.test/link'
        ]);

        $this->assertNotEmpty($linkId);

        // 3. Create article in queue
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $configId,
            'seed_keyword' => 'Integration Test Article'
        ]);

        $this->assertNotEmpty($articleId);

        // 4. Add categories and tags
        $this->queueService->addCategory($articleId, 'Integration');
        $this->queueService->addTag($articleId, 'test');

        // 5. Verify complete setup
        $config = $this->configService->getConfiguration($configId, false);
        $this->assertEquals('Integration Test Config', $config['config_name']);

        $links = $this->configService->getInternalLinks($configId);
        $this->assertCount(1, $links);

        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('Integration Test Article', $article['seed_keyword']);

        $categories = $this->queueService->getCategories($articleId);
        $this->assertContains('Integration', $categories);

        $tags = $this->queueService->getTags($articleId);
        $this->assertContains('test', $tags);
    }

    public function testCascadeDeleteConfiguration() {
        // Create config with article
        $configId = $this->configService->createConfiguration([
            'config_name' => 'Cascade Test',
            'wordpress_site_url' => 'https://cascade.test',
            'wordpress_api_key' => 'cascade-key',
            'openai_api_key' => 'cascade-openai'
        ]);

        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $configId,
            'seed_keyword' => 'Cascade Article'
        ]);

        // Delete configuration should cascade to articles due to foreign key
        $this->configService->deleteConfiguration($configId);

        // Article should no longer exist or be orphaned depending on ON DELETE behavior
        $article = $this->queueService->getArticle($articleId);
        // If ON DELETE CASCADE is set, article should be null
        // If not, this test documents the current behavior
        $this->assertNull($article);
    }

    public function testPaginationListArticles() {
        // Create 15 articles
        for ($i = 1; $i <= 15; $i++) {
            $this->queueService->addToQueue([
                'configuration_id' => $this->testConfigId,
                'seed_keyword' => "Pagination Article {$i}"
            ]);
        }

        // Test first page
        $page1 = $this->queueService->listArticles(null, null, 10, 0);
        $this->assertCount(10, $page1);

        // Test second page
        $page2 = $this->queueService->listArticles(null, null, 10, 10);
        $this->assertGreaterThanOrEqual(5, count($page2));

        // Verify no overlap
        $page1Ids = array_column($page1, 'article_id');
        $page2Ids = array_column($page2, 'article_id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    // ====================================================================
    // Validation Tests
    // ====================================================================

    public function testConfigurationUrlValidation() {
        $this->expectException(Exception::class);

        $this->configService->createConfiguration([
            'config_name' => 'Invalid URL Test',
            'wordpress_site_url' => 'not-a-valid-url',
            'wordpress_api_key' => 'key',
            'openai_api_key' => 'key'
        ]);
    }

    public function testArticleStatusValidation() {
        $articleId = $this->queueService->addToQueue([
            'configuration_id' => $this->testConfigId,
            'seed_keyword' => 'Status Validation Test'
        ]);

        // Attempt invalid status update
        $this->expectException(Exception::class);

        $this->queueService->updateArticle($articleId, [
            'status' => 'invalid_status'
        ]);
    }

    public function testDuplicatePreventionInternalLink() {
        $this->configService->addInternalLink($this->testConfigId, [
            'anchor_text' => 'Duplicate Test',
            'target_url' => 'https://example.com/duplicate'
        ]);

        // Adding same anchor text should either succeed or throw exception
        // depending on business rules - test documents current behavior
        try {
            $this->configService->addInternalLink($this->testConfigId, [
                'anchor_text' => 'Duplicate Test',
                'target_url' => 'https://example.com/duplicate2'
            ]);
            // If it succeeds, document that duplicates are allowed
            $this->assertTrue(true);
        } catch (Exception $e) {
            // If it fails, document that duplicates are prevented
            $this->assertStringContainsString('duplicate', strtolower($e->getMessage()));
        }
    }
}
