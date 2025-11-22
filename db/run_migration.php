<?php
/**
 * Database Migration Runner
 *
 * Runs a specific SQL migration file against the database
 *
 * Usage: php db/run_migration.php <migration_number>
 * Example: php db/run_migration.php 048
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

// ANSI color codes
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

function printHeader($text) {
    echo COLOR_BLUE . "\n" . str_repeat("=", 60) . "\n";
    echo $text . "\n";
    echo str_repeat("=", 60) . COLOR_RESET . "\n\n";
}

function printSuccess($text) {
    echo COLOR_GREEN . "✓ " . $text . COLOR_RESET . "\n";
}

function printError($text) {
    echo COLOR_RED . "✗ " . $text . COLOR_RESET . "\n";
}

function printWarning($text) {
    echo COLOR_YELLOW . "⚠ " . $text . COLOR_RESET . "\n";
}

function printInfo($text) {
    echo COLOR_BLUE . "ℹ " . $text . COLOR_RESET . "\n";
}

// Parse command line arguments
$migrationNumber = $argv[1] ?? null;

if (!$migrationNumber) {
    printError("Migration number required");
    echo "Usage: php db/run_migration.php <migration_number>\n";
    echo "Example: php db/run_migration.php 048\n";
    exit(1);
}

// Find migration file
$migrationFile = null;
$migrationsDir = __DIR__ . '/migrations';
$files = scandir($migrationsDir);

foreach ($files as $file) {
    if (strpos($file, $migrationNumber . '_') === 0 && substr($file, -4) === '.sql') {
        $migrationFile = $migrationsDir . '/' . $file;
        break;
    }
}

if (!$migrationFile || !file_exists($migrationFile)) {
    printError("Migration file not found for migration number: $migrationNumber");
    exit(1);
}

printHeader("Database Migration Runner");
printInfo("Migration file: " . basename($migrationFile));

// Read migration SQL
$sql = file_get_contents($migrationFile);

if (empty($sql)) {
    printError("Migration file is empty");
    exit(1);
}

printInfo("Migration SQL loaded (" . strlen($sql) . " bytes)");

// Initialize database connection
try {
    $config = [
        'database_path' => __DIR__ . '/../data/chatbot.db',
        'app_env' => getEnvValue('APP_ENV') ?? 'development'
    ];

    $db = new DB($config);
    printSuccess("Database connection established");

} catch (Exception $e) {
    printError("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Run migration
printInfo("\nExecuting migration...\n");

try {
    // Get PDO instance to run multi-statement SQL
    $reflection = new ReflectionClass($db);
    $property = $reflection->getProperty('pdo');
    $property->setAccessible(true);
    $pdo = $property->getValue($db);

    // Execute the migration SQL
    $result = $pdo->exec($sql);

    if ($result === false) {
        $error = $pdo->errorInfo();
        throw new Exception("Migration failed: " . $error[2]);
    }

    printSuccess("\nMigration executed successfully!");

    // Show what was created
    echo "\nChecking created tables...\n";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'blog_%' ORDER BY name");

    if (!empty($tables)) {
        printSuccess("Found " . count($tables) . " blog tables:");
        foreach ($tables as $table) {
            echo "  • " . $table['name'] . "\n";
        }
    }

    echo "\nChecking created indexes...\n";
    $indexes = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_blog_%' ORDER BY name");

    if (!empty($indexes)) {
        printSuccess("Found " . count($indexes) . " blog indexes:");
        foreach ($indexes as $index) {
            echo "  • " . $index['name'] . "\n";
        }
    }

    echo "\nChecking created triggers...\n";
    $triggers = $db->query("SELECT name FROM sqlite_master WHERE type='trigger' AND name LIKE '%blog%' ORDER BY name");

    if (!empty($triggers)) {
        printSuccess("Found " . count($triggers) . " blog triggers:");
        foreach ($triggers as $trigger) {
            echo "  • " . $trigger['name'] . "\n";
        }
    }

    printHeader("Migration Complete");
    printInfo("Next step: Run validation script");
    echo "  php db/validate_blog_schema.php\n\n";

    exit(0);

} catch (Exception $e) {
    printError("\nMigration failed: " . $e->getMessage());

    // Show SQL error details if available
    if (isset($pdo)) {
        $error = $pdo->errorInfo();
        if (!empty($error[2])) {
            echo "\nSQL Error Details:\n";
            echo "  SQLSTATE: " . $error[0] . "\n";
            echo "  Driver Error Code: " . $error[1] . "\n";
            echo "  Error Message: " . $error[2] . "\n";
        }
    }

    exit(1);
}
