# Workflow Prompts - Scenarios Reutilizáveis

Este diretório contém **workflow prompts** que definem processos estruturados step-by-step para tarefas comuns de desenvolvimento.

## O que são Workflow Prompts?

Workflow prompts são arquivos `.prompt.md` que definem:
- **Processo estruturado** com steps sequenciais
- **Validation gates** em cada step
- **Checklists** para garantir qualidade
- **Templates** e exemplos práticos
- **Comandos** específicos para executar
- **Success criteria** clara

São como "receitas" ou "playbooks" para realizar tarefas complexas de forma consistente.

## Prompts Disponíveis

### 📋 Code Review (`code-review.prompt.md`)

**Objetivo**: Revisar código de forma sistemática antes do merge

**Steps**:
1. Contexto e Escopo
2. Arquitetura e Design
3. Qualidade do Código
4. Segurança
5. Performance
6. Testes
7. Documentação
8. Multi-Tenancy e Compliance
9. Database Changes
10. Final Review

**Quando usar**:
- Pull Request está pronto para review
- Antes de mergear código
- Validar mudanças de outro dev

**Exemplo de uso**:
```
@plan Execute o workflow de code review para o PR #123 que 
adiciona sistema de templates de agents.
```

**Output**: Relatório completo de review com aprovação/mudanças/rejeição

---

### ⚙️ Feature Implementation (`feature-implementation.prompt.md`)

**Objetivo**: Implementar nova feature do zero ao deploy

**Steps**:
1. Análise e Planejamento
2. Database Design
3. Backend - Service Layer
4. Backend - API Endpoints
5. Frontend - UI Components
6. Testing
7. Documentation
8. Security Review
9. Integration & Smoke Tests
10. Code Review & Deploy

**Quando usar**:
- Nova funcionalidade a implementar
- Feature request aprovada
- Need to build something from scratch

**Exemplo de uso**:
```
@backend Execute o workflow de feature implementation para criar 
um sistema de webhooks personalizáveis por tenant.
```

**Output**: Feature completa, testada, documentada e pronta para produção

---

### 🐛 Bug Fix (`bug-fix.prompt.md`)

**Objetivo**: Diagnosticar, corrigir e validar bug fixes

**Steps**:
1. Reproduzir o Bug
2. Investigar Causa Raiz
3. Desenvolver Fix
4. Adicionar Testes
5. Validar Manualmente
6. Security & Performance Check
7. Update Documentation
8. Regression Testing
9. Code Review
10. Deploy & Monitor

**Quando usar**:
- Bug reportado por usuário
- Erro identificado em testes
- Issue aberta no GitHub

**Exemplo de uso**:
```
@backend Execute o workflow de bug fix para o Issue #456 onde 
agents com temperatura 0.0 não estão sendo salvos corretamente.
```

**Output**: Bug corrigido, testado, sem regressões

---

### 🗄️ Database Migration (`database-migration.prompt.md`)

**Objetivo**: Criar e deployar migrations de forma segura

**Steps**:
1. Análise e Planejamento
2. Determinar Número da Migration
3. Escrever SQL da Migration
4. Testar a Migration
5. Atualizar Services PHP
6. Criar/Atualizar Testes
7. Documentação
8. Production Deployment Plan
9. Execute Migration in Production
10. Monitor & Document

**Quando usar**:
- Novo schema de tabela
- Adicionar/modificar colunas
- Criar índices
- Mudar constraints

**Exemplo de uso**:
```
@dba Execute o workflow de database migration para adicionar 
suporte a soft deletes em todas as tabelas principais.
```

**Output**: Migration criada, testada e deployed com segurança

---

### 📝 Documentation (`documentation.prompt.md`)

**Objetivo**: Criar documentação clara e completa

**Steps**:
1. Identificar Necessidade
2. Pesquisar Contexto e Exemplos
3. Estruturar o Conteúdo
4. Escrever Conteúdo
5. Revisar e Refinar
6. Testar Instruções
7. Integrar com Docs Existentes
8. Update CHANGELOG
9. Code Comments
10. Final Review e Publish

**Quando usar**:
- Nova feature precisa de docs
- API endpoint adicionado
- Processo operacional mudou
- Onboarding de novos devs

**Exemplo de uso**:
```
@plan Execute o workflow de documentation para criar guia completo 
do sistema de multi-tenancy, incluindo setup e best practices.
```

**Output**: Documentação completa, testada, integrada e publicada

---

## Como Usar Workflow Prompts

### Sintaxe Básica

```
@mode Execute o workflow [nome-do-prompt] para [seu objetivo]
```

### Escolhendo o Mode Certo

| Workflow | Mode Recomendado | Por quê |
|----------|------------------|---------|
| Code Review | `plan` ou `security` | Análise sem modificar código |
| Feature Implementation | `backend` ou `frontend` | Precisa criar código |
| Bug Fix | `backend` ou `frontend` | Precisa modificar código |
| Database Migration | `dba` | Expertise em databases |
| Documentation | `plan` | Planejamento e escrita |

### Exemplos Completos

**Code Review**:
```
@plan Execute o workflow de code review para o PR #789. 
Foque especialmente em segurança e performance.
```

**Feature Implementation**:
```
@backend Execute o workflow de feature implementation para criar 
sistema de rate limiting customizável por tenant, incluindo:
- Limites por endpoint
- Limites por período (hora/dia/mês)
- Override manual por super-admin
```

**Bug Fix**:
```
@backend Execute o workflow de bug fix para resolver o problema 
onde jobs em retry ficam stuck quando o worker reinicia (Issue #555).
```

**Database Migration**:
```
@dba Execute o workflow de database migration para adicionar tabela 
de audit_api_calls que registra todas chamadas à API com:
- endpoint, method, status_code
- user_id, tenant_id, ip_address
- request_time, response_time
- Retenção de 90 dias
```

**Documentation**:
```
@plan Execute o workflow de documentation para criar runbook de 
disaster recovery, incluindo:
- Backup and restore procedures
- Failover steps
- Communication plan
- Rollback procedures
```

## Workflow vs Chatmode

**Chatmodes** são **personas** (quem você está conversando):
- Frontend developer
- DBA specialist
- Security engineer

**Workflows** são **processos** (o que fazer):
- Code review process
- Feature implementation process
- Bug fixing process

**Use juntos**:
```
@dba Execute o workflow de database migration para...
     ↑                      ↑
   Persona              Processo
```

## Vantagens dos Workflow Prompts

1. **Consistência**: Mesmo processo toda vez
2. **Qualidade**: Checklists garantem nada esquecido
3. **Treinamento**: Novos devs aprendem o processo
4. **Auditoria**: Steps documentados para compliance
5. **Eficiência**: Não precisa lembrar todos os passos
6. **Best Practices**: Incorpora padrões do projeto

## Estrutura de um Workflow Prompt

```markdown
---
name: Workflow Name
description: O que este workflow faz
mode: backend  # Mode recomendado
model: gpt-4o
temperature: 0.3
tools:
  - view
  - create
  - edit
---

# Prompt: Workflow Name

Descrição e objetivo do workflow.

## Inputs Necessários
- Input 1: [descrição]
- Input 2: [descrição]

## Steps

### Step 1: Nome do Step

**Ação**: O que fazer neste step

**Checklist**:
- [ ] Item 1
- [ ] Item 2

**Comandos**:
```bash
comando aqui
```

**Validação**:
- [ ] Critério 1
- [ ] Critério 2

---

### Step 2: Próximo Step

...

## Success Criteria

Workflow está completo quando:
- ✅ Critério 1
- ✅ Critério 2
```

## Customizando Workflows

Você pode adaptar workflows para suas necessidades:

```
@backend Execute o workflow de feature implementation, mas pule 
o step de frontend porque esta feature é backend-only.
```

Ou pedir foco em área específica:

```
@security Execute os steps 4 (Segurança) e 6 (Performance) do 
workflow de code review para este PR.
```

## Criando Novos Workflows

Para criar novo workflow:

1. **Identifique processo repetitivo**: Que tarefas você faz sempre?
2. **Mapeie steps**: Quebre em etapas lógicas
3. **Adicione validações**: Como garantir qualidade em cada step?
4. **Documente comandos**: Quais comandos executar?
5. **Defina success criteria**: Como saber que terminou?
6. **Crie arquivo**: `.github/prompts/nome-do-workflow.prompt.md`
7. **Teste**: Use em cenários reais
8. **Refine**: Melhore baseado em experiência

### Template Básico

```markdown
---
name: Seu Workflow
description: O que faz
mode: backend
model: gpt-4o
temperature: 0.3
---

# Prompt: Seu Workflow

## Objetivo
[O que este workflow resolve]

## Steps

### Step 1: [Nome]
**Ação**: [O que fazer]
**Validação**: [Como validar]

### Step 2: [Nome]
...

## Success Criteria
- ✅ [Critério 1]
- ✅ [Critério 2]
```

## Workflows Combinados

Você pode executar workflows em sequência:

```
# 1. Plan primeiro
@plan Execute o workflow de feature implementation até Step 1 
(Análise e Planejamento) para sistema de notificações.

# 2. Database
@dba Execute Step 2 do workflow (Database Design) baseado no 
plano anterior.

# 3. Backend
@backend Execute Steps 3-5 (Service Layer, API Endpoints, Testing).

# 4. Documentation
@plan Execute o workflow de documentation para documentar a feature.

# 5. Review
@security Execute o workflow de code review antes do merge.
```

## Checklist de Qualidade

Todo workflow deve garantir:

- ✅ **Functionality**: Feature/fix funciona como esperado
- ✅ **Tests**: Testes automatizados criados e passando
- ✅ **Security**: Sem vulnerabilidades introduzidas
- ✅ **Performance**: Sem degradação de performance
- ✅ **Documentation**: Docs atualizados
- ✅ **Code Quality**: Lint e static analysis passing
- ✅ **Backward Compatibility**: Não quebra código existente
- ✅ **Multi-Tenancy**: Isolamento preservado (se aplicável)
- ✅ **Audit**: Operações logadas (se aplicável)

## Referências

- **Chatmodes**: Veja `.github/chatmodes/` para personas especializadas
- **Copilot Instructions**: `.github/copilot-instructions.md`
- **Contributing Guide**: `docs/CONTRIBUTING.md`
- **Project Standards**: `docs/PROJECT_DESCRIPTION.md`

## Workflows Futuros

Workflows planejados para adicionar:

- [ ] **Refactoring**: Refatorar código legacy
- [ ] **Testing**: Adicionar testes a código existente
- [ ] **Performance Optimization**: Otimizar código lento
- [ ] **Security Audit**: Auditoria completa de segurança
- [ ] **Dependency Update**: Atualizar dependências com segurança
- [ ] **Rollback**: Reverter deploy com problemas

## Support

Para dúvidas ou melhorias nos workflows, abra uma issue no GitHub.
