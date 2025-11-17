<?php
/**
 * Tests for ChatHandler Refactoring (Issue #001)
 * 
 * Tests the extracted classes:
 * - ChatRequestValidator
 * - AgentConfigResolver
 * - ConversationRepository
 * - ChatRateLimiter
 */

// Load classes directly without config.php to avoid OpenAI key requirement
require_once __DIR__ . '/../includes/ChatRequestValidator.php';
require_once __DIR__ . '/../includes/AgentConfigResolver.php';
require_once __DIR__ . '/../includes/ConversationRepository.php';
require_once __DIR__ . '/../includes/ChatRateLimiter.php';

echo "\n=== ChatHandler Refactoring Tests ===\n";

// Test configuration
$testConfig = [
    'security' => [
        'max_message_length' => 1000,
        'sanitize_input' => true
    ],
    'chat_config' => [
        'enable_file_upload' => false,
        'max_messages' => 10,
        'rate_limit_requests' => 5,
        'rate_limit_window' => 60
    ],
    'storage' => [
        'type' => 'session',
        'path' => sys_get_temp_dir()
    ]
];

// ============================================================================
// ChatRequestValidator Tests
// ============================================================================

echo "\n--- Test 1: ChatRequestValidator - Valid Message ---\n";
try {
    $validator = new ChatRequestValidator($testConfig);
    $message = "Hello, this is a test message";
    $result = $validator->validateMessage($message);
    
    if (!empty($result)) {
        echo "✓ PASS: Message validated successfully\n";
    } else {
        echo "✗ FAIL: Message validation returned empty result\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 2: ChatRequestValidator - Empty Message ---\n";
try {
    $validator = new ChatRequestValidator($testConfig);
    $validator->validateMessage("");
    echo "✗ FAIL: Empty message should throw exception\n";
    exit(1);
} catch (Exception $e) {
    if ($e->getCode() == 400 && strpos($e->getMessage(), 'empty') !== false) {
        echo "✓ PASS: Empty message rejected correctly\n";
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Test 3: ChatRequestValidator - Message Too Long ---\n";
try {
    $validator = new ChatRequestValidator($testConfig);
    $longMessage = str_repeat("x", 1001);
    $validator->validateMessage($longMessage);
    echo "✗ FAIL: Long message should throw exception\n";
    exit(1);
} catch (Exception $e) {
    if ($e->getCode() == 400 && strpos($e->getMessage(), 'too long') !== false) {
        echo "✓ PASS: Long message rejected correctly\n";
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Test 4: ChatRequestValidator - Valid Conversation ID ---\n";
try {
    $validator = new ChatRequestValidator($testConfig);
    $validator->validateConversationId("conv-12345_test");
    echo "✓ PASS: Valid conversation ID accepted\n";
} catch (Exception $e) {
    echo "✗ FAIL: Valid conversation ID rejected: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 5: ChatRequestValidator - Invalid Conversation ID ---\n";
try {
    $validator = new ChatRequestValidator($testConfig);
    $validator->validateConversationId("conv@invalid!");
    echo "✗ FAIL: Invalid conversation ID should throw exception\n";
    exit(1);
} catch (Exception $e) {
    if ($e->getCode() == 400 && strpos($e->getMessage(), 'Invalid') !== false) {
        echo "✓ PASS: Invalid conversation ID rejected correctly\n";
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Test 6: ChatRequestValidator - File Upload Disabled ---\n";
try {
    $validator = new ChatRequestValidator($testConfig);
    $fileData = ['name' => 'test.txt', 'type' => 'text/plain'];
    $validator->validateFileData($fileData);
    echo "✗ FAIL: File upload should be rejected when disabled\n";
    exit(1);
} catch (Exception $e) {
    if ($e->getCode() == 400 && strpos($e->getMessage(), 'not enabled') !== false) {
        echo "✓ PASS: File upload correctly rejected when disabled\n";
    } else {
        echo "✗ FAIL: Wrong exception: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ============================================================================
// AgentConfigResolver Tests
// ============================================================================

echo "\n--- Test 7: AgentConfigResolver - No Agent Service ---\n";
try {
    $resolver = new AgentConfigResolver($testConfig, null);
    $overrides = $resolver->resolveAgentOverrides('test-agent');
    
    if (is_array($overrides) && empty($overrides)) {
        echo "✓ PASS: Returns empty array when no agent service configured\n";
    } else {
        echo "✗ FAIL: Should return empty array\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 8: AgentConfigResolver - Merge With Defaults ---\n";
try {
    $resolver = new AgentConfigResolver($testConfig, null);
    $defaults = ['model' => 'gpt-4', 'temperature' => 0.7];
    $overrides = ['temperature' => 0.9, 'max_tokens' => 1000];
    $merged = $resolver->mergeWithDefaults($overrides, $defaults);
    
    if ($merged['model'] === 'gpt-4' 
        && $merged['temperature'] === 0.9 
        && $merged['max_tokens'] === 1000) {
        echo "✓ PASS: Configuration merged correctly\n";
    } else {
        echo "✗ FAIL: Configuration merge incorrect\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ConversationRepository Tests
// ============================================================================

echo "\n--- Test 9: ConversationRepository - Save and Get History (Session) ---\n";
try {
    // Ensure clean session state
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    $repo = new ConversationRepository($testConfig);
    $conversationId = "test-conv-" . time();
    $messages = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there']
    ];
    
    $repo->saveHistory($conversationId, $messages);
    $retrieved = $repo->getHistory($conversationId);
    
    if (count($retrieved) === 2 
        && $retrieved[0]['role'] === 'user' 
        && $retrieved[1]['content'] === 'Hi there') {
        echo "✓ PASS: Session history saved and retrieved correctly\n";
    } else {
        echo "✗ FAIL: History mismatch\n";
        var_dump($retrieved);
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 10: ConversationRepository - Save and Get History (File) ---\n";
try {
    $fileConfig = $testConfig;
    $fileConfig['storage']['type'] = 'file';
    $fileConfig['storage']['path'] = sys_get_temp_dir() . '/test_conversations';
    
    $repo = new ConversationRepository($fileConfig);
    $conversationId = "test-conv-file-" . time();
    $messages = [
        ['role' => 'user', 'content' => 'Test file storage'],
        ['role' => 'assistant', 'content' => 'File storage works']
    ];
    
    $repo->saveHistory($conversationId, $messages);
    $retrieved = $repo->getHistory($conversationId);
    
    if (count($retrieved) === 2 
        && $retrieved[0]['content'] === 'Test file storage') {
        echo "✓ PASS: File history saved and retrieved correctly\n";
    } else {
        echo "✗ FAIL: File history mismatch\n";
        exit(1);
    }
    
    // Cleanup
    $filePath = $fileConfig['storage']['path'] . '/' . $conversationId . '.json';
    if (file_exists($filePath)) {
        unlink($filePath);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 11: ConversationRepository - Max Messages Limit ---\n";
try {
    $repo = new ConversationRepository($testConfig);
    $conversationId = "test-conv-limit-" . time();
    
    // Create 15 messages (config limit is 10)
    $messages = [];
    for ($i = 1; $i <= 15; $i++) {
        $messages[] = ['role' => 'user', 'content' => "Message $i"];
    }
    
    $repo->saveHistory($conversationId, $messages);
    $retrieved = $repo->getHistory($conversationId);
    
    if (count($retrieved) === 10 
        && $retrieved[0]['content'] === 'Message 6') { // First message should be #6
        echo "✓ PASS: Message limit enforced correctly\n";
    } else {
        echo "✗ FAIL: Message limit not enforced (got " . count($retrieved) . " messages)\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ChatRateLimiter Tests
// ============================================================================

echo "\n--- Test 12: ChatRateLimiter - Legacy Rate Limit (No Service) ---\n";
try {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    
    $limiter = new ChatRateLimiter($testConfig, null, null);
    
    // Should allow first 5 requests
    for ($i = 0; $i < 5; $i++) {
        $limiter->checkRateLimitLegacy(null);
    }
    
    echo "✓ PASS: First 5 requests allowed\n";
} catch (Exception $e) {
    echo "✗ FAIL: Should allow configured number of requests: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 13: ChatRateLimiter - Legacy Rate Limit Exceeded ---\n";
try {
    // 6th request should fail
    $limiter->checkRateLimitLegacy(null);
    echo "✗ FAIL: Rate limit should be exceeded\n";
    exit(1);
} catch (Exception $e) {
    if ($e->getCode() == 429) {
        echo "✓ PASS: Rate limit exceeded correctly detected\n";
    } else {
        echo "✗ FAIL: Wrong exception code: " . $e->getCode() . "\n";
        exit(1);
    }
}

// Cleanup rate limit files
$cleanupPattern = sys_get_temp_dir() . '/chatbot_requests_*';
foreach (glob($cleanupPattern) as $file) {
    unlink($file);
}

echo "\n=== Test Summary ===\n";
echo "Total tests passed: 13/13 ✅\n";
echo "Total tests failed: 0\n";
echo "\n✅ All ChatHandler refactoring tests passed!\n";
