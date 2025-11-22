<?php
/**
 * WordPress Image Generator Service
 *
 * Generates images using DALL-E 3 API based on prompts from ContentStructureBuilder.
 * Downloads and validates images for blog posts.
 *
 * Responsibilities:
 * - Generate featured image via DALL-E 3 (1792x1024)
 * - Generate chapter images (1024x1024)
 * - Download and store images temporarily
 * - Validate image generation
 * - Track generation costs
 *
 * @package WordPressBlog\Services
 */

class WordPressImageGenerator {
    private $apiKey;
    private $baseUrl;
    private $tempDir;

    /**
     * DALL-E 3 Pricing (as of 2024)
     */
    const DALLE3_STANDARD_1024 = 0.040;  // $0.040 per image
    const DALLE3_STANDARD_1792 = 0.080;  // $0.080 per image
    const DALLE3_HD_1024 = 0.080;        // $0.080 per image
    const DALLE3_HD_1792 = 0.120;        // $0.120 per image

    /**
     * Constructor
     *
     * @param string $apiKey OpenAI API key
     * @param string $tempDir Temporary directory for downloaded images
     */
    public function __construct($apiKey, $tempDir = null) {
        $this->apiKey = $apiKey;
        $this->baseUrl = 'https://api.openai.com/v1';
        $this->tempDir = $tempDir ?? sys_get_temp_dir() . '/wordpress_blog_images';

        // Ensure temp directory exists
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Generate all images for an article
     *
     * @param array $imagePrompts Image prompts from ContentStructureBuilder
     * @param string $quality Image quality: 'standard' or 'hd'
     * @return array Generated image data with paths and URLs
     * @throws Exception If generation fails
     */
    public function generateAllImages(array $imagePrompts, $quality = 'standard') {
        if (!isset($imagePrompts['featured_image'])) {
            throw new Exception("Featured image prompt is required");
        }

        $generatedImages = [
            'featured_image' => null,
            'chapter_images' => [],
            'total_cost' => 0,
            'generated_at' => date('Y-m-d H:i:s')
        ];

        // Generate featured image (1792x1024 landscape)
        $featuredImage = $this->generateImage(
            $imagePrompts['featured_image'],
            '1792x1024',
            $quality,
            'featured'
        );

        $generatedImages['featured_image'] = $featuredImage;
        $generatedImages['total_cost'] += $featuredImage['cost'];

        // Generate chapter images (1024x1024 square)
        if (isset($imagePrompts['chapter_images']) && is_array($imagePrompts['chapter_images'])) {
            foreach ($imagePrompts['chapter_images'] as $index => $prompt) {
                $chapterImage = $this->generateImage(
                    $prompt,
                    '1024x1024',
                    $quality,
                    'chapter_' . ($index + 1)
                );

                $generatedImages['chapter_images'][] = $chapterImage;
                $generatedImages['total_cost'] += $chapterImage['cost'];
            }
        }

        return $generatedImages;
    }

    /**
     * Generate a single image using DALL-E 3
     *
     * @param string $prompt Image generation prompt
     * @param string $size Image size: '1024x1024' or '1792x1024'
     * @param string $quality Quality: 'standard' or 'hd'
     * @param string $filename Base filename (without extension)
     * @return array Image data
     * @throws Exception If generation fails
     */
    public function generateImage($prompt, $size = '1024x1024', $quality = 'standard', $filename = 'image') {
        $payload = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $quality,
            'response_format' => 'url' // Get URL to download
        ];

        $response = $this->makeImageRequest($payload);

        if (!isset($response['data'][0]['url'])) {
            throw new Exception("Failed to generate image: Invalid API response");
        }

        $imageUrl = $response['data'][0]['url'];
        $revisedPrompt = $response['data'][0]['revised_prompt'] ?? $prompt;

        // Download the image
        $localPath = $this->downloadImage($imageUrl, $filename);

        // Calculate cost
        $cost = $this->calculateCost($size, $quality);

        return [
            'prompt' => $prompt,
            'revised_prompt' => $revisedPrompt,
            'url' => $imageUrl,
            'local_path' => $localPath,
            'size' => $size,
            'quality' => $quality,
            'cost' => $cost,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Download image from URL to local temp directory
     *
     * @param string $url Image URL
     * @param string $filename Base filename
     * @return string Local file path
     * @throws Exception If download fails
     */
    private function downloadImage($url, $filename) {
        $extension = 'png'; // DALL-E returns PNG images
        $localPath = $this->tempDir . '/' . $filename . '_' . time() . '.' . $extension;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60
        ]);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($imageData === false || $httpCode !== 200) {
            throw new Exception("Failed to download image: {$error}");
        }

        // Validate it's a valid image
        if (!$this->isValidImage($imageData)) {
            throw new Exception("Downloaded data is not a valid image");
        }

        // Save to file
        if (file_put_contents($localPath, $imageData) === false) {
            throw new Exception("Failed to save image to: {$localPath}");
        }

        return $localPath;
    }

    /**
     * Validate that data is a valid image
     *
     * @param string $imageData Image binary data
     * @return bool True if valid
     */
    private function isValidImage($imageData) {
        // Check PNG signature
        $pngSignature = "\x89PNG\r\n\x1a\n";
        if (substr($imageData, 0, 8) === $pngSignature) {
            return true;
        }

        // Check JPEG signature
        if (substr($imageData, 0, 2) === "\xFF\xD8") {
            return true;
        }

        return false;
    }

    /**
     * Make request to OpenAI Images API
     *
     * @param array $payload Request payload
     * @return array Response data
     * @throws Exception If request fails
     */
    private function makeImageRequest(array $payload) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/images/generations',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 120 // Images can take longer
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL error: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown error';
            throw new Exception("OpenAI Images API error (HTTP {$httpCode}): {$errorMessage}");
        }

        if (!$decoded) {
            throw new Exception("Failed to decode API response");
        }

        return $decoded;
    }

    /**
     * Calculate cost for image generation
     *
     * @param string $size Image size
     * @param string $quality Quality level
     * @return float Cost in USD
     */
    private function calculateCost($size, $quality) {
        if ($quality === 'hd') {
            return $size === '1792x1024' ? self::DALLE3_HD_1792 : self::DALLE3_HD_1024;
        } else {
            return $size === '1792x1024' ? self::DALLE3_STANDARD_1792 : self::DALLE3_STANDARD_1024;
        }
    }

    /**
     * Get image metadata (dimensions, file size)
     *
     * @param string $imagePath Path to image file
     * @return array Metadata
     */
    public function getImageMetadata($imagePath) {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: {$imagePath}");
        }

        $imageInfo = getimagesize($imagePath);

        if ($imageInfo === false) {
            throw new Exception("Failed to get image info: {$imagePath}");
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime'],
            'file_size' => filesize($imagePath),
            'file_size_mb' => round(filesize($imagePath) / (1024 * 1024), 2)
        ];
    }

    /**
     * Validate all generated images
     *
     * @param array $generatedImages Images from generateAllImages()
     * @return array Validation results
     */
    public function validateImages(array $generatedImages) {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Validate featured image
        if (isset($generatedImages['featured_image'])) {
            $featuredValidation = $this->validateSingleImage(
                $generatedImages['featured_image'],
                'featured_image',
                ['1792x1024']
            );

            if (!$featuredValidation['valid']) {
                $results['valid'] = false;
                $results['errors'] = array_merge($results['errors'], $featuredValidation['errors']);
            }

            $results['warnings'] = array_merge($results['warnings'], $featuredValidation['warnings']);
        } else {
            $results['valid'] = false;
            $results['errors'][] = 'Missing featured image';
        }

        // Validate chapter images
        if (isset($generatedImages['chapter_images'])) {
            foreach ($generatedImages['chapter_images'] as $index => $image) {
                $chapterValidation = $this->validateSingleImage(
                    $image,
                    'chapter_image_' . ($index + 1),
                    ['1024x1024']
                );

                if (!$chapterValidation['valid']) {
                    $results['valid'] = false;
                    $results['errors'] = array_merge($results['errors'], $chapterValidation['errors']);
                }

                $results['warnings'] = array_merge($results['warnings'], $chapterValidation['warnings']);
            }
        }

        return $results;
    }

    /**
     * Validate a single image
     *
     * @param array $image Image data
     * @param string $name Image name for error messages
     * @param array $allowedSizes Allowed image sizes
     * @return array Validation result
     */
    private function validateSingleImage(array $image, $name, array $allowedSizes) {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Check required fields
        $requiredFields = ['local_path', 'size', 'url'];
        foreach ($requiredFields as $field) {
            if (!isset($image[$field])) {
                $result['valid'] = false;
                $result['errors'][] = "{$name}: Missing field '{$field}'";
            }
        }

        // Check file exists
        if (isset($image['local_path']) && !file_exists($image['local_path'])) {
            $result['valid'] = false;
            $result['errors'][] = "{$name}: File not found at {$image['local_path']}";
            return $result; // Can't do further validation
        }

        // Check size matches allowed sizes
        if (isset($image['size']) && !in_array($image['size'], $allowedSizes)) {
            $result['warnings'][] = "{$name}: Size {$image['size']} not in allowed sizes: " . implode(', ', $allowedSizes);
        }

        // Get and validate image metadata
        try {
            $metadata = $this->getImageMetadata($image['local_path']);

            // Check if file is too large (warn if > 5MB)
            if ($metadata['file_size_mb'] > 5) {
                $result['warnings'][] = "{$name}: Large file size ({$metadata['file_size_mb']} MB)";
            }

            // Verify dimensions match declared size
            if (isset($image['size'])) {
                list($expectedWidth, $expectedHeight) = explode('x', $image['size']);
                if ($metadata['width'] != $expectedWidth || $metadata['height'] != $expectedHeight) {
                    $result['warnings'][] = "{$name}: Actual dimensions ({$metadata['width']}x{$metadata['height']}) don't match declared size ({$image['size']})";
                }
            }
        } catch (Exception $e) {
            $result['valid'] = false;
            $result['errors'][] = "{$name}: Validation error - " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Clean up temporary images
     *
     * @param array $generatedImages Images to clean up
     */
    public function cleanupImages(array $generatedImages) {
        $deletedFiles = 0;

        if (isset($generatedImages['featured_image']['local_path'])) {
            if (file_exists($generatedImages['featured_image']['local_path'])) {
                unlink($generatedImages['featured_image']['local_path']);
                $deletedFiles++;
            }
        }

        if (isset($generatedImages['chapter_images'])) {
            foreach ($generatedImages['chapter_images'] as $image) {
                if (isset($image['local_path']) && file_exists($image['local_path'])) {
                    unlink($image['local_path']);
                    $deletedFiles++;
                }
            }
        }

        return $deletedFiles;
    }

    /**
     * Get total cost for image generation
     *
     * @param array $generatedImages Images data
     * @return float Total cost in USD
     */
    public function getTotalCost(array $generatedImages) {
        return $generatedImages['total_cost'] ?? 0;
    }

    /**
     * Regenerate a single image
     *
     * @param string $prompt Image prompt
     * @param string $size Image size
     * @param string $quality Quality level
     * @param string $filename Base filename
     * @return array Image data
     */
    public function regenerateImage($prompt, $size, $quality, $filename) {
        return $this->generateImage($prompt, $size, $quality, $filename);
    }

    /**
     * Get image generation statistics
     *
     * @param array $generatedImages Images data
     * @return array Statistics
     */
    public function getStatistics(array $generatedImages) {
        $totalImages = 1; // Featured image
        $totalImages += isset($generatedImages['chapter_images']) ? count($generatedImages['chapter_images']) : 0;

        $totalSize = 0;
        if (isset($generatedImages['featured_image']['local_path'])) {
            $totalSize += filesize($generatedImages['featured_image']['local_path']);
        }

        if (isset($generatedImages['chapter_images'])) {
            foreach ($generatedImages['chapter_images'] as $image) {
                if (isset($image['local_path']) && file_exists($image['local_path'])) {
                    $totalSize += filesize($image['local_path']);
                }
            }
        }

        return [
            'total_images' => $totalImages,
            'featured_images' => 1,
            'chapter_images' => isset($generatedImages['chapter_images']) ? count($generatedImages['chapter_images']) : 0,
            'total_size_mb' => round($totalSize / (1024 * 1024), 2),
            'total_cost' => $generatedImages['total_cost'] ?? 0,
            'average_cost_per_image' => $totalImages > 0 ? round(($generatedImages['total_cost'] ?? 0) / $totalImages, 4) : 0
        ];
    }

    /**
     * Set temporary directory for images
     *
     * @param string $dir Directory path
     */
    public function setTempDir($dir) {
        $this->tempDir = $dir;

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Get temporary directory path
     *
     * @return string Directory path
     */
    public function getTempDir() {
        return $this->tempDir;
    }
}
