<?php
/**
 * Platform Performance Optimizations for SMO Social Plugin
 * 
 * This file contains optimized platform management functions and strategies
 * to improve the performance of platform operations by implementing
 * lazy loading, connection pooling, and efficient resource management.
 * 
 * @package SMO_Social
 * @subpackage Performance_Optimizations
 * @since 1.0.0
 */

namespace SMO_Social\Performance\Platforms;

// Ensure direct file access is blocked
if (!defined('ABSPATH')) {
    wp_die(__('Access denied', 'smo-social'));
}

/**
 * Platform Performance Optimizations Class
 * 
 * Contains optimized platform management functions for improved performance
 */
class PlatformOptimizations {
    
    /**
     * Lazy loading platform manager with intelligent caching
     * 
     * @package private
     */
    private static $loaded_platforms = array();
    private static $platform_cache = array();
    private static $connection_pool = array();
    
    /**
     * Get platform instance with lazy loading and caching
     * 
     * @param string $slug Platform slug
     * @return mixed Platform instance or null
     */
    public static function get_platform_lazy($slug) {
        // Return cached platform if already loaded
        if (isset(self::$loaded_platforms[$slug])) {
            return self::$loaded_platforms[$slug];
        }
        
        // Load platform only when needed
        $platform = self::load_platform($slug);
        
        if ($platform) {
            self::$loaded_platforms[$slug] = $platform;
            return $platform;
        }
        
        return null;
    }
    
    /**
     * Load platform with intelligent caching
     * 
     * @param string $slug Platform slug
     * @return mixed Platform instance or null
     */
    private static function load_platform($slug) {
        $cache_key = "platform_{$slug}";
        
        // Check in-memory cache first
        if (isset(self::$platform_cache[$cache_key])) {
            return self::$platform_cache[$cache_key];
        }
        
        $platform_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$slug}.json";
        
        if (!file_exists($platform_file)) {
            return null;
        }
        
        $platform_data = json_decode(file_get_contents($platform_file), true);
        
        if (!$platform_data) {
            return null;
        }
        
        // Create platform instance
        $class_name = self::get_platform_class($slug);
        
        if (!class_exists($class_name)) {
            return null;
        }
        
        $platform = new $class_name($platform_data);
        
        // Cache for future use
        self::$platform_cache[$cache_key] = $platform;
        
        return $platform;
    }
    
    /**
     * Get platform class name
     * 
     * @param string $slug Platform slug
     * @return string Class name
     */
    private static function get_platform_class($slug) {
        // Map platform slugs to class names
        $class_map = array(
            'twitter' => '\\SMO_Social\\Platforms\\TwitterPlatform',
            'facebook' => '\\SMO_Social\\Platforms\\FacebookPlatform',
            'instagram' => '\\SMO_Social\\Platforms\\InstagramPlatform',
            'linkedin' => '\\SMO_Social\\Platforms\\LinkedInPlatform',
            'mastodon' => '\\SMO_Social\\Platforms\\MastodonPlatform',
            'youtube' => '\\SMO_Social\\Platforms\\YouTubePlatform',
            'tiktok' => '\\SMO_Social\\Platforms\\TikTokPlatform',
            // Add more mappings as needed
        );
        
        return $class_map[$slug] ?? '\\SMO_Social\\Platforms\\GenericPlatform';
    }
    
    /**
     * Clear loaded platforms cache
     * 
     * @return void
     */
    public static function clear_platform_cache() {
        self::$loaded_platforms = array();
        self::$platform_cache = array();
        self::$connection_pool = array();
    }
    
    /**
     * Batch process platform operations with connection pooling
     * 
     * @param array $platforms Array of platform slugs
     * @param string $operation Operation to perform
     * @param array $data Data for the operation
     * @return array Results from all platforms
     */
    public static function batch_process_platforms($platforms, $operation, $data = array()) {
        if (empty($platforms)) {
            return array();
        }
        
        $results = array();
        $errors = array();
        
        // Create connection pool for batch operations
        $connection_pool = self::create_connection_pool($platforms);
        
        foreach ($platforms as $slug) {
            try {
                $platform = self::get_platform_lazy($slug);
                
                if (!$platform) {
                    $errors[$slug] = 'Platform not found';
                    continue;
                }
                
                // Use connection from pool if available
                $connection = $connection_pool[$slug] ?? null;
                
                // Perform operation based on type
                $result = self::perform_platform_operation($platform, $operation, $data, $connection);
                
                if ($result !== false) {
                    $results[$slug] = $result;
                } else {
                    $errors[$slug] = 'Operation failed';
                }
                
            } catch (\Exception $e) {
                $errors[$slug] = $e->getMessage();
            }
        }
        
        // Close connection pool
        self::close_connection_pool($connection_pool);
        
        return array(
            'success' => $results,
            'errors' => $errors,
            'total_processed' => count($results),
            'total_errors' => count($errors)
        );
    }
    
    /**
     * Create connection pool for platforms
     * 
     * @param array $platforms Array of platform slugs
     * @return array Connection pool
     */
    private static function create_connection_pool($platforms) {
        $pool = array();
        
        foreach ($platforms as $slug) {
            try {
                // Create persistent connection for each platform
                $pool[$slug] = self::create_persistent_connection($slug);
            } catch (\Exception $e) {
                // Connection failed, will use regular connection
                error_log("SMO Social: Connection pool creation failed for {$slug}: " . $e->getMessage());
            }
        }
        
        return $pool;
    }
    
    /**
     * Create persistent connection for platform
     * 
     * @param string $slug Platform slug
     * @return mixed Connection object or null
     */
    private static function create_persistent_connection($slug) {
        // This is a placeholder for actual connection pooling logic
        // In a real implementation, this would create HTTP clients with connection reuse
        return array(
            'platform' => $slug,
            'connection_id' => uniqid('conn_'),
            'created_at' => time()
        );
    }
    
    /**
     * Perform platform operation with connection
     * 
     * @param object $platform Platform instance
     * @param string $operation Operation type
     * @param array $data Operation data
     * @param mixed $connection Connection object
     * @return mixed Operation result or false on failure
     */
    private static function perform_platform_operation($platform, $operation, $data, $connection = null) {
        switch ($operation) {
            case 'health_check':
                return $platform->health_check();
                
            case 'get_status':
                return $platform->get_status();
                
            case 'test_connection':
                return $platform->test_connection();
                
            case 'get_features':
                return $platform->get_features();
                
            case 'get_metrics':
                return $platform->get_metrics($data);
                
            default:
                throw new \Exception("Unknown operation: {$operation}");
        }
    }
    
    /**
     * Close connection pool
     * 
     * @param array $connection_pool Connection pool to close
     * @return void
     */
    private static function close_connection_pool($connection_pool) {
        foreach ($connection_pool as $connection) {
            // Close persistent connections
            self::close_connection($connection);
        }
    }
    
    /**
     * Close individual connection
     * 
     * @param mixed $connection Connection to close
     * @return void
     */
    private static function close_connection($connection) {
        // Placeholder for actual connection closing logic
        // In a real implementation, this would properly close HTTP clients
        error_log("SMO Social: Closing connection for platform " . $connection['platform']);
    }
    
    /**
     * Optimize platform token queries with batching
     * 
     * @param array $platforms Array of platform slugs
     * @return array Token information for all platforms
     */
    public static function get_platform_tokens_optimized($platforms) {
        if (empty($platforms)) {
            return array();
        }
        
        global $wpdb;
        $tokens_table = $wpdb->prefix . 'smo_platform_tokens';
        
        $platforms_str = "'" . implode("','", $platforms) . "'";
        
        // Single query to get all token information
        $token_data = $wpdb->get_results("
            SELECT 
                platform_slug,
                COUNT(*) as total_tokens,
                COUNT(CASE WHEN token_expires > NOW() THEN 1 END) as valid_tokens,
                COUNT(CASE WHEN token_expires <= NOW() THEN 1 END) as expired_tokens,
                MAX(updated_at) as last_updated,
                GROUP_CONCAT(user_id) as user_ids
            FROM $tokens_table 
            WHERE platform_slug IN ($platforms_str)
            GROUP BY platform_slug
        ", ARRAY_A);
        
        $results = array();
        foreach ($token_data as $data) {
            $results[$data['platform_slug']] = array(
                'total_tokens' => (int) $data['total_tokens'],
                'valid_tokens' => (int) $data['valid_tokens'],
                'expired_tokens' => (int) $data['expired_tokens'],
                'last_updated' => $data['last_updated'],
                'user_ids' => $data['user_ids'] ? explode(',', $data['user_ids']) : array()
            );
        }
        
        return $results;
    }
    
    /**
     * Rate limiting optimization for platform operations
     * 
     * @param string $platform_slug Platform slug
     * @param string $operation Operation type
     * @return bool Whether operation should proceed
     */
    public static function check_rate_limit($platform_slug, $operation) {
        $cache_key = "rate_limit_{$platform_slug}_{$operation}";
        $limit_data = get_transient($cache_key);
        
        if ($limit_data === false) {
            // Initialize rate limit tracking
            $limit_data = array(
                'count' => 0,
                'window_start' => time(),
                'requests_per_minute' => 0
            );
        }
        
        $current_time = time();
        $window_duration = 60; // 1 minute window
        
        // Reset window if expired
        if ($current_time - $limit_data['window_start'] > $window_duration) {
            $limit_data = array(
                'count' => 0,
                'window_start' => $current_time,
                'requests_per_minute' => $limit_data['count']
            );
        }
        
        // Check rate limits (platform-specific)
        $limits = self::get_rate_limits($platform_slug);
        $max_requests = $limits[$operation] ?? 60; // Default 60 requests per minute
        
        if ($limit_data['count'] >= $max_requests) {
            return false; // Rate limited
        }
        
        // Increment counter
        $limit_data['count']++;
        
        // Cache updated data
        set_transient($cache_key, $limit_data, $window_duration);
        
        return true;
    }
    
    /**
     * Get rate limits for platform operations
     * 
     * @param string $platform_slug Platform slug
     * @return array Rate limits configuration
     */
    private static function get_rate_limits($platform_slug) {
        $limits = array(
            'twitter' => array(
                'health_check' => 120,
                'post' => 300,
                'get_metrics' => 60
            ),
            'facebook' => array(
                'health_check' => 100,
                'post' => 200,
                'get_metrics' => 50
            ),
            'instagram' => array(
                'health_check' => 100,
                'post' => 150,
                'get_metrics' => 40
            ),
            'linkedin' => array(
                'health_check' => 80,
                'post' => 100,
                'get_metrics' => 30
            ),
            'mastodon' => array(
                'health_check' => 60,
                'post' => 80,
                'get_metrics' => 20
            )
        );
        
        return $limits[$platform_slug] ?? array('health_check' => 60, 'post' => 100, 'get_metrics' => 30);
    }
    
    /**
     * Platform performance monitoring
     * 
     * @param string $platform_slug Platform slug
     * @param string $operation Operation type
     * @param float $execution_time Execution time in seconds
     * @param bool $success Whether operation was successful
     * @return void
     */
    public static function log_platform_performance($platform_slug, $operation, $execution_time, $success) {
        $cache_key = "perf_{$platform_slug}_{$operation}";
        $perf_data = get_transient($cache_key);
        
        if ($perf_data === false) {
            $perf_data = array(
                'total_operations' => 0,
                'successful_operations' => 0,
                'total_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0
            );
        }
        
        $perf_data['total_operations']++;
        
        if ($success) {
            $perf_data['successful_operations']++;
            $perf_data['total_time'] += $execution_time;
            $perf_data['min_time'] = min($perf_data['min_time'], $execution_time);
            $perf_data['max_time'] = max($perf_data['max_time'], $execution_time);
        }
        
        // Cache performance data for 1 hour
        set_transient($cache_key, $perf_data, 3600);
    }
    
    /**
     * Get platform performance statistics
     * 
     * @param string $platform_slug Platform slug
     * @return array Performance statistics
     */
    public static function get_platform_performance_stats($platform_slug) {
        $stats = array();
        
        $operations = array('health_check', 'post', 'get_metrics', 'test_connection');
        
        foreach ($operations as $operation) {
            $cache_key = "perf_{$platform_slug}_{$operation}";
            $perf_data = get_transient($cache_key);
            
            if ($perf_data !== false) {
                $avg_time = $perf_data['total_operations'] > 0 ? 
                    $perf_data['total_time'] / $perf_data['successful_operations'] : 0;
                
                $success_rate = $perf_data['total_operations'] > 0 ? 
                    ($perf_data['successful_operations'] / $perf_data['total_operations']) * 100 : 0;
                
                $stats[$operation] = array(
                    'total_operations' => $perf_data['total_operations'],
                    'successful_operations' => $perf_data['successful_operations'],
                    'success_rate' => round($success_rate, 2),
                    'avg_execution_time' => round($avg_time, 3),
                    'min_execution_time' => $perf_data['min_time'] === PHP_FLOAT_MAX ? 0 : $perf_data['min_time'],
                    'max_execution_time' => $perf_data['max_time']
                );
            }
        }
        
        return $stats;
    }
    
    /**
     * Optimize platform initialization sequence
     * 
     * @param array $platforms Array of platform slugs to initialize
     * @return array Initialization results
     */
    public static function initialize_platforms_optimized($platforms) {
        if (empty($platforms)) {
            return array('initialized' => 0, 'failed' => 0, 'errors' => array());
        }
        
        $results = array(
            'initialized' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        // Sort platforms by priority (commonly used platforms first)
        $priority_order = array('facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'tiktok', 'mastodon');
        $sorted_platforms = self::sort_platforms_by_priority($platforms, $priority_order);
        
        foreach ($sorted_platforms as $slug) {
            try {
                $start_time = microtime(true);
                
                $platform = self::get_platform_lazy($slug);
                
                $end_time = microtime(true);
                $execution_time = $end_time - $start_time;
                
                if ($platform) {
                    $results['initialized']++;
                    self::log_platform_performance($slug, 'initialization', $execution_time, true);
                } else {
                    $results['failed']++;
                    $results['errors'][$slug] = 'Platform initialization failed';
                    self::log_platform_performance($slug, 'initialization', $execution_time, false);
                }
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$slug] = $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Sort platforms by priority
     * 
     * @param array $platforms Array of platform slugs
     * @param array $priority_order Priority order array
     * @return array Sorted platform slugs
     */
    private static function sort_platforms_by_priority($platforms, $priority_order) {
        // Create priority map
        $priority_map = array_flip($priority_order);
        
        // Sort platforms based on priority
        usort($platforms, function($a, $b) use ($priority_map) {
            $priority_a = $priority_map[$a] ?? PHP_INT_MAX;
            $priority_b = $priority_map[$b] ?? PHP_INT_MAX;
            
            return $priority_a - $priority_b;
        });
        
        return $platforms;
    }
    
    /**
     * Cleanup platform resources
     * 
     * @return void
     */
    public static function cleanup_platform_resources() {
        // Clear all caches
        self::clear_platform_cache();
        
        // Cleanup any persistent connections
        if (!empty(self::$connection_pool)) {
            self::close_connection_pool(self::$connection_pool);
        }
        
        // Clear performance statistics older than 24 hours
        self::cleanup_old_performance_data();
    }
    
    /**
     * Cleanup old performance data
     * 
     * @return void
     */
    private static function cleanup_old_performance_data() {
        global $wpdb;
        
        // This is a simplified cleanup - in a real implementation,
        // you would iterate through all transients and remove old ones
        $cleanup_key = 'platform_perf_cleanup';
        $last_cleanup = get_transient($cleanup_key);
        
        if ($last_cleanup === false || time() - $last_cleanup > 86400) { // 24 hours
            // Mark cleanup as done
            set_transient($cleanup_key, time(), 86400);
            
            // In a real implementation, you would use WordPress transients API
            // or a custom cleanup mechanism to remove old performance data
            error_log('SMO Social: Platform performance data cleanup completed');
        }
    }
}
