# WordPress Blog Tables Migration Instructions

## Migration 048: Add WordPress Blog Automation Tables

This migration adds 5 new tables, 15 indexes, and 3 triggers to support WordPress blog automation.

---

## Prerequisites

- PHP 7.4 or higher installed
- SQLite3 extension enabled in PHP
- Database file exists at: `data/chatbot.db`

---

## Option 1: Run Migration Using PHP Script (Recommended)

### Step 1: Run the Migration

```bash
php db/run_migration.php 048
```

This will:
- Load the migration SQL file
- Execute it against the database
- Show created tables, indexes, and triggers
- Report success or failure

### Step 2: Validate the Schema

```bash
php db/validate_blog_schema.php
```

This will:
- Check all 5 tables exist
- Verify all columns and data types
- Confirm all 15 indexes are created
- Check all 3 triggers exist
- Validate foreign keys
- Test check constraints
- Report validation results

**Expected Output:**
```
WordPress Blog Schema Validation
==================================================

Checking tables...
✓ Table 'blog_configurations' exists
✓ Table 'blog_articles_queue' exists
✓ Table 'blog_article_categories' exists
✓ Table 'blog_article_tags' exists
✓ Table 'blog_internal_links' exists

... (more checks)

==================================================
Validation Summary
==================================================
Total checks: 65
Passed: 65
Failed: 0
Warnings: 0
Success rate: 100.0%

✓ ALL VALIDATIONS PASSED!
The WordPress blog schema is correctly installed.
```

---

## Option 2: Run Migration Using SQLite3 CLI

### Step 1: Run the Migration

```bash
sqlite3 data/chatbot.db < db/migrations/048_add_wordpress_blog_tables.sql
```

### Step 2: Verify Tables Were Created

```bash
sqlite3 data/chatbot.db "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'blog_%';"
```

**Expected Output:**
```
blog_article_categories
blog_article_tags
blog_articles_queue
blog_configurations
blog_internal_links
```

### Step 3: Verify Indexes Were Created

```bash
sqlite3 data/chatbot.db "SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_blog_%';"
```

**Expected Output:** (15 indexes)

### Step 4: Run Validation Script

```bash
php db/validate_blog_schema.php
```

---

## Option 3: Run Migration Using Database Management Tool

If you have a database management tool installed (DB Browser for SQLite, DBeaver, etc.):

1. Open `data/chatbot.db` in your tool
2. Open `db/migrations/048_add_wordpress_blog_tables.sql`
3. Execute the SQL
4. Run validation: `php db/validate_blog_schema.php`

---

## What This Migration Creates

### Tables (5)

1. **blog_configurations**
   - Stores WordPress blog configurations
   - Encrypted API credentials (WordPress, OpenAI)
   - Content settings (chapters, word count, CTA)
   - 17 columns total

2. **blog_articles_queue**
   - Article generation queue
   - Status tracking (queued, processing, completed, failed, published)
   - Execution logging
   - WordPress post tracking
   - 17 columns total

3. **blog_article_categories**
   - Many-to-many relationship for article categories
   - 5 columns total

4. **blog_article_tags**
   - Many-to-many relationship for article tags
   - 5 columns total

5. **blog_internal_links**
   - Internal link repository for SEO
   - Keyword-based relevance
   - Priority and active status
   - 9 columns total

### Indexes (15)

- `idx_blog_configs_config_name`
- `idx_blog_configs_created_at`
- `idx_blog_articles_status`
- `idx_blog_articles_configuration_id`
- `idx_blog_articles_scheduled_date`
- `idx_blog_articles_created_at`
- `idx_blog_articles_queue_polling`
- `idx_blog_categories_article_id`
- `idx_blog_categories_category_name`
- `idx_blog_tags_article_id`
- `idx_blog_tags_tag_name`
- `idx_blog_links_configuration_id`
- `idx_blog_links_is_active`
- `idx_blog_links_priority`
- `idx_blog_links_config_active`

### Triggers (3)

- `update_blog_configurations_timestamp` - Auto-update updated_at
- `update_blog_articles_queue_timestamp` - Auto-update updated_at
- `update_blog_internal_links_timestamp` - Auto-update updated_at

### Foreign Keys (4)

- `blog_articles_queue.configuration_id` → `blog_configurations.configuration_id`
- `blog_article_categories.article_id` → `blog_articles_queue.article_id`
- `blog_article_tags.article_id` → `blog_articles_queue.article_id`
- `blog_internal_links.configuration_id` → `blog_configurations.configuration_id`

---

## Troubleshooting

### Error: "table already exists"

The migration has already been run. To rollback:

```bash
sqlite3 data/chatbot.db <<EOF
BEGIN TRANSACTION;

DROP TRIGGER IF EXISTS update_blog_configurations_timestamp;
DROP TRIGGER IF EXISTS update_blog_articles_queue_timestamp;
DROP TRIGGER IF EXISTS update_blog_internal_links_timestamp;

DROP TABLE IF EXISTS blog_article_tags;
DROP TABLE IF EXISTS blog_article_categories;
DROP TABLE IF EXISTS blog_internal_links;
DROP TABLE IF EXISTS blog_articles_queue;
DROP TABLE IF EXISTS blog_configurations;

COMMIT;
EOF
```

Then run the migration again.

### Error: "database is locked"

Close any applications accessing the database and try again.

### Error: "constraint failed"

Check that you're running the migration on the correct database. The migration includes check constraints that prevent invalid data.

### Validation Script Fails

Check the validation output for specific errors:
- Missing tables: Re-run migration
- Missing columns: Check migration SQL
- Missing indexes: Verify migration completed
- Foreign key errors: Ensure foreign keys are enabled

---

## Manual Verification Queries

### Check All Blog Tables

```sql
SELECT name, sql FROM sqlite_master
WHERE type='table' AND name LIKE 'blog_%'
ORDER BY name;
```

### Check blog_configurations Schema

```sql
PRAGMA table_info(blog_configurations);
```

### Check All Blog Indexes

```sql
SELECT name, tbl_name, sql FROM sqlite_master
WHERE type='index' AND name LIKE 'idx_blog_%'
ORDER BY name;
```

### Check All Blog Triggers

```sql
SELECT name, tbl_name, sql FROM sqlite_master
WHERE type='trigger' AND name LIKE '%blog%'
ORDER BY name;
```

### Check Foreign Keys

```sql
PRAGMA foreign_keys = ON;
PRAGMA foreign_key_list(blog_articles_queue);
PRAGMA foreign_key_list(blog_internal_links);
PRAGMA foreign_key_list(blog_article_categories);
PRAGMA foreign_key_list(blog_article_tags);
```

### Test Insert (Verify Constraints)

```sql
-- This should succeed
INSERT INTO blog_configurations (
    config_name,
    website_url,
    number_of_chapters,
    max_word_count,
    wordpress_api_url,
    wordpress_api_key_encrypted,
    openai_api_key_encrypted
) VALUES (
    'Test Config',
    'https://example.com',
    5,
    3000,
    'https://example.com/wp-json',
    'encrypted_wp_key',
    'encrypted_openai_key'
);

-- Verify insertion
SELECT configuration_id, config_name, number_of_chapters FROM blog_configurations;

-- Clean up
DELETE FROM blog_configurations WHERE config_name = 'Test Config';
```

---

## Next Steps After Migration

1. ✅ **Run Validation**: `php db/validate_blog_schema.php`
2. **Proceed to Phase 2**: Implement service classes
   - Issue #4: WordPressBlogConfigurationService
   - Issue #5: WordPressBlogQueueService
3. **Review Implementation Plan**: `docs/WORDPRESS_BLOG_IMPLEMENTATION_PLAN.md`
4. **Track Progress**: `docs/issues/wordpress-agent-20251120/IMPLEMENTATION_ISSUES.md`

---

## Files Created in This Phase

- `db/migrations/048_add_wordpress_blog_tables.sql` - Migration SQL
- `db/validate_blog_schema.php` - Validation script
- `db/run_migration.php` - Migration runner script
- `db/MIGRATION_INSTRUCTIONS.md` - This file

---

## Support

If you encounter issues:

1. Check the troubleshooting section above
2. Verify PHP and SQLite3 are installed correctly
3. Ensure database file has correct permissions
4. Review migration SQL for syntax errors
5. Check application logs for detailed errors

---

**Status**: Ready to run
**Migration Version**: 048
**Date**: 2025-11-20
