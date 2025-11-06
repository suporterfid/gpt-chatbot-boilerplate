<?php
/**
 * LeadScorer - Scores and qualifies leads based on extracted data
 * 
 * Uses configurable rules-based scoring to assess lead quality
 */

class LeadScorer {
    private $config;
    
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    /**
     * Score a lead based on entities and intent
     * 
     * @param array $entities Extracted lead entities
     * @param array $intent Intent detection results
     * @return array ['score' => 0..100, 'rationale' => [...], 'qualified' => bool]
     */
    public function score($entities, $intent) {
        $score = 0;
        $rationale = [];
        $weights = $this->getWeights();
        
        // Base score from intent level
        $intentLevel = $intent['intent'] ?? 'none';
        switch ($intentLevel) {
            case 'high':
                $score += $weights['intent_high'];
                $rationale[] = [
                    'factor' => 'High commercial intent',
                    'points' => $weights['intent_high'],
                    'signals' => $intent['signals'] ?? []
                ];
                break;
            case 'medium':
                $score += $weights['intent_medium'];
                $rationale[] = [
                    'factor' => 'Medium commercial intent',
                    'points' => $weights['intent_medium']
                ];
                break;
            case 'low':
                $score += $weights['intent_low'];
                $rationale[] = [
                    'factor' => 'Low commercial intent',
                    'points' => $weights['intent_low']
                ];
                break;
        }
        
        // Decision maker bonus
        if ($this->isDecisionMaker($entities['role'] ?? null)) {
            $score += $weights['decision_maker'];
            $rationale[] = [
                'factor' => 'Decision maker role: ' . ($entities['role'] ?? 'detected'),
                'points' => $weights['decision_maker']
            ];
        }
        
        // Active project/urgency bonus
        if (isset($entities['urgency']) && in_array($entities['urgency'], ['high', 'medium'])) {
            $score += $weights['urgency'];
            $rationale[] = [
                'factor' => 'Active project with ' . $entities['urgency'] . ' urgency',
                'points' => $weights['urgency']
            ];
        }
        
        // ICP (Ideal Customer Profile) fit bonus
        $icpScore = $this->assessICPFit($entities);
        if ($icpScore > 0) {
            $score += $icpScore;
            $rationale[] = [
                'factor' => 'ICP fit (company size/industry match)',
                'points' => $icpScore
            ];
        }
        
        // Contactability - must have email or phone
        $hasContact = !empty($entities['email']) || !empty($entities['phone']);
        if (!$hasContact) {
            $score += $weights['no_contact'];
            $rationale[] = [
                'factor' => 'No contact information provided',
                'points' => $weights['no_contact']
            ];
        } else {
            $contactPoints = 5;
            $score += $contactPoints;
            $rationale[] = [
                'factor' => 'Contact information available',
                'points' => $contactPoints
            ];
        }
        
        // Company information bonus
        if (!empty($entities['company'])) {
            $companyPoints = 5;
            $score += $companyPoints;
            $rationale[] = [
                'factor' => 'Company identified: ' . $entities['company'],
                'points' => $companyPoints
            ];
        }
        
        // Ensure score is within bounds
        $score = max(0, min(100, $score));
        
        // Determine if qualified
        $scoreThreshold = $this->config['score_threshold'] ?? 70;
        $qualified = $score >= $scoreThreshold;
        
        return [
            'score' => $score,
            'rationale' => $rationale,
            'qualified' => $qualified,
            'threshold' => $scoreThreshold
        ];
    }
    
    /**
     * Get scoring weights from config
     * 
     * @return array
     */
    private function getWeights() {
        $defaults = [
            'intent_low' => 20,
            'intent_medium' => 50,
            'intent_high' => 75,
            'decision_maker' => 15,
            'active_project' => 10,
            'icp_fit' => 10,
            'urgency' => 10,
            'no_contact' => -10,
        ];
        
        if (isset($this->config['scoring']['weights'])) {
            return array_merge($defaults, $this->config['scoring']['weights']);
        }
        
        return $defaults;
    }
    
    /**
     * Check if role indicates decision maker
     * 
     * @param string|null $role
     * @return bool
     */
    private function isDecisionMaker($role) {
        if (empty($role)) {
            return false;
        }
        
        $role = strtolower($role);
        $decisionMakerKeywords = [
            'cto', 'ceo', 'cfo', 'coo', 'cio', 'cmo', 'cpo',
            'vp', 'vice president', 'director', 'head of',
            'founder', 'co-founder', 'owner', 'president'
        ];
        
        foreach ($decisionMakerKeywords as $keyword) {
            if (strpos($role, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Assess Ideal Customer Profile fit
     * 
     * @param array $entities
     * @return int Score adjustment
     */
    private function assessICPFit($entities) {
        $score = 0;
        
        // Prefer enterprise and mid-market
        $companySize = $entities['company_size'] ?? null;
        if (in_array($companySize, ['enterprise', 'mid-market'])) {
            $score += 10;
        } elseif ($companySize === 'small') {
            $score += 5;
        }
        
        // Prefer certain industries (can be customized)
        $preferredIndustries = ['technology', 'software', 'SaaS', 'fintech', 'healthcare'];
        $industry = $entities['industry'] ?? null;
        if ($industry && in_array(strtolower($industry), $preferredIndustries)) {
            $score += 5;
        }
        
        return $score;
    }
    
    /**
     * Score using ML model (future implementation)
     * 
     * @param array $payload
     * @return array
     */
    public function scoreML($payload) {
        // Stub for future ML implementation
        // Would call external ML endpoint with lead data
        throw new Exception('ML scoring not yet implemented');
    }
}
