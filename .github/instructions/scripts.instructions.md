---
applyTo: "scripts/**/*.{php,sh}"
description: "Regras específicas para scripts de manutenção e automação"
---

# Instruções para Scripts - gpt-chatbot-boilerplate

## Arquivos Alvo
- `scripts/*.php` - Scripts PHP de manutenção
- `scripts/*.sh` - Shell scripts de automação
- `scripts/worker.php` - Background worker
- `scripts/run_migrations.php` - Migration runner

## Filosofia de Scripts

### Princípios
- **Idempotência**: Scripts devem ser seguros para executar múltiplas vezes.
- **Logging**: Sempre logar operações importantes e erros.
- **Exit Codes**: Usar exit codes apropriados (0 = sucesso, não-zero = erro).
- **Validação**: Validar pré-condições antes de executar operações críticas.
- **Documentação**: Incluir help/usage quando aplicável.

### Tipos de Scripts
1. **Maintenance Scripts**: Backup, restore, cleanup
2. **Migration Scripts**: Database migrations
3. **Worker Scripts**: Background jobs, queue processing
4. **Deployment Scripts**: Setup, configuration, health checks
5. **Utility Scripts**: Data import/export, reporting

## Scripts PHP

### Estrutura Padrão
```php
#!/usr/bin/env php
<?php
/**
 * Script Description: Brief description of what this script does
 * 
 * Usage: php scripts/example_script.php [options]
 * 
 * Options:
 *   --help     Show this help message
 *   --dry-run  Run without making changes
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/DB.php';

// Parse command line arguments
$options = getopt('', ['help', 'dry-run']);

if (isset($options['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

$dryRun = isset($options['dry-run']);

// Main execution
try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting example script\n";
    
    if ($dryRun) {
        echo "DRY RUN MODE - No changes will be made\n";
    }
    
    // Script logic here
    $result = performOperation($db, $dryRun);
    
    echo "[" . date('Y-m-d H:i:s') . "] Script completed successfully\n";
    exit(0);
    
} catch (Exception $e) {
    error_log("Script failed: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

function performOperation(Database $db, bool $dryRun): array
{
    // Implementation
}
```

### Boas Práticas PHP Scripts

#### Validação de Pré-condições
```php
// Verificar ambiente
if (getenv('APP_ENV') === 'production' && !$forceProduction) {
    echo "ERROR: Cannot run in production without --force-production flag\n";
    exit(1);
}

// Verificar dependências
if (!extension_loaded('pdo')) {
    echo "ERROR: PDO extension is required\n";
    exit(1);
}

// Verificar arquivos/diretórios necessários
if (!is_writable('/path/to/output')) {
    echo "ERROR: Output directory is not writable\n";
    exit(1);
}
```

#### Logging Estruturado
```php
function logInfo(string $message, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = empty($context) ? '' : ' ' . json_encode($context);
    echo "[{$timestamp}] INFO: {$message}{$contextStr}\n";
}

function logError(string $message, Exception $e = null): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] ERROR: {$message}\n";
    if ($e) {
        echo "  Exception: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        error_log($message . ': ' . $e->getMessage());
    }
}
```

#### Progress Reporting
```php
// Para operações longas
$total = count($items);
$processed = 0;

foreach ($items as $item) {
    processItem($item);
    $processed++;
    
    // Reportar progresso a cada 10%
    if ($processed % ceil($total / 10) === 0) {
        $percentage = round(($processed / $total) * 100);
        echo "Progress: {$processed}/{$total} ({$percentage}%)\n";
    }
}
```

#### Dry Run Mode
```php
function updateRecord(Database $db, int $id, array $data, bool $dryRun): void
{
    $query = "UPDATE table SET field = :value WHERE id = :id";
    
    if ($dryRun) {
        echo "DRY RUN: Would execute: {$query}\n";
        echo "  Parameters: " . json_encode(['id' => $id, 'value' => $data['value']]) . "\n";
        return;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $id, 'value' => $data['value']]);
    echo "Updated record ID: {$id}\n";
}
```

## Shell Scripts

### Estrutura Padrão
```bash
#!/bin/bash
set -euo pipefail  # Exit on error, undefined vars, pipe failures

# Script Description: Brief description
# Usage: ./script_name.sh [options]

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

# Parse arguments
FORCE=false
while [[ $# -gt 0 ]]; do
    case $1 in
        --force)
            FORCE=true
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --force    Force operation even with warnings"
            echo "  --help     Show this help message"
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Main execution
main() {
    log_info "Starting operation..."
    
    # Your script logic here
    
    log_info "Operation completed successfully"
}

# Run main function
main

exit 0
```

### Boas Práticas Shell Scripts

#### Error Handling
```bash
# Use set flags para error handling robusto
set -euo pipefail

# Ou capture errors manualmente
if ! command_that_might_fail; then
    log_error "Command failed"
    exit 1
fi

# Usar trap para cleanup
cleanup() {
    log_info "Cleaning up..."
    rm -f "$TEMP_FILE"
}
trap cleanup EXIT
```

#### Validação de Comandos
```bash
# Verificar se comandos necessários estão disponíveis
require_command() {
    if ! command -v "$1" &> /dev/null; then
        log_error "Required command not found: $1"
        exit 1
    fi
}

require_command "curl"
require_command "jq"
require_command "docker"
```

#### Backups Seguros
```bash
# Sempre fazer backup antes de modificar arquivos importantes
backup_file() {
    local file=$1
    local backup="${file}.backup.$(date +%Y%m%d_%H%M%S)"
    
    if [ -f "$file" ]; then
        cp "$file" "$backup"
        log_info "Backed up $file to $backup"
    fi
}

backup_file "/important/config/file.conf"
```

#### Confirmação para Operações Destrutivas
```bash
confirm_operation() {
    if [ "$FORCE" = false ]; then
        read -p "This will delete data. Continue? (yes/no): " -r
        if [ "$REPLY" != "yes" ]; then
            log_info "Operation cancelled"
            exit 0
        fi
    fi
}

# Antes de operação destrutiva
confirm_operation
rm -rf /path/to/data
```

## Scripts Específicos

### Migration Runner (run_migrations.php)
```php
// Tracking de migrations executadas
function hasRunMigration(Database $db, string $filename): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM migrations WHERE filename = :filename"
    );
    $stmt->execute([':filename' => $filename]);
    return $stmt->fetchColumn() > 0;
}

// Executar migration com transação
function runMigration(Database $db, string $file): void
{
    $db->beginTransaction();
    try {
        $sql = file_get_contents($file);
        $db->exec($sql);
        
        // Registrar migration
        $stmt = $db->prepare(
            "INSERT INTO migrations (filename, executed_at) VALUES (:filename, :executed_at)"
        );
        $stmt->execute([
            ':filename' => basename($file),
            ':executed_at' => date('Y-m-d H:i:s')
        ]);
        
        $db->commit();
        echo "✓ Migration executed: " . basename($file) . "\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception("Migration failed: " . $e->getMessage());
    }
}
```

### Background Worker (worker.php)
```php
// Loop principal do worker
function runWorker(Database $db): void
{
    $running = true;
    
    // Capturar SIGTERM para graceful shutdown
    pcntl_signal(SIGTERM, function() use (&$running) {
        echo "Received SIGTERM, shutting down gracefully...\n";
        $running = false;
    });
    
    echo "Worker started at " . date('Y-m-d H:i:s') . "\n";
    
    while ($running) {
        try {
            // Processar jobs
            $job = fetchNextJob($db);
            
            if ($job) {
                processJob($db, $job);
            } else {
                // Nenhum job, aguardar
                sleep(5);
            }
            
            // Despachar sinais
            pcntl_signal_dispatch();
            
        } catch (Exception $e) {
            error_log("Worker error: " . $e->getMessage());
            sleep(10); // Aguardar antes de tentar novamente
        }
    }
    
    echo "Worker stopped at " . date('Y-m-d H:i:s') . "\n";
}
```

### Backup Scripts (db_backup.sh)
```bash
#!/bin/bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-./backups}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/backup_${TIMESTAMP}.sql.gz"

# Criar diretório de backup
mkdir -p "$BACKUP_DIR"

log_info "Starting database backup..."

# Backup baseado no tipo de database
if [ "$DB_TYPE" = "mysql" ]; then
    mysqldump \
        -h "$DB_HOST" \
        -u "$DB_USER" \
        -p"$DB_PASSWORD" \
        "$DB_NAME" | gzip > "$BACKUP_FILE"
elif [ "$DB_TYPE" = "sqlite" ]; then
    sqlite3 "$DB_PATH" ".backup '$BACKUP_FILE.tmp'"
    gzip "$BACKUP_FILE.tmp"
    mv "$BACKUP_FILE.tmp.gz" "$BACKUP_FILE"
else
    log_error "Unsupported database type: $DB_TYPE"
    exit 1
fi

# Verificar tamanho do backup
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
log_info "Backup created: $BACKUP_FILE ($BACKUP_SIZE)"

# Limpar backups antigos (manter últimos 7 dias)
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +7 -delete
log_info "Old backups cleaned up"

exit 0
```

### Smoke Test (smoke_test.sh)
```bash
#!/bin/bash
set -euo pipefail

FAILURES=0

run_test() {
    local test_name=$1
    local test_command=$2
    
    echo -n "Testing: $test_name... "
    
    if eval "$test_command" &> /dev/null; then
        echo -e "${GREEN}PASS${NC}"
    else
        echo -e "${RED}FAIL${NC}"
        FAILURES=$((FAILURES + 1))
    fi
}

log_info "Running smoke tests..."

# Test 1: Config file exists
run_test "Config file" "[ -f config.php ]"

# Test 2: Database connection
run_test "Database connection" "php -r \"require 'config.php'; require 'includes/DB.php'; echo 'OK';\""

# Test 3: OpenAI API key configured
run_test "OpenAI API key" "[ ! -z \"\$OPENAI_API_KEY\" ]"

# Test 4: Web server responding
run_test "Web server" "curl -s http://localhost:8088/ > /dev/null"

# Test 5: Admin API accessible
run_test "Admin API" "curl -s http://localhost:8088/admin-api.php > /dev/null"

# Summary
echo ""
if [ $FAILURES -eq 0 ]; then
    log_info "All smoke tests passed!"
    exit 0
else
    log_error "$FAILURES test(s) failed"
    exit 1
fi
```

## Agendamento de Scripts

### Cron Jobs
```bash
# Exemplo de crontab
# Backup diário às 2am
0 2 * * * /path/to/scripts/db_backup.sh >> /var/log/backup.log 2>&1

# Worker deve rodar continuamente (usar systemd ao invés de cron)
# Cleanup de dados antigos semanalmente
0 3 * * 0 /usr/bin/php /path/to/scripts/cleanup_old_data.php >> /var/log/cleanup.log 2>&1

# Geração de faturas mensalmente
0 0 1 * * /usr/bin/php /path/to/scripts/generate_invoices.php >> /var/log/invoices.log 2>&1
```

### Systemd Services (para workers)
```ini
# /etc/systemd/system/chatbot-worker.service
[Unit]
Description=Chatbot Background Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/chatbot
ExecStart=/usr/bin/php /var/www/chatbot/scripts/worker.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

## Logging e Monitoramento

### Log Rotation
```bash
# /etc/logrotate.d/chatbot-scripts
/var/log/chatbot/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload chatbot-worker
    endscript
}
```

### Health Checks
```bash
# Script de health check para monitoring
#!/bin/bash
check_worker_health() {
    if ! pgrep -f "worker.php" > /dev/null; then
        log_error "Worker not running"
        return 1
    fi
    
    # Verificar se worker processou jobs recentemente
    local last_job=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
        -e "SELECT MAX(processed_at) FROM jobs WHERE status='completed'" -s)
    
    if [ -z "$last_job" ]; then
        log_warn "No jobs processed recently"
    fi
    
    return 0
}
```

## Segurança em Scripts

### Não Expor Credenciais
```bash
# ERRADO: Hardcoded credentials
mysql -u root -p'password123' mydatabase

# CORRETO: Usar variáveis de ambiente
mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME"

# CORRETO: Usar arquivo de configuração seguro
mysql --defaults-file=/secure/path/.my.cnf mydatabase
```

### Validar Input
```php
// Em scripts PHP que recebem argumentos
$agentId = $argv[1] ?? null;

if (!is_numeric($agentId) || $agentId <= 0) {
    echo "ERROR: Invalid agent_id\n";
    exit(1);
}

$agentId = (int)$agentId;
```

### Permissões Apropriadas
```bash
# Scripts devem ter permissões restritivas
chmod 750 scripts/backup.sh

# Arquivos de configuração sensíveis
chmod 600 .env

# Diretórios de backup
chmod 700 /var/backups/chatbot/
```

## Testing de Scripts

### Dry Run Mode
Sempre implementar modo dry-run para testar sem efeitos colaterais:
```bash
./script.sh --dry-run
```

### Unit Tests para Scripts PHP
```php
// tests/test_script_functions.php
require_once __DIR__ . '/../scripts/example_script.php';

// Testar função específica
$result = performOperation($mockDb, true);
if ($result['success']) {
    echo "✓ PASS: Function works correctly\n";
}
```

## Checklist de Revisão

Antes de adicionar/modificar scripts:

- [ ] Script tem header com descrição e usage
- [ ] Validação de pré-condições implementada
- [ ] Error handling robusto (try/catch, set -e)
- [ ] Logging apropriado (timestamps, níveis)
- [ ] Exit codes corretos (0 sucesso, não-zero erro)
- [ ] Modo dry-run quando aplicável
- [ ] Confirmação para operações destrutivas
- [ ] Credenciais não hardcoded
- [ ] Permissões de arquivo apropriadas
- [ ] Testado localmente antes de commit
- [ ] Documentação/comentários para lógica complexa
- [ ] Idempotente quando possível
