<?php
/**
 * WordPress Blog Base Exception
 *
 * Base exception class for all WordPress Blog related exceptions.
 * Provides common functionality for error context, retry logic classification,
 * and error reporting.
 *
 * @package WordPressBlog\Exceptions
 */

class WordPressBlogException extends Exception {
    /**
     * Additional error context
     *
     * @var array
     */
    protected $context = [];

    /**
     * Whether this error is retryable
     *
     * @var bool
     */
    protected $retryable = false;

    /**
     * HTTP status code (if applicable)
     *
     * @var int|null
     */
    protected $httpStatusCode = null;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param Throwable|null $previous Previous exception
     * @param array $context Additional context
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null, array $context = []) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get error context
     *
     * @return array Context data
     */
    public function getContext() {
        return $this->context;
    }

    /**
     * Set error context
     *
     * @param array $context Context data
     * @return self
     */
    public function setContext(array $context) {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context item
     *
     * @param string $key Context key
     * @param mixed $value Context value
     * @return self
     */
    public function addContext($key, $value) {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Check if error is retryable
     *
     * @return bool Retryable
     */
    public function isRetryable() {
        return $this->retryable;
    }

    /**
     * Set retryable flag
     *
     * @param bool $retryable Retryable
     * @return self
     */
    public function setRetryable($retryable) {
        $this->retryable = $retryable;
        return $this;
    }

    /**
     * Get HTTP status code
     *
     * @return int|null HTTP status code
     */
    public function getHttpStatusCode() {
        return $this->httpStatusCode;
    }

    /**
     * Set HTTP status code
     *
     * @param int $code HTTP status code
     * @return self
     */
    public function setHttpStatusCode($code) {
        $this->httpStatusCode = $code;
        return $this;
    }

    /**
     * Get full error details
     *
     * @return array Error details
     */
    public function toArray() {
        return [
            'exception' => get_class($this),
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'retryable' => $this->retryable,
            'http_status_code' => $this->httpStatusCode,
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Get JSON representation
     *
     * @return string JSON string
     */
    public function toJson() {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Get user-friendly error message
     *
     * @return string User message
     */
    public function getUserMessage() {
        return $this->getMessage();
    }
}
