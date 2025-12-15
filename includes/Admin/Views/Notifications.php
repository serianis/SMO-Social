<?php
/**
 * Notification Center View
 *
 * Centralized notification management system with modern gradient design
 * 
 * @package SMO_Social
 * @subpackage Admin/Views
 * @since 1.0.0
 */

namespace SMO_Social\Admin\Views;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notifications View Class
 */
class Notifications
{
    /**
     * Render the notifications view
     */
    public function render()
    {
        // Get notifications
        $notifications = $this->get_notifications();
        
        // Calculate stats
        $total_notifications = count($notifications);
        $unread_count = count(array_filter($notifications, function($n) { return !$n['read']; }));
        $read_count = $total_notifications - $unread_count;
        $today_count = count(array_filter($notifications, function($n) { 
            return date('Y-m-d', strtotime($n['created_at'] ?? 'now')) === date('Y-m-d'); 
        }));

        // Use Common Layout
        if (class_exists('\SMO_Social\Admin\Views\Common\AppLayout')) {
            \SMO_Social\Admin\Views\Common\AppLayout::render_start('notifications', __('Notifications', 'smo-social'));
        }
        ?>

        <!-- Modern Gradient Header -->
        <div class="smo-import-header">
            <div class="smo-header-content">
                <h1 class="smo-page-title">
                    <span class="smo-icon">🔔</span>
                    <?php _e('Notification Center', 'smo-social'); ?>
                </h1>
                <p class="smo-page-subtitle">
                    <?php _e('Stay updated with all your social media activities', 'smo-social'); ?>
                </p>
            </div>
            <div class="smo-header-actions">
                <button type="button" class="smo-btn smo-btn-secondary" id="smo-clear-notifications">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Clear All', 'smo-social'); ?>
                </button>
                <button type="button" class="smo-btn smo-btn-primary" id="smo-mark-all-read">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Mark All Read', 'smo-social'); ?>
                </button>
            </div>
        </div>

        <!-- Dashboard Stats Overview -->
        <div class="smo-import-dashboard">
            <div class="smo-stats-grid">
                <div class="smo-stat-card smo-stat-gradient-1">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-bell"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($total_notifications); ?></h3>
                        <p class="smo-stat-label"><?php _e('Total Notifications', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">📬 All Time</span>
                    </div>
                </div>

                <div class="smo-stat-card smo-stat-gradient-2">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-email"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($unread_count); ?></h3>
                        <p class="smo-stat-label"><?php _e('Unread', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">📩 Pending</span>
                    </div>
                </div>

                <div class="smo-stat-card smo-stat-gradient-3">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($read_count); ?></h3>
                        <p class="smo-stat-label"><?php _e('Read', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">✅ Completed</span>
                    </div>
                </div>

                <div class="smo-stat-card smo-stat-gradient-4">
                    <div class="smo-stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="smo-stat-content">
                        <h3 class="smo-stat-number"><?php echo esc_html($today_count); ?></h3>
                        <p class="smo-stat-label"><?php _e('Today', 'smo-social'); ?></p>
                        <span class="smo-stat-trend">📅 Recent</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="smo-quick-actions">
            <button class="smo-quick-action-btn" id="smo-filter-all">
                <span class="dashicons dashicons-bell"></span>
                <span><?php _e('All Notifications', 'smo-social'); ?></span>
            </button>
            <button class="smo-quick-action-btn" id="smo-filter-unread">
                <span class="dashicons dashicons-email"></span>
                <span><?php _e('Unread Only', 'smo-social'); ?></span>
            </button>
            <button class="smo-quick-action-btn" id="smo-filter-today">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php _e('Today', 'smo-social'); ?></span>
            </button>
            <button class="smo-quick-action-btn" id="smo-refresh-notifications">
                <span class="dashicons dashicons-update"></span>
                <span><?php _e('Refresh', 'smo-social'); ?></span>
            </button>
        </div>

        <!-- Notifications List -->
        <div class="smo-card">
            <div class="smo-card-header">
                <h2 class="smo-card-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('Recent Notifications', 'smo-social'); ?>
                </h2>
                <div class="smo-card-actions">
                    <select class="smo-form-select" id="smo-notification-filter" style="width: 200px;">
                        <option value="all"><?php _e('All Types', 'smo-social'); ?></option>
                        <option value="success"><?php _e('Success', 'smo-social'); ?></option>
                        <option value="warning"><?php _e('Warnings', 'smo-social'); ?></option>
                        <option value="error"><?php _e('Errors', 'smo-social'); ?></option>
                        <option value="info"><?php _e('Info', 'smo-social'); ?></option>
                    </select>
                </div>
            </div>
            <div class="smo-card-body">
                <div class="smo-notifications-list">
                    <?php if (empty($notifications)): ?>
                        <div class="smo-empty-state">
                            <p><?php _e('No notifications at this time.', 'smo-social'); ?></p>
                            <button class="smo-btn smo-btn-primary" id="smo-create-test-notification">
                                <span class="dashicons dashicons-plus"></span>
                                <?php _e('Create Test Notification', 'smo-social'); ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="smo-notification-item <?php echo $notification['read'] ? 'read' : 'unread'; ?>"
                                data-id="<?php echo esc_attr($notification['id']); ?>"
                                data-type="<?php echo esc_attr($notification['type'] ?? 'info'); ?>">
                                <div class="smo-notification-icon">
                                    <span class="dashicons dashicons-<?php echo esc_attr($notification['icon']); ?>"></span>
                                </div>
                                <div class="smo-notification-content">
                                    <h4><?php echo esc_html($notification['title']); ?></h4>
                                    <p><?php echo esc_html($notification['message']); ?></p>
                                    <span class="smo-notification-time"><?php echo esc_html($notification['time']); ?></span>
                                </div>
                                <div class="smo-notification-actions">
                                    <?php if (!$notification['read']): ?>
                                        <button type="button" class="smo-btn smo-btn-secondary smo-btn-sm smo-mark-read"
                                            data-id="<?php echo esc_attr($notification['id']); ?>">
                                            <span class="dashicons dashicons-yes"></span>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="smo-btn smo-btn-secondary smo-btn-sm smo-dismiss-notification"
                                        data-id="<?php echo esc_attr($notification['id']); ?>">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
            // Mark notification as read
            $('.smo-mark-read').on('click', function() {
                const notificationId = $(this).data('id');
                markNotificationRead(notificationId);
            });

            // Mark all as read
            $('#smo-mark-all-read').on('click', function() {
                $('.smo-notification-item.unread').each(function() {
                    const id = $(this).data('id');
                    markNotificationRead(id);
                });
            });

            // Dismiss notification
            $('.smo-dismiss-notification').on('click', function() {
                const notificationId = $(this).data('id');
                dismissNotification(notificationId);
            });

            // Clear all notifications
            $('#smo-clear-notifications').on('click', function() {
                if (confirm('<?php _e("Are you sure you want to clear all notifications?", "smo-social"); ?>')) {
                    clearAllNotifications();
                }
            });

            // Filter buttons
            $('#smo-filter-all').on('click', function() {
                $('.smo-notification-item').show();
            });

            $('#smo-filter-unread').on('click', function() {
                $('.smo-notification-item').hide();
                $('.smo-notification-item.unread').show();
            });

            $('#smo-filter-today').on('click', function() {
                // Filter logic for today's notifications
                $('.smo-notification-item').each(function() {
                    const time = $(this).find('.smo-notification-time').text();
                    if (time.includes('Just now') || time.includes('minute') || time.includes('hour')) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Type filter
            $('#smo-notification-filter').on('change', function() {
                const type = $(this).val();
                if (type === 'all') {
                    $('.smo-notification-item').show();
                } else {
                    $('.smo-notification-item').hide();
                    $(`.smo-notification-item[data-type="${type}"]`).show();
                }
            });

            // Refresh notifications
            $('#smo-refresh-notifications').on('click', function() {
                location.reload();
            });

            function markNotificationRead(id) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_mark_notification_read',
                        notification_id: id,
                        nonce: '<?php echo wp_create_nonce("smo_notifications_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`.smo-notification-item[data-id="${id}"]`).removeClass('unread').addClass('read');
                            $(`.smo-notification-item[data-id="${id}"] .smo-mark-read`).remove();
                        }
                    }
                });
            }

            function dismissNotification(id) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_dismiss_notification',
                        notification_id: id,
                        nonce: '<?php echo wp_create_nonce("smo_notifications_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`.smo-notification-item[data-id="${id}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    }
                });
            }

            function clearAllNotifications() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smo_clear_notifications',
                        nonce: '<?php echo wp_create_nonce("smo_notifications_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.smo-notification-item').fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    }
                });
            }
        });
        </script>

        <?php
    }

    /**
     * Get notifications
     * 
     * @return array
     */
    private function get_notifications()
    {
        global $wpdb;
        
        $notifications = array();
        
        // Try to get real notifications from database first
        $activity_table = $wpdb->prefix . 'smo_activity_logs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($activity_table) . "'");
        
        if (!empty($table_exists)) {
            // Get recent activities as notifications
            $activities = $wpdb->get_results(
                "SELECT action, details, created_at FROM $activity_table ORDER BY created_at DESC LIMIT 20",
                ARRAY_A
            );
            
            if ($activities) {
                foreach ($activities as $activity) {
                    $notification = array(
                        'id' => wp_hash($activity['created_at'] . $activity['action']),
                        'title' => $this->format_activity_title($activity['action']),
                        'message' => $this->format_activity_message($activity['action'], $activity['details']),
                        'icon' => $this->get_activity_icon($activity['action']),
                        'time' => $this->format_relative_time($activity['created_at']),
                        'type' => $this->get_activity_type($activity['action']),
                        'created_at' => $activity['created_at'],
                        'read' => false
                    );
                    $notifications[] = $notification;
                }
            }
        }
        
        // If no real notifications, provide some default/sample notifications
        if (empty($notifications)) {
            $notifications = array(
                array(
                    'id' => 1,
                    'title' => __('Welcome to SMO Social', 'smo-social'),
                    'message' => __('Get started by connecting your social media platforms.', 'smo-social'),
                    'icon' => 'info',
                    'time' => __('Just now', 'smo-social'),
                    'type' => 'info',
                    'created_at' => current_time('mysql'),
                    'read' => false
                ),
                array(
                    'id' => 2,
                    'title' => __('Create Your First Post', 'smo-social'),
                    'message' => __('Start creating and scheduling your social media content.', 'smo-social'),
                    'icon' => 'edit',
                    'time' => __('1 hour ago', 'smo-social'),
                    'type' => 'info',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'read' => false
                )
            );
        }
        
        return $notifications;
    }
    
    /**
     * Format activity action into notification title
     */
    private function format_activity_title($action)
    {
        $titles = array(
            'POST_SCHEDULED' => __('Post Scheduled', 'smo-social'),
            'POST_PUBLISHED' => __('Post Published', 'smo-social'),
            'POST_FAILED' => __('Post Failed', 'smo-social'),
            'PLATFORM_CONNECTED' => __('Platform Connected', 'smo-social'),
            'PLATFORM_DISCONNECTED' => __('Platform Disconnected', 'smo-social'),
            'USER_LOGIN' => __('User Login', 'smo-social'),
            'SETTINGS_UPDATED' => __('Settings Updated', 'smo-social')
        );
        
        return $titles[$action] ?? __('System Activity', 'smo-social');
    }
    
    /**
     * Format activity details into notification message
     */
    private function format_activity_message($action, $details)
    {
        switch ($action) {
            case 'POST_SCHEDULED':
                return __('A new post has been scheduled successfully.', 'smo-social');
            case 'POST_PUBLISHED':
                return __('A post has been published to social media.', 'smo-social');
            case 'POST_FAILED':
                return __('A post failed to publish. Please check the logs.', 'smo-social');
            case 'PLATFORM_CONNECTED':
                return __('A social media platform has been connected.', 'smo-social');
            case 'PLATFORM_DISCONNECTED':
                return __('A social media platform has been disconnected.', 'smo-social');
            default:
                return __('System activity has been recorded.', 'smo-social');
        }
    }
    
    /**
     * Get icon for activity type
     */
    private function get_activity_icon($action)
    {
        $icons = array(
            'POST_SCHEDULED' => 'calendar-alt',
            'POST_PUBLISHED' => 'yes',
            'POST_FAILED' => 'warning',
            'PLATFORM_CONNECTED' => 'networking',
            'PLATFORM_DISCONNECTED' => 'admin-links',
            'USER_LOGIN' => 'admin-users',
            'SETTINGS_UPDATED' => 'admin-settings'
        );
        
        return $icons[$action] ?? 'admin-generic';
    }
    
    /**
     * Get activity type for filtering
     */
    private function get_activity_type($action)
    {
        $types = array(
            'POST_SCHEDULED' => 'success',
            'POST_PUBLISHED' => 'success',
            'POST_FAILED' => 'error',
            'PLATFORM_CONNECTED' => 'success',
            'PLATFORM_DISCONNECTED' => 'warning',
            'USER_LOGIN' => 'info',
            'SETTINGS_UPDATED' => 'info'
        );
        
        return $types[$action] ?? 'info';
    }
    
    /**
     * Format timestamp into relative time
     */
    private function format_relative_time($timestamp)
    {
        $time_diff = current_time('timestamp') - strtotime($timestamp);
        
        if ($time_diff < 60) {
            return __('Just now', 'smo-social');
        } elseif ($time_diff < 3600) {
            $minutes = floor($time_diff / 60);
            return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'smo-social'), $minutes);
        } elseif ($time_diff < 86400) {
            $hours = floor($time_diff / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'smo-social'), $hours);
        } elseif ($time_diff < 2592000) {
            $days = floor($time_diff / 86400);
            return sprintf(_n('%d day ago', '%d days ago', $days, 'smo-social'), $days);
        } else {
            return date('M j, Y', strtotime($timestamp));
        }
    }
}
