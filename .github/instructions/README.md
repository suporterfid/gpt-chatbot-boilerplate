# Instruções Condicionais por Escopo

Esta pasta contém arquivos de instruções modulares que são aplicados seletivamente pelo GitHub Copilot baseado em padrões glob. Isso permite que o AI receba contexto específico e relevante apenas para os arquivos que estão sendo editados.

## 📁 Estrutura

```
.github/instructions/
├── README.md                       # Este arquivo
├── admin-ui.instructions.md        # Admin UI (public/admin/)
├── backend-php.instructions.md     # Backend PHP (includes/, APIs)
├── database.instructions.md        # Database migrations (db/migrations/)
├── documentation.instructions.md   # Documentação (docs/)
├── frontend.instructions.md        # Frontend (JS, CSS, HTML)
├── infrastructure.instructions.md  # DevOps (Docker, K8s, Terraform, CI/CD)
├── scripts.instructions.md         # Scripts (scripts/)
└── tests.instructions.md           # Testes (tests/)
```

## 🎯 Como Funciona

Cada arquivo `.instructions.md` contém:

1. **YAML Frontmatter**: Define quando as instruções são aplicadas
   ```yaml
   ---
   applyTo: "pattern/**/*.{ext,ext2}"
   description: "Descrição do escopo"
   ---
   ```

2. **Conteúdo Markdown**: Instruções específicas para aquele escopo
   - Padrões de código
   - Boas práticas
   - Exemplos
   - Checklists

### Exemplo de Frontmatter

```yaml
---
applyTo: "**/*.php"
description: "Regras específicas para código backend PHP"
---
```

## 📚 Arquivos de Instruções

### frontend.instructions.md
- **Padrão**: `**/*.{js,css,html}`
- **Escopo**: JavaScript, CSS, HTML (widget, UI)
- **Conteúdo**:
  - Vanilla JS best practices
  - CSS organization (BEM, mobile-first)
  - SSE/WebSocket patterns
  - XSS prevention
  - Browser compatibility

### backend-php.instructions.md
- **Padrão**: `**/*.php`
- **Escopo**: Todo código PHP (includes/, endpoints)
- **Conteúdo**:
  - PSR-12 coding standards
  - Type hints e strict types
  - Dependency injection
  - Security (SQL injection, input validation)
  - Dual API support (Chat Completions + Responses)
  - Multi-tenancy patterns

### tests.instructions.md
- **Padrão**: `tests/**/*.php`
- **Escopo**: Testes unitários, integração, carga
- **Conteúdo**:
  - Test structure e nomenclatura
  - Setup/teardown patterns
  - Assertions claras
  - Smoke tests
  - Load testing (k6)
  - Security testing

### scripts.instructions.md
- **Padrão**: `scripts/**/*.{php,sh}`
- **Escopo**: Scripts de manutenção e automação
- **Conteúdo**:
  - Script structure (PHP e Shell)
  - Idempotency patterns
  - Logging e error handling
  - Dry-run mode
  - Backup strategies
  - Worker patterns

### documentation.instructions.md
- **Padrão**: `docs/**/*.md`
- **Escopo**: Toda documentação
- **Conteúdo**:
  - Markdown formatting
  - Documentation structure
  - API documentation patterns
  - Changelog format (Keep a Changelog)
  - Contributing guidelines

### infrastructure.instructions.md
- **Padrão**: `{Dockerfile,docker-compose*.yml,helm/**/*,terraform/**/*,.github/workflows/*.yml}`
- **Escopo**: Docker, K8s, Terraform, CI/CD
- **Conteúdo**:
  - Dockerfile best practices
  - Docker Compose patterns
  - Kubernetes/Helm charts
  - Terraform modules
  - GitHub Actions workflows
  - Security scanning

### admin-ui.instructions.md
- **Padrão**: `public/admin/**/*.{js,css,html}`
- **Escopo**: Interface administrativa
- **Conteúdo**:
  - SPA patterns (Vanilla JS)
  - State management
  - API client patterns
  - Modal/form components
  - Accessibility (ARIA, keyboard nav)
  - UI/UX best practices

### database.instructions.md
- **Padrão**: `db/migrations/*.sql`
- **Escopo**: Database migrations
- **Conteúdo**:
  - Migration naming conventions
  - Schema design patterns
  - Index strategies
  - Foreign keys e constraints
  - Data migrations
  - Rollback strategies

## 🎨 Benefícios

### 1. Contexto Seletivo
O AI recebe apenas instruções relevantes para o arquivo sendo editado, evitando confusão e melhorando a qualidade das sugestões.

### 2. Redução de Poluição
Sem instruções condicionais, o AI receberia todas as regras de todos os domínios, tornando o contexto poluído e menos efetivo.

### 3. Manutenibilidade
Cada domínio tem suas instruções em arquivo separado, facilitando updates e manutenção.

### 4. Especialização
Permite regras altamente específicas para cada tecnologia/domínio sem conflitos.

### 5. Performance
Menos contexto = respostas mais rápidas e focadas.

## 📝 Como Adicionar Novas Instruções

1. **Criar novo arquivo** `.instructions.md` nesta pasta
2. **Adicionar frontmatter** YAML com `applyTo` e `description`
3. **Escrever conteúdo** específico para o escopo
4. **Incluir exemplos** práticos e checklists
5. **Atualizar este README** com informações do novo arquivo

### Template

```markdown
---
applyTo: "pattern/**/*.ext"
description: "Descrição breve do escopo"
---

# Instruções para [Domínio] - gpt-chatbot-boilerplate

## Arquivos Alvo
- Lista de arquivos/padrões cobertos

## Filosofia
Princípios e guidelines gerais

## Padrões de Código
Regras específicas de código

## Exemplos
Exemplos práticos

## Checklist de Revisão
- [ ] Item 1
- [ ] Item 2
```

## 🔍 Padrões Glob Suportados

Os padrões glob seguem a sintaxe padrão:

- `**/*` - Todos os arquivos em todos os diretórios
- `*.js` - Arquivos JS na raiz
- `**/*.js` - Arquivos JS em qualquer lugar
- `**/*.{js,ts}` - Múltiplas extensões
- `src/**/*.test.js` - Pattern específico
- `{Dockerfile,*.yml}` - Múltiplos padrões

## 📖 Referências

- [GitHub Copilot Documentation](https://docs.github.com/en/copilot)
- [Glob Pattern Reference](https://en.wikipedia.org/wiki/Glob_(programming))
- [Project Contributing Guide](../../docs/CONTRIBUTING.md)

## 🤝 Contribuindo

Ao modificar instruções:

1. Manter consistência com `.github/copilot-instructions.md`
2. Seguir formato e estrutura existente
3. Incluir exemplos práticos
4. Testar que padrões glob funcionam corretamente
5. Atualizar este README se adicionar novos arquivos

## 📋 Status

| Arquivo | Escopo | Linhas | Status |
|---------|--------|--------|--------|
| frontend.instructions.md | JS/CSS/HTML | 176 | ✅ |
| backend-php.instructions.md | PHP | 359 | ✅ |
| tests.instructions.md | Tests | 435 | ✅ |
| scripts.instructions.md | Scripts | 622 | ✅ |
| documentation.instructions.md | Docs | 526 | ✅ |
| infrastructure.instructions.md | DevOps | 713 | ✅ |
| admin-ui.instructions.md | Admin UI | 704 | ✅ |
| database.instructions.md | Migrations | 628 | ✅ |

**Total**: 4,163 linhas de instruções contextuais

---

**Nota**: Este sistema de instruções condicionais complementa (não substitui) o arquivo principal `.github/copilot-instructions.md`, que contém regras globais aplicáveis a todo o projeto.
