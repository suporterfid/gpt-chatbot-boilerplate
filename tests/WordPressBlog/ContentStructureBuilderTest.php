<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressContentStructureBuilder
 *
 * Tests the content structure generation service including metadata,
 * chapter outlines, and content prompts generation.
 */
class ContentStructureBuilderTest extends TestCase {
    private $db;
    private $configService;
    private $contentBuilder;
    private $mockOpenAIClient;
    private $cryptoAdapter;

    protected function setUp(): void {
        // Create in-memory database
        $this->db = new DB([
            'db_type' => 'sqlite',
            'db_path' => ':memory:'
        ]);

        // Run migration
        $migration = file_get_contents(__DIR__ . '/../../db/migrations/048_add_wordpress_blog_tables.sql');
        $statements = array_filter(
            array_map('trim', explode(';', $migration)),
            function($stmt) { return !empty($stmt); }
        );

        foreach ($statements as $statement) {
            $this->db->execute($statement);
        }

        // Initialize services
        $this->cryptoAdapter = new CryptoAdapter([
            'encryption_key' => 'test-encryption-key-32-bytes-long!'
        ]);

        $this->configService = new WordPressBlogConfigurationService($this->db, $this->cryptoAdapter);

        // Create mock OpenAI client
        $this->mockOpenAIClient = $this->createMock(OpenAIClient::class);

        $this->contentBuilder = new WordPressContentStructureBuilder(
            $this->mockOpenAIClient,
            $this->configService,
            $this->db
        );
    }

    protected function tearDown(): void {
        $this->db = null;
        $this->configService = null;
        $this->contentBuilder = null;
        $this->mockOpenAIClient = null;
    }

    /**
     * Helper: Create test configuration
     */
    private function createTestConfig(): string {
        return $this->configService->createConfiguration([
            'config_name' => 'Test Config',
            'wordpress_site_url' => 'https://test.com',
            'wordpress_api_key' => 'wp_test_key',
            'openai_api_key' => 'sk-test-key',
            'number_of_chapters' => 5,
            'target_words_per_chapter' => 300,
            'introduction_words' => 150,
            'conclusion_words' => 150,
            'target_audience' => 'developers',
            'writing_style' => 'technical'
        ]);
    }

    /**
     * Helper: Mock OpenAI response
     */
    private function mockOpenAIResponse($returnValue) {
        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($returnValue)
                        ]
                    ]
                ]
            ]);
    }

    /**
     * Test: Generate complete article structure successfully
     */
    public function testGenerateArticleStructureSuccess(): void {
        $configId = $this->createTestConfig();

        // Mock all OpenAI responses
        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnOnConsecutiveCalls(
                // Metadata response
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title' => 'Complete Guide to PHP Testing',
                            'subtitle' => 'Master PHPUnit and Testing Best Practices',
                            'slug' => 'complete-guide-php-testing',
                            'meta_description' => 'Learn everything about PHP testing with PHPUnit, from basic unit tests to advanced integration testing techniques.'
                        ])]
                    ]]
                ],
                // Chapter outline response
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'chapters' => [
                                [
                                    'title' => 'Introduction to PHPUnit',
                                    'summary' => 'Learn the basics of PHPUnit',
                                    'key_points' => ['Setup', 'Basic assertions', 'Running tests']
                                ],
                                [
                                    'title' => 'Writing Effective Tests',
                                    'summary' => 'Best practices for test writing',
                                    'key_points' => ['AAA pattern', 'Mocking', 'Test doubles']
                                ],
                                [
                                    'title' => 'Advanced Testing Techniques',
                                    'summary' => 'Advanced concepts',
                                    'key_points' => ['Data providers', 'Fixtures', 'Test suites']
                                ],
                                [
                                    'title' => 'Integration Testing',
                                    'summary' => 'Testing component interactions',
                                    'key_points' => ['Database testing', 'API testing', 'E2E tests']
                                ],
                                [
                                    'title' => 'Continuous Integration',
                                    'summary' => 'Automating tests in CI/CD',
                                    'key_points' => ['GitHub Actions', 'Test coverage', 'Automated reporting']
                                ]
                            ]
                        ])]
                    ]]
                ],
                // Introduction response
                [
                    'choices' => [[
                        'message' => ['content' => 'This is a comprehensive introduction to PHP testing...']
                    ]]
                ],
                // Conclusion response
                [
                    'choices' => [[
                        'message' => ['content' => 'In conclusion, PHP testing is essential for...']
                    ]]
                ],
                // SEO snippet response
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title_tag' => 'PHP Testing Guide 2024 | PHPUnit Best Practices',
                            'meta_description' => 'Master PHP testing with our complete guide covering PHPUnit, mocking, integration tests, and CI/CD automation.',
                            'og_title' => 'Complete Guide to PHP Testing',
                            'og_description' => 'Learn everything about PHP testing from basics to advanced techniques.'
                        ])]
                    ]]
                ],
                // Image prompts response
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'featured_image' => 'Modern developer workspace with multiple monitors showing PHPUnit test results, clean code, and green checkmarks, professional photography, bright lighting',
                            'chapter_images' => [
                                'PHPUnit logo with testing code in background',
                                'Developer writing clean test code',
                                'Advanced testing dashboard with metrics',
                                'Integration testing workflow diagram',
                                'CI/CD pipeline with automated tests'
                            ]
                        ])]
                    ]]
                ]
            );

        $articleData = [
            'seed_keyword' => 'PHP testing with PHPUnit'
        ];

        $structure = $this->contentBuilder->generateArticleStructure($configId, $articleData);

        // Validate structure
        $this->assertArrayHasKey('metadata', $structure);
        $this->assertArrayHasKey('chapters', $structure);
        $this->assertArrayHasKey('introduction', $structure);
        $this->assertArrayHasKey('conclusion', $structure);
        $this->assertArrayHasKey('seo_snippet', $structure);
        $this->assertArrayHasKey('image_prompts', $structure);
        $this->assertArrayHasKey('content_prompts', $structure);

        // Validate metadata
        $this->assertEquals('Complete Guide to PHP Testing', $structure['metadata']['title']);
        $this->assertEquals('complete-guide-php-testing', $structure['metadata']['slug']);

        // Validate chapters
        $this->assertCount(5, $structure['chapters']);
        $this->assertEquals('Introduction to PHPUnit', $structure['chapters'][0]['title']);

        // Validate content prompts
        $this->assertCount(5, $structure['content_prompts']);
        $this->assertEquals(1, $structure['content_prompts'][0]['chapter_number']);
        $this->assertEquals(300, $structure['content_prompts'][0]['target_words']);

        // Validate total words
        $this->assertEquals(1800, $structure['total_words']); // 150 + (5 * 300) + 150
    }

    /**
     * Test: Fail when configuration not found
     */
    public function testGenerateArticleStructureConfigNotFound(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configuration not found');

        $this->contentBuilder->generateArticleStructure('invalid-id', ['seed_keyword' => 'test']);
    }

    /**
     * Test: Fail when seed keyword is missing
     */
    public function testGenerateArticleStructureMissingSeedKeyword(): void {
        $configId = $this->createTestConfig();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Seed keyword is required');

        $this->contentBuilder->generateArticleStructure($configId, []);
    }

    /**
     * Test: Fail when OpenAI returns invalid metadata response
     */
    public function testGenerateMetadataInvalidResponse(): void {
        $configId = $this->createTestConfig();

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => 'invalid json']
                ]]
            ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse metadata response');

        $this->contentBuilder->generateArticleStructure($configId, ['seed_keyword' => 'test']);
    }

    /**
     * Test: Fail when OpenAI API call fails
     */
    public function testGenerateMetadataAPIFailure(): void {
        $configId = $this->createTestConfig();

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to generate metadata');

        $this->contentBuilder->generateArticleStructure($configId, ['seed_keyword' => 'test']);
    }

    /**
     * Test: Slug sanitization
     */
    public function testSlugSanitization(): void {
        $configId = $this->createTestConfig();

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnOnConsecutiveCalls(
                // Metadata with special characters in slug
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title' => 'A Guide to PHP & MySQL!',
                            'slug' => 'A Guide to PHP & MySQL!!!',
                            'meta_description' => 'Test description'
                        ])]
                    ]]
                ],
                // Chapter outline
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'chapters' => [
                                ['title' => 'Chapter 1', 'summary' => 'Test', 'key_points' => ['A', 'B']]
                            ]
                        ])]
                    ]]
                ],
                // Introduction
                ['choices' => [['message' => ['content' => 'Intro']]]],
                // Conclusion
                ['choices' => [['message' => ['content' => 'Conclusion']]]],
                // SEO snippet
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title_tag' => 'Test',
                            'meta_description' => 'Test'
                        ])]
                    ]]
                ],
                // Image prompts
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'featured_image' => 'Test',
                            'chapter_images' => ['Test']
                        ])]
                    ]]
                ]
            );

        $structure = $this->contentBuilder->generateArticleStructure($configId, ['seed_keyword' => 'test']);

        // Verify slug is sanitized
        $this->assertEquals('a-guide-to-php-mysql', $structure['metadata']['slug']);
    }

    /**
     * Test: Content prompts match number of chapters
     */
    public function testContentPromptsMatchChapters(): void {
        $configId = $this->createTestConfig();

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnOnConsecutiveCalls(
                // Metadata
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title' => 'Test Title',
                            'slug' => 'test-title',
                            'meta_description' => 'Test'
                        ])]
                    ]]
                ],
                // Chapter outline with 3 chapters
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'chapters' => [
                                ['title' => 'Chapter 1', 'summary' => 'Test', 'key_points' => ['A']],
                                ['title' => 'Chapter 2', 'summary' => 'Test', 'key_points' => ['B']],
                                ['title' => 'Chapter 3', 'summary' => 'Test', 'key_points' => ['C']]
                            ]
                        ])]
                    ]]
                ],
                // Introduction
                ['choices' => [['message' => ['content' => 'Intro']]]],
                // Conclusion
                ['choices' => [['message' => ['content' => 'Conclusion']]]],
                // SEO snippet
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title_tag' => 'Test',
                            'meta_description' => 'Test'
                        ])]
                    ]]
                ],
                // Image prompts
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'featured_image' => 'Test',
                            'chapter_images' => ['Test', 'Test', 'Test']
                        ])]
                    ]]
                ]
            );

        $structure = $this->contentBuilder->generateArticleStructure($configId, ['seed_keyword' => 'test']);

        // Verify content prompts match chapters
        $this->assertCount(3, $structure['content_prompts']);
        $this->assertEquals('Chapter 1', $structure['content_prompts'][0]['chapter_title']);
        $this->assertEquals('Chapter 2', $structure['content_prompts'][1]['chapter_title']);
        $this->assertEquals('Chapter 3', $structure['content_prompts'][2]['chapter_title']);
    }

    /**
     * Test: Total word count calculation
     */
    public function testTotalWordCountCalculation(): void {
        $configId = $this->configService->createConfiguration([
            'config_name' => 'Custom Word Count',
            'wordpress_site_url' => 'https://test.com',
            'wordpress_api_key' => 'wp_test_key',
            'openai_api_key' => 'sk-test-key',
            'number_of_chapters' => 3,
            'target_words_per_chapter' => 500,
            'introduction_words' => 200,
            'conclusion_words' => 200
        ]);

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnOnConsecutiveCalls(
                // Metadata
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title' => 'Test',
                            'slug' => 'test',
                            'meta_description' => 'Test'
                        ])]
                    ]]
                ],
                // Chapters
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'chapters' => [
                                ['title' => 'Ch1', 'summary' => 'T', 'key_points' => ['A']],
                                ['title' => 'Ch2', 'summary' => 'T', 'key_points' => ['B']],
                                ['title' => 'Ch3', 'summary' => 'T', 'key_points' => ['C']]
                            ]
                        ])]
                    ]]
                ],
                // Intro
                ['choices' => [['message' => ['content' => 'I']]]],
                // Conclusion
                ['choices' => [['message' => ['content' => 'C']]]],
                // SEO
                ['choices' => [['message' => ['content' => json_encode(['title_tag' => 'T', 'meta_description' => 'T'])]]]],
                // Images
                ['choices' => [['message' => ['content' => json_encode(['featured_image' => 'T', 'chapter_images' => ['A', 'B', 'C']])]]]]
            );

        $structure = $this->contentBuilder->generateArticleStructure($configId, ['seed_keyword' => 'test']);

        // 200 (intro) + 3 * 500 (chapters) + 200 (conclusion) = 1900
        $this->assertEquals(1900, $structure['total_words']);
    }

    /**
     * Test: Validate structure - valid structure
     */
    public function testValidateStructureValid(): void {
        $validStructure = [
            'metadata' => [
                'title' => 'Test Title',
                'slug' => 'test-title',
                'meta_description' => 'Test description'
            ],
            'chapters' => [
                ['title' => 'Chapter 1', 'summary' => 'Test', 'key_points' => ['A', 'B']]
            ],
            'introduction' => 'Test introduction',
            'conclusion' => 'Test conclusion',
            'seo_snippet' => ['title_tag' => 'Test', 'meta_description' => 'Test'],
            'image_prompts' => ['featured_image' => 'Test', 'chapter_images' => ['Test']],
            'content_prompts' => [
                ['chapter_number' => 1, 'chapter_title' => 'Chapter 1', 'prompt' => 'Test', 'target_words' => 300]
            ]
        ];

        $result = $this->contentBuilder->validateStructure($validStructure);
        $this->assertTrue($result);
    }

    /**
     * Test: Validate structure - missing required field
     */
    public function testValidateStructureMissingField(): void {
        $invalidStructure = [
            'metadata' => ['title' => 'Test'],
            'chapters' => []
            // Missing other required fields
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required field');

        $this->contentBuilder->validateStructure($invalidStructure);
    }

    /**
     * Test: Validate structure - missing metadata field
     */
    public function testValidateStructureMissingMetadataField(): void {
        $invalidStructure = [
            'metadata' => [
                'title' => 'Test'
                // Missing slug and meta_description
            ],
            'chapters' => [['title' => 'Test']],
            'introduction' => 'Test',
            'conclusion' => 'Test',
            'seo_snippet' => [],
            'image_prompts' => [],
            'content_prompts' => []
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing required metadata field');

        $this->contentBuilder->validateStructure($invalidStructure);
    }

    /**
     * Test: Validate structure - empty chapters
     */
    public function testValidateStructureEmptyChapters(): void {
        $invalidStructure = [
            'metadata' => [
                'title' => 'Test',
                'slug' => 'test',
                'meta_description' => 'Test'
            ],
            'chapters' => [], // Empty chapters
            'introduction' => 'Test',
            'conclusion' => 'Test',
            'seo_snippet' => [],
            'image_prompts' => [],
            'content_prompts' => []
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Chapters must be a non-empty array');

        $this->contentBuilder->validateStructure($invalidStructure);
    }

    /**
     * Test: Validate structure - prompts don't match chapters
     */
    public function testValidateStructurePromptsMismatch(): void {
        $invalidStructure = [
            'metadata' => [
                'title' => 'Test',
                'slug' => 'test',
                'meta_description' => 'Test'
            ],
            'chapters' => [
                ['title' => 'Ch1'],
                ['title' => 'Ch2']
            ],
            'introduction' => 'Test',
            'conclusion' => 'Test',
            'seo_snippet' => [],
            'image_prompts' => [],
            'content_prompts' => [
                ['prompt' => 'Test'] // Only 1 prompt for 2 chapters
            ]
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Number of content prompts must match number of chapters');

        $this->contentBuilder->validateStructure($invalidStructure);
    }

    /**
     * Test: Chapter content prompt includes all necessary context
     */
    public function testChapterContentPromptIncludesContext(): void {
        $configId = $this->createTestConfig();

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnOnConsecutiveCalls(
                // Metadata
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'title' => 'PHP Testing Guide',
                            'slug' => 'php-testing-guide',
                            'meta_description' => 'Complete guide to PHP testing'
                        ])]
                    ]]
                ],
                // Chapters
                [
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'chapters' => [
                                [
                                    'title' => 'Getting Started with PHPUnit',
                                    'summary' => 'Learn the basics of PHPUnit framework',
                                    'key_points' => ['Installation', 'Configuration', 'First test']
                                ]
                            ]
                        ])]
                    ]]
                ],
                // Intro
                ['choices' => [['message' => ['content' => 'Intro']]]],
                // Conclusion
                ['choices' => [['message' => ['content' => 'Conclusion']]]],
                // SEO
                ['choices' => [['message' => ['content' => json_encode(['title_tag' => 'T', 'meta_description' => 'T'])]]]],
                // Images
                ['choices' => [['message' => ['content' => json_encode(['featured_image' => 'T', 'chapter_images' => ['T']])]]]]
            );

        $structure = $this->contentBuilder->generateArticleStructure($configId, ['seed_keyword' => 'PHP testing']);

        $prompt = $structure['content_prompts'][0]['prompt'];

        // Verify prompt includes key context
        $this->assertStringContainsString('PHP Testing Guide', $prompt);
        $this->assertStringContainsString('Getting Started with PHPUnit', $prompt);
        $this->assertStringContainsString('Learn the basics of PHPUnit framework', $prompt);
        $this->assertStringContainsString('Installation', $prompt);
        $this->assertStringContainsString('Configuration', $prompt);
        $this->assertStringContainsString('First test', $prompt);
        $this->assertStringContainsString('300 words', $prompt);
        $this->assertStringContainsString('developers', $prompt);
        $this->assertStringContainsString('technical', $prompt);
    }

    /**
     * Test: Generated structure includes timestamp
     */
    public function testGeneratedStructureIncludesTimestamp(): void {
        $configId = $this->createTestConfig();

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnOnConsecutiveCalls(
                ['choices' => [['message' => ['content' => json_encode(['title' => 'T', 'slug' => 't', 'meta_description' => 'T'])]]]],
                ['choices' => [['message' => ['content' => json_encode(['chapters' => [['title' => 'C', 'summary' => 'S', 'key_points' => ['A']]]])]]]],
                ['choices' => [['message' => ['content' => 'I']]]],
                ['choices' => [['message' => ['content' => 'C']]]],
                ['choices' => [['message' => ['content' => json_encode(['title_tag' => 'T', 'meta_description' => 'T'])]]]],
                ['choices' => [['message' => ['content' => json_encode(['featured_image' => 'F', 'chapter_images' => ['I']])]]]]
            );

        $structure = $this->contentBuilder->generateArticleStructure($configId, ['seed_keyword' => 'test']);

        $this->assertArrayHasKey('generated_at', $structure);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $structure['generated_at']);
    }

    /**
     * Test: Different configurations produce different word counts
     */
    public function testDifferentConfigurationWordCounts(): void {
        // Config 1: Small article
        $config1 = $this->configService->createConfiguration([
            'config_name' => 'Small',
            'wordpress_site_url' => 'https://test.com',
            'wordpress_api_key' => 'key',
            'openai_api_key' => 'key',
            'number_of_chapters' => 2,
            'target_words_per_chapter' => 200,
            'introduction_words' => 100,
            'conclusion_words' => 100
        ]);

        // Config 2: Large article
        $config2 = $this->configService->createConfiguration([
            'config_name' => 'Large',
            'wordpress_site_url' => 'https://test.com',
            'wordpress_api_key' => 'key',
            'openai_api_key' => 'key',
            'number_of_chapters' => 10,
            'target_words_per_chapter' => 500,
            'introduction_words' => 300,
            'conclusion_words' => 300
        ]);

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnCallback(function() {
                static $call = 0;
                $call++;

                // Return appropriate responses based on call order
                if ($call % 6 == 1) { // Metadata
                    return ['choices' => [['message' => ['content' => json_encode(['title' => 'T', 'slug' => 't', 'meta_description' => 'T'])]]]];
                } elseif ($call % 6 == 2) { // Chapters
                    $chapterCount = ($call <= 6) ? 2 : 10;
                    $chapters = array_fill(0, $chapterCount, ['title' => 'C', 'summary' => 'S', 'key_points' => ['A']]);
                    return ['choices' => [['message' => ['content' => json_encode(['chapters' => $chapters])]]]];
                } elseif ($call % 6 == 3 || $call % 6 == 4) { // Intro/Conclusion
                    return ['choices' => [['message' => ['content' => 'Text']]]];
                } elseif ($call % 6 == 5) { // SEO
                    return ['choices' => [['message' => ['content' => json_encode(['title_tag' => 'T', 'meta_description' => 'T'])]]]];
                } else { // Images
                    $chapterCount = ($call <= 12) ? 2 : 10;
                    return ['choices' => [['message' => ['content' => json_encode(['featured_image' => 'F', 'chapter_images' => array_fill(0, $chapterCount, 'I')])]]]];
                }
            });

        $structure1 = $this->contentBuilder->generateArticleStructure($config1, ['seed_keyword' => 'test']);
        $structure2 = $this->contentBuilder->generateArticleStructure($config2, ['seed_keyword' => 'test']);

        // Small: 100 + (2 * 200) + 100 = 600
        $this->assertEquals(600, $structure1['total_words']);

        // Large: 300 + (10 * 500) + 300 = 5600
        $this->assertEquals(5600, $structure2['total_words']);
    }
}
