<?php
/**
 * SMO Social Content Analytics Widget
 *
 * Displays content performance analytics
 */

namespace SMO_Social\Admin\Widgets;

class ContentAnalyticsWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'content-analytics';
        $this->name = __('Content Analytics', 'smo-social');
        $this->description = __('Analyze content performance and engagement', 'smo-social');
        $this->category = 'analytics';
        $this->icon = '📈';
        $this->default_size = 'large';
        $this->capabilities = array('view_woocommerce_reports');
    }

    /**
     * Render widget content
     *
     * @param array $settings Widget settings
     * @return string HTML content
     */
    public function render($settings = array()) {
        $analytics_data = $this->get_analytics_data($settings);

        $html = '<div class="smo-content-analytics">';

        // Top performing content
        $html .= '<div class="smo-top-content">';
        $html .= '<h4>' . __('Top Performing Content', 'smo-social') . '</h4>';
        $html .= '<div class="smo-content-list">';

        foreach ($analytics_data['top_content'] as $content) {
            $html .= '<div class="smo-content-item">';
            $html .= '<div class="smo-content-title">' . esc_html($content['title']) . '</div>';
            $html .= '<div class="smo-content-stats">';
            $html .= '<span class="smo-stat">' . __('Engagement:', 'smo-social') . ' ' . $this->format_number($content['engagement']) . '</span>';
            $html .= '<span class="smo-stat">' . __('Reach:', 'smo-social') . ' ' . $this->format_number($content['reach']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
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
        return $this->get_analytics_data($settings);
    }

    /**
     * Get analytics data
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_analytics_data($settings = array()) {
        $cache_key = 'smo_content_analytics_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 600, function() {
            // Mock content analytics data
            return array(
                'top_content' => array(
                    array(
                        'title' => 'Summer Campaign Post',
                        'engagement' => rand(500, 2000),
                        'reach' => rand(5000, 15000)
                    ),
                    array(
                        'title' => 'Product Launch Video',
                        'engagement' => rand(300, 1500),
                        'reach' => rand(3000, 12000)
                    ),
                    array(
                        'title' => 'Customer Success Story',
                        'engagement' => rand(200, 1000),
                        'reach' => rand(2000, 8000)
                    )
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
                'id' => 'show_reach',
                'type' => 'checkbox',
                'label' => __('Show Reach Metrics', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'content_limit',
                'type' => 'select',
                'label' => __('Number of Items', 'smo-social'),
                'options' => array(
                    '3' => __('3 items', 'smo-social'),
                    '5' => __('5 items', 'smo-social'),
                    '10' => __('10 items', 'smo-social')
                ),
                'default' => '3'
            )
        );
    }
}