/**
 * GPT Chatbot Boilerplate - JavaScript Component
 * Open-source, white-label GPT-powered chatbot for any website
 * 
 * Features:
 * - Real-time streaming (SSE + WebSocket with fallback)
 * - White-label customization
 * - Mobile responsive
 * - Multi-turn conversations
 * - Easy integration
 * 
 * Usage:
 * ChatBot.init('#container', options);
 * ChatBot.init(options); // for floating mode
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
        show: false, // auto-show for floating mode
        
        // API settings
        apiEndpoint: '/chat.php',
        streamingMode: 'auto', // 'sse', 'websocket', 'ajax', 'auto'
        maxMessages: 50,
        enableMarkdown: true,
        
        // Theme customization
        theme: {
            primaryColor: '#1FB8CD',
            backgroundColor: '#F5F5F5',
            fontFamily: 'inherit',
            borderRadius: '8px',
            shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
        },
        
        // Assistant settings
        assistant: {
            name: 'Assistant',
            avatar: null,
            welcomeMessage: 'Hello! How can I help you today?',
            placeholder: 'Type your message...'
        },
        
        // Callbacks
        onMessage: null,
        onError: null,
        onConnect: null,
        onDisconnect: null
    };

    // Main ChatBot class
    class ChatBot {
        constructor(container, options = {}) {
            this.container = typeof container === 'string' ? 
                document.querySelector(container) : container;
            this.options = this.mergeConfig(DEFAULT_CONFIG, options);
            this.messages = [];
            this.isConnected = false;
            this.connection = null;
            this.connectionType = 'none';
            this.isMinimized = false;
            this.isVisible = true;
            this.isTyping = false;
            this.messageId = 0;
            
            this.init();
        }

        mergeConfig(defaults, options) {
            const merged = { ...defaults };
            
            for (const key in options) {
                if (options.hasOwnProperty(key)) {
                    if (typeof options[key] === 'object' && options[key] !== null && 
                        !Array.isArray(options[key]) && typeof defaults[key] === 'object') {
                        merged[key] = { ...defaults[key], ...options[key] };
                    } else {
                        merged[key] = options[key];
                    }
                }
            }
            
            return merged;
        }

        init() {
            this.createElements();
            this.applyTheme();
            this.attachEventListeners();
            this.initializeConnection();
            
            // Show welcome message
            if (this.options.assistant.welcomeMessage) {
                this.addMessage('assistant', this.options.assistant.welcomeMessage);
            }
            
            // Auto-show for floating mode
            if (this.options.mode === 'floating' && this.options.show) {
                this.show();
            }
        }

        createElements() {
            if (this.options.mode === 'floating') {
                this.createFloatingWidget();
            } else {
                this.createInlineWidget();
            }
        }

        createFloatingWidget() {
            // Create toggle button
            this.toggleBtn = document.createElement('button');
            this.toggleBtn.className = `chatbot-toggle ${this.options.position}`;
            this.toggleBtn.innerHTML = '💬';
            this.toggleBtn.title = 'Open Chat';
            document.body.appendChild(this.toggleBtn);

            // Create chatbot container
            this.element = document.createElement('div');
            this.element.className = `chatbot-container floating-mode ${this.options.position} hidden`;
            this.element.innerHTML = this.getWidgetHTML();
            document.body.appendChild(this.element);

            // Toggle functionality
            this.toggleBtn.addEventListener('click', () => {
                this.isVisible ? this.hide() : this.show();
            });
        }

        createInlineWidget() {
            if (!this.container) {
                console.error('ChatBot: Container element not found');
                return;
            }

            this.element = document.createElement('div');
            this.element.className = 'chatbot-container inline-mode';
            this.element.style.height = this.options.height;
            this.element.innerHTML = this.getWidgetHTML();
            this.container.appendChild(this.element);
        }

        getWidgetHTML() {
            const assistantInitial = this.options.assistant.name.charAt(0).toUpperCase();
            
            return `
                <div class="chatbot-header">
                    <div class="chatbot-header-content">
                        <div class="chatbot-avatar">
                            ${this.options.assistant.avatar ? 
                                `<img src="${this.options.assistant.avatar}" alt="Assistant" style="width: 100%; height: 100%; border-radius: 50%;">` :
                                assistantInitial
                            }
                        </div>
                        <div class="chatbot-title">${this.options.title}</div>
                    </div>
                    <div class="chatbot-controls">
                        <div class="chatbot-connection-status">
                            <div class="chatbot-connection-dot"></div>
                            <span class="chatbot-connection-text">Connecting...</span>
                        </div>
                        ${this.options.mode === 'floating' ? `
                            <button class="chatbot-btn chatbot-minimize" title="Minimize">−</button>
                            <button class="chatbot-btn chatbot-close" title="Close">×</button>
                        ` : ''}
                    </div>
                </div>
                
                <div class="chatbot-messages" id="chatbot-messages-${this.messageId}"></div>
                
                <div class="chatbot-input-area">
                    <form class="chatbot-input-form">
                        <textarea 
                            class="chatbot-input" 
                            placeholder="${this.options.assistant.placeholder}"
                            rows="1"
                        ></textarea>
                        <button type="submit" class="chatbot-send-btn" title="Send">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </form>
                </div>
                
                <div class="chatbot-status">
                    <span class="chatbot-status-text">Ready to chat</span>
                </div>
            `;
        }

        applyTheme() {
            const theme = this.options.theme;
            
            if (theme.primaryColor) {
                this.element.style.setProperty('--chatbot-primary', theme.primaryColor);
            }
            if (theme.backgroundColor) {
                this.element.style.setProperty('--chatbot-bg', theme.backgroundColor);
            }
            if (theme.fontFamily) {
                this.element.style.fontFamily = theme.fontFamily;
            }
            if (theme.borderRadius) {
                this.element.style.setProperty('--chatbot-radius', theme.borderRadius);
            }
            if (theme.shadow) {
                this.element.style.boxShadow = theme.shadow;
            }

            // Apply primary color to various elements
            const header = this.element.querySelector('.chatbot-header');
            const sendBtn = this.element.querySelector('.chatbot-send-btn');
            
            if (header && theme.primaryColor) {
                header.style.backgroundColor = theme.primaryColor;
            }
            if (sendBtn && theme.primaryColor) {
                sendBtn.style.backgroundColor = theme.primaryColor;
            }
        }

        attachEventListeners() {
            const form = this.element.querySelector('.chatbot-input-form');
            const input = this.element.querySelector('.chatbot-input');
            const messagesContainer = this.element.querySelector('.chatbot-messages');

            // Form submission
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });

            // Auto-resize textarea
            input.addEventListener('input', (e) => {
                e.target.style.height = 'auto';
                e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
            });

            // Enter key handling
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Floating mode controls
            if (this.options.mode === 'floating') {
                const header = this.element.querySelector('.chatbot-header');
                const minimizeBtn = this.element.querySelector('.chatbot-minimize');
                const closeBtn = this.element.querySelector('.chatbot-close');

                header.addEventListener('click', (e) => {
                    if (!e.target.closest('.chatbot-controls')) {
                        this.toggleMinimize();
                    }
                });

                if (minimizeBtn) {
                    minimizeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.toggleMinimize();
                    });
                }

                if (closeBtn) {
                    closeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.hide();
                    });
                }
            }

            // Auto-scroll to bottom on new messages
            const observer = new MutationObserver(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            });
            observer.observe(messagesContainer, { childList: true });
        }

        initializeConnection() {
            // Simulate connection setup based on streaming mode
            this.updateConnectionStatus('connecting', 'Connecting...');
            
            setTimeout(() => {
                if (this.options.streamingMode === 'auto') {
                    // Simulate auto-detection (in real implementation, try WebSocket first, then SSE)
                    this.connectionType = 'sse'; // Simulated fallback to SSE
                } else {
                    this.connectionType = this.options.streamingMode;
                }
                
                this.isConnected = true;
                this.updateConnectionStatus('connected', 'Connected');
                
                if (this.options.onConnect) {
                    this.options.onConnect(this.connectionType);
                }
            }, 1000);
        }

        updateConnectionStatus(status, text) {
            const dot = this.element.querySelector('.chatbot-connection-dot');
            const statusText = this.element.querySelector('.chatbot-connection-text');
            const statusBar = this.element.querySelector('.chatbot-status-text');
            
            if (dot) {
                dot.className = `chatbot-connection-dot ${status}`;
            }
            if (statusText) {
                statusText.textContent = text;
            }
            if (statusBar) {
                statusBar.textContent = text;
            }
        }

        sendMessage() {
            const input = this.element.querySelector('.chatbot-input');
            const message = input.value.trim();
            
            if (!message || this.isTyping) return;

            // Add user message
            this.addMessage('user', message);
            input.value = '';
            input.style.height = 'auto';

            // Simulate API call with streaming response
            this.simulateAssistantResponse(message);
        }

        addMessage(role, content, streaming = false) {
            const messagesContainer = this.element.querySelector('.chatbot-messages');
            const messageElement = document.createElement('div');
            messageElement.className = `message ${role}`;
            
            const avatarInitial = role === 'user' ? 'U' : 
                this.options.assistant.name.charAt(0).toUpperCase();
            
            messageElement.innerHTML = `
                <div class="message-avatar">
                    ${role === 'user' ? avatarInitial : 
                        (this.options.assistant.avatar ? 
                            `<img src="${this.options.assistant.avatar}" alt="Assistant" style="width: 100%; height: 100%; border-radius: 50%;">` :
                            avatarInitial
                        )
                    }
                </div>
                <div class="message-content ${streaming ? 'typing' : ''}">
                    ${streaming ? this.getTypingIndicator() : this.formatMessage(content)}
                </div>
            `;
            
            messagesContainer.appendChild(messageElement);
            this.messages.push({ role, content, timestamp: Date.now() });
            
            // Trim messages if exceeding max
            if (this.messages.length > this.options.maxMessages) {
                this.messages = this.messages.slice(-this.options.maxMessages);
                // Remove old message elements
                const messageElements = messagesContainer.querySelectorAll('.message');
                if (messageElements.length > this.options.maxMessages) {
                    messageElements[0].remove();
                }
            }
            
            return messageElement;
        }

        getTypingIndicator() {
            return `
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            `;
        }

        formatMessage(content) {
            if (!this.options.enableMarkdown) {
                return this.escapeHtml(content);
            }

            // Basic markdown support
            let formatted = this.escapeHtml(content);
            
            // Bold text
            formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            
            // Italic text
            formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
            
            // Inline code
            formatted = formatted.replace(/`(.*?)`/g, '<code>$1</code>');
            
            // Links
            formatted = formatted.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
            
            // Line breaks
            formatted = formatted.replace(/\n/g, '<br>');
            
            return formatted;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        simulateAssistantResponse(userMessage) {
            this.isTyping = true;
            this.updateConnectionStatus('connected', 'Thinking...');
            
            // Add typing indicator
            const typingMessage = this.addMessage('assistant', '', true);
            
            // Simulate streaming response
            setTimeout(() => {
                const response = this.generateMockResponse(userMessage);
                this.streamResponse(typingMessage, response);
            }, 500 + Math.random() * 1000);
        }

        generateMockResponse(userMessage) {
            const responses = [
                "Thank you for your message! This is a demo response showing how the chatbot would work with a real GPT backend. In production, this would be powered by OpenAI's API.",
                
                "I understand you're asking about **" + userMessage.substring(0, 20) + "**. This chatbot boilerplate supports:\n\n• Real-time streaming responses\n• Multi-turn conversations\n• Custom theming\n• Mobile responsiveness",
                
                "This is a demonstration of the streaming response feature. In a real implementation, this would connect to your PHP backend which communicates with OpenAI's API using either Server-Sent Events or WebSockets.",
                
                "Great question! The boilerplate includes:\n\n1. **Frontend**: Plain JavaScript widget\n2. **Backend**: PHP with OpenAI integration\n3. **Streaming**: SSE and WebSocket support\n4. **Customization**: White-label ready\n\nWould you like to know more about any of these features?",
                
                "I can help you with that! This chatbot demonstrates the key features:\n\n• ✅ Real-time streaming\n• ✅ Auto-fallback (WebSocket → SSE → AJAX)\n• ✅ Mobile responsive design\n• ✅ Easy integration\n• ✅ Customizable themes"
            ];
            
            return responses[Math.floor(Math.random() * responses.length)];
        }

        streamResponse(messageElement, response) {
            const contentElement = messageElement.querySelector('.message-content');
            contentElement.className = 'message-content';
            contentElement.innerHTML = '';
            
            let currentText = '';
            let index = 0;
            
            const streamInterval = setInterval(() => {
                if (index >= response.length) {
                    clearInterval(streamInterval);
                    this.isTyping = false;
                    this.updateConnectionStatus('connected', 'Connected');
                    
                    // Update message in history
                    const lastMessage = this.messages[this.messages.length - 1];
                    if (lastMessage && lastMessage.role === 'assistant') {
                        lastMessage.content = response;
                    }
                    
                    if (this.options.onMessage) {
                        this.options.onMessage('assistant', response);
                    }
                    
                    return;
                }
                
                // Add characters at varying speeds to simulate real streaming
                const chunkSize = Math.random() > 0.7 ? 2 : 1;
                currentText += response.substring(index, index + chunkSize);
                index += chunkSize;
                
                contentElement.innerHTML = this.formatMessage(currentText) + 
                    '<span class="cursor" style="opacity: 0.7;">▋</span>';
                
            }, 50 + Math.random() * 50);
        }

        show() {
            if (this.options.mode === 'floating') {
                this.element.classList.remove('hidden');
                this.isVisible = true;
                
                if (this.toggleBtn) {
                    this.toggleBtn.style.display = 'none';
                }
            }
        }

        hide() {
            if (this.options.mode === 'floating') {
                this.element.classList.add('hidden');
                this.isVisible = false;
                
                if (this.toggleBtn) {
                    this.toggleBtn.style.display = 'flex';
                }
            }
        }

        toggleMinimize() {
            if (this.options.mode === 'floating') {
                this.isMinimized = !this.isMinimized;
                this.element.classList.toggle('minimized', this.isMinimized);
                
                const minimizeBtn = this.element.querySelector('.chatbot-minimize');
                if (minimizeBtn) {
                    minimizeBtn.textContent = this.isMinimized ? '□' : '−';
                    minimizeBtn.title = this.isMinimized ? 'Restore' : 'Minimize';
                }
            }
        }

        updateConfig(newOptions) {
            this.options = this.mergeConfig(this.options, newOptions);
            this.applyTheme();
            
            // Update title if changed
            const titleElement = this.element.querySelector('.chatbot-title');
            if (titleElement) {
                titleElement.textContent = this.options.title;
            }
            
            // Update assistant name in avatar if changed
            const avatars = this.element.querySelectorAll('.message.assistant .message-avatar');
            avatars.forEach(avatar => {
                if (!avatar.querySelector('img')) {
                    avatar.textContent = this.options.assistant.name.charAt(0).toUpperCase();
                }
            });
        }

        clearChat() {
            const messagesContainer = this.element.querySelector('.chatbot-messages');
            messagesContainer.innerHTML = '';
            this.messages = [];
            
            if (this.options.assistant.welcomeMessage) {
                this.addMessage('assistant', this.options.assistant.welcomeMessage);
            }
        }

        isVisible() {
            return this.isVisible;
        }

        destroy() {
            if (this.element) {
                this.element.remove();
            }
            if (this.toggleBtn) {
                this.toggleBtn.remove();
            }
            if (this.connection) {
                this.connection.close();
            }
        }

        // Public API methods
        sendProgrammaticMessage(message) {
            this.addMessage('user', message);
            this.simulateAssistantResponse(message);
        }

        getConversationHistory() {
            return [...this.messages];
        }

        exportConversation() {
            return {
                timestamp: new Date().toISOString(),
                messages: this.messages,
                config: {
                    assistantName: this.options.assistant.name,
                    title: this.options.title
                }
            };
        }
    }

    // Static methods for ChatBot
    ChatBot.instances = [];

    ChatBot.init = function(containerOrOptions, options) {
        let container = null;
        let config = {};

        if (typeof containerOrOptions === 'string' || 
            containerOrOptions instanceof HTMLElement) {
            // Inline mode
            container = containerOrOptions;
            config = options || {};
        } else {
            // Floating mode
            config = containerOrOptions || {};
            config.mode = 'floating';
        }

        const instance = new ChatBot(container, config);
        ChatBot.instances.push(instance);
        return instance;
    };

    ChatBot.destroyAll = function() {
        ChatBot.instances.forEach(instance => instance.destroy());
        ChatBot.instances = [];
    };

    ChatBot.getInstances = function() {
        return [...ChatBot.instances];
    };

    // Utility functions for common integrations
    ChatBot.utils = {
        // Auto-detect user preferences
        detectUserPreferences() {
            return {
                theme: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
                reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                language: navigator.language || 'en'
            };
        },

        // Generate unique session ID
        generateSessionId() {
            return 'chatbot_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        },

        // Storage helpers
        saveToStorage(key, data) {
            try {
                sessionStorage.setItem(key, JSON.stringify(data));
            } catch (e) {
                console.warn('ChatBot: Could not save to storage', e);
            }
        },

        loadFromStorage(key) {
            try {
                const data = sessionStorage.getItem(key);
                return data ? JSON.parse(data) : null;
            } catch (e) {
                console.warn('ChatBot: Could not load from storage', e);
                return null;
            }
        }
    };

    // Make ChatBot available globally
    window.ChatBot = ChatBot;

    // Auto-initialize if data attributes are found
    document.addEventListener('DOMContentLoaded', function() {
        const autoInitElements = document.querySelectorAll('[data-chatbot]');
        
        autoInitElements.forEach(element => {
            const config = {};
            
            // Parse data attributes
            for (const attr of element.attributes) {
                if (attr.name.startsWith('data-chatbot-')) {
                    const key = attr.name.replace('data-chatbot-', '').replace(/-([a-z])/g, 
                        (match, letter) => letter.toUpperCase());
                    
                    let value = attr.value;
                    if (value === 'true') value = true;
                    if (value === 'false') value = false;
                    if (!isNaN(value) && value !== '') value = Number(value);
                    
                    config[key] = value;
                }
            }
            
            ChatBot.init(element, config);
        });
    });

    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden - could pause connections
            ChatBot.instances.forEach(instance => {
                if (instance.isConnected) {
                    instance.updateConnectionStatus('paused', 'Connection paused');
                }
            });
        } else {
            // Page is visible - resume connections
            ChatBot.instances.forEach(instance => {
                if (instance.isConnected) {
                    instance.updateConnectionStatus('connected', 'Connected');
                }
            });
        }
    });

})(window, document);