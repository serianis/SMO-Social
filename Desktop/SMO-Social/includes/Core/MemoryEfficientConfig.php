<?php
namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * MemoryEfficientConfig - Configuration manager for memory-efficient processing
 *
 * Centralized configuration for batch sizes, memory limits, and processing parameters
 * across all streaming classes.
 */
class MemoryEfficientConfig {
    private $default_config;
    private $custom_config;
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new MemoryConfigLogger();

        // Set default configuration values
        $this->default_config = array(
            'analytics' => array(
                'batch_size' => 1000,
                'max_memory_usage' => 50, // MB
                'stream_timeout' => 300, // seconds
                'max_retries' => 3
            ),
            'content_import' => array(
                'batch_size' => 500,
                'max_memory_usage' => 40, // MB
                'stream_timeout' => 600, // seconds
                'max_retries' => 5
            ),
            'cache_metadata' => array(
                'batch_size' => 1000,
                'max_memory_usage' => 30, // MB
                'stream_timeout' => 180, // seconds
                'max_retries' => 3
            ),
            'ai_cache' => array(
                'batch_size' => 1000,
                'max_memory_usage' => 30, // MB
                'stream_timeout' => 120, // seconds
                'max_retries' => 3
            ),
            'core_cache' => array(
                'batch_size' => 1000,
                'max_memory_usage' => 30, // MB
                'stream_timeout' => 180, // seconds
                'max_retries' => 3
            ),
            'global' => array(
                'memory_warning_threshold' => 70, // % of max memory
                'gc_frequency' => 100, // operations between GC calls
                'max_concurrent_streams' => 5,
                'enable_logging' => true,
                'log_level' => 'info'
            )
        );

        // Load custom configuration if available
        $this->load_custom_config();
    }

    /**
     * Load custom configuration from database or options
     */
    private function load_custom_config() {
        // Try to load from WordPress options
        if (function_exists('get_option')) {
            $saved_config = get_option('smo_memory_efficient_config', array());

            if (!empty($saved_config) && is_array($saved_config)) {
                $this->custom_config = $saved_config;
                $this->logger->log("Loaded custom memory-efficient configuration from database");
                return;
            }
        }

        // No custom config found, use defaults
        $this->custom_config = array();
        $this->logger->log("Using default memory-efficient configuration");
    }

    /**
     * Save current configuration to database
     */
    public function save_config() {
        if (function_exists('update_option')) {
            $result = update_option('smo_memory_efficient_config', $this->custom_config, true);

            if ($result) {
                $this->logger->log("Memory-efficient configuration saved successfully");
                return true;
            } else {
                $this->logger->log("Failed to save memory-efficient configuration");
                return false;
            }
        }

        $this->logger->log("Cannot save configuration - WordPress functions not available");
        return false;
    }

    /**
     * Get configuration for a specific component
     */
    public function get_config($component) {
        $config = array();

        // Merge default and custom config
        if (isset($this->default_config[$component])) {
            $config = $this->default_config[$component];
        }

        if (isset($this->custom_config[$component])) {
            $config = array_merge($config, $this->custom_config[$component]);
        }

        return $config;
    }

    /**
     * Set custom configuration for a component
     */
    public function set_config($component, $config) {
        if (!isset($this->default_config[$component])) {
            $this->logger->log("Invalid component: {$component}");
            return false;
        }

        // Validate configuration
        $validated_config = $this->validate_config($component, $config);

        if ($validated_config === false) {
            $this->logger->log("Invalid configuration for component: {$component}");
            return false;
        }

        $this->custom_config[$component] = $validated_config;
        $this->logger->log("Updated configuration for component: {$component}");

        return true;
    }

    /**
     * Validate configuration values
     */
    public function validate_config($component, $config) {
        $valid_config = array();

        foreach ($config as $key => $value) {
            switch ($key) {
                case 'batch_size':
                    if (is_numeric($value) && $value > 0 && $value <= 10000) {
                        $valid_config[$key] = intval($value);
                    }
                    break;

                case 'max_memory_usage':
                    if (is_numeric($value) && $value > 0 && $value <= 100) { // Max 100MB
                        $valid_config[$key] = intval($value);
                    }
                    break;

                case 'stream_timeout':
                    if (is_numeric($value) && $value > 0 && $value <= 3600) { // Max 1 hour
                        $valid_config[$key] = intval($value);
                    }
                    break;

                case 'max_retries':
                    if (is_numeric($value) && $value >= 0 && $value <= 10) {
                        $valid_config[$key] = intval($value);
                    }
                    break;

                case 'memory_warning_threshold':
                    if (is_numeric($value) && $value > 0 && $value <= 100) {
                        $valid_config[$key] = intval($value);
                    }
                    break;

                case 'gc_frequency':
                    if (is_numeric($value) && $value > 0 && $value <= 1000) {
                        $valid_config[$key] = intval($value);
                    }
                    break;

                case 'max_concurrent_streams':
                    if (is_numeric($value) && $value > 0 && $value <= 20) {
                        $valid_config[$key] = intval($value);
                    }
                    break;

                case 'enable_logging':
                    if (is_bool($value)) {
                        $valid_config[$key] = $value;
                    }
                    break;

                case 'log_level':
                    if (in_array($value, array('debug', 'info', 'warning', 'error', 'critical'))) {
                        $valid_config[$key] = $value;
                    }
                    break;
            }
        }

        return empty($valid_config) ? false : $valid_config;
    }

    /**
     * Get global configuration
     */
    public function get_global_config() {
        $config = array();

        if (isset($this->default_config['global'])) {
            $config = $this->default_config['global'];
        }

        if (isset($this->custom_config['global'])) {
            $config = array_merge($config, $this->custom_config['global']);
        }

        return $config;
    }

    /**
     * Set global configuration
     */
    public function set_global_config($config) {
        $validated_config = array();

        foreach ($config as $key => $value) {
            if (isset($this->default_config['global'][$key])) {
                $validated = $this->validate_config('global', array($key => $value));

                if ($validated !== false) {
                    $validated_config[$key] = $validated[$key];
                }
            }
        }

        if (!empty($validated_config)) {
            if (!isset($this->custom_config['global'])) {
                $this->custom_config['global'] = array();
            }

            $this->custom_config['global'] = array_merge($this->custom_config['global'], $validated_config);
            $this->logger->log("Updated global memory-efficient configuration");
            return true;
        }

        return false;
    }

    /**
     * Get all configuration
     */
    public function get_all_config() {
        return array(
            'default' => $this->default_config,
            'custom' => $this->custom_config,
            'effective' => $this->get_effective_config()
        );
    }

    /**
     * Get effective configuration (merged default + custom)
     */
    public function get_effective_config() {
        $effective = $this->default_config;

        foreach ($this->custom_config as $component => $config) {
            if (isset($effective[$component])) {
                $effective[$component] = array_merge($effective[$component], $config);
            } else {
                $effective[$component] = $config;
            }
        }

        return $effective;
    }

    /**
     * Reset to default configuration
     */
    public function reset_to_defaults() {
        $this->custom_config = array();
        $this->logger->log("Reset memory-efficient configuration to defaults");

        if (function_exists('update_option')) {
            delete_option('smo_memory_efficient_config');
        }

        return true;
    }

    /**
     * Check if configuration is valid
     */
    public function is_valid_config() {
        $effective_config = $this->get_effective_config();

        foreach ($effective_config as $component => $config) {
            if ($component === 'global') {
                continue;
            }

            $required_keys = array('batch_size', 'max_memory_usage');
            foreach ($required_keys as $key) {
                if (!isset($config[$key]) || !is_numeric($config[$key]) || $config[$key] <= 0) {
                    $this->logger->log("Invalid configuration for {$component}: missing or invalid {$key}");
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get memory usage recommendations based on current system
     */
    public function get_memory_recommendations() {
        $memory_limit = ini_get('memory_limit');
        $recommendations = array();

        if (preg_match('/(\d+)M/', $memory_limit, $matches)) {
            $memory_limit_mb = intval($matches[1]);

            $recommendations['current_limit'] = $memory_limit_mb;
            $recommendations['suggested_batch_sizes'] = array();

            if ($memory_limit_mb <= 128) {
                $recommendations['suggested_batch_sizes']['analytics'] = 500;
                $recommendations['suggested_batch_sizes']['content_import'] = 250;
                $recommendations['suggested_batch_sizes']['cache'] = 500;
                $recommendations['memory_level'] = 'low';
            } elseif ($memory_limit_mb <= 256) {
                $recommendations['suggested_batch_sizes']['analytics'] = 1000;
                $recommendations['suggested_batch_sizes']['content_import'] = 500;
                $recommendations['suggested_batch_sizes']['cache'] = 1000;
                $recommendations['memory_level'] = 'medium';
            } else {
                $recommendations['suggested_batch_sizes']['analytics'] = 2000;
                $recommendations['suggested_batch_sizes']['content_import'] = 1000;
                $recommendations['suggested_batch_sizes']['cache'] = 1500;
                $recommendations['memory_level'] = 'high';
            }
        } else {
            $recommendations['memory_limit_unknown'] = true;
            $recommendations['suggested_batch_sizes'] = array(
                'analytics' => 1000,
                'content_import' => 500,
                'cache' => 1000
            );
        }

        return $recommendations;
    }

    /**
     * Apply recommended configuration based on system resources
     */
    public function apply_recommended_config() {
        $recommendations = $this->get_memory_recommendations();

        if (isset($recommendations['suggested_batch_sizes'])) {
            $config_updates = array();

            foreach ($recommendations['suggested_batch_sizes'] as $component => $batch_size) {
                if (isset($this->default_config[$component])) {
                    $config_updates[$component] = array(
                        'batch_size' => $batch_size
                    );

                    // Set memory usage based on batch size
                    $memory_usage = min(50, intval($batch_size / 20));
                    $config_updates[$component]['max_memory_usage'] = $memory_usage;
                }
            }

            foreach ($config_updates as $component => $config) {
                $this->set_config($component, $config);
            }

            $this->logger->log("Applied recommended memory-efficient configuration based on system resources");
            return true;
        }

        return false;
    }
}

/**
 * Simple logger for memory configuration
 */
class MemoryConfigLogger {
    public function log($message) {
        if (function_exists('error_log')) {
            error_log('SMO Memory Config: ' . $message);
        }
    }
}