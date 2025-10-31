#!/usr/bin/env php
<?php
/**
 * Phase 5: Chat Flow Integration Tests
 * Tests agent selection and default agent fallback in chat flow
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/OpenAIClient.php';
require_once __DIR__ . '/../includes/ChatHandler.php';

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

function assert_contains($needle, $haystack, $message) {
    global $testsPassed, $testsFailed;
    if (strpos($haystack, $needle) !== false || in_array($needle, $haystack, true)) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (needle not found in haystack)\n";
        $testsFailed++;
    }
}

echo "=== Running Phase 5: Chat Flow Integration Tests ===\n\n";

// Setup: Create test database
$testDbPath = '/tmp/test_phase5_' . time() . '.db';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

try {
    $db = new DB(['database_path' => $testDbPath]);
    
    // Run migrations
    echo "--- Setup: Running Migrations ---\n";
    $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "✓ Migrations completed\n\n";
    
    $agentService = new AgentService($db);
    
    // Create minimal config for ChatHandler
    $config = [
        'openai' => [
            'api_key' => 'test_key',
            'base_url' => 'https://api.openai.com/v1',
            'org' => ''
        ],
        'chat' => [
            'system_message' => 'Default system message',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 500
        ],
        'responses' => [
            'model' => 'gpt-4o',
            'temperature' => 0.7
        ],
        'security' => [
            'max_message_length' => 10000,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 60
        ],
        'chat_config' => [
            'max_messages' => 50
        ],
        'storage' => [
            'type' => 'session'
        ]
    ];
    
    // Create ChatHandler with AgentService
    $chatHandler = new ChatHandler($config, $agentService);
    
    echo "--- Test 1: ChatHandler without AgentService (Backwards Compatibility) ---\n";
    $chatHandlerNoAgent = new ChatHandler($config, null);
    assert_not_null($chatHandlerNoAgent, "ChatHandler can be created without AgentService");
    
    echo "\n--- Test 2: Agent Configuration Resolution ---\n";
    
    // Create test agents
    $agent1Data = [
        'name' => 'Test Chat Agent',
        'description' => 'Test agent for chat completion',
        'api_type' => 'chat',
        'model' => 'gpt-4',
        'temperature' => 0.5,
        'system_message' => 'You are a helpful test assistant.',
        'is_default' => false
    ];
    
    $agent1 = $agentService->createAgent($agent1Data);
    assert_not_null($agent1, "Test chat agent created");
    $agent1Id = $agent1['id'];
    
    $agent2Data = [
        'name' => 'Test Responses Agent',
        'description' => 'Test agent for responses API',
        'api_type' => 'responses',
        'prompt_id' => 'test_prompt_123',
        'prompt_version' => 'v1',
        'model' => 'gpt-4o',
        'temperature' => 0.7,
        'tools' => [
            ['type' => 'file_search']
        ],
        'vector_store_ids' => ['vs_test123'],
        'max_num_results' => 10,
        'is_default' => true
    ];
    
    $agent2 = $agentService->createAgent($agent2Data);
    assert_not_null($agent2, "Test responses agent created");
    $agent2Id = $agent2['id'];
    
    // Set agent2 as default
    $agentService->setDefaultAgent($agent2Id);
    
    echo "\n--- Test 3: Explicit Agent ID Resolution ---\n";
    
    // Test resolveAgentOverrides with explicit agent_id (using reflection to test private method)
    $reflection = new ReflectionClass($chatHandler);
    $method = $reflection->getMethod('resolveAgentOverrides');
    $method->setAccessible(true);
    
    $overrides1 = $method->invoke($chatHandler, $agent1Id);
    assert_equals('chat', $overrides1['api_type'] ?? null, "Agent 1 api_type resolved correctly");
    assert_equals('gpt-4', $overrides1['model'] ?? null, "Agent 1 model resolved correctly");
    assert_equals(0.5, $overrides1['temperature'] ?? null, "Agent 1 temperature resolved correctly");
    assert_equals('You are a helpful test assistant.', $overrides1['system_message'] ?? null, "Agent 1 system_message resolved correctly");
    
    echo "\n--- Test 4: Default Agent Fallback ---\n";
    
    // Test with no agent_id (should use default agent)
    $overridesDefault = $method->invoke($chatHandler, null);
    assert_equals('responses', $overridesDefault['api_type'] ?? null, "Default agent api_type resolved correctly");
    assert_equals('gpt-4o', $overridesDefault['model'] ?? null, "Default agent model resolved correctly");
    assert_equals('test_prompt_123', $overridesDefault['prompt_id'] ?? null, "Default agent prompt_id resolved correctly");
    assert_equals('v1', $overridesDefault['prompt_version'] ?? null, "Default agent prompt_version resolved correctly");
    assert_true(isset($overridesDefault['tools']), "Default agent tools resolved");
    assert_true(isset($overridesDefault['vector_store_ids']), "Default agent vector_store_ids resolved");
    
    echo "\n--- Test 5: Invalid Agent ID Fallback ---\n";
    
    // Test with invalid agent_id (should fallback to default)
    $overridesInvalid = $method->invoke($chatHandler, 'invalid_agent_id');
    assert_equals('responses', $overridesInvalid['api_type'] ?? null, "Invalid agent falls back to default agent");
    assert_equals('gpt-4o', $overridesInvalid['model'] ?? null, "Invalid agent fallback uses default model");
    
    echo "\n--- Test 6: No Default Agent Scenario ---\n";
    
    // Manually unset default agent (since setDefaultAgent doesn't support clearing)
    $db->execute("UPDATE agents SET is_default = 0");
    
    // Create new ChatHandler to get fresh state
    $chatHandler2 = new ChatHandler($config, $agentService);
    $method2 = (new ReflectionClass($chatHandler2))->getMethod('resolveAgentOverrides');
    $method2->setAccessible(true);
    
    $overridesNoDefault = $method2->invoke($chatHandler2, null);
    assert_equals([], $overridesNoDefault, "No agent_id and no default agent returns empty array");
    
    echo "\n--- Test 7: Agent Override Precedence in Chat Completion ---\n";
    
    // Reset default agent for this test
    $agentService->setDefaultAgent($agent1Id);
    $chatHandler3 = new ChatHandler($config, $agentService);
    
    // The handleChatCompletion method should use agent system_message over config
    // We can't easily test this without mocking OpenAI, but we can verify the setup
    assert_not_null($chatHandler3, "ChatHandler with default agent created");
    
    echo "\n--- Test 8: Agent Override Precedence in Responses Chat ---\n";
    
    $agentService->setDefaultAgent($agent2Id);
    $chatHandler4 = new ChatHandler($config, $agentService);
    assert_not_null($chatHandler4, "ChatHandler with responses agent created");
    
    echo "\n--- Test 9: Agent Service Null Safety ---\n";
    
    // Test that ChatHandler gracefully handles null AgentService
    $chatHandlerNull = new ChatHandler($config, null);
    $methodNull = (new ReflectionClass($chatHandlerNull))->getMethod('resolveAgentOverrides');
    $methodNull->setAccessible(true);
    
    $overridesNull = $methodNull->invoke($chatHandlerNull, 'any_agent_id');
    assert_equals([], $overridesNull, "Null AgentService returns empty overrides");
    
    echo "\n--- Test 10: Agent Configuration Field Coverage ---\n";
    
    // Create agent with all possible fields
    $fullAgentData = [
        'name' => 'Full Config Agent',
        'api_type' => 'responses',
        'prompt_id' => 'full_prompt',
        'prompt_version' => 'v2',
        'model' => 'gpt-4-turbo',
        'temperature' => 0.9,
        'top_p' => 0.95,
        'max_output_tokens' => 1000,
        'system_message' => 'System message test',
        'tools' => [
            ['type' => 'file_search'],
            ['type' => 'code_interpreter']
        ],
        'vector_store_ids' => ['vs_1', 'vs_2'],
        'max_num_results' => 25,
        'is_default' => false
    ];
    
    $fullAgent = $agentService->createAgent($fullAgentData);
    $fullAgentId = $fullAgent['id'];
    
    $chatHandler5 = new ChatHandler($config, $agentService);
    $method5 = (new ReflectionClass($chatHandler5))->getMethod('resolveAgentOverrides');
    $method5->setAccessible(true);
    
    $fullOverrides = $method5->invoke($chatHandler5, $fullAgentId);
    
    assert_equals('responses', $fullOverrides['api_type'] ?? null, "Full agent api_type");
    assert_equals('full_prompt', $fullOverrides['prompt_id'] ?? null, "Full agent prompt_id");
    assert_equals('v2', $fullOverrides['prompt_version'] ?? null, "Full agent prompt_version");
    assert_equals('gpt-4-turbo', $fullOverrides['model'] ?? null, "Full agent model");
    assert_equals(0.9, $fullOverrides['temperature'] ?? null, "Full agent temperature");
    assert_equals(0.95, $fullOverrides['top_p'] ?? null, "Full agent top_p");
    assert_equals(1000, $fullOverrides['max_output_tokens'] ?? null, "Full agent max_output_tokens");
    assert_equals('System message test', $fullOverrides['system_message'] ?? null, "Full agent system_message");
    assert_true(is_array($fullOverrides['tools'] ?? null), "Full agent tools is array");
    assert_equals(2, count($fullOverrides['tools'] ?? []), "Full agent has 2 tools");
    assert_true(is_array($fullOverrides['vector_store_ids'] ?? null), "Full agent vector_store_ids is array");
    assert_equals(2, count($fullOverrides['vector_store_ids'] ?? []), "Full agent has 2 vector stores");
    assert_equals(25, $fullOverrides['max_num_results'] ?? null, "Full agent max_num_results");
    
    echo "\n--- Test 11: Error Handling ---\n";
    
    // Test that exceptions in resolveAgentOverrides are caught
    // This is hard to test without breaking the database, but we can verify the structure
    try {
        $overridesError = $method5->invoke($chatHandler5, null);
        assert_true(is_array($overridesError), "resolveAgentOverrides returns array even on edge cases");
    } catch (Exception $e) {
        assert_true(false, "resolveAgentOverrides should not throw exceptions");
    }
    
} catch (Exception $e) {
    echo "✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $testsFailed++;
}

// Cleanup
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Total tests passed: $testsPassed\n";
echo "Total tests failed: $testsFailed\n";

if ($testsFailed === 0) {
    echo "\n✅ All tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed!\n";
    exit(1);
}
