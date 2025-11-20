<?php
/**
 * Public Whitelabel Page
 * Serves a standalone chatbot page for a specific agent
 * 
 * Routes:
 * - /public/whitelabel.php?id={agent_public_id}
 * - /public/whitelabel.php?path={vanity_path}
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/AgentService.php';
require_once __DIR__ . '/../includes/WhitelabelTokenService.php';

// Logging helper
function log_wl($message, $level = 'info') {
    global $config;
    $logFile = $config['logging']['file'] ?? __DIR__ . '/../logs/chatbot.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $line = "[$ts][$level][Whitelabel][$ip] $message\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
}

// Send 404 error
function send404($message = 'Agent not found') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Not Found</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
            color: #333;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 4rem;
            margin: 0;
            color: #666;
        }
        p {
            font-size: 1.2rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <p>' . htmlspecialchars($message) . '</p>
    </div>
</body>
</html>';
    exit;
}

// Get agent identifier from URL
$agentPublicId = $_GET['id'] ?? null;
$vanityPath = $_GET['path'] ?? null;
$customDomain = $_SERVER['HTTP_HOST'] ?? null;

// Initialize database and services
try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? null,
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/../data/chatbot.db'
    ];
    $db = new DB($dbConfig);
    $agentService = new AgentService($db);
    $tokenService = new WhitelabelTokenService($db, $config);
} catch (Exception $e) {
    log_wl('Database initialization failed: ' . $e->getMessage(), 'error');
    send404('Service temporarily unavailable');
}

// Resolve agent
$agent = null;

// Try custom domain first
if ($customDomain && !$agentPublicId && !$vanityPath) {
    $agent = $agentService->getAgentByCustomDomain($customDomain);
    if ($agent) {
        log_wl("Resolved agent via custom domain: {$customDomain}");
    }
}

// Try public ID
if (!$agent && $agentPublicId) {
    $agent = $agentService->getAgentByPublicId($agentPublicId);
    if ($agent) {
        log_wl("Resolved agent via public ID: {$agentPublicId}");
    } else {
        log_wl("Agent not found for public ID: {$agentPublicId}", 'warn');
    }
}

// Try vanity path
if (!$agent && $vanityPath) {
    $agent = $agentService->getAgentByVanityPath($vanityPath);
    if ($agent) {
        log_wl("Resolved agent via vanity path: {$vanityPath}");
    } else {
        log_wl("Agent not found for vanity path: {$vanityPath}", 'warn');
    }
}

// If no agent found, return 404
if (!$agent) {
    log_wl('No agent identifier provided or agent not found', 'warn');
    send404('Agent not found or not published');
}

// Verify whitelabel is enabled
if (!$agent['whitelabel_enabled']) {
    log_wl("Whitelabel not enabled for agent: {$agent['id']}", 'warn');
    send404('Agent not published');
}

// Get public configuration
$publicConfig = $agentService->getPublicWhitelabelConfig($agent['agent_public_id']);

// Generate whitelabel token
$wlToken = null;
if ($agent['wl_hmac_secret']) {
    $ttl = $agent['wl_token_ttl_seconds'] ?? 600;
    $wlToken = $tokenService->generateToken($agent['agent_public_id'], $agent['wl_hmac_secret'], $ttl);
}

// Prepare theme with defaults
$theme = array_merge([
    'primaryColor' => '#1FB8CD',
    'backgroundColor' => '#F5F5F5',
    'surfaceColor' => '#FFFFFF',
    'textColor' => '#333333',
    'borderRadius' => '8px'
], $publicConfig['theme'] ?? []);

// Log successful page load
log_wl("Whitelabel page loaded for agent: {$agent['name']} ({$agent['agent_public_id']})");

// Get base URL for API endpoint
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host;

// Render HTML
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($publicConfig['title']); ?></title>
    
    <!-- Favicon -->
    <?php if (!empty($publicConfig['logo_url'])): ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($publicConfig['logo_url']); ?>">
    <?php endif; ?>
    
    <!-- Chatbot CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/chatbot.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: <?php echo htmlspecialchars($theme['backgroundColor']); ?>;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .wl-header {
            background: <?php echo htmlspecialchars($theme['surfaceColor']); ?>;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .wl-logo {
            height: 40px;
            width: auto;
        }
        
        .wl-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: <?php echo htmlspecialchars($theme['textColor']); ?>;
        }
        
        .wl-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        
        .wl-chat-wrapper {
            width: 100%;
            max-width: 800px;
            height: 600px;
            max-height: 80vh;
            background: <?php echo htmlspecialchars($theme['surfaceColor']); ?>;
            border-radius: <?php echo htmlspecialchars($theme['borderRadius']); ?>;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        
        .wl-footer {
            padding: 1rem 2rem;
            text-align: center;
            color: #666;
            font-size: 0.875rem;
        }

        .wl-version {
            padding: 0.5rem 2rem;
            text-align: center;
            color: #999;
            font-size: 0.75rem;
        }
        
        .wl-footer a {
            color: <?php echo htmlspecialchars($theme['primaryColor']); ?>;
            text-decoration: none;
        }
        
        .wl-footer a:hover {
            text-decoration: underline;
        }
        
        .wl-disclaimer {
            max-width: 800px;
            margin: 1rem auto 0;
            padding: 0 2rem;
            color: #666;
            font-size: 0.75rem;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .wl-header {
                padding: 0.75rem 1rem;
            }
            
            .wl-title {
                font-size: 1.25rem;
            }
            
            .wl-container {
                padding: 1rem;
            }
            
            .wl-chat-wrapper {
                height: calc(100vh - 200px);
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="wl-header">
        <?php if (!empty($publicConfig['logo_url'])): ?>
        <img src="<?php echo htmlspecialchars($publicConfig['logo_url']); ?>" 
             alt="<?php echo htmlspecialchars($publicConfig['title']); ?>" 
             class="wl-logo">
        <?php endif; ?>
        <h1 class="wl-title"><?php echo htmlspecialchars($publicConfig['title']); ?></h1>
    </div>
    
    <!-- Chat Container -->
    <div class="wl-container">
        <div id="whitelabel-chat" class="wl-chat-wrapper"></div>
    </div>
    
    <!-- Footer -->
    <?php if (!empty($publicConfig['legal_disclaimer_md'])): ?>
    <div class="wl-disclaimer">
        <?php 
        // Simple, safe markdown to HTML conversion
        $disclaimer = htmlspecialchars($publicConfig['legal_disclaimer_md']);
        
        // Limit length to prevent DoS
        if (strlen($disclaimer) > 1000) {
            $disclaimer = substr($disclaimer, 0, 1000) . '...';
        }
        
        // Simple replacements (safe, non-recursive)
        $disclaimer = preg_replace('/\*\*(.{1,100}?)\*\*/', '<strong>$1</strong>', $disclaimer);
        $disclaimer = preg_replace('/\*(.{1,100}?)\*/', '<em>$1</em>', $disclaimer);
        $disclaimer = nl2br($disclaimer);
        echo $disclaimer;
        ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($publicConfig['footer_brand_md'])): ?>
    <div class="wl-footer">
        <?php
        $footer = htmlspecialchars($publicConfig['footer_brand_md']);

        // Limit length to prevent DoS
        if (strlen($footer) > 500) {
            $footer = substr($footer, 0, 500) . '...';
        }

        // Simple link conversion (safe, non-recursive)
        $footer = preg_replace('/\[(.{1,50}?)\]\((.{1,200}?)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $footer);
        echo $footer;
        ?>
    </div>
    <?php endif; ?>

    <div class="wl-version">
        Version <?php echo htmlspecialchars($config['app_version'] ?? '1.0.0', ENT_QUOTES, 'UTF-8'); ?>
    </div>
    
    <!-- Chatbot Script -->
    <script src="<?php echo $baseUrl; ?>/chatbot-enhanced.js"></script>
    <script>
        (function() {
            // Initialize chatbot with whitelabel configuration
            const chatbot = new EnhancedChatbot({
                mode: 'inline',
                containerId: 'whitelabel-chat',
                apiType: <?php echo json_encode($publicConfig['api_type']); ?>,
                apiEndpoint: <?php echo json_encode($baseUrl . '/chat-unified.php'); ?>,
                
                // Whitelabel parameters
                agentPublicId: <?php echo json_encode($agent['agent_public_id']); ?>,
                wlToken: <?php echo json_encode($wlToken); ?>,
                
                // Branding
                title: <?php echo json_encode($publicConfig['title']); ?>,
                enableFileUpload: <?php echo json_encode($publicConfig['enable_file_upload']); ?>,
                
                // Theme
                theme: <?php echo json_encode($theme); ?>,
                
                // Assistant settings
                assistant: {
                    name: <?php echo json_encode($publicConfig['title']); ?>,
                    avatar: <?php echo json_encode($publicConfig['logo_url']); ?>,
                    welcomeMessage: <?php echo json_encode($publicConfig['welcome_message']); ?>,
                    placeholder: <?php echo json_encode($publicConfig['placeholder']); ?>
                },
                
                // Layout settings
                layout: {
                    header: {
                        showAvatar: <?php echo !empty($publicConfig['logo_url']) ? 'true' : 'false'; ?>,
                        showTitle: true,
                        showPoweredBy: false,
                        showMaximize: false,
                        showClose: false,
                        showApiTypePill: false
                    }
                }
            });
        })();
    </script>
</body>
</html>
