<?php
/**
 * WordPress Content Structure Builder Service
 *
 * Generates article structure including metadata, chapter outlines, and content prompts
 * using OpenAI GPT-4 API based on configuration and seed keyword.
 *
 * Responsibilities:
 * - Generate article metadata (title, subtitle, slug, meta description)
 * - Create chapter outline based on configuration
 * - Generate introduction and conclusion
 * - Create content prompts for each chapter
 * - Generate DALL-E image prompts
 * - Build SEO snippets
 *
 * @package WordPressBlog\Services
 */

class WordPressContentStructureBuilder {
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
     * Generate complete article structure
     *
     * @param string $configId Configuration ID
     * @param array $articleData Article data from queue (includes seed_keyword)
     * @return array Complete article structure
     * @throws Exception If generation fails
     */
    public function generateArticleStructure($configId, array $articleData) {
        // Get configuration with credentials
        $config = $this->configService->getConfiguration($configId, true);
        if (!$config) {
            throw new Exception("Configuration not found: {$configId}");
        }

        $seedKeyword = $articleData['seed_keyword'] ?? '';
        if (empty($seedKeyword)) {
            throw new Exception("Seed keyword is required");
        }

        // Step 1: Generate article metadata
        $metadata = $this->generateMetadata($config, $seedKeyword);

        // Step 2: Generate chapter outline
        $chapters = $this->generateChapterOutline($config, $metadata, $seedKeyword);

        // Step 3: Generate introduction
        $introduction = $this->generateIntroduction($config, $metadata, $chapters);

        // Step 4: Generate conclusion
        $conclusion = $this->generateConclusion($config, $metadata, $chapters);

        // Step 5: Generate SEO snippet
        $seoSnippet = $this->generateSEOSnippet($config, $metadata);

        // Step 6: Generate image prompts
        $imagePrompts = $this->generateImagePrompts($config, $metadata, $chapters);

        // Step 7: Generate content prompts for each chapter
        $contentPrompts = $this->generateContentPrompts($config, $metadata, $chapters);

        return [
            'metadata' => $metadata,
            'chapters' => $chapters,
            'introduction' => $introduction,
            'conclusion' => $conclusion,
            'seo_snippet' => $seoSnippet,
            'image_prompts' => $imagePrompts,
            'content_prompts' => $contentPrompts,
            'total_words' => $this->calculateTotalWords($config, count($chapters)),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate article metadata (title, subtitle, slug, meta description)
     *
     * @param array $config Configuration
     * @param string $seedKeyword Seed keyword
     * @return array Metadata
     */
    private function generateMetadata(array $config, $seedKeyword) {
        $prompt = $this->buildMetadataPrompt($config, $seedKeyword);

        $payload = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert SEO content strategist and copywriter. Generate compelling article metadata that is optimized for search engines and user engagement.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.8,
            'max_tokens' => 800,
            'response_format' => ['type' => 'json_object']
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Failed to generate metadata: Invalid API response");
        }

        $metadata = json_decode($response['choices'][0]['message']['content'], true);

        if (!$metadata || !isset($metadata['title'])) {
            throw new Exception("Failed to parse metadata response");
        }

        // Ensure slug is URL-safe
        $metadata['slug'] = $this->sanitizeSlug($metadata['slug'] ?? $metadata['title']);

        return $metadata;
    }

    /**
     * Generate chapter outline
     *
     * @param array $config Configuration
     * @param array $metadata Article metadata
     * @param string $seedKeyword Seed keyword
     * @return array Chapter outline
     */
    private function generateChapterOutline(array $config, array $metadata, $seedKeyword) {
        $numberOfChapters = $config['number_of_chapters'] ?? 5;
        $prompt = $this->buildChapterOutlinePrompt($config, $metadata, $seedKeyword, $numberOfChapters);

        $payload = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert content strategist. Create well-structured, logical chapter outlines that flow naturally and provide comprehensive coverage of the topic.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object']
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Failed to generate chapter outline: Invalid API response");
        }

        $outline = json_decode($response['choices'][0]['message']['content'], true);

        if (!$outline || !isset($outline['chapters']) || !is_array($outline['chapters'])) {
            throw new Exception("Failed to parse chapter outline response");
        }

        return $outline['chapters'];
    }

    /**
     * Generate introduction section
     *
     * @param array $config Configuration
     * @param array $metadata Article metadata
     * @param array $chapters Chapter outline
     * @return string Introduction content
     */
    private function generateIntroduction(array $config, array $metadata, array $chapters) {
        $wordCount = $config['introduction_words'] ?? 150;
        $prompt = $this->buildIntroductionPrompt($config, $metadata, $chapters, $wordCount);

        $payload = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert content writer. Write engaging, SEO-optimized introductions that hook readers and set the stage for the article.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.8,
            'max_tokens' => $wordCount * 2 // Rough token estimate
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Failed to generate introduction: Invalid API response");
        }

        return trim($response['choices'][0]['message']['content']);
    }

    /**
     * Generate conclusion section
     *
     * @param array $config Configuration
     * @param array $metadata Article metadata
     * @param array $chapters Chapter outline
     * @return string Conclusion content
     */
    private function generateConclusion(array $config, array $metadata, array $chapters) {
        $wordCount = $config['conclusion_words'] ?? 150;
        $prompt = $this->buildConclusionPrompt($config, $metadata, $chapters, $wordCount);

        $payload = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert content writer. Write compelling conclusions that summarize key points and include clear calls-to-action.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.8,
            'max_tokens' => $wordCount * 2
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Failed to generate conclusion: Invalid API response");
        }

        return trim($response['choices'][0]['message']['content']);
    }

    /**
     * Generate SEO snippet (title tag, meta description)
     *
     * @param array $config Configuration
     * @param array $metadata Article metadata
     * @return array SEO snippet data
     */
    private function generateSEOSnippet(array $config, array $metadata) {
        $prompt = $this->buildSEOSnippetPrompt($config, $metadata);

        $payload = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an SEO expert. Create optimized title tags and meta descriptions that maximize click-through rates while staying within length limits.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 300,
            'response_format' => ['type' => 'json_object']
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Failed to generate SEO snippet: Invalid API response");
        }

        $snippet = json_decode($response['choices'][0]['message']['content'], true);

        if (!$snippet) {
            throw new Exception("Failed to parse SEO snippet response");
        }

        return $snippet;
    }

    /**
     * Generate DALL-E image prompts
     *
     * @param array $config Configuration
     * @param array $metadata Article metadata
     * @param array $chapters Chapter outline
     * @return array Image prompts (featured + chapter images)
     */
    private function generateImagePrompts(array $config, array $metadata, array $chapters) {
        $prompt = $this->buildImagePromptsPrompt($config, $metadata, $chapters);

        $payload = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at creating DALL-E prompts. Generate detailed, vivid image prompts that will result in professional, high-quality images suitable for blog posts.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.8,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object']
        ];

        $response = $this->openAIClient->createChatCompletion($payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception("Failed to generate image prompts: Invalid API response");
        }

        $prompts = json_decode($response['choices'][0]['message']['content'], true);

        if (!$prompts || !isset($prompts['featured_image'])) {
            throw new Exception("Failed to parse image prompts response");
        }

        return $prompts;
    }

    /**
     * Generate content prompts for each chapter
     *
     * @param array $config Configuration
     * @param array $metadata Article metadata
     * @param array $chapters Chapter outline
     * @return array Content prompts for each chapter
     */
    private function generateContentPrompts(array $config, array $metadata, array $chapters) {
        $prompts = [];
        $targetWordsPerChapter = $config['target_words_per_chapter'] ?? 300;

        foreach ($chapters as $index => $chapter) {
            $prompts[] = [
                'chapter_number' => $index + 1,
                'chapter_title' => $chapter['title'] ?? "Chapter " . ($index + 1),
                'prompt' => $this->buildChapterContentPrompt(
                    $config,
                    $metadata,
                    $chapter,
                    $index + 1,
                    count($chapters),
                    $targetWordsPerChapter
                ),
                'target_words' => $targetWordsPerChapter
            ];
        }

        return $prompts;
    }

    /**
     * Build metadata generation prompt
     */
    private function buildMetadataPrompt(array $config, $seedKeyword) {
        $targetAudience = $config['target_audience'] ?? 'general audience';
        $writingStyle = $config['writing_style'] ?? 'professional';

        return <<<PROMPT
Generate optimized article metadata for a blog post about: "{$seedKeyword}"

Target Audience: {$targetAudience}
Writing Style: {$writingStyle}

Please provide the following in JSON format:
{
  "title": "Compelling article title (50-60 characters, include main keyword)",
  "subtitle": "Engaging subtitle that complements the title",
  "slug": "url-friendly-slug",
  "meta_description": "SEO-optimized meta description (150-160 characters)"
}

Requirements:
- Title must be attention-grabbing and include the seed keyword
- Subtitle should provide additional context or value proposition
- Slug should be URL-safe and concise
- Meta description must be compelling and within character limit
- All elements should be optimized for SEO and user engagement
PROMPT;
    }

    /**
     * Build chapter outline generation prompt
     */
    private function buildChapterOutlinePrompt(array $config, array $metadata, $seedKeyword, $numberOfChapters) {
        $targetAudience = $config['target_audience'] ?? 'general audience';
        $writingStyle = $config['writing_style'] ?? 'professional';

        return <<<PROMPT
Create a detailed {$numberOfChapters}-chapter outline for an article titled: "{$metadata['title']}"

Seed Keyword: {$seedKeyword}
Target Audience: {$targetAudience}
Writing Style: {$writingStyle}

Please provide the outline in JSON format:
{
  "chapters": [
    {
      "title": "Chapter title",
      "summary": "Brief description of what this chapter covers",
      "key_points": ["Point 1", "Point 2", "Point 3"]
    }
  ]
}

Requirements:
- Create exactly {$numberOfChapters} chapters
- Chapters should flow logically from one to the next
- Cover the topic comprehensively
- Each chapter should have 3-5 key points
- Chapters should be balanced in scope
- Use clear, descriptive titles
PROMPT;
    }

    /**
     * Build introduction generation prompt
     */
    private function buildIntroductionPrompt(array $config, array $metadata, array $chapters, $wordCount) {
        $targetAudience = $config['target_audience'] ?? 'general audience';
        $writingStyle = $config['writing_style'] ?? 'professional';

        $chapterTitles = array_map(function($ch) {
            return $ch['title'] ?? '';
        }, $chapters);
        $chapterList = implode("\n", $chapterTitles);

        return <<<PROMPT
Write an engaging introduction for the article: "{$metadata['title']}"

Target Word Count: {$wordCount} words
Target Audience: {$targetAudience}
Writing Style: {$writingStyle}

Article Overview:
{$chapterList}

Requirements:
- Hook the reader in the first sentence
- Clearly state what the article will cover
- Explain why this topic matters to the reader
- Set expectations for what they'll learn
- Target approximately {$wordCount} words
- Use markdown formatting
- Include the seed keyword naturally
- Write in {$writingStyle} style for {$targetAudience}
PROMPT;
    }

    /**
     * Build conclusion generation prompt
     */
    private function buildConclusionPrompt(array $config, array $metadata, array $chapters, $wordCount) {
        $targetAudience = $config['target_audience'] ?? 'general audience';
        $writingStyle = $config['writing_style'] ?? 'professional';

        $chapterTitles = array_map(function($ch) {
            return $ch['title'] ?? '';
        }, $chapters);
        $chapterList = implode("\n", $chapterTitles);

        return <<<PROMPT
Write a compelling conclusion for the article: "{$metadata['title']}"

Target Word Count: {$wordCount} words
Target Audience: {$targetAudience}
Writing Style: {$writingStyle}

Chapter Topics Covered:
{$chapterList}

Requirements:
- Summarize the key takeaways
- Reinforce the main message
- Include a clear call-to-action
- Leave the reader with something memorable
- Target approximately {$wordCount} words
- Use markdown formatting
- Write in {$writingStyle} style for {$targetAudience}
PROMPT;
    }

    /**
     * Build SEO snippet generation prompt
     */
    private function buildSEOSnippetPrompt(array $config, array $metadata) {
        return <<<PROMPT
Create SEO-optimized snippets for the article: "{$metadata['title']}"

Meta Description: {$metadata['meta_description']}

Please provide in JSON format:
{
  "title_tag": "SEO title tag (50-60 characters)",
  "meta_description": "Optimized meta description (150-160 characters)",
  "og_title": "Open Graph title for social sharing",
  "og_description": "Open Graph description for social sharing"
}

Requirements:
- Title tag must be within 50-60 characters
- Meta description must be within 150-160 characters
- Include primary keyword in title tag
- Make descriptions compelling for click-through
- Optimize for both search engines and social media
PROMPT;
    }

    /**
     * Build image prompts generation prompt
     */
    private function buildImagePromptsPrompt(array $config, array $metadata, array $chapters) {
        $chapterTitles = array_map(function($ch, $idx) {
            return ($idx + 1) . ". " . ($ch['title'] ?? '');
        }, $chapters, array_keys($chapters));
        $chapterList = implode("\n", $chapterTitles);

        return <<<PROMPT
Create DALL-E image generation prompts for the article: "{$metadata['title']}"

Chapters:
{$chapterList}

Please provide in JSON format:
{
  "featured_image": "Detailed DALL-E prompt for the main featured image (1792x1024)",
  "chapter_images": [
    "DALL-E prompt for chapter 1 image",
    "DALL-E prompt for chapter 2 image"
  ]
}

Requirements:
- Featured image should represent the overall article theme
- Each chapter should have a unique, relevant image prompt
- Prompts should be detailed and descriptive
- Specify style, mood, colors, and composition
- Images should be professional and blog-appropriate
- Avoid text in images
- Use photorealistic or modern illustration style
PROMPT;
    }

    /**
     * Build individual chapter content generation prompt
     */
    private function buildChapterContentPrompt(array $config, array $metadata, array $chapter, $chapterNumber, $totalChapters, $targetWords) {
        $targetAudience = $config['target_audience'] ?? 'general audience';
        $writingStyle = $config['writing_style'] ?? 'professional';

        $chapterTitle = $chapter['title'] ?? "Chapter {$chapterNumber}";
        $chapterSummary = $chapter['summary'] ?? '';
        $keyPoints = isset($chapter['key_points']) && is_array($chapter['key_points'])
            ? implode("\n- ", $chapter['key_points'])
            : '';

        return <<<PROMPT
Write content for Chapter {$chapterNumber} of {$totalChapters} for the article: "{$metadata['title']}"

Chapter Title: {$chapterTitle}
Chapter Summary: {$chapterSummary}
Key Points to Cover:
- {$keyPoints}

Target Word Count: {$targetWords} words
Target Audience: {$targetAudience}
Writing Style: {$writingStyle}

Requirements:
- Write approximately {$targetWords} words
- Cover all key points comprehensively
- Use clear headings and subheadings (H3, H4)
- Include practical examples where appropriate
- Use markdown formatting
- Write in {$writingStyle} style for {$targetAudience}
- Ensure smooth transitions between sections
- Include bullet points or numbered lists where helpful
- Make content actionable and valuable
PROMPT;
    }

    /**
     * Calculate total expected word count
     */
    private function calculateTotalWords(array $config, $numberOfChapters) {
        $introWords = $config['introduction_words'] ?? 150;
        $conclusionWords = $config['conclusion_words'] ?? 150;
        $wordsPerChapter = $config['target_words_per_chapter'] ?? 300;

        return $introWords + ($wordsPerChapter * $numberOfChapters) + $conclusionWords;
    }

    /**
     * Sanitize slug to be URL-safe
     */
    private function sanitizeSlug($slug) {
        // Convert to lowercase
        $slug = strtolower($slug);

        // Replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');

        // Replace multiple consecutive hyphens with single hyphen
        $slug = preg_replace('/-+/', '-', $slug);

        return $slug;
    }

    /**
     * Validate article structure
     *
     * @param array $structure Article structure to validate
     * @return bool True if valid
     * @throws Exception If validation fails
     */
    public function validateStructure(array $structure) {
        $required = ['metadata', 'chapters', 'introduction', 'conclusion', 'seo_snippet', 'image_prompts', 'content_prompts'];

        foreach ($required as $field) {
            if (!isset($structure[$field])) {
                throw new Exception("Missing required field in article structure: {$field}");
            }
        }

        // Validate metadata
        $requiredMetadata = ['title', 'slug', 'meta_description'];
        foreach ($requiredMetadata as $field) {
            if (empty($structure['metadata'][$field])) {
                throw new Exception("Missing required metadata field: {$field}");
            }
        }

        // Validate chapters
        if (!is_array($structure['chapters']) || empty($structure['chapters'])) {
            throw new Exception("Chapters must be a non-empty array");
        }

        // Validate content prompts match chapters
        if (count($structure['content_prompts']) !== count($structure['chapters'])) {
            throw new Exception("Number of content prompts must match number of chapters");
        }

        return true;
    }
}
