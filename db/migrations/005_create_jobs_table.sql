-- Jobs table for background job processing
CREATE TABLE IF NOT EXISTS jobs (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'running', 'completed', 'failed')),
    available_at DATETIME NOT NULL,
    locked_by TEXT,
    locked_at DATETIME,
    result_json TEXT,
    error_text TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Index for job queue polling
CREATE INDEX IF NOT EXISTS idx_jobs_status_available ON jobs(status, available_at);

-- Index for locked jobs
CREATE INDEX IF NOT EXISTS idx_jobs_locked_by ON jobs(locked_by);

-- Index for job type
CREATE INDEX IF NOT EXISTS idx_jobs_type ON jobs(type);

-- Index for created_at (for cleanup and monitoring)
CREATE INDEX IF NOT EXISTS idx_jobs_created_at ON jobs(created_at);
