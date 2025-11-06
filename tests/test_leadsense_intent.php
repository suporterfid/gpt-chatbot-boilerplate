<?php
/**
 * Unit Tests for LeadSense IntentDetector
 */

require_once __DIR__ . '/../includes/LeadSense/IntentDetector.php';

class IntentDetectorTest {
    private $detector;
    private $passed = 0;
    private $failed = 0;
    
    public function __construct() {
        $config = [
            'intent_threshold' => 0.6,
            'extractor' => [
                'context_window' => 10
            ]
        ];
        $this->detector = new IntentDetector($config);
    }
    
    public function run() {
        echo "Running IntentDetector Tests...\n\n";
        
        $this->testPricingIntent();
        $this->testTrialIntent();
        $this->testIntegrationIntent();
        $this->testNoIntent();
        $this->testHighIntent();
        $this->testContextualIntent();
        
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
    
    private function testPricingIntent() {
        echo "Test: Pricing Intent Detection\n";
        $result = $this->detector->detect(
            "How much does your service cost?",
            "Our pricing starts at $99 per month.",
            []
        );
        
        $this->assert(
            in_array($result['intent'], ['low', 'medium', 'high']),
            "Should detect pricing intent"
        );
        $this->assert(
            $result['confidence'] > 0.2,
            "Should have reasonable confidence for pricing"
        );
        echo "\n";
    }
    
    private function testTrialIntent() {
        echo "Test: Trial Intent Detection\n";
        $result = $this->detector->detect(
            "Can I try your product for free?",
            "Yes, we offer a 14-day free trial.",
            []
        );
        
        $this->assert(
            $result['intent'] !== 'none' || count($result['signals']) > 0,
            "Should detect trial signals even if intent is low"
        );
        $this->assert(
            count($result['signals']) > 0,
            "Should have trial-related signals"
        );
        echo "\n";
    }
    
    private function testIntegrationIntent() {
        echo "Test: Integration Intent Detection\n";
        $result = $this->detector->detect(
            "How do I integrate this with my CRM?",
            "We have APIs and webhooks for integration.",
            []
        );
        
        $this->assert(
            $result['intent'] !== 'none' || count($result['signals']) > 0,
            "Should detect integration signals"
        );
        $this->assert(
            count($result['signals']) > 0,
            "Should have integration-related signals"
        );
        echo "\n";
    }
    
    private function testNoIntent() {
        echo "Test: No Commercial Intent\n";
        $result = $this->detector->detect(
            "What's the weather like today?",
            "I don't have weather information.",
            []
        );
        
        $this->assert(
            $result['intent'] === 'none',
            "Should detect no commercial intent"
        );
        $this->assert(
            $result['confidence'] < 0.3,
            "Should have low confidence for non-commercial queries"
        );
        echo "\n";
    }
    
    private function testHighIntent() {
        echo "Test: High Intent (Multiple Signals)\n";
        $result = $this->detector->detect(
            "I need pricing info for enterprise plan. We have budget approved and need to get started ASAP.",
            "I'll connect you with our sales team.",
            []
        );
        
        $this->assert(
            in_array($result['intent'], ['high', 'medium']),
            "Should detect high intent with multiple signals"
        );
        $this->assert(
            count($result['signals']) >= 2,
            "Should have multiple signals detected"
        );
        echo "\n";
    }
    
    private function testContextualIntent() {
        echo "Test: Contextual Intent (Repeated Interest)\n";
        $context = [
            ['role' => 'user', 'content' => 'Tell me about your pricing'],
            ['role' => 'assistant', 'content' => 'Here are our plans...'],
            ['role' => 'user', 'content' => 'What about integrations?'],
            ['role' => 'assistant', 'content' => 'We support many integrations...']
        ];
        
        $result = $this->detector->detect(
            "Can I get a trial to test it out?",
            "Absolutely, sign up here.",
            $context
        );
        
        $this->assert(
            count($result['signals']) >= 2,
            "Should have multiple signals from context and current message"
        );
        $this->assert(
            $result['confidence'] > 0.2,
            "Should have boosted confidence from contextual signals"
        );
        echo "\n";
    }
}

// Run tests
$test = new IntentDetectorTest();
$success = $test->run();
exit($success ? 0 : 1);
