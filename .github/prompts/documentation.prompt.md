---
name: Documentation
description: Workflow para criar e atualizar documentação técnica e de usuário
mode: plan
model: gpt-4o
temperature: 0.5
tools:
  - view
  - create
  - edit
  - bash
---

# Prompt: Documentation

Workflow estruturado para criar documentação clara, completa e manutenível.

## Objetivo

Produzir documentação que seja útil, precisa e fácil de manter, seguindo padrões do projeto.

## Tipos de Documentação

1. **README** - Visão geral e quick start
2. **API Documentation** - Especificação de endpoints
3. **Architecture Docs** - Design e decisões técnicas
4. **User Guides** - Tutoriais e how-tos
5. **Runbooks** - Procedimentos operacionais
6. **CHANGELOG** - Histórico de mudanças
7. **Code Comments** - Documentação inline

## Steps

### Step 1: Identificar Necessidade

**Ação**: Determinar o que precisa ser documentado

**Triggers para Documentação**:
- [ ] Nova feature implementada
- [ ] API endpoint adicionado/modificado
- [ ] Arquitetura mudou
- [ ] Processo operacional mudou
- [ ] Feedback de usuários sobre falta de docs
- [ ] Onboarding de novo dev demorou muito

**Perguntas**:
1. **Audiência**: Quem vai ler? (Devs, ops, usuários finais?)
2. **Propósito**: Resolver qual problema?
3. **Escopo**: O que cobrir?
4. **Formato**: README, guide, runbook, API spec?
5. **Localização**: Onde colocar? (`docs/`, raiz, inline?)

**Validação**:
- [ ] Necessidade clara
- [ ] Audiência identificada
- [ ] Formato apropriado escolhido

---

### Step 2: Pesquisar Context e Exemplos

**Ação**: Entender o código/feature antes de documentar

**Para Feature/API**:
```bash
# Ver o código
view includes/FeatureService.php

# Ver testes
view tests/test_feature.php

# Ver histórico
git log --oneline -- includes/FeatureService.php

# Ver uso atual
grep -r "FeatureService" includes/
grep -r "create_feature" public/
```

**Para Operações**:
```bash
# Ver scripts existentes
ls -la scripts/

# Ver logs de execução
tail logs/backup.log

# Ver configurações
cat .env.example
```

**Buscar Docs Similares**:
```bash
# Ver estrutura de docs existentes
ls -la docs/

# Ler docs relacionados
view docs/PROJECT_DESCRIPTION.md
view docs/api.md
```

**Validação**:
- [ ] Código entendido completamente
- [ ] Exemplos encontrados
- [ ] Padrões do projeto identificados

---

### Step 3: Estruturar o Conteúdo

**Ação**: Criar outline antes de escrever

### Template para README

```markdown
# Project/Feature Name

Brief one-line description.

## Overview

2-3 parágrafos explicando o que é, para que serve, e por que existe.

## Features

- ✅ Feature 1
- ✅ Feature 2
- ✅ Feature 3

## Quick Start

Minimal steps para começar:

```bash
# Step 1
command here

# Step 2
another command
```

## Installation

Detailed installation instructions.

## Configuration

Environment variables and config options.

## Usage

Common use cases with examples.

## API Reference

Link to detailed API docs if applicable.

## Troubleshooting

Common issues and solutions.

## Contributing

How to contribute (link to CONTRIBUTING.md).

## License

License information.
```

### Template para API Documentation

```markdown
## Endpoint Name

**Method**: `POST`  
**Path**: `/api/endpoint`  
**Auth**: Required (API Key or Session)  
**Permissions**: `resource:action`

### Description

What this endpoint does and when to use it.

### Request

**Headers**:
```
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

**Body**:
```json
{
  "field1": "string",
  "field2": 123,
  "field3": ["array", "of", "values"]
}
```

**Parameters**:
- `field1` (string, required) - Description of field1
- `field2` (integer, optional, default: 10) - Description of field2
- `field3` (array, optional) - Description of field3

### Response

**Success (200)**:
```json
{
  "success": true,
  "data": {
    "id": "obj_123",
    "created_at": "2024-01-20T10:00:00Z"
  }
}
```

**Error (400)**:
```json
{
  "error": "Invalid input",
  "details": {
    "field1": "Field is required"
  }
}
```

**Error (403)**:
```json
{
  "error": "Forbidden",
  "message": "Insufficient permissions"
}
```

### Example

**cURL**:
```bash
curl -X POST http://localhost/api/endpoint \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "field1": "value",
    "field2": 123
  }'
```

**JavaScript**:
```javascript
const response = await fetch('/api/endpoint', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    field1: 'value',
    field2: 123
  })
});

const data = await response.json();
```

**PHP**:
```php
$response = $apiClient->post('/api/endpoint', [
    'field1' => 'value',
    'field2' => 123
]);
```

### Notes

- Additional context or warnings
- Rate limiting information
- Pagination details if applicable
```

### Template para Runbook

```markdown
# Runbook: Operation Name

## Overview

What this procedure does and when to use it.

## Prerequisites

- [ ] Access to production servers
- [ ] Backup completed
- [ ] Team notified

## Procedure

### Step 1: Preparation

Description and commands:

```bash
command here
```

Expected output:
```
example output
```

### Step 2: Execution

```bash
main commands
```

### Step 3: Verification

```bash
verification commands
```

### Step 4: Cleanup

```bash
cleanup commands
```

## Rollback

If something goes wrong:

```bash
rollback commands
```

## Validation

- [ ] Step 1 completed
- [ ] Step 2 completed
- [ ] System stable
- [ ] No errors in logs

## Troubleshooting

### Issue: Common Problem

**Symptoms**: Description

**Solution**:
```bash
solution commands
```

## Post-Operation

- [ ] Document execution
- [ ] Update team
- [ ] Monitor for 24h
```

**Validação**:
- [ ] Outline criado
- [ ] Seções identificadas
- [ ] Template escolhido

---

### Step 4: Escrever Conteúdo

**Ação**: Escrever documentação seguindo outline

### Princípios de Boa Documentação

**1. Clareza**:
```markdown
❌ Evitar: "The system utilizes an advanced algorithmic approach to process data."
✅ Preferir: "The system processes data using X algorithm."

❌ Evitar: "It is recommended that users should perhaps consider..."
✅ Preferir: "Users must configure X before Y."
```

**2. Exemplos Práticos**:
```markdown
❌ Sem exemplo:
"Configure the API key in your environment."

✅ Com exemplo:
"Configure the API key in your environment:

```bash
export OPENAI_API_KEY=sk-your-key-here
```

Or in `.env` file:
```
OPENAI_API_KEY=sk-your-key-here
```
"
```

**3. Estrutura Progressiva** (Simples → Complexo):
```markdown
## Quick Start (Simple)
npm install && npm start

## Basic Usage (Medium)
Detailed installation and basic config

## Advanced Configuration (Complex)
All options, edge cases, customization
```

**4. Visual Aids**:
```markdown
# Use tabelas para comparações

| Feature | Plan A | Plan B |
|---------|--------|--------|
| Users   | 10     | 100    |
| Storage | 1GB    | 10GB   |

# Use diagramas para fluxos

```
User → Frontend → Backend → Database
                    ↓
                 OpenAI API
```

# Use code blocks com syntax highlighting

```php
<?php
// PHP code here
```

```javascript
// JavaScript code here
```
```

**5. Warnings e Notes**:
```markdown
> ⚠️ **Warning**: This operation is destructive and cannot be undone.

> 💡 **Tip**: Use the `--dry-run` flag to preview changes.

> 📝 **Note**: This feature requires PHP 8.0+.

> 🔒 **Security**: Never commit API keys to version control.
```

**Validação**:
- [ ] Conteúdo claro e direto
- [ ] Exemplos práticos incluídos
- [ ] Warnings onde apropriado
- [ ] Links funcionais
- [ ] Code blocks com syntax highlighting

---

### Step 5: Revisar e Refinar

**Ação**: Melhorar qualidade da documentação

**Self-Review Checklist**:

**Conteúdo**:
- [ ] **Accuracy** - Informação está correta?
- [ ] **Completeness** - Cobre todos os casos?
- [ ] **Clarity** - Fácil de entender?
- [ ] **Examples** - Tem exemplos práticos?
- [ ] **Up-to-date** - Reflete código atual?

**Estrutura**:
- [ ] **Headers** - Hierarquia clara (H1 → H2 → H3)?
- [ ] **ToC** - Precisa de Table of Contents?
- [ ] **Links** - Internal/external links funcionam?
- [ ] **Navigation** - Fácil de navegar?

**Formatação**:
- [ ] **Markdown** - Syntax correta?
- [ ] **Code blocks** - Language specified?
- [ ] **Lists** - Formatadas consistentemente?
- [ ] **Tables** - Bem formatadas?

**Legibilidade**:
- [ ] **Paragraphs** - Não muito longos?
- [ ] **Sentences** - Não muito complexas?
- [ ] **Technical terms** - Explicados quando necessário?
- [ ] **Grammar** - Sem erros?

**Comandos para Verificar Links**:
```bash
# Check for broken links (install markdown-link-check)
npm install -g markdown-link-check
markdown-link-check docs/your-doc.md

# Check internal links
grep -r "\[.*\](.*\.md)" docs/ | grep -v "http"
```

**Validação**:
- [ ] Documentação revisada
- [ ] Links verificados
- [ ] Sem typos
- [ ] Markdown válido

---

### Step 6: Testar Instruções

**Ação**: Validar que instruções realmente funcionam

**Para Installation Docs**:
```bash
# Fresh clone em diretório temporário
cd /tmp
git clone https://github.com/user/repo.git
cd repo

# Seguir instruções EXATAMENTE como documentado
# Cada comando deve funcionar
```

**Para API Docs**:
```bash
# Testar cada exemplo de cURL
curl -X POST http://localhost/api/endpoint \
  -H "Authorization: Bearer TEST_KEY" \
  -d '{"test": "data"}'

# Verificar response codes
# Verificar JSON structure
```

**Para Runbooks**:
```bash
# Executar cada step em ambiente de teste
# Validar que produz resultado esperado
# Validar rollback funciona
```

**Red Team Review** (se possível):
- [ ] Dar doc para alguém novo
- [ ] Observar onde fica confuso
- [ ] Coletar feedback
- [ ] Iterar baseado em feedback

**Validação**:
- [ ] Todos os exemplos testados
- [ ] Instruções funcionam
- [ ] Nenhum passo faltando
- [ ] Feedback incorporado

---

### Step 7: Integrar com Docs Existentes

**Ação**: Conectar nova documentação com ecosystem

**Update Index/ToC**:
```markdown
<!-- docs/README.md -->

## Documentation Index

### Getting Started
- [Quick Start](QUICK_START.md)
- [Installation](INSTALLATION.md)
- [**New: Feature X Guide**](FEATURE_X_GUIDE.md) ← Adicionar

### API Reference
- [REST API](api.md)
- [**New: Feature X API**](feature-x-api.md) ← Adicionar
```

**Add Cross-Links**:
```markdown
<!-- Em doc existente, adicionar link para novo doc -->

For more details on Feature X, see [Feature X Guide](FEATURE_X_GUIDE.md).

<!-- Em novo doc, linkar para docs relacionados -->

This feature integrates with [Agent Management](AGENTS.md) and [Multi-Tenancy](MULTI_TENANCY.md).
```

**Update Main README**:
```markdown
<!-- README.md -->

## 📚 Documentation

- [Quick Start](docs/QUICK_START.md)
- [Feature X Guide](docs/FEATURE_X_GUIDE.md) ← Adicionar se user-facing
```

**Validação**:
- [ ] ToC atualizado
- [ ] Cross-links adicionados
- [ ] Navegação funciona
- [ ] Nenhum doc órfão

---

### Step 8: Update CHANGELOG

**Ação**: Documentar mudanças para usuários

**CHANGELOG.md Entry**:
```markdown
## [Unreleased]

### Added
- Feature X management system
- Complete API documentation for Feature X endpoints
- User guide for Feature X configuration and usage

### Changed
- Updated Architecture docs to include Feature X design
- Improved API documentation with more examples

### Documentation
- Added `docs/FEATURE_X_GUIDE.md` - Complete user guide
- Added `docs/FEATURE_X_API.md` - API reference
- Updated `docs/api.md` with new endpoints
```

**Quando usar cada seção**:
- **Added** - Novas features, docs, endpoints
- **Changed** - Modificações em features existentes
- **Deprecated** - Features que serão removidas
- **Removed** - Features removidas
- **Fixed** - Bug fixes
- **Security** - Vulnerabilidades corrigidas
- **Documentation** - Mudanças apenas em docs

**Validação**:
- [ ] CHANGELOG atualizado
- [ ] Entries na seção correta
- [ ] Links para docs se relevante

---

### Step 9: Code Comments

**Ação**: Adicionar/atualizar comments no código

**Quando Comentar**:
```php
// ✅ COMENTAR: Lógica complexa ou não-óbvia
// Calculate exponential backoff with jitter to prevent thundering herd
$backoff = min(
    pow(2, $attempt) * 1000 + rand(0, 1000),
    30000
);

// ✅ COMENTAR: Decisões técnicas importantes
// Using REAL instead of INTEGER for temperature to support decimal values (0.1-2.0)
// This maintains compatibility with OpenAI API expectations
ALTER TABLE agents MODIFY COLUMN temperature REAL;

// ✅ COMENTAR: Workarounds e hacks temporários
// HACK: SQLite doesn't support DROP COLUMN, so we recreate the table
// TODO: Migrate to ALTER TABLE when we drop SQLite support
CREATE TABLE agents_new (...);

// ✅ COMENTAR: Security considerations
// Validate tenant ownership before allowing access
// This prevents data leaks between tenants
if ($resource['tenant_id'] !== $currentTenantId) {
    throw new ForbiddenException('Access denied');
}

// ❌ NÃO COMENTAR: Código óbvio
// Set name to input name
$name = $input['name']; // Desnecessário

// ❌ NÃO COMENTAR: O QUE o código faz (use nomes descritivos)
// Loop through users
foreach ($users as $user) { // Óbvio pelo código
```

**PHPDoc para Classes e Métodos**:
```php
/**
 * Service for managing features
 * 
 * Provides CRUD operations for features with full multi-tenant
 * support and audit logging.
 * 
 * @package App\Services
 */
class FeatureService
{
    /**
     * Create a new feature
     * 
     * @param array $data Feature data including name (required) and description (optional)
     * @return string Created feature ID
     * @throws InvalidArgumentException If validation fails
     */
    public function create(array $data): string
    {
        // Implementation
    }
}
```

**Validação**:
- [ ] Comments adicionados onde necessário
- [ ] PHPDoc em métodos públicos
- [ ] Decisões técnicas documentadas
- [ ] TODOs marcados

---

### Step 10: Final Review e Publish

**Ação**: Review final antes de merge

**Pre-Publish Checklist**:

**Content**:
- [ ] Spelling/grammar check
- [ ] All links work
- [ ] All examples tested
- [ ] No sensitive data (keys, passwords)
- [ ] License/copyright if needed

**Structure**:
- [ ] Proper markdown formatting
- [ ] Headers hierarchy correct (H1 → H2 → H3)
- [ ] ToC updated if needed
- [ ] Cross-links in place

**Integration**:
- [ ] Linked from main docs index
- [ ] CHANGELOG updated
- [ ] README updated if needed
- [ ] No orphan documents

**Validation Commands**:
```bash
# Spell check (install aspell)
aspell -c docs/your-doc.md

# Markdown lint (install markdownlint)
markdownlint docs/your-doc.md

# Check links
markdown-link-check docs/your-doc.md

# Preview (install grip)
grip docs/your-doc.md
# Open http://localhost:6419
```

**Get Peer Review**:
- [ ] Share with team member
- [ ] Ask for feedback on clarity
- [ ] Incorporate suggestions
- [ ] Get approval

**Publish**:
```bash
# Commit documentation
git add docs/
git commit -m "docs: Add Feature X guide and API documentation"

# Push and create PR
git push origin docs/feature-x

# Merge after approval
```

**Validação**:
- [ ] Final review completo
- [ ] Peer review feito
- [ ] Documentation merged
- [ ] Accessible to users

---

## Success Criteria

Documentação está completa quando:

- ✅ Conteúdo claro e completo
- ✅ Exemplos funcionam
- ✅ Instruções validadas
- ✅ Links verificados
- ✅ Integrada com docs existentes
- ✅ CHANGELOG atualizado
- ✅ Peer reviewed
- ✅ Published e accessible

## Documentation Best Practices

### DRY (Don't Repeat Yourself)
```markdown
❌ Repetir mesma instrução em vários lugares
✅ Criar doc central e linkar para ela

"For installation instructions, see [Installation Guide](INSTALL.md)"
```

### Keep It Updated
```markdown
# Adicionar nota de versão se doc pode ficar desatualizado
> 📝 **Note**: This documentation is for version 2.0+. 
> For version 1.x, see [Legacy Docs](legacy/).

# Ou adicionar data de última atualização
> Last updated: 2024-01-20
```

### Progressive Disclosure
```markdown
## Quick Start (para 90% dos usuários)
Simple instructions

## Advanced Configuration (para 10%)
Detailed options

## Expert Mode (para 1%)
All the edge cases
```

### Show, Don't Tell
```markdown
❌ "Configure the environment variables correctly"
✅ 
```bash
# Copy example config
cp .env.example .env

# Edit with your values
nano .env

# Required variables:
OPENAI_API_KEY=sk-your-key-here
DATABASE_URL=sqlite:./data/db.sqlite
```
```

## Templates

Ver templates completos em:
- `docs/templates/README_TEMPLATE.md`
- `docs/templates/API_DOC_TEMPLATE.md`
- `docs/templates/RUNBOOK_TEMPLATE.md`
- `docs/templates/USER_GUIDE_TEMPLATE.md`

## Ferramentas Úteis

```bash
# Markdown linting
npm install -g markdownlint-cli
markdownlint docs/

# Link checking
npm install -g markdown-link-check
markdown-link-check docs/**/*.md

# Preview
npm install -g grip
grip docs/README.md

# Spell check
aspell check -c docs/file.md

# Generate ToC
npm install -g markdown-toc
markdown-toc -i docs/file.md
```

## Referências

- Existing Docs: `docs/`
- README: `README.md`
- API Docs: `docs/api.md`
- Architecture: `docs/PROJECT_DESCRIPTION.md`
- Contributing: `docs/CONTRIBUTING.md`
- Markdown Guide: https://www.markdownguide.org/
