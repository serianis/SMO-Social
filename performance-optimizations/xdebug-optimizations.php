<?php
/**
 * Xdebug Performance Optimizations for SMO Social Plugin
 * 
 * This file provides comprehensive optimizations to address Xdebug timeouts
 * and initialization delays by implementing lazy loading, caching, and
 * performance monitoring.
 *
 * @package SMO_Social
 * @subpackage Performance_Optimizations
 * @since 1.0.0
 */

namespace SMO_Social\Performance\Xdebug;

// Ensure direct file access is blocked
if (!defined('ABSPATH')) {
    wp_die(__('Access denied', 'smo-social'));
}

/**
 * Xdebug Performance Optimizer
 * 
 * Handles Xdebug-specific optimizations and performance monitoring
 */
class XdebugOptimizer {
    
    /**
     * Initialize Xdebug optimizations
     */
    public static function init() {
        // Set PHP performance settings for Xdebug
        self::configure_php_settings();
        
        // Implement lazy loading for database schema
        self::setup_lazy_loading();
        
        // Add performance monitoring hooks
        self::setup_performance_monitoring();
        
        // Cache optimization
        self::setup_caching_optimization();
    }
    
    /**
     * Configure PHP settings for optimal Xdebug performance
     */
    private static function configure_php_settings() {
        // Increase memory limit for Xdebug operations
        if (self::should_adjust_memory_limit()) {
            @ini_set('memory_limit', '512M');
        }
        
        // Set optimal execution time for Xdebug
        if (self::should_adjust_execution_time()) {
            @ini_set('max_execution_time', 300);
        }
        
        // Optimize error reporting during debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            @ini_set('display_errors', 0);
            @ini_set('log_errors', 1);
        }
    }
    
    /**
     * Check if memory limit should be adjusted
     */
    private static function should_adjust_memory_limit() {
        $current_limit = self::convert_to_bytes(ini_get('memory_limit'));
        $recommended_limit = 256 * 1024 * 1024; // 256MB
        
        return $current_limit < $recommended_limit && !defined('WP_MEMORY_LIMIT');
    }
    
    /**
     * Check if execution time should be adjusted
     */
    private static function should_adjust_execution_time() {
        $current_time = ini_get('max_execution_time');
        $recommended_time = 300; // 5 minutes
        
        return $current_time > 0 && $current_time < $recommended_time;
    }
    
    /**
     * Convert memory limit string to bytes
     */
    private static function convert_to_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
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
     * Setup lazy loading for database schema files
     */
    private static function setup_lazy_loading() {
        // Replace direct schema loading with lazy loading
        add_filter('smo_lazy_load_schema', array(__CLASS__, 'lazy_load_schema_file'), 10, 2);
        
        // Cache schema operations
        add_action('smo_schema_operation', array(__CLASS__, 'cache_schema_operation'), 10, 3);
    }
    
    /**
     * Lazy load schema files only when needed
     */
    public static function lazy_load_schema_file($file_path, $operation) {
        $cache_key = 'smo_schema_' . md5($file_path . $operation);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            self::log_performance('schema_cache_hit', $file_path);
            return $cached_result;
        }
        
        $start_time = microtime(true);
        
        if (file_exists($file_path)) {
            // Only load the specific operation needed
            ob_start();
            include_once $file_path;
            $output = ob_get_clean();
            
            $execution_time = microtime(true) - $start_time;
            
            // Cache the result for future use
            set_transient($cache_key, $output, 3600); // 1 hour cache
            
            self::log_performance('schema_lazy_load', $file_path, $execution_time);
            
            return $output;
        }
        
        return false;
    }
    
    /**
     * Cache schema operations to avoid repeated execution
     */
    public static function cache_schema_operation($operation, $table_name, $result) {
        $cache_key = 'smo_schema_op_' . md5($operation . $table_name);
        set_transient($cache_key, array(
            'result' => $result,
            'timestamp' => time(),
            'operation' => $operation
        ), 1800); // 30 minutes cache
    }
    
    /**
     * Setup performance monitoring
     */
    private static function setup_performance_monitoring() {
        // Monitor plugin initialization time
        add_action('plugins_loaded', array(__CLASS__, 'measure_initialization_time'), 1);
        
        // Monitor database operations
        add_action('smo_before_db_operation', array(__CLASS__, 'log_db_operation_start'), 10, 2);
        add_action('smo_after_db_operation', array(__CLASS__, 'log_db_operation_end'), 10, 3);
        
        // Monitor file operations
        add_action('smo_before_file_operation', array(__CLASS__, 'log_file_operation_start'), 10, 2);
        add_action('smo_after_file_operation', array(__CLASS__, 'log_file_operation_end'), 10, 3);
    }
    
    /**
     * Measure plugin initialization time
     */
    public static function measure_initialization_time() {
        if (!defined('SMO_INIT_START_TIME')) {
            define('SMO_INIT_START_TIME', microtime(true));
        }
        
        $init_time = microtime(true) - SMO_INIT_START_TIME;
        
        if ($init_time > 2.0) { // Log if initialization takes more than 2 seconds
            self::log_performance('slow_initialization', array(
                'time' => $init_time,
                'memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ));
        }
    }
    
    /**
     * Log database operation start
     */
    public static function log_db_operation_start($operation, $table_name) {
        $GLOBALS['smo_db_op_start'] = microtime(true);
        $GLOBALS['smo_db_op_info'] = array($operation, $table_name);
    }
    
    /**
     * Log database operation end
     */
    public static function log_db_operation_end($operation, $table_name, $result) {
        if (isset($GLOBALS['smo_db_op_start'])) {
            $execution_time = microtime(true) - $GLOBALS['smo_db_op_start'];
            
            if ($execution_time > 1.0) { // Log slow operations
                self::log_performance('slow_db_operation', array(
                    'operation' => $operation,
                    'table' => $table_name,
                    'time' => $execution_time,
                    'memory' => memory_get_usage(true)
                ));
            }
            
            unset($GLOBALS['smo_db_op_start'], $GLOBALS['smo_db_op_info']);
        }
    }
    
    /**
     * Log file operation start
     */
    public static function log_file_operation_start($operation, $file_path) {
        $GLOBALS['smo_file_op_start'] = microtime(true);
        $GLOBALS['smo_file_op_info'] = array($operation, $file_path);
    }
    
    /**
     * Log file operation end
     */
    public static function log_file_operation_end($operation, $file_path, $result) {
        if (isset($GLOBALS['smo_file_op_start'])) {
            $execution_time = microtime(true) - $GLOBALS['smo_file_op_start'];
            
            if ($execution_time > 0.5) { // Log slow file operations
                self::log_performance('slow_file_operation', array(
                    'operation' => $operation,
                    'file' => $file_path,
                    'time' => $execution_time,
                    'memory' => memory_get_usage(true)
                ));
            }
            
            unset($GLOBALS['smo_file_op_start'], $GLOBALS['smo_file_op_info']);
        }
    }
    
    /**
     * Setup caching optimization
     */
    private static function setup_caching_optimization() {
        // Cache frequently accessed data
        add_action('init', array(__CLASS__, 'setup_data_caching'));
        
        // Implement object caching for schema data
        add_filter('smo_get_cached_schema', array(__CLASS__, 'get_cached_schema'), 10, 2);
        add_filter('smo_set_cached_schema', array(__CLASS__, 'set_cached_schema'), 10, 3);
    }
    
    /**
     * Setup data caching for frequently accessed information
     */
    public static function setup_data_caching() {
        // Cache plugin version to avoid repeated checks
        $cached_version = get_transient('smo_plugin_version');
        if ($cached_version === false) {
            set_transient('smo_plugin_version', SMO_SOCIAL_VERSION, 3600);
        }
        
        // Cache database schema version
        $cached_db_version = get_transient('smo_db_version');
        if ($cached_db_version === false) {
            $db_version = get_option('smo_social_db_version', '0.0.0');
            set_transient('smo_db_version', $db_version, 3600);
        }
    }
    
    /**
     * Get cached schema data
     */
    public static function get_cached_schema($schema_type, $user_id = null) {
        $cache_key = 'smo_schema_' . $schema_type;
        if ($user_id) {
            $cache_key .= '_' . $user_id;
        }
        
        return get_transient($cache_key);
    }
    
    /**
     * Set cached schema data
     */
    public static function set_cached_schema($schema_type, $data, $user_id = null) {
        $cache_key = 'smo_schema_' . $schema_type;
        if ($user_id) {
            $cache_key .= '_' . $user_id;
        }
        
        set_transient($cache_key, $data, 1800); // 30 minutes
    }
    
    /**
     * Log performance metrics
     */
    private static function log_performance($event, $data = null, $execution_time = null) {
        $log_entry = array(
            'event' => $event,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => $execution_time,
            'data' => $data
        );
        
        // Log to WordPress debug log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('SMO Xdebug Performance: ' . json_encode($log_entry));
        }
        
        // Store in custom performance log table if available
        self::store_performance_log($log_entry);
    }
    
    /**
     * Store performance log in database
     */
    private static function store_performance_log($log_entry) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_performance_logs';
        
        // Create table if it doesn't exist
        self::create_performance_log_table($table_name);
        
        $wpdb->insert($table_name, array(
            'event_type' => $log_entry['event'],
            'execution_time' => $log_entry['execution_time'],
            'memory_usage' => $log_entry['memory_usage'],
            'peak_memory' => $log_entry['peak_memory'],
            'event_data' => json_encode($log_entry['data']),
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Create performance log table
     */
    private static function create_performance_log_table($table_name) {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            execution_time decimal(10,6) DEFAULT NULL,
            memory_usage bigint(20) DEFAULT NULL,
            peak_memory bigint(20) DEFAULT NULL,
            event_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at),
            KEY idx_execution_time (execution_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get performance statistics
     */
    public static function get_performance_stats($time_range = '24 hours') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_performance_logs';
        $time_cutoff = date('Y-m-d H:i:s', strtotime('-' . $time_range));
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_events,
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time,
                AVG(memory_usage) as avg_memory_usage,
                MAX(peak_memory) as max_peak_memory,
                event_type,
                COUNT(*) as event_count
            FROM $table_name 
            WHERE created_at >= %s
            GROUP BY event_type
            ORDER BY event_count DESC
        ", $time_cutoff));
        
        return $stats;
    }
    
    /**
     * Clear performance logs
     */
    public static function clear_performance_logs($older_than_days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_performance_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$older_than_days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
    }
}

// Initialize Xdebug optimizations
if (class_exists('\SMO_Social\Performance\Xdebug\XdebugOptimizer')) {
    \SMO_Social\Performance\Xdebug\XdebugOptimizer::init();
}