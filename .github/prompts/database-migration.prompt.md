---
name: Database Migration
description: Workflow para criar, testar e deployar migrations de banco de dados
mode: dba
model: gpt-4o
temperature: 0.2
tools:
  - view
  - create
  - edit
  - bash
---

# Prompt: Database Migration

Workflow completo para criar e deployar database migrations de forma segura e testada.

## Objetivo

Criar migrations que modificam o schema do banco de dados mantendo compatibilidade, integridade e performance.

## Inputs Necessários

- **Change Description**: [O que precisa ser mudado no DB]
- **Reason**: [Por que esta mudança é necessária]
- **Impact**: [Quais serviços/features são afetados]

## Steps

### Step 1: Análise e Planejamento

**Ação**: Entender o que precisa ser mudado e planejar a migration

**Perguntas**:
1. **Tipo de mudança**:
   - [ ] Nova tabela
   - [ ] Adicionar coluna(s)
   - [ ] Modificar coluna(s)
   - [ ] Remover coluna(s)
   - [ ] Adicionar índice(s)
   - [ ] Adicionar foreign key(s)
   - [ ] Modificar constraints

2. **Impacto**:
   - Quais serviços usam esta tabela?
   - Há dados existentes afetados?
   - É backward compatible?
   - Requer downtime?

3. **Multi-tenancy**:
   - Precisa de `tenant_id`?
   - Afeta isolamento de tenants?

4. **Performance**:
   - Novos índices necessários?
   - Query performance impactada?
   - Tabela grande? (slow migration)

**Validação**:
- [ ] Requisitos claros
- [ ] Impactos identificados
- [ ] Estratégia definida

---

### Step 2: Determinar Número da Migration

**Ação**: Encontrar próximo número sequencial

**Comandos**:
```bash
# Ver última migration
ls -1 db/migrations/ | tail -1

# Exemplo output: 017_create_audit_artifacts.sql
# Próxima seria: 018_your_migration_name.sql
```

**Nomenclatura**:
```
NNN_action_object.sql

Onde:
- NNN = número sequencial (001, 002, ..., 018)
- action = create, add, modify, drop, create_index
- object = table name ou descrição

Exemplos:
- 018_create_features_table.sql
- 019_add_status_to_agents.sql
- 020_add_index_on_messages_created_at.sql
- 021_modify_prompts_content_type.sql
```

**Validação**:
- [ ] Número sequencial correto
- [ ] Nome descritivo e claro

---

### Step 3: Escrever SQL da Migration

**Ação**: Criar arquivo SQL com schema changes

### Caso 1: Nova Tabela

```sql
-- Migration: 018_create_features_table.sql
-- Purpose: Add feature management system
-- Created: 2024-01-15
-- Affects: New FeatureService, Admin UI features section

-- ============================================================
-- UP Migration
-- ============================================================

CREATE TABLE IF NOT EXISTS features (
    -- Primary key
    id VARCHAR(50) PRIMARY KEY,
    
    -- Multi-tenancy
    tenant_id VARCHAR(36),
    
    -- Feature data
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'active',
    config TEXT, -- JSON config
    
    -- Metadata
    created_by VARCHAR(50),
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_features_tenant ON features(tenant_id);
CREATE INDEX IF NOT EXISTS idx_features_status ON features(status);
CREATE INDEX IF NOT EXISTS idx_features_created ON features(created_at);

-- Full-text search index (if needed)
-- CREATE INDEX IF NOT EXISTS idx_features_name_search ON features(name);

-- ============================================================
-- DOWN Migration (Rollback)
-- ============================================================

-- Uncomment to enable rollback
-- DROP TABLE IF EXISTS features;
```

### Caso 2: Adicionar Coluna

```sql
-- Migration: 019_add_priority_to_jobs.sql
-- Purpose: Add priority field for job queue prioritization
-- Created: 2024-01-16
-- Affects: JobQueue service, worker script

-- ============================================================
-- UP Migration
-- ============================================================

-- Add priority column (default to normal priority = 5)
ALTER TABLE jobs ADD COLUMN priority INTEGER DEFAULT 5;

-- Add index for job processing order
CREATE INDEX IF NOT EXISTS idx_jobs_priority ON jobs(priority DESC, created_at ASC);

-- Update existing jobs to normal priority (if needed)
-- UPDATE jobs SET priority = 5 WHERE priority IS NULL;

-- ============================================================
-- DOWN Migration (Rollback)
-- ============================================================

-- SQLite doesn't support DROP COLUMN easily
-- Would need to recreate table without the column
-- Or comment: Migration not easily reversible on SQLite

-- DROP INDEX IF EXISTS idx_jobs_priority;
-- ALTER TABLE jobs DROP COLUMN priority; -- MySQL only
```

### Caso 3: Modificar Coluna

```sql
-- Migration: 020_modify_agent_temperature_type.sql
-- Purpose: Change temperature from INTEGER to REAL for decimal support
-- Created: 2024-01-17
-- Affects: AgentService, agent creation/update

-- ============================================================
-- UP Migration
-- ============================================================

-- SQLite: Need to recreate table
-- Step 1: Create new table with correct schema
CREATE TABLE agents_new (
    id VARCHAR(50) PRIMARY KEY,
    tenant_id VARCHAR(36),
    name VARCHAR(255) NOT NULL,
    api_type VARCHAR(20) DEFAULT 'chat',
    model VARCHAR(100),
    temperature REAL DEFAULT 0.7,  -- Changed from INTEGER to REAL
    system_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Step 2: Copy data from old table
INSERT INTO agents_new 
SELECT id, tenant_id, name, api_type, model, 
       CAST(temperature AS REAL),  -- Convert to REAL
       system_message, created_at, updated_at
FROM agents;

-- Step 3: Drop old table
DROP TABLE agents;

-- Step 4: Rename new table
ALTER TABLE agents_new RENAME TO agents;

-- Step 5: Recreate indexes
CREATE INDEX IF NOT EXISTS idx_agents_tenant ON agents(tenant_id);
CREATE INDEX IF NOT EXISTS idx_agents_created ON agents(created_at);

-- ============================================================
-- DOWN Migration (Rollback)
-- ============================================================

-- Similar process but reverting REAL back to INTEGER
-- (Usually not needed, as this is a non-breaking change)
```

### Caso 4: Adicionar Índice

```sql
-- Migration: 021_add_indexes_for_performance.sql
-- Purpose: Add indexes to improve query performance
-- Created: 2024-01-18
-- Affects: All queries on these tables

-- ============================================================
-- UP Migration
-- ============================================================

-- Index for audit log queries by action
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log(action);

-- Index for audit log queries by user
CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log(user_id, created_at DESC);

-- Index for messages by conversation
CREATE INDEX IF NOT EXISTS idx_audit_messages_conversation 
ON audit_messages(conversation_id, created_at ASC);

-- Composite index for multi-tenant queries with status
CREATE INDEX IF NOT EXISTS idx_agents_tenant_status 
ON agents(tenant_id, status);

-- ============================================================
-- DOWN Migration (Rollback)
-- ============================================================

-- DROP INDEX IF EXISTS idx_audit_log_action;
-- DROP INDEX IF EXISTS idx_audit_log_user;
-- DROP INDEX IF EXISTS idx_audit_messages_conversation;
-- DROP INDEX IF EXISTS idx_agents_tenant_status;
```

**Checklist**:
- [ ] SQL syntax correto
- [ ] `IF NOT EXISTS` / `IF EXISTS` usado
- [ ] Compatível SQLite E MySQL (ou comentado diferenças)
- [ ] Timestamps incluídos
- [ ] Índices apropriados criados
- [ ] Foreign keys definidos
- [ ] Multi-tenancy considerado
- [ ] Defaults sensatos definidos
- [ ] Comments explicativos
- [ ] DOWN migration documentada

**Validação**:
- [ ] SQL valida sem erros
- [ ] Schema faz sentido
- [ ] Performance considerada

---

### Step 4: Testar a Migration

**Ação**: Executar migration em ambiente de teste

**Setup de Teste**:
```bash
# Backup database antes de testar
cp data/chatbot.db data/chatbot.db.backup

# Ou para MySQL
mysqldump -u user -p database > backup.sql
```

**Executar Migration**:
```bash
# Run migrations script
php scripts/run_migrations.php

# Output esperado:
# Running migrations...
# ✓ 001_create_agents.sql
# ✓ 002_create_prompts.sql
# ...
# ✓ 018_your_new_migration.sql
# All migrations completed successfully!
```

**Verificar Schema**:
```bash
# SQLite: Ver schema da tabela
sqlite3 data/chatbot.db ".schema table_name"

# MySQL: Ver schema
mysql -u user -p -e "DESCRIBE database.table_name"

# Ver todos os índices
sqlite3 data/chatbot.db ".indices table_name"
mysql -u user -p -e "SHOW INDEXES FROM database.table_name"
```

**Testar Operações CRUD**:
```sql
-- Insert test data
INSERT INTO features (id, tenant_id, name, description) 
VALUES ('test-1', 'tenant-1', 'Test Feature', 'Test description');

-- Query data
SELECT * FROM features WHERE tenant_id = 'tenant-1';

-- Update data
UPDATE features SET status = 'inactive' WHERE id = 'test-1';

-- Delete data
DELETE FROM features WHERE id = 'test-1';
```

**Testar Índices**:
```sql
-- SQLite: Explain query plan
EXPLAIN QUERY PLAN 
SELECT * FROM features WHERE tenant_id = 'tenant-1' AND status = 'active';

-- Should show: SEARCH using INDEX idx_features_tenant
-- Not: SCAN TABLE features (which is slow)
```

**Validação**:
- [ ] Migration executa sem erros
- [ ] Schema criado corretamente
- [ ] Índices presentes
- [ ] Foreign keys funcionam
- [ ] CRUD operations funcionam
- [ ] Query plan usa índices

**Se houver erro**:
```bash
# Restore backup
cp data/chatbot.db.backup data/chatbot.db

# Fix migration SQL
vim db/migrations/018_your_migration.sql

# Try again
php scripts/run_migrations.php
```

---

### Step 5: Atualizar Services PHP

**Ação**: Modificar serviços que usam o schema

**Se nova tabela**, criar novo service:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/TenantContext.php';

class FeatureService
{
    private DB $db;
    private TenantContext $tenantContext;
    
    public function __construct(DB $db, TenantContext $tenantContext)
    {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
    }
    
    public function create(array $data): string
    {
        $id = 'feat_' . bin2hex(random_bytes(16));
        $tenantId = $this->tenantContext->getCurrentTenantId();
        
        $this->db->execute(
            'INSERT INTO features (id, tenant_id, name, description, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$id, $tenantId, $data['name'], $data['description'] ?? null, date('Y-m-d H:i:s')]
        );
        
        return $id;
    }
    
    public function getById(string $id): ?array
    {
        $tenantId = $this->tenantContext->getCurrentTenantId();
        
        $result = $this->db->query(
            'SELECT * FROM features WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
        
        return $result[0] ?? null;
    }
    
    // ... more methods
}
```

**Se coluna adicionada**, atualizar service existente:
```php
// Before migration: AgentService
public function create(array $data): string
{
    $this->db->execute(
        'INSERT INTO agents (id, name, model, temperature) VALUES (?, ?, ?, ?)',
        [$id, $data['name'], $data['model'], $data['temperature']]
    );
}

// After migration (added 'priority' column):
public function create(array $data): string
{
    $this->db->execute(
        'INSERT INTO agents (id, name, model, temperature, priority) VALUES (?, ?, ?, ?, ?)',
        [$id, $data['name'], $data['model'], $data['temperature'], $data['priority'] ?? 5]
    );
}
```

**Validação**:
- [ ] Services atualizados
- [ ] Queries usam novos campos
- [ ] Backward compatible (se possível)
- [ ] Type hints corretos

---

### Step 6: Criar/Atualizar Testes

**Ação**: Testar a migration e services atualizados

**Test para Migration**:
```php
<?php
// tests/test_features_migration.php

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/FeatureService.php';

class FeaturesMigrationTest
{
    public function run(): void
    {
        echo "Testing features migration...\n";
        
        $this->testTableExists();
        $this->testIndexesExist();
        $this->testCRUDOperations();
        $this->testMultiTenantIsolation();
        
        echo "All migration tests passed!\n";
    }
    
    private function testTableExists(): void
    {
        $db = new DB($config);
        
        // Check table exists
        $result = $db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='features'"
        );
        
        assert(!empty($result), 'Features table should exist');
        echo "✓ Table exists\n";
    }
    
    private function testIndexesExist(): void
    {
        $db = new DB($config);
        
        // Check indexes
        $result = $db->query("PRAGMA index_list('features')");
        
        $indexNames = array_column($result, 'name');
        assert(in_array('idx_features_tenant', $indexNames), 'Tenant index should exist');
        assert(in_array('idx_features_status', $indexNames), 'Status index should exist');
        
        echo "✓ Indexes exist\n";
    }
    
    private function testCRUDOperations(): void
    {
        $service = new FeatureService($db, $tenantContext);
        
        // Create
        $id = $service->create([
            'name' => 'Test Feature',
            'description' => 'Test description'
        ]);
        assert(!empty($id), 'Should create feature');
        
        // Read
        $feature = $service->getById($id);
        assert($feature !== null, 'Should read feature');
        assert($feature['name'] === 'Test Feature', 'Name should match');
        
        // Update
        $service->update($id, ['name' => 'Updated']);
        $updated = $service->getById($id);
        assert($updated['name'] === 'Updated', 'Should update feature');
        
        // Delete
        $service->delete($id);
        $deleted = $service->getById($id);
        assert($deleted === null, 'Should delete feature');
        
        echo "✓ CRUD operations work\n";
    }
    
    private function testMultiTenantIsolation(): void
    {
        // Test tenant isolation
        $tenantContext->setCurrentTenantId('tenant-a');
        $idA = $service->create(['name' => 'Feature A']);
        
        $tenantContext->setCurrentTenantId('tenant-b');
        $featureA = $service->getById($idA);
        
        assert($featureA === null, 'Tenant B should not see tenant A data');
        echo "✓ Multi-tenant isolation works\n";
    }
}

$test = new FeaturesMigrationTest();
$test->run();
```

**Validação**:
- [ ] Testes criados
- [ ] Todos testes passam
- [ ] Schema validado
- [ ] CRUD testado
- [ ] Multi-tenancy testado

---

### Step 7: Documentação

**Ação**: Documentar a migration e mudanças

**1. Comment no arquivo SQL** (já feito no Step 3)

**2. Update Schema Documentation**:
```markdown
<!-- docs/database-schema.md -->

## Features Table

Stores feature configurations for the feature management system.

**Schema**:
```sql
CREATE TABLE features (
    id VARCHAR(50) PRIMARY KEY,
    tenant_id VARCHAR(36),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Indexes**:
- `idx_features_tenant` - Multi-tenant filtering
- `idx_features_status` - Status-based queries
- `idx_features_created` - Date sorting

**Foreign Keys**:
- `tenant_id` → `tenants(id)` - Tenant ownership

**Used By**:
- `FeatureService.php` - CRUD operations
- `admin-api.php` - Feature management endpoints
```

**3. CHANGELOG.md**:
```markdown
## [Unreleased]

### Added
- Database migration 018: Created features table for feature management system
- FeatureService for managing features with full multi-tenant support
```

**4. Migration README** (se complexo):
```markdown
<!-- db/migrations/README.md -->

## Migration 018: Create Features Table

### Purpose
Add database schema for feature management system.

### What Changed
- Created `features` table
- Added indexes for performance
- Added foreign keys for referential integrity

### Impact
- New FeatureService can be used
- Admin API endpoints can manage features
- No impact on existing functionality

### Rollback
If needed, run:
```sql
DROP TABLE IF EXISTS features;
```

### Notes
- Multi-tenant enabled with tenant_id
- Fully backward compatible
```

**Validação**:
- [ ] SQL comentado
- [ ] Schema docs atualizados
- [ ] CHANGELOG atualizado
- [ ] README criado (se necessário)

---

### Step 8: Production Deployment Plan

**Ação**: Planejar deploy seguro em produção

**Pre-Deployment Checklist**:
- [ ] Migration testada localmente
- [ ] Testes passando
- [ ] Backup plan pronto
- [ ] Rollback plan pronto
- [ ] Downtime estimado (se houver)
- [ ] Team notificado

**Backup Strategy**:
```bash
# SQLite backup
cp data/chatbot.db "data/chatbot.db.backup.$(date +%Y%m%d_%H%M%S)"

# MySQL backup
mysqldump -u user -p database > "backup_$(date +%Y%m%d_%H%M%S).sql"
```

**Deployment Steps**:
```bash
# 1. Enter maintenance mode (if needed)
touch maintenance.flag

# 2. Backup database
./scripts/db_backup.sh

# 3. Run migration
php scripts/run_migrations.php

# 4. Verify migration
sqlite3 data/chatbot.db ".schema new_table"

# 5. Restart services (if needed)
docker-compose restart

# 6. Exit maintenance mode
rm maintenance.flag

# 7. Monitor logs
tail -f logs/app.log
```

**Rollback Plan**:
```bash
# If migration fails or causes issues:

# 1. Stop services
docker-compose down

# 2. Restore backup
cp data/chatbot.db.backup data/chatbot.db
# or
mysql -u user -p database < backup.sql

# 3. Restart services
docker-compose up -d

# 4. Notify team
```

**Validation**:
- [ ] Deployment plan documentado
- [ ] Backup strategy definida
- [ ] Rollback plan definido
- [ ] Team ciente do processo

---

### Step 9: Execute Migration in Production

**Ação**: Deployar a migration

**Execution**:
```bash
# Connect to production server
ssh user@production-server

# Navigate to app directory
cd /var/www/chatbot

# Pull latest code
git pull origin main

# Backup database
./scripts/db_backup.sh

# Run migration
php scripts/run_migrations.php

# Expected output:
# Running migrations...
# ✓ 001_create_agents.sql (already applied)
# ✓ 002_create_prompts.sql (already applied)
# ...
# ✓ 018_create_features.sql (applying...)
# ✓ 018_create_features.sql (completed)
# All migrations completed successfully!

# Verify
sqlite3 data/chatbot.db ".tables" | grep features

# Restart services if needed
docker-compose restart
```

**Post-Deployment Validation**:
```bash
# Check logs for errors
tail -100 logs/app.log | grep -i "error\|exception"

# Test API endpoints
curl -X POST http://localhost/admin-api.php?action=create_feature \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name": "Test Feature"}'

# Monitor for issues
watch -n 5 'tail -20 logs/app.log'
```

**Validation**:
- [ ] Migration executed successfully
- [ ] No errors in logs
- [ ] Services running normally
- [ ] API endpoints working
- [ ] Users not impacted

---

### Step 10: Monitor & Document

**Ação**: Monitorar sistema e documentar resultado

**Monitoring** (primeiras 24h):
- [ ] Error rate normal
- [ ] Performance stable
- [ ] No database errors
- [ ] Disk space OK
- [ ] Query performance OK

**Documentation**:
```markdown
## Migration 018 Deployment Report

**Date**: 2024-01-20 14:30 UTC
**Migration**: 018_create_features_table.sql
**Environment**: Production
**Downtime**: None
**Duration**: 3 seconds

### Changes
- Created features table
- Added 3 indexes
- Added foreign keys

### Results
- ✅ Migration successful
- ✅ No errors detected
- ✅ Performance stable
- ✅ Users not impacted

### Metrics
- Migration time: 3s
- Database size before: 42MB
- Database size after: 42MB
- Query performance: No degradation

### Issues
None reported.
```

**Validation**:
- [ ] System stable após 24h
- [ ] Deployment documentado
- [ ] Lessons learned capturados

---

## Success Criteria

Migration está completa quando:

- ✅ Migration file criado e testado
- ✅ Schema modificado corretamente
- ✅ Services atualizados
- ✅ Testes passando
- ✅ Documentação atualizada
- ✅ Deployed em produção
- ✅ Sistema estável
- ✅ Sem regressões

## Troubleshooting

### Migration falha ao executar
- Verificar syntax SQL
- Testar manualmente no sqlite3/mysql
- Verificar se tabela/coluna já existe
- Verificar permissões do arquivo

### Performance degradada após migration
- Verificar se índices foram criados
- Rodar ANALYZE (SQLite) ou OPTIMIZE (MySQL)
- Verificar query plans com EXPLAIN

### Rollback necessário
- Restaurar backup
- Remover entrada de migration do tracking
- Investigar causa raiz antes de tentar novamente

## Referências

- Migrations: `db/migrations/`
- Migration Runner: `scripts/run_migrations.php`
- Database Abstraction: `includes/DB.php`
- Schema Docs: `docs/database-schema.md`
