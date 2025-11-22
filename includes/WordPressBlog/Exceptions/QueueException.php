<?php
/**
 * Queue Exception
 *
 * Thrown when queue operations fail (e.g., article not found, invalid status transition).
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class QueueException extends WordPressBlogException {
    protected $retryable = false; // Queue errors typically are not retryable

    public function getUserMessage() {
        return 'Queue error: ' . $this->getMessage();
    }
}
