<?php
namespace SMO_Social\Admin\Ajax;

use SMO_Social\Admin\Views\EnhancedDashboard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard AJAX Handler
 * 
 * Handles all AJAX requests related to the main dashboard
 */
class DashboardAjax extends BaseAjaxHandler {
    
    /**
     * Register AJAX actions
     */
    public function register() {
        add_action('wp_ajax_smo_get_dashboard_overview', [$this, 'get_overview']);
        add_action('wp_ajax_smo_get_post_analytics', [$this, 'get_post_analytics']);
        add_action('wp_ajax_smo_get_custom_analytics', [$this, 'get_custom_analytics']);
        add_action('wp_ajax_smo_tag_content', [$this, 'tag_content']);
    }

    /**
     * Get dashboard overview statistics
     */
    public function get_overview() {
        if (!$this->verify_request()) {
            return;
        }

        // Gather statistics from various sources
        $stats = [
            'total_reach' => '12.5K', // Mock for now
            'engagement' => '5.2%',   // Mock
            'scheduled' => 0,
            'response_time' => '1.2h' // Mock
        ];
        
        // Get real scheduled count if possible
        if (class_exists('SMO_Social\Admin\Views\EnhancedDashboard')) {
            $stats['scheduled'] = EnhancedDashboard::get_video_posts_count(); // We fixed this method earlier
        } else {
            // Fallback query
            global $wpdb;
            $stats['scheduled'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = %s AND post_status = %s",
                'smo_post', 'future'
            )) ?: 0;
        }

        $this->send_success($stats);
    }

    /**
     * Get post analytics
     */
    public function get_post_analytics() {
        if (!$this->verify_request()) {
            return;
        }

        // Mock data for chart
        $data = [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'datasets' => [
                [
                    'label' => __('Views', 'smo-social'),
                    'data' => [120, 190, 300, 500, 200, 300, 450],
                    'borderColor' => '#3b82f6',
                    'tension' => 0.4
                ],
                [
                    'label' => __('Engagement', 'smo-social'),
                    'data' => [20, 30, 50, 80, 40, 60, 90],
                    'borderColor' => '#10b981',
                    'tension' => 0.4
                ]
            ]
        ];

        $this->send_success($data);
    }

    /**
     * Get custom analytics (e.g. date range)
     */
    public function get_custom_analytics() {
        if (!$this->verify_request()) {
            return;
        }
        
        $range = $this->get_text('range', '7days');
        
        // Logic to return data based on range would go here
        // Returning mock success for now
        $this->send_success(['range' => $range, 'message' => 'Data filtered']);
    }

    /**
     * Tag content
     */
    public function tag_content() {
        if (!$this->verify_request()) {
            return;
        }

        $post_id = $this->get_int('post_id');
        $tags = $this->get_text('tags'); // Comma separated

        if (!$post_id) {
            $this->send_error(__('Invalid Post ID', 'smo-social'));
            return;
        }

        // Here we would save tags to the post
        // update_post_meta($post_id, 'smo_tags', $tags);

        $this->send_success(['post_id' => $post_id, 'tags' => $tags], __('Content tagged successfully', 'smo-social'));
    }
}
