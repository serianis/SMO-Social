<?php
/**
 * SMO Social Posts Chart Widget
 *
 * Advanced analytics widget with drill-down capabilities for post performance
 */

namespace SMO_Social\Admin\Widgets;

class PostsChartWidget extends BaseWidget {
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'posts-chart';
        $this->name = __('Posts Analytics', 'smo-social');
        $this->description = __('Interactive chart showing posting trends and performance metrics', 'smo-social');
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
        $chart_data = $this->get_chart_data($settings);
        $timeframe = isset($settings['timeframe']) ? $settings['timeframe'] : '30d';
        $metric = isset($settings['metric']) ? $settings['metric'] : 'posts';

        $html = '<div class="smo-posts-chart-widget">';

        // Chart controls
        $html .= '<div class="smo-chart-controls">';
        $html .= '<div class="smo-chart-filters">';
        $html .= '<select class="smo-timeframe-select" data-widget-id="' . esc_attr($this->get_id()) . '">';
        $html .= '<option value="7d" ' . selected($timeframe, '7d', false) . '>' . __('Last 7 days', 'smo-social') . '</option>';
        $html .= '<option value="30d" ' . selected($timeframe, '30d', false) . '>' . __('Last 30 days', 'smo-social') . '</option>';
        $html .= '<option value="90d" ' . selected($timeframe, '90d', false) . '>' . __('Last 90 days', 'smo-social') . '</option>';
        $html .= '</select>';

        $html .= '<select class="smo-metric-select" data-widget-id="' . esc_attr($this->get_id()) . '">';
        $html .= '<option value="posts" ' . selected($metric, 'posts', false) . '>' . __('Posts Count', 'smo-social') . '</option>';
        $html .= '<option value="engagement" ' . selected($metric, 'engagement', false) . '>' . __('Engagement', 'smo-social') . '</option>';
        $html .= '<option value="reach" ' . selected($metric, 'reach', false) . '>' . __('Reach', 'smo-social') . '</option>';
        $html .= '</select>';
        $html .= '</div>';

        $html .= '<button class="button button-small smo-drilldown-toggle" data-widget-id="' . esc_attr($this->get_id()) . '">';
        $html .= __('Drill Down', 'smo-social');
        $html .= '</button>';
        $html .= '</div>';

        // Chart container
        $html .= '<div class="smo-chart-container">';
        $html .= '<canvas id="smo-posts-chart-' . esc_attr($this->get_id()) . '" width="400" height="200"></canvas>';
        $html .= '</div>';

        // Drill-down details (hidden by default)
        $html .= '<div class="smo-drilldown-details" style="display: none;">';
        $html .= '<h4>' . __('Detailed Breakdown', 'smo-social') . '</h4>';
        $html .= '<div class="smo-drilldown-content" id="smo-drilldown-' . esc_attr($this->get_id()) . '">';
        $html .= $this->render_drilldown_content($chart_data, $settings);
        $html .= '</div>';
        $html .= '</div>';

        // Summary stats
        $html .= '<div class="smo-chart-summary">';
        $html .= '<div class="smo-summary-stat">';
        $html .= '<span class="smo-summary-label">' . __('Total', 'smo-social') . '</span>';
        $html .= '<span class="smo-summary-value">' . $this->format_number($chart_data['summary']['total']) . '</span>';
        $html .= '</div>';

        $html .= '<div class="smo-summary-stat">';
        $html .= '<span class="smo-summary-label">' . __('Average', 'smo-social') . '</span>';
        $html .= '<span class="smo-summary-value">' . $this->format_number($chart_data['summary']['average']) . '</span>';
        $html .= '</div>';

        $html .= '<div class="smo-summary-stat">';
        $html .= '<span class="smo-summary-label">' . __('Peak', 'smo-social') . '</span>';
        $html .= '<span class="smo-summary-value">' . $this->format_number($chart_data['summary']['peak']) . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        // Add Chart.js script
        $html .= '<script>';
        $html .= 'if (typeof Chart !== "undefined") {';
        $html .= 'new Chart(document.getElementById("smo-posts-chart-' . esc_attr($this->get_id()) . '"), {';
        $html .= 'type: "line",';
        $html .= 'data: ' . json_encode($chart_data['chart']) . ',';
        $html .= 'options: {';
        $html .= 'responsive: true,';
        $html .= 'maintainAspectRatio: false,';
        $html .= 'plugins: {';
        $html .= 'legend: { display: false }';
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
     * Render drill-down content
     *
     * @param array $data Chart data
     * @param array $settings Widget settings
     * @return string HTML content
     */
    private function render_drilldown_content($data, $settings = array()) {
        $html = '<div class="smo-drilldown-table">';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>' . __('Date', 'smo-social') . '</th>';
        $html .= '<th>' . __('Posts', 'smo-social') . '</th>';
        $html .= '<th>' . __('Engagement', 'smo-social') . '</th>';
        $html .= '<th>' . __('Reach', 'smo-social') . '</th>';
        $html .= '<th>' . __('Platforms', 'smo-social') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($data['drilldown'] as $item) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($item['date']) . '</td>';
            $html .= '<td>' . $this->format_number($item['posts']) . '</td>';
            $html .= '<td>' . $this->format_number($item['engagement']) . '</td>';
            $html .= '<td>' . $this->format_number($item['reach']) . '</td>';
            $html .= '<td>' . esc_html(implode(', ', $item['platforms'])) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get chart data
     *
     * @param array $settings Widget settings
     * @return array
     */
    private function get_chart_data($settings = array()) {
        $timeframe = isset($settings['timeframe']) ? $settings['timeframe'] : '30d';
        $metric = isset($settings['metric']) ? $settings['metric'] : 'posts';

        $cache_key = 'smo_posts_chart_' . $timeframe . '_' . $metric . '_' . get_current_user_id();

        return $this->get_cached_data($cache_key, 600, function() use ($timeframe, $metric) {
            global $wpdb;

            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
            $analytics_table = $wpdb->prefix . 'smo_analytics';

            // Calculate date range
            $days = intval(str_replace('d', '', $timeframe));
            $start_date = date('Y-m-d', strtotime("-{$days} days"));
            $end_date = date('Y-m-d');

            // Get posts data grouped by date
            $posts_data = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as posts_count,
                    GROUP_CONCAT(DISTINCT platform_slug) as platforms
                FROM $posts_table
                WHERE DATE(created_at) BETWEEN %s AND %s
                AND status != 'draft'
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                $start_date, $end_date
            ), ARRAY_A);

            // Generate date range
            $dates = array();
            $current_date = strtotime($start_date);
            $end_timestamp = strtotime($end_date);

            while ($current_date <= $end_timestamp) {
                $dates[] = date('Y-m-d', $current_date);
                $current_date = strtotime('+1 day', $current_date);
            }

            // Prepare chart data
            $chart_labels = array();
            $chart_values = array();
            $drilldown_data = array();
            $total = 0;
            $peak = 0;

            foreach ($dates as $date) {
                $chart_labels[] = date('M j', strtotime($date));

                // Find data for this date
                $date_data = array_filter($posts_data, function($item) use ($date) {
                    return $item['date'] === $date;
                });

                $posts_count = 0;
                $platforms = array();
                $engagement = rand(100, 1000); // Mock engagement data
                $reach = rand(1000, 10000); // Mock reach data

                if (!empty($date_data)) {
                    $data = reset($date_data);
                    $posts_count = intval($data['posts_count']);
                    $platforms = explode(',', $data['platforms']);
                }

                // Determine value based on metric
                switch ($metric) {
                    case 'engagement':
                        $value = $engagement;
                        break;
                    case 'reach':
                        $value = $reach;
                        break;
                    default:
                        $value = $posts_count;
                }

                $chart_values[] = $value;
                $total += $value;
                $peak = max($peak, $value);

                // Drilldown data
                $drilldown_data[] = array(
                    'date' => date('M j, Y', strtotime($date)),
                    'posts' => $posts_count,
                    'engagement' => $engagement,
                    'reach' => $reach,
                    'platforms' => array_map('ucfirst', array_filter($platforms))
                );
            }

            $count = count(array_filter($chart_values));
            $average = $count > 0 ? $total / $count : 0;

            return array(
                'chart' => array(
                    'labels' => $chart_labels,
                    'datasets' => array(
                        array(
                            'label' => ucfirst($metric),
                            'data' => $chart_values,
                            'borderColor' => 'rgb(75, 192, 192)',
                            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                            'tension' => 0.1
                        )
                    )
                ),
                'drilldown' => $drilldown_data,
                'summary' => array(
                    'total' => $total,
                    'average' => round($average, 1),
                    'peak' => $peak
                )
            );
        });
    }

    /**
     * Get widget data for AJAX
     *
     * @param array $settings Widget settings
     * @return array
     */
    public function get_data($settings = array()) {
        return $this->get_chart_data($settings);
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
                'label' => __('Default Timeframe', 'smo-social'),
                'options' => array(
                    '7d' => __('Last 7 days', 'smo-social'),
                    '30d' => __('Last 30 days', 'smo-social'),
                    '90d' => __('Last 90 days', 'smo-social')
                ),
                'default' => '30d'
            ),
            array(
                'id' => 'metric',
                'type' => 'select',
                'label' => __('Default Metric', 'smo-social'),
                'options' => array(
                    'posts' => __('Posts Count', 'smo-social'),
                    'engagement' => __('Engagement', 'smo-social'),
                    'reach' => __('Reach', 'smo-social')
                ),
                'default' => 'posts'
            ),
            array(
                'id' => 'show_drilldown',
                'type' => 'checkbox',
                'label' => __('Show Drill-down by Default', 'smo-social'),
                'default' => false
            ),
            array(
                'id' => 'auto_refresh',
                'type' => 'checkbox',
                'label' => __('Auto-refresh Data', 'smo-social'),
                'default' => true
            )
        );
    }
}