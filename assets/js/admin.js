/**
 * SMO Social Admin JavaScript
 * Comprehensive social media management interface
 */

(function ($) {
    'use strict';

    // Validate AJAX configuration
    if (typeof smo_social_ajax === 'undefined') {
        console.error('SMO Social: AJAX configuration not found. Please ensure the plugin is properly activated.');
        // Provide fallback configuration
        window.smo_social_ajax = {
            ajax_url: '/wp-admin/admin-ajax.php',
            nonce: '',
            strings: {
                error: 'Error',
                success: 'Success',
                confirm_delete: 'Are you sure you want to delete this item?'
            }
        };
    }

    // Global state
    let SMOAdmin = {
        platforms: {},
        currentPreview: 'facebook',
        postContent: '',
        scheduledPosts: [],

        // Utility function for reduced motion detection
        prefersReducedMotion: function() {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        init: function () {
            this.bindEvents();
            this.loadPlatformStatus();
            this.loadDashboardData();
            this.initTooltips();
            this.initPreviewSystem();
            this.initPostComposer();
        },

        bindEvents: function () {
            // Platform management
            $(document).on('click', '.smo-connect-platform', this.connectPlatform);
            $(document).on('click', '.smo-disconnect-platform', this.disconnectPlatform);
            $(document).on('click', '.smo-test-platform', this.testPlatform);

            // Post composer
            $(document).on('input', '#smo-post-content', this.updateCharCounter);
            $(document).on('change', '.smo-platform-checkbox', this.updatePreview);
            $(document).on('click', '.smo-preview-tab', this.switchPreview);
            $(document).on('keydown', '.smo-preview-tab', this.handleTabKeydown);
            $(document).on('click', '#smo-schedule-post', this.schedulePost);
            $(document).on('click', '#smo-save-draft', this.saveDraft);

            // AI features
            $(document).on('click', '.smo-ai-generate', this.generateAIContent);
            $(document).on('click', '.smo-ai-optimize-hashtags', this.optimizeHashtags);
            $(document).on('click', '.smo-ai-alt-text', this.generateAltText);

            // Queue management
            $(document).on('click', '.smo-cancel-post', this.cancelScheduledPost);
            $(document).on('click', '.smo-retry-post', this.retryFailedPost);

            // Analytics
            $(document).on('change', '.smo-analytics-period', this.loadAnalytics);

            // Bulk actions
            $(document).on('click', '.smo-bulk-action', this.handleBulkAction);

            // Modal accessibility
            $(document).on('keydown', '.smo-modal', this.handleModalKeydown);
            $(document).on('click', '.smo-modal', this.handleModalBackdropClick);

            // Category buttons keyboard navigation
            $(document).on('keydown', '.smo-category-btn', this.handleCategoryKeydown);

            // AI Provider switching
            $(document).on('change', '#smo-primary-provider', this.switchAIProvider);

            // Trigger change event on load to show correct provider
            /* Delayed trigger to ensure DOM is fully ready if script runs early */
            setTimeout(() => {
                $('#smo-primary-provider').trigger('change');
            }, 100);
        },

        switchAIProvider: function () {
            const provider = $(this).val();
            $('.smo-provider-config').hide();
            $('#provider-config-' + provider).fadeIn(300);
        },

        // Platform Management
        connectPlatform: function (e) {
            e.preventDefault();
            const platform = $(this).data('platform');
            const $button = $(this);

            $button.prop('disabled', true).html('<span class="smo-loading"></span> Connecting...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_connect_platform',
                    platform: platform,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Redirect to OAuth URL
                        window.open(response.data.auth_url, '_blank', 'width=600,height=700');
                        SMOAdmin.showNotice('Platform connection initiated', 'success');
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                        $button.prop('disabled', false).html('Connect');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Connection failed. Please try again.', 'error');
                    $button.prop('disabled', false).html('Connect');
                }
            });
        },

        disconnectPlatform: function (e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to disconnect this platform?')) {
                return;
            }

            const platform = $(this).data('platform');
            const $button = $(this);

            $button.prop('disabled', true).html('<span class="smo-loading"></span>');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_disconnect_platform',
                    platform: platform,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.loadPlatformStatus();
                        SMOAdmin.showNotice('Platform disconnected successfully', 'success');
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Disconnection failed', 'error');
                }
            });
        },

        testPlatform: function (e) {
            e.preventDefault();
            const platform = $(this).data('platform');
            const $button = $(this);

            $button.prop('disabled', true).html('<span class="smo-loading"></span> Testing...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_test_platform',
                    platform: platform,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.showNotice('Platform connection successful', 'success');
                        SMOAdmin.loadPlatformStatus();
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Test failed', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Test Connection');
                }
            });
        },

        // Queue Management
        cancelScheduledPost: function (e) {
            e.preventDefault();
            const postId = $(this).data('post-id');
            const $button = $(this);

            if (!postId) {
                SMOAdmin.showNotice('Post ID not found', 'error');
                return;
            }

            $button.prop('disabled', true).html('<span class="smo-loading"></span> Cancelling...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_cancel_post',
                    post_id: postId,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.showNotice('Post cancelled successfully', 'success');
                        SMOAdmin.loadRecentPosts();
                    } else {
                        SMOAdmin.showNotice(response.data || 'Failed to cancel post', 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Failed to cancel post', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Cancel');
                }
            });
        },

        retryFailedPost: function (e) {
            e.preventDefault();
            const postId = $(this).data('post-id');
            const $button = $(this);

            if (!postId) {
                SMOAdmin.showNotice('Post ID not found', 'error');
                return;
            }

            $button.prop('disabled', true).html('<span class="smo-loading"></span> Retrying...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_retry_post',
                    post_id: postId,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.showNotice('Post retry initiated successfully', 'success');
                        SMOAdmin.loadRecentPosts();
                    } else {
                        SMOAdmin.showNotice(response.data || 'Failed to retry post', 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Failed to retry post', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Retry');
                }
            });
        },

        // Dashboard Data
        loadPlatformStatus: function () {
            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_get_platform_status',
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.updatePlatformStatus(response.data);
                    }
                }
            });
        },

        updatePlatformStatus: function (platforms) {
            Object.keys(platforms).forEach(function (slug) {
                const platform = platforms[slug];
                const $statusEl = $(`.smo-platform-status[data-platform="${slug}"]`);

                if ($statusEl.length) {
                    $statusEl.removeClass('connected disconnected');
                    $statusEl.addClass(platform.connected ? 'connected' : 'disconnected');

                    // Update status indicator
                    const $statusIndicator = $statusEl.find('.smo-status-indicator');
                    $statusIndicator
                        .removeClass('smo-status-connected smo-status-disconnected')
                        .addClass(platform.connected ? 'smo-status-connected' : 'smo-status-disconnected')
                        .text(platform.connected ? 'Connected' : 'Disconnected');

                    // Update action buttons
                    const $actions = $statusEl.find('.smo-platform-actions');
                    $actions.empty();

                    if (platform.connected) {
                        $actions.html(`
                            <button class="smo-btn smo-btn-sm smo-btn-secondary smo-test-platform" data-platform="${slug}">
                                Test
                            </button>
                            <button class="smo-btn smo-btn-sm smo-btn-danger smo-disconnect-platform" data-platform="${slug}">
                                Disconnect
                            </button>
                        `);
                    } else {
                        $actions.html(`
                            <button class="smo-btn smo-btn-sm smo-btn-primary smo-connect-platform" data-platform="${slug}">
                                Connect
                            </button>
                        `);
                    }
                }
            });
        },

        loadDashboardData: function () {
            // Load recent posts
            this.loadRecentPosts();

            // Load queue status
            this.loadQueueStatus();

            // Load analytics summary
            this.loadAnalyticsSummary();
        },

        loadRecentPosts: function () {
            // AJAX call to load recent scheduled posts
            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_get_recent_posts',
                    nonce: smo_social_ajax.nonce,
                    limit: 5
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.updateRecentPosts(response.data);
                    }
                }
            });
        },

        updateRecentPosts: function (posts) {
            const $container = $('.smo-recent-posts');
            if (!$container.length || !posts.length) return;

            let html = '';
            posts.forEach(function (post) {
                html += `
                    <div class="smo-queue-item">
                        <div class="smo-queue-info">
                            <div class="smo-queue-title">${post.title || 'Untitled Post'}</div>
                            <div class="smo-queue-meta">
                                ${post.platforms.join(', ')} • 
                                ${post.status} • 
                                ${new Date(post.scheduled_time).toLocaleString()}
                            </div>
                        </div>
                        <div class="smo-queue-status smo-status-${post.status}">${post.status}</div>
                    </div>
                `;
            });

            $container.html(html);
        },

        loadQueueStatus: function () {
            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_get_queue_status',
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.updateQueueStatus(response.data);
                    }
                }
            });
        },

        updateQueueStatus: function (data) {
            // Update dashboard widgets with queue stats
            $('.smo-queue-count').text(data.pending || 0);
            $('.smo-failed-count').text(data.failed || 0);
            $('.smo-processing-count').text(data.processing || 0);
        },

        loadAnalyticsSummary: function () {
            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_get_analytics_summary',
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.updateAnalyticsSummary(response.data);
                    }
                }
            });
        },

        updateAnalyticsSummary: function (data) {
            $('.smo-total-reach').text(data.total_reach || 0);
            $('.smo-total-engagement').text(data.total_engagement || 0);
            $('.smo-avg-engagement-rate').text((data.avg_engagement_rate || 0) + '%');
        },

        // Post Composer
        initPostComposer: function () {
            this.updateCharCounter();
            this.updatePreview();
        },

        updateCharCounter: function () {
            const content = $('#smo-post-content').val();
            const selectedPlatforms = this.getSelectedPlatforms() || [];
            let maxChars = 280; // Default Twitter limit

            if (selectedPlatforms.length > 0) {
                // Use the most restrictive character limit
                maxChars = Math.min(...selectedPlatforms.map(p => p.max_chars));
            }

            const charCount = content ? content.length : 0;
            const remaining = maxChars - charCount;

            const $counter = $('#smo-char-counter');
            $counter.text(`${charCount}/${maxChars} characters`);

            $counter.removeClass('warning error');
            if (remaining < 50) {
                $counter.addClass('warning');
            }
            if (remaining < 0) {
                $counter.addClass('error');
            }
        },

        getSelectedPlatforms: function () {
            const selected = [];
            $('.smo-platform-checkbox.selected').each(function () {
                const slug = $(this).find('input').val();
                if (SMOAdmin.platforms[slug]) {
                    selected.push(SMOAdmin.platforms[slug]);
                }
            });
            return selected;
        },

        updatePreview: function () {
            const content = $('#smo-post-content').val();
            const selectedPlatforms = this.getSelectedPlatforms() || [];

            // Update platform selection visual state
            $('.smo-platform-checkbox').removeClass('selected');
            $('.smo-platform-checkbox input:checked').closest('.smo-platform-checkbox').addClass('selected');

            // Update previews for each platform
            selectedPlatforms.forEach(function (platform) {
                SMOAdmin.updatePlatformPreview(platform, content);
            });
        },

        updatePlatformPreview: function (platform, content) {
            const $preview = $(`.smo-preview-content[data-platform="${platform.slug}"]`);
            if (!$preview.length) return;

            // Update content
            $preview.find('.smo-preview-content-text').text(content || 'Your post content will appear here...');

            // Add platform-specific formatting
            switch (platform.slug) {
                case 'twitter':
                    // Handle mentions, hashtags, links
                    content = content
                        .replace(/@(\w+)/g, '<span style="color: #1DA1F2;">@$1</span>')
                        .replace(/#(\w+)/g, '<span style="color: #1DA1F2;">#$1</span>')
                        .replace(/\b(https?:\/\/[^\s]+)/g, '<span style="color: #1DA1F2;">$1</span>');
                    $preview.find('.smo-preview-content-text').html(content);
                    break;

                case 'linkedin':
                    // Professional formatting
                    content = content.replace(/\n/g, '<br>');
                    $preview.find('.smo-preview-content-text').html(content);
                    break;
            }
        },

        switchPreview: function (e) {
            e.preventDefault();
            const platform = $(this).data('platform');

            // Update tab state
            $('.smo-preview-tab').removeClass('active').attr('aria-selected', 'false').attr('tabindex', '-1');
            $(this).addClass('active').attr('aria-selected', 'true').attr('tabindex', '0');

            // Update content visibility
            $('.smo-preview-content').removeClass('active').attr('aria-hidden', 'true');
            $(`.smo-preview-content[data-platform="${platform}"]`).addClass('active').attr('aria-hidden', 'false');

            SMOAdmin.currentPreview = platform;

            // Announce to screen readers
            SMOAdmin.announceToScreenReader(`Switched to ${platform} preview`);
        },

        schedulePost: function (e) {
            e.preventDefault();

            const content = $('#smo-post-content').val();
            const scheduledTime = $('#smo-schedule-time').val();
            const selectedPlatforms = $('.smo-platform-checkbox input:checked').map(function () {
                return $(this).val();
            }).get();

            if (!content.trim()) {
                SMOAdmin.showNotice('Please enter post content', 'error');
                return;
            }

            if (selectedPlatforms.length === 0) {
                SMOAdmin.showNotice('Please select at least one platform', 'error');
                return;
            }

            if (!scheduledTime) {
                SMOAdmin.showNotice('Please select a schedule time', 'error');
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).html('<span class="smo-loading"></span> Scheduling...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_schedule_post',
                    content: content,
                    platforms: selectedPlatforms,
                    scheduled_time: scheduledTime,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.showNotice('Post scheduled successfully', 'success');
                        $('#smo-post-content, #smo-schedule-time').val('');
                        SMOAdmin.updateCharCounter();
                        SMOAdmin.loadRecentPosts();
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Failed to schedule post', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Schedule Post');
                }
            });
        },

        saveDraft: function (e) {
            e.preventDefault();

            const content = $('#smo-post-content').val();
            const selectedPlatforms = $('.smo-platform-checkbox input:checked').map(function () {
                return $(this).val();
            }).get();

            if (!content.trim()) {
                SMOAdmin.showNotice('Please enter post content', 'error');
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).html('<span class="smo-loading"></span> Saving...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_save_draft',
                    content: content,
                    platforms: selectedPlatforms,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.showNotice('Draft saved successfully', 'success');
                        $('#smo-post-content, #smo-schedule-time').val('');
                        SMOAdmin.updateCharCounter();
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Failed to save draft', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Save Draft');
                }
            });
        },

        // AI Features
        generateAIContent: function (e) {
            e.preventDefault();
            const type = $(this).data('type');
            const $button = $(this);

            $button.prop('disabled', true).html('<span class="smo-loading"></span> Generating...');

            const prompt = $('#smo-ai-prompt').val();
            if (!prompt.trim()) {
                SMOAdmin.showNotice('Please enter a prompt for AI generation', 'error');
                $button.prop('disabled', false).html('Generate');
                return;
            }

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_ai_generate',
                    type: type,
                    prompt: prompt,
                    platform: SMOAdmin.currentPreview,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#smo-post-content').val(response.data.content);
                        SMOAdmin.updateCharCounter();
                        SMOAdmin.showNotice('AI content generated successfully', 'success');
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('AI generation failed', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Generate');
                }
            });
        },

        optimizeHashtags: function (e) {
            e.preventDefault();
            const content = $('#smo-post-content').val();
            const $button = $(this);

            if (!content.trim()) {
                SMOAdmin.showNotice('Please enter post content first', 'error');
                return;
            }

            $button.prop('disabled', true).html('<span class="smo-loading"></span> Optimizing...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_optimize_hashtags',
                    content: content,
                    platform: SMOAdmin.currentPreview,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const optimizedContent = content + '\n\n' + response.data.hashtags;
                        $('#smo-post-content').val(optimizedContent);
                        SMOAdmin.updateCharCounter();
                        SMOAdmin.showNotice('Hashtags optimized successfully', 'success');
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Hashtag optimization failed', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Optimize Hashtags');
                }
            });
        },

        generateAltText: function (e) {
            e.preventDefault();
            const imageUrl = $(this).data('image');
            const $button = $(this);

            if (!imageUrl) {
                SMOAdmin.showNotice('No image selected', 'error');
                return;
            }

            $button.prop('disabled', true).html('<span class="smo-loading"></span> Generating...');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_generate_alt_text',
                    image_url: imageUrl,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Add alt text to content or show in modal
                        SMOAdmin.showAltTextModal(response.data.alt_text);
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Alt text generation failed', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html('Generate Alt Text');
                }
            });
        },

        // Analytics
        loadAnalytics: function () {
            const period = $(this).val();

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_get_analytics',
                    period: period,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.updateAnalytics(response.data);
                    }
                }
            });
        },

        updateAnalytics: function (data) {
            // Update charts and metrics
            this.updateAnalyticsCharts(data.charts);
            this.updateAnalyticsMetrics(data.metrics);
        },

        updateAnalyticsCharts: function (charts) {
            // Initialize or update Chart.js charts
            Object.keys(charts).forEach(function (chartId) {
                const chartData = charts[chartId];
                const $canvas = $(`#${chartId}`);

                if ($canvas.length && window.Chart) {
                    // Create or update chart
                    if (window[chartId + 'Chart']) {
                        window[chartId + 'Chart'].destroy();
                    }

                    window[chartId + 'Chart'] = new Chart($canvas[0], {
                        type: chartData.type,
                        data: chartData.data,
                        options: chartData.options
                    });
                }
            });
        },

        // Utility Functions
        showNotice: function (message, type) {
            const $notice = $(`
                <div class="smo-notice smo-notice-${type}" role="alert" aria-live="assertive">
                    ${message}
                    <button class="smo-notice-close" aria-label="Close notification">&times;</button>
                </div>
            `);

            $('.smo-admin-notices').append($notice);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);

            // Manual close
            $notice.find('.smo-notice-close').click(() => {
                $notice.fadeOut(() => $notice.remove());
            });
        },

        announceToScreenReader: function (message) {
            const announcement = document.createElement('div');
            announcement.setAttribute('aria-live', 'polite');
            announcement.setAttribute('aria-atomic', 'true');
            announcement.style.position = 'absolute';
            announcement.style.left = '-10000px';
            announcement.style.width = '1px';
            announcement.style.height = '1px';
            announcement.style.overflow = 'hidden';

            document.body.appendChild(announcement);
            announcement.textContent = message;

            setTimeout(() => {
                document.body.removeChild(announcement);
            }, 1000);
        },

        showAltTextModal: function (altText) {
            const modal = $(`
                <div class="smo-modal">
                    <div class="smo-modal-content">
                        <div class="smo-modal-header">
                            <h3>Generated Alt Text</h3>
                            <button class="smo-modal-close">&times;</button>
                        </div>
                        <div class="smo-modal-body">
                            <p>${altText}</p>
                            <button class="smo-btn smo-btn-primary" id="smo-copy-alt-text">Copy to Clipboard</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);
            modal.fadeIn();

            // Copy functionality
            modal.find('#smo-copy-alt-text').click(function () {
                navigator.clipboard.writeText(altText).then(() => {
                    SMOAdmin.showNotice('Alt text copied to clipboard', 'success');
                });
            });

            // Close modal
            modal.find('.smo-modal-close, .smo-modal').click(function (e) {
                if (e.target === this) {
                    modal.fadeOut(() => modal.remove());
                }
            });
        },

        initTooltips: function () {
            // Initialize tooltips for buttons and help text
            $('[data-tooltip]').each(function () {
                const text = $(this).data('tooltip');
                $(this).attr('title', text);
            });
        },

        initPreviewSystem: function () {
            // Initialize platform preview tabs
            const firstTab = $('.smo-preview-tab').first();
            if (firstTab.length) {
                firstTab.click();
            }

            // Add ARIA attributes to tabs
            this.initTabAccessibility();
        },

        initTabAccessibility: function () {
            const $tabs = $('.smo-preview-tab');
            const $tabList = $('.smo-preview-tabs');

            if ($tabList.length) {
                $tabList.attr('role', 'tablist');
            }

            $tabs.each(function (index) {
                const $tab = $(this);
                const isActive = $tab.hasClass('active');
                const tabId = 'tab-' + index;
                const panelId = 'panel-' + index;

                $tab.attr({
                    'role': 'tab',
                    'aria-selected': isActive,
                    'aria-controls': panelId,
                    'id': tabId,
                    'tabindex': isActive ? '0' : '-1'
                });

                // Find corresponding panel
                const $panel = $tab.closest('.smo-composer-body').find('.smo-preview-content').eq(index);
                if ($panel.length) {
                    $panel.attr({
                        'role': 'tabpanel',
                        'aria-labelledby': tabId,
                        'id': panelId
                    });
                }
            });
        },

        handleTabKeydown: function (e) {
            const $currentTab = $(e.target);
            const $tabs = $('.smo-preview-tab');
            const currentIndex = $tabs.index($currentTab);

            switch (e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                    $tabs.eq(prevIndex).focus().click();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    const nextIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                    $tabs.eq(nextIndex).focus().click();
                    break;
                case 'Home':
                    e.preventDefault();
                    $tabs.first().focus().click();
                    break;
                case 'End':
                    e.preventDefault();
                    $tabs.last().focus().click();
                    break;
            }
        },

        handleModalKeydown: function (e) {
            if (e.key === 'Escape') {
                const $modal = $(e.target).closest('.smo-modal');
                if ($modal.length) {
                    SMOAdmin.closeModal($modal);
                }
            }
        },

        handleModalBackdropClick: function (e) {
            if (e.target === this) {
                SMOAdmin.closeModal($(this));
            }
        },

        closeModal: function ($modal) {
            $modal.fadeOut(function () {
                $(this).remove();
            });
        },

        handleCategoryKeydown: function (e) {
            const $currentBtn = $(e.target);
            const $buttons = $('.smo-category-btn');
            const currentIndex = $buttons.index($currentBtn);

            switch (e.key) {
                case 'ArrowLeft':
                case 'ArrowUp':
                    e.preventDefault();
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : $buttons.length - 1;
                    $buttons.eq(prevIndex).focus();
                    break;
                case 'ArrowRight':
                case 'ArrowDown':
                    e.preventDefault();
                    const nextIndex = currentIndex < $buttons.length - 1 ? currentIndex + 1 : 0;
                    $buttons.eq(nextIndex).focus();
                    break;
                case 'Home':
                    e.preventDefault();
                    $buttons.first().focus();
                    break;
                case 'End':
                    e.preventDefault();
                    $buttons.last().focus();
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    $currentBtn.click();
                    break;
            }
        },

        // Bulk Actions
        handleBulkAction: function (e) {
            e.preventDefault();

            const action = $(this).data('action');
            const selectedIds = $('.smo-post-checkbox:checked').map(function () {
                return $(this).val();
            }).get();

            if (selectedIds.length === 0) {
                SMOAdmin.showNotice('Please select posts to perform bulk action', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to ${action} ${selectedIds.length} posts?`)) {
                return;
            }

            const $button = $(this);
            $button.prop('disabled', true).html('<span class="smo-loading"></span>');

            $.ajax({
                url: smo_social_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_bulk_action',
                    bulk_action: action,
                    post_ids: selectedIds,
                    nonce: smo_social_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        SMOAdmin.showNotice(`${selectedIds.length} posts ${action}ed successfully`, 'success');
                        SMOAdmin.loadRecentPosts();
                    } else {
                        SMOAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function () {
                    SMOAdmin.showNotice('Bulk action failed', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false).html($button.data('original-text'));
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        SMOAdmin.init();
    });

    // Make SMOAdmin available globally
    window.SMOAdmin = SMOAdmin;

    // Expose reduced motion utility globally
    window.smoPrefersReducedMotion = SMOAdmin.prefersReducedMotion;

})(jQuery);
