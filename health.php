<?php
/**
 * Enhanced Health Check Endpoint
 * 
 * Provides detailed health status for:
 * - Database connectivity
 * - OpenAI API connectivity
 * - Worker status
 * - Disk space
 * - Memory usage
 * - Queue health
 * 
 * Returns JSON with overall status and detailed component checks
 */

require_once 'config.php';
require_once 'includes/DB.php';
require_once 'includes/JobQueue.php';

header('Content-Type: application/json');

// Initialize response
$health = [
    'status' => 'healthy',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'version' => '2.1.0',
    'checks' => []
];

$overallHealthy = true;

// Check: Database connectivity
try {
    $db = new DB($config);
    $db->query("SELECT 1")->fetch();
    
    $health['checks']['database'] = [
        'status' => 'healthy',
        'response_time_ms' => 0,
        'type' => $config['admin']['database_type'] ?? 'sqlite'
    ];
} catch (Exception $e) {
    $overallHealthy = false;
    $health['checks']['database'] = [
        'status' => 'unhealthy',
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ];
}

// Check: OpenAI API connectivity
$openaiHealthy = false;
$openaiStart = microtime(true);
try {
    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . ($config['openai']['api_key'] ?? ''),
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseTime = (microtime(true) - $openaiStart) * 1000;
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 401) {
        // 401 means API key issue but API is reachable
        $openaiHealthy = true;
        $health['checks']['openai_api'] = [
            'status' => $httpCode === 200 ? 'healthy' : 'degraded',
            'response_time_ms' => round($responseTime, 2),
            'http_code' => $httpCode
        ];
    } else {
        $health['checks']['openai_api'] = [
            'status' => 'unhealthy',
            'response_time_ms' => round($responseTime, 2),
            'http_code' => $httpCode
        ];
        $overallHealthy = false;
    }
} catch (Exception $e) {
    $health['checks']['openai_api'] = [
        'status' => 'unhealthy',
        'error' => 'Failed to connect to OpenAI API',
        'details' => $e->getMessage()
    ];
    $overallHealthy = false;
}

// Check: Worker status
try {
    if (isset($db)) {
        $stmt = $db->query("
            SELECT MAX(updated_at) as last_update 
            FROM jobs 
            WHERE status IN ('running', 'completed', 'failed')
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['last_update']) {
            $lastUpdate = strtotime($row['last_update']);
            $secondsSinceLastJob = time() - $lastUpdate;
            
            if ($secondsSinceLastJob < 300) {
                $health['checks']['worker'] = [
                    'status' => 'healthy',
                    'last_job_seconds_ago' => $secondsSinceLastJob
                ];
            } else {
                $health['checks']['worker'] = [
                    'status' => 'stale',
                    'last_job_seconds_ago' => $secondsSinceLastJob,
                    'warning' => 'Worker has not processed jobs recently'
                ];
            }
        } else {
            $health['checks']['worker'] = [
                'status' => 'unknown',
                'message' => 'No job history found'
            ];
        }
    }
} catch (Exception $e) {
    $health['checks']['worker'] = [
        'status' => 'unknown',
        'error' => 'Failed to check worker status'
    ];
}

// Check: Queue depth
try {
    if (isset($db)) {
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM jobs 
            WHERE status = 'pending'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $queueDepth = (int)$row['count'];
        
        if ($queueDepth < 100) {
            $status = 'healthy';
        } elseif ($queueDepth < 500) {
            $status = 'warning';
        } else {
            $status = 'critical';
            $overallHealthy = false;
        }
        
        $health['checks']['queue'] = [
            'status' => $status,
            'pending_jobs' => $queueDepth
        ];
    }
} catch (Exception $e) {
    $health['checks']['queue'] = [
        'status' => 'unknown',
        'error' => 'Failed to check queue depth'
    ];
}

// Check: Disk space
try {
    $dataPath = __DIR__ . '/data';
    if (is_dir($dataPath)) {
        $diskFree = disk_free_space($dataPath);
        $diskTotal = disk_total_space($dataPath);
        $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
        
        if ($diskUsedPercent < 80) {
            $status = 'healthy';
        } elseif ($diskUsedPercent < 90) {
            $status = 'warning';
        } else {
            $status = 'critical';
            $overallHealthy = false;
        }
        
        $health['checks']['disk_space'] = [
            'status' => $status,
            'free_bytes' => $diskFree,
            'total_bytes' => $diskTotal,
            'used_percent' => round($diskUsedPercent, 2)
        ];
    }
} catch (Exception $e) {
    $health['checks']['disk_space'] = [
        'status' => 'unknown',
        'error' => 'Failed to check disk space'
    ];
}

// Check: Memory usage
try {
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    // Convert memory limit to bytes
    if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
        $memoryLimitBytes = (int)$matches[1];
        switch (strtoupper($matches[2])) {
            case 'G':
                $memoryLimitBytes *= 1024 * 1024 * 1024;
                break;
            case 'M':
                $memoryLimitBytes *= 1024 * 1024;
                break;
            case 'K':
                $memoryLimitBytes *= 1024;
                break;
        }
    } else {
        $memoryLimitBytes = (int)$memoryLimit;
    }
    
    $memoryUsedPercent = ($memoryUsage / $memoryLimitBytes) * 100;
    
    if ($memoryUsedPercent < 70) {
        $status = 'healthy';
    } elseif ($memoryUsedPercent < 85) {
        $status = 'warning';
    } else {
        $status = 'critical';
    }
    
    $health['checks']['memory'] = [
        'status' => $status,
        'used_bytes' => $memoryUsage,
        'limit_bytes' => $memoryLimitBytes,
        'used_percent' => round($memoryUsedPercent, 2)
    ];
} catch (Exception $e) {
    $health['checks']['memory'] = [
        'status' => 'unknown',
        'error' => 'Failed to check memory usage'
    ];
}

// Set overall status
if (!$overallHealthy) {
    $health['status'] = 'unhealthy';
    http_response_code(503);
} elseif (isset($health['checks']['worker']['status']) && $health['checks']['worker']['status'] === 'stale') {
    $health['status'] = 'degraded';
    http_response_code(200);
}

echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
