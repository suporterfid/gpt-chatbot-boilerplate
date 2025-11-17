---
applyTo: "docs/**/*.md"
description: "Regras específicas para documentação do projeto"
---

# Instruções para Documentação - gpt-chatbot-boilerplate

## Arquivos Alvo
- `docs/*.md` - Documentação principal
- `README.md` - Documentação raiz do projeto
- `CHANGELOG.md` - Histórico de mudanças
- `CONTRIBUTING.md` - Guia de contribuição

## Filosofia de Documentação

### Princípios
- **Clareza**: Escrever para audiência técnica, mas de forma acessível.
- **Atualização**: Manter documentação sincronizada com código.
- **Completude**: Cobrir todos os aspectos importantes do sistema.
- **Exemplos**: Incluir exemplos práticos e cases de uso.
- **Organização**: Estruturar informação de forma lógica e navegável.

### Audiência
- **Desenvolvedores**: Implementando ou integrando o chatbot
- **Operadores**: Fazendo deploy e manutenção
- **Usuários Finais**: Usando a interface administrativa
- **Contribuidores**: Melhorando o código base

## Estrutura de Documentação

### Hierarquia
```
docs/
├── README.md                    # Índice principal
├── QUICK_START.md              # Início rápido
├── FEATURES.md                 # Lista de features
├── api.md                      # Referência de API
├── customization-guide.md      # Guia de customização
├── deployment.md               # Guia de deploy
├── OPERATIONS_GUIDE.md         # Guia operacional
├── SECURITY_MODEL.md           # Modelo de segurança
├── CONTRIBUTING.md             # Guia de contribuição
├── CHANGELOG.md                # Histórico de mudanças
├── ops/                        # Documentação operacional
│   ├── backup_restore.md
│   ├── disaster_recovery.md
│   └── monitoring.md
└── [feature-specific]/         # Docs específicas
```

### Seções Recomendadas
Cada documento deve ter (quando aplicável):
1. **Título e Descrição**: Breve overview
2. **Tabela de Conteúdos**: Para docs longas
3. **Pré-requisitos**: O que é necessário saber/ter
4. **Instruções Passo-a-Passo**: Como fazer
5. **Exemplos**: Código e casos de uso
6. **Troubleshooting**: Problemas comuns
7. **Referências**: Links relacionados

## Formato Markdown

### Headers
```markdown
# Título Principal (H1) - Apenas um por documento

## Seção Principal (H2)

### Subseção (H3)

#### Detalhes (H4) - Usar com moderação
```

### Código
````markdown
```php
// Incluir linguagem para syntax highlighting
function example() {
    return true;
}
```

```bash
# Comandos shell
docker-compose up -d
```

```json
{
    "config": "value"
}
```

Inline code: Use `backticks` para código inline.
````

### Listas
```markdown
- Lista não ordenada
  - Item aninhado
  - Outro item
- Próximo item

1. Lista ordenada
2. Segundo item
   1. Sub-item
   2. Outro sub-item
3. Terceiro item
```

### Links
```markdown
[Texto do Link](https://example.com)
[Link Interno](./outro-documento.md)
[Link para Seção](#seção-específica)
```

### Alertas e Notas
```markdown
> **Nota:** Informação importante que o leitor deve saber.

> ⚠️ **Aviso:** Algo que pode causar problemas se ignorado.

> 🚨 **Crítico:** Informação de segurança ou que pode causar perda de dados.

> 💡 **Dica:** Sugestão útil ou melhoria.
```

### Tabelas
```markdown
| Coluna 1 | Coluna 2 | Coluna 3 |
|----------|----------|----------|
| Valor 1  | Valor 2  | Valor 3  |
| A        | B        | C        |
```

### Checkboxes
```markdown
- [x] Tarefa completa
- [ ] Tarefa pendente
- [ ] Outra tarefa
```

## Tipos de Documentação

### Quick Start (QUICK_START.md)
- **Objetivo**: Fazer usuário começar em < 10 minutos
- **Estrutura**:
  ```markdown
  # Quick Start
  
  ## Prerequisites
  - Lista de requisitos mínimos
  
  ## Installation
  1. Clone the repository
  2. Configure .env
  3. Run migrations
  4. Start services
  
  ## First Steps
  - Acessar chatbot
  - Criar primeiro agent
  - Testar chat
  
  ## Next Steps
  - Links para documentação mais detalhada
  ```

### API Reference (api.md)
- **Objetivo**: Documentar todos os endpoints e suas APIs
- **Estrutura para cada endpoint**:
  ```markdown
  ### POST /endpoint-name
  
  Brief description of what this endpoint does.
  
  **Authentication:** Required (API Key or Session)
  
  **Request:**
  ```json
  {
      "param1": "value",
      "param2": 123
  }
  ```
  
  **Response (200 OK):**
  ```json
  {
      "success": true,
      "data": {}
  }
  ```
  
  **Error Responses:**
  - `400 Bad Request`: Invalid parameters
  - `401 Unauthorized`: Missing or invalid authentication
  - `403 Forbidden`: Insufficient permissions
  
  **Example:**
  ```bash
  curl -X POST https://api.example.com/endpoint \
       -H "Content-Type: application/json" \
       -H "Authorization: Bearer YOUR_KEY" \
       -d '{"param1": "value"}'
  ```
  ```

### Deployment Guide (deployment.md)
- **Objetivo**: Instruções completas para deploy em produção
- **Incluir**:
  - Requisitos de sistema
  - Configuração de ambiente
  - Opções de deploy (Docker, bare-metal, cloud)
  - Configuração de servidor web (Nginx/Apache)
  - SSL/TLS setup
  - Configuração de banco de dados
  - Backup e recovery
  - Monitoring setup
  - Security hardening
  - Performance tuning

### Operations Guide (OPERATIONS_GUIDE.md)
- **Objetivo**: Guia para operação day-to-day
- **Incluir**:
  - Rotinas de manutenção
  - Monitoramento e alertas
  - Troubleshooting comum
  - Logs e debugging
  - Scaling procedures
  - Backup/restore procedures
  - Update/upgrade process
  - Emergency procedures

### Feature Documentation
Para cada feature major, criar documento específico:
```markdown
# Feature Name

## Overview
Brief description and use cases.

## How It Works
Technical explanation of implementation.

## Configuration
```yaml
# Configuration options
feature:
  enabled: true
  option1: value
```

## Usage Examples

### Basic Example
```php
// Code example
```

### Advanced Example
```php
// More complex example
```

## API Reference
Link to API documentation for this feature.

## Troubleshooting
Common issues and solutions.

## See Also
- Related documentation
- External resources
```

## Changelog (CHANGELOG.md)

### Formato
Seguir [Keep a Changelog](https://keepachangelog.com/):
```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- New features added

### Changed
- Changes in existing functionality

### Deprecated
- Soon-to-be removed features

### Removed
- Removed features

### Fixed
- Bug fixes

### Security
- Security fixes

## [1.2.0] - 2024-01-15

### Added
- Feature X for improved Y
- Support for Z

### Fixed
- Bug in component A
- Issue with B

## [1.1.0] - 2024-01-01
...
```

## Contributing Guide (CONTRIBUTING.md)

### Estrutura
```markdown
# Contributing Guide

## Welcome
Introduction and thanks for interest.

## Code of Conduct
Link to code of conduct.

## How to Contribute

### Reporting Bugs
- Where to report
- What to include
- Template

### Suggesting Features
- How to suggest
- What makes a good suggestion

### Pull Requests
1. Fork repository
2. Create branch
3. Make changes
4. Add tests
5. Update documentation
6. Submit PR

## Development Setup
Step-by-step setup for development.

## Coding Standards
- Link to style guides
- Linting and testing requirements

## Commit Messages
Format and examples.

## Review Process
What to expect after submitting PR.
```

## Manutenção de Documentação

### Quando Atualizar
- ✅ Ao adicionar nova feature
- ✅ Ao modificar API existente
- ✅ Ao mudar configuração
- ✅ Ao corrigir bug que afeta uso
- ✅ Ao deprecar funcionalidade
- ✅ Ao adicionar dependência

### Checklist de Atualização
```markdown
- [ ] README.md atualizado se feature é user-facing
- [ ] CHANGELOG.md atualizado com mudança
- [ ] API docs atualizados se endpoint mudou
- [ ] Quick start atualizado se afeta setup
- [ ] Deployment guide atualizado se afeta deploy
- [ ] Exemplos de código testados e funcionando
- [ ] Links internos verificados
- [ ] Screenshots atualizados se UI mudou
```

### Review de Documentação
Antes de commitar mudanças em docs:
1. Ler documento do início ao fim
2. Testar todos os exemplos de código
3. Verificar todos os links
4. Confirmar formatação Markdown correta
5. Checar ortografia e gramática
6. Validar que informação está atual

## Exemplos Práticos

### Exemplo: Documentando Nova Feature
```markdown
# WhatsApp Integration

## Overview

O GPT Chatbot Boilerplate agora suporta integração com WhatsApp Business API,
permitindo que seus agentes respondam mensagens via WhatsApp.

## Prerequisites

- Conta WhatsApp Business aprovada
- Access token da API
- Webhook endpoint público (HTTPS)

## Configuration

1. Configure as variáveis de ambiente:

```bash
WHATSAPP_ENABLED=true
WHATSAPP_ACCESS_TOKEN=your_token_here
WHATSAPP_PHONE_NUMBER_ID=your_phone_id
WHATSAPP_WEBHOOK_TOKEN=random_secure_token
```

2. Configure o webhook no dashboard do WhatsApp:
   - URL: `https://yourdomain.com/webhooks/whatsapp.php`
   - Verify Token: Use o valor de `WHATSAPP_WEBHOOK_TOKEN`

## Usage

### Linking Agent to WhatsApp

```php
POST /admin-api.php?action=link_agent_to_whatsapp

{
    "agent_id": 1,
    "whatsapp_number": "+5511999999999"
}
```

### Testing

Envie uma mensagem para o número WhatsApp configurado.
O agent responderá automaticamente.

## Troubleshooting

**Problema:** Mensagens não sendo recebidas

**Solução:**
1. Verifique se webhook está acessível publicamente
2. Confirme que verify token está correto
3. Check logs em `/var/log/whatsapp-webhook.log`

**Problema:** Respostas não sendo enviadas

**Solução:**
1. Verifique access token
2. Confirme que phone number ID está correto
3. Check rate limits da API

## API Reference

Ver [WhatsApp API Documentation](./WHATSAPP_API.md) para detalhes completos.

## See Also

- [Webhooks Documentation](./WEBHOOK_IMPLEMENTATION.md)
- [Channel Management](./CHANNELS.md)
```

## Idiomas

### Default: Inglês
- Documentação principal em inglês para alcance global
- Comentários de código em inglês

### Português Brasileiro
- Documentação pode ter versões PT-BR para facilitar adoção local
- Nomear arquivos: `GUIA_FEATURE.md` ou `FEATURE_PTBR.md`
- Indicar idioma no título: `# Feature Name (PT-BR)`

### Outras Línguas
- Bem-vindas contribuições em outras línguas
- Manter estrutura e qualidade consistente

## Ferramentas

### Linting Markdown
```bash
# Usar markdownlint para validar formato
npm install -g markdownlint-cli
markdownlint docs/**/*.md
```

### Spell Check
```bash
# Usar cspell para verificar ortografia
npm install -g cspell
cspell "docs/**/*.md"
```

### Link Checking
```bash
# Verificar links quebrados
npm install -g markdown-link-check
markdown-link-check docs/README.md
```

## Checklist de Revisão

Antes de commitar documentação:

- [ ] Formato Markdown válido
- [ ] Headers hierárquicos corretos (H1 → H2 → H3)
- [ ] Exemplos de código incluem linguagem para highlighting
- [ ] Todos os exemplos foram testados
- [ ] Links internos funcionam
- [ ] Links externos válidos
- [ ] Ortografia verificada
- [ ] Screenshots atualizados (se aplicável)
- [ ] Tabela de conteúdos atualizada (docs longas)
- [ ] CHANGELOG.md atualizado
- [ ] Cross-references para docs relacionadas
- [ ] Informação técnica precisa e atual
