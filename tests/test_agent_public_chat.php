<?php
/**
 * Test Agent Public Chat Page Access
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

echo "\n=== Testing Agent Public Chat Page Access ===\n";

// Initialize database connection
$dbConfig = [
    'database_url' => $config['admin']['database_url'] ?? '',
    'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db',
];
$db = new DB($dbConfig);
$agentService = new AgentService($db);

// Clean up any test agents from previous runs
echo "\n--- Cleanup: Removing any existing test agents ---\n";
try {
    $testAgents = $db->query("SELECT id FROM agents WHERE name LIKE 'Test Public Chat%'");
    foreach ($testAgents as $agent) {
        $db->execute("DELETE FROM agents WHERE id = ?", [$agent['id']]);
        echo "Removed test agent: {$agent['id']}\n";
    }
} catch (Exception $e) {
    echo "Cleanup note: " . $e->getMessage() . "\n";
}

// Test 1: Create agent with slug
echo "\n--- Test 1: Create agent with slug ---\n";
try {
    $agent = $agentService->createAgent([
        'name' => 'Test Public Chat Agent',
        'slug' => 'test-public-chat',
        'description' => 'Test agent for public chat access',
        'api_type' => 'responses'
    ]);
    
    if ($agent && $agent['slug'] === 'test-public-chat') {
        echo "✓ PASS: Agent created with slug 'test-public-chat'\n";
        echo "  Agent ID: {$agent['id']}\n";
        echo "  Agent Slug: {$agent['slug']}\n";
    } else {
        echo "✗ FAIL: Agent slug not set correctly\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Retrieve agent by slug using getAgentBySlug
echo "\n--- Test 2: Retrieve agent by slug ---\n";
try {
    $foundAgent = $agentService->getAgentBySlug('test-public-chat');
    
    if ($foundAgent) {
        if ($foundAgent['id'] === $agent['id'] && $foundAgent['slug'] === 'test-public-chat') {
            echo "✓ PASS: Agent retrieved by slug correctly\n";
            echo "  Retrieved Agent ID: {$foundAgent['id']}\n";
            echo "  Retrieved Agent Name: {$foundAgent['name']}\n";
        } else {
            echo "✗ FAIL: Retrieved agent does not match\n";
            exit(1);
        }
    } else {
        echo "✗ FAIL: Agent not found by slug\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Try to retrieve non-existent slug
echo "\n--- Test 3: Try to retrieve non-existent slug ---\n";
try {
    $notFound = $agentService->getAgentBySlug('non-existent-slug');
    
    if ($notFound === null) {
        echo "✓ PASS: Non-existent slug returns null\n";
    } else {
        echo "✗ FAIL: Non-existent slug should return null\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Try to retrieve with empty slug
echo "\n--- Test 4: Try to retrieve with empty slug ---\n";
try {
    $emptyResult = $agentService->getAgentBySlug('');
    
    if ($emptyResult === null) {
        echo "✓ PASS: Empty slug returns null\n";
    } else {
        echo "✗ FAIL: Empty slug should return null\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Test public URL configuration
echo "\n--- Test 5: Verify public URL configuration ---\n";
$slugBasePath = $config['agent_public_access']['slug_base_path'] ?? null;
if ($slugBasePath === '/a') {
    echo "✓ PASS: Public URL base path configured correctly: {$slugBasePath}\n";
    echo "  Agent public URL would be: {$slugBasePath}/{$agent['slug']}\n";
} else {
    echo "⚠ WARNING: Public URL base path not configured or incorrect: " . ($slugBasePath ?? 'not set') . "\n";
}

// Cleanup: Remove test agent
echo "\n--- Cleanup: Removing test agent ---\n";
try {
    $db->execute("DELETE FROM agents WHERE id = ?", [$agent['id']]);
    echo "Removed test agent: {$agent['id']}\n";
} catch (Exception $e) {
    echo "Cleanup error: " . $e->getMessage() . "\n";
}

echo "\n=== All Agent Public Chat tests passed ===\n";
