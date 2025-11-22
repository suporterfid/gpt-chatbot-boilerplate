<?php
/**
 * Image Generation Exception
 *
 * Thrown when image generation fails (DALL-E API errors, download failures).
 * Often retryable for transient API issues.
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class ImageGenerationException extends WordPressBlogException {
    protected $retryable = true; // Image generation can often be retried

    public function getUserMessage() {
        return 'Image generation failed: ' . $this->getMessage();
    }

    /**
     * Set image type context
     *
     * @param string $imageType Image type (e.g., 'featured', 'chapter')
     * @param int|null $chapterNumber Chapter number if applicable
     * @return self
     */
    public function setImageContext($imageType, $chapterNumber = null) {
        $this->addContext('image_type', $imageType);
        if ($chapterNumber !== null) {
            $this->addContext('chapter_number', $chapterNumber);
        }
        return $this;
    }
}
