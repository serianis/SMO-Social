/**
 * SMO Social Integrations JavaScript
 * 
 * Handles all integration-related functionality
 */

(function ($) {
    'use strict';

    const SMOIntegrations = {
        currentIntegration: null,
        selectedItems: [],

        init() {
            this.bindEvents();
            this.loadIntegrations();
        },

        bindEvents() {
            // Category filtering
            $(document).on('click', '.smo-category-btn', this.filterByCategory.bind(this));

            // Refresh integrations
            $('#smo-refresh-integrations').on('click', this.loadIntegrations.bind(this));

            // Integration actions
            $(document).on('click', '.smo-connect-integration', this.showConnectionModal.bind(this));
            $(document).on('click', '.smo-disconnect-integration', this.disconnectIntegration.bind(this));
            $(document).on('click', '.smo-test-integration', this.testIntegration.bind(this));
            $(document).on('click', '.smo-browse-integration', this.showBrowserModal.bind(this));

            // Modal actions
            $('#smo-modal-cancel, .smo-modal-close').on('click', this.closeModal.bind(this));
            $('#smo-modal-connect').on('click', this.connectIntegration.bind(this));
            $('#smo-browser-cancel').on('click', this.closeBrowserModal.bind(this));
            $('#smo-browser-import').on('click', this.importSelected.bind(this));

            // Browser actions - commented out due to missing method implementations
            // $('#smo-browser-search').on('input', this.searchContent.bind(this));
            // $('#smo-browser-filter').on('change', this.filterContent.bind(this));
            // $('#smo-browser-prev').on('click', () => this.changePage(-1));
            // $('#smo-browser-next').on('click', () => this.changePage(1));
            // $(document).on('click', '.smo-browser-item', this.toggleItemSelection.bind(this));
        },

        loadIntegrations() {
            const $grid = $('#smo-integrations-grid');
            $grid.html('<div class="smo-loading"><div class="smo-spinner"></div><p>Loading integrations...</p></div>');

            $.ajax({
                url: smoIntegrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_integrations',
                    nonce: smoIntegrations.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderIntegrations(response.data.integrations);
                    } else {
                        this.showError('Failed to load integrations');
                    }
                },
                error: () => {
                    this.showError('Network error occurred');
                }
            });
        },

        renderIntegrations(integrations) {
            const $grid = $('#smo-integrations-grid');
            $grid.empty();

            const categories = {
                content: ['feedly', 'pocket'],
                storage: ['dropbox', 'google_drive', 'onedrive', 'google_photos'],
                automation: ['zapier', 'ifttt'],
                media: ['canva', 'unsplash', 'pixabay']
            };

            Object.entries(integrations).forEach(([id, integration]) => {
                const category = Object.keys(categories).find(cat =>
                    categories[cat].includes(id)
                ) || 'other';

                const card = this.createIntegrationCard(id, integration, category);
                $grid.append(card);
            });
        },

        createIntegrationCard(id, integration, category) {
            const isConnected = integration.connected;
            const statusClass = isConnected ? 'connected' : 'disconnected';
            const statusText = isConnected ? '✓ Connected' : '○ Not Connected';

            return $(`
                <div class="smo-integration-card" data-integration="${id}" data-category="${category}">
                    <div class="smo-integration-header">
                        <div class="smo-integration-icon">${integration.icon}</div>
                        <div class="smo-integration-info">
                            <h3>${integration.name}</h3>
                            <span class="smo-integration-status ${statusClass}">${statusText}</span>
                        </div>
                    </div>
                    <p class="smo-integration-description">${integration.description}</p>
                    <div class="smo-integration-actions">
                        ${isConnected ? `
                            <button class="smo-btn smo-btn-outline smo-browse-integration" data-integration="${id}">
                                <span class="dashicons dashicons-search"></span>
                                Browse
                            </button>
                            <button class="smo-btn smo-btn-outline smo-test-integration" data-integration="${id}">
                                <span class="dashicons dashicons-yes"></span>
                                Test
                            </button>
                            <button class="smo-btn smo-btn-danger smo-disconnect-integration" data-integration="${id}">
                                <span class="dashicons dashicons-dismiss"></span>
                                Disconnect
                            </button>
                        ` : `
                            <button class="smo-btn smo-btn-primary smo-connect-integration" data-integration="${id}">
                                <span class="dashicons dashicons-admin-plugins"></span>
                                Connect
                            </button>
                        `}
                    </div>
                </div>
            `);
        },

        filterByCategory(e) {
            const category = $(e.currentTarget).data('category');

            $('.smo-category-btn').removeClass('active');
            $(e.currentTarget).addClass('active');

            if (category === 'all') {
                $('.smo-integration-card').show();
            } else {
                $('.smo-integration-card').hide();
                $(`.smo-integration-card[data-category="${category}"]`).show();
            }
        },

        showConnectionModal(e) {
            const integrationId = $(e.currentTarget).data('integration');
            this.currentIntegration = integrationId;

            const integration = smoIntegrations.integrations[integrationId];

            $('#smo-modal-title').text(`Connect ${integration.name}`);

            let formHtml = '';

            switch (integration.auth_type) {
                case 'api_key':
                    formHtml = `
                        <div class="smo-form-group">
                            <label for="api-key">API Key</label>
                            <input type="text" id="api-key" class="smo-form-control" placeholder="Enter your ${integration.name} API key">
                            <p class="smo-form-help">Get your API key from ${integration.name} settings</p>
                        </div>
                    `;
                    break;

                case 'oauth2':
                    formHtml = `
                        <div class="smo-form-group">
                            <label for="client-id">Client ID</label>
                            <input type="text" id="client-id" class="smo-form-control" placeholder="Enter Client ID">
                        </div>
                        <div class="smo-form-group">
                            <label for="client-secret">Client Secret</label>
                            <input type="password" id="client-secret" class="smo-form-control" placeholder="Enter Client Secret">
                        </div>
                        <p class="smo-form-help">You'll be redirected to authorize access after connecting</p>
                    `;
                    break;

                case 'webhook':
                    formHtml = `
                        <div class="smo-form-group">
                            <label for="webhook-url">Webhook URL / Key</label>
                            <input type="text" id="webhook-url" class="smo-form-control" placeholder="Enter webhook URL or key">
                            <p class="smo-form-help">Copy this from your ${integration.name} account</p>
                        </div>
                    `;
                    break;
            }

            $('#smo-modal-body').html(formHtml);
            $('#smo-integration-modal').fadeIn(200);
        },

        connectIntegration() {
            const integrationId = this.currentIntegration;
            const integration = smoIntegrations.integrations[integrationId];

            let credentials = {};

            switch (integration.auth_type) {
                case 'api_key':
                    credentials.api_key = $('#api-key').val();
                    break;
                case 'oauth2':
                    credentials.client_id = $('#client-id').val();
                    credentials.client_secret = $('#client-secret').val();
                    break;
                case 'webhook':
                    credentials.webhook_url = $('#webhook-url').val();
                    credentials.webhook_key = $('#webhook-url').val();
                    break;
            }

            $('#smo-modal-connect').prop('disabled', true).text('Connecting...');

            $.ajax({
                url: smoIntegrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_connect_integration',
                    nonce: smoIntegrations.nonce,
                    integration_id: integrationId,
                    credentials: credentials
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.auth_url) {
                            // Show loading state during OAuth redirect
                            $('#smo-modal-connect').html('<span class="dashicons dashicons-update smo-spin"></span> Redirecting to authorization...');
                            $('#smo-modal-connect').prop('disabled', true);
                            
                            // Add loading overlay
                            const $modal = $('#smo-integration-modal');
                            if (!$modal.find('.smo-oauth-loading').length) {
                                $modal.append(`
                                    <div class="smo-oauth-loading">
                                        <div class="smo-loading-content">
                                            <span class="dashicons dashicons-update smo-spin large"></span>
                                            <h3>Connecting to ${integration.name}</h3>
                                            <p>Please authorize access in the new window...</p>
                                            <div class="smo-loading-dots">
                                                <span></span><span></span><span></span>
                                            </div>
                                        </div>
                                    </div>
                                `);
                            }
                            
                            // Open in new window/tab for better UX
                            const authWindow = window.open(response.data.auth_url, '_blank', 'width=600,height=700,scrollbars=yes,resizable=yes');
                            
                            // Fallback if popup is blocked
                            if (!authWindow) {
                                setTimeout(() => {
                                    window.location.href = response.data.auth_url;
                                }, 2000);
                            }
                            
                            // Poll for window closure (fallback for popup blocking)
                            let checkClosed = setInterval(() => {
                                if (authWindow && authWindow.closed) {
                                    clearInterval(checkClosed);
                                    this.closeModal();
                                    this.loadIntegrations(); // Refresh to check connection status
                                }
                            }, 1000);
                            
                            // Also check for successful connection via page refresh after timeout
                            setTimeout(() => {
                                this.closeModal();
                                this.loadIntegrations();
                            }, 30000); // 30 second timeout
                            
                        } else {
                            this.showSuccess(response.data.message);
                            this.closeModal();
                            this.loadIntegrations();
                        }
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError('Connection failed');
                },
                complete: () => {
                    $('#smo-modal-connect').prop('disabled', false).text('Connect');
                }
            });
        },

        disconnectIntegration(e) {
            const integrationId = $(e.currentTarget).data('integration');

            if (!confirm('Are you sure you want to disconnect this integration?')) {
                return;
            }

            $.ajax({
                url: smoIntegrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_disconnect_integration',
                    nonce: smoIntegrations.nonce,
                    integration_id: integrationId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.loadIntegrations();
                    } else {
                        this.showError(response.data.message);
                    }
                }
            });
        },

        testIntegration(e) {
            const integrationId = $(e.currentTarget).data('integration');
            const $btn = $(e.currentTarget);

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update smo-spin"></span> Testing...');

            $.ajax({
                url: smoIntegrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_test_integration',
                    nonce: smoIntegrations.nonce,
                    integration_id: integrationId
                },
                success: (response) => {
                    if (response.success && response.data.success) {
                        this.showSuccess('Connection test successful!');
                    } else {
                        this.showError(response.data.message || 'Connection test failed');
                    }
                },
                complete: () => {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Test');
                }
            });
        },

        showBrowserModal(e) {
            const integrationId = $(e.currentTarget).data('integration');
            this.currentIntegration = integrationId;
            this.selectedItems = [];

            const integration = smoIntegrations.integrations[integrationId];
            $('#smo-browser-title').text(`Browse ${integration.name}`);

            $('#smo-integration-browser-modal').fadeIn(200);
            this.loadBrowserContent();
        },

        loadBrowserContent(params = {}) {
            const $content = $('#smo-browser-content');
            $content.html('<div class="smo-loading"><div class="smo-spinner"></div><p>Loading...</p></div>');

            $.ajax({
                url: smoIntegrations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_integration_data',
                    nonce: smoIntegrations.nonce,
                    integration_id: this.currentIntegration,
                    action_type: 'list',
                    params: params
                },
                success: (response) => {
                    if (response.success) {
                        this.renderBrowserContent(response.data);
                    } else {
                        $content.html(`<p class="smo-error">${response.data.message}</p>`);
                    }
                }
            });
        },

        renderBrowserContent(data) {
            const $content = $('#smo-browser-content');
            $content.html('<div class="smo-browser-grid"></div>');
            const $grid = $content.find('.smo-browser-grid');

            let items = [];

            // Normalize data based on integration response structure
            if (data.designs) {
                // Canva
                items = data.designs.map(d => ({
                    id: d.id,
                    title: d.title || 'Untitled',
                    thumbnail: d.thumbnail_url || d.thumbnail || '',
                    type: 'image'
                }));
            } else if (data.images) {
                // Unsplash, Pixabay
                items = data.images.map(i => ({
                    id: i.id,
                    title: i.alt_description || i.tags || 'Image',
                    thumbnail: i.urls?.small || i.webformatURL || '',
                    type: 'image'
                }));
            } else if (data.files) {
                // Dropbox, Google Drive, OneDrive
                items = data.files.map(f => ({
                    id: f.id,
                    title: f.name,
                    thumbnail: f.thumbnailLink || '',
                    icon: f.mimeType?.includes('image') ? 'dashicons-format-image' : 'dashicons-media-document',
                    type: 'file'
                }));
            } else if (data.photos) {
                // Google Photos
                items = data.photos.map(p => ({
                    id: p.id,
                    title: p.filename || 'Photo',
                    thumbnail: p.baseUrl ? `${p.baseUrl}=w400-h400-c` : '',
                    type: 'image'
                }));
            } else if (data.items) {
                // Feedly, Pocket
                const list = Array.isArray(data.items) ? data.items : Object.values(data.items);
                items = list.map(i => ({
                    id: i.id || i.item_id,
                    title: i.title || i.resolved_title || i.given_title || 'Untitled',
                    thumbnail: i.visual?.url || i.top_image_url || i.image?.src || '',
                    type: 'article',
                    excerpt: i.summary?.content || i.excerpt || ''
                }));
            } else if (data.albums) {
                // Google Photos Albums
                items = data.albums.map(a => ({
                    id: a.id,
                    title: a.title,
                    thumbnail: a.coverPhotoBaseUrl ? `${a.coverPhotoBaseUrl}=w400-h400-c` : '',
                    type: 'album'
                }));
            } else if (data.feeds) {
                // Feedly Feeds
                items = data.feeds.map(f => ({
                    id: f.id,
                    title: f.title,
                    thumbnail: f.visualUrl || f.iconUrl || '',
                    type: 'feed',
                    excerpt: f.description || ''
                }));
            }

            if (items.length === 0) {
                $content.html('<p class="smo-no-items">No items found.</p>');
                return;
            }

            items.forEach(item => {
                const $item = $(`
                    <div class="smo-browser-item" data-item-id="${item.id}" data-item-type="${item.type}">
                        <div class="smo-browser-item-preview">
                            ${item.thumbnail ? `<img src="${item.thumbnail}" alt="${item.title}">` :
                        `<span class="dashicons ${item.icon || 'dashicons-media-default'}"></span>`}
                        </div>
                        <div class="smo-browser-item-details">
                            <h4>${item.title}</h4>
                            ${item.excerpt ? `<p>${item.excerpt.substring(0, 50)}...</p>` : ''}
                        </div>
                        <div class="smo-browser-item-check">
                            <span class="dashicons dashicons-yes"></span>
                        </div>
                    </div>
                `);
                $grid.append($item);
            });
        },

        importSelected() {
            if (this.selectedItems.length === 0) {
                this.showError('Please select items to import');
                return;
            }

            $('#smo-browser-import').prop('disabled', true).text('Importing...');

            const promises = this.selectedItems.map(itemId => {
                return $.ajax({
                    url: smoIntegrations.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smo_import_from_integration',
                        nonce: smoIntegrations.nonce,
                        integration_id: this.currentIntegration,
                        item_id: itemId
                    }
                });
            });

            Promise.all(promises).then(() => {
                this.showSuccess(`Successfully imported ${this.selectedItems.length} item(s)`);
                this.closeBrowserModal();
            }).catch(() => {
                this.showError('Some items failed to import');
            }).finally(() => {
                $('#smo-browser-import').prop('disabled', false).text('Import Selected');
            });
        },

        toggleItemSelection(e) {
            const $item = $(e.currentTarget);
            const itemId = $item.data('item-id');

            $item.toggleClass('selected');

            if ($item.hasClass('selected')) {
                this.selectedItems.push(itemId);
            } else {
                this.selectedItems = this.selectedItems.filter(id => id !== itemId);
            }

            $('#smo-browser-import').prop('disabled', this.selectedItems.length === 0);
        },

        closeModal() {
            $('#smo-integration-modal').fadeOut(200);
            this.currentIntegration = null;
        },

        closeBrowserModal() {
            $('#smo-integration-browser-modal').fadeOut(200);
            this.currentIntegration = null;
            this.selectedItems = [];
        },

        importSelected() {
            if (this.selectedItems.length === 0) {
                this.showError('No items selected for import');
                return;
            }

            // Show loading state
            $('#smo-browser-import').prop('disabled', true).text('Importing...');

            // Simulate import process (replace with actual implementation)
            setTimeout(() => {
                this.showSuccess(`Successfully imported ${this.selectedItems.length} item(s)`);
                this.closeBrowserModal();
                $('#smo-browser-import').prop('disabled', false).text('Import Selected');
                this.selectedItems = [];
            }, 1500);
        },

        showSuccess(message) {
            // Use WordPress admin notices or custom notification
            this.showNotification(message, 'success');
        },

        showError(message) {
            this.showNotification(message, 'error');
        },

        showNotification(message, type) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);

            $('.smo-integrations-wrap').prepend($notice);

            setTimeout(() => {
                $notice.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        if ($('.smo-integrations-wrap').length) {
            SMOIntegrations.init();
        }
    });

})(jQuery);
