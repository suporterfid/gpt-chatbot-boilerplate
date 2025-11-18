<?php
/**
 * Metrics Endpoint - Prometheus-compatible metrics
 * Exposes application metrics for monitoring and alerting
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration
require_once 'config.php';

// Keep manual requires temporarily for backward compatibility during migration
require_once 'includes/DB.php';
require_once 'includes/JobQueue.php';
require_once 'includes/MetricsCollector.php';

header('Content-Type: text/plain; version=0.0.4; charset=utf-8');

// Initialize database and job queue
try {
    $db = new DB($config);
    $jobQueue = new JobQueue($db);
    $metricsCollector = MetricsCollector::getInstance($config['observability']['metrics'] ?? []);
} catch (Exception $e) {
    http_response_code(500);
    echo "# ERROR: Failed to initialize metrics\n";
    exit();
}

// Helper function to format Prometheus metric
function promMetric($name, $type, $help, $value, $labels = []) {
    $output = "# HELP $name $help\n";
    $output .= "# TYPE $name $type\n";
    
    $labelStr = '';
    if (!empty($labels)) {
        $labelPairs = [];
        foreach ($labels as $key => $val) {
            // Properly escape label values for Prometheus exposition format
            $escapedVal = str_replace(
                ['\\', "\n", '"'],
                ['\\\\', '\\n', '\\"'],
                (string)$val
            );
            $labelPairs[] = $key . '="' . $escapedVal . '"';
        }
        $labelStr = '{' . implode(',', $labelPairs) . '}';
    }
    
    $output .= "$name$labelStr $value\n";
    return $output;
}

// Metric: Application info
echo promMetric(
    'chatbot_info',
    'gauge',
    'Application information',
    1,
    [
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'db_type' => $config['admin']['database_type'] ?? 'sqlite'
    ]
);

// Metric: Jobs processed total
try {
    $stmt = $db->query("
        SELECT status, COUNT(*) as count 
        FROM jobs 
        GROUP BY status
    ");
    
    $jobCounts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $jobCounts[$row['status']] = (int)$row['count'];
    }
    
    // Jobs by status
    foreach (['pending', 'running', 'completed', 'failed', 'cancelled'] as $status) {
        $count = $jobCounts[$status] ?? 0;
        echo promMetric(
            'chatbot_jobs_total',
            'counter',
            'Total number of jobs by status',
            $count,
            ['status' => $status]
        );
    }
    
    // Total processed jobs
    $totalProcessed = ($jobCounts['completed'] ?? 0) + ($jobCounts['failed'] ?? 0);
    echo promMetric(
        'chatbot_jobs_processed_total',
        'counter',
        'Total number of processed jobs',
        $totalProcessed
    );
    
    // Failed jobs
    echo promMetric(
        'chatbot_jobs_failed_total',
        'counter',
        'Total number of failed jobs',
        $jobCounts['failed'] ?? 0
    );
    
    // Pending queue depth
    echo promMetric(
        'chatbot_jobs_queue_depth',
        'gauge',
        'Number of jobs waiting in queue',
        $jobCounts['pending'] ?? 0
    );
    
} catch (Exception $e) {
    error_log("Metrics: Failed to query jobs: " . $e->getMessage());
}

// Metric: Jobs by type
try {
    $stmt = $db->query("
        SELECT type, status, COUNT(*) as count 
        FROM jobs 
        GROUP BY type, status
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo promMetric(
            'chatbot_jobs_by_type',
            'counter',
            'Jobs by type and status',
            (int)$row['count'],
            [
                'type' => $row['type'],
                'status' => $row['status']
            ]
        );
    }
} catch (Exception $e) {
    error_log("Metrics: Failed to query jobs by type: " . $e->getMessage());
}

// Metric: Admin API requests (from audit log)
try {
    $stmt = $db->query("
        SELECT 
            CASE 
                WHEN action LIKE 'agent.%' THEN 'agent'
                WHEN action LIKE 'prompt.%' THEN 'prompt'
                WHEN action LIKE 'vector_store.%' THEN 'vector_store'
                WHEN action LIKE 'job.%' THEN 'job'
                WHEN action LIKE 'user.%' THEN 'user'
                ELSE 'other'
            END as resource,
            COUNT(*) as count
        FROM audit_log
        GROUP BY resource
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo promMetric(
            'chatbot_admin_api_requests_total',
            'counter',
            'Total admin API requests by resource',
            (int)$row['count'],
            ['resource' => $row['resource']]
        );
    }
} catch (Exception $e) {
    error_log("Metrics: Failed to query audit log: " . $e->getMessage());
}

// Metric: Agents
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM agents");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo promMetric(
        'chatbot_agents_total',
        'gauge',
        'Total number of configured agents',
        (int)$row['count']
    );
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM agents WHERE is_default = 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo promMetric(
        'chatbot_agents_default',
        'gauge',
        'Number of default agents (should be 1)',
        (int)$row['count']
    );
} catch (Exception $e) {
    error_log("Metrics: Failed to query agents: " . $e->getMessage());
}

// Metric: Vector stores
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM vector_stores");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo promMetric(
        'chatbot_vector_stores_total',
        'gauge',
        'Total number of vector stores',
        (int)$row['count']
    );
} catch (Exception $e) {
    error_log("Metrics: Failed to query vector stores: " . $e->getMessage());
}

// Metric: Prompts
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prompts");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo promMetric(
        'chatbot_prompts_total',
        'gauge',
        'Total number of prompts',
        (int)$row['count']
    );
} catch (Exception $e) {
    error_log("Metrics: Failed to query prompts: " . $e->getMessage());
}

// Metric: Admin users
try {
    $stmt = $db->query("
        SELECT role, COUNT(*) as count 
        FROM admin_users 
        WHERE is_active = 1
        GROUP BY role
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo promMetric(
            'chatbot_admin_users_total',
            'gauge',
            'Total admin users by role',
            (int)$row['count'],
            ['role' => $row['role']]
        );
    }
} catch (Exception $e) {
    error_log("Metrics: Failed to query admin users: " . $e->getMessage());
}

// Metric: Webhook events
try {
    $stmt = $db->query("
        SELECT processed, COUNT(*) as count 
        FROM webhook_events 
        GROUP BY processed
    ");
    
    $webhookCounts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['processed'] ? 'processed' : 'pending';
        $webhookCounts[$status] = (int)$row['count'];
    }
    
    echo promMetric(
        'chatbot_webhook_events_total',
        'counter',
        'Total webhook events by status',
        $webhookCounts['processed'] ?? 0,
        ['status' => 'processed']
    );
    
    echo promMetric(
        'chatbot_webhook_events_total',
        'counter',
        'Total webhook events by status',
        $webhookCounts['pending'] ?? 0,
        ['status' => 'pending']
    );
} catch (Exception $e) {
    error_log("Metrics: Failed to query webhook events: " . $e->getMessage());
}

// Metric: Worker health (last seen timestamp)
try {
    // Get the most recent job update to estimate worker liveness
    $stmt = $db->query("
        SELECT MAX(updated_at) as last_update 
        FROM jobs 
        WHERE status IN ('running', 'completed', 'failed')
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['last_update']) {
        $lastUpdate = strtotime($row['last_update']);
        $now = time();
        $secondsSinceLastJob = $now - $lastUpdate;
        
        echo promMetric(
            'chatbot_worker_last_job_seconds',
            'gauge',
            'Seconds since worker last processed a job',
            $secondsSinceLastJob
        );
        
        // Worker health flag (1 = healthy, 0 = stale)
        $isHealthy = ($secondsSinceLastJob < 300) ? 1 : 0; // 5 minutes threshold
        echo promMetric(
            'chatbot_worker_healthy',
            'gauge',
            'Worker health status (1 = healthy, 0 = stale)',
            $isHealthy
        );
    }
} catch (Exception $e) {
    error_log("Metrics: Failed to query worker health: " . $e->getMessage());
}

// Metric: Database size (SQLite only)
if ($config['admin']['database_type'] === 'sqlite') {
    $dbPath = $config['admin']['database_path'];
    if (file_exists($dbPath)) {
        $dbSize = filesize($dbPath);
        echo promMetric(
            'chatbot_database_size_bytes',
            'gauge',
            'Database file size in bytes',
            $dbSize
        );
    }
}

// Export runtime metrics from MetricsCollector
$runtimeMetrics = $metricsCollector->getMetrics();
foreach ($runtimeMetrics as $metric) {
    $metricType = $metric['type'];
    $metricName = $metric['name'];
    $value = $metric['value'] ?? 0;
    $labels = $metric['labels'] ?? [];
    
    // Convert metric type to Prometheus type
    $promType = match($metricType) {
        'counter' => 'counter',
        'gauge' => 'gauge',
        'histogram' => 'summary',
        default => 'gauge',
    };
    
    echo promMetric(
        $metricName,
        $promType,
        "Runtime metric: $metricName",
        $value,
        $labels
    );
}

// Metric: Multi-tenant metrics
try {
    // Jobs by tenant
    $stmt = $db->query("
        SELECT tenant_id, status, COUNT(*) as count 
        FROM jobs 
        WHERE tenant_id IS NOT NULL
        GROUP BY tenant_id, status
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo promMetric(
            'chatbot_tenant_jobs_total',
            'counter',
            'Total jobs by tenant and status',
            (int)$row['count'],
            [
                'tenant_id' => $row['tenant_id'],
                'status' => $row['status']
            ]
        );
    }
} catch (Exception $e) {
    error_log("Metrics: Failed to query tenant jobs: " . $e->getMessage());
}

// Metric: Active tenants
try {
    $stmt = $db->query("
        SELECT COUNT(DISTINCT tenant_id) as count 
        FROM jobs 
        WHERE tenant_id IS NOT NULL
        AND created_at > datetime('now', '-1 day')
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo promMetric(
        'chatbot_active_tenants_24h',
        'gauge',
        'Number of active tenants in last 24 hours',
        (int)$row['count']
    );
} catch (Exception $e) {
    error_log("Metrics: Failed to query active tenants: " . $e->getMessage());
}

// Metric: Tenant conversation metrics (if conversations table exists)
try {
    $stmt = $db->query("
        SELECT tenant_id, COUNT(*) as count 
        FROM conversations 
        WHERE tenant_id IS NOT NULL
        AND created_at > datetime('now', '-1 day')
        GROUP BY tenant_id
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo promMetric(
            'chatbot_tenant_conversations_24h',
            'gauge',
            'Number of conversations per tenant in last 24 hours',
            (int)$row['count'],
            ['tenant_id' => $row['tenant_id']]
        );
    }
} catch (Exception $e) {
    // Table might not exist in all installations
    error_log("Metrics: Could not query tenant conversations: " . $e->getMessage());
}

// Metric: Scrape duration
echo promMetric(
    'chatbot_metrics_scrape_duration_seconds',
    'gauge',
    'Time taken to generate metrics',
    microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
);

exit();
