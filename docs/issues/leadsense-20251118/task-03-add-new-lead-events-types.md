# Task 3: Add New Lead Events Types

## Status: Concluído

## Data de Conclusão: 2025-11-18

## Objective
Document and implement support for new lead event types required by CRM functionality: `stage_changed`, `owner_changed`, `pipeline_changed`, `deal_updated`, and structured `note` events.

## Prerequisites
- Review existing lead_events table schema
- Understand current event types: 'detected', 'updated', 'qualified', 'notified', 'synced', 'note'
- Review LeadRepository implementation

## Overview

The existing `lead_events` table already supports extensible event types via CHECK constraint:
```sql
type TEXT NOT NULL CHECK(type IN ('detected', 'updated', 'qualified', 'notified', 'synced', 'note'))
```

**Decision:** For backward compatibility and flexibility, we'll **NOT modify the CHECK constraint**. Instead, we'll:
1. Document the new event types in code comments
2. Use application-level validation
3. Consider removing the CHECK constraint in future (allowing any event type)

## Migration Decision

### Option A: Update CHECK Constraint (Breaking)
```sql
-- db/migrations/044_update_lead_events_types.sql
-- Requires table recreation in SQLite

-- Would need to recreate table with expanded CHECK constraint
-- Complex and risky for existing data
```

### Option B: Remove CHECK Constraint (Recommended)
```sql
-- db/migrations/044_relax_lead_events_constraint.sql
-- Allow any event type for future extensibility

-- SQLite: Requires table recreation
CREATE TABLE lead_events_new (
    id TEXT PRIMARY KEY,
    lead_id TEXT NOT NULL,
    type TEXT NOT NULL,  -- No CHECK constraint
    payload_json TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

-- Copy data
INSERT INTO lead_events_new SELECT * FROM lead_events;

-- Replace table
DROP TABLE lead_events;
ALTER TABLE lead_events_new RENAME TO lead_events;

-- Recreate indexes
CREATE INDEX IF NOT EXISTS idx_lead_events_lead_id ON lead_events(lead_id);
CREATE INDEX IF NOT EXISTS idx_lead_events_type ON lead_events(type);
CREATE INDEX IF NOT EXISTS idx_lead_events_created_at ON lead_events(created_at);
```

### Option C: No Migration (Pragmatic - Recommended for MVP)
- Keep existing CHECK constraint as documentation
- Application code simply uses new types
- SQLite will accept them if constraint not enforced at runtime
- Document in code that CHECK constraint may not match all types

**Recommendation: Option C for MVP**, Option B for production release.

## New Event Types

### 1. `stage_changed`
When a lead moves between pipeline stages (Kanban drag-and-drop).

**Payload Structure:**
```json
{
  "old_stage_id": "stage_lead_capture",
  "old_stage_name": "Lead Capture",
  "new_stage_id": "stage_support",
  "new_stage_name": "Support",
  "pipeline_id": "pipe_default",
  "changed_by": "admin_user_123",
  "changed_by_type": "admin_user",
  "changed_by_name": "Frank Wilson",
  "changed_at": "2025-01-18T10:30:00Z",
  "note": "Customer requested technical evaluation"
}
```

**When Generated:**
- API: `POST /admin-api.php?action=leadsense.crm.move_lead`
- Automatic: When lead progresses through qualification stages

### 2. `owner_changed`
When lead ownership is reassigned.

**Payload Structure:**
```json
{
  "old_owner_id": "admin_user_123",
  "old_owner_type": "admin_user",
  "old_owner_name": "Frank Wilson",
  "new_owner_id": "admin_user_456",
  "new_owner_type": "admin_user",
  "new_owner_name": "Sarah Johnson",
  "changed_by": "admin_user_789",
  "changed_by_type": "admin_user",
  "changed_at": "2025-01-18T10:30:00Z",
  "reason": "Reassigned for specialized expertise"
}
```

**When Generated:**
- API: `POST /admin-api.php?action=leadsense.crm.update_lead_inline`
- Automatic: Round-robin assignment, territory rules

### 3. `pipeline_changed`
When a lead is moved to a different pipeline (less common).

**Payload Structure:**
```json
{
  "old_pipeline_id": "pipe_default",
  "old_pipeline_name": "Default",
  "new_pipeline_id": "pipe_enterprise",
  "new_pipeline_name": "Enterprise Sales",
  "new_stage_id": "stage_discovery",
  "new_stage_name": "Discovery",
  "changed_by": "admin_user_123",
  "changed_by_type": "admin_user",
  "changed_at": "2025-01-18T10:30:00Z",
  "reason": "Qualified for enterprise track"
}
```

### 4. `deal_updated`
When deal value, probability, or close date changes.

**Payload Structure:**
```json
{
  "changes": {
    "deal_value": {
      "old": 5000.00,
      "new": 10000.00
    },
    "probability": {
      "old": 30,
      "new": 70
    },
    "expected_close_date": {
      "old": "2025-02-01",
      "new": "2025-01-25"
    },
    "currency": {
      "old": "USD",
      "new": "USD"
    }
  },
  "changed_by": "admin_user_123",
  "changed_by_type": "admin_user",
  "changed_at": "2025-01-18T10:30:00Z",
  "note": "Customer committed to faster deployment"
}
```

### 5. Enhanced `note` Event
User-added notes from CRM interface.

**Payload Structure:**
```json
{
  "text": "Customer asked for a follow-up demo next week. Interested in API integration.",
  "author_id": "admin_user_123",
  "author_type": "admin_user",
  "author_name": "Frank Wilson",
  "created_at": "2025-01-18T10:30:00Z",
  "context": {
    "source": "crm_board",
    "stage_id": "stage_support",
    "stage_name": "Support"
  }
}
```

## Implementation in Code

### Update LeadRepository

**File: `includes/LeadSense/LeadRepository.php`**

Add methods for creating structured events:

```php
/**
 * Record a stage change event
 */
public function recordStageChange($leadId, $oldStage, $newStage, $changedBy) {
    $payload = [
        'old_stage_id' => $oldStage['id'],
        'old_stage_name' => $oldStage['name'],
        'new_stage_id' => $newStage['id'],
        'new_stage_name' => $newStage['name'],
        'pipeline_id' => $newStage['pipeline_id'],
        'changed_by' => $changedBy['id'],
        'changed_by_type' => $changedBy['type'],
        'changed_by_name' => $changedBy['name'] ?? null,
        'changed_at' => date('c')
    ];
    
    return $this->addEvent($leadId, 'stage_changed', $payload);
}

/**
 * Record an owner change event
 */
public function recordOwnerChange($leadId, $oldOwner, $newOwner, $changedBy) {
    $payload = [
        'old_owner_id' => $oldOwner['id'] ?? null,
        'old_owner_type' => $oldOwner['type'] ?? null,
        'old_owner_name' => $oldOwner['name'] ?? null,
        'new_owner_id' => $newOwner['id'],
        'new_owner_type' => $newOwner['type'],
        'new_owner_name' => $newOwner['name'] ?? null,
        'changed_by' => $changedBy['id'],
        'changed_by_type' => $changedBy['type'],
        'changed_at' => date('c')
    ];
    
    return $this->addEvent($leadId, 'owner_changed', $payload);
}

/**
 * Record a deal update event
 */
public function recordDealUpdate($leadId, $changes, $changedBy) {
    $payload = [
        'changes' => $changes,
        'changed_by' => $changedBy['id'],
        'changed_by_type' => $changedBy['type'],
        'changed_at' => date('c')
    ];
    
    return $this->addEvent($leadId, 'deal_updated', $payload);
}

/**
 * Add a note to lead
 */
public function addNote($leadId, $text, $author, $context = []) {
    $payload = [
        'text' => $text,
        'author_id' => $author['id'],
        'author_type' => $author['type'],
        'author_name' => $author['name'] ?? null,
        'created_at' => date('c'),
        'context' => $context
    ];
    
    return $this->addEvent($leadId, 'note', $payload);
}
```

## Event Constants (Optional)

Create event type constants for consistency:

**File: `includes/LeadSense/LeadEventTypes.php`** (new)

```php
<?php
/**
 * Lead Event Types Constants
 */
class LeadEventTypes {
    // Existing types
    const DETECTED = 'detected';
    const UPDATED = 'updated';
    const QUALIFIED = 'qualified';
    const NOTIFIED = 'notified';
    const SYNCED = 'synced';
    
    // CRM-specific types
    const STAGE_CHANGED = 'stage_changed';
    const OWNER_CHANGED = 'owner_changed';
    const PIPELINE_CHANGED = 'pipeline_changed';
    const DEAL_UPDATED = 'deal_updated';
    const NOTE = 'note';
    
    /**
     * Get all valid event types
     */
    public static function all() {
        return [
            self::DETECTED,
            self::UPDATED,
            self::QUALIFIED,
            self::NOTIFIED,
            self::SYNCED,
            self::STAGE_CHANGED,
            self::OWNER_CHANGED,
            self::PIPELINE_CHANGED,
            self::DEAL_UPDATED,
            self::NOTE
        ];
    }
    
    /**
     * Check if event type is valid
     */
    public static function isValid($type) {
        return in_array($type, self::all(), true);
    }
}
```

## Timeline Rendering

For Admin UI timeline display:

```javascript
// public/admin/js/lead-timeline.js
function formatLeadEvent(event) {
    const payload = JSON.parse(event.payload_json || '{}');
    
    switch(event.type) {
        case 'stage_changed':
            return {
                icon: '🔄',
                title: 'Stage Changed',
                description: `Moved from ${payload.old_stage_name} to ${payload.new_stage_name}`,
                actor: payload.changed_by_name,
                timestamp: event.created_at
            };
            
        case 'owner_changed':
            return {
                icon: '👤',
                title: 'Owner Changed',
                description: `Reassigned from ${payload.old_owner_name || 'Unassigned'} to ${payload.new_owner_name}`,
                actor: payload.changed_by_name,
                timestamp: event.created_at
            };
            
        case 'deal_updated':
            const changes = Object.keys(payload.changes).map(k => {
                const c = payload.changes[k];
                return `${k}: ${c.old} → ${c.new}`;
            }).join(', ');
            return {
                icon: '💰',
                title: 'Deal Updated',
                description: changes,
                actor: payload.changed_by_name,
                timestamp: event.created_at
            };
            
        case 'note':
            return {
                icon: '📝',
                title: 'Note Added',
                description: payload.text,
                actor: payload.author_name,
                timestamp: event.created_at
            };
            
        // ... other types
    }
}
```

## Testing Checklist

- [ ] Document all new event types
- [ ] Add LeadEventTypes constants class
- [ ] Update LeadRepository with event recording methods
- [ ] Test event creation in database
- [ ] Verify payload JSON structure
- [ ] Test timeline rendering in UI
- [ ] Ensure backward compatibility with existing events
- [ ] Add unit tests for event recording

## Testing Examples

```php
// tests/test_lead_events_crm.php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/LeadSense/LeadRepository.php';

$leadRepo = new LeadRepository($config['leadsense']);

// Test stage change event
$oldStage = ['id' => 'stage_1', 'name' => 'Lead Capture', 'pipeline_id' => 'pipe_1'];
$newStage = ['id' => 'stage_2', 'name' => 'Support', 'pipeline_id' => 'pipe_1'];
$changedBy = ['id' => 'admin_1', 'type' => 'admin_user', 'name' => 'Frank'];

$leadRepo->recordStageChange('lead_123', $oldStage, $newStage, $changedBy);

// Verify event created
$events = $leadRepo->getLeadEvents('lead_123');
$lastEvent = end($events);
assert($lastEvent['type'] === 'stage_changed');
echo "✓ PASS: Stage change event recorded\n";
```

## Related Tasks
- Task 2: Extend leads table (provides data for events)
- Task 6: CRM Lead Management Service (uses event recording)
- Task 14: Lead detail drawer (displays timeline)

## References
- Spec Section 2.3.2: lead_events new types
- `db/migrations/018_create_leadsense_tables.sql`
- `includes/LeadSense/LeadRepository.php`

## Implementação Realizada

### Arquivos Criados

1. ✅ **`includes/LeadSense/LeadEventTypes.php`** - Classe de constantes para tipos de eventos
   - Define 10 tipos de eventos (6 existentes + 4 novos CRM)
   - Métodos de validação: `isValid()`, `all()`, `getCRM()`, `getExisting()`
   - Helpers para UI: `getLabel()`, `getIcon()`
   - Facilita manutenção e consistência dos tipos de eventos

2. ✅ **`db/migrations/044_relax_lead_events_constraint.sql`** - Migração para remover restrição CHECK
   - Remove CHECK constraint que limitava tipos de eventos
   - Permite extensibilidade futura
   - Mantém backward compatibility (dados existentes preservados)
   - Recria índices corretamente

3. ✅ **`tests/test_lead_events_crm.php`** - Suite completa de testes
   - 12 grupos de testes cobrindo todas as funcionalidades
   - Testa classe LeadEventTypes (constantes, validação, labels)
   - Testa todos os novos métodos de gravação de eventos
   - Verifica estrutura de payload JSON
   - Testa backward compatibility
   - 22 assertions passando

### Arquivos Modificados

1. ✅ **`includes/LeadSense/LeadRepository.php`** - Métodos estendidos
   - `recordStageChange()` - Grava mudanças de estágio
   - `recordOwnerChange()` - Grava mudanças de proprietário
   - `recordPipelineChange()` - Grava mudanças de pipeline
   - `recordDealUpdate()` - Grava atualizações de deal/oportunidade
   - `addNote()` - Adiciona notas com contexto estruturado
   - `getLeadEvents()` - Busca eventos com filtro opcional por tipo
   - Fix: Corrigido warning de undefined key 'qualified'
   - Total: ~180 linhas de código adicionadas

### Testes Realizados

✅ **Suite de testes CRM (test_lead_events_crm.php):**
- Test 1: LeadEventTypes - Constantes (2 checks) ✓
- Test 2: LeadEventTypes - Validação (3 checks) ✓
- Test 3: LeadEventTypes - Labels e Ícones (2 checks) ✓
- Test 4: Criação de lead de teste (1 check) ✓
- Test 5: Record Stage Change Event (2 checks) ✓
- Test 6: Record Owner Change Event (2 checks) ✓
- Test 7: Record Pipeline Change Event (2 checks) ✓
- Test 8: Record Deal Update Event (2 checks) ✓
- Test 9: Add Note with Context (2 checks) ✓
- Test 10: Get Events with Type Filtering (2 checks) ✓
- Test 11: Verify Event Timestamps (1 check) ✓
- Test 12: Backward Compatibility (2 checks) ✓

**Total: 22/22 testes passando**

✅ **Suite de testes do repositório (run_tests.php):**
- Todos os 28 testes existentes continuam passando
- Nenhuma regressão introduzida

### Estruturas de Payload Implementadas

**1. stage_changed:**
```json
{
  "old_stage_id": "stage_lead_capture",
  "old_stage_name": "Lead Capture",
  "new_stage_id": "stage_support",
  "new_stage_name": "Support",
  "pipeline_id": "pipe_default",
  "changed_by": "admin_user_123",
  "changed_by_type": "admin_user",
  "changed_by_name": "Test Admin",
  "changed_at": "2025-11-18T11:30:00+00:00",
  "note": "Optional note"
}
```

**2. owner_changed:**
```json
{
  "old_owner_id": "admin_user_123",
  "old_owner_type": "admin_user",
  "old_owner_name": "Frank Wilson",
  "new_owner_id": "admin_user_456",
  "new_owner_type": "admin_user",
  "new_owner_name": "Sarah Johnson",
  "changed_by": "admin_user_789",
  "changed_by_type": "admin_user",
  "changed_by_name": "Manager",
  "changed_at": "2025-11-18T11:30:00+00:00",
  "reason": "Optional reason"
}
```

**3. pipeline_changed:**
```json
{
  "old_pipeline_id": "pipe_default",
  "old_pipeline_name": "Default",
  "new_pipeline_id": "pipe_enterprise",
  "new_pipeline_name": "Enterprise Sales",
  "new_stage_id": "stage_discovery",
  "new_stage_name": "Discovery",
  "changed_by": "admin_user_123",
  "changed_by_type": "admin_user",
  "changed_by_name": "Test Admin",
  "changed_at": "2025-11-18T11:30:00+00:00",
  "reason": "Optional reason"
}
```

**4. deal_updated:**
```json
{
  "changes": {
    "deal_value": {"old": 5000.00, "new": 10000.00},
    "probability": {"old": 30, "new": 70},
    "expected_close_date": {"old": "2025-02-01", "new": "2025-01-25"}
  },
  "changed_by": "admin_user_123",
  "changed_by_type": "admin_user",
  "changed_by_name": "Test Admin",
  "changed_at": "2025-11-18T11:30:00+00:00",
  "note": "Optional note"
}
```

**5. note (enhanced):**
```json
{
  "text": "Customer asked for follow-up demo",
  "author_id": "admin_user_123",
  "author_type": "admin_user",
  "author_name": "Test Admin",
  "created_at": "2025-11-18T11:30:00+00:00",
  "context": {
    "source": "crm_board",
    "stage_id": "stage_support",
    "stage_name": "Support"
  }
}
```

### Características da Implementação

✅ **Backward Compatibility:**
- Métodos existentes (`addEvent()`, `getEvents()`) continuam funcionando
- Eventos antigos preservados após migração
- Nenhuma quebra em funcionalidade existente

✅ **Extensibilidade:**
- CHECK constraint removido permite novos tipos no futuro
- Validação feita em application-level via `LeadEventTypes::isValid()`
- Estrutura de payload JSON flexível

✅ **Qualidade de Código:**
- Type hints em todos os métodos
- Documentação PHPDoc completa
- Parâmetros opcionais com defaults sensatos
- Tratamento de null values apropriado

✅ **Preparação para UI:**
- Labels e ícones pré-definidos para renderização
- Estrutura de payload consistente para parsing
- Timestamps em formato ISO 8601

### Próximos Passos (Task 4)

Esta implementação prepara o terreno para:
- Task 4: Seeding de pipeline default
- Task 5: PipelineService (usará `recordStageChange`)
- Task 6: LeadManagementService (usará todos os métodos)
- Task 14: Lead detail drawer (renderizará timeline com eventos)

## Commits Relacionados

- Criação de LeadEventTypes.php com constantes e helpers
- Extensão de LeadRepository com métodos CRM
- Criação de migração 044 para remover CHECK constraint
- Adição de suite completa de testes
- Fix de warning undefined key 'qualified'
- Atualização do status da task
