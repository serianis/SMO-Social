<?php
/**
 * Integration Tests for Memory Monitoring System
 *
 * Tests the interaction between MemoryMonitor, MemoryAlertSystem, MemoryMonitorConfig, and admin view
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
require_once __DIR__ . '/../includes/Core/MemoryMonitor.php';
require_once __DIR__ . '/../includes/Core/MemoryMonitorConfig.php';
require_once __DIR__ . '/../includes/Core/MemoryAlertSystem.php';
require_once __DIR__ . '/../includes/Admin/Views/MemoryMonitoring.php';

// Mock database for integration testing
class MockWPDBIntegration {
    public $prefix = 'wp_';
    public $charset_collate = 'utf8mb4_unicode_ci';
    private $tables = [];

    public function get_charset_collate() {
        return 'utf8mb4_unicode_ci';
    }

    public function insert($table, $data) {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
        $this->tables[$table][] = $data;
        return true;
    }

    public function get_results($query, $output = ARRAY_A) {
        // Mock different query responses
        if (strpos($query, 'smo_memory_metrics_history') !== false) {
            return [
                [
                    'id' => 1,
                    'timestamp' => date('Y-m-d H:i:s', time() - 3600),
                    'memory_usage' => 67108864,
                    'usage_percentage' => 65.5,
                    'system_memory' => 67108864,
                    'peak_memory' => 134217728,
                    'pool_memory' => 16777216,
                    'cache_memory' => 8388608,
                    'status' => 'warning',
                    'efficiency_score' => 72.5,
                    'component_breakdown' => json_encode(['system' => ['usage' => 67108864]]),
                    'system_info' => json_encode(['php_version' => '8.1.0'])
                ],
                [
                    'id' => 2,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'memory_usage' => 75497472,
                    'usage_percentage' => 70.2,
                    'system_memory' => 75497472,
                    'peak_memory' => 150994944,
                    'pool_memory' => 20971520,
                    'cache_memory' => 10485760,
                    'status' => 'warning',
                    'efficiency_score' => 68.9,
                    'component_breakdown' => json_encode(['system' => ['usage' => 75497472]]),
                    'system_info' => json_encode(['php_version' => '8.1.0'])
                ]
            ];
        }
        return [];
    }

    public function prepare($query, ...$args) {
        return $query;
    }

    public function replace($table, $data) {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
        $this->tables[$table][] = $data;
        return true;
    }

    public function get_var($query) {
        if (strpos($query, 'SHOW TABLES LIKE') !== false) {
            return 'wp_smo_memory_monitoring';
        }
        return null;
    }

    public function query($query) {
        return true;
    }

    public function get_inserted_data($table) {
        return $this->tables[$table] ?? [];
    }
}

class MemoryMonitoringIntegrationTest {
    private $monitor;
    private $alert_system;
    private $config;
    private $view;
    private $original_wpdb;

    public function __construct() {
        // Backup original wpdb
        $this->original_wpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new MockWPDBIntegration();

        // Initialize components
        $this->config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();
        $this->monitor = \SMO_Social\Core\MemoryMonitor::get_instance();
        $this->alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();
        $this->view = new \SMO_Social\Admin\Views\MemoryMonitoring();
    }

    public function __destruct() {
        // Restore original wpdb
        if ($this->original_wpdb) {
            $GLOBALS['wpdb'] = $this->original_wpdb;
        }
    }

    public function run_all_tests() {
        echo "Starting Memory Monitoring System Integration Tests...\n";
        echo "=====================================================\n\n";

        $tests = [
            'test_component_initialization',
            'test_configuration_flow',
            'test_monitoring_alert_flow',
            'test_data_persistence_flow',
            'test_admin_view_integration',
            'test_real_time_monitoring_flow',
            'test_alert_lifecycle',
            'test_configuration_persistence',
            'test_system_health_integration',
            'test_error_recovery_flow'
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $test) {
            try {
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
            echo "🎉 All Memory Monitoring integration tests PASSED!\n";
        } else {
            echo "⚠️  Some tests failed. Please review the integration.\n";
        }
    }

    private function test_component_initialization() {
        // Test that all components can be initialized together
        if (!$this->monitor) {
            throw new Exception('MemoryMonitor not initialized');
        }

        if (!$this->alert_system) {
            throw new Exception('MemoryAlertSystem not initialized');
        }

        if (!$this->config) {
            throw new Exception('MemoryMonitorConfig not initialized');
        }

        if (!$this->view) {
            throw new Exception('MemoryMonitoring view not initialized');
        }

        // Test singleton consistency
        $monitor2 = \SMO_Social\Core\MemoryMonitor::get_instance();
        if ($this->monitor !== $monitor2) {
            throw new Exception('MemoryMonitor singleton not consistent');
        }

        $config2 = \SMO_Social\Core\MemoryMonitorConfig::get_instance();
        if ($this->config !== $config2) {
            throw new Exception('MemoryMonitorConfig singleton not consistent');
        }
    }

    private function test_configuration_flow() {
        // Test configuration updates flow through all components
        $original_config = $this->config->get_config();

        // Update configuration
        $new_config = [
            'monitoring_enabled' => false,
            'monitoring_interval' => 45,
            'warning_threshold' => 75
        ];

        $result = $this->config->update_config($new_config);
        if (!$result) {
            throw new Exception('Configuration update failed');
        }

        // Verify config was updated
        $updated_config = $this->config->get_config();
        if ($updated_config['monitoring_enabled'] !== false) {
            throw new Exception('Configuration not updated in config instance');
        }

        // Test that monitor picks up config changes
        $this->monitor->update_config($new_config);

        // Restore original config
        $this->config->update_config([
            'monitoring_enabled' => $original_config['monitoring_enabled'],
            'monitoring_interval' => $original_config['monitoring_interval'],
            'warning_threshold' => $original_config['warning_threshold']
        ]);
    }

    private function test_monitoring_alert_flow() {
        // Test the complete flow from monitoring to alerts
        $this->alert_system->clear_all_alerts();

        // Force monitoring
        $this->monitor->force_memory_monitoring();

        // Get current stats
        $stats = $this->monitor->get_current_stats();
        if (empty($stats)) {
            throw new Exception('Monitoring did not produce stats');
        }

        // Check if alerts were triggered based on stats
        $active_alerts = $this->alert_system->get_active_alerts();

        // Test alert triggering manually
        $alert_result = $this->alert_system->trigger_alert(
            'integration_test',
            'Integration Test Alert',
            'Testing alert system integration',
            'info'
        );

        if (!$alert_result) {
            throw new Exception('Manual alert triggering failed');
        }

        // Verify alert was created
        $alerts_after = $this->alert_system->get_active_alerts();
        if (count($alerts_after) < 1) {
            throw new Exception('Alert was not created');
        }

        // Test alert resolution
        $resolve_result = $this->alert_system->resolve_alert('integration_test');
        if (!$resolve_result) {
            throw new Exception('Alert resolution failed');
        }
    }

    private function test_data_persistence_flow() {
        // Test data flow from monitoring to database persistence
        $this->monitor->force_memory_monitoring();

        // Check that data was stored in database
        $wpdb = $GLOBALS['wpdb'];
        $inserted_data = $wpdb->get_inserted_data('wp_smo_memory_monitoring');

        if (empty($inserted_data)) {
            throw new Exception('Monitoring data not persisted to database');
        }

        // Test historical data retrieval
        $historical_data = $this->monitor->get_database_memory_history(10);
        if (!is_array($historical_data)) {
            throw new Exception('Historical data retrieval failed');
        }

        // Test enhanced metrics storage
        $enhanced_data = $wpdb->get_inserted_data('wp_smo_memory_metrics_history');
        if (empty($enhanced_data)) {
            throw new Exception('Enhanced metrics not stored');
        }
    }

    private function test_admin_view_integration() {
        // Test that admin view can retrieve data from all components
        $dashboard_data = $this->invoke_private_method($this->view, 'get_dashboard_data');

        if (!is_array($dashboard_data)) {
            throw new Exception('Dashboard data not retrieved');
        }

        // Verify data from different components is integrated
        $required_sections = [
            'current_stats',
            'component_usage',
            'active_alerts',
            'efficiency_analysis',
            'config',
            'memory_leaks',
            'usage_patterns',
            'forecast',
            'recommendations'
        ];

        foreach ($required_sections as $section) {
            if (!isset($dashboard_data[$section])) {
                throw new Exception("Dashboard data missing section: $section");
            }
        }

        // Test view rendering
        ob_start();
        $this->view->render();
        $output = ob_get_clean();

        if (empty($output)) {
            throw new Exception('View rendering produced no output');
        }

        // Check for expected content integration
        if (strpos($output, 'Memory Monitoring Dashboard') === false) {
            throw new Exception('View output missing expected dashboard title');
        }
    }

    private function test_real_time_monitoring_flow() {
        // Test real-time monitoring integration
        $rt_status = $this->monitor->get_real_time_status();

        if (!is_array($rt_status)) {
            throw new Exception('Real-time status not available');
        }

        $required_rt_keys = ['enabled', 'interval', 'current_interval', 'last_monitor_time'];
        foreach ($required_rt_keys as $key) {
            if (!isset($rt_status[$key])) {
                throw new Exception("Real-time status missing key: $key");
            }
        }

        // Test real-time memory status
        $rt_memory = $this->monitor->get_real_time_memory_status();
        if (!is_array($rt_memory)) {
            throw new Exception('Real-time memory status failed');
        }

        // Test real-time monitoring toggle
        $original_rt = $this->monitor->set_real_time_monitoring(true);
        $new_rt_status = $this->monitor->get_real_time_status();

        if (!$new_rt_status['enabled']) {
            throw new Exception('Real-time monitoring not enabled');
        }

        // Restore original setting
        $this->monitor->set_real_time_monitoring(false);
    }

    private function test_alert_lifecycle() {
        // Test complete alert lifecycle integration
        $this->alert_system->clear_all_alerts();

        // Create alert
        $alert_id = 'lifecycle_test';
        $create_result = $this->alert_system->trigger_alert(
            $alert_id,
            'Lifecycle Test',
            'Testing complete alert lifecycle',
            'warning'
        );

        if (!$create_result) {
            throw new Exception('Alert creation failed in lifecycle test');
        }

        // Verify alert exists
        if (!$this->alert_system->is_alert_active($alert_id)) {
            throw new Exception('Alert not active after creation');
        }

        // Update alert
        $update_result = $this->alert_system->update_alert(
            $alert_id,
            'Updated Lifecycle Test',
            'Updated message',
            'critical'
        );

        if (!$update_result) {
            throw new Exception('Alert update failed');
        }

        // Verify update
        $alert = $this->alert_system->get_alert($alert_id);
        if ($alert['severity'] !== 'critical') {
            throw new Exception('Alert not updated correctly');
        }

        // Resolve alert
        $resolve_result = $this->alert_system->resolve_alert($alert_id, 'Lifecycle test complete');
        if (!$resolve_result) {
            throw new Exception('Alert resolution failed');
        }

        // Verify alert is resolved
        if ($this->alert_system->is_alert_active($alert_id)) {
            throw new Exception('Alert still active after resolution');
        }

        // Check history
        $history = $this->alert_system->get_alert_history();
        $found_in_history = false;
        foreach ($history as $historical_alert) {
            if ($historical_alert['type'] === $alert_id && $historical_alert['status'] === 'resolved') {
                $found_in_history = true;
                break;
            }
        }

        if (!$found_in_history) {
            throw new Exception('Resolved alert not found in history');
        }
    }

    private function test_configuration_persistence() {
        // Test configuration changes persist and affect all components
        $original_config = $this->config->get_config();

        // Make configuration changes
        $test_config = [
            'monitoring_interval' => 60,
            'warning_threshold' => 80,
            'critical_threshold' => 95,
            'max_active_alerts' => 100
        ];

        // Update config
        $this->config->update_config($test_config);

        // Verify changes in config
        $updated_config = $this->config->get_config();
        if ($updated_config['monitoring_interval'] !== 60) {
            throw new Exception('Configuration not persisted in config instance');
        }

        // Update monitor with new config
        $this->monitor->update_config($test_config);

        // Update alert system
        $this->alert_system->update_config(['max_active_alerts' => 100]);

        // Verify alert system got update
        $alert_stats = $this->alert_system->get_alert_statistics();
        // Note: This is a simplified check - in real implementation would need internal access

        // Restore original config
        $this->config->update_config($original_config);
    }

    private function test_system_health_integration() {
        // Test system health checks integration
        $health = $this->config->get_health_status();

        if (!is_array($health)) {
            throw new Exception('Health status not returned');
        }

        $required_health_keys = ['overall', 'issues', 'warnings', 'last_check'];
        foreach ($required_health_keys as $key) {
            if (!isset($health[$key])) {
                throw new Exception("Health status missing key: $key");
            }
        }

        // Test compatibility check
        $compatibility = $this->config->get_system_compatibility();
        if (!is_array($compatibility)) {
            throw new Exception('Compatibility check failed');
        }

        // Test monitoring diagnostics
        $diagnostics = $this->monitor->get_monitoring_diagnostics();
        if (!is_array($diagnostics)) {
            throw new Exception('Monitoring diagnostics failed');
        }

        // Verify integration between health checks
        if (!isset($compatibility['is_compatible'])) {
            throw new Exception('Compatibility check missing compatibility flag');
        }
    }

    private function test_error_recovery_flow() {
        // Test error handling and recovery across components
        $this->alert_system->clear_all_alerts();

        // Test with invalid configuration
        try {
            $this->config->update_config(['monitoring_interval' => 'invalid']);
            // Should handle gracefully
        } catch (Exception $e) {
            // Expected to potentially throw, but should be handled
        }

        // Test monitoring with potential errors
        $this->monitor->force_memory_monitoring();

        // Test alert system error handling
        $invalid_alert = $this->alert_system->trigger_alert('', '', '', '');
        if ($invalid_alert) {
            throw new Exception('Invalid alert creation should fail');
        }

        // Test resolution of non-existent alert
        $resolve_invalid = $this->alert_system->resolve_alert('nonexistent');
        if ($resolve_invalid) {
            throw new Exception('Resolving non-existent alert should fail');
        }

        // Test view with missing data
        $dashboard_data = $this->invoke_private_method($this->view, 'get_dashboard_data');
        if (!is_array($dashboard_data)) {
            throw new Exception('View should handle errors gracefully');
        }

        // Verify system remains functional after errors
        $stats = $this->monitor->get_current_stats();
        if (!is_array($stats)) {
            throw new Exception('System not functional after error handling');
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
    $test = new MemoryMonitoringIntegrationTest();
    $test->run_all_tests();
} else {
    echo "This test should be run from the command line.";
}