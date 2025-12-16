/**
 * SMO Social - Content Import & Automation Enhanced JavaScript
 * Handles all interactive functionality for the Content Import interface
 */

(function($) {
    'use strict';

    const SMOContentImport = {

        /**
         * API Key validation patterns
         */
        validationPatterns: {
            'openai': {
                pattern: /^sk-[a-zA-Z0-9]{48,}$/,
                example: 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'OpenAI keys start with "sk-" followed by 48+ alphanumeric characters',
                minLength: 50,
                maxLength: 100
            },
            'anthropic': {
                pattern: /^sk-ant-api03-[a-zA-Z0-9_-]{95,}$/,
                example: 'sk-ant-api03-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Anthropic keys start with "sk-ant-api03-" followed by 95+ alphanumeric characters and underscores',
                minLength: 110,
                maxLength: 150
            },
            'openrouter': {
                pattern: /^sk-or-v1-[a-zA-Z0-9]{64}$/,
                example: 'sk-or-v1-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'OpenRouter keys start with "sk-or-v1-" followed by 64 alphanumeric characters',
                minLength: 74,
                maxLength: 74
            },
            'huggingface': {
                pattern: /^hf_[a-zA-Z0-9]{34,}$/,
                example: 'hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'HuggingFace keys start with "hf_" followed by 34+ alphanumeric characters',
                minLength: 37,
                maxLength: 50
            },
            'replicate': {
                pattern: /^r8_[a-zA-Z0-9]{40}$/,
                example: 'r8_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Replicate keys start with "r8_" followed by 40 alphanumeric characters',
                minLength: 43,
                maxLength: 43
            },
            'together': {
                pattern: /^[a-zA-Z0-9]{64}$/,
                example: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Together AI keys are 64 alphanumeric characters',
                minLength: 64,
                maxLength: 64
            },
            'cohere': {
                pattern: /^[a-zA-Z0-9]{40}$/,
                example: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Cohere keys are 40 alphanumeric characters',
                minLength: 40,
                maxLength: 40
            },
            'stability': {
                pattern: /^sk-[a-zA-Z0-9]{48}$/,
                example: 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Stability AI keys start with "sk-" followed by 48 alphanumeric characters',
                minLength: 51,
                maxLength: 51
            },
            'midjourney': {
                pattern: /^[a-zA-Z0-9]{25,}$/,
                example: 'xxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Midjourney keys are 25+ alphanumeric characters',
                minLength: 25,
                maxLength: 50
            },
            'elevenlabs': {
                pattern: /^[a-zA-Z0-9]{32}$/,
                example: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'ElevenLabs keys are 32 alphanumeric characters',
                minLength: 32,
                maxLength: 32
            },
            'runway': {
                pattern: /^[a-zA-Z0-9_-]{40,}$/,
                example: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Runway ML keys are 40+ alphanumeric characters and underscores',
                minLength: 40,
                maxLength: 60
            },
            'perplexity': {
                pattern: /^pplx-[a-zA-Z0-9]{60,}$/,
                example: 'pplx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Perplexity keys start with "pplx-" followed by 60+ alphanumeric characters',
                minLength: 65,
                maxLength: 80
            },
            'groq': {
                pattern: /^gsk_[a-zA-Z0-9]{50,}$/,
                example: 'gsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Groq keys start with "gsk_" followed by 50+ alphanumeric characters',
                minLength: 54,
                maxLength: 70
            },
            'fireworks': {
                pattern: /^fw_[a-zA-Z0-9]{40,}$/,
                example: 'fw_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Fireworks AI keys start with "fw_" followed by 40+ alphanumeric characters',
                minLength: 43,
                maxLength: 60
            },
            'voyage': {
                pattern: /^pa-[a-zA-Z0-9_-]{95,}$/,
                example: 'pa-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                description: 'Voyage AI keys start with "pa-" followed by 95+ alphanumeric characters and underscores',
                minLength: 98,
                maxLength: 120
            },
            'custom-api': {
                pattern: /^[a-zA-Z0-9_-]{16,}$/,
                example: 'xxxxxxxxxxxxxxxx',
                description: 'Custom API keys are 16+ alphanumeric characters and underscores',
                minLength: 16,
                maxLength: 256
            },
            'ollama': {
                pattern: /^[a-zA-Z0-9_-]{0,}$/,
                example: '',
                description: 'Ollama typically doesn\'t require API keys for local instances',
                minLength: 0,
                maxLength: 256
            },
            'lm-studio': {
                pattern: /^[a-zA-Z0-9_-]{0,}$/,
                example: '',
                description: 'LM Studio typically doesn\'t require API keys for local instances',
                minLength: 0,
                maxLength: 256
            }
        },

        /**
         * Initialize the module
         */
        init: function() {
            this.bindEvents();
            this.loadDashboardStats();
            this.initTabs();
            this.loadInitialData();
            this.initProviderConfig();
            this.initProviderStatusChecks();
            this.initAPIKeyValidation();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Header actions
            $('#smo-add-import-source').on('click', this.showAddSourceModal.bind(this));
            $('#smo-create-automation-rule').on('click', this.showCreateRuleModal.bind(this));

            // Quick actions
            $('#smo-sync-all-sources').on('click', this.syncAllSources.bind(this));
            $('#smo-run-automation-batch').on('click', this.runAutomationBatch.bind(this));
            $('#smo-view-logs').on('click', this.viewLogs.bind(this));
            $('#smo-export-settings').on('click', this.exportSettings.bind(this));

            // Settings save action
            $('#smo-save-settings').on('click', this.saveSettings.bind(this));

            // Tab switching
            $('.smo-tab-link').on('click', this.switchTab.bind(this));

            // Filters
            $('#source-filter-type, #source-filter-status').on('change', this.filterSources.bind(this));
            $('#content-search').on('input', this.searchContent.bind(this));
            $('#content-filter-source, #content-filter-date').on('change', this.filterContent.bind(this));
            $('#smo-analytics-period').on('change', this.loadAnalytics.bind(this));

            // Bulk actions
            $('#smo-apply-automation-bulk-action').on('click', this.applyBulkAction.bind(this));

            // Modal actions
            $('#smo-create-template').on('click', this.showCreateTemplateModal.bind(this));
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            // Set first tab as active
            $('.smo-tab-link').first().addClass('active');
            $('.smo-tab-panel').first().addClass('active');
        },

        /**
         * Switch between tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            const $link = $(e.currentTarget);
            const tabName = $link.data('tab');

            // Update active states
            $('.smo-tab-link').removeClass('active');
            $link.addClass('active');

            $('.smo-tab-panel').removeClass('active');
            $(`#${tabName}-panel`).addClass('active');

            // Load tab-specific data
            this.loadTabData(tabName);

            // Check provider statuses when switching to AI Providers tab
            if (tabName === 'ai-providers') {
                this.checkAllProviderStatuses();
            }
        },

        /**
         * Load dashboard statistics
         */
        loadDashboardStats: function() {
            $.ajax({
                url: smoContentImportEnhanced.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_import_dashboard_stats',
                    nonce: smoContentImportEnhanced.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#total-imported-items').text(response.data.total_imported);
                        $('#active-automation-rules').text(response.data.active_rules);
                        $('#automated-shares-today').text(response.data.auto_shares_today);
                        $('#automation-success-rate').text(response.data.success_rate);
                        
                        // Update tab badges
                        $('#sources-count').text(response.data.total_imported);
                        $('#rules-count').text(response.data.active_rules);
                    }
                }
            });
        },

        /**
         * Load initial data for all tabs
         */
        loadInitialData: function() {
            this.loadImportSources();
        },

        /**
         * Load tab-specific data
         */
        loadTabData: function(tabName) {
            switch(tabName) {
                case 'import-sources':
                    this.loadImportSources();
                    break;
                case 'automation-rules':
                    this.loadAutomationRules();
                    break;
                case 'transformation-templates':
                    this.loadTemplates();
                    break;
                case 'imported-content':
                    this.loadImportedContent();
                    break;
                case 'analytics':
                    this.loadAnalytics();
                    break;
            }
        },

        /**
         * Load import sources
         */
        loadImportSources: function() {
            const $container = $('#import-sources-list');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentImportEnhanced.strings.loading + '</p></div>');
            
            // Simulate loading - replace with actual AJAX call
            setTimeout(() => {
                $container.html(this.renderSourcesGrid());
            }, 500);
        },

        /**
         * Render sources grid
         */
        renderSourcesGrid: function() {
            // Example data - replace with actual data from server
            const sources = [
                {
                    id: 1,
                    name: 'Tech News RSS',
                    type: 'RSS Feed',
                    status: 'active',
                    lastSync: '2 hours ago',
                    itemsImported: 145
                },
                {
                    id: 2,
                    name: 'Blog API',
                    type: 'API',
                    status: 'active',
                    lastSync: '1 day ago',
                    itemsImported: 89
                }
            ];
            
            let html = '';
            sources.forEach(source => {
                html += `
                    <div class="smo-source-card" data-source-id="${source.id}">
                        <div class="smo-source-header">
                            <h3>${source.name}</h3>
                            <span class="smo-source-status smo-status-${source.status}">${source.status}</span>
                        </div>
                        <div class="smo-source-meta">
                            <span class="smo-source-type">${source.type}</span>
                            <span class="smo-source-sync">Last sync: ${source.lastSync}</span>
                        </div>
                        <div class="smo-source-stats">
                            <div class="smo-stat">
                                <span class="smo-stat-value">${source.itemsImported}</span>
                                <span class="smo-stat-label">Items Imported</span>
                            </div>
                        </div>
                        <div class="smo-source-actions">
                            <button class="smo-btn smo-btn-outline smo-btn-sm" onclick="SMOContentImport.syncSource(${source.id})">
                                <span class="dashicons dashicons-update"></span> Sync Now
                            </button>
                            <button class="smo-btn smo-btn-outline smo-btn-sm" onclick="SMOContentImport.editSource(${source.id})">
                                <span class="dashicons dashicons-edit"></span> Edit
                            </button>
                        </div>
                    </div>
                `;
            });
            
            return html || '<div class="smo-empty-state"><p>No import sources found. Add your first source to get started!</p></div>';
        },

        /**
         * Load automation rules
         */
        loadAutomationRules: function() {
            const $container = $('#automation-rules-list');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentImportEnhanced.strings.loading + '</p></div>');
            
            setTimeout(() => {
                $container.html(this.renderRulesGrid());
            }, 500);
        },

        /**
         * Render rules grid
         */
        renderRulesGrid: function() {
            const rules = [
                {
                    id: 1,
                    name: 'Auto-publish Tech Articles',
                    trigger: 'New RSS Item',
                    action: 'Publish to Twitter',
                    status: 'active',
                    executions: 234
                }
            ];
            
            let html = '';
            rules.forEach(rule => {
                html += `
                    <div class="smo-rule-card" data-rule-id="${rule.id}">
                        <div class="smo-rule-header">
                            <input type="checkbox" class="smo-rule-checkbox" value="${rule.id}">
                            <h3>${rule.name}</h3>
                            <span class="smo-rule-status smo-status-${rule.status}">${rule.status}</span>
                        </div>
                        <div class="smo-rule-flow">
                            <div class="smo-rule-trigger">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <span>${rule.trigger}</span>
                            </div>
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <div class="smo-rule-action">
                                <span class="dashicons dashicons-share"></span>
                                <span>${rule.action}</span>
                            </div>
                        </div>
                        <div class="smo-rule-stats">
                            <span>${rule.executions} executions</span>
                        </div>
                        <div class="smo-rule-actions">
                            <button class="smo-btn smo-btn-outline smo-btn-sm" onclick="SMOContentImport.toggleRule(${rule.id})">
                                <span class="dashicons dashicons-controls-pause"></span> Pause
                            </button>
                            <button class="smo-btn smo-btn-outline smo-btn-sm" onclick="SMOContentImport.editRule(${rule.id})">
                                <span class="dashicons dashicons-edit"></span> Edit
                            </button>
                        </div>
                    </div>
                `;
            });
            
            return html || '<div class="smo-empty-state"><p>No automation rules found. Create your first rule!</p></div>';
        },

        /**
         * Load templates
         */
        loadTemplates: function() {
            const $container = $('#templates-list');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentImportEnhanced.strings.loading + '</p></div>');
            
            setTimeout(() => {
                $container.html('<div class="smo-empty-state"><p>No templates found. Create your first template!</p></div>');
            }, 500);
        },

        /**
         * Load imported content
         */
        loadImportedContent: function() {
            const $container = $('#imported-content-list');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentImportEnhanced.strings.loading + '</p></div>');
            
            $.ajax({
                url: smoContentImportEnhanced.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_recent_imports',
                    nonce: smoContentImportEnhanced.nonce,
                    limit: 20
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(SMOContentImport.renderContentGrid(response.data));
                    } else {
                        $container.html('<div class="smo-empty-state"><p>No imported content found.</p></div>');
                    }
                }
            });
        },

        /**
         * Render content grid
         */
        renderContentGrid: function(items) {
            if (!items || items.length === 0) {
                return '<div class="smo-empty-state"><p>No imported content found.</p></div>';
            }
            
            let html = '';
            items.forEach(item => {
                html += `
                    <div class="smo-content-card">
                        <div class="smo-content-thumbnail">
                            ${item.image ? `<img src="${item.image}" alt="${item.title}">` : '<span class="dashicons dashicons-media-default"></span>'}
                        </div>
                        <div class="smo-content-info">
                            <h4>${item.title}</h4>
                            <p>${item.excerpt}</p>
                            <div class="smo-content-meta">
                                <span class="smo-content-source">${item.source}</span>
                                <span class="smo-content-date">${item.date}</span>
                            </div>
                        </div>
                        <div class="smo-content-actions">
                            <button class="smo-btn smo-btn-primary smo-btn-sm">Use Content</button>
                        </div>
                    </div>
                `;
            });
            
            return html;
        },

        /**
         * Load analytics
         */
        loadAnalytics: function() {
            const period = $('#smo-analytics-period').val() || 30;
            
            $.ajax({
                url: smoContentImportEnhanced.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_automation_performance',
                    nonce: smoContentImportEnhanced.nonce,
                    days: period
                },
                success: function(response) {
                    if (response.success) {
                        SMOContentImport.renderCharts(response.data);
                    }
                }
            });
        },

        /**
         * Render analytics charts
         */
        renderCharts: function(data) {
            // Placeholder for Chart.js implementation
        },

        /**
         * Sync all sources
         */
        syncAllSources: function() {
            this.showNotification(smoContentImportEnhanced.strings.processing, 'info');
            
            // Implement actual sync logic
            setTimeout(() => {
                this.showNotification(smoContentImportEnhanced.strings.success, 'success');
                this.loadDashboardStats();
            }, 2000);
        },

        /**
         * Run automation batch
         */
        runAutomationBatch: function() {
            this.showNotification('Running automation batch...', 'info');
            
            setTimeout(() => {
                this.showNotification('Automation batch completed!', 'success');
            }, 2000);
        },

        /**
         * Filter sources
         */
        filterSources: function() {
            const type = $('#source-filter-type').val();
            const status = $('#source-filter-status').val();
            
            // Implement filtering logic
            this.loadImportSources();
        },

        /**
         * Search content
         */
        searchContent: function() {
            const query = $('#content-search').val();
            
            // Implement search logic
            this.loadImportedContent();
        },

        /**
         * Filter content
         */
        filterContent: function() {
            this.loadImportedContent();
        },

        /**
         * Check all provider statuses
         */
        checkAllProviderStatuses: function() {
            $('.smo-provider-status').each(function() {
                const $status = $(this);
                const provider = $status.data('provider');
                SMOContentImport.checkProviderStatus(provider, $status);
            });
        },

        /**
         * Initialize provider status checks
         */
        initProviderStatusChecks: function() {
            // Check status for all visible provider configs
            $('.smo-provider-status').each(function() {
                const $status = $(this);
                const provider = $status.data('provider');
                SMOContentImport.checkProviderStatus(provider, $status);
            });

            // Set up periodic status checks
            setInterval(function() {
                $('.smo-provider-status').each(function() {
                    const $status = $(this);
                    const provider = $status.data('provider');
                    SMOContentImport.checkProviderStatus(provider, $status);
                });
            }, 300000); // Check every 5 minutes
        },

        /**
         * Check provider status via AJAX
         */
        checkProviderStatus: function(provider, $statusElement) {
            // Update status to checking
            $statusElement.removeClass('status-success status-error status-warning status-disabled')
                          .addClass('status-checking')
                          .html('<span class="smo-status-icon">⏳</span><span class="smo-status-text">Checking...</span><span class="smo-status-time">-</span>');

            // Simulate status check - replace with actual AJAX call
            $.ajax({
                url: smoContentImportEnhanced.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_check_provider_status',
                    nonce: smoContentImportEnhanced.nonce,
                    provider: provider
                },
                success: function(response) {
                    if (response.success && response.data) {
                        SMOContentImport.updateProviderStatus($statusElement, response.data);
                    } else {
                        // Show error status
                        $statusElement.removeClass('status-checking')
                                     .addClass('status-error')
                                     .html('<span class="smo-status-icon">❌</span><span class="smo-status-text">Connection Failed</span><span class="smo-status-time">' + SMOContentImport.getCurrentTime() + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    // Show error status
                    $statusElement.removeClass('status-checking')
                                 .addClass('status-error')
                                 .html('<span class="smo-status-icon">❌</span><span class="smo-status-text">Error: ' + error + '</span><span class="smo-status-time">' + SMOContentImport.getCurrentTime() + '</span>');
                }
            });
        },

        /**
         * Update provider status UI
         */
        updateProviderStatus: function($statusElement, statusData) {
            let statusClass = 'status-disabled';
            let statusIcon = '⚠️';
            let statusText = 'Not Configured';

            // Determine status based on response
            if (statusData.valid) {
                if (statusData.connected) {
                    statusClass = 'status-success';
                    statusIcon = '✅';
                    statusText = 'Connected';
                } else {
                    statusClass = 'status-warning';
                    statusIcon = '⚠️';
                    statusText = 'Configuration Invalid';
                }
            } else {
                statusClass = 'status-error';
                statusIcon = '❌';
                statusText = 'Not Configured';
            }

            // Update the status element
            $statusElement.removeClass('status-checking status-success status-error status-warning status-disabled')
                          .addClass(statusClass)
                          .html('<span class="smo-status-icon">' + statusIcon + '</span><span class="smo-status-text">' + statusText + '</span><span class="smo-status-time">' + SMOContentImport.getCurrentTime() + '</span>');

            // Add detailed message if available
            if (statusData.message) {
                $statusElement.attr('title', statusData.message);
            }
        },

        /**
         * Get current time for status display
         */
        getCurrentTime: function() {
            const now = new Date();
            return now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        },

        /**
         * Initialize provider configuration event handlers
         */
        initProviderConfig: function() {
            // Handle provider selection change
            $(document).on('change', '#smo_social_primary_provider', this.handleProviderChange.bind(this));

            // Handle save provider configuration button click
            $(document).on('click', '.smo-save-provider-config', this.saveProviderConfig.bind(this));
        },

        /**
         * Initialize API key validation
         */
        initAPIKeyValidation: function() {
            // Bind validation events to API key inputs
            $(document).on('input', 'input[name*="_key"], input[name*="_api_key"]', this.validateAPIKeyRealtime.bind(this));
            $(document).on('blur', 'input[name*="_key"], input[name*="_api_key"]', this.validateAPIKeyOnBlur.bind(this));

            // Also validate on provider change to show appropriate validation for the selected provider
            $(document).on('change', '#smo_social_primary_provider', this.updateProviderValidation.bind(this));
        },

        /**
         * Real-time API key validation (on input)
         */
        validateAPIKeyRealtime: function(e) {
            const $input = $(e.target);
            const provider = this.getProviderFromInput($input);
            const value = $input.val().trim();

            // Clear previous validation state
            this.clearValidationState($input);

            // Only show validation feedback if there's content
            if (value.length > 0) {
                const result = this.validateAPIKeyFormat(value, provider);
                this.showValidationFeedback($input, result, false); // false = don't show error message yet
            }
        },

        /**
         * API key validation on blur (final validation)
         */
        validateAPIKeyOnBlur: function(e) {
            const $input = $(e.target);
            const provider = this.getProviderFromInput($input);
            const value = $input.val().trim();

            // Clear previous validation state
            this.clearValidationState($input);

            if (value.length > 0) {
                const result = this.validateAPIKeyFormat(value, provider);
                this.showValidationFeedback($input, result, true); // true = show error message
            }
        },

        /**
         * Update validation when provider changes
         */
        updateProviderValidation: function(e) {
            const provider = $(e.target).val();
            const $keyInput = $(`input[name="smo_social_${provider}_key"]`);

            if ($keyInput.length && $keyInput.val().trim()) {
                const result = this.validateAPIKeyFormat($keyInput.val().trim(), provider);
                this.clearValidationState($keyInput);
                this.showValidationFeedback($keyInput, result, true);
            }
        },

        /**
         * Get provider name from input field
         */
        getProviderFromInput: function($input) {
            const name = $input.attr('name');
            if (!name) return 'generic';

            // Extract provider from name like "smo_social_openai_key"
            const match = name.match(/smo_social_([a-zA-Z0-9_-]+)_key/);
            return match ? match[1] : 'generic';
        },

        /**
         * Validate API key format
         */
        validateAPIKeyFormat: function(key, provider) {
            if (!key || key.length === 0) {
                return {
                    valid: false,
                    error: 'API key cannot be empty',
                    pattern: null
                };
            }

            const patternData = this.validationPatterns[provider];
            if (!patternData) {
                // Generic validation for unknown providers
                return this.validateGenericKey(key);
            }

            // Check length constraints
            if (key.length < patternData.minLength) {
                return {
                    valid: false,
                    error: `API key is too short. Minimum length: ${patternData.minLength} characters`,
                    pattern: patternData.pattern,
                    example: patternData.example
                };
            }

            if (patternData.maxLength && key.length > patternData.maxLength) {
                return {
                    valid: false,
                    error: `API key is too long. Maximum length: ${patternData.maxLength} characters`,
                    pattern: patternData.pattern,
                    example: patternData.example
                };
            }

            // Check pattern match
            if (!patternData.pattern.test(key)) {
                return {
                    valid: false,
                    error: `API key format is invalid. ${patternData.description}`,
                    pattern: patternData.pattern,
                    example: patternData.example
                };
            }

            return {
                valid: true,
                message: 'API key format is valid',
                pattern: patternData.pattern
            };
        },

        /**
         * Generic validation for unknown providers
         */
        validateGenericKey: function(key) {
            if (key.length < 16) {
                return {
                    valid: false,
                    error: 'API key is too short. Minimum length: 16 characters',
                    pattern: null
                };
            }

            if (key.length > 256) {
                return {
                    valid: false,
                    error: 'API key is too long. Maximum length: 256 characters',
                    pattern: null
                };
            }

            // Should contain at least some alphanumeric characters
            if (!/[a-zA-Z0-9]/.test(key)) {
                return {
                    valid: false,
                    error: 'API key should contain alphanumeric characters',
                    pattern: null
                };
            }

            return {
                valid: true,
                message: 'API key format appears valid',
                pattern: null
            };
        },

        /**
         * Clear validation state from input
         */
        clearValidationState: function($input) {
            $input.removeClass('smo-input-valid smo-input-invalid smo-input-warning');
            $input.closest('.smo-form-group').find('.smo-validation-message').remove();
        },

        /**
         * Show validation feedback
         */
        showValidationFeedback: function($input, result, showMessage) {
            const $formGroup = $input.closest('.smo-form-group');

            // Add appropriate CSS class
            if (result.valid) {
                $input.addClass('smo-input-valid');
            } else {
                $input.addClass('smo-input-invalid');
            }

            // Show validation message if requested
            if (showMessage && !result.valid) {
                const $message = $('<div class="smo-validation-message smo-validation-error"></div>');
                $message.text(result.error);

                // Add example if available
                if (result.example) {
                    $message.append(`<br><small>Example: <code>${result.example}</code></small>`);
                }

                $formGroup.append($message);
            } else if (showMessage && result.valid) {
                const $message = $('<div class="smo-validation-message smo-validation-success"></div>');
                $message.text(result.message);
                $formGroup.append($message);
            }
        },

        /**
         * Handle provider selection change
         */
        handleProviderChange: function(e) {
            const $select = $(e.target);
            const provider = $select.val();

            if (!provider) {
                return;
            }

            // Show loading state
            this.showProviderConfigLoading();

            // AJAX call to switch provider
            $.ajax({
                url: smoContentImportEnhanced.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_switch_provider',
                    nonce: smoContentImportEnhanced.nonce,
                    provider: provider
                },
                success: function(response) {
                    if (response.success) {
                        // Update the provider configuration form
                        $('#provider-config-container').html(response.data.config_form);

                        // Show success notification
                        SMOContentImport.showNotification(response.data.message, 'success');
                    } else {
                        // Show error notification
                        SMOContentImport.showNotification(response.data.message || 'Failed to switch provider', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    // Show error notification
                    SMOContentImport.showNotification('Error switching provider: ' + error, 'error');
                }
            });
        },

        /**
         * Save provider configuration
         */
        saveProviderConfig: function(e) {
            e.preventDefault();

            const $button = $(e.target);
            const $form = $button.closest('.smo-provider-config-form');
            const provider = $form.data('provider');

            // Validate form
            const config = {};
            let isValid = true;

            $form.find('input, select').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                const value = $input.val();

                if ($input.prop('required') && !value) {
                    isValid = false;
                    $input.addClass('smo-input-error');
                } else {
                    $input.removeClass('smo-input-error');
                    config[name] = value;
                }
            });

            if (!isValid) {
                this.showNotification('Please fill in all required fields', 'error');
                return;
            }

            // Show saving state
            $button.prop('disabled', true).text('Saving...');
            $form.find('.smo-config-status').text('Saving configuration...').removeClass('success error');

            // AJAX call to save configuration
            $.ajax({
                url: smoContentImportEnhanced.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_save_provider_config',
                    nonce: smoContentImportEnhanced.nonce,
                    provider: provider,
                    config: config
                },
                success: function(response) {
                    if (response.success) {
                        // Show success status
                        $form.find('.smo-config-status').text(response.data.message).addClass('success');

                        // Log the activity
                        SMOContentImport.logActivity('PROVIDER_CONFIG_SAVED', provider);

                    } else {
                        // Show error status
                        $form.find('.smo-config-status').text(response.data.message || 'Failed to save configuration').addClass('error');
                    }
                },
                error: function(xhr, status, error) {
                    // Show error status
                    $form.find('.smo-config-status').text('Error saving configuration: ' + error).addClass('error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text('Save Configuration');
                }
            });
        },

        /**
         * Show provider config loading state
         */
        showProviderConfigLoading: function() {
            const $container = $('#provider-config-container');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>Loading provider configuration...</p></div>');
        },

        /**
         * Log activity for analytics
         */
        logActivity: function(action, resource) {
            // Simple logging for now - could be enhanced with actual tracking
            console.log('Activity logged:', action, resource);
        },

        /**
         * Apply bulk action
         */
        applyBulkAction: function() {
            const action = $('#smo-automation-bulk-action').val();
            const selected = $('.smo-rule-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (!action) {
                this.showNotification('Please select an action', 'error');
                return;
            }
            
            if (selected.length === 0) {
                this.showNotification('Please select at least one rule', 'error');
                return;
            }
            
            this.showNotification(`Applying ${action} to ${selected.length} rules...`, 'info');
        },

        /**
         * Save settings with API key validation
         */
        saveSettings: function(e) {
            e.preventDefault();

            const $button = $(e.target);
            const originalText = $button.text();

            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Validating...');

            // Validate all API keys first
            this.validateAllAPIKeys().then(validationResults => {
                // Check if all validations passed
                const failedValidations = validationResults.filter(result => !result.valid);

                if (failedValidations.length > 0) {
                    // Show validation errors
                    this.showValidationErrors(failedValidations);
                    $button.prop('disabled', false).html(originalText);
                    this.showNotification('Please fix the validation errors before saving.', 'error');
                    return;
                }

                // All validations passed, proceed with saving
                $button.html('<span class="dashicons dashicons-update"></span> Saving...');
                this.performSettingsSave().then(saveResult => {
                    if (saveResult.success) {
                        this.showNotification('Settings saved successfully!', 'success');
                        // Refresh provider statuses after save
                        this.checkAllProviderStatuses();
                    } else {
                        this.showNotification('Failed to save settings: ' + (saveResult.message || 'Unknown error'), 'error');
                    }
                }).catch(error => {
                    this.showNotification('Error saving settings: ' + error, 'error');
                }).finally(() => {
                    $button.prop('disabled', false).html(originalText);
                });

            }).catch(error => {
                this.showNotification('Validation error: ' + error, 'error');
                $button.prop('disabled', false).html(originalText);
            });
        },

        /**
         * Validate all API keys on the page
         */
        validateAllAPIKeys: function() {
            const validationPromises = [];
            const $apiKeyInputs = $('input[data-validate="api-key"]');

            $apiKeyInputs.each((index, input) => {
                const $input = $(input);
                const provider = this.getProviderFromInput($input);
                const value = $input.val().trim();

                if (value.length > 0) { // Only validate non-empty keys
                    const promise = this.validateAPIKeyServer(value, provider).then(result => {
                        return {
                            input: $input,
                            provider: provider,
                            valid: result.valid,
                            error: result.error || null,
                            example: result.example || null
                        };
                    });
                    validationPromises.push(promise);
                }
            });

            return Promise.all(validationPromises);
        },

        /**
         * Validate API key on server
         */
        validateAPIKeyServer: function(key, provider) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: smoContentImportEnhanced.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smo_validate_api_key',
                        nonce: smoContentImportEnhanced.nonce,
                        api_key: key,
                        provider: provider
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            resolve({
                                valid: false,
                                error: response.data.error || 'Validation failed'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        reject('Server validation error: ' + error);
                    }
                });
            });
        },

        /**
         * Show validation errors
         */
        showValidationErrors: function(failedValidations) {
            failedValidations.forEach(validation => {
                const $input = validation.input;
                const $formGroup = $input.closest('.smo-form-group');

                // Clear previous validation
                this.clearValidationState($input);

                // Add error class
                $input.addClass('smo-input-invalid');

                // Add error message
                const $message = $('<div class="smo-validation-message smo-validation-error"></div>');
                $message.text(validation.error);

                if (validation.example) {
                    $message.append(`<br><small>Example: <code>${validation.example}</code></small>`);
                }

                $formGroup.append($message);

                // Scroll to first error
                if (validation === failedValidations[0]) {
                    $input[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    $input.focus();
                }
            });
        },

        /**
         * Perform the actual settings save
         */
        performSettingsSave: function() {
            return new Promise((resolve, reject) => {
                // Collect all form data
                const formData = new FormData();

                // Add all visible form inputs
                $('input[name], select[name], textarea[name]').each(function() {
                    const $input = $(this);
                    const name = $input.attr('name');
                    const value = $input.val();

                    if (name && !$input.is(':disabled')) {
                        formData.append(name, value);
                    }
                });

                // Add WordPress action and nonce
                formData.append('action', 'smo_save_settings');
                formData.append('nonce', smoContentImportEnhanced.nonce);

                $.ajax({
                    url: smoContentImportEnhanced.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        reject('Save failed: ' + error);
                    }
                });
            });
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            const $notification = $('<div class="smo-notification smo-notification-' + type + '">' + message + '</div>');
            $('body').append($notification);

            setTimeout(() => {
                $notification.addClass('smo-notification-show');
            }, 100);

            setTimeout(() => {
                $notification.removeClass('smo-notification-show');
                setTimeout(() => $notification.remove(), 300);
            }, 3000);
        },

        /**
         * Show modals (placeholders)
         */
        showAddSourceModal: function() {
            // Show add source modal implementation
        },

        showCreateRuleModal: function() {
            // Show create rule modal implementation
        },

        showCreateTemplateModal: function() {
            // Show create template modal implementation
        },

        viewLogs: function() {
            // View logs implementation
        },

        exportSettings: function() {
            // Export settings implementation
        },

        syncSource: function(id) {
            // Sync source implementation
        },

        editSource: function(id) {
            // Edit source implementation
        },

        toggleRule: function(id) {
            // Toggle rule implementation
        },

        editRule: function(id) {
            // Edit rule implementation
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SMOContentImport.init();
    });

    // Expose to global scope
    window.SMOContentImport = SMOContentImport;

})(jQuery);
