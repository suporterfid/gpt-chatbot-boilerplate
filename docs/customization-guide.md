# Customization Guide - Enhanced GPT Chatbot Boilerplate

This guide covers extensive customization options for both Chat Completions API and Assistants API implementations, with practical examples and use cases.

## Table of Contents

- [API Selection & Configuration](#api-selection--configuration)
- [Chat Completions Customization](#chat-completions-customization)
- [Assistants API Customization](#assistants-api-customization)
- [UI/UX Customization](#uiux-customization)
- [File Upload Customization](#file-upload-customization)
- [Custom Function Calling](#custom-function-calling)
- [Advanced Integration Examples](#advanced-integration-examples)
- [Industry-Specific Configurations](#industry-specific-configurations)

## API Selection & Configuration

### Choosing Between APIs

**Use Chat Completions API when:**
- You need simple conversational AI
- Stateless interactions are sufficient
- You want lower latency and costs
- Simple question-answer scenarios

**Use Assistants API when:**
- You need persistent conversation context
- File processing capabilities are required
- Code execution/interpretation is needed
- Complex multi-turn conversations with tools
- Advanced reasoning capabilities are important

### Dynamic API Switching

```javascript
// Allow users to switch between APIs dynamically
class AdaptiveChatBot {
    constructor(container, options) {
        this.chatbot = null;
        this.currentAPI = options.apiType || 'chat';
        this.initialize(container, options);
    }
    
    switchAPI(newAPIType) {
        if (this.currentAPI === newAPIType) return;
        
        // Save current conversation
        const messages = this.chatbot.messages;
        
        // Destroy current instance
        this.chatbot.destroy();
        
        // Create new instance with different API
        this.currentAPI = newAPIType;
        const newOptions = { ...this.options, apiType: newAPIType };
        
        this.chatbot = ChatBot.init(this.container, newOptions);
        
        // Restore messages (convert format if needed)
        this.restoreMessages(messages, newAPIType);
    }
    
    restoreMessages(messages, targetAPI) {
        if (targetAPI === 'assistants') {
            // Convert to thread format if needed
            messages.forEach(msg => {
                this.chatbot.addMessage(msg);
            });
        } else {
            // Standard format for chat completions
            this.chatbot.messages = messages;
        }
    }
}

// Usage
const adaptiveBot = new AdaptiveChatBot('#chat-container', {
    apiType: 'chat'
});

// Switch to Assistants API when file upload is needed
document.getElementById('enable-files').addEventListener('click', () => {
    adaptiveBot.switchAPI('assistants');
});
```

## Chat Completions Customization

### Custom System Prompts

```php
// config.php - Dynamic system prompts
'chat' => [
    'system_message_templates' => [
        'support' => 'You are a helpful customer support representative for {company_name}. Be professional and empathetic.',
        'sales' => 'You are a knowledgeable sales assistant for {company_name}. Focus on helping customers find the right products.',
        'technical' => 'You are a technical expert specializing in {domain}. Provide accurate, detailed technical information.',
        'casual' => 'You are a friendly AI assistant. Be conversational and helpful while maintaining a casual tone.'
    ]
]
```

```javascript
// Frontend - Dynamic system prompt selection
ChatBot.init({
    apiType: 'chat',
    systemPromptType: 'support',
    companyName: 'Acme Corp',
    
    // Custom preprocessing
    preprocessMessage: function(message, context) {
        // Add context-aware information
        const userType = this.getUserType();
        const timeOfDay = new Date().getHours();
        
        return {
            message: message,
            context: {
                user_type: userType,
                time_of_day: timeOfDay,
                page_url: window.location.href
            }
        };
    }
});
```

### Conversation Memory & Context

```javascript
// Advanced conversation management
class ContextualChatBot extends ChatBot {
    constructor(container, options) {
        super(container, options);
        this.conversationContext = {
            userPreferences: {},
            sessionData: {},
            previousTopics: []
        };
    }
    
    enhanceMessage(message) {
        // Add contextual information
        const enhancedMessage = {
            content: message,
            context: {
                previous_topics: this.conversationContext.previousTopics,
                user_preferences: this.conversationContext.userPreferences,
                session_data: this.conversationContext.sessionData,
                timestamp: new Date().toISOString(),
                page_context: this.getPageContext()
            }
        };
        
        return enhancedMessage;
    }
    
    getPageContext() {
        return {
            url: window.location.href,
            title: document.title,
            page_type: document.body.dataset.pageType,
            user_scroll_position: window.scrollY
        };
    }
    
    updateContext(message, response) {
        // Extract topics and preferences
        this.extractTopics(message, response);
        this.updateUserPreferences(message, response);
    }
}
```

### Custom Response Processing

```php
// includes/CustomChatHandler.php
class CustomChatHandler extends ChatHandler {
    public function processResponse($response, $context) {
        // Custom response enhancement
        $response = $this->addPersonalization($response, $context);
        $response = $this->addRecommendations($response, $context);
        $response = $this->formatForIndustry($response, $context);
        
        return parent::processResponse($response, $context);
    }
    
    private function addPersonalization($response, $context) {
        // Add user-specific personalization
        if (isset($context['user_name'])) {
            $response = str_replace('[USER]', $context['user_name'], $response);
        }
        
        return $response;
    }
    
    private function addRecommendations($response, $context) {
        // Add contextual recommendations
        if ($this->shouldAddRecommendations($response, $context)) {
            $recommendations = $this->generateRecommendations($context);
            $response .= "\n\nBased on your interests, you might also like:\n" . $recommendations;
        }
        
        return $response;
    }
}
```

## Assistants API Customization

### Custom Assistant Creation

```php
// includes/CustomAssistantManager.php
class CustomAssistantManager extends AssistantManager {
    public function createDomainSpecificAssistant($domain, $expertise) {
        $assistantConfig = [
            'name' => ucfirst($domain) . ' Expert Assistant',
            'description' => "Specialized AI assistant for {$domain} with expertise in {$expertise}",
            'instructions' => $this->buildDomainInstructions($domain, $expertise),
            'model' => $this->selectOptimalModel($domain),
            'tools' => $this->buildDomainTools($domain)
        ];
        
        return $this->openAIClient->createAssistant($assistantConfig);
    }
    
    private function buildDomainInstructions($domain, $expertise) {
        $templates = [
            'ecommerce' => "You are an expert e-commerce assistant specialized in {$expertise}. Help users with product recommendations, order issues, and shopping decisions.",
            'healthcare' => "You are a healthcare information assistant with expertise in {$expertise}. Provide accurate medical information while emphasizing the need for professional consultation.",
            'education' => "You are an educational assistant specializing in {$expertise}. Help students learn through clear explanations, examples, and guided problem-solving.",
            'finance' => "You are a financial advisor assistant with expertise in {$expertise}. Provide information about financial concepts while noting that this is not personalized financial advice."
        ];
        
        return str_replace('{$expertise}', $expertise, $templates[$domain] ?? $templates['default']);
    }
    
    private function buildDomainTools($domain) {
        $commonTools = [
            ['type' => 'code_interpreter'],
            ['type' => 'file_search']
        ];
        
        $domainSpecificTools = [
            'ecommerce' => [
                ['type' => 'function', 'function' => $this->getProductSearchFunction()],
                ['type' => 'function', 'function' => $this->getPriceComparisonFunction()]
            ],
            'healthcare' => [
                ['type' => 'function', 'function' => $this->getSymptomCheckerFunction()],
                ['type' => 'function', 'function' => $this->getDrugInteractionFunction()]
            ],
            'education' => [
                ['type' => 'function', 'function' => $this->getQuizGeneratorFunction()],
                ['type' => 'function', 'function' => $this->getProgressTrackerFunction()]
            ]
        ];
        
        return array_merge($commonTools, $domainSpecificTools[$domain] ?? []);
    }
}
```

### Advanced Thread Management

```php
// includes/AdvancedThreadManager.php
class AdvancedThreadManager extends ThreadManager {
    public function createContextualThread($conversationId, $userContext) {
        $metadata = [
            'conversation_id' => $conversationId,
            'user_context' => json_encode($userContext),
            'created_at' => time(),
            'domain' => $userContext['domain'] ?? 'general',
            'user_type' => $userContext['user_type'] ?? 'visitor'
        ];
        
        // Add domain-specific initialization
        $initMessages = $this->getDomainInitializationMessages($userContext['domain'] ?? 'general');
        
        $thread = $this->openAIClient->createThread($metadata);
        
        // Add initialization messages
        foreach ($initMessages as $message) {
            $this->openAIClient->addMessageToThread($thread['id'], $message);
        }
        
        return $thread['id'];
    }
    
    public function getThreadAnalytics($threadId) {
        $messages = $this->openAIClient->getThreadMessages($threadId);
        
        return [
            'message_count' => count($messages['data']),
            'user_messages' => $this->countMessagesByRole($messages['data'], 'user'),
            'assistant_messages' => $this->countMessagesByRole($messages['data'], 'assistant'),
            'tools_used' => $this->extractToolsUsed($messages['data']),
            'conversation_topics' => $this->extractTopics($messages['data']),
            'sentiment_analysis' => $this->analyzeSentiment($messages['data'])
        ];
    }
}
```

### Custom Function Definitions

```php
// includes/CustomFunctions.php
class CustomFunctions {
    public static function getEcommerceFunctions() {
        return [
            [
                'name' => 'search_products',
                'description' => 'Search for products in the catalog',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query'],
                        'category' => ['type' => 'string', 'description' => 'Product category'],
                        'price_range' => [
                            'type' => 'object',
                            'properties' => [
                                'min' => ['type' => 'number'],
                                'max' => ['type' => 'number']
                            ]
                        ]
                    ],
                    'required' => ['query']
                ]
            ],
            [
                'name' => 'get_product_details',
                'description' => 'Get detailed information about a product',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'string', 'description' => 'Product ID']
                    ],
                    'required' => ['product_id']
                ]
            ],
            [
                'name' => 'check_inventory',
                'description' => 'Check product availability and stock levels',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'string', 'description' => 'Product ID'],
                        'location' => ['type' => 'string', 'description' => 'Store location or region']
                    ],
                    'required' => ['product_id']
                ]
            ]
        ];
    }
    
    public static function getHealthcareFunctions() {
        return [
            [
                'name' => 'symptom_checker',
                'description' => 'Provide information about symptoms (educational only)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'symptoms' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'List of symptoms'
                        ],
                        'age_group' => ['type' => 'string', 'description' => 'Age group (child, adult, senior)'],
                        'severity' => ['type' => 'string', 'enum' => ['mild', 'moderate', 'severe']]
                    ],
                    'required' => ['symptoms']
                ]
            ],
            [
                'name' => 'find_healthcare_providers',
                'description' => 'Find healthcare providers in the area',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'specialty' => ['type' => 'string', 'description' => 'Medical specialty'],
                        'location' => ['type' => 'string', 'description' => 'City, state or zip code'],
                        'insurance' => ['type' => 'string', 'description' => 'Insurance provider']
                    ],
                    'required' => ['location']
                ]
            ]
        ];
    }
}
```

## UI/UX Customization

### Theme System

```javascript
// Advanced theme system with multiple presets
const ThemeManager = {
    themes: {
        corporate: {
            primaryColor: '#1a365d',
            backgroundColor: '#f7fafc',
            surfaceColor: '#ffffff',
            textColor: '#2d3748',
            mutedColor: '#718096',
            fontFamily: 'Inter, sans-serif',
            borderRadius: '8px',
            shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
        },
        medical: {
            primaryColor: '#065f46',
            backgroundColor: '#ecfdf5',
            surfaceColor: '#ffffff',
            textColor: '#1f2937',
            mutedColor: '#6b7280',
            fontFamily: 'system-ui, sans-serif',
            borderRadius: '12px',
            shadow: '0 1px 3px 0 rgba(0, 0, 0, 0.1)'
        },
        ecommerce: {
            primaryColor: '#7c2d12',
            backgroundColor: '#fef7ff',
            surfaceColor: '#ffffff',
            textColor: '#1c1917',
            mutedColor: '#78716c',
            fontFamily: '"Helvetica Neue", Arial, sans-serif',
            borderRadius: '6px',
            shadow: '0 2px 8px rgba(0, 0, 0, 0.15)'
        },
        education: {
            primaryColor: '#1e40af',
            backgroundColor: '#eff6ff',
            surfaceColor: '#ffffff',
            textColor: '#1e293b',
            mutedColor: '#64748b',
            fontFamily: '"Source Sans Pro", sans-serif',
            borderRadius: '10px',
            shadow: '0 4px 6px -1px rgba(59, 130, 246, 0.1)'
        }
    },
    
    applyTheme: function(themeName, customizations = {}) {
        const theme = { ...this.themes[themeName], ...customizations };
        
        // Apply CSS custom properties
        const root = document.documentElement;
        Object.keys(theme).forEach(property => {
            root.style.setProperty(`--chatbot-${property.replace(/([A-Z])/g, '-$1').toLowerCase()}`, theme[property]);
        });
        
        return theme;
    },
    
    createCustomTheme: function(baseTheme, overrides) {
        return { ...this.themes[baseTheme], ...overrides };
    }
};

// Usage
const customTheme = ThemeManager.createCustomTheme('corporate', {
    primaryColor: '#your-brand-color',
    fontFamily: 'Your Brand Font'
});

ChatBot.init({
    theme: customTheme
});
```

### Responsive Design Customization

```css
/* Advanced responsive design */
@media (max-width: 768px) {
    .chatbot-widget.enhanced {
        --chatbot-width: 100vw;
        --chatbot-height: 100vh;
        --chatbot-border-radius: 0;
    }
    
    .chatbot-floating {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
    }
    
    .chatbot-container {
        border-radius: 0 !important;
        height: 100vh !important;
        width: 100vw !important;
    }
}

@media (max-width: 480px) {
    .chatbot-message-bubble {
        max-width: 90%;
        font-size: 14px;
    }
    
    .chatbot-input {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .chatbot-widget.enhanced {
        --chatbot-background-color: #1f2937;
        --chatbot-surface-color: #374151;
        --chatbot-text-color: #f9fafb;
        --chatbot-muted-color: #9ca3af;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .chatbot-widget.enhanced {
        --chatbot-border-radius: 4px;
        --chatbot-shadow: 0 0 0 2px currentColor;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .chatbot-widget.enhanced * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

### Custom Message Rendering

```javascript
// Custom message renderer for different content types
class CustomMessageRenderer {
    constructor(chatbot) {
        this.chatbot = chatbot;
        this.renderers = new Map();
        this.setupDefaultRenderers();
    }
    
    setupDefaultRenderers() {
        this.addRenderer('text', this.renderTextMessage);
        this.addRenderer('image', this.renderImageMessage);
        this.addRenderer('code', this.renderCodeMessage);
        this.addRenderer('table', this.renderTableMessage);
        this.addRenderer('chart', this.renderChartMessage);
    }
    
    addRenderer(type, renderer) {
        this.renderers.set(type, renderer);
    }
    
    renderMessage(message) {
        const contentType = this.detectContentType(message.content);
        const renderer = this.renderers.get(contentType) || this.renderers.get('text');
        
        return renderer.call(this, message);
    }
    
    renderImageMessage(message) {
        const imageRegex = /!\[([^\]]*)\]\(([^)]+)\)/g;
        let content = message.content;
        
        content = content.replace(imageRegex, (match, alt, src) => {
            return `<div class="message-image">
                <img src="${src}" alt="${alt}" loading="lazy" />
                <span class="image-caption">${alt}</span>
            </div>`;
        });
        
        return content;
    }
    
    renderCodeMessage(message) {
        const codeBlocks = message.content.match(/```([\\s\\S]*?)```/g);
        
        if (!codeBlocks) return message.content;
        
        let content = message.content;
        codeBlocks.forEach(block => {
            const code = block.replace(/```/g, '');
            const highlighted = this.highlightCode(code);
            const codeHtml = `
                <div class="code-block">
                    <div class="code-header">
                        <span class="code-language">${this.detectLanguage(code)}</span>
                        <button class="copy-code" onclick="navigator.clipboard.writeText(\`${code}\`)">Copy</button>
                    </div>
                    <pre><code>${highlighted}</code></pre>
                </div>
            `;
            content = content.replace(block, codeHtml);
        });
        
        return content;
    }
    
    renderTableMessage(message) {
        // Detect and render markdown tables
        const tableRegex = /(\|.*\|[\\r\\n]+\|.*\|[\\r\\n]+(\|.*\|[\\r\\n]*)*)/g;
        
        return message.content.replace(tableRegex, (match) => {
            const rows = match.trim().split('\\n');
            const headers = rows[0].split('|').map(h => h.trim()).filter(h => h);
            const dataRows = rows.slice(2).map(row => 
                row.split('|').map(cell => cell.trim()).filter(cell => cell)
            );
            
            let tableHtml = '<div class="message-table"><table><thead><tr>';
            headers.forEach(header => {
                tableHtml += `<th>${header}</th>`;
            });
            tableHtml += '</tr></thead><tbody>';
            
            dataRows.forEach(row => {
                tableHtml += '<tr>';
                row.forEach(cell => {
                    tableHtml += `<td>${cell}</td>`;
                });
                tableHtml += '</tr>';
            });
            
            tableHtml += '</tbody></table></div>';
            return tableHtml;
        });
    }
}

// Integration with chatbot
ChatBot.init({
    customMessageRenderer: new CustomMessageRenderer(),
    
    // Custom message processing
    onMessage: function(message) {
        if (message.role === 'assistant') {
            // Apply custom rendering
            const rendered = this.customMessageRenderer.renderMessage(message);
            // Update the message element
            this.updateMessageContent(message.element, rendered);
        }
    }
});
```

## File Upload Customization

### Advanced File Handling

```javascript
// Enhanced file upload with preview and validation
class AdvancedFileUpload {
    constructor(chatbot, options = {}) {
        this.chatbot = chatbot;
        this.options = {
            maxFiles: 5,
            maxTotalSize: 50 * 1024 * 1024, // 50MB
            allowedTypes: ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png'],
            previewEnabled: true,
            compressionEnabled: false,
            ...options
        };
        
        this.uploadQueue = [];
        this.setupFileHandling();
    }
    
    setupFileHandling() {
        // Drag and drop support
        this.setupDragAndDrop();
        
        // File compression if enabled
        if (this.options.compressionEnabled) {
            this.setupCompression();
        }
        
        // Preview generation
        if (this.options.previewEnabled) {
            this.setupPreviewGeneration();
        }
    }
    
    setupDragAndDrop() {
        const dropZone = this.chatbot.widget;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, this.handleDragEvent.bind(this), false);
        });
    }
    
    handleDragEvent(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const dropZone = this.chatbot.widget;
        
        if (e.type === 'dragenter' || e.type === 'dragover') {
            dropZone.classList.add('drag-over');
        } else if (e.type === 'dragleave') {
            dropZone.classList.remove('drag-over');
        } else if (e.type === 'drop') {
            dropZone.classList.remove('drag-over');
            const files = Array.from(e.dataTransfer.files);
            this.handleFiles(files);
        }
    }
    
    async handleFiles(files) {
        // Validate total files and size
        if (this.uploadQueue.length + files.length > this.options.maxFiles) {
            this.chatbot.showError(`Maximum ${this.options.maxFiles} files allowed`);
            return;
        }
        
        const totalSize = this.getTotalSize([...this.uploadQueue, ...files]);
        if (totalSize > this.options.maxTotalSize) {
            this.chatbot.showError(`Total file size exceeds ${this.formatFileSize(this.options.maxTotalSize)}`);
            return;
        }
        
        // Process each file
        for (const file of files) {
            if (this.validateFile(file)) {
                await this.processFile(file);
            }
        }
        
        this.updateFilePreview();
    }
    
    async processFile(file) {
        const fileData = {
            id: this.generateFileId(),
            file: file,
            name: file.name,
            size: file.size,
            type: file.type,
            preview: null,
            processed: false
        };
        
        // Generate preview
        if (this.options.previewEnabled) {
            fileData.preview = await this.generatePreview(file);
        }
        
        // Compress if enabled
        if (this.options.compressionEnabled && this.isCompressible(file)) {
            fileData.file = await this.compressFile(file);
            fileData.compressed = true;
        }
        
        fileData.processed = true;
        this.uploadQueue.push(fileData);
    }
    
    async generatePreview(file) {
        return new Promise((resolve) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        // Generate thumbnail
                        const maxSize = 100;
                        const ratio = Math.min(maxSize / img.width, maxSize / img.height);
                        canvas.width = img.width * ratio;
                        canvas.height = img.height * ratio;
                        
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        resolve(canvas.toDataURL());
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                // Generate file type icon
                resolve(this.getFileTypeIcon(file.type));
            }
        });
    }
    
    async compressFile(file) {
        if (!file.type.startsWith('image/')) return file;
        
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Calculate compressed dimensions
                    const maxDimension = 1200;
                    const ratio = Math.min(maxDimension / img.width, maxDimension / img.height);
                    
                    if (ratio >= 1) {
                        resolve(file); // No compression needed
                        return;
                    }
                    
                    canvas.width = img.width * ratio;
                    canvas.height = img.height * ratio;
                    
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    
                    canvas.toBlob((blob) => {
                        const compressedFile = new File([blob], file.name, {
                            type: file.type,
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, file.type, 0.8);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }
    
    updateFilePreview() {
        const previewContainer = this.chatbot.widget.querySelector('.file-preview-list');
        if (!previewContainer) return;
        
        previewContainer.innerHTML = this.uploadQueue.map(fileData => `
            <div class="file-preview-item ${fileData.processed ? 'processed' : 'processing'}" data-file-id="${fileData.id}">
                <div class="file-preview-image">
                    ${fileData.preview ? `<img src="${fileData.preview}" alt="${fileData.name}">` : '<div class="file-icon">📄</div>'}
                </div>
                <div class="file-info">
                    <span class="file-name">${fileData.name}</span>
                    <span class="file-size">${this.formatFileSize(fileData.size)}</span>
                    ${fileData.compressed ? '<span class="compressed-indicator">Compressed</span>' : ''}
                </div>
                <button class="file-remove" data-file-id="${fileData.id}">✕</button>
            </div>
        `).join('');
    }
}

// Usage
ChatBot.init({
    enableFileUpload: true,
    fileUploadHandler: new AdvancedFileUpload(this, {
        maxFiles: 10,
        maxTotalSize: 100 * 1024 * 1024, // 100MB
        compressionEnabled: true,
        previewEnabled: true
    })
});
```

### File Processing Callbacks

```javascript
// Advanced file processing with callbacks
ChatBot.init({
    enableFileUpload: true,
    
    // File validation callback
    validateFile: function(file) {
        // Custom validation logic
        if (file.name.includes('confidential')) {
            this.showError('Confidential files are not allowed');
            return false;
        }
        
        if (file.type === 'application/pdf' && file.size > 5 * 1024 * 1024) {
            this.showError('PDF files must be smaller than 5MB');
            return false;
        }
        
        return true;
    },
    
    // File preprocessing callback
    preprocessFile: async function(file) {
        // Extract text from documents for better context
        if (file.type === 'application/pdf') {
            const text = await this.extractPDFText(file);
            return { ...file, extractedText: text };
        }
        
        return file;
    },
    
    // File upload progress callback
    onFileUploadProgress: function(file, progress) {
        const progressBar = document.querySelector(`[data-file-id="${file.id}"] .progress-bar`);
        if (progressBar) {
            progressBar.style.width = `${progress}%`;
        }
    },
    
    // File upload complete callback
    onFileUploadComplete: function(file, response) {
        console.log('File uploaded:', file.name, response);
        
        // Update UI to show upload success
        const fileElement = document.querySelector(`[data-file-id="${file.id}"]`);
        if (fileElement) {
            fileElement.classList.add('upload-complete');
        }
    }
});
```

## Custom Function Calling

### E-commerce Functions

```php
// E-commerce specific function implementations
class EcommerceFunctions {
    public function searchProducts($query, $category = null, $priceRange = null) {
        // Connect to product database
        $db = $this->getProductDatabase();
        
        $sql = "SELECT * FROM products WHERE title LIKE ? OR description LIKE ?";
        $params = ["%{$query}%", "%{$query}%"];
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($priceRange) {
            $sql .= " AND price BETWEEN ? AND ?";
            $params[] = $priceRange['min'];
            $params[] = $priceRange['max'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'products' => array_slice($products, 0, 10), // Limit to 10 results
            'total_found' => count($products),
            'search_query' => $query
        ];
    }
    
    public function getProductDetails($productId) {
        $db = $this->getProductDatabase();
        
        $stmt = $db->prepare("
            SELECT p.*, 
                   AVG(r.rating) as average_rating,
                   COUNT(r.id) as review_count,
                   i.stock_quantity
            FROM products p
            LEFT JOIN reviews r ON p.id = r.product_id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['error' => 'Product not found'];
        }
        
        // Get related products
        $relatedProducts = $this->getRelatedProducts($product['category'], $productId);
        
        return [
            'product' => $product,
            'related_products' => $relatedProducts,
            'in_stock' => $product['stock_quantity'] > 0
        ];
    }
    
    public function checkInventory($productId, $location = null) {
        $db = $this->getProductDatabase();
        
        if ($location) {
            $stmt = $db->prepare("
                SELECT i.*, s.name as store_name, s.address
                FROM inventory i
                JOIN stores s ON i.store_id = s.id
                WHERE i.product_id = ? AND s.city = ?
            ");
            $stmt->execute([$productId, $location]);
        } else {
            $stmt = $db->prepare("
                SELECT SUM(stock_quantity) as total_stock,
                       COUNT(DISTINCT store_id) as store_count
                FROM inventory
                WHERE product_id = ?
            ");
            $stmt->execute([$productId]);
        }
        
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'product_id' => $productId,
            'inventory' => $inventory,
            'available' => !empty($inventory) && array_sum(array_column($inventory, 'stock_quantity')) > 0
        ];
    }
}
```

### Healthcare Information Functions

```php
// Healthcare information functions (educational only)
class HealthcareFunctions {
    private $symptomsDatabase;
    private $providersAPI;
    
    public function symptomChecker($symptoms, $ageGroup = 'adult', $severity = 'mild') {
        // This is for educational purposes only - always recommend professional consultation
        $possibleConditions = $this->analyzeSymptomsEducational($symptoms, $ageGroup);
        
        $response = [
            'disclaimer' => 'This information is for educational purposes only and does not constitute medical advice. Please consult with a healthcare professional for proper diagnosis and treatment.',
            'symptoms_analyzed' => $symptoms,
            'age_group' => $ageGroup,
            'severity' => $severity,
            'educational_information' => $possibleConditions,
            'recommendations' => [
                'general' => 'Monitor symptoms and rest as needed',
                'when_to_seek_care' => $this->getSeekCareGuidance($severity),
                'emergency_signs' => $this->getEmergencySignsForSymptoms($symptoms)
            ]
        ];
        
        // Add severity-specific recommendations
        if ($severity === 'severe') {
            $response['urgent_notice'] = 'Severe symptoms may require immediate medical attention. Consider contacting a healthcare provider or emergency services.';
        }
        
        return $response;
    }
    
    public function findHealthcareProviders($location, $specialty = null, $insurance = null) {
        // Use healthcare provider API (example)
        $searchParams = [
            'location' => $location,
            'radius' => 25, // miles
            'limit' => 10
        ];
        
        if ($specialty) {
            $searchParams['specialty'] = $specialty;
        }
        
        if ($insurance) {
            $searchParams['insurance'] = $insurance;
        }
        
        $providers = $this->searchProviders($searchParams);
        
        return [
            'location' => $location,
            'specialty' => $specialty,
            'providers' => array_map(function($provider) {
                return [
                    'name' => $provider['name'],
                    'specialty' => $provider['specialty'],
                    'address' => $provider['address'],
                    'phone' => $provider['phone'],
                    'rating' => $provider['rating'],
                    'accepts_new_patients' => $provider['accepting_patients'],
                    'insurance_accepted' => $provider['insurance_plans']
                ];
            }, $providers)
        ];
    }
    
    private function analyzeSymptomsEducational($symptoms, $ageGroup) {
        // Educational symptom analysis - not diagnostic
        $commonConditions = [];
        
        // Simple symptom matching for educational purposes
        foreach ($symptoms as $symptom) {
            $relatedConditions = $this->getEducationalConditionsForSymptom($symptom, $ageGroup);
            $commonConditions = array_merge($commonConditions, $relatedConditions);
        }
        
        // Return most common educational matches
        $conditionCounts = array_count_values($commonConditions);
        arsort($conditionCounts);
        
        return array_slice(array_keys($conditionCounts), 0, 5);
    }
}
```

### Educational Functions

```php
// Educational assistant functions
class EducationFunctions {
    public function generateQuiz($topic, $difficulty = 'medium', $questionCount = 5) {
        $questions = $this->getQuestionsFromDatabase($topic, $difficulty, $questionCount);
        
        return [
            'topic' => $topic,
            'difficulty' => $difficulty,
            'questions' => array_map(function($q, $index) {
                return [
                    'id' => $index + 1,
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'type' => $q['type'] // multiple_choice, true_false, short_answer
                ];
            }, $questions, array_keys($questions)),
            'instructions' => 'Answer all questions to receive feedback and explanations.'
        ];
    }
    
    public function checkQuizAnswers($quizId, $answers) {
        $quiz = $this->getQuizById($quizId);
        $results = [];
        $score = 0;
        
        foreach ($answers as $questionId => $answer) {
            $question = $quiz['questions'][$questionId - 1];
            $correct = $this->isAnswerCorrect($question, $answer);
            
            if ($correct) {
                $score++;
            }
            
            $results[] = [
                'question_id' => $questionId,
                'your_answer' => $answer,
                'correct_answer' => $question['correct_answer'],
                'is_correct' => $correct,
                'explanation' => $question['explanation']
            ];
        }
        
        return [
            'quiz_id' => $quizId,
            'score' => $score,
            'total_questions' => count($quiz['questions']),
            'percentage' => round(($score / count($quiz['questions'])) * 100),
            'results' => $results,
            'performance_level' => $this->getPerformanceLevel($score, count($quiz['questions'])),
            'recommendations' => $this->getStudyRecommendations($quiz['topic'], $score, count($quiz['questions']))
        ];
    }
    
    public function trackProgress($userId, $topic, $activity) {
        // Track learning progress
        $progress = $this->getUserProgress($userId, $topic);
        
        $progress['activities'][] = [
            'type' => $activity['type'],
            'timestamp' => time(),
            'score' => $activity['score'] ?? null,
            'duration' => $activity['duration'] ?? null
        ];
        
        // Update mastery level
        $progress['mastery_level'] = $this->calculateMasteryLevel($progress['activities']);
        $progress['last_activity'] = time();
        
        $this->saveUserProgress($userId, $topic, $progress);
        
        return [
            'topic' => $topic,
            'mastery_level' => $progress['mastery_level'],
            'total_activities' => count($progress['activities']),
            'achievements' => $this->checkAchievements($progress),
            'next_recommendations' => $this->getNextLearningRecommendations($topic, $progress['mastery_level'])
        ];
    }
}
```

## Advanced Integration Examples

### Multi-Domain Chatbot

```javascript
// Multi-domain chatbot that adapts based on context
class MultiDomainChatBot {
    constructor(container, options) {
        this.domains = {
            ecommerce: {
                apiType: 'assistants',
                assistantId: 'asst_ecommerce_123',
                functions: ['search_products', 'get_product_details', 'check_inventory'],
                theme: 'ecommerce',
                welcomeMessage: 'Hello! I can help you find products, check availability, and answer questions about your order.'
            },
            support: {
                apiType: 'chat',
                systemMessage: 'You are a helpful customer support representative.',
                theme: 'corporate',
                welcomeMessage: 'Hi! I\'m here to help with any questions or issues you might have.'
            },
            education: {
                apiType: 'assistants',
                assistantId: 'asst_education_456',
                functions: ['generate_quiz', 'track_progress', 'explain_concept'],
                theme: 'education',
                welcomeMessage: 'Welcome! I\'m your learning assistant. I can create quizzes, explain concepts, and track your progress.'
            }
        };
        
        this.currentDomain = this.detectDomain();
        this.initializeDomainBot(container, options);
    }
    
    detectDomain() {
        // Detect domain based on URL, page content, or context
        const url = window.location.href;
        const pageType = document.body.dataset.pageType;
        
        if (url.includes('/shop') || url.includes('/products') || pageType === 'product') {
            return 'ecommerce';
        } else if (url.includes('/learn') || url.includes('/course') || pageType === 'education') {
            return 'education';
        } else {
            return 'support';
        }
    }
    
    initializeDomainBot(container, options) {
        const domainConfig = this.domains[this.currentDomain];
        const theme = ThemeManager.themes[domainConfig.theme] || ThemeManager.themes.corporate;
        
        const botOptions = {
            ...options,
            apiType: domainConfig.apiType,
            theme: theme,
            assistant: {
                ...options.assistant,
                welcomeMessage: domainConfig.welcomeMessage
            }
        };
        
        // Add domain-specific configuration
        if (domainConfig.assistantId) {
            botOptions.assistantConfig = {
                assistantId: domainConfig.assistantId,
                enableTools: true,
                customFunctions: domainConfig.functions
            };
        }
        
        if (domainConfig.systemMessage) {
            botOptions.systemMessage = domainConfig.systemMessage;
        }
        
        this.chatbot = ChatBot.init(container, botOptions);
    }
    
    switchDomain(newDomain) {
        if (this.domains[newDomain] && newDomain !== this.currentDomain) {
            this.chatbot.destroy();
            this.currentDomain = newDomain;
            this.initializeDomainBot(this.container, this.originalOptions);
        }
    }
}

// Usage
const multiDomainBot = new MultiDomainChatBot('#chat-container', {
    mode: 'floating',
    position: 'bottom-right'
});

// Switch domains programmatically
document.addEventListener('DOMContentLoaded', function() {
    // Auto-switch based on navigation
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-page-type') {
                const newDomain = multiDomainBot.detectDomain();
                multiDomainBot.switchDomain(newDomain);
            }
        });
    });
    
    observer.observe(document.body, { attributes: true });
});
```

### A/B Testing Integration

```javascript
// A/B testing for chatbot variations
class ABTestingChatBot {
    constructor(container, options) {
        this.experiments = options.experiments || {};
        this.userId = this.getUserId();
        this.activeExperiments = this.getActiveExperiments();
        
        // Apply experiment variations
        const experimentalOptions = this.applyExperiments(options);
        
        this.chatbot = ChatBot.init(container, experimentalOptions);
        this.trackExperiments();
    }
    
    getActiveExperiments() {
        const active = {};
        
        for (const [experimentName, experiment] of Object.entries(this.experiments)) {
            if (this.shouldParticipateInExperiment(experimentName, experiment)) {
                const variation = this.getVariationForUser(experimentName, experiment);
                active[experimentName] = variation;
            }
        }
        
        return active;
    }
    
    shouldParticipateInExperiment(experimentName, experiment) {
        // Check if user should participate based on targeting criteria
        if (experiment.enabled === false) return false;
        if (experiment.traffic && Math.random() > experiment.traffic) return false;
        
        // Check targeting criteria
        if (experiment.targeting) {
            return this.matchesTargeting(experiment.targeting);
        }
        
        return true;
    }
    
    getVariationForUser(experimentName, experiment) {
        // Consistent variation assignment based on user ID
        const hash = this.hashString(this.userId + experimentName);
        const variationIndex = hash % experiment.variations.length;
        return experiment.variations[variationIndex];
    }
    
    applyExperiments(baseOptions) {
        let options = { ...baseOptions };
        
        for (const [experimentName, variation] of Object.entries(this.activeExperiments)) {
            switch (experimentName) {
                case 'welcome_message_test':
                    options.assistant.welcomeMessage = variation.welcomeMessage;
                    break;
                    
                case 'theme_color_test':
                    options.theme.primaryColor = variation.primaryColor;
                    break;
                    
                case 'api_type_test':
                    options.apiType = variation.apiType;
                    break;
                    
                case 'position_test':
                    options.position = variation.position;
                    break;
            }
        }
        
        return options;
    }
    
    trackExperiments() {
        // Track experiment participation
        for (const [experimentName, variation] of Object.entries(this.activeExperiments)) {
            this.trackEvent('experiment_participation', {
                experiment: experimentName,
                variation: variation.name,
                user_id: this.userId
            });
        }
        
        // Track chatbot interactions for experiment analysis
        this.chatbot.options.onMessage = (message) => {
            this.trackEvent('chatbot_message', {
                role: message.role,
                experiments: this.activeExperiments,
                user_id: this.userId
            });
        };
    }
    
    trackEvent(eventName, properties) {
        // Send to analytics platform
        if (window.analytics) {
            window.analytics.track(eventName, properties);
        }
        
        // Send to your own analytics endpoint
        fetch('/api/analytics/track', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                event: eventName,
                properties: properties,
                timestamp: Date.now()
            })
        }).catch(console.error);
    }
}

// Experiment configuration
const experiments = {
    welcome_message_test: {
        enabled: true,
        traffic: 0.5, // 50% of users
        variations: [
            { name: 'control', welcomeMessage: 'Hello! How can I help you today?' },
            { name: 'friendly', welcomeMessage: 'Hi there! I\'m excited to help you out! What can I do for you?' },
            { name: 'professional', welcomeMessage: 'Good day. I\'m here to assist you with your inquiries. How may I help?' }
        ]
    },
    
    theme_color_test: {
        enabled: true,
        traffic: 0.3, // 30% of users
        targeting: {
            page_type: ['product', 'category'], // Only on product pages
            user_type: ['new_visitor'] // Only new visitors
        },
        variations: [
            { name: 'blue', primaryColor: '#1FB8CD' },
            { name: 'green', primaryColor: '#10B981' },
            { name: 'purple', primaryColor: '#8B5CF6' }
        ]
    },
    
    api_type_test: {
        enabled: false, // Currently disabled
        traffic: 0.1, // 10% of users when enabled
        variations: [
            { name: 'chat', apiType: 'chat' },
            { name: 'assistants', apiType: 'assistants' }
        ]
    }
};

// Usage
const abTestBot = new ABTestingChatBot('#chat-container', {
    mode: 'floating',
    experiments: experiments,
    // ... other options
});
```

## Industry-Specific Configurations

### E-commerce Configuration

```javascript
// Complete e-commerce chatbot setup
const ecommerceChatBot = ChatBot.init({
    apiType: 'assistants',
    mode: 'floating',
    position: 'bottom-right',
    
    // E-commerce specific theme
    theme: ThemeManager.themes.ecommerce,
    
    // Assistant configuration
    assistantConfig: {
        assistantId: 'asst_ecommerce_specialist',
        enableTools: true,
        enableFileSearch: true,
        customFunctions: [
            'search_products',
            'get_product_details', 
            'check_inventory',
            'compare_products',
            'get_recommendations',
            'track_order'
        ]
    },
    
    // E-commerce specific settings
    assistant: {
        name: 'Shopping Assistant',
        welcomeMessage: 'Hi! I can help you find products, compare options, check availability, and track your orders. What are you looking for today?',
        avatar: '/assets/shopping-assistant-avatar.png'
    },
    
    // Enable file upload for product images/receipts
    enableFileUpload: true,
    allowedFileTypes: ['jpg', 'png', 'pdf', 'txt'],
    
    // Custom message preprocessing for product context
    preprocessMessage: function(message, context) {
        // Add current page product information
        const productId = document.querySelector('[data-product-id]')?.dataset.productId;
        const category = document.body.dataset.category;
        const cart = this.getCartContents();
        
        return {
            message: message,
            context: {
                current_product: productId,
                current_category: category,
                cart_items: cart,
                user_history: this.getUserPurchaseHistory(),
                page_type: document.body.dataset.pageType
            }
        };
    },
    
    // E-commerce specific callbacks
    onMessage: function(message) {
        if (message.role === 'assistant' && message.content.includes('product:')) {
            // Extract product recommendations and enhance UI
            this.enhanceProductRecommendations(message);
        }
    },
    
    onToolCall: function(toolData) {
        if (toolData.name === 'search_products') {
            // Show loading state for product search
            this.showProductSearchLoading();
        }
    }
});

// Add e-commerce specific methods
ecommerceChatBot.getCartContents = function() {
    // Get cart contents from your e-commerce system
    return JSON.parse(localStorage.getItem('cart') || '[]');
};

ecommerceChatBot.enhanceProductRecommendations = function(message) {
    // Parse product recommendations from message
    const productRegex = /product:(\d+)/g;
    const productIds = [...message.content.matchAll(productRegex)].map(m => m[1]);
    
    // Add interactive product cards to the message
    productIds.forEach(productId => {
        this.addProductCard(message.element, productId);
    });
};
```

### Healthcare Configuration

```javascript
// Healthcare information chatbot (educational only)
const healthcareChatBot = ChatBot.init({
    apiType: 'assistants',
    mode: 'inline',
    
    // Healthcare specific theme
    theme: ThemeManager.themes.medical,
    
    assistantConfig: {
        assistantId: 'asst_health_educator',
        enableTools: true,
        customFunctions: [
            'symptom_checker',
            'find_healthcare_providers',
            'medication_information',
            'health_tips'
        ]
    },
    
    assistant: {
        name: 'Health Information Assistant',
        welcomeMessage: 'Hello! I provide educational health information and can help you find healthcare resources. Please remember that this is not medical advice - always consult with healthcare professionals for medical concerns.',
        avatar: '/assets/health-assistant-avatar.png'
    },
    
    // Healthcare specific disclaimers
    onMessage: function(message) {
        if (message.role === 'assistant' && this.isHealthAdviceMessage(message.content)) {
            this.addMedicalDisclaimer(message.element);
        }
    },
    
    // Enhanced privacy for healthcare
    privacyMode: 'strict',
    dataRetention: 'session-only', // Don't persist health-related conversations
    
    // Healthcare specific UI elements
    customElements: {
        emergencyButton: {
            text: 'Emergency Services',
            action: () => window.open('tel:911'),
            className: 'emergency-button'
        }
    }
});

healthcareChatBot.addMedicalDisclaimer = function(messageElement) {
    const disclaimer = document.createElement('div');
    disclaimer.className = 'medical-disclaimer';
    disclaimer.innerHTML = `
        <div class="disclaimer-icon">⚠️</div>
        <div class="disclaimer-text">
            <strong>Medical Disclaimer:</strong> This information is for educational purposes only. 
            It is not intended as medical advice. Please consult with a healthcare professional.
        </div>
    `;
    messageElement.appendChild(disclaimer);
};
```

### Educational Configuration

```javascript
// Educational assistant chatbot
const educationChatBot = ChatBot.init({
    apiType: 'assistants',
    mode: 'inline',
    height: '600px',
    
    theme: ThemeManager.themes.education,
    
    assistantConfig: {
        assistantId: 'asst_education_tutor',
        enableCodeInterpreter: true,
        enableFileSearch: true,
        customFunctions: [
            'generate_quiz',
            'check_quiz_answers',
            'explain_concept',
            'track_progress',
            'create_study_plan'
        ]
    },
    
    assistant: {
        name: 'Learning Assistant',
        welcomeMessage: 'Hello! I\'m your personal learning assistant. I can help explain concepts, create quizzes, track your progress, and much more. What subject would you like to explore today?',
        avatar: '/assets/education-assistant-avatar.png'
    },
    
    // Enable file upload for homework/documents
    enableFileUpload: true,
    allowedFileTypes: ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png'],
    maxFileSize: 25 * 1024 * 1024, // 25MB for educational documents
    
    // Learning-specific features
    features: {
        progressTracking: true,
        achievementSystem: true,
        studyReminders: true,
        collaborativeMode: false // Can be enabled for group study
    },
    
    // Custom learning callbacks
    onMessage: function(message) {
        if (message.role === 'assistant') {
            // Check for learning objectives and track progress
            this.extractLearningObjectives(message);
            this.updateProgress(message);
        }
    },
    
    onToolCall: function(toolData) {
        if (toolData.name === 'generate_quiz') {
            this.prepareQuizInterface();
        } else if (toolData.name === 'check_quiz_answers') {
            this.displayQuizResults(toolData.result);
        }
    }
});

// Add educational specific methods
educationChatBot.prepareQuizInterface = function() {
    // Add interactive quiz UI elements
    const quizContainer = document.createElement('div');
    quizContainer.className = 'interactive-quiz-container';
    this.messageContainer.appendChild(quizContainer);
};

educationChatBot.updateProgress = function(message) {
    // Extract and track learning progress
    const concepts = this.extractConcepts(message.content);
    concepts.forEach(concept => {
        this.trackConceptMastery(concept);
    });
};
```

This comprehensive customization guide provides extensive examples for configuring the enhanced GPT Chatbot Boilerplate for various use cases, from simple chat implementations to complex industry-specific solutions with advanced features like file processing, custom function calling, and domain-specific AI assistants.