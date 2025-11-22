<?php
/**
 * WordPress Publish Exception
 *
 * Thrown when publishing to WordPress fails (API errors, authentication failures).
 * Retryable for transient network issues.
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class WordPressPublishException extends WordPressBlogException {
    protected $retryable = true; // Publishing can often be retried

    public function getUserMessage() {
        return 'WordPress publishing failed: ' . $this->getMessage();
    }

    /**
     * Set WordPress response context
     *
     * @param int|null $postId WordPress post ID if created
     * @param int|null $httpCode HTTP status code
     * @return self
     */
    public function setWordPressContext($postId = null, $httpCode = null) {
        if ($postId !== null) {
            $this->addContext('post_id', $postId);
        }
        if ($httpCode !== null) {
            $this->setHttpStatusCode($httpCode);

            // Authentication errors are not retryable
            if (in_array($httpCode, [401, 403])) {
                $this->retryable = false;
            }
        }
        return $this;
    }
}
