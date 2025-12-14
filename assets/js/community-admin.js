/**
 * Community Admin JavaScript
 * Handles the community marketplace interface functionality
 */

(function($) {
    'use strict';

    // Community App State
    const CommunityApp = {
        templates: [],
        reputationData: {},
        activeTab: 'templates',
        filters: {
            category: 'all',
            verifiedOnly: false,
            sortBy: 'installs'
        }
    };

    /**
     * Initialize the community app
     */
    function initCommunityApp() {
        loadCommunityData();
        bindEvents();
        renderMarketplace();
    }

    /**
     * Load community data via AJAX
     */
    function loadCommunityData() {
        $.ajax({
            url: smoCommunityData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smo_get_community_data',
                nonce: smoCommunityData.nonce
            },
            success: function(response) {
                if (response.success) {
                    CommunityApp.templates = response.data.templates || {};
                    CommunityApp.reputationData = response.data.reputation || {};
                    renderMarketplace();
                }
            },
            error: function() {
                showNotification('Error loading community data', 'error');
            }
        });
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Template installation
        $(document).on('click', '.smo-install-template', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            installTemplate(templateId);
        });

        // Template rating
        $(document).on('click', '.smo-rate-template', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            showRatingModal(templateId);
        });

        // Filter changes
        $(document).on('change', '#smo-category-filter, #smo-verified-filter, #smo-sort-filter', function() {
            updateFilters();
            renderMarketplace();
            announceFilterChange();
        });

        // Search functionality
        $(document).on('input', '#smo-template-search', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterTemplates(searchTerm);
            announceSearchResults(searchTerm);
        });

        // Create template button
        $(document).on('click', '#smo-create-template', function() {
            showCreateTemplateModal();
        });

        // Import template button
        $(document).on('click', '#smo-import-template', function() {
            showImportTemplateModal();
        });

        // Keyboard navigation for template cards
        $(document).on('keydown', '.smo-template-card', handleTemplateCardKeydown);

        // Modal accessibility
        $(document).on('keydown', '.smo-modal-overlay', handleModalKeydown);
    }

    /**
     * Handle keyboard navigation for template cards
     */
    function handleTemplateCardKeydown(e) {
        const $card = $(e.target).closest('.smo-template-card');
        const $cards = $('.smo-template-card:visible');
        const currentIndex = $cards.index($card);

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                const nextIndex = currentIndex < $cards.length - 1 ? currentIndex + 1 : 0;
                $cards.eq(nextIndex).find('.smo-install-template, .smo-rate-template').first().focus();
                break;
            case 'ArrowUp':
                e.preventDefault();
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : $cards.length - 1;
                $cards.eq(prevIndex).find('.smo-install-template, .smo-rate-template').first().focus();
                break;
        }
    }

    /**
     * Handle modal keyboard navigation
     */
    function handleModalKeydown(e) {
        if (e.key === 'Escape') {
            const $modal = $(e.target).closest('.smo-modal-overlay');
            if ($modal.length) {
                $modal.remove();
                announceToScreenReader('Modal closed');
            }
        }
    }

    /**
     * Announce filter changes to screen readers
     */
    function announceFilterChange() {
        const category = $('#smo-category-filter').val();
        const verified = $('#smo-verified-filter').is(':checked');
        const sort = $('#smo-sort-filter').val();

        let announcement = 'Filters updated.';
        if (category !== 'all') announcement += ` Category: ${category}.`;
        if (verified) announcement += ' Showing verified templates only.';
        announcement += ` Sorted by: ${sort}.`;

        announceToScreenReader(announcement);
    }

    /**
     * Announce search results
     */
    function announceSearchResults(searchTerm) {
        const visibleCards = $('.smo-template-card:visible').length;
        const announcement = searchTerm ?
            `Search results for "${searchTerm}": ${visibleCards} templates found.` :
            `Showing all ${visibleCards} templates.`;
        announceToScreenReader(announcement);
    }

    /**
     * Screen reader announcement utility
     */
    function announceToScreenReader(message) {
        const announcement = $('<div>')
            .attr('aria-live', 'polite')
            .attr('aria-atomic', 'true')
            .css({
                position: 'absolute',
                left: '-10000px',
                width: '1px',
                height: '1px',
                overflow: 'hidden'
            })
            .text(message);

        $('body').append(announcement);
        setTimeout(() => announcement.remove(), 1000);
    }

    /**
     * Install a template
     */
    function installTemplate(templateId) {
        const button = $(`.smo-install-template[data-template-id="${templateId}"]`);
        const originalText = button.text();
        
        button.text('Installing...').prop('disabled', true);

        $.ajax({
            url: smoCommunityData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smo_install_template_from_marketplace',
                template_id: templateId,
                nonce: smoCommunityData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(`Template "${templateId}" installed successfully!`, 'success');
                    button.text('Installed').removeClass('smo-install-template').addClass('smo-installed');
                } else {
                    showNotification(`Installation failed: ${response.data}`, 'error');
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showNotification('Installation failed. Please try again.', 'error');
                button.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Update filters
     */
    function updateFilters() {
        CommunityApp.filters.category = $('#smo-category-filter').val();
        CommunityApp.filters.verifiedOnly = $('#smo-verified-filter').is(':checked');
        CommunityApp.filters.sortBy = $('#smo-sort-filter').val();
    }

    /**
     * Filter templates by search term
     */
    function filterTemplates(searchTerm) {
        const filteredTemplates = {};
        
        Object.keys(CommunityApp.templates).forEach(templateId => {
            const template = CommunityApp.templates[templateId];
            const searchableText = `${template.name} ${template.description} ${template.author}`.toLowerCase();
            
            if (searchableText.includes(searchTerm)) {
                filteredTemplates[templateId] = template;
            }
        });
        
        renderTemplatesList(filteredTemplates);
    }

    /**
     * Render the main marketplace
     */
    function renderMarketplace() {
        const container = $('#smo-community-app');
        if (container.length === 0) return;

        container.html(`
            <div class="smo-marketplace-header">
                <div class="smo-search-filters" role="search">
                    <label for="smo-template-search" class="sr-only">Search templates</label>
                    <input type="text" id="smo-template-search" placeholder="Search templates..." class="regular-text" aria-describedby="search-help">
                    <span id="search-help" class="sr-only">Type to search through available templates</span>

                    <label for="smo-category-filter" class="sr-only">Filter by category</label>
                    <select id="smo-category-filter" aria-describedby="category-help">
                        <option value="all">All Categories</option>
                        <option value="marketing">Marketing</option>
                        <option value="education">Education</option>
                        <option value="engagement">Engagement</option>
                        <option value="announcement">Announcements</option>
                    </select>
                    <span id="category-help" class="sr-only">Filter templates by category</span>

                    <label for="smo-verified-filter">
                        <input type="checkbox" id="smo-verified-filter" aria-describedby="verified-help">
                        Verified only
                    </label>
                    <span id="verified-help" class="sr-only">Show only verified templates</span>

                    <label for="smo-sort-filter" class="sr-only">Sort templates</label>
                    <select id="smo-sort-filter" aria-describedby="sort-help">
                        <option value="installs">Most Installed</option>
                        <option value="rating">Highest Rated</option>
                        <option value="newest">Newest</option>
                        <option value="name">Name</option>
                    </select>
                    <span id="sort-help" class="sr-only">Sort templates by selected criteria</span>
                </div>
                <div class="smo-marketplace-stats" aria-live="polite">
                    <span id="smo-templates-count">${Object.keys(CommunityApp.templates).length} templates available</span>
                </div>
            </div>
            <div class="smo-templates-grid" id="smo-templates-container" role="grid" aria-label="Template marketplace">
                ${renderTemplatesList(CommunityApp.templates)}
            </div>
        `);
    }

    /**
     * Render templates list
     */
    function renderTemplatesList(templates) {
        if (Object.keys(templates).length === 0) {
            return '<div class="smo-no-templates">No templates found matching your criteria.</div>';
        }

        let sortedTemplates = Object.entries(templates);
        
        // Apply filters
        sortedTemplates = sortedTemplates.filter(([id, template]) => {
            if (CommunityApp.filters.category !== 'all' && template.category !== CommunityApp.filters.category) {
                return false;
            }
            
            const reputation = CommunityApp.reputationData[id];
            if (CommunityApp.filters.verifiedOnly && !reputation?.verified) {
                return false;
            }
            
            return true;
        });

        // Apply sorting
        sortedTemplates.sort(([idA, templateA], [idB, templateB]) => {
            const reputationA = CommunityApp.reputationData[idA] || {};
            const reputationB = CommunityApp.reputationData[idB] || {};
            
            switch (CommunityApp.filters.sortBy) {
                case 'installs':
                    return (reputationB.install_count || 0) - (reputationA.install_count || 0);
                case 'rating':
                    return (reputationB.rating || 0) - (reputationA.rating || 0);
                case 'newest':
                    return new Date(templateB.version || 0) - new Date(templateA.version || 0);
                case 'name':
                    return templateA.name.localeCompare(templateB.name);
                default:
                    return 0;
            }
        });

        return sortedTemplates.map(([id, template]) => renderTemplateCard(id, template)).join('');
    }

    /**
     * Render a single template card
     */
    function renderTemplateCard(templateId, template) {
        const reputation = CommunityApp.reputationData[templateId] || {};
        const isInstalled = Object.keys(smoCommunityData.installedTemplates || {}).includes(templateId);
        const verifiedBadge = reputation.verified ? '<span class="smo-verified-badge" aria-label="Verified template">✓ Verified</span>' : '';

        return `
            <div class="smo-template-card" data-template-id="${templateId}" role="article" aria-labelledby="template-title-${templateId}">
                <div class="smo-template-header">
                    <h3 id="template-title-${templateId}">${escapeHtml(template.name)}</h3>
                    ${verifiedBadge}
                </div>
                <div class="smo-template-meta">
                    <span class="smo-template-version">v${template.version || '1.0'}</span>
                    <span class="smo-template-author">by ${escapeHtml(template.author || 'Unknown')}</span>
                    <span class="smo-template-category">${escapeHtml(template.category || 'General')}</span>
                </div>
                <div class="smo-template-description">
                    ${escapeHtml(template.description || 'No description available')}
                </div>
                <div class="smo-template-stats">
                    <span class="smo-stat" aria-label="${reputation.install_count || 0} installations">
                        <strong>${reputation.install_count || 0}</strong> installs
                    </span>
                    <span class="smo-stat" aria-label="${reputation.success_rate || 100}% success rate">
                        <strong>${reputation.success_rate || 100}%</strong> success rate
                    </span>
                    <span class="smo-stat" aria-label="Estimated duration: ${template.estimated_duration || 'N/A'}">
                        <strong>${template.estimated_duration || 'N/A'}</strong> duration
                    </span>
                </div>
                <div class="smo-template-tags" role="list" aria-label="Template tags">
                    ${(template.tags || []).map(tag => `<span class="smo-tag" role="listitem">${escapeHtml(tag)}</span>`).join('')}
                </div>
                <div class="smo-template-actions" role="group" aria-label="Template actions">
                    ${isInstalled ?
                        '<button class="button smo-installed" disabled aria-label="Template already installed">Installed</button>' :
                        `<button class="button button-primary smo-install-template" data-template-id="${templateId}" aria-label="Install ${escapeHtml(template.name)} template">Install</button>`
                    }
                    <button class="button smo-rate-template" data-template-id="${templateId}" aria-label="Rate ${escapeHtml(template.name)} template">
                        ⭐ Rate
                    </button>
                    <button class="button" onclick="viewTemplateDetails('${templateId}')" aria-label="View details for ${escapeHtml(template.name)} template">
                        Details
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Show rating modal
     */
    function showRatingModal(templateId) {
        const modal = $(`
            <div class="smo-modal-overlay">
                <div class="smo-modal">
                    <div class="smo-modal-header">
                        <h3>Rate Template</h3>
                        <button class="smo-modal-close">&times;</button>
                    </div>
                    <div class="smo-modal-body">
                        <div class="smo-rating-stars">
                            ${[1,2,3,4,5].map(i => 
                                `<button class="smo-star" data-rating="${i}">⭐</button>`
                            ).join('')}
                        </div>
                        <textarea placeholder="Write your review (optional)" rows="4" class="smo-review-text"></textarea>
                    </div>
                    <div class="smo-modal-footer">
                        <button class="button button-primary" id="smo-submit-rating">Submit Rating</button>
                        <button class="button" id="smo-cancel-rating">Cancel</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);
        let selectedRating = 0;

        // Handle star rating
        modal.find('.smo-star').on('click', function() {
            selectedRating = $(this).data('rating');
            modal.find('.smo-star').each(function(index) {
                if (index < selectedRating) {
                    $(this).text('⭐');
                } else {
                    $(this).text('☆');
                }
            });
        });

        // Handle modal close
        modal.find('.smo-modal-close, #smo-cancel-rating').on('click', function() {
            modal.remove();
        });

        // Handle rating submission
        $('#smo-submit-rating').on('click', function() {
            const review = modal.find('.smo-review-text').val();
            
            $.ajax({
                url: smoCommunityData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smo_rate_template',
                    template_id: templateId,
                    rating: selectedRating,
                    review: review,
                    nonce: smoCommunityData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('Rating submitted successfully!', 'success');
                        modal.remove();
                    } else {
                        showNotification('Failed to submit rating', 'error');
                    }
                }
            });
        });
    }

    /**
     * Show create template modal
     */
    function showCreateTemplateModal() {
        const modal = $(`
            <div class="smo-modal-overlay">
                <div class="smo-modal large">
                    <div class="smo-modal-header">
                        <h3>Create New Template</h3>
                        <button class="smo-modal-close">&times;</button>
                    </div>
                    <div class="smo-modal-body">
                        <div class="smo-form-group">
                            <label>Template Name *</label>
                            <input type="text" id="smo-template-name" class="regular-text" required>
                        </div>
                        <div class="smo-form-group">
                            <label>Template ID *</label>
                            <input type="text" id="smo-template-id" class="regular-text" required>
                            <small>Only lowercase letters, numbers, and underscores</small>
                        </div>
                        <div class="smo-form-group">
                            <label>Description *</label>
                            <textarea id="smo-template-description" rows="3" required></textarea>
                        </div>
                        <div class="smo-form-group">
                            <label>Category</label>
                            <select id="smo-template-category">
                                <option value="marketing">Marketing</option>
                                <option value="education">Education</option>
                                <option value="engagement">Engagement</option>
                                <option value="announcement">Announcement</option>
                            </select>
                        </div>
                        <div class="smo-form-group">
                            <label>JSON Content *</label>
                            <textarea id="smo-template-json" rows="15" class="code-textarea" required></textarea>
                            <small>Paste your template JSON here</small>
                        </div>
                    </div>
                    <div class="smo-modal-footer">
                        <button class="button button-primary" id="smo-create-template-submit">Create Template</button>
                        <button class="button" id="smo-cancel-create">Cancel</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);

        // Handle modal close
        modal.find('.smo-modal-close, #smo-cancel-create').on('click', function() {
            modal.remove();
        });

        // Handle template creation
        $('#smo-create-template-submit').on('click', function() {
            const name = $('#smo-template-name').val();
            const templateId = $('#smo-template-id').val();
            const description = $('#smo-template-description').val();
            const category = $('#smo-template-category').val();
            const jsonContent = $('#smo-template-json').val();

            try {
                const templateData = JSON.parse(jsonContent);
                // Add missing fields
                templateData.template_id = templateId;
                templateData.name = name;
                templateData.description = description;
                templateData.category = category;
                templateData.author = 'Current User'; // Could get from WordPress
                templateData.version = templateData.version || '1.0.0';

                $.ajax({
                    url: smoCommunityData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smo_create_template',
                        template_data: JSON.stringify(templateData),
                        nonce: smoCommunityData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Template created successfully!', 'success');
                            modal.remove();
                            loadCommunityData(); // Refresh data
                        } else {
                            showNotification(`Creation failed: ${response.data}`, 'error');
                        }
                    }
                });
            } catch (e) {
                showNotification('Invalid JSON format', 'error');
            }
        });
    }

    /**
     * Show import template modal
     */
    function showImportTemplateModal() {
        const modal = $(`
            <div class="smo-modal-overlay">
                <div class="smo-modal">
                    <div class="smo-modal-header">
                        <h3>Import Template</h3>
                        <button class="smo-modal-close">&times;</button>
                    </div>
                    <div class="smo-modal-body">
                        <div class="smo-form-group">
                            <label>Template JSON File</label>
                            <input type="file" id="smo-template-file" accept=".json" required>
                            <small>Upload a template JSON file</small>
                        </div>
                    </div>
                    <div class="smo-modal-footer">
                        <button class="button button-primary" id="smo-import-template-submit">Import Template</button>
                        <button class="button" id="smo-cancel-import">Cancel</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);

        // Handle modal close
        modal.find('.smo-modal-close, #smo-cancel-import').on('click', function() {
            modal.remove();
        });

        // Handle template import
        $('#smo-import-template-submit').on('click', function() {
            const fileInput = $('#smo-template-file')[0];
            if (!fileInput.files[0]) {
                showNotification('Please select a file', 'error');
                return;
            }

            const file = fileInput.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                try {
                    const templateData = JSON.parse(e.target.result);
                    
                    $.ajax({
                        url: smoCommunityData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'smo_create_template',
                            template_data: JSON.stringify(templateData),
                            nonce: smoCommunityData.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification('Template imported successfully!', 'success');
                                modal.remove();
                                loadCommunityData(); // Refresh data
                            } else {
                                showNotification(`Import failed: ${response.data}`, 'error');
                            }
                        }
                    });
                } catch (e) {
                    showNotification('Invalid JSON file', 'error');
                }
            };

            reader.readAsText(file);
        });
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="smo-notification smo-notification-${type}" role="alert" aria-live="assertive">
                ${escapeHtml(message)}
                <button class="smo-notification-close" aria-label="Close notification">&times;</button>
            </div>
        `);

        $('body').append(notification);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);

        // Close button
        notification.find('.smo-notification-close').on('click', function() {
            notification.fadeOut(() => notification.remove());
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Global function for template details (called from card buttons)
     */
    window.viewTemplateDetails = function(templateId) {
        const template = CommunityApp.templates[templateId];
        if (!template) return;

        const modal = $(`
            <div class="smo-modal-overlay">
                <div class="smo-modal large">
                    <div class="smo-modal-header">
                        <h3>${escapeHtml(template.name)}</h3>
                        <button class="smo-modal-close">&times;</button>
                    </div>
                    <div class="smo-modal-body">
                        <div class="smo-template-detail-section">
                            <h4>Description</h4>
                            <p>${escapeHtml(template.description || 'No description available')}</p>
                        </div>
                        <div class="smo-template-detail-section">
                            <h4>Posts Preview</h4>
                            <div class="smo-posts-preview">
                                ${renderPostsPreview(template.posts || [])}
                            </div>
                        </div>
                        <div class="smo-template-detail-section">
                            <h4>Requirements</h4>
                            <ul>
                                <li><strong>Platforms:</strong> ${(template.requirements?.platforms_needed || []).join(', ')}</li>
                                <li><strong>Duration:</strong> ${template.estimated_duration || 'N/A'}</li>
                                <li><strong>Difficulty:</strong> ${template.difficulty || 'N/A'}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);

        modal.find('.smo-modal-close').on('click', function() {
            modal.remove();
        });
    };

    /**
     * Render posts preview
     */
    function renderPostsPreview(posts) {
        if (posts.length === 0) return '<p>No posts defined</p>';

        return posts.slice(0, 3).map(post => `
            <div class="smo-post-preview">
                <div class="smo-post-meta">
                    <strong>Day ${post.day}</strong> - ${post.platforms.join(', ')}
                </div>
                <div class="smo-post-content">
                    "${escapeHtml(post.content_template.substring(0, 100))}${post.content_template.length > 100 ? '...' : ''}"
                </div>
            </div>
        `).join('') + (posts.length > 3 ? `<p><em>...and ${posts.length - 3} more posts</em></p>` : '');
    }

    /**
     * Initialize templates page
     */
    function initTemplatesPage() {
        loadTemplatesList();
        bindTemplatePageEvents();
    }

    /**
     * Load templates list for templates page
     */
    function loadTemplatesList() {
        const container = $('#smo-templates-list');
        if (!container.length) return;

        // Get installed templates
        const installedTemplates = smoCommunityData.installedTemplates || {};

        // Get available templates
        $.ajax({
            url: smoCommunityData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smo_get_community_data',
                nonce: smoCommunityData.nonce
            },
            success: function(response) {
                if (response.success) {
                    const availableTemplates = response.data.templates || {};
                    renderTemplatesPageList(availableTemplates, installedTemplates);
                }
            },
            error: function() {
                showNotification('Error loading templates', 'error');
            }
        });
    }

    /**
     * Render templates list for templates page
     */
    function renderTemplatesPageList(availableTemplates, installedTemplates) {
        const container = $('#smo-templates-list');

        if (Object.keys(availableTemplates).length === 0) {
            container.html('<p>No templates available.</p>');
            return;
        }

        let html = '<div class="smo-templates-grid">';

        Object.keys(availableTemplates).forEach(templateId => {
            const template = availableTemplates[templateId];
            const isInstalled = installedTemplates.hasOwnProperty(templateId);

            html += `
                <div class="smo-template-card">
                    <div class="smo-template-header">
                        <h3>${escapeHtml(template.name)}</h3>
                        ${isInstalled ? '<span class="smo-installed-badge">Installed</span>' : ''}
                    </div>
                    <div class="smo-template-meta">
                        <span>v${template.version || '1.0'}</span>
                        <span>by ${escapeHtml(template.author || 'Unknown')}</span>
                    </div>
                    <div class="smo-template-description">
                        ${escapeHtml(template.description || 'No description')}
                    </div>
                    <div class="smo-template-actions">
                        ${isInstalled ?
                            '<button class="button smo-uninstall-template" data-template-id="' + templateId + '">Uninstall</button>' :
                            '<button class="button button-primary smo-install-template" data-template-id="' + templateId + '">Install</button>'
                        }
                        <button class="button" onclick="viewTemplateDetails('${templateId}')">Details</button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.html(html);
    }

    /**
     * Bind events for templates page
     */
    function bindTemplatePageEvents() {
        // Template installation from templates page
        $(document).on('click', '.smo-install-template', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            installTemplateFromPage(templateId);
        });

        // Template uninstallation
        $(document).on('click', '.smo-uninstall-template', function(e) {
            e.preventDefault();
            const templateId = $(this).data('template-id');
            uninstallTemplate(templateId);
        });
    }

    /**
     * Install template from templates page
     */
    function installTemplateFromPage(templateId) {
        const button = $(`.smo-install-template[data-template-id="${templateId}"]`);
        const originalText = button.text();

        button.text('Installing...').prop('disabled', true);

        $.ajax({
            url: smoCommunityData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smo_install_template_from_marketplace',
                template_id: templateId,
                nonce: smoCommunityData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(`Template "${templateId}" installed successfully!`, 'success');
                    button.text('Installed').removeClass('smo-install-template').addClass('smo-uninstall-template');
                    loadTemplatesList(); // Refresh list
                } else {
                    showNotification(`Installation failed: ${response.data}`, 'error');
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showNotification('Installation failed. Please try again.', 'error');
                button.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Uninstall template
     */
    function uninstallTemplate(templateId) {
        if (!confirm('Are you sure you want to uninstall this template?')) {
            return;
        }

        const button = $(`.smo-uninstall-template[data-template-id="${templateId}"]`);
        const originalText = button.text();

        button.text('Uninstalling...').prop('disabled', true);

        $.ajax({
            url: smoCommunityData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smo_uninstall_template',
                template_id: templateId,
                nonce: smoCommunityData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(`Template "${templateId}" uninstalled successfully!`, 'success');
                    loadTemplatesList(); // Refresh list
                } else {
                    showNotification(`Uninstallation failed: ${response.data}`, 'error');
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showNotification('Uninstallation failed. Please try again.', 'error');
                button.text(originalText).prop('disabled', false);
            }
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#smo-community-app').length) {
            initCommunityApp();
        }
        if ($('#smo-templates-list').length) {
            initTemplatesPage();
        }
    });

})(jQuery);