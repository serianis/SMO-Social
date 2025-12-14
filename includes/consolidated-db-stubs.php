<?php
/**
 * Consolidated WordPress Database Stubs for Intelephense
 * 
 * This file provides a single, comprehensive wpdb class definition
 * to resolve all database-related undefined method errors.
 */

// Avoid redeclaration
if (defined('SMO_SOCIAL_DB_STUBS_LOADED')) {
    return;
}
define('SMO_SOCIAL_DB_STUBS_LOADED', true);

/**
 * WordPress wpdb class stub for Intelephense
 * 
 * Provides all method signatures that Intelephense can recognize
 * without conflicting with actual WordPress functionality.
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