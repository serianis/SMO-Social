<?php
/**
 * Real-Time Manager
 * Κύριος manager που ενοποιεί το νέο REST API-based real-time system
 * Αντικαθιστά το παλιό WebSocket system
 */

namespace SMO_Social\RealTime;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/RESTAPIManager.php';
require_once __DIR__ . '/DataManager.php';
require_once __DIR__ . '/PollingManager.php';

/**
 * Real-Time Manager
 * Κύριος orchestrator για το real-time system
 */
class RealTimeManager {
    /** @var RESTAPIManager */
    private $rest_api_manager;
    /** @var DataManager */
    private $data_manager;
    /** @var PollingManager */
    private $polling_manager;
    /** @var array */
    private $config;
    /** @var bool */
    private $initialized = false;

    public function __construct() {
        $this->config = [
            'enabled' => true,
            'method' => 'rest_api_polling', // Αντί για websocket
            'max_channels_per_user' => 10,
            'max_messages_per_channel' => 100,
            'default_poll_interval' => 5, // seconds
            'enable_websocket_fallback' => false, // Disabled by default
            'debug_mode' => false
        ];
        
        $this->load_config();
    }

    /**
     * Load configuration from WordPress options
     */
    private function load_config() {
        $saved_config = get_option('smo_realtime_config', []);
        if (is_array($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }
    }

    /**
     * Initialize the real-time system
     */
    public function initialize() {
        if ($this->initialized) {
            return;
        }

        if (!$this->config['enabled']) {
            error_log('SMO RealTime: System disabled in configuration');
            return;
        }

        try {
            // Initialize core components
            $this->rest_api_manager = new RESTAPIManager();
            $this->data_manager = new DataManager();
            $this->polling_manager = new PollingManager();

            // Initialize WordPress integration
            $this->init_wordpress_integration();

            $this->initialized = true;
            
            error_log('SMO RealTime: System initialized successfully with method: ' . $this->config['method']);

        } catch (\Exception $e) {
            error_log('SMO RealTime: Initialization failed: ' . $e->getMessage());
            $this->config['enabled'] = false;
        }
    }

    /**
     * Initialize WordPress integration
     */
    private function init_wordpress_integration() {
        // Add REST API routes
        add_action('rest_api_init', [$this, 'register_compatibility_routes']);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Add AJAX handlers for backwards compatibility
        add_action('wp_ajax_smo_get_websocket_config', [$this, 'ajax_get_config']);
        add_action('wp_ajax_nopriv_smo_get_websocket_config', [$this, 'ajax_get_config']);

        // Add settings
        add_action('admin_init', [$this, 'register_settings']);

        // Cleanup on plugin deactivation
        register_deactivation_hook(__FILE__, [$this, 'cleanup_on_deactivation']);
    }

    /**
     * Register compatibility REST routes for existing code
     */
    public function register_compatibility_routes() {
        // Get messages endpoint (compatible with old WebSocket client)
        register_rest_route('smo-social/v1', '/realtime/poll', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_poll_request'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'session_id' => [
                    'required' => false,
                    'type' => 'string'
                ],
                'channels' => [
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ]);
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        error_log('SMO RealTime: enqueue_scripts called');
        
        // Enqueue the new polling client
        $script_url = plugin_dir_url(__DIR__) . '../../assets/js/smo-polling-client.js';
        error_log('SMO RealTime: Script URL: ' . $script_url);
        
        wp_enqueue_script(
            'smo-polling-client',
            $script_url,
            ['jquery'],
            '2.0.0',
            true
        );

        // Pass configuration to JavaScript
        $config = [
            'enabled' => $this->config['enabled'],
            'method' => $this->config['method'],
            'pollInterval' => $this->config['default_poll_interval'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('smo-social/v1/realtime/')
        ];
        
        error_log('SMO RealTime: Config being passed: ' . print_r($config, true));
        
        wp_localize_script('smo-polling-client', 'smoRealTimeConfig', $config);

        // Dequeue old WebSocket scripts if they exist
        wp_dequeue_script('smo-realtime');
        
        error_log('SMO RealTime: Scripts enqueued successfully');
    }

    /**
     * Register WordPress settings
     */
    public function register_settings() {
        register_setting('smo_social_settings', 'smo_realtime_config', [
            'sanitize_callback' => [$this, 'sanitize_config']
        ]);
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
     * Check permissions for API access
     */
    public function check_permissions($request) {
        return current_user_can('read') || is_user_logged_in();
    }

    /**
     * Handle poll request (compatibility endpoint)
     */
    public function handle_poll_request($request) {
        $session_id = $request->get_param('session_id');
        $channels_param = $request->get_param('channels');
        
        if (!$session_id) {
            return new \WP_Error('missing_session', 'Session ID required', ['status' => 400]);
        }

        // Parse channels
        $channels = [];
        if ($channels_param) {
            $channels = array_map('trim', explode(',', $channels_param));
        }

        // Get or create session
        $session = $this->polling_manager->get_polling_session($session_id);
        if (!$session) {
            return new \WP_Error('invalid_session', 'Invalid session ID', ['status' => 400]);
        }

        // Update session channels
        if (!empty($channels)) {
            $this->polling_manager->update_polling_session($session_id, $channels);
        }

        // Get messages
        $messages = $this->polling_manager->get_session_messages($session_id, $this->data_manager);
        
        return $messages;
    }

    /**
     * AJAX handler for getting config (backwards compatibility)
     */
    public function ajax_get_config() {
        check_ajax_referer('smo_social_nonce', 'nonce');
        
        $config = [
            'success' => true,
            'url' => rest_url('smo-social/v1/realtime/poll'),
            'method' => $this->config['method'],
            'enabled' => $this->config['enabled'],
            'poll_interval' => $this->config['default_poll_interval']
        ];

        wp_send_json($config);
    }

    /**
     * Get system status
     */
    public function get_status() {
        if (!$this->initialized) {
            return [
                'enabled' => false,
                'initialized' => false,
                'method' => 'none',
                'message' => 'System not initialized'
            ];
        }

        return [
            'enabled' => $this->config['enabled'],
            'initialized' => $this->initialized,
            'method' => $this->config['method'],
            'statistics' => $this->get_statistics(),
            'components' => [
                'rest_api' => $this->rest_api_manager ? true : false,
                'data_manager' => $this->data_manager ? true : false,
                'polling_manager' => $this->polling_manager ? true : false
            ]
        ];
    }

    /**
     * Get system statistics
     */
    public function get_statistics() {
        if (!$this->initialized) {
            return [];
        }

        return [
            'data_manager' => $this->data_manager->get_statistics(),
            'polling_manager' => $this->polling_manager->get_statistics()
        ];
    }

    /**
     * Get configuration
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Update configuration
     */
    public function update_config($new_config) {
        $this->config = array_merge($this->config, $new_config);
        update_option('smo_realtime_config', $this->config);
        
        return $this->config;
    }

    /**
     * Enable/disable system
     */
    public function set_enabled($enabled) {
        $this->config['enabled'] = (bool) $enabled;
        update_option('smo_realtime_config', $this->config);
        
        error_log('SMO RealTime: System ' . ($enabled ? 'enabled' : 'disabled'));
        
        return $this->config['enabled'];
    }

    /**
     * Publish message to channel
     */
    public function publish($channel, $data, $type = 'message') {
        if (!$this->initialized || !$this->config['enabled']) {
            return false;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        $message = [
            'type' => $type,
            'channel' => $channel,
            'data' => $data,
            'user_id' => $user_id,
            'timestamp' => current_time('mysql'),
            'id' => uniqid('msg_', true)
        ];

        return $this->data_manager->add_message_to_channel($channel, $message);
    }

    /**
     * Subscribe user to channel
     */
    public function subscribe_user($channel, $user_id = null) {
        if (!$this->initialized || !$this->config['enabled']) {
            return false;
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return $this->data_manager->subscribe_to_channel($channel, $user_id);
    }

    /**
     * Get client JavaScript configuration
     */
    public function get_client_config() {
        return [
            'enabled' => $this->config['enabled'],
            'method' => $this->config['method'],
            'pollInterval' => $this->config['default_poll_interval'] * 1000, // Convert to milliseconds
            'restUrl' => rest_url('smo-social/v1/realtime/'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'debug' => $this->config['debug_mode']
        ];
    }

    /**
     * Cleanup on plugin deactivation
     */
    public function cleanup_on_deactivation() {
        if ($this->initialized && $this->polling_manager) {
            // Clean up all polling sessions
            $this->polling_manager->force_cleanup();
        }
        
        error_log('SMO RealTime: Cleanup completed on deactivation');
    }

    /**
     * Force system restart
     */
    public function restart() {
        $this->initialized = false;
        $this->initialize();
        
        error_log('SMO RealTime: System restarted');
        
        return $this->initialized;
    }

    /**
     * Get compatibility info for migration from WebSocket
     */
    public function get_compatibility_info() {
        return [
            'migration_from' => 'websocket',
            'migration_to' => 'rest_api_polling',
            'breaking_changes' => [
                'No WebSocket server required',
                'Uses HTTP polling instead of persistent connections',
                'Different client library (smo-polling-client.js)',
                'New REST API endpoints'
            ],
            'compatibility_endpoints' => [
                'smo_get_websocket_config' => 'Available for backwards compatibility'
            ],
            'benefits' => [
                'No external dependencies',
                'Works on all WordPress hosting',
                'Better error handling',
                'Scalable architecture'
            ]
        ];
    }
}