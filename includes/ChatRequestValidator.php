<?php
declare(strict_types=1);

/**
 * ChatRequestValidator
 * 
 * Validates chat request parameters including messages, conversation IDs, and file uploads.
 * Extracted from ChatHandler to follow Single Responsibility Principle.
 * 
 * @package GPT_Chatbot
 */
class ChatRequestValidator
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
     * Validate a chat message
     * 
     * @param string $message The message to validate
     * @return string The validated (and potentially sanitized) message
     * @throws Exception if validation fails
     */
    public function validateMessage(string $message): string
    {
        // Check if message is empty
        if (empty(trim($message))) {
            throw new Exception('Message cannot be empty', 400);
        }

        // Check message length
        $maxLength = $this->config['security']['max_message_length'] ?? 10000;
        if (strlen($message) > $maxLength) {
            throw new Exception('Message too long', 400);
        }

        // Sanitize input if enabled
        if ($this->config['security']['sanitize_input'] ?? false) {
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }

        return $message;
    }

    /**
     * Validate conversation ID format
     * 
     * @param string $conversationId The conversation ID to validate
     * @throws Exception if validation fails
     */
    public function validateConversationId(string $conversationId): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $conversationId)) {
            throw new Exception('Invalid conversation ID format', 400);
        }
    }

    /**
     * Validate file upload data
     * 
     * @param mixed $fileData Single file or array of files to validate
     * @throws Exception if validation fails
     */
    public function validateFileData($fileData): void
    {
        // Check if file upload is enabled
        if (!($this->config['chat_config']['enable_file_upload'] ?? false)) {
            throw new Exception('File upload not enabled', 400);
        }

        // Load FileValidator if not already loaded
        if (!class_exists('FileValidator')) {
            require_once __DIR__ . '/FileValidator.php';
        }
        
        $validator = new FileValidator();
        
        // Ensure file data is an array
        if (!is_array($fileData)) {
            $fileData = [$fileData];
        }

        // Validate each file
        foreach ($fileData as $file) {
            // Use comprehensive validation from FileValidator
            // This validates: filename, size (encoded & decoded), MIME type, malware
            $validator->validateFile($file, $this->config['chat_config']);
        }
    }

    /**
     * Validate complete chat request
     * 
     * @param string $message The message to validate
     * @param string $conversationId The conversation ID to validate
     * @param mixed|null $fileData Optional file data to validate
     * @return string The validated (and potentially sanitized) message
     * @throws Exception if any validation fails
     */
    public function validateRequest(string $message, string $conversationId, $fileData = null): string
    {
        // Validate message
        $message = $this->validateMessage($message);

        // Validate conversation ID
        $this->validateConversationId($conversationId);

        // Validate file data if provided
        if ($fileData !== null) {
            $this->validateFileData($fileData);
        }

        return $message;
    }
}
