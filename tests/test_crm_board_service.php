#!/usr/bin/env php
<?php
/**
 * Test Suite for CRM BoardService
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/LeadSense/CRM/PipelineService.php';
require_once __DIR__ . '/../includes/LeadSense/CRM/BoardService.php';
require_once __DIR__ . '/../includes/LeadSense/LeadEventTypes.php';

echo "\n=== Testing CRM BoardService ===\n";

// Setup test database
$dbConfig = [
    'database_path' => '/tmp/test_board_service_' . time() . '.db'
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

// Create test pipeline and stages
echo "\n--- Setup: Creating Test Pipeline ---\n";
$pipelineService = new PipelineService($db, null);
$boardService = new BoardService($db, null);

$pipelineData = [
    'name' => 'Test Pipeline',
    'description' => 'Test pipeline for board testing',
    'is_default' => true,
    'color' => '#8b5cf6',
    'stages' => [
        ['name' => 'New', 'slug' => 'new', 'color' => '#a855f7'],
        ['name' => 'In Progress', 'slug' => 'in_progress', 'color' => '#3b82f6'],
        ['name' => 'Won', 'slug' => 'won', 'color' => '#22c55e', 'is_won' => true, 'is_closed' => true]
    ]
];

$pipeline = $pipelineService->createPipeline($pipelineData);
if (isset($pipeline['error'])) {
    echo "✗ FAIL: Pipeline creation failed: {$pipeline['error']}\n";
    exit(1);
}
echo "✓ Pipeline created: {$pipeline['id']}\n";

$pipelineId = $pipeline['id'];
$stages = $pipeline['stages'] ?? [];
if (count($stages) < 3) {
    echo "✗ FAIL: Not enough stages created\n";
    exit(1);
}

$newStageId = $stages[0]['id'];
$inProgressStageId = $stages[1]['id'];
$wonStageId = $stages[2]['id'];

// Create test leads
echo "\n--- Setup: Creating Test Leads ---\n";
$lead1Id = null;
$lead2Id = null;

try {
    $lead1Id = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $db->execute("
        INSERT INTO leads (
            id, agent_id, conversation_id, name, email, company, score, 
            intent_level, qualified, status, source_channel,
            pipeline_id, stage_id,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ", [
        $lead1Id, 'agent1', 'conv1', 'John Doe', 'john@example.com', 'Acme Corp', 85,
        'high', 1, 'open', 'web',
        $pipelineId, $newStageId
    ]);
    
    echo "✓ Lead 1 created: {$lead1Id}\n";
    
    $lead2Id = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $db->execute("
        INSERT INTO leads (
            id, agent_id, conversation_id, name, email, company, score, 
            intent_level, qualified, status, source_channel,
            pipeline_id, stage_id,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ", [
        $lead2Id, 'agent1', 'conv2', 'Jane Smith', 'jane@example.com', 'Tech Inc', 70,
        'medium', 1, 'open', 'whatsapp',
        $pipelineId, $newStageId
    ]);
    
    echo "✓ Lead 2 created: {$lead2Id}\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Lead creation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 1: Get Leads Board
echo "\n--- Test 1: Get Leads Board ---\n";
try {
    $board = $boardService->getLeadsBoard($pipelineId);
    
    if (isset($board['error'])) {
        echo "✗ FAIL: Board fetch failed: {$board['error']}\n";
        exit(1);
    }
    
    if (!isset($board['pipeline'])) {
        echo "✗ FAIL: Pipeline missing in board response\n";
        exit(1);
    }
    echo "✓ PASS: Board has pipeline\n";
    
    if (!isset($board['stages']) || !is_array($board['stages'])) {
        echo "✗ FAIL: Stages missing in board response\n";
        exit(1);
    }
    echo "✓ PASS: Board has stages\n";
    
    if (count($board['stages']) !== 3) {
        echo "✗ FAIL: Expected 3 stages, got " . count($board['stages']) . "\n";
        exit(1);
    }
    echo "✓ PASS: Board has correct number of stages\n";
    
    // Check first stage has 2 leads
    $firstStage = $board['stages'][0];
    if ($firstStage['lead_count'] !== 2) {
        echo "✗ FAIL: Expected 2 leads in first stage, got {$firstStage['lead_count']}\n";
        exit(1);
    }
    echo "✓ PASS: First stage has 2 leads\n";
    
    if (count($firstStage['leads']) !== 2) {
        echo "✗ FAIL: Expected 2 lead objects in first stage, got " . count($firstStage['leads']) . "\n";
        exit(1);
    }
    echo "✓ PASS: First stage lead objects loaded\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Board fetch exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Move Lead Between Stages
echo "\n--- Test 2: Move Lead Between Stages ---\n";
try {
    $result = $boardService->moveLead(
        $lead1Id,
        $newStageId,
        $inProgressStageId,
        $pipelineId,
        ['changed_by' => 'test_user', 'changed_by_type' => 'admin_user']
    );
    
    if (isset($result['error'])) {
        echo "✗ FAIL: Move failed: {$result['error']}\n";
        exit(1);
    }
    
    if (!isset($result['lead'])) {
        echo "✗ FAIL: Lead missing in move response\n";
        exit(1);
    }
    echo "✓ PASS: Lead moved successfully\n";
    
    if ($result['lead']['stage_id'] !== $inProgressStageId) {
        echo "✗ FAIL: Lead stage not updated\n";
        exit(1);
    }
    echo "✓ PASS: Lead stage updated correctly\n";
    
    // Verify event was created
    $events = $db->query("SELECT * FROM lead_events WHERE lead_id = ? AND type = ?", [
        $lead1Id, LeadEventTypes::STAGE_CHANGED
    ]);
    
    if (count($events) !== 1) {
        echo "✗ FAIL: Expected 1 stage_changed event, got " . count($events) . "\n";
        exit(1);
    }
    echo "✓ PASS: Stage changed event created\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Move exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Update Lead Inline
echo "\n--- Test 3: Update Lead Inline ---\n";
try {
    $updateData = [
        'id' => $lead2Id,
        'owner_id' => 'owner123',
        'owner_type' => 'admin_user',
        'deal_value' => 50000.00,
        'currency' => 'USD',
        'probability' => 75,
        'tags' => ['hot', 'enterprise']
    ];
    
    $result = $boardService->updateLeadInline($lead2Id, $updateData);
    
    if (isset($result['error'])) {
        echo "✗ FAIL: Update failed: {$result['error']}\n";
        exit(1);
    }
    
    if (!isset($result['lead'])) {
        echo "✗ FAIL: Lead missing in update response\n";
        exit(1);
    }
    echo "✓ PASS: Lead updated successfully\n";
    
    $updatedLead = $result['lead'];
    if ($updatedLead['owner_id'] !== 'owner123') {
        echo "✗ FAIL: Owner not updated\n";
        exit(1);
    }
    echo "✓ PASS: Owner updated\n";
    
    if ((float)$updatedLead['deal_value'] !== 50000.00) {
        echo "✗ FAIL: Deal value not updated\n";
        exit(1);
    }
    echo "✓ PASS: Deal value updated\n";
    
    // Verify events were created
    $ownerEvents = $db->query("SELECT * FROM lead_events WHERE lead_id = ? AND type = ?", [
        $lead2Id, LeadEventTypes::OWNER_CHANGED
    ]);
    
    if (count($ownerEvents) !== 1) {
        echo "✗ FAIL: Expected 1 owner_changed event\n";
        exit(1);
    }
    echo "✓ PASS: Owner changed event created\n";
    
    $dealEvents = $db->query("SELECT * FROM lead_events WHERE lead_id = ? AND type = ?", [
        $lead2Id, LeadEventTypes::DEAL_UPDATED
    ]);
    
    if (count($dealEvents) !== 1) {
        echo "✗ FAIL: Expected 1 deal_updated event\n";
        exit(1);
    }
    echo "✓ PASS: Deal updated event created\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Update exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Add Note
echo "\n--- Test 4: Add Note to Lead ---\n";
try {
    $result = $boardService->addNote(
        $lead1Id,
        'This is a test note',
        ['created_by' => 'test_user', 'created_by_type' => 'admin_user']
    );
    
    if (isset($result['error'])) {
        echo "✗ FAIL: Add note failed: {$result['error']}\n";
        exit(1);
    }
    
    if (!isset($result['success']) || !$result['success']) {
        echo "✗ FAIL: Note not added successfully\n";
        exit(1);
    }
    echo "✓ PASS: Note added successfully\n";
    
    // Verify event was created
    $noteEvents = $db->query("SELECT * FROM lead_events WHERE lead_id = ? AND type = ?", [
        $lead1Id, LeadEventTypes::NOTE
    ]);
    
    if (count($noteEvents) !== 1) {
        echo "✗ FAIL: Expected 1 note event\n";
        exit(1);
    }
    echo "✓ PASS: Note event created\n";
    
    $noteEvent = $noteEvents[0];
    $payload = json_decode($noteEvent['payload_json'], true);
    if ($payload['text'] !== 'This is a test note') {
        echo "✗ FAIL: Note text mismatch\n";
        exit(1);
    }
    echo "✓ PASS: Note text correct\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Add note exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Get Board with Filters
echo "\n--- Test 5: Get Board with Filters ---\n";
try {
    $board = $boardService->getLeadsBoard($pipelineId, [
        'min_score' => 80
    ]);
    
    if (isset($board['error'])) {
        echo "✗ FAIL: Filtered board fetch failed\n";
        exit(1);
    }
    
    // Should only have lead1 (score 85) in results
    $totalLeads = 0;
    foreach ($board['stages'] as $stage) {
        $totalLeads += count($stage['leads']);
    }
    
    if ($totalLeads !== 1) {
        echo "✗ FAIL: Expected 1 lead with min_score=80, got {$totalLeads}\n";
        exit(1);
    }
    echo "✓ PASS: Filtered board returned correct number of leads\n";
    
    // Verify it's the right lead
    foreach ($board['stages'] as $stage) {
        foreach ($stage['leads'] as $lead) {
            if ((int)$lead['score'] < 80) {
                echo "✗ FAIL: Found lead with score < 80\n";
                exit(1);
            }
        }
    }
    echo "✓ PASS: All filtered leads meet criteria\n";
    
} catch (Exception $e) {
    echo "✗ FAIL: Filtered board exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== All BoardService tests passed ===\n";
exit(0);
