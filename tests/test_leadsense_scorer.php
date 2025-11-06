<?php
/**
 * Unit Tests for LeadSense LeadScorer
 */

require_once __DIR__ . '/../includes/LeadSense/LeadScorer.php';

class LeadScorerTest {
    private $scorer;
    private $passed = 0;
    private $failed = 0;
    
    public function __construct() {
        $config = [
            'score_threshold' => 70,
            'scoring' => [
                'mode' => 'rules',
                'weights' => [
                    'intent_low' => 20,
                    'intent_medium' => 50,
                    'intent_high' => 75,
                    'decision_maker' => 15,
                    'active_project' => 10,
                    'icp_fit' => 10,
                    'urgency' => 10,
                    'no_contact' => -10,
                ]
            ]
        ];
        $this->scorer = new LeadScorer($config);
    }
    
    public function run() {
        echo "Running LeadScorer Tests...\n\n";
        
        $this->testBasicScoring();
        $this->testDecisionMakerBonus();
        $this->testNoContactPenalty();
        $this->testQualifiedLead();
        $this->testUnqualifiedLead();
        $this->testICPFit();
        
        echo "\n=================================\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "=================================\n";
        
        return $this->failed === 0;
    }
    
    private function assert($condition, $message) {
        if ($condition) {
            echo "✓ $message\n";
            $this->passed++;
        } else {
            echo "✗ $message\n";
            $this->failed++;
        }
    }
    
    private function testBasicScoring() {
        echo "Test: Basic Scoring with Medium Intent\n";
        $entities = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'Developer',
            'company' => 'Example Inc'
        ];
        $intent = ['intent' => 'medium', 'confidence' => 0.7];
        
        $result = $this->scorer->score($entities, $intent);
        
        $this->assert(
            $result['score'] > 0,
            "Should calculate a positive score"
        );
        $this->assert(
            isset($result['rationale']) && is_array($result['rationale']),
            "Should provide scoring rationale"
        );
        $this->assert(
            isset($result['qualified']) && is_bool($result['qualified']),
            "Should indicate qualification status"
        );
        echo "\n";
    }
    
    private function testDecisionMakerBonus() {
        echo "Test: Decision Maker Bonus\n";
        $entitiesRegular = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'Developer',
        ];
        $entitiesCTO = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'role' => 'CTO',
        ];
        $intent = ['intent' => 'medium', 'confidence' => 0.7];
        
        $resultRegular = $this->scorer->score($entitiesRegular, $intent);
        $resultCTO = $this->scorer->score($entitiesCTO, $intent);
        
        $this->assert(
            $resultCTO['score'] > $resultRegular['score'],
            "CTO should score higher than regular developer"
        );
        echo "\n";
    }
    
    private function testNoContactPenalty() {
        echo "Test: No Contact Information Penalty\n";
        $entitiesWithContact = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
        $entitiesNoContact = [
            'name' => 'John Doe'
        ];
        $intent = ['intent' => 'medium', 'confidence' => 0.7];
        
        $resultWith = $this->scorer->score($entitiesWithContact, $intent);
        $resultWithout = $this->scorer->score($entitiesNoContact, $intent);
        
        $this->assert(
            $resultWith['score'] > $resultWithout['score'],
            "Lead with contact info should score higher"
        );
        echo "\n";
    }
    
    private function testQualifiedLead() {
        echo "Test: Qualified Lead (High Score)\n";
        $entities = [
            'name' => 'Sarah Johnson',
            'email' => 'sarah@techcorp.com',
            'phone' => '555-1234',
            'role' => 'VP of Engineering',
            'company' => 'TechCorp Inc',
            'company_size' => 'enterprise',
            'industry' => 'technology',
            'urgency' => 'high'
        ];
        $intent = ['intent' => 'high', 'confidence' => 0.9];
        
        $result = $this->scorer->score($entities, $intent);
        
        $this->assert(
            $result['qualified'] === true,
            "Should qualify as a lead with high score"
        );
        $this->assert(
            $result['score'] >= 70,
            "Should meet or exceed threshold (70)"
        );
        echo "\n";
    }
    
    private function testUnqualifiedLead() {
        echo "Test: Unqualified Lead (Low Score)\n";
        $entities = [
            'name' => 'Someone',
        ];
        $intent = ['intent' => 'low', 'confidence' => 0.3];
        
        $result = $this->scorer->score($entities, $intent);
        
        $this->assert(
            $result['qualified'] === false,
            "Should not qualify with low intent and minimal data"
        );
        $this->assert(
            $result['score'] < 70,
            "Should score below threshold"
        );
        echo "\n";
    }
    
    private function testICPFit() {
        echo "Test: ICP Fit Bonus\n";
        $entitiesNonICP = [
            'email' => 'test@example.com',
            'company_size' => 'solopreneur',
            'industry' => 'retail'
        ];
        $entitiesICP = [
            'email' => 'test@example.com',
            'company_size' => 'enterprise',
            'industry' => 'technology'
        ];
        $intent = ['intent' => 'medium', 'confidence' => 0.6];
        
        $resultNonICP = $this->scorer->score($entitiesNonICP, $intent);
        $resultICP = $this->scorer->score($entitiesICP, $intent);
        
        $this->assert(
            $resultICP['score'] > $resultNonICP['score'],
            "ICP-fit lead should score higher"
        );
        echo "\n";
    }
}

// Run tests
$test = new LeadScorerTest();
$success = $test->run();
exit($success ? 0 : 1);
