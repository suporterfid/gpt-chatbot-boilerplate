<?php
/**
 * WordPress Blog Validation Engine
 *
 * Comprehensive validation for:
 * - Configuration validation (URLs, ranges, required fields)
 * - API connectivity tests (OpenAI, WordPress, Google Drive)
 * - Content validation (word count, markdown syntax, images)
 * - Output validation (article structure, chapter content)
 * - Prohibited content detection
 *
 * @package WordPressBlog\Validation
 */

class WordPressBlogValidationEngine {
    private $errors = [];
    private $warnings = [];

    /**
     * Validate blog configuration
     *
     * @param array $config Configuration array
     * @return array Validation result with errors/warnings
     */
    public function validateConfiguration(array $config) {
        $this->errors = [];
        $this->warnings = [];

        // Required fields
        $required = [
            'config_name' => 'Configuration name',
            'wordpress_site_url' => 'WordPress site URL',
            'wordpress_api_key' => 'WordPress API key',
            'openai_api_key' => 'OpenAI API key'
        ];

        foreach ($required as $field => $label) {
            if (empty($config[$field])) {
                $this->errors[] = "{$label} is required";
            }
        }

        // Validate URLs
        if (!empty($config['wordpress_site_url'])) {
            if (!$this->validateUrl($config['wordpress_site_url'])) {
                $this->errors[] = 'WordPress site URL is not valid';
            }
        }

        // Validate target word count
        if (isset($config['target_word_count'])) {
            $wordCount = intval($config['target_word_count']);
            if ($wordCount < 500 || $wordCount > 10000) {
                $this->errors[] = 'Target word count must be between 500 and 10,000';
            }
        }

        // Validate image quality
        if (isset($config['image_quality'])) {
            $validQualities = ['standard', 'hd'];
            if (!in_array($config['image_quality'], $validQualities)) {
                $this->errors[] = 'Image quality must be "standard" or "hd"';
            }
        }

        // Validate auto_publish (boolean)
        if (isset($config['auto_publish'])) {
            if (!is_bool($config['auto_publish']) && !in_array($config['auto_publish'], [0, 1, '0', '1', 'true', 'false'], true)) {
                $this->errors[] = 'Auto-publish must be a boolean value';
            }
        }

        // Validate Google Drive folder ID format (if provided)
        if (!empty($config['google_drive_folder_id'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]{28,}$/', $config['google_drive_folder_id'])) {
                $this->warnings[] = 'Google Drive folder ID format may be invalid';
            }
        }

        // Validate OpenAI API key format
        if (!empty($config['openai_api_key'])) {
            if (!preg_match('/^sk-proj-[a-zA-Z0-9_-]+$/', $config['openai_api_key']) &&
                !preg_match('/^sk-[a-zA-Z0-9]{48}$/', $config['openai_api_key'])) {
                $this->warnings[] = 'OpenAI API key format may be invalid';
            }
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    /**
     * Test API connectivity for OpenAI
     *
     * @param string $apiKey OpenAI API key
     * @return array Test result with status and message
     */
    public function testOpenAIConnectivity($apiKey) {
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $error
            ];
        }

        if ($httpCode === 200) {
            return [
                'success' => true,
                'message' => 'OpenAI API connection successful'
            ];
        } elseif ($httpCode === 401) {
            return [
                'success' => false,
                'message' => 'Invalid API key'
            ];
        } elseif ($httpCode === 429) {
            return [
                'success' => false,
                'message' => 'Rate limit exceeded'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'API returned status code: ' . $httpCode
            ];
        }
    }

    /**
     * Test WordPress REST API connectivity
     *
     * @param string $siteUrl WordPress site URL
     * @param string $apiKey WordPress API key (application password)
     * @return array Test result
     */
    public function testWordPressConnectivity($siteUrl, $apiKey) {
        $url = rtrim($siteUrl, '/') . '/wp-json/wp/v2/posts';

        // Parse API key (format: username:password)
        $parts = explode(':', $apiKey, 2);
        if (count($parts) !== 2) {
            return [
                'success' => false,
                'message' => 'Invalid API key format (expected username:password)'
            ];
        }

        list($username, $password) = $parts;

        $ch = curl_init($url . '?per_page=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $error
            ];
        }

        if ($httpCode === 200) {
            return [
                'success' => true,
                'message' => 'WordPress API connection successful'
            ];
        } elseif ($httpCode === 401 || $httpCode === 403) {
            return [
                'success' => false,
                'message' => 'Authentication failed'
            ];
        } elseif ($httpCode === 404) {
            return [
                'success' => false,
                'message' => 'WordPress REST API not found (ensure permalinks are enabled)'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'API returned status code: ' . $httpCode
            ];
        }
    }

    /**
     * Validate article content structure
     *
     * @param array $structure Article structure
     * @return array Validation result
     */
    public function validateArticleStructure(array $structure) {
        $this->errors = [];
        $this->warnings = [];

        // Required structure fields
        $requiredFields = ['metadata', 'chapters', 'introduction', 'conclusion'];
        foreach ($requiredFields as $field) {
            if (!isset($structure[$field])) {
                $this->errors[] = "Missing required field: {$field}";
            }
        }

        // Validate metadata
        if (isset($structure['metadata'])) {
            $requiredMeta = ['title', 'slug', 'meta_description'];
            foreach ($requiredMeta as $field) {
                if (empty($structure['metadata'][$field])) {
                    $this->errors[] = "Missing metadata field: {$field}";
                }
            }

            // Validate title length
            if (isset($structure['metadata']['title'])) {
                $titleLen = strlen($structure['metadata']['title']);
                if ($titleLen < 10) {
                    $this->errors[] = 'Title is too short (minimum 10 characters)';
                } elseif ($titleLen > 200) {
                    $this->warnings[] = 'Title is very long (recommended maximum 100 characters)';
                }
            }

            // Validate meta description
            if (isset($structure['metadata']['meta_description'])) {
                $descLen = strlen($structure['metadata']['meta_description']);
                if ($descLen < 50) {
                    $this->warnings[] = 'Meta description is short (recommended 120-160 characters)';
                } elseif ($descLen > 200) {
                    $this->warnings[] = 'Meta description is too long (maximum 160 characters)';
                }
            }
        }

        // Validate chapters
        if (isset($structure['chapters'])) {
            if (!is_array($structure['chapters']) || empty($structure['chapters'])) {
                $this->errors[] = 'Article must have at least one chapter';
            } else {
                $chapterCount = count($structure['chapters']);
                if ($chapterCount < 3) {
                    $this->warnings[] = 'Article has few chapters (recommended: 5-8)';
                } elseif ($chapterCount > 15) {
                    $this->warnings[] = 'Article has many chapters (recommended: 5-8)';
                }

                foreach ($structure['chapters'] as $index => $chapter) {
                    if (empty($chapter['chapter_title'])) {
                        $this->errors[] = "Chapter {$index} is missing title";
                    }
                    if (empty($chapter['chapter_outline'])) {
                        $this->warnings[] = "Chapter {$index} is missing outline";
                    }
                }
            }
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    /**
     * Validate generated content
     *
     * @param array $content Generated content with chapters
     * @param int $targetWordCount Target word count
     * @return array Validation result
     */
    public function validateContent(array $content, $targetWordCount = 2000) {
        $this->errors = [];
        $this->warnings = [];

        // Count total words
        $totalWords = 0;

        if (isset($content['introduction']['content'])) {
            $totalWords += $this->countWords($content['introduction']['content']);
        }

        if (isset($content['chapters']) && is_array($content['chapters'])) {
            foreach ($content['chapters'] as $chapter) {
                if (isset($chapter['content'])) {
                    $totalWords += $this->countWords($chapter['content']);
                }
            }
        }

        if (isset($content['conclusion']['content'])) {
            $totalWords += $this->countWords($content['conclusion']['content']);
        }

        // Validate word count (±5% tolerance)
        $tolerance = $targetWordCount * 0.05;
        $minWords = $targetWordCount - $tolerance;
        $maxWords = $targetWordCount + $tolerance;

        if ($totalWords < $minWords) {
            $this->warnings[] = "Content is shorter than target ({$totalWords} vs {$targetWordCount} words)";
        } elseif ($totalWords > $maxWords) {
            $this->warnings[] = "Content is longer than target ({$totalWords} vs {$targetWordCount} words)";
        }

        // Validate markdown syntax
        if (isset($content['chapters']) && is_array($content['chapters'])) {
            foreach ($content['chapters'] as $index => $chapter) {
                if (isset($chapter['content'])) {
                    $markdownIssues = $this->validateMarkdownSyntax($chapter['content']);
                    if (!empty($markdownIssues)) {
                        $this->warnings[] = "Chapter {$index} has markdown issues: " . implode(', ', $markdownIssues);
                    }
                }
            }
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'word_count' => $totalWords
        ];
    }

    /**
     * Validate image files
     *
     * @param array $images Array of image data
     * @return array Validation result
     */
    public function validateImages(array $images) {
        $this->errors = [];
        $this->warnings = [];

        // Check featured image
        if (!isset($images['featured_image']) || empty($images['featured_image']['local_path'])) {
            $this->errors[] = 'Missing featured image';
        } else {
            $this->validateImageFile($images['featured_image']['local_path'], 'Featured image');
        }

        // Check chapter images
        if (isset($images['chapter_images']) && is_array($images['chapter_images'])) {
            foreach ($images['chapter_images'] as $index => $image) {
                if (empty($image['local_path'])) {
                    $this->warnings[] = "Chapter {$index} image is missing";
                } else {
                    $this->validateImageFile($image['local_path'], "Chapter {$index} image");
                }
            }
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    /**
     * Detect prohibited content
     *
     * @param string $content Content to check
     * @return array Detection result
     */
    public function detectProhibitedContent($content) {
        $prohibitedPatterns = [
            'spam' => [
                '/\bcasino\b/i',
                '/\bviagra\b/i',
                '/\benlargement\b/i'
            ],
            'harmful' => [
                '/\bexploit\b.*\bvulnerability\b/i',
                '/\bhack\b.*\bpassword\b/i'
            ]
        ];

        $detected = [];

        foreach ($prohibitedPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $detected[] = $category;
                    break;
                }
            }
        }

        return [
            'has_prohibited' => !empty($detected),
            'categories' => array_unique($detected)
        ];
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    /**
     * Validate URL format
     *
     * @param string $url URL to validate
     * @return bool Valid
     */
    private function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false &&
               preg_match('/^https?:\/\//i', $url);
    }

    /**
     * Count words in text
     *
     * @param string $text Text content
     * @return int Word count
     */
    private function countWords($text) {
        // Remove markdown syntax
        $text = preg_replace('/```[\s\S]*?```/', '', $text); // Code blocks
        $text = preg_replace('/`[^`]+`/', '', $text); // Inline code
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text); // Links
        $text = preg_replace('/[#*_~`]/', '', $text); // Markdown symbols
        $text = strip_tags($text); // HTML tags

        return str_word_count($text);
    }

    /**
     * Validate markdown syntax
     *
     * @param string $content Markdown content
     * @return array Issues found
     */
    private function validateMarkdownSyntax($content) {
        $issues = [];

        // Check for unclosed code blocks
        $codeBlocks = substr_count($content, '```');
        if ($codeBlocks % 2 !== 0) {
            $issues[] = 'unclosed code block';
        }

        // Check for broken links
        if (preg_match('/\[([^\]]*)\]\([^\)]*$/', $content)) {
            $issues[] = 'broken link syntax';
        }

        // Check for broken bold/italic
        $boldCount = substr_count($content, '**');
        if ($boldCount % 2 !== 0) {
            $issues[] = 'unclosed bold markup';
        }

        return $issues;
    }

    /**
     * Validate individual image file
     *
     * @param string $path Image file path
     * @param string $label Label for error messages
     * @return void
     */
    private function validateImageFile($path, $label) {
        // Check if file exists
        if (!file_exists($path)) {
            $this->errors[] = "{$label}: file not found";
            return;
        }

        // Check if readable
        if (!is_readable($path)) {
            $this->errors[] = "{$label}: file not readable";
            return;
        }

        // Check file size (should be > 0 and < 10MB)
        $fileSize = filesize($path);
        if ($fileSize === 0) {
            $this->errors[] = "{$label}: file is empty";
        } elseif ($fileSize > 10 * 1024 * 1024) {
            $this->warnings[] = "{$label}: file is very large (>10MB)";
        }

        // Check if it's actually an image
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            $this->errors[] = "{$label}: not a valid image file";
            return;
        }

        // Validate dimensions
        list($width, $height) = $imageInfo;
        if ($width < 100 || $height < 100) {
            $this->warnings[] = "{$label}: image dimensions are very small ({$width}x{$height})";
        }
    }

    /**
     * Get all current errors
     *
     * @return array Errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get all current warnings
     *
     * @return array Warnings
     */
    public function getWarnings() {
        return $this->warnings;
    }

    /**
     * Clear errors and warnings
     *
     * @return void
     */
    public function clearMessages() {
        $this->errors = [];
        $this->warnings = [];
    }
}
