#!/usr/bin/env php
<?php
/**
 * Test LeadSense CRM Integration
 * 
 * Verifies that new leads are automatically assigned to default pipeline/stage
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/LeadSense/LeadRepository.php';
require_once __DIR__ . '/../includes/LeadSense/CRM/PipelineService.php';

echo "\n=== Testing LeadSense CRM Integration ===\n";

// Setup test database
$dbConfig = [
    'database_path' => '/tmp/test_leadsense_crm_' . time() . '.db'
];
$db = new DB($dbConfig);

// Run migrations
echo "\n--- Setup: Running Migrations ---\n";
try {
    $count = $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "✓ Migrations executed: {$count} files\n";
} catch (Exception $e) {
    echo "✗ FAIL: Migrations failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Create default pipeline
echo "\n--- Setup: Creating Default Pipeline ---\n";
$pipelineService = new PipelineService($db, null);

$pipelineData = [
    'name' => 'Default',
    'description' => 'Default pipeline',
    'is_default' => true,
    'color' => '#8b5cf6',
    'stages' => [
        ['name' => 'Lead Capture', 'slug' => 'lead_capture', 'color' => '#a855f7'],
        ['name' => 'Qualified', 'slug' => 'qualified', 'color' => '#22c55e']
    ]
];

$pipeline = $pipelineService->createPipeline($pipelineData);
if (isset($pipeline['error'])) {
    echo "✗ FAIL: Pipeline creation failed: {$pipeline['error']}\n";
    exit(1);
}

echo "✓ Default pipeline created: {$pipeline['id']}\n";
$pipelineId = $pipeline['id'];
$firstStageId = $pipeline['stages'][0]['id'] ?? null;

if (!$firstStageId) {
    echo "✗ FAIL: No stages in pipeline\n";
    exit(1);
}

echo "✓ First stage ID: {$firstStageId}\n";

// Test 1: Create lead without specifying pipeline/stage
echo "\n--- Test 1: Create Lead (Auto-assign Pipeline) ---\n";
$leadRepo = new LeadRepository(['database_path' => null], null);
$leadRepo->setDb($db);

try {
    $leadData = [
        'agent_id' => 'agent1',
        'conversation_id' => 'conv_test_1',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'company' => 'Test Corp',
        'score' => 75,
        'intent_level' => 'high',
        'qualified' => true,
        'status' => 'new',
        'source_channel' => 'web'
        // Note: NOT specifying pipeline_id or stage_id
    ];
    
    $leadId = $leadRepo->createOrUpdateLead($leadData);
    
    if (!$leadId) {
        echo "✗ FAIL: Lead creation returned empty ID\n";
        exit(1);
    }
    
    echo "✓ PASS: Lead created: {$leadId}\n";
    
    // Verify lead was assigned to pipeline and stage
    $lead = $db->getOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
    
    if (!$lead) {
        echo "✗ FAIL: Lead not found in database\n";
        exit(1);
    }
    
    if ($lead['pipeline_id'] !== $pipelineId) {
        echo "✗ FAIL: Lead not assigned to default pipeline\n";
        echo "  Expected: {$pipelineId}\n";
        echo "  Got: {$lead['pipeline_id']}\n";
        exit(1);
    }
    echo "✓ PASS: Lead assigned to default pipeline\n";
    
    if ($lead['stage_id'] !== $firstStageId) {
        echo "✗ FAIL: Lead not assigned to first stage\n";
        echo "  Expected: {$firstStageId}\n";
        echo "  Got: {$lead['stage_id']}\n";
        exit(1);
    }
    echo "✓ PASS: Lead assigned to first stage\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Create lead with explicit pipeline/stage (should use provided values)
echo "\n--- Test 2: Create Lead (Explicit Pipeline/Stage) ---\n";
try {
    $secondStageId = $pipeline['stages'][1]['id'] ?? null;
    
    if (!$secondStageId) {
        echo "✗ FAIL: Second stage not found\n";
        exit(1);
    }
    
    $leadData = [
        'agent_id' => 'agent1',
        'conversation_id' => 'conv_test_2',
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'score' => 90,
        'intent_level' => 'high',
        'qualified' => true,
        'status' => 'open',
        'source_channel' => 'whatsapp',
        'pipeline_id' => $pipelineId,
        'stage_id' => $secondStageId  // Explicitly set to second stage
    ];
    
    $leadId = $leadRepo->createOrUpdateLead($leadData);
    echo "✓ PASS: Lead created with explicit stage: {$leadId}\n";
    
    // Verify lead was assigned to specified stage
    $lead = $db->getOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
    
    if ($lead['stage_id'] !== $secondStageId) {
        echo "✗ FAIL: Lead not assigned to specified stage\n";
        echo "  Expected: {$secondStageId}\n";
        echo "  Got: {$lead['stage_id']}\n";
        exit(1);
    }
    echo "✓ PASS: Lead assigned to specified stage\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Update existing lead (should preserve pipeline/stage)
echo "\n--- Test 3: Update Existing Lead ---\n";
try {
    $updateData = [
        'agent_id' => 'agent1',
        'conversation_id' => 'conv_test_1',  // Same conversation as Test 1
        'name' => 'John Doe Updated',
        'score' => 80  // Updated score
    ];
    
    $leadId = $leadRepo->createOrUpdateLead($updateData);
    echo "✓ PASS: Lead updated: {$leadId}\n";
    
    // Verify pipeline/stage were preserved
    $lead = $db->getOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
    
    if ($lead['pipeline_id'] !== $pipelineId) {
        echo "✗ FAIL: Pipeline changed during update\n";
        exit(1);
    }
    echo "✓ PASS: Pipeline preserved during update\n";
    
    if ($lead['stage_id'] !== $firstStageId) {
        echo "✗ FAIL: Stage changed during update\n";
        exit(1);
    }
    echo "✓ PASS: Stage preserved during update\n";
    
    if ($lead['name'] !== 'John Doe Updated') {
        echo "✗ FAIL: Name not updated\n";
        exit(1);
    }
    echo "✓ PASS: Lead data updated correctly\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== All LeadSense CRM Integration tests passed ===\n";
exit(0);
