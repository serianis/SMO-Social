<?php
namespace SMO_Social\Core;

class Plugin {
    public function init() {
        // Initialize admin interface with error handling
        if (class_exists('\SMO_Social\Admin\Admin')) {
            try {
                new \SMO_Social\Admin\Admin();
            } catch (\Exception $e) {
                error_log('SMO Social: Error initializing Admin class: ' . $e->getMessage());
                // Continue initialization even if Admin fails
            }
        } else {
            error_log('SMO Social: Admin class not found');
        }

        // Initialize API handlers
        new \SMO_Social\API\API();

        // Initialize platforms
        new \SMO_Social\Platforms\Manager();

        // Initialize AI features
        \SMO_Social\AI\Manager::getInstance();

        // Initialize scheduling
        new \SMO_Social\Scheduling\Scheduler();

        // Initialize analytics
        new \SMO_Social\Analytics\Dashboard();

        // Initialize community features
        new \SMO_Social\Community\TemplateManager();
        new \SMO_Social\Community\ReputationManager();
        new \SMO_Social\Community\ValidationPipeline();
        new \SMO_Social\Admin\CommunityMarketplace();

        // Initialize media library manager
        if (class_exists('\SMO_Social\Content\MediaLibraryManager')) {
            new \SMO_Social\Content\MediaLibraryManager();
        }

        // Initialize branding manager
        if (class_exists('\SMO_Social\WhiteLabel\BrandingManager')) {
            try {
                new \SMO_Social\WhiteLabel\BrandingManager();
            } catch (\Exception $e) {
                error_log('SMO Social: Error initializing BrandingManager: ' . $e->getMessage());
            }
        } else {
            error_log('SMO Social: BrandingManager class not found');
        }

        // Initialize real-time features
        $this->init_realtime_features();

        // Add hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    /**
     * Initialize real-time features
     */
    private function init_realtime_features() {
        // Initialize WebSocket server manager
        if (class_exists('\SMO_Social\WebSocket\WebSocketServerManager')) {
            new \SMO_Social\WebSocket\WebSocketServerManager();
        }

        // Initialize comment manager for real-time comments (placeholder for future implementation)
        // if (class_exists('\SMO_Social\Social\CommentManager')) {
        //     new \SMO_Social\Social\CommentManager();
        // }

        // Initialize real-time collaboration
        if (class_exists('\SMO_Social\Collaboration\RealTimeCollaboration')) {
            new \SMO_Social\Collaboration\RealTimeCollaboration();
        }

        // Add AJAX handlers for WebSocket
        add_action('wp_ajax_smo_get_websocket_config', array($this, 'ajax_get_websocket_config'));
        add_action('wp_ajax_smo_get_websocket_token', array($this, 'ajax_get_websocket_token'));
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }

        wp_enqueue_script('smo-social-admin', SMO_SOCIAL_PLUGIN_URL . 'assets/js/admin.js', array('wp-element'), SMO_SOCIAL_VERSION, true);
        wp_enqueue_style('smo-social-admin', SMO_SOCIAL_PLUGIN_URL . 'assets/css/admin.css', array(), SMO_SOCIAL_VERSION);

        // Enqueue real-time features
        wp_enqueue_script('smo-realtime', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-realtime.js', array('jquery'), SMO_SOCIAL_VERSION, true);

        // Localize script with AJAX data and WebSocket token
        wp_localize_script('smo-realtime', 'smo_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_social_nonce'),
            'websocket_token' => $this->get_websocket_token_for_current_user()
        ));
    }

    public function enqueue_frontend_scripts() {
        // Enqueue real-time features on frontend if user is logged in
        if (is_user_logged_in()) {
            wp_enqueue_script('smo-realtime', SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-realtime.js', array('jquery'), SMO_SOCIAL_VERSION, true);

            wp_localize_script('smo-realtime', 'smo_ajax_object', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('smo_social_nonce'),
                'websocket_token' => $this->get_websocket_token_for_current_user()
            ));
        }
    }

    /**
     * Get WebSocket token for current user
     */
    private function get_websocket_token_for_current_user() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }

        // Try to get from RealTimeCollaboration
        if (class_exists('\SMO_Social\Collaboration\RealTimeCollaboration')) {
            $collaboration = new \SMO_Social\Collaboration\RealTimeCollaboration();
            if (method_exists($collaboration, 'get_websocket_token')) {
                return $collaboration->get_websocket_token($user_id);
            }
        }

        // Generate a simple token for testing
        return 'test_token_' . md5($user_id . '_smo_social_' . time());
    }

    /**
     * AJAX handler for WebSocket configuration
     */
    public function ajax_get_websocket_config() {
        // Enhanced nonce validation with multiple fallback strategies
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        $ajax_nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        // Try different nonce validation strategies
        $nonce_valid = false;
        
        // Strategy 1: Check if it's a REST API nonce
        if (!$nonce_valid && function_exists('wp_verify_nonce') && function_exists('wp_create_nonce')) {
            $rest_nonce = wp_create_nonce('wp_rest');
            if (wp_verify_nonce($nonce, 'wp_rest') || wp_verify_nonce($ajax_nonce, 'wp_rest')) {
                $nonce_valid = true;
                error_log('SMO WebSocket: REST API nonce validation successful');
            }
        }
        
        // Strategy 2: Check if it's a standard AJAX nonce
        if (!$nonce_valid && function_exists('check_ajax_referer')) {
            try {
                check_ajax_referer('smo_social_nonce', 'nonce');
                $nonce_valid = true;
                error_log('SMO WebSocket: AJAX nonce validation successful');
            } catch (\Exception $e) {
                error_log('SMO WebSocket: AJAX nonce validation failed: ' . $e->getMessage());
            }
        }
        
        // Strategy 3: Check if it's a general WordPress nonce
        if (!$nonce_valid && function_exists('wp_verify_nonce')) {
            if (wp_verify_nonce($nonce, 'smo_social_nonce') || wp_verify_nonce($ajax_nonce, 'smo_social_nonce')) {
                $nonce_valid = true;
                error_log('SMO WebSocket: General nonce validation successful');
            }
        }
        
        // Strategy 4: Allow in debug mode or if no nonce provided (for fallback)
        if (!$nonce_valid && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SMO WebSocket: Debug mode - allowing request without valid nonce');
            $nonce_valid = true;
        }
        
        if (!$nonce_valid) {
            error_log('SMO WebSocket: All nonce validation strategies failed');
            wp_send_json(array(
                'success' => false,
                'message' => 'Nonce validation failed',
                'url' => null
            ));
            return;
        }

        if (!is_user_logged_in()) {
            error_log('SMO WebSocket: User not logged in');
            wp_die(__('Unauthorized'));
        }

        // Enhanced logging for debugging
        error_log('SMO WebSocket: ajax_get_websocket_config called successfully');
        error_log('SMO WebSocket: User ID: ' . get_current_user_id());
        error_log('SMO WebSocket: User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));
        error_log('SMO WebSocket: HTTP Host: ' . ($_SERVER['HTTP_HOST'] ?? 'Unknown'));
        
        // Try multiple fallback options with logging
        $config = array('url' => null);
        $attempts = array();

        // Attempt 1: Try local WebSocket server (WordPress-based)
        try {
            if (class_exists('\SMO_Social\WebSocket\WebSocketServerManager')) {
                error_log('SMO WebSocket: WebSocketServerManager class exists, trying to instantiate');
                $ws_manager = new \SMO_Social\WebSocket\WebSocketServerManager();
                $status = $ws_manager->get_status();
                error_log('SMO WebSocket: Server status: ' . json_encode($status));
                
                if (!empty($status['config']['port']) && $status['running']) {
                    $protocol = !empty($status['config']['ssl']) ? 'wss' : 'ws';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $port = $status['config']['port'];
                    
                    // Remove default ports from host if present
                    $host = preg_replace('/:\d+$/', '', $host);
                    
                    $local_url = "{$protocol}://{$host}:{$port}";
                    $config['url'] = $local_url;
                    $attempts[] = "WordPress server: {$local_url}";
                    error_log('SMO WebSocket: Using WordPress server: ' . $local_url);
                }
            }
        } catch (\Exception $e) {
            error_log('SMO WebSocket: WordPress server error: ' . $e->getMessage());
            $attempts[] = "WordPress server failed: " . $e->getMessage();
        }

        // Attempt 2: Check for alternative WebSocket configurations
        if (!$config['url']) {
            try {
                // Check for custom WebSocket server configurations
                $custom_ws_config = get_option('smo_custom_websocket_config', []);
                if (!empty($custom_ws_config) && !empty($custom_ws_config['url'])) {
                    $config['url'] = $custom_ws_config['url'];
                    $attempts[] = "Custom WebSocket: " . $custom_ws_config['url'];
                    error_log('SMO WebSocket: Using custom WebSocket configuration');
                }
            } catch (\Exception $e) {
                error_log('SMO WebSocket: Custom WebSocket check failed: ' . $e->getMessage());
                $attempts[] = "Custom WebSocket check failed: " . $e->getMessage();
            }
        }

        // Attempt 3: Check WordPress option fallback
        if (!$config['url']) {
            try {
                $ws_config = get_option('smo_websocket_config');
                if (!empty($ws_config) && !empty($ws_config['port'])) {
                    $protocol = !empty($ws_config['ssl_key_file']) && !empty($ws_config['ssl_cert_file']) ? 'wss' : 'ws';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $port = $ws_config['port'];
                    
                    // Remove default ports from host if present
                    $host = preg_replace('/:\d+$/', '', $host);

                    $option_url = "{$protocol}://{$host}:{$port}";
                    $config['url'] = $option_url;
                    $attempts[] = "WP option: {$option_url}";
                    error_log('SMO WebSocket: Using WP option: ' . $option_url);
                }
            } catch (\Exception $e) {
                error_log('SMO WebSocket: WP option error: ' . $e->getMessage());
                $attempts[] = "WP option failed: " . $e->getMessage();
            }
        }

        // Final fallback to working public WebSocket
        if (!$config['url']) {
            // Public fallback removed to prevent connection errors to unreliable services
            // The client will handle null URL by disabling real-time features gracefully
            $config['url'] = null;
            error_log('SMO WebSocket: No valid WebSocket configuration found');
        }

        // Log all attempted URLs for debugging
        error_log('SMO WebSocket: Configuration attempts: ' . json_encode($attempts));
        error_log('SMO WebSocket: Final URL: ' . $config['url']);

        // Return success response with proper structure
        $config['success'] = true;
        $config['message'] = 'WebSocket configuration retrieved successfully';

        /*
        $config = array('url' => null);

        // Try to get WebSocket manager configuration first
        try {
            if (class_exists('\SMO_Social\WebSocket\WebSocketServerManager')) {
                $ws_manager = new \SMO_Social\WebSocket\WebSocketServerManager();
                $status = $ws_manager->get_status();
                
                if (!empty($status['config']['port'])) {
                    $protocol = !empty($status['config']['ssl']) ? 'wss' : 'ws';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $port = $status['config']['port'];
                    
                    // Remove default ports from host if present
                    $host = preg_replace('/:\d+$/', '', $host);
                    
                    $config['url'] = "{$protocol}://{$host}:{$port}";
                }
            }
        } catch (\Exception $e) {
            error_log('SMO Social: WebSocket config error: ' . $e->getMessage());
        }

        // Fallback to direct option check if WebSocket manager failed
        if (!$config['url']) {
            $ws_config = get_option('smo_websocket_config');
            if (!empty($ws_config) && !empty($ws_config['port'])) {
                $protocol = !empty($ws_config['ssl_key_file']) && !empty($ws_config['ssl_cert_file']) ? 'wss' : 'ws';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $port = $ws_config['port'];
                
                // Remove default ports from host if present
                $host = preg_replace('/:\d+$/', '', $host);

                $config['url'] = "{$protocol}://{$host}:{$port}";
            }
        }

        // Final fallback to a public WebSocket for testing if no local server is configured
        if (!$config['url']) {
            $config['url'] = 'wss://socketsbay.com/wss/v2/1/demo/';
        }
        */
        
        wp_send_json($config);
    }

    /**
     * Get WebSocket manager instance
     */
    private function get_websocket_manager() {
        // Try to get from global scope first
        if (isset($GLOBALS['smo_websocket_manager'])) {
            return $GLOBALS['smo_websocket_manager'];
        }

        // Create new instance and store in global scope
        try {
            $ws_manager = new \SMO_Social\WebSocket\WebSocketServerManager();
            $GLOBALS['smo_websocket_manager'] = $ws_manager;
            return $ws_manager;
        } catch (\Exception $e) {
            error_log('SMO Social: Failed to create WebSocket manager: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * AJAX handler for WebSocket token
     */
    public function ajax_get_websocket_token() {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_die(__('Unauthorized'));
        }

        $token = $this->get_websocket_token_for_current_user();

        wp_send_json(array(
            'token' => $token,
            'user_id' => get_current_user_id()
        ));
    }
}
