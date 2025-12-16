<?php
/**
 * WordPress REST API Real-Time Manager
 * Αντικαθιστά το WebSocket system με WordPress-native REST API polling
 */

namespace SMO_Social\RealTime;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DataManager.php';
require_once __DIR__ . '/PollingManager.php';

/**
 * REST API Real-Time Manager
 * Διαχειρίζεται REST API endpoints για real-time functionality
 */
class RESTAPIManager {
    /** @var DataManager */
    private $data_manager;
    /** @var PollingManager */
    private $polling_manager;
    /** @var array */
    private $config;

    public function __construct() {
        $this->data_manager = new DataManager();
        $this->polling_manager = new PollingManager();
        $this->config = [
            'poll_interval' => 5, // seconds
            'max_poll_interval' => 30,
            'cleanup_interval' => 300, // 5 minutes
            'max_data_age' => 3600, // 1 hour
            'max_channels' => 100,
            'max_subscribers_per_channel' => 50
        ];
        
        $this->load_config();
        $this->init_hooks();
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
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('init', [$this, 'init_real_time_system']);
        
        // Cleanup old data
        add_action('smo_realtime_cleanup', [$this, 'cleanup_old_data']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('smo_realtime_cleanup')) {
            wp_schedule_event(time(), 'every_5_minutes', 'smo_realtime_cleanup');
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Subscribe to channel
        register_rest_route('smo-social/v1', '/realtime/subscribe', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_subscribe'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'channel' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'token' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Unsubscribe from channel
        register_rest_route('smo-social/v1', '/realtime/unsubscribe', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_unsubscribe'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'channel' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Get messages for channel
        register_rest_route('smo-social/v1', '/realtime/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_messages'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'channel' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'since' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Publish message to channel
        register_rest_route('smo-social/v1', '/realtime/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_publish'],
            'permission_callback' => [$this, 'check_publish_permissions'],
            'args' => [
                'channel' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'data' => [
                    'required' => true,
                    'type' => 'object'
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'message',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        // Get server status
        register_rest_route('smo-social/v1', '/realtime/status', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_status'],
            'permission_callback' => [$this, 'check_permissions']
        ]);

        // Generate authentication token
        register_rest_route('smo-social/v1', '/realtime/token', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate_token'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
    }

    /**
     * Initialize real-time system
     */
    public function init_real_time_system() {
        // Initialize data manager
        $this->data_manager->initialize();
        
        // Initialize polling manager
        $this->polling_manager->initialize();
        
        error_log('SMO RealTime: REST API system initialized');
    }

    /**
     * Check permissions for API access
     */
    public function check_permissions($request) {
        // Allow for read operations if user is logged in
        if (is_user_logged_in()) {
            return true;
        }

        // Check for valid nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }

        return false;
    }

    /**
     * Check permissions for publishing
     */
    public function check_publish_permissions($request) {
        // Only allow authenticated users to publish
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        return current_user_can('publish_posts') || current_user_can('edit_posts');
    }

    /**
     * Handle channel subscription
     */
    public function handle_subscribe($request) {
        $channel = $request->get_param('channel');
        $token = $request->get_param('token');
        
        if (empty($channel)) {
            return new \WP_Error('invalid_channel', 'Channel is required', ['status' => 400]);
        }

        $user_id = get_current_user_id();
        if (!$user_id && $token) {
            $user_id = $this->validate_token($token);
        }

        if (!$user_id) {
            return new \WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
        }

        $result = $this->data_manager->subscribe_to_channel($channel, $user_id);
        
        if ($result) {
            return [
                'success' => true,
                'channel' => $channel,
                'subscribed_at' => current_time('mysql')
            ];
        }

        return new \WP_Error('subscription_failed', 'Failed to subscribe to channel', ['status' => 500]);
    }

    /**
     * Handle channel unsubscription
     */
    public function handle_unsubscribe($request) {
        $channel = $request->get_param('channel');
        
        if (empty($channel)) {
            return new \WP_Error('invalid_channel', 'Channel is required', ['status' => 400]);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
        }

        $result = $this->data_manager->unsubscribe_from_channel($channel, $user_id);
        
        if ($result) {
            return [
                'success' => true,
                'channel' => $channel,
                'unsubscribed_at' => current_time('mysql')
            ];
        }

        return new \WP_Error('unsubscription_failed', 'Failed to unsubscribe from channel', ['status' => 500]);
    }

    /**
     * Handle getting messages for a channel
     */
    public function handle_get_messages($request) {
        $channel = $request->get_param('channel');
        $since = $request->get_param('since');
        
        if (empty($channel)) {
            return new \WP_Error('invalid_channel', 'Channel is required', ['status' => 400]);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
        }

        // Check if user is subscribed to this channel
        if (!$this->data_manager->is_user_subscribed($channel, $user_id)) {
            return new \WP_Error('not_subscribed', 'User not subscribed to channel', ['status' => 403]);
        }

        $messages = $this->data_manager->get_channel_messages($channel, $since);
        
        return [
            'success' => true,
            'channel' => $channel,
            'messages' => $messages,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Handle publishing message to channel
     */
    public function handle_publish($request) {
        $channel = $request->get_param('channel');
        $data = $request->get_param('data');
        $type = $request->get_param('type') ?: 'message';
        
        if (empty($channel) || empty($data)) {
            return new \WP_Error('invalid_data', 'Channel and data are required', ['status' => 400]);
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
        }

        // Check if user has permission to publish to this channel
        if (!$this->can_publish_to_channel($channel, $user_id)) {
            return new \WP_Error('permission_denied', 'Insufficient permissions', ['status' => 403]);
        }

        $message = [
            'type' => $type,
            'channel' => $channel,
            'data' => $data,
            'user_id' => $user_id,
            'timestamp' => current_time('mysql'),
            'id' => uniqid('msg_', true)
        ];

        $result = $this->data_manager->add_message_to_channel($channel, $message);
        
        if ($result) {
            return [
                'success' => true,
                'message' => $message
            ];
        }

        return new \WP_Error('publish_failed', 'Failed to publish message', ['status' => 500]);
    }

    /**
     * Handle getting server status
     */
    public function handle_get_status($request) {
        $stats = $this->data_manager->get_statistics();
        
        return [
            'success' => true,
            'status' => 'active',
            'version' => '2.0.0',
            'type' => 'rest_api_polling',
            'statistics' => $stats,
            'config' => [
                'poll_interval' => $this->config['poll_interval'],
                'max_data_age' => $this->config['max_data_age']
            ]
        ];
    }

    /**
     * Handle generating authentication token
     */
    public function handle_generate_token($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
        }

        $token = $this->generate_token($user_id);
        
        return [
            'success' => true,
            'token' => $token,
            'user_id' => $user_id,
            'expires' => date('Y-m-d H:i:s', time() + 3600) // 1 hour
        ];
    }

    /**
     * Check if user can publish to channel
     */
    private function can_publish_to_channel($channel, $user_id) {
        // Admin users can publish to any channel
        if (current_user_can('manage_options')) {
            return true;
        }

        // Users can publish to their own channels
        $user_channels = ['user_' . $user_id, 'notifications_user_' . $user_id];
        if (in_array($channel, $user_channels)) {
            return true;
        }

        // Check if user is subscribed to the channel
        return $this->data_manager->is_user_subscribed($channel, $user_id);
    }

    /**
     * Validate authentication token
     */
    private function validate_token($token) {
        $stored_tokens = get_option('smo_realtime_tokens', []);
        
        if (!is_array($stored_tokens)) {
            return false;
        }

        foreach ($stored_tokens as $user_id => $user_token) {
            if ($user_token === $token) {
                return $user_id;
            }
        }

        return false;
    }

    /**
     * Generate authentication token
     */
    private function generate_token($user_id) {
        $token = wp_generate_password(32, false);
        $stored_tokens = get_option('smo_realtime_tokens', []);
        
        if (!is_array($stored_tokens)) {
            $stored_tokens = [];
        }
        
        $stored_tokens[$user_id] = $token;
        update_option('smo_realtime_tokens', $stored_tokens);

        return $token;
    }

    /**
     * Cleanup old data
     */
    public function cleanup_old_data() {
        $this->data_manager->cleanup_old_data($this->config['max_data_age']);
        $this->polling_manager->cleanup_idle_sessions();
        
        error_log('SMO RealTime: Cleanup completed');
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
}