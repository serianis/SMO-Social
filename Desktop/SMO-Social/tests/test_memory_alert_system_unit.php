<?php
/**
 * Unit Tests for MemoryAlertSystem Class
 *
 * Comprehensive unit testing for the MemoryAlertSystem class
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
require_once __DIR__ . '/../includes/Core/MemoryAlertSystem.php';
require_once __DIR__ . '/../includes/Core/MemoryMonitorConfig.php';
require_once __DIR__ . '/../includes/Core/Logger.php';

// Mock classes for testing
class MockMemoryMonitorConfigForAlerts {
    private static $instance = null;
    private $config = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_alert_config() {
        return $this->config ?: [
            'alert_system_enabled' => true,
            'max_active_alerts' => 50,
            'max_history_entries' => 200,
            'auto_resolve_hours' => 24,
            'notification_channels' => ['admin_dashboard', 'log'],
            'email_notifications' => false,
            'email_recipients' => ['admin@example.com'],
            'warning_threshold' => 70,
            'critical_threshold' => 90
        ];
    }

    public function update_config($config) {
        $this->config = array_merge($this->config, $config);
        return true;
    }
}

class MockLoggerForAlerts {
    public static $logs = [];

    public static function error($message, $context = []) {
        self::$logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    public static function warning($message, $context = []) {
        self::$logs[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public static function info($message, $context = []) {
        self::$logs[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public static function debug($message, $context = []) {
        self::$logs[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }

    public static function clear_logs() {
        self::$logs = [];
    }

    public static function get_logs() {
        return self::$logs;
    }
}

class MemoryAlertSystemUnitTest {
    private $alert_system;
    private $original_config;
    private $original_logger;

    public function __construct() {
        // Backup originals
        $this->original_config = $GLOBALS['SMO_Social\Core\MemoryMonitorConfig'] ?? null;
        $this->original_logger = $GLOBALS['SMO_Social\Core\Logger'] ?? null;

        // Set up mocks
        $GLOBALS['SMO_Social\Core\MemoryMonitorConfig'] = new MockMemoryMonitorConfigForAlerts();
        $GLOBALS['SMO_Social\Core\Logger'] = new MockLoggerForAlerts();
    }

    public function __destruct() {
        // Restore originals
        if ($this->original_config) {
            $GLOBALS['SMO_Social\Core\MemoryMonitorConfig'] = $this->original_config;
        }
        if ($this->original_logger) {
            $GLOBALS['SMO_Social\Core\Logger'] = $this->original_logger;
        }
    }

    public function run_all_tests() {
        echo "Starting MemoryAlertSystem Unit Tests...\n";
        echo "=========================================\n\n";

        $tests = [
            'test_singleton_pattern',
            'test_initialization',
            'test_alert_triggering',
            'test_alert_resolution',
            'test_alert_updating',
            'test_alert_queries',
            'test_alert_statistics',
            'test_notification_system',
            'test_alert_limits',
            'test_auto_resolution',
            'test_configuration_updates',
            'test_error_handling'
        ];

        $passed = 0;
        $total = count($tests);

        foreach ($tests as $test) {
            try {
                MockLoggerForAlerts::clear_logs();
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
            echo "🎉 All MemoryAlertSystem unit tests PASSED!\n";
        } else {
            echo "⚠️  Some tests failed. Please review the implementation.\n";
        }
    }

    private function test_singleton_pattern() {
        $instance1 = \SMO_Social\Core\MemoryAlertSystem::get_instance();
        $instance2 = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        if ($instance1 !== $instance2) {
            throw new Exception('Singleton pattern not working - different instances returned');
        }

        if (!($instance1 instanceof \SMO_Social\Core\MemoryAlertSystem)) {
            throw new Exception('Instance is not of correct type');
        }
    }

    private function test_initialization() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Test that configuration is loaded
        $stats = $alert_system->get_alert_statistics();
        if (!is_array($stats)) {
            throw new Exception('Alert statistics not available');
        }

        $required_keys = ['active_alerts', 'total_history', 'alerts_by_severity', 'alerts_by_type'];
        foreach ($required_keys as $key) {
            if (!isset($stats[$key])) {
                throw new Exception("Alert statistics missing key: $key");
            }
        }
    }

    private function test_alert_triggering() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Test triggering a new alert
        $result = $alert_system->trigger_alert(
            'test_memory_warning',
            'Test Memory Warning',
            'This is a test memory warning alert',
            'warning',
            ['test_data' => 'memory_test']
        );

        if (!$result) {
            throw new Exception('Failed to trigger test alert');
        }

        // Verify alert was created
        $active_alerts = $alert_system->get_active_alerts();
        if (count($active_alerts) !== 1) {
            throw new Exception('Alert was not added to active alerts');
        }

        $alert = $active_alerts[0];
        if ($alert['type'] !== 'test_memory_warning') {
            throw new Exception('Alert type not set correctly');
        }

        if ($alert['severity'] !== 'warning') {
            throw new Exception('Alert severity not set correctly');
        }

        // Test logging
        $logs = MockLoggerForAlerts::get_logs();
        $found_log = false;
        foreach ($logs as $log) {
            if (strpos($log['message'], 'Alert triggered') !== false) {
                $found_log = true;
                break;
            }
        }
        if (!$found_log) {
            throw new Exception('Alert triggering was not logged');
        }
    }

    private function test_alert_resolution() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // First trigger an alert
        $alert_system->trigger_alert(
            'test_resolution',
            'Test Resolution Alert',
            'This alert will be resolved',
            'info'
        );

        // Verify it's active
        if (!$alert_system->is_alert_active('test_resolution')) {
            throw new Exception('Alert should be active before resolution');
        }

        // Resolve the alert
        $result = $alert_system->resolve_alert('test_resolution', 'Test resolution note');

        if (!$result) {
            throw new Exception('Failed to resolve alert');
        }

        // Verify it's no longer active
        if ($alert_system->is_alert_active('test_resolution')) {
            throw new Exception('Alert should not be active after resolution');
        }

        // Verify it's in history
        $history = $alert_system->get_alert_history();
        $found_in_history = false;
        foreach ($history as $alert) {
            if ($alert['type'] === 'test_resolution' && $alert['status'] === 'resolved') {
                $found_in_history = true;
                break;
            }
        }
        if (!$found_in_history) {
            throw new Exception('Resolved alert not found in history');
        }
    }

    private function test_alert_updating() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Trigger initial alert
        $alert_system->trigger_alert(
            'test_update',
            'Original Title',
            'Original message',
            'warning'
        );

        // Update the alert
        $result = $alert_system->update_alert(
            'test_update',
            'Updated Title',
            'Updated message',
            'critical',
            ['updated' => true]
        );

        if (!$result) {
            throw new Exception('Failed to update alert');
        }

        // Verify update
        $alert = $alert_system->get_alert('test_update');
        if (!$alert) {
            throw new Exception('Updated alert not found');
        }

        if ($alert['title'] !== 'Updated Title') {
            throw new Exception('Alert title not updated');
        }

        if ($alert['severity'] !== 'critical') {
            throw new Exception('Alert severity not updated');
        }
    }

    private function test_alert_queries() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Clear any existing alerts
        $alert_system->clear_all_alerts();

        // Add multiple alerts
        $alerts_to_add = [
            ['type' => 'memory_warning', 'severity' => 'warning'],
            ['type' => 'memory_critical', 'severity' => 'critical'],
            ['type' => 'memory_info', 'severity' => 'info']
        ];

        foreach ($alerts_to_add as $alert_data) {
            $alert_system->trigger_alert(
                $alert_data['type'],
                'Test Alert',
                'Test message',
                $alert_data['severity']
            );
        }

        // Test get_active_alerts
        $active = $alert_system->get_active_alerts();
        if (count($active) !== 3) {
            throw new Exception('Incorrect number of active alerts returned');
        }

        // Test get_alert_history
        $history = $alert_system->get_alert_history();
        if (count($history) < 3) {
            throw new Exception('Alert history not populated correctly');
        }

        // Test is_alert_active
        if (!$alert_system->is_alert_active('memory_warning')) {
            throw new Exception('is_alert_active returned false for active alert');
        }

        if ($alert_system->is_alert_active('nonexistent_alert')) {
            throw new Exception('is_alert_active returned true for nonexistent alert');
        }
    }

    private function test_alert_statistics() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Clear alerts and add known set
        $alert_system->clear_all_alerts();

        $alert_system->trigger_alert('warning1', 'Warning 1', 'Message', 'warning');
        $alert_system->trigger_alert('warning2', 'Warning 2', 'Message', 'warning');
        $alert_system->trigger_alert('critical1', 'Critical 1', 'Message', 'critical');
        $alert_system->trigger_alert('info1', 'Info 1', 'Message', 'info');

        $stats = $alert_system->get_alert_statistics();

        if ($stats['active_alerts'] !== 4) {
            throw new Exception('Incorrect active alerts count in statistics');
        }

        if ($stats['alerts_by_severity']['warning'] !== 2) {
            throw new Exception('Incorrect warning alerts count in statistics');
        }

        if ($stats['alerts_by_severity']['critical'] !== 1) {
            throw new Exception('Incorrect critical alerts count in statistics');
        }

        if ($stats['alerts_by_severity']['info'] !== 1) {
            throw new Exception('Incorrect info alerts count in statistics');
        }
    }

    private function test_notification_system() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Clear logs
        MockLoggerForAlerts::clear_logs();

        // Trigger alert (should trigger log notification)
        $alert_system->trigger_alert(
            'notification_test',
            'Notification Test',
            'Testing notification system',
            'warning'
        );

        // Check that log notification was sent
        $logs = MockLoggerForAlerts::get_logs();
        $found_notification = false;
        foreach ($logs as $log) {
            if ($log['level'] === 'warning' && strpos($log['message'], 'Memory Alert') !== false) {
                $found_notification = true;
                break;
            }
        }

        if (!$found_notification) {
            throw new Exception('Log notification was not sent');
        }
    }

    private function test_alert_limits() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Clear alerts
        $alert_system->clear_all_alerts();

        // Set low max active alerts for testing
        $alert_system->update_config(['max_active_alerts' => 2]);

        // Add alerts up to limit
        $alert_system->trigger_alert('limit_test1', 'Limit Test 1', 'Message', 'warning');
        $alert_system->trigger_alert('limit_test2', 'Limit Test 2', 'Message', 'warning');

        // This should fail due to limit
        $result = $alert_system->trigger_alert('limit_test3', 'Limit Test 3', 'Message', 'warning');

        if ($result) {
            throw new Exception('Alert creation should have failed due to limit');
        }

        // Verify only 2 alerts are active
        $active = $alert_system->get_active_alerts();
        if (count($active) !== 2) {
            throw new Exception('Alert limit not enforced correctly');
        }
    }

    private function test_auto_resolution() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Clear alerts
        $alert_system->clear_all_alerts();

        // Set short auto-resolve time for testing
        $alert_system->update_config(['auto_resolve_hours' => 0]); // Immediate resolution

        // Add an alert
        $alert_system->trigger_alert('auto_resolve_test', 'Auto Resolve Test', 'Message', 'warning');

        // Run cleanup (simulate cron)
        $alert_system->cleanup_old_alerts();

        // Check if alert was auto-resolved
        $active = $alert_system->get_active_alerts();
        if (count($active) !== 0) {
            throw new Exception('Alert was not auto-resolved');
        }
    }

    private function test_configuration_updates() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Test configuration update
        $new_config = [
            'max_active_alerts' => 100,
            'auto_resolve_hours' => 48,
            'notification_channels' => ['log', 'email']
        ];

        $alert_system->update_config($new_config);

        // Verify configuration was updated (this would need internal access in real implementation)
        // For this test, we just verify the method doesn't throw exceptions
    }

    private function test_error_handling() {
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        // Test resolving non-existent alert
        $result = $alert_system->resolve_alert('nonexistent_alert');
        if ($result) {
            throw new Exception('Resolving non-existent alert should return false');
        }

        // Test updating non-existent alert
        $result = $alert_system->update_alert('nonexistent_alert', 'Title', 'Message', 'warning');
        if ($result) {
            throw new Exception('Updating non-existent alert should return false');
        }

        // Test getting non-existent alert
        $alert = $alert_system->get_alert('nonexistent_alert');
        if ($alert !== null) {
            throw new Exception('Getting non-existent alert should return null');
        }

        // Test clearing alerts
        $cleared = $alert_system->clear_all_alerts();
        if (!is_int($cleared)) {
            throw new Exception('Clear all alerts should return integer count');
        }
    }
}

// Run the tests
if (php_sapi_name() === 'cli') {
    $test = new MemoryAlertSystemUnitTest();
    $test->run_all_tests();
} else {
    echo "This test should be run from the command line.";
}