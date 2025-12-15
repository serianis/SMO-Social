<?php
/**
 * SMO Social Dashboard View
 * 
 * Modern dashboard with gradient design, stats, and quick actions
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard {
    private $plugin;
    private $database;
    
    public function __construct($plugin = null) {
        $this->plugin = $plugin;
        $this->database = new \SMO_Social\Core\DatabaseManager();
    }
    
    public function render() {
        $data = $this->get_overview_data();
        ob_start();
        
        // Use Common Layout
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('dashboard', __('Dashboard', 'smo-social'));
            
            // Render standardized gradient header using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_header([
                'icon' => '🎯',
                'title' => __('Dashboard Overview', 'smo-social'),
                'subtitle' => __('Monitor your social media performance and activity', 'smo-social'),
                'actions' => [
                    [
                        'id' => 'smo-refresh-dashboard',
                        'label' => __('Refresh', 'smo-social'),
                        'icon' => 'update',
                        'class' => 'smo-btn-secondary'
                    ],
                    [
                        'href' => admin_url('admin.php?page=smo-social-create'),
                        'label' => __('Create Post', 'smo-social'),
                        'icon' => 'plus',
                        'class' => 'smo-btn-primary'
                    ]
                ]
            ]);
            
            // Render standardized stats dashboard using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
                [
                    'icon' => 'calendar-alt',
                    'value' => $data['total_posts_scheduled'] ?? 0,
                    'label' => __('Scheduled Posts', 'smo-social'),
                    'trend' => '📅 Active'
                ],
                [
                    'icon' => 'share',
                    'value' => $data['total_platforms_connected'] ?? 0,
                    'label' => __('Connected Platforms', 'smo-social'),
                    'trend' => '🔗 Online'
                ],
                [
                    'icon' => 'yes-alt',
                    'value' => $data['pending_approvals'] ?? 0,
                    'label' => __('Pending Approvals', 'smo-social'),
                    'trend' => '⏳ Waiting'
                ],
                [
                    'icon' => 'chart-line',
                    'value' => $data['performance_metrics']['total_posts_published'] ?? 0,
                    'label' => __('Posts Published', 'smo-social'),
                    'trend' => '✅ Success'
                ]
            ]);
            
            // Render standardized quick actions using AppLayout
            \SMO_Social\Admin\Views\Common\AppLayout::render_quick_actions([
                [
                    'icon' => 'edit',
                    'label' => __('Create Post', 'smo-social'),
                    'onclick' => "location.href='" . admin_url('admin.php?page=smo-social-create') . "'"
                ],
                [
                    'icon' => 'calendar',
                    'label' => __('View Calendar', 'smo-social'),
                    'onclick' => "location.href='" . admin_url('admin.php?page=smo-social-calendar') . "'"
                ],
                [
                    'icon' => 'admin-network',
                    'label' => __('Manage Platforms', 'smo-social'),
                    'onclick' => "location.href='" . admin_url('admin.php?page=smo-social-platforms') . "'"
                ],
                [
                    'icon' => 'chart-bar',
                    'label' => __('View Reports', 'smo-social'),
                    'onclick' => "location.href='" . admin_url('admin.php?page=smo-social-reports') . "'"
                ]
            ]);
        }
        ?>

        <!-- Main Content Grid -->
        <div class="smo-grid">
            <!-- Recent Activity Card -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('Recent Activity', 'smo-social'); ?>
                    </h2>
                    <div class="smo-card-actions">
                        <span class="smo-card-count"><?php echo count($data['recent_activity']); ?> <?php _e('items', 'smo-social'); ?></span>
                    </div>
                </div>
                <div class="smo-card-body">
                    <?php if (!empty($data['recent_activity'])): ?>
                        <ul class="smo-activity-list">
                            <?php foreach (array_slice($data['recent_activity'], 0, 10) as $activity): ?>
                                <li class="smo-activity-item">
                                    <span class="smo-activity-icon">📝</span>
                                    <div class="smo-activity-details">
                                        <strong><?php echo esc_html($activity['action'] ?? 'Activity'); ?></strong>
                                        <small><?php echo esc_html($activity['timestamp'] ?? ''); ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="smo-empty-state"><?php _e('No recent activity', 'smo-social'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Performance Metrics Card -->
            <div class="smo-card">
                <div class="smo-card-header">
                    <h2 class="smo-card-title">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Performance Metrics', 'smo-social'); ?>
                    </h2>
                </div>
                <div class="smo-card-body">
                    <div class="smo-metrics-grid">
                        <div class="smo-metric-item">
                            <span class="smo-metric-label"><?php _e('Engagement Rate', 'smo-social'); ?></span>
                            <span class="smo-metric-value"><?php echo number_format($data['performance_metrics']['average_engagement_rate'] ?? 0, 2); ?>%</span>
                        </div>
                        <div class="smo-metric-item">
                            <span class="smo-metric-label"><?php _e('Total Reach', 'smo-social'); ?></span>
                            <span class="smo-metric-value"><?php echo number_format($data['performance_metrics']['total_reach'] ?? 0); ?></span>
                        </div>
                        <div class="smo-metric-item">
                            <span class="smo-metric-label"><?php _e('Best Platform', 'smo-social'); ?></span>
                            <span class="smo-metric-value"><?php echo esc_html(ucfirst($data['performance_metrics']['best_performing_platform'] ?? 'N/A')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <?php
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_end();
        }
        
        return ob_get_clean();
    }
    
    public function get_overview_data() {
        global $wpdb;
        
        $data = array(
            'total_posts_scheduled' => 0,
            'total_platforms_connected' => 0,
            'pending_approvals' => 0,
            'recent_activity' => array(),
            'performance_metrics' => array()
        );
        
        // Get total scheduled posts
        $table_name = $wpdb->prefix . 'smo_scheduled_posts';
        $data['total_posts_scheduled'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'scheduled'");
        
        // Get connected platforms
        $platforms_table = $wpdb->prefix . 'smo_platform_tokens';
        $data['total_platforms_connected'] = $wpdb->get_var("SELECT COUNT(DISTINCT platform_slug) FROM $platforms_table");
        
        // Get pending approvals
        $approvals_table = $wpdb->prefix . 'smo_approval_queue';
        $data['pending_approvals'] = $wpdb->get_var("SELECT COUNT(*) FROM $approvals_table WHERE status = 'pending'");
        
        // Get recent activity
        $activity_table = $wpdb->prefix . 'smo_activity_logs';
        $recent_activity = $wpdb->get_results(
            "SELECT * FROM $activity_table ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );
        $data['recent_activity'] = $recent_activity ?: array();
        
        // Get performance metrics
        $data['performance_metrics'] = $this->calculate_performance_metrics();
        
        return $data;
    }
    
    private function calculate_performance_metrics() {
        global $wpdb;
        
        $metrics = array(
            'total_posts_published' => 0,
            'average_engagement_rate' => 0,
            'best_performing_platform' => '',
            'total_reach' => 0
        );
        
        // Calculate total posts published
        $analytics_table = $wpdb->prefix . 'smo_post_analytics';
        $metrics['total_posts_published'] = $wpdb->get_var("SELECT COUNT(*) FROM $analytics_table");
        
        // Calculate average engagement rate
        $avg_engagement = $wpdb->get_var("SELECT AVG(engagement_rate) FROM $analytics_table");
        $metrics['average_engagement_rate'] = $avg_engagement ?: 0;
        
        // Find best performing platform
        $best_platform = $wpdb->get_row(
            "SELECT platform_slug, SUM(reach) as total_reach FROM $analytics_table GROUP BY platform_slug ORDER BY total_reach DESC LIMIT 1",
            ARRAY_A
        );
        $metrics['best_performing_platform'] = $best_platform ? $best_platform['platform_slug'] : '';
        $metrics['total_reach'] = $best_platform ? $best_platform['total_reach'] : 0;
        
        return $metrics;
    }
    
    public function get_performance_chart_data() {
        global $wpdb;
        
        $chart_data = array(
            'labels' => array(),
            'datasets' => array()
        );
        
        // Get last 30 days of data
        $analytics_table = $wpdb->prefix . 'smo_post_analytics';
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $daily_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, 
                        COUNT(*) as posts_count,
                        AVG(engagement_rate) as avg_engagement,
                        SUM(reach) as total_reach
                 FROM $analytics_table 
                 WHERE created_at >= %s 
                 GROUP BY DATE(created_at) 
                 ORDER BY date",
                $thirty_days_ago
            ),
            ARRAY_A
        );
        
        foreach ($daily_stats as $stat) {
            $chart_data['labels'][] = $stat['date'];
            $chart_data['datasets']['posts'][] = $stat['posts_count'];
            $chart_data['datasets']['engagement'][] = round($stat['avg_engagement'], 2);
            $chart_data['datasets']['reach'][] = $stat['total_reach'];
        }
        
        return $chart_data;
    }
}
