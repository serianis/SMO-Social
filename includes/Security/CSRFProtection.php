<?php
namespace SMO_Social\Security;

class CSRFProtection {
    private $token_lifetime;
    private $token_storage;
    
    public function __construct($token_lifetime = 3600) {
        $this->token_lifetime = $token_lifetime;
        $this->token_storage = array();
    }
    
    public function generate_token($action = '') {
        $token = bin2hex(\random_bytes(32));
        $timestamp = time();
        $identifier = $action . '_' . $timestamp . '_' . $token;
        
        $this->token_storage[$identifier] = array(
            'token' => $token,
            'action' => $action,
            'timestamp' => $timestamp,
            'expires' => $timestamp + $this->token_lifetime
        );
        
        return $token;
    }
    
    public function validate_token($token, $action = '') {
        if (empty($token)) {
            return false;
        }
        
        // Find the token in storage
        foreach ($this->token_storage as $identifier => $data) {
            if ($data['token'] === $token && $data['action'] === $action) {
                // Check if token has expired
                if (time() > $data['expires']) {
                    unset($this->token_storage[$identifier]);
                    return false;
                }
                
                // Token is valid, remove it to prevent replay attacks
                unset($this->token_storage[$identifier]);
                return true;
            }
        }
        
        return false;
    }
    
    public function create_nonce($action = '', $user_id = '') {
        $nonce = wp_create_nonce($action . '_' . $user_id);
        return $nonce;
    }
    
    public function verify_nonce($nonce, $action = '') {
        return wp_verify_nonce($nonce, $action);
    }
    
    public function get_form_token_field($action = '') {
        $token = $this->generate_token($action);
        
        return '<input type="hidden" name="smo_csrf_token" value="' . esc_attr($token) . '">' .
               '<input type="hidden" name="smo_csrf_action" value="' . esc_attr($action) . '">';
    }
    
    public function check_request_token() {
        if (empty($_POST['smo_csrf_token']) || empty($_POST['smo_csrf_action'])) {
            return array(
                'valid' => false,
                'error' => 'Missing CSRF token'
            );
        }
        
        $token = $_POST['smo_csrf_token'];
        $action = $_POST['smo_csrf_action'];
        
        if (!$this->validate_token($token, $action)) {
            return array(
                'valid' => false,
                'error' => 'Invalid or expired CSRF token'
            );
        }
        
        return array('valid' => true);
    }
    
    public function check_ajax_token() {
        $headers = getallheaders();
        
        if (empty($headers['X-CSRF-Token'])) {
            return array(
                'valid' => false,
                'error' => 'Missing CSRF token header'
            );
        }
        
        $token = $headers['X-CSRF-Token'];
        $action = $headers['X-CSRF-Action'] ?? 'ajax_request';
        
        if (!$this->validate_token($token, $action)) {
            return array(
                'valid' => false,
                'error' => 'Invalid or expired CSRF token'
            );
        }
        
        return array('valid' => true);
    }
    
    public function get_ajax_headers() {
        $token = $this->generate_token('ajax_request');
        
        return array(
            'X-CSRF-Token' => $token,
            'X-CSRF-Action' => 'ajax_request'
        );
    }
    
    public function sanitize_request_data($data) {
        $sanitizer = new DataSanitizer();
        
        return $sanitizer->sanitize_array($data, 'string');
    }
    
    public function prevent_duplicate_submission($form_id, $timeout = 30) {
        $transient_key = 'smo_form_submission_' . $form_id;
        
        if (get_transient($transient_key)) {
            return array(
                'allowed' => false,
                'error' => 'Form already submitted recently'
            );
        }
        
        set_transient($transient_key, time(), $timeout);
        
        return array('allowed' => true);
    }
    
    public function cleanup_expired_tokens() {
        $current_time = time();
        
        foreach ($this->token_storage as $identifier => $data) {
            if ($current_time > $data['expires']) {
                unset($this->token_storage[$identifier]);
            }
        }
    }
    
    public function log_security_event($event_type, $details = array()) {
        // Log security events for monitoring
        $log_data = array(
            'event_type' => $event_type,
            'details' => $details,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        // Store in database if available
        if (function_exists('is_plugin_active')) {
            global $wpdb;
            if ($wpdb) {
                $table_name = $wpdb->prefix . 'smo_security_logs';
                $wpdb->insert($table_name, $log_data);
            }
        }
        
        return array('success' => true);
    }
}
