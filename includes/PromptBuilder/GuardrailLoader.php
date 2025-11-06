<?php
declare(strict_types=1);

/**
 * Guardrail Loader
 * Loads and manages guardrail template snippets from YAML files
 */

class GuardrailLoader
{
    private string $templatesPath;
    private array $cache = [];

    public function __construct(string $templatesPath)
    {
        $this->templatesPath = rtrim($templatesPath, '/');
        
        if (!is_dir($this->templatesPath)) {
            throw new Exception("Guardrails templates path does not exist: {$this->templatesPath}");
        }
    }

    /**
     * Load guardrail template snippets by keys.
     *
     * @param array $keys List of guardrail keys to load
     * @return array Map of key => ['title' => string, 'snippet' => string, 'meta' => array]
     */
    public function load(array $keys): array
    {
        $result = [];
        
        foreach ($keys as $key) {
            $template = $this->loadTemplate($key);
            if ($template !== null) {
                $result[$key] = $template;
            }
        }
        
        return $result;
    }

    /**
     * Return all available guardrail keys and metadata.
     * 
     * @return array List of all guardrails with their metadata
     */
    public function catalog(): array
    {
        $catalog = [];
        $files = glob($this->templatesPath . '/*.yaml');
        
        if ($files === false) {
            return [];
        }
        
        foreach ($files as $file) {
            $template = $this->loadTemplateFromFile($file);
            if ($template !== null && isset($template['key'])) {
                $catalog[] = [
                    'key' => $template['key'],
                    'title' => $template['title'] ?? $template['key'],
                    'description' => $template['meta']['description'] ?? '',
                    'mandatory' => $template['meta']['mandatory'] ?? false,
                    'priority' => $template['meta']['priority'] ?? 999,
                ];
            }
        }
        
        // Sort by priority
        usort($catalog, function($a, $b) {
            return ($a['priority'] ?? 999) <=> ($b['priority'] ?? 999);
        });
        
        return $catalog;
    }

    /**
     * Load a single template by key
     * 
     * @param string $key Guardrail key
     * @return array|null Template data or null if not found
     */
    private function loadTemplate(string $key): ?array
    {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $file = $this->templatesPath . '/' . $key . '.yaml';
        
        if (!file_exists($file)) {
            error_log("Guardrail template not found: {$key}");
            return null;
        }
        
        $template = $this->loadTemplateFromFile($file);
        
        // Cache the result
        if ($template !== null) {
            $this->cache[$key] = $template;
        }
        
        return $template;
    }

    /**
     * Load and parse a YAML template file
     * 
     * @param string $file Path to YAML file
     * @return array|null Parsed template or null on error
     */
    private function loadTemplateFromFile(string $file): ?array
    {
        $content = file_get_contents($file);
        
        if ($content === false) {
            error_log("Failed to read guardrail template file: {$file}");
            return null;
        }
        
        try {
            $data = $this->parseYaml($content);
            
            // Validate schema
            if (!$this->validateTemplate($data)) {
                error_log("Invalid guardrail template schema: {$file}");
                return null;
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error parsing guardrail template {$file}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Simple YAML parser (handles basic key-value and multiline strings)
     * Note: This is a minimal parser for our specific use case.
     * For complex YAML, consider using a library like symfony/yaml.
     * 
     * @param string $content YAML content
     * @return array Parsed data
     */
    private function parseYaml(string $content): array
    {
        $data = [];
        $lines = explode("\n", $content);
        $currentKey = null;
        $multilineValue = [];
        $inMultiline = false;
        $currentObject = null; // For nested objects like 'meta'
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (preg_match('/^\s*#/', $line) || trim($line) === '') {
                continue;
            }
            
            // Detect multiline string start (key: |)
            if (preg_match('/^(\w+):\s*\|/', $line, $matches)) {
                // Save previous multiline if any
                if ($inMultiline && $currentKey !== null) {
                    $data[$currentKey] = rtrim(implode("\n", $multilineValue));
                }
                
                $currentKey = $matches[1];
                $multilineValue = [];
                $inMultiline = true;
                $currentObject = null; // Exit any nested object
                continue;
            }
            
            // In multiline mode
            if ($inMultiline) {
                // Check for next top-level key (end of multiline)
                if (preg_match('/^(\w+):/', $line)) {
                    // Save multiline value
                    if ($currentKey !== null) {
                        $data[$currentKey] = rtrim(implode("\n", $multilineValue));
                    }
                    $inMultiline = false;
                    $multilineValue = [];
                    
                    // Fall through to process this line
                } else {
                    // Continue multiline
                    $multilineValue[] = $line;
                    continue;
                }
            }
            
            // Top-level key with value (key: value)
            if (preg_match('/^(\w+):\s*(.+)$/', $line, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);
                
                // Type conversion
                if ($value === 'true') $value = true;
                elseif ($value === 'false') $value = false;
                elseif (is_numeric($value)) $value = $value + 0;
                
                $data[$key] = $value;
                $currentObject = null;
                continue;
            }
            
            // Top-level key without value (starts nested object)
            if (preg_match('/^(\w+):\s*$/', $line, $matches)) {
                $key = $matches[1];
                $data[$key] = [];
                $currentObject = $key;
                continue;
            }
            
            // Nested property (  property: value)
            if (preg_match('/^\s{2,}(\w+):\s*(.*)$/', $line, $matches) && $currentObject !== null) {
                $property = $matches[1];
                $value = trim($matches[2]);
                
                // Type conversion
                if ($value === 'true') $value = true;
                elseif ($value === 'false') $value = false;
                elseif (is_numeric($value)) $value = $value + 0;
                
                if (!is_array($data[$currentObject])) {
                    $data[$currentObject] = [];
                }
                $data[$currentObject][$property] = $value;
            }
        }
        
        // Save final multiline if any
        if ($inMultiline && $currentKey !== null) {
            $data[$currentKey] = rtrim(implode("\n", $multilineValue));
        }
        
        return $data;
    }

    /**
     * Validate template schema
     * 
     * @param array $data Template data
     * @return bool True if valid
     */
    private function validateTemplate(array $data): bool
    {
        // Required fields
        if (!isset($data['key']) || !isset($data['snippet'])) {
            return false;
        }
        
        // Key must be non-empty string
        if (!is_string($data['key']) || trim($data['key']) === '') {
            return false;
        }
        
        // Snippet must be non-empty string
        if (!is_string($data['snippet']) || trim($data['snippet']) === '') {
            return false;
        }
        
        return true;
    }

    /**
     * Interpolate variables in template snippet
     * 
     * @param string $snippet Template snippet with {{variable}} placeholders
     * @param array $variables Key-value pairs for interpolation
     * @return string Interpolated snippet
     */
    public function interpolate(string $snippet, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $snippet = str_replace('{{' . $key . '}}', (string)$value, $snippet);
        }
        
        return $snippet;
    }
}
