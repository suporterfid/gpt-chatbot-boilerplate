---
name: Code Review
description: Revisão estruturada de código com checklist de qualidade e segurança
mode: plan
model: gpt-4o
temperature: 0.3
tools:
  - view
  - bash
  - codeql_checker
  - gh-advisory-database
---

# Prompt: Code Review

Este workflow guia uma revisão completa de código, focando em qualidade, segurança, performance e manutenibilidade.

## Objetivo

Realizar code review sistemático de mudanças no código, identificando problemas e sugerindo melhorias antes do merge.

## Pré-requisitos

- [ ] Pull Request criado
- [ ] CI/CD passou (testes, lint, build)
- [ ] Autor descreveu mudanças no PR

## Steps

### Step 1: Contexto e Escopo

**Ação**: Entender o que está sendo mudado e por quê

**Perguntas**:
1. Qual problema está sendo resolvido?
2. Quais arquivos foram modificados?
3. Qual o impacto das mudanças?

**Validação**:
- [ ] Objetivo claro e bem definido
- [ ] Mudanças alinhadas com objetivo
- [ ] Escopo razoável (não muito grande)

**Comandos**:
```bash
# Ver arquivos modificados
git diff --name-only origin/main

# Ver estatísticas
git diff --stat origin/main

# Ver mudanças completas
git diff origin/main
```

---

### Step 2: Arquitetura e Design

**Ação**: Avaliar decisões de arquitetura e design patterns

**Checklist**:
- [ ] **Separação de responsabilidades** - Classes/funções têm propósito único?
- [ ] **Abstração apropriada** - Nível de abstração faz sentido?
- [ ] **Reutilização** - Código duplicado foi evitado?
- [ ] **Consistência** - Segue padrões do projeto?
- [ ] **Extensibilidade** - Fácil de estender no futuro?

**Perguntas**:
1. A solução segue a arquitetura existente?
2. Há acoplamento excessivo entre componentes?
3. Abstrações são claras e necessárias?
4. Código duplicado poderia ser extraído?

**Validação**:
- [ ] Design patterns apropriados
- [ ] Clean architecture respeitada
- [ ] Sem over-engineering

---

### Step 3: Qualidade do Código

**Ação**: Revisar qualidade e legibilidade do código

**Checklist PHP**:
- [ ] **PSR-12** - Coding standards seguidos?
- [ ] **Strict types** - `declare(strict_types=1);` presente?
- [ ] **Type hints** - Parâmetros e retornos tipados?
- [ ] **Nomenclatura** - Classes PascalCase, métodos camelCase, constantes UPPER_SNAKE_CASE?
- [ ] **Early returns** - Evita aninhamento excessivo?
- [ ] **Small functions** - Funções com responsabilidade única?
- [ ] **No magic numbers** - Valores hardcoded são constantes?

**Checklist JavaScript**:
- [ ] **ES6+** - const/let, arrow functions, template literals?
- [ ] **Strict equality** - Usa `===` e `!==`?
- [ ] **ESLint** - Sem warnings?
- [ ] **No jQuery** - Usa DOM nativo?
- [ ] **Promises/async** - Callbacks evitados?

**Validação**:
- [ ] Código legível e auto-explicativo
- [ ] Comentários apenas onde necessário
- [ ] Sem code smells óbvios

**Comandos**:
```bash
# PHP static analysis
composer run analyze

# JavaScript lint
npm run lint

# Check syntax
php -l includes/file.php
node --check file.js
```

---

### Step 4: Segurança

**Ação**: Identificar vulnerabilidades de segurança

**Checklist**:
- [ ] **SQL Injection** - Prepared statements usados?
- [ ] **XSS** - HTML sanitizado?
- [ ] **CSRF** - Tokens CSRF em forms?
- [ ] **Authentication** - Endpoints protegidos?
- [ ] **Authorization** - Permissões verificadas?
- [ ] **Multi-tenancy** - Queries filtram por tenant_id?
- [ ] **Secrets** - API keys não hardcoded?
- [ ] **File Upload** - Validação completa (tipo, tamanho, conteúdo)?
- [ ] **Rate Limiting** - APIs protegidas?
- [ ] **Audit Logging** - Operações sensíveis logadas?

**Validação**:
- [ ] CodeQL sem novos alertas
- [ ] Dependências sem vulnerabilidades
- [ ] Sem exposição de dados sensíveis

**Comandos**:
```bash
# Run CodeQL
# Use codeql_checker tool

# Check dependencies
composer audit
npm audit

# Check for secrets
git log -p | grep -iE "password|api_key|secret|token"
```

---

### Step 5: Performance

**Ação**: Avaliar impacto em performance

**Checklist**:
- [ ] **N+1 queries** - Evitados?
- [ ] **Lazy loading** - Usado quando apropriado?
- [ ] **Caching** - Dados repetidos cacheados?
- [ ] **Bulk operations** - Loops com DB otimizados?
- [ ] **Memory** - Grandes arrays evitados?
- [ ] **Indexação** - DB queries usam índices?

**Perguntas**:
1. Há queries que podem ser otimizadas?
2. Loops grandes podem ser melhorados?
3. Há necessidade de pagination?
4. Cache seria útil aqui?

**Validação**:
- [ ] Sem degradação de performance
- [ ] Queries eficientes
- [ ] Uso de memória razoável

---

### Step 6: Testes

**Ação**: Verificar cobertura e qualidade dos testes

**Checklist**:
- [ ] **Unit tests** - Lógica crítica testada?
- [ ] **Integration tests** - Fluxos completos testados?
- [ ] **Edge cases** - Casos extremos cobertos?
- [ ] **Error handling** - Erros testados?
- [ ] **Assertions** - Asserts claros e específicos?
- [ ] **Nomenclatura** - Nomes descritivos?

**Validação**:
- [ ] Todos os testes passam
- [ ] Cobertura adequada (novo código testado)
- [ ] Testes não quebram facilmente

**Comandos**:
```bash
# Run all tests
php tests/run_tests.php

# Run specific test
php tests/test_new_feature.php

# Smoke tests
bash scripts/smoke_test.sh
```

---

### Step 7: Documentação

**Ação**: Validar documentação e comentários

**Checklist**:
- [ ] **PHPDoc** - Métodos públicos documentados?
- [ ] **JSDoc** - Funções complexas documentadas?
- [ ] **README** - Atualizado se necessário?
- [ ] **API docs** - Endpoints novos documentados?
- [ ] **Comments** - Código complexo explicado?
- [ ] **CHANGELOG** - Mudanças listadas?

**Validação**:
- [ ] Documentação clara e atualizada
- [ ] Comentários úteis (não óbvios)
- [ ] API docs completos

---

### Step 8: Multi-Tenancy e Compliance

**Ação**: Verificar isolamento de tenants e compliance

**Checklist**:
- [ ] **Tenant isolation** - Queries filtram por tenant_id?
- [ ] **Resource ownership** - Validado antes de acesso?
- [ ] **Audit logs** - Operações auditadas?
- [ ] **PII** - Dados pessoais protegidos?
- [ ] **Consent** - Consentimento verificado (se aplicável)?
- [ ] **Retention** - Políticas de retenção consideradas?

**Validação**:
- [ ] Sem vazamento entre tenants
- [ ] LGPD/GDPR compliance mantido
- [ ] Audit trail completo

---

### Step 9: Database Changes

**Ação**: Revisar mudanças de schema e migrations

**Checklist** (se aplicável):
- [ ] **Migration file** - Criado com nomenclatura correta?
- [ ] **Backward compatible** - Mudança quebra algo?
- [ ] **Indexes** - Criados onde necessário?
- [ ] **Foreign keys** - Integridade referencial?
- [ ] **SQLite + MySQL** - Compatível com ambos?
- [ ] **Rollback** - DOWN migration presente?

**Validação**:
- [ ] Migration executa sem erros
- [ ] Schema atualizado corretamente
- [ ] Dados migrados sem perda

**Comandos**:
```bash
# Run migrations
php scripts/run_migrations.php

# Check schema
sqlite3 data/chatbot.db ".schema table_name"
```

---

### Step 10: Final Review

**Ação**: Revisão final e decisão

**Checklist**:
- [ ] Todos os steps anteriores passaram
- [ ] CI/CD verde
- [ ] Aprovação de reviewer(s)
- [ ] Merge conflicts resolvidos
- [ ] Commit messages claros

**Decisão**:
- ✅ **Approve** - Se tudo OK
- 🔄 **Request Changes** - Se há problemas
- 💬 **Comment** - Se precisa discussão

---

## Output do Review

```markdown
## Code Review Summary

### ✅ Aprovado / ⚠️ Mudanças Necessárias / ❌ Rejeitar

**Resumo**: [Breve descrição do PR e conclusão]

### Pontos Positivos
- [O que está bem feito]
- [Boas práticas observadas]

### Problemas Identificados

#### Critical (deve corrigir)
- [ ] [Problema crítico 1]
- [ ] [Problema crítico 2]

#### High (recomendado corrigir)
- [ ] [Problema importante 1]
- [ ] [Problema importante 2]

#### Medium (sugestões)
- [ ] [Sugestão de melhoria 1]
- [ ] [Sugestão de melhoria 2]

#### Low (nice to have)
- [ ] [Melhoria opcional 1]

### Comentários Inline
[Link para comentários específicos no código]

### Security Concerns
[Vulnerabilidades identificadas, se houver]

### Performance Considerations
[Impactos de performance, se houver]

### Next Steps
1. [O que o autor deve fazer]
2. [Próximas ações]
```

## Referências

- Coding Standards: `.github/copilot-instructions.md`
- Security Model: `docs/SECURITY_MODEL.md`
- Architecture: `docs/PROJECT_DESCRIPTION.md`
- Contributing: `docs/CONTRIBUTING.md`
