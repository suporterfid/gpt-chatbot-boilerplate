<?php
/**
 * SecureFileUpload
 * 
 * Secure temporary file handling for uploaded files.
 * Prevents path traversal, race conditions, and ensures proper cleanup.
 * 
 * @package GPT_Chatbot
 */

class SecureFileUpload {
    /**
     * @var string Upload directory path
     */
    private string $uploadDir;
    
    /**
     * Constructor
     * 
     * @param string $uploadDir Directory for temporary uploads (should be outside web root)
     */
    public function __construct(string $uploadDir) {
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->ensureSecureDirectory();
    }
    
    /**
     * Ensure upload directory exists and is secure
     * 
     * @throws Exception If directory cannot be created or secured
     */
    private function ensureSecureDirectory(): void {
        // Create directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0700, true)) {
                throw new Exception('Failed to create upload directory', 500);
            }
        }
        
        // Verify directory is writable
        if (!is_writable($this->uploadDir)) {
            throw new Exception('Upload directory is not writable', 500);
        }
        
        // Create .htaccess to deny web access (Apache)
        $htaccess = $this->uploadDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $htaccessContent = "# Security: Deny all web access\n";
            $htaccessContent .= "Require all denied\n";
            $htaccessContent .= "Deny from all\n";
            
            if (@file_put_contents($htaccess, $htaccessContent) === false) {
                error_log("Warning: Failed to create .htaccess in upload directory");
            }
        }
        
        // Create index.php to prevent directory listing
        $index = $this->uploadDir . '/index.php';
        if (!file_exists($index)) {
            $indexContent = "<?php\n// Security: Deny directory access\nheader('HTTP/1.0 403 Forbidden');\nexit('Access denied');\n";
            
            if (@file_put_contents($index, $indexContent) === false) {
                error_log("Warning: Failed to create index.php in upload directory");
            }
        }
    }
    
    /**
     * Create a temporary file with secure random name
     * 
     * @param string $content File content
     * @param string $originalFilename Original filename (for logging/tracking)
     * @return string Path to created temporary file
     * @throws Exception If file creation fails
     */
    public function createTempFile(string $content, string $originalFilename): string {
        // Generate cryptographically secure random filename
        $randomName = bin2hex(random_bytes(16));
        
        // Sanitize original filename for use in temp name
        $sanitizedName = $this->sanitizeFilename($originalFilename);
        
        // Construct full path
        $tempPath = $this->uploadDir . '/' . $randomName . '_' . $sanitizedName;
        
        // Ensure file doesn't already exist (should be statistically impossible)
        if (file_exists($tempPath)) {
            error_log("SECURITY WARNING: Random filename collision detected: {$tempPath}");
            throw new Exception('Failed to create unique temporary file', 500);
        }
        
        // Create file with exclusive lock (fails if file exists)
        $fp = @fopen($tempPath, 'x');
        if ($fp === false) {
            throw new Exception('Failed to create temporary file', 500);
        }
        
        try {
            // Acquire exclusive lock
            if (!flock($fp, LOCK_EX)) {
                throw new Exception('Failed to lock temporary file', 500);
            }
            
            // Write content
            $written = fwrite($fp, $content);
            if ($written === false || $written !== strlen($content)) {
                throw new Exception('Failed to write file content', 500);
            }
            
            // Release lock
            flock($fp, LOCK_UN);
            
        } finally {
            fclose($fp);
        }
        
        // Set restrictive permissions (owner read/write only)
        if (!chmod($tempPath, 0600)) {
            error_log("Warning: Failed to set restrictive permissions on {$tempPath}");
        }
        
        return $tempPath;
    }
    
    /**
     * Cleanup temporary file securely
     * 
     * Overwrites content with random data before deletion to prevent recovery
     * 
     * @param string $path Path to temporary file
     * @return bool True if cleanup succeeded
     */
    public function cleanupTempFile(string $path): bool {
        if (!file_exists($path)) {
            return true; // Already cleaned up
        }
        
        // Verify path is within upload directory (security check)
        $realPath = realpath($path);
        $realUploadDir = realpath($this->uploadDir);
        
        if ($realPath === false || $realUploadDir === false) {
            error_log("Failed to resolve real paths for cleanup: {$path}");
            return false;
        }
        
        if (strpos($realPath, $realUploadDir) !== 0) {
            error_log("SECURITY WARNING: Attempt to delete file outside upload directory: {$path}");
            return false;
        }
        
        try {
            // Overwrite with random data before deletion
            $size = filesize($path);
            if ($size > 0) {
                $fp = @fopen($path, 'r+');
                if ($fp !== false) {
                    fseek($fp, 0);
                    fwrite($fp, random_bytes($size));
                    fclose($fp);
                }
            }
            
            // Delete file
            return @unlink($path);
            
        } catch (Exception $e) {
            error_log("Failed to cleanup temporary file {$path}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize filename for use in temporary file path
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private function sanitizeFilename(string $filename): string {
        // Remove path components
        $filename = basename($filename);
        
        // Remove potentially dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 100) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $basename = substr($basename, 0, 100 - strlen($extension) - 1);
            $filename = $basename . '.' . $extension;
        }
        
        return $filename;
    }
    
    /**
     * Get upload directory path
     * 
     * @return string Upload directory path
     */
    public function getUploadDir(): string {
        return $this->uploadDir;
    }
    
    /**
     * Clean up old temporary files (for maintenance)
     * 
     * Removes files older than specified age
     * 
     * @param int $maxAge Maximum age in seconds (default: 1 hour)
     * @return int Number of files cleaned up
     */
    public function cleanupOldFiles(int $maxAge = 3600): int {
        $count = 0;
        $cutoffTime = time() - $maxAge;
        
        try {
            $files = glob($this->uploadDir . '/*');
            if ($files === false) {
                return 0;
            }
            
            foreach ($files as $file) {
                // Skip directories and hidden files
                if (!is_file($file) || basename($file)[0] === '.') {
                    continue;
                }
                
                // Check file age
                $mtime = filemtime($file);
                if ($mtime !== false && $mtime < $cutoffTime) {
                    if ($this->cleanupTempFile($file)) {
                        $count++;
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error during old files cleanup: " . $e->getMessage());
        }
        
        return $count;
    }
}
