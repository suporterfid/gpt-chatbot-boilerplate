#!/usr/bin/env php
<?php
/**
 * Migrate legacy ADMIN_TOKEN authentication to named super-admin credentials.
 *
 * This script will:
 *  - Load environment variables from .env
 *  - Ensure a target super-admin email exists (creating or upgrading as needed)
 *  - Generate a secure temporary password for that user
 *  - Remove the legacy ADMIN_TOKEN from the environment file (after backup)
 *  - Provide migration guidance and log every step for auditing
 *
 * Usage:
 *   php scripts/migrate_admin_token.php [--email=admin@example.com] [--yes] [--force] [--non-interactive]
 *        [--log-file=/path/to/log] [--backup=/path/to/backup.env]
 *
 * Options:
 *   --email             Target email for the super-admin account
 *   --yes               Skip interactive confirmations (assumes "yes" to prompts)
 *   --force             Continue even if ADMIN_TOKEN is missing or empty
 *   --non-interactive   Fail instead of prompting when input is required (use with --yes/--email)
 *   --log-file          Custom log file path (defaults to logs/admin_token_migration.log)
 *   --backup            Override .env backup destination. If a directory is provided it will create a timestamped file.
 */

ini_set('display_errors', 'stderr');

$rootDir = realpath(__DIR__ . '/..');
$envPath = $rootDir . '/.env';

if (!is_dir($rootDir)) {
    fwrite(STDERR, "Unable to determine project root directory.\n");
    exit(1);
}

require_once $rootDir . '/config.php';
require_once $rootDir . '/includes/DB.php';
require_once $rootDir . '/includes/AdminAuth.php';

$options = getopt('', [
    'email::',
    'yes',
    'force',
    'non-interactive',
    'log-file::',
    'backup::'
]);

$interactive = !isset($options['non-interactive']);
$autoConfirm = isset($options['yes']);
$force = isset($options['force']);
$targetEmail = isset($options['email']) ? trim($options['email']) : null;
$logFile = $options['log-file'] ?? ($rootDir . '/logs/admin_token_migration.log');

if (!is_dir(dirname($logFile))) {
    if (!mkdir(dirname($logFile), 0755, true) && !is_dir(dirname($logFile))) {
        fwrite(STDERR, "Failed to create log directory: " . dirname($logFile) . "\n");
        exit(1);
    }
}

$logHandle = fopen($logFile, 'ab');
if (!$logHandle) {
    fwrite(STDERR, "Unable to open log file: {$logFile}\n");
    exit(1);
}

function logMessage($level, $message)
{
    global $logHandle;
    $timestamp = date('c');
    $line = sprintf('[%s] [%s] %s%s', $timestamp, strtoupper($level), $message, PHP_EOL);
    fwrite(STDOUT, '[' . strtoupper($level) . "] " . $message . PHP_EOL);
    if ($logHandle) {
        fwrite($logHandle, $line);
        fflush($logHandle);
    }
}

register_shutdown_function(function () use ($logHandle) {
    if ($logHandle) {
        fclose($logHandle);
    }
});

function startsWith($haystack, $needle)
{
    if (function_exists('str_starts_with')) {
        return str_starts_with($haystack, $needle);
    }

    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

function prompt($message)
{
    if (function_exists('readline')) {
        $input = readline($message);
        if ($input !== false) {
            return $input;
        }
    }

    fwrite(STDOUT, $message);
    $input = fgets(STDIN);
    return $input === false ? '' : trim($input);
}

function loadEnvFile($path)
{
    if (!file_exists($path)) {
        return [];
    }

    $lines = file($path);
    if ($lines === false) {
        throw new RuntimeException('Failed to read .env file.');
    }

    return $lines;
}

function extractEnvEntries(array $lines)
{
    $entries = [];
    foreach ($lines as $idx => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || startsWith($trimmed, '#')) {
            continue;
        }

        $delimiterPos = strpos($line, '=');
        if ($delimiterPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $delimiterPos));
        $value = substr($line, $delimiterPos + 1);
        $entries[$key] = [
            'index' => $idx,
            'value' => rtrim($value, "\r\n"),
        ];
    }

    return $entries;
}

try {
    $envLines = loadEnvFile($envPath);
} catch (RuntimeException $e) {
    logMessage('error', $e->getMessage());
    exit(1);
}

$envEntries = extractEnvEntries($envLines);
$adminToken = $envEntries['ADMIN_TOKEN']['value'] ?? null;

if (!$adminToken) {
    $message = 'No ADMIN_TOKEN value detected in .env.';
    if (!$force) {
        logMessage('error', $message . ' Re-run with --force to continue without a legacy token.');
        exit(1);
    }
    logMessage('warning', $message . ' Continuing because --force was supplied.');
}

if (!$targetEmail && $interactive) {
    $targetEmail = trim(prompt('Enter the email address for the super-admin: '));
}

if (!$targetEmail) {
    logMessage('error', 'A target email is required. Provide --email=address or run interactively.');
    exit(1);
}

if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
    logMessage('error', 'The provided email address is invalid: ' . $targetEmail);
    exit(1);
}

$maskedToken = $adminToken ? substr($adminToken, 0, 4) . str_repeat('*', max(0, strlen($adminToken) - 4)) : '(none)';

logMessage('info', 'Preparing to migrate legacy ADMIN_TOKEN based authentication.');
logMessage('info', 'Legacy ADMIN_TOKEN (masked): ' . $maskedToken);
logMessage('info', 'Target super-admin email: ' . $targetEmail);

if (!$autoConfirm) {
    if (!$interactive) {
        logMessage('error', 'Interactive confirmation required. Re-run with --yes to proceed in non-interactive mode.');
        exit(1);
    }

    $response = strtolower(trim(prompt('Proceed with migration? [y/N]: ')));
    if ($response !== 'y' && $response !== 'yes') {
        logMessage('info', 'Migration aborted by user.');
        exit(0);
    }
}

$backupTarget = $options['backup'] ?? null;
if ($backupTarget) {
    if (is_dir($backupTarget)) {
        $backupPath = rtrim($backupTarget, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env.backup-' . date('Ymd-His');
    } else {
        $backupPath = $backupTarget;
    }
} else {
    $backupPath = $rootDir . '/backups/.env.backup-' . date('Ymd-His');
}

if (!is_dir(dirname($backupPath))) {
    if (!mkdir(dirname($backupPath), 0755, true) && !is_dir(dirname($backupPath))) {
        logMessage('error', 'Failed to create backup directory: ' . dirname($backupPath));
        exit(1);
    }
}

if (file_exists($envPath)) {
    if (!copy($envPath, $backupPath)) {
        logMessage('error', 'Unable to create .env backup at ' . $backupPath);
        exit(1);
    }
    logMessage('info', 'Created .env backup at ' . $backupPath);
} else {
    logMessage('warning', '.env file not found. Continuing without backup.');
}

$dbConfig = [
    'database_url' => $config['admin']['database_url'] ?? '',
    'database_path' => $config['admin']['database_path'] ?? ($rootDir . '/data/chatbot.db'),
];

try {
    $db = new DB($dbConfig);
    $adminAuth = new AdminAuth($db, $config);
} catch (Exception $e) {
    logMessage('error', 'Failed to initialize AdminAuth: ' . $e->getMessage());
    exit(1);
}

try {
    $existingUser = $adminAuth->getUserByEmail($targetEmail);
} catch (Exception $e) {
    logMessage('error', 'Unable to query admin user: ' . $e->getMessage());
    exit(1);
}

function generateTemporaryPassword($length = 20)
{
    $bytes = random_bytes(32);
    $password = rtrim(strtr(base64_encode($bytes), '+/', 'Aa'), '=');
    if (strlen($password) < $length) {
        $password .= bin2hex(random_bytes($length));
    }
    return substr($password, 0, $length);
}

try {
    $tempPassword = generateTemporaryPassword();
} catch (Exception $e) {
    logMessage('error', 'Failed to generate temporary password: ' . $e->getMessage());
    exit(1);
}

try {
    if ($existingUser) {
        logMessage('info', 'Existing admin user located for ' . $targetEmail . '.');
        if ($existingUser['role'] !== AdminAuth::ROLE_SUPER_ADMIN) {
            $adminAuth->updateUserRole($existingUser['id'], AdminAuth::ROLE_SUPER_ADMIN);
            logMessage('info', 'Upgraded user role to super-admin.');
        }

        if (!(bool)$existingUser['is_active']) {
            $adminAuth->activateUser($existingUser['id']);
            logMessage('info', 'Reactivated existing user.');
        }

        $adminAuth->updateUserPassword($existingUser['id'], $tempPassword);
        logMessage('info', 'Assigned temporary password to existing user.');
        $superAdmin = $adminAuth->getUser($existingUser['id']);
    } else {
        logMessage('info', 'No admin user found for ' . $targetEmail . '. Creating a new super-admin user.');
        $superAdmin = $adminAuth->createUser($targetEmail, $tempPassword, AdminAuth::ROLE_SUPER_ADMIN);
        logMessage('info', 'Created new super-admin user.');
    }
} catch (Exception $e) {
    logMessage('error', 'Failed to ensure super-admin account: ' . $e->getMessage());
    exit(1);
}

$updatedLines = $envLines;
if (isset($envEntries['ADMIN_TOKEN'])) {
    $index = $envEntries['ADMIN_TOKEN']['index'];
    $updatedLines[$index] = "ADMIN_TOKEN=\n";
} else {
    $updatedLines[] = "ADMIN_TOKEN=\n";
}

$updatedLines[] = "# ADMIN_TOKEN migrated on " . date('c') . " by scripts/migrate_admin_token.php\n";

if (file_put_contents($envPath, $updatedLines) === false) {
    logMessage('error', 'Failed to write updated .env file.');
    exit(1);
}

logMessage('info', 'Cleared ADMIN_TOKEN from .env.');

$guidance = [
    'Super-admin email: ' . $targetEmail,
    'Temporary password: ' . $tempPassword,
    'Login URL: /public/admin/ (update if served from a subdirectory)',
    'Next steps:',
    '  1. Sign in using the temporary credentials and immediately change the password.',
    '  2. Create named API keys for operators that previously relied on the shared ADMIN_TOKEN.',
    '  3. Distribute new credentials securely and remove any copies of the temporary password after first use.',
    '  4. Update automation scripts to use per-user API keys instead of the legacy token.',
];

logMessage('info', str_repeat('-', 60));
foreach ($guidance as $line) {
    logMessage('info', $line);
}
logMessage('info', str_repeat('-', 60));

logMessage('info', 'Migration completed successfully. Review the log for audit details: ' . $logFile);

exit(0);
