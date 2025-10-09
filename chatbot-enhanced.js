/**
 * Enhanced GPT Chatbot Widget - Supports both Chat Completions and Responses API
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
        position: 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
        title: 'Chat Assistant',
        height: '400px',
        width: '350px',
        show: false,

        // API settings
        apiType: 'responses', // 'chat' or 'responses'
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

        // Responses-specific settings
        responsesConfig: {
            promptId: '',
            promptVersion: '',
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

        layout: {
            density: 'comfortable', // 'compact', 'comfortable', 'spacious'
            floatingTogglePosition: 'auto',
            header: {
                showAvatar: true,
                showTitle: true,
                showStatusBadge: true,
                showApiTypePill: true,
                showPoweredBy: true,
                showMaximize: true,
                showClose: true
            },
            badges: {
                showConnection: true,
                showActiveMode: true
            },
            container: {
                width: null,
                height: null,
                minHeight: '320px',
                maxHeight: '85vh'
            }
        },

        timeline: {
            showTimestamps: undefined,
            showApiBadges: true,
            showRoleAvatars: true,
            showToolEvents: true
        },

        accessibility: {
            ariaLabel: 'AI assistant chat window',
            liveRegion: 'polite',
            announceErrors: true
        },

        proactive: {
            enabled: false,
            message: 'Hi there! Need any help?',
            delay: 5000,
            autoOpen: false,
            autoOpenDelay: 6000,
            dismissForHours: 12,
            storageKey: 'chatbot-proactive-dismissed',
            ctas: [
                // { label: 'Ask a question', type: 'message', message: 'What can you do?' }
            ]
        },

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
            this.layoutOptions = this.options.layout || {};
            this.timelineOptions = Object.assign({
                showTimestamps: typeof this.options.timestamps === 'boolean' ? this.options.timestamps : undefined,
                showApiBadges: true,
                showRoleAvatars: true,
                showToolEvents: true
            }, this.options.timeline || {});
            if (typeof this.timelineOptions.showTimestamps === 'undefined') {
                this.timelineOptions.showTimestamps = !!this.options.timestamps;
            }
            this.options.timestamps = this.timelineOptions.showTimestamps;
            this.accessibilityOptions = Object.assign({}, DEFAULT_CONFIG.accessibility, this.options.accessibility || {});
            this.proactiveOptions = Object.assign({}, DEFAULT_CONFIG.proactive, this.options.proactive || {});

            // Backwards compatibility: map deprecated assistantConfig into responsesConfig
            if (options.assistantConfig && !options.responsesConfig) {
                this.options.responsesConfig = Object.assign({}, options.assistantConfig);
            }

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
            this.proactiveTimer = null;
            this.proactiveAutoOpenTimer = null;

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
            this.applyLayoutPreferences();
        }

        /**
         * Create floating widget structure
         */
        createFloatingWidget() {
            const widget = document.createElement('div');
            widget.classList.add('chatbot-widget', 'chatbot-floating', 'enhanced');

            const placement = this.resolveFloatingPosition();
            widget.classList.add(`position-${placement}`);

            const apiBadge = this.options.apiType === 'chat' ? 'CHAT' : 'RESP';

            widget.innerHTML = `
                <div class="chatbot-toggle" title="Open Chat" role="button" aria-expanded="false">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 13.54 2.36 14.99 3.01 16.28L2.1 21.9L7.72 20.99C9.01 21.64 10.46 22 12 22C17.52 22 22 17.52 22 12S17.52 2 12 2Z" fill="currentColor"/>
                    </svg>
                    <div class="api-indicator" aria-hidden="true">${apiBadge}</div>
                </div>
                <div class="chatbot-container" style="display: none;" aria-hidden="true">
                    ${this.getWidgetContent()}
                </div>
                ${this.proactiveOptions.enabled ? this.getProactivePromptHTML() : ''}
            `;

            return widget;
        }

        /**
         * Resolve floating widget position class
         */
        resolveFloatingPosition() {
            const available = ['bottom-right', 'bottom-left', 'top-right', 'top-left'];
            const layoutPosition = this.layoutOptions.floatingTogglePosition;
            const preferred = (layoutPosition && layoutPosition !== 'auto') ? layoutPosition : this.options.position;
            return available.includes(preferred) ? preferred : 'bottom-right';
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
                ${this.proactiveOptions.enabled ? this.getProactivePromptHTML() : ''}
            `;
            return widget;
        }

        /**
         * Build proactive prompt markup when enabled
         */
        getProactivePromptHTML() {
            const ctas = Array.isArray(this.proactiveOptions.ctas) ? this.proactiveOptions.ctas : [];
            const actions = ctas.map((cta, index) => {
                const label = this.escapeHtml(cta.label || 'Start chat');
                return `<button type="button" class="proactive-cta" data-cta-index="${index}">${label}</button>`;
            }).join('');

            return `
                <div class="chatbot-proactive" role="dialog" aria-live="polite" aria-hidden="true" hidden>
                    <div class="proactive-inner">
                        <button type="button" class="proactive-dismiss" aria-label="Dismiss prompt">✕</button>
                        <div class="proactive-message">${this.escapeHtml(this.proactiveOptions.message || '')}</div>
                        ${actions ? `<div class="proactive-actions">${actions}</div>` : ''}
                    </div>
                </div>
            `;
        }

        /**
         * Get widget content HTML
         */
        getWidgetContent() {
            const headerOptions = this.layoutOptions.header || {};
            const badgeOptions = this.layoutOptions.badges || {};
            const showAvatar = headerOptions.showAvatar !== false && this.timelineOptions.showRoleAvatars !== false;
            const showTitle = headerOptions.showTitle !== false;
            const showStatus = headerOptions.showStatusBadge !== false;
            const showApiType = headerOptions.showApiTypePill !== false;
            const showMaximize = headerOptions.showMaximize !== false;
            const showClose = headerOptions.showClose !== false && this.options.mode === 'floating';
            const showPoweredBy = headerOptions.showPoweredBy !== false;
            const showConnectionBadge = badgeOptions.showConnection !== false;
            const showModeChip = badgeOptions.showActiveMode !== false;

            const apiLabel = this.options.apiType.toUpperCase();

            return `
                <div class="chatbot-header" role="banner">
                    <div class="chatbot-header-info">
                        ${showAvatar ?
                            (this.options.assistant.avatar ?
                                `<img src="${this.options.assistant.avatar}" alt="${this.escapeHtml(this.options.assistant.name || 'Assistant')} avatar" class="chatbot-avatar">` :
                                '<div class="chatbot-avatar-placeholder" aria-hidden="true"></div>'
                            ) : ''
                        }
                        <div class="chatbot-header-text">
                            ${showTitle ? `<h3 class="chatbot-title">${this.options.title}</h3>` : ''}
                            ${showStatus ? `
                                <p class="chatbot-status" role="status" aria-live="polite">
                                    <span class="chatbot-status-indicator disconnected" aria-hidden="true"></span>
                                    <span class="chatbot-status-text">Offline</span>
                                    ${showConnectionBadge ? '<span class="chatbot-connection-badge" data-status="offline">Offline</span>' : ''}
                                    ${showModeChip ? '<span class="chatbot-mode-chip" data-mode="auto">Auto</span>' : ''}
                                    ${showApiType ? `<span class="chatbot-api-type" aria-label="${apiLabel} API mode">${apiLabel}</span>` : ''}
                                </p>
                            ` : ''}
                        </div>
                    </div>
                    <div class="chatbot-header-controls">
                        ${showMaximize ? '<button class="chatbot-maximize" title="Maximize" aria-pressed="false">▢</button>' : ''}
                        ${showClose ? '<button class="chatbot-close" title="Close Chat">✕</button>' : ''}
                    </div>
                </div>
                <div class="chatbot-messages" id="chatbot-messages-${this.conversationId}" role="log" aria-live="${this.accessibilityOptions.liveRegion}" aria-relevant="additions text">
                    <!-- Messages will be added here -->
                </div>
                <div class="chatbot-typing" id="chatbot-typing-${this.conversationId}" style="display: none;" role="status" aria-live="polite">
                    <div class="chatbot-typing-indicator" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="chatbot-typing-text">${this.options.assistant.thinking}</span>
                </div>
                ${this.options.enableFileUpload ? this.getFilePreviewHTML() : ''}
                <div class="chatbot-input-container" role="form" aria-label="Message composer">
                    <div class="chatbot-input-wrapper">
                        ${this.options.enableFileUpload ? this.getFileInputHTML() : ''}
                        <textarea
                            class="chatbot-input"
                            placeholder="${this.options.assistant.placeholder}"
                            rows="1"
                            aria-label="${this.accessibilityOptions.ariaLabel} message input"
                            id="chatbot-input-${this.conversationId}"></textarea>
                        <button class="chatbot-send" title="Send Message" id="chatbot-send-${this.conversationId}" aria-label="Send message">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                                <path d="M2.01 21L23 12L2.01 3L2 10L17 12L2 14L2.01 21Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                    ${showPoweredBy ? `
                        <div class="chatbot-footer">
                            <small class="chatbot-powered-by">
                                Powered by <a href="#" target="_blank" rel="noopener">OpenAI ${this.options.apiType === 'chat' ? 'Chat Completions' : 'Responses'}</a>
                            </small>
                        </div>
                    ` : ''}
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
            this.maximizeButton = this.widget.querySelector('.chatbot-maximize');
            this.header = this.widget.querySelector('.chatbot-header');
            this.statusIndicator = this.widget.querySelector('.chatbot-status-indicator');
            this.statusText = this.widget.querySelector('.chatbot-status-text');
            this.connectionBadge = this.widget.querySelector('.chatbot-connection-badge');
            this.modeChip = this.widget.querySelector('.chatbot-mode-chip');
            this.inputWrapper = this.widget.querySelector('.chatbot-input-wrapper');
            this.proactiveBanner = this.widget.querySelector('.chatbot-proactive');

            if (this.options.enableFileUpload) {
                this.fileInput = this.widget.querySelector('.chatbot-file-input');
                this.fileButton = this.widget.querySelector('.chatbot-file-button');
                this.filePreview = this.widget.querySelector('.chatbot-file-preview');
            }
        }

        /**
         * Apply layout classes and sizing tokens
         */
        applyLayoutPreferences() {
            if (!this.widget) return;

            const density = this.layoutOptions.density || 'comfortable';
            ['layout-compact', 'layout-comfortable', 'layout-spacious'].forEach(cls => this.widget.classList.remove(cls));
            this.widget.classList.add(`layout-${density}`);

            const containerConfig = this.layoutOptions.container || {};
            const width = containerConfig.width || this.options.width;
            const height = containerConfig.height || this.options.height;
            const minHeight = containerConfig.minHeight || '320px';
            const maxHeight = containerConfig.maxHeight || '85vh';

            if (this.widget) {
                this.widget.style.setProperty('--chatbot-width', width || '350px');
                this.widget.style.setProperty('--chatbot-height', height || '500px');
                this.widget.style.setProperty('--chatbot-min-height', minHeight);
                this.widget.style.setProperty('--chatbot-max-height', maxHeight);
            }

            if (this.chatContainer) {
                this.chatContainer.setAttribute('role', 'region');
                this.chatContainer.setAttribute('aria-label', this.accessibilityOptions.ariaLabel);
                this.chatContainer.setAttribute('aria-hidden', this.options.mode === 'floating' ? 'true' : 'false');
            }

            if (this.messageContainer) {
                this.messageContainer.setAttribute('tabindex', '0');
            }

            if (this.toggleButton) {
                this.toggleButton.setAttribute('aria-controls', `chatbot-messages-${this.conversationId}`);
                this.toggleButton.setAttribute('aria-label', `Open ${this.options.title}`);
            }

            if (this.options.mode === 'floating') {
                const placement = this.resolveFloatingPosition();
                ['position-bottom-right', 'position-bottom-left', 'position-top-right', 'position-top-left'].forEach(cls => this.widget.classList.remove(cls));
                this.widget.classList.add(`position-${placement}`);
            }

            if (this.proactiveBanner) {
                this.setupProactivePrompt();
            }

            this.setActiveMode(this.connectionType || 'auto');
        }

        /**
         * Initialise proactive prompt timers and bindings
         */
        setupProactivePrompt() {
            if (!this.proactiveOptions.enabled || !this.proactiveBanner) {
                return;
            }

            if (this.isProactiveSuppressed()) {
                this.proactiveBanner.hidden = true;
                this.proactiveBanner.setAttribute('aria-hidden', 'true');
                return;
            }

            const dismissButton = this.proactiveBanner.querySelector('.proactive-dismiss');
            if (dismissButton && !dismissButton.dataset.bound) {
                dismissButton.dataset.bound = 'true';
                dismissButton.addEventListener('click', () => {
                    this.hideProactivePrompt();
                    this.persistProactiveSuppression();
                });
            }

            const ctaButtons = Array.from(this.proactiveBanner.querySelectorAll('.proactive-cta'));
            ctaButtons.forEach((button) => {
                if (!button.dataset.bound) {
                    button.dataset.bound = 'true';
                    button.addEventListener('click', (event) => {
                        const index = Number(event.currentTarget.dataset.ctaIndex || 0);
                        const ctaConfig = Array.isArray(this.proactiveOptions.ctas) ? this.proactiveOptions.ctas[index] : null;
                        this.handleProactiveCTA(ctaConfig);
                    });
                }
            });

            clearTimeout(this.proactiveTimer);
            clearTimeout(this.proactiveAutoOpenTimer);
            const delay = Math.max(0, Number(this.proactiveOptions.delay || 0));
            this.proactiveTimer = setTimeout(() => {
                if (this.isProactiveSuppressed()) {
                    return;
                }

                this.showProactivePrompt();

                if (this.proactiveOptions.autoOpen) {
                    const autoDelay = Math.max(0, Number(this.proactiveOptions.autoOpenDelay || delay));
                    clearTimeout(this.proactiveAutoOpenTimer);
                    this.proactiveAutoOpenTimer = setTimeout(() => {
                        if (!this.isProactiveSuppressed()) {
                            this.show();
                        }
                    }, autoDelay);
                }
            }, delay);
        }

        /**
         * Determine if proactive prompt should be suppressed
         */
        isProactiveSuppressed() {
            const key = this.proactiveOptions.storageKey || 'chatbot-proactive-dismissed';
            const now = Date.now();

            try {
                const stored = window.localStorage.getItem(key);
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (parsed && parsed.expiresAt && Number(parsed.expiresAt) > now) {
                        return true;
                    }
                }
            } catch (error) {
                // localStorage may be unavailable; fall back to cookies
            }

            const cookieValue = this.readCookie(key);
            if (cookieValue) {
                const expires = Number(cookieValue);
                if (!Number.isNaN(expires) && expires > now) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Persist proactive suppression marker
         */
        persistProactiveSuppression() {
            const key = this.proactiveOptions.storageKey || 'chatbot-proactive-dismissed';
            const hours = Math.max(0.25, Number(this.proactiveOptions.dismissForHours || 12));
            const expiresAt = Date.now() + hours * 60 * 60 * 1000;

            const payload = JSON.stringify({ expiresAt });
            try {
                window.localStorage.setItem(key, payload);
            } catch (error) {
                // Ignore write failure (storage disabled)
            }

            const maxAge = Math.round(hours * 60 * 60);
            document.cookie = `${key}=${expiresAt}; path=/; max-age=${maxAge}; SameSite=Lax`;
        }

        /**
         * Show proactive prompt banner
         */
        showProactivePrompt() {
            if (!this.proactiveBanner) return;

            this.proactiveBanner.hidden = false;
            this.proactiveBanner.setAttribute('aria-hidden', 'false');
            this.proactiveBanner.classList.add('is-visible');
        }

        /**
         * Hide proactive prompt banner
         */
        hideProactivePrompt() {
            if (!this.proactiveBanner) return;

            this.proactiveBanner.classList.remove('is-visible');
            this.proactiveBanner.setAttribute('aria-hidden', 'true');
            this.proactiveBanner.hidden = true;
        }

        /**
         * Handle CTA interaction from proactive prompt
         */
        handleProactiveCTA(cta) {
            if (!cta) {
                this.show();
                this.persistProactiveSuppression();
                this.hideProactivePrompt();
                return;
            }

            const dismissOnClick = cta.dismissOnClick !== false;

            if (cta.type === 'message' && cta.message) {
                this.show();
                if (this.inputField) {
                    this.inputField.value = cta.message;
                    this.autoResizeTextarea();
                    this.inputField.focus();
                }
                if (cta.autoSend) {
                    this.handleSend();
                }
            } else {
                this.show();
            }

            if (dismissOnClick) {
                this.persistProactiveSuppression();
                this.hideProactivePrompt();
            }
        }

        /**
         * Read cookie helper
         */
        readCookie(name) {
            if (typeof document === 'undefined') return null;
            const value = document.cookie || '';
            const parts = value.split(';').map(part => part.trim());
            for (const part of parts) {
                if (!part) continue;
                const [cookieName, ...rest] = part.split('=');
                if (cookieName === name) {
                    return rest.join('=');
                }
            }
            return null;
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
                    --chatbot-width: ${widget && widget.style.getPropertyValue('--chatbot-width') ? widget.style.getPropertyValue('--chatbot-width') : (this.layoutOptions.container && this.layoutOptions.container.width) || this.options.width};
                    --chatbot-height: ${widget && widget.style.getPropertyValue('--chatbot-height') ? widget.style.getPropertyValue('--chatbot-height') : (this.layoutOptions.container && this.layoutOptions.container.height) || this.options.height};
                    --chatbot-min-height: ${widget && widget.style.getPropertyValue('--chatbot-min-height') ? widget.style.getPropertyValue('--chatbot-min-height') : (this.layoutOptions.container && this.layoutOptions.container.minHeight) || '320px'};
                    --chatbot-max-height: ${widget && widget.style.getPropertyValue('--chatbot-max-height') ? widget.style.getPropertyValue('--chatbot-max-height') : (this.layoutOptions.container && this.layoutOptions.container.maxHeight) || '85vh'};
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

            // Maximize/restore button
            if (this.maximizeButton) {
                this.maximizeButton.addEventListener('click', () => this.toggleMaximize());
            }

            // Double-click header to toggle maximize
            if (this.header) {
                this.header.addEventListener('dblclick', () => this.toggleMaximize());
            }

            // Send button
            if (this.sendButton) {
                this.sendButton.addEventListener('click', () => this.handleSend());
            }

            // Input field
            if (this.inputField) {
                this.inputField.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.handleSend();
                    }
                });

                // Auto-resize textarea
                this.inputField.addEventListener('input', () => {
                    this.autoResizeTextarea();
                    this.clearComposerError();
                });

                this.inputField.addEventListener('focus', () => this.clearComposerError());
            }

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

            if (this.widget && this.options.mode === 'floating') {
                this.widget.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && this.isOpen()) {
                        event.preventDefault();
                        this.hide();
                        if (this.toggleButton) {
                            this.toggleButton.focus();
                        }
                    }
                });
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

            this.clearComposerError();

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
            this.setComposerBusy(true);

            try {
                // Prepare request data
                const requestData = {
                    message: message,
                    conversation_id: this.conversationId,
                    api_type: this.options.apiType
                };

                if (this.options.apiType === 'responses') {
                    const responsesConfig = this.options.responsesConfig || {};
                    Object.entries(responsesConfig).forEach(([key, value]) => {
                        if (value === undefined || value === null || value === '') {
                            return;
                        }

                        const normalizedKey = key.replace(/([A-Z])/g, '_$1').toLowerCase();
                        if (normalizedKey.length === 0) {
                            return;
                        }

                        requestData[normalizedKey] = value;
                    });
                }

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
                this.setComposerBusy(false);
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
                        this.setActiveMode('websocket');
                        this.setConnectionStatus(true);

                        ws.send(JSON.stringify(requestData));
                        resolve(true);
                    };

                    ws.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        this.handleStreamChunk(data);
                    };

                    ws.onerror = () => {
                        this.setConnectionStatus(false);
                        this.setActiveMode('auto');
                        resolve(false);
                    };

                    ws.onclose = () => {
                        this.setConnectionStatus(false);
                        this.setActiveMode('auto');
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

                    // If files are present, skip SSE (EventSource only supports GET)
                    if (requestData.file_data && requestData.file_data.length) {
                        resolve(false);
                        return;
                    }

                    const params = new URLSearchParams({
                        message: requestData.message || '',
                        conversation_id: requestData.conversation_id || '',
                        api_type: requestData.api_type || this.options.apiType || 'chat'
                    });

                    if (requestData.prompt_id) {
                        params.set('prompt_id', `${requestData.prompt_id}`);
                    }

                    if (requestData.prompt_version) {
                        params.set('prompt_version', `${requestData.prompt_version}`);
                    }

                    const url = `${this.options.apiEndpoint}?${params.toString()}`;
                    const eventSource = new EventSource(url);

                    this.eventSource = eventSource;
                    this.connectionType = 'sse';
                    this.setActiveMode('sse');

                    eventSource.onopen = () => {
                        this.setConnectionStatus(true);
                        resolve(true);
                    };

                    eventSource.onmessage = (event) => {
                        try {
                            const data = JSON.parse(event.data);
                            this.handleStreamChunk(data);
                        } catch (e) {
                            // Ignore non-JSON keep-alive lines
                        }
                    };

                    eventSource.onerror = () => {
                        try { eventSource.close(); } catch (_) {}
                        this.setConnectionStatus(false);
                        this.setActiveMode('auto');
                        resolve(false);
                    };

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
            this.setActiveMode('ajax');

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

            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response payload');
            }

            if (data.error) {
                const errorMessage = typeof data.error === 'string' ? data.error : (data.error.message || 'Unknown error');
                throw new Error(errorMessage);
            }

            const content = typeof data.response === 'string' && data.response !== ''
                ? data.response
                : (typeof data.content === 'string' ? data.content : '');

            if (content === '') {
                throw new Error('Empty response payload');
            }

            this.setConnectionStatus(true);
            this.addMessage({
                role: 'assistant',
                content,
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

                if (data.response_id && this.currentMessageElement) {
                    this.currentMessageElement.dataset.responseId = data.response_id;
                }

                if (data.run_id) {
                    this.currentRunId = data.run_id;
                }

            } else if (data.type === 'chunk') {
                const chunkContent = this.resolveStreamText(data);
                if (this.currentMessageElement && chunkContent) {
                    this.appendToMessage(this.currentMessageElement, chunkContent);
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
         * Normalize streaming payloads from Chat Completions or Responses API
         */
        resolveStreamText(data) {
            if (typeof data.content === 'string') {
                return data.content;
            }

            if (Array.isArray(data.content)) {
                return data.content.join('');
            }

            if (data.delta) {
                if (typeof data.delta === 'string') {
                    return data.delta;
                }

                if (typeof data.delta === 'object') {
                    if (typeof data.delta.text === 'string') {
                        return data.delta.text;
                    }

                    if (typeof data.delta.output_text === 'string') {
                        return data.delta.output_text;
                    }

                    if (Array.isArray(data.delta.content)) {
                        return data.delta.content
                            .map(segment => (segment && segment.text) ? segment.text : '')
                            .join('');
                    }
                }
            }

            return '';
        }

        /**
         * Handle tool calls from Responses API
         */
        handleToolCall(data) {
            const toolName = data.tool_name;
            let toolArgs = data.arguments;
            const callId = data.call_id || null;

            if (this.timelineOptions.showToolEvents === false) {
                return;
            }

            if (typeof toolArgs === 'string') {
                try {
                    const parsed = JSON.parse(toolArgs);
                    toolArgs = parsed;
                } catch (err) {
                    // Keep plain string when parsing fails
                }
            }

            // Show tool execution in UI
            if (this.currentMessageElement) {
                let toolIndicator = null;
                if (callId) {
                    toolIndicator = this.currentMessageElement.querySelector(`.tool-execution[data-call-id="${callId}"]`);
                }

                if (!toolIndicator) {
                    toolIndicator = document.createElement('div');
                    toolIndicator.className = 'tool-execution';
                    if (callId) {
                        toolIndicator.dataset.callId = callId;
                    }

                    toolIndicator.innerHTML = `
                        <div class="tool-info">
                            <span class="tool-icon">🔧</span>
                            <span class="tool-name">Executing: ${toolName}</span>
                            ${data.status === 'completed' ? '<span class="tool-status">(completed)</span>' : ''}
                        </div>
                    `;

                    const bubble = this.currentMessageElement.querySelector('.chatbot-message-bubble');
                    bubble.appendChild(toolIndicator);
                } else {
                    const statusNode = toolIndicator.querySelector('.tool-status');
                    if (data.status === 'completed') {
                        if (statusNode) {
                            statusNode.textContent = '(completed)';
                        } else {
                            const statusEl = document.createElement('span');
                            statusEl.className = 'tool-status';
                            statusEl.textContent = '(completed)';
                            toolIndicator.querySelector('.tool-info').appendChild(statusEl);
                        }
                    }
                }

                if (toolArgs && typeof toolArgs === 'object') {
                    const argsBlock = document.createElement('pre');
                    argsBlock.className = 'tool-arguments';
                    argsBlock.textContent = JSON.stringify(toolArgs, null, 2);
                    toolIndicator.appendChild(argsBlock);
                } else if (toolArgs) {
                    const argsText = document.createElement('div');
                    argsText.className = 'tool-arguments';
                    argsText.textContent = toolArgs;
                    toolIndicator.appendChild(argsText);
                }
            }

            // Trigger callback
            if (this.options.onToolCall) {
                this.options.onToolCall({
                    name: toolName,
                    arguments: toolArgs,
                    runId: this.currentRunId,
                    status: data.status || 'in_progress'
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
            messageDiv.dataset.role = message.role;
            messageDiv.setAttribute('role', 'article');
            messageDiv.setAttribute('tabindex', '-1');

            if (message.error) {
                messageDiv.classList.add('chatbot-message-error');
                messageDiv.setAttribute('aria-live', 'assertive');
            }

            const content = document.createElement('div');
            content.className = 'chatbot-message-content';

            if (message.role === 'assistant' && this.options.assistant.avatar && this.timelineOptions.showRoleAvatars !== false) {
                const avatar = document.createElement('img');
                avatar.src = this.options.assistant.avatar;
                avatar.className = 'chatbot-message-avatar';
                messageDiv.appendChild(avatar);
            }

            const bubble = document.createElement('div');
            bubble.className = 'chatbot-message-bubble';

            // Add API type indicator
            if (message.apiType && this.timelineOptions.showApiBadges !== false) {
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
                messageDiv.classList.add('has-files');
            }

            if (this.options.timestamps && message.timestamp) {
                const timestamp = document.createElement('div');
                timestamp.className = 'chatbot-message-timestamp';
                timestamp.textContent = this.formatTimestamp(message.timestamp);
                bubble.appendChild(timestamp);
            }

            if (message.role === 'assistant' && this.timelineOptions.showToolEvents === false) {
                bubble.classList.add('hide-tool-events');
            }

            content.appendChild(bubble);
            messageDiv.appendChild(content);

            messageDiv.dataset.rawContent = initialContent;
            const speakerLabel = message.role === 'user' ? 'User' : (message.role === 'assistant' ? (this.options.assistant.name || 'Assistant') : 'System');
            messageDiv.setAttribute('aria-label', `${speakerLabel} message`);

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
                return this.escapeHtml(content || '');
            }

            let text = (content || '').replace(/\r\n/g, '\n');
            const codeBlocks = [];

            text = text.replace(/```(\w+)?\n?([\s\S]*?)```/g, (match, lang, code) => {
                const index = codeBlocks.length;
                const languageAttr = lang ? ` data-language="${this.escapeHtml(lang)}"` : '';
                codeBlocks.push(`<pre><code${languageAttr}>${this.escapeHtml(code.trimEnd())}</code></pre>`);
                return `{{CODE_BLOCK_${index}}}`;
            });

            text = this.escapeHtml(text);

            // Headings
            text = text.replace(/^###### (.+)$/gm, '<h6>$1</h6>')
                .replace(/^##### (.+)$/gm, '<h5>$1</h5>')
                .replace(/^#### (.+)$/gm, '<h4>$1</h4>')
                .replace(/^### (.+)$/gm, '<h3>$1</h3>')
                .replace(/^## (.+)$/gm, '<h2>$1</h2>')
                .replace(/^# (.+)$/gm, '<h1>$1</h1>');

            // Blockquotes
            text = text.replace(/^>\s?(.+)$/gm, '<blockquote>$1</blockquote>');

            // Links
            text = text.replace(/\[([^\]]+)]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

            // Bold / italic / strikethrough
            text = text.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                .replace(/~~(.+?)~~/g, '<del>$1</del>');

            // Inline code
            text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

            // Lists
            text = text.replace(/(^|\n)(\s*[-*+] .+(?:\n\s*[-*+] .+)*)/g, (match, prefix, list) => {
                const items = list.trim().split(/\n/).map(item => `<li>${item.replace(/^\s*[-*+]\s*/, '')}</li>`).join('');
                return `${prefix}<ul>${items}</ul>`;
            });

            text = text.replace(/(^|\n)(\s*\d+[.)] .+(?:\n\s*\d+[.)] .+)*)/g, (match, prefix, list) => {
                const items = list.trim().split(/\n/).map(item => `<li>${item.replace(/^\s*\d+[.)]\s*/, '')}</li>`).join('');
                return `${prefix}<ol>${items}</ol>`;
            });

            // Tables (very lightweight): convert pipes into table structure
            text = text.replace(/(^|\n)(\|.+\|\n)(\|[\-\s:]+\|\n)((?:\|.*\|\n?)*)/g, (match) => {
                const rows = match.trim().split(/\n/).filter(Boolean);
                if (rows.length < 2) return match;
                const headerCells = rows[0].split('|').filter(cell => cell.trim().length > 0).map(cell => `<th>${cell.trim()}</th>`).join('');
                const bodyRows = rows.slice(2).map(row => {
                    const cells = row.split('|').filter(cell => cell.trim().length > 0).map(cell => `<td>${cell.trim()}</td>`).join('');
                    return `<tr>${cells}</tr>`;
                }).join('');
                return `<table><thead><tr>${headerCells}</tr></thead><tbody>${bodyRows}</tbody></table>`;
            });

            // Paragraph and line breaks
            text = text.replace(/\n{2,}/g, '</p><p>');
            text = text.replace(/\n/g, '<br>');
            text = `<p>${text}</p>`;
            text = text.replace(/<p><\/p>/g, '');
            text = text.replace(/<p>(<(?:ul|ol|table|blockquote|h[1-6])>)/g, '$1');
            text = text.replace(/(<\/(?:ul|ol|table|blockquote|h[1-6])>)<\/p>/g, '$1');
            text = text.replace(/<br>(<(?:ul|ol|table|blockquote|h[1-6])>)/g, '$1');
            text = text.replace(/(<\/(?:ul|ol|table|blockquote|h[1-6])>)<br>/g, '$1');

            codeBlocks.forEach((block, index) => {
                text = text.replace(`{{CODE_BLOCK_${index}}}`, block);
            });

            return text;
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
                this.statusText.textContent = connected ? 'Online' : 'Offline';
            }

            if (this.connectionBadge) {
                this.connectionBadge.dataset.status = connected ? 'online' : 'offline';
                this.connectionBadge.textContent = connected ? 'Online' : 'Offline';
            }

            if (this.widget) {
                this.widget.setAttribute('data-connection', connected ? 'online' : 'offline');
            }

            if (connected && this.options.onConnect) {
                this.options.onConnect();
            } else if (!connected && this.options.onDisconnect) {
                this.options.onDisconnect();
            }
        }

        /**
         * Update mode badge based on active transport
         */
        setActiveMode(mode) {
            if (!this.modeChip) return;

            let label = 'Auto';
            let modeKey = 'auto';

            switch (mode) {
                case 'websocket':
                    label = 'WebSocket';
                    modeKey = 'websocket';
                    break;
                case 'sse':
                    label = 'SSE';
                    modeKey = 'sse';
                    break;
                case 'ajax':
                    label = 'HTTP';
                    modeKey = 'http';
                    break;
                case 'chat':
                case 'responses':
                    label = mode.charAt(0).toUpperCase() + mode.slice(1);
                    modeKey = mode.toLowerCase();
                    break;
                default:
                    if (typeof mode === 'string' && mode.trim().length > 0 && mode !== 'auto') {
                        label = mode;
                        modeKey = mode.toLowerCase();
                    }
            }

            this.modeChip.dataset.mode = modeKey;
            this.modeChip.textContent = label;
        }

        /**
         * Handle errors and notify UI
         */
        handleError(error) {
            console.error('EnhancedChatBot error:', error);

            this.showError('Sorry, I encountered an error. Please try again.');
            this.setTyping(false);
            this.setComposerBusy(false);
            this.setComposerError();
            this.setConnectionStatus(false);

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
         * Toggle composer busy state visuals
         */
        setComposerBusy(isBusy) {
            if (this.sendButton) {
                this.sendButton.disabled = !!isBusy;
                this.sendButton.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            }

            if (this.inputWrapper) {
                this.inputWrapper.classList.toggle('is-busy', !!isBusy);
                this.inputWrapper.classList.toggle('is-disabled', !!isBusy);
            }

            if (this.inputField) {
                if (isBusy) {
                    this.inputField.setAttribute('data-busy', 'true');
                } else {
                    this.inputField.removeAttribute('data-busy');
                }
            }
        }

        /**
         * Highlight composer error state
         */
        setComposerError() {
            if (this.inputWrapper) {
                this.inputWrapper.classList.add('has-error');
            }

            if (this.inputField) {
                this.inputField.setAttribute('aria-invalid', 'true');
            }
        }

        /**
         * Clear composer error state
         */
        clearComposerError() {
            if (this.inputWrapper) {
                this.inputWrapper.classList.remove('has-error');
            }

            if (this.inputField) {
                this.inputField.removeAttribute('aria-invalid');
            }
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

            if (this.chatContainer) {
                this.chatContainer.style.removeProperty('display');
                this.chatContainer.setAttribute('aria-hidden', 'false');
            }

            if (this.widget) {
                this.widget.classList.add('is-open');
            }

            if (this.toggleButton) {
                this.toggleButton.style.display = 'none';
                this.toggleButton.setAttribute('aria-expanded', 'true');
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

            if (this.chatContainer) {
                this.chatContainer.style.display = 'none';
                this.chatContainer.setAttribute('aria-hidden', 'true');
            }

            if (this.widget) {
                this.widget.classList.remove('is-open');
            }

            if (this.toggleButton) {
                this.toggleButton.style.display = 'flex';
                this.toggleButton.setAttribute('aria-expanded', 'false');
            }
        }

        /**
         * Toggle widget visibility
         */
        toggle() {
            if (this.options.mode !== 'floating') return;

            if (!this.isOpen()) {
                this.show();
            } else {
                this.hide();
            }
        }

        /**
         * Determine if widget is currently visible
         */
        isOpen() {
            if (this.options.mode !== 'floating') {
                return true;
            }
            return this.chatContainer && this.chatContainer.style.display !== 'none';
        }

        /**
         * Toggle maximize state
         */
        toggleMaximize() {
            this.isMaximized = !this.isMaximized;
            this.widget.classList.toggle('is-maximized', this.isMaximized);
            if (this.maximizeButton) {
                this.maximizeButton.setAttribute('aria-pressed', this.isMaximized ? 'true' : 'false');
                this.maximizeButton.title = this.isMaximized ? 'Restore' : 'Maximize';
            }
            this.scrollToBottom();
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
            this.setComposerError();
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
            const baseDefaults = (defaults && typeof defaults === 'object') ? defaults : (Array.isArray(defaults) ? defaults : {});
            const result = Array.isArray(baseDefaults) ? [...baseDefaults] : { ...baseDefaults };

            for (const key in options) {
                const defaultValue = baseDefaults ? baseDefaults[key] : undefined;
                const optionValue = options[key];

                if (
                    optionValue &&
                    typeof optionValue === 'object' &&
                    !Array.isArray(optionValue)
                ) {
                    const base = (defaultValue && typeof defaultValue === 'object' && !Array.isArray(defaultValue))
                        ? defaultValue
                        : {};
                    result[key] = this.mergeConfig(base, optionValue);
                } else {
                    result[key] = optionValue;
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

            if (this.proactiveTimer) {
                clearTimeout(this.proactiveTimer);
                this.proactiveTimer = null;
            }

            if (this.proactiveAutoOpenTimer) {
                clearTimeout(this.proactiveAutoOpenTimer);
                this.proactiveAutoOpenTimer = null;
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
