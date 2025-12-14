<?php
/**
 * Database Security Helper
 * 
 * Provides secure database operations with prepared statements and sanitization
 */

namespace SMO_Social\Core;

class DatabaseSecurity
{
    /**
     * Secure database operation with prepared statements
     */
    public static function secureUpdate(string $table, array $data, array $where, array $format = [], array $where_format = []): bool|int
    {
        global $wpdb;
        
        // Validate table name to prevent SQL injection
        if (!self::isValidTableName($table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }
        
        // Sanitize data and format arrays
        $sanitized_data = self::sanitizeData($data);
        $sanitized_format = self::validateFormat($format, $sanitized_data);
        
        // Sanitize where clause
        $sanitized_where = self::sanitizeData($where);
        $sanitized_where_format = self::validateFormat($where_format, $sanitized_where);
        
        try {
            $result = $wpdb->update(
                $wpdb->prefix . $table,
                $sanitized_data,
                $sanitized_where,
                $sanitized_format,
                $sanitized_where_format
            );
            
            if ($result === false) {
                error_log('SMO Social DB Update Error: ' . $wpdb->last_error);
                return false;
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('SMO Social DB Update Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Secure database query with prepared statements
     */
    public static function secureQuery(string $query, array $params = []): mixed
    {
        global $wpdb;
        
        // Basic query validation
        if (!self::isValidQuery($query)) {
            throw new \InvalidArgumentException('Potentially unsafe SQL query');
        }
        
        try {
            if (!empty($params)) {
                return $wpdb->get_results($wpdb->prepare($query, $params));
            }
            
            return $wpdb->get_results($query);
        } catch (\Exception $e) {
            error_log('SMO Social DB Query Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Secure single row retrieval
     */
    public static function secureGetRow(string $query, array $params = [], string $output = 'OBJECT'): mixed
    {
        global $wpdb;
        
        if (!self::isValidQuery($query)) {
            throw new \InvalidArgumentException('Potentially unsafe SQL query');
        }
        
        try {
            if (!empty($params)) {
                return $wpdb->get_row($wpdb->prepare($query, $params), $output);
            }
            
            return $wpdb->get_row($query, $output);
        } catch (\Exception $e) {
            error_log('SMO Social DB GetRow Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Secure insertion with automatic sanitization
     */
    public static function secureInsert(string $table, array $data, array $format = []): bool|int
    {
        global $wpdb;
        
        if (!self::isValidTableName($table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }
        
        $sanitized_data = self::sanitizeData($data);
        $sanitized_format = self::validateFormat($format, $sanitized_data);
        
        try {
            $result = $wpdb->insert(
                $wpdb->prefix . $table,
                $sanitized_data,
                $sanitized_format ?: null
            );
            
            if ($result === false) {
                error_log('SMO Social DB Insert Error: ' . $wpdb->last_error);
                return false;
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('SMO Social DB Insert Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced comment management with security
     */
    public static function updateCommentSentimentSecurely(int $comment_id, string $sentiment): bool
    {
        // Validate sentiment values
        $allowed_sentiments = ['positive', 'neutral', 'negative'];
        if (!in_array($sentiment, $allowed_sentiments, true)) {
            throw new \InvalidArgumentException('Invalid sentiment value');
        }
        
        // Use prepared statement with proper sanitization
        return self::secureUpdate(
            'smo_social_comments',
            [
                'sentiment' => $sentiment,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $comment_id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }
    
    /**
     * Get comments with enhanced pagination and security
     */
    public static function getCommentsSecurely(array $filters = [], int $page = 1, int $per_page = 20): array
    {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $where_conditions = ['1=1']; // Base condition
        $where_values = [];
        
        // Build safe WHERE conditions
        if (!empty($filters['platform'])) {
            $platform = sanitize_text_field($filters['platform']);
            $where_conditions[] = 'platform = %s';
            $where_values[] = $platform;
        }
        
        if (!empty($filters['sentiment'])) {
            $sentiment = sanitize_text_field($filters['sentiment']);
            if (in_array($sentiment, ['positive', 'neutral', 'negative'], true)) {
                $where_conditions[] = 'sentiment = %s';
                $where_values[] = $sentiment;
            }
        }
        
        if (isset($filters['is_replied']) && $filters['is_replied'] !== '') {
            $is_replied = (int) $filters['is_replied'];
            $where_conditions[] = 'is_replied = %d';
            $where_values[] = $is_replied;
        }
        
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $where_conditions[] = '(content LIKE %s OR author_name LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
        }
        
        // Build query with LIMIT and COUNT
        $base_query = "SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->prefix}smo_social_comments 
                      WHERE " . implode(' AND ', $where_conditions) . " 
                      ORDER BY created_at DESC 
                      LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        try {
            $comments = $wpdb->get_results($wpdb->prepare($base_query, $where_values));
            
            // Get total count
            $total_count = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
            
            return [
                'comments' => $comments ?: [],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_count > 0 ? (int) ceil($total_count / $per_page) : 0,
                    'total_items' => $total_count
                ]
            ];
        } catch (\Exception $e) {
            error_log('SMO Social Comments Query Error: ' . $e->getMessage());
            return [
                'comments' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_items' => 0
                ]
            ];
        }
    }
    
    /**
     * Validate table name to prevent SQL injection
     */
    private static function isValidTableName(string $table): bool
    {
        // Only allow alphanumeric, underscore, and basic table name patterns
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) === 1;
    }
    
    /**
     * Basic SQL query validation
     */
    private static function isValidQuery(string $query): bool
    {
        // Deny dangerous SQL keywords
        $dangerous_keywords = [
            'DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT', 'REVOKE',
            'UNION', 'INFORMATION_SCHEMA', 'LOAD_FILE', 'INTO OUTFILE', 'INTO DUMPFILE'
        ];
        
        $upper_query = strtoupper($query);
        foreach ($dangerous_keywords as $keyword) {
            if (strpos($upper_query, $keyword) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize data array values
     */
    private static function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            // Validate key name
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                $sanitized[$key] = is_string($value) ? sanitize_text_field($value) : $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate and sanitize format array
     */
    private static function validateFormat(array $format, array $data): array
    {
        $allowed_formats = ['%s', '%d', '%f'];
        $sanitized_format = [];
        
        foreach ($format as $key => $fmt) {
            if (in_array($fmt, $allowed_formats, true) && array_key_exists($key, $data)) {
                $sanitized_format[$key] = $fmt;
            }
        }
        
        return $sanitized_format;
    }
}