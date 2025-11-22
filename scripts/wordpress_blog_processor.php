<?php
/**
 * WordPress Blog Processor Script
 *
 * CLI script for processing blog article queue. Can be run manually or via cron.
 *
 * Usage:
 *   php scripts/wordpress_blog_processor.php [options]
 *
 * Options:
 *   --max=N              Maximum number of articles to process (default: 10)
 *   --article-id=ID      Process specific article by ID
 *   --health-check       Run system health check
 *   --stats              Show processing statistics
 *   --retry-failed       Retry all failed articles
 *   --cleanup=N          Clean up articles older than N days (default: 30)
 *   --verbose            Verbose output
 *   --help               Show this help message
 *
 * Exit Codes:
 *   0 - Success
 *   1 - General error
 *   2 - Configuration error
 *   3 - Health check failed
 *
 * Examples:
 *   php scripts/wordpress_blog_processor.php --max=5 --verbose
 *   php scripts/wordpress_blog_processor.php --article-id=abc123
 *   php scripts/wordpress_blog_processor.php --health-check
 *   php scripts/wordpress_blog_processor.php --retry-failed
 *
 * @package WordPressBlog\Scripts
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Load dependencies
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/SecretsManager.php';
require_once __DIR__ . '/../includes/CryptoAdapter.php';
require_once __DIR__ . '/../includes/OpenAIClient.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressBlogConfigurationService.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressBlogQueueService.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressContentStructureBuilder.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressChapterContentWriter.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressImageGenerator.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressAssetOrganizer.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressPublisher.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressBlogExecutionLogger.php';
require_once __DIR__ . '/../includes/WordPressBlog/Services/WordPressBlogGeneratorService.php';
require_once __DIR__ . '/../includes/WordPressBlog/Orchestration/WordPressBlogWorkflowOrchestrator.php';

/**
 * Parse command line arguments
 */
function parseArgs($argv) {
    $args = [
        'max' => 10,
        'article_id' => null,
        'health_check' => false,
        'stats' => false,
        'stats_days' => 7,
        'retry_failed' => false,
        'cleanup' => null,
        'verbose' => false,
        'help' => false
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
        } elseif ($arg === '--health-check') {
            $args['health_check'] = true;
        } elseif ($arg === '--stats') {
            $args['stats'] = true;
        } elseif ($arg === '--retry-failed') {
            $args['retry_failed'] = true;
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $args['verbose'] = true;
        } elseif (strpos($arg, '--max=') === 0) {
            $args['max'] = intval(substr($arg, 6));
        } elseif (strpos($arg, '--article-id=') === 0) {
            $args['article_id'] = substr($arg, 13);
        } elseif (strpos($arg, '--stats-days=') === 0) {
            $args['stats_days'] = intval(substr($arg, 13));
        } elseif (strpos($arg, '--cleanup=') === 0) {
            $args['cleanup'] = intval(substr($arg, 10));
        }
    }

    return $args;
}

/**
 * Show help message
 */
function showHelp() {
    echo <<<HELP
WordPress Blog Processor Script

Usage:
  php scripts/wordpress_blog_processor.php [options]

Options:
  --max=N              Maximum number of articles to process (default: 10)
  --article-id=ID      Process specific article by ID
  --health-check       Run system health check
  --stats              Show processing statistics
  --stats-days=N       Days to include in statistics (default: 7)
  --retry-failed       Retry all failed articles
  --cleanup=N          Clean up articles older than N days (default: 30)
  --verbose, -v        Verbose output
  --help, -h           Show this help message

Exit Codes:
  0 - Success
  1 - General error
  2 - Configuration error
  3 - Health check failed

Examples:
  # Process up to 5 articles from queue
  php scripts/wordpress_blog_processor.php --max=5 --verbose

  # Process specific article
  php scripts/wordpress_blog_processor.php --article-id=abc123

  # Run health check
  php scripts/wordpress_blog_processor.php --health-check

  # Show statistics
  php scripts/wordpress_blog_processor.php --stats --stats-days=30

  # Retry failed articles
  php scripts/wordpress_blog_processor.php --retry-failed --verbose

  # Clean up old articles
  php scripts/wordpress_blog_processor.php --cleanup=30

HELP;
}

/**
 * Initialize services
 */
function initializeServices() {
    try {
        // Initialize secrets manager
        $secretsManager = new SecretsManager();

        // Initialize database
        $db = new DB([
            'db_type' => 'sqlite',
            'db_path' => __DIR__ . '/../db/chatbot.db'
        ]);

        // Initialize crypto adapter
        $encryptionKey = $secretsManager->get('WORDPRESS_BLOG_ENCRYPTION_KEY')
            ?? $secretsManager->get('ENCRYPTION_KEY')
            ?? 'default-key-please-change-in-production';

        $cryptoAdapter = new CryptoAdapter([
            'encryption_key' => $encryptionKey
        ]);

        // Initialize OpenAI client
        $openaiApiKey = $secretsManager->get('OPENAI_API_KEY');
        if (!$openaiApiKey) {
            throw new Exception('OPENAI_API_KEY not found in environment');
        }

        $openAIClient = new OpenAIClient([
            'api_key' => $openaiApiKey,
            'base_url' => 'https://api.openai.com/v1'
        ]);

        // Initialize orchestrator
        $orchestrator = new WordPressBlogWorkflowOrchestrator($db, $cryptoAdapter, $openAIClient);

        return [
            'db' => $db,
            'orchestrator' => $orchestrator
        ];

    } catch (Exception $e) {
        echo "✗ Initialization failed: " . $e->getMessage() . "\n";
        exit(2);
    }
}

/**
 * Run health check
 */
function runHealthCheck($orchestrator, $verbose) {
    echo "Running system health check...\n\n";

    $health = $orchestrator->healthCheck();

    echo "Overall Status: " . strtoupper($health['status']) . "\n";
    echo "Timestamp: {$health['timestamp']}\n\n";

    echo "Component Checks:\n";
    foreach ($health['checks'] as $component => $check) {
        $icon = $check['status'] === 'ok' ? '✓' : ($check['status'] === 'warning' ? '⚠' : '✗');
        echo "  {$icon} {$component}: {$check['message']}\n";

        if ($verbose && isset($check['statistics'])) {
            echo "     Statistics: " . json_encode($check['statistics']) . "\n";
        }
    }

    echo "\n";

    return $health['status'] === 'healthy' ? 0 : 3;
}

/**
 * Show statistics
 */
function showStatistics($orchestrator, $days, $verbose) {
    echo "Processing Statistics (last {$days} days)\n\n";

    $stats = $orchestrator->getProcessingStatistics($days);
    $queueStats = $orchestrator->getQueueStatistics();

    echo "Total Articles: {$stats['total_articles']}\n\n";

    echo "By Status:\n";
    foreach ($stats['by_status'] as $status => $data) {
        $count = $data['count'];
        $avgDuration = $data['avg_duration_seconds']
            ? round($data['avg_duration_seconds']) . 's'
            : 'N/A';

        echo "  {$status}: {$count} articles (avg duration: {$avgDuration})\n";
    }

    echo "\n";

    echo "Current Queue:\n";
    foreach ($queueStats as $status => $count) {
        echo "  {$status}: {$count}\n";
    }

    echo "\n";

    return 0;
}

/**
 * Process queue
 */
function processQueue($orchestrator, $maxArticles, $verbose) {
    echo "Processing queue (max: {$maxArticles} articles)...\n\n";

    $results = $orchestrator->processQueue($maxArticles, $verbose);

    echo "\n";
    echo "Processing Summary:\n";
    echo "  Processed: {$results['processed']}\n";
    echo "  Succeeded: {$results['succeeded']}\n";
    echo "  Failed: {$results['failed']}\n";

    if ($verbose && !empty($results['articles'])) {
        echo "\nArticle Details:\n";
        foreach ($results['articles'] as $article) {
            $icon = $article['status'] === 'success' ? '✓' : '✗';
            echo "  {$icon} {$article['article_id']}: {$article['status']}\n";

            if (isset($article['post_url'])) {
                echo "     URL: {$article['post_url']}\n";
            }
            if (isset($article['error'])) {
                echo "     Error: {$article['error']}\n";
            }
        }
    }

    echo "\n";

    return $results['failed'] > 0 ? 1 : 0;
}

/**
 * Process specific article
 */
function processSpecificArticle($orchestrator, $articleId, $verbose) {
    echo "Processing article: {$articleId}\n\n";

    $result = $orchestrator->processSpecificArticle($articleId, $verbose);

    echo "\n";

    if ($result['success']) {
        echo "✓ Article processed successfully\n";

        if (isset($result['post_url'])) {
            echo "  Post URL: {$result['post_url']}\n";
        }

        if (isset($result['attempts'])) {
            echo "  Attempts: {$result['attempts']}\n";
        }

        echo "\n";
        return 0;
    } else {
        echo "✗ Article processing failed\n";
        echo "  Error: {$result['error']}\n";

        if (isset($result['attempts'])) {
            echo "  Attempts: {$result['attempts']}\n";
        }

        echo "\n";
        return 1;
    }
}

/**
 * Retry failed articles
 */
function retryFailedArticles($orchestrator, $verbose) {
    echo "Retrying failed articles...\n\n";

    $results = $orchestrator->retryFailedArticles($verbose);

    echo "\n";
    echo "Retry Summary:\n";
    echo "  Total: {$results['total']}\n";
    echo "  Retried: {$results['retried']}\n";
    echo "  Succeeded: {$results['succeeded']}\n";
    echo "  Failed: {$results['failed']}\n";
    echo "\n";

    return $results['failed'] > 0 ? 1 : 0;
}

/**
 * Clean up old articles
 */
function cleanupOldArticles($orchestrator, $days, $verbose) {
    echo "Cleaning up articles older than {$days} days...\n\n";

    $deleted = $orchestrator->cleanupOldArticles($days);

    echo "✓ Deleted {$deleted} old articles\n\n";

    return 0;
}

// Main execution
try {
    $args = parseArgs($argv);

    if ($args['help']) {
        showHelp();
        exit(0);
    }

    $services = initializeServices();
    $orchestrator = $services['orchestrator'];

    // Health check
    if ($args['health_check']) {
        exit(runHealthCheck($orchestrator, $args['verbose']));
    }

    // Statistics
    if ($args['stats']) {
        exit(showStatistics($orchestrator, $args['stats_days'], $args['verbose']));
    }

    // Cleanup
    if ($args['cleanup'] !== null) {
        exit(cleanupOldArticles($orchestrator, $args['cleanup'], $args['verbose']));
    }

    // Retry failed
    if ($args['retry_failed']) {
        exit(retryFailedArticles($orchestrator, $args['verbose']));
    }

    // Process specific article
    if ($args['article_id']) {
        exit(processSpecificArticle($orchestrator, $args['article_id'], $args['verbose']));
    }

    // Process queue (default action)
    exit(processQueue($orchestrator, $args['max'], $args['verbose']));

} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    if (isset($args['verbose']) && $args['verbose']) {
        echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
}
