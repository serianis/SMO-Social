<?php
/**
 * Real-Time Settings Page
 * Διαχείριση του νέου REST API polling system
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$real_time_config = get_option('smo_realtime_config', [
    'enabled' => true,
    'method' => 'rest_api_polling',
    'default_poll_interval' => 5,
    'max_channels_per_user' => 10,
    'debug_mode' => false
]);

// Get system status
$real_time_manager = isset($GLOBALS['smo_real_time_manager']) ? $GLOBALS['smo_real_time_manager'] : null;
$status = $real_time_manager ? $real_time_manager->get_status() : null;

// Get statistics
$statistics = $real_time_manager ? $real_time_manager->get_statistics() : null;
?>

<div class="wrap">
    <h1><?php _e('SMO Social Real-Time Settings', 'smo-social'); ?></h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully.', 'smo-social'); ?></p>
        </div>
    <?php endif; ?>

    <div class="notice notice-info">
        <p><strong><?php _e('Migration Notice:', 'smo-social'); ?></strong> 
        <?php _e('The WebSocket system has been replaced with a WordPress-native REST API polling system. This provides better compatibility, reliability, and works on all hosting environments without requiring external servers.', 'smo-social'); ?></p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('smo_realtime_config'); ?>
        <?php do_settings_sections('smo_realtime_config'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Real-Time System', 'smo-social'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="smo_realtime_config[enabled]" value="1" 
                               <?php checked($real_time_config['enabled'], true); ?>>
                        <?php _e('Enable real-time features', 'smo-social'); ?>
                    </label>
                    <p class="description"><?php _e('Enable/disable real-time notifications and updates.', 'smo-social'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Polling Interval', 'smo-social'); ?></th>
                <td>
                    <input type="number" name="smo_realtime_config[default_poll_interval]" 
                           value="<?php echo esc_attr($real_time_config['default_poll_interval']); ?>" 
                           min="2" max="30" class="small-text">
                    <p class="description"><?php _e('Time in seconds between polling requests (2-30 seconds).', 'smo-social'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Max Channels per User', 'smo-social'); ?></th>
                <td>
                    <input type="number" name="smo_realtime_config[max_channels_per_user]" 
                           value="<?php echo esc_attr($real_time_config['max_channels_per_user']); ?>" 
                           min="1" max="50" class="small-text">
                    <p class="description"><?php _e('Maximum number of channels a user can subscribe to.', 'smo-social'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Debug Mode', 'smo-social'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="smo_realtime_config[debug_mode]" value="1" 
                               <?php checked($real_time_config['debug_mode'], true); ?>>
                        <?php _e('Enable debug logging', 'smo-social'); ?>
                    </label>
                    <p class="description"><?php _e('Log real-time system activity to WordPress debug log.', 'smo-social'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'smo-social')); ?>
    </form>

    <!-- System Status -->
    <div class="card">
        <h2><?php _e('System Status', 'smo-social'); ?></h2>
        <?php if ($status): ?>
            <table class="widefat">
                <tr>
                    <td><?php _e('System Enabled', 'smo-social'); ?></td>
                    <td><?php echo $status['enabled'] ? '✓' : '✗'; ?></td>
                </tr>
                <tr>
                    <td><?php _e('Method', 'smo-social'); ?></td>
                    <td><?php echo esc_html($status['method']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Initialized', 'smo-social'); ?></td>
                    <td><?php echo $status['initialized'] ? '✓' : '✗'; ?></td>
                </tr>
                <tr>
                    <td><?php _e('Components', 'smo-social'); ?></td>
                    <td>
                        <?php foreach ($status['components'] as $component => $status): ?>
                            <div><?php echo esc_html($component); ?>: <?php echo $status ? '✓' : '✗'; ?></div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        <?php else: ?>
            <p class="description"><?php _e('Real-time system is not initialized. Please check plugin activation.', 'smo-social'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Statistics -->
    <?php if ($statistics): ?>
    <div class="card">
        <h2><?php _e('Statistics', 'smo-social'); ?></h2>
        <table class="widefat">
            <tr>
                <td><?php _e('Data Manager', 'smo-social'); ?></td>
                <td>
                    <div><?php _e('Total Messages', 'smo-social'); ?>: <?php echo $statistics['data_manager']['total_messages']; ?></div>
                    <div><?php _e('Total Channels', 'smo-social'); ?>: <?php echo $statistics['data_manager']['total_channels']; ?></div>
                    <div><?php _e('Total Subscribers', 'smo-social'); ?>: <?php echo $statistics['data_manager']['total_subscribers']; ?></div>
                </td>
            </tr>
            <tr>
                <td><?php _e('Polling Manager', 'smo-social'); ?></td>
                <td>
                    <div><?php _e('Total Sessions', 'smo-social'); ?>: <?php echo $statistics['polling_manager']['total_sessions']; ?></div>
                    <div><?php _e('Active Sessions', 'smo-social'); ?>: <?php echo $statistics['polling_manager']['active_sessions']; ?></div>
                    <div><?php _e('Total Requests', 'smo-social'); ?>: <?php echo $statistics['polling_manager']['total_requests']; ?></div>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Migration Tools -->
    <div class="card">
        <h2><?php _e('Migration Tools', 'smo-social'); ?></h2>
        <p class="description"><?php _e('Use these tools to migrate from the old WebSocket system or troubleshoot issues.', 'smo-social'); ?></p>
        
        <form method="post" action="">
            <input type="hidden" name="smo_realtime_action" value="migrate">
            <?php wp_nonce_field('smo_realtime_migration'); ?>
            <input type="submit" class="button button-primary" value="<?php _e('Run Migration', 'smo-social'); ?>">
        </form>

        <form method="post" action="" style="margin-top: 10px;">
            <input type="hidden" name="smo_realtime_action" value="rollback">
            <?php wp_nonce_field('smo_realtime_rollback'); ?>
            <input type="submit" class="button" value="<?php _e('Rollback Migration', 'smo-social'); ?>">
        </form>

        <form method="post" action="" style="margin-top: 10px;">
            <input type="hidden" name="smo_realtime_action" value="cleanup">
            <?php wp_nonce_field('smo_realtime_cleanup'); ?>
            <input type="submit" class="button" value="<?php _e('Cleanup Old Files', 'smo-social'); ?>">
        </form>
    </div>

    <!-- Test Tools -->
    <div class="card">
        <h2><?php _e('Test Tools', 'smo-social'); ?></h2>
        <p class="description"><?php _e('Test the real-time system functionality.', 'smo-social'); ?></p>
        
        <form method="post" action="">
            <input type="hidden" name="smo_realtime_action" value="test">
            <?php wp_nonce_field('smo_realtime_test'); ?>
            <input type="submit" class="button button-secondary" value="<?php _e('Test Real-Time System', 'smo-social'); ?>">
        </form>

        <?php if (isset($_POST['smo_realtime_action']) && $_POST['smo_realtime_action'] === 'test'): ?>
        <div style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-left: 4px solid #0073aa;">
            <h3><?php _e('Test Results:', 'smo-social'); ?></h3>
            <?php
            if ($real_time_manager) {
                $test_results = $real_time_manager->get_status();
                echo '<pre>' . print_r($test_results, true) . '</pre>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Documentation -->
    <div class="card">
        <h2><?php _e('Documentation', 'smo-social'); ?></h2>
        <h3><?php _e('What Changed:', 'smo-social'); ?></h3>
        <ul>
            <li><?php _e('WebSocket server replaced with WordPress REST API polling', 'smo-social'); ?></li>
            <li><?php _e('No external server dependencies required', 'smo-social'); ?></li>
            <li><?php _e('Works on all WordPress hosting environments', 'smo-social'); ?></li>
            <li><?php _e('Better error handling and reliability', 'smo-social'); ?></li>
        </ul>

        <h3><?php _e('Benefits:', 'smo-social'); ?></h3>
        <ul>
            <li><?php _e('No external server setup required', 'smo-social'); ?></li>
            <li><?php _e('No sockets extension configuration needed', 'smo-social'); ?></li>
            <li><?php _e('Works on shared hosting', 'smo-social'); ?></li>
            <li><?php _e('Better performance and scalability', 'smo-social'); ?></li>
        </ul>

        <h3><?php _e('API Endpoints:', 'smo-social'); ?></h3>
        <p><?php _e('New REST API endpoints available:', 'smo-social'); ?></p>
        <ul>
            <li><code>/wp-json/smo-social/v1/realtime/subscribe</code> - <?php _e('Subscribe to channel', 'smo-social'); ?></li>
            <li><code>/wp-json/smo-social/v1/realtime/unsubscribe</code> - <?php _e('Unsubscribe from channel', 'smo-social'); ?></li>
            <li><code>/wp-json/smo-social/v1/realtime/messages</code> - <?php _e('Get messages', 'smo-social'); ?></li>
            <li><code>/wp-json/smo-social/v1/realtime/publish</code> - <?php _e('Publish message', 'smo-social'); ?></li>
            <li><code>/wp-json/smo-social/v1/realtime/status</code> - <?php _e('Get system status', 'smo-social'); ?></li>
        </ul>
    </div>
</div>