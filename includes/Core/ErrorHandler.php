<?php
/**
 * Centralized error handling and logging system
 * 
 * Consolidates hundreds of duplicated try-catch blocks and error logging patterns
 * Provides consistent error handling, logging, and recovery mechanisms
 * 
 * @package SMO_Social
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ErrorHandler {
    
    /**
     * Error levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * Error categories
     */
    const CATEGORY_DATABASE = 'database';
    const CATEGORY_NETWORK = 'network';
    const CATEGORY_VALIDATION = 'validation';
    const CATEGORY_PERMISSION = 'permission';
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_API = 'api';
    const CATEGORY_CACHE = 'cache';
    
    /**
     * Log levels configuration
     * @var array
     */
    private static $log_levels = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 4
    ];
    
    /**
     * Minimum log level to actually log
     * @var int
     */
    private static $min_log_level = 1; // INFO by default
    
    /**
     * Error statistics
     * @var array
     */
    private static $error_stats = [];
    
    /**
     * Execute code with automatic error handling
     * 
     * @param callable $callback Code to execute
     * @param array $options Error handling options
     * @return mixed Result of callback or false on error
     */
    public static function execute($callback, $options = []) {
        $defaults = [
            'catch_exceptions' => true,
            'log_errors' => true,
            'retry_count' => 0,
            'retry_delay' => 0,
            'category' => self::CATEGORY_SYSTEM,
            'context' => [],
            'return_false_on_error' => true
        ];
        
        $options = array_merge($defaults, $options);
        $attempt = 0;
        $max_attempts = $options['retry_count'] + 1;
        
        while ($attempt < $max_attempts) {
            try {
                $result = $callback();
                
                if ($attempt > 0) {
                    self::log(self::LEVEL_INFO, 'Operation succeeded after retries', [
                        'category' => $options['category'],
                        'attempts' => $attempt + 1,
                        'context' => $options['context']
                    ]);
                }
                
                return $result;
                
            } catch (\Exception $e) {
                $attempt++;
                $is_last_attempt = ($attempt >= $max_attempts);
                
                if ($options['catch_exceptions']) {
                    if ($options['log_errors']) {
                        self::logException($e, $options['category'], $options['context'], $attempt, $max_attempts);
                    }
                    
                    if ($is_last_attempt) {
                        return $options['return_false_on_error'] ? false : null;
                    }
                    
                    // Wait before retry
                    if ($options['retry_delay'] > 0 && !$is_last_attempt) {
                        sleep($options['retry_delay']);
                    }
                } else {
                    // Re-throw if not catching
                    throw $e;
                }
            }
        }
        
        return $options['return_false_on_error'] ? false : null;
    }
    
    /**
     * Log error with context
     * 
     * @param string $level Log level
     * @param string $message Error message
     * @param array $context Additional context
     * @param string $category Error category
     */
    public static function log($level, $message, $context = [], $category = self::CATEGORY_SYSTEM) {
        // Check if we should log this level
        if (!self::shouldLog($level)) {
            return;
        }
        
        // Build log entry
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'category' => $category,
            'context' => $context,
            'request_id' => self::getRequestId(),
            'user_id' => get_current_user_id(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Update statistics
        self::updateErrorStats($level, $category);
        
        // Log to appropriate handler
        if (function_exists('error_log')) {
            $formatted_message = self::formatLogMessage($log_entry);
            error_log($formatted_message);
        }
        
        // WordPress specific logging
        if (function_exists('wp_debug_log') && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            self::logToWordPress($log_entry);
        }
        
        // Custom logging hooks
        do_action('smo_social_error_log', $log_entry);
    }
    
    /**
     * Log exception with full details
     * 
     * @param \Exception $exception Exception to log
     * @param string $category Error category
     * @param array $context Additional context
     * @param int $attempt Current attempt number
     * @param int $max_attempts Maximum attempts
     */
    public static function logException($exception, $category = self::CATEGORY_SYSTEM, $context = [], $attempt = 1, $max_attempts = 1) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => self::LEVEL_ERROR,
            'message' => $exception->getMessage(),
            'category' => $category,
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ],
            'context' => $context,
            'attempt' => $attempt,
            'max_attempts' => $max_attempts,
            'request_id' => self::getRequestId(),
            'user_id' => get_current_user_id(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Include previous exception if exists
        if ($exception->getPrevious()) {
            $log_entry['exception']['previous'] = [
                'class' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
                'code' => $exception->getPrevious()->getCode()
            ];
        }
        
        self::log(self::LEVEL_ERROR, 'Exception occurred', $log_entry, $category);
    }
    
    /**
     * Handle database errors
     * 
     * @param \Exception $exception Database exception
     * @param string $query SQL query that failed
     * @param array $params Query parameters
     * @param string $operation Database operation
     * @return bool Always returns false for error handling
     */
    public static function handleDatabaseError($exception, $query = '', $params = [], $operation = 'query') {
        $context = [
            'query' => $query,
            'params' => $params,
            'operation' => $operation,
            'error_code' => $exception->getCode()
        ];
        
        self::logException($exception, self::CATEGORY_DATABASE, $context);
        
        // Database-specific error handling
        if (method_exists($exception, 'getSqlState')) {
            $context['sql_state'] = $exception->getSqlState();
        }
        
        return false;
    }
    
    /**
     * Handle API errors
     * 
     * @param string $api_name Name of the API
     * @param string $endpoint API endpoint
     * @param int $status_code HTTP status code
     * @param string $response_body Response body
     * @param array $request_headers Request headers
     * @return bool Always returns false for error handling
     */
    public static function handleApiError($api_name, $endpoint, $status_code, $response_body = '', $request_headers = []) {
        $context = [
            'api_name' => $api_name,
            'endpoint' => $endpoint,
            'status_code' => $status_code,
            'response_body' => $response_body,
            'request_headers' => $request_headers
        ];
        
        $level = $status_code >= 500 ? self::LEVEL_CRITICAL : self::LEVEL_ERROR;
        $message = "API Error: {$api_name} - {$endpoint} returned {$status_code}";
        
        self::log($level, $message, $context, self::CATEGORY_API);
        
        return false;
    }
    
    /**
     * Handle validation errors
     * 
     * @param array $errors Validation errors
     * @param string $operation Operation that failed
     * @param array $data Data that failed validation
     * @return bool Always returns false for error handling
     */
    public static function handleValidationError($errors, $operation = '', $data = []) {
        $context = [
            'errors' => $errors,
            'operation' => $operation,
            'data' => $data,
            'error_count' => count($errors)
        ];
        
        $message = 'Validation failed' . ($operation ? " for operation: {$operation}" : '');
        self::log(self::LEVEL_WARNING, $message, $context, self::CATEGORY_VALIDATION);
        
        return false;
    }
    
    /**
     * Handle permission errors
     * 
     * @param string $operation Operation attempted
     * @param string $required_capability Required capability
     * @param int $user_id User ID
     * @return bool Always returns false for error handling
     */
    public static function handlePermissionError($operation, $required_capability = '', $user_id = null) {
        $context = [
            'operation' => $operation,
            'required_capability' => $required_capability,
            'user_id' => $user_id ?: get_current_user_id(),
            'current_capabilities' => $user_id ? get_user_capabilities($user_id) : []
        ];
        
        $message = 'Permission denied' . ($operation ? " for operation: {$operation}" : '');
        self::log(self::LEVEL_WARNING, $message, $context, self::CATEGORY_PERMISSION);
        
        return false;
    }
    
    /**
     * Set minimum log level
     * 
     * @param string $level Minimum log level
     */
    public static function setMinLogLevel($level) {
        if (isset(self::$log_levels[$level])) {
            self::$min_log_level = self::$log_levels[$level];
        }
    }
    
    /**
     * Get error statistics
     * 
     * @param string $category Optional category filter
     * @param string $level Optional level filter
     * @return array
     */
    public static function getErrorStats($category = null, $level = null) {
        if ($category && isset(self::$error_stats[$category])) {
            $stats = self::$error_stats[$category];
        } else {
            $stats = self::$error_stats;
        }
        
        if ($level && isset($stats[$level])) {
            return $stats[$level];
        }
        
        return $stats;
    }
    
    /**
     * Clear error statistics
     * 
     * @param string $category Optional category to clear
     */
    public static function clearErrorStats($category = null) {
        if ($category) {
            unset(self::$error_stats[$category]);
        } else {
            self::$error_stats = [];
        }
    }
    
    /**
     * Check if we should log at this level
     * 
     * @param string $level Log level to check
     * @return bool
     */
    private static function shouldLog($level) {
        return isset(self::$log_levels[$level]) && self::$log_levels[$level] >= self::$min_log_level;
    }
    
    /**
     * Update error statistics
     * 
     * @param string $level Log level
     * @param string $category Error category
     */
    private static function updateErrorStats($level, $category) {
        if (!isset(self::$error_stats[$category])) {
            self::$error_stats[$category] = [];
        }
        
        if (!isset(self::$error_stats[$category][$level])) {
            self::$error_stats[$category][$level] = 0;
        }
        
        self::$error_stats[$category][$level]++;
        
        // Also track total errors for this category
        if (!isset(self::$error_stats[$category]['total'])) {
            self::$error_stats[$category]['total'] = 0;
        }
        self::$error_stats[$category]['total']++;
    }
    
    /**
     * Format log message for standard error logging
     * 
     * @param array $log_entry Log entry data
     * @return string Formatted message
     */
    private static function formatLogMessage($log_entry) {
        $message = sprintf(
            '[%s] [%s] [%s] %s',
            strtoupper($log_entry['level']),
            strtoupper($log_entry['category']),
            $log_entry['timestamp'],
            $log_entry['message']
        );
        
        // Add context if available
        if (!empty($log_entry['context'])) {
            $message .= ' | Context: ' . json_encode($log_entry['context']);
        }
        
        // Add request ID if available
        if (!empty($log_entry['request_id'])) {
            $message .= ' | Request: ' . $log_entry['request_id'];
        }
        
        return $message;
    }
    
    /**
     * Log to WordPress debug system
     * 
     * @param array $log_entry Log entry data
     */
    private static function logToWordPress($log_entry) {
        if (function_exists('wp_debug_log')) {
            wp_debug_log($log_entry);
        }
        
        // Alternative WordPress logging
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            $message = sprintf(
                'SMO Social %s: %s',
                strtoupper($log_entry['level']),
                $log_entry['message']
            );
            
            if (!empty($log_entry['context'])) {
                $message .= ' | ' . json_encode($log_entry['context']);
            }
            
            error_log($message);
        }
    }
    
    /**
     * Get or generate request ID for tracking
     * 
     * @return string
     */
    private static function getRequestId() {
        static $request_id = null;
        
        if ($request_id === null) {
            $request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? 
                         $_SERVER['HTTP_X_REQUESTID'] ?? 
                         uniqid('req_', true);
        }
        
        return $request_id;
    }
    
    /**
     * Create safe error response for AJAX requests
     * 
     * @param \Exception $exception Exception to handle
     * @param bool $debug_mode Whether to include debug information
     * @return array Error response array
     */
    public static function createAjaxErrorResponse($exception, $debug_mode = false) {
        $response = [
            'success' => false,
            'message' => 'An error occurred while processing your request'
        ];
        
        if ($debug_mode || (defined('WP_DEBUG') && WP_DEBUG)) {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        // Log the error
        self::logException($exception);
        
        return $response;
    }
    
    /**
     * Handle critical errors with emergency procedures
     * 
     * @param string $message Error message
     * @param array $context Error context
     */
    public static function handleCriticalError($message, $context = []) {
        self::log(self::LEVEL_CRITICAL, $message, $context, self::CATEGORY_SYSTEM);
        
        // Trigger emergency procedures
        do_action('smo_social_critical_error', $message, $context);
        
        // In production, you might want to:
        // - Send alerts to administrators
        // - Activate emergency maintenance mode
        // - Log to external monitoring systems
    }
}
