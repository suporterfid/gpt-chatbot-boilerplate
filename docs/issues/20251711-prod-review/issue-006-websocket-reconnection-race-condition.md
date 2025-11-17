# Issue 006: WebSocket Reconnection Race Conditions

**Category:** Architecture & Robustness  
**Severity:** Medium  
**Priority:** Medium  
**File:** `chatbot-enhanced.js`

## Problem Description

The WebSocket fallback logic in `chatbot-enhanced.js` lacks proper reconnection handling and has potential race conditions when switching between transport modes (WebSocket → SSE → AJAX).

## Issues Identified

### 1. No Reconnection Strategy

From the configuration (line 28):
```javascript
streamingMode: 'auto', // 'sse', 'websocket', 'ajax', 'auto'
```

The 'auto' mode should handle failures gracefully, but there's likely no exponential backoff or connection state management.

### 2. Potential Race Conditions

When a WebSocket connection fails and falls back to SSE:
- Messages sent during transition may be lost
- No message queue for pending messages
- State inconsistency between transport types

### 3. Missing Connection State Management

The code needs to track:
- Connection status (connecting, connected, disconnecting, disconnected)
- Message delivery confirmation
- Pending messages during reconnection
- Failed message retry logic

### 4. No Heartbeat/Keepalive

Long-lived WebSocket connections without heartbeat:
- May be closed by proxies/firewalls
- Client doesn't know if connection is stale
- No automatic recovery from silent failures

## Attack/Failure Scenarios

### Scenario 1: Message Loss During Fallback

```
1. User sends message via WebSocket
2. WebSocket connection drops mid-send
3. System falls back to SSE
4. Original message is lost
5. User thinks message was sent but assistant never responds
```

### Scenario 2: Race Condition on Reconnect

```
1. Connection drops
2. Auto-reconnect initiates
3. User sends new message
4. Reconnection completes
5. Two connection handlers active simultaneously
6. Duplicate messages or state corruption
```

### Scenario 3: Thundering Herd on Server Restart

```
1. WebSocket server restarts
2. All connected clients disconnect simultaneously
3. All clients retry immediately
4. Server overwhelmed by reconnection storm
```

## Impact

- **Medium**: Message delivery failures
- **Medium**: Poor user experience during network issues
- **Low**: Potential for duplicate messages
- **Low**: Server resource exhaustion

## Recommendations

### 1. Implement Connection State Machine

```javascript
class ConnectionManager {
    constructor(options) {
        this.options = options;
        this.state = 'disconnected'; // disconnected, connecting, connected, reconnecting
        this.transport = null; // 'websocket', 'sse', 'ajax'
        this.connection = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 1000; // Start with 1s
        this.maxReconnectDelay = 30000; // Max 30s
        this.messageQueue = [];
        this.heartbeatTimer = null;
        this.heartbeatInterval = 30000; // 30s
        this.listeners = {};
    }
    
    /**
     * Connect with automatic transport fallback
     */
    async connect() {
        if (this.state === 'connecting' || this.state === 'connected') {
            return;
        }
        
        this.setState('connecting');
        
        // Try transports in order: WebSocket → SSE → AJAX
        const transports = this.options.streamingMode === 'auto'
            ? ['websocket', 'sse', 'ajax']
            : [this.options.streamingMode];
        
        for (const transport of transports) {
            try {
                await this.connectWithTransport(transport);
                this.setState('connected');
                this.reconnectAttempts = 0;
                this.startHeartbeat();
                this.flushMessageQueue();
                return;
            } catch (error) {
                console.warn(`Failed to connect with ${transport}:`, error.message);
                continue;
            }
        }
        
        // All transports failed
        this.setState('disconnected');
        this.scheduleReconnect();
    }
    
    /**
     * Connect using specific transport
     */
    async connectWithTransport(transport) {
        switch (transport) {
            case 'websocket':
                return this.connectWebSocket();
            case 'sse':
                return this.connectSSE();
            case 'ajax':
                // AJAX doesn't maintain connection
                this.transport = 'ajax';
                return Promise.resolve();
            default:
                throw new Error(`Unknown transport: ${transport}`);
        }
    }
    
    /**
     * WebSocket connection with error handling
     */
    connectWebSocket() {
        return new Promise((resolve, reject) => {
            if (!this.options.websocketEndpoint) {
                reject(new Error('WebSocket endpoint not configured'));
                return;
            }
            
            const ws = new WebSocket(this.options.websocketEndpoint);
            const timeout = setTimeout(() => {
                ws.close();
                reject(new Error('WebSocket connection timeout'));
            }, 5000);
            
            ws.onopen = () => {
                clearTimeout(timeout);
                this.connection = ws;
                this.transport = 'websocket';
                this.setupWebSocketHandlers(ws);
                resolve();
            };
            
            ws.onerror = (error) => {
                clearTimeout(timeout);
                reject(error);
            };
        });
    }
    
    /**
     * SSE connection with error handling
     */
    connectSSE() {
        return new Promise((resolve, reject) => {
            // SSE is connection-per-request, so just verify endpoint is accessible
            fetch(this.options.apiEndpoint, {
                method: 'HEAD'
            }).then(response => {
                if (response.ok) {
                    this.transport = 'sse';
                    resolve();
                } else {
                    reject(new Error('SSE endpoint not accessible'));
                }
            }).catch(reject);
        });
    }
    
    /**
     * Setup WebSocket event handlers
     */
    setupWebSocketHandlers(ws) {
        ws.onmessage = (event) => {
            this.handleMessage(JSON.parse(event.data));
        };
        
        ws.onclose = (event) => {
            console.log('WebSocket closed:', event.code, event.reason);
            this.handleDisconnection();
        };
        
        ws.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }
    
    /**
     * Handle disconnection and reconnect
     */
    handleDisconnection() {
        if (this.state === 'disconnected') {
            return; // Already handling
        }
        
        this.stopHeartbeat();
        this.setState('disconnected');
        
        // Don't reconnect if user explicitly disconnected
        if (!this.userDisconnected) {
            this.scheduleReconnect();
        }
    }
    
    /**
     * Schedule reconnection with exponential backoff
     */
    scheduleReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnect attempts reached');
            this.emit('max_reconnect_attempts');
            return;
        }
        
        // Exponential backoff with jitter
        const delay = Math.min(
            this.reconnectDelay * Math.pow(2, this.reconnectAttempts),
            this.maxReconnectDelay
        );
        
        // Add random jitter to prevent thundering herd
        const jitter = delay * 0.3 * Math.random();
        const actualDelay = delay + jitter;
        
        this.reconnectAttempts++;
        
        console.log(`Reconnecting in ${Math.round(actualDelay / 1000)}s (attempt ${this.reconnectAttempts})`);
        
        setTimeout(() => {
            this.setState('reconnecting');
            this.connect();
        }, actualDelay);
    }
    
    /**
     * Send message with queuing for offline mode
     */
    async sendMessage(message) {
        if (this.state !== 'connected') {
            // Queue message for later delivery
            this.messageQueue.push(message);
            console.log('Message queued (offline):', message);
            return { queued: true };
        }
        
        try {
            if (this.transport === 'websocket') {
                return this.sendViaWebSocket(message);
            } else if (this.transport === 'sse') {
                return this.sendViaSSE(message);
            } else {
                return this.sendViaAJAX(message);
            }
        } catch (error) {
            // On failure, queue and trigger reconnect
            this.messageQueue.push(message);
            this.handleDisconnection();
            throw error;
        }
    }
    
    /**
     * Send message via WebSocket
     */
    sendViaWebSocket(message) {
        return new Promise((resolve, reject) => {
            if (!this.connection || this.connection.readyState !== WebSocket.OPEN) {
                reject(new Error('WebSocket not connected'));
                return;
            }
            
            const messageId = this.generateMessageId();
            const payload = { ...message, messageId };
            
            // Setup delivery confirmation handler
            const confirmTimeout = setTimeout(() => {
                reject(new Error('Message delivery timeout'));
            }, 10000);
            
            this.once(`confirm:${messageId}`, () => {
                clearTimeout(confirmTimeout);
                resolve({ messageId });
            });
            
            this.connection.send(JSON.stringify(payload));
        });
    }
    
    /**
     * Flush queued messages after reconnection
     */
    flushMessageQueue() {
        if (this.messageQueue.length === 0) {
            return;
        }
        
        console.log(`Flushing ${this.messageQueue.length} queued messages`);
        
        const queue = [...this.messageQueue];
        this.messageQueue = [];
        
        queue.forEach(async (message) => {
            try {
                await this.sendMessage(message);
            } catch (error) {
                console.error('Failed to send queued message:', error);
                // Re-queue on failure
                this.messageQueue.push(message);
            }
        });
    }
    
    /**
     * Start heartbeat/keepalive
     */
    startHeartbeat() {
        this.stopHeartbeat();
        
        this.heartbeatTimer = setInterval(() => {
            if (this.transport === 'websocket' && this.connection) {
                try {
                    this.connection.send(JSON.stringify({ type: 'ping' }));
                } catch (error) {
                    console.error('Heartbeat failed:', error);
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
    }
    
    /**
     * Set connection state and notify listeners
     */
    setState(newState) {
        const oldState = this.state;
        this.state = newState;
        this.emit('state_change', { oldState, newState });
        console.log(`Connection state: ${oldState} → ${newState}`);
    }
    
    /**
     * Event emitter methods
     */
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    once(event, callback) {
        const wrapper = (...args) => {
            this.off(event, wrapper);
            callback(...args);
        };
        this.on(event, wrapper);
    }
    
    off(event, callback) {
        if (!this.listeners[event]) return;
        this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
    }
    
    emit(event, data) {
        if (!this.listeners[event]) return;
        this.listeners[event].forEach(callback => callback(data));
    }
    
    /**
     * Generate unique message ID
     */
    generateMessageId() {
        return `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }
    
    /**
     * Graceful disconnect
     */
    disconnect() {
        this.userDisconnected = true;
        this.stopHeartbeat();
        
        if (this.connection) {
            if (this.transport === 'websocket') {
                this.connection.close(1000, 'User disconnect');
            } else if (this.transport === 'sse') {
                this.connection.close();
            }
            this.connection = null;
        }
        
        this.setState('disconnected');
    }
}
```

### 2. Integrate with EnhancedChatBot

```javascript
class EnhancedChatBot {
    constructor(container, options = {}) {
        // ... existing initialization ...
        
        // Initialize connection manager
        this.connectionManager = new ConnectionManager(this.options);
        
        // Setup connection state listeners
        this.connectionManager.on('state_change', ({ oldState, newState }) => {
            this.handleConnectionStateChange(oldState, newState);
        });
        
        this.connectionManager.on('max_reconnect_attempts', () => {
            this.showError('Connection failed. Please refresh the page.');
        });
        
        // Connect
        this.connectionManager.connect();
    }
    
    handleConnectionStateChange(oldState, newState) {
        // Update UI based on connection state
        const statusIndicator = this.widget.querySelector('.connection-status');
        
        if (statusIndicator) {
            statusIndicator.textContent = newState;
            statusIndicator.className = `connection-status status-${newState}`;
        }
        
        // Notify user of reconnection
        if (newState === 'reconnecting') {
            this.showNotification('Reconnecting...', 'info');
        } else if (newState === 'connected' && oldState === 'reconnecting') {
            this.showNotification('Reconnected!', 'success');
        }
        
        // Call user callback
        if (this.options.onConnect && newState === 'connected') {
            this.options.onConnect();
        } else if (this.options.onDisconnect && newState === 'disconnected') {
            this.options.onDisconnect();
        }
    }
    
    async sendMessage() {
        const message = this.inputField.value.trim();
        if (!message) return;
        
        try {
            const result = await this.connectionManager.sendMessage({
                message: message,
                conversation_id: this.conversationId
            });
            
            if (result.queued) {
                this.showNotification('Message queued (offline)', 'warning');
            }
            
            // ... rest of message handling ...
        } catch (error) {
            this.showError('Failed to send message: ' + error.message);
        }
    }
}
```

## Testing Requirements

```javascript
// Test reconnection scenarios
describe('Connection Manager', () => {
    it('should reconnect with exponential backoff', async () => {
        const manager = new ConnectionManager(config);
        
        // Simulate connection failures
        manager.connectWebSocket = () => Promise.reject(new Error('Connection failed'));
        
        const delays = [];
        const originalSetTimeout = setTimeout;
        setTimeout = (fn, delay) => {
            delays.push(delay);
            return originalSetTimeout(fn, 0); // Speed up test
        };
        
        await manager.connect();
        
        // Verify exponential backoff: 1s, 2s, 4s, 8s...
        expect(delays[0]).toBeCloseTo(1000, -2);
        expect(delays[1]).toBeCloseTo(2000, -2);
        expect(delays[2]).toBeCloseTo(4000, -2);
        
        setTimeout = originalSetTimeout;
    });
    
    it('should queue messages during disconnection', async () => {
        const manager = new ConnectionManager(config);
        manager.setState('disconnected');
        
        const result = await manager.sendMessage({ text: 'test' });
        
        expect(result.queued).toBe(true);
        expect(manager.messageQueue).toHaveLength(1);
    });
    
    it('should flush queue on reconnection', async () => {
        const manager = new ConnectionManager(config);
        manager.messageQueue = [
            { text: 'msg1' },
            { text: 'msg2' }
        ];
        
        manager.sendViaAJAX = jest.fn().mockResolvedValue({});
        manager.setState('connected');
        manager.flushMessageQueue();
        
        expect(manager.sendViaAJAX).toHaveBeenCalledTimes(2);
        expect(manager.messageQueue).toHaveLength(0);
    });
});
```

## Estimated Effort

- **Effort:** 2-3 days
- **Risk:** Medium (requires careful state management)

## Related Issues

- Issue 007: SSE connection handling
- Issue 008: Error recovery patterns
- Issue 001: ChatHandler complexity
