<?php
/**
 * SMO Social Team Activity Widget
 *
 * Displays team member activities and collaboration metrics
 */

namespace SMO_Social\Admin\Widgets;

class TeamActivityWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'team-activity';
        $this->name = __('Team Activity', 'smo-social');
        $this->description = __('Team member activities and collaboration', 'smo-social');
        $this->category = 'team';
        $this->icon = '👥';
        $this->default_size = 'medium';
        $this->capabilities = array('list_users');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $team_activity = $this->get_team_activity($settings);

        $html = '<div class="smo-team-activity">';

        if (!empty($team_activity)) {
            foreach ($team_activity as $activity) {
                $html .= '<div class="smo-team-activity-item">';
                $html .= '<div class="smo-team-member">' . esc_html($activity['member']) . '</div>';
                $html .= '<div class="smo-team-action">' . esc_html($activity['action']) . '</div>';
                $html .= '<div class="smo-team-time">' . esc_html($activity['time']) . '</div>';
                $html .= '</div>';
            }
        } else {
            $html .= '<p>' . __('No recent team activity', 'smo-social') . '</p>';
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
        return $this->get_team_activity($settings);
    }

    /**
     * Get team activity
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_team_activity($settings = array()) {
        $cache_key = 'smo_team_activity_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 300, function() {
            // Mock team activity data
            return array(
                array(
                    'member' => 'John Doe',
                    'action' => 'Published post to Facebook',
                    'time' => '10 minutes ago'
                ),
                array(
                    'member' => 'Jane Smith',
                    'action' => 'Created content calendar',
                    'time' => '1 hour ago'
                ),
                array(
                    'member' => 'Bob Johnson',
                    'action' => 'Approved workflow item',
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