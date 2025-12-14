/**
 * SMO Social Chat Interface
 * Handles real-time chat functionality with AI providers
 */
class SMOChatInterface {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            apiRoot: window.smoChatConfig?.root || '/wp-json/smo-social/v1/',
            nonce: window.smoChatConfig?.nonce || '',
            userId: window.smoChatConfig?.userId || 0,
            theme: options.theme || 'light',
            autoScroll: options.autoScroll !== false,
            showTyping: options.showTyping !== false,
            streamingEnabled: options.streamingEnabled !== false,
            apiWorking: false,
            wsUrl: window.smoChatConfig?.wsUrl || null,
            ...options
        };

        this.currentSession = null;
        this.sessions = [];
        this.providers = [];
        this.isTyping = false;
        this.isProcessing = false;
        this.currentStream = null;
        this.abortController = null;
        this.pollingInterval = null;
        this.ws = null;

        this.init();
    }

    /**
     * Create a fallback container if the specified one doesn't exist
     */
    createFallbackContainer() {
        const container = document.createElement('div');
        container.id = 'smo-chat-fallback-container';
        container.className = 'smo-chat-container';
        container.style.position = 'fixed';
        container.style.bottom = '20px';
        container.style.right = '20px';
        container.style.width = '350px';
        container.style.height = '500px';
        container.style.zIndex = '9999';

        document.body.appendChild(container);
        return container;
    }

    async init() {
        try {
            // Verify container exists and is properly initialized
            if (!this.container) {
                this.container = this.createFallbackContainer();
            }

            // Test basic REST API connectivity first
            try {
                await this.testRestAPI();
            } catch (apiError) {
                // Continue without API if test fails
            }

            await this.loadStyles();

            // Only try to load providers/sessions if API is working
            if (this.options.apiWorking) {
                await this.loadProviders();
                await this.loadSessions();
            }

            this.render();
            this.bindEvents();
            this.setupRealtimeUpdates();
        } catch (error) {
            this.showError('Failed to initialize chat interface');
        }
    }

    async testRestAPI() {
        try {
            const response = await this.apiRequest('test');
            this.options.apiWorking = true;
            return response;
        } catch (error) {
            // Try fallback check
            try {
                const response = await this.apiRequest('chat/providers');
                if (response.success) {
                    this.options.apiWorking = true;
                    this.providers = response.data.providers;
                }
            } catch (e) {
                this.options.apiWorking = false;
            }
        }
    }

    async loadStyles() {
        // Styles are now loaded via wp_enqueue_style in PHP
        return Promise.resolve();
    }

    async loadProviders() {
        if (this.providers.length > 0) return;
        try {
            const response = await this.apiRequest('chat/providers');
            if (response.success) {
                this.providers = response.data.providers;
            }
        } catch (error) {
            this.showError('Failed to load providers: ' + error.message);
        }
    }

    async loadSessions() {
        try {
            const response = await this.apiRequest('chat/session?limit=50');
            if (response.success) {
                this.sessions = response.data.sessions || [];
                if (this.sessions.length > 0 && !this.currentSession) {
                    this.setCurrentSession(this.sessions[0]);
                }
            }
        } catch (error) {
            // Silently fail for session loading
        }
    }

    render() {
        this.container.innerHTML = `
            <div class="smo-chat-container" role="application" aria-label="AI Chat Assistant">
                <div class="smo-chat-header">
                    <h3 class="smo-chat-title">AI Assistant</h3>
                    <div class="smo-chat-controls">
                        <label for="smo-chat-provider" class="sr-only">Select AI Provider</label>
                        <select class="smo-chat-select" id="smo-chat-provider" aria-describedby="provider-help">
                            <option value="">Select Provider</option>
                            ${this.providers.map(provider =>
            `<option value="${provider.id}">${provider.name}</option>`
        ).join('')}
                        </select>
                        <span id="provider-help" class="sr-only">Choose the AI provider for your chat session</span>

                        <label for="smo-chat-model" class="sr-only">Select AI Model</label>
                        <select class="smo-chat-select" id="smo-chat-model" aria-describedby="model-help">
                            <option value="">Select Model</option>
                        </select>
                        <span id="model-help" class="sr-only">Choose the AI model for generating responses</span>

                        <div class="smo-chat-actions">
                            <button class="smo-chat-action-btn" id="smo-chat-new-session" aria-label="Start a new chat session">
                                <span class="dashicons dashicons-plus"></span> New
                            </button>
                            <button class="smo-chat-action-btn" id="smo-chat-sessions" aria-expanded="false" aria-haspopup="listbox" aria-label="View chat sessions">
                                <span class="dashicons dashicons-list-view"></span> History
                            </button>
                        </div>
                    </div>
                </div>

                <div class="smo-chat-messages" id="smo-chat-messages" role="log" aria-live="polite" aria-label="Chat messages">
                    <div class="smo-chat-typing" id="smo-chat-typing" aria-hidden="true">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>

                <div class="smo-chat-input-container">
                    <div class="smo-chat-input-wrapper">
                        <label for="smo-chat-input" class="sr-only">Type your message</label>
                        <textarea
                            class="smo-chat-input-field"
                            id="smo-chat-input"
                            placeholder="Type your message here..."
                            rows="1"
                            aria-describedby="input-help"
                        ></textarea>
                        <span id="input-help" class="sr-only">Press Enter to send, Shift+Enter for new line</span>
                        <button class="smo-chat-send" id="smo-chat-send" disabled aria-label="Send message">
                            <span>Send</span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>

                <div class="smo-chat-session-list" id="smo-chat-session-list" role="listbox" aria-label="Chat sessions" aria-hidden="true"></div>
            </div>
        `;
    }

    bindEvents() {
        // Send message
        const sendBtn = this.container.querySelector('#smo-chat-send');
        const inputField = this.container.querySelector('#smo-chat-input');

        sendBtn.addEventListener('click', () => this.sendMessage());
        inputField.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Auto-resize textarea
        inputField.addEventListener('input', () => {
            this.autoResizeTextarea(inputField);
            this.updateSendButton();
        });

        // Provider/model selection
        const providerSelect = this.container.querySelector('#smo-chat-provider');
        const modelSelect = this.container.querySelector('#smo-chat-model');

        providerSelect.addEventListener('change', () => this.updateModels());

        // Session management
        const newSessionBtn = this.container.querySelector('#smo-chat-new-session');
        const sessionsBtn = this.container.querySelector('#smo-chat-sessions');

        newSessionBtn.addEventListener('click', () => this.createNewSession());
        sessionsBtn.addEventListener('click', () => this.toggleSessionList());

        // Session selection
        const sessionList = this.container.querySelector('#smo-chat-session-list');
        sessionList.addEventListener('click', (e) => {
            const item = e.target.closest('.smo-chat-session-item');
            if (item) {
                const sessionId = item.dataset.sessionId;
                this.selectSession(sessionId);
            }
        });

        // Keyboard navigation for session list
        sessionList.addEventListener('keydown', (e) => this.handleSessionListKeydown(e));

        // Accessibility: announce status changes
        this.setupAccessibilityAnnouncements();
    }

    setupAccessibilityAnnouncements() {
        const liveRegion = document.createElement('div');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.style.position = 'absolute';
        liveRegion.style.left = '-10000px';
        liveRegion.style.width = '1px';
        liveRegion.style.height = '1px';
        liveRegion.style.overflow = 'hidden';
        liveRegion.id = 'smo-chat-live-region';

        this.container.appendChild(liveRegion);
        this.liveRegion = liveRegion;
    }

    announceToScreenReader(message) {
        if (this.liveRegion) {
            this.liveRegion.textContent = message;
            setTimeout(() => {
                this.liveRegion.textContent = '';
            }, 1000);
        }
    }

    handleSessionListKeydown(e) {
        const sessionItems = Array.from(this.container.querySelectorAll('.smo-chat-session-item'));
        const currentIndex = sessionItems.indexOf(e.target);

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                const nextIndex = currentIndex < sessionItems.length - 1 ? currentIndex + 1 : 0;
                sessionItems[nextIndex].focus();
                break;
            case 'ArrowUp':
                e.preventDefault();
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : sessionItems.length - 1;
                sessionItems[prevIndex].focus();
                break;
            case 'Enter':
            case ' ':
                e.preventDefault();
                e.target.click();
                break;
            case 'Escape':
                this.toggleSessionList();
                break;
        }
    }

    setupRealtimeUpdates() {
        // Setup periodic refresh of sessions and messages
        setInterval(() => {
            this.refreshMessages();
        }, 5000);

        // Setup WebSocket for real-time updates (if available)
        this.setupWebSocket();
    }

    async setupWebSocket() {
        if (typeof WebSocket !== 'undefined') {
            try {
                const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const wsUrl = this.options.wsUrl || `${protocol}//${window.location.hostname}:8080/chat`;
                this.ws = new WebSocket(wsUrl);

                this.ws.onopen = () => {
                    // WebSocket connected successfully
                };

                this.ws.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    this.handleRealtimeUpdate(data);
                };

                this.ws.onclose = (event) => {
                    this.fallbackToPolling();
                };

                this.ws.onerror = (error) => {
                    this.fallbackToPolling();
                };

                // Set a timeout for connection attempt
                setTimeout(() => {
                    if (this.ws && this.ws.readyState === WebSocket.CONNECTING) {
                        this.ws.close();
                        this.fallbackToPolling();
                    }
                }, 5000);

            } catch (error) {
                this.fallbackToPolling();
            }
        } else {
            this.fallbackToPolling();
        }
    }

    fallbackToPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        this.pollingInterval = setInterval(() => {
            this.pollForUpdates();
        }, 10000);
    }

    async pollForUpdates() {
        try {
            await this.loadSessions();
            if (this.currentSession) {
                await this.loadSessionMessages(this.currentSession.id);
            }
        } catch (error) {
            // Silently handle polling failures
        }
    }

    cleanup() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }

    handleRealtimeUpdate(data) {
        switch (data.type) {
            case 'typing':
                this.showTypingIndicator(data.isTyping);
                break;
            case 'message':
                this.addMessage(data.message);
                break;
            case 'session_update':
                this.updateSession(data.session);
                break;
        }
    }

    async sendMessage() {
        const inputField = this.container.querySelector('#smo-chat-input');
        const content = inputField.value.trim();

        if (!content || this.isProcessing) return;

        this.isProcessing = true;
        this.updateSendButton();
        this.showTypingIndicator(true);

        try {
            const userMessage = {
                id: Date.now(),
                role: 'user',
                content: content,
                timestamp: new Date().toISOString()
            };
            this.addMessage(userMessage);

            inputField.value = '';
            this.autoResizeTextarea(inputField);
            this.updateSendButton();

            if (this.options.streamingEnabled) {
                await this.streamMessage(content);
            } else {
                await this.standardMessage(content);
            }

        } catch (error) {
            this.showError('Failed to send message. Please try again.');
        } finally {
            this.isProcessing = false;
            this.showTypingIndicator(false);
            this.updateSendButton();
        }
    }

    async streamMessage(content) {
        const messagesContainer = this.container.querySelector('#smo-chat-messages');

        const assistantMessageId = 'stream-' + Date.now();
        const assistantMessage = {
            id: assistantMessageId,
            role: 'assistant',
            content: '',
            timestamp: new Date().toISOString(),
            streaming: true
        };

        this.addMessage(assistantMessage);

        this.abortController = new AbortController();

        try {
            // Get a valid nonce using the standardized strategy
            const nonce = await this.getValidNonce();
            if (!nonce) {
                throw new Error('Authentication failed: No valid nonce available');
            }

            const response = await fetch(`${this.options.apiRoot}chat/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    session_id: this.currentSession?.id,
                    content: content,
                    stream: true
                }),
                signal: this.abortController.signal
            });

            if (!response.ok) {
                // Handle nonce errors in streaming
                if (response.status === 403) {
                    const errorData = await response.json().catch(() => ({}));
                    if (errorData.code === 'rest_cookie_invalid_nonce' || errorData.code === 'invalid_nonce') {
                        await this.refreshNonce();
                        // Retry once with fresh nonce
                        return this.streamMessage(content);
                    }
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                const chunk = decoder.decode(value);
                const lines = chunk.split('\n');

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.slice(6));
                            if (data.content) {
                                this.updateStreamingMessage(assistantMessageId, data.content);
                            }
                            if (data.done) {
                                this.finishStreamingMessage(assistantMessageId, data);
                                this.abortController = null;
                                return;
                            }
                        } catch (e) {
                            // Failed to parse streaming data
                        }
                    }
                }
            }

        } catch (error) {
            if (error.name === 'AbortError') {
                this.updateStreamingMessage(assistantMessageId, 'Response generation was cancelled.');
            } else {
                this.updateStreamingMessage(assistantMessageId, 'Sorry, I encountered an error while streaming the response.');
            }
            this.abortController = null;
        }
    }

    async standardMessage(content) {
        try {
            const response = await this.apiRequest('chat/message', {
                method: 'POST',
                body: JSON.stringify({
                    session_id: this.currentSession?.id,
                    content: content,
                    content_type: 'text'
                })
            });

            if (response.success) {
                this.addMessage(response.data.assistant_message);
            } else {
                throw new Error(response.message || 'Failed to get response');
            }
        } catch (error) {
            this.showError('Failed to get AI response. Please try again.');
        }
    }

    async loadSessionMessages(sessionId) {
        try {
            const response = await this.apiRequest(`chat/session/${sessionId}/messages`);
            if (response.success) {
                this.displayMessages(response.data.messages);
            }
        } catch (error) {
            // Silently handle session message loading failures
        }
    }

    addMessage(message) {
        const messagesContainer = this.container.querySelector('#smo-chat-messages');
        const messageElement = this.createMessageElement(message);

        messagesContainer.insertBefore(messageElement,
            messagesContainer.querySelector('#smo-chat-typing'));

        if (this.options.autoScroll) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    createMessageElement(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `smo-chat-message ${message.role}`;
        messageDiv.id = `message-${message.id}`;

        const streamingIndicator = message.streaming ?
            '<span class="smo-chat-streaming">Generating response...</span>' : '';

        const cancelButton = message.streaming ?
            '<button type="button" class="smo-chat-cancel-btn" data-message-id="' + message.id + '">Cancel</button>' : '';

        messageDiv.innerHTML = `
            <div class="smo-chat-message-content">
                ${this.formatMessageContent(message.content, message.content_type)}
            </div>
            <div class="smo-chat-message-meta">
                <span>${new Date(message.timestamp).toLocaleTimeString()}</span>
                ${message.tokens_used ? `<span>${message.tokens_used} tokens</span>` : ''}
                ${streamingIndicator}
                ${cancelButton}
            </div>
        `;

        if (message.streaming) {
            const cancelBtn = messageDiv.querySelector('.smo-chat-cancel-btn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => this.cancelStreaming(message.id));
            }
        }

        return messageDiv;
    }

    formatMessageContent(content, contentType = 'text') {
        switch (contentType) {
            case 'markdown':
                return this.markdownToHtml(content);
            case 'code':
                return `<pre><code>${this.escapeHtml(content)}</code></pre>`;
            default:
                return this.escapeHtml(content).replace(/\n/g, '<br>');
        }
    }

    markdownToHtml(markdown) {
        return markdown
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showTypingIndicator(show) {
        const typingElement = this.container.querySelector('#smo-chat-typing');
        if (show) {
            typingElement.classList.add('show');
            if (this.options.autoScroll) {
                typingElement.scrollIntoView({ behavior: 'smooth' });
            }
        } else {
            typingElement.classList.remove('show');
        }
        this.isTyping = show;
    }

    showError(message) {
        let messagesContainer = this.container?.querySelector('#smo-chat-messages');

        if (!messagesContainer) {
            if (this.container) {
                messagesContainer = this.container;
            } else {
                messagesContainer = document.body;
            }
        }

        const errorElement = document.createElement('div');
        errorElement.className = 'smo-chat-error';
        errorElement.textContent = 'Chat Error: ' + message;

        try {
            const typingElement = messagesContainer.querySelector('#smo-chat-typing');
            if (typingElement && messagesContainer.contains(typingElement)) {
                messagesContainer.insertBefore(errorElement, typingElement);
            } else {
                messagesContainer.appendChild(errorElement);
            }

            if (this.options.autoScroll && messagesContainer.scrollHeight) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } catch (insertError) {
            messagesContainer.appendChild(errorElement);
        }

        setTimeout(() => {
            if (errorElement.parentNode) {
                errorElement.remove();
            }
        }, 5000);
    }

    async createNewSession() {
        try {
            const response = await this.apiRequest('chat/session', {
                method: 'POST',
                body: JSON.stringify({
                    session_name: `Chat ${new Date().toLocaleString()}`,
                    provider_id: this.getSelectedProviderId(),
                    model_name: this.getSelectedModelName()
                })
            });

            if (response.success) {
                this.sessions.unshift(response.data);
                this.setCurrentSession(response.data);
                this.clearMessages();
            }
        } catch (error) {
            this.showError('Failed to create new chat session.');
        }
    }

    async setCurrentSession(session) {
        this.currentSession = session;
        await this.loadSessionMessages(session.id);
        this.updateSessionDisplay();
    }

    updateSessionDisplay() {
        const title = this.container.querySelector('.smo-chat-title');
        if (this.currentSession && title) {
            title.textContent = this.currentSession.session_name || 'AI Assistant';
        }
    }

    clearMessages() {
        const messagesContainer = this.container.querySelector('#smo-chat-messages');
        const typingElement = messagesContainer.querySelector('#smo-chat-typing');
        messagesContainer.querySelectorAll('.smo-chat-message').forEach(msg => msg.remove());
        messagesContainer.insertBefore(typingElement, null);
    }

    displayMessages(messages) {
        this.clearMessages();
        messages.forEach(message => {
            this.addMessage(message);
        });
    }

    updateModels() {
        const providerSelect = this.container.querySelector('#smo-chat-provider');
        const modelSelect = this.container.querySelector('#smo-chat-model');
        const selectedProviderId = providerSelect.value;

        modelSelect.innerHTML = '<option value="">Select Model</option>';

        if (!selectedProviderId) return;

        const provider = this.providers.find(p => p.id == selectedProviderId);
        if (provider && provider.models) {
            provider.models.forEach(model => {
                const option = document.createElement('option');
                option.value = model.name;
                option.textContent = model.name;
                modelSelect.appendChild(option);
            });
        }
    }

    getSelectedProviderId() {
        const providerSelect = this.container.querySelector('#smo-chat-provider');
        return providerSelect.value ? parseInt(providerSelect.value) : null;
    }

    getSelectedModelName() {
        const modelSelect = this.container.querySelector('#smo-chat-model');
        return modelSelect.value || null;
    }

    updateSendButton() {
        const sendBtn = this.container.querySelector('#smo-chat-send');
        const inputField = this.container.querySelector('#smo-chat-input');
        const hasContent = inputField.value.trim().length > 0;
        sendBtn.disabled = !hasContent || this.isProcessing;
    }

    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    toggleSessionList() {
        const sessionList = this.container.querySelector('#smo-chat-session-list');
        const sessionsBtn = this.container.querySelector('#smo-chat-sessions');

        if (sessionList.style.display === 'block') {
            sessionList.style.display = 'none';
            sessionList.setAttribute('aria-hidden', 'true');
            sessionsBtn.setAttribute('aria-expanded', 'false');
            this.announceToScreenReader('Chat sessions list closed');
        } else {
            this.renderSessionList();
            sessionList.style.display = 'block';
            sessionList.setAttribute('aria-hidden', 'false');
            sessionsBtn.setAttribute('aria-expanded', 'true');
            this.announceToScreenReader('Chat sessions list opened');
        }
    }

    renderSessionList() {
        const sessionList = this.container.querySelector('#smo-chat-session-list');
        sessionList.innerHTML = '';

        if (this.sessions.length === 0) {
            sessionList.innerHTML = '<div class="smo-chat-session-item">No previous sessions</div>';
            return;
        }

        this.sessions.forEach(session => {
            const item = document.createElement('div');
            item.className = 'smo-chat-session-item';
            if (this.currentSession && session.id === this.currentSession.id) {
                item.classList.add('active');
            }
            item.dataset.sessionId = session.id;
            item.tabIndex = 0;
            item.innerHTML = `
                <div class="smo-chat-session-name">${session.session_name || 'Untitled Session'}</div>
                <div class="smo-chat-session-date">${new Date(session.created_at).toLocaleDateString()}</div>
            `;
            sessionList.appendChild(item);
        });
    }

    selectSession(sessionId) {
        const session = this.sessions.find(s => s.id == sessionId);
        if (session) {
            this.setCurrentSession(session);
            this.toggleSessionList(); // Close list
        }
    }

    updateStreamingMessage(messageId, content) {
        const messageDiv = this.container.querySelector(`#message-${messageId}`);
        if (messageDiv) {
            const contentDiv = messageDiv.querySelector('.smo-chat-message-content');
            // Append content, handling markdown if needed
            // For now, just append text
            const currentContent = contentDiv.innerHTML;
            // This is a simplification. In a real streaming scenario, we'd append to a buffer and re-render markdown
            // But for now, let's assume content is just text chunks
            contentDiv.textContent += content;
        }
    }

    finishStreamingMessage(messageId, data) {
        const messageDiv = this.container.querySelector(`#message-${messageId}`);
        if (messageDiv) {
            // Remove streaming indicator
            const meta = messageDiv.querySelector('.smo-chat-message-meta');
            const indicator = meta.querySelector('.smo-chat-streaming');
            if (indicator) indicator.remove();

            const cancelBtn = meta.querySelector('.smo-chat-cancel-btn');
            if (cancelBtn) cancelBtn.remove();

            // Render final markdown
            const contentDiv = messageDiv.querySelector('.smo-chat-message-content');
            contentDiv.innerHTML = this.formatMessageContent(contentDiv.textContent, 'markdown');
        }
    }

    cancelStreaming(messageId) {
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
            this.updateStreamingMessage(messageId, ' [Cancelled]');
        }
    }

    refreshMessages() {
        if (this.currentSession && !this.isProcessing) {
            // Logic to check for new messages in background
            // For now, we rely on WebSocket or manual polling
        }
    }

    /**
     * Check if a nonce appears to be valid (basic validation)
     */
    isValidNonce(nonce) {
        if (!nonce || typeof nonce !== 'string') {
            return false;
        }

        // WordPress nonces are typically 10-12 characters
        // This is a basic length check, not cryptographic validation
        return nonce.length >= 8 && nonce.length <= 20;
    }

    /**
     * Refresh the nonce by making a request to get a fresh one
     */
    async refreshNonce() {
        try {
            // Try to get a fresh nonce from WordPress REST API
            const response = await fetch('/wp-json/wp/v2/users/me', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (response.ok) {
                // Extract nonce from response headers if available
                const nonceHeader = response.headers.get('X-WP-Nonce');
                if (nonceHeader) {
                    this.options.restNonce = nonceHeader;
                    return;
                }
            }

            // Alternative: try to refresh via REST API nonce endpoint
            try {
                const refreshUrl = this.options.apiRoot + 'nonce/refresh';
                const nonce = this.options.nonce || window.wpApiSettings?.nonce || '';

                const refreshResponse = await fetch(refreshUrl, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({})
                });

                if (refreshResponse.ok) {
                    const data = await refreshResponse.json();
                    if (data.success && data.data.restNonce) {
                        this.options.restNonce = data.data.restNonce;
                        return;
                    }
                }
            } catch (error) {
                // Silently handle refresh failures
            }

        } catch (error) {
            // Silently handle refresh errors
        }
    }

    async apiRequest(endpoint, options = {}) {
        const url = this.options.apiRoot + endpoint;

        // Standardized nonce strategy: Always prefer wp_rest nonce for REST API
        let nonce = await this.getValidNonce();

        if (!nonce) {
            throw new Error('Authentication failed: No valid nonce available');
        }

        const headers = {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json'
        };

        const config = {
            credentials: 'same-origin',
            ...options,
            headers: {
                ...headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                // Enhanced error handling for nonce-related issues
                if (response.status === 403 && (
                    data.code === 'rest_cookie_invalid_nonce' || 
                    data.code === 'invalid_nonce' ||
                    data.message?.includes('nonce')
                )) {
                    
                    // Clear current nonce to force refresh
                    this.options.restNonce = null;
                    this.options.nonce = null;
                    
                    // Retry once with fresh nonce
                    const freshNonce = await this.getValidNonce();
                    if (freshNonce) {
                        config.headers['X-WP-Nonce'] = freshNonce;
                        const retryResponse = await fetch(url, config);
                        const retryData = await retryResponse.json();
                        
                        if (!retryResponse.ok) {
                            throw new Error(retryData.message || 'API request failed after nonce refresh');
                        }
                        
                        return retryData;
                    } else {
                        throw new Error('Failed to refresh nonce: Authentication unavailable');
                    }
                }
                throw new Error(data.message || 'API request failed');
            }

            return data;
        } catch (error) {
            // Handle network errors and other fetch issues
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error('Network error: Unable to connect to API');
            }
            throw error;
        }
    }

    /**
     * Get a valid nonce using standardized strategy
     */
    async getValidNonce() {
        // Primary strategy: wp_rest nonce from WordPress REST API
        if (this.options.restNonce && this.isValidNonce(this.options.restNonce)) {
            return this.options.restNonce;
        }

        // Fallback to window.wpApiSettings if available
        if (window.wpApiSettings && window.wpApiSettings.nonce && this.isValidNonce(window.wpApiSettings.nonce)) {
            this.options.restNonce = window.wpApiSettings.nonce;
            return window.wpApiSettings.nonce;
        }

        // Try to refresh nonce
        await this.refreshNonce();
        
        if (this.options.restNonce && this.isValidNonce(this.options.restNonce)) {
            return this.options.restNonce;
        }

        // Final fallback: use smo_social_nonce if available
        if (this.options.nonce && this.isValidNonce(this.options.nonce)) {
            return this.options.nonce;
        }

        return null;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('smo-chat-container')) {
        window.smoChat = new SMOChatInterface('smo-chat-container');
    }
});
