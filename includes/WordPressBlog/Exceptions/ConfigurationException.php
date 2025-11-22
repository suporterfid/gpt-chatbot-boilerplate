<?php
/**
 * Configuration Exception
 *
 * Thrown when configuration validation fails or configuration-related errors occur.
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class ConfigurationException extends WordPressBlogException {
    protected $retryable = false; // Configuration errors are not retryable

    public function getUserMessage() {
        return 'Configuration error: ' . $this->getMessage();
    }
}
