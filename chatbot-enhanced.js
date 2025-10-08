/**
 * Enhanced GPT Chatbot Widget - Supports both Chat Completions and Assistants API
 * Real-time streaming chatbot with SSE and WebSocket support
 * 
 * @author Open Source Community  
 * @version 2.0.0
 * @license MIT
 */

(function(window, document) {
    'use strict';

    // Default configuration
    const DEFAULT_CONFIG = {
        // Basic settings
        mode: 'inline', // 'inline' or 'floating'
        position: 'bottom-right', // 'bottom-right' or 'bottom-left'
        title: 'Chat Assistant',
        height: '400px',
        width: '350px',
        show: false,

        // API settings
        apiType: 'chat', // 'chat' or 'assistants'
        // Resolved at runtime relative to script location if not provided
        apiEndpoint: null,
        // Only used if explicitly provided; not attempted by default
        websocketEndpoint: null,
        streamingMode: 'auto', // 'sse', 'websocket', 'ajax', 'auto'
        maxMessages: 50,
        enableMarkdown: true,
        enableFileUpload: false,
        maxFileSize: 10485760, // 10MB
        allowedFileTypes: ['txt', 'pdf', 'doc', 'docx', 'jpg', 'png'],

        // Assistant-specific settings
        assistantConfig: {
            assistantId: '',
            enableTools: false,
            enableCodeInterpreter: false,
            enableFileSearch: false,
            customFunctions: [],
        },

        // Theme customization
        theme: {
            primaryColor: '#1FB8CD',
            backgroundColor: '#F5F5F5',
            surfaceColor: '#FFFFFF',
            textColor: '#333333',
            mutedColor: '#666666',
            fontFamily: 'inherit',
            fontSize: '14px',
            borderRadius: '8px',
            shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
        },

        // Assistant settings
        assistant: {
            name: 'Assistant',
            avatar: null,
            welcomeMessage: 'Hello! How can I help you today?',
            placeholder: 'Type your message...',
            thinking: 'Assistant is thinking...',
            processingFile: 'Processing your file...'
        },

        // UI settings
        animations: true,
        sound: false,
        timestamps: false,
        autoScroll: true,
        showTypingIndicator: true,
        showFilePreview: true,

        // Callbacks
        onMessage: null,
        onError: null,
        onConnect: null,
        onDisconnect: null,
        onTyping: null,
        onFileUpload: null,
        onToolCall: null
    };

    /**
     * Enhanced ChatBot class with dual API support
     */
    class EnhancedChatBot {
        constructor(container, options = {}) {
            this.container = typeof container === 'string' ? 
                document.querySelector(container) : container;
            this.options = this.mergeConfig(DEFAULT_CONFIG, options);

            // State
            this.messages = [];
            this.conversationId = this.generateConversationId();
            this.isConnected = false;
            this.isTyping = false;
            this.connectionType = null;
            this.eventSource = null;
            this.websocket = null;
            this.currentMessageElement = null;
            this.uploadedFiles = [];
            this.currentRunId = null;

            // UI Elements
            this.widget = null;
            this.chatContainer = null;
            this.messageContainer = null;
            this.inputField = null;
            this.sendButton = null;
            this.fileInput = null;
            this.filePreview = null;

            // Bind methods used as callbacks/scheduled work to ensure correct context
            this.scrollToBottom = this.scrollToBottom.bind(this);

            // Environment-derived defaults
            this.basePath = this.detectBasePath();

            // Fill defaults that depend on runtime if not explicitly provided
            if (!options.apiEndpoint && !DEFAULT_CONFIG.apiEndpoint) {
                // Absolute path relative to script folder
                this.options.apiEndpoint = this.basePath + 'chat-unified.php';
            }

            if (!options.websocketEndpoint && !DEFAULT_CONFIG.websocketEndpoint) {
                // Do not set a websocket endpoint by default to avoid localhost leakage
                this.options.websocketEndpoint = null;
            }

            this.init();
        }

        /**
         * Detect base path of the script file (folder containing chatbot-enhanced.js)
         */
        detectBasePath() {
            try {
                let scriptSrc = '';
                if (document.currentScript && document.currentScript.src) {
                    scriptSrc = document.currentScript.src;
                } else {
                    const scripts = document.getElementsByTagName('script');
                    const found = Array.from(scripts).find(s => (s.src || '').indexOf('chatbot-enhanced.js') !== -1) || scripts[scripts.length - 1];
                    scriptSrc = found ? found.src : '';
                }
                const url = new URL(scriptSrc, window.location.href);
                // Return absolute path from root, ending with '/'
                return url.pathname.replace(/[^\/]+$/, '');
            } catch (e) {
                // Fallback to current page path folder
                return (window.location.pathname || '/').replace(/[^\/]+$/, '');
            }
        }

        /**
         * Initialize the chatbot
         */
        init() {
            this.createWidget();
            this.applyTheme();
            this.bindEvents();
            this.showWelcomeMessage();

            if (this.options.mode === 'floating' && this.options.show) {
                this.show();
            }
        }

        /**
         * Create the chatbot widget HTML structure
         */
        createWidget() {
            const widgetHTML = this.options.mode === 'floating' ? 
                this.createFloatingWidget() : this.createInlineWidget();

            if (this.options.mode === 'floating') {
                document.body.appendChild(widgetHTML);
                this.widget = widgetHTML;
            } else {
                if (!this.container) {
                    throw new Error('Container element required for inline mode');
                }
                this.container.appendChild(widgetHTML);
                this.widget = widgetHTML;
            }

            this.cacheElements();
        }

        /**
         * Create floating widget structure
         */
        createFloatingWidget() {
            const widget = document.createElement('div');
            widget.className = 'chatbot-widget chatbot-floating enhanced';
            widget.innerHTML = `
                <div class="chatbot-toggle" title="Open Chat">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 13.54 2.36 14.99 3.01 16.28L2.1 21.9L7.72 20.99C9.01 21.64 10.46 22 12 22C17.52 22 22 17.52 22 12S17.52 2 12 2Z" fill="currentColor"/>
                    </svg>
                    ${this.options.apiType === 'assistants' ? 
                        '<div class="api-indicator">GPT</div>' : 
                        '<div class="api-indicator">AI</div>'
                    }
                </div>
                <div class="chatbot-container" style="display: none;">
                    ${this.getWidgetContent()}
                </div>
            `;
            return widget;
        }

        /**
         * Create inline widget structure
         */
        createInlineWidget() {
            const widget = document.createElement('div');
            widget.className = 'chatbot-widget chatbot-inline enhanced';
            widget.innerHTML = `
                <div class="chatbot-container">
                    ${this.getWidgetContent()}
                </div>
            `;
            return widget;
        }

        /**
         * Get widget content HTML
         */
        getWidgetContent() {
            return `
                <div class="chatbot-header">
                    <div class="chatbot-header-info">
                        ${this.options.assistant.avatar ? 
                            `<img src="${this.options.assistant.avatar}" alt="Avatar" class="chatbot-avatar">` : 
                            '<div class="chatbot-avatar-placeholder"></div>'
                        }
                        <div class="chatbot-header-text">
                            <h3 class="chatbot-title">${this.options.title}</h3>
                            <p class="chatbot-status">
                                <span class="chatbot-status-indicator"></span>
                                <span class="chatbot-status-text">Online</span>
                                <span class="chatbot-api-type">${this.options.apiType.toUpperCase()}</span>
                            </p>
                        </div>
                    </div>
                    ${this.options.mode === 'floating' ? 
                        '<button class="chatbot-close" title="Close Chat">✕</button>' : ''
                    }
                </div>
                <div class="chatbot-messages" id="chatbot-messages-${this.conversationId}">
                    <!-- Messages will be added here -->
                </div>
                <div class="chatbot-typing" id="chatbot-typing-${this.conversationId}" style="display: none;">
                    <div class="chatbot-typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="chatbot-typing-text">${this.options.assistant.thinking}</span>
                </div>
                ${this.options.enableFileUpload ? this.getFilePreviewHTML() : ''}
                <div class="chatbot-input-container">
                    <div class="chatbot-input-wrapper">
                        ${this.options.enableFileUpload ? this.getFileInputHTML() : ''}
                        <textarea 
                            class="chatbot-input" 
                            placeholder="${this.options.assistant.placeholder}"
                            rows="1"
                            id="chatbot-input-${this.conversationId}"></textarea>
                        <button class="chatbot-send" title="Send Message" id="chatbot-send-${this.conversationId}">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M2.01 21L23 12L2.01 3L2 10L17 12L2 14L2.01 21Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                    <div class="chatbot-footer">
                        <small class="chatbot-powered-by">
                            Powered by <a href="#" target="_blank">OpenAI ${this.options.apiType === 'assistants' ? 'Assistants' : 'GPT'}</a>
                        </small>
                    </div>
                </div>
            `;
        }

        /**
         * Get file input HTML for file upload functionality
         */
        getFileInputHTML() {
            return `
                <input type="file" 
                       class="chatbot-file-input" 
                       id="chatbot-file-${this.conversationId}"
                       multiple 
                       accept="${this.options.allowedFileTypes.map(type => '.' + type).join(',')}"
                       style="display: none;">
                <button class="chatbot-file-button" title="Upload File" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" fill="currentColor"/>
                    </svg>
                </button>
            `;
        }

        /**
         * Get file preview HTML
         */
        getFilePreviewHTML() {
            return `
                <div class="chatbot-file-preview" id="chatbot-file-preview-${this.conversationId}" style="display: none;">
                    <div class="file-preview-header">
                        <span>Files to upload:</span>
                        <button class="file-preview-clear" title="Clear files">✕</button>
                    </div>
                    <div class="file-preview-list"></div>
                </div>
            `;
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            this.chatContainer = this.widget.querySelector('.chatbot-container');
            this.messageContainer = this.widget.querySelector('.chatbot-messages');
            this.typingContainer = this.widget.querySelector('.chatbot-typing');
            this.inputField = this.widget.querySelector('.chatbot-input');
            this.sendButton = this.widget.querySelector('.chatbot-send');
            this.toggleButton = this.widget.querySelector('.chatbot-toggle');
            this.closeButton = this.widget.querySelector('.chatbot-close');
            this.statusIndicator = this.widget.querySelector('.chatbot-status-indicator');
            this.statusText = this.widget.querySelector('.chatbot-status-text');

            if (this.options.enableFileUpload) {
                this.fileInput = this.widget.querySelector('.chatbot-file-input');
                this.fileButton = this.widget.querySelector('.chatbot-file-button');
                this.filePreview = this.widget.querySelector('.chatbot-file-preview');
            }
        }

        /**
         * Apply theme configuration
         */
        applyTheme() {
            const { theme } = this.options;
            const widget = this.widget;

            // Create CSS custom properties
            const style = document.createElement('style');
            style.textContent = `
                .chatbot-widget.enhanced {
                    --chatbot-primary-color: ${theme.primaryColor};
                    --chatbot-background-color: ${theme.backgroundColor};
                    --chatbot-surface-color: ${theme.surfaceColor};
                    --chatbot-text-color: ${theme.textColor};
                    --chatbot-muted-color: ${theme.mutedColor};
                    --chatbot-font-family: ${theme.fontFamily};
                    --chatbot-font-size: ${theme.fontSize};
                    --chatbot-border-radius: ${theme.borderRadius};
                    --chatbot-shadow: ${theme.shadow};
                }

                .api-indicator {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: var(--chatbot-primary-color);
                    color: white;
                    font-size: 8px;
                    padding: 1px 4px;
                    border-radius: 6px;
                    font-weight: bold;
                }

                .chatbot-api-type {
                    font-size: 10px;
                    background: var(--chatbot-primary-color);
                    color: white;
                    padding: 1px 4px;
                    border-radius: 3px;
                    margin-left: 6px;
                }
            `;
            document.head.appendChild(style);

            // Apply dimensions
            if (this.options.mode === 'inline') {
                widget.style.height = this.options.height;
                widget.style.width = this.options.width;
            } else {
                this.chatContainer.style.height = this.options.height;
                this.chatContainer.style.width = this.options.width;
            }
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Toggle button (floating mode)
            if (this.toggleButton) {
                this.toggleButton.addEventListener('click', () => this.toggle());
            }

            // Close button (floating mode)
            if (this.closeButton) {
                this.closeButton.addEventListener('click', () => this.hide());
            }

            // Send button
            this.sendButton.addEventListener('click', () => this.handleSend());

            // Input field
            this.inputField.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.handleSend();
                }
            });

            // Auto-resize textarea
            this.inputField.addEventListener('input', () => this.autoResizeTextarea());

            // File upload events
            if (this.options.enableFileUpload) {
                this.fileButton.addEventListener('click', () => this.fileInput.click());
                this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));

                if (this.filePreview) {
                    const clearButton = this.filePreview.querySelector('.file-preview-clear');
                    if (clearButton) {
                        clearButton.addEventListener('click', () => this.clearFiles());
                    }
                }
            }
        }

        /**
         * Handle file selection
         */
        handleFileSelect(event) {
            const files = Array.from(event.target.files);

            for (const file of files) {
                if (this.validateFile(file)) {
                    this.addFileToUpload(file);
                }
            }

            this.updateFilePreview();
            event.target.value = ''; // Clear input
        }

        /**
         * Validate file before upload
         */
        validateFile(file) {
            // Check file size
            if (file.size > this.options.maxFileSize) {
                this.showError(`File "${file.name}" is too large. Maximum size: ${this.formatFileSize(this.options.maxFileSize)}`);
                return false;
            }

            // Check file type
            const extension = file.name.split('.').pop().toLowerCase();
            if (!this.options.allowedFileTypes.includes(extension)) {
                this.showError(`File type "${extension}" is not allowed. Allowed types: ${this.options.allowedFileTypes.join(', ')}`);
                return false;
            }

            return true;
        }

        /**
         * Add file to upload queue
         */
        addFileToUpload(file) {
            const fileData = {
                file: file,
                id: 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                name: file.name,
                size: file.size,
                type: file.type
            };

            this.uploadedFiles.push(fileData);
        }

        /**
         * Update file preview display
         */
        updateFilePreview() {
            if (!this.filePreview) return;

            if (this.uploadedFiles.length === 0) {
                this.filePreview.style.display = 'none';
                return;
            }

            this.filePreview.style.display = 'block';
            const listContainer = this.filePreview.querySelector('.file-preview-list');

            listContainer.innerHTML = this.uploadedFiles.map(fileData => `
                <div class="file-preview-item" data-file-id="${fileData.id}">
                    <div class="file-info">
                        <span class="file-name">${fileData.name}</span>
                        <span class="file-size">${this.formatFileSize(fileData.size)}</span>
                    </div>
                    <button class="file-remove" data-file-id="${fileData.id}" title="Remove file">✕</button>
                </div>
            `).join('');

            // Add remove file event listeners
            listContainer.querySelectorAll('.file-remove').forEach(button => {
                button.addEventListener('click', (e) => {
                    const fileId = e.target.dataset.fileId;
                    this.removeFile(fileId);
                });
            });
        }

        /**
         * Remove file from upload queue
         */
        removeFile(fileId) {
            this.uploadedFiles = this.uploadedFiles.filter(file => file.id !== fileId);
            this.updateFilePreview();
        }

        /**
         * Clear all files
         */
        clearFiles() {
            this.uploadedFiles = [];
            this.updateFilePreview();
        }

        /**
         * Format file size for display
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        /**
         * Show welcome message
         */
        showWelcomeMessage() {
            if (this.options.assistant.welcomeMessage) {
                this.addMessage({
                    role: 'assistant',
                    content: this.options.assistant.welcomeMessage,
                    timestamp: new Date(),
                    apiType: this.options.apiType
                });
            }
        }

        /**
         * Handle send message
         */
        handleSend() {
            const message = this.inputField.value.trim();
            if (!message && this.uploadedFiles.length === 0) return;

            // Add user message
            this.addMessage({
                role: 'user',
                content: message || '(File upload)',
                timestamp: new Date(),
                files: this.uploadedFiles.length > 0 ? [...this.uploadedFiles] : null
            });

            // Clear input and files
            this.inputField.value = '';
            const filesToSend = [...this.uploadedFiles];
            this.clearFiles();
            this.autoResizeTextarea();

            // Send message
            this.sendMessage(message, filesToSend);
        }

        /**
         * Send message to API
         */
        async sendMessage(message, files = []) {
            this.setTyping(true);

            try {
                // Prepare request data
                const requestData = {
                    message: message,
                    conversation_id: this.conversationId,
                    api_type: this.options.apiType
                };

                // Add file data if present
                if (files.length > 0) {
                    requestData.file_data = await Promise.all(
                        files.map(fileData => this.fileToBase64(fileData.file))
                    );
                }

                // Try connection types in order of preference
                if (this.options.streamingMode === 'auto' || this.options.streamingMode === 'websocket') {
                    if (await this.tryWebSocket(requestData)) {
                        return;
                    }
                }

                if (this.options.streamingMode === 'auto' || this.options.streamingMode === 'sse') {
                    if (await this.trySSE(requestData)) {
                        return;
                    }
                }

                // Fallback to AJAX
                await this.tryAjax(requestData);

            } catch (error) {
                this.handleError(error);
            } finally {
                this.setTyping(false);
            }
        }

        /**
         * Convert file to base64
         */
        fileToBase64(file) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    resolve({
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        data: e.target.result.split(',')[1] // Remove data:mime;base64, prefix
                    });
                };
                reader.readAsDataURL(file);
            });
        }

        /**
         * Try WebSocket connection
         */
        async tryWebSocket(requestData) {
            return new Promise((resolve) => {
                // Skip if no endpoint configured
                if (!this.options.websocketEndpoint) {
                    resolve(false);
                    return;
                }
                try {
                    if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                        this.websocket.send(JSON.stringify(requestData));
                        resolve(true);
                        return;
                    }

                    let endpoint = this.options.websocketEndpoint;
                    // If page is https and endpoint is ws://, attempt to upgrade to wss://
                    if (window.location.protocol === 'https:' && /^ws:\/\//i.test(endpoint)) {
                        endpoint = endpoint.replace(/^ws:\/\//i, 'wss://');
                    }

                    const ws = new WebSocket(endpoint);

                    ws.onopen = () => {
                        this.websocket = ws;
                        this.connectionType = 'websocket';
                        this.setConnectionStatus(true);

                        ws.send(JSON.stringify(requestData));
                        resolve(true);
                    };

                    ws.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        this.handleStreamChunk(data);
                    };

                    ws.onerror = () => {
                        resolve(false);
                    };

                    ws.onclose = () => {
                        this.setConnectionStatus(false);
                        this.websocket = null;
                    };

                    // Timeout fallback
                    setTimeout(() => resolve(false), 2000);

                } catch (error) {
                    resolve(false);
                }
            });
        }

        /**
         * Try SSE connection
         */
        async trySSE(requestData) {
            return new Promise((resolve) => {
                try {
                    if (this.eventSource) {
                        this.eventSource.close();
                    }

                    // For SSE, we need to send data via POST
                    const eventSource = new EventSource(this.options.apiEndpoint);

                    // Send the request data via POST first
                    fetch(this.options.apiEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'text/event-stream'
                        },
                        body: JSON.stringify(requestData)
                    }).then(() => {
                        this.eventSource = eventSource;
                        this.connectionType = 'sse';

                        eventSource.onopen = () => {
                            this.setConnectionStatus(true);
                            resolve(true);
                        };

                        eventSource.onmessage = (event) => {
                            const data = JSON.parse(event.data);
                            this.handleStreamChunk(data);
                        };

                        eventSource.onerror = () => {
                            eventSource.close();
                            this.setConnectionStatus(false);
                            resolve(false);
                        };
                    }).catch(() => resolve(false));

                    // Timeout fallback
                    setTimeout(() => {
                        if (eventSource.readyState === EventSource.CONNECTING) {
                            eventSource.close();
                            resolve(false);
                        }
                    }, 3000);

                } catch (error) {
                    resolve(false);
                }
            });
        }

        /**
         * Try AJAX fallback
         */
        async tryAjax(requestData) {
            this.connectionType = 'ajax';

            requestData.stream = false; // Disable streaming for AJAX

            const response = await fetch(this.options.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.addMessage({
                role: 'assistant',
                content: data.response || data.content,
                timestamp: new Date(),
                apiType: this.options.apiType
            });
        }

        /**
         * Handle streaming response chunks
         */
        handleStreamChunk(data) {
            if (data.type === 'start' || data.type === 'run_created') {
                this.currentMessageElement = this.addMessage({
                    role: 'assistant',
                    content: '',
                    timestamp: new Date(),
                    streaming: true,
                    apiType: this.options.apiType,
                    runId: data.run_id
                });

                if (data.run_id) {
                    this.currentRunId = data.run_id;
                }

            } else if (data.type === 'chunk') {
                if (this.currentMessageElement) {
                    this.appendToMessage(this.currentMessageElement, data.content);
                }

            } else if (data.type === 'done') {
                if (this.currentMessageElement) {
                    this.finalizeMessage(this.currentMessageElement);
                    this.currentMessageElement = null;
                }
                this.setTyping(false);
                this.currentRunId = null;

                if (this.eventSource) {
                    this.eventSource.close();
                }

            } else if (data.type === 'error') {
                this.handleError(new Error(data.message));

            } else if (data.type === 'tool_call') {
                this.handleToolCall(data);
            }
        }

        /**
         * Handle tool calls from Assistants API
         */
        handleToolCall(data) {
            const toolName = data.tool_name;
            const toolArgs = data.arguments;

            // Show tool execution in UI
            if (this.currentMessageElement) {
                const toolIndicator = document.createElement('div');
                toolIndicator.className = 'tool-execution';
                toolIndicator.innerHTML = `
                    <div class="tool-info">
                        <span class="tool-icon">🔧</span>
                        <span class="tool-name">Executing: ${toolName}</span>
                    </div>
                `;

                const bubble = this.currentMessageElement.querySelector('.chatbot-message-bubble');
                bubble.appendChild(toolIndicator);
            }

            // Trigger callback
            if (this.options.onToolCall) {
                this.options.onToolCall({
                    name: toolName,
                    arguments: toolArgs,
                    runId: this.currentRunId
                });
            }
        }

        /**
         * Add message to conversation
         */
        addMessage(message) {
            this.messages.push(message);

            // Limit message history
            if (this.messages.length > this.options.maxMessages) {
                this.messages = this.messages.slice(-this.options.maxMessages);
            }

            const messageElement = this.createMessageElement(message);
            this.messageContainer.appendChild(messageElement);

            if (this.options.autoScroll) {
                if (typeof this.scrollToBottom === 'function') {
                    this.scrollToBottom();
                } else if (this.messageContainer) {
                    this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
                }
            }

            // Callback
            if (this.options.onMessage) {
                this.options.onMessage(message);
            }

            return messageElement;
        }

        /**
         * Create message element
         */
        createMessageElement(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chatbot-message chatbot-message-${message.role} ${message.apiType || 'chat'}`;

            const content = document.createElement('div');
            content.className = 'chatbot-message-content';

            if (message.role === 'assistant' && this.options.assistant.avatar) {
                const avatar = document.createElement('img');
                avatar.src = this.options.assistant.avatar;
                avatar.className = 'chatbot-message-avatar';
                messageDiv.appendChild(avatar);
            }

            const bubble = document.createElement('div');
            bubble.className = 'chatbot-message-bubble';

            // Add API type indicator
            if (message.apiType) {
                const apiIndicator = document.createElement('div');
                apiIndicator.className = 'message-api-indicator';
                apiIndicator.textContent = message.apiType.toUpperCase();
                bubble.appendChild(apiIndicator);
            }

            // Add message content
            const messageContent = document.createElement('div');
            messageContent.className = 'message-text';

            const initialContent = message.content || '';

            if (this.options.enableMarkdown) {
                messageContent.innerHTML = this.formatMessage(initialContent);
            } else {
                messageContent.textContent = initialContent;
            }

            bubble.appendChild(messageContent);

            // Add file attachments if present
            if (message.files && message.files.length > 0) {
                const filesContainer = document.createElement('div');
                filesContainer.className = 'message-files';

                message.files.forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'message-file-item';
                    const fileSize = file.file ? file.file.size : file.size;
                    fileItem.innerHTML = `
                        <span class="file-icon">📄</span>
                        <span class="file-name">${file.name}</span>
                        <span class="file-size">${this.formatFileSize(fileSize || 0)}</span>
                    `;
                    filesContainer.appendChild(fileItem);
                });

                bubble.appendChild(filesContainer);
            }

            if (this.options.timestamps && message.timestamp) {
                const timestamp = document.createElement('div');
                timestamp.className = 'chatbot-message-timestamp';
                timestamp.textContent = this.formatTimestamp(message.timestamp);
                bubble.appendChild(timestamp);
            }

            content.appendChild(bubble);
            messageDiv.appendChild(content);

            messageDiv.dataset.rawContent = initialContent;

            if (message.streaming) {
                messageDiv.classList.add('chatbot-streaming');
            }

            if (this.options.animations) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    messageDiv.style.transition = 'all 0.3s ease';
                    messageDiv.style.opacity = '1';
                    messageDiv.style.transform = 'translateY(0)';
                }, 10);
            }

            return messageDiv;
        }

        /**
         * Format message content (basic markdown support)
         */
        formatMessage(content) {
            if (!this.options.enableMarkdown) {
                return this.escapeHtml(content);
            }

            return content
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        }

        /**
         * Append content to streaming message
         */
        appendToMessage(messageElement, content) {
            if (!messageElement) return;

            const messageText = messageElement.querySelector('.message-text');
            if (!messageText) return;

            const previousContent = messageElement.dataset.rawContent || '';
            const updatedContent = previousContent + content;

            messageElement.dataset.rawContent = updatedContent;

            if (this.options.enableMarkdown) {
                messageText.innerHTML = this.formatMessage(updatedContent);
            } else {
                messageText.textContent = updatedContent;
            }

            if (this.options.autoScroll) {
                if (typeof this.scrollToBottom === 'function') {
                    this.scrollToBottom();
                } else if (this.messageContainer) {
                    this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
                }
            }
        }

        /**
         * Finalize streaming message
         */
        finalizeMessage(messageElement) {
            if (!messageElement) return;
            messageElement.classList.remove('chatbot-streaming');
        }

        /**
         * Format timestamp for display
         */
        formatTimestamp(timestamp) {
            const date = timestamp instanceof Date ? timestamp : new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        /**
         * Escape HTML content
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Set typing indicator visibility
         */
        setTyping(isTyping) {
            this.isTyping = isTyping;

            if (!this.typingContainer) return;

            if (!this.options.showTypingIndicator) {
                this.typingContainer.style.display = 'none';
                return;
            }

            this.typingContainer.style.display = isTyping ? 'flex' : 'none';
        }

        /**
         * Update connection status indicators
         */
        setConnectionStatus(connected) {
            this.isConnected = connected;

            if (this.statusIndicator) {
                this.statusIndicator.className = `chatbot-status-indicator ${connected ? 'connected' : 'disconnected'}`;
            }

            if (this.statusText) {
                this.statusText.textContent = connected ? 'Online' : 'Connecting...';
            }

            if (connected && this.options.onConnect) {
                this.options.onConnect();
            } else if (!connected && this.options.onDisconnect) {
                this.options.onDisconnect();
            }
        }

        /**
         * Handle errors and notify UI
         */
        handleError(error) {
            console.error('EnhancedChatBot error:', error);

            this.showError('Sorry, I encountered an error. Please try again.');
            this.setTyping(false);

            if (this.options.onError) {
                this.options.onError(error);
            }
        }

        /**
         * Auto-resize textarea based on content
         */
        autoResizeTextarea() {
            if (!this.inputField) return;

            this.inputField.style.height = 'auto';
            this.inputField.style.height = Math.min(this.inputField.scrollHeight, 160) + 'px';
        }

        /**
         * Scroll message view to bottom
         */
        scrollToBottom() {
            if (!this.messageContainer) return;

            requestAnimationFrame(() => {
                this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
            });
        }

        /**
         * Show widget (floating mode)
         */
        show() {
            if (this.options.mode !== 'floating') return;

            this.chatContainer.style.display = 'block';

            if (this.toggleButton) {
                this.toggleButton.style.display = 'none';
            }

            if (this.options.animations) {
                this.chatContainer.style.opacity = '0';
                this.chatContainer.style.transform = 'translateY(20px) scale(0.95)';
                requestAnimationFrame(() => {
                    this.chatContainer.style.transition = 'all 0.3s ease';
                    this.chatContainer.style.opacity = '1';
                    this.chatContainer.style.transform = 'translateY(0) scale(1)';
                });
            }

            setTimeout(() => this.inputField && this.inputField.focus(), 300);
        }

        /**
         * Hide widget (floating mode)
         */
        hide() {
            if (this.options.mode !== 'floating') return;

            this.chatContainer.style.display = 'none';

            if (this.toggleButton) {
                this.toggleButton.style.display = 'flex';
            }
        }

        /**
         * Toggle widget visibility
         */
        toggle() {
            if (this.options.mode !== 'floating') return;

            if (this.chatContainer.style.display === 'none' || this.chatContainer.style.display === '') {
                this.show();
            } else {
                this.hide();
            }
        }

        /**
         * Clear conversation history and reset view
         */
        clearHistory() {
            this.messages = [];

            if (this.messageContainer) {
                this.messageContainer.innerHTML = '';
            }

            if (this.options.enableFileUpload) {
                this.clearFiles();
            }

            this.showWelcomeMessage();
        }

        /**
         * Send message programmatically
         */
        sendMessageProgrammatically(message) {
            this.addMessage({
                role: 'user',
                content: message,
                timestamp: new Date()
            });

            this.sendMessage(message);
        }

        /**
         * Show error message
         */
        showError(message) {
            this.addMessage({
                role: 'system',
                content: message,
                timestamp: new Date(),
                error: true
            });
        }

        /**
         * Generate unique conversation ID
         */
        generateConversationId() {
            return 'conv_' + Math.random().toString(36).substr(2, 9);
        }

        /**
         * Merge configuration objects
         */
        mergeConfig(defaults, options) {
            const result = { ...defaults };

            for (const key in options) {
                if (typeof options[key] === 'object' && !Array.isArray(options[key]) && options[key] !== null) {
                    result[key] = { ...result[key], ...options[key] };
                } else {
                    result[key] = options[key];
                }
            }

            return result;
        }

        /**
         * Destroy widget instance
         */
        destroy() {
            if (this.eventSource) {
                this.eventSource.close();
            }

            if (this.websocket) {
                this.websocket.close();
            }

            if (this.widget && this.widget.parentNode) {
                this.widget.parentNode.removeChild(this.widget);
            }
        }
    }

    // Static methods
    EnhancedChatBot.instances = [];

    /**
     * Initialize enhanced chatbot
     */
    EnhancedChatBot.init = function(container, options) {
        if (typeof container === 'object' && !container.nodeType) {
            options = container;
            container = null;
        }

        const instance = new EnhancedChatBot(container, options);
        EnhancedChatBot.instances.push(instance);
        return instance;
    };

    /**
     * Destroy all instances
     */
    EnhancedChatBot.destroyAll = function() {
        EnhancedChatBot.instances.forEach(instance => instance.destroy());
        EnhancedChatBot.instances = [];
    };

    // Export to global scope
    window.ChatBot = EnhancedChatBot; // Keep same global name for compatibility
    window.EnhancedChatBot = EnhancedChatBot;

})(window, document);
