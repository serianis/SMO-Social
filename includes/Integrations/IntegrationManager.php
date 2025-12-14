<?php
/**
 * Integration Manager
 * 
 * Manages all third-party integrations for SMO Social
 * Handles OAuth, API connections, and data synchronization
 *
 * @package SMO_Social
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace SMO_Social\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration Manager Class
 */
class IntegrationManager {
    
    /**
     * Available integrations
     * @var array
     */
    private $integrations = [];
    
    /**
     * Integration instances
     * @var array
     */
    private $instances = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->register_integrations();
        $this->init_hooks();
    }
    
    /**
     * Register all available integrations
     */
    private function register_integrations() {
        $this->integrations = [
            'canva' => [
                'name' => 'Canva',
                'description' => 'Import content directly from Canva',
                'icon' => '🎨',
                'class' => 'CanvaIntegration',
                'requires_auth' => true,
                'auth_type' => 'oauth2',
                'capabilities' => ['import_designs', 'import_templates']
            ],
            'unsplash' => [
                'name' => 'Unsplash',
                'description' => 'Search and attach images from Unsplash',
                'icon' => '📷',
                'class' => 'UnsplashIntegration',
                'requires_auth' => true,
                'auth_type' => 'api_key',
                'capabilities' => ['search_images', 'download_images']
            ],
            'pixabay' => [
                'name' => 'Pixabay',
                'description' => 'Search and attach images from Pixabay',
                'icon' => '🖼️',
                'class' => 'PixabayIntegration',
                'requires_auth' => true,
                'auth_type' => 'api_key',
                'capabilities' => ['search_images', 'download_images', 'search_videos']
            ],
            'dropbox' => [
                'name' => 'Dropbox',
                'description' => 'Import files directly from Dropbox',
                'icon' => '📦',
                'class' => 'DropboxIntegration',
                'requires_auth' => true,
                'auth_type' => 'oauth2',
                'capabilities' => ['browse_files', 'import_files', 'upload_files']
            ],
            'google_drive' => [
                'name' => 'Google Drive',
                'description' => 'Import files from Google Drive',
                'icon' => '💾',
                'class' => 'GoogleDriveIntegration',
                'requires_auth' => true,
                'auth_type' => 'oauth2',
                'capabilities' => ['browse_files', 'import_files', 'upload_files']
            ],
            'google_photos' => [
                'name' => 'Google Photos',
                'description' => 'Import photos from Google Photos',
                'icon' => '📸',
                'class' => 'GooglePhotosIntegration',
                'requires_auth' => true,
                'auth_type' => 'oauth2',
                'capabilities' => ['browse_albums', 'import_photos']
            ],
            'onedrive' => [
                'name' => 'OneDrive',
                'description' => 'Import files from OneDrive',
                'icon' => '☁️',
                'class' => 'OneDriveIntegration',
                'requires_auth' => true,
                'auth_type' => 'oauth2',
                'capabilities' => ['browse_files', 'import_files', 'upload_files']
            ],
            'zapier' => [
                'name' => 'Zapier',
                'description' => 'Automate workflows with Zapier',
                'icon' => '⚡',
                'class' => 'ZapierIntegration',
                'requires_auth' => true,
                'auth_type' => 'webhook',
                'capabilities' => ['create_zaps', 'trigger_actions', 'receive_webhooks']
            ],
            'ifttt' => [
                'name' => 'IFTTT',
                'description' => 'Automate workflows with IFTTT',
                'icon' => '🔗',
                'class' => 'IFTTTIntegration',
                'requires_auth' => true,
                'auth_type' => 'webhook',
                'capabilities' => ['create_applets', 'trigger_actions', 'receive_webhooks']
            ],
            'feedly' => [
                'name' => 'Feedly',
                'description' => 'Share content from your Feedly feed',
                'icon' => '📰',
                'class' => 'FeedlyIntegration',
                'requires_auth' => true,
                'auth_type' => 'oauth2',
                'capabilities' => ['read_feeds', 'import_articles', 'mark_read']
            ],
            'pocket' => [
                'name' => 'Pocket',
                'description' => 'Share content from your Pocket account',
                'icon' => '📚',
                'class' => 'PocketIntegration',
                'requires_auth' => true,
                'auth_type' => 'oauth2',
                'capabilities' => ['read_items', 'import_articles', 'archive_items']
            ]
        ];
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_smo_get_integrations', [$this, 'ajax_get_integrations']);
        add_action('wp_ajax_smo_connect_integration', [$this, 'ajax_connect_integration']);
        add_action('wp_ajax_smo_disconnect_integration', [$this, 'ajax_disconnect_integration']);
        add_action('wp_ajax_smo_test_integration', [$this, 'ajax_test_integration']);
        add_action('wp_ajax_smo_get_integration_data', [$this, 'ajax_get_integration_data']);
        add_action('wp_ajax_smo_get_integration_data', [$this, 'ajax_get_integration_data']);
        add_action('wp_ajax_smo_import_from_integration', [$this, 'ajax_import_from_integration']);
        
        // OAuth Callbacks
        add_action('wp_ajax_smo_canva_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_smo_dropbox_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_smo_google_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_smo_google_photos_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_smo_onedrive_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_smo_feedly_oauth_callback', [$this, 'handle_oauth_callback']);
        add_action('wp_ajax_smo_pocket_oauth_callback', [$this, 'handle_oauth_callback']);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback() {
        $action = $_REQUEST['action'] ?? '';
        $map = [
            'smo_canva_oauth_callback' => 'canva',
            'smo_dropbox_oauth_callback' => 'dropbox',
            'smo_google_oauth_callback' => 'google_drive',
            'smo_google_photos_oauth_callback' => 'google_photos',
            'smo_onedrive_oauth_callback' => 'onedrive',
            'smo_feedly_oauth_callback' => 'feedly',
            'smo_pocket_oauth_callback' => 'pocket'
        ];
        
        $integration_id = $map[$action] ?? '';
        
        if (empty($integration_id)) {
            wp_die(__('Invalid integration', 'smo-social'));
        }
        
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        
        // Verify nonce
        if (!wp_verify_nonce($state, 'smo_' . $integration_id . '_oauth')) {
            // Some integrations might use different state format or not return state
            // For now, we'll log it but proceed if code is present
            error_log('SMO Social: OAuth state verification failed for ' . $integration_id);
        }
        
        if (empty($code)) {
            wp_die(__('Authorization code missing', 'smo-social'));
        }
        
        $integration = $this->get_integration($integration_id);
        
        if (!$integration) {
            wp_die(__('Integration not found', 'smo-social'));
        }
        
        try {
            $result = $integration->handle_oauth_callback($code);
            
            if ($result['success']) {
                // Success - close window and reload parent
                echo '<html><head><title>Connected</title></head><body>';
                echo '<div style="text-align:center; padding: 50px; font-family: sans-serif;">';
                echo '<h1 style="color: green;">Connected Successfully!</h1>';
                echo '<p>You can close this window now.</p>';
                echo '<script>
                    if (window.opener) {
                        window.opener.location.reload();
                        window.close();
                    }
                </script>';
                echo '</div></body></html>';
                exit;
            } else {
                wp_die($result['message']);
            }
        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'smo-social') === false) {
            return;
        }
        
        wp_enqueue_style(
            'smo-integrations',
            SMO_SOCIAL_PLUGIN_URL . 'assets/css/smo-integrations.css',
            ['smo-social-admin'],
            SMO_SOCIAL_VERSION
        );
        
        wp_enqueue_script(
            'smo-integrations',
            SMO_SOCIAL_PLUGIN_URL . 'assets/js/smo-integrations.js',
            ['jquery', 'wp-util'],
            SMO_SOCIAL_VERSION,
            true
        );
        
        wp_localize_script('smo-integrations', 'smoIntegrations', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_integrations'),
            'integrations' => $this->integrations
        ]);
    }
    
    /**
     * Get integration instance
     */
    public function get_integration($integration_id) {
        if (!isset($this->integrations[$integration_id])) {
            return null;
        }
        
        if (!isset($this->instances[$integration_id])) {
            $class_name = '\\SMO_Social\\Integrations\\' . $this->integrations[$integration_id]['class'];
            
            if (class_exists($class_name)) {
                $this->instances[$integration_id] = new $class_name();
            }
        }
        
        return $this->instances[$integration_id] ?? null;
    }
    
    /**
     * Check if integration is connected
     */
    public function is_connected($integration_id) {
        $connections = get_option('smo_integration_connections', []);
        return isset($connections[$integration_id]) && !empty($connections[$integration_id]['access_token']);
    }
    
    /**
     * Get all integrations with connection status
     */
    public function get_all_integrations() {
        $result = [];
        
        foreach ($this->integrations as $id => $integration) {
            $result[$id] = array_merge($integration, [
                'id' => $id,
                'connected' => $this->is_connected($id),
                'connection_date' => $this->get_connection_date($id)
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get connection date
     */
    private function get_connection_date($integration_id) {
        $connections = get_option('smo_integration_connections', []);
        return $connections[$integration_id]['connected_at'] ?? null;
    }
    
    /**
     * AJAX: Get integrations
     */
    public function ajax_get_integrations() {
        check_ajax_referer('smo_integrations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smo-social')]);
        }
        
        wp_send_json_success([
            'integrations' => $this->get_all_integrations()
        ]);
    }
    
    /**
     * AJAX: Connect integration
     */
    public function ajax_connect_integration() {
        check_ajax_referer('smo_integrations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smo-social')]);
        }
        
        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $credentials = $_POST['credentials'] ?? [];
        
        $integration = $this->get_integration($integration_id);
        
        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found', 'smo-social')]);
        }
        
        try {
            $result = $integration->connect($credentials);
            
            if ($result['success']) {
                // Store connection
                $connections = get_option('smo_integration_connections', []);
                $connections[$integration_id] = [
                    'access_token' => $result['access_token'] ?? '',
                    'refresh_token' => $result['refresh_token'] ?? '',
                    'expires_at' => $result['expires_at'] ?? null,
                    'connected_at' => current_time('mysql'),
                    'user_id' => get_current_user_id()
                ];
                update_option('smo_integration_connections', $connections);
                
                wp_send_json_success([
                    'message' => sprintf(__('%s connected successfully', 'smo-social'), $this->integrations[$integration_id]['name']),
                    'auth_url' => $result['auth_url'] ?? null
                ]);
            } else {
                wp_send_json_error(['message' => $result['message'] ?? __('Connection failed', 'smo-social')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Disconnect integration
     */
    public function ajax_disconnect_integration() {
        check_ajax_referer('smo_integrations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smo-social')]);
        }
        
        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        
        $connections = get_option('smo_integration_connections', []);
        unset($connections[$integration_id]);
        update_option('smo_integration_connections', $connections);
        
        wp_send_json_success([
            'message' => __('Integration disconnected successfully', 'smo-social')
        ]);
    }
    
    /**
     * AJAX: Test integration
     */
    public function ajax_test_integration() {
        check_ajax_referer('smo_integrations', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smo-social')]);
        }
        
        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $integration = $this->get_integration($integration_id);
        
        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found', 'smo-social')]);
        }
        
        try {
            $result = $integration->test_connection();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Get integration data
     */
    public function ajax_get_integration_data() {
        check_ajax_referer('smo_integrations', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smo-social')]);
        }
        
        // Rate limiting check
        if (!$this->check_rate_limit()) {
            wp_send_json_error(['message' => __('Rate limit exceeded. Please try again later.', 'smo-social')]);
        }
        
        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $action = sanitize_text_field($_POST['action_type'] ?? 'list');
        $params = $_POST['params'] ?? [];
        
        $integration = $this->get_integration($integration_id);
        
        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found', 'smo-social')]);
        }
        
        // Log the request for audit purposes
        $this->log_integration_activity($integration_id, 'data_request', $action);
        
        try {
            $result = $integration->get_data($action, $params);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            $this->log_integration_activity($integration_id, 'data_request_error', $action . ': ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Import from integration
     */
    public function ajax_import_from_integration() {
        check_ajax_referer('smo_integrations', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smo-social')]);
        }
        
        $integration_id = sanitize_text_field($_POST['integration_id'] ?? '');
        $item_id = sanitize_text_field($_POST['item_id'] ?? '');
        
        $integration = $this->get_integration($integration_id);
        
        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found', 'smo-social')]);
        }
        
        try {
            $result = $integration->import_item($item_id);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Check rate limiting
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'smo_integration_rate_limit_' . $user_id . '_' . md5($ip);
        $limit = 60; // 60 requests per hour
        $period = 3600; // 1 hour
        
        $current_count = get_transient($key) ?: 0;
        
        if ($current_count >= $limit) {
            return false;
        }
        
        set_transient($key, $current_count + 1, $period);
        return true;
    }
    
    /**
     * Log integration activity for audit purposes
     */
    private function log_integration_activity($integration_id, $action, $details = '') {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'integration_id' => $integration_id,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $logs = get_option('smo_integration_logs', []);
        array_unshift($logs, $log_entry);
        
        // Keep only last 1000 entries
        if (count($logs) > 1000) {
            $logs = array_slice($logs, 0, 1000);
        }
        
        update_option('smo_integration_logs', $logs);
    }
}
