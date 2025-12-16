<?php
/**
 * Comprehensive WordPress Stubs for Intelephense
 *
 * This file consolidates all WordPress stub definitions from multiple files
 * to eliminate DRY violations and provide a single, comprehensive set of stubs
 * for development and testing environments.
 *
 * Includes:
 * - Database classes (wpdb)
 * - Error handling (WP_Error)
 * - Testing framework (WP_UnitTestCase, etc.)
 * - WordPress functions and utilities
 * - External libraries (Redis, Socket, etc.)
 */

// Avoid redeclaration
if (defined('SMO_SOCIAL_COMPREHENSIVE_STUBS_LOADED')) {
    return;
}
define('SMO_SOCIAL_COMPREHENSIVE_STUBS_LOADED', true);

// Prevent loading in WordPress environment to avoid conflicts
if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-config.php')) {
    return;
}

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
             * @var int Last insert ID
             */
            public $insert_id;

            /**
             * @var string|null Last query
             */
            public $last_query;

            /**
             * @var array Last results
             */
            public $last_result;

            /**
             * @var int Num rows
             */
            public $num_rows;

            /**
             * @var string Posts table name
             */
            public $posts;

            /**
             * @var string Users table name
             */
            public $users;

            /**
             * @var string Comments table name
             */
            public $comments;

            /**
             * @var string Options table name
             */
            public $options;

            /**
             * Prepares a SQL query for safe execution
             *
             * @param string $query SQL query with placeholders (%s, %d, %f)
             * @param mixed $args,... Variables to replace placeholders
             * @return string Prepared SQL query
             */
            public function prepare($query, ...$args) {
                if (empty($args)) {
                    return $query;
                }
                return sprintf($query, ...$args);
            }

            /**
             * Get a single row from the database
             *
             * @param string $query SQL query
             * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT constants
             * @return object|array|null Database query result
             */
            public function get_row($query, $output = 'OBJECT') {
                $this->last_query = $query;
                $this->last_result = array();
                $this->num_rows = 0;
                return null;
            }

            /**
             * Get one variable from the database
             *
             * @param string $query SQL query
             * @param int $xcol_offset Column offset
             * @param int $yrow_offset Row offset
             * @return string|null Database query result
             */
            public function get_var($query, $xcol_offset = 0, $yrow_offset = 0) {
                $this->last_query = $query;
                $this->last_result = array();
                $this->num_rows = 0;
                return null;
            }

            /**
             * Get an array of database results
             *
             * @param string $query SQL query
             * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT constants
             * @return array Database query results
             */
            public function get_results($query, $output = 'OBJECT') {
                $this->last_query = $query;
                $this->last_result = array();
                $this->num_rows = 0;
                return array();
            }

            /**
             * Insert a row into a table
             *
             * @param string $table Table name
             * @param array $data Data to insert (column => value pairs)
             * @param array|string $format Optional. Format string or array of formats
             * @return int|false Number of rows inserted or false on error
             */
            public function insert($table, $data, $format = null) {
                $this->insert_id = 0;
                $this->last_query = "INSERT INTO $table";
                return 1;
            }

            /**
             * Replace a row in the table
             *
             * @param string $table Table name
             * @param array $data Data to insert (column => value pairs)
             * @param array|string $format Optional. Format string or array of formats
             * @return int|false Number of rows inserted or false on error
             */
            public function replace($table, $data, $format = null) {
                $this->last_query = "REPLACE INTO $table";
                return 1;
            }

            /**
             * Update a row in the table
             *
             * @param string $table Table name
             * @param array $data Data to update (column => value pairs)
             * @param array $where WHERE clause (column => value pairs)
             * @param array|string $format Optional. Format string or array of formats
             * @param array|string $where_format Optional. WHERE format string or array
             * @return int|false Number of rows updated or false on error
             */
            public function update($table, $data, $where, $format = null, $where_format = null) {
                $this->last_query = "UPDATE $table";
                return 1;
            }

            /**
             * Delete a row from the table
             *
             * @param string $table Table name
             * @param array $where WHERE clause (column => value pairs)
             * @param array|string $format Optional. Format string or array of formats
             * @return int|false Number of rows deleted or false on error
             */
            public function delete($table, $where, $format = null) {
                $this->last_query = "DELETE FROM $table";
                return 1;
            }

            /**
             * Perform a MySQL database query
             *
             * @param string $query SQL query
             * @return int|false Number of rows affected or false on error
             */
            public function query($query) {
                $this->last_query = $query;
                $this->last_result = array();
                $this->num_rows = 0;
                return 0;
            }

            /**
             * Escapes characters for use in MySQL LIKE clauses
             *
             * @param string $text The text to be escaped
             * @return string The escaped text
             */
            public function esc_like($text) {
                return addslashes($text);
            }

            /**
             * Get the database charset collate string
             *
             * @return string Database charset collate
             */
            public function get_charset_collate() {
                return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            }

            /**
             * Get table schema
             *
             * @param string $table Table name
             * @return string Table schema
             */
            public function get_charset($table = '') {
                return 'utf8mb4';
            }

            /**
             * Escape data for use in a MySQL query
             *
             * @param mixed $data Data to escape
             * @return string Escaped data
             */
            public function _real_escape($data) {
                return addslashes($data);
            }

            /**
             * Escape data for use in a MySQL query
             *
             * @param mixed $data Data to escape
             * @return string Escaped data
             */
            public function escape($data) {
                if (is_array($data)) {
                    return array_map(array($this, '_real_escape'), $data);
                }
                return $this->_real_escape($data);
            }

            /**
             * Escapes string for use in SQL query
             *
             * @param string $string String to escape
             * @return string Escaped string
             */
            public function escape_by_ref(&$string) {
                $string = $this->escape($string);
            }

            /**
             * Format a string for use in SQL query
             *
             * @param string $format Format string
             * @param mixed $args,... Arguments
             * @return string Formatted string
             */
            public function format($format, ...$args) {
                return vsprintf($format, $args);
            }

            /**
             * Check if table exists
             *
             * @param string $table Table name
             * @return bool True if table exists
             */
            public function table_exists($table) {
                return true;
            }

            /**
             * Execute a query
             *
             * @param string $query SQL query
             * @return int|false Query result
             */
            public function query_ex($query) {
                return $this->query($query);
            }

            public function __get($name) {
                return $this->$name;
            }

            public function __call($name, $arguments) {
                return null;
            }
        }
    }

    /**
     * Global wpdb variable declaration for Intelephense
     * @var \wpdb $wpdb WordPress database abstraction object
     */
    if (!isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['wpdb']->prefix = 'wp_';
        $GLOBALS['wpdb']->insert_id = 0;
    }

    /**
     * Create alias for global namespace
     */
    if (!isset($wpdb) || !($wpdb instanceof \wpdb)) {
        $wpdb = $GLOBALS['wpdb'];
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
             * Constructor
             *
             * @param string|array $code Error code or array of errors
             * @param string $message Error message
             * @param mixed $data Optional error data
             */
            public function __construct($code = '', $message = '', $data = '') {
                if (is_array($code)) {
                    foreach ($code as $error_code => $error_message) {
                        $this->add($error_code, $error_message);
                    }
                } else if ($code) {
                    $this->add($code, $message, $data);
                }
            }

            /**
             * Add an error or errors
             *
             * @param string $code Error code
             * @param string $message Error message
             * @param mixed $data Optional error data
             * @return void
             */
            public function add($code, $message = '', $data = '') {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }

            /**
             * Remove an error
             *
             * @param string $code Error code
             * @return void
             */
            public function remove($code) {
                unset($this->errors[$code]);
                unset($this->error_data[$code]);
            }

            /**
             * Get all error codes
             *
             * @return array Error codes
             */
            public function get_error_codes() {
                return array_keys($this->errors);
            }

            /**
             * Get all error messages for a code
             *
             * @param string $code Error code
             * @return array Error messages
             */
            public function get_error_messages($code = '') {
                if (empty($code)) {
                    $messages = array();
                    foreach ($this->errors as $code => $messages_for_code) {
                        $messages = array_merge($messages, $messages_for_code);
                    }
                    return $messages;
                }
                return isset($this->errors[$code]) ? $this->errors[$code] : array();
            }

            /**
             * Get a single error message
             *
             * @param string $code Error code
             * @param string $message_position Optional message position
             * @return string Error message
             */
            public function get_error_message($code = '', $message_position = 0) {
                $messages = $this->get_error_messages($code);
                return isset($messages[$message_position]) ? $messages[$message_position] : '';
            }

            /**
             * Get error data for a code
             *
             * @param string $code Error code
             * @return mixed Error data
             */
            public function get_error_data($code = '') {
                return isset($this->error_data[$code]) ? $this->error_data[$code] : '';
            }

            /**
             * Check if there are errors
             *
             * @return bool True if there are errors
             */
            public function has_errors() {
                return !empty($this->errors);
            }

            /**
             * Get error code
             *
             * @return string Error code
             */
            public function get_error_code() {
                return 'error';
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
    namespace SMO_Social\Admin\Views {
        if (!class_exists('SMO_Social\Admin\Views\MediaLibrary')) {
            class MediaLibrary {
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
        }
    }
}

// Include comprehensive WordPress functions
require_once __DIR__ . '/wordpress-functions.php';

// Include testing framework stubs
require_once __DIR__ . '/wordpress-test-stubs.php';