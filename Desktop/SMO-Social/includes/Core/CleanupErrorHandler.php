<?php
/**
 * Cleanup Error Handler for SMO Social
 *
 * Comprehensive error handling and logging system for cleanup operations
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../wordpress-functions.php';
require_once __DIR__ . '/../consolidated-db-stubs.php';
require_once __DIR__ . '/CleanupConfiguration.php';

/**
 * Cleanup Error Handler
 */
class CleanupErrorHandler {
    /**
     * @var CleanupConfiguration Configuration instance
     */
    private $config;

    /**
     * @var array Error statistics
     */
    private $error_stats = [
        'total_errors' => 0,
        'database_errors' => 0,
        'websocket_errors' => 0,
        'cache_errors' => 0,
        'validation_errors' => 0,
        'cleanup_errors' => 0,
        'recovery_attempts' => 0,
        'recovery_successes' => 0,
        'last_error_time' => 0,
        'error_rates' => []
    ];

    /**
     * @var array Error log
     */
    private $error_log = [];

    /**
     * @var int Maximum log size
     */
    private $max_log_size = 100;

    /**
     * Constructor
     */
    public function __construct() {
        $this->config = new CleanupConfiguration();
        $this->initialize_error_logging();
    }

    /**
     * Initialize error logging system
     */
    private function initialize_error_logging() {
        $logging_config = $this->config->get_logging_config();

        // Set error reporting level based on configuration
        if ($logging_config['logging_enabled']) {
            error_reporting(E_ALL);

            // Set custom error handler
            set_error_handler([$this, 'handle_error']);
            set_exception_handler([$this, 'handle_exception']);

            // Register shutdown function for fatal errors
            register_shutdown_function([$this, 'handle_shutdown']);
        }
    }

    /**
     * Handle PHP errors
     *
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool True if error was handled
     */
    public function handle_error($errno, $errstr, $errfile, $errline) {
        $error_types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];

        // E_STRICT removed in PHP 8.4+ - no longer handled

        $error_type = $error_types[$errno] ?? 'UNKNOWN';
        $error_context = [
            'type' => $error_type,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => time(),
            'severity' => $this->get_error_severity($errno),
            'context' => $this->get_error_context()
        ];

        $this->log_error('php_error', $error_context);
        $this->update_error_statistics('php_error');

        // Log to WordPress error log if available
        if (function_exists('error_log')) {
            error_log('SMO Cleanup Error [' . $error_type . ']: ' . $errstr . ' in ' . $errfile . ' on line ' . $errline);
        }

        // For critical errors, attempt recovery
        if ($this->is_critical_error($errno)) {
            $this->attempt_recovery();
        }

        return true;
    }

    /**
     * Handle PHP exceptions
     *
     * @param \Exception $exception Exception object
     */
    public function handle_exception(\Exception $exception) {
        $error_context = [
            'type' => 'EXCEPTION',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'timestamp' => time(),
            'severity' => 'critical',
            'context' => $this->get_error_context(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode()
        ];

        $this->log_error('exception', $error_context);
        $this->update_error_statistics('exception');

        // Log to WordPress error log if available
        if (function_exists('error_log')) {
            error_log('SMO Cleanup Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
            error_log('Trace: ' . $exception->getTraceAsString());
        }

        // Attempt recovery for exceptions
        $this->attempt_recovery();
    }

    /**
     * Handle shutdown for fatal errors
     */
    public function handle_shutdown() {
        $error = error_get_last();

        if ($error !== null && $this->is_fatal_error($error['type'])) {
            $error_context = [
                'type' => 'FATAL_ERROR',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => time(),
                'severity' => 'critical',
                'context' => $this->get_error_context()
            ];

            $this->log_error('fatal_error', $error_context);
            $this->update_error_statistics('fatal_error');

            // Log to WordPress error log if available
            if (function_exists('error_log')) {
                error_log('SMO Cleanup Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
            }

            // Attempt emergency cleanup on fatal errors
            $this->emergency_cleanup();
        }
    }

    /**
     * Log error to internal error log
     *
     * @param string $error_type Error type
     * @param array $error_context Error context
     */
    private function log_error($error_type, $error_context) {
        $error_entry = [
            'id' => uniqid('error_', true),
            'type' => $error_type,
            'timestamp' => time(),
            'context' => $error_context,
            'resolved' => false,
            'recovery_attempted' => false
        ];

        $this->error_log[] = $error_entry;

        // Maintain log size
        if (count($this->error_log) > $this->max_log_size) {
            array_shift($this->error_log);
        }

        // Update error statistics
        $this->error_stats['total_errors']++;
        $this->error_stats['last_error_time'] = time();

        // Update error rates
        $this->update_error_rates($error_type);
    }

    /**
     * Update error statistics
     *
     * @param string $error_type Error type
     */
    private function update_error_statistics($error_type) {
        $this->error_stats['total_errors']++;

        switch ($error_type) {
            case 'database_error':
                $this->error_stats['database_errors']++;
                break;
            case 'websocket_error':
                $this->error_stats['websocket_errors']++;
                break;
            case 'cache_error':
                $this->error_stats['cache_errors']++;
                break;
            case 'validation_error':
                $this->error_stats['validation_errors']++;
                break;
            case 'cleanup_error':
                $this->error_stats['cleanup_errors']++;
                break;
        }
    }

    /**
     * Update error rates
     *
     * @param string $error_type Error type
     */
    private function update_error_rates($error_type) {
        $now = time();
        $this->error_stats['error_rates'][$error_type] = [
            'count' => ($this->error_stats['error_rates'][$error_type]['count'] ?? 0) + 1,
            'last_occurrence' => $now
        ];
    }

    /**
     * Get error severity
     *
     * @param int $errno Error number
     * @return string Error severity
     */
    private function get_error_severity($errno) {
        $critical_errors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        $warning_errors = [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING];
        $notice_errors = [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED];

        // E_STRICT removed in PHP 8.4+ - no longer handled

        if (in_array($errno, $critical_errors)) {
            return 'critical';
        } elseif (in_array($errno, $warning_errors)) {
            return 'warning';
        } elseif (in_array($errno, $notice_errors)) {
            return 'notice';
        }

        return 'unknown';
    }

    /**
     * Get error context
     *
     * @return array Error context information
     */
    private function get_error_context() {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'timestamp' => time()
        ];
    }

    /**
     * Check if error is critical
     *
     * @param int $errno Error number
     * @return bool True if error is critical
     */
    private function is_critical_error($errno) {
        $critical_errors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        return in_array($errno, $critical_errors);
    }

    /**
     * Check if error is fatal
     *
     * @param int $error_type Error type
     * @return bool True if error is fatal
     */
    private function is_fatal_error($error_type) {
        $fatal_errors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        return in_array($error_type, $fatal_errors);
    }

    /**
     * Attempt error recovery
     */
    private function attempt_recovery() {
        $this->error_stats['recovery_attempts']++;

        try {
            // Force garbage collection
            gc_collect_cycles();

            // Clean up resources
            $this->emergency_cleanup();

            // Log recovery attempt
            $this->log_recovery_attempt('automatic_recovery', true);

            $this->error_stats['recovery_successes']++;

        } catch (\Exception $e) {
            $this->log_recovery_attempt('automatic_recovery', false, $e->getMessage());
        }
    }

    /**
     * Perform emergency cleanup
     */
    private function emergency_cleanup() {
        // Clean up database connections
        if (class_exists('\SMO_Social\Core\DatabaseManager')) {
            try {
                \SMO_Social\Core\DatabaseManager::cleanup_connection_pool();
            } catch (\Exception $e) {
                error_log('SMO Cleanup: Emergency database cleanup failed - ' . $e->getMessage());
            }
        }

        // Clean up WebSocket connections
        if (class_exists('\SMO_Social\WebSocket\WebSocketServerManager')) {
            try {
                $websocket_manager = new \SMO_Social\WebSocket\WebSocketServerManager();
                $websocket_manager->cleanup_connection_pool();
            } catch (\Exception $e) {
                error_log('SMO Cleanup: Emergency WebSocket cleanup failed - ' . $e->getMessage());
            }
        }

        // Clean up cache objects
        if (class_exists('\SMO_Social\Core\CacheManager')) {
            try {
                $cache_manager = new \SMO_Social\Core\CacheManager();
                if (method_exists($cache_manager, 'cleanup_cache_object_pool')) {
                    $cache_manager->cleanup_cache_object_pool();
                }
            } catch (\Exception $e) {
                error_log('SMO Cleanup: Emergency cache cleanup failed - ' . $e->getMessage());
            }
        }
    }

    /**
     * Log recovery attempt
     *
     * @param string $recovery_type Recovery type
     * @param bool $success Whether recovery was successful
     * @param string $message Optional message
     */
    private function log_recovery_attempt($recovery_type, $success, $message = '') {
        $recovery_log = [
            'recovery_type' => $recovery_type,
            'success' => $success,
            'timestamp' => time(),
            'message' => $message,
            'context' => $this->get_error_context()
        ];

        // Add to error log
        if (!empty($this->error_log)) {
            $last_error = end($this->error_log);
            $last_error['recovery_attempted'] = true;
            $last_error['recovery_details'] = $recovery_log;
        }
    }

    /**
     * Get error statistics
     *
     * @return array Error statistics
     */
    public function get_error_statistics() {
        return $this->error_stats;
    }

    /**
     * Get error log
     *
     * @param int $limit Maximum number of errors to return
     * @return array Error log
     */
    public function get_error_log($limit = 50) {
        return array_slice($this->error_log, -$limit);
    }

    /**
     * Get recent errors
     *
     * @param int $hours Hours to look back
     * @return array Recent errors
     */
    public function get_recent_errors($hours = 24) {
        $cutoff_time = time() - ($hours * 3600);
        $recent_errors = [];

        foreach ($this->error_log as $error) {
            if ($error['timestamp'] >= $cutoff_time) {
                $recent_errors[] = $error;
            }
        }

        return $recent_errors;
    }

    /**
     * Get error summary
     *
     * @return array Error summary
     */
    public function get_error_summary() {
        $summary = [
            'total_errors' => $this->error_stats['total_errors'],
            'error_rate' => $this->calculate_error_rate(),
            'recovery_rate' => $this->calculate_recovery_rate(),
            'error_types' => [],
            'recent_errors' => $this->get_recent_errors(1)
        ];

        // Count error types
        foreach ($this->error_stats['error_rates'] as $type => $data) {
            $summary['error_types'][$type] = $data['count'];
        }

        return $summary;
    }

    /**
     * Calculate error rate
     *
     * @return float Error rate per hour
     */
    private function calculate_error_rate() {
        if ($this->error_stats['total_errors'] === 0) {
            return 0.0;
        }

        $time_window = max(1, time() - $this->error_stats['last_error_time']);
        return ($this->error_stats['total_errors'] * 3600) / $time_window;
    }

    /**
     * Calculate recovery rate
     *
     * @return float Recovery rate
     */
    private function calculate_recovery_rate() {
        if ($this->error_stats['recovery_attempts'] === 0) {
            return 0.0;
        }

        return $this->error_stats['recovery_successes'] / $this->error_stats['recovery_attempts'];
    }

    /**
     * Clear error log
     */
    public function clear_error_log() {
        $this->error_log = [];
        $this->error_stats = [
            'total_errors' => 0,
            'database_errors' => 0,
            'websocket_errors' => 0,
            'cache_errors' => 0,
            'validation_errors' => 0,
            'cleanup_errors' => 0,
            'recovery_attempts' => 0,
            'recovery_successes' => 0,
            'last_error_time' => 0,
            'error_rates' => []
        ];
    }

    /**
     * Handle database-specific errors
     *
     * @param string $error_type Error type
     * @param string $message Error message
     * @param array $context Error context
     */
    public function handle_database_error($error_type, $message, $context = []) {
        $error_context = array_merge([
            'type' => 'database_error',
            'message' => $message,
            'timestamp' => time(),
            'severity' => 'critical',
            'context' => $this->get_error_context()
        ], $context);

        $this->log_error('database_error', $error_context);
        $this->update_error_statistics('database_error');

        // Log to WordPress error log
        error_log('SMO Database Error: ' . $message);
    }

    /**
     * Handle WebSocket-specific errors
     *
     * @param string $error_type Error type
     * @param string $message Error message
     * @param array $context Error context
     */
    public function handle_websocket_error($error_type, $message, $context = []) {
        $error_context = array_merge([
            'type' => 'websocket_error',
            'message' => $message,
            'timestamp' => time(),
            'severity' => 'critical',
            'context' => $this->get_error_context()
        ], $context);

        $this->log_error('websocket_error', $error_context);
        $this->update_error_statistics('websocket_error');

        // Log to WordPress error log
        error_log('SMO WebSocket Error: ' . $message);
    }

    /**
     * Handle cache-specific errors
     *
     * @param string $error_type Error type
     * @param string $message Error message
     * @param array $context Error context
     */
    public function handle_cache_error($error_type, $message, $context = []) {
        $error_context = array_merge([
            'type' => 'cache_error',
            'message' => $message,
            'timestamp' => time(),
            'severity' => 'critical',
            'context' => $this->get_error_context()
        ], $context);

        $this->log_error('cache_error', $error_context);
        $this->update_error_statistics('cache_error');

        // Log to WordPress error log
        error_log('SMO Cache Error: ' . $message);
    }

    /**
     * Handle validation errors
     *
     * @param string $error_type Error type
     * @param string $message Error message
     * @param array $context Error context
     */
    public function handle_validation_error($error_type, $message, $context = []) {
        $error_context = array_merge([
            'type' => 'validation_error',
            'message' => $message,
            'timestamp' => time(),
            'severity' => 'warning',
            'context' => $this->get_error_context()
        ], $context);

        $this->log_error('validation_error', $error_context);
        $this->update_error_statistics('validation_error');

        // Log to WordPress error log
        error_log('SMO Validation Error: ' . $message);
    }

    /**
     * Handle cleanup operation errors
     *
     * @param string $error_type Error type
     * @param string $message Error message
     * @param array $context Error context
     */
    public function handle_cleanup_error($error_type, $message, $context = []) {
        $error_context = array_merge([
            'type' => 'cleanup_error',
            'message' => $message,
            'timestamp' => time(),
            'severity' => 'warning',
            'context' => $this->get_error_context()
        ], $context);

        $this->log_error('cleanup_error', $error_context);
        $this->update_error_statistics('cleanup_error');

        // Log to WordPress error log
        error_log('SMO Cleanup Error: ' . $message);
    }

    /**
     * Get error reporting configuration
     *
     * @return array Error reporting configuration
     */
    public function get_error_reporting_config() {
        $logging_config = $this->config->get_logging_config();
        return [
            'error_reporting_enabled' => $logging_config['logging_enabled'],
            'log_level' => $logging_config['log_level'],
            'error_log_file' => $logging_config['log_file'],
            'max_log_size' => $logging_config['max_log_size'],
            'error_email_notifications' => false,
            'error_slack_notifications' => false
        ];
    }

    /**
     * Get error severity levels
     *
     * @return array Error severity levels
     */
    public function get_error_severity_levels() {
        return [
            'critical' => ['description' => 'System-critical errors that require immediate attention', 'color' => 'red'],
            'warning' => ['description' => 'Potential issues that may require attention', 'color' => 'orange'],
            'notice' => ['description' => 'Informational messages that don\'t require action', 'color' => 'blue'],
            'unknown' => ['description' => 'Unclassified errors', 'color' => 'gray']
        ];
    }
}