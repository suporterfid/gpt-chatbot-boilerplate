<?php
/**
 * LeadSenseService - Orchestrates the LeadSense pipeline
 * 
 * Coordinates detection, extraction, scoring, persistence, and notifications
 * for commercial opportunity detection in conversations
 */

require_once __DIR__ . '/IntentDetector.php';
require_once __DIR__ . '/EntityExtractor.php';
require_once __DIR__ . '/LeadScorer.php';
require_once __DIR__ . '/LeadRepository.php';
require_once __DIR__ . '/Notifier.php';
require_once __DIR__ . '/Redactor.php';

class LeadSenseService {
    private $config;
    private $intentDetector;
    private $entityExtractor;
    private $leadScorer;
    private $leadRepository;
    private $notifier;
    private $redactor;
    private $lastProcessedTime = [];
    
    public function __construct($config = []) {
        $this->config = $config;
        
        // Initialize components
        $this->intentDetector = new IntentDetector($config);
        $this->entityExtractor = new EntityExtractor($config);
        $this->leadScorer = new LeadScorer($config);
        $this->redactor = new Redactor($config);
        $this->leadRepository = new LeadRepository($config);
        $this->notifier = new Notifier($config, $this->redactor);
    }
    
    /**
     * Check if LeadSense is enabled
     * 
     * @return bool
     */
    public function isEnabled() {
        return $this->config['enabled'] ?? false;
    }
    
    /**
     * Process a conversation turn for lead detection
     * 
     * @param array $turnData Turn context including messages and metadata
     * @return array|null Lead data if detected, null otherwise
     */
    public function processTurn($turnData) {
        // Check if enabled
        if (!$this->isEnabled()) {
            return null;
        }
        
        // Extract required data
        $agentId = $turnData['agent_id'] ?? null;
        $conversationId = $turnData['conversation_id'] ?? null;
        $tenantId = $turnData['tenant_id'] ?? null;

        // Ensure repository uses correct tenant context (or clears previous one)
        $this->leadRepository->setTenantId($tenantId);
        $userMessage = $turnData['user_message'] ?? '';
        $assistantMessage = $turnData['assistant_message'] ?? '';
        
        if (empty($conversationId) || empty($userMessage)) {
            return null;
        }
        
        // Check debounce window to avoid duplicate processing
        if ($this->shouldDebounce($conversationId)) {
            error_log("LeadSense: Debouncing conversation $conversationId");
            return null;
        }
        
        try {
            // Step 1: Detect commercial intent
            $messages = $turnData['messages'] ?? [];
            $intentResult = $this->intentDetector->detect(
                $userMessage,
                $assistantMessage,
                $messages
            );
            
            // If no meaningful intent, skip further processing
            if ($intentResult['intent'] === 'none') {
                return null;
            }
            
            // Step 2: Extract entities
            $context = [
                'messages' => $messages,
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage
            ];
            $entities = $this->entityExtractor->extract($context);
            
            // Step 3: Score the lead
            $scoreResult = $this->leadScorer->score($entities, $intentResult);
            
            // Step 4: Persist the lead
            $leadData = array_merge($entities, [
                'agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'intent_level' => $intentResult['intent'],
                'score' => $scoreResult['score'],
                'qualified' => $scoreResult['qualified'],
                'status' => 'new',
                'source_channel' => $turnData['source_channel'] ?? 'web',
                'tenant_id' => $tenantId,
                'extras' => [
                    'intent_signals' => $intentResult['signals'] ?? [],
                    'intent_confidence' => $intentResult['confidence'] ?? 0,
                    'model' => $turnData['model'] ?? null,
                    'prompt_id' => $turnData['prompt_id'] ?? null
                ]
            ]);
            
            $leadId = $this->leadRepository->createOrUpdateLead($leadData);
            
            // Add event
            $this->leadRepository->addEvent($leadId, 'detected', [
                'intent' => $intentResult,
                'entities' => $entities
            ]);
            
            // Add score snapshot
            $this->leadRepository->addScoreSnapshot(
                $leadId,
                $scoreResult['score'],
                $scoreResult['rationale']
            );
            
            // Step 5: Send notifications if qualified
            if ($scoreResult['qualified']) {
                $this->notifyQualifiedLead($leadId, $leadData, $scoreResult);
            }
            
            // Update debounce timestamp
            $this->updateDebounceTimestamp($conversationId);
            
            // Return lead summary
            return [
                'lead_id' => $leadId,
                'score' => $scoreResult['score'],
                'qualified' => $scoreResult['qualified'],
                'intent_level' => $intentResult['intent']
            ];
            
        } catch (Exception $e) {
            error_log("LeadSense processing error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if conversation should be debounced
     * 
     * @param string $conversationId
     * @return bool
     */
    private function shouldDebounce($conversationId) {
        $debounceWindow = $this->config['debounce_window'] ?? 300; // 5 minutes default
        
        if (!isset($this->lastProcessedTime[$conversationId])) {
            return false;
        }
        
        $elapsed = time() - $this->lastProcessedTime[$conversationId];
        return $elapsed < $debounceWindow;
    }
    
    /**
     * Update debounce timestamp
     * 
     * @param string $conversationId
     */
    private function updateDebounceTimestamp($conversationId) {
        $this->lastProcessedTime[$conversationId] = time();
    }
    
    /**
     * Send notifications for a qualified lead
     * 
     * @param string $leadId
     * @param array $leadData
     * @param array $scoreResult
     */
    private function notifyQualifiedLead($leadId, $leadData, $scoreResult) {
        try {
            // Check daily notification limit
            $tenantId = $leadData['tenant_id'] ?? $this->leadRepository->getTenantId();

            if ($this->hasReachedDailyLimit($tenantId)) {
                error_log("LeadSense: Daily notification limit reached");
                return;
            }
            
            $results = $this->notifier->notifyNewQualifiedLead($leadData, $scoreResult);
            
            // Log notification results
            $this->leadRepository->addEvent($leadId, 'notified', [
                'results' => $results,
                'timestamp' => date('c')
            ]);
            
            // Update qualified event
            $this->leadRepository->addEvent($leadId, 'qualified', [
                'score' => $scoreResult['score'],
                'rationale' => $scoreResult['rationale']
            ]);
            
        } catch (Exception $e) {
            error_log("LeadSense notification error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if daily notification limit has been reached
     * 
     * @return bool
     */
    private function hasReachedDailyLimit($tenantId = null) {
        $maxDaily = $this->config['max_daily_notifications'] ?? 100;

        if ($maxDaily <= 0) {
            return false;
        }

        $currentCount = $this->leadRepository->countDailyNotifiedEvents($tenantId);

        return $currentCount >= $maxDaily;
    }
    
    /**
     * Get follow-up instruction for agent prompts
     * 
     * @return string|null
     */
    public function getFollowUpInstruction() {
        if (!$this->isEnabled()) {
            return null;
        }
        
        $followupEnabled = $this->config['followup_enabled'] ?? true;
        if (!$followupEnabled) {
            return null;
        }
        
        // Default instruction - can be customized via config
        return "When you detect commercial intent (pricing inquiries, trial requests, integration questions), " .
               "naturally ask one brief clarifying question to gather missing information such as the user's " .
               "role, company, or specific needs. Keep your main answer helpful and the question unobtrusive.";
    }
}
