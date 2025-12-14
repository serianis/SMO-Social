<?php
/**
 * PHP type stubs for Intelephense
 * 
 * This file provides type declarations that Intelephense can recognize
 * for superglobal variables and other PHP built-in functions.
 * Note: This file should be included in non-WordPress development environments
 * to provide proper type hints for Intelephense.
 * 
 * DO NOT EXECUTE THIS FILE - IT'S FOR TYPE STUBBING ONLY
 */

namespace {
    /**
     * @var array $GLOBALS Global variables array
     * @global array $GLOBALS
     */
    // Note: $GLOBALS is a superglobal and cannot be directly assigned

    /**
     * @var array $_SERVER Server variables array
     * @global array $_SERVER
     */
    // Note: $_SERVER is a superglobal and cannot be directly assigned

    /**
     * Mock WordPress Database Class for Intelephense
     * 
     * This class provides method signatures that Intelephense can recognize
     * for WordPress database operations.
     */
    if (!class_exists('wpdb')) {
        class wpdb {
            /**
             * @var string Database prefix
             */
            public $prefix;
            
            /**
             * Prepares a SQL query for safe execution
             * 
             * @param string $query SQL query with placeholders
             * @param mixed ...$args Arguments to substitute
             * @return string SQL query
             */
            public function prepare($query, ...$args) {
                return vsprintf(str_replace(['%d', '%s', '%f'], ['%d', '%s', '%f'], $query), $args);
            }
            
            /**
             * Execute a query
             * 
             * @param string $query SQL query
             * @return int|false Number of rows affected or false on error
             */
            public function query($query) {
                return 0;
            }
            
            /**
             * Get results from database
             * 
             * @param string $query SQL query
             * @param string $output Output type
             * @return array Results
             */
            public function get_results($query, $output = 'OBJECT') {
                return array();
            }
            
            /**
             * Get single variable from database
             * 
             * @param string $query SQL query
             * @return mixed Variable value
             */
            public function get_var($query) {
                return null;
            }
            
            /**
             * Insert row into database
             * 
             * @param string $table Table name
             * @param array $data Data to insert
             * @param array $format Format specification
             * @return int|false Insert ID or false
             */
            public function insert($table, $data, $format = array()) {
                return 1;
            }
            
            /**
             * Update database row
             * 
             * @param string $table Table name
             * @param array $data Data to update
             * @param array $where Where conditions
             * @param array $format Format specification
             * @param array $where_format Where format specification
             * @return int|false Rows affected or false
             */
            public function update($table, $data, $where, $format = array(), $where_format = array()) {
                return 1;
            }
            
            /**
             * Delete from database
             * 
             * @param string $table Table name
             * @param array $where Where conditions
             * @param array $format Format specification
             * @return int|false Rows affected or false
             */
            public function delete($table, $where, $format = array()) {
                return 1;
            }
        }
    }

    /**
     * Mock Redis Class for Intelephense
     * 
     * This class provides method signatures that Intelephense can recognize
     * for Redis operations.
     */
    if (!class_exists('Redis')) {
        class Redis {
            /**
             * Connect to Redis server
             * 
             * @param string $host Redis host
             * @param int $port Redis port
             * @param float $timeout Connection timeout
             * @return bool True on success
             */
            public function connect($host, $port = 6379, $timeout = 0.0) {
                return true;
            }
            
            /**
             * Test connectivity
             * 
             * @return string Redis response
             */
            public function ping() {
                return '+PONG';
            }
            
            /**
             * Get value from Redis
             * 
             * @param string $key Key to get
             * @return string|bool Value or false if not found
             */
            public function get($key) {
                return false;
            }
            
            /**
             * Set value in Redis with expiration
             * 
             * @param string $key Key to set
             * @param string $value Value to set
             * @param int $timeout Expiration time in seconds
             * @return bool True on success
             */
            public function setex($key, $timeout, $value) {
                return true;
            }
            
            /**
             * Delete keys from Redis
             * 
             * @param string|array $keys Keys to delete
             * @return int Number of deleted keys
             */
            public function del($keys) {
                return 0;
            }
            
            /**
             * Get keys matching pattern
             * 
             * @param string $pattern Pattern to match
             * @return array Matching keys
             */
            public function keys($pattern) {
                return array();
            }
            
            /**
             * Get Redis info
             * 
             * @param string $section Info section
             * @return array Redis info
             */
            public function info($section = '') {
                return array(
                    'used_memory_human' => '1M',
                    'used_memory_peak_human' => '2M'
                );
            }
        }
    }

    /**
     * Mock Exception Class for Intelephense
     * 
     * This class provides method signatures that Intelephense can recognize
     * for exception handling.
     */
    if (!class_exists('Exception')) {
        class Exception {
            /**
             * @var string Exception message
             */
            protected $message;
            
            /**
             * @var int Exception code
             */
            protected $code;
            
            /**
             * Constructor
             * 
             * @param string $message Exception message
             * @param int $code Exception code
             */
            public function __construct($message = '', $code = 0) {
                $this->message = $message;
                $this->code = $code;
            }
            
            /**
             * Get exception message
             * 
             * @return string Exception message
             */
            public function getMessage() {
                return $this->message;
            }
            
            /**
             * Get exception code
             * 
             * @return int Exception code
             */
            public function getCode() {
                return $this->code;
            }
            
            /**
             * Get stack trace as string
             * 
             * @return string Stack trace
             */
            public function __toString() {
                return 'Exception: ' . $this->message;
            }
        }
    }

    /**
     * Mock WordPress WP_Error Class for Intelephense
     * 
     * This class provides method signatures that Intelephense can recognize
     * for WordPress error handling.
     */
    if (!class_exists('WP_Error')) {
        class WP_Error {
            /**
             * Error code
             * @var string
             */
            public $errors = array();
            
            /**
             * Error messages
             * @var array
             */
            public $error_data = array();
            
            /**
             * Add error
             * 
             * @param string $code Error code
             * @param string $message Error message
             * @param mixed $data Error data
             */
            public function add($code, $message, $data = '') {
                // Mock implementation
            }
            
            /**
             * Get error code
             * 
             * @return string Error code
             */
            public function get_error_code() {
                return 'error';
            }
            
            /**
             * Get error message
             * 
             * @param string $code Error code
             * @return string Error message
             */
            public function get_error_message($code = '') {
                return 'Error message';
            }
            
            /**
             * Check if error has specific code
             * 
             * @param string $code Error code
             * @return bool True if error has code
             */
            public function has_errors() {
                return false;
            }
        }
    }

    /**
     * Mock WordPress wp_json_encode function
     * 
     * @param mixed $data Data to encode
     * @param int $options JSON encode options
     * @param int $depth Maximum depth
     * @return string JSON string
     */
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0, $depth = 512) {
            return json_encode($data, $options, $depth);
        }
    }
    
    /**
     * Mock WordPress wp_send_json_success function
     * 
     * @param mixed $data Response data
     * @param int $status_code HTTP status code
     */
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data = null, $status_code = null) {
            wp_send_json(array('success' => true, 'data' => $data), $status_code);
        }
    }
    
    /**
     * Mock WordPress wp_send_json_error function
     * 
     * @param mixed $data Error data
     * @param int $status_code HTTP status code
     */
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null, $status_code = null) {
            wp_send_json(array('success' => false, 'data' => $data), $status_code);
        }
    }
    
    /**
     * Mock WordPress wp_send_json function
     * 
     * @param mixed $response Response data
     * @param int $status_code HTTP status code
     */
    if (!function_exists('wp_send_json')) {
        function wp_send_json($response, $status_code = null) {
            header('Content-Type: application/json');
            echo wp_json_encode($response);
            if ($status_code) {
                http_response_code($status_code);
            }
            exit;
        }
    }

    /**
     * Mock Socket Class for Intelephense
     * 
     * This class provides method signatures that Intelephense can recognize
     * for Socket operations used in WebSocket functionality.
     */
    if (!class_exists('Socket')) {
        class Socket {
            /**
             * Close the socket
             * 
             * @return bool True on success
             */
            public function close() {
                return true;
            }
            
            /**
             * Bind socket to address
             * 
             * @param string $address Address to bind to
             * @param int $port Port to bind to
             * @return bool True on success
             */
            public function bind($address, $port = 0) {
                return true;
            }
            
            /**
             * Listen on socket
             * 
             * @param int $backlog Maximum backlog connections
             * @return bool True on success
             */
            public function listen($backlog = 0) {
                return true;
            }
            
            /**
             * Accept incoming connection
             * 
             * @return Socket|null New socket or null on failure
             */
            public function accept() {
                return new Socket();
            }
            
            /**
             * Connect to remote address
             * 
             * @param string $address Remote address
             * @param int $port Remote port
             * @return bool True on success
             */
            public function connect($address, $port = 0) {
                return true;
            }
            
            /**
             * Read from socket
             * 
             * @param int $length Maximum bytes to read
             * @return string|false Data read or false on error
             */
            public function read($length) {
                return '';
            }
            
            /**
             * Write to socket
             * 
             * @param string $buffer Data to write
             * @param int $length Maximum bytes to write
             * @return int|false Bytes written or false on error
             */
            public function write($buffer, $length = 0) {
                return strlen($buffer);
            }
        }
    }

    /**
     * Stub class for SMO_Social\Admin\Views\MediaLibrary
     * This provides type hints for Intelephense when the actual view file is included
     */
    if (!class_exists('SMO_Social\\Admin\\Views\\MediaLibrary')) {
        class SMO_Social_Admin_Views_MediaLibrary {
            /**
             * Render the media library interface
             *
             * @return void
             */
            public function render() {
                // Stub implementation for Intelephense
                echo '<div class="wrap"><h1>Media Library</h1><p>Media library interface would be rendered here.</p></div>';
            }
        }
        
        // Create an alias for the expected class name
        if (!class_exists('SMO_Social\\Admin\\Views\\MediaLibrary')) {
            class_alias('SMO_Social_Admin_Views_MediaLibrary', 'SMO_Social\\Admin\\Views\\MediaLibrary');
        }
    }
}
