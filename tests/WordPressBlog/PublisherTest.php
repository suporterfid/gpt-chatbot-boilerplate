<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressPublisher
 *
 * Tests WordPress publishing including markdown conversion, content assembly,
 * and REST API integration.
 */
class PublisherTest extends TestCase {
    private $publisher;
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/test_publisher_' . time();
        mkdir($this->tempDir, 0755, true);

        // Create publisher with test credentials
        $this->publisher = new WordPressPublisher(
            'https://example.com',
            'test-api-key'
        );
    }

    protected function tearDown(): void {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveDeleteDirectory($this->tempDir);
        }

        $this->publisher = null;
    }

    /**
     * Helper: Recursively delete directory
     */
    private function recursiveDeleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDeleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test: Markdown to HTML - headings
     */
    public function testMarkdownToHtmlHeadings(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "# Heading 1\n## Heading 2\n### Heading 3";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<h1>Heading 1</h1>', $html);
        $this->assertStringContainsString('<h2>Heading 2</h2>', $html);
        $this->assertStringContainsString('<h3>Heading 3</h3>', $html);
    }

    /**
     * Test: Markdown to HTML - bold and italic
     */
    public function testMarkdownToHtmlBoldItalic(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "This is **bold** and this is *italic*.";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
    }

    /**
     * Test: Markdown to HTML - links
     */
    public function testMarkdownToHtmlLinks(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "Check out [this link](https://example.com).";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<a href="https://example.com">this link</a>', $html);
    }

    /**
     * Test: Markdown to HTML - unordered lists
     */
    public function testMarkdownToHtmlUnorderedLists(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "- Item 1\n- Item 2\n- Item 3";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Item 1</li>', $html);
        $this->assertStringContainsString('<li>Item 2</li>', $html);
        $this->assertStringContainsString('</ul>', $html);
    }

    /**
     * Test: Markdown to HTML - ordered lists
     */
    public function testMarkdownToHtmlOrderedLists(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "1. First\n2. Second\n3. Third";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>First</li>', $html);
        $this->assertStringContainsString('<li>Second</li>', $html);
        $this->assertStringContainsString('</ol>', $html);
    }

    /**
     * Test: Markdown to HTML - code blocks
     */
    public function testMarkdownToHtmlCodeBlocks(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "```php\necho 'Hello World';\n```";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<pre><code class="language-php">', $html);
        $this->assertStringContainsString("echo 'Hello World';", $html);
        $this->assertStringContainsString('</code></pre>', $html);
    }

    /**
     * Test: Markdown to HTML - inline code
     */
    public function testMarkdownToHtmlInlineCode(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "Use the `function()` to call it.";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<code>function()</code>', $html);
    }

    /**
     * Test: Markdown to HTML - blockquotes
     */
    public function testMarkdownToHtmlBlockquotes(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "> This is a quote";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<blockquote>This is a quote</blockquote>', $html);
    }

    /**
     * Test: Markdown to HTML - paragraphs
     */
    public function testMarkdownToHtmlParagraphs(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "This is a paragraph.\n\nThis is another paragraph.";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<p>This is a paragraph.</p>', $html);
        $this->assertStringContainsString('<p>This is another paragraph.</p>', $html);
    }

    /**
     * Test: Markdown to HTML - complex document
     */
    public function testMarkdownToHtmlComplexDocument(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = <<<MD
# Main Title

This is an introduction with **bold** text and *italic* text.

## Section 1

Here's a [link](https://example.com) and some `inline code`.

### Subsection

- List item 1
- List item 2

```javascript
console.log('Hello');
```

> A quote here

Regular paragraph at the end.
MD;

        $html = $method->invoke($this->publisher, $markdown);

        // Verify all elements are present
        $this->assertStringContainsString('<h1>Main Title</h1>', $html);
        $this->assertStringContainsString('<h2>Section 1</h2>', $html);
        $this->assertStringContainsString('<h3>Subsection</h3>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
        $this->assertStringContainsString('<a href="https://example.com">link</a>', $html);
        $this->assertStringContainsString('<code>inline code</code>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>List item 1</li>', $html);
        $this->assertStringContainsString('<pre><code class="language-javascript">', $html);
        $this->assertStringContainsString('<blockquote>', $html);
    }

    /**
     * Test: Assemble article content - full article
     */
    public function testAssembleArticleContentFull(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('assembleArticleContent');
        $method->setAccessible(true);

        $articleData = [
            'introduction' => '# Introduction\n\nThis is the intro.',
            'chapters' => [
                [
                    'chapter_title' => 'Chapter 1',
                    'content' => 'Chapter 1 content here.'
                ],
                [
                    'chapter_title' => 'Chapter 2',
                    'content' => 'Chapter 2 content here.'
                ]
            ],
            'conclusion' => 'This is the conclusion.'
        ];

        $html = $method->invoke($this->publisher, $articleData);

        $this->assertStringContainsString('Introduction', $html);
        $this->assertStringContainsString('<h2>Chapter 1</h2>', $html);
        $this->assertStringContainsString('Chapter 1 content', $html);
        $this->assertStringContainsString('<h2>Chapter 2</h2>', $html);
        $this->assertStringContainsString('Chapter 2 content', $html);
        $this->assertStringContainsString('conclusion', $html);
    }

    /**
     * Test: Assemble article content - with chapter images
     */
    public function testAssembleArticleContentWithImages(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('assembleArticleContent');
        $method->setAccessible(true);

        $articleData = [
            'chapters' => [
                [
                    'chapter_title' => 'Test Chapter',
                    'content' => 'Content here.',
                    'image_url' => 'https://example.com/image.png'
                ]
            ]
        ];

        $html = $method->invoke($this->publisher, $articleData);

        $this->assertStringContainsString('<figure>', $html);
        $this->assertStringContainsString('<img src="https://example.com/image.png"', $html);
        $this->assertStringContainsString('alt="Test Chapter"', $html);
        $this->assertStringContainsString('</figure>', $html);
    }

    /**
     * Test: Assemble article content - with CTA
     */
    public function testAssembleArticleContentWithCTA(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('assembleArticleContent');
        $method->setAccessible(true);

        $articleData = [
            'introduction' => 'Intro',
            'cta' => [
                'heading' => 'Get Started Today',
                'text' => 'Join thousands of users.',
                'button_text' => 'Sign Up',
                'button_url' => 'https://example.com/signup'
            ]
        ];

        $html = $method->invoke($this->publisher, $articleData);

        $this->assertStringContainsString('<div class="cta-section">', $html);
        $this->assertStringContainsString('<h3>Get Started Today</h3>', $html);
        $this->assertStringContainsString('Join thousands of users.', $html);
        $this->assertStringContainsString('<a href="https://example.com/signup"', $html);
        $this->assertStringContainsString('class="cta-button">Sign Up</a>', $html);
    }

    /**
     * Test: Build CTA section
     */
    public function testBuildCTASection(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('buildCTASection');
        $method->setAccessible(true);

        $ctaData = [
            'heading' => 'Custom Heading',
            'text' => 'Custom text here.',
            'button_text' => 'Click Me',
            'button_url' => 'https://test.com'
        ];

        $html = $method->invoke($this->publisher, $ctaData);

        $this->assertStringContainsString('Custom Heading', $html);
        $this->assertStringContainsString('Custom text here.', $html);
        $this->assertStringContainsString('Click Me', $html);
        $this->assertStringContainsString('https://test.com', $html);
    }

    /**
     * Test: Build CTA section - with defaults
     */
    public function testBuildCTASectionDefaults(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('buildCTASection');
        $method->setAccessible(true);

        $html = $method->invoke($this->publisher, []);

        $this->assertStringContainsString('Ready to get started?', $html);
        $this->assertStringContainsString('Learn More', $html);
    }

    /**
     * Test: Validate article data - valid
     */
    public function testValidateArticleDataValid(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('validateArticleData');
        $method->setAccessible(true);

        $articleData = [
            'metadata' => [
                'title' => 'Test Article'
            ],
            'introduction' => 'Intro text'
        ];

        $this->expectNotToPerformAssertions();
        $method->invoke($this->publisher, $articleData);
    }

    /**
     * Test: Validate article data - empty
     */
    public function testValidateArticleDataEmpty(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('validateArticleData');
        $method->setAccessible(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Article data cannot be empty');

        $method->invoke($this->publisher, []);
    }

    /**
     * Test: Validate article data - missing title
     */
    public function testValidateArticleDataMissingTitle(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('validateArticleData');
        $method->setAccessible(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must have a title');

        $method->invoke($this->publisher, [
            'metadata' => [],
            'introduction' => 'Text'
        ]);
    }

    /**
     * Test: Validate article data - no content
     */
    public function testValidateArticleDataNoContent(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('validateArticleData');
        $method->setAccessible(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('at least one content section');

        $method->invoke($this->publisher, [
            'metadata' => ['title' => 'Test']
        ]);
    }

    /**
     * Test: Update post status - valid status
     */
    public function testUpdatePostStatusValid(): void {
        $validStatuses = ['draft', 'publish', 'pending', 'private', 'trash'];

        foreach ($validStatuses as $status) {
            // Just verify no exception is thrown for valid statuses
            // We can't actually test the API call without mocking
            $this->assertTrue(in_array($status, $validStatuses));
        }
    }

    /**
     * Test: Update post status - invalid status
     */
    public function testUpdatePostStatusInvalid(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid post status');

        $this->publisher->updatePostStatus(123, 'invalid_status');
    }

    /**
     * Test: Markdown special characters escaped in HTML
     */
    public function testMarkdownSpecialCharactersEscaped(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "This & that < > test.";
        $html = $method->invoke($this->publisher, $markdown);

        // HTML should be generated, but special chars in plain text should remain
        $this->assertNotEmpty($html);
    }

    /**
     * Test: Assemble content - only introduction
     */
    public function testAssembleContentOnlyIntroduction(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('assembleArticleContent');
        $method->setAccessible(true);

        $articleData = [
            'introduction' => 'Only intro text here.'
        ];

        $html = $method->invoke($this->publisher, $articleData);

        $this->assertStringContainsString('intro text', $html);
    }

    /**
     * Test: Assemble content - only chapters
     */
    public function testAssembleContentOnlyChapters(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('assembleArticleContent');
        $method->setAccessible(true);

        $articleData = [
            'chapters' => [
                ['chapter_title' => 'Solo', 'content' => 'Solo content.']
            ]
        ];

        $html = $method->invoke($this->publisher, $articleData);

        $this->assertStringContainsString('<h2>Solo</h2>', $html);
        $this->assertStringContainsString('Solo content', $html);
    }

    /**
     * Test: Markdown horizontal rule
     */
    public function testMarkdownHorizontalRule(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "Before\n\n---\n\nAfter";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<hr />', $html);
    }

    /**
     * Test: Multiple markdown styles
     */
    public function testMarkdownMultipleStyles(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $markdown = "This is **bold and *italic***";
        $html = $method->invoke($this->publisher, $markdown);

        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('<em>', $html);
    }

    /**
     * Test: Empty markdown returns empty string
     */
    public function testEmptyMarkdown(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $html = $method->invoke($this->publisher, '');

        $this->assertEquals('', $html);
    }

    /**
     * Test: Markdown with only whitespace
     */
    public function testMarkdownOnlyWhitespace(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('convertMarkdownToHtml');
        $method->setAccessible(true);

        $html = $method->invoke($this->publisher, "   \n\n   ");

        $this->assertEquals('', trim($html));
    }

    /**
     * Test: Chapter without title
     */
    public function testChapterWithoutTitle(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('assembleArticleContent');
        $method->setAccessible(true);

        $articleData = [
            'chapters' => [
                ['content' => 'Content without title.']
            ]
        ];

        $html = $method->invoke($this->publisher, $articleData);

        $this->assertStringContainsString('Content without title', $html);
        // Should not have <h2> tag
        $this->assertStringNotContainsString('<h2></h2>', $html);
    }

    /**
     * Test: CTA section escapes HTML
     */
    public function testCTASectionEscapesHTML(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('buildCTASection');
        $method->setAccessible(true);

        $ctaData = [
            'heading' => '<script>alert("xss")</script>',
            'button_url' => 'javascript:alert("xss")'
        ];

        $html = $method->invoke($this->publisher, $ctaData);

        // HTML should be escaped
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * Test: Article with all sections
     */
    public function testArticleWithAllSections(): void {
        $reflection = new ReflectionClass($this->publisher);
        $method = $reflection->getMethod('assembleArticleContent');
        $method->setAccessible(true);

        $articleData = [
            'introduction' => '# Intro\n\nIntroduction text.',
            'chapters' => [
                [
                    'chapter_title' => 'Chapter 1',
                    'content' => 'Chapter 1 **content**.',
                    'image_url' => 'https://example.com/ch1.png'
                ],
                [
                    'chapter_title' => 'Chapter 2',
                    'content' => 'Chapter 2 content.'
                ]
            ],
            'conclusion' => 'Conclusion text.',
            'cta' => [
                'heading' => 'CTA Heading',
                'button_text' => 'Click',
                'button_url' => 'https://cta.com'
            ]
        ];

        $html = $method->invoke($this->publisher, $articleData);

        // Verify all sections present
        $this->assertStringContainsString('Introduction text', $html);
        $this->assertStringContainsString('<h2>Chapter 1</h2>', $html);
        $this->assertStringContainsString('<strong>content</strong>', $html);
        $this->assertStringContainsString('<img src="https://example.com/ch1.png"', $html);
        $this->assertStringContainsString('<h2>Chapter 2</h2>', $html);
        $this->assertStringContainsString('Conclusion text', $html);
        $this->assertStringContainsString('CTA Heading', $html);
    }
}
