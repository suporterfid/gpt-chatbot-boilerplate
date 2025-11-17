# GitHub Copilot Configuration

Este diretório contém configurações para GitHub Copilot, incluindo instruções gerais, chat modes especializados e workflow prompts reutilizáveis.

## 📁 Estrutura

```
.github/
├── README_COPILOT.md                    # Este arquivo
├── copilot-instructions.md      # Instruções globais do Copilot
├── chatmodes/                   # Personas especializadas
│   ├── README.md
│   ├── plan.chatmode.md         # 🎯 Planejamento (read-only)
│   ├── dba.chatmode.md          # 🗄️ Database specialist
│   ├── frontend.chatmode.md     # 🎨 JavaScript/UI expert
│   ├── backend.chatmode.md      # ⚙️ PHP/API expert
│   ├── devops.chatmode.md       # 🚀 Deployment/Infra expert
│   └── security.chatmode.md     # 🔒 Security/Compliance expert
└── prompts/                     # Workflows estruturados
    ├── README.md
    ├── code-review.prompt.md              # 📋 Code review process
    ├── feature-implementation.prompt.md   # ⚙️ Feature development
    ├── bug-fix.prompt.md                  # 🐛 Bug fixing process
    ├── database-migration.prompt.md       # 🗄️ DB migration workflow
    └── documentation.prompt.md            # 📝 Documentation workflow
```

## 🚀 Quick Start

### Para Desenvolvedores

**Planejando uma feature**:
```
@plan Analise o impacto de adicionar suporte a webhooks personalizados 
por tenant. Quais componentes precisam ser modificados?
```

**Desenvolvendo backend**:
```
@backend Implemente um serviço de notificações que envie emails 
quando um job falha após todas as tentativas de retry.
```

**Trabalhando com banco de dados**:
```
@dba Crie uma migration para adicionar soft deletes nas tabelas 
principais, mantendo compatibilidade SQLite e MySQL.
```

**Desenvolvendo frontend**:
```
@frontend Adicione um botão de "Export Chat" no widget que permita 
baixar a conversa em formato JSON ou TXT.
```

### Para DevOps

**Configurando infra**:
```
@devops Configure um pipeline de CI/CD que execute testes, build 
Docker image, e faça deploy automático para staging.
```

**Configurando monitoring**:
```
@devops Configure alertas no Grafana para notificar quando a taxa 
de erro da API exceder 5% ou latência p95 > 2s.
```

### Para Security

**Análise de segurança**:
```
@security Analise o sistema de autenticação e autorização. 
Identifique possíveis vulnerabilidades e sugira melhorias.
```

**Code review de segurança**:
```
@security Execute o workflow de code review focando em segurança 
para o PR #123 que adiciona upload de arquivos.
```

## 📚 Documentação Completa

- **Chatmodes**: [`chatmodes/README.md`](chatmodes/README.md) - Personas especializadas
- **Prompts**: [`prompts/README.md`](prompts/README.md) - Workflows reutilizáveis
- **Copilot Instructions**: [`copilot-instructions.md`](copilot-instructions.md) - Regras globais

## 💡 Conceitos

### Chatmodes (Personas)

**O que são**: Especialistas virtuais com conhecimento profundo em áreas específicas

**Quando usar**: Quando você precisa de expertise específica

**Exemplo**:
- `@dba` para trabalhar com banco de dados
- `@frontend` para trabalhar com JavaScript/UI
- `@backend` para trabalhar com PHP/APIs

### Workflow Prompts (Processos)

**O que são**: Processos estruturados step-by-step para tarefas complexas

**Quando usar**: Quando você quer seguir um processo padronizado

**Exemplo**:
```
@backend Execute o workflow de feature implementation para criar 
sistema de rate limiting customizável.
```

### Combinando Ambos

Você pode usar chatmodes COM workflows:

```
@dba Execute o workflow de database migration para adicionar 
tabela de audit logs.
     ↑                    ↑
  Persona            Processo
```

## 🎯 Casos de Uso Comuns

### Planejamento de Features

```markdown
**Cenário**: Product manager pediu uma nova feature

**Abordagem**:
1. @plan Analise requisitos e crie plano de implementação
2. @dba Design do schema se precisar de DB
3. @backend ou @frontend Implementação
4. @security Review de segurança
5. @devops Plan de deployment
```

### Resolução de Bugs

```markdown
**Cenário**: Bug reportado em produção

**Abordagem**:
1. @plan Analise logs e identifique causa raiz (read-only)
2. @backend ou @frontend Execute workflow de bug fix
3. @security Verifique se fix não introduz vulnerabilidades
4. @devops Plan de deploy do hotfix
```

### Criação de Database Schema

```markdown
**Cenário**: Nova feature precisa de tabelas

**Abordagem**:
1. @plan Design conceitual do schema
2. @dba Execute workflow de database migration
3. @backend Atualize services para usar novo schema
4. @security Revise isolamento multi-tenant
```

### Code Review

```markdown
**Cenário**: PR pronto para review

**Abordagem**:
1. @plan Execute workflow de code review - overview geral
2. @security Foque em vulnerabilidades
3. @backend ou @frontend Revise qualidade do código
4. @devops Revise impacto em deployment
```

### Documentação

```markdown
**Cenário**: Feature nova precisa de docs

**Abordagem**:
1. @plan Execute workflow de documentation
2. @backend Adicione code comments
3. @frontend Documente componentes UI
4. @devops Atualize runbooks
```

## 🛠️ Ferramentas por Chatmode

| Chatmode | Ferramentas Principais |
|----------|------------------------|
| Plan | `view`, `bash` (read-only), `github_*` |
| DBA | `view`, `create`, `edit`, `bash`, SQL tools |
| Frontend | `view`, `create`, `edit`, `playwright`, `npm` |
| Backend | `view`, `create`, `edit`, `bash`, `composer` |
| DevOps | `view`, `create`, `edit`, `bash`, Docker, K8s |
| Security | `codeql_checker`, `gh-advisory-database`, scanning |

## ✨ Melhores Práticas

### 1. Use o Mode Certo

```
❌ @backend Analise arquitetura e crie plano
✅ @plan Analise arquitetura e crie plano

❌ @plan Crie a migration de banco de dados
✅ @dba Crie a migration de banco de dados
```

### 2. Seja Específico

```
❌ @backend Melhore o código
✅ @backend Refatore AgentService para extrair validação em 
   método separado e adicionar type hints em todos os parâmetros
```

### 3. Use Workflows para Tarefas Complexas

```
❌ @backend Crie uma nova feature de notificações
✅ @backend Execute o workflow de feature implementation para 
   sistema de notificações incluindo email, SMS e webhooks
```

### 4. Combine Modes Sequencialmente

```
1. @plan Crie plano de implementação
2. @dba Execute parte de database
3. @backend Execute parte de backend
4. @security Execute security review
```

### 5. Forneça Contexto

```
❌ @backend Fix the bug
✅ @backend Execute workflow de bug fix para Issue #123 onde 
   agents com temperature 0.0 não estão sendo salvos. 
   Error: "Column temperature cannot be null"
```

## 📊 Matriz de Decisão

**Escolha o chatmode baseado na tarefa**:

| Tarefa | Chatmode | Workflow |
|--------|----------|----------|
| Entender codebase | `plan` | - |
| Criar feature | `backend`/`frontend` | `feature-implementation` |
| Fix bug | `backend`/`frontend` | `bug-fix` |
| Migration DB | `dba` | `database-migration` |
| Documentar | `plan` | `documentation` |
| Code review | `plan`/`security` | `code-review` |
| Deploy config | `devops` | - |
| Security audit | `security` | - |
| Performance DB | `dba` | - |
| CI/CD setup | `devops` | - |

## 🔄 Workflow Típico de Desenvolvimento

```mermaid
graph TD
    A[Nova Feature Request] --> B[@plan: Análise]
    B --> C{Precisa DB?}
    C -->|Sim| D[@dba: Migration]
    C -->|Não| E[@backend/frontend: Implementação]
    D --> E
    E --> F[@security: Security Review]
    F --> G{Aprovado?}
    G -->|Não| E
    G -->|Sim| H[@plan: Code Review]
    H --> I{Aprovado?}
    I -->|Não| E
    I -->|Sim| J[@devops: Deploy]
    J --> K[Produção]
```

## 📖 Aprendizado Progressivo

### Nível 1: Iniciante

- Use chatmodes simples: `@plan`, `@backend`, `@frontend`
- Tarefas específicas e pequenas
- Pedir exemplos e explicações

### Nível 2: Intermediário

- Use workflows estruturados
- Combine múltiplos chatmodes
- Tarefas mais complexas

### Nível 3: Avançado

- Customize workflows para suas necessidades
- Crie novos chatmodes especializados
- Otimize processos do time

## 🤝 Contribuindo

Para melhorar chatmodes ou workflows:

1. Identifique gap ou melhoria
2. Crie/modifique arquivo `.chatmode.md` ou `.prompt.md`
3. Teste em casos reais
4. Abra PR com mudanças
5. Documente no README

## 📚 Recursos

- **Projeto**: [`docs/PROJECT_DESCRIPTION.md`](../docs/PROJECT_DESCRIPTION.md)
- **API**: [`docs/api.md`](../docs/api.md)
- **Contributing**: [`docs/CONTRIBUTING.md`](../docs/CONTRIBUTING.md)
- **Architecture**: [`docs/`](../docs/)

## 💬 Support

Para dúvidas:
- Abra uma issue no GitHub
- Consulte documentação em `docs/`
- Revise exemplos neste diretório

---

**Dica Final**: Comece simples com `@plan` para entender o projeto, depois evolua para chatmodes especializados conforme fica mais familiarizado! 🚀
