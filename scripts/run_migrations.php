#!/usr/bin/env php
<?php
/**
 * Run database migrations
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

$dbConfig = [
    'database_url' => $config['admin']['database_url'] ?? '',
    'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db',
];

try {
    $db = new DB($dbConfig);
    echo "Running database migrations...\n";
    $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "Migrations completed successfully!\n";
    
    // --- Running Post-Migration Scripts ---
    echo "\n--- Running Post-Migration Scripts ---\n";
    
    // Seed default pipeline if CRM tables exist
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='crm_pipelines'");
    if (!empty($tables)) {
        echo "\n🌱 Seeding default CRM pipeline...\n";
        
        // Run seeding script
        $seedOutput = [];
        $seedReturnVar = 0;
        exec('php ' . escapeshellarg(__DIR__ . '/seed_default_pipeline.php') . ' 2>&1', $seedOutput, $seedReturnVar);
        
        foreach ($seedOutput as $line) {
            echo $line . "\n";
        }
        
        if ($seedReturnVar === 0) {
            echo "\n🔄 Backfilling existing leads...\n";
            
            // Run backfill script
            $backfillOutput = [];
            $backfillReturnVar = 0;
            exec('php ' . escapeshellarg(__DIR__ . '/backfill_existing_leads.php') . ' 2>&1', $backfillOutput, $backfillReturnVar);
            
            foreach ($backfillOutput as $line) {
                echo $line . "\n";
            }
            
            if ($backfillReturnVar !== 0) {
                echo "⚠️ Warning: Backfill script had issues but migrations succeeded.\n";
            }
        } else {
            echo "⚠️ Warning: Seeding script had issues but migrations succeeded.\n";
        }
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
