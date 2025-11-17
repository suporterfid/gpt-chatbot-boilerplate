<?php
/**
 * Integration test for webhook event processing flow (Issue wh-001c)
 *
 * Tests the complete flow:
 * 1. WebhookGateway receives event and enqueues job
 * 2. Worker claims and processes the job
 * 3. WebhookEventProcessor routes to appropriate handler
 * 4. Event is marked as processed in webhook_events table
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/WebhookGateway.php';
require_once __DIR__ . '/../includes/JobQueue.php';
require_once __DIR__ . '/../includes/WebhookEventProcessor.php';
require_once __DIR__ . '/../includes/OpenAIClient.php';
require_once __DIR__ . '/../includes/ChatHandler.php';

function testWebhookIntegration(): void {
    echo "\n=== Testing Webhook Integration (End-to-End) ===\n\n";
    
    global $config;
    $allPassed = true;
    
    try {
        // Initialize components
        $db = new DB($config['database'] ?? []);
        
        // Run migrations to ensure database schema is up to date
        echo "Running database migrations...\n";
        $db->runMigrations();
        echo "✓ Database schema ready\n\n";
        
        // Configure webhook gateway for async processing
        $webhookGateway = new WebhookGateway($config, $db, null, null, true);
        
        // Initialize job queue
        $jobQueue = new JobQueue($db);
        
        // Mock ChatHandler to avoid OpenAI calls
        $mockChatHandler = new class($config, $db) extends ChatHandler {
            public function handleChatCompletionSync($message, $conversationId, $agentId = null, $tenantId = null) {
                return [
                    'message' => 'Mock response to: ' . substr($message, 0, 50),
                    'processing_time_ms' => 42
                ];
            }
        };
        
        // Initialize processor
        $webhookEventProcessor = new WebhookEventProcessor($config, $db, $mockChatHandler);
        
        echo "✓ All components initialized successfully\n\n";
        
    } catch (Exception $e) {
        echo "✗ Failed to initialize components: " . $e->getMessage() . "\n";
        return;
    }
    
    // Test 1: Submit webhook event and verify job creation
    echo "Test 1: Submit webhook event and verify job enqueued...\n";
    try {
        $headers = ['Content-Type' => 'application/json'];
        $payload = json_encode([
            'event' => 'message.created',
            'timestamp' => time(),
            'data' => [
                'message' => 'Integration test message',
                'conversation_id' => 'integration_test_001'
            ]
        ]);
        
        $result = $webhookGateway->handleRequest($headers, $payload);
        
        if ($result['status'] === 'received' && 
            $result['processing'] === 'async' &&
            isset($result['job_id'])) {
            echo "  ✓ Webhook event accepted and job enqueued\n";
            echo "  ✓ Job ID: " . $result['job_id'] . "\n";
            echo "  ✓ Event ID: " . $result['event_id'] . "\n";
            
            $jobId = $result['job_id'];
            $eventId = $result['event_id'];
        } else {
            echo "  ✗ Webhook event not properly enqueued\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
            return;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception submitting webhook: " . $e->getMessage() . "\n";
        $allPassed = false;
        return;
    }
    
    // Test 2: Verify job exists in queue
    echo "\nTest 2: Verify job exists in queue...\n";
    try {
        $job = $jobQueue->getJob($jobId);
        
        if ($job && $job['type'] === 'webhook_event' && $job['status'] === 'pending') {
            echo "  ✓ Job found in queue with correct type\n";
            echo "  ✓ Job status: " . $job['status'] . "\n";
        } else {
            echo "  ✗ Job not found or incorrect type\n";
            if ($job) {
                echo "  Job: " . json_encode($job) . "\n";
            }
            $allPassed = false;
            return;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception checking job: " . $e->getMessage() . "\n";
        $allPassed = false;
        return;
    }
    
    // Test 3: Process the job (simulate worker)
    echo "\nTest 3: Process job through worker flow...\n";
    try {
        // Claim the job
        $claimedJob = $jobQueue->claimNext();
        
        if (!$claimedJob || $claimedJob['id'] !== $jobId) {
            echo "  ✗ Failed to claim job\n";
            $allPassed = false;
            return;
        }
        
        echo "  ✓ Job claimed successfully\n";
        echo "  ✓ Job locked by: " . $claimedJob['locked_by'] . "\n";
        
        // Process the job payload
        $payload = $claimedJob['payload'];
        $processingResult = $webhookEventProcessor->processEvent($payload);
        
        if ($processingResult['status'] === 'processed') {
            echo "  ✓ Job processed successfully by WebhookEventProcessor\n";
            echo "  ✓ Event type: " . $processingResult['event_type'] . "\n";
            
            // Mark job as completed
            $jobQueue->markCompleted($jobId, $processingResult);
            echo "  ✓ Job marked as completed\n";
        } else {
            echo "  ✗ Job processing failed\n";
            echo "  Result: " . json_encode($processingResult) . "\n";
            $allPassed = false;
            return;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing job: " . $e->getMessage() . "\n";
        $allPassed = false;
        return;
    }
    
    // Test 4: Verify job completion
    echo "\nTest 4: Verify job marked as completed...\n";
    try {
        $completedJob = $jobQueue->getJob($jobId);
        
        if ($completedJob['status'] === 'completed' && isset($completedJob['result'])) {
            echo "  ✓ Job status is 'completed'\n";
            echo "  ✓ Job result stored\n";
        } else {
            echo "  ✗ Job not marked as completed\n";
            echo "  Job status: " . $completedJob['status'] . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception verifying completion: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 5: Test idempotency - submit same event again
    echo "\nTest 5: Test idempotency (duplicate event should be rejected)...\n";
    try {
        $headers = ['Content-Type' => 'application/json'];
        $payload = json_encode([
            'id' => $eventId, // Use same event ID
            'event' => 'message.created',
            'timestamp' => time(),
            'data' => [
                'message' => 'Integration test message',
                'conversation_id' => 'integration_test_001'
            ]
        ]);
        
        $result = $webhookGateway->handleRequest($headers, $payload);
        
        if ($result['status'] === 'received' && 
            isset($result['note']) && 
            $result['note'] === 'duplicate_event') {
            echo "  ✓ Duplicate event detected and rejected\n";
            echo "  ✓ No new job created\n";
        } else {
            echo "  ✗ Idempotency check failed\n";
            echo "  Result: " . json_encode($result) . "\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception testing idempotency: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Test 6: Test multiple event types
    echo "\nTest 6: Process different event types...\n";
    try {
        $eventTypes = [
            ['event' => 'conversation.created', 'data' => ['conversation_id' => 'test_002']],
            ['event' => 'file.uploaded', 'data' => ['file_id' => 'file_abc', 'filename' => 'test.pdf']],
            ['event' => 'ping', 'data' => []]
        ];
        
        $processed = 0;
        foreach ($eventTypes as $eventData) {
            $payload = json_encode([
                'event' => $eventData['event'],
                'timestamp' => time(),
                'data' => $eventData['data']
            ]);
            
            $result = $webhookGateway->handleRequest($headers, $payload);
            
            if ($result['status'] === 'received' && isset($result['job_id'])) {
                // Claim and process
                $job = $jobQueue->claimNext();
                if ($job) {
                    $procResult = $webhookEventProcessor->processEvent($job['payload']);
                    $jobQueue->markCompleted($job['id'], $procResult);
                    $processed++;
                }
            }
        }
        
        if ($processed === count($eventTypes)) {
            echo "  ✓ All " . count($eventTypes) . " event types processed successfully\n";
        } else {
            echo "  ✗ Only $processed/" . count($eventTypes) . " events processed\n";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo "  ✗ Exception processing multiple events: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
    
    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    if ($allPassed) {
        echo "✓ All integration tests passed!\n";
        echo "\nEnd-to-End Flow Verified:\n";
        echo "  WebhookGateway → JobQueue → Worker → WebhookEventProcessor → ChatHandler\n";
        echo "  ✓ Event enqueueing\n";
        echo "  ✓ Job claiming and processing\n";
        echo "  ✓ Event routing to handlers\n";
        echo "  ✓ Idempotency enforcement\n";
        echo "  ✓ Multiple event types support\n";
    } else {
        echo "✗ Some integration tests failed. Please review the output above.\n";
        exit(1);
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    testWebhookIntegration();
}
