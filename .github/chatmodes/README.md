# Chat Modes - Personas Especializadas

Este diretório contém **modos de chat customizados** (chatmodes) que definem personas especializadas para diferentes áreas de desenvolvimento.

## O que são Chatmodes?

Chatmodes são arquivos `.chatmode.md` que definem:
- **Persona especializada** com conhecimento específico
- **Ferramentas permitidas** (read-only, full-access, specific tools)
- **Modelo e temperatura** otimizados para a tarefa
- **Contexto do projeto** relevante para a especialização
- **Padrões e boas práticas** da área

## Chatmodes Disponíveis

### 🎯 Plan Mode (`plan.chatmode.md`)

**Especialização**: Planejamento e Análise  
**Acesso**: Read-only  
**Quando usar**:
- Analisar arquitetura antes de fazer mudanças
- Criar planos de implementação detalhados
- Avaliar impactos de features
- Identificar riscos e dependências

**Exemplo de uso**:
```
@plan Analise a arquitetura atual do sistema de agents e sugira 
como implementar um sistema de templates de agents reutilizáveis.
```

**Ferramentas disponíveis**:
- `view` - Ler arquivos
- `bash` (read-only) - Comandos de leitura (ls, grep, git log)
- `github_*` - Buscar código, issues, PRs

**Output típico**: Plano detalhado com checklist, impactos, riscos e validações

---

### 🗄️ DBA Mode (`dba.chatmode.md`)

**Especialização**: Banco de Dados  
**Acesso**: Database-focused  
**Quando usar**:
- Criar database migrations
- Otimizar queries e índices
- Projetar schema de novas tabelas
- Diagnosticar problemas de performance no DB

**Exemplo de uso**:
```
@dba Crie uma migration para adicionar suporte a tags nos agents, 
incluindo tabela de relacionamento many-to-many e índices apropriados.
```

**Ferramentas disponíveis**:
- Leitura e criação de arquivos
- Bash completo (executar migrations, queries)
- Acesso ao SQLite/MySQL

**Output típico**: Migration SQL, testes de schema, documentação

---

### 🎨 Frontend Mode (`frontend.chatmode.md`)

**Especialização**: JavaScript e UI/UX  
**Acesso**: Frontend-focused  
**Quando usar**:
- Desenvolver/modificar `chatbot-enhanced.js`
- Criar componentes no Admin SPA
- Implementar novas UI features
- Fix de bugs no frontend
- Melhorias de UX

**Exemplo de uso**:
```
@frontend Adicione suporte a drag-and-drop de arquivos no widget 
do chatbot, com preview das imagens antes de enviar.
```

**Ferramentas disponíveis**:
- Edição de JS/CSS
- Playwright (testar UI no browser)
- ESLint

**Output típico**: Código JavaScript/CSS, screenshots de resultado

---

### ⚙️ Backend Mode (`backend.chatmode.md`)

**Especialização**: PHP e APIs  
**Acesso**: Backend-focused  
**Quando usar**:
- Criar/modificar services PHP
- Implementar endpoints REST
- Integrar APIs externas
- Implementar lógica de negócio
- Fix de bugs no backend

**Exemplo de uso**:
```
@backend Implemente um serviço de notificações que permita enviar 
emails e webhooks quando um job falha após todas as tentativas.
```

**Ferramentas disponíveis**:
- Edição de código PHP
- Composer, PHPStan
- Execução de testes

**Output típico**: Services PHP, endpoints API, testes

---

### 🚀 DevOps Mode (`devops.chatmode.md`)

**Especialização**: Deployment e Infraestrutura  
**Acesso**: Infrastructure-focused  
**Quando usar**:
- Configurar Docker/Kubernetes
- Criar scripts de deploy
- Configurar CI/CD
- Setup de monitoring
- Backup e restore procedures
- Load testing

**Exemplo de uso**:
```
@devops Configure um pipeline de CI/CD no GitHub Actions que 
execute testes, build Docker image, e faça deploy automático 
para staging quando há merge na branch develop.
```

**Ferramentas disponíveis**:
- Edição de Dockerfiles, K8s charts
- Scripts de deployment
- Acesso a tools de infra

**Output típico**: Configs de infra, scripts, documentação operacional

---

### 🔒 Security Mode (`security.chatmode.md`)

**Especialização**: Segurança e Compliance  
**Acesso**: Security-focused  
**Temperatura**: 0.2 (mais determinístico)  
**Quando usar**:
- Revisar código para vulnerabilidades
- Implementar features de segurança
- Análise de compliance (LGPD/GDPR)
- Setup de RBAC e autenticação
- Auditar dependencies

**Exemplo de uso**:
```
@security Analise o código de upload de arquivos e identifique 
possíveis vulnerabilidades. Sugira melhorias de segurança.
```

**Ferramentas disponíveis**:
- CodeQL checker
- GitHub Advisory Database
- Security scanning tools

**Output típico**: Relatório de vulnerabilidades, fixes, recomendações

---

## Como Usar Chatmodes

### Sintaxe Básica

```
@mode-name [sua solicitação]
```

### Exemplos

**Planning**:
```
@plan Analise o impacto de adicionar suporte a múltiplos idiomas 
no chatbot. Quais componentes precisam mudar?
```

**Database**:
```
@dba Crie uma migration para adicionar soft deletes na tabela agents
```

**Frontend**:
```
@frontend Adicione um botão de "Clear History" no widget do chatbot
```

**Backend**:
```
@backend Implemente rate limiting por tenant no admin-api.php
```

**DevOps**:
```
@devops Configure health checks no docker-compose para restart automático
```

**Security**:
```
@security Revise o sistema de autenticação e identifique melhorias
```

## Quando Usar Cada Mode

| Tarefa | Mode Recomendado | Alternativa |
|--------|------------------|-------------|
| Planejar nova feature | `plan` | - |
| Criar migration | `dba` | - |
| UI nova ou mudança | `frontend` | - |
| Endpoint REST | `backend` | - |
| Docker/K8s config | `devops` | - |
| Security review | `security` | `plan` |
| Bug fix frontend | `frontend` | - |
| Bug fix backend | `backend` | - |
| Performance DB | `dba` | `devops` |
| CI/CD pipeline | `devops` | - |
| RBAC/Auth | `security` | `backend` |
| Monitoring setup | `devops` | - |
| Code review | `security` ou `plan` | - |

## Combinando Modes

Você pode usar múltiplos modes em sequência:

```
# 1. Plan primeiro
@plan Como implementar sistema de notificações?

# 2. DBA para database
@dba Crie a migration para tabela de notificações

# 3. Backend para lógica
@backend Implemente NotificationService

# 4. DevOps para deployment
@devops Configure worker para processar notificações
```

## Vantagens dos Chatmodes

1. **Especialização**: Cada mode tem expertise profunda em sua área
2. **Contexto**: Mode já conhece padrões e práticas do projeto
3. **Ferramentas**: Apenas tools relevantes disponíveis
4. **Segurança**: Read-only modes não podem modificar código
5. **Consistência**: Seguem padrões estabelecidos do projeto

## Estrutura de um Chatmode

```markdown
---
name: Mode Name
description: O que este mode faz
model: gpt-4o  # ou gpt-4o-mini
temperature: 0.3  # 0.0-1.0
tools:
  - view
  - create
  - edit
  - bash
permissions: read-only | full | specific-area
---

# Modo Name - Especialista em X

Descrição detalhada da persona e capacidades.

## Suas Responsabilidades
- Responsabilidade 1
- Responsabilidade 2

## Contexto do Projeto
Informações específicas relevantes para esta área.

## Padrões e Boas Práticas
Guias e exemplos de código.

## Output Esperado
Template do que o mode deve produzir.
```

## Criando Novos Chatmodes

Para criar um novo chatmode:

1. **Identifique a necessidade**: Há uma especialização faltando?
2. **Defina escopo**: O que este mode deve fazer?
3. **Escolha ferramentas**: Quais tools são necessários?
4. **Documente contexto**: Que conhecimento específico precisa?
5. **Crie arquivo**: `.github/chatmodes/nome.chatmode.md`
6. **Teste**: Use o mode em casos reais
7. **Refine**: Ajuste baseado em feedback

## Referências

- **Prompts**: Veja `.github/prompts/` para workflows reutilizáveis
- **Copilot Instructions**: `.github/copilot-instructions.md`
- **Project Description**: `docs/PROJECT_DESCRIPTION.md`
- **Architecture**: `docs/`

## Support

Para dúvidas ou melhorias nos chatmodes, abra uma issue no GitHub.
