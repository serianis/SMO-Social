<?php
/**
 * Memory Monitor Configuration for SMO Social
 *
 * Centralized configuration management for memory monitoring system
 * with WordPress settings integration and validation.
 *
 * @package SMO_Social
 * @subpackage Core
 * @since 1.0.0
 */

namespace SMO_Social\Core;

if (!defined('ABSPATH')) {
    exit;
}

class MemoryMonitorConfig {
    /**
     * @var MemoryMonitorConfig|null Singleton instance
     */
    private static $instance = null;

    /**
     * @var array Default configuration
     */
    private $default_config = [];

    /**
     * @var array Custom configuration
     */
    private $custom_config = [];

    /**
     * @var array Effective configuration (merged)
     */
    private $effective_config = [];

    /**
     * MemoryMonitorConfig constructor (private for singleton)
     */
    private function __construct() {
        $this->initialize_default_config();
        $this->load_custom_config();
        $this->merge_configurations();
    }

    /**
     * Get singleton instance
     *
     * @return MemoryMonitorConfig
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize default configuration
     */
    private function initialize_default_config() {
        $this->default_config = [
            // Memory monitoring settings
            'monitoring_enabled' => true,
            'monitoring_interval' => 10, // seconds
            'warning_threshold' => 70, // percentage
            'critical_threshold' => 90, // percentage
            'max_history_entries' => 100,

            // Alert system settings
            'alert_system_enabled' => true,
            'max_active_alerts' => 50,
            'max_history_entries' => 200,
            'auto_resolve_hours' => 24,
            'notification_channels' => ['admin_dashboard', 'log'],
            'email_notifications' => false,
            'email_recipients' => [get_option('admin_email')],

            // Database settings
            'database_cleanup_interval' => 86400, // 24 hours in seconds
            'database_max_records' => 1000,

            // Integration settings
            'integrate_with_object_pools' => true,
            'integrate_with_cache_systems' => true,
            'integrate_with_connection_pools' => true,

            // Performance settings
            'enable_real_time_monitoring' => true,
            'enable_historical_analysis' => true,
            'enable_memory_leak_detection' => true,
            'enable_efficiency_scoring' => true,

            // Display settings
            'show_admin_footer_stats' => true,
            'show_dashboard_widget' => true,
            'show_alert_notifications' => true
        ];
    }

    /**
     * Load custom configuration from database
     */
    private function load_custom_config() {
        if (function_exists('get_option')) {
            $saved_config = get_option('smo_memory_monitor_config', []);

            if (!empty($saved_config) && is_array($saved_config)) {
                $this->custom_config = $saved_config;
            }
        }
    }

    /**
     * Merge default and custom configurations
     */
    private function merge_configurations() {
        $this->effective_config = array_merge($this->default_config, $this->custom_config);
    }

    /**
     * Get complete configuration
     *
     * @return array Complete configuration
     */
    public function get_config() {
        return $this->effective_config;
    }

    /**
     * Get memory monitoring configuration
     *
     * @return array Memory monitoring configuration
     */
    public function get_monitoring_config() {
        return [
            'monitoring_enabled' => $this->effective_config['monitoring_enabled'],
            'monitoring_interval' => $this->effective_config['monitoring_interval'],
            'warning_threshold' => $this->effective_config['warning_threshold'],
            'critical_threshold' => $this->effective_config['critical_threshold'],
            'max_history_entries' => $this->effective_config['max_history_entries']
        ];
    }

    /**
     * Get alert system configuration
     *
     * @return array Alert system configuration
     */
    public function get_alert_config() {
        return [
            'alert_system_enabled' => $this->effective_config['alert_system_enabled'],
            'max_active_alerts' => $this->effective_config['max_active_alerts'],
            'max_history_entries' => $this->effective_config['max_history_entries'],
            'auto_resolve_hours' => $this->effective_config['auto_resolve_hours'],
            'notification_channels' => $this->effective_config['notification_channels'],
            'email_notifications' => $this->effective_config['email_notifications'],
            'email_recipients' => $this->effective_config['email_recipients']
        ];
    }

    /**
     * Get database configuration
     *
     * @return array Database configuration
     */
    public function get_database_config() {
        return [
            'database_cleanup_interval' => $this->effective_config['database_cleanup_interval'],
            'database_max_records' => $this->effective_config['database_max_records']
        ];
    }

    /**
     * Get integration configuration
     *
     * @return array Integration configuration
     */
    public function get_integration_config() {
        return [
            'integrate_with_object_pools' => $this->effective_config['integrate_with_object_pools'],
            'integrate_with_cache_systems' => $this->effective_config['integrate_with_cache_systems'],
            'integrate_with_connection_pools' => $this->effective_config['integrate_with_connection_pools']
        ];
    }

    /**
     * Get performance configuration
     *
     * @return array Performance configuration
     */
    public function get_performance_config() {
        return [
            'enable_real_time_monitoring' => $this->effective_config['enable_real_time_monitoring'],
            'enable_historical_analysis' => $this->effective_config['enable_historical_analysis'],
            'enable_memory_leak_detection' => $this->effective_config['enable_memory_leak_detection'],
            'enable_efficiency_scoring' => $this->effective_config['enable_efficiency_scoring']
        ];
    }

    /**
     * Get display configuration
     *
     * @return array Display configuration
     */
    public function get_display_config() {
        return [
            'show_admin_footer_stats' => $this->effective_config['show_admin_footer_stats'],
            'show_dashboard_widget' => $this->effective_config['show_dashboard_widget'],
            'show_alert_notifications' => $this->effective_config['show_alert_notifications']
        ];
    }

    /**
     * Update configuration with change logging
     *
     * @param array $new_config New configuration values
     * @return bool True if configuration was updated
     */
    public function update_config($new_config) {
        $old_config = $this->effective_config;

        $validated_config = $this->validate_config($new_config);

        if ($validated_config === false) {
            return false;
        }

        $this->custom_config = array_merge($this->custom_config, $validated_config);
        $this->merge_configurations();

        $result = $this->save_config();

        if ($result) {
            $this->log_config_change('update', $old_config, $this->effective_config);
        }

        return $result;
    }

    /**
     * Update monitoring configuration
     *
     * @param array $monitoring_config Monitoring configuration
     * @return bool True if configuration was updated
     */
    public function update_monitoring_config($monitoring_config) {
        $validated = [];

        if (isset($monitoring_config['monitoring_enabled'])) {
            $validated['monitoring_enabled'] = boolval($monitoring_config['monitoring_enabled']);
        }

        if (isset($monitoring_config['monitoring_interval'])) {
            $interval = intval($monitoring_config['monitoring_interval']);
            $validated['monitoring_interval'] = max(1, min(60, $interval));
        }

        if (isset($monitoring_config['warning_threshold'])) {
            $threshold = intval($monitoring_config['warning_threshold']);
            $validated['warning_threshold'] = max(10, min(90, $threshold));
        }

        if (isset($monitoring_config['critical_threshold'])) {
            $threshold = intval($monitoring_config['critical_threshold']);
            $validated['critical_threshold'] = max(50, min(99, $threshold));

            // Ensure critical threshold is higher than warning threshold
            if (isset($validated['warning_threshold']) && $validated['critical_threshold'] <= $validated['warning_threshold']) {
                $validated['critical_threshold'] = $validated['warning_threshold'] + 10;
            }
        }

        if (isset($monitoring_config['max_history_entries'])) {
            $max_history = intval($monitoring_config['max_history_entries']);
            $validated['max_history_entries'] = max(50, min(1000, $max_history));
        }

        if (empty($validated)) {
            return false;
        }

        return $this->update_config($validated);
    }

    /**
     * Update alert configuration
     *
     * @param array $alert_config Alert configuration
     * @return bool True if configuration was updated
     */
    public function update_alert_config($alert_config) {
        $validated = [];

        if (isset($alert_config['alert_system_enabled'])) {
            $validated['alert_system_enabled'] = boolval($alert_config['alert_system_enabled']);
        }

        if (isset($alert_config['max_active_alerts'])) {
            $max_active = intval($alert_config['max_active_alerts']);
            $validated['max_active_alerts'] = max(10, min(200, $max_active));
        }

        if (isset($alert_config['max_history_entries'])) {
            $max_history = intval($alert_config['max_history_entries']);
            $validated['max_history_entries'] = max(50, min(1000, $max_history));
        }

        if (isset($alert_config['auto_resolve_hours'])) {
            $auto_resolve = intval($alert_config['auto_resolve_hours']);
            $validated['auto_resolve_hours'] = max(1, min(168, $auto_resolve)); // Max 1 week
        }

        if (isset($alert_config['notification_channels'])) {
            $channels = $alert_config['notification_channels'];
            if (is_array($channels)) {
                $valid_channels = ['admin_dashboard', 'email', 'log', 'webhook'];
                $validated_channels = array_intersect($channels, $valid_channels);
                $validated['notification_channels'] = array_values($validated_channels);
            }
        }

        if (isset($alert_config['email_notifications'])) {
            $validated['email_notifications'] = boolval($alert_config['email_notifications']);
        }

        if (isset($alert_config['email_recipients'])) {
            $recipients = $alert_config['email_recipients'];
            if (is_array($recipients)) {
                $validated_recipients = [];
                foreach ($recipients as $email) {
                    if (is_email($email)) {
                        $validated_recipients[] = $email;
                    }
                }
                if (!empty($validated_recipients)) {
                    $validated['email_recipients'] = $validated_recipients;
                }
            }
        }

        if (empty($validated)) {
            return false;
        }

        return $this->update_config($validated);
    }

    /**
     * Validate configuration values
     *
     * @param array $config Configuration to validate
     * @return array|false Validated configuration or false
     */
    private function validate_config($config) {
        if (empty($config) || !is_array($config)) {
            return false;
        }

        $validated_config = [];

        foreach ($config as $key => $value) {
            switch ($key) {
                case 'monitoring_enabled':
                case 'alert_system_enabled':
                case 'integrate_with_object_pools':
                case 'integrate_with_cache_systems':
                case 'integrate_with_connection_pools':
                case 'enable_real_time_monitoring':
                case 'enable_historical_analysis':
                case 'enable_memory_leak_detection':
                case 'enable_efficiency_scoring':
                case 'show_admin_footer_stats':
                case 'show_dashboard_widget':
                case 'show_alert_notifications':
                case 'email_notifications':
                    $validated_config[$key] = boolval($value);
                    break;

                case 'monitoring_interval':
                    $int_value = intval($value);
                    $validated_config[$key] = max(1, min(60, $int_value));
                    break;

                case 'warning_threshold':
                    $int_value = intval($value);
                    $validated_config[$key] = max(10, min(90, $int_value));
                    break;

                case 'critical_threshold':
                    $int_value = intval($value);
                    $validated_config[$key] = max(50, min(99, $int_value));
                    break;

                case 'max_history_entries':
                case 'max_active_alerts':
                case 'database_max_records':
                    $int_value = intval($value);
                    $validated_config[$key] = max(10, min(10000, $int_value));
                    break;

                case 'auto_resolve_hours':
                    $int_value = intval($value);
                    $validated_config[$key] = max(1, min(720, $int_value)); // Max 30 days
                    break;

                case 'database_cleanup_interval':
                    $int_value = intval($value);
                    $validated_config[$key] = max(3600, min(86400, $int_value)); // 1 hour to 1 day
                    break;

                case 'notification_channels':
                    if (is_array($value)) {
                        $valid_channels = ['admin_dashboard', 'email', 'log', 'webhook'];
                        $validated_config[$key] = array_intersect($value, $valid_channels);
                    }
                    break;

                case 'email_recipients':
                    if (is_array($value)) {
                        $validated_emails = [];
                        foreach ($value as $email) {
                            if (is_email($email)) {
                                $validated_emails[] = $email;
                            }
                        }
                        if (!empty($validated_emails)) {
                            $validated_config[$key] = $validated_emails;
                        }
                    }
                    break;
            }
        }

        return empty($validated_config) ? false : $validated_config;
    }

    /**
     * Save configuration to database
     *
     * @return bool True if configuration was saved
     */
    private function save_config() {
        if (function_exists('update_option')) {
            $result = update_option('smo_memory_monitor_config', $this->custom_config, true);

            if ($result) {
                $this->merge_configurations();
                return true;
            }
        }

        return false;
    }

    /**
     * Reset configuration to defaults
     *
     * @return bool True if configuration was reset
     */
    public function reset_to_defaults() {
        $this->custom_config = [];
        $this->merge_configurations();

        if (function_exists('update_option')) {
            return update_option('smo_memory_monitor_config', [], true);
        }

        return false;
    }

    /**
     * Get configuration validation status
     *
     * @return array Validation status
     */
    public function validate_current_config() {
        $validation = [
            'is_valid' => true,
            'issues' => [],
            'warnings' => []
        ];

        // Check monitoring configuration
        if ($this->effective_config['warning_threshold'] >= $this->effective_config['critical_threshold']) {
            $validation['is_valid'] = false;
            $validation['issues'][] = 'Warning threshold should be lower than critical threshold';
        }

        if ($this->effective_config['monitoring_interval'] < 1 || $this->effective_config['monitoring_interval'] > 60) {
            $validation['is_valid'] = false;
            $validation['issues'][] = 'Monitoring interval should be between 1 and 60 seconds';
        }

        // Check alert configuration
        if ($this->effective_config['max_active_alerts'] < 10) {
            $validation['warnings'][] = 'Max active alerts is quite low, consider increasing';
        }

        if ($this->effective_config['auto_resolve_hours'] < 1) {
            $validation['is_valid'] = false;
            $validation['issues'][] = 'Auto-resolve hours should be at least 1 hour';
        }

        // Check notification channels
        if (empty($this->effective_config['notification_channels'])) {
            $validation['warnings'][] = 'No notification channels configured';
        }

        // Check email configuration
        if ($this->effective_config['email_notifications'] && empty($this->effective_config['email_recipients'])) {
            $validation['is_valid'] = false;
            $validation['issues'][] = 'Email notifications enabled but no recipients configured';
        }

        return $validation;
    }

    /**
     * Get configuration recommendations based on system
     *
     * @return array Configuration recommendations
     */
    public function get_configuration_recommendations() {
        $memory_limit = ini_get('memory_limit');
        $recommendations = [
            'monitoring' => [],
            'alerts' => [],
            'performance' => []
        ];

        if (preg_match('/^(\d+)(\w)$/', $memory_limit, $matches)) {
            $limit_value = (int)$matches[1];
            $limit_unit = strtolower($matches[2]);

            switch ($limit_unit) {
                case 'g':
                    $limit_value *= 1024;
                    // Fall through to MB
                case 'm':
                    $limit_value *= 1024;
                    // Fall through to KB
                case 'k':
                    $limit_value *= 1024;
                    break;
            }

            // Recommendations based on memory limit
            if ($limit_value <= 67108864) { // 64MB
                $recommendations['monitoring'][] = 'Consider increasing PHP memory limit for better performance';
                $recommendations['monitoring'][] = 'Set warning threshold to 60% and critical to 80% for low memory environments';
            } elseif ($limit_value <= 134217728) { // 128MB
                $recommendations['monitoring'][] = 'Memory limit is moderate, monitor usage closely';
            } else {
                $recommendations['monitoring'][] = 'Memory limit is sufficient for most operations';
            }
        }

        // Alert recommendations
        if ($this->effective_config['email_notifications']) {
            $recommendations['alerts'][] = 'Email notifications are enabled - ensure email recipients are valid';
        } else {
            $recommendations['alerts'][] = 'Consider enabling email notifications for critical alerts';
        }

        // Performance recommendations
        if (!$this->effective_config['enable_memory_leak_detection']) {
            $recommendations['performance'][] = 'Enable memory leak detection for better system health monitoring';
        }

        if (!$this->effective_config['enable_efficiency_scoring']) {
            $recommendations['performance'][] = 'Enable efficiency scoring to optimize memory usage';
        }

        return $recommendations;
    }

    /**
     * Export configuration
     *
     * @return array Exportable configuration
     */
    public function export_config() {
        return [
            'version' => '1.0',
            'timestamp' => current_time('mysql'),
            'configuration' => $this->effective_config,
            'system_info' => [
                'php_version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'wordpress_version' => get_bloginfo('version')
            ]
        ];
    }

    /**
     * Import configuration
     *
     * @param array $import_data Configuration data to import
     * @return bool True if configuration was imported
     */
    public function import_config($import_data) {
        if (!isset($import_data['configuration']) || !is_array($import_data['configuration'])) {
            return false;
        }

        $imported_config = $import_data['configuration'];
        $validated_config = $this->validate_config($imported_config);

        if ($validated_config === false) {
            return false;
        }

        $this->custom_config = array_merge($this->custom_config, $validated_config);
        $this->merge_configurations();

        return $this->save_config();
    }

    /**
     * Get configuration change history
     *
     * @return array Configuration change history
     */
    public function get_config_change_history() {
        // This would be implemented with a more sophisticated tracking system
        // For now, return basic info
        return [
            'last_updated' => current_time('mysql'),
            'current_version' => '1.0',
            'changes_since_default' => array_diff_assoc($this->effective_config, $this->default_config)
        ];
    }

    /**
     * Get configuration presets for different environments
     *
     * @return array Configuration presets
     */
    public function get_configuration_presets() {
        return [
            'development' => [
                'name' => 'Development',
                'description' => 'Optimized for development with frequent monitoring and detailed logging',
                'config' => [
                    'monitoring_enabled' => true,
                    'monitoring_interval' => 5,
                    'warning_threshold' => 60,
                    'critical_threshold' => 80,
                    'max_history_entries' => 200,
                    'alert_system_enabled' => true,
                    'max_active_alerts' => 100,
                    'auto_resolve_hours' => 1,
                    'notification_channels' => ['admin_dashboard', 'log'],
                    'email_notifications' => false,
                    'database_cleanup_interval' => 1800,
                    'database_max_records' => 500,
                    'integrate_with_object_pools' => true,
                    'integrate_with_cache_systems' => true,
                    'integrate_with_connection_pools' => true,
                    'enable_real_time_monitoring' => true,
                    'enable_historical_analysis' => true,
                    'enable_memory_leak_detection' => true,
                    'enable_efficiency_scoring' => true,
                    'show_admin_footer_stats' => true,
                    'show_dashboard_widget' => true,
                    'show_alert_notifications' => true
                ]
            ],
            'production' => [
                'name' => 'Production',
                'description' => 'Optimized for production with balanced monitoring and performance',
                'config' => [
                    'monitoring_enabled' => true,
                    'monitoring_interval' => 30,
                    'warning_threshold' => 75,
                    'critical_threshold' => 90,
                    'max_history_entries' => 500,
                    'alert_system_enabled' => true,
                    'max_active_alerts' => 200,
                    'auto_resolve_hours' => 24,
                    'notification_channels' => ['admin_dashboard', 'email', 'log'],
                    'email_notifications' => true,
                    'database_cleanup_interval' => 3600,
                    'database_max_records' => 1000,
                    'integrate_with_object_pools' => true,
                    'integrate_with_cache_systems' => true,
                    'integrate_with_connection_pools' => true,
                    'enable_real_time_monitoring' => false,
                    'enable_historical_analysis' => true,
                    'enable_memory_leak_detection' => true,
                    'enable_efficiency_scoring' => true,
                    'show_admin_footer_stats' => false,
                    'show_dashboard_widget' => true,
                    'show_alert_notifications' => true
                ]
            ],
            'high_traffic' => [
                'name' => 'High Traffic',
                'description' => 'Optimized for high-traffic sites with efficient monitoring and reduced overhead',
                'config' => [
                    'monitoring_enabled' => true,
                    'monitoring_interval' => 60,
                    'warning_threshold' => 80,
                    'critical_threshold' => 95,
                    'max_history_entries' => 1000,
                    'alert_system_enabled' => true,
                    'max_active_alerts' => 500,
                    'auto_resolve_hours' => 12,
                    'notification_channels' => ['admin_dashboard', 'email', 'log', 'webhook'],
                    'email_notifications' => true,
                    'database_cleanup_interval' => 7200,
                    'database_max_records' => 2000,
                    'integrate_with_object_pools' => true,
                    'integrate_with_cache_systems' => true,
                    'integrate_with_connection_pools' => true,
                    'enable_real_time_monitoring' => false,
                    'enable_historical_analysis' => true,
                    'enable_memory_leak_detection' => false,
                    'enable_efficiency_scoring' => true,
                    'show_admin_footer_stats' => false,
                    'show_dashboard_widget' => false,
                    'show_alert_notifications' => true
                ]
            ],
            'minimal' => [
                'name' => 'Minimal',
                'description' => 'Minimal monitoring for resource-constrained environments',
                'config' => [
                    'monitoring_enabled' => true,
                    'monitoring_interval' => 300,
                    'warning_threshold' => 85,
                    'critical_threshold' => 95,
                    'max_history_entries' => 50,
                    'alert_system_enabled' => false,
                    'max_active_alerts' => 10,
                    'auto_resolve_hours' => 168,
                    'notification_channels' => ['log'],
                    'email_notifications' => false,
                    'database_cleanup_interval' => 86400,
                    'database_max_records' => 100,
                    'integrate_with_object_pools' => false,
                    'integrate_with_cache_systems' => false,
                    'integrate_with_connection_pools' => false,
                    'enable_real_time_monitoring' => false,
                    'enable_historical_analysis' => false,
                    'enable_memory_leak_detection' => false,
                    'enable_efficiency_scoring' => false,
                    'show_admin_footer_stats' => false,
                    'show_dashboard_widget' => false,
                    'show_alert_notifications' => false
                ]
            ]
        ];
    }

    /**
     * Apply configuration preset
     *
     * @param string $preset_name Preset name
     * @return bool True if preset was applied successfully
     */
    public function apply_preset($preset_name) {
        $presets = $this->get_configuration_presets();

        if (!isset($presets[$preset_name])) {
            return false;
        }

        $preset_config = $presets[$preset_name]['config'];
        $validated_config = $this->validate_config($preset_config);

        if ($validated_config === false) {
            return false;
        }

        $this->custom_config = $validated_config;
        $this->merge_configurations();

        return $this->save_config();
    }

    /**
     * Get admin interface settings sections
     *
     * @return array Admin settings sections
     */
    public function get_admin_settings_sections() {
        return [
            'monitoring' => [
                'title' => __('Memory Monitoring', 'smo-social'),
                'description' => __('Configure memory monitoring settings and thresholds.', 'smo-social'),
                'fields' => $this->get_monitoring_admin_fields()
            ],
            'alerts' => [
                'title' => __('Alert System', 'smo-social'),
                'description' => __('Configure alert thresholds and notification settings.', 'smo-social'),
                'fields' => $this->get_alert_admin_fields()
            ],
            'database' => [
                'title' => __('Database Settings', 'smo-social'),
                'description' => __('Configure database cleanup and storage settings.', 'smo-social'),
                'fields' => $this->get_database_admin_fields()
            ],
            'integrations' => [
                'title' => __('System Integrations', 'smo-social'),
                'description' => __('Configure integration with other SMO Social systems.', 'smo-social'),
                'fields' => $this->get_integration_admin_fields()
            ],
            'performance' => [
                'title' => __('Performance Settings', 'smo-social'),
                'description' => __('Configure performance monitoring and analysis features.', 'smo-social'),
                'fields' => $this->get_performance_admin_fields()
            ],
            'display' => [
                'title' => __('Display Options', 'smo-social'),
                'description' => __('Configure how memory information is displayed in the admin interface.', 'smo-social'),
                'fields' => $this->get_display_admin_fields()
            ],
            'presets' => [
                'title' => __('Configuration Presets', 'smo-social'),
                'description' => __('Apply predefined configurations optimized for different environments.', 'smo-social'),
                'fields' => $this->get_preset_admin_fields()
            ]
        ];
    }

    /**
     * Get monitoring admin fields
     *
     * @return array Admin fields for monitoring section
     */
    private function get_monitoring_admin_fields() {
        return [
            'monitoring_enabled' => [
                'type' => 'checkbox',
                'label' => __('Enable Memory Monitoring', 'smo-social'),
                'description' => __('Enable automatic memory monitoring and analysis.', 'smo-social'),
                'default' => true
            ],
            'monitoring_interval' => [
                'type' => 'number',
                'label' => __('Monitoring Interval (seconds)', 'smo-social'),
                'description' => __('How often to check memory usage (1-60 seconds).', 'smo-social'),
                'min' => 1,
                'max' => 60,
                'default' => 10
            ],
            'warning_threshold' => [
                'type' => 'number',
                'label' => __('Warning Threshold (%)', 'smo-social'),
                'description' => __('Memory usage percentage that triggers warnings (10-90%).', 'smo-social'),
                'min' => 10,
                'max' => 90,
                'default' => 70
            ],
            'critical_threshold' => [
                'type' => 'number',
                'label' => __('Critical Threshold (%)', 'smo-social'),
                'description' => __('Memory usage percentage that triggers critical alerts (50-99%).', 'smo-social'),
                'min' => 50,
                'max' => 99,
                'default' => 90
            ],
            'max_history_entries' => [
                'type' => 'number',
                'label' => __('Max History Entries', 'smo-social'),
                'description' => __('Maximum number of monitoring entries to keep in memory (50-1000).', 'smo-social'),
                'min' => 50,
                'max' => 1000,
                'default' => 100
            ]
        ];
    }

    /**
     * Get alert admin fields
     *
     * @return array Admin fields for alert section
     */
    private function get_alert_admin_fields() {
        return [
            'alert_system_enabled' => [
                'type' => 'checkbox',
                'label' => __('Enable Alert System', 'smo-social'),
                'description' => __('Enable automatic alerts for memory issues.', 'smo-social'),
                'default' => true
            ],
            'max_active_alerts' => [
                'type' => 'number',
                'label' => __('Max Active Alerts', 'smo-social'),
                'description' => __('Maximum number of active alerts to maintain (10-500).', 'smo-social'),
                'min' => 10,
                'max' => 500,
                'default' => 50
            ],
            'auto_resolve_hours' => [
                'type' => 'number',
                'label' => __('Auto-resolve Hours', 'smo-social'),
                'description' => __('Hours after which alerts are automatically resolved (1-168).', 'smo-social'),
                'min' => 1,
                'max' => 168,
                'default' => 24
            ],
            'notification_channels' => [
                'type' => 'multicheck',
                'label' => __('Notification Channels', 'smo-social'),
                'description' => __('Select channels for alert notifications.', 'smo-social'),
                'options' => [
                    'admin_dashboard' => __('Admin Dashboard', 'smo-social'),
                    'email' => __('Email', 'smo-social'),
                    'log' => __('System Log', 'smo-social'),
                    'webhook' => __('Webhook', 'smo-social')
                ],
                'default' => ['admin_dashboard', 'log']
            ],
            'email_notifications' => [
                'type' => 'checkbox',
                'label' => __('Email Notifications', 'smo-social'),
                'description' => __('Send email notifications for critical alerts.', 'smo-social'),
                'default' => false
            ],
            'email_recipients' => [
                'type' => 'textarea',
                'label' => __('Email Recipients', 'smo-social'),
                'description' => __('Comma-separated list of email addresses for notifications.', 'smo-social'),
                'default' => get_option('admin_email')
            ]
        ];
    }

    /**
     * Get database admin fields
     *
     * @return array Admin fields for database section
     */
    private function get_database_admin_fields() {
        return [
            'database_cleanup_interval' => [
                'type' => 'number',
                'label' => __('Cleanup Interval (seconds)', 'smo-social'),
                'description' => __('How often to clean up old monitoring data (3600-86400).', 'smo-social'),
                'min' => 3600,
                'max' => 86400,
                'default' => 86400
            ],
            'database_max_records' => [
                'type' => 'number',
                'label' => __('Max Database Records', 'smo-social'),
                'description' => __('Maximum monitoring records to keep in database (100-10000).', 'smo-social'),
                'min' => 100,
                'max' => 10000,
                'default' => 1000
            ]
        ];
    }

    /**
     * Get integration admin fields
     *
     * @return array Admin fields for integration section
     */
    private function get_integration_admin_fields() {
        return [
            'integrate_with_object_pools' => [
                'type' => 'checkbox',
                'label' => __('Object Pool Integration', 'smo-social'),
                'description' => __('Monitor memory usage of object pools.', 'smo-social'),
                'default' => true
            ],
            'integrate_with_cache_systems' => [
                'type' => 'checkbox',
                'label' => __('Cache System Integration', 'smo-social'),
                'description' => __('Monitor memory usage of cache systems.', 'smo-social'),
                'default' => true
            ],
            'integrate_with_connection_pools' => [
                'type' => 'checkbox',
                'label' => __('Connection Pool Integration', 'smo-social'),
                'description' => __('Monitor memory usage of connection pools.', 'smo-social'),
                'default' => true
            ]
        ];
    }

    /**
     * Get performance admin fields
     *
     * @return array Admin fields for performance section
     */
    private function get_performance_admin_fields() {
        return [
            'enable_real_time_monitoring' => [
                'type' => 'checkbox',
                'label' => __('Real-time Monitoring', 'smo-social'),
                'description' => __('Enable real-time memory monitoring with shorter intervals.', 'smo-social'),
                'default' => true
            ],
            'enable_historical_analysis' => [
                'type' => 'checkbox',
                'label' => __('Historical Analysis', 'smo-social'),
                'description' => __('Enable analysis of memory usage trends over time.', 'smo-social'),
                'default' => true
            ],
            'enable_memory_leak_detection' => [
                'type' => 'checkbox',
                'label' => __('Memory Leak Detection', 'smo-social'),
                'description' => __('Enable detection of potential memory leaks.', 'smo-social'),
                'default' => true
            ],
            'enable_efficiency_scoring' => [
                'type' => 'checkbox',
                'label' => __('Efficiency Scoring', 'smo-social'),
                'description' => __('Calculate and display memory efficiency scores.', 'smo-social'),
                'default' => true
            ]
        ];
    }

    /**
     * Get display admin fields
     *
     * @return array Admin fields for display section
     */
    private function get_display_admin_fields() {
        return [
            'show_admin_footer_stats' => [
                'type' => 'checkbox',
                'label' => __('Admin Footer Stats', 'smo-social'),
                'description' => __('Show memory statistics in the admin footer.', 'smo-social'),
                'default' => true
            ],
            'show_dashboard_widget' => [
                'type' => 'checkbox',
                'label' => __('Dashboard Widget', 'smo-social'),
                'description' => __('Show memory monitoring widget on the dashboard.', 'smo-social'),
                'default' => true
            ],
            'show_alert_notifications' => [
                'type' => 'checkbox',
                'label' => __('Alert Notifications', 'smo-social'),
                'description' => __('Show alert notifications in the admin interface.', 'smo-social'),
                'default' => true
            ]
        ];
    }

    /**
     * Get preset admin fields
     *
     * @return array Admin fields for preset section
     */
    private function get_preset_admin_fields() {
        $presets = $this->get_configuration_presets();
        $options = [];

        foreach ($presets as $key => $preset) {
            $options[$key] = $preset['name'] . ' - ' . $preset['description'];
        }

        return [
            'apply_preset' => [
                'type' => 'select',
                'label' => __('Apply Configuration Preset', 'smo-social'),
                'description' => __('Select a preset configuration optimized for your environment.', 'smo-social'),
                'options' => $options,
                'default' => ''
            ]
        ];
    }

    /**
     * Get system compatibility status with enhanced checks
     *
     * @return array System compatibility status
     */
    public function get_system_compatibility() {
        $compatibility = [
            'is_compatible' => true,
            'issues' => [],
            'warnings' => [],
            'recommendations' => [],
            'requirements' => [
                'php_version' => [
                    'required' => '7.4',
                    'recommended' => '8.0',
                    'current' => phpversion(),
                    'status' => version_compare(phpversion(), '7.4', '>=') ? 'ok' : 'failed'
                ],
                'memory_limit' => [
                    'required' => '64M',
                    'recommended' => '128M',
                    'current' => ini_get('memory_limit'),
                    'status' => 'ok' // Will be checked below
                ],
                'wordpress_version' => [
                    'required' => '5.0',
                    'recommended' => '5.6',
                    'current' => get_bloginfo('version'),
                    'status' => version_compare(get_bloginfo('version'), '5.0', '>=') ? 'ok' : 'failed'
                ],
                'mysql_version' => [
                    'required' => '5.6',
                    'recommended' => '8.0',
                    'current' => $this->get_mysql_version(),
                    'status' => 'ok' // Will be checked below
                ],
                'opcache_enabled' => [
                    'required' => false,
                    'recommended' => true,
                    'current' => extension_loaded('Zend OPcache'),
                    'status' => extension_loaded('Zend OPcache') ? 'ok' : 'warning'
                ],
                'max_execution_time' => [
                    'required' => '30',
                    'recommended' => '60',
                    'current' => ini_get('max_execution_time'),
                    'status' => 'ok' // Will be checked below
                ]
            ]
        ];

        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(\w)$/', $memory_limit, $matches)) {
            $limit_value = (int)$matches[1];
            $limit_unit = strtolower($matches[2]);

            switch ($limit_unit) {
                case 'g':
                    $limit_value *= 1024;
                case 'm':
                    $limit_value *= 1024;
                case 'k':
                    $limit_value *= 1024;
                    break;
            }

            if ($limit_value < 67108864) { // 64MB
                $compatibility['requirements']['memory_limit']['status'] = 'failed';
            } elseif ($limit_value < 134217728) { // 128MB
                $compatibility['requirements']['memory_limit']['status'] = 'warning';
            }
        }

        // Check MySQL version
        $mysql_version = $this->get_mysql_version();
        if (version_compare($mysql_version, '5.6', '<')) {
            $compatibility['requirements']['mysql_version']['status'] = 'failed';
        } elseif (version_compare($mysql_version, '8.0', '<')) {
            $compatibility['requirements']['mysql_version']['status'] = 'warning';
        }

        // Check max execution time
        $max_exec = ini_get('max_execution_time');
        if ($max_exec > 0 && $max_exec < 30) {
            $compatibility['requirements']['max_execution_time']['status'] = 'failed';
        } elseif ($max_exec > 0 && $max_exec < 60) {
            $compatibility['requirements']['max_execution_time']['status'] = 'warning';
        }

        // Check for issues and generate recommendations
        foreach ($compatibility['requirements'] as $req => $status) {
            if ($status['status'] === 'failed') {
                $compatibility['is_compatible'] = false;
                $compatibility['issues'][] = sprintf(
                    __('Requirement %s not met: requires %s, has %s', 'smo-social'),
                    $req,
                    $status['required'],
                    $status['current']
                );
            } elseif ($status['status'] === 'warning') {
                $compatibility['warnings'][] = sprintf(
                    __('Requirement %s below recommended: recommended %s, has %s', 'smo-social'),
                    $req,
                    $status['recommended'],
                    $status['current']
                );
            }
        }

        // Generate recommendations
        if (!$compatibility['is_compatible']) {
            $compatibility['recommendations'][] = __('Please update your system requirements to meet minimum specifications.', 'smo-social');
        }

        if (count($compatibility['warnings']) > 0) {
            $compatibility['recommendations'][] = __('Consider upgrading to recommended specifications for optimal performance.', 'smo-social');
        }

        // Memory monitoring specific recommendations
        $memory_limit_mb = $this->parse_memory_limit($memory_limit);
        if ($memory_limit_mb < 128) {
            $compatibility['recommendations'][] = __('For better memory monitoring accuracy, consider increasing PHP memory limit to at least 128MB.', 'smo-social');
        }

        return $compatibility;
    }

    /**
     * Get MySQL version
     *
     * @return string MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        try {
            return $wpdb->get_var("SELECT VERSION() as version");
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Parse memory limit to MB
     *
     * @param string $memory_limit Memory limit string
     * @return int Memory limit in MB
     */
    private function parse_memory_limit($memory_limit) {
        if (preg_match('/^(\d+)(\w)$/', $memory_limit, $matches)) {
            $limit_value = (int)$matches[1];
            $limit_unit = strtolower($matches[2]);

            switch ($limit_unit) {
                case 'g':
                    return $limit_value * 1024;
                case 'm':
                    return $limit_value;
                case 'k':
                    return $limit_value / 1024;
                default:
                    return $limit_value / (1024 * 1024); // Assume bytes
            }
        }
        return 0;
    }

    /**
     * Get configuration migration support
     *
     * @return array Migration information
     */
    public function get_migration_info() {
        $current_version = '1.1.0'; // Current config version
        $saved_version = get_option('smo_memory_monitor_config_version', '1.0.0');

        return [
            'current_version' => $current_version,
            'saved_version' => $saved_version,
            'needs_migration' => version_compare($saved_version, $current_version, '<'),
            'available_migrations' => $this->get_available_migrations($saved_version, $current_version)
        ];
    }

    /**
     * Run configuration migration
     *
     * @return bool True if migration was successful
     */
    public function run_migration() {
        $migration_info = $this->get_migration_info();

        if (!$migration_info['needs_migration']) {
            return true;
        }

        try {
            foreach ($migration_info['available_migrations'] as $migration) {
                $result = $this->{$migration['method']}();
                if (!$result) {
                    throw new \Exception("Migration {$migration['version']} failed");
                }
            }

            // Update version
            update_option('smo_memory_monitor_config_version', $migration_info['current_version']);

            // Log successful migration
            if (class_exists('\SMO_Social\Core\Logger')) {
                Logger::info('Memory monitor configuration migrated successfully', [
                    'from_version' => $migration_info['saved_version'],
                    'to_version' => $migration_info['current_version']
                ]);
            }

            return true;

        } catch (\Exception $e) {
            if (class_exists('\SMO_Social\Core\Logger')) {
                Logger::error('Memory monitor configuration migration failed', [
                    'error' => $e->getMessage(),
                    'from_version' => $migration_info['saved_version'],
                    'to_version' => $migration_info['current_version']
                ]);
            }
            return false;
        }
    }

    /**
     * Get available migrations between versions
     *
     * @param string $from_version From version
     * @param string $to_version To version
     * @return array Available migrations
     */
    private function get_available_migrations($from_version, $to_version) {
        $migrations = [
            '1.0.0_to_1.1.0' => [
                'version' => '1.1.0',
                'method' => 'migrate_1_0_0_to_1_1_0',
                'description' => 'Add new configuration options and presets support'
            ]
        ];

        $available = [];
        foreach ($migrations as $migration_key => $migration) {
            if (version_compare($from_version, $migration['version'], '<') &&
                version_compare($migration['version'], $to_version, '<=')) {
                $available[] = $migration;
            }
        }

        return $available;
    }

    /**
     * Migration from version 1.0.0 to 1.1.0
     *
     * @return bool True if migration successful
     */
    private function migrate_1_0_0_to_1_1_0() {
        // Add new default values for new configuration options
        $new_defaults = [
            'enable_real_time_monitoring' => true,
            'enable_historical_analysis' => true,
            'enable_memory_leak_detection' => true,
            'enable_efficiency_scoring' => true
        ];

        // Merge with existing custom config
        $this->custom_config = array_merge($new_defaults, $this->custom_config);
        $this->merge_configurations();

        return $this->save_config();
    }

    /**
     * Create configuration backup
     *
     * @param string $backup_name Optional backup name
     * @return array Backup information
     */
    public function create_backup($backup_name = null) {
        if ($backup_name === null) {
            $backup_name = 'backup_' . date('Y-m-d_H-i-s');
        }

        $backup_data = [
            'name' => $backup_name,
            'timestamp' => time(),
            'version' => '1.1.0',
            'configuration' => $this->effective_config,
            'system_info' => [
                'php_version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'wordpress_version' => get_bloginfo('version'),
                'mysql_version' => $this->get_mysql_version()
            ]
        ];

        $backups = get_option('smo_memory_monitor_config_backups', []);
        $backups[$backup_name] = $backup_data;

        // Keep only last 10 backups
        if (count($backups) > 10) {
            array_shift($backups);
        }

        $result = update_option('smo_memory_monitor_config_backups', $backups);

        if ($result && class_exists('\SMO_Social\Core\Logger')) {
            Logger::info('Memory monitor configuration backup created', [
                'backup_name' => $backup_name,
                'config_size' => count($this->effective_config)
            ]);
        }

        return [
            'success' => $result,
            'backup_name' => $backup_name,
            'backup_data' => $backup_data
        ];
    }

    /**
     * Restore configuration from backup
     *
     * @param string $backup_name Backup name to restore
     * @return bool True if restore was successful
     */
    public function restore_backup($backup_name) {
        $backups = get_option('smo_memory_monitor_config_backups', []);

        if (!isset($backups[$backup_name])) {
            return false;
        }

        $backup_data = $backups[$backup_name];

        // Validate backup data
        if (!isset($backup_data['configuration']) || !is_array($backup_data['configuration'])) {
            return false;
        }

        // Create backup of current config before restore
        $this->create_backup('pre_restore_' . date('Y-m-d_H-i-s'));

        // Restore configuration
        $this->custom_config = $backup_data['configuration'];
        $this->merge_configurations();

        $result = $this->save_config();

        if ($result && class_exists('\SMO_Social\Core\Logger')) {
            Logger::info('Memory monitor configuration restored from backup', [
                'backup_name' => $backup_name,
                'backup_version' => $backup_data['version'] ?? 'unknown',
                'restored_at' => current_time('mysql')
            ]);
        }

        return $result;
    }

    /**
     * Get available backups
     *
     * @return array List of available backups
     */
    public function get_available_backups() {
        $backups = get_option('smo_memory_monitor_config_backups', []);

        $backup_list = [];
        foreach ($backups as $name => $data) {
            $backup_list[$name] = [
                'name' => $name,
                'timestamp' => $data['timestamp'],
                'formatted_date' => date('Y-m-d H:i:s', $data['timestamp']),
                'version' => $data['version'] ?? 'unknown',
                'config_count' => isset($data['configuration']) ? count($data['configuration']) : 0
            ];
        }

        // Sort by timestamp descending
        uasort($backup_list, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backup_list;
    }

    /**
     * Delete configuration backup
     *
     * @param string $backup_name Backup name to delete
     * @return bool True if deletion was successful
     */
    public function delete_backup($backup_name) {
        $backups = get_option('smo_memory_monitor_config_backups', []);

        if (!isset($backups[$backup_name])) {
            return false;
        }

        unset($backups[$backup_name]);
        $result = update_option('smo_memory_monitor_config_backups', $backups);

        if ($result && class_exists('\SMO_Social\Core\Logger')) {
            Logger::info('Memory monitor configuration backup deleted', [
                'backup_name' => $backup_name
            ]);
        }

        return $result;
    }

    /**
     * Get configuration change history
     *
     * @return array Configuration change history
     */
    public function get_change_history() {
        $history = get_option('smo_memory_monitor_config_history', []);

        // Ensure it's an array
        if (!is_array($history)) {
            $history = [];
        }

        // Sort by timestamp descending
        uasort($history, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return array_slice($history, 0, 50); // Return last 50 changes
    }

    /**
     * Log configuration change
     *
     * @param string $action Action performed
     * @param array $old_config Old configuration
     * @param array $new_config New configuration
     * @param string $user_id User ID (optional)
     */
    private function log_config_change($action, $old_config = [], $new_config = [], $user_id = null) {
        if (!function_exists('get_current_user_id')) {
            $user_id = $user_id ?: 'system';
        } else {
            $user_id = $user_id ?: get_current_user_id();
        }

        $change_entry = [
            'timestamp' => time(),
            'action' => $action,
            'user_id' => $user_id,
            'old_config' => $old_config,
            'new_config' => $new_config,
            'changes' => array_diff_assoc($new_config, $old_config)
        ];

        $history = get_option('smo_memory_monitor_config_history', []);
        if (!is_array($history)) {
            $history = [];
        }

        $history[] = $change_entry;

        // Keep only last 100 entries
        if (count($history) > 100) {
            array_shift($history);
        }

        update_option('smo_memory_monitor_config_history', $history);

        // Log to system log if available
        if (class_exists('\SMO_Social\Core\Logger')) {
            Logger::info('Memory monitor configuration changed', [
                'action' => $action,
                'user_id' => $user_id,
                'change_count' => count($change_entry['changes'])
            ]);
        }
    }


    /**
     * Get performance optimization recommendations
     *
     * @return array Performance recommendations
     */
    public function get_performance_recommendations() {
        $recommendations = [
            'critical' => [],
            'warning' => [],
            'info' => []
        ];

        $system_info = $this->get_system_compatibility();
        $current_config = $this->effective_config;

        // Memory limit recommendations
        $memory_limit_mb = $this->parse_memory_limit(ini_get('memory_limit'));
        if ($memory_limit_mb < 128) {
            $recommendations['critical'][] = [
                'message' => __('Increase PHP memory limit to at least 128MB for better performance', 'smo-social'),
                'action' => 'php_ini',
                'parameter' => 'memory_limit',
                'recommended_value' => '128M'
            ];
        }

        // Monitoring interval optimization
        if ($current_config['monitoring_interval'] < 10 && !$current_config['enable_real_time_monitoring']) {
            $recommendations['warning'][] = [
                'message' => __('Consider increasing monitoring interval for better performance', 'smo-social'),
                'action' => 'config',
                'parameter' => 'monitoring_interval',
                'recommended_value' => 30
            ];
        }

        // Real-time monitoring recommendations
        if ($current_config['enable_real_time_monitoring'] && $memory_limit_mb < 256) {
            $recommendations['warning'][] = [
                'message' => __('Real-time monitoring may impact performance with low memory limit', 'smo-social'),
                'action' => 'config',
                'parameter' => 'enable_real_time_monitoring',
                'recommended_value' => false
            ];
        }

        // Database cleanup optimization
        if ($current_config['database_cleanup_interval'] < 3600) {
            $recommendations['info'][] = [
                'message' => __('Consider increasing database cleanup interval to reduce database load', 'smo-social'),
                'action' => 'config',
                'parameter' => 'database_cleanup_interval',
                'recommended_value' => 3600
            ];
        }

        // Alert system optimization
        if ($current_config['alert_system_enabled'] && empty($current_config['notification_channels'])) {
            $recommendations['warning'][] = [
                'message' => __('Enable notification channels for effective alert system', 'smo-social'),
                'action' => 'config',
                'parameter' => 'notification_channels',
                'recommended_value' => ['admin_dashboard', 'log']
            ];
        }

        // Integration recommendations
        $enabled_integrations = 0;
        foreach (['integrate_with_object_pools', 'integrate_with_cache_systems', 'integrate_with_connection_pools'] as $integration) {
            if ($current_config[$integration]) {
                $enabled_integrations++;
            }
        }

        if ($enabled_integrations > 2 && $memory_limit_mb < 256) {
            $recommendations['info'][] = [
                'message' => __('Multiple integrations enabled - monitor memory usage closely', 'smo-social'),
                'action' => 'monitor',
                'parameter' => 'memory_usage',
                'recommended_value' => 'regular_check'
            ];
        }

        return $recommendations;
    }

    /**
     * Get configuration health status
     *
     * @return array Health status information
     */
    public function get_health_status() {
        $status = [
            'overall' => 'healthy',
            'issues' => [],
            'warnings' => [],
            'last_check' => time()
        ];

        $validation = $this->validate_current_config();
        $compatibility = $this->get_system_compatibility();
        $recommendations = $this->get_performance_recommendations();

        // Check validation status
        if (!$validation['is_valid']) {
            $status['overall'] = 'critical';
            $status['issues'] = array_merge($status['issues'], $validation['issues']);
        }

        // Check compatibility
        if (!$compatibility['is_compatible']) {
            $status['overall'] = 'critical';
            $status['issues'] = array_merge($status['issues'], $compatibility['issues']);
        }

        // Check for warnings
        $all_warnings = array_merge(
            $validation['warnings'],
            $compatibility['warnings'],
            array_column($recommendations['warning'], 'message'),
            array_column($recommendations['critical'], 'message')
        );

        if (!empty($all_warnings)) {
            if ($status['overall'] === 'healthy') {
                $status['overall'] = 'warning';
            }
            $status['warnings'] = $all_warnings;
        }

        // Check last backup
        $backups = $this->get_available_backups();
        if (empty($backups)) {
            $status['warnings'][] = __('No configuration backups found', 'smo-social');
        } else {
            $latest_backup = reset($backups);
            $days_since_backup = (time() - $latest_backup['timestamp']) / (60 * 60 * 24);
            if ($days_since_backup > 7) {
                $status['warnings'][] = __('Configuration backup is older than 7 days', 'smo-social');
            }
        }

        return $status;
    }
}