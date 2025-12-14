<?php
namespace SMO_Social\Security;

/**
 * Security Audit Logger
 * 
 * Comprehensive security event logging system for tracking and monitoring
 * security-related activities in the SMO-Social plugin.
 * 
 * @since 2.1.0
 */
class SecurityAuditLogger
{
    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_CRITICAL = 'critical';
    
    const EVENT_AUTHENTICATION = 'authentication';
    const EVENT_CSRF_VALIDATION = 'csrf_validation';
    const EVENT_INPUT_VALIDATION = 'input_validation';
    const EVENT_FILE_UPLOAD = 'file_upload';
    const EVENT_PERMISSION = 'permission';
    const EVENT_API_ACCESS = 'api_access';
    const EVENT_DATA_ACCESS = 'data_access';
    const EVENT_CONFIGURATION = 'configuration';
    
    private static $log_levels = [
        self::LOG_LEVEL_DEBUG => 0,
        self::LOG_LEVEL_INFO => 1,
        self::LOG_LEVEL_WARNING => 2,
        self::LOG_LEVEL_ERROR => 3,
        self::LOG_LEVEL_CRITICAL => 4
    ];
    
    private static $event_types = [
        self::EVENT_AUTHENTICATION,
        self::EVENT_CSRF_VALIDATION,
        self::EVENT_INPUT_VALIDATION,
        self::EVENT_FILE_UPLOAD,
        self::EVENT_PERMISSION,
        self::EVENT_API_ACCESS,
        self::EVENT_DATA_ACCESS,
        self::EVENT_CONFIGURATION
    ];
    
    /**
     * Log a security event
     *
     * @param string $event_type Type of security event
     * @param string $level Log level (debug, info, warning, error, critical)
     * @param string $message Event message
     * @param array $context Additional context data
     * @return bool Success status
     */
    public static function log(string $event_type, string $level, string $message, array $context = []): bool
    {
        // Validate event type
        if (!in_array($event_type, self::$event_types)) {
            $event_type = 'unknown';
        }
        
        // Validate log level
        if (!isset(self::$log_levels[$level])) {
            $level = self::LOG_LEVEL_INFO;
        }
        
        // Build log entry
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => self::getCurrentUserId(),
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'session_id' => self::getSessionId(),
            'plugin_version' => defined('SMO_SOCIAL_VERSION') ? SMO_SOCIAL_VERSION : 'unknown'
        ];
        
        // Filter by log level if configured
        $min_log_level = self::getMinLogLevel();
        if (self::$log_levels[$level] < self::$log_levels[$min_log_level]) {
            return true; // Skip logging but return success
        }
        
        // Store in database
        $stored = self::storeInDatabase($log_entry);
        
        // Also log to error log for critical events
        if ($level === self::LOG_LEVEL_CRITICAL) {
            error_log('SMO Social Security [CRITICAL]: ' . $message . ' - ' . json_encode($context));
        }
        
        return $stored;
    }
    
    /**
     * Log authentication events
     */
    public static function logAuthentication(string $action, string $result, array $context = []): bool
    {
        return self::log(
            self::EVENT_AUTHENTICATION,
            $result === 'success' ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_WARNING,
            "Authentication {$action}: {$result}",
            array_merge(['action' => $action, 'result' => $result], $context)
        );
    }
    
    /**
     * Log CSRF validation events
     */
    public static function logCSRFValidation(string $action, bool $success, array $context = []): bool
    {
        $level = $success ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_WARNING;
        $message = $success ? "CSRF validation passed for action: {$action}" : "CSRF validation failed for action: {$action}";
        
        return self::log(self::EVENT_CSRF_VALIDATION, $level, $message, array_merge([
            'action' => $action,
            'success' => $success
        ], $context));
    }
    
    /**
     * Log input validation events
     */
    public static function logInputValidation(string $form, bool $success, array $errors = [], array $context = []): bool
    {
        $level = $success ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_WARNING;
        $message = $success 
            ? "Input validation passed for form: {$form}" 
            : "Input validation failed for form: {$form}";
        
        return self::log(self::EVENT_INPUT_VALIDATION, $level, $message, array_merge([
            'form' => $form,
            'success' => $success,
            'errors' => $errors
        ], $context));
    }
    
    /**
     * Log file upload events
     */
    public static function logFileUpload(string $filename, bool $success, string $reason = '', array $context = []): bool
    {
        $level = $success ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_WARNING;
        $message = $success 
            ? "File upload successful: {$filename}" 
            : "File upload failed: {$filename} - {$reason}";
        
        return self::log(self::EVENT_FILE_UPLOAD, $level, $message, array_merge([
            'filename' => $filename,
            'success' => $success,
            'reason' => $reason
        ], $context));
    }
    
    /**
     * Log permission events
     */
    public static function logPermission(string $action, string $result, array $context = []): bool
    {
        $level = $result === 'granted' ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_WARNING;
        $message = "Permission {$action}: {$result}";
        
        return self::log(self::EVENT_PERMISSION, $level, $message, array_merge([
            'action' => $action,
            'result' => $result
        ], $context));
    }
    
    /**
     * Log API access events
     */
    public static function logAPIAccess(string $endpoint, string $method, bool $success, array $context = []): bool
    {
        $level = $success ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_WARNING;
        $message = "API access: {$method} {$endpoint} - " . ($success ? 'success' : 'failure');
        
        return self::log(self::EVENT_API_ACCESS, $level, $message, array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
            'success' => $success
        ], $context));
    }
    
    /**
     * Log data access events
     */
    public static function logDataAccess(string $resource, string $action, bool $success, array $context = []): bool
    {
        $level = $success ? self::LOG_LEVEL_INFO : self::LOG_LEVEL_WARNING;
        $message = "Data access: {$action} on {$resource} - " . ($success ? 'success' : 'failure');
        
        return self::log(self::EVENT_DATA_ACCESS, $level, $message, array_merge([
            'resource' => $resource,
            'action' => $action,
            'success' => $success
        ], $context));
    }
    
    /**
     * Log configuration changes
     */
    public static function logConfiguration(string $action, array $changes, array $context = []): bool
    {
        return self::log(
            self::EVENT_CONFIGURATION,
            self::LOG_LEVEL_INFO,
            "Configuration {$action}",
            array_merge(['action' => $action, 'changes' => $changes], $context)
        );
    }
    
    /**
     * Get security logs with filtering
     *
     * @param array $filters Filter options
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @return array Log entries
     */
    public static function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_security_logs';
        
        // Build query
        $where_conditions = ['1=1'];
        $query_params = [];
        
        if (!empty($filters['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $query_params[] = $filters['event_type'];
        }
        
        if (!empty($filters['level'])) {
            $where_conditions[] = 'level = %s';
            $query_params[] = $filters['level'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = %s';
            $query_params[] = $filters['user_id'];
        }
        
        if (!empty($filters['ip_address'])) {
            $where_conditions[] = 'ip_address = %s';
            $query_params[] = $filters['ip_address'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'timestamp >= %s';
            $query_params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'timestamp <= %s';
            $query_params[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $query_params[] = $limit;
        $query_params[] = $offset;
        
        if (!empty($query_params)) {
            $query = $wpdb->prepare($query, $query_params);
        }
        
        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }
    
    /**
     * Clean up old logs
     *
     * @param int $days Number of days to retain logs
     * @return int Number of deleted records
     */
    public static function cleanupOldLogs(int $days = 90): int
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_security_logs';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %s",
            $cutoff_date
        ));
        
        return (int) $deleted;
    }
    
    /**
     * Get security statistics
     *
     * @param int $days Number of days to analyze
     * @return array Statistics
     */
    public static function getSecurityStats(int $days = 30): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_security_logs';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total events by type
        $events_by_type = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count FROM {$table_name} 
             WHERE timestamp >= %s 
             GROUP BY event_type ORDER BY count DESC",
            $date_from
        ), ARRAY_A);
        
        // Events by level
        $events_by_level = $wpdb->get_results($wpdb->prepare(
            "SELECT level, COUNT(*) as count FROM {$table_name} 
             WHERE timestamp >= %s 
             GROUP BY level ORDER BY count DESC",
            $date_from
        ), ARRAY_A);
        
        // Top IP addresses
        $top_ips = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as count FROM {$table_name} 
             WHERE timestamp >= %s 
             GROUP BY ip_address ORDER BY count DESC LIMIT 10",
            $date_from
        ), ARRAY_A);
        
        // Failed events (warnings and errors)
        $failed_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE timestamp >= %s AND level IN ('warning', 'error', 'critical')",
            $date_from
        ));
        
        return [
            'total_events' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE timestamp >= %s",
                $date_from
            )),
            'failed_events' => (int) $failed_events,
            'events_by_type' => $events_by_type ?: [],
            'events_by_level' => $events_by_level ?: [],
            'top_ip_addresses' => $top_ips ?: [],
            'date_range' => [
                'from' => $date_from,
                'to' => current_time('mysql')
            ]
        ];
    }
    
    /**
     * Create database table for security logs
     */
    public static function createLogTable(): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_security_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            event_type varchar(50) NOT NULL,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id varchar(100),
            ip_address varchar(45),
            user_agent text,
            session_id varchar(100),
            plugin_version varchar(20),
            PRIMARY KEY (id),
            KEY timestamp_idx (timestamp),
            KEY event_type_idx (event_type),
            KEY level_idx (level),
            KEY ip_address_idx (ip_address),
            KEY user_id_idx (user_id)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        return true;
    }
    
    /**
     * Store log entry in database
     */
    private static function storeInDatabase(array $log_entry): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_security_logs';
        
        // Ensure table exists
        if (!self::logTableExists()) {
            self::createLogTable();
        }
        
        // Insert log entry
        $result = $wpdb->insert(
            $table_name,
            [
                'timestamp' => $log_entry['timestamp'],
                'event_type' => $log_entry['event_type'],
                'level' => $log_entry['level'],
                'message' => $log_entry['message'],
                'context' => json_encode($log_entry['context']),
                'user_id' => $log_entry['user_id'],
                'ip_address' => $log_entry['ip_address'],
                'user_agent' => $log_entry['user_agent'],
                'session_id' => $log_entry['session_id'],
                'plugin_version' => $log_entry['plugin_version']
            ],
            [
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s'
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Check if log table exists
     */
    private static function logTableExists(): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_security_logs';
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
    
    /**
     * Get minimum log level from settings
     */
    private static function getMinLogLevel(): string
    {
        $setting = get_option('smo_security_log_level', self::LOG_LEVEL_INFO);
        return self::$log_levels[$setting] !== null ? $setting : self::LOG_LEVEL_INFO;
    }
    
    /**
     * Get current user ID
     */
    private static function getCurrentUserId(): string
    {
        if (function_exists('get_current_user_id')) {
            $user_id = get_current_user_id();
            return $user_id ? 'user_' . $user_id : 'guest';
        }
        
        return session_id() ?? 'unknown';
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP(): string
    {
        $ip_keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP'
        ];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Get user agent
     */
    private static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * Get session ID
     */
    private static function getSessionId(): string
    {
        return session_id() ?? 'none';
    }
}