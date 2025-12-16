<?php
/**
 * Cleanup Configuration for SMO Social
 *
 * Centralized configuration management for all cleanup mechanisms
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

/**
 * Cleanup Configuration Manager
 */
class CleanupConfiguration {
    /**
     * @var array Default configuration for all cleanup mechanisms
     */
    private $default_config = [];

    /**
     * @var array Current configuration
     */
    private $config = [];

    /**
     * @var array Configuration overrides from settings
     */
    private $settings_overrides = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_default_config();
        $this->load_settings_overrides();
        $this->apply_overrides();
    }

    /**
     * Initialize default configuration for all cleanup mechanisms
     */
    private function initialize_default_config() {
        $this->default_config = [
            // Database cleanup configuration
            'database_cleanup' => [
                'idle_timeout' => 300, // 5 minutes
                'stale_timeout' => 1800, // 30 minutes
                'max_cleanup_batch' => 10,
                'health_check_interval' => 60, // 1 minute
                'validation_interval' => 300, // 5 minutes
                'max_retries' => 3,
                'retry_delay' => 1000, // 1 second
                'connection_validation_enabled' => true,
                'health_monitoring_enabled' => true,
                'automatic_cleanup_enabled' => true
            ],

            // WebSocket cleanup configuration
            'websocket_cleanup' => [
                'idle_timeout' => 300, // 5 minutes
                'stale_timeout' => 1800, // 30 minutes
                'max_cleanup_batch' => 10,
                'health_check_interval' => 60, // 1 minute
                'validation_interval' => 300, // 5 minutes
                'max_retries' => 3,
                'retry_delay' => 1000, // 1 second
                'memory_threshold' => 50, // MB
                'connection_validation_enabled' => true,
                'health_monitoring_enabled' => true,
                'automatic_cleanup_enabled' => true,
                'memory_based_cleanup_enabled' => true
            ],

            // Cache cleanup configuration
            'cache_cleanup' => [
                'idle_timeout' => 300, // 5 minutes
                'stale_timeout' => 1800, // 30 minutes
                'max_cleanup_batch' => 10,
                'health_check_interval' => 60, // 1 minute
                'validation_interval' => 300, // 5 minutes
                'max_retries' => 3,
                'retry_delay' => 1000, // 1 second
                'memory_threshold' => 50, // MB
                'cache_ttl' => 3600, // 1 hour default TTL
                'connection_validation_enabled' => true,
                'health_monitoring_enabled' => true,
                'automatic_cleanup_enabled' => true,
                'memory_based_cleanup_enabled' => true
            ],

            // Global cleanup configuration
            'global_cleanup' => [
                'logging_enabled' => true,
                'log_level' => 'info', // info, warning, error
                'performance_monitoring_enabled' => true,
                'resource_monitoring_enabled' => true,
                'cleanup_cron_interval' => 'hourly', // hourly, daily, weekly
                'max_cleanup_execution_time' => 30, // seconds
                'cleanup_priority' => 'balanced' // aggressive, balanced, conservative
            ]
        ];
    }

    /**
     * Load configuration overrides from WordPress settings
     */
    private function load_settings_overrides() {
        $settings = get_option('smo_social_settings', []);

        // Load database cleanup overrides
        if (isset($settings['database_cleanup']) && is_array($settings['database_cleanup'])) {
            $this->settings_overrides['database_cleanup'] = $settings['database_cleanup'];
        }

        // Load WebSocket cleanup overrides
        if (isset($settings['websocket_cleanup']) && is_array($settings['websocket_cleanup'])) {
            $this->settings_overrides['websocket_cleanup'] = $settings['websocket_cleanup'];
        }

        // Load cache cleanup overrides
        if (isset($settings['cache_cleanup']) && is_array($settings['cache_cleanup'])) {
            $this->settings_overrides['cache_cleanup'] = $settings['cache_cleanup'];
        }

        // Load global cleanup overrides
        if (isset($settings['global_cleanup']) && is_array($settings['global_cleanup'])) {
            $this->settings_overrides['global_cleanup'] = $settings['global_cleanup'];
        }
    }

    /**
     * Apply configuration overrides
     */
    private function apply_overrides() {
        $this->config = $this->default_config;

        foreach ($this->settings_overrides as $section => $overrides) {
            if (isset($this->config[$section]) && is_array($this->config[$section])) {
                $this->config[$section] = array_merge($this->config[$section], $overrides);
            }
        }
    }

    /**
     * Get configuration for a specific cleanup mechanism
     *
     * @param string $mechanism Cleanup mechanism name (database, websocket, cache)
     * @return array Configuration for the specified mechanism
     */
    public function get_mechanism_config($mechanism) {
        $mechanism_key = $mechanism . '_cleanup';

        if (isset($this->config[$mechanism_key])) {
            return $this->config[$mechanism_key];
        }

        return [];
    }

    /**
     * Get global cleanup configuration
     *
     * @return array Global cleanup configuration
     */
    public function get_global_config() {
        return $this->config['global_cleanup'] ?? [];
    }

    /**
     * Get complete configuration
     *
     * @return array Complete cleanup configuration
     */
    public function get_complete_config() {
        return $this->config;
    }

    /**
     * Update configuration for a specific mechanism
     *
     * @param string $mechanism Cleanup mechanism name
     * @param array $new_config New configuration to merge
     */
    public function update_mechanism_config($mechanism, $new_config) {
        $mechanism_key = $mechanism . '_cleanup';

        if (isset($this->config[$mechanism_key]) && is_array($this->config[$mechanism_key])) {
            $this->config[$mechanism_key] = array_merge($this->config[$mechanism_key], $new_config);

            // Save to WordPress options
            $settings = get_option('smo_social_settings', []);
            $settings[$mechanism_key] = $this->config[$mechanism_key];
            update_option('smo_social_settings', $settings);
        }
    }

    /**
     * Update global cleanup configuration
     *
     * @param array $new_config New global configuration to merge
     */
    public function update_global_config($new_config) {
        if (isset($this->config['global_cleanup']) && is_array($this->config['global_cleanup'])) {
            $this->config['global_cleanup'] = array_merge($this->config['global_cleanup'], $new_config);

            // Save to WordPress options
            $settings = get_option('smo_social_settings', []);
            $settings['global_cleanup'] = $this->config['global_cleanup'];
            update_option('smo_social_settings', $settings);
        }
    }

    /**
     * Reset configuration to defaults
     */
    public function reset_to_defaults() {
        $this->initialize_default_config();
        $this->settings_overrides = [];

        // Remove cleanup settings from WordPress options
        $settings = get_option('smo_social_settings', []);
        unset($settings['database_cleanup']);
        unset($settings['websocket_cleanup']);
        unset($settings['cache_cleanup']);
        unset($settings['global_cleanup']);
        update_option('smo_social_settings', $settings);

        $this->config = $this->default_config;
    }

    /**
     * Get configuration for database cleanup
     *
     * @return array Database cleanup configuration
     */
    public function get_database_cleanup_config() {
        return $this->get_mechanism_config('database');
    }

    /**
     * Get configuration for WebSocket cleanup
     *
     * @return array WebSocket cleanup configuration
     */
    public function get_websocket_cleanup_config() {
        return $this->get_mechanism_config('websocket');
    }

    /**
     * Get configuration for cache cleanup
     *
     * @return array Cache cleanup configuration
     */
    public function get_cache_cleanup_config() {
        return $this->get_mechanism_config('cache');
    }

    /**
     * Validate configuration values
     *
     * @param array $config Configuration to validate
     * @return array Validated configuration
     */
    public function validate_config($config) {
        $validated = [];

        foreach ($config as $key => $value) {
            if (is_numeric($value)) {
                // Ensure numeric values are within reasonable bounds
                if ($key === 'idle_timeout' || $key === 'stale_timeout' || $key === 'validation_interval') {
                    $validated[$key] = max(30, min(3600, intval($value))); // 30 seconds to 1 hour
                } elseif ($key === 'max_cleanup_batch') {
                    $validated[$key] = max(1, min(50, intval($value))); // 1 to 50
                } elseif ($key === 'health_check_interval') {
                    $validated[$key] = max(10, min(300, intval($value))); // 10 seconds to 5 minutes
                } elseif ($key === 'memory_threshold') {
                    $validated[$key] = max(10, min(200, intval($value))); // 10MB to 200MB
                } elseif ($key === 'cache_ttl') {
                    $validated[$key] = max(60, min(86400, intval($value))); // 1 minute to 24 hours
                } else {
                    $validated[$key] = intval($value);
                }
            } elseif (is_bool($value)) {
                $validated[$key] = $value;
            } elseif (is_string($value)) {
                // Validate string values
                if ($key === 'cleanup_cron_interval' && !in_array($value, ['hourly', 'daily', 'weekly'])) {
                    $validated[$key] = 'hourly'; // Default
                } elseif ($key === 'log_level' && !in_array($value, ['info', 'warning', 'error'])) {
                    $validated[$key] = 'info'; // Default
                } elseif ($key === 'cleanup_priority' && !in_array($value, ['aggressive', 'balanced', 'conservative'])) {
                    $validated[$key] = 'balanced'; // Default
                } else {
                    $validated[$key] = $value;
                }
            } else {
                $validated[$key] = $value;
            }
        }

        return $validated;
    }

    /**
     * Get configuration recommendations based on system resources
     *
     * @return array Recommended configuration
     */
    public function get_recommended_config() {
        $memory_limit = $this->get_memory_limit_in_bytes();
        $recommendations = [];

        // Database recommendations
        $recommendations['database_cleanup'] = [
            'idle_timeout' => 300,
            'stale_timeout' => 1800,
            'max_cleanup_batch' => min(10, max(1, floor($memory_limit / (1024 * 1024 * 50)))), // Adjust based on memory
            'health_check_interval' => 60,
            'validation_interval' => 300
        ];

        // WebSocket recommendations
        $recommendations['websocket_cleanup'] = [
            'idle_timeout' => 300,
            'stale_timeout' => 1800,
            'max_cleanup_batch' => min(10, max(1, floor($memory_limit / (1024 * 1024 * 50)))),
            'health_check_interval' => 60,
            'memory_threshold' => min(50, max(10, floor($memory_limit / (1024 * 1024 * 2)))) // 50% of memory limit
        ];

        // Cache recommendations
        $recommendations['cache_cleanup'] = [
            'idle_timeout' => 300,
            'stale_timeout' => 1800,
            'max_cleanup_batch' => min(10, max(1, floor($memory_limit / (1024 * 1024 * 50)))),
            'health_check_interval' => 60,
            'memory_threshold' => min(50, max(10, floor($memory_limit / (1024 * 1024 * 2)))),
            'cache_ttl' => 3600
        ];

        return $recommendations;
    }

    /**
     * Get memory limit in bytes
     *
     * @return int Memory limit in bytes
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
     * Get configuration for logging
     *
     * @return array Logging configuration
     */
    public function get_logging_config() {
        $global_config = $this->get_global_config();
        return [
            'logging_enabled' => $global_config['logging_enabled'] ?? true,
            'log_level' => $global_config['log_level'] ?? 'info',
            'log_file' => defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/smo-social-cleanup.log' : '/tmp/smo-social-cleanup.log',
            'max_log_size' => 10 * 1024 * 1024 // 10MB
        ];
    }

    /**
     * Get configuration for performance monitoring
     *
     * @return array Performance monitoring configuration
     */
    public function get_performance_monitoring_config() {
        $global_config = $this->get_global_config();
        return [
            'performance_monitoring_enabled' => $global_config['performance_monitoring_enabled'] ?? true,
            'monitoring_interval' => 60, // seconds
            'data_retention_days' => 30, // days
            'alert_thresholds' => [
                'high_memory' => 80, // percentage
                'high_cpu' => 75, // percentage
                'slow_operations' => 5000 // milliseconds
            ]
        ];
    }
}