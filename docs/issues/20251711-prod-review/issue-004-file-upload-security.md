# Issue 004: Insecure File Upload Implementation

**Category:** Security  
**Severity:** Critical  
**Priority:** Critical  
**File:** `includes/ChatHandler.php`, `includes/OpenAIClient.php`

## Problem Description

The file upload implementation has several security vulnerabilities that could lead to remote code execution (RCE), path traversal, or denial of service attacks.

## Vulnerable Code

### ChatHandler.php (lines 2046-2065)

```php
private function validateFileData($fileData) {
    if (!is_array($fileData)) {
        $fileData = [$fileData];
    }

    foreach ($fileData as $file) {
        if (!isset($file['data']) || !isset($file['type'])) {
            throw new Exception('Invalid file data format', 400);
        }

        if ($file['size'] > $this->config['chat_config']['max_file_size']) {
            throw new Exception('File size exceeds limit', 400);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $this->config['chat_config']['allowed_file_types'])) {
            throw new Exception('File type not allowed', 400);
        }
    }
}
```

### OpenAIClient.php (lines 246-293)

```php
public function uploadFile($fileData, $purpose = 'user_data') {
    $ch = curl_init();

    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'chatbot_upload_');
    file_put_contents($tempFile, base64_decode($fileData['data']));

    $postFields = [
        'purpose' => $purpose,
        'file' => new CURLFile($tempFile, $fileData['type'], $fileData['name'])
    ];
    
    // ... upload to OpenAI ...
    
    // Clean up temp file
    unlink($tempFile);
}
```

## Critical Security Issues

### 1. MIME Type Validation Bypass

**Problem**: Only checking file extension, not actual file content

```php
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!in_array(strtolower($extension), $this->config['chat_config']['allowed_file_types'])) {
```

**Attack**: Rename `malicious.php` to `malicious.pdf` - will bypass validation

### 2. Path Traversal in Temporary Files

**Problem**: Predictable temporary file names and no path sanitization

```php
$tempFile = tempnam(sys_get_temp_dir(), 'chatbot_upload_');
```

**Attack**: Race condition - attacker could predict filename and create symlink

### 3. No Magic Byte Verification

**Problem**: Trust user-provided MIME type without verification

```php
$postFields = [
    'purpose' => $purpose,
    'file' => new CURLFile($tempFile, $fileData['type'], $fileData['name'])
];
```

**Attack**: Upload executable file disguised as PDF

### 4. Double Extension Attack

**Problem**: Only checking last extension

```php
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
```

**Attack**: Filename like `malicious.pdf.php` may bypass some checks

### 5. No File Content Scanning

**Problem**: No virus/malware scanning before processing

### 6. Insufficient Size Validation

**Problem**: Size check uses user-provided value

```php
if ($file['size'] > $this->config['chat_config']['max_file_size']) {
```

**Attack**: Provide fake size value, upload larger file

### 7. Temporary File Cleanup Race Condition

**Problem**: File exists in temp directory before upload completes

```php
file_put_contents($tempFile, base64_decode($fileData['data']));
// File is accessible here!
$postFields = [...];
```

**Attack**: Access temporary file before cleanup

## Attack Scenarios

### Scenario 1: Remote Code Execution (if files served locally)

```javascript
// Attacker uploads PHP file disguised as PDF
POST /chat-unified.php
{
  "message": "Process this file",
  "file_data": [{
    "name": "invoice.pdf",
    "type": "application/pdf",
    "size": 1024,
    "data": "<base64 encoded PHP backdoor>"
  }]
}
```

If temporary files are in web-accessible directory and not cleaned up properly, attacker could execute code.

### Scenario 2: Zip Bomb / Decompression Bomb

```javascript
// Upload compressed file that expands to huge size
POST /chat-unified.php
{
  "message": "Analyze this",
  "file_data": [{
    "name": "data.zip",
    "type": "application/zip",
    "size": 50000,  // Lies about size
    "data": "<base64 of zip bomb>"  // Actually 10GB when extracted
  }]
}
```

### Scenario 3: Path Traversal

```javascript
// Attempt directory traversal in filename
POST /chat-unified.php
{
  "message": "Check this",
  "file_data": [{
    "name": "../../../etc/passwd",
    "type": "text/plain",
    "size": 100,
    "data": "..."
  }]
}
```

## Impact

- **Critical**: Remote Code Execution if temporary files accessible
- **Critical**: Server compromise through malicious file uploads
- **High**: Denial of Service through large files or zip bombs
- **High**: Data exfiltration through path traversal
- **Medium**: Resource exhaustion

## Recommendations

### 1. Implement Comprehensive MIME Type Validation

```php
class FileValidator {
    /**
     * Validate file using multiple methods
     */
    public function validateFile(array $fileData, array $config): void {
        // 1. Validate filename
        $this->validateFilename($fileData['name']);
        
        // 2. Validate size (before decoding!)
        $this->validateSize($fileData, $config);
        
        // 3. Decode and validate content
        $content = $this->decodeFileData($fileData['data']);
        
        // 4. Verify MIME type from content (magic bytes)
        $this->validateMimeType($content, $fileData['type'], $config);
        
        // 5. Scan for malware (if scanner available)
        $this->scanForMalware($content);
    }
    
    private function validateFilename(string $filename): void {
        // Sanitize filename
        $filename = basename($filename); // Remove directory components
        
        // Check for path traversal attempts
        if (strpos($filename, '..') !== false) {
            throw new Exception('Invalid filename: path traversal detected', 400);
        }
        
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            throw new Exception('Invalid filename: null byte detected', 400);
        }
        
        // Check for multiple extensions
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            throw new Exception('Invalid filename: multiple extensions not allowed', 400);
        }
        
        // Validate characters
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            throw new Exception('Invalid filename: contains invalid characters', 400);
        }
        
        // Validate length
        if (strlen($filename) > 255) {
            throw new Exception('Filename too long', 400);
        }
    }
    
    private function validateSize(array $fileData, array $config): void {
        // Validate encoded size first
        $encodedSize = strlen($fileData['data']);
        $maxEncoded = $config['max_file_size'] * 1.37; // Base64 overhead
        
        if ($encodedSize > $maxEncoded) {
            throw new Exception('Encoded file size exceeds limit', 400);
        }
        
        // Decode and check actual size
        $decoded = base64_decode($fileData['data'], true);
        if ($decoded === false) {
            throw new Exception('Invalid base64 encoding', 400);
        }
        
        $actualSize = strlen($decoded);
        if ($actualSize > $config['max_file_size']) {
            throw new Exception('File size exceeds limit', 400);
        }
        
        // Verify user-provided size matches
        if (isset($fileData['size']) && abs($actualSize - $fileData['size']) > 1024) {
            throw new Exception('Declared size mismatch', 400);
        }
    }
    
    private function validateMimeType(string $content, string $declaredType, array $config): void {
        // Use finfo to detect actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualType = finfo_buffer($finfo, $content);
        finfo_close($finfo);
        
        // Check if actual type is allowed
        $allowedTypes = $this->getAllowedMimeTypes($config);
        if (!in_array($actualType, $allowedTypes, true)) {
            throw new Exception("File type not allowed: $actualType", 400);
        }
        
        // Verify declared type matches actual type
        if ($declaredType !== $actualType) {
            error_log("MIME type mismatch: declared=$declaredType, actual=$actualType");
            // Proceed with actual type, but log the discrepancy
        }
    }
    
    private function getAllowedMimeTypes(array $config): array {
        $extensions = $config['allowed_file_types'] ?? [];
        $mimeMap = [
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];
        
        $allowed = [];
        foreach ($extensions as $ext) {
            if (isset($mimeMap[$ext])) {
                $allowed[] = $mimeMap[$ext];
            }
        }
        
        return $allowed;
    }
    
    private function scanForMalware(string $content): void {
        // Integrate with ClamAV or similar if available
        if (extension_loaded('clamav')) {
            $result = clamav_scan_buffer($content);
            if ($result !== CL_CLEAN) {
                throw new Exception('Malware detected in uploaded file', 400);
            }
        }
        
        // Basic signature detection for known malware patterns
        $this->detectMalwareSignatures($content);
    }
    
    private function detectMalwareSignatures(string $content): void {
        // Check for common malware signatures
        $signatures = [
            '<?php',           // PHP code
            '<script',         // JavaScript
            'eval(',           // Eval function
            'base64_decode(',  // Obfuscation
            'system(',         // System commands
            'exec(',           // Command execution
            'passthru(',       // Command execution
            'shell_exec(',     // Shell execution
        ];
        
        foreach ($signatures as $signature) {
            if (stripos($content, $signature) !== false) {
                throw new Exception('Potentially malicious content detected', 400);
            }
        }
    }
}
```

### 2. Secure Temporary File Handling

```php
class SecureFileUpload {
    private string $uploadDir;
    
    public function __construct(string $uploadDir) {
        // Store files outside web root
        $this->uploadDir = $uploadDir;
        
        // Ensure directory exists and has restrictive permissions
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0700, true);
        }
        
        // Create .htaccess to deny web access
        $htaccess = $this->uploadDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }
    
    public function createTempFile(string $content, string $filename): string {
        // Generate cryptographically secure random filename
        $randomName = bin2hex(random_bytes(16));
        $sanitizedName = $this->sanitizeFilename($filename);
        $tempPath = $this->uploadDir . '/' . $randomName . '_' . $sanitizedName;
        
        // Write with exclusive lock
        $fp = fopen($tempPath, 'x'); // 'x' fails if file exists
        if ($fp === false) {
            throw new Exception('Failed to create temporary file', 500);
        }
        
        flock($fp, LOCK_EX);
        fwrite($fp, $content);
        flock($fp, LOCK_UN);
        fclose($fp);
        
        // Set restrictive permissions
        chmod($tempPath, 0600);
        
        return $tempPath;
    }
    
    public function cleanupTempFile(string $path): void {
        if (file_exists($path)) {
            // Secure delete: overwrite with random data
            $fp = fopen($path, 'r+');
            if ($fp) {
                $size = filesize($path);
                fseek($fp, 0);
                fwrite($fp, random_bytes($size));
                fclose($fp);
            }
            unlink($path);
        }
    }
    
    private function sanitizeFilename(string $filename): string {
        // Remove path components
        $filename = basename($filename);
        
        // Remove potentially dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }
        
        return $filename;
    }
}
```

### 3. Update OpenAIClient.php

```php
public function uploadFile($fileData, $purpose = 'user_data') {
    // Validate file first
    $validator = new FileValidator();
    $validator->validateFile($fileData, $this->config);
    
    // Decode content
    $content = base64_decode($fileData['data'], true);
    if ($content === false) {
        throw new Exception('Invalid base64 encoding', 400);
    }
    
    // Create secure temporary file
    $secureUpload = new SecureFileUpload('/var/chatbot/temp/uploads');
    $tempFile = $secureUpload->createTempFile($content, $fileData['name']);
    
    try {
        // Get actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMimeType = finfo_file($finfo, $tempFile);
        finfo_close($finfo);
        
        // Upload to OpenAI
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/files',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'purpose' => $purpose,
                'file' => new CURLFile($tempFile, $actualMimeType, basename($fileData['name']))
            ],
            CURLOPT_HTTPHEADER => $this->getHeaders(),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('File upload error: HTTP ' . $httpCode);
        }
        
        $response = json_decode($result, true);
        return $response['id'] ?? null;
        
    } finally {
        // Always cleanup, even on exception
        $secureUpload->cleanupTempFile($tempFile);
    }
}
```

### 4. Configuration Updates

```php
// In config.php
$config['chat_config']['max_file_size'] = 10 * 1024 * 1024; // 10MB
$config['chat_config']['allowed_file_types'] = ['pdf', 'txt', 'jpg', 'png'];

// Add MIME type whitelist
$config['chat_config']['allowed_mime_types'] = [
    'application/pdf',
    'text/plain',
    'image/jpeg',
    'image/png',
];

// Secure upload directory (outside web root!)
$config['chat_config']['upload_dir'] = '/var/chatbot/uploads';

// Enable malware scanning if available
$config['chat_config']['enable_malware_scan'] = extension_loaded('clamav');
```

## Testing Requirements

```php
// Security test cases
class FileUploadSecurityTest {
    public function testMaliciousPhpUpload() {
        $phpBackdoor = '<?php system($_GET["cmd"]); ?>';
        $fileData = [
            'name' => 'invoice.pdf',
            'type' => 'application/pdf',
            'size' => strlen($phpBackdoor),
            'data' => base64_encode($phpBackdoor)
        ];
        
        try {
            $validator = new FileValidator();
            $validator->validateFile($fileData, $config);
            throw new Exception('SECURITY: PHP code not detected!');
        } catch (Exception $e) {
            if ($e->getMessage() !== 'Potentially malicious content detected') {
                throw new Exception('SECURITY: Wrong exception: ' . $e->getMessage());
            }
            echo "✓ PHP upload blocked\n";
        }
    }
    
    public function testPathTraversal() {
        $fileData = [
            'name' => '../../../etc/passwd',
            'type' => 'text/plain',
            'size' => 100,
            'data' => base64_encode('test')
        ];
        
        try {
            $validator = new FileValidator();
            $validator->validateFile($fileData, $config);
            throw new Exception('SECURITY: Path traversal not blocked!');
        } catch (Exception $e) {
            echo "✓ Path traversal blocked\n";
        }
    }
    
    public function testMimeTypeSpoofing() {
        // Create fake PDF (actually HTML)
        $html = '<html><body>Not a PDF</body></html>';
        $fileData = [
            'name' => 'document.pdf',
            'type' => 'application/pdf', // Lie about type
            'size' => strlen($html),
            'data' => base64_encode($html)
        ];
        
        try {
            $validator = new FileValidator();
            $validator->validateFile($fileData, $config);
            throw new Exception('SECURITY: MIME spoofing not detected!');
        } catch (Exception $e) {
            echo "✓ MIME spoofing detected\n";
        }
    }
}
```

## Estimated Effort

- **Effort:** 2-3 days
- **Risk:** Medium (requires careful testing to avoid breaking legitimate uploads)

## Related Issues

- Issue 002: SQL injection
- Issue 005: Temporary file cleanup
- Issue 017: Resource limits and DoS protection
