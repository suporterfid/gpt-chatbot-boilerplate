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
     * Supported actions
     */
    private const ACTION_CREATE_POST = 'create_post';
    private const ACTION_UPDATE_POST = 'update_post';
    private const ACTION_SEARCH_POSTS = 'search_posts';
    private const ACTION_GET_POST = 'get_post';
    private const ACTION_LIST_CATEGORIES = 'list_categories';

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

        // Extract user intent from recent messages
        $userMessage = $this->extractUserMessage($messages, -1);
        $intent = $this->detectUserIntent($userMessage);

        $context['user_intent'] = $intent;
        $context['user_message'] = $userMessage;

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

                default:
                    // Unknown action - let LLM handle it
                    $this->logInfo('No specific action detected, using LLM');
                    $result = [
                        'action' => 'llm_assisted',
                        'message' => $userMessage
                    ];
            }
        } catch (Exception $e) {
            $this->logError('WordPress action failed', [
                'action' => $intent['action'],
                'error' => $e->getMessage()
            ]);

            throw new AgentProcessingException(
                'Failed to execute WordPress action: ' . $e->getMessage()
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

        // Skip LLM for simple data retrieval
        if (in_array($action, [
            self::ACTION_SEARCH_POSTS,
            self::ACTION_GET_POST,
            self::ACTION_LIST_CATEGORIES
        ])) {
            return false; // Return data directly
        }

        // Use LLM for content creation and updates
        return true;
    }

    public function prepareLLMMessages(array $processedData, array $context): array
    {
        $messages = $processedData['messages'];
        $intent = $processedData['intent'];
        $result = $processedData['result'];

        // Build enhanced system message
        $systemMessage = $context['agent_config']['system_message'] ??
            'You are a WordPress content management assistant.';

        $systemMessage .= "\n\nYou help users create and manage WordPress content.";
        $systemMessage .= "\nYou have access to the WordPress REST API.";

        // Add context about the detected action
        if (isset($intent['action'])) {
            $systemMessage .= "\n\nThe user wants to: " . $intent['action'];
        }

        // Add context about available tools
        $systemMessage .= "\n\nUse the available tools to interact with WordPress.";

        // Inject result context if available
        if ($result && isset($result['post_id'])) {
            $systemMessage .= "\n\nA post operation was initiated (Post ID: " . $result['post_id'] . ").";
        }

        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemMessage
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

    /**
     * Detect user intent from message
     */
    private function detectUserIntent(string $message): array
    {
        $patterns = [
            self::ACTION_CREATE_POST => '/\b(create|write|make|publish|post|new)\b.*\b(post|article|blog)/i',
            self::ACTION_UPDATE_POST => '/\b(update|edit|modify|change)\b.*\b(post|article)/i',
            self::ACTION_SEARCH_POSTS => '/\b(search|find|look for|query)\b.*\b(post|article)/i',
            self::ACTION_GET_POST => '/\b(get|show|display|view)\b.*\b(post|article)/i',
            self::ACTION_LIST_CATEGORIES => '/\b(list|show|get)\b.*\b(categories|category)/i'
        ];

        $detected = $this->detectIntent($message, $patterns);

        if ($detected) {
            return [
                'action' => $detected['intent'],
                'confidence' => 'high',
                'matches' => $detected['matches']
            ];
        }

        return [
            'action' => 'unknown',
            'confidence' => 'low'
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
