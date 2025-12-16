<?php
/**
 * Optimized Autoloader for SMO Social Plugin
 * 
 * This file provides a performance-optimized autoloader that replaces the
 * inefficient directory-scanning autoloader with a class map-based approach
 * and lazy loading capabilities.
 *
 * @package SMO_Social
 * @subpackage Performance_Optimizations
 * @since 1.0.0
 */

namespace SMO_Social\Performance;

// Ensure direct file access is blocked
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimized Autoloader Class
 * 
 * Provides efficient class loading with caching and lazy loading
 */
class OptimizedAutoloader {
    
    /**
     * Class map cache
     */
    private static $class_map = null;
    
    /**
     * Plugin directory
     */
    private static $plugin_dir = null;
    
    /**
     * Cache key for class map
     */
    private static $cache_key = 'smo_class_map';
    
    /**
     * Directory to namespace mapping
     */
    private static $directory_map = array();
    
    /**
     * Initialize the optimized autoloader
     */
    public static function init() {
        self::$plugin_dir = SMO_SOCIAL_PLUGIN_DIR;
        
        // Initialize directory mapping
        self::init_directory_map();
        
        // Load cached class map or build it
        self::load_class_map();
        
        // Register the autoloader
        spl_autoload_register(array(__CLASS__, 'autoload'));
        
        // Add performance hooks
        add_action('smo_before_class_load', array(__CLASS__, 'log_class_load_start'));
        add_action('smo_after_class_load', array(__CLASS__, 'log_class_load_end'), 10, 2);
    }
    
    /**
     * Initialize directory to namespace mapping
     */
    private static function init_directory_map() {
        self::$directory_map = array(
            'Core' => 'SMO_Social\\Core',
            'Admin' => 'SMO_Social\\Admin',
            'AI' => 'SMO_Social\\AI',
            'Analytics' => 'SMO_Social\\Analytics',
            'API' => 'SMO_Social\\API',
            'Automation' => 'SMO_Social\\Automation',
            'Chat' => 'SMO_Social\\Chat',
            'Collaboration' => 'SMO_Social\\Collaboration',
            'Community' => 'SMO_Social\\Community',
            'Content' => 'SMO_Social\\Content',
            'Features' => 'SMO_Social\\Features',
            'Platforms' => 'SMO_Social\\Platforms',
            'Reports' => 'SMO_Social\\Reports',
            'Resilience' => 'SMO_Social\\Resilience',
            'Scheduling' => 'SMO_Social\\Scheduling',
            'Security' => 'SMO_Social\\Security',
            'Social' => 'SMO_Social\\Social',
            'Team' => 'SMO_Social\\Team',
            'Testing' => 'SMO_Social\\Testing',
            'WebSocket' => 'SMO_Social\\WebSocket',
            'WhiteLabel' => 'SMO_Social\\WhiteLabel',
            'WordPress' => 'SMO_Social\\WordPress',
            'Admin/Views' => 'SMO_Social\\Admin\\Views',
            'Admin/Widgets' => 'SMO_Social\\Admin\\Widgets',
            'AI/Analysis' => 'SMO_Social\\AI\\Analysis',
            'AI/Content' => 'SMO_Social\\AI\\Content',
            'AI/Models' => 'SMO_Social\\AI\\Models',
            'AI/Optimization' => 'SMO_Social\\AI\\Optimization',
            'AI/Processing' => 'SMO_Social\\AI\\Processing'
        );
    }
    
    /**
     * Load class map from cache or build it
     */
    private static function load_class_map() {
        // Try to get from cache first
        if (self::$class_map === null) {
            $cached_map = get_transient(self::$cache_key);
            
            if ($cached_map !== false) {
                self::$class_map = $cached_map;
                return;
            }
            
            // Build class map if not cached
            self::build_class_map();
            
            // Cache the class map
            set_transient(self::$cache_key, self::$class_map, 3600); // 1 hour cache
        }
    }
    
    /**
     * Build class map by scanning the includes directory
     */
    private static function build_class_map() {
        self::$class_map = array();
        
        $includes_dir = self::$plugin_dir . 'includes/';
        
        if (!is_dir($includes_dir)) {
            return;
        }
        
        // Scan each directory
        foreach (self::$directory_map as $dir => $namespace) {
            $dir_path = $includes_dir . $dir;
            if (is_dir($dir_path)) {
                self::scan_directory($dir_path, $namespace, $dir);
            }
        }
        
        // Add special cases for subdirectories
        self::add_special_cases();
    }
    
    /**
     * Scan a directory recursively for PHP files
     */
    private static function scan_directory($dir_path, $namespace, $relative_path) {
        $files = glob($dir_path . '/*.php');
        
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            
            // Build full class name
            $class_name = $namespace . '\\' . $filename;
            
            // Map to file path
            $file_path = str_replace(self::$plugin_dir, '', $file);
            
            self::$class_map[$class_name] = $file_path;
        }
        
        // Scan subdirectories
        $subdirs = glob($dir_path . '/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $subdir_name = basename($subdir);
            $subdir_relative = $relative_path . '/' . $subdir_name;
            $subdir_namespace = $namespace . '\\' . $subdir_name;
            
            if (isset(self::$directory_map[$subdir_relative])) {
                $subdir_namespace = self::$directory_map[$subdir_relative];
            }
            
            self::scan_directory($subdir, $subdir_namespace, $subdir_relative);
        }
    }
    
    /**
     * Add special case mappings
     */
    private static function add_special_cases() {
        // Add any special class mappings here
        self::$class_map['SMO_Social\\Core\\Plugin'] = 'includes/Core/Plugin.php';
        self::$class_map['SMO_Social\\Core\\DatabaseManager'] = 'includes/Core/DatabaseManager.php';
        self::$class_map['SMO_Social\\Admin\\Admin'] = 'includes/Admin/Admin.php';
    }
    
    /**
     * Autoload function
     */
    public static function autoload($class_name) {
        // Only handle SMO_Social classes
        if (strpos($class_name, 'SMO_Social\\') !== 0) {
            return false;
        }
        
        do_action('smo_before_class_load', $class_name);
        
        // Check if class is in our map
        if (isset(self::$class_map[$class_name])) {
            $file_path = self::$plugin_dir . self::$class_map[$class_name];
            
            if (file_exists($file_path)) {
                // Use include_once to prevent multiple includes
                include_once $file_path;
                
                do_action('smo_after_class_load', $class_name, $file_path);
                return true;
            }
        }
        
        do_action('smo_after_class_load', $class_name, null);
        return false;
    }
    
    /**
     * Log class load start
     */
    public static function log_class_load_start($class_name) {
        if (!defined('SMO_CLASS_LOAD_START')) {
            define('SMO_CLASS_LOAD_START', microtime(true));
        }
        
        $GLOBALS['smo_current_class'] = $class_name;
    }
    
    /**
     * Log class load end
     */
    public static function log_class_load_end($class_name, $file_path) {
        if (isset($GLOBALS['smo_current_class']) && $GLOBALS['smo_current_class'] === $class_name) {
            $load_time = microtime(true) - SMO_CLASS_LOAD_START;
            
            // Log slow class loading
            if ($load_time > 0.1) { // 100ms threshold
                error_log(sprintf(
                    'SMO Slow Class Load: %s took %.3fs (File: %s)',
                    $class_name,
                    $load_time,
                    $file_path ?: 'Not found'
                ));
            }
            
            unset($GLOBALS['smo_current_class']);
        }
    }
    
    /**
     * Force rebuild of class map
     */
    public static function rebuild_class_map() {
        delete_transient(self::$cache_key);
        self::$class_map = null;
        self::load_class_map();
    }
    
    /**
     * Get class map statistics
     */
    public static function get_class_map_stats() {
        return array(
            'total_classes' => count(self::$class_map),
            'cached' => get_transient(self::$cache_key) !== false,
            'cache_age' => get_option('_transient_timeout_' . self::$cache_key, 0) - time(),
            'memory_usage' => memory_get_usage(true)
        );
    }
    
    /**
     * Preload critical classes
     */
    public static function preload_critical_classes() {
        $critical_classes = array(
            'SMO_Social\\Core\\Plugin',
            'SMO_Social\\Core\\DatabaseManager',
            'SMO_Social\\Admin\\Admin',
            'SMO_Social\\Platforms\\Manager',
            'SMO_Social\\AI\\Manager'
        );
        
        foreach ($critical_classes as $class) {
            if (!class_exists($class)) {
                self::autoload($class);
            }
        }
    }
    
    /**
     * Clear class map cache
     */
    public static function clear_cache() {
        delete_transient(self::$cache_key);
        self::$class_map = null;
    }
}