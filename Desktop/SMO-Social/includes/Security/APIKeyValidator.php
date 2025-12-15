<?php
namespace SMO_Social\Security;

class APIKeyValidator {
    private $api_keys;
    private $rate_limits;
    
    public function __construct() {
        $this->api_keys = array();
        $this->rate_limits = array();
    }
    
    public function validate_api_key($key, $platform = '') {
        if (empty($key)) {
            return array(
                'valid' => false,
                'error' => 'API key is required'
            );
        }
        
        // Check if key exists and is valid
        if (!$this->is_valid_key($key)) {
            return array(
                'valid' => false,
                'error' => 'Invalid API key'
            );
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit($key, $platform)) {
            return array(
                'valid' => false,
                'error' => 'Rate limit exceeded for this API key'
            );
        }
        
        return array(
            'valid' => true,
            'key_info' => $this->get_key_info($key)
        );
    }
    
    public function generate_api_key($user_id, $permissions = array(), $expires_at = null) {
        $api_key = 'smo_' . bin2hex(\random_bytes(32));
        
        $key_data = array(
            'key' => $api_key,
            'user_id' => $user_id,
            'permissions' => $permissions,
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'is_active' => true
        );
        
        $this->api_keys[$api_key] = $key_data;
        
        return array(
            'success' => true,
            'api_key' => $api_key,
            'key_data' => $key_data
        );
    }
    
    public function revoke_api_key($key) {
        if (isset($this->api_keys[$key])) {
            $this->api_keys[$key]['is_active'] = false;
            return array('success' => true);
        }
        
        return array(
            'success' => false,
            'error' => 'API key not found'
        );
    }
    
    public function check_permissions($key, $required_permission) {
        $validation = $this->validate_api_key($key);
        
        if (!$validation['valid']) {
            return false;
        }
        
        $key_info = $validation['key_info'];
        $permissions = $key_info['permissions'] ?? array();
        
        return in_array($required_permission, $permissions);
    }
    
    private function is_valid_key($key) {
        if (!isset($this->api_keys[$key])) {
            return false;
        }
        
        $key_data = $this->api_keys[$key];
        
        // Check if active
        if (!$key_data['is_active']) {
            return false;
        }
        
        // Check expiration
        if ($key_data['expires_at'] && strtotime($key_data['expires_at']) < time()) {
            return false;
        }
        
        return true;
    }
    
    private function check_rate_limit($key, $platform) {
        $current_time = time();
        $window_size = 3600; // 1 hour window
        $max_requests = 1000; // Max requests per hour
        
        $rate_key = $key . '_' . $platform;
        
        if (!isset($this->rate_limits[$rate_key])) {
            $this->rate_limits[$rate_key] = array(
                'requests' => array(),
                'window_start' => $current_time
            );
        }
        
        $rate_data = $this->rate_limits[$rate_key];
        
        // Remove old requests outside the window
        $rate_data['requests'] = array_filter($rate_data['requests'], function($timestamp) use ($current_time, $window_size) {
            return ($current_time - $timestamp) < $window_size;
        });
        
        // Check if under limit
        if (count($rate_data['requests']) >= $max_requests) {
            return false;
        }
        
        // Add current request
        $rate_data['requests'][] = $current_time;
        $rate_data['window_start'] = $current_time;
        $this->rate_limits[$rate_key] = $rate_data;
        
        return true;
    }
    
    private function get_key_info($key) {
        return $this->api_keys[$key] ?? array();
    }
}
