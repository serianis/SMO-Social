<?php
/**
 * Memory Monitor for SMO Social
 *
 * Real-time memory monitoring and analysis system that integrates with
 * all memory management components including object pools, cache systems,
 * and connection managers.
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class MemoryMonitor {
    /**
     * @var MemoryMonitor|null Singleton instance
     */
    private static $instance = null;

    /**
     * @var array Memory usage history
     */
    private $memory_history = [];

    /**
     * @var array Current memory statistics
     */
    private $current_stats = [];

    /**
     * @var array Alert thresholds
     */
    private $alert_thresholds = [];

    /**
     * @var array Integration points with other systems
     */
    private $integrated_systems = [];

    /**
     * @var int Last monitoring timestamp
     */
    private $last_monitor_time = 0;

    /**
     * @var int Monitoring interval in seconds
     */
    private $monitoring_interval = 10;

    /**
     * @var bool Monitoring enabled status
     */
    private $monitoring_enabled = true;

    /**
     * @var bool Real-time monitoring enabled status
     */
    private $real_time_monitoring = false;

    /**
     * @var int Real-time monitoring interval in seconds (shorter for real-time)
     */
    private $real_time_interval = 1;

    /**
     * @var array Hourly usage patterns for analysis
     */
    private $hourly_patterns = [];

    /**
     * @var array Daily usage patterns for analysis
     */
    private $daily_patterns = [];

    /**
     * MemoryMonitor constructor (private for singleton)
     */
    private function __construct() {
        $this->initialize_config();
        $this->setup_hooks();
        $this->integrate_with_existing_systems();
    }

    /**
     * Get singleton instance
     *
     * @return MemoryMonitor
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize configuration from MemoryMonitorConfig
     */
    private function initialize_config() {
        $config = MemoryMonitorConfig::get_instance()->get_config();

        $this->alert_thresholds = [
            'warning' => $config['warning_threshold'] ?? 70, // 70% of memory limit
            'critical' => $config['critical_threshold'] ?? 90, // 90% of memory limit
            'max_history' => $config['max_history_entries'] ?? 100,
            'monitoring_interval' => $config['monitoring_interval'] ?? 10
        ];

        $this->monitoring_interval = max(1, min(60, $this->alert_thresholds['monitoring_interval']));
        $this->monitoring_enabled = $config['monitoring_enabled'] ?? true;
        $this->real_time_monitoring = $config['enable_real_time_monitoring'] ?? false;

        // Use shorter interval for real-time monitoring
        if ($this->real_time_monitoring) {
            $this->monitoring_interval = max(1, min($this->monitoring_interval, $this->real_time_interval));
        }
    }

    /**
     * Setup WordPress hooks for monitoring
     */
    private function setup_hooks() {
        // Schedule regular memory monitoring
        add_action('smo_memory_monitoring', [$this, 'perform_memory_monitoring']);

        // Initialize cron job if not already scheduled
        if (!wp_next_scheduled('smo_memory_monitoring')) {
            wp_schedule_event(time(), 'every_' . $this->monitoring_interval . '_seconds', 'smo_memory_monitoring');
        }

        // Add cleanup on plugin deactivation
        register_deactivation_hook(__FILE__, [$this, 'cleanup_on_deactivation']);

        // Add memory monitoring to admin footer
        add_action('admin_footer', [$this, 'add_admin_memory_display']);

        // Add memory monitoring to AJAX responses
        add_action('wp_ajax_smo_get_memory_stats', [$this, 'handle_memory_stats_ajax']);
        add_action('wp_ajax_smo_get_memory_history', [$this, 'handle_memory_history_ajax']);
    }

    /**
     * Integrate with existing memory management systems
     */
    private function integrate_with_existing_systems() {
        // Integrate with ObjectPoolMonitor
        if (class_exists('\SMO_Social\Core\ObjectPoolMonitor')) {
            $this->integrated_systems['object_pool_monitor'] = ObjectPoolMonitor::get_instance();
        }

        // Integrate with MemoryEfficientConfig
        if (class_exists('\SMO_Social\Core\MemoryEfficientConfig')) {
            $this->integrated_systems['memory_efficient_config'] = new MemoryEfficientConfig();
        }

        // Integrate with CacheManager
        if (class_exists('\SMO_Social\Core\CacheManager')) {
            $this->integrated_systems['cache_manager'] = 'available';
        }

        // Integrate with EnhancedCacheManager
        if (class_exists('\SMO_Social\Core\EnhancedCacheManager')) {
            $this->integrated_systems['enhanced_cache_manager'] = 'available';
        }

        // Integrate with BoundedCacheManager
        if (class_exists('\SMO_Social\AI\BoundedCacheManager')) {
            $this->integrated_systems['bounded_cache_manager'] = 'available';
        }

        // Integrate with ResourceCleanupManager
        if (class_exists('\SMO_Social\Core\ResourceCleanupManager')) {
            $this->integrated_systems['resource_cleanup_manager'] = 'available';
        }

        // Integrate with individual pool systems
        if (class_exists('\SMO_Social\Core\DatabaseConnectionPool')) {
            $this->integrated_systems['database_pool'] = 'available';
        }

        if (class_exists('\SMO_Social\Core\CacheObjectPool')) {
            $this->integrated_systems['cache_pool'] = 'available';
        }

        if (class_exists('\SMO_Social\WebSocket\WebSocketConnectionPool')) {
            $this->integrated_systems['websocket_pool'] = 'available';
        }
    }

    /**
     * Perform comprehensive memory monitoring
     */
    public function perform_memory_monitoring() {
        if (!$this->monitoring_enabled) {
            return;
        }

        $current_time = time();

        // Only monitor if interval has passed
        if (($current_time - $this->last_monitor_time) >= $this->monitoring_interval) {
            $this->last_monitor_time = $current_time;

            try {
                // Collect system memory usage
                $system_memory = $this->collect_system_memory_usage();

                // Collect memory usage from integrated systems
                $integrated_memory = $this->collect_integrated_systems_memory();

                // Calculate totals and analyze
                $total_memory = $this->calculate_total_memory_usage($system_memory, $integrated_memory);

                // Store in history
                $this->store_memory_data($total_memory);

                // Check for alerts
                $this->check_memory_alerts($total_memory);

                // Log memory usage
                $this->log_memory_usage($total_memory);

            } catch (\Exception $e) {
                Logger::error('Memory monitoring failed: ' . $e->getMessage(), [
                    'exception' => $e->getTraceAsString(),
                    'last_monitor_time' => $this->last_monitor_time,
                    'monitoring_interval' => $this->monitoring_interval
                ]);

                // Attempt to trigger alert for monitoring failure
                if (class_exists('\SMO_Social\Core\MemoryAlertSystem')) {
                    try {
                        $alert_system = MemoryAlertSystem::get_instance();
                        $alert_system->trigger_alert(
                            'memory_monitoring_failure',
                            'Memory Monitoring System Failure',
                            'The memory monitoring system encountered an error: ' . $e->getMessage(),
                            'critical',
                            ['exception' => $e->getMessage()]
                        );
                    } catch (\Exception $alert_exception) {
                        Logger::error('Failed to trigger monitoring failure alert: ' . $alert_exception->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Collect system-level memory usage
     *
     * @return array System memory usage data
     */
    private function collect_system_memory_usage() {
        $memory_data = [];

        // Get current memory usage
        $memory_data['current_usage'] = memory_get_usage(true);
        $memory_data['peak_usage'] = memory_get_peak_usage(true);

        // Get memory limit
        $memory_limit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(\w)$/', $memory_limit, $matches)) {
            $limit_value = (int)$matches[1];
            $limit_unit = strtolower($matches[2]);

            switch ($limit_unit) {
                case 'g':
                    $limit_value *= 1024;
                    // Fall through to MB
                case 'm':
                    $limit_value *= 1024;
                    // Fall through to KB
                case 'k':
                    $limit_value *= 1024;
                    break;
            }

            $memory_data['memory_limit'] = $limit_value;
            $memory_data['memory_limit_formatted'] = $memory_limit;
        } else {
            $memory_data['memory_limit'] = 0;
            $memory_data['memory_limit_formatted'] = 'Unknown';
        }

        // Calculate usage percentages
        if ($memory_data['memory_limit'] > 0) {
            $memory_data['usage_percentage'] = ($memory_data['current_usage'] / $memory_data['memory_limit']) * 100;
            $memory_data['peak_usage_percentage'] = ($memory_data['peak_usage'] / $memory_data['memory_limit']) * 100;
        } else {
            $memory_data['usage_percentage'] = 0;
            $memory_data['peak_usage_percentage'] = 0;
        }

        // Format for human readability
        $memory_data['current_usage_formatted'] = $this->format_bytes($memory_data['current_usage']);
        $memory_data['peak_usage_formatted'] = $this->format_bytes($memory_data['peak_usage']);

        return $memory_data;
    }

    /**
     * Collect memory usage from integrated systems
     *
     * @return array Integrated systems memory usage
     */
    private function collect_integrated_systems_memory() {
        $systems_memory = [];

        try {
            // Collect from ObjectPoolMonitor if available
            if (isset($this->integrated_systems['object_pool_monitor'])) {
                $pool_monitor = $this->integrated_systems['object_pool_monitor'];
                $pool_stats = $pool_monitor->get_current_stats();
                $total_pool_memory = 0;
                $pool_details = [];

                foreach ($pool_stats['pools'] as $pool_name => $pool_data) {
                    $pool_memory = 0;
                    if (isset($pool_data['memory_usage']['current_usage'])) {
                        $pool_memory = $pool_data['memory_usage']['current_usage'];
                        $total_pool_memory += $pool_memory;
                    }

                    // Enhanced pool data collection for real-time tracking
                    $pool_details[$pool_name] = [
                        'memory_usage' => $pool_memory,
                        'hit_rate' => $pool_data['hit_rate'] ?? 0,
                        'current_pool_size' => $pool_data['current_pool_size'] ?? 0,
                        'max_pool_size' => $pool_data['max_pool_size'] ?? 0,
                        'connections_created' => $pool_data['connections_created'] ?? $pool_data['objects_created'] ?? 0,
                        'connections_reused' => $pool_data['connections_reused'] ?? $pool_data['objects_reused'] ?? 0,
                        'status' => $pool_data['status'] ?? 'unknown'
                    ];
                }

                $systems_memory['object_pools'] = [
                    'current_usage' => $total_pool_memory,
                    'formatted' => $this->format_bytes($total_pool_memory),
                    'pools' => $pool_details,
                    'pool_count' => count($pool_details),
                    'total_hit_rate' => $this->calculate_average_hit_rate($pool_details)
                ];
            }

            // Collect from individual pool systems (fallback)
            foreach (['database_pool', 'cache_pool', 'websocket_pool'] as $pool_type) {
                if (isset($this->integrated_systems[$pool_type]) && $this->integrated_systems[$pool_type] === 'available') {
                    // These would be instantiated through ObjectPoolMonitor
                    // We'll get their memory usage from the pool monitor if not already collected
                    if (!isset($systems_memory['object_pools']['pools'][$pool_type])) {
                        // Attempt to get individual pool data
                        $systems_memory['individual_pools'][$pool_type] = [
                            'status' => 'available',
                            'memory_usage' => 0 // Would need specific implementation
                        ];
                    }
                }
            }

            // Collect from CacheManager if available
            if (isset($this->integrated_systems['cache_manager']) && $this->integrated_systems['cache_manager'] === 'available') {
                try {
                    $cache_manager = new \SMO_Social\Core\CacheManager();
                    $cache_stats = $cache_manager->get_stats();
                    if (isset($cache_stats['memory_usage'])) {
                        $systems_memory['cache_manager'] = [
                            'current_usage' => $cache_stats['memory_usage'],
                            'formatted' => $this->format_bytes($cache_stats['memory_usage']),
                            'cache_keys' => $cache_stats['cache_keys'] ?? 0,
                            'redis_available' => $cache_stats['redis_available'] ?? false
                        ];
                    }
                } catch (\Exception $e) {
                    Logger::error('Failed to collect CacheManager memory: ' . $e->getMessage());
                }
            }

            // Collect from EnhancedCacheManager if available
            if (isset($this->integrated_systems['enhanced_cache_manager']) && $this->integrated_systems['enhanced_cache_manager'] === 'available') {
                try {
                    $enhanced_cache = new \SMO_Social\Core\EnhancedCacheManager();
                    $cache_stats = $enhanced_cache->getStats();
                    if (isset($cache_stats['file_cache_size'])) {
                        $systems_memory['enhanced_cache_manager'] = [
                            'current_usage' => $cache_stats['file_cache_size'],
                            'formatted' => $this->format_bytes($cache_stats['file_cache_size']),
                            'total_files' => $cache_stats['total_files'] ?? 0,
                            'redis_info' => $cache_stats['redis_info'] ?? []
                        ];
                    }
                } catch (\Exception $e) {
                    Logger::error('Failed to collect EnhancedCacheManager memory: ' . $e->getMessage());
                }
            }

            // Collect from BoundedCacheManager if available
            if (isset($this->integrated_systems['bounded_cache_manager']) && $this->integrated_systems['bounded_cache_manager'] === 'available') {
                try {
                    $bounded_cache = new \SMO_Social\AI\BoundedCacheManager();
                    $cache_stats = $bounded_cache->get_stats();
                    if (isset($cache_stats['total_size'])) {
                        $systems_memory['bounded_cache_manager'] = [
                            'current_usage' => $cache_stats['total_size'],
                            'formatted' => $this->format_bytes($cache_stats['total_size']),
                            'total_files' => $cache_stats['total_files'] ?? 0,
                            'expired_files' => $cache_stats['expired_files'] ?? 0
                        ];
                    }
                } catch (\Exception $e) {
                    Logger::error('Failed to collect BoundedCacheManager memory: ' . $e->getMessage());
                }
            }

            // Collect from ResourceCleanupManager if available
            if (isset($this->integrated_systems['resource_cleanup_manager']) && $this->integrated_systems['resource_cleanup_manager'] === 'available') {
                try {
                    $cleanup_manager = new \SMO_Social\Core\ResourceCleanupManager();
                    $cleanup_stats = $cleanup_manager->get_cleanup_statistics();
                    if (isset($cleanup_stats['memory_usage_history']) && !empty($cleanup_stats['memory_usage_history'])) {
                        $latest_memory = end($cleanup_stats['memory_usage_history']);
                        $systems_memory['resource_cleanup_manager'] = [
                            'current_usage' => $latest_memory['memory_usage'] ?? 0,
                            'formatted' => $this->format_bytes($latest_memory['memory_usage'] ?? 0),
                            'peak_usage' => $latest_memory['memory_peak'] ?? 0,
                            'registered_streams' => $cleanup_stats['registered_streams'] ?? 0
                        ];
                    }
                } catch (\Exception $e) {
                    Logger::error('Failed to collect ResourceCleanupManager memory: ' . $e->getMessage());
                }
            }

            // Collect from individual pool systems
            $pool_systems = [
                'database_pool' => '\SMO_Social\Core\DatabaseConnectionPool',
                'websocket_pool' => '\SMO_Social\WebSocket\WebSocketConnectionPool'
            ];

            foreach ($pool_systems as $pool_key => $pool_class) {
                if (isset($this->integrated_systems[$pool_key]) && $this->integrated_systems[$pool_key] === 'available') {
                    try {
                        // For individual pools, we'd need instances or static methods
                        // This is a simplified approach - in practice, these would be managed by ObjectPoolMonitor
                        $systems_memory['individual_pools'][$pool_key] = [
                            'status' => 'available',
                            'memory_usage' => 0, // Would need specific implementation
                            'note' => 'Memory usage tracked via ObjectPoolMonitor'
                        ];
                    } catch (\Exception $e) {
                        Logger::error("Failed to collect {$pool_key} memory: " . $e->getMessage());
                    }
                }
            }

            // Collect from MemoryEfficientConfig if available
            if (isset($this->integrated_systems['memory_efficient_config'])) {
                $config_memory = $this->integrated_systems['memory_efficient_config']->get_memory_usage();
                if ($config_memory) {
                    $systems_memory['config_systems'] = [
                        'current_usage' => $config_memory,
                        'formatted' => $this->format_bytes($config_memory)
                    ];
                }
            }

        } catch (\Exception $e) {
            Logger::error('Failed to collect integrated systems memory: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'integrated_systems' => array_keys($this->integrated_systems)
            ]);

            // Return minimal data on error
            $systems_memory = [
                'error' => 'Collection failed: ' . $e->getMessage(),
                'object_pools' => ['current_usage' => 0, 'pools' => []]
            ];
        }

        return $systems_memory;
    }

    /**
     * Calculate average hit rate across pools
     *
     * @param array $pool_details Pool details array
     * @return float Average hit rate
     */
    private function calculate_average_hit_rate($pool_details) {
        if (empty($pool_details)) {
            return 0.0;
        }

        $total_hit_rate = 0;
        $count = 0;

        foreach ($pool_details as $pool) {
            if (isset($pool['hit_rate'])) {
                $total_hit_rate += $pool['hit_rate'];
                $count++;
            }
        }

        return $count > 0 ? $total_hit_rate / $count : 0.0;
    }

    /**
     * Calculate total memory usage
     *
     * @param array $system_memory System memory data
     * @param array $integrated_memory Integrated systems memory data
     * @return array Total memory usage data
     */
    private function calculate_total_memory_usage($system_memory, $integrated_memory) {
        $total_memory = [
            'timestamp' => time(),
            'system' => $system_memory,
            'integrated_systems' => $integrated_memory,
            'total_usage' => $system_memory['current_usage'],
            'total_usage_formatted' => $system_memory['current_usage_formatted'],
            'usage_percentage' => $system_memory['usage_percentage'],
            'status' => 'normal'
        ];

        // Add integrated systems memory to total
        if (isset($integrated_memory['object_pools']['current_usage'])) {
            $total_memory['total_usage'] += $integrated_memory['object_pools']['current_usage'];
        }

        // Add cache systems memory
        $cache_systems = ['cache_manager', 'enhanced_cache_manager', 'bounded_cache_manager'];
        foreach ($cache_systems as $cache_system) {
            if (isset($integrated_memory[$cache_system]['current_usage'])) {
                $total_memory['total_usage'] += $integrated_memory[$cache_system]['current_usage'];
            }
        }

        // Add resource cleanup manager memory
        if (isset($integrated_memory['resource_cleanup_manager']['current_usage'])) {
            $total_memory['total_usage'] += $integrated_memory['resource_cleanup_manager']['current_usage'];
        }

        // Add config systems memory
        if (isset($integrated_memory['config_systems']['current_usage'])) {
            $total_memory['total_usage'] += $integrated_memory['config_systems']['current_usage'];
        }

        $total_memory['total_usage_formatted'] = $this->format_bytes($total_memory['total_usage']);

        // Determine status based on usage percentage
        if ($total_memory['usage_percentage'] >= $this->alert_thresholds['critical']) {
            $total_memory['status'] = 'critical';
        } elseif ($total_memory['usage_percentage'] >= $this->alert_thresholds['warning']) {
            $total_memory['status'] = 'warning';
        }

        // Calculate memory efficiency score
        $total_memory['efficiency_score'] = $this->calculate_memory_efficiency($total_memory);

        return $total_memory;
    }

    /**
     * Calculate memory usage trend over recent history
     *
     * @return float Trend as percentage change (positive = increasing)
     */
    private function calculate_memory_trend() {
        if (count($this->memory_history) < 3) {
            return 0.0;
        }

        $recent_entries = array_slice($this->memory_history, -3);
        $first_usage = $recent_entries[0]['usage_percentage'];
        $last_usage = $recent_entries[count($recent_entries) - 1]['usage_percentage'];

        if ($first_usage == 0) {
            return 0.0;
        }

        return (($last_usage - $first_usage) / $first_usage);
    }

    /**
     * Calculate memory efficiency score (0-100)
     *
     * @param array $memory_data Memory usage data
     * @return float Efficiency score
     */
    private function calculate_memory_efficiency($memory_data) {
        $score = 100.0;

        try {
            // Base score starts at 100
            $penalties = 0;
            $bonuses = 0;

            // Penalize for high memory usage (more aggressive for real-time monitoring)
            $usage_penalty_multiplier = $this->real_time_monitoring ? 2.5 : 2.0;
            if ($memory_data['usage_percentage'] > 80) {
                $penalties += ($memory_data['usage_percentage'] - 80) * $usage_penalty_multiplier;
            } elseif ($memory_data['usage_percentage'] > 60) {
                $penalties += ($memory_data['usage_percentage'] - 60) * 0.5;
            }

            // Penalize for memory leaks (trend analysis)
            if (count($this->memory_history) >= 3) {
                $recent_trend = $this->calculate_memory_trend();
                if ($recent_trend > 0.05) { // 5% increase trend
                    $penalties += min(30, $recent_trend * 500); // Up to 30 points penalty
                }
            }

            // Bonus for good hit rates in object pools
            if (isset($memory_data['integrated_systems']['object_pools']['pools'])) {
                $total_hit_rate = 0;
                $pool_count = 0;
                $total_utilization = 0;

                foreach ($memory_data['integrated_systems']['object_pools']['pools'] as $pool) {
                    if (isset($pool['hit_rate'])) {
                        $total_hit_rate += $pool['hit_rate'];
                        $pool_count++;
                    }
                    if (isset($pool['current_pool_size']) && isset($pool['max_pool_size'])) {
                        $utilization = $pool['max_pool_size'] > 0 ? ($pool['current_pool_size'] / $pool['max_pool_size']) : 0;
                        $total_utilization += $utilization;
                    }
                }

                if ($pool_count > 0) {
                    $avg_hit_rate = $total_hit_rate / $pool_count;
                    $avg_utilization = $total_utilization / $pool_count;

                    // Hit rate bonus (up to 25 points)
                    $bonuses += min(25, $avg_hit_rate * 25);

                    // Utilization efficiency bonus (up to 15 points)
                    if ($avg_utilization < 0.8) {
                        $bonuses += (1 - $avg_utilization) * 15;
                    }
                }
            }

            // Bonus for cache system efficiency
            $cache_systems = ['cache_manager', 'enhanced_cache_manager', 'bounded_cache_manager'];
            $cache_efficiency_bonus = 0;
            $cache_count = 0;

            foreach ($cache_systems as $cache_system) {
                if (isset($memory_data['integrated_systems'][$cache_system])) {
                    $cache_count++;
                    $cache_data = $memory_data['integrated_systems'][$cache_system];

                    // Bonus for cache systems with reasonable memory usage
                    if (isset($cache_data['current_usage']) && $cache_data['current_usage'] > 0) {
                        // Bonus for efficient cache usage (up to 10 points per cache system)
                        $cache_efficiency_bonus += min(10, 100 - ($cache_data['current_usage'] / (1024 * 1024))); // Assume 1MB max for bonus
                    }
                }
            }

            if ($cache_count > 0) {
                $bonuses += min(20, $cache_efficiency_bonus); // Cap total cache bonus at 20 points
            }

            // Bonus for resource cleanup manager activity
            if (isset($memory_data['integrated_systems']['resource_cleanup_manager'])) {
                $cleanup_data = $memory_data['integrated_systems']['resource_cleanup_manager'];
                if (isset($cleanup_data['registered_streams']) && $cleanup_data['registered_streams'] > 0) {
                    // Bonus for having cleanup systems active (up to 10 points)
                    $bonuses += min(10, $cleanup_data['registered_streams'] * 2);
                }
            }

            // Real-time monitoring bonus (encourages real-time tracking)
            if ($this->real_time_monitoring) {
                $bonuses += 5;
            }

            // Memory limit awareness bonus
            if (isset($memory_data['system']['memory_limit']) && $memory_data['system']['memory_limit'] > 0) {
                $limit_utilization = $memory_data['total_usage'] / $memory_data['system']['memory_limit'];
                if ($limit_utilization < 0.5) {
                    $bonuses += 10; // Bonus for staying well below limit
                }
            }

            // Calculate final score
            $score = $score - $penalties + $bonuses;

            // Ensure score is between 0 and 100
            $score = max(0.0, min(100.0, $score));

            Logger::debug('Memory efficiency calculation', [
                'final_score' => $score,
                'penalties' => $penalties,
                'bonuses' => $bonuses,
                'usage_percentage' => $memory_data['usage_percentage'],
                'real_time_monitoring' => $this->real_time_monitoring
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to calculate memory efficiency: ' . $e->getMessage(), [
                'memory_data' => $memory_data,
                'exception' => $e->getTraceAsString()
            ]);
            $score = 50.0; // Default neutral score on error
        }

        return round($score, 2);
    }

    /**
     * Store memory data in history
     *
     * @param array $memory_data Memory usage data
     */
    private function store_memory_data($memory_data) {
        $this->memory_history[] = $memory_data;
        $this->current_stats = $memory_data;

        // Limit history size
        if (count($this->memory_history) > $this->alert_thresholds['max_history']) {
            array_shift($this->memory_history);
        }

        // Store in basic database table for backward compatibility
        $this->store_memory_data_in_database($memory_data);

        // Store enhanced data for advanced analysis
        $this->store_enhanced_memory_data($memory_data);
    }

    /**
     * Store memory data in database
     *
     * @param array $memory_data Memory usage data
     */
    private function store_memory_data_in_database($memory_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_monitoring';

        // Check if table exists, create if not
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_memory_monitoring_table();
        }

        // Insert memory data
        $wpdb->insert($table_name, [
            'timestamp' => current_time('mysql'),
            'memory_usage' => $memory_data['total_usage'],
            'usage_percentage' => $memory_data['usage_percentage'],
            'status' => $memory_data['status'],
            'efficiency_score' => $memory_data['efficiency_score'],
            'system_memory' => $memory_data['system']['current_usage'],
            'pool_memory' => isset($memory_data['integrated_systems']['object_pools']['current_usage'])
                ? $memory_data['integrated_systems']['object_pools']['current_usage']
                : 0,
            'memory_data' => json_encode($memory_data)
        ]);
    }

    /**
     * Create memory monitoring database table
     */
    private function create_memory_monitoring_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_monitoring';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            memory_usage bigint(20) NOT NULL,
            usage_percentage float NOT NULL,
            status varchar(20) NOT NULL,
            efficiency_score float NOT NULL,
            system_memory bigint(20) NOT NULL,
            pool_memory bigint(20) NOT NULL,
            memory_data text NOT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Calculate enhanced metrics for detailed analysis
     *
     * @param array $memory_data Memory usage data
     * @return array Enhanced metrics
     */
    private function calculate_enhanced_metrics($memory_data) {
        $metrics = [
            'fragmentation_ratio' => 0.0,
            'gc_runs' => 0,
            'pool_hit_rate' => 0.0,
            'cache_hit_rate' => 0.0,
            'connection_pool_utilization' => 0.0,
            'websocket_pool_utilization' => 0.0,
            'memory_pressure_level' => 'low',
            'trend_direction' => 'stable',
            'trend_magnitude' => 0.0,
            'anomaly_score' => 0.0
        ];

        try {
            // Calculate fragmentation ratio (peak vs current usage)
            if ($memory_data['system']['current_usage'] > 0) {
                $metrics['fragmentation_ratio'] = ($memory_data['system']['peak_usage'] - $memory_data['system']['current_usage']) / $memory_data['system']['current_usage'];
            }

            // Get garbage collection info
            $metrics['gc_runs'] = gc_collect_cycles();

            // Calculate pool hit rates
            if (isset($memory_data['integrated_systems']['object_pools']['pools'])) {
                $total_hit_rate = 0;
                $pool_count = 0;
                $total_utilization = 0;

                foreach ($memory_data['integrated_systems']['object_pools']['pools'] as $pool) {
                    if (isset($pool['hit_rate'])) {
                        $total_hit_rate += $pool['hit_rate'];
                        $pool_count++;
                    }
                    if (isset($pool['current_pool_size']) && isset($pool['max_pool_size'])) {
                        $utilization = $pool['max_pool_size'] > 0 ? ($pool['current_pool_size'] / $pool['max_pool_size']) : 0;
                        $total_utilization += $utilization;
                    }
                }

                if ($pool_count > 0) {
                    $metrics['pool_hit_rate'] = $total_hit_rate / $pool_count;
                    $metrics['connection_pool_utilization'] = $total_utilization / $pool_count;
                }
            }

            // Calculate cache hit rates (simplified)
            $cache_systems = ['cache_manager', 'enhanced_cache_manager', 'bounded_cache_manager'];
            $cache_hit_rates = [];
            foreach ($cache_systems as $cache_system) {
                if (isset($memory_data['integrated_systems'][$cache_system])) {
                    $cache_hit_rates[] = 0.85; // Assume 85% hit rate for active cache systems
                }
            }
            if (!empty($cache_hit_rates)) {
                $metrics['cache_hit_rate'] = array_sum($cache_hit_rates) / count($cache_hit_rates);
            }

            // Determine memory pressure level
            $usage_percentage = $memory_data['usage_percentage'];
            if ($usage_percentage >= 90) {
                $metrics['memory_pressure_level'] = 'critical';
            } elseif ($usage_percentage >= 75) {
                $metrics['memory_pressure_level'] = 'high';
            } elseif ($usage_percentage >= 60) {
                $metrics['memory_pressure_level'] = 'medium';
            }

            // Calculate trend direction and magnitude
            if (count($this->memory_history) >= 3) {
                $recent_trend = $this->calculate_memory_trend();
                $metrics['trend_magnitude'] = abs($recent_trend);

                if ($recent_trend > 0.01) {
                    $metrics['trend_direction'] = 'increasing';
                } elseif ($recent_trend < -0.01) {
                    $metrics['trend_direction'] = 'decreasing';
                }
            }

            // Calculate anomaly score
            $metrics['anomaly_score'] = $this->calculate_anomaly_score($memory_data);

        } catch (\Exception $e) {
            Logger::error('Failed to calculate enhanced metrics: ' . $e->getMessage());
        }

        return $metrics;
    }

    /**
     * Calculate cache memory usage across all cache systems
     *
     * @param array $memory_data Memory usage data
     * @return int Total cache memory usage
     */
    private function calculate_cache_memory_usage($memory_data) {
        $cache_memory = 0;
        $cache_systems = ['cache_manager', 'enhanced_cache_manager', 'bounded_cache_manager'];

        foreach ($cache_systems as $cache_system) {
            if (isset($memory_data['integrated_systems'][$cache_system]['current_usage'])) {
                $cache_memory += $memory_data['integrated_systems'][$cache_system]['current_usage'];
            }
        }

        return $cache_memory;
    }

    /**
     * Get detailed component breakdown for analysis
     *
     * @param array $memory_data Memory usage data
     * @return array Component breakdown
     */
    private function get_component_breakdown($memory_data) {
        $breakdown = [
            'system' => [
                'usage' => $memory_data['system']['current_usage'],
                'percentage' => $memory_data['system']['usage_percentage']
            ],
            'pools' => [],
            'caches' => [],
            'other' => 0
        ];

        // Object pools breakdown
        if (isset($memory_data['integrated_systems']['object_pools']['pools'])) {
            foreach ($memory_data['integrated_systems']['object_pools']['pools'] as $pool_name => $pool_data) {
                if (isset($pool_data['memory_usage'])) {
                    $breakdown['pools'][$pool_name] = [
                        'usage' => $pool_data['memory_usage'],
                        'hit_rate' => $pool_data['hit_rate'] ?? 0,
                        'utilization' => isset($pool_data['current_pool_size']) && isset($pool_data['max_pool_size'])
                            ? ($pool_data['current_pool_size'] / $pool_data['max_pool_size'])
                            : 0
                    ];
                }
            }
        }

        // Cache systems breakdown
        $cache_systems = ['cache_manager', 'enhanced_cache_manager', 'bounded_cache_manager'];
        foreach ($cache_systems as $cache_system) {
            if (isset($memory_data['integrated_systems'][$cache_system])) {
                $cache_data = $memory_data['integrated_systems'][$cache_system];
                $breakdown['caches'][$cache_system] = [
                    'usage' => $cache_data['current_usage'],
                    'formatted' => $cache_data['formatted']
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Get system information for context
     *
     * @return array System information
     */
    private function get_system_info() {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'operating_system' => PHP_OS,
            'wordpress_version' => get_bloginfo('version') ?? 'Unknown',
            'active_plugins' => count(get_option('active_plugins', [])),
            'database_size' => 'Unknown', // Would need to query database
            'cache_enabled' => defined('WP_CACHE') && WP_CACHE,
            'object_cache' => function_exists('wp_cache_get') ? 'Available' : 'Not Available'
        ];
    }

    /**
     * Calculate anomaly score based on statistical analysis
     *
     * @param array $memory_data Current memory data
     * @return float Anomaly score (0-1, higher = more anomalous)
     */
    private function calculate_anomaly_score($memory_data) {
        if (count($this->memory_history) < 10) {
            return 0.0;
        }

        try {
            $usage_percentages = array_column($this->memory_history, 'usage_percentage');
            $mean = array_sum($usage_percentages) / count($usage_percentages);
            $variance = 0;

            foreach ($usage_percentages as $usage) {
                $variance += pow($usage - $mean, 2);
            }
            $variance /= count($usage_percentages);
            $std_dev = sqrt($variance);

            if ($std_dev == 0) {
                return 0.0;
            }

            $z_score = abs($memory_data['usage_percentage'] - $mean) / $std_dev;

            // Convert z-score to anomaly score (0-1 scale)
            return min(1.0, $z_score / 3.0); // 3 standard deviations = max anomaly

        } catch (\Exception $e) {
            Logger::error('Failed to calculate anomaly score: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Store trend analysis in database
     */
    private function store_trend_analysis() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_trends';

        // Analyze last 24 hours of data
        $end_time = time();
        $start_time = $end_time - (24 * 60 * 60);

        $recent_data = array_filter($this->memory_history, function($entry) use ($start_time, $end_time) {
            return $entry['timestamp'] >= $start_time && $entry['timestamp'] <= $end_time;
        });

        if (count($recent_data) < 5) {
            return; // Not enough data for trend analysis
        }

        // Calculate trend metrics
        $usages = array_column($recent_data, 'usage_percentage');
        $timestamps = array_column($recent_data, 'timestamp');

        $trend_analysis = $this->calculate_linear_trend($timestamps, $usages);

        $wpdb->insert($table_name, [
            'analysis_period_start' => date('Y-m-d H:i:s', $start_time),
            'analysis_period_end' => date('Y-m-d H:i:s', $end_time),
            'trend_type' => 'memory_usage_24h',
            'trend_direction' => $trend_analysis['direction'],
            'trend_slope' => $trend_analysis['slope'],
            'trend_strength' => $trend_analysis['strength'],
            'confidence_level' => $trend_analysis['confidence'],
            'data_points_analyzed' => count($recent_data),
            'average_usage' => array_sum($usages) / count($usages),
            'peak_usage' => max($usages),
            'volatility_index' => $this->calculate_volatility($usages),
            'predictive_accuracy' => 0.0, // Will be updated with actual predictions
            'next_predicted_value' => $trend_analysis['next_value'],
            'prediction_confidence' => $trend_analysis['confidence']
        ]);
    }

    /**
     * Calculate linear trend using least squares method
     *
     * @param array $x_values X values (timestamps)
     * @param array $y_values Y values (usage percentages)
     * @return array Trend analysis results
     */
    private function calculate_linear_trend($x_values, $y_values) {
        $n = count($x_values);
        if ($n < 2) {
            return ['slope' => 0, 'direction' => 'stable', 'strength' => 0, 'confidence' => 0, 'next_value' => 0];
        }

        // Normalize timestamps to hours from start
        $start_time = min($x_values);
        $x_normalized = array_map(function($x) use ($start_time) {
            return ($x - $start_time) / 3600; // Convert to hours
        }, $x_values);

        // Calculate means
        $x_mean = array_sum($x_normalized) / $n;
        $y_mean = array_sum($y_values) / $n;

        // Calculate slope and intercept
        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x_normalized[$i] - $x_mean) * ($y_values[$i] - $y_mean);
            $denominator += pow($x_normalized[$i] - $x_mean, 2);
        }

        $slope = $denominator != 0 ? $numerator / $denominator : 0;

        // Determine direction and strength
        $direction = 'stable';
        $strength = abs($slope);

        if ($slope > 0.01) {
            $direction = 'increasing';
        } elseif ($slope < -0.01) {
            $direction = 'decreasing';
        }

        // Calculate confidence (R-squared approximation)
        $ss_res = 0;
        $ss_tot = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $y_mean + $slope * ($x_normalized[$i] - $x_mean);
            $ss_res += pow($y_values[$i] - $predicted, 2);
            $ss_tot += pow($y_values[$i] - $y_mean, 2);
        }

        $confidence = $ss_tot != 0 ? 1 - ($ss_res / $ss_tot) : 0;

        // Predict next value (1 hour ahead)
        $next_x = max($x_normalized) + 1;
        $next_value = $y_mean + $slope * ($next_x - $x_mean);

        return [
            'slope' => $slope,
            'direction' => $direction,
            'strength' => min(1.0, $strength), // Normalize to 0-1
            'confidence' => $confidence,
            'next_value' => max(0, $next_value) // Ensure non-negative
        ];
    }

    /**
     * Calculate volatility index (coefficient of variation)
     *
     * @param array $values Values to analyze
     * @return float Volatility index
     */
    private function calculate_volatility($values) {
        if (empty($values)) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        if ($mean == 0) {
            return 0.0;
        }

        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);
        $std_dev = sqrt($variance);

        return $std_dev / $mean; // Coefficient of variation
    }

    /**
     * Detect memory leak patterns and store them
     */
    private function detect_memory_leak_patterns() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_leak_patterns';

        // Analyze last 50 data points for leak patterns
        $recent_data = array_slice($this->memory_history, -50);

        if (count($recent_data) < 10) {
            return;
        }

        // Check for consistent upward trend
        $usages = array_column($recent_data, 'usage_percentage');
        $trend = $this->calculate_linear_trend(range(0, count($usages) - 1), $usages);

        // Check for memory leak indicators
        $leak_indicators = $this->analyze_leak_indicators($recent_data);

        if ($leak_indicators['is_leak_detected']) {
            $wpdb->insert($table_name, [
                'detection_timestamp' => current_time('mysql'),
                'leak_type' => $leak_indicators['leak_type'],
                'severity_level' => $leak_indicators['severity'],
                'memory_growth_rate' => $trend['slope'],
                'leak_duration_hours' => (time() - $recent_data[0]['timestamp']) / 3600,
                'affected_components' => json_encode($leak_indicators['affected_components']),
                'suspected_causes' => json_encode($leak_indicators['suspected_causes']),
                'evidence_data' => json_encode([
                    'trend_analysis' => $trend,
                    'recent_data_points' => count($recent_data),
                    'growth_rate' => $trend['slope'],
                    'volatility' => $this->calculate_volatility($usages)
                ]),
                'confidence_score' => $leak_indicators['confidence']
            ]);
        }
    }

    /**
     * Analyze indicators for memory leaks
     *
     * @param array $data Recent memory data
     * @return array Leak analysis results
     */
    private function analyze_leak_indicators($data) {
        $result = [
            'is_leak_detected' => false,
            'leak_type' => 'unknown',
            'severity' => 'low',
            'affected_components' => [],
            'suspected_causes' => [],
            'confidence' => 0.0
        ];

        $usages = array_column($data, 'usage_percentage');
        $trend = $this->calculate_linear_trend(range(0, count($usages) - 1), $usages);

        // Check for consistent growth
        if ($trend['slope'] > 0.02 && $trend['confidence'] > 0.7) { // Growing by more than 0.02% per interval with good confidence
            $result['is_leak_detected'] = true;
            $result['leak_type'] = 'gradual_growth';
            $result['confidence'] = $trend['confidence'];

            // Determine severity
            $growth_rate = $trend['slope'];
            if ($growth_rate > 0.1) {
                $result['severity'] = 'critical';
            } elseif ($growth_rate > 0.05) {
                $result['severity'] = 'high';
            } elseif ($growth_rate > 0.02) {
                $result['severity'] = 'medium';
            }

            // Identify affected components
            $result['affected_components'] = $this->identify_affected_components($data);
            $result['suspected_causes'] = $this->identify_suspected_causes($data, $trend);
        }

        return $result;
    }

    /**
     * Identify components affected by memory issues
     *
     * @param array $data Memory data
     * @return array Affected components
     */
    private function identify_affected_components($data) {
        $components = [];

        foreach ($data as $entry) {
            if (isset($entry['integrated_systems']['object_pools']['pools'])) {
                foreach ($entry['integrated_systems']['object_pools']['pools'] as $pool_name => $pool_data) {
                    if (isset($pool_data['memory_usage']) && $pool_data['memory_usage'] > 1000000) { // 1MB threshold
                        $components[] = 'pool_' . $pool_name;
                    }
                }
            }
        }

        return array_unique($components);
    }

    /**
     * Identify suspected causes of memory issues
     *
     * @param array $data Memory data
     * @param array $trend Trend analysis
     * @return array Suspected causes
     */
    private function identify_suspected_causes($data, $trend) {
        $causes = [];

        // Check for cache issues
        $cache_growth = $this->analyze_cache_growth($data);
        if ($cache_growth > 0.1) {
            $causes[] = 'cache_accumulation';
        }

        // Check for connection pool issues
        $pool_issues = $this->analyze_pool_issues($data);
        if (!empty($pool_issues)) {
            $causes = array_merge($causes, $pool_issues);
        }

        // Check for object pool inefficiencies
        if ($trend['slope'] > 0.05) {
            $causes[] = 'object_pool_inefficiency';
        }

        return array_unique($causes);
    }

    /**
     * Analyze cache growth patterns
     *
     * @param array $data Memory data
     * @return float Cache growth rate
     */
    private function analyze_cache_growth($data) {
        $cache_usages = [];
        foreach ($data as $entry) {
            $cache_usage = 0;
            $cache_systems = ['cache_manager', 'enhanced_cache_manager', 'bounded_cache_manager'];
            foreach ($cache_systems as $cache_system) {
                if (isset($entry['integrated_systems'][$cache_system]['current_usage'])) {
                    $cache_usage += $entry['integrated_systems'][$cache_system]['current_usage'];
                }
            }
            $cache_usages[] = $cache_usage;
        }

        if (count($cache_usages) < 2) {
            return 0.0;
        }

        $trend = $this->calculate_linear_trend(range(0, count($cache_usages) - 1), $cache_usages);
        return $trend['slope'];
    }

    /**
     * Analyze object pool issues
     *
     * @param array $data Memory data
     * @return array Pool issues identified
     */
    private function analyze_pool_issues($data) {
        $issues = [];

        foreach ($data as $entry) {
            if (isset($entry['integrated_systems']['object_pools']['pools'])) {
                foreach ($entry['integrated_systems']['object_pools']['pools'] as $pool_name => $pool_data) {
                    // Check for low hit rates
                    if (isset($pool_data['hit_rate']) && $pool_data['hit_rate'] < 0.5) {
                        $issues[] = 'low_hit_rate_' . $pool_name;
                    }

                    // Check for high utilization
                    if (isset($pool_data['current_pool_size']) && isset($pool_data['max_pool_size'])) {
                        $utilization = $pool_data['current_pool_size'] / $pool_data['max_pool_size'];
                        if ($utilization > 0.9) {
                            $issues[] = 'high_utilization_' . $pool_name;
                        }
                    }
                }
            }
        }

        return array_unique($issues);
    }

    /**
     * Update usage patterns analysis
     *
     * @param array $memory_data Current memory data
     */
    private function update_usage_patterns($memory_data) {
        // Update hourly and daily patterns
        $current_hour = (int)date('H', $memory_data['timestamp']);
        $this->update_hourly_pattern($current_hour, $memory_data);

        $current_day = (int)date('N', $memory_data['timestamp']); // 1 = Monday, 7 = Sunday
        $this->update_daily_pattern($current_day, $memory_data);

        // Update pattern analysis in database periodically
        if (rand(1, 100) <= 5) { // 5% chance to update patterns
            $this->update_pattern_analysis();
        }
    }

    /**
     * Update hourly usage pattern
     *
     * @param int $hour Hour of day (0-23)
     * @param array $memory_data Memory data
     */
    private function update_hourly_pattern($hour, $memory_data) {
        if (!isset($this->hourly_patterns[$hour])) {
            $this->hourly_patterns[$hour] = ['count' => 0, 'total_usage' => 0, 'peak_usage' => 0];
        }

        $this->hourly_patterns[$hour]['count']++;
        $this->hourly_patterns[$hour]['total_usage'] += $memory_data['usage_percentage'];
        $this->hourly_patterns[$hour]['peak_usage'] = max(
            $this->hourly_patterns[$hour]['peak_usage'],
            $memory_data['usage_percentage']
        );
    }

    /**
     * Update daily usage pattern
     *
     * @param int $day Day of week (1-7, Monday = 1)
     * @param array $memory_data Memory data
     */
    private function update_daily_pattern($day, $memory_data) {
        if (!isset($this->daily_patterns[$day])) {
            $this->daily_patterns[$day] = ['count' => 0, 'total_usage' => 0, 'peak_usage' => 0];
        }

        $this->daily_patterns[$day]['count']++;
        $this->daily_patterns[$day]['total_usage'] += $memory_data['usage_percentage'];
        $this->daily_patterns[$day]['peak_usage'] = max(
            $this->daily_patterns[$day]['peak_usage'],
            $memory_data['usage_percentage']
        );
    }

    /**
     * Update pattern analysis in database
     */
    private function update_pattern_analysis() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_usage_patterns';

        // Update or insert hourly pattern
        if (isset($this->hourly_patterns)) {
            foreach ($this->hourly_patterns as $hour => $pattern) {
                if ($pattern['count'] > 0) {
                    $avg_usage = $pattern['total_usage'] / $pattern['count'];

                    $wpdb->replace($table_name, [
                        'pattern_name' => "hourly_pattern_{$hour}",
                        'pattern_type' => 'hourly',
                        'time_period' => $hour,
                        'pattern_data' => json_encode($pattern),
                        'frequency_distribution' => json_encode($this->calculate_frequency_distribution($pattern)),
                        'peak_usage_times' => json_encode([$hour]),
                        'low_usage_times' => json_encode([]),
                        'correlation_factors' => json_encode([]),
                        'predictive_power' => $this->calculate_pattern_predictive_power($pattern),
                        'confidence_score' => min(1.0, $pattern['count'] / 100),
                        'last_analyzed' => current_time('mysql'),
                        'next_analysis_due' => date('Y-m-d H:i:s', time() + (24 * 60 * 60)),
                        'is_active' => 1
                    ]);
                }
            }
        }

        // Update or insert daily pattern
        if (isset($this->daily_patterns)) {
            foreach ($this->daily_patterns as $day => $pattern) {
                if ($pattern['count'] > 0) {
                    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    $day_name = $day_names[$day - 1];

                    $wpdb->replace($table_name, [
                        'pattern_name' => "daily_pattern_{$day_name}",
                        'pattern_type' => 'daily',
                        'time_period' => $day,
                        'pattern_data' => json_encode($pattern),
                        'frequency_distribution' => json_encode($this->calculate_frequency_distribution($pattern)),
                        'peak_usage_times' => json_encode([$day_name]),
                        'low_usage_times' => json_encode([]),
                        'correlation_factors' => json_encode([]),
                        'predictive_power' => $this->calculate_pattern_predictive_power($pattern),
                        'confidence_score' => min(1.0, $pattern['count'] / 30),
                        'last_analyzed' => current_time('mysql'),
                        'next_analysis_due' => date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)),
                        'is_active' => 1
                    ]);
                }
            }
        }
    }

    /**
     * Calculate frequency distribution for pattern analysis
     *
     * @param array $pattern Pattern data
     * @return array Frequency distribution
     */
    private function calculate_frequency_distribution($pattern) {
        return [
            'low' => $pattern['count'] * 0.2,
            'medium' => $pattern['count'] * 0.5,
            'high' => $pattern['count'] * 0.3
        ];
    }

    /**
     * Calculate predictive power of a pattern
     *
     * @param array $pattern Pattern data
     * @return float Predictive power (0-1)
     */
    private function calculate_pattern_predictive_power($pattern) {
        if ($pattern['count'] < 5) {
            return 0.0;
        }

        $avg_usage = $pattern['total_usage'] / $pattern['count'];
        $variance = $pattern['peak_usage'] - $avg_usage;

        return max(0.0, min(1.0, 1.0 - ($variance / 100)));
    }

    /**
     * Enhanced data collection and storage for historical analysis
     *
     * @param array $memory_data Memory usage data
     */
    private function store_enhanced_memory_data($memory_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_metrics_history';

        // Calculate additional metrics for enhanced analysis
        $enhanced_data = $this->calculate_enhanced_metrics($memory_data);

        // Store in enhanced metrics table
        $wpdb->insert($table_name, [
            'timestamp' => current_time('mysql'),
            'memory_usage' => $memory_data['total_usage'],
            'usage_percentage' => $memory_data['usage_percentage'],
            'system_memory' => $memory_data['system']['current_usage'],
            'peak_memory' => $memory_data['system']['peak_usage'],
            'pool_memory' => isset($memory_data['integrated_systems']['object_pools']['current_usage'])
                ? $memory_data['integrated_systems']['object_pools']['current_usage']
                : 0,
            'cache_memory' => $this->calculate_cache_memory_usage($memory_data),
            'config_memory' => isset($memory_data['integrated_systems']['config_systems']['current_usage'])
                ? $memory_data['integrated_systems']['config_systems']['current_usage']
                : 0,
            'cleanup_memory' => isset($memory_data['integrated_systems']['resource_cleanup_manager']['current_usage'])
                ? $memory_data['integrated_systems']['resource_cleanup_manager']['current_usage']
                : 0,
            'status' => $memory_data['status'],
            'efficiency_score' => $memory_data['efficiency_score'],
            'memory_limit' => $memory_data['system']['memory_limit'],
            'available_memory' => $memory_data['system']['memory_limit'] - $memory_data['total_usage'],
            'fragmentation_ratio' => $enhanced_data['fragmentation_ratio'],
            'garbage_collection_runs' => $enhanced_data['gc_runs'],
            'object_pool_hit_rate' => $enhanced_data['pool_hit_rate'],
            'cache_hit_rate' => $enhanced_data['cache_hit_rate'],
            'connection_pool_utilization' => $enhanced_data['connection_pool_utilization'],
            'websocket_pool_utilization' => $enhanced_data['websocket_pool_utilization'],
            'memory_pressure_level' => $enhanced_data['memory_pressure_level'],
            'trend_direction' => $enhanced_data['trend_direction'],
            'trend_magnitude' => $enhanced_data['trend_magnitude'],
            'anomaly_score' => $enhanced_data['anomaly_score'],
            'component_breakdown' => json_encode($this->get_component_breakdown($memory_data)),
            'system_info' => json_encode($this->get_system_info())
        ]);

        // Store trend analysis if enough data points
        if (count($this->memory_history) >= 10) {
            $this->store_trend_analysis();
        }

        // Check for memory leak patterns
        $this->detect_memory_leak_patterns();

        // Update usage patterns
        $this->update_usage_patterns($memory_data);
    }

    /**
     * Check for memory alerts based on thresholds
     *
     * @param array $memory_data Memory usage data
     */
    private function check_memory_alerts($memory_data) {
        if (!class_exists('\SMO_Social\Core\MemoryAlertSystem')) {
            return; // Skip alerts if system not available
        }

        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        if ($memory_data['status'] === 'critical') {
            $alert_system->trigger_alert(
                'memory_critical',
                'Critical Memory Usage',
                sprintf(
                    'Memory usage has reached critical levels: %.1f%% (%.1f MB / %.1f MB)',
                    $memory_data['usage_percentage'],
                    $memory_data['total_usage'] / (1024 * 1024),
                    isset($memory_data['system']['memory_limit']) ? $memory_data['system']['memory_limit'] / (1024 * 1024) : 0
                ),
                'critical',
                $memory_data
            );
        } elseif ($memory_data['status'] === 'warning') {
            $alert_system->trigger_alert(
                'memory_warning',
                'High Memory Usage Warning',
                sprintf(
                    'Memory usage is approaching critical levels: %.1f%% (%.1f MB / %.1f MB)',
                    $memory_data['usage_percentage'],
                    $memory_data['total_usage'] / (1024 * 1024),
                    isset($memory_data['system']['memory_limit']) ? $memory_data['system']['memory_limit'] / (1024 * 1024) : 0
                ),
                'warning',
                $memory_data
            );
        }

        // Check for memory leaks (consistent increase over time)
        if (count($this->memory_history) >= 5) {
            $this->check_for_memory_leaks();
        }
    }

    /**
     * Check for potential memory leaks
     */
    private function check_for_memory_leaks() {
        $recent_history = array_slice($this->memory_history, -5);
        $usage_increase = 0;
        $leak_detected = true;

        // Check if memory usage is consistently increasing
        for ($i = 1; $i < count($recent_history); $i++) {
            $prev_usage = $recent_history[$i-1]['total_usage'];
            $curr_usage = $recent_history[$i]['total_usage'];

            if ($curr_usage <= $prev_usage) {
                $leak_detected = false;
                break;
            }

            $usage_increase += ($curr_usage - $prev_usage);
        }

        if ($leak_detected && $usage_increase > 0) {
            $avg_increase = $usage_increase / (count($recent_history) - 1);
            $increase_percentage = ($usage_increase / $recent_history[0]['total_usage']) * 100;

            if ($increase_percentage > 5) { // More than 5% increase over 5 monitoring cycles
                if (class_exists('\SMO_Social\Core\MemoryAlertSystem')) {
                    $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();
                    $alert_system->trigger_alert(
                        'memory_leak_detected',
                        'Potential Memory Leak Detected',
                        sprintf(
                            'Memory usage has consistently increased by %.1f%% (%.1f MB) over the last %d monitoring cycles. Potential memory leak detected.',
                            $increase_percentage,
                            $usage_increase / (1024 * 1024),
                            count($recent_history)
                        ),
                        'critical',
                        [
                            'increase_percentage' => $increase_percentage,
                            'total_increase_bytes' => $usage_increase,
                            'history' => $recent_history
                        ]
                    );
                }
                }
            }
    }

    /**
     * Log memory usage using Logger class
     *
     * @param array $memory_data Memory usage data
     */
    private function log_memory_usage($memory_data) {
        $log_message = sprintf(
            "Memory usage: %.1f%% (%s / %s), Status: %s, Efficiency: %.1f%%",
            $memory_data['usage_percentage'],
            $memory_data['total_usage_formatted'],
            $memory_data['system']['memory_limit_formatted'],
            ucfirst($memory_data['status']),
            $memory_data['efficiency_score']
        );

        // Use appropriate log level based on status
        switch ($memory_data['status']) {
            case 'critical':
                Logger::error($log_message, $memory_data);
                break;
            case 'warning':
                Logger::warning($log_message, $memory_data);
                break;
            default:
                Logger::info($log_message, $memory_data);
                break;
        }
    }

    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes Bytes to format
     * @param int $decimals Number of decimal places
     * @return string Formatted size
     */
    public function format_bytes($bytes, $decimals = 2) {
        $size = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        if ($factor > 0) {
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
        } else {
            return $bytes . ' B';
        }
    }

    /**
     * Get current memory statistics
     *
     * @return array Current memory statistics
     */
    public function get_current_stats() {
        return $this->current_stats;
    }

    /**
     * Get memory usage history
     *
     * @param int $limit Limit number of entries
     * @return array Memory usage history
     */
    public function get_memory_history($limit = 0) {
        if ($limit > 0) {
            return array_slice($this->memory_history, -$limit);
        }
        return $this->memory_history;
    }

    /**
     * Get memory usage history from database
     *
     * @param int $limit Limit number of entries
     * @param string $status Filter by status
     * @return array Database memory history
     */
    public function get_database_memory_history($limit = 100, $status = '') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_monitoring';

        $query = "SELECT * FROM $table_name";
        $where_clauses = [];
        $params = [];

        if (!empty($status)) {
            $where_clauses[] = "status = %s";
            $params[] = $status;
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $query .= " ORDER BY timestamp DESC";

        if ($limit > 0) {
            $query .= " LIMIT " . intval($limit);
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Decode memory_data field
        foreach ($results as &$result) {
            $result['memory_data'] = json_decode($result['memory_data'], true);
        }

        return $results;
    }

    /**
     * Get memory efficiency analysis
     *
     * @return array Memory efficiency analysis
     */
    public function get_memory_efficiency_analysis() {
        if (empty($this->memory_history)) {
            return [
                'average_usage' => 0,
                'peak_usage' => 0,
                'average_efficiency' => 0,
                'trend' => 'stable',
                'recommendations' => []
            ];
        }

        $total_usage = 0;
        $total_efficiency = 0;
        $peak_usage = 0;
        $count = count($this->memory_history);

        foreach ($this->memory_history as $entry) {
            $total_usage += $entry['usage_percentage'];
            $total_efficiency += $entry['efficiency_score'];
            $peak_usage = max($peak_usage, $entry['usage_percentage']);
        }

        $analysis = [
            'average_usage' => $total_usage / $count,
            'peak_usage' => $peak_usage,
            'average_efficiency' => $total_efficiency / $count,
            'trend' => 'stable',
            'recommendations' => []
        ];

        // Determine trend
        if ($count >= 3) {
            $recent_entries = array_slice($this->memory_history, -3);
            $first_usage = $recent_entries[0]['usage_percentage'];
            $last_usage = $recent_entries[count($recent_entries) - 1]['usage_percentage'];

            if ($last_usage > $first_usage + 5) {
                $analysis['trend'] = 'increasing';
            } elseif ($last_usage < $first_usage - 5) {
                $analysis['trend'] = 'decreasing';
            }
        }

        // Generate recommendations
        if ($analysis['average_usage'] > 80) {
            $analysis['recommendations'][] = 'Consider increasing PHP memory limit';
        }

        if ($analysis['average_efficiency'] < 60) {
            $analysis['recommendations'][] = 'Review object pool configurations for better efficiency';
        }

        if ($analysis['trend'] === 'increasing') {
            $analysis['recommendations'][] = 'Monitor for potential memory leaks';
        }

        return $analysis;
    }

    /**
     * Add memory display to admin footer
     */
    public function add_admin_memory_display() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current_stats = $this->get_current_stats();
        $memory_info = sprintf(
            __('Memory: %s / %s (%.1f%%)', 'smo-social'),
            $current_stats['total_usage_formatted'] ?? 'N/A',
            $current_stats['system']['memory_limit_formatted'] ?? 'N/A',
            $current_stats['usage_percentage'] ?? 0
        );

        $status_class = $current_stats['status'] ?? 'normal';

        echo '<div class="smo-memory-footer" style="text-align: right; padding: 5px; font-size: 11px; color: #666;">';
        echo '<span class="smo-memory-status ' . esc_attr($status_class) . '">' . esc_html($memory_info) . '</span>';
        echo '</div>';

        echo '<style>
            .smo-memory-footer .smo-memory-status {
                padding: 2px 6px;
                border-radius: 3px;
                display: inline-block;
            }
            .smo-memory-footer .smo-memory-status.normal {
                background: #d4edda;
                color: #155724;
            }
            .smo-memory-footer .smo-memory-status.warning {
                background: #fff3cd;
                color: #856404;
            }
            .smo-memory-footer .smo-memory-status.critical {
                background: #f8d7da;
                color: #721c24;
            }
        </style>';
    }

    /**
     * Handle AJAX request for memory stats
     */
    public function handle_memory_stats_ajax() {
        check_ajax_referer('smo_memory_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $stats = $this->get_current_stats();
        wp_send_json_success($stats);
    }

    /**
     * Handle AJAX request for memory history
     */
    public function handle_memory_history_ajax() {
        check_ajax_referer('smo_memory_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $history = $this->get_memory_history($limit);

        wp_send_json_success($history);
    }

    /**
     * Perform cleanup on plugin deactivation
     */
    public function cleanup_on_deactivation() {
        // Clear cron jobs
        wp_clear_scheduled_hook('smo_memory_monitoring');

        // Clean up database table (optional, could keep data for analysis)
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_memory_monitoring';

        // Optionally truncate table instead of dropping
        $wpdb->query("TRUNCATE TABLE $table_name");
    }

    /**
     * Get integrated systems status
     *
     * @return array Integrated systems status
     */
    public function get_integrated_systems_status() {
        $status = [];

        foreach ($this->integrated_systems as $system_name => $system) {
            $status[$system_name] = [
                'name' => $system_name,
                'status' => is_object($system) ? 'integrated' : 'available',
                'type' => is_object($system) ? get_class($system) : 'class'
            ];
        }

        return $status;
    }

    /**
     * Force immediate memory monitoring
     */
    public function force_memory_monitoring() {
        $this->last_monitor_time = 0;
        $this->perform_memory_monitoring();
    }

    /**
     * Update monitoring configuration
     *
     * @param array $new_config New configuration
     */
    public function update_config($new_config) {
        $config_instance = MemoryMonitorConfig::get_instance();

        // Update the configuration
        $config_instance->update_config($new_config);

        // Re-initialize our config
        $this->initialize_config();

        // Update monitoring interval if changed
        if (isset($new_config['monitoring_interval']) || isset($new_config['enable_real_time_monitoring'])) {
            $this->update_cron_schedule();
        }
    }

    /**
     * Enable or disable real-time monitoring
     *
     * @param bool $enable Whether to enable real-time monitoring
     * @return bool True if successful
     */
    public function set_real_time_monitoring($enable) {
        try {
            $config_instance = MemoryMonitorConfig::get_instance();
            $result = $config_instance->update_config(['enable_real_time_monitoring' => boolval($enable)]);

            if ($result) {
                $this->initialize_config();
                $this->update_cron_schedule();

                Logger::info('Real-time monitoring ' . ($enable ? 'enabled' : 'disabled'), [
                    'real_time_enabled' => $this->real_time_monitoring,
                    'monitoring_interval' => $this->monitoring_interval
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to set real-time monitoring: ' . $e->getMessage(), [
                'enable' => $enable,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get real-time monitoring status
     *
     * @return array Real-time monitoring status
     */
    public function get_real_time_status() {
        return [
            'enabled' => $this->real_time_monitoring,
            'interval' => $this->real_time_interval,
            'current_interval' => $this->monitoring_interval,
            'last_monitor_time' => $this->last_monitor_time,
            'next_monitor_time' => $this->last_monitor_time + $this->monitoring_interval
        ];
    }

    /**
     * Update cron schedule for monitoring
     */
    private function update_cron_schedule() {
        // Clear existing schedule
        wp_clear_scheduled_hook('smo_memory_monitoring');

        // Add new schedule
        if (!wp_next_scheduled('smo_memory_monitoring')) {
            wp_schedule_event(time(), 'every_' . $this->monitoring_interval . '_seconds', 'smo_memory_monitoring');
        }
    }

    /**
     * Get real-time memory status
     *
     * @return array Real-time memory status
     */
    public function get_real_time_memory_status() {
        // Force immediate monitoring for real-time status
        $this->force_memory_monitoring();

        $current_stats = $this->get_current_stats();
        $status = [
            'timestamp' => time(),
            'real_time_enabled' => $this->real_time_monitoring,
            'monitoring_interval' => $this->monitoring_interval,
            'last_update' => $this->last_monitor_time,
            'memory_status' => $current_stats,
            'efficiency_score' => $current_stats['efficiency_score'] ?? 0,
            'alerts_active' => $this->get_active_alerts_count(),
            'trend' => $this->calculate_memory_trend(),
            'performance_metrics' => $this->get_performance_metrics()
        ];

        return $status;
    }

    /**
     * Get active alerts count
     *
     * @return int Number of active alerts
     */
    private function get_active_alerts_count() {
        // This would integrate with MemoryAlertSystem
        if (class_exists('\SMO_Social\Core\MemoryAlertSystem')) {
            try {
                $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();
                $active_alerts = $alert_system->get_active_alerts();
                return count($active_alerts);
            } catch (\Exception $e) {
                Logger::error('Failed to get active alerts count: ' . $e->getMessage());
            }
        }
        return 0;
    }

    /**
     * Get performance metrics for real-time monitoring
     *
     * @return array Performance metrics
     */
    private function get_performance_metrics() {
        $metrics = [
            'monitoring_frequency' => $this->real_time_monitoring ? 'real-time' : 'scheduled',
            'average_response_time' => 0, // Would need to track actual monitoring times
            'data_points_collected' => count($this->memory_history),
            'memory_limit_utilization' => 0,
            'pool_efficiency' => 0
        ];

        if (!empty($this->current_stats)) {
            $metrics['memory_limit_utilization'] = $this->current_stats['usage_percentage'];

            if (isset($this->current_stats['integrated_systems']['object_pools']['total_hit_rate'])) {
                $metrics['pool_efficiency'] = $this->current_stats['integrated_systems']['object_pools']['total_hit_rate'];
            }
        }

        return $metrics;
    }

    /**
     * Get memory usage by component
     *
     * @return array Memory usage breakdown by component
     */
    public function get_memory_usage_by_component() {
        $current_stats = $this->get_current_stats();
        $breakdown = [];

        // System memory
        $breakdown['system'] = [
            'name' => 'System',
            'usage' => $current_stats['system']['current_usage'],
            'usage_formatted' => $current_stats['system']['current_usage_formatted'],
            'percentage' => $current_stats['system']['usage_percentage']
        ];

        // Object pools memory
        if (isset($current_stats['integrated_systems']['object_pools'])) {
            $breakdown['object_pools'] = [
                'name' => 'Object Pools',
                'usage' => $current_stats['integrated_systems']['object_pools']['current_usage'],
                'usage_formatted' => $current_stats['integrated_systems']['object_pools']['formatted'],
                'percentage' => ($current_stats['integrated_systems']['object_pools']['current_usage'] / $current_stats['system']['memory_limit']) * 100
            ];

            // Add individual pool breakdown
            foreach ($current_stats['integrated_systems']['object_pools']['pools'] as $pool_name => $pool_data) {
                if (isset($pool_data['memory_usage'])) {
                    $breakdown['pool_' . $pool_name] = [
                        'name' => 'Pool: ' . ucfirst(str_replace('_', ' ', $pool_name)),
                        'usage' => $pool_data['memory_usage'],
                        'usage_formatted' => $this->format_bytes($pool_data['memory_usage']),
                        'percentage' => ($pool_data['memory_usage'] / $current_stats['system']['memory_limit']) * 100,
                        'hit_rate' => $pool_data['hit_rate'] ?? 0,
                        'utilization' => isset($pool_data['current_pool_size']) && isset($pool_data['max_pool_size']) && $pool_data['max_pool_size'] > 0
                            ? ($pool_data['current_pool_size'] / $pool_data['max_pool_size']) * 100
                            : 0
                    ];
                }
            }
        }

        // Cache systems memory
        $cache_systems = [
            'cache_manager' => 'Cache Manager',
            'enhanced_cache_manager' => 'Enhanced Cache Manager',
            'bounded_cache_manager' => 'Bounded Cache Manager'
        ];

        foreach ($cache_systems as $system_key => $system_name) {
            if (isset($current_stats['integrated_systems'][$system_key])) {
                $cache_data = $current_stats['integrated_systems'][$system_key];
                $breakdown[$system_key] = [
                    'name' => $system_name,
                    'usage' => $cache_data['current_usage'],
                    'usage_formatted' => $cache_data['formatted'],
                    'percentage' => ($cache_data['current_usage'] / $current_stats['system']['memory_limit']) * 100,
                    'additional_info' => []
                ];

                // Add cache-specific metrics
                if (isset($cache_data['cache_keys'])) {
                    $breakdown[$system_key]['additional_info']['cache_keys'] = $cache_data['cache_keys'];
                }
                if (isset($cache_data['total_files'])) {
                    $breakdown[$system_key]['additional_info']['total_files'] = $cache_data['total_files'];
                }
                if (isset($cache_data['redis_available'])) {
                    $breakdown[$system_key]['additional_info']['redis_available'] = $cache_data['redis_available'];
                }
            }
        }

        // Resource cleanup manager memory
        if (isset($current_stats['integrated_systems']['resource_cleanup_manager'])) {
            $cleanup_data = $current_stats['integrated_systems']['resource_cleanup_manager'];
            $breakdown['resource_cleanup_manager'] = [
                'name' => 'Resource Cleanup Manager',
                'usage' => $cleanup_data['current_usage'],
                'usage_formatted' => $cleanup_data['formatted'],
                'percentage' => ($cleanup_data['current_usage'] / $current_stats['system']['memory_limit']) * 100,
                'additional_info' => [
                    'registered_streams' => $cleanup_data['registered_streams'] ?? 0,
                    'peak_usage' => $cleanup_data['peak_usage'] ?? 0
                ]
            ];
        }

        // Config systems memory
        if (isset($current_stats['integrated_systems']['config_systems'])) {
            $config_data = $current_stats['integrated_systems']['config_systems'];
            $breakdown['config_systems'] = [
                'name' => 'Configuration Systems',
                'usage' => $config_data['current_usage'],
                'usage_formatted' => $config_data['formatted'],
                'percentage' => ($config_data['current_usage'] / $current_stats['system']['memory_limit']) * 100
            ];
        }

        return $breakdown;
    }

    /**
     * Get monitoring diagnostics for system health check
     *
     * @return array Monitoring diagnostics
     */
    public function get_monitoring_diagnostics() {
        $diagnostics = [
            'timestamp' => time(),
            'system_status' => 'operational',
            'issues' => [],
            'metrics' => [],
            'configuration' => []
        ];

        try {
            // Check monitoring status
            $time_since_last_monitor = time() - $this->last_monitor_time;
            if ($time_since_last_monitor > ($this->monitoring_interval * 2)) {
                $diagnostics['issues'][] = 'Monitoring may be delayed - last check was ' . $time_since_last_monitor . ' seconds ago';
                $diagnostics['system_status'] = 'warning';
            }

            // Check configuration
            $diagnostics['configuration'] = [
                'monitoring_enabled' => $this->monitoring_enabled,
                'real_time_monitoring' => $this->real_time_monitoring,
                'monitoring_interval' => $this->monitoring_interval,
                'alert_thresholds' => $this->alert_thresholds
            ];

            // Check integrations
            $diagnostics['metrics']['integrations'] = $this->get_integrated_systems_status();

            // Check memory history
            $diagnostics['metrics']['history_size'] = count($this->memory_history);
            $diagnostics['metrics']['current_stats_available'] = !empty($this->current_stats);

            // Check cron status
            $diagnostics['metrics']['cron_scheduled'] = wp_next_scheduled('smo_memory_monitoring') !== false;

            // Performance metrics
            if (!empty($this->memory_history)) {
                $diagnostics['metrics']['average_efficiency'] = array_sum(array_column($this->memory_history, 'efficiency_score')) / count($this->memory_history);
                $diagnostics['metrics']['memory_trend'] = $this->calculate_memory_trend();
            }

            // Check for critical issues
            if (!$this->monitoring_enabled) {
                $diagnostics['issues'][] = 'Memory monitoring is disabled';
                $diagnostics['system_status'] = 'critical';
            }

            if (empty($this->integrated_systems)) {
                $diagnostics['issues'][] = 'No integrated systems detected';
                $diagnostics['system_status'] = 'warning';
            }

        } catch (\Exception $e) {
            $diagnostics['system_status'] = 'error';
            $diagnostics['issues'][] = 'Diagnostics failed: ' . $e->getMessage();
            Logger::error('Monitoring diagnostics failed: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
        }

        return $diagnostics;
    }

    /**
     * Get historical memory metrics from database
     *
     * @param int $limit Number of records to retrieve
     * @param array $filters Additional filters
     * @return array Historical metrics
     */
    public function get_historical_metrics($limit = 100, $filters = []) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_metrics_history';

        $query = "SELECT * FROM {$table_name}";
        $where_clauses = [];
        $params = [];

        // Apply filters
        if (isset($filters['start_date'])) {
            $where_clauses[] = "timestamp >= %s";
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $where_clauses[] = "timestamp <= %s";
            $params[] = $filters['end_date'];
        }

        if (isset($filters['status'])) {
            $where_clauses[] = "status = %s";
            $params[] = $filters['status'];
        }

        if (isset($filters['min_usage_percentage'])) {
            $where_clauses[] = "usage_percentage >= %f";
            $params[] = $filters['min_usage_percentage'];
        }

        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $query .= " ORDER BY timestamp DESC";

        if ($limit > 0) {
            $query .= " LIMIT %d";
            $params[] = $limit;
        }

        $results = $wpdb->get_results($wpdb->prepare($query, $params), \ARRAY_A);

        // Decode JSON fields
        foreach ($results as &$result) {
            $result['component_breakdown'] = json_decode($result['component_breakdown'], true);
            $result['system_info'] = json_decode($result['system_info'], true);
        }

        return $results ?: [];
    }

    /**
     * Get trend analysis data
     *
     * @param int $hours Hours to look back
     * @return array Trend analysis
     */
    public function get_trend_analysis($hours = 24) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_trends';
        $cutoff_time = date('Y-m-d H:i:s', time() - ($hours * 60 * 60));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE analysis_period_end >= %s ORDER BY analysis_period_end DESC",
            $cutoff_time
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get memory leak detection results
     *
     * @param int $hours Hours to look back
     * @return array Memory leak patterns
     */
    public function get_memory_leak_patterns($hours = 24) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_leak_patterns';
        $cutoff_time = date('Y-m-d H:i:s', time() - ($hours * 60 * 60));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE detection_timestamp >= %s ORDER BY detection_timestamp DESC",
            $cutoff_time
        ), ARRAY_A);

        // Decode JSON fields
        foreach ($results as &$result) {
            $result['affected_components'] = json_decode($result['affected_components'], true);
            $result['suspected_causes'] = json_decode($result['suspected_causes'], true);
            $result['evidence_data'] = json_decode($result['evidence_data'], true);
        }

        return $results ?: [];
    }

    /**
     * Get usage pattern analysis
     *
     * @param string $pattern_type Type of pattern (hourly, daily)
     * @return array Usage patterns
     */
    public function get_usage_patterns($pattern_type = 'all') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_usage_patterns';

        $query = "SELECT * FROM {$table_name}";
        $params = [];

        if ($pattern_type !== 'all') {
            $query .= " WHERE pattern_type = %s";
            $params[] = $pattern_type;
        }

        $query .= " ORDER BY predictive_power DESC, confidence_score DESC";

        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);

        // Decode JSON fields
        foreach ($results as &$result) {
            $result['pattern_data'] = json_decode($result['pattern_data'], true);
            $result['frequency_distribution'] = json_decode($result['frequency_distribution'], true);
            $result['peak_usage_times'] = json_decode($result['peak_usage_times'], true);
            $result['low_usage_times'] = json_decode($result['low_usage_times'], true);
            $result['correlation_factors'] = json_decode($result['correlation_factors'], true);
        }

        return $results ?: [];
    }

    /**
     * Generate predictive memory usage forecast
     *
     * @param int $hours Hours to forecast
     * @return array Forecast data
     */
    public function generate_memory_forecast($hours = 24) {
        $forecast = [
            'forecast_period_hours' => $hours,
            'predictions' => [],
            'confidence_intervals' => [],
            'risk_assessment' => 'low',
            'recommendations' => []
        ];

        try {
            // Get recent historical data
            $historical_data = $this->get_historical_metrics(100, [
                'start_date' => date('Y-m-d H:i:s', time() - (7 * 24 * 60 * 60)) // Last 7 days
            ]);

            if (count($historical_data) < 10) {
                $forecast['error'] = 'Insufficient historical data for forecasting';
                return $forecast;
            }

            // Simple forecasting using trend analysis
            $usages = array_column($historical_data, 'usage_percentage');
            $timestamps = array_column($historical_data, 'timestamp');

            // Convert timestamps to hours from start
            $start_time = strtotime($timestamps[0]);
            $hours_from_start = array_map(function($timestamp) use ($start_time) {
                return (strtotime($timestamp) - $start_time) / 3600;
            }, $timestamps);

            $trend = $this->calculate_linear_trend($hours_from_start, $usages);

            // Generate predictions
            $current_time = time();
            for ($i = 1; $i <= $hours; $i++) {
                $future_time = $current_time + ($i * 60 * 60);
                $hours_from_start_future = ($future_time - $start_time) / 3600;

                $predicted_usage = $usages[0] + $trend['slope'] * ($hours_from_start_future - $hours_from_start[0]);
                $predicted_usage = max(0, min(100, $predicted_usage)); // Clamp to 0-100%

                $forecast['predictions'][] = [
                    'timestamp' => date('Y-m-d H:i:s', $future_time),
                    'predicted_usage_percentage' => round($predicted_usage, 2),
                    'confidence_level' => $trend['confidence']
                ];
            }

            // Assess risk
            $max_predicted = max(array_column($forecast['predictions'], 'predicted_usage_percentage'));
            if ($max_predicted > 90) {
                $forecast['risk_assessment'] = 'critical';
            } elseif ($max_predicted > 75) {
                $forecast['risk_assessment'] = 'high';
            } elseif ($max_predicted > 60) {
                $forecast['risk_assessment'] = 'medium';
            }

            // Generate recommendations based on forecast
            if ($forecast['risk_assessment'] !== 'low') {
                $forecast['recommendations'] = [
                    'Consider increasing PHP memory limit before predicted peak usage',
                    'Monitor cache systems for potential cleanup needs',
                    'Review object pool configurations for optimization',
                    'Schedule maintenance during predicted low-usage periods'
                ];
            }

        } catch (\Exception $e) {
            $forecast['error'] = 'Forecast generation failed: ' . $e->getMessage();
            Logger::error('Memory forecast generation failed: ' . $e->getMessage());
        }

        return $forecast;
    }

    /**
     * Generate optimization recommendations based on analysis
     *
     * @return array Optimization recommendations
     */
    public function generate_optimization_recommendations() {
        $recommendations = [];

        try {
            $current_stats = $this->get_current_stats();
            if (empty($current_stats)) {
                return $recommendations;
            }

            // Check memory usage thresholds
            if ($current_stats['usage_percentage'] > 80) {
                $recommendations[] = [
                    'type' => 'memory_limit_increase',
                    'priority' => 'high',
                    'title' => 'Increase PHP Memory Limit',
                    'description' => 'Current memory usage is above 80%. Consider increasing the PHP memory limit.',
                    'risk_level' => 'high',
                    'complexity' => 'medium',
                    'affected_components' => ['php.ini', 'wp-config.php'],
                    'expected_benefit_mb' => 0,
                    'expected_benefit_percentage' => 20.0,
                    'implementation_complexity' => 'medium'
                ];
            }

            // Check object pool efficiency
            if (isset($current_stats['integrated_systems']['object_pools'])) {
                $pool_data = $current_stats['integrated_systems']['object_pools'];
                $avg_hit_rate = $pool_data['total_hit_rate'] ?? 0;

                if ($avg_hit_rate < 0.7) {
                    $recommendations[] = [
                        'type' => 'pool_optimization',
                        'priority' => 'medium',
                        'title' => 'Optimize Object Pool Configuration',
                        'description' => 'Object pool hit rate is below 70%. Review pool sizes and configurations.',
                        'risk_level' => 'medium',
                        'complexity' => 'low',
                        'affected_components' => ['ObjectPoolMonitor', 'DatabaseConnectionPool'],
                        'expected_benefit_mb' => 0,
                        'expected_benefit_percentage' => 15.0,
                        'implementation_complexity' => 'low'
                    ];
                }
            }

            // Check for memory leak patterns
            $leak_patterns = $this->get_memory_leak_patterns(24);
            if (!empty($leak_patterns)) {
                $recommendations[] = [
                    'type' => 'memory_leak_mitigation',
                    'priority' => 'critical',
                    'title' => 'Address Memory Leak Patterns',
                    'description' => 'Memory leak patterns detected. Implement the suggested mitigation strategies.',
                    'risk_level' => 'critical',
                    'complexity' => 'high',
                    'affected_components' => ['MemoryMonitor', 'ResourceCleanupManager'],
                    'expected_benefit_mb' => 0,
                    'expected_benefit_percentage' => 30.0,
                    'implementation_complexity' => 'high'
                ];
            }

            // Check cache efficiency
            $cache_efficiency = $this->analyze_cache_efficiency();
            if ($cache_efficiency < 0.6) {
                $recommendations[] = [
                    'type' => 'cache_optimization',
                    'priority' => 'medium',
                    'title' => 'Optimize Cache Systems',
                    'description' => 'Cache systems are underperforming. Review cache strategies and sizes.',
                    'risk_level' => 'medium',
                    'complexity' => 'medium',
                    'affected_components' => ['CacheManager', 'EnhancedCacheManager'],
                    'expected_benefit_mb' => 0,
                    'expected_benefit_percentage' => 10.0,
                    'implementation_complexity' => 'medium'
                ];
            }

            // Store recommendations in database
            $this->store_optimization_recommendations($recommendations);

        } catch (\Exception $e) {
            Logger::error('Failed to generate optimization recommendations: ' . $e->getMessage());
        }

        return $recommendations;
    }

    /**
     * Store optimization recommendations in database
     *
     * @param array $recommendations Recommendations to store
     */
    private function store_optimization_recommendations($recommendations) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_memory_optimization_recommendations';

        foreach ($recommendations as $rec) {
            $wpdb->replace($table_name, [
                'recommendation_type' => $rec['type'],
                'priority_level' => $rec['priority'],
                'title' => $rec['title'],
                'description' => $rec['description'],
                'technical_details' => json_encode($rec),
                'implementation_steps' => json_encode([]),
                'expected_benefit_mb' => $rec['expected_benefit_mb'],
                'expected_benefit_percentage' => $rec['expected_benefit_percentage'],
                'implementation_complexity' => $rec['implementation_complexity'],
                'risk_level' => $rec['risk_level'],
                'prerequisites' => json_encode([]),
                'affected_components' => json_encode($rec['affected_components']),
                'status' => 'pending'
            ]);
        }
    }

    /**
     * Analyze cache efficiency
     *
     * @return float Cache efficiency score (0-1)
     */
    private function analyze_cache_efficiency() {
        $current_stats = $this->get_current_stats();
        if (empty($current_stats) || !isset($current_stats['integrated_systems'])) {
            return 0.5;
        }

        $cache_score = 0;
        $cache_count = 0;

        $cache_systems = ['cache_manager', 'enhanced_cache_manager', 'bounded_cache_manager'];
        foreach ($cache_systems as $cache_system) {
            if (isset($current_stats['integrated_systems'][$cache_system])) {
                $cache_count++;
                $cache_score += 0.8; // Assume 80% efficiency for active cache systems
            }
        }

        return $cache_count > 0 ? $cache_score / $cache_count : 0.5;
    }
}