<?php
/**
 * Real-Time Migration Script
 * Βοηθά στη μετάβαση από WebSocket σε REST API polling
 */

namespace SMO_Social\RealTime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration Manager
 * Handles migration from WebSocket to REST API polling system
 */
class MigrationManager {
    /** @var RealTimeManager */
    private $real_time_manager;
    /** @var array */
    private $migration_steps;

    public function __construct() {
        $this->real_time_manager = new RealTimeManager();
        $this->migration_steps = [
            'analyze_current_setup',
            'backup_existing_data',
            'disable_websocket_system',
            'enable_rest_api_system',
            'update_client_code',
            'test_new_system',
            'cleanup_websocket_files'
        ];
    }

    /**
     * Run complete migration
     */
    public function run_migration() {
        $results = [];
        
        foreach ($this->migration_steps as $step) {
            try {
                $result = $this->$step();
                $results[$step] = [
                    'success' => true,
                    'result' => $result
                ];
                error_log("SMO Migration: {$step} completed successfully");
            } catch (\Exception $e) {
                $results[$step] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                error_log("SMO Migration: {$step} failed: " . $e->getMessage());
                break;
            }
        }

        return $results;
    }

    /**
     * Analyze current WebSocket setup
     */
    private function analyze_current_setup() {
        $analysis = [
            'websocket_enabled' => false,
            'websocket_files_exist' => false,
            'websocket_config_exists' => false,
            'websocket_running' => false,
            'legacy_setup_detected' => false
        ];

        // Check if legacy WebSocket files exist
        $legacy_files = [
            ABSPATH . 'websocket-server.php',
            plugin_dir_path(__DIR__) . '../../websocket-server.php',
            plugin_dir_path(__DIR__) . '../../start-websocket-server.bat',
            plugin_dir_path(__DIR__) . '../../websocket-launcher.bat'
        ];

        foreach ($legacy_files as $file) {
            if (file_exists($file)) {
                $analysis['websocket_files_exist'] = true;
                break;
            }
        }

        // Check WordPress options
        $websocket_config = get_option('smo_websocket_config', []);
        if (!empty($websocket_config)) {
            $analysis['websocket_config_exists'] = true;
            $analysis['websocket_enabled'] = isset($websocket_config['enabled']) ? $websocket_config['enabled'] : false;
        }

        // Check for legacy setup indicators
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $analysis['legacy_setup_detected'] = strpos(ABSPATH, 'xampp') !== false ||
                                               strpos(ABSPATH, 'htdocs') !== false ||
                                               file_exists(ABSPATH . 'xampp-websocket-server.php');
        }

        // Test WebSocket connection (if enabled)
        if ($analysis['websocket_enabled']) {
            $host = $websocket_config['host'] ?? '127.0.0.1';
            $port = $websocket_config['port'] ?? 8080;
            
            $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket) {
                socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
                $connected = @socket_connect($socket, $host, $port);
                socket_close($socket);
                
                $analysis['websocket_running'] = $connected;
            }
        }

        return $analysis;
    }

    /**
     * Backup existing data
     */
    private function backup_existing_data() {
        $backup_data = [];

        // Backup WebSocket configuration
        $websocket_config = get_option('smo_websocket_config', []);
        if (!empty($websocket_config)) {
            $backup_data['websocket_config'] = $websocket_config;
        }

        // Backup tokens
        $websocket_tokens = get_option('smo_websocket_tokens', []);
        if (!empty($websocket_tokens)) {
            $backup_data['websocket_tokens'] = $websocket_tokens;
        }

        // Backup any existing real-time data
        $channels = get_option('smo_realtime_channels', []);
        $messages = get_option('smo_realtime_messages', []);
        $subscribers = get_option('smo_realtime_subscribers', []);

        if (!empty($channels) || !empty($messages) || !empty($subscribers)) {
            $backup_data['real_time_data'] = [
                'channels' => $channels,
                'messages' => $messages,
                'subscribers' => $subscribers
            ];
        }

        // Save backup
        if (!empty($backup_data)) {
            $backup_file = wp_upload_dir()['basedir'] . '/smo_websocket_migration_backup_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
            $backup_data['backup_file'] = $backup_file;
        }

        return $backup_data;
    }

    /**
     * Disable WebSocket system
     */
    private function disable_websocket_system() {
        // Disable WebSocket configuration
        update_option('smo_websocket_config', array_merge(
            get_option('smo_websocket_config', []),
            ['enabled' => false, 'migrated_to' => 'rest_api_polling']
        ));

        // Stop any running WebSocket server
        $this->stop_websocket_server();

        return true;
    }

    /**
     * Stop WebSocket server if running
     */
    private function stop_websocket_server() {
        // Try to stop via process management (if supported)
        if (function_exists('shell_exec')) {
            // Kill PHP processes running legacy WebSocket servers
            shell_exec('pkill -f "websocket-server.php" 2>/dev/null');
            shell_exec('pkill -f "xampp-websocket-server.php" 2>/dev/null'); // Legacy support
            shell_exec('taskkill /F /IM php.exe 2>/dev/null'); // Windows - be careful with this
        }

        return true;
    }

    /**
     * Enable REST API system
     */
    private function enable_rest_api_system() {
        // Initialize the new system
        $this->real_time_manager->initialize();
        
        // Enable the system
        $this->real_time_manager->set_enabled(true);
        
        // Configure it
        $config = [
            'enabled' => true,
            'method' => 'rest_api_polling',
            'default_poll_interval' => 5,
            'max_channels_per_user' => 10,
            'debug_mode' => WP_DEBUG
        ];
        
        $this->real_time_manager->update_config($config);

        return true;
    }

    /**
     * Update client code references
     */
    private function update_client_code() {
        $updates = [];

        // Update JavaScript file references
        $js_files_to_update = [
            plugin_dir_path(__DIR__) . '../../assets/js/smo-realtime.js'
        ];

        foreach ($js_files_to_update as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                
                // Replace WebSocket references with polling references
                $updated_content = str_replace(
                    ['WebSocket', 'ws://', 'SMORealTimeClient'],
                    ['Polling', 'wp-json/smo-social/v1/realtime/', 'SMOPollingClient'],
                    $content
                );
                
                if ($updated_content !== $content) {
                    file_put_contents($file, $updated_content);
                    $updates[] = $file;
                }
            }
        }

        // Update PHP files that reference WebSocket
        $php_files_to_scan = [
            plugin_dir_path(__DIR__) . '../../includes/WebSocket/',
            plugin_dir_path(__DIR__) . '../../includes/Chat/',
        ];

        foreach ($php_files_to_scan as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*.php');
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if (strpos($content, 'WebSocket') !== false) {
                        // Add compatibility notes
                        $updated_content = "<?php\n/**\n * COMPATIBILITY FILE - WebSocket references kept for migration\n * New system uses REST API polling\n */\n\n" . $content;
                        file_put_contents($file, $updated_content);
                        $updates[] = $file;
                    }
                }
            }
        }

        return $updates;
    }

    /**
     * Test new system
     */
    private function test_new_system() {
        $tests = [];

        // Test REST API endpoints
        $endpoints_to_test = [
            'smo-social/v1/realtime/status',
            'smo-social/v1/realtime/subscribe',
            'smo-social/v1/realtime/messages',
            'smo-social/v1/realtime/publish'
        ];

        foreach ($endpoints_to_test as $endpoint) {
            $url = rest_url($endpoint);
            $response = wp_remote_get($url);
            
            $tests[$endpoint] = [
                'accessible' => !is_wp_error($response),
                'status_code' => is_wp_error($response) ? null : wp_remote_retrieve_response_code($response)
            ];
        }

        // Test data manager
        try {
            $data_manager = new DataManager();
            $test_channel = 'test_migration_' . time();
            $data_manager->subscribe_to_channel($test_channel, 1);
            $data_manager->add_message_to_channel($test_channel, [
                'type' => 'test',
                'data' => ['message' => 'Migration test']
            ]);
            
            $tests['data_manager'] = [
                'working' => true,
                'test_channel' => $test_channel
            ];
        } catch (\Exception $e) {
            $tests['data_manager'] = [
                'working' => false,
                'error' => $e->getMessage()
            ];
        }

        // Test polling manager
        try {
            $polling_manager = new PollingManager();
            $session_id = $polling_manager->create_polling_session(1, ['test_channel']);
            
            $tests['polling_manager'] = [
                'working' => !empty($session_id),
                'session_id' => $session_id
            ];
        } catch (\Exception $e) {
            $tests['polling_manager'] = [
                'working' => false,
                'error' => $e->getMessage()
            ];
        }

        return $tests;
    }

    /**
     * Clean up WebSocket files
     */
    private function cleanup_websocket_files() {
        $files_to_remove = [
            ABSPATH . 'xampp-websocket-server.php', // Legacy support
            ABSPATH . 'websocket-server.php',
            plugin_dir_path(__DIR__) . '../../xampp-websocket-server.php', // Legacy support
            plugin_dir_path(__DIR__) . '../../websocket-server.php',
            plugin_dir_path(__DIR__) . '../../setup-xampp-websocket.php', // Legacy support
            plugin_dir_path(__DIR__) . '../../setup-websocket.php',
            plugin_dir_path(__DIR__) . '../../start-websocket-server.bat',
            plugin_dir_path(__DIR__) . '../../XAMPP_WEBSOCKET_LAUNCHER.bat', // Legacy support
            plugin_dir_path(__DIR__) . '../../websocket-launcher.bat'
        ];

        $removed_files = [];
        
        foreach ($files_to_remove as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    $removed_files[] = $file;
                }
            }
        }

        // Remove WebSocket directory if empty (but keep the core WebSocket classes)
        $websocket_dir = plugin_dir_path(__DIR__) . '../../includes/WebSocket/';
        if (is_dir($websocket_dir)) {
            $files_in_dir = glob($websocket_dir . '*');
            // Only remove if directory is truly empty or contains only legacy files
            if (count($files_in_dir) === 0 ||
                (count($files_in_dir) === 1 && strpos($files_in_dir[0], 'Legacy') !== false)) {
                rmdir($websocket_dir);
                $removed_files[] = $websocket_dir;
            }
        }

        return $removed_files;
    }

    /**
     * Get migration status
     */
    public function get_migration_status() {
        $current_setup = $this->analyze_current_setup();
        
        return [
            'migration_needed' => $current_setup['websocket_enabled'] || $current_setup['websocket_files_exist'],
            'current_setup' => $current_setup,
            'migration_steps' => $this->migration_steps,
            'new_system_status' => $this->real_time_manager->get_status()
        ];
    }

    /**
     * Rollback migration
     */
    public function rollback_migration() {
        // Re-enable WebSocket system
        update_option('smo_websocket_config', array_merge(
            get_option('smo_websocket_config', []),
            ['enabled' => true, 'migrated_from' => 'rest_api_polling']
        ));

        // Disable new system
        $this->real_time_manager->set_enabled(false);

        // Restore backup data
        $backup_files = glob(wp_upload_dir()['basedir'] . '/smo_websocket_migration_backup_*.json');
        if (!empty($backup_files)) {
            $latest_backup = end($backup_files);
            $backup_data = json_decode(file_get_contents($latest_backup), true);
            
            if (isset($backup_data['websocket_config'])) {
                update_option('smo_websocket_config', $backup_data['websocket_config']);
            }
            
            if (isset($backup_data['websocket_tokens'])) {
                update_option('smo_websocket_tokens', $backup_data['websocket_tokens']);
            }
        }

        error_log('SMO Migration: Rollback completed');
        return true;
    }
}