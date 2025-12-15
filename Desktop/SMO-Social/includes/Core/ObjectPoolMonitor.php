<?php
/**
 * Object Pool Monitor for SMO Social
 *
 * Implements monitoring and cleanup mechanisms for all object pools
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class ObjectPoolMonitor {
    /**
     * @var ObjectPoolMonitor|null Singleton instance
     */
    private static $instance = null;

    /**
     * @var array Pool instances
     */
    private $pools = [];

    /**
     * @var array Monitoring configuration
     */
    private $config = [];

    /**
     * @var array Statistics history
     */
    private $stats_history = [];

    /**
     * @var int Last cleanup timestamp
     */
    private $last_cleanup = 0;

    /**
     * ObjectPoolMonitor constructor (private for singleton)
     */
    private function __construct() {
        $this->config = ObjectPoolConfig::get_monitoring_config();
        $this->last_cleanup = time();
        $this->initialize_pools();
        $this->setup_hooks();
    }

    /**
     * Get singleton instance
     *
     * @return ObjectPoolMonitor
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize all pool instances
     */
    private function initialize_pools() {
        // Initialize database connection pool
        $db_pool_config = ObjectPoolConfig::get_pool_config('database_pool');
        if ($db_pool_config['enabled']) {
            $this->pools['database'] = new DatabaseConnectionPool([], $db_pool_config['max_pool_size']);
        }

        // Initialize AI cache object pool
        $ai_cache_config = ObjectPoolConfig::get_pool_config('ai_cache_pool');
        if ($ai_cache_config['enabled']) {
            $this->pools['ai_cache'] = new CacheObjectPool($ai_cache_config['max_pool_size'], $ai_cache_config['object_timeout']);
        }

        // Initialize core cache object pool
        $core_cache_config = ObjectPoolConfig::get_pool_config('core_cache_pool');
        if ($core_cache_config['enabled']) {
            $this->pools['core_cache'] = new CacheObjectPool($core_cache_config['max_pool_size'], $core_cache_config['object_timeout']);
        }

        // Initialize WebSocket connection pool
        $websocket_config = ObjectPoolConfig::get_pool_config('websocket_pool');
        if ($websocket_config['enabled']) {
            $ws_config = [
                'host' => '127.0.0.1',
                'port' => 8080,
                'timeout' => $websocket_config['connection_timeout']
            ];
            $this->pools['websocket'] = new \SMO_Social\WebSocket\WebSocketConnectionPool($ws_config, $websocket_config['max_pool_size']);
        }
    }

    /**
     * Setup WordPress hooks for monitoring
     */
    private function setup_hooks() {
        // Schedule regular cleanup
        add_action('smo_pool_cleanup', [$this, 'perform_cleanup']);

        // Schedule stats collection
        add_action('smo_pool_stats_collection', [$this, 'collect_stats']);

        // Initialize cron jobs if not already scheduled
        if (!wp_next_scheduled('smo_pool_cleanup')) {
            wp_schedule_event(time(), 'every_5_minutes', 'smo_pool_cleanup');
        }

        if (!wp_next_scheduled('smo_pool_stats_collection')) {
            wp_schedule_event(time(), 'every_minute', 'smo_pool_stats_collection');
        }

        // Add cleanup on plugin deactivation
        register_deactivation_hook(__FILE__, [$this, 'cleanup_on_deactivation']);

        // Register with MemoryMonitor if available
        $this->register_with_memory_monitor();
    }

    /**
     * Perform cleanup of all pools
     */
    public function perform_cleanup() {
        $current_time = time();
        $cleanup_interval = $this->config['cleanup_interval'] ?? 300;

        // Only perform cleanup if interval has passed
        if (($current_time - $this->last_cleanup) >= $cleanup_interval) {
            $this->last_cleanup = $current_time;

            foreach ($this->pools as $pool_name => $pool) {
                try {
                    if (method_exists($pool, 'cleanup_idle_connections')) {
                        $pool->cleanup_idle_connections();
                    } elseif (method_exists($pool, 'cleanup_idle_objects')) {
                        $pool->cleanup_idle_objects();
                    }

                    if ($this->config['stats_logging'] ?? true) {
                        error_log("SMO Social: Cleanup performed for $pool_name pool");
                    }
                } catch (\Exception $e) {
                    error_log("SMO Social: Error cleaning up $pool_name pool: " . $e->getMessage());
                }
            }

            // Log memory usage if enabled
            if ($this->config['memory_tracking'] ?? true) {
                $this->log_memory_usage();
            }
        }
    }

    /**
     * Collect statistics from all pools
     */
    public function collect_stats() {
        $stats = [
            'timestamp' => time(),
            'pools' => []
        ];

        foreach ($this->pools as $pool_name => $pool) {
            try {
                if (method_exists($pool, 'get_stats')) {
                    $stats['pools'][$pool_name] = $pool->get_stats();
                }

                if (method_exists($pool, 'get_memory_usage')) {
                    $stats['pools'][$pool_name]['memory_usage'] = $pool->get_memory_usage();
                }
            } catch (\Exception $e) {
                error_log("SMO Social: Error collecting stats for $pool_name pool: " . $e->getMessage());
            }
        }

        // Store stats in history (keep last 10 entries)
        $this->stats_history[] = $stats;
        if (count($this->stats_history) > 10) {
            array_shift($this->stats_history);
        }

        // Log stats if enabled
        if ($this->config['stats_logging'] ?? true) {
            $this->log_stats($stats);
        }
    }

    /**
     * Log statistics to error log
     *
     * @param array $stats Statistics to log
     */
    private function log_stats($stats) {
        $log_message = "SMO Social Pool Stats: ";

        foreach ($stats['pools'] as $pool_name => $pool_stats) {
            $hit_rate = $pool_stats['hit_rate'] ?? 0;
            $current_size = $pool_stats['current_pool_size'] ?? 0;
            $max_size = $pool_stats['max_pool_size'] ?? 0;

            $log_message .= "$pool_name(HR:{$hit_rate}%, Size:$current_size/$max_size) ";
        }

        error_log(rtrim($log_message));
    }

    /**
     * Log memory usage statistics
     */
    private function log_memory_usage() {
        $total_memory = 0;
        $memory_details = [];

        foreach ($this->pools as $pool_name => $pool) {
            try {
                if (method_exists($pool, 'get_memory_usage')) {
                    $memory_usage = $pool->get_memory_usage();
                    $current_usage = $memory_usage['current_usage'] ?? 0;
                    $total_memory += $current_usage;
                    $memory_details[] = "$pool_name:{$current_usage}bytes";
                }
            } catch (\Exception $e) {
                error_log("SMO Social: Error getting memory usage for $pool_name pool: " . $e->getMessage());
            }
        }

        error_log("SMO Social Memory Usage: Total:" . $total_memory . "bytes (" . implode(', ', $memory_details) . ")");
    }

    /**
     * Get current statistics for all pools
     *
     * @return array Current pool statistics
     */
    public function get_current_stats() {
        $current_stats = [
            'timestamp' => time(),
            'pools' => []
        ];

        foreach ($this->pools as $pool_name => $pool) {
            try {
                if (method_exists($pool, 'get_stats')) {
                    $current_stats['pools'][$pool_name] = $pool->get_stats();
                }

                if (method_exists($pool, 'get_memory_usage')) {
                    $current_stats['pools'][$pool_name]['memory_usage'] = $pool->get_memory_usage();
                }

                if (method_exists($pool, 'get_pool_status')) {
                    $current_stats['pools'][$pool_name]['status'] = $pool->get_pool_status();
                }
            } catch (\Exception $e) {
                $current_stats['pools'][$pool_name]['error'] = $e->getMessage();
            }
        }

        return $current_stats;
    }

    /**
     * Get statistics history
     *
     * @return array Statistics history
     */
    public function get_stats_history() {
        return $this->stats_history;
    }

    /**
     * Get a specific pool instance
     *
     * @param string $pool_name Pool name
     * @return mixed|null Pool instance
     */
    public function get_pool($pool_name) {
        return $this->pools[$pool_name] ?? null;
    }

    /**
     * Clear all pool statistics
     */
    public function clear_stats() {
        $this->stats_history = [];
        foreach ($this->pools as $pool) {
            if (method_exists($pool, 'reset_stats')) {
                $pool->reset_stats();
            }
        }
    }

    /**
     * Perform cleanup on plugin deactivation
     */
    public function cleanup_on_deactivation() {
        foreach ($this->pools as $pool_name => $pool) {
            try {
                if (method_exists($pool, 'clear_pool')) {
                    $pool->clear_pool();
                }
            } catch (\Exception $e) {
                error_log("SMO Social: Error clearing $pool_name pool on deactivation: " . $e->getMessage());
            }
        }

        // Clear cron jobs
        wp_clear_scheduled_hook('smo_pool_cleanup');
        wp_clear_scheduled_hook('smo_pool_stats_collection');
    }

    /**
     * Get overall system health status
     *
     * @return array System health status
     */
    public function get_system_health() {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'memory_usage' => 0,
            'hit_rates' => [],
            'pool_utilization' => []
        ];

        $total_memory = 0;

        foreach ($this->pools as $pool_name => $pool) {
            try {
                if (method_exists($pool, 'get_stats')) {
                    $stats = $pool->get_stats();
                    $hit_rate = $stats['hit_rate'] ?? 0;
                    $current_size = $stats['current_pool_size'] ?? 0;
                    $max_size = $stats['max_pool_size'] ?? 1;

                    $health['hit_rates'][$pool_name] = $hit_rate;
                    $health['pool_utilization'][$pool_name] = $max_size > 0 ? ($current_size / $max_size) * 100 : 0;

                    // Check for potential issues
                    if ($hit_rate < 0.3) {
                        $health['issues'][] = "Low hit rate for $pool_name pool: " . round($hit_rate * 100, 1) . "%";
                    }

                    if (($current_size / $max_size) > 0.9) {
                        $health['issues'][] = "High utilization for $pool_name pool: " . round(($current_size / $max_size) * 100, 1) . "%";
                    }
                }

                if (method_exists($pool, 'get_memory_usage')) {
                    $memory_usage = $pool->get_memory_usage();
                    $total_memory += $memory_usage['current_usage'] ?? 0;
                }
            } catch (\Exception $e) {
                $health['issues'][] = "Error getting stats for $pool_name pool: " . $e->getMessage();
            }
        }

        $health['memory_usage'] = $total_memory;

        if ($total_memory > 100000000) { // > 100MB
            $health['issues'][] = "High memory usage: " . round($total_memory / 1048576, 1) . "MB";
        }

        if (!empty($health['issues'])) {
            $health['status'] = 'warning';
            if (count($health['issues']) > 2) {
                $health['status'] = 'critical';
            }
        }

        return $health;
    }

    /**
     * Get pool performance metrics
     *
     * @return array Performance metrics
     */
    public function get_performance_metrics() {
        $metrics = [
            'overall_hit_rate' => 0,
            'total_connections' => 0,
            'total_objects' => 0,
            'memory_efficiency' => 0,
            'pool_details' => []
        ];

        $total_hits = 0;
        $total_requests = 0;
        $total_memory = 0;
        $total_objects = 0;

        foreach ($this->pools as $pool_name => $pool) {
            try {
                if (method_exists($pool, 'get_stats')) {
                    $stats = $pool->get_stats();
                    $pool_metrics = [
                        'hit_rate' => $stats['hit_rate'] ?? 0,
                        'current_size' => $stats['current_pool_size'] ?? 0,
                        'max_size' => $stats['max_pool_size'] ?? 0,
                        'connections_created' => $stats['connections_created'] ?? $stats['objects_created'] ?? 0,
                        'connections_reused' => $stats['connections_reused'] ?? $stats['objects_reused'] ?? 0
                    ];

                    $total_hits += ($stats['connections_reused'] ?? $stats['objects_reused'] ?? 0);
                    $total_requests += ($stats['total_requests'] ?? 0);
                    $total_objects += ($stats['current_pool_size'] ?? 0);

                    $metrics['pool_details'][$pool_name] = $pool_metrics;
                }

                if (method_exists($pool, 'get_memory_usage')) {
                    $memory_usage = $pool->get_memory_usage();
                    $total_memory += ($memory_usage['current_usage'] ?? 0);
                }
            } catch (\Exception $e) {
                error_log("SMO Social: Error getting metrics for $pool_name pool: " . $e->getMessage());
            }
        }

        if ($total_requests > 0) {
            $metrics['overall_hit_rate'] = $total_requests > 0 ? ($total_hits / $total_requests) : 0;
        }

        if ($total_objects > 0 && $total_memory > 0) {
            $metrics['memory_efficiency'] = $total_memory / $total_objects;
        }

        $metrics['total_connections'] = $total_objects;

        return $metrics;
    }

    /**
     * Update monitoring configuration
     *
     * @param array $new_config New monitoring configuration
     */
    public function update_monitoring_config(array $new_config) {
        $this->config = array_merge($this->config, $new_config);

        // Validate the config using the same rules as ObjectPoolConfig
        $validated_config = [];
        foreach ($new_config as $key => $value) {
            switch ($key) {
                case 'cleanup_interval':
                    if (is_numeric($value)) {
                        $validated_config[$key] = max(60, min(3600, intval($value)));
                    }
                    break;

                case 'stats_logging':
                case 'memory_tracking':
                    $validated_config[$key] = boolval($value);
                    break;
            }
        }

        $this->config = array_merge($this->config, $validated_config);

        // Update the config in database
        ObjectPoolConfig::save_monitoring_config($this->config);
    }

    /**
     * Get current monitoring configuration
     *
     * @return array Current monitoring configuration
     */
    public function get_monitoring_config() {
        return $this->config;
    }

    /**
     * Perform immediate cleanup of all pools
     */
    public function force_cleanup() {
        $this->last_cleanup = 0; // Force cleanup on next run
        $this->perform_cleanup();
    }

    /**
     * Register with MemoryMonitor for integrated monitoring
     */
    private function register_with_memory_monitor() {
        if (class_exists('\SMO_Social\Core\MemoryMonitor')) {
            try {
                $memory_monitor = \SMO_Social\Core\MemoryMonitor::get_instance();
                // The MemoryMonitor already integrates with ObjectPoolMonitor
                // This method ensures the connection is established
                if ($this->config['stats_logging'] ?? true) {
                    error_log("SMO Social: ObjectPoolMonitor registered with MemoryMonitor");
                }
            } catch (\Exception $e) {
                error_log("SMO Social: Failed to register ObjectPoolMonitor with MemoryMonitor: " . $e->getMessage());
            }
        }
    }

    /**
     * Get pool utilization report
     *
     * @return array Pool utilization report
     */
    public function get_utilization_report() {
        $report = [
            'timestamp' => time(),
            'pools' => []
        ];

        foreach ($this->pools as $pool_name => $pool) {
            try {
                $pool_report = [
                    'utilization' => 0,
                    'hit_rate' => 0,
                    'memory_usage' => 0,
                    'efficiency_score' => 0
                ];

                if (method_exists($pool, 'get_stats')) {
                    $stats = $pool->get_stats();
                    $current_size = $stats['current_pool_size'] ?? 0;
                    $max_size = $stats['max_pool_size'] ?? 1;
                    $hit_rate = $stats['hit_rate'] ?? 0;

                    $pool_report['utilization'] = $max_size > 0 ? ($current_size / $max_size) * 100 : 0;
                    $pool_report['hit_rate'] = $hit_rate;
                }

                if (method_exists($pool, 'get_memory_usage')) {
                    $memory_usage = $pool->get_memory_usage();
                    $pool_report['memory_usage'] = $memory_usage['current_usage'] ?? 0;

                    // Simple efficiency score: hit_rate * (1 - utilization)
                    $pool_report['efficiency_score'] = $pool_report['hit_rate'] * (1 - ($pool_report['utilization'] / 100));
                }

                $report['pools'][$pool_name] = $pool_report;
            } catch (\Exception $e) {
                error_log("SMO Social: Error generating report for $pool_name pool: " . $e->getMessage());
            }
        }

        return $report;
    }
}