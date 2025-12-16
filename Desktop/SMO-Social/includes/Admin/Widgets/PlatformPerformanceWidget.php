<?php
/**
 * SMO Social Platform Performance Widget
 *
 * Displays performance metrics by platform
 */

namespace SMO_Social\Admin\Widgets;

class PlatformPerformanceWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'platform-performance';
        $this->name = __('Platform Performance', 'smo-social');
        $this->description = __('Compare performance across different social platforms', 'smo-social');
        $this->category = 'performance';
        $this->icon = '📊';
        $this->default_size = 'medium';
        $this->capabilities = array('view_woocommerce_reports');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $performance_data = $this->get_performance_data($settings);

        $html = '<div class="smo-platform-performance">';

        // Platform comparison table
        $html .= '<div class="smo-platform-table">';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>' . __('Platform', 'smo-social') . '</th>';
        $html .= '<th>' . __('Posts', 'smo-social') . '</th>';
        $html .= '<th>' . __('Engagement', 'smo-social') . '</th>';
        $html .= '<th>' . __('Reach', 'smo-social') . '</th>';
        $html .= '<th>' . __('Performance', 'smo-social') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($performance_data['platforms'] as $platform) {
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html(ucfirst($platform['name'])) . '</strong></td>';
            $html .= '<td>' . $this->format_number($platform['posts']) . '</td>';
            $html .= '<td>' . $this->format_number($platform['engagement']) . '</td>';
            $html .= '<td>' . $this->format_number($platform['reach']) . '</td>';
            $html .= '<td><span class="smo-performance-score ' . esc_attr($platform['score_class']) . '">' . esc_html($platform['score']) . '</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

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
        return $this->get_performance_data($settings);
    }

    /**
     * Get performance data
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_performance_data($settings = array()) {
        $timeframe = isset($settings['timeframe']) ? $settings['timeframe'] : '30d';
        $cache_key = 'smo_platform_performance_' . $timeframe . '_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 600, function() {
            // Mock platform performance data
            $platforms = array(
                array(
                    'name' => 'facebook',
                    'posts' => rand(50, 200),
                    'engagement' => rand(1000, 5000),
                    'reach' => rand(5000, 25000),
                    'score' => 'A'
                ),
                array(
                    'name' => 'instagram',
                    'posts' => rand(30, 150),
                    'engagement' => rand(800, 4000),
                    'reach' => rand(3000, 20000),
                    'score' => 'A-'
                ),
                array(
                    'name' => 'twitter',
                    'posts' => rand(40, 180),
                    'engagement' => rand(600, 3000),
                    'reach' => rand(2000, 15000),
                    'score' => 'B+'
                ),
                array(
                    'name' => 'linkedin',
                    'posts' => rand(20, 100),
                    'engagement' => rand(400, 2000),
                    'reach' => rand(1000, 8000),
                    'score' => 'B'
                )
            );

            // Add score classes
            foreach ($platforms as &$platform) {
                switch ($platform['score']) {
                    case 'A':
                        $platform['score_class'] = 'excellent';
                        break;
                    case 'A-':
                        $platform['score_class'] = 'very-good';
                        break;
                    case 'B+':
                        $platform['score_class'] = 'good';
                        break;
                    case 'B':
                        $platform['score_class'] = 'fair';
                        break;
                    default:
                        $platform['score_class'] = 'poor';
                }
            }

            return array('platforms' => $platforms);
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
                'id' => 'timeframe',
                'type' => 'select',
                'label' => __('Timeframe', 'smo-social'),
                'options' => array(
                    '7d' => __('Last 7 days', 'smo-social'),
                    '30d' => __('Last 30 days', 'smo-social'),
                    '90d' => __('Last 90 days', 'smo-social')
                ),
                'default' => '30d'
            ),
            array(
                'id' => 'show_reach',
                'type' => 'checkbox',
                'label' => __('Show Reach Column', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'show_scores',
                'type' => 'checkbox',
                'label' => __('Show Performance Scores', 'smo-social'),
                'default' => true
            )
        );
    }
}