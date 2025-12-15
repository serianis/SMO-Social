<?php
/**
 * Link Posts Widget
 * 
 * Create and manage link posts for social media sharing
 *
 * @package SMO_Social
 * @subpackage Admin\Widgets
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Link Posts Widget Class
 */
class LinkPostsWidget extends BaseWidget
{
    /**
     * Initialize widget properties
     */
    protected function init()
    {
        $this->id = 'link_posts';
        $this->name = __('Link Posts', 'smo-social');
        $this->description = __('Create and share link posts across your social media platforms', 'smo-social');
        $this->category = 'content';
        $this->icon = 'dashicons-admin-links';
        $this->default_size = 'large';
        $this->capabilities = array('edit_smo_posts');
    }

    /**
     * Get widget data
     */
    public function get_data($args = array())
    {
        global $wpdb;

        $user_id = isset($args['user_id']) ? $args['user_id'] : get_current_user_id();
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        // Get recent link posts
        $recent_link_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $posts_table 
             WHERE created_by = %d 
             AND post_type = 'link'
             ORDER BY created_at DESC 
             LIMIT 10",
            $user_id
        ), ARRAY_A);

        // Get link post statistics
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_links,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
             FROM $posts_table 
             WHERE created_by = %d AND post_type = 'link'",
            $user_id
        ), ARRAY_A);

        return array(
            'recent_posts' => $recent_link_posts,
            'stats' => $stats ?: array('total_links' => 0, 'published' => 0, 'scheduled' => 0),
            'available_platforms' => $this->get_available_platforms()
        );
    }

    /**
     * Get available platforms
     */
    private function get_available_platforms()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'smo_platform_tokens';

        $platforms = $wpdb->get_results(
            "SELECT DISTINCT platform_slug, platform_name 
             FROM $table 
             WHERE status = 'active'",
            ARRAY_A
        );

        return $platforms ?: array();
    }

    /**
     * Render widget content
     */
    public function render($data = array())
    {
        if (empty($data)) {
            $data = $this->get_data();
        }

        $stats = $data['stats'];

        ob_start();
        ?>

        <div class="smo-widget smo-link-posts-widget">
            <div class="smo-widget-header">
                <h3><?php echo esc_html($this->name); ?></h3>
                <div class="smo-widget-actions">
                    <button class="smo-btn smo-btn-primary smo-btn-sm" id="create-link-post">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Create Link Post', 'smo-social'); ?>
                    </button>
                </div>
            </div>

            <div class="smo-widget-body">
                <!-- Statistics -->
                <div class="smo-link-stats">
                    <div class="smo-stat-item">
                        <span class="smo-stat-value"><?php echo esc_html($stats['total_links']); ?></span>
                        <span class="smo-stat-label"><?php esc_html_e('Total Links', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-stat-item">
                        <span class="smo-stat-value"><?php echo esc_html($stats['published']); ?></span>
                        <span class="smo-stat-label"><?php esc_html_e('Published', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-stat-item">
                        <span class="smo-stat-value"><?php echo esc_html($stats['scheduled']); ?></span>
                        <span class="smo-stat-label"><?php esc_html_e('Scheduled', 'smo-social'); ?></span>
                    </div>
                </div>

                <!-- Create Link Post Form (Initially Hidden) -->
                <div id="link-post-form" class="smo-link-post-form" style="display: none;">
                    <div class="smo-form-header">
                        <h4><?php esc_html_e('Create Link Post', 'smo-social'); ?></h4>
                        <button class="smo-btn-icon" id="close-link-form">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>

                    <div class="smo-form-group">
                        <label for="link-url"><?php esc_html_e('URL', 'smo-social'); ?> *</label>
                        <input type="url" id="link-url" class="smo-input" placeholder="https://example.com/article" required>
                        <button type="button" class="smo-btn smo-btn-secondary smo-btn-sm" id="fetch-link-preview">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e('Fetch Preview', 'smo-social'); ?>
                        </button>
                    </div>

                    <!-- Link Preview -->
                    <div id="link-preview" class="smo-link-preview" style="display: none;">
                        <div class="smo-preview-image">
                            <img id="preview-image" src="" alt="">
                        </div>
                        <div class="smo-preview-content">
                            <h5 id="preview-title"></h5>
                            <p id="preview-description"></p>
                            <span id="preview-domain" class="smo-preview-domain"></span>
                        </div>
                    </div>

                    <div class="smo-form-group">
                        <label for="link-caption"><?php esc_html_e('Caption', 'smo-social'); ?></label>
                        <textarea id="link-caption" class="smo-textarea" rows="4"
                            placeholder="<?php esc_attr_e('Add a caption for your link post...', 'smo-social'); ?>"></textarea>
                        <div class="smo-char-counter">
                            <span id="caption-count">0</span> / 500
                        </div>
                    </div>

                    <div class="smo-form-group">
                        <label><?php esc_html_e('Platforms', 'smo-social'); ?> *</label>
                        <div class="smo-platform-selector">
                            <?php foreach ($data['available_platforms'] as $platform): ?>
                                <label class="smo-platform-option">
                                    <input type="checkbox" name="link_platforms[]"
                                        value="<?php echo esc_attr($platform['platform_slug']); ?>">
                                    <span class="smo-platform-badge">
                                        <span class="dashicons dashicons-share"></span>
                                        <?php echo esc_html($platform['platform_name'] ?? ucfirst($platform['platform_slug'])); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="smo-form-group">
                        <label for="link-schedule"><?php esc_html_e('Schedule', 'smo-social'); ?></label>
                        <div class="smo-schedule-options">
                            <label class="smo-radio-label">
                                <input type="radio" name="schedule_type" value="now" checked>
                                <span><?php esc_html_e('Post Now', 'smo-social'); ?></span>
                            </label>
                            <label class="smo-radio-label">
                                <input type="radio" name="schedule_type" value="schedule">
                                <span><?php esc_html_e('Schedule for Later', 'smo-social'); ?></span>
                            </label>
                        </div>
                        <div id="schedule-datetime" style="display: none; margin-top: 10px;">
                            <input type="datetime-local" id="scheduled-time" class="smo-input">
                        </div>
                    </div>

                    <div class="smo-form-group">
                        <label class="smo-checkbox-label">
                            <input type="checkbox" id="link-auto-hashtags" checked>
                            <span><?php esc_html_e('Auto-generate hashtags', 'smo-social'); ?></span>
                        </label>
                        <label class="smo-checkbox-label">
                            <input type="checkbox" id="link-optimize-content" checked>
                            <span><?php esc_html_e('Optimize for each platform', 'smo-social'); ?></span>
                        </label>
                    </div>

                    <div class="smo-form-actions">
                        <button type="button" class="smo-btn smo-btn-secondary" id="cancel-link-post">
                            <?php esc_html_e('Cancel', 'smo-social'); ?>
                        </button>
                        <button type="button" class="smo-btn smo-btn-primary" id="submit-link-post">
                            <span class="dashicons dashicons-share"></span>
                            <?php esc_html_e('Create Link Post', 'smo-social'); ?>
                        </button>
                    </div>
                </div>

                <!-- Recent Link Posts -->
                <?php if (!empty($data['recent_posts'])): ?>
                    <div class="smo-recent-links">
                        <h4><?php esc_html_e('Recent Link Posts', 'smo-social'); ?></h4>
                        <div class="smo-links-list">
                            <?php foreach ($data['recent_posts'] as $post):
                                $metadata = json_decode($post['metadata'] ?? '{}', true);
                                $link_url = $metadata['link_url'] ?? '';
                                $link_title = $metadata['link_title'] ?? $post['title'];
                                ?>
                                <div class="smo-link-item">
                                    <div class="smo-link-icon">
                                        <span class="dashicons dashicons-admin-links"></span>
                                    </div>
                                    <div class="smo-link-content">
                                        <div class="smo-link-title"><?php echo esc_html($link_title); ?></div>
                                        <div class="smo-link-url"><?php echo esc_html($link_url); ?></div>
                                        <div class="smo-link-meta">
                                            <span class="smo-link-status smo-status-<?php echo esc_attr($post['status']); ?>">
                                                <?php echo esc_html(ucfirst($post['status'])); ?>
                                            </span>
                                            <span class="smo-link-date">
                                                <?php echo esc_html(human_time_diff(strtotime($post['created_at']), current_time('timestamp'))); ?>
                                                ago
                                            </span>
                                        </div>
                                    </div>
                                    <div class="smo-link-actions">
                                        <button class="smo-btn-icon" data-action="edit"
                                            data-post-id="<?php echo esc_attr($post['id']); ?>"
                                            title="<?php esc_attr_e('Edit', 'smo-social'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button class="smo-btn-icon" data-action="delete"
                                            data-post-id="<?php echo esc_attr($post['id']); ?>"
                                            title="<?php esc_attr_e('Delete', 'smo-social'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="smo-empty-state">
                        <span class="dashicons dashicons-admin-links"></span>
                        <h4><?php esc_html_e('No Link Posts Yet', 'smo-social'); ?></h4>
                        <p><?php esc_html_e('Create your first link post to share content across your social media platforms.', 'smo-social'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Show/hide link post form
                $('#create-link-post').on('click', function () {
                    $('#link-post-form').slideDown();
                    $('.smo-recent-links, .smo-empty-state').slideUp();
                });

                $('#close-link-form, #cancel-link-post').on('click', function () {
                    $('#link-post-form').slideUp();
                    $('.smo-recent-links, .smo-empty-state').slideDown();
                    resetLinkForm();
                });

                // Schedule type toggle
                $('input[name="schedule_type"]').on('change', function () {
                    if ($(this).val() === 'schedule') {
                        $('#schedule-datetime').slideDown();
                    } else {
                        $('#schedule-datetime').slideUp();
                    }
                });

                // Character counter
                $('#link-caption').on('input', function () {
                    var count = $(this).val().length;
                    $('#caption-count').text(count);
                    if (count > 500) {
                        $(this).addClass('smo-error');
                    } else {
                        $(this).removeClass('smo-error');
                    }
                });

                // Fetch link preview
                $('#fetch-link-preview').on('click', function () {
                    var url = $('#link-url').val();
                    if (!url) {
                        alert('<?php esc_html_e('Please enter a URL first', 'smo-social'); ?>');
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Fetching...');

                    $.ajax({
                        url: smo_social_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'smo_fetch_link_preview',
                            nonce: smo_social_ajax.nonce,
                            url: url
                        },
                        success: function (response) {
                            if (response.success && response.data) {
                                var data = response.data;
                                $('#preview-image').attr('src', data.image || '');
                                $('#preview-title').text(data.title || '');
                                $('#preview-description').text(data.description || '');
                                $('#preview-domain').text(data.domain || '');
                                $('#link-preview').slideDown();

                                // Auto-fill caption if empty
                                if (!$('#link-caption').val() && data.title) {
                                    $('#link-caption').val(data.title + '\n\n' + url);
                                    $('#caption-count').text($('#link-caption').val().length);
                                }
                            }
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Fetch Preview');
                        },
                        error: function () {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Fetch Preview');
                        }
                    });
                });

                // Submit link post
                $('#submit-link-post').on('click', function () {
                    var url = $('#link-url').val();
                    var caption = $('#link-caption').val();
                    var platforms = $('input[name="link_platforms[]"]:checked').map(function () {
                        return $(this).val();
                    }).get();
                    var scheduleType = $('input[name="schedule_type"]:checked').val();
                    var scheduledTime = $('#scheduled-time').val();

                    // Validation
                    if (!url) {
                        alert('<?php esc_html_e('Please enter a URL', 'smo-social'); ?>');
                        return;
                    }

                    if (platforms.length === 0) {
                        alert('<?php esc_html_e('Please select at least one platform', 'smo-social'); ?>');
                        return;
                    }

                    if (scheduleType === 'schedule' && !scheduledTime) {
                        alert('<?php esc_html_e('Please select a date and time', 'smo-social'); ?>');
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creating...');

                    $.ajax({
                        url: smo_social_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'smo_create_link_post',
                            nonce: smo_social_ajax.nonce,
                            url: url,
                            caption: caption,
                            platforms: platforms,
                            schedule_type: scheduleType,
                            scheduled_time: scheduledTime,
                            auto_hashtags: $('#link-auto-hashtags').is(':checked'),
                            optimize_content: $('#link-optimize-content').is(':checked')
                        },
                        success: function (response) {
                            if (response.success) {
                                alert('<?php esc_html_e('Link post created successfully!', 'smo-social'); ?>');
                                location.reload();
                            } else {
                                alert(response.data || '<?php esc_html_e('Error creating link post', 'smo-social'); ?>');
                            }
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-share"></span> Create Link Post');
                        },
                        error: function () {
                            alert('<?php esc_html_e('Error creating link post', 'smo-social'); ?>');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-share"></span> Create Link Post');
                        }
                    });
                });

                // Delete link post
                $(document).on('click', '[data-action="delete"]', function () {
                    if (!confirm('<?php esc_html_e('Are you sure you want to delete this link post?', 'smo-social'); ?>')) {
                        return;
                    }

                    var postId = $(this).data('post-id');
                    var $item = $(this).closest('.smo-link-item');

                    $.ajax({
                        url: smo_social_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'smo_delete_link_post',
                            nonce: smo_social_ajax.nonce,
                            post_id: postId
                        },
                        success: function (response) {
                            if (response.success) {
                                $item.fadeOut(function () {
                                    $(this).remove();
                                });
                            }
                        }
                    });
                });

                function resetLinkForm() {
                    $('#link-url').val('');
                    $('#link-caption').val('');
                    $('#caption-count').text('0');
                    $('input[name="link_platforms[]"]').prop('checked', false);
                    $('input[name="schedule_type"][value="now"]').prop('checked', true);
                    $('#schedule-datetime').hide();
                    $('#link-preview').hide();
                }
            });
        </script>
<?php
        return ob_get_clean();
    }

    /**
     * Get widget settings schema
     */
    public function get_settings_schema()
    {
        return array(
            'show_stats' => array(
                'type' => 'checkbox',
                'label' => __('Show Statistics', 'smo-social'),
                'default' => true
            ),
            'recent_limit' => array(
                'type' => 'number',
                'label' => __('Number of Recent Posts to Show', 'smo-social'),
                'default' => 10,
                'min' => 1,
                'max' => 50
            ),
            'auto_fetch_preview' => array(
                'type' => 'checkbox',
                'label' => __('Auto-fetch Link Preview', 'smo-social'),
                'default' => true
            )
        );
    }
}
