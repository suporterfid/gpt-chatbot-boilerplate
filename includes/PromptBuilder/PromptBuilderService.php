<?php
declare(strict_types=1);

/**
 * Prompt Builder Service
 * Orchestrates LLM-based generation of structured agent prompts with guardrails
 */

require_once __DIR__ . '/../OpenAIClient.php';
require_once __DIR__ . '/GuardrailLoader.php';
require_once __DIR__ . '/PromptSpecRepository.php';
require_once __DIR__ . '/../PIIRedactor.php';

class PromptBuilderService
{
    private OpenAIClient $client;
    private GuardrailLoader $guardrails;
    private PromptSpecRepository $repo;
    private ?PIIRedactor $redactor;
    private array $config;

    public function __construct(
        OpenAIClient $client,
        GuardrailLoader $guardrails,
        PromptSpecRepository $repo,
        ?PIIRedactor $redactor = null,
        array $config = []
    ) {
        $this->client = $client;
        $this->guardrails = $guardrails;
        $this->repo = $repo;
        $this->redactor = $redactor;
        $this->config = $config;
    }

    /**
     * Generate a structured Markdown prompt for an agent idea.
     *
     * @param string $agentId Agent ID
     * @param string $ideaText User's agent idea description
     * @param array $guardrailKeys List of guardrail keys to apply
     * @param array $options Additional options (e.g., ['language' => 'en', 'brand_name' => 'Acme'])
     * @return array {version:int, prompt_md:string, applied_guardrails:array, usage?:array, latency_ms?:int}
     * @throws Exception On generation or storage failure
     */
    public function generate(
        string $agentId,
        string $ideaText,
        array $guardrailKeys = [],
        array $options = []
    ): array {
        $startTime = microtime(true);
        
        // Validate input
        $this->validateInput($ideaText, $guardrailKeys);
        
        // Apply default guardrails if none specified
        if (empty($guardrailKeys)) {
            $guardrailKeys = $this->config['default_guardrails'] ?? ['hallucination_prevention', 'scope_restriction'];
        }
        
        // Ensure mandatory guardrails are included
        $guardrailKeys = $this->ensureMandatoryGuardrails($guardrailKeys);
        
        // Load guardrail templates
        $templates = $this->guardrails->load($guardrailKeys);
        
        // Build the LLM request
        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($ideaText, $templates, $options);
        
        // Call OpenAI to generate the structured prompt
        $response = $this->callOpenAI($systemPrompt, $userMessage);
        
        // Extract generated prompt from response
        $generatedPrompt = $this->extractPromptFromResponse($response);
        
        // Apply PII redaction if configured
        if ($this->redactor) {
            $generatedPrompt = $this->redactor->redact($generatedPrompt);
        }
        
        // Store the version
        $createdBy = $options['created_by'] ?? null;
        $version = $this->repo->createVersion(
            $agentId,
            $generatedPrompt,
            $this->buildGuardrailsMetadata($templates),
            $createdBy
        );
        
        $latencyMs = (int)((microtime(true) - $startTime) * 1000);
        
        return [
            'version' => $version,
            'prompt_md' => $generatedPrompt,
            'applied_guardrails' => array_keys($templates),
            'usage' => $response['usage'] ?? null,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * Validate input parameters
     * 
     * @param string $ideaText Agent idea
     * @param array $guardrailKeys Guardrail keys
     * @throws Exception On validation failure
     */
    private function validateInput(string $ideaText, array $guardrailKeys): void
    {
        $ideaText = trim($ideaText);
        
        // Check minimum length
        if (strlen($ideaText) < 10) {
            throw new InvalidArgumentException('Agent idea must be at least 10 characters long', 422);
        }

        // Check maximum length
        $maxLength = $this->config['max_idea_length'] ?? 2000;
        if (strlen($ideaText) > $maxLength) {
            throw new InvalidArgumentException("Agent idea must not exceed {$maxLength} characters", 422);
        }

        // Validate guardrail keys are strings
        foreach ($guardrailKeys as $key) {
            if (!is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('Invalid guardrail key', 422);
            }
        }
    }

    /**
     * Ensure mandatory guardrails are included
     * 
     * @param array $guardrailKeys User-selected guardrails
     * @return array Guardrails with mandatory ones added
     */
    private function ensureMandatoryGuardrails(array $guardrailKeys): array
    {
        $catalog = $this->guardrails->catalog();
        
        foreach ($catalog as $item) {
            if (!empty($item['mandatory']) && !in_array($item['key'], $guardrailKeys, true)) {
                $guardrailKeys[] = $item['key'];
            }
        }
        
        return array_unique($guardrailKeys);
    }

    /**
     * Build system prompt for the LLM
     * 
     * @return string System prompt
     */
    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert AI agent specification writer. Your task is to transform a brief agent idea into a comprehensive, well-structured Markdown specification.

Your output must follow this exact structure:

# Agent Specification

## 1. Role
A clear, concise description of the agent's primary role and purpose.

## 2. Audience
Who will interact with this agent? Define the target users.

## 3. Capabilities
List the specific capabilities and tasks the agent can perform:
- Capability 1
- Capability 2
- ...

## 4. Tone & Style
Describe the communication style (e.g., professional, friendly, technical, empathetic).

## 5. Out-of-Scope
Explicitly state what the agent should NOT do or answer.

{GUARDRAILS_SECTION}

## Important Guidelines:
- Be specific and actionable
- Avoid vague statements
- Keep the specification focused and clear
- Use bullet points for lists
- Ensure guardrails are clearly integrated

Output ONLY the Markdown specification. Do not include any preamble or explanations.
PROMPT;
    }

    /**
     * Build user message with idea and guardrails
     * 
     * @param string $ideaText User's idea
     * @param array $templates Loaded guardrail templates
     * @param array $options Additional options for interpolation
     * @return string User message
     */
    private function buildUserMessage(string $ideaText, array $templates, array $options): string
    {
        $language = $options['language'] ?? 'en';
        
        // Build guardrails section
        $guardrailsText = '';
        foreach ($templates as $key => $template) {
            $snippet = $template['snippet'];
            
            // Apply interpolation if variables provided
            if (!empty($options['variables'])) {
                $snippet = $this->guardrails->interpolate($snippet, $options['variables']);
            }
            
            $guardrailsText .= $snippet . "\n\n";
        }
        
        $message = "Agent Idea:\n{$ideaText}\n\n";
        $message .= "Required Guardrails:\n{$guardrailsText}\n";
        
        if ($language !== 'en') {
            $message .= "\nLanguage: Generate the specification in {$language}\n";
        }
        
        return $message;
    }

    /**
     * Call OpenAI API to generate the prompt
     * 
     * @param string $systemPrompt System prompt
     * @param string $userMessage User message
     * @return array API response
     * @throws Exception On API error
     */
    private function callOpenAI(string $systemPrompt, string $userMessage): array
    {
        $model = $this->config['model'] ?? 'gpt-4o-mini';
        $timeout = ($this->config['timeout_ms'] ?? 20000) / 1000; // Convert to seconds
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ];
        
        try {
            // Use synchronous completion (non-streaming)
            return $this->client->createChatCompletion($payload);
        } catch (Exception $e) {
            error_log("Prompt Builder: OpenAI API error: " . $e->getMessage());

            $message = $this->normalizeOpenAIErrorMessage($e->getMessage());

            throw new Exception($message, 424);
        }
    }

    /**
     * Provide a human-friendly message for OpenAI dependency failures
     */
    private function normalizeOpenAIErrorMessage(string $rawMessage): string
    {
        $message = trim($rawMessage);

        if ($message === '') {
            return 'Failed to generate prompt specification due to an unexpected OpenAI error.';
        }

        if (stripos($message, 'api key') !== false || stripos($message, '401') !== false) {
            return 'Failed to generate prompt specification: OpenAI rejected the credentials. Verify OPENAI_API_KEY.';
        }

        return 'Failed to generate prompt specification: ' . $message;
    }

    /**
     * Extract the generated prompt from API response
     * 
     * @param array $response OpenAI API response
     * @return string Generated prompt text
     * @throws Exception If response format is unexpected
     */
    private function extractPromptFromResponse(array $response): string
    {
        if (empty($response['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response: no content generated');
        }
        
        $content = trim($response['choices'][0]['message']['content']);
        
        // Remove code blocks if present (sometimes LLMs wrap in ```markdown ... ```)
        $content = preg_replace('/^```(?:markdown)?\s*\n/m', '', $content);
        $content = preg_replace('/\n```\s*$/m', '', $content);
        
        return trim($content);
    }

    /**
     * Build metadata about applied guardrails
     * 
     * @param array $templates Loaded guardrail templates
     * @return array Guardrails metadata
     */
    private function buildGuardrailsMetadata(array $templates): array
    {
        $metadata = [];
        
        foreach ($templates as $key => $template) {
            $metadata[] = [
                'key' => $key,
                'title' => $template['title'] ?? $key,
                'mandatory' => $template['meta']['mandatory'] ?? false,
            ];
        }
        
        return $metadata;
    }

    /**
     * Get the system prompt with guardrails section inserted
     * 
     * @param array $templates Guardrail templates
     * @return string System prompt with guardrails
     */
    private function getSystemPromptWithGuardrails(array $templates): string
    {
        $basePrompt = $this->buildSystemPrompt();
        
        // Build guardrails placeholder text
        $guardrailsList = [];
        $sectionNumber = 6; // Start after Out-of-Scope
        
        foreach ($templates as $key => $template) {
            $title = $template['title'] ?? ucfirst(str_replace('_', ' ', $key));
            $guardrailsList[] = "## {$sectionNumber}. Guardrails — {$title}";
            $sectionNumber++;
        }
        
        $guardrailsSection = implode("\n", $guardrailsList);
        
        return str_replace('{GUARDRAILS_SECTION}', $guardrailsSection, $basePrompt);
    }
}
