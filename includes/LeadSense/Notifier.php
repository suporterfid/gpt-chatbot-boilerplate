<?php
/**
 * Notifier - Sends notifications for qualified leads
 * 
 * Supports Slack webhooks and generic webhooks with retry logic
 * Updated to use WebhookDispatcher for centralized webhook management
 */

require_once __DIR__ . '/../WebhookDispatcher.php';
require_once __DIR__ . '/../DB.php';

class Notifier {
    private $config;
    private $redactor;
    private $dispatcher;
    private $db;
    
    public function __construct($config = [], $redactor = null, $db = null) {
        $this->config = $config;
        $this->redactor = $redactor;
        $this->db = $db;
        
        // Initialize dispatcher if DB is available
        if ($db) {
            $this->dispatcher = new WebhookDispatcher($db, $config, 'leadsense');
        }
    }
    
    /**
     * Notify about a new qualified lead
     * 
     * @param array $lead Lead data
     * @param array $scoreData Score and rationale
     * @return array Results of notifications sent
     */
    public function notifyNewQualifiedLead($lead, $scoreData) {
        $results = [];
        
        // Redact PII if configured
        $notificationLead = $this->redactor ? 
            $this->redactor->redactLead($lead) : 
            $lead;
        
        // Send to Slack if configured (still uses direct sending for Slack format)
        $slackWebhook = $this->config['notify']['slack_webhook_url'] ?? '';
        if (!empty($slackWebhook)) {
            $results['slack'] = $this->sendSlackNotification(
                $slackWebhook,
                $notificationLead,
                $scoreData
            );
        }
        
        // Send via WebhookDispatcher if available (for registered subscribers)
        if ($this->dispatcher) {
            try {
                $dispatchResult = $this->dispatcher->dispatch(
                    'lead.qualified',
                    [
                        'lead' => $notificationLead,
                        'score' => $scoreData
                    ]
                );
                $results['dispatcher'] = $dispatchResult;
            } catch (Exception $e) {
                error_log("LeadSense: Failed to dispatch webhook: " . $e->getMessage());
                $results['dispatcher'] = ['error' => $e->getMessage()];
            }
        }
        
        // Legacy: Send to generic webhook if configured (backward compatibility)
        $webhook = $this->config['notify']['webhook_url'] ?? '';
        if (!empty($webhook)) {
            $results['webhook_legacy'] = $this->sendWebhookNotification(
                $webhook,
                $notificationLead,
                $scoreData
            );
        }
        
        return $results;
    }
    
    /**
     * Send Slack notification
     * 
     * @param string $webhookUrl
     * @param array $lead
     * @param array $scoreData
     * @return bool Success
     */
    private function sendSlackNotification($webhookUrl, $lead, $scoreData) {
        $message = $this->buildSlackMessage($lead, $scoreData);
        
        return $this->sendWithRetry($webhookUrl, [
            'json' => json_encode($message),
            'headers' => ['Content-Type: application/json']
        ]);
    }
    
    /**
     * Build Slack message payload
     * 
     * @param array $lead
     * @param array $scoreData
     * @return array
     */
    private function buildSlackMessage($lead, $scoreData) {
        $score = $scoreData['score'] ?? 0;
        $qualified = $scoreData['qualified'] ?? false;
        
        $emoji = $qualified ? ':star:' : ':information_source:';
        $color = $score >= 80 ? '#36a64f' : ($score >= 60 ? '#ff9900' : '#cccccc');
        
        $fields = [];
        
        if (!empty($lead['name'])) {
            $fields[] = [
                'title' => 'Name',
                'value' => $lead['name'],
                'short' => true
            ];
        }
        
        if (!empty($lead['company'])) {
            $fields[] = [
                'title' => 'Company',
                'value' => $lead['company'],
                'short' => true
            ];
        }
        
        if (!empty($lead['role'])) {
            $fields[] = [
                'title' => 'Role',
                'value' => $lead['role'],
                'short' => true
            ];
        }
        
        if (!empty($lead['email'])) {
            $fields[] = [
                'title' => 'Email',
                'value' => $lead['email'],
                'short' => true
            ];
        }
        
        if (!empty($lead['phone'])) {
            $fields[] = [
                'title' => 'Phone',
                'value' => $lead['phone'],
                'short' => true
            ];
        }
        
        $fields[] = [
            'title' => 'Lead Score',
            'value' => $score . '/100',
            'short' => true
        ];
        
        $fields[] = [
            'title' => 'Intent Level',
            'value' => ucfirst($lead['intent_level'] ?? 'unknown'),
            'short' => true
        ];
        
        if (!empty($lead['interest'])) {
            $interest = strlen($lead['interest']) > 200 ? 
                substr($lead['interest'], 0, 197) . '...' : 
                $lead['interest'];
            $fields[] = [
                'title' => 'Interest',
                'value' => $interest,
                'short' => false
            ];
        }
        
        return [
            'text' => "$emoji New " . ($qualified ? "Qualified " : "") . "Lead Detected",
            'attachments' => [
                [
                    'color' => $color,
                    'fields' => $fields,
                    'footer' => 'LeadSense',
                    'ts' => time()
                ]
            ]
        ];
    }
    
    /**
     * Send generic webhook notification
     * 
     * @param string $webhookUrl
     * @param array $lead
     * @param array $scoreData
     * @return bool Success
     */
    private function sendWebhookNotification($webhookUrl, $lead, $scoreData) {
        $payload = [
            'event' => 'lead.qualified',
            'timestamp' => date('c'),
            'lead' => $lead,
            'score' => $scoreData
        ];
        
        $headers = ['Content-Type: application/json'];
        
        // Add HMAC signature if secret is configured
        $secret = $this->config['notify']['webhook_secret'] ?? '';
        if (!empty($secret)) {
            $signature = hash_hmac('sha256', json_encode($payload), $secret);
            $headers[] = 'X-LeadSense-Signature: sha256=' . $signature;
        }
        
        return $this->sendWithRetry($webhookUrl, [
            'json' => json_encode($payload),
            'headers' => $headers
        ]);
    }
    
    /**
     * Send HTTP request with retry logic
     * 
     * @param string $url
     * @param array $options
     * @param int $maxRetries
     * @return bool Success
     */
    private function sendWithRetry($url, $options, $maxRetries = 3) {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            try {
                $result = $this->sendHttpRequest($url, $options);
                if ($result) {
                    return true;
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                error_log("LeadSense notification attempt " . ($attempt + 1) . " failed: " . $lastError);
            }
            
            $attempt++;
            if ($attempt < $maxRetries) {
                // Exponential backoff: 1s, 2s, 4s
                sleep(pow(2, $attempt - 1));
            }
        }
        
        error_log("LeadSense notification failed after $maxRetries attempts. Last error: $lastError");
        return false;
    }
    
    /**
     * Send HTTP request using cURL
     * 
     * @param string $url
     * @param array $options
     * @return bool Success
     */
    private function sendHttpRequest($url, $options) {
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if (isset($options['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['json']);
        }
        
        if (isset($options['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("HTTP error: $httpCode - $response");
        }
        
        return true;
    }
}
