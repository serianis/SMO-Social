<?php
/**
 * SMO Social Recent Activity Widget
 *
 * Displays recent user activities and system events
 */

namespace SMO_Social\Admin\Widgets;

class RecentActivityWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'recent-activity';
        $this->name = __('Recent Activity', 'smo-social');
        $this->description = __('Latest activities and system events', 'smo-social');
        $this->category = 'engagement';
        $this->icon = '📋';
        $this->default_size = 'medium';
        $this->capabilities = array('read');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $activities = $this->get_recent_activities($settings);

        $html = '<div class="smo-recent-activity">';

        if (!empty($activities)) {
            foreach ($activities as $activity) {
                $html .= '<div class="smo-activity-item">';
                $html .= '<span class="smo-activity-icon">' . esc_html($activity['icon']) . '</span>';
                $html .= '<div class="smo-activity-content">';
                $html .= '<div class="smo-activity-text">' . esc_html($activity['text']) . '</div>';
                $html .= '<div class="smo-activity-time">' . esc_html($activity['time']) . '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>' . __('No recent activity', 'smo-social') . '</p>';
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
        return $this->get_recent_activities($settings);
    }

    /**
     * Get recent activities
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_recent_activities($settings = array()) {
        $cache_key = 'smo_recent_activity_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 300, function() {
            // Mock activity data
            return array(
                array(
                    'icon' => '📝',
                    'text' => 'New post published to Facebook',
                    'time' => '5 minutes ago'
                ),
                array(
                    'icon' => '👥',
                    'text' => 'User joined the team',
                    'time' => '1 hour ago'
                ),
                array(
                    'icon' => '📊',
                    'text' => 'Analytics report generated',
                    'time' => '2 hours ago'
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
                'id' => 'activity_limit',
                'type' => 'select',
                'label' => __('Number of Activities', 'smo-social'),
                'options' => array(
                    '5' => __('5 activities', 'smo-social'),
                    '10' => __('10 activities', 'smo-social'),
                    '15' => __('15 activities', 'smo-social')
                ),
                'default' => '5'
            )
        );
    }
}