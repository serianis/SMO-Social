<?php
/**
 * Auto Publish Widget
 * 
 * Manages automated publishing and scheduling of WordPress content
 *
 * @package SMO_Social
 * @subpackage Admin\Widgets
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Widgets;

use SMO_Social\Scheduling\AutoPublishManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto Publish Widget Class
 */
class AutoPublishWidget extends BaseWidget {
    
    /**
     * @var AutoPublishManager
     */
    private $auto_publish_manager;
    
    /**
     * Initialize widget properties
     */
    protected function init() {
        $this->id = 'auto_publish';
        $this->name = __('Auto Publish', 'smo-social');
        $this->description = __('Automated publishing and scheduling of your WordPress content', 'smo-social');
        $this->category = 'automation';
        $this->icon = 'dashicons-calendar-alt';
        $this->default_size = 'large';
        $this->capabilities = array('manage_options');
        
        $this->auto_publish_manager = new AutoPublishManager();
    }
    
    /**
     * Get widget data
     */
    public function get_data($args = array()) {
        $settings = $this->auto_publish_manager->get_settings();
        $queue_stats = $this->auto_publish_manager->get_queue_stats();
        $recent_activity = $this->auto_publish_manager->get_recent_activity(5);
        
        return array(
            'settings' => $settings,
            'queue_stats' => $queue_stats,
            'recent_activity' => $recent_activity,
            'available_platforms' => $this->get_available_platforms(),
            'time_slots' => $this->get_time_slots($settings)
        );
    }
    
    /**
     * Get available platforms
     */
    private function get_available_platforms() {
        global $wpdb;
        $table = $wpdb->prefix . 'smo_platform_tokens';
        
        $platforms = $wpdb->get_results(
            "SELECT DISTINCT platform_slug, platform_name 
             FROM $table 
             WHERE status = 'active'",
            ARRAY_A
        );
        
        return $platforms ?: array();
    }
    
    /**
     * Get configured time slots
     */
    private function get_time_slots($settings) {
        return isset($settings['time_slots']) ? $settings['time_slots'] : array(
            array('time' => '09:00', 'days' => array('monday', 'wednesday', 'friday')),
            array('time' => '14:00', 'days' => array('tuesday', 'thursday')),
            array('time' => '18:00', 'days' => array('saturday', 'sunday'))
        );
    }
    
    /**
     * Render widget content
     */
    public function render($data = array()) {
        if (empty($data)) {
            $data = $this->get_data();
        }
        
        $settings = $data['settings'];
        $queue_stats = $data['queue_stats'];
        $is_enabled = !empty($settings['enabled']);
        
        ob_start();
        ?>
        
        <div class="smo-widget smo-auto-publish-widget">
            <div class="smo-widget-header">
                <h3><?php echo esc_html($this->name); ?></h3>
                <div class="smo-widget-actions">
                    <label class="smo-toggle-switch">
                        <input type="checkbox" 
                               id="auto-publish-toggle" 
                               <?php checked($is_enabled); ?>
                               data-action="toggle-auto-publish">
                        <span class="smo-toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="smo-widget-body">
                <!-- Status Banner -->
                <div class="smo-status-banner <?php echo $is_enabled ? 'active' : 'inactive'; ?>">
                    <span class="smo-status-icon dashicons dashicons-<?php echo $is_enabled ? 'yes-alt' : 'dismiss'; ?>"></span>
                    <span class="smo-status-text">
                        <?php echo $is_enabled 
                            ? esc_html__('Auto-publish is active', 'smo-social')
                            : esc_html__('Auto-publish is disabled', 'smo-social'); ?>
                    </span>
                </div>
                
                <!-- Queue Statistics -->
                <div class="smo-queue-stats">
                    <div class="smo-stat-row">
                        <div class="smo-stat-item">
                            <span class="smo-stat-label"><?php esc_html_e('Pending', 'smo-social'); ?></span>
                            <span class="smo-stat-value"><?php echo esc_html($queue_stats['pending']); ?></span>
                        </div>
                        <div class="smo-stat-item">
                            <span class="smo-stat-label"><?php esc_html_e('Ready', 'smo-social'); ?></span>
                            <span class="smo-stat-value smo-highlight"><?php echo esc_html($queue_stats['ready_to_publish']); ?></span>
                        </div>
                        <div class="smo-stat-item">
                            <span class="smo-stat-label"><?php esc_html_e('Processed', 'smo-social'); ?></span>
                            <span class="smo-stat-value"><?php echo esc_html($queue_stats['processed']); ?></span>
                        </div>
                        <div class="smo-stat-item">
                            <span class="smo-stat-label"><?php esc_html_e('Failed', 'smo-social'); ?></span>
                            <span class="smo-stat-value <?php echo $queue_stats['failed'] > 0 ? 'smo-error' : ''; ?>">
                                <?php echo esc_html($queue_stats['failed']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Settings -->
                <div class="smo-quick-settings">
                    <h4><?php esc_html_e('Quick Settings', 'smo-social'); ?></h4>
                    
                    <!-- Platform Selection -->
                    <div class="smo-setting-group">
                        <label><?php esc_html_e('Platforms', 'smo-social'); ?></label>
                        <div class="smo-platform-checkboxes">
                            <?php foreach ($data['available_platforms'] as $platform): ?>
                            <label class="smo-checkbox-label">
                                <input type="checkbox" 
                                       name="auto_publish_platforms[]" 
                                       value="<?php echo esc_attr($platform['platform_slug']); ?>"
                                       <?php checked(in_array($platform['platform_slug'], $settings['platforms'] ?? array())); ?>>
                                <span><?php echo esc_html($platform['platform_name'] ?? ucfirst($platform['platform_slug'])); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Delay Setting -->
                    <div class="smo-setting-group">
                        <label for="auto-publish-delay"><?php esc_html_e('Publish Delay (minutes)', 'smo-social'); ?></label>
                        <input type="number" 
                               id="auto-publish-delay" 
                               name="delay_minutes"
                               value="<?php echo esc_attr($settings['delay_minutes'] ?? 0); ?>"
                               min="0" 
                               max="1440"
                               class="smo-input-small">
                        <p class="smo-help-text"><?php esc_html_e('Delay before auto-publishing (0 for immediate)', 'smo-social'); ?></p>
                    </div>
                    
                    <!-- Post Types -->
                    <div class="smo-setting-group">
                        <label><?php esc_html_e('Auto-publish for', 'smo-social'); ?></label>
                        <div class="smo-post-type-checkboxes">
                            <?php 
                            $post_types = array('post' => 'Posts', 'page' => 'Pages');
                            foreach ($post_types as $type => $label): 
                            ?>
                            <label class="smo-checkbox-label">
                                <input type="checkbox" 
                                       name="auto_publish_post_types[]" 
                                       value="<?php echo esc_attr($type); ?>"
                                       <?php checked(in_array($type, $settings['post_types'] ?? array('post'))); ?>>
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Additional Options -->
                    <div class="smo-setting-group">
                        <label class="smo-checkbox-label">
                            <input type="checkbox" 
                                   name="auto_hashtags"
                                   <?php checked($settings['auto_hashtags'] ?? true); ?>>
                            <span><?php esc_html_e('Auto-generate hashtags', 'smo-social'); ?></span>
                        </label>
                        
                        <label class="smo-checkbox-label">
                            <input type="checkbox" 
                                   name="auto_optimize"
                                   <?php checked($settings['auto_optimize'] ?? true); ?>>
                            <span><?php esc_html_e('Auto-optimize content for each platform', 'smo-social'); ?></span>
                        </label>
                        
                        <label class="smo-checkbox-label">
                            <input type="checkbox" 
                                   name="require_featured_image"
                                   <?php checked($settings['require_featured_image'] ?? false); ?>>
                            <span><?php esc_html_e('Require featured image', 'smo-social'); ?></span>
                        </label>
                    </div>
                    
                    <!-- Save Button -->
                    <button type="button" class="smo-btn smo-btn-primary" id="save-auto-publish-settings">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e('Save Settings', 'smo-social'); ?>
                    </button>
                </div>
                
                <!-- Time Slots Configuration -->
                <div class="smo-time-slots-section">
                    <h4><?php esc_html_e('Publishing Time Slots', 'smo-social'); ?></h4>
                    <p class="smo-help-text"><?php esc_html_e('Set specific times for publishing your content', 'smo-social'); ?></p>
                    
                    <div id="time-slots-container">
                        <?php foreach ($data['time_slots'] as $index => $slot): ?>
                        <div class="smo-time-slot" data-index="<?php echo esc_attr($index); ?>">
                            <input type="time" 
                                   name="time_slots[<?php echo esc_attr($index); ?>][time]" 
                                   value="<?php echo esc_attr($slot['time']); ?>"
                                   class="smo-input-time">
                            <div class="smo-days-selector">
                                <?php 
                                $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
                                foreach ($days as $day): 
                                ?>
                                <label class="smo-day-checkbox" title="<?php echo esc_attr(ucfirst($day)); ?>">
                                    <input type="checkbox" 
                                           name="time_slots[<?php echo esc_attr($index); ?>][days][]" 
                                           value="<?php echo esc_attr($day); ?>"
                                           <?php checked(in_array($day, $slot['days'] ?? array())); ?>>
                                    <span><?php echo esc_html(substr($day, 0, 1)); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="smo-btn-icon smo-remove-slot" title="<?php esc_attr_e('Remove', 'smo-social'); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="smo-btn smo-btn-secondary" id="add-time-slot">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Time Slot', 'smo-social'); ?>
                    </button>
                </div>
                
                <!-- Recent Activity -->
                <?php if (!empty($data['recent_activity'])): ?>
                <div class="smo-recent-activity">
                    <h4><?php esc_html_e('Recent Activity', 'smo-social'); ?></h4>
                    <ul class="smo-activity-list">
                        <?php foreach ($data['recent_activity'] as $activity): ?>
                        <li class="smo-activity-item">
                            <span class="smo-activity-status smo-status-<?php echo esc_attr($activity['status']); ?>"></span>
                            <div class="smo-activity-content">
                                <strong><?php echo esc_html($activity['title'] ?? $activity['post_title'] ?? 'Untitled'); ?></strong>
                                <span class="smo-activity-meta">
                                    <?php echo esc_html(ucfirst($activity['status'])); ?> • 
                                    <?php echo esc_html(human_time_diff(strtotime($activity['created_at']), current_time('timestamp'))); ?> ago
                                </span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Process Queue Button -->
                <?php if ($queue_stats['ready_to_publish'] > 0): ?>
                <div class="smo-queue-actions">
                    <button type="button" class="smo-btn smo-btn-primary smo-btn-block" id="process-queue-now">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php esc_html_e('Process Queue Now', 'smo-social'); ?>
                        <span class="smo-badge"><?php echo esc_html($queue_stats['ready_to_publish']); ?></span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle auto-publish
            $('#auto-publish-toggle').on('change', function() {
                var enabled = $(this).is(':checked');
                $.ajax({
                    url: smo_social_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smo_toggle_auto_publish',
                        nonce: smo_social_ajax.nonce,
                        enabled: enabled
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.smo-status-banner')
                                .removeClass('active inactive')
                                .addClass(enabled ? 'active' : 'inactive');
                            $('.smo-status-text').text(enabled ? 
                                '<?php esc_html_e('Auto-publish is active', 'smo-social'); ?>' : 
                                '<?php esc_html_e('Auto-publish is disabled', 'smo-social'); ?>');
                        }
                    }
                });
            });
            
            // Save settings
            $('#save-auto-publish-settings').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
                
                var settings = {
                    platforms: $('input[name="auto_publish_platforms[]"]:checked').map(function() {
                        return $(this).val();
                    }).get(),
                    delay_minutes: $('#auto-publish-delay').val(),
                    post_types: $('input[name="auto_publish_post_types[]"]:checked').map(function() {
                        return $(this).val();
                    }).get(),
                    auto_hashtags: $('input[name="auto_hashtags"]').is(':checked'),
                    auto_optimize: $('input[name="auto_optimize"]').is(':checked'),
                    require_featured_image: $('input[name="require_featured_image"]').is(':checked')
                };
                
                $.ajax({
                    url: smo_social_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smo_save_auto_publish_settings',
                        nonce: smo_social_ajax.nonce,
                        settings: settings
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.html('<span class="dashicons dashicons-yes"></span> Saved!');
                            setTimeout(function() {
                                $btn.html(originalText).prop('disabled', false);
                            }, 2000);
                        }
                    },
                    error: function() {
                        $btn.html(originalText).prop('disabled', false);
                    }
                });
            });
            
            // Add time slot
            $('#add-time-slot').on('click', function() {
                var index = $('#time-slots-container .smo-time-slot').length;
                var slotHtml = `
                    <div class="smo-time-slot" data-index="${index}">
                        <input type="time" name="time_slots[${index}][time]" value="09:00" class="smo-input-time">
                        <div class="smo-days-selector">
                            <?php foreach (array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') as $day): ?>
                            <label class="smo-day-checkbox" title="<?php echo esc_attr(ucfirst($day)); ?>">
                                <input type="checkbox" name="time_slots[${index}][days][]" value="<?php echo esc_attr($day); ?>">
                                <span><?php echo esc_html(substr($day, 0, 1)); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="smo-btn-icon smo-remove-slot">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                `;
                $('#time-slots-container').append(slotHtml);
            });
            
            // Remove time slot
            $(document).on('click', '.smo-remove-slot', function() {
                $(this).closest('.smo-time-slot').remove();
            });
            
            // Process queue now
            $('#process-queue-now').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: smo_social_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smo_process_queue_now',
                        nonce: smo_social_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            });
        });
        </script>
        
        <style>
        .smo-auto-publish-widget .smo-status-banner {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .smo-auto-publish-widget .smo-status-banner.active {
            background: #e7f5ec;
            color: #00a32a;
            border: 1px solid #00a32a;
        }
        
        .smo-auto-publish-widget .smo-status-banner.inactive {
            background: #f0f0f1;
            color: #646970;
            border: 1px solid #c3c4c7;
        }
        
        .smo-auto-publish-widget .smo-queue-stats {
            margin-bottom: 20px;
        }
        
        .smo-auto-publish-widget .smo-stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        
        .smo-auto-publish-widget .smo-stat-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .smo-auto-publish-widget .smo-stat-label {
            display: block;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .smo-auto-publish-widget .smo-stat-value {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: #1d2327;
        }
        
        .smo-auto-publish-widget .smo-stat-value.smo-highlight {
            color: #0073aa;
        }
        
        .smo-auto-publish-widget .smo-stat-value.smo-error {
            color: #d63638;
        }
        
        .smo-auto-publish-widget .smo-quick-settings {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .smo-auto-publish-widget .smo-setting-group {
            margin-bottom: 15px;
        }
        
        .smo-auto-publish-widget .smo-setting-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1d2327;
        }
        
        .smo-auto-publish-widget .smo-checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 15px;
            margin-bottom: 8px;
            font-weight: normal;
        }
        
        .smo-auto-publish-widget .smo-input-small {
            width: 100px;
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .smo-auto-publish-widget .smo-help-text {
            font-size: 12px;
            color: #666;
            margin: 5px 0 0;
        }
        
        .smo-auto-publish-widget .smo-time-slot {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .smo-auto-publish-widget .smo-input-time {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .smo-auto-publish-widget .smo-days-selector {
            display: flex;
            gap: 5px;
            flex: 1;
        }
        
        .smo-auto-publish-widget .smo-day-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 2px solid #ddd;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .smo-auto-publish-widget .smo-day-checkbox input {
            display: none;
        }
        
        .smo-auto-publish-widget .smo-day-checkbox input:checked + span {
            background: #0073aa;
            color: white;
        }
        
        .smo-auto-publish-widget .smo-day-checkbox span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            font-weight: 600;
            font-size: 12px;
        }
        
        .smo-auto-publish-widget .smo-activity-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .smo-auto-publish-widget .smo-activity-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .smo-auto-publish-widget .smo-status-pending {
            background: #dba617;
        }
        
        .smo-auto-publish-widget .smo-status-processed {
            background: #00a32a;
        }
        
        .smo-auto-publish-widget .smo-status-failed {
            background: #d63638;
        }
        
        .smo-auto-publish-widget .smo-activity-meta {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        
        .smo-auto-publish-widget .smo-toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .smo-auto-publish-widget .smo-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .smo-auto-publish-widget .smo-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }
        
        .smo-auto-publish-widget .smo-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        .smo-auto-publish-widget input:checked + .smo-toggle-slider {
            background-color: #0073aa;
        }
        
        .smo-auto-publish-widget input:checked + .smo-toggle-slider:before {
            transform: translateX(26px);
        }
        
        .smo-auto-publish-widget .smo-btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .smo-auto-publish-widget .smo-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        </style>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get widget settings schema
     */
    public function get_settings_schema() {
        return array(
            'show_queue_stats' => array(
                'type' => 'checkbox',
                'label' => __('Show Queue Statistics', 'smo-social'),
                'default' => true
            ),
            'show_recent_activity' => array(
                'type' => 'checkbox',
                'label' => __('Show Recent Activity', 'smo-social'),
                'default' => true
            ),
            'activity_limit' => array(
                'type' => 'number',
                'label' => __('Activity Items to Show', 'smo-social'),
                'default' => 5,
                'min' => 1,
                'max' => 20
            )
        );
    }
}
