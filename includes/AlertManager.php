<?php
/**
 * Alert Manager - Send alerts to external systems
 * 
 * Supports:
 * - Slack webhooks
 * - Generic webhooks
 * - Email (via PHP mail)
 * - PagerDuty
 */

class AlertManager {
    private $config;
    private $logger;
    
    // Alert severities
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';
    
    public function __construct($config, $logger = null) {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Send an alert
     * 
     * @param string $title Alert title
     * @param string $message Alert message
     * @param string $severity Alert severity (info, warning, critical)
     * @param array $context Additional context
     */
    public function sendAlert($title, $message, $severity = self::SEVERITY_WARNING, $context = []) {
        $alert = [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'context' => $context
        ];
        
        // Log the alert
        if ($this->logger) {
            $logLevel = $this->severityToLogLevel($severity);
            $this->logger->$logLevel('alert_manager', 'alert_triggered', $alert);
        }
        
        // Send to configured channels
        $this->sendToSlack($alert);
        $this->sendToWebhook($alert);
        $this->sendToEmail($alert);
        $this->sendToPagerDuty($alert);
    }
    
    /**
     * Send alert to Slack
     */
    private function sendToSlack($alert) {
        $webhookUrl = $this->config['alerting']['slack_webhook_url'] ?? '';
        
        if (empty($webhookUrl)) {
            return;
        }
        
        // Build Slack message
        $color = $this->getSeverityColor($alert['severity']);
        
        $payload = [
            'username' => 'ChatBot Alerts',
            'icon_emoji' => ':rotating_light:',
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $alert['title'],
                    'text' => $alert['message'],
                    'fields' => $this->buildSlackFields($alert),
                    'footer' => 'ChatBot Monitoring',
                    'ts' => strtotime($alert['timestamp'])
                ]
            ]
        ];
        
        $this->sendHttpPost($webhookUrl, json_encode($payload), [
            'Content-Type: application/json'
        ]);
    }
    
    /**
     * Send alert to generic webhook
     */
    private function sendToWebhook($alert) {
        $webhookUrl = $this->config['alerting']['webhook_url'] ?? '';
        $secret = $this->config['alerting']['webhook_secret'] ?? '';
        
        if (empty($webhookUrl)) {
            return;
        }
        
        $payload = json_encode($alert);
        
        // Add HMAC signature if secret is configured
        $headers = ['Content-Type: application/json'];
        if (!empty($secret)) {
            $signature = hash_hmac('sha256', $payload, $secret);
            $headers[] = 'X-Signature: sha256=' . $signature;
        }
        
        $this->sendHttpPost($webhookUrl, $payload, $headers);
    }
    
    /**
     * Send alert via email
     * 
     * Note: For production use, consider using PHPMailer, SwiftMailer, 
     * or queuing emails for better reliability instead of PHP's mail() function.
     */
    private function sendToEmail($alert) {
        $emailTo = $this->config['alerting']['email_to'] ?? '';
        $emailFrom = $this->config['alerting']['email_from'] ?? 'alerts@chatbot.local';
        
        if (empty($emailTo)) {
            return;
        }
        
        // Only send critical alerts via email by default
        if ($alert['severity'] !== self::SEVERITY_CRITICAL) {
            $sendAll = $this->config['alerting']['email_all_severities'] ?? false;
            if (!$sendAll) {
                return;
            }
        }
        
        $subject = sprintf('[%s] %s', strtoupper($alert['severity']), $alert['title']);
        
        $body = "Alert Details:\n\n";
        $body .= "Title: " . $alert['title'] . "\n";
        $body .= "Severity: " . $alert['severity'] . "\n";
        $body .= "Message: " . $alert['message'] . "\n";
        $body .= "Timestamp: " . $alert['timestamp'] . "\n\n";
        
        if (!empty($alert['context'])) {
            $body .= "Context:\n";
            foreach ($alert['context'] as $key => $value) {
                $body .= "  $key: " . json_encode($value) . "\n";
            }
        }
        
        $headers = [
            'From: ' . $emailFrom,
            'Reply-To: ' . $emailFrom,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        // Using mail() for simplicity - consider using a proper mail library for production
        @mail($emailTo, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Send alert to PagerDuty
     */
    private function sendToPagerDuty($alert) {
        $routingKey = $this->config['alerting']['pagerduty_routing_key'] ?? '';
        
        if (empty($routingKey)) {
            return;
        }
        
        // Only page for critical alerts
        if ($alert['severity'] !== self::SEVERITY_CRITICAL) {
            return;
        }
        
        $payload = [
            'routing_key' => $routingKey,
            'event_action' => 'trigger',
            'payload' => [
                'summary' => $alert['title'],
                'severity' => $alert['severity'],
                'source' => 'chatbot',
                'timestamp' => $alert['timestamp'],
                'custom_details' => [
                    'message' => $alert['message'],
                    'context' => $alert['context']
                ]
            ]
        ];
        
        $this->sendHttpPost(
            'https://events.pagerduty.com/v2/enqueue',
            json_encode($payload),
            ['Content-Type: application/json']
        );
    }
    
    /**
     * Build Slack attachment fields
     */
    private function buildSlackFields($alert) {
        $fields = [
            [
                'title' => 'Severity',
                'value' => strtoupper($alert['severity']),
                'short' => true
            ],
            [
                'title' => 'Timestamp',
                'value' => $alert['timestamp'],
                'short' => true
            ]
        ];
        
        // Add context fields
        foreach ($alert['context'] as $key => $value) {
            $fields[] = [
                'title' => ucwords(str_replace('_', ' ', $key)),
                'value' => is_scalar($value) ? (string)$value : json_encode($value),
                'short' => true
            ];
        }
        
        return $fields;
    }
    
    /**
     * Get color for severity
     */
    private function getSeverityColor($severity) {
        switch ($severity) {
            case self::SEVERITY_CRITICAL:
                return 'danger';
            case self::SEVERITY_WARNING:
                return 'warning';
            case self::SEVERITY_INFO:
            default:
                return 'good';
        }
    }
    
    /**
     * Convert severity to log level
     */
    private function severityToLogLevel($severity) {
        switch ($severity) {
            case self::SEVERITY_CRITICAL:
                return 'critical';
            case self::SEVERITY_WARNING:
                return 'warn';
            case self::SEVERITY_INFO:
            default:
                return 'info';
        }
    }
    
    /**
     * Send HTTP POST request
     */
    private function sendHttpPost($url, $data, $headers = []) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($this->logger && $httpCode >= 400) {
            $this->logger->warn('alert_manager', 'alert_delivery_failed', [
                'url' => $url,
                'http_code' => $httpCode,
                'response' => substr($response, 0, 200)
            ]);
        }
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    /**
     * Helper: Send queue depth alert
     */
    public function alertQueueDepth($depth, $threshold) {
        $this->sendAlert(
            'High Queue Depth',
            "Job queue has {$depth} pending jobs (threshold: {$threshold})",
            $depth > $threshold * 2 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING,
            [
                'queue_depth' => $depth,
                'threshold' => $threshold,
                'overage_percent' => round((($depth - $threshold) / $threshold) * 100, 2)
            ]
        );
    }
    
    /**
     * Helper: Send worker down alert
     */
    public function alertWorkerDown($secondsSinceLastJob) {
        $this->sendAlert(
            'Background Worker Down',
            "Worker has not processed jobs in {$secondsSinceLastJob} seconds",
            self::SEVERITY_CRITICAL,
            [
                'seconds_since_last_job' => $secondsSinceLastJob,
                'threshold' => 300
            ]
        );
    }
    
    /**
     * Helper: Send high error rate alert
     */
    public function alertHighErrorRate($errorRate, $component = null) {
        $message = $component 
            ? "Error rate for {$component} is {$errorRate}%"
            : "Overall error rate is {$errorRate}%";
            
        $this->sendAlert(
            'High Error Rate',
            $message,
            $errorRate > 50 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING,
            [
                'error_rate_percent' => $errorRate,
                'component' => $component,
                'threshold_warning' => 10,
                'threshold_critical' => 50
            ]
        );
    }
    
    /**
     * Helper: Send disk space alert
     */
    public function alertDiskSpace($usedPercent, $freeBytes) {
        $this->sendAlert(
            'Low Disk Space',
            sprintf('Disk usage at %.1f%% (%s free)', $usedPercent, $this->formatBytes($freeBytes)),
            $usedPercent > 95 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING,
            [
                'used_percent' => $usedPercent,
                'free_bytes' => $freeBytes,
                'free_human' => $this->formatBytes($freeBytes)
            ]
        );
    }
    
    /**
     * Format bytes to human-readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
