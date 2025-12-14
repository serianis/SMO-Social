<?php
/**
 * Auto-Publish WordPress Content Manager
 * 
 * Manages automatic publishing of WordPress content to social media platforms
 *
 * @package SMO_Social
 * @subpackage Scheduling
 * @since 1.0.0
 */

namespace SMO_Social\Scheduling;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-Publish Content Manager
 */
class AutoPublishManager {
    
    /**
     * Initialize auto-publish functionality
     */
    public function __construct() {
        // Only add WordPress hooks if in WordPress mode
        if (function_exists('add_action')) {
            add_action('wp_insert_post', array($this, 'handle_new_post'), 10, 3);
            add_action('post_updated', array($this, 'handle_updated_post'), 10, 3);
            add_action('smo_process_auto_publish_queue', array($this, 'process_auto_publish_queue'));
            add_action('smo_auto_publish_cron', array($this, 'run_auto_publish_cron'));
            
            // Schedule auto-publish processing only if WordPress scheduling functions exist
            if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
                if (!wp_next_scheduled('smo_auto_publish_cron')) {
                    wp_schedule_event(time(), 'every_5_minutes', 'smo_auto_publish_cron');
                }
            }
        }
    }
    
    /**
     * Handle new WordPress post creation
     */
    public function handle_new_post($post_id, $post, $update) {
        if ($update || $post->post_status !== 'publish') {
            return;
        }
        
        // Check if post should be auto-published
        if (!$this->should_auto_publish($post_id)) {
            return;
        }
        
        // Add to auto-publish queue
        $this->add_to_auto_publish_queue($post_id, 'new_post');
    }
    
    /**
     * Handle post updates
     */
    public function handle_updated_post($post_id, $post_after, $post_before) {
        if ($post_before->post_status === 'publish' && $post_after->post_status === 'publish') {
            // Post is being updated, check if it's already in queue
            global $wpdb;
            $table_name = $wpdb->prefix . 'smo_auto_publish_content';
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE post_id = %d AND status = 'pending'",
                $post_id
            ));
            
            if (!$existing) {
                $this->add_to_auto_publish_queue($post_id, 'updated_post');
            }
        }
    }
    
    /**
     * Check if post should be auto-published
     */
    private function should_auto_publish($post_id) {
        $settings = get_option('smo_auto_publish_settings', array());

        // Ensure settings is an array
        if (!is_array($settings)) {
            $settings = array();
        }

        // Debug: Log settings type and value
        error_log("SMO Debug: should_auto_publish - settings type: " . gettype($settings) . ", value: " . print_r($settings, true));

        if (empty($settings['enabled'])) {
            return false;
        }
        
        $post = get_post($post_id);
        
        // Check post type
        if (!empty($settings['post_types']) && !in_array($post->post_type, $settings['post_types'])) {
            return false;
        }
        
        // Check categories
        if (!empty($settings['categories']) && !\has_category($settings['categories'], $post_id)) {
            return false;
        }
        
        // Check if post has featured image
        if (!empty($settings['require_featured_image']) && !has_post_thumbnail($post_id)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Add post to auto-publish queue
     */
    private function add_to_auto_publish_queue($post_id, $trigger_type = 'manual') {
        global $wpdb;
        
        $post = get_post($post_id);
        $user_id = get_current_user_id();
        
        // Get auto-publish settings
        $settings = get_option('smo_auto_publish_settings', array(
            'platforms' => array(),
            'delay_minutes' => 0,
            'custom_message' => '',
            'auto_hashtags' => true,
            'auto_optimize' => true
        ));

        // Ensure settings is an array
        if (!is_array($settings)) {
            $settings = array();
        }

        // Ensure platforms is an array
        if (!isset($settings['platforms']) || !is_array($settings['platforms'])) {
            $settings['platforms'] = array();
        }
        
        // Calculate publish date
        $publish_date = current_time('mysql');
        if ($settings['delay_minutes'] > 0) {
            $publish_date = date('Y-m-d H:i:s', strtotime("+{$settings['delay_minutes']} minutes"));
        }
        
        // Prepare data
        $data = array(
            'post_id' => $post_id,
            'user_id' => $user_id,
            'site_id' => get_current_blog_id(),
            'title' => $post->post_title,
            'content' => $this->prepare_content_for_platforms($post),
            'excerpt' => $post->post_excerpt,
            'featured_image_id' => get_post_thumbnail_id($post_id),
            'categories' => implode(',', \wp_get_post_categories($post_id, array('fields' => 'ids'))),
            'tags' => implode(',', \wp_get_post_tags($post_id, array('fields' => 'names'))),
            'publish_date' => $publish_date,
            'platforms' => implode(',', $settings['platforms']),
            'auto_hashtags' => $settings['auto_hashtags'],
            'auto_optimize' => $settings['auto_optimize'],
            'custom_message' => $settings['custom_message'],
            'priority' => 'normal',
            'status' => 'pending'
        );
        
        // Insert into queue
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            // Log activity
            error_log("SMO Social: Added post $post_id to auto-publish queue");
            
            // Trigger immediate processing if delay is 0
            if ($settings['delay_minutes'] == 0) {
                $this->process_auto_publish_queue();
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Prepare content for different platforms
     */
    private function prepare_content_for_platforms($post) {
        $content = $post->post_content;
        $title = $post->post_title;
        $excerpt = $post->post_excerpt ?: wp_trim_words($post->post_content, 30);
        
        // Remove HTML tags for social media
        $content = strip_tags($content);
        $title = strip_tags($title);
        $excerpt = strip_tags($excerpt);
        
        // Combine title and content
        $prepared_content = $title . "\n\n" . $excerpt;
        
        // Add URL
        $post_url = get_permalink($post->ID);
        $prepared_content .= "\n\n" . $post_url;
        
        return $prepared_content;
    }
    
    /**
     * Process auto-publish queue
     */
    public function process_auto_publish_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Get pending items ready to publish
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = 'pending' 
             AND publish_date <= %s
             ORDER BY priority DESC, publish_date ASC
             LIMIT 10",
            current_time('mysql')
        ));
        
        foreach ($items as $item) {
            $this->process_auto_publish_item($item);
        }
    }
    
    /**
     * Process individual auto-publish item
     */
    private function process_auto_publish_item($item) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $platforms = explode(',', $item->platforms);
        
        // Create scheduled posts for each platform
        foreach ($platforms as $platform) {
            $platform = trim($platform);
            
            // Prepare post data
            $post_data = array(
                'title' => $item->title,
                'content' => $this->optimize_content_for_platform($item->content, $platform),
                'scheduled_time' => $item->publish_date,
                'status' => 'scheduled',
                'created_by' => $item->user_id,
                'post_type' => 'auto_publish',
                'priority' => $item->priority
            );
            
            // Insert scheduled post
            $result = $wpdb->insert($posts_table, $post_data);
            
            if ($result) {
                $scheduled_post_id = $wpdb->insert_id;
                
                // Add platform association
                $wpdb->insert(
                    $wpdb->prefix . 'smo_post_platforms',
                    array(
                        'post_id' => $scheduled_post_id,
                        'platform_slug' => $platform
                    )
                );
                
                // Add media if available
                if ($item->featured_image_id) {
                    $wpdb->insert(
                        $wpdb->prefix . 'smo_post_media',
                        array(
                            'post_id' => $scheduled_post_id,
                            'media_id' => $item->featured_image_id,
                            'media_type' => 'image'
                        )
                    );
                }
            }
        }
        
        // Mark auto-publish item as processed
        $wpdb->update(
            $table_name,
            array(
                'status' => 'processed',
                'actual_publish_date' => current_time('mysql')
            ),
            array('id' => $item->id)
        );
        
        error_log("SMO Social: Processed auto-publish item {$item->id}");
    }
    
    /**
     * Optimize content for specific platform
     */
    private function optimize_content_for_platform($content, $platform) {
        $optimized = $content;
        
        // Platform-specific optimizations
        switch ($platform) {
            case 'twitter':
                // Limit to 280 characters
                if (strlen($optimized) > 280) {
                    $optimized = substr($optimized, 0, 277) . '...';
                }
                break;
                
            case 'instagram':
                // Instagram likes longer captions
                // Add hashtags automatically if enabled
                // Note: This would use AI in a real implementation
                break;
                
            case 'linkedin':
                // Professional tone adjustments
                // LinkedIn has a 3000 character limit
                if (strlen($optimized) > 3000) {
                    $optimized = substr($optimized, 0, 2997) . '...';
                }
                break;
        }
        
        return $optimized;
    }
    
    /**
     * Run auto-publish cron job
     */
    public function run_auto_publish_cron() {
        $this->process_auto_publish_queue();
    }
    
    /**
     * Get auto-publish settings
     */
    public function get_settings() {
        return get_option('smo_auto_publish_settings', array(
            'enabled' => false,
            'platforms' => array(),
            'post_types' => array('post'),
            'categories' => array(),
            'delay_minutes' => 0,
            'require_featured_image' => false,
            'auto_hashtags' => true,
            'auto_optimize' => true,
            'custom_message' => ''
        ));
    }
    
    /**
     * Update auto-publish settings
     */
    public function update_settings($settings) {
        $current_settings = $this->get_settings();

        // Ensure settings is an array
        if (!is_array($settings)) {
            $settings = array();
        }

        // Debug: Log types
        error_log("SMO Debug: update_settings - current_settings type: " . gettype($current_settings) . ", settings type: " . gettype($settings));
        error_log("SMO Debug: update_settings - current_settings: " . print_r($current_settings, true) . ", settings: " . print_r($settings, true));

        $updated_settings = array_merge($current_settings, $settings);

        update_option('smo_auto_publish_settings', $updated_settings);

        return $updated_settings;
    }
    
    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' AND publish_date <= NOW() THEN 1 ELSE 0 END) as ready_to_publish
            FROM $table_name",
            ARRAY_A
        );
        
        return array(
            'total' => intval($stats['total']),
            'pending' => intval($stats['pending']),
            'processed' => intval($stats['processed']),
            'failed' => intval($stats['failed']),
            'ready_to_publish' => intval($stats['ready_to_publish'])
        );
    }
    
    /**
     * Get recent auto-publish activity
     */
    public function get_recent_activity($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT apc.*, p.post_title, p.post_type 
            FROM $table_name apc
            LEFT JOIN {$wpdb->posts} p ON apc.post_id = p.ID
            ORDER BY apc.created_at DESC
            LIMIT %d",
            $limit
        ), ARRAY_A);
        
        return $activities;
    }
    
    /**
     * Remove item from queue
     */
    public function remove_from_queue($item_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $item_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Retry failed item
     */
    public function retry_failed_item($item_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_auto_publish_content';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => 'pending',
                'retry_count' => new \stdClass(), // This will be incremented
                'error_message' => null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $item_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Get available post types for auto-publishing
     */
    public function get_available_post_types() {
        $post_types = get_post_types(array(
            'public' => true,
            '_builtin' => false
        ), 'objects');
        
        // Add built-in post types
        $builtin_types = array('post', 'page');
        foreach ($builtin_types as $type) {
            if (post_type_exists($type)) {
                $post_types[$type] = get_post_type_object($type);
            }
        }
        
        return $post_types;
    }
    
    /**
     * Get available categories
     */
    public function get_available_categories() {
        return get_categories(array(
            'hide_empty' => false,
            'number' => 100
        ));
    }
    
    /**
     * Validate auto-publish settings
     */
    public function validate_settings($settings) {
        $errors = array();
        
        if (empty($settings['platforms'])) {
            $errors[] = __('At least one platform must be selected.', 'smo-social');
        }
        
        if ($settings['delay_minutes'] < 0 || $settings['delay_minutes'] > 1440) {
            $errors[] = __('Delay minutes must be between 0 and 1440.', 'smo-social');
        }
        
        return empty($errors) ? true : $errors;
    }
}
