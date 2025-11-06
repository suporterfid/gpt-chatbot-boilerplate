-- LeadSense Tables for Commercial Opportunity Detection

-- Leads table - stores detected commercial opportunities
CREATE TABLE IF NOT EXISTS leads (
    id TEXT PRIMARY KEY,
    agent_id TEXT,
    conversation_id TEXT NOT NULL,
    name TEXT,
    company TEXT,
    role TEXT,
    email TEXT,
    phone TEXT,
    industry TEXT,
    company_size TEXT,
    interest TEXT,
    intent_level TEXT CHECK(intent_level IN ('none', 'low', 'medium', 'high')) DEFAULT 'none',
    score INTEGER DEFAULT 0,
    qualified INTEGER DEFAULT 0,
    status TEXT CHECK(status IN ('new', 'open', 'won', 'lost', 'nurture')) DEFAULT 'new',
    source_channel TEXT DEFAULT 'web',
    extras_json TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    UNIQUE(agent_id, conversation_id)
);

CREATE INDEX IF NOT EXISTS idx_leads_agent_id ON leads(agent_id);
CREATE INDEX IF NOT EXISTS idx_leads_conversation_id ON leads(conversation_id);
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_leads_qualified ON leads(qualified);
CREATE INDEX IF NOT EXISTS idx_leads_created_at ON leads(created_at);

-- Lead Events table - audit trail of lead lifecycle
CREATE TABLE IF NOT EXISTS lead_events (
    id TEXT PRIMARY KEY,
    lead_id TEXT NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('detected', 'updated', 'qualified', 'notified', 'synced', 'note')),
    payload_json TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_lead_events_lead_id ON lead_events(lead_id);
CREATE INDEX IF NOT EXISTS idx_lead_events_type ON lead_events(type);
CREATE INDEX IF NOT EXISTS idx_lead_events_created_at ON lead_events(created_at);

-- Lead Scores table - snapshot of scoring history
CREATE TABLE IF NOT EXISTS lead_scores (
    id TEXT PRIMARY KEY,
    lead_id TEXT NOT NULL,
    score INTEGER NOT NULL,
    rationale_json TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_lead_scores_lead_id ON lead_scores(lead_id);
CREATE INDEX IF NOT EXISTS idx_lead_scores_created_at ON lead_scores(created_at);
