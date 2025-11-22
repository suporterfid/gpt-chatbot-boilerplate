<?php
/**
 * WordPress Blog Configuration Service
 *
 * Manages WordPress blog configurations including CRUD operations,
 * credential encryption/decryption, and internal links management.
 *
 * @package WordPressBlog\Services
 */

require_once __DIR__ . '/../../DB.php';
require_once __DIR__ . '/../../CryptoAdapter.php';
require_once __DIR__ . '/../../config.php';

class WordPressBlogConfigurationService {
    private $db;
    private $crypto;

    /**
     * Constructor
     *
     * @param DB $db Database instance
     * @param CryptoAdapter|array $config Either a CryptoAdapter instance or configuration array with encryption_key
     */
    public function __construct($db, $config = []) {
        $this->db = $db;

        // Handle both CryptoAdapter object and config array
        if ($config instanceof CryptoAdapter) {
            $this->crypto = $config;
        } else {
            // Initialize encryption from config array
            $encryptionKey = $config['encryption_key'] ?? getEnvValue('BLOG_ENCRYPTION_KEY') ?? getEnvValue('ENCRYPTION_KEY');
            if (empty($encryptionKey)) {
                throw new Exception('Encryption key is required for WordPressBlogConfigurationService');
            }

            $this->crypto = new CryptoAdapter(['encryption_key' => $encryptionKey]);
        }
    }

    // ============================================================
    // CRUD Operations
    // ============================================================

    /**
     * Create a new blog configuration
     *
     * @param array $config Configuration data
     * @return string Configuration ID (UUID)
     * @throws Exception
     */
    public function createConfiguration(array $config) {
        // Validate required fields
        $this->validateConfigurationData($config, true);

        // Encrypt API credentials
        $encryptedCredentials = $this->encryptCredentials([
            'wordpress_api_key' => $config['wordpress_api_key'],
            'openai_api_key' => $config['openai_api_key']
        ]);

        // Generate UUID for configuration_id
        $configurationId = $this->generateUUID();

        // Prepare data for insertion
        $insertData = [
            'configuration_id' => $configurationId,
            'config_name' => $config['config_name'],
            'website_url' => $config['website_url'],
            'number_of_chapters' => $config['number_of_chapters'] ?? 5,
            'max_word_count' => $config['max_word_count'] ?? 3000,
            'introduction_length' => $config['introduction_length'] ?? 300,
            'conclusion_length' => $config['conclusion_length'] ?? 200,
            'cta_message' => $config['cta_message'] ?? null,
            'cta_url' => $config['cta_url'] ?? null,
            'company_offering' => $config['company_offering'] ?? null,
            'wordpress_api_url' => $config['wordpress_api_url'],
            'wordpress_api_key_encrypted' => $encryptedCredentials['wordpress_api_key'],
            'openai_api_key_encrypted' => $encryptedCredentials['openai_api_key'],
            'default_publish_status' => $config['default_publish_status'] ?? 'draft',
            'google_drive_folder_id' => $config['google_drive_folder_id'] ?? null
        ];

        // Insert into database
        $sql = "INSERT INTO blog_configurations (
            configuration_id, config_name, website_url, number_of_chapters,
            max_word_count, introduction_length, conclusion_length,
            cta_message, cta_url, company_offering, wordpress_api_url,
            wordpress_api_key_encrypted, openai_api_key_encrypted,
            default_publish_status, google_drive_folder_id
        ) VALUES (
            :configuration_id, :config_name, :website_url, :number_of_chapters,
            :max_word_count, :introduction_length, :conclusion_length,
            :cta_message, :cta_url, :company_offering, :wordpress_api_url,
            :wordpress_api_key_encrypted, :openai_api_key_encrypted,
            :default_publish_status, :google_drive_folder_id
        )";

        $this->db->execute($sql, $insertData);

        return $configurationId;
    }

    /**
     * Get configuration by ID
     *
     * @param string $configId Configuration ID
     * @param bool $includeCredentials Whether to decrypt and include credentials
     * @return array|null Configuration data or null if not found
     */
    public function getConfiguration(string $configId, bool $includeCredentials = false) {
        $sql = "SELECT * FROM blog_configurations WHERE configuration_id = :config_id";
        $results = $this->db->query($sql, ['config_id' => $configId]);

        if (empty($results)) {
            return null;
        }

        $config = $results[0];

        // Decrypt credentials if requested
        if ($includeCredentials) {
            $decryptedCredentials = $this->decryptCredentials([
                'wordpress_api_key' => $config['wordpress_api_key_encrypted'],
                'openai_api_key' => $config['openai_api_key_encrypted']
            ]);

            $config['wordpress_api_key'] = $decryptedCredentials['wordpress_api_key'];
            $config['openai_api_key'] = $decryptedCredentials['openai_api_key'];
        }

        // Remove encrypted versions from response
        unset($config['wordpress_api_key_encrypted']);
        unset($config['openai_api_key_encrypted']);

        return $config;
    }

    /**
     * Update configuration
     *
     * @param string $configId Configuration ID
     * @param array $updates Fields to update
     * @return bool Success status
     * @throws Exception
     */
    public function updateConfiguration(string $configId, array $updates) {
        // Check if configuration exists
        if (!$this->getConfiguration($configId)) {
            throw new Exception("Configuration not found: $configId");
        }

        // Validate updates
        $this->validateConfigurationData($updates, false);

        // Encrypt API credentials if provided
        if (isset($updates['wordpress_api_key'])) {
            $encrypted = $this->crypto->encrypt($updates['wordpress_api_key']);
            $updates['wordpress_api_key_encrypted'] = $this->crypto->encodeForStorage($encrypted);
            unset($updates['wordpress_api_key']);
        }

        if (isset($updates['openai_api_key'])) {
            $encrypted = $this->crypto->encrypt($updates['openai_api_key']);
            $updates['openai_api_key_encrypted'] = $this->crypto->encodeForStorage($encrypted);
            unset($updates['openai_api_key']);
        }

        // Build UPDATE query dynamically
        $setClause = [];
        $params = ['config_id' => $configId];

        foreach ($updates as $field => $value) {
            $setClause[] = "$field = :$field";
            $params[$field] = $value;
        }

        if (empty($setClause)) {
            return true; // Nothing to update
        }

        $sql = "UPDATE blog_configurations SET " . implode(', ', $setClause) .
               " WHERE configuration_id = :config_id";

        $rowsAffected = $this->db->execute($sql, $params);

        return $rowsAffected > 0;
    }

    /**
     * Delete configuration
     *
     * @param string $configId Configuration ID
     * @return bool Success status
     */
    public function deleteConfiguration(string $configId) {
        $sql = "DELETE FROM blog_configurations WHERE configuration_id = :config_id";
        $rowsAffected = $this->db->execute($sql, ['config_id' => $configId]);

        return $rowsAffected > 0;
    }

    /**
     * List all configurations
     *
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array Array of configurations
     */
    public function listConfigurations(int $limit = 50, int $offset = 0) {
        $sql = "SELECT configuration_id, config_name, website_url,
                       number_of_chapters, max_word_count, default_publish_status,
                       created_at, updated_at
                FROM blog_configurations
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $configs = $this->db->query($sql, [
            'limit' => $limit,
            'offset' => $offset
        ]);

        return $configs;
    }

    // ============================================================
    // Validation
    // ============================================================

    /**
     * Validate configuration data
     *
     * @param array $data Configuration data
     * @param bool $requireAll Whether all required fields must be present
     * @return array Validation errors (empty if valid)
     * @throws Exception If validation fails
     */
    public function validateConfigurationData(array $data, bool $requireAll = false) {
        $errors = [];

        // Required fields (only check if requireAll is true)
        if ($requireAll) {
            $required = ['config_name', 'website_url', 'wordpress_api_url',
                        'wordpress_api_key', 'openai_api_key'];

            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $errors[] = "Missing required field: $field";
                }
            }
        }

        // Validate config_name
        if (isset($data['config_name'])) {
            if (strlen($data['config_name']) < 1 || strlen($data['config_name']) > 255) {
                $errors[] = "config_name must be between 1 and 255 characters";
            }
        }

        // Validate URLs
        if (isset($data['website_url']) && !filter_var($data['website_url'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid website_url format";
        }

        if (isset($data['wordpress_api_url']) && !filter_var($data['wordpress_api_url'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid wordpress_api_url format";
        }

        if (isset($data['cta_url']) && !empty($data['cta_url']) &&
            !filter_var($data['cta_url'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid cta_url format";
        }

        // Validate number ranges
        if (isset($data['number_of_chapters'])) {
            if ($data['number_of_chapters'] < 1 || $data['number_of_chapters'] > 20) {
                $errors[] = "number_of_chapters must be between 1 and 20";
            }
        }

        if (isset($data['max_word_count'])) {
            if ($data['max_word_count'] < 500 || $data['max_word_count'] > 10000) {
                $errors[] = "max_word_count must be between 500 and 10000";
            }
        }

        if (isset($data['introduction_length'])) {
            if ($data['introduction_length'] < 100 || $data['introduction_length'] > 1000) {
                $errors[] = "introduction_length must be between 100 and 1000";
            }
        }

        if (isset($data['conclusion_length'])) {
            if ($data['conclusion_length'] < 100 || $data['conclusion_length'] > 1000) {
                $errors[] = "conclusion_length must be between 100 and 1000";
            }
        }

        // Validate API keys
        if (isset($data['wordpress_api_key']) && strlen($data['wordpress_api_key']) < 20) {
            $errors[] = "wordpress_api_key must be at least 20 characters";
        }

        if (isset($data['openai_api_key'])) {
            if (!str_starts_with($data['openai_api_key'], 'sk-')) {
                $errors[] = "openai_api_key must start with 'sk-'";
            }
        }

        // Validate publish status
        if (isset($data['default_publish_status'])) {
            if (!in_array($data['default_publish_status'], ['draft', 'publish', 'pending'])) {
                $errors[] = "default_publish_status must be 'draft', 'publish', or 'pending'";
            }
        }

        if (!empty($errors)) {
            throw new Exception("Validation failed: " . implode(", ", $errors));
        }

        return [];
    }

    /**
     * Check if configuration is complete and ready to use
     *
     * @param string $configId Configuration ID
     * @return bool True if configuration is complete
     */
    public function isConfigurationComplete(string $configId) {
        $config = $this->getConfiguration($configId, true);

        if (!$config) {
            return false;
        }

        // Check required fields
        $required = ['config_name', 'website_url', 'wordpress_api_url',
                    'wordpress_api_key', 'openai_api_key'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                return false;
            }
        }

        return true;
    }

    // ============================================================
    // Credential Management
    // ============================================================

    /**
     * Encrypt API credentials
     *
     * @param array $credentials Array with 'wordpress_api_key' and 'openai_api_key'
     * @return array Encrypted credentials
     */
    private function encryptCredentials(array $credentials) {
        $encrypted = [];

        foreach ($credentials as $key => $value) {
            $encryptedData = $this->crypto->encrypt($value);
            $encrypted[$key] = $this->crypto->encodeForStorage($encryptedData);
        }

        return $encrypted;
    }

    /**
     * Decrypt API credentials
     *
     * @param array $encryptedCredentials Array with encrypted credentials
     * @return array Decrypted credentials
     */
    private function decryptCredentials(array $encryptedCredentials) {
        $decrypted = [];

        foreach ($encryptedCredentials as $key => $encrypted) {
            $encryptedData = $this->crypto->decodeFromStorage($encrypted);
            $decrypted[str_replace('_encrypted', '', $key)] = $this->crypto->decrypt(
                $encryptedData['ciphertext'],
                $encryptedData['nonce'],
                $encryptedData['tag']
            );
        }

        return $decrypted;
    }

    // ============================================================
    // Internal Links Management
    // ============================================================

    /**
     * Add an internal link
     *
     * @param string $configId Configuration ID
     * @param array $linkData Link data (url, anchor_text, relevance_keywords, priority, is_active)
     * @return string Link ID (UUID)
     * @throws Exception
     */
    public function addInternalLink(string $configId, array $linkData) {
        // Validate configuration exists
        if (!$this->getConfiguration($configId)) {
            throw new Exception("Configuration not found: $configId");
        }

        // Validate link data
        if (empty($linkData['url']) || empty($linkData['anchor_text'])) {
            throw new Exception("URL and anchor_text are required for internal links");
        }

        if (!filter_var($linkData['url'], FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL format");
        }

        // Generate UUID for link_id
        $linkId = $this->generateUUID();

        // Prepare keywords (convert array to JSON if needed)
        $keywords = $linkData['relevance_keywords'] ?? null;
        if (is_array($keywords)) {
            $keywords = json_encode($keywords);
        }

        $sql = "INSERT INTO blog_internal_links (
            link_id, configuration_id, url, anchor_text, relevance_keywords,
            priority, is_active
        ) VALUES (
            :link_id, :configuration_id, :url, :anchor_text, :keywords,
            :priority, :is_active
        )";

        $this->db->execute($sql, [
            'link_id' => $linkId,
            'configuration_id' => $configId,
            'url' => $linkData['url'],
            'anchor_text' => $linkData['anchor_text'],
            'keywords' => $keywords,
            'priority' => $linkData['priority'] ?? 5,
            'is_active' => isset($linkData['is_active']) ? ($linkData['is_active'] ? 1 : 0) : 1
        ]);

        return $linkId;
    }

    /**
     * Get internal links for a configuration
     *
     * @param string $configId Configuration ID
     * @param bool $activeOnly Whether to return only active links
     * @return array Array of internal links
     */
    public function getInternalLinks(string $configId, bool $activeOnly = true) {
        $sql = "SELECT * FROM blog_internal_links
                WHERE configuration_id = :config_id";

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY priority DESC, created_at DESC";

        $links = $this->db->query($sql, ['config_id' => $configId]);

        // Parse JSON keywords
        foreach ($links as &$link) {
            if (!empty($link['relevance_keywords'])) {
                $link['relevance_keywords'] = json_decode($link['relevance_keywords'], true);
            }
        }

        return $links;
    }

    /**
     * Update an internal link
     *
     * @param string $linkId Link ID
     * @param array $updates Fields to update
     * @return bool Success status
     */
    public function updateInternalLink(string $linkId, array $updates) {
        // Validate URL if provided
        if (isset($updates['url']) && !filter_var($updates['url'], FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL format");
        }

        // Convert keywords array to JSON if needed
        if (isset($updates['relevance_keywords']) && is_array($updates['relevance_keywords'])) {
            $updates['relevance_keywords'] = json_encode($updates['relevance_keywords']);
        }

        // Convert is_active boolean to integer
        if (isset($updates['is_active'])) {
            $updates['is_active'] = $updates['is_active'] ? 1 : 0;
        }

        // Build UPDATE query
        $setClause = [];
        $params = ['link_id' => $linkId];

        foreach ($updates as $field => $value) {
            $setClause[] = "$field = :$field";
            $params[$field] = $value;
        }

        if (empty($setClause)) {
            return true;
        }

        $sql = "UPDATE blog_internal_links SET " . implode(', ', $setClause) .
               " WHERE link_id = :link_id";

        $rowsAffected = $this->db->execute($sql, $params);

        return $rowsAffected > 0;
    }

    /**
     * Delete an internal link
     *
     * @param string $linkId Link ID
     * @return bool Success status
     */
    public function deleteInternalLink(string $linkId) {
        $sql = "DELETE FROM blog_internal_links WHERE link_id = :link_id";
        $rowsAffected = $this->db->execute($sql, ['link_id' => $linkId]);

        return $rowsAffected > 0;
    }

    /**
     * Find relevant links by keywords
     *
     * @param string $configId Configuration ID
     * @param array $keywords Array of keywords to match
     * @param int $limit Maximum number of links to return
     * @return array Array of relevant links
     */
    public function findRelevantLinks(string $configId, array $keywords, int $limit = 5) {
        // Get all active links for configuration
        $allLinks = $this->getInternalLinks($configId, true);

        if (empty($allLinks) || empty($keywords)) {
            return [];
        }

        // Score each link based on keyword relevance
        $scoredLinks = [];

        foreach ($allLinks as $link) {
            $score = 0;
            $linkKeywords = $link['relevance_keywords'] ?? [];

            if (is_string($linkKeywords)) {
                $linkKeywords = json_decode($linkKeywords, true) ?? [];
            }

            // Calculate relevance score
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim($keyword));

                foreach ($linkKeywords as $linkKeyword) {
                    $linkKeyword = strtolower(trim($linkKeyword));

                    // Exact match
                    if ($keyword === $linkKeyword) {
                        $score += 10;
                    }
                    // Partial match
                    elseif (stripos($linkKeyword, $keyword) !== false ||
                            stripos($keyword, $linkKeyword) !== false) {
                        $score += 5;
                    }
                }

                // Check anchor text
                if (stripos($link['anchor_text'], $keyword) !== false) {
                    $score += 3;
                }
            }

            // Add priority bonus
            $score += ($link['priority'] ?? 5);

            if ($score > 0) {
                $link['relevance_score'] = $score;
                $scoredLinks[] = $link;
            }
        }

        // Sort by relevance score
        usort($scoredLinks, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });

        // Return top N links
        return array_slice($scoredLinks, 0, $limit);
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Generate a UUID v4
     *
     * @return string UUID
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
