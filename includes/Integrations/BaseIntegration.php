<?php
/**
 * Base Integration Class
 * 
 * Abstract base class for all third-party integrations
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
 * Base Integration Abstract Class
 */
abstract class BaseIntegration {
    
    /**
     * Integration ID
     * @var string
     */
    protected $integration_id;
    
    /**
     * Integration name
     * @var string
     */
    protected $name;
    
    /**
     * API base URL
     * @var string
     */
    protected $api_base_url;
    
    /**
     * Access token
     * @var string
     */
    protected $access_token;
    
    /**
     * Refresh token
     * @var string
     */
    protected $refresh_token;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load stored credentials
     */
    protected function load_credentials() {
        $connections = get_option('smo_integration_connections', []);
        
        if (isset($connections[$this->integration_id])) {
            $this->access_token = $connections[$this->integration_id]['access_token'] ?? '';
            $this->refresh_token = $connections[$this->integration_id]['refresh_token'] ?? '';
        }
    }
    
    /**
     * Connect to the integration
     * 
     * @param array $credentials
     * @return array
     */
    abstract public function connect($credentials);
    
    /**
     * Test the connection
     * 
     * @return array
     */
    abstract public function test_connection();
    
    /**
     * Get data from the integration
     * 
     * @param string $action
     * @param array $params
     * @return array
     */
    abstract public function get_data($action, $params = []);
    
    /**
     * Import an item from the integration
     * 
     * @param string $item_id
     * @return array
     */
    abstract public function import_item($item_id);
    
    /**
     * Make API request
     * 
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @param array $headers
     * @return array
     */
    protected function make_request($endpoint, $method = 'GET', $data = [], $headers = []) {
        $url = $this->api_base_url . $endpoint;
        
        $default_headers = [
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json'
        ];
        
        $headers = array_merge($default_headers, $headers);
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        ];
        
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code >= 400) {
            throw new \Exception($data['error'] ?? 'API request failed');
        }
        
        return $data;
    }
    
    /**
     * Refresh access token
     * 
     * @return bool
     */
    protected function refresh_token() {
        // Override in child classes if needed
        return false;
    }
    
    /**
     * Store credentials
     * 
     * @param array $credentials
     */
    protected function store_credentials($credentials) {
        $connections = get_option('smo_integration_connections', []);
        
        $connections[$this->integration_id] = array_merge(
            $connections[$this->integration_id] ?? [],
            $credentials,
            ['updated_at' => current_time('mysql')]
        );
        
        update_option('smo_integration_connections', $connections);
        
        $this->load_credentials();
    }
    
    /**
     * Download file from URL
     * 
     * @param string $url
     * @param string $filename
     * @return int|\WP_Error Attachment ID or error
     */
    protected function download_to_media_library($url, $filename = '') {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = \download_url($url);
        
        if (\is_wp_error($tmp)) {
            return $tmp;
        }
        
        if (empty($filename)) {
            $filename = basename($url);
        }
        
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp
        ];
        
        $id = \media_handle_sideload($file_array, 0);
        
        if (\is_wp_error($id)) {
            @unlink($tmp);
            return $id;
        }
        
        return $id;
    }
    /**
     * Handle OAuth callback
     * 
     * @param string $code
     * @return array
     */
    public function handle_oauth_callback($code) {
        return ['success' => false, 'message' => 'Not implemented'];
    }
}
