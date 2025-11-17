---
name: Feature Implementation
description: Workflow completo para implementar uma nova feature do zero ao deploy
mode: backend
model: gpt-4o
temperature: 0.4
tools:
  - view
  - create
  - edit
  - bash
  - report_progress
---

# Prompt: Feature Implementation

Workflow estruturado para implementar uma nova feature, desde o planejamento até testes e documentação.

## Objetivo

Implementar uma nova funcionalidade de forma completa, testada e documentada, seguindo os padrões do projeto.

## Inputs Necessários

- **Feature Description**: [O que deve ser implementado]
- **Requirements**: [Requisitos funcionais e não-funcionais]
- **Acceptance Criteria**: [Como validar que está completo]

## Steps

### Step 1: Análise e Planejamento

**Ação**: Entender requisitos e criar plano de implementação

**Perguntas a responder**:
1. Qual problema estamos resolvendo?
2. Quem são os usuários desta feature?
3. Quais componentes serão afetados?
4. Há dependências de outras features?
5. Qual o MVP (Minimum Viable Product)?

**Análise de Impacto**:
- **Frontend**: Precisa de UI nova? Modificar existente?
- **Backend**: Novos endpoints? Novos serviços?
- **Database**: Novas tabelas? Modificar schema?
- **Integrations**: APIs externas? Webhooks?
- **Security**: Autenticação? Autorização? RBAC?
- **Multi-tenancy**: Precisa isolamento por tenant?

**Validação**:
- [ ] Requisitos claros e completos
- [ ] Escopo bem definido
- [ ] Impactos identificados

**Output**: Plano detalhado com checklist

---

### Step 2: Database Design (se necessário)

**Ação**: Projetar schema e criar migration

**Checklist**:
- [ ] Identificar entidades necessárias
- [ ] Definir atributos e tipos
- [ ] Definir relacionamentos (foreign keys)
- [ ] Criar índices necessários
- [ ] Considerar multi-tenancy (tenant_id?)
- [ ] Adicionar timestamps (created_at, updated_at)

**Template de Migration**:
```sql
-- Migration: NNN_create_feature_table.sql
-- Purpose: [Descrição da feature]

CREATE TABLE IF NOT EXISTS feature_table (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id VARCHAR(36),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_feature_tenant ON feature_table(tenant_id);
CREATE INDEX IF NOT EXISTS idx_feature_status ON feature_table(status);
```

**Validação**:
- [ ] Migration criada
- [ ] Schema documentado
- [ ] Compatível SQLite e MySQL
- [ ] Migration testada

**Comandos**:
```bash
# Create migration file
touch db/migrations/NNN_create_feature.sql

# Run migration
php scripts/run_migrations.php

# Verify schema
sqlite3 data/chatbot.db ".schema feature_table"
```

---

### Step 3: Backend - Service Layer

**Ação**: Criar serviço de domínio para a feature

**Structure**:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/DB.php';
require_once __DIR__ . '/TenantContext.php';
require_once __DIR__ . '/AuditService.php';

class FeatureService
{
    private DB $db;
    private TenantContext $tenantContext;
    private AuditService $audit;
    
    public function __construct(DB $db, TenantContext $tenantContext, AuditService $audit)
    {
        $this->db = $db;
        $this->tenantContext = $tenantContext;
        $this->audit = $audit;
    }
    
    /**
     * Create new feature item
     */
    public function create(array $data): string
    {
        $this->validateCreateData($data);
        
        $id = $this->generateId();
        $tenantId = $this->tenantContext->getCurrentTenantId();
        
        $this->db->execute(
            'INSERT INTO feature_table (id, tenant_id, name, description, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$id, $tenantId, $data['name'], $data['description'] ?? null, date('Y-m-d H:i:s')]
        );
        
        $this->audit->log('feature.create', null, ['id' => $id], $tenantId);
        
        return $id;
    }
    
    /**
     * Get feature item by ID
     */
    public function getById(string $id): ?array
    {
        $tenantId = $this->tenantContext->getCurrentTenantId();
        
        $result = $this->db->query(
            'SELECT * FROM feature_table WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
        
        return $result[0] ?? null;
    }
    
    /**
     * List all feature items
     */
    public function list(array $filters = []): array
    {
        $tenantId = $this->tenantContext->getCurrentTenantId();
        
        $sql = 'SELECT * FROM feature_table WHERE tenant_id = ?';
        $params = [$tenantId];
        
        if (isset($filters['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Update feature item
     */
    public function update(string $id, array $data): bool
    {
        $tenantId = $this->tenantContext->getCurrentTenantId();
        
        // Verify ownership
        $existing = $this->getById($id);
        if (!$existing) {
            throw new \InvalidArgumentException('Feature not found');
        }
        
        $this->validateUpdateData($data);
        
        $this->db->execute(
            'UPDATE feature_table SET name = ?, description = ?, updated_at = ?
             WHERE id = ? AND tenant_id = ?',
            [$data['name'], $data['description'] ?? null, date('Y-m-d H:i:s'), $id, $tenantId]
        );
        
        $this->audit->log('feature.update', null, ['id' => $id], $tenantId);
        
        return true;
    }
    
    /**
     * Delete feature item
     */
    public function delete(string $id): bool
    {
        $tenantId = $this->tenantContext->getCurrentTenantId();
        
        // Verify ownership
        $existing = $this->getById($id);
        if (!$existing) {
            return false;
        }
        
        $this->db->execute(
            'DELETE FROM feature_table WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
        
        $this->audit->log('feature.delete', null, ['id' => $id], $tenantId);
        
        return true;
    }
    
    private function validateCreateData(array $data): void
    {
        if (!isset($data['name']) || empty(trim($data['name']))) {
            throw new \InvalidArgumentException('Name is required');
        }
        
        if (strlen($data['name']) > 255) {
            throw new \InvalidArgumentException('Name too long');
        }
    }
    
    private function validateUpdateData(array $data): void
    {
        $this->validateCreateData($data);
    }
    
    private function generateId(): string
    {
        return 'feat_' . bin2hex(random_bytes(16));
    }
}
```

**Validação**:
- [ ] Serviço criado em `includes/`
- [ ] CRUD completo implementado
- [ ] Multi-tenancy respeitado
- [ ] Audit logging presente
- [ ] Validações implementadas
- [ ] Type hints completos

---

### Step 4: Backend - API Endpoints

**Ação**: Adicionar endpoints em `admin-api.php`

**Endpoints a criar**:
- `POST /admin-api.php?action=create_feature` - Criar
- `GET /admin-api.php?action=get_feature&id=X` - Obter
- `GET /admin-api.php?action=list_features` - Listar
- `PUT /admin-api.php?action=update_feature` - Atualizar
- `DELETE /admin-api.php?action=delete_feature&id=X` - Deletar

**Template de Endpoint**:
```php
// In admin-api.php

case 'create_feature':
    // Check permission
    if (!$auth->hasPermission($user, 'features', 'create')) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate
    if (!isset($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name required']);
        exit;
    }
    
    // Execute
    try {
        $id = $featureService->create($input);
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        error_log("Create feature failed: {$e->getMessage()}");
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
    break;
```

**Validação**:
- [ ] Todos endpoints implementados
- [ ] RBAC verificado em cada endpoint
- [ ] Input validation completa
- [ ] Error handling apropriado
- [ ] Respostas JSON consistentes

---

### Step 5: Frontend - UI Components (se necessário)

**Ação**: Criar interface no Admin UI

**Estrutura**:
```javascript
// public/admin/app.js

const FeatureView = {
    render() {
        const container = document.getElementById('main-content');
        container.innerHTML = `
            <div class="view-header">
                <h2>Features</h2>
                <button onclick="FeatureView.showCreateModal()" class="btn-primary">
                    Create Feature
                </button>
            </div>
            <div id="features-list" class="list-container">
                Loading...
            </div>
        `;
        
        this.loadList();
    },
    
    async loadList() {
        try {
            const response = await AdminApp.apiCall('list_features');
            const features = response.features || [];
            
            const listContainer = document.getElementById('features-list');
            
            if (features.length === 0) {
                listContainer.innerHTML = '<p>No features found.</p>';
                return;
            }
            
            listContainer.innerHTML = features.map(feature => `
                <div class="list-item" data-id="${feature.id}">
                    <div class="item-content">
                        <h3>${escapeHtml(feature.name)}</h3>
                        <p>${escapeHtml(feature.description || '')}</p>
                        <small>Created: ${feature.created_at}</small>
                    </div>
                    <div class="item-actions">
                        <button onclick="FeatureView.edit('${feature.id}')" class="btn-secondary">
                            Edit
                        </button>
                        <button onclick="FeatureView.delete('${feature.id}')" class="btn-danger">
                            Delete
                        </button>
                    </div>
                </div>
            `).join('');
            
        } catch (error) {
            console.error('Failed to load features:', error);
            alert('Failed to load features');
        }
    },
    
    showCreateModal() {
        // Show modal for creating feature
        // ...
    },
    
    async create(data) {
        try {
            await AdminApp.apiCall('create_feature', data);
            alert('Feature created successfully');
            this.loadList();
        } catch (error) {
            console.error('Create failed:', error);
            alert('Failed to create feature');
        }
    },
    
    async edit(id) {
        // Load feature and show edit modal
        // ...
    },
    
    async delete(id) {
        if (!confirm('Are you sure?')) return;
        
        try {
            await AdminApp.apiCall('delete_feature', { id });
            alert('Feature deleted successfully');
            this.loadList();
        } catch (error) {
            console.error('Delete failed:', error);
            alert('Failed to delete feature');
        }
    }
};
```

**Validação**:
- [ ] UI criada e funcional
- [ ] CRUD operations working
- [ ] Error handling with user feedback
- [ ] Responsive design
- [ ] Accessibility (labels, ARIA)

---

### Step 6: Testing

**Ação**: Criar testes abrangentes

**Test File**: `tests/test_feature.php`

```php
<?php

require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/FeatureService.php';
require_once __DIR__ . '/../includes/TenantContext.php';
require_once __DIR__ . '/../includes/AuditService.php';

class FeatureServiceTest
{
    private DB $db;
    private FeatureService $service;
    
    public function run(): void
    {
        echo "Running Feature Service Tests...\n";
        
        $this->testCreate();
        $this->testGetById();
        $this->testList();
        $this->testUpdate();
        $this->testDelete();
        $this->testMultiTenantIsolation();
        
        echo "All tests passed!\n";
    }
    
    private function testCreate(): void
    {
        $id = $this->service->create([
            'name' => 'Test Feature',
            'description' => 'Test description'
        ]);
        
        assert(!empty($id), 'Feature ID should not be empty');
        assert(str_starts_with($id, 'feat_'), 'ID should start with feat_');
        
        echo "✓ Create test passed\n";
    }
    
    private function testMultiTenantIsolation(): void
    {
        // Create feature in tenant A
        $tenantA = 'tenant-a';
        $this->tenantContext->setCurrentTenantId($tenantA);
        $idA = $this->service->create(['name' => 'Feature A']);
        
        // Switch to tenant B
        $tenantB = 'tenant-b';
        $this->tenantContext->setCurrentTenantId($tenantB);
        
        // Should not see tenant A's feature
        $feature = $this->service->getById($idA);
        assert($feature === null, 'Tenant B should not see tenant A feature');
        
        echo "✓ Multi-tenant isolation test passed\n";
    }
    
    // More tests...
}

// Run tests
$test = new FeatureServiceTest();
$test->run();
```

**Validação**:
- [ ] Unit tests criados
- [ ] Integration tests criados
- [ ] Edge cases testados
- [ ] Multi-tenancy testado
- [ ] Todos os testes passam

**Comandos**:
```bash
# Run new tests
php tests/test_feature.php

# Run all tests
php tests/run_tests.php

# Static analysis
composer run analyze
```

---

### Step 7: Documentation

**Ação**: Documentar a feature

**Documentos a atualizar**:

1. **API Documentation** (`docs/api.md`):
```markdown
### Feature Management

#### Create Feature
**POST** `/admin-api.php?action=create_feature`

Creates a new feature item.

**Request**:
```json
{
  "name": "Feature Name",
  "description": "Optional description"
}
```

**Response**:
```json
{
  "success": true,
  "id": "feat_abc123"
}
```

**Permissions**: `features:create`
```

2. **README Updates** (se feature é user-facing)

3. **CHANGELOG.md**:
```markdown
## [Unreleased]

### Added
- Feature management system with CRUD operations
- Admin UI for managing features
- Multi-tenant support for features
```

**Validação**:
- [ ] API docs atualizados
- [ ] README atualizado (se necessário)
- [ ] CHANGELOG atualizado
- [ ] Inline comments em código complexo

---

### Step 8: Security Review

**Ação**: Revisar segurança da implementação

**Checklist**:
- [ ] SQL Injection - Prepared statements usados?
- [ ] XSS - HTML sanitizado no frontend?
- [ ] RBAC - Permissões verificadas?
- [ ] Multi-tenancy - tenant_id filtrado?
- [ ] Input validation - Completa no backend?
- [ ] Audit logging - Operações logadas?
- [ ] Rate limiting - Aplicado se necessário?

**Comandos**:
```bash
# Run security analysis
composer run analyze

# Check dependencies
composer audit
```

**Validação**:
- [ ] Sem vulnerabilidades identificadas
- [ ] Security best practices seguidos

---

### Step 9: Integration & Smoke Tests

**Ação**: Testar integração completa

**Checklist**:
- [ ] Migration executa
- [ ] Backend endpoints funcionam
- [ ] Frontend UI funciona
- [ ] CRUD completo funcional
- [ ] Multi-tenancy funciona
- [ ] Audit logs gerados

**Comandos**:
```bash
# Run smoke tests
bash scripts/smoke_test.sh

# Manual testing
php -S localhost:8000
# Open http://localhost:8000/public/admin/
```

**Validação**:
- [ ] End-to-end flow completo
- [ ] Sem erros em produção

---

### Step 10: Code Review & Deploy

**Ação**: Preparar para merge

**Checklist Final**:
- [ ] All tests passing
- [ ] Static analysis clean
- [ ] Documentation complete
- [ ] Security reviewed
- [ ] Code reviewed by peer
- [ ] Migration tested
- [ ] Backward compatible

**Deploy Steps**:
```bash
# Commit changes
git add .
git commit -m "feat: Add feature management system"

# Push and create PR
git push origin feature/feature-management

# After approval and merge
# Run migration in production
php scripts/run_migrations.php

# Monitor logs
tail -f logs/app.log
```

---

## Success Criteria

Feature está completa quando:

- ✅ Database schema criado
- ✅ Service layer implementado
- ✅ API endpoints funcionais
- ✅ Frontend UI (se aplicável)
- ✅ Testes passando (unit + integration)
- ✅ Documentação atualizada
- ✅ Security review passed
- ✅ Code review approved
- ✅ Deployed to production

## Referências

- Architecture: `docs/PROJECT_DESCRIPTION.md`
- API Patterns: `admin-api.php`
- Service Patterns: `includes/AgentService.php`
- Test Patterns: `tests/test_phase5_agent_integration.php`
- Frontend Patterns: `public/admin/app.js`
