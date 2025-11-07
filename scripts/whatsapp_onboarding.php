#!/usr/bin/env php
<?php
/**
 * WhatsApp Onboarding Automation Script
 * 
 * Automates the WhatsApp Business number onboarding process:
 * - Creates tenant (if new)
 * - Creates admin user
 * - Creates agent
 * - Configures WhatsApp channel
 * - Sets up consent management
 * - Creates default templates
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/TenantService.php';
require_once __DIR__ . '/../includes/AdminAuth.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/ConsentService.php';
require_once __DIR__ . '/../includes/WhatsAppTemplateService.php';

// Color output helpers
function colorize($text, $color) {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function success($msg) { echo colorize("✓ $msg\n", 'green'); }
function error($msg) { echo colorize("✗ $msg\n", 'red'); }
function info($msg) { echo colorize("ℹ $msg\n", 'cyan'); }
function warning($msg) { echo colorize("⚠ $msg\n", 'yellow'); }
function title($msg) { echo colorize("\n=== $msg ===\n", 'magenta'); }

// Parse command line arguments
function parseArgs($argv) {
    $args = [];
    for ($i = 1; $i < count($argv); $i++) {
        if (strpos($argv[$i], '--') === 0) {
            $key = substr($argv[$i], 2);
            $value = isset($argv[$i + 1]) && strpos($argv[$i + 1], '--') !== 0 ? $argv[$i + 1] : true;
            $args[$key] = $value;
            if ($value !== true) {
                $i++;
            }
        }
    }
    return $args;
}

// Interactive prompt
function prompt($question, $default = null) {
    $defaultText = $default ? " [$default]" : '';
    echo "$question$defaultText: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    return $line === '' ? $default : $line;
}

// Main onboarding function
function runOnboarding($args) {
    global $config;

    title("WhatsApp Business Onboarding Automation");

    // Initialize database
    $db = new DB($config['storage']);
    $db->runMigrations(__DIR__ . '/../db/migrations');

    // Initialize services
    $tenantService = new TenantService($db);
    $adminAuth = new AdminAuth($db, $config);
    
    // Step 1: Gather information
    title("Step 1: Customer Information");
    
    $customerName = $args['customer-name'] ?? prompt("Customer business name");
    $customerSlug = $args['customer-slug'] ?? prompt("Customer slug (URL-safe)", strtolower(preg_replace('/[^a-z0-9]+/', '-', $customerName)));
    $billingEmail = $args['billing-email'] ?? prompt("Billing email");
    $plan = $args['plan'] ?? prompt("Subscription plan", "enterprise");
    
    // Step 2: WhatsApp details
    title("Step 2: WhatsApp Configuration");
    
    $whatsappNumber = $args['whatsapp-number'] ?? prompt("WhatsApp Business number (E.164 format, e.g., +5511999999999)");
    $zapiInstanceId = $args['zapi-instance'] ?? prompt("Z-API Instance ID");
    $zapiToken = $args['zapi-token'] ?? prompt("Z-API Token");
    
    // Step 3: Admin user
    title("Step 3: Admin User Creation");
    
    $adminEmail = $args['admin-email'] ?? prompt("Admin user email");
    $adminPassword = $args['admin-password'] ?? prompt("Admin user password (min 8 chars)");
    
    if (strlen($adminPassword) < 8) {
        error("Password must be at least 8 characters");
        exit(1);
    }
    
    // Step 4: Agent configuration
    title("Step 4: Agent Configuration");
    
    $agentName = $args['agent-name'] ?? prompt("Agent name", "$customerName WhatsApp Agent");
    $systemMessage = $args['system-message'] ?? prompt("System message (optional)", "You are a helpful customer support agent for $customerName.");
    
    // Step 5: Confirm
    title("Summary");
    info("Customer: $customerName ($customerSlug)");
    info("Billing: $billingEmail");
    info("Plan: $plan");
    info("WhatsApp: $whatsappNumber");
    info("Z-API Instance: $zapiInstanceId");
    info("Admin: $adminEmail");
    info("Agent: $agentName");
    
    if (!isset($args['yes'])) {
        $confirm = prompt("\nProceed with onboarding? (yes/no)", "yes");
        if (strtolower($confirm) !== 'yes') {
            info("Onboarding cancelled");
            exit(0);
        }
    }
    
    // Execute onboarding
    try {
        // Create tenant
        title("Creating Tenant");
        $tenant = $tenantService->createTenant([
            'name' => $customerName,
            'slug' => $customerSlug,
            'status' => 'active',
            'billing_email' => $billingEmail,
            'plan' => $plan,
            'settings' => [
                'features' => ['whatsapp', 'leadsense', 'audit_trails'],
                'limits' => [
                    'max_agents' => 10,
                    'max_conversations_per_month' => 50000
                ],
                'compliance' => [
                    'pii_redaction' => true,
                    'consent_required' => true,
                    'retention_days' => 90,
                    'opt_in_message' => 'Welcome! By continuing, you consent to receive automated messages. Reply STOP to opt out anytime.',
                    'opt_out_keywords' => ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'OPTOUT', 'SAIR'],
                    'opt_in_keywords' => ['START', 'SUBSCRIBE', 'YES', 'OPTIN', 'SIM']
                ]
            ]
        ]);
        success("Tenant created: {$tenant['id']}");
        
        // Create admin user
        title("Creating Admin User");
        $user = $adminAuth->createUser($adminEmail, $adminPassword, 'admin', $tenant['id']);
        success("Admin user created: {$user['id']}");
        
        // Generate API key
        $apiKey = $adminAuth->generateApiKey($user['id'], "$customerName Admin Key", 365);
        success("API key generated: {$apiKey['key']} (expires in 365 days)");
        info("Store this API key securely - it won't be shown again!");
        
        // Create agent
        title("Creating Agent");
        $agentService = new AgentService($db, $tenant['id']);
        $agent = $agentService->createAgent([
            'name' => $agentName,
            'api_type' => 'responses',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'system_message' => $systemMessage,
            'tools' => [['type' => 'file_search']],
            'is_default' => true
        ]);
        success("Agent created: {$agent['id']}");
        
        // Configure WhatsApp channel
        title("Configuring WhatsApp Channel");
        
        $channelConfig = [
            'enabled' => true,
            'whatsapp_business_number' => $whatsappNumber,
            'zapi_instance_id' => $zapiInstanceId,
            'zapi_token' => $zapiToken,
            'zapi_base_url' => 'https://api.z-api.io',
            'zapi_timeout_ms' => 30000,
            'zapi_retries' => 3,
            'reply_chunk_size' => 4000,
            'allow_media_upload' => true,
            'max_media_size_bytes' => 10485760,
            'allowed_media_types' => ['image/jpeg', 'image/png', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        ];
        
        $channelId = generateUuid();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        
        $stmt = $db->prepare("
            INSERT INTO agent_channels (id, agent_id, channel, enabled, config_json, created_at, updated_at)
            VALUES (?, ?, 'whatsapp', 1, ?, ?, ?)
        ");
        $stmt->execute([$channelId, $agent['id'], json_encode($channelConfig), $now, $now]);
        
        success("WhatsApp channel configured");
        info("Webhook URL: https://yourdomain.com/channels/whatsapp/{$agent['id']}/webhook");
        warning("Configure this webhook URL in your Z-API dashboard!");
        
        // Create default templates
        title("Creating Default Templates");
        
        $templateService = new WhatsAppTemplateService($db, $tenant['id']);
        
        $templates = [
            [
                'name' => 'welcome_message',
                'category' => 'UTILITY',
                'language' => 'en',
                'content' => "Hi {{1}}! 👋\n\nThank you for contacting $customerName. Our AI assistant is here to help you.\n\nBy continuing, you consent to automated messages.\nReply STOP to opt out anytime.\n\nHow can we help you today?"
            ],
            [
                'name' => 'opt_in_confirmation',
                'category' => 'UTILITY',
                'language' => 'en',
                'content' => "✅ You're now subscribed to updates from $customerName.\n\nYou can opt out anytime by sending STOP."
            ],
            [
                'name' => 'opt_out_confirmation',
                'category' => 'UTILITY',
                'language' => 'en',
                'content' => "You've been unsubscribed from $customerName.\n\nTo resubscribe, send START anytime."
            ]
        ];
        
        foreach ($templates as $tpl) {
            $template = $templateService->createTemplate([
                'template_name' => $tpl['name'],
                'template_category' => $tpl['category'],
                'language_code' => $tpl['language'],
                'content_text' => $tpl['content'],
                'agent_id' => $agent['id']
            ]);
            success("Template created: {$tpl['name']} ({$template['id']})");
            info("Submit this template for approval in WhatsApp Business Manager");
        }
        
        // Summary
        title("Onboarding Complete!");
        
        echo "\n";
        success("Tenant ID: {$tenant['id']}");
        success("Tenant Slug: {$tenant['slug']}");
        success("Agent ID: {$agent['id']}");
        success("Admin Email: $adminEmail");
        success("API Key: {$apiKey['key']}");
        
        echo "\n";
        info("Next Steps:");
        echo "1. Configure webhook in Z-API dashboard:\n";
        echo "   URL: https://yourdomain.com/channels/whatsapp/{$agent['id']}/webhook\n";
        echo "\n";
        echo "2. Submit templates for approval in WhatsApp Business Manager:\n";
        foreach ($templates as $tpl) {
            echo "   - {$tpl['name']}\n";
        }
        echo "\n";
        echo "3. Access Admin UI: https://yourdomain.com/public/admin/\n";
        echo "   Login with: $adminEmail\n";
        echo "\n";
        echo "4. Test messaging by sending a WhatsApp message to: $whatsappNumber\n";
        echo "\n";
        
        // Save onboarding info to file
        $onboardingData = [
            'tenant' => $tenant,
            'agent' => $agent,
            'admin_email' => $adminEmail,
            'api_key' => $apiKey['key'],
            'whatsapp_number' => $whatsappNumber,
            'webhook_url' => "https://yourdomain.com/channels/whatsapp/{$agent['id']}/webhook",
            'created_at' => $now
        ];
        
        $outputFile = __DIR__ . "/../data/onboarding_{$tenant['slug']}_" . date('Y-m-d') . ".json";
        @mkdir(dirname($outputFile), 0755, true);
        file_put_contents($outputFile, json_encode($onboardingData, JSON_PRETTY_PRINT));
        info("Onboarding data saved to: $outputFile");
        
    } catch (Exception $e) {
        error("Onboarding failed: " . $e->getMessage());
        exit(1);
    }
}

function generateUuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

// Show help
function showHelp() {
    echo <<<HELP
WhatsApp Business Onboarding Automation

Usage: php whatsapp_onboarding.php [options]

Options:
  --customer-name <name>       Customer business name
  --customer-slug <slug>       URL-safe customer identifier
  --billing-email <email>      Billing contact email
  --plan <plan>                Subscription plan (default: enterprise)
  --whatsapp-number <number>   WhatsApp Business number (E.164 format)
  --zapi-instance <id>         Z-API Instance ID
  --zapi-token <token>         Z-API Token
  --admin-email <email>        Admin user email
  --admin-password <password>  Admin user password
  --agent-name <name>          Agent display name
  --system-message <message>   Agent system message
  --yes                        Skip confirmation prompt
  --help                       Show this help message

Examples:
  # Interactive mode
  php whatsapp_onboarding.php

  # Non-interactive mode
  php whatsapp_onboarding.php \\
    --customer-name "Acme Corp" \\
    --customer-slug "acme" \\
    --billing-email "billing@acme.com" \\
    --whatsapp-number "+5511999999999" \\
    --zapi-instance "instance123" \\
    --zapi-token "token456" \\
    --admin-email "admin@acme.com" \\
    --admin-password "SecurePass123!" \\
    --yes

HELP;
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

$args = parseArgs($argv);

if (isset($args['help'])) {
    showHelp();
    exit(0);
}

runOnboarding($args);
