#!/usr/bin/env php
<?php
/**
 * Unit test for list_models endpoint
 */

echo "Testing list_models endpoint\n";
echo "============================\n\n";

// Test 1: Verify OpenAIAdminClient has listModels method
echo "Test 1: OpenAIAdminClient::listModels() method exists... ";
require_once __DIR__ . '/../includes/OpenAIAdminClient.php';

$reflectionClass = new ReflectionClass('OpenAIAdminClient');
if ($reflectionClass->hasMethod('listModels')) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Method not found\n";
    exit(1);
}

// Test 2: Verify the method is public
echo "Test 2: listModels() is public... ";
$method = $reflectionClass->getMethod('listModels');
if ($method->isPublic()) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Method is not public\n";
    exit(1);
}

// Test 3: Verify admin-api.php handles list_models action
echo "Test 3: admin-api.php contains list_models action... ";
$adminApiContent = file_get_contents(__DIR__ . '/../admin-api.php');
if (strpos($adminApiContent, "case 'list_models':") !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Action not found in admin-api.php\n";
    exit(1);
}

// Test 4: Verify admin-api.php calls listModels on openaiClient
echo "Test 4: admin-api.php calls openaiClient->listModels()... ";
if (strpos($adminApiContent, '$models = $openaiClient->listModels()') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Method call not found\n";
    exit(1);
}

// Test 5: Verify admin.js has listModels method
echo "Test 5: admin.js AdminAPI class has listModels()... ";
$adminJsContent = file_get_contents(__DIR__ . '/../public/admin/admin.js');
if (strpos($adminJsContent, 'listModels()') !== false && 
    strpos($adminJsContent, "return this.request('list_models')") !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Method not found in admin.js\n";
    exit(1);
}

// Test 6: Verify showCreateAgentModal fetches models
echo "Test 6: showCreateAgentModal fetches models... ";
if (strpos($adminJsContent, 'models = await api.listModels()') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Models are not being fetched\n";
    exit(1);
}

// Test 7: Verify form creates dropdown for models
echo "Test 7: Agent form creates dropdown for models... ";
if (strpos($adminJsContent, '<select name="model"') !== false && 
    strpos($adminJsContent, 'modelOptions') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Model dropdown not created\n";
    exit(1);
}

// Test 8: Verify model dropdown has fallback
echo "Test 8: Model input has fallback to text field... ";
if (strpos($adminJsContent, 'modelOptions ?') !== false && 
    strpos($adminJsContent, '<input type="text" name="model"') !== false) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL - Fallback not implemented\n";
    exit(1);
}

echo "\n✓ All tests passed!\n";
exit(0);
