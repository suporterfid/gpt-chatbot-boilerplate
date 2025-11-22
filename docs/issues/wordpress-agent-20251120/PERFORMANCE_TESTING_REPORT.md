# WordPress Blog Automation - Performance Testing Report

## Overview

This document outlines performance benchmarks, load testing procedures, and optimization recommendations for the WordPress Blog Automation system.

**Testing Date**: November 21, 2025
**Environment**: Staging/Production-like
**Database**: MySQL 5.7 (recommended for production)
**Server**: 4GB RAM, 2 CPU cores

---

## Performance Benchmarks

### Database Operations Benchmarks

| Operation | Target | Measurement Method | Status |
|-----------|--------|-------------------|--------|
| INSERT configuration | <50ms | Time single INSERT | Pending |
| SELECT configuration by ID | <20ms | Time single SELECT with WHERE | Pending |
| SELECT all configurations | <100ms | Time SELECT without WHERE | Pending |
| UPDATE configuration | <30ms | Time UPDATE with WHERE | Pending |
| DELETE configuration | <40ms | Time DELETE with CASCADE | Pending |
| INSERT article to queue | <50ms | Time single INSERT | Pending |
| SELECT queue with filter | <100ms | Time SELECT with WHERE and LIMIT | Pending |
| UPDATE article status | <25ms | Time UPDATE status field | Pending |
| INSERT execution log entry | <30ms | Time single INSERT | Pending |
| SELECT execution log | <50ms | Time SELECT with JOIN | Pending |
| Complex metrics query | <500ms | Time aggregation query | Pending |

**Testing Script**:
```php
<?php
// tests/Performance/DatabaseBenchmark.php

require_once 'vendor/autoload.php';

$db = new DB(['db_type' => 'mysql', ...]);

// Benchmark INSERT configuration
$start = microtime(true);
$db->insert('wp_blog_configurations', [
    'config_name' => 'Benchmark Test',
    'wordpress_site_url' => 'https://test.com',
    ...
]);
$end = microtime(true);
$insertTime = ($end - $start) * 1000; // Convert to ms

echo "Configuration INSERT: {$insertTime}ms\n";

// Repeat for each operation
// Run 100 times and calculate average
?>
```

---

### Content Generation Benchmarks

| Phase | Target | Measurement Method | Status |
|-------|--------|-------------------|--------|
| Structure building | <30s | Time buildStructure() method | Pending |
| Single chapter generation (400 words) | <25s | Time single OpenAI API call | Pending |
| Full content generation (5 chapters, 2000 words) | <120s | Time generateContent() method | Pending |
| Featured image generation | <30s | Time Replicate API call | Pending |
| Asset organization (Google Drive upload) | <20s | Time organizeAssets() method | Pending |
| WordPress publishing | <15s | Time publishToWordPress() method | Pending |
| **Total workflow (2000-word article)** | **<5 min** | **Time complete processing** | **Pending** |

**Testing Script**:
```bash
#!/bin/bash
# tests/Performance/workflow_benchmark.sh

ARTICLE_ID="benchmark-test-$(date +%s)"

# Queue article
echo "Queuing article..."
START=$(date +%s)

php scripts/wordpress_blog_processor.php --article-id=$ARTICLE_ID

END=$(date +%s)
DURATION=$((END - START))

echo "Total processing time: ${DURATION}s"

# Fetch execution log to get individual stage timings
curl "http://localhost/admin-api.php?action=wordpress_blog_get_execution_log&article_id=$ARTICLE_ID" \
  -H "Authorization: Bearer $TOKEN" | jq '.execution_log[] | "\(.stage): \(.execution_time_ms)ms"'
```

**Expected Output**:
```
queue: 5ms
validation: 150ms
structure: 12500ms (12.5s)
content: 95000ms (95s)
image: 25000ms (25s)
assets: 15000ms (15s)
publish: 10000ms (10s)
---
Total: 157655ms (157.7s = 2min 37s)
```

---

### API Endpoint Benchmarks

| Endpoint | Target | Measurement Method | Status |
|----------|--------|-------------------|--------|
| POST /create_configuration | <1s | Time API response | Pending |
| GET /get_configurations | <500ms | Time API response | Pending |
| GET /get_configuration | <300ms | Time API response | Pending |
| PUT /update_configuration | <800ms | Time API response | Pending |
| DELETE /delete_configuration | <600ms | Time API response | Pending |
| POST /queue_article | <200ms | Time API response | Pending |
| GET /get_queue | <1s | Time API response with 50 articles | Pending |
| GET /get_article | <300ms | Time API response | Pending |
| PUT /update_article_status | <250ms | Time API response | Pending |
| GET /get_execution_log | <1s | Time API response | Pending |
| GET /get_metrics | <2s | Time API response with 7-day data | Pending |
| GET /system_health | <500ms | Time API response | Pending |

**Testing Script**:
```bash
#!/bin/bash
# tests/Performance/api_benchmark.sh

TOKEN="your_test_token"
API_URL="http://localhost/admin-api.php"

# Function to measure endpoint
benchmark_endpoint() {
    local action=$1
    local method=$2
    local data=$3

    START=$(date +%s%3N) # milliseconds

    if [ "$method" = "GET" ]; then
        curl -s "${API_URL}?action=${action}" \
          -H "Authorization: Bearer $TOKEN" > /dev/null
    else
        curl -s -X POST "${API_URL}?action=${action}" \
          -H "Authorization: Bearer $TOKEN" \
          -H "Content-Type: application/json" \
          -d "$data" > /dev/null
    fi

    END=$(date +%s%3N)
    DURATION=$((END - START))

    echo "$action: ${DURATION}ms"
}

# Test each endpoint
benchmark_endpoint "wordpress_blog_get_configurations" "GET"
benchmark_endpoint "wordpress_blog_get_queue" "GET"
benchmark_endpoint "wordpress_blog_system_health" "GET"
benchmark_endpoint "wordpress_blog_get_metrics" "GET"

# Run 10 times and calculate average
```

---

## Load Testing

### Test Scenario 1: Concurrent Article Processing

**Objective**: Verify system can handle multiple articles processing simultaneously

**Setup**:
1. Queue 10 articles
2. Process 3 concurrently using separate PHP processes
3. Monitor database locks, memory usage, CPU

**Test Script**:
```bash
#!/bin/bash
# tests/Performance/concurrent_processing.sh

# Queue 10 articles
for i in {1..10}; do
    php scripts/queue_article.php --topic="Load Test Article $i" --config-id=1
done

# Start 3 concurrent processors
php scripts/wordpress_blog_processor.php --mode=single &
php scripts/wordpress_blog_processor.php --mode=single &
php scripts/wordpress_blog_processor.php --mode=single &

# Wait for all to complete
wait

echo "All processing complete"
```

**Metrics to Monitor**:
- [ ] Database lock waits
- [ ] Query execution times
- [ ] Memory usage per process
- [ ] CPU utilization
- [ ] Completion time for all 10 articles

**Expected Results**:
- [ ] All 10 articles process successfully
- [ ] No database deadlocks
- [ ] Memory usage < 256MB per process
- [ ] CPU usage distributed across cores
- [ ] Total time < 60 minutes

**Status**: ☐ Pass  ☐ Fail  ☐ Pending

**Results**:
- Articles completed: _______
- Database locks: _______
- Average memory: _______ MB
- Average CPU: _______%
- Total time: _______ minutes

---

### Test Scenario 2: API Load Testing

**Objective**: Test API endpoints under load

**Tool**: Apache Bench (ab) or k6

**Test Configuration**:
```bash
# Test GET /get_queue endpoint
# 1000 requests, 50 concurrent
ab -n 1000 -c 50 \
  -H "Authorization: Bearer $TOKEN" \
  "http://localhost/admin-api.php?action=wordpress_blog_get_queue"
```

**Metrics to Track**:
- Requests per second
- Mean response time
- 95th percentile response time
- Failed requests
- Connection errors

**Expected Results**:
- [ ] Requests per second > 50
- [ ] Mean response time < 500ms
- [ ] 95th percentile < 1s
- [ ] Failed requests < 1%
- [ ] No connection errors

**Status**: ☐ Pass  ☐ Fail  ☐ Pending

**Results**:
```
Concurrency Level:      50
Time taken for tests:   _______ seconds
Complete requests:      1000
Failed requests:        _______
Requests per second:    _______ [#/sec]
Time per request:       _______ [ms] (mean)
Time per request:       _______ [ms] (mean, across all concurrent requests)

Percentage of the requests served within a certain time (ms)
  50%  _______
  66%  _______
  75%  _______
  80%  _______
  90%  _______
  95%  _______
  98%  _______
  99%  _______
 100%  _______ (longest request)
```

---

### Test Scenario 3: Database Stress Test

**Objective**: Test database performance under heavy load

**Setup**:
1. Pre-populate database with 1000 configurations
2. Pre-populate with 5000 articles in queue
3. Run complex queries

**Test Script**:
```php
<?php
// tests/Performance/database_stress_test.php

// Populate test data
for ($i = 0; $i < 1000; $i++) {
    $configService->createConfiguration([...]);
}

for ($i = 0; $i < 5000; $i++) {
    $queueService->queueArticle(rand(1, 1000), "Test Article $i");
}

// Test complex query performance
$start = microtime(true);
$results = $db->query("
    SELECT
        c.config_name,
        COUNT(q.id) as total_articles,
        SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed,
        AVG(CASE WHEN q.status = 'completed' THEN
            TIMESTAMPDIFF(SECOND, q.processing_started_at, q.processing_completed_at)
            ELSE NULL END) as avg_processing_time
    FROM wp_blog_configurations c
    LEFT JOIN wp_blog_article_queue q ON c.id = q.config_id
    GROUP BY c.id
    ORDER BY total_articles DESC
    LIMIT 100
");
$end = microtime(true);

echo "Complex query time: " . (($end - $start) * 1000) . "ms\n";
?>
```

**Expected Results**:
- [ ] Query execution < 2s with 5000 articles
- [ ] No timeout errors
- [ ] Index usage confirmed (EXPLAIN query)
- [ ] Memory usage acceptable

**Status**: ☐ Pass  ☐ Fail  ☐ Pending

---

## Memory and Resource Testing

### Test Scenario 4: Memory Leak Detection

**Objective**: Ensure no memory leaks during extended processing

**Test Script**:
```bash
#!/bin/bash
# tests/Performance/memory_leak_test.sh

# Process 50 articles sequentially
for i in {1..50}; do
    echo "Processing article $i"

    # Get memory before
    MEMORY_BEFORE=$(ps aux | grep 'wordpress_blog_processor' | grep -v grep | awk '{print $6}')

    php scripts/wordpress_blog_processor.php --mode=single

    # Get memory after
    MEMORY_AFTER=$(ps aux | grep 'wordpress_blog_processor' | grep -v grep | awk '{print $6}')

    echo "Memory before: ${MEMORY_BEFORE}KB, after: ${MEMORY_AFTER}KB"

    sleep 5
done
```

**Expected Results**:
- [ ] Memory usage stays consistent (±10% variance)
- [ ] No continuous memory growth
- [ ] Peak memory < 512MB
- [ ] Memory released after processing

**Status**: ☐ Pass  ☐ Fail  ☐ Pending

---

## Optimization Recommendations

### Database Optimizations

**1. Add Indexes**
```sql
-- Add indexes for frequently queried fields
CREATE INDEX idx_article_status ON wp_blog_article_queue(status);
CREATE INDEX idx_article_config ON wp_blog_article_queue(config_id);
CREATE INDEX idx_article_created ON wp_blog_article_queue(created_at DESC);
CREATE INDEX idx_exec_log_article ON wp_blog_execution_log(article_id);
CREATE INDEX idx_exec_log_stage_status ON wp_blog_execution_log(stage, status);

-- Composite index for common filter combinations
CREATE INDEX idx_queue_status_config ON wp_blog_article_queue(status, config_id);
```

**2. Enable Query Cache** (MySQL):
```ini
# my.cnf
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M
```

**3. Optimize Connection Pooling**:
```php
// Use persistent connections
$db = new PDO('mysql:host=localhost;dbname=wp_blog', 'user', 'pass', [
    PDO::ATTR_PERSISTENT => true
]);
```

**4. For SQLite - Enable WAL Mode**:
```bash
sqlite3 db/database.db "PRAGMA journal_mode=WAL;"
sqlite3 db/database.db "PRAGMA synchronous=NORMAL;"
```

---

### PHP Optimizations

**1. Enable OPcache**:
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

**2. Increase Memory Limit**:
```ini
; php.ini
memory_limit = 512M
```

**3. Optimize PHP-FPM**:
```ini
; www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

---

### Application-Level Optimizations

**1. Implement Caching**:
```php
// Cache configurations in Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$cacheKey = "wp_blog_config_{$configId}";
$cached = $redis->get($cacheKey);

if ($cached) {
    return json_decode($cached, true);
}

$config = $this->loadConfiguration($configId);
$redis->setex($cacheKey, 3600, json_encode($config)); // 1 hour cache
```

**2. Batch Processing**:
```php
// Process multiple chapters in parallel
$promises = [];
foreach ($chapters as $chapter) {
    $promises[] = $this->generateChapterAsync($chapter);
}

$results = Promise::all($promises)->wait();
```

**3. Lazy Loading**:
```php
// Don't load execution log unless requested
public function getArticle($articleId, $includeLog = false) {
    $article = $this->db->select('wp_blog_article_queue', ['id' => $articleId]);

    if ($includeLog) {
        $article['execution_log'] = $this->executionLogger->getExecutionLog($articleId);
    }

    return $article;
}
```

---

### Network Optimizations

**1. Enable Gzip Compression**:
```apache
# .htaccess
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

**2. Browser Caching**:
```apache
# .htaccess
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
</IfModule>
```

**3. CDN for Static Assets**:
```html
<!-- Use CDN for libraries -->
<script src="https://cdn.jsdelivr.net/npm/[library]"></script>
```

---

## Performance Monitoring

### Real-Time Monitoring

**1. Application Performance Monitoring (APM)**:
```php
// Integrate New Relic or Datadog
// Add to bootstrap
if (extension_loaded('newrelic')) {
    newrelic_set_appname('WordPress Blog Automation');
}
```

**2. Query Performance Monitoring**:
```php
// Log slow queries
$db->on('query', function($query, $time) {
    if ($time > 1000) { // > 1 second
        error_log("Slow query ({$time}ms): {$query}");
    }
});
```

**3. Error Rate Monitoring**:
```php
// Track error rates
$errorRate = $db->query("
    SELECT
        COUNT(CASE WHEN status = 'failed' THEN 1 END) * 100.0 / COUNT(*) as error_rate
    FROM wp_blog_article_queue
    WHERE processing_completed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
")->fetch()['error_rate'];

if ($errorRate > 10) {
    // Send alert
}
```

---

## Benchmark Results Template

### Database Operations Results

| Operation | Target | Result | Pass/Fail | Notes |
|-----------|--------|--------|-----------|-------|
| INSERT configuration | <50ms | ___ms | ☐ | _____ |
| SELECT configuration | <20ms | ___ms | ☐ | _____ |
| UPDATE configuration | <30ms | ___ms | ☐ | _____ |
| DELETE configuration | <40ms | ___ms | ☐ | _____ |
| INSERT article | <50ms | ___ms | ☐ | _____ |
| SELECT queue | <100ms | ___ms | ☐ | _____ |

### Content Generation Results

| Phase | Target | Result | Pass/Fail | Notes |
|-------|--------|--------|-----------|-------|
| Structure | <30s | ___s | ☐ | _____ |
| Content (5 ch) | <120s | ___s | ☐ | _____ |
| Image | <30s | ___s | ☐ | _____ |
| Assets | <20s | ___s | ☐ | _____ |
| Publish | <15s | ___s | ☐ | _____ |
| **Total** | **<5m** | **___s** | **☐** | **_____** |

### API Endpoint Results

| Endpoint | Target | Result | Pass/Fail | Notes |
|----------|--------|--------|-----------|-------|
| create_configuration | <1s | ___ms | ☐ | _____ |
| get_configurations | <500ms | ___ms | ☐ | _____ |
| queue_article | <200ms | ___ms | ☐ | _____ |
| get_queue | <1s | ___ms | ☐ | _____ |
| get_metrics | <2s | ___ms | ☐ | _____ |
| system_health | <500ms | ___ms | ☐ | _____ |

---

## Performance Test Sign-Off

**Tester**: _______________
**Date**: _______________

**Overall Performance Assessment**:
☐ Exceeds expectations
☐ Meets expectations
☐ Below expectations (requires optimization)

**Critical Performance Issues**:
1. _____________________________________________
2. _____________________________________________

**Optimization Priority**:
1. _____________________________________________
2. _____________________________________________

**Approved for Production**: ☐ Yes  ☐ No  ☐ Conditional

---

**Document Version**: 1.0
**Last Updated**: November 21, 2025
**Next Review**: After optimization implementation
