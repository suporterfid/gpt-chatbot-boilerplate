-- Migration: Add WordPress Blog Automation Tables
-- Description: Adds tables for WordPress blog article generation and queue management
-- Version: 1.0
-- Date: 2025-11-20

BEGIN TRANSACTION;

-- ============================================================
-- 1. Create blog_configurations table
-- ============================================================

-- Stores WordPress blog configurations with encrypted credentials
CREATE TABLE IF NOT EXISTS blog_configurations (
    configuration_id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    config_name TEXT NOT NULL,
    website_url TEXT NOT NULL,
    number_of_chapters INTEGER DEFAULT 5 NOT NULL CHECK(number_of_chapters BETWEEN 1 AND 20),
    max_word_count INTEGER DEFAULT 3000 NOT NULL CHECK(max_word_count BETWEEN 500 AND 10000),
    introduction_length INTEGER DEFAULT 300 NOT NULL CHECK(introduction_length BETWEEN 100 AND 1000),
    conclusion_length INTEGER DEFAULT 200 NOT NULL CHECK(conclusion_length BETWEEN 100 AND 1000),
    cta_message TEXT,
    cta_url TEXT,
    company_offering TEXT,
    wordpress_api_url TEXT NOT NULL,
    wordpress_api_key_encrypted TEXT NOT NULL,
    openai_api_key_encrypted TEXT NOT NULL,
    default_publish_status TEXT DEFAULT 'draft' NOT NULL CHECK(default_publish_status IN ('draft', 'publish', 'pending')),
    google_drive_folder_id TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL
);

-- Indexes for blog_configurations
CREATE INDEX IF NOT EXISTS idx_blog_configs_config_name
    ON blog_configurations(config_name);

CREATE INDEX IF NOT EXISTS idx_blog_configs_created_at
    ON blog_configurations(created_at);

-- ============================================================
-- 2. Create blog_articles_queue table
-- ============================================================

-- Stores article generation queue with status tracking
CREATE TABLE IF NOT EXISTS blog_articles_queue (
    article_id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    configuration_id TEXT NOT NULL,
    status TEXT DEFAULT 'queued' NOT NULL CHECK(status IN ('queued', 'processing', 'completed', 'failed', 'published')),
    seed_keyword TEXT NOT NULL,
    target_audience TEXT,
    writing_style TEXT,
    publication_date TEXT,
    scheduled_date TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
    processing_started_at TEXT,
    processing_completed_at TEXT,
    execution_log_url TEXT,
    wordpress_post_id INTEGER,
    wordpress_post_url TEXT,
    error_message TEXT,
    retry_count INTEGER DEFAULT 0 NOT NULL,
    FOREIGN KEY (configuration_id) REFERENCES blog_configurations(configuration_id) ON DELETE CASCADE
);

-- Indexes for blog_articles_queue
CREATE INDEX IF NOT EXISTS idx_blog_articles_status
    ON blog_articles_queue(status);

CREATE INDEX IF NOT EXISTS idx_blog_articles_configuration_id
    ON blog_articles_queue(configuration_id);

CREATE INDEX IF NOT EXISTS idx_blog_articles_scheduled_date
    ON blog_articles_queue(scheduled_date);

CREATE INDEX IF NOT EXISTS idx_blog_articles_created_at
    ON blog_articles_queue(created_at);

-- Index for queue polling (status + scheduled_date for FIFO)
CREATE INDEX IF NOT EXISTS idx_blog_articles_queue_polling
    ON blog_articles_queue(status, scheduled_date, created_at);

-- ============================================================
-- 3. Create blog_article_categories table (many-to-many)
-- ============================================================

-- Stores category associations for blog articles
CREATE TABLE IF NOT EXISTS blog_article_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id TEXT NOT NULL,
    category_id INTEGER,
    category_name TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
    FOREIGN KEY (article_id) REFERENCES blog_articles_queue(article_id) ON DELETE CASCADE
);

-- Indexes for blog_article_categories
CREATE INDEX IF NOT EXISTS idx_blog_categories_article_id
    ON blog_article_categories(article_id);

CREATE INDEX IF NOT EXISTS idx_blog_categories_category_name
    ON blog_article_categories(category_name);

-- ============================================================
-- 4. Create blog_article_tags table (many-to-many)
-- ============================================================

-- Stores tag associations for blog articles
CREATE TABLE IF NOT EXISTS blog_article_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id TEXT NOT NULL,
    tag_id INTEGER,
    tag_name TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
    FOREIGN KEY (article_id) REFERENCES blog_articles_queue(article_id) ON DELETE CASCADE
);

-- Indexes for blog_article_tags
CREATE INDEX IF NOT EXISTS idx_blog_tags_article_id
    ON blog_article_tags(article_id);

CREATE INDEX IF NOT EXISTS idx_blog_tags_tag_name
    ON blog_article_tags(tag_name);

-- ============================================================
-- 5. Create blog_internal_links table
-- ============================================================

-- Stores internal link repository for SEO optimization
CREATE TABLE IF NOT EXISTS blog_internal_links (
    link_id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))),
    configuration_id TEXT NOT NULL,
    url TEXT NOT NULL,
    anchor_text TEXT NOT NULL,
    relevance_keywords TEXT,  -- JSON array of keywords
    priority INTEGER DEFAULT 5 NOT NULL CHECK(priority BETWEEN 1 AND 10),
    is_active INTEGER DEFAULT 1 NOT NULL,  -- SQLite uses INTEGER for boolean (0=false, 1=true)
    created_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP NOT NULL,
    FOREIGN KEY (configuration_id) REFERENCES blog_configurations(configuration_id) ON DELETE CASCADE
);

-- Indexes for blog_internal_links
CREATE INDEX IF NOT EXISTS idx_blog_links_configuration_id
    ON blog_internal_links(configuration_id);

CREATE INDEX IF NOT EXISTS idx_blog_links_is_active
    ON blog_internal_links(is_active);

CREATE INDEX IF NOT EXISTS idx_blog_links_priority
    ON blog_internal_links(priority DESC);

-- Composite index for active links by configuration
CREATE INDEX IF NOT EXISTS idx_blog_links_config_active
    ON blog_internal_links(configuration_id, is_active);

-- ============================================================
-- 6. Create triggers to update updated_at timestamps
-- ============================================================

-- Trigger for blog_configurations
CREATE TRIGGER IF NOT EXISTS update_blog_configurations_timestamp
AFTER UPDATE ON blog_configurations
BEGIN
    UPDATE blog_configurations
    SET updated_at = CURRENT_TIMESTAMP
    WHERE configuration_id = NEW.configuration_id;
END;

-- Trigger for blog_articles_queue
CREATE TRIGGER IF NOT EXISTS update_blog_articles_queue_timestamp
AFTER UPDATE ON blog_articles_queue
BEGIN
    UPDATE blog_articles_queue
    SET updated_at = CURRENT_TIMESTAMP
    WHERE article_id = NEW.article_id;
END;

-- Trigger for blog_internal_links
CREATE TRIGGER IF NOT EXISTS update_blog_internal_links_timestamp
AFTER UPDATE ON blog_internal_links
BEGIN
    UPDATE blog_internal_links
    SET updated_at = CURRENT_TIMESTAMP
    WHERE link_id = NEW.link_id;
END;

COMMIT;

-- ============================================================
-- Rollback Instructions (for reference)
-- ============================================================
-- To rollback this migration, execute the following:
--
-- BEGIN TRANSACTION;
--
-- -- Drop triggers
-- DROP TRIGGER IF EXISTS update_blog_configurations_timestamp;
-- DROP TRIGGER IF EXISTS update_blog_articles_queue_timestamp;
-- DROP TRIGGER IF EXISTS update_blog_internal_links_timestamp;
--
-- -- Drop tables (order matters due to foreign keys)
-- DROP TABLE IF EXISTS blog_article_tags;
-- DROP TABLE IF EXISTS blog_article_categories;
-- DROP TABLE IF EXISTS blog_internal_links;
-- DROP TABLE IF EXISTS blog_articles_queue;
-- DROP TABLE IF EXISTS blog_configurations;
--
-- COMMIT;

-- ============================================================
-- Verification Queries (for testing)
-- ============================================================
-- Run these after migration to verify schema:
--
-- -- List all WordPress blog tables
-- SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'blog_%';
--
-- -- Check blog_configurations schema
-- PRAGMA table_info(blog_configurations);
--
-- -- Check blog_articles_queue schema
-- PRAGMA table_info(blog_articles_queue);
--
-- -- Check indexes
-- SELECT name, tbl_name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_blog_%';
--
-- -- Check triggers
-- SELECT name, tbl_name FROM sqlite_master WHERE type='trigger' AND name LIKE '%blog%';
--
-- -- Test constraint checks
-- INSERT INTO blog_configurations (config_name, website_url, number_of_chapters, wordpress_api_url, wordpress_api_key_encrypted, openai_api_key_encrypted)
-- VALUES ('Test Config', 'https://example.com', 5, 'https://example.com/wp-json', 'encrypted_key_1', 'encrypted_key_2');
--
-- -- Verify foreign key constraints
-- PRAGMA foreign_keys = ON;
-- PRAGMA foreign_key_check;
