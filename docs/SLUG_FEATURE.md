# Agent Slug Feature Documentation

## Visão Geral

O recurso de slug permite atribuir um identificador único e legível a cada agente, facilitando o acesso e referência em integrações e URLs.

## Características

### 1. Campo de Slug na Interface
- Localizado na etapa "Identidade do agente" do wizard de criação/edição
- Validação automática de formato
- Sanitização em tempo real ao sair do campo
- Campo opcional (agentes podem não ter slug)

### 2. Formato do Slug
- **Tamanho**: 3 a 64 caracteres
- **Caracteres permitidos**: 
  - Letras minúsculas (a-z)
  - Números (0-9)
  - Hífens (-)
- **Validação**: Padrão regex `^[a-z0-9-]{3,64}$`
- **Unicidade**: Cada slug deve ser único no contexto do tenant

### 3. Sanitização Automática

O sistema automaticamente sanitiza o slug informado:

| Entrada | Sanitizado | Descrição |
|---------|-----------|-----------|
| `Atendimento Premium` | `atendimento-premium` | Espaços → hífens, minúsculas |
| `vendas_2024` | `vendas2024` | Underscores removidos |
| `--suporte--` | `suporte` | Hífens múltiplos/bordas removidos |
| `Test Agent #1` | `test-agent-1` | Caracteres especiais removidos |

## Uso via API

### Criar Agente com Slug

```bash
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer ${ADMIN_API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Suporte Premium",
    "vanity_path": "suporte-premium",
    "description": "Agente especializado em suporte premium",
    "api_type": "responses",
    "model": "gpt-4o-mini"
  }'
```

### Atualizar Slug

```bash
curl -X POST "http://localhost/admin-api.php?action=update_agent&id=AGENT_ID" \
  -H "Authorization: Bearer ${ADMIN_API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "vanity_path": "novo-slug"
  }'
```

### Remover Slug

```bash
curl -X POST "http://localhost/admin-api.php?action=update_agent&id=AGENT_ID" \
  -H "Authorization: Bearer ${ADMIN_API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "vanity_path": null
  }'
```

## Uso no Código PHP

### Buscar Agente por Slug

```php
<?php
require_once 'includes/AgentService.php';

$agentService = new AgentService($db);

// Buscar agente pelo slug
$agent = $agentService->getAgentBySlug('suporte-premium');

if ($agent) {
    echo "Agente encontrado: {$agent['name']}\n";
    echo "ID: {$agent['id']}\n";
} else {
    echo "Agente não encontrado com esse slug\n";
}
```

### Criar Agente com Slug

```php
$agent = $agentService->createAgent([
    'name' => 'Agente de Vendas',
    'slug' => 'agente-vendas',  // ou 'vanity_path'
    'api_type' => 'responses',
    'model' => 'gpt-4o-mini',
    'temperature' => 0.7
]);
```

### Atualizar Slug

```php
$updated = $agentService->updateAgent($agentId, [
    'slug' => 'novo-slug'
]);
```

## Casos de Uso

### 1. URLs Amigáveis
```
❌ Antes: /chat?agent=a1b2c3d4-e5f6-7890-abcd-ef1234567890
✅ Depois: /chat?agent=suporte-premium
```

### 2. Configuração de Integrações
```yaml
# Configuração de webhook
whatsapp:
  agent_slug: "atendimento-whatsapp"
  webhook_url: "https://api.example.com/webhook"
```

### 3. Multi-tenancy
```php
// Cada tenant pode ter seu próprio "suporte"
$tenant1Agent = $agentService->getAgentBySlug('suporte'); // Tenant 1
$tenant2Agent = $agentService->getAgentBySlug('suporte'); // Tenant 2
// Retornam agentes diferentes baseados no contexto do tenant
```

## Validações e Erros

### Formato Inválido
```json
{
  "error": "Invalid slug format: must be 3-64 characters, lowercase letters, numbers and hyphens only"
}
```

### Slug Duplicado
```json
{
  "error": "Slug already in use"
}
```

### Slug Muito Curto
```json
{
  "error": "Invalid slug format: must be 3-64 characters, lowercase letters, numbers and hyphens only"
}
```

## Interface do Usuário

### Campo de Slug no Wizard

Na etapa "Identidade do agente" do wizard de criação/edição, você encontrará:

```
┌─────────────────────────────────────────────┐
│ Nome *                                       │
│ ┌─────────────────────────────────────────┐ │
│ │ Atendimento Premium                     │ │
│ └─────────────────────────────────────────┘ │
│ Este nome será exibido nas listagens        │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ Slug (identificador único)                  │
│ ┌─────────────────────────────────────────┐ │
│ │ atendimento-premium                     │ │
│ └─────────────────────────────────────────┘ │
│ 3-64 caracteres: letras minúsculas,         │
│ números e hífens. Deixe em branco para      │
│ não usar slug.                              │
└─────────────────────────────────────────────┘
```

### Exibição no Resumo

No resumo do agente, o slug é exibido:

```
Resumo do agente
─────────────────────────────────
Nome:        Atendimento Premium
Slug:        atendimento-premium
Descrição:   Agente especializado...
API:         responses
Modelo:      gpt-4o-mini
```

## Migração de Dados

O campo `vanity_path` foi adicionado à tabela `agents` na migration `018_add_whitelabel_fields.sql`. Agentes existentes têm `vanity_path = NULL` por padrão.

Para adicionar slugs a agentes existentes:

```php
// Exemplo de script de migração
$agents = $agentService->listAgents();
foreach ($agents as $agent) {
    if ($agent['vanity_path'] === null) {
        // Gerar slug baseado no nome
        $slug = strtolower($agent['name']);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        
        try {
            $agentService->updateAgent($agent['id'], [
                'slug' => $slug
            ]);
            echo "Updated {$agent['name']} with slug: $slug\n";
        } catch (Exception $e) {
            echo "Skipped {$agent['name']}: {$e->getMessage()}\n";
        }
    }
}
```

## Testes

Os testes automatizados estão em `tests/test_agent_slug.php` e cobrem:

- ✅ Criação de agentes com slug
- ✅ Validação de formato
- ✅ Validação de unicidade
- ✅ Sanitização automática
- ✅ Busca por slug
- ✅ Atualização de slug
- ✅ Remoção de slug

Execute os testes:
```bash
php tests/test_agent_slug.php
```

## Limitações Conhecidas

1. **Não há histórico de slugs**: Se um slug é alterado, o slug antigo não fica reservado
2. **Case-sensitive em busca**: Embora slugs sejam armazenados em lowercase, a busca é case-sensitive
3. **Sem validação de palavras reservadas**: Slugs como "admin", "api", etc. são permitidos

## Roadmap Futuro

- [ ] Histórico de slugs para preservar links antigos
- [ ] Validação de palavras reservadas
- [ ] Sugestão automática de slug baseado no nome
- [ ] Busca case-insensitive
- [ ] Aliases de slug (múltiplos slugs apontando para o mesmo agente)
