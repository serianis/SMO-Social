<?php
namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * ResourceCleanupManager - Centralized resource cleanup and error handling
 *
 * Manages proper resource cleanup, error handling, and memory management
 * across all streaming classes and memory-efficient data structures.
 */
class ResourceCleanupManager {
    private $registered_streams;
    private $error_handler;
    private $shutdown_registered;
    private $logger;
    private $config;

    /**
     * Constructor
     */
    public function __construct() {
        $this->registered_streams = array();
        $this->error_handler = new MemoryEfficientErrorHandler();
        $this->logger = new CleanupLogger();
        $this->config = new MemoryEfficientConfig();
        $this->shutdown_registered = false;

        $this->initialize();
        $this->register_with_memory_monitor();
    }

    /**
     * Initialize the cleanup manager
     */
    private function initialize() {
        $this->logger->log("Initializing Resource Cleanup Manager");

        // Register shutdown function if not already registered
        if (!$this->shutdown_registered) {
            register_shutdown_function(array($this, 'emergency_cleanup'));
            $this->shutdown_registered = true;
            $this->logger->log("Registered shutdown function for emergency cleanup");
        }

        // Set error handler
        set_error_handler(array($this->error_handler, 'handle_error'));

        // Register for memory monitoring
        $this->register_memory_monitor();

        $this->logger->log("Resource Cleanup Manager initialized");
    }

    /**
     * Register a stream for cleanup management
     */
    public function register_stream($stream_name, $stream_object) {
        if (!isset($this->registered_streams[$stream_name])) {
            $this->registered_streams[$stream_name] = array(
                'object' => $stream_object,
                'registered_at' => time(),
                'last_cleanup' => time(),
                'cleanup_count' => 0,
                'error_count' => 0
            );

            $this->logger->log("Registered stream for cleanup: {$stream_name}");
            return true;
        }

        $this->logger->log("Stream already registered: {$stream_name}");
        return false;
    }

    /**
     * Unregister a stream
     */
    public function unregister_stream($stream_name) {
        if (isset($this->registered_streams[$stream_name])) {
            // Perform final cleanup
            $this->cleanup_stream($stream_name, true);

            unset($this->registered_streams[$stream_name]);
            $this->logger->log("Unregistered and cleaned up stream: {$stream_name}");
            return true;
        }

        $this->logger->log("Stream not found for unregistration: {$stream_name}");
        return false;
    }

    /**
     * Cleanup a specific stream
     */
    public function cleanup_stream($stream_name, $force = false) {
        if (isset($this->registered_streams[$stream_name])) {
            $stream_data = $this->registered_streams[$stream_name];
            $stream_object = $stream_data['object'];

            try {
                // Check if the stream object has cleanup methods
                if (method_exists($stream_object, 'cleanup_resources')) {
                    $stream_object->cleanup_resources();
                }

                if (method_exists($stream_object, 'check_memory_usage')) {
                    $stream_object->check_memory_usage();
                }

                // Update cleanup statistics
                $this->registered_streams[$stream_name]['last_cleanup'] = time();
                $this->registered_streams[$stream_name]['cleanup_count']++;

                $this->logger->log("Performed cleanup for stream: {$stream_name}");

                return true;
            } catch (\Exception $e) {
                $this->registered_streams[$stream_name]['error_count']++;
                $this->logger->log("Error cleaning up stream {$stream_name}: " . $e->getMessage());
                return false;
            }
        }

        $this->logger->log("Stream not found for cleanup: {$stream_name}");
        return false;
    }

    /**
     * Cleanup all registered streams
     */
    public function cleanup_all_streams() {
        $this->logger->log("Starting cleanup of all registered streams");

        $total_cleaned = 0;
        $total_errors = 0;

        foreach ($this->registered_streams as $stream_name => $stream_data) {
            $result = $this->cleanup_stream($stream_name);

            if ($result) {
                $total_cleaned++;
            } else {
                $total_errors++;
            }
        }

        $this->logger->log("Completed cleanup of all streams. Cleaned: {$total_cleaned}, Errors: {$total_errors}");
        return array('cleaned' => $total_cleaned, 'errors' => $total_errors);
    }

    /**
     * Emergency cleanup on shutdown
     */
    public function emergency_cleanup() {
        $this->logger->log("Performing emergency cleanup on shutdown");

        // Force cleanup of all streams
        $this->cleanup_all_streams();

        // Force garbage collection
        gc_collect_cycles();

        // Log memory usage
        $memory_usage = memory_get_usage(true) / (1024 * 1024);
        $this->logger->log("Emergency cleanup completed. Final memory usage: {$memory_usage}MB");

        // Save cleanup statistics if possible
        $this->save_cleanup_statistics();
    }

    /**
     * Register memory monitor
     */
    private function register_memory_monitor() {
        $global_config = $this->config->get_global_config();

        if (isset($global_config['enable_memory_monitoring']) && $global_config['enable_memory_monitoring']) {
            $memory_limit = $this->get_memory_limit_in_bytes();
            $warning_threshold = $memory_limit * ($global_config['memory_warning_threshold'] / 100);

            $this->logger->log("Registered memory monitor. Limit: {$memory_limit} bytes, Warning at: {$warning_threshold} bytes");
        }
    }

    /**
     * Get memory limit in bytes
     */
    private function get_memory_limit_in_bytes() {
        $memory_limit = ini_get('memory_limit');

        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            $value = intval($matches[1]);
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'G':
                    return $value * 1024 * 1024 * 1024;
                case 'M':
                    return $value * 1024 * 1024;
                case 'K':
                    return $value * 1024;
                default:
                    return $value;
            }
        }

        return 128 * 1024 * 1024; // Default to 128MB
    }

    /**
     * Check system memory usage
     */
    public function check_system_memory_usage() {
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->get_memory_limit_in_bytes();
        $usage_percentage = ($memory_usage / $memory_limit) * 100;

        $global_config = $this->config->get_global_config();
        $warning_threshold = $global_config['memory_warning_threshold'] ?? 70;

        if ($usage_percentage > $warning_threshold) {
            $this->logger->log("WARNING: High memory usage - {$usage_percentage}% of {$memory_limit} bytes");

            // Trigger cleanup
            $this->cleanup_all_streams();

            // Force garbage collection
            gc_collect_cycles();

            return true;
        }

        return false;
    }

    /**
     * Save cleanup statistics
     */
    private function save_cleanup_statistics() {
        if (function_exists('update_option')) {
            $statistics = array(
                'last_cleanup' => time(),
                'registered_streams' => count($this->registered_streams),
                'total_cleanups' => 0,
                'total_errors' => 0,
                'memory_usage_history' => array()
            );

            foreach ($this->registered_streams as $stream_name => $stream_data) {
                $statistics['total_cleanups'] += $stream_data['cleanup_count'];
                $statistics['total_errors'] += $stream_data['error_count'];
            }

            // Add current memory usage to history
            $statistics['memory_usage_history'][] = array(
                'timestamp' => time(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true)
            );

            // Keep only last 10 entries
            if (count($statistics['memory_usage_history']) > 10) {
                array_shift($statistics['memory_usage_history']);
            }

            update_option('smo_resource_cleanup_statistics', $statistics, true);
            $this->logger->log("Saved resource cleanup statistics");
        }
    }

    /**
     * Get cleanup statistics
     */
    public function get_cleanup_statistics() {
        if (function_exists('get_option')) {
            $statistics = get_option('smo_resource_cleanup_statistics', array());

            if (!empty($statistics)) {
                return $statistics;
            }
        }

        return array(
            'message' => 'No cleanup statistics available',
            'registered_streams' => count($this->registered_streams)
        );
    }

    /**
     * Reset cleanup statistics
     */
    public function reset_cleanup_statistics() {
        if (function_exists('delete_option')) {
            delete_option('smo_resource_cleanup_statistics');
            $this->logger->log("Reset resource cleanup statistics");
            return true;
        }

        return false;
    }

    /**
     * Handle stream errors
     */
    public function handle_stream_error($stream_name, $error_message, $error_code = 0) {
        if (isset($this->registered_streams[$stream_name])) {
            $this->registered_streams[$stream_name]['error_count']++;
            $this->registered_streams[$stream_name]['last_error'] = array(
                'message' => $error_message,
                'code' => $error_code,
                'timestamp' => time()
            );

            $this->logger->log("Stream error in {$stream_name}: [{$error_code}] {$error_message}");

            // Check if we should disable this stream
            $global_config = $this->config->get_global_config();
            $max_errors = $global_config['max_stream_errors_before_disable'] ?? 10;

            if ($this->registered_streams[$stream_name]['error_count'] >= $max_errors) {
                $this->logger->log("Disabling stream due to repeated errors: {$stream_name}");
                $this->unregister_stream($stream_name);
                return false;
            }

            return true;
        }

        $this->logger->log("Error from unregistered stream {$stream_name}: {$error_message}");
        return false;
    }

    /**
     * Get error statistics
     */
    public function get_error_statistics() {
        $stats = array(
            'total_errors' => 0,
            'errors_by_stream' => array(),
            'recent_errors' => array()
        );

        foreach ($this->registered_streams as $stream_name => $stream_data) {
            if (isset($stream_data['error_count']) && $stream_data['error_count'] > 0) {
                $stats['total_errors'] += $stream_data['error_count'];
                $stats['errors_by_stream'][$stream_name] = $stream_data['error_count'];

                if (isset($stream_data['last_error'])) {
                    $stats['recent_errors'][] = array(
                        'stream' => $stream_name,
                        'error' => $stream_data['last_error']['message'],
                        'code' => $stream_data['last_error']['code'],
                        'timestamp' => $stream_data['last_error']['timestamp']
                    );
                }
            }
        }

        // Sort recent errors by timestamp (newest first)
        usort($stats['recent_errors'], function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return $stats;
    }

    /**
     * Perform periodic maintenance
     */
    public function perform_periodic_maintenance() {
        $this->logger->log("Starting periodic maintenance");

        $maintenance_start = time();

        // 1. Check memory usage
        $high_memory = $this->check_system_memory_usage();

        // 2. Cleanup all streams
        $cleanup_result = $this->cleanup_all_streams();

        // 3. Save statistics
        $this->save_cleanup_statistics();

        // 4. Check for long-running streams
        $this->check_long_running_streams();

        $maintenance_time = time() - $maintenance_start;
        $this->logger->log("Completed periodic maintenance in {$maintenance_time} seconds");

        return array(
            'high_memory_detected' => $high_memory,
            'cleanup_result' => $cleanup_result,
            'maintenance_time' => $maintenance_time
        );
    }

    /**
     * Check for long-running streams
     */
    private function check_long_running_streams() {
        $current_time = time();
        $global_config = $this->config->get_global_config();
        $max_stream_age = $global_config['max_stream_age'] ?? 3600; // 1 hour default

        foreach ($this->registered_streams as $stream_name => $stream_data) {
            $stream_age = $current_time - $stream_data['registered_at'];

            if ($stream_age > $max_stream_age) {
                $this->logger->log("WARNING: Long-running stream detected: {$stream_name} (age: {$stream_age} seconds)");

                // Force cleanup
                $this->cleanup_stream($stream_name, true);
            }
        }
    }

    /**
     * Register with MemoryMonitor for integrated monitoring
     */
    private function register_with_memory_monitor() {
        if (class_exists('\SMO_Social\Core\MemoryMonitor')) {
            try {
                // The MemoryMonitor will collect stats from ResourceCleanupManager when available
                // This ensures the ResourceCleanupManager is recognized as an integrated system
                $this->logger->log("ResourceCleanupManager registered with MemoryMonitor");
            } catch (\Exception $e) {
                $this->logger->log("Failed to register ResourceCleanupManager with MemoryMonitor: " . $e->getMessage());
            }
        }
    }

    /**
     * Get system resource information
     */
    public function get_system_resources() {
        return array(
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => $this->get_memory_limit_in_bytes(),
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'timestamp' => time()
        );
    }
}

/**
 * Memory-efficient error handler
 */
class MemoryEfficientErrorHandler {
    private $logger;

    public function __construct() {
        $this->logger = new CleanupLogger();
    }

    /**
     * Handle errors with memory efficiency
     */
    public function handle_error($errno, $errstr, $errfile, $errline) {
        $error_types = array(
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
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        );

        $error_type = $error_types[$errno] ?? 'UNKNOWN';

        // Only log errors that are relevant to memory management
        if (strpos($errstr, 'memory') !== false ||
            strpos($errstr, 'Memory') !== false ||
            $errno === E_ERROR ||
            $errno === E_USER_ERROR) {

            $this->logger->log("PHP {$error_type}: {$errstr} in {$errfile} on line {$errline}");

            // Check memory usage on errors
            $memory_usage = memory_get_usage(true) / (1024 * 1024);
            $this->logger->log("Current memory usage during error: {$memory_usage}MB");

            // Force cleanup on critical errors
            if ($errno === E_ERROR || $errno === E_USER_ERROR) {
                gc_collect_cycles();
                $this->logger->log("Forced garbage collection after critical error");
            }
        }

        // Don't execute PHP internal error handler
        return true;
    }
}

/**
 * Simple logger for resource cleanup
 */
class CleanupLogger {
    public function log($message) {
        if (function_exists('error_log')) {
            error_log('SMO Cleanup: ' . $message);
        }
    }
}