<?php
/**
 * SMO Social Quick Stats Widget
 *
 * Displays quick statistics and metrics
 */

namespace SMO_Social\Admin\Widgets;

class QuickStatsWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'quick-stats';
        $this->name = __('Quick Stats', 'smo-social');
        $this->description = __('Essential metrics at a glance', 'smo-social');
        $this->category = 'overview';
        $this->icon = '⚡';
        $this->default_size = 'small';
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

        $html = '<div class="smo-quick-stats">';

        foreach ($stats as $stat) {
            $html .= '<div class="smo-quick-stat">';
            $html .= '<span class="smo-stat-icon">' . esc_html($stat['icon']) . '</span>';
            $html .= '<div class="smo-stat-content">';
            $html .= '<span class="smo-stat-value">' . esc_html($stat['value']) . '</span>';
            $html .= '<span class="smo-stat-label">' . esc_html($stat['label']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

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
        $cache_key = 'smo_quick_stats_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 300, function() {
            global $wpdb;

            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

            // Get basic stats
            $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM $posts_table");
            $active_platforms = $wpdb->get_var("SELECT COUNT(DISTINCT platform_slug) FROM $posts_table WHERE status != 'draft'");
            $today_posts = $wpdb->get_var("SELECT COUNT(*) FROM $posts_table WHERE DATE(created_at) = CURDATE()");

            return array(
                array(
                    'icon' => '📝',
                    'value' => $this->format_number($total_posts),
                    'label' => __('Total Posts', 'smo-social')
                ),
                array(
                    'icon' => '🌐',
                    'value' => $this->format_number($active_platforms),
                    'label' => __('Platforms', 'smo-social')
                ),
                array(
                    'icon' => '📅',
                    'value' => $this->format_number($today_posts),
                    'label' => __('Today', 'smo-social')
                )
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
                'id' => 'show_icons',
                'type' => 'checkbox',
                'label' => __('Show Icons', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'refresh_interval',
                'type' => 'select',
                'label' => __('Refresh Interval', 'smo-social'),
                'options' => array(
                    '60' => __('1 minute', 'smo-social'),
                    '300' => __('5 minutes', 'smo-social'),
                    '600' => __('10 minutes', 'smo-social')
                ),
                'default' => '300'
            )
        );
    }
}