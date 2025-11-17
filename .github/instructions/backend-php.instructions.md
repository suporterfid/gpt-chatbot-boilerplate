---
applyTo: "**/*.php"
description: "Regras específicas para código backend PHP (includes/, APIs, endpoints)"
---

# Instruções Backend PHP - gpt-chatbot-boilerplate

## Arquivos Alvo
- `includes/*.php` - Serviços e classes core
- `chat-unified.php` - Endpoint principal de chat
- `admin-api.php` - API administrativa
- `metrics.php` - Endpoint de métricas
- `config.php` - Configuração principal
- `webhooks/*.php` - Handlers de webhooks

## Padrões de Código PSR-12

### Estilo e Convenções
- **PSR-12**: Seguir rigorosamente o padrão PSR-12 de coding style.
- **Strict Types**: Usar `declare(strict_types=1);` no topo de novos arquivos core.
- **Nomenclatura**:
  - Classes: `PascalCase`
  - Métodos/funções: `camelCase`
  - Constantes: `UPPER_SNAKE_CASE`
- **Type Hints**: Sempre especificar tipos de parâmetros e retorno quando possível.

### Estrutura de Classes
```php
<?php
declare(strict_types=1);

/**
 * Descrição breve da classe.
 * 
 * @package GPT_Chatbot
 */
class ExampleService
{
    private Database $db;
    
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    public function doSomething(string $input): array
    {
        // Early return para validação
        if (empty($input)) {
            return ['error' => 'Input required'];
        }
        
        // Lógica principal
        return $this->processInput($input);
    }
    
    private function processInput(string $input): array
    {
        // Implementação
    }
}
```

## Arquitetura e Separação de Responsabilidades

### Componentes Core

#### ChatHandler (includes/ChatHandler.php)
- **Responsabilidade**: Orquestração do chat, validação, rate limiting
- **Não deve**: Fazer chamadas diretas à OpenAI (usar OpenAIClient)
- **Deve**: Validar entrada, gerenciar histórico, processar uploads

#### OpenAIClient (includes/OpenAIClient.php)
- **Responsabilidade**: Transporte HTTP para APIs OpenAI
- **Não deve**: Conter lógica de negócio
- **Deve**: Gerenciar streaming, retry logic, file uploads

#### AgentService (includes/AgentService.php)
- **Responsabilidade**: CRUD de agentes, gerenciamento de configurações
- **Não deve**: Processar mensagens de chat
- **Deve**: Mesclar configurações (Request → Agent → Config)

#### AdminAuth (includes/AdminAuth.php)
- **Responsabilidade**: Autenticação, autorização, RBAC
- **Não deve**: Conter lógica de negócio de outros domínios
- **Deve**: Validar permissões, gerenciar sessões, API keys

### Endpoints Principais

#### chat-unified.php
- API unificada suportando Chat Completions e Responses API
- Negociação de SSE streaming
- Validação de entrada e rate limiting
- Não misturar lógica de apresentação com lógica de negócio

#### admin-api.php
- API RESTful para operações administrativas
- Autenticação obrigatória (session ou API key)
- Validação RBAC para todas as operações
- Retornar JSON estruturado consistente

## Padrões de Código

### Dependency Injection
- Preferir injeção de dependências via construtor.
- Evitar usar `global` ou `$_GET`/`$_POST` diretamente em classes.
- Passar configuração explicitamente, não acessar `config.php` diretamente.

### Early Returns
```php
public function processData(array $data): array
{
    // Validação com early return
    if (empty($data)) {
        return ['error' => 'Data required'];
    }
    
    if (!isset($data['required_field'])) {
        return ['error' => 'Missing required_field'];
    }
    
    // Lógica principal
    return $this->doProcessing($data);
}
```

### Error Handling
```php
try {
    $result = $this->riskyOperation();
} catch (DatabaseException $e) {
    error_log("Database error: " . $e->getMessage());
    return ['error' => 'Database operation failed'];
} catch (Exception $e) {
    error_log("Unexpected error: " . $e->getMessage());
    return ['error' => 'An unexpected error occurred'];
}
```

### Métodos Pequenos e Focados
- Cada método deve ter uma única responsabilidade.
- Extrair lógica complexa em métodos privados auxiliares.
- Manter métodos públicos com no máximo 50 linhas.

## Dual API Support

### Switching Between APIs
O projeto suporta tanto Chat Completions quanto Responses API:

```php
// Respeitar configuração API_TYPE
$apiType = $config['api_type'] ?? 'chat';

if ($apiType === 'responses') {
    // Usar Responses API com assistant_id
} else {
    // Usar Chat Completions API tradicional
}
```

### Garantir Funcionalidades em Ambos
- Streaming deve funcionar em ambos os modos
- Tool calling deve ser suportado
- File uploads devem funcionar
- Configuração de agentes deve ser respeitada

## Segurança

### Validação de Entrada
```php
// Sanitizar e validar sempre
$message = trim($_POST['message'] ?? '');
if (strlen($message) > 10000) {
    return ['error' => 'Message too long'];
}

// Validar tipos
$agentId = filter_var($_POST['agent_id'] ?? null, FILTER_VALIDATE_INT);
if ($agentId === false) {
    return ['error' => 'Invalid agent_id'];
}
```

### Secrets e Configuração
- **NUNCA** expor `OPENAI_API_KEY` em logs ou respostas.
- Usar variáveis de ambiente via `config.php`.
- Não commitar secrets no código.
- Validar existência de configurações críticas no bootstrap.

### SQL Injection Prevention
```php
// Usar prepared statements sempre
$stmt = $db->prepare("SELECT * FROM agents WHERE id = :id");
$stmt->execute([':id' => $agentId]);

// NUNCA concatenar strings SQL com input do usuário
// ERRADO: $query = "SELECT * FROM agents WHERE id = " . $id;
```

### File Upload Security
```php
// Validar tipo de arquivo
$allowedTypes = ['application/pdf', 'text/plain', 'image/jpeg'];
if (!in_array($file['type'], $allowedTypes)) {
    return ['error' => 'File type not allowed'];
}

// Validar tamanho
$maxSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxSize) {
    return ['error' => 'File too large'];
}

// Usar nome de arquivo seguro
$safeName = bin2hex(random_bytes(16)) . '_' . basename($file['name']);
```

### Rate Limiting
- Implementar rate limiting em endpoints públicos.
- Usar IP-based sliding window.
- Configurar limites via `config.php` ou tenant-specific.

## Database Operations

### Migrations
- Usar arquivos SQL em `db/migrations/`.
- Nomear sequencialmente: `001_description.sql`, `002_description.sql`.
- Incluir comentários explicando o propósito.
- Testar rollback quando possível.

### Query Performance
- Usar índices apropriados (ver migrations).
- Evitar SELECT * quando possível.
- Paginar resultados grandes.
- Usar EXPLAIN para queries complexas.

### Transactions
```php
$db->beginTransaction();
try {
    // Múltiplas operações
    $db->exec("INSERT ...");
    $db->exec("UPDATE ...");
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

## Testes e Qualidade

### Comandos de Teste
```bash
# Todos os testes
php tests/run_tests.php

# Smoke tests
bash scripts/smoke_test.sh

# Análise estática
composer run analyze

# Se mudanças em DB/migrations
php scripts/run_migrations.php
```

### Escrever Testes
- Adicionar testes em `tests/` para novas funcionalidades.
- Manter testes focados e independentes.
- Usar nomes descritivos de testes.
- Testar edge cases e error paths.

### PHPStan
- Código deve passar no nível configurado em `phpstan.neon`.
- Corrigir warnings antes de commitar.
- Não usar `@phpstan-ignore` sem justificativa.

## Observabilidade

### Logging
```php
// Usar ObservabilityLogger quando disponível
$logger->info('Operation completed', [
    'user_id' => $userId,
    'duration_ms' => $duration
]);

// Para errors
error_log("Critical error: " . $e->getMessage());
```

### Métricas
- Coletar métricas em operações críticas.
- Usar MetricsCollector para Prometheus.
- Incluir labels relevantes (agent_id, api_type, status).

### Tracing
- Adicionar trace spans para operações lentas.
- Incluir contexto relevante (conversation_id, user_id).

## Multi-Tenancy

### Tenant Context
- Sempre passar tenant_id quando relevante.
- Validar acesso a recursos por tenant.
- Isolar dados entre tenants.

### Resource Authorization
```php
// Verificar permissões antes de operações
$authService = new ResourceAuthService($db);
if (!$authService->canAccess($userId, 'agent', $agentId, 'update')) {
    return ['error' => 'Permission denied'];
}
```

## Comentários e Documentação

### PHPDoc
```php
/**
 * Processa mensagem do usuário e retorna resposta do assistente.
 * 
 * @param string $message Mensagem do usuário
 * @param array $options Opções de configuração (agent_id, api_type, etc)
 * @return array Resposta contendo 'response' ou 'error'
 * @throws OpenAIException Se a API OpenAI falhar
 */
public function processMessage(string $message, array $options = []): array
{
    // Implementação
}
```

### Comentários Inline
- Comentar lógica complexa ou não óbvia.
- Explicar decisões de design importantes.
- Documentar workarounds temporários com TODO/FIXME.
- Não comentar código óbvio.

## Checklist de Revisão

Antes de finalizar mudanças em código PHP:

- [ ] Código segue PSR-12
- [ ] Type hints em parâmetros e retornos
- [ ] Validação de entrada implementada
- [ ] Error handling apropriado
- [ ] Secrets não expostos
- [ ] SQL usa prepared statements
- [ ] `composer run analyze` passa
- [ ] `php tests/run_tests.php` passa
- [ ] Testes adicionados para novas funcionalidades
- [ ] Documentação PHPDoc atualizada
- [ ] Logging apropriado incluído
- [ ] Suporta dual API (se aplicável)
- [ ] Multi-tenancy respeitado (se aplicável)
