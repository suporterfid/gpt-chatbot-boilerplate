<?php
/**
 * Thread Management for GPT Assistants API
 */

class ThreadManager {
    private $openAIClient;
    private $config;
    private $threadMappings;

    public function __construct($openAIClient, $config) {
        $this->openAIClient = $openAIClient;
        $this->config = $config;
        $this->loadThreadMappings();
    }

    public function getOrCreateThread($conversationId) {
        // Check if thread exists for this conversation
        if (isset($this->threadMappings[$conversationId])) {
            $threadId = $this->threadMappings[$conversationId]['thread_id'];

            // Verify thread still exists
            try {
                $this->openAIClient->getThread($threadId);
                return $threadId;
            } catch (Exception $e) {
                // Thread no longer exists, remove mapping
                unset($this->threadMappings[$conversationId]);
                $this->saveThreadMappings();
            }
        }

        // Create new thread
        $thread = $this->openAIClient->createThread([
            'conversation_id' => $conversationId,
            'created_at' => time()
        ]);

        $threadId = $thread['id'];

        // Store mapping
        $this->threadMappings[$conversationId] = [
            'thread_id' => $threadId,
            'created_at' => time(),
            'last_activity' => time()
        ];

        $this->saveThreadMappings();

        return $threadId;
    }

    public function updateThreadMapping($conversationId, $threadId) {
        if (isset($this->threadMappings[$conversationId])) {
            $this->threadMappings[$conversationId]['last_activity'] = time();
            $this->saveThreadMappings();
        }
    }

    public function getThreadHistory($conversationId, $limit = 20) {
        if (!isset($this->threadMappings[$conversationId])) {
            return [];
        }

        $threadId = $this->threadMappings[$conversationId]['thread_id'];

        try {
            $response = $this->openAIClient->getThreadMessages($threadId, [
                'limit' => $limit,
                'order' => 'desc'
            ]);

            return array_reverse($response['data']);
        } catch (Exception $e) {
            error_log("Error getting thread history: " . $e->getMessage());
            return [];
        }
    }

    public function cleanupOldThreads() {
        $cleanupHours = $this->config['assistants']['thread_cleanup_hours'];
        $cutoffTime = time() - ($cleanupHours * 3600);
        $cleaned = 0;

        foreach ($this->threadMappings as $conversationId => $mapping) {
            if ($mapping['last_activity'] < $cutoffTime) {
                try {
                    // Delete thread from OpenAI
                    $this->openAIClient->deleteThread($mapping['thread_id']);
                } catch (Exception $e) {
                    // Thread might already be deleted, continue cleanup
                }

                // Remove from mappings
                unset($this->threadMappings[$conversationId]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->saveThreadMappings();
            error_log("Cleaned up {$cleaned} old threads");
        }

        return $cleaned;
    }

    public function deleteThread($conversationId) {
        if (isset($this->threadMappings[$conversationId])) {
            $threadId = $this->threadMappings[$conversationId]['thread_id'];

            try {
                $this->openAIClient->deleteThread($threadId);
            } catch (Exception $e) {
                // Thread might already be deleted
            }

            unset($this->threadMappings[$conversationId]);
            $this->saveThreadMappings();
        }
    }

    private function loadThreadMappings() {
        $mappingFile = $this->getMappingFile();

        if (file_exists($mappingFile)) {
            $data = file_get_contents($mappingFile);
            $this->threadMappings = json_decode($data, true) ?: [];
        } else {
            $this->threadMappings = [];
        }
    }

    private function saveThreadMappings() {
        $mappingFile = $this->getMappingFile();
        $mappingDir = dirname($mappingFile);

        if (!is_dir($mappingDir)) {
            mkdir($mappingDir, 0755, true);
        }

        file_put_contents($mappingFile, json_encode($this->threadMappings, JSON_PRETTY_PRINT));
    }

    private function getMappingFile() {
        $storageType = $this->config['storage']['type'];

        switch ($storageType) {
            case 'file':
                return $this->config['storage']['path'] . '/thread_mappings.json';
            default:
                return sys_get_temp_dir() . '/chatbot_thread_mappings.json';
        }
    }

    public function getThreadStats() {
        $totalThreads = count($this->threadMappings);
        $activeThreads = 0;
        $oldestActivity = time();
        $newestActivity = 0;

        foreach ($this->threadMappings as $mapping) {
            $lastActivity = $mapping['last_activity'];

            if ($lastActivity > time() - 3600) { // Active in last hour
                $activeThreads++;
            }

            $oldestActivity = min($oldestActivity, $lastActivity);
            $newestActivity = max($newestActivity, $lastActivity);
        }

        return [
            'total_threads' => $totalThreads,
            'active_threads' => $activeThreads,
            'oldest_activity' => $oldestActivity,
            'newest_activity' => $newestActivity
        ];
    }
}
?>