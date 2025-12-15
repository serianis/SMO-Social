<?php
namespace SMO_Social\Scheduling;

class Scheduler {
    private $platform_manager;
    private $queue_manager;

    public function __construct() {
        $this->platform_manager = new \SMO_Social\Platforms\Manager();
        $this->queue_manager = new QueueManager();
        
        // Check if WordPress functions are available before initializing hooks
        if ($this->is_wordpress_available()) {
            // Defer hook initialization to WordPress init to ensure WordPress functions are available
            // Using a closure to avoid visibility issues
            \add_action('init', function() {
                if (method_exists($this, 'init_hooks') && is_callable(array($this, 'init_hooks'))) {
                    $this->init_hooks();
                }
            });
        } else {
            // Log warning for debugging
            error_log('SMO Social: WordPress functions not available - hooks not initialized');
        }
    }
    
    /**
     * Check if WordPress functions are available
     */
    private function is_wordpress_available() {
        return function_exists('add_action') && 
               function_exists('wp_next_scheduled') && 
               function_exists('wp_schedule_event');
    }

    public function init_hooks() {
        // Verify method is being called correctly
        if (!method_exists($this, 'init_hooks')) {
            error_log('SMO Social: init_hooks method does not exist');
            return;
        }
        
        if (!is_callable(array($this, 'init_hooks'))) {
            error_log('SMO Social: init_hooks method is not callable');
            return;
        }
        
        error_log('SMO Social: init_hooks called successfully');
        
        try {
            // WordPress cron hooks
            \add_action('smo_process_queue', array($this, 'process_queue'));
            \add_action('smo_cleanup_failed_posts', array($this, 'cleanup_failed_posts'));
            \add_action('smo_check_post_schedules', array($this, 'check_post_schedules'));
            
            // Schedule cron events
            \add_action('wp', array($this, 'schedule_cron_events'));
            
            // AJAX handlers
            \add_action('wp_ajax_smo_schedule_post', array($this, 'ajax_schedule_post'));
            \add_action('wp_ajax_smo_save_draft', array($this, 'ajax_save_draft'));
            \add_action('wp_ajax_smo_get_scheduled_posts', array($this, 'ajax_get_scheduled_posts'));
            \add_action('wp_ajax_smo_cancel_scheduled_post', array($this, 'ajax_cancel_scheduled_post'));
            \add_action('wp_ajax_smo_retry_failed_post', array($this, 'ajax_retry_failed_post'));
            \add_action('wp_ajax_smo_bulk_action', array($this, 'ajax_bulk_action'));
            
            // WordPress hooks
            \add_action('save_post', array($this, 'auto_schedule_from_post'), 10, 2);
            
            error_log('SMO Social: All hooks registered successfully');
            
        } catch (\Exception $e) {
            error_log('SMO Social: Error initializing hooks: ' . $e->getMessage());
        }
    }

    // Schedule cron events
    public function schedule_cron_events() {
        if (!\wp_next_scheduled('smo_process_queue')) {
            \wp_schedule_event(time(), 'every_5_minutes', 'smo_process_queue');
        }
        
        if (!\wp_next_scheduled('smo_cleanup_failed_posts')) {
            \wp_schedule_event(time(), 'hourly', 'smo_cleanup_failed_posts');
        }
        
        if (!\wp_next_scheduled('smo_check_post_schedules')) {
            \wp_schedule_event(time(), 'every_minute', 'smo_check_post_schedules');
        }
    }

    // Main queue processing
    public function process_queue() {
        $this->log_activity('QUEUE_PROCESS_START');
        
        try {
            // Get pending items from queue
            $pending_items = $this->queue_manager->get_pending_items(10);
            
            foreach ($pending_items as $item) {
                $this->process_queue_item($item);
            }
            
            // Process scheduled posts
            $this->process_scheduled_posts();
            
            $this->log_activity('QUEUE_PROCESS_COMPLETE', null, null, array(
                'processed_items' => count($pending_items)
            ));
            
        } catch (\Exception $e) {
            $this->log_activity('QUEUE_PROCESS_ERROR', null, null, array(
                'error' => $e->getMessage()
            ));
        }
    }

    private function process_queue_item($queue_item) {
        // Update status to processing
        $this->queue_manager->update_status($queue_item->id, 'processing');
        
        try {
            // Get the scheduled post
            $scheduled_post = $this->get_scheduled_post($queue_item->scheduled_post_id);
            
            if (!$scheduled_post) {
                throw new \Exception('Scheduled post not found');
            }
            
            // Get platform
            $platform = $this->platform_manager->get_platform($queue_item->platform_slug);
            if (!$platform) {
                throw new \Exception('Platform not found: ' . $queue_item->platform_slug);
            }
            
            // Prepare post data
            $post_data = $this->prepare_post_data($scheduled_post);
            
            // Post to platform
            $result = $platform->post($post_data['content'], $post_data['options']);
            
            if ($result['success']) {
                // Update post with platform response
                $this->update_scheduled_post_response(
                    $scheduled_post->id,
                    $queue_item->platform_slug,
                    $result
                );
                
                // Mark queue item as completed
                $completed_time = $this->is_wordpress_available() ? \current_time('mysql') : date('Y-m-d H:i:s');
                $this->queue_manager->update_status($queue_item->id, 'completed', null, $completed_time);
                
                $this->log_activity('POST_PUBLISHED_SUCCESS', 'post', $scheduled_post->id, array(
                    'platform' => $queue_item->platform_slug,
                    'post_id' => $result['post_id']
                ));
                
            } else {
                // Handle failure
                $this->handle_post_failure($queue_item, $result['error']);
            }
            
        } catch (\Exception $e) {
            $this->handle_post_failure($queue_item, $e->getMessage());
        }
    }

    private function prepare_post_data($scheduled_post) {
        $data = array(
            'content' => $scheduled_post->content,
            'options' => array()
        );
        
        // Add media if available
        if (!empty($scheduled_post->media_urls)) {
            $media_urls = json_decode($scheduled_post->media_urls, true);
            if (is_array($media_urls)) {
                foreach ($media_urls as $media) {
                    if ($media['type'] === 'image') {
                        $data['options']['images'][] = $media['url'];
                    } elseif ($media['type'] === 'video') {
                        $data['options']['videos'][] = $media['url'];
                    }
                }
            }
        }
        
        // Add platform-specific options
        $data['options']['scheduled_time'] = $scheduled_post->scheduled_time;
        
        return $data;
    }

    private function handle_post_failure($queue_item, $error_message) {
        $attempts = $queue_item->attempts + 1;
        $max_attempts = $queue_item->max_attempts;
        
        if ($attempts >= $max_attempts) {
            // Max attempts reached - mark as failed
            $this->queue_manager->update_status($queue_item->id, 'failed', $error_message);
            $this->update_scheduled_post_status($queue_item->scheduled_post_id, 'failed');
            
            $this->log_activity('POST_PUBLISH_FAILED', 'post', $queue_item->scheduled_post_id, array(
                'platform' => $queue_item->platform_slug,
                'error' => $error_message,
                'attempts' => $attempts
            ));
            
        } else {
            // Retry with exponential backoff
            $retry_delay = pow(2, $attempts) * 300; // 5 minutes * 2^attempts
            $retry_time = date('Y-m-d H:i:s', time() + $retry_delay);
            
            $this->queue_manager->update_status($queue_item->id, 'retry', $error_message, $retry_time);
            $this->queue_manager->increment_attempts($queue_item->id);
            
            $this->log_activity('POST_RETRY_SCHEDULED', 'post', $queue_item->scheduled_post_id, array(
                'platform' => $queue_item->platform_slug,
                'retry_time' => $retry_time,
                'attempt' => $attempts
            ));
        }
    }

    // Scheduled posts processing
    private function process_scheduled_posts() {
        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $queue_table = $wpdb->prefix . 'smo_queue';
        
        // Get posts that are due for publishing
        $now = $this->is_wordpress_available() ? \current_time('mysql') : date('Y-m-d H:i:s');
        $scheduled_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $posts_table 
             WHERE status = 'scheduled' 
             AND scheduled_time <= %s 
             ORDER BY priority DESC, scheduled_time ASC",
            $now
        ));
        
        foreach ($scheduled_posts as $post) {
            // Update post status to publishing
            $wpdb->update(
                $posts_table,
                array('status' => 'publishing'),
                array('id' => $post->id)
            );
            
            // Create queue entries for each platform
            $platforms = explode(',', $post->platforms);
            foreach ($platforms as $platform_slug) {
                $platform_slug = trim($platform_slug);
                
                // Check if queue entry already exists
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $queue_table 
                     WHERE scheduled_post_id = %d 
                     AND platform_slug = %s 
                     AND status IN ('pending', 'processing')",
                    $post->id,
                    $platform_slug
                ));
                
                if (!$existing) {
                    $wpdb->insert(
                        $queue_table,
                        array(
                            'scheduled_post_id' => $post->id,
                            'platform_slug' => $platform_slug,
                            'priority' => $post->priority,
                            'scheduled_for' => $now,
                            'status' => 'pending'
                        )
                    );
                }
            }
        }
    }

    // Check post schedules (called every minute)
    public function check_post_schedules() {
        // This method can be used for additional schedule validation
        // For example, checking if posts need to be moved to different time slots
        // based on platform rate limits or user preferences
    }

    // Schedule a new post
    public function schedule_post($title, $content, $platforms, $scheduled_time, $options = array()) {
        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Validate input
        if (empty($content) || empty($platforms) || empty($scheduled_time)) {
            throw new \InvalidArgumentException('Content, platforms, and scheduled time are required');
        }
        
        // Validate scheduled time is in the future
        if (strtotime($scheduled_time) <= time()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future');
        }
        
        // Validate platforms
        $valid_platforms = array();
        foreach ($platforms as $platform_slug) {
            $platform = $this->platform_manager->get_platform($platform_slug);
            if (!$platform) {
                throw new \InvalidArgumentException('Invalid platform: ' . $platform_slug);
            }
            
            // Check rate limits
            if (!$this->check_platform_rate_limit($platform)) {
                throw new \Exception('Rate limit exceeded for platform: ' . $platform->get_name());
            }
            
            $valid_platforms[] = $platform_slug;
        }
        
        // Insert scheduled post
        $result = $wpdb->insert(
            $posts_table,
            array(
                'title' => $title,
                'content' => $content,
                'media_urls' => !empty($options['media']) ? json_encode($options['media']) : null,
                'platforms' => implode(',', $valid_platforms),
                'scheduled_time' => $scheduled_time,
                'status' => 'scheduled',
                'priority' => isset($options['priority']) ? $options['priority'] : 'normal'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to schedule post');
        }
        
        $post_id = $wpdb->insert_id;
        
        // Create content variants if requested
        if (!empty($options['create_variants'])) {
            $this->create_content_variants($post_id, $content, $valid_platforms);
        }
        
        $this->log_activity('POST_SCHEDULED', 'post', $post_id, array(
            'platforms' => $valid_platforms,
            'scheduled_time' => $scheduled_time
        ));
        
        return $post_id;
    }

    // Save as draft
    public function save_draft($title, $content, $platforms, $options = array()) {
        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Validate platforms
        $valid_platforms = array();
        foreach ($platforms as $platform_slug) {
            $platform = $this->platform_manager->get_platform($platform_slug);
            if ($platform) {
                $valid_platforms[] = $platform_slug;
            }
        }
        
        // Insert draft
        $result = $wpdb->insert(
            $posts_table,
            array(
                'title' => $title,
                'content' => $content,
                'media_urls' => !empty($options['media']) ? json_encode($options['media']) : null,
                'platforms' => implode(',', $valid_platforms),
                'scheduled_time' => null,
                'status' => 'draft'
            )
        );
        
        if ($result === false) {
            throw new \Exception('Failed to save draft');
        }
        
        $post_id = $wpdb->insert_id;
        
        $this->log_activity('DRAFT_SAVED', 'post', $post_id);
        
        return $post_id;
    }

    // Bulk scheduling
    public function bulk_schedule($posts_data) {
        $results = array();
        
        foreach ($posts_data as $post_data) {
            try {
                $post_id = $this->schedule_post(
                    $post_data['title'],
                    $post_data['content'],
                    $post_data['platforms'],
                    $post_data['scheduled_time'],
                    $post_data['options'] ?? array()
                );
                
                $results[] = array(
                    'success' => true,
                    'post_id' => $post_id,
                    'data' => $post_data
                );
                
            } catch (\Exception $e) {
                $results[] = array(
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $post_data
                );
            }
        }
        
        return $results;
    }

    // Helper methods
    private function get_scheduled_post($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $post_id
        ));
    }

    private function update_scheduled_post_status($post_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $post_id),
            array('%s'),
            array('%d')
        );
    }

    private function update_scheduled_post_response($post_id, $platform, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        $post = $this->get_scheduled_post($post_id);
        
        $responses = array();
        if (!empty($post->platform_responses)) {
            $responses = json_decode($post->platform_responses, true) ?: array();
        }
        
        $responses[$platform] = $response;
        
        // Check if all platforms have responded
        $all_responded = true;
        $platforms = explode(',', $post->platforms);
        
        foreach ($platforms as $platform_slug) {
            $platform_slug = trim($platform_slug);
            if (!isset($responses[$platform_slug]['success'])) {
                $all_responded = false;
                break;
            }
        }
        
        $new_status = $all_responded ? 'published' : 'publishing';
        
        $wpdb->update(
            $table_name,
            array(
                'platform_responses' => json_encode($responses),
                'status' => $new_status,
                'post_id' => $response['post_id'] ?? $post->post_id
            ),
            array('id' => $post_id)
        );
    }

    private function check_platform_rate_limit($platform) {
        // Implementation for rate limiting check
        // This would integrate with the Platform class rate limiting
        return true; // Simplified for now
    }

    private function create_content_variants($post_id, $content, $platforms) {
        global $wpdb;
        
        $variants_table = $wpdb->prefix . 'smo_content_variants';
        
        $variant_types = array(
            'short' => 'Short version optimized for character limits',
            'long' => 'Long-form version with additional context',
            'professional' => 'Professional tone for business platforms',
            'casual' => 'Casual, friendly tone for personal platforms',
            'hashtag_heavy' => 'Version with optimized hashtags'
        );
        
        foreach ($variant_types as $type => $description) {
            $wpdb->insert(
                $variants_table,
                array(
                    'scheduled_post_id' => $post_id,
                    'variant_type' => $type,
                    'content' => $this->generate_variant($content, $type),
                    'created_by' => 'ai'
                )
            );
        }
    }

    private function generate_variant($content, $type) {
        // Basic variant generation - would integrate with AI later
        switch ($type) {
            case 'short':
                return substr($content, 0, 280) . (strlen($content) > 280 ? '...' : '');
            case 'long':
                return $content . "\n\n#SocialMedia #Marketing #Content";
            case 'professional':
                return $this->make_professional($content);
            case 'casual':
                return $this->make_casual($content);
            case 'hashtag_heavy':
                return $content . "\n\n#marketing #social #content #strategy #growth";
            default:
                return $content;
        }
    }

    private function make_professional($content) {
        // Remove excessive punctuation and make more formal
        return preg_replace('/!+/', '.', $content);
    }

    private function make_casual($content) {
        // Add casual elements
        return $content . " 😊";
    }

    // Cleanup failed posts
    public function cleanup_failed_posts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Delete failed posts older than 30 days
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE status = 'failed' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ));
        
        // Clean up old queue entries
        $queue_table = $wpdb->prefix . 'smo_queue';
        $wpdb->query(
            "DELETE FROM $queue_table 
             WHERE status IN ('completed', 'failed') 
             AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    // AJAX handlers
    public function ajax_schedule_post() {
        if ($this->is_wordpress_available()) {
            \check_ajax_referer('smo_social_nonce', 'nonce');
            
            if (!\current_user_can('edit_smo_posts')) {
                \wp_send_json_error('Insufficient permissions');
            }
        }
        
        try {
            $title = $this->is_wordpress_available() ? \sanitize_text_field(filter_input(INPUT_POST, 'title') ?? '') : (filter_input(INPUT_POST, 'title') ?? '');
            $content = $this->is_wordpress_available() ? \sanitize_textarea_field(filter_input(INPUT_POST, 'content') ?? '') : (filter_input(INPUT_POST, 'content') ?? '');
$platforms = $this->is_wordpress_available() ? array_map('\sanitize_text_field', filter_input(INPUT_POST, 'platforms', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []) : array_map('sanitize_text_field', filter_input(INPUT_POST, 'platforms', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []);
$scheduled_time = $this->is_wordpress_available() ? \sanitize_text_field(filter_input(INPUT_POST, 'scheduled_time') ?? '') : (filter_input(INPUT_POST, 'scheduled_time') ?? '');
            
            $post_id = $this->schedule_post($title, $content, $platforms, $scheduled_time);
            
            if ($this->is_wordpress_available()) {
                \wp_send_json_success(array('post_id' => $post_id));
            } else {
                echo json_encode(array('success' => true, 'post_id' => $post_id));
            }
            
        } catch (\Exception $e) {
            if ($this->is_wordpress_available()) {
                \wp_send_json_error($e->getMessage());
            } else {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
        }
    }

    public function ajax_save_draft() {
        
        if ($this->is_wordpress_available()) {
            \check_ajax_referer('smo_social_nonce', 'nonce');
            
            if (!\current_user_can('edit_smo_posts')) {
                \wp_send_json_error('Insufficient permissions');
            }
        }
        
        try {
            $title = $this->is_wordpress_available() ? \sanitize_text_field(filter_input(INPUT_POST, 'title') ?? '') : (filter_input(INPUT_POST, 'title') ?? '');
            $content = $this->is_wordpress_available() ? \sanitize_textarea_field(filter_input(INPUT_POST, 'content') ?? '') : (filter_input(INPUT_POST, 'content') ?? '');
            $platforms = $this->is_wordpress_available() ? array_map('\sanitize_text_field', filter_input(INPUT_POST, 'platforms', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []) : array_map('sanitize_text_field', filter_input(INPUT_POST, 'platforms', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []);
            
            $post_id = $this->save_draft($title, $content, $platforms);
            
            if ($this->is_wordpress_available()) {
                \wp_send_json_success(array('post_id' => $post_id));
            } else {
                echo json_encode(array('success' => true, 'post_id' => $post_id));
            }
            
        } catch (\Exception $e) {
            if ($this->is_wordpress_available()) {
                \wp_send_json_error($e->getMessage());
            } else {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
        }
    }

    public function ajax_get_scheduled_posts() {
        if ($this->is_wordpress_available()) {
            \check_ajax_referer('smo_social_nonce', 'nonce');
            
            if (!\current_user_can('edit_smo_posts')) {
                \wp_send_json_error('Insufficient permissions');
            }
        }
        
        try {
            global $wpdb;
            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
            
            // Get filter parameters
$status = $this->is_wordpress_available() ? \sanitize_text_field(filter_input(INPUT_POST, 'status') ?? '') : (filter_input(INPUT_POST, 'status') ?? '');
$platform = $this->is_wordpress_available() ? \sanitize_text_field(filter_input(INPUT_POST, 'platform') ?? '') : (filter_input(INPUT_POST, 'platform') ?? '');
$limit = $this->is_wordpress_available() ? (int) \sanitize_text_field(filter_input(INPUT_POST, 'limit') ?? '50') : (int) (filter_input(INPUT_POST, 'limit') ?? 50);
            
            // Build query
            $query = "SELECT * FROM $posts_table WHERE 1=1";
            $args = array();
            
            if (!empty($status)) {
                $query .= " AND status = %s";
                $args[] = $status;
            }
            
            if (!empty($platform)) {
                $query .= " AND FIND_IN_SET(%s, platforms)";
                $args[] = $platform;
            }
            
            $query .= " ORDER BY created_at DESC LIMIT %d";
            $args[] = $limit;
            
            // Prepare and execute query
            if (!empty($args)) {
                $posts = $wpdb->get_results($wpdb->prepare($query, $args));
            } else {
                $posts = $wpdb->get_results($wpdb->prepare($query, $limit));
            }
            
            // Format response
            $formatted_posts = array();
            foreach ($posts as $post) {
                $formatted_posts[] = array(
                    'id' => $post->id,
                    'title' => $post->title,
                    'content' => $post->content,
                    'platforms' => explode(',', $post->platforms),
                    'scheduled_time' => $post->scheduled_time,
                    'status' => $post->status,
                    'priority' => $post->priority,
                    'created_at' => $post->created_at,
                    'media_urls' => !empty($post->media_urls) ? json_decode($post->media_urls, true) : array()
                );
            }
            
            if ($this->is_wordpress_available()) {
                \wp_send_json_success(array(
                    'posts' => $formatted_posts,
                    'total' => count($formatted_posts)
                ));
            } else {
                echo json_encode(array(
                    'success' => true,
                    'posts' => $formatted_posts,
                    'total' => count($formatted_posts)
                ));
            }
            
        } catch (\Exception $e) {
            if ($this->is_wordpress_available()) {
                \wp_send_json_error($e->getMessage());
            } else {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
        }
    }

    public function ajax_cancel_scheduled_post() {
        if ($this->is_wordpress_available()) {
            \check_ajax_referer('smo_social_nonce', 'nonce');
            
            if (!\current_user_can('edit_smo_posts')) {
                \wp_send_json_error('Insufficient permissions');
            }
        }
        
        try {
            $post_id = $this->is_wordpress_available() ? (int) \sanitize_text_field(filter_input(INPUT_POST, 'post_id') ?? 0) : (int) (filter_input(INPUT_POST, 'post_id') ?? 0);
            
            if (empty($post_id)) {
                throw new \Exception('Invalid post ID');
            }
            
            global $wpdb;
            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
            
            // Check if post exists and is scheduled
            $post = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $posts_table WHERE id = %d",
                $post_id
            ));
            
            if (!$post) {
                throw new \Exception('Post not found');
            }
            
            if ($post->status !== 'scheduled') {
                throw new \Exception('Only scheduled posts can be cancelled');
            }
            
            // Update status to cancelled
            $wpdb->update(
                $posts_table,
                array('status' => 'cancelled'),
                array('id' => $post_id),
                array('%s'),
                array('%d')
            );
            
            // Cancel any pending queue items
            $queue_table = $wpdb->prefix . 'smo_queue';
            $wpdb->update(
                $queue_table,
                array('status' => 'cancelled'),
                array(
                    'scheduled_post_id' => $post_id,
                    'status' => 'pending'
                ),
                array('%s'),
                array('%d', '%s')
            );
            
            if ($this->is_wordpress_available()) {
                \wp_send_json_success(array('message' => 'Post cancelled successfully'));
            } else {
                echo json_encode(array('success' => true, 'message' => 'Post cancelled successfully'));
            }
            
        } catch (\Exception $e) {
            if ($this->is_wordpress_available()) {
                \wp_send_json_error($e->getMessage());
            } else {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
        }
    }

    public function ajax_retry_failed_post() {
        if ($this->is_wordpress_available()) {
            \check_ajax_referer('smo_social_nonce', 'nonce');
            
            if (!\current_user_can('edit_smo_posts')) {
                \wp_send_json_error('Insufficient permissions');
            }
        }
        
        try {
            $post_id = $this->is_wordpress_available() ? (int) \sanitize_text_field(filter_input(INPUT_POST, 'post_id') ?? 0) : (int) (filter_input(INPUT_POST, 'post_id') ?? 0);
            
            if (empty($post_id)) {
                throw new \Exception('Invalid post ID');
            }
            
            global $wpdb;
            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
            $queue_table = $wpdb->prefix . 'smo_queue';
            
            // Check if post exists and is failed
            $post = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $posts_table WHERE id = %d AND status = 'failed'",
                $post_id
            ));
            
            if (!$post) {
                throw new \Exception('Failed post not found');
            }
            
            // Reset post status to scheduled
            $wpdb->update(
                $posts_table,
                array('status' => 'scheduled'),
                array('id' => $post_id),
                array('%s'),
                array('%d')
            );
            
            // Reset queue items to pending
            $wpdb->update(
                $queue_table,
                array('status' => 'pending', 'attempts' => 0, 'error_message' => null),
                array('scheduled_post_id' => $post_id),
                array('%s', '%d', '%s'),
                array('%d')
            );
            
            if ($this->is_wordpress_available()) {
                \wp_send_json_success(array('message' => 'Post retry scheduled successfully'));
            } else {
                echo json_encode(array('success' => true, 'message' => 'Post retry scheduled successfully'));
            }
            
        } catch (\Exception $e) {
            if ($this->is_wordpress_available()) {
                \wp_send_json_error($e->getMessage());
            } else {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
        }
    }

    public function ajax_bulk_action() {
        if ($this->is_wordpress_available()) {
            \check_ajax_referer('smo_social_nonce', 'nonce');
            
            if (!\current_user_can('edit_smo_posts')) {
                \wp_send_json_error('Insufficient permissions');
            }
        }
        
        try {
$action = $this->is_wordpress_available() ? \sanitize_text_field(filter_input(INPUT_POST, 'action') ?? '') : (filter_input(INPUT_POST, 'action') ?? '');
$post_ids = $this->is_wordpress_available() ? \sanitize_text_field(filter_input(INPUT_POST, 'post_ids') ?? '') : (filter_input(INPUT_POST, 'post_ids') ?? '');
            
            if (empty($action) || empty($post_ids)) {
                throw new \Exception('Action and post IDs are required');
            }
            
            $post_ids = json_decode($post_ids, true);
            if (!is_array($post_ids)) {
                throw new \Exception('Invalid post IDs format');
            }
            
            global $wpdb;
            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
            $queue_table = $wpdb->prefix . 'smo_queue';
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($post_ids as $post_id) {
                try {
                    switch ($action) {
                        case 'cancel':
                            // Cancel post logic
                            $wpdb->update(
                                $posts_table,
                                array('status' => 'cancelled'),
                                array('id' => $post_id),
                                array('%s'),
                                array('%d')
                            );
                            $wpdb->update(
                                $queue_table,
                                array('status' => 'cancelled'),
                                array(
                                    'scheduled_post_id' => $post_id,
                                    'status' => 'pending'
                                ),
                                array('%s'),
                                array('%d', '%s')
                            );
                            break;
                            
                        case 'retry':
                            // Retry logic
                            $wpdb->update(
                                $posts_table,
                                array('status' => 'scheduled'),
                                array('id' => $post_id),
                                array('%s'),
                                array('%d')
                            );
                            $wpdb->update(
                                $queue_table,
                                array('status' => 'pending', 'attempts' => 0, 'error_message' => null),
                                array('scheduled_post_id' => $post_id),
                                array('%s', '%d', '%s'),
                                array('%d')
                            );
                            break;
                            
                        case 'delete':
                            // Delete post and related queue items
                            $wpdb->delete($posts_table, array('id' => $post_id), array('%d'));
                            $wpdb->delete($queue_table, array('scheduled_post_id' => $post_id), array('%d'));
                            break;
                            
                        default:
                            throw new \Exception('Invalid action: ' . $action);
                    }
                    
                    $success_count++;
                    
                } catch (\Exception $e) {
                    $error_count++;
                }
            }
            
            if ($this->is_wordpress_available()) {
                \wp_send_json_success(array(
                    'message' => sprintf('Bulk action completed: %d success, %d errors', $success_count, $error_count),
                    'success_count' => $success_count,
                    'error_count' => $error_count
                ));
            } else {
                echo json_encode(array(
                    'success' => true,
                    'message' => sprintf('Bulk action completed: %d success, %d errors', $success_count, $error_count),
                    'success_count' => $success_count,
                    'error_count' => $error_count
                ));
            }
            
        } catch (\Exception $e) {
            if ($this->is_wordpress_available()) {
                \wp_send_json_error($e->getMessage());
            } else {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
        }
    }

    private function log_activity($action, $resource_type = null, $resource_id = null, $details = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_activity_logs';
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'action' => $action,
                'resource_type' => $resource_type,
                'resource_id' => $resource_id,
                'details' => json_encode($details),
                'created_at' => current_time('mysql')
            )
        );
    }
}
