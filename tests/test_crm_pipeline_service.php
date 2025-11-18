#!/usr/bin/env php
<?php
/**
 * Test Suite for CRM PipelineService
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/LeadSense/CRM/PipelineService.php';

echo "\n=== Testing CRM PipelineService ===\n";

// Setup test database
$dbConfig = [
    'database_path' => '/tmp/test_pipeline_service_' . time() . '.db'
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

// Run seeding script
echo "\n--- Setup: Seeding Default Pipeline ---\n";
try {
    // Temporarily override database path for seeding script
    $originalDbPath = $_ENV['DB_PATH'] ?? null;
    $_ENV['DB_PATH'] = $dbConfig['database_path'];
    
    // Create a temporary config for the seeding script
    $GLOBALS['__TEST_DB__'] = $db;
    
    // Include and run the seeding logic directly
    $seedPipelineId = null;
    
    // Check if default pipeline already exists
    $stmt = $db->query("SELECT id, name FROM crm_pipelines WHERE is_default = 1 LIMIT 1", []);
    $existingPipeline = !empty($stmt) ? $stmt[0] : null;
    
    if (!$existingPipeline) {
        // Generate UUID function
        function generateTestUUID() {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
        
        $db->beginTransaction();
        
        // Create default pipeline
        $pipelineId = generateTestUUID();
        $db->execute("
            INSERT INTO crm_pipelines (id, client_id, name, description, is_default, color, created_at, updated_at)
            VALUES (?, NULL, ?, ?, 1, ?, datetime('now'), datetime('now'))
        ", [$pipelineId, 'Default', 'Default pipeline for all leads', '#8b5cf6']);
        
        // Define default stages
        $stages = [
            ['name' => 'Lead Capture', 'slug' => 'lead_capture', 'color' => '#a855f7', 'position' => 0],
            ['name' => 'Support', 'slug' => 'support', 'color' => '#3b82f6', 'position' => 1],
            ['name' => 'Commercial Lead', 'slug' => 'commercial_lead', 'color' => '#22c55e', 'position' => 2],
            ['name' => 'Negotiation', 'slug' => 'negotiation', 'color' => '#f59e0b', 'position' => 3],
            ['name' => 'Closed Won', 'slug' => 'closed_won', 'color' => '#10b981', 'position' => 4, 'is_won' => 1, 'is_closed' => 1],
            ['name' => 'Closed Lost', 'slug' => 'closed_lost', 'color' => '#ef4444', 'position' => 5, 'is_lost' => 1, 'is_closed' => 1]
        ];
        
        foreach ($stages as $stage) {
            $stageId = generateTestUUID();
            $db->execute("
                INSERT INTO crm_pipeline_stages 
                (id, pipeline_id, name, slug, position, color, is_won, is_lost, is_closed, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ", [
                $stageId, $pipelineId, $stage['name'], $stage['slug'], $stage['position'], $stage['color'],
                $stage['is_won'] ?? 0, $stage['is_lost'] ?? 0, $stage['is_closed'] ?? 0
            ]);
        }
        
        $db->commit();
        echo "✓ Default pipeline seeded\n";
    } else {
        echo "✓ Default pipeline already exists\n";
    }
    
    // Restore original DB path
    if ($originalDbPath !== null) {
        $_ENV['DB_PATH'] = $originalDbPath;
    } else {
        unset($_ENV['DB_PATH']);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize service
$service = new PipelineService($db);

// Test 1: Get Default Pipeline (seeded)
echo "\n--- Test 1: Get Default Pipeline ---\n";
try {
    $defaultPipeline = $service->getDefaultPipeline();
    
    if ($defaultPipeline && $defaultPipeline['is_default'] == 1) {
        echo "✓ PASS: Default pipeline exists\n";
        echo "  ID: {$defaultPipeline['id']}\n";
        echo "  Name: {$defaultPipeline['name']}\n";
        echo "  Stages: " . count($defaultPipeline['stages']) . "\n";
        
        if (count($defaultPipeline['stages']) >= 4) {
            echo "✓ PASS: Default pipeline has stages\n";
        } else {
            echo "✗ FAIL: Expected at least 4 stages\n";
            exit(1);
        }
    } else {
        echo "✗ FAIL: No default pipeline found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: List Pipelines
echo "\n--- Test 2: List Pipelines ---\n";
try {
    $pipelines = $service->listPipelines();
    
    if (count($pipelines) > 0) {
        echo "✓ PASS: Pipelines listed (count: " . count($pipelines) . ")\n";
    } else {
        echo "✗ FAIL: No pipelines found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Create Pipeline without Stages
echo "\n--- Test 3: Create Pipeline (Simple) ---\n";
try {
    $pipelineData = [
        'name' => 'Test Sales Pipeline',
        'description' => 'For testing purposes',
        'color' => '#3b82f6',
        'is_default' => false
    ];
    
    $newPipeline = $service->createPipeline($pipelineData);
    
    if (isset($newPipeline['error'])) {
        echo "✗ FAIL: {$newPipeline['error']}\n";
        exit(1);
    }
    
    if ($newPipeline['name'] === 'Test Sales Pipeline') {
        echo "✓ PASS: Pipeline created\n";
        echo "  ID: {$newPipeline['id']}\n";
    } else {
        echo "✗ FAIL: Pipeline name mismatch\n";
        exit(1);
    }
    
    $testPipelineId = $newPipeline['id'];
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Create Pipeline with Stages
echo "\n--- Test 4: Create Pipeline with Stages ---\n";
try {
    $pipelineData = [
        'name' => 'Enterprise Sales',
        'description' => 'Enterprise deal flow',
        'color' => '#8b5cf6',
        'is_default' => false,
        'stages' => [
            ['name' => 'Discovery', 'slug' => 'discovery', 'color' => '#3b82f6'],
            ['name' => 'Proposal', 'slug' => 'proposal', 'color' => '#22c55e'],
            ['name' => 'Negotiation', 'slug' => 'negotiation', 'color' => '#f59e0b'],
            ['name' => 'Closed Won', 'slug' => 'won', 'color' => '#10b981', 'is_won' => true, 'is_closed' => true]
        ]
    ];
    
    $pipeline = $service->createPipeline($pipelineData);
    
    if (isset($pipeline['error'])) {
        echo "✗ FAIL: {$pipeline['error']}\n";
        exit(1);
    }
    
    if (count($pipeline['stages']) === 4) {
        echo "✓ PASS: Pipeline created with 4 stages\n";
    } else {
        echo "✗ FAIL: Expected 4 stages, got " . count($pipeline['stages']) . "\n";
        exit(1);
    }
    
    // Verify stage positions
    $positions = array_column($pipeline['stages'], 'position');
    if ($positions === [0, 1, 2, 3]) {
        echo "✓ PASS: Stage positions are sequential\n";
    } else {
        echo "✗ FAIL: Stage positions not sequential\n";
        exit(1);
    }
    
    $enterprisePipelineId = $pipeline['id'];
    $firstStageId = $pipeline['stages'][0]['id'];
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Get Pipeline with Stages
echo "\n--- Test 5: Get Pipeline with Stages ---\n";
try {
    $fetched = $service->getPipeline($enterprisePipelineId, true);
    
    if ($fetched && $fetched['name'] === 'Enterprise Sales') {
        echo "✓ PASS: Pipeline fetched\n";
    } else {
        echo "✗ FAIL: Pipeline not found or name mismatch\n";
        exit(1);
    }
    
    if (isset($fetched['stages']) && count($fetched['stages']) === 4) {
        echo "✓ PASS: Stages included in response\n";
    } else {
        echo "✗ FAIL: Stages not included or count mismatch\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 6: Update Pipeline
echo "\n--- Test 6: Update Pipeline ---\n";
try {
    $updateData = [
        'name' => 'Enterprise Sales - Updated',
        'color' => '#6366f1'
    ];
    
    $updated = $service->updatePipeline($enterprisePipelineId, $updateData);
    
    if (isset($updated['error'])) {
        echo "✗ FAIL: {$updated['error']}\n";
        exit(1);
    }
    
    if ($updated['name'] === 'Enterprise Sales - Updated') {
        echo "✓ PASS: Pipeline name updated\n";
    } else {
        echo "✗ FAIL: Name not updated\n";
        exit(1);
    }
    
    if ($updated['color'] === '#6366f1') {
        echo "✓ PASS: Pipeline color updated\n";
    } else {
        echo "✗ FAIL: Color not updated\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 7: List Stages
echo "\n--- Test 7: List Stages ---\n";
try {
    $stages = $service->listStages($enterprisePipelineId);
    
    if (count($stages) === 4) {
        echo "✓ PASS: All stages listed\n";
    } else {
        echo "✗ FAIL: Expected 4 stages\n";
        exit(1);
    }
    
    // Check ordering
    $positions = array_column($stages, 'position');
    if ($positions === [0, 1, 2, 3]) {
        echo "✓ PASS: Stages ordered by position\n";
    } else {
        echo "✗ FAIL: Stages not ordered correctly\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 8: Get Single Stage
echo "\n--- Test 8: Get Single Stage ---\n";
try {
    $stage = $service->getStage($firstStageId);
    
    if ($stage && $stage['name'] === 'Discovery') {
        echo "✓ PASS: Stage fetched\n";
    } else {
        echo "✗ FAIL: Stage not found or name mismatch\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 9: Create Individual Stage
echo "\n--- Test 9: Create Individual Stage ---\n";
try {
    $stageData = [
        'name' => 'Closed Lost',
        'slug' => 'lost',
        'color' => '#ef4444',
        'is_lost' => true,
        'is_closed' => true
    ];
    
    $newStage = $service->createStage($enterprisePipelineId, $stageData);
    
    if ($newStage['name'] === 'Closed Lost') {
        echo "✓ PASS: Stage created\n";
    } else {
        echo "✗ FAIL: Stage creation failed\n";
        exit(1);
    }
    
    if ($newStage['position'] === 4) {
        echo "✓ PASS: Stage position auto-assigned correctly\n";
    } else {
        echo "✗ FAIL: Expected position 4, got {$newStage['position']}\n";
        exit(1);
    }
    
    $closedLostStageId = $newStage['id'];
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 10: Update Stage
echo "\n--- Test 10: Update Stage ---\n";
try {
    $updateData = [
        'color' => '#dc2626'
    ];
    
    $updated = $service->updateStage($closedLostStageId, $updateData);
    
    if (isset($updated['error'])) {
        echo "✗ FAIL: {$updated['error']}\n";
        exit(1);
    }
    
    if ($updated['color'] === '#dc2626') {
        echo "✓ PASS: Stage color updated\n";
    } else {
        echo "✗ FAIL: Color not updated\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 11: Reorder Stages
echo "\n--- Test 11: Reorder Stages ---\n";
try {
    $currentStages = $service->listStages($enterprisePipelineId);
    $stageIds = array_column($currentStages, 'id');
    
    // Reverse order
    $reversedIds = array_reverse($stageIds);
    
    $reordered = $service->reorderStages($enterprisePipelineId, $reversedIds);
    
    if (isset($reordered['error'])) {
        echo "✗ FAIL: {$reordered['error']}\n";
        exit(1);
    }
    
    $newPositions = array_column($reordered, 'position');
    if ($newPositions === [0, 1, 2, 3, 4]) {
        echo "✓ PASS: Stages reordered successfully\n";
    } else {
        echo "✗ FAIL: Reordering failed\n";
        exit(1);
    }
    
    // Verify first stage is now Closed Lost
    if ($reordered[0]['name'] === 'Closed Lost') {
        echo "✓ PASS: Stage order verified\n";
    } else {
        echo "✗ FAIL: Expected 'Closed Lost' as first stage\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 12: Set Default Pipeline
echo "\n--- Test 12: Set Default Pipeline ---\n";
try {
    $result = $service->setDefaultPipeline($enterprisePipelineId);
    
    if (isset($result['error'])) {
        echo "✗ FAIL: {$result['error']}\n";
        exit(1);
    }
    
    if ($result['is_default'] == 1) {
        echo "✓ PASS: Pipeline set as default\n";
    } else {
        echo "✗ FAIL: is_default flag not set\n";
        exit(1);
    }
    
    // Verify only one default
    $defaultPipeline = $service->getDefaultPipeline();
    if ($defaultPipeline['id'] === $enterprisePipelineId) {
        echo "✓ PASS: Only one default pipeline (atomicity)\n";
    } else {
        echo "✗ FAIL: Multiple defaults or wrong default\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 13: Archive Stage (should fail with leads check - but we have no leads)
echo "\n--- Test 13: Archive Stage ---\n";
try {
    $result = $service->archiveStage($closedLostStageId);
    
    if (isset($result['success']) && $result['success']) {
        echo "✓ PASS: Stage archived\n";
    } else {
        echo "✗ FAIL: Stage archiving failed\n";
        exit(1);
    }
    
    // Verify stage is archived
    $stages = $service->listStages($enterprisePipelineId, false);
    $archivedStageFound = false;
    foreach ($stages as $stage) {
        if ($stage['id'] === $closedLostStageId) {
            $archivedStageFound = true;
            break;
        }
    }
    
    if (!$archivedStageFound) {
        echo "✓ PASS: Archived stage not in active list\n";
    } else {
        echo "✗ FAIL: Archived stage still in active list\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 14: Archive Pipeline (should fail - it's default)
echo "\n--- Test 14: Archive Pipeline (Default - Should Fail) ---\n";
try {
    $result = $service->archivePipeline($enterprisePipelineId);
    
    if (isset($result['error']) && strpos($result['error'], 'default') !== false) {
        echo "✓ PASS: Cannot archive default pipeline (validation works)\n";
    } else {
        echo "✗ FAIL: Should have prevented archiving default pipeline\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 15: Archive Pipeline (Non-Default)
echo "\n--- Test 15: Archive Pipeline (Non-Default) ---\n";
try {
    // First make sure testPipelineId is not default
    $result = $service->archivePipeline($testPipelineId);
    
    if (isset($result['success']) && $result['success']) {
        echo "✓ PASS: Non-default pipeline archived\n";
    } else {
        echo "✗ FAIL: Pipeline archiving failed\n";
        exit(1);
    }
    
    // Verify pipeline is archived
    $pipelines = $service->listPipelines(false);
    $archivedPipelineFound = false;
    foreach ($pipelines as $pipeline) {
        if ($pipeline['id'] === $testPipelineId) {
            $archivedPipelineFound = true;
            break;
        }
    }
    
    if (!$archivedPipelineFound) {
        echo "✓ PASS: Archived pipeline not in active list\n";
    } else {
        echo "✗ FAIL: Archived pipeline still in active list\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 16: Get Lead Count by Stage (empty for now)
echo "\n--- Test 16: Get Lead Count by Stage ---\n";
try {
    $counts = $service->getLeadCountByStage($enterprisePipelineId);
    
    if (is_array($counts)) {
        echo "✓ PASS: Lead counts retrieved (count: " . count($counts) . ")\n";
    } else {
        echo "✗ FAIL: Invalid response\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 17: Validation - Empty Name
echo "\n--- Test 17: Validation - Empty Name ---\n";
try {
    $invalidData = [
        'name' => '',
        'description' => 'Should fail'
    ];
    
    $result = $service->createPipeline($invalidData);
    
    if (isset($result['error']) && strpos($result['error'], 'required') !== false) {
        echo "✓ PASS: Validation caught empty name\n";
    } else {
        echo "✗ FAIL: Validation should have failed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 18: Slugify Function (via stage creation)
echo "\n--- Test 18: Auto-Slugify Stage Name ---\n";
try {
    // Create another test pipeline for this
    $testPipeline2 = $service->createPipeline([
        'name' => 'Slugify Test Pipeline',
        'is_default' => false
    ]);
    
    $stageData = [
        'name' => 'Contract Review & Approval',
        // slug not provided - should be auto-generated
        'color' => '#3b82f6'
    ];
    
    $stage = $service->createStage($testPipeline2['id'], $stageData);
    
    if ($stage['slug'] === 'contract-review-approval') {
        echo "✓ PASS: Stage name auto-slugified correctly\n";
    } else {
        echo "✗ FAIL: Expected 'contract-review-approval', got '{$stage['slug']}'\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}

// Cleanup
echo "\n--- Cleanup ---\n";
try {
    unlink($dbConfig['database_path']);
    echo "✓ Test database cleaned up\n";
} catch (Exception $e) {
    echo "Warning: Could not delete test database\n";
}

echo "\n=== All PipelineService Tests Passed ===\n";
echo "Total tests: 18 groups\n";
echo "✅ All tests successful!\n\n";
