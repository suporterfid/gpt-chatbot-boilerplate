<?php
/**
 * AutomationService - Manages CRM automation rules
 * 
 * Handles rule CRUD, trigger evaluation, and action execution.
 * Supports webhook, Slack, email, and other notification types.
 */

require_once __DIR__ . '/../../DB.php';
require_once __DIR__ . '/../LeadEventTypes.php';

class AutomationService {
    private $db;
    private $tenantId;
    private $config;
    
    public function __construct($db, $config = [], $tenantId = null) {
        $this->db = $db;
        $this->config = $config;
        $this->tenantId = $tenantId;
    }
    
    /**
     * Set tenant context
     */
    public function setTenantId($tenantId) {
        $this->tenantId = $tenantId;
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * List automation rules
     * 
     * @param array $filters Filters (is_active, trigger_event)
     * @return array List of rules
     */
    public function listRules($filters = []) {
        $sql = "SELECT * FROM crm_automation_rules WHERE 1=1";
        $params = [];
        
        // Tenant filtering
        if ($this->tenantId !== null) {
            $sql .= " AND (client_id = ? OR client_id IS NULL)";
            $params[] = $this->tenantId;
        }
        
        // Active filter
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = (int)(bool)$filters['is_active'];
        }
        
        // Trigger event filter
        if (!empty($filters['trigger_event'])) {
            $sql .= " AND trigger_event = ?";
            $params[] = $filters['trigger_event'];
        }
        
        // Archive filter
        if (!isset($filters['include_archived']) || !$filters['include_archived']) {
            $sql .= " AND archived_at IS NULL";
        }
        
        $sql .= " ORDER BY name ASC";
        
        $rules = $this->db->query($sql, $params);
        
        // Parse JSON fields
        foreach ($rules as &$rule) {
            if ($rule['trigger_filter']) {
                $rule['trigger_filter'] = json_decode($rule['trigger_filter'], true) ?? [];
            }
            if ($rule['action_config']) {
                $rule['action_config'] = json_decode($rule['action_config'], true) ?? [];
            }
        }
        
        return $rules;
    }
    
    /**
     * Get a specific rule
     * 
     * @param string $ruleId Rule UUID
     * @return array|null Rule data
     */
    public function getRule($ruleId) {
        $sql = "SELECT * FROM crm_automation_rules WHERE id = ?";
        $rule = $this->db->getOne($sql, [$ruleId]);
        
        if (!$rule) {
            return null;
        }
        
        // Parse JSON fields
        if ($rule['trigger_filter']) {
            $rule['trigger_filter'] = json_decode($rule['trigger_filter'], true) ?? [];
        }
        if ($rule['action_config']) {
            $rule['action_config'] = json_decode($rule['action_config'], true) ?? [];
        }
        
        return $rule;
    }
    
    /**
     * Create an automation rule
     * 
     * @param array $data Rule data
     * @return array Created rule or error
     */
    public function createRule($data) {
        // Validation
        $errors = $this->validateRuleData($data);
        if (!empty($errors)) {
            return ['error' => implode(', ', $errors)];
        }
        
        $ruleId = $this->generateUUID();
        
        try {
            $sql = "INSERT INTO crm_automation_rules
                (id, client_id, name, is_active, trigger_event, trigger_filter, 
                 action_type, action_config, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))";
            
            $params = [
                $ruleId,
                $this->tenantId,
                $data['name'],
                isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
                $data['trigger_event'],
                isset($data['trigger_filter']) ? json_encode($data['trigger_filter']) : null,
                $data['action_type'],
                json_encode($data['action_config'])
            ];
            
            $this->db->execute($sql, $params);
            
            return $this->getRule($ruleId);
            
        } catch (Exception $e) {
            error_log("Failed to create automation rule: " . $e->getMessage());
            return ['error' => 'Failed to create automation rule'];
        }
    }
    
    /**
     * Update an automation rule
     * 
     * @param string $ruleId Rule UUID
     * @param array $data Update data
     * @return array Updated rule or error
     */
    public function updateRule($ruleId, $data) {
        $rule = $this->getRule($ruleId);
        if (!$rule) {
            return ['error' => 'Rule not found'];
        }
        
        try {
            $fields = [];
            $params = [];
            
            $allowedFields = ['name', 'is_active', 'trigger_event', 'trigger_filter', 'action_type', 'action_config'];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    if ($field === 'is_active') {
                        $fields[] = "$field = ?";
                        $params[] = (int)(bool)$data[$field];
                    } elseif (in_array($field, ['trigger_filter', 'action_config'])) {
                        $fields[] = "$field = ?";
                        $params[] = json_encode($data[$field]);
                    } else {
                        $fields[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($fields)) {
                return $rule; // No changes
            }
            
            $fields[] = "updated_at = datetime('now')";
            $params[] = $ruleId;
            
            $sql = "UPDATE crm_automation_rules SET " . implode(', ', $fields) . " WHERE id = ?";
            $this->db->execute($sql, $params);
            
            return $this->getRule($ruleId);
            
        } catch (Exception $e) {
            error_log("Failed to update automation rule: " . $e->getMessage());
            return ['error' => 'Failed to update automation rule'];
        }
    }
    
    /**
     * Archive an automation rule
     * 
     * @param string $ruleId Rule UUID
     * @return array Success or error
     */
    public function archiveRule($ruleId) {
        $rule = $this->getRule($ruleId);
        if (!$rule) {
            return ['error' => 'Rule not found'];
        }
        
        try {
            $sql = "UPDATE crm_automation_rules 
                SET archived_at = datetime('now'), updated_at = datetime('now')
                WHERE id = ?";
            
            $this->db->execute($sql, [$ruleId]);
            
            return ['success' => true, 'message' => 'Rule archived'];
            
        } catch (Exception $e) {
            error_log("Failed to archive automation rule: " . $e->getMessage());
            return ['error' => 'Failed to archive automation rule'];
        }
    }
    
    /**
     * Evaluate and execute rules for an event
     * 
     * @param string $eventType Event type (e.g., 'lead.created', 'lead.stage_changed')
     * @param array $context Event context (lead, stage, pipeline data)
     * @return array Results of rule execution
     */
    public function evaluateRules($eventType, $context) {
        // Get active rules for this event type
        $rules = $this->listRules([
            'is_active' => true,
            'trigger_event' => $eventType
        ]);
        
        $results = [];
        
        foreach ($rules as $rule) {
            // Evaluate trigger filter
            if ($this->evaluateFilter($rule['trigger_filter'], $context)) {
                // Execute action
                $result = $this->executeAction($rule, $context);
                $results[] = [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'result' => $result
                ];
                
                // Log execution
                $this->logExecution($rule['id'], $context, $result);
            }
        }
        
        return $results;
    }
    
    /**
     * Evaluate trigger filter against context
     * 
     * @param array $filter Filter conditions
     * @param array $context Event context
     * @return bool True if filter matches
     */
    private function evaluateFilter($filter, $context) {
        if (empty($filter)) {
            return true; // No filter means always match
        }
        
        $lead = $context['lead'] ?? [];
        
        // Check pipeline_id
        if (isset($filter['pipeline_id']) && ($lead['pipeline_id'] ?? null) !== $filter['pipeline_id']) {
            return false;
        }
        
        // Check stage_id
        if (isset($filter['stage_id']) && ($lead['stage_id'] ?? null) !== $filter['stage_id']) {
            return false;
        }
        
        // Check min_score
        if (isset($filter['min_score']) && ($lead['score'] ?? 0) < $filter['min_score']) {
            return false;
        }
        
        // Check qualified
        if (isset($filter['qualified']) && ($lead['qualified'] ?? false) !== $filter['qualified']) {
            return false;
        }
        
        // Check status
        if (isset($filter['status']) && ($lead['status'] ?? null) !== $filter['status']) {
            return false;
        }
        
        // Check intent_level
        if (isset($filter['intent_level']) && ($lead['intent_level'] ?? null) !== $filter['intent_level']) {
            return false;
        }
        
        // Check tags (lead must have ALL specified tags)
        if (isset($filter['tags']) && is_array($filter['tags']) && !empty($filter['tags'])) {
            $leadTags = $lead['tags'] ?? [];
            if (is_string($leadTags)) {
                $leadTags = json_decode($leadTags, true) ?? [];
            }
            
            foreach ($filter['tags'] as $requiredTag) {
                if (!in_array($requiredTag, $leadTags)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Execute an action
     * 
     * @param array $rule Rule data
     * @param array $context Event context
     * @return array Execution result
     */
    private function executeAction($rule, $context) {
        $actionType = $rule['action_type'];
        $actionConfig = $rule['action_config'];
        $lead = $context['lead'] ?? [];
        
        try {
            switch ($actionType) {
                case 'webhook':
                    return $this->executeWebhook($actionConfig, $lead, $context);
                    
                case 'slack':
                    return $this->executeSlack($actionConfig, $lead, $context);
                    
                case 'email':
                    return $this->executeEmail($actionConfig, $lead, $context);
                    
                default:
                    return [
                        'success' => false,
                        'error' => "Unsupported action type: $actionType"
                    ];
            }
        } catch (Exception $e) {
            error_log("Failed to execute action: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute webhook action
     * 
     * @param array $config Webhook configuration
     * @param array $lead Lead data
     * @param array $context Full context
     * @return array Result
     */
    private function executeWebhook($config, $lead, $context) {
        $url = $config['url'] ?? null;
        
        if (empty($url)) {
            return ['success' => false, 'error' => 'Webhook URL not configured'];
        }
        
        // Build payload
        $payload = [
            'event' => $context['event_type'] ?? 'unknown',
            'lead' => $lead,
            'pipeline_id' => $lead['pipeline_id'] ?? null,
            'stage_id' => $lead['stage_id'] ?? null,
            'timestamp' => date('c')
        ];
        
        // Add additional context if available
        if (isset($context['stage'])) {
            $payload['stage'] = $context['stage'];
        }
        if (isset($context['pipeline'])) {
            $payload['pipeline'] = $context['pipeline'];
        }
        
        // Send webhook
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: LeadSense-CRM/1.0'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        // Add custom headers if provided
        if (!empty($config['headers'])) {
            $headers = [];
            foreach ($config['headers'] as $key => $value) {
                $headers[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
                ['Content-Type: application/json', 'User-Agent: LeadSense-CRM/1.0'],
                $headers
            ));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => "Webhook request failed: $error"
            ];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'http_code' => $httpCode,
                'response' => $response
            ];
        } else {
            return [
                'success' => false,
                'error' => "Webhook returned HTTP $httpCode",
                'response' => $response
            ];
        }
    }
    
    /**
     * Execute Slack notification action
     * 
     * @param array $config Slack configuration
     * @param array $lead Lead data
     * @param array $context Full context
     * @return array Result
     */
    private function executeSlack($config, $lead, $context) {
        $webhookUrl = $config['webhook_url'] ?? $this->config['leadsense']['crm']['slack_webhook_url'] ?? null;
        
        if (empty($webhookUrl)) {
            return ['success' => false, 'error' => 'Slack webhook URL not configured'];
        }
        
        // Build message
        $leadName = $lead['name'] ?? 'Unknown';
        $leadCompany = $lead['company'] ?? '';
        $leadScore = $lead['score'] ?? 0;
        $eventType = $context['event_type'] ?? 'event';
        
        $text = $config['message_template'] ?? "🔔 Lead Update: {lead_name} from {lead_company} (Score: {score})";
        $text = str_replace(
            ['{lead_name}', '{lead_company}', '{score}', '{event_type}'],
            [$leadName, $leadCompany, $leadScore, $eventType],
            $text
        );
        
        $payload = [
            'text' => $text,
            'username' => $config['username'] ?? 'LeadSense CRM',
            'icon_emoji' => $config['icon'] ?? ':bell:'
        ];
        
        // Add blocks for rich formatting if configured
        if (!empty($config['use_blocks']) && $config['use_blocks']) {
            $payload['blocks'] = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*$leadName*" . ($leadCompany ? " from *$leadCompany*" : "")
                    ]
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "Score: $leadScore | Event: $eventType"
                        ]
                    ]
                ]
            ];
        }
        
        // Send to Slack
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => "Slack request failed: $error"];
        }
        
        if ($httpCode === 200 && $response === 'ok') {
            return ['success' => true, 'message' => 'Slack notification sent'];
        } else {
            return ['success' => false, 'error' => "Slack returned: $response"];
        }
    }
    
    /**
     * Execute email notification action (placeholder)
     * 
     * @param array $config Email configuration
     * @param array $lead Lead data
     * @param array $context Full context
     * @return array Result
     */
    private function executeEmail($config, $lead, $context) {
        // This is a placeholder for email integration
        // Would integrate with existing email service or SMTP
        return [
            'success' => false,
            'error' => 'Email action not yet implemented'
        ];
    }
    
    /**
     * Log rule execution
     * 
     * @param string $ruleId Rule UUID
     * @param array $context Event context
     * @param array $result Execution result
     * @return string Log ID
     */
    private function logExecution($ruleId, $context, $result) {
        $logId = $this->generateUUID();
        $lead = $context['lead'] ?? [];
        
        try {
            $sql = "INSERT INTO crm_automation_logs
                (id, rule_id, lead_id, event_type, status, message, payload_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))";
            
            $params = [
                $logId,
                $ruleId,
                $lead['id'] ?? null,
                $context['event_type'] ?? 'unknown',
                $result['success'] ? 'success' : 'error',
                $result['error'] ?? $result['message'] ?? 'Executed',
                json_encode([
                    'context' => $context,
                    'result' => $result
                ])
            ];
            
            $this->db->execute($sql, $params);
            
            return $logId;
            
        } catch (Exception $e) {
            error_log("Failed to log automation execution: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get execution logs for a rule
     * 
     * @param string $ruleId Rule UUID
     * @param array $filters Filters (limit, offset)
     * @return array List of logs
     */
    public function getRuleLogs($ruleId, $filters = []) {
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        
        $sql = "SELECT * FROM crm_automation_logs 
            WHERE rule_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";
        
        return $this->db->query($sql, [$ruleId, $limit, $offset]);
    }
    
    /**
     * Validate rule data
     * 
     * @param array $data Rule data
     * @return array Array of error messages (empty if valid)
     */
    private function validateRuleData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Rule name is required';
        }
        
        if (empty($data['trigger_event'])) {
            $errors[] = 'Trigger event is required';
        }
        
        if (empty($data['action_type'])) {
            $errors[] = 'Action type is required';
        }
        
        if (!isset($data['action_config']) || !is_array($data['action_config'])) {
            $errors[] = 'Action config is required and must be an object';
        }
        
        // Validate action_config based on action_type
        if (!empty($data['action_type'])) {
            switch ($data['action_type']) {
                case 'webhook':
                    if (empty($data['action_config']['url'])) {
                        $errors[] = 'Webhook URL is required for webhook action';
                    }
                    break;
                    
                case 'slack':
                    // webhook_url can be in config or global config
                    break;
                    
                case 'email':
                    if (empty($data['action_config']['to'])) {
                        $errors[] = 'Email recipient is required for email action';
                    }
                    break;
            }
        }
        
        return $errors;
    }
}
