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
- **Modelo**: GPT-4o, GPT-4o-mini, GPT-4-turbo, etc.
- **Prompts**: Instruções do sistema e prompts reutilizáveis
- **Ferramentas**: File search, function calling, code interpreter, etc.
- **Parâmetros**: Temperature, top_p, max tokens, etc.
- **Vector Stores**: Bases de conhecimento para busca em arquivos
- **Response Format**: Estrutura de saída (JSON schemas, guardrails)
- **Multi-tenancy**: Isolamento por tenant para ambientes multi-inquilino
- **Integrações**: WhatsApp, LeadSense CRM, webhooks personalizados

## Pré-requisitos

### 1. Instalação e Configuração Inicial

#### Opção A: Instalação via Interface Web (Recomendado)

A maneira mais fácil de começar é usar o assistente de instalação web:

1. Inicie a aplicação:
```bash
git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
cd gpt-chatbot-boilerplate

# Com Docker (recomendado, inclui MySQL)
docker-compose up -d

# Ou com servidor PHP
php -S localhost:8000
```

2. Acesse o assistente de instalação:
```
http://localhost:8088/setup/install.php
# ou http://localhost:8000/setup/install.php
```

3. Siga os passos do assistente:
   - ✅ Verificar requisitos do sistema
   - ⚙️ Configurar OpenAI API e parâmetros
   - 🗄️ Escolher e configurar banco de dados (SQLite ou MySQL)
   - 🔐 Configurar credenciais de administrador
   - 🎯 Habilitar recursos opcionais
   - 🚀 Inicializar banco de dados

4. Guarde as credenciais do usuário administrador criado (email e senha) — elas serão usadas no novo fluxo de login do painel. Tokens continuam disponíveis para integrações headless, porém são considerados legados.

#### Opção B: Configuração Manual

Se preferir configurar manualmente, edite o arquivo `.env`:

```bash
# Habilitar Admin API
ADMIN_ENABLED=true

# Token de autenticação (mínimo 32 caracteres)
ADMIN_TOKEN=seu_token_admin_seguro_com_no_minimo_32_caracteres

# Configuração do banco de dados
DATABASE_PATH=./data/chatbot.db
# Ou MySQL:
# DATABASE_URL=mysql:host=mysql;port=3306;dbname=chatbot;charset=utf8mb4
# DB_HOST=mysql
# DB_PORT=3306
# DB_NAME=chatbot
# DB_USER=chatbot
# DB_PASSWORD=senha_segura

# Chave da API OpenAI
OPENAI_API_KEY=sk-sua-chave-aqui
```

### 2. Executar Migrações

As migrações são executadas automaticamente:
- Pelo assistente de instalação web
- Na primeira requisição ao Admin API
- Ou manualmente via comando:

```bash
php -r "require 'includes/DB.php'; \$db = new DB(['database_path' => './data/chatbot.db']); echo \$db->runMigrations('./db/migrations') . ' migrations executadas';"
```

### 3. Acessar a Interface Administrativa

Após configurar o `.env`, acesse:

```
http://seu-dominio/public/admin/
```

#### Autenticação Moderna (Recomendado)

Você será direcionado para o formulário de login. Informe o **email** e **senha** do usuário administrador criado durante a instalação. Após autenticar, o navegador receberá um cookie `admin_session` (HttpOnly, SameSite=Lax) válido por 24 horas por padrão (`ADMIN_SESSION_TTL`).

**Vantagens da autenticação por sessão:**
- ✅ Segurança aprimorada com cookies HttpOnly
- ✅ Suporte a RBAC (viewer, admin, super-admin)
- ✅ Multi-tenancy nativo
- ✅ Rotação automática de sessões
- ✅ Auditoria completa de ações

#### Chaves de API (Para Automação)

Para scripts e integrações, use chaves de API individuais:

```bash
# Gerar uma nova chave de API (via interface ou API)
curl -b cookies.txt -X POST "http://localhost/admin-api.php?action=generate_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Script de Backup",
    "expires_at": "2025-12-31T23:59:59Z",
    "permissions": ["read", "write"]
  }'

# Usar a chave de API gerada
curl -H "X-API-Key: key_abc123def456..." \
  "http://localhost/admin-api.php?action=list_agents"
```

> **⚠️ Importante:** O cabeçalho `Authorization: Bearer <ADMIN_TOKEN>` está **depreciado** e será removido em versões futuras. Migre para sessões ou chaves de API individuais.

#### Login via API (Scripts de Automação)

Para criar sessões manualmente:

```bash
# Realiza login e salva o cookie de sessão
curl -i -c cookies.txt -X POST "http://localhost/admin-api.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "senha-super-segura"
  }'

# Consulta o usuário autenticado reaproveitando o cookie
curl -b cookies.txt "http://localhost/admin-api.php?action=current_user"

# Finaliza a sessão atual
curl -b cookies.txt -X POST "http://localhost/admin-api.php?action=logout"
```

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
  - Deve ser único dentro do tenant

**Campos Opcionais:**

- **Slug** (Identificador URL): Slug único para acessar o agente
  - Formato: apenas letras minúsculas, números e hífens
  - Exemplo: "customer-support", "assistente-vendas"
  - Usado para criar URLs amigáveis
  - Deve ser único dentro do tenant
  - Máximo 64 caracteres

- **Description** (Descrição): Breve descrição do propósito do agente
  - Exemplo: "Atende consultas de clientes usando nossa base de conhecimento"

- **API Type*** (Tipo de API): 
  - **Responses API**: Para funcionalidades avançadas (prompts, tools, file search)
  - **Chat Completions API**: Para conversação simples e direta
  - Padrão: `responses`

- **Model** (Modelo):
  - Exemplos: `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`, `gpt-3.5-turbo`
  - Deixe em branco para usar o modelo padrão do config.php

- **Prompt ID**: ID de um prompt salvo na OpenAI (formato: `pmpt_xxxxx`)
  - Use a aba "Prompts" para criar e gerenciar prompts
  - Suporta versionamento de prompts

- **Prompt Version**: Versão do prompt (ex: "1", "latest")
  - Permite fixar versões específicas ou usar sempre a mais recente

- **System Message**: Mensagem de sistema personalizada
  - Exemplo: "Você é um assistente prestativo especializado em suporte técnico"
  - Alternativa ao uso de Prompt ID

- **Temperature** (0-2): Criatividade das respostas
  - 0.1-0.4: Respostas precisas e factuais (suporte técnico, FAQ)
  - 0.7-1.0: Balanceado (uso geral, padrão: 0.7)
  - 1.2-2.0: Muito criativo (brainstorming, conteúdo criativo)

- **Top P** (0-1): Diversidade do vocabulário
  - 0.5: Vocabulário mais limitado e focado
  - 1.0: Vocabulário completo (padrão, recomendado)

- **Max Output Tokens**: Limite de tokens na resposta
  - Exemplo: 1024, 2048, 4096, 8192
  - Controla o tamanho máximo das respostas

- **Vector Store IDs**: IDs de Vector Stores para busca em arquivos
  - Formato: `vs_abc123,vs_def456` (separados por vírgula)
  - Use a aba "Vector Stores" para criar e gerenciar
  - Suporta múltiplos stores para diferentes bases de conhecimento

- **Max Num Results**: Número máximo de resultados em buscas
  - Padrão: 20 resultados por busca em vector stores
  - Range recomendado: 10-50

- **Enable File Search Tool**: Ativa a ferramenta de busca em arquivos
  - Requer Vector Store IDs configurados
  - Permite que o agente busque em documentos automaticamente

- **Response Format** (Guardrails): Estrutura de saída JSON
  - Define schemas para respostas estruturadas
  - Útil para extração de dados, formulários, validação
  - Ver seção sobre Hybrid Guardrails

- **Tenant ID**: Identificador do tenant (multi-tenancy)
  - Atribuído automaticamente se o usuário pertence a um tenant
  - Garante isolamento de dados entre clientes

- **Set as Default Agent**: Define este agente como padrão
  - Requisições sem `agent_id` usarão este agente
  - Apenas um agente pode ser padrão por tenant

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
# Com sessão autenticada (recomendado)
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=create_agent" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Agent",
    "slug": "customer-support",
    "description": "Atende consultas de clientes usando nossa base de conhecimento",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_kb_12345"],
    "max_num_results": 20,
    "is_default": true
  }'

# Ou com chave de API
curl -X POST "http://seu-dominio/admin-api.php?action=create_agent" \
  -H "X-API-Key: key_abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Agent",
    "slug": "customer-support",
    "description": "Atende consultas de clientes usando nossa base de conhecimento",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_kb_12345"],
    "max_num_results": 20,
    "is_default": true
  }'
```

**Resposta de Sucesso:**

```json
{
  "data": {
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "name": "Customer Support Agent",
    "slug": "customer-support",
    "description": "Atende consultas de clientes usando nossa base de conhecimento",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "top_p": 1.0,
    "max_output_tokens": 2048,
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_kb_12345"],
    "max_num_results": 20,
    "response_format": null,
    "is_default": true,
    "tenant_id": "tenant_xyz789",
    "created_at": "2025-11-18T17:10:20Z",
    "updated_at": "2025-11-18T17:10:20Z"
  }
}
```

#### Listar Agentes

```bash
# Com sessão
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=list_agents"

# Ou com chave de API
curl -H "X-API-Key: key_abc123..." "http://seu-dominio/admin-api.php?action=list_agents"
```

#### Atualizar Agente

```bash
# Com sessão
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=update_agent&id=AGENT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "temperature": 0.9,
    "description": "Descrição atualizada",
    "max_num_results": 30
  }'

# Ou com chave de API
curl -H "X-API-Key: key_abc123..." -X POST \
  "http://seu-dominio/admin-api.php?action=update_agent&id=AGENT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "temperature": 0.9,
    "description": "Descrição atualizada"
  }'
```

#### Definir Agente como Padrão

```bash
# Com sessão
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=make_default&id=AGENT_ID"

# Ou com chave de API
curl -H "X-API-Key: key_abc123..." -X POST \
  "http://seu-dominio/admin-api.php?action=make_default&id=AGENT_ID"
```

#### Deletar Agente

```bash
# Com sessão
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=delete_agent&id=AGENT_ID"

# Ou com chave de API
curl -H "X-API-Key: key_abc123..." -X POST \
  "http://seu-dominio/admin-api.php?action=delete_agent&id=AGENT_ID"
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

### Exemplo 5: Agente Multi-Tenant com Isolamento

Para ambientes com múltiplos clientes:

```json
{
  "name": "Suporte Cliente Premium",
  "description": "Agente dedicado para clientes premium com SLA diferenciado",
  "api_type": "responses",
  "model": "gpt-4o",
  "temperature": 0.5,
  "tenant_id": "tenant_premium_xyz",
  "system_message": "Você é um assistente premium. Priorize respostas rápidas e detalhadas.",
  "tools": [{"type": "file_search"}],
  "vector_store_ids": ["vs_kb_premium"],
  "max_output_tokens": 4096
}
```

**Vantagens:**
- ✅ Isolamento completo de dados entre tenants
- ✅ Configurações personalizadas por cliente
- ✅ Billing e usage tracking separados
- ✅ Vector stores dedicados

### Exemplo 6: Agente com LeadSense Integrado

Para detecção automática de oportunidades comerciais:

```json
{
  "name": "Assistente de Vendas Inteligente",
  "description": "Identifica e qualifica leads automaticamente nas conversas",
  "api_type": "responses",
  "model": "gpt-4o-mini",
  "temperature": 0.7,
  "system_message": "Você é um assistente de vendas. Identifique oportunidades comerciais e extraia informações de contato.",
  "tools": [{"type": "file_search"}],
  "vector_store_ids": ["vs_produtos", "vs_precos"]
}
```

**Configuração adicional (via `.env`):**
```bash
LEADSENSE_ENABLED=true
LEADSENSE_INTENT_THRESHOLD=0.6
LEADSENSE_SCORE_THRESHOLD=70
```

**Recursos do LeadSense:**
- 🎯 Detecção automática de intenção de compra
- 📊 Scoring de leads (0-100)
- 🏢 Extração de entidades (nome, email, telefone, empresa)
- 📈 Pipeline visual de CRM
- 🔔 Notificações Slack para leads qualificados
- 🔗 Webhooks para CRMs externos (HubSpot, Salesforce)

Ver [LEADSENSE_QUICKSTART.md](LEADSENSE_QUICKSTART.md) para detalhes.

### Exemplo 7: Agente com WhatsApp

Para atendimento omnichannel via WhatsApp Business:

```json
{
  "name": "Suporte WhatsApp",
  "description": "Agente para atendimento via WhatsApp com contexto preservado",
  "api_type": "responses",
  "model": "gpt-4o-mini",
  "temperature": 0.6,
  "system_message": "Você é um assistente via WhatsApp. Use respostas concisas e amigáveis.",
  "max_output_tokens": 1500
}
```

**Configuração do canal:**
```bash
# Via Admin API
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=link_agent_to_channel" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": "agent-whatsapp-id",
    "channel_type": "whatsapp",
    "channel_config": {
      "instance_id": "sua-instancia-zapi",
      "phone_number": "+5511999999999"
    }
  }'
```

**Recursos do canal WhatsApp:**
- 📱 Suporte a texto, imagens, documentos, áudio
- 💬 Sessões persistentes com contexto
- 🔄 Chunking automático de mensagens longas
- 🛑 Comandos STOP/START para opt-out
- 🔐 Verificação de assinatura de webhook
- ✅ Idempotência contra duplicatas

Ver [WHATSAPP_INTEGRATION.md](WHATSAPP_INTEGRATION.md) para detalhes.

## Recursos Avançados

### Multi-Tenancy

O sistema suporta isolamento completo entre tenants (clientes):

**Características:**
- Cada tenant tem seus próprios agentes, prompts, vector stores
- Isolamento de dados garantido por tenant_id
- Usuários administradores vinculados a tenants específicos
- Super-admins podem acessar todos os tenants
- Billing e quotas por tenant

**Configuração:**
```bash
# Criar tenant via API
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=create_tenant" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Empresa XYZ",
    "slug": "empresa-xyz",
    "plan": "premium",
    "billing_email": "billing@xyz.com"
  }'
```

Ver [MULTI_TENANCY.md](MULTI_TENANCY.md) para arquitetura completa.

### Observabilidade e Monitoramento

Monitore o desempenho e uso dos seus agentes:

**Métricas disponíveis:**
- 📊 Requisições por agente
- ⏱️ Latência (P95, P99)
- 💰 Tokens consumidos e custos
- ❌ Taxa de erros
- 👥 Usuários ativos
- 🔍 Uso de ferramentas (file_search, etc)

**Acesso:**
```bash
# Métricas Prometheus
curl "http://seu-dominio/metrics.php"

# Health check
curl "http://seu-dominio/admin-api.php?action=health"
```

**Dashboards:**
- Grafana pré-configurado em `observability/docker/grafana/`
- 15+ regras de alerta automáticas
- Logs estruturados com trace IDs
- Integração com Loki, Prometheus, Jaeger

Ver [OBSERVABILITY.md](OBSERVABILITY.md) para setup completo.

### Webhooks e Background Jobs

Execute tarefas assíncronas e integre com sistemas externos:

**Background Worker:**
```bash
# Iniciar worker para processar jobs
php scripts/worker.php

# Ou via systemd
sudo systemctl start chatbot-worker
```

**Webhooks customizados:**
```bash
# Registrar webhook
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=create_webhook_subscriber" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://seu-sistema.com/webhook",
    "events": ["agent.created", "lead.qualified", "vector_store.completed"],
    "secret": "webhook-secret-key"
  }'
```

**Eventos disponíveis:**
- `agent.*` - Criação, atualização, exclusão
- `lead.*` - Lead criado, qualificado, atualizado
- `vector_store.*` - Ingestion completa, falha
- `conversation.*` - Nova conversa, mensagem
- `job.*` - Job completado, falhou

Ver [WEBHOOK_EXTENSIBILITY.md](WEBHOOK_EXTENSIBILITY.md) para detalhes.

### Compliance e Privacidade

Recursos para adequação a GDPR, LGPD e regulamentações:

**Recursos disponíveis:**
- 🔒 Criptografia AES-256-GCM at rest
- 🗑️ Deleção completa de dados via API
- 📤 Exportação de dados de usuário
- ✅ Gestão de consentimento
- 🎭 Redação automática de PII em logs
- 📝 Audit trails completos
- ⏳ Políticas de retenção configuráveis
- 🛡️ Legal hold para investigações

**Exemplo - Exportar dados de usuário:**
```bash
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=export_user_data&user_id=USER_ID" \
  -o user_data.zip
```

**Exemplo - Deletar dados (GDPR/LGPD):**
```bash
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=delete_user_data" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "USER_ID",
    "reason": "User GDPR deletion request",
    "confirmation": "I confirm permanent deletion"
  }'
```

Ver [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md) para detalhes.

### Billing e Quotas

Controle custos e estabeleça limites de uso:

**Configuração de quotas:**
```bash
# Definir quota por tenant
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=set_tenant_quota" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "tenant_xyz",
    "quotas": {
      "max_tokens_per_month": 1000000,
      "max_requests_per_day": 10000,
      "max_vector_stores": 5,
      "max_agents": 10
    }
  }'
```

**Monitoramento de uso:**
```bash
# Obter uso atual do tenant
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=get_tenant_usage&tenant_id=tenant_xyz"
```

**Recursos:**
- 📊 Tracking de tokens, requests, storage
- 💰 Cálculo automático de custos
- 🚨 Alertas de limite (80%, 90%, 100%)
- 📈 Relatórios de billing mensais
- 🔒 Hard limits e soft limits
- 💳 Integração com gateways de pagamento

Ver [BILLING_METERING.md](BILLING_METERING.md) para detalhes.

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
- Um padrão por tenant em multi-tenancy

❌ **Evite:**
- Múltiplos defaults por tenant (só pode haver um)
- Agente experimental como default
- Trocar default frequentemente em produção

### 7. Multi-Tenancy

✅ **Faça:**
- Sempre especifique tenant_id ao criar recursos
- Use isolamento de tenant para dados sensíveis
- Configure quotas adequadas por tenant
- Monitore uso individual de cada tenant
- Documente relacionamentos entre tenants e agentes

❌ **Evite:**
- Compartilhar vector stores entre tenants sem necessidade
- Expor dados de um tenant para outro
- Quotas globais (use por tenant)
- Misturar dados de produção e teste no mesmo tenant

### 8. Segurança

✅ **Faça:**
- Use sessões ou API keys (não ADMIN_TOKEN legado)
- Implemente RBAC apropriado (viewer, admin, super-admin)
- Rotacione credenciais periodicamente
- Monitore audit logs regularmente
- Configure rate limiting por tenant
- Use HTTPS em produção
- Valide permissões antes de operações sensíveis

❌ **Evite:**
- Compartilhar API keys entre usuários
- Dar permissões de super-admin desnecessariamente
- Ignorar audit trails
- Expor secrets em logs ou respostas
- Rate limits muito permissivos

### 9. Observabilidade

✅ **Faça:**
- Configure Prometheus + Grafana
- Estabeleça alertas para métricas críticas
- Monitore custos por agente e tenant
- Rastreie erros com trace IDs
- Configure log aggregation (Loki, CloudWatch, etc)
- Revise dashboards regularmente

❌ **Evite:**
- Produção sem monitoramento
- Ignorar alertas de performance
- Logs excessivos em produção
- PII em logs (use redação automática)

### 10. Performance

✅ **Faça:**
- Configure max_output_tokens adequadamente
- Use models mais leves quando possível (gpt-4o-mini)
- Otimize vector stores (remova duplicatas)
- Configure caching quando apropriado
- Monitore latência por agente
- Execute load tests antes de mudanças grandes

❌ **Evite:**
- max_output_tokens muito alto sem necessidade
- Vector stores gigantes não otimizados
- Múltiplas chamadas sequenciais (use batch quando possível)
- Modelos caros para tarefas simples

## Solução de Problemas

### Erro: "Unauthorized" ou "Invalid session"

**Causa:** Sessão expirada ou não autenticado

**Solução:**
```bash
# Fazer login novamente
curl -i -c cookies.txt -X POST "http://seu-dominio/admin-api.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"email": "seu@email.com", "password": "sua-senha"}'

# Ou gerar uma nova chave de API
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=generate_api_key" \
  -H "Content-Type: application/json" \
  -d '{"name": "Minha Chave", "expires_at": "2025-12-31T23:59:59Z"}'
```

### Erro: "Forbidden" ou "Permission denied"

**Causa:** Usuário não tem permissão para a operação (RBAC)

**Solução:**
```bash
# Verificar suas permissões
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=current_user"

# Solicitar ao super-admin que atualize suas permissões
# Roles disponíveis: viewer (leitura), admin (leitura+escrita), super-admin (todos)
```

### Erro: "Invalid admin token" (Legado)

**Causa:** Token legado ADMIN_TOKEN não configurado ou incorreto

**Solução:**
```bash
# ⚠️ ADMIN_TOKEN está depreciado. Migre para sessões ou API keys!

# Se ainda precisar usar (temporário):
grep ADMIN_TOKEN .env

# Certifique-se de que tem pelo menos 32 caracteres
# Use o header correto:
curl -H "Authorization: Bearer SEU_TOKEN_AQUI" ...

# Recomendado: Migrar para autenticação moderna
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

**Causa:** Apache/Nginx não está servindo arquivos estáticos ou redirecionamento incorreto

**Solução:**
```apache
# Apache - certifique-se de que .htaccess está configurado
# Ou configure no VirtualHost:
<Directory "/var/www/html/public/admin">
    AllowOverride All
    Require all granted
</Directory>
```

```nginx
# Nginx - configuração correta
location /public/admin/ {
    alias /var/www/html/public/admin/;
    index index.html;
    try_files $uri $uri/ /public/admin/index.html;
}

location /admin-api.php {
    fastcgi_pass php-fpm:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### Erro: "Tenant not found" ou "Invalid tenant"

**Causa:** Requisição de usuário vinculado a tenant que não existe ou foi desativado

**Solução:**
```bash
# Verificar tenant do usuário
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=current_user"

# Verificar status do tenant
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=get_tenant&id=TENANT_ID"

# Reativar tenant (super-admin apenas)
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=update_tenant&id=TENANT_ID" \
  -H "Content-Type: application/json" \
  -d '{"status": "active"}'
```

### Erro: "Quota exceeded"

**Causa:** Tenant atingiu limite de uso configurado

**Solução:**
```bash
# Verificar uso atual
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=get_tenant_usage&tenant_id=TENANT_ID"

# Aumentar quotas (super-admin ou billing admin)
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=set_tenant_quota" \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": "TENANT_ID",
    "quotas": {
      "max_tokens_per_month": 2000000,
      "max_requests_per_day": 20000
    }
  }'
```

### Webhook não está sendo recebido

**Causa:** URL incorreta, SSL inválido, ou assinatura não validada

**Solução:**
```bash
# 1. Verificar registro do webhook
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=list_webhook_subscribers"

# 2. Verificar logs de webhook
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=list_webhook_logs&subscriber_id=SUB_ID"

# 3. Testar webhook manualmente
curl -X POST "https://seu-dominio/webhooks/openai.php" \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: test" \
  -d '{"event": "test"}'

# 4. Verificar que a URL é acessível publicamente (HTTPS obrigatório)
```

### Job queue está congestionada

**Causa:** Worker não está rodando ou há muitos jobs falhando

**Solução:**
```bash
# Verificar status do worker
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=health"

# Ver jobs pendentes
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=list_jobs&status=pending"

# Iniciar worker
php scripts/worker.php

# Ou via systemd
sudo systemctl start chatbot-worker
sudo systemctl status chatbot-worker

# Reprocessar jobs falhados
curl -b cookies.txt -X POST "http://seu-dominio/admin-api.php?action=retry_job&id=JOB_ID"

# Verificar dead letter queue
curl -b cookies.txt "http://seu-dominio/admin-api.php?action=list_dead_letter_queue"
```

## Recursos Adicionais

### Documentação Relacionada

**Primeiros Passos:**
- [README.md](../README.md) - Visão geral do projeto
- [QUICK_START.md](QUICK_START.md) - Guia de início rápido
- [INSTALLATION_WIZARD.md](INSTALLATION_WIZARD.md) - Instalação via interface web

**Arquitetura e Desenvolvimento:**
- [PROJECT_DESCRIPTION.md](PROJECT_DESCRIPTION.md) - Descrição completa do projeto
- [FEATURES.md](FEATURES.md) - Lista completa de features
- [PHASE1_DB_AGENT.md](PHASE1_DB_AGENT.md) - Agentes e banco de dados
- [PHASE2_ADMIN_UI.md](PHASE2_ADMIN_UI.md) - Interface administrativa
- [PHASE3_WORKERS_WEBHOOKS.md](PHASE3_WORKERS_WEBHOOKS.md) - Workers e RBAC
- [api.md](api.md) - Referência completa da API (190+ endpoints)
- [customization-guide.md](customization-guide.md) - Guia de customização (English)

**Recursos Avançados:**
- [MULTI_TENANCY.md](MULTI_TENANCY.md) - Arquitetura multi-tenant
- [WHATSAPP_INTEGRATION.md](WHATSAPP_INTEGRATION.md) - Integração WhatsApp
- [LEADSENSE_QUICKSTART.md](LEADSENSE_QUICKSTART.md) - LeadSense CRM
- [LEADSENSE_CRM.md](LEADSENSE_CRM.md) - Pipeline visual de CRM
- [HYBRID_GUARDRAILS.md](HYBRID_GUARDRAILS.md) - Structured outputs
- [prompt_builder_overview.md](prompt_builder_overview.md) - Construtor de prompts
- [WEBHOOK_EXTENSIBILITY.md](WEBHOOK_EXTENSIBILITY.md) - Webhooks customizados

**Segurança e Compliance:**
- [SECURITY_MODEL.md](SECURITY_MODEL.md) - Modelo de segurança
- [RESOURCE_AUTHORIZATION.md](RESOURCE_AUTHORIZATION.md) - Autorização de recursos
- [COMPLIANCE_OPERATIONS.md](COMPLIANCE_OPERATIONS.md) - GDPR/LGPD compliance
- [AUDIT_TRAILS.md](AUDIT_TRAILS.md) - Auditoria completa

**Operações e Produção:**
- [OPERATIONS_GUIDE.md](OPERATIONS_GUIDE.md) - Guia operacional
- [deployment.md](deployment.md) - Deploy em produção
- [OBSERVABILITY.md](OBSERVABILITY.md) - Monitoramento e métricas
- [ops/backup_restore.md](ops/backup_restore.md) - Backup e restore
- [ops/disaster_recovery.md](ops/disaster_recovery.md) - Disaster recovery
- [ops/secrets_management.md](ops/secrets_management.md) - Gestão de secrets

**Billing e Monetização:**
- [BILLING_METERING.md](BILLING_METERING.md) - Sistema de billing
- [MULTI_TENANT_BILLING.md](MULTI_TENANT_BILLING.md) - Billing multi-tenant
- [WHITELABEL_PUBLISHING.md](WHITELABEL_PUBLISHING.md) - Publicação whitelabel

### Exemplos de Código

```bash
# Exemplos completos no repositório
examples/
├── basic-integration.html      # Integração básica
├── advanced-agent.js           # Agente com todas features
├── multi-tenant-setup.sh       # Setup multi-tenant
├── leadsense-config.json       # Configuração LeadSense
└── whatsapp-agent.json         # Agente WhatsApp completo
```

### Scripts Úteis

```bash
# Operacionais
scripts/worker.php              # Background worker
scripts/db_backup.sh            # Backup automático
scripts/db_restore.sh           # Restore de backup
scripts/run_migrations.php      # Executar migrations
scripts/smoke_test.sh           # Smoke tests

# Desenvolvimento
tests/run_tests.php             # Suite de testes (183 tests)
composer run analyze            # PHPStan static analysis
npm run lint                    # ESLint frontend

# Load testing
tests/load/chat_api.js          # K6 load test
```

### Suporte e Comunidade

- 📖 [Documentação Completa](../docs/)
- 🐛 [Reportar Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
- 💬 [Discussões](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)
- 🤝 [Guia de Contribuição](CONTRIBUTING.md)
- 📊 [Roadmap Público](https://github.com/suporterfid/gpt-chatbot-boilerplate/projects)
- ⭐ [Dar uma Estrela](https://github.com/suporterfid/gpt-chatbot-boilerplate)

### Dicas de Produção

**Antes de ir para produção:**
- ✅ Use MySQL/PostgreSQL (não SQLite)
- ✅ Configure backup automático (`scripts/db_backup.sh`)
- ✅ Habilite monitoramento (Prometheus + Grafana)
- ✅ Configure HTTPS com certificado válido
- ✅ Inicie o background worker (`scripts/worker.php`)
- ✅ Configure rate limiting adequado
- ✅ Rode smoke tests (`scripts/smoke_test.sh`)
- ✅ Execute load tests (`k6 run tests/load/chat_api.js`)
- ✅ Configure alertas no Grafana
- ✅ Documente seu disaster recovery plan
- ✅ Configure rotação de logs
- ✅ Revise segurança com [SECURITY_MODEL.md](SECURITY_MODEL.md)

**Métricas recomendadas para monitorar:**
- 📊 Latência P95 e P99 por agente
- 💰 Custo por tenant (tokens consumidos)
- ❌ Taxa de erro e tipos de erro
- 👥 Usuários ativos e conversas por dia
- 🔍 Taxa de uso de ferramentas (file_search, etc)
- ⚡ Health do worker e jobs pendentes
- 💾 Uso de storage (vector stores, arquivos)
- 🔐 Tentativas de autenticação falhadas

---

**Desenvolvido com ❤️ pela comunidade open source**

Se este guia foi útil, considere dar uma ⭐ no [repositório](https://github.com/suporterfid/gpt-chatbot-boilerplate)!
