<?php
/**
 * WordPress Specialized Agent
 *
 * Manages WordPress content creation, updates, and queries through the WordPress REST API.
 *
 * Features:
 * - Create and publish blog posts
 * - Update existing posts
 * - Search and query WordPress content
 * - Manage post metadata (categories, tags, featured images)
 * - Support for custom post types
 *
 * @package ChatbotBoilerplate\Agents
 * @version 1.0.0
 */

require_once __DIR__ . '/../../includes/Agents/AbstractSpecializedAgent.php';
require_once __DIR__ . '/tools/WordPressApiClient.php';

use ChatbotBoilerplate\Agents\AbstractSpecializedAgent;
use ChatbotBoilerplate\Exceptions\AgentValidationException;
use ChatbotBoilerplate\Exceptions\AgentProcessingException;

class WordPressAgent extends AbstractSpecializedAgent
{
    /**
     * WordPress API client
     * @var WordPressApiClient
     */
    private $wpClient;

    /**
     * Lazy-loaded dependencies for Pro workflow
     * @var object|null
     */
    private $configurationService;
    private $queueService;
    private $generatorService;
    private $publisherService;
    private $executionLogger;
    private $credentialManager;

    /**
     * Cache decrypted secrets per request lifecycle
     * @var array<string, mixed>
     */
    private $credentialCache = [];

    /**
     * Supported actions for direct WordPress operations
     */
    private const ACTION_CREATE_POST = 'create_post';
    private const ACTION_UPDATE_POST = 'update_post';
    private const ACTION_SEARCH_POSTS = 'search_posts';
    private const ACTION_GET_POST = 'get_post';
    private const ACTION_LIST_CATEGORIES = 'list_categories';

    /**
     * Workflow orchestration actions aligned with automation phases
     * - queue_article: enqueue or pick up items for processing
     * - generate_structure: create outlines/metadata for the article
     * - write_chapters: draft chapter content
     * - generate_assets: create images and other assets
     * - assemble_article: merge chapters/assets into publishable output
     * - publish_article: push assembled content to WordPress
     * - monitor_workflow: check processing status or health
     * - fetch_execution_log: retrieve execution logs for debugging
     * - manage_internal_links: maintain internal link repository
     */
    private const ACTION_QUEUE_ARTICLE = 'queue_article';
    private const ACTION_GENERATE_STRUCTURE = 'generate_structure';
    private const ACTION_WRITE_CHAPTERS = 'write_chapters';
    private const ACTION_GENERATE_ASSETS = 'generate_assets';
    private const ACTION_ASSEMBLE_ARTICLE = 'assemble_article';
    private const ACTION_PUBLISH_ARTICLE = 'publish_article';
    private const ACTION_MONITOR_WORKFLOW = 'monitor_workflow';
    private const ACTION_FETCH_EXECUTION_LOG = 'fetch_execution_log';
    private const ACTION_MANAGE_INTERNAL_LINKS = 'manage_internal_links';

    // ==================== METADATA ====================

    public function getAgentType(): string
    {
        return 'wordpress';
    }

    public function getDisplayName(): string
    {
        return 'WordPress Content Manager';
    }

    public function getDescription(): string
    {
        return 'Specialized agent for managing WordPress content. Can create, update, and query blog posts, pages, and custom post types through the WordPress REST API.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['wp_site_url', 'wp_username', 'wp_app_password'],
            'properties' => [
                'wp_site_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'WordPress site URL (e.g., https://example.com)',
                    'examples' => ['https://example.com', 'https://blog.example.com']
                ],
                'wp_username' => [
                    'type' => 'string',
                    'description' => 'WordPress username for authentication',
                    'minLength' => 1
                ],
                'wp_app_password' => [
                    'type' => 'string',
                    'description' => 'WordPress Application Password (supports ${VAR_NAME})',
                    'minLength' => 20,
                    'sensitive' => true
                ],
                'default_category' => [
                    'type' => 'string',
                    'description' => 'Default category slug for new posts',
                    'default' => 'uncategorized'
                ],
                'default_status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'publish', 'pending', 'private'],
                    'default' => 'draft',
                    'description' => 'Default post status for new posts'
                ],
                'default_author_id' => [
                    'type' => 'integer',
                    'description' => 'Default author ID for posts',
                    'default' => 1,
                    'minimum' => 1
                ],
                'auto_publish' => [
                    'type' => 'boolean',
                    'description' => 'Automatically publish posts without review',
                    'default' => false
                ],
                'post_type' => [
                    'type' => 'string',
                    'description' => 'Post type to work with',
                    'default' => 'post',
                    'enum' => ['post', 'page']
                ],
                'enable_featured_images' => [
                    'type' => 'boolean',
                    'description' => 'Enable featured image support',
                    'default' => false
                ],
                'api_timeout_ms' => [
                    'type' => 'integer',
                    'description' => 'API request timeout in milliseconds',
                    'default' => 30000,
                    'minimum' => 5000,
                    'maximum' => 120000
                ],
                'max_posts_per_request' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of posts to fetch in search',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ],
                'configuration_id' => [
                    'type' => 'string',
                    'description' => 'Identifier referencing a stored WordPress Blog Automation configuration record',
                    'minLength' => 1
                ],
                'article_queue_id' => [
                    'type' => 'string',
                    'description' => 'Queue ID used by the automation orchestrator for this agent',
                    'minLength' => 1
                ],
                'workflow_phases' => [
                    'type' => 'object',
                    'description' => 'Toggle the automation phases that this agent should run',
                    'properties' => [
                        'generate_structure' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Toggle chapter outline and metadata generation'
                        ],
                        'write_chapters' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Toggle chapter content drafting'
                        ],
                        'generate_assets' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Toggle featured/chapter image generation'
                        ],
                        'assemble_article' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Toggle content assembly & markdown to HTML conversion'
                        ],
                        'publish_article' => [
                            'type' => 'boolean',
                            'default' => false,
                            'description' => 'Automatically publish to WordPress after assembly'
                        ]
                    ],
                    'default' => []
                ],
                'content_parameters' => [
                    'type' => 'object',
                    'description' => 'Constraints for generated long-form content',
                    'properties' => [
                        'number_of_chapters' => [
                            'type' => 'integer',
                            'default' => 6,
                            'minimum' => 1,
                            'maximum' => 20,
                            'description' => 'Desired number of body chapters'
                        ],
                        'max_word_count' => [
                            'type' => 'integer',
                            'default' => 1800,
                            'minimum' => 500,
                            'maximum' => 6000,
                            'description' => 'Upper bound for article word count'
                        ],
                        'introduction_length' => [
                            'type' => 'integer',
                            'default' => 180,
                            'minimum' => 50,
                            'maximum' => 600,
                            'description' => 'Introduction target length (words)'
                        ],
                        'conclusion_length' => [
                            'type' => 'integer',
                            'default' => 180,
                            'minimum' => 50,
                            'maximum' => 600,
                            'description' => 'Conclusion target length (words)'
                        ],
                        'tone' => [
                            'type' => 'string',
                            'description' => 'Writing tone guidance (e.g., authoritative, friendly)'
                        ],
                        'target_audience' => [
                            'type' => 'string',
                            'description' => 'Audience persona/role description used in prompts'
                        ]
                    ],
                    'default' => []
                ],
                'cta' => [
                    'type' => 'object',
                    'description' => 'Call-to-action copy injected into articles',
                    'properties' => [
                        'message' => [
                            'type' => 'string',
                            'description' => 'CTA copy appended to conclusion'
                        ],
                        'url' => [
                            'type' => 'string',
                            'format' => 'uri',
                            'description' => 'Destination URL for CTA'
                        ],
                        'company_offering' => [
                            'type' => 'string',
                            'description' => 'Short description of the offer or product'
                        ]
                    ],
                    'default' => []
                ],
                'seo_preferences' => [
                    'type' => 'object',
                    'description' => 'SEO metadata and snippet guidelines',
                    'properties' => [
                        'primary_keywords' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Primary keywords that must be included in copy'
                        ],
                        'secondary_keywords' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Secondary keywords for variety'
                        ],
                        'meta_description_length' => [
                            'type' => 'integer',
                            'default' => 155,
                            'minimum' => 90,
                            'maximum' => 200,
                            'description' => 'Target character length for meta description'
                        ],
                        'slug_strategy' => [
                            'type' => 'string',
                            'enum' => ['keyword', 'sentence', 'custom'],
                            'default' => 'keyword',
                            'description' => 'How slugs should be constructed'
                        ]
                    ],
                    'default' => []
                ],
                'image_preferences' => [
                    'type' => 'object',
                    'description' => 'Image generation parameters',
                    'properties' => [
                        'enabled' => [
                            'type' => 'boolean',
                            'default' => true,
                            'description' => 'Whether to request DALL·E assets'
                        ],
                        'featured_image_style' => [
                            'type' => 'string',
                            'description' => 'Style hint for featured images (e.g., “cinematic photo”)'
                        ],
                        'chapter_image_style' => [
                            'type' => 'string',
                            'description' => 'Style hint for per-chapter images'
                        ],
                        'image_count_limit' => [
                            'type' => 'integer',
                            'default' => 6,
                            'minimum' => 0,
                            'maximum' => 20,
                            'description' => 'Hard limit of generated images per article'
                        ]
                    ],
                    'default' => []
                ],
                'storage_preferences' => [
                    'type' => 'object',
                    'description' => 'Asset storage rules for generated artifacts',
                    'properties' => [
                        'provider' => [
                            'type' => 'string',
                            'enum' => ['google_drive', 's3', 'local', 'none'],
                            'default' => 'google_drive',
                            'description' => 'Where generated files should be persisted'
                        ],
                        'google_drive_folder_id' => [
                            'type' => 'string',
                            'description' => 'Destination folder ID when using Google Drive'
                        ],
                        'asset_manifest_url' => [
                            'type' => 'string',
                            'format' => 'uri',
                            'description' => 'Pre-existing manifest file to append to'
                        ]
                    ],
                    'default' => []
                ],
                'credential_aliases' => [
                    'type' => 'object',
                    'description' => 'Alias references for retrieving/encrypting credentials',
                    'properties' => [
                        'wordpress' => [
                            'type' => 'string',
                            'description' => 'Alias/key for WordPress credentials in the credential vault'
                        ],
                        'openai' => [
                            'type' => 'string',
                            'description' => 'Alias/key for OpenAI credentials'
                        ],
                        'google_drive' => [
                            'type' => 'string',
                            'description' => 'Alias/key for Google Drive service account'
                        ]
                    ],
                    'default' => []
                ],
                'enable_execution_logging' => [
                    'type' => 'boolean',
                    'description' => 'Toggle execution log storage for every phase',
                    'default' => true
                ],
                'enable_queue_polling' => [
                    'type' => 'boolean',
                    'description' => 'Allow orchestrator to poll this configuration for queued work',
                    'default' => true
                ],
                'monitoring_channel' => [
                    'type' => 'string',
                    'description' => 'Optional Slack/email channel identifier for workflow alerts'
                ],
                'execution_log_retention_days' => [
                    'type' => 'integer',
                    'description' => 'Retention window for execution logs',
                    'default' => 30,
                    'minimum' => 1,
                    'maximum' => 365
                ]
            ]
        ];
    }

    // ==================== INITIALIZATION ====================

    public function initialize(array $dependencies): void
    {
        parent::initialize($dependencies);

        $this->configurationService = $dependencies['wordpress_blog_configuration_service'] ?? null;
        $this->queueService = $dependencies['wordpress_blog_queue_service'] ?? null;
        $this->generatorService = $dependencies['wordpress_blog_generator_service'] ?? null;
        $this->publisherService = $dependencies['wordpress_blog_publisher'] ?? null;
        $this->executionLogger = $dependencies['wordpress_blog_execution_logger'] ?? null;
        $this->credentialManager = $dependencies['credential_manager'] ?? null;
        $this->credentialCache = [];

        $this->logInfo('WordPress agent initialized', [
            'configuration_service' => (bool) $this->configurationService,
            'queue_service' => (bool) $this->queueService,
            'generator_service' => (bool) $this->generatorService,
            'publisher_service' => (bool) $this->publisherService,
            'execution_logger' => (bool) $this->executionLogger,
            'credential_manager' => (bool) $this->credentialManager
        ]);
    }

    // ==================== CONTEXT BUILDING ====================

    public function buildContext(array $messages, array $agentConfig): array
    {
        $context = parent::buildContext($messages, $agentConfig);

        $specializedConfig = $context['specialized_config'] ?? [];
        $configurationId = $specializedConfig['configuration_id'] ?? null;
        $queueId = $specializedConfig['article_queue_id'] ?? null;

        $configuration = $this->loadBlogConfiguration($configurationId);
        $queueEntry = $this->loadQueueEntry($queueId);
        $internalLinks = $this->loadInternalLinks($configurationId);

        // Extract user intent from recent messages (after loading workflow context)
        $userMessage = $this->extractUserMessage($messages, -1);
        $intent = $this->detectUserIntent($userMessage, [
            'queue_entry' => $queueEntry,
            'workflow_phases' => $specializedConfig['workflow_phases'] ?? [],
            'configuration_id' => $configurationId,
            'queue_id' => $queueId
        ]);

        $context['user_intent'] = $intent;
        $context['user_message'] = $userMessage;

        $context['blog_workflow'] = [
            'configuration_id' => $configurationId,
            'queue_id' => $queueId,
            'configuration' => $configuration,
            'queue_entry' => $queueEntry,
            'internal_links' => $internalLinks,
            'execution_log' => $queueEntry['execution_log_url'] ?? $queueEntry['execution_log_path'] ?? null,
            'metadata' => [
                'article_id' => $queueEntry['article_id'] ?? $queueEntry['id'] ?? null,
                'configuration_id' => $configurationId,
                'last_status' => $queueEntry['status'] ?? null,
                'retry_count' => $queueEntry['retry_count'] ?? 0
            ]
        ];

        $this->logInfo('WordPress blog context enriched', [
            'has_configuration' => (bool) $configuration,
            'has_queue_entry' => (bool) $queueEntry,
            'internal_link_count' => is_array($internalLinks) ? count($internalLinks) : 0,
            'configuration_id' => $configurationId,
            'queue_id' => $queueId
        ]);

        return $context;
    }

    // ==================== INPUT VALIDATION ====================

    public function validateInput(array $messages, array $context): array
    {
        $validatedMessages = parent::validateInput($messages, $context);

        // Validate WordPress configuration is present
        $config = $context['specialized_config'] ?? [];

        if (empty($config['wp_site_url'])) {
            throw new AgentValidationException('WordPress site URL is required');
        }

        $credentialAliases = $config['credential_aliases'] ?? [];
        $hasDirectCredentials = !empty($config['wp_username']) && !empty($config['wp_app_password']);
        $hasAliasConfigured = !empty($credentialAliases['wordpress']);

        if (!$hasDirectCredentials && !$hasAliasConfigured) {
            throw new AgentValidationException('WordPress credentials are required (provide username/password or credential alias)');
        }

        $configurationId = $config['configuration_id'] ?? ($context['blog_workflow']['configuration_id'] ?? null);
        if (empty($configurationId)) {
            throw new AgentValidationException('Blog automation requires configuration_id to load runtime settings');
        }

        $queueId = $config['article_queue_id'] ?? ($context['blog_workflow']['queue_id'] ?? null);
        if (empty($queueId)) {
            throw new AgentValidationException('Article queue ID is required to locate the queued blog payload');
        }

        if (empty($context['blog_workflow']['configuration'])) {
            throw new AgentValidationException("Blog configuration could not be resolved for configuration_id {$configurationId}");
        }

        if (empty($context['blog_workflow']['queue_entry'])) {
            throw new AgentValidationException("No queued article payload found for article_queue_id {$queueId}");
        }

        $workflowPhases = $config['workflow_phases'] ?? [];
        $imagePreferences = $config['image_preferences'] ?? [];
        $assetsEnabled = ($workflowPhases['generate_assets'] ?? true) && ($imagePreferences['enabled'] ?? true);
        $hasOpenAiCredentials = !empty($credentialAliases['openai']) || !empty($config['openai_api_key'] ?? null);

        if ($assetsEnabled && !$hasOpenAiCredentials) {
            throw new AgentValidationException('Asset generation is enabled but no OpenAI credential alias or API key was provided');
        }

        $storagePreferences = $config['storage_preferences'] ?? [];
        $usingGoogleDrive = ($storagePreferences['provider'] ?? 'google_drive') === 'google_drive';

        if ($assetsEnabled && $usingGoogleDrive) {
            if (empty($storagePreferences['google_drive_folder_id'])) {
                throw new AgentValidationException('Google Drive storage requires google_drive_folder_id');
            }

            if (empty($credentialAliases['google_drive'])) {
                throw new AgentValidationException('Google Drive storage requires google_drive credential alias');
            }
        }

        if (($config['enable_execution_logging'] ?? true) && !$this->executionLogger && empty($context['blog_workflow']['execution_log'])) {
            throw new AgentValidationException('Execution logging is enabled but no logger dependency or existing log pointer was provided');
        }

        return $validatedMessages;
    }

    // ==================== CORE PROCESSING ====================

    public function process(array $input, array $context): array
    {
        $this->logInfo('WordPress agent processing started');

        // Initialize WordPress API client
        $config = $context['specialized_config'];
        $wordpressCredentials = $this->getWordPressCredentials($config);
        $this->wpClient = new WordPressApiClient(
            $config['wp_site_url'],
            $wordpressCredentials['username'],
            $wordpressCredentials['password'],
            $config['api_timeout_ms'] ?? 30000
        );

        $intent = $context['user_intent'];
        $userMessage = $context['user_message'];

        $result = null;

        // Process based on detected intent
        try {
            switch ($intent['action']) {
                case self::ACTION_CREATE_POST:
                    $result = $this->handleCreatePost($intent, $userMessage, $context);
                    break;

                case self::ACTION_UPDATE_POST:
                    $result = $this->handleUpdatePost($intent, $userMessage, $context);
                    break;

                case self::ACTION_SEARCH_POSTS:
                    $result = $this->handleSearchPosts($intent, $userMessage, $context);
                    break;

                case self::ACTION_GET_POST:
                    $result = $this->handleGetPost($intent, $userMessage, $context);
                    break;

                case self::ACTION_LIST_CATEGORIES:
                    $result = $this->handleListCategories($context);
                    break;

                case self::ACTION_QUEUE_ARTICLE:
                case self::ACTION_GENERATE_STRUCTURE:
                case self::ACTION_WRITE_CHAPTERS:
                case self::ACTION_GENERATE_ASSETS:
                case self::ACTION_ASSEMBLE_ARTICLE:
                case self::ACTION_PUBLISH_ARTICLE:
                case self::ACTION_MONITOR_WORKFLOW:
                case self::ACTION_FETCH_EXECUTION_LOG:
                case self::ACTION_MANAGE_INTERNAL_LINKS:
                    $result = $this->handleWorkflowDirective($intent['action'], $intent, $context);
                    break;

                default:
                    // Unknown action - let LLM handle it
                    $this->logInfo('No specific action detected, using LLM');
                    $result = [
                        'action' => 'llm_assisted',
                        'message' => $userMessage
                    ];
            }
        } catch (AgentProcessingException $processingException) {
            throw $processingException;
        } catch (Exception $e) {
            $this->logError('WordPress action failed', [
                'action' => $intent['action'],
                'error' => $e->getMessage()
            ]);

            throw new AgentProcessingException(
                'Failed to execute WordPress action: ' . $e->getMessage(),
                'wordpress',
                500,
                $e
            );
        }

        return [
            'messages' => $input,
            'intent' => $intent,
            'result' => $result,
            'wordpress_data' => [
                'site_url' => $config['wp_site_url'],
                'action_performed' => $intent['action']
            ]
        ];
    }

    // ==================== LLM INTEGRATION ====================

    public function requiresLLM(array $processedData, array $context): bool
    {
        $intent = $processedData['intent'] ?? [];
        $action = $intent['action'] ?? null;

        $nonLlmActions = [
            self::ACTION_SEARCH_POSTS,
            self::ACTION_GET_POST,
            self::ACTION_LIST_CATEGORIES,
            self::ACTION_QUEUE_ARTICLE,
            self::ACTION_GENERATE_ASSETS,
            self::ACTION_ASSEMBLE_ARTICLE,
            self::ACTION_PUBLISH_ARTICLE,
            self::ACTION_MONITOR_WORKFLOW,
            self::ACTION_FETCH_EXECUTION_LOG,
            self::ACTION_MANAGE_INTERNAL_LINKS
        ];

        if (in_array($action, $nonLlmActions, true)) {
            $this->logInfo('Skipping LLM - non-creative workflow action', [
                'action' => $action,
                'reason' => 'admin_or_operational'
            ]);

            return false; // Return data directly
        }

        $creativeActions = [
            self::ACTION_CREATE_POST,
            self::ACTION_UPDATE_POST,
            self::ACTION_GENERATE_STRUCTURE,
            self::ACTION_WRITE_CHAPTERS
        ];

        $requiresLlm = in_array($action, $creativeActions, true) || $action === 'unknown';

        $this->logInfo('LLM decision evaluated', [
            'action' => $action,
            'requires_llm' => $requiresLlm,
            'reason' => $requiresLlm ? 'creative_generation' : 'default_skip'
        ]);

        return $requiresLlm;
    }

    public function prepareLLMMessages(array $processedData, array $context): array
    {
        $messages = $processedData['messages'] ?? [];
        $intent = $processedData['intent'] ?? [];
        $result = $processedData['result'] ?? [];
        $workflow = $context['blog_workflow'] ?? [];
        $configuration = $workflow['configuration'] ?? [];
        $queueEntry = $workflow['queue_entry'] ?? [];

        $systemMessage = $context['agent_config']['system_message'] ?? 'You are a WordPress Blog Automation Pro agent.';

        $metadata = [
            'site' => $configuration['website_url'] ?? $context['specialized_config']['wp_site_url'] ?? null,
            'post_type' => $context['specialized_config']['post_type'] ?? 'post',
            'default_status' => $context['specialized_config']['default_status'] ?? 'draft',
            'tone' => $configuration['writing_style'] ?? $queueEntry['writing_style'] ?? null,
            'audience' => $configuration['target_audience'] ?? $queueEntry['target_audience'] ?? null,
            'max_word_count' => $configuration['max_word_count'] ?? $queueEntry['max_word_count'] ?? null,
            'chapters' => $configuration['number_of_chapters'] ?? $queueEntry['number_of_chapters'] ?? null,
            'primary_keywords' => $queueEntry['primary_keywords'] ?? $configuration['primary_keywords'] ?? null,
            'secondary_keywords' => $queueEntry['secondary_keywords'] ?? $configuration['secondary_keywords'] ?? null,
            'cta_message' => $configuration['cta_message'] ?? $queueEntry['cta_message'] ?? null,
            'cta_url' => $configuration['cta_url'] ?? $queueEntry['cta_url'] ?? null,
        ];

        $brief = array_filter([
            'seed_keyword' => $queueEntry['seed_keyword'] ?? null,
            'topic' => $queueEntry['topic'] ?? ($result['topic'] ?? null),
            'goal' => $queueEntry['goal'] ?? null,
            'target_language' => $queueEntry['language'] ?? $configuration['language'] ?? null,
            'style' => $queueEntry['style'] ?? null,
            'tone' => $queueEntry['tone'] ?? null,
        ]);

        $chapterSpecs = $processedData['structure']['chapters'] ?? $queueEntry['chapter_specs'] ?? $queueEntry['chapters'] ?? [];
        $existingChapters = $processedData['chapters'] ?? $queueEntry['chapters'] ?? [];

        $progress = array_filter([
            'article_id' => $workflow['metadata']['article_id'] ?? null,
            'queue_status' => $workflow['metadata']['last_status'] ?? ($queueEntry['status'] ?? null),
            'retry_count' => $workflow['metadata']['retry_count'] ?? 0,
            'execution_log' => $workflow['execution_log'] ?? null,
        ]);

        $tools = array_map(function (array $tool) {
            return $tool['function']['name'] ?? $tool['type'] ?? 'unknown';
        }, $this->getCustomTools());

        $structuredContext = [
            'workflow_intent' => $intent,
            'blog_configuration' => $metadata,
            'article_brief' => $brief,
            'chapter_plan' => $chapterSpecs,
            'existing_chapters' => $existingChapters,
            'progress' => $progress,
            'tools' => $tools,
            'streaming_guidance' => 'Stream concise chunks, mark partial completions, avoid regenerating finished sections.',
        ];

        $systemMessage .= "\nAlways enforce SEO keywords, tone, word counts, and CTA placement from the context.";
        $systemMessage .= "\nUse available tools only when needed and prefer idempotent operations.";
        $systemMessage .= "\nAvoid duplicating already completed chapters or outlines.";
        $systemMessage .= "\nIf partial content exists, continue from remaining gaps and note progress for streaming consumers.";

        $contextBlock = "Workflow context:\n" . json_encode($structuredContext, JSON_PRETTY_PRINT);

        $messages = array_merge([
            [
                'role' => 'system',
                'content' => $systemMessage
            ],
            [
                'role' => 'system',
                'content' => $contextBlock
            ]
        ], $messages);

        $this->logInfo('Prepared LLM prompt for creative workflow', [
            'action' => $intent['action'] ?? null,
            'has_brief' => !empty($brief),
            'chapter_spec_count' => is_array($chapterSpecs) ? count($chapterSpecs) : 0,
            'existing_chapter_count' => is_array($existingChapters) ? count($existingChapters) : 0
        ]);

        return $messages;
    }

    public function formatOutput(array $processedData, array $context): array
    {
        $intent = $processedData['intent'] ?? [];
        $result = $processedData['result'] ?? [];

        // For non-LLM actions, format data directly
        if (!$this->requiresLLM($processedData, $context)) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => $this->formatDataResponse($intent, $result)
                ],
                'metadata' => [
                    'agent_type' => 'wordpress',
                    'action' => $intent['action'],
                    'processed_at' => date('c')
                ]
            ];
        }

        // For LLM-assisted actions, use parent formatting
        $output = parent::formatOutput($processedData, $context);

        // Enhance metadata
        $output['metadata']['wordpress_action'] = $intent['action'] ?? 'unknown';
        $output['metadata']['wordpress_result'] = $result;

        return $output;
    }

    // ==================== CUSTOM TOOLS ====================

    public function getCustomTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_wordpress_post',
                    'description' => 'Create a new WordPress blog post',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Post title'
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'Post content (HTML allowed)'
                            ],
                            'excerpt' => [
                                'type' => 'string',
                                'description' => 'Post excerpt (optional)'
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['draft', 'publish', 'pending', 'private'],
                                'description' => 'Post status'
                            ],
                            'categories' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Category slugs'
                            ],
                            'tags' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Tag names'
                            ]
                        ],
                        'required' => ['title', 'content']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_wordpress_post',
                    'description' => 'Update an existing WordPress post',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'post_id' => [
                                'type' => 'integer',
                                'description' => 'Post ID to update'
                            ],
                            'title' => [
                                'type' => 'string',
                                'description' => 'New post title (optional)'
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'New post content (optional)'
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['draft', 'publish', 'pending', 'private'],
                                'description' => 'New post status (optional)'
                            ]
                        ],
                        'required' => ['post_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_wordpress_posts',
                    'description' => 'Search for WordPress posts',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => [
                                'type' => 'string',
                                'description' => 'Search query'
                            ],
                            'per_page' => [
                                'type' => 'integer',
                                'description' => 'Number of results (max 100)',
                                'default' => 10
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => ['publish', 'draft', 'pending', 'private', 'any'],
                                'description' => 'Post status filter'
                            ]
                        ],
                        'required' => ['search']
                    ]
                ]
            ]
        ];
    }

    public function executeCustomTool(string $toolName, array $arguments, array $context): array
    {
        $this->logInfo('Executing WordPress tool', ['tool' => $toolName]);

        switch ($toolName) {
            case 'create_wordpress_post':
                return $this->toolCreatePost($arguments, $context);

            case 'update_wordpress_post':
                return $this->toolUpdatePost($arguments, $context);

            case 'search_wordpress_posts':
                return $this->toolSearchPosts($arguments, $context);

            default:
                return parent::executeCustomTool($toolName, $arguments, $context);
        }
    }

    // ==================== ACTION HANDLERS ====================

    /**
     * Handle create post action
     */
    private function handleCreatePost(array $intent, string $userMessage, array $context): array
    {
        $this->logInfo('Creating WordPress post');

        $config = $context['specialized_config'];

        // Extract post details from user message (basic extraction)
        // In production, LLM would be used to extract structured data
        $postData = [
            'title' => $intent['title'] ?? 'New Post',
            'content' => $userMessage,
            'status' => $config['auto_publish'] ? 'publish' : ($config['default_status'] ?? 'draft'),
            'author' => $config['default_author_id'] ?? 1
        ];

        return [
            'action' => 'create_post',
            'status' => 'prepared',
            'post_data' => $postData,
            'message' => 'Post data prepared for creation'
        ];
    }

    /**
     * Handle update post action
     */
    private function handleUpdatePost(array $intent, string $userMessage, array $context): array
    {
        $this->logInfo('Updating WordPress post');

        $postId = $intent['post_id'] ?? null;

        if (!$postId) {
            throw new AgentProcessingException('Post ID is required for updates');
        }

        return [
            'action' => 'update_post',
            'post_id' => $postId,
            'status' => 'prepared',
            'message' => "Prepared to update post {$postId}"
        ];
    }

    /**
     * Handle search posts action
     */
    private function handleSearchPosts(array $intent, string $userMessage, array $context): array
    {
        $this->logInfo('Searching WordPress posts');

        $query = $intent['search_query'] ?? $userMessage;
        $config = $context['specialized_config'];

        $posts = $this->wpClient->searchPosts([
            'search' => $query,
            'per_page' => $config['max_posts_per_request'] ?? 10
        ]);

        return [
            'action' => 'search_posts',
            'query' => $query,
            'results' => $posts,
            'count' => count($posts)
        ];
    }

    /**
     * Handle get post action
     */
    private function handleGetPost(array $intent, string $userMessage, array $context): array
    {
        $this->logInfo('Getting WordPress post');

        $postId = $intent['post_id'] ?? null;

        if (!$postId) {
            throw new AgentProcessingException('Post ID is required');
        }

        $post = $this->wpClient->getPost($postId);

        return [
            'action' => 'get_post',
            'post_id' => $postId,
            'post' => $post
        ];
    }

    /**
     * Handle list categories action
     */
    private function handleListCategories(array $context): array
    {
        $this->logInfo('Listing WordPress categories');

        $categories = $this->wpClient->getCategories();

        return [
            'action' => 'list_categories',
            'categories' => $categories,
            'count' => count($categories)
        ];
    }

    /**
     * Handle workflow orchestration directives
     */
    private function handleWorkflowDirective(string $action, array $intent, array $context): array
    {
        switch ($action) {
            case self::ACTION_QUEUE_ARTICLE:
                return $this->handleQueueArticle($intent, $context);
            case self::ACTION_GENERATE_STRUCTURE:
                return $this->handleGenerateStructure($intent, $context);
            case self::ACTION_WRITE_CHAPTERS:
                return $this->handleWriteChapters($intent, $context);
            case self::ACTION_GENERATE_ASSETS:
                return $this->handleGenerateAssets($intent, $context);
            case self::ACTION_ASSEMBLE_ARTICLE:
                return $this->handleAssembleArticle($intent, $context);
            case self::ACTION_PUBLISH_ARTICLE:
                return $this->handlePublishArticle($intent, $context);
            case self::ACTION_MONITOR_WORKFLOW:
                return $this->handleMonitorStatus($intent, $context);
            case self::ACTION_FETCH_EXECUTION_LOG:
                return $this->handleFetchExecutionLog($intent, $context);
            case self::ACTION_MANAGE_INTERNAL_LINKS:
                return $this->handleManageInternalLinks($intent, $context);
            default:
                throw new AgentProcessingException('Unsupported workflow directive: ' . $action, 'wordpress');
        }
    }

    private function handleQueueArticle(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;

        $this->updateQueueStatus($queueId, $articleId, 'queueing', ['intent' => $intent]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'queue', 'in_progress', [
            'message' => 'Queueing article for automation',
            'intent' => $intent
        ]) ?? $logPointer;

        try {
            $queuePayload = [
                'configuration' => $workflow['configuration'] ?? null,
                'queue_entry' => $workflow['queue_entry'] ?? null,
                'intent' => $intent,
                'context' => $context
            ];

            $result = $this->callServiceMethodSafe(
                $this->queueService,
                ['queueArticle', 'enqueue', 'enqueueArticle', 'addToQueue'],
                [
                    [$queuePayload],
                    [$queueId, $queuePayload],
                    [$queueId, $articleId, $queuePayload]
                ]
            );
        } catch (\Throwable $exception) {
            throw $this->wrapWorkflowException(self::ACTION_QUEUE_ARTICLE, 'queue', $exception, $context);
        }

        $finalStatus = 'queued';
        $this->updateQueueStatus($queueId, $articleId, $finalStatus, ['service_result' => $result]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'queue', 'completed', [
            'result' => $result
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_QUEUE_ARTICLE,
            'phase' => 'queue',
            'status' => $finalStatus,
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'result' => $result,
            'execution_log' => $logPointer,
            'debug_matches' => $intent['matches'] ?? []
        ];
    }

    private function handleGenerateStructure(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;

        $this->updateQueueStatus($queueId, $articleId, 'processing_outline', ['intent' => $intent]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'structure', 'in_progress', [
            'message' => 'Generating outline and metadata'
        ]) ?? $logPointer;

        try {
            $structure = $this->callServiceMethodSafe(
                $this->generatorService,
                ['generateStructure', 'buildStructure', 'createOutline', 'generateOutline'],
                [
                    [$workflow],
                    [$articleId, $workflow],
                    [$articleId, $context]
                ]
            );
        } catch (\Throwable $exception) {
            throw $this->wrapWorkflowException(self::ACTION_GENERATE_STRUCTURE, 'structure', $exception, $context);
        }

        $this->updateQueueStatus($queueId, $articleId, 'structure_ready', ['structure' => $structure]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'structure', 'completed', [
            'outline_generated' => (bool) $structure
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_GENERATE_STRUCTURE,
            'phase' => 'structure',
            'status' => 'structure_ready',
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'structure' => $structure,
            'execution_log' => $logPointer
        ];
    }

    private function handleWriteChapters(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;

        $this->updateQueueStatus($queueId, $articleId, 'writing', ['intent' => $intent]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'writing', 'in_progress', [
            'message' => 'Drafting chapters'
        ]) ?? $logPointer;

        try {
            $chapters = $this->callServiceMethodSafe(
                $this->generatorService,
                ['writeChapters', 'generateChapters', 'draftChapters', 'createChapters'],
                [
                    [$workflow],
                    [$articleId, $workflow],
                    [$articleId, $context]
                ]
            );
        } catch (\Throwable $exception) {
            throw $this->wrapWorkflowException(self::ACTION_WRITE_CHAPTERS, 'writing', $exception, $context);
        }

        $this->updateQueueStatus($queueId, $articleId, 'chapters_ready', ['chapters' => $chapters]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'writing', 'completed', [
            'chapters_generated' => is_array($chapters)
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_WRITE_CHAPTERS,
            'phase' => 'writing',
            'status' => 'chapters_ready',
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'chapters' => $chapters,
            'execution_log' => $logPointer
        ];
    }

    private function handleGenerateAssets(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;

        $this->updateQueueStatus($queueId, $articleId, 'generating_assets', ['intent' => $intent]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'assets', 'in_progress', [
            'message' => 'Generating images and media assets'
        ]) ?? $logPointer;

        try {
            $assets = $this->callServiceMethodSafe(
                $this->generatorService,
                ['generateAssets', 'createAssets', 'generateImages', 'produceAssets'],
                [
                    [$workflow],
                    [$articleId, $workflow],
                    [$articleId, $context]
                ]
            );
        } catch (\Throwable $exception) {
            throw $this->wrapWorkflowException(self::ACTION_GENERATE_ASSETS, 'assets', $exception, $context);
        }

        $this->updateQueueStatus($queueId, $articleId, 'assets_ready', ['assets' => $assets]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'assets', 'completed', [
            'assets_generated' => is_array($assets)
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_GENERATE_ASSETS,
            'phase' => 'assets',
            'status' => 'assets_ready',
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'assets' => $assets,
            'execution_log' => $logPointer
        ];
    }

    private function handleAssembleArticle(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;

        $this->updateQueueStatus($queueId, $articleId, 'assembling', ['intent' => $intent]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'assembly', 'in_progress', [
            'message' => 'Assembling chapters and assets into article'
        ]) ?? $logPointer;

        try {
            $assembled = $this->callServiceMethodSafe(
                $this->generatorService,
                ['assembleArticle', 'assemble', 'buildArticle', 'finalizeArticle'],
                [
                    [$workflow],
                    [$articleId, $workflow],
                    [$articleId, $context]
                ]
            );
        } catch (\Throwable $exception) {
            throw $this->wrapWorkflowException(self::ACTION_ASSEMBLE_ARTICLE, 'assembly', $exception, $context);
        }

        $this->updateQueueStatus($queueId, $articleId, 'assembly_ready', ['assembled' => $assembled]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'assembly', 'completed', [
            'assembled' => (bool) $assembled
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_ASSEMBLE_ARTICLE,
            'phase' => 'assembly',
            'status' => 'assembly_ready',
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'assembled_article' => $assembled,
            'execution_log' => $logPointer
        ];
    }

    private function handlePublishArticle(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;

        $this->updateQueueStatus($queueId, $articleId, 'publishing', ['intent' => $intent]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'publish', 'in_progress', [
            'message' => 'Publishing article to WordPress'
        ]) ?? $logPointer;

        try {
            $publication = $this->callServiceMethodSafe(
                $this->publisherService,
                ['publishArticle', 'publish', 'pushArticle', 'postToWordPress', 'post'],
                [
                    [$workflow],
                    [$articleId, $workflow],
                    [$articleId, $context]
                ]
            );
        } catch (\Throwable $exception) {
            throw $this->wrapWorkflowException(self::ACTION_PUBLISH_ARTICLE, 'publish', $exception, $context);
        }

        $finalStatus = 'published';
        $this->updateQueueStatus($queueId, $articleId, $finalStatus, ['publication' => $publication]);
        $logPointer = $this->logExecutionStep($queueId, $articleId, 'publish', 'completed', [
            'publication' => $publication
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_PUBLISH_ARTICLE,
            'phase' => 'publish',
            'status' => $finalStatus,
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'publication' => $publication,
            'execution_log' => $logPointer
        ];
    }

    private function handleMonitorStatus(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $currentStatus = $workflow['metadata']['last_status'] ?? ($workflow['queue_entry']['status'] ?? null);

        try {
            $serviceStatus = $this->callServiceMethodSafe(
                $this->queueService,
                ['getStatus', 'getQueueStatus', 'getArticleStatus', 'getStatusForArticle'],
                [
                    [$queueId],
                    [$queueId, $articleId],
                    [$articleId]
                ]
            );
            if ($serviceStatus) {
                $currentStatus = $serviceStatus;
            }
        } catch (\Throwable $exception) {
            $this->logWarning('Failed to fetch workflow status', [
                'queue_id' => $queueId,
                'article_id' => $articleId,
                'error' => $exception->getMessage()
            ]);
        }

        $logPointer = $this->logExecutionStep($queueId, $articleId, 'monitor', 'observed', [
            'status' => $currentStatus
        ]) ?? ($workflow['execution_log'] ?? null);

        return [
            'action' => self::ACTION_MONITOR_WORKFLOW,
            'phase' => 'monitor',
            'status' => $currentStatus,
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'execution_log' => $logPointer
        ];
    }

    private function handleFetchExecutionLog(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;
        $logContents = null;

        try {
            $logContents = $this->callServiceMethodSafe(
                $this->executionLogger,
                ['getLog', 'fetchLog', 'retrieve', 'retrieveLog', 'read'],
                [
                    [$queueId, $articleId],
                    [$logPointer]
                ]
            );
        } catch (\Throwable $exception) {
            $this->logWarning('Failed to fetch execution log', [
                'queue_id' => $queueId,
                'article_id' => $articleId,
                'error' => $exception->getMessage()
            ]);
        }

        $logPointer = $this->logExecutionStep($queueId, $articleId, 'log', 'requested', [
            'execution_log' => $logPointer,
            'retrieved' => (bool) $logContents
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_FETCH_EXECUTION_LOG,
            'phase' => 'log',
            'status' => $workflow['metadata']['last_status'] ?? ($workflow['queue_entry']['status'] ?? null),
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'execution_log' => $logPointer,
            'log_contents' => $logContents
        ];
    }

    private function handleManageInternalLinks(array $intent, array $context): array
    {
        $workflow = $context['blog_workflow'] ?? [];
        $configurationId = $workflow['configuration_id'] ?? null;
        $logPointer = $workflow['execution_log'] ?? null;

        $managedLinks = $workflow['internal_links'] ?? [];

        try {
            $managedLinks = $this->callServiceMethodSafe(
                $this->configurationService,
                ['syncInternalLinks', 'manageInternalLinks', 'updateInternalLinks', 'getInternalLinks'],
                [
                    [$configurationId, $intent, $workflow],
                    [$configurationId]
                ]
            ) ?? $managedLinks;
        } catch (\Throwable $exception) {
            throw $this->wrapWorkflowException(self::ACTION_MANAGE_INTERNAL_LINKS, 'internal_links', $exception, $context);
        }

        $logPointer = $this->logExecutionStep($workflow['queue_id'] ?? null, $workflow['metadata']['article_id'] ?? null, 'internal_links', 'completed', [
            'link_count' => is_array($managedLinks) ? count($managedLinks) : 0
        ]) ?? $logPointer;

        return [
            'action' => self::ACTION_MANAGE_INTERNAL_LINKS,
            'phase' => 'internal_links',
            'status' => 'links_updated',
            'configuration_id' => $configurationId,
            'queue_id' => $workflow['queue_id'] ?? null,
            'article_id' => $workflow['metadata']['article_id'] ?? null,
            'internal_links' => $managedLinks,
            'execution_log' => $logPointer
        ];
    }

    private function callServiceMethodSafe($service, array $methods, array $argumentSets = [[]])
    {
        $lastException = null;

        foreach ($argumentSets as $arguments) {
            try {
                $result = $this->callServiceMethod($service, $methods, $arguments);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $exception) {
                $lastException = $exception;
                $this->logWarning('Service method invocation failed', [
                    'methods' => $methods,
                    'arguments' => $arguments,
                    'error' => $exception->getMessage()
                ]);
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        return null;
    }

    private function updateQueueStatus(?string $queueId, ?string $articleId, string $status, array $details = []): array
    {
        $statusData = [
            'queue_id' => $queueId,
            'article_id' => $articleId,
            'status' => $status,
            'details' => $details
        ];

        if (!$this->queueService) {
            return $statusData;
        }

        try {
            $response = $this->callServiceMethodSafe(
                $this->queueService,
                ['updateStatus', 'updateQueueStatus', 'markStatus', 'markArticleStatus', 'setStatus', 'updateArticleStatus'],
                [
                    [$queueId, $status, $details],
                    [$articleId, $status],
                    [$status, $details]
                ]
            );

            if ($response !== null) {
                $statusData['service_response'] = $response;
            }
        } catch (\Throwable $exception) {
            $this->logWarning('Queue status update failed', [
                'queue_id' => $queueId,
                'article_id' => $articleId,
                'status' => $status,
                'error' => $exception->getMessage()
            ]);
        }

        return $statusData;
    }

    private function logExecutionStep(?string $queueId, ?string $articleId, string $phase, string $status, array $details = []): ?string
    {
        $details['phase'] = $phase;
        $details['status'] = $status;

        if (!$this->executionLogger) {
            return $details['execution_log'] ?? null;
        }

        try {
            $result = $this->callServiceMethodSafe(
                $this->executionLogger,
                ['logPhase', 'append', 'appendLog', 'record', 'recordPhase', 'write'],
                [
                    [$queueId, $articleId, $phase, $status, $details],
                    [$phase, $status, $details]
                ]
            );

            if (is_string($result)) {
                return $result;
            }

            if (is_array($result)) {
                return $result['log_url'] ?? $result['log'] ?? $result['path'] ?? null;
            }
        } catch (\Throwable $exception) {
            $this->logWarning('Execution logging failed', [
                'queue_id' => $queueId,
                'article_id' => $articleId,
                'phase' => $phase,
                'status' => $status,
                'error' => $exception->getMessage()
            ]);
        }

        return $details['execution_log'] ?? null;
    }

    private function wrapWorkflowException(string $action, string $phase, \Throwable $exception, array $context): AgentProcessingException
    {
        $workflow = $context['blog_workflow'] ?? [];
        $queueId = $workflow['queue_id'] ?? null;
        $articleId = $workflow['metadata']['article_id'] ?? null;

        $this->updateQueueStatus($queueId, $articleId, 'failed', [
            'phase' => $phase,
            'error' => $exception->getMessage(),
            'action' => $action
        ]);

        $this->logExecutionStep($queueId, $articleId, $phase, 'failed', [
            'action' => $action,
            'exception' => $exception->getMessage()
        ]);

        $domainError = [
            self::ACTION_QUEUE_ARTICLE => 'ConfigurationException',
            self::ACTION_GENERATE_STRUCTURE => 'ContentGenerationException',
            self::ACTION_WRITE_CHAPTERS => 'ContentGenerationException',
            self::ACTION_GENERATE_ASSETS => 'ImageGenerationException',
            self::ACTION_ASSEMBLE_ARTICLE => 'ContentAssemblyException',
            self::ACTION_PUBLISH_ARTICLE => 'WordPressPublishException',
            self::ACTION_MANAGE_INTERNAL_LINKS => 'ConfigurationException'
        ][$action] ?? 'WorkflowException';

        $message = sprintf(
            '[%s] %s phase failed: %s',
            $domainError,
            $phase,
            $exception->getMessage()
        );

        return new AgentProcessingException($message, 'wordpress', 500, $exception);
    }

    // ==================== TOOL IMPLEMENTATIONS ====================

    /**
     * Tool: Create WordPress post
     */
    private function toolCreatePost(array $arguments, array $context): array
    {
        $config = $context['specialized_config'];

        $postData = [
            'title' => $arguments['title'],
            'content' => $arguments['content'],
            'excerpt' => $arguments['excerpt'] ?? '',
            'status' => $arguments['status'] ?? ($config['default_status'] ?? 'draft'),
            'author' => $config['default_author_id'] ?? 1
        ];

        // Handle categories
        if (isset($arguments['categories']) && !empty($arguments['categories'])) {
            $postData['categories'] = $this->resolveCategoryIds($arguments['categories']);
        }

        // Handle tags
        if (isset($arguments['tags']) && !empty($arguments['tags'])) {
            $postData['tags'] = $this->resolveTagIds($arguments['tags']);
        }

        $result = $this->wpClient->createPost($postData);

        $this->logInfo('Post created', ['post_id' => $result['id']]);

        return [
            'success' => true,
            'post_id' => $result['id'],
            'post_url' => $result['link'] ?? null,
            'status' => $result['status'],
            'message' => "Post '{$arguments['title']}' created successfully"
        ];
    }

    /**
     * Tool: Update WordPress post
     */
    private function toolUpdatePost(array $arguments, array $context): array
    {
        $postId = $arguments['post_id'];

        $updateData = [];

        if (isset($arguments['title'])) {
            $updateData['title'] = $arguments['title'];
        }

        if (isset($arguments['content'])) {
            $updateData['content'] = $arguments['content'];
        }

        if (isset($arguments['status'])) {
            $updateData['status'] = $arguments['status'];
        }

        $result = $this->wpClient->updatePost($postId, $updateData);

        $this->logInfo('Post updated', ['post_id' => $postId]);

        return [
            'success' => true,
            'post_id' => $postId,
            'post_url' => $result['link'] ?? null,
            'message' => "Post {$postId} updated successfully"
        ];
    }

    /**
     * Tool: Search WordPress posts
     */
    private function toolSearchPosts(array $arguments, array $context): array
    {
        $config = $context['specialized_config'];

        $params = [
            'search' => $arguments['search'],
            'per_page' => min(
                $arguments['per_page'] ?? 10,
                $config['max_posts_per_request'] ?? 10
            )
        ];

        if (isset($arguments['status'])) {
            $params['status'] = $arguments['status'];
        }

        $posts = $this->wpClient->searchPosts($params);

        $this->logInfo('Posts searched', ['count' => count($posts)]);

        return [
            'success' => true,
            'count' => count($posts),
            'posts' => array_map(function ($post) {
                return [
                    'id' => $post['id'],
                    'title' => $post['title']['rendered'] ?? '',
                    'excerpt' => strip_tags($post['excerpt']['rendered'] ?? ''),
                    'status' => $post['status'],
                    'date' => $post['date'],
                    'link' => $post['link']
                ];
            }, $posts)
        ];
    }

    // ==================== HELPER METHODS ====================

    private function callServiceMethod($service, array $methods, array $arguments = [])
    {
        if (!is_object($service)) {
            return null;
        }

        foreach ($methods as $method) {
            if (method_exists($service, $method)) {
                return call_user_func_array([$service, $method], $arguments);
            }
        }

        return null;
    }

    private function loadBlogConfiguration(?string $configurationId): ?array
    {
        if (!$configurationId) {
            return null;
        }

        try {
            $configuration = $this->callServiceMethod($this->configurationService, [
                'getConfigurationById',
                'findConfigurationById',
                'getById'
            ], [$configurationId]);

            if ($configuration) {
                return $configuration;
            }

            $this->logWarning('No WordPress blog configuration found', [
                'configuration_id' => $configurationId
            ]);
        } catch (\Exception $e) {
            $this->logWarning('Failed to load WordPress blog configuration', [
                'configuration_id' => $configurationId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function loadQueueEntry(?string $queueId): ?array
    {
        if (!$queueId) {
            return null;
        }

        try {
            $queueEntry = $this->callServiceMethod($this->queueService, [
                'getQueueEntryById',
                'getArticleById',
                'findQueueEntry',
                'findById'
            ], [$queueId]);

            if ($queueEntry) {
                return $queueEntry;
            }

            $this->logWarning('No queued article found for automation', [
                'queue_id' => $queueId
            ]);
        } catch (\Exception $e) {
            $this->logWarning('Failed to load queued article payload', [
                'queue_id' => $queueId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function loadInternalLinks(?string $configurationId): array
    {
        if (!$configurationId) {
            return [];
        }

        try {
            $links = $this->callServiceMethod($this->configurationService, [
                'getInternalLinks',
                'listInternalLinks',
                'getInternalLinksByConfigurationId'
            ], [$configurationId]);

            if (is_array($links)) {
                return $links;
            }

            $this->logWarning('Internal link repository missing or empty', [
                'configuration_id' => $configurationId
            ]);
        } catch (\Exception $e) {
            $this->logWarning('Failed to load internal links', [
                'configuration_id' => $configurationId,
                'error' => $e->getMessage()
            ]);
        }

        return [];
    }

    /**
     * Detect user intent from message
     */
    private function detectUserIntent(string $message, array $workflowContext = []): array
    {
        $patterns = [
            self::ACTION_QUEUE_ARTICLE => [
                '/\b(queue|enqueue|schedule|add)\b.*\b(article|post|job)\b/i',
                '/\bstart\b.*\b(workflow|automation|run)\b/i'
            ],
            self::ACTION_GENERATE_STRUCTURE => [
                '/\b(outline|structure|blueprint|brief|skeleton)\b.*\b(article|post)?/i',
                '/\bchapter\b.*\bplan\b/i'
            ],
            self::ACTION_WRITE_CHAPTERS => [
                '/\b(write|draft|fill|expand)\b.*\b(chapter|section)s?/i',
                '/\bchapter content\b/i'
            ],
            self::ACTION_GENERATE_ASSETS => [
                '/\b(generate|create|produce)\b.*\b(image|asset|graphic|visual|media)s?/i',
                '/\bfeatured image\b|\bchapter image\b/i'
            ],
            self::ACTION_ASSEMBLE_ARTICLE => [
                '/\b(assemble|merge|compile|stitch|combine)\b.*\b(article|chapters|assets)\b/i'
            ],
            self::ACTION_PUBLISH_ARTICLE => [
                '/\b(publish|go live|post live|push)\b.*\b(article|post)\b/i'
            ],
            self::ACTION_MONITOR_WORKFLOW => [
                '/\b(status|progress|monitor|check)\b.*\b(queue|article|job|run)\b/i',
                '/\bhow\b.*\bgoing\b/i'
            ],
            self::ACTION_FETCH_EXECUTION_LOG => [
                '/\b(execution|run|processing)\b.*\blog\b/i',
                '/\b(show|share|get)\b.*\b(logs|trace|audit)\b/i'
            ],
            self::ACTION_MANAGE_INTERNAL_LINKS => [
                '/\b(internal\s+link|cross[- ]link|linking strategy|link graph)\b/i'
            ],
            self::ACTION_CREATE_POST => [
                '/\b(create|write|make|publish|post|new)\b.*\b(post|article|blog)/i'
            ],
            self::ACTION_UPDATE_POST => [
                '/\b(update|edit|modify|change)\b.*\b(post|article)/i'
            ],
            self::ACTION_SEARCH_POSTS => [
                '/\b(search|find|look for|query)\b.*\b(post|article)/i'
            ],
            self::ACTION_GET_POST => [
                '/\b(get|show|display|view)\b.*\b(post|article)/i'
            ],
            self::ACTION_LIST_CATEGORIES => [
                '/\b(list|show|get)\b.*\b(categories|category)/i'
            ]
        ];

        foreach ($patterns as $action => $actionPatterns) {
            foreach ($actionPatterns as $pattern) {
                if (preg_match($pattern, $message, $matches)) {
                    return [
                        'action' => $action,
                        'confidence' => 'high',
                        'matches' => [
                            'pattern' => $pattern,
                            'excerpt' => $matches[0] ?? null
                        ]
                    ];
                }
            }
        }

        // Fallback heuristics using queue metadata or known workflow status
        $queueEntry = $workflowContext['queue_entry'] ?? [];
        $queueStatus = $queueEntry['status'] ?? null;
        $heuristicMatches = [];

        if ($queueStatus) {
            $statusMap = [
                'queued' => self::ACTION_QUEUE_ARTICLE,
                'pending_outline' => self::ACTION_GENERATE_STRUCTURE,
                'structure_ready' => self::ACTION_WRITE_CHAPTERS,
                'writing' => self::ACTION_WRITE_CHAPTERS,
                'chapters_ready' => self::ACTION_GENERATE_ASSETS,
                'assets_ready' => self::ACTION_ASSEMBLE_ARTICLE,
                'assembly_ready' => self::ACTION_PUBLISH_ARTICLE,
                'published' => self::ACTION_MONITOR_WORKFLOW,
                'failed' => self::ACTION_FETCH_EXECUTION_LOG
            ];

            if (isset($statusMap[$queueStatus])) {
                $heuristicMatches[] = 'queue_status:' . $queueStatus;

                return [
                    'action' => $statusMap[$queueStatus],
                    'confidence' => 'medium',
                    'matches' => $heuristicMatches
                ];
            }
        }

        // If user is asking about status but no regex matched, default to monitoring
        if (preg_match('/\b(status|progress|state)\b/i', $message)) {
            return [
                'action' => self::ACTION_MONITOR_WORKFLOW,
                'confidence' => 'medium',
                'matches' => ['keyword:status']
            ];
        }

        return [
            'action' => 'unknown',
            'confidence' => 'low',
            'matches' => []
        ];
    }

    /**
     * Resolve category slugs to IDs
     */
    private function resolveCategoryIds(array $categorySlugs): array
    {
        $categories = $this->wpClient->getCategories();
        $ids = [];

        foreach ($categorySlugs as $slug) {
            foreach ($categories as $cat) {
                if ($cat['slug'] === $slug) {
                    $ids[] = $cat['id'];
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * Resolve tag names to IDs (creates tags if they don't exist)
     */
    private function resolveTagIds(array $tagNames): array
    {
        // For simplicity, return tag names directly
        // WordPress API will create tags if they don't exist
        return $tagNames;
    }

    /**
     * Resolve WordPress credentials once per lifecycle
     */
    private function getWordPressCredentials(array $config): array
    {
        if (isset($this->credentialCache['wordpress'])) {
            return $this->credentialCache['wordpress'];
        }

        $username = $config['wp_username'] ?? null;
        $password = $config['wp_app_password'] ?? null;

        $credentialAliases = $config['credential_aliases'] ?? [];
        $alias = $credentialAliases['wordpress'] ?? null;
        if ($alias) {
            $resolved = $this->resolveCredentialAlias($alias);
            if (is_array($resolved)) {
                $username = $resolved['username'] ?? $username;
                $password = $resolved['password'] ?? ($resolved['secret'] ?? $password);
            } elseif (is_string($resolved)) {
                $password = $resolved;
            }
        }

        if (!$username || !$password) {
            throw new AgentValidationException('WordPress credentials are required', [
                'field' => 'credential_aliases.wordpress',
                'error' => 'missing_credentials'
            ]);
        }

        $this->credentialCache['wordpress'] = [
            'username' => $username,
            'password' => $password
        ];

        return $this->credentialCache['wordpress'];
    }

    /**
     * Resolve a credential alias via injected credential manager
     */
    private function resolveCredentialAlias(string $alias)
    {
        if (!$this->credentialManager || !$alias) {
            return null;
        }

        $attemptOrder = ['resolve', 'get', 'fetch', 'retrieve', 'load'];

        foreach ($attemptOrder as $method) {
            if (!method_exists($this->credentialManager, $method)) {
                continue;
            }

            try {
                $result = $this->credentialManager->{$method}($alias);
            } catch (\Throwable $exception) {
                $this->logError('Credential manager error', [
                    'alias' => $alias,
                    'method' => $method,
                    'error' => $exception->getMessage()
                ]);
                continue;
            }

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Format data response for non-LLM actions
     */
    private function formatDataResponse(array $intent, array $result): string
    {
        $action = $intent['action'];

        switch ($action) {
            case self::ACTION_SEARCH_POSTS:
                $count = $result['count'] ?? 0;
                if ($count === 0) {
                    return "No posts found matching your search.";
                }

                $posts = $result['results'] ?? [];
                $output = "Found {$count} post(s):\n\n";

                foreach (array_slice($posts, 0, 5) as $post) {
                    $title = $post['title']['rendered'] ?? 'Untitled';
                    $excerpt = strip_tags($post['excerpt']['rendered'] ?? '');
                    $excerpt = substr($excerpt, 0, 100) . '...';
                    $output .= "• **{$title}**\n  {$excerpt}\n  [View Post]({$post['link']})\n\n";
                }

                return $output;

            case self::ACTION_GET_POST:
                $post = $result['post'] ?? null;
                if (!$post) {
                    return "Post not found.";
                }

                $title = $post['title']['rendered'] ?? 'Untitled';
                $content = strip_tags($post['content']['rendered'] ?? '');
                $content = substr($content, 0, 300) . '...';

                return "**{$title}**\n\n{$content}\n\n[View Post]({$post['link']})";

            case self::ACTION_LIST_CATEGORIES:
                $categories = $result['categories'] ?? [];
                if (empty($categories)) {
                    return "No categories found.";
                }

                $output = "Available categories:\n\n";
                foreach ($categories as $cat) {
                    $output .= "• {$cat['name']} (slug: {$cat['slug']})\n";
                }

                return $output;

            case self::ACTION_MONITOR_WORKFLOW:
                $status = $result['workflow_status'] ?? 'unknown';
                $articleId = $result['article_id'] ?? 'n/a';
                return "Workflow status for article {$articleId}: {$status}.";

            case self::ACTION_FETCH_EXECUTION_LOG:
                $logPointer = $result['execution_log'] ?? 'not provided';
                return "Execution log reference: {$logPointer}.";

            default:
                return "Action completed: " . $action;
        }
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        parent::cleanup();

        $this->wpClient = null;
        $this->logDebug('WordPress agent cleanup completed');
    }
}
