<?php
/**
 * Advanced Reports View
 *
 * Displays detailed analytics and reporting features with modern gradient design
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get date range from URL params or default to last 30 days
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');

// Get report data
$report_data = $this->get_advanced_report_data($start_date, $end_date);

// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('reports', __('Advanced Reports', 'smo-social'));
    
    // Render standardized gradient header using AppLayout
    \SMO_Social\Admin\Views\Common\AppLayout::render_header([
        'icon' => '📊',
        'title' => __('Advanced Reports & Analytics', 'smo-social'),
        'subtitle' => __('Comprehensive insights and performance metrics', 'smo-social'),
        'actions' => [
            [
                'id' => 'smo-print-report',
                'label' => __('Print Report', 'smo-social'),
                'icon' => 'printer',
                'class' => 'smo-btn-secondary'
            ],
            [
                'id' => 'smo-export-report',
                'label' => __('Export CSV', 'smo-social'),
                'icon' => 'download',
                'class' => 'smo-btn-primary'
            ]
        ]
    ]);
    
    // Prepare dynamic trend strings
    $posts_trend = ($report_data['posts_change'] >= 0 ? '📈 +' : '📉 ') . $report_data['posts_change'] . '%';
    $engagement_trend = ($report_data['engagement_change'] >= 0 ? '📈 +' : '📉 ') . $report_data['engagement_change'] . '%';
    $success_trend = ($report_data['success_change'] >= 0 ? '📈 +' : '📉 ') . $report_data['success_change'] . '%';
    
    // Render standardized stats dashboard using AppLayout
    \SMO_Social\Admin\Views\Common\AppLayout::render_stats_dashboard([
        [
            'icon' => 'admin-post',
            'value' => number_format($report_data['total_posts']),
            'label' => __('Total Posts', 'smo-social'),
            'trend' => $posts_trend
        ],
        [
            'icon' => 'heart',
            'value' => number_format($report_data['engagement_rate'], 2) . '%',
            'label' => __('Engagement Rate', 'smo-social'),
            'trend' => $engagement_trend
        ],
        [
            'icon' => 'yes-alt',
            'value' => number_format($report_data['success_rate'], 1) . '%',
            'label' => __('Success Rate', 'smo-social'),
            'trend' => $success_trend
        ],
        [
            'icon' => 'admin-network',
            'value' => number_format($report_data['active_platforms']),
            'label' => __('Active Platforms', 'smo-social'),
            'trend' => '🌐 Current'
        ]
    ]);
}
?>

<!-- Quick Actions Bar -->
<div class="smo-quick-actions">
    <form method="get" action="" style="display: flex; gap: 10px; align-items: center; flex: 1;">
        <input type="hidden" name="page" value="smo-social-reports">
        <label for="start_date" style="font-weight: 600;"><?php _e('From:', 'smo-social'); ?></label>
        <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="smo-form-input" style="width: 150px;">
        <label for="end_date" style="font-weight: 600;"><?php _e('To:', 'smo-social'); ?></label>
        <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="smo-form-input" style="width: 150px;">
        <button type="submit" class="smo-btn smo-btn-primary">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Update Report', 'smo-social'); ?>
        </button>
    </form>
    <button type="button" class="smo-quick-action-btn" id="smo-generate-weekly-report">
        <span class="dashicons dashicons-calendar"></span>
        <span><?php _e('Weekly Summary', 'smo-social'); ?></span>
    </button>
    <button type="button" class="smo-quick-action-btn" id="smo-generate-monthly-report">
        <span class="dashicons dashicons-calendar-alt"></span>
        <span><?php _e('Monthly Report', 'smo-social'); ?></span>
    </button>
</div>

<!-- Main Content Grid -->
<div class="smo-grid">
    <!-- Charts Section -->
    <div class="smo-card">
        <div class="smo-card-header">
            <h2 class="smo-card-title">
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Posts Over Time', 'smo-social'); ?>
            </h2>
        </div>
        <div class="smo-card-body">
            <canvas id="posts-chart" height="300"></canvas>
        </div>
    </div>

    <div class="smo-card">
        <div class="smo-card-header">
            <h2 class="smo-card-title">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('Platform Performance', 'smo-social'); ?>
            </h2>
        </div>
        <div class="smo-card-body">
            <canvas id="platform-chart" height="300"></canvas>
        </div>
    </div>

    <!-- Detailed Tables -->
    <div class="smo-card" style="grid-column: span 2;">
        <div class="smo-card-header">
            <h2 class="smo-card-title">
                <span class="dashicons dashicons-star-filled"></span>
                <?php _e('Top Performing Posts', 'smo-social'); ?>
            </h2>
        </div>
        <div class="smo-card-body">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Post', 'smo-social'); ?></th>
                        <th><?php _e('Platform', 'smo-social'); ?></th>
                        <th><?php _e('Published', 'smo-social'); ?></th>
                        <th><?php _e('Engagement', 'smo-social'); ?></th>
                        <th><?php _e('Reach', 'smo-social'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($report_data['top_posts'])): ?>
                        <?php foreach ($report_data['top_posts'] as $post): ?>
                            <tr>
                                <td><?php echo esc_html(wp_trim_words($post['content'], 10)); ?></td>
                                <td><?php echo esc_html($post['platform']); ?></td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($post['published_at']))); ?></td>
                                <td><?php echo number_format($post['engagement']); ?></td>
                                <td><?php echo number_format($post['reach']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="smo-empty-state">
                                <p><?php _e('No data available for the selected period.', 'smo-social'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="smo-card" style="grid-column: span 2;">
        <div class="smo-card-header">
            <h2 class="smo-card-title">
                <span class="dashicons dashicons-admin-network"></span>
                <?php _e('Platform Statistics', 'smo-social'); ?>
            </h2>
        </div>
        <div class="smo-card-body">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Platform', 'smo-social'); ?></th>
                        <th><?php _e('Posts', 'smo-social'); ?></th>
                        <th><?php _e('Success Rate', 'smo-social'); ?></th>
                        <th><?php _e('Avg Engagement', 'smo-social'); ?></th>
                        <th><?php _e('Avg Reach', 'smo-social'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($report_data['platform_stats'])): ?>
                        <?php foreach ($report_data['platform_stats'] as $platform => $stats): ?>
                            <tr>
                                <td><?php echo esc_html($platform); ?></td>
                                <td><?php echo number_format($stats['posts']); ?></td>
                                <td><?php echo number_format($stats['success_rate'], 1); ?>%</td>
                                <td><?php echo number_format($stats['avg_engagement']); ?></td>
                                <td><?php echo number_format($stats['avg_reach']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="smo-empty-state">
                                <p><?php _e('No platform data available.', 'smo-social'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
jQuery(document).ready(function($) {
    // Initialize charts
    const postsChartData = <?php echo json_encode($report_data['posts_chart_data'] ?? []); ?>;
    const platformChartData = <?php echo json_encode($report_data['platform_chart_data'] ?? []); ?>;

    // Posts over time chart
    if (document.getElementById('posts-chart') && postsChartData && Object.keys(postsChartData).length > 0) {
        new Chart(document.getElementById('posts-chart'), {
            type: 'line',
            data: postsChartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Platform performance chart
    if (document.getElementById('platform-chart') && platformChartData && Object.keys(platformChartData).length > 0) {
        new Chart(document.getElementById('platform-chart'), {
            type: 'bar',
            data: platformChartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Export functionality
    $('#smo-export-report').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_export_report',
                start_date: '<?php echo $start_date; ?>',
                end_date: '<?php echo $end_date; ?>',
                nonce: '<?php echo wp_create_nonce("smo_reports_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const link = document.createElement('a');
                    link.href = response.data.url;
                    link.download = response.data.filename;
                    link.click();
                } else {
                    alert('Export failed: ' + response.data);
                }
            }
        });
    });

    // Print functionality
    $('#smo-print-report').on('click', function() {
        window.print();
    });

    // Generate reports
    $('.smo-quick-action-btn').on('click', function() {
        var reportType = $(this).attr('id').replace('smo-generate-', '').replace('-report', '');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_generate_custom_report',
                type: reportType,
                start_date: '<?php echo $start_date; ?>',
                end_date: '<?php echo $end_date; ?>',
                nonce: '<?php echo wp_create_nonce("smo_reports_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Report generated successfully!');
                }
            }
        });
    });
});
</script>