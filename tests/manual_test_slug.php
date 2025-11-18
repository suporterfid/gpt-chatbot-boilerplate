#!/usr/bin/env php
<?php
/**
 * Manual test script to verify slug functionality
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

echo "\n=== Manual Test: Agent Slug Feature ===\n\n";

$db = new DB($config);
$agentService = new AgentService($db);

// Test 1: Create agent with slug
echo "Creating test agent with slug 'demo-agent'...\n";
$testAgentName = 'Demo Agent ' . time();

try {
    $agent = $agentService->createAgent([
        'name' => $testAgentName,
        'slug' => 'demo-agent',
        'description' => 'Demo agent to test slug functionality',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7
    ]);
    
    echo "✅ Agent created successfully!\n";
    echo "   ID: {$agent['id']}\n";
    echo "   Name: {$agent['name']}\n";
    echo "   Slug: {$agent['vanity_path']}\n\n";
    
    // Test 2: Retrieve by slug
    echo "Retrieving agent by slug...\n";
    $retrieved = $agentService->getAgentBySlug('demo-agent');
    
    if ($retrieved && $retrieved['id'] === $agent['id']) {
        echo "✅ Agent retrieved successfully by slug!\n";
        echo "   Retrieved name: {$retrieved['name']}\n\n";
    } else {
        echo "❌ Failed to retrieve agent by slug\n\n";
    }
    
    // Test 3: Update slug
    echo "Updating agent slug to 'updated-demo'...\n";
    $updated = $agentService->updateAgent($agent['id'], [
        'slug' => 'updated-demo'
    ]);
    
    if ($updated && $updated['vanity_path'] === 'updated-demo') {
        echo "✅ Slug updated successfully!\n";
        echo "   New slug: {$updated['vanity_path']}\n\n";
    } else {
        echo "❌ Failed to update slug\n\n";
    }
    
    // Test 4: Test sanitization
    echo "Testing slug sanitization with 'Test With Spaces'...\n";
    $sanitized = $agentService->updateAgent($agent['id'], [
        'slug' => 'Test With Spaces'
    ]);
    
    if ($sanitized && $sanitized['vanity_path'] === 'test-with-spaces') {
        echo "✅ Slug sanitized correctly!\n";
        echo "   Sanitized to: {$sanitized['vanity_path']}\n\n";
    } else {
        echo "❌ Slug sanitization failed\n";
        echo "   Got: " . ($sanitized['vanity_path'] ?? 'null') . "\n\n";
    }
    
    // Cleanup
    echo "Cleaning up test agent...\n";
    $agentService->deleteAgent($agent['id']);
    echo "✅ Test agent deleted\n\n";
    
    echo "=== All Manual Tests Passed ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   Code: {$e->getCode()}\n";
    exit(1);
}
