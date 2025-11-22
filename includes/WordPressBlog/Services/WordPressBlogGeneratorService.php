<?php
/**
 * WordPress Blog Generator Service
 *
 * Main orchestrator service that coordinates all services through a 6-phase
 * processing pipeline. This is the core workflow engine for blog article generation.
 *
 * 6-Phase Pipeline:
 * 1. Retrieve & Validate Configuration
 * 2. Generate Article Structure
 * 3. Generate Content (chapters, intro, conclusion)
 * 4. Generate Images (featured + chapters)
 * 5. Organize Assets (upload to Google Drive)
 * 6. Publish to WordPress
 *
 * @package WordPressBlog\Services
 */

// Required dependencies
require_once __DIR__ . '/WordPressBlogConfigurationService.php';
require_once __DIR__ . '/WordPressBlogQueueService.php';
require_once __DIR__ . '/WordPressBlogExecutionLogger.php';
require_once __DIR__ . '/WordPressContentStructureBuilder.php';
require_once __DIR__ . '/WordPressChapterContentWriter.php';
require_once __DIR__ . '/WordPressImageGenerator.php';
require_once __DIR__ . '/WordPressAssetOrganizer.php';
require_once __DIR__ . '/WordPressPublisher.php';

class WordPressBlogGeneratorService {
    private $db;
    private $cryptoAdapter;
    private $openAIClient;
    private $configService;
    private $queueService;
    private $logger;

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

        // Initialize services
        $this->configService = new WordPressBlogConfigurationService($db, $cryptoAdapter);
        $this->queueService = new WordPressBlogQueueService($db);
    }

    /**
     * Generate complete blog article
     *
     * Orchestrates all 6 phases of the generation pipeline.
     *
     * @param string $articleId Article ID from queue
     * @return array Generation result with all metadata
     * @throws Exception If generation fails at any phase
     */
    public function generateArticle($articleId) {
        // Initialize execution logger
        $this->logger = new WordPressBlogExecutionLogger($articleId);

        try {
            // Mark article as processing
            $this->queueService->markAsProcessing($articleId);

            // Phase 1: Retrieve & Validate Configuration
            $this->logger->startPhase('phase1_configuration');
            $article = $this->queueService->getArticle($articleId);
            $config = $this->configService->getConfiguration($article['configuration_id'], true);

            if (!$config) {
                throw new Exception("Configuration not found: {$article['configuration_id']}");
            }

            $this->logger->completePhase('phase1_configuration', [
                'config_id' => $config['configuration_id'],
                'config_name' => $config['config_name']
            ]);

            // Phase 2: Generate Article Structure
            $this->logger->startPhase('phase2_structure');
            $structure = $this->generateStructure($config, $article);
            $this->logger->completePhase('phase2_structure', [
                'title' => $structure['metadata']['title'],
                'chapters' => count($structure['chapters'])
            ]);

            // Phase 3: Generate Content
            $this->logger->startPhase('phase3_content');
            $content = $this->generateContent($config, $structure);
            $this->logger->completePhase('phase3_content', [
                'total_words' => $this->countTotalWords($content)
            ]);

            // Phase 4: Generate Images
            $this->logger->startPhase('phase4_images');
            $images = $this->generateImages($config, $structure);
            $this->logger->completePhase('phase4_images', [
                'total_images' => count($images['chapter_images']) + 1,
                'total_cost' => $images['total_cost']
            ]);

            // Phase 5: Organize Assets
            $this->logger->startPhase('phase5_assets');
            $assets = $this->organizeAssets($config, $structure, $content, $images);
            $this->logger->completePhase('phase5_assets', [
                'folder_url' => $assets['folder_url'],
                'total_files' => $assets['manifest']['statistics']['total_files']
            ]);

            // Phase 6: Publish to WordPress
            $this->logger->startPhase('phase6_publish');
            $publication = $this->publishToWordPress($config, $structure, $content, $images, $assets, $article);
            $this->logger->completePhase('phase6_publish', [
                'post_id' => $publication['post_id'],
                'post_url' => $publication['post_url']
            ]);

            // Mark article as completed
            $this->queueService->markAsCompleted(
                $articleId,
                $publication['post_id'],
                $publication['post_url']
            );

            // Generate final result
            $result = [
                'success' => true,
                'article_id' => $articleId,
                'post_id' => $publication['post_id'],
                'post_url' => $publication['post_url'],
                'structure' => $structure['metadata'],
                'assets' => $assets,
                'execution_summary' => $this->logger->generateSummary(),
                'audit_trail_path' => $this->saveAuditTrail($articleId)
            ];

            return $result;

        } catch (Exception $e) {
            // Log the failure
            if (isset($this->logger)) {
                $currentPhase = $this->getCurrentPhase();
                if ($currentPhase) {
                    $this->logger->failPhase($currentPhase, $e->getMessage(), $e);
                }
            }

            // Mark article as failed
            $this->queueService->markAsFailed($articleId, $e->getMessage());

            // Save audit trail even for failures
            if (isset($this->logger)) {
                $this->saveAuditTrail($articleId);
            }

            throw $e;
        }
    }

    /**
     * Phase 2: Generate Article Structure
     *
     * @param array $config Configuration
     * @param array $article Article data
     * @return array Article structure
     */
    private function generateStructure(array $config, array $article) {
        $structureBuilder = new WordPressContentStructureBuilder(
            $this->openAIClient,
            $this->configService,
            $this->db
        );

        $structure = $structureBuilder->generateArticleStructure(
            $config['configuration_id'],
            $article
        );

        // Log API calls for structure generation
        $this->logger->logApiCall(
            'openai',
            'structure_generation',
            ['seed_keyword' => $article['seed_keyword']],
            ['chapters' => count($structure['chapters'])],
            $this->estimateStructureCost($config, $structure)
        );

        return $structure;
    }

    /**
     * Phase 3: Generate Content
     *
     * @param array $config Configuration
     * @param array $structure Article structure
     * @return array Generated content
     */
    private function generateContent(array $config, array $structure) {
        $contentWriter = new WordPressChapterContentWriter(
            $this->openAIClient,
            $this->configService,
            $this->db
        );

        // Write all chapters
        $chapters = $contentWriter->writeAllChapters(
            $config['configuration_id'],
            $structure
        );

        // Process introduction with links
        $introduction = $contentWriter->writeIntroductionWithLinks(
            $config['configuration_id'],
            $structure['introduction']
        );

        // Process conclusion with links
        $conclusion = $contentWriter->writeConclusionWithLinks(
            $config['configuration_id'],
            $structure['conclusion']
        );

        // Log API calls for content generation
        foreach ($chapters as $chapter) {
            $cost = $this->logger->calculateGPT4Cost(
                $chapter['target_words'] * 2, // Rough estimate for input
                $chapter['actual_words'] * 1.3 // Rough estimate for output
            );

            $this->logger->logApiCall(
                'openai',
                'chapter_writing',
                ['chapter' => $chapter['chapter_number']],
                ['words' => $chapter['actual_words']],
                $cost
            );
        }

        return [
            'introduction' => $introduction,
            'chapters' => $chapters,
            'conclusion' => $conclusion,
            'statistics' => $contentWriter->getContentStatistics($chapters)
        ];
    }

    /**
     * Phase 4: Generate Images
     *
     * @param array $config Configuration
     * @param array $structure Article structure
     * @return array Generated images
     */
    private function generateImages(array $config, array $structure) {
        $imageGenerator = new WordPressImageGenerator(
            $config['openai_api_key'] // Decrypted key
        );

        $quality = $config['image_quality'] ?? 'standard';

        $images = $imageGenerator->generateAllImages(
            $structure['image_prompts'],
            $quality
        );

        // Log API calls for image generation
        $this->logger->logApiCall(
            'dalle',
            'featured_image',
            ['size' => '1792x1024'],
            ['url' => $images['featured_image']['url']],
            $images['featured_image']['cost']
        );

        foreach ($images['chapter_images'] as $index => $image) {
            $this->logger->logApiCall(
                'dalle',
                'chapter_image',
                ['chapter' => $index + 1, 'size' => '1024x1024'],
                ['url' => $image['url']],
                $image['cost']
            );
        }

        return $images;
    }

    /**
     * Phase 5: Organize Assets
     *
     * @param array $config Configuration
     * @param array $structure Article structure
     * @param array $content Generated content
     * @param array $images Generated images
     * @return array Asset organization result
     */
    private function organizeAssets(array $config, array $structure, array $content, array $images) {
        $assetOrganizer = new WordPressAssetOrganizer(
            $config['google_drive_api_key'] ?? '' // Optional
        );

        // Prepare assets for upload
        $assets = [
            'content_files' => $this->prepareContentFiles($structure, $content),
            'images' => $images,
            'metadata' => [
                'article_structure' => $structure['metadata'],
                'content_statistics' => $content['statistics'],
                'generated_at' => date('Y-m-d H:i:s')
            ]
        ];

        $result = $assetOrganizer->organizeAssets(
            $assets,
            $structure['metadata']['slug']
        );

        // Log Google Drive operations
        $stats = $assetOrganizer->getStatistics($result);
        $this->logger->logApiCall(
            'google_drive',
            'organize_assets',
            ['files' => $stats['total_files']],
            ['folder_url' => $stats['folder_url']],
            0 // Google Drive API is typically free for reasonable usage
        );

        // Clean up local temporary files
        $assetOrganizer->cleanupImages($images);

        return $result;
    }

    /**
     * Phase 6: Publish to WordPress
     *
     * @param array $config Configuration
     * @param array $structure Article structure
     * @param array $content Generated content
     * @param array $images Generated images
     * @param array $assets Asset organization result
     * @param array $article Original article data
     * @return array Publication result
     */
    private function publishToWordPress(array $config, array $structure, array $content, array $images, array $assets, array $article) {
        $publisher = new WordPressPublisher(
            $config['wordpress_site_url'],
            $config['wordpress_api_key']
        );

        // Prepare article data for publishing
        $articleData = [
            'metadata' => $structure['metadata'],
            'introduction' => $content['introduction']['content'],
            'chapters' => $content['chapters'],
            'conclusion' => $content['conclusion']['content'],
            'featured_image_path' => $images['featured_image']['local_path'] ?? null
        ];

        // Add chapter image URLs from assets if available
        if (isset($assets['uploaded_files']['images']['chapters'])) {
            foreach ($articleData['chapters'] as $index => &$chapter) {
                if (isset($assets['uploaded_files']['images']['chapters'][$index])) {
                    $chapter['image_url'] = $assets['uploaded_files']['images']['chapters'][$index]['url'];
                }
            }
        }

        // Publishing options
        $options = [
            'status' => $config['auto_publish'] ? 'publish' : 'draft',
            'categories' => $article['categories'] ?? [],
            'tags' => $article['tags'] ?? []
        ];

        $publication = $publisher->publishArticle($articleData, $options);

        // Log WordPress operations
        $this->logger->logApiCall(
            'wordpress',
            'create_post',
            ['title' => $structure['metadata']['title']],
            ['post_id' => $publication['post_id'], 'post_url' => $publication['post_url']],
            0 // WordPress API is free
        );

        return $publication;
    }

    /**
     * Prepare content files for upload
     *
     * @param array $structure Article structure
     * @param array $content Generated content
     * @return array Content files
     */
    private function prepareContentFiles(array $structure, array $content) {
        $files = [];

        // Introduction
        $files[] = [
            'name' => 'introduction.md',
            'content' => $content['introduction']['content']
        ];

        // Chapters
        foreach ($content['chapters'] as $chapter) {
            $files[] = [
                'name' => 'chapter-' . $chapter['chapter_number'] . '.md',
                'content' => "# {$chapter['chapter_title']}\n\n{$chapter['content']}"
            ];
        }

        // Conclusion
        $files[] = [
            'name' => 'conclusion.md',
            'content' => $content['conclusion']['content']
        ];

        return $files;
    }

    /**
     * Count total words in generated content
     *
     * @param array $content Content data
     * @return int Total word count
     */
    private function countTotalWords(array $content) {
        $total = 0;

        $total += $content['introduction']['actual_words'] ?? 0;
        $total += $content['conclusion']['actual_words'] ?? 0;

        foreach ($content['chapters'] as $chapter) {
            $total += $chapter['actual_words'] ?? 0;
        }

        return $total;
    }

    /**
     * Estimate structure generation cost
     *
     * @param array $config Configuration
     * @param array $structure Generated structure
     * @return float Estimated cost
     */
    private function estimateStructureCost(array $config, array $structure) {
        // Rough estimation:
        // - Metadata: ~1000 tokens
        // - Chapter outline: ~500 tokens per chapter
        // - SEO: ~300 tokens
        // - Image prompts: ~200 tokens per image

        $chapters = count($structure['chapters']);
        $estimatedTokens = 1000 + ($chapters * 500) + 300 + ($chapters * 200);

        return $this->logger->calculateGPT4Cost($estimatedTokens * 0.5, $estimatedTokens * 0.5);
    }

    /**
     * Get current phase from logger
     *
     * @return string|null Current phase name
     */
    private function getCurrentPhase() {
        $phases = $this->logger->getPhases();
        foreach ($phases as $name => $phase) {
            if ($phase['status'] === 'in_progress') {
                return $name;
            }
        }
        return null;
    }

    /**
     * Save audit trail to file
     *
     * @param string $articleId Article ID
     * @return string File path
     */
    private function saveAuditTrail($articleId) {
        $logDir = __DIR__ . '/../../../logs/wordpress_blog';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $filename = "article_{$articleId}_" . date('Y-m-d_His') . '.json';
        $filepath = $logDir . '/' . $filename;

        $this->logger->saveToFile($filepath);

        return $filepath;
    }

    /**
     * Get generation progress
     *
     * Calculate progress percentage based on completed phases.
     *
     * @param string $articleId Article ID
     * @return array Progress data
     */
    public function getProgress($articleId) {
        $article = $this->queueService->getArticle($articleId);

        if (!$article) {
            throw new Exception("Article not found: {$articleId}");
        }

        $status = $article['status'];
        $progress = 0;

        switch ($status) {
            case 'queued':
                $progress = 0;
                break;
            case 'processing':
                // Try to determine phase from logs if available
                $progress = 50; // Midway estimate
                break;
            case 'completed':
            case 'published':
                $progress = 100;
                break;
            case 'failed':
                $progress = 0;
                break;
        }

        return [
            'article_id' => $articleId,
            'status' => $status,
            'progress_percent' => $progress,
            'updated_at' => $article['updated_at']
        ];
    }

    /**
     * Validate configuration before generation
     *
     * @param string $configId Configuration ID
     * @return bool Valid
     * @throws Exception If validation fails
     */
    public function validateConfiguration($configId) {
        $config = $this->configService->getConfiguration($configId, true);

        if (!$config) {
            throw new Exception("Configuration not found: {$configId}");
        }

        // Check required fields
        $required = [
            'wordpress_site_url',
            'wordpress_api_key',
            'openai_api_key'
        ];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new Exception("Missing required configuration field: {$field}");
            }
        }

        return true;
    }
}
