# WordPress Blog Automation - Detailed Implementation Plan

**Project**: Complete WordPress Blog Automation Pro Agent Implementation
**Status**: Foundation Complete (15%) - Core Implementation Required (85%)
**Estimated Timeline**: 4-5 weeks
**Last Updated**: 2025-11-20

---

## Implementation Overview

This plan outlines the complete implementation of the WordPress Blog Automation feature as specified in `WORDPRESS_BLOG_AUTOMATION_PRO_AGENTE_SPEC.md`. The agent framework and architecture are complete; this plan focuses on building the missing service layer, database schema, API endpoints, UI components, and testing infrastructure.

---

## Phase 1: Database Foundation (2-3 days)

### Task 1.1: Create Database Migration File
**File**: `db/migrations/048_add_wordpress_blog_tables.sql`
**Priority**: CRITICAL - Blocking all other tasks
**Estimated Time**: 3-4 hours

**Requirements**:
- Create migration file following existing pattern (see `047_add_specialized_agent_support.sql`)
- Include rollback/down migration
- Add indexes for performance
- Include foreign key constraints

**Tables to Create**:

1. **blog_configurations** (Primary configuration storage)
   ```sql
   - configuration_id (UUID, PRIMARY KEY)
   - config_name (VARCHAR(255), NOT NULL)
   - website_url (VARCHAR(500), NOT NULL)
   - number_of_chapters (INT, DEFAULT 5)
   - max_word_count (INT, DEFAULT 3000)
   - introduction_length (INT, DEFAULT 300)
   - conclusion_length (INT, DEFAULT 200)
   - cta_message (TEXT)
   - cta_url (VARCHAR(500))
   - company_offering (TEXT)
   - wordpress_api_url (VARCHAR(500), NOT NULL)
   - wordpress_api_key_encrypted (TEXT, NOT NULL)
   - openai_api_key_encrypted (TEXT, NOT NULL)
   - default_publish_status (ENUM: 'draft', 'publish', 'pending', DEFAULT 'draft')
   - google_drive_folder_id (VARCHAR(255))
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
   - updated_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP ON UPDATE)
   - INDEX idx_config_name, idx_created_at
   ```

2. **blog_articles_queue** (Article processing queue)
   ```sql
   - article_id (UUID, PRIMARY KEY)
   - configuration_id (UUID, NOT NULL, FOREIGN KEY)
   - status (ENUM: 'queued', 'processing', 'completed', 'failed', 'published')
   - seed_keyword (VARCHAR(255), NOT NULL)
   - target_audience (VARCHAR(255))
   - writing_style (VARCHAR(100))
   - publication_date (TIMESTAMP)
   - scheduled_date (TIMESTAMP)
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
   - updated_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP ON UPDATE)
   - processing_started_at (TIMESTAMP NULL)
   - processing_completed_at (TIMESTAMP NULL)
   - execution_log_url (TEXT)
   - wordpress_post_id (BIGINT NULL)
   - wordpress_post_url (VARCHAR(500))
   - error_message (TEXT)
   - retry_count (INT, DEFAULT 0)
   - INDEX idx_status, idx_configuration_id, idx_scheduled_date
   - FOREIGN KEY (configuration_id) REFERENCES blog_configurations(configuration_id)
   ```

3. **blog_article_categories** (Many-to-many categories)
   ```sql
   - id (BIGINT AUTO_INCREMENT, PRIMARY KEY)
   - article_id (UUID, NOT NULL)
   - category_id (BIGINT)
   - category_name (VARCHAR(255), NOT NULL)
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
   - INDEX idx_article_id, idx_category_name
   - FOREIGN KEY (article_id) REFERENCES blog_articles_queue(article_id) ON DELETE CASCADE
   ```

4. **blog_article_tags** (Many-to-many tags)
   ```sql
   - id (BIGINT AUTO_INCREMENT, PRIMARY KEY)
   - article_id (UUID, NOT NULL)
   - tag_id (BIGINT)
   - tag_name (VARCHAR(255), NOT NULL)
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
   - INDEX idx_article_id, idx_tag_name
   - FOREIGN KEY (article_id) REFERENCES blog_articles_queue(article_id) ON DELETE CASCADE
   ```

5. **blog_internal_links** (Internal link repository)
   ```sql
   - link_id (UUID, PRIMARY KEY)
   - configuration_id (UUID, NOT NULL)
   - url (VARCHAR(500), NOT NULL)
   - anchor_text (VARCHAR(255), NOT NULL)
   - relevance_keywords (JSON)
   - priority (INT, DEFAULT 5)
   - is_active (BOOLEAN, DEFAULT TRUE)
   - created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
   - updated_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP ON UPDATE)
   - INDEX idx_configuration_id, idx_is_active
   - FOREIGN KEY (configuration_id) REFERENCES blog_configurations(configuration_id) ON DELETE CASCADE
   ```

**Acceptance Criteria**:
- [ ] Migration runs successfully on clean database
- [ ] All tables created with correct schema
- [ ] Indexes created for performance
- [ ] Foreign keys properly constrained
- [ ] Rollback script works correctly
- [ ] No SQL syntax errors

---

### Task 1.2: Create Database Schema Validation Script
**File**: `db/validate_blog_schema.php`
**Priority**: HIGH
**Estimated Time**: 1-2 hours
**Dependencies**: Task 1.1

**Requirements**:
- Verify all 5 tables exist
- Check column definitions match spec
- Validate indexes are present
- Test foreign key constraints
- Output validation report

**Acceptance Criteria**:
- [ ] Script detects missing tables
- [ ] Script validates column types
- [ ] Script checks index existence
- [ ] Returns success/failure exit code
- [ ] Provides detailed error messages

---

### Task 1.3: Run Migration and Validate Schema
**Priority**: CRITICAL
**Estimated Time**: 30 minutes
**Dependencies**: Tasks 1.1, 1.2

**Steps**:
1. Run migration: `php db/migrate.php` or equivalent
2. Run validation: `php db/validate_blog_schema.php`
3. Verify tables in database client
4. Test INSERT/SELECT on each table
5. Document any issues

**Acceptance Criteria**:
- [ ] Migration completes without errors
- [ ] Validation script passes all checks
- [ ] Can manually query all tables
- [ ] Foreign keys prevent invalid data

---

## Phase 2: Core Service Classes - Part A: Configuration & Queue (2-3 days)

### Task 2.1: Implement WordPressBlogConfigurationService
**File**: `includes/WordPressBlog/Services/WordPressBlogConfigurationService.php`
**Priority**: CRITICAL
**Estimated Time**: 4-6 hours
**Dependencies**: Phase 1 complete

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressBlogConfigurationService {
    private $db;
    private $secretsManager;

    // CRUD Operations
    public function createConfiguration(array $config): string
    public function getConfiguration(string $configId): ?array
    public function updateConfiguration(string $configId, array $updates): bool
    public function deleteConfiguration(string $configId): bool
    public function listConfigurations(int $limit = 50, int $offset = 0): array

    // Validation
    public function validateConfiguration(array $config): array
    public function isConfigurationComplete(string $configId): bool

    // Credential Management
    private function encryptCredentials(array $credentials): array
    private function decryptCredentials(array $encryptedData): array

    // Internal Links
    public function addInternalLink(string $configId, array $linkData): string
    public function getInternalLinks(string $configId, bool $activeOnly = true): array
    public function updateInternalLink(string $linkId, array $updates): bool
    public function deleteInternalLink(string $linkId): bool
    public function findRelevantLinks(string $configId, array $keywords, int $limit = 5): array
}
```

**Key Features**:
- Use existing `SecretsManager` for encrypting WordPress API keys and OpenAI API keys
- Validate required fields: `config_name`, `website_url`, `wordpress_api_url`, `wordpress_api_key`, `openai_api_key`
- Validate number ranges: `number_of_chapters` (1-20), `max_word_count` (500-10000)
- Validate URLs using PHP filter_var
- Support JSON encoding for relevance_keywords
- Implement soft delete (set is_active = false for links)
- Use prepared statements for all queries

**Acceptance Criteria**:
- [ ] All CRUD methods implemented
- [ ] Credentials encrypted at rest
- [ ] Validation catches invalid data
- [ ] Returns proper error messages
- [ ] Uses database transactions where needed
- [ ] Handles SQL errors gracefully

---

### Task 2.2: Implement WordPressBlogQueueService
**File**: `includes/WordPressBlog/Services/WordPressBlogQueueService.php`
**Priority**: CRITICAL
**Estimated Time**: 4-5 hours
**Dependencies**: Task 2.1

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressBlogQueueService {
    private $db;

    // Queue Management
    public function queueArticle(array $articleData): string
    public function getNextQueuedArticle(): ?array
    public function getArticle(string $articleId): ?array
    public function cancelArticle(string $articleId): bool
    public function deleteArticle(string $articleId): bool

    // Status Management
    public function updateStatus(string $articleId, string $newStatus, ?string $errorMessage = null): bool
    public function markAsProcessing(string $articleId): bool
    public function markAsCompleted(string $articleId, int $wpPostId, string $wpPostUrl): bool
    public function markAsFailed(string $articleId, string $errorMessage): bool
    public function markAsPublished(string $articleId): bool

    // Query Methods
    public function listQueuedArticles(int $limit = 50, int $offset = 0): array
    public function listByStatus(string $status, int $limit = 50): array
    public function listByConfiguration(string $configId, int $limit = 50): array
    public function getQueueStats(): array

    // Category & Tag Management
    public function addCategories(string $articleId, array $categories): bool
    public function addTags(string $articleId, array $tags): bool
    public function getCategories(string $articleId): array
    public function getTags(string $articleId): array

    // Lock Management (prevent duplicate processing)
    private function acquireLock(string $articleId): bool
    private function releaseLock(string $articleId): bool
}
```

**Key Features**:
- Use `SELECT ... FOR UPDATE` for database-level row locking
- Implement status transitions with validation (queued → processing → completed/failed)
- Track retry count and increment on failures
- Store execution timestamps (processing_started_at, processing_completed_at)
- FIFO queue ordering by `scheduled_date` then `created_at`
- Support bulk category/tag insertion
- Calculate queue statistics (total queued, processing, completed, failed)

**Status Transition Rules**:
- `queued` → `processing` (when picked by orchestrator)
- `processing` → `completed` (article generated, not yet published)
- `processing` → `failed` (error occurred, retry_count < 3)
- `completed` → `published` (successfully posted to WordPress)
- `failed` → `queued` (manual retry)

**Acceptance Criteria**:
- [ ] Implements row-level locking
- [ ] Status transitions validated
- [ ] Categories and tags properly associated
- [ ] Queue statistics accurate
- [ ] Handles concurrent access safely
- [ ] Returns enriched article data with configuration

---

### Task 2.3: Create Unit Tests for Configuration Service
**File**: `tests/WordPressBlog/ConfigurationServiceTest.php`
**Priority**: HIGH
**Estimated Time**: 2-3 hours
**Dependencies**: Task 2.1

**Test Coverage**:
- Test configuration creation with valid data
- Test configuration creation with invalid data (validation errors)
- Test credential encryption/decryption
- Test configuration retrieval
- Test configuration update
- Test configuration deletion
- Test internal link CRUD operations
- Test findRelevantLinks with keyword matching
- Test edge cases (null values, empty strings, SQL injection attempts)

**Acceptance Criteria**:
- [ ] Minimum 80% code coverage
- [ ] All public methods tested
- [ ] Edge cases covered
- [ ] Uses test database or mocks

---

### Task 2.4: Create Unit Tests for Queue Service
**File**: `tests/WordPressBlog/QueueServiceTest.php`
**Priority**: HIGH
**Estimated Time**: 2-3 hours
**Dependencies**: Task 2.2

**Test Coverage**:
- Test article queuing
- Test getNextQueuedArticle with FIFO ordering
- Test status transitions (all valid paths)
- Test invalid status transitions (should fail)
- Test concurrent access (locking behavior)
- Test category and tag operations
- Test queue statistics calculation
- Test retry logic

**Acceptance Criteria**:
- [ ] Minimum 80% code coverage
- [ ] Status transitions validated
- [ ] Locking behavior tested
- [ ] Queue ordering verified

---

## Phase 3: Core Service Classes - Part B: Content Generation (3-4 days)

### Task 3.1: Implement WordPressContentStructureBuilder
**File**: `includes/WordPressBlog/Services/WordPressContentStructureBuilder.php`
**Priority**: CRITICAL
**Estimated Time**: 6-8 hours
**Dependencies**: Task 2.1 (needs configuration)

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressContentStructureBuilder {
    private $openAIClient;
    private $configService;

    // Main Structure Generation
    public function generateArticleStructure(array $config, array $articleData): array

    // Component Generators (called by generateArticleStructure)
    private function generateMetadata(string $seedKeyword, string $targetAudience): array
    private function generateChapterOutline(array $config, array $metadata): array
    private function generateIntroduction(array $config, array $metadata): string
    private function generateConclusion(array $config, array $metadata, array $chapters): string
    private function generateSEOSnippet(array $metadata): array

    // Helper Methods
    private function createChapterPrompts(array $chapters, array $config): array
    private function createImagePrompts(array $chapters): array
    private function validateStructure(array $structure): bool
}
```

**Return Structure** (from generateArticleStructure):
```php
[
    'metadata' => [
        'title' => 'Generated SEO Title',
        'subtitle' => 'Compelling subtitle',
        'slug' => 'seo-friendly-slug',
        'meta_description' => 'SEO meta description (150-160 chars)',
        'seo_snippet' => [
            'title' => 'Google search title',
            'url' => 'https://example.com/slug',
            'description' => 'Search result description'
        ]
    ],
    'introduction' => 'Markdown content for intro',
    'chapters' => [
        [
            'chapter_number' => 1,
            'title' => 'Chapter 1 Title',
            'content_prompt' => 'Detailed prompt for GPT-4 to write this chapter',
            'target_word_count' => 500,
            'keywords' => ['keyword1', 'keyword2'],
            'image_prompt' => 'DALL-E prompt for chapter image',
            'internal_links_context' => 'Suggested internal link anchors'
        ],
        // ... more chapters
    ],
    'conclusion' => 'Markdown content for conclusion',
    'cta' => [
        'message' => 'CTA text from config',
        'url' => 'CTA URL from config'
    ],
    'structure_metadata' => [
        'total_chapters' => 5,
        'estimated_word_count' => 3000,
        'target_audience' => 'specified audience',
        'writing_style' => 'specified style'
    ]
]
```

**OpenAI Prompts Required**:

1. **Metadata Generation Prompt**:
   ```
   Generate an SEO-optimized article structure for the following:
   - Seed Keyword: {seed_keyword}
   - Target Audience: {target_audience}
   - Writing Style: {writing_style}

   Return JSON with:
   {
     "title": "Compelling, SEO-friendly title (60 chars max)",
     "subtitle": "Engaging subtitle",
     "slug": "url-friendly-slug",
     "meta_description": "SEO meta description (150-160 chars)"
   }
   ```

2. **Chapter Outline Prompt**:
   ```
   Create a {number_of_chapters}-chapter outline for an article titled "{title}".
   Target audience: {target_audience}
   Target word count: {max_word_count} words total

   Return JSON array of chapters:
   [
     {
       "chapter_number": 1,
       "title": "Chapter title",
       "keywords": ["keyword1", "keyword2"],
       "target_word_count": 500
     }
   ]
   ```

3. **Introduction Prompt**:
   ```
   Write an engaging {introduction_length}-word introduction for:
   Title: {title}
   Subtitle: {subtitle}
   Target Audience: {target_audience}

   The introduction should:
   - Hook the reader immediately
   - Preview the article's value
   - Include the seed keyword: {seed_keyword}
   - Use {writing_style} writing style

   Return in markdown format.
   ```

4. **Conclusion Prompt**:
   ```
   Write a {conclusion_length}-word conclusion for an article about "{title}".

   Chapter summaries:
   {chapter_summaries}

   The conclusion should:
   - Summarize key takeaways
   - Reinforce the main message
   - Lead naturally to the CTA
   - Use {writing_style} writing style

   Return in markdown format.
   ```

**Acceptance Criteria**:
- [ ] Generates complete article structure
- [ ] Uses OpenAI GPT-4 for intelligent content planning
- [ ] All prompts properly formatted
- [ ] Returns valid JSON structure
- [ ] Handles OpenAI API errors
- [ ] Validates output structure
- [ ] Word count targets properly distributed across chapters

---

### Task 3.2: Implement WordPressChapterContentWriter
**File**: `includes/WordPressBlog/Services/WordPressChapterContentWriter.php`
**Priority**: CRITICAL
**Estimated Time**: 5-6 hours
**Dependencies**: Task 3.1

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressChapterContentWriter {
    private $openAIClient;

    // Main Methods
    public function writeChapter(array $chapterSpec, array $context): array
    public function writeMultipleChapters(array $chapters, array $context, bool $parallel = false): array

    // Context Building
    private function buildChapterContext(array $chapterSpec, array $adjacentChapters, array $internalLinks): string
    private function selectRelevantInternalLinks(array $allLinks, array $chapterKeywords, int $maxLinks = 3): array

    // Content Processing
    private function validateWordCount(string $content, int $targetCount, float $tolerance = 0.05): bool
    private function injectInternalLinks(string $content, array $links): string
    private function formatMarkdown(string $content): string

    // Error Handling
    private function retryWithSimplifiedPrompt(array $chapterSpec, int $attemptNumber): array
}
```

**Chapter Writing Prompt Template**:
```
Write chapter {chapter_number} for an article about "{article_title}".

**Chapter Title**: {chapter_title}
**Target Word Count**: {target_word_count} words (±5% acceptable)
**Target Keywords**: {keywords_list}
**Writing Style**: {writing_style}
**Target Audience**: {target_audience}

**Context from Previous Chapter**:
{previous_chapter_summary}

**Context for Next Chapter**:
{next_chapter_preview}

**Internal Links to Include**:
{internal_links_list}

**Requirements**:
- Write in markdown format
- Use proper heading hierarchy (## for chapter title, ### for sections)
- Naturally incorporate target keywords
- Include 2-3 internal links with provided anchor text
- Maintain coherence with adjacent chapters
- Use {writing_style} tone throughout
- Stay within target word count (±5%)

Return only the markdown content without meta-commentary.
```

**Return Structure** (from writeChapter):
```php
[
    'chapter_number' => 1,
    'title' => 'Chapter Title',
    'content' => 'Full markdown content...',
    'word_count' => 523,
    'internal_links_used' => [
        ['url' => '...', 'anchor_text' => '...'],
    ],
    'keywords_incorporated' => ['keyword1', 'keyword2'],
    'generation_metadata' => [
        'model' => 'gpt-4',
        'tokens_used' => 1500,
        'generation_time' => 3.2,
        'retry_count' => 0
    ]
]
```

**Key Features**:
- Context-aware generation (considers adjacent chapters)
- Intelligent internal link placement (3-5 per chapter, contextually relevant)
- Word count validation with ±5% tolerance
- Retry logic with simplified prompts if first attempt fails
- Support for parallel generation (optional, for faster processing)
- Proper markdown formatting (headers, lists, bold, italic)

**Acceptance Criteria**:
- [ ] Generates coherent chapter content
- [ ] Word count within ±5% of target
- [ ] Internal links naturally integrated
- [ ] Maintains consistent writing style
- [ ] Context from adjacent chapters considered
- [ ] Handles API errors with retry logic
- [ ] Returns structured metadata

---

### Task 3.3: Implement WordPressImageGenerator
**File**: `includes/WordPressBlog/Services/WordPressImageGenerator.php`
**Priority**: HIGH
**Estimated Time**: 4-5 hours
**Dependencies**: None (independent service)

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressImageGenerator {
    private $openAIClient;
    private $storageService;

    // Main Generation Methods
    public function generateFeaturedImage(array $articleMetadata): array
    public function generateChapterImages(array $chapters): array
    public function generateSingleImage(string $prompt, array $options = []): array

    // Prompt Building
    private function buildFeaturedImagePrompt(array $metadata): string
    private function buildChapterImagePrompt(array $chapter, int $chapterNumber): string
    private function enhancePromptForConsistency(string $basePrompt, array $styleGuide): string

    // Image Processing
    private function downloadImage(string $imageUrl): string
    private function validateImage(string $filePath): bool
    private function optimizeImage(string $filePath): string

    // Storage
    private function saveToTempStorage(string $imageData, string $filename): string
    private function generateImageMetadata(array $generationData): array

    // Error Handling
    private function retryGeneration(string $prompt, int $maxRetries = 3): ?array
}
```

**DALL-E 3 Generation Parameters**:
- Model: `dall-e-3`
- Size: `1792x1024` (landscape, optimized for blog headers)
- Quality: `hd`
- Style: `natural` (photorealistic) or `vivid` (more artistic) based on config

**Featured Image Prompt Template**:
```
Create a professional, eye-catching featured image for a blog article.

Article Title: {title}
Article Topic: {seed_keyword}
Target Audience: {target_audience}

The image should:
- Be visually compelling and relevant to "{seed_keyword}"
- Use a professional, modern aesthetic
- Avoid text overlays (text will be added later)
- Be suitable for a business/professional blog
- Use colors: {brand_colors if provided}

Style: {visual_style from config, default: 'professional photorealistic'}
```

**Chapter Image Prompt Template**:
```
Create an illustration for Chapter {chapter_number}: "{chapter_title}"

Context: This chapter discusses {chapter_keywords}
Style: Match the professional, modern aesthetic of the article
Audience: {target_audience}

The image should:
- Visually represent the chapter's main concept
- Be simple and clear
- Use a consistent style across all chapter images
- Avoid text overlays
```

**Return Structure**:
```php
[
    'image_type' => 'featured' | 'chapter',
    'chapter_number' => 1, // only for chapter images
    'image_url' => 'https://oaidalleapiprodscus...',
    'local_path' => '/tmp/image_12345.png',
    'file_size' => 245678,
    'dimensions' => ['width' => 1792, 'height' => 1024],
    'prompt_used' => 'Full DALL-E prompt',
    'revised_prompt' => 'DALL-E revised prompt',
    'generation_metadata' => [
        'model' => 'dall-e-3',
        'quality' => 'hd',
        'generation_time' => 12.5,
        'retry_count' => 0
    ]
]
```

**Acceptance Criteria**:
- [ ] Generates high-quality images via DALL-E 3
- [ ] Featured image is 1792x1024px
- [ ] Chapter images maintain visual consistency
- [ ] Images downloaded and saved locally
- [ ] Metadata properly tracked
- [ ] Retry logic handles API failures
- [ ] Validates image file integrity

---

### Task 3.4: Implement WordPressAssetOrganizer
**File**: `includes/WordPressBlog/Services/WordPressAssetOrganizer.php`
**Priority**: MEDIUM
**Estimated Time**: 4-5 hours
**Dependencies**: Task 3.3

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressAssetOrganizer {
    private $googleDriveClient;
    private $config;

    // Folder Management
    public function createArticleFolder(string $articleId, array $metadata): string
    public function getFolderStructure(string $articleId): array

    // Asset Upload
    public function uploadAssets(string $folderId, array $assets): array
    public function uploadImage(string $folderId, string $imagePath, array $metadata): array
    public function uploadDocument(string $folderId, string $docPath, string $mimeType): array

    // Manifest Generation
    public function generateAssetManifest(string $articleId, array $uploadedAssets): array
    public function saveManifest(string $folderId, array $manifest): string

    // URL Management
    public function makePublic(string $fileId): string
    public function getPublicUrl(string $fileId): string

    // Cleanup
    public function cleanupLocalFiles(array $filePaths): void

    // Error Handling
    private function handleQuotaExceeded(): void
    private function retryUpload(string $filePath, int $maxRetries = 3): ?array
}
```

**Folder Structure** (in Google Drive):
```
Blog Articles/
└── {article_slug}_{article_id}/
    ├── images/
    │   ├── featured_image.png
    │   ├── chapter_1.png
    │   ├── chapter_2.png
    │   └── ...
    ├── content/
    │   ├── article_full.md
    │   └── article_full.html
    └── manifest.json
```

**Manifest Structure** (manifest.json):
```json
{
  "article_id": "uuid",
  "article_title": "Title",
  "created_at": "2025-11-20T10:30:00Z",
  "assets": {
    "featured_image": {
      "file_id": "google_drive_file_id",
      "public_url": "https://drive.google.com/...",
      "file_name": "featured_image.png",
      "file_size": 245678,
      "uploaded_at": "2025-11-20T10:32:00Z"
    },
    "chapter_images": [
      {
        "chapter_number": 1,
        "file_id": "...",
        "public_url": "...",
        "file_name": "chapter_1.png",
        "file_size": 198234,
        "uploaded_at": "2025-11-20T10:33:00Z"
      }
    ],
    "content": {
      "markdown": {
        "file_id": "...",
        "public_url": "...",
        "file_name": "article_full.md"
      },
      "html": {
        "file_id": "...",
        "public_url": "...",
        "file_name": "article_full.html"
      }
    }
  },
  "statistics": {
    "total_files": 8,
    "total_size_bytes": 1847293,
    "upload_duration_seconds": 45.3
  }
}
```

**Google Drive API Integration**:
- Use Google Drive API v3
- Authenticate with service account (credentials from config)
- Create folders with `mimeType: 'application/vnd.google-apps.folder'`
- Upload files with proper MIME types
- Set permissions to `anyone with link can view` for public URLs

**Acceptance Criteria**:
- [ ] Creates organized folder structure
- [ ] Uploads all assets successfully
- [ ] Generates proper public URLs
- [ ] Creates complete manifest.json
- [ ] Handles quota errors gracefully
- [ ] Cleans up local temporary files
- [ ] Retry logic for failed uploads

---

### Task 3.5: Implement WordPressPublisher
**File**: `includes/WordPressBlog/Services/WordPressPublisher.php`
**Priority**: CRITICAL
**Estimated Time**: 5-6 hours
**Dependencies**: Tasks 3.1, 3.2, 3.3, 3.4

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressPublisher {
    private $httpClient;
    private $markdownParser;

    // Main Publishing
    public function publishArticle(array $articleContent, array $config): array

    // Content Assembly
    public function assembleFullContent(array $structure, array $chapters, array $images): string
    private function convertMarkdownToHTML(string $markdown): string
    private function injectImages(string $html, array $imageMapping): string
    private function injectCTA(string $html, array $ctaData): string

    // WordPress API
    private function createWordPressPost(array $postData, array $config): array
    private function uploadFeaturedImage(string $imageUrl, array $config): int
    private function assignCategories(int $postId, array $categories, array $config): bool
    private function assignTags(int $postId, array $tags, array $config): bool

    // Validation
    private function validatePostCreation(array $response): bool
    private function verifyPostLive(int $postId, array $config): bool

    // Error Handling
    private function handleWordPressError(array $errorResponse): void
    private function retryWithBackoff(callable $operation, int $maxRetries = 3): mixed
}
```

**WordPress REST API Integration**:

**Authentication**:
- Use Application Password authentication
- Header: `Authorization: Basic {base64(username:application_password)}`

**Create Post Endpoint**:
```
POST {wordpress_api_url}/wp-json/wp/v2/posts
```

**Post Object**:
```json
{
  "title": "Article Title",
  "content": "<html>Full HTML content</html>",
  "excerpt": "Meta description",
  "slug": "article-slug",
  "status": "draft" | "publish" | "pending",
  "categories": [1, 5, 12],
  "tags": [3, 7, 15],
  "featured_media": 123,
  "meta": {
    "meta_description": "SEO meta description"
  }
}
```

**Featured Image Upload**:
```
POST {wordpress_api_url}/wp-json/wp/v2/media
Content-Type: multipart/form-data

file: <image binary data>
title: "Featured Image Title"
alt_text: "Alt text for SEO"
```

**HTML Assembly**:
```html
<article class="blog-post">
  <!-- Introduction -->
  <div class="intro">
    {introduction_html}
  </div>

  <!-- Chapters -->
  <div class="chapter" id="chapter-1">
    <h2>{chapter_title}</h2>
    <img src="{chapter_image_url}" alt="{chapter_title}" class="chapter-image" />
    {chapter_content_html}
  </div>

  <!-- More chapters... -->

  <!-- Conclusion -->
  <div class="conclusion">
    {conclusion_html}
  </div>

  <!-- CTA -->
  <div class="cta-section">
    <p>{cta_message}</p>
    <a href="{cta_url}" class="cta-button">Learn More</a>
  </div>
</article>
```

**Return Structure**:
```php
[
    'success' => true,
    'wordpress_post_id' => 12345,
    'wordpress_post_url' => 'https://example.com/article-slug',
    'wordpress_edit_url' => 'https://example.com/wp-admin/post.php?post=12345&action=edit',
    'status' => 'draft' | 'publish',
    'featured_image_id' => 67890,
    'categories_assigned' => [1, 5, 12],
    'tags_assigned' => [3, 7, 15],
    'publishing_metadata' => [
        'api_calls_made' => 4,
        'total_time_seconds' => 8.7,
        'retry_count' => 0
    ]
]
```

**Acceptance Criteria**:
- [ ] Converts markdown to semantic HTML
- [ ] Properly assembles full article content
- [ ] Injects images with correct URLs
- [ ] Creates WordPress post successfully
- [ ] Uploads and assigns featured image
- [ ] Assigns categories and tags
- [ ] Returns WordPress post ID and URL
- [ ] Handles API rate limiting (429 errors)
- [ ] Retry logic with exponential backoff
- [ ] Validates post is accessible

---

### Task 3.6: Implement WordPressBlogExecutionLogger
**File**: `includes/WordPressBlog/Services/WordPressBlogExecutionLogger.php`
**Priority**: MEDIUM
**Estimated Time**: 3-4 hours
**Dependencies**: None (independent service)

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressBlogExecutionLogger {
    private $storageService;
    private $currentLog = [];

    // Session Management
    public function startLogging(string $articleId): void
    public function endLogging(): string

    // Phase Logging
    public function logPhaseStart(string $phase, array $context = []): void
    public function logPhaseComplete(string $phase, array $result = []): void
    public function logPhaseError(string $phase, string $error, array $context = []): void

    // API Call Logging
    public function logAPICall(string $service, string $endpoint, array $request, array $response, float $duration): void
    public function logAPIError(string $service, string $endpoint, array $error): void

    // General Logging
    public function logInfo(string $message, array $context = []): void
    public function logWarning(string $message, array $context = []): void
    public function logError(string $message, array $context = []): void

    // Metrics
    public function recordMetric(string $metricName, $value): void
    public function getMetrics(): array

    // Output
    public function generateAuditTrail(): array
    public function saveLog(string $destination): string
    private function formatLogForDisplay(): string
}
```

**Log Structure**:
```json
{
  "article_id": "uuid",
  "execution_id": "unique_execution_id",
  "started_at": "2025-11-20T10:00:00Z",
  "completed_at": "2025-11-20T10:15:23Z",
  "total_duration_seconds": 923.5,
  "status": "completed" | "failed",
  "phases": [
    {
      "phase": "retrieve_configuration",
      "started_at": "2025-11-20T10:00:00Z",
      "completed_at": "2025-11-20T10:00:02Z",
      "duration_seconds": 2.1,
      "status": "success",
      "result": {
        "configuration_id": "uuid",
        "config_name": "My Blog Config"
      }
    },
    {
      "phase": "generate_structure",
      "started_at": "2025-11-20T10:00:02Z",
      "completed_at": "2025-11-20T10:00:15Z",
      "duration_seconds": 13.2,
      "status": "success",
      "api_calls": [
        {
          "service": "openai",
          "endpoint": "/v1/chat/completions",
          "model": "gpt-4",
          "tokens_used": 1250,
          "duration_seconds": 3.5,
          "cost_usd": 0.0375
        }
      ]
    }
  ],
  "api_calls_summary": {
    "openai": {
      "total_calls": 12,
      "total_tokens": 25000,
      "total_cost_usd": 0.75,
      "total_duration_seconds": 45.2
    },
    "wordpress": {
      "total_calls": 4,
      "total_duration_seconds": 8.1
    },
    "google_drive": {
      "total_calls": 8,
      "total_duration_seconds": 23.7
    }
  },
  "metrics": {
    "chapters_generated": 5,
    "images_generated": 6,
    "word_count": 3245,
    "internal_links_used": 15,
    "retry_count": 0
  },
  "errors": [],
  "warnings": [
    {
      "timestamp": "2025-11-20T10:05:30Z",
      "message": "Chapter 3 word count slightly over target (5.2% variance)"
    }
  ]
}
```

**Acceptance Criteria**:
- [ ] Logs all phases with timestamps
- [ ] Tracks API calls and costs
- [ ] Records errors and warnings
- [ ] Calculates execution metrics
- [ ] Generates human-readable audit trail
- [ ] Saves log to accessible location (file or database)
- [ ] Returns log URL for reference

---

### Task 3.7: Create Unit Tests for Content Generation Services
**File**: `tests/WordPressBlog/ContentGenerationServicesTest.php`
**Priority**: HIGH
**Estimated Time**: 4-5 hours
**Dependencies**: Tasks 3.1-3.6

**Test Coverage**:
- WordPressContentStructureBuilder:
  - Test structure generation with valid config
  - Test metadata generation
  - Test chapter outline generation
  - Test introduction/conclusion generation
  - Test OpenAI API error handling
- WordPressChapterContentWriter:
  - Test single chapter generation
  - Test multi-chapter generation (parallel)
  - Test word count validation
  - Test internal link injection
  - Test retry logic
- WordPressImageGenerator:
  - Test featured image generation
  - Test chapter image generation
  - Test image download and validation
  - Test retry on failure
- WordPressAssetOrganizer:
  - Test folder creation
  - Test asset upload
  - Test manifest generation
  - Test public URL generation
- WordPressPublisher:
  - Test markdown to HTML conversion
  - Test content assembly
  - Test WordPress post creation
  - Test featured image upload
  - Test category/tag assignment
- WordPressBlogExecutionLogger:
  - Test phase logging
  - Test API call tracking
  - Test metric recording
  - Test audit trail generation

**Acceptance Criteria**:
- [ ] Minimum 75% code coverage for all services
- [ ] Uses mocks for external APIs (OpenAI, WordPress, Google Drive)
- [ ] Tests error handling paths
- [ ] Tests edge cases

---

## Phase 4: Orchestration & Workflow (2-3 days)

### Task 4.1: Implement WordPressBlogGeneratorService
**File**: `includes/WordPressBlog/Services/WordPressBlogGeneratorService.php`
**Priority**: CRITICAL
**Estimated Time**: 6-8 hours
**Dependencies**: Phase 3 complete

**Class Structure**:
```php
namespace WordPressBlog\Services;

class WordPressBlogGeneratorService {
    private $configService;
    private $queueService;
    private $structureBuilder;
    private $chapterWriter;
    private $imageGenerator;
    private $assetOrganizer;
    private $publisher;
    private $executionLogger;

    // Main Orchestration
    public function generateAndPublishArticle(string $articleId): array

    // Phase Methods (called by generateAndPublishArticle)
    private function phase1_RetrieveConfiguration(string $articleId): array
    private function phase2_GenerateStructure(array $config, array $articleData): array
    private function phase3_GenerateContent(array $structure, array $config): array
    private function phase4_GenerateAssets(array $structure, array $chapters): array
    private function phase5_OrganizeAssets(array $assets, array $metadata): array
    private function phase6_PublishArticle(array $fullContent, array $config): array

    // Helper Methods
    private function validatePhaseOutput(string $phase, $output): void
    private function handlePhaseError(string $phase, \Exception $e): void
    private function calculateProgress(string $currentPhase): int
}
```

**6-Phase Processing Pipeline**:

**Phase 1: Retrieve & Validate Configuration**
- Fetch article from queue (with lock)
- Load configuration
- Decrypt API credentials
- Validate configuration completeness
- Mark article as `processing`

**Phase 2: Generate Article Structure**
- Call `ContentStructureBuilder->generateArticleStructure()`
- Validate structure output
- Log structure generation

**Phase 3: Generate Content (Parallel)**
- Call `ChapterWriter->writeMultipleChapters()` (parallel generation)
- Generate introduction
- Generate conclusion
- Validate word counts
- Log content generation

**Phase 4: Generate Assets (Parallel)**
- Call `ImageGenerator->generateFeaturedImage()`
- Call `ImageGenerator->generateChapterImages()` (parallel generation)
- Download all images
- Validate image files

**Phase 5: Organize Assets**
- Call `AssetOrganizer->createArticleFolder()`
- Upload all assets to Google Drive
- Generate manifest
- Get public URLs

**Phase 6: Publish to WordPress**
- Assemble full content with `Publisher->assembleFullContent()`
- Convert markdown to HTML
- Call `Publisher->publishArticle()`
- Update queue status to `completed`
- Store WordPress post ID and URL

**Error Handling Strategy**:
- Each phase wrapped in try-catch
- Phase failures logged with execution logger
- Article status updated to `failed` with error message
- Retry count incremented
- If retry_count < 3, article remains in queue for retry
- If retry_count >= 3, article marked as permanently failed

**Return Structure**:
```php
[
    'success' => true,
    'article_id' => 'uuid',
    'wordpress_post_id' => 12345,
    'wordpress_post_url' => 'https://example.com/article',
    'execution_log_url' => 'https://drive.google.com/.../execution_log.json',
    'assets_manifest_url' => 'https://drive.google.com/.../manifest.json',
    'statistics' => [
        'total_duration_seconds' => 923.5,
        'word_count' => 3245,
        'chapters_generated' => 5,
        'images_generated' => 6,
        'api_costs_usd' => 0.85
    ],
    'phases_completed' => [
        'retrieve_configuration' => true,
        'generate_structure' => true,
        'generate_content' => true,
        'generate_assets' => true,
        'organize_assets' => true,
        'publish_article' => true
    ]
]
```

**Acceptance Criteria**:
- [ ] All 6 phases implemented
- [ ] Services properly orchestrated
- [ ] Error handling at each phase
- [ ] Progress tracking accurate
- [ ] Database transactions used appropriately
- [ ] Execution logged comprehensively
- [ ] Returns complete result structure

---

### Task 4.2: Implement WordPressBlogWorkflowOrchestrator
**File**: `includes/WordPressBlog/Orchestration/WordPressBlogWorkflowOrchestrator.php`
**Priority**: CRITICAL
**Estimated Time**: 5-6 hours
**Dependencies**: Task 4.1

**Class Structure**:
```php
namespace WordPressBlog\Orchestration;

class WordPressBlogWorkflowOrchestrator {
    private $queueService;
    private $generatorService;
    private $logger;

    // Main Orchestration
    public function processNextQueuedArticle(): ?array
    public function processQueue(int $maxArticles = 10): array

    // Queue Polling
    private function getNextArticle(): ?array
    private function shouldProcessArticle(array $article): bool

    // Workflow Management
    private function executeWorkflow(string $articleId): array
    private function handleWorkflowSuccess(string $articleId, array $result): void
    private function handleWorkflowFailure(string $articleId, \Exception $e): void

    // Retry Logic
    private function shouldRetry(array $article): bool
    private function calculateBackoffDelay(int $retryCount): int
    private function requeueForRetry(string $articleId, int $delaySeconds): void

    // Health Checks
    public function checkSystemHealth(): array
    private function testAPIConnectivity(): array
}
```

**Processing Logic**:

```php
public function processNextQueuedArticle(): ?array {
    // 1. Get next queued article (with database lock)
    $article = $this->queueService->getNextQueuedArticle();

    if (!$article) {
        return null; // Queue empty
    }

    // 2. Validate article can be processed
    if (!$this->shouldProcessArticle($article)) {
        return null;
    }

    // 3. Mark as processing
    $this->queueService->markAsProcessing($article['article_id']);

    // 4. Execute workflow
    try {
        $result = $this->generatorService->generateAndPublishArticle($article['article_id']);

        // 5. Handle success
        $this->handleWorkflowSuccess($article['article_id'], $result);

        return $result;

    } catch (\Exception $e) {
        // 6. Handle failure
        $this->handleWorkflowFailure($article['article_id'], $e);

        return [
            'success' => false,
            'article_id' => $article['article_id'],
            'error' => $e->getMessage()
        ];
    }
}
```

**Retry Strategy**:
- Retry Count 1: Immediate retry (0 seconds delay)
- Retry Count 2: 5 minutes delay (300 seconds)
- Retry Count 3: 15 minutes delay (900 seconds)
- Retry Count 4+: Mark as permanently failed

**System Health Check**:
```php
public function checkSystemHealth(): array {
    return [
        'queue_status' => [
            'total_queued' => $this->queueService->countByStatus('queued'),
            'total_processing' => $this->queueService->countByStatus('processing'),
            'total_completed' => $this->queueService->countByStatus('completed'),
            'total_failed' => $this->queueService->countByStatus('failed')
        ],
        'api_connectivity' => [
            'openai' => $this->testOpenAIConnection(),
            'wordpress' => $this->testWordPressConnection(),
            'google_drive' => $this->testGoogleDriveConnection()
        ],
        'system_status' => 'healthy' | 'degraded' | 'down'
    ];
}
```

**Acceptance Criteria**:
- [ ] Processes queue with FIFO ordering
- [ ] Implements database locking
- [ ] Retry logic with exponential backoff
- [ ] Handles concurrent execution safely
- [ ] Logs all workflow executions
- [ ] System health check functional
- [ ] Can process multiple articles sequentially

---

### Task 4.3: Create WordPress Blog Processor Script
**File**: `scripts/wordpress_blog_processor.php`
**Priority**: CRITICAL
**Estimated Time**: 2-3 hours
**Dependencies**: Task 4.2

**Script Structure**:
```php
<?php
/**
 * WordPress Blog Queue Processor
 *
 * This script processes queued blog articles and can be run:
 * - Via cron: */5 * * * * php /path/to/scripts/wordpress_blog_processor.php
 * - Manually: php scripts/wordpress_blog_processor.php
 * - With arguments: php scripts/wordpress_blog_processor.php --max=5 --verbose
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/autoload.php';

use WordPressBlog\Orchestration\WordPressBlogWorkflowOrchestrator;

// Parse command-line arguments
$options = getopt('', ['max:', 'verbose', 'health-check', 'article-id:']);

$maxArticles = isset($options['max']) ? (int)$options['max'] : 1;
$verbose = isset($options['verbose']);
$healthCheck = isset($options['health-check']);
$specificArticleId = $options['article-id'] ?? null;

// Initialize orchestrator
$orchestrator = new WordPressBlogWorkflowOrchestrator(
    $queueService,
    $generatorService,
    $logger
);

// Health check mode
if ($healthCheck) {
    $health = $orchestrator->checkSystemHealth();
    echo json_encode($health, JSON_PRETTY_PRINT) . "\n";
    exit($health['system_status'] === 'healthy' ? 0 : 1);
}

// Process specific article
if ($specificArticleId) {
    echo "Processing specific article: {$specificArticleId}\n";
    $result = $orchestrator->executeWorkflow($specificArticleId);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit($result['success'] ? 0 : 1);
}

// Process queue
echo "Processing up to {$maxArticles} queued articles...\n";

$results = $orchestrator->processQueue($maxArticles);

foreach ($results as $result) {
    if ($result['success']) {
        echo "[SUCCESS] Article {$result['article_id']} published to WordPress\n";
        if ($verbose) {
            echo "  WordPress Post ID: {$result['wordpress_post_id']}\n";
            echo "  URL: {$result['wordpress_post_url']}\n";
            echo "  Duration: {$result['statistics']['total_duration_seconds']}s\n";
        }
    } else {
        echo "[FAILED] Article {$result['article_id']}: {$result['error']}\n";
    }
}

$successCount = count(array_filter($results, fn($r) => $r['success']));
$failCount = count($results) - $successCount;

echo "\nSummary: {$successCount} succeeded, {$failCount} failed\n";

exit($failCount > 0 ? 1 : 0);
```

**Cron Configuration** (recommended):
```bash
# Process queue every 5 minutes
*/5 * * * * php /path/to/scripts/wordpress_blog_processor.php --max=1 >> /var/log/wordpress_blog_processor.log 2>&1

# Health check every hour
0 * * * * php /path/to/scripts/wordpress_blog_processor.php --health-check >> /var/log/wordpress_blog_health.log 2>&1
```

**Acceptance Criteria**:
- [ ] Can be run from command line
- [ ] Supports command-line arguments
- [ ] Processes queue automatically
- [ ] Logs output appropriately
- [ ] Returns proper exit codes
- [ ] Can process specific article by ID
- [ ] Health check mode functional

---

### Task 4.4: Create Unit Tests for Orchestration
**File**: `tests/WordPressBlog/OrchestrationTest.php`
**Priority**: HIGH
**Estimated Time**: 3-4 hours
**Dependencies**: Tasks 4.1-4.3

**Test Coverage**:
- WordPressBlogGeneratorService:
  - Test full 6-phase pipeline
  - Test each phase independently
  - Test phase error handling
  - Test progress tracking
- WordPressBlogWorkflowOrchestrator:
  - Test queue processing
  - Test retry logic
  - Test backoff delays
  - Test concurrent execution prevention
  - Test system health check
- Processor Script:
  - Test command-line argument parsing
  - Test single article processing
  - Test batch processing
  - Test health check mode

**Acceptance Criteria**:
- [ ] Minimum 80% code coverage
- [ ] Tests full workflow end-to-end
- [ ] Tests error scenarios
- [ ] Uses test database

---

## Phase 5: API Endpoints (2-3 days)

### Task 5.1: Add Configuration Management Endpoints
**File**: `admin-api.php` (extend existing file)
**Priority**: HIGH
**Estimated Time**: 4-5 hours
**Dependencies**: Task 2.1

**Endpoints to Add**:

```php
// Configuration CRUD
POST   /api/wordpress-blog/configurations
GET    /api/wordpress-blog/configurations/{config_id}
PUT    /api/wordpress-blog/configurations/{config_id}
DELETE /api/wordpress-blog/configurations/{config_id}
GET    /api/wordpress-blog/configurations

// Internal Links Management
POST   /api/wordpress-blog/configurations/{config_id}/links
GET    /api/wordpress-blog/configurations/{config_id}/links
PUT    /api/wordpress-blog/links/{link_id}
DELETE /api/wordpress-blog/links/{link_id}
```

**Implementation Example**:

```php
// POST /api/wordpress-blog/configurations
Route::post('/wordpress-blog/configurations', function() {
    // Verify authentication
    $user = authenticateRequest();
    if (!$user || !$user->hasPermission('manage_blog_configurations')) {
        return jsonResponse(['error' => 'Unauthorized'], 403);
    }

    // Validate request
    $data = json_decode(file_get_contents('php://input'), true);
    $validation = validateConfigurationData($data);
    if (!$validation['valid']) {
        return jsonResponse(['error' => $validation['errors']], 400);
    }

    // Create configuration
    $configService = new WordPressBlogConfigurationService($db, $secretsManager);
    $configId = $configService->createConfiguration($data);

    // Return response
    return jsonResponse([
        'success' => true,
        'configuration_id' => $configId,
        'message' => 'Configuration created successfully'
    ], 201);
});

// GET /api/wordpress-blog/configurations/{config_id}
Route::get('/wordpress-blog/configurations/{config_id}', function($configId) {
    $user = authenticateRequest();
    if (!$user) {
        return jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $configService = new WordPressBlogConfigurationService($db, $secretsManager);
    $config = $configService->getConfiguration($configId);

    if (!$config) {
        return jsonResponse(['error' => 'Configuration not found'], 404);
    }

    // Don't return encrypted credentials
    unset($config['wordpress_api_key_encrypted']);
    unset($config['openai_api_key_encrypted']);

    return jsonResponse($config);
});
```

**Request Validation**:
- Validate required fields
- Validate data types
- Validate URL formats
- Validate number ranges
- Sanitize input data

**Response Format**:
```json
{
  "success": true,
  "configuration_id": "uuid",
  "message": "Operation successful"
}
```

**Error Response Format**:
```json
{
  "error": "Error message",
  "validation_errors": {
    "field_name": "Error description"
  }
}
```

**Acceptance Criteria**:
- [ ] All configuration endpoints implemented
- [ ] Authentication required
- [ ] Request validation functional
- [ ] Proper error responses
- [ ] API keys never exposed in responses
- [ ] Follows RESTful conventions

---

### Task 5.2: Add Article Queue Management Endpoints
**File**: `admin-api.php` (extend existing file)
**Priority**: HIGH
**Estimated Time**: 4-5 hours
**Dependencies**: Task 2.2

**Endpoints to Add**:

```php
// Article Queue Operations
POST   /api/wordpress-blog/articles/queue
GET    /api/wordpress-blog/articles/queue
GET    /api/wordpress-blog/articles/queue/{article_id}
PUT    /api/wordpress-blog/articles/queue/{article_id}/status
DELETE /api/wordpress-blog/articles/queue/{article_id}

// Categories & Tags
POST   /api/wordpress-blog/articles/{article_id}/categories
POST   /api/wordpress-blog/articles/{article_id}/tags
GET    /api/wordpress-blog/articles/{article_id}/categories
GET    /api/wordpress-blog/articles/{article_id}/tags
DELETE /api/wordpress-blog/articles/{article_id}/categories/{category_id}
DELETE /api/wordpress-blog/articles/{article_id}/tags/{tag_id}
```

**Implementation Examples**:

```php
// POST /api/wordpress-blog/articles/queue
Route::post('/wordpress-blog/articles/queue', function() {
    $user = authenticateRequest();
    if (!$user || !$user->hasPermission('queue_blog_articles')) {
        return jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['configuration_id', 'seed_keyword'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            return jsonResponse(['error' => "Missing required field: {$field}"], 400);
        }
    }

    // Queue article
    $queueService = new WordPressBlogQueueService($db);
    $articleId = $queueService->queueArticle($data);

    return jsonResponse([
        'success' => true,
        'article_id' => $articleId,
        'status' => 'queued',
        'message' => 'Article queued successfully'
    ], 201);
});

// GET /api/wordpress-blog/articles/queue
Route::get('/wordpress-blog/articles/queue', function() {
    $user = authenticateRequest();
    if (!$user) {
        return jsonResponse(['error' => 'Unauthorized'], 403);
    }

    // Parse query parameters
    $status = $_GET['status'] ?? null;
    $configId = $_GET['configuration_id'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $queueService = new WordPressBlogQueueService($db);

    if ($status) {
        $articles = $queueService->listByStatus($status, $limit, $offset);
    } elseif ($configId) {
        $articles = $queueService->listByConfiguration($configId, $limit, $offset);
    } else {
        $articles = $queueService->listQueuedArticles($limit, $offset);
    }

    return jsonResponse([
        'articles' => $articles,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total' => count($articles)
        ]
    ]);
});

// PUT /api/wordpress-blog/articles/queue/{article_id}/status
Route::put('/wordpress-blog/articles/queue/{article_id}/status', function($articleId) {
    $user = authenticateRequest();
    if (!$user || !$user->hasPermission('manage_blog_queue')) {
        return jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $newStatus = $data['status'] ?? null;

    if (!in_array($newStatus, ['queued', 'processing', 'completed', 'failed', 'published'])) {
        return jsonResponse(['error' => 'Invalid status'], 400);
    }

    $queueService = new WordPressBlogQueueService($db);
    $success = $queueService->updateStatus($articleId, $newStatus, $data['error_message'] ?? null);

    if (!$success) {
        return jsonResponse(['error' => 'Failed to update status'], 500);
    }

    return jsonResponse([
        'success' => true,
        'article_id' => $articleId,
        'status' => $newStatus
    ]);
});
```

**Acceptance Criteria**:
- [ ] All queue endpoints implemented
- [ ] Authentication and authorization enforced
- [ ] Query parameter filtering works
- [ ] Pagination implemented
- [ ] Status validation enforced
- [ ] Proper error handling

---

### Task 5.3: Add Monitoring & Execution Endpoints
**File**: `admin-api.php` (extend existing file)
**Priority**: MEDIUM
**Estimated Time**: 3-4 hours
**Dependencies**: Tasks 3.6, 4.2

**Endpoints to Add**:

```php
// Execution Monitoring
GET /api/wordpress-blog/executions/{article_id}/log
GET /api/wordpress-blog/executions/status/{article_id}
GET /api/wordpress-blog/metrics/processing
GET /api/wordpress-blog/system/health
```

**Implementation Examples**:

```php
// GET /api/wordpress-blog/executions/{article_id}/log
Route::get('/wordpress-blog/executions/{article_id}/log', function($articleId) {
    $user = authenticateRequest();
    if (!$user) {
        return jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $queueService = new WordPressBlogQueueService($db);
    $article = $queueService->getArticle($articleId);

    if (!$article || !$article['execution_log_url']) {
        return jsonResponse(['error' => 'Execution log not found'], 404);
    }

    // Fetch log from storage (Google Drive or local)
    $logContent = fetchLogFromStorage($article['execution_log_url']);

    return jsonResponse(json_decode($logContent, true));
});

// GET /api/wordpress-blog/metrics/processing
Route::get('/wordpress-blog/metrics/processing', function() {
    $user = authenticateRequest();
    if (!$user) {
        return jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $queueService = new WordPressBlogQueueService($db);
    $stats = $queueService->getQueueStats();

    // Calculate additional metrics
    $metrics = [
        'queue_status' => $stats,
        'processing_times' => calculateAverageProcessingTimes($db),
        'success_rate' => calculateSuccessRate($db),
        'api_costs' => calculateAPICosts($db),
        'last_24_hours' => getRecentActivity($db, 24)
    ];

    return jsonResponse($metrics);
});

// GET /api/wordpress-blog/system/health
Route::get('/wordpress-blog/system/health', function() {
    $orchestrator = new WordPressBlogWorkflowOrchestrator(
        $queueService,
        $generatorService,
        $logger
    );

    $health = $orchestrator->checkSystemHealth();

    return jsonResponse($health);
});
```

**Metrics to Calculate**:
- Total articles queued/processing/completed/failed
- Average processing time
- Success rate (completed / total processed)
- Total API costs (OpenAI, Google Drive)
- Articles processed in last 24 hours
- Current processing backlog

**Acceptance Criteria**:
- [ ] Execution log retrieval works
- [ ] Metrics calculations accurate
- [ ] System health check functional
- [ ] Performance acceptable (<1s response)
- [ ] Proper caching where applicable

---

### Task 5.4: Create API Endpoint Tests
**File**: `tests/API/WordPressBlogAPITest.php`
**Priority**: HIGH
**Estimated Time**: 4-5 hours
**Dependencies**: Tasks 5.1-5.3

**Test Coverage**:
- Configuration Endpoints:
  - Test CREATE with valid data
  - Test CREATE with invalid data (validation)
  - Test GET single configuration
  - Test GET all configurations
  - Test UPDATE configuration
  - Test DELETE configuration
  - Test authentication required
- Queue Endpoints:
  - Test queuing article
  - Test listing queue
  - Test filtering by status
  - Test filtering by configuration
  - Test status updates
  - Test category/tag management
- Monitoring Endpoints:
  - Test execution log retrieval
  - Test metrics calculation
  - Test health check

**Acceptance Criteria**:
- [ ] All endpoints tested
- [ ] Authentication tested
- [ ] Validation tested
- [ ] Error responses tested
- [ ] Uses test database

---

## Phase 6: Admin UI Components (3-4 days)

### Task 6.1: Create Blog Configuration Management UI
**File**: `public/admin/wordpress-blog-config.js`
**Priority**: MEDIUM
**Estimated Time**: 6-8 hours
**Dependencies**: Task 5.1

**UI Components**:

1. **Configuration List View**
   - Table of all configurations
   - Columns: Name, Website URL, Status, Created Date, Actions
   - Search/filter functionality
   - Create New button

2. **Configuration Form** (Create/Edit)
   ```html
   <form id="blog-config-form">
     <div class="form-section">
       <h3>Basic Information</h3>
       <input name="config_name" required placeholder="Configuration Name" />
       <input name="website_url" required type="url" placeholder="https://example.com" />
     </div>

     <div class="form-section">
       <h3>Content Settings</h3>
       <input name="number_of_chapters" type="number" min="1" max="20" value="5" />
       <input name="max_word_count" type="number" min="500" max="10000" value="3000" />
       <input name="introduction_length" type="number" value="300" />
       <input name="conclusion_length" type="number" value="200" />
     </div>

     <div class="form-section">
       <h3>API Credentials</h3>
       <input name="wordpress_api_url" required type="url" placeholder="https://example.com" />
       <input name="wordpress_api_key" required type="password" placeholder="WordPress API Key" />
       <input name="openai_api_key" required type="password" placeholder="OpenAI API Key" />
     </div>

     <div class="form-section">
       <h3>CTA Settings</h3>
       <textarea name="cta_message" placeholder="Call to action message"></textarea>
       <input name="cta_url" type="url" placeholder="https://..." />
       <textarea name="company_offering" placeholder="Describe your company offering"></textarea>
     </div>

     <div class="form-section">
       <h3>Publishing Settings</h3>
       <select name="default_publish_status">
         <option value="draft">Draft</option>
         <option value="publish">Publish Immediately</option>
         <option value="pending">Pending Review</option>
       </select>
       <input name="google_drive_folder_id" placeholder="Google Drive Folder ID (optional)" />
     </div>

     <button type="submit">Save Configuration</button>
   </form>
   ```

3. **Validation & Error Display**
   - Real-time validation
   - Clear error messages
   - Credential masking

4. **API Integration**:
```javascript
async function saveConfiguration(formData) {
  try {
    const response = await fetch('/api/wordpress-blog/configurations', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getAuthToken()}`
      },
      body: JSON.stringify(formData)
    });

    if (!response.ok) {
      const error = await response.json();
      showErrors(error.validation_errors);
      return;
    }

    const result = await response.json();
    showSuccess(`Configuration "${formData.config_name}" created successfully`);
    redirectToConfigList();

  } catch (error) {
    showError('Failed to save configuration: ' + error.message);
  }
}
```

**Acceptance Criteria**:
- [ ] Can create new configurations
- [ ] Can edit existing configurations
- [ ] Can delete configurations (with confirmation)
- [ ] Form validation works
- [ ] API credentials masked
- [ ] Success/error messages displayed
- [ ] Responsive design

---

### Task 6.2: Create Internal Links Repository UI
**File**: `public/admin/wordpress-blog-links.js`
**Priority**: MEDIUM
**Estimated Time**: 3-4 hours
**Dependencies**: Task 5.1

**UI Components**:

1. **Links List View** (per configuration)
   - Table: URL, Anchor Text, Keywords, Priority, Status, Actions
   - Add New Link button
   - Bulk operations (enable/disable, delete)

2. **Link Form** (Add/Edit)
   ```html
   <form id="internal-link-form">
     <input name="url" required type="url" placeholder="https://example.com/article" />
     <input name="anchor_text" required placeholder="Anchor text for linking" />
     <input name="relevance_keywords" placeholder="keyword1, keyword2, keyword3" />
     <input name="priority" type="number" min="1" max="10" value="5" />
     <label>
       <input name="is_active" type="checkbox" checked />
       Active
     </label>
     <button type="submit">Save Link</button>
   </form>
   ```

3. **Keyword Tagging**
   - Tag-style input for keywords
   - Autocomplete from existing keywords

4. **Preview Panel**
   - Show how link will appear in content
   - Relevance score visualization

**Acceptance Criteria**:
- [ ] Can add internal links
- [ ] Can edit existing links
- [ ] Can delete links (with confirmation)
- [ ] Keyword tagging functional
- [ ] Link preview works
- [ ] Filtered by configuration

---

### Task 6.3: Create Article Queue Manager UI
**File**: `public/admin/wordpress-blog-queue.js`
**Priority**: HIGH
**Estimated Time**: 6-8 hours
**Dependencies**: Task 5.2

**UI Components**:

1. **Queue Dashboard**
   - Summary cards: Queued, Processing, Completed, Failed
   - Recent articles list
   - Quick actions

2. **Queue Table View**
   - Columns: Seed Keyword, Status, Configuration, Scheduled Date, Actions
   - Status badges (color-coded)
   - Filter by status/configuration
   - Sort by date
   - Pagination

3. **Queue New Article Form**
   ```html
   <form id="queue-article-form">
     <select name="configuration_id" required>
       <option value="">Select Configuration</option>
       <!-- Populated from API -->
     </select>

     <input name="seed_keyword" required placeholder="Primary keyword/topic" />
     <input name="target_audience" placeholder="e.g., Small business owners" />

     <select name="writing_style">
       <option value="professional">Professional</option>
       <option value="casual">Casual</option>
       <option value="technical">Technical</option>
       <option value="conversational">Conversational</option>
     </select>

     <input name="scheduled_date" type="datetime-local" />

     <div id="categories-section">
       <label>Categories</label>
       <input name="categories" placeholder="Category 1, Category 2" />
     </div>

     <div id="tags-section">
       <label>Tags</label>
       <input name="tags" placeholder="tag1, tag2, tag3" />
     </div>

     <button type="submit">Queue Article</button>
   </form>
   ```

4. **Article Detail View**
   - Full article information
   - Processing status timeline
   - Execution log viewer
   - WordPress post link (when published)
   - Retry button (for failed articles)
   - Cancel button (for queued/processing)

5. **Status Timeline Visualization**
   ```
   Queued → Processing → Completed → Published
     ✓         ✓           ✓           ⏳
   ```

**JavaScript Implementation**:
```javascript
// Fetch and display queue
async function loadQueue(status = null, configId = null) {
  let url = '/api/wordpress-blog/articles/queue?limit=50';
  if (status) url += `&status=${status}`;
  if (configId) url += `&configuration_id=${configId}`;

  const response = await fetch(url, {
    headers: { 'Authorization': `Bearer ${getAuthToken()}` }
  });

  const data = await response.json();
  renderQueueTable(data.articles);
}

// Queue new article
async function queueArticle(formData) {
  const response = await fetch('/api/wordpress-blog/articles/queue', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${getAuthToken()}`
    },
    body: JSON.stringify(formData)
  });

  if (!response.ok) {
    const error = await response.json();
    showError(error.error);
    return;
  }

  const result = await response.json();
  showSuccess(`Article "${formData.seed_keyword}" queued successfully`);
  loadQueue(); // Refresh table
}

// Real-time status updates (polling every 10 seconds for processing articles)
setInterval(async () => {
  const processingArticles = getProcessingArticles();
  if (processingArticles.length > 0) {
    for (const articleId of processingArticles) {
      await updateArticleStatus(articleId);
    }
  }
}, 10000);
```

**Acceptance Criteria**:
- [ ] Can queue new articles
- [ ] Queue table displays correctly
- [ ] Filtering and sorting work
- [ ] Article detail view functional
- [ ] Can retry failed articles
- [ ] Can cancel queued articles
- [ ] Status updates in real-time
- [ ] Responsive design

---

### Task 6.4: Create Processing Metrics Dashboard
**File**: `public/admin/wordpress-blog-metrics.js`
**Priority**: MEDIUM
**Estimated Time**: 4-5 hours
**Dependencies**: Task 5.3

**UI Components**:

1. **Overview Cards**
   - Total articles processed (today/week/month)
   - Success rate percentage
   - Average processing time
   - Total API costs

2. **Charts** (using Chart.js or similar)
   - Articles processed over time (line chart)
   - Status distribution (pie chart)
   - Processing times trend (bar chart)
   - API costs breakdown (stacked bar chart)

3. **Recent Activity Feed**
   - Latest published articles with links
   - Recent failures with error messages
   - Processing timeline

4. **System Health Indicator**
   - API connectivity status (OpenAI, WordPress, Google Drive)
   - Queue health (backlog size)
   - Overall system status badge

**Sample Implementation**:
```javascript
async function loadMetrics() {
  const response = await fetch('/api/wordpress-blog/metrics/processing', {
    headers: { 'Authorization': `Bearer ${getAuthToken()}` }
  });

  const metrics = await response.json();

  // Update overview cards
  document.getElementById('total-processed').textContent = metrics.queue_status.total_completed;
  document.getElementById('success-rate').textContent = `${metrics.success_rate}%`;
  document.getElementById('avg-time').textContent = `${metrics.processing_times.average}s`;
  document.getElementById('total-costs').textContent = `$${metrics.api_costs.total_usd}`;

  // Render charts
  renderProcessingChart(metrics.last_24_hours);
  renderStatusDistribution(metrics.queue_status);
  renderCostsBreakdown(metrics.api_costs);
}

// Auto-refresh every 30 seconds
setInterval(loadMetrics, 30000);
```

**Acceptance Criteria**:
- [ ] Overview cards display correct data
- [ ] Charts render correctly
- [ ] Recent activity feed updates
- [ ] System health indicator works
- [ ] Auto-refresh functional
- [ ] Performance acceptable (<2s load)

---

### Task 6.5: Integrate UI Components into Admin Dashboard
**File**: `public/admin/admin.js` (extend existing)
**Priority**: MEDIUM
**Estimated Time**: 2-3 hours
**Dependencies**: Tasks 6.1-6.4

**Integration Steps**:

1. Add navigation menu items:
   ```javascript
   {
     title: 'WordPress Blog',
     icon: 'article',
     children: [
       { title: 'Configurations', url: '/admin/wordpress-blog/configs' },
       { title: 'Article Queue', url: '/admin/wordpress-blog/queue' },
       { title: 'Internal Links', url: '/admin/wordpress-blog/links' },
       { title: 'Metrics', url: '/admin/wordpress-blog/metrics' }
     ]
   }
   ```

2. Add routing:
   ```javascript
   const routes = {
     '/admin/wordpress-blog/configs': loadConfigurationsPage,
     '/admin/wordpress-blog/queue': loadQueuePage,
     '/admin/wordpress-blog/links': loadLinksPage,
     '/admin/wordpress-blog/metrics': loadMetricsPage
   };
   ```

3. Add permission checks:
   ```javascript
   if (!currentUser.hasPermission('manage_wordpress_blog')) {
     showError('You do not have permission to access WordPress Blog features');
     redirectToHome();
   }
   ```

4. Add CSS styling (match existing admin theme)

**Acceptance Criteria**:
- [ ] Navigation menu updated
- [ ] Routing works correctly
- [ ] Permission checks enforced
- [ ] CSS matches existing theme
- [ ] No console errors

---

## Phase 7: Error Handling & Validation (1-2 days)

### Task 7.1: Implement WordPressBlogValidationEngine
**File**: `includes/WordPressBlog/Validation/WordPressBlogValidationEngine.php`
**Priority**: HIGH
**Estimated Time**: 4-5 hours
**Dependencies**: Phase 2 complete

**Class Structure**:
```php
namespace WordPressBlog\Validation;

class WordPressBlogValidationEngine {
    // Configuration Validation
    public function validateConfiguration(array $config): array
    private function validateURLFormat(string $url): bool
    private function validateNumberRange(int $value, int $min, int $max): bool
    private function validateWordCountLimits(array $config): array

    // API Connectivity
    public function testOpenAIConnection(string $apiKey): array
    public function testWordPressConnection(string $apiUrl, string $apiKey): array
    public function testGoogleDriveConnection(array $credentials): array

    // Content Validation
    public function validateWordCount(string $content, int $targetCount, float $tolerance = 0.05): bool
    public function validateImageFile(string $filePath): array
    public function validateMarkdown(string $markdown): array

    // Output Validation
    public function validateArticleStructure(array $structure): array
    public function validateChapterContent(array $chapter): array

    // Prohibited Content Detection
    public function detectProhibitedPhrases(string $content, array $prohibitedPhrases = []): array
}
```

**Validation Rules**:

1. **Configuration Validation**:
   - `config_name`: Required, 1-255 characters
   - `website_url`: Required, valid URL format
   - `wordpress_api_url`: Required, valid URL, must be accessible
   - `wordpress_api_key`: Required, minimum 20 characters
   - `openai_api_key`: Required, starts with 'sk-'
   - `number_of_chapters`: 1-20
   - `max_word_count`: 500-10000
   - `introduction_length`: 100-1000
   - `conclusion_length`: 100-1000

2. **Article Queue Validation**:
   - `configuration_id`: Must exist in database
   - `seed_keyword`: Required, 1-255 characters
   - `scheduled_date`: Optional, must be future date
   - `status`: Must be valid enum value

3. **Content Validation**:
   - Word count within ±5% of target
   - Valid markdown syntax
   - Internal links have valid URLs
   - Images are valid PNG/JPG files
   - Images meet size requirements (>100KB, <5MB)

4. **API Connectivity Tests**:
```php
public function testOpenAIConnection(string $apiKey): array {
    try {
        $client = new OpenAIClient($apiKey);
        $response = $client->testConnection();

        return [
            'success' => true,
            'message' => 'OpenAI API connection successful',
            'response_time_ms' => $response['duration']
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'OpenAI API connection failed: ' . $e->getMessage()
        ];
    }
}
```

**Acceptance Criteria**:
- [ ] All validation methods implemented
- [ ] Configuration validation comprehensive
- [ ] API connectivity tests functional
- [ ] Content validation accurate
- [ ] Returns detailed error messages
- [ ] Performance acceptable (<100ms per validation)

---

### Task 7.2: Implement Error Handler and Exception Classes
**File**: `includes/WordPressBlog/Exceptions/` (multiple files)
**Priority**: HIGH
**Estimated Time**: 3-4 hours
**Dependencies**: None

**Exception Class Hierarchy**:

```php
// Base exception
namespace WordPressBlog\Exceptions;

class WordPressBlogException extends \Exception {
    protected $context = [];

    public function __construct(string $message, int $code = 0, array $context = []) {
        parent::__construct($message, $code);
        $this->context = $context;
    }

    public function getContext(): array {
        return $this->context;
    }
}

// Specific exceptions
class ConfigurationException extends WordPressBlogException {}
class ConfigurationNotFoundException extends ConfigurationException {}
class InvalidConfigurationException extends ConfigurationException {}

class QueueException extends WordPressBlogException {}
class ArticleNotFoundException extends QueueException {}
class InvalidStatusTransitionException extends QueueException {}

class ContentGenerationException extends WordPressBlogException {}
class StructureGenerationException extends ContentGenerationException {}
class ChapterGenerationException extends ContentGenerationException {}
class WordCountValidationException extends ContentGenerationException {}

class ImageGenerationException extends WordPressBlogException {}
class ImageDownloadException extends ImageGenerationException {}
class ImageValidationException extends ImageGenerationException {}

class WordPressPublishException extends WordPressBlogException {}
class WordPressAuthenticationException extends WordPressPublishException {}
class WordPressAPIException extends WordPressPublishException {}

class StorageException extends WordPressBlogException {}
class GoogleDriveException extends StorageException {}
class QuotaExceededException extends StorageException {}

class CredentialException extends WordPressBlogException {}
class EncryptionException extends CredentialException {}
class DecryptionException extends CredentialException {}
```

**Error Handler**:

```php
namespace WordPressBlog\ErrorHandling;

class WordPressBlogErrorHandler {
    private $executionLogger;

    // Main Error Handling
    public function handleException(\Exception $e, array $context = []): void
    public function handleAPIError(string $service, array $errorResponse): void

    // Retry Logic
    public function shouldRetry(\Exception $e): bool
    public function calculateBackoff(int $attemptNumber): int
    public function retryOperation(callable $operation, int $maxRetries = 3): mixed

    // Error Classification
    private function isRetryableError(\Exception $e): bool
    private function isRateLimitError(array $response): bool
    private function isAuthenticationError(array $response): bool

    // Error Reporting
    public function reportError(\Exception $e, array $context = []): void
    private function sendErrorNotification(array $errorData): void
}
```

**Retry Strategy**:
- **Retryable Errors**: API rate limits, temporary network errors, storage quota
- **Non-Retryable Errors**: Authentication failures, invalid configuration, validation errors
- **Backoff Formula**: `delay = base_delay * (2 ^ attempt_number)` (exponential backoff)
  - Attempt 1: 0 seconds (immediate)
  - Attempt 2: 2 seconds
  - Attempt 3: 4 seconds
  - Attempt 4: 8 seconds
  - Attempt 5+: 16 seconds (max)

**Acceptance Criteria**:
- [ ] Exception hierarchy created
- [ ] All exceptions extend base class
- [ ] Error handler implements retry logic
- [ ] Exponential backoff calculated correctly
- [ ] Error context preserved
- [ ] Errors logged appropriately

---

### Task 7.3: Implement Credential Encryption for Blog Services
**File**: `includes/WordPressBlog/Security/BlogCredentialManager.php`
**Priority**: HIGH
**Estimated Time**: 2-3 hours
**Dependencies**: Task 2.1

**Class Structure**:
```php
namespace WordPressBlog\Security;

class BlogCredentialManager {
    private $secretsManager;

    // Encryption/Decryption
    public function encryptAPIKey(string $plainKey, string $keyType): string
    public function decryptAPIKey(string $encryptedKey, string $keyType): string

    // Batch Operations
    public function encryptCredentials(array $credentials): array
    public function decryptCredentials(array $encryptedData): array

    // Key Rotation
    public function rotateEncryptionKey(): void
    public function reencryptAllCredentials(): void

    // Validation
    public function validateWordPressCredentials(string $apiUrl, string $apiKey): bool
    public function validateOpenAICredentials(string $apiKey): bool

    // Audit
    public function logCredentialAccess(string $configId, string $keyType): void
}
```

**Integration with SecretsManager**:
```php
public function encryptAPIKey(string $plainKey, string $keyType): string {
    // Use existing SecretsManager
    return $this->secretsManager->encrypt($plainKey, [
        'key_type' => $keyType,
        'purpose' => 'wordpress_blog_automation'
    ]);
}

public function decryptAPIKey(string $encryptedKey, string $keyType): string {
    // Decrypt and validate
    $plainKey = $this->secretsManager->decrypt($encryptedKey);

    // Log access
    $this->logCredentialAccess('unknown', $keyType);

    return $plainKey;
}
```

**Security Best Practices**:
- Never log API keys (even partially)
- Decrypt credentials only when needed
- Keep decrypted keys in memory only
- Clear sensitive data after use: `sodium_memzero()`
- Audit all credential access
- Support key rotation without service interruption

**Acceptance Criteria**:
- [ ] Integrates with existing SecretsManager
- [ ] Encrypts credentials before database storage
- [ ] Decrypts credentials securely
- [ ] Audit logging functional
- [ ] Never exposes plaintext keys in logs
- [ ] Performance acceptable (<10ms per operation)

---

### Task 7.4: Create Error Handling Tests
**File**: `tests/WordPressBlog/ErrorHandlingTest.php`
**Priority**: MEDIUM
**Estimated Time**: 2-3 hours
**Dependencies**: Tasks 7.1-7.3

**Test Coverage**:
- Exception hierarchy
- Error handler retry logic
- Backoff calculation
- Credential encryption/decryption
- Validation engine
- API connectivity tests

**Acceptance Criteria**:
- [ ] All exception types tested
- [ ] Retry logic validated
- [ ] Encryption security verified
- [ ] Validation rules tested

---

## Phase 8: Integration Testing & Documentation (2-3 days)

### Task 8.1: Create End-to-End Integration Tests
**File**: `tests/Integration/WordPressBlogE2ETest.php`
**Priority**: HIGH
**Estimated Time**: 6-8 hours
**Dependencies**: All previous phases

**Test Scenarios**:

1. **Happy Path - Full Article Generation**
   - Create configuration
   - Queue article
   - Process queue (full workflow)
   - Verify article published to WordPress
   - Verify assets in Google Drive
   - Verify execution log exists

2. **Error Recovery - API Failure**
   - Simulate OpenAI API failure
   - Verify retry logic triggers
   - Verify eventual success or proper failure handling

3. **Error Recovery - WordPress Failure**
   - Simulate WordPress API failure
   - Verify article not marked as published
   - Verify error logged

4. **Concurrent Processing**
   - Queue multiple articles
   - Process queue with multiple workers
   - Verify no duplicate processing
   - Verify all articles processed

5. **Configuration Update During Processing**
   - Start article processing
   - Update configuration
   - Verify processing uses original config

**Test Implementation**:
```php
public function testFullArticleGenerationWorkflow() {
    // 1. Create configuration
    $configService = new WordPressBlogConfigurationService($this->db, $this->secretsManager);
    $configId = $configService->createConfiguration([
        'config_name' => 'Test Config',
        'website_url' => 'https://test-blog.com',
        'wordpress_api_url' => 'https://test-blog.com',
        'wordpress_api_key' => 'test_key',
        'openai_api_key' => getenv('TEST_OPENAI_API_KEY'),
        'number_of_chapters' => 3,
        'max_word_count' => 1500
    ]);

    // 2. Queue article
    $queueService = new WordPressBlogQueueService($this->db);
    $articleId = $queueService->queueArticle([
        'configuration_id' => $configId,
        'seed_keyword' => 'Test Article Topic',
        'target_audience' => 'Developers',
        'writing_style' => 'technical'
    ]);

    // 3. Process article
    $generatorService = new WordPressBlogGeneratorService(/* dependencies */);
    $result = $generatorService->generateAndPublishArticle($articleId);

    // 4. Verify results
    $this->assertTrue($result['success']);
    $this->assertNotNull($result['wordpress_post_id']);
    $this->assertNotNull($result['wordpress_post_url']);

    // 5. Verify article in database
    $article = $queueService->getArticle($articleId);
    $this->assertEquals('published', $article['status']);
    $this->assertNotNull($article['execution_log_url']);

    // 6. Verify execution log
    $log = $this->fetchExecutionLog($article['execution_log_url']);
    $this->assertEquals('completed', $log['status']);
    $this->assertCount(6, $log['phases']);
}
```

**Acceptance Criteria**:
- [ ] All test scenarios pass
- [ ] Tests use real APIs (in test mode)
- [ ] Tests clean up after themselves
- [ ] Tests run in <5 minutes
- [ ] Provides detailed failure messages

---

### Task 8.2: Create Operational Runbook
**File**: `docs/WORDPRESS_BLOG_OPERATIONS.md`
**Priority**: MEDIUM
**Estimated Time**: 3-4 hours
**Dependencies**: All implementation complete

**Runbook Sections**:

1. **Setup & Configuration**
   - Initial database migration
   - Creating first configuration
   - Setting up cron job
   - Configuring API credentials

2. **Daily Operations**
   - Queuing articles
   - Monitoring queue status
   - Reviewing published articles
   - Checking system health

3. **Monitoring & Alerts**
   - Key metrics to watch
   - Warning signs (high failure rate, slow processing)
   - Health check interpretation
   - Log analysis

4. **Troubleshooting Guide**
   - Article stuck in "processing" status → Solution steps
   - OpenAI API failures → How to diagnose and fix
   - WordPress authentication errors → Credential verification
   - Google Drive quota exceeded → Storage management
   - High word count variance → Configuration adjustment

5. **Maintenance Tasks**
   - Daily: Check queue health, review failures
   - Weekly: Analyze metrics, optimize configurations
   - Monthly: Review API costs, clean up old logs
   - Quarterly: Audit credentials, review internal links

6. **Emergency Procedures**
   - How to pause queue processing
   - How to cancel all queued articles
   - How to reprocess failed articles
   - How to rollback configuration changes

**Troubleshooting Examples**:
```markdown
### Problem: Article Stuck in "Processing" Status

**Symptoms**: Article has status "processing" for >30 minutes

**Diagnosis**:
1. Check execution log URL in database
2. Review log for last completed phase
3. Check system logs for errors

**Resolution**:
1. If phase failed: Update article status to "failed", review error
2. If worker died: Reset status to "queued" to retry
3. If external API down: Wait for recovery, then retry

**Prevention**:
- Implement processing timeout (max 20 minutes)
- Add health check monitoring
- Set up alerting for stuck articles
```

**Acceptance Criteria**:
- [ ] All sections completed
- [ ] Troubleshooting guide comprehensive
- [ ] Includes code examples
- [ ] Maintenance schedule defined
- [ ] Emergency procedures clear

---

### Task 8.3: Create API Documentation
**File**: `docs/WORDPRESS_BLOG_API.md`
**Priority**: MEDIUM
**Estimated Time**: 2-3 hours
**Dependencies**: Phase 5 complete

**Documentation Format**:

For each endpoint, document:
- HTTP Method and Path
- Authentication requirements
- Request parameters (path, query, body)
- Request example (curl)
- Response format
- Response examples (success and error)
- Error codes

**Example**:
```markdown
## Create Configuration

Creates a new WordPress blog configuration.

**Endpoint**: `POST /api/wordpress-blog/configurations`

**Authentication**: Required (Bearer token)

**Permissions**: `manage_blog_configurations`

**Request Body**:
```json
{
  "config_name": "My Blog Config",
  "website_url": "https://example.com",
  "wordpress_api_url": "https://example.com",
  "wordpress_api_key": "application_password_here",
  "openai_api_key": "sk-...",
  "number_of_chapters": 5,
  "max_word_count": 3000,
  "introduction_length": 300,
  "conclusion_length": 200,
  "cta_message": "Get started today!",
  "cta_url": "https://example.com/signup",
  "default_publish_status": "draft"
}
```

**Response** (Success - 201 Created):
```json
{
  "success": true,
  "configuration_id": "550e8400-e29b-41d4-a716-446655440000",
  "message": "Configuration created successfully"
}
```

**Response** (Error - 400 Bad Request):
```json
{
  "error": "Validation failed",
  "validation_errors": {
    "wordpress_api_key": "Must be at least 20 characters",
    "number_of_chapters": "Must be between 1 and 20"
  }
}
```

**Example Request**:
```bash
curl -X POST https://your-domain.com/api/wordpress-blog/configurations \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d @request.json
```
```

**Acceptance Criteria**:
- [ ] All endpoints documented
- [ ] Examples provided for each
- [ ] Error codes explained
- [ ] Authentication requirements clear
- [ ] Formatted consistently

---

### Task 8.4: Create Setup Guide
**File**: `docs/WORDPRESS_BLOG_SETUP.md`
**Priority**: MEDIUM
**Estimated Time**: 2-3 hours
**Dependencies**: All implementation complete

**Setup Guide Sections**:

1. **Prerequisites**
   - PHP version (7.4+)
   - Database (MySQL 5.7+ or PostgreSQL 10+)
   - OpenAI API account
   - WordPress site with REST API enabled
   - Google Drive service account (optional)

2. **Installation Steps**
   ```bash
   # 1. Run database migration
   php db/migrate.php

   # 2. Validate schema
   php db/validate_blog_schema.php

   # 3. Configure environment variables
   cp .env.example .env
   # Edit .env with your credentials

   # 4. Test setup
   php scripts/wordpress_blog_processor.php --health-check
   ```

3. **Configuration**
   - Creating first blog configuration (step-by-step)
   - Setting up internal links repository
   - Configuring cron job

4. **Testing**
   - Queue a test article
   - Process manually
   - Verify WordPress publication
   - Review execution logs

5. **Production Deployment**
   - Security checklist
   - Performance optimization
   - Monitoring setup
   - Backup strategy

**Acceptance Criteria**:
- [ ] Step-by-step instructions
- [ ] Includes screenshots (optional)
- [ ] Testing instructions clear
- [ ] Production considerations covered

---

### Task 8.5: Update Main Implementation Documentation
**File**: `docs/WORDPRESS_BLOG_IMPLEMENTATION.md`
**Priority**: LOW
**Estimated Time**: 1-2 hours
**Dependencies**: All tasks complete

**Content**:
- Overview of implementation
- Architecture diagram
- Technology stack
- File structure reference
- Link to all sub-documentation (API, Operations, Setup)
- Known limitations
- Future enhancements

**Acceptance Criteria**:
- [ ] Comprehensive overview
- [ ] Links to all docs
- [ ] Architecture diagram updated
- [ ] Reflects actual implementation

---

## Phase 9: Final Testing & Validation (1-2 days)

### Task 9.1: Run Full Test Suite
**Priority**: CRITICAL
**Estimated Time**: 2-3 hours
**Dependencies**: All implementation and tests complete

**Steps**:
1. Run all unit tests: `php vendor/bin/phpunit tests/WordPressBlog/`
2. Run integration tests: `php vendor/bin/phpunit tests/Integration/`
3. Run API tests: `php vendor/bin/phpunit tests/API/`
4. Check code coverage: `php vendor/bin/phpunit --coverage-html coverage/`
5. Review coverage report (target: 80%+)

**Acceptance Criteria**:
- [ ] All tests pass
- [ ] Code coverage ≥80%
- [ ] No critical issues found
- [ ] Performance tests pass

---

### Task 9.2: Manual End-to-End Testing
**Priority**: CRITICAL
**Estimated Time**: 3-4 hours
**Dependencies**: All implementation complete

**Test Cases**:

1. **Configuration Management**
   - [ ] Create new configuration via UI
   - [ ] Edit configuration
   - [ ] Delete configuration
   - [ ] Add internal links

2. **Article Queue**
   - [ ] Queue article via UI
   - [ ] View queue status
   - [ ] Filter by status/configuration
   - [ ] View article details

3. **Processing**
   - [ ] Process article manually: `php scripts/wordpress_blog_processor.php --article-id=<id>`
   - [ ] Verify all 6 phases complete
   - [ ] Check execution log
   - [ ] Verify WordPress publication

4. **Error Handling**
   - [ ] Queue article with invalid configuration
   - [ ] Simulate API failure (disable network)
   - [ ] Verify retry logic
   - [ ] Verify error logged

5. **Monitoring**
   - [ ] View metrics dashboard
   - [ ] Check system health
   - [ ] Review execution logs
   - [ ] Verify statistics accurate

**Acceptance Criteria**:
- [ ] All test cases pass
- [ ] UI functions correctly
- [ ] Processing completes successfully
- [ ] Errors handled gracefully
- [ ] Monitoring displays accurate data

---

### Task 9.3: Performance Testing
**Priority**: HIGH
**Estimated Time**: 2-3 hours
**Dependencies**: All implementation complete

**Performance Benchmarks**:

1. **Database Operations**
   - Configuration retrieval: <50ms
   - Queue fetch: <100ms
   - Status update: <50ms

2. **Content Generation**
   - Structure generation: <30s
   - Single chapter generation: <15s
   - Image generation: <20s

3. **Full Workflow**
   - 3-chapter article: <3 minutes
   - 5-chapter article: <5 minutes
   - 10-chapter article: <10 minutes

4. **API Endpoints**
   - Configuration CRUD: <200ms
   - Queue list: <500ms
   - Metrics dashboard: <1s

**Load Testing**:
- Queue 10 articles
- Process concurrently (if supported)
- Measure throughput
- Verify no database locks or race conditions

**Acceptance Criteria**:
- [ ] All benchmarks met
- [ ] No performance regressions
- [ ] Concurrent processing stable
- [ ] Database performs adequately

---

### Task 9.4: Security Audit
**Priority**: HIGH
**Estimated Time**: 2-3 hours
**Dependencies**: All implementation complete

**Security Checklist**:

1. **Authentication & Authorization**
   - [ ] All endpoints require authentication
   - [ ] Permission checks enforced
   - [ ] No privilege escalation possible

2. **Credential Management**
   - [ ] API keys encrypted at rest
   - [ ] API keys never logged
   - [ ] API keys not exposed in responses
   - [ ] Secure credential rotation supported

3. **Input Validation**
   - [ ] All user input validated
   - [ ] SQL injection prevented (prepared statements)
   - [ ] XSS prevented (output escaping)
   - [ ] CSRF protection enabled

4. **API Security**
   - [ ] Rate limiting implemented
   - [ ] Request validation comprehensive
   - [ ] Error messages don't leak sensitive info
   - [ ] HTTPS enforced (in production)

5. **Data Privacy**
   - [ ] Execution logs don't contain credentials
   - [ ] Proper data sanitization
   - [ ] Secure data deletion

**Acceptance Criteria**:
- [ ] All security checks pass
- [ ] No high-severity vulnerabilities
- [ ] Follows OWASP top 10 guidelines
- [ ] Security documentation complete

---

### Task 9.5: Create Release Checklist
**File**: `docs/WORDPRESS_BLOG_RELEASE_CHECKLIST.md`
**Priority**: MEDIUM
**Estimated Time**: 1 hour
**Dependencies**: All testing complete

**Checklist Content**:

```markdown
# WordPress Blog Automation - Release Checklist

## Pre-Release

- [ ] All unit tests passing
- [ ] All integration tests passing
- [ ] Code coverage ≥80%
- [ ] Performance benchmarks met
- [ ] Security audit completed
- [ ] Manual testing completed
- [ ] Documentation complete
- [ ] API documentation up to date

## Database

- [ ] Migration file reviewed
- [ ] Migration tested on clean database
- [ ] Rollback tested
- [ ] Indexes optimized
- [ ] Foreign keys validated

## Configuration

- [ ] Environment variables documented
- [ ] Sample .env file provided
- [ ] Encryption keys configured
- [ ] API credentials tested

## Deployment

- [ ] Backup database before migration
- [ ] Run migration: `php db/migrate.php`
- [ ] Validate schema: `php db/validate_blog_schema.php`
- [ ] Test health check: `php scripts/wordpress_blog_processor.php --health-check`
- [ ] Configure cron job
- [ ] Verify cron executes

## Post-Release

- [ ] Monitor first article generation
- [ ] Verify WordPress publication
- [ ] Check execution logs
- [ ] Monitor error rates
- [ ] Review system health dashboard
- [ ] Test UI functionality
- [ ] Verify API endpoints

## Rollback Plan (if needed)

1. Disable cron job
2. Cancel all queued articles
3. Run rollback migration
4. Restore database from backup
5. Notify stakeholders
```

**Acceptance Criteria**:
- [ ] Checklist comprehensive
- [ ] Covers all critical areas
- [ ] Rollback plan included
- [ ] Easy to follow

---

## Implementation Summary

### Total Estimated Timeline: 4-5 weeks

| Phase | Duration | Tasks | Status |
|-------|----------|-------|--------|
| Phase 1: Database Foundation | 2-3 days | 3 | Pending |
| Phase 2: Core Services (Config & Queue) | 2-3 days | 4 | Pending |
| Phase 3: Core Services (Content Gen) | 3-4 days | 7 | Pending |
| Phase 4: Orchestration & Workflow | 2-3 days | 4 | Pending |
| Phase 5: API Endpoints | 2-3 days | 4 | Pending |
| Phase 6: Admin UI Components | 3-4 days | 5 | Pending |
| Phase 7: Error Handling & Validation | 1-2 days | 4 | Pending |
| Phase 8: Integration Testing & Docs | 2-3 days | 5 | Pending |
| Phase 9: Final Testing & Validation | 1-2 days | 5 | Pending |
| **TOTAL** | **18-27 days** | **41 tasks** | **0% Complete** |

### Critical Path

The following tasks are on the critical path and must be completed sequentially:

1. Task 1.1: Database Migration (BLOCKS all other tasks)
2. Task 2.1: Configuration Service (BLOCKS Tasks 2.2, 3.1, 4.1)
3. Task 2.2: Queue Service (BLOCKS Task 4.1)
4. Task 3.1: Structure Builder (BLOCKS Task 3.2)
5. Task 3.2: Chapter Writer (BLOCKS Task 4.1)
6. Task 4.1: Generator Service (BLOCKS Task 4.2)
7. Task 4.2: Orchestrator (BLOCKS Task 4.3)
8. Task 4.3: Processor Script (BLOCKS Phase 9)

### Parallelization Opportunities

These tasks can be worked on in parallel:

- **After Phase 1**: Tasks 2.1 + 7.2 (Exception classes)
- **After Task 2.1**: Tasks 2.2 + 3.3 (Image Generator) + 3.4 (Asset Organizer)
- **After Task 2.2**: Tasks 2.3 + 2.4 (Tests)
- **After Task 3.2**: Tasks 3.3 + 3.4 + 3.5 + 3.6 (All independent services)
- **After Phase 4**: Tasks 5.1 + 5.2 + 5.3 + 6.1 + 6.2 (API & UI in parallel)
- **After Phase 6**: Tasks 7.1 + 8.2 + 8.3 + 8.4 (Docs in parallel)

### Success Criteria

Implementation is production-ready when:

- [x] Foundation complete (15%) - DONE
- [ ] All 41 tasks completed (85%) - PENDING
- [ ] All tests passing (100%)
- [ ] Code coverage ≥80%
- [ ] Performance benchmarks met
- [ ] Security audit passed
- [ ] Documentation complete
- [ ] Manual E2E test passed
- [ ] First article successfully published to WordPress

---

## Next Steps

1. **Review and Approve Plan**: Review this implementation plan and provide feedback
2. **Assign Resources**: Determine who will work on which phases
3. **Set Up Project Tracking**: Create tickets/issues for each task
4. **Begin Phase 1**: Start with database migration (Task 1.1)
5. **Regular Check-ins**: Weekly progress reviews and blockers discussion

---

## Notes

- All file paths assume repository root: `c:\Users\alexa\Dropbox\workspace\dev\gpt-chatbot-boilerplate\`
- Use existing patterns from the codebase (follow `includes/` structure, database patterns, etc.)
- Leverage existing infrastructure (SecretsManager, authentication, error handling patterns)
- Test against real APIs (OpenAI, WordPress, Google Drive) but use test accounts/credentials
- Consider performance from the start (database indexes, caching, parallel processing)
- Follow security best practices (encryption at rest, input validation, output escaping)
