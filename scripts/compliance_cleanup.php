#!/usr/bin/env php
<?php
/**
 * Compliance Cleanup Script
 * 
 * Automated data retention and deletion for GDPR/LGPD compliance
 * - Deletes expired conversation messages
 * - Removes old session data
 * - Cleans up expired consents
 * - Maintains audit logs
 * - Respects legal holds
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

// Parse arguments
$options = getopt('', ['days:', 'dry-run', 'verbose', 'tenant:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Compliance Cleanup Script - Data Retention Automation

Usage: php compliance_cleanup.php [options]

Options:
  --days=<days>        Retention period in days (default: 90)
  --tenant=<id>        Limit to specific tenant ID
  --dry-run            Show what would be deleted without actually deleting
  --verbose            Show detailed output
  --help               Show this help message

Examples:
  # Dry run with 90 day retention
  php compliance_cleanup.php --days=90 --dry-run --verbose

  # Execute cleanup for specific tenant
  php compliance_cleanup.php --days=90 --tenant=tenant-uuid

  # Execute cleanup with verbose output
  php compliance_cleanup.php --days=90 --verbose

HELP;
    exit(0);
}

$retentionDays = isset($options['days']) ? (int)$options['days'] : 90;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$tenantId = $options['tenant'] ?? null;

// Initialize database
$db = new DB($config['storage']);

// Helper functions
function log_message($message, $level = 'INFO') {
    global $verbose;
    if ($verbose || $level === 'ERROR') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp][$level] $message\n";
    }
}

function format_number($number) {
    return number_format($number);
}

// Main cleanup function
function runCleanup($db, $retentionDays, $dryRun, $tenantId) {
    $cutoffDate = date('Y-m-d\TH:i:s\Z', strtotime("-$retentionDays days"));
    $stats = [
        'messages' => 0,
        'sessions' => 0,
        'consents' => 0,
        'audit_events' => 0,
        'skipped_legal_hold' => 0
    ];
    
    log_message("Starting compliance cleanup");
    log_message("Retention period: $retentionDays days");
    log_message("Cutoff date: $cutoffDate");
    log_message("Dry run: " . ($dryRun ? 'YES' : 'NO'));
    if ($tenantId) {
        log_message("Tenant filter: $tenantId");
    }
    log_message(str_repeat('-', 60));
    
    // Step 1: Clean up channel messages
    log_message("Step 1: Cleaning up channel messages...");
    $stats['messages'] = cleanupMessages($db, $cutoffDate, $dryRun, $tenantId);
    log_message("  → " . format_number($stats['messages']) . " messages " . ($dryRun ? 'would be' : '') . " deleted");
    
    // Step 2: Clean up inactive sessions
    log_message("Step 2: Cleaning up inactive sessions...");
    $stats['sessions'] = cleanupSessions($db, $cutoffDate, $dryRun, $tenantId);
    log_message("  → " . format_number($stats['sessions']) . " sessions " . ($dryRun ? 'would be' : '') . " deleted");
    
    // Step 3: Clean up expired consents
    log_message("Step 3: Cleaning up expired consents...");
    $stats['consents'] = cleanupExpiredConsents($db, $dryRun, $tenantId);
    log_message("  → " . format_number($stats['consents']) . " consents " . ($dryRun ? 'would be' : '') . " deleted");
    
    // Step 4: Clean up old audit events (keep only last 12 months)
    $auditRetentionDays = 365;
    log_message("Step 4: Cleaning up audit events (retention: $auditRetentionDays days)...");
    $stats['audit_events'] = cleanupAuditEvents($db, $auditRetentionDays, $dryRun);
    log_message("  → " . format_number($stats['audit_events']) . " audit events " . ($dryRun ? 'would be' : '') . " deleted");
    
    // Step 5: Check for legal holds that prevented deletion
    log_message("Step 5: Checking legal holds...");
    $stats['skipped_legal_hold'] = countLegalHolds($db, $tenantId);
    if ($stats['skipped_legal_hold'] > 0) {
        log_message("  ⚠ " . format_number($stats['skipped_legal_hold']) . " records under legal hold (skipped)", 'WARN');
    } else {
        log_message("  ✓ No records under legal hold");
    }
    
    log_message(str_repeat('-', 60));
    log_message("Cleanup " . ($dryRun ? 'simulation' : '') . " complete!");
    log_message("Summary:");
    log_message("  Messages: " . format_number($stats['messages']));
    log_message("  Sessions: " . format_number($stats['sessions']));
    log_message("  Consents: " . format_number($stats['consents']));
    log_message("  Audit Events: " . format_number($stats['audit_events']));
    log_message("  Legal Hold: " . format_number($stats['skipped_legal_hold']));
    
    return $stats;
}

function cleanupMessages($db, $cutoffDate, $dryRun, $tenantId) {
    $sql = "SELECT COUNT(*) as count FROM channel_messages WHERE created_at < ?";
    $params = [$cutoffDate];
    
    if ($tenantId) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenantId;
    }
    
    // Exclude messages under legal hold
    $sql .= " AND (metadata_json IS NULL OR metadata_json NOT LIKE '%\"legal_hold\":true%')";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if (!$dryRun && $count > 0) {
        $deleteSql = str_replace("SELECT COUNT(*) as count FROM", "DELETE FROM", $sql);
        $stmt = $db->prepare($deleteSql);
        $stmt->execute($params);
    }
    
    return $count;
}

function cleanupSessions($db, $cutoffDate, $dryRun, $tenantId) {
    // Delete sessions with no activity in retention period
    $sql = "SELECT COUNT(*) as count FROM channel_sessions WHERE last_seen_at < ?";
    $params = [$cutoffDate];
    
    if ($tenantId) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenantId;
    }
    
    // Exclude sessions under legal hold
    $sql .= " AND (metadata_json IS NULL OR metadata_json NOT LIKE '%\"legal_hold\":true%')";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if (!$dryRun && $count > 0) {
        $deleteSql = str_replace("SELECT COUNT(*) as count FROM", "DELETE FROM", $sql);
        $stmt = $db->prepare($deleteSql);
        $stmt->execute($params);
    }
    
    return $count;
}

function cleanupExpiredConsents($db, $dryRun, $tenantId) {
    // Delete consents that are expired and withdrawn
    $now = gmdate('Y-m-d\TH:i:s\Z');
    
    $sql = "
        SELECT COUNT(*) as count FROM user_consents 
        WHERE expires_at IS NOT NULL 
        AND expires_at < ? 
        AND consent_status = 'withdrawn'
    ";
    $params = [$now];
    
    if ($tenantId) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenantId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if (!$dryRun && $count > 0) {
        $deleteSql = str_replace("SELECT COUNT(*) as count FROM", "DELETE FROM", $sql);
        $stmt = $db->prepare($deleteSql);
        $stmt->execute($params);
    }
    
    return $count;
}

function cleanupAuditEvents($db, $retentionDays, $dryRun) {
    // Clean up old audit conversation and message events
    // Keep consent audit logs for 3 years (legal requirement)
    $cutoffDate = date('Y-m-d\TH:i:s\Z', strtotime("-$retentionDays days"));
    
    $tables = ['audit_events', 'audit_messages'];
    $totalCount = 0;
    
    foreach ($tables as $table) {
        $sql = "SELECT COUNT(*) as count FROM $table WHERE created_at < ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$cutoffDate]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $totalCount += $count;
        
        if (!$dryRun && $count > 0) {
            $deleteSql = "DELETE FROM $table WHERE created_at < ?";
            $stmt = $db->prepare($deleteSql);
            $stmt->execute([$cutoffDate]);
        }
    }
    
    return $totalCount;
}

function countLegalHolds($db, $tenantId) {
    // Count records under legal hold
    $count = 0;
    
    // Check channel_messages
    $sql = "SELECT COUNT(*) as count FROM channel_messages WHERE metadata_json LIKE '%\"legal_hold\":true%'";
    $params = [];
    
    if ($tenantId) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenantId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count += $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Check channel_sessions
    $sql = "SELECT COUNT(*) as count FROM channel_sessions WHERE metadata_json LIKE '%\"legal_hold\":true%'";
    $params = [];
    
    if ($tenantId) {
        $sql .= " AND tenant_id = ?";
        $params[] = $tenantId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count += $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return $count;
}

// Log to file
function logToFile($message, $stats = null) {
    global $config;
    
    $logDir = $config['logging']['directory'] ?? __DIR__ . '/../logs';
    $logFile = "$logDir/compliance_cleanup.log";
    
    @mkdir($logDir, 0755, true);
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    
    if ($stats) {
        $logEntry .= "  Stats: " . json_encode($stats) . "\n";
    }
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Run cleanup
try {
    $stats = runCleanup($db, $retentionDays, $dryRun, $tenantId);
    
    $summary = $dryRun ? "Dry run completed" : "Cleanup completed successfully";
    logToFile($summary, $stats);
    
    exit(0);
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage(), 'ERROR');
    logToFile("Cleanup failed: " . $e->getMessage());
    exit(1);
}
