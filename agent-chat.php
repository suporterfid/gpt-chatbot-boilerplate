<?php
/**
 * Public Agent Chat Page
 * Loads a chat interface for a specific agent based on slug
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/DB.php';
require_once __DIR__ . '/includes/AgentService.php';

// Get slug from URL parameter
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 - Not Found</h1><p>Agent not specified.</p></body></html>';
    exit;
}

// Validate slug format (basic check)
if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 - Not Found</h1><p>Invalid agent identifier.</p></body></html>';
    exit;
}

// Initialize database and service
try {
    $dbConfig = [
        'database_url' => $config['admin']['database_url'] ?? '',
        'database_path' => $config['admin']['database_path'] ?? __DIR__ . '/data/chatbot.db',
    ];
    $db = new DB($dbConfig);
    $agentService = new AgentService($db);
    
    // Get agent by slug
    $agent = $agentService->getAgentBySlug($slug);
    
    if (!$agent) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 - Not Found</h1><p>Agent not found.</p></body></html>';
        exit;
    }
} catch (Exception $e) {
    error_log("Error loading agent by slug: " . $e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Error</h1><p>This agent is currently unavailable.</p></body></html>';
    exit;
}

// Agent found - render the chat page
$agentName = htmlspecialchars($agent['name'] ?? 'AI Assistant', ENT_QUOTES, 'UTF-8');
$agentDescription = htmlspecialchars($agent['description'] ?? '', ENT_QUOTES, 'UTF-8');
$agentId = htmlspecialchars($agent['id'], ENT_QUOTES, 'UTF-8');
$apiType = htmlspecialchars($agent['api_type'] ?? 'responses', ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $agentName; ?> - Chat</title>
    <link rel="stylesheet" href="chatbot.css">
    <style>
        /* Full-page chat interface styles */
        :root {
            --brand-primary: #5f6360;
            --brand-secondary: rgb(245, 245, 245);
            --brand-accent: rgb(80, 120, 255);
            --brand-background: rgb(249, 249, 250);
            --brand-text: rgb(38, 38, 38);
            --brand-border: rgb(208, 208, 210);
            --brand-muted: rgba(38, 38, 38, 0.65);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: var(--brand-text);
            background: var(--brand-background);
            overflow: hidden;
        }

        .chat-page-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
        }

        .chat-header {
            background: white;
            border-bottom: 1px solid var(--brand-border);
            padding: 16px 24px;
            flex-shrink: 0;
        }

        .chat-header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .agent-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-accent), var(--brand-primary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .agent-info {
            flex: 1;
            min-width: 0;
        }

        .agent-name {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--brand-text);
        }

        .agent-description {
            margin: 4px 0 0 0;
            font-size: 14px;
            color: var(--brand-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding: 0;
        }

        /* Override chatbot widget styles for full-page mode */
        #chatbot-container {
            position: static !important;
            transform: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            width: 100% !important;
            height: 100% !important;
            max-width: 1200px !important;
            max-height: none !important;
            border: none !important;
        }

        #chatbot-container .chatbot-header {
            display: none !important;
        }

        #chatbot-container .chatbot-body {
            height: 100% !important;
            border-radius: 0 !important;
        }

        @media (max-width: 768px) {
            .chat-header {
                padding: 12px 16px;
            }

            .agent-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .agent-name {
                font-size: 18px;
            }

            .agent-description {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-page-wrapper">
        <header class="chat-header">
            <div class="chat-header-content">
                <div class="agent-icon">
                    <?php echo strtoupper(substr($agentName, 0, 1)); ?>
                </div>
                <div class="agent-info">
                    <h1 class="agent-name"><?php echo $agentName; ?></h1>
                    <?php if ($agentDescription): ?>
                        <p class="agent-description"><?php echo $agentDescription; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <main class="chat-container" id="chat-main">
            <!-- Chat widget will be initialized here -->
        </main>
    </div>

    <!-- Load chatbot widget -->
    <script src="chatbot-enhanced.js"></script>
    <script>
        // Initialize chatbot widget with agent configuration
        document.addEventListener('DOMContentLoaded', function() {
            ChatBot.init({
                mode: 'inline',
                container: 'chat-main',
                apiType: '<?php echo $apiType; ?>',
                apiEndpoint: '/chat-unified.php',
                agent: {
                    id: '<?php echo $agentId; ?>',
                    name: '<?php echo addslashes($agentName); ?>'
                },
                assistant: {
                    name: '<?php echo addslashes($agentName); ?>',
                    welcomeMessage: 'Hello! How can I help you today?'
                },
                ui: {
                    title: false, // Hide title since we have header
                    theme: 'light'
                }
            });
        });
    </script>
</body>
</html>
