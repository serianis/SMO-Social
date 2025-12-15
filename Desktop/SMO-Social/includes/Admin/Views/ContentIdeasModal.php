<?php
/**
 * Content Ideas Modal Component
 * Full CRUD operations for content ideas management
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Ideas Modal View
 */
class ContentIdeasModal {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_get_content_ideas_modal', array($this, 'ajax_get_content_ideas'));
        add_action('wp_ajax_smo_create_content_idea', array($this, 'ajax_create_idea'));
        add_action('wp_ajax_smo_update_content_idea', array($this, 'ajax_update_idea'));
        add_action('wp_ajax_smo_delete_content_idea', array($this, 'ajax_delete_idea'));
        add_action('wp_ajax_smo_bulk_ideas_action', array($this, 'ajax_bulk_action'));
    }
    
    /**
     * Get content ideas modal HTML
     */
    public function get_modal_html() {
        ob_start();
        ?>
        <div id="smo-modal-content-ideas" class="smo-modal">
            <div class="smo-modal-content large-modal">
                <span class="smo-modal-close">&times;</span>
                <div class="smo-modal-header">
                    <h3><?php _e('Content Ideas Manager', 'smo-social'); ?></h3>
                    <div class="smo-modal-actions">
                        <button type="button" class="button button-primary" id="smo-add-new-idea">
                            <?php _e('Add New Idea', 'smo-social'); ?>
                        </button>
                        <button type="button" class="button" id="smo-refresh-ideas">
                            <?php _e('Refresh', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="smo-modal-body">
                    <!-- Filter Controls -->
                    <div class="smo-filters-section">
                        <div class="smo-filters-row">
                            <div class="smo-filter-group">
                                <label><?php _e('Status:', 'smo-social'); ?></label>
                                <select id="smo-filter-status">
                                    <option value=""><?php _e('All Statuses', 'smo-social'); ?></option>
                                    <option value="idea"><?php _e('Ideas', 'smo-social'); ?></option>
                                    <option value="draft"><?php _e('Drafts', 'smo-social'); ?></option>
                                    <option value="scheduled"><?php _e('Scheduled', 'smo-social'); ?></option>
                                    <option value="published"><?php _e('Published', 'smo-social'); ?></option>
                                </select>
                            </div>
                            
                            <div class="smo-filter-group">
                                <label><?php _e('Priority:', 'smo-social'); ?></label>
                                <select id="smo-filter-priority">
                                    <option value=""><?php _e('All Priorities', 'smo-social'); ?></option>
                                    <option value="high"><?php _e('High', 'smo-social'); ?></option>
                                    <option value="medium"><?php _e('Medium', 'smo-social'); ?></option>
                                    <option value="low"><?php _e('Low', 'smo-social'); ?></option>
                                </select>
                            </div>
                            
                            <div class="smo-filter-group">
                                <label><?php _e('Type:', 'smo-social'); ?></label>
                                <select id="smo-filter-type">
                                    <option value=""><?php _e('All Types', 'smo-social'); ?></option>
                                    <option value="post"><?php _e('Posts', 'smo-social'); ?></option>
                                    <option value="story"><?php _e('Stories', 'smo-social'); ?></option>
                                    <option value="video"><?php _e('Videos', 'smo-social'); ?></option>
                                    <option value="campaign"><?php _e('Campaigns', 'smo-social'); ?></option>
                                </select>
                            </div>
                            
                            <div class="smo-search-group">
                                <input type="text" id="smo-search-ideas" placeholder="<?php _e('Search ideas...', 'smo-social'); ?>">
                                <button type="button" class="button" id="smo-search-btn"><?php _e('Search', 'smo-social'); ?></button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bulk Actions -->
                    <div class="smo-bulk-actions" style="display: none;">
                        <select id="smo-bulk-select">
                            <option value=""><?php _e('Bulk Actions', 'smo-social'); ?></option>
                            <option value="delete"><?php _e('Delete', 'smo-social'); ?></option>
                            <option value="change_status"><?php _e('Change Status', 'smo-social'); ?></option>
                            <option value="change_priority"><?php _e('Change Priority', 'smo-social'); ?></option>
                        </select>
                        <button type="button" class="button" id="smo-apply-bulk"><?php _e('Apply', 'smo-social'); ?></button>
                        <span class="smo-selected-count"></span>
                    </div>
                    
                    <!-- Ideas Grid -->
                    <div class="smo-ideas-grid" id="smo-ideas-container">
                        <!-- Ideas will be loaded here via AJAX -->
                        <div class="smo-loading-indicator">
                            <div class="smo-spinner"></div>
                            <p><?php _e('Loading content ideas...', 'smo-social'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="smo-pagination" id="smo-ideas-pagination">
                        <!-- Pagination will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add/Edit Idea Modal -->
        <div id="smo-modal-idea-form" class="smo-modal">
            <div class="smo-modal-content">
                <span class="smo-modal-close">&times;</span>
                <h3 id="smo-form-title"><?php _e('Add New Idea', 'smo-social'); ?></h3>
                <div class="smo-modal-body">
                    <form id="smo-idea-form" class="smo-form">
                        <input type="hidden" id="smo-idea-id" value="">
                        
                        <div class="smo-form-grid two-columns">
                            <div class="smo-form-field required">
                                <label for="smo-idea-title" class="smo-form-label"><?php _e('Title', 'smo-social'); ?></label>
                                <input type="text" id="smo-idea-title" class="smo-input" required 
                                       aria-describedby="smo-idea-title-help">
                                <p id="smo-idea-title-help" class="smo-form-help">
                                    <span class="icon">ℹ️</span>
                                    <?php _e('Give your content idea a descriptive title', 'smo-social'); ?>
                                </p>
                            </div>
                            
                            <div class="smo-form-field">
                                <label for="smo-idea-type" class="smo-form-label"><?php _e('Content Type', 'smo-social'); ?></label>
                                <select id="smo-idea-type" class="smo-select">
                                    <option value="post"><?php _e('Post', 'smo-social'); ?></option>
                                    <option value="story"><?php _e('Story', 'smo-social'); ?></option>
                                    <option value="video"><?php _e('Video', 'smo-social'); ?></option>
                                    <option value="campaign"><?php _e('Campaign', 'smo-social'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="smo-form-field">
                            <label for="smo-idea-description" class="smo-form-label"><?php _e('Description', 'smo-social'); ?></label>
                            <textarea id="smo-idea-description" class="smo-textarea" rows="4"
                                      placeholder="<?php _e('Describe your content idea...', 'smo-social'); ?>"></textarea>
                        </div>
                        
                        <div class="smo-form-grid two-columns">
                            <div class="smo-form-field">
                                <label for="smo-idea-priority" class="smo-form-label"><?php _e('Priority', 'smo-social'); ?></label>
                                <select id="smo-idea-priority" class="smo-select">
                                    <option value="low"><?php _e('Low', 'smo-social'); ?></option>
                                    <option value="medium"><?php _e('Medium', 'smo-social'); ?></option>
                                    <option value="high"><?php _e('High', 'smo-social'); ?></option>
                                </select>
                            </div>
                            
                            <div class="smo-form-field">
                                <label for="smo-idea-status" class="smo-form-label"><?php _e('Status', 'smo-social'); ?></label>
                                <select id="smo-idea-status" class="smo-select">
                                    <option value="idea"><?php _e('Idea', 'smo-social'); ?></option>
                                    <option value="draft"><?php _e('Draft', 'smo-social'); ?></option>
                                    <option value="scheduled"><?php _e('Scheduled', 'smo-social'); ?></option>
                                    <option value="published"><?php _e('Published', 'smo-social'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="smo-form-field">
                            <label for="smo-idea-scheduled" class="smo-form-label"><?php _e('Scheduled Date', 'smo-social'); ?></label>
                            <input type="datetime-local" id="smo-idea-scheduled" class="smo-input">
                            <p class="smo-form-help">
                                <span class="icon">📅</span>
                                <?php _e('Optional: Set a schedule for this content', 'smo-social'); ?>
                            </p>
                        </div>
                        
                        <div class="smo-form-section">
                            <h4 class="smo-form-section-title"><?php _e('Target Platforms', 'smo-social'); ?></h4>
                            <div class="smo-form-grid three-columns">
                                <div class="smo-form-field">
                                    <label class="smo-checkbox">
                                        <input type="checkbox" value="facebook" class="smo-input">
                                        <span class="smo-form-label">Facebook</span>
                                    </label>
                                </div>
                                <div class="smo-form-field">
                                    <label class="smo-checkbox">
                                        <input type="checkbox" value="instagram" class="smo-input">
                                        <span class="smo-form-label">Instagram</span>
                                    </label>
                                </div>
                                <div class="smo-form-field">
                                    <label class="smo-checkbox">
                                        <input type="checkbox" value="twitter" class="smo-input">
                                        <span class="smo-form-label">Twitter</span>
                                    </label>
                                </div>
                                <div class="smo-form-field">
                                    <label class="smo-checkbox">
                                        <input type="checkbox" value="linkedin" class="smo-input">
                                        <span class="smo-form-label">LinkedIn</span>
                                    </label>
                                </div>
                                <div class="smo-form-field">
                                    <label class="smo-checkbox">
                                        <input type="checkbox" value="tiktok" class="smo-input">
                                        <span class="smo-form-label">TikTok</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="smo-form-field">
                            <label for="smo-idea-tags" class="smo-form-label"><?php _e('Tags', 'smo-social'); ?></label>
                            <input type="text" id="smo-idea-tags" class="smo-input" 
                                   placeholder="<?php _e('Enter tags separated by commas', 'smo-social'); ?>">
                            <p class="smo-form-help">
                                <span class="icon">#️⃣</span>
                                <?php _e('Add relevant tags to help organize your ideas', 'smo-social'); ?>
                            </p>
                        </div>
                        
                        <div class="smo-button-group right">
                            <button type="button" class="smo-btn smo-btn-secondary" id="smo-cancel-idea">
                                <?php _e('Cancel', 'smo-social'); ?>
                            </button>
                            <button type="submit" class="smo-btn smo-btn-primary" id="smo-save-idea">
                                <?php _e('Save Idea', 'smo-social'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let selectedIdeas = [];
            
            // Load initial ideas
            loadIdeas();
            
            // Filter handlers
            $('#smo-filter-status, #smo-filter-priority, #smo-filter-type').on('change', function() {
                currentPage = 1;
                loadIdeas();
            });
            
            $('#smo-search-btn').on('click', function() {
                currentPage = 1;
                loadIdeas();
            });
            
            $('#smo-search-ideas').on('keypress', function(e) {
                if (e.which === 13) {
                    currentPage = 1;
                    loadIdeas();
                }
            });
            
            // Add new idea
            $('#smo-add-new-idea').on('click', function() {
                $('#smo-form-title').text('<?php _e("Add New Idea", "smo-social"); ?>');
                $('#smo-idea-form')[0].reset();
                $('#smo-idea-id').val('');
                $('#smo-modal-idea-form').show();
            });
            
            // Save idea
            $('#smo-idea-form').on('submit', function(e) {
                e.preventDefault();
                saveIdea();
            });
            
            // Refresh ideas
            $('#smo-refresh-ideas').on('click', function() {
                loadIdeas();
            });
            
            // Modal close handlers
            $('.smo-modal-close').on('click', function() {
                $(this).closest('.smo-modal').hide();
            });
            
            // Bulk actions
            $('#smo-apply-bulk').on('click', function() {
                applyBulkAction();
            });
            
            function loadIdeas() {
                $('#smo-ideas-container').html('<div class="smo-loading-indicator"><div class="smo-spinner"></div><p><?php _e("Loading content ideas...", "smo-social"); ?></p></div>');
                
                $.post(ajaxurl, {
                    action: 'smo_get_content_ideas_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    page: currentPage,
                    status: $('#smo-filter-status').val(),
                    priority: $('#smo-filter-priority').val(),
                    type: $('#smo-filter-type').val(),
                    search: $('#smo-search-ideas').val()
                }, function(response) {
                    if (response.success) {
                        renderIdeas(response.data.ideas);
                        renderPagination(response.data.pagination);
                    } else {
                        $('#smo-ideas-container').html('<p><?php _e("Error loading ideas", "smo-social"); ?></p>');
                    }
                });
            }
            
            function renderIdeas(ideas) {
                if (!ideas || ideas.length === 0) {
                    $('#smo-ideas-container').html('<p><?php _e("No ideas found", "smo-social"); ?></p>');
                    return;
                }
                
                let html = '';
                ideas.forEach(function(idea) {
                    html += createIdeaCard(idea);
                });
                
                $('#smo-ideas-container').html(html);
                
                // Bind action handlers
                bindIdeaActions();
            }
            
            function createIdeaCard(idea) {
                return `
                    <div class="smo-idea-card" data-id="${idea.id}">
                        <div class="smo-idea-header">
                            <h4 class="smo-idea-title">${escapeHtml(idea.title)}</h4>
                            <div class="smo-idea-actions">
                                <button type="button" class="button button-small smo-edit-idea" data-id="${idea.id}"><?php _e("Edit", "smo-social"); ?></button>
                                <button type="button" class="button button-small smo-delete-idea" data-id="${idea.id}"><?php _e("Delete", "smo-social"); ?></button>
                            </div>
                        </div>
                        <div class="smo-idea-meta">
                            <span class="smo-status-${idea.status}"><?php _e("Status:", "smo-social"); ?> ${idea.status}</span>
                            <span class="smo-priority-${idea.priority}"><?php _e("Priority:", "smo-social"); ?> ${idea.priority}</span>
                            <span><?php _e("Type:", "smo-social"); ?> ${idea.content_type}</span>
                            <span><?php _e("Created:", "smo-social"); ?> ${formatDate(idea.created_at)}</span>
                        </div>
                        <div class="smo-idea-description">${escapeHtml(idea.description)}</div>
                        <div class="smo-idea-platforms">
                            ${idea.target_platforms ? idea.target_platforms.map(p => `<span class="smo-platform-tag">${p}</span>`).join('') : ''}
                        </div>
                    </div>
                `;
            }
            
            function bindIdeaActions() {
                $('.smo-edit-idea').on('click', function() {
                    const ideaId = $(this).data('id');
                    editIdea(ideaId);
                });
                
                $('.smo-delete-idea').on('click', function() {
                    const ideaId = $(this).data('id');
                    deleteIdea(ideaId);
                });
            }
            
            function editIdea(id) {
                // Find idea data from current view or make AJAX call
                $.post(ajaxurl, {
                    action: 'smo_get_content_idea',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    id: id
                }, function(response) {
                    if (response.success) {
                        const idea = response.data;
                        $('#smo-form-title').text('<?php _e("Edit Idea", "smo-social"); ?>');
                        $('#smo-idea-id').val(idea.id);
                        $('#smo-idea-title').val(idea.title);
                        $('#smo-idea-description').val(idea.description);
                        $('#smo-idea-type').val(idea.content_type);
                        $('#smo-idea-priority').val(idea.priority);
                        $('#smo-idea-status').val(idea.status);
                        $('#smo-idea-scheduled').val(idea.scheduled_date);
                        
                        // Set platforms
                        $('.smo-platform-checkboxes input').prop('checked', false);
                        if (idea.target_platforms) {
                            idea.target_platforms.forEach(function(platform) {
                                $(`.smo-platform-checkboxes input[value="${platform}"]`).prop('checked', true);
                            });
                        }
                        
                        $('#smo-idea-tags').val(idea.tags);
                        $('#smo-modal-idea-form').show();
                    }
                });
            }
            
            function saveIdea() {
                const formData = {
                    id: $('#smo-idea-id').val(),
                    title: $('#smo-idea-title').val(),
                    description: $('#smo-idea-description').val(),
                    content_type: $('#smo-idea-type').val(),
                    priority: $('#smo-idea-priority').val(),
                    status: $('#smo-idea-status').val(),
                    scheduled_date: $('#smo-idea-scheduled').val(),
                    target_platforms: $('.smo-platform-checkboxes input:checked').map(function() {
                        return this.value;
                    }).get(),
                    tags: $('#smo-idea-tags').val()
                };
                
                const action = formData.id ? 'smo_update_content_idea' : 'smo_create_content_idea';
                
                $.post(ajaxurl, {
                    action: action,
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    data: formData
                }, function(response) {
                    if (response.success) {
                        $('#smo-modal-idea-form').hide();
                        loadIdeas();
                        showNotification('<?php _e("Idea saved successfully", "smo-social"); ?>', 'success');
                    } else {
                        showNotification(response.data, 'error');
                    }
                });
            }
            
            function deleteIdea(id) {
                if (!confirm('<?php _e("Are you sure you want to delete this idea?", "smo-social"); ?>')) {
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'smo_delete_content_idea',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    id: id
                }, function(response) {
                    if (response.success) {
                        loadIdeas();
                        showNotification('<?php _e("Idea deleted successfully", "smo-social"); ?>', 'success');
                    } else {
                        showNotification(response.data, 'error');
                    }
                });
            }
            
            function renderPagination(pagination) {
                // Render pagination HTML based on data
                let html = '';
                if (pagination && pagination.total_pages > 1) {
                    // Build pagination
                }
                $('#smo-ideas-pagination').html(html);
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString();
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get content ideas for modal
     */
    public function ajax_get_content_ideas() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Build filters
        $filters = array(
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'priority' => sanitize_text_field($_POST['priority'] ?? ''),
            'content_type' => sanitize_text_field($_POST['type'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'limit' => $per_page,
            'offset' => $offset
        );

        try {
            require_once __DIR__ . '/../Content/ContentIdeasManager.php';
            $manager = new \SMO_Social\Content\ContentIdeasManager();

            $ideas = $manager->get_content_ideas($filters);
            $total = $manager->get_content_ideas_count($filters);
            
            // Format data for frontend
            $formatted_ideas = array();
            foreach ($ideas as $idea) {
                $formatted_ideas[] = array(
                    'id' => $idea['id'],
                    'title' => $idea['title'],
                    'description' => wp_trim_words($idea['description'], 20),
                    'content_type' => $idea['content_type'],
                    'priority' => $idea['priority'],
                    'status' => $idea['status'],
                    'target_platforms' => $idea['target_platforms'] ? explode(',', $idea['target_platforms']) : array(),
                    'tags' => $idea['tags'],
                    'created_at' => $idea['created_at'],
                    'scheduled_date' => $idea['scheduled_date']
                );
            }
            
            $pagination = array(
                'current_page' => $page,
                'total_pages' => ceil($total / $per_page),
                'total_items' => $total
            );
            
            wp_send_json_success(array(
                'ideas' => $formatted_ideas,
                'pagination' => $pagination
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Create content idea
     */
    public function ajax_create_idea() {
        // Enhanced CSRF validation
        if (!\SMO_Social\Security\CSRFManager::validateToken($_POST['csrf_token'] ?? '', 'content_idea_create')) {
            wp_send_json_error('Security validation failed', 403);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        // Check if data exists in POST
        if (empty($_POST['data'])) {
            wp_send_json_error(__('No data provided'));
        }
        
        $data = array(
            'title' => sanitize_text_field($_POST['data']['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['data']['description'] ?? ''),
            'content_type' => sanitize_text_field($_POST['data']['content_type'] ?? ''),
            'priority' => sanitize_text_field($_POST['data']['priority'] ?? ''),
            'status' => sanitize_text_field($_POST['data']['status'] ?? ''),
            'target_platforms' => array_map('sanitize_text_field', $_POST['data']['target_platforms'] ?? array()),
            'tags' => sanitize_text_field($_POST['data']['tags'] ?? ''),
            'scheduled_date' => sanitize_text_field($_POST['data']['scheduled_date'] ?? '')
        );
        
        try {
            require_once __DIR__ . '/../Content/ContentIdeasManager.php';
            $manager = new \SMO_Social\Content\ContentIdeasManager();
            
            $idea_id = $manager->add_content_idea($data, get_current_user_id());
            
            wp_send_json_success(array(
                'message' => __('Content idea created successfully', 'smo-social'),
                'idea_id' => $idea_id
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Update content idea
     */
    public function ajax_update_idea() {
        // Enhanced CSRF validation
        if (!\SMO_Social\Security\CSRFManager::validateToken($_POST['csrf_token'] ?? '', 'content_idea_update')) {
            wp_send_json_error('Security validation failed', 403);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        // Check if data exists in POST
        if (empty($_POST['data'])) {
            wp_send_json_error(__('No data provided'));
        }
        
        $idea_id = intval($_POST['data']['id'] ?? 0);
        if ($idea_id === 0) {
            wp_send_json_error(__('Invalid idea ID'));
        }
        
        $data = array(
            'title' => sanitize_text_field($_POST['data']['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['data']['description'] ?? ''),
            'content_type' => sanitize_text_field($_POST['data']['content_type'] ?? ''),
            'priority' => sanitize_text_field($_POST['data']['priority'] ?? ''),
            'status' => sanitize_text_field($_POST['data']['status'] ?? ''),
            'target_platforms' => array_map('sanitize_text_field', $_POST['data']['target_platforms'] ?? array()),
            'tags' => sanitize_text_field($_POST['data']['tags'] ?? ''),
            'scheduled_date' => sanitize_text_field($_POST['data']['scheduled_date'] ?? '')
        );
        
        try {
            require_once __DIR__ . '/../Content/ContentIdeasManager.php';
            $manager = new \SMO_Social\Content\ContentIdeasManager();
            
            $manager->update_content_idea($idea_id, $data, get_current_user_id());
            
            wp_send_json_success(__('Content idea updated successfully', 'smo-social'));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Delete content idea
     */
    public function ajax_delete_idea() {
        // Enhanced CSRF validation
        if (!\SMO_Social\Security\CSRFManager::validateToken($_POST['csrf_token'] ?? '', 'content_idea_delete')) {
            wp_send_json_error('Security validation failed', 403);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        $idea_id = intval($_POST['id'] ?? 0);
        if ($idea_id === 0) {
            wp_send_json_error(__('Invalid idea ID'));
        }
        
        try {
            require_once __DIR__ . '/../Content/ContentIdeasManager.php';
            $manager = new \SMO_Social\Content\ContentIdeasManager();
            
            $manager->delete_content_idea($idea_id);
            
            wp_send_json_success(__('Content idea deleted successfully', 'smo-social'));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Bulk actions on ideas
     */
    public function ajax_bulk_action() {
        // Enhanced CSRF validation
        if (!\SMO_Social\Security\CSRFManager::validateToken($_POST['csrf_token'] ?? '', 'content_idea_bulk')) {
            wp_send_json_error('Security validation failed', 403);
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions'));
        }
        
        $action = sanitize_text_field($_POST['action'] ?? '');
        if (empty($action)) {
            wp_send_json_error(__('No action specified'));
        }
        
        $idea_ids = array_map('intval', $_POST['idea_ids'] ?? array());
        if (empty($idea_ids)) {
            wp_send_json_error(__('No idea IDs provided'));
        }
        
        try {
            require_once __DIR__ . '/../Content/ContentIdeasManager.php';
            $manager = new \SMO_Social\Content\ContentIdeasManager();
            
            switch ($action) {
                case 'delete':
                    foreach ($idea_ids as $id) {
                        $manager->delete_content_idea($id);
                    }
                    break;
                    
                case 'change_status':
                    $status = sanitize_text_field($_POST['value'] ?? '');
                    if (empty($status)) {
                        wp_send_json_error(__('No status value provided'));
                    }
                    foreach ($idea_ids as $id) {
                        $manager->update_content_idea($id, array('status' => $status), get_current_user_id());
                    }
                    break;
                    
                case 'change_priority':
                    $priority = sanitize_text_field($_POST['value'] ?? '');
                    if (empty($priority)) {
                        wp_send_json_error(__('No priority value provided'));
                    }
                    foreach ($idea_ids as $id) {
                        $manager->update_content_idea($id, array('priority' => $priority), get_current_user_id());
                    }
                    break;
            }
            
            wp_send_json_success(__('Bulk action completed successfully', 'smo-social'));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
