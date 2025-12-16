<?php
/**
 * SMO Social Analytics Dashboard
 * Advanced analytics with real-time data, comprehensive insights, and export capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

// Initialize analytics dashboard
$analytics_dashboard = new \SMO_Social\Analytics\Dashboard();

// Check if analytics tables exist, create if not
$analytics_dashboard->ensure_analytics_tables();

// Enqueue Chart.js
wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);

// Enqueue enhanced CSS
wp_enqueue_style(
    'smo-content-import-enhanced',
    SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-content-import-enhanced.css',
    array('smo-social-admin'),
    SMO_SOCIAL_VERSION
);

// Localize script for AJAX
wp_localize_script('smo-social-admin', 'smoAnalytics', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('smo_analytics_nonce'),
    'exportNonce' => wp_create_nonce('smo_export_nonce'),
    'realTimeNonce' => wp_create_nonce('smo_realtime_nonce'),
    'platforms' => $analytics_dashboard->get_active_platforms()
));

// Use Common Layout with AppLayout helpers
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('analytics', __('Analytics Dashboard', 'smo-social'));
    
    // Render standardized gradient header using AppLayout
    \SMO_Social\Admin\Views\Common\AppLayout::render_header([
        'icon' => '📈',
        'title' => __('Analytics Dashboard', 'smo-social'),
        'subtitle' => __('Performance insights and metrics', 'smo-social'),
        'actions' => [
            [
                'id' => 'refresh-analytics',
                'label' => __('Refresh Data', 'smo-social'),
                'icon' => 'update',
                'class' => 'smo-btn-primary'
            ],
            [
                'id' => 'export-analytics',
                'label' => __('Export Data', 'smo-social'),
                'icon' => 'download',
                'class' => 'smo-btn-secondary'
            ]
        ]
    ]);
    
    // Render standardized stats dashboard using AppLayout
    \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
        [
            'icon' => 'admin-post',
            'value' => '-',
            'label' => __('Total Posts', 'smo-social'),
            'trend' => '-',
            'id' => 'total-posts'
        ],
        [
            'icon' => 'groups',
            'value' => '-',
            'label' => __('Total Reach', 'smo-social'),
            'trend' => '-',
            'id' => 'total-reach'
        ],
        [
            'icon' => 'heart',
            'value' => '-',
            'label' => __('Engagement Rate', 'smo-social'),
            'trend' => '-',
            'id' => 'engagement-rate'
        ],
        [
            'icon' => 'awards',
            'value' => '-',
            'label' => __('Best Platform', 'smo-social'),
            'trend' => '-',
            'id' => 'best-platform'
        ]
    ]);
}
?>

<!-- Dashboard Controls -->
<div class="smo-dashboard-controls">
    <div class="smo-control-group">
        <label for="date-range"><?php _e('Date Range:', 'smo-social'); ?></label>
        <select id="date-range">
            <option value="7"><?php _e('Last 7 days', 'smo-social'); ?></option>
            <option value="30" selected><?php _e('Last 30 days', 'smo-social'); ?></option>
            <option value="90"><?php _e('Last 90 days', 'smo-social'); ?></option>
            <option value="365"><?php _e('Last year', 'smo-social'); ?></option>
        </select>
    </div>

    <div class="smo-control-group">
        <label for="platform-filter"><?php _e('Platform:', 'smo-social'); ?></label>
        <select id="platform-filter">
            <option value="all" selected><?php _e('All Platforms', 'smo-social'); ?></option>
            <?php foreach ($analytics_dashboard->get_active_platforms() as $platform): ?>
                <option value="<?php echo esc_attr($platform['slug']); ?>">
                    <?php echo esc_html($platform['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Charts Row -->
<div class="smo-charts-row">
    <div class="smo-card">
        <div class="smo-card-header">
            <h3><?php _e('Posts Over Time', 'smo-social'); ?></h3>
        </div>
        <div class="smo-card-body">
            <canvas id="posts-timeline-chart" width="400" height="200"></canvas>
        </div>
    </div>

    <div class="smo-card">
        <div class="smo-card-header">
            <h3><?php _e('Platform Performance', 'smo-social'); ?></h3>
        </div>
        <div class="smo-card-body">
            <canvas id="platform-performance-chart" width="400" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Engagement Analysis -->
<div class="smo-card">
    <div class="smo-card-header">
        <h3><?php _e('Engagement Analysis', 'smo-social'); ?></h3>
    </div>
    <div class="smo-card-body">
        <canvas id="engagement-chart" width="400" height="200"></canvas>
    </div>
</div>

<!-- Content Performance Table -->
<div class="smo-card">
    <div class="smo-card-header">
        <h3><?php _e('Top Performing Content', 'smo-social'); ?></h3>
    </div>
    <div class="smo-card-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Content', 'smo-social'); ?></th>
                    <th><?php _e('Platform', 'smo-social'); ?></th>
                    <th><?php _e('Date', 'smo-social'); ?></th>
                    <th><?php _e('Reach', 'smo-social'); ?></th>
                    <th><?php _e('Engagement', 'smo-social'); ?></th>
                </tr>
            </thead>
            <tbody id="performance-table-body">
                <!-- Generated by JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>

<script>
jQuery(document).ready(function($) {
    loadAnalyticsData();

    $('#date-range, #platform-filter').on('change', function() {
        loadAnalyticsData();
    });

    $('#refresh-analytics').on('click', function() {
        loadAnalyticsData();
    });

    $('#export-analytics').on('click', function() {
        alert('Export feature coming soon!');
    });
});

function loadAnalyticsData() {
    const dateRange = $('#date-range').val();
    const platform = $('#platform-filter').val();

    $.ajax({
        url: smoAnalytics.ajaxurl,
        type: 'POST',
        data: {
            action: 'smo_get_analytics_data',
            nonce: smoAnalytics.nonce,
            date_range: dateRange,
            platform: platform
        },
        success: function(response) {
            if (response.success) {
                updateDashboard(response.data);
            }
        }
    });
}

function updateDashboard(data) {
    if (data.summary) {
        $('#total-posts').text(data.summary.total_posts || '-');
        $('#total-reach').text(data.summary.total_reach || '-');
        $('#engagement-rate').text(data.summary.engagement_rate || '-');
        $('#best-platform').text(data.summary.best_platform || '-');
    }
}
</script>