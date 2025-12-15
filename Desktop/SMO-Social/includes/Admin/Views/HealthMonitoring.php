<?php
/**
 * SMO Social Health Monitoring Dashboard
 * Real-time platform health monitoring and status visualization
 */

if (!defined('ABSPATH')) {
    exit;
}

// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('health-monitoring', __('Health Monitoring', 'smo-social'));
}

// Get platform manager and current health data
$platform_manager = new \SMO_Social\Platforms\Manager();
$platforms = $platform_manager->get_platforms();
$health_data = array();
$overall_health = array(
    'healthy' => 0,
    'warning' => 0,
    'critical' => 0,
    'total' => 0
);

// Get latest health data for each platform
$sanitizer = '\SMO_Social\Admin\Helpers\ViewDataSanitizer';
foreach ($platforms as $platform_slug => $platform) {
    $health_cache_key = 'smo_health_status_' . $platform_slug;
    $cached_health = get_transient($health_cache_key);
    
    if ($cached_health) {
        $health_data[$platform_slug] = $cached_health;
        $status = $sanitizer::safe_get($cached_health, 'status', 'unknown');
        if (isset($overall_health[$status])) {
            $overall_health[$status]++;
        }
        $overall_health['total']++;
    } else {
        // Trigger health check if no cached data
        $platform->health_check();
        $cached_health = get_transient($health_cache_key);
        if ($cached_health) {
            $health_data[$platform_slug] = $cached_health;
            $status = $sanitizer::safe_get($cached_health, 'status', 'unknown');
            if (isset($overall_health[$status])) {
                $overall_health[$status]++;
            }
            $overall_health['total']++;
        }
    }
}
?>

<div class="smo-health-monitoring">
    
    <!-- Overall Health Summary -->
    <div class="smo-health-summary">
        <h3><?php _e('Overall Platform Health', 'smo-social'); ?></h3>
        <div class="smo-health-stats">
            <div class="smo-stat-card healthy">
                <span class="smo-stat-number"><?php echo $overall_health['healthy']; ?></span>
                <span class="smo-stat-label"><?php _e('Healthy', 'smo-social'); ?></span>
            </div>
            <div class="smo-stat-card warning">
                <span class="smo-stat-number"><?php echo $overall_health['warning']; ?></span>
                <span class="smo-stat-label"><?php _e('Warning', 'smo-social'); ?></span>
            </div>
            <div class="smo-stat-card critical">
                <span class="smo-stat-number"><?php echo $overall_health['critical']; ?></span>
                <span class="smo-stat-label"><?php _e('Critical', 'smo-social'); ?></span>
            </div>
            <div class="smo-stat-card total">
                <span class="smo-stat-number"><?php echo $overall_health['total']; ?></span>
                <span class="smo-stat-label"><?php _e('Total Platforms', 'smo-social'); ?></span>
            </div>
        </div>
    </div>

    <!-- Platform Health Details -->
    <div class="smo-platform-health-grid">
        <?php foreach ($platforms as $platform_slug => $platform): ?>
            <?php 
            $platform_health = $health_data[$platform_slug] ?? null;
            $platform_name = $platform->get_name();
            $platform_icon = $this->get_platform_icon($platform_slug);
            ?>
            
            <div class="smo-platform-health-card" data-platform="<?php echo esc_attr($platform_slug); ?>">
                <div class="smo-platform-header">
                    <div class="smo-platform-info">
                        <span class="smo-platform-icon"><?php echo $platform_icon; ?></span>
                        <h4><?php echo esc_html($platform_name); ?></h4>
                    </div>
                    <div class="smo-platform-status">
                        <?php if ($platform_health): ?>
                                        <?php
                                        $health_status = $sanitizer::safe_get($platform_health, 'status', 'unknown');
                                        ?>
                                        <span class="smo-status-indicator status-<?php echo esc_attr($health_status); ?>">
                                            <?php echo $this->get_status_icon($health_status); ?>
                                        </span>
                                        <span class="smo-status-text"><?php echo ucfirst($health_status); ?></span>
                                    <?php else: ?>
                                        <span class="smo-status-indicator status-unknown">
                                            <span class="dashicons dashicons-clock"></span>
                                        </span>
                                        <span class="smo-status-text"><?php _e('Checking...', 'smo-social'); ?></span>
                                    <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($platform_health): ?>
                    <?php
                    $response_time = $sanitizer::safe_get($platform_health, 'response_time', 0);
                    $last_check = $sanitizer::safe_get($platform_health, 'last_check', '');
                    $summary = $sanitizer::safe_get($platform_health, 'summary', array());
                    $critical_issues = $sanitizer::safe_get($summary, 'critical_issues', 0);
                    $warnings = $sanitizer::safe_get($summary, 'warnings', 0);
                    $total_issues = $critical_issues + $warnings;
                    ?>
                    <div class="smo-platform-metrics">
                         <div class="smo-metric">
                             <span class="smo-metric-label"><?php _e('Response Time', 'smo-social'); ?></span>
                             <span class="smo-metric-value"><?php echo $response_time; ?>ms</span>
                         </div>
                         <div class="smo-metric">
                             <span class="smo-metric-label"><?php _e('Last Check', 'smo-social'); ?></span>
                             <span class="smo-metric-value">
                                 <?php echo !empty($last_check) ? human_time_diff(strtotime($last_check)) . ' ago' : __('N/A', 'smo-social'); ?>
                             </span>
                         </div>
                         <div class="smo-metric">
                             <span class="smo-metric-label"><?php _e('Issues', 'smo-social'); ?></span>
                             <span class="smo-metric-value"><?php echo $total_issues; ?></span>
                         </div>
                     </div>

                     <?php if ($critical_issues > 0 || $warnings > 0): ?>
                         <div class="smo-platform-issues">
                             <?php if ($critical_issues > 0): ?>
                                 <div class="smo-issue critical">
                                     <strong><?php _e('Critical Issues:', 'smo-social'); ?></strong>
                                     <span><?php echo $critical_issues; ?></span>
                                 </div>
                             <?php endif; ?>
                             <?php if ($warnings > 0): ?>
                                 <div class="smo-issue warning">
                                     <strong><?php _e('Warnings:', 'smo-social'); ?></strong>
                                     <span><?php echo $warnings; ?></span>
                                 </div>
                             <?php endif; ?>
                         </div>
                     <?php endif; ?>
                <?php endif; ?>
                
                <div class="smo-platform-actions">
                    <button class="button smo-refresh-health" data-platform="<?php echo esc_attr($platform_slug); ?>">
                        <?php _e('Refresh', 'smo-social'); ?>
                    </button>
                    <button class="button smo-view-details" data-platform="<?php echo esc_attr($platform_slug); ?>">
                        <?php _e('Details', 'smo-social'); ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Real-time Updates Notice -->
    <div class="smo-realtime-notice">
        <p><?php _e('Health data automatically refreshes every 5 minutes. Click "Refresh" to update immediately.', 'smo-social'); ?></p>
    </div>

</div>

<!-- Health Details Modal -->
<div id="smo-health-details-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <div class="smo-modal-header">
            <h3><?php _e('Platform Health Details', 'smo-social'); ?></h3>
            <span class="smo-modal-close">&times;</span>
        </div>
        <div class="smo-modal-body">
            <div id="smo-health-details-content"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Auto-refresh health data every 5 minutes
    setInterval(function() {
        refreshAllHealthData();
    }, 300000); // 5 minutes
    
    // Manual refresh button
    $('.smo-refresh-health').on('click', function() {
        var platform = $(this).data('platform');
        refreshPlatformHealth(platform);
    });
    
    // View details button
    $('.smo-view-details').on('click', function() {
        var platform = $(this).data('platform');
        showHealthDetails(platform);
    });
    
    // Modal close
    $('.smo-modal-close, .smo-modal').on('click', function(e) {
        if (e.target === this) {
            $('#smo-health-details-modal').hide();
        }
    });
    
    function refreshPlatformHealth(platform) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_refresh_platform_health',
                platform: platform,
                nonce: '<?php echo wp_create_nonce("smo_health_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Simple refresh for now
                }
            }
        });
    }
    
    function refreshAllHealthData() {
        $('.smo-platform-health-card').each(function() {
            var platform = $(this).data('platform');
            refreshPlatformHealth(platform);
        });
    }
    
    function showHealthDetails(platform) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_get_platform_health_details',
                platform: platform,
                nonce: '<?php echo wp_create_nonce("smo_health_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#smo-health-details-content').html(response.data.html);
                    $('#smo-health-details-modal').show();
                }
            }
        });
    }
});
</script>


<?php
// Helper methods for the view
class HealthMonitoringViewHelper {
    
    public function get_platform_icon($platform_slug) {
        $icons = array(
            'twitter' => '🐦',
            'facebook' => '📘',
            'instagram' => '📷',
            'linkedin' => '💼',
            'youtube' => '📺',
            'tiktok' => '🎵',
            'pinterest' => '📌',
            'snapchat' => '👻',
            'telegram' => '✈️',
            'whatsapp' => '💬'
        );
        
        return $icons[$platform_slug] ?? '🌐';
    }
    
    public function get_status_icon($status) {
        $icons = array(
            'healthy' => '✅',
            'warning' => '⚠️',
            'critical' => '❌',
            'unknown' => '❓'
        );
        
        return $icons[$status] ?? '❓';
    }
}

$helper = new HealthMonitoringViewHelper();
?>