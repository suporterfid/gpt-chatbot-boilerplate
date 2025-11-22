<?php
/**
 * Blog Credential Manager
 *
 * Manages encryption, decryption, and validation of API credentials
 * for WordPress Blog services with audit logging.
 *
 * Integrates with existing SecretsManager and CryptoAdapter for
 * secure credential management.
 *
 * @package WordPressBlog\Security
 */

require_once __DIR__ . '/../../SecretsManager.php';
require_once __DIR__ . '/../../CryptoAdapter.php';
require_once __DIR__ . '/../Exceptions/CredentialException.php';

class BlogCredentialManager {
    private $cryptoAdapter;
    private $db;
    private $auditLog = [];

    /**
     * Constructor
     *
     * @param CryptoAdapter $cryptoAdapter Encryption adapter
     * @param DB|null $db Database instance for audit logging
     */
    public function __construct(CryptoAdapter $cryptoAdapter, $db = null) {
        $this->cryptoAdapter = $cryptoAdapter;
        $this->db = $db;
    }

    /**
     * Encrypt API credential
     *
     * @param string $credential Plaintext credential
     * @param string $credentialType Type of credential (for audit logging)
     * @return array Encrypted data with ciphertext, nonce, tag
     * @throws CredentialException If encryption fails
     */
    public function encryptCredential($credential, $credentialType = 'unknown') {
        if (empty($credential)) {
            throw (new CredentialException('Credential cannot be empty'))
                ->setCredentialType($credentialType);
        }

        try {
            $encrypted = $this->cryptoAdapter->encrypt($credential);

            $this->logAudit('encrypt', $credentialType, true);

            return $encrypted;

        } catch (Exception $e) {
            $this->logAudit('encrypt', $credentialType, false, $e->getMessage());

            throw (new CredentialException('Failed to encrypt credential: ' . $e->getMessage()))
                ->setCredentialType($credentialType)
                ->addContext('original_error', $e->getMessage());
        }
    }

    /**
     * Decrypt API credential
     *
     * @param string $ciphertext Encrypted credential
     * @param string $nonce Encryption nonce
     * @param string $tag Authentication tag
     * @param string $credentialType Type of credential (for audit logging)
     * @return string Decrypted plaintext credential
     * @throws CredentialException If decryption fails
     */
    public function decryptCredential($ciphertext, $nonce, $tag, $credentialType = 'unknown') {
        if (empty($ciphertext) || empty($nonce) || empty($tag)) {
            throw (new CredentialException('Missing encryption data'))
                ->setCredentialType($credentialType);
        }

        try {
            $decrypted = $this->cryptoAdapter->decrypt($ciphertext, $nonce, $tag);

            $this->logAudit('decrypt', $credentialType, true);

            return $decrypted;

        } catch (Exception $e) {
            $this->logAudit('decrypt', $credentialType, false, $e->getMessage());

            throw (new CredentialException('Failed to decrypt credential: ' . $e->getMessage()))
                ->setCredentialType($credentialType)
                ->addContext('original_error', $e->getMessage());
        }
    }

    /**
     * Encrypt batch of credentials
     *
     * @param array $credentials Associative array of credential_type => plaintext
     * @return array Encrypted credentials with metadata
     * @throws CredentialException If any encryption fails
     */
    public function encryptBatch(array $credentials) {
        $encrypted = [];

        foreach ($credentials as $type => $value) {
            if (!empty($value)) {
                $encrypted[$type] = $this->encryptCredential($value, $type);
            }
        }

        return $encrypted;
    }

    /**
     * Decrypt batch of credentials
     *
     * @param array $credentials Associative array of credential data
     * @return array Decrypted credentials
     * @throws CredentialException If any decryption fails
     */
    public function decryptBatch(array $credentials) {
        $decrypted = [];

        foreach ($credentials as $type => $data) {
            if (is_array($data) && isset($data['ciphertext'], $data['nonce'], $data['tag'])) {
                $decrypted[$type] = $this->decryptCredential(
                    $data['ciphertext'],
                    $data['nonce'],
                    $data['tag'],
                    $type
                );
            }
        }

        return $decrypted;
    }

    /**
     * Validate credential format
     *
     * @param string $credential Credential to validate
     * @param string $credentialType Type of credential
     * @return array Validation result with valid flag and message
     */
    public function validateCredential($credential, $credentialType) {
        if (empty($credential)) {
            return [
                'valid' => false,
                'message' => 'Credential is empty'
            ];
        }

        switch ($credentialType) {
            case 'openai_api_key':
                return $this->validateOpenAIKey($credential);

            case 'wordpress_api_key':
                return $this->validateWordPressKey($credential);

            case 'google_drive_api_key':
                return $this->validateGoogleDriveKey($credential);

            default:
                return [
                    'valid' => true,
                    'message' => 'No specific validation for this credential type'
                ];
        }
    }

    /**
     * Rotate encryption key for credentials
     *
     * Note: This requires decrypting with old key and re-encrypting with new key.
     * Should be called when changing encryption keys.
     *
     * @param array $encryptedData Old encrypted data
     * @param CryptoAdapter $newCryptoAdapter New crypto adapter with new key
     * @param string $credentialType Credential type
     * @return array New encrypted data
     * @throws CredentialException If rotation fails
     */
    public function rotateCredential(array $encryptedData, CryptoAdapter $newCryptoAdapter, $credentialType) {
        try {
            // Decrypt with current key
            $plaintext = $this->decryptCredential(
                $encryptedData['ciphertext'],
                $encryptedData['nonce'],
                $encryptedData['tag'],
                $credentialType
            );

            // Encrypt with new key
            $newEncrypted = $newCryptoAdapter->encrypt($plaintext);

            $this->logAudit('rotate', $credentialType, true);

            return $newEncrypted;

        } catch (Exception $e) {
            $this->logAudit('rotate', $credentialType, false, $e->getMessage());

            throw (new CredentialException('Failed to rotate credential: ' . $e->getMessage()))
                ->setCredentialType($credentialType);
        }
    }

    /**
     * Mask credential for safe display
     *
     * Shows only first 4 and last 4 characters
     *
     * @param string $credential Credential to mask
     * @return string Masked credential
     */
    public function maskCredential($credential) {
        if (empty($credential)) {
            return '';
        }

        $length = strlen($credential);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($credential, 0, 4) . str_repeat('*', $length - 8) . substr($credential, -4);
    }

    /**
     * Get audit log
     *
     * @return array Audit log entries
     */
    public function getAuditLog() {
        return $this->auditLog;
    }

    /**
     * Clear audit log
     *
     * @return void
     */
    public function clearAuditLog() {
        $this->auditLog = [];
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    /**
     * Validate OpenAI API key format
     *
     * @param string $key API key
     * @return array Validation result
     */
    private function validateOpenAIKey($key) {
        // OpenAI keys: sk-proj-... or sk-...
        if (preg_match('/^sk-proj-[a-zA-Z0-9_-]+$/', $key)) {
            return ['valid' => true, 'message' => 'Valid OpenAI project key format'];
        }

        if (preg_match('/^sk-[a-zA-Z0-9]{48}$/', $key)) {
            return ['valid' => true, 'message' => 'Valid OpenAI key format'];
        }

        return [
            'valid' => false,
            'message' => 'Invalid OpenAI API key format (expected sk-... or sk-proj-...)'
        ];
    }

    /**
     * Validate WordPress API key format
     *
     * @param string $key API key (application password)
     * @return array Validation result
     */
    private function validateWordPressKey($key) {
        // WordPress application passwords: username:password format
        $parts = explode(':', $key, 2);

        if (count($parts) !== 2) {
            return [
                'valid' => false,
                'message' => 'Invalid WordPress API key format (expected username:password)'
            ];
        }

        list($username, $password) = $parts;

        if (empty($username) || empty($password)) {
            return [
                'valid' => false,
                'message' => 'WordPress API key has empty username or password'
            ];
        }

        if (strlen($password) < 20) {
            return [
                'valid' => false,
                'message' => 'WordPress application password is too short'
            ];
        }

        return ['valid' => true, 'message' => 'Valid WordPress API key format'];
    }

    /**
     * Validate Google Drive API key format
     *
     * @param string $key API key
     * @return array Validation result
     */
    private function validateGoogleDriveKey($key) {
        // Google API keys are typically 39 characters
        if (strlen($key) < 30) {
            return [
                'valid' => false,
                'message' => 'Google Drive API key is too short'
            ];
        }

        return ['valid' => true, 'message' => 'Valid Google Drive API key format'];
    }

    /**
     * Log audit entry
     *
     * @param string $operation Operation type (encrypt/decrypt/rotate)
     * @param string $credentialType Credential type
     * @param bool $success Success flag
     * @param string|null $errorMessage Error message if failed
     * @return void
     */
    private function logAudit($operation, $credentialType, $success, $errorMessage = null) {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'credential_type' => $credentialType,
            'success' => $success
        ];

        if (!$success && $errorMessage) {
            $entry['error'] = $errorMessage;
        }

        $this->auditLog[] = $entry;

        // Persist to database if available
        if ($this->db) {
            try {
                $this->persistAuditLog($entry);
            } catch (Exception $e) {
                // Don't fail operation if audit logging fails
                error_log('Failed to persist audit log: ' . $e->getMessage());
            }
        }
    }

    /**
     * Persist audit log to database
     *
     * @param array $entry Audit log entry
     * @return void
     */
    private function persistAuditLog(array $entry) {
        // Check if audit table exists
        $tableExists = $this->db->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='credential_audit_log'"
        )->fetch();

        if (!$tableExists) {
            // Create audit table
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS credential_audit_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp TEXT NOT NULL,
                    operation TEXT NOT NULL,
                    credential_type TEXT NOT NULL,
                    success INTEGER NOT NULL,
                    error TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }

        // Insert audit entry
        $this->db->execute(
            "INSERT INTO credential_audit_log (timestamp, operation, credential_type, success, error)
             VALUES (?, ?, ?, ?, ?)",
            [
                $entry['timestamp'],
                $entry['operation'],
                $entry['credential_type'],
                $entry['success'] ? 1 : 0,
                $entry['error'] ?? null
            ]
        );
    }
}
