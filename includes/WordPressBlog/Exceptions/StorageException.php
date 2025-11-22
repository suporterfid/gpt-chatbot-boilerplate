<?php
/**
 * Storage Exception
 *
 * Thrown when storage operations fail (Google Drive uploads, file system errors).
 * Retryable for transient network/storage issues.
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class StorageException extends WordPressBlogException {
    protected $retryable = true; // Storage operations can often be retried

    public function getUserMessage() {
        return 'Storage operation failed: ' . $this->getMessage();
    }

    /**
     * Set storage context
     *
     * @param string $operation Operation type (e.g., 'upload', 'delete', 'create_folder')
     * @param string|null $path File/folder path
     * @return self
     */
    public function setStorageContext($operation, $path = null) {
        $this->addContext('operation', $operation);
        if ($path !== null) {
            $this->addContext('path', $path);
        }
        return $this;
    }
}
