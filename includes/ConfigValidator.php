<?php
/**
 * Configuration Validator
 * 
 * Validates required configuration keys and their formats to ensure
 * the application can start safely with proper configuration.
 * 
 * @package GPT_Chatbot
 */

class ConfigValidator
{
    /**
     * Required configuration keys and their descriptions
     * 
     * @var array<string, string>
     */
    private array $requiredKeys = [
        'openai.api_key' => 'OPENAI_API_KEY environment variable',
        'openai.base_url' => 'OPENAI_BASE_URL environment variable',
    ];
    
    /**
     * Validation errors
     * 
     * @var array<string>
     */
    private array $errors = [];
    
    /**
     * Validate configuration array
     * 
     * @param array $config Configuration array to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(array $config): bool
    {
        $this->errors = [];
        
        // Check required keys exist and are not empty
        foreach ($this->requiredKeys as $key => $description) {
            if (!$this->hasKey($config, $key)) {
                $this->errors[] = "Missing required configuration: $description";
                continue;
            }
            
            $value = $this->getKey($config, $key);
            if (empty($value)) {
                $this->errors[] = "Empty value for required configuration: $description";
            }
        }
        
        // Validate specific formats
        $this->validateApiKey($config);
        $this->validateUrl($config['openai']['base_url'] ?? null, 'OpenAI Base URL');
        
        // Validate paths if they exist
        if (isset($config['admin']['database_path'])) {
            $this->validatePath($config['admin']['database_path'], 'Database path');
        }
        
        if (isset($config['storage']['path']) && $config['storage']['type'] === 'file') {
            $this->validatePath($config['storage']['path'], 'Storage path', true);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     * 
     * @return array<string> Array of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Validate API key format without exposing the key
     * 
     * @param array $config Configuration array
     * @return void
     */
    private function validateApiKey(array $config): void
    {
        $apiKey = $config['openai']['api_key'] ?? null;
        
        if (!$apiKey) {
            return; // Already reported as missing
        }
        
        // Validate format without exposing the key
        // OpenAI API keys start with 'sk-' and are at least 40 characters
        if (!preg_match('/^sk-[a-zA-Z0-9_-]{32,}$/', $apiKey)) {
            $this->errors[] = "OPENAI_API_KEY has invalid format (must start with 'sk-' and be at least 40 characters)";
        }
    }
    
    /**
     * Validate URL format and security
     * 
     * @param string|null $url URL to validate
     * @param string $name Descriptive name for error messages
     * @return void
     */
    private function validateUrl(?string $url, string $name): void
    {
        if (!$url) {
            return;
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->errors[] = "$name must be a valid URL";
            return;
        }
        
        $parsedUrl = parse_url($url);
        
        // Ensure HTTPS in production
        if (isset($parsedUrl['scheme']) && $parsedUrl['scheme'] !== 'https') {
            // Allow http only for localhost/development
            $host = $parsedUrl['host'] ?? '';
            if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                $this->errors[] = "$name must use HTTPS in production (current: {$parsedUrl['scheme']}://$host)";
            }
        }
    }
    
    /**
     * Validate file path exists and is writable
     * 
     * @param string|null $path Path to validate
     * @param string $name Descriptive name for error messages
     * @param bool $mustExist Whether the path must already exist
     * @return void
     */
    private function validatePath(?string $path, string $name, bool $mustExist = false): void
    {
        if (!$path) {
            return;
        }
        
        $dir = is_dir($path) ? $path : dirname($path);
        
        if ($mustExist && !is_dir($dir)) {
            $this->errors[] = "$name directory does not exist: $dir";
            return;
        }
        
        // Check parent directory exists
        $parentDir = dirname($dir);
        if (!is_dir($parentDir)) {
            $this->errors[] = "$name parent directory does not exist: $parentDir";
            return;
        }
        
        // Check writability (on parent if directory doesn't exist yet)
        $checkDir = is_dir($dir) ? $dir : $parentDir;
        if (!is_writable($checkDir)) {
            $this->errors[] = "$name directory is not writable: $checkDir";
        }
    }
    
    /**
     * Check if a nested key exists in array using dot notation
     * 
     * @param array $config Configuration array
     * @param string $key Dot-notation key (e.g., 'openai.api_key')
     * @return bool True if key exists
     */
    private function hasKey(array $config, string $key): bool
    {
        $keys = explode('.', $key);
        $current = $config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return false;
            }
            $current = $current[$k];
        }
        
        return true;
    }
    
    /**
     * Get value from nested array using dot notation
     * 
     * @param array $config Configuration array
     * @param string $key Dot-notation key (e.g., 'openai.api_key')
     * @return mixed Value at the key or null if not found
     */
    private function getKey(array $config, string $key)
    {
        $keys = explode('.', $key);
        $current = $config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return null;
            }
            $current = $current[$k];
        }
        
        return $current;
    }
}
