<?php
/**
 * Enhanced Dashboard Manager
 * 
 * Comprehensive dashboard feature manager integrating:
 * - Post Duplication
 * - Comment Reply System
 * - Comment Scoring
 * - Advanced Analytics
 * - Performance Overview
 * - Custom Analytics (Content Tagging)
 * - Audience Demographics
 * 
 * @package SMO_Social
 * @subpackage Features
 * @since 2.0.0
 */

namespace SMO_Social\Features;

if (!defined('ABSPATH')) {
    exit;
}

class EnhancedDashboardManager {
    
    private $post_duplication_manager;
    private $comment_manager;
    private $analytics_manager;
    private $demographics_tracker;
    
    public function __construct() {
        $this->init_managers();
        $this->init_hooks();
    }
    
    /**
     * Initialize all feature managers
     */
    private function init_managers() {
        // Initialize feature managers
        if (class_exists('\SMO_Social\Content\PostDuplicationManager')) {
            $this->post_duplication_manager = new \SMO_Social\Content\PostDuplicationManager();
        }
        // Additional managers can be initialized here
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Dashboard AJAX hooks
        add_action('wp_ajax_smo_get_dashboard_overview', array($this, 'ajax_get_dashboard_overview'));
        add_action('wp_ajax_smo_get_post_analytics', array($this, 'ajax_get_post_analytics'));
        add_action('wp_ajax_smo_get_custom_analytics', array($this, 'ajax_get_custom_analytics'));
        add_action('wp_ajax_smo_tag_content', array($this, 'ajax_tag_content'));
        add_action('wp_ajax_smo_get_tagged_analytics', array($this, 'ajax_get_tagged_analytics'));
        
        // Admin interface hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_scripts'));
    }
    
    /**
     * Get performance overview data
     * 
     * @return array Performance metrics data
     */
    private function get_performance_overview() {
        global $wpdb;
        
        $overview = array();
        
        // Get basic metrics
        $overview['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smo_scheduled_posts");
        $overview['published_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smo_scheduled_posts WHERE status = 'published'");
        $overview['scheduled_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}smo_scheduled_posts WHERE status = 'scheduled'");
        
        // Get engagement data
        $engagement_data = $wpdb->get_results("
            SELECT SUM(CASE WHEN metric_name = 'engagements' THEN metric_value ELSE 0 END) as total_engagements,
                   SUM(CASE WHEN metric_name = 'impressions' THEN metric_value ELSE 0 END) as total_impressions,
                   AVG(CASE WHEN metric_name = 'engagement_rate' THEN metric_value ELSE 0 END) as avg_engagement_rate
            FROM {$wpdb->prefix}smo_analytics
        ", ARRAY_A);
        
        if ($engagement_data) {
            $overview = array_merge($overview, $engagement_data[0]);
        }
        
        // Get platform breakdown
        $platform_data = $wpdb->get_results("
            SELECT platform, COUNT(*) as post_count,
                   SUM(CASE WHEN a.metric_name = 'engagements' THEN a.metric_value ELSE 0 END) as platform_engagements
            FROM {$wpdb->prefix}smo_scheduled_posts p
            LEFT JOIN {$wpdb->prefix}smo_analytics a ON p.id = a.post_id
            WHERE p.status = 'published'
            GROUP BY platform
        ", ARRAY_A);
        
        $overview['platform_breakdown'] = $platform_data ?: array();
        
        return $overview;
    }
    
    /**
     * AJAX handler for getting dashboard overview
     */
    public function ajax_get_dashboard_overview() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $overview = $this->get_performance_overview();
        wp_send_json_success($overview);
    }
    
    /**
     * AJAX: Get post analytics
     */
    public function ajax_get_post_analytics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_analytics';
        
        $analytics = $wpdb->get_results($wpdb->prepare("
            SELECT metric_name, metric_value, platform, created_at
            FROM {$table_name}
            WHERE post_id = %d
            ORDER BY created_at DESC
        ", $post_id), ARRAY_A);
        
        wp_send_json_success($analytics);
    }
    
    /**
     * AJAX: Get custom analytics (tagged content)
     */
    public function ajax_get_custom_analytics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $tag = sanitize_text_field($_POST['tag'] ?? '');
        
        global $wpdb;
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $analytics_table = $wpdb->prefix . 'smo_analytics';
        
        $tagged_posts = $wpdb->get_results($wpdb->prepare("
            SELECT p.id, p.title, p.content, p.platform, p.created_at,
                   SUM(CASE WHEN a.metric_name = 'engagements' THEN a.metric_value ELSE 0 END) as engagements,
                   SUM(CASE WHEN a.metric_name = 'impressions' THEN a.metric_value ELSE 0 END) as impressions
            FROM {$posts_table} p
            LEFT JOIN {$analytics_table} a ON p.id = a.post_id
            WHERE p.tags LIKE %s
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ", '%' . $wpdb->esc_like($tag) . '%'), ARRAY_A);
        
        wp_send_json_success($tagged_posts);
    }
    
    /**
     * AJAX: Tag content
     */
    public function ajax_tag_content() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $tags = sanitize_text_field($_POST['tags'] ?? '');
        
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        
        $result = $wpdb->update(
            $table_name,
            array('tags' => $tags),
            array('id' => $post_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Tags updated successfully', 'smo-social'));
        } else {
            wp_send_json_error(__('Failed to update tags', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Get tagged analytics
     */
    public function ajax_get_tagged_analytics() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        global $wpdb;
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        
        // Get all unique tags
        $all_tags = $wpdb->get_col("
            SELECT DISTINCT tags
            FROM {$posts_table}
            WHERE tags IS NOT NULL AND tags != ''
        ");
        
        // Parse and count tags
        $tag_stats = array();
        foreach ($all_tags as $tag_string) {
            $tags = array_map('trim', explode(',', $tag_string));
            foreach ($tags as $tag) {
                if (empty($tag)) continue;
                
                if (!isset($tag_stats[$tag])) {
                    $tag_stats[$tag] = array(
                        'count' => 0,
                        'engagements' => 0,
                        'impressions' => 0
                    );
                }
                $tag_stats[$tag]['count']++;
            }
        }
        
        wp_send_json_success($tag_stats);
    }
    
    /**
     * Enqueue dashboard scripts and styles
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_dashboard_scripts($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }
        
        // Enqueue dashboard specific scripts
        wp_enqueue_script(
            'smo-enhanced-dashboard',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/dashboard-redesign.js',
            array('jquery'),
            SMO_SOCIAL_VERSION,
            true
        );
        
        wp_enqueue_style(
            'smo-enhanced-dashboard',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/dashboard-redesign.css',
            array(),
            SMO_SOCIAL_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('smo-enhanced-dashboard', 'smo_dashboard_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'create_post_url' => admin_url('admin.php?page=smo-social-create'),
            'nonce' => wp_create_nonce('smo_social_nonce'),
            'strings' => array(
                'loading' => __('Loading...', 'smo-social'),
                'error' => __('An error occurred', 'smo-social'),
                'success' => __('Data updated successfully', 'smo-social')
            )
        ));
    }
}
