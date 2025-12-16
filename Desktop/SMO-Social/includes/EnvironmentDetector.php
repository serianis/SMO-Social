<?php
/**
 * Environment Detection Utility
 * 
 * Provides robust environment detection for both WordPress and standalone modes
 * with comprehensive error handling and fallback mechanisms.
 */

namespace SMO_Social\Utilities;

// Check if we're being loaded in a WordPress context without the main plugin
if (!defined('ABSPATH')) {
    // exit;
}

// Removed direct echo statement to prevent "headers already sent" errors during activation

// For standalone mode, we'll handle the version check differently
if (!defined('SMO_SOCIAL_VERSION') && defined('ABSPATH')) {
    // If we're in WordPress but the constant isn't defined, we're being loaded incorrectly
    // Only exit if we're definitely in WordPress environment and the plugin is already loaded
    if (!function_exists('smo_social_is_wordpress') && file_exists(ABSPATH . 'wp-config.php') && defined('SMO_SOCIAL_PLUGIN_DIR')) {
        // exit;
    }
}

/**
 * Environment Detector Class
 * 
 * Handles detection and validation of the running environment (WordPress vs standalone)
 * and provides utilities for environment-specific operations.
 */
class EnvironmentDetector {
    
    /**
     * @var bool|null WordPress environment flag
     */
    private static $is_wordpress = null;
    
    /**
     * @var array Environment information cache
     */
    private static $environment_info = null;
    
    /**
     * Detect if running in WordPress environment
     * 
     * @return bool True if running in WordPress, false for standalone
     */
    public static function isWordPress() {
        if (self::$is_wordpress === null) {
            self::$is_wordpress = self::detectWordPressEnvironment();
        }
        return self::$is_wordpress;
    }
    
    /**
     * Detect WordPress environment
     * 
     * @return bool
     */
    private static function detectWordPressEnvironment() {
        // Check if ABSPATH is defined and points to valid WordPress installation
        if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-config.php')) {
            return true;
        }
        
        // Check for WordPress-specific constants
        $wp_constants = array('WPINC', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR');
        $found_constants = 0;
        
        foreach ($wp_constants as $constant) {
            if (defined($constant)) {
                $found_constants++;
            }
        }
        
        // If multiple WordPress constants are defined, likely in WordPress
        if ($found_constants >= 2) {
            return true;
        }
        
        // Check for WordPress functions (excluding our compatibility layer)
        $wp_functions = array('get_bloginfo', 'wp_enqueue_script', 'add_action');
        $found_functions = 0;
        
        foreach ($wp_functions as $function) {
            if (function_exists($function) && !self::isCompatibilityFunction($function)) {
                $found_functions++;
            }
        }
        
        // If WordPress functions are available, likely in WordPress
        if ($found_functions >= 2) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a function is from our compatibility layer
     * 
     * @param string $function Function name to check
     * @return bool True if it's a compatibility function
     */
    private static function isCompatibilityFunction($function) {
        // Functions that are part of our compatibility layer
        $compatibility_functions = array(
            'wp_create_nonce', 'esc_html', 'esc_attr', 'wp_remote_request',
            'add_filter', 'do_action', 'register_activation_hook',
            'plugin_dir_path', 'sanitize_text_field', 'wp_die'
        );
        
        return in_array($function, $compatibility_functions);
    }
    
    /**
     * Get comprehensive environment information
     * 
     * @return array Environment information
     */
    public static function getEnvironmentInfo() {
        if (self::$environment_info === null) {
            self::$environment_info = array(
                'is_wordpress' => self::isWordPress(),
                'php_version' => PHP_VERSION,
                'sapi' => php_sapi_name(),
                'plugin_version' => defined('SMO_SOCIAL_VERSION') ? SMO_SOCIAL_VERSION : 'unknown',
                'plugin_dir' => defined('SMO_SOCIAL_PLUGIN_DIR') ? SMO_SOCIAL_PLUGIN_DIR : null,
                'plugin_url' => defined('SMO_SOCIAL_PLUGIN_URL') ? SMO_SOCIAL_PLUGIN_URL : null,
                'wordpress_version' => self::getWordPressVersion(),
                'available_functions' => self::getAvailableFunctions(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'error_reporting' => error_reporting(),
                'debug_mode' => defined('WP_DEBUG') ? WP_DEBUG : false,
                'standalone_mode' => !self::isWordPress()
            );
        }
        
        return self::$environment_info;
    }
    
    /**
     * Get WordPress version if available
     * 
     * @return string|null WordPress version or null if not in WordPress
     */
    private static function getWordPressVersion() {
        if (!self::isWordPress()) {
            return null;
        }
        
        if (function_exists('get_bloginfo')) {
            return get_bloginfo('version');
        }
        
        // Fallback version detection  
        if (defined('WP_CORE_VERSION')) {
            $version = constant('WP_CORE_VERSION');
            return $version;
        }
        
        return 'unknown';
    }
    
    /**
     * Get list of available WordPress functions
     * 
     * @return array List of available functions
     */
    private static function getAvailableFunctions() {
        $wp_functions = array(
            'core' => array('get_bloginfo', 'wp_enqueue_script', 'wp_enqueue_style', 'add_action', 'add_filter'),
            'security' => array('wp_verify_nonce', 'wp_create_nonce', 'esc_html', 'esc_attr', 'sanitize_text_field'),
            'database' => array('get_option', 'update_option', 'delete_option', 'get_user_meta', 'update_user_meta'),
            'url' => array('home_url', 'site_url', 'admin_url', 'wp_redirect'),
            'hooks' => array('do_action', 'apply_filters', 'has_action', 'did_action'),
            'files' => array('wp_upload_dir', 'wp_handle_upload'),
            'cache' => array('wp_cache_get', 'wp_cache_set', 'wp_cache_delete'),
            'meta' => array('get_post_meta', 'update_post_meta', 'get_user_meta', 'update_user_meta')
        );
        
        $available = array();
        foreach ($wp_functions as $category => $functions) {
            $available[$category] = array();
            foreach ($functions as $function) {
                if (function_exists($function)) {
                    $available[$category][] = $function;
                }
            }
        }
        
        return $available;
    }
    
    /**
     * Validate environment requirements
     * 
     * @return array Validation results with errors and warnings
     */
    public static function validateEnvironment() {
        $results = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'info' => array()
        );
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $results['errors'][] = sprintf(
                'SMO Social requires PHP 7.4 or higher. Current version: %s',
                PHP_VERSION
            );
            $results['valid'] = false;
        } else {
            $results['info'][] = sprintf('PHP version: %s (OK)', PHP_VERSION);
        }
        
        // Check required extensions
        $required_extensions = array('curl', 'json', 'mbstring');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $results['warnings'][] = sprintf(
                    'Recommended extension not found: %s',
                    $extension
                );
            }
        }
        
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = self::convertToBytes($memory_limit);
        if ($memory_limit_bytes < 134217728) { // 128MB
            $results['warnings'][] = sprintf(
                'Memory limit is low: %s (recommended: 128M or higher)',
                $memory_limit
            );
        }
        
        // WordPress-specific checks
        if (self::isWordPress()) {
            $results['info'][] = 'Running in WordPress environment';
            
            // Check WordPress version
            $wp_version = self::getWordPressVersion();
            if ($wp_version && version_compare($wp_version, '5.0', '<')) {
                $results['warnings'][] = sprintf(
                    'WordPress version %s is below recommended 5.0+',
                    $wp_version
                );
            }
        } else {
            $results['info'][] = 'Running in standalone mode';
            $results['warnings'][] = 'Limited WordPress integration features available';
        }
        
        // Check file permissions
        $plugin_dir = defined('SMO_SOCIAL_PLUGIN_DIR') ? SMO_SOCIAL_PLUGIN_DIR : dirname(__FILE__);
        if (!is_writable($plugin_dir)) {
            $results['warnings'][] = 'Plugin directory is not writable';
        }
        
        return $results;
    }
    
    /**
     * Convert memory limit string to bytes
     * 
     * @param string $val Memory limit string (e.g., "128M", "1G")
     * @return int Bytes
     */
    private static function convertToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
    
    /**
     * Get environment-specific URL
     * 
     * @param string $path Path to append
     * @param string $scheme URL scheme
     * @return string Formatted URL
     */
    public static function getUrl($path = '', $scheme = null) {
        if (self::isWordPress() && function_exists('home_url')) {
            return home_url($path, $scheme);
        }
        
        // Standalone mode fallback
        $base_url = defined('SMO_SOCIAL_PLUGIN_URL') ? SMO_SOCIAL_PLUGIN_URL : 'http://localhost/smo-social/';
        return rtrim($base_url, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Get environment-specific directory path
     * 
     * @param string $subdirectory Subdirectory to append
     * @return string Formatted directory path
     */
    public static function getPath($subdirectory = '') {
        $base_path = defined('SMO_SOCIAL_PLUGIN_DIR') ? SMO_SOCIAL_PLUGIN_DIR : dirname(__FILE__) . '/';
        return rtrim($base_path, '/') . '/' . ltrim($subdirectory, '/');
    }
    
    /**
     * Log environment-specific messages
     * 
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$level}] SMO Social: {$message}";
        
        if (self::isWordPress()) {
            if (function_exists('error_log')) {
                error_log($log_entry);
            }
        } else {
            error_log($log_entry);
        }
        
        // Also output to stdout in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG || !self::isWordPress()) {
            echo $log_entry . PHP_EOL;
        }
    }
    
    /**
     * Safe function execution with environment fallbacks
     * 
     * @param string $function WordPress function name
     * @param array $args Function arguments
     * @param mixed $default Default value if function fails
     * @return mixed Function result or default value
     */
    public static function safeExecute($function, $args = array(), $default = null) {
        if (self::isWordPress() && function_exists($function)) {
            try {
                return call_user_func_array($function, $args);
            } catch (\Exception $e) {
                self::log("Exception in {$function}: " . $e->getMessage(), 'warning');
                return $default;
            }
        }
        
        // Return default for standalone mode or missing function
        return $default;
    }
    
    /**
     * Check if a feature is available in current environment
     * 
     * @param string $feature Feature name to check
     * @return bool True if feature is available
     */
    public static function isFeatureAvailable($feature) {
        $feature_requirements = array(
            'admin_menu' => array('WordPress' => true, 'Standalone' => false),
            'cron_jobs' => array('WordPress' => true, 'Standalone' => false),
            'user_management' => array('WordPress' => true, 'Standalone' => false),
            'file_uploads' => array('WordPress' => true, 'Standalone' => true),
            'database' => array('WordPress' => true, 'Standalone' => false),
            'caching' => array('WordPress' => true, 'Standalone' => true),
            'rest_api' => array('WordPress' => true, 'Standalone' => false),
            'shortcodes' => array('WordPress' => true, 'Standalone' => false)
        );
        
        if (!isset($feature_requirements[$feature])) {
            return false;
        }
        
        $environment = self::isWordPress() ? 'WordPress' : 'Standalone';
        return $feature_requirements[$feature][$environment] ?? false;
    }
}
