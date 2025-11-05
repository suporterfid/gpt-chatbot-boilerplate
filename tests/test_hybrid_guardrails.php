#!/usr/bin/env php
<?php
/**
 * Test Hybrid Guardrails Implementation
 * Tests response_format support with agents, config, and request overrides
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

echo "=== Testing Hybrid Guardrails Implementation ===\n\n";

$dbConfig = [
    'database_url' => $config['admin']['database_url'] ?? '',
    'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db',
];

$db = new DB($dbConfig);
$agentService = new AgentService($db);

$testsPassed = 0;
$testsFailed = 0;

/**
 * Test helper function
 */
function runTest($name, $callable) {
    global $testsPassed, $testsFailed;
    echo "Test: $name ... ";
    try {
        $callable();
        echo "✓ PASSED\n";
        $testsPassed++;
    } catch (Exception $e) {
        echo "✗ FAILED: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
}

// Test 1: Create agent with response_format (JSON schema)
runTest("Create agent with JSON schema response_format", function() use ($agentService) {
    $agent = $agentService->createAgent([
        'name' => 'test_guardrails_bedtime_story_' . uniqid(),
        'description' => 'Test agent with JSON schema guardrails for bedtime stories',
        'api_type' => 'responses',
        'system_message' => 'Always respond strictly according to the JSON schema defined in the request. If unsure, output empty strings instead of free text.',
        'model' => 'gpt-4.1',
        'temperature' => 0.7,
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'bedtime_story',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'story' => ['type' => 'string'],
                        'moral' => ['type' => 'string']
                    ],
                    'required' => ['title', 'story', 'moral']
                ]
            ]
        ]
    ]);
    
    if (!isset($agent['response_format'])) {
        throw new Exception('response_format not set on created agent');
    }
    
    if ($agent['response_format']['type'] !== 'json_schema') {
        throw new Exception('response_format type is not json_schema');
    }
    
    // Clean up
    $agentService->deleteAgent($agent['id']);
});

// Test 2: Create agent with file search and response_format
runTest("Create agent with file_search tools and response_format", function() use ($agentService) {
    $agent = $agentService->createAgent([
        'name' => 'test_guardrails_research_' . uniqid(),
        'description' => 'Test agent with file search and JSON schema',
        'api_type' => 'responses',
        'system_message' => 'You are a research assistant. You must answer ONLY using verified information from the provided files. If unsure, respond with \'insufficient_data\'. Output must follow the JSON schema.',
        'model' => 'gpt-4.1',
        'temperature' => 0,
        'tools' => [
            [
                'type' => 'file_search',
                'vector_store_ids' => ['vs_example_123'],
                'max_num_results' => 10
            ]
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'file_search_answer',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'answer' => ['type' => 'string'],
                        'citations' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'file_name' => ['type' => 'string'],
                                    'snippet' => ['type' => 'string']
                                ],
                                'required' => ['file_name', 'snippet']
                            ]
                        ]
                    ],
                    'required' => ['answer', 'citations'],
                    'additionalProperties' => false
                ]
            ]
        ]
    ]);
    
    if (!isset($agent['response_format'])) {
        throw new Exception('response_format not set on created agent');
    }
    
    if (!isset($agent['tools'])) {
        throw new Exception('tools not set on created agent');
    }
    
    if ($agent['tools'][0]['type'] !== 'file_search') {
        throw new Exception('file_search tool not properly configured');
    }
    
    // Clean up
    $agentService->deleteAgent($agent['id']);
});

// Test 3: Update agent with response_format
runTest("Update agent to add response_format", function() use ($agentService) {
    $agent = $agentService->createAgent([
        'name' => 'test_update_format_' . uniqid(),
        'description' => 'Test agent for updating response_format',
        'api_type' => 'responses'
    ]);
    
    // Update with response_format
    $updated = $agentService->updateAgent($agent['id'], [
        'response_format' => [
            'type' => 'json_object'
        ]
    ]);
    
    if ($updated['response_format']['type'] !== 'json_object') {
        throw new Exception('response_format not updated correctly');
    }
    
    // Clean up
    $agentService->deleteAgent($agent['id']);
});

// Test 4: Validate response_format type
runTest("Validate response_format type must be valid", function() use ($agentService) {
    $exceptionThrown = false;
    try {
        $agent = $agentService->createAgent([
            'name' => 'test_invalid_format_' . uniqid(),
            'description' => 'Test invalid response_format',
            'api_type' => 'responses',
            'response_format' => [
                'type' => 'invalid_type'
            ]
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'response_format type must be one of') !== false) {
            $exceptionThrown = true;
        }
    }
    
    if (!$exceptionThrown) {
        throw new Exception('Expected validation error for invalid response_format type');
    }
});

// Test 5: Validate json_schema must have required fields
runTest("Validate json_schema must have name and schema fields", function() use ($agentService) {
    $exceptionThrown = false;
    try {
        $agent = $agentService->createAgent([
            'name' => 'test_incomplete_schema_' . uniqid(),
            'description' => 'Test incomplete json_schema',
            'api_type' => 'responses',
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'test'
                    // Missing schema field
                ]
            ]
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'json_schema must include schema field') !== false) {
            $exceptionThrown = true;
        }
    }
    
    if (!$exceptionThrown) {
        throw new Exception('Expected validation error for incomplete json_schema');
    }
});

// Test 6: Create agent with hybrid configuration (prompt_id + system_message + response_format)
runTest("Create agent with hybrid config (prompt_id + system_message + response_format)", function() use ($agentService) {
    $agent = $agentService->createAgent([
        'name' => 'test_hybrid_config_' . uniqid(),
        'description' => 'Test hybrid configuration',
        'api_type' => 'responses',
        'prompt_id' => 'pmpt_test123',
        'prompt_version' => '1',
        'system_message' => 'Additional local system instructions',
        'model' => 'gpt-4.1',
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'hybrid_test',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'result' => ['type' => 'string']
                    ],
                    'required' => ['result']
                ]
            ]
        ]
    ]);
    
    if (!isset($agent['prompt_id']) || $agent['prompt_id'] !== 'pmpt_test123') {
        throw new Exception('prompt_id not preserved');
    }
    
    if (!isset($agent['system_message'])) {
        throw new Exception('system_message not preserved');
    }
    
    if (!isset($agent['response_format'])) {
        throw new Exception('response_format not preserved');
    }
    
    // Clean up
    $agentService->deleteAgent($agent['id']);
});

// Test 7: Retrieve agent and verify response_format is properly deserialized
runTest("Retrieve agent and verify response_format deserialization", function() use ($agentService) {
    $agent = $agentService->createAgent([
        'name' => 'test_retrieve_format_' . uniqid(),
        'description' => 'Test response_format retrieval',
        'api_type' => 'responses',
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'test_schema',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'field1' => ['type' => 'string'],
                        'field2' => ['type' => 'number']
                    ]
                ]
            ]
        ]
    ]);
    
    // Retrieve the agent
    $retrieved = $agentService->getAgent($agent['id']);
    
    if (!is_array($retrieved['response_format'])) {
        throw new Exception('response_format not properly deserialized');
    }
    
    if ($retrieved['response_format']['type'] !== 'json_schema') {
        throw new Exception('response_format type not preserved after retrieval');
    }
    
    if (!isset($retrieved['response_format']['json_schema']['schema']['properties']['field1'])) {
        throw new Exception('response_format schema details not preserved');
    }
    
    // Clean up
    $agentService->deleteAgent($agent['id']);
});

// Test 8: List agents with response_format
runTest("List agents and verify response_format in results", function() use ($agentService) {
    $agent = $agentService->createAgent([
        'name' => 'test_list_format_' . uniqid(),
        'description' => 'Test listing with response_format',
        'api_type' => 'responses',
        'response_format' => [
            'type' => 'json_object'
        ]
    ]);
    
    $agents = $agentService->listAgents();
    
    $found = false;
    foreach ($agents as $a) {
        if ($a['id'] === $agent['id']) {
            $found = true;
            if (!isset($a['response_format'])) {
                throw new Exception('response_format not present in list result');
            }
        }
    }
    
    if (!$found) {
        throw new Exception('Created agent not found in list');
    }
    
    // Clean up
    $agentService->deleteAgent($agent['id']);
});

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

if ($testsFailed > 0) {
    echo "\nSome tests failed. Please review the errors above.\n";
    exit(1);
} else {
    echo "\nAll tests passed! ✓\n";
    exit(0);
}
