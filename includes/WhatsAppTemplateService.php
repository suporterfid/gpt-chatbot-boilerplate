<?php
/**
 * WhatsAppTemplateService - Manages WhatsApp Business message templates
 * 
 * Handles template creation, approval tracking, usage logging, and sending
 * templated messages via WhatsApp Business API.
 */

class WhatsAppTemplateService {
    private $db;
    private $tenantId;

    public function __construct($db, $tenantId = null) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }

    /**
     * Create a new template
     */
    public function createTemplate($data) {
        $required = ['template_name', 'template_category', 'language_code', 'content_text'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $templateId = $this->generateUuid();
        $now = $this->now();

        $stmt = $this->db->prepare("
            INSERT INTO whatsapp_templates (
                id, tenant_id, agent_id, template_name, template_category,
                language_code, status, content_text, header_type, header_text,
                header_media_url, footer_text, buttons_json, variables_json,
                metadata_json, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $templateId,
            $this->tenantId,
            $data['agent_id'] ?? null,
            $data['template_name'],
            $data['template_category'],
            $data['language_code'],
            'draft',
            $data['content_text'],
            $data['header_type'] ?? null,
            $data['header_text'] ?? null,
            $data['header_media_url'] ?? null,
            $data['footer_text'] ?? null,
            isset($data['buttons']) ? json_encode($data['buttons']) : null,
            isset($data['variables']) ? json_encode($data['variables']) : null,
            isset($data['metadata']) ? json_encode($data['metadata']) : null,
            $now,
            $now
        ]);

        return $this->getTemplate($templateId);
    }

    /**
     * Update template
     */
    public function updateTemplate($templateId, $data) {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found");
        }

        $allowedFields = [
            'template_name', 'template_category', 'language_code', 'content_text',
            'header_type', 'header_text', 'header_media_url', 'footer_text',
            'buttons_json', 'variables_json', 'metadata_json', 'status',
            'whatsapp_template_id', 'quality_score', 'rejection_reason'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = ?";
                
                // Handle JSON fields
                if (in_array($field, ['buttons_json', 'variables_json', 'metadata_json'])) {
                    $params[] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
                } else {
                    $params[] = $data[$field];
                }
            }
        }

        if (empty($updates)) {
            return $template;
        }

        $updates[] = "updated_at = ?";
        $params[] = $this->now();
        $params[] = $templateId;

        $sql = "UPDATE whatsapp_templates SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getTemplate($templateId);
    }

    /**
     * Submit template for approval
     */
    public function submitTemplate($templateId) {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found");
        }

        if ($template['status'] !== 'draft') {
            throw new Exception("Only draft templates can be submitted");
        }

        $now = $this->now();

        $stmt = $this->db->prepare("
            UPDATE whatsapp_templates
            SET status = 'pending', submitted_at = ?, updated_at = ?
            WHERE id = ?
        ");

        $stmt->execute([$now, $now, $templateId]);

        return $this->getTemplate($templateId);
    }

    /**
     * Mark template as approved (called after WhatsApp approves)
     */
    public function approveTemplate($templateId, $whatsappTemplateId, $qualityScore = 'PENDING') {
        $now = $this->now();

        $stmt = $this->db->prepare("
            UPDATE whatsapp_templates
            SET status = 'approved', 
                whatsapp_template_id = ?,
                quality_score = ?,
                approved_at = ?,
                updated_at = ?
            WHERE id = ?
        ");

        $stmt->execute([$whatsappTemplateId, $qualityScore, $now, $now, $templateId]);

        return $this->getTemplate($templateId);
    }

    /**
     * Mark template as rejected
     */
    public function rejectTemplate($templateId, $rejectionReason) {
        $now = $this->now();

        $stmt = $this->db->prepare("
            UPDATE whatsapp_templates
            SET status = 'rejected',
                rejection_reason = ?,
                rejected_at = ?,
                updated_at = ?
            WHERE id = ?
        ");

        $stmt->execute([$rejectionReason, $now, $now, $templateId]);

        return $this->getTemplate($templateId);
    }

    /**
     * Get template by ID
     */
    public function getTemplate($templateId) {
        $sql = "SELECT * FROM whatsapp_templates WHERE id = ?";
        $params = [$templateId];

        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($template) {
            $template = $this->hydrateTemplate($template);
        }

        return $template ?: null;
    }

    /**
     * Get template by name and language
     */
    public function getTemplateByName($templateName, $languageCode) {
        $sql = "
            SELECT * FROM whatsapp_templates
            WHERE template_name = ? AND language_code = ? AND status = 'approved'
        ";
        $params = [$templateName, $languageCode];

        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($template) {
            $template = $this->hydrateTemplate($template);
        }

        return $template ?: null;
    }

    /**
     * List templates with filters
     */
    public function listTemplates($filters = []) {
        $sql = "SELECT * FROM whatsapp_templates WHERE 1=1";
        $params = [];

        if ($this->tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->tenantId;
        }

        if (isset($filters['agent_id'])) {
            $sql .= " AND agent_id = ?";
            $params[] = $filters['agent_id'];
        }

        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['category'])) {
            $sql .= " AND template_category = ?";
            $params[] = $filters['category'];
        }

        if (isset($filters['language'])) {
            $sql .= " AND language_code = ?";
            $params[] = $filters['language'];
        }

        if (isset($filters['search'])) {
            $sql .= " AND (template_name LIKE ? OR content_text LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'hydrateTemplate'], $templates);
    }

    /**
     * Delete template
     */
    public function deleteTemplate($templateId) {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found");
        }

        // Only allow deletion of draft or rejected templates
        if (!in_array($template['status'], ['draft', 'rejected'])) {
            throw new Exception("Cannot delete approved or pending templates");
        }

        $stmt = $this->db->prepare("DELETE FROM whatsapp_templates WHERE id = ?");
        $stmt->execute([$templateId]);

        return ['success' => true, 'deleted' => $templateId];
    }

    /**
     * Log template usage
     */
    public function logTemplateUsage($templateId, $agentId, $externalUserId, $variables = [], $options = []) {
        $usageId = $this->generateUuid();
        $now = $this->now();

        $stmt = $this->db->prepare("
            INSERT INTO whatsapp_template_usage (
                id, template_id, agent_id, channel, external_user_id,
                conversation_id, variables_json, delivery_status,
                sent_at, metadata_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $usageId,
            $templateId,
            $agentId,
            $options['channel'] ?? 'whatsapp',
            $externalUserId,
            $options['conversation_id'] ?? null,
            json_encode($variables),
            'sent',
            $now,
            isset($options['metadata']) ? json_encode($options['metadata']) : null
        ]);

        // Increment usage count
        $this->incrementUsageCount($templateId);

        return $usageId;
    }

    /**
     * Update template usage delivery status
     */
    public function updateTemplateUsageStatus($usageId, $status, $errorCode = null, $errorMessage = null) {
        $updates = ['delivery_status = ?'];
        $params = [$status];

        if ($status === 'delivered') {
            $updates[] = 'delivered_at = ?';
            $params[] = $this->now();
        } elseif ($status === 'read') {
            $updates[] = 'read_at = ?';
            $params[] = $this->now();
        } elseif ($status === 'failed') {
            if ($errorCode) {
                $updates[] = 'error_code = ?';
                $params[] = $errorCode;
            }
            if ($errorMessage) {
                $updates[] = 'error_message = ?';
                $params[] = $errorMessage;
            }
        }

        $params[] = $usageId;

        $sql = "UPDATE whatsapp_template_usage SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return true;
    }

    /**
     * Get template usage statistics
     */
    public function getTemplateStats($templateId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN delivery_status = 'read' THEN 1 ELSE 0 END) as read,
                SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed,
                MIN(sent_at) as first_sent,
                MAX(sent_at) as last_sent
            FROM whatsapp_template_usage
            WHERE template_id = ?
        ");

        $stmt->execute([$templateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Render template with variables
     */
    public function renderTemplate($templateId, $variables = []) {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found");
        }

        if ($template['status'] !== 'approved') {
            throw new Exception("Template must be approved before rendering");
        }

        $content = $template['content_text'];
        $header = $template['header_text'];

        // Replace variables in format {{1}}, {{2}}, etc.
        foreach ($variables as $index => $value) {
            $placeholder = '{{' . ($index + 1) . '}}';
            $content = str_replace($placeholder, $value, $content);
            if ($header) {
                $header = str_replace($placeholder, $value, $header);
            }
        }

        return [
            'content' => $content,
            'header' => $header,
            'footer' => $template['footer_text'],
            'buttons' => $template['buttons'],
            'whatsapp_template_id' => $template['whatsapp_template_id']
        ];
    }

    /**
     * Increment template usage count
     */
    private function incrementUsageCount($templateId) {
        $stmt = $this->db->prepare("
            UPDATE whatsapp_templates
            SET usage_count = usage_count + 1,
                last_used_at = ?,
                updated_at = ?
            WHERE id = ?
        ");

        $now = $this->now();
        $stmt->execute([$now, $now, $templateId]);
    }

    /**
     * Hydrate template (decode JSON fields)
     */
    private function hydrateTemplate($template) {
        if (isset($template['buttons_json'])) {
            $template['buttons'] = json_decode($template['buttons_json'], true);
        }
        if (isset($template['variables_json'])) {
            $template['variables'] = json_decode($template['variables_json'], true);
        }
        if (isset($template['metadata_json'])) {
            $template['metadata'] = json_decode($template['metadata_json'], true);
        }
        return $template;
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
