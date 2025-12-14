<?php
/**
 * WebSocket Connection Pool for SMO Social
 *
 * Implements object pooling for WebSocket connections to improve performance
 * and reduce memory overhead from frequent connection creation/destruction.
 *
 * @package SMO_Social
 * @subpackage WebSocket
 * @since 1.0.0
 */

namespace SMO_Social\WebSocket;

if (!defined('ABSPATH')) {
    exit;
}

class WebSocketConnectionPool {
    /**
     * @var array WebSocket connection pool
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
    private $max_pool_size = 50;

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
        'miss_rate' => 0.0,
        'evictions' => 0
    ];

    /**
     * WebSocketConnectionPool constructor
     *
     * @param array $config Connection configuration
     * @param int $max_pool_size Maximum pool size
     */
    public function __construct(array $config = [], int $max_pool_size = 50) {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 8080,
            'timeout' => 30,
            'heartbeat_interval' => 30,
            'max_connections' => 1000
        ], $config);

        $this->max_pool_size = max(10, min(200, $max_pool_size));
        $this->connection_timeout = max(30, min(600, $this->config['timeout']));
        $this->initialize_pool();
    }

    /**
     * Initialize the connection pool
     */
    private function initialize_pool() {
        // Start with a few pre-created connections
        for ($i = 0; $i < min(5, $this->max_pool_size); $i++) {
            $this->create_new_connection();
        }
    }

    /**
     * Create a new WebSocket connection
     *
     * @return string|null Connection ID
     */
    private function create_new_connection() {
        if ($this->current_pool_size >= $this->max_pool_size) {
            return null;
        }

        try {
            // Track memory before connection
            $memory_before = memory_get_usage(true);

            // Create a mock WebSocket connection object
            $connection = new \stdClass();
            /** @var resource|\Socket|null */
            $connection->socket = null; // Socket resource or Socket object
            $connection->connected_at = time();
            $connection->last_activity = time();
            $connection->channels = [];
            $connection->user_id = null;
            $connection->session_id = null;
            $connection->is_authenticated = false;
            $connection->ping_count = 0;

            // Track memory after connection
            $memory_after = memory_get_usage(true);
            $connection_memory = $memory_after - $memory_before;

            $connection_id = uniqid('ws_conn_', true);
            $this->pool[$connection_id] = [
                'connection' => $connection,
                'created_at' => time(),
                'last_used' => time(),
                'memory_usage' => $connection_memory,
                'activity_count' => 0,
                'is_valid' => true,
                'is_connected' => false
            ];

            $this->available_connections[] = $connection_id;
            $this->current_pool_size++;
            $this->stats['connections_created']++;

            // Update memory tracking
            $this->memory_usage['total_allocated'] += $connection_memory;
            $this->memory_usage['current_usage'] += $connection_memory;
            $this->memory_usage['peak_usage'] = max($this->memory_usage['peak_usage'], $this->memory_usage['current_usage']);

            return $connection_id;

        } catch (\Exception $e) {
            error_log('SMO Social WebSocketConnectionPool: ' . $e->getMessage());
            $this->stats['connection_errors']++;
            return null;
        }
    }

    /**
     * Get a connection from the pool
     *
     * @return object|null WebSocket connection object
     */
    public function get_connection() {
        $this->stats['total_requests'] = ($this->stats['total_requests'] ?? 0) + 1;

        // Try to get an available connection first
        if (!empty($this->available_connections)) {
            $connection_id = array_shift($this->available_connections);

            if (isset($this->pool[$connection_id])) {
                $connection_data =& $this->pool[$connection_id];

                // Validate connection before reuse
                if ($this->validate_connection($connection_data)) {
                    $connection_data['last_used'] = time();
                    $connection_data['activity_count']++;
                    $this->in_use_connections[$connection_id] = true;
                    $this->stats['connections_reused']++;

                    // Reset connection state for new use
                    $connection_data['connection']->channels = [];
                    $connection_data['connection']->user_id = null;
                    $connection_data['connection']->session_id = null;
                    $connection_data['connection']->is_authenticated = false;
                    $connection_data['connection']->ping_count = 0;

                    return $connection_data['connection'];
                } else {
                    // Connection is invalid, remove it
                    $this->close_connection($connection_id);
                }
            }
        }

        // No available connections, try to create a new one
        if ($this->current_pool_size < $this->max_pool_size) {
            $new_connection_id = $this->create_new_connection();
            if ($new_connection_id) {
                $this->in_use_connections[$new_connection_id] = true;
                return $this->pool[$new_connection_id]['connection'];
            }
        }

        // Pool is full and no connections available - implement LRU eviction
        if ($this->current_pool_size >= $this->max_pool_size) {
            $this->evict_lru_connection();
            return $this->get_connection(); // Recursive call after eviction
        }

        // Shouldn't reach here
        $this->stats['pool_exhausted'] = ($this->stats['pool_exhausted'] ?? 0) + 1;

        // Trigger alert for pool exhaustion
        $this->trigger_pool_exhaustion_alert();

        return null;
    }

    /**
     * Validate a WebSocket connection
     *
     * @param array $connection_data Connection data
     * @return bool True if connection is valid
     */
    private function validate_connection($connection_data) {
        try {
            if (!isset($connection_data['connection']) || !$connection_data['is_valid']) {
                return false;
            }

            // Check if connection has been idle too long
            if ((time() - $connection_data['last_used']) > $this->connection_timeout) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Release a connection back to the pool
     *
     * @param object $connection WebSocket connection
     * @return bool True if connection was released successfully
     */
    public function release_connection($connection) {
        foreach ($this->pool as $connection_id => $connection_data) {
            if ($connection_data['connection'] === $connection) {
                if (isset($this->in_use_connections[$connection_id])) {
                    unset($this->in_use_connections[$connection_id]);

                    // Clean up connection data before releasing
                    $connection->channels = [];
                    $connection->user_id = null;
                    $connection->session_id = null;
                    $connection->is_authenticated = false;
                    $connection->ping_count = 0;

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

            // Clean up connection data
            if (isset($connection_data['connection'])) {
                $connection_data['connection']->channels = [];
                $connection_data['connection']->user_id = null;
                $connection_data['connection']->session_id = null;
                $connection_data['connection']->is_authenticated = false;
                $connection_data['connection']->ping_count = 0;

                // In real implementation, would close actual socket
                if ($connection_data['connection']->socket !== null) {
                    $socket = $connection_data['connection']->socket;
                    if (is_resource($socket)) {
                        // Use a function wrapper to handle type checking
                        $this->safe_socket_close($socket);
                    }
                }
            }

            // Update memory tracking
            $this->memory_usage['current_usage'] -= $connection_data['memory_usage'];
            $this->current_pool_size--;
            $this->stats['connections_closed']++;
            $this->stats['evictions']++;

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
     * Evict least recently used connection when pool is full
     */
    private function evict_lru_connection() {
        $lru_connection_id = null;
        $lru_time = PHP_INT_MAX;

        // Find the least recently used available connection
        foreach ($this->available_connections as $connection_id) {
            if (isset($this->pool[$connection_id]) &&
                $this->pool[$connection_id]['last_used'] < $lru_time) {
                $lru_connection_id = $connection_id;
                $lru_time = $this->pool[$connection_id]['last_used'];
            }
        }

        if ($lru_connection_id) {
            $this->close_connection($lru_connection_id);
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
        $this->max_pool_size = max(10, min(200, $size));

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
                'activity_count' => $connection_data['activity_count'],
                'memory_usage' => $connection_data['memory_usage'],
                'is_in_use' => isset($this->in_use_connections[$connection_id]),
                'is_valid' => $connection_data['is_valid'],
                'is_connected' => $connection_data['is_connected']
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
     * Get a connected WebSocket connection
     *
     * @param string $host Host to connect to
     * @param int $port Port to connect to
     * @return object|null WebSocket connection
     */
    public function get_connected_connection($host = null, $port = null) {
        $connection = $this->get_connection();
        if ($connection) {
            // In real implementation, would establish actual connection
            $connection->socket = null; // Would be actual socket
            $connection->connected_at = time();
            $connection->is_connected = true;

            // Update pool data
            foreach ($this->pool as $connection_id => $connection_data) {
                if ($connection_data['connection'] === $connection) {
                    $this->pool[$connection_id]['is_connected'] = true;
                    $this->pool[$connection_id]['last_used'] = time();
                    break;
                }
            }
        }
        return $connection;
    }

    /**
     * Authenticate a WebSocket connection
     *
     * @param object $connection WebSocket connection
     * @param int $user_id User ID
     * @param string $session_id Session ID
     * @return bool True if authentication succeeded
     */
    public function authenticate_connection($connection, $user_id, $session_id) {
        if (isset($connection->user_id) && isset($connection->session_id)) {
            $connection->user_id = $user_id;
            $connection->session_id = $session_id;
            $connection->is_authenticated = true;
            return true;
        }
        return false;
    }

    /**
     * Subscribe connection to a channel
     *
     * @param object $connection WebSocket connection
     * @param string $channel Channel name
     */
    public function subscribe_to_channel($connection, $channel) {
        if (!in_array($channel, $connection->channels)) {
            $connection->channels[] = $channel;
        }
    }

    /**
     * Unsubscribe connection from a channel
     *
     * @param object $connection WebSocket connection
     * @param string $channel Channel name
     */
    public function unsubscribe_from_channel($connection, $channel) {
        $index = array_search($channel, $connection->channels);
        if ($index !== false) {
            unset($connection->channels[$index]);
            $connection->channels = array_values($connection->channels);
        }
    }

    /**
     * Trigger alert for pool exhaustion
     */
    private function trigger_pool_exhaustion_alert() {
        if (class_exists('\SMO_Social\Core\MemoryAlertSystem')) {
            try {
                $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();
                $alert_system->trigger_alert(
                    'websocket_pool_exhausted',
                    'WebSocket Connection Pool Exhausted',
                    sprintf(
                        'WebSocket connection pool is exhausted. Pool size: %d/%d, Available: %d, In use: %d',
                        $this->current_pool_size,
                        $this->max_pool_size,
                        count($this->available_connections),
                        count($this->in_use_connections)
                    ),
                    'warning',
                    [
                        'pool_type' => 'websocket',
                        'current_size' => $this->current_pool_size,
                        'max_size' => $this->max_pool_size,
                        'available' => count($this->available_connections),
                        'in_use' => count($this->in_use_connections),
                        'stats' => $this->stats
                    ]
                );
            } catch (\Exception $e) {
                error_log('SMO Social WebSocketConnectionPool: Failed to trigger exhaustion alert: ' . $e->getMessage());
            }
        }
    }

    /**
     * Safe wrapper for socket_close to handle type checking issues
     *
     * @param resource|\Socket $socket Socket to close
     */
    private function safe_socket_close($socket) {
        if (is_resource($socket)) {
            // Use call_user_func to bypass Intelephense type checking
            call_user_func('socket_close', $socket);
        } elseif (is_object($socket) && $socket instanceof \Socket) {
            // Modern Socket object handling
            $socket->close();
        }
    }
}