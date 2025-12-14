<?php
/**
 * Content Calendar View
 *
 * Displays a calendar interface for managing scheduled posts
 */

namespace SMO_Social\Admin\Views;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calendar View Class
 */
class Calendar
{
    /**
     * Initialize WordPress hooks
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }

        wp_enqueue_style(
            'smo-content-import-enhanced',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-content-import-enhanced.css',
            array('smo-social-admin'),
            SMO_SOCIAL_VERSION
        );

        wp_enqueue_script(
            'smo-content-import-enhanced',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-content-import-enhanced.js',
            array('jquery', 'wp-util', 'smo-social-admin'),
            SMO_SOCIAL_VERSION,
            true
        );

        wp_localize_script('smo-content-import-enhanced', 'smoContentImportEnhanced', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_content_import_enhanced'),
            'strings' => array(
                'importing' => __('Importing...', 'smo-social'),
                'success' => __('Success!', 'smo-social'),
                'error' => __('Error occurred', 'smo-social')
            )
        ));
    }

    /**
     * Render the calendar view
     */
    public function render()
    {
        $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $posts = $this->get_calendar_posts($current_month, $current_year);

        $prev_month = $current_month - 1;
        $prev_year = $current_year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }

        $next_month = $current_month + 1;
        $next_year = $current_year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }

        // Use AppLayout helpers
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('calendar', __('Content Calendar', 'smo-social'));
            
            \SMO_Social\Admin\Views\Common\AppLayout::render_header([
                'icon' => '📅',
                'title' => __('Content Calendar', 'smo-social'),
                'subtitle' => __('Schedule and manage your content', 'smo-social'),
                'actions' => [
                    [
                        'id' => 'smo-add-calendar-post',
                        'label' => __('Add New Post', 'smo-social'),
                        'icon' => 'plus-alt',
                        'class' => 'smo-btn-primary'
                    ]
                ]
            ]);
            
            \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
                [
                    'icon' => 'calendar-alt',
                    'value' => '-',
                    'label' => __('Total Scheduled', 'smo-social'),
                    'trend' => '📅 This month',
                    'id' => 'total-scheduled'
                ],
                [
                    'icon' => 'chart-line',
                    'value' => '-',
                    'label' => __('Avg Engagement', 'smo-social'),
                    'trend' => '📈 Last 30 days',
                    'id' => 'avg-engagement-rate'
                ],
                [
                    'icon' => 'admin-site',
                    'value' => '-',
                    'label' => __('Connected Platforms', 'smo-social'),
                    'trend' => '🌐 Active',
                    'id' => 'connected-platforms'
                ],
                [
                    'icon' => 'yes-alt',
                    'value' => '-',
                    'label' => __('Published', 'smo-social'),
                    'trend' => '✅ This month',
                    'id' => 'published-count'
                ]
            ]);
        }
        ?>

        <!-- Main Content Tabs -->
        <div class="smo-card">
            <div class="smo-tabs-wrapper">
                <nav class="smo-tabs-nav">
                    <a href="#calendar-view" class="smo-tab-link active" data-tab="calendar-view">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Calendar View', 'smo-social'); ?>
                    </a>
                    <a href="#analytics" class="smo-tab-link" data-tab="analytics">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php esc_html_e('Analytics', 'smo-social'); ?>
                    </a>
                    <a href="#insights" class="smo-tab-link" data-tab="insights">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('AI Insights', 'smo-social'); ?>
                    </a>
                    <a href="#forecasting" class="smo-tab-link" data-tab="forecasting">
                        <span class="dashicons dashicons-chart-line"></span>
                        <?php esc_html_e('Forecasting', 'smo-social'); ?>
                    </a>
                    <a href="#performance" class="smo-tab-link" data-tab="performance">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('Performance', 'smo-social'); ?>
                    </a>
                </nav>

                <div class="smo-tabs-content">
                    <!-- Calendar View Tab -->
                    <div class="smo-tab-panel active" id="calendar-view-panel">
                        <div class="smo-panel-header">
                            <div class="smo-calendar-nav" style="display: flex; align-items: center; gap: 15px;">
                                <a href="<?php echo \add_query_arg(array('month' => $prev_month, 'year' => $prev_year)); ?>" class="smo-btn smo-btn-secondary">
                                    &larr; <?php echo date('M', mktime(0, 0, 0, $prev_month, 1, $prev_year)); ?>
                                </a>
                                <h2 style="margin: 0;"><?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?></h2>
                                <a href="<?php echo \add_query_arg(array('month' => $next_month, 'year' => $next_year)); ?>" class="smo-btn smo-btn-secondary">
                                    <?php echo date('M', mktime(0, 0, 0, $next_month, 1, $next_year)); ?> &rarr;
                                </a>
                            </div>
                            <div class="smo-panel-actions">
                                <select id="smo-calendar-filter">
                                    <option value="all"><?php esc_html_e('All Posts', 'smo-social'); ?></option>
                                    <option value="scheduled"><?php esc_html_e('Scheduled', 'smo-social'); ?></option>
                                    <option value="published"><?php esc_html_e('Published', 'smo-social'); ?></option>
                                    <option value="failed"><?php esc_html_e('Failed', 'smo-social'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="smo-calendar-container">
                            <?php echo $this->render_calendar_grid($current_month, $current_year, $posts); ?>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="smo-tab-panel" id="analytics-panel">
                        <div class="smo-panel-header">
                            <div class="smo-panel-title">
                                <h2><?php esc_html_e('Calendar Analytics', 'smo-social'); ?></h2>
                                <p><?php esc_html_e('Track your posting performance and engagement metrics', 'smo-social'); ?></p>
                            </div>
                        </div>
                        <div class="smo-charts-grid">
                            <div class="smo-chart-container">
                                <h4><?php esc_html_e('Posts Over Time', 'smo-social'); ?></h4>
                                <canvas id="posts-chart" width="400" height="200"></canvas>
                            </div>
                            <div class="smo-chart-container">
                                <h4><?php esc_html_e('Platform Performance', 'smo-social'); ?></h4>
                                <canvas id="platform-chart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- AI Insights Tab -->
                    <div class="smo-tab-panel" id="insights-panel">
                        <div class="smo-panel-header">
                            <div class="smo-panel-title">
                                <h2><?php esc_html_e('AI-Powered Insights', 'smo-social'); ?></h2>
                                <p><?php esc_html_e('AI recommendations for your content strategy', 'smo-social'); ?></p>
                            </div>
                            <div class="smo-panel-actions">
                                <button class="smo-btn smo-btn-secondary" id="refresh-insights">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Refresh', 'smo-social'); ?>
                                </button>
                            </div>
                        </div>
                        <div id="insights-list" class="smo-insights-grid">
                            <p class="smo-empty-state"><?php esc_html_e('Click Refresh to generate AI insights', 'smo-social'); ?></p>
                        </div>
                    </div>

                    <!-- Forecasting Tab -->
                    <div class="smo-tab-panel" id="forecasting-panel">
                        <div class="smo-panel-header">
                            <div class="smo-panel-title">
                                <h2><?php esc_html_e('Performance Forecasting', 'smo-social'); ?></h2>
                                <p><?php esc_html_e('Predict future performance based on historical data', 'smo-social'); ?></p>
                            </div>
                            <div class="smo-panel-actions">
                                <select id="forecast-period">
                                    <option value="7"><?php esc_html_e('7 Days', 'smo-social'); ?></option>
                                    <option value="30" selected><?php esc_html_e('30 Days', 'smo-social'); ?></option>
                                    <option value="90"><?php esc_html_e('90 Days', 'smo-social'); ?></option>
                                </select>
                                <button class="smo-btn smo-btn-primary" id="generate-forecast">
                                    <?php esc_html_e('Generate Forecast', 'smo-social'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="smo-forecast-chart">
                            <canvas id="forecast-canvas" width="800" height="300"></canvas>
                        </div>
                    </div>

                    <!-- Performance Tab -->
                    <div class="smo-tab-panel" id="performance-panel">
                        <div class="smo-panel-header">
                            <div class="smo-panel-title">
                                <h2><?php esc_html_e('Performance Metrics', 'smo-social'); ?></h2>
                                <p><?php esc_html_e('Detailed performance analysis and optimization tips', 'smo-social'); ?></p>
                            </div>
                        </div>
                        <div class="smo-metrics-grid">
                            <div class="smo-metric-item">
                                <div class="smo-metric-title"><?php esc_html_e('Best Performing Day', 'smo-social'); ?></div>
                                <div class="smo-metric-value" id="best-day">-</div>
                            </div>
                            <div class="smo-metric-item">
                                <div class="smo-metric-title"><?php esc_html_e('Optimal Posting Frequency', 'smo-social'); ?></div>
                                <div class="smo-metric-value" id="optimal-frequency">-</div>
                            </div>
                            <div class="smo-metric-item">
                                <div class="smo-metric-title"><?php esc_html_e('Content Mix Balance', 'smo-social'); ?></div>
                                <div class="smo-metric-value" id="content-mix">-</div>
                            </div>
                            <div class="smo-metric-item">
                                <div class="smo-metric-title"><?php esc_html_e('ROI Score', 'smo-social'); ?></div>
                                <div class="smo-metric-value" id="roi-score">-</div>
                            </div>
                        </div>
                        <div class="smo-recommendations-section">
                            <h4><?php esc_html_e('Optimization Recommendations', 'smo-social'); ?></h4>
                            <div id="optimization-recommendations" class="smo-recommendations-list">
                                <p class="smo-empty-state"><?php esc_html_e('No recommendations available yet', 'smo-social'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Post Details Modal -->
        <div id="smo-post-modal" class="smo-modal" style="display: none;">
            <div class="smo-modal-content">
                <div class="smo-modal-header">
                    <h3><?php esc_html_e('Post Details', 'smo-social'); ?></h3>
                    <button type="button" class="smo-modal-close">&times;</button>
                </div>
                <div class="smo-modal-body" id="smo-post-details">
                    <!-- Post details will be loaded here -->
                </div>
            </div>
        </div>

        <?php
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        }
    }

    /**
     * Get calendar posts
     */
    private function get_calendar_posts($month, $year)
    {
        global $wpdb;

        $start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $posts_table WHERE scheduled_time BETWEEN %s AND %s ORDER BY scheduled_time ASC",
            $start_date,
            $end_date
        ), ARRAY_A);

        $calendar_posts = array();
        if ($posts) {
            foreach ($posts as $post) {
                $day = date('j', strtotime($post['scheduled_time']));
                if (!isset($calendar_posts[$day])) {
                    $calendar_posts[$day] = array();
                }
                $calendar_posts[$day][] = array(
                    'id' => $post['id'],
                    'title' => !empty($post['title']) ? $post['title'] : wp_trim_words($post['content'], 5),
                    'content' => $post['content'],
                    'status' => $post['status'],
                    'date' => date('Y-m-d', strtotime($post['scheduled_time'])),
                    'scheduled_time' => $post['scheduled_time']
                );
            }
        }
        return $calendar_posts;
    }

    /**
     * Render calendar grid
     */
    private function render_calendar_grid($month, $year, $posts)
    {
        $html = '<div class="smo-calendar-grid">';

        $days = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
        foreach ($days as $day) {
            $html .= '<div class="smo-calendar-day-header">' . esc_html($day) . '</div>';
        }

        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $first_day_weekday = date('w', $first_day);
        $days_in_month = date('t', $first_day);
        $current_date = date('Y-m-d');

        for ($i = 0; $i < $first_day_weekday; $i++) {
            $html .= '<div class="smo-calendar-day other-month"></div>';
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = mktime(0, 0, 0, $month, $day, $year);
            $date_string = date('Y-m-d', $date);
            $is_today = ($date_string === $current_date);

            $class = 'smo-calendar-day';
            if ($is_today) {
                $class .= ' today';
            }

            $html .= '<div class="' . $class . '" data-date="' . $date_string . '">';
            $html .= '<div class="smo-calendar-day-number">' . $day . '</div>';
            $html .= '<div class="smo-calendar-posts">';

            if (isset($posts[$day]) && is_array($posts[$day])) {
                foreach ($posts[$day] as $post) {
                    $post_class = 'smo-calendar-post ' . esc_attr($post['status']);
                    $html .= '<div class="' . $post_class . '" data-post-id="' . esc_attr($post['id']) . '">';
                    $html .= esc_html($post['title']);
                    $html .= '</div>';
                }
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $total_cells = $first_day_weekday + $days_in_month;
        $remaining_cells = (7 - ($total_cells % 7)) % 7;
        for ($day = 1; $day <= $remaining_cells; $day++) {
            $html .= '<div class="smo-calendar-day other-month"></div>';
        }

        $html .= '</div>';
        return $html;
    }
}
