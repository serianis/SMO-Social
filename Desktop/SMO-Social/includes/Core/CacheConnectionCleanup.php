<?php
/**
 * Cache Connection Cleanup for SMO Social
 *
 * Handles comprehensive cache connection cleanup, validation, and monitoring
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';
require_once __DIR__ . '/CacheObjectPool.php';
require_once __DIR__ . '/ResourceCleanupManager.php';

/**
 * Cache Connection Cleanup
 */
class CacheConnectionCleanup {
    /**
     * @var CacheObjectPool|null Cache object pool instance
     */
    private $cache_object_pool = null;

    /**
     * @var array Configuration for cleanup operations
     */
    private $config = [];

    /**
     * @var ResourceCleanupManager|null Resource cleanup manager instance
     */
    private $resource_cleanup_manager = null;

    /**
     * @var array Connection health statistics
     */
    private $health_stats = [
        'total_cache_objects' => 0,
        'active_cache_objects' => 0,
        'idle_cache_objects' => 0,
        'stale_cache_objects' => 0,
        'cleanup_count' => 0,
        'last_cleanup_time' => 0,
        'cache_errors' => 0,
        'validation_failures' => 0,
        'memory_usage' => 0,
        'hit_rate' => 0.0,
        'miss_rate' => 0.0
    ];

    /**
     * Constructor
     *
     * @param array $config Cleanup configuration
     */
    public function __construct($config = []) {
        $this->config = array_merge([
            'idle_timeout' => 300, // 5 minutes
            'stale_timeout' => 1800, // 30 minutes
            'max_cleanup_batch' => 10,
            'health_check_interval' => 60, // 1 minute
            'validation_interval' => 300, // 5 minutes
            'max_retries' => 3,
            'retry_delay' => 1000, // 1 second
            'memory_threshold' => 50, // MB
            'cache_ttl' => 3600 // 1 hour default TTL
        ], $config);

        $this->resource_cleanup_manager = new ResourceCleanupManager();
        $this->initialize_cache_object_pool();
    }

    /**
     * Initialize cache object pool
     */
    private function initialize_cache_object_pool() {
        if ($this->cache_object_pool === null) {
            $pool_size = 20; // Default pool size

            // Get pool size from settings if available
            $settings = get_option('smo_social_settings', []);
            if (isset($settings['cache_pool_size']) && is_numeric($settings['cache_pool_size'])) {
                $pool_size = max(5, min(50, intval($settings['cache_pool_size'])));
            }

            $this->cache_object_pool = new CacheObjectPool($pool_size);
        }
    }

    /**
     * Get cache object pool instance
     *
     * @return CacheObjectPool|null
     */
    public function get_cache_object_pool() {
        return $this->cache_object_pool;
    }

    /**
     * Validate cache object
     *
     * @param object $cache_object Cache object
     * @return bool True if cache object is valid
     */
    public function validate_cache_object($cache_object) {
        if (!is_object($cache_object)) {
            $this->health_stats['validation_failures']++;
            return false;
        }

        try {
            // Check if cache object has required methods
            if (!method_exists($cache_object, 'get') || !method_exists($cache_object, 'set')) {
                $this->health_stats['validation_failures']++;
                error_log('SMO Social: Cache object validation failed - missing required methods');
                return false;
            }

            // Check if cache object is still responsive
            $test_key = 'smo_cache_validation_test_' . time();
            $test_value = ['valid' => true, 'timestamp' => time()];

            // Test set operation
            $set_result = $cache_object->set($test_key, $test_value, 60);

            if (!$set_result) {
                $this->health_stats['validation_failures']++;
                error_log('SMO Social: Cache object validation failed - set operation failed');
                return false;
            }

            // Test get operation
            $get_result = $cache_object->get($test_key);

            if ($get_result === false || !isset($get_result['valid']) || !$get_result['valid']) {
                $this->health_stats['validation_failures']++;
                error_log('SMO Social: Cache object validation failed - get operation failed');
                return false;
            }

            // Clean up test data
            $cache_object->delete($test_key);

            return true;
        } catch (\Exception $e) {
            $this->health_stats['validation_failures']++;
            error_log('SMO Social: Cache object validation error - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check cache object health with comprehensive diagnostics
     *
     * @param object $cache_object Cache object
     * @return array Health check results
     */
    public function check_cache_object_health($cache_object) {
        $health_check = [
            'valid' => false,
            'set_operation_success' => false,
            'get_operation_success' => false,
            'delete_operation_success' => false,
            'latency' => 0,
            'error' => null,
            'timestamp' => time(),
            'memory_usage' => 0,
            'cache_size' => 0
        ];

        if (!is_object($cache_object)) {
            $health_check['error'] = 'Invalid cache object';
            return $health_check;
        }

        try {
            // Start timing
            $start_time = microtime(true);

            // Test set operation
            $test_key = 'smo_cache_health_test_' . time();
            $test_value = ['health_check' => true, 'timestamp' => time()];

            $set_result = $cache_object->set($test_key, $test_value, 60);
            $health_check['set_operation_success'] = $set_result;

            if (!$set_result) {
                $health_check['error'] = 'Set operation failed';
                return $health_check;
            }

            // Test get operation
            $get_result = $cache_object->get($test_key);
            $health_check['get_operation_success'] = $get_result !== false;

            if ($get_result === false) {
                $health_check['error'] = 'Get operation failed';
                return $health_check;
            }

            // Test delete operation
            $delete_result = $cache_object->delete($test_key);
            $health_check['delete_operation_success'] = $delete_result;

            // Calculate latency
            $health_check['latency'] = microtime(true) - $start_time;

            // Check memory usage
            $health_check['memory_usage'] = memory_get_usage(true) / (1024 * 1024);

            // Get cache size if method exists
            if (method_exists($cache_object, 'get_cache_size')) {
                $health_check['cache_size'] = $cache_object->get_cache_size();
            }

            $health_check['valid'] = $health_check['set_operation_success'] &&
                                  $health_check['get_operation_success'] &&
                                  $health_check['delete_operation_success'];

        } catch (\Exception $e) {
            $health_check['error'] = 'Health check error: ' . $e->getMessage();
        }

        return $health_check;
    }

    /**
     * Monitor cache object state and perform cleanup if needed
     *
     * @param object $cache_object Cache object
     * @param int $last_used_time Timestamp of last usage
     * @return bool True if cache object should be cleaned up
     */
    public function monitor_cache_object_state($cache_object, $last_used_time) {
        $current_time = time();
        $idle_time = $current_time - $last_used_time;

        // Check if cache object is idle
        $is_idle = $idle_time > $this->config['idle_timeout'];

        // Check if cache object is stale
        $is_stale = $idle_time > $this->config['stale_timeout'];

        // Validate cache object
        $is_valid = $this->validate_cache_object($cache_object);

        // Check memory usage
        $current_memory = memory_get_usage(true) / (1024 * 1024);
        $memory_exceeded = $current_memory > $this->config['memory_threshold'];

        // Update health statistics
        $this->health_stats['total_cache_objects']++;
        $this->health_stats['memory_usage'] = $current_memory;

        if ($is_valid) {
            if ($is_stale || $memory_exceeded) {
                $this->health_stats['stale_cache_objects']++;
                return true; // Clean up stale cache objects or when memory is high
            } elseif ($is_idle) {
                $this->health_stats['idle_cache_objects']++;
                // Keep idle cache objects for now, but mark for potential cleanup
            } else {
                $this->health_stats['active_cache_objects']++;
            }
        } else {
            $this->health_stats['cache_errors']++;
            return true; // Clean up invalid cache objects
        }

        return false;
    }

    /**
     * Clean up idle and stale cache objects
     *
     * @param int $max_cleanup Maximum number of cache objects to clean up
     * @return int Number of cache objects cleaned up
     */
    public function cleanup_idle_cache_objects($max_cleanup = null) {
        if ($this->cache_object_pool === null) {
            return 0;
        }

        $max_cleanup = $max_cleanup ?? $this->config['max_cleanup_batch'];
        $cleanup_count = 0;

        try {
            $cache_objects = $this->cache_object_pool->get_pool_status()['objects'];

            foreach ($cache_objects as $cache_object_id => $cache_object_data) {
                if ($cleanup_count >= $max_cleanup) {
                    break;
                }

                $cache_object = $cache_object_data['cache_object'] ?? null;
                $last_used = $cache_object_data['last_used'] ?? 0;

                if ($this->monitor_cache_object_state($cache_object, $last_used)) {
                    // Use the pool's cleanup method to remove idle objects
                    $this->cache_object_pool->cleanup_idle_objects(0); // Cleanup all idle objects
                    $cleanup_count++;
                    $this->health_stats['cleanup_count']++;
                }
            }

            $this->health_stats['last_cleanup_time'] = time();

        } catch (\Exception $e) {
            error_log('SMO Social: Cache object cleanup error - ' . $e->getMessage());
        }

        return $cleanup_count;
    }

    /**
     * Automatic cleanup for stale cache objects
     *
     * @return int Number of cache objects cleaned up
     */
    public function automatic_cleanup() {
        $cleanup_count = 0;

        try {
            // Clean up idle cache objects
            $cleanup_count += $this->cleanup_idle_cache_objects();

            // Additional cleanup for resource management
            if ($this->resource_cleanup_manager) {
                $this->resource_cleanup_manager->cleanup_all_streams();
            }

            // Check memory usage and cleanup if needed
            $this->check_memory_based_cleanup();

        } catch (\Exception $e) {
            error_log('SMO Social: Automatic cache cleanup error - ' . $e->getMessage());
        }

        return $cleanup_count;
    }

    /**
     * Memory-based cleanup when memory usage is high
     */
    public function check_memory_based_cleanup() {
        $current_memory = memory_get_usage(true) / (1024 * 1024);

        if ($current_memory > $this->config['memory_threshold']) {
            error_log('SMO Social: High memory usage detected - ' . $current_memory . 'MB, performing aggressive cache cleanup');

            // Clean up more cache objects when memory is high
            $this->cleanup_idle_cache_objects($this->config['max_cleanup_batch'] * 2);

            // Force garbage collection
            gc_collect_cycles();
        }
    }

    /**
     * Get cache object health statistics
     *
     * @return array Health statistics
     */
    public function get_health_statistics() {
        return $this->health_stats;
    }

    /**
     * Reset health statistics
     */
    public function reset_health_statistics() {
        $this->health_stats = [
            'total_cache_objects' => 0,
            'active_cache_objects' => 0,
            'idle_cache_objects' => 0,
            'stale_cache_objects' => 0,
            'cleanup_count' => 0,
            'last_cleanup_time' => 0,
            'cache_errors' => 0,
            'validation_failures' => 0,
            'memory_usage' => 0,
            'hit_rate' => 0.0,
            'miss_rate' => 0.0
        ];
    }

    /**
     * Get configuration
     *
     * @return array Current configuration
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Set configuration
     *
     * @param array $config New configuration
     */
    public function set_config($config) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Perform comprehensive cache object validation
     *
     * @return array Validation results
     */
    public function perform_comprehensive_validation() {
        $validation_results = [
            'total_cache_objects' => 0,
            'valid_cache_objects' => 0,
            'invalid_cache_objects' => 0,
            'validation_errors' => [],
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true) / (1024 * 1024)
        ];

        try {
            if ($this->cache_object_pool === null) {
                return $validation_results;
            }

            $cache_objects = $this->cache_object_pool->get_pool_status()['objects'];

            foreach ($cache_objects as $cache_object_id => $cache_object_data) {
                $cache_object = $cache_object_data['cache_object'] ?? null;
                $validation_results['total_cache_objects']++;

                if ($this->validate_cache_object($cache_object)) {
                    $validation_results['valid_cache_objects']++;
                } else {
                    $validation_results['invalid_cache_objects']++;
                    $validation_results['validation_errors'][] = "Cache object $cache_object_id failed validation";
                }
            }

        } catch (\Exception $e) {
            $validation_results['validation_errors'][] = 'Validation error: ' . $e->getMessage();
        }

        return $validation_results;
    }

    /**
     * Cache object state monitoring with timeout detection
     *
     * @param int $timeout Timeout in seconds
     * @return array Monitoring results
     */
    public function monitor_cache_object_states($timeout = null) {
        $timeout = $timeout ?? $this->config['health_check_interval'];
        $monitoring_results = [
            'monitored_cache_objects' => 0,
            'healthy_cache_objects' => 0,
            'unhealthy_cache_objects' => 0,
            'cache_objects_requiring_cleanup' => 0,
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true) / (1024 * 1024)
        ];

        try {
            if ($this->cache_object_pool === null) {
                return $monitoring_results;
            }

            $cache_objects = $this->cache_object_pool->get_pool_status()['objects'];

            foreach ($cache_objects as $cache_object_id => $cache_object_data) {
                $cache_object = $cache_object_data['cache_object'] ?? null;
                $last_used = $cache_object_data['last_used'] ?? 0;

                $monitoring_results['monitored_cache_objects']++;

                if ($this->monitor_cache_object_state($cache_object, $last_used)) {
                    $monitoring_results['cache_objects_requiring_cleanup']++;
                } else {
                    $monitoring_results['healthy_cache_objects']++;
                }
            }

        } catch (\Exception $e) {
            error_log('SMO Social: Cache object state monitoring error - ' . $e->getMessage());
            $monitoring_results['unhealthy_cache_objects'] = $monitoring_results['monitored_cache_objects'];
        }

        return $monitoring_results;
    }

    /**
     * Cache object health checking with timeout detection
     *
     * @param int $timeout Timeout in seconds
     * @return array Health check results
     */
    public function check_cache_object_health_with_timeout($timeout = null) {
        $timeout = $timeout ?? $this->config['health_check_interval'];
        $health_results = [
            'checked_cache_objects' => 0,
            'healthy_cache_objects' => 0,
            'unhealthy_cache_objects' => 0,
            'health_checks' => [],
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true) / (1024 * 1024)
        ];

        try {
            if ($this->cache_object_pool === null) {
                return $health_results;
            }

            $cache_objects = $this->cache_object_pool->get_pool_status()['objects'];

            foreach ($cache_objects as $cache_object_id => $cache_object_data) {
                $cache_object = $cache_object_data['cache_object'] ?? null;

                $health_results['checked_cache_objects']++;

                $health_check = $this->check_cache_object_health($cache_object);
                $health_results['health_checks'][$cache_object_id] = $health_check;

                if ($health_check['valid']) {
                    $health_results['healthy_cache_objects']++;
                } else {
                    $health_results['unhealthy_cache_objects']++;
                }
            }

        } catch (\Exception $e) {
            error_log('SMO Social: Cache object health checking error - ' . $e->getMessage());
        }

        return $health_results;
    }

    /**
     * Clean up all cache objects (force cleanup)
     *
     * @return int Number of cache objects cleaned up
     */
    public function cleanup_all_cache_objects() {
        $cleanup_count = 0;

        try {
            if ($this->cache_object_pool === null) {
                return $cleanup_count;
            }

            $cache_objects = $this->cache_object_pool->get_pool_status()['objects'];

            foreach ($cache_objects as $cache_object_id => $cache_object_data) {
                // Use the pool's cleanup method to remove idle objects
                $this->cache_object_pool->cleanup_idle_objects(0); // Cleanup all idle objects
                $cleanup_count++;
            }

            $this->health_stats['cleanup_count'] += $cleanup_count;
            $this->health_stats['last_cleanup_time'] = time();

        } catch (\Exception $e) {
            error_log('SMO Social: Cleanup all cache objects error - ' . $e->getMessage());
        }

        return $cleanup_count;
    }

    /**
     * Get cache object pool statistics with cleanup information
     *
     * @return array Pool statistics with cleanup info
     */
    public function get_cache_object_pool_stats() {
        $stats = [
            'pool_stats' => [],
            'cleanup_stats' => $this->health_stats,
            'config' => $this->config,
            'memory_usage' => memory_get_usage(true) / (1024 * 1024)
        ];

        try {
            if ($this->cache_object_pool !== null) {
                $stats['pool_stats'] = $this->cache_object_pool->get_stats();
            }
        } catch (\Exception $e) {
            error_log('SMO Social: Get cache object pool stats error - ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get cache-specific cleanup statistics
     *
     * @return array Cache cleanup statistics
     */
    public function get_cache_cleanup_stats() {
        return [
            'cache_objects' => $this->health_stats['total_cache_objects'],
            'active_cache_objects' => $this->health_stats['active_cache_objects'],
            'idle_cache_objects' => $this->health_stats['idle_cache_objects'],
            'stale_cache_objects' => $this->health_stats['stale_cache_objects'],
            'cleanup_operations' => $this->health_stats['cleanup_count'],
            'cache_errors' => $this->health_stats['cache_errors'],
            'validation_failures' => $this->health_stats['validation_failures'],
            'memory_usage_mb' => $this->health_stats['memory_usage'],
            'hit_rate' => $this->health_stats['hit_rate'],
            'miss_rate' => $this->health_stats['miss_rate'],
            'last_cleanup_time' => $this->health_stats['last_cleanup_time']
        ];
    }

    /**
     * Update cache statistics based on cache operations
     *
     * @param bool $hit Whether the cache operation was a hit
     */
    public function update_cache_statistics($hit) {
        if ($hit) {
            $this->health_stats['hit_rate'] = ($this->health_stats['hit_rate'] * 10 + 1) / 11;
            $this->health_stats['miss_rate'] = ($this->health_stats['miss_rate'] * 10 + 0) / 11;
        } else {
            $this->health_stats['hit_rate'] = ($this->health_stats['hit_rate'] * 10 + 0) / 11;
            $this->health_stats['miss_rate'] = ($this->health_stats['miss_rate'] * 10 + 1) / 11;
        }
    }

    /**
     * Get cache performance metrics
     *
     * @return array Cache performance metrics
     */
    public function get_cache_performance_metrics() {
        return [
            'hit_rate' => $this->health_stats['hit_rate'],
            'miss_rate' => $this->health_stats['miss_rate'],
            'efficiency_ratio' => $this->health_stats['hit_rate'] / max(0.01, $this->health_stats['miss_rate']),
            'memory_efficiency' => $this->health_stats['memory_usage'] / max(1, $this->health_stats['total_cache_objects'])
        ];
    }
}