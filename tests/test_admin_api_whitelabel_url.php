#!/usr/bin/env php
<?php
if (!getenv('ADMIN_TOKEN')) {
    $defaultToken = 'test_admin_token_for_phase1_testing_min32chars';
    putenv('ADMIN_TOKEN=' . $defaultToken);
    $_ENV['ADMIN_TOKEN'] = $defaultToken;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';

const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[0;33m";
const NC = "\033[0m";

$allPassed = true;
$agentService = null;
$agentId = null;

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

log_message("\n=== Admin API get_whitelabel_url Endpoint Test ===", YELLOW);

try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db'
    ];

    $db = new DB($dbConfig);
    $db->runMigrations(__DIR__ . '/../db/migrations');
    $agentService = new AgentService($db);
    pass('Initialized AgentService');

    $agent = $agentService->createAgent([
        'name' => 'Admin API Whitelabel URL Test ' . uniqid(),
        'description' => 'Automated test agent for get_whitelabel_url endpoint',
        'api_type' => 'responses',
        'model' => 'gpt-4o-mini'
    ]);
    $agentId = $agent['id'];
    pass('Created test agent');

    $wlConfig = [
        'wl_title' => 'Test Widget',
        'wl_welcome_message' => 'Hello from automated test!',
    ];

    $agent = $agentService->enableWhitelabel($agentId, $wlConfig);
    pass('Enabled whitelabel publishing for agent');

    $slug = 'support-test-agent-' . substr($agentId, -4);
    $agent = $agentService->updateWhitelabelConfig($agentId, ['vanity_path' => $slug]);
    $slug = $agent['vanity_path'];
    $authToken = $config['admin']['token'] ?: 'test_admin_token_for_phase1_testing_min32chars';

    $queryString = http_build_query([
        'action' => 'get_whitelabel_url',
        'id' => $agentId
    ]);

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $command = sprintf('php %s', escapeshellarg(dirname(__DIR__) . '/tests/helpers/admin_api_request.php'));
    $baseEnv = $_ENV;
    $baseEnv['PATH'] = getenv('PATH') ?: '/usr/bin:/bin';
    $env = array_merge($baseEnv, [
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'localhost',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $authToken,
        'QUERY_STRING' => $queryString
    ]);

    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__), $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to launch admin_api_request helper');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException('Admin API request failed: ' . trim($errors));
    }

    $response = json_decode($output, true);
    if (!$response || !isset($response['data'])) {
        throw new RuntimeException('Unexpected admin API response: ' . $output);
    }

    $data = $response['data'];
    $baseUrl = 'http://localhost';
    $expectedUrl = $baseUrl . '/public/whitelabel.php?id=' . urlencode($agent['agent_public_id']);
    $expectedVanity = $baseUrl . '/public/whitelabel.php?path=' . urlencode($slug);
    $expectedPretty = $baseUrl . '/chat/@' . urlencode($slug);

    if (($data['url'] ?? null) === $expectedUrl) {
        pass('Primary whitelabel URL returned correctly');
    } else {
        fail('Primary whitelabel URL mismatch: ' . ($data['url'] ?? 'missing'));
    }

    if (($data['vanity_url'] ?? null) === $expectedVanity) {
        pass('Vanity URL returned correctly');
    } else {
        fail('Vanity URL mismatch: ' . ($data['vanity_url'] ?? 'missing'));
    }

    if (($data['pretty_url'] ?? null) === $expectedPretty) {
        pass('Pretty URL returned correctly');
    } else {
        fail('Pretty URL mismatch: ' . ($data['pretty_url'] ?? 'missing'));
    }

    if (($data['agent_public_id'] ?? null) === $agent['agent_public_id']) {
        pass('Agent public ID included in response');
    } else {
        fail('Agent public ID missing or incorrect');
    }
} catch (Exception $e) {
    fail('Test execution error: ' . $e->getMessage());
}

if ($agentService && $agentId) {
    try {
        $agentService->deleteAgent($agentId);
        pass('Cleaned up test agent');
    } catch (Exception $cleanupError) {
        fail('Failed to delete test agent: ' . $cleanupError->getMessage());
    }
}

exit($allPassed ? 0 : 1);
