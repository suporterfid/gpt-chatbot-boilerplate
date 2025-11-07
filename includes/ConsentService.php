<?php
/**
 * ConsentService - Manages user consent for GDPR/LGPD compliance
 * 
 * Handles opt-in/opt-out workflows, consent tracking, and audit logging
 * for WhatsApp and other communication channels.
 */

class ConsentService {
    private $db;
    private $tenantId;

    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }

    /**
     * Grant consent for a user
     */
    public function grantConsent($agentId, $channel, $externalUserId, $options = []) {
        $consentType = $options['consent_type'] ?? 'service';
        $consentMethod = $options['consent_method'] ?? 'first_contact';
        $consentText = $options['consent_text'] ?? null;
        $consentLanguage = $options['consent_language'] ?? 'en';
        $ipAddress = $options['ip_address'] ?? null;
        $userAgent = $options['user_agent'] ?? null;
        $expiresAt = $options['expires_at'] ?? null;
        $legalBasis = $options['legal_basis'] ?? 'consent';
        $metadata = $options['metadata'] ?? [];

        $consentId = $this->generateUuid();
        $now = $this->now();

        // Check if consent already exists
        $existing = $this->getConsent($agentId, $channel, $externalUserId, $consentType);
        
        if ($existing) {
            // Update existing consent
            return $this->updateConsentStatus(
                $existing['id'],
                'granted',
                'renewed',
                'User re-granted consent',
                'user'
            );
        }

        // Insert new consent record
        $stmt = $this->db->prepare("
            INSERT INTO user_consents (
                id, tenant_id, agent_id, channel, external_user_id,
                consent_type, consent_status, consent_method, consent_text,
                consent_language, ip_address, user_agent, granted_at,
                expires_at, legal_basis, metadata_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $consentId,
            $this->tenantId,
            $agentId,
            $channel,
            $externalUserId,
            $consentType,
            'granted',
            $consentMethod,
            $consentText,
            $consentLanguage,
            $ipAddress,
            $userAgent,
            $now,
            $expiresAt,
            $legalBasis,
            json_encode($metadata),
            $now,
            $now
        ]);

        // Log audit entry
        $this->logConsentAudit($consentId, 'granted', null, 'granted', 'Initial consent granted', 'user', $ipAddress, $userAgent);

        return $this->getConsentById($consentId);
    }

    /**
     * Withdraw consent (opt-out)
     */
    public function withdrawConsent($agentId, $channel, $externalUserId, $consentType = 'all', $options = []) {
        $reason = $options['reason'] ?? 'User requested opt-out';
        $triggeredBy = $options['triggered_by'] ?? 'user';
        $ipAddress = $options['ip_address'] ?? null;
        $userAgent = $options['user_agent'] ?? null;

        if ($consentType === 'all') {
            // Withdraw all consent types for this user
            $consents = $this->getAllConsents($agentId, $channel, $externalUserId);
            $results = [];
            foreach ($consents as $consent) {
                if ($consent['consent_status'] === 'granted') {
                    $results[] = $this->updateConsentStatus(
                        $consent['id'],
                        'withdrawn',
                        'withdrawn',
                        $reason,
                        $triggeredBy,
                        $ipAddress,
                        $userAgent
                    );
                }
            }
            return $results;
        }

        $consent = $this->getConsent($agentId, $channel, $externalUserId, $consentType);
        
        if (!$consent) {
            return ['error' => 'Consent not found'];
        }

        return $this->updateConsentStatus(
            $consent['id'],
            'withdrawn',
            'withdrawn',
            $reason,
            $triggeredBy,
            $ipAddress,
            $userAgent
        );
    }

    /**
     * Check if user has granted consent
     */
    public function hasConsent($agentId, $channel, $externalUserId, $consentType = 'service') {
        $consent = $this->getConsent($agentId, $channel, $externalUserId, $consentType);
        
        if (!$consent) {
            return false;
        }

        // Check if consent is granted and not expired
        if ($consent['consent_status'] !== 'granted') {
            return false;
        }

        // Check expiration
        if ($consent['expires_at'] !== null) {
            $expiresAt = strtotime($consent['expires_at']);
            if ($expiresAt < time()) {
                // Consent expired, update status
                $this->updateConsentStatus(
                    $consent['id'],
                    'withdrawn',
                    'expired',
                    'Consent expired',
                    'system'
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Get consent record
     */
    public function getConsent($agentId, $channel, $externalUserId, $consentType) {
        $sql = "
            SELECT * FROM user_consents
            WHERE agent_id = ? AND channel = ? AND external_user_id = ? AND consent_type = ?
        ";
        $params = [$agentId, $channel, $externalUserId, $consentType];

        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all consents for a user
     */
    public function getAllConsents($agentId, $channel, $externalUserId) {
        $sql = "
            SELECT * FROM user_consents
            WHERE agent_id = ? AND channel = ? AND external_user_id = ?
        ";
        $params = [$agentId, $channel, $externalUserId];

        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get consent by ID
     */
    public function getConsentById($consentId) {
        $sql = "SELECT * FROM user_consents WHERE id = ?";
        $params = [$consentId];

        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get consent audit history
     */
    public function getConsentAuditHistory($consentId, $limit = 100) {
        $stmt = $this->db->prepare("
            SELECT * FROM consent_audit_log
            WHERE consent_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$consentId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List consents with filters
     */
    public function listConsents($filters = []) {
        $sql = "SELECT * FROM user_consents WHERE 1=1";
        $params = [];

        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }

        if (isset($filters['agent_id'])) {
            $sql .= " AND agent_id = ?";
            $params[] = $filters['agent_id'];
        }

        if (isset($filters['channel'])) {
            $sql .= " AND channel = ?";
            $params[] = $filters['channel'];
        }

        if (isset($filters['consent_type'])) {
            $sql .= " AND consent_type = ?";
            $params[] = $filters['consent_type'];
        }

        if (isset($filters['consent_status'])) {
            $sql .= " AND consent_status = ?";
            $params[] = $filters['consent_status'];
        }

        if (isset($filters['external_user_id'])) {
            $sql .= " AND external_user_id = ?";
            $params[] = $filters['external_user_id'];
        }

        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete consent (GDPR right to erasure)
     */
    public function deleteConsent($consentId) {
        // First, archive to audit log if needed
        $consent = $this->getConsentById($consentId);
        if (!$consent) {
            return ['error' => 'Consent not found'];
        }

        // Log deletion in audit trail
        $this->logConsentAudit(
            $consentId,
            'deleted',
            $consent['consent_status'],
            'deleted',
            'GDPR/LGPD erasure request',
            'admin'
        );

        // Delete consent record
        $stmt = $this->db->prepare("DELETE FROM user_consents WHERE id = ?");
        $stmt->execute([$consentId]);

        return ['success' => true, 'deleted' => $consentId];
    }

    /**
     * Process opt-in/opt-out keywords
     */
    public function processConsentKeyword($agentId, $channel, $externalUserId, $keyword, $options = []) {
        $keyword = strtoupper(trim($keyword));
        
        // Define opt-out keywords
        $optOutKeywords = $options['opt_out_keywords'] ?? ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'OPTOUT', 'SAIR'];
        
        // Define opt-in keywords
        $optInKeywords = $options['opt_in_keywords'] ?? ['START', 'SUBSCRIBE', 'YES', 'OPTIN', 'SIM'];

        if (in_array($keyword, $optOutKeywords)) {
            return [
                'action' => 'opt_out',
                'result' => $this->withdrawConsent($agentId, $channel, $externalUserId, 'all', $options)
            ];
        }

        if (in_array($keyword, $optInKeywords)) {
            return [
                'action' => 'opt_in',
                'result' => $this->grantConsent($agentId, $channel, $externalUserId, $options)
            ];
        }

        return ['action' => 'none'];
    }

    /**
     * Update consent status
     */
    private function updateConsentStatus($consentId, $newStatus, $action, $reason, $triggeredBy, $ipAddress = null, $userAgent = null) {
        $consent = $this->getConsentById($consentId);
        if (!$consent) {
            return ['error' => 'Consent not found'];
        }

        $previousStatus = $consent['consent_status'];
        $now = $this->now();

        $updates = [
            'consent_status' => $newStatus,
            'updated_at' => $now
        ];

        if ($newStatus === 'withdrawn') {
            $updates['withdrawn_at'] = $now;
        }

        $setClauses = [];
        $params = [];
        foreach ($updates as $key => $value) {
            $setClauses[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $consentId;

        $sql = "UPDATE user_consents SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        // Log audit entry
        $this->logConsentAudit($consentId, $action, $previousStatus, $newStatus, $reason, $triggeredBy, $ipAddress, $userAgent);

        return $this->getConsentById($consentId);
    }

    /**
     * Log consent audit entry
     */
    private function logConsentAudit($consentId, $action, $previousStatus, $newStatus, $reason, $triggeredBy, $ipAddress = null, $userAgent = null, $metadata = []) {
        $auditId = $this->generateUuid();
        $now = $this->now();

        $stmt = $this->db->prepare("
            INSERT INTO consent_audit_log (
                id, consent_id, action, previous_status, new_status,
                reason, triggered_by, ip_address, user_agent,
                metadata_json, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $auditId,
            $consentId,
            $action,
            $previousStatus,
            $newStatus,
            $reason,
            $triggeredBy,
            $ipAddress,
            $userAgent,
            json_encode($metadata),
            $now
        ]);

        return $auditId;
    }

    /**
     * Generate UUID v4
     */
    private function generateUuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Get current timestamp in ISO 8601 format
     */
    private function now() {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * Set tenant context
     */
    public function setTenantId($tenantId) {
        $this->tenantId = $tenantId;
    }
}
