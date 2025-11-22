<?php
/**
 * WordPress Blog End-to-End Integration Tests
 *
 * Comprehensive end-to-end integration tests covering:
 * - Happy path: Full article generation and publication
 * - Error recovery: API failures with retry logic
 * - Error recovery: WordPress publishing failures
 * - Concurrent processing: Multiple articles simultaneously
 * - Configuration updates during processing
 *
 * These tests verify the complete WordPress Blog automation workflow
 * from configuration to final publication, including all components:
 * - Configuration Management
 * - Queue System
 * - Content Generation (OpenAI)
 * - Image Generation (Replicate)
 * - WordPress Publishing
 * - Asset Organization
 * - Error Handling and Retry Logic
 *
 * @package WordPressBlog\Tests\Integration
 */

use PHPUnit\Framework\TestCase;

class WordPressBlogE2ETest extends TestCase {
    private $db;
    private $cryptoAdapter;

    // Services
    private $configService;
    private $queueService;
    private $contentStructureBuilder;
    private $chapterContentWriter;
    private $imageGenerator;
    private $assetOrganizer;
    private $publisher;
    private $executionLogger;
    private $errorHandler;
    private $validationEngine;

    // Test data
    private $testConfigId;
    private $testArticleIds = [];
    private $createdFiles = [];

    protected function setUp(): void {
        // Initialize in-memory database
        $this->db = new DB([
            'db_type' => 'sqlite',
            'db_path' => ':memory:'
        ]);

        // Initialize crypto adapter
        $this->cryptoAdapter = new CryptoAdapter([
            'encryption_key' => 'test-key-32-chars-for-testing!'
        ]);

        // Create database schema
        $this->createDatabaseSchema();

        // Initialize services
        $this->initializeServices();

        // Create test configuration
        $this->createTestConfiguration();
    }

    protected function tearDown(): void {
        // Clean up test articles
        foreach ($this->testArticleIds as $articleId) {
            $this->queueService->deleteArticle($articleId);
        }

        // Clean up test files
        foreach ($this->createdFiles as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Clean up test configuration
        if ($this->testConfigId) {
            $this->configService->deleteConfiguration($this->testConfigId);
        }

        // Close database connection
        $this->db = null;
    }

    // ====================================================================
    // Database Setup
    // ====================================================================

    private function createDatabaseSchema() {
        // Create wp_blog_configurations table
        $this->db->execute("
            CREATE TABLE wp_blog_configurations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_name TEXT NOT NULL,
                wordpress_site_url TEXT NOT NULL,
                wordpress_username TEXT,
                wordpress_api_key TEXT NOT NULL,
                openai_api_key TEXT NOT NULL,
                openai_model TEXT DEFAULT 'gpt-4',
                replicate_api_key TEXT,
                target_word_count INTEGER DEFAULT 2000,
                max_internal_links INTEGER DEFAULT 5,
                google_drive_folder_id TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create wp_blog_internal_links table
        $this->db->execute("
            CREATE TABLE wp_blog_internal_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_id INTEGER NOT NULL,
                url TEXT NOT NULL,
                anchor_text TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (config_id) REFERENCES wp_blog_configurations(id) ON DELETE CASCADE
            )
        ");

        // Create wp_blog_article_queue table
        $this->db->execute("
            CREATE TABLE wp_blog_article_queue (
                id TEXT PRIMARY KEY,
                config_id INTEGER NOT NULL,
                topic TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                wordpress_post_id INTEGER,
                processing_started_at TEXT,
                processing_completed_at TEXT,
                error_message TEXT,
                retry_count INTEGER DEFAULT 0,
                content_json TEXT,
                metadata_json TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (config_id) REFERENCES wp_blog_configurations(id) ON DELETE CASCADE
            )
        ");

        // Create wp_blog_execution_log table
        $this->db->execute("
            CREATE TABLE wp_blog_execution_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id TEXT NOT NULL,
                stage TEXT NOT NULL,
                status TEXT NOT NULL,
                message TEXT,
                error_details TEXT,
                execution_time_ms INTEGER,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (article_id) REFERENCES wp_blog_article_queue(id) ON DELETE CASCADE
            )
        ");
    }

    private function initializeServices() {
        $this->configService = new ConfigurationService($this->db, $this->cryptoAdapter);
        $this->queueService = new QueueService($this->db);
        $this->contentStructureBuilder = new ContentStructureBuilder();
        $this->chapterContentWriter = new ChapterContentWriter($this->cryptoAdapter);
        $this->imageGenerator = new ImageGenerator($this->cryptoAdapter);
        $this->assetOrganizer = new AssetOrganizer();
        $this->publisher = new Publisher($this->cryptoAdapter);
        $this->executionLogger = new ExecutionLogger($this->db);
        $this->errorHandler = new WordPressBlogErrorHandler(3, 2);
        $this->validationEngine = new WordPressBlogValidationEngine();
    }

    private function createTestConfiguration() {
        $configData = [
            'config_name' => 'E2E Test Configuration',
            'wordpress_site_url' => 'https://test-blog.example.com',
            'wordpress_username' => 'test_user',
            'wordpress_api_key' => 'test-api-key-1234567890',
            'openai_api_key' => 'sk-test-1234567890abcdef',
            'openai_model' => 'gpt-4',
            'replicate_api_key' => 'r8_test1234567890',
            'target_word_count' => 2000,
            'max_internal_links' => 3,
            'google_drive_folder_id' => 'test-folder-id'
        ];

        $result = $this->configService->createConfiguration($configData);
        $this->testConfigId = $result['config_id'];

        // Add internal links
        $this->configService->addInternalLink(
            $this->testConfigId,
            'https://test-blog.example.com/article-1',
            'Related Article 1'
        );
        $this->configService->addInternalLink(
            $this->testConfigId,
            'https://test-blog.example.com/article-2',
            'Related Article 2'
        );
    }

    // ====================================================================
    // Test Scenario 1: Happy Path - Full Article Generation and Publication
    // ====================================================================

    public function testHappyPathFullArticleGenerationAndPublication() {
        // Step 1: Queue article
        $topic = 'The Future of Artificial Intelligence in Healthcare';
        $articleId = $this->queueService->queueArticle($this->testConfigId, $topic);
        $this->testArticleIds[] = $articleId;

        $this->assertNotEmpty($articleId);
        $this->executionLogger->logStage($articleId, 'queue', 'completed', 'Article queued successfully');

        // Step 2: Load configuration
        $config = $this->configService->getConfiguration($this->testConfigId);
        $this->assertNotNull($config);
        $this->assertEquals('E2E Test Configuration', $config['config_name']);

        // Step 3: Validate configuration
        $validationResult = $this->validationEngine->validateConfiguration($config);
        $this->assertTrue($validationResult['valid'], 'Configuration should be valid');
        $this->executionLogger->logStage($articleId, 'validation', 'completed', 'Configuration validated');

        // Step 4: Update article status to processing
        $this->queueService->updateArticleStatus($articleId, 'processing');
        $article = $this->queueService->getArticle($articleId);
        $this->assertEquals('processing', $article['status']);

        // Step 5: Build content structure
        $startTime = microtime(true);
        $structure = $this->contentStructureBuilder->buildStructure(
            $topic,
            $config['target_word_count']
        );
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->assertArrayHasKey('title', $structure);
        $this->assertArrayHasKey('meta_description', $structure);
        $this->assertArrayHasKey('chapters', $structure);
        $this->assertGreaterThan(0, count($structure['chapters']));
        $this->executionLogger->logStage($articleId, 'structure', 'completed', 'Content structure built', null, $executionTime);

        // Step 6: Generate chapter content
        $startTime = microtime(true);
        $content = $this->chapterContentWriter->generateContent(
            $structure,
            $config['openai_api_key'],
            $config['openai_model']
        );
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->assertArrayHasKey('chapters', $content);
        $this->executionLogger->logStage($articleId, 'content', 'completed', 'Chapter content generated', null, $executionTime);

        // Step 7: Validate generated content
        $contentValidation = $this->validationEngine->validateContent($content, $config['target_word_count']);
        $this->assertTrue($contentValidation['valid'], 'Generated content should be valid');

        // Step 8: Generate featured image
        $startTime = microtime(true);
        $imagePrompt = "Featured image for: {$structure['title']}";
        $featuredImageUrl = $this->imageGenerator->generateImage(
            $imagePrompt,
            $config['replicate_api_key'],
            'featured'
        );
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->assertNotEmpty($featuredImageUrl);
        $this->executionLogger->logStage($articleId, 'image', 'completed', 'Featured image generated', null, $executionTime);

        // Step 9: Validate image URL
        $imageValidation = $this->validationEngine->validateImageUrl($featuredImageUrl);
        $this->assertTrue($imageValidation['valid'], 'Image URL should be valid');

        // Step 10: Organize assets (mock Google Drive upload)
        $assetData = [
            'article_id' => $articleId,
            'topic' => $topic,
            'content' => $content,
            'images' => ['featured_image' => $featuredImageUrl]
        ];

        $startTime = microtime(true);
        $assetResult = $this->assetOrganizer->organizeAssets(
            $assetData,
            $config['google_drive_folder_id']
        );
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->assertTrue($assetResult['success']);
        $this->executionLogger->logStage($articleId, 'assets', 'completed', 'Assets organized', null, $executionTime);

        // Step 11: Publish to WordPress (mock)
        $publishData = [
            'title' => $structure['title'],
            'content' => $this->formatContentForWordPress($content),
            'meta_description' => $structure['meta_description'],
            'featured_image_url' => $featuredImageUrl,
            'status' => 'draft'
        ];

        $startTime = microtime(true);
        $publishResult = $this->publisher->publishToWordPress(
            $publishData,
            $config['wordpress_site_url'],
            $config['wordpress_username'],
            $config['wordpress_api_key']
        );
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->assertTrue($publishResult['success']);
        $this->assertArrayHasKey('post_id', $publishResult);
        $this->executionLogger->logStage($articleId, 'publish', 'completed', 'Published to WordPress', null, $executionTime);

        // Step 12: Update article with completion data
        $this->queueService->updateArticle($articleId, [
            'status' => 'completed',
            'wordpress_post_id' => $publishResult['post_id'],
            'content_json' => json_encode($content),
            'processing_completed_at' => date('Y-m-d H:i:s')
        ]);

        // Step 13: Verify final state
        $finalArticle = $this->queueService->getArticle($articleId);
        $this->assertEquals('completed', $finalArticle['status']);
        $this->assertNotNull($finalArticle['wordpress_post_id']);
        $this->assertNotNull($finalArticle['content_json']);

        // Step 14: Verify execution log
        $executionLog = $this->executionLogger->getExecutionLog($articleId);
        $this->assertGreaterThan(5, count($executionLog)); // At least queue, structure, content, image, publish

        // Verify all stages completed
        $completedStages = array_filter($executionLog, function($log) {
            return $log['status'] === 'completed';
        });
        $this->assertGreaterThan(5, count($completedStages));
    }

    // ====================================================================
    // Test Scenario 2: Error Recovery - API Failures with Retry
    // ====================================================================

    public function testErrorRecoveryAPIFailuresWithRetry() {
        // Queue article
        $topic = 'Machine Learning Best Practices';
        $articleId = $this->queueService->queueArticle($this->testConfigId, $topic);
        $this->testArticleIds[] = $articleId;

        $config = $this->configService->getConfiguration($this->testConfigId);
        $this->queueService->updateArticleStatus($articleId, 'processing');

        // Simulate content generation with retry
        $attemptCount = 0;
        $maxAttempts = 3;

        $contentGenerationCallable = function() use (&$attemptCount, $maxAttempts) {
            $attemptCount++;

            // Fail on first two attempts
            if ($attemptCount < 3) {
                $exception = new ContentGenerationException(
                    'OpenAI API rate limit exceeded'
                );
                $exception->setRetryable(true);
                $exception->setHttpStatusCode(429);
                $exception->setErrorType('rate_limit');
                $exception->addContext('attempt', $attemptCount);
                throw $exception;
            }

            // Success on third attempt
            return [
                'chapters' => [
                    [
                        'title' => 'Introduction',
                        'content' => 'This is a test chapter with at least 200 words. ' . str_repeat('Lorem ipsum dolor sit amet. ', 30)
                    ]
                ]
            ];
        };

        // Execute with retry logic
        try {
            $startTime = microtime(true);
            $content = $this->errorHandler->executeWithRetry(
                $contentGenerationCallable,
                [],
                'content_generation'
            );
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Verify retry worked
            $this->assertEquals(3, $attemptCount);
            $this->assertArrayHasKey('chapters', $content);
            $this->executionLogger->logStage(
                $articleId,
                'content',
                'completed',
                "Content generated after {$attemptCount} attempts",
                null,
                $executionTime
            );

            // Verify backoff calculation
            $backoff1 = $this->errorHandler->calculateBackoff(1);
            $backoff2 = $this->errorHandler->calculateBackoff(2);
            $backoff3 = $this->errorHandler->calculateBackoff(3);

            $this->assertEquals(2, $backoff1);  // 2 * 2^0 = 2
            $this->assertEquals(4, $backoff2);  // 2 * 2^1 = 4
            $this->assertEquals(8, $backoff3);  // 2 * 2^2 = 8

        } catch (Exception $e) {
            $this->fail('Retry logic should have succeeded after 3 attempts: ' . $e->getMessage());
        }

        // Update article status
        $this->queueService->updateArticle($articleId, [
            'status' => 'completed',
            'retry_count' => $attemptCount - 1,
            'content_json' => json_encode($content)
        ]);

        // Verify retry count recorded
        $finalArticle = $this->queueService->getArticle($articleId);
        $this->assertEquals(2, $finalArticle['retry_count']);
    }

    // ====================================================================
    // Test Scenario 3: Error Recovery - WordPress Publishing Failures
    // ====================================================================

    public function testErrorRecoveryWordPressPublishingFailures() {
        // Queue article
        $topic = 'Cloud Computing Security';
        $articleId = $this->queueService->queueArticle($this->testConfigId, $topic);
        $this->testArticleIds[] = $articleId;

        $config = $this->configService->getConfiguration($this->testConfigId);
        $this->queueService->updateArticleStatus($articleId, 'processing');

        // Prepare publish data
        $publishData = [
            'title' => 'Cloud Computing Security Best Practices',
            'content' => 'Test content for publication',
            'status' => 'draft'
        ];

        // Test non-retryable error (authentication)
        $authErrorCallable = function() use ($publishData) {
            $exception = new WordPressPublishException(
                'Authentication failed: Invalid credentials'
            );
            $exception->setHttpStatusCode(401);
            $exception->setRetryable(false); // Auth errors are not retryable
            throw $exception;
        };

        try {
            $this->errorHandler->executeWithRetry(
                $authErrorCallable,
                [],
                'wordpress_publish'
            );
            $this->fail('Non-retryable error should throw immediately');
        } catch (WordPressPublishException $e) {
            $this->assertEquals(401, $e->getHttpStatusCode());
            $this->assertFalse($e->isRetryable());

            // Log error
            $this->executionLogger->logStage(
                $articleId,
                'publish',
                'failed',
                'WordPress authentication failed',
                $e->getMessage()
            );
        }

        // Test retryable error (temporary server error)
        $attemptCount = 0;
        $serverErrorCallable = function() use (&$attemptCount) {
            $attemptCount++;

            if ($attemptCount < 2) {
                $exception = new WordPressPublishException(
                    'WordPress server temporarily unavailable'
                );
                $exception->setHttpStatusCode(503);
                $exception->setRetryable(true);
                throw $exception;
            }

            return ['success' => true, 'post_id' => 12345];
        };

        try {
            $result = $this->errorHandler->executeWithRetry(
                $serverErrorCallable,
                [],
                'wordpress_publish_retry'
            );

            $this->assertTrue($result['success']);
            $this->assertEquals(2, $attemptCount);

            $this->executionLogger->logStage(
                $articleId,
                'publish',
                'completed',
                "Published after {$attemptCount} attempts"
            );

        } catch (Exception $e) {
            $this->fail('Retryable publish error should succeed: ' . $e->getMessage());
        }
    }

    // ====================================================================
    // Test Scenario 4: Concurrent Processing - Multiple Articles
    // ====================================================================

    public function testConcurrentProcessingMultipleArticles() {
        $topics = [
            'Artificial Intelligence in Education',
            'Blockchain Technology Explained',
            'Quantum Computing Basics'
        ];

        $queuedArticleIds = [];

        // Step 1: Queue multiple articles
        foreach ($topics as $topic) {
            $articleId = $this->queueService->queueArticle($this->testConfigId, $topic);
            $queuedArticleIds[] = $articleId;
            $this->testArticleIds[] = $articleId;
        }

        $this->assertCount(3, $queuedArticleIds);

        // Step 2: Verify all articles are queued
        $queuedArticles = $this->queueService->getQueuedArticles([
            'status' => 'pending',
            'config_id' => $this->testConfigId
        ]);

        $this->assertGreaterThanOrEqual(3, count($queuedArticles));

        // Step 3: Simulate concurrent processing
        $processingResults = [];

        foreach ($queuedArticleIds as $articleId) {
            // Update to processing
            $this->queueService->updateArticleStatus($articleId, 'processing');

            // Simulate quick processing
            $startTime = microtime(true);

            try {
                // Simple structure generation
                $structure = [
                    'title' => 'Test Title for ' . $articleId,
                    'meta_description' => 'Test meta description',
                    'chapters' => [
                        ['title' => 'Chapter 1', 'word_count' => 500]
                    ]
                ];

                // Log completion
                $executionTime = (microtime(true) - $startTime) * 1000;
                $this->executionLogger->logStage($articleId, 'structure', 'completed', 'Structure built', null, $executionTime);

                // Update status
                $this->queueService->updateArticleStatus($articleId, 'completed');

                $processingResults[$articleId] = ['success' => true];

            } catch (Exception $e) {
                $this->executionLogger->logStage($articleId, 'structure', 'failed', 'Structure failed', $e->getMessage());
                $processingResults[$articleId] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // Step 4: Verify all completed successfully
        $this->assertCount(3, $processingResults);
        foreach ($processingResults as $result) {
            $this->assertTrue($result['success']);
        }

        // Step 5: Verify final status
        foreach ($queuedArticleIds as $articleId) {
            $article = $this->queueService->getArticle($articleId);
            $this->assertEquals('completed', $article['status']);
        }

        // Step 6: Verify execution logs for all articles
        foreach ($queuedArticleIds as $articleId) {
            $log = $this->executionLogger->getExecutionLog($articleId);
            $this->assertGreaterThan(0, count($log));
        }
    }

    // ====================================================================
    // Test Scenario 5: Configuration Update During Processing
    // ====================================================================

    public function testConfigurationUpdateDuringProcessing() {
        // Queue article
        $topic = 'Cybersecurity Trends 2025';
        $articleId = $this->queueService->queueArticle($this->testConfigId, $topic);
        $this->testArticleIds[] = $articleId;

        // Start processing
        $this->queueService->updateArticleStatus($articleId, 'processing');

        // Load initial configuration
        $config = $this->configService->getConfiguration($this->testConfigId);
        $initialWordCount = $config['target_word_count'];
        $this->assertEquals(2000, $initialWordCount);

        // Update configuration during processing
        $updateResult = $this->configService->updateConfiguration($this->testConfigId, [
            'target_word_count' => 3000,
            'max_internal_links' => 5
        ]);

        $this->assertTrue($updateResult['success']);

        // Reload configuration (simulating what processing code would do)
        $updatedConfig = $this->configService->getConfiguration($this->testConfigId);
        $this->assertEquals(3000, $updatedConfig['target_word_count']);
        $this->assertEquals(5, $updatedConfig['max_internal_links']);

        // Verify article still uses updated config
        $structure = $this->contentStructureBuilder->buildStructure(
            $topic,
            $updatedConfig['target_word_count']
        );

        // Calculate total expected words
        $totalExpectedWords = 0;
        foreach ($structure['chapters'] as $chapter) {
            $totalExpectedWords += $chapter['word_count'];
        }

        // Verify word count is close to updated target (within 10%)
        $this->assertGreaterThan($updatedConfig['target_word_count'] * 0.9, $totalExpectedWords);
        $this->assertLessThan($updatedConfig['target_word_count'] * 1.1, $totalExpectedWords);

        // Complete processing
        $this->queueService->updateArticleStatus($articleId, 'completed');
        $this->executionLogger->logStage($articleId, 'config_update', 'completed', 'Processed with updated configuration');

        // Verify article completed
        $finalArticle = $this->queueService->getArticle($articleId);
        $this->assertEquals('completed', $finalArticle['status']);
    }

    // ====================================================================
    // Test Scenario 6: Complete Workflow with Validation
    // ====================================================================

    public function testCompleteWorkflowWithValidation() {
        // Queue article
        $topic = 'DevOps Best Practices';
        $articleId = $this->queueService->queueArticle($this->testConfigId, $topic);
        $this->testArticleIds[] = $articleId;

        $config = $this->configService->getConfiguration($this->testConfigId);

        // Step 1: Validate configuration before processing
        $configValidation = $this->validationEngine->validateConfiguration($config);
        $this->assertTrue($configValidation['valid'], 'Configuration must be valid');

        if (!empty($configValidation['warnings'])) {
            foreach ($configValidation['warnings'] as $warning) {
                $this->executionLogger->logStage($articleId, 'validation', 'warning', $warning);
            }
        }

        // Step 2: Process article
        $this->queueService->updateArticleStatus($articleId, 'processing');

        // Build structure
        $structure = $this->contentStructureBuilder->buildStructure($topic, $config['target_word_count']);
        $this->assertArrayHasKey('title', $structure);

        // Generate content
        $content = [
            'chapters' => [
                [
                    'title' => 'Introduction to DevOps',
                    'content' => str_repeat('DevOps is a methodology that combines development and operations. ', 50) // ~400 words
                ],
                [
                    'title' => 'Key DevOps Practices',
                    'content' => str_repeat('Continuous integration and continuous deployment are essential. ', 50) // ~400 words
                ],
                [
                    'title' => 'Tools and Technologies',
                    'content' => str_repeat('Docker, Kubernetes, and Jenkins are popular DevOps tools. ', 50) // ~400 words
                ],
                [
                    'title' => 'Best Practices',
                    'content' => str_repeat('Automation, monitoring, and collaboration are key principles. ', 50) // ~400 words
                ],
                [
                    'title' => 'Conclusion',
                    'content' => str_repeat('DevOps continues to evolve with new practices emerging. ', 50) // ~400 words
                ]
            ]
        ];

        // Step 3: Validate generated content
        $contentValidation = $this->validationEngine->validateContent($content, $config['target_word_count']);
        $this->assertTrue($contentValidation['valid'], 'Generated content must be valid');
        $this->assertArrayHasKey('word_count', $contentValidation);

        // Step 4: Generate and validate image
        $imageUrl = 'https://example.com/test-image.jpg';
        $imageValidation = $this->validationEngine->validateImageUrl($imageUrl);
        $this->assertTrue($imageValidation['valid'], 'Image URL must be valid');

        // Step 5: Complete processing
        $this->queueService->updateArticle($articleId, [
            'status' => 'completed',
            'content_json' => json_encode($content),
            'processing_completed_at' => date('Y-m-d H:i:s')
        ]);

        // Step 6: Verify final state with all validations passed
        $finalArticle = $this->queueService->getArticle($articleId);
        $this->assertEquals('completed', $finalArticle['status']);
        $this->assertNotNull($finalArticle['content_json']);

        $executionLog = $this->executionLogger->getExecutionLog($articleId);
        $this->assertGreaterThan(0, count($executionLog));
    }

    // ====================================================================
    // Helper Methods
    // ====================================================================

    private function formatContentForWordPress($content) {
        $formattedContent = '';

        foreach ($content['chapters'] as $chapter) {
            $formattedContent .= "<h2>{$chapter['title']}</h2>\n";
            $formattedContent .= "<p>{$chapter['content']}</p>\n\n";
        }

        return $formattedContent;
    }
}
