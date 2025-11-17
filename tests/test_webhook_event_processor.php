<?php
/**
 * Test suite for WebhookEventProcessor (Issue wh-001c)
 *
 * Validates that normalized webhook events from WebhookGateway are properly:
 * - Routed to appropriate handlers
 * - Processed through ChatHandler for agent interactions
 * - Integrated with JobQueue for async processing
 * - Tracked with idempotency via webhook_events table
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/OpenAIClient.php';
require_once __DIR__ . '/../includes/WebhookEventProcessor.php';
require_once __DIR__ . '/../includes/ChatHandler.php';

function testWebhookEventProcessor(): void {
    echo "\n=== Testing WebhookEventProcessor (Issue wh-001c) ===\n\n";
    
    global $config;
    $allPassed = true;
    
    try {
        // Initialize test database
        $db = new DB($config['database'] ?? []);
        
        // Mock ChatHandler for testing (avoid actual OpenAI calls)
        $mockChatHandler = new class($config, $db) extends ChatHandler {
            public function handleChatCompletionSync($message, $conversationId, $agentId = null, $tenantId = null) {
                // Mock response without calling OpenAI
                return [
                    'message' => 'Mock response to: ' . substr($message, 0, 50),
                    'processing_time_ms' => 42
                ];
            }
        };
        
        // Initialize processor
        $processor = new WebhookEventProcessor($config, $db, $mockChatHandler);
        
        echo "✓ WebhookEventProcessor initialized successfully\n\n";
        
    } catch (Exception $e) {
        echo "✗ Failed to initialize WebhookEventProcessor: " . $e->getMessage() . "\n";
        return;
    }
    
    // Test 1: Process chat message event
    echo "Test 1: Process chat message event...\n";
    try {
        $normalizedEvent = [
            'event_id' => 'test_chat_' . bin2hex(random_bytes(8)),
            'event_type' => 'message.created',
            'timestamp' => time(),
            'data' => [
                'message' => 'Hello from webhook test',
                'conversation_id' => 'test_conv_001',
                'agent_id' => null,
                'tenant_id' => null
            ],
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
        
        $result = $processor->processEvent($normalizedEvent);
        
        if ($result['status'] === 'processed' && 
            $result['event_type'] === 'message.created' &&
            isset($result['result']['conversation_id'])) {
            echo "  ✓ Chat message event processed successfully\n";
            echo "  ✓ Conversation ID: " . $result['result']['conversation_id'] . "\n";
        } else {
            echo "  ✗ Chat message event processing failed\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing chat message: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 2: Process conversation created event
    echo "\nTest 2: Process conversation created event...\n";
    try {
        $normalizedEvent = [
            'event_id' => 'test_conv_' . bin2hex(random_bytes(8)),
            'event_type' => 'conversation.created',
            'timestamp' => time(),
            'data' => [
                'conversation_id' => 'test_conv_002',
                'agent_id' => 'test_agent',
                'tenant_id' => 'test_tenant'
            ],
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
        
        $result = $processor->processEvent($normalizedEvent);
        
        if ($result['status'] === 'processed' && 
            $result['event_type'] === 'conversation.created' &&
            $result['result']['status'] === 'initialized') {
            echo "  ✓ Conversation created event processed successfully\n";
        } else {
            echo "  ✗ Conversation created event processing failed\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing conversation created: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 3: Process file uploaded event
    echo "\nTest 3: Process file uploaded event...\n";
    try {
        $normalizedEvent = [
            'event_id' => 'test_file_' . bin2hex(random_bytes(8)),
            'event_type' => 'file.uploaded',
            'timestamp' => time(),
            'data' => [
                'file_id' => 'file_123',
                'filename' => 'test_document.pdf'
            ],
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
        
        $result = $processor->processEvent($normalizedEvent);
        
        if ($result['status'] === 'processed' && 
            $result['event_type'] === 'file.uploaded' &&
            $result['result']['status'] === 'acknowledged') {
            echo "  ✓ File uploaded event processed successfully\n";
        } else {
            echo "  ✗ File uploaded event processing failed\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing file uploaded: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 4: Process ping/test event
    echo "\nTest 4: Process ping/test event...\n";
    try {
        $normalizedEvent = [
            'event_id' => 'test_ping_' . bin2hex(random_bytes(8)),
            'event_type' => 'ping',
            'timestamp' => time(),
            'data' => [],
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
        
        $result = $processor->processEvent($normalizedEvent);
        
        if ($result['status'] === 'processed' && 
            $result['result']['status'] === 'acknowledged') {
            echo "  ✓ Ping event processed successfully\n";
        } else {
            echo "  ✗ Ping event processing failed\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing ping: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 5: Process unknown event type (should not throw error)
    echo "\nTest 5: Process unknown event type gracefully...\n";
    try {
        $normalizedEvent = [
            'event_id' => 'test_unknown_' . bin2hex(random_bytes(8)),
            'event_type' => 'unknown.custom.event',
            'timestamp' => time(),
            'data' => ['custom' => 'data'],
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
        
        $result = $processor->processEvent($normalizedEvent);
        
        if ($result['status'] === 'processed' && 
            isset($result['result']['status']) &&
            $result['result']['status'] === 'ignored' &&
            $result['result']['reason'] === 'unknown_event_type') {
            echo "  ✓ Unknown event type handled gracefully\n";
        } else {
            echo "  ✗ Unknown event type not handled properly\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing unknown event (should handle gracefully): " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 6: Process agent trigger event
    echo "\nTest 6: Process agent trigger event...\n";
    try {
        $normalizedEvent = [
            'event_id' => 'test_trigger_' . bin2hex(random_bytes(8)),
            'event_type' => 'agent.trigger',
            'timestamp' => time(),
            'data' => [
                'action' => 'process_message',
                'agent_id' => 'test_agent',
                'payload' => [
                    'message' => 'Triggered message',
                    'conversation_id' => 'trigger_conv_001'
                ]
            ],
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
        
        $result = $processor->processEvent($normalizedEvent);
        
        if ($result['status'] === 'processed' && 
            isset($result['result']['conversation_id'])) {
            echo "  ✓ Agent trigger event processed successfully\n";
        } else {
            echo "  ✗ Agent trigger event processing failed\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing agent trigger: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 7: Reject invalid event (missing required data)
    echo "\nTest 7: Reject chat event with missing message...\n";
    try {
        $normalizedEvent = [
            'event_id' => 'test_invalid_' . bin2hex(random_bytes(8)),
            'event_type' => 'message.created',
            'timestamp' => time(),
            'data' => [
                'conversation_id' => 'test_conv_003'
                // Missing 'message' field
            ],
            'received_at' => time(),
            'source' => 'webhook_gateway'
        ];
        
        $result = $processor->processEvent($normalizedEvent);
        
        // Should throw exception
        echo "  ✗ Invalid event should have thrown exception\n";
        $allPassed = false;
        
    } catch (WebhookEventProcessorException $e) {
        if ($e->getErrorCode() === 'invalid_event_data') {
            echo "  ✓ Invalid event rejected with proper error code\n";
        } else {
            echo "  ✗ Invalid event rejected but wrong error code: " . $e->getErrorCode() . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Unexpected exception: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    if ($allPassed) {
        echo "✓ All WebhookEventProcessor tests passed!\n";
    } else {
        echo "✗ Some tests failed. Please review the output above.\n";
        exit(1);
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    testWebhookEventProcessor();
}
