#!/usr/bin/env php
<?php
/**
 * Simple test runner for Phase 1 implementation
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

// Test counter
$testsPassed = 0;
$testsFailed = 0;

function assert_true($condition, $message) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message\n";
        $testsFailed++;
    }
}

function assert_equals($expected, $actual, $message) {
    global $testsPassed, $testsFailed;
    if ($expected === $actual) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n";
        $testsFailed++;
    }
}

function assert_not_null($value, $message) {
    global $testsPassed, $testsFailed;
    if ($value !== null) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (value is null)\n";
        $testsFailed++;
    }
}

echo "=== Running Phase 1 Tests ===\n\n";

// Test 1: Database connection
echo "--- Test 1: Database Connection ---\n";
try {
    $dbConfig = [
        'database_path' => '/tmp/test_chatbot_' . time() . '.db'
    ];
    $db = new DB($dbConfig);
    assert_true(true, "Database connection established");
} catch (Exception $e) {
    assert_true(false, "Database connection failed: " . $e->getMessage());
}

// Test 2: Run migrations
echo "\n--- Test 2: Run Migrations ---\n";
try {
    $count = $db->runMigrations(__DIR__ . '/../db/migrations');
    assert_true($count >= 0, "Migrations executed (count: $count)");
    
    // Check if agents table exists
    $exists = $db->tableExists('agents');
    assert_true($exists, "Agents table created");
} catch (Exception $e) {
    assert_true(false, "Migration failed: " . $e->getMessage());
}

// Test 3: AgentService - Create Agent
echo "\n--- Test 3: AgentService - Create Agent ---\n";
try {
    $agentService = new AgentService($db);
    
    $agentData = [
        'name' => 'Test Agent',
        'description' => 'A test agent',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7,
        'tools' => [['type' => 'file_search']],
        'vector_store_ids' => ['vs_test123'],
        'is_default' => true
    ];
    
    $agent = $agentService->createAgent($agentData);
    
    assert_not_null($agent, "Agent created");
    assert_not_null($agent['id'], "Agent has ID");
    assert_equals('Test Agent', $agent['name'], "Agent name matches");
    assert_equals('responses', $agent['api_type'], "Agent api_type matches");
    assert_equals(true, $agent['is_default'], "Agent is default");
    assert_not_null($agent['tools'], "Agent has tools");
    assert_not_null($agent['vector_store_ids'], "Agent has vector_store_ids");
    
    $createdAgentId = $agent['id'];
} catch (Exception $e) {
    assert_true(false, "Create agent failed: " . $e->getMessage());
    $createdAgentId = null;
}

// Test 4: AgentService - Get Agent
echo "\n--- Test 4: AgentService - Get Agent ---\n";
if ($createdAgentId) {
    try {
        $agent = $agentService->getAgent($createdAgentId);
        assert_not_null($agent, "Agent retrieved");
        assert_equals($createdAgentId, $agent['id'], "Agent ID matches");
    } catch (Exception $e) {
        assert_true(false, "Get agent failed: " . $e->getMessage());
    }
}

// Test 5: AgentService - List Agents
echo "\n--- Test 5: AgentService - List Agents ---\n";
try {
    $agents = $agentService->listAgents();
    assert_true(count($agents) > 0, "Agents list not empty");
    assert_equals($createdAgentId, $agents[0]['id'], "First agent matches created agent");
} catch (Exception $e) {
    assert_true(false, "List agents failed: " . $e->getMessage());
}

// Test 6: AgentService - Get Default Agent
echo "\n--- Test 6: AgentService - Get Default Agent ---\n";
try {
    $defaultAgent = $agentService->getDefaultAgent();
    assert_not_null($defaultAgent, "Default agent exists");
    assert_equals($createdAgentId, $defaultAgent['id'], "Default agent is the created agent");
} catch (Exception $e) {
    assert_true(false, "Get default agent failed: " . $e->getMessage());
}

// Test 7: AgentService - Create Second Agent
echo "\n--- Test 7: AgentService - Create Second Agent ---\n";
try {
    $agentData2 = [
        'name' => 'Second Agent',
        'api_type' => 'chat',
        'system_message' => 'You are a helpful assistant.',
        'is_default' => false
    ];
    
    $agent2 = $agentService->createAgent($agentData2);
    assert_not_null($agent2, "Second agent created");
    assert_equals('Second Agent', $agent2['name'], "Second agent name matches");
    assert_equals(false, $agent2['is_default'], "Second agent is not default");
    
    $secondAgentId = $agent2['id'];
} catch (Exception $e) {
    assert_true(false, "Create second agent failed: " . $e->getMessage());
    $secondAgentId = null;
}

// Test 8: AgentService - Set Default Agent (Atomicity Test)
echo "\n--- Test 8: AgentService - Set Default Agent (Atomicity) ---\n";
if ($secondAgentId) {
    try {
        $agentService->setDefaultAgent($secondAgentId);
        
        // Check that only the second agent is default
        $agents = $agentService->listAgents();
        $defaultCount = 0;
        foreach ($agents as $agent) {
            if ($agent['is_default']) {
                $defaultCount++;
                assert_equals($secondAgentId, $agent['id'], "New default agent is second agent");
            }
        }
        assert_equals(1, $defaultCount, "Only one default agent exists");
    } catch (Exception $e) {
        assert_true(false, "Set default agent failed: " . $e->getMessage());
    }
}

// Test 9: AgentService - Update Agent
echo "\n--- Test 9: AgentService - Update Agent ---\n";
if ($createdAgentId) {
    try {
        $updateData = [
            'description' => 'Updated description',
            'temperature' => 0.9
        ];
        
        $updatedAgent = $agentService->updateAgent($createdAgentId, $updateData);
        assert_equals('Updated description', $updatedAgent['description'], "Agent description updated");
        assert_equals(0.9, $updatedAgent['temperature'], "Agent temperature updated");
    } catch (Exception $e) {
        assert_true(false, "Update agent failed: " . $e->getMessage());
    }
}

// Test 10: AgentService - Delete Agent
echo "\n--- Test 10: AgentService - Delete Agent ---\n";
if ($secondAgentId) {
    try {
        $result = $agentService->deleteAgent($secondAgentId);
        assert_true($result, "Agent deleted");
        
        // Verify it's gone
        $deletedAgent = $agentService->getAgent($secondAgentId);
        assert_true($deletedAgent === null, "Deleted agent not found");
    } catch (Exception $e) {
        assert_true(false, "Delete agent failed: " . $e->getMessage());
    }
}

// Test 11: AgentService - Validation Tests
echo "\n--- Test 11: AgentService - Validation Tests ---\n";
try {
    // Test missing name
    try {
        $agentService->createAgent(['api_type' => 'chat']);
        assert_true(false, "Should fail without name");
    } catch (Exception $e) {
        assert_true(strpos($e->getMessage(), 'name') !== false, "Validates name is required");
    }
    
    // Test invalid api_type
    try {
        $agentService->createAgent(['name' => 'Test', 'api_type' => 'invalid']);
        assert_true(false, "Should fail with invalid api_type");
    } catch (Exception $e) {
        assert_true(strpos($e->getMessage(), 'api_type') !== false, "Validates api_type");
    }
    
    // Test invalid temperature
    try {
        $agentService->createAgent(['name' => 'Test', 'temperature' => 3.0]);
        assert_true(false, "Should fail with invalid temperature");
    } catch (Exception $e) {
        assert_true(strpos($e->getMessage(), 'temperature') !== false || strpos($e->getMessage(), 'Temperature') !== false, "Validates temperature range");
    }
} catch (Exception $e) {
    assert_true(false, "Validation tests failed: " . $e->getMessage());
}

// Cleanup
try {
    if (file_exists($dbConfig['database_path'])) {
        unlink($dbConfig['database_path']);
    }
} catch (Exception $e) {
    // Ignore cleanup errors
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Total tests passed: $testsPassed\n";
echo "Total tests failed: $testsFailed\n";

if ($testsFailed > 0) {
    echo "\n❌ Some tests failed!\n";
    exit(1);
} else {
    echo "\n✅ All tests passed!\n";
    exit(0);
}
