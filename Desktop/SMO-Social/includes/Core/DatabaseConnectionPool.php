<?php
/**
 * Database Connection Pool for SMO Social
 *
 * Implements object pooling for database connections to improve performance
 * and reduce memory overhead from frequent connection creation/destruction.
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseConnectionPool {
    /**
     * @var array Database connection pool
     */
    private $pool = [];

    /**
     * @var array Available connections
     */
    private $available_connections = [];

    /**
     * @var array In-use connections
     */
    private $in_use_connections = [];

    /**
     * @var int Maximum pool size
     */
    private $max_pool_size = 10;

    /**
     * @var int Current pool size
     */
    private $current_pool_size = 0;

    /**
     * @var int Connection timeout in seconds
     */
    private $connection_timeout = 300;

    /**
     * @var array Connection configuration
     */
    private $config = [];

    /**
     * @var array Memory usage tracking
     */
    private $memory_usage = [
        'total_allocated' => 0,
        'peak_usage' => 0,
        'current_usage' => 0
    ];

    /**
     * @var array Pool statistics
     */
    private $stats = [
        'connections_created' => 0,
        'connections_reused' => 0,
        'connections_closed' => 0,
        'connection_errors' => 0,
        'hit_rate' => 0.0,
        'miss_rate' => 0.0
    ];

    /**
     * DatabaseConnectionPool constructor
     *
     * @param array $config Database configuration
     * @param int $max_pool_size Maximum pool size
     */
    public function __construct(array $config = [], int $max_pool_size = 10) {
        global $wpdb;

        // Safely get database configuration with proper fallback logic
        $db_host = $this->safe_constant_value('DB_HOST');
        $db_user = $this->safe_constant_value('DB_USER');
        $db_password = $this->safe_constant_value('DB_PASSWORD');
        $db_name = $this->safe_constant_value('DB_NAME');

        // Fallback to $wpdb if available in WordPress context
        $wpdb_host = isset($wpdb) ? ($wpdb->dbhost ?? null) : null;
        $wpdb_user = isset($wpdb) ? ($wpdb->dbuser ?? null) : null;
        $wpdb_password = isset($wpdb) ? ($wpdb->dbpassword ?? null) : null;
        $wpdb_name = isset($wpdb) ? ($wpdb->dbname ?? null) : null;

        $this->config = array_merge([
            'host' => $db_host ?? $wpdb_host ?? 'localhost',
            'user' => $db_user ?? $wpdb_user ?? 'root',
            'password' => $db_password ?? $wpdb_password ?? '',
            'database' => $db_name ?? $wpdb_name ?? 'wordpress',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ], $config);

        $this->max_pool_size = max(1, min(50, $max_pool_size));
        $this->initialize_pool();
    }

    /**
     * Safely get constant value without triggering undefined constant errors
     *
     * @param string $constant_name Constant name
     * @return string|null Constant value or null if not defined
     */
    private function safe_constant_value($constant_name) {
        // Use defined() check to avoid undefined constant errors in namespace context
        if (defined($constant_name)) {
            return constant($constant_name);
        }
        return null;
    }

    /**
     * Initialize the connection pool
     */
    private function initialize_pool() {
        // Pre-create some connections to warm up the pool
        for ($i = 0; $i < min(3, $this->max_pool_size); $i++) {
            $this->create_new_connection();
        }
    }

    /**
     * Create a new database connection
     *
     * @return \mysqli|null Database connection
     */
    private function create_new_connection() {
        if ($this->current_pool_size >= $this->max_pool_size) {
            return null;
        }

        try {
            // Track memory before connection
            $memory_before = memory_get_usage(true);

            $connection = mysqli_init();

            if (!$connection) {
                throw new \Exception('Failed to initialize MySQL connection');
            }

            // Set connection options
            mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
            mysqli_options($connection, MYSQLI_OPT_READ_TIMEOUT, $this->connection_timeout);

            // Establish connection
            if (!mysqli_real_connect(
                $connection,
                $this->config['host'],
                $this->config['user'],
                $this->config['password'],
                $this->config['database'],
                null,
                null,
                MYSQLI_CLIENT_FOUND_ROWS
            )) {
                throw new \Exception('Connection failed: ' . mysqli_connect_error());
            }

            // Set character set
            if (!mysqli_set_charset($connection, $this->config['charset'])) {
                throw new \Exception('Failed to set character set');
            }

            // Track memory after connection
            $memory_after = memory_get_usage(true);
            $connection_memory = $memory_after - $memory_before;

            $connection_id = uniqid('db_conn_', true);
            $this->pool[$connection_id] = [
                'connection' => $connection,
                'created_at' => time(),
                'last_used' => time(),
                'memory_usage' => $connection_memory,
                'query_count' => 0,
                'is_valid' => true
            ];

            $this->available_connections[] = $connection_id;
            $this->current_pool_size++;
            $this->stats['connections_created']++;

            // Update memory tracking
            $this->memory_usage['total_allocated'] += $connection_memory;
            $this->memory_usage['current_usage'] += $connection_memory;
            $this->memory_usage['peak_usage'] = max($this->memory_usage['peak_usage'], $this->memory_usage['current_usage']);

            return $connection;

        } catch (\Exception $e) {
            error_log('SMO Social DatabaseConnectionPool: ' . $e->getMessage());
            $this->stats['connection_errors']++;
            return null;
        }
    }

    /**
     * Get a connection from the pool
     *
     * @return \mysqli|null Database connection
     */
    public function get_connection() {
        $this->stats['total_requests'] = ($this->stats['total_requests'] ?? 0) + 1;

        // Try to get an available connection first
        if (!empty($this->available_connections)) {
            $connection_id = array_shift($this->available_connections);

            if (isset($this->pool[$connection_id])) {
                $connection_data = $this->pool[$connection_id];

                // Validate connection before reuse
                if ($this->validate_connection($connection_data['connection'])) {
                    $connection_data['last_used'] = time();
                    $connection_data['query_count']++;
                    $this->in_use_connections[$connection_id] = true;
                    $this->stats['connections_reused']++;

                    return $connection_data['connection'];
                } else {
                    // Connection is invalid, remove it
                    $this->close_connection($connection_id);
                }
            }
        }

        // No available connections, try to create a new one
        if ($this->current_pool_size < $this->max_pool_size) {
            $connection = $this->create_new_connection();
            if ($connection) {
                // Find the connection ID for tracking
                foreach ($this->pool as $connection_id => $connection_data) {
                    if ($connection_data['connection'] === $connection) {
                        $this->in_use_connections[$connection_id] = true;
                        return $connection;
                    }
                }
            }
        }

        // Pool is full and no connections available
        $this->stats['pool_exhausted'] = ($this->stats['pool_exhausted'] ?? 0) + 1;

        // Trigger alert for pool exhaustion
        $this->trigger_pool_exhaustion_alert();

        return null;
    }

    /**
     * Validate a database connection
     *
     * @param \mysqli $connection Database connection
     * @return bool True if connection is valid
     */
    private function validate_connection($connection) {
        try {
            // Check if connection is a valid mysqli object
            if (!($connection instanceof \mysqli)) {
                return false;
            }

            // Test connection with a simple query instead of deprecated mysqli_ping
            // This is more reliable and not deprecated
            $result = mysqli_query($connection, 'SELECT 1');
            if ($result !== false) {
                mysqli_free_result($result);
                return true;
            }

            // Check if connection is still alive using errno
            return $connection->errno === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Release a connection back to the pool
     *
     * @param \mysqli $connection Database connection
     * @return bool True if connection was released successfully
     */
    public function release_connection($connection) {
        foreach ($this->pool as $connection_id => $connection_data) {
            if ($connection_data['connection'] === $connection) {
                if (isset($this->in_use_connections[$connection_id])) {
                    unset($this->in_use_connections[$connection_id]);
                    $this->available_connections[] = $connection_id;
                    $this->pool[$connection_id]['last_used'] = time();
                    return true;
                }
                return false;
            }
        }
        return false;
    }

    /**
     * Close a connection and remove it from the pool
     *
     * @param string $connection_id Connection ID
     */
    private function close_connection($connection_id) {
        if (isset($this->pool[$connection_id])) {
            $connection_data = $this->pool[$connection_id];

            // Close the connection (works with mysqli objects)
            if ($connection_data['connection'] instanceof \mysqli) {
                $connection_data['connection']->close();
            }

            // Update memory tracking
            $this->memory_usage['current_usage'] -= $connection_data['memory_usage'];
            $this->current_pool_size--;
            $this->stats['connections_closed']++;

            // Remove from all tracking arrays
            unset($this->pool[$connection_id]);
            unset($this->in_use_connections[$connection_id]);

            // Remove from available connections if present
            $index = array_search($connection_id, $this->available_connections);
            if ($index !== false) {
                unset($this->available_connections[$index]);
            }
        }
    }

    /**
     * Clean up idle connections
     *
     * @param int $max_idle_time Maximum idle time in seconds
     */
    public function cleanup_idle_connections($max_idle_time = 300) {
        $current_time = time();
        $connections_to_remove = [];

        foreach ($this->pool as $connection_id => $connection_data) {
            if (!isset($this->in_use_connections[$connection_id]) &&
                ($current_time - $connection_data['last_used']) > $max_idle_time) {
                $connections_to_remove[] = $connection_id;
            }
        }

        foreach ($connections_to_remove as $connection_id) {
            $this->close_connection($connection_id);
        }
    }

    /**
     * Get pool statistics
     *
     * @return array Pool statistics
     */
    public function get_stats() {
        $total_requests = $this->stats['total_requests'] ?? 1;
        $this->stats['hit_rate'] = $total_requests > 0
            ? ($this->stats['connections_reused'] / $total_requests)
            : 0.0;
        $this->stats['miss_rate'] = $total_requests > 0
            ? (($total_requests - $this->stats['connections_reused']) / $total_requests)
            : 0.0;

        return array_merge($this->stats, [
            'current_pool_size' => $this->current_pool_size,
            'available_connections' => count($this->available_connections),
            'in_use_connections' => count($this->in_use_connections),
            'max_pool_size' => $this->max_pool_size,
            'memory_usage' => $this->memory_usage
        ]);
    }

    /**
     * Get memory usage information
     *
     * @return array Memory usage data
     */
    public function get_memory_usage() {
        return $this->memory_usage;
    }

    /**
     * Set maximum pool size
     *
     * @param int $size Maximum pool size
     */
    public function set_max_pool_size($size) {
        $this->max_pool_size = max(1, min(50, $size));

        // If reducing pool size, cleanup excess connections
        if ($this->current_pool_size > $this->max_pool_size) {
            $this->cleanup_excess_connections();
        }
    }

    /**
     * Cleanup excess connections when pool size is reduced
     */
    private function cleanup_excess_connections() {
        while ($this->current_pool_size > $this->max_pool_size) {
            // Find the least recently used available connection
            $lru_connection_id = null;
            $lru_time = PHP_INT_MAX;

            foreach ($this->available_connections as $connection_id) {
                if (isset($this->pool[$connection_id]) &&
                    $this->pool[$connection_id]['last_used'] < $lru_time) {
                    $lru_connection_id = $connection_id;
                    $lru_time = $this->pool[$connection_id]['last_used'];
                }
            }

            if ($lru_connection_id) {
                $this->close_connection($lru_connection_id);
            } else {
                break; // No more connections to remove
            }
        }
    }

    /**
     * Get current pool size
     *
     * @return int Current pool size
     */
    public function get_current_pool_size() {
        return $this->current_pool_size;
    }

    /**
     * Get available connection count
     *
     * @return int Available connections
     */
    public function get_available_connections_count() {
        return count($this->available_connections);
    }

    /**
     * Clear the entire connection pool
     */
    public function clear_pool() {
        foreach ($this->pool as $connection_id => $connection_data) {
            $this->close_connection($connection_id);
        }

        $this->pool = [];
        $this->available_connections = [];
        $this->in_use_connections = [];
        $this->current_pool_size = 0;
        $this->memory_usage = [
            'total_allocated' => 0,
            'peak_usage' => 0,
            'current_usage' => 0
        ];
    }

    /**
     * Get connection pool status
     *
     * @return array Pool status information
     */
    public function get_pool_status() {
        $connections_info = [];
        foreach ($this->pool as $connection_id => $connection_data) {
            $connections_info[] = [
                'id' => $connection_id,
                'created_at' => $connection_data['created_at'],
                'last_used' => $connection_data['last_used'],
                'query_count' => $connection_data['query_count'],
                'memory_usage' => $connection_data['memory_usage'],
                'is_in_use' => isset($this->in_use_connections[$connection_id]),
                'is_valid' => $connection_data['is_valid']
            ];
        }

        return [
            'connections' => $connections_info,
            'pool_size' => $this->current_pool_size,
            'max_pool_size' => $this->max_pool_size,
            'available' => count($this->available_connections),
            'in_use' => count($this->in_use_connections),
            'memory_usage' => $this->memory_usage,
            'stats' => $this->get_stats()
        ];
    }

    /**
     * Trigger alert for pool exhaustion
     */
    private function trigger_pool_exhaustion_alert() {
        if (class_exists('\SMO_Social\Core\MemoryAlertSystem')) {
            try {
                $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();
                $alert_system->trigger_alert(
                    'database_pool_exhausted',
                    'Database Connection Pool Exhausted',
                    sprintf(
                        'Database connection pool is exhausted. Pool size: %d/%d, Available: %d, In use: %d',
                        $this->current_pool_size,
                        $this->max_pool_size,
                        count($this->available_connections),
                        count($this->in_use_connections)
                    ),
                    'warning',
                    [
                        'pool_type' => 'database',
                        'current_size' => $this->current_pool_size,
                        'max_size' => $this->max_pool_size,
                        'available' => count($this->available_connections),
                        'in_use' => count($this->in_use_connections),
                        'stats' => $this->stats
                    ]
                );
            } catch (\Exception $e) {
                error_log('SMO Social DatabaseConnectionPool: Failed to trigger exhaustion alert: ' . $e->getMessage());
            }
        }
    }
}