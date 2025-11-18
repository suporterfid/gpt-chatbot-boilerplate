#!/usr/bin/env php
<?php
/**
 * Test: Agent Slug (vanity_path) functionality
 * 
 * Tests the slug/vanity_path feature for agents:
 * - Creating agents with slugs
 * - Updating agent slugs
 * - Slug uniqueness validation
 * - Slug format validation
 * - Getting agents by slug
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

echo "\n=== Testing Agent Slug Functionality ===\n";

$db = new DB($config);
$agentService = new AgentService($db);

// Helper function to generate unique test names
function generateTestName($prefix = 'test-slug-agent') {
    return $prefix . '-' . time() . '-' . bin2hex(random_bytes(4));
}

// Test 1: Create agent with valid slug
echo "\n--- Test 1: Create agent with valid slug ---\n";
try {
    $testName = generateTestName();
    $agent = $agentService->createAgent([
        'name' => $testName,
        'slug' => 'test-agent-slug',
        'api_type' => 'responses',
        'description' => 'Test agent with slug'
    ]);
    
    if ($agent && isset($agent['vanity_path']) && $agent['vanity_path'] === 'test-agent-slug') {
        echo "✓ PASS: Agent created with slug: {$agent['vanity_path']}\n";
        $testAgentId1 = $agent['id'];
    } else {
        echo "✗ FAIL: Agent slug not set correctly\n";
        echo "   Expected: test-agent-slug\n";
        echo "   Got: " . ($agent['vanity_path'] ?? 'null') . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception creating agent with slug: {$e->getMessage()}\n";
    exit(1);
}

// Test 2: Create agent with vanity_path field name
echo "\n--- Test 2: Create agent using vanity_path field ---\n";
try {
    $testName = generateTestName();
    $agent = $agentService->createAgent([
        'name' => $testName,
        'vanity_path' => 'test-vanity-path',
        'api_type' => 'responses'
    ]);
    
    if ($agent && isset($agent['vanity_path']) && $agent['vanity_path'] === 'test-vanity-path') {
        echo "✓ PASS: Agent created with vanity_path: {$agent['vanity_path']}\n";
        $testAgentId2 = $agent['id'];
    } else {
        echo "✗ FAIL: Agent vanity_path not set correctly\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Test 3: Slug sanitization and normalization
echo "\n--- Test 3: Test slug sanitization ---\n";
try {
    $testName = generateTestName();
    $agent = $agentService->createAgent([
        'name' => $testName,
        'slug' => 'Test Agent With Spaces',
        'api_type' => 'responses'
    ]);
    
    if ($agent && isset($agent['vanity_path']) && $agent['vanity_path'] === 'test-agent-with-spaces') {
        echo "✓ PASS: Slug sanitized correctly: {$agent['vanity_path']}\n";
        $testAgentId3 = $agent['id'];
    } else {
        echo "✗ FAIL: Slug sanitization failed\n";
        echo "   Expected: test-agent-with-spaces\n";
        echo "   Got: " . ($agent['vanity_path'] ?? 'null') . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Test 4: Create agent without slug (should be allowed)
echo "\n--- Test 4: Create agent without slug ---\n";
try {
    $testName = generateTestName();
    $agent = $agentService->createAgent([
        'name' => $testName,
        'api_type' => 'responses'
    ]);
    
    if ($agent && $agent['vanity_path'] === null) {
        echo "✓ PASS: Agent created without slug\n";
        $testAgentId4 = $agent['id'];
    } else {
        echo "✗ FAIL: Expected null vanity_path for agent without slug\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Test 5: Slug uniqueness - try to create duplicate
echo "\n--- Test 5: Test slug uniqueness validation ---\n";
try {
    $testName = generateTestName();
    $agent = $agentService->createAgent([
        'name' => $testName,
        'slug' => 'test-agent-slug', // Duplicate from Test 1
        'api_type' => 'responses'
    ]);
    
    echo "✗ FAIL: Duplicate slug was allowed (should have thrown exception)\n";
    exit(1);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'already in use') || $e->getCode() === 409) {
        echo "✓ PASS: Duplicate slug rejected: {$e->getMessage()}\n";
    } else {
        echo "✗ FAIL: Wrong exception for duplicate slug: {$e->getMessage()}\n";
        exit(1);
    }
}

// Test 6: Invalid slug format - too short
echo "\n--- Test 6: Test invalid slug format (too short) ---\n";
try {
    $testName = generateTestName();
    $agent = $agentService->createAgent([
        'name' => $testName,
        'slug' => 'ab', // Only 2 characters, minimum is 3
        'api_type' => 'responses'
    ]);
    
    echo "✗ FAIL: Invalid slug format was allowed\n";
    exit(1);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'Invalid slug')) {
        echo "✓ PASS: Invalid slug rejected: {$e->getMessage()}\n";
    } else {
        echo "✗ FAIL: Wrong exception for invalid slug: {$e->getMessage()}\n";
        exit(1);
    }
}

// Test 7: Invalid slug format - invalid characters
echo "\n--- Test 7: Test invalid slug format (invalid chars) ---\n";
try {
    $testName = generateTestName();
    $agent = $agentService->createAgent([
        'name' => $testName,
        'slug' => 'test_underscore', // Underscores not allowed
        'api_type' => 'responses'
    ]);
    
    // Sanitization will remove underscores, resulting in 'testunderscore'
    // This should be accepted as a valid slug
    if ($agent && $agent['vanity_path'] === 'testunderscore') {
        echo "✓ PASS: Slug with underscores sanitized to: {$agent['vanity_path']}\n";
        $testAgentId5 = $agent['id'];
    } else {
        echo "✗ FAIL: Slug sanitization unexpected result\n";
        echo "   Expected: testunderscore\n";
        echo "   Got: " . ($agent['vanity_path'] ?? 'null') . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Test 8: Get agent by slug
echo "\n--- Test 8: Get agent by slug ---\n";
try {
    $agent = $agentService->getAgentBySlug('test-agent-slug');
    
    if ($agent && $agent['id'] === $testAgentId1) {
        echo "✓ PASS: Agent retrieved by slug: {$agent['name']}\n";
    } else {
        echo "✗ FAIL: Wrong agent retrieved or not found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Test 9: Update agent slug
echo "\n--- Test 9: Update agent slug ---\n";
try {
    $updatedAgent = $agentService->updateAgent($testAgentId1, [
        'slug' => 'updated-slug'
    ]);
    
    if ($updatedAgent && $updatedAgent['vanity_path'] === 'updated-slug') {
        echo "✓ PASS: Agent slug updated: {$updatedAgent['vanity_path']}\n";
    } else {
        echo "✗ FAIL: Agent slug not updated correctly\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Test 10: Update to duplicate slug (should fail)
echo "\n--- Test 10: Test update to duplicate slug ---\n";
try {
    $updatedAgent = $agentService->updateAgent($testAgentId2, [
        'slug' => 'updated-slug' // Duplicate from Test 9
    ]);
    
    echo "✗ FAIL: Duplicate slug update was allowed\n";
    exit(1);
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'already in use') || $e->getCode() === 409) {
        echo "✓ PASS: Duplicate slug update rejected: {$e->getMessage()}\n";
    } else {
        echo "✗ FAIL: Wrong exception: {$e->getMessage()}\n";
        exit(1);
    }
}

// Test 11: Update agent to remove slug (set to null)
echo "\n--- Test 11: Remove slug from agent ---\n";
try {
    $updatedAgent = $agentService->updateAgent($testAgentId3, [
        'slug' => null
    ]);
    
    if ($updatedAgent && $updatedAgent['vanity_path'] === null) {
        echo "✓ PASS: Agent slug removed (set to null)\n";
    } else {
        echo "✗ FAIL: Agent slug not removed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Test 12: Update agent to remove slug (empty string)
echo "\n--- Test 12: Remove slug using empty string ---\n";
try {
    $updatedAgent = $agentService->updateAgent($testAgentId5, [
        'slug' => ''
    ]);
    
    if ($updatedAgent && $updatedAgent['vanity_path'] === null) {
        echo "✓ PASS: Agent slug removed with empty string\n";
    } else {
        echo "✗ FAIL: Agent slug not removed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: {$e->getMessage()}\n";
    exit(1);
}

// Cleanup: Delete test agents
echo "\n--- Cleanup: Deleting test agents ---\n";
$testAgentIds = [$testAgentId1, $testAgentId2, $testAgentId3, $testAgentId4, $testAgentId5];
$deletedCount = 0;
foreach ($testAgentIds as $id) {
    try {
        if (isset($id)) {
            $agentService->deleteAgent($id);
            $deletedCount++;
        }
    } catch (Exception $e) {
        echo "Warning: Failed to delete test agent $id: {$e->getMessage()}\n";
    }
}
echo "Deleted $deletedCount test agent(s)\n";

echo "\n=== All Agent Slug Tests Passed ===\n";
exit(0);
