<?php
/**
 * Test Agent Slug Functionality
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

echo "\n=== Testing Agent Slug Functionality ===\n";

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
    $testAgents = $db->query("SELECT id FROM agents WHERE name LIKE 'Test Agent Slug%'");
    foreach ($testAgents as $agent) {
        $db->execute("DELETE FROM agents WHERE id = ?", [$agent['id']]);
        echo "Removed test agent: {$agent['id']}\n";
    }
} catch (Exception $e) {
    echo "Cleanup note: " . $e->getMessage() . "\n";
}

// Test 1: Create agent with valid slug
echo "\n--- Test 1: Create agent with valid slug ---\n";
try {
    $agent1 = $agentService->createAgent([
        'name' => 'Test Agent Slug 1',
        'slug' => 'test-agent-slug-1',
        'description' => 'Test agent with slug',
        'api_type' => 'responses'
    ]);
    
    if ($agent1 && $agent1['slug'] === 'test-agent-slug-1') {
        echo "✓ PASS: Agent created with slug 'test-agent-slug-1'\n";
        echo "  Agent ID: {$agent1['id']}\n";
        echo "  Agent Name: {$agent1['name']}\n";
        echo "  Agent Slug: {$agent1['slug']}\n";
    } else {
        echo "✗ FAIL: Agent slug not set correctly\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Create agent without slug (should be allowed)
echo "\n--- Test 2: Create agent without slug ---\n";
try {
    $agent2 = $agentService->createAgent([
        'name' => 'Test Agent Slug 2',
        'description' => 'Test agent without slug',
        'api_type' => 'responses'
    ]);
    
    if ($agent2 && ($agent2['slug'] === null || $agent2['slug'] === '')) {
        echo "✓ PASS: Agent created without slug (slug is null/empty)\n";
        echo "  Agent ID: {$agent2['id']}\n";
    } else {
        echo "✗ FAIL: Agent should have null/empty slug\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Try to create agent with duplicate slug (should fail)
echo "\n--- Test 3: Try to create agent with duplicate slug ---\n";
try {
    $agent3 = $agentService->createAgent([
        'name' => 'Test Agent Slug 3',
        'slug' => 'test-agent-slug-1', // Same slug as agent1
        'api_type' => 'responses'
    ]);
    
    echo "✗ FAIL: Should have thrown exception for duplicate slug\n";
    exit(1);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'already in use') !== false || strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
        echo "✓ PASS: Duplicate slug rejected: " . $e->getMessage() . "\n";
    } else {
        echo "✗ FAIL: Wrong error message: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Test 4: Try to create agent with invalid slug format (should fail)
echo "\n--- Test 4: Try to create agent with invalid slug format ---\n";
try {
    $agent4 = $agentService->createAgent([
        'name' => 'Test Agent Slug 4',
        'slug' => 'Invalid Slug With Spaces', // Invalid format
        'api_type' => 'responses'
    ]);
    
    echo "✗ FAIL: Should have thrown exception for invalid slug format\n";
    exit(1);
} catch (Exception $e) {
    if (strpos(strtolower($e->getMessage()), 'slug') !== false) {
        echo "✓ PASS: Invalid slug format rejected: " . $e->getMessage() . "\n";
    } else {
        echo "✗ FAIL: Wrong error message: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Test 5: Update agent slug
echo "\n--- Test 5: Update agent slug ---\n";
try {
    $updated = $agentService->updateAgent($agent2['id'], [
        'slug' => 'updated-slug'
    ]);
    
    if ($updated && $updated['slug'] === 'updated-slug') {
        echo "✓ PASS: Agent slug updated successfully\n";
        echo "  New slug: {$updated['slug']}\n";
    } else {
        echo "✗ FAIL: Agent slug not updated correctly\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Update to duplicate slug (should fail)
echo "\n--- Test 6: Try to update to duplicate slug ---\n";
try {
    $agentService->updateAgent($agent2['id'], [
        'slug' => 'test-agent-slug-1' // Same as agent1
    ]);
    
    echo "✗ FAIL: Should have thrown exception for duplicate slug\n";
    exit(1);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'already in use') !== false || strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
        echo "✓ PASS: Duplicate slug rejected on update: " . $e->getMessage() . "\n";
    } else {
        echo "✗ FAIL: Wrong error message: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Cleanup: Remove test agents
echo "\n--- Cleanup: Removing test agents ---\n";
try {
    $db->execute("DELETE FROM agents WHERE id = ?", [$agent1['id']]);
    echo "Removed test agent 1\n";
    
    $db->execute("DELETE FROM agents WHERE id = ?", [$agent2['id']]);
    echo "Removed test agent 2\n";
} catch (Exception $e) {
    echo "Cleanup error: " . $e->getMessage() . "\n";
}

echo "\n=== All Agent Slug tests passed ===\n";
