---
applyTo: "tests/**/*.php"
description: "Regras específicas para testes e test infrastructure"
---

# Instruções para Testes - gpt-chatbot-boilerplate

## Arquivos Alvo
- `tests/run_tests.php` - Runner principal de testes
- `tests/test_*.php` - Arquivos de teste individuais
- `tests/helpers/*.php` - Helpers e utilities para testes
- `tests/load/*.js` - Testes de carga (k6)

## Filosofia de Testes

### Princípios
- **Independência**: Cada teste deve rodar independentemente.
- **Clareza**: Nomes de testes devem descrever o que está sendo testado.
- **Velocidade**: Testes devem ser rápidos para feedback imediato.
- **Confiabilidade**: Evitar testes flaky (intermitentes).
- **Cobertura**: Focar em testes que agregam valor, não apenas cobertura de linha.

### Tipos de Testes
1. **Unit Tests**: Testam componentes isolados (classes, métodos).
2. **Integration Tests**: Testam interação entre componentes (DB, API).
3. **Smoke Tests**: Verificam funcionalidades críticas em produção.
4. **Load Tests**: Avaliam performance sob carga.

## Estrutura de Testes

### Formato Padrão
```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';
require_once __DIR__ . '/../includes/ExampleService.php';

echo "\n=== Testing ExampleService ===\n";

// Test 1: Basic functionality
echo "\n--- Test 1: Basic Operation ---\n";
try {
    $service = new ExampleService($db);
    $result = $service->doSomething('input');
    
    if ($result['success']) {
        echo "✓ PASS: Basic operation successful\n";
    } else {
        echo "✗ FAIL: Expected success, got: " . json_encode($result) . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Error handling
echo "\n--- Test 2: Error Handling ---\n";
try {
    $result = $service->doSomething('');
    
    if (isset($result['error'])) {
        echo "✓ PASS: Error handled correctly\n";
    } else {
        echo "✗ FAIL: Expected error for empty input\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Unexpected exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== All ExampleService tests passed ===\n";
```

### Nomenclatura
- **Arquivos**: `test_feature_name.php`
- **Testes**: Descrever o que está sendo testado: "Test 1: User creation with valid data"
- **Assertions**: Mensagens claras: "✓ PASS: User created with ID 123"

## Boas Práticas

### Setup e Teardown
```php
// Setup antes dos testes
$db->beginTransaction();

try {
    // Rodar testes aqui
    
    // Rollback no final para limpar dados de teste
    $db->rollBack();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

### Dados de Teste
- Usar dados únicos (timestamps, UUIDs) para evitar colisões.
- Limpar dados de teste após execução.
- Não depender de dados pré-existentes no banco.
- Usar fixtures ou factories quando apropriado.

```php
// Gerar dados únicos
$uniqueId = 'test_' . time() . '_' . bin2hex(random_bytes(4));
$testEmail = "test_{$uniqueId}@example.com";
```

### Asserções Claras
```php
// BOM: Mensagem descritiva
if ($result['status'] === 'success') {
    echo "✓ PASS: Agent created successfully with ID: {$result['agent_id']}\n";
} else {
    echo "✗ FAIL: Expected status='success', got: {$result['status']}\n";
    echo "   Full response: " . json_encode($result) . "\n";
    exit(1);
}

// RUIM: Mensagem genérica
if ($result['status'] === 'success') {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    exit(1);
}
```

### Testes de Error Paths
- Sempre testar caminhos de erro, não apenas happy paths.
- Verificar mensagens de erro apropriadas.
- Testar edge cases (empty strings, null, boundaries).

```php
// Test: Empty input
$result = $service->process('');
if (isset($result['error']) && str_contains($result['error'], 'required')) {
    echo "✓ PASS: Empty input rejected with appropriate error\n";
} else {
    echo "✗ FAIL: Expected error for empty input\n";
    exit(1);
}

// Test: Oversized input
$largeInput = str_repeat('x', 100000);
$result = $service->process($largeInput);
if (isset($result['error']) && str_contains($result['error'], 'too large')) {
    echo "✓ PASS: Oversized input rejected\n";
} else {
    echo "✗ FAIL: Expected error for oversized input\n";
    exit(1);
}
```

## Testes de Integração

### Database Tests
- Testar migrações funcionam corretamente.
- Verificar constraints e índices.
- Testar transactions e rollbacks.

```php
// Testar constraint de unique
$db->exec("INSERT INTO agents (name, slug) VALUES ('Test', 'test-slug')");
try {
    $db->exec("INSERT INTO agents (name, slug) VALUES ('Test2', 'test-slug')");
    echo "✗ FAIL: Duplicate slug should have been rejected\n";
    exit(1);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'UNIQUE constraint')) {
        echo "✓ PASS: Unique constraint enforced on slug\n";
    } else {
        throw $e;
    }
}
```

### API Tests
- Testar endpoints com diferentes inputs.
- Verificar códigos de status HTTP.
- Validar formato de resposta JSON.

```php
// Helper para fazer request à API
function makeApiRequest(string $action, array $data = []): array
{
    $ch = curl_init("http://localhost:8088/admin-api.php?action={$action}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Admin-Token: test_token_123'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Testar endpoint
$response = makeApiRequest('create_agent', [
    'name' => 'Test Agent',
    'model' => 'gpt-4o-mini'
]);

if ($response['status'] === 200 && isset($response['body']['agent_id'])) {
    echo "✓ PASS: Agent created via API\n";
} else {
    echo "✗ FAIL: API request failed\n";
    exit(1);
}
```

## Testes de Segurança

### Authentication Tests
```php
// Testar acesso sem autenticação
$response = makeApiRequest('list_agents', [], includeAuth: false);
if ($response['status'] === 401) {
    echo "✓ PASS: Unauthenticated request rejected\n";
} else {
    echo "✗ FAIL: Expected 401, got: {$response['status']}\n";
    exit(1);
}
```

### Authorization Tests (RBAC)
```php
// Testar permissões por role
$viewerToken = createUserWithRole('viewer');
$response = makeApiRequestWithToken('delete_agent', ['id' => 1], $viewerToken);

if ($response['status'] === 403) {
    echo "✓ PASS: Viewer cannot delete agents\n";
} else {
    echo "✗ FAIL: Viewer should not have delete permission\n";
    exit(1);
}
```

### Input Validation Tests
```php
// Testar SQL injection prevention
$maliciousInput = "'; DROP TABLE agents; --";
$result = $service->getAgent($maliciousInput);

if (isset($result['error']) || $result === null) {
    // Verificar que a tabela ainda existe
    $tableExists = $db->query("SELECT COUNT(*) FROM agents")->fetchColumn() !== false;
    if ($tableExists) {
        echo "✓ PASS: SQL injection prevented\n";
    } else {
        echo "✗ FAIL: SQL injection was successful!\n";
        exit(1);
    }
}
```

## Smoke Tests (Production Readiness)

### Executar Smoke Tests
```bash
bash scripts/smoke_test.sh
```

### O Que Incluir
- Verificação de configuração (env vars, DB connection).
- Teste de endpoints críticos (health check, chat endpoint).
- Validação de permissões de arquivo.
- Verificação de serviços externos (OpenAI API).

### Exemplo
```php
// Smoke test: Verificar OpenAI API está acessível
$client = new OpenAIClient($config);
try {
    $models = $client->listModels();
    if (!empty($models)) {
        echo "✓ PASS: OpenAI API accessible\n";
    } else {
        echo "✗ FAIL: OpenAI API returned empty model list\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Cannot connect to OpenAI API: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Load Tests (k6)

### Estrutura de Load Test
```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 10 },  // Ramp up
        { duration: '1m', target: 50 },   // Sustained load
        { duration: '30s', target: 0 },   // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of requests under 500ms
        http_req_failed: ['rate<0.1'],    // Less than 10% errors
    },
};

export default function () {
    const payload = JSON.stringify({
        message: 'Hello, how can you help me?',
        api_type: 'chat',
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
        },
    };

    const res = http.post('http://localhost:8088/chat-unified.php', payload, params);

    check(res, {
        'status is 200': (r) => r.status === 200,
        'response has data': (r) => r.json('response') !== undefined,
    });

    sleep(1);
}
```

### Executar Load Tests
```bash
k6 run tests/load/chat_api.js
```

## Helpers e Utilities

### Criar Helpers Reutilizáveis
```php
// tests/helpers/test_helpers.php
function createTestAgent(Database $db, array $overrides = []): int
{
    $defaults = [
        'name' => 'Test Agent ' . time(),
        'model' => 'gpt-4o-mini',
        'instructions' => 'You are a helpful assistant',
    ];
    
    $data = array_merge($defaults, $overrides);
    
    $stmt = $db->prepare(
        "INSERT INTO agents (name, model, instructions) VALUES (:name, :model, :instructions)"
    );
    $stmt->execute($data);
    
    return (int)$db->lastInsertId();
}

function cleanupTestData(Database $db): void
{
    $db->exec("DELETE FROM agents WHERE name LIKE 'Test Agent%'");
    $db->exec("DELETE FROM admin_users WHERE email LIKE 'test%@example.com'");
}
```

## Continuous Integration

### GitHub Actions
- Testes rodam automaticamente em cada PR.
- Falhas de teste bloqueiam merge.
- Smoke tests rodam em deploy para staging/production.

### Comandos de CI
```yaml
# .github/workflows/tests.yml
- name: Run tests
  run: php tests/run_tests.php

- name: Run static analysis
  run: composer run analyze

- name: Run smoke tests
  run: bash scripts/smoke_test.sh
```

## Debugging de Testes

### Quando um Teste Falha
1. Ler mensagem de erro cuidadosamente.
2. Verificar dados de teste e estado do banco.
3. Adicionar output de debug temporário.
4. Rodar teste isoladamente para reproduzir.
5. Verificar logs de erro (error_log).

### Debug Output
```php
// Adicionar output detalhado temporariamente
echo "\n[DEBUG] Current state:\n";
var_dump($currentState);

echo "\n[DEBUG] Expected state:\n";
var_dump($expectedState);

echo "\n[DEBUG] Database contents:\n";
$rows = $db->query("SELECT * FROM agents")->fetchAll(PDO::FETCH_ASSOC);
var_dump($rows);
```

## Checklist de Revisão

Antes de adicionar/modificar testes:

- [ ] Teste tem nome descritivo
- [ ] Teste é independente (não depende de outros)
- [ ] Teste limpa dados após execução
- [ ] Mensagens de asserção são claras
- [ ] Testa tanto happy path quanto error paths
- [ ] Testa edge cases relevantes
- [ ] Não tem hardcoded values que mudam (IDs, timestamps)
- [ ] Roda rapidamente (< 5s por teste quando possível)
- [ ] Pode ser executado localmente
- [ ] Pode ser executado em CI
- [ ] Documentado se lógica não é óbvia
