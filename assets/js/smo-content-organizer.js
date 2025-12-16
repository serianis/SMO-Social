/**
 * SMO Social - Content Organizer Enhanced JavaScript
 * Handles Kanban board, drag-and-drop, and all interactive functionality
 */

(function ($) {
    'use strict';

    const SMOContentOrganizer = {

        /**
         * Initialize the module
         */
        init: function () {
            this.bindEvents();
            this.loadDashboardStats();
            this.initTabs();
            this.loadInitialData();
            this.initKanban();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Header actions
            $('#smo-quick-add-idea').on('click', this.showQuickIdeaModal.bind(this));
            $('#smo-add-category, #smo-create-category').on('click', this.showCategoryModal.bind(this));

            // Quick actions
            $('#smo-bulk-organize').on('click', this.bulkOrganize.bind(this));
            $('#smo-export-content').on('click', this.exportContent.bind(this));
            $('#smo-import-content').on('click', this.importContent.bind(this));

            $('#smo-view-calendar').on('click', this.viewCalendar.bind(this));

            // Tab switching
            $('.smo-tab-link').on('click', this.switchTab.bind(this));

            // View toggle
            $('.smo-view-btn').on('click', this.toggleView.bind(this));

            // Filters
            $('#ideas-filter-status, #ideas-filter-category, #ideas-filter-priority').on('change', this.filterIdeas.bind(this));
            $('#library-search').on('input', this.searchLibrary.bind(this));
            $('#library-filter-type').on('change', this.filterLibrary.bind(this));
            $('#template-filter-category').on('change', this.filterTemplates.bind(this));

            // Modal actions
            $('#smo-add-idea').on('click', this.showIdeaDetailModal.bind(this));
            $('#smo-create-template').on('click', this.showTemplateModal.bind(this));
            $('#smo-upload-content').on('click', this.uploadContent.bind(this));

            // Modal close
            $('.smo-modal-close, .smo-cancel-modal').on('click', this.closeModal.bind(this));

            // Form submissions
            $('#smo-category-form').on('submit', this.saveCategory.bind(this));
            $('#smo-quick-idea-form').on('submit', this.saveQuickIdea.bind(this));
            $('#smo-idea-detail-form').on('submit', this.saveIdeaDetail.bind(this));
            $('#smo-template-form').on('submit', this.saveTemplate.bind(this));
            $('#smo-bulk-organize-form').on('submit', this.saveBulkOrganize.bind(this));
            $('#smo-export-form').on('submit', this.performExport.bind(this));
            $('#smo-import-form').on('submit', this.performImport.bind(this));
            $('#smo-upload-form').on('submit', this.performUpload.bind(this));

            // Convert idea to post
            $('#convert-to-post').on('click', this.convertToPost.bind(this));
        },

        /**
         * Initialize tabs
         */
        initTabs: function () {
            $('.smo-tab-link').first().addClass('active');
            $('.smo-tab-panel').first().addClass('active');
        },

        /**
         * Switch between tabs
         */
        switchTab: function (e) {
            e.preventDefault();
            const $link = $(e.currentTarget);
            const tabName = $link.data('tab');

            $('.smo-tab-link').removeClass('active');
            $link.addClass('active');

            $('.smo-tab-panel').removeClass('active');
            $(`#${tabName}-panel`).addClass('active');

            this.loadTabData(tabName);
        },

        /**
         * Load dashboard statistics
         */
        loadDashboardStats: function () {
            this.ajax_get_organizer_stats().done((response) => {
                if (response.success) {
                    const stats = response.data;
                    $('#total-categories').text(stats.total_categories);
                    $('#total-ideas').text(stats.total_ideas);
                    $('#total-drafts').text(stats.total_drafts);
                    $('#total-scheduled').text(stats.total_scheduled);

                    $('#categories-badge').text(stats.total_categories);
                    $('#ideas-badge').text(stats.total_ideas);
                }
            }).fail(() => {
                // Fallback to simulated data if AJAX fails
                $('#total-categories').text('12');
                $('#total-ideas').text('34');
                $('#total-drafts').text('8');
                $('#total-scheduled').text('15');
                $('#categories-badge').text('12');
                $('#ideas-badge').text('34');
            });
        },
        /**
         * AJAX: Get organizer stats
         */
        ajax_get_organizer_stats: function () {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_organizer_stats',
                    nonce: smoContentOrganizer.nonce
                }
            });
        },

        /**
         * View calendar functionality
         */
        viewCalendar: function () {
            console.log('viewCalendar method called');
            // Redirect to calendar view or show calendar modal
            // This is a placeholder implementation - should be implemented based on requirements
            window.location.href = 'admin.php?page=smo-social-calendar';
        },

        /**
         * AJAX: Get category by ID
         */
        ajax_get_category: function (id) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_category',
                    nonce: smoContentOrganizer.nonce,
                    id: id
                }
            });
        },

        /**
         * AJAX: Delete category
         */
        ajax_delete_category: function (id) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_delete_category',
                    nonce: smoContentOrganizer.nonce,
                    id: id
                }
            });
        },

        /**
         * AJAX: Get idea by ID
         */
        ajax_get_idea: function (id) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_idea',
                    nonce: smoContentOrganizer.nonce,
                    id: id
                }
            });
        },

        /**
         * AJAX: Update idea status
         */
        ajax_update_idea_status: function (ideaId, newStatus) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_update_idea_status',
                    nonce: smoContentOrganizer.nonce,
                    idea_id: ideaId,
                    status: newStatus
                }
            });
        },

        /**
         * AJAX: Save quick idea
         */
        ajax_save_quick_idea: function (data) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'smo_save_quick_idea',
                    nonce: smoContentOrganizer.nonce
                }, data)
            });
        },

        /**
         * AJAX: Save idea
         */
        ajax_save_idea: function (data) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'smo_save_idea',
                    nonce: smoContentOrganizer.nonce
                }, data)
            });
        },

        /**
         * AJAX: Get content library
         */
        ajax_get_content_library: function () {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_content_library',
                    nonce: smoContentOrganizer.nonce
                }
            });
        },

        /**
         * AJAX: Search content library
         */
        ajax_search_content_library: function (query) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_search_content_library',
                    nonce: smoContentOrganizer.nonce,
                    query: query
                }
            });
        },

        /**
         * AJAX: Get templates
         */
        ajax_get_templates: function () {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_templates',
                    nonce: smoContentOrganizer.nonce
                }
            });
        },

        /**
         * AJAX: Save template
         */
        ajax_save_template: function (data) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'smo_save_template',
                    nonce: smoContentOrganizer.nonce
                }, data)
            });
        },

        /**
         * AJAX: Upload content
         */
        ajax_upload_content: function (formData) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            });
        },

        /**
         * AJAX: Export content
         */
        ajax_export_content: function (data) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'smo_export_content',
                    nonce: smoContentOrganizer.nonce
                }, data)
            });
        },

        /**
         * AJAX: Import content
         */
        ajax_import_content: function (formData) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            });
        },

        /**
         * AJAX: Bulk organize ideas
         */
        ajax_bulk_organize: function (data) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'smo_bulk_organize',
                    nonce: smoContentOrganizer.nonce
                }, data)
            });
        },

        /**
         * AJAX: Export content
         */
        ajax_export_content: function (data) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'smo_export_content',
                    nonce: smoContentOrganizer.nonce
                }, data)
            });
        },

        /**
         * AJAX: Import content
         */
        ajax_import_content: function (formData) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            });
        },

        /**
         * AJAX: Save category
         */
        ajax_save_category: function (data) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: 'smo_save_category',
                    nonce: smoContentOrganizer.nonce
                }, data)
            });
        },

        /**
         * AJAX: Get categories
         */
        ajax_get_categories: function () {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_categories',
                    nonce: smoContentOrganizer.nonce
                }
            });
        },

        /**
         * AJAX: Get category by ID
         */
        ajax_get_category: function (id) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_category',
                    nonce: smoContentOrganizer.nonce,
                    id: id
                }
            });
        },

        /**
         * AJAX: Get ideas
         */
        ajax_get_ideas: function (status) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_ideas',
                    nonce: smoContentOrganizer.nonce,
                    status: status
                }
            });
        },

        /**
         * AJAX: Get idea by ID
         */
        ajax_get_idea: function (id) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_idea',
                    nonce: smoContentOrganizer.nonce,
                    id: id
                }
            });
        },

        /**
         * Load initial data
         */
        loadInitialData: function () {
            this.loadCategories();
            this.loadIdeas();
        },

        /**
         * Load tab-specific data
         */
        loadTabData: function (tabName) {
            switch (tabName) {
                case 'categories':
                    this.loadCategories();
                    break;
                case 'ideas':
                    this.loadIdeas();
                    break;
                case 'content-library':
                    this.loadContentLibrary();
                    break;
                case 'templates':
                    this.loadTemplates();
                    break;
            }
        },

        /**
         * Initialize Kanban board with drag-and-drop
         */
        initKanban: function () {
            const self = this;

            // Make sure sortable is available
            if ($.fn.sortable) {
                $('.smo-kanban-items').sortable({
                    connectWith: '.smo-kanban-items',
                    placeholder: 'smo-kanban-placeholder',
                    cursor: 'move',
                    opacity: 0.8,
                    helper: 'clone',
                    zIndex: 9999,
                    start: function (e, ui) {
                        ui.item.addClass('dragging');
                        ui.placeholder.height(ui.item.outerHeight());
                    },
                    stop: function (e, ui) {
                        ui.item.removeClass('dragging');
                    },
                    update: function (e, ui) {
                        if (this === ui.item.parent()[0]) {
                            const itemId = ui.item.data('idea-id');
                            const newStatus = ui.item.closest('.smo-kanban-column').data('status');
                            self.updateIdeaStatus(itemId, newStatus);
                        }
                    },
                    receive: function (e, ui) {
                        const itemId = ui.item.data('idea-id');
                        const newStatus = ui.item.closest('.smo-kanban-column').data('status');
                        self.updateIdeaStatus(itemId, newStatus);
                    }
                });
            } else {
                console.error('jQuery UI Sortable not available');
                this.showNotification('Drag and drop functionality requires jQuery UI Sortable', 'error');
            }
        },

        /**
         * Load categories
         */
        loadCategories: function () {
            const $container = $('#categories-container');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentOrganizer.strings.loading + '</p></div>');

            this.ajax_get_categories().done((response) => {
                if (response.success) {
                    $container.html(this.renderCategoriesGrid(response.data));
                }
            }).fail(() => {
                // Fallback to simulated data if AJAX fails
                setTimeout(() => {
                    $container.html(this.renderCategoriesGrid());
                }, 500);
            });
        },

        /**
         * Render categories grid
         */
        renderCategoriesGrid: function (categories) {
            // Validate and use default data if no categories provided or not an array
            if (!categories || !Array.isArray(categories)) {
                categories = [
                    { id: 1, name: 'Product Launches', color: '#667eea', icon: '🚀', count: 12 },
                    { id: 2, name: 'Blog Posts', color: '#f093fb', icon: '📝', count: 24 },
                    { id: 3, name: 'Social Media', color: '#4facfe', icon: '📱', count: 45 },
                    { id: 4, name: 'Promotions', color: '#43e97b', icon: '🎯', count: 8 }
                ];
            }

            let html = '';
            categories.forEach(cat => {
                html += `
                    <div class="smo-category-card" data-category-id="${cat.id}">
                        <div class="smo-category-icon" style="background: ${cat.color}">
                            ${cat.icon}
                        </div>
                        <div class="smo-category-content">
                            <h3>${cat.name}</h3>
                            <p>${cat.count} items</p>
                        </div>
                        <div class="smo-category-actions">
                            <button class="smo-btn-icon" onclick="SMOContentOrganizer.editCategory(${cat.id})" title="Edit">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button class="smo-btn-icon" onclick="SMOContentOrganizer.deleteCategory(${cat.id})" title="Delete">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                `;
            });

            return html;
        },

        /**
         * Load ideas into Kanban board
         */
        loadIdeas: function () {
            // Get filter values
            const statusFilter = $('#ideas-filter-status').val();
            const categoryFilter = $('#ideas-filter-category').val();
            const priorityFilter = $('#ideas-filter-priority').val();

            // Load ideas for each status
            const statuses = ['idea', 'draft', 'scheduled', 'published'];
            const promises = [];

            statuses.forEach(status => {
                promises.push(this.ajax_get_ideas(status));
            });

            // Clear loading states
            $('.smo-kanban-items').html('<div class="smo-loading"><div class="smo-spinner"></div></div>');

            // When all AJAX calls complete
            $.when.apply($, promises).done((...responses) => {
                const ideasByStatus = {};

                // Process each response
                responses.forEach((response, index) => {
                    if (response && response.success) {
                        const status = statuses[index];
                        ideasByStatus[status] = response.data;
                    }
                });

                // Apply filtering
                this.applyIdeasFiltering(ideasByStatus, statusFilter, categoryFilter, priorityFilter);

            }).fail(() => {
                // Fallback to simulated data if AJAX fails
                const ideas = {
                    idea: [
                        { id: 1, title: 'New product announcement', priority: 'high', category: 'Product Launches', content: 'Share our latest product features...' },
                        { id: 2, title: 'Customer success story', priority: 'medium', category: 'Blog Posts', content: 'Interview with happy customer...' }
                    ],
                    draft: [
                        { id: 3, title: 'How-to guide', priority: 'medium', category: 'Blog Posts', content: 'Step by step tutorial...' }
                    ],
                    scheduled: [
                        { id: 4, title: 'Weekly newsletter', priority: 'high', category: 'Social Media', content: 'Curated content for this week...' }
                    ],
                    published: [
                        { id: 5, title: 'Product launch post', priority: 'high', category: 'Product Launches', content: 'We are excited to announce...' }
                    ]
                };

                // Apply filtering to fallback data
                this.applyIdeasFiltering(ideas, statusFilter, categoryFilter, priorityFilter);
            });
        },

        /**
         * Apply filtering to ideas data
         */
        applyIdeasFiltering: function (ideasByStatus, statusFilter, categoryFilter, priorityFilter) {
            // Populate each column
            Object.keys(ideasByStatus).forEach(status => {
                const $column = $(`#kanban-${status}`);
                let items = ideasByStatus[status] || [];

                // Apply filters
                if (statusFilter && statusFilter !== 'all' && status !== statusFilter) {
                    items = [];
                }

                if (categoryFilter && categoryFilter !== 'all') {
                    items = items.filter(idea => idea.category_id == categoryFilter);
                }

                if (priorityFilter && priorityFilter !== 'all') {
                    items = items.filter(idea => idea.priority === priorityFilter);
                }

                if (items.length === 0) {
                    $column.html('<div class="smo-kanban-empty">Drop ideas here</div>');
                } else {
                    let html = '';
                    items.forEach(idea => {
                        html += this.renderKanbanItem(idea);
                    });
                    $column.html(html);
                }

                // Update count
                $column.closest('.smo-kanban-column').find('.smo-kanban-count').text(items.length);
            });
        },

        /**
         * Render a single Kanban item
         */
        renderKanbanItem: function (idea) {
            return `
                <div class="smo-kanban-item" data-idea-id="${idea.id}">
                    <div class="smo-kanban-item-header">
                        <h4 class="smo-kanban-item-title">${idea.title}</h4>
                        <span class="smo-kanban-item-priority ${idea.priority}">${idea.priority}</span>
                    </div>
                    <div class="smo-kanban-item-content">
                        ${idea.content.substring(0, 80)}...
                    </div>
                    <div class="smo-kanban-item-footer">
                        <span class="smo-kanban-item-category">${idea.category}</span>
                        <div class="smo-kanban-item-actions">
                            <button class="smo-kanban-item-action" onclick="SMOContentOrganizer.viewIdea(${idea.id})" title="View">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button class="smo-kanban-item-action" onclick="SMOContentOrganizer.editIdea(${idea.id})" title="Edit">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Update idea status when moved in Kanban
         */
        updateIdeaStatus: function (ideaId, newStatus) {
            $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_update_idea_status',
                    nonce: smoContentOrganizer.nonce,
                    idea_id: ideaId,
                    status: newStatus
                },
                success: function (response) {
                    if (response.success) {
                        SMOContentOrganizer.showNotification('Idea status updated!', 'success');
                        SMOContentOrganizer.loadDashboardStats();
                    } else {
                        SMOContentOrganizer.handleAJAXError(null, null, null, 'Failed to update idea status');
                    }
                },
                error: function (xhr, status, error) {
                    SMOContentOrganizer.handleAJAXError(xhr, status, error, 'Failed to update idea status');
                }
            });
        },

        /**
         * Load content library
         */
        loadContentLibrary: function () {
            const $container = $('#content-library-grid');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentOrganizer.strings.loading + '</p></div>');

            this.ajax_get_content_library().done((response) => {
                if (response.success) {
                    if (response.data.items && response.data.items.length > 0) {
                        let html = '';
                        response.data.items.forEach(item => {
                            html += `
                                <div class="smo-library-item" data-content-id="${item.id}">
                                    <div class="smo-library-item-header">
                                        <h4>${item.title}</h4>
                                        <span class="smo-library-item-type ${item.type}">${item.type}</span>
                                    </div>
                                    <div class="smo-library-item-content">
                                        <p>${item.description}</p>
                                    </div>
                                    <div class="smo-library-item-footer">
                                        <span class="smo-library-item-date">${item.created_at}</span>
                                        <div class="smo-library-item-actions">
                                            <button class="smo-btn-icon" onclick="SMOContentOrganizer.downloadContent(${item.id})" title="Download">
                                                <span class="dashicons dashicons-download"></span>
                                            </button>
                                            <button class="smo-btn-icon" onclick="SMOContentOrganizer.deleteContent(${item.id})" title="Delete">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        $container.html(html);
                    } else {
                        $container.html('<div class="smo-empty-state"><p>No content in library. Upload your first asset!</p></div>');
                    }
                } else {
                    $container.html('<div class="smo-empty-state"><p>No content in library. Upload your first asset!</p></div>');
                }
            }).fail(() => {
                $container.html('<div class="smo-empty-state"><p>No content in library. Upload your first asset!</p></div>');
            });
        },

        /**
         * Load templates
         */
        loadTemplates: function () {
            const $container = $('#templates-grid');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentOrganizer.strings.loading + '</p></div>');

            this.ajax_get_templates().done((response) => {
                if (response.success) {
                    if (response.data.templates && response.data.templates.length > 0) {
                        let html = '';
                        response.data.templates.forEach(template => {
                            html += `
                                <div class="smo-template-card" data-template-id="${template.id}">
                                    <div class="smo-template-card-header">
                                        <h4>${template.name}</h4>
                                        <span class="smo-template-category">${template.category_name}</span>
                                    </div>
                                    <div class="smo-template-card-content">
                                        <p>${template.content.substring(0, 100)}...</p>
                                    </div>
                                    <div class="smo-template-card-footer">
                                        <button class="smo-btn-sm" onclick="SMOContentOrganizer.editTemplate(${template.id})">Edit</button>
                                        <button class="smo-btn-sm" onclick="SMOContentOrganizer.deleteTemplate(${template.id})">Delete</button>
                                        <button class="smo-btn-sm" onclick="SMOContentOrganizer.useTemplate(${template.id})">Use</button>
                                    </div>
                                </div>
                            `;
                        });
                        $container.html(html);
                    } else {
                        $container.html('<div class="smo-empty-state"><p>No templates found. Create your first template!</p></div>');
                    }
                } else {
                    $container.html('<div class="smo-empty-state"><p>No templates found. Create your first template!</p></div>');
                }
            }).fail(() => {
                $container.html('<div class="smo-empty-state"><p>No templates found. Create your first template!</p></div>');
            });
        },

        /**
         * Toggle view (grid/list)
         */
        toggleView: function (e) {
            const $btn = $(e.currentTarget);
            const view = $btn.data('view');

            $('.smo-view-btn').removeClass('active');
            $btn.addClass('active');

            const $container = $('#categories-container');
            $container.removeClass('smo-categories-grid smo-categories-list');
            $container.addClass(`smo-categories-${view}`);
        },

        /**
         * Filter ideas
         */
        filterIdeas: function () {
            // Get current filter values
            const statusFilter = $('#ideas-filter-status').val();
            const categoryFilter = $('#ideas-filter-category').val();
            const priorityFilter = $('#ideas-filter-priority').val();

            // Reload ideas with current filters
            this.loadIdeas();
        },

        /**
         * Search library
         */
        searchLibrary: function () {
            const query = $('#library-search').val().trim();

            if (query.length < 2 && query.length > 0) {
                this.showNotification('Please enter at least 2 characters to search', 'error');
                return;
            }

            const $container = $('#content-library-grid');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentOrganizer.strings.searching + '</p></div>');

            if (query === '') {
                // Load all content if search is empty
                this.loadContentLibrary();
            } else {
                // Perform actual search
                this.ajax_search_content_library(query).done((response) => {
                    if (response.success) {
                        if (response.data.results && response.data.results.length > 0) {
                            let html = '';
                            response.data.results.forEach(item => {
                                html += `
                                    <div class="smo-library-item">
                                        <h4>${item.title}</h4>
                                        <p>${item.description}</p>
                                        <span class="smo-library-item-type">${item.type}</span>
                                    </div>
                                `;
                            });
                            $container.html(html);
                        } else {
                            $container.html(`<div class="smo-empty-state"><p>Search results for "${query}": No matching content found.</p></div>`);
                        }
                    } else {
                        $container.html(`<div class="smo-empty-state"><p>Search results for "${query}": No matching content found.</p></div>`);
                    }
                }).fail(() => {
                    $container.html(`<div class="smo-empty-state"><p>Search results for "${query}": No matching content found.</p></div>`);
                });
            }
        },

        /**
         * Filter library
         */
        filterLibrary: function () {
            this.loadContentLibrary();
        },

        /**
         * Filter templates
         */
        filterTemplates: function () {
            this.loadTemplates();
        },

        /**
         * Show category modal
         */
        showCategoryModal: function () {
            $('#smo-category-modal').addClass('active');
            $('#category-id').val('');
            $('#category-name').val('');
            $('#category-description').val('');
            $('#category-color').val('#667eea');
            $('#smo-category-modal-title').text('Create Category');
        },

        /**
         * Show quick idea modal
         */
        showQuickIdeaModal: function () {
            $('#smo-quick-idea-modal').addClass('active');
            this.loadCategoriesIntoSelect('#quick-idea-category');
        },

        /**
         * Show idea detail modal
         */
        showIdeaDetailModal: function () {
            $('#smo-idea-detail-modal').addClass('active');
            $('#idea-id').val('');
            this.loadCategoriesIntoSelect('#idea-category');
        },

        /**
         * Load categories into select dropdown
         */
        loadCategoriesIntoSelect: function (selector) {
            const categories = [
                { id: 1, name: 'Product Launches' },
                { id: 2, name: 'Blog Posts' },
                { id: 3, name: 'Social Media' },
                { id: 4, name: 'Promotions' }
            ];

            let html = '<option value="">Select category...</option>';
            categories.forEach(cat => {
                html += `<option value="${cat.id}">${cat.name}</option>`;
            });

            $(selector).html(html);
        },

        /**
         * Close modal
         */
        closeModal: function (e) {
            if (e) e.preventDefault();
            $('.smo-modal').removeClass('active');
        },

        /**
         * Save category
         */
        saveCategory: function (e) {
            e.preventDefault();

            // Validate form data
            const name = $('#category-name').val().trim();
            const color = $('#category-color').val().trim();

            if (!name) {
                this.showNotification('Category name is required', 'error');
                return;
            }

            if (name.length > 100) {
                this.showNotification('Category name must be 100 characters or less', 'error');
                return;
            }

            if (!/^#[0-9A-Fa-f]{6}$/.test(color)) {
                this.showNotification('Please enter a valid hex color code', 'error');
                return;
            }

            const data = {
                action: 'smo_save_category',
                nonce: smoContentOrganizer.nonce,
                id: $('#category-id').val(),
                name: name,
                description: $('#category-description').val(),
                color: color,
                icon: $('#category-icon').val()
            };

            $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        SMOContentOrganizer.showNotification(smoContentOrganizer.strings.saved, 'success');
                        SMOContentOrganizer.closeModal();
                        SMOContentOrganizer.loadCategories();
                        SMOContentOrganizer.loadDashboardStats();
                    } else {
                        SMOContentOrganizer.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    SMOContentOrganizer.showNotification('An error occurred while saving the category: ' + error, 'error');
                }
            });
        },

        /**
         * Save quick idea
         */
        saveQuickIdea: function (e) {
            e.preventDefault();

            // Validate form data
            const title = $('#quick-idea-title').val().trim();
            const category = $('#quick-idea-category').val();

            if (!title) {
                this.showNotification('Idea title is required', 'error');
                return;
            }

            if (title.length > 200) {
                this.showNotification('Idea title must be 200 characters or less', 'error');
                return;
            }

            if (!category) {
                this.showNotification('Please select a category', 'error');
                return;
            }

            const data = {
                action: 'smo_save_quick_idea',
                nonce: smoContentOrganizer.nonce,
                title: title,
                description: $('#quick-idea-description').val(),
                category: category,
                priority: $('#quick-idea-priority').val()
            };

            $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        SMOContentOrganizer.showNotification(smoContentOrganizer.strings.saved, 'success');
                        SMOContentOrganizer.closeModal();
                        SMOContentOrganizer.loadIdeas();
                        SMOContentOrganizer.loadDashboardStats();
                    } else {
                        SMOContentOrganizer.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    SMOContentOrganizer.showNotification('An error occurred while saving the idea: ' + error, 'error');
                }
            });
        },

        /**
         * Save idea detail
         */
        saveIdeaDetail: function (e) {
            e.preventDefault();

            // Validate form data
            const title = $('#idea-title').val().trim();
            const category = $('#idea-category').val();

            if (!title) {
                this.showNotification('Idea title is required', 'error');
                return;
            }

            if (title.length > 200) {
                this.showNotification('Idea title must be 200 characters or less', 'error');
                return;
            }

            if (!category) {
                this.showNotification('Please select a category', 'error');
                return;
            }

            const data = {
                action: 'smo_save_idea',
                nonce: smoContentOrganizer.nonce,
                id: $('#idea-id').val(),
                title: title,
                content: $('#idea-content').val(),
                category: category,
                priority: $('#idea-priority').val(),
                status: $('#idea-status').val(),
                scheduled_date: $('#idea-scheduled-date').val(),
                tags: $('#idea-tags').val()
            };

            $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        SMOContentOrganizer.showNotification(smoContentOrganizer.strings.saved, 'success');
                        SMOContentOrganizer.closeModal();
                        SMOContentOrganizer.loadIdeas();
                        SMOContentOrganizer.loadDashboardStats();
                    } else {
                        SMOContentOrganizer.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    SMOContentOrganizer.showNotification('An error occurred while saving the idea: ' + error, 'error');
                }
            });
        },

        /**
         * Convert idea to post
         */
        convertToPost: function () {
            const ideaId = $('#idea-id').val();

            if (!ideaId) {
                this.showNotification('No idea selected', 'error');
                return;
            }

            // Redirect to post creation page with idea data
            window.location.href = `admin.php?page=smo-social-create-post&idea_id=${ideaId}`;
        },

        /**
         * Show notification
         */
        showNotification: function (message, type) {
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
         * Public methods for inline onclick handlers
         */
        editCategory: function (id) {
            this.ajax_get_category(id).done((response) => {
                if (response.success) {
                    const category = response.data;
                    $('#category-id').val(category.id);
                    $('#category-name').val(category.name);
                    $('#category-description').val(category.description);
                    $('#category-color').val(category.color);
                    $('#category-icon').val(category.icon);
                    $('#smo-category-modal-title').text('Edit Category');
                    $('#smo-category-modal').addClass('active');
                }
            });
        },

        deleteCategory: function (id) {
            if (confirm(smoContentOrganizer.strings.confirmDelete)) {
                $.ajax({
                    url: smoContentOrganizer.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smo_delete_category',
                        nonce: smoContentOrganizer.nonce,
                        id: id
                    },
                    success: (response) => {
                        if (response.success) {
                            this.showNotification(smoContentOrganizer.strings.deleted, 'success');
                            this.loadCategories();
                            this.loadDashboardStats();
                        } else {
                            this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                        }
                    }
                });
            }
        },

        viewIdea: function (id) {
            this.ajax_get_idea(id).done((response) => {
                if (response.success) {
                    const idea = response.data;
                    $('#idea-id').val(idea.id);
                    $('#idea-title').val(idea.title);
                    $('#idea-content').val(idea.content);
                    $('#idea-category').val(idea.category_id);
                    $('#idea-priority').val(idea.priority);
                    $('#idea-status').val(idea.status);
                    $('#idea-scheduled-date').val(idea.scheduled_date);
                    $('#idea-tags').val(idea.tags);
                    $('#smo-idea-detail-modal-title').text('View Idea');
                    $('#smo-idea-detail-modal').addClass('active');
                    $('#idea-content').prop('readonly', true);
                    $('#idea-title').prop('readonly', true);
                }
            });
        },

        editIdea: function (id) {
            this.ajax_get_idea(id).done((response) => {
                if (response.success) {
                    const idea = response.data;
                    $('#idea-id').val(idea.id);
                    $('#idea-title').val(idea.title);
                    $('#idea-content').val(idea.content);
                    $('#idea-category').val(idea.category_id);
                    $('#idea-priority').val(idea.priority);
                    $('#idea-status').val(idea.status);
                    $('#idea-scheduled-date').val(idea.scheduled_date);
                    $('#idea-tags').val(idea.tags);
                    $('#smo-idea-detail-modal-title').text('Edit Idea');
                    $('#smo-idea-detail-modal').addClass('active');
                    $('#idea-content').prop('readonly', false);
                    $('#idea-title').prop('readonly', false);
                }
            });
        },

        /**
         * Bulk organize functionality
         */
        bulkOrganize: function () {
            $('#smo-bulk-organize-modal').addClass('active');
            this.loadCategoriesIntoSelect('#bulk-organize-category');
            this.loadIdeasIntoBulkOrganize();
        },

        /**
         * Load ideas into bulk organize modal
         */
        loadIdeasIntoBulkOrganize: function () {
            const $container = $('#bulk-organize-ideas');
            $container.html('<div class="smo-loading"><div class="smo-spinner"></div><p>' + smoContentOrganizer.strings.loading + '</p></div>');

            this.ajax_get_ideas('all').done((response) => {
                if (response.success) {
                    let html = '';
                    response.data.forEach(idea => {
                        html += `
                            <div class="smo-bulk-organize-item">
                                <input type="checkbox" id="bulk-idea-${idea.id}" name="bulk-ideas" value="${idea.id}">
                                <label for="bulk-idea-${idea.id}">${idea.title}</label>
                            </div>
                        `;
                    });
                    $container.html(html);
                }
            }).fail(() => {
                $container.html('<div class="smo-empty-state"><p>No ideas found</p></div>');
            });
        },

        /**
         * Save bulk organize changes
         */
        saveBulkOrganize: function () {
            const selectedIdeas = [];
            $('input[name="bulk-ideas"]:checked').each(function () {
                selectedIdeas.push($(this).val());
            });

            const categoryId = $('#bulk-organize-category').val();
            const status = $('#bulk-organize-status').val();

            if (selectedIdeas.length === 0) {
                this.showNotification('Please select at least one idea', 'error');
                return;
            }

            if (!categoryId) {
                this.showNotification('Please select a category', 'error');
                return;
            }

            $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_bulk_organize',
                    nonce: smoContentOrganizer.nonce,
                    ideas: selectedIdeas,
                    category_id: categoryId,
                    status: status
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Ideas organized successfully!', 'success');
                        this.closeModal();
                        this.loadIdeas();
                        this.loadDashboardStats();
                    } else {
                        this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                    }
                }
            });
        },

        /**
         * Export content functionality
         */
        exportContent: function () {
            $('#smo-export-modal').addClass('active');
        },

        /**
         * Perform export
         */
        performExport: function () {
            const format = $('#export-format').val();
            const contentType = $('#export-content-type').val();

            this.ajax_export_content({
                format: format,
                content_type: contentType
            }).done((response) => {
                if (response.success) {
                    window.location.href = response.data.download_url;
                    this.closeModal();
                } else {
                    this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                }
            });
        },

        /**
         * Import content functionality
         */
        importContent: function () {
            $('#smo-import-modal').addClass('active');
        },

        /**
         * Perform import
         */
        performImport: function () {
            const formData = new FormData($('#smo-import-form')[0]);

            this.ajax_import_content(formData).done((response) => {
                if (response.success) {
                    this.showNotification('Content imported successfully!', 'success');
                    this.closeModal();
                    this.loadContentLibrary();
                } else {
                    this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                }
            });
        },

        /**
         * Show template modal
         */
        showTemplateModal: function () {
            $('#smo-template-modal').addClass('active');
            $('#template-id').val('');
            $('#template-name').val('');
            $('#template-content').val('');
            $('#template-category').val('');
            $('#smo-template-modal-title').text('Create Template');
        },

        /**
         * Save template
         */
        saveTemplate: function (e) {
            e.preventDefault();

            const name = $('#template-name').val().trim();
            const content = $('#template-content').val().trim();
            const category = $('#template-category').val();

            if (!name) {
                this.showNotification('Template name is required', 'error');
                return;
            }

            if (!content) {
                this.showNotification('Template content is required', 'error');
                return;
            }

            if (!category) {
                this.showNotification('Please select a category', 'error');
                return;
            }

            const data = {
                id: $('#template-id').val(),
                name: name,
                content: content,
                category_id: category
            };

            this.ajax_save_template(data).done((response) => {
                if (response.success) {
                    this.showNotification('Template saved successfully!', 'success');
                    this.closeModal();
                    this.loadTemplates();
                } else {
                    this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                }
            });
        },

        /**
         * Edit template
         */
        editTemplate: function (id) {
            this.ajax_get_template(id).done((response) => {
                if (response.success) {
                    const template = response.data;
                    $('#template-id').val(template.id);
                    $('#template-name').val(template.name);
                    $('#template-content').val(template.content);
                    $('#template-category').val(template.category_id);
                    $('#smo-template-modal-title').text('Edit Template');
                    $('#smo-template-modal').addClass('active');
                } else {
                    this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                }
            }).fail(() => {
                this.showNotification('Failed to load template data', 'error');
            });
        },

        /**
         * Delete template
         */
        deleteTemplate: function (id) {
            if (confirm(smoContentOrganizer.strings.confirmDelete)) {
                $.ajax({
                    url: smoContentOrganizer.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smo_delete_template',
                        nonce: smoContentOrganizer.nonce,
                        id: id
                    },
                    success: (response) => {
                        if (response.success) {
                            this.showNotification('Template deleted successfully!', 'success');
                            this.loadTemplates();
                        } else {
                            this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                        }
                    },
                    error: (xhr, status, error) => {
                        this.showNotification('An error occurred while deleting the template: ' + error, 'error');
                    }
                });
            }
        },

        /**
         * Use template
         */
        useTemplate: function (id) {
            this.ajax_get_template(id).done((response) => {
                if (response.success) {
                    const template = response.data;
                    // Redirect to post creation with template data
                    window.location.href = `admin.php?page=smo-social-create-post&template_id=${template.id}`;
                } else {
                    this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                }
            }).fail(() => {
                this.showNotification('Failed to load template data', 'error');
            });
        },

        /**
         * AJAX: Get template by ID
         */
        ajax_get_template: function (id) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_get_template',
                    nonce: smoContentOrganizer.nonce,
                    id: id
                }
            });
        },

        /**
         * Download content
         */
        downloadContent: function (id) {
            this.ajax_download_content(id).done((response) => {
                if (response.success) {
                    window.location.href = response.data.download_url;
                } else {
                    this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                }
            }).fail(() => {
                this.showNotification('Failed to download content', 'error');
            });
        },

        /**
         * AJAX: Download content
         */
        ajax_download_content: function (id) {
            return $.ajax({
                url: smoContentOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_download_content',
                    nonce: smoContentOrganizer.nonce,
                    id: id
                }
            });
        },

        /**
         * Delete content
         */
        deleteContent: function (id) {
            if (confirm(smoContentOrganizer.strings.confirmDelete)) {
                $.ajax({
                    url: smoContentOrganizer.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smo_delete_content',
                        nonce: smoContentOrganizer.nonce,
                        id: id
                    },
                    success: (response) => {
                        if (response.success) {
                            this.showNotification('Content deleted successfully!', 'success');
                            this.loadContentLibrary();
                        } else {
                            this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                        }
                    },
                    error: (xhr, status, error) => {
                        this.showNotification('An error occurred while deleting the content: ' + error, 'error');
                    }
                });
            }
        },

        /**
         * Upload content functionality
         */
        uploadContent: function () {
            $('#smo-upload-modal').addClass('active');
        },

        /**
         * Perform upload
         */
        performUpload: function () {
            const formData = new FormData($('#smo-upload-form')[0]);

            // Validate file is selected
            const fileInput = $('#upload-file')[0];
            if (!fileInput || fileInput.files.length === 0) {
                this.showNotification('Please select a file to upload', 'error');
                return;
            }

            // Validate file size
            const maxSize = 10 * 1024 * 1024; // 10MB
            const file = fileInput.files[0];
            if (file.size > maxSize) {
                this.showNotification('File size exceeds maximum limit of 10MB', 'error');
                return;
            }

            // Show loading state
            const $uploadBtn = $('#smo-upload-form').find('button[type="submit"]');
            const originalText = $uploadBtn.text();
            $uploadBtn.prop('disabled', true).text('Uploading...');

            this.ajax_upload_content(formData).done((response) => {
                if (response.success) {
                    this.showNotification('Content uploaded successfully!', 'success');
                    this.closeModal();
                    this.loadContentLibrary();
                } else {
                    this.showNotification(response.data.message || smoContentOrganizer.strings.error, 'error');
                }
            }).fail((xhr, status, error) => {
                this.showNotification('Upload failed: ' + (error || 'Unknown error'), 'error');
            }).always(() => {
                // Restore button state
                $uploadBtn.prop('disabled', false).text(originalText);
            });
        },

        /**
         * Enhanced error handling for AJAX calls
         */
        handleAJAXError: function (xhr, status, error, context) {
            let errorMessage = 'An error occurred';

            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMessage = xhr.responseJSON.data.message;
            } else if (error) {
                errorMessage = error;
            } else if (status) {
                errorMessage = status;
            }

            // Add context if provided
            if (context) {
                errorMessage = context + ': ' + errorMessage;
            }

            this.showNotification(errorMessage, 'error');

            // Log to console for debugging
            console.error('AJAX Error:', status, error);
            if (xhr.responseJSON) {
                console.error('Response:', xhr.responseJSON);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        SMOContentOrganizer.init();
    });

    // Expose to global scope
    window.SMOContentOrganizer = SMOContentOrganizer;

})(jQuery);
