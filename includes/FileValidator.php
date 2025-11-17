<?php
/**
 * FileValidator
 * 
 * Comprehensive file validation and security checks for uploaded files.
 * Prevents remote code execution, MIME type spoofing, and malware uploads.
 * 
 * @package GPT_Chatbot
 */

require_once __DIR__ . '/SecurityValidator.php';

class FileValidator {
    /**
     * MIME type mapping for allowed file extensions
     */
    private const MIME_TYPE_MAP = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'csv' => 'text/csv',
        'json' => 'application/json',
        'xml' => 'application/xml',
    ];
    
    /**
     * Malware signatures to detect in file content
     */
    private const MALWARE_SIGNATURES = [
        '<?php',
        '<%',
        '<script',
        'eval(',
        'base64_decode(',
        'system(',
        'exec(',
        'passthru(',
        'shell_exec(',
        'popen(',
        'proc_open(',
        'pcntl_exec(',
        'assert(',
        'preg_replace.*\/e',
        'create_function',
        'include(',
        'require(',
        '__halt_compiler',
        'call_user_func',
    ];
    
    /**
     * Validate uploaded file data comprehensively
     * 
     * @param array $fileData File data containing 'name', 'type', 'data', 'size'
     * @param array $config Configuration with max_file_size, allowed_file_types
     * @return string Decoded file content (safe to use)
     * @throws Exception If validation fails
     */
    public function validateFile(array $fileData, array $config): string {
        // 1. Validate required fields
        if (!isset($fileData['name']) || !isset($fileData['type']) || !isset($fileData['data'])) {
            throw new Exception('Invalid file data format: missing required fields', 400);
        }
        
        // 2. Validate filename
        $filename = SecurityValidator::validateFilename($fileData['name']);
        if ($filename === null) {
            throw new Exception('Invalid filename', 400);
        }
        
        // 3. Validate size before decoding
        $encodedSize = strlen($fileData['data']);
        $maxEncodedSize = $config['max_file_size'] * 1.37; // Base64 overhead ~37%
        
        if ($encodedSize > $maxEncodedSize) {
            throw new Exception('Encoded file size exceeds limit', 400);
        }
        
        // 4. Decode and validate content
        $content = $this->decodeFileData($fileData['data']);
        
        // 5. Validate actual size
        $actualSize = strlen($content);
        if ($actualSize > $config['max_file_size']) {
            throw new Exception('File size exceeds limit', 400);
        }
        
        // 6. Verify user-provided size matches (if provided)
        if (isset($fileData['size']) && abs($actualSize - $fileData['size']) > 1024) {
            // Allow 1KB tolerance for encoding differences
            error_log("File size mismatch: declared={$fileData['size']}, actual={$actualSize}");
        }
        
        // 7. Validate MIME type from content (magic bytes)
        $this->validateMimeType($content, $fileData['type'], $filename, $config);
        
        // 8. Scan for malware signatures
        $this->scanForMalware($content, $filename);
        
        return $content;
    }
    
    /**
     * Decode base64 file data safely
     * 
     * @param string $data Base64 encoded data
     * @return string Decoded content
     * @throws Exception If decoding fails
     */
    private function decodeFileData(string $data): string {
        $decoded = base64_decode($data, true);
        
        if ($decoded === false) {
            throw new Exception('Invalid base64 encoding', 400);
        }
        
        return $decoded;
    }
    
    /**
     * Validate MIME type using file content (magic bytes)
     * 
     * @param string $content File content
     * @param string $declaredType User-declared MIME type
     * @param string $filename Filename with extension
     * @param array $config Configuration
     * @throws Exception If MIME type is not allowed
     */
    private function validateMimeType(string $content, string $declaredType, string $filename, array $config): void {
        // Get actual MIME type from content using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new Exception('Failed to initialize MIME type detection', 500);
        }
        
        $actualType = finfo_buffer($finfo, $content);
        finfo_close($finfo);
        
        if ($actualType === false) {
            throw new Exception('Failed to detect MIME type', 500);
        }
        
        // Get allowed MIME types based on configuration
        $allowedTypes = $this->getAllowedMimeTypes($config);
        
        // Check if actual type is allowed
        if (!in_array($actualType, $allowedTypes, true)) {
            error_log("File upload rejected: MIME type not allowed. File={$filename}, Declared={$declaredType}, Actual={$actualType}");
            throw new Exception("File type not allowed: {$actualType}", 400);
        }
        
        // Log if declared type doesn't match actual type (potential spoofing attempt)
        if ($declaredType !== $actualType) {
            error_log("MIME type mismatch: declared={$declaredType}, actual={$actualType}, file={$filename}");
        }
        
        // Validate extension matches MIME type
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $expectedMime = self::MIME_TYPE_MAP[$extension] ?? null;
        
        if ($expectedMime !== null && $actualType !== $expectedMime) {
            // Some exceptions for MIME type variations
            $this->checkMimeTypeException($actualType, $expectedMime, $extension);
        }
    }
    
    /**
     * Check if MIME type variation is acceptable
     * 
     * @param string $actualType Actual MIME type
     * @param string $expectedMime Expected MIME type
     * @param string $extension File extension
     * @throws Exception If MIME type doesn't match extension
     */
    private function checkMimeTypeException(string $actualType, string $expectedMime, string $extension): void {
        // Allow common MIME type variations
        $exceptions = [
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'jpg' => ['image/jpeg', 'image/jpg'],
            'txt' => ['text/plain', 'text/x-plain'],
            'csv' => ['text/csv', 'text/plain'],
            'json' => ['application/json', 'text/plain'],
        ];
        
        if (isset($exceptions[$extension])) {
            if (in_array($actualType, $exceptions[$extension], true)) {
                return; // Acceptable variation
            }
        }
        
        // Not an acceptable variation
        throw new Exception("File extension '{$extension}' does not match content type '{$actualType}'", 400);
    }
    
    /**
     * Get allowed MIME types based on configuration
     * 
     * @param array $config Configuration
     * @return array List of allowed MIME types
     */
    private function getAllowedMimeTypes(array $config): array {
        $allowedExtensions = $config['allowed_file_types'] ?? [];
        $allowedMimes = [];
        
        foreach ($allowedExtensions as $ext) {
            $ext = strtolower(trim($ext));
            if (isset(self::MIME_TYPE_MAP[$ext])) {
                $allowedMimes[] = self::MIME_TYPE_MAP[$ext];
            }
        }
        
        // Remove duplicates
        return array_unique($allowedMimes);
    }
    
    /**
     * Scan file content for malware signatures
     * 
     * @param string $content File content
     * @param string $filename Filename for logging
     * @throws Exception If malware detected
     */
    private function scanForMalware(string $content, string $filename): void {
        // Check for common malware signatures
        foreach (self::MALWARE_SIGNATURES as $signature) {
            // Case-insensitive search for PHP and script tags
            if (stripos($content, $signature) !== false) {
                error_log("SECURITY ALERT: Malware signature detected in file: {$filename}, signature: {$signature}");
                throw new Exception('Potentially malicious content detected in file', 400);
            }
        }
        
        // Check for executable file headers (ELF, PE, Mach-O)
        $header = substr($content, 0, 4);
        
        // ELF header (Linux executables)
        if ($header === "\x7FELF") {
            throw new Exception('Executable file detected (ELF)', 400);
        }
        
        // PE header (Windows executables)
        if (substr($content, 0, 2) === "MZ") {
            throw new Exception('Executable file detected (PE/Windows)', 400);
        }
        
        // Mach-O header (macOS executables)
        if (in_array($header, ["\xFE\xED\xFA\xCE", "\xFE\xED\xFA\xCF", "\xCE\xFA\xED\xFE", "\xCF\xFA\xED\xFE"], true)) {
            throw new Exception('Executable file detected (Mach-O)', 400);
        }
        
        // Check for HTML/JavaScript in non-HTML files
        if (preg_match('/<(html|body|head|iframe|object|embed)/i', $content)) {
            throw new Exception('HTML content detected in non-HTML file', 400);
        }
    }
    
    /**
     * Get the actual MIME type for a file extension
     * 
     * @param string $extension File extension
     * @return string|null MIME type or null if not found
     */
    public static function getMimeTypeForExtension(string $extension): ?string {
        $extension = strtolower(trim($extension));
        return self::MIME_TYPE_MAP[$extension] ?? null;
    }
    
    /**
     * Check if an extension is allowed
     * 
     * @param string $extension File extension
     * @param array $config Configuration
     * @return bool True if allowed
     */
    public static function isExtensionAllowed(string $extension, array $config): bool {
        $allowedExtensions = $config['allowed_file_types'] ?? [];
        $extension = strtolower(trim($extension));
        
        return in_array($extension, $allowedExtensions, true);
    }
}
