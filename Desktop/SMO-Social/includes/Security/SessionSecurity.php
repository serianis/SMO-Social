<?php
/**
 * SessionSecurity class for secure session management
 * 
 * @property array $_SERVER Superglobal server variables
 * @property array $_SESSION Superglobal session variables
 * @property string $random_bytes Function for generating random bytes
 */
namespace SMO_Social\Security;

/**
 * @global array $GLOBALS['_SERVER']
 * @global array $GLOBALS['_SESSION']
 */

// Include global declarations for Intelephense compatibility
require_once __DIR__ . '/../global-declarations.php';
require_once __DIR__ . '/../type-stubs.php';

// Note: random_bytes and random_int functions are now defined in global-declarations.php

// Global variable declarations are handled by global-declarations.php

class SessionSecurity {
    private $session_timeout;
    private $regenerate_interval;
    
    public function __construct($timeout = 1800, $regenerate_interval = 300) {
        $this->session_timeout = $timeout; // 30 minutes default
        $this->regenerate_interval = $regenerate_interval; // 5 minutes default
        
        $this->initialize_session_security();
    }
    
    private function initialize_session_security() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Set secure session configuration
        /** @var array $_SERVER */
        /** @var array $_SESSION */
        
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        ini_set('session.cookie_samesite', 'Strict');
        
        // Regenerate session ID on first visit
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['ip_address'] = $this->get_client_ip();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['created_at'] = time();
            $_SESSION['last_activity'] = time();
        } else {
            // Validate session
            $this->validate_session();
            $this->update_session_activity();
        }
    }
    
    public function validate_session() {
        // Ensure session is started before accessing $_SESSION
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        /** @var array $_SESSION */
        /** @var array $_SERVER */
        
        // Check IP address consistency
        $current_ip = $this->get_client_ip();
        if (!isset($_SESSION['ip_address']) || $_SESSION['ip_address'] !== $current_ip) {
            $this->destroy_session();
            return false;
        }
        
        // Check user agent consistency
        $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($_SESSION['user_agent'] !== $current_ua) {
            $this->destroy_session();
            return false;
        }
        
        // Check session timeout
        $current_time = time();
        if ($current_time - $_SESSION['last_activity'] > $this->session_timeout) {
            $this->destroy_session();
            return false;
        }
        
        return true;
    }
    
    public function update_session_activity() {
        // Ensure session is started before accessing $_SESSION
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        
        /** @var array $_SESSION */
        
        $current_time = time();
        
        // Update last activity
        $_SESSION['last_activity'] = $current_time;
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration']) || 
            ($current_time - $_SESSION['last_regeneration']) > $this->regenerate_interval) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = $current_time;
        }
    }
    
    public function set_session_data($key, $value) {
        if (!$this->validate_session()) {
            return false;
        }
        
        /** @var array $_SESSION */
        
        $_SESSION[$key] = $value;
        $this->update_session_activity();
        
        return true;
    }
    
    public function get_session_data($key, $default = null) {
        if (!$this->validate_session()) {
            return $default;
        }
        
        /** @var array $_SESSION */
        
        return $_SESSION[$key] ?? $default;
    }
    
    public function destroy_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            /** @var array $_SESSION */
            $_SESSION = array();
            
            // Delete session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            session_destroy();
        }
    }
    
    public function regenerate_session_id() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            /** @var array $_SESSION */
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    public function check_concurrent_sessions($user_id) {
        // Ensure session is started before accessing $_SESSION
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }
        
        // Ensure globals are available for Intelephense
        global $_SESSION, $_SERVER;
        
        // Store session info to detect concurrent sessions
        $session_key = 'user_sessions_' . $user_id;
        
        if (!isset($_SESSION[$session_key])) {
            $_SESSION[$session_key] = array();
        }
        
        $current_session = session_id();
        $user_sessions = $_SESSION[$session_key];
        
        // Remove expired sessions
        $current_time = time();
        foreach ($user_sessions as $session_id => $data) {
            if ($current_time - $data['timestamp'] > $this->session_timeout) {
                unset($user_sessions[$session_id]);
            }
        }
        
        // Add current session
        $user_sessions[$current_session] = array(
            'timestamp' => $current_time,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        // Update session data
        // Removed logic that attempts to "destroy" old sessions, as this requires external session storage management.
        $_SESSION[$session_key] = $user_sessions;
        
        return count($user_sessions);
    }
    
    public function create_csrf_token($action = '') {
        // Ensure session is started before accessing $_SESSION
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        /** @var array $_SESSION */
        
        $token = bin2hex(random_bytes(32));
        $token_key = 'csrf_token_' . $action;
        
        $_SESSION[$token_key] = array(
            'token' => $token,
            'timestamp' => time(),
            'ip_address' => $this->get_client_ip()
        );
        
        return $token;
    }
    
    public function validate_csrf_token($token, $action = '') {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        /** @var array $_SESSION */
        $token_key = 'csrf_token_' . $action;
        
        if (!isset($_SESSION[$token_key])) {
            return false;
        }
        
        /** @var array $stored_token */
        /** @var array $_SESSION */
        $stored_token = $_SESSION[$token_key];
        
        // Check if token matches
        if (!\hash_equals($stored_token['token'], $token)) {
            return false;
        }
        
        // Check if token is not too old (1 hour limit)
        if (time() - $stored_token['timestamp'] > 3600) {
            unset($_SESSION[$token_key]);
            return false;
        }
        
        // Check if IP address matches
        if ($stored_token['ip_address'] !== $this->get_client_ip()) {
            unset($_SESSION[$token_key]);
            return false;
        }
        
        // Remove token after use (for single-use tokens)
        unset($_SESSION[$token_key]);
        
        return true;
    }
    
    public function log_session_event($event_type, $details = array()) {
        // Ensure session is started before accessing session functions
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return array('success' => false, 'error' => 'Session not active');
        }
        
        // Ensure $_SERVER is available for Intelephense
        global $_SERVER;
        
        $log_data = array(
            'event_type' => $event_type,
            'session_id' => session_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'details' => $details
        );
        
        // Store in database if available
        if (function_exists('is_plugin_active')) {
            global $wpdb; // Ensure $wpdb is global here
            if ($wpdb) {
                $table_name = $wpdb->prefix . 'smo_session_logs';
                $wpdb->insert($table_name, $log_data);
            }
        }
        
        return array('success' => true);
    }
    
    private function get_client_ip() {
        // Ensure $_SERVER is available for Intelephense
        global $_SERVER;
        
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    public function get_session_info() {
        if (session_status() !== PHP_SESSION_ACTIVE || !$this->validate_session()) {
            return array('active' => false);
        }
        
        /** @var array $_SESSION */
        /** @var array $_SERVER */
        
        return array(
            'active' => true,
            'session_id' => session_id(),
            'ip_address' => $this->get_session_data('ip_address'),
            'user_agent' => $this->get_session_data('user_agent'),
            'created_at' => $this->get_session_data('created_at'),
            'last_activity' => $this->get_session_data('last_activity'),
            'lifetime' => $this->session_timeout
        );
    }
    
    public function cleanup_expired_sessions() {
        // This would typically be done via session garbage collection
        // For custom session storage, you'd implement cleanup logic here
        return array('success' => true);
    }
}
