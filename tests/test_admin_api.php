#!/usr/bin/env php
<?php
/**
 * Integration test for Admin API
 */

// Set up environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_admin_token_for_phase1_testing_min32chars';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET['action'] = 'create_agent';

// Mock stdin
$GLOBALS['http_response_code_value'] = 200;
$GLOBALS['http_headers'] = [];

function http_response_code($code = null) {
    if ($code !== null) {
        $GLOBALS['http_response_code_value'] = $code;
    }
    return $GLOBALS['http_response_code_value'];
}

function header($header) {
    $GLOBALS['http_headers'][] = $header;
}

// Create a test input stream
$testInput = json_encode([
    'name' => 'Integration Test Agent',
    'description' => 'Created via integration test',
    'api_type' => 'responses',
    'model' => 'gpt-4o',
    'temperature' => 0.8,
    'tools' => [['type' => 'file_search']],
    'vector_store_ids' => ['vs_integration_test'],
    'is_default' => true
]);

// Write to a temp file for php://input simulation
$tmpFile = '/tmp/test_admin_input.json';
file_put_contents($tmpFile, $testInput);

// Capture output
ob_start();

// Change to repo directory
chdir(__DIR__ . '/..');

// Override file_get_contents for php://input
stream_wrapper_unregister("php");
stream_wrapper_register("php", "MockPhpStream");

class MockPhpStream {
    public $context;
    private $data;
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            $this->data = file_get_contents('/tmp/test_admin_input.json');
            $this->position = 0;
            return true;
        }
        return false;
    }
    
    public function stream_read($count) {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    public function stream_eof() {
        return $this->position >= strlen($this->data);
    }
    
    public function stream_stat() {
        return [];
    }
}

// Load and execute admin-api.php
try {
    require 'admin-api.php';
} catch (Exception $e) {
    // exit() was called
}

$output = ob_get_clean();

echo "HTTP Response Code: " . $GLOBALS['http_response_code_value'] . "\n";
echo "Headers:\n";
foreach ($GLOBALS['http_headers'] as $header) {
    echo "  $header\n";
}
echo "\nResponse Body:\n";
echo $output . "\n";

// Parse and verify
$response = json_decode($output, true);
if ($response && isset($response['data'])) {
    echo "\n✅ Admin API create_agent test PASSED\n";
    echo "Agent ID: " . $response['data']['id'] . "\n";
    echo "Agent Name: " . $response['data']['name'] . "\n";
    
    // Test list agents
    echo "\n--- Testing list_agents ---\n";
    $_GET['action'] = 'list_agents';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    try {
        require 'admin-api.php';
    } catch (Exception $e) {
        // exit() was called
    }
    $listOutput = ob_get_clean();
    
    $listResponse = json_decode($listOutput, true);
    if ($listResponse && isset($listResponse['data']) && count($listResponse['data']) > 0) {
        echo "✅ List agents test PASSED\n";
        echo "Found " . count($listResponse['data']) . " agent(s)\n";
    } else {
        echo "❌ List agents test FAILED\n";
    }
} else {
    echo "\n❌ Admin API create_agent test FAILED\n";
    echo "Response: $output\n";
}

// Cleanup
unlink($tmpFile);
