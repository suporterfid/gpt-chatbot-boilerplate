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