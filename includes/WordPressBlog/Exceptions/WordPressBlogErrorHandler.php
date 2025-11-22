<?php
/**
 * WordPress Blog Error Handler
 *
 * Centralized error handling with:
 * - Retry logic with exponential backoff
 * - Error classification (retryable vs non-retryable)
 * - Error logging and reporting
 * - Rate limit detection
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class WordPressBlogErrorHandler {
    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private $maxRetries = 3;

    /**
     * Base delay for exponential backoff (seconds)
     *
     * @var int
     */
    private $baseDelay = 2;

    /**
     * Logger instance (optional)
     *
     * @var object|null
     */
    private $logger = null;

    /**
     * Constructor
     *
     * @param int $maxRetries Maximum retry attempts
     * @param int $baseDelay Base delay in seconds
     * @param object|null $logger Logger instance
     */
    public function __construct($maxRetries = 3, $baseDelay = 2, $logger = null) {
        $this->maxRetries = $maxRetries;
        $this->baseDelay = $baseDelay;
        $this->logger = $logger;
    }

    /**
     * Execute callable with retry logic
     *
     * @param callable $callable Function to execute
     * @param array $args Arguments for the callable
     * @param string $operationName Operation name for logging
     * @return mixed Result from callable
     * @throws WordPressBlogException If all retries exhausted
     */
    public function executeWithRetry(callable $callable, array $args = [], $operationName = 'operation') {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $this->log("Attempting {$operationName} (attempt {$attempt}/{$this->maxRetries})", 'info');

                $result = call_user_func_array($callable, $args);

                if ($attempt > 1) {
                    $this->log("{$operationName} succeeded on attempt {$attempt}", 'info');
                }

                return $result;

            } catch (WordPressBlogException $e) {
                $lastException = $e;

                $this->log(
                    "{$operationName} failed on attempt {$attempt}: " . $e->getMessage(),
                    'error',
                    ['exception' => get_class($e), 'context' => $e->getContext()]
                );

                // Check if error is retryable
                if (!$e->isRetryable()) {
                    $this->log("{$operationName} error is not retryable, aborting", 'error');
                    throw $e;
                }

                // Check if we should retry
                if ($attempt < $this->maxRetries) {
                    $delay = $this->calculateBackoff($attempt);
                    $this->log("Waiting {$delay} seconds before retry...", 'info');
                    sleep($delay);
                } else {
                    $this->log("{$operationName} exhausted all {$this->maxRetries} retries", 'error');
                    throw $e;
                }

            } catch (Exception $e) {
                // Convert generic exceptions to WordPressBlogException
                $wrappedException = new WordPressBlogException(
                    $e->getMessage(),
                    $e->getCode(),
                    $e,
                    ['original_exception' => get_class($e)]
                );

                $lastException = $wrappedException;

                $this->log(
                    "{$operationName} failed with unexpected error: " . $e->getMessage(),
                    'error',
                    ['exception' => get_class($e)]
                );

                // Don't retry unexpected exceptions by default
                throw $wrappedException;
            }
        }

        // This should never be reached, but just in case
        throw $lastException;
    }

    /**
     * Calculate exponential backoff delay
     *
     * @param int $attempt Attempt number (1-based)
     * @return int Delay in seconds
     */
    public function calculateBackoff($attempt) {
        // Exponential backoff: baseDelay * 2^(attempt-1)
        // Attempt 1: 2 * 2^0 = 2s
        // Attempt 2: 2 * 2^1 = 4s
        // Attempt 3: 2 * 2^2 = 8s
        // Attempt 4: 2 * 2^3 = 16s
        $delay = $this->baseDelay * pow(2, $attempt - 1);

        // Cap at 60 seconds
        return min($delay, 60);
    }

    /**
     * Handle exception and determine if retryable
     *
     * @param Exception $exception Exception to handle
     * @return array Handling result with retryable flag and suggested delay
     */
    public function handleException(Exception $exception) {
        $retryable = false;
        $delay = 0;
        $errorType = 'unknown';

        if ($exception instanceof WordPressBlogException) {
            $retryable = $exception->isRetryable();
            $errorType = $this->classifyException($exception);

            // Special handling for rate limits
            if ($this->isRateLimitError($exception)) {
                $retryable = true;
                $delay = 60; // Wait 1 minute for rate limits
                $errorType = 'rate_limit';
            }
        }

        $this->log(
            "Exception handled: " . get_class($exception),
            'info',
            [
                'message' => $exception->getMessage(),
                'retryable' => $retryable,
                'error_type' => $errorType,
                'suggested_delay' => $delay
            ]
        );

        return [
            'retryable' => $retryable,
            'error_type' => $errorType,
            'suggested_delay' => $delay,
            'message' => $exception->getMessage()
        ];
    }

    /**
     * Classify exception type
     *
     * @param WordPressBlogException $exception Exception to classify
     * @return string Error type
     */
    private function classifyException(WordPressBlogException $exception) {
        $class = get_class($exception);
        $message = $exception->getMessage();

        // Check for specific error patterns
        if (stripos($message, 'rate limit') !== false || $exception->getHttpStatusCode() === 429) {
            return 'rate_limit';
        } elseif (stripos($message, 'timeout') !== false) {
            return 'timeout';
        } elseif (stripos($message, 'connection') !== false) {
            return 'connection';
        } elseif (in_array($exception->getHttpStatusCode(), [500, 502, 503, 504])) {
            return 'server_error';
        } elseif (in_array($exception->getHttpStatusCode(), [401, 403])) {
            return 'authentication';
        } elseif ($exception->getHttpStatusCode() === 404) {
            return 'not_found';
        }

        // Classify by exception type
        $mapping = [
            'ConfigurationException' => 'configuration',
            'QueueException' => 'queue',
            'ContentGenerationException' => 'content_generation',
            'ImageGenerationException' => 'image_generation',
            'WordPressPublishException' => 'publishing',
            'StorageException' => 'storage',
            'CredentialException' => 'credentials'
        ];

        foreach ($mapping as $exceptionClass => $type) {
            if (strpos($class, $exceptionClass) !== false) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Check if exception is a rate limit error
     *
     * @param WordPressBlogException $exception Exception to check
     * @return bool Is rate limit error
     */
    private function isRateLimitError(WordPressBlogException $exception) {
        if ($exception->getHttpStatusCode() === 429) {
            return true;
        }

        $message = strtolower($exception->getMessage());
        $rateLimitKeywords = ['rate limit', 'too many requests', 'quota exceeded'];

        foreach ($rateLimitKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return void
     */
    private function log($message, $level = 'info', array $context = []) {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->$level($message, $context);
        } else {
            // Fallback to error_log
            $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
            error_log("[{$level}] {$message}{$contextStr}");
        }
    }

    /**
     * Set logger
     *
     * @param object $logger Logger instance
     * @return self
     */
    public function setLogger($logger) {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Set max retries
     *
     * @param int $maxRetries Maximum retry attempts
     * @return self
     */
    public function setMaxRetries($maxRetries) {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * Set base delay
     *
     * @param int $baseDelay Base delay in seconds
     * @return self
     */
    public function setBaseDelay($baseDelay) {
        $this->baseDelay = $baseDelay;
        return $this;
    }

    /**
     * Get max retries
     *
     * @return int Maximum retries
     */
    public function getMaxRetries() {
        return $this->maxRetries;
    }

    /**
     * Get base delay
     *
     * @return int Base delay
     */
    public function getBaseDelay() {
        return $this->baseDelay;
    }
}
