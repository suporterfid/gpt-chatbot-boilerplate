<?php
/**
 * WordPress Publisher Service
 *
 * Publishes articles to WordPress via REST API. Converts markdown to HTML,
 * assembles full content, uploads featured image, and assigns categories/tags.
 *
 * Responsibilities:
 * - Convert markdown to semantic HTML
 * - Assemble full article content (intro + chapters + conclusion)
 * - Inject CTA sections
 * - Create WordPress post via REST API
 * - Upload and assign featured image
 * - Assign categories and tags
 * - Handle API rate limiting and errors
 * - Verify post publication
 *
 * @package WordPressBlog\Services
 */

class WordPressPublisher {
    private $siteUrl;
    private $apiKey;
    private $baseUrl;
    private $maxRetries = 3;
    private $retryDelay = 2; // seconds

    /**
     * Constructor
     *
     * @param string $siteUrl WordPress site URL (e.g., https://example.com)
     * @param string $apiKey WordPress API key (Application Password)
     */
    public function __construct($siteUrl, $apiKey) {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->apiKey = $apiKey;
        $this->baseUrl = $this->siteUrl . '/wp-json/wp/v2';
    }

    /**
     * Publish complete article to WordPress
     *
     * @param array $articleData Article data including content, metadata, images
     * @param array $options Publishing options (status, categories, tags)
     * @return array Publication result with post ID and URL
     * @throws Exception If publication fails
     */
    public function publishArticle(array $articleData, array $options = []) {
        // Validate article data
        $this->validateArticleData($articleData);

        // Assemble full content
        $htmlContent = $this->assembleArticleContent($articleData);

        // Upload featured image
        $featuredImageId = null;
        if (isset($articleData['featured_image_path'])) {
            $featuredImageId = $this->uploadFeaturedImage(
                $articleData['featured_image_path'],
                $articleData['metadata']['title'] ?? 'Featured Image'
            );
        }

        // Prepare post data
        $postData = [
            'title' => $articleData['metadata']['title'] ?? 'Untitled',
            'content' => $htmlContent,
            'status' => $options['status'] ?? 'draft',
            'slug' => $articleData['metadata']['slug'] ?? '',
            'excerpt' => $articleData['metadata']['meta_description'] ?? '',
            'meta' => [
                'description' => $articleData['metadata']['meta_description'] ?? ''
            ]
        ];

        // Add featured image if uploaded
        if ($featuredImageId) {
            $postData['featured_media'] = $featuredImageId;
        }

        // Create post
        $post = $this->createPost($postData);

        // Assign categories
        if (!empty($options['categories'])) {
            $this->assignCategories($post['id'], $options['categories']);
        }

        // Assign tags
        if (!empty($options['tags'])) {
            $this->assignTags($post['id'], $options['tags']);
        }

        // Verify post is accessible
        $isAccessible = $this->verifyPostAccessible($post['link']);

        return [
            'post_id' => $post['id'],
            'post_url' => $post['link'],
            'status' => $post['status'],
            'featured_image_id' => $featuredImageId,
            'is_accessible' => $isAccessible,
            'published_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Assemble full article content from parts
     *
     * @param array $articleData Article data
     * @return string Complete HTML content
     */
    private function assembleArticleContent(array $articleData) {
        $parts = [];

        // Add introduction
        if (isset($articleData['introduction'])) {
            $parts[] = $this->convertMarkdownToHtml($articleData['introduction']);
        }

        // Add chapters
        if (isset($articleData['chapters']) && is_array($articleData['chapters'])) {
            foreach ($articleData['chapters'] as $chapter) {
                $chapterHtml = '';

                // Chapter title
                if (isset($chapter['chapter_title'])) {
                    $chapterHtml .= '<h2>' . htmlspecialchars($chapter['chapter_title']) . '</h2>' . "\n\n";
                }

                // Chapter content
                if (isset($chapter['content'])) {
                    $chapterHtml .= $this->convertMarkdownToHtml($chapter['content']);
                }

                // Chapter image (if available)
                if (isset($chapter['image_url'])) {
                    $chapterHtml .= '<figure>' . "\n";
                    $chapterHtml .= '<img src="' . htmlspecialchars($chapter['image_url']) . '" alt="' . htmlspecialchars($chapter['chapter_title'] ?? 'Chapter image') . '" />' . "\n";
                    $chapterHtml .= '</figure>' . "\n\n";
                }

                $parts[] = $chapterHtml;
            }
        }

        // Add conclusion
        if (isset($articleData['conclusion'])) {
            $parts[] = $this->convertMarkdownToHtml($articleData['conclusion']);
        }

        // Add CTA section if configured
        if (isset($articleData['cta'])) {
            $parts[] = $this->buildCTASection($articleData['cta']);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Convert markdown to HTML
     *
     * Supports:
     * - Headings (H1-H6)
     * - Bold and italic
     * - Links
     * - Lists (ordered and unordered)
     * - Code blocks and inline code
     * - Blockquotes
     * - Paragraphs
     *
     * @param string $markdown Markdown content
     * @return string HTML content
     */
    private function convertMarkdownToHtml($markdown) {
        $html = $markdown;

        // Normalize line endings
        $html = str_replace(["\r\n", "\r"], "\n", $html);

        // Code blocks (must be before inline code)
        $html = preg_replace('/```([a-z]*)\n(.*?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Headings
        $html = preg_replace('/^######\s+(.*)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.*)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.*)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.*)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.*)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.*)$/m', '<h1>$1</h1>', $html);

        // Bold (must be before italic)
        $html = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $html);

        // Italic
        $html = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $html);
        $html = preg_replace('/_([^_]+)_/', '<em>$1</em>', $html);

        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html);

        // Blockquotes
        $html = preg_replace('/^>\s+(.*)$/m', '<blockquote>$1</blockquote>', $html);

        // Horizontal rule
        $html = preg_replace('/^(---|\*\*\*|___)$/m', '<hr />', $html);

        // Unordered lists
        $html = preg_replace_callback('/(?:^[\*\-]\s+.+$\n?)+/m', function($matches) {
            $items = preg_replace('/^[\*\-]\s+(.*)$/m', '<li>$1</li>', $matches[0]);
            return '<ul>' . "\n" . $items . '</ul>' . "\n";
        }, $html);

        // Ordered lists
        $html = preg_replace_callback('/(?:^\d+\.\s+.+$\n?)+/m', function($matches) {
            $items = preg_replace('/^\d+\.\s+(.*)$/m', '<li>$1</li>', $matches[0]);
            return '<ol>' . "\n" . $items . '</ol>' . "\n";
        }, $html);

        // Paragraphs (wrap non-HTML blocks)
        $lines = explode("\n", $html);
        $inBlock = false;
        $result = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Check if line is HTML tag
            if (preg_match('/^<(h[1-6]|ul|ol|blockquote|pre|hr|\/?(ul|ol|blockquote|pre))/', $trimmed)) {
                if (strpos($trimmed, '</') === 0 || strpos($trimmed, '<hr') === 0) {
                    $inBlock = false;
                } else {
                    $inBlock = true;
                }
                $result[] = $line;
            } elseif (empty($trimmed)) {
                $inBlock = false;
                $result[] = '';
            } elseif (!$inBlock && !empty($trimmed)) {
                $result[] = '<p>' . $trimmed . '</p>';
            } else {
                $result[] = $line;
            }
        }

        $html = implode("\n", $result);

        // Clean up extra newlines
        $html = preg_replace('/\n{3,}/', "\n\n", $html);

        return trim($html);
    }

    /**
     * Build CTA (Call-to-Action) section
     *
     * @param array $ctaData CTA configuration
     * @return string CTA HTML
     */
    private function buildCTASection(array $ctaData) {
        $heading = $ctaData['heading'] ?? 'Ready to get started?';
        $text = $ctaData['text'] ?? '';
        $buttonText = $ctaData['button_text'] ?? 'Learn More';
        $buttonUrl = $ctaData['button_url'] ?? '#';

        $html = '<div class="cta-section">' . "\n";
        $html .= '<h3>' . htmlspecialchars($heading) . '</h3>' . "\n";

        if (!empty($text)) {
            $html .= '<p>' . htmlspecialchars($text) . '</p>' . "\n";
        }

        $html .= '<a href="' . htmlspecialchars($buttonUrl) . '" class="cta-button">' . htmlspecialchars($buttonText) . '</a>' . "\n";
        $html .= '</div>';

        return $html;
    }

    /**
     * Upload featured image to WordPress
     *
     * @param string $imagePath Local image path
     * @param string $title Image title
     * @return int Media ID
     * @throws Exception If upload fails
     */
    private function uploadFeaturedImage($imagePath, $title) {
        if (!file_exists($imagePath)) {
            throw new Exception("Featured image not found: {$imagePath}");
        }

        $imageData = file_get_contents($imagePath);
        $mimeType = mime_content_type($imagePath);
        $filename = basename($imagePath);

        $response = $this->makeRequest(
            'POST',
            '/media',
            $imageData,
            [
                'Content-Type: ' . $mimeType,
                'Content-Disposition: attachment; filename="' . $filename . '"'
            ]
        );

        if (!isset($response['id'])) {
            throw new Exception("Failed to upload featured image");
        }

        // Update media title
        $this->makeRequest(
            'POST',
            '/media/' . $response['id'],
            [
                'title' => $title,
                'alt_text' => $title
            ]
        );

        return $response['id'];
    }

    /**
     * Create WordPress post
     *
     * @param array $postData Post data
     * @return array Created post data
     * @throws Exception If creation fails
     */
    private function createPost(array $postData) {
        $response = $this->makeRequest('POST', '/posts', $postData);

        if (!isset($response['id'])) {
            throw new Exception("Failed to create post");
        }

        return $response;
    }

    /**
     * Assign categories to post
     *
     * @param int $postId Post ID
     * @param array $categories Category names or IDs
     * @return bool Success
     */
    private function assignCategories($postId, array $categories) {
        // Get or create category IDs
        $categoryIds = [];

        foreach ($categories as $category) {
            if (is_numeric($category)) {
                $categoryIds[] = $category;
            } else {
                $categoryIds[] = $this->getOrCreateCategory($category);
            }
        }

        if (empty($categoryIds)) {
            return false;
        }

        $this->makeRequest(
            'POST',
            '/posts/' . $postId,
            ['categories' => $categoryIds]
        );

        return true;
    }

    /**
     * Assign tags to post
     *
     * @param int $postId Post ID
     * @param array $tags Tag names or IDs
     * @return bool Success
     */
    private function assignTags($postId, array $tags) {
        // Get or create tag IDs
        $tagIds = [];

        foreach ($tags as $tag) {
            if (is_numeric($tag)) {
                $tagIds[] = $tag;
            } else {
                $tagIds[] = $this->getOrCreateTag($tag);
            }
        }

        if (empty($tagIds)) {
            return false;
        }

        $this->makeRequest(
            'POST',
            '/posts/' . $postId,
            ['tags' => $tagIds]
        );

        return true;
    }

    /**
     * Get or create category by name
     *
     * @param string $name Category name
     * @return int Category ID
     */
    private function getOrCreateCategory($name) {
        // Try to find existing category
        try {
            $response = $this->makeRequest(
                'GET',
                '/categories?search=' . urlencode($name)
            );

            if (!empty($response) && isset($response[0]['id'])) {
                return $response[0]['id'];
            }
        } catch (Exception $e) {
            // Continue to create
        }

        // Create new category
        $response = $this->makeRequest(
            'POST',
            '/categories',
            ['name' => $name]
        );

        return $response['id'];
    }

    /**
     * Get or create tag by name
     *
     * @param string $name Tag name
     * @return int Tag ID
     */
    private function getOrCreateTag($name) {
        // Try to find existing tag
        try {
            $response = $this->makeRequest(
                'GET',
                '/tags?search=' . urlencode($name)
            );

            if (!empty($response) && isset($response[0]['id'])) {
                return $response[0]['id'];
            }
        } catch (Exception $e) {
            // Continue to create
        }

        // Create new tag
        $response = $this->makeRequest(
            'POST',
            '/tags',
            ['name' => $name]
        );

        return $response['id'];
    }

    /**
     * Verify post is accessible via URL
     *
     * @param string $url Post URL
     * @return bool True if accessible
     */
    private function verifyPostAccessible($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_NOBODY => true, // HEAD request
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Make WordPress REST API request with retry logic
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param mixed $data Request data
     * @param array $extraHeaders Extra headers
     * @return array Response data
     * @throws Exception If request fails after retries
     */
    private function makeRequest($method, $endpoint, $data = null, array $extraHeaders = []) {
        $url = $this->baseUrl . $endpoint;
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $ch = curl_init();

                $headers = array_merge([
                    'Authorization: Bearer ' . $this->apiKey
                ], $extraHeaders);

                $options = [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_TIMEOUT => 60
                ];

                if ($method === 'POST') {
                    $options[CURLOPT_POST] = true;

                    if ($data !== null) {
                        // Check if data is binary (for image uploads)
                        if (is_string($data) && !json_decode($data)) {
                            $options[CURLOPT_POSTFIELDS] = $data;
                        } else {
                            $headers[] = 'Content-Type: application/json';
                            $options[CURLOPT_HTTPHEADER] = $headers;
                            $options[CURLOPT_POSTFIELDS] = is_string($data) ? $data : json_encode($data);
                        }
                    }
                } elseif ($method === 'PUT') {
                    $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                    if ($data !== null) {
                        $headers[] = 'Content-Type: application/json';
                        $options[CURLOPT_HTTPHEADER] = $headers;
                        $options[CURLOPT_POSTFIELDS] = json_encode($data);
                    }
                } elseif ($method === 'GET') {
                    // GET request - no body
                }

                curl_setopt_array($ch, $options);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($response === false) {
                    throw new Exception("cURL error: {$error}");
                }

                // Handle rate limiting (429)
                if ($httpCode === 429) {
                    if ($attempt < $this->maxRetries) {
                        $delay = $this->retryDelay * pow(2, $attempt - 1); // Exponential backoff
                        sleep($delay);
                        continue;
                    }
                    throw new Exception("WordPress API rate limit exceeded");
                }

                // Handle other errors
                if ($httpCode >= 400) {
                    $decoded = json_decode($response, true);
                    $errorMessage = $decoded['message'] ?? $decoded['error'] ?? 'Unknown error';
                    throw new Exception("WordPress API error (HTTP {$httpCode}): {$errorMessage}");
                }

                // Success - decode and return
                $decoded = json_decode($response, true);

                if ($decoded === null && $httpCode === 200) {
                    // Some endpoints return empty responses
                    return [];
                }

                return $decoded;

            } catch (Exception $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                // Retry with delay
                sleep($this->retryDelay);
            }
        }

        throw new Exception("Request failed after {$this->maxRetries} attempts");
    }

    /**
     * Validate article data structure
     *
     * @param array $articleData Article data
     * @throws Exception If validation fails
     */
    private function validateArticleData(array $articleData) {
        if (empty($articleData)) {
            throw new Exception("Article data cannot be empty");
        }

        // Require metadata with title
        if (!isset($articleData['metadata']['title'])) {
            throw new Exception("Article must have a title in metadata");
        }

        // Require at least one content section
        if (!isset($articleData['introduction']) &&
            !isset($articleData['chapters']) &&
            !isset($articleData['conclusion'])) {
            throw new Exception("Article must have at least one content section");
        }
    }

    /**
     * Update post status
     *
     * @param int $postId Post ID
     * @param string $status New status (draft, publish, pending, private)
     * @return bool Success
     */
    public function updatePostStatus($postId, $status) {
        $validStatuses = ['draft', 'publish', 'pending', 'private', 'trash'];

        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid post status: {$status}");
        }

        $this->makeRequest(
            'POST',
            '/posts/' . $postId,
            ['status' => $status]
        );

        return true;
    }

    /**
     * Delete post
     *
     * @param int $postId Post ID
     * @param bool $force Force delete (skip trash)
     * @return bool Success
     */
    public function deletePost($postId, $force = false) {
        $endpoint = '/posts/' . $postId;
        if ($force) {
            $endpoint .= '?force=true';
        }

        $this->makeRequest('DELETE', $endpoint);

        return true;
    }

    /**
     * Get post by ID
     *
     * @param int $postId Post ID
     * @return array Post data
     */
    public function getPost($postId) {
        return $this->makeRequest('GET', '/posts/' . $postId);
    }
}
