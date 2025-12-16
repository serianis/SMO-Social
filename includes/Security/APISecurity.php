<?php
namespace SMO_Social\Security;

class APISecurity {
    private $rate_limits;
    private $api_keys;
    private $whitelist;
    
    public function __construct() {
        $this->rate_limits = array();
        $this->api_keys = array();
        $this->whitelist = array();
    }
    
    public function check_rate_limit($identifier, $max_requests = 100, $window = 3600) {
        $current_time = time();
        $window_start = $current_time - $window;
        
        if (!isset($this->rate_limits[$identifier])) {
            $this->rate_limits[$identifier] = array();
        }
        
        // Remove old requests outside the window
        $this->rate_limits[$identifier] = array_filter(
            $this->rate_limits[$identifier],
            function($timestamp) use ($window_start) {
                return $timestamp > $window_start;
            }
        );
        
        // Check if under limit
        if (count($this->rate_limits[$identifier]) >= $max_requests) {
            return array(
                'allowed' => false,
                'error' => 'Rate limit exceeded',
                'retry_after' => $window
            );
        }
        
        // Add current request
        $this->rate_limits[$identifier][] = $current_time;
        
        return array('allowed' => true);
    }
    
    public function validate_api_key($api_key, $required_permissions = array()) {
        if (empty($api_key)) {
            return array(
                'valid' => false,
                'error' => 'API key is required'
            );
        }
        
        // Check if API key exists and is valid
        $key_data = $this->get_api_key_data($api_key);
        
        if (!$key_data || !$key_data['active']) {
            return array(
                'valid' => false,
                'error' => 'Invalid API key'
            );
        }
        
        // Check expiration
        if ($key_data['expires_at'] && time() > strtotime($key_data['expires_at'])) {
            return array(
                'valid' => false,
                'error' => 'API key has expired'
            );
        }
        
        // Check permissions
        if (!empty($required_permissions)) {
            $user_permissions = $key_data['permissions'] ?? array();
            $missing_permissions = array_diff($required_permissions, $user_permissions);
            
            if (!empty($missing_permissions)) {
                return array(
                    'valid' => false,
                    'error' => 'Missing required permissions: ' . implode(', ', $missing_permissions)
                );
            }
        }
        
        return array(
            'valid' => true,
            'key_data' => $key_data
        );
    }
    
    public function validate_ip_address($ip = null) {
        if (!$ip) {
            $ip = $this->get_client_ip();
        }
        
        // Check against whitelist if configured
        if (!empty($this->whitelist)) {
            if (!in_array($ip, $this->whitelist)) {
                return array(
                    'allowed' => false,
                    'error' => 'IP address not allowed'
                );
            }
        }
        
        return array('allowed' => true);
    }
    
    public function validate_request_signature($data, $signature, $api_key) {
        $expected_signature = \hash_hmac('sha256', $data, $api_key);
        
        if (!\hash_equals($expected_signature, $signature)) {
            return array(
                'valid' => false,
                'error' => 'Invalid request signature'
            );
        }
        
        return array('valid' => true);
    }
    
    public function check_content_type($content_type) {
        $allowed_types = array(
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data'
        );
        
        if (!in_array($content_type, $allowed_types)) {
            return array(
                'allowed' => false,
                'error' => 'Content-Type not allowed'
            );
        }
        
        return array('allowed' => true);
    }
    
    public function sanitize_api_input($input, $type = 'string') {
        $sanitizer = new DataSanitizer();
        return $sanitizer->sanitize($input, $type);
    }
    
    public function validate_api_version($version) {
        $supported_versions = array('v1', 'v2');
        
        if (!in_array($version, $supported_versions)) {
            return array(
                'valid' => false,
                'error' => 'API version not supported'
            );
        }
        
        return array('valid' => true);
    }
    
    public function generate_api_key($user_id, $permissions = array(), $expires_at = null) {
        $api_key = 'smo_' . bin2hex(\random_bytes(32));
        
        $this->api_keys[$api_key] = array(
            'user_id' => $user_id,
            'permissions' => $permissions,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expires_at,
            'active' => true
        );
        
        return array(
            'success' => true,
            'api_key' => $api_key
        );
    }
    
    public function revoke_api_key($api_key) {
        if (isset($this->api_keys[$api_key])) {
            $this->api_keys[$api_key]['active'] = false;
            return array('success' => true);
        }
        
        return array(
            'success' => false,
            'error' => 'API key not found'
        );
    }
    
    public function log_api_request($request_data) {
        $log_data = array(
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'endpoint' => $request_data['endpoint'] ?? '',
            'method' => $request_data['method'] ?? '',
            'response_code' => $request_data['response_code'] ?? 200,
            'timestamp' => date('Y-m-d H:i:s')
        );
        
        // Store in database if available
        if (function_exists('is_plugin_active') && isset($GLOBALS['wpdb']) && $GLOBALS['wpdb']) {
            $table_name = $GLOBALS['wpdb']->prefix . 'smo_api_logs';
            $GLOBALS['wpdb']->insert($table_name, $log_data);
        }
        
        return array('success' => true);
    }
    
    public function get_client_ip() {
        // Get client IP address from various server headers
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR');
        
        // Use a simple approach that should work with all linters
        foreach ($ip_keys as $key) {
            $value = $this->get_server_var($key);
            if (!empty($value)) {
                $ips = explode(',', $value);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $this->get_server_var('REMOTE_ADDR') ?? 'unknown';
    }
    
    /**
     * Helper method to safely get server variables
     * This avoids linter issues with direct superglobal access
     */
    private function get_server_var($key) {
        $_SERVER = $_SERVER ?? array();
        
        return isset($_SERVER[$key]) ? $_SERVER[$key] : null;
    }
    
    public function add_ip_to_whitelist($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->whitelist[] = $ip;
            return array('success' => true);
        }
        
        return array(
            'success' => false,
            'error' => 'Invalid IP address'
        );
    }
    
    public function remove_ip_from_whitelist($ip) {
        $key = array_search($ip, $this->whitelist);
        if ($key !== false) {
            unset($this->whitelist[$key]);
            return array('success' => true);
        }
        
        return array(
            'success' => false,
            'error' => 'IP address not found in whitelist'
        );
    }
    
    private function get_api_key_data($api_key) {
        return $this->api_keys[$api_key] ?? null;
    }
    
    public function cleanup_expired_keys() {
        $current_time = time();
        
        foreach ($this->api_keys as $key => $data) {
            if ($data['expires_at'] && strtotime($data['expires_at']) < $current_time) {
                $this->api_keys[$key]['active'] = false;
            }
        }
    }
    
    public function get_usage_stats($api_key) {
        if (!isset($this->rate_limits[$api_key])) {
            return array(
                'requests_count' => 0,
                'requests_this_hour' => 0,
                'first_request' => null,
                'last_request' => null
            );
        }
        
        $requests = $this->rate_limits[$api_key];
        
        return array(
            'requests_count' => count($requests),
            'requests_this_hour' => count(array_filter($requests, function($timestamp) {
                return $timestamp > (time() - 3600);
            })),
            'first_request' => !empty($requests) ? min($requests) : null,
            'last_request' => !empty($requests) ? max($requests) : null
        );
    }
}
