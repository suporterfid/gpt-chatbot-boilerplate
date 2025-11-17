<?php
/**
 * File Upload Security Tests
 * 
 * Tests for Issue #004: File Upload Security vulnerabilities
 * Validates MIME type detection, malware scanning, and secure file handling
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/FileValidator.php';
require_once __DIR__ . '/../includes/SecureFileUpload.php';

echo "\n=== File Upload Security Tests ===\n";

$testsPassed = 0;
$testsFailed = 0;

// Test configuration
$testConfig = [
    'max_file_size' => 1024 * 1024, // 1MB
    'allowed_file_types' => ['txt', 'pdf', 'jpg', 'png'],
];

/**
 * Test 1: Valid text file upload
 */
echo "\n--- Test 1: Valid Text File ---\n";
try {
    $validator = new FileValidator();
    $content = "This is a valid text file.\nIt has multiple lines.";
    $fileData = [
        'name' => 'document.txt',
        'type' => 'text/plain',
        'size' => strlen($content),
        'data' => base64_encode($content)
    ];
    
    $decoded = $validator->validateFile($fileData, $testConfig);
    
    if ($decoded === $content) {
        echo "✓ PASS: Valid text file accepted\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Content mismatch\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Valid file rejected: " . $e->getMessage() . "\n";
    $testsFailed++;
}

/**
 * Test 2: Malicious PHP file disguised as PDF
 */
echo "\n--- Test 2: PHP File Disguised as PDF ---\n";
try {
    $validator = new FileValidator();
    $phpBackdoor = '<?php system($_GET["cmd"]); ?>';
    $fileData = [
        'name' => 'invoice.pdf',
        'type' => 'application/pdf',
        'size' => strlen($phpBackdoor),
        'data' => base64_encode($phpBackdoor)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: PHP backdoor not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    // Accept either MIME type detection or malware signature detection
    if (strpos($e->getMessage(), 'malicious') !== false || strpos($e->getMessage(), 'type not allowed') !== false) {
        echo "✓ PASS: PHP backdoor detected and blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 3: Path traversal in filename
 */
echo "\n--- Test 3: Path Traversal in Filename ---\n";
try {
    $validator = new FileValidator();
    $content = "test content";
    $fileData = [
        'name' => '../../../etc/passwd',
        'type' => 'text/plain',
        'size' => strlen($content),
        'data' => base64_encode($content)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: Path traversal not blocked!\n";
    $testsFailed++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'path') !== false || strpos($e->getMessage(), 'filename') !== false) {
        echo "✓ PASS: Path traversal blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 4: MIME type spoofing (HTML as PDF)
 */
echo "\n--- Test 4: MIME Type Spoofing ---\n";
try {
    $validator = new FileValidator();
    $html = '<html><body>Not a PDF</body></html>';
    $fileData = [
        'name' => 'document.pdf',
        'type' => 'application/pdf',
        'size' => strlen($html),
        'data' => base64_encode($html)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: MIME spoofing not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'type') !== false || strpos($e->getMessage(), 'HTML') !== false) {
        echo "✓ PASS: MIME type spoofing detected\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 5: File size exceeds limit
 */
echo "\n--- Test 5: File Size Exceeds Limit ---\n";
try {
    $validator = new FileValidator();
    $largeContent = str_repeat('A', 2 * 1024 * 1024); // 2MB
    $fileData = [
        'name' => 'large.txt',
        'type' => 'text/plain',
        'size' => strlen($largeContent),
        'data' => base64_encode($largeContent)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: Large file not rejected!\n";
    $testsFailed++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'size') !== false) {
        echo "✓ PASS: Large file rejected\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 6: Double extension attack
 */
echo "\n--- Test 6: Double Extension Attack ---\n";
try {
    $validator = new FileValidator();
    $content = "test";
    $fileData = [
        'name' => 'file.txt.php',
        'type' => 'text/plain',
        'size' => strlen($content),
        'data' => base64_encode($content)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: Double extension not blocked!\n";
    $testsFailed++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'extension') !== false) {
        echo "✓ PASS: Double extension blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 7: Null byte injection
 */
echo "\n--- Test 7: Null Byte Injection ---\n";
try {
    $validator = new FileValidator();
    $content = "test";
    $fileData = [
        'name' => "file.txt\0.php",
        'type' => 'text/plain',
        'size' => strlen($content),
        'data' => base64_encode($content)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: Null byte not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'null') !== false || strpos($e->getMessage(), 'filename') !== false) {
        echo "✓ PASS: Null byte injection blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 8: JavaScript in file content
 */
echo "\n--- Test 8: JavaScript in Content ---\n";
try {
    $validator = new FileValidator();
    $content = '<script>alert("XSS")</script>';
    $fileData = [
        'name' => 'file.txt',
        'type' => 'text/plain',
        'size' => strlen($content),
        'data' => base64_encode($content)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: JavaScript not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    // Accept either MIME type detection (HTML) or malware signature detection
    if (strpos($e->getMessage(), 'malicious') !== false || strpos($e->getMessage(), 'HTML') !== false || strpos($e->getMessage(), 'type not allowed') !== false) {
        echo "✓ PASS: JavaScript detected and blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 9: Executable file (ELF header)
 */
echo "\n--- Test 9: Executable File Detection ---\n";
try {
    $validator = new FileValidator();
    $elfHeader = "\x7FELF\x01\x01\x01\x00"; // ELF header
    $fileData = [
        'name' => 'file.txt',
        'type' => 'text/plain',
        'size' => strlen($elfHeader),
        'data' => base64_encode($elfHeader)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: Executable not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    // Accept either specific executable detection or MIME type rejection
    if (strpos($e->getMessage(), 'Executable') !== false || strpos($e->getMessage(), 'type not allowed') !== false) {
        echo "✓ PASS: Executable file detected and blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 10: Invalid base64 encoding
 */
echo "\n--- Test 10: Invalid Base64 Encoding ---\n";
try {
    $validator = new FileValidator();
    $fileData = [
        'name' => 'file.txt',
        'type' => 'text/plain',
        'size' => 100,
        'data' => 'This is not valid base64!!!'
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: Invalid base64 not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'base64') !== false) {
        echo "✓ PASS: Invalid base64 detected\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 11: SecureFileUpload - Create and cleanup
 */
echo "\n--- Test 11: SecureFileUpload Create and Cleanup ---\n";
try {
    $uploadDir = sys_get_temp_dir() . '/chatbot_test_uploads_' . time();
    $secureUpload = new SecureFileUpload($uploadDir);
    
    $content = "Test file content";
    $filename = "test.txt";
    
    // Create temp file
    $tempPath = $secureUpload->createTempFile($content, $filename);
    
    if (!file_exists($tempPath)) {
        echo "✗ FAIL: Temp file not created\n";
        $testsFailed++;
    } else {
        $readContent = file_get_contents($tempPath);
        if ($readContent === $content) {
            echo "✓ PASS: Temp file created with correct content\n";
            
            // Test cleanup
            $cleaned = $secureUpload->cleanupTempFile($tempPath);
            if ($cleaned && !file_exists($tempPath)) {
                echo "✓ PASS: Temp file cleaned up successfully\n";
                $testsPassed++;
            } else {
                echo "✗ FAIL: Temp file cleanup failed\n";
                $testsFailed++;
            }
        } else {
            echo "✗ FAIL: Content mismatch\n";
            $testsFailed++;
        }
    }
    
    // Cleanup test directory
    @rmdir($uploadDir);
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}

/**
 * Test 12: SecureFileUpload - Directory security
 */
echo "\n--- Test 12: SecureFileUpload Directory Security ---\n";
try {
    $uploadDir = sys_get_temp_dir() . '/chatbot_test_uploads_security_' . time();
    $secureUpload = new SecureFileUpload($uploadDir);
    
    // Check .htaccess exists
    $htaccess = $uploadDir . '/.htaccess';
    $index = $uploadDir . '/index.php';
    
    if (file_exists($htaccess)) {
        echo "✓ PASS: .htaccess created for security\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: .htaccess not created\n";
        $testsFailed++;
    }
    
    if (file_exists($index)) {
        echo "✓ PASS: index.php created to prevent directory listing\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: index.php not created\n";
        $testsFailed++;
    }
    
    // Cleanup
    @unlink($htaccess);
    @unlink($index);
    @rmdir($uploadDir);
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}

/**
 * Test 13: SecureFileUpload - Path traversal prevention
 */
echo "\n--- Test 13: SecureFileUpload Path Traversal Prevention ---\n";
try {
    $uploadDir = sys_get_temp_dir() . '/chatbot_test_uploads_traversal_' . time();
    $secureUpload = new SecureFileUpload($uploadDir);
    
    $content = "Test content";
    
    // Try to create file with path traversal in filename
    $tempPath = $secureUpload->createTempFile($content, '../../../etc/passwd');
    
    // Check that file was created in upload dir (not traversed)
    $realPath = realpath($tempPath);
    $realUploadDir = realpath($uploadDir);
    
    if ($realPath !== false && $realUploadDir !== false && strpos($realPath, $realUploadDir) === 0) {
        echo "✓ PASS: File created within upload directory (traversal prevented)\n";
        $testsPassed++;
        
        // Cleanup
        $secureUpload->cleanupTempFile($tempPath);
    } else {
        echo "✗ FAIL: Path traversal not prevented properly\n";
        $testsFailed++;
    }
    
    @rmdir($uploadDir);
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    $testsFailed++;
}

/**
 * Test 14: eval() detection
 */
echo "\n--- Test 14: eval() Function Detection ---\n";
try {
    $validator = new FileValidator();
    $content = 'eval(base64_decode("malicious_code"))';
    $fileData = [
        'name' => 'file.txt',
        'type' => 'text/plain',
        'size' => strlen($content),
        'data' => base64_encode($content)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: eval() not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'malicious') !== false) {
        echo "✓ PASS: eval() detected and blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 15: Windows executable (PE header)
 */
echo "\n--- Test 15: Windows Executable Detection ---\n";
try {
    $validator = new FileValidator();
    $peHeader = "MZ\x90\x00"; // PE/Windows executable header
    $fileData = [
        'name' => 'file.txt',
        'type' => 'text/plain',
        'size' => strlen($peHeader),
        'data' => base64_encode($peHeader)
    ];
    
    $validator->validateFile($fileData, $testConfig);
    
    echo "✗ FAIL: Windows executable not detected!\n";
    $testsFailed++;
} catch (Exception $e) {
    // Accept either specific executable detection or MIME type rejection
    if (strpos($e->getMessage(), 'Executable') !== false || strpos($e->getMessage(), 'type not allowed') !== false) {
        echo "✓ PASS: Windows executable detected and blocked\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

/**
 * Test 16: Valid PDF file
 */
echo "\n--- Test 16: Valid PDF File ---\n";
try {
    $validator = new FileValidator();
    // Minimal valid PDF header
    $pdfContent = "%PDF-1.4\n%\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\nxref\n0 1\n0000000000 65535 f\ntrailer\n<<\n/Size 1\n/Root 1 0 R\n>>\nstartxref\n0\n%%EOF";
    $fileData = [
        'name' => 'document.pdf',
        'type' => 'application/pdf',
        'size' => strlen($pdfContent),
        'data' => base64_encode($pdfContent)
    ];
    
    $testConfigPdf = $testConfig;
    $testConfigPdf['allowed_file_types'][] = 'pdf';
    
    $decoded = $validator->validateFile($fileData, $testConfigPdf);
    
    if ($decoded === $pdfContent) {
        echo "✓ PASS: Valid PDF accepted\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: Valid PDF rejected or content mismatch\n";
        $testsFailed++;
    }
} catch (Exception $e) {
    echo "✗ FAIL: Valid PDF rejected: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";

if ($testsFailed > 0) {
    echo "\n❌ SOME TESTS FAILED\n";
    exit(1);
} else {
    echo "\n✅ ALL TESTS PASSED\n";
    exit(0);
}
