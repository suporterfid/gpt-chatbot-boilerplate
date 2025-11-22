<?php
/**
 * WordPress Chapter Content Writer Service
 *
 * Writes chapter content using OpenAI GPT-4 API with context awareness,
 * internal link injection, and word count validation.
 *
 * Responsibilities:
 * - Generate chapter content based on prompts
 * - Inject relevant internal links
 * - Validate word counts
 * - Maintain context across chapters
 * - Format content in markdown
 *
 * @package WordPressBlog\Services
 */

class WordPressChapterContentWriter {
    private $openAIClient;
    private $configService;
    private $db;

    /**
     * Constructor
     *
     * @param OpenAIClient $openAIClient OpenAI API client
     * @param WordPressBlogConfigurationService $configService Configuration service
     * @param DB $db Database instance
     */
    public function __construct($openAIClient, $configService, $db) {
        $this->openAIClient = $openAIClient;
        $this->configService = $configService;
        $this->db = $db;
    }

    /**
     * Write all chapter content for an article
     *
     * @param string $configId Configuration ID
     * @param array $articleStructure Article structure from ContentStructureBuilder
     * @return array Array of written chapters with metadata
     * @throws Exception If writing fails
     */
    public function writeAllChapters($configId, array $articleStructure) {
        $config = $this->configService->getConfiguration($configId, true);
        if (!$config) {
            throw new Exception("Configuration not found: {$configId}");
        }

        $writtenChapters = [];
        $previousChapterContext = '';

        foreach ($articleStructure['content_prompts'] as $promptData) {
            $chapterNumber = $promptData['chapter_number'];
            $targetWords = $promptData['target_words'];

            // Write chapter with context from previous chapter
            $chapterContent = $this->writeChapter(
                $config,
                $promptData,
                $articleStructure['metadata'],
                $previousChapterContext
            );

            // Inject internal links if available
            if ($config['enable_internal_links']) {
                $chapterContent = $this->injectInternalLinks(
                    $configId,
                    $chapterContent,
                    $config['internal_links_per_chapter'] ?? 2
                );
            }

            // Validate word count
            $actualWords = $this->countWords($chapterContent);
            $wordCountStatus = $this->validateWordCount($actualWords, $targetWords);

            $writtenChapters[] = [
                'chapter_number' => $chapterNumber,
                'chapter_title' => $promptData['chapter_title'],
                'content' => $chapterContent,
                'target_words' => $targetWords,
                'actual_words' => $actualWords,
                'word_count_status' => $wordCountStatus,
                'has_internal_links' => $config['enable_internal_links'],
                'written_at' => date('Y-m-d H:i:s')
            ];

            // Update context for next chapter (last 200 words)
            $previousChapterContext = $this->extractContextFromContent($chapterContent, 200);
        }

        return $writtenChapters;
    }

    /**
     * Write a single chapter
     *
     * @param array $config Configuration
     * @param array $promptData Prompt data for this chapter
     * @param array $metadata Article metadata
     * @param string $previousContext Context from previous chapter
     * @return string Chapter content in markdown
     */
    private function writeChapter(array $config, array $promptData, array $metadata, $previousContext = '') {
        $prompt = $promptData['prompt'];

        // Add context from previous chapter if available
        if (!empty($previousContext)) {
            $prompt .= "\n\nContext from previous chapter:\n" . $previousContext;
            $prompt .= "\n\nEnsure smooth transition from the previous chapter.";
        }

        $payload = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt($config)
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => $promptData['target_words'] * 2 // Rough token estimate
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Failed to generate chapter content: Invalid API response");
        }

        return trim($response['choices'][0]['message']['content']);
    }

    /**
     * Inject internal links into content
     *
     * @param string $configId Configuration ID
     * @param string $content Chapter content
     * @param int $maxLinks Maximum number of links to inject
     * @return string Content with injected links
     */
    private function injectInternalLinks($configId, $content, $maxLinks = 2) {
        // Extract keywords from content for relevance matching
        $keywords = $this->extractKeywords($content);

        // Find relevant internal links
        $relevantLinks = $this->configService->findRelevantLinks($configId, $keywords, $maxLinks * 2);

        if (empty($relevantLinks)) {
            return $content;
        }

        $linksInjected = 0;
        $modifiedContent = $content;

        foreach ($relevantLinks as $link) {
            if ($linksInjected >= $maxLinks) {
                break;
            }

            // Try to inject the link at a relevant position
            $injected = $this->injectLinkIntoContent(
                $modifiedContent,
                $link['anchor_text'],
                $link['url']
            );

            if ($injected !== $modifiedContent) {
                $modifiedContent = $injected;
                $linksInjected++;
            }
        }

        return $modifiedContent;
    }

    /**
     * Inject a single link into content at the most relevant position
     *
     * @param string $content Content
     * @param string $anchorText Anchor text to search for
     * @param string $targetUrl Target URL
     * @return string Modified content
     */
    private function injectLinkIntoContent($content, $anchorText, $targetUrl) {
        // Look for the anchor text in the content (case-insensitive)
        $pattern = '/\b' . preg_quote($anchorText, '/') . '\b/i';

        // Check if anchor text exists and is not already a link
        if (preg_match($pattern, $content)) {
            // Make sure it's not already in a link
            $linkPattern = '/\[([^\]]*' . preg_quote($anchorText, '/') . '[^\]]*)\]\([^\)]+\)/i';

            if (!preg_match($linkPattern, $content)) {
                // Replace first occurrence with markdown link
                $modifiedContent = preg_replace(
                    $pattern,
                    '[${0}](' . $targetUrl . ')',
                    $content,
                    1 // Only replace first occurrence
                );

                return $modifiedContent;
            }
        }

        return $content;
    }

    /**
     * Extract keywords from content for relevance matching
     *
     * @param string $content Content
     * @return array Keywords
     */
    private function extractKeywords($content) {
        // Remove markdown formatting
        $text = preg_replace('/[#*`\[\]()]/u', ' ', $content);

        // Convert to lowercase
        $text = strtolower($text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove common stop words
        $stopWords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'is', 'was', 'are', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'
        ];

        $words = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });

        // Count word frequency
        $wordCounts = array_count_values($words);

        // Sort by frequency
        arsort($wordCounts);

        // Return top 20 keywords
        return array_keys(array_slice($wordCounts, 0, 20));
    }

    /**
     * Extract context from content (last N words)
     *
     * @param string $content Content
     * @param int $wordLimit Word limit
     * @return string Context excerpt
     */
    private function extractContextFromContent($content, $wordLimit = 200) {
        // Remove markdown formatting for cleaner context
        $text = preg_replace('/[#*`]/u', '', $content);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Get last N words
        $contextWords = array_slice($words, -$wordLimit);

        return implode(' ', $contextWords);
    }

    /**
     * Count words in content
     *
     * @param string $content Content
     * @return int Word count
     */
    private function countWords($content) {
        // Remove markdown formatting
        $text = preg_replace('/[#*`\[\]()]/u', ' ', $content);

        // Remove URLs
        $text = preg_replace('/https?:\/\/[^\s]+/', '', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return count($words);
    }

    /**
     * Validate word count against target
     *
     * @param int $actualWords Actual word count
     * @param int $targetWords Target word count
     * @return string Status: 'on_target', 'under', 'over'
     */
    private function validateWordCount($actualWords, $targetWords) {
        $tolerance = 0.15; // 15% tolerance
        $minWords = $targetWords * (1 - $tolerance);
        $maxWords = $targetWords * (1 + $tolerance);

        if ($actualWords < $minWords) {
            return 'under';
        } elseif ($actualWords > $maxWords) {
            return 'over';
        } else {
            return 'on_target';
        }
    }

    /**
     * Build system prompt for content generation
     *
     * @param array $config Configuration
     * @return string System prompt
     */
    private function buildSystemPrompt(array $config) {
        $writingStyle = $config['writing_style'] ?? 'professional';
        $targetAudience = $config['target_audience'] ?? 'general audience';

        return <<<SYSTEM
You are an expert content writer specializing in creating high-quality blog posts.

Writing Guidelines:
- Style: {$writingStyle}
- Target Audience: {$targetAudience}
- Format: Use markdown formatting (headings, lists, bold, italic)
- Structure: Use clear H3 and H4 headings to organize content
- Tone: Engaging, informative, and authoritative
- Quality: Provide practical value and actionable insights
- Examples: Include relevant examples where appropriate
- Clarity: Write clear, concise sentences
- Flow: Ensure smooth transitions between sections

Content Requirements:
- Use proper markdown syntax for all formatting
- Break up long paragraphs for readability
- Use bullet points and numbered lists where appropriate
- Include code examples if relevant to the topic
- Make content scannable with clear headings
- Provide depth while maintaining clarity
- Write in active voice when possible
- End sections with clear takeaways

Do NOT:
- Include the chapter title as a heading (it will be added separately)
- Include meta commentary about the writing process
- Use placeholder text or TODOs
- Reference other chapters by number
- Include conclusion statements (there will be a separate conclusion section)
SYSTEM;
    }

    /**
     * Write introduction with internal links
     *
     * @param string $configId Configuration ID
     * @param string $introduction Introduction content
     * @return array Introduction with metadata
     */
    public function writeIntroductionWithLinks($configId, $introduction) {
        $config = $this->configService->getConfiguration($configId, true);
        if (!$config) {
            throw new Exception("Configuration not found: {$configId}");
        }

        $modifiedIntroduction = $introduction;

        if ($config['enable_internal_links']) {
            $maxLinks = max(1, intval(($config['internal_links_per_chapter'] ?? 2) / 2));
            $modifiedIntroduction = $this->injectInternalLinks($configId, $introduction, $maxLinks);
        }

        return [
            'content' => $modifiedIntroduction,
            'actual_words' => $this->countWords($modifiedIntroduction),
            'has_internal_links' => $config['enable_internal_links']
        ];
    }

    /**
     * Write conclusion with internal links
     *
     * @param string $configId Configuration ID
     * @param string $conclusion Conclusion content
     * @return array Conclusion with metadata
     */
    public function writeConclusionWithLinks($configId, $conclusion) {
        $config = $this->configService->getConfiguration($configId, true);
        if (!$config) {
            throw new Exception("Configuration not found: {$configId}");
        }

        $modifiedConclusion = $conclusion;

        if ($config['enable_internal_links']) {
            $maxLinks = max(1, intval(($config['internal_links_per_chapter'] ?? 2) / 2));
            $modifiedConclusion = $this->injectInternalLinks($configId, $conclusion, $maxLinks);
        }

        return [
            'content' => $modifiedConclusion,
            'actual_words' => $this->countWords($modifiedConclusion),
            'has_internal_links' => $config['enable_internal_links']
        ];
    }

    /**
     * Regenerate a single chapter if word count is significantly off
     *
     * @param array $config Configuration
     * @param array $promptData Prompt data
     * @param array $metadata Article metadata
     * @param int $actualWords Previous attempt word count
     * @return string Regenerated content
     */
    public function regenerateChapter(array $config, array $promptData, array $metadata, $actualWords) {
        $targetWords = $promptData['target_words'];
        $prompt = $promptData['prompt'];

        // Add guidance about word count
        if ($actualWords < $targetWords * 0.85) {
            $prompt .= "\n\nIMPORTANT: The previous attempt was too short ({$actualWords} words). Please expand the content to reach approximately {$targetWords} words by adding more detail, examples, and depth.";
        } elseif ($actualWords > $targetWords * 1.15) {
            $prompt .= "\n\nIMPORTANT: The previous attempt was too long ({$actualWords} words). Please condense the content to approximately {$targetWords} words while maintaining all key points.";
        }

        return $this->writeChapter($config, $promptData, $metadata);
    }

    /**
     * Get content statistics
     *
     * @param array $writtenChapters Written chapters data
     * @return array Statistics
     */
    public function getContentStatistics(array $writtenChapters) {
        $totalWords = 0;
        $totalTargetWords = 0;
        $chaptersOnTarget = 0;
        $chaptersUnder = 0;
        $chaptersOver = 0;

        foreach ($writtenChapters as $chapter) {
            $totalWords += $chapter['actual_words'];
            $totalTargetWords += $chapter['target_words'];

            switch ($chapter['word_count_status']) {
                case 'on_target':
                    $chaptersOnTarget++;
                    break;
                case 'under':
                    $chaptersUnder++;
                    break;
                case 'over':
                    $chaptersOver++;
                    break;
            }
        }

        $accuracy = $totalTargetWords > 0
            ? round(($totalWords / $totalTargetWords) * 100, 2)
            : 0;

        return [
            'total_chapters' => count($writtenChapters),
            'total_words' => $totalWords,
            'total_target_words' => $totalTargetWords,
            'word_count_accuracy' => $accuracy,
            'chapters_on_target' => $chaptersOnTarget,
            'chapters_under_target' => $chaptersUnder,
            'chapters_over_target' => $chaptersOver,
            'average_words_per_chapter' => count($writtenChapters) > 0
                ? round($totalWords / count($writtenChapters), 0)
                : 0
        ];
    }
}
