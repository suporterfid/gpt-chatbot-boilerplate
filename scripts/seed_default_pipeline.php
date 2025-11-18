#!/usr/bin/env php
<?php
/**
 * Seed Default CRM Pipeline
 * 
 * Creates a default pipeline with standard stages for LeadSense CRM
 * Idempotent - safe to run multiple times
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? '',
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db',
    ];
    
    $db = new DB($dbConfig);
    $pdo = $db->getPDO();
    
    echo "🔍 Checking for existing default pipeline...\n";
    
    // Check if default pipeline already exists
    $stmt = $pdo->prepare("SELECT id, name FROM crm_pipelines WHERE is_default = 1 LIMIT 1");
    $stmt->execute();
    $existingPipeline = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingPipeline) {
        echo "✓ Default pipeline already exists: {$existingPipeline['name']} ({$existingPipeline['id']})\n";
        
        // Check stages
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM crm_pipeline_stages WHERE pipeline_id = ?");
        $stmt->execute([$existingPipeline['id']]);
        $stageCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "✓ Pipeline has {$stageCount} stages\n";
        
        if ($stageCount > 0) {
            echo "✅ Default pipeline already seeded. Nothing to do.\n";
            exit(0);
        }
    }
    
    echo "📝 Creating default pipeline...\n";
    
    $pdo->beginTransaction();
    
    // Create default pipeline
    $pipelineId = generateUUID();
    $stmt = $pdo->prepare("
        INSERT INTO crm_pipelines (id, client_id, name, description, is_default, color, created_at, updated_at)
        VALUES (?, NULL, ?, ?, 1, ?, datetime('now'), datetime('now'))
    ");
    $stmt->execute([
        $pipelineId,
        'Default',
        'Default pipeline for all leads',
        '#8b5cf6'  // Purple
    ]);
    
    echo "✓ Created pipeline: {$pipelineId}\n";
    
    // Define default stages
    $stages = [
        [
            'name' => 'Lead Capture',
            'slug' => 'lead_capture',
            'color' => '#a855f7',  // Purple
            'position' => 0,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Support',
            'slug' => 'support',
            'color' => '#3b82f6',  // Blue
            'position' => 1,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Commercial Lead',
            'slug' => 'commercial_lead',
            'color' => '#22c55e',  // Green
            'position' => 2,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Negotiation',
            'slug' => 'negotiation',
            'color' => '#f59e0b',  // Amber
            'position' => 3,
            'is_won' => 0,
            'is_lost' => 0,
            'is_closed' => 0
        ],
        [
            'name' => 'Closed Won',
            'slug' => 'closed_won',
            'color' => '#10b981',  // Emerald
            'position' => 4,
            'is_won' => 1,
            'is_lost' => 0,
            'is_closed' => 1
        ],
        [
            'name' => 'Closed Lost',
            'slug' => 'closed_lost',
            'color' => '#ef4444',  // Red
            'position' => 5,
            'is_won' => 0,
            'is_lost' => 1,
            'is_closed' => 1
        ]
    ];
    
    // Insert stages
    $stmt = $pdo->prepare("
        INSERT INTO crm_pipeline_stages 
        (id, pipeline_id, name, slug, position, color, is_won, is_lost, is_closed, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
    ");
    
    $stageIds = [];
    foreach ($stages as $stage) {
        $stageId = generateUUID();
        $stageIds[$stage['slug']] = $stageId;
        
        $stmt->execute([
            $stageId,
            $pipelineId,
            $stage['name'],
            $stage['slug'],
            $stage['position'],
            $stage['color'],
            $stage['is_won'],
            $stage['is_lost'],
            $stage['is_closed']
        ]);
        
        echo "  ✓ Created stage: {$stage['name']} ({$stageId})\n";
    }
    
    $pdo->commit();
    
    echo "\n✅ Default pipeline seeded successfully!\n";
    echo "Pipeline ID: {$pipelineId}\n";
    echo "Stages: " . count($stages) . "\n";
    
    // Return IDs for further processing
    return [
        'pipeline_id' => $pipelineId,
        'stage_ids' => $stageIds
    ];
    
} catch (Exception $e) {
    if (isset($db)) {
        try {
            $pdo = $db->getPDO();
            if ($pdo->inTransaction()) {
                $db->rollback();
            }
        } catch (Exception $rollbackError) {
            // Ignore rollback errors
        }
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
