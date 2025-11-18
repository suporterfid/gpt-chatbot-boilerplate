# Task 2: Extend Leads Table with CRM Fields

## Status: Concluído

## Data de Conclusão: 2025-11-18

## Implementação Realizada

Migration 043_extend_leads_with_crm_fields.sql criada com sucesso, adicionando os seguintes campos CRM à tabela `leads`:

**Campos de Pipeline:**
- pipeline_id (TEXT NULL) - Referência ao pipeline CRM
- stage_id (TEXT NULL) - Referência ao estágio do pipeline

**Campos de Ownership:**
- owner_id (TEXT NULL) - ID do proprietário do lead
- owner_type (TEXT NULL) - Tipo de proprietário (admin_user, agent, external)

**Campos de Deal/Oportunidade:**
- deal_value (REAL NULL) - Valor da oportunidade
- currency (TEXT NULL) - Código da moeda (USD, BRL, etc.)
- probability (INTEGER NULL) - Probabilidade de ganho (0-100)
- expected_close_date (TEXT NULL) - Data esperada de fechamento

**Campos de Categorização:**
- tags (TEXT NULL) - Array JSON de tags

**Índices criados:**
- idx_leads_pipeline_stage - Para queries por pipeline e estágio
- idx_leads_owner - Para queries por proprietário
- idx_leads_deal_value - Para agregações de valor
- idx_leads_expected_close - Para queries por data de fechamento

### Testes Realizados:
✅ Migration executada com sucesso
✅ 9 novos campos adicionados à tabela leads
✅ 4 novos índices criados
✅ Backward compatibility mantida - leads existentes funcionam normalmente
✅ Teste de inserção com campos CRM - sucesso
✅ Teste de inserção sem campos CRM (legacy) - sucesso
✅ Suite de testes do repositório - 28/28 testes passando

## Commits Relacionados
- Criação do arquivo db/migrations/043_extend_leads_with_crm_fields.sql

## Objetivo
Create migration to add CRM-related fields to the existing `leads` table, enabling pipeline tracking, ownership, and deal management.

## Prerequisites
- Task 1 completed (CRM tables created)
- Review existing `leads` table schema
- Understand backward compatibility requirements

## File to Create

### `db/migrations/043_extend_leads_with_crm_fields.sql`

```sql
-- LeadSense CRM: Extend leads table with pipeline and deal fields
-- This migration adds CRM capabilities to existing LeadSense leads

-- Add pipeline and stage tracking
ALTER TABLE leads ADD COLUMN pipeline_id TEXT NULL;
ALTER TABLE leads ADD COLUMN stage_id TEXT NULL;

-- Add ownership tracking
ALTER TABLE leads ADD COLUMN owner_id TEXT NULL;
ALTER TABLE leads ADD COLUMN owner_type TEXT NULL;

-- Add deal/opportunity fields
ALTER TABLE leads ADD COLUMN deal_value REAL NULL;
ALTER TABLE leads ADD COLUMN currency TEXT NULL;
ALTER TABLE leads ADD COLUMN probability INTEGER NULL;
ALTER TABLE leads ADD COLUMN expected_close_date TEXT NULL;

-- Add tags (JSON array)
ALTER TABLE leads ADD COLUMN tags TEXT NULL;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_leads_pipeline_stage
    ON leads (pipeline_id, stage_id);

CREATE INDEX IF NOT EXISTS idx_leads_owner
    ON leads (owner_id, owner_type);

CREATE INDEX IF NOT EXISTS idx_leads_deal_value
    ON leads (deal_value);

CREATE INDEX IF NOT EXISTS idx_leads_expected_close
    ON leads (expected_close_date);

-- Note: Foreign key constraints added separately for compatibility
-- SQLite requires table recreation for FK constraints, which we'll handle in app logic
-- For MySQL/PostgreSQL, uncomment the following:
-- ALTER TABLE leads ADD CONSTRAINT fk_leads_pipeline
--     FOREIGN KEY (pipeline_id) REFERENCES crm_pipelines(id) ON DELETE SET NULL;
-- ALTER TABLE leads ADD CONSTRAINT fk_leads_stage
--     FOREIGN KEY (stage_id) REFERENCES crm_pipeline_stages(id) ON DELETE SET NULL;
```

## Implementation Steps

1. **Create migration file**
   - File: `db/migrations/043_extend_leads_with_crm_fields.sql`
   - Follow ALTER TABLE syntax for SQLite compatibility

2. **Test migration**
   ```bash
   # Backup database first
   cp data/chatbot.db data/chatbot.db.backup
   
   # Run migration
   php scripts/run_migrations.php
   ```

3. **Verify new columns**
   ```bash
   sqlite3 data/chatbot.db "PRAGMA table_info(leads);"
   ```

4. **Test with existing data**
   - Ensure existing leads still accessible
   - Verify NULL values for new columns
   - Test that queries still work

## New Field Specifications

### Pipeline Fields
- **pipeline_id**: TEXT NULL
  - References crm_pipelines.id
  - NULL allowed for legacy leads
  - Will be auto-assigned on first CRM interaction

- **stage_id**: TEXT NULL
  - References crm_pipeline_stages.id
  - Must belong to the assigned pipeline
  - NULL allowed for legacy leads

### Ownership Fields
- **owner_id**: TEXT NULL
  - Can reference admin_users.id, agents.id, or external system ID
  - NULL means unassigned

- **owner_type**: TEXT NULL
  - Values: 'admin_user', 'agent', 'external'
  - Indicates the type of owner_id reference

### Deal Fields
- **deal_value**: REAL NULL
  - Numeric value of opportunity
  - Stored as decimal (18,2 equivalent in app logic)
  - NULL means deal value not specified

- **currency**: TEXT NULL
  - ISO 4217 currency code (e.g., 'USD', 'BRL', 'EUR')
  - Default can be set in config
  - NULL uses system default

- **probability**: INTEGER NULL
  - Integer 0-100 representing win probability
  - NULL means not assessed
  - Can be used for weighted pipeline value calculations

- **expected_close_date**: TEXT NULL
  - Date in ISO 8601 format (YYYY-MM-DD)
  - Used for forecasting and follow-up scheduling
  - NULL means no target date

### Tags Field
- **tags**: TEXT NULL
  - JSON array of strings: `["hot", "enterprise", "pricing"]`
  - Enables flexible categorization
  - NULL or empty array `[]` means no tags
  - Use SQLite json functions for querying

## Backward Compatibility

### Existing Leads
- All new columns default to NULL
- Existing queries continue to work
- LeadSense detection still creates leads normally
- CRM fields populated only when using CRM features

### Migration Strategy
```php
// In LeadRepository or CRM service:
// Auto-assign to default pipeline on first access
if ($lead['pipeline_id'] === null) {
    $defaultPipeline = $this->getDefaultPipeline();
    $firstStage = $this->getFirstStage($defaultPipeline['id']);
    $this->assignLeadToPipeline($lead['id'], $defaultPipeline['id'], $firstStage['id']);
}
```

## Query Examples

### Find leads in specific stage
```sql
SELECT * FROM leads 
WHERE pipeline_id = ? AND stage_id = ?
ORDER BY created_at DESC;
```

### Get leads by owner
```sql
SELECT * FROM leads 
WHERE owner_id = ? AND owner_type = ?
AND status != 'won' AND status != 'lost';
```

### Pipeline value by stage
```sql
SELECT 
    stage_id,
    COUNT(*) as lead_count,
    SUM(deal_value) as total_value,
    SUM(deal_value * COALESCE(probability, 100) / 100.0) as weighted_value
FROM leads
WHERE pipeline_id = ?
AND deal_value IS NOT NULL
GROUP BY stage_id;
```

### Search by tags
```sql
-- Find leads with specific tag
SELECT * FROM leads
WHERE tags LIKE '%"hot"%';

-- Or using json_each (SQLite 3.38+)
SELECT l.* FROM leads l, json_each(l.tags) t
WHERE t.value = 'hot';
```

## Testing Checklist

- [ ] Migration file created
- [ ] Migration runs successfully
- [ ] All new columns added
- [ ] All indexes created
- [ ] Existing leads still queryable
- [ ] New fields accept NULL values
- [ ] Deal value accepts decimal numbers
- [ ] Tags field accepts JSON arrays
- [ ] Indexes improve query performance
- [ ] Backward compatibility maintained

## Rollback Plan

If migration fails or causes issues:

1. **Restore from backup**
   ```bash
   cp data/chatbot.db.backup data/chatbot.db
   ```

2. **Alternative: Drop columns** (SQLite limitation - requires table recreation)
   ```sql
   -- SQLite doesn't support DROP COLUMN directly
   -- Would need to recreate table without new columns
   -- Better to restore from backup
   ```

## Data Migration Script (Optional)

Create a script to backfill existing leads:

```php
// scripts/backfill_lead_crm_fields.php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

$db = Database::getInstance();

// Get default pipeline
$defaultPipeline = $db->query(
    "SELECT id FROM crm_pipelines WHERE is_default = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$defaultPipeline) {
    die("No default pipeline found. Run seeding script first.\n");
}

// Get first stage
$firstStage = $db->query(
    "SELECT id FROM crm_pipeline_stages 
     WHERE pipeline_id = ? 
     ORDER BY position ASC LIMIT 1",
    [$defaultPipeline['id']]
)->fetch(PDO::FETCH_ASSOC);

// Update leads without pipeline
$stmt = $db->prepare(
    "UPDATE leads 
     SET pipeline_id = ?, stage_id = ?
     WHERE pipeline_id IS NULL"
);
$stmt->execute([$defaultPipeline['id'], $firstStage['id']]);

echo "Backfilled " . $stmt->rowCount() . " leads\n";
```

## Related Tasks
- Task 1: Create CRM tables migration (prerequisite)
- Task 3: Add new lead_events types
- Task 4: Create default pipeline seeding

## References
- Spec Section 2.3: Changes to Existing Tables
- `db/migrations/018_create_leadsense_tables.sql`
