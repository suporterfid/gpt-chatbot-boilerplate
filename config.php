<?php
/**
 * GPT Chatbot Configuration - Enhanced with Responses API Support
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

if (!function_exists('getEnvValue')) {
    function getEnvValue(string $key) {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }
}

if (!function_exists('parseFlexibleEnvArray')) {
    function parseFlexibleEnvArray($value, array $options = []): array {
        $allowJson = $options['allow_json'] ?? true;
        $delimiter = $options['delimiter'] ?? ',';
        $map = $options['map'] ?? null;
        $trimStrings = $options['trim_strings'] ?? true;
        $filterEmpty = $options['filter_empty'] ?? true;

        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }
        }

        $items = $value;

        if (is_string($items) && $allowJson) {
            $decoded = json_decode($items, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $items = $decoded;
            }
        }

        if (is_string($items)) {
            $items = array_map('trim', explode($delimiter, $items));
        }

        if (is_scalar($items) && !is_array($items)) {
            $items = [(string)$items];
        }

        if (!is_array($items)) {
            return [];
        }

        // Normalize associative arrays to a single entry
        if (array_values($items) !== $items) {
            $items = [$items];
        }

        $result = [];

        foreach ($items as $item) {
            if ($map && is_callable($map)) {
                $item = $map($item);
            }

            if ($item === null) {
                continue;
            }

            if ($trimStrings && is_string($item)) {
                $item = trim($item);
            }

            if ($filterEmpty) {
                if (is_string($item) && $item === '') {
                    continue;
                }

                if (is_array($item) && empty($item)) {
                    continue;
                }
            }

            $result[] = $item;
        }

        return $result;
    }
}

if (!function_exists('parseResponsesToolsEnv')) {
    function parseResponsesToolsEnv($value): array {
        return parseFlexibleEnvArray($value, [
            'map' => function($item) {
                if (is_string($item) || is_numeric($item)) {
                    $type = strtolower(trim((string)$item));
                    return $type === '' ? null : ['type' => $type];
                }

                if (is_array($item)) {
                    if (isset($item['type']) && is_string($item['type'])) {
                        $item['type'] = strtolower(trim($item['type']));
                    }

                    return $item;
                }

                return null;
            },
            'trim_strings' => false,
        ]);
    }
}

if (!function_exists('parseResponsesVectorStoreIdsEnv')) {
    function parseResponsesVectorStoreIdsEnv($value): array {
        $ids = parseFlexibleEnvArray($value, [
            'map' => function($item) {
                if (is_string($item) || is_numeric($item)) {
                    $id = trim((string)$item);
                    return $id === '' ? null : $id;
                }

                return null;
            }
        ]);

        if (empty($ids)) {
            return [];
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('parseIntFromEnv')) {
    function parseIntFromEnv($value, int $min, int $max): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => $min,
                'max_range' => $max,
            ],
        ]);

        if ($filtered === false) {
            return null;
        }

        return (int)$filtered;
    }
}

if (!function_exists('parseResponseFormatEnv')) {
    function parseResponseFormatEnv($value): ?array {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        return null;
    }
}

$responsesToolsEnv = getEnvValue('RESPONSES_TOOLS');
$responsesVectorStoreIdsEnv = getEnvValue('RESPONSES_VECTOR_STORE_IDS');
$responsesMaxResultsEnv = getEnvValue('RESPONSES_MAX_NUM_RESULTS');
$responsesResponseFormatEnv = getEnvValue('RESPONSES_RESPONSE_FORMAT');

$defaultResponsesTools = parseResponsesToolsEnv($responsesToolsEnv);
$defaultVectorStoreIds = parseResponsesVectorStoreIdsEnv($responsesVectorStoreIdsEnv);
$defaultMaxNumResults = parseIntFromEnv($responsesMaxResultsEnv, 1, 200);
$defaultResponseFormat = parseResponseFormatEnv($responsesResponseFormatEnv);

$config = [
    // API Configuration - Choose between 'chat' or 'responses'
    'api_type' => $_ENV['API_TYPE'] ?? getenv('API_TYPE') ?: 'responses',

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

    // Responses API Configuration
    'responses' => [
        'model' => $_ENV['RESPONSES_MODEL'] ?? getenv('RESPONSES_MODEL') ?: ($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini'),
        'temperature' => (float)($_ENV['RESPONSES_TEMPERATURE'] ?? getenv('RESPONSES_TEMPERATURE') ?: ($_ENV['OPENAI_TEMPERATURE'] ?? getenv('OPENAI_TEMPERATURE') ?: 0.7)),
        'max_output_tokens' => (int)($_ENV['RESPONSES_MAX_OUTPUT_TOKENS'] ?? getenv('RESPONSES_MAX_OUTPUT_TOKENS') ?: 1024),
        'top_p' => (float)($_ENV['RESPONSES_TOP_P'] ?? getenv('RESPONSES_TOP_P') ?: ($_ENV['OPENAI_TOP_P'] ?? getenv('OPENAI_TOP_P') ?: 1.0)),
        'frequency_penalty' => (float)($_ENV['RESPONSES_FREQUENCY_PENALTY'] ?? getenv('RESPONSES_FREQUENCY_PENALTY') ?: ($_ENV['OPENAI_FREQUENCY_PENALTY'] ?? getenv('OPENAI_FREQUENCY_PENALTY') ?: 0.0)),
        'presence_penalty' => (float)($_ENV['RESPONSES_PRESENCE_PENALTY'] ?? getenv('RESPONSES_PRESENCE_PENALTY') ?: ($_ENV['OPENAI_PRESENCE_PENALTY'] ?? getenv('OPENAI_PRESENCE_PENALTY') ?: 0.0)),
        'system_message' => $_ENV['RESPONSES_SYSTEM_MESSAGE'] ?? getenv('RESPONSES_SYSTEM_MESSAGE') ?: ($_ENV['SYSTEM_MESSAGE'] ?? getenv('SYSTEM_MESSAGE') ?: 'You are a helpful AI assistant.'),
        'prompt_id' => $_ENV['RESPONSES_PROMPT_ID'] ?? getenv('RESPONSES_PROMPT_ID') ?: '',
        'prompt_version' => $_ENV['RESPONSES_PROMPT_VERSION'] ?? getenv('RESPONSES_PROMPT_VERSION') ?: '',
        'default_tools' => $defaultResponsesTools,
        'default_vector_store_ids' => $defaultVectorStoreIds,
        'default_max_num_results' => $defaultMaxNumResults,
        'response_format' => $defaultResponseFormat,
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
    ],

    // Admin Configuration
    'admin' => [
        'enabled' => filter_var($_ENV['ADMIN_ENABLED'] ?? getenv('ADMIN_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'token' => $_ENV['ADMIN_TOKEN'] ?? getenv('ADMIN_TOKEN') ?: '',
        'database_url' => $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '',
        'database_path' => $_ENV['DATABASE_PATH'] ?? getenv('DATABASE_PATH') ?: __DIR__ . '/data/chatbot.db',
        'rate_limit_requests' => (int)($_ENV['ADMIN_RATE_LIMIT_REQUESTS'] ?? getenv('ADMIN_RATE_LIMIT_REQUESTS') ?: 300),
        'rate_limit_window' => (int)($_ENV['ADMIN_RATE_LIMIT_WINDOW'] ?? getenv('ADMIN_RATE_LIMIT_WINDOW') ?: 60),
        'jobs_enabled' => filter_var($_ENV['JOBS_ENABLED'] ?? getenv('JOBS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ],
    
    // Channels Configuration (WhatsApp, Telegram, etc.)
    'channels' => [
        'whatsapp' => [
            'enabled' => filter_var($_ENV['CHANNELS_WHATSAPP_ENABLED'] ?? getenv('CHANNELS_WHATSAPP_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            'default_zapi_base_url' => $_ENV['ZAPI_BASE_URL'] ?? getenv('ZAPI_BASE_URL') ?: 'https://api.z-api.io',
            'default_timeout_ms' => (int)($_ENV['ZAPI_TIMEOUT_MS'] ?? getenv('ZAPI_TIMEOUT_MS') ?: 30000),
            'default_retries' => (int)($_ENV['ZAPI_RETRIES'] ?? getenv('ZAPI_RETRIES') ?: 3),
            'default_reply_chunk_size' => (int)($_ENV['WHATSAPP_REPLY_CHUNK_SIZE'] ?? getenv('WHATSAPP_REPLY_CHUNK_SIZE') ?: 4000),
            'default_allow_media' => filter_var($_ENV['WHATSAPP_ALLOW_MEDIA'] ?? getenv('WHATSAPP_ALLOW_MEDIA') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'default_max_media_size' => (int)($_ENV['WHATSAPP_MAX_MEDIA_SIZE'] ?? getenv('WHATSAPP_MAX_MEDIA_SIZE') ?: 10485760), // 10MB
        ]
    ],
    
    // Auditing Configuration
    'auditing' => [
        'enabled' => filter_var($_ENV['AUDIT_ENABLED'] ?? getenv('AUDIT_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'encrypt_at_rest' => filter_var($_ENV['AUDIT_ENCRYPT'] ?? getenv('AUDIT_ENCRYPT') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'encryption_key' => $_ENV['AUDIT_ENC_KEY'] ?? getenv('AUDIT_ENC_KEY') ?: '',
        'retention_days' => (int)($_ENV['AUDIT_RETENTION_DAYS'] ?? getenv('AUDIT_RETENTION_DAYS') ?: 90),
        'pii_redaction_patterns' => $_ENV['AUDIT_PII_PATTERNS'] ?? getenv('AUDIT_PII_PATTERNS') ?: '',
        'sample_rate' => (float)($_ENV['AUDIT_SAMPLE_RATE'] ?? getenv('AUDIT_SAMPLE_RATE') ?: 1.0),
        'evaluate_async' => filter_var($_ENV['AUDIT_EVAL_ASYNC'] ?? getenv('AUDIT_EVAL_ASYNC') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'database_url' => $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '',
        'database_path' => $_ENV['DATABASE_PATH'] ?? getenv('DATABASE_PATH') ?: __DIR__ . '/data/chatbot.db',
    ],
    
    // LeadSense Configuration - Commercial Opportunity Detection
    'leadsense' => [
        'enabled' => filter_var($_ENV['LEADSENSE_ENABLED'] ?? getenv('LEADSENSE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'intent_threshold' => (float)($_ENV['LEADSENSE_INTENT_THRESHOLD'] ?? getenv('LEADSENSE_INTENT_THRESHOLD') ?: 0.6),
        'score_threshold' => (int)($_ENV['LEADSENSE_SCORE_THRESHOLD'] ?? getenv('LEADSENSE_SCORE_THRESHOLD') ?: 70),
        'followup_enabled' => filter_var($_ENV['LEADSENSE_FOLLOWUP_ENABLED'] ?? getenv('LEADSENSE_FOLLOWUP_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'pii_redaction' => filter_var($_ENV['LEADSENSE_PII_REDACTION'] ?? getenv('LEADSENSE_PII_REDACTION') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'database_url' => $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '',
        'database_path' => $_ENV['DATABASE_PATH'] ?? getenv('DATABASE_PATH') ?: __DIR__ . '/data/chatbot.db',
        
        // Notification settings
        'notify' => [
            'slack_webhook_url' => $_ENV['LEADSENSE_SLACK_WEBHOOK'] ?? getenv('LEADSENSE_SLACK_WEBHOOK') ?: '',
            'webhook_url' => $_ENV['LEADSENSE_WEBHOOK_URL'] ?? getenv('LEADSENSE_WEBHOOK_URL') ?: '',
            'webhook_secret' => $_ENV['LEADSENSE_WEBHOOK_SECRET'] ?? getenv('LEADSENSE_WEBHOOK_SECRET') ?: '',
        ],
        
        // Storage settings
        'storage' => [
            'encryption' => filter_var($_ENV['LEADSENSE_ENCRYPTION'] ?? getenv('LEADSENSE_ENCRYPTION') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            'encryption_key' => $_ENV['LEADSENSE_ENC_KEY'] ?? getenv('LEADSENSE_ENC_KEY') ?: '',
        ],
        
        // Extractor settings
        'extractor' => [
            'max_tokens' => (int)($_ENV['LEADSENSE_MAX_TOKENS'] ?? getenv('LEADSENSE_MAX_TOKENS') ?: 1000),
            'max_fields' => (int)($_ENV['LEADSENSE_MAX_FIELDS'] ?? getenv('LEADSENSE_MAX_FIELDS') ?: 20),
            'context_window' => (int)($_ENV['LEADSENSE_CONTEXT_WINDOW'] ?? getenv('LEADSENSE_CONTEXT_WINDOW') ?: 10),
        ],
        
        // Scoring settings
        'scoring' => [
            'mode' => $_ENV['LEADSENSE_SCORING_MODE'] ?? getenv('LEADSENSE_SCORING_MODE') ?: 'rules',
            'weights' => [
                'intent_low' => 20,
                'intent_medium' => 50,
                'intent_high' => 75,
                'decision_maker' => 15,
                'active_project' => 10,
                'icp_fit' => 10,
                'urgency' => 10,
                'no_contact' => -10,
            ],
        ],
        
        // ML settings (future)
        'ml' => [
            'endpoint' => $_ENV['LEADSENSE_ML_ENDPOINT'] ?? getenv('LEADSENSE_ML_ENDPOINT') ?: '',
            'api_key' => $_ENV['LEADSENSE_ML_API_KEY'] ?? getenv('LEADSENSE_ML_API_KEY') ?: '',
        ],
        
        // Rate limiting
        'debounce_window' => (int)($_ENV['LEADSENSE_DEBOUNCE_WINDOW'] ?? getenv('LEADSENSE_DEBOUNCE_WINDOW') ?: 300), // 5 minutes
        'max_daily_notifications' => (int)($_ENV['LEADSENSE_MAX_DAILY_NOTIFICATIONS'] ?? getenv('LEADSENSE_MAX_DAILY_NOTIFICATIONS') ?: 100),
    ],
    
    // Prompt Builder Configuration - AI Agent Specification Generator
    'prompt_builder' => [
        'enabled' => filter_var($_ENV['PROMPT_BUILDER_ENABLED'] ?? getenv('PROMPT_BUILDER_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'model' => $_ENV['PROMPT_BUILDER_MODEL'] ?? getenv('PROMPT_BUILDER_MODEL') ?: ($_ENV['RESPONSES_MODEL'] ?? getenv('RESPONSES_MODEL') ?: 'gpt-4o-mini'),
        'timeout_ms' => (int)($_ENV['PROMPT_BUILDER_TIMEOUT_MS'] ?? getenv('PROMPT_BUILDER_TIMEOUT_MS') ?: 20000),
        'default_guardrails' => explode(',', $_ENV['PROMPT_BUILDER_DEFAULT_GUARDRAILS'] ?? getenv('PROMPT_BUILDER_DEFAULT_GUARDRAILS') ?: 'hallucination_prevention,scope_restriction'),
        'encryption_at_rest' => filter_var($_ENV['PROMPT_BUILDER_ENCRYPTION'] ?? getenv('PROMPT_BUILDER_ENCRYPTION') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'encryption_key' => $_ENV['PROMPT_BUILDER_ENC_KEY'] ?? getenv('PROMPT_BUILDER_ENC_KEY') ?: ($_ENV['AUDIT_ENC_KEY'] ?? getenv('AUDIT_ENC_KEY') ?: ''),
        'rate_limit_per_min' => (int)($_ENV['PROMPT_BUILDER_RATE_LIMIT'] ?? getenv('PROMPT_BUILDER_RATE_LIMIT') ?: 10),
        'audit_enabled' => filter_var($_ENV['PROMPT_BUILDER_AUDIT'] ?? getenv('PROMPT_BUILDER_AUDIT') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'templates_path' => __DIR__ . '/includes/PromptBuilder/templates/guardrails',
    ]
];

// Validate critical configuration
if (empty($config['openai']['api_key'])) {
    error_log('WARNING: OpenAI API key not configured. Please set OPENAI_API_KEY environment variable.');
}

// Validate API type
if (!in_array($config['api_type'], ['chat', 'responses'], true)) {
    error_log('WARNING: Invalid API_TYPE configured. Falling back to responses.');
    $config['api_type'] = 'responses';
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