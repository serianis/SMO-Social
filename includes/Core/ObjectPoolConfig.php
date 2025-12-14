<?php
/**
 * Object Pool Configuration for SMO Social
 *
 * Centralized configuration management for all object pools
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class ObjectPoolConfig {
    /**
     * @var array Default pool configurations
     */
    private static $default_config = [
        'database_pool' => [
            'max_pool_size' => 10,
            'connection_timeout' => 300,
            'enabled' => true
        ],
        'ai_cache_pool' => [
            'max_pool_size' => 20,
            'object_timeout' => 600,
            'enabled' => true
        ],
        'core_cache_pool' => [
            'max_pool_size' => 15,
            'object_timeout' => 600,
            'enabled' => true
        ],
        'websocket_pool' => [
            'max_pool_size' => 50,
            'connection_timeout' => 300,
            'enabled' => true
        ],
        'monitoring' => [
            'cleanup_interval' => 300,
            'stats_logging' => true,
            'memory_tracking' => true
        ]
    ];

    /**
     * Get pool configuration
     *
     * @param string $pool_name Pool name
     * @return array Pool configuration
     */
    public static function get_pool_config($pool_name) {
        $settings = get_option('smo_social_settings', []);
        $pool_config = self::$default_config[$pool_name] ?? [];

        // Merge with saved settings if available
        if (isset($settings[$pool_name])) {
            $pool_config = array_merge($pool_config, $settings[$pool_name]);
        }

        return $pool_config;
    }

    /**
     * Save pool configuration
     *
     * @param string $pool_name Pool name
     * @param array $config Configuration to save
     * @return bool True if configuration was saved successfully
     */
    public static function save_pool_config($pool_name, array $config) {
        $settings = get_option('smo_social_settings', []);

        // Validate and sanitize config
        $validated_config = self::validate_pool_config($pool_name, $config);

        $settings[$pool_name] = $validated_config;
        return update_option('smo_social_settings', $settings);
    }

    /**
     * Validate pool configuration
     *
     * @param string $pool_name Pool name
     * @param array $config Configuration to validate
     * @return array Validated configuration
     */
    private static function validate_pool_config($pool_name, array $config) {
        $default_config = self::$default_config[$pool_name] ?? [];
        $validated = [];

        foreach ($config as $key => $value) {
            switch ($key) {
                case 'max_pool_size':
                    if (is_numeric($value)) {
                        $min_size = 1;
                        $max_size = 100;

                        // Adjust limits based on pool type
                        if ($pool_name === 'database_pool') {
                            $max_size = 50;
                        } elseif ($pool_name === 'websocket_pool') {
                            $max_size = 200;
                        }

                        $validated[$key] = max($min_size, min($max_size, intval($value)));
                    }
                    break;

                case 'connection_timeout':
                case 'object_timeout':
                    if (is_numeric($value)) {
                        $validated[$key] = max(30, min(3600, intval($value)));
                    }
                    break;

                case 'enabled':
                    $validated[$key] = boolval($value);
                    break;

                case 'cleanup_interval':
                    if (is_numeric($value)) {
                        $validated[$key] = max(60, min(3600, intval($value)));
                    }
                    break;

                case 'stats_logging':
                case 'memory_tracking':
                    $validated[$key] = boolval($value);
                    break;
            }
        }

        // Fill in missing values with defaults
        return array_merge($default_config, $validated);
    }

    /**
     * Get all pool configurations
     *
     * @return array All pool configurations
     */
    public static function get_all_pool_configs() {
        $settings = get_option('smo_social_settings', []);
        $all_configs = [];

        foreach (self::$default_config as $pool_name => $default_config) {
            $all_configs[$pool_name] = self::get_pool_config($pool_name);
        }

        return $all_configs;
    }

    /**
     * Reset pool configuration to defaults
     *
     * @param string|null $pool_name Pool name to reset, or null for all pools
     * @return bool True if configuration was reset successfully
     */
    public static function reset_pool_config($pool_name = null) {
        $settings = get_option('smo_social_settings', []);

        if ($pool_name === null) {
            // Reset all pools
            foreach (array_keys(self::$default_config) as $pool) {
                if (isset($settings[$pool])) {
                    unset($settings[$pool]);
                }
            }
        } else {
            // Reset specific pool
            if (isset($settings[$pool_name])) {
                unset($settings[$pool_name]);
            }
        }

        return update_option('smo_social_settings', $settings);
    }

    /**
     * Get monitoring configuration
     *
     * @return array Monitoring configuration
     */
    public static function get_monitoring_config() {
        return self::get_pool_config('monitoring');
    }

    /**
     * Save monitoring configuration
     *
     * @param array $config Monitoring configuration
     * @return bool True if configuration was saved successfully
     */
    public static function save_monitoring_config(array $config) {
        return self::save_pool_config('monitoring', $config);
    }

    /**
     * Get default configurations
     *
     * @return array Default configurations
     */
    public static function get_default_configs() {
        return self::$default_config;
    }

    /**
     * Initialize default pool configurations if not set
     */
    public static function initialize_default_configs() {
        $settings = get_option('smo_social_settings', []);

        $needs_update = false;
        foreach (self::$default_config as $pool_name => $default_config) {
            if (!isset($settings[$pool_name])) {
                $settings[$pool_name] = $default_config;
                $needs_update = true;
            }
        }

        if ($needs_update) {
            update_option('smo_social_settings', $settings);
        }
    }

    /**
     * Get pool configuration for display in admin interface
     *
     * @return array Formatted pool configurations
     */
    public static function get_admin_pool_configs() {
        $configs = self::get_all_pool_configs();
        $admin_configs = [];

        foreach ($configs as $pool_name => $config) {
            $admin_configs[$pool_name] = [
                'display_name' => self::get_pool_display_name($pool_name),
                'config' => $config,
                'description' => self::get_pool_description($pool_name)
            ];
        }

        return $admin_configs;
    }

    /**
     * Get display name for pool
     *
     * @param string $pool_name Pool name
     * @return string Display name
     */
    private static function get_pool_display_name($pool_name) {
        $names = [
            'database_pool' => 'Database Connection Pool',
            'ai_cache_pool' => 'AI Cache Object Pool',
            'core_cache_pool' => 'Core Cache Object Pool',
            'websocket_pool' => 'WebSocket Connection Pool',
            'monitoring' => 'Pool Monitoring'
        ];

        return $names[$pool_name] ?? ucfirst(str_replace('_', ' ', $pool_name));
    }

    /**
     * Get description for pool
     *
     * @param string $pool_name Pool name
     * @return string Description
     */
    private static function get_pool_description($pool_name) {
        $descriptions = [
            'database_pool' => 'Manages database connection pooling to reduce connection overhead',
            'ai_cache_pool' => 'Manages cache object pooling for AI operations',
            'core_cache_pool' => 'Manages cache object pooling for core operations',
            'websocket_pool' => 'Manages WebSocket connection pooling for real-time features',
            'monitoring' => 'Controls monitoring and cleanup of all object pools'
        ];

        return $descriptions[$pool_name] ?? '';
    }

    /**
     * Get configuration validation rules
     *
     * @return array Validation rules for each pool
     */
    public static function get_validation_rules() {
        return [
            'database_pool' => [
                'max_pool_size' => ['type' => 'integer', 'min' => 1, 'max' => 50],
                'connection_timeout' => ['type' => 'integer', 'min' => 30, 'max' => 3600],
                'enabled' => ['type' => 'boolean']
            ],
            'ai_cache_pool' => [
                'max_pool_size' => ['type' => 'integer', 'min' => 1, 'max' => 100],
                'object_timeout' => ['type' => 'integer', 'min' => 30, 'max' => 3600],
                'enabled' => ['type' => 'boolean']
            ],
            'core_cache_pool' => [
                'max_pool_size' => ['type' => 'integer', 'min' => 1, 'max' => 100],
                'object_timeout' => ['type' => 'integer', 'min' => 30, 'max' => 3600],
                'enabled' => ['type' => 'boolean']
            ],
            'websocket_pool' => [
                'max_pool_size' => ['type' => 'integer', 'min' => 1, 'max' => 200],
                'connection_timeout' => ['type' => 'integer', 'min' => 30, 'max' => 3600],
                'enabled' => ['type' => 'boolean']
            ],
            'monitoring' => [
                'cleanup_interval' => ['type' => 'integer', 'min' => 60, 'max' => 3600],
                'stats_logging' => ['type' => 'boolean'],
                'memory_tracking' => ['type' => 'boolean']
            ]
        ];
    }
}