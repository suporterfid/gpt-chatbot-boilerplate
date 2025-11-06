<?php
/**
 * Unit Tests for LeadSense EntityExtractor
 */

require_once __DIR__ . '/../includes/LeadSense/EntityExtractor.php';

class EntityExtractorTest {
    private $extractor;
    private $passed = 0;
    private $failed = 0;
    
    public function __construct() {
        $config = [
            'extractor' => [
                'context_window' => 10,
                'max_tokens' => 1000,
                'max_fields' => 20
            ]
        ];
        $this->extractor = new EntityExtractor($config);
    }
    
    public function run() {
        echo "Running EntityExtractor Tests...\n\n";
        
        $this->testEmailExtraction();
        $this->testPhoneExtraction();
        $this->testRoleExtraction();
        $this->testNameExtraction();
        $this->testCompanyExtraction();
        $this->testCompanySizeExtraction();
        $this->testCompleteProfile();
        
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
    
    private function testEmailExtraction() {
        echo "Test: Email Extraction\n";
        $context = [
            'user_message' => 'My email is john.doe@example.com',
            'assistant_message' => 'Thanks for providing your email.',
            'messages' => []
        ];
        
        $result = $this->extractor->extract($context);
        
        $this->assert(
            $result['email'] === 'john.doe@example.com',
            "Should extract email address"
        );
        echo "\n";
    }
    
    private function testPhoneExtraction() {
        echo "Test: Phone Number Extraction\n";
        $context = [
            'user_message' => 'You can reach me at 555-123-4567',
            'assistant_message' => 'Got it, I will call you.',
            'messages' => []
        ];
        
        $result = $this->extractor->extract($context);
        
        $this->assert(
            $result['phone'] !== null,
            "Should extract phone number"
        );
        $this->assert(
            strpos($result['phone'], '555') !== false,
            "Should contain the correct phone number"
        );
        echo "\n";
    }
    
    private function testRoleExtraction() {
        echo "Test: Role/Title Extraction\n";
        $context = [
            'user_message' => 'I am the CTO of our company.',
            'assistant_message' => 'Great to meet you!',
            'messages' => []
        ];
        
        $result = $this->extractor->extract($context);
        
        $this->assert(
            $result['role'] !== null,
            "Should extract role"
        );
        $this->assert(
            stripos($result['role'], 'CTO') !== false,
            "Should identify CTO role"
        );
        echo "\n";
    }
    
    private function testNameExtraction() {
        echo "Test: Name Extraction\n";
        $context = [
            'user_message' => 'My name is John Smith',
            'assistant_message' => 'Nice to meet you, John!',
            'messages' => []
        ];
        
        $result = $this->extractor->extract($context);
        
        $this->assert(
            $result['name'] !== null,
            "Should extract name"
        );
        $this->assert(
            strpos($result['name'], 'John') !== false,
            "Should contain the correct name"
        );
        echo "\n";
    }
    
    private function testCompanyExtraction() {
        echo "Test: Company Name Extraction\n";
        $context = [
            'user_message' => 'I work at Acme Corporation',
            'assistant_message' => 'Thanks for sharing that.',
            'messages' => []
        ];
        
        $result = $this->extractor->extract($context);
        
        $this->assert(
            $result['company'] !== null,
            "Should extract company name"
        );
        $this->assert(
            strpos($result['company'], 'Acme') !== false,
            "Should contain the correct company name"
        );
        echo "\n";
    }
    
    private function testCompanySizeExtraction() {
        echo "Test: Company Size Extraction\n";
        $context = [
            'user_message' => 'We are an enterprise with 5000 employees',
            'assistant_message' => 'That is a large organization.',
            'messages' => []
        ];
        
        $result = $this->extractor->extract($context);
        
        $this->assert(
            $result['company_size'] !== null,
            "Should detect company size"
        );
        $this->assert(
            $result['company_size'] === 'enterprise',
            "Should categorize as enterprise"
        );
        echo "\n";
    }
    
    private function testCompleteProfile() {
        echo "Test: Complete Profile Extraction\n";
        $context = [
            'user_message' => 'Hi, I am Sarah Johnson, CTO at TechStart Inc. My email is sarah@techstart.com and phone is 555-987-6543. We are a small startup in the SaaS industry.',
            'assistant_message' => 'Thanks for the detailed introduction!',
            'messages' => []
        ];
        
        $result = $this->extractor->extract($context);
        
        $this->assert(
            $result['name'] !== null,
            "Should extract name from complete profile"
        );
        $this->assert(
            $result['role'] !== null,
            "Should extract role from complete profile"
        );
        $this->assert(
            $result['email'] !== null,
            "Should extract email from complete profile"
        );
        $this->assert(
            $result['phone'] !== null,
            "Should extract phone from complete profile"
        );
        $this->assert(
            $result['company'] !== null,
            "Should extract company from complete profile"
        );
        $this->assert(
            $result['industry'] !== null,
            "Should extract industry from complete profile"
        );
        echo "\n";
    }
}

// Run tests
$test = new EntityExtractorTest();
$success = $test->run();
exit($success ? 0 : 1);
