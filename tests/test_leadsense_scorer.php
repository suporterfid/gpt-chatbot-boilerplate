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
        $this->testMLScoringSuccess();
        $this->testMLScoringFallback();
        
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

    private function testMLScoringSuccess() {
        echo "Test: ML Scoring Success\n";

        $mockClient = function ($endpoint, $payload, $headers) {
            return [
                'status' => 200,
                'body' => json_encode([
                    'score' => 85,
                    'qualified' => true,
                    'rationale' => [
                        ['factor' => 'ml_model', 'points' => 85],
                    ],
                ]),
            ];
        };

        $scorer = new LeadScorer([
            'score_threshold' => 70,
            'scoring' => [
                'mode' => 'ml',
            ],
            'ml' => [
                'endpoint' => 'https://ml.example.com/score',
                'api_key' => 'secret',
                'http_client' => $mockClient,
            ],
        ]);

        $entities = [
            'email' => 'ml@example.com',
            'company' => 'ML Corp',
        ];
        $intent = [
            'intent' => 'high',
            'confidence' => 0.95,
            'signals' => ['intent:demo_request'],
        ];

        $result = $scorer->score($entities, $intent);

        $this->assert(
            $result['score'] >= 80,
            "Should return score from ML response"
        );
        $this->assert(
            $result['qualified'] === true,
            "Should use ML qualified flag"
        );
        $this->assert(
            is_array($result['rationale']) && count($result['rationale']) === 1,
            "Should normalize ML rationale into an array"
        );
        echo "\n";
    }

    private function testMLScoringFallback() {
        echo "Test: ML Scoring Fallback to Rules\n";

        $mockClient = function () {
            throw new Exception('Simulated network failure');
        };

        $mlConfig = [
            'score_threshold' => 70,
            'scoring' => [
                'mode' => 'ml',
            ],
            'ml' => [
                'endpoint' => 'https://ml.example.com/score',
                'api_key' => 'secret',
                'http_client' => $mockClient,
            ],
        ];

        $entities = [
            'email' => 'fallback@example.com',
            'role' => 'Developer',
        ];
        $intent = [
            'intent' => 'medium',
            'confidence' => 0.7,
        ];

        $fallbackScorer = new LeadScorer($mlConfig);
        $fallbackResult = $fallbackScorer->score($entities, $intent);

        $rulesScorer = new LeadScorer([
            'score_threshold' => 70,
            'scoring' => [
                'mode' => 'rules',
            ],
        ]);
        $expected = $rulesScorer->score($entities, $intent);

        $this->assert(
            $fallbackResult['score'] === $expected['score'],
            "Should fallback to rules-based score when ML fails"
        );
        $this->assert(
            $fallbackResult['qualified'] === $expected['qualified'],
            "Should fallback to rules-based qualification when ML fails"
        );
        echo "\n";
    }
}

// Run tests
$test = new LeadScorerTest();
$success = $test->run();
exit($success ? 0 : 1);
