<?php
/**
 * Centralized database management helper
 * 
 * Eliminates 400+ instances of duplicated global $wpdb declarations
 * Provides consistent database operations, connection management, and query building
 * 
 * @package SMO_Social
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class DatabaseManager {
    
    /**
     * WordPress database object
     * @var \wpdb
     */
    private static $wpdb_instance = null;
    
    /**
     * Table prefix cache
     * @var array
     */
    private static $table_prefixes = [];
    
    /**
     * Query result cache
     * @var array
     */
    private static $query_cache = [];
    
    /**
     * Initialize the database manager
     * 
     * @param \wpdb $wpdb WordPress database object
     */
    public static function init($wpdb = null) {
        self::$wpdb_instance = $wpdb ?: self::getWPDB();
    }
    
    /**
     * Get the WordPress database object
     * 
     * @return \wpdb
     */
    public static function getWPDB() {
        if (self::$wpdb_instance === null) {
            global $wpdb;
            self::$wpdb_instance = $wpdb;
        }
        return self::$wpdb_instance;
    }
    
    /**
     * Get table name with proper prefix
     * 
     * @param string $table_name Table name (without prefix)
     * @param bool $cache Whether to cache the result
     * @return string
     */
    public static function getTableName($table_name, $cache = true) {
        if ($cache && isset(self::$table_prefixes[$table_name])) {
            return self::$table_prefixes[$table_name];
        }
        
        $wpdb = self::getWPDB();
        $full_table_name = $wpdb->prefix . $table_name;
        
        if ($cache) {
            self::$table_prefixes[$table_name] = $full_table_name;
        }
        
        return $full_table_name;
    }
    
    /**
     * Execute a prepared query
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Query parameters
     * @param string $cache_key Optional cache key
     * @param int $cache_duration Cache duration in seconds
     * @return mixed Query result
     */
    public static function query($query, $params = [], $cache_key = null, $cache_duration = 300) {
        // Check cache first
        if ($cache_key && self::getFromCache($cache_key, $cache_duration)) {
            return self::getFromCache($cache_key);
        }
        
        $wpdb = self::getWPDB();
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $result = $wpdb->query($query);
        
        // Cache successful queries
        if ($cache_key && $result !== false) {
            self::setCache($cache_key, $result);
        }
        
        return $result;
    }
    
    /**
     * Get a single row from database
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param string $output Output format (OBJECT, ARRAY_A, ARRAY_N)
     * @param string $cache_key Optional cache key
     * @param int $cache_duration Cache duration in seconds
     * @return mixed
     */
    public static function getRow($query, $params = [], $output = 'OBJECT', $cache_key = null, $cache_duration = 300) {
        // Check cache first
        if ($cache_key && self::getFromCache($cache_key, $cache_duration)) {
            return self::getFromCache($cache_key);
        }
        
        $wpdb = self::getWPDB();
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $result = $wpdb->get_row($query, $output);
        
        // Cache successful queries
        if ($cache_key && $result !== null) {
            self::setCache($cache_key, $result);
        }
        
        return $result;
    }
    
    /**
     * Get multiple rows from database
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param string $output Output format (OBJECT, ARRAY_A, ARRAY_N)
     * @param string $cache_key Optional cache key
     * @param int $cache_duration Cache duration in seconds
     * @return array
     */
    public static function getResults($query, $params = [], $output = 'OBJECT', $cache_key = null, $cache_duration = 300) {
        // Check cache first
        if ($cache_key && self::getFromCache($cache_key, $cache_duration)) {
            return self::getFromCache($cache_key);
        }
        
        $wpdb = self::getWPDB();
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $result = $wpdb->get_results($query, $output);
        
        // Cache successful queries
        if ($cache_key && !empty($result)) {
            self::setCache($cache_key, $result);
        }
        
        return $result ?: [];
    }
    
    /**
     * Get a single variable from database
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param int $xcol_offset Column offset
     * @param int $yrow_offset Row offset
     * @param string $cache_key Optional cache key
     * @param int $cache_duration Cache duration in seconds
     * @return mixed
     */
    public static function getVar($query, $params = [], $xcol_offset = 0, $yrow_offset = 0, $cache_key = null, $cache_duration = 300) {
        // Check cache first
        if ($cache_key && self::getFromCache($cache_key, $cache_duration)) {
            return self::getFromCache($cache_key);
        }
        
        $wpdb = self::getWPDB();
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $result = $wpdb->get_var($query, $xcol_offset, $yrow_offset);
        
        // Cache successful queries
        if ($cache_key && $result !== null) {
            self::setCache($cache_key, $result);
        }
        
        return $result;
    }
    
    /**
     * Insert data into table
     * 
     * @param string $table Table name (without prefix)
     * @param array $data Data to insert (column => value)
     * @param array $format Format for each column (optional)
     * @return int|false Insert ID or false on failure
     */
    public static function insert($table, $data, $format = []) {
        $wpdb = self::getWPDB();
        $table_name = self::getTableName($table);
        
        // Auto-detect formats if not provided
        if (empty($format)) {
            $format = [];
            foreach ($data as $value) {
                $format[] = self::detectDataType($value);
            }
        }
        
        $result = $wpdb->insert($table_name, $data, $format);
        
        // Clear related cache
        if ($result) {
            self::clearTableCache($table);
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update data in table
     * 
     * @param string $table Table name (without prefix)
     * @param array $data Data to update (column => value)
     * @param array $where WHERE conditions (column => value)
     * @param array $format Format for data values (optional)
     * @param array $where_format Format for where values (optional)
     * @return int|false Number of rows updated or false on failure
     */
    public static function update($table, $data, $where, $format = [], $where_format = []) {
        $wpdb = self::getWPDB();
        $table_name = self::getTableName($table);
        
        // Auto-detect formats if not provided
        if (empty($format)) {
            $format = [];
            foreach ($data as $value) {
                $format[] = self::detectDataType($value);
            }
        }
        
        if (empty($where_format)) {
            $where_format = [];
            foreach ($where as $value) {
                $where_format[] = self::detectDataType($value);
            }
        }
        
        $result = $wpdb->update($table_name, $data, $where, $format, $where_format);
        
        // Clear related cache
        if ($result !== false) {
            self::clearTableCache($table);
        }
        
        return $result;
    }
    
    /**
     * Delete data from table
     * 
     * @param string $table Table name (without prefix)
     * @param array $where WHERE conditions (column => value)
     * @param array $where_format Format for where values (optional)
     * @return int|false Number of rows deleted or false on failure
     */
    public static function delete($table, $where, $where_format = []) {
        $wpdb = self::getWPDB();
        $table_name = self::getTableName($table);
        
        if (empty($where_format)) {
            $where_format = [];
            foreach ($where as $value) {
                $where_format[] = self::detectDataType($value);
            }
        }
        
        $result = $wpdb->delete($table_name, $where, $where_format);
        
        // Clear related cache
        if ($result !== false) {
            self::clearTableCache($table);
        }
        
        return $result;
    }
    
    /**
     * Build a SELECT query with common clauses
     * 
     * @param string $table Table name (without prefix)
     * @param array $fields Fields to select
     * @param array $where WHERE conditions
     * @param array $options Additional options (order_by, limit, offset, etc.)
     * @return string
     */
    public static function buildSelectQuery($table, $fields = ['*'], $where = [], $options = []) {
        $table_name = self::getTableName($table);
        
        $query = "SELECT " . implode(', ', $fields) . " FROM {$table_name}";
        
        // Add WHERE clause
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $field => $value) {
                if (is_array($value)) {
                    $placeholders = implode(', ', array_fill(0, count($value), '%s'));
                    $conditions[] = "{$field} IN ({$placeholders})";
                } else {
                    $conditions[] = "{$field} = %s";
                }
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Add ORDER BY
        if (!empty($options['order_by'])) {
            $order = $options['order'] ?? 'ASC';
            $query .= " ORDER BY {$options['order_by']} {$order}";
        }
        
        // Add LIMIT
        if (!empty($options['limit'])) {
            $query .= " LIMIT {$options['limit']}";
        }
        
        // Add OFFSET
        if (!empty($options['offset'])) {
            $query .= " OFFSET {$options['offset']}";
        }
        
        return $query;
    }
    
    /**
     * Check if table exists
     * 
     * @param string $table Table name (with or without prefix)
     * @return bool
     */
    public static function tableExists($table) {
        $wpdb = self::getWPDB();
        
        // Add prefix if not present
        if (strpos($table, $wpdb->prefix) !== 0) {
            $table = self::getTableName($table, false);
        }
        
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return $result === $table;
    }
    
    /**
     * Get table statistics
     * 
     * @param string $table Table name (without prefix)
     * @return array
     */
    public static function getTableStats($table) {
        $table_name = self::getTableName($table);
        $wpdb = self::getWPDB();
        
        return [
            'rows' => self::getVar("SELECT COUNT(*) FROM {$table_name}"),
            'size' => self::getVar($wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb' 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ))
        ];
    }
    
    /**
     * Clear cache for a specific table
     * 
     * @param string $table Table name
     */
    public static function clearTableCache($table) {
        $prefix = 'table_' . $table . '_';
        foreach (self::$query_cache as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                unset(self::$query_cache[$key]);
            }
        }
    }
    
    /**
     * Clear all cache
     */
    public static function clearAllCache() {
        self::$query_cache = [];
    }
    
    /**
     * Get data type for prepared statement
     * 
     * @param mixed $value Value to analyze
     * @return string Format string (%d, %f, %s)
     */
    private static function detectDataType($value) {
        if (is_int($value)) {
            return '%d';
        } elseif (is_float($value)) {
            return '%f';
        } else {
            return '%s';
        }
    }
    
    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @param int $max_age Maximum age in seconds
     * @return mixed|null
     */
    private static function getFromCache($key, $max_age = 300) {
        if (!isset(self::$query_cache[$key])) {
            return null;
        }
        
        $cached = self::$query_cache[$key];
        if (time() - $cached['time'] > $max_age) {
            unset(self::$query_cache[$key]);
            return null;
        }
        
        return $cached['data'];
    }
    
    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     */
    private static function setCache($key, $data) {
        self::$query_cache[$key] = [
            'data' => $data,
            'time' => time()
        ];
    }
    
    /**
     * Execute transaction
     * 
     * @param callable $callback Callback function to execute in transaction
     * @return mixed Result of callback or false on failure
     */
    public static function transaction($callback) {
        $wpdb = self::getWPDB();
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $result = $callback(self::getWPDB());
            $wpdb->query('COMMIT');
            return $result;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    /**
     * Get charset collate for table creation
     * 
     * @return string
     */
    public static function getCharsetCollate() {
        $wpdb = self::getWPDB();
        return $wpdb->get_charset_collate();
    }
    
    /**
     * Escape string for SQL
     * 
     * @param string $string String to escape
     * @return string
     */
    public static function escape($string) {
        $wpdb = self::getWPDB();
        return $wpdb->_real_escape($string);
    }
    
    /**
     * Escape like string for SQL LIKE clauses
     * 
     * @param string $string String to escape
     * @return string
     */
    public static function escapeLike($string) {
        $wpdb = self::getWPDB();
        return $wpdb->esc_like($string);
    }
}
