<?php
/**
 * Advanced Scheduling View
 * 
 * Provides automated publishing and scheduling features with time slot management
 * for WordPress content across multiple social media platforms.
 *
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current scheduling settings
$settings = get_option('smo_advanced_scheduling', array(
    'auto_publish_enabled' => false,
    'default_slots' => array(),
    'platform_settings' => array(),
    'content_rules' => array(),
    'fallback_settings' => array()
));

// Get available time slots
$time_slots = isset($settings['default_slots']) ? $settings['default_slots'] : array();
$platforms = array(
    'twitter' => array('name' => 'Twitter', 'max_posts' => 50, 'interval' => 15),
    'facebook' => array('name' => 'Facebook', 'max_posts' => 10, 'interval' => 60),
    'linkedin' => array('name' => 'LinkedIn', 'max_posts' => 10, 'interval' => 60),
    'instagram' => array('name' => 'Instagram', 'max_posts' => 10, 'interval' => 60)
);

// Use Common Layout
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_start('advanced-scheduling', __('Advanced Scheduling', 'smo-social'));
}
?>

<!-- Modern Gradient Header -->
<div class="smo-import-header">
    <div class="smo-header-content">
        <h1 class="smo-page-title">
            <span class="smo-icon">📅</span>
            <?php _e('Advanced Scheduling', 'smo-social'); ?>
        </h1>
        <p class="smo-page-subtitle">
            <?php _e('Automate your social media publishing with intelligent scheduling', 'smo-social'); ?>
        </p>
    </div>
    <div class="smo-header-actions">
        <button type="button" class="smo-btn smo-btn-secondary" id="smo-toggle-auto-publish">
            <?php echo $settings['auto_publish_enabled'] ? __('Disable Auto-Publish', 'smo-social') : __('Enable Auto-Publish', 'smo-social'); ?>
        </button>
        <button type="button" class="smo-btn smo-btn-primary" id="smo-process-queue-now">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Process Queue Now', 'smo-social'); ?>
        </button>
    </div>
</div>

<!-- Dashboard Stats Overview -->
<div class="smo-import-dashboard">
    <div class="smo-stats-grid">
        <div class="smo-stat-card smo-stat-gradient-1">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html(count($time_slots)); ?></h3>
                <p class="smo-stat-label"><?php _e('Time Slots Configured', 'smo-social'); ?></p>
                <span class="smo-stat-trend">⏰ Active</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-2">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-admin-post"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number" id="smo-queue-total">0</h3>
                <p class="smo-stat-label"><?php _e('Posts in Queue', 'smo-social'); ?></p>
                <span class="smo-stat-trend">📋 Waiting</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-3">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-yes"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number" id="smo-queue-completed">0</h3>
                <p class="smo-stat-label"><?php _e('Completed Today', 'smo-social'); ?></p>
                <span class="smo-stat-trend">✅ Success</span>
            </div>
        </div>

        <div class="smo-stat-card smo-stat-gradient-4">
            <div class="smo-stat-icon">
                <span class="dashicons dashicons-admin-network"></span>
            </div>
            <div class="smo-stat-content">
                <h3 class="smo-stat-number"><?php echo esc_html(count($platforms)); ?></h3>
                <p class="smo-stat-label"><?php _e('Platforms Supported', 'smo-social'); ?></p>
                <span class="smo-stat-trend">🌐 Connected</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="smo-quick-actions">
    <button class="smo-quick-action-btn" id="smo-add-time-slot">
        <span class="dashicons dashicons-plus"></span>
        <span><?php _e('Add Time Slot', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-view-analytics">
        <span class="dashicons dashicons-chart-bar"></span>
        <span><?php _e('View Analytics', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-export-schedule">
        <span class="dashicons dashicons-download"></span>
        <span><?php _e('Export Schedule', 'smo-social'); ?></span>
    </button>
    <button class="smo-quick-action-btn" id="smo-bulk-actions">
        <span class="dashicons dashicons-admin-tools"></span>
        <span><?php _e('Bulk Actions', 'smo-social'); ?></span>
    </button>
</div>

<div class="smo-grid">
        <!-- Scheduling Status -->
        <div class="smo-card" style="grid-column: span 2;">
            <div class="smo-card-header">
                <h2><?php _e('Scheduling Status', 'smo-social'); ?></h2>
                <div class="smo-scheduling-controls" style="display: flex; gap: 10px;">
                    <button type="button" class="button button-primary" id="smo-toggle-auto-publish">
                        <?php echo $settings['auto_publish_enabled'] ? __('Disable Auto-Publish', 'smo-social') : __('Enable Auto-Publish', 'smo-social'); ?>
                    </button>
                    <button type="button" class="button" id="smo-process-queue-now"><?php _e('Process Queue Now', 'smo-social'); ?></button>
                </div>
            </div>
            <div class="smo-card-body">
                <div class="smo-status-indicators" style="display: flex; gap: 30px;">
                    <div class="smo-status-item">
                        <span class="smo-status-label" style="display: block; margin-bottom: 5px; color: #646970; font-size: 12px;"><?php _e('Auto-Publish', 'smo-social'); ?></span>
                        <span class="smo-status-badge <?php echo $settings['auto_publish_enabled'] ? 'active' : 'inactive'; ?>" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: <?php echo $settings['auto_publish_enabled'] ? '#d1e7dd' : '#f8d7da'; ?>; color: <?php echo $settings['auto_publish_enabled'] ? '#0f5132' : '#721c24'; ?>;">
                            <?php echo $settings['auto_publish_enabled'] ? __('Enabled', 'smo-social') : __('Disabled', 'smo-social'); ?>
                        </span>
                    </div>
                    <div class="smo-status-item">
                        <span class="smo-status-label" style="display: block; margin-bottom: 5px; color: #646970; font-size: 12px;"><?php _e('Time Slots', 'smo-social'); ?></span>
                        <span class="smo-status-badge" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: #e2e3e5; color: #383d41;"><?php echo count($time_slots); ?> <?php _e('configured', 'smo-social'); ?></span>
                    </div>
                    <div class="smo-status-item">
                        <span class="smo-status-label" style="display: block; margin-bottom: 5px; color: #646970; font-size: 12px;"><?php _e('Queue Processing', 'smo-social'); ?></span>
                        <span class="smo-status-badge active" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: #d1e7dd; color: #0f5132;"><?php _e('Active', 'smo-social'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Slots Management -->
        <div class="smo-card" style="grid-column: span 2;">
            <div class="smo-card-header">
                <h2><?php _e('Publishing Time Slots', 'smo-social'); ?></h2>
            </div>
            <div class="smo-card-body">
                <p class="description" style="margin-bottom: 20px;"><?php _e('Configure optimal time slots for automated publishing across different platforms.', 'smo-social'); ?></p>
                
                <div class="smo-time-slots-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($platforms as $platform_key => $platform_info): ?>
                        <div class="smo-platform-slot-card" data-platform="<?php echo esc_attr($platform_key); ?>" style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                            <div class="smo-platform-header" style="background: #f9fafb; padding: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb;">
                                <h3 style="margin: 0; font-size: 14px; font-weight: 600;"><?php echo esc_html($platform_info['name']); ?></h3>
                                <span class="smo-platform-limit" style="font-size: 11px; color: #6b7280;"><?php printf(__('Max %d posts/day', 'smo-social'), $platform_info['max_posts']); ?></span>
                            </div>
                        
                            <div class="smo-platform-slots" style="padding: 15px; min-height: 100px;">
                                <?php
                                $platform_slots_data = isset($time_slots[$platform_key]) ? $time_slots[$platform_key] : array();
                                $platform_slots = is_array($platform_slots_data) ? $platform_slots_data : array();
                                if (empty($platform_slots)):
                                    ?>
                                        <div class="smo-no-slots" style="text-align: center; color: #9ca3af; font-style: italic; padding: 20px;"><?php _e('No time slots configured', 'smo-social'); ?></div>
                                <?php else: ?>
                                        <?php foreach ($platform_slots as $slot): ?>
                                            <div class="smo-time-slot" data-slot-id="<?php echo esc_attr($slot['id']); ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f3f4f6; border-radius: 4px; margin-bottom: 8px;">
                                                <span class="smo-slot-time" style="font-weight: 500; color: #2563eb;"><?php echo esc_html($slot['time']); ?></span>
                                                <span class="smo-slot-days" style="font-size: 11px; color: #6b7280;"><?php echo esc_html(implode(', ', $slot['days'])); ?></span>
                                                <button type="button" class="smo-remove-slot" data-slot-id="<?php echo esc_attr($slot['id']); ?>" style="background: none; border: none; color: #ef4444; cursor: pointer;">&times;</button>
                                            </div>
                                        <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        
                            <div class="smo-platform-actions" style="padding: 10px 15px; border-top: 1px solid #e5e7eb; text-align: center; background: #f9fafb;">
                                <button type="button" class="button button-small smo-add-slot" data-platform="<?php echo esc_attr($platform_key); ?>">
                                    <?php _e('Add Time Slot', 'smo-social'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Content Rules -->
        <div class="smo-card">
            <div class="smo-card-header">
                <h2><?php _e('Content Publishing Rules', 'smo-social'); ?></h2>
            </div>
            <div class="smo-card-body">
                <p class="description" style="margin-bottom: 20px;"><?php _e('Define automated rules for content categorization and platform-specific publishing.', 'smo-social'); ?></p>
                
                <div class="smo-rules-container">
                    <div class="smo-rule-category" style="margin-bottom: 20px;">
                        <h3 style="font-size: 14px; margin-bottom: 10px;"><?php _e('Auto-Categorization', 'smo-social'); ?></h3>
                        <div class="smo-form-group">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="auto_categorize" value="1" <?php checked(isset($settings['content_rules']['auto_categorize']) ? $settings['content_rules']['auto_categorize'] : false); ?>>
                                <?php _e('Automatically categorize new posts', 'smo-social'); ?>
                            </label>
                            <select name="default_category">
                                <option value="general"><?php _e('General', 'smo-social'); ?></option>
                                <option value="promotion"><?php _e('Promotion', 'smo-social'); ?></option>
                                <option value="education"><?php _e('Education', 'smo-social'); ?></option>
                                <option value="news"><?php _e('News', 'smo-social'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="smo-rule-category" style="margin-bottom: 20px;">
                        <h3 style="font-size: 14px; margin-bottom: 10px;"><?php _e('Platform Optimization', 'smo-social'); ?></h3>
                        <div class="smo-form-group">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="auto_optimize" value="1" <?php checked(isset($settings['content_rules']['auto_optimize']) ? $settings['content_rules']['auto_optimize'] : true); ?>>
                                <?php _e('Auto-optimize content for each platform', 'smo-social'); ?>
                            </label>
                        </div>
                        <div class="smo-form-group">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="auto_hashtags" value="1" <?php checked(isset($settings['content_rules']['auto_hashtags']) ? $settings['content_rules']['auto_hashtags'] : true); ?>>
                                <?php _e('Auto-add relevant hashtags', 'smo-social'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="smo-rule-category">
                        <h3 style="font-size: 14px; margin-bottom: 10px;"><?php _e('Queue Management', 'smo-social'); ?></h3>
                        <div class="smo-form-group">
                            <label><?php _e('Max queue size:', 'smo-social'); ?></label>
                            <input type="number" name="max_queue_size" value="<?php echo isset($settings['content_rules']['max_queue_size']) ? esc_attr($settings['content_rules']['max_queue_size']) : '100'; ?>" min="10" max="1000">
                        </div>
                        <div class="smo-form-group">
                            <label><?php _e('Processing interval (minutes):', 'smo-social'); ?></label>
                            <select name="processing_interval">
                                <option value="1" <?php selected(isset($settings['content_rules']['processing_interval']) ? $settings['content_rules']['processing_interval'] : 5, 1); ?>><?php _e('Every minute', 'smo-social'); ?></option>
                                <option value="5" <?php selected(isset($settings['content_rules']['processing_interval']) ? $settings['content_rules']['processing_interval'] : 5, 5); ?>><?php _e('Every 5 minutes', 'smo-social'); ?></option>
                                <option value="10" <?php selected(isset($settings['content_rules']['processing_interval']) ? $settings['content_rules']['processing_interval'] : 5, 10); ?>><?php _e('Every 10 minutes', 'smo-social'); ?></option>
                                <option value="15" <?php selected(isset($settings['content_rules']['processing_interval']) ? $settings['content_rules']['processing_interval'] : 5, 15); ?>><?php _e('Every 15 minutes', 'smo-social'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Status -->
        <div class="smo-card">
            <div class="smo-card-header">
                <h2><?php _e('Current Queue Status', 'smo-social'); ?></h2>
            </div>
            <div class="smo-card-body">
                <div class="smo-queue-status">
                    <div class="smo-queue-stats" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div class="smo-queue-stat" style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 8px;">
                            <span class="smo-stat-number" id="smo-queue-total" style="display: block; font-size: 24px; font-weight: bold; color: #2563eb;">0</span>
                            <span class="smo-stat-label" style="font-size: 12px; color: #6b7280;"><?php _e('Total in Queue', 'smo-social'); ?></span>
                        </div>
                        <div class="smo-queue-stat" style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 8px;">
                            <span class="smo-stat-number" id="smo-queue-processing" style="display: block; font-size: 24px; font-weight: bold; color: #d97706;">0</span>
                            <span class="smo-stat-label" style="font-size: 12px; color: #6b7280;"><?php _e('Processing', 'smo-social'); ?></span>
                        </div>
                        <div class="smo-queue-stat" style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 8px;">
                            <span class="smo-stat-number" id="smo-queue-failed" style="display: block; font-size: 24px; font-weight: bold; color: #dc2626;">0</span>
                            <span class="smo-stat-label" style="font-size: 12px; color: #6b7280;"><?php _e('Failed', 'smo-social'); ?></span>
                        </div>
                        <div class="smo-queue-stat" style="text-align: center; padding: 15px; background: #f9fafb; border-radius: 8px;">
                            <span class="smo-stat-number" id="smo-queue-completed" style="display: block; font-size: 24px; font-weight: bold; color: #059669;">0</span>
                            <span class="smo-stat-label" style="font-size: 12px; color: #6b7280;"><?php _e('Completed Today', 'smo-social'); ?></span>
                        </div>
                    </div>
                    
                    <div class="smo-queue-activity">
                        <h3 style="font-size: 14px; margin-bottom: 10px;"><?php _e('Recent Queue Activity', 'smo-social'); ?></h3>
                        <div id="smo-queue-activity-list" style="max-height: 200px; overflow-y: auto; font-size: 13px;">
                            <p><?php _e('Loading queue activity...', 'smo-social'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Add Time Slot Modal -->
<div id="smo-add-slot-modal" class="smo-modal" style="display: none;">
    <div class="smo-modal-content">
        <div class="smo-modal-header">
            <h3><?php _e('Add Time Slot', 'smo-social'); ?></h3>
            <button type="button" class="smo-modal-close">&times;</button>
        </div>
        <div class="smo-modal-body">
            <form id="smo-add-slot-form">
                <input type="hidden" name="platform" id="smo-slot-platform">

                <div class="smo-form-group">
                    <label for="smo-slot-time"><?php _e('Time', 'smo-social'); ?></label>
                    <input type="time" id="smo-slot-time" name="time" required>
                    <p class="description"><?php _e('Select the time for publishing', 'smo-social'); ?></p>
                </div>

                <div class="smo-form-group">
                    <label><?php _e('Days of Week', 'smo-social'); ?></label>
                    <div>
                        <?php
                        $days = array(
                            'mon' => __('Monday', 'smo-social'),
                            'tue' => __('Tuesday', 'smo-social'),
                            'wed' => __('Wednesday', 'smo-social'),
                            'thu' => __('Thursday', 'smo-social'),
                            'fri' => __('Friday', 'smo-social'),
                            'sat' => __('Saturday', 'smo-social'),
                            'sun' => __('Sunday', 'smo-social')
                        );
                        foreach ($days as $day_key => $day_name): ?>
                            <label style="display: inline-block; margin-right: 10px;">
                                <input type="checkbox" name="days[]" value="<?php echo esc_attr($day_key); ?>" checked>
                                <?php echo esc_html($day_name); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="smo-form-group">
                    <input type="submit" class="smo-btn smo-btn-primary" value="<?php _e('Add Time Slot', 'smo-social'); ?>">
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
    \SMO_Social\Admin\Views\Common\AppLayout::render_end();
}
?>

<script>
jQuery(document).ready(function($) {
    // Initialize
    loadQueueStats();
    loadQueueActivity();
    
    // Auto-refresh queue data every 30 seconds
    setInterval(function() {
        loadQueueStats();
        loadQueueActivity();
    }, 30000);
    
    // Toggle auto-publish
    $('#smo-toggle-auto-publish').click(function() {
        const button = $(this);
        const enabled = button.text().includes('<?php _e('Disable', 'smo-social'); ?>');
        
        $.post(ajaxurl, {
            action: 'smo_toggle_auto_publish',
            nonce: smo_social_ajax.nonce,
            enabled: !enabled
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Process queue now
    $('#smo-process-queue-now').click(function() {
        const button = $(this);
        button.prop('disabled', true).text('<?php _e('Processing...', 'smo-social'); ?>');
        
        $.post(ajaxurl, {
            action: 'smo_process_queue_now',
            nonce: smo_social_ajax.nonce
        }, function(response) {
            button.prop('disabled', false).text('<?php _e('Process Queue Now', 'smo-social'); ?>');
            if (response.success) {
                loadQueueStats();
                loadQueueActivity();
                alert('<?php _e('Queue processed successfully!', 'smo-social'); ?>');
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Add time slot
    $('.smo-add-slot').click(function() {
        const platform = $(this).data('platform');
        $('#smo-slot-platform').val(platform);
        $('#smo-add-slot-modal').show();
    });
    
    // Modal close
    $('.smo-modal-close, .smo-modal').click(function(e) {
        if (e.target === this) {
            $('.smo-modal').hide();
        }
    });
    
    // Add slot form
    $('#smo-add-slot-form').submit(function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'smo_add_time_slot',
            nonce: smo_social_ajax.nonce,
            platform: $('#smo-slot-platform').val(),
            time: $('#smo-slot-time').val(),
            days: $('input[name="days[]"]:checked').map(function() {
                return this.value;
            }).get()
        };
        
        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Remove time slot
    $('.smo-remove-slot').click(function() {
        if (confirm('<?php _e('Are you sure you want to remove this time slot?', 'smo-social'); ?>')) {
            const slotId = $(this).data('slot-id');
            
            $.post(ajaxurl, {
                action: 'smo_remove_time_slot',
                nonce: smo_social_ajax.nonce,
                slot_id: slotId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
    });
    
    function loadQueueStats() {
        $.post(ajaxurl, {
            action: 'smo_get_queue_stats',
            nonce: smo_social_ajax.nonce
        }, function(response) {
            if (response.success) {
                const stats = response.data;
                $('#smo-queue-total').text(stats.total);
                $('#smo-queue-processing').text(stats.processing);
                $('#smo-queue-failed').text(stats.failed);
                $('#smo-queue-completed').text(stats.completed);
            }
        });
    }
    
    function loadQueueActivity() {
        $.post(ajaxurl, {
            action: 'smo_get_queue_activity',
            nonce: smo_social_ajax.nonce
        }, function(response) {
            if (response.success) {
                $('#smo-queue-activity-list').html(response.data);
            }
        });
    }
});
</script>