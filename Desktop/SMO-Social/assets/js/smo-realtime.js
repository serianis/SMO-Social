/**
 * SMO Social Real-Time WebSocket Client
 * Handles real-time features for comments, collaboration, and activity feeds
 */

class SMORealTimeClient {
    constructor(options = {}) {
        this.ws = null;
        this.channels = new Set();
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 2; // Further reduced to prevent excessive attempts
        this.reconnectInterval = 10000; // Increased interval to 10 seconds for better UX
        this.heartbeatInterval = null;
        this.token = options.token || null;
        this.userId = options.userId || null;
        this.connectionEnabled = true; // Allow disabling real-time features
        this.onMessageCallback = options.onMessage || this.handleMessage.bind(this);
        this.onConnectCallback = options.onConnect || null;
        this.onDisconnectCallback = options.onDisconnect || null;
        this.onErrorCallback = options.onError || null;

        // Feature-specific handlers
        this.commentHandlers = new Set();
        this.collaborationHandlers = new Set();
        this.activityHandlers = new Set();
        
        // Enhanced failure tracking with exponential backoff
        this.failureCount = 0;
        this.maxFailures = 2; // Reduced to disable faster after failures
        this.configCache = null;
        this.configCacheTimeout = 60000; // Cache config for 1 minute
        this.lastConfigRequest = 0;
        
        // Circuit breaker pattern
        this.circuitBreakerOpen = false;
        this.circuitBreakerTimeout = 300000; // 5 minutes
        this.circuitBreakerLastFailure = 0;
    }

    /**
     * Connect to WebSocket server
     */
    connect(token = null) {
        if (token) {
            this.token = token;
        }

        if (!this.token) {
            return false;
        }

        // Check if real-time features are disabled
        if (!this.connectionEnabled) {
            return false;
        }
        
        // Check circuit breaker
        if (this.circuitBreakerOpen) {
            const now = Date.now();
            if (now - this.circuitBreakerLastFailure < this.circuitBreakerTimeout) {
                return false;
            } else {
                // Reset circuit breaker after timeout
                this.circuitBreakerOpen = false;
            }
        }

        // Get WebSocket URL from server config (with caching)
        this.getWebSocketConfig().then(config => {
            if (!config.url) {
                this.disableRealTimeFeatures();
                return;
            }
            
            // Direct connection attempt (skip test for simplicity)
            this.ws = new WebSocket(config.url);

            this.ws.onopen = (event) => {
                this.reconnectAttempts = 0;
                this.failureCount = 0; // Reset failure count on successful connection
                this.authenticate();
                this.startHeartbeat();
                if (this.onConnectCallback) {
                    this.onConnectCallback(event);
                }
            };

            this.ws.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);
                    this.onMessageCallback(message);
                } catch (e) {
                    // Failed to parse message
                }
            };

            this.ws.onclose = (event) => {
                this.stopHeartbeat();
                this.handleReconnect();
                if (this.onDisconnectCallback) {
                    this.onDisconnectCallback(event);
                }
            };

            this.ws.onerror = (error) => {
                this.failureCount++;
                
                // Update circuit breaker
                this.circuitBreakerOpen = true;
                this.circuitBreakerLastFailure = Date.now();
                
                // Disable real-time features after too many failures
                if (this.failureCount >= this.maxFailures) {
                    this.disableRealTimeFeatures();
                    return;
                }
                
                if (this.onErrorCallback) {
                    this.onErrorCallback(error);
                }
            };
        }).catch(error => {
            // Failed to get WebSocket config
        });

        return true;
    }

    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.stopHeartbeat();
        this.channels.clear();
    }

    /**
     * Authenticate with the server
     */
    authenticate() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.send({
                type: 'authenticate',
                token: this.token
            });
        }
    }

    /**
     * Subscribe to a channel
     */
    subscribe(channel) {
        if (!this.channels.has(channel)) {
            this.channels.add(channel);
            this.send({
                type: 'subscribe',
                channel: channel
            });
        }
    }

    /**
     * Unsubscribe from a channel
     */
    unsubscribe(channel) {
        if (this.channels.has(channel)) {
            this.channels.delete(channel);
            this.send({
                type: 'unsubscribe',
                channel: channel
            });
        }
    }

    /**
     * Publish message to a channel
     */
    publish(channel, data) {
        this.send({
            type: 'publish',
            channel: channel,
            data: data
        });
    }

    /**
     * Send message to server
     */
    send(message) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(message));
        }
    }

    /**
     * Handle incoming messages
     */
    handleMessage(message) {
        switch (message.type) {
            case 'authenticated':
                this.resubscribeToChannels();
                break;

            case 'message':
                this.routeMessage(message);
                break;

            case 'ping':
                this.send({type: 'pong'});
                break;
        }
    }

    /**
     * Route messages to appropriate handlers
     */
    routeMessage(message) {
        const { channel, data } = message;

        if (channel.startsWith('comments_post_')) {
            this.handleCommentMessage(channel, data);
        } else if (channel.startsWith('collaboration_session_')) {
            this.handleCollaborationMessage(channel, data);
        } else if (channel.startsWith('activity_feed_')) {
            this.handleActivityMessage(channel, data);
        }
    }

    /**
     * Handle comment-related messages
     */
    handleCommentMessage(channel, data) {
        this.commentHandlers.forEach(handler => {
            try {
                handler(channel, data);
            } catch (e) {
                // Comment handler error
            }
        });

        // Default comment handling
        switch (data.type) {
            case 'new_comment':
                this.onNewComment(data);
                break;
            case 'comment_reply':
                this.onCommentReply(data);
                break;
        }
    }

    /**
     * Handle collaboration-related messages
     */
    handleCollaborationMessage(channel, data) {
        this.collaborationHandlers.forEach(handler => {
            try {
                handler(channel, data);
            } catch (e) {
                // Collaboration handler error
            }
        });

        // Default collaboration handling
        switch (data.type) {
            case 'content_update':
                this.onContentUpdate(data);
                break;
            case 'session_update':
                this.onSessionUpdate(data);
                break;
            case 'conflict_detected':
                this.onConflictDetected(data);
                break;
            case 'approval_update':
                this.onApprovalUpdate(data);
                break;
        }
    }

    /**
     * Handle activity feed messages
     */
    handleActivityMessage(channel, data) {
        this.activityHandlers.forEach(handler => {
            try {
                handler(channel, data);
            } catch (e) {
                // Activity handler error
            }
        });

        // Default activity handling
        this.onActivityUpdate(data);
    }

    /**
     * Disable real-time features due to connection failures
     */
    disableRealTimeFeatures() {
        this.connectionEnabled = false;
        
        // Dispatch an event so UI can handle graceful degradation
        document.dispatchEvent(new CustomEvent('smoRealTimeDisabled', {
            detail: { reason: 'connection_failure' }
        }));
    }

    /**
     * Enable real-time features again
     */
    enableRealTimeFeatures() {
        this.connectionEnabled = true;
        
        // Dispatch an event so UI can handle re-enabling
        document.dispatchEvent(new CustomEvent('smoRealTimeEnabled'));
    }

    /**
     * Handle reconnection with failure tracking
     */
    handleReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts && this.connectionEnabled) {
            this.reconnectAttempts++;
            const delay = Math.min(this.reconnectInterval * this.reconnectAttempts, 30000); // Cap at 30 seconds
            setTimeout(() => {
                this.connect();
            }, delay);
        } else if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this.disableRealTimeFeatures();
        }
    }

    /**
     * Resubscribe to all channels after reconnection
     */
    resubscribeToChannels() {
        this.channels.forEach(channel => {
            this.send({
                type: 'subscribe',
                channel: channel
            });
        });
    }

    /**
     * Start heartbeat
     */
    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.send({type: 'ping'});
            }
        }, 30000); // 30 seconds
    }

    /**
     * Stop heartbeat
     */
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }

    /**
     * Get WebSocket configuration from server (with caching)
     */
    async getWebSocketConfig() {
        const now = Date.now();
        
        // Return cached config if still valid
        if (this.configCache && (now - this.lastConfigRequest) < this.configCacheTimeout) {
            return this.configCache;
        }
        
        try {
            // Use improved nonce strategy for WebSocket config (simplified)
            let nonce = null;
            
            // Priority 1: Use wp_rest nonce if available
            if (typeof window.smoChatConfig !== 'undefined' && window.smoChatConfig.restNonce) {
                nonce = window.smoChatConfig.restNonce;
            }
            // Priority 2: Fallback to smo_social_nonce
            else if (typeof window.smoChatConfig !== 'undefined' && window.smoChatConfig.nonce) {
                nonce = window.smoChatConfig.nonce;
            }
            // Priority 3: Use smo_ajax_object nonce
            else if (typeof window.smo_ajax_object !== 'undefined' && window.smo_ajax_object.nonce) {
                nonce = window.smo_ajax_object.nonce;
            }
            
            // Update request timestamp
            this.lastConfigRequest = now;
            
            let config;
            if (nonce) {
                // Request with nonce
                try {
                    const response = await fetch(window.smo_ajax_object.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'smo_get_websocket_config',
                            nonce: nonce
                        })
                    });

                    if (response.ok) {
                        config = await response.json();
                    } else {
                        throw new Error(`HTTP ${response.status}`);
                    }
                } catch (error) {
                    // Fallback to request without nonce
                    config = await this.getWebSocketConfigWithoutNonce();
                }
            } else {
                // Request without nonce
                config = await this.getWebSocketConfigWithoutNonce();
            }
            
            // Cache the result
            this.configCache = config;
            return config;
            
        } catch (error) {
            // Return cached config if available, otherwise empty config
            if (this.configCache) {
                return this.configCache;
            }
            
            return { url: null };
        }
    }

    /**
     * Get WebSocket configuration with specific nonce
     */
    async getWebSocketConfigWithNonce(nonce) {
        try {
            const response = await fetch(window.smo_ajax_object.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-WP-Nonce': nonce
                },
                body: new URLSearchParams({
                    action: 'smo_get_websocket_config',
                    nonce: nonce
                })
            });

            if (response.ok) {
                const config = await response.json();
                
                // Handle both old and new response formats
                if (config.success === false) {
                    return { url: null };
                }
                
                return config;
            }
        } catch (error) {
            // Failed to get WebSocket config with specific nonce
        }

        return { url: null };
    }

    /**
     * Get WebSocket configuration without nonce (fallback method)
     */
    async getWebSocketConfigWithoutNonce() {
        try {
            const response = await fetch(window.smo_ajax_object.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                    // No nonce header for fallback
                },
                body: new URLSearchParams({
                    action: 'smo_get_websocket_config',
                    timestamp: Date.now() // Add timestamp to prevent caching issues
                })
            });

            if (response.ok) {
                const config = await response.json();
                
                // Handle both old and new response formats
                if (config.success === false) {
                    return { url: null };
                }
                
                // Set a flag to indicate this was a fallback request
                config.fallback = true;
                return config;
            }
        } catch (error) {
            // Failed to get WebSocket config without nonce
        }

        return { url: null };
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

    // Default event handlers (can be overridden)

    onNewComment(data) {
        // Trigger custom event for UI updates
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
window.SMORealTime = new SMORealTimeClient();

// Auto-connect if token is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof smo_ajax_object !== 'undefined' && smo_ajax_object.websocket_token) {
        window.SMORealTime.connect(smo_ajax_object.websocket_token);
    }
});
