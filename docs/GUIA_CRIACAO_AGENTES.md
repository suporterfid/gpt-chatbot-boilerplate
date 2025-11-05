# Guia de Criação e Publicação de Agentes

Este guia apresenta um passo a passo completo para criar, configurar e publicar agentes de IA no GPT Chatbot Boilerplate, utilizando tanto a interface administrativa visual quanto a API REST.

## Índice

- [Introdução](#introdução)
- [Pré-requisitos](#pré-requisitos)
- [Métodos de Criação](#métodos-de-criação)
  - [1. Via Interface Administrativa (Recomendado)](#1-via-interface-administrativa-recomendado)
  - [2. Via Admin API (REST)](#2-via-admin-api-rest)
- [Configuração do Agente](#configuração-do-agente)
- [Publicação e Uso](#publicação-e-uso)
- [Exemplos Práticos](#exemplos-práticos)
- [Melhores Práticas](#melhores-práticas)
- [Solução de Problemas](#solução-de-problemas)

## Introdução

Os **Agentes** são configurações persistentes de IA que permitem criar múltiplas personalidades e comportamentos para o chatbot sem necessidade de alterações no código. Cada agente pode ter:

- **API Type**: Responses API (avançada) ou Chat Completions API (simples)
- **Modelo**: GPT-4o, GPT-4o-mini, etc.
- **Prompts**: Instruções do sistema e prompts reutilizáveis
- **Ferramentas**: File search, function calling, etc.
- **Parâmetros**: Temperature, top_p, max tokens, etc.
- **Vector Stores**: Bases de conhecimento para busca em arquivos

## Pré-requisitos

### 1. Configuração Inicial

Certifique-se de que o Admin está habilitado no arquivo `.env`:

```bash
# Habilitar Admin API
ADMIN_ENABLED=true

# Token de autenticação (mínimo 32 caracteres)
ADMIN_TOKEN=seu_token_admin_seguro_com_no_minimo_32_caracteres

# Configuração do banco de dados
DATABASE_PATH=./data/chatbot.db
# Ou MySQL:
# DATABASE_URL=mysql://usuario:senha@localhost/chatbot_db

# Chave da API OpenAI
OPENAI_API_KEY=sk-sua-chave-aqui
```

### 2. Executar Migrações

As migrações são executadas automaticamente na primeira requisição ao Admin API. Para executar manualmente:

```bash
php -r "require 'includes/DB.php'; \$db = new DB(['database_path' => './data/chatbot.db']); echo \$db->runMigrations('./db/migrations') . ' migrations executadas';"
```

### 3. Acessar a Interface Administrativa

Após configurar o `.env`, acesse:

```
http://seu-dominio/public/admin/
```

Digite o `ADMIN_TOKEN` quando solicitado.

## Métodos de Criação

### 1. Via Interface Administrativa (Recomendado)

A interface visual é a forma mais fácil e rápida de criar e gerenciar agentes.

#### Passo 1: Acessar a Página de Agentes

![Admin UI - Página de Agentes Vazia](images/admin-ui-agents-empty.png)

1. Acesse `http://seu-dominio/public/admin/`
2. Digite seu token de admin
3. Clique em "Agents" no menu lateral (já selecionado por padrão)

#### Passo 2: Criar Novo Agente

Clique no botão **"Create Agent"** ou **"Create Your First Agent"** (se não houver agentes ainda).

![Formulário de Criação de Agente](images/admin-ui-create-agent-form.png)

#### Passo 3: Preencher o Formulário

![Formulário Preenchido](images/admin-ui-create-agent-filled.png)

**Campos Obrigatórios:**

- **Name*** (Nome): Identificador único do agente
  - Exemplo: "Customer Support Agent", "Assistente de Vendas"

**Campos Opcionais:**

- **Description** (Descrição): Breve descrição do propósito do agente
  - Exemplo: "Atende consultas de clientes usando nossa base de conhecimento"

- **API Type*** (Tipo de API): 
  - **Responses API**: Para funcionalidades avançadas (prompts, tools, file search)
  - **Chat Completions API**: Para conversação simples e direta

- **Model** (Modelo):
  - Exemplos: `gpt-4o`, `gpt-4o-mini`, `gpt-3.5-turbo`
  - Deixe em branco para usar o modelo padrão do config.php

- **Prompt ID**: ID de um prompt salvo na OpenAI (formato: `pmpt_xxxxx`)
  - Use a aba "Prompts" para criar e gerenciar prompts

- **Prompt Version**: Versão do prompt (ex: "1", "latest")

- **System Message**: Mensagem de sistema personalizada
  - Exemplo: "Você é um assistente prestativo especializado em suporte técnico"

- **Temperature** (0-2): Criatividade das respostas
  - 0.1-0.4: Respostas precisas e factuais
  - 0.7-1.0: Balanceado (padrão: 0.7)
  - 1.2-2.0: Muito criativo

- **Top P** (0-1): Diversidade do vocabulário (padrão: 1)

- **Max Output Tokens**: Limite de tokens na resposta
  - Exemplo: 1024, 2048, 4096

- **Vector Store IDs**: IDs de Vector Stores para busca em arquivos
  - Formato: `vs_abc123,vs_def456` (separados por vírgula)
  - Use a aba "Vector Stores" para criar e gerenciar

- **Enable File Search Tool**: Ativa a ferramenta de busca em arquivos
  - Requer Vector Store IDs configurados

- **Set as Default Agent**: Define este agente como padrão
  - Requisições sem `agent_id` usarão este agente

#### Passo 4: Salvar o Agente

Clique em **"Create Agent"** para salvar.

![Lista de Agentes](images/admin-ui-agents-list.png)

Após a criação, o agente aparecerá na lista com:
- Nome e tipo (badge colorido)
- Modelo configurado
- Status (Default se for o agente padrão)
- Data de atualização
- Ações: Edit, Test, Delete

### 2. Via Admin API (REST)

Para automação ou integração com sistemas externos, use a Admin API.

#### Criar Agente via API

```bash
curl -X POST "http://seu-dominio/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer SEU_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Agent",
    "description": "Atende consultas de clientes usando nossa base de conhecimento",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_kb_12345"],
    "is_default": true
  }'
```

**Resposta de Sucesso:**

```json
{
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "name": "Customer Support Agent",
    "description": "Atende consultas de clientes usando nossa base de conhecimento",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_kb_12345"],
    "is_default": true,
    "created_at": "2025-11-05T23:10:20Z",
    "updated_at": "2025-11-05T23:10:20Z"
  }
}
```

#### Listar Agentes

```bash
curl -X GET "http://seu-dominio/admin-api.php?action=list_agents" \
  -H "Authorization: Bearer SEU_ADMIN_TOKEN"
```

#### Atualizar Agente

```bash
curl -X POST "http://seu-dominio/admin-api.php?action=update_agent&id=AGENT_ID" \
  -H "Authorization: Bearer SEU_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "temperature": 0.9,
    "description": "Descrição atualizada"
  }'
```

#### Definir Agente como Padrão

```bash
curl -X POST "http://seu-dominio/admin-api.php?action=make_default&id=AGENT_ID" \
  -H "Authorization: Bearer SEU_ADMIN_TOKEN"
```

#### Deletar Agente

```bash
curl -X POST "http://seu-dominio/admin-api.php?action=delete_agent&id=AGENT_ID" \
  -H "Authorization: Bearer SEU_ADMIN_TOKEN"
```

## Configuração do Agente

### Escolhendo o Tipo de API

**Responses API** - Recomendado para:
- ✅ Uso de prompts reutilizáveis salvos na OpenAI
- ✅ Busca em documentos (file search)
- ✅ Function calling e ferramentas personalizadas
- ✅ Controle fino sobre comportamento e versões

**Chat Completions API** - Recomendado para:
- ✅ Conversação simples e rápida
- ✅ Menor latência e custo
- ✅ Cenários pergunta-resposta básicos
- ✅ Sem necessidade de ferramentas avançadas

### Configurando Prompts

#### Opção 1: System Message Inline

Defina diretamente no campo "System Message":

```
Você é um assistente de atendimento ao cliente prestativo e profissional.
Seu objetivo é ajudar os clientes a resolver problemas de forma eficiente.
Sempre seja educado, claro e forneça soluções práticas.
```

#### Opção 2: Prompt ID Reutilizável

1. Acesse a aba **"Prompts"** no Admin UI
2. Crie um novo prompt ou sincronize um existente da OpenAI
3. Copie o Prompt ID (formato: `pmpt_xxxxx`)
4. Cole no campo "Prompt ID" ao criar o agente
5. Opcionalmente, especifique a versão (padrão: latest)

**Vantagens do Prompt ID:**
- ✅ Versionamento automático
- ✅ Reutilizável entre múltiplos agentes
- ✅ Sincronização com a OpenAI
- ✅ Histórico de alterações

### Configurando Vector Stores

Para agentes que precisam buscar informações em documentos:

1. Acesse a aba **"Vector Stores"** no Admin UI
2. Crie um novo Vector Store ou use um existente
3. Faça upload de arquivos (PDF, TXT, DOCX, etc.)
4. Copie o Vector Store ID (formato: `vs_xxxxx`)
5. Cole no campo "Vector Store IDs" (separados por vírgula se múltiplos)
6. Marque **"Enable File Search Tool"**

### Ajustando Parâmetros

**Temperature** (Criatividade):
- `0.0-0.3`: Respostas consistentes e previsíveis (suporte técnico, FAQ)
- `0.4-0.8`: Balanceado (uso geral)
- `0.9-2.0`: Criativo e variado (escrita criativa, brainstorming)

**Top P** (Diversidade de Vocabulário):
- `0.5`: Vocabulário mais limitado e focado
- `1.0`: Vocabulário completo (recomendado)

**Max Output Tokens**:
- `500-1000`: Respostas curtas e diretas
- `1024-2048`: Respostas médias (padrão)
- `4096+`: Respostas longas e detalhadas

## Publicação e Uso

### Usando o Agente no Chat

#### Opção 1: Agente Padrão (Sem Especificar ID)

Se você definiu um agente como padrão, todas as requisições sem `agent_id` o usarão automaticamente:

```bash
curl -X POST "http://seu-dominio/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Qual é sua política de devolução?",
    "conversation_id": "conv_123"
  }'
```

#### Opção 2: Especificar Agent ID

```bash
curl -X POST "http://seu-dominio/chat-unified.php" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Qual é sua política de devolução?",
    "conversation_id": "conv_123",
    "agent_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  }'
```

#### Opção 3: Integração JavaScript

```javascript
// Usando agente padrão
ChatBot.init({
    mode: 'floating',
    apiEndpoint: '/chat-unified.php',
    assistant: {
        name: 'Assistente',
        welcomeMessage: 'Olá! Como posso ajudar?'
    }
});

// Especificando um agente
ChatBot.init({
    mode: 'floating',
    apiEndpoint: '/chat-unified.php',
    requestModifier: (payload) => {
        payload.agent_id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        return payload;
    }
});
```

### Seleção Dinâmica de Agentes

Permita que usuários selecionem entre múltiplos agentes:

```javascript
// Buscar lista de agentes
fetch('/admin-api.php?action=list_agents', {
    headers: {
        'Authorization': 'Bearer SEU_ADMIN_TOKEN'
    }
})
.then(r => r.json())
.then(data => {
    const agents = data.data;
    
    // Criar seletor de agentes
    const selector = document.createElement('select');
    agents.forEach(agent => {
        const option = document.createElement('option');
        option.value = agent.id;
        option.textContent = agent.name;
        selector.appendChild(option);
    });
    
    // Inicializar chatbot com agente selecionado
    let currentAgentId = agents[0].id;
    
    selector.addEventListener('change', (e) => {
        currentAgentId = e.target.value;
    });
    
    ChatBot.init({
        mode: 'floating',
        requestModifier: (payload) => {
            payload.agent_id = currentAgentId;
            return payload;
        }
    });
});
```

### Precedência de Configuração

Quando um agente é usado, as configurações são mescladas na seguinte ordem (da maior para a menor prioridade):

1. **Parâmetros da Requisição** (mais alta)
2. **Configuração do Agente**
3. **config.php Defaults** (mais baixa)

**Exemplo:**
- Agente tem: `model: "gpt-4o"`, `temperature: 0.7`
- Requisição tem: `model: "gpt-3.5-turbo"`
- **Resultado**: Usa `gpt-3.5-turbo` (requisição) com `temperature: 0.7` (agente)

## Exemplos Práticos

### Exemplo 1: Agente de Suporte Técnico

```json
{
  "name": "Suporte Técnico",
  "description": "Especialista em resolver problemas técnicos usando nossa documentação",
  "api_type": "responses",
  "model": "gpt-4o",
  "temperature": 0.3,
  "system_message": "Você é um especialista em suporte técnico. Forneça soluções precisas e passo a passo.",
  "tools": [{"type": "file_search"}],
  "vector_store_ids": ["vs_documentacao_tecnica"],
  "max_output_tokens": 2048
}
```

**Quando usar:** FAQ técnica, troubleshooting, documentação

### Exemplo 2: Agente de Vendas

```json
{
  "name": "Assistente de Vendas",
  "description": "Ajuda clientes a encontrar produtos e responde perguntas sobre catálogo",
  "api_type": "responses",
  "model": "gpt-4o-mini",
  "temperature": 0.7,
  "system_message": "Você é um assistente de vendas amigável e persuasivo. Ajude os clientes a encontrar os melhores produtos.",
  "tools": [{"type": "file_search"}],
  "vector_store_ids": ["vs_catalogo_produtos"],
  "max_num_results": 20
}
```

**Quando usar:** Recomendação de produtos, informações de catálogo

### Exemplo 3: Agente Criativo

```json
{
  "name": "Assistente Criativo",
  "description": "Gera conteúdo criativo e ideias inovadoras",
  "api_type": "chat",
  "model": "gpt-4o",
  "temperature": 1.2,
  "top_p": 0.95,
  "system_message": "Você é um assistente criativo. Gere ideias originais e conteúdo envolvente.",
  "max_output_tokens": 3000
}
```

**Quando usar:** Brainstorming, escrita criativa, geração de ideias

### Exemplo 4: Agente com Guardrails JSON

Para respostas estruturadas:

```json
{
  "name": "Extrator de Dados",
  "description": "Extrai informações estruturadas de textos",
  "api_type": "responses",
  "model": "gpt-4o",
  "temperature": 0.1,
  "response_format": {
    "type": "json_schema",
    "json_schema": {
      "name": "dados_extraidos",
      "schema": {
        "type": "object",
        "properties": {
          "nome": {"type": "string"},
          "email": {"type": "string"},
          "telefone": {"type": "string"}
        },
        "required": ["nome", "email"]
      }
    }
  }
}
```

**Quando usar:** Extração de dados, formulários, validação

## Melhores Práticas

### 1. Nomeação e Organização

✅ **Faça:**
- Use nomes descritivos: "Suporte Cliente - PT-BR", "Sales Assistant - EN"
- Adicione descrições claras do propósito
- Agrupe agentes por função ou idioma

❌ **Evite:**
- Nomes genéricos: "Agent 1", "Test"
- Agentes sem descrição
- Duplicatas desnecessárias

### 2. Configuração de Temperatura

✅ **Faça:**
- `0.1-0.3` para suporte técnico e FAQ
- `0.7-0.9` para uso geral e conversação
- `1.0-1.5` para tarefas criativas

❌ **Evite:**
- Temperature muito alta para informações factuais
- Temperature muito baixa para tarefas criativas

### 3. Vector Stores e File Search

✅ **Faça:**
- Organize documentos em Vector Stores temáticos
- Mantenha Vector Stores atualizados
- Use múltiplos stores quando apropriado
- Configure `max_num_results` adequadamente (10-50)

❌ **Evite:**
- Um único Vector Store gigante com tudo
- Documentos desatualizados
- `max_num_results` muito alto (desperdício)

### 4. Prompts

✅ **Faça:**
- Use Prompt IDs para prompts reutilizáveis
- Versione seus prompts
- Teste prompts antes de publicar
- Seja específico nas instruções

❌ **Evite:**
- Prompts muito vagos ou genéricos
- Prompts duplicados inline
- Instruções contraditórias

### 5. Testes

✅ **Faça:**
- Use a função "Test" no Admin UI
- Teste com casos de uso reais
- Valide respostas antes de tornar default
- Monitore logs de auditoria

❌ **Evite:**
- Publicar sem testar
- Usar produção para experimentos
- Ignorar erros nos logs

### 6. Agente Padrão

✅ **Faça:**
- Defina um agente padrão robusto
- Use configurações conservadoras
- Documente qual é o padrão

❌ **Evite:**
- Múltiplos defaults (só pode haver um)
- Agente experimental como default
- Trocar default frequentemente em produção

## Solução de Problemas

### Erro: "Invalid admin token"

**Causa:** Token não configurado ou incorreto

**Solução:**
```bash
# Verifique o token no .env
grep ADMIN_TOKEN .env

# Certifique-se de que tem pelo menos 32 caracteres
# Use o header correto:
curl -H "Authorization: Bearer SEU_TOKEN_AQUI" ...
```

### Erro: "Agent not found"

**Causa:** Agent ID inválido ou agente deletado

**Solução:**
```bash
# Liste todos os agentes
curl -X GET "http://seu-dominio/admin-api.php?action=list_agents" \
  -H "Authorization: Bearer SEU_ADMIN_TOKEN"

# Use um agent_id válido da lista
```

### Erro: "vector_store_ids must contain non-empty strings"

**Causa:** Vector Store IDs inválidos ou vazios quando File Search está habilitado

**Solução:**
- Certifique-se de fornecer Vector Store IDs válidos (formato: `vs_xxxxx`)
- Ou desabilite "Enable File Search Tool" se não for usar

### Agente não está sendo usado

**Causa:** Request não inclui `agent_id` e não há agente padrão

**Solução:**
```bash
# Opção 1: Defina um agente como padrão
curl -X POST "http://seu-dominio/admin-api.php?action=make_default&id=AGENT_ID" \
  -H "Authorization: Bearer SEU_ADMIN_TOKEN"

# Opção 2: Sempre inclua agent_id nas requisições
{
  "message": "Olá",
  "agent_id": "seu-agent-id-aqui"
}
```

### Database não está acessível

**Causa:** Permissões ou path incorreto

**Solução:**
```bash
# Crie o diretório data
mkdir -p data

# Dê permissões
chmod 755 data

# Verifique o path no .env
grep DATABASE_PATH .env
```

### Admin UI não carrega

**Causa:** Apache/Nginx não está servindo arquivos estáticos

**Solução:**
```apache
# Apache - certifique-se de que .htaccess está configurado
# Ou configure no VirtualHost:
<Directory "/var/www/html/public/admin">
    AllowOverride All
    Require all granted
</Directory>
```

## Recursos Adicionais

### Documentação Relacionada

- [README.md](../README.md) - Visão geral do projeto
- [PHASE1_DB_AGENT.md](PHASE1_DB_AGENT.md) - Detalhes técnicos da implementação
- [PHASE2_ADMIN_UI.md](PHASE2_ADMIN_UI.md) - Documentação completa do Admin UI
- [customization-guide.md](customization-guide.md) - Guia de customização (English)
- [api.md](api.md) - Referência completa da API

### Suporte

- 📖 [Documentação Completa](../docs/)
- 🐛 [Reportar Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- 💬 [Discussões](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)

---

**Desenvolvido com ❤️ pela comunidade open source**
