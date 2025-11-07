<?php
/**
 * Test Compliance Features
 * Tests ConsentService and WhatsAppTemplateService
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/ConsentService.php';
require_once __DIR__ . '/../includes/WhatsAppTemplateService.php';

// Color output
function colorize($text, $color) {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function pass($msg) { echo colorize("✓ PASS: $msg\n", 'green'); }
function fail($msg) { echo colorize("✗ FAIL: $msg\n", 'red'); }
function info($msg) { echo colorize("ℹ $msg\n", 'blue'); }
function title($msg) { echo colorize("\n=== $msg ===\n", 'yellow'); }

// Initialize
$passed = 0;
$failed = 0;

title("Compliance Features Test Suite");

try {
    // Setup test database
    $config['storage']['database_path'] = __DIR__ . '/../data/test_compliance.db';
    $db = new DB($config['storage']);
    
    // Run migrations
    info("Running migrations...");
    $db->runMigrations(__DIR__ . '/../db/migrations');
    pass("Migrations complete");
    
    // Initialize services
    $consentService = new ConsentService($db, 'test-tenant-id');
    $templateService = new WhatsAppTemplateService($db, 'test-tenant-id');
    
    // Test data
    $agentId = 'test-agent-' . uniqid();
    $channel = 'whatsapp';
    $externalUserId = '+5511999999999';
    
    // ===== Consent Service Tests =====
    
    title("ConsentService Tests");
    
    // Test 1: Grant consent
    info("Test 1: Grant consent");
    $consent = $consentService->grantConsent($agentId, $channel, $externalUserId, [
        'consent_type' => 'service',
        'consent_method' => 'first_contact',
        'consent_text' => 'User started conversation',
        'ip_address' => '192.168.1.1'
    ]);
    
    if ($consent && $consent['consent_status'] === 'granted') {
        pass("Consent granted successfully");
        $passed++;
    } else {
        fail("Failed to grant consent");
        $failed++;
    }
    
    // Test 2: Check consent
    info("Test 2: Check consent status");
    $hasConsent = $consentService->hasConsent($agentId, $channel, $externalUserId, 'service');
    if ($hasConsent === true) {
        pass("Consent check returned true");
        $passed++;
    } else {
        fail("Consent check failed");
        $failed++;
    }
    
    // Test 3: Get consent record
    info("Test 3: Get consent record");
    $retrievedConsent = $consentService->getConsent($agentId, $channel, $externalUserId, 'service');
    if ($retrievedConsent && $retrievedConsent['id'] === $consent['id']) {
        pass("Consent record retrieved");
        $passed++;
    } else {
        fail("Failed to retrieve consent");
        $failed++;
    }
    
    // Test 4: Withdraw consent
    info("Test 4: Withdraw consent");
    $withdrawn = $consentService->withdrawConsent($agentId, $channel, $externalUserId, 'service', [
        'reason' => 'User sent STOP'
    ]);
    if ($withdrawn && $withdrawn['consent_status'] === 'withdrawn') {
        pass("Consent withdrawn successfully");
        $passed++;
    } else {
        fail("Failed to withdraw consent");
        $failed++;
    }
    
    // Test 5: Check consent after withdrawal
    info("Test 5: Check consent after withdrawal");
    $hasConsentAfter = $consentService->hasConsent($agentId, $channel, $externalUserId, 'service');
    if ($hasConsentAfter === false) {
        pass("Consent check returned false after withdrawal");
        $passed++;
    } else {
        fail("Consent check failed after withdrawal");
        $failed++;
    }
    
    // Test 6: Get consent audit history
    info("Test 6: Get consent audit history");
    $auditHistory = $consentService->getConsentAuditHistory($consent['id']);
    if (is_array($auditHistory) && count($auditHistory) >= 2) {
        pass("Audit history retrieved (entries: " . count($auditHistory) . ")");
        $passed++;
    } else {
        fail("Failed to retrieve audit history");
        $failed++;
    }
    
    // Test 7: Process opt-out keyword
    info("Test 7: Process opt-out keyword");
    
    // First grant consent again
    $consentService->grantConsent($agentId, $channel, $externalUserId, ['consent_type' => 'service']);
    
    $result = $consentService->processConsentKeyword($agentId, $channel, $externalUserId, 'STOP');
    if ($result['action'] === 'opt_out') {
        pass("Opt-out keyword processed");
        $passed++;
    } else {
        fail("Failed to process opt-out keyword");
        $failed++;
    }
    
    // Test 8: Process opt-in keyword
    info("Test 8: Process opt-in keyword");
    $result = $consentService->processConsentKeyword($agentId, $channel, $externalUserId, 'START');
    if ($result['action'] === 'opt_in') {
        pass("Opt-in keyword processed");
        $passed++;
    } else {
        fail("Failed to process opt-in keyword");
        $failed++;
    }
    
    // Test 9: List consents
    info("Test 9: List consents");
    $consents = $consentService->listConsents(['agent_id' => $agentId]);
    if (is_array($consents) && count($consents) > 0) {
        pass("Consents listed (" . count($consents) . " records)");
        $passed++;
    } else {
        fail("Failed to list consents");
        $failed++;
    }
    
    // ===== WhatsApp Template Service Tests =====
    
    title("WhatsAppTemplateService Tests");
    
    // Test 10: Create template
    info("Test 10: Create template");
    $template = $templateService->createTemplate([
        'template_name' => 'welcome_message',
        'template_category' => 'UTILITY',
        'language_code' => 'en',
        'content_text' => 'Hi {{1}}! Welcome to {{2}}. Reply STOP to opt out.',
        'agent_id' => $agentId,
        'header_text' => 'Welcome',
        'footer_text' => 'Powered by AI',
        'variables' => [
            ['position' => 1, 'example' => 'John'],
            ['position' => 2, 'example' => 'Acme Corp']
        ]
    ]);
    
    if ($template && $template['status'] === 'draft') {
        pass("Template created successfully");
        $passed++;
    } else {
        fail("Failed to create template");
        $failed++;
    }
    
    $templateId = $template['id'];
    
    // Test 11: Get template
    info("Test 11: Get template");
    $retrievedTemplate = $templateService->getTemplate($templateId);
    if ($retrievedTemplate && $retrievedTemplate['id'] === $templateId) {
        pass("Template retrieved");
        $passed++;
    } else {
        fail("Failed to retrieve template");
        $failed++;
    }
    
    // Test 12: Update template
    info("Test 12: Update template");
    $updatedTemplate = $templateService->updateTemplate($templateId, [
        'footer_text' => 'Powered by AI - Updated'
    ]);
    if ($updatedTemplate && $updatedTemplate['footer_text'] === 'Powered by AI - Updated') {
        pass("Template updated");
        $passed++;
    } else {
        fail("Failed to update template");
        $failed++;
    }
    
    // Test 13: Submit template
    info("Test 13: Submit template");
    $submittedTemplate = $templateService->submitTemplate($templateId);
    if ($submittedTemplate && $submittedTemplate['status'] === 'pending') {
        pass("Template submitted");
        $passed++;
    } else {
        fail("Failed to submit template");
        $failed++;
    }
    
    // Test 14: Approve template
    info("Test 14: Approve template");
    $approvedTemplate = $templateService->approveTemplate($templateId, 'whatsapp-template-123', 'HIGH');
    if ($approvedTemplate && $approvedTemplate['status'] === 'approved') {
        pass("Template approved");
        $passed++;
    } else {
        fail("Failed to approve template");
        $failed++;
    }
    
    // Test 15: Get template by name
    info("Test 15: Get template by name");
    $templateByName = $templateService->getTemplateByName('welcome_message', 'en');
    if ($templateByName && $templateByName['id'] === $templateId) {
        pass("Template retrieved by name");
        $passed++;
    } else {
        fail("Failed to retrieve template by name");
        $failed++;
    }
    
    // Test 16: Render template
    info("Test 16: Render template");
    $rendered = $templateService->renderTemplate($templateId, ['John', 'Acme Corp']);
    if ($rendered && strpos($rendered['content'], 'Hi John!') !== false) {
        pass("Template rendered correctly");
        $passed++;
    } else {
        fail("Failed to render template");
        $failed++;
    }
    
    // Test 17: Log template usage
    info("Test 17: Log template usage");
    $usageId = $templateService->logTemplateUsage($templateId, $agentId, $externalUserId, ['John', 'Acme Corp']);
    if ($usageId) {
        pass("Template usage logged");
        $passed++;
    } else {
        fail("Failed to log template usage");
        $failed++;
    }
    
    // Test 18: Get template stats
    info("Test 18: Get template stats");
    $stats = $templateService->getTemplateStats($templateId);
    if ($stats && $stats['total_sent'] > 0) {
        pass("Template stats retrieved (sent: {$stats['total_sent']})");
        $passed++;
    } else {
        fail("Failed to retrieve template stats");
        $failed++;
    }
    
    // Test 19: List templates
    info("Test 19: List templates");
    $templates = $templateService->listTemplates(['status' => 'approved']);
    if (is_array($templates) && count($templates) > 0) {
        pass("Templates listed (" . count($templates) . " records)");
        $passed++;
    } else {
        fail("Failed to list templates");
        $failed++;
    }
    
    // Test 20: Create rejected template and delete
    info("Test 20: Create and delete rejected template");
    $rejectedTemplate = $templateService->createTemplate([
        'template_name' => 'test_delete',
        'template_category' => 'UTILITY',
        'language_code' => 'en',
        'content_text' => 'Test message',
        'agent_id' => $agentId
    ]);
    
    $result = $templateService->deleteTemplate($rejectedTemplate['id']);
    if ($result && $result['success']) {
        pass("Template deleted");
        $passed++;
    } else {
        fail("Failed to delete template");
        $failed++;
    }
    
    // Summary
    title("Test Summary");
    echo "Passed: " . colorize($passed, 'green') . "\n";
    echo "Failed: " . colorize($failed, 'red') . "\n";
    echo "Total:  " . ($passed + $failed) . "\n";
    
    if ($failed === 0) {
        echo "\n" . colorize("✓ All tests passed!", 'green') . "\n";
        exit(0);
    } else {
        echo "\n" . colorize("✗ Some tests failed", 'red') . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    fail("Test suite failed: " . $e->getMessage());
    echo $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    // Cleanup test database
    if (isset($config['storage']['database_path']) && file_exists($config['storage']['database_path'])) {
        @unlink($config['storage']['database_path']);
    }
}
