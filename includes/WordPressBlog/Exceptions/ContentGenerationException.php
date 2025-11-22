<?php
/**
 * Content Generation Exception
 *
 * Thrown when content generation fails (OpenAI API errors, generation timeouts).
 * Often retryable for transient API issues.
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class ContentGenerationException extends WordPressBlogException {
    protected $retryable = true; // Content generation can often be retried

    public function getUserMessage() {
        return 'Content generation failed: ' . $this->getMessage();
    }

    /**
     * Determine if exception is retryable based on error type
     *
     * @param string $errorType Error type (e.g., 'rate_limit', 'timeout', 'api_error')
     * @return self
     */
    public function setErrorType($errorType) {
        $this->addContext('error_type', $errorType);

        // Non-retryable errors
        $nonRetryable = ['invalid_api_key', 'insufficient_quota', 'content_policy_violation'];

        if (in_array($errorType, $nonRetryable)) {
            $this->retryable = false;
        }

        return $this;
    }
}
