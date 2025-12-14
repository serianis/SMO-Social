<?php
/**
 * WebSocket Server Connection Manager
 * Manages WebSocket connections for real-time features
 */

namespace SMO_Social\WebSocket;

if (!defined('ABSPATH')) {
    exit;
}

// WordPress functions will be called with global namespace

require_once __DIR__ . '/../consolidated-db-stubs.php';
require_once __DIR__ . '/WebSocketConnectionPool.php';
require_once __DIR__ . '/WebSocketConnectionCleanup.php';

/**
 * WebSocket Server Manager
 * Handles WebSocket server lifecycle, connections, and message routing
 */
class WebSocketServerManager {
    /** @var \Socket|null */
    private $server;
    /** @var array<string, array{socket: \Socket, connected_at: int, last_ping: int, channels: array, user_id: ?int, session_id: ?string}> */
    private $connections = [];
    /** @var array */
    private $channels = [];
    /** @var array */
    private $config;
    /** @var bool */
    private $running = false;
    /** @var WebSocketConnectionPool|null */
    private $connection_pool = null;

    public function __construct() {
        error_log('SMO Debug: WebSocketServerManager constructor called');

        $this->config = [
            'host' => '0.0.0.0',
            'port' => 8080,
            'max_connections' => 1000,
            'heartbeat_interval' => 30,
            'timeout' => 300,
            'ssl' => false,
            'cert_path' => '',
            'key_path' => ''
        ];

        $this->load_config();
        $this->init_hooks();

        error_log('SMO Debug: WebSocketServerManager initialized with config: ' . json_encode($this->config));
        $this->initialize_connection_pool();
    }

    /**
     * Initialize connection pool
     */
    private function initialize_connection_pool() {
        if ($this->connection_pool === null) {
            $pool_size = 50; // Default pool size

            // Get pool size from settings if available
            $settings = get_option('smo_social_settings', []);
            if (isset($settings['websocket_pool_size']) && is_numeric($settings['websocket_pool_size'])) {
                $pool_size = max(10, min(100, intval($settings['websocket_pool_size'])));
            }

            $this->connection_pool = new WebSocketConnectionPool($this->config, $pool_size);
        }
    }

    /**
     * Load WebSocket configuration from WordPress options
     */
    private function load_config() {
        $saved_config = \get_option('smo_websocket_config', []);
        if (!is_array($saved_config)) {
            $saved_config = [];
        }
        $this->config = array_merge($this->config, $saved_config);
        
        // Validate critical configuration
        $this->validate_config();
    }
    
    /**
     * Validate WebSocket configuration
     */
    private function validate_config() {
        // Validate host
        if (empty($this->config['host'])) {
            $this->config['host'] = '127.0.0.1';
        }
        
        // Validate port
        $port = intval($this->config['port']);
        if ($port <= 0 || $port > 65535) {
            $this->config['port'] = 8080;
        }
        
        // Validate connection limits
        $this->config['max_connections'] = max(1, min(1000, intval($this->config['max_connections'])));
        
        // Validate timeouts
        $this->config['timeout'] = max(30, min(3600, intval($this->config['timeout'])));
        $this->config['heartbeat_interval'] = max(10, min(300, intval($this->config['heartbeat_interval'])));
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Only initialize WordPress WebSocket if standalone server is not available
        \add_action('init', array($this, 'check_standalone_server'));
        \add_action('wp_ajax_smo_websocket_status', array($this, 'ajax_get_status'));
        \add_action('wp_ajax_smo_websocket_restart', array($this, 'ajax_restart_server'));
    }

    /**
     * Check if standalone server is available and start WordPress server if needed
     */
    public function check_standalone_server() {
        // Check if standalone server is running
        $standalone_available = $this->check_standalone_server_availability();
        
        if ($standalone_available) {
            error_log('SMO WebSocket: Standalone server detected, WordPress WebSocket disabled');
            return;
        }
        
        // Start WordPress WebSocket server if standalone not available
        $this->start_server();
    }
    
    /**
     * Check if standalone WebSocket server is available
     */
    private function check_standalone_server_availability() {
        $config = $this->config;
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 8080;
        
        // Try to connect to standalone server
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            return false;
        }
        
        // Set timeout for connection attempt
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);
        
        $connected = @socket_connect($socket, $host, $port);
        socket_close($socket);
        
        if ($connected) {
            error_log('SMO WebSocket: Standalone server detected on ' . $host . ':' . $port);
            return true;
        }
        
        return false;
    }

    /**
     * Start WebSocket server (only called when standalone server is not available)
     */
    public function start_server() {
        error_log('SMO WebSocket: Attempting to start WebSocket server');
        error_log('SMO WebSocket: Current running status: ' . ($this->running ? 'true' : 'false'));
        error_log('SMO WebSocket: Config: ' . json_encode($this->config));

        if ($this->running) {
            error_log('SMO WebSocket: WebSocket server already running');
            return;
        }

        try {
            // Check if WebSocket extension is available
            if (!extension_loaded('sockets')) {
                error_log('SMO Social: WebSocket server requires sockets extension - skipping WebSocket initialization');
                error_log('SMO Debug: Sockets extension not available');
                return;
            }

            error_log('SMO WebSocket: Sockets extension available, proceeding with server start');
            error_log('SMO WebSocket: Attempting to bind to ' . $this->config['host'] . ':' . $this->config['port']);

            $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$this->server) {
                $error_msg = 'Failed to create socket: ' . socket_strerror(socket_last_error());
                error_log('SMO WebSocket: ' . $error_msg);
                throw new \Exception($error_msg);
            }
            error_log('SMO WebSocket: Socket created successfully');

            socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
            error_log('SMO WebSocket: SO_REUSEADDR option set');

            if (!socket_bind($this->server, $this->config['host'], $this->config['port'])) {
                $error_msg = 'Failed to bind socket to ' . $this->config['host'] . ':' . $this->config['port'] . ': ' . socket_strerror(socket_last_error());
                error_log('SMO WebSocket: ' . $error_msg);
                throw new \Exception($error_msg);
            }
            error_log('SMO WebSocket: Socket bound successfully to ' . $this->config['host'] . ':' . $this->config['port']);

            if (!socket_listen($this->server, 10)) {
                $error_msg = 'Failed to listen on socket: ' . socket_strerror(socket_last_error());
                error_log('SMO WebSocket: ' . $error_msg);
                throw new \Exception($error_msg);
            }
            error_log('SMO WebSocket: Socket listening successfully');

            socket_set_nonblock($this->server);
            $this->running = true;

            // Start server loop in background
            $this->start_server_loop();

            error_log('SMO Social: WebSocket server started on ' . $this->config['host'] . ':' . $this->config['port']);

        } catch (\Exception $e) {
            error_log('SMO Social: Failed to start WebSocket server: ' . $e->getMessage());
            // Don't throw exception - continue without WebSocket functionality
        }
    }

    /**
     * Stop WebSocket server
     */
    public function stop_server() {
        if (!$this->running) {
            return;
        }

        $this->running = false;

        // Close all connections
        foreach ($this->connections as $connection) {
            if (is_resource($connection)) {
                socket_close($connection);
            }
        }

        if (is_resource($this->server)) {
            socket_close($this->server);
        }

        $this->connections = [];
        $this->channels = [];

        error_log('SMO Social: WebSocket server stopped');
    }

    /**
     * Start server loop for handling connections
     */
    private function start_server_loop() {
        // In a real implementation, this would run in a separate process or thread
        // For WordPress integration, we'll use WordPress cron or AJAX polling
        \add_action('smo_websocket_process_connections', array($this, 'process_connections'));
        \add_action('smo_websocket_cleanup_connections', array($this, 'cleanup_connections'));

        // @noinspection PhpUndefinedFunctionInspection
        if (function_exists('wp_next_scheduled') && !\wp_next_scheduled('smo_websocket_process_connections')) {
            // @noinspection PhpUndefinedFunctionInspection
            if (function_exists('wp_schedule_event')) {
                // @noinspection PhpUndefinedFunctionInspection
                \wp_schedule_event(\time(), 'every_minute', 'smo_websocket_process_connections');
            }
        }

        // @noinspection PhpUndefinedFunctionInspection
        if (function_exists('wp_next_scheduled') && !\wp_next_scheduled('smo_websocket_cleanup_connections')) {
            // @noinspection PhpUndefinedFunctionInspection
            if (function_exists('wp_schedule_event')) {
                // @noinspection PhpUndefinedFunctionInspection
                \wp_schedule_event(\time(), 'hourly', 'smo_websocket_cleanup_connections');
            }
        }
    }

    /**
     * Process incoming connections and messages
     */
    public function process_connections() {
        if (!$this->running || !is_resource($this->server)) {
            return;
        }

        // Accept new connections
        $new_connection = @socket_accept($this->server);
        if ($new_connection !== false) {
            socket_set_nonblock($new_connection);
            $connection_id = uniqid('conn_', true);
            $this->connections[$connection_id] = [
                'socket' => $new_connection,
                'connected_at' => time(),
                'last_ping' => time(),
                'channels' => [],
                'user_id' => null,
                'session_id' => null
            ];
        }

        // Process existing connections
        foreach ($this->connections as $connection_id => $connection) {
            if (isset($connection['socket']) && is_resource($connection['socket'])) {
                $this->process_connection($connection_id, $connection);
            }
        }
    }

    /**
     * Process individual connection
     */
    private function process_connection($connection_id, &$connection) {
        $socket = $connection['socket'];

        // Check for incoming data
        $data = @socket_read($socket, 2048);
        if ($data === false) {
            $error = socket_last_error($socket);
            if ($error !== SOCKET_EWOULDBLOCK) {
                // Connection error, remove connection
                $this->remove_connection($connection_id);
                return;
            }
        } elseif ($data !== '') {
            $this->handle_message($connection_id, $data);
        }

        // Send heartbeat if needed
        if (time() - $connection['last_ping'] > $this->config['heartbeat_interval']) {
            $this->send_ping($connection_id);
        }
    }

    /**
     * Handle incoming WebSocket message
     */
    private function handle_message($connection_id, $data) {
        try {
            $message = json_decode($data, true);
            if (!$message || !isset($message['type'])) {
                return;
            }

            switch ($message['type']) {
                case 'subscribe':
                    $this->handle_subscribe($connection_id, $message);
                    break;
                case 'unsubscribe':
                    $this->handle_unsubscribe($connection_id, $message);
                    break;
                case 'publish':
                    $this->handle_publish($connection_id, $message);
                    break;
                case 'ping':
                    $this->handle_ping($connection_id);
                    break;
                case 'authenticate':
                    $this->handle_authenticate($connection_id, $message);
                    break;
                default:
                    // Handle custom message types
                    \do_action('smo_websocket_message', $connection_id, $message);
            }

        } catch (\Exception $e) {
            error_log('SMO Social: WebSocket message error: ' . $e->getMessage());
        }
    }

    /**
     * Handle channel subscription
     */
    private function handle_subscribe($connection_id, $message) {
        $channel = $message['channel'] ?? '';
        if (empty($channel)) {
            return;
        }

        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }

        $this->channels[$channel][$connection_id] = true;
        $this->connections[$connection_id]['channels'][] = $channel;

        $this->send_message($connection_id, [
            'type' => 'subscribed',
            'channel' => $channel
        ]);
    }

    /**
     * Handle channel unsubscription
     */
    private function handle_unsubscribe($connection_id, $message) {
        $channel = $message['channel'] ?? '';
        if (empty($channel)) {
            return;
        }

        if (isset($this->channels[$channel][$connection_id])) {
            unset($this->channels[$channel][$connection_id]);
        }

        $key = array_search($channel, $this->connections[$connection_id]['channels']);
        if ($key !== false) {
            unset($this->connections[$connection_id]['channels'][$key]);
        }

        $this->send_message($connection_id, [
            'type' => 'unsubscribed',
            'channel' => $channel
        ]);
    }

    /**
     * Handle message publishing
     */
    private function handle_publish($connection_id, $message) {
        $channel = $message['channel'] ?? '';
        $data = $message['data'] ?? [];

        if (empty($channel)) {
            return;
        }

        $this->publish_to_channel($channel, $data, $connection_id);
    }

    /**
     * Handle ping message
     */
    private function handle_ping($connection_id) {
        $this->connections[$connection_id]['last_ping'] = time();
        $this->send_message($connection_id, ['type' => 'pong']);
    }

    /**
     * Handle authentication
     */
    private function handle_authenticate($connection_id, $message) {
        $token = $message['token'] ?? '';
        $user_id = $this->validate_token($token);

        if ($user_id) {
            $this->connections[$connection_id]['user_id'] = $user_id;
            $this->send_message($connection_id, [
                'type' => 'authenticated',
                'user_id' => $user_id
            ]);
        } else {
            $this->send_message($connection_id, [
                'type' => 'authentication_failed'
            ]);
        }
    }

    /**
     * Publish message to channel
     */
    public function publish_to_channel($channel, $data, $exclude_connection = null) {
        if (!isset($this->channels[$channel])) {
            return;
        }

        $message = [
            'type' => 'message',
            'channel' => $channel,
            'data' => $data,
            'timestamp' => \current_time('mysql')
        ];

        foreach ($this->channels[$channel] as $connection_id => $active) {
            if ($connection_id !== $exclude_connection) {
                $this->send_message($connection_id, $message);
            }
        }
    }

    /**
     * Send message to specific connection
     */
    private function send_message($connection_id, $message) {
        if (!isset($this->connections[$connection_id])) {
            return false;
        }

        $socket = $this->connections[$connection_id]['socket'];
        if (!is_resource($socket)) {
            return false;
        }
        
        $data = json_encode($message) . "\n";

        $result = @socket_write($socket, $data, strlen($data));
        return $result !== false;
    }

    /**
     * Send ping to connection
     */
    private function send_ping($connection_id) {
        $this->send_message($connection_id, ['type' => 'ping']);
    }

    /**
     * Validate authentication token
     */
    private function validate_token($token) {
        // Simple token validation - in production, use proper JWT or similar
        $stored_tokens = \get_option('smo_websocket_tokens', []);

        // Ensure $stored_tokens is an array before iterating
        if (!is_array($stored_tokens)) {
            return false;
        }

        foreach ($stored_tokens as $user_id => $user_token) {
            if ($user_token === $token) {
                return $user_id;
            }
        }

        return false;
    }

    /**
     * Generate authentication token for user
     */
    public function generate_token($user_id) {
        $token = \wp_generate_password(32, false);
        $stored_tokens = \get_option('smo_websocket_tokens', []);
        $stored_tokens[$user_id] = $token;
        \update_option('smo_websocket_tokens', $stored_tokens);

        return $token;
    }

    /**
     * Remove connection
     */
    private function remove_connection($connection_id) {
        if (!isset($this->connections[$connection_id])) {
            return;
        }

        $connection = $this->connections[$connection_id];

        // Remove from channels
        foreach ($connection['channels'] as $channel) {
            if (isset($this->channels[$channel][$connection_id])) {
                unset($this->channels[$channel][$connection_id]);
            }
        }

        // Close socket
        if (is_resource($connection['socket'])) {
            socket_close($connection['socket']);
        }

        unset($this->connections[$connection_id]);
    }

    /**
     * Cleanup inactive connections
     */
    public function cleanup_connections() {
        $now = time();
        $timeout = $this->config['timeout'];

        foreach ($this->connections as $connection_id => $connection) {
            if ($now - $connection['last_ping'] > $timeout) {
                $this->remove_connection($connection_id);
            }
        }
    }

    /**
     * Get server status
     */
    public function get_status() {
        return [
            'running' => $this->running,
            'connections' => count($this->connections),
            'channels' => count($this->channels),
            'config' => $this->config
        ];
    }

    /**
     * Set configuration for testing purposes
     * 
     * @param array $config Configuration array to set
     */
    public function test_set_config($config) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration for testing purposes
     * 
     * @return array Current configuration
     */
    public function test_get_config() {
        return $this->config;
    }

    /**
     * Public wrapper for validate_config method (for testing)
     */
    public function test_validate_config() {
        $this->validate_config();
    }

    /**
     * Public wrapper for check_standalone_server_availability method (for testing)
     * 
     * @return bool True if standalone server is available
     */
    public function test_check_standalone_server_availability() {
        return $this->check_standalone_server_availability();
    }

    /**
     * AJAX handler for server status
     */
    public function ajax_get_status() {
        \check_ajax_referer('smo_social_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_die(__('Insufficient permissions'));
        }

        \wp_send_json_success($this->get_status());
    }

    /**
     * AJAX handler for restarting server
     */
    public function ajax_restart_server() {
        \check_ajax_referer('smo_social_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_die(__('Insufficient permissions'));
        }

        $this->stop_server();
        $this->start_server();

        \wp_send_json_success(['message' => 'WebSocket server restarted']);
    }

    /**
     * Get client-side JavaScript for WebSocket connection
     */
    public function get_client_js() {
        $protocol = $this->config['ssl'] ? 'wss' : 'ws';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $port = $this->config['port'];

        return "
        class SMOWebSocket {
            constructor() {
                this.ws = null;
                this.channels = [];
                this.reconnectAttempts = 0;
                this.maxReconnectAttempts = 5;
                this.reconnectInterval = 1000;
                this.token = null;
            }

            connect(token = null) {
                this.token = token;
                const protocol = " . ($this->config['ssl'] ? "'wss'" : "'ws'") . ";
                const host = '" . $host . "';
                const port = " . $port . ";

                this.ws = new WebSocket(protocol + '://' + host + ':' + port);

                this.ws.onopen = (event) => {
                    console.log('WebSocket connected');
                    this.reconnectAttempts = 0;
                    if (this.token) {
                        this.authenticate(this.token);
                    }
                };

                this.ws.onmessage = (event) => {
                    const message = JSON.parse(event.data);
                    this.handleMessage(message);
                };

                this.ws.onclose = (event) => {
                    console.log('WebSocket disconnected');
                    this.handleReconnect();
                };

                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                };
            }

            authenticate(token) {
                this.send({type: 'authenticate', token: token});
            }

            subscribe(channel) {
                this.send({type: 'subscribe', channel: channel});
                if (!this.channels.includes(channel)) {
                    this.channels.push(channel);
                }
            }

            unsubscribe(channel) {
                this.send({type: 'unsubscribe', channel: channel});
                const index = this.channels.indexOf(channel);
                if (index > -1) {
                    this.channels.splice(index, 1);
                }
            }

            publish(channel, data) {
                this.send({type: 'publish', channel: channel, data: data});
            }

            send(message) {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.ws.send(JSON.stringify(message));
                }
            }

            handleMessage(message) {
                switch (message.type) {
                    case 'message':
                        this.onMessage(message.channel, message.data);
                        break;
                    case 'subscribed':
                        console.log('Subscribed to channel:', message.channel);
                        break;
                    case 'unsubscribed':
                        console.log('Unsubscribed from channel:', message.channel);
                        break;
                    case 'authenticated':
                        console.log('Authenticated as user:', message.user_id);
                        break;
                    case 'ping':
                        this.send({type: 'ping'});
                        break;
                }
            }

            handleReconnect() {
                if (this.reconnectAttempts < this.maxReconnectAttempts) {
                    this.reconnectAttempts++;
                    setTimeout(() => {
                        console.log('Attempting to reconnect... (' + this.reconnectAttempts + ')');
                        this.connect(this.token);
                    }, this.reconnectInterval * this.reconnectAttempts);
                }
            }

            onMessage(channel, data) {
                // Override this method to handle incoming messages
                console.log('Received message on channel', channel, ':', data);
            }
        }
        ";
   }

   /**
    * Get a WebSocket connection from the pool
    *
    * @return object|null WebSocket connection
    */
   public function get_connection_from_pool() {
       if ($this->connection_pool === null) {
           $this->initialize_connection_pool();
       }

       return $this->connection_pool->get_connection();
   }

   /**
    * Release a WebSocket connection back to the pool
    *
    * @param object $connection WebSocket connection
    * @return bool True if connection was released successfully
    */
   public function release_connection_to_pool($connection) {
       if ($this->connection_pool !== null) {
           return $this->connection_pool->release_connection($connection);
       }
       return false;
   }

   /**
    * Get WebSocket connection pool statistics
    *
    * @return array Pool statistics
    */
   public function get_connection_pool_stats() {
       if ($this->connection_pool === null) {
           $this->initialize_connection_pool();
       }

       return $this->connection_pool->get_stats();
   }

   /**
    * Cleanup WebSocket connection pool
    */
   public function cleanup_connection_pool() {
       if ($this->connection_pool !== null) {
           $this->connection_pool->cleanup_idle_connections();
       }
   }

   /**
    * Get WebSocket connection cleanup instance
    *
    * @return WebSocketConnectionCleanup
    */
   public function get_connection_cleanup() {
       static $cleanup_instance = null;

       if ($cleanup_instance === null) {
           $cleanup_instance = new WebSocketConnectionCleanup();
       }

       return $cleanup_instance;
   }

   /**
    * Perform comprehensive WebSocket connection cleanup
    *
    * @return array Cleanup results
    */
   public function perform_comprehensive_cleanup() {
       $cleanup = $this->get_connection_cleanup();
       return [
           'idle_cleanup' => $cleanup->cleanup_idle_connections(),
           'automatic_cleanup' => $cleanup->automatic_cleanup(),
           'health_stats' => $cleanup->get_health_statistics(),
           'validation_results' => $cleanup->perform_comprehensive_validation()
       ];
   }

   /**
    * Monitor WebSocket connection health
    *
    * @return array Health monitoring results
    */
   public function monitor_connection_health() {
       $cleanup = $this->get_connection_cleanup();
       return [
           'health_checks' => $cleanup->check_connection_health_with_timeout(),
           'monitoring_results' => $cleanup->monitor_connection_states(),
           'pool_stats' => $cleanup->get_connection_pool_stats(),
           'websocket_stats' => $cleanup->get_websocket_cleanup_stats()
       ];
   }

   /**
    * Enhanced cleanup connections method
    */
   public function enhanced_cleanup_connections() {
       // Original cleanup logic
       $this->original_cleanup_connections();

       // Additional cleanup using the new cleanup mechanism
       $cleanup = $this->get_connection_cleanup();
       $cleanup->cleanup_idle_connections();

       // Check memory usage and cleanup if needed
       $cleanup->check_memory_based_cleanup();
   }

   /**
    * Original cleanup connections method (renamed to avoid conflict)
    */
   private function original_cleanup_connections() {
       $now = time();
       $timeout = $this->config['timeout'];

       foreach ($this->connections as $connection_id => $connection) {
           if ($now - $connection['last_ping'] > $timeout) {
               $this->remove_connection($connection_id);
           }
       }
   }

   /**
    * Get a connected WebSocket connection from the pool
    *
    * @return object|null Connected WebSocket connection
    */
   public function get_connected_connection_from_pool() {
       if ($this->connection_pool === null) {
           $this->initialize_connection_pool();
       }

       return $this->connection_pool->get_connected_connection($this->config['host'], $this->config['port']);
   }
}
