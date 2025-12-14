<?php
/**
 * SMO Social Engagement Metrics Widget
 *
 * Displays engagement metrics and analytics
 */

namespace SMO_Social\Admin\Widgets;

class EngagementMetricsWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'engagement-metrics';
        $this->name = __('Engagement Metrics', 'smo-social');
        $this->description = __('Track likes, shares, comments, and engagement rates', 'smo-social');
        $this->category = 'engagement';
        $this->icon = '💬';
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
        $metrics = $this->get_engagement_data($settings);

        $html = '<div class="smo-engagement-metrics">';

        // Summary cards
        $html .= '<div class="smo-engagement-summary">';
        foreach ($metrics['summary'] as $metric) {
            $html .= '<div class="smo-engagement-card">';
            $html .= '<div class="smo-engagement-icon">' . esc_html($metric['icon']) . '</div>';
            $html .= '<div class="smo-engagement-content">';
            $html .= '<div class="smo-engagement-value">' . $this->format_number($metric['value']) . '</div>';
            $html .= '<div class="smo-engagement-label">' . esc_html($metric['label']) . '</div>';
            if (isset($metric['change'])) {
                $change_class = $metric['change'] >= 0 ? 'positive' : 'negative';
                $change_icon = $metric['change'] >= 0 ? '↗️' : '↘️';
                $html .= '<div class="smo-engagement-change ' . $change_class . '">';
                $html .= $change_icon . ' ' . abs($metric['change']) . '%';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Engagement chart
        $html .= '<div class="smo-engagement-chart">';
        $html .= '<h4>' . __('Engagement Trends', 'smo-social') . '</h4>';
        $html .= '<canvas id="smo-engagement-chart-' . esc_attr($this->get_id()) . '" width="400" height="200"></canvas>';
        $html .= '</div>';

        $html .= '</div>';

        // Add Chart.js script
        $html .= '<script>';
        $html .= 'if (typeof Chart !== "undefined") {';
        $html .= 'new Chart(document.getElementById("smo-engagement-chart-' . esc_attr($this->get_id()) . '"), {';
        $html .= 'type: "line",';
        $html .= 'data: ' . json_encode($metrics['chart']) . ',';
        $html .= 'options: {';
        $html .= 'responsive: true,';
        $html .= 'maintainAspectRatio: false,';
        $html .= 'plugins: {';
        $html .= 'legend: { display: true, position: "bottom" }';
        $html .= '},';
        $html .= 'scales: {';
        $html .= 'y: { beginAtZero: true }';
        $html .= '}';
        $html .= '}';
        $html .= '});';
        $html .= '}';
        $html .= '</script>';

        return $this->render_wrapper($html, $settings);
    }

    /**
     * Get widget data
     *
     * @param array $settings Widget settings
     * @return array
     */
    public function get_data($settings = array()) {
        return $this->get_engagement_data($settings);
    }

    /**
     * Get engagement data
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_engagement_data($settings = array()) {
        $timeframe = isset($settings['timeframe']) ? $settings['timeframe'] : '30d';
        $cache_key = 'smo_engagement_metrics_' . $timeframe . '_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 600, function() use ($timeframe) {
            global $wpdb;

            $analytics_table = $wpdb->prefix . 'smo_analytics';

            // Calculate date range
            $days = intval(str_replace('d', '', $timeframe));
            $start_date = date('Y-m-d', strtotime("-{$days} days"));

            // Mock engagement data (in production, this would come from analytics table)
            $total_likes = rand(1000, 5000);
            $total_shares = rand(500, 2000);
            $total_comments = rand(200, 1000);
            $engagement_rate = rand(30, 80) / 10;

            // Generate trend data
            $dates = array();
            $likes_data = array();
            $shares_data = array();
            $comments_data = array();

            for ($i = $days; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dates[] = date('M j', strtotime($date));
                $likes_data[] = rand(10, 100);
                $shares_data[] = rand(5, 50);
                $comments_data[] = rand(2, 20);
            }

            return array(
                'summary' => array(
                    array(
                        'icon' => '👍',
                        'value' => $total_likes,
                        'label' => __('Total Likes', 'smo-social'),
                        'change' => rand(-10, 15)
                    ),
                    array(
                        'icon' => '🔄',
                        'value' => $total_shares,
                        'label' => __('Total Shares', 'smo-social'),
                        'change' => rand(-5, 10)
                    ),
                    array(
                        'icon' => '💬',
                        'value' => $total_comments,
                        'label' => __('Total Comments', 'smo-social'),
                        'change' => rand(-8, 12)
                    ),
                    array(
                        'icon' => '📈',
                        'value' => $engagement_rate,
                        'label' => __('Engagement Rate', 'smo-social'),
                        'change' => rand(-3, 8)
                    )
                ),
                'chart' => array(
                    'labels' => $dates,
                    'datasets' => array(
                        array(
                            'label' => __('Likes', 'smo-social'),
                            'data' => $likes_data,
                            'borderColor' => 'rgb(75, 192, 192)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                            'tension' => 0.1
                        ),
                        array(
                            'label' => __('Shares', 'smo-social'),
                            'data' => $shares_data,
                            'borderColor' => 'rgb(255, 99, 132)',
                            'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                            'tension' => 0.1
                        ),
                        array(
                            'label' => __('Comments', 'smo-social'),
                            'data' => $comments_data,
                            'borderColor' => 'rgb(54, 162, 235)',
                            'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                            'tension' => 0.1
                        )
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
                'id' => 'show_chart',
                'type' => 'checkbox',
                'label' => __('Show Trend Chart', 'smo-social'),
                'default' => true
            ),
            array(
                'id' => 'show_changes',
                'type' => 'checkbox',
                'label' => __('Show Percentage Changes', 'smo-social'),
                'default' => true
            )
        );
    }
}