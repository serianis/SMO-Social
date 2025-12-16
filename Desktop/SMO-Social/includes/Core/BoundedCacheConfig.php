<?php
/**
 * Bounded Cache Configuration for SMO Social
 *
 * Centralized configuration management for bounded cache systems
 * with support for different cache types and eviction policies
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class BoundedCacheConfig {
    /**
     * @var array Default configuration for all bounded cache systems
     */
    private $default_config = [
        'ai_cache' => [
            'max_cache_size' => 104857600, // 100MB
            'max_memory_usage' => 50, // 50MB
            'batch_size' => 1000,
            'lru_cache_limit' => 10000,
            'eviction_policy' => 'lru', // lru, fifo, random
            'cleanup_interval' => 300, // 5 minutes
            'stats_enabled' => true
        ],
        'redis_cache' => [
            'max_cache_size' => 52428800, // 50MB
            'max_memory_usage' => 50, // 50MB
            'batch_size' => 1000,
            'lru_cache_limit' => 10000,
            'eviction_policy' => 'lru',
            'cleanup_interval' => 300,
            'stats_enabled' => true,
            'redis_ttl' => 3600 // 1 hour
        ],
        'object_pool' => [
            'max_pool_size' => 20,
            'max_memory_usage' => 50, // 50MB
            'object_timeout' => 600, // 10 minutes
            'eviction_policy' => 'lru',
            'cleanup_interval' => 300,
            'stats_enabled' => true
        ]
    ];

    /**
     * @var array Current configuration
     */
    private $config = [];

    /**
     * @var array Cache instances
     */
    private $cache_instances = [
        'ai_cache' => null,
        'redis_cache' => null,
        'object_pool' => null
    ];

    /**
     * Constructor
     *
     * @param array $custom_config Custom configuration to override defaults
     */
    public function __construct($custom_config = []) {
        $this->config = $this->load_configuration($custom_config);
    }

    /**
     * Load configuration with WordPress settings integration
     */
    private function load_configuration($custom_config) {
        // Start with defaults
        $config = $this->default_config;

        // Override with custom config
        if (!empty($custom_config)) {
            $config = array_merge($config, $custom_config);
        }

        // Load from WordPress settings if available
        if (function_exists('get_option')) {
            $settings = get_option('smo_social_bounded_cache_settings', []);

            if (!empty($settings)) {
                foreach ($settings as $cache_type => $cache_settings) {
                    if (isset($config[$cache_type])) {
                        $config[$cache_type] = array_merge($config[$cache_type], $cache_settings);
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Get configuration for a specific cache type
     *
     * @param string $cache_type Cache type (ai_cache, redis_cache, object_pool)
     * @return array Configuration for the cache type
     */
    public function get_config($cache_type) {
        if (isset($this->config[$cache_type])) {
            return $this->config[$cache_type];
        }
        return [];
    }

    /**
     * Get all configuration
     *
     * @return array All configuration
     */
    public function get_all_config() {
        return $this->config;
    }

    /**
     * Set configuration for a specific cache type
     *
     * @param string $cache_type Cache type
     * @param array $config Configuration to set
     */
    public function set_config($cache_type, $config) {
        if (isset($this->config[$cache_type])) {
            $this->config[$cache_type] = array_merge($this->config[$cache_type], $config);

            // Save to WordPress settings if available
            if (function_exists('update_option')) {
                $current_settings = get_option('smo_social_bounded_cache_settings', []);
                $current_settings[$cache_type] = $this->config[$cache_type];
                update_option('smo_social_bounded_cache_settings', $current_settings);
            }
        }
    }

    /**
     * Get AI Cache Manager instance with bounded configuration
     *
     * @return \SMO_Social\AI\BoundedCacheManager
     */
    public function get_ai_cache_manager() {
        if ($this->cache_instances['ai_cache'] === null) {
            $config = $this->get_config('ai_cache');
            $this->cache_instances['ai_cache'] = new \SMO_Social\AI\BoundedCacheManager(
                $config['max_cache_size'] ?? 104857600,
                $config['max_memory_usage'] ?? 50
            );

            // Apply batch configuration
            $this->cache_instances['ai_cache']->set_batch_config(
                $config['batch_size'] ?? 1000,
                $config['max_memory_usage'] ?? 50
            );
        }
        return $this->cache_instances['ai_cache'];
    }

    /**
     * Get Redis Cache instance with bounded configuration
     *
     * @param object|null $redis_instance Redis instance
     * @return \SMO_Social\Core\BoundedRedisCache
     */
    public function get_redis_cache($redis_instance = null) {
        if ($this->cache_instances['redis_cache'] === null) {
            $config = $this->get_config('redis_cache');
            $this->cache_instances['redis_cache'] = new \SMO_Social\Core\BoundedRedisCache(
                $redis_instance,
                $config['max_cache_size'] ?? 52428800,
                $config['max_memory_usage'] ?? 50
            );

            // Apply batch configuration
            $this->cache_instances['redis_cache']->set_batch_config(
                $config['batch_size'] ?? 1000,
                $config['max_memory_usage'] ?? 50
            );
        }
        return $this->cache_instances['redis_cache'];
    }

    /**
     * Get Object Pool instance with bounded configuration
     *
     * @return \SMO_Social\Core\BoundedCacheObjectPool
     */
    public function get_object_pool() {
        if ($this->cache_instances['object_pool'] === null) {
            $config = $this->get_config('object_pool');
            $this->cache_instances['object_pool'] = new \SMO_Social\Core\BoundedCacheObjectPool(
                $config['max_pool_size'] ?? 20,
                $config['object_timeout'] ?? 600,
                $config['max_memory_usage'] ?? 50
            );
        }
        return $this->cache_instances['object_pool'];
    }

    /**
     * Get eviction policy configuration
     *
     * @param string $cache_type Cache type
     * @return string Eviction policy
     */
    public function get_eviction_policy($cache_type) {
        $config = $this->get_config($cache_type);
        return $config['eviction_policy'] ?? 'lru';
    }

    /**
     * Set eviction policy for a cache type
     *
     * @param string $cache_type Cache type
     * @param string $policy Eviction policy (lru, fifo, random)
     */
    public function set_eviction_policy($cache_type, $policy) {
        $valid_policies = ['lru', 'fifo', 'random'];
        if (in_array($policy, $valid_policies)) {
            $this->set_config($cache_type, ['eviction_policy' => $policy]);
        }
    }

    /**
     * Get cache size limits
     *
     * @param string $cache_type Cache type
     * @return array Size limits (max_cache_size, max_memory_usage)
     */
    public function get_cache_size_limits($cache_type) {
        $config = $this->get_config($cache_type);
        return [
            'max_cache_size' => $config['max_cache_size'] ?? 0,
            'max_memory_usage' => $config['max_memory_usage'] ?? 0
        ];
    }

    /**
     * Set cache size limits
     *
     * @param string $cache_type Cache type
     * @param int $max_cache_size Maximum cache size in bytes
     * @param int $max_memory_usage Maximum memory usage in MB
     */
    public function set_cache_size_limits($cache_type, $max_cache_size, $max_memory_usage) {
        $this->set_config($cache_type, [
            'max_cache_size' => max(1048576, $max_cache_size), // Minimum 1MB
            'max_memory_usage' => max(10, $max_memory_usage) // Minimum 10MB
        ]);
    }

    /**
     * Get cleanup interval
     *
     * @param string $cache_type Cache type
     * @return int Cleanup interval in seconds
     */
    public function get_cleanup_interval($cache_type) {
        $config = $this->get_config($cache_type);
        return $config['cleanup_interval'] ?? 300;
    }

    /**
     * Set cleanup interval
     *
     * @param string $cache_type Cache type
     * @param int $interval Cleanup interval in seconds
     */
    public function set_cleanup_interval($cache_type, $interval) {
        $this->set_config($cache_type, [
            'cleanup_interval' => max(60, min(3600, $interval)) // 1 minute to 1 hour
        ]);
    }

    /**
     * Enable or disable statistics tracking
     *
     * @param string $cache_type Cache type
     * @param bool $enabled Whether to enable statistics
     */
    public function set_stats_enabled($cache_type, $enabled) {
        $this->set_config($cache_type, ['stats_enabled' => (bool)$enabled]);
    }

    /**
     * Get statistics enabled status
     *
     * @param string $cache_type Cache type
     * @return bool Whether statistics are enabled
     */
    public function get_stats_enabled($cache_type) {
        $config = $this->get_config($cache_type);
        return $config['stats_enabled'] ?? true;
    }

    /**
     * Get all cache instances
     *
     * @return array All cache instances
     */
    public function get_all_cache_instances() {
        return [
            'ai_cache' => $this->get_ai_cache_manager(),
            'redis_cache' => $this->get_redis_cache(),
            'object_pool' => $this->get_object_pool()
        ];
    }

    /**
     * Reset all cache instances
     */
    public function reset_all_caches() {
        if ($this->cache_instances['ai_cache'] !== null) {
            $this->cache_instances['ai_cache']->clear();
        }

        if ($this->cache_instances['redis_cache'] !== null) {
            $this->cache_instances['redis_cache']->clear();
        }

        if ($this->cache_instances['object_pool'] !== null) {
            $this->cache_instances['object_pool']->clear_pool();
        }
    }

    /**
     * Get comprehensive cache statistics
     *
     * @return array Comprehensive statistics for all caches
     */
    public function get_comprehensive_stats() {
        $stats = [];

        // AI Cache stats
        if ($this->cache_instances['ai_cache'] !== null) {
            $stats['ai_cache'] = $this->cache_instances['ai_cache']->get_stats();
        }

        // Redis Cache stats
        if ($this->cache_instances['redis_cache'] !== null) {
            $stats['redis_cache'] = $this->cache_instances['redis_cache']->get_stats();
        }

        // Object Pool stats
        if ($this->cache_instances['object_pool'] !== null) {
            $stats['object_pool'] = $this->cache_instances['object_pool']->get_stats();
        }

        return $stats;
    }

    /**
     * Perform comprehensive cleanup on all caches
     */
    public function perform_comprehensive_cleanup() {
        $results = [];

        // AI Cache cleanup
        if ($this->cache_instances['ai_cache'] !== null) {
            $results['ai_cache'] = $this->cache_instances['ai_cache']->enhanced_cleanup_expired();
        }

        // Redis Cache cleanup
        if ($this->cache_instances['redis_cache'] !== null) {
            $results['redis_cache'] = $this->cache_instances['redis_cache']->enhanced_cleanup();
        }

        // Object Pool cleanup
        if ($this->cache_instances['object_pool'] !== null) {
            $results['object_pool'] = $this->cache_instances['object_pool']->perform_memory_cleanup();
        }

        return $results;
    }

    /**
     * Get configuration as JSON for API responses
     */
    public function get_config_json() {
        return json_encode($this->config, JSON_PRETTY_PRINT);
    }

    /**
     * Load configuration from JSON
     *
     * @param string $json_config JSON configuration string
     */
    public function load_config_from_json($json_config) {
        $config_data = json_decode($json_config, true);
        if (is_array($config_data)) {
            $this->config = array_merge($this->config, $config_data);

            // Save to WordPress settings if available
            if (function_exists('update_option')) {
                update_option('smo_social_bounded_cache_settings', $this->config);
            }
        }
    }

    /**
     * Reset configuration to defaults
     */
    public function reset_to_defaults() {
        $this->config = $this->default_config;

        // Save to WordPress settings if available
        if (function_exists('update_option')) {
            update_option('smo_social_bounded_cache_settings', $this->config);
        }
    }

    /**
     * Get default configuration
     */
    public function get_default_config() {
        return $this->default_config;
    }

    /**
     * Validate configuration
     *
     * @return array Validation results
     */
    public function validate_config() {
        $validation_results = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        foreach ($this->config as $cache_type => $cache_config) {
            // Check cache size limits
            if (isset($cache_config['max_cache_size']) && $cache_config['max_cache_size'] < 1048576) {
                $validation_results['errors'][] = "$cache_type: max_cache_size too small (minimum 1MB)";
                $validation_results['valid'] = false;
            }

            if (isset($cache_config['max_memory_usage']) && $cache_config['max_memory_usage'] < 10) {
                $validation_results['errors'][] = "$cache_type: max_memory_usage too small (minimum 10MB)";
                $validation_results['valid'] = false;
            }

            // Check pool sizes
            if ($cache_type === 'object_pool' && isset($cache_config['max_pool_size'])) {
                if ($cache_config['max_pool_size'] < 5) {
                    $validation_results['warnings'][] = "$cache_type: max_pool_size is small (minimum 5 recommended)";
                } elseif ($cache_config['max_pool_size'] > 100) {
                    $validation_results['warnings'][] = "$cache_type: max_pool_size is large (maximum 100 recommended)";
                }
            }
        }

        return $validation_results;
    }
}