#!/usr/bin/env php
<?php
/**
 * Test CRM Lead Event Types (Task 3)
 * 
 * Tests new event recording methods and LeadEventTypes class
 */

// Set minimal env to avoid config errors
putenv('OPENAI_API_KEY=sk-test-dummy-key-for-testing');

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/LeadSense/LeadRepository.php';
require_once __DIR__ . '/../includes/LeadSense/LeadEventTypes.php';

echo "\n=== Testing CRM Lead Event Types (Task 3) ===\n";

// Initialize repository
$config = [
    'database_path' => __DIR__ . '/../data/chatbot.db',
    'database_url' => null
];
$leadRepo = new LeadRepository($config);

// Test data
$testLeadId = null;
$testEventIds = [];

echo "\n--- Test 1: LeadEventTypes Class - Constants ---\n";
try {
    // Test all constants are defined
    $allTypes = LeadEventTypes::all();
    
    if (count($allTypes) >= 10) {
        echo "✓ PASS: All event types defined (" . count($allTypes) . " types)\n";
    } else {
        echo "✗ FAIL: Expected at least 10 event types, got " . count($allTypes) . "\n";
        exit(1);
    }
    
    // Test specific constants exist
    $requiredTypes = [
        LeadEventTypes::DETECTED,
        LeadEventTypes::STAGE_CHANGED,
        LeadEventTypes::OWNER_CHANGED,
        LeadEventTypes::PIPELINE_CHANGED,
        LeadEventTypes::DEAL_UPDATED,
        LeadEventTypes::NOTE
    ];
    
    foreach ($requiredTypes as $type) {
        if (!in_array($type, $allTypes)) {
            echo "✗ FAIL: Event type '$type' not found in all()\n";
            exit(1);
        }
    }
    
    echo "✓ PASS: All required event types exist\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 2: LeadEventTypes Class - Validation ---\n";
try {
    // Test valid types
    if (LeadEventTypes::isValid('stage_changed')) {
        echo "✓ PASS: stage_changed is valid\n";
    } else {
        echo "✗ FAIL: stage_changed should be valid\n";
        exit(1);
    }
    
    // Test invalid type
    if (!LeadEventTypes::isValid('invalid_type')) {
        echo "✓ PASS: invalid_type correctly rejected\n";
    } else {
        echo "✗ FAIL: invalid_type should be invalid\n";
        exit(1);
    }
    
    // Test getCRM() returns only CRM types
    $crmTypes = LeadEventTypes::getCRM();
    if (in_array('stage_changed', $crmTypes) && !in_array('detected', $crmTypes)) {
        echo "✓ PASS: getCRM() returns only CRM event types\n";
    } else {
        echo "✗ FAIL: getCRM() should return only CRM types\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 3: LeadEventTypes Class - Labels and Icons ---\n";
try {
    // Test labels
    $label = LeadEventTypes::getLabel('stage_changed');
    if (!empty($label) && $label !== 'stage_changed') {
        echo "✓ PASS: stage_changed has label: '$label'\n";
    } else {
        echo "✗ FAIL: stage_changed should have a human-readable label\n";
        exit(1);
    }
    
    // Test icons
    $icon = LeadEventTypes::getIcon('stage_changed');
    if (!empty($icon)) {
        echo "✓ PASS: stage_changed has icon: '$icon'\n";
    } else {
        echo "✗ FAIL: stage_changed should have an icon\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 4: Create Test Lead ---\n";
try {
    // Create a test lead for event recording
    $testLeadId = $leadRepo->createOrUpdateLead([
        'conversation_id' => 'test_crm_events_' . time(),
        'agent_id' => 'test_agent',
        'name' => 'Test Lead for CRM Events',
        'email' => 'crm-test@example.com',
        'status' => 'new'
    ]);
    
    if (!empty($testLeadId)) {
        echo "✓ PASS: Test lead created: $testLeadId\n";
    } else {
        echo "✗ FAIL: Failed to create test lead\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 5: Record Stage Change Event ---\n";
try {
    $oldStage = [
        'id' => 'stage_lead_capture',
        'name' => 'Lead Capture',
        'pipeline_id' => 'pipe_default'
    ];
    
    $newStage = [
        'id' => 'stage_support',
        'name' => 'Support',
        'pipeline_id' => 'pipe_default'
    ];
    
    $changedBy = [
        'id' => 'admin_user_123',
        'type' => 'admin_user',
        'name' => 'Test Admin'
    ];
    
    $eventId = $leadRepo->recordStageChange($testLeadId, $oldStage, $newStage, $changedBy, 'Customer requested technical evaluation');
    
    if (!empty($eventId)) {
        echo "✓ PASS: Stage change event recorded: $eventId\n";
        $testEventIds[] = $eventId;
        
        // Verify event in database
        $events = $leadRepo->getLeadEvents($testLeadId, 'stage_changed');
        if (!empty($events) && $events[0]['type'] === 'stage_changed') {
            $payload = $events[0]['payload'];
            if ($payload['old_stage_name'] === 'Lead Capture' && $payload['new_stage_name'] === 'Support') {
                echo "✓ PASS: Stage change payload correct\n";
            } else {
                echo "✗ FAIL: Stage change payload incorrect\n";
                exit(1);
            }
        } else {
            echo "✗ FAIL: Stage change event not found in database\n";
            exit(1);
        }
    } else {
        echo "✗ FAIL: Failed to record stage change event\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 6: Record Owner Change Event ---\n";
try {
    $oldOwner = [
        'id' => 'admin_user_123',
        'type' => 'admin_user',
        'name' => 'Frank Wilson'
    ];
    
    $newOwner = [
        'id' => 'admin_user_456',
        'type' => 'admin_user',
        'name' => 'Sarah Johnson'
    ];
    
    $changedBy = [
        'id' => 'admin_user_789',
        'type' => 'admin_user',
        'name' => 'Manager'
    ];
    
    $eventId = $leadRepo->recordOwnerChange($testLeadId, $oldOwner, $newOwner, $changedBy, 'Reassigned for specialized expertise');
    
    if (!empty($eventId)) {
        echo "✓ PASS: Owner change event recorded: $eventId\n";
        $testEventIds[] = $eventId;
        
        // Verify payload
        $events = $leadRepo->getLeadEvents($testLeadId, 'owner_changed');
        if (!empty($events)) {
            $payload = $events[0]['payload'];
            if ($payload['old_owner_name'] === 'Frank Wilson' && $payload['new_owner_name'] === 'Sarah Johnson') {
                echo "✓ PASS: Owner change payload correct\n";
            } else {
                echo "✗ FAIL: Owner change payload incorrect\n";
                exit(1);
            }
        }
    } else {
        echo "✗ FAIL: Failed to record owner change event\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 7: Record Pipeline Change Event ---\n";
try {
    $oldPipeline = [
        'id' => 'pipe_default',
        'name' => 'Default'
    ];
    
    $newPipeline = [
        'id' => 'pipe_enterprise',
        'name' => 'Enterprise Sales'
    ];
    
    $newStage = [
        'id' => 'stage_discovery',
        'name' => 'Discovery'
    ];
    
    $changedBy = [
        'id' => 'admin_user_123',
        'type' => 'admin_user',
        'name' => 'Test Admin'
    ];
    
    $eventId = $leadRepo->recordPipelineChange($testLeadId, $oldPipeline, $newPipeline, $newStage, $changedBy, 'Qualified for enterprise track');
    
    if (!empty($eventId)) {
        echo "✓ PASS: Pipeline change event recorded: $eventId\n";
        $testEventIds[] = $eventId;
        
        // Verify payload
        $events = $leadRepo->getLeadEvents($testLeadId, 'pipeline_changed');
        if (!empty($events)) {
            $payload = $events[0]['payload'];
            if ($payload['old_pipeline_name'] === 'Default' && $payload['new_pipeline_name'] === 'Enterprise Sales') {
                echo "✓ PASS: Pipeline change payload correct\n";
            } else {
                echo "✗ FAIL: Pipeline change payload incorrect\n";
                exit(1);
            }
        }
    } else {
        echo "✗ FAIL: Failed to record pipeline change event\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 8: Record Deal Update Event ---\n";
try {
    $changes = [
        'deal_value' => [
            'old' => 5000.00,
            'new' => 10000.00
        ],
        'probability' => [
            'old' => 30,
            'new' => 70
        ],
        'expected_close_date' => [
            'old' => '2025-02-01',
            'new' => '2025-01-25'
        ]
    ];
    
    $changedBy = [
        'id' => 'admin_user_123',
        'type' => 'admin_user',
        'name' => 'Test Admin'
    ];
    
    $eventId = $leadRepo->recordDealUpdate($testLeadId, $changes, $changedBy, 'Customer committed to faster deployment');
    
    if (!empty($eventId)) {
        echo "✓ PASS: Deal update event recorded: $eventId\n";
        $testEventIds[] = $eventId;
        
        // Verify payload
        $events = $leadRepo->getLeadEvents($testLeadId, 'deal_updated');
        if (!empty($events)) {
            $payload = $events[0]['payload'];
            if (isset($payload['changes']['deal_value']) && $payload['changes']['deal_value']['new'] == 10000.00) {
                echo "✓ PASS: Deal update payload correct\n";
            } else {
                echo "✗ FAIL: Deal update payload incorrect\n";
                exit(1);
            }
        }
    } else {
        echo "✗ FAIL: Failed to record deal update event\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 9: Add Note with Context ---\n";
try {
    $author = [
        'id' => 'admin_user_123',
        'type' => 'admin_user',
        'name' => 'Test Admin'
    ];
    
    $context = [
        'source' => 'crm_board',
        'stage_id' => 'stage_support',
        'stage_name' => 'Support'
    ];
    
    $eventId = $leadRepo->addNote($testLeadId, 'Customer asked for a follow-up demo next week. Interested in API integration.', $author, $context);
    
    if (!empty($eventId)) {
        echo "✓ PASS: Note with context recorded: $eventId\n";
        $testEventIds[] = $eventId;
        
        // Verify payload
        $events = $leadRepo->getLeadEvents($testLeadId, 'note');
        if (!empty($events)) {
            $payload = $events[0]['payload'];
            if (isset($payload['text']) && isset($payload['context']) && $payload['context']['source'] === 'crm_board') {
                echo "✓ PASS: Note payload with context correct\n";
            } else {
                echo "✗ FAIL: Note payload incorrect\n";
                exit(1);
            }
        }
    } else {
        echo "✗ FAIL: Failed to record note\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 10: Get Events with Type Filtering ---\n";
try {
    // Get all CRM events
    $crmTypes = LeadEventTypes::getCRM();
    $crmEvents = $leadRepo->getLeadEvents($testLeadId, $crmTypes);
    
    if (count($crmEvents) >= 4) {
        echo "✓ PASS: Found " . count($crmEvents) . " CRM events\n";
    } else {
        echo "✗ FAIL: Expected at least 4 CRM events, got " . count($crmEvents) . "\n";
        exit(1);
    }
    
    // Get all events (no filter)
    $allEvents = $leadRepo->getLeadEvents($testLeadId);
    
    if (count($allEvents) >= count($crmEvents)) {
        echo "✓ PASS: All events retrieved: " . count($allEvents) . "\n";
    } else {
        echo "✗ FAIL: All events count should be >= CRM events count\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 11: Verify Event Timestamps ---\n";
try {
    $events = $leadRepo->getLeadEvents($testLeadId);
    
    foreach ($events as $event) {
        if (isset($event['payload']['changed_at']) || isset($event['payload']['created_at'])) {
            $timestamp = $event['payload']['changed_at'] ?? $event['payload']['created_at'];
            
            // Verify ISO 8601 format
            $parsed = date_parse($timestamp);
            if ($parsed !== false && $parsed['error_count'] === 0) {
                // Timestamp is valid
            } else {
                echo "✗ FAIL: Invalid timestamp format in event: " . $event['type'] . "\n";
                exit(1);
            }
        }
    }
    
    echo "✓ PASS: All event timestamps are valid ISO 8601 format\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Test 12: Backward Compatibility ---\n";
try {
    // Verify existing addEvent() method still works
    $eventId = $leadRepo->addEvent($testLeadId, 'detected', ['source' => 'test']);
    
    if (!empty($eventId)) {
        echo "✓ PASS: Existing addEvent() method still works\n";
    } else {
        echo "✗ FAIL: Existing addEvent() method broken\n";
        exit(1);
    }
    
    // Verify existing getEvents() method still works
    $events = $leadRepo->getEvents($testLeadId);
    
    if (!empty($events)) {
        echo "✓ PASS: Existing getEvents() method still works\n";
    } else {
        echo "✗ FAIL: Existing getEvents() method broken\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Test Summary ===\n";
echo "✅ All CRM event type tests passed!\n";
echo "Total events created: " . count($testEventIds) . "\n";
echo "Test lead ID: $testLeadId\n";

exit(0);
