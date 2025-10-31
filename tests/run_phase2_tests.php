#!/usr/bin/env php
<?php
/**
 * Phase 2 Test Suite - PromptService and VectorStoreService
 */

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/PromptService.php';
require_once __DIR__ . '/../includes/VectorStoreService.php';

// Test counter
$testsPassed = 0;
$testsFailed = 0;

function assert_true($condition, $message) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message\n";
        $testsFailed++;
    }
}

function assert_equals($expected, $actual, $message) {
    global $testsPassed, $testsFailed;
    if ($expected === $actual) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n";
        $testsFailed++;
    }
}

function assert_not_null($value, $message) {
    global $testsPassed, $testsFailed;
    if ($value !== null) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (value is null)\n";
        $testsFailed++;
    }
}

function assert_null($value, $message) {
    global $testsPassed, $testsFailed;
    if ($value === null) {
        echo "✓ PASS: $message\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $message (value is not null: " . var_export($value, true) . ")\n";
        $testsFailed++;
    }
}

echo "=== Running Phase 2 Tests ===\n\n";

// Setup test database
$testDbPath = '/tmp/test_phase2_' . time() . '.db';
$db = new DB(['database_path' => $testDbPath]);

// Run migrations
echo "--- Setup: Running Migrations ---\n";
try {
    $db->runMigrations(__DIR__ . '/../db/migrations');
    echo "✓ Migrations completed\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize services (without OpenAI client for unit tests)
$promptService = new PromptService($db, null);
$vectorStoreService = new VectorStoreService($db, null);

// ==================== PromptService Tests ====================

echo "\n--- Test 1: Create Prompt ---\n";
try {
    $prompt = $promptService->createPrompt([
        'name' => 'Test Prompt',
        'description' => 'A test prompt',
        'content' => 'You are a test assistant'
    ]);
    
    assert_not_null($prompt, 'Prompt created');
    assert_equals('Test Prompt', $prompt['name'], 'Prompt name matches');
    assert_equals('A test prompt', $prompt['description'], 'Prompt description matches');
    assert_not_null($prompt['id'], 'Prompt has ID');
    
    $promptId = $prompt['id'];
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 2: Get Prompt ---\n";
try {
    $retrievedPrompt = $promptService->getPrompt($promptId);
    assert_not_null($retrievedPrompt, 'Prompt retrieved');
    assert_equals($promptId, $retrievedPrompt['id'], 'Prompt ID matches');
    assert_equals('Test Prompt', $retrievedPrompt['name'], 'Prompt name matches');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 3: List Prompts ---\n";
try {
    $prompts = $promptService->listPrompts();
    assert_true(count($prompts) > 0, 'Prompts list not empty');
    assert_equals($promptId, $prompts[0]['id'], 'First prompt matches created prompt');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 4: Update Prompt ---\n";
try {
    $updatedPrompt = $promptService->updatePrompt($promptId, [
        'description' => 'Updated description'
    ]);
    
    assert_not_null($updatedPrompt, 'Prompt updated');
    assert_equals('Updated description', $updatedPrompt['description'], 'Description updated');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 5: Create Prompt Version ---\n";
try {
    $version = $promptService->createPromptVersion($promptId, [
        'version' => 'v1.0',
        'summary' => 'Initial version'
    ]);
    
    assert_not_null($version, 'Version created');
    assert_equals('v1.0', $version['version'], 'Version matches');
    assert_equals($promptId, $version['prompt_id'], 'Version linked to prompt');
    
    $versionId = $version['id'];
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 6: List Prompt Versions ---\n";
try {
    $versions = $promptService->listPromptVersions($promptId);
    assert_true(count($versions) > 0, 'Versions list not empty');
    assert_equals($versionId, $versions[0]['id'], 'First version matches created version');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 7: Delete Prompt ---\n";
try {
    $promptService->deletePrompt($promptId);
    $deletedPrompt = $promptService->getPrompt($promptId);
    assert_null($deletedPrompt, 'Prompt deleted');
    
    // Versions should be deleted too (cascade)
    $versions = $promptService->listPromptVersions($promptId);
    assert_equals(0, count($versions), 'Versions deleted with prompt');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 8: Prompt Validation ---\n";
try {
    $caughtException = false;
    try {
        $promptService->createPrompt([
            'description' => 'Missing name'
        ]);
    } catch (Exception $e) {
        $caughtException = true;
    }
    
    assert_true($caughtException, 'Validates name is required');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

// ==================== VectorStoreService Tests ====================

echo "\n--- Test 9: Create Vector Store ---\n";
try {
    $store = $vectorStoreService->createVectorStore([
        'name' => 'Test Store'
    ]);
    
    assert_not_null($store, 'Vector store created');
    assert_equals('Test Store', $store['name'], 'Store name matches');
    assert_equals('ready', $store['status'], 'Store status is ready');
    assert_not_null($store['id'], 'Store has ID');
    
    $storeId = $store['id'];
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 10: Get Vector Store ---\n";
try {
    $retrievedStore = $vectorStoreService->getVectorStore($storeId);
    assert_not_null($retrievedStore, 'Vector store retrieved');
    assert_equals($storeId, $retrievedStore['id'], 'Store ID matches');
    assert_equals('Test Store', $retrievedStore['name'], 'Store name matches');
    assert_equals(0, $retrievedStore['file_count'], 'File count is 0');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 11: List Vector Stores ---\n";
try {
    $stores = $vectorStoreService->listVectorStores();
    assert_true(count($stores) > 0, 'Stores list not empty');
    assert_equals($storeId, $stores[0]['id'], 'First store matches created store');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 12: Add File to Vector Store (without OpenAI) ---\n";
try {
    $file = $vectorStoreService->addFile($storeId, [
        'name' => 'test.txt',
        'size' => 1024,
        'mime_type' => 'text/plain'
    ]);
    
    assert_not_null($file, 'File added');
    assert_equals('test.txt', $file['name'], 'File name matches');
    assert_equals($storeId, $file['vector_store_id'], 'File linked to store');
    assert_equals('pending', $file['ingestion_status'], 'Ingestion status is pending');
    
    $fileId = $file['id'];
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 13: List Vector Store Files ---\n";
try {
    $files = $vectorStoreService->listFiles($storeId);
    assert_true(count($files) > 0, 'Files list not empty');
    assert_equals($fileId, $files[0]['id'], 'First file matches added file');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 14: Update File Status ---\n";
try {
    $updatedFile = $vectorStoreService->updateFileStatus($fileId, 'completed');
    assert_equals('completed', $updatedFile['ingestion_status'], 'File status updated');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 15: Vector Store File Count ---\n";
try {
    $store = $vectorStoreService->getVectorStore($storeId);
    assert_equals(1, $store['file_count'], 'File count updated');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 16: Delete File ---\n";
try {
    $vectorStoreService->deleteFile($fileId);
    $deletedFile = $vectorStoreService->getFile($fileId);
    assert_null($deletedFile, 'File deleted');
    
    $store = $vectorStoreService->getVectorStore($storeId);
    assert_equals(0, $store['file_count'], 'File count decremented');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 17: Update Vector Store ---\n";
try {
    $updatedStore = $vectorStoreService->updateVectorStore($storeId, [
        'status' => 'ingesting'
    ]);
    
    assert_equals('ingesting', $updatedStore['status'], 'Store status updated');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 18: Delete Vector Store ---\n";
try {
    $vectorStoreService->deleteVectorStore($storeId);
    $deletedStore = $vectorStoreService->getVectorStore($storeId);
    assert_null($deletedStore, 'Vector store deleted');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 19: Vector Store Validation ---\n";
try {
    $caughtException = false;
    try {
        $vectorStoreService->createVectorStore([
            'metadata' => 'Missing name'
        ]);
    } catch (Exception $e) {
        $caughtException = true;
    }
    
    assert_true($caughtException, 'Validates name is required');
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\n--- Test 20: Filter Vector Stores by Status ---\n";
try {
    // Create stores with different statuses
    $store1 = $vectorStoreService->createVectorStore(['name' => 'Ready Store']);
    $store2 = $vectorStoreService->createVectorStore(['name' => 'Ingesting Store']);
    $vectorStoreService->updateVectorStore($store2['id'], ['status' => 'ingesting']);
    
    $readyStores = $vectorStoreService->listVectorStores(['status' => 'ready']);
    $ingestingStores = $vectorStoreService->listVectorStores(['status' => 'ingesting']);
    
    assert_equals(1, count($readyStores), 'One ready store');
    assert_equals(1, count($ingestingStores), 'One ingesting store');
    
    // Cleanup
    $vectorStoreService->deleteVectorStore($store1['id']);
    $vectorStoreService->deleteVectorStore($store2['id']);
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}

// Cleanup
echo "\n--- Cleanup ---\n";
try {
    unlink($testDbPath);
    echo "✓ Test database cleaned up\n";
} catch (Exception $e) {
    echo "Warning: Could not delete test database: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Total tests passed: $testsPassed\n";
echo "Total tests failed: $testsFailed\n";

if ($testsFailed === 0) {
    echo "\n✅ All tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed!\n";
    exit(1);
}
