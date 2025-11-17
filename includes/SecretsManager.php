<?php
/**
 * Secrets Manager
 * 
 * Centralized management of sensitive configuration values like API keys,
 * passwords, and tokens. Provides secure access and logging redaction.
 * 
 * @package GPT_Chatbot
 */

class SecretsManager
{
    /**
     * Loaded secrets
     * 
     * @var array<string, mixed>
     */
    private array $secrets = [];
    
    /**
     * Whether secrets have been loaded
     * 
     * @var bool
     */
    private bool $loaded = false;
    
    /**
     * Source of secrets (env, aws-secrets-manager, vault)
     * 
     * @var string
     */
    private string $secretsSource;
    
    /**
     * Constructor
     * 
     * @param string $secretsSource Source to load secrets from
     */
    public function __construct(string $secretsSource = 'env')
    {
        $this->secretsSource = $secretsSource;
        $this->loadSecrets();
    }
    
    /**
     * Get secret by key
     * 
     * @param string $key Secret key (e.g., 'openai.api_key')
     * @param mixed $default Default value if not found
     * @return mixed Secret value
     * @throws RuntimeException If secrets not loaded
     */
    public function get(string $key, $default = null)
    {
        if (!$this->loaded) {
            throw new RuntimeException('Secrets not loaded');
        }
        
        return $this->secrets[$key] ?? $default;
    }
    
    /**
     * Set secret (for testing or runtime updates)
     * 
     * @param string $key Secret key
     * @param mixed $value Secret value
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->secrets[$key] = $value;
    }
    
    /**
     * Check if secret exists
     * 
     * @param string $key Secret key
     * @return bool True if secret exists
     */
    public function has(string $key): bool
    {
        return isset($this->secrets[$key]);
    }
    
    /**
     * Reload secrets (for rotation)
     * 
     * @return void
     */
    public function reload(): void
    {
        $this->loadSecrets();
    }
    
    /**
     * Get redacted secret for logging
     * Shows first 4 and last 4 characters, rest as asterisks
     * 
     * @param string $key Secret key
     * @return string Redacted secret value
     */
    public function getRedacted(string $key): string
    {
        $secret = $this->get($key);
        
        if (!$secret || !is_string($secret)) {
            return '[not set]';
        }
        
        $len = strlen($secret);
        
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        
        // Show first 4 and last 4 characters
        return substr($secret, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($secret, -4);
    }
    
    /**
     * Get all secret keys (not values!)
     * 
     * @return array<string> Array of secret keys
     */
    public function getKeys(): array
    {
        return array_keys($this->secrets);
    }
    
    /**
     * Load secrets from configured source
     * 
     * @return void
     * @throws RuntimeException If unknown secrets source
     */
    private function loadSecrets(): void
    {
        switch ($this->secretsSource) {
            case 'env':
                $this->loadFromEnv();
                break;
                
            case 'aws-secrets-manager':
                $this->loadFromAWS();
                break;
                
            case 'vault':
                $this->loadFromVault();
                break;
                
            default:
                throw new RuntimeException("Unknown secrets source: {$this->secretsSource}");
        }
        
        $this->loaded = true;
    }
    
    /**
     * Load from environment variables
     * 
     * @return void
     */
    private function loadFromEnv(): void
    {
        // Use getEnvValue if available (from config.php)
        $getValue = function_exists('getEnvValue') 
            ? 'getEnvValue' 
            : fn($key) => getenv($key) ?: null;
        
        $this->secrets = [
            'openai.api_key' => $getValue('OPENAI_API_KEY'),
            'openai.organization' => $getValue('OPENAI_ORGANIZATION'),
            'admin.token' => $getValue('ADMIN_TOKEN'),
            'database.password' => $getValue('DB_PASSWORD'),
            'jwt.secret' => $getValue('JWT_SECRET'),
            'asaas.api_key' => $getValue('ASAAS_API_KEY'),
        ];
    }
    
    /**
     * Load from AWS Secrets Manager
     * 
     * @return void
     * @throws RuntimeException Not implemented
     */
    private function loadFromAWS(): void
    {
        // Implement AWS Secrets Manager integration
        // Requires: composer require aws/aws-sdk-php
        
        /*
        $client = new SecretsManagerClient([
            'region' => getenv('AWS_REGION') ?: 'us-east-1',
            'version' => 'latest'
        ]);
        
        $secretName = getenv('AWS_SECRET_NAME');
        $result = $client->getSecretValue(['SecretId' => $secretName]);
        $secrets = json_decode($result['SecretString'], true);
        
        $this->secrets = $secrets;
        */
        
        throw new RuntimeException('AWS Secrets Manager not implemented. Please configure SECRETS_SOURCE=env or implement AWS integration.');
    }
    
    /**
     * Load from HashiCorp Vault
     * 
     * @return void
     * @throws RuntimeException Not implemented
     */
    private function loadFromVault(): void
    {
        // Implement Vault integration
        // Requires: composer require vault-php/vault-php
        
        throw new RuntimeException('Vault integration not implemented. Please configure SECRETS_SOURCE=env or implement Vault integration.');
    }
}
