<?php
/**
 * GPT Chatbot Installation Wizard
 * 
 * Web-based installation interface for configuring the chatbot instance.
 * This script helps users set up the required configuration parameters,
 * initialize the database, and validate the installation.
 */

session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Prevent re-installation if already configured
$lockFile = __DIR__ . '/../.install.lock';
$envFile = __DIR__ . '/../.env';

// Handle installation lock removal (for re-installation)
if (isset($_GET['unlock']) && $_GET['unlock'] === 'confirm') {
    // Verify CSRF token for unlock action
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die('Invalid security token. Please try again.');
    }
    
    if (file_exists($lockFile)) {
        unlink($lockFile);
        header('Location: install.php');
        exit;
    }
}

$isInstalled = file_exists($lockFile);
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

$automationSeedDefaultsPrefill = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['automation_seed_defaults'])) {
    $automationSeedDefaultsPrefill = filter_var($_POST['automation_seed_defaults'], FILTER_VALIDATE_BOOLEAN);
} elseif (!empty($_SESSION['install_config']['automation_seed_defaults'])) {
    $automationSeedDefaultsPrefill = filter_var(
        $_SESSION['install_config']['automation_seed_defaults'],
        FILTER_VALIDATE_BOOLEAN
    );
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid security token. Please refresh the page and try again.');
    }
    
    $errors = [];
    $success = false;
    
    if (isset($_POST['step'])) {
        $currentStep = (int)$_POST['step'];
        
        // Step 2: Validate and save configuration
        if ($currentStep === 2) {
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';

            $automationSeedDefaults = filter_var($_POST['automation_seed_defaults'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

            $config = [
                'API_TYPE' => $_POST['api_type'] ?? 'responses',
                'OPENAI_API_KEY' => trim($_POST['openai_api_key'] ?? ''),
                'OPENAI_BASE_URL' => trim($_POST['openai_base_url'] ?? 'https://api.openai.com/v1'),
                
                // Chat Completions
                'OPENAI_MODEL' => $_POST['openai_model'] ?? 'gpt-4o-mini',
                'OPENAI_TEMPERATURE' => $_POST['openai_temperature'] ?? '0.7',
                
                // Responses API
                'RESPONSES_MODEL' => $_POST['responses_model'] ?? 'gpt-4o-mini',
                'RESPONSES_TEMPERATURE' => $_POST['responses_temperature'] ?? '0.7',
                'RESPONSES_MAX_OUTPUT_TOKENS' => $_POST['responses_max_output_tokens'] ?? '1024',
                
                // Database
                'DATABASE_TYPE' => $_POST['database_type'] ?? 'sqlite',
                'DATABASE_PATH' => $_POST['database_path'] ?? './data/chatbot.db',
                'DATABASE_URL' => '',

                // Admin
                'ADMIN_ENABLED' => 'true',

                // Security
                'CORS_ORIGINS' => $_POST['cors_origins'] ?? '*',
                'MAX_MESSAGE_LENGTH' => $_POST['max_message_length'] ?? '4000',
                
                // Features
                'ENABLE_FILE_UPLOAD' => isset($_POST['enable_file_upload']) ? 'true' : 'false',
                'MAX_FILE_SIZE' => $_POST['max_file_size'] ?? '10485760',
                'ALLOWED_FILE_TYPES' => $_POST['allowed_file_types'] ?? 'txt,pdf,doc,docx,jpg,png',
                
                // Jobs
                'JOBS_ENABLED' => isset($_POST['jobs_enabled']) ? 'true' : 'false',
                
                // Storage
                'STORAGE_TYPE' => $_POST['storage_type'] ?? 'session',
                'STORAGE_PATH' => $_POST['storage_path'] ?? '/tmp/chatbot_conversations',
                
                // Audit
                'AUDIT_ENABLED' => isset($_POST['audit_enabled']) ? 'true' : 'false',
                'AUDIT_ENCRYPT' => isset($_POST['audit_encrypt']) ? 'true' : 'false',
                'AUDIT_ENC_KEY' => $_POST['audit_enc_key'] ?? bin2hex(random_bytes(32)),
                'AUDIT_RETENTION_DAYS' => $_POST['audit_retention_days'] ?? '90',
            ];
            
            // Validate required fields
            if (empty($config['OPENAI_API_KEY'])) {
                $errors[] = 'OpenAI API Key is required';
            } elseif (!str_starts_with($config['OPENAI_API_KEY'], 'sk-') || strlen($config['OPENAI_API_KEY']) < 20) {
                $errors[] = 'Invalid OpenAI API Key format. Must start with "sk-" and be at least 20 characters';
            }
            
            // Validate admin email
            if (empty($adminEmail)) {
                $errors[] = 'Admin email is required';
            } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid admin email address';
            }

            // Validate admin password
            if (empty($adminPassword)) {
                $errors[] = 'Admin password is required';
            } elseif ($adminPassword !== $adminPasswordConfirm) {
                $errors[] = 'Admin password confirmation does not match';
            } else {
                $passwordLength = strlen($adminPassword);
                $hasUppercase = preg_match('/[A-Z]/', $adminPassword);
                $hasLowercase = preg_match('/[a-z]/', $adminPassword);
                $hasNumber = preg_match('/\d/', $adminPassword);
                $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $adminPassword);

                if ($passwordLength < 12) {
                    $errors[] = 'Admin password must be at least 12 characters long';
                }

                if (!$hasUppercase || !$hasLowercase || !$hasNumber || !$hasSpecial) {
                    $errors[] = 'Admin password must include uppercase, lowercase, numeric, and special characters';
                }
            }
            
            // Database configuration
            if ($config['DATABASE_TYPE'] === 'mysql') {
                $dbHost = trim($_POST['db_host'] ?? 'mysql');
                $dbPort = trim($_POST['db_port'] ?? '3306');
                $dbName = trim($_POST['db_name'] ?? 'chatbot');
                $dbUser = trim($_POST['db_user'] ?? 'chatbot');
                $dbPass = trim($_POST['db_password'] ?? '');
                
                if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
                    $errors[] = 'MySQL configuration is incomplete';
                } else {
                    $config['DATABASE_URL'] = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        $dbHost, $dbPort, $dbName
                    );
                    $config['DB_HOST'] = $dbHost;
                    $config['DB_PORT'] = $dbPort;
                    $config['DB_NAME'] = $dbName;
                    $config['DB_USER'] = $dbUser;
                    $config['DB_PASSWORD'] = $dbPass;
                }
            }
            
            if (empty($errors)) {
                $adminPasswordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

                $defaultAdminEmailEnv = preg_replace("/[\r\n]/", '', $adminEmail);
                $defaultAdminPasswordEnv = preg_replace("/[\r\n]/", '', $adminPassword);

                // Generate .env file
                $envContent = "# GPT Chatbot Configuration\n";
                $envContent .= "# Generated by installation wizard on " . date('Y-m-d H:i:s') . "\n\n";
                
                $envContent .= "# Core Configuration\n";
                $envContent .= "API_TYPE={$config['API_TYPE']}\n";
                $envContent .= "STORAGE_TYPE={$config['STORAGE_TYPE']}\n\n";
                
                $envContent .= "# OpenAI Configuration\n";
                $envContent .= "OPENAI_API_KEY={$config['OPENAI_API_KEY']}\n";
                $envContent .= "OPENAI_BASE_URL={$config['OPENAI_BASE_URL']}\n\n";
                
                $envContent .= "# Chat Completions API\n";
                $envContent .= "OPENAI_MODEL={$config['OPENAI_MODEL']}\n";
                $envContent .= "OPENAI_TEMPERATURE={$config['OPENAI_TEMPERATURE']}\n";
                $envContent .= "OPENAI_MAX_TOKENS=1000\n";
                $envContent .= "OPENAI_TOP_P=1.0\n";
                $envContent .= "OPENAI_FREQUENCY_PENALTY=0.0\n";
                $envContent .= "OPENAI_PRESENCE_PENALTY=0.0\n\n";
                
                $envContent .= "# Responses API\n";
                $envContent .= "RESPONSES_MODEL={$config['RESPONSES_MODEL']}\n";
                $envContent .= "RESPONSES_TEMPERATURE={$config['RESPONSES_TEMPERATURE']}\n";
                $envContent .= "RESPONSES_MAX_OUTPUT_TOKENS={$config['RESPONSES_MAX_OUTPUT_TOKENS']}\n";
                $envContent .= "RESPONSES_TOP_P=1.0\n\n";
                
                $envContent .= "# Database Configuration\n";
                if ($config['DATABASE_TYPE'] === 'mysql') {
                    $envContent .= "DATABASE_URL={$config['DATABASE_URL']}\n";
                    $envContent .= "DB_HOST={$config['DB_HOST']}\n";
                    $envContent .= "DB_PORT={$config['DB_PORT']}\n";
                    $envContent .= "DB_NAME={$config['DB_NAME']}\n";
                    $envContent .= "DB_USER={$config['DB_USER']}\n";
                    $envContent .= "DB_PASSWORD={$config['DB_PASSWORD']}\n";
                    $envContent .= "DATABASE_PATH=\n\n";
                } else {
                    $envContent .= "DATABASE_PATH={$config['DATABASE_PATH']}\n";
                    $envContent .= "DATABASE_URL=\n\n";
                }
                
                $envContent .= "# Admin Configuration\n";
                $envContent .= "ADMIN_ENABLED={$config['ADMIN_ENABLED']}\n";
                $envContent .= "ADMIN_RATE_LIMIT_REQUESTS=300\n";
                $envContent .= "ADMIN_RATE_LIMIT_WINDOW=60\n\n";

                if ($automationSeedDefaults) {
                    $envContent .= "# Automation Defaults (Optional)\n";
                    $envContent .= "# WARNING: DEFAULT_ADMIN_EMAIL and DEFAULT_ADMIN_PASSWORD are written for CI/bootstrap flows.\n";
                    $envContent .= "# Rotate or remove these credentials immediately after automated provisioning.\n";
                    $envContent .= "DEFAULT_ADMIN_EMAIL={$defaultAdminEmailEnv}\n";
                    $envContent .= "DEFAULT_ADMIN_PASSWORD={$defaultAdminPasswordEnv}\n\n";
                }
                
                $envContent .= "# File Upload\n";
                $envContent .= "ENABLE_FILE_UPLOAD={$config['ENABLE_FILE_UPLOAD']}\n";
                $envContent .= "MAX_FILE_SIZE={$config['MAX_FILE_SIZE']}\n";
                $envContent .= "ALLOWED_FILE_TYPES={$config['ALLOWED_FILE_TYPES']}\n\n";
                
                $envContent .= "# Storage\n";
                $envContent .= "STORAGE_PATH={$config['STORAGE_PATH']}\n\n";
                
                $envContent .= "# Chat Configuration\n";
                $envContent .= "CHAT_MAX_MESSAGES=50\n";
                $envContent .= "CHAT_SESSION_TIMEOUT=3600\n";
                $envContent .= "CHAT_RATE_LIMIT=60\n";
                $envContent .= "CHAT_RATE_WINDOW=60\n";
                $envContent .= "CHAT_ENABLE_LOGGING=true\n\n";
                
                $envContent .= "# Security\n";
                $envContent .= "CORS_ORIGINS={$config['CORS_ORIGINS']}\n";
                $envContent .= "VALIDATE_REFERER=false\n";
                $envContent .= "API_KEY_VALIDATION=true\n";
                $envContent .= "SANITIZE_INPUT=true\n";
                $envContent .= "MAX_MESSAGE_LENGTH={$config['MAX_MESSAGE_LENGTH']}\n\n";
                
                $envContent .= "# Background Jobs\n";
                $envContent .= "JOBS_ENABLED={$config['JOBS_ENABLED']}\n\n";
                
                $envContent .= "# Audit Trail\n";
                $envContent .= "AUDIT_ENABLED={$config['AUDIT_ENABLED']}\n";
                $envContent .= "AUDIT_ENCRYPT={$config['AUDIT_ENCRYPT']}\n";
                $envContent .= "AUDIT_ENC_KEY={$config['AUDIT_ENC_KEY']}\n";
                $envContent .= "AUDIT_RETENTION_DAYS={$config['AUDIT_RETENTION_DAYS']}\n";
                $envContent .= "AUDIT_PII_PATTERNS=\n";
                $envContent .= "AUDIT_SAMPLE_RATE=1.0\n";
                $envContent .= "AUDIT_EVAL_ASYNC=false\n\n";
                
                $envContent .= "# WebSocket (Optional)\n";
                $envContent .= "WEBSOCKET_ENABLED=false\n";
                $envContent .= "WEBSOCKET_HOST=0.0.0.0\n";
                $envContent .= "WEBSOCKET_PORT=8080\n";
                $envContent .= "WEBSOCKET_SSL=false\n\n";
                
                $envContent .= "# Logging\n";
                $envContent .= "LOG_LEVEL=info\n";
                $envContent .= "LOG_FILE=logs/chatbot.log\n";
                $envContent .= "LOG_MAX_SIZE=10485760\n";
                $envContent .= "LOG_MAX_FILES=5\n\n";
                
                $envContent .= "# Performance\n";
                $envContent .= "CACHE_ENABLED=false\n";
                $envContent .= "CACHE_TTL=3600\n";
                $envContent .= "COMPRESSION_ENABLED=true\n\n";
                
                $envContent .= "# Development\n";
                $envContent .= "DEBUG=false\n";
                
                // Save .env file
                if (file_put_contents($envFile, $envContent) !== false) {
                    // Set restrictive permissions for security
                    chmod($envFile, 0600);
                    $_SESSION['install_config'] = array_merge(
                        $config,
                        [
                            'admin_email' => $adminEmail,
                            'admin_password' => $adminPassword,
                            'admin_password_hash' => $adminPasswordHash,
                            'automation_seed_defaults' => $automationSeedDefaults,
                        ]
                    );
                    header('Location: install.php?step=3');
                    exit;
                } else {
                    $errors[] = 'Failed to write .env file. Check directory permissions.';
                }
            }
        }
        
        // Step 3: Initialize database
        if ($currentStep === 3) {
            try {
                // Load the configuration
                $config = require __DIR__ . '/../config.php';
                require_once __DIR__ . '/../includes/DB.php';
                require_once __DIR__ . '/../includes/AdminAuth.php';

                $dbConfig = [];
                if (!empty(getEnvValue('DATABASE_URL'))) {
                    $dbConfig['database_url'] = getEnvValue('DATABASE_URL');
                } else {
                    $dbConfig['database_path'] = getEnvValue('DATABASE_PATH') ?? './data/chatbot.db';
                }

                $db = new DB($dbConfig);

                // Run migrations
                $migrationsPath = __DIR__ . '/../db/migrations';
                $migrationsRun = $db->runMigrations($migrationsPath);

                // Attempt to create the initial super-admin user
                $adminStatus = [
                    'created' => false,
                    'already_existed' => false,
                ];

                $adminEmail = $_SESSION['install_config']['admin_email'] ?? null;
                $adminPassword = $_SESSION['install_config']['admin_password'] ?? null;

                if ($adminEmail && $adminPassword) {
                    $adminAuth = new AdminAuth($db, $config);

                    try {
                        $existingAdmin = $adminAuth->getUserByEmail($adminEmail);
                        if ($existingAdmin) {
                            $adminStatus['already_existed'] = true;
                        } else {
                            $adminAuth->createUser($adminEmail, $adminPassword, AdminAuth::ROLE_SUPER_ADMIN);
                            $adminStatus['created'] = true;
                        }
                    } catch (Exception $e) {
                        if ($e->getCode() === 409 || strpos(strtolower($e->getMessage()), 'exists') !== false) {
                            $adminStatus['already_existed'] = true;
                        } else {
                            $errors[] = 'Super admin account could not be created: ' . $e->getMessage();
                        }
                    }
                } else {
                    $errors[] = 'Super admin credentials are missing from the previous step. Please return to Step 2 and save the configuration again.';
                }

                if (empty($errors)) {
                    // Create lock file
                    file_put_contents($lockFile, json_encode([
                        'installed_at' => date('c'),
                        'migrations_run' => $migrationsRun,
                        'php_version' => PHP_VERSION,
                        'database_type' => !empty(getEnvValue('DATABASE_URL')) ? 'mysql' : 'sqlite'
                    ]));

                    $_SESSION['install_config']['admin_setup_status'] = $adminStatus;
                    unset($_SESSION['install_config']['admin_password'], $_SESSION['install_config']['admin_password_hash']);

                    $success = true;
                    header('Location: install.php?step=4');
                    exit;
                }
            } catch (PDOException $e) {
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'Access denied') !== false) {
                    $errors[] = 'Database authentication failed. Please check your username and password.';
                } elseif (strpos($errorMsg, 'Unknown database') !== false) {
                    $errors[] = 'Database does not exist. Please create the database first or check the database name.';
                } elseif (strpos($errorMsg, 'Connection refused') !== false) {
                    $errors[] = 'Cannot connect to database server. Please verify the host and port are correct and the server is running.';
                } else {
                    $errors[] = 'Database connection failed: ' . $errorMsg;
                }
            } catch (Exception $e) {
                $errors[] = 'Database initialization failed: ' . $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPT Chatbot - Installation Wizard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .progress-bar {
            background: #f0f0f0;
            height: 6px;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .content {
            padding: 40px;
        }
        
        .step {
            display: none;
        }
        
        .step.active {
            display: block;
        }
        
        .step h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #888;
            font-size: 12px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: space-between;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .alert-success {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
        }
        
        .alert-info {
            background: #eef;
            border: 1px solid #ccf;
            color: #33c;
        }
        
        .alert-warning {
            background: #ffeaa7;
            border: 1px solid #fdcb6e;
            color: #d63031;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            margin-bottom: 10px;
            color: #667eea;
        }
        
        .info-box ul {
            margin-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        .collapsible {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .collapsible-header {
            padding: 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }
        
        .collapsible-header:hover {
            background: #f0f0f0;
        }
        
        .collapsible-content {
            padding: 0 15px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .collapsible.open .collapsible-content {
            padding: 15px;
            max-height: 1000px;
        }
        
        .status-check {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        
        .status-check .icon {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .status-check.success .icon {
            background: #4caf50;
            color: white;
        }
        
        .status-check.error .icon {
            background: #f44336;
            color: white;
        }
        
        .status-check.warning .icon {
            background: #ff9800;
            color: white;
        }
        
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .installed-notice {
            text-align: center;
            padding: 40px;
        }
        
        .installed-notice .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 GPT Chatbot Installation</h1>
            <p>Web-based setup wizard for your chatbot instance</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($step / 4) * 100; ?>%"></div>
        </div>
        
        <div class="content">
            <?php if ($isInstalled && $step !== 4): ?>
                <div class="installed-notice">
                    <div class="icon">✅</div>
                    <h2>Installation Already Complete</h2>
                    <p>This chatbot instance has already been installed.</p>
                    
                    <?php
                    if (file_exists($lockFile)) {
                        $lockData = json_decode(file_get_contents($lockFile), true);
                        echo '<div class="info-box" style="margin-top: 20px; text-align: left;">';
                        echo '<h3>Installation Details</h3>';
                        echo '<ul>';
                        echo '<li><strong>Installed:</strong> ' . htmlspecialchars($lockData['installed_at'] ?? 'Unknown') . '</li>';
                        echo '<li><strong>PHP Version:</strong> ' . htmlspecialchars($lockData['php_version'] ?? 'Unknown') . '</li>';
                        echo '<li><strong>Database:</strong> ' . htmlspecialchars($lockData['database_type'] ?? 'Unknown') . '</li>';
                        echo '<li><strong>Migrations:</strong> ' . htmlspecialchars($lockData['migrations_run'] ?? '0') . ' applied</li>';
                        echo '</ul>';
                        echo '</div>';
                    }
                    ?>
                    
                    <div class="button-group" style="margin-top: 30px; justify-content: center;">
                        <a href="../public/admin/" class="btn btn-primary">Go to Admin Panel</a>
                        <a href="../" class="btn btn-secondary">Go to Chatbot</a>
                    </div>
                    
                    <div class="alert alert-warning" style="margin-top: 30px;">
                        <strong>⚠️ Need to reinstall?</strong><br>
                        To run the installation again, you must first delete the <code>.install.lock</code> file or 
                        <a href="?unlock=confirm&token=<?php echo urlencode($_SESSION['csrf_token']); ?>" style="color: #d63031; font-weight: bold;">click here to unlock</a>.
                        This will allow you to reconfigure the system but will not delete existing data.
                    </div>
                </div>
            <?php else: ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Errors:</strong>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Step 1: Welcome & Requirements -->
            <div class="step <?php echo $step === 1 ? 'active' : ''; ?>">
                <h2>Welcome to GPT Chatbot Setup</h2>
                
                <div class="info-box">
                    <h3>Before You Begin</h3>
                    <p>This installation wizard will help you configure your chatbot instance. Make sure you have:</p>
                    <ul>
                        <li>An OpenAI API key (<a href="https://platform.openai.com/api-keys" target="_blank">Get one here</a>)</li>
                        <li>PHP 8.0 or higher installed</li>
                        <li>Write permissions for the application directory</li>
                        <li>Database access (SQLite or MySQL)</li>
                    </ul>
                </div>
                
                <h3 style="margin-top: 30px; margin-bottom: 15px;">System Requirements Check</h3>
                
                <?php
                $checks = [
                    'PHP Version' => [
                        'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
                        'message' => 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.0.0', '>=') ? ' ✓' : ' - Requires 8.0+')
                    ],
                    'cURL Extension' => [
                        'status' => extension_loaded('curl'),
                        'message' => extension_loaded('curl') ? 'Installed' : 'Not installed (required for OpenAI API)'
                    ],
                    'JSON Extension' => [
                        'status' => extension_loaded('json'),
                        'message' => extension_loaded('json') ? 'Installed' : 'Not installed (required)'
                    ],
                    'PDO Extension' => [
                        'status' => extension_loaded('pdo'),
                        'message' => extension_loaded('pdo') ? 'Installed' : 'Not installed (required for database)'
                    ],
                    'SQLite PDO Driver' => [
                        'status' => extension_loaded('pdo_sqlite'),
                        'message' => extension_loaded('pdo_sqlite') ? 'Installed' : 'Not installed (optional, for SQLite)'
                    ],
                    'MySQL PDO Driver' => [
                        'status' => extension_loaded('pdo_mysql'),
                        'message' => extension_loaded('pdo_mysql') ? 'Installed' : 'Not installed (optional, for MySQL)'
                    ],
                    'Write Permissions' => [
                        'status' => is_writable(__DIR__ . '/..'),
                        'message' => is_writable(__DIR__ . '/..') ? 'Application directory is writable' : 'Cannot write to application directory'
                    ],
                ];
                
                $allPassed = true;
                foreach ($checks as $name => $check) {
                    $required = in_array($name, ['PHP Version', 'cURL Extension', 'JSON Extension', 'PDO Extension', 'Write Permissions']);
                    if ($required && !$check['status']) {
                        $allPassed = false;
                    }
                    
                    $class = $check['status'] ? 'success' : ($required ? 'error' : 'warning');
                    echo '<div class="status-check ' . $class . '">';
                    echo '<div class="icon">' . ($check['status'] ? '✓' : '✗') . '</div>';
                    echo '<div><strong>' . htmlspecialchars($name) . ':</strong> ' . htmlspecialchars($check['message']) . '</div>';
                    echo '</div>';
                }
                ?>
                
                <div class="button-group">
                    <div></div>
                    <?php if ($allPassed): ?>
                        <a href="?step=2" class="btn btn-primary">Continue →</a>
                    <?php else: ?>
                        <button class="btn btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">Fix requirements to continue</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Step 2: Configuration -->
            <div class="step <?php echo $step === 2 ? 'active' : ''; ?>">
                <h2>Configuration Settings</h2>
                
                <form method="POST" action="install.php">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="collapsible open">
                        <div class="collapsible-header" onclick="toggleCollapsible(this)">
                            <span>🔑 OpenAI Configuration (Required)</span>
                            <span>▼</span>
                        </div>
                        <div class="collapsible-content">
                            <div class="form-group">
                                <label for="openai_api_key">OpenAI API Key *</label>
                                <input type="text" id="openai_api_key" name="openai_api_key" required 
                                       placeholder="sk-...">
                                <small>Your OpenAI API key. Get it from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="api_type">Default API Type</label>
                                <select id="api_type" name="api_type">
                                    <option value="responses" selected>Responses API (Recommended)</option>
                                    <option value="chat">Chat Completions API</option>
                                </select>
                                <small>Choose the default API for conversations. Responses API supports prompts and tools.</small>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="openai_model">Chat Completions Model</label>
                                    <select id="openai_model" name="openai_model">
                                        <option value="gpt-4o-mini" selected>GPT-4o Mini</option>
                                        <option value="gpt-4o">GPT-4o</option>
                                        <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="responses_model">Responses API Model</label>
                                    <select id="responses_model" name="responses_model">
                                        <option value="gpt-4o-mini" selected>GPT-4o Mini</option>
                                        <option value="gpt-4o">GPT-4o</option>
                                        <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapsible">
                        <div class="collapsible-header" onclick="toggleCollapsible(this)">
                            <span>🗄️ Database Configuration</span>
                            <span>▶</span>
                        </div>
                        <div class="collapsible-content">
                            <div class="form-group">
                                <label for="database_type">Database Type</label>
                                <select id="database_type" name="database_type" onchange="toggleDatabaseConfig(this.value)">
                                    <option value="sqlite" selected>SQLite (File-based, no setup required)</option>
                                    <option value="mysql">MySQL / MariaDB</option>
                                </select>
                            </div>
                            
                            <div id="sqlite_config">
                                <div class="form-group">
                                    <label for="database_path">Database File Path</label>
                                    <input type="text" id="database_path" name="database_path" value="./data/chatbot.db">
                                    <small>Path to SQLite database file. Directory will be created automatically.</small>
                                </div>
                            </div>
                            
                            <div id="mysql_config" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="db_host">Host</label>
                                        <input type="text" id="db_host" name="db_host" value="mysql" placeholder="localhost">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="db_port">Port</label>
                                        <input type="text" id="db_port" name="db_port" value="3306" placeholder="3306">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="db_name">Database Name</label>
                                        <input type="text" id="db_name" name="db_name" value="chatbot" placeholder="chatbot">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="db_user">Username</label>
                                        <input type="text" id="db_user" name="db_user" value="chatbot" placeholder="chatbot">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_password">Password</label>
                                    <input type="password" id="db_password" name="db_password" placeholder="Enter database password">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapsible">
                        <div class="collapsible-header" onclick="toggleCollapsible(this)">
                            <span>🔐 Admin & Security</span>
                            <span>▶</span>
                        </div>
                        <div class="collapsible-content">
                            <div class="form-group">
                                <label for="admin_email">Admin Email</label>
                                <input type="email" id="admin_email" name="admin_email"
                                       value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ($_SESSION['install_config']['admin_email'] ?? '')); ?>"
                                       placeholder="admin@example.com">
                                <small>This email will be used to sign in to the admin panel.</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="admin_password">Admin Password</label>
                                    <input type="password" id="admin_password" name="admin_password" autocomplete="new-password"
                                           placeholder="Create a strong password">
                                    <small>Minimum 12 characters with uppercase, lowercase, numbers, and symbols.</small>
                                </div>

                                <div class="form-group">
                                    <label for="admin_password_confirm">Confirm Password</label>
                                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" autocomplete="new-password"
                                           placeholder="Re-enter the password">
                                </div>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" id="automation_seed_defaults" name="automation_seed_defaults" value="1"
                                       <?php echo $automationSeedDefaultsPrefill ? 'checked' : ''; ?>>
                                <label for="automation_seed_defaults">
                                    <strong>Write automation defaults</strong><br>
                                    <small>Writes DEFAULT_ADMIN_EMAIL and DEFAULT_ADMIN_PASSWORD into <code>.env</code> for CI/bootstrap flows.
                                        Rotate these secrets immediately after provisioning or prefer your secret manager.</small>
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="cors_origins">CORS Origins</label>
                                <input type="text" id="cors_origins" name="cors_origins" value="*">
                                <small>Allowed CORS origins. Use * for all or specify domains (comma-separated).</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_message_length">Max Message Length</label>
                                <input type="number" id="max_message_length" name="max_message_length" value="4000">
                                <small>Maximum characters allowed per message.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapsible">
                        <div class="collapsible-header" onclick="toggleCollapsible(this)">
                            <span>⚙️ Features & Options</span>
                            <span>▶</span>
                        </div>
                        <div class="collapsible-content">
                            <div class="checkbox-group">
                                <input type="checkbox" id="enable_file_upload" name="enable_file_upload" value="1">
                                <label for="enable_file_upload">Enable File Upload</label>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_file_size">Max File Size (bytes)</label>
                                    <input type="number" id="max_file_size" name="max_file_size" value="10485760">
                                    <small>10MB = 10485760 bytes</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="allowed_file_types">Allowed File Types</label>
                                    <input type="text" id="allowed_file_types" name="allowed_file_types" 
                                           value="txt,pdf,doc,docx,jpg,png">
                                </div>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="jobs_enabled" name="jobs_enabled" value="1" checked>
                                <label for="jobs_enabled">Enable Background Jobs</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="audit_enabled" name="audit_enabled" value="1" checked>
                                <label for="audit_enabled">Enable Audit Trail</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="audit_encrypt" name="audit_encrypt" value="1">
                                <label for="audit_encrypt">Encrypt Audit Data</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <a href="?step=1" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Save Configuration →</button>
                    </div>
                </form>
            </div>
            
            <!-- Step 3: Database Initialization -->
            <div class="step <?php echo $step === 3 ? 'active' : ''; ?>">
                <h2>Database Initialization</h2>
                
                <div class="alert alert-info">
                    <strong>ℹ️ Ready to Initialize</strong><br>
                    Your configuration has been saved. Click the button below to initialize the database and run migrations.
                </div>
                
                <div class="info-box">
                    <h3>What will happen:</h3>
                    <ul>
                        <li>Database connection will be established</li>
                        <li>Required tables will be created</li>
                        <li>All migrations will be executed</li>
                        <li>Installation lock file will be created</li>
                    </ul>
                </div>
                
                <form method="POST" action="install.php">
                    <input type="hidden" name="step" value="3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="button-group">
                        <a href="?step=2" class="btn btn-secondary">← Back to Configuration</a>
                        <button type="submit" class="btn btn-primary">Initialize Database →</button>
                    </div>
                </form>
            </div>
            
            <!-- Step 4: Complete -->
            <div class="step <?php echo $step === 4 ? 'active' : ''; ?>">
                <div style="text-align: center;">
                    <div style="font-size: 72px; margin-bottom: 20px;">🎉</div>
                    <h2>Installation Complete!</h2>
                    <p style="font-size: 18px; color: #666; margin: 20px 0;">
                        Your GPT Chatbot instance has been successfully configured and is ready to use.
                    </p>
                </div>
                
                <div class="info-box" style="margin-top: 30px;">
                    <h3>Next Steps:</h3>
                    <ul>
                        <li><strong>Access Admin Panel:</strong> Configure agents, prompts, and vector stores</li>
                        <li><strong>Create Your First Agent:</strong> Set up AI agents for different use cases</li>
                        <li><strong>Test the Chatbot:</strong> Try the chat interface on your website</li>
                        <li><strong>Read Documentation:</strong> Learn about advanced features and customization</li>
                    </ul>
                </div>
                
                <?php
                $configuredAdminEmail = $_SESSION['install_config']['admin_email'] ?? null;
                $adminSetupStatus = $_SESSION['install_config']['admin_setup_status'] ?? [];
                if ($configuredAdminEmail) {
                    $created = !empty($adminSetupStatus['created']);
                    $alreadyExisted = !empty($adminSetupStatus['already_existed']);
                    $alertClass = $created ? 'alert alert-success' : 'alert alert-info';
                    $statusIcon = $created ? '✅' : 'ℹ️';
                    $headline = $created ? 'Super Admin Account Created' : 'Super Admin Account Ready';

                    if ($alreadyExisted && !$created) {
                        $headline = 'Super Admin Account Already Exists';
                        $statusIcon = 'ℹ️';
                    }

                    echo '<div class="' . $alertClass . '" style="margin-top: 30px; text-align: left;">';
                    echo '<strong>' . $statusIcon . ' ' . $headline . '</strong><br>';
                    echo 'Email: <code style="font-size: 14px; word-break: break-all;">' . htmlspecialchars($configuredAdminEmail) . '</code><br>';

                    if ($created) {
                        echo '<small>A new super-admin account was created during installation.</small>';
                    } elseif ($alreadyExisted) {
                        echo '<small>An existing super-admin account with this email was detected and left unchanged.</small>';
                    } else {
                        echo '<small>Use the credentials you configured in Step 2 to access the admin panel.</small>';
                    }

                    echo '<div style="margin-top: 12px;">';
                    echo '<ul style="margin-left: 18px;">';
                    echo '<li>Store the password you set in Step 2 in a secure password manager.</li>';
                    echo '<li>Log in to the admin panel and change the password immediately after installation.</li>';
                    echo '</ul>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
                
                <div class="button-group" style="margin-top: 30px; justify-content: center;">
                    <a href="../public/admin/" class="btn btn-primary">Open Admin Panel</a>
                    <a href="../" class="btn btn-secondary">View Chatbot</a>
                </div>
                
                <div style="margin-top: 40px; text-align: center; color: #888;">
                    <p>📚 <a href="../docs/" style="color: #667eea;">Read the Documentation</a> | 
                       🐛 <a href="https://github.com/suporterfid/gpt-chatbot-boilerplate/issues" style="color: #667eea;">Report Issues</a></p>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleCollapsible(header) {
            const collapsible = header.parentElement;
            const arrow = header.querySelector('span:last-child');
            
            collapsible.classList.toggle('open');
            arrow.textContent = collapsible.classList.contains('open') ? '▼' : '▶';
        }
        
        function toggleDatabaseConfig(type) {
            const sqliteConfig = document.getElementById('sqlite_config');
            const mysqlConfig = document.getElementById('mysql_config');
            
            if (type === 'mysql') {
                sqliteConfig.style.display = 'none';
                mysqlConfig.style.display = 'block';
            } else {
                sqliteConfig.style.display = 'block';
                mysqlConfig.style.display = 'none';
            }
        }
    </script>
</body>
</html>
