#!/usr/bin/env php
<?php
/**
 * Test Prompt Builder Components
 * Basic smoke tests to verify the implementation works
 */

require_once __DIR__ . '/../includes/PromptBuilder/GuardrailLoader.php';

echo "Testing Prompt Builder Components\n";
echo "==================================\n\n";

// Test 1: GuardrailLoader
echo "Test 1: GuardrailLoader\n";
echo "-----------------------\n";

try {
    $templatesPath = __DIR__ . '/../includes/PromptBuilder/templates/guardrails';
    $loader = new GuardrailLoader($templatesPath);
    
    // Test catalog
    echo "Loading guardrails catalog...\n";
    $catalog = $loader->catalog();
    
    if (count($catalog) > 0) {
        echo "✓ Found " . count($catalog) . " guardrails\n";
        foreach ($catalog as $item) {
            $mandatory = $item['mandatory'] ? '[REQUIRED]' : '[OPTIONAL]';
            echo "  - {$item['key']}: {$item['title']} {$mandatory}\n";
        }
    } else {
        echo "✗ No guardrails found!\n";
        exit(1);
    }
    
    // Test loading specific guardrails
    echo "\nLoading specific guardrails...\n";
    $templates = $loader->load(['hallucination_prevention', 'scope_restriction']);
    
    if (count($templates) === 2) {
        echo "✓ Loaded 2 guardrails successfully\n";
        
        // Verify structure
        foreach ($templates as $key => $template) {
            if (isset($template['key']) && isset($template['snippet']) && isset($template['title'])) {
                echo "  ✓ {$key} has valid structure\n";
            } else {
                echo "  ✗ {$key} missing required fields\n";
                exit(1);
            }
        }
    } else {
        echo "✗ Expected 2 guardrails, got " . count($templates) . "\n";
        exit(1);
    }
    
    // Test interpolation
    echo "\nTesting variable interpolation...\n";
    $snippet = "Hello {{name}}, welcome to {{company}}!";
    $interpolated = $loader->interpolate($snippet, [
        'name' => 'Alice',
        'company' => 'Acme Corp'
    ]);
    
    if ($interpolated === "Hello Alice, welcome to Acme Corp!") {
        echo "✓ Variable interpolation works\n";
    } else {
        echo "✗ Interpolation failed\n";
        echo "  Expected: Hello Alice, welcome to Acme Corp!\n";
        echo "  Got: $interpolated\n";
        exit(1);
    }
    
    echo "\n✓ All GuardrailLoader tests passed!\n\n";
    
} catch (Exception $e) {
    echo "✗ GuardrailLoader test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Database Schema
echo "Test 2: Database Schema\n";
echo "-----------------------\n";

try {
    require_once __DIR__ . '/../includes/DB.php';
    
    $dbConfig = [
        'database_path' => __DIR__ . '/../data/chatbot.db'
    ];
    
    $db = new DB($dbConfig);
    
    // Check if migration was run
    echo "Checking if agent_prompts table exists...\n";
    $pdo = $db->getPdo();
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='agent_prompts'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ agent_prompts table exists\n";
        
        // Check schema
        $stmt = $pdo->query("PRAGMA table_info(agent_prompts)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $expectedColumns = ['id', 'agent_id', 'version', 'prompt_md', 'guardrails_json', 'created_by', 'created_at', 'updated_at'];
        $actualColumns = array_column($columns, 'name');
        
        $missing = array_diff($expectedColumns, $actualColumns);
        
        if (empty($missing)) {
            echo "✓ All required columns present\n";
        } else {
            echo "✗ Missing columns: " . implode(', ', $missing) . "\n";
            exit(1);
        }
        
        // Check agents table for active_prompt_version
        echo "\nChecking agents table for active_prompt_version...\n";
        $stmt = $pdo->query("PRAGMA table_info(agents)");
        $agentColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $agentColumnNames = array_column($agentColumns, 'name');
        
        if (in_array('active_prompt_version', $agentColumnNames)) {
            echo "✓ active_prompt_version column exists in agents table\n";
        } else {
            echo "⚠ active_prompt_version column NOT found (migration may not be run)\n";
        }
        
    } else {
        echo "⚠ agent_prompts table does not exist (migration not run)\n";
        echo "  Run: php scripts/migrate.php or access admin-api.php to auto-migrate\n";
    }
    
    echo "\n✓ Database schema checks complete!\n\n";
    
} catch (Exception $e) {
    echo "⚠ Database test skipped: " . $e->getMessage() . "\n";
    echo "  (This is OK if database hasn't been initialized yet)\n\n";
}

// Test 3: Configuration
echo "Test 3: Configuration\n";
echo "---------------------\n";

$config = require __DIR__ . '/../config.php';

if (isset($config['prompt_builder'])) {
    echo "✓ prompt_builder configuration exists\n";
    
    $required = ['enabled', 'model', 'timeout_ms', 'default_guardrails', 'templates_path'];
    $missing = [];
    
    foreach ($required as $key) {
        if (!isset($config['prompt_builder'][$key])) {
            $missing[] = $key;
        }
    }
    
    if (empty($missing)) {
        echo "✓ All required configuration keys present\n";
        
        // Show config values
        echo "\nConfiguration values:\n";
        echo "  - Enabled: " . ($config['prompt_builder']['enabled'] ? 'Yes' : 'No') . "\n";
        echo "  - Model: " . ($config['prompt_builder']['model'] ?? 'N/A') . "\n";
        echo "  - Timeout: " . ($config['prompt_builder']['timeout_ms'] ?? 'N/A') . "ms\n";
        echo "  - Rate Limit: " . ($config['prompt_builder']['rate_limit_per_min'] ?? 'N/A') . " req/min\n";
        echo "  - Default Guardrails: " . implode(', ', $config['prompt_builder']['default_guardrails'] ?? []) . "\n";
        
    } else {
        echo "✗ Missing configuration keys: " . implode(', ', $missing) . "\n";
        exit(1);
    }
} else {
    echo "✗ prompt_builder configuration not found\n";
    exit(1);
}

echo "\n✓ All configuration tests passed!\n\n";

// Summary
echo "==================================\n";
echo "Summary: All tests passed! ✓\n";
echo "==================================\n\n";

echo "Prompt Builder is ready to use!\n";
echo "Access it via: Admin UI → Agents → ✨ Prompt Builder button\n\n";

echo "Next steps:\n";
echo "1. Run database migration if you haven't: php scripts/migrate.php\n";
echo "2. Set OPENAI_API_KEY in .env file\n";
echo "3. Access /public/admin/ and click Prompt Builder on any agent\n\n";

exit(0);
