#!/usr/bin/env php
<?php
/**
 * Test that the test_agent endpoint returns agent name correctly with UTF-8 encoding
 */

echo "=== Test Agent Name in Response ===\n\n";

// Test credentials (generated for testing only)
const TEST_TOKEN = 'test_admin_token_for_utf8_testing_min32chars';

// Set up .env file for testing
$envContent = "ADMIN_TOKEN=" . TEST_TOKEN . "\n";
$envContent .= "OPENAI_API_KEY=test_key\n";
$envPath = __DIR__ . '/../.env';
$envBackupPath = __DIR__ . '/../.env.backup.utf8test';

// Backup existing .env if it exists
if (file_exists($envPath)) {
    copy($envPath, $envBackupPath);
    echo "Backed up existing .env\n";
}

// Write test .env
file_put_contents($envPath, $envContent);
echo "Created test .env with ADMIN_TOKEN\n\n";

// Start PHP built-in server
$port = 9997;
$serverLog = '/tmp/test_agent_utf8_server.log';
$baseDir = escapeshellarg(__DIR__ . "/..");
$cmd = "php -S localhost:$port -t $baseDir > $serverLog 2>&1 & echo \$!";
$pid = trim(shell_exec($cmd));
sleep(3); // Wait for server to start

echo "Started test server (PID: $pid) on port $port\n\n";

$baseUrl = "http://localhost:$port";

$testsPassed = 0;
$testsFailed = 0;

// Set test token for authentication
putenv("ADMIN_TOKEN=" . TEST_TOKEN);
$_ENV['ADMIN_TOKEN'] = TEST_TOKEN;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

$dbConfig = [
    'database_url' => null,
    'database_path' => __DIR__ . '/../data/chatbot.db'
];

$db = new DB($dbConfig);

// Run migrations
try {
    $db->runMigrations(__DIR__ . '/../db/migrations');
} catch (Exception $e) {
    // Migrations might already be run, continue
}

$agentService = new AgentService($db);

// Test 1: Create agent with simple ASCII name
echo "--- Test 1: Agent with ASCII name ---\n";
$testAgent1 = $agentService->createAgent([
    'name' => 'Customer Support Agent',
    'description' => 'Test agent for ASCII name',
    'api_type' => 'chat',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7,
    'system_prompt' => 'You are a helpful assistant.',
    'is_default' => false
]);

$agentId1 = $testAgent1['id'];
echo "Created test agent: $agentId1 with name '{$testAgent1['name']}'\n";

// Test the endpoint with SSE
$url1 = "$baseUrl/admin-api.php?action=test_agent&id=$agentId1&token=" . urlencode(TEST_TOKEN);

$ch = curl_init($url1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode1\n";

// Parse SSE response to find the start event
$lines = explode("\n", $response1);
$startEventData = null;

for ($i = 0; $i < count($lines); $i++) {
    if (trim($lines[$i]) === 'event: message' && isset($lines[$i + 1])) {
        $dataLine = $lines[$i + 1];
        if (strpos($dataLine, 'data: ') === 0) {
            $jsonData = substr($dataLine, 6);
            $data = json_decode($jsonData, true);
            if ($data && isset($data['type']) && $data['type'] === 'start') {
                $startEventData = $data;
                break;
            }
        }
    }
}

if ($startEventData !== null) {
    echo "✓ Found start event\n";
    
    // Check for agent_name field
    if (isset($startEventData['agent_name'])) {
        echo "✓ agent_name field is present: '{$startEventData['agent_name']}'\n";
        
        if ($startEventData['agent_name'] === $testAgent1['name']) {
            echo "✓ PASS: agent_name matches expected value\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: agent_name mismatch. Expected '{$testAgent1['name']}', got '{$startEventData['agent_name']}'\n";
            $testsFailed++;
        }
    } else {
        echo "✗ FAIL: agent_name field is missing from start event\n";
        echo "  Available fields: " . implode(', ', array_keys($startEventData)) . "\n";
        $testsFailed++;
    }
    
    // Check for new agent object structure
    if (isset($startEventData['agent'])) {
        echo "✓ agent object is present\n";
        
        if (isset($startEventData['agent']['name'])) {
            echo "✓ agent.name field is present: '{$startEventData['agent']['name']}'\n";
            
            if ($startEventData['agent']['name'] === $testAgent1['name']) {
                echo "✓ PASS: agent.name matches expected value\n";
                $testsPassed++;
            } else {
                echo "✗ FAIL: agent.name mismatch\n";
                $testsFailed++;
            }
        } else {
            echo "✗ FAIL: agent.name field is missing\n";
            $testsFailed++;
        }
    } else {
        echo "✗ FAIL: agent object is missing from start event\n";
        $testsFailed++;
    }
} else {
    echo "✗ FAIL: Could not find start event in response\n";
    echo "  Response preview: " . substr($response1, 0, 200) . "\n";
    $testsFailed++;
}

// Clean up test agent 1
$agentService->deleteAgent($agentId1);
echo "Deleted test agent: $agentId1\n\n";

// Test 2: Create agent with UTF-8 characters (Portuguese with accents)
echo "--- Test 2: Agent with UTF-8 characters (Portuguese) ---\n";
$testAgent2 = $agentService->createAgent([
    'name' => 'Agente de Suporte em Português',
    'description' => 'Test agent for UTF-8 encoding',
    'api_type' => 'chat',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7,
    'system_prompt' => 'Você é um assistente útil.',
    'is_default' => false
]);

$agentId2 = $testAgent2['id'];
echo "Created test agent: $agentId2 with name '{$testAgent2['name']}'\n";

// Test the endpoint with SSE
$url2 = "$baseUrl/admin-api.php?action=test_agent&id=$agentId2&token=" . urlencode(TEST_TOKEN);

$ch = curl_init($url2);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response2 = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode2\n";

// Parse SSE response to find the start event
$lines = explode("\n", $response2);
$startEventData = null;

for ($i = 0; $i < count($lines); $i++) {
    if (trim($lines[$i]) === 'event: message' && isset($lines[$i + 1])) {
        $dataLine = $lines[$i + 1];
        if (strpos($dataLine, 'data: ') === 0) {
            $jsonData = substr($dataLine, 6);
            $data = json_decode($jsonData, true);
            if ($data && isset($data['type']) && $data['type'] === 'start') {
                $startEventData = $data;
                break;
            }
        }
    }
}

if ($startEventData !== null) {
    echo "✓ Found start event\n";
    
    // Check for agent_name field
    if (isset($startEventData['agent_name'])) {
        echo "✓ agent_name field is present: '{$startEventData['agent_name']}'\n";
        
        if ($startEventData['agent_name'] === $testAgent2['name']) {
            echo "✓ PASS: UTF-8 agent_name matches expected value (no encoding issues)\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: UTF-8 agent_name mismatch or encoding issue\n";
            echo "  Expected: '{$testAgent2['name']}'\n";
            echo "  Got: '{$startEventData['agent_name']}'\n";
            $testsFailed++;
        }
    } else {
        echo "✗ FAIL: agent_name field is missing from start event\n";
        $testsFailed++;
    }
    
    // Check for new agent object structure with UTF-8
    if (isset($startEventData['agent']) && isset($startEventData['agent']['name'])) {
        echo "✓ agent.name field is present: '{$startEventData['agent']['name']}'\n";
        
        if ($startEventData['agent']['name'] === $testAgent2['name']) {
            echo "✓ PASS: UTF-8 agent.name matches expected value (no encoding issues)\n";
            $testsPassed++;
        } else {
            echo "✗ FAIL: UTF-8 agent.name mismatch or encoding issue\n";
            $testsFailed++;
        }
    } else {
        echo "✗ FAIL: agent.name field is missing\n";
        $testsFailed++;
    }
} else {
    echo "✗ FAIL: Could not find start event in response\n";
    echo "  Response preview: " . substr($response2, 0, 200) . "\n";
    $testsFailed++;
}

// Clean up test agent 2
$agentService->deleteAgent($agentId2);
echo "Deleted test agent: $agentId2\n\n";

// Stop server
shell_exec("kill $pid 2>/dev/null");
echo "Stopped test server (PID: $pid)\n";

// Restore .env
if (file_exists($envBackupPath)) {
    rename($envBackupPath, $envPath);
    echo "Restored original .env\n";
} else {
    unlink($envPath);
    echo "Removed test .env\n";
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
