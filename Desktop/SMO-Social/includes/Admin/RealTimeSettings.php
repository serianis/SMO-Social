<?php
/**
 * Real-Time Settings Handler
 * Διαχείριση των ρυθμίσεων του real-time system
 */

namespace SMO_Social\Admin;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Views/RealTimeSettings.php';

/**
 * Real-Time Settings Handler
 */
class RealTimeSettings {
    /** @var string */
    private $page_slug = 'smo-realtime-settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_smo_realtime_actions', [$this, 'handle_actions']);
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'smo-social',
            __('Real-Time Settings', 'smo-social'),
            __('Real-Time', 'smo-social'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Include the view file
        require_once __DIR__ . '/Views/RealTimeSettings.php';
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'smo_realtime_config',
            'smo_realtime_config',
            [
                'sanitize_callback' => [$this, 'sanitize_config']
            ]
        );

        // Add section
        add_settings_section(
            'smo_realtime_main',
            __('Real-Time Configuration', 'smo-social'),
            function() {
                echo '<p>' . __('Configure the real-time system settings.', 'smo-social') . '</p>';
            },
            'smo_realtime_config'
        );
    }

    /**
     * Sanitize configuration
     */
    public function sanitize_config($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : true;
        $sanitized['method'] = 'rest_api_polling'; // Force to polling method
        $sanitized['default_poll_interval'] = isset($input['default_poll_interval']) ? max(2, min(30, intval($input['default_poll_interval']))) : 5;
        $sanitized['max_channels_per_user'] = isset($input['max_channels_per_user']) ? max(1, min(50, intval($input['max_channels_per_user']))) : 10;
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? (bool) $input['debug_mode'] : false;
        
        return $sanitized;
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'smo-social'));
        }

        if (!isset($_POST['smo_realtime_action'])) {
            wp_redirect(admin_url('admin.php?page=' . $this->page_slug));
            exit;
        }

        $action = sanitize_text_field($_POST['smo_realtime_action']);

        switch ($action) {
            case 'migrate':
                $this->handle_migration();
                break;
            case 'rollback':
                $this->handle_rollback();
                break;
            case 'cleanup':
                $this->handle_cleanup();
                break;
            case 'test':
                $this->handle_test();
                break;
        }

        wp_redirect(admin_url('admin.php?page=' . $this->page_slug));
        exit;
    }

    /**
     * Handle migration
     */
    private function handle_migration() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'smo_realtime_migration')) {
            wp_die(__('Invalid nonce', 'smo-social'));
        }

        if (!class_exists('SMO_Social\\RealTime\\MigrationManager')) {
            require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/RealTime/MigrationManager.php';
        }

        $migration_manager = new \SMO_Social\RealTime\MigrationManager();
        $results = $migration_manager->run_migration();

        // Store results for display
        update_option('smo_realtime_migration_results', $results);

        add_action('admin_notices', function() {
            $results = get_option('smo_realtime_migration_results', []);
            $success_count = 0;
            $error_count = 0;

            foreach ($results as $step => $result) {
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }

            if ($error_count === 0) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(__('Migration completed successfully! %d steps completed.', 'smo-social'), $success_count) . '</p>';
                echo '</div>';
            } else {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . sprintf(__('Migration completed with %d errors and %d successes.', 'smo-social'), $error_count, $success_count) . '</p>';
                echo '</div>';
            }
        });
    }

    /**
     * Handle rollback
     */
    private function handle_rollback() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'smo_realtime_rollback')) {
            wp_die(__('Invalid nonce', 'smo-social'));
        }

        if (!class_exists('SMO_Social\\RealTime\\MigrationManager')) {
            require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/RealTime/MigrationManager.php';
        }

        $migration_manager = new \SMO_Social\RealTime\MigrationManager();
        $migration_manager->rollback_migration();

        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . __('Migration rolled back successfully. WebSocket system has been re-enabled.', 'smo-social') . '</p>';
            echo '</div>';
        });
    }

    /**
     * Handle cleanup
     */
    private function handle_cleanup() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'smo_realtime_cleanup')) {
            wp_die(__('Invalid nonce', 'smo-social'));
        }

        if (!class_exists('SMO_Social\\RealTime\\MigrationManager')) {
            require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/RealTime/MigrationManager.php';
        }

        $migration_manager = new \SMO_Social\RealTime\MigrationManager();
        $removed_files = $migration_manager->cleanup_websocket_files();

        add_action('admin_notices', function() use ($removed_files) {
            if (!empty($removed_files)) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf(__('Cleanup completed. Removed %d files.', 'smo-social'), count($removed_files)) . '</p>';
                echo '<ul>';
                foreach ($removed_files as $file) {
                    echo '<li>' . esc_html($file) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            } else {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>' . __('No WebSocket files found to remove.', 'smo-social') . '</p>';
                echo '</div>';
            }
        });
    }

    /**
     * Handle test
     */
    private function handle_test() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'smo_realtime_test')) {
            wp_die(__('Invalid nonce', 'smo-social'));
        }

        // Test is handled in the view file
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Test completed. Check results below.', 'smo-social') . '</p>';
            echo '</div>';
        });
    }
}