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

    /**
     * SecurityUtils - XSS Prevention and Input Sanitization
     * Provides methods to sanitize HTML, URLs, and filenames to prevent XSS attacks
     */
    const SecurityUtils = {
        /**
         * Sanitize HTML content using DOMPurify if available, fallback to escaping
         * @param {string} dirty - Untrusted HTML content
         * @param {object} options - DOMPurify configuration options
         * @returns {string} Sanitized HTML
         */
        sanitizeHTML(dirty, options = {}) {
            if (!dirty) return '';
            
            const defaultOptions = {
                ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a', 'p', 'br', 'ul', 'ol', 'li', 
                              'code', 'pre', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                              'table', 'thead', 'tbody', 'tr', 'th', 'td', 'del'],
                ALLOWED_ATTR: ['href', 'target', 'class', 'rel', 'data-language'],
                ALLOW_DATA_ATTR: false,
                ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
                KEEP_CONTENT: true
            };
            
            const config = Object.assign({}, defaultOptions, options);
            
            // Use DOMPurify if available (loaded via CDN)
            if (typeof DOMPurify !== 'undefined') {
                return DOMPurify.sanitize(dirty, config);
            }
            
            // Fallback to basic escaping if DOMPurify not loaded
            console.warn('DOMPurify not loaded - using basic HTML escaping. For better security, include DOMPurify.');
            return this.escapeHTML(dirty);
        },
        
        /**
         * Escape HTML special characters
         * @param {string} text - Text to escape
         * @returns {string} Escaped text
         */
        escapeHTML(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Sanitize text for use in attributes
         * @param {string} text - Text to sanitize
         * @returns {string} Sanitized text
         */
        sanitizeAttribute(text) {
            if (!text) return '';
            return text.replace(/[<>"']/g, char => {
                const escapeChars = {
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#x27;'
                };
                return escapeChars[char];
            });
        },
        
        /**
         * Sanitize URL to prevent dangerous protocols
         * @param {string} url - URL to sanitize
         * @returns {string|null} Sanitized URL or null if dangerous
         */
        sanitizeURL(url) {
            if (!url) return null;
            
            try {
                // Decode to catch encoded dangerous protocols
                const decoded = decodeURIComponent(url);
                
                // Check for dangerous protocols
                const dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
                const lowerURL = decoded.toLowerCase().trim();
                
                for (const protocol of dangerousProtocols) {
                    if (lowerURL.startsWith(protocol)) {
                        console.warn('Dangerous URL protocol detected and blocked:', protocol);
                        return null;
                    }
                }
                
                // Only allow safe protocols
                if (!/^(https?|mailto|tel):/.test(lowerURL) && !/^\//.test(lowerURL) && !/^#/.test(lowerURL)) {
                    console.warn('URL protocol not in whitelist:', url);
                    return null;
                }
                
                return url;
            } catch (e) {
                console.error('Error sanitizing URL:', e);
                return null;
            }
        },
        
        /**
         * Sanitize filename for display
         * @param {string} filename - Filename to sanitize
         * @returns {string} Sanitized filename
         */
        sanitizeFilename(filename) {
            if (!filename) return '';
            
            // Remove any HTML tags
            filename = filename.replace(/<[^>]*>/g, '');
            
            // Escape special characters
            return this.escapeHTML(filename);
        },
        
        /**
         * Make links safe by adding security attributes and validating URLs
         * @param {Element} container - Container element with links to sanitize
         */
        makeLinksSafe(container) {
            if (!container) return;
            
            const links = container.querySelectorAll('a');
            
            links.forEach(link => {
                const href = link.getAttribute('href');
                const sanitizedURL = this.sanitizeURL(href);
                
                if (!sanitizedURL) {
                    // Remove dangerous link, keep text
                    const text = document.createTextNode(link.textContent);
                    link.parentNode.replaceChild(text, link);
                    return;
                }
                
                // Update with sanitized URL
                link.setAttribute('href', sanitizedURL);
                
                // Add security attributes for external links
                if (sanitizedURL.startsWith('http')) {
                    link.setAttribute('rel', 'noopener noreferrer');
                    link.setAttribute('target', '_blank');
                    link.classList.add('external-link');
                }
            });
        }
    };

    /**
     * ConnectionManager - Robust connection handling with state machine, reconnection, and message queuing
     * Manages WebSocket, SSE, and AJAX connections with automatic failover
     */
    class ConnectionManager {
        constructor(options, chatbot) {
            this.options = options;
            this.chatbot = chatbot;
            this.state = 'disconnected'; // disconnected, connecting, connected, reconnecting
            this.transport = null; // 'websocket', 'sse', 'ajax', or null
            this.connection = null;
            this.reconnectAttempts = 0;
            this.maxReconnectAttempts = 10;
            this.reconnectDelay = 1000; // Start with 1s
            this.maxReconnectDelay = 30000; // Max 30s
            this.messageQueue = [];
            this.heartbeatTimer = null;
            this.heartbeatInterval = 30000; // 30s
            this.heartbeatTimeout = null;
            this.listeners = {};
            this.userDisconnected = false;
            this.reconnectTimer = null;
            this.connectionTimeout = null;
            this.currentRequestData = null;
            
            // Bind methods to preserve context
            this.handleWebSocketMessage = this.handleWebSocketMessage.bind(this);
            this.handleWebSocketClose = this.handleWebSocketClose.bind(this);
            this.handleWebSocketError = this.handleWebSocketError.bind(this);
            this.handleSSEMessage = this.handleSSEMessage.bind(this);
            this.handleSSEError = this.handleSSEError.bind(this);
        }
        
        /**
         * Connect with automatic transport fallback
         * @param {object} requestData - Initial request data for connection
         * @returns {Promise<boolean>} Success status
         */
        async connect(requestData) {
            if (this.state === 'connecting' || this.state === 'connected') {
                console.log('ConnectionManager: Already connecting or connected');
                return true;
            }
            
            this.setState('connecting');
            this.currentRequestData = requestData;
            
            // Determine transports to try based on streamingMode
            const transports = this.getTransportsToTry();
            
            for (const transport of transports) {
                try {
                    console.log(`ConnectionManager: Attempting ${transport} connection...`);
                    const success = await this.connectWithTransport(transport, requestData);
                    
                    if (success) {
                        this.setState('connected');
                        this.reconnectAttempts = 0;
                        this.transport = transport;
                        
                        if (transport === 'websocket') {
                            this.startHeartbeat();
                        }
                        
                        this.flushMessageQueue();
                        console.log(`ConnectionManager: Connected via ${transport}`);
                        return true;
                    }
                } catch (error) {
                    console.warn(`ConnectionManager: Failed to connect with ${transport}:`, error.message);
                    continue;
                }
            }
            
            // All transports failed
            console.error('ConnectionManager: All transports failed');
            this.setState('disconnected');
            this.scheduleReconnect();
            return false;
        }
        
        /**
         * Get list of transports to try based on configuration
         * @returns {Array<string>} Transport names in order of preference
         */
        getTransportsToTry() {
            const mode = this.options.streamingMode;
            
            if (mode === 'websocket' && this.options.websocketEndpoint) {
                return ['websocket'];
            } else if (mode === 'sse') {
                return ['sse'];
            } else if (mode === 'ajax') {
                return ['ajax'];
            } else {
                // Auto mode: try WebSocket → SSE → AJAX
                const transports = [];
                if (this.options.websocketEndpoint) {
                    transports.push('websocket');
                }
                transports.push('sse', 'ajax');
                return transports;
            }
        }
        
        /**
         * Connect using specific transport
         * @param {string} transport - Transport type
         * @param {object} requestData - Request data
         * @returns {Promise<boolean>} Success status
         */
        async connectWithTransport(transport, requestData) {
            // Clear any existing connection timeout
            if (this.connectionTimeout) {
                clearTimeout(this.connectionTimeout);
                this.connectionTimeout = null;
            }
            
            switch (transport) {
                case 'websocket':
                    return this.connectWebSocket(requestData);
                case 'sse':
                    return this.connectSSE(requestData);
                case 'ajax':
                    // AJAX doesn't maintain a persistent connection
                    this.transport = 'ajax';
                    return Promise.resolve(true);
                default:
                    throw new Error(`Unknown transport: ${transport}`);
            }
        }
        
        /**
         * WebSocket connection with error handling
         * @param {object} requestData - Initial request data
         * @returns {Promise<boolean>} Success status
         */
        connectWebSocket(requestData) {
            return new Promise((resolve, reject) => {
                if (!this.options.websocketEndpoint) {
                    reject(new Error('WebSocket endpoint not configured'));
                    return;
                }
                
                // Check if we already have an open connection
                if (this.connection && this.connection.readyState === WebSocket.OPEN) {
                    console.log('ConnectionManager: Reusing existing WebSocket connection');
                    this.connection.send(JSON.stringify(requestData));
                    resolve(true);
                    return;
                }
                
                let endpoint = this.options.websocketEndpoint;
                
                // Upgrade to wss:// if page is https://
                if (window.location.protocol === 'https:' && /^ws:\/\//i.test(endpoint)) {
                    endpoint = endpoint.replace(/^ws:\/\//i, 'wss://');
                }
                
                try {
                    const ws = new WebSocket(endpoint);
                    
                    // Connection timeout
                    this.connectionTimeout = setTimeout(() => {
                        console.warn('ConnectionManager: WebSocket connection timeout');
                        ws.close();
                        reject(new Error('WebSocket connection timeout'));
                    }, 5000);
                    
                    ws.onopen = () => {
                        clearTimeout(this.connectionTimeout);
                        this.connectionTimeout = null;
                        
                        this.connection = ws;
                        this.setupWebSocketHandlers(ws);
                        
                        // Send initial request
                        ws.send(JSON.stringify(requestData));
                        resolve(true);
                    };
                    
                    ws.onerror = (error) => {
                        clearTimeout(this.connectionTimeout);
                        this.connectionTimeout = null;
                        reject(error);
                    };
                    
                } catch (error) {
                    clearTimeout(this.connectionTimeout);
                    this.connectionTimeout = null;
                    reject(error);
                }
            });
        }
        
        /**
         * Setup WebSocket event handlers
         * @param {WebSocket} ws - WebSocket instance
         */
        setupWebSocketHandlers(ws) {
            ws.onmessage = this.handleWebSocketMessage;
            ws.onclose = this.handleWebSocketClose;
            ws.onerror = this.handleWebSocketError;
        }
        
        /**
         * Handle WebSocket message
         * @param {MessageEvent} event - Message event
         */
        handleWebSocketMessage(event) {
            try {
                const data = JSON.parse(event.data);
                
                // Handle pong response to ping
                if (data.type === 'pong') {
                    this.clearHeartbeatTimeout();
                    return;
                }
                
                // Delegate to chatbot
                if (this.chatbot && this.chatbot.handleStreamChunk) {
                    this.chatbot.handleStreamChunk(data);
                }
                
                this.emit('message', data);
            } catch (error) {
                console.error('ConnectionManager: Error parsing WebSocket message:', error);
            }
        }
        
        /**
         * Handle WebSocket close
         * @param {CloseEvent} event - Close event
         */
        handleWebSocketClose(event) {
            console.log('ConnectionManager: WebSocket closed:', event.code, event.reason);
            this.stopHeartbeat();
            this.connection = null;
            
            // Don't reconnect if this was a clean user-initiated disconnect
            if (!this.userDisconnected && event.code !== 1000) {
                this.handleDisconnection();
            }
        }
        
        /**
         * Handle WebSocket error
         * @param {Event} error - Error event
         */
        handleWebSocketError(error) {
            console.error('ConnectionManager: WebSocket error:', error);
        }
        
        /**
         * SSE connection with error handling
         * @param {object} requestData - Request data
         * @returns {Promise<boolean>} Success status
         */
        connectSSE(requestData) {
            return new Promise((resolve, reject) => {
                try {
                    // Close existing EventSource
                    if (this.connection && this.connection.close) {
                        this.connection.close();
                    }
                    
                    // Skip SSE if files are present (EventSource only supports GET)
                    if (requestData.file_data && requestData.file_data.length) {
                        reject(new Error('SSE does not support file uploads'));
                        return;
                    }
                    
                    // Build URL with parameters
                    const params = new URLSearchParams();
                    params.set('message', requestData.message || '');
                    params.set('conversation_id', requestData.conversation_id || '');
                    params.set('api_type', requestData.api_type || this.options.apiType || 'chat');
                    
                    // Add other parameters
                    const skipKeys = new Set(['message', 'conversation_id', 'api_type', 'file_data', 'stream']);
                    Object.entries(requestData).forEach(([key, value]) => {
                        if (skipKeys.has(key) || value === undefined || value === null) {
                            return;
                        }
                        
                        if (typeof value === 'object') {
                            try {
                                params.set(key, JSON.stringify(value));
                            } catch (e) {
                                console.warn(`ConnectionManager: Failed to stringify ${key}:`, e);
                            }
                        } else {
                            params.set(key, String(value));
                        }
                    });
                    
                    const url = `${this.options.apiEndpoint}?${params.toString()}`;
                    const eventSource = new EventSource(url);
                    
                    this.connection = eventSource;
                    this.sseClosedCleanly = false;
                    
                    // Connection timeout
                    this.connectionTimeout = setTimeout(() => {
                        console.warn('ConnectionManager: SSE connection timeout');
                        eventSource.close();
                        reject(new Error('SSE connection timeout'));
                    }, 5000);
                    
                    eventSource.onopen = () => {
                        clearTimeout(this.connectionTimeout);
                        this.connectionTimeout = null;
                        resolve(true);
                    };
                    
                    eventSource.onmessage = this.handleSSEMessage;
                    eventSource.onerror = this.handleSSEError;
                    
                } catch (error) {
                    clearTimeout(this.connectionTimeout);
                    this.connectionTimeout = null;
                    reject(error);
                }
            });
        }
        
        /**
         * Handle SSE message
         * @param {MessageEvent} event - Message event
         */
        handleSSEMessage(event) {
            try {
                const data = JSON.parse(event.data);
                
                // Delegate to chatbot
                if (this.chatbot && this.chatbot.handleStreamChunk) {
                    this.chatbot.handleStreamChunk(data);
                }
                
                this.emit('message', data);
            } catch (error) {
                // Ignore non-JSON keep-alive messages
                if (event.data && event.data.trim()) {
                    console.warn('ConnectionManager: Failed to parse SSE message:', event.data);
                }
            }
        }
        
        /**
         * Handle SSE error
         * @param {Event} error - Error event
         */
        handleSSEError(error) {
            const eventSource = this.connection;
            const closedCleanly = this.sseClosedCleanly;
            
            if (eventSource) {
                try {
                    eventSource.close();
                } catch (e) {
                    console.error('ConnectionManager: Error closing EventSource:', e);
                }
            }
            
            this.connection = null;
            
            // Don't trigger reconnection if this was a clean close
            if (!closedCleanly && !this.userDisconnected) {
                console.error('ConnectionManager: SSE error:', error);
                this.handleDisconnection();
            }
        }
        
        /**
         * Handle disconnection and schedule reconnect
         */
        handleDisconnection() {
            if (this.state === 'disconnected' || this.userDisconnected) {
                return;
            }
            
            this.stopHeartbeat();
            this.setState('disconnected');
            
            this.scheduleReconnect();
        }
        
        /**
         * Schedule reconnection with exponential backoff and jitter
         */
        scheduleReconnect() {
            // Clear any existing reconnect timer
            if (this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
            
            if (this.reconnectAttempts >= this.maxReconnectAttempts) {
                console.error('ConnectionManager: Max reconnect attempts reached');
                this.emit('max_reconnect_attempts');
                this.setState('disconnected');
                return;
            }
            
            // Exponential backoff with jitter to prevent thundering herd
            const baseDelay = Math.min(
                this.reconnectDelay * Math.pow(2, this.reconnectAttempts),
                this.maxReconnectDelay
            );
            
            // Add random jitter (±30%)
            const jitter = baseDelay * 0.3 * (Math.random() * 2 - 1);
            const actualDelay = Math.max(baseDelay + jitter, 1000);
            
            this.reconnectAttempts++;
            
            console.log(`ConnectionManager: Reconnecting in ${Math.round(actualDelay / 1000)}s (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
            this.emit('reconnecting', { attempt: this.reconnectAttempts, delay: actualDelay });
            
            this.reconnectTimer = setTimeout(() => {
                this.setState('reconnecting');
                this.connect(this.currentRequestData);
            }, actualDelay);
        }
        
        /**
         * Send message with queuing for offline mode
         * @param {object} messageData - Message data to send
         * @returns {Promise<object>} Send result
         */
        async sendMessage(messageData) {
            // Queue message if not connected
            if (this.state !== 'connected') {
                this.messageQueue.push(messageData);
                console.log('ConnectionManager: Message queued (offline)', messageData);
                this.emit('message_queued', { message: messageData, queueLength: this.messageQueue.length });
                return { queued: true, queueLength: this.messageQueue.length };
            }
            
            try {
                if (this.transport === 'websocket') {
                    return this.sendViaWebSocket(messageData);
                } else if (this.transport === 'sse') {
                    // SSE is request-per-message, so reconnect with new message
                    return this.connect(messageData);
                } else {
                    // AJAX fallback
                    return { success: true, transport: 'ajax' };
                }
            } catch (error) {
                console.error('ConnectionManager: Failed to send message:', error);
                // Queue and trigger reconnect
                this.messageQueue.push(messageData);
                this.handleDisconnection();
                throw error;
            }
        }
        
        /**
         * Send message via WebSocket
         * @param {object} messageData - Message data
         * @returns {Promise<object>} Send result
         */
        sendViaWebSocket(messageData) {
            return new Promise((resolve, reject) => {
                if (!this.connection || this.connection.readyState !== WebSocket.OPEN) {
                    reject(new Error('WebSocket not connected'));
                    return;
                }
                
                try {
                    this.connection.send(JSON.stringify(messageData));
                    resolve({ success: true, transport: 'websocket' });
                } catch (error) {
                    reject(error);
                }
            });
        }
        
        /**
         * Flush queued messages after reconnection
         */
        flushMessageQueue() {
            if (this.messageQueue.length === 0) {
                return;
            }
            
            console.log(`ConnectionManager: Flushing ${this.messageQueue.length} queued messages`);
            this.emit('flushing_queue', { queueLength: this.messageQueue.length });
            
            const queue = [...this.messageQueue];
            this.messageQueue = [];
            
            // Process queue sequentially
            queue.forEach(async (messageData, index) => {
                try {
                    await this.sendMessage(messageData);
                    console.log(`ConnectionManager: Sent queued message ${index + 1}/${queue.length}`);
                } catch (error) {
                    console.error(`ConnectionManager: Failed to send queued message ${index + 1}:`, error);
                    // Re-queue on failure
                    this.messageQueue.push(messageData);
                }
            });
        }
        
        /**
         * Start heartbeat/keepalive for WebSocket
         */
        startHeartbeat() {
            this.stopHeartbeat();
            
            this.heartbeatTimer = setInterval(() => {
                if (this.transport === 'websocket' && this.connection && this.connection.readyState === WebSocket.OPEN) {
                    try {
                        this.connection.send(JSON.stringify({ type: 'ping' }));
                        
                        // Set timeout for pong response
                        this.heartbeatTimeout = setTimeout(() => {
                            console.warn('ConnectionManager: Heartbeat timeout - no pong received');
                            this.handleDisconnection();
                        }, 5000);
                        
                    } catch (error) {
                        console.error('ConnectionManager: Heartbeat failed:', error);
                        this.handleDisconnection();
                    }
                }
            }, this.heartbeatInterval);
        }
        
        /**
         * Stop heartbeat
         */
        stopHeartbeat() {
            if (this.heartbeatTimer) {
                clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
            }
            this.clearHeartbeatTimeout();
        }
        
        /**
         * Clear heartbeat timeout
         */
        clearHeartbeatTimeout() {
            if (this.heartbeatTimeout) {
                clearTimeout(this.heartbeatTimeout);
                this.heartbeatTimeout = null;
            }
        }
        
        /**
         * Set connection state and notify listeners
         * @param {string} newState - New state
         */
        setState(newState) {
            const oldState = this.state;
            if (oldState === newState) {
                return;
            }
            
            this.state = newState;
            this.emit('state_change', { oldState, newState });
            console.log(`ConnectionManager: State changed: ${oldState} → ${newState}`);
        }
        
        /**
         * Graceful disconnect
         */
        disconnect() {
            console.log('ConnectionManager: User-initiated disconnect');
            this.userDisconnected = true;
            this.stopHeartbeat();
            
            // Clear reconnect timer
            if (this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
            
            // Close connection
            if (this.connection) {
                try {
                    if (this.transport === 'websocket' && this.connection.readyState === WebSocket.OPEN) {
                        this.connection.close(1000, 'User disconnect');
                    } else if (this.transport === 'sse') {
                        this.sseClosedCleanly = true;
                        this.connection.close();
                    }
                } catch (error) {
                    console.error('ConnectionManager: Error during disconnect:', error);
                }
                this.connection = null;
            }
            
            this.setState('disconnected');
        }
        
        /**
         * Get current connection status
         * @returns {object} Status object
         */
        getStatus() {
            return {
                state: this.state,
                transport: this.transport,
                reconnectAttempts: this.reconnectAttempts,
                queuedMessages: this.messageQueue.length,
                connected: this.state === 'connected'
            };
        }
        
        // Event emitter methods
        
        /**
         * Register event listener
         * @param {string} event - Event name
         * @param {function} callback - Callback function
         */
        on(event, callback) {
            if (!this.listeners[event]) {
                this.listeners[event] = [];
            }
            this.listeners[event].push(callback);
        }
        
        /**
         * Register one-time event listener
         * @param {string} event - Event name
         * @param {function} callback - Callback function
         */
        once(event, callback) {
            const wrapper = (...args) => {
                this.off(event, wrapper);
                callback(...args);
            };
            this.on(event, wrapper);
        }
        
        /**
         * Unregister event listener
         * @param {string} event - Event name
         * @param {function} callback - Callback function
         */
        off(event, callback) {
            if (!this.listeners[event]) return;
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
        
        /**
         * Emit event to listeners
         * @param {string} event - Event name
         * @param {*} data - Event data
         */
        emit(event, data) {
            if (!this.listeners[event]) return;
            this.listeners[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`ConnectionManager: Error in ${event} listener:`, error);
                }
            });
        }
    }

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
        
        // Whitelabel parameters (set by whitelabel page, not modifiable by user)
        agentPublicId: null,
        wlToken: null,

        // Responses-specific settings
        responsesConfig: {
            promptId: '',
            promptVersion: '',
            tools: null,
            defaultTools: null,
            defaultVectorStoreIds: null,
            defaultMaxNumResults: null,
        },

        // Theme customization
        theme: {
            primaryColor: '#5f6360',
            backgroundColor: '#f2f4f2',
            surfaceColor: '#ffffff',
            textColor: '#2a2d2b',
            mutedColor: 'rgba(42, 45, 43, 0.64)',
            fontFamily: '"Arial", "Helvetica", "helvetica-w01-bold", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            fontSize: '16px',
            borderRadius: '16px',
            shadow: '0 16px 32px -12px rgba(0, 0, 0, 0.25)'
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
            this.sseClosedCleanly = false;
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
            
            // Initialize ConnectionManager after widget creation
            this.initializeConnectionManager();
        }
        
        /**
         * Initialize ConnectionManager with event listeners
         */
        initializeConnectionManager() {
            this.connectionManager = new ConnectionManager(this.options, this);
            
            // Setup connection state change listeners
            this.connectionManager.on('state_change', ({ oldState, newState }) => {
                this.handleConnectionStateChange(oldState, newState);
            });
            
            this.connectionManager.on('reconnecting', ({ attempt, delay }) => {
                const seconds = Math.round(delay / 1000);
                this.showReconnectingNotification(attempt, seconds);
            });
            
            this.connectionManager.on('max_reconnect_attempts', () => {
                this.showError('Unable to connect. Please refresh the page.');
            });
            
            this.connectionManager.on('message_queued', ({ queueLength }) => {
                this.showQueuedNotification(queueLength);
            });
            
            this.connectionManager.on('flushing_queue', ({ queueLength }) => {
                console.log(`Sending ${queueLength} queued messages...`);
            });
        }
        
        /**
         * Handle connection state changes
         * @param {string} oldState - Previous state
         * @param {string} newState - New state
         */
        handleConnectionStateChange(oldState, newState) {
            console.log(`Connection state: ${oldState} → ${newState}`);
            
            // Update connection status in UI
            const connected = newState === 'connected';
            this.setConnectionStatus(connected);
            
            // Update active mode display
            if (newState === 'connected' && this.connectionManager.transport) {
                this.setActiveMode(this.connectionManager.transport);
            } else if (newState === 'disconnected' || newState === 'reconnecting') {
                this.setActiveMode('offline');
            } else if (newState === 'connecting') {
                this.setActiveMode('connecting');
            }
            
            // Show notifications for state changes
            if (newState === 'connected' && oldState === 'reconnecting') {
                this.showSuccessNotification('Reconnected successfully!');
            } else if (newState === 'disconnected' && oldState === 'connected') {
                this.showWarningNotification('Connection lost. Attempting to reconnect...');
            }
            
            // Call user callbacks
            if (connected && this.options.onConnect) {
                try {
                    this.options.onConnect();
                } catch (error) {
                    console.error('Error in onConnect callback:', error);
                }
            } else if (!connected && oldState === 'connected' && this.options.onDisconnect) {
                try {
                    this.options.onDisconnect();
                } catch (error) {
                    console.error('Error in onDisconnect callback:', error);
                }
            }
        }
        
        /**
         * Show reconnecting notification
         * @param {number} attempt - Attempt number
         * @param {number} seconds - Seconds until retry
         */
        showReconnectingNotification(attempt, seconds) {
            const message = `Reconnecting... (attempt ${attempt}, waiting ${seconds}s)`;
            this.showWarningNotification(message);
        }
        
        /**
         * Show queued message notification
         * @param {number} queueLength - Number of queued messages
         */
        showQueuedNotification(queueLength) {
            this.showWarningNotification(`Message queued. ${queueLength} message(s) waiting to send.`);
        }
        
        /**
         * Show success notification (helper method)
         * @param {string} message - Message to display
         */
        showSuccessNotification(message) {
            console.log(`✓ ${message}`);
            // Could also show a temporary UI notification
        }
        
        /**
         * Show warning notification (helper method)
         * @param {string} message - Message to display
         */
        showWarningNotification(message) {
            console.warn(`⚠ ${message}`);
            // Could also show a temporary UI notification
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
                <div class="file-preview-item" data-file-id="${SecurityUtils.sanitizeAttribute(fileData.id)}">
                    <div class="file-info">
                        <span class="file-name">${SecurityUtils.sanitizeFilename(fileData.name)}</span>
                        <span class="file-size">${this.formatFileSize(fileData.size)}</span>
                    </div>
                    <button class="file-remove" data-file-id="${SecurityUtils.sanitizeAttribute(fileData.id)}" title="Remove file">✕</button>
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
                
                // Add whitelabel parameters if provided (always included, never removable)
                if (this.options.agentPublicId) {
                    requestData.agent_public_id = this.options.agentPublicId;
                }
                
                if (this.options.wlToken) {
                    requestData.wl_token = this.options.wlToken;
                }

                if (this.options.apiType === 'responses') {
                    const responsesConfig = this.options.responsesConfig || {};
                    Object.entries(responsesConfig).forEach(([key, value]) => {
                        if (typeof value === 'undefined' || value === null) {
                            return;
                        }

                        if (typeof value === 'string' && value.trim() === '') {
                            return;
                        }

                        if (this.isEmptyObject(value)) {
                            return;
                        }

                        const normalizedKey = this.normalizeConfigKey(key);
                        if (!normalizedKey) {
                            return;
                        }

                        requestData[normalizedKey] = this.cloneConfigValue(value);
                    });
                }

                // Add file data if present
                if (files.length > 0) {
                    requestData.file_data = await Promise.all(
                        files.map(fileData => this.fileToBase64(fileData.file))
                    );
                }

                // Use ConnectionManager for robust connection handling
                if (this.connectionManager) {
                    // ConnectionManager handles transport selection and failover
                    const result = await this.connectionManager.connect(requestData);
                    
                    if (!result && this.connectionManager.state !== 'connected') {
                        // Connection failed, but message is queued
                        console.log('Message queued - will be sent when connection is restored');
                    }
                } else {
                    // Fallback to legacy connection logic (for backward compatibility)
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
                }

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

        normalizeConfigKey(key) {
            if (typeof key !== 'string') {
                return '';
            }

            const trimmed = key.trim();
            if (trimmed.length === 0) {
                return '';
            }

            return trimmed
                .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
                .replace(/[\s-]+/g, '_')
                .replace(/__+/g, '_')
                .toLowerCase();
        }

        cloneConfigValue(value) {
            if (value === null || typeof value !== 'object') {
                return value;
            }

            if (typeof structuredClone === 'function') {
                try {
                    return structuredClone(value);
                } catch (_) {
                    // Fallback to JSON clone below
                }
            }

            try {
                return JSON.parse(JSON.stringify(value));
            } catch (_) {
                if (Array.isArray(value)) {
                    return value.slice();
                }

                return Object.assign({}, value);
            }
        }

        isPlainObject(value) {
            return Object.prototype.toString.call(value) === '[object Object]';
        }

        isEmptyObject(value) {
            if (!this.isPlainObject(value)) {
                return false;
            }

            return Object.keys(value).length === 0;
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
                        this.sseClosedCleanly = true;
                        try { this.eventSource.close(); } catch (_) {}
                        this.eventSource = null;
                    }

                    this.sseClosedCleanly = false;

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

                    const skipKeys = new Set(['message', 'conversation_id', 'api_type', 'file_data', 'stream']);
                    Object.entries(requestData).forEach(([key, value]) => {
                        if (skipKeys.has(key)) {
                            return;
                        }

                        if (typeof value === 'undefined' || value === null) {
                            return;
                        }

                        let paramValue;
                        if (typeof value === 'object') {
                            try {
                                paramValue = JSON.stringify(value);
                            } catch (_) {
                                return;
                            }
                        } else {
                            paramValue = `${value}`;
                        }

                        params.set(key, paramValue);
                    });

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
                        const isCurrent = this.eventSource === eventSource;
                        const closedCleanly = this.sseClosedCleanly && isCurrent;

                        try { eventSource.close(); } catch (_) {}

                        if (!isCurrent) {
                            return;
                        }

                        this.eventSource = null;

                        if (closedCleanly) {
                            return;
                        }

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
                    this.sseClosedCleanly = true;
                    try { this.eventSource.close(); } catch (_) {}
                    this.eventSource = null;
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
                        <span class="file-name">${SecurityUtils.sanitizeFilename(file.name)}</span>
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
                return SecurityUtils.escapeHTML(content || '');
            }

            let text = (content || '').replace(/\r\n/g, '\n');
            const codeBlocks = [];

            text = text.replace(/```(\w+)?\n?([\s\S]*?)```/g, (match, lang, code) => {
                const index = codeBlocks.length;
                const languageAttr = lang ? ` data-language="${SecurityUtils.escapeHTML(lang)}"` : '';
                codeBlocks.push(`<pre><code${languageAttr}>${SecurityUtils.escapeHTML(code.trimEnd())}</code></pre>`);
                return `{{CODE_BLOCK_${index}}}`;
            });

            text = SecurityUtils.escapeHTML(text);

            // Headings
            text = text.replace(/^###### (.+)$/gm, '<h6>$1</h6>')
                .replace(/^##### (.+)$/gm, '<h5>$1</h5>')
                .replace(/^#### (.+)$/gm, '<h4>$1</h4>')
                .replace(/^### (.+)$/gm, '<h3>$1</h3>')
                .replace(/^## (.+)$/gm, '<h2>$1</h2>')
                .replace(/^# (.+)$/gm, '<h1>$1</h1>');

            // Blockquotes
            text = text.replace(/^>\s?(.+)$/gm, '<blockquote>$1</blockquote>');

            // Links - Only allow https:// and http:// protocols
            text = text.replace(/\[([^\]]+)]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

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

            // Final sanitization pass with DOMPurify if available
            // This provides an additional layer of security
            return SecurityUtils.sanitizeHTML(text);
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
         * Escape HTML content (delegates to SecurityUtils)
         */
        escapeHtml(text) {
            return SecurityUtils.escapeHTML(text);
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
