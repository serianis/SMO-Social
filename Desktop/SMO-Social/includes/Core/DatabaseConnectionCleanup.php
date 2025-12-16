<?php
/**
 * Database Connection Cleanup for SMO Social
 *
 * Handles comprehensive database connection cleanup, validation, and monitoring
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
require_once __DIR__ . '/DatabaseConnectionPool.php';
require_once __DIR__ . '/ResourceCleanupManager.php';

/**
 * Database Connection Cleanup
 */
class DatabaseConnectionCleanup {
    /**
     * @var DatabaseConnectionPool|null Database connection pool instance
     */
    private $connection_pool = null;

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
        'total_connections' => 0,
        'active_connections' => 0,
        'idle_connections' => 0,
        'stale_connections' => 0,
        'cleanup_count' => 0,
        'last_cleanup_time' => 0,
        'connection_errors' => 0,
        'validation_failures' => 0
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
            'retry_delay' => 1000 // 1 second
        ], $config);

        $this->resource_cleanup_manager = new ResourceCleanupManager();
        $this->initialize_connection_pool();
    }

    /**
     * Initialize connection pool
     */
    private function initialize_connection_pool() {
        if ($this->connection_pool === null) {
            $pool_config = [
                'max_pool_size' => 10,
                'connection_timeout' => 300
            ];

            // Get pool size from settings if available
            $settings = get_option('smo_social_settings', []);
            if (isset($settings['db_pool_size']) && is_numeric($settings['db_pool_size'])) {
                $pool_config['max_pool_size'] = max(1, min(20, intval($settings['db_pool_size'])));
            }

            $this->connection_pool = new DatabaseConnectionPool([], $pool_config['max_pool_size']);
            $this->connection_pool->set_max_pool_size($pool_config['max_pool_size']);
        }
    }

    /**
     * Get connection pool instance
     *
     * @return DatabaseConnectionPool|null
     */
    public function get_connection_pool() {
        return $this->connection_pool;
    }

    /**
     * Validate database connection
     *
     * @param mixed $connection Database connection (mysqli object)
     * @return bool True if connection is valid
     */
    public function validate_connection($connection) {
        if (!is_object($connection) || !($connection instanceof \mysqli)) {
            $this->health_stats['validation_failures']++;
            return false;
        }

        try {
            // Simple validation query using mysqli object method
            $result = $connection->ping();

            if (!$result) {
                $this->health_stats['validation_failures']++;
                error_log('SMO Social: Database connection validation failed - ' . $connection->error);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->health_stats['validation_failures']++;
            error_log('SMO Social: Database connection validation error - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check connection health with comprehensive diagnostics
     *
     * @param mixed $connection Database connection (mysqli object)
     * @return array Health check results
     */
    public function check_connection_health($connection) {
        $health_check = [
            'valid' => false,
            'ping_success' => false,
            'query_success' => false,
            'latency' => 0,
            'error' => null,
            'timestamp' => time()
        ];

        if (!is_object($connection) || !($connection instanceof \mysqli)) {
            $health_check['error'] = 'Invalid connection object';
            return $health_check;
        }

        try {
            // Start timing
            $start_time = microtime(true);

            // Ping test
            $ping_result = $connection->ping();
            $health_check['ping_success'] = $ping_result;

            if (!$ping_result) {
                $health_check['error'] = 'Connection ping failed: ' . $connection->error;
                return $health_check;
            }

            // Simple query test
            $query_result = $connection->query('SELECT 1');
            $health_check['query_success'] = $query_result !== false;

            if ($query_result) {
                $query_result->free();
            }

            // Calculate latency
            $health_check['latency'] = microtime(true) - $start_time;
            $health_check['valid'] = true;

        } catch (\Exception $e) {
            $health_check['error'] = 'Health check error: ' . $e->getMessage();
        }

        return $health_check;
    }

    /**
     * Monitor connection state and perform cleanup if needed
     *
     * @param resource $connection Database connection
     * @param int $last_used_time Timestamp of last usage
     * @return bool True if connection should be cleaned up
     */
    public function monitor_connection_state($connection, $last_used_time) {
        $current_time = time();
        $idle_time = $current_time - $last_used_time;

        // Check if connection is idle
        $is_idle = $idle_time > $this->config['idle_timeout'];

        // Check if connection is stale
        $is_stale = $idle_time > $this->config['stale_timeout'];

        // Validate connection
        $is_valid = $this->validate_connection($connection);

        // Update health statistics
        $this->health_stats['total_connections']++;

        if ($is_valid) {
            if ($is_stale) {
                $this->health_stats['stale_connections']++;
                return true; // Clean up stale connections
            } elseif ($is_idle) {
                $this->health_stats['idle_connections']++;
                // Keep idle connections for now, but mark for potential cleanup
            } else {
                $this->health_stats['active_connections']++;
            }
        } else {
            $this->health_stats['connection_errors']++;
            return true; // Clean up invalid connections
        }

        return false;
    }

    /**
     * Clean up idle and stale connections
     *
     * @param int $max_cleanup Maximum number of connections to clean up
     * @return int Number of connections cleaned up
     */
    public function cleanup_idle_connections($max_cleanup = null) {
        if ($this->connection_pool === null) {
            return 0;
        }

        $max_cleanup = $max_cleanup ?? $this->config['max_cleanup_batch'];
        $cleanup_count = 0;

        try {
            $connections = $this->connection_pool->get_pool_status()['connections'];

            foreach ($connections as $connection_id => $connection_data) {
                if ($cleanup_count >= $max_cleanup) {
                    break;
                }

                $connection = $connection_data['connection'] ?? null;
                $last_used = $connection_data['last_used'] ?? 0;

                if ($this->monitor_connection_state($connection, $last_used)) {
                    // Use the pool's cleanup method to remove idle connections
                    $this->connection_pool->cleanup_idle_connections(0); // Cleanup all idle connections
                    $cleanup_count++;
                    $this->health_stats['cleanup_count']++;
                }
            }

            $this->health_stats['last_cleanup_time'] = time();

        } catch (\Exception $e) {
            error_log('SMO Social: Database connection cleanup error - ' . $e->getMessage());
        }

        return $cleanup_count;
    }

    /**
     * Automatic cleanup for stale connections
     *
     * @return int Number of connections cleaned up
     */
    public function automatic_cleanup() {
        $cleanup_count = 0;

        try {
            // Clean up idle connections
            $cleanup_count += $this->cleanup_idle_connections();

            // Additional cleanup for resource management
            if ($this->resource_cleanup_manager) {
                // Resource cleanup manager doesn't have database-specific methods
                // $this->resource_cleanup_manager->cleanup_all_streams();
            }

        } catch (\Exception $e) {
            error_log('SMO Social: Automatic database cleanup error - ' . $e->getMessage());
        }

        return $cleanup_count;
    }

    /**
     * Get connection health statistics
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
            'total_connections' => 0,
            'active_connections' => 0,
            'idle_connections' => 0,
            'stale_connections' => 0,
            'cleanup_count' => 0,
            'last_cleanup_time' => 0,
            'connection_errors' => 0,
            'validation_failures' => 0
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
     * Perform comprehensive connection validation
     *
     * @return array Validation results
     */
    public function perform_comprehensive_validation() {
        $validation_results = [
            'total_connections' => 0,
            'valid_connections' => 0,
            'invalid_connections' => 0,
            'validation_errors' => [],
            'timestamp' => time()
        ];

        try {
            if ($this->connection_pool === null) {
                return $validation_results;
            }

            $connections = $this->connection_pool->get_pool_status()['connections'];

            foreach ($connections as $connection_id => $connection_data) {
                $connection = $connection_data['connection'] ?? null;
                $validation_results['total_connections']++;

                if ($this->validate_connection($connection)) {
                    $validation_results['valid_connections']++;
                } else {
                    $validation_results['invalid_connections']++;
                    $validation_results['validation_errors'][] = "Connection $connection_id failed validation";
                }
            }

        } catch (\Exception $e) {
            $validation_results['validation_errors'][] = 'Validation error: ' . $e->getMessage();
        }

        return $validation_results;
    }

    /**
     * Connection state monitoring with timeout detection
     *
     * @param int $timeout Timeout in seconds
     * @return array Monitoring results
     */
    public function monitor_connection_states($timeout = null) {
        $timeout = $timeout ?? $this->config['health_check_interval'];
        $monitoring_results = [
            'monitored_connections' => 0,
            'healthy_connections' => 0,
            'unhealthy_connections' => 0,
            'connections_requiring_cleanup' => 0,
            'timestamp' => time()
        ];

        try {
            if ($this->connection_pool === null) {
                return $monitoring_results;
            }

            $connections = $this->connection_pool->get_pool_status()['connections'];

            foreach ($connections as $connection_id => $connection_data) {
                $connection = $connection_data['connection'] ?? null;
                $last_used = $connection_data['last_used'] ?? 0;

                $monitoring_results['monitored_connections']++;

                if ($this->monitor_connection_state($connection, $last_used)) {
                    $monitoring_results['connections_requiring_cleanup']++;
                } else {
                    $monitoring_results['healthy_connections']++;
                }
            }

        } catch (\Exception $e) {
            error_log('SMO Social: Connection state monitoring error - ' . $e->getMessage());
            $monitoring_results['unhealthy_connections'] = $monitoring_results['monitored_connections'];
        }

        return $monitoring_results;
    }

    /**
     * Connection health checking with timeout detection
     *
     * @param int $timeout Timeout in seconds
     * @return array Health check results
     */
    public function check_connection_health_with_timeout($timeout = null) {
        $timeout = $timeout ?? $this->config['health_check_interval'];
        $health_results = [
            'checked_connections' => 0,
            'healthy_connections' => 0,
            'unhealthy_connections' => 0,
            'health_checks' => [],
            'timestamp' => time()
        ];

        try {
            if ($this->connection_pool === null) {
                return $health_results;
            }

            $connections = $this->connection_pool->get_pool_status()['connections'];

            foreach ($connections as $connection_id => $connection_data) {
                $connection = $connection_data['connection'] ?? null;

                $health_results['checked_connections']++;

                $health_check = $this->check_connection_health($connection);
                $health_results['health_checks'][$connection_id] = $health_check;

                if ($health_check['valid']) {
                    $health_results['healthy_connections']++;
                } else {
                    $health_results['unhealthy_connections']++;
                }
            }

        } catch (\Exception $e) {
            error_log('SMO Social: Connection health checking error - ' . $e->getMessage());
        }

        return $health_results;
    }

    /**
     * Clean up all connections (force cleanup)
     *
     * @return int Number of connections cleaned up
     */
    public function cleanup_all_connections() {
        $cleanup_count = 0;

        try {
            if ($this->connection_pool === null) {
                return $cleanup_count;
            }

            $connections = $this->connection_pool->get_pool_status()['connections'];

            foreach ($connections as $connection_id => $connection_data) {
                // Use the pool's cleanup method to remove idle connections
                $this->connection_pool->cleanup_idle_connections(0); // Cleanup all idle connections
                $cleanup_count++;
            }

            $this->health_stats['cleanup_count'] += $cleanup_count;
            $this->health_stats['last_cleanup_time'] = time();

        } catch (\Exception $e) {
            error_log('SMO Social: Cleanup all connections error - ' . $e->getMessage());
        }

        return $cleanup_count;
    }

    /**
     * Get connection pool statistics with cleanup information
     *
     * @return array Pool statistics with cleanup info
     */
    public function get_connection_pool_stats() {
        $stats = [
            'pool_stats' => [],
            'cleanup_stats' => $this->health_stats,
            'config' => $this->config
        ];

        try {
            if ($this->connection_pool !== null) {
                $stats['pool_stats'] = $this->connection_pool->get_stats();
            }
        } catch (\Exception $e) {
            error_log('SMO Social: Get connection pool stats error - ' . $e->getMessage());
        }

        return $stats;
    }
}