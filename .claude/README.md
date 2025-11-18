# Claude Code Configuration

Este diretório contém a configuração do Claude Code para o projeto GPT Chatbot Boilerplate.

## Estrutura

```
.claude/
├── context.md              # Contexto completo do projeto
├── mcp.json               # Configuração de MCP servers
├── commands/              # Comandos customizados
│   ├── run-tests.md
│   ├── check-code-quality.md
│   ├── create-migration.md
│   ├── add-agent.md
│   ├── review-security.md
│   ├── add-channel.md
│   ├── explain-flow.md
│   ├── debug-issue.md
│   ├── setup-monitoring.md
│   ├── optimize-performance.md
│   ├── generate-api-docs.md
│   └── refactor-service.md
└── README.md              # Este arquivo
```

## Contexto do Projeto (context.md)

O arquivo [context.md](context.md) contém informações essenciais sobre o projeto:

- Overview e tecnologias utilizadas
- Estrutura do projeto
- Padrões de arquitetura
- Fluxos principais
- Comandos comuns
- Guias de desenvolvimento

O Claude Code usa este arquivo para entender o contexto do projeto e fornecer assistência mais precisa.

## Comandos Customizados

Os comandos customizados estão disponíveis no diretório `commands/`. Para usá-los, digite `/` no Claude Code seguido do nome do comando.

### Comandos Disponíveis

#### `/run-tests`
Executa a suite de testes do projeto e identifica falhas.

**Uso**: `/run-tests`

#### `/check-code-quality`
Executa verificações de qualidade de código (PHPStan e linting).

**Uso**: `/check-code-quality`

#### `/create-migration`
Assistente para criar novas migrações de banco de dados.

**Uso**: `/create-migration`

**O que faz**:
- Determina o próximo número de migração
- Cria o arquivo SQL
- Valida a sintaxe
- Mostra como executar

#### `/add-agent`
Assistente para criar um novo agente de IA.

**Uso**: `/add-agent`

**Solicita**:
- Nome do agente
- Descrição
- System prompt
- Modelo (gpt-4, gpt-3.5-turbo, etc.)
- Temperatura
- Tools/funções necessárias

#### `/review-security`
Realiza uma análise de segurança do código.

**Uso**: `/review-security`

**Verifica**:
- SQL injection
- XSS vulnerabilities
- Input validation
- Authentication/authorization
- Sensitive data handling
- OWASP Top 10 compliance

#### `/add-channel`
Assistente para integrar um novo canal de comunicação.

**Uso**: `/add-channel`

**Cria**:
- Classe adapter implementando ChannelInterface
- Webhook endpoint
- Configuração no .env
- Testes básicos
- Documentação

#### `/explain-flow`
Explica o fluxo completo de uma requisição de chat.

**Uso**: `/explain-flow`

**Explica**:
- Frontend → API → Services → OpenAI → Response
- Inclui arquivos e números de linha

#### `/debug-issue`
Assistente interativo para debug de problemas.

**Uso**: `/debug-issue`

**Processo**:
1. Coleta informações sobre o problema
2. Analisa logs
3. Revisa código relevante
4. Identifica causas
5. Sugere soluções
6. Ajuda na implementação

#### `/setup-monitoring`
Guia para configurar o stack de monitoramento.

**Uso**: `/setup-monitoring`

**Configura**:
- Prometheus
- Grafana
- Loki
- Dashboards
- Alerts

#### `/optimize-performance`
Analisa e otimiza performance do código.

**Uso**: `/optimize-performance`

**Analisa**:
- Queries de banco de dados
- Oportunidades de cache
- Tempos de resposta
- Uso de memória
- Bundle size
- Executa testes de carga

#### `/generate-api-docs`
Gera documentação completa da API.

**Uso**: `/generate-api-docs`

**Gera**:
- Documentação de endpoints
- Request/response formats
- Exemplos curl
- OpenAPI/Swagger spec

#### `/refactor-service`
Assistente para refatoração de serviços.

**Uso**: `/refactor-service`

**Analisa**:
- Code smells
- SOLID principles
- Testability
- Error handling
- Code duplication

## MCP Servers (mcp.json)

O arquivo [mcp.json](mcp.json) configura os Model Context Protocol servers:

### Filesystem Server
Permite ao Claude Code acessar o sistema de arquivos do projeto.

### Git Server
Permite ao Claude Code interagir com o repositório Git:
- Ver histórico de commits
- Analisar mudanças
- Criar branches
- Gerenciar pull requests

## Como Usar

### Iniciar uma Conversa com Contexto

O Claude Code automaticamente carrega o contexto do projeto do arquivo `context.md`. Você pode começar fazendo perguntas sobre o projeto ou pedindo ajuda com tarefas específicas.

**Exemplos**:
- "Como funciona o sistema de multi-tenancy?"
- "Quero adicionar um novo canal de comunicação"
- "Preciso otimizar a performance das queries"
- "Como faço para criar um novo agente?"

### Usar Comandos Customizados

Digite `/` seguido do nome do comando:

```
/run-tests
/add-agent
/debug-issue
```

### Referenciar Arquivos

Use o formato `[arquivo](caminho)` para criar referências clicáveis:

```
[ChatHandler.php](includes/ChatHandler.php)
[admin-api.php:150](admin-api.php#L150)
```

## Boas Práticas

### Ao Pedir Ajuda

1. **Seja específico**: Descreva o problema ou tarefa claramente
2. **Forneça contexto**: Mencione o que você já tentou
3. **Inclua erros**: Copie mensagens de erro completas
4. **Especifique arquivos**: Se relevante, mencione arquivos específicos

### Ao Implementar Mudanças

1. **Execute testes**: Use `/run-tests` após mudanças
2. **Verifique qualidade**: Use `/check-code-quality`
3. **Revise segurança**: Use `/review-security` para mudanças sensíveis
4. **Documente**: Atualize documentação quando necessário

### Ao Criar Novos Recursos

1. **Planeje primeiro**: Discuta a arquitetura com Claude
2. **Siga padrões**: Mantenha consistência com código existente
3. **Escreva testes**: Crie testes junto com o código
4. **Atualize docs**: Documente novos recursos

## Exemplos de Uso

### Criar um Novo Agente de Suporte

```
/add-agent
```

Responda as perguntas do assistente sobre:
- Nome: "Support Bot"
- Descrição: "Customer support assistant"
- Prompt: "You are a helpful customer support agent..."
- Modelo: gpt-4
- Temperatura: 0.7

### Debug de Erro em Produção

```
/debug-issue
```

Descreva o problema:
- O que aconteceu: "Chat não está respondendo"
- Erro: "500 Internal Server Error"
- Endpoint: chat-unified.php

O Claude irá:
1. Verificar logs
2. Analisar código relevante
3. Identificar causa
4. Sugerir solução

### Adicionar Integração com Telegram

```
/add-channel
```

Informe:
- Canal: Telegram
- API: https://core.telegram.org/bots/api
- Auth: Bot Token
- Webhook: Sim

O Claude irá criar:
- `includes/channels/TelegramAdapter.php`
- `channels/telegram/webhook.php`
- Configuração no `.env.example`
- Testes básicos
- Documentação

### Otimizar Performance

```
/optimize-performance
```

O Claude irá:
1. Analisar queries N+1
2. Identificar oportunidades de cache
3. Revisar response times
4. Sugerir melhorias
5. Implementar otimizações

## Manutenção

### Atualizar Contexto

Se houver mudanças significativas no projeto, atualize [context.md](context.md):

```
Atualize o arquivo context.md com as mudanças recentes:
- Nova feature X
- Mudança na arquitetura Y
- Novo padrão Z
```

### Adicionar Novos Comandos

Para criar um novo comando customizado:

1. Crie arquivo em `commands/nome-comando.md`
2. Escreva as instruções para o Claude
3. Use markdown para formatação
4. Teste o comando: `/nome-comando`

**Exemplo** (`commands/deploy-prod.md`):
```markdown
Deploy the application to production:
1. Run all tests and ensure they pass
2. Check code quality
3. Build Docker image
4. Tag with version number
5. Push to registry
6. Update Kubernetes deployment
7. Verify health checks
8. Monitor logs for errors
```

## Suporte

Para problemas ou sugestões relacionados ao Claude Code:
- Documentação: https://code.claude.com/docs
- Issues: https://github.com/anthropics/claude-code/issues

Para problemas do projeto GPT Chatbot Boilerplate:
- Documentação: [docs/](../docs/)
- Issues: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues

## Recursos Adicionais

- [Documentação Oficial Claude Code](https://code.claude.com/docs)
- [Guia de MCP](https://modelcontextprotocol.io)
- [Documentação do Projeto](../docs/README.md)
- [Guia de Contribuição](../CONTRIBUTING.md)
