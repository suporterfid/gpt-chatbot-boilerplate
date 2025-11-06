#!/usr/bin/env php
<?php
/**
 * Test Audit Service
 * Basic tests for audit trail functionality
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/AuditService.php';

echo "Testing Audit Service...\n\n";

// Test configuration
$testConfig = [
    'enabled' => true,
    'encrypt_at_rest' => true,
    'encryption_key' => 'TEST_ONLY_KEY_DO_NOT_USE_IN_PRODUCTION_32c',
    'retention_days' => 90,
    'pii_redaction_patterns' => '',
    'database_url' => '',
    'database_path' => __DIR__ . '/../data/chatbot.db'
];

try {
    // Initialize Audit Service
    echo "1. Initializing Audit Service...\n";
    $auditService = new AuditService($testConfig);
    
    if (!$auditService->isEnabled()) {
        echo "   ERROR: Audit service is not enabled\n";
        exit(1);
    }
    echo "   ✓ Audit Service initialized\n\n";
    
    // Test 1: Start a conversation
    echo "2. Starting audit conversation...\n";
    $conversationId = 'test_conv_' . uniqid();
    $auditConvId = $auditService->startConversation(
        'test_agent',
        'web',
        $conversationId,
        hash('sha256', '127.0.0.1'),
        ['test' => true]
    );
    
    if (empty($auditConvId)) {
        echo "   ERROR: Failed to start conversation\n";
        exit(1);
    }
    echo "   ✓ Conversation started: $conversationId\n\n";
    
    // Test 2: Append user message
    echo "3. Appending user message...\n";
    $userMessageId = $auditService->appendMessage($conversationId, [
        'role' => 'user',
        'content' => 'Hello, this is a test message with an email test@example.com',
        'request_meta' => [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7
        ]
    ]);
    
    if (empty($userMessageId)) {
        echo "   ERROR: Failed to append user message\n";
        exit(1);
    }
    echo "   ✓ User message appended: $userMessageId\n\n";
    
    // Test 3: Record events
    echo "4. Recording events...\n";
    $auditService->recordEvent($conversationId, 'request_start', [
        'api_type' => 'responses',
        'streaming' => true
    ], $userMessageId);
    
    $auditService->recordEvent($conversationId, 'stream_start', [
        'response_id' => 'test_response_123'
    ]);
    echo "   ✓ Events recorded\n\n";
    
    // Test 4: Append assistant message
    echo "5. Appending assistant message...\n";
    $assistantMessageId = $auditService->appendMessage($conversationId, [
        'role' => 'assistant',
        'content' => 'This is a test response from the assistant.'
    ]);
    
    if (empty($assistantMessageId)) {
        echo "   ERROR: Failed to append assistant message\n";
        exit(1);
    }
    echo "   ✓ Assistant message appended: $assistantMessageId\n\n";
    
    // Test 5: Finalize message
    echo "6. Finalizing assistant message...\n";
    $auditService->finalizeMessage($assistantMessageId, [
        'response_id' => 'test_response_123',
        'finish_reason' => 'completed',
        'latency_ms' => 1234,
        'http_status' => 200
    ]);
    echo "   ✓ Message finalized\n\n";
    
    // Test 6: Retrieve conversation
    echo "7. Retrieving conversation...\n";
    $conversation = $auditService->getConversation($conversationId);
    
    if (!$conversation) {
        echo "   ERROR: Failed to retrieve conversation\n";
        exit(1);
    }
    echo "   ✓ Conversation retrieved\n";
    echo "   Agent ID: " . $conversation['agent_id'] . "\n";
    echo "   Channel: " . $conversation['channel'] . "\n\n";
    
    // Test 7: Retrieve messages
    echo "8. Retrieving messages...\n";
    $messages = $auditService->getMessages($conversationId, false);
    
    if (count($messages) !== 2) {
        echo "   ERROR: Expected 2 messages, got " . count($messages) . "\n";
        exit(1);
    }
    echo "   ✓ Retrieved " . count($messages) . " messages\n";
    echo "   Message 1 role: " . $messages[0]['role'] . "\n";
    echo "   Message 2 role: " . $messages[1]['role'] . "\n";
    echo "   Content is encrypted: " . (!empty($messages[0]['content_enc']) ? 'yes' : 'no') . "\n\n";
    
    // Test 8: Retrieve messages with decryption
    echo "9. Retrieving messages with decryption...\n";
    $messagesDecrypted = $auditService->getMessages($conversationId, true);
    
    if (empty($messagesDecrypted[0]['content'])) {
        echo "   ERROR: Failed to decrypt content\n";
        exit(1);
    }
    echo "   ✓ Messages decrypted\n";
    echo "   User message content: " . substr($messagesDecrypted[0]['content'], 0, 50) . "...\n";
    echo "   PII redacted: " . (strpos($messagesDecrypted[0]['content'], '[EMAIL_REDACTED]') !== false ? 'yes' : 'no') . "\n\n";
    
    // Test 9: Retrieve events
    echo "10. Retrieving events...\n";
    $events = $auditService->getEvents($conversationId);
    
    if (count($events) < 2) {
        echo "   ERROR: Expected at least 2 events, got " . count($events) . "\n";
        exit(1);
    }
    echo "   ✓ Retrieved " . count($events) . " events\n";
    foreach ($events as $event) {
        echo "   - " . $event['type'] . "\n";
    }
    echo "\n";
    
    // Test 10: List conversations
    echo "11. Listing conversations...\n";
    $conversations = $auditService->listConversations(['agent_id' => 'test_agent'], 10, 0);
    
    if (count($conversations) < 1) {
        echo "   ERROR: Expected at least 1 conversation\n";
        exit(1);
    }
    echo "   ✓ Listed " . count($conversations) . " conversations\n\n";
    
    echo "========================================\n";
    echo "All tests passed successfully!\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "\n\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
