<?php
declare(strict_types=1);

/**
 * Prompt Specification Repository
 * Handles CRUD operations for agent_prompts table with optional encryption
 */

require_once __DIR__ . '/../CryptoAdapter.php';

class PromptSpecRepository
{
    private PDO $pdo;
    private array $config;
    private ?CryptoAdapter $crypto = null;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
        
        // Initialize encryption if enabled
        if (!empty($config['encryption_at_rest']) && !empty($config['encryption_key'])) {
            $this->crypto = new CryptoAdapter($config['encryption_key']);
        }
    }

    /**
     * Create a new version row and return version number.
     * 
     * @param string $agentId Agent ID
     * @param string $promptMd Markdown prompt specification
     * @param array $guardrails List of applied guardrails with metadata
     * @param string|null $createdBy Admin user ID or token hash
     * @return int Version number
     */
    public function createVersion(
        string $agentId,
        string $promptMd,
        array $guardrails,
        ?string $createdBy = null
    ): int {
        // Get next version number for this agent
        $version = $this->getNextVersion($agentId);
        
        // Encrypt prompt if enabled
        $storedPrompt = $this->crypto ? $this->crypto->encrypt($promptMd) : $promptMd;
        
        // Generate UUID for this prompt version
        $id = $this->generateUUID();
        
        $sql = "INSERT INTO agent_prompts (
            id, agent_id, version, prompt_md, guardrails_json, created_by, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $id,
            $agentId,
            $version,
            $storedPrompt,
            json_encode($guardrails),
            $createdBy
        ]);
        
        return $version;
    }

    /**
     * List all versions for an agent
     * 
     * @param string $agentId Agent ID
     * @return array List of versions with metadata (excludes prompt content)
     */
    public function listVersions(string $agentId): array
    {
        $sql = "SELECT id, version, created_by, created_at, updated_at, guardrails_json
                FROM agent_prompts 
                WHERE agent_id = ? 
                ORDER BY version DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId]);
        
        $versions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $versions[] = [
                'id' => $row['id'],
                'version' => (int)$row['version'],
                'created_by' => $row['created_by'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'guardrails' => json_decode($row['guardrails_json'] ?? '[]', true),
            ];
        }
        
        return $versions;
    }

    /**
     * Get a specific version
     * 
     * @param string $agentId Agent ID
     * @param int $version Version number
     * @return array|null Version data including prompt content, or null if not found
     */
    public function getVersion(string $agentId, int $version): ?array
    {
        $sql = "SELECT id, version, prompt_md, guardrails_json, created_by, created_at, updated_at
                FROM agent_prompts 
                WHERE agent_id = ? AND version = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId, $version]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        // Decrypt prompt if encrypted
        $promptMd = $row['prompt_md'];
        if ($this->crypto) {
            try {
                $promptMd = $this->crypto->decrypt($promptMd);
            } catch (Exception $e) {
                error_log("Failed to decrypt prompt for agent {$agentId} version {$version}: " . $e->getMessage());
                // Return encrypted content as-is if decryption fails
            }
        }
        
        return [
            'id' => $row['id'],
            'version' => (int)$row['version'],
            'prompt_md' => $promptMd,
            'guardrails' => json_decode($row['guardrails_json'] ?? '[]', true),
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Get the latest version for an agent
     * 
     * @param string $agentId Agent ID
     * @return array|null Latest version data or null if no versions exist
     */
    public function getLatestVersion(string $agentId): ?array
    {
        $sql = "SELECT version 
                FROM agent_prompts 
                WHERE agent_id = ? 
                ORDER BY version DESC 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return $this->getVersion($agentId, (int)$row['version']);
    }

    /**
     * Activate a specific version for an agent
     * Updates the agents.active_prompt_version column
     * 
     * @param string $agentId Agent ID
     * @param int $version Version number to activate
     * @return bool True on success
     * @throws Exception If version doesn't exist
     */
    public function activateVersion(string $agentId, int $version): bool
    {
        // Verify version exists
        $versionData = $this->getVersion($agentId, $version);
        if ($versionData === null) {
            throw new Exception("Version {$version} not found for agent {$agentId}");
        }
        
        // Update agents table
        $sql = "UPDATE agents SET active_prompt_version = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$version, $agentId]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Deactivate prompt for an agent (set active_prompt_version to NULL)
     * 
     * @param string $agentId Agent ID
     * @return bool True on success
     */
    public function deactivateVersion(string $agentId): bool
    {
        $sql = "UPDATE agents SET active_prompt_version = NULL WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId]);
        
        return true;
    }

    /**
     * Get the active version number for an agent
     * 
     * @param string $agentId Agent ID
     * @return int|null Active version number or null if no version is active
     */
    public function getActiveVersionNumber(string $agentId): ?int
    {
        $sql = "SELECT active_prompt_version FROM agents WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || $row['active_prompt_version'] === null) {
            return null;
        }
        
        return (int)$row['active_prompt_version'];
    }

    /**
     * Delete a specific version
     * 
     * @param string $agentId Agent ID
     * @param int $version Version number
     * @return bool True on success
     */
    public function deleteVersion(string $agentId, int $version): bool
    {
        // Don't allow deleting active version
        $activeVersion = $this->getActiveVersionNumber($agentId);
        if ($activeVersion === $version) {
            throw new Exception("Cannot delete active version. Deactivate it first.");
        }
        
        $sql = "DELETE FROM agent_prompts WHERE agent_id = ? AND version = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId, $version]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Get next version number for an agent
     * 
     * @param string $agentId Agent ID
     * @return int Next version number (1 if no versions exist)
     */
    private function getNextVersion(string $agentId): int
    {
        $sql = "SELECT MAX(version) as max_version FROM agent_prompts WHERE agent_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row && $row['max_version'] !== null ? (int)$row['max_version'] + 1 : 1;
    }

    /**
     * Generate a UUID v4
     * 
     * @return string UUID
     */
    private function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
