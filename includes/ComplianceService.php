<?php
/**
 * Compliance Service
 * Handles GDPR/LGPD compliance operations including data export, deletion, and retention
 */

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/PIIRedactor.php';

class ComplianceService {
    private $db;
    private $tenantId;
    private $piiRedactor;
    
    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
        $this->piiRedactor = new PIIRedactor();
    }
    
    /**
     * Export all data for a user (GDPR Art. 15, LGPD Art. 18)
     * 
     * @param string $externalUserId User identifier (phone, email, etc.)
     * @param string $format Export format (json, csv)
     * @return array User data export
     */
    public function exportUserData($externalUserId, $format = 'json') {
        $export = [
            'request_date' => date('Y-m-d H:i:s'),
            'user_id' => $externalUserId,
            'tenant_id' => $this->tenantId,
            'data' => []
        ];
        
        // 1. Consent records
        $consents = $this->db->query(
            "SELECT * FROM user_consents 
             WHERE external_user_id = ? AND tenant_id = ? 
             ORDER BY created_at DESC",
            [$externalUserId, $this->tenantId]
        );
        $export['data']['consents'] = $consents;
        
        // 2. Consent audit history
        if (!empty($consents)) {
            $consentIds = array_column($consents, 'id');
            $placeholders = str_repeat('?,', count($consentIds) - 1) . '?';
            $auditLog = $this->db->query(
                "SELECT * FROM consent_audit_log 
                 WHERE consent_id IN ($placeholders) 
                 ORDER BY changed_at DESC",
                $consentIds
            );
            $export['data']['consent_history'] = $auditLog;
        }
        
        // 3. Channel sessions
        $sessions = $this->db->query(
            "SELECT * FROM channel_sessions 
             WHERE external_user_id = ? AND tenant_id = ? 
             ORDER BY created_at DESC",
            [$externalUserId, $this->tenantId]
        );
        $export['data']['sessions'] = $sessions;
        
        // 4. Channel messages
        $messages = $this->db->query(
            "SELECT cm.* FROM channel_messages cm
             JOIN channel_sessions cs ON cm.session_id = cs.id
             WHERE cs.external_user_id = ? AND cm.tenant_id = ? 
             ORDER BY cm.created_at DESC",
            [$externalUserId, $this->tenantId]
        );
        $export['data']['messages'] = $messages;
        
        // 5. Conversations (if stored separately)
        $conversations = $this->db->query(
            "SELECT * FROM audit_conversations 
             WHERE external_user_id = ? AND tenant_id = ? 
             ORDER BY created_at DESC",
            [$externalUserId, $this->tenantId]
        );
        $export['data']['conversations'] = $conversations;
        
        // 6. Usage events (anonymized, for transparency)
        $usageEvents = $this->db->query(
            "SELECT event_type, units, cost, created_at 
             FROM usage_logs 
             WHERE tenant_id = ? AND metadata LIKE ? 
             ORDER BY created_at DESC 
             LIMIT 1000",
            [$this->tenantId, '%' . $externalUserId . '%']
        );
        $export['data']['usage_events'] = $usageEvents;
        
        // 7. Leads (if applicable)
        $leads = $this->db->query(
            "SELECT * FROM leads 
             WHERE phone = ? AND tenant_id = ? 
             ORDER BY created_at DESC",
            [$externalUserId, $this->tenantId]
        );
        $export['data']['leads'] = $leads;
        
        // Convert to requested format
        if ($format === 'csv') {
            return $this->convertToCSV($export);
        }
        
        return $export;
    }
    
    /**
     * Delete all user data (GDPR Art. 17, LGPD Art. 18)
     * Right to Erasure / Right to be Forgotten
     * 
     * @param string $externalUserId User identifier
     * @param bool $softDelete If true, mark as deleted but keep for audit
     * @return array Deletion summary
     */
    public function deleteUserData($externalUserId, $softDelete = false) {
        $summary = [
            'user_id' => $externalUserId,
            'tenant_id' => $this->tenantId,
            'deletion_date' => date('Y-m-d H:i:s'),
            'soft_delete' => $softDelete,
            'records_deleted' => []
        ];
        
        $this->db->beginTransaction();
        
        try {
            // 1. Handle consent records
            if ($softDelete) {
                // Mark as deleted but preserve for audit
                $result = $this->db->execute(
                    "UPDATE user_consents 
                     SET status = 'deleted', updated_at = NOW() 
                     WHERE external_user_id = ? AND tenant_id = ?",
                    [$externalUserId, $this->tenantId]
                );
                $summary['records_deleted']['consents_marked'] = $result;
            } else {
                // Hard delete (preserves audit log due to FK cascade)
                $count = $this->db->query(
                    "SELECT COUNT(*) as c FROM user_consents 
                     WHERE external_user_id = ? AND tenant_id = ?",
                    [$externalUserId, $this->tenantId]
                )[0]['c'];
                
                $this->db->execute(
                    "DELETE FROM user_consents 
                     WHERE external_user_id = ? AND tenant_id = ?",
                    [$externalUserId, $this->tenantId]
                );
                $summary['records_deleted']['consents'] = $count;
            }
            
            // 2. Delete channel messages
            $msgCount = $this->db->query(
                "SELECT COUNT(*) as c FROM channel_messages cm
                 JOIN channel_sessions cs ON cm.session_id = cs.id
                 WHERE cs.external_user_id = ? AND cm.tenant_id = ?",
                [$externalUserId, $this->tenantId]
            )[0]['c'];
            
            $this->db->execute(
                "DELETE cm FROM channel_messages cm
                 JOIN channel_sessions cs ON cm.session_id = cs.id
                 WHERE cs.external_user_id = ? AND cm.tenant_id = ?",
                [$externalUserId, $this->tenantId]
            );
            $summary['records_deleted']['messages'] = $msgCount;
            
            // 3. Delete channel sessions
            $sessCount = $this->db->query(
                "SELECT COUNT(*) as c FROM channel_sessions 
                 WHERE external_user_id = ? AND tenant_id = ?",
                [$externalUserId, $this->tenantId]
            )[0]['c'];
            
            $this->db->execute(
                "DELETE FROM channel_sessions 
                 WHERE external_user_id = ? AND tenant_id = ?",
                [$externalUserId, $this->tenantId]
            );
            $summary['records_deleted']['sessions'] = $sessCount;
            
            // 4. Delete conversations
            $convCount = $this->db->query(
                "SELECT COUNT(*) as c FROM audit_conversations 
                 WHERE external_user_id = ? AND tenant_id = ?",
                [$externalUserId, $this->tenantId]
            )[0]['c'];
            
            $this->db->execute(
                "DELETE FROM audit_conversations 
                 WHERE external_user_id = ? AND tenant_id = ?",
                [$externalUserId, $this->tenantId]
            );
            $summary['records_deleted']['conversations'] = $convCount;
            
            // 5. Delete leads
            $leadCount = $this->db->query(
                "SELECT COUNT(*) as c FROM leads 
                 WHERE phone = ? AND tenant_id = ?",
                [$externalUserId, $this->tenantId]
            )[0]['c'];
            
            $this->db->execute(
                "DELETE FROM leads 
                 WHERE phone = ? AND tenant_id = ?",
                [$externalUserId, $this->tenantId]
            );
            $summary['records_deleted']['leads'] = $leadCount;
            
            // 6. Anonymize usage logs (can't delete for billing purposes)
            $usageCount = $this->db->execute(
                "UPDATE usage_logs 
                 SET metadata = JSON_SET(
                     metadata, 
                     '$.user_id', 'REDACTED',
                     '$.phone', 'REDACTED'
                 )
                 WHERE tenant_id = ? AND (
                     metadata LIKE ? OR 
                     metadata LIKE ?
                 )",
                [
                    $this->tenantId,
                    '%' . $externalUserId . '%',
                    '%phone":"' . $externalUserId . '%'
                ]
            );
            $summary['records_deleted']['usage_logs_anonymized'] = $usageCount;
            
            // 7. Log deletion in audit trail
            $this->db->insert(
                "INSERT INTO audit_events 
                 (tenant_id, event_type, resource_type, resource_id, details, ip_address, created_at) 
                 VALUES (?, 'data_deletion', 'user_data', ?, ?, ?, NOW())",
                [
                    $this->tenantId,
                    $externalUserId,
                    json_encode(['summary' => $summary, 'soft_delete' => $softDelete]),
                    $_SERVER['REMOTE_ADDR'] ?? 'system'
                ]
            );
            
            $this->db->commit();
            $summary['status'] = 'completed';
            
        } catch (Exception $e) {
            $this->db->rollback();
            $summary['status'] = 'failed';
            $summary['error'] = $e->getMessage();
            throw $e;
        }
        
        return $summary;
    }
    
    /**
     * Apply retention policy - delete old data
     * 
     * @param int $conversationDays Delete conversations older than X days
     * @param int $auditDays Delete audit logs older than X days
     * @param int $usageDays Delete usage logs older than X days
     * @return array Cleanup summary
     */
    public function applyRetentionPolicy($conversationDays = 180, $auditDays = 365, $usageDays = 730) {
        $summary = [
            'execution_date' => date('Y-m-d H:i:s'),
            'tenant_id' => $this->tenantId,
            'records_deleted' => []
        ];
        
        $this->db->beginTransaction();
        
        try {
            // 1. Delete old conversations
            $conversationCutoff = date('Y-m-d H:i:s', strtotime("-{$conversationDays} days"));
            $convResult = $this->db->execute(
                "DELETE FROM audit_conversations 
                 WHERE tenant_id = ? AND created_at < ?",
                [$this->tenantId, $conversationCutoff]
            );
            $summary['records_deleted']['conversations'] = $convResult;
            
            // 2. Delete old channel messages
            $msgResult = $this->db->execute(
                "DELETE cm FROM channel_messages cm
                 WHERE cm.tenant_id = ? AND cm.created_at < ?",
                [$this->tenantId, $conversationCutoff]
            );
            $summary['records_deleted']['messages'] = $msgResult;
            
            // 3. Delete old audit events (except data_deletion events)
            $auditCutoff = date('Y-m-d H:i:s', strtotime("-{$auditDays} days"));
            $auditResult = $this->db->execute(
                "DELETE FROM audit_events 
                 WHERE tenant_id = ? AND created_at < ? AND event_type != 'data_deletion'",
                [$this->tenantId, $auditCutoff]
            );
            $summary['records_deleted']['audit_events'] = $auditResult;
            
            // 4. Aggregate old usage logs
            $usageCutoff = date('Y-m-d H:i:s', strtotime("-{$usageDays} days"));
            
            // First, create aggregated summary
            $oldUsage = $this->db->query(
                "SELECT 
                    event_type, 
                    DATE(created_at) as date,
                    COUNT(*) as count,
                    SUM(units) as total_units,
                    SUM(cost) as total_cost
                 FROM usage_logs
                 WHERE tenant_id = ? AND created_at < ?
                 GROUP BY event_type, DATE(created_at)",
                [$this->tenantId, $usageCutoff]
            );
            
            // Store aggregated data
            foreach ($oldUsage as $agg) {
                $this->db->insert(
                    "INSERT INTO usage_aggregates 
                     (tenant_id, event_type, date, count, total_units, total_cost, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                     count = VALUES(count),
                     total_units = VALUES(total_units),
                     total_cost = VALUES(total_cost)",
                    [
                        $this->tenantId,
                        $agg['event_type'],
                        $agg['date'],
                        $agg['count'],
                        $agg['total_units'],
                        $agg['total_cost']
                    ]
                );
            }
            
            // Then delete old detailed logs
            $usageResult = $this->db->execute(
                "DELETE FROM usage_logs 
                 WHERE tenant_id = ? AND created_at < ?",
                [$this->tenantId, $usageCutoff]
            );
            $summary['records_deleted']['usage_logs'] = $usageResult;
            $summary['aggregated'] = count($oldUsage);
            
            // 5. Delete expired consent records
            $expiredConsent = $this->db->execute(
                "DELETE FROM user_consents 
                 WHERE tenant_id = ? 
                 AND expires_at IS NOT NULL 
                 AND expires_at < NOW()
                 AND status != 'deleted'",
                [$this->tenantId]
            );
            $summary['records_deleted']['expired_consents'] = $expiredConsent;
            
            $this->db->commit();
            $summary['status'] = 'completed';
            
        } catch (Exception $e) {
            $this->db->rollback();
            $summary['status'] = 'failed';
            $summary['error'] = $e->getMessage();
            throw $e;
        }
        
        return $summary;
    }
    
    /**
     * Generate compliance report
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Compliance report
     */
    public function generateComplianceReport($startDate, $endDate) {
        $report = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'tenant_id' => $this->tenantId,
            'generated_at' => date('Y-m-d H:i:s'),
            'metrics' => []
        ];
        
        // 1. Consent statistics
        $consentStats = $this->db->query(
            "SELECT 
                status,
                COUNT(*) as count
             FROM user_consents
             WHERE tenant_id = ? 
             AND created_at BETWEEN ? AND ?
             GROUP BY status",
            [$this->tenantId, $startDate, $endDate]
        );
        $report['metrics']['consent_status'] = $consentStats;
        
        // 2. Data deletion requests
        $deletionRequests = $this->db->query(
            "SELECT COUNT(*) as count
             FROM audit_events
             WHERE tenant_id = ? 
             AND event_type = 'data_deletion'
             AND created_at BETWEEN ? AND ?",
            [$this->tenantId, $startDate, $endDate]
        )[0]['count'];
        $report['metrics']['deletion_requests'] = $deletionRequests;
        
        // 3. Data export requests
        $exportRequests = $this->db->query(
            "SELECT COUNT(*) as count
             FROM audit_events
             WHERE tenant_id = ? 
             AND event_type = 'data_export'
             AND created_at BETWEEN ? AND ?",
            [$this->tenantId, $startDate, $endDate]
        )[0]['count'];
        $report['metrics']['export_requests'] = $exportRequests;
        
        // 4. Active users with consent
        $activeUsers = $this->db->query(
            "SELECT COUNT(DISTINCT external_user_id) as count
             FROM user_consents
             WHERE tenant_id = ? 
             AND status = 'granted'",
            [$this->tenantId]
        )[0]['count'];
        $report['metrics']['active_consented_users'] = $activeUsers;
        
        // 5. PII redaction status
        $tenantInfo = $this->db->getOne(
            "SELECT pii_redaction_enabled FROM tenants WHERE id = ?",
            [$this->tenantId]
        );
        $report['metrics']['pii_redaction_enabled'] = (bool)($tenantInfo['pii_redaction_enabled'] ?? false);
        
        return $report;
    }
    
    /**
     * Convert export data to CSV format
     * 
     * @param array $export Export data
     * @return string CSV content
     */
    private function convertToCSV($export) {
        $csv = [];
        
        foreach ($export['data'] as $type => $records) {
            if (empty($records)) continue;
            
            $csv[] = "=== $type ===";
            
            if (!empty($records)) {
                // Header row
                $csv[] = implode(',', array_keys($records[0]));
                
                // Data rows
                foreach ($records as $record) {
                    $row = array_map(function($val) {
                        return '"' . str_replace('"', '""', (string)$val) . '"';
                    }, array_values($record));
                    $csv[] = implode(',', $row);
                }
            }
            
            $csv[] = ""; // Empty line between sections
        }
        
        return implode("\n", $csv);
    }
    
    /**
     * Enable PII redaction for tenant
     * 
     * @param bool $enabled
     * @return bool Success
     */
    public function setPIIRedactionEnabled($enabled) {
        $result = $this->db->execute(
            "UPDATE tenants SET pii_redaction_enabled = ? WHERE id = ?",
            [$enabled ? 1 : 0, $this->tenantId]
        );
        
        return $result > 0;
    }
    
    /**
     * Check if PII redaction is enabled for tenant
     * 
     * @return bool
     */
    public function isPIIRedactionEnabled() {
        $result = $this->db->getOne(
            "SELECT pii_redaction_enabled FROM tenants WHERE id = ?",
            [$this->tenantId]
        );
        
        return (bool)($result['pii_redaction_enabled'] ?? false);
    }
}
