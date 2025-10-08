<?php
/**
 * GPT Chatbot Configuration - Enhanced with Assistants API Support
 */

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $env = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$config = [
    // API Configuration - Choose between 'chat' or 'assistants'
    'api_type' => $_ENV['API_TYPE'] ?? getenv('API_TYPE') ?: 'chat',

    // OpenAI Configuration
    'openai' => [
        'api_key' => $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '',
        'organization' => $_ENV['OPENAI_ORGANIZATION'] ?? getenv('OPENAI_ORGANIZATION') ?: '',
        'base_url' => $_ENV['OPENAI_BASE_URL'] ?? getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1',
    ],

    // Chat Completions API Configuration
    'chat' => [
        'model' => $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-3.5-turbo',
        'temperature' => (float)($_ENV['OPENAI_TEMPERATURE'] ?? getenv('OPENAI_TEMPERATURE') ?: 0.7),
        'max_tokens' => (int)($_ENV['OPENAI_MAX_TOKENS'] ?? getenv('OPENAI_MAX_TOKENS') ?: 1000),
        'top_p' => (float)($_ENV['OPENAI_TOP_P'] ?? getenv('OPENAI_TOP_P') ?: 1.0),
        'frequency_penalty' => (float)($_ENV['OPENAI_FREQUENCY_PENALTY'] ?? getenv('OPENAI_FREQUENCY_PENALTY') ?: 0.0),
        'presence_penalty' => (float)($_ENV['OPENAI_PRESENCE_PENALTY'] ?? getenv('OPENAI_PRESENCE_PENALTY') ?: 0.0),
        'system_message' => $_ENV['SYSTEM_MESSAGE'] ?? getenv('SYSTEM_MESSAGE') ?: 'You are a helpful AI assistant.',
    ],

    // Assistants API Configuration
    'assistants' => [
        'assistant_id' => $_ENV['ASSISTANT_ID'] ?? getenv('ASSISTANT_ID') ?: '',
        'create_assistant' => filter_var($_ENV['CREATE_ASSISTANT'] ?? getenv('CREATE_ASSISTANT') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'assistant_name' => $_ENV['ASSISTANT_NAME'] ?? getenv('ASSISTANT_NAME') ?: 'ChatBot Assistant',
        'assistant_description' => $_ENV['ASSISTANT_DESCRIPTION'] ?? getenv('ASSISTANT_DESCRIPTION') ?: 'A helpful AI assistant for website visitors',
        'assistant_instructions' => $_ENV['ASSISTANT_INSTRUCTIONS'] ?? getenv('ASSISTANT_INSTRUCTIONS') ?: 'You are a helpful AI assistant. Answer questions clearly and concisely.',
        'model' => $_ENV['ASSISTANT_MODEL'] ?? getenv('ASSISTANT_MODEL') ?: 'gpt-3.5-turbo',
        'temperature' => (float)($_ENV['ASSISTANT_TEMPERATURE'] ?? getenv('ASSISTANT_TEMPERATURE') ?: 0.7),
        'tools' => explode(',', $_ENV['ASSISTANT_TOOLS'] ?? getenv('ASSISTANT_TOOLS') ?: ''),
        'file_search' => filter_var($_ENV['ASSISTANT_FILE_SEARCH'] ?? getenv('ASSISTANT_FILE_SEARCH') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'code_interpreter' => filter_var($_ENV['ASSISTANT_CODE_INTERPRETER'] ?? getenv('ASSISTANT_CODE_INTERPRETER') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'max_completion_tokens' => (int)($_ENV['ASSISTANT_MAX_TOKENS'] ?? getenv('ASSISTANT_MAX_TOKENS') ?: 1000),
        'thread_cleanup_hours' => (int)($_ENV['THREAD_CLEANUP_HOURS'] ?? getenv('THREAD_CLEANUP_HOURS') ?: 24),
    ],

    // Session & Storage Configuration
    'storage' => [
        'type' => $_ENV['STORAGE_TYPE'] ?? getenv('STORAGE_TYPE') ?: 'session', // 'session', 'file', 'database'
        'path' => $_ENV['STORAGE_PATH'] ?? getenv('STORAGE_PATH') ?: sys_get_temp_dir() . '/chatbot_threads',
        'database_url' => $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '',
    ],

    // Chat Configuration
    'chat_config' => [
        'max_messages' => (int)($_ENV['CHAT_MAX_MESSAGES'] ?? getenv('CHAT_MAX_MESSAGES') ?: 50),
        'session_timeout' => (int)($_ENV['CHAT_SESSION_TIMEOUT'] ?? getenv('CHAT_SESSION_TIMEOUT') ?: 3600),
        'rate_limit_requests' => (int)($_ENV['CHAT_RATE_LIMIT'] ?? getenv('CHAT_RATE_LIMIT') ?: 60),
        'rate_limit_window' => (int)($_ENV['CHAT_RATE_WINDOW'] ?? getenv('CHAT_RATE_WINDOW') ?: 60),
        'enable_logging' => filter_var($_ENV['CHAT_ENABLE_LOGGING'] ?? getenv('CHAT_ENABLE_LOGGING') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'enable_file_upload' => filter_var($_ENV['ENABLE_FILE_UPLOAD'] ?? getenv('ENABLE_FILE_UPLOAD') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'max_file_size' => (int)($_ENV['MAX_FILE_SIZE'] ?? getenv('MAX_FILE_SIZE') ?: 10485760), // 10MB
        'allowed_file_types' => explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? getenv('ALLOWED_FILE_TYPES') ?: 'txt,pdf,doc,docx,jpg,png'),
    ],

    // Security Configuration
    'security' => [
        'allowed_origins' => explode(',', $_ENV['CORS_ORIGINS'] ?? getenv('CORS_ORIGINS') ?: '*'),
        'validate_referer' => filter_var($_ENV['VALIDATE_REFERER'] ?? getenv('VALIDATE_REFERER') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'api_key_validation' => filter_var($_ENV['API_KEY_VALIDATION'] ?? getenv('API_KEY_VALIDATION') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'sanitize_input' => filter_var($_ENV['SANITIZE_INPUT'] ?? getenv('SANITIZE_INPUT') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'max_message_length' => (int)($_ENV['MAX_MESSAGE_LENGTH'] ?? getenv('MAX_MESSAGE_LENGTH') ?: 4000),
        'csrf_protection' => filter_var($_ENV['CSRF_PROTECTION'] ?? getenv('CSRF_PROTECTION') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],

    // WebSocket Configuration
    'websocket' => [
        'enabled' => filter_var($_ENV['WEBSOCKET_ENABLED'] ?? getenv('WEBSOCKET_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'host' => $_ENV['WEBSOCKET_HOST'] ?? getenv('WEBSOCKET_HOST') ?: '0.0.0.0',
        'port' => (int)($_ENV['WEBSOCKET_PORT'] ?? getenv('WEBSOCKET_PORT') ?: 8080),
        'ssl' => filter_var($_ENV['WEBSOCKET_SSL'] ?? getenv('WEBSOCKET_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'ssl_cert' => $_ENV['WEBSOCKET_SSL_CERT'] ?? getenv('WEBSOCKET_SSL_CERT') ?: '',
        'ssl_key' => $_ENV['WEBSOCKET_SSL_KEY'] ?? getenv('WEBSOCKET_SSL_KEY') ?: '',
    ],

    // Logging Configuration
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? getenv('LOG_LEVEL') ?: 'info',
        'file' => $_ENV['LOG_FILE'] ?? getenv('LOG_FILE') ?: 'logs/chatbot.log',
        'max_size' => (int)($_ENV['LOG_MAX_SIZE'] ?? getenv('LOG_MAX_SIZE') ?: 10485760), // 10MB
        'max_files' => (int)($_ENV['LOG_MAX_FILES'] ?? getenv('LOG_MAX_FILES') ?: 5),
    ],

    // Performance Configuration
    'performance' => [
        'cache_enabled' => filter_var($_ENV['CACHE_ENABLED'] ?? getenv('CACHE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'cache_ttl' => (int)($_ENV['CACHE_TTL'] ?? getenv('CACHE_TTL') ?: 3600),
        'compression_enabled' => filter_var($_ENV['COMPRESSION_ENABLED'] ?? getenv('COMPRESSION_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ]
];

// Validate critical configuration
if (empty($config['openai']['api_key'])) {
    error_log('WARNING: OpenAI API key not configured. Please set OPENAI_API_KEY environment variable.');
}

// Validate API type specific configuration
if ($config['api_type'] === 'assistants') {
    if (empty($config['assistants']['assistant_id']) && !$config['assistants']['create_assistant']) {
        error_log('WARNING: Assistant API requires ASSISTANT_ID or CREATE_ASSISTANT=true');
    }
}

// Create storage directory if needed
if ($config['storage']['type'] === 'file') {
    $storageDir = $config['storage']['path'];
    if (!file_exists($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
}

return $config;
?>