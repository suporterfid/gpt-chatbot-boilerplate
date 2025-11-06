#!/usr/bin/env php
<?php
/**
 * Monitoring Check Script
 * 
 * Performs health checks and sends alerts if issues are detected.
 * Can be run from cron for continuous monitoring.
 * 
 * Usage: php scripts/monitoring_check.php [--quiet]
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/JobQueue.php';
require_once __DIR__ . '/../includes/ObservabilityLogger.php';
require_once __DIR__ . '/../includes/AlertManager.php';

$quiet = in_array('--quiet', $argv);

// Initialize services
$logger = new ObservabilityLogger($config);
$alertManager = new AlertManager($config, $logger);

function log_message($message, $quiet = false) {
    if (!$quiet) {
        echo "[" . date('Y-m-d H:i:s') . "] $message\n";
    }
}

log_message("Starting monitoring checks...", $quiet);

try {
    $db = new DB($config);
    $jobQueue = new JobQueue($db);
} catch (Exception $e) {
    log_message("CRITICAL: Failed to initialize database: " . $e->getMessage(), $quiet);
    $alertManager->sendAlert(
        'Database Connection Failed',
        'Unable to connect to database: ' . $e->getMessage(),
        AlertManager::SEVERITY_CRITICAL,
        ['error' => $e->getMessage()]
    );
    exit(1);
}

// Check 1: Worker Health
log_message("Checking worker health...", $quiet);
try {
    $stmt = $db->query("
        SELECT MAX(updated_at) as last_update 
        FROM jobs 
        WHERE status IN ('running', 'completed', 'failed')
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['last_update']) {
        $lastUpdate = strtotime($row['last_update']);
        $secondsSinceLastJob = time() - $lastUpdate;
        
        if ($secondsSinceLastJob > 300) {
            log_message("WARNING: Worker appears down (last job: {$secondsSinceLastJob}s ago)", $quiet);
            $alertManager->alertWorkerDown($secondsSinceLastJob);
        } else {
            log_message("✓ Worker healthy (last job: {$secondsSinceLastJob}s ago)", $quiet);
        }
    } else {
        log_message("INFO: No job history found", $quiet);
    }
} catch (Exception $e) {
    log_message("ERROR: Failed to check worker health: " . $e->getMessage(), $quiet);
}

// Check 2: Queue Depth
log_message("Checking queue depth...", $quiet);
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'pending'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $queueDepth = (int)$row['count'];
    
    $warningThreshold = 100;
    $criticalThreshold = 500;
    
    if ($queueDepth > $criticalThreshold) {
        log_message("CRITICAL: Queue depth is {$queueDepth} (threshold: {$criticalThreshold})", $quiet);
        $alertManager->alertQueueDepth($queueDepth, $criticalThreshold);
    } elseif ($queueDepth > $warningThreshold) {
        log_message("WARNING: Queue depth is {$queueDepth} (threshold: {$warningThreshold})", $quiet);
        $alertManager->alertQueueDepth($queueDepth, $warningThreshold);
    } else {
        log_message("✓ Queue depth healthy: {$queueDepth} jobs", $quiet);
    }
} catch (Exception $e) {
    log_message("ERROR: Failed to check queue depth: " . $e->getMessage(), $quiet);
}

// Check 3: Job Failure Rate (last hour)
log_message("Checking job failure rate...", $quiet);
try {
    $stmt = $db->query("
        SELECT 
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
        FROM jobs
        WHERE updated_at >= datetime('now', '-1 hour')
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $completed = (int)$row['completed'];
    $failed = (int)$row['failed'];
    $total = $completed + $failed;
    
    if ($total > 0) {
        $failureRate = ($failed / $total) * 100;
        
        if ($failureRate > 50) {
            log_message("CRITICAL: Job failure rate is {$failureRate}%", $quiet);
            $alertManager->alertHighErrorRate($failureRate, 'job_queue');
        } elseif ($failureRate > 10) {
            log_message("WARNING: Job failure rate is {$failureRate}%", $quiet);
            $alertManager->alertHighErrorRate($failureRate, 'job_queue');
        } else {
            log_message("✓ Job failure rate healthy: {$failureRate}%", $quiet);
        }
    } else {
        log_message("INFO: No jobs processed in last hour", $quiet);
    }
} catch (Exception $e) {
    log_message("ERROR: Failed to check job failure rate: " . $e->getMessage(), $quiet);
}

// Check 4: Disk Space
log_message("Checking disk space...", $quiet);
try {
    $dataPath = __DIR__ . '/../data';
    if (is_dir($dataPath)) {
        $diskFree = disk_free_space($dataPath);
        $diskTotal = disk_total_space($dataPath);
        $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
        
        if ($diskUsedPercent > 95) {
            log_message("CRITICAL: Disk usage at {$diskUsedPercent}%", $quiet);
            $alertManager->alertDiskSpace($diskUsedPercent, $diskFree);
        } elseif ($diskUsedPercent > 80) {
            log_message("WARNING: Disk usage at {$diskUsedPercent}%", $quiet);
            $alertManager->alertDiskSpace($diskUsedPercent, $diskFree);
        } else {
            log_message("✓ Disk space healthy: {$diskUsedPercent}% used", $quiet);
        }
    }
} catch (Exception $e) {
    log_message("ERROR: Failed to check disk space: " . $e->getMessage(), $quiet);
}

// Check 5: Database Size (SQLite only)
if ($config['admin']['database_type'] === 'sqlite') {
    log_message("Checking database size...", $quiet);
    try {
        $dbPath = $config['admin']['database_path'];
        if (file_exists($dbPath)) {
            $dbSize = filesize($dbPath);
            $dbSizeMB = $dbSize / 1024 / 1024;
            
            // Alert if database is > 1GB
            if ($dbSizeMB > 1024) {
                log_message("WARNING: Database size is {$dbSizeMB} MB", $quiet);
                $alertManager->sendAlert(
                    'Large Database Size',
                    "Database has grown to {$dbSizeMB} MB. Consider archiving old data.",
                    AlertManager::SEVERITY_WARNING,
                    ['size_mb' => $dbSizeMB, 'path' => $dbPath]
                );
            } else {
                log_message("✓ Database size: {$dbSizeMB} MB", $quiet);
            }
        }
    } catch (Exception $e) {
        log_message("ERROR: Failed to check database size: " . $e->getMessage(), $quiet);
    }
}

// Check 6: OpenAI API Connectivity (optional, can be slow)
if (!in_array('--skip-external', $argv)) {
    log_message("Checking OpenAI API connectivity...", $quiet);
    try {
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . ($config['openai']['api_key'] ?? ''),
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 401) {
            log_message("✓ OpenAI API reachable", $quiet);
        } else {
            log_message("WARNING: OpenAI API returned HTTP {$httpCode}", $quiet);
            $alertManager->sendAlert(
                'OpenAI API Connectivity Issue',
                "OpenAI API returned HTTP {$httpCode}",
                AlertManager::SEVERITY_WARNING,
                ['http_code' => $httpCode]
            );
        }
    } catch (Exception $e) {
        log_message("ERROR: Failed to check OpenAI API: " . $e->getMessage(), $quiet);
    }
}

log_message("Monitoring checks completed.", $quiet);
exit(0);
