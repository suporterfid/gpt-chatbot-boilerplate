#!/usr/bin/env php
<?php
/**
 * Backfill Existing Leads with Default Pipeline
 * 
 * Assigns all existing leads without pipeline to the default pipeline
 * Run after seeding default pipeline
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? '',
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db',
    ];
    
    $db = new DB($dbConfig);
    $pdo = $db->getPDO();
    
    echo "🔍 Finding default pipeline...\n";
    
    // Get default pipeline and first stage
    $stmt = $pdo->prepare("SELECT id FROM crm_pipelines WHERE is_default = 1 LIMIT 1");
    $stmt->execute();
    $pipeline = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pipeline) {
        echo "❌ No default pipeline found. Run seed_default_pipeline.php first.\n";
        exit(1);
    }
    
    $pipelineId = $pipeline['id'];
    echo "✓ Found pipeline: {$pipelineId}\n";
    
    // Get first stage (Lead Capture)
    $stmt = $pdo->prepare("
        SELECT id, name FROM crm_pipeline_stages 
        WHERE pipeline_id = ? 
        ORDER BY position ASC 
        LIMIT 1
    ");
    $stmt->execute([$pipelineId]);
    $stage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stage) {
        echo "❌ No stages found in pipeline. Check seeding.\n";
        exit(1);
    }
    
    $stageId = $stage['id'];
    echo "✓ Found first stage: {$stage['name']} ({$stageId})\n";
    
    // Count leads without pipeline
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leads WHERE pipeline_id IS NULL");
    $stmt->execute();
    $leadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($leadCount === 0) {
        echo "✅ No leads to backfill. All leads already assigned to pipelines.\n";
        exit(0);
    }
    
    echo "📊 Found {$leadCount} leads without pipeline assignment\n";
    echo "🔄 Assigning to default pipeline...\n";
    
    // Update leads
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE leads 
        SET pipeline_id = ?, 
            stage_id = ?,
            updated_at = datetime('now')
        WHERE pipeline_id IS NULL
    ");
    $stmt->execute([$pipelineId, $stageId]);
    
    $updatedCount = $stmt->rowCount();
    
    $pdo->commit();
    
    echo "✅ Backfilled {$updatedCount} leads\n";
    echo "   Pipeline: {$pipelineId}\n";
    echo "   Stage: {$stage['name']}\n";
    
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
