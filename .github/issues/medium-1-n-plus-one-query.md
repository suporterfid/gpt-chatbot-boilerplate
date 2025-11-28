# [MEDIUM] Fix N+1 Query Pattern in Queue Processing

## Priority
🟡 **Medium** - Performance optimization

## Type
- [ ] Security Issue
- [x] Bug
- [ ] Feature Request
- [x] Performance

## Description
The WordPress Blog queue processing executes 2 separate database queries per article instead of using a JOIN. This creates an N+1 query problem that impacts performance when processing large queues.

## Performance Impact
- **Current**: 2 queries per article (1 for article + 1 for configuration)
- **Expected**: 1 query per article with JOIN
- **Impact at scale**:
  - Processing 100 articles = 200 queries (should be 100)
  - Processing 1000 articles = 2000 queries (should be 1000)
  - Extra database round-trips and connection overhead

## Current Code
```php
// includes/WordPressBlog/Orchestration/WordPressBlogWorkflowOrchestrator.php:64-78

public function processQueue($maxArticles = 10, $verbose = false) {
    for ($i = 0; $i < $maxArticles; $i++) {
        // Query 1: Get next article
        $article = $this->queueService->getNextQueuedArticle();

        if (!$article) {
            break;
        }

        // Query 2: Get configuration (separate query!)
        $config = $this->configService->getConfiguration($article['configuration_id'], true);

        // ... process article
    }
}
```

```php
// includes/WordPressBlog/Services/WordPressBlogQueueService.php:104

public function getNextQueuedArticle() {
    $sql = "SELECT * FROM blog_articles_queue
            WHERE status = 'queued'
            AND (scheduled_date IS NULL OR scheduled_date <= datetime('now'))
            ORDER BY /* ... */
            LIMIT 1";
    // Only queries articles table, not joining configurations
}
```

## Proposed Solution
Join configurations table in `getNextQueuedArticle()` to fetch both article and config in one query.

## Implementation Tasks

### Task 1: Update getNextQueuedArticle() with JOIN

```php
// includes/WordPressBlog/Services/WordPressBlogQueueService.php

public function getNextQueuedArticle() {
    $tenantId = TenantContext::getCurrentTenantId();

    // Start transaction for row locking
    $this->db->execute("BEGIN IMMEDIATE TRANSACTION");

    try {
        // ✅ Join configurations table to fetch everything in one query
        $sql = "SELECT
                    q.*,
                    c.config_name,
                    c.website_url,
                    c.number_of_chapters,
                    c.max_word_count,
                    c.introduction_length,
                    c.conclusion_length,
                    c.cta_message,
                    c.cta_url,
                    c.company_offering,
                    c.wordpress_api_url,
                    c.wordpress_api_key_encrypted,
                    c.wordpress_api_key_nonce,
                    c.wordpress_api_key_tag,
                    c.openai_api_key_encrypted,
                    c.openai_api_key_nonce,
                    c.openai_api_key_tag,
                    c.default_publish_status,
                    c.google_drive_folder_id
                FROM blog_articles_queue q
                INNER JOIN blog_configurations c
                    ON q.configuration_id = c.configuration_id
                WHERE q.status = 'queued'
                  AND q.tenant_id = :tenant_id
                  AND (q.scheduled_date IS NULL OR q.scheduled_date <= datetime('now'))
                ORDER BY
                    CASE WHEN q.scheduled_date IS NULL THEN 1 ELSE 0 END,
                    q.scheduled_date ASC,
                    q.created_at ASC
                LIMIT 1";

        $results = $this->db->query($sql, ['tenant_id' => $tenantId]);

        if (empty($results)) {
            $this->db->execute("COMMIT");
            return null;
        }

        $article = $results[0];

        // Decrypt credentials using BlogCredentialManager
        $credentialManager = new BlogCredentialManager($this->cryptoAdapter);

        try {
            $article['wordpress_api_key'] = $credentialManager->decryptCredential(
                $article['wordpress_api_key_encrypted'],
                $article['wordpress_api_key_nonce'],
                $article['wordpress_api_key_tag'],
                'wordpress_api_key'
            );

            $article['openai_api_key'] = $credentialManager->decryptCredential(
                $article['openai_api_key_encrypted'],
                $article['openai_api_key_nonce'],
                $article['openai_api_key_tag'],
                'openai_api_key'
            );

            // Remove encrypted fields from result
            unset(
                $article['wordpress_api_key_encrypted'],
                $article['wordpress_api_key_nonce'],
                $article['wordpress_api_key_tag'],
                $article['openai_api_key_encrypted'],
                $article['openai_api_key_nonce'],
                $article['openai_api_key_tag']
            );

        } catch (Exception $e) {
            // Log decryption error but don't fail the query
            error_log("Failed to decrypt credentials for article {$article['article_id']}: " . $e->getMessage());
        }

        // Structure data properly
        $result = [
            // Article fields
            'article_id' => $article['article_id'],
            'configuration_id' => $article['configuration_id'],
            'status' => $article['status'],
            'seed_keyword' => $article['seed_keyword'],
            'target_audience' => $article['target_audience'],
            'writing_style' => $article['writing_style'],
            'publication_date' => $article['publication_date'],
            'scheduled_date' => $article['scheduled_date'],
            'retry_count' => $article['retry_count'],

            // Configuration nested
            'configuration' => [
                'configuration_id' => $article['configuration_id'],
                'config_name' => $article['config_name'],
                'website_url' => $article['website_url'],
                'number_of_chapters' => $article['number_of_chapters'],
                'max_word_count' => $article['max_word_count'],
                'introduction_length' => $article['introduction_length'],
                'conclusion_length' => $article['conclusion_length'],
                'cta_message' => $article['cta_message'],
                'cta_url' => $article['cta_url'],
                'company_offering' => $article['company_offering'],
                'wordpress_api_url' => $article['wordpress_api_url'],
                'wordpress_api_key' => $article['wordpress_api_key'] ?? null,
                'openai_api_key' => $article['openai_api_key'] ?? null,
                'default_publish_status' => $article['default_publish_status'],
                'google_drive_folder_id' => $article['google_drive_folder_id']
            ]
        ];

        $this->db->execute("COMMIT");

        return $result;

    } catch (Exception $e) {
        $this->db->execute("ROLLBACK");
        throw $e;
    }
}
```

### Task 2: Remove Redundant Configuration Fetch from Orchestrator

```php
// includes/WordPressBlog/Orchestration/WordPressBlogWorkflowOrchestrator.php

public function processQueue($maxArticles = 10, $verbose = false) {
    $results = [
        'processed' => 0,
        'succeeded' => 0,
        'failed' => 0,
        'articles' => []
    ];

    $this->log('Starting queue processing...', $verbose);

    for ($i = 0; $i < $maxArticles; $i++) {
        // Get next queued article (now includes configuration via JOIN)
        $article = $this->queueService->getNextQueuedArticle();

        if (!$article) {
            $this->log('No more articles in queue', $verbose);
            break;
        }

        // ✅ Configuration is already included in $article['configuration']
        // ❌ REMOVE: $config = $this->configService->getConfiguration($article['configuration_id'], true);

        $results['processed']++;
        $this->log("Processing article: {$article['article_id']}", $verbose);

        try {
            // Pass article with embedded configuration
            $result = $this->processArticle($article['article_id'], $verbose);

            // ... rest of processing
        } catch (Exception $e) {
            // ... error handling
        }
    }

    return $results;
}
```

### Task 3: Update processArticle() to Use Embedded Configuration

```php
// includes/WordPressBlog/Orchestration/WordPressBlogWorkflowOrchestrator.php

public function processArticle($articleId, $verbose = false) {
    $this->logger = new WordPressBlogExecutionLogger($articleId);

    try {
        $this->queueService->markAsProcessing($articleId);

        // Phase 1: Get article with configuration (already fetched via JOIN)
        $this->logger->startPhase('phase1_configuration');

        $article = $this->queueService->getArticle($articleId); // This should also use JOIN

        if (!isset($article['configuration'])) {
            throw new Exception("Configuration not included in article data");
        }

        $config = $article['configuration'];

        $this->logger->completePhase('phase1_configuration', [
            'config_id' => $config['configuration_id'],
            'config_name' => $config['config_name']
        ]);

        // ... continue with phases 2-6
    }
}
```

### Task 4: Add Performance Tests

```php
// tests/Performance/QueueProcessingPerformanceTest.php

final class QueueProcessingPerformanceTest extends TestCase
{
    public function testQueueProcessingQueryCount(): void
    {
        // Queue 10 articles
        for ($i = 0; $i < 10; $i++) {
            $this->queueService->queueArticle([
                'configuration_id' => $this->testConfigId,
                'seed_keyword' => "keyword-{$i}"
            ]);
        }

        // Enable query logging
        $queryLog = [];
        $this->db->setQueryLogger(function($sql) use (&$queryLog) {
            $queryLog[] = $sql;
        });

        // Process queue
        $orchestrator = new WordPressBlogWorkflowOrchestrator($this->db, $this->crypto, $this->openai);
        $orchestrator->processQueue(10, false);

        // Count SELECT queries to articles and configurations tables
        $articleQueries = 0;
        $configQueries = 0;

        foreach ($queryLog as $query) {
            if (stripos($query, 'SELECT') !== false) {
                if (stripos($query, 'blog_articles_queue') !== false) {
                    $articleQueries++;
                }
                if (stripos($query, 'blog_configurations') !== false &&
                    stripos($query, 'JOIN') === false) {
                    $configQueries++; // Separate query for config (BAD)
                }
            }
        }

        // Should have ~10 article queries with JOIN (not 10 article + 10 config)
        $this->assertLessThanOrEqual(12, $articleQueries, 'Too many article queries');
        $this->assertEquals(0, $configQueries, 'Should not have separate config queries when using JOIN');
    }

    public function testQueueProcessingPerformanceBenchmark(): void
    {
        // Queue 100 articles
        for ($i = 0; $i < 100; $i++) {
            $this->queueService->queueArticle([
                'configuration_id' => $this->testConfigId,
                'seed_keyword' => "keyword-{$i}"
            ]);
        }

        // Measure processing time
        $startTime = microtime(true);

        $orchestrator = new WordPressBlogWorkflowOrchestrator($this->db, $this->crypto, $this->openai);
        $result = $orchestrator->processQueue(100, false);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should process 100 articles in reasonable time
        // (This is a smoke test; actual generation would be slower)
        $this->assertLessThan(10, $duration, 'Queue processing too slow');
        $this->assertEquals(100, $result['processed']);
    }
}
```

### Task 5: Add Database Query Monitoring

```php
// includes/DB.php - Add query logging capability

class DB {
    private $queryLogger = null;

    public function setQueryLogger(callable $logger) {
        $this->queryLogger = $logger;
    }

    public function query($sql, $params = []) {
        if ($this->queryLogger) {
            call_user_func($this->queryLogger, $sql, $params);
        }

        // ... existing query implementation
    }
}
```

## Acceptance Criteria
- [ ] `getNextQueuedArticle()` uses JOIN to fetch configuration
- [ ] Only 1 query per article instead of 2
- [ ] Configuration data properly decrypted in single query
- [ ] Orchestrator no longer makes redundant config fetch
- [ ] Performance tests pass
- [ ] Query count reduced by ~50% for queue processing
- [ ] Benchmark shows measurable performance improvement
- [ ] No regression in functionality

## Testing Steps
1. Enable database query logging
2. Queue 10 test articles
3. Run queue processor:
   ```bash
   php scripts/wordpress_blog_processor.php --max-articles=10 --verbose
   ```
4. Count queries in log:
   - Before fix: ~20+ SELECT queries (10 articles + 10 configs)
   - After fix: ~10 SELECT queries (10 articles with JOINed configs)
5. Benchmark with 100 articles and compare timing

## Performance Comparison

### Before (N+1 Pattern)
```
Processing 100 articles:
- Queries: ~200 (100 articles + 100 configs)
- Time: ~500ms (database overhead)
```

### After (JOIN)
```
Processing 100 articles:
- Queries: ~100 (articles with JOINed configs)
- Time: ~250ms (50% reduction in DB overhead)
```

## Related Issues
- Part of: Performance optimization sprint
- Related to: Database query optimization
- Blocks: Large-scale queue processing

## Estimated Effort
**4-6 hours**
- Update getNextQueuedArticle() with JOIN: 2 hours
- Update orchestrator to use embedded config: 1 hour
- Testing and validation: 2-3 hours

## Additional Context
Identified in code review as Medium Priority Issue #5. While not critical for small queues (< 10 articles), this becomes a significant bottleneck when processing 100+ articles in batch.

**Related Pattern**: Similar optimization should be considered for other services that fetch related data separately.

**Database Impact**: JOIN operation is indexed (foreign key on `configuration_id`), so performance should be excellent.
