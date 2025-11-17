---
applyTo: "db/migrations/*.sql"
description: "Regras específicas para migrations e schema de banco de dados"
---

# Instruções para Database Migrations - gpt-chatbot-boilerplate

## Arquivos Alvo
- `db/migrations/*.sql` - Arquivos de migration SQL

## Filosofia de Migrations

### Princípios
- **Incremental**: Cada migration é um passo pequeno e atômico
- **Versionamento**: Migrations são versionadas sequencialmente
- **Idempotência**: Safe para executar múltiplas vezes (quando possível)
- **Rollback**: Considerar como reverter (quando aplicável)
- **Testing**: Testar migration em ambiente de desenvolvimento primeiro
- **Documentation**: Comentar propósito e mudanças importantes

### Tipos de Migrations
1. **Schema Changes**: CREATE TABLE, ALTER TABLE, DROP TABLE
2. **Data Migrations**: INSERT, UPDATE, DELETE de dados
3. **Index Creation**: CREATE INDEX para performance
4. **Constraint Changes**: ADD/DROP FOREIGN KEY, UNIQUE, CHECK

## Nomenclatura e Organização

### Formato de Nome
```
NNN_descriptive_name.sql

Onde:
- NNN: Número sequencial com zero padding (001, 002, 003, ...)
- descriptive_name: Nome descritivo em snake_case
```

### Exemplos
```
001_create_agents.sql
002_create_prompts.sql
003_create_vector_stores.sql
010_add_response_format_to_agents.sql
025_create_quotas.sql
```

### Tracking de Migrations
```sql
-- Tabela para tracking (criada automaticamente)
CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename VARCHAR(255) UNIQUE NOT NULL,
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## Estrutura de Migration

### Template Básico
```sql
-- Migration: 001_create_agents.sql
-- Description: Create agents table for storing AI agent configurations
-- Date: 2024-01-15
-- Author: Team

-- Main changes:
-- - Create agents table
-- - Add indexes for performance

BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS agents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    instructions TEXT,
    temperature REAL DEFAULT 0.7,
    max_tokens INTEGER,
    tools TEXT, -- JSON array
    response_format TEXT, -- JSON object
    is_default BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX idx_agents_slug ON agents(slug);
CREATE INDEX idx_agents_is_default ON agents(is_default);

COMMIT;
```

## Criação de Tabelas

### Padrões Recomendados
```sql
CREATE TABLE IF NOT EXISTS table_name (
    -- Primary Key (sempre incluir)
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Business columns
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    status VARCHAR(50) DEFAULT 'active',
    
    -- JSON columns (para flexibilidade)
    metadata TEXT, -- Store as JSON string
    
    -- Foreign Keys
    user_id INTEGER,
    
    -- Timestamps (sempre incluir)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Key Constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Tipos de Dados

#### SQLite
```sql
-- Inteiros
INTEGER           -- 4 bytes
BIGINT           -- 8 bytes

-- Texto
VARCHAR(N)       -- Variable length string
TEXT             -- Unlimited text

-- Numérico
REAL             -- Floating point
DECIMAL(10,2)    -- Fixed precision

-- Boolean (usar INTEGER)
BOOLEAN          -- 0 ou 1 (stored as INTEGER)

-- Datas (usar TEXT em formato ISO8601)
DATETIME         -- 'YYYY-MM-DD HH:MM:SS'
DATE             -- 'YYYY-MM-DD'

-- Binário
BLOB             -- Binary data
```

#### MySQL/PostgreSQL (quando migrar)
```sql
-- Inteiros
INT              -- 4 bytes
BIGINT           -- 8 bytes
TINYINT          -- 1 byte (boolean)

-- Texto
VARCHAR(N)       -- Variable length
TEXT             -- Up to 65KB
LONGTEXT         -- Up to 4GB

-- Numérico
DECIMAL(10,2)    -- Fixed precision
FLOAT            -- Floating point

-- Datas
TIMESTAMP        -- Auto-updating timestamp
DATETIME         -- Manual timestamp
DATE             -- Date only

-- JSON (MySQL 5.7+, PostgreSQL 9.2+)
JSON             -- Native JSON type
JSONB            -- Binary JSON (PostgreSQL)
```

### Constraints

#### NOT NULL
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    email VARCHAR(255) NOT NULL,  -- Obrigatório
    name VARCHAR(255)              -- Opcional
);
```

#### UNIQUE
```sql
CREATE TABLE agents (
    id INTEGER PRIMARY KEY,
    slug VARCHAR(255) UNIQUE NOT NULL,  -- Único
    name VARCHAR(255) NOT NULL
);

-- Ou unique constraint separado
ALTER TABLE agents ADD CONSTRAINT unique_slug UNIQUE(slug);

-- Unique composto
CREATE TABLE agent_channels (
    id INTEGER PRIMARY KEY,
    agent_id INTEGER,
    channel_type VARCHAR(50),
    UNIQUE(agent_id, channel_type)  -- Combinação única
);
```

#### DEFAULT
```sql
CREATE TABLE jobs (
    id INTEGER PRIMARY KEY,
    status VARCHAR(50) DEFAULT 'pending',
    priority INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### CHECK
```sql
CREATE TABLE agents (
    id INTEGER PRIMARY KEY,
    temperature REAL CHECK(temperature >= 0 AND temperature <= 2),
    max_tokens INTEGER CHECK(max_tokens > 0),
    status VARCHAR(20) CHECK(status IN ('active', 'inactive', 'archived'))
);
```

#### FOREIGN KEY
```sql
CREATE TABLE prompts (
    id INTEGER PRIMARY KEY,
    agent_id INTEGER NOT NULL,
    content TEXT,
    
    -- Cascade delete: quando agent é deletado, prompts também são
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- Outras opções:
-- ON DELETE RESTRICT  -- Impede delete se há referências
-- ON DELETE SET NULL  -- Seta NULL quando parent é deletado
-- ON DELETE NO ACTION -- Default, equivalente a RESTRICT
```

## Indexes

### Quando Criar Índices
- ✅ Colunas usadas em WHERE clauses
- ✅ Colunas usadas em JOIN conditions
- ✅ Colunas usadas em ORDER BY
- ✅ Foreign keys
- ✅ Unique constraints (já criam índice)
- ❌ Tabelas muito pequenas (< 1000 rows)
- ❌ Colunas com poucos valores distintos
- ❌ Colunas que mudam frequentemente

### Criação de Índices
```sql
-- Índice simples
CREATE INDEX idx_agents_model ON agents(model);

-- Índice composto (ordem importa!)
CREATE INDEX idx_messages_conversation_timestamp 
ON messages(conversation_id, created_at);

-- Índice único
CREATE UNIQUE INDEX idx_users_email ON users(email);

-- Índice parcial (SQLite 3.8+)
CREATE INDEX idx_active_agents 
ON agents(name) WHERE status = 'active';

-- Índice full-text search (SQLite FTS5)
CREATE VIRTUAL TABLE agents_fts USING fts5(
    name, 
    instructions, 
    content='agents', 
    content_rowid='id'
);
```

## Alteração de Tabelas

### ADD COLUMN
```sql
-- Adicionar coluna (sempre com DEFAULT ou NULL)
ALTER TABLE agents ADD COLUMN api_version VARCHAR(20) DEFAULT 'v1';

-- Múltiplas colunas (separar em statements)
ALTER TABLE agents ADD COLUMN feature_x TEXT;
ALTER TABLE agents ADD COLUMN feature_y INTEGER DEFAULT 0;
```

### RENAME COLUMN (SQLite 3.25+)
```sql
ALTER TABLE agents RENAME COLUMN old_name TO new_name;
```

### DROP COLUMN (SQLite 3.35+)
```sql
-- CUIDADO: Pode causar perda de dados
ALTER TABLE agents DROP COLUMN deprecated_field;
```

### MODIFY COLUMN
```sql
-- SQLite não suporta ALTER COLUMN diretamente
-- Usar estratégia de recreate:

BEGIN TRANSACTION;

-- 1. Criar nova tabela com schema atualizado
CREATE TABLE agents_new (
    id INTEGER PRIMARY KEY,
    name VARCHAR(500) NOT NULL,  -- Aumentado de 255 para 500
    -- ... outras colunas
);

-- 2. Copiar dados
INSERT INTO agents_new SELECT * FROM agents;

-- 3. Drop tabela antiga
DROP TABLE agents;

-- 4. Renomear nova tabela
ALTER TABLE agents_new RENAME TO agents;

-- 5. Recriar indexes
CREATE INDEX idx_agents_name ON agents(name);

COMMIT;
```

## Data Migrations

### INSERT de Dados Iniciais
```sql
-- Inserir dados seed
INSERT OR IGNORE INTO agents (id, name, slug, model, instructions, is_default)
VALUES (
    1,
    'Default Assistant',
    'default-assistant',
    'gpt-4o-mini',
    'You are a helpful assistant.',
    1
);

-- Múltiplos inserts
INSERT OR IGNORE INTO config (key, value) VALUES
    ('app_version', '1.0.0'),
    ('maintenance_mode', '0'),
    ('max_upload_size', '10485760');
```

### UPDATE de Dados
```sql
-- Atualizar dados existentes
UPDATE agents 
SET model = 'gpt-4o-mini' 
WHERE model = 'gpt-3.5-turbo';

-- Com WHERE para segurança
UPDATE users 
SET status = 'inactive' 
WHERE last_login < datetime('now', '-90 days');
```

### DELETE de Dados
```sql
-- Sempre usar WHERE!
DELETE FROM logs WHERE created_at < datetime('now', '-30 days');

-- Nunca sem WHERE (perda total de dados)
-- DELETE FROM table_name;  -- PERIGOSO!
```

## Multi-Tenancy

### Adicionar tenant_id
```sql
-- Migration para adicionar multi-tenancy
BEGIN TRANSACTION;

-- 1. Adicionar coluna tenant_id
ALTER TABLE agents ADD COLUMN tenant_id INTEGER;

-- 2. Popular com tenant padrão
UPDATE agents SET tenant_id = 1 WHERE tenant_id IS NULL;

-- 3. Tornar NOT NULL
-- (SQLite não suporta ALTER COLUMN, então recriar tabela seria necessário)

-- 4. Adicionar foreign key constraint (em nova tabela)
-- FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE

-- 5. Criar índice
CREATE INDEX idx_agents_tenant_id ON agents(tenant_id);

-- 6. Atualizar índices compostos para incluir tenant_id
CREATE INDEX idx_agents_tenant_slug ON agents(tenant_id, slug);

COMMIT;
```

## Performance

### Analyze Tables
```sql
-- Atualizar estatísticas para query planner
ANALYZE;

-- Analyze tabela específica
ANALYZE agents;
```

### Vacuum
```sql
-- Recuperar espaço e desfragmentar
VACUUM;

-- Auto-vacuum (configurar no config)
PRAGMA auto_vacuum = FULL;
```

## Rollback Strategy

### Comentar Rollback
```sql
-- Migration: 010_add_feature_x.sql
-- Rollback: DROP COLUMN feature_x; DROP INDEX idx_feature_x;

ALTER TABLE agents ADD COLUMN feature_x TEXT;
CREATE INDEX idx_feature_x ON agents(feature_x);
```

### Criar Migration de Rollback Separada
```sql
-- 011_rollback_feature_x.sql
ALTER TABLE agents DROP COLUMN feature_x;
-- DROP INDEX idx_feature_x;  -- Removido automaticamente com coluna
```

## Testing de Migrations

### Checklist de Teste
```bash
# 1. Backup banco de dados
cp data/chatbot.db data/chatbot.db.backup

# 2. Executar migration
php scripts/run_migrations.php

# 3. Verificar schema
sqlite3 data/chatbot.db ".schema agents"

# 4. Verificar dados
sqlite3 data/chatbot.db "SELECT * FROM agents LIMIT 5;"

# 5. Testar aplicação
php tests/run_tests.php

# 6. Se falhar, restaurar backup
cp data/chatbot.db.backup data/chatbot.db
```

### SQL para Verificação
```sql
-- Verificar se tabela existe
SELECT name FROM sqlite_master 
WHERE type='table' AND name='agents';

-- Verificar colunas de uma tabela
PRAGMA table_info(agents);

-- Verificar índices
PRAGMA index_list(agents);

-- Verificar foreign keys
PRAGMA foreign_key_list(agents);

-- Contar registros
SELECT COUNT(*) FROM agents;
```

## Exemplos Completos

### Criar Tabela com Relações
```sql
-- 030_create_conversations.sql
BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    agent_id INTEGER NOT NULL,
    user_id INTEGER,
    session_id VARCHAR(255),
    context TEXT, -- JSON
    metadata TEXT, -- JSON
    status VARCHAR(50) DEFAULT 'active',
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Indexes
CREATE INDEX idx_conversations_tenant ON conversations(tenant_id);
CREATE INDEX idx_conversations_agent ON conversations(agent_id);
CREATE INDEX idx_conversations_session ON conversations(session_id);
CREATE INDEX idx_conversations_status ON conversations(status);
CREATE INDEX idx_conversations_started ON conversations(started_at);

COMMIT;
```

### Migration de Dados Complexa
```sql
-- 035_migrate_agent_tools.sql
-- Migrate tools from TEXT to proper JSON format
BEGIN TRANSACTION;

-- Create temporary column
ALTER TABLE agents ADD COLUMN tools_new TEXT;

-- Migrate data (convert to proper JSON if needed)
UPDATE agents 
SET tools_new = CASE 
    WHEN tools IS NULL THEN NULL
    WHEN json_valid(tools) THEN tools
    ELSE json_array(json_object('type', tools))
END;

-- Drop old column and rename new (recreate table strategy)
CREATE TABLE agents_temp AS SELECT 
    id, name, slug, model, instructions, temperature, max_tokens,
    tools_new as tools, response_format, is_default, 
    created_at, updated_at
FROM agents;

DROP TABLE agents;
ALTER TABLE agents_temp RENAME TO agents;

-- Recreate indexes
CREATE INDEX idx_agents_slug ON agents(slug);
CREATE INDEX idx_agents_is_default ON agents(is_default);

COMMIT;
```

## Migrations para Diferentes Databases

### SQLite
```sql
-- Tipo de dado
INTEGER PRIMARY KEY AUTOINCREMENT

-- Timestamp
DATETIME DEFAULT CURRENT_TIMESTAMP

-- JSON
TEXT  -- Store as string, use json_*() functions
```

### MySQL
```sql
-- Tipo de dado
INT AUTO_INCREMENT PRIMARY KEY

-- Timestamp
TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

-- JSON
JSON  -- Native type
```

### PostgreSQL
```sql
-- Tipo de dado
SERIAL PRIMARY KEY

-- Timestamp
TIMESTAMP DEFAULT CURRENT_TIMESTAMP

-- JSON
JSONB  -- Binary JSON with indexing
```

## Segurança

### Evitar SQL Injection em Migrations
```sql
-- BOM: Usar prepared statements no código
-- Mas migrations são executadas diretamente, então:
-- - Não incluir user input em migrations
-- - Validar dados antes de inserir
-- - Usar transações

-- RUIM: Concatenar strings SQL com user input
-- Migrations não devem receber input externo
```

### Backup Antes de Migrations
```bash
# Sempre fazer backup antes de migration em produção
php scripts/db_backup.sh
php scripts/run_migrations.php
```

## Checklist de Revisão

Antes de commitar uma migration:

- [ ] Número sequencial correto
- [ ] Nome descritivo
- [ ] Comentários explicando propósito
- [ ] Usa `IF NOT EXISTS` quando apropriado
- [ ] Wrapped em `BEGIN TRANSACTION` ... `COMMIT`
- [ ] Indexes criados para performance
- [ ] Foreign keys definidos corretamente
- [ ] Defaults apropriados para novas colunas
- [ ] NOT NULL apenas quando necessário
- [ ] Testado localmente
- [ ] Testado com `php tests/run_tests.php`
- [ ] Rollback strategy documentada
- [ ] Compatível com SQLite (ou especificado DB)
- [ ] Não quebra dados existentes
