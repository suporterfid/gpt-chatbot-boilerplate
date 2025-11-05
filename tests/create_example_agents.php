#!/usr/bin/env php
<?php
/**
 * Hybrid Guardrails Examples
 * Demonstrates practical use cases for response_format with agents
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

echo "=== Hybrid Guardrails Examples ===\n\n";

$dbConfig = [
    'database_url' => $config['admin']['database_url'] ?? '',
    'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db',
];

$db = new DB($dbConfig);
$agentService = new AgentService($db);

// Example 1: Bedtime Story Generator with JSON Schema
echo "Example 1: Creating Bedtime Story Generator Agent\n";
echo str_repeat('-', 60) . "\n";

$bedtimeAgent = $agentService->createAgent([
    'name' => 'bedtime_story_generator_example',
    'description' => 'Generates bedtime stories with structured output',
    'api_type' => 'responses',
    'model' => 'gpt-4.1',
    'temperature' => 0.7,
    'system_message' => 'Always respond strictly according to the JSON schema defined in the request. If unsure, output empty strings instead of free text.',
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

echo "✓ Created agent: {$bedtimeAgent['name']}\n";
echo "  ID: {$bedtimeAgent['id']}\n";
echo "  Model: {$bedtimeAgent['model']}\n";
echo "  Response Format Type: {$bedtimeAgent['response_format']['type']}\n";
echo "  Schema Name: {$bedtimeAgent['response_format']['json_schema']['name']}\n\n";

echo "Example cURL request:\n";
echo "curl -X POST http://localhost/chat-unified.php \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\n";
echo "    \"message\": \"Create a bedtime story about a unicorn that learns to share.\",\n";
echo "    \"conversation_id\": \"bedtime_001\",\n";
echo "    \"api_type\": \"responses\",\n";
echo "    \"agent_id\": \"{$bedtimeAgent['id']}\"\n";
echo "  }'\n\n";

// Example 2: Research Assistant with File Search
echo "\nExample 2: Creating Research Assistant with File Search\n";
echo str_repeat('-', 60) . "\n";

$researchAgent = $agentService->createAgent([
    'name' => 'research_assistant_example',
    'description' => 'Research assistant with file search and structured citations',
    'api_type' => 'responses',
    'model' => 'gpt-4.1',
    'temperature' => 0,
    'system_message' => 'You are a research assistant. You must answer ONLY using verified information from the provided files. If unsure, respond with \'insufficient_data\'. Output must follow the JSON schema.',
    'tools' => [
        [
            'type' => 'file_search',
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

echo "✓ Created agent: {$researchAgent['name']}\n";
echo "  ID: {$researchAgent['id']}\n";
echo "  Tools: file_search\n";
echo "  Response Format: JSON Schema with citations\n\n";

echo "Example cURL request (with vector store):\n";
echo "curl -X POST http://localhost/chat-unified.php \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\n";
echo "    \"message\": \"What are the main features of the product?\",\n";
echo "    \"conversation_id\": \"research_001\",\n";
echo "    \"api_type\": \"responses\",\n";
echo "    \"agent_id\": \"{$researchAgent['id']}\",\n";
echo "    \"tools\": [{\"type\": \"file_search\", \"vector_store_ids\": [\"vs_YOUR_VECTOR_STORE\"]}]\n";
echo "  }'\n\n";

// Example 3: Hybrid Configuration (prompt_id + system_message + response_format)
echo "\nExample 3: Creating Hybrid Agent (OpenAI Prompt + Local Config)\n";
echo str_repeat('-', 60) . "\n";

$hybridAgent = $agentService->createAgent([
    'name' => 'hybrid_agent_example',
    'description' => 'Combines OpenAI prompt with local guardrails',
    'api_type' => 'responses',
    'prompt_id' => 'pmpt_example123',  // Your OpenAI stored prompt
    'prompt_version' => '1',
    'model' => 'gpt-4.1',
    'system_message' => 'Additional local instructions: Always be concise and to the point.',
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'hybrid_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'result' => ['type' => 'string'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1]
                ],
                'required' => ['result']
            ]
        ]
    ]
]);

echo "✓ Created agent: {$hybridAgent['name']}\n";
echo "  ID: {$hybridAgent['id']}\n";
echo "  Prompt ID: {$hybridAgent['prompt_id']}\n";
echo "  Has local system message: Yes\n";
echo "  Has response format: Yes\n\n";

echo "This agent will:\n";
echo "  1. Use the OpenAI stored prompt (pmpt_example123)\n";
echo "  2. Apply local system message instructions\n";
echo "  3. Enforce JSON schema on output\n\n";

// Example 4: Simple JSON Object Format
echo "\nExample 4: Creating Agent with JSON Object Format\n";
echo str_repeat('-', 60) . "\n";

$jsonAgent = $agentService->createAgent([
    'name' => 'json_object_agent_example',
    'description' => 'Returns responses as JSON objects without strict schema',
    'api_type' => 'responses',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.5,
    'system_message' => 'Always return your response as a valid JSON object with relevant fields.',
    'response_format' => [
        'type' => 'json_object'
    ]
]);

echo "✓ Created agent: {$jsonAgent['name']}\n";
echo "  ID: {$jsonAgent['id']}\n";
echo "  Response Format: JSON Object (flexible schema)\n\n";

echo "This format allows the model to choose the JSON structure.\n\n";

// Example 5: Data Extraction Agent
echo "\nExample 5: Creating Data Extraction Agent\n";
echo str_repeat('-', 60) . "\n";

$extractionAgent = $agentService->createAgent([
    'name' => 'data_extraction_example',
    'description' => 'Extracts structured data from unstructured text',
    'api_type' => 'responses',
    'model' => 'gpt-4.1',
    'temperature' => 0,
    'system_message' => 'Extract all relevant entities from the user input and structure them according to the schema.',
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'data_extraction',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'entities' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['person', 'organization', 'location', 'date', 'other']
                                ],
                                'value' => ['type' => 'string'],
                                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1]
                            ],
                            'required' => ['type', 'value']
                        ]
                    ],
                    'summary' => ['type' => 'string']
                ],
                'required' => ['entities']
            ]
        ]
    ]
]);

echo "✓ Created agent: {$extractionAgent['name']}\n";
echo "  ID: {$extractionAgent['id']}\n";
echo "  Purpose: Extract entities with confidence scores\n\n";

echo "Example input: \"John Smith from Acme Corp visited Paris on June 15th.\"\n";
echo "Expected output: Structured entities with types and confidence scores\n\n";

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "Created 5 Example Agents:\n\n";
echo "1. Bedtime Story Generator: {$bedtimeAgent['id']}\n";
echo "2. Research Assistant: {$researchAgent['id']}\n";
echo "3. Hybrid Agent: {$hybridAgent['id']}\n";
echo "4. JSON Object Agent: {$jsonAgent['id']}\n";
echo "5. Data Extraction Agent: {$extractionAgent['id']}\n\n";

echo "All agents are ready to use!\n";
echo "Use the cURL examples above or integrate via your client application.\n\n";

echo "To clean up these examples, run:\n";
echo "  php tests/cleanup_example_agents.php\n\n";

// Create cleanup script
$cleanupScript = <<<'PHP'
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

$dbConfig = [
    'database_url' => $config['admin']['database_url'] ?? '',
    'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db',
];

$db = new DB($dbConfig);
$agentService = new AgentService($db);

$exampleAgents = [
    'bedtime_story_generator_example',
    'research_assistant_example',
    'hybrid_agent_example',
    'json_object_agent_example',
    'data_extraction_example'
];

echo "Cleaning up example agents...\n";
foreach ($exampleAgents as $name) {
    $agents = $agentService->listAgents(['name' => $name]);
    foreach ($agents as $agent) {
        $agentService->deleteAgent($agent['id']);
        echo "  Deleted: {$agent['name']} ({$agent['id']})\n";
    }
}
echo "Cleanup complete!\n";
PHP;

file_put_contents(__DIR__ . '/cleanup_example_agents.php', $cleanupScript);
chmod(__DIR__ . '/cleanup_example_agents.php', 0755);

echo "Cleanup script created: tests/cleanup_example_agents.php\n";
