<?php

use ChatbotBoilerplate\Exceptions\AgentProcessingException;
use ChatbotBoilerplate\Exceptions\AgentValidationException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../agents/wordpress/WordPressAgent.php';

final class WordPressAgentWorkflowTest extends TestCase
{
    private WordPressAgent $agent;
    private StubQueueService $queueService;
    private StubConfigurationService $configurationService;
    private StubGeneratorService $generatorService;
    private StubPublisherService $publisherService;
    private StubExecutionLogger $executionLogger;
    private array $specializedConfig;

    protected function setUp(): void
    {
        $configuration = $this->loadFixture('configuration.json');
        $queueEntry = $this->loadFixture('queue_entry.json');
        $internalLinks = $this->loadFixture('internal_links.json');

        $this->configurationService = new StubConfigurationService($configuration, $internalLinks);
        $this->queueService = new StubQueueService($queueEntry);
        $this->generatorService = new StubGeneratorService();
        $this->publisherService = new StubPublisherService();
        $this->executionLogger = new StubExecutionLogger();

        $this->agent = new WordPressAgent();
        $this->agent->initialize([
            'wordpress_blog_configuration_service' => $this->configurationService,
            'wordpress_blog_queue_service' => $this->queueService,
            'wordpress_blog_generator_service' => $this->generatorService,
            'wordpress_blog_publisher' => $this->publisherService,
            'wordpress_blog_execution_logger' => $this->executionLogger,
        ]);

        $this->specializedConfig = [
            'wp_site_url' => 'https://demo.example.com',
            'wp_username' => 'admin',
            'wp_app_password' => 'app-password-123',
            'configuration_id' => 'config-123',
            'article_queue_id' => 'queue-789',
            'default_status' => 'draft',
            'workflow_phases' => ['generate_assets' => true],
            'image_preferences' => ['enabled' => true],
            'storage_preferences' => ['provider' => 's3'],
            'credential_aliases' => ['openai' => 'alias-openai'],
            'enable_execution_logging' => true,
        ];
    }

    public function testBuildContextEnrichesWorkflow(): void
    {
        $messages = $this->messages('What is the status of the queued article?');

        $context = $this->agent->buildContext($messages, [
            'id' => 'agent-status',
            'specialized_config' => $this->specializedConfig,
        ]);

        $this->assertSame('monitor_workflow', $context['user_intent']['action']);
        $this->assertSame('queue-789', $context['blog_workflow']['queue_id']);
        $this->assertSame('config-123', $context['blog_workflow']['configuration_id']);
        $this->assertSame('article-789', $context['blog_workflow']['metadata']['article_id']);
        $this->assertCount(2, $context['blog_workflow']['internal_links']);
        $this->assertSame('chapters_ready', $context['blog_workflow']['queue_entry']['status']);
    }

    public function testValidateInputFailsWhenConfigurationMissing(): void
    {
        $agent = new WordPressAgent();
        $agent->initialize([
            'wordpress_blog_configuration_service' => new StubConfigurationService(null, []),
            'wordpress_blog_queue_service' => new StubQueueService(null),
            'wordpress_blog_execution_logger' => new StubExecutionLogger(),
        ]);

        $context = [
            'specialized_config' => [
                'wp_site_url' => 'https://demo.example.com',
                'wp_username' => 'admin',
                'wp_app_password' => 'app-password-123',
                'configuration_id' => 'missing-config',
                'article_queue_id' => 'missing-queue',
                'enable_execution_logging' => true,
            ],
            'blog_workflow' => [
                'configuration_id' => 'missing-config',
                'queue_id' => 'missing-queue',
                'configuration' => null,
                'queue_entry' => null,
                'execution_log' => null,
            ],
        ];

        $this->expectException(AgentValidationException::class);
        $agent->validateInput($this->messages('Publish this post'), $context);
    }

    public function testGenerateAssetsWorkflowHappyPath(): void
    {
        $messages = $this->messages('Generate chapter images and featured image');
        $context = $this->agent->buildContext($messages, [
            'id' => 'agent-happy-path',
            'specialized_config' => $this->specializedConfig,
        ]);

        $validated = $this->agent->validateInput($messages, $context);
        $processed = $this->agent->process($validated, $context);

        $this->assertSame('assets_ready', $processed['result']['status']);
        $this->assertSame('queue-789', $processed['result']['queue_id']);
        $this->assertSame('https://logs.example.com/run/queue-789/assets-completed', $processed['result']['execution_log']);

        $statuses = array_column($this->queueService->statusCalls, 'status');
        $this->assertEquals(['processing', 'completed'], $statuses);
        $this->assertFalse($this->agent->requiresLLM($processed, $context));
    }

    public function testPublishFailureWrapsAgentProcessingException(): void
    {
        $this->publisherService->publishException = new Exception('Invalid publish payload', 500);

        $messages = $this->messages('Publish the assembled article now');
        $context = $this->agent->buildContext($messages, [
            'id' => 'agent-publish',
            'specialized_config' => $this->specializedConfig,
        ]);
        $validated = $this->agent->validateInput($messages, $context);

        try {
            $this->agent->process($validated, $context);
            $this->fail('Expected processing exception');
        } catch (AgentProcessingException $exception) {
            $this->assertStringContainsString('failed', strtolower($exception->getMessage()));
            $this->assertSame('failed', end($this->queueService->statusCalls)['status']);
            $this->assertStringContainsString('logs.example.com', $exception->getMessage());
        }
    }

    public function testImageGenerationRateLimitSchedulesRetry(): void
    {
        $this->generatorService->generateAssetsException = new Exception('Rate limit exceeded', 429);

        $messages = $this->messages('Generate image assets for this queued article');
        $context = $this->agent->buildContext($messages, [
            'id' => 'agent-retry',
            'specialized_config' => $this->specializedConfig,
        ]);
        $validated = $this->agent->validateInput($messages, $context);

        try {
            $this->agent->process($validated, $context);
            $this->fail('Expected retry scheduling exception');
        } catch (AgentProcessingException $exception) {
            $this->assertStringContainsString('retry', strtolower($exception->getMessage()));
            $this->assertContains('retry_scheduled', array_column($this->queueService->statusCalls, 'status'));
        }
    }

    private function messages(string $content): array
    {
        return [
            ['role' => 'user', 'content' => $content],
        ];
    }

    private function loadFixture(string $fixture): array
    {
        $path = __DIR__ . '/../fixtures/wordpress_blog/' . $fixture;
        $contents = file_get_contents($path);
        return json_decode($contents, true) ?? [];
    }
}

final class StubConfigurationService
{
    public function __construct(private ?array $configuration, private array $internalLinks)
    {
    }

    public function getConfigurationById(string $configurationId): ?array
    {
        if (!$this->configuration || ($this->configuration['configuration_id'] ?? null) !== $configurationId) {
            return null;
        }

        return $this->configuration;
    }

    public function getInternalLinks(string $configurationId): array
    {
        return $this->internalLinks;
    }
}

final class StubQueueService
{
    public array $statusCalls = [];

    public function __construct(private ?array $queueEntry)
    {
    }

    public function updateStatus($queueId, $status, $details = [])
    {
        $this->statusCalls[] = [
            'queue_id' => $queueId,
            'status' => $status,
            'details' => $details,
        ];

        return [
            'queue_id' => $queueId,
            'status' => $status,
            'details' => $details,
        ];
    }

    public function getQueueEntryById(string $queueId): ?array
    {
        return $this->queueEntry;
    }
}

final class StubGeneratorService
{
    public ?Exception $generateAssetsException = null;

    public function generateAssets(...$workflow): array
    {
        if ($this->generateAssetsException) {
            throw $this->generateAssetsException;
        }

        return [
            'featured_image' => 'https://cdn.example.com/featured.png',
            'chapter_images' => [
                'chapter-1' => 'https://cdn.example.com/ch1.png',
                'chapter-2' => 'https://cdn.example.com/ch2.png',
            ],
        ];
    }
}

final class StubPublisherService
{
    public ?Exception $publishException = null;

    public function publishArticle(array $workflow): array
    {
        if ($this->publishException) {
            throw $this->publishException;
        }

        return [
            'post_id' => 501,
            'status' => 'publish',
        ];
    }
}

final class StubExecutionLogger
{
    public array $logCalls = [];

    public function logPhase($queueId, $articleId, $phase, $status, array $payload)
    {
        $this->logCalls[] = compact('queueId', 'articleId', 'phase', 'status', 'payload');

        return sprintf('https://logs.example.com/run/%s/%s-%s', $queueId, $phase, $status);
    }
}
