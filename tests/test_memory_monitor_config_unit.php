<?php
/**
 * Unit Tests for MemoryMonitorConfig Class
 *
 * Comprehensive unit testing for the MemoryMonitorConfig class
 *
 * @package SMO_Social
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once __DIR__ . '/../includes/wordpress-test-stubs.php';
require_once __DIR__ . '/../includes/wordpress-functions.php';
require_once __DIR__ . '/../includes/Core/MemoryMonitorConfig.php';

// Mock WordPress functions for testing
class MockWordPressFunctions {
    private static $options = [];

    public static function get_option($key, $default = false) {
        return self::$options[$key] ?? $default;
    }

    public static function update_option($key, $value, $autoload = null) {
        self::$options[$key] = $value;
        return true;
    }

    public static function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function get_bloginfo($key) {
        $info = [
            'version' => '5.8.0',
            'name' => 'Test Site'
        ];
        return $info[$key] ?? '';
    }

    public static function clear_options() {
        self::$options = [];
    }
}

class MemoryMonitorConfigUnitTest {
    private $config;
    private $original_functions;

    public function __construct() {
        // Backup original functions
        $this->original_functions = [
            'get_option' => function_exists('get_option') ? 'get_option' : null,
            'update_option' => function_exists('update_option') ? 'update_option' : null,
            'is_email' => function_exists('is_email') ? 'is_email' : null,
            'get_bloginfo' => function_exists('get_bloginfo') ? 'get_bloginfo' : null
        ];

        // Override with mocks
        if (!function_exists('get_option')) {
            function get_option($key, $default = false) {
                return MockWordPressFunctions::get_option($key, $default);
            }
        }
        if (!function_exists('update_option')) {
            function update_option($key, $value, $autoload = null) {
                return MockWordPressFunctions::update_option($key, $value, $autoload);
            }
        }
        if (!function_exists('is_email')) {
            function is_email($email) {
                return MockWordPressFunctions::is_email($email);
            }
        }
        if (!function_exists('get_bloginfo')) {
            function get_bloginfo($key) {
                return MockWordPressFunctions::get_bloginfo($key);
            }
        }
    }

    public function __destruct() {
        // Restore original functions if they existed
        MockWordPressFunctions::clear_options();
    }

    public function run_all_tests() {
        echo "Starting MemoryMonitorConfig Unit Tests...\n";
        echo "===========================================\n\n";

        $tests = [
            'test_singleton_pattern',
            'test_default_configuration',
            'test_configuration_loading',
            'test_configuration_sections',
            'test_configuration_validation',
            'test_configuration_updates',
            'test_preset_configurations',
            'test_backup_restore',
            'test_migration_support',
            'test_system_compatibility',
            'test_performance_recommendations',
            'test_health_status',
            'test_error_handling'
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $test) {
            try {
                MockWordPressFunctions::clear_options();
                echo "Running $test...\n";
                $this->$test();
                echo "✓ $test PASSED\n\n";
                $passed++;
            } catch (Exception $e) {
                echo "✗ $test FAILED: " . $e->getMessage() . "\n\n";
            }
        }

        echo "Test Results: $passed/$total tests passed\n";

        if ($passed === $total) {
            echo "🎉 All MemoryMonitorConfig unit tests PASSED!\n";
        } else {
            echo "⚠️  Some tests failed. Please review the implementation.\n";
        }
    }

    private function test_singleton_pattern() {
        $instance1 = \SMO_Social\Core\MemoryMonitorConfig::get_instance();
        $instance2 = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        if ($instance1 !== $instance2) {
            throw new Exception('Singleton pattern not working - different instances returned');
        }

        if (!($instance1 instanceof \SMO_Social\Core\MemoryMonitorConfig)) {
            throw new Exception('Instance is not of correct type');
        }
    }

    private function test_default_configuration() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        $full_config = $config->get_config();

        if (!is_array($full_config)) {
            throw new Exception('Default configuration not returned as array');
        }

        // Test required default keys exist
        $required_keys = [
            'monitoring_enabled',
            'monitoring_interval',
            'warning_threshold',
            'critical_threshold',
            'max_history_entries',
            'alert_system_enabled',
            'max_active_alerts',
            'auto_resolve_hours',
            'notification_channels',
            'email_notifications',
            'email_recipients',
            'database_cleanup_interval',
            'database_max_records',
            'integrate_with_object_pools',
            'integrate_with_cache_systems',
            'integrate_with_connection_pools',
            'enable_real_time_monitoring',
            'enable_historical_analysis',
            'enable_memory_leak_detection',
            'enable_efficiency_scoring',
            'show_admin_footer_stats',
            'show_dashboard_widget',
            'show_alert_notifications'
        ];

        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $full_config)) {
                throw new Exception("Default configuration missing key: $key");
            }
        }
    }

    private function test_configuration_loading() {
        // Test monitoring config section
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();
        $monitoring_config = $config->get_monitoring_config();

        $required_keys = ['monitoring_enabled', 'monitoring_interval', 'warning_threshold', 'critical_threshold', 'max_history_entries'];
        foreach ($required_keys as $key) {
            if (!isset($monitoring_config[$key])) {
                throw new Exception("Monitoring config missing key: $key");
            }
        }

        // Test alert config section
        $alert_config = $config->get_alert_config();
        $required_keys = ['alert_system_enabled', 'max_active_alerts', 'max_history_entries', 'auto_resolve_hours', 'notification_channels'];
        foreach ($required_keys as $key) {
            if (!isset($alert_config[$key])) {
                throw new Exception("Alert config missing key: $key");
            }
        }

        // Test integration config section
        $integration_config = $config->get_integration_config();
        $required_keys = ['integrate_with_object_pools', 'integrate_with_cache_systems', 'integrate_with_connection_pools'];
        foreach ($required_keys as $key) {
            if (!isset($integration_config[$key])) {
                throw new Exception("Integration config missing key: $key");
            }
        }
    }

    private function test_configuration_sections() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        // Test performance config
        $performance_config = $config->get_performance_config();
        $required_keys = ['enable_real_time_monitoring', 'enable_historical_analysis', 'enable_memory_leak_detection', 'enable_efficiency_scoring'];
        foreach ($required_keys as $key) {
            if (!isset($performance_config[$key])) {
                throw new Exception("Performance config missing key: $key");
            }
        }

        // Test display config
        $display_config = $config->get_display_config();
        $required_keys = ['show_admin_footer_stats', 'show_dashboard_widget', 'show_alert_notifications'];
        foreach ($required_keys as $key) {
            if (!isset($display_config[$key])) {
                throw new Exception("Display config missing key: $key");
            }
        }
    }

    private function test_configuration_validation() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        // Test valid configuration
        $valid_config = [
            'monitoring_enabled' => true,
            'monitoring_interval' => 30,
            'warning_threshold' => 75,
            'critical_threshold' => 90,
            'max_history_entries' => 200
        ];

        $result = $this->invoke_private_method($config, 'validate_config', [$valid_config]);
        if ($result === false) {
            throw new Exception('Valid configuration was rejected');
        }

        // Test invalid monitoring interval
        $invalid_config = ['monitoring_interval' => 100]; // Too high
        $result = $this->invoke_private_method($config, 'validate_config', [$invalid_config]);
        if ($result !== false && isset($result['monitoring_interval']) && $result['monitoring_interval'] !== 60) {
            throw new Exception('Invalid monitoring interval was not clamped');
        }

        // Test invalid thresholds
        $invalid_thresholds = ['warning_threshold' => 95]; // Too high
        $result = $this->invoke_private_method($config, 'validate_config', [$invalid_thresholds]);
        if ($result !== false && isset($result['warning_threshold']) && $result['warning_threshold'] !== 90) {
            throw new Exception('Invalid warning threshold was not clamped');
        }
    }

    private function test_configuration_updates() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        $original_config = $config->get_config();

        // Test monitoring config update
        $monitoring_update = [
            'monitoring_enabled' => false,
            'monitoring_interval' => 45,
            'warning_threshold' => 80
        ];

        $result = $config->update_monitoring_config($monitoring_update);
        if (!$result) {
            throw new Exception('Monitoring config update failed');
        }

        // Verify update
        $updated_monitoring = $config->get_monitoring_config();
        if ($updated_monitoring['monitoring_enabled'] !== false) {
            throw new Exception('Monitoring enabled not updated');
        }
        if ($updated_monitoring['monitoring_interval'] !== 45) {
            throw new Exception('Monitoring interval not updated');
        }

        // Test alert config update
        $alert_update = [
            'alert_system_enabled' => false,
            'max_active_alerts' => 75,
            'auto_resolve_hours' => 36
        ];

        $result = $config->update_alert_config($alert_update);
        if (!$result) {
            throw new Exception('Alert config update failed');
        }

        // Verify update
        $updated_alert = $config->get_alert_config();
        if ($updated_alert['alert_system_enabled'] !== false) {
            throw new Exception('Alert system enabled not updated');
        }
    }

    private function test_preset_configurations() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        $presets = $config->get_configuration_presets();

        if (!is_array($presets)) {
            throw new Exception('Configuration presets not returned as array');
        }

        $expected_presets = ['development', 'production', 'high_traffic', 'minimal'];
        foreach ($expected_presets as $preset_name) {
            if (!isset($presets[$preset_name])) {
                throw new Exception("Preset '$preset_name' not found");
            }

            $preset = $presets[$preset_name];
            if (!isset($preset['name']) || !isset($preset['description']) || !isset($preset['config'])) {
                throw new Exception("Preset '$preset_name' missing required fields");
            }
        }

        // Test applying a preset
        $result = $config->apply_preset('development');
        if (!$result) {
            throw new Exception('Failed to apply development preset');
        }

        // Verify preset was applied
        $current_config = $config->get_config();
        if ($current_config['monitoring_interval'] !== 5) { // Development preset value
            throw new Exception('Development preset not applied correctly');
        }
    }

    private function test_backup_restore() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        // Create backup
        $backup_result = $config->create_backup('test_backup');
        if (!$backup_result['success']) {
            throw new Exception('Failed to create configuration backup');
        }

        // Modify configuration
        $config->update_config(['monitoring_interval' => 99]);

        // Restore backup
        $restore_result = $config->restore_backup('test_backup');
        if (!$restore_result) {
            throw new Exception('Failed to restore configuration backup');
        }

        // Verify restoration
        $current_config = $config->get_config();
        if ($current_config['monitoring_interval'] === 99) {
            throw new Exception('Configuration not restored from backup');
        }
    }

    private function test_migration_support() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        $migration_info = $config->get_migration_info();

        if (!is_array($migration_info)) {
            throw new Exception('Migration info not returned as array');
        }

        $required_keys = ['current_version', 'saved_version', 'needs_migration', 'available_migrations'];
        foreach ($required_keys as $key) {
            if (!isset($migration_info[$key])) {
                throw new Exception("Migration info missing key: $key");
            }
        }
    }

    private function test_system_compatibility() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        $compatibility = $config->get_system_compatibility();

        if (!is_array($compatibility)) {
            throw new Exception('System compatibility not returned as array');
        }

        $required_keys = ['is_compatible', 'issues', 'warnings', 'recommendations', 'requirements'];
        foreach ($required_keys as $key) {
            if (!isset($compatibility[$key])) {
                throw new Exception("Compatibility check missing key: $key");
            }
        }

        // Test requirements structure
        $requirements = $compatibility['requirements'];
        $expected_reqs = ['php_version', 'memory_limit', 'wordpress_version', 'mysql_version', 'max_execution_time'];
        foreach ($expected_reqs as $req) {
            if (!isset($requirements[$req])) {
                throw new Exception("Requirements missing: $req");
            }
        }
    }

    private function test_performance_recommendations() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        $recommendations = $config->get_performance_recommendations();

        if (!is_array($recommendations)) {
            throw new Exception('Performance recommendations not returned as array');
        }

        $expected_levels = ['critical', 'warning', 'info'];
        foreach ($expected_levels as $level) {
            if (!isset($recommendations[$level])) {
                throw new Exception("Performance recommendations missing level: $level");
            }
        }
    }

    private function test_health_status() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        $health = $config->get_health_status();

        if (!is_array($health)) {
            throw new Exception('Health status not returned as array');
        }

        $required_keys = ['overall', 'issues', 'warnings', 'last_check'];
        foreach ($required_keys as $key) {
            if (!isset($health[$key])) {
                throw new Exception("Health status missing key: $key");
            }
        }

        // Test validation
        $validation = $config->validate_current_config();
        if (!is_array($validation)) {
            throw new Exception('Configuration validation failed');
        }

        if (!isset($validation['is_valid'])) {
            throw new Exception('Validation result missing is_valid flag');
        }
    }

    private function test_error_handling() {
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();

        // Test invalid preset application
        $result = $config->apply_preset('nonexistent_preset');
        if ($result) {
            throw new Exception('Applying nonexistent preset should fail');
        }

        // Test restoring nonexistent backup
        $result = $config->restore_backup('nonexistent_backup');
        if ($result) {
            throw new Exception('Restoring nonexistent backup should fail');
        }

        // Test deleting nonexistent backup
        $result = $config->delete_backup('nonexistent_backup');
        if ($result) {
            throw new Exception('Deleting nonexistent backup should fail');
        }

        // Test empty configuration update
        $result = $config->update_config([]);
        if ($result) {
            throw new Exception('Empty configuration update should fail');
        }

        // Test invalid configuration values
        $invalid_config = ['monitoring_interval' => 'invalid_string'];
        $result = $config->update_config($invalid_config);
        if ($result) {
            throw new Exception('Invalid configuration should be rejected');
        }
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invoke_private_method($object, $method_name, $args = []) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}

// Run the tests
if (php_sapi_name() === 'cli') {
    $test = new MemoryMonitorConfigUnitTest();
    $test->run_all_tests();
} else {
    echo "This test should be run from the command line.";
}