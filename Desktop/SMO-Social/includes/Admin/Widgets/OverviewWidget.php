<?php
/**
 * SMO Social Overview Widget
 *
 * Displays key statistics and quick metrics
 */

namespace SMO_Social\Admin\Widgets;

class OverviewWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'overview';
        $this->name = __('SMO Social Overview', 'smo-social');
        $this->description = __('Quick overview of your social media performance', 'smo-social');
        $this->category = 'overview';
        $this->icon = '📊';
        $this->default_size = 'medium';
        $this->capabilities = array('manage_options');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $stats = $this->get_stats_data($settings);

        $html = '<div class="smo-overview-stats">';

        // Total Posts
        $html .= '<div class="smo-overview-stat">';
        $html .= '<span class="smo-stat-icon">📝</span>';
        $html .= '<div class="smo-stat-content">';
        $html .= '<span class="smo-stat-value">' . $this->format_number($stats['total_posts']) . '</span>';
        $html .= '<span class="smo-stat-label">' . __('Total Posts', 'smo-social') . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        // Scheduled Posts
        $html .= '<div class="smo-overview-stat">';
        $html .= '<span class="smo-stat-icon">⏰</span>';
        $html .= '<div class="smo-stat-content">';
        $html .= '<span class="smo-stat-value">' . $this->format_number($stats['scheduled_posts']) . '</span>';
        $html .= '<span class="smo-stat-label">' . __('Scheduled', 'smo-social') . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        // Published Today
        $html .= '<div class="smo-overview-stat">';
        $html .= '<span class="smo-stat-icon">✅</span>';
        $html .= '<div class="smo-stat-content">';
        $html .= '<span class="smo-stat-value">' . $this->format_number($stats['published_today']) . '</span>';
        $html .= '<span class="smo-stat-label">' . __('Published Today', 'smo-social') . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        // Engagement Rate
        $html .= '<div class="smo-overview-stat">';
        $html .= '<span class="smo-stat-icon">📈</span>';
        $html .= '<div class="smo-stat-content">';
        $html .= '<span class="smo-stat-value">' . $this->format_percentage($stats['engagement_rate']) . '</span>';
        $html .= '<span class="smo-stat-label">' . __('Engagement Rate', 'smo-social') . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        // Quick Actions
        $html .= '<div class="smo-overview-actions">';
        $html .= '<a href="' . admin_url('admin.php?page=smo-social-create') . '" class="button button-primary button-small">';
        $html .= __('Create Post', 'smo-social');
        $html .= '</a>';
        $html .= '<a href="' . admin_url('admin.php?page=smo-social-posts') . '" class="button button-small">';
        $html .= __('View Posts', 'smo-social');
        $html .= '</a>';
        $html .= '</div>';

        return $this->render_wrapper($html, $settings);
    }

    /**
     * Get widget data
     *
     * @param array $settings Widget settings
     * @return array
     */
    public function get_data($settings = array()) {
        return $this->get_stats_data($settings);
    }

    /**
     * Get statistics data
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_stats_data($settings = array()) {
        $cache_key = 'smo_overview_stats_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 300, function() {
            global $wpdb;

            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

            // Get basic stats
            $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM $posts_table");
            $scheduled_posts = $wpdb->get_var("SELECT COUNT(*) FROM $posts_table WHERE status = 'scheduled'");
            $published_today = $wpdb->get_var("SELECT COUNT(*) FROM $posts_table WHERE status = 'published' AND DATE(created_at) = CURDATE()");

            // Calculate engagement rate (mock data for now)
            $engagement_rate = 5.2;

            return array(
                'total_posts' => intval($total_posts),
                'scheduled_posts' => intval($scheduled_posts),
                'published_today' => intval($published_today),
                'engagement_rate' => floatval($engagement_rate)
            );
        });
    }

    /**
     * Get widget settings fields
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            array(
                'id' => 'show_engagement',
                'type' => 'checkbox',
                'label' => __('Show Engagement Rate', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'show_actions',
                'type' => 'checkbox',
                'label' => __('Show Quick Actions', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'refresh_interval',
                'type' => 'select',
                'label' => __('Refresh Interval', 'smo-social'),
                'options' => array(
                    '300' => __('5 minutes', 'smo-social'),
                    '600' => __('10 minutes', 'smo-social'),
                    '1800' => __('30 minutes', 'smo-social')
                ),
                'default' => '300'
            )
        );
    }
}