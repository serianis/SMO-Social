<?php
/**
 * WordPress compatibility and function availability helper
 * 
 * Consolidates duplicated WordPress function checks and compatibility layers
 * Provides consistent access to WordPress functions with fallback mechanisms
 * 
 * @package SMO_Social
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WordPressCompatibilityHelper {
    
    /**
     * WordPress function cache
     * @var array
     */
    private static $function_cache = [];
    
    /**
     * Available WordPress functions cache
     * @var array
     */
    private static $available_functions = [];
    
    /**
     * WordPress version cache
     * @var string|null
     */
    private static $wp_version = null;
    
    /**
     * Check if we're running in WordPress environment
     * 
     * @return bool
     */
    public static function isWordPressEnvironment() {
        return defined('ABSPATH') && function_exists('get_bloginfo');
    }
    
    /**
     * Get WordPress version
     * 
     * @return string
     */
    public static function getWordPressVersion() {
        if (self::$wp_version === null) {
            if (self::isWordPressEnvironment() && function_exists('get_bloginfo')) {
                self::$wp_version = get_bloginfo('version');
            } else {
                self::$wp_version = '0.0.0'; // Unknown version
            }
        }
        
        return self::$wp_version;
    }
    
    /**
     * Check if WordPress version meets minimum requirement
     * 
     * @param string $min_version Minimum version required
     * @return bool
     */
    public static function isWordPressVersionAtLeast($min_version) {
        return version_compare(self::getWordPressVersion(), $min_version, '>=');
    }
    
    /**
     * Check if a WordPress function is available
     * 
     * @param string $function_name Function name
     * @return bool
     */
    public static function functionExists($function_name) {
        if (isset(self::$function_cache[$function_name])) {
            return self::$function_cache[$function_name];
        }
        
        $exists = function_exists($function_name);
        self::$function_cache[$function_name] = $exists;
        
        if ($exists) {
            self::$available_functions[] = $function_name;
        }
        
        return $exists;
    }
    
    /**
     * Get list of available WordPress functions
     * 
     * @param string $category Function category filter
     * @return array
     */
    public static function getAvailableFunctions($category = null) {
        if (empty(self::$available_functions)) {
            // Populate common WordPress functions
            $common_functions = [
                'core' => ['get_bloginfo', 'wp_enqueue_script', 'wp_enqueue_style', 'add_action', 'add_filter'],
                'nonce' => ['wp_create_nonce', 'wp_verify_nonce', 'check_ajax_referer'],
                'security' => ['current_user_can', 'wp_die', 'esc_html', 'esc_attr', 'sanitize_text_field'],
                'ajax' => ['wp_send_json_success', 'wp_send_json_error', 'wp_send_json'],
                'user' => ['get_current_user_id', 'get_userdata', 'wp_get_current_user'],
                'options' => ['get_option', 'update_option', 'add_option', 'delete_option'],
                'meta' => ['get_post_meta', 'update_post_meta', 'add_post_meta', 'delete_post_meta'],
                'url' => ['home_url', 'admin_url', 'wp_redirect', 'wp_safe_redirect']
            ];
            
            foreach ($common_functions as $cat => $functions) {
                foreach ($functions as $function) {
                    if (self::functionExists($function)) {
                        if (!isset(self::$available_functions[$cat])) {
                            self::$available_functions[$cat] = [];
                        }
                        self::$available_functions[$cat][] = $function;
                    }
                }
            }
        }
        
        if ($category && isset(self::$available_functions[$category])) {
            return self::$available_functions[$category];
        }
        
        return self::$available_functions;
    }
    
    /**
     * Safely execute a WordPress function with fallback
     * 
     * @param string $function Function name
     * @param array $args Function arguments
     * @param mixed $default Default value if function doesn't exist
     * @param bool $log_missing Whether to log missing functions
     * @return mixed
     */
    public static function safeExecute($function, $args = [], $default = null, $log_missing = false) {
        if (!self::functionExists($function)) {
            if ($log_missing) {
                ErrorHandler::log(ErrorHandler::LEVEL_DEBUG, "WordPress function not available: {$function}");
            }
            return $default;
        }
        
        try {
            return call_user_func_array($function, $args);
        } catch (\Exception $e) {
            ErrorHandler::logException($e, ErrorHandler::CATEGORY_SYSTEM, [
                'function' => $function,
                'args' => $args
            ]);
            return $default;
        }
    }
    
    /**
     * Get option with fallback
     * 
     * @param string $option_name Option name
     * @param mixed $default Default value
     * @return mixed
     */
    public static function getOption($option_name, $default = false) {
        if (self::functionExists('get_option')) {
            return get_option($option_name, $default);
        }
        
        // Fallback for non-WordPress environments
        return $default;
    }
    
    /**
     * Update option with fallback
     * 
     * @param string $option_name Option name
     * @param mixed $value Option value
     * @param string|bool $autoload Autoload option
     * @return bool
     */
    public static function updateOption($option_name, $value, $autoload = null) {
        if (self::functionExists('update_option')) {
            if ($autoload !== null) {
                return update_option($option_name, $value, $autoload);
            }
            return update_option($option_name, $value);
        }
        
        // Fallback for non-WordPress environments
        ErrorHandler::log(ErrorHandler::LEVEL_WARNING, 'Cannot update option outside WordPress environment', [
            'option' => $option_name,
            'value' => $value
        ]);
        
        return false;
    }
    
    /**
     * Check user capability with fallback
     * 
     * @param string $capability Capability to check
     * @param int $user_id User ID (optional)
     * @return bool
     */
    public static function userCan($capability, $user_id = null) {
        if (self::functionExists('current_user_can')) {
            if ($user_id !== null) {
                // Switch to user context temporarily
                $current_user_id = get_current_user_id();
                wp_set_current_user($user_id);
                $result = current_user_can($capability);
                wp_set_current_user($current_user_id);
                return $result;
            }
            return current_user_can($capability);
        }
        
        // Fallback: assume admin can do everything, others limited
        if ($capability === 'manage_options') {
            return self::isCurrentUserAdmin();
        }
        
        return $user_id === get_current_user_id(); // Users can only modify their own stuff
    }
    
    /**
     * Check if current user is administrator
     * 
     * @return bool
     */
    public static function isCurrentUserAdmin() {
        if (self::functionExists('current_user_can')) {
            return current_user_can('manage_options');
        }
        
        // Fallback for non-WordPress environments
        $current_user_id = self::safeExecute('get_current_user_id', [], 0);
        return in_array($current_user_id, [1]); // Assume user 1 is admin
    }
    
    /**
     * Create nonce with fallback
     * 
     * @param string $action Action string
     * @return string
     */
    public static function createNonce($action = 'smo_social_nonce') {
        if (self::functionExists('wp_create_nonce')) {
            return wp_create_nonce($action);
        }
        
        // Fallback nonce generation
        return hash('sha256', $action . wp_salt('nonce') . time());
    }
    
    /**
     * Verify nonce with fallback
     * 
     * @param string $nonce Nonce to verify
     * @param string $action Expected action
     * @return bool
     */
    public static function verifyNonce($nonce, $action = 'smo_social_nonce') {
        if (self::functionExists('wp_verify_nonce')) {
            return wp_verify_nonce($nonce, $action) !== false;
        }
        
        // Basic validation (in real implementation, you'd verify against stored nonces)
        return !empty($nonce) && strlen($nonce) > 10;
    }
    
    /**
     * Sanitize text with fallback
     * 
     * @param string $text Text to sanitize
     * @return string
     */
    public static function sanitizeText($text) {
        if (self::functionExists('sanitize_text_field')) {
            return sanitize_text_field($text);
        }
        
        // Fallback sanitization
        return strip_tags(trim($text));
    }
    
    /**
     * Escape HTML with fallback
     * 
     * @param string $text Text to escape
     * @return string
     */
    public static function escapeHtml($text) {
        if (self::functionExists('esc_html')) {
            return esc_html($text);
        }
        
        // Fallback escaping
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Escape HTML attribute with fallback
     * 
     * @param string $text Text to escape
     * @return string
     */
    public static function escapeAttr($text) {
        if (self::functionExists('esc_attr')) {
            return esc_attr($text);
        }
        
        // Fallback escaping
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get current user ID with fallback
     * 
     * @return int
     */
    public static function getCurrentUserId() {
        if (self::functionExists('get_current_user_id')) {
            return get_current_user_id();
        }
        
        // Fallback for non-WordPress environments
        return 0;
    }
    
    /**
     * Check if we're in admin area
     * 
     * @return bool
     */
    public static function isAdminArea() {
        if (self::functionExists('is_admin')) {
            return is_admin();
        }
        
        // Fallback based on URL path
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($request_uri, '/wp-admin/') !== false || strpos($request_uri, 'admin.php') !== false;
    }
    
    /**
     * Check if we're in AJAX request
     * 
     * @return bool
     */
    public static function isAjaxRequest() {
        return self::safeExecute('wp_doing_ajax', [], false) || 
               (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
    
    /**
     * Get WordPress home URL with fallback
     * 
     * @param string $path Optional path
     * @param string $scheme URL scheme
     * @return string
     */
    public static function getHomeUrl($path = '', $scheme = null) {
        if (self::functionExists('home_url')) {
            return home_url($path, $scheme);
        }
        
        // Fallback for non-WordPress environments
        $protocol = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/' . ltrim($path, '/');
    }
    
    /**
     * Get WordPress admin URL with fallback
     * 
     * @param string $path Optional path
     * @param string $scheme URL scheme
     * @return string
     */
    public static function getAdminUrl($path = '', $scheme = null) {
        if (self::functionExists('admin_url')) {
            return admin_url($path, $scheme);
        }
        
        // Fallback for non-WordPress environments
        return self::getHomeUrl('wp-admin/' . ltrim($path, '/'), $scheme);
    }
    
    /**
     * Enqueue script with fallback
     * 
     * @param string $handle Script handle
     * @param string $src Script source
     * @param array $deps Dependencies
     * @param string|bool $ver Version
     * @param bool $in_footer Whether to load in footer
     */
    public static function enqueueScript($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
        if (self::functionExists('wp_enqueue_script')) {
            wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
        } else {
            // Fallback: add to HTML head manually
            ErrorHandler::log(ErrorHandler::LEVEL_DEBUG, 'Cannot enqueue script outside WordPress', [
                'handle' => $handle,
                'src' => $src
            ]);
        }
    }
    
    /**
     * Enqueue style with fallback
     * 
     * @param string $handle Style handle
     * @param string $src Style source
     * @param array $deps Dependencies
     * @param string|bool $ver Version
     * @param string $media Media query
     */
    public static function enqueueStyle($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        if (self::functionExists('wp_enqueue_style')) {
            wp_enqueue_style($handle, $src, $deps, $ver, $media);
        } else {
            // Fallback: add to HTML head manually
            ErrorHandler::log(ErrorHandler::LEVEL_DEBUG, 'Cannot enqueue style outside WordPress', [
                'handle' => $handle,
                'src' => $src
            ]);
        }
    }
    
    /**
     * Add action hook with fallback
     * 
     * @param string $hook Hook name
     * @param callable $function_to_add Function to add
     * @param int $priority Priority
     * @param int $accepted_args Number of accepted arguments
     */
    public static function addAction($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
        if (self::functionExists('add_action')) {
            add_action($hook, $function_to_add, $priority, $accepted_args);
        } else {
            // Fallback: store for manual execution
            if (!isset(self::$available_functions['hooks'])) {
                self::$available_functions['hooks'] = [];
            }
            
            if (!isset(self::$available_functions['hooks'][$hook])) {
                self::$available_functions['hooks'][$hook] = [];
            }
            
            self::$available_functions['hooks'][$hook][] = [
                'function' => $function_to_add,
                'priority' => $priority,
                'accepted_args' => $accepted_args
            ];
        }
    }
    
    /**
     * Execute queued actions (for non-WordPress environments)
     * 
     * @param string $hook Hook name
     * @param ...$args Arguments to pass
     */
    public static function doAction($hook, ...$args) {
        if (self::functionExists('do_action')) {
            do_action($hook, ...$args);
        } elseif (isset(self::$available_functions['hooks'][$hook])) {
            // Execute manually queued actions
            foreach (self::$available_functions['hooks'][$hook] as $action) {
                call_user_func_array($action['function'], $args);
            }
        }
    }
    
    /**
     * Check if plugin is active
     * 
     * @param string $plugin_file Plugin file path
     * @return bool
     */
    public static function isPluginActive($plugin_file) {
        if (self::functionExists('is_plugin_active')) {
            return is_plugin_active($plugin_file);
        }
        
        // Fallback: check active plugins list
        $active_plugins = self::getOption('active_plugins', []);
        return in_array($plugin_file, $active_plugins);
    }
    
    /**
     * Get WordPress memory limit
     * 
     * @return string
     */
    public static function getMemoryLimit() {
        if (self::functionExists('ini_get')) {
            return ini_get('memory_limit');
        }
        
        return '128M'; // Default fallback
    }
    
    /**
     * Check if we're in WordPress multisite
     * 
     * @return bool
     */
    public static function isMultisite() {
        if (self::functionExists('is_multisite')) {
            return is_multisite();
        }
        
        return defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE;
    }
}