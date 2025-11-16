#!/usr/bin/env php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[0;33m";
const NC = "\033[0m";

$allPassed = true;
$agentsToCleanup = [];
$agentService = null;

function log_message($message, $color = NC) {
    echo $color . $message . NC . "\n";
}

function pass($message) {
    global $allPassed;
    log_message('✓ PASS: ' . $message, GREEN);
}

function fail($message) {
    global $allPassed;
    $allPassed = false;
    log_message('✗ FAIL: ' . $message, RED);
}

log_message("\n=== Vanity Path Validation Tests ===", YELLOW);

try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db'
    ];

    $db = new DB($dbConfig);
    $db->runMigrations(__DIR__ . '/../db/migrations');
    $agentService = new AgentService($db);
    pass('Initialized AgentService for vanity path tests');

    $agentA = $agentService->createAgent([
        'name' => 'Vanity Path Agent A ' . uniqid(),
        'description' => 'Test agent for vanity path sanitation',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini'
    ]);
    $agentsToCleanup[] = $agentA['id'];

    $agentB = $agentService->createAgent([
        'name' => 'Vanity Path Agent B ' . uniqid(),
        'description' => 'Second agent for vanity path collision',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini'
    ]);
    $agentsToCleanup[] = $agentB['id'];

    pass('Created two test agents');

    // Happy path sanitation
    $updatedAgent = $agentService->updateWhitelabelConfig($agentA['id'], [
        'vanity_path' => 'My Fancy Support Path'
    ]);

    if ($updatedAgent['vanity_path'] === 'my-fancy-support-path') {
        pass('Vanity path sanitized and persisted correctly');
    } else {
        fail('Vanity path was not sanitized as expected: ' . ($updatedAgent['vanity_path'] ?? 'NULL'));
    }

    // Invalid characters
    try {
        $agentService->updateWhitelabelConfig($agentA['id'], [
            'vanity_path' => '@@@'
        ]);
        fail('Invalid vanity path should have thrown an exception');
    } catch (Exception $e) {
        if ((int)$e->getCode() === 400) {
            pass('Invalid vanity path rejected with clear exception');
        } else {
            fail('Invalid vanity path threw unexpected error code: ' . $e->getCode());
        }
    }

    // Collision detection
    $sharedSlugSource = 'Shared Support Path';
    $sharedSlug = $agentService->updateWhitelabelConfig($agentA['id'], [
        'vanity_path' => $sharedSlugSource
    ]);

    if (($sharedSlug['vanity_path'] ?? null) !== 'shared-support-path') {
        fail('Failed to persist shared vanity path on first agent');
    } else {
        pass('Primary agent updated with shared vanity path');
    }

    try {
        $agentService->updateWhitelabelConfig($agentB['id'], [
            'vanity_path' => $sharedSlugSource
        ]);
        fail('Expected vanity path collision but update succeeded');
    } catch (Exception $e) {
        if ((int)$e->getCode() === 409) {
            pass('Vanity path collision converted into HTTP 409 error');
        } else {
            fail('Vanity path collision raised unexpected error code: ' . $e->getCode());
        }
    }
} catch (Exception $e) {
    fail('Unexpected error during vanity path tests: ' . $e->getMessage());
} finally {
    if ($agentService && !empty($agentsToCleanup)) {
        foreach ($agentsToCleanup as $agentId) {
            try {
                $agentService->deleteAgent($agentId);
            } catch (Exception $cleanupError) {
                log_message('Cleanup failed for agent ' . $agentId . ': ' . $cleanupError->getMessage(), YELLOW);
            }
        }
    }
}

exit($allPassed ? 0 : 1);
