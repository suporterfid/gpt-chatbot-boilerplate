#!/usr/bin/env php
<?php
/**
 * Test Consent and WhatsApp Template Services
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/ConsentService.php';
require_once __DIR__ . '/../includes/WhatsAppTemplateService.php';
require_once __DIR__ . '/../includes/TenantService.php';
require_once __DIR__ . '/../includes/AgentService.php';

function testHeader($title) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 60) . "\n\n";
}

function testPass($message) {
    echo "✓ PASS: $message\n";
}

function testFail($message) {
    echo "✗ FAIL: $message\n";
    exit(1);
}

// Initialize
$db = new DB($config['storage']);
$testTenantId = 'test_tenant_' . uniqid();
$testAgentId = 'test_agent_' . uniqid();
$testUserId = '+5511999999999';

testHeader("Setting up test environment");

// Create test tenant
$tenantService = new TenantService($db);
$tenant = $tenantService->createTenant([
    'name' => 'Test Tenant',
    'slug' => 'test-tenant-' . uniqid(),
    'status' => 'active',
    'billing_email' => 'test@example.com'
]);
$testTenantId = $tenant['id'];
testPass("Created test tenant: {$testTenantId}");

// Create test agent
$agentService = new AgentService($db, $testTenantId);
$agent = $agentService->createAgent([
    'name' => 'Test Agent',
    'api_type' => 'responses',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7
]);
$testAgentId = $agent['id'];
testPass("Created test agent: {$testAgentId}");

// ===== Test Consent Service =====
testHeader("Testing ConsentService");

$consentService = new ConsentService($db, $testTenantId);

// Test 1: Grant consent
echo "Test 1: Grant consent...\n";
$consent = $consentService->grantConsent($testAgentId, 'whatsapp', $testUserId, [
    'consent_type' => 'service',
    'consent_method' => 'first_contact',
    'consent_text' => 'User initiated first contact',
    'ip_address' => '203.0.113.42',
    'legal_basis' => 'legitimate_interest'
]);

if ($consent && $consent['consent_status'] === 'granted') {
    testPass("Consent granted successfully");
} else {
    testFail("Failed to grant consent");
}

// Test 2: Check consent
echo "\nTest 2: Check if user has consent...\n";
$hasConsent = $consentService->hasConsent($testAgentId, 'whatsapp', $testUserId, 'service');
if ($hasConsent === true) {
    testPass("Consent check returned true");
} else {
    testFail("Consent check returned false");
}

// Test 3: Process opt-out keyword
echo "\nTest 3: Process opt-out keyword (STOP)...\n";
$result = $consentService->processConsentKeyword($testAgentId, 'whatsapp', $testUserId, 'STOP', [
    'ip_address' => '203.0.113.42'
]);

if ($result['action'] === 'opt_out') {
    testPass("Opt-out processed successfully");
} else {
    testFail("Opt-out not processed correctly");
}

// Test 4: Check consent after opt-out
echo "\nTest 4: Check consent after opt-out...\n";
$hasConsent = $consentService->hasConsent($testAgentId, 'whatsapp', $testUserId, 'service');
if ($hasConsent === false) {
    testPass("Consent correctly revoked after opt-out");
} else {
    testFail("Consent still active after opt-out");
}

// Test 5: Process opt-in keyword
echo "\nTest 5: Process opt-in keyword (START)...\n";
$result = $consentService->processConsentKeyword($testAgentId, 'whatsapp', $testUserId, 'START', [
    'ip_address' => '203.0.113.42'
]);

if ($result['action'] === 'opt_in') {
    testPass("Opt-in processed successfully");
} else {
    testFail("Opt-in not processed correctly");
}

// Test 6: Check consent after opt-in
echo "\nTest 6: Check consent after opt-in...\n";
$hasConsent = $consentService->hasConsent($testAgentId, 'whatsapp', $testUserId, 'service');
if ($hasConsent === true) {
    testPass("Consent correctly granted after opt-in");
} else {
    testFail("Consent not granted after opt-in");
}

// Test 7: List consents
echo "\nTest 7: List consents with filters...\n";
$consents = $consentService->listConsents([
    'agent_id' => $testAgentId,
    'channel' => 'whatsapp',
    'limit' => 10
]);

if (is_array($consents) && count($consents) > 0) {
    testPass("Listed " . count($consents) . " consent(s)");
} else {
    testFail("Failed to list consents");
}

// Test 8: Get consent audit history
echo "\nTest 8: Get consent audit history...\n";
$auditLog = $consentService->getConsentAuditHistory($consent['id'], 100);
if (is_array($auditLog) && count($auditLog) >= 3) {
    testPass("Retrieved " . count($auditLog) . " audit entries");
} else {
    testFail("Failed to get audit history (expected at least 3 entries: grant, withdraw, renew)");
}

// ===== Test WhatsApp Template Service =====
testHeader("Testing WhatsAppTemplateService");

$templateService = new WhatsAppTemplateService($db, $testTenantId);

// Test 1: Create template
echo "Test 1: Create WhatsApp template...\n";
$template = $templateService->createTemplate([
    'template_name' => 'test_welcome_' . uniqid(),
    'template_category' => 'UTILITY',
    'language_code' => 'en',
    'content_text' => 'Hi {{1}}! Welcome to our service.',
    'header_text' => 'Welcome!',
    'footer_text' => 'Reply STOP to unsubscribe',
    'agent_id' => $testAgentId
]);

if ($template && $template['status'] === 'draft') {
    testPass("Template created successfully with status 'draft'");
} else {
    testFail("Failed to create template");
}

$templateId = $template['id'];

// Test 2: Get template
echo "\nTest 2: Get template by ID...\n";
$retrievedTemplate = $templateService->getTemplate($templateId);
if ($retrievedTemplate && $retrievedTemplate['id'] === $templateId) {
    testPass("Template retrieved successfully");
} else {
    testFail("Failed to retrieve template");
}

// Test 3: Update template
echo "\nTest 3: Update template...\n";
$updatedTemplate = $templateService->updateTemplate($templateId, [
    'footer_text' => 'Contact us anytime - Reply STOP to opt out'
]);

if ($updatedTemplate && $updatedTemplate['footer_text'] === 'Contact us anytime - Reply STOP to opt out') {
    testPass("Template updated successfully");
} else {
    testFail("Failed to update template");
}

// Test 4: Submit template
echo "\nTest 4: Submit template for approval...\n";
$submittedTemplate = $templateService->submitTemplate($templateId);
if ($submittedTemplate && $submittedTemplate['status'] === 'pending') {
    testPass("Template submitted successfully with status 'pending'");
} else {
    testFail("Failed to submit template");
}

// Test 5: Approve template
echo "\nTest 5: Approve template...\n";
$approvedTemplate = $templateService->approveTemplate($templateId, 'wa_test_123', 'HIGH');
if ($approvedTemplate && $approvedTemplate['status'] === 'approved') {
    testPass("Template approved successfully with status 'approved'");
} else {
    testFail("Failed to approve template");
}

// Test 6: List templates
echo "\nTest 6: List templates with filters...\n";
$templates = $templateService->listTemplates([
    'agent_id' => $testAgentId,
    'status' => 'approved',
    'limit' => 10
]);

if (is_array($templates) && count($templates) > 0) {
    testPass("Listed " . count($templates) . " template(s)");
} else {
    testFail("Failed to list templates");
}

// Test 7: Log template usage
echo "\nTest 7: Log template usage...\n";
$usageId = $templateService->logTemplateUsage($templateId, $testAgentId, $testUserId, ['John'], [
    'conversation_id' => 'conv_123',
    'channel' => 'whatsapp'
]);

if ($usageId) {
    testPass("Template usage logged successfully");
} else {
    testFail("Failed to log template usage");
}

// Test 8: Get template stats
echo "\nTest 8: Get template usage statistics...\n";
$stats = $templateService->getTemplateStats($templateId);
if ($stats && $stats['total_sent'] >= 1) {
    testPass("Template stats retrieved: {$stats['total_sent']} messages sent");
} else {
    testFail("Failed to get template stats");
}

// Test 9: Render template with variables
echo "\nTest 9: Render template with variables...\n";
$rendered = $templateService->renderTemplate($templateId, ['John']);
if ($rendered && strpos($rendered['content'], 'Hi John!') !== false) {
    testPass("Template rendered successfully: " . substr($rendered['content'], 0, 50) . "...");
} else {
    testFail("Failed to render template");
}

// ===== Cleanup =====
testHeader("Cleanup");

echo "Deleting test data...\n";
$db->execute("DELETE FROM user_consents WHERE tenant_id = ?", [$testTenantId]);
$db->execute("DELETE FROM consent_audit_log WHERE consent_id IN (SELECT id FROM user_consents WHERE tenant_id = ?)", [$testTenantId]);
$db->execute("DELETE FROM whatsapp_templates WHERE tenant_id = ?", [$testTenantId]);
$db->execute("DELETE FROM whatsapp_template_usage WHERE agent_id = ?", [$testAgentId]);
$db->execute("DELETE FROM agents WHERE id = ?", [$testAgentId]);
$db->execute("DELETE FROM tenants WHERE id = ?", [$testTenantId]);
testPass("Test data cleaned up");

testHeader("All Tests Passed!");
echo "\n✓ ConsentService: All tests passed\n";
echo "✓ WhatsAppTemplateService: All tests passed\n\n";
