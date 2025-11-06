<?php
/**
 * IntentDetector - Detects commercial intent in conversations
 * 
 * Uses keyword-based heuristics to identify signals of commercial interest
 * such as pricing inquiries, trial requests, integration questions, etc.
 */

class IntentDetector {
    private $config;
    
    // Commercial intent keywords organized by category
    private $intentPatterns = [
        'pricing' => [
            'price', 'pricing', 'cost', 'how much', 'pricing plan', 'subscription',
            'payment', 'billing', 'budget', 'quote', 'estimate', 'affordable',
            'expensive', 'cheap', 'discount', 'promo', 'promotion'
        ],
        'trial' => [
            'trial', 'demo', 'test', 'try', 'evaluate', 'evaluation', 'pilot',
            'proof of concept', 'poc', 'sandbox', 'free tier'
        ],
        'integration' => [
            'integrate', 'integration', 'api', 'webhook', 'connect', 'sync',
            'setup', 'configure', 'implement', 'deploy', 'onboard', 'migration'
        ],
        'decision_makers' => [
            'cto', 'ceo', 'cfo', 'vp', 'director', 'head of', 'manager',
            'decision maker', 'team lead', 'president', 'founder', 'owner'
        ],
        'evaluation' => [
            'compare', 'comparison', 'alternative', 'versus', 'vs', 'better than',
            'competitor', 'review', 'pros and cons', 'features', 'capabilities'
        ],
        'urgency' => [
            'urgent', 'asap', 'immediately', 'right now', 'soon', 'quickly',
            'deadline', 'time sensitive', 'emergency', 'critical'
        ],
        'commitment' => [
            'contract', 'agreement', 'sign up', 'purchase', 'buy', 'order',
            'commit', 'start', 'begin', 'go ahead', 'proceed', 'approved'
        ],
        'budget' => [
            'budget', 'funding', 'approved', 'allocated', 'investment',
            'spend', 'roi', 'return on investment', 'value'
        ]
    ];
    
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    /**
     * Detect commercial intent in a conversation turn
     * 
     * @param string $userText User's message
     * @param string $assistantText Assistant's response
     * @param array $context Previous conversation context
     * @return array ['intent' => 'none|low|medium|high', 'signals' => [], 'confidence' => 0..1]
     */
    public function detect($userText, $assistantText, $context = []) {
        $signals = [];
        $score = 0;
        
        // Combine texts for analysis
        $combinedText = strtolower($userText . ' ' . $assistantText);
        
        // Check each pattern category
        foreach ($this->intentPatterns as $category => $keywords) {
            $matches = [];
            foreach ($keywords as $keyword) {
                if (strpos($combinedText, strtolower($keyword)) !== false) {
                    $matches[] = $keyword;
                }
            }
            
            if (!empty($matches)) {
                $categoryWeight = $this->getCategoryWeight($category);
                $score += $categoryWeight;
                $signals[] = [
                    'category' => $category,
                    'keywords' => $matches,
                    'weight' => $categoryWeight
                ];
            }
        }
        
        // Adjust score based on context (previous turns)
        if (!empty($context)) {
            $contextScore = $this->analyzeContext($context);
            $score += $contextScore;
            if ($contextScore > 0) {
                $signals[] = [
                    'category' => 'context',
                    'keywords' => ['repeated_interest'],
                    'weight' => $contextScore
                ];
            }
        }
        
        // Normalize confidence to 0-1 range
        $confidence = min(1.0, $score / 100);
        
        // Determine intent level
        $intentLevel = $this->getIntentLevel($confidence);
        
        return [
            'intent' => $intentLevel,
            'signals' => $signals,
            'confidence' => round($confidence, 2),
            'raw_score' => $score
        ];
    }
    
    /**
     * Get weight for a specific category
     * 
     * @param string $category
     * @return int
     */
    private function getCategoryWeight($category) {
        $weights = [
            'pricing' => 25,
            'trial' => 20,
            'integration' => 20,
            'decision_makers' => 15,
            'evaluation' => 15,
            'urgency' => 10,
            'commitment' => 30,
            'budget' => 25
        ];
        
        return $weights[$category] ?? 10;
    }
    
    /**
     * Analyze conversation context for repeated signals
     * 
     * @param array $context Array of previous messages
     * @return int Additional score based on context
     */
    private function analyzeContext($context) {
        $score = 0;
        $maxContext = $this->config['extractor']['context_window'] ?? 10;
        
        // Limit context analysis to recent messages
        $recentContext = array_slice($context, -$maxContext);
        
        // Count commercial signals in context
        $contextText = '';
        foreach ($recentContext as $msg) {
            if (isset($msg['content'])) {
                $contextText .= ' ' . strtolower($msg['content']);
            }
        }
        
        // Add bonus for repeated interest
        $commercialKeywordCount = 0;
        foreach ($this->intentPatterns as $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($contextText, strtolower($keyword)) !== false) {
                    $commercialKeywordCount++;
                }
            }
        }
        
        // Score based on context depth
        if ($commercialKeywordCount > 5) {
            $score = 20; // Strong repeated interest
        } elseif ($commercialKeywordCount > 2) {
            $score = 10; // Moderate repeated interest
        }
        
        return $score;
    }
    
    /**
     * Map confidence score to intent level
     * 
     * @param float $confidence
     * @return string 'none', 'low', 'medium', or 'high'
     */
    private function getIntentLevel($confidence) {
        $threshold = $this->config['intent_threshold'] ?? 0.6;
        
        if ($confidence < 0.3) {
            return 'none';
        } elseif ($confidence < $threshold) {
            return 'low';
        } elseif ($confidence < 0.8) {
            return 'medium';
        } else {
            return 'high';
        }
    }
}
