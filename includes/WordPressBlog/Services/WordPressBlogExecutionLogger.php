<?php
/**
 * WordPress Blog Execution Logger Service
 *
 * Logs all execution phases, API calls, errors, and metrics for blog generation.
 * Generates comprehensive audit trail in JSON format.
 *
 * Responsibilities:
 * - Log phase start/complete/error events
 * - Track API calls with request/response data
 * - Calculate costs for OpenAI API calls
 * - Record errors and warnings
 * - Calculate execution metrics (timing, success rates)
 * - Generate human-readable audit trail
 * - Save logs to file or database
 *
 * @package WordPressBlog\Services
 */

class WordPressBlogExecutionLogger {
    private $articleId;
    private $logs = [];
    private $phases = [];
    private $apiCalls = [];
    private $errors = [];
    private $warnings = [];
    private $startTime;
    private $currentPhase = null;

    /**
     * API Cost Constants (as of 2024)
     */
    const GPT4_INPUT_COST_PER_1K = 0.03;    // $0.03 per 1K input tokens
    const GPT4_OUTPUT_COST_PER_1K = 0.06;   // $0.06 per 1K output tokens
    const DALLE3_STANDARD_1024 = 0.040;
    const DALLE3_STANDARD_1792 = 0.080;
    const DALLE3_HD_1024 = 0.080;
    const DALLE3_HD_1792 = 0.120;

    /**
     * Constructor
     *
     * @param string $articleId Article ID for this execution
     */
    public function __construct($articleId) {
        $this->articleId = $articleId;
        $this->startTime = microtime(true);

        $this->log('execution_start', [
            'article_id' => $articleId,
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION
        ]);
    }

    /**
     * Start a phase
     *
     * @param string $phaseName Phase name
     * @param array $metadata Phase metadata
     */
    public function startPhase($phaseName, array $metadata = []) {
        $this->currentPhase = $phaseName;

        $phaseData = [
            'phase' => $phaseName,
            'status' => 'in_progress',
            'start_time' => microtime(true),
            'start_timestamp' => date('Y-m-d H:i:s'),
            'metadata' => $metadata
        ];

        $this->phases[$phaseName] = $phaseData;

        $this->log('phase_start', [
            'phase' => $phaseName,
            'metadata' => $metadata
        ]);
    }

    /**
     * Complete a phase
     *
     * @param string $phaseName Phase name
     * @param array $result Phase result data
     */
    public function completePhase($phaseName, array $result = []) {
        if (!isset($this->phases[$phaseName])) {
            $this->warning("Phase '{$phaseName}' was not started");
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - $this->phases[$phaseName]['start_time'];

        $this->phases[$phaseName]['status'] = 'completed';
        $this->phases[$phaseName]['end_time'] = $endTime;
        $this->phases[$phaseName]['end_timestamp'] = date('Y-m-d H:i:s');
        $this->phases[$phaseName]['duration_seconds'] = round($duration, 2);
        $this->phases[$phaseName]['result'] = $result;

        $this->log('phase_complete', [
            'phase' => $phaseName,
            'duration_seconds' => round($duration, 2),
            'result_summary' => $this->summarizeResult($result)
        ]);

        if ($this->currentPhase === $phaseName) {
            $this->currentPhase = null;
        }
    }

    /**
     * Mark a phase as failed
     *
     * @param string $phaseName Phase name
     * @param string $errorMessage Error message
     * @param Exception|null $exception Exception object
     */
    public function failPhase($phaseName, $errorMessage, $exception = null) {
        if (!isset($this->phases[$phaseName])) {
            $this->phases[$phaseName] = [
                'phase' => $phaseName,
                'start_time' => microtime(true),
                'start_timestamp' => date('Y-m-d H:i:s')
            ];
        }

        $endTime = microtime(true);
        $duration = $endTime - $this->phases[$phaseName]['start_time'];

        $this->phases[$phaseName]['status'] = 'failed';
        $this->phases[$phaseName]['end_time'] = $endTime;
        $this->phases[$phaseName]['end_timestamp'] = date('Y-m-d H:i:s');
        $this->phases[$phaseName]['duration_seconds'] = round($duration, 2);
        $this->phases[$phaseName]['error'] = $errorMessage;

        if ($exception) {
            $this->phases[$phaseName]['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        $this->error($errorMessage, [
            'phase' => $phaseName,
            'exception' => $exception ? $exception->getMessage() : null
        ]);

        if ($this->currentPhase === $phaseName) {
            $this->currentPhase = null;
        }
    }

    /**
     * Log an API call
     *
     * @param string $apiName API name (openai, wordpress, google_drive)
     * @param string $operation Operation name
     * @param array $request Request data
     * @param array $response Response data
     * @param float|null $cost Cost in USD
     */
    public function logApiCall($apiName, $operation, array $request, array $response, $cost = null) {
        $callData = [
            'api' => $apiName,
            'operation' => $operation,
            'timestamp' => date('Y-m-d H:i:s'),
            'request' => $this->sanitizeForLogging($request),
            'response' => $this->sanitizeForLogging($response),
            'cost_usd' => $cost,
            'phase' => $this->currentPhase
        ];

        $this->apiCalls[] = $callData;

        $this->log('api_call', [
            'api' => $apiName,
            'operation' => $operation,
            'cost_usd' => $cost
        ]);
    }

    /**
     * Calculate GPT-4 API cost
     *
     * @param int $inputTokens Input tokens
     * @param int $outputTokens Output tokens
     * @return float Cost in USD
     */
    public function calculateGPT4Cost($inputTokens, $outputTokens) {
        $inputCost = ($inputTokens / 1000) * self::GPT4_INPUT_COST_PER_1K;
        $outputCost = ($outputTokens / 1000) * self::GPT4_OUTPUT_COST_PER_1K;
        return round($inputCost + $outputCost, 4);
    }

    /**
     * Calculate DALL-E 3 cost
     *
     * @param string $size Image size (1024x1024 or 1792x1024)
     * @param string $quality Quality (standard or hd)
     * @return float Cost in USD
     */
    public function calculateDALLE3Cost($size, $quality = 'standard') {
        if ($quality === 'hd') {
            return $size === '1792x1024' ? self::DALLE3_HD_1792 : self::DALLE3_HD_1024;
        } else {
            return $size === '1792x1024' ? self::DALLE3_STANDARD_1792 : self::DALLE3_STANDARD_1024;
        }
    }

    /**
     * Log a generic event
     *
     * @param string $eventType Event type
     * @param array $data Event data
     */
    public function log($eventType, array $data = []) {
        $this->logs[] = [
            'timestamp' => microtime(true),
            'datetime' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'phase' => $this->currentPhase,
            'data' => $data
        ];
    }

    /**
     * Log an error
     *
     * @param string $message Error message
     * @param array $context Error context
     */
    public function error($message, array $context = []) {
        $this->errors[] = [
            'timestamp' => microtime(true),
            'datetime' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'phase' => $this->currentPhase
        ];

        $this->log('error', [
            'message' => $message,
            'context' => $context
        ]);
    }

    /**
     * Log a warning
     *
     * @param string $message Warning message
     * @param array $context Warning context
     */
    public function warning($message, array $context = []) {
        $this->warnings[] = [
            'timestamp' => microtime(true),
            'datetime' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'phase' => $this->currentPhase
        ];

        $this->log('warning', [
            'message' => $message,
            'context' => $context
        ]);
    }

    /**
     * Generate execution summary
     *
     * @return array Execution summary
     */
    public function generateSummary() {
        $endTime = microtime(true);
        $totalDuration = $endTime - $this->startTime;

        // Calculate costs
        $totalCost = 0;
        $costByApi = [];

        foreach ($this->apiCalls as $call) {
            if (isset($call['cost_usd']) && $call['cost_usd'] > 0) {
                $totalCost += $call['cost_usd'];

                if (!isset($costByApi[$call['api']])) {
                    $costByApi[$call['api']] = 0;
                }
                $costByApi[$call['api']] += $call['cost_usd'];
            }
        }

        // Phase statistics
        $phaseStats = [];
        foreach ($this->phases as $name => $phase) {
            $phaseStats[$name] = [
                'status' => $phase['status'],
                'duration_seconds' => $phase['duration_seconds'] ?? 0,
                'start' => $phase['start_timestamp'],
                'end' => $phase['end_timestamp'] ?? null
            ];
        }

        return [
            'article_id' => $this->articleId,
            'execution_status' => $this->determineExecutionStatus(),
            'start_time' => date('Y-m-d H:i:s', $this->startTime),
            'end_time' => date('Y-m-d H:i:s', $endTime),
            'total_duration_seconds' => round($totalDuration, 2),
            'total_duration_formatted' => $this->formatDuration($totalDuration),
            'phases' => $phaseStats,
            'api_calls' => [
                'total_calls' => count($this->apiCalls),
                'by_api' => $this->groupApiCalls(),
                'total_cost_usd' => round($totalCost, 2),
                'cost_by_api' => $costByApi
            ],
            'errors' => [
                'count' => count($this->errors),
                'messages' => array_column($this->errors, 'message')
            ],
            'warnings' => [
                'count' => count($this->warnings),
                'messages' => array_column($this->warnings, 'message')
            ]
        ];
    }

    /**
     * Generate full audit trail
     *
     * @return array Complete audit trail
     */
    public function generateAuditTrail() {
        return [
            'version' => '1.0',
            'article_id' => $this->articleId,
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $this->generateSummary(),
            'phases' => $this->phases,
            'api_calls' => $this->apiCalls,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'all_logs' => $this->logs
        ];
    }

    /**
     * Save audit trail to file
     *
     * @param string $filePath File path
     * @return bool Success
     */
    public function saveToFile($filePath) {
        $auditTrail = $this->generateAuditTrail();
        $json = json_encode($auditTrail, JSON_PRETTY_PRINT);

        $result = file_put_contents($filePath, $json);

        if ($result === false) {
            $this->error("Failed to save audit trail to file: {$filePath}");
            return false;
        }

        $this->log('audit_trail_saved', ['file_path' => $filePath]);
        return true;
    }

    /**
     * Get execution summary as formatted text
     *
     * @return string Formatted summary
     */
    public function getFormattedSummary() {
        $summary = $this->generateSummary();
        $output = [];

        $output[] = "=== WordPress Blog Generation Execution Summary ===";
        $output[] = "";
        $output[] = "Article ID: {$summary['article_id']}";
        $output[] = "Status: {$summary['execution_status']}";
        $output[] = "Duration: {$summary['total_duration_formatted']}";
        $output[] = "Total Cost: $" . number_format($summary['api_calls']['total_cost_usd'], 2);
        $output[] = "";

        $output[] = "--- Phases ---";
        foreach ($summary['phases'] as $name => $phase) {
            $status = strtoupper($phase['status']);
            $duration = $phase['duration_seconds'];
            $output[] = "  {$name}: {$status} ({$duration}s)";
        }
        $output[] = "";

        $output[] = "--- API Calls ---";
        $output[] = "  Total: {$summary['api_calls']['total_calls']}";
        foreach ($summary['api_calls']['by_api'] as $api => $count) {
            $cost = isset($summary['api_calls']['cost_by_api'][$api])
                ? '$' . number_format($summary['api_calls']['cost_by_api'][$api], 2)
                : '$0.00';
            $output[] = "  {$api}: {$count} calls ({$cost})";
        }
        $output[] = "";

        if ($summary['errors']['count'] > 0) {
            $output[] = "--- Errors ({$summary['errors']['count']}) ---";
            foreach ($summary['errors']['messages'] as $error) {
                $output[] = "  - {$error}";
            }
            $output[] = "";
        }

        if ($summary['warnings']['count'] > 0) {
            $output[] = "--- Warnings ({$summary['warnings']['count']}) ---";
            foreach ($summary['warnings']['messages'] as $warning) {
                $output[] = "  - {$warning}";
            }
            $output[] = "";
        }

        return implode("\n", $output);
    }

    /**
     * Determine overall execution status
     *
     * @return string Status (success, partial_success, failed)
     */
    private function determineExecutionStatus() {
        $statuses = array_column($this->phases, 'status');

        if (empty($statuses)) {
            return 'unknown';
        }

        if (in_array('failed', $statuses)) {
            $failedCount = count(array_filter($statuses, function($s) { return $s === 'failed'; }));
            $totalCount = count($statuses);

            if ($failedCount === $totalCount) {
                return 'failed';
            } else {
                return 'partial_success';
            }
        }

        $completedCount = count(array_filter($statuses, function($s) { return $s === 'completed'; }));
        if ($completedCount === count($statuses)) {
            return 'success';
        }

        return 'in_progress';
    }

    /**
     * Format duration in human-readable format
     *
     * @param float $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function formatDuration($seconds) {
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return "{$minutes}m " . round($secs) . "s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }

    /**
     * Group API calls by API name
     *
     * @return array Count by API
     */
    private function groupApiCalls() {
        $grouped = [];

        foreach ($this->apiCalls as $call) {
            $api = $call['api'];
            if (!isset($grouped[$api])) {
                $grouped[$api] = 0;
            }
            $grouped[$api]++;
        }

        return $grouped;
    }

    /**
     * Summarize result data for logging
     *
     * @param array $result Result data
     * @return array Summarized result
     */
    private function summarizeResult(array $result) {
        $summary = [];

        foreach ($result as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = count($value) . ' items';
            } elseif (is_string($value) && strlen($value) > 100) {
                $summary[$key] = substr($value, 0, 100) . '...';
            } else {
                $summary[$key] = $value;
            }
        }

        return $summary;
    }

    /**
     * Sanitize data for logging (remove sensitive info)
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private function sanitizeForLogging(array $data) {
        $sanitized = $data;
        $sensitiveKeys = ['api_key', 'password', 'token', 'secret', 'authorization'];

        foreach ($sanitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Get all phases
     *
     * @return array Phases data
     */
    public function getPhases() {
        return $this->phases;
    }

    /**
     * Get all API calls
     *
     * @return array API calls
     */
    public function getApiCalls() {
        return $this->apiCalls;
    }

    /**
     * Get all errors
     *
     * @return array Errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get all warnings
     *
     * @return array Warnings
     */
    public function getWarnings() {
        return $this->warnings;
    }

    /**
     * Get total execution time
     *
     * @return float Duration in seconds
     */
    public function getTotalExecutionTime() {
        return microtime(true) - $this->startTime;
    }

    /**
     * Get total cost
     *
     * @return float Total cost in USD
     */
    public function getTotalCost() {
        $total = 0;

        foreach ($this->apiCalls as $call) {
            if (isset($call['cost_usd'])) {
                $total += $call['cost_usd'];
            }
        }

        return round($total, 2);
    }
}
