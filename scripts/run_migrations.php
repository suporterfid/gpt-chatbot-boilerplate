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
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
