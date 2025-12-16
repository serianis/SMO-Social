/**
 * SMO Social Enhanced Create Post
 * Handles all advanced post creation features with modern UI
 */

(function ($) {
    'use strict';

    const SMOEnhancedPost = {
        // Configuration
        config: {
            maxGalleryImages: 10,
            maxLinks: 5,
            maxReshareQueue: 200,
            platformLimits: {
                twitter: 280,
                instagram: 2200,
                linkedin: 3000,
                facebook: 63206
            }
        },

        // State management
        state: {
            selectedImages: [],
            selectedVideos: [],
            galleryImages: [],
            reshareQueue: [],
            currentPostType: 'text'
        },

        /**
         * Initialize the enhanced post creator
         */
        init: function () {
            this.bindEvents();
            this.initializeComponents();
            this.setupValidation();
            this.initializeImageUpload();
            this.initializeVideoUpload();
            this.initializeGallery();
            this.initializeReshare();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function () {
            // Post type selection
            $('input[name="post_type"]').on('change', this.handlePostTypeChange.bind(this));

            // Content editing
            $('#post_content').on('input', this.updateCharacterCount.bind(this));
            $('#smo-add-emoji').on('click', this.showEmojiPicker.bind(this));

            // Link management
            $('#smo-add-link').on('click', this.addLinkField.bind(this));
            $(document).on('click', '.smo-remove-link', this.removeLinkField.bind(this));
            $(document).on('input', '.smo-link-input', this.handleLinkInput.bind(this));
            $(document).on('click', '.smo-preview-link', this.previewLink.bind(this));

            // Media management - Single images
            $('#smo-add-media').on('click', this.openMediaLibrary.bind(this));
            $(document).on('click', '.smo-remove-media', this.removeMedia.bind(this));
            $(document).on('click', '.smo-edit-media', this.editMedia.bind(this));

            // Gallery management - Multiple images
            $('#smo-add-gallery-images').on('click', this.openGalleryLibrary.bind(this));
            $(document).on('click', '.smo-remove-gallery-item', this.removeGalleryItem.bind(this));

            // Video management
            $('#video_url').on('input', this.handleVideoUrlInput.bind(this));
            $('#smo-preview-video').on('click', this.previewVideo.bind(this));

            // Reshare management
            $('#original_post_url').on('input', this.handleReshareUrlInput.bind(this));
            $('input[name="add_to_queue"]').on('change', this.toggleReshareQueue.bind(this));
            $('#smo-preview-reshare').on('click', this.previewReshare.bind(this));

            // AI features
            $('#smo-ai-optimize').on('click', this.optimizeContent.bind(this));
            $('#smo-generate-hashtags').on('click', this.generateHashtags.bind(this));
            $('#smo-best-time').on('click', this.getBestTimes.bind(this));

            // Scheduling
            $('input[name="schedule_type"]').on('change', this.handleScheduleTypeChange.bind(this));

            // Templates
            $('#smo-load-template').on('click', this.loadTemplate.bind(this));
            $('#smo-save-template').on('click', this.saveTemplate.bind(this));

            // Form actions
            $('#smo-preview-post').on('click', this.previewPost.bind(this));
            $('#smo-reset-form').on('click', this.resetForm.bind(this));
            $('#smo-enhanced-create-post-form').on('submit', this.handleFormSubmit.bind(this));

            // Platform selection
            $('input[name="post_platforms[]"]').on('change', this.updateCharacterCount.bind(this));
        },

        /**
         * Initialize components
         */
        initializeComponents: function () {
            this.updateCharacterCount();
            this.handlePostTypeChange();
            this.handleScheduleTypeChange();
        },

        /**
         * Setup form validation
         */
        setupValidation: function () {
            if (typeof $.fn.validate !== 'function') {
                return;
            }

            $('#smo-enhanced-create-post-form').validate({
                rules: {
                    post_content: {
                        required: true,
                        minlength: 10
                    },
                    'post_platforms[]': {
                        required: true
                    }
                },
                messages: {
                    post_content: {
                        required: 'Please enter post content',
                        minlength: 'Content must be at least 10 characters'
                    },
                    'post_platforms[]': {
                        required: 'Please select at least one platform'
                    }
                }
            });
        },

        /**
         * Initialize image upload functionality
         */
        initializeImageUpload: function () {
        },

        /**
         * Initialize video upload functionality
         */
        initializeVideoUpload: function () {
        },

        /**
         * Initialize gallery functionality
         */
        initializeGallery: function () {
            this.state.galleryImages = [];
        },

        /**
         * Initialize reshare functionality
         */
        initializeReshare: function () {
            this.state.reshareQueue = [];
        },

        /**
         * Handle post type change
         */
        handlePostTypeChange: function () {
            const postType = $('input[name="post_type"]:checked').val();
            this.state.currentPostType = postType;

            // Hide all specific fields
            $('.smo-link-fields, .smo-video-fields, .smo-gallery-fields, .smo-reshare-fields').hide();

            // Show relevant fields based on post type
            switch (postType) {
                case 'link':
                    $('.smo-link-fields').slideDown(300);
                    break;
                case 'video':
                    $('.smo-video-fields').slideDown(300);
                    break;
                case 'gallery':
                    $('.smo-gallery-fields').slideDown(300);
                    break;
                case 'reshare':
                    $('.smo-reshare-fields').slideDown(300);
                    break;
            }
        },

        /**
         * Update character count with platform limits
         */
        updateCharacterCount: function () {
            const content = $('#post_content').val();
            const length = content.length;
            const selectedPlatforms = $('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            let warnings = [];
            let maxExceeded = false;

            selectedPlatforms.forEach((platform) => {
                const limit = this.config.platformLimits[platform];
                if (limit && length > limit) {
                    warnings.push(`${platform}: ${length}/${limit}`);
                    maxExceeded = true;
                }
            });

            const $charCount = $('#char-count');
            if (maxExceeded) {
                $charCount.css('color', '#dc3545').html(`⚠️ ${warnings.join(', ')}`);
            } else if (warnings.length > 0) {
                $charCount.css('color', '#ffc107').html(`${length} characters`);
            } else {
                $charCount.css('color', '#28a745').html(`${length} characters`);
            }
        },

        /**
         * Show emoji picker
         */
        showEmojiPicker: function () {
            const emojis = [
                '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇',
                '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚',
                '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩',
                '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣',
                '🔥', '💯', '✨', '⭐', '🌟', '💫', '🎉', '🎊', '🎈', '🎁',
                '👍', '👎', '👏', '🙌', '👐', '🤝', '🙏', '💪', '✌️', '🤞',
                '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔'
            ];

            if ($('.smo-emoji-picker').length) {
                $('.smo-emoji-picker').remove();
                return;
            }

            let html = '<div class="smo-emoji-picker">';
            emojis.forEach(emoji => {
                html += `<span class="smo-emoji" data-emoji="${emoji}">${emoji}</span>`;
            });
            html += '</div>';

            $('#post_content').after(html);

            $('.smo-emoji').on('click', function () {
                const emoji = $(this).data('emoji');
                const textarea = $('#post_content')[0];
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const text = textarea.value;
                textarea.value = text.slice(0, start) + emoji + text.slice(end);
                textarea.focus();
                textarea.setSelectionRange(start + emoji.length, start + emoji.length);
                $('.smo-emoji-picker').remove();
                SMOEnhancedPost.updateCharacterCount();
            });

            // Close picker when clicking outside
            $(document).one('click', function (e) {
                if (!$(e.target).closest('.smo-emoji-picker, #smo-add-emoji').length) {
                    $('.smo-emoji-picker').remove();
                }
            });
        },

        /**
         * Add link field
         */
        addLinkField: function () {
            const linkCount = $('.smo-link-item').length;

            if (linkCount >= this.config.maxLinks) {
                this.showNotification(`Maximum ${this.config.maxLinks} links allowed per post`, 'warning');
                return;
            }

            const newLink = $(`
                <div class="smo-link-item" style="animation: slideIn 0.3s ease;">
                    <input type="url" name="post_links[]" class="smo-link-input regular-text" 
                           placeholder="https://example.com">
                    <button type="button" class="button smo-remove-link">×</button>
                    <button type="button" class="button smo-preview-link">Preview</button>
                </div>
            `);

            $('#smo-links-list').append(newLink);
            this.updateRemoveButtons();
        },

        /**
         * Remove link field
         */
        removeLinkField: function (e) {
            $(e.currentTarget).closest('.smo-link-item').fadeOut(300, function () {
                $(this).remove();
                SMOEnhancedPost.updateRemoveButtons();
            });
        },

        /**
         * Update remove buttons visibility
         */
        updateRemoveButtons: function () {
            const linkCount = $('.smo-link-item').length;
            $('.smo-remove-link').toggle(linkCount > 1);
        },

        /**
         * Handle link input
         */
        handleLinkInput: function (e) {
            const $input = $(e.currentTarget);
            const url = $input.val();
            const $linkItem = $input.closest('.smo-link-item');

            if (url && this.isValidUrl(url)) {
                $linkItem.find('.smo-preview-link').show();
                $input.removeClass('error').addClass('valid');
            } else {
                $linkItem.find('.smo-preview-link').hide();
                $input.removeClass('valid');
            }
        },

        /**
         * Validate URL
         */
        isValidUrl: function (string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        },

        /**
         * Preview link
         */
        previewLink: function (e) {
            const url = $(e.currentTarget).siblings('.smo-link-input').val();
            if (!url) return;

            this.showLoading('Fetching link preview...');

            $.post(ajaxurl, {
                action: 'smo_get_link_preview',
                nonce: smoChatConfig.nonce,
                url: url
            }, (response) => {
                this.hideLoading();
                if (response.success) {
                    this.showLinkPreviewModal(response.data);
                } else {
                    this.showNotification('Failed to fetch link preview', 'error');
                }
            });
        },

        /**
         * Show link preview modal
         */
        showLinkPreviewModal: function (preview) {
            const html = `
                <div class="smo-modal" id="smo-link-preview-modal">
                    <div class="smo-modal-content">
                        <span class="smo-close">&times;</span>
                        <h3>Link Preview</h3>
                        <div class="smo-link-preview">
                            ${preview.image ? `<img src="${preview.image}" alt="Preview">` : ''}
                            <h4>${preview.title || 'No title'}</h4>
                            <p>${preview.description || 'No description'}</p>
                            <small>${preview.domain || ''}</small>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(html);
            $('#smo-link-preview-modal').fadeIn(300);

            $('.smo-close, .smo-modal').on('click', function (e) {
                if (e.target === this) {
                    $('#smo-link-preview-modal').fadeOut(300, function () {
                        $(this).remove();
                    });
                }
            });
        },

        /**
         * Open media library for single images
         */
        openMediaLibrary: function () {
            const mediaUploader = wp.media({
                title: 'Select Images',
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
                attachments.forEach((attachment) => {
                    this.addMediaItem(attachment);
                });
            });

            mediaUploader.open();
        },

        /**
         * Add media item
         */
        addMediaItem: function (attachment) {
            this.state.selectedImages.push(attachment.id);

            const html = `
                <div class="smo-media-item" data-media-id="${attachment.id}" style="animation: fadeIn 0.3s ease;">
                    <img src="${attachment.url}" alt="${attachment.alt || ''}">
                    <div class="smo-media-actions">
                        <button type="button" class="button button-small smo-edit-media">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </button>
                        <button type="button" class="button button-small smo-remove-media">
                            <span class="dashicons dashicons-no"></span> Remove
                        </button>
                    </div>
                    <input type="hidden" name="media_ids[]" value="${attachment.id}">
                </div>
            `;

            $('#smo-media-list').append(html);
        },

        /**
         * Remove media
         */
        removeMedia: function (e) {
            const $item = $(e.currentTarget).closest('.smo-media-item');
            const mediaId = $item.data('media-id');

            $item.fadeOut(300, function () {
                $(this).remove();
                const index = SMOEnhancedPost.state.selectedImages.indexOf(mediaId);
                if (index > -1) {
                    SMOEnhancedPost.state.selectedImages.splice(index, 1);
                }
            });
        },

        /**
         * Edit media
         */
        editMedia: function (e) {
            const mediaId = $(e.currentTarget).closest('.smo-media-item').data('media-id');
            // Trigger image editor (if available)
            if (typeof SMOImageEditor !== 'undefined') {
                SMOImageEditor.openEditor(mediaId);
            } else {
                this.showNotification('Image editor not available', 'info');
            }
        },

        /**
         * Open gallery library for multiple images
         */
        openGalleryLibrary: function () {
            const currentCount = $('.smo-gallery-item').length;
            const remaining = this.config.maxGalleryImages - currentCount;

            if (remaining <= 0) {
                this.showNotification(`Maximum ${this.config.maxGalleryImages} images allowed in gallery`, 'warning');
                return;
            }

            const mediaUploader = wp.media({
                title: `Select Images for Gallery (${remaining} remaining)`,
                button: {
                    text: 'Add to Gallery'
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', () => {
                const attachments = mediaUploader.state().get('selection').toJSON();
                const toAdd = attachments.slice(0, remaining);

                toAdd.forEach((attachment, index) => {
                    this.addGalleryItem(attachment, currentCount + index);
                });

                if (attachments.length > remaining) {
                    this.showNotification(`Only ${remaining} images were added due to the limit`, 'info');
                }
            });

            mediaUploader.open();
        },

        /**
         * Add gallery item
         */
        addGalleryItem: function (attachment, index) {
            this.state.galleryImages.push(attachment.id);

            const html = `
                <div class="smo-gallery-item smo-media-item" data-media-id="${attachment.id}" 
                     data-gallery-index="${index}" style="animation: fadeIn 0.3s ease;">
                    <img src="${attachment.url}" alt="${attachment.alt || ''}">
                    <div class="smo-gallery-controls">
                        <input type="number" name="gallery_order[]" value="${index + 1}" 
                               min="1" max="${this.config.maxGalleryImages}" 
                               class="smo-gallery-order" title="Display order">
                        <button type="button" class="button button-small smo-remove-gallery-item">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    <input type="hidden" name="gallery_media_ids[]" value="${attachment.id}">
                </div>
            `;

            $('#smo-gallery-list').append(html);
            this.updateGalleryCount();
        },

        /**
         * Remove gallery item
         */
        removeGalleryItem: function (e) {
            const $item = $(e.currentTarget).closest('.smo-gallery-item');
            const mediaId = $item.data('media-id');

            $item.fadeOut(300, function () {
                $(this).remove();
                const index = SMOEnhancedPost.state.galleryImages.indexOf(mediaId);
                if (index > -1) {
                    SMOEnhancedPost.state.galleryImages.splice(index, 1);
                }
                SMOEnhancedPost.updateGalleryCount();
            });
        },

        /**
         * Update gallery count display
         */
        updateGalleryCount: function () {
            const count = $('.smo-gallery-item').length;
            const $label = $('.smo-gallery-fields label').first();
            $label.html(`Image Gallery (${count}/${this.config.maxGalleryImages} images)`);
        },

        /**
         * Handle video URL input
         */
        handleVideoUrlInput: function (e) {
            const url = $(e.currentTarget).val();
            if (url && this.isValidUrl(url)) {
                $('#smo-preview-video').show();
            } else {
                $('#smo-preview-video').hide();
            }
        },

        /**
         * Preview video
         */
        previewVideo: function () {
            const url = $('#video_url').val();
            if (!url) return;

            // Create video preview modal
            const html = `
                <div class="smo-modal" id="smo-video-preview-modal">
                    <div class="smo-modal-content">
                        <span class="smo-close">&times;</span>
                        <h3>Video Preview</h3>
                        <div class="smo-video-preview">
                            <iframe src="${this.getEmbedUrl(url)}" 
                                    width="100%" height="400" frameborder="0" 
                                    allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(html);
            $('#smo-video-preview-modal').fadeIn(300);

            $('.smo-close, .smo-modal').on('click', function (e) {
                if (e.target === this) {
                    $('#smo-video-preview-modal').fadeOut(300, function () {
                        $(this).remove();
                    });
                }
            });
        },

        /**
         * Get embed URL for video
         */
        getEmbedUrl: function (url) {
            // YouTube
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                const videoId = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
                return videoId ? `https://www.youtube.com/embed/${videoId[1]}` : url;
            }
            // Vimeo
            if (url.includes('vimeo.com')) {
                const videoId = url.match(/vimeo\.com\/(\d+)/);
                return videoId ? `https://player.vimeo.com/video/${videoId[1]}` : url;
            }
            return url;
        },

        /**
         * Handle reshare URL input
         */
        handleReshareUrlInput: function (e) {
            const url = $(e.currentTarget).val();
            if (url && this.isValidUrl(url)) {
                $('#smo-preview-reshare').show();
            } else {
                $('#smo-preview-reshare').hide();
            }
        },

        /**
         * Toggle reshare queue settings
         */
        toggleReshareQueue: function (e) {
            const isChecked = $(e.currentTarget).is(':checked');
            $('.smo-queue-settings').toggle(isChecked);
        },

        /**
         * Preview reshare
         */
        previewReshare: function () {
            const url = $('#original_post_url').val();
            if (!url) return;

            this.showNotification('Fetching post preview...', 'info');
            // Implementation would fetch the original post details
        },

        /**
         * Optimize content with AI
         */
        optimizeContent: function () {
            const content = $('#post_content').val();
            const platforms = $('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (!content) {
                this.showNotification('Please enter some content first', 'warning');
                return;
            }

            this.showLoading('Optimizing content with AI...');

            $.post(ajaxurl, {
                action: 'smo_ai_optimize_content',
                nonce: smoChatConfig.nonce,
                content: content,
                platforms: platforms
            }, (response) => {
                this.hideLoading();
                if (response.success) {
                    $('#post_content').val(response.data.optimized_content);
                    this.updateCharacterCount();
                    this.showNotification('Content optimized successfully!', 'success');
                } else {
                    this.showNotification('Failed to optimize content', 'error');
                }
            });
        },

        /**
         * Generate hashtags with AI
         */
        generateHashtags: function () {
            const content = $('#post_content').val();

            if (!content) {
                this.showNotification('Please enter some content first', 'warning');
                return;
            }

            this.showLoading('Generating hashtags...');

            $.post(ajaxurl, {
                action: 'smo_ai_generate_hashtags',
                nonce: smoChatConfig.nonce,
                content: content
            }, (response) => {
                this.hideLoading();
                if (response.success) {
                    $('#post_hashtags').val(response.data.hashtags.join(' '));
                    this.showNotification('Hashtags generated successfully!', 'success');
                } else {
                    this.showNotification('Failed to generate hashtags', 'error');
                }
            });
        },

        /**
         * Get best posting times
         */
        getBestTimes: function () {
            const platforms = $('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (platforms.length === 0) {
                this.showNotification('Please select platforms first', 'warning');
                return;
            }

            this.showLoading('Calculating best times...');

            $.post(ajaxurl, {
                action: 'smo_get_best_posting_times',
                nonce: smoChatConfig.nonce,
                platforms: platforms
            }, (response) => {
                this.hideLoading();
                if (response.success) {
                    this.displayBestTimes(response.data);
                } else {
                    this.showNotification('Failed to get best times', 'error');
                }
            });
        },

        /**
         * Display best posting times
         */
        displayBestTimes: function (times) {
            let html = '<div class="smo-time-suggestions">';
            times.forEach((suggestion) => {
                html += `
                    <div class="smo-time-suggestion" data-time="${suggestion.time}">
                        <strong>${suggestion.platform}</strong>: ${suggestion.time} 
                        <span class="smo-engagement-rate">(${suggestion.engagement}% engagement)</span>
                    </div>
                `;
            });
            html += '</div>';

            $('#smo-time-suggestions').html(html);

            $('.smo-time-suggestion').on('click', function () {
                const time = $(this).data('time');
                const dateTime = new Date();
                const [hours, minutes] = time.split(':');
                dateTime.setHours(parseInt(hours), parseInt(minutes));
                $('#scheduled_time').val(dateTime.toISOString().slice(0, 16));
                $('input[name="schedule_type"][value="scheduled"]').prop('checked', true);
                SMOEnhancedPost.handleScheduleTypeChange();
            });
        },

        /**
         * Handle schedule type change
         */
        handleScheduleTypeChange: function () {
            const scheduleType = $('input[name="schedule_type"]:checked').val();
            $('.smo-schedule-fields').toggle(scheduleType !== 'now');
        },

        /**
         * Load template
         */
        loadTemplate: function () {
            const templateId = $('#post_template').val();
            if (!templateId) return;

            this.showLoading('Loading template...');

            $.post(ajaxurl, {
                action: 'smo_load_template',
                nonce: smoChatConfig.nonce,
                template_id: templateId
            }, (response) => {
                this.hideLoading();
                if (response.success) {
                    const template = response.data;
                    $('#post_title').val(template.name);
                    $('#post_content').val(template.content_template);
                    this.updateCharacterCount();
                    this.showNotification('Template loaded successfully!', 'success');
                } else {
                    this.showNotification('Failed to load template', 'error');
                }
            });
        },

        /**
         * Save template
         */
        saveTemplate: function () {
            const title = $('#post_title').val();
            const content = $('#post_content').val();
            const platforms = $('input[name="post_platforms[]"]:checked').map(function () {
                return this.value;
            }).get();

            if (!content) {
                this.showNotification('Please enter content for the template', 'warning');
                return;
            }

            const templateName = prompt('Template Name:', title || 'New Template');
            if (!templateName) return;

            this.showLoading('Saving template...');

            $.post(ajaxurl, {
                action: 'smo_save_template',
                nonce: smoChatConfig.nonce,
                name: templateName,
                content: content,
                platforms: platforms
            }, (response) => {
                this.hideLoading();
                if (response.success) {
                    this.showNotification('Template saved successfully!', 'success');
                } else {
                    this.showNotification('Failed to save template', 'error');
                }
            });
        },

        /**
         * Preview post
         */
        previewPost: function () {
            const formData = this.collectFormData();

            this.showLoading('Generating preview...');

            $.post(ajaxurl, {
                action: 'smo_preview_post',
                nonce: smoChatConfig.nonce,
                data: formData
            }, (response) => {
                this.hideLoading();
                if (response.success) {
                    this.showPreviewModal(response.data.html);
                } else {
                    this.showNotification('Failed to generate preview', 'error');
                }
            });
        },

        /**
         * Show preview modal
         */
        showPreviewModal: function (html) {
            const modal = `
                <div class="smo-modal" id="smo-preview-modal">
                    <div class="smo-modal-content smo-preview-content">
                        <span class="smo-close">&times;</span>
                        <h3>Post Preview</h3>
                        <div class="smo-preview-body">${html}</div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            $('#smo-preview-modal').fadeIn(300);

            $('.smo-close, .smo-modal').on('click', function (e) {
                if (e.target === this) {
                    $('#smo-preview-modal').fadeOut(300, function () {
                        $(this).remove();
                    });
                }
            });
        },

        /**
         * Collect form data
         */
        collectFormData: function () {
            return {
                title: $('#post_title').val(),
                content: $('#post_content').val(),
                post_type: $('input[name="post_type"]:checked').val(),
                platforms: $('input[name="post_platforms[]"]:checked').map(function () {
                    return this.value;
                }).get(),
                hashtags: $('#post_hashtags').val(),
                mentions: $('#post_mentions').val(),
                schedule_type: $('input[name="schedule_type"]:checked').val(),
                scheduled_time: $('#scheduled_time').val(),
                priority: $('#post_priority').val(),
                links: $('input[name="post_links[]"]').map(function () {
                    return this.value;
                }).get().filter(v => v),
                video_url: $('#video_url').val(),
                media_ids: this.state.selectedImages,
                gallery_ids: this.state.galleryImages,
                reshare_url: $('#original_post_url').val(),
                reshare_settings: {
                    add_to_queue: $('input[name="add_to_queue"]').is(':checked'),
                    max_reshares: $('input[name="max_reshares"]').val(),
                    interval: $('input[name="reshare_interval"]').val()
                }
            };
        },

        /**
         * Reset form
         */
        resetForm: function () {
            if (!confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) {
                return;
            }

            $('#smo-enhanced-create-post-form')[0].reset();
            $('#smo-media-list, #smo-gallery-list').empty();
            this.state.selectedImages = [];
            this.state.galleryImages = [];
            this.updateCharacterCount();
            this.handlePostTypeChange();
            this.handleScheduleTypeChange();
            this.showNotification('Form reset successfully', 'info');
        },

        /**
         * Handle form submit
         */
        handleFormSubmit: function (e) {
            const formData = this.collectFormData();

            if (!formData.content) {
                this.showNotification('Please enter post content', 'error');
                e.preventDefault();
                return false;
            }

            if (formData.platforms.length === 0) {
                this.showNotification('Please select at least one platform', 'error');
                e.preventDefault();
                return false;
            }

            // Add loading state
            $('#submit').prop('disabled', true).val('Scheduling...');
            this.showLoading('Scheduling your post...');

            return true;
        },

        /**
         * Show loading overlay
         */
        showLoading: function (message) {
            const html = `
                <div class="smo-loading-overlay">
                    <div class="smo-loading-spinner"></div>
                    <p>${message}</p>
                </div>
            `;
            $('body').append(html);
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function () {
            $('.smo-loading-overlay').fadeOut(300, function () {
                $(this).remove();
            });
        },

        /**
         * Show notification
         */
        showNotification: function (message, type = 'info') {
            const icons = {
                success: '✓',
                error: '✗',
                warning: '⚠',
                info: 'ℹ'
            };

            const html = `
                <div class="smo-notification smo-notification-${type}">
                    <span class="smo-notification-icon">${icons[type]}</span>
                    <span class="smo-notification-message">${message}</span>
                </div>
            `;

            $('body').append(html);

            setTimeout(() => {
                $('.smo-notification').fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        if ($('#smo-enhanced-create-post-form').length) {
            SMOEnhancedPost.init();
        }
    });

    // Expose to global scope
    window.SMOEnhancedPost = SMOEnhancedPost;

})(jQuery);
