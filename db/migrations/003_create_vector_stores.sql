-- Migration: Create vector_stores and vector_store_files tables
-- Description: Tables for managing vector stores and their files

CREATE TABLE IF NOT EXISTS vector_stores (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    openai_store_id TEXT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'ready' CHECK(status IN ('ready','ingesting','error','deleted')),
    meta_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS vector_store_files (
    id TEXT PRIMARY KEY,
    vector_store_id TEXT NOT NULL,
    name TEXT NOT NULL,
    openai_file_id TEXT NULL,
    size INTEGER NULL,
    mime_type TEXT NULL,
    ingestion_status TEXT NOT NULL DEFAULT 'pending' CHECK(ingestion_status IN ('pending','in_progress','completed','failed','cancelled')),
    error_message TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (vector_store_id) REFERENCES vector_stores(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_vector_stores_name ON vector_stores(name);
CREATE INDEX IF NOT EXISTS idx_vector_stores_openai_id ON vector_stores(openai_store_id);
CREATE INDEX IF NOT EXISTS idx_vector_stores_status ON vector_stores(status);
CREATE INDEX IF NOT EXISTS idx_vector_store_files_store_id ON vector_store_files(vector_store_id);
CREATE INDEX IF NOT EXISTS idx_vector_store_files_openai_id ON vector_store_files(openai_file_id);
CREATE INDEX IF NOT EXISTS idx_vector_store_files_status ON vector_store_files(ingestion_status);
