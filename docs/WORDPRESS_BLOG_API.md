# WordPress Blog Automation - API Documentation

## Table of Contents

1. [Authentication](#authentication)
2. [Configuration Endpoints](#configuration-endpoints)
3. [Queue Management Endpoints](#queue-management-endpoints)
4. [Monitoring & Metrics Endpoints](#monitoring--metrics-endpoints)
5. [Error Codes](#error-codes)
6. [Rate Limiting](#rate-limiting)
7. [Examples](#examples)

---

## Authentication

All API endpoints require authentication using a Bearer token.

### Authentication Header

```http
Authorization: Bearer YOUR_API_TOKEN
```

### Getting an API Token

API tokens are managed through the admin panel or generated via the authentication system.

### Example Authentication

```bash
curl -X GET https://your-domain.com/admin-api.php?action=wordpress_blog_get_configurations \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

---

## Configuration Endpoints

### 1. Create Configuration

Create a new blog configuration with WordPress and API credentials.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_create_configuration`

**Authentication:** Required

**Request Body:**
```json
{
  "config_name": "My Blog Configuration",
  "wordpress_site_url": "https://myblog.com",
  "wordpress_username": "admin",
  "wordpress_api_key": "xxxx xxxx xxxx xxxx xxxx xxxx",
  "openai_api_key": "sk-xxxxxxxxxxxxxxxx",
  "openai_model": "gpt-4",
  "replicate_api_key": "r8_xxxxxxxxxxxxxxxx",
  "target_word_count": 2000,
  "max_internal_links": 5,
  "google_drive_folder_id": "folder-id-here"
}
```

**Required Fields:**
- `config_name` (string): Unique name for the configuration
- `wordpress_site_url` (string): Full WordPress site URL with protocol
- `wordpress_api_key` (string): WordPress application password
- `openai_api_key` (string): OpenAI API key starting with `sk-`

**Optional Fields:**
- `wordpress_username` (string): WordPress username (default: derived from API key)
- `openai_model` (string): OpenAI model to use (default: `gpt-4`)
- `replicate_api_key` (string): Replicate API token for image generation
- `target_word_count` (integer): Target article length (default: 2000)
- `max_internal_links` (integer): Maximum internal links per article (default: 5)
- `google_drive_folder_id` (string): Google Drive folder for asset storage

**Success Response (200 OK):**
```json
{
  "success": true,
  "config_id": 1,
  "message": "Configuration created successfully"
}
```

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "Validation failed",
  "details": {
    "errors": [
      "Configuration name is required",
      "Invalid WordPress site URL format"
    ],
    "warnings": [
      "Google Drive folder ID not provided - asset storage disabled"
    ]
  }
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_create_configuration \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_name": "Tech Blog",
    "wordpress_site_url": "https://techblog.com",
    "wordpress_username": "admin",
    "wordpress_api_key": "abcd efgh ijkl mnop qrst uvwx",
    "openai_api_key": "sk-1234567890abcdef",
    "target_word_count": 2500
  }'
```

---

### 2. Get All Configurations

Retrieve all blog configurations.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_configurations`

**Authentication:** Required

**Query Parameters:** None

**Success Response (200 OK):**
```json
{
  "success": true,
  "configurations": [
    {
      "id": 1,
      "config_name": "Tech Blog",
      "wordpress_site_url": "https://techblog.com",
      "wordpress_username": "admin",
      "wordpress_api_key": "abcd****uvwx",
      "openai_api_key": "sk-1****cdef",
      "openai_model": "gpt-4",
      "replicate_api_key": "r8_1****5678",
      "target_word_count": 2500,
      "max_internal_links": 5,
      "google_drive_folder_id": "folder-id",
      "created_at": "2025-11-01 10:30:00",
      "updated_at": "2025-11-15 14:20:00"
    }
  ]
}
```

**Note:** API keys are automatically masked in responses for security.

**cURL Example:**
```bash
curl -X GET https://your-domain.com/admin-api.php?action=wordpress_blog_get_configurations \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 3. Get Single Configuration

Retrieve a specific configuration by ID.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_configuration&config_id={id}`

**Authentication:** Required

**Query Parameters:**
- `config_id` (integer, required): Configuration ID

**Success Response (200 OK):**
```json
{
  "success": true,
  "configuration": {
    "id": 1,
    "config_name": "Tech Blog",
    "wordpress_site_url": "https://techblog.com",
    "target_word_count": 2500,
    ...
  }
}
```

**Error Response (404 Not Found):**
```json
{
  "success": false,
  "error": "Configuration not found"
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/admin-api.php?action=wordpress_blog_get_configuration&config_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 4. Update Configuration

Update an existing configuration.

**Endpoint:** `PUT /admin-api.php?action=wordpress_blog_update_configuration`

**Authentication:** Required

**Request Body:**
```json
{
  "config_id": 1,
  "config_name": "Updated Tech Blog",
  "target_word_count": 3000,
  "max_internal_links": 7
}
```

**Note:** Only include fields you want to update. Omitted fields remain unchanged.

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Configuration updated successfully"
}
```

**cURL Example:**
```bash
curl -X PUT https://your-domain.com/admin-api.php?action=wordpress_blog_update_configuration \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "target_word_count": 3000
  }'
```

---

### 5. Delete Configuration

Delete a configuration and all associated data.

**Endpoint:** `DELETE /admin-api.php?action=wordpress_blog_delete_configuration&config_id={id}`

**Authentication:** Required

**Query Parameters:**
- `config_id` (integer, required): Configuration ID

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Configuration deleted successfully"
}
```

**Warning:** This action is irreversible and will delete:
- Configuration record
- All internal links
- All queued articles for this configuration
- All execution logs

**cURL Example:**
```bash
curl -X DELETE "https://your-domain.com/admin-api.php?action=wordpress_blog_delete_configuration&config_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 6. Add Internal Link

Add an internal link to the repository for a configuration.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_add_internal_link`

**Authentication:** Required

**Request Body:**
```json
{
  "config_id": 1,
  "url": "https://myblog.com/previous-article",
  "anchor_text": "Related Article Title"
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "link_id": 42,
  "message": "Internal link added successfully"
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_add_internal_link \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "url": "https://myblog.com/ai-basics",
    "anchor_text": "Introduction to AI"
  }'
```

---

### 7. Get Internal Links

Retrieve all internal links for a configuration.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_internal_links&config_id={id}`

**Authentication:** Required

**Query Parameters:**
- `config_id` (integer, required): Configuration ID

**Success Response (200 OK):**
```json
{
  "success": true,
  "internal_links": [
    {
      "id": 1,
      "config_id": 1,
      "url": "https://myblog.com/ai-basics",
      "anchor_text": "Introduction to AI",
      "created_at": "2025-11-01 10:45:00"
    },
    {
      "id": 2,
      "config_id": 1,
      "url": "https://myblog.com/ml-guide",
      "anchor_text": "Machine Learning Guide",
      "created_at": "2025-11-02 09:15:00"
    }
  ]
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/admin-api.php?action=wordpress_blog_get_internal_links&config_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 8. Delete Internal Link

Remove an internal link from the repository.

**Endpoint:** `DELETE /admin-api.php?action=wordpress_blog_delete_internal_link&link_id={id}`

**Authentication:** Required

**Query Parameters:**
- `link_id` (integer, required): Internal link ID

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Internal link deleted successfully"
}
```

**cURL Example:**
```bash
curl -X DELETE "https://your-domain.com/admin-api.php?action=wordpress_blog_delete_internal_link&link_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Queue Management Endpoints

### 9. Queue Article

Add a new article to the processing queue.

**Endpoint:** `POST /admin-api.php?action=wordpress_blog_queue_article`

**Authentication:** Required

**Request Body:**
```json
{
  "config_id": 1,
  "topic": "The Future of Artificial Intelligence in Healthcare"
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "article_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "message": "Article queued successfully",
  "status": "pending"
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/admin-api.php?action=wordpress_blog_queue_article \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_id": 1,
    "topic": "Machine Learning Best Practices"
  }'
```

---

### 10. Get Queue

Retrieve articles from the queue with optional filtering.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_queue`

**Authentication:** Required

**Query Parameters:**
- `status` (string, optional): Filter by status (`pending`, `processing`, `completed`, `failed`)
- `config_id` (integer, optional): Filter by configuration ID
- `limit` (integer, optional): Number of results (default: 50, max: 100)
- `offset` (integer, optional): Pagination offset (default: 0)

**Success Response (200 OK):**
```json
{
  "success": true,
  "articles": [
    {
      "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "config_id": 1,
      "config_name": "Tech Blog",
      "topic": "Machine Learning Best Practices",
      "status": "processing",
      "wordpress_post_id": null,
      "processing_started_at": "2025-11-21 10:30:00",
      "processing_completed_at": null,
      "error_message": null,
      "retry_count": 0,
      "created_at": "2025-11-21 10:25:00",
      "updated_at": "2025-11-21 10:30:00"
    }
  ],
  "total": 15,
  "limit": 50,
  "offset": 0
}
```

**cURL Example:**
```bash
# Get all pending articles
curl -X GET "https://your-domain.com/admin-api.php?action=wordpress_blog_get_queue&status=pending" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get articles for specific configuration
curl -X GET "https://your-domain.com/admin-api.php?action=wordpress_blog_get_queue&config_id=1&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 11. Get Single Article

Retrieve details for a specific article.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_article&article_id={id}`

**Authentication:** Required

**Query Parameters:**
- `article_id` (string, required): Article ID (UUID)

**Success Response (200 OK):**
```json
{
  "success": true,
  "article": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "config_id": 1,
    "topic": "Machine Learning Best Practices",
    "status": "completed",
    "wordpress_post_id": 12345,
    "processing_started_at": "2025-11-21 10:30:00",
    "processing_completed_at": "2025-11-21 10:45:00",
    "error_message": null,
    "retry_count": 1,
    "content_json": "{\"chapters\": [...]}",
    "metadata_json": "{\"word_count\": 2100}",
    "created_at": "2025-11-21 10:25:00",
    "updated_at": "2025-11-21 10:45:00"
  }
}
```

**Error Response (404 Not Found):**
```json
{
  "success": false,
  "error": "Article not found"
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/admin-api.php?action=wordpress_blog_get_article&article_id=a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 12. Update Article Status

Update the processing status of an article.

**Endpoint:** `PUT /admin-api.php?action=wordpress_blog_update_article_status`

**Authentication:** Required

**Request Body:**
```json
{
  "article_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "status": "processing"
}
```

**Valid Status Values:**
- `pending`: Article is queued and waiting
- `processing`: Article is currently being processed
- `completed`: Article was successfully published
- `failed`: Article processing failed

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Article status updated successfully"
}
```

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "error": "Invalid status value",
  "valid_statuses": ["pending", "processing", "completed", "failed"]
}
```

**cURL Example:**
```bash
curl -X PUT https://your-domain.com/admin-api.php?action=wordpress_blog_update_article_status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "article_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "status": "completed"
  }'
```

---

### 13. Delete Article

Remove an article from the queue.

**Endpoint:** `DELETE /admin-api.php?action=wordpress_blog_delete_article&article_id={id}`

**Authentication:** Required

**Query Parameters:**
- `article_id` (string, required): Article ID (UUID)

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Article deleted successfully"
}
```

**Note:** This deletes the article from the queue and all associated execution logs. It does NOT delete the published WordPress post.

**cURL Example:**
```bash
curl -X DELETE "https://your-domain.com/admin-api.php?action=wordpress_blog_delete_article&article_id=a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Monitoring & Metrics Endpoints

### 14. Get Execution Log

Retrieve the execution log for an article showing all processing stages.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_execution_log&article_id={id}`

**Authentication:** Required

**Query Parameters:**
- `article_id` (string, required): Article ID (UUID)

**Success Response (200 OK):**
```json
{
  "success": true,
  "execution_log": [
    {
      "id": 1,
      "article_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "stage": "queue",
      "status": "completed",
      "message": "Article queued successfully",
      "error_details": null,
      "execution_time_ms": 5,
      "created_at": "2025-11-21 10:25:00"
    },
    {
      "id": 2,
      "article_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "stage": "structure",
      "status": "completed",
      "message": "Content structure built",
      "error_details": null,
      "execution_time_ms": 1250,
      "created_at": "2025-11-21 10:30:15"
    },
    {
      "id": 3,
      "article_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "stage": "content",
      "status": "completed",
      "message": "Chapter content generated",
      "error_details": null,
      "execution_time_ms": 8500,
      "created_at": "2025-11-21 10:35:30"
    }
  ],
  "total_execution_time_ms": 12500
}
```

**Execution Stages:**
- `queue`: Article added to queue
- `validation`: Configuration validated
- `structure`: Content structure created
- `content`: Chapter content generated
- `image`: Featured image generated
- `assets`: Assets organized (Google Drive)
- `publish`: Published to WordPress

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/admin-api.php?action=wordpress_blog_get_execution_log&article_id=a1b2c3d4-e5f6-7890-abcd-ef1234567890" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 15. Get Processing Metrics

Retrieve processing metrics and statistics.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_get_metrics`

**Authentication:** Required

**Query Parameters:**
- `days` (integer, optional): Number of days to analyze (default: 7, max: 90)

**Success Response (200 OK):**
```json
{
  "success": true,
  "metrics": {
    "overview": {
      "total_articles": 150,
      "completed": 142,
      "failed": 5,
      "processing": 2,
      "pending": 1,
      "success_rate": 94.67
    },
    "performance": {
      "avg_processing_time_minutes": 12.5,
      "total_processing_time_hours": 31.25,
      "fastest_article_minutes": 8.2,
      "slowest_article_minutes": 25.7
    },
    "costs": {
      "estimated_openai_cost_usd": 21.30,
      "estimated_replicate_cost_usd": 7.50,
      "total_estimated_cost_usd": 28.80,
      "avg_cost_per_article_usd": 0.19
    },
    "by_status": {
      "pending": 1,
      "processing": 2,
      "completed": 142,
      "failed": 5
    },
    "recent_activity": {
      "last_24_hours": 12,
      "last_7_days": 45,
      "last_30_days": 150
    }
  },
  "period": {
    "days": 7,
    "start_date": "2025-11-14",
    "end_date": "2025-11-21"
  }
}
```

**cURL Example:**
```bash
# Get metrics for last 30 days
curl -X GET "https://your-domain.com/admin-api.php?action=wordpress_blog_get_metrics&days=30" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### 16. Get System Health

Check system health and readiness.

**Endpoint:** `GET /admin-api.php?action=wordpress_blog_system_health`

**Authentication:** Required

**Success Response (200 OK):**
```json
{
  "success": true,
  "health": {
    "status": "healthy",
    "checks": {
      "database": {
        "status": "pass",
        "message": "Database connection successful"
      },
      "disk_space": {
        "status": "pass",
        "message": "85% free (42.5 GB available)"
      },
      "api_keys": {
        "status": "pass",
        "message": "All API keys validated"
      },
      "queue": {
        "status": "pass",
        "message": "12 articles in queue, 2 processing"
      },
      "stuck_articles": {
        "status": "pass",
        "message": "No stuck articles found"
      }
    },
    "timestamp": "2025-11-21 10:00:00"
  }
}
```

**Warning Response (200 OK with warnings):**
```json
{
  "success": true,
  "health": {
    "status": "degraded",
    "checks": {
      "database": { "status": "pass", "message": "Database connected" },
      "disk_space": {
        "status": "warning",
        "message": "15% free (7.5 GB available) - cleanup recommended"
      },
      "stuck_articles": {
        "status": "warning",
        "message": "2 articles stuck in processing > 2 hours"
      }
    }
  }
}
```

**Error Response (500 Internal Server Error):**
```json
{
  "success": false,
  "health": {
    "status": "unhealthy",
    "checks": {
      "database": {
        "status": "fail",
        "message": "Database connection failed"
      }
    }
  }
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/admin-api.php?action=wordpress_blog_system_health \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Error Codes

### HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request succeeded |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid request parameters or validation failed |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Resource already exists or state conflict |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error occurred |

### Application Error Codes

**Configuration Errors (1000-1099):**
- `1001`: Invalid configuration name
- `1002`: Invalid WordPress URL
- `1003`: Invalid API key format
- `1004`: Configuration not found
- `1005`: Configuration validation failed

**Queue Errors (2000-2099):**
- `2001`: Invalid article ID
- `2002`: Article not found
- `2003`: Invalid status transition
- `2004`: Article already processing

**Processing Errors (3000-3099):**
- `3001`: Content generation failed
- `3002`: Image generation failed
- `3003`: WordPress publish failed
- `3004`: Validation failed
- `3005`: Retry limit exceeded

**API Errors (4000-4099):**
- `4001`: OpenAI API error
- `4002`: Replicate API error
- `4003`: WordPress API error
- `4004`: Google Drive API error
- `4005`: Rate limit exceeded

### Error Response Format

```json
{
  "success": false,
  "error": "Human-readable error message",
  "error_code": 1003,
  "details": {
    "field": "openai_api_key",
    "reason": "API key must start with 'sk-'"
  }
}
```

---

## Rate Limiting

### Default Limits

- **General API Calls**: 100 requests per minute per token
- **Queue Operations**: 50 requests per minute per token
- **Metrics/Health Checks**: 20 requests per minute per token

### Rate Limit Headers

Responses include rate limit information:

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1637582400
```

### Rate Limit Exceeded Response (429)

```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "retry_after": 60,
  "limit": 100,
  "reset_at": "2025-11-21 10:15:00"
}
```

**Recommendation:** Implement exponential backoff when receiving 429 responses.

---

## Examples

### Complete Workflow Example

```bash
#!/bin/bash
TOKEN="YOUR_API_TOKEN"
API_URL="https://your-domain.com/admin-api.php"

# 1. Create configuration
CONFIG_RESPONSE=$(curl -s -X POST "${API_URL}?action=wordpress_blog_create_configuration" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "config_name": "My Tech Blog",
    "wordpress_site_url": "https://myblog.com",
    "wordpress_username": "admin",
    "wordpress_api_key": "xxxx xxxx xxxx xxxx xxxx xxxx",
    "openai_api_key": "sk-xxxxxxxxxxxxxxxx",
    "target_word_count": 2000
  }')

CONFIG_ID=$(echo $CONFIG_RESPONSE | jq -r '.config_id')
echo "Created configuration: $CONFIG_ID"

# 2. Add internal links
curl -s -X POST "${API_URL}?action=wordpress_blog_add_internal_link" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"config_id\": $CONFIG_ID,
    \"url\": \"https://myblog.com/article-1\",
    \"anchor_text\": \"Related Article\"
  }"

# 3. Queue articles
TOPICS=(
  "The Future of AI"
  "Machine Learning Basics"
  "Web Development Trends"
)

for TOPIC in "${TOPICS[@]}"; do
  ARTICLE_RESPONSE=$(curl -s -X POST "${API_URL}?action=wordpress_blog_queue_article" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"config_id\": $CONFIG_ID,
      \"topic\": \"$TOPIC\"
    }")

  ARTICLE_ID=$(echo $ARTICLE_RESPONSE | jq -r '.article_id')
  echo "Queued article: $ARTICLE_ID - $TOPIC"
done

# 4. Check queue status
curl -s -X GET "${API_URL}?action=wordpress_blog_get_queue&status=pending" \
  -H "Authorization: Bearer $TOKEN" | jq '.articles[] | {id, topic, status}'

# 5. Monitor metrics
curl -s -X GET "${API_URL}?action=wordpress_blog_get_metrics&days=1" \
  -H "Authorization: Bearer $TOKEN" | jq '.metrics.overview'
```

### Batch Operations Example

```bash
#!/bin/bash
TOKEN="YOUR_API_TOKEN"
API_URL="https://your-domain.com/admin-api.php"

# Bulk queue articles from CSV
# Format: topic
while IFS=, read -r topic; do
  curl -s -X POST "${API_URL}?action=wordpress_blog_queue_article" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
      \"config_id\": 1,
      \"topic\": \"$topic\"
    }" | jq '{article_id, topic, status}'

  sleep 1  # Rate limiting
done < articles.csv
```

### Monitoring Script Example

```bash
#!/bin/bash
TOKEN="YOUR_API_TOKEN"
API_URL="https://your-domain.com/admin-api.php"

# Check system health
HEALTH=$(curl -s -X GET "${API_URL}?action=wordpress_blog_system_health" \
  -H "Authorization: Bearer $TOKEN")

STATUS=$(echo $HEALTH | jq -r '.health.status')

if [ "$STATUS" != "healthy" ]; then
  echo "ALERT: System status is $STATUS"
  echo $HEALTH | jq '.health.checks'

  # Send alert email
  echo $HEALTH | mail -s "WordPress Blog System Alert" admin@example.com
fi

# Check for stuck articles
STUCK=$(curl -s -X GET "${API_URL}?action=wordpress_blog_get_queue" \
  -H "Authorization: Bearer $TOKEN" | \
  jq '[.articles[] | select(.status == "processing" and (.processing_started_at | fromdateiso8601) < (now - 7200))]')

if [ "$(echo $STUCK | jq 'length')" -gt 0 ]; then
  echo "ALERT: Found stuck articles"
  echo $STUCK | jq '.[] | {id, topic, processing_started_at}'
fi

# Check metrics
METRICS=$(curl -s -X GET "${API_URL}?action=wordpress_blog_get_metrics&days=1" \
  -H "Authorization: Bearer $TOKEN")

SUCCESS_RATE=$(echo $METRICS | jq -r '.metrics.overview.success_rate')

if (( $(echo "$SUCCESS_RATE < 90" | bc -l) )); then
  echo "ALERT: Success rate is $SUCCESS_RATE% (below 90%)"
fi
```

---

## Postman Collection

A Postman collection with all endpoints is available for download:

**Download:** [wordpress-blog-api.postman_collection.json](#)

**Import Instructions:**
1. Open Postman
2. Click "Import" button
3. Select the JSON file
4. Set `{{token}}` variable in environment
5. Set `{{base_url}}` variable to your API URL

---

## SDK Support

### PHP SDK Example

```php
<?php
class WordPressBlogAPIClient {
    private $baseUrl;
    private $token;

    public function __construct($baseUrl, $token) {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    private function request($action, $method = 'GET', $data = null) {
        $url = "{$this->baseUrl}/admin-api.php?action={$action}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->token}",
            "Content-Type: application/json"
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function createConfiguration($data) {
        return $this->request('wordpress_blog_create_configuration', 'POST', $data);
    }

    public function queueArticle($configId, $topic) {
        return $this->request('wordpress_blog_queue_article', 'POST', [
            'config_id' => $configId,
            'topic' => $topic
        ]);
    }

    public function getMetrics($days = 7) {
        return $this->request("wordpress_blog_get_metrics&days={$days}");
    }
}

// Usage
$client = new WordPressBlogAPIClient('https://your-domain.com', 'YOUR_TOKEN');
$result = $client->queueArticle(1, 'AI in Healthcare');
```

---

## Support

**API Documentation Issues:**
- GitHub: https://github.com/your-repo/issues
- Email: api-support@yourdomain.com

**Rate Limit Increases:**
- Contact: enterprise@yourdomain.com

**API Status:**
- Status Page: https://status.yourdomain.com

---

**API Version:** 1.0
**Last Updated:** November 21, 2025
**Next Review:** February 21, 2026
