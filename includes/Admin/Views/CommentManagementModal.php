<?php
/**
 * Comment Management Modal Component
 * Real-time comment synchronization and management across platforms
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../../Social/CommentManager.php';

/**
 * Comment Management Modal View
 */
class CommentManagementModal {
    
    private $comment_manager;
    
    public function __construct() {
        $this->comment_manager = new \SMO_Social\Social\CommentManager();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_get_comment_management_data', array($this, 'ajax_get_comment_data'));
        add_action('wp_ajax_smo_bulk_reply_comments', array($this, 'ajax_bulk_reply_comments'));
        add_action('wp_ajax_smo_update_comment_batch_sentiment', array($this, 'ajax_update_comment_batch_sentiment'));
        add_action('wp_ajax_smo_get_comment_scores_modal', array($this, 'ajax_get_comment_scores'));
        add_action('wp_ajax_smo_reply_to_comment', array($this, 'ajax_reply_to_comment'));
        add_action('wp_ajax_smo_sync_comments', array($this, 'ajax_sync_comments'));
        add_action('wp_ajax_smo_update_comment_sentiment', array($this, 'ajax_update_comment_sentiment'));
    }
    
    /**
     * Get comment management modal HTML
     */
    public function get_modal_html() {
        ob_start();
        ?>
        <div id="smo-modal-comment-management" class="smo-modal">
            <div class="smo-modal-content extra-large-modal">
                <span class="smo-modal-close" role="button" tabindex="0" aria-label="<?php _e('Close modal', 'smo-social'); ?>">&times;</span>
                
                <div class="smo-modal-header">
                    <h3><?php _e('Comment Management Center', 'smo-social'); ?></h3>
                    <div class="smo-modal-actions">
                        <div class="smo-status-indicator" id="smo-sync-status">
                            <span class="smo-status-dot"></span>
                            <span class="smo-status-text"><?php _e('Live Sync Active', 'smo-social'); ?></span>
                        </div>
                        <button type="button" class="button button-primary" id="smo-sync-comments-btn">
                            <?php _e('Sync Now', 'smo-social'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="smo-modal-body">
                    <!-- Tabs Navigation -->
                    <div class="smo-tabs-navigation">
                        <button class="smo-tab-btn active" data-tab="comments" aria-controls="tab-comments" role="tab">
                            <?php _e('Comments', 'smo-social'); ?>
                            <span class="smo-tab-badge" id="comments-badge">0</span>
                        </button>
                        <button class="smo-tab-btn" data-tab="scores" aria-controls="tab-scores" role="tab">
                            <?php _e('Scores', 'smo-social'); ?>
                        </button>
                        <button class="smo-tab-btn" data-tab="analytics" aria-controls="tab-analytics" role="tab">
                            <?php _e('Analytics', 'smo-social'); ?>
                        </button>
                    </div>
                    
                    <!-- Comments Tab -->
                    <div class="smo-tab-content">
                        <div id="tab-comments" class="smo-tab-pane active" role="tabpanel">
                            <!-- Filters and Controls -->
                            <div class="smo-comments-controls">
                                <div class="smo-filters-row">
                                    <div class="smo-filter-group">
                                        <label for="smo-filter-platform" class="smo-filter-label"><?php _e('Platform:', 'smo-social'); ?></label>
                                        <select id="smo-filter-platform" class="smo-filter-select">
                                            <option value=""><?php _e('All Platforms', 'smo-social'); ?></option>
                                            <option value="facebook"><?php _e('Facebook', 'smo-social'); ?></option>
                                            <option value="instagram"><?php _e('Instagram', 'smo-social'); ?></option>
                                            <option value="twitter"><?php _e('Twitter/X', 'smo-social'); ?></option>
                                            <option value="linkedin"><?php _e('LinkedIn', 'smo-social'); ?></option>
                                            <option value="threads"><?php _e('Threads', 'smo-social'); ?></option>
                                            <option value="bluesky"><?php _e('Bluesky', 'smo-social'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="smo-filter-group">
                                        <label for="smo-filter-sentiment" class="smo-filter-label"><?php _e('Sentiment:', 'smo-social'); ?></label>
                                        <select id="smo-filter-sentiment" class="smo-filter-select">
                                            <option value=""><?php _e('All Sentiments', 'smo-social'); ?></option>
                                            <option value="positive"><?php _e('Positive', 'smo-social'); ?></option>
                                            <option value="neutral"><?php _e('Neutral', 'smo-social'); ?></option>
                                            <option value="negative"><?php _e('Negative', 'smo-social'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="smo-filter-group">
                                        <label for="smo-filter-status" class="smo-filter-label"><?php _e('Status:', 'smo-social'); ?></label>
                                        <select id="smo-filter-status" class="smo-filter-select">
                                            <option value=""><?php _e('All Status', 'smo-social'); ?></option>
                                            <option value="0"><?php _e('Unanswered', 'smo-social'); ?></option>
                                            <option value="1"><?php _e('Replied', 'smo-social'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="smo-search-group">
                                        <label for="smo-search-comments" class="smo-filter-label"><?php _e('Search:', 'smo-social'); ?></label>
                                        <input type="text" id="smo-search-comments" class="smo-search-input" 
                                               placeholder="<?php _e('Search comments...', 'smo-social'); ?>">
                                    </div>
                                </div>
                                
                                <div class="smo-bulk-actions-row">
                                    <label class="smo-checkbox-label">
                                        <input type="checkbox" id="smo-select-all-comments">
                                        <?php _e('Select All', 'smo-social'); ?>
                                    </label>
                                    <div class="smo-bulk-actions" style="display: none;" id="smo-bulk-actions">
                                        <select id="smo-bulk-action-select">
                                            <option value=""><?php _e('Bulk Actions', 'smo-social'); ?></option>
                                            <option value="reply"><?php _e('Send Replies', 'smo-social'); ?></option>
                                            <option value="update_sentiment"><?php _e('Update Sentiment', 'smo-social'); ?></option>
                                            <option value="mark_read"><?php _e('Mark as Read', 'smo-social'); ?></option>
                                        </select>
                                        <button type="button" class="button" id="smo-apply-bulk-action"><?php _e('Apply', 'smo-social'); ?></button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Comments List -->
                            <div class="smo-comments-list" id="smo-comments-container">
                                <div class="smo-loading-indicator">
                                    <div class="smo-spinner"></div>
                                    <p><?php _e('Loading comments...', 'smo-social'); ?></p>
                                </div>
                            </div>
                            
                            <!-- Pagination -->
                            <div class="smo-pagination" id="smo-comments-pagination"></div>
                        </div>
                        
                        <!-- Scores Tab -->
                        <div id="tab-scores" class="smo-tab-pane" role="tabpanel">
                            <div class="smo-scores-section">
                                <div class="smo-scores-grid" id="smo-scores-container">
                                    <!-- Scores will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Analytics Tab -->
                        <div id="tab-analytics" class="smo-tab-pane" role="tabpanel">
                            <div class="smo-analytics-section">
                                <div class="smo-analytics-cards">
                                    <div class="smo-analytics-card">
                                        <h4><?php _e('Response Time', 'smo-social'); ?></h4>
                                        <div class="smo-analytics-value" id="smo-avg-response-time">-</div>
                                        <span class="smo-analytics-label"><?php _e('Average', 'smo-social'); ?></span>
                                    </div>
                                    <div class="smo-analytics-card">
                                        <h4><?php _e('Reply Rate', 'smo-social'); ?></h4>
                                        <div class="smo-analytics-value" id="smo-reply-rate">-</div>
                                        <span class="smo-analytics-label"><?php _e('This Week', 'smo-social'); ?></span>
                                    </div>
                                    <div class="smo-analytics-card">
                                        <h4><?php _e('Engagement Score', 'smo-social'); ?></h4>
                                        <div class="smo-analytics-value" id="smo-engagement-score">-</div>
                                        <span class="smo-analytics-label"><?php _e('Overall', 'smo-social'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="smo-analytics-charts">
                                    <div class="smo-chart-container">
                                        <h4><?php _e('Sentiment Distribution', 'smo-social'); ?></h4>
                                        <canvas id="smo-sentiment-chart" width="400" height="200"></canvas>
                                    </div>
                                    <div class="smo-chart-container">
                                        <h4><?php _e('Platform Performance', 'smo-social'); ?></h4>
                                        <canvas id="smo-platform-chart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reply Modal -->
        <div id="smo-modal-reply" class="smo-modal">
            <div class="smo-modal-content small-modal">
                <span class="smo-modal-close" role="button" tabindex="0" aria-label="<?php _e('Close modal', 'smo-social'); ?>">&times;</span>
                <h3><?php _e('Reply to Comment', 'smo-social'); ?></h3>
                <div class="smo-modal-body">
                    <div class="smo-reply-preview" id="smo-reply-preview"></div>
                    <form id="smo-reply-form">
                        <div class="smo-form-group">
                            <label for="smo-reply-textarea"><?php _e('Your Reply:', 'smo-social'); ?></label>
                            <textarea id="smo-reply-textarea" rows="4" required 
                                      placeholder="<?php _e('Type your reply here...', 'smo-social'); ?>"></textarea>
                        </div>
                        <div class="smo-form-actions">
                            <button type="submit" class="button button-primary" id="smo-send-reply">
                                <?php _e('Send Reply', 'smo-social'); ?>
                            </button>
                            <button type="button" class="button" id="smo-cancel-reply">
                                <?php _e('Cancel', 'smo-social'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
<script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let selectedComments = [];
            let currentFilters = {};
            let wsConnection = null;
            
            // Initialize modal
            initializeCommentModal();
            
            // Tab navigation
            $('.smo-tab-btn').on('click', function() {
                const tab = $(this).data('tab');
                switchTab(tab);
            });
            
            // Filter handlers
            $('#smo-filter-platform, #smo-filter-sentiment, #smo-filter-status').on('change', function() {
                currentPage = 1;
                loadComments();
            });
            
            $('#smo-search-comments').on('input', debounce(function() {
                currentPage = 1;
                loadComments();
            }, 500));
            
            // Bulk selection
            $('#smo-select-all-comments').on('change', function() {
                const isChecked = $(this).prop('checked');
                $('.smo-comment-checkbox').prop('checked', isChecked);
                updateBulkActions();
            });
            
            // Sync button
            $('#smo-sync-comments-btn').on('click', function() {
                syncComments();
            });
            
            // Reply form
            $('#smo-reply-form').on('submit', function(e) {
                e.preventDefault();
                sendReply();
            });
            
            function initializeCommentModal() {
                loadComments();
                loadScores();
                loadAnalytics();
                initializeWebSocket();
            }
            
            function loadComments() {
                $('#smo-comments-container').html('<div class="smo-loading-indicator"><div class="smo-spinner"></div><p>Loading comments...</p></div>');
                
                currentFilters = {
                    platform: $('#smo-filter-platform').val(),
                    sentiment: $('#smo-filter-sentiment').val(),
                    is_replied: $('#smo-filter-status').val(),
                    search: $('#smo-search-comments').val(),
                    page: currentPage,
                    limit: 20
                };
                
                $.post(ajaxurl, {
                    action: 'smo_get_comment_management_data',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    filters: currentFilters
                }, function(response) {
                    if (response.success) {
                        renderComments(response.data.comments);
                        updateCommentsBadge(response.data.total_count);
                        renderPagination(response.data.pagination);
                    } else {
                        $('#smo-comments-container').html('<p>Error loading comments: ' + response.data + '</p>');
                    }
                });
            }
            
            function renderComments(comments) {
                if (!comments || comments.length === 0) {
                    $('#smo-comments-container').html('<p>No comments found.</p>');
                    return;
                }
                
                let html = '';
                comments.forEach(function(comment) {
                    html += createCommentHtml(comment);
                });
                
                $('#smo-comments-container').html(html);
                
                // Bind event handlers
                bindCommentEvents();
            }
            
            function createCommentHtml(comment) {
                const isReplied = comment.is_replied == 1;
                const sentimentClass = 'smo-sentiment-' + comment.sentiment;
                
                return `
                    <div class="smo-comment-item" data-id="${comment.id}">
                        <div class="smo-comment-header">
                            <div class="smo-comment-author">
                                <div class="smo-author-avatar">${comment.author_name.charAt(0).toUpperCase()}</div>
                                <div>
                                    <strong>${escapeHtml(comment.author_name)}</strong>
                                    <span class="smo-platform-badge">${escapeHtml(comment.platform)}</span>
                                    ${isReplied ? '<span class="smo-replied-badge" style="color: #00a32a;">✓ Replied</span>' : ''}
                                </div>
                            </div>
                            <div>
                                <input type="checkbox" class="smo-comment-checkbox" data-comment-id="${comment.id}">
                                <label class="smo-checkbox-label"></label>
                            </div>
                        </div>
                        <div class="smo-comment-content">${escapeHtml(comment.content)}</div>
                        <div class="smo-comment-meta">
                            <span class="smo-comment-date">${formatDate(comment.created_at)}</span>
                            <span class="${sentimentClass}">${comment.sentiment}</span>
                        </div>
                        <div class="smo-comment-actions">
                            <button type="button" class="button button-small smo-reply-btn" data-comment-id="${comment.id}">
                                ${isReplied ? 'View Reply' : 'Reply'}
                            </button>
                            <button type="button" class="button button-small smo-update-sentiment-btn" data-comment-id="${comment.id}">
                                Update Sentiment
                            </button>
                        </div>
                    </div>
                `;
            }
            
            function bindCommentEvents() {
                // Reply buttons
                $('.smo-reply-btn').on('click', function() {
                    const commentId = $(this).data('comment-id');
                    openReplyModal(commentId);
                });
                
                // Update sentiment buttons
                $('.smo-update-sentiment-btn').on('click', function() {
                    const commentId = $(this).data('comment-id');
                    updateSentiment(commentId);
                });
                
                // Comment checkboxes
                $('.smo-comment-checkbox').on('change', function() {
                    updateBulkActions();
                });
            }
            
            function openReplyModal(commentId) {
                const comment = findCommentById(commentId);
                if (comment) {
                    $('#smo-reply-preview').html(`
                        <strong>Original comment by ${comment.author_name}:</strong><br>
                        "${escapeHtml(comment.content)}"
                    `);
                    $('#smo-modal-reply').show();
                    $('#smo-reply-textarea').focus();
                }
            }
            
            function sendReply() {
                const replyText = $('#smo-reply-textarea').val();
                const commentId = $('#smo-reply-textarea').data('comment-id');
                
                $.post(ajaxurl, {
                    action: 'smo_reply_to_comment',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                    comment_id: commentId,
                    reply_content: replyText
                }, function(response) {
                    if (response.success) {
                        $('#smo-modal-reply').hide();
                        loadComments();
                        showNotification('Reply sent successfully', 'success');
                    } else {
                        showNotification('Failed to send reply: ' + response.data, 'error');
                    }
                });
            }
            
            function updateBulkActions() {
                selectedComments = [];
                $('.smo-comment-checkbox:checked').each(function() {
                    selectedComments.push($(this).data('comment-id'));
                });
                
                if (selectedComments.length > 0) {
                    $('#smo-bulk-actions').show();
                    $('.smo-selected-count').text(`${selectedComments.length} selected`);
                } else {
                    $('#smo-bulk-actions').hide();
                }
            }
            
            function loadScores() {
                $.post(ajaxurl, {
                    action: 'smo_get_comment_scores_modal',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        renderScores(response.data);
                    }
                });
            }
            
            function renderScores(scores) {
                const container = $('#smo-scores-container');
                let html = '';
                
                scores.forEach(function(score) {
                    html += `
                        <div class="smo-score-card">
                            <div class="smo-score-header">
                                <h4>${escapeHtml(score.platform)}</h4>
                                <span>${formatDate(score.date)}</span>
                            </div>
                            <div class="smo-score-value">${score.total_score || 0}</div>
                            <p>Comments sent: ${score.comments_sent || 0}</p>
                            <p>Consistency: ${(score.consistency_score || 0).toFixed(1)}%</p>
                            <p>Speed: ${(score.speed_score || 0).toFixed(1)}%</p>
                            <p>Habits: ${(score.habits_score || 0).toFixed(1)}%</p>
                        </div>
                    `;
                });
                
                container.html(html);
            }
            
            function loadAnalytics() {
                // This would load analytics data
                // For now, just show placeholder values
                $('#smo-avg-response-time').text('2.3h');
                $('#smo-reply-rate').text('85%');
                $('#smo-engagement-score').text('78');
            }
            
            function initializeWebSocket() {
                // WebSocket connection for real-time updates
                if (typeof WebSocket !== 'undefined') {
                    const wsUrl = 'ws://localhost:8080'; // Configure WebSocket server
                    wsConnection = new WebSocket(wsUrl);
                    
                    wsConnection.onopen = function() {
                        console.log('WebSocket connected for comment updates');
                    };
                    
                    wsConnection.onmessage = function(event) {
                        const data = JSON.parse(event.data);
                        if (data.type === 'new_comment') {
                            handleNewComment(data.comment);
                        }
                    };
                    
                    wsConnection.onerror = function(error) {
                        console.log('WebSocket error:', error);
                    };
                }
            }
            
            function handleNewComment(comment) {
                // Add new comment to the top of the list
                const newCommentHtml = createCommentHtml(comment);
                $('#smo-comments-container').prepend(newCommentHtml);
                bindCommentEvents();
                
                // Update badge count
                const currentCount = parseInt($('#comments-badge').text()) || 0;
                $('#comments-badge').text(currentCount + 1);
                
                showNotification('New comment received', 'info');
            }
            
            function syncComments() {
                $.post(ajaxurl, {
                    action: 'smo_sync_comments',
                    nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        loadComments();
                        showNotification('Comments synchronized successfully', 'success');
                    } else {
                        showNotification('Sync failed: ' + response.data, 'error');
                    }
                });
            }
            
            function switchTab(tab) {
                $('.smo-tab-btn').removeClass('active');
                $('.smo-tab-pane').removeClass('active');
                
                $(`.smo-tab-btn[data-tab="${tab}"]`).addClass('active');
                $(`#tab-${tab}`).addClass('active');
            }
            
            function updateCommentsBadge(count) {
                $('#comments-badge').text(count);
            }
            
            function renderPagination(pagination) {
                // Implement pagination rendering
            }
            
            function findCommentById(id) {
                // This would search through the current comments list
                return null;
            }
            
            function updateSentiment(commentId) {
                const sentiment = prompt('Enter sentiment (positive, neutral, negative):');
                if (sentiment && ['positive', 'neutral', 'negative'].includes(sentiment)) {
                    $.post(ajaxurl, {
                        action: 'smo_update_comment_sentiment',
                        nonce: '<?php echo wp_create_nonce("smo_social_nonce"); ?>',
                        comment_id: commentId,
                        sentiment: sentiment
                    }, function(response) {
                        if (response.success) {
                            loadComments();
                            showNotification('Sentiment updated', 'success');
                        } else {
                            showNotification('Failed to update sentiment', 'error');
                        }
                    });
                }
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }
            
            function debounce(func, wait) {
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
            
            function showNotification(message, type) {
                const notification = $(`<div class="smo-notification smo-notification-${type}">${message}</div>`);
                $('body').append(notification);
                
                setTimeout(function() {
                    notification.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get comment management data
     */
    public function ajax_get_comment_data() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $filters = $_POST['filters'] ?? array();
        
        try {
            $comments = $this->comment_manager->get_comments($filters);
            $total_count = count($comments);
            
            // Add pagination if needed
            $page = intval($filters['page'] ?? 1);
            $per_page = intval($filters['limit'] ?? 20);
            $offset = ($page - 1) * $per_page;
            
            $paginated_comments = array_slice($comments, $offset, $per_page);
            
            $pagination = array(
                'current_page' => $page,
                'total_pages' => ceil($total_count / $per_page),
                'total_items' => $total_count
            );
            
            wp_send_json_success(array(
                'comments' => $paginated_comments,
                'pagination' => $pagination,
                'total_count' => $total_count
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Bulk reply comments
     */
    public function ajax_bulk_reply_comments() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $comment_ids = array_map('intval', $_POST['comment_ids'] ?? array());
        $reply_content = wp_kses_post($_POST['reply_content']);
        
        if (empty($comment_ids) || empty($reply_content)) {
            wp_send_json_error(__('Comment IDs and reply content are required', 'smo-social'));
        }
        
        $success_count = 0;
        
        foreach ($comment_ids as $comment_id) {
            try {
                $this->comment_manager->reply_to_comment($comment_id, $reply_content);
                $success_count++;
            } catch (\Exception $e) {
                error_log('SMO Social: Failed to reply to comment ' . $comment_id . ': ' . $e->getMessage());
            }
        }
        
        wp_send_json_success(array(
            'success_count' => $success_count,
            'message' => sprintf(__('Replies sent to %d comments', 'smo-social'), $success_count)
        ));
    }
    
    /**
     * AJAX: Update comment batch sentiment
     */
    public function ajax_update_comment_batch_sentiment() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $comment_ids = array_map('intval', $_POST['comment_ids'] ?? array());
        $sentiment = sanitize_text_field($_POST['sentiment']);
        
        if (empty($comment_ids) || !in_array($sentiment, array('positive', 'neutral', 'negative'))) {
            wp_send_json_error(__('Valid comment IDs and sentiment are required', 'smo-social'));
        }
        
        global $wpdb;
        $success_count = 0;
        
        foreach ($comment_ids as $comment_id) {
            // Use prepared statement with proper sanitization
            $query = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}smo_social_comments 
                 SET sentiment = %s, updated_at = %s 
                 WHERE id = %d",
                $sentiment,
                current_time('mysql'),
                $comment_id
            );
            
            $result = $wpdb->query($query);
            
            if ($result !== false && $result > 0) {
                $success_count++;
            }
        }
        
        wp_send_json_success(array(
            'success_count' => $success_count,
            'message' => sprintf(__('Sentiment updated for %d comments', 'smo-social'), $success_count)
        ));
    }
    
    /**
     * AJAX: Get comment scores for modal
     */
    public function ajax_get_comment_scores() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $user_id = get_current_user_id();
        
        try {
            $scores = $this->comment_manager->get_user_comment_scores($user_id);
            wp_send_json_success($scores);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Reply to individual comment
     */
    public function ajax_reply_to_comment() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $reply_content = wp_kses_post($_POST['reply_content'] ?? '');
        
        if (empty($comment_id) || empty($reply_content)) {
            wp_send_json_error(__('Comment ID and reply content are required', 'smo-social'));
        }
        
        try {
            $this->comment_manager->reply_to_comment($comment_id, $reply_content);
            wp_send_json_success(__('Reply sent successfully', 'smo-social'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Sync comments from platforms
     */
    public function ajax_sync_comments() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        try {
            $this->comment_manager->sync_all_comments();
            wp_send_json_success(array(
                'message' => __('Comments synchronized successfully', 'smo-social')
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Update single comment sentiment
     */
    public function ajax_update_comment_sentiment() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $sentiment = sanitize_text_field($_POST['sentiment']);
        
        if (empty($comment_id) || !in_array($sentiment, array('positive', 'neutral', 'negative'))) {
            wp_send_json_error(__('Valid comment ID and sentiment are required', 'smo-social'));
        }
        
        global $wpdb;
        
        $query = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}smo_social_comments 
             SET sentiment = %s, updated_at = %s 
             WHERE id = %d",
            $sentiment,
            current_time('mysql'),
            $comment_id
        );
        
        $result = $wpdb->query($query);
        
        if ($result !== false && $result > 0) {
            wp_send_json_success(__('Sentiment updated', 'smo-social'));
        } else {
            wp_send_json_error(__('Failed to update sentiment', 'smo-social'));
        }
    }
}
