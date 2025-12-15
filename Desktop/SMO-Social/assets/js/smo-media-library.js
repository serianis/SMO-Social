/**
 * SMO Social Media Library JavaScript
 * 
 * Handles all interactive functionality for the media library including:
 * - Image browsing and filtering
 * - Selection management
 * - Share modal interactions
 * - AJAX operations
 * - Responsive behavior
 *
 * @package SMO_Social
 * @subpackage Assets/JS
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // Media Library Manager
    const SMOMediaLibrary = {
        selectedImages: [],
        currentPage: 1,
        currentView: 'grid',
        isLoading: false,
        filters: {
            search: '',
            date_range: '',
            file_types: [],
            file_size: '',
            dimensions: [],
            sort_by: 'date',
            per_page: 24
        },
        pagination: {
            total_pages: 1,
            total_found: 0
        },

        /**
         * Initialize the media library
         */
        init: function () {
            this.bindEvents();
            this.loadMedia();
            this.initializeKeyboardShortcuts();
            this.initializeDragAndDrop();

            // Initialize tooltips
            this.initTooltips();
        },

        /**
         * Bind all event listeners
         */
        bindEvents: function () {
            const $document = $(document);

            // View toggle
            $document.on('click', '.smo-view-btn', this.toggleView.bind(this));

            // Search and filters
            $('#smo-media-search').on('input', this.debounce(this.searchMedia.bind(this), 300));
            $('#smo-clear-search').on('click', this.clearSearch.bind(this));
            $('#smo-date-filter, #smo-size-filter, #smo-sort-by, #smo-per-page').on('change', this.applyFilters.bind(this));

            // File type and dimension filters
            $document.on('change', '.smo-file-types input, .smo-dimension-filters input', this.applyFilters.bind(this));

            // Image selection (using event delegation)
            $document.on('click', '.smo-media-item, .smo-media-list-item', this.toggleImageSelection.bind(this));

            // Image actions
            $document.on('click', '.smo-preview-btn', this.previewImage.bind(this));
            $document.on('click', '.smo-edit-btn', this.openImageEditor.bind(this));
            $document.on('click', '.smo-share-btn', this.shareSingleImage.bind(this));

            // Pagination
            $('#smo-prev-page').on('click', this.prevPage.bind(this));
            $('#smo-next-page').on('click', this.nextPage.bind(this));

            // Upload
            $('#smo-upload-images').on('click', this.uploadImages.bind(this));

            // Share actions
            $('#smo-share-selected, #smo-share-from-bar').on('click', this.openShareModal.bind(this));
            $('#smo-clear-selection').on('click', this.clearSelection.bind(this));

            // Modal controls
            $document.on('click', '.smo-modal-close, #smo-cancel-share', this.closeModal.bind(this));
            $document.on('click', '.smo-modal', function (e) {
                if (e.target === this) {
                    SMOMediaLibrary.closeModal();
                }
            });

            // Share form
            $('#smo-share-form').on('submit', this.submitShare.bind(this));
            $document.on('change', 'input[name="schedule_type"]', this.toggleScheduleFields.bind(this));
            $('#smo-post-content').on('input', this.updateCharacterCount.bind(this));
            $('#smo-generate-hashtags').on('click', this.generateHashtags.bind(this));

            // Preview
            $('#smo-preview-share').on('click', this.previewShare.bind(this));

            // Bulk operations
            $('#smo-bulk-schedule').on('click', this.openBulkScheduleModal.bind(this));
        },

        /**
         * Load media library with current filters
         */
        loadMedia: function () {
            if (this.isLoading) {
                return;
            }

            this.isLoading = true;
            const $container = this.currentView === 'grid' ? $('#smo-media-grid') : $('#smo-media-list');

            // Show loading state
            $container.html(this.getLoadingState());

            // Prepare AJAX data
            const ajaxData = {
                action: 'smo_get_media_library',
                nonce: smoMediaLibrary.nonce,
                page: this.currentPage,
                ...this.filters
            };

            // Make AJAX request
            $.ajax({
                url: smoMediaLibrary.ajax_url,
                type: 'POST',
                data: ajaxData,
                timeout: 30000
            })
                .done((response) => {
                    this.isLoading = false;

                    if (response.success) {
                        this.renderMedia(response.data);
                        this.updatePagination(response.data);
                        this.updateResultsCount(response.data);
                    } else {
                        this.showError('Failed to load media library: ' + response.data);
                    }
                })
                .fail((jqXHR, textStatus, errorThrown) => {
                    this.isLoading = false;
                    this.showError('Failed to load media library: ' + textStatus);
                });
        },

        /**
         * Render media items in the current view
         */
        renderMedia: function (data) {
            const $container = this.currentView === 'grid' ? $('#smo-media-grid') : $('#smo-media-list');

            if (!data.media || data.media.length === 0) {
                $container.html(this.getNoResultsState());
                return;
            }

            let html = '';
            if (this.currentView === 'grid') {
                html = this.renderGridView(data.media);
            } else {
                html = this.renderListView(data.media);
            }

            $container.html(html);

            // Animate items in
            this.animateItemsIn($container);
        },

        /**
         * Render grid view of media items
         */
        renderGridView: function (media) {
            return media.map((item, index) => `
                <div class="smo-media-item" data-id="${item.id}" data-url="${item.url}" style="animation-delay: ${index * 0.05}s">
                    <div class="smo-checkbox"></div>
                    <img src="${item.thumbnail}" alt="${this.escapeHtml(item.alt_text || item.title || '')}" loading="lazy">
                    <div class="smo-media-overlay">
                        <button type="button" class="button button-small smo-preview-btn" data-image-id="${item.id}">
                            <span class="dashicons dashicons-visibility"></span> Preview
                        </button>
                        <button type="button" class="button button-small smo-edit-btn" data-image-id="${item.id}" data-url="${item.url}" data-filename="${item.filename}">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </button>
                        <button type="button" class="button button-small smo-share-btn" data-image-id="${item.id}">
                            <span class="dashicons dashicons-share"></span> Share
                        </button>
                    </div>
                    <div class="smo-media-info">
                        <div class="smo-media-title" title="${this.escapeHtml(item.title || item.filename)}">${this.escapeHtml(item.title || item.filename)}</div>
                        <div class="smo-media-meta">
                            <span>${item.size}</span>
                            <span>${item.dimensions}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        },

        /**
         * Render list view of media items
         */
        renderListView: function (media) {
            return media.map((item, index) => `
                <div class="smo-media-list-item" data-id="${item.id}" data-url="${item.url}" style="animation-delay: ${index * 0.03}s">
                    <div class="smo-media-list-thumb">
                        <img src="${item.thumbnail}" alt="${this.escapeHtml(item.alt_text || item.title || '')}" loading="lazy">
                    </div>
                    <div class="smo-media-list-details">
                        <div class="smo-media-list-title" title="${this.escapeHtml(item.title || item.filename)}">${this.escapeHtml(item.title || item.filename)}</div>
                        <div class="smo-media-list-meta">
                            <span>${item.size}</span>
                            <span>${item.dimensions}</span>
                            <span>${item.date}</span>
                        </div>
                    </div>
                    <div class="smo-media-list-actions">
                        <button type="button" class="button button-small smo-preview-btn" data-image-id="${item.id}">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" class="button button-small smo-edit-btn" data-image-id="${item.id}" data-url="${item.url}" data-filename="${item.filename}">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="button button-small smo-share-btn" data-image-id="${item.id}">
                            <span class="dashicons dashicons-share"></span>
                        </button>
                    </div>
                </div>
            `).join('');
        },

        /**
         * Toggle image selection
         */
        toggleImageSelection: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $item = $(this);
            const imageId = parseInt($item.data('id'));
            const imageUrl = $item.data('url');

            if ($item.hasClass('selected')) {
                // Deselect
                this.deselectImage(imageId, $item);
            } else {
                // Select
                this.selectImage(imageId, imageUrl, $item);
            }

            this.updateSelectionUI();

            // Provide haptic feedback on mobile
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
        },

        /**
         * Select an image
         */
        selectImage: function (imageId, imageUrl, $element) {
            $element.addClass('selected');

            this.selectedImages.push({
                id: imageId,
                url: imageUrl,
                element: $element,
                selectedAt: new Date()
            });
        },

        /**
         * Deselect an image
         */
        deselectImage: function (imageId, $element) {
            $element.removeClass('selected');

            this.selectedImages = this.selectedImages.filter(img => img.id !== imageId);
        },

        /**
         * Update selection UI elements
         */
        updateSelectionUI: function () {
            const count = this.selectedImages.length;

            if (count > 0) {
                this.showSelectionBar();
                this.updateSelectionCount(count);
                this.enableSelectionButtons();
            } else {
                this.hideSelectionBar();
                this.disableSelectionButtons();
            }
        },

        /**
         * Show selection bar
         */
        showSelectionBar: function () {
            const $bar = $('#smo-selection-bar');
            if ($bar.length) {
                $bar.slideDown(300, 'easeInOutCubic');
            }
        },

        /**
         * Hide selection bar
         */
        hideSelectionBar: function () {
            const $bar = $('#smo-selection-bar');
            if ($bar.length) {
                $bar.slideUp(300, 'easeInOutCubic');
            }
        },

        /**
         * Update selection count
         */
        updateSelectionCount: function (count) {
            $('#smo-selected-count').text(`${count} image${count !== 1 ? 's' : ''} selected`);
        },

        /**
         * Enable selection buttons
         */
        enableSelectionButtons: function () {
            $('#smo-share-selected, #smo-bulk-schedule').prop('disabled', false).removeClass('disabled');
            $('#smo-share-from-bar').prop('disabled', false);
        },

        /**
         * Disable selection buttons
         */
        disableSelectionButtons: function () {
            $('#smo-share-selected, #smo-bulk-schedule').prop('disabled', true).addClass('disabled');
            $('#smo-share-from-bar').prop('disabled', true);
        },

        /**
         * Clear all selections
         */
        clearSelection: function () {
            this.selectedImages.forEach(img => {
                img.element.removeClass('selected');
            });
            this.selectedImages = [];
            this.updateSelectionUI();
        },

        /**
         * Get current filter parameters
         */
        getFilterParameters: function () {
            return {
                search: this.filters.search,
                date_range: this.filters.date_range,
                file_types: this.filters.file_types,
                file_size: this.filters.file_size,
                dimensions: this.filters.dimensions,
                sort_by: this.filters.sort_by,
                per_page: this.filters.per_page
            };
        },

        /**
         * Apply filters and reload media
         */
        applyFilters: function () {
            // Update filter values
            this.filters.search = $('#smo-media-search').val();
            this.filters.date_range = $('#smo-date-filter').val();
            this.filters.file_size = $('#smo-size-filter').val();
            this.filters.sort_by = $('#smo-sort-by').val();
            this.filters.per_page = parseInt($('#smo-per-page').val());

            // Update array filters
            this.filters.file_types = $('.smo-file-types input:checked').map((i, el) => $(el).val()).get();
            this.filters.dimensions = $('.smo-dimension-filters input:checked').map((i, el) => $(el).val()).get();

            // Reset to first page and reload
            this.currentPage = 1;
            this.loadMedia();
        },

        /**
         * Search media with debouncing
         */
        searchMedia: function () {
            this.filters.search = $('#smo-media-search').val();
            this.currentPage = 1;
            this.loadMedia();
        },

        /**
         * Clear search and reload
         */
        clearSearch: function () {
            $('#smo-media-search').val('');
            this.filters.search = '';
            this.currentPage = 1;
            this.loadMedia();

            // Clear search box focus
            $('#smo-media-search').blur();
        },

        /**
         * Toggle between grid and list views
         */
        toggleView: function (e) {
            const $button = $(e.currentTarget);
            const view = $button.data('view');

            if (view === this.currentView) return;

            // Update button states
            $('.smo-view-btn').removeClass('active');
            $button.addClass('active');

            // Switch views
            this.currentView = view;

            if (view === 'grid') {
                $('#smo-media-grid').show();
                $('#smo-media-list').hide();
            } else {
                $('#smo-media-grid').hide();
                $('#smo-media-list').show();
            }
        },

        /**
         * Update pagination controls
         */
        updatePagination: function (data) {
            this.pagination.total_pages = data.total_pages || 1;
            this.pagination.total_found = data.total_found || 0;

            $('#smo-page-info').text(`Page ${this.currentPage} of ${this.pagination.total_pages}`);
            $('#smo-prev-page').prop('disabled', this.currentPage <= 1);
            $('#smo-next-page').prop('disabled', this.currentPage >= this.pagination.total_pages);
        },

        /**
         * Update results count display
         */
        updateResultsCount: function (data) {
            const count = data.total_found || 0;
            $('#smo-results-count').text(`${count} image${count !== 1 ? 's' : ''} found`);
        },

        /**
         * Navigate to previous page
         */
        prevPage: function () {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadMedia();
            }
        },

        /**
         * Navigate to next page
         */
        nextPage: function () {
            if (this.currentPage < this.pagination.total_pages) {
                this.currentPage++;
                this.loadMedia();
            }
        },

        /**
         * Open upload dialog
         */
        uploadImages: function () {
            const mediaUploader = wp.media({
                title: 'Upload Images for Social Media',
                button: {
                    text: 'Add Images'
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', () => {
                const attachments = mediaUploader.state().get('selection').toJSON();
                this.handleUploadedImages(attachments);
            });

            mediaUploader.open();
        },

        /**
         * Handle uploaded images
         */
        handleUploadedImages: function (attachments) {
            if (attachments.length > 0) {
                // Refresh media library to show new uploads
                this.loadMedia();

                // Show success message
                this.showNotification(`${attachments.length} image${attachments.length !== 1 ? 's' : ''} uploaded successfully`, 'success');
            }
        },

        /**
         * Open share modal
         */
        openShareModal: function () {
            if (this.selectedImages.length === 0) {
                this.showNotification(smoMediaLibrary.strings.select_at_least_one, 'warning');
                return;
            }

            // Populate selected images preview
            this.populateSelectedImagesPreview();

            // Show modal
            $('#smo-share-modal').fadeIn(300);

            // Focus on content textarea
            setTimeout(() => {
                $('#smo-post-content').focus();
            }, 100);
        },

        /**
         * Populate selected images preview in modal
         */
        populateSelectedImagesPreview: function () {
            const previewHtml = this.selectedImages.map(img =>
                `<img src="${img.url}" alt="Selected image" loading="lazy">`
            ).join('');

            $('#smo-selected-images-preview').html(previewHtml);
        },

        /**
         * Close modal
         */
        closeModal: function () {
            $('.smo-modal').fadeOut(200);
        },

        /**
         * Toggle schedule fields based on selection
         */
        toggleScheduleFields: function (e) {
            const scheduleType = $('input[name="schedule_type"]:checked').val();
            const $datetimeField = $('#smo-schedule-datetime');

            if (scheduleType === 'scheduled') {
                $datetimeField.slideDown(200);
            } else {
                $datetimeField.slideUp(200);
            }
        },

        /**
         * Update character count
         */
        updateCharacterCount: function () {
            const content = $(this).val();
            const count = content.length;
            $('#smo-char-count').text(count);

            // Change color based on length
            const $counter = $('#smo-char-count');
            $counter.removeClass('warning error');

            if (count > 280) {
                $counter.addClass('error');
            } else if (count > 250) {
                $counter.addClass('warning');
            }
        },

        /**
         * Generate hashtags
         */
        generateHashtags: function () {
            const content = $('#smo-post-content').val();

            if (!content) {
                this.showNotification('Please enter some content first', 'warning');
                return;
            }

            // Show loading state
            const $button = $('#smo-generate-hashtags');
            const originalText = $button.text();
            $button.prop('disabled', true).text('Generating...');

            $.ajax({
                url: smoMediaLibrary.ajax_url,
                type: 'POST',
                data: {
                    action: 'smo_generate_hashtags',
                    nonce: smoMediaLibrary.nonce,
                    content: content
                }
            })
                .done((response) => {
                    if (response.success) {
                        $('#smo-hashtags').val(response.data.hashtags.join(' '));
                        this.showNotification('Hashtags generated successfully', 'success');
                    } else {
                        this.showNotification('Failed to generate hashtags: ' + response.data, 'error');
                    }
                })
                .fail(() => {
                    this.showNotification('Failed to generate hashtags', 'error');
                })
                .always(() => {
                    $button.prop('disabled', false).text(originalText);
                });
        },

        /**
         * Submit share form
         */
        submitShare: function (e) {
            e.preventDefault();

            if (this.selectedImages.length === 0) {
                this.showNotification(smoMediaLibrary.strings.select_at_least_one, 'warning');
                return;
            }

            // Validate form
            if (!this.validateShareForm()) {
                return;
            }

            // Prepare form data
            const formData = {
                action: 'smo_share_selected_images',
                nonce: smoMediaLibrary.nonce,
                image_ids: this.selectedImages.map(img => img.id),
                content: $('#smo-post-content').val(),
                hashtags: $('#smo-hashtags').val(),
                platforms: $('input[name="platforms[]"]:checked').map((i, el) => $(el).val()).get(),
                sharing_method: $('input[name="sharing_method"]:checked').val(),
                schedule_type: $('input[name="schedule_type"]:checked').val(),
                scheduled_time: $('#smo-scheduled-time').val(),
                template_id: $('#smo-post-template').val() || null
            };

            // Show loading state
            const $submitButton = $('#smo-submit-share');
            const originalText = $submitButton.text();
            $submitButton.prop('disabled', true).text('Sharing...');

            // Make AJAX request
            $.ajax({
                url: smoMediaLibrary.ajax_url,
                type: 'POST',
                data: formData,
                timeout: 60000 // 1 minute timeout for sharing operations
            })
                .done((response) => {
                    if (response.success) {
                        this.handleShareSuccess(response.data);
                    } else {
                        this.handleShareError(response.data);
                    }
                })
                .fail((jqXHR, textStatus, errorThrown) => {
                    this.handleShareError('Network error: ' + textStatus);
                })
                .always(() => {
                    $submitButton.prop('disabled', false).text(originalText);
                });
        },

        /**
         * Handle successful share
         */
        handleShareSuccess: function (data) {
            this.showNotification(smoMediaLibrary.strings.success, 'success');
            this.closeModal();
            this.clearSelection();
            this.loadMedia(); // Refresh to show any changes

            // Show detailed results
            if (data.success && data.success.length > 0) {
                const successCount = data.success.length;
                const failedCount = data.failed ? data.failed.length : 0;
                const message = `Successfully scheduled ${successCount} post${successCount !== 1 ? 's' : ''}`;

                this.showNotification(message, 'success', 5000);
            }
        },

        /**
         * Handle share error
         */
        handleShareError: function (error) {
            this.showNotification(smoMediaLibrary.strings.error + ': ' + error, 'error');
        },

        /**
         * Validate share form
         */
        validateShareForm: function () {
            const platforms = $('input[name="platforms[]"]:checked').length;

            if (platforms === 0) {
                this.showNotification('Please select at least one platform', 'warning');
                return false;
            }

            // Validate scheduled time if scheduled option is selected
            const scheduleType = $('input[name="schedule_type"]:checked').val();
            if (scheduleType === 'scheduled') {
                const scheduledTime = $('#smo-scheduled-time').val();
                if (!scheduledTime) {
                    this.showNotification('Please select a scheduled time', 'warning');
                    return false;
                }

                const scheduledDate = new Date(scheduledTime);
                const now = new Date();
                if (scheduledDate <= now) {
                    this.showNotification('Scheduled time must be in the future', 'warning');
                    return false;
                }
            }

            return true;
        },

        /**
         * Open image editor
         */
        openImageEditor: function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(e.currentTarget);
            const imageId = $btn.data('image-id');
            const imageUrl = $btn.data('url');
            const filename = $btn.data('filename');

            if (window.SMOImageEditor) {
                window.SMOImageEditor.open(imageUrl, imageId, filename);
            } else {
                this.showNotification('Image Editor not available', 'error');
            }
        },

        /**
         * Preview share post
         */
        previewShare: function () {
            const selectedImage = this.selectedImages[0]; // Preview first selected image
            if (!selectedImage) {
                this.showNotification('No image selected for preview', 'warning');
                return;
            }

            const formData = {
                action: 'smo_preview_share',
                nonce: smoMediaLibrary.nonce,
                image_url: selectedImage.url,
                content: $('#smo-post-content').val(),
                hashtags: $('#smo-hashtags').val(),
                platforms: $('input[name="platforms[]"]:checked').map((i, el) => $(el).val()).get()
            };

            $.ajax({
                url: smoMediaLibrary.ajax_url,
                type: 'POST',
                data: formData
            })
                .done((response) => {
                    if (response.success) {
                        $('#smo-preview-content').html(response.data.html);
                        $('#smo-preview-modal').fadeIn(300);
                    } else {
                        this.showNotification('Failed to generate preview: ' + response.data, 'error');
                    }
                })
                .fail(() => {
                    this.showNotification('Failed to generate preview', 'error');
                });
        },

        /**
         * Preview single image
         */
        previewImage: function (e) {
            e.stopPropagation();

            const imageId = $(e.currentTarget).data('image-id');
            const $item = $(`.smo-media-item[data-id="${imageId}"], .smo-media-list-item[data-id="${imageId}"]`);
            const imageUrl = $item.data('url');

            if (imageUrl) {
                this.openImagePreview(imageUrl);
            }
        },

        /**
         * Share single image
         */
        shareSingleImage: function (e) {
            e.stopPropagation();

            const imageId = $(e.currentTarget).data('image-id');
            const $item = $(`.smo-media-item[data-id="${imageId}"], .smo-media-list-item[data-id="${imageId}"]`);

            if (!$item.hasClass('selected')) {
                this.selectImage(imageId, $item.data('url'), $item);
                this.updateSelectionUI();
            }

            this.openShareModal();
        },

        /**
         * Open image preview modal
         */
        openImagePreview: function (imageUrl) {
            const previewHtml = `
                <div class="smo-image-preview-modal">
                    <div class="smo-image-preview-container">
                        <img src="${imageUrl}" alt="Image preview" style="max-width: 100%; max-height: 80vh; object-fit: contain;">
                        <div class="smo-image-preview-actions">
                            <button type="button" class="button" onclick="SMOMediaLibrary.closeModal()">Close</button>
                        </div>
                    </div>
                </div>
            `;

            // Create and show modal
            const $modal = $('<div class="smo-modal" style="display: flex;">').html(previewHtml);
            $('body').append($modal);

            // Close on escape key or click
            $modal.on('click', function (e) {
                if (e.target === this) {
                    $modal.remove();
                }
            });

            $(document).on('keydown.preview', function (e) {
                if (e.keyCode === 27) { // Escape key
                    $modal.remove();
                    $(document).off('keydown.preview');
                }
            });
        },

        /**
         * Open bulk schedule modal
         */
        openBulkScheduleModal: function () {
            // For now, just use the regular share modal
            // This can be enhanced with bulk-specific features
            this.openShareModal();
        },

        /**
         * Show notification
         */
        showNotification: function (message, type = 'info', duration = 3000) {
            const $notification = $(`
                <div class="smo-notification smo-notification-${type}">
                    <div class="smo-notification-content">
                        <span class="smo-notification-message">${this.escapeHtml(message)}</span>
                        <button type="button" class="smo-notification-close">&times;</button>
                    </div>
                </div>
            `);

            $('body').append($notification);

            // Show notification
            setTimeout(() => {
                $notification.addClass('show');
            }, 100);

            // Auto hide
            if (duration > 0) {
                setTimeout(() => {
                    this.hideNotification($notification);
                }, duration);
            }

            // Manual close
            $notification.find('.smo-notification-close').on('click', () => {
                this.hideNotification($notification);
            });
        },

        /**
         * Hide notification
         */
        hideNotification: function ($notification) {
            $notification.removeClass('show');
            setTimeout(() => {
                $notification.remove();
            }, 300);
        },

        /**
         * Show error state
         */
        showError: function (message) {
            const $container = this.currentView === 'grid' ? $('#smo-media-grid') : $('#smo-media-list');
            $container.html(`
                <div class="smo-error-state">
                    <div class="smo-error-icon">⚠️</div>
                    <p>${this.escapeHtml(message)}</p>
                    <button type="button" class="button" onclick="SMOMediaLibrary.loadMedia()">Try Again</button>
                </div>
            `);
        },

        /**
         * Get loading state HTML
         */
        getLoadingState: function () {
            return `
                <div class="smo-loading-state">
                    <div class="smo-spinner"></div>
                    <p>Loading media library...</p>
                </div>
            `;
        },

        /**
         * Get no results state HTML
         */
        getNoResultsState: function () {
            return `
                <div class="smo-no-results">
                    <div class="smo-no-results-icon">📷</div>
                    <p>No images found matching your criteria.</p>
                    <button type="button" class="button" onclick="SMOMediaLibrary.clearSearch(); SMOMediaLibrary.loadMedia();">Clear Filters</button>
                </div>
            `;
        },

        /**
         * Animate items in
         */
        animateItemsIn: function ($container) {
            const $items = $container.find('.smo-media-item, .smo-media-list-item');
            $items.each(function (index) {
                $(this).css('animation-delay', `${index * 0.05}s`);
            });
        },

        /**
         * Initialize keyboard shortcuts
         */
        initializeKeyboardShortcuts: function () {
            $(document).on('keydown', (e) => {
                // Ctrl+A or Cmd+A - Select all
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 65) {
                    e.preventDefault();
                    this.selectAllImages();
                }

                // Escape - Clear selection or close modal
                if (e.keyCode === 27) {
                    if ($('.smo-modal').is(':visible')) {
                        this.closeModal();
                    } else {
                        this.clearSelection();
                    }
                }

                // Delete - Remove selected images
                if (e.keyCode === 46) {
                    if (this.selectedImages.length > 0) {
                        e.preventDefault();
                        this.clearSelection();
                    }
                }
            });
        },

        /**
         * Select all images on current page
         */
        selectAllImages: function () {
            const $items = $('.smo-media-item, .smo-media-list-item');
            $items.each((index, item) => {
                const $item = $(item);
                const imageId = parseInt($item.data('id'));

                if (!$item.hasClass('selected')) {
                    this.selectImage(imageId, $item.data('url'), $item);
                }
            });

            this.updateSelectionUI();
            this.showNotification(`Selected all ${$items.length} images on this page`, 'info');
        },

        /**
         * Initialize drag and drop functionality
         */
        initializeDragAndDrop: function () {
            // This can be enhanced for drag-to-reorder or drag-to-share functionality
            if (typeof $.fn.draggable !== 'undefined') {
                $('.smo-media-item').draggable({
                    helper: 'clone',
                    appendTo: 'body',
                    zIndex: 1000
                });
            }
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function () {
            // Simple tooltip implementation - can be enhanced with a library like Tippy.js
            $('[title]').on('mouseenter', function () {
                const title = $(this).attr('title');
                if (title) {
                    $(this).data('original-title', title).removeAttr('title');

                    const $tooltip = $(`<div class="smo-tooltip">${title}</div>`);
                    $('body').append($tooltip);

                    const position = $(this).offset();
                    $tooltip.css({
                        top: position.top - $tooltip.outerHeight() - 5,
                        left: position.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                    }).addClass('show');
                }
            }).on('mouseleave', function () {
                const $tooltip = $('.smo-tooltip');
                $tooltip.removeClass('show');
                setTimeout(() => $tooltip.remove(), 200);

                $(this).attr('title', $(this).data('original-title'));
            });
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function (text) {
            const map = {
                '&': '&',
                '<': '<',
                '>': '>',
                '"': '"',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function (m) { return map[m]; });
        },

        /**
         * Debounce function for performance
         */
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        // Only initialize if we're on the media library page
        if ($('.smo-media-library').length) {
            SMOMediaLibrary.init();
        }
    });

    // Global access for debugging
    window.SMOMediaLibrary = SMOMediaLibrary;

})(jQuery);
