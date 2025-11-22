<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressChapterContentWriter
 *
 * Tests chapter content generation, internal link injection, and word count validation.
 */
class ChapterContentWriterTest extends TestCase {
    private $db;
    private $configService;
    private $contentWriter;
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

        $this->contentWriter = new WordPressChapterContentWriter(
            $this->mockOpenAIClient,
            $this->configService,
            $this->db
        );
    }

    protected function tearDown(): void {
        $this->db = null;
        $this->configService = null;
        $this->contentWriter = null;
        $this->mockOpenAIClient = null;
    }

    /**
     * Helper: Create test configuration
     */
    private function createTestConfig(array $overrides = []): string {
        $defaults = [
            'config_name' => 'Test Config',
            'wordpress_site_url' => 'https://test.com',
            'wordpress_api_key' => 'wp_test_key',
            'openai_api_key' => 'sk-test-key',
            'number_of_chapters' => 3,
            'target_words_per_chapter' => 300,
            'introduction_words' => 150,
            'conclusion_words' => 150,
            'target_audience' => 'developers',
            'writing_style' => 'technical',
            'enable_internal_links' => true,
            'internal_links_per_chapter' => 2
        ];

        return $this->configService->createConfiguration(array_merge($defaults, $overrides));
    }

    /**
     * Helper: Create article structure
     */
    private function createArticleStructure(): array {
        return [
            'metadata' => [
                'title' => 'PHP Testing Guide',
                'slug' => 'php-testing-guide',
                'meta_description' => 'Complete guide to PHP testing'
            ],
            'content_prompts' => [
                [
                    'chapter_number' => 1,
                    'chapter_title' => 'Introduction to PHPUnit',
                    'prompt' => 'Write about PHPUnit basics',
                    'target_words' => 300
                ],
                [
                    'chapter_number' => 2,
                    'chapter_title' => 'Writing Tests',
                    'prompt' => 'Write about writing effective tests',
                    'target_words' => 300
                ],
                [
                    'chapter_number' => 3,
                    'chapter_title' => 'Advanced Techniques',
                    'prompt' => 'Write about advanced testing techniques',
                    'target_words' => 300
                ]
            ]
        ];
    }

    /**
     * Test: Write all chapters successfully
     */
    public function testWriteAllChaptersSuccess(): void {
        $configId = $this->createTestConfig();
        $structure = $this->createArticleStructure();

        // Mock OpenAI responses for each chapter
        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnOnConsecutiveCalls(
                [
                    'choices' => [[
                        'message' => ['content' => str_repeat('PHPUnit is a testing framework. ', 50)] // ~300 words
                    ]]
                ],
                [
                    'choices' => [[
                        'message' => ['content' => str_repeat('Writing tests requires practice. ', 50)] // ~300 words
                    ]]
                ],
                [
                    'choices' => [[
                        'message' => ['content' => str_repeat('Advanced techniques improve quality. ', 50)] // ~300 words
                    ]]
                ]
            );

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        $this->assertCount(3, $writtenChapters);

        // Validate first chapter
        $this->assertEquals(1, $writtenChapters[0]['chapter_number']);
        $this->assertEquals('Introduction to PHPUnit', $writtenChapters[0]['chapter_title']);
        $this->assertGreaterThan(0, $writtenChapters[0]['actual_words']);
        $this->assertEquals(300, $writtenChapters[0]['target_words']);
        $this->assertArrayHasKey('word_count_status', $writtenChapters[0]);
        $this->assertTrue($writtenChapters[0]['has_internal_links']);
    }

    /**
     * Test: Word count validation - on target
     */
    public function testWordCountOnTarget(): void {
        $configId = $this->createTestConfig();
        $structure = $this->createArticleStructure();

        // Generate exactly 300 words (within 15% tolerance: 255-345)
        $content = str_repeat('word ', 300);

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        $this->assertEquals('on_target', $writtenChapters[0]['word_count_status']);
    }

    /**
     * Test: Word count validation - under target
     */
    public function testWordCountUnder(): void {
        $configId = $this->createTestConfig();
        $structure = $this->createArticleStructure();

        // Generate 200 words (below 15% tolerance)
        $content = str_repeat('word ', 200);

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        $this->assertEquals('under', $writtenChapters[0]['word_count_status']);
    }

    /**
     * Test: Word count validation - over target
     */
    public function testWordCountOver(): void {
        $configId = $this->createTestConfig();
        $structure = $this->createArticleStructure();

        // Generate 400 words (above 15% tolerance)
        $content = str_repeat('word ', 400);

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        $this->assertEquals('over', $writtenChapters[0]['word_count_status']);
    }

    /**
     * Test: Internal links injection
     */
    public function testInternalLinksInjection(): void {
        $configId = $this->createTestConfig();

        // Add internal links to configuration
        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'testing framework',
            'target_url' => 'https://test.com/frameworks',
            'keywords' => ['testing', 'framework', 'phpunit']
        ]);

        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'unit tests',
            'target_url' => 'https://test.com/unit-tests',
            'keywords' => ['unit', 'tests', 'testing']
        ]);

        $structure = $this->createArticleStructure();

        // Mock content that includes the anchor text
        $content = 'PHPUnit is a testing framework for PHP. You should write unit tests for your code.';

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        // Check that links were injected
        $firstChapterContent = $writtenChapters[0]['content'];
        $this->assertStringContainsString('[testing framework](https://test.com/frameworks)', $firstChapterContent);
    }

    /**
     * Test: Internal links disabled
     */
    public function testInternalLinksDisabled(): void {
        $configId = $this->createTestConfig(['enable_internal_links' => false]);
        $structure = $this->createArticleStructure();

        $content = 'PHPUnit is a testing framework.';

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        $this->assertFalse($writtenChapters[0]['has_internal_links']);
    }

    /**
     * Test: Context passing between chapters
     */
    public function testContextPassingBetweenChapters(): void {
        $configId = $this->createTestConfig();
        $structure = $this->createArticleStructure();

        $callCount = 0;
        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnCallback(function($payload) use (&$callCount) {
                $callCount++;

                // Check that second and third calls include context
                if ($callCount > 1) {
                    $userMessage = $payload['messages'][1]['content'];
                    $this->assertStringContainsString('Context from previous chapter', $userMessage);
                }

                return [
                    'choices' => [[
                        'message' => ['content' => 'Chapter content with context. ' . str_repeat('word ', 50)]
                    ]]
                ];
            });

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        $this->assertCount(3, $writtenChapters);
    }

    /**
     * Test: Count words correctly
     */
    public function testCountWordsCorrectly(): void {
        $configId = $this->createTestConfig();
        $structure = [
            'metadata' => ['title' => 'Test'],
            'content_prompts' => [
                [
                    'chapter_number' => 1,
                    'chapter_title' => 'Test',
                    'prompt' => 'Test',
                    'target_words' => 10
                ]
            ]
        ];

        // 10 words exactly
        $content = 'one two three four five six seven eight nine ten';

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        $this->assertEquals(10, $writtenChapters[0]['actual_words']);
    }

    /**
     * Test: Count words ignores markdown
     */
    public function testCountWordsIgnoresMarkdown(): void {
        $configId = $this->createTestConfig();
        $structure = [
            'metadata' => ['title' => 'Test'],
            'content_prompts' => [
                [
                    'chapter_number' => 1,
                    'chapter_title' => 'Test',
                    'prompt' => 'Test',
                    'target_words' => 10
                ]
            ]
        ];

        // 10 words with markdown
        $content = '## Heading\n\nThis is **bold** and *italic* text with `code`.';

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        // Should count: Heading, This, is, bold, and, italic, text, with, code = 9 words
        $this->assertEquals(9, $writtenChapters[0]['actual_words']);
    }

    /**
     * Test: Write introduction with links
     */
    public function testWriteIntroductionWithLinks(): void {
        $configId = $this->createTestConfig();

        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'PHP testing',
            'target_url' => 'https://test.com/php-testing',
            'keywords' => ['php', 'testing']
        ]);

        $introduction = 'This guide covers PHP testing with PHPUnit framework.';

        $result = $this->contentWriter->writeIntroductionWithLinks($configId, $introduction);

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('actual_words', $result);
        $this->assertArrayHasKey('has_internal_links', $result);
        $this->assertTrue($result['has_internal_links']);
        $this->assertStringContainsString('[PHP testing](https://test.com/php-testing)', $result['content']);
    }

    /**
     * Test: Write conclusion with links
     */
    public function testWriteConclusionWithLinks(): void {
        $configId = $this->createTestConfig();

        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'best practices',
            'target_url' => 'https://test.com/best-practices',
            'keywords' => ['best', 'practices']
        ]);

        $conclusion = 'Following best practices ensures quality code.';

        $result = $this->contentWriter->writeConclusionWithLinks($configId, $conclusion);

        $this->assertArrayHasKey('content', $result);
        $this->assertStringContainsString('[best practices](https://test.com/best-practices)', $result['content']);
    }

    /**
     * Test: Get content statistics
     */
    public function testGetContentStatistics(): void {
        $writtenChapters = [
            [
                'chapter_number' => 1,
                'actual_words' => 280,
                'target_words' => 300,
                'word_count_status' => 'on_target'
            ],
            [
                'chapter_number' => 2,
                'actual_words' => 200,
                'target_words' => 300,
                'word_count_status' => 'under'
            ],
            [
                'chapter_number' => 3,
                'actual_words' => 400,
                'target_words' => 300,
                'word_count_status' => 'over'
            ]
        ];

        $stats = $this->contentWriter->getContentStatistics($writtenChapters);

        $this->assertEquals(3, $stats['total_chapters']);
        $this->assertEquals(880, $stats['total_words']);
        $this->assertEquals(900, $stats['total_target_words']);
        $this->assertEquals(1, $stats['chapters_on_target']);
        $this->assertEquals(1, $stats['chapters_under_target']);
        $this->assertEquals(1, $stats['chapters_over_target']);
        $this->assertEquals(293, $stats['average_words_per_chapter']);
        $this->assertEquals(97.78, $stats['word_count_accuracy']);
    }

    /**
     * Test: Inject link does not create duplicate links
     */
    public function testInjectLinkNoDuplicates(): void {
        $configId = $this->createTestConfig();

        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'testing',
            'target_url' => 'https://test.com/testing',
            'keywords' => ['testing']
        ]);

        $structure = [
            'metadata' => ['title' => 'Test'],
            'content_prompts' => [
                [
                    'chapter_number' => 1,
                    'chapter_title' => 'Test',
                    'prompt' => 'Test',
                    'target_words' => 50
                ]
            ]
        ];

        // Content already has a link with "testing"
        $content = 'This covers [testing](https://other.com) and more testing topics.';

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        // Should only link the second occurrence
        $chapterContent = $writtenChapters[0]['content'];
        $linkCount = substr_count($chapterContent, '[testing]');
        $this->assertEquals(2, $linkCount); // Original link + one new link
    }

    /**
     * Test: Extract keywords from content
     */
    public function testExtractKeywords(): void {
        $configId = $this->createTestConfig();

        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'testing',
            'target_url' => 'https://test.com/testing',
            'keywords' => ['testing', 'phpunit', 'framework']
        ]);

        $structure = [
            'metadata' => ['title' => 'Test'],
            'content_prompts' => [
                [
                    'chapter_number' => 1,
                    'chapter_title' => 'Test',
                    'prompt' => 'Test',
                    'target_words' => 50
                ]
            ]
        ];

        // Content with repeated keywords
        $content = 'PHPUnit is a testing framework. Testing with PHPUnit framework improves code quality. Framework testing is essential.';

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        // Should find relevant link based on keywords
        $chapterContent = $writtenChapters[0]['content'];
        $this->assertStringContainsString('https://test.com/testing', $chapterContent);
    }

    /**
     * Test: Fail when configuration not found
     */
    public function testWriteAllChaptersConfigNotFound(): void {
        $structure = $this->createArticleStructure();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configuration not found');

        $this->contentWriter->writeAllChapters('invalid-id', $structure);
    }

    /**
     * Test: Fail when OpenAI returns invalid response
     */
    public function testWriteChapterInvalidResponse(): void {
        $configId = $this->createTestConfig();
        $structure = $this->createArticleStructure();

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to generate chapter content');

        $this->contentWriter->writeAllChapters($configId, $structure);
    }

    /**
     * Test: System prompt includes configuration settings
     */
    public function testSystemPromptIncludesSettings(): void {
        $configId = $this->createTestConfig([
            'writing_style' => 'casual',
            'target_audience' => 'beginners'
        ]);

        $structure = [
            'metadata' => ['title' => 'Test'],
            'content_prompts' => [
                [
                    'chapter_number' => 1,
                    'chapter_title' => 'Test',
                    'prompt' => 'Test',
                    'target_words' => 50
                ]
            ]
        ];

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturnCallback(function($payload) {
                $systemMessage = $payload['messages'][0]['content'];
                $this->assertStringContainsString('casual', $systemMessage);
                $this->assertStringContainsString('beginners', $systemMessage);

                return [
                    'choices' => [[
                        'message' => ['content' => 'Test content']
                    ]]
                ];
            });

        $this->contentWriter->writeAllChapters($configId, $structure);
    }

    /**
     * Test: Regenerate chapter with word count guidance
     */
    public function testRegenerateChapterWithGuidance(): void {
        $config = [
            'writing_style' => 'professional',
            'target_audience' => 'developers'
        ];

        $promptData = [
            'chapter_number' => 1,
            'chapter_title' => 'Test Chapter',
            'prompt' => 'Write about testing',
            'target_words' => 300
        ];

        $metadata = ['title' => 'Test Article'];

        $this->mockOpenAIClient
            ->expects($this->once())
            ->method('createChatCompletion')
            ->with($this->callback(function($payload) {
                $userMessage = $payload['messages'][1]['content'];
                return strpos($userMessage, 'too short') !== false;
            }))
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => str_repeat('word ', 300)]
                ]]
            ]);

        $content = $this->contentWriter->regenerateChapter($config, $promptData, $metadata, 200);

        $this->assertNotEmpty($content);
    }

    /**
     * Test: Internal link respects max links per chapter
     */
    public function testInternalLinksRespectsMaxLinks(): void {
        $configId = $this->createTestConfig(['internal_links_per_chapter' => 1]);

        // Add multiple links
        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'testing',
            'target_url' => 'https://test.com/testing',
            'keywords' => ['testing']
        ]);

        $this->configService->addInternalLink($configId, [
            'anchor_text' => 'framework',
            'target_url' => 'https://test.com/framework',
            'keywords' => ['framework']
        ]);

        $structure = [
            'metadata' => ['title' => 'Test'],
            'content_prompts' => [
                [
                    'chapter_number' => 1,
                    'chapter_title' => 'Test',
                    'prompt' => 'Test',
                    'target_words' => 50
                ]
            ]
        ];

        $content = 'This is about testing and framework development.';

        $this->mockOpenAIClient
            ->method('createChatCompletion')
            ->willReturn([
                'choices' => [[
                    'message' => ['content' => $content]
                ]]
            ]);

        $writtenChapters = $this->contentWriter->writeAllChapters($configId, $structure);

        // Should only have 1 internal link (max per chapter)
        $chapterContent = $writtenChapters[0]['content'];
        $linkCount = substr_count($chapterContent, '](https://test.com/');
        $this->assertLessThanOrEqual(1, $linkCount);
    }
}
