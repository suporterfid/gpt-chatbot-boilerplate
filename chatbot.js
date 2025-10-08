/**
 * GPT Chatbot Widget - Open Source Boilerplate
 * Real-time streaming chatbot with SSE and WebSocket support
 * 
 * @author Open Source Community
 * @version 1.0.0
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
        apiEndpoint: '/chat.php',
        websocketEndpoint: 'ws://localhost:8080',
        streamingMode: 'auto', // 'sse', 'websocket', 'ajax', 'auto'
        maxMessages: 50,
        enableMarkdown: true,

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
            thinking: 'Assistant is thinking...'
        },

        // UI settings
        animations: true,
        sound: false,
        timestamps: false,
        autoScroll: true,

        // Callbacks
        onMessage: null,
        onError: null,
        onConnect: null,
        onDisconnect: null,
        onTyping: null
    };

    /**
     * Main ChatBot class
     */
    class ChatBot {
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

            // UI Elements
            this.widget = null;
            this.chatContainer = null;
            this.messageContainer = null;
            this.inputField = null;
            this.sendButton = null;

            this.init();
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
            widget.className = 'chatbot-widget chatbot-floating';
            widget.innerHTML = `
                <div class="chatbot-toggle" title="Open Chat">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 13.54 2.36 14.99 3.01 16.28L2.1 21.9L7.72 20.99C9.01 21.64 10.46 22 12 22C17.52 22 22 17.52 22 12S17.52 2 12 2Z" fill="currentColor"/>
                    </svg>
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
            widget.className = 'chatbot-widget chatbot-inline';
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
                <div class="chatbot-input-container">
                    <div class="chatbot-input-wrapper">
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
                            Powered by <a href="#" target="_blank">Your Brand</a>
                        </small>
                    </div>
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
                .chatbot-widget {
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
        }

        /**
         * Show welcome message
         */
        showWelcomeMessage() {
            if (this.options.assistant.welcomeMessage) {
                this.addMessage({
                    role: 'assistant',
                    content: this.options.assistant.welcomeMessage,
                    timestamp: new Date()
                });
            }
        }

        /**
         * Handle send message
         */
        handleSend() {
            const message = this.inputField.value.trim();
            if (!message) return;

            // Add user message
            this.addMessage({
                role: 'user',
                content: message,
                timestamp: new Date()
            });

            // Clear input
            this.inputField.value = '';
            this.autoResizeTextarea();

            // Send message
            this.sendMessage(message);
        }

        /**
         * Send message to API
         */
        async sendMessage(message) {
            this.setTyping(true);

            try {
                // Try connection types in order of preference
                if (this.options.streamingMode === 'auto' || this.options.streamingMode === 'websocket') {
                    if (await this.tryWebSocket(message)) {
                        return;
                    }
                }

                if (this.options.streamingMode === 'auto' || this.options.streamingMode === 'sse') {
                    if (await this.trySSE(message)) {
                        return;
                    }
                }

                // Fallback to AJAX
                await this.tryAjax(message);

            } catch (error) {
                this.handleError(error);
            } finally {
                this.setTyping(false);
            }
        }

        /**
         * Try WebSocket connection
         */
        async tryWebSocket(message) {
            return new Promise((resolve) => {
                try {
                    if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                        this.websocket.send(JSON.stringify({
                            message: message,
                            conversation_id: this.conversationId
                        }));
                        resolve(true);
                        return;
                    }

                    const ws = new WebSocket(this.options.websocketEndpoint);

                    ws.onopen = () => {
                        this.websocket = ws;
                        this.connectionType = 'websocket';
                        this.setConnectionStatus(true);

                        ws.send(JSON.stringify({
                            message: message,
                            conversation_id: this.conversationId
                        }));

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
        async trySSE(message) {
            return new Promise((resolve) => {
                try {
                    if (this.eventSource) {
                        this.eventSource.close();
                    }

                    const url = new URL(this.options.apiEndpoint, window.location.origin);
                    url.searchParams.append('message', message);
                    url.searchParams.append('conversation_id', this.conversationId);

                    const eventSource = new EventSource(url);
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
        async tryAjax(message) {
            this.connectionType = 'ajax';

            const response = await fetch(this.options.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    conversation_id: this.conversationId,
                    stream: false
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.addMessage({
                role: 'assistant',
                content: data.response,
                timestamp: new Date()
            });
        }

        /**
         * Handle streaming response chunks
         */
        handleStreamChunk(data) {
            if (data.type === 'start') {
                this.currentMessageElement = this.addMessage({
                    role: 'assistant',
                    content: '',
                    timestamp: new Date(),
                    streaming: true
                });
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
                if (this.eventSource) {
                    this.eventSource.close();
                }
            } else if (data.type === 'error') {
                this.handleError(new Error(data.message));
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
                this.scrollToBottom();
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
            messageDiv.className = `chatbot-message chatbot-message-${message.role}`;

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

            if (this.options.enableMarkdown) {
                bubble.innerHTML = this.formatMessage(message.content);
            } else {
                bubble.textContent = message.content;
            }

            if (this.options.timestamps && message.timestamp) {
                const timestamp = document.createElement('div');
                timestamp.className = 'chatbot-message-timestamp';
                timestamp.textContent = this.formatTimestamp(message.timestamp);
                bubble.appendChild(timestamp);
            }

            content.appendChild(bubble);
            messageDiv.appendChild(content);

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
         * Append content to streaming message
         */
        appendToMessage(messageElement, content) {
            const bubble = messageElement.querySelector('.chatbot-message-bubble');
            const currentContent = bubble.textContent || bubble.innerHTML;

            if (this.options.enableMarkdown) {
                bubble.innerHTML = this.formatMessage(currentContent + content);
            } else {
                bubble.textContent = currentContent + content;
            }

            if (this.options.autoScroll) {
                this.scrollToBottom();
            }
        }

        /**
         * Finalize streaming message
         */
        finalizeMessage(messageElement) {
            messageElement.classList.remove('chatbot-streaming');
        }

        /**
         * Format message content (basic markdown support)
         */
        formatMessage(content) {
            if (!this.options.enableMarkdown) {
                return this.escapeHtml(content);
            }

            return content
                .replace(/
/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        }

        /**
         * Format timestamp
         */
        formatTimestamp(timestamp) {
            return timestamp.toLocaleTimeString([], { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Set typing indicator
         */
        setTyping(typing) {
            this.isTyping = typing;
            this.typingContainer.style.display = typing ? 'block' : 'none';

            if (this.options.autoScroll) {
                this.scrollToBottom();
            }

            if (this.options.onTyping) {
                this.options.onTyping(typing);
            }
        }

        /**
         * Set connection status
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
         * Handle errors
         */
        handleError(error) {
            console.error('ChatBot error:', error);

            this.addMessage({
                role: 'assistant',
                content: 'Sorry, I encountered an error. Please try again.',
                timestamp: new Date(),
                error: true
            });

            this.setTyping(false);

            if (this.options.onError) {
                this.options.onError(error);
            }
        }

        /**
         * Auto-resize textarea
         */
        autoResizeTextarea() {
            const textarea = this.inputField;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        /**
         * Scroll to bottom
         */
        scrollToBottom() {
            setTimeout(() => {
                this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
            }, 10);
        }

        /**
         * Show widget (floating mode)
         */
        show() {
            if (this.options.mode === 'floating') {
                this.chatContainer.style.display = 'block';
                this.toggleButton.style.display = 'none';

                if (this.options.animations) {
                    this.chatContainer.style.opacity = '0';
                    this.chatContainer.style.transform = 'translateY(20px) scale(0.95)';
                    setTimeout(() => {
                        this.chatContainer.style.transition = 'all 0.3s ease';
                        this.chatContainer.style.opacity = '1';
                        this.chatContainer.style.transform = 'translateY(0) scale(1)';
                    }, 10);
                }

                // Focus input
                setTimeout(() => this.inputField.focus(), 300);
            }
        }

        /**
         * Hide widget (floating mode)
         */
        hide() {
            if (this.options.mode === 'floating') {
                this.chatContainer.style.display = 'none';
                this.toggleButton.style.display = 'flex';
            }
        }

        /**
         * Toggle widget visibility (floating mode)
         */
        toggle() {
            if (this.chatContainer.style.display === 'none') {
                this.show();
            } else {
                this.hide();
            }
        }

        /**
         * Clear conversation history
         */
        clearHistory() {
            this.messages = [];
            this.messageContainer.innerHTML = '';
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
         * Destroy widget
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
    }

    // Static methods
    ChatBot.instances = [];

    /**
     * Initialize chatbot with container or floating mode
     */
    ChatBot.init = function(container, options) {
        // If first parameter is not a string or element, treat it as options for floating mode
        if (typeof container === 'object' && !container.nodeType) {
            options = container;
            container = null;
        }

        const instance = new ChatBot(container, options);
        ChatBot.instances.push(instance);
        return instance;
    };

    /**
     * Destroy all instances
     */
    ChatBot.destroyAll = function() {
        ChatBot.instances.forEach(instance => instance.destroy());
        ChatBot.instances = [];
    };

    // Export to global scope
    window.ChatBot = ChatBot;

    // Auto-initialize from data attributes
    document.addEventListener('DOMContentLoaded', function() {
        const autoElements = document.querySelectorAll('[data-chatbot]');
        autoElements.forEach(element => {
            try {
                const config = JSON.parse(element.dataset.chatbot || '{}');
                ChatBot.init(element, config);
            } catch (error) {
                console.error('Error auto-initializing chatbot:', error);
            }
        });
    });

})(window, document);
