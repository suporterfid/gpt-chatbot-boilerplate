<?php
/**
 * Assistant Management for GPT Assistants API
 */

class AssistantManager {
    private $openAIClient;
    private $config;
    private $assistantId;

    public function __construct($openAIClient, $config) {
        $this->openAIClient = $openAIClient;
        $this->config = $config;
        $this->assistantId = null;
    }

    public function getOrCreateAssistant() {
        // Return cached assistant ID
        if ($this->assistantId) {
            return $this->assistantId;
        }

        // Use configured assistant ID
        if (!empty($this->config['assistants']['assistant_id'])) {
            $this->assistantId = $this->config['assistants']['assistant_id'];

            // Verify assistant exists
            try {
                $this->openAIClient->getAssistant($this->assistantId);
                return $this->assistantId;
            } catch (Exception $e) {
                error_log("Assistant {$this->assistantId} not found: " . $e->getMessage());

                if (!$this->config['assistants']['create_assistant']) {
                    throw new Exception("Assistant not found and auto-creation disabled");
                }

                // Reset to create new assistant
                $this->assistantId = null;
            }
        }

        // Create new assistant if enabled
        if ($this->config['assistants']['create_assistant']) {
            $this->assistantId = $this->createAssistant();
            return $this->assistantId;
        }

        throw new Exception("No assistant configured and auto-creation disabled");
    }

    private function createAssistant() {
        $tools = $this->buildToolsConfig();

        $assistantConfig = [
            'name' => $this->config['assistants']['assistant_name'],
            'description' => $this->config['assistants']['assistant_description'],
            'instructions' => $this->config['assistants']['assistant_instructions'],
            'model' => $this->config['assistants']['model'],
            'temperature' => $this->config['assistants']['temperature'],
            'tools' => $tools
        ];

        $response = $this->openAIClient->createAssistant($assistantConfig);

        if (!isset($response['id'])) {
            throw new Exception("Failed to create assistant");
        }

        $assistantId = $response['id'];

        // Log the created assistant ID for future reference
        error_log("Created new assistant: {$assistantId}");

        return $assistantId;
    }

    private function buildToolsConfig() {
        $tools = [];

        // Add built-in tools
        if ($this->config['assistants']['code_interpreter']) {
            $tools[] = ['type' => 'code_interpreter'];
        }

        if ($this->config['assistants']['file_search']) {
            $tools[] = ['type' => 'file_search'];
        }

        // Add custom function tools
        $customTools = $this->getCustomTools();
        $tools = array_merge($tools, $customTools);

        return $tools;
    }

    private function getCustomTools() {
        // Define custom function tools here
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => 'Get current weather for a location',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => [
                                'type' => 'string',
                                'description' => 'The city and country, e.g. San Francisco, CA'
                            ]
                        ],
                        'required' => ['location']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_knowledge',
                    'description' => 'Search the knowledge base for information',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'The search query'
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ]
        ];
    }

    public function updateAssistant($assistantId, $updates) {
        return $this->openAIClient->updateAssistant($assistantId, $updates);
    }

    public function deleteAssistant($assistantId) {
        return $this->openAIClient->deleteAssistant($assistantId);
    }

    public function listAssistants() {
        return $this->openAIClient->listAssistants();
    }
}
?>