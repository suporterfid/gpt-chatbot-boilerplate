<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressBlogExecutionLogger
 *
 * Tests execution logging, phase tracking, API call logging, and audit trail generation.
 */
class ExecutionLoggerTest extends TestCase {
    private $logger;
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/test_logs_' . time();
        mkdir($this->tempDir, 0755, true);

        $this->logger = new WordPressBlogExecutionLogger('test-article-123');
    }

    protected function tearDown(): void {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->recursiveDeleteDirectory($this->tempDir);
        }

        $this->logger = null;
    }

    /**
     * Helper: Recursively delete directory
     */
    private function recursiveDeleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDeleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test: Constructor initializes logger
     */
    public function testConstructorInitializesLogger(): void {
        $reflection = new ReflectionClass($this->logger);
        $articleIdProperty = $reflection->getProperty('articleId');
        $articleIdProperty->setAccessible(true);

        $this->assertEquals('test-article-123', $articleIdProperty->getValue($this->logger));
    }

    /**
     * Test: Start phase
     */
    public function testStartPhase(): void {
        $this->logger->startPhase('test_phase', ['key' => 'value']);

        $phases = $this->logger->getPhases();

        $this->assertArrayHasKey('test_phase', $phases);
        $this->assertEquals('in_progress', $phases['test_phase']['status']);
        $this->assertEquals('test_phase', $phases['test_phase']['phase']);
        $this->assertArrayHasKey('start_time', $phases['test_phase']);
        $this->assertEquals(['key' => 'value'], $phases['test_phase']['metadata']);
    }

    /**
     * Test: Complete phase
     */
    public function testCompletePhase(): void {
        $this->logger->startPhase('test_phase');
        usleep(100000); // 0.1 seconds
        $this->logger->completePhase('test_phase', ['output' => 'result']);

        $phases = $this->logger->getPhases();

        $this->assertEquals('completed', $phases['test_phase']['status']);
        $this->assertArrayHasKey('duration_seconds', $phases['test_phase']);
        $this->assertGreaterThan(0, $phases['test_phase']['duration_seconds']);
        $this->assertEquals(['output' => 'result'], $phases['test_phase']['result']);
    }

    /**
     * Test: Fail phase
     */
    public function testFailPhase(): void {
        $this->logger->startPhase('test_phase');
        $exception = new Exception('Test error', 500);
        $this->logger->failPhase('test_phase', 'Phase failed', $exception);

        $phases = $this->logger->getPhases();

        $this->assertEquals('failed', $phases['test_phase']['status']);
        $this->assertEquals('Phase failed', $phases['test_phase']['error']);
        $this->assertArrayHasKey('exception', $phases['test_phase']);
        $this->assertEquals('Test error', $phases['test_phase']['exception']['message']);
        $this->assertEquals(500, $phases['test_phase']['exception']['code']);
    }

    /**
     * Test: Fail phase without starting it first
     */
    public function testFailPhaseWithoutStarting(): void {
        $this->logger->failPhase('unstarted_phase', 'Error occurred');

        $phases = $this->logger->getPhases();

        $this->assertArrayHasKey('unstarted_phase', $phases);
        $this->assertEquals('failed', $phases['unstarted_phase']['status']);
    }

    /**
     * Test: Log API call
     */
    public function testLogApiCall(): void {
        $this->logger->logApiCall(
            'openai',
            'create_completion',
            ['model' => 'gpt-4', 'prompt' => 'test'],
            ['output' => 'response'],
            0.05
        );

        $apiCalls = $this->logger->getApiCalls();

        $this->assertCount(1, $apiCalls);
        $this->assertEquals('openai', $apiCalls[0]['api']);
        $this->assertEquals('create_completion', $apiCalls[0]['operation']);
        $this->assertEquals(0.05, $apiCalls[0]['cost_usd']);
    }

    /**
     * Test: Calculate GPT-4 cost
     */
    public function testCalculateGPT4Cost(): void {
        // 1000 input tokens + 500 output tokens
        // Cost = (1000/1000 * 0.03) + (500/1000 * 0.06) = 0.03 + 0.03 = 0.06
        $cost = $this->logger->calculateGPT4Cost(1000, 500);

        $this->assertEquals(0.06, $cost);
    }

    /**
     * Test: Calculate GPT-4 cost - different token counts
     */
    public function testCalculateGPT4CostVariousTokens(): void {
        // 2500 input tokens + 1500 output tokens
        // Cost = (2500/1000 * 0.03) + (1500/1000 * 0.06) = 0.075 + 0.09 = 0.165
        $cost = $this->logger->calculateGPT4Cost(2500, 1500);

        $this->assertEquals(0.165, $cost);
    }

    /**
     * Test: Calculate DALL-E 3 cost - standard 1024
     */
    public function testCalculateDALLE3CostStandard1024(): void {
        $cost = $this->logger->calculateDALLE3Cost('1024x1024', 'standard');

        $this->assertEquals(0.040, $cost);
    }

    /**
     * Test: Calculate DALL-E 3 cost - standard 1792
     */
    public function testCalculateDALLE3CostStandard1792(): void {
        $cost = $this->logger->calculateDALLE3Cost('1792x1024', 'standard');

        $this->assertEquals(0.080, $cost);
    }

    /**
     * Test: Calculate DALL-E 3 cost - HD 1024
     */
    public function testCalculateDALLE3CostHD1024(): void {
        $cost = $this->logger->calculateDALLE3Cost('1024x1024', 'hd');

        $this->assertEquals(0.080, $cost);
    }

    /**
     * Test: Calculate DALL-E 3 cost - HD 1792
     */
    public function testCalculateDALLE3CostHD1792(): void {
        $cost = $this->logger->calculateDALLE3Cost('1792x1024', 'hd');

        $this->assertEquals(0.120, $cost);
    }

    /**
     * Test: Log error
     */
    public function testLogError(): void {
        $this->logger->error('Test error message', ['context_key' => 'context_value']);

        $errors = $this->logger->getErrors();

        $this->assertCount(1, $errors);
        $this->assertEquals('Test error message', $errors[0]['message']);
        $this->assertEquals(['context_key' => 'context_value'], $errors[0]['context']);
    }

    /**
     * Test: Log warning
     */
    public function testLogWarning(): void {
        $this->logger->warning('Test warning message', ['warn_key' => 'warn_value']);

        $warnings = $this->logger->getWarnings();

        $this->assertCount(1, $warnings);
        $this->assertEquals('Test warning message', $warnings[0]['message']);
        $this->assertEquals(['warn_key' => 'warn_value'], $warnings[0]['context']);
    }

    /**
     * Test: Generate summary
     */
    public function testGenerateSummary(): void {
        $this->logger->startPhase('phase1');
        $this->logger->completePhase('phase1');

        $this->logger->logApiCall('openai', 'completion', [], [], 0.10);
        $this->logger->error('Test error');
        $this->logger->warning('Test warning');

        $summary = $this->logger->generateSummary();

        $this->assertEquals('test-article-123', $summary['article_id']);
        $this->assertArrayHasKey('execution_status', $summary);
        $this->assertArrayHasKey('total_duration_seconds', $summary);
        $this->assertArrayHasKey('phases', $summary);
        $this->assertArrayHasKey('api_calls', $summary);
        $this->assertArrayHasKey('errors', $summary);
        $this->assertArrayHasKey('warnings', $summary);

        $this->assertEquals(1, $summary['api_calls']['total_calls']);
        $this->assertEquals(0.10, $summary['api_calls']['total_cost_usd']);
        $this->assertEquals(1, $summary['errors']['count']);
        $this->assertEquals(1, $summary['warnings']['count']);
    }

    /**
     * Test: Execution status - success
     */
    public function testExecutionStatusSuccess(): void {
        $this->logger->startPhase('phase1');
        $this->logger->completePhase('phase1');

        $this->logger->startPhase('phase2');
        $this->logger->completePhase('phase2');

        $summary = $this->logger->generateSummary();

        $this->assertEquals('success', $summary['execution_status']);
    }

    /**
     * Test: Execution status - failed
     */
    public function testExecutionStatusFailed(): void {
        $this->logger->startPhase('phase1');
        $this->logger->failPhase('phase1', 'Error');

        $this->logger->startPhase('phase2');
        $this->logger->failPhase('phase2', 'Error');

        $summary = $this->logger->generateSummary();

        $this->assertEquals('failed', $summary['execution_status']);
    }

    /**
     * Test: Execution status - partial success
     */
    public function testExecutionStatusPartialSuccess(): void {
        $this->logger->startPhase('phase1');
        $this->logger->completePhase('phase1');

        $this->logger->startPhase('phase2');
        $this->logger->failPhase('phase2', 'Error');

        $summary = $this->logger->generateSummary();

        $this->assertEquals('partial_success', $summary['execution_status']);
    }

    /**
     * Test: Generate audit trail
     */
    public function testGenerateAuditTrail(): void {
        $this->logger->startPhase('test_phase');
        $this->logger->completePhase('test_phase');
        $this->logger->logApiCall('openai', 'test', [], [], 0.05);

        $auditTrail = $this->logger->generateAuditTrail();

        $this->assertEquals('1.0', $auditTrail['version']);
        $this->assertEquals('test-article-123', $auditTrail['article_id']);
        $this->assertArrayHasKey('summary', $auditTrail);
        $this->assertArrayHasKey('phases', $auditTrail);
        $this->assertArrayHasKey('api_calls', $auditTrail);
        $this->assertArrayHasKey('errors', $auditTrail);
        $this->assertArrayHasKey('warnings', $auditTrail);
        $this->assertArrayHasKey('all_logs', $auditTrail);
    }

    /**
     * Test: Save to file
     */
    public function testSaveToFile(): void {
        $filePath = $this->tempDir . '/audit_trail.json';

        $this->logger->startPhase('test_phase');
        $this->logger->completePhase('test_phase');

        $result = $this->logger->saveToFile($filePath);

        $this->assertTrue($result);
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $decoded = json_decode($content, true);

        $this->assertNotNull($decoded);
        $this->assertEquals('test-article-123', $decoded['article_id']);
    }

    /**
     * Test: Save to file - invalid path
     */
    public function testSaveToFileInvalidPath(): void {
        $filePath = '/invalid/path/audit_trail.json';

        $result = $this->logger->saveToFile($filePath);

        $this->assertFalse($result);

        // Should log error
        $errors = $this->logger->getErrors();
        $this->assertGreaterThan(0, count($errors));
    }

    /**
     * Test: Get formatted summary
     */
    public function testGetFormattedSummary(): void {
        $this->logger->startPhase('phase1');
        $this->logger->completePhase('phase1');
        $this->logger->logApiCall('openai', 'test', [], [], 0.10);

        $formatted = $this->logger->getFormattedSummary();

        $this->assertStringContainsString('WordPress Blog Generation Execution Summary', $formatted);
        $this->assertStringContainsString('test-article-123', $formatted);
        $this->assertStringContainsString('phase1', $formatted);
        $this->assertStringContainsString('$0.10', $formatted);
    }

    /**
     * Test: Get total execution time
     */
    public function testGetTotalExecutionTime(): void {
        usleep(100000); // 0.1 seconds
        $time = $this->logger->getTotalExecutionTime();

        $this->assertGreaterThan(0, $time);
        $this->assertLessThan(1, $time); // Should be less than 1 second
    }

    /**
     * Test: Get total cost
     */
    public function testGetTotalCost(): void {
        $this->logger->logApiCall('openai', 'test1', [], [], 0.05);
        $this->logger->logApiCall('openai', 'test2', [], [], 0.10);
        $this->logger->logApiCall('dalle', 'test3', [], [], 0.08);

        $totalCost = $this->logger->getTotalCost();

        $this->assertEquals(0.23, $totalCost);
    }

    /**
     * Test: Get total cost - no API calls
     */
    public function testGetTotalCostNoApiCalls(): void {
        $totalCost = $this->logger->getTotalCost();

        $this->assertEquals(0.00, $totalCost);
    }

    /**
     * Test: Multiple phases tracking
     */
    public function testMultiplePhasesTracking(): void {
        $this->logger->startPhase('phase1');
        $this->logger->completePhase('phase1');

        $this->logger->startPhase('phase2');
        $this->logger->completePhase('phase2');

        $this->logger->startPhase('phase3');
        $this->logger->failPhase('phase3', 'Error');

        $phases = $this->logger->getPhases();

        $this->assertCount(3, $phases);
        $this->assertEquals('completed', $phases['phase1']['status']);
        $this->assertEquals('completed', $phases['phase2']['status']);
        $this->assertEquals('failed', $phases['phase3']['status']);
    }

    /**
     * Test: API calls grouped by API
     */
    public function testApiCallsGroupedByApi(): void {
        $this->logger->logApiCall('openai', 'test1', [], [], 0.05);
        $this->logger->logApiCall('openai', 'test2', [], [], 0.10);
        $this->logger->logApiCall('wordpress', 'test3', [], [], 0);
        $this->logger->logApiCall('google_drive', 'test4', [], [], 0);

        $summary = $this->logger->generateSummary();

        $this->assertEquals(4, $summary['api_calls']['total_calls']);
        $this->assertEquals(2, $summary['api_calls']['by_api']['openai']);
        $this->assertEquals(1, $summary['api_calls']['by_api']['wordpress']);
        $this->assertEquals(1, $summary['api_calls']['by_api']['google_drive']);
    }

    /**
     * Test: Cost tracking by API
     */
    public function testCostTrackingByApi(): void {
        $this->logger->logApiCall('openai', 'test1', [], [], 0.15);
        $this->logger->logApiCall('openai', 'test2', [], [], 0.20);
        $this->logger->logApiCall('dalle', 'test3', [], [], 0.08);

        $summary = $this->logger->generateSummary();

        $this->assertEquals(0.35, $summary['api_calls']['cost_by_api']['openai']);
        $this->assertEquals(0.08, $summary['api_calls']['cost_by_api']['dalle']);
        $this->assertEquals(0.43, $summary['api_calls']['total_cost_usd']);
    }

    /**
     * Test: Phase duration tracking
     */
    public function testPhaseDurationTracking(): void {
        $this->logger->startPhase('fast_phase');
        usleep(50000); // 0.05 seconds
        $this->logger->completePhase('fast_phase');

        $phases = $this->logger->getPhases();

        $this->assertGreaterThanOrEqual(0.04, $phases['fast_phase']['duration_seconds']);
        $this->assertLessThanOrEqual(0.2, $phases['fast_phase']['duration_seconds']);
    }

    /**
     * Test: Current phase tracking
     */
    public function testCurrentPhaseTracking(): void {
        $this->logger->startPhase('phase1');

        $reflection = new ReflectionClass($this->logger);
        $property = $reflection->getProperty('currentPhase');
        $property->setAccessible(true);

        $this->assertEquals('phase1', $property->getValue($this->logger));

        $this->logger->completePhase('phase1');

        $this->assertNull($property->getValue($this->logger));
    }

    /**
     * Test: Error messages collected
     */
    public function testErrorMessagesCollected(): void {
        $this->logger->error('Error 1');
        $this->logger->error('Error 2');
        $this->logger->error('Error 3');

        $summary = $this->logger->generateSummary();

        $this->assertEquals(3, $summary['errors']['count']);
        $this->assertContains('Error 1', $summary['errors']['messages']);
        $this->assertContains('Error 2', $summary['errors']['messages']);
        $this->assertContains('Error 3', $summary['errors']['messages']);
    }

    /**
     * Test: Warning messages collected
     */
    public function testWarningMessagesCollected(): void {
        $this->logger->warning('Warning 1');
        $this->logger->warning('Warning 2');

        $summary = $this->logger->generateSummary();

        $this->assertEquals(2, $summary['warnings']['count']);
        $this->assertContains('Warning 1', $summary['warnings']['messages']);
        $this->assertContains('Warning 2', $summary['warnings']['messages']);
    }

    /**
     * Test: Duration formatting
     */
    public function testDurationFormatting(): void {
        $this->logger->startPhase('phase1');
        usleep(100000); // 0.1 seconds
        $this->logger->completePhase('phase1');

        $summary = $this->logger->generateSummary();

        // Should include formatted duration
        $this->assertArrayHasKey('total_duration_formatted', $summary);
        $this->assertStringContainsString('s', $summary['total_duration_formatted']);
    }

    /**
     * Test: Complete phase logs warning if not started
     */
    public function testCompletePhaseNotStartedLogsWarning(): void {
        $this->logger->completePhase('nonexistent_phase');

        $warnings = $this->logger->getWarnings();

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('was not started', $warnings[0]['message']);
    }

    /**
     * Test: Zero cost API calls tracked
     */
    public function testZeroCostApiCallsTracked(): void {
        $this->logger->logApiCall('wordpress', 'create_post', [], [], 0);

        $apiCalls = $this->logger->getApiCalls();

        $this->assertCount(1, $apiCalls);
        $this->assertEquals(0, $apiCalls[0]['cost_usd']);

        $totalCost = $this->logger->getTotalCost();
        $this->assertEquals(0, $totalCost);
    }
}
