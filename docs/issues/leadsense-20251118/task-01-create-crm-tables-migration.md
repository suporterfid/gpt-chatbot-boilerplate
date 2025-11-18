# Task 1: Create Database Migration for CRM Tables

## Status: Concluído

## Data de Conclusão: 2025-11-18

## Objective
Create SQL migration files for the new CRM tables: `crm_pipelines`, `crm_pipeline_stages`, `crm_lead_assignments`, `crm_automation_rules`, and `crm_automation_logs`.

## Prerequisites
- Understanding of existing migration structure in `db/migrations/`
- Review migration 018 (LeadSense tables) for consistency
- Understand SQLite/MySQL/PostgreSQL compatibility requirements

## Files to Create

### 1. `db/migrations/038_create_crm_pipelines.sql`

```sql
-- LeadSense CRM: Pipelines table
-- Represents CRM boards/pipelines for organizing leads

CREATE TABLE IF NOT EXISTS crm_pipelines (
    id TEXT PRIMARY KEY,                    -- UUID
    client_id TEXT NULL,                    -- tenant/client (multi-tenancy)
    name TEXT NOT NULL,
    description TEXT NULL,
    is_default INTEGER NOT NULL DEFAULT 0,  -- boolean (0/1)
    color TEXT NULL,                        -- hex color or Tailwind token
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    archived_at TEXT NULL
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_pipelines_client_default 
    ON crm_pipelines (client_id, is_default);

CREATE INDEX IF NOT EXISTS idx_crm_pipelines_archived 
    ON crm_pipelines (archived_at);
```

### 2. `db/migrations/039_create_crm_pipeline_stages.sql`

```sql
-- LeadSense CRM: Pipeline Stages table
-- Represents columns/stages within a pipeline (Kanban columns)

CREATE TABLE IF NOT EXISTS crm_pipeline_stages (
    id TEXT PRIMARY KEY,                    -- UUID
    pipeline_id TEXT NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,                     -- unique per pipeline
    position INTEGER NOT NULL DEFAULT 0,    -- column ordering
    color TEXT NULL,                        -- stage color (header)
    is_won INTEGER NOT NULL DEFAULT 0,      -- marks "Closed Won" type stages
    is_lost INTEGER NOT NULL DEFAULT 0,     -- marks "Closed Lost" type stages
    is_closed INTEGER NOT NULL DEFAULT 0,   -- generic "closed" indicator
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    archived_at TEXT NULL,
    
    FOREIGN KEY (pipeline_id) REFERENCES crm_pipelines(id) ON DELETE CASCADE
);

-- Indexes
CREATE UNIQUE INDEX IF NOT EXISTS idx_crm_stages_pipeline_slug
    ON crm_pipeline_stages (pipeline_id, slug);

CREATE INDEX IF NOT EXISTS idx_crm_stages_pipeline_position
    ON crm_pipeline_stages (pipeline_id, position);

CREATE INDEX IF NOT EXISTS idx_crm_stages_archived
    ON crm_pipeline_stages (archived_at);
```

### 3. `db/migrations/040_create_crm_lead_assignments.sql`

```sql
-- LeadSense CRM: Lead Assignments table
-- Tracks historical ownership assignments

CREATE TABLE IF NOT EXISTS crm_lead_assignments (
    id TEXT PRIMARY KEY,                    -- UUID
    lead_id TEXT NOT NULL,
    owner_id TEXT NOT NULL,                 -- admin user id or agent id
    owner_type TEXT NOT NULL,               -- 'admin_user', 'agent', 'external'
    assigned_by TEXT NULL,                  -- who made the assignment
    note TEXT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    ended_at TEXT NULL,
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_lead_assignments_lead
    ON crm_lead_assignments (lead_id);

CREATE INDEX IF NOT EXISTS idx_crm_lead_assignments_owner
    ON crm_lead_assignments (owner_id, owner_type);

CREATE INDEX IF NOT EXISTS idx_crm_lead_assignments_active
    ON crm_lead_assignments (lead_id, ended_at);
```

### 4. `db/migrations/041_create_crm_automation_rules.sql`

```sql
-- LeadSense CRM: Automation Rules table
-- Stores event-driven automation rules

CREATE TABLE IF NOT EXISTS crm_automation_rules (
    id TEXT PRIMARY KEY,                    -- UUID
    client_id TEXT NULL,                    -- tenant/client
    name TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,   -- boolean
    -- Trigger configuration
    trigger_event TEXT NOT NULL,            -- 'lead.created', 'lead.stage_changed', etc.
    trigger_filter TEXT NULL,               -- JSON: conditions for triggering
    -- Action configuration
    action_type TEXT NOT NULL,              -- 'webhook', 'slack', 'email', 'whatsapp'
    action_config TEXT NOT NULL,            -- JSON: action parameters
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    archived_at TEXT NULL
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_automation_client_active
    ON crm_automation_rules (client_id, is_active, trigger_event);

CREATE INDEX IF NOT EXISTS idx_crm_automation_trigger
    ON crm_automation_rules (trigger_event, is_active);
```

### 5. `db/migrations/042_create_crm_automation_logs.sql`

```sql
-- LeadSense CRM: Automation Logs table
-- Tracks execution of automation rules

CREATE TABLE IF NOT EXISTS crm_automation_logs (
    id TEXT PRIMARY KEY,                    -- UUID
    rule_id TEXT NOT NULL,
    lead_id TEXT NULL,
    event_type TEXT NOT NULL,
    status TEXT NOT NULL,                   -- 'success', 'error', 'skipped'
    message TEXT NULL,
    payload_json TEXT NULL,                 -- snapshot of event data
    created_at TEXT DEFAULT (datetime('now')),
    
    FOREIGN KEY (rule_id) REFERENCES crm_automation_rules(id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_crm_automation_logs_rule
    ON crm_automation_logs (rule_id, created_at);

CREATE INDEX IF NOT EXISTS idx_crm_automation_logs_lead
    ON crm_automation_logs (lead_id, created_at);

CREATE INDEX IF NOT EXISTS idx_crm_automation_logs_status
    ON crm_automation_logs (status, created_at);
```

## Implementation Steps

1. **Create migration files**
   - Follow sequential numbering (038-042)
   - Use existing migration format
   - Ensure SQLite compatibility (TEXT for UUIDs, INTEGER for booleans)

2. **Test migrations**
   ```bash
   php scripts/run_migrations.php
   ```

3. **Verify tables created**
   ```bash
   sqlite3 data/chatbot.db ".schema crm_pipelines"
   sqlite3 data/chatbot.db ".schema crm_pipeline_stages"
   sqlite3 data/chatbot.db ".schema crm_lead_assignments"
   sqlite3 data/chatbot.db ".schema crm_automation_rules"
   sqlite3 data/chatbot.db ".schema crm_automation_logs"
   ```

## Database Design Notes

### Field Naming
- Use TEXT for IDs (UUID strings) - SQLite compatible
- Use INTEGER for booleans (0/1) - SQLite compatible
- Use TEXT for timestamps with `datetime('now')` default
- Use TEXT for JSON fields (SQLite 3.38+ has json functions)

### Foreign Keys
- CASCADE on DELETE for child records
- Use TEXT references (not BINARY/CHAR)

### Indexes
- Composite indexes for common queries
- Single column indexes for FK relationships
- Consider archived_at in queries

## Testing Checklist

- [ ] All migration files created
- [ ] Migrations run successfully
- [ ] Tables created with correct schema
- [ ] Indexes created
- [ ] Foreign keys work (test cascade delete)
- [ ] Default values work correctly
- [ ] No SQL syntax errors

## Related Tasks
- Task 2: Extend leads table with CRM fields
- Task 4: Create default pipeline seeding script

## References
- `db/migrations/018_create_leadsense_tables.sql` - Existing LeadSense tables
- Spec Section 2.2: New Entities

## Implementação Realizada

### Arquivos Criados
1. ✅ `db/migrations/038_create_crm_pipelines.sql` - Tabela de pipelines CRM
2. ✅ `db/migrations/039_create_crm_pipeline_stages.sql` - Tabela de estágios de pipeline
3. ✅ `db/migrations/040_create_crm_lead_assignments.sql` - Tabela de atribuições de leads
4. ✅ `db/migrations/041_create_crm_automation_rules.sql` - Tabela de regras de automação
5. ✅ `db/migrations/042_create_crm_automation_logs.sql` - Tabela de logs de automação

### Testes Realizados
- ✅ Migrations executadas com sucesso (todos os 5 arquivos)
- ✅ Schema das tabelas verificado corretamente
- ✅ Índices criados conforme especificação
- ✅ Foreign keys funcionando (testado cascade delete)
- ✅ Suite de testes do repositório passou (28 testes)

### Verificações de Integridade
1. **Tabelas criadas:** Todas as 5 tabelas CRM foram criadas corretamente
2. **Índices:** 13 índices criados para otimização de queries
3. **Foreign Keys:** 
   - `crm_pipeline_stages.pipeline_id` → `crm_pipelines.id` (CASCADE)
   - `crm_lead_assignments.lead_id` → `leads.id` (CASCADE)
   - `crm_automation_logs.rule_id` → `crm_automation_rules.id` (CASCADE)
4. **Cascade Delete:** Testado e funcionando corretamente

### Detalhes Técnicos
- Compatibilidade: SQLite (com suporte futuro para MySQL/PostgreSQL)
- Tipo de dados: TEXT para UUIDs, INTEGER para booleans
- Timestamps: TEXT com `datetime('now')` default
- JSON fields: TEXT para armazenar configurações

## Commits Relacionados
- Criação inicial das 5 migrations CRM
- Execução e validação das migrations
- Atualização do status da task
