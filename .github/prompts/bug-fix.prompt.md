---
name: Bug Fix
description: Workflow sistemático para investigar, corrigir e validar bugs
mode: backend
model: gpt-4o
temperature: 0.3
tools:
  - view
  - edit
  - bash
  - report_progress
---

# Prompt: Bug Fix

Workflow estruturado para diagnosticar, corrigir e validar a correção de bugs de forma sistemática.

## Objetivo

Resolver bugs de forma completa e confiável, evitando regressões e documentando o problema e solução.

## Inputs Necessários

- **Bug Description**: [Descrição do problema]
- **Steps to Reproduce**: [Como reproduzir]
- **Expected Behavior**: [Comportamento esperado]
- **Actual Behavior**: [Comportamento atual]
- **Environment**: [Browser, PHP version, etc]

## Steps

### Step 1: Reproduzir o Bug

**Ação**: Confirmar que o bug existe e entender as condições

**Checklist**:
- [ ] Seguir steps to reproduce exatamente
- [ ] Conseguir reproduzir consistentemente
- [ ] Testar em diferentes ambientes (se relevante)
- [ ] Capturar logs/screenshots do erro

**Perguntas**:
1. O bug ocorre sempre ou é intermitente?
2. Afeta todos os usuários ou apenas alguns?
3. Começou recentemente ou é antigo?
4. Há padrão nas ocorrências?

**Validação**:
- [ ] Bug reproduzido localmente
- [ ] Condições de ocorrência claras
- [ ] Logs/evidências capturadas

**Comandos**:
```bash
# Check logs
tail -f logs/app.log
tail -f logs/error.log

# Start local server
php -S localhost:8000

# Check database state
sqlite3 data/chatbot.db "SELECT * FROM table WHERE ..."
```

---

### Step 2: Investigar Causa Raiz

**Ação**: Identificar a causa do problema

**Técnicas de Investigação**:

1. **Análise de Logs**:
```bash
# Error logs
grep -i "error\|exception\|fatal" logs/*.log

# Recent errors
tail -100 logs/error.log

# Specific error pattern
grep "SpecificError" logs/app.log | tail -20
```

2. **Code Inspection**:
```bash
# Find related code
grep -r "function_name" includes/

# Git history
git log --oneline --grep="feature" -- file.php
git blame file.php
```

3. **Database State**:
```bash
# Check data
sqlite3 data/chatbot.db "SELECT * FROM table LIMIT 10"

# Check constraints
sqlite3 data/chatbot.db ".schema table"
```

4. **Debugging**:
```php
// Add debug logging
error_log("DEBUG: Variable value = " . print_r($var, true));

// Check execution path
error_log("DEBUG: Reached checkpoint A");
```

**Hipóteses Comuns**:
- [ ] **Null/Undefined** - Variável não inicializada?
- [ ] **Type mismatch** - String onde esperava int?
- [ ] **Missing validation** - Input inválido não tratado?
- [ ] **Race condition** - Problema de concorrência?
- [ ] **SQL error** - Query malformada ou constraint violation?
- [ ] **Permission issue** - RBAC bloqueando acesso?
- [ ] **Configuration** - Variável de ambiente faltando?
- [ ] **Third-party API** - OpenAI/webhook falhando?

**Validação**:
- [ ] Causa raiz identificada
- [ ] Hipótese validada com evidências
- [ ] Escopo do impacto claro

---

### Step 3: Desenvolver Fix

**Ação**: Implementar correção minimal e focada

**Princípios**:
- ✅ **Minimal change** - Apenas o necessário
- ✅ **Root cause** - Corrigir causa, não sintoma
- ✅ **Defensive** - Adicionar validações preventivas
- ✅ **Backward compatible** - Não quebrar funcionalidade existente

**Padrões de Fix Comuns**:

1. **Null Check**:
```php
// Antes (buggy)
$value = $data['key'];

// Depois (fixed)
$value = $data['key'] ?? null;
if ($value === null) {
    throw new InvalidArgumentException('Key is required');
}
```

2. **Type Validation**:
```php
// Antes (buggy)
$count = $_GET['count'];

// Depois (fixed)
$count = filter_var($_GET['count'] ?? 10, FILTER_VALIDATE_INT);
if ($count === false || $count < 1) {
    $count = 10; // Default
}
```

3. **Array Access**:
```php
// Antes (buggy)
$item = $items[0];

// Depois (fixed)
if (empty($items)) {
    throw new Exception('No items found');
}
$item = $items[0];
```

4. **Database Query**:
```php
// Antes (buggy)
$result = $db->query('SELECT * FROM table WHERE id = ?', [$id]);
return $result[0];

// Depois (fixed)
$result = $db->query('SELECT * FROM table WHERE id = ?', [$id]);
if (empty($result)) {
    return null;
}
return $result[0];
```

5. **Error Handling**:
```php
// Antes (buggy)
$data = json_decode($input);

// Depois (fixed)
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
}
```

**Validação**:
- [ ] Fix implementado
- [ ] Código revisado
- [ ] Não introduz novos problemas

---

### Step 4: Adicionar Testes

**Ação**: Criar teste que reproduz o bug e valida o fix

**Test Structure**:
```php
<?php

require_once __DIR__ . '/../includes/ServiceWithBug.php';

class BugFixTest
{
    public function testBugIsFixed(): void
    {
        echo "Testing bug fix...\n";
        
        // Setup: Reproduce conditions that caused bug
        $service = new ServiceWithBug();
        
        // Before fix: This would throw an error or return wrong value
        // After fix: Should handle correctly
        try {
            $result = $service->methodThatHadBug(['invalid' => 'input']);
            
            // Assert expected behavior
            assert($result !== null, 'Should handle invalid input gracefully');
            
        } catch (InvalidArgumentException $e) {
            // Expected exception is fine
            assert(str_contains($e->getMessage(), 'required'), 'Should have clear error message');
        }
        
        echo "✓ Bug fix test passed\n";
    }
    
    public function testEdgeCases(): void
    {
        // Test edge cases that could cause similar bugs
        $service = new ServiceWithBug();
        
        // Empty input
        $result = $service->methodThatHadBug([]);
        assert($result === null, 'Empty input should return null');
        
        // Null input
        try {
            $service->methodThatHadBug(null);
            assert(false, 'Should throw on null input');
        } catch (InvalidArgumentException $e) {
            // Expected
        }
        
        echo "✓ Edge cases test passed\n";
    }
}

// Run test
$test = new BugFixTest();
$test->testBugIsFixed();
$test->testEdgeCases();
```

**Validação**:
- [ ] Teste reproduz bug original (fails before fix)
- [ ] Teste passa após fix
- [ ] Edge cases cobertos
- [ ] Teste adicionado à suite

**Comandos**:
```bash
# Run specific test
php tests/test_bug_fix.php

# Run all tests to check for regressions
php tests/run_tests.php
```

---

### Step 5: Validar Manualmente

**Ação**: Testar fix em cenário real

**Checklist**:
- [ ] Reproduzir steps originais - bug não ocorre mais
- [ ] Testar casos relacionados - sem regressão
- [ ] Testar edge cases - tratamento correto
- [ ] Verificar logs - sem novos erros
- [ ] UI/UX - experiência melhorou

**Comandos**:
```bash
# Start local environment
docker-compose up -d

# Check logs in real-time
docker-compose logs -f chatbot

# Test manually
# Open browser, execute steps to reproduce

# Check database
sqlite3 data/chatbot.db "SELECT * FROM table"
```

**Validação**:
- [ ] Bug resolvido no cenário original
- [ ] Nenhum efeito colateral detectado
- [ ] Experiência do usuário melhorou

---

### Step 6: Security & Performance Check

**Ação**: Garantir que fix não introduz problemas

**Security Checklist**:
- [ ] Sem novos vetores de ataque
- [ ] Validação de input mantida/melhorada
- [ ] Sem exposição de dados sensíveis em logs
- [ ] RBAC ainda funciona
- [ ] Multi-tenancy preservado

**Performance Checklist**:
- [ ] Sem queries N+1 introduzidas
- [ ] Sem loops desnecessários
- [ ] Caching ainda funciona
- [ ] Sem memory leaks

**Comandos**:
```bash
# Static analysis
composer run analyze

# Security scan
# Use codeql_checker if available

# Check for common issues
grep -r "var_dump\|print_r\|die(" includes/
```

**Validação**:
- [ ] Sem novos problemas de segurança
- [ ] Performance não degradou

---

### Step 7: Update Documentation

**Ação**: Documentar o problema e solução

**Documentos a atualizar**:

1. **Commit Message**:
```
fix: Handle null values in feature X processing

Fixes #123

Problem: Feature X crashed when receiving null values in field Y
because the code didn't check for null before accessing properties.

Solution: Added null check and validation, returning appropriate
error message to user instead of crashing.

Also added defensive checks for edge cases and improved error
messages for better debugging.
```

2. **CHANGELOG.md**:
```markdown
## [Unreleased]

### Fixed
- Fixed crash when processing null values in feature X (#123)
- Improved error handling and validation in ServiceName
```

3. **Code Comments** (se lógica complexa):
```php
// Fix for issue #123: Must check for null before accessing
// properties to prevent crash when API returns incomplete data
if ($data['field'] === null) {
    throw new InvalidArgumentException('Field is required');
}
```

**Validação**:
- [ ] Commit message claro e descritivo
- [ ] CHANGELOG atualizado
- [ ] Comments adicionados se necessário
- [ ] Issue linkado no commit

---

### Step 8: Regression Testing

**Ação**: Garantir que fix não quebrou nada

**Test Suite Completa**:
```bash
# Run all unit tests
php tests/run_tests.php

# Run integration tests
php tests/test_phase5_agent_integration.php

# Run smoke tests
bash scripts/smoke_test.sh

# Static analysis
composer run analyze

# Lint
npm run lint
```

**Áreas Críticas a Testar**:
- [ ] Features relacionadas funcionam
- [ ] Auth/RBAC ainda funciona
- [ ] Multi-tenancy preservado
- [ ] APIs retornam respostas corretas
- [ ] UI não quebrou

**Validação**:
- [ ] Todos os testes passam
- [ ] Sem regressões detectadas
- [ ] CI/CD verde

---

### Step 9: Code Review

**Ação**: Obter revisão do fix

**Preparar para Review**:
1. Criar PR com descrição clara
2. Linkar issue original
3. Documentar o que mudou e por quê
4. Mostrar evidências (antes/depois)

**PR Description Template**:
```markdown
## Bug Fix: [Título do Bug]

### Problem
[Descrição do bug original]

**Steps to Reproduce**:
1. [Passo 1]
2. [Passo 2]
3. [Erro ocorre]

**Expected**: [O que deveria acontecer]
**Actual**: [O que acontecia]

### Root Cause
[Explicação da causa raiz]

### Solution
[O que foi feito para corrigir]

**Changes**:
- Modified `file1.php` - Added null check and validation
- Added `test_bug_fix.php` - Test reproducing and validating fix
- Updated `CHANGELOG.md`

### Testing
- [x] Unit tests pass
- [x] Manual testing verified
- [x] No regressions detected
- [x] Edge cases covered

### Screenshots/Logs
[Antes]
```
Error log showing the bug
```

[Depois]
```
Success log after fix
```

Fixes #123
```

**Validação**:
- [ ] PR criado com descrição completa
- [ ] Reviewer entende problema e solução
- [ ] Feedback incorporado

---

### Step 10: Deploy & Monitor

**Ação**: Deploy para produção e monitorar

**Pre-Deploy Checklist**:
- [ ] All tests passing
- [ ] Code reviewed and approved
- [ ] CHANGELOG updated
- [ ] Deployment plan ready
- [ ] Rollback plan ready

**Deploy Steps**:
```bash
# Merge PR
git checkout main
git pull origin main

# Deploy (depends on infrastructure)
# Docker:
docker-compose -f docker-compose.prod.yml up -d

# Or K8s:
kubectl rollout restart deployment/chatbot
```

**Post-Deploy Monitoring**:
```bash
# Watch logs
tail -f logs/app.log | grep "ERROR\|Exception"

# Check metrics
curl http://localhost/metrics.php

# Monitor error rate
# Check Grafana dashboard if available
```

**Monitoring Checklist** (first 24h):
- [ ] Error rate normal
- [ ] No new errors in logs
- [ ] Performance stable
- [ ] User reports positive
- [ ] Metrics look good

**Validação**:
- [ ] Fix deployed successfully
- [ ] Bug não ocorre mais em produção
- [ ] Sem novos problemas detectados

---

## Success Criteria

Bug está resolvido quando:

- ✅ Causa raiz identificada e documentada
- ✅ Fix implementado de forma minimal
- ✅ Testes criados e passando
- ✅ Validação manual completa
- ✅ Sem regressões detectadas
- ✅ Documentação atualizada
- ✅ Code review aprovado
- ✅ Deployed e monitorado
- ✅ Usuários confirmam resolução

## Troubleshooting

### Bug ainda ocorre após fix
- [ ] Verificar se fix foi realmente aplicado (check git log)
- [ ] Confirmar que está testando versão correta
- [ ] Re-examinar causa raiz - pode haver múltiplas causas
- [ ] Adicionar mais logging para investigar

### Fix introduziu regressão
- [ ] Reverter fix imediatamente
- [ ] Analisar impacto mais amplo
- [ ] Criar testes para casos afetados
- [ ] Reimplementar com mais cuidado

### Não consegue reproduzir bug
- [ ] Pedir mais informações ao reporter
- [ ] Verificar ambiente (versões, config)
- [ ] Pode ser intermitente - adicionar logging
- [ ] Pode ser específico de produção

## Referências

- Issue Templates: `.github/ISSUE_TEMPLATE/`
- Testing Patterns: `tests/run_tests.php`
- Logging: `includes/ObservabilityLogger.php`
- Debugging Tips: `docs/OPERATIONS_GUIDE.md`
