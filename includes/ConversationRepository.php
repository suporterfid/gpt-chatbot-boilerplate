<?php
declare(strict_types=1);

/**
 * ConversationRepository
 * 
 * Manages conversation history storage and retrieval.
 * Supports multiple storage backends (session, file, database).
 * Extracted from ChatHandler to follow Single Responsibility Principle.
 * 
 * @package GPT_Chatbot
 */
class ConversationRepository
{
    private array $config;

    /**
     * Constructor
     * 
     * @param array $config Application configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get conversation history
     * 
     * @param string $conversationId The conversation ID
     * @return array Array of message objects
     */
    public function getHistory(string $conversationId): array
    {
        $storageType = $this->config['storage']['type'] ?? 'session';

        switch ($storageType) {
            case 'session':
                return $this->getFromSession($conversationId);

            case 'file':
                return $this->getFromFile($conversationId);

            default:
                return [];
        }
    }

    /**
     * Save conversation history
     * 
     * @param string $conversationId The conversation ID
     * @param array $messages Array of message objects
     */
    public function saveHistory(string $conversationId, array $messages): void
    {
        // Limit conversation history to configured maximum
        $maxMessages = $this->config['chat_config']['max_messages'] ?? 50;
        if (count($messages) > $maxMessages) {
            $messages = array_slice($messages, -$maxMessages);
        }

        $storageType = $this->config['storage']['type'] ?? 'session';

        switch ($storageType) {
            case 'session':
                $this->saveToSession($conversationId, $messages);
                break;

            case 'file':
                $this->saveToFile($conversationId, $messages);
                break;
        }
    }

    /**
     * Get conversation history from session storage
     * 
     * @param string $conversationId The conversation ID
     * @return array Array of message objects
     */
    private function getFromSession(string $conversationId): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = 'chatbot_conversation_' . $conversationId;
        return $_SESSION[$sessionKey] ?? [];
    }

    /**
     * Save conversation history to session storage
     * 
     * @param string $conversationId The conversation ID
     * @param array $messages Array of message objects
     */
    private function saveToSession(string $conversationId, array $messages): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionKey = 'chatbot_conversation_' . $conversationId;
        $_SESSION[$sessionKey] = $messages;
    }

    /**
     * Get conversation history from file storage
     * 
     * @param string $conversationId The conversation ID
     * @return array Array of message objects
     */
    private function getFromFile(string $conversationId): array
    {
        $storagePath = $this->config['storage']['path'] ?? sys_get_temp_dir();
        $filePath = $storagePath . '/' . $conversationId . '.json';
        
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            return json_decode($content, true) ?: [];
        }
        
        return [];
    }

    /**
     * Save conversation history to file storage
     * 
     * @param string $conversationId The conversation ID
     * @param array $messages Array of message objects
     */
    private function saveToFile(string $conversationId, array $messages): void
    {
        $storagePath = $this->config['storage']['path'] ?? sys_get_temp_dir();
        
        // Ensure storage directory exists
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        $filePath = $storagePath . '/' . $conversationId . '.json';
        file_put_contents($filePath, json_encode($messages));
    }
}
