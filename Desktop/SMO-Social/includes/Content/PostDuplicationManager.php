<?php
/**
 * Post Duplication Manager
 * Handles cloning and reusing posts across platforms and campaigns
 */

namespace SMO_Social\Content;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';

/**
 * Post Duplication Manager
 * 
 * Manages post cloning and reuse:
 * - Clone posts to different platforms
 * - Create post templates
 * - Bulk duplicate functionality
 * - Content adaptation for different platforms
 * - Version control for duplicated posts
 */
class PostDuplicationManager {
    
    public $last_error = '';
    
    private $table_names;
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'scheduled_posts' => $wpdb->prefix . 'smo_scheduled_posts',
            'post_templates' => $wpdb->prefix . 'smo_post_templates',
            'duplication_history' => $wpdb->prefix . 'smo_duplication_history'
        );
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_smo_duplicate_post', array($this, 'ajax_duplicate_post'));
        add_action('wp_ajax_smo_create_post_template', array($this, 'ajax_create_post_template'));
        add_action('wp_ajax_smo_get_post_templates', array($this, 'ajax_get_post_templates'));
        add_action('wp_ajax_smo_use_post_template', array($this, 'ajax_use_post_template'));
        add_action('wp_ajax_smo_bulk_duplicate_posts', array($this, 'ajax_bulk_duplicate_posts'));
        add_action('wp_ajax_smo_get_duplication_history', array($this, 'ajax_get_duplication_history'));
    }
    
    /**
     * Duplicate a post
     */
    public function duplicate_post($post_id, $options = array()) {
        global $wpdb;
        
        // Get original post
        $original_post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['scheduled_posts']} WHERE id = %d",
            $post_id
        ));
        
        if (!$original_post) {
            throw new \Exception('Original post not found');
        }
        
        // Prepare duplication options
        $default_options = array(
            'platforms' => array(), // Empty means use original platforms
            'title_suffix' => ' (Copy)',
            'content_suffix' => '',
            'schedule_immediately' => false,
            'adjust_timing' => true,
            'time_adjustment_hours' => 24,
            'adjust_for_platform' => true,
            'create_template' => false,
            'template_name' => ''
        );
        
        $options = wp_parse_args($options, $default_options);
        
        // Determine target platforms
        $target_platforms = !empty($options['platforms']) ? $options['platforms'] : array($original_post->platform);
        
        $duplicated_posts = array();
        
        foreach ($target_platforms as $platform) {
            $duplicated_post_id = $this->create_duplicated_post($original_post, $platform, $options);
            if ($duplicated_post_id) {
                $duplicated_posts[] = $duplicated_post_id;
            }
        }
        
        // Record duplication history
        $this->record_duplication_history($post_id, $duplicated_posts, $options);
        
        // Create template if requested
        if ($options['create_template'] && !empty($duplicated_posts)) {
            $this->create_post_template_from_post($original_post, $options['template_name']);
        }
        
        return $duplicated_posts;
    }
    
    /**
     * Create a duplicated post
     */
    private function create_duplicated_post($original_post, $platform, $options) {
        global $wpdb;
        
        // Prepare duplicated content
        $new_title = $this->adjust_content_for_platform(
            $original_post->title . $options['title_suffix'], 
            $platform
        );
        
        $new_content = $this->adjust_content_for_platform(
            $original_post->content . $options['content_suffix'], 
            $platform
        );
        
        // Determine scheduling
        $scheduled_time = null;
        $status = 'draft';
        
        if ($options['schedule_immediately']) {
            $scheduled_time = $this->calculate_optimal_time($platform);
            $status = 'scheduled';
        } elseif ($options['adjust_timing']) {
            $scheduled_time = $this->adjust_scheduling($original_post->scheduled_time, $options['time_adjustment_hours']);
            $status = $scheduled_time ? 'scheduled' : 'draft';
        }
        
        // Prepare media (simplified - in production would handle file copying)
        $media_urls = $original_post->media_urls;
        
        // Insert duplicated post
        $result = $wpdb->insert(
            $this->table_names['scheduled_posts'],
            array(
                'title' => $new_title,
                'content' => $new_content,
                'media_urls' => $media_urls,
                'platform' => $platform,
                'scheduled_time' => $scheduled_time,
                'status' => $status,
                'priority' => $original_post->priority,
                'duplicate_of' => $original_post->id,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
        
        if ($result === false) {
            error_log("SMO Social: Failed to duplicate post: " . $wpdb->last_error);
            return false;
        }
        
        $new_post_id = $wpdb->insert_id;
        
        // Copy tags and categories if they exist
        if ($original_post->tags) {
            $wpdb->update(
                $this->table_names['scheduled_posts'],
                array('tags' => $original_post->tags),
                array('id' => $new_post_id),
                array('%s'),
                array('%d')
            );
        }
        
        return $new_post_id;
    }
    
    /**
     * Adjust content for specific platform
     */
    private function adjust_content_for_platform($content, $platform) {
        // Platform-specific adjustments
        switch ($platform) {
            case 'twitter':
                // Twitter character limit and format
                $content = $this->truncate_for_twitter($content);
                break;
                
            case 'linkedin':
                // LinkedIn professional tone adjustments
                $content = $this->adjust_for_linkedin($content);
                break;
                
            case 'instagram':
                // Instagram hashtag and visual focus
                $content = $this->optimize_for_instagram($content);
                break;
                
            case 'facebook':
                // Facebook engagement focus
                $content = $this->optimize_for_facebook($content);
                break;
        }
        
        return $content;
    }
    
    /**
     * Truncate content for Twitter
     */
    private function truncate_for_twitter($content) {
        $max_length = 280;
        if (strlen($content) <= $max_length) {
            return $content;
        }
        
        // Truncate and add ellipsis
        $truncated = substr($content, 0, $max_length - 3);
        $truncated = preg_replace('/\s+\S*$/', '', $truncated); // Remove partial word
        return $truncated . '...';
    }
    
    /**
     * Adjust content for LinkedIn
     */
    private function adjust_for_linkedin($content) {
        // Add professional tone indicators
        $content = preg_replace('/\b(gonna|wanna|gotta)\b/', '', $content);
        $content = preg_replace('/\b(awesome|cool|rad)\b/', 'excellent', $content);
        
        return $content;
    }
    
    /**
     * Optimize content for Instagram
     */
    private function optimize_for_instagram($content) {
        // Ensure hashtags are present or add relevant ones
        if (!preg_match('/#[a-zA-Z0-9_]+/', $content)) {
            $content .= ' #socialmedia #content #marketing';
        }
        
        return $content;
    }
    
    /**
     * Optimize content for Facebook
     */
    private function optimize_for_facebook($content) {
        // Add engagement prompts for Facebook
        if (!preg_match('/\?$/', $content)) {
            $content .= ' What do you think?';
        }
        
        return $content;
    }
    
    /**
     * Calculate optimal posting time for platform
     */
    private function calculate_optimal_time($platform) {
        $default_times = array(
            'facebook' => '14:00:00',
            'twitter' => '12:00:00',
            'linkedin' => '10:00:00',
            'instagram' => '16:00:00',
            'pinterest' => '20:00:00'
        );
        
        $time = isset($default_times[$platform]) ? $default_times[$platform] : '12:00:00';
        $date = date('Y-m-d');
        
        // If time has passed today, schedule for tomorrow
        if ($time <= date('H:i:s')) {
            $date = date('Y-m-d', strtotime('+1 day'));
        }
        
        return $date . ' ' . $time;
    }
    
    /**
     * Adjust scheduling timing
     */
    private function adjust_scheduling($original_time, $adjustment_hours) {
        if (!$original_time) {
            return null;
        }
        
        $adjusted_time = strtotime($original_time) + ($adjustment_hours * 3600);
        return date('Y-m-d H:i:s', $adjusted_time);
    }
    
    /**
     * Create post template
     */
    public function create_post_template($template_data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_names['post_templates'],
            array(
                'name' => sanitize_text_field($template_data['name']),
                'title_template' => sanitize_text_field($template_data['title_template']),
                'content_template' => wp_kses_post($template_data['content_template']),
                'platforms' => is_array($template_data['platforms']) ? implode(',', $template_data['platforms']) : $template_data['platforms'],
                'default_media' => sanitize_text_field($template_data['default_media'] ?? ''),
                'tags' => sanitize_text_field($template_data['tags'] ?? ''),
                'category' => sanitize_text_field($template_data['category'] ?? ''),
                'variables' => json_encode($template_data['variables'] ?? array()),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            throw new \Exception('Failed to create post template: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create template from existing post
     */
    private function create_post_template_from_post($post, $template_name) {
        $template_data = array(
            'name' => $template_name ?: $post->title . ' Template',
            'title_template' => $post->title,
            'content_template' => $post->content,
            'platforms' => $post->platform,
            'default_media' => $post->media_urls,
            'tags' => $post->tags ?? '',
            'category' => $post->category ?? '',
            'variables' => array(
                'title' => array('label' => 'Title', 'required' => true),
                'content' => array('label' => 'Content', 'required' => true)
            )
        );
        
        return $this->create_post_template($template_data);
    }
    
    /**
     * Use post template
     */
    public function use_post_template($template_id, $variables = array()) {
        global $wpdb;
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['post_templates']} WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            throw new \Exception('Template not found');
        }
        
        // Replace variables in template
        $title = $template->title_template;
        $content = $template->content_template;
        
        foreach ($variables as $var_name => $var_value) {
            $title = str_replace('{{' . $var_name . '}}', $var_value, $title);
            $content = str_replace('{{' . $var_name . '}}', $var_value, $content);
        }
        
        return array(
            'title' => $title,
            'content' => $content,
            'platforms' => explode(',', $template->platforms),
            'default_media' => $template->default_media,
            'tags' => $template->tags,
            'category' => $template->category
        );
    }
    
    /**
     * Bulk duplicate posts
     */
    public function bulk_duplicate_posts($post_ids, $options = array()) {
        $duplicated_posts = array();
        
        foreach ($post_ids as $post_id) {
            try {
                $duplicates = $this->duplicate_post($post_id, $options);
                $duplicated_posts = array_merge($duplicated_posts, $duplicates);
            } catch (\Exception $e) {
                error_log("SMO Social: Failed to duplicate post {$post_id}: " . $e->getMessage());
            }
        }
        
        return $duplicated_posts;
    }
    
    /**
     * Record duplication history
     */
    private function record_duplication_history($original_post_id, $duplicated_post_ids, $options) {
        global $wpdb;
        
        $wpdb->insert(
            $this->table_names['duplication_history'],
            array(
                'original_post_id' => $original_post_id,
                'duplicated_post_ids' => json_encode($duplicated_post_ids),
                'options' => json_encode($options),
                'duplicated_by' => get_current_user_id(),
                'duplicated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Get duplication history
     */
    public function get_duplication_history($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['original_post_id'])) {
            $where_conditions[] = "original_post_id = %d";
            $where_values[] = $filters['original_post_id'];
        }
        
        if (!empty($filters['duplicated_by'])) {
            $where_conditions[] = "duplicated_by = %d";
            $where_values[] = $filters['duplicated_by'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT dh.*, u.display_name as duplicated_by_name
                  FROM {$this->table_names['duplication_history']} dh
                  LEFT JOIN {$wpdb->prefix}users u ON dh.duplicated_by = u.ID
                  $where_clause
                  ORDER BY dh.duplicated_at DESC
                  LIMIT %d";
        
        $where_values[] = $filters['limit'] ?? 50;
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get post templates
     */
    public function get_post_templates($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($filters['platform'])) {
            $where_conditions[] = "FIND_IN_SET(%s, platforms)";
            $where_values[] = $filters['platform'];
        }
        
        if (!empty($filters['created_by'])) {
            $where_conditions[] = "created_by = %d";
            $where_values[] = $filters['created_by'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT pt.*, u.display_name as created_by_name
                  FROM {$this->table_names['post_templates']} pt
                  LEFT JOIN {$wpdb->prefix}users u ON pt.created_by = u.ID
                  $where_clause
                  ORDER BY pt.created_at DESC";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $templates = $wpdb->get_results($query, ARRAY_A);
        
        // Decode JSON variables for each template
        foreach ($templates as &$template) {
            $template['variables'] = json_decode($template['variables'], true) ?: array();
            $template['platforms'] = explode(',', $template['platforms']);
        }
        
        return $templates;
    }
    
    // AJAX handlers
    
    public function ajax_duplicate_post() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_id = intval($_POST['post_id']);
        $options = array(
            'platforms' => array_map('sanitize_text_field', $_POST['platforms'] ?? array()),
            'title_suffix' => sanitize_text_field($_POST['title_suffix'] ?? ' (Copy)'),
            'schedule_immediately' => !empty($_POST['schedule_immediately']),
            'time_adjustment_hours' => intval($_POST['time_adjustment_hours'] ?? 24)
        );
        
        try {
            $duplicated_post_ids = $this->duplicate_post($post_id, $options);
            wp_send_json_success(array(
                'duplicated_posts' => $duplicated_post_ids,
                'message' => sprintf(__('Successfully duplicated post. Created %d new posts.', 'smo-social'), count($duplicated_post_ids))
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_create_post_template() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $template_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'title_template' => sanitize_text_field($_POST['title_template']),
            'content_template' => wp_kses_post($_POST['content_template']),
            'platforms' => array_map('sanitize_text_field', $_POST['platforms'] ?? array()),
            'default_media' => sanitize_text_field($_POST['default_media'] ?? ''),
            'tags' => sanitize_text_field($_POST['tags'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'variables' => $_POST['variables'] ?? array()
        );
        
        try {
            $template_id = $this->create_post_template($template_data);
            wp_send_json_success(array('template_id' => $template_id));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_post_templates() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'platform' => sanitize_text_field($_POST['platform'] ?? ''),
            'created_by' => intval($_POST['created_by'] ?? 0)
        );
        
        $templates = $this->get_post_templates($filters);
        wp_send_json_success($templates);
    }
    
    public function ajax_use_post_template() {
        $_POST = $_POST ?? array();
        
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $template_id = intval($_POST['template_id']);
        $variables = $_POST['variables'] ?? array();
        
        try {
            $post_data = $this->use_post_template($template_id, $variables);
            wp_send_json_success($post_data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_bulk_duplicate_posts() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());
        $options = array(
            'platforms' => array_map('sanitize_text_field', $_POST['platforms'] ?? array()),
            'title_suffix' => sanitize_text_field($_POST['title_suffix'] ?? ' (Copy)'),
            'schedule_immediately' => !empty($_POST['schedule_immediately']),
            'time_adjustment_hours' => intval($_POST['time_adjustment_hours'] ?? 24)
        );
        
        try {
            $duplicated_post_ids = $this->bulk_duplicate_posts($post_ids, $options);
            wp_send_json_success(array(
                'duplicated_posts' => $duplicated_post_ids,
                'message' => sprintf(__('Successfully duplicated %d posts. Created %d new posts.', 'smo-social'), 
                    count($post_ids), count($duplicated_post_ids))
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_duplication_history() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }
        
        $filters = array(
            'original_post_id' => intval($_POST['original_post_id'] ?? 0),
            'duplicated_by' => intval($_POST['duplicated_by'] ?? 0),
            'limit' => intval($_POST['limit'] ?? 50)
        );
        
        $history = $this->get_duplication_history($filters);
        wp_send_json_success($history);
    }
}