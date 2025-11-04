-- Dead Letter Queue for Failed Jobs
-- Jobs that exceed max_attempts are moved here for manual inspection/retry

CREATE TABLE IF NOT EXISTS dead_letter_queue (
    id TEXT PRIMARY KEY,
    original_job_id TEXT NOT NULL,
    type TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 3,
    error_text TEXT,
    original_created_at TEXT NOT NULL,
    failed_at TEXT NOT NULL,
    requeued_at TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_dlq_type ON dead_letter_queue(type);
CREATE INDEX IF NOT EXISTS idx_dlq_failed_at ON dead_letter_queue(failed_at);
CREATE INDEX IF NOT EXISTS idx_dlq_requeued_at ON dead_letter_queue(requeued_at);
