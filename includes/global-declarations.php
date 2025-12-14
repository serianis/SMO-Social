<?php
/**
 * Global declarations for Intelephense compatibility
 * 
 * This file provides global variable declarations to satisfy Intelephense
 * in non-WordPress development environments.
 */

// Superglobal variable type declarations for Intelephense
/**
 * @var array $_POST
 */
global $_POST;
if (!isset($_POST)) {
    $_POST = array();
}

/**
 * @var array $_SERVER
 */
global $_SERVER;
if (!isset($_SERVER)) {
    $_SERVER = array(
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'Mock Browser',
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'example.com'
    );
}

/**
 * @var array $_SESSION
 */
global $_SESSION;
if (!isset($_SESSION)) {
    $_SESSION = array();
}

/**
 * @var array $_GET
 */
if (!isset($_GET)) {
    $_GET = array();
}

/**
 * @var array $_COOKIE
 */
if (!isset($_COOKIE)) {
    $_COOKIE = array();
}

/**
 * @var array $_FILES
 */
if (!isset($_FILES)) {
    $_FILES = array();
}

/**
 * WordPress Database Class Global Declaration
 * 
 * Provides global wpdb class declaration for Intelephense compatibility
 */

// Global wpdb declaration for Intelephense
/**
 * @var \wpdb $wpdb WordPress database abstraction object
 * @global \wpdb $wpdb
 */
// Define missing constants
if (!defined('PHP_BINARY_READ')) {
    define('PHP_BINARY_READ', 1);
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

if (!defined('AUTH_KEY')) {
    define('AUTH_KEY', 'dummy-auth-key-for-development');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'wordpress');
}

// SMO Social Redis constants for EnhancedCacheManager
if (!defined('SMO_REDIS_HOST')) {
    define('SMO_REDIS_HOST', '127.0.0.1');
}

if (!defined('SMO_REDIS_PORT')) {
    define('SMO_REDIS_PORT', 6379);
}

if (!defined('SMO_REDIS_TIMEOUT')) {
    define('SMO_REDIS_TIMEOUT', 2.5);
}

// WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__FILE__)) . '/');
}

// WordPress upload directory constant
if (!defined('WP_UPLOAD_DIR')) {
    define('WP_UPLOAD_DIR', ABSPATH . 'wp-content/uploads');
}

// SMO Social plugin directory constant
if (!defined('SMO_SOCIAL_PLUGIN_DIR')) {
    define('SMO_SOCIAL_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
}

// Functions are now defined in wordpress-functions.php

// Add missing cryptographic functions for compatibility
if (!function_exists('random_int')) {
    /**
     * @param int $min
     * @param int $max
     * @return int
     */
    function random_int($min, $max) {
        if (function_exists('random_bytes')) {
            $range = $max - $min + 1;
            $maxRandom = 256 - ($range % 256);
            
            do {
                $randomBytes = random_bytes(1);
                $randomValue = ord($randomBytes);
            } while ($randomValue >= $maxRandom);
            
            return $min + ($randomValue % $range);
        }
        
        // Fallback if random_bytes is not available
        return rand($min, $max);
    }
}

if (!function_exists('hash_equals')) {
    /**
     * @param string $known_string
     * @param string $user_string
     * @return bool
     */
    function hash_equals($known_string, $user_string) {
        if (strlen($known_string) !== strlen($user_string)) {
            return false;
        }
        
        $result = 0;
        for ($i = 0; $i < strlen($known_string); $i++) {
            $result |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        
        return $result === 0;
    }
}

if (!function_exists('random_bytes')) {
    /**
     * @param int $length
     * @return string
     */
    function random_bytes($length) {
        if ($length <= 0) {
            throw new \InvalidArgumentException('Length must be positive');
        }
        
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= chr(random_int(0, 255));
        }
        return $random;
    }
}

