<?php
/**
 * Posts Per Day Widget
 * 
 * Displays social media posts per day analytics and insights
 *
 * @package SMO_Social
 * @subpackage Admin\Widgets
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Widgets;

use SMO_Social\Analytics\PostsPerDayManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Posts Per Day Widget Class
 */
class PostsPerDayWidget extends BaseWidget
{

    /**
     * @var PostsPerDayManager
     */
    private $posts_per_day_manager;

    /**
     * Initialize widget properties
     */
    protected function init()
    {
        $this->id = 'posts_per_day';
        $this->name = __('Posts Per Day Analytics', 'smo-social');
        $this->description = __('Track and analyze your daily posting activity across platforms', 'smo-social');
        $this->category = 'analytics';
        $this->icon = 'dashicons-chart-line';
        $this->default_size = 'large';
        $this->capabilities = array('edit_smo_posts');

        $this->posts_per_day_manager = new PostsPerDayManager();
    }

    /**
     * Get widget data
     */
    public function get_data($args = array())
    {
        $user_id = isset($args['user_id']) ? $args['user_id'] : get_current_user_id();

        // Get comprehensive stats from the manager
        $stats = $this->posts_per_day_manager->get_dashboard_stats($user_id);

        return array(
            'stats' => $stats,
            'chart_data' => $this->prepare_chart_data($stats['trends']),
            'platform_breakdown' => $stats['platform_today'],
            'insights' => $stats['insights']
        );
    }

    /**
     * Prepare chart data for visualization
     */
    private function prepare_chart_data($trends)
    {
        $labels = array();
        $posts_data = array();
        $published_data = array();

        foreach ($trends as $day) {
            $labels[] = date('M j', strtotime($day['date']));
            $posts_data[] = $day['posts'];
            $published_data[] = $day['published'];
        }

        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('Total Posts', 'smo-social'),
                    'data' => $posts_data,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                    'tension' => 0.4
                ),
                array(
                    'label' => __('Published', 'smo-social'),
                    'data' => $published_data,
                    'borderColor' => '#00a32a',
                    'backgroundColor' => 'rgba(0, 163, 42, 0.1)',
                    'tension' => 0.4
                )
            )
        );
    }

    /**
     * Render widget content
     */
    public function render($data = array())
    {
        if (empty($data)) {
            $data = $this->get_data();
        }

        $stats = $data['stats'];
        $today = $stats['today'];
        $yesterday = $stats['yesterday'];
        $week_avg = $stats['week_average'];
        $month_avg = $stats['month_average'];
        $peak_day = $stats['peak_day'];

        ob_start();
        ?>

        <div class="smo-widget smo-posts-per-day-widget">
            <div class="smo-widget-header">
                <h3><?php echo esc_html($this->name); ?></h3>
                <div class="smo-widget-actions">
                    <button class="smo-btn-icon" data-action="refresh" title="<?php esc_attr_e('Refresh', 'smo-social'); ?>">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                    <button class="smo-btn-icon" data-action="settings" title="<?php esc_attr_e('Settings', 'smo-social'); ?>">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </button>
                </div>
            </div>

            <div class="smo-widget-body">
                <!-- Today's Stats -->
                <div class="smo-stats-grid">
                    <div class="smo-stat-card">
                        <div class="smo-stat-label"><?php esc_html_e('Today', 'smo-social'); ?></div>
                        <div class="smo-stat-value"><?php echo esc_html($today['total_posts']); ?></div>
                        <div class="smo-stat-meta">
                            <span
                                class="smo-stat-change <?php echo $today['total_posts'] > $yesterday['total_posts'] ? 'positive' : 'negative'; ?>">
                                <?php
                                $change = $yesterday['total_posts'] > 0
                                    ? round((($today['total_posts'] - $yesterday['total_posts']) / $yesterday['total_posts']) * 100, 1)
                                    : 0;
                                echo $change > 0 ? '+' : '';
                                echo esc_html($change);
                                ?>%
                            </span>
                            <span class="smo-stat-label-small"><?php esc_html_e('vs yesterday', 'smo-social'); ?></span>
                        </div>
                    </div>

                    <div class="smo-stat-card">
                        <div class="smo-stat-label"><?php esc_html_e('7-Day Avg', 'smo-social'); ?></div>
                        <div class="smo-stat-value"><?php echo esc_html($week_avg['avg_posts_per_day']); ?></div>
                        <div class="smo-stat-meta">
                            <span class="smo-stat-label-small"><?php esc_html_e('posts/day', 'smo-social'); ?></span>
                        </div>
                    </div>

                    <div class="smo-stat-card">
                        <div class="smo-stat-label"><?php esc_html_e('30-Day Avg', 'smo-social'); ?></div>
                        <div class="smo-stat-value"><?php echo esc_html($month_avg['avg_posts_per_day']); ?></div>
                        <div class="smo-stat-meta">
                            <span class="smo-stat-label-small"><?php esc_html_e('posts/day', 'smo-social'); ?></span>
                        </div>
                    </div>

                    <div class="smo-stat-card">
                        <div class="smo-stat-label"><?php esc_html_e('Success Rate', 'smo-social'); ?></div>
                        <div class="smo-stat-value"><?php echo esc_html($today['success_rate']); ?>%</div>
                        <div class="smo-stat-meta">
                            <span class="smo-stat-label-small">
                                <?php echo esc_html($today['published_posts']); ?>/<?php echo esc_html($today['total_posts']); ?>
                                <?php esc_html_e('published', 'smo-social'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="smo-chart-container" style="margin-top: 20px;">
                    <canvas id="posts-per-day-chart" height="200"></canvas>
                </div>

                <!-- Platform Breakdown -->
                <?php if (!empty($data['platform_breakdown'])): ?>
                    <div class="smo-platform-breakdown" style="margin-top: 20px;">
                        <h4><?php esc_html_e('Platform Breakdown (Today)', 'smo-social'); ?></h4>
                        <div class="smo-platform-list">
                            <?php foreach ($data['platform_breakdown'] as $platform): ?>
                                <div class="smo-platform-item">
                                    <div class="smo-platform-info">
                                        <span class="smo-platform-name"><?php echo esc_html(ucfirst($platform['platform'])); ?></span>
                                        <span class="smo-platform-count"><?php echo esc_html($platform['posts']); ?>
                                            <?php esc_html_e('posts', 'smo-social'); ?></span>
                                    </div>
                                    <div class="smo-platform-bar">
                                        <div class="smo-platform-bar-fill"
                                            style="width: <?php echo esc_attr(min(100, ($platform['posts'] / max(1, $today['total_posts'])) * 100)); ?>%">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Insights -->
                <?php if (!empty($stats['insights'])): ?>
                    <div class="smo-insights" style="margin-top: 20px;">
                        <h4><?php esc_html_e('Insights', 'smo-social'); ?></h4>
                        <?php foreach ($stats['insights'] as $insight): ?>
                            <div class="smo-insight-item smo-insight-<?php echo esc_attr($insight['type']); ?>">
                                <span class="smo-insight-icon dashicons dashicons-<?php
                                echo $insight['type'] === 'success' ? 'yes-alt' :
                                    ($insight['type'] === 'warning' ? 'warning' : 'info');
                                ?>"></span>
                                <div class="smo-insight-content">
                                    <strong><?php echo esc_html($insight['title']); ?></strong>
                                    <p><?php echo esc_html($insight['message']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Peak Day Info -->
                <?php if ($peak_day): ?>
                    <div class="smo-peak-day-info"
                        style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-radius: 4px;">
                        <strong><?php esc_html_e('Peak Posting Day:', 'smo-social'); ?></strong>
                        <span><?php echo esc_html($peak_day['formatted_date']); ?></span>
                        <span class="smo-badge"><?php echo esc_html($peak_day['total_posts']); ?>
                            <?php esc_html_e('posts', 'smo-social'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Initialize chart
                var ctx = document.getElementById('posts-per-day-chart');
                if (ctx) {
                    var chartData = <?php echo json_encode($data['chart_data']); ?>;
                    new Chart(ctx, {
                        type: 'line',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>

        <style>
            .smo-posts-per-day-widget .smo-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }

            .smo-posts-per-day-widget .smo-stat-card {
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 3px solid #0073aa;
            }

            .smo-posts-per-day-widget .smo-stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .smo-posts-per-day-widget .smo-stat-value {
                font-size: 28px;
                font-weight: 700;
                color: #1d2327;
                line-height: 1;
                margin-bottom: 8px;
            }

            .smo-posts-per-day-widget .smo-stat-change {
                font-size: 14px;
                font-weight: 600;
                margin-right: 5px;
            }

            .smo-posts-per-day-widget .smo-stat-change.positive {
                color: #00a32a;
            }

            .smo-posts-per-day-widget .smo-stat-change.negative {
                color: #d63638;
            }

            .smo-posts-per-day-widget .smo-stat-label-small {
                font-size: 11px;
                color: #999;
            }

            .smo-posts-per-day-widget .smo-platform-item {
                margin-bottom: 12px;
            }

            .smo-posts-per-day-widget .smo-platform-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                font-size: 13px;
            }

            .smo-posts-per-day-widget .smo-platform-name {
                font-weight: 600;
            }

            .smo-posts-per-day-widget .smo-platform-bar {
                height: 8px;
                background: #e0e0e0;
                border-radius: 4px;
                overflow: hidden;
            }

            .smo-posts-per-day-widget .smo-platform-bar-fill {
                height: 100%;
                background: linear-gradient(90deg, #0073aa, #00a32a);
                transition: width 0.3s ease;
            }

            .smo-posts-per-day-widget .smo-insight-item {
                display: flex;
                gap: 10px;
                padding: 12px;
                margin-bottom: 10px;
                border-radius: 6px;
                background: #fff;
                border: 1px solid #ddd;
            }

            .smo-posts-per-day-widget .smo-insight-success {
                border-left: 3px solid #00a32a;
                background: #f0f9f4;
            }

            .smo-posts-per-day-widget .smo-insight-warning {
                border-left: 3px solid #dba617;
                background: #fcf9e8;
            }

            .smo-posts-per-day-widget .smo-insight-info {
                border-left: 3px solid #0073aa;
                background: #f0f6fc;
            }

            .smo-posts-per-day-widget .smo-insight-icon {
                flex-shrink: 0;
                width: 20px;
                height: 20px;
            }

            .smo-posts-per-day-widget .smo-insight-content p {
                margin: 5px 0 0;
                font-size: 13px;
                color: #666;
            }

            .smo-posts-per-day-widget .smo-badge {
                display: inline-block;
                padding: 4px 8px;
                background: #0073aa;
                color: white;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
                margin-left: 10px;
            }
        </style>

        <?php
        return ob_get_clean();
    }

    /**
     * Get widget settings schema
     */
    public function get_settings_fields()
    {
        return array(
            'refresh_interval' => array(
                'type' => 'number',
                'label' => __('Refresh Interval (minutes)', 'smo-social'),
                'default' => 5,
                'min' => 1,
                'max' => 60
            ),
            'show_chart' => array(
                'type' => 'checkbox',
                'label' => __('Show Chart', 'smo-social'),
                'default' => true
            ),
            'show_platforms' => array(
                'type' => 'checkbox',
                'label' => __('Show Platform Breakdown', 'smo-social'),
                'default' => true
            ),
            'show_insights' => array(
                'type' => 'checkbox',
                'label' => __('Show Insights', 'smo-social'),
                'default' => true
            )
        );
    }
}
