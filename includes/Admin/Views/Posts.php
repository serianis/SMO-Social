<?php
namespace SMO_Social\Admin\Views;

class Posts
{
    private $database;
    private $platforms;

    public function __construct($database = null, $platforms = array())
    {
        $this->database = $database ?: new \SMO_Social\Core\DatabaseManager();
        $this->platforms = $platforms;
    }

    public function get_posts($filters = array(), $limit = 50, $offset = 0)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_posts';
        $where_clauses = array();
        $params = array();

        // Build WHERE clause based on filters
        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['platform'])) {
            $where_clauses[] = 'platform_slug = %s';
            $params[] = $filters['platform'];
        }

        if (!empty($filters['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($params, array($limit, $offset))
        );

        $posts = $wpdb->get_results($query, ARRAY_A);

        // Add platform information
        foreach ($posts as &$post) {
            $post['platform_info'] = $this->get_platform_info($post['platform_slug']);
            $post['analytics'] = $this->get_post_analytics($post['id']);
        }

        return $posts ?: array();
    }

    public function get_post($post_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_posts';
        $post = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $post_id),
            ARRAY_A
        );

        if ($post) {
            $post['platform_info'] = $this->get_platform_info($post['platform_slug']);
            $post['analytics'] = $this->get_post_analytics($post['id']);
            $post['comments'] = $this->get_post_comments($post['id']);
        }

        return $post;
    }

    public function create_post($data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_posts';

        $post_data = array(
            'title' => $data['title'] ?? '',
            'content' => $data['content'] ?? '',
            'platform_slug' => $data['platform_slug'] ?? '',
            'user_id' => $data['user_id'] ?? get_current_user_id(),
            'status' => $data['status'] ?? 'draft',
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'media_attachments' => !empty($data['media']) ? json_encode($data['media']) : null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table_name, $post_data);

        if ($result) {
            $post_id = $wpdb->insert_id;
            $post_data['id'] = $post_id;

            // Log the creation
            $this->log_post_activity($post_id, 'created', $post_data);

            return array(
                'success' => true,
                'post_id' => $post_id,
                'post_data' => $post_data
            );
        }

        return array(
            'success' => false,
            'error' => 'Failed to create post'
        );
    }

    public function update_post($post_id, $data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_posts';

        $update_data = array(
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'platform_slug' => $data['platform_slug'] ?? null,
            'status' => $data['status'] ?? null,
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'media_attachments' => isset($data['media']) ? json_encode($data['media']) : null,
            'updated_at' => current_time('mysql')
        );

        // Remove null values
        $update_data = array_filter($update_data, function ($value) {
            return $value !== null;
        });

        $result = $wpdb->update($table_name, $update_data, array('id' => $post_id));

        if ($result !== false) {
            // Log the update
            $this->log_post_activity($post_id, 'updated', $update_data);

            return array(
                'success' => true,
                'post_id' => $post_id
            );
        }

        return array(
            'success' => false,
            'error' => 'Failed to update post'
        );
    }

    public function delete_post($post_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_posts';
        $analytics_table = $wpdb->prefix . 'smo_post_analytics';
        $comments_table = $wpdb->prefix . 'smo_post_comments';

        // Delete related data first
        $wpdb->delete($analytics_table, array('post_id' => $post_id));
        $wpdb->delete($comments_table, array('post_id' => $post_id));

        // Delete the post
        $result = $wpdb->delete($table_name, array('id' => $post_id));

        if ($result !== false) {
            // Log the deletion
            $this->log_post_activity($post_id, 'deleted');

            return array('success' => true);
        }

        return array(
            'success' => false,
            'error' => 'Failed to delete post'
        );
    }

    public function publish_post($post_id, $platform_slug = null)
    {
        // Get post data
        $post = $this->get_post($post_id);

        if (!$post) {
            return array(
                'success' => false,
                'error' => 'Post not found'
            );
        }

        $platform = $platform_slug ?? $post['platform_slug'];

        if (!isset($this->platforms[$platform])) {
            return array(
                'success' => false,
                'error' => 'Platform not available'
            );
        }

        $platform_instance = $this->platforms[$platform];

        // Format content for platform
        $formatted_content = $this->format_content_for_platform($post['content'], $platform);

        // Post to platform
        $result = $platform_instance->post($formatted_content, array(
            'media' => json_decode($post['media_attachments'] ?? '[]', true)
        ));

        if ($result['success']) {
            // Update post status
            $this->update_post($post_id, array(
                'status' => 'published',
                'published_at' => current_time('mysql'),
                'platform_post_id' => $result['post_id'] ?? null
            ));

            return array(
                'success' => true,
                'platform_result' => $result
            );
        }

        return array(
            'success' => false,
            'error' => $result['error']
        );
    }

    public function schedule_post($post_id, $schedule_time)
    {
        return $this->update_post($post_id, array(
            'status' => 'scheduled',
            'scheduled_time' => $schedule_time
        ));
    }

    public function bulk_action($post_ids, $action, $data = array())
    {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        foreach ($post_ids as $post_id) {
            switch ($action) {
                case 'publish':
                    $result = $this->publish_post($post_id);
                    break;
                case 'schedule':
                    $result = $this->schedule_post($post_id, $data['schedule_time'] ?? null);
                    break;
                case 'delete':
                    $result = $this->delete_post($post_id);
                    break;
                case 'update_status':
                    $result = $this->update_post($post_id, array('status' => $data['status']));
                    break;
                default:
                    $result = array('success' => false, 'error' => 'Invalid action');
            }

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = array(
                    'post_id' => $post_id,
                    'error' => $result['error'] ?? 'Unknown error'
                );
            }
        }

        return $results;
    }

    private function get_platform_info($platform_slug)
    {
        if (isset($this->platforms[$platform_slug])) {
            return array(
                'name' => $this->platforms[$platform_slug]->get_name(),
                'slug' => $platform_slug,
                'supports_images' => $this->platforms[$platform_slug]->supports_images(),
                'supports_videos' => $this->platforms[$platform_slug]->supports_videos()
            );
        }

        return array('name' => $platform_slug, 'slug' => $platform_slug);
    }

    private function get_post_analytics($post_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_post_analytics';
        $analytics = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post_id),
            ARRAY_A
        );

        return $analytics ?: array();
    }

    private function get_post_comments($post_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_post_comments';
        $comments = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC", $post_id),
            ARRAY_A
        );

        return $comments ?: array();
    }

    private function format_content_for_platform($content, $platform_slug)
    {
        // Apply platform-specific formatting
        switch ($platform_slug) {
            case 'twitter':
                if (strlen($content) > 280) {
                    $content = substr($content, 0, 277) . '...';
                }
                break;
            case 'instagram':
                // Add hashtags if present
                break;
            case 'linkedin':
                // Professional formatting
                break;
        }

        return $content;
    }

    private function log_post_activity($post_id, $action, $data = array())
    {
        global $wpdb;

        $log_table = $wpdb->prefix . 'smo_post_activity_log';
        $wpdb->insert($log_table, array(
            'post_id' => $post_id,
            'action' => $action,
            'data' => json_encode($data),
            'timestamp' => current_time('mysql')
        ));
    }

    public function get_posts_count($filters = array())
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_posts';
        $where_clauses = array();
        $params = array();

        // Build WHERE clause
        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['platform'])) {
            $where_clauses[] = 'platform_slug = %s';
            $params[] = $filters['platform'];
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name $where_sql", $params);
        return $wpdb->get_var($query) ?: 0;
    }

    /**
     * Render the posts management interface
     */
    public function render()
    {
        $sanitizer = '\SMO_Social\Admin\Helpers\ViewDataSanitizer';
        
        // Get post statistics
        $total_posts = $this->get_posts_count();
        $draft_posts = $this->get_posts_count(['status' => 'draft']);
        $scheduled_posts = $this->get_posts_count(['status' => 'scheduled']);
        $published_posts = $this->get_posts_count(['status' => 'published']);
        
        // Get recent posts
        $recent_posts = $this->get_posts([], 20, 0);
        
        // Normalize posts
        $recent_posts = array_map(function($post) use ($sanitizer) {
            return $sanitizer::normalize_post($post);
        }, $recent_posts ?: array());
        
        // Use Common Layout
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('posts', __('All Posts', 'smo-social'));
            
            // Render standardized gradient header using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_header([
                'icon' => '📝',
                'title' => __('Posts Management', 'smo-social'),
                'subtitle' => __('Manage and monitor all your social media posts', 'smo-social'),
                'actions' => [
                    [
                        'id' => 'smo-bulk-actions',
                        'label' => __('Bulk Actions', 'smo-social'),
                        'icon' => 'admin-tools',
                        'class' => 'smo-btn-secondary'
                    ],
                    [
                        'href' => admin_url('admin.php?page=smo-social-create'),
                        'label' => __('Create Post', 'smo-social'),
                        'icon' => 'plus',
                        'class' => 'smo-btn-primary'
                    ]
                ]
            ]);
            
            // Render standardized stats dashboard using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
                [
                    'icon' => 'admin-post',
                    'value' => $total_posts,
                    'label' => __('Total Posts', 'smo-social'),
                    'trend' => '📊 All Time'
                ],
                [
                    'icon' => 'edit',
                    'value' => $draft_posts,
                    'label' => __('Drafts', 'smo-social'),
                    'trend' => '✏️ In Progress'
                ],
                [
                    'icon' => 'clock',
                    'value' => $scheduled_posts,
                    'label' => __('Scheduled', 'smo-social'),
                    'trend' => '⏰ Pending'
                ],
                [
                    'icon' => 'yes',
                    'value' => $published_posts,
                    'label' => __('Published', 'smo-social'),
                    'trend' => '✅ Live'
                ]
            ]);
            
            // Render standardized quick actions using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_quick_actions([
                [
                    'icon' => 'admin-post',
                    'label' => __('All Posts', 'smo-social'),
                    'id' => 'smo-filter-all'
                ],
                [
                    'icon' => 'edit',
                    'label' => __('Drafts', 'smo-social'),
                    'id' => 'smo-filter-drafts'
                ],
                [
                    'icon' => 'clock',
                    'label' => __('Scheduled', 'smo-social'),
                    'id' => 'smo-filter-scheduled'
                ],
                [
                    'icon' => 'yes',
                    'label' => __('Published', 'smo-social'),
                    'id' => 'smo-filter-published'
                ]
            ]);
        }
        ob_start();
        ?>

        <!-- Main Content -->
        <div class="smo-card">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('All Posts', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <div class="posts-filters" style="display: flex; gap: 10px;">
                        <input type="text" class="smo-form-input" id="smo-search-posts" style="width: 250px;"
                            placeholder="<?php esc_attr_e('Search posts...', 'smo-social'); ?>" />
                        <select class="smo-form-select" id="smo-status-filter" style="width: 150px;">
                            <option value=""><?php esc_html_e('All Status', 'smo-social'); ?></option>
                            <option value="draft"><?php esc_html_e('Draft', 'smo-social'); ?></option>
                            <option value="scheduled"><?php esc_html_e('Scheduled', 'smo-social'); ?></option>
                            <option value="published"><?php esc_html_e('Published', 'smo-social'); ?></option>
                        </select>
                        <select class="smo-form-select" id="smo-platform-filter" style="width: 150px;">
                            <option value=""><?php esc_html_e('All Platforms', 'smo-social'); ?></option>
                            <option value="facebook"><?php esc_html_e('Facebook', 'smo-social'); ?></option>
                            <option value="twitter"><?php esc_html_e('Twitter', 'smo-social'); ?></option>
                            <option value="instagram"><?php esc_html_e('Instagram', 'smo-social'); ?></option>
                            <option value="linkedin"><?php esc_html_e('LinkedIn', 'smo-social'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="smo-card-body">
                <div class="posts-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="select-all-posts"></th>
                                <th><?php esc_html_e('Title', 'smo-social'); ?></th>
                                <th><?php esc_html_e('Platform', 'smo-social'); ?></th>
                                <th><?php esc_html_e('Status', 'smo-social'); ?></th>
                                <th><?php esc_html_e('Scheduled', 'smo-social'); ?></th>
                                <th><?php esc_html_e('Created', 'smo-social'); ?></th>
                                <th><?php esc_html_e('Actions', 'smo-social'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_posts)): ?>
                                <?php foreach ($recent_posts as $post): ?>
                                    <?php
                                    $post_id = $sanitizer::safe_get($post, 'id', '');
                                    $title = $sanitizer::safe_get($post, 'title', __('(No title)', 'smo-social'));
                                    $content = $sanitizer::safe_get($post, 'content', '');
                                    $platform_slug = $sanitizer::safe_get($post, 'platform_slug', '');
                                    $status = $sanitizer::safe_get($post, 'status', 'draft');
                                    $scheduled_time = $sanitizer::safe_get($post, 'scheduled_time', '');
                                    $created_at = $sanitizer::safe_get($post, 'created_at', '');
                                    
                                    $status_class = '';
                                    $status_icon = '';
                                    switch ($status) {
                                        case 'published':
                                            $status_class = 'smo-status-published';
                                            $status_icon = '✅';
                                            break;
                                        case 'scheduled':
                                            $status_class = 'smo-status-scheduled';
                                            $status_icon = '⏰';
                                            break;
                                        case 'draft':
                                            $status_class = 'smo-status-draft';
                                            $status_icon = '✏️';
                                            break;
                                        default:
                                            $status_class = 'smo-status-default';
                                            $status_icon = '📝';
                                    }
                                    ?>
                                    <tr data-post-id="<?php echo esc_attr($post_id); ?>">
                                        <td><input type="checkbox" class="post-checkbox" value="<?php echo esc_attr($post_id); ?>"></td>
                                        <td>
                                            <strong><?php echo esc_html($title); ?></strong>
                                            <br><small><?php echo esc_html(wp_trim_words($content, 10)); ?></small>
                                        </td>
                                        <td>
                                            <span class="dashicons dashicons-<?php echo esc_attr($platform_slug); ?>"></span>
                                            <?php echo esc_html(ucfirst($platform_slug)); ?>
                                        </td>
                                        <td>
                                            <span class="smo-status-badge <?php echo esc_attr($status_class); ?>">
                                                <?php echo $status_icon; ?> <?php echo esc_html(ucfirst($status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $scheduled_time ? esc_html($sanitizer::format_timestamp($scheduled_time, 'Y-m-d H:i')) : '-'; ?></td>
                                        <td><?php echo esc_html($sanitizer::format_date($created_at, 'Y-m-d')); ?></td>
                                        <td>
                                            <button class="button button-small smo-edit-post" data-post-id="<?php echo esc_attr($post_id); ?>">
                                                <?php esc_html_e('Edit', 'smo-social'); ?>
                                            </button>
                                            <button class="button button-small button-link-delete smo-delete-post" data-post-id="<?php echo esc_attr($post_id); ?>">
                                                <?php esc_html_e('Delete', 'smo-social'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="smo-empty-state">
                                        <p><?php _e('No posts found. Create your first post to get started!', 'smo-social'); ?></p>
                                        <a href="<?php echo admin_url('admin.php?page=smo-social-create'); ?>" class="smo-btn smo-btn-primary">
                                            <span class="dashicons dashicons-plus"></span>
                                            <?php _e('Create Post', 'smo-social'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <script>
        jQuery(document).ready(function($) {
            // Select all posts
            $('#select-all-posts').on('change', function() {
                $('.post-checkbox').prop('checked', $(this).is(':checked'));
            });

            // Quick filter buttons
            $('.smo-quick-action-btn').on('click', function() {
                const filterId = $(this).attr('id');
                let filterValue = '';
                
                if (filterId === 'smo-filter-drafts') filterValue = 'draft';
                else if (filterId === 'smo-filter-scheduled') filterValue = 'scheduled';
                else if (filterId === 'smo-filter-published') filterValue = 'published';
                
                $('#smo-status-filter').val(filterValue).trigger('change');
            });

            // Edit post
            $('.smo-edit-post').on('click', function() {
                const postId = $(this).data('post-id');
                window.location.href = '<?php echo admin_url('admin.php?page=smo-social-create&post_id='); ?>' + postId;
            });

            // Delete post
            $('.smo-delete-post').on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to delete this post?', 'smo-social'); ?>')) {
                    return;
                }
                
                const postId = $(this).data('post-id');
                const row = $(this).closest('tr');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_delete_post',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('smo_posts_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            row.fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('<?php _e('Error deleting post', 'smo-social'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        
        <?php
        $content = ob_get_clean();
        echo $content;

        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        }
    }
}
