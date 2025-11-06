<?php
/**
 * EntityExtractor - Extracts lead information from conversations
 * 
 * Uses regex patterns and heuristics to extract:
 * - Contact info (email, phone)
 * - Personal info (name, role)
 * - Company info (company name, size, industry)
 * - Interest signals
 */

class EntityExtractor {
    private $config;
    
    public function __construct($config = []) {
        $this->config = $config;
    }
    
    /**
     * Extract lead entities from conversation context
     * 
     * @param array $context Conversation history and current turn
     * @return array Extracted entities
     */
    public function extract($context) {
        $entities = [
            'name' => null,
            'company' => null,
            'role' => null,
            'email' => null,
            'phone' => null,
            'industry' => null,
            'company_size' => null,
            'interest' => null,
            'urgency' => null
        ];
        
        // Combine all text for extraction
        $allText = $this->combineContext($context);
        
        // Extract email
        $entities['email'] = $this->extractEmail($allText);
        
        // Extract phone
        $entities['phone'] = $this->extractPhone($allText);
        
        // Extract role/title
        $entities['role'] = $this->extractRole($allText);
        
        // Extract name (simple heuristic)
        $entities['name'] = $this->extractName($allText, $context);
        
        // Extract company name
        $entities['company'] = $this->extractCompany($allText);
        
        // Extract company size
        $entities['company_size'] = $this->extractCompanySize($allText);
        
        // Extract industry
        $entities['industry'] = $this->extractIndustry($allText);
        
        // Extract interest/project description
        $entities['interest'] = $this->extractInterest($context);
        
        // Extract urgency signals
        $entities['urgency'] = $this->extractUrgency($allText);
        
        return $entities;
    }
    
    /**
     * Combine context messages into searchable text
     */
    private function combineContext($context) {
        $text = '';
        $maxContext = $this->config['extractor']['context_window'] ?? 10;
        
        if (isset($context['messages']) && is_array($context['messages'])) {
            $recentMessages = array_slice($context['messages'], -$maxContext);
            foreach ($recentMessages as $msg) {
                if (isset($msg['content'])) {
                    $text .= ' ' . $msg['content'];
                }
            }
        }
        
        // Include current turn
        if (isset($context['user_message'])) {
            $text .= ' ' . $context['user_message'];
        }
        if (isset($context['assistant_message'])) {
            $text .= ' ' . $context['assistant_message'];
        }
        
        return $text;
    }
    
    /**
     * Extract email address
     */
    private function extractEmail($text) {
        $pattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';
        if (preg_match($pattern, $text, $matches)) {
            return $matches[0];
        }
        return null;
    }
    
    /**
     * Extract phone number
     */
    private function extractPhone($text) {
        // Match various phone formats
        $patterns = [
            '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',           // 123-456-7890
            '/\b\(\d{3}\)\s?\d{3}[-.\s]?\d{4}\b/',           // (123) 456-7890
            '/\b\+\d{1,3}[\s.-]?\d{1,14}\b/',                // International
            '/\b\d{10,11}\b/'                                  // 10-11 digits
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $matches[0];
            }
        }
        return null;
    }
    
    /**
     * Extract role/job title
     */
    private function extractRole($text) {
        $roles = [
            'CTO', 'CEO', 'CFO', 'COO', 'CIO', 'CMO', 'CPO',
            'VP', 'Vice President',
            'Director', 'Head of', 'Manager', 'Lead',
            'Founder', 'Co-Founder', 'Owner', 'President',
            'Engineer', 'Developer', 'Designer', 'Analyst',
            'Consultant', 'Specialist', 'Coordinator'
        ];
        
        foreach ($roles as $role) {
            $pattern = '/\b' . preg_quote($role, '/') . '(?:\s+of\s+[\w\s]+)?/i';
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[0]);
            }
        }
        
        // Check for "I am a/an [role]" pattern
        if (preg_match('/I\s+am\s+(?:a|an|the)\s+([\w\s]+?)(?:\.|,|at|for|\b)/i', $text, $matches)) {
            $role = trim($matches[1]);
            if (strlen($role) < 50) { // Sanity check
                return $role;
            }
        }
        
        return null;
    }
    
    /**
     * Extract name (simple heuristic)
     */
    private function extractName($text, $context) {
        // Look for "My name is [Name]" or "I'm [Name]" patterns
        $patterns = [
            '/(?:my name is|I\'?m|I am)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i',
            '/call me\s+([A-Z][a-z]+)/i',
            '/this is\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                // Filter out common false positives
                $blacklist = ['Customer', 'User', 'Support', 'Team', 'Help'];
                if (!in_array($name, $blacklist) && strlen($name) < 50) {
                    return $name;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract company name
     */
    private function extractCompany($text) {
        // Look for company suffixes
        $suffixes = ['Inc', 'LLC', 'Ltd', 'Corporation', 'Corp', 'Company', 'Co', 'Ltda'];
        $pattern = '/\b([A-Z][A-Za-z0-9&\s]+)\s+(' . implode('|', $suffixes) . ')\.?\b/';
        
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1] . ' ' . $matches[2]);
        }
        
        // Look for "I work at/for [Company]" patterns
        $patterns = [
            '/(?:work at|work for|working at|employed by)\s+([A-Z][A-Za-z0-9&\s]+?)(?:\.|,|\band\b|\bas\b)/i',
            '/(?:company|organization|firm) (?:is|called|named)\s+([A-Z][A-Za-z0-9&\s]+?)(?:\.|,)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $company = trim($matches[1]);
                if (strlen($company) < 50) {
                    return $company;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract company size indicators
     */
    private function extractCompanySize($text) {
        $sizes = [
            'enterprise' => ['enterprise', 'large company', 'Fortune 500', 'multinational'],
            'mid-market' => ['mid-size', 'medium company', 'growing company'],
            'small' => ['small business', 'startup', 'small company', 'SMB', 'small team'],
            'solopreneur' => ['solo', 'freelancer', 'individual', 'one person']
        ];
        
        foreach ($sizes as $size => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    return $size;
                }
            }
        }
        
        // Look for employee count mentions
        if (preg_match('/(\d+)\s+employees?/i', $text, $matches)) {
            $count = (int)$matches[1];
            if ($count > 1000) return 'enterprise';
            if ($count > 100) return 'mid-market';
            if ($count > 10) return 'small';
            return 'solopreneur';
        }
        
        return null;
    }
    
    /**
     * Extract industry
     */
    private function extractIndustry($text) {
        $industries = [
            'technology', 'software', 'SaaS', 'fintech', 'finance', 'banking',
            'healthcare', 'medical', 'education', 'e-commerce', 'retail',
            'manufacturing', 'logistics', 'real estate', 'marketing',
            'consulting', 'legal', 'insurance', 'telecommunications',
            'media', 'entertainment', 'hospitality', 'travel'
        ];
        
        foreach ($industries as $industry) {
            if (stripos($text, $industry) !== false) {
                return $industry;
            }
        }
        
        return null;
    }
    
    /**
     * Extract interest/project description
     */
    private function extractInterest($context) {
        $interest = '';
        
        // Look at user messages to build interest description
        if (isset($context['messages']) && is_array($context['messages'])) {
            $userMessages = array_filter($context['messages'], function($msg) {
                return isset($msg['role']) && $msg['role'] === 'user';
            });
            
            // Take last 3 user messages as interest summary
            $recentUserMessages = array_slice($userMessages, -3);
            foreach ($recentUserMessages as $msg) {
                if (isset($msg['content'])) {
                    $interest .= $msg['content'] . ' ';
                }
            }
        }
        
        // Include current user message
        if (isset($context['user_message'])) {
            $interest .= $context['user_message'];
        }
        
        // Truncate to reasonable length
        $interest = trim($interest);
        if (strlen($interest) > 500) {
            $interest = substr($interest, 0, 497) . '...';
        }
        
        return !empty($interest) ? $interest : null;
    }
    
    /**
     * Extract urgency indicators
     */
    private function extractUrgency($text) {
        $urgencyKeywords = [
            'urgent', 'asap', 'immediately', 'right now', 'soon',
            'quickly', 'deadline', 'time sensitive', 'emergency'
        ];
        
        foreach ($urgencyKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return 'high';
            }
        }
        
        $moderateKeywords = ['need', 'want', 'looking for', 'interested in'];
        foreach ($moderateKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return 'medium';
            }
        }
        
        return 'low';
    }
}
