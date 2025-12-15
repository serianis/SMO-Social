/**
 * SMO Social Real-Time Polling Client
 * Αντικαθιστά το WebSocket με WordPress-native REST API polling
 */

class SMOPollingClient {
    constructor(options = {}) {
        this.sessionId = null;
        this.channels = new Set();
        this.isPolling = false;
        this.pollInterval = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.reconnectDelay = 5000; // 5 seconds
        this.token = options.token || null;
        this.userId = options.userId || null;
        this.connectionEnabled = true;
        this.lastMessageTime = null;
        this.config = {
            pollInterval: 5000, // 5 seconds
            maxPollInterval: 30000, // 30 seconds
            retryDelay: 5000,
            maxRetries: 3
        };
        
        // Event handlers
        this.onMessageCallback = options.onMessage || this.handleMessage.bind(this);
        this.onConnectCallback = options.onConnect || null;
        this.onDisconnectCallback = options.onDisconnect || null;
        this.onErrorCallback = options.onError || null;
        
        // Feature-specific handlers
        this.commentHandlers = new Set();
        this.collaborationHandlers = new Set();
        this.activityHandlers = new Set();
        
        this.failureCount = 0;
        this.maxFailures = 5;
    }

    /**
     * Connect to real-time system
     */
    async connect(token = null) {
        if (token) {
            this.token = token;
        }

        if (!this.connectionEnabled) {
            return false;
        }

        try {
            console.log('SMO RealTime: Connecting to polling system...');
            
            // Create polling session
            const sessionResult = await this.createPollingSession();
            if (!sessionResult.success) {
                throw new Error('Failed to create polling session');
            }

            this.sessionId = sessionResult.session_id;
            this.isPolling = true;
            this.reconnectAttempts = 0;
            this.failureCount = 0;

            // Start polling
            this.startPolling();
            
            if (this.onConnectCallback) {
                this.onConnectCallback({ type: 'connected', method: 'polling' });
            }

            console.log('SMO RealTime: Connected to polling system');
            return true;

        } catch (error) {
            console.error('SMO RealTime: Connection failed:', error);
            this.handleConnectionError(error);
            return false;
        }
    }

    /**
     * Create polling session
     */
    async createPollingSession() {
        const channels = Array.from(this.channels);
        
        const response = await this.makeRequest('/wp-json/smo-social/v1/realtime/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                channels: channels,
                token: this.token
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    /**
     * Start polling for messages
     */
    startPolling() {
        if (!this.isPolling || !this.sessionId) {
            return;
        }

        // Initial poll
        this.pollForMessages();
        
        // Set up regular polling
        this.pollInterval = setInterval(() => {
            this.pollForMessages();
        }, this.config.pollInterval);

        console.log('SMO RealTime: Polling started');
    }

    /**
     * Stop polling
     */
    stopPolling() {
        this.isPolling = false;
        
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }

        // Remove session
        if (this.sessionId) {
            this.removePollingSession();
        }

        console.log('SMO RealTime: Polling stopped');
    }

    /**
     * Poll for new messages
     */
    async pollForMessages() {
        if (!this.isPolling || !this.sessionId) {
            return;
        }

        try {
            const url = `/wp-json/smo-social/v1/realtime/messages?session_id=${this.sessionId}`;
            if (this.lastMessageTime) {
                url += `&since=${encodeURIComponent(this.lastMessageTime)}`;
            }

            const response = await this.makeRequest(url);
            
            if (!response.ok) {
                if (response.status === 401) {
                    // Session expired, try to reconnect
                    await this.reconnect();
                    return;
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.success && data.messages) {
                this.failureCount = 0; // Reset failure count on success
                
                // Process new messages
                for (const message of data.messages) {
                    this.lastMessageTime = message.received_at;
                    this.onMessageCallback(message);
                }
            }

        } catch (error) {
            this.failureCount++;
            console.error('SMO RealTime: Poll failed:', error);
            
            if (this.failureCount >= this.maxFailures) {
                this.disableRealTimeFeatures();
                return;
            }
            
            // Exponential backoff for polling interval
            const delay = Math.min(this.config.pollInterval * Math.pow(2, this.failureCount), this.config.maxPollInterval);
            this.adjustPollingInterval(delay);
        }
    }

    /**
     * Adjust polling interval based on failures
     */
    adjustPollingInterval(newInterval) {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
        
        this.config.pollInterval = newInterval;
        this.pollInterval = setInterval(() => {
            this.pollForMessages();
        }, newInterval);
    }

    /**
     * Subscribe to channel
     */
    async subscribe(channel) {
        if (this.channels.has(channel)) {
            return true;
        }

        try {
            this.channels.add(channel);
            
            const response = await this.makeRequest('/wp-json/smo-social/v1/realtime/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    channel: channel,
                    token: this.token
                })
            });

            const data = await response.json();
            return data.success === true;

        } catch (error) {
            console.error('SMO RealTime: Subscribe failed:', error);
            this.channels.delete(channel);
            return false;
        }
    }

    /**
     * Unsubscribe from channel
     */
    async unsubscribe(channel) {
        if (!this.channels.has(channel)) {
            return true;
        }

        try {
            const response = await this.makeRequest('/wp-json/smo-social/v1/realtime/unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    channel: channel,
                    token: this.token
                })
            });

            const data = await response.json();
            if (data.success === true) {
                this.channels.delete(channel);
                return true;
            }

        } catch (error) {
            console.error('SMO RealTime: Unsubscribe failed:', error);
        }

        return false;
    }

    /**
     * Publish message to channel
     */
    async publish(channel, data, type = 'message') {
        try {
            const response = await this.makeRequest('/wp-json/smo-social/v1/realtime/publish', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    channel: channel,
                    data: data,
                    type: type
                })
            });

            const result = await response.json();
            return result.success === true;

        } catch (error) {
            console.error('SMO RealTime: Publish failed:', error);
            return false;
        }
    }

    /**
     * Handle incoming messages
     */
    handleMessage(message) {
        // Route message to appropriate handlers
        switch (message.type) {
            case 'new_comment':
                this.handleCommentMessage(message);
                break;
            case 'content_update':
                this.handleCollaborationMessage(message);
                break;
            case 'activity_update':
                this.handleActivityMessage(message);
                break;
            default:
                this.routeMessage(message);
        }
    }

    /**
     * Route messages to appropriate handlers
     */
    routeMessage(message) {
        const { channel, data } = message;

        if (channel.startsWith('comments_post_')) {
            this.handleCommentMessage(message);
        } else if (channel.startsWith('collaboration_session_')) {
            this.handleCollaborationMessage(message);
        } else if (channel.startsWith('activity_feed_')) {
            this.handleActivityMessage(message);
        }
    }

    /**
     * Handle comment-related messages
     */
    handleCommentMessage(message) {
        this.commentHandlers.forEach(handler => {
            try {
                handler(message.channel, message.data);
            } catch (e) {
                console.error('SMO RealTime: Comment handler error:', e);
            }
        });

        // Default comment handling
        switch (message.data.type) {
            case 'new_comment':
                this.onNewComment(message.data);
                break;
            case 'comment_reply':
                this.onCommentReply(message.data);
                break;
        }
    }

    /**
     * Handle collaboration-related messages
     */
    handleCollaborationMessage(message) {
        this.collaborationHandlers.forEach(handler => {
            try {
                handler(message.channel, message.data);
            } catch (e) {
                console.error('SMO RealTime: Collaboration handler error:', e);
            }
        });

        // Default collaboration handling
        switch (message.data.type) {
            case 'content_update':
                this.onContentUpdate(message.data);
                break;
            case 'session_update':
                this.onSessionUpdate(message.data);
                break;
            case 'conflict_detected':
                this.onConflictDetected(message.data);
                break;
            case 'approval_update':
                this.onApprovalUpdate(message.data);
                break;
        }
    }

    /**
     * Handle activity feed messages
     */
    handleActivityMessage(message) {
        this.activityHandlers.forEach(handler => {
            try {
                handler(message.channel, message.data);
            } catch (e) {
                console.error('SMO RealTime: Activity handler error:', e);
            }
        });

        this.onActivityUpdate(message.data);
    }

    /**
     * Handle connection errors
     */
    handleConnectionError(error) {
        if (this.onErrorCallback) {
            this.onErrorCallback(error);
        }

        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            setTimeout(() => {
                this.reconnect();
            }, this.reconnectDelay * this.reconnectAttempts);
        } else {
            this.disableRealTimeFeatures();
        }
    }

    /**
     * Reconnect to the system
     */
    async reconnect() {
        console.log('SMO RealTime: Attempting to reconnect...');
        
        this.stopPolling();
        
        // Wait before reconnecting
        await new Promise(resolve => setTimeout(resolve, this.reconnectDelay));
        
        return this.connect(this.token);
    }

    /**
     * Remove polling session
     */
    async removePollingSession() {
        if (!this.sessionId) {
            return;
        }

        try {
            await this.makeRequest(`/wp-json/smo-social/v1/realtime/unsubscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id: this.sessionId
                })
            });
        } catch (error) {
            console.error('SMO RealTime: Failed to remove session:', error);
        }

        this.sessionId = null;
    }

    /**
     * Make HTTP request with error handling
     */
    async makeRequest(url, options = {}) {
        const defaultOptions = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // Add nonce if available
        if (typeof window.smo_ajax_object !== 'undefined' && window.smo_ajax_object.nonce) {
            defaultOptions.headers['X-WP-Nonce'] = window.smo_ajax_object.nonce;
        }

        const finalOptions = { ...defaultOptions, ...options };
        
        return fetch(url, finalOptions);
    }

    /**
     * Disable real-time features
     */
    disableRealTimeFeatures() {
        this.connectionEnabled = false;
        this.stopPolling();
        
        document.dispatchEvent(new CustomEvent('smoRealTimeDisabled', {
            detail: { reason: 'connection_failure' }
        }));
    }

    /**
     * Enable real-time features again
     */
    enableRealTimeFeatures() {
        this.connectionEnabled = true;
        this.failureCount = 0;
        
        document.dispatchEvent(new CustomEvent('smoRealTimeEnabled'));
    }

    /**
     * Get connection status
     */
    getStatus() {
        return {
            connected: this.isPolling && this.connectionEnabled,
            method: 'polling',
            sessionId: this.sessionId,
            channels: Array.from(this.channels),
            failureCount: this.failureCount,
            config: this.config
        };
    }

    // Event handler registration methods
    onComment(handler) {
        this.commentHandlers.add(handler);
    }

    offComment(handler) {
        this.commentHandlers.delete(handler);
    }

    onCollaboration(handler) {
        this.collaborationHandlers.add(handler);
    }

    offCollaboration(handler) {
        this.collaborationHandlers.delete(handler);
    }

    onActivity(handler) {
        this.activityHandlers.add(handler);
    }

    offActivity(handler) {
        this.activityHandlers.delete(handler);
    }

    // Default event handlers
    onNewComment(data) {
        document.dispatchEvent(new CustomEvent('smoNewComment', { detail: data }));
    }

    onCommentReply(data) {
        document.dispatchEvent(new CustomEvent('smoCommentReply', { detail: data }));
    }

    onContentUpdate(data) {
        document.dispatchEvent(new CustomEvent('smoContentUpdate', { detail: data }));
    }

    onSessionUpdate(data) {
        document.dispatchEvent(new CustomEvent('smoSessionUpdate', { detail: data }));
    }

    onConflictDetected(data) {
        document.dispatchEvent(new CustomEvent('smoConflictDetected', { detail: data }));
    }

    onApprovalUpdate(data) {
        document.dispatchEvent(new CustomEvent('smoApprovalUpdate', { detail: data }));
    }

    onActivityUpdate(data) {
        document.dispatchEvent(new CustomEvent('smoActivityUpdate', { detail: data }));
    }
}

// Initialize global instance
window.SMOPolling = new SMOPollingClient();

// Auto-connect if token is available
document.addEventListener('DOMContentLoaded', async function() {
    if (typeof smo_ajax_object !== 'undefined' && smo_ajax_object.websocket_token) {
        await window.SMOPolling.connect(smo_ajax_object.websocket_token);
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SMOPollingClient;
}