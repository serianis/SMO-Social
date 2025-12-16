<?php
/**
 * Unit Tests for MemoryMonitor Class
 *
 * Comprehensive unit testing for the MemoryMonitor class covering all functionality
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
require_once __DIR__ . '/../includes/Core/Logger.php';

// Mock classes for testing
class MockMemoryMonitorConfig {
    private static $instance = null;
    private $config = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_config() {
        return $this->config ?: [
            'monitoring_enabled' => true,
            'monitoring_interval' => 10,
            'warning_threshold' => 70,
            'critical_threshold' => 90,
            'max_history_entries' => 100,
            'enable_real_time_monitoring' => false
        ];
    }

    public function update_config($config) {
        $this->config = array_merge($this->config, $config);
        return true;
    }
}

class MockLogger {
    public static function error($message, $context = []) {
        echo "ERROR: $message\n";
    }

    public static function warning($message, $context = []) {
        echo "WARNING: $message\n";
    }

    public static function info($message, $context = []) {
        echo "INFO: $message\n";
    }

    public static function debug($message, $context = []) {
        echo "DEBUG: $message\n";
    }
}

class MockWPDB {
    public $prefix = 'wp_';
    public $charset_collate = 'utf8mb4_unicode_ci';

    public function get_charset_collate() {
        return 'utf8mb4_unicode_ci';
    }

    public function insert($table, $data) {
        return true;
    }

    public function get_results($query, $output = ARRAY_A) {
        if (strpos($query, 'smo_memory_metrics_history') !== false) {
            return [
                [
                    'id' => 1,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'memory_usage' => 67108864,
                    'usage_percentage' => 65.5,
                    'system_memory' => 67108864,
                    'peak_memory' => 134217728,
                    'pool_memory' => 16777216,
                    'cache_memory' => 8388608,
                    'status' => 'warning',
                    'efficiency_score' => 72.5,
                    'component_breakdown' => json_encode(['system' => ['usage' => 67108864, 'percentage' => 65.5]]),
                    'system_info' => json_encode(['php_version' => '8.1.0'])
                ]
            ];
        }
        if (strpos($query, 'smo_memory_trends') !== false) {
            return [
                [
                    'id' => 1,
                    'trend_type' => 'memory_usage_24h',
                    'trend_direction' => 'increasing',
                    'trend_slope' => 0.0234,
                    'trend_strength' => 0.8,
                    'confidence_level' => 0.85
                ]
            ];
        }
        return [];
    }

    public function prepare($query, ...$args) {
        return $query;
    }

    public function replace($table, $data) {
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
}

class MemoryMonitorUnitTest {
    private $monitor;
    private $original_config;
    private $original_logger;
    private $original_wpdb;

    public function __construct() {
        // Backup originals
        $this->original_config = $GLOBALS['SMO_Social\Core\MemoryMonitorConfig'] ?? null;
        $this->original_logger = $GLOBALS['SMO_Social\Core\Logger'] ?? null;
        $this->original_wpdb = $GLOBALS['wpdb'] ?? null;

        // Set up mocks
        $GLOBALS['SMO_Social\Core\MemoryMonitorConfig'] = new MockMemoryMonitorConfig();
        $GLOBALS['SMO_Social\Core\Logger'] = new MockLogger();
        $GLOBALS['wpdb'] = new MockWPDB();
    }

    public function __destruct() {
        // Restore originals
        if ($this->original_config) {
            $GLOBALS['SMO_Social\Core\MemoryMonitorConfig'] = $this->original_config;
        }
        if ($this->original_logger) {
            $GLOBALS['SMO_Social\Core\Logger'] = $this->original_logger;
        }
        if ($this->original_wpdb) {
            $GLOBALS['wpdb'] = $this->original_wpdb;
        }
    }

    public function run_all_tests() {
        echo "Starting MemoryMonitor Unit Tests...\n";
        echo "=====================================\n\n";

        $tests = [
            'test_singleton_pattern',
            'test_initialization',
            'test_memory_collection',
            'test_data_storage',
            'test_alert_system',
            'test_historical_data',
            'test_trend_analysis',
            'test_memory_leak_detection',
            'test_usage_patterns',
            'test_forecasting',
            'test_optimization_recommendations',
            'test_component_breakdown',
            'test_diagnostics',
            'test_real_time_monitoring',
            'test_configuration_updates',
            'test_error_handling'
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
            echo "🎉 All MemoryMonitor unit tests PASSED!\n";
        } else {
            echo "⚠️  Some tests failed. Please review the implementation.\n";
        }
    }

    private function test_singleton_pattern() {
        $instance1 = \SMO_Social\Core\MemoryMonitor::get_instance();
        $instance2 = \SMO_Social\Core\MemoryMonitor::get_instance();

        if ($instance1 !== $instance2) {
            throw new Exception('Singleton pattern not working - different instances returned');
        }

        if (!($instance1 instanceof \SMO_Social\Core\MemoryMonitor)) {
            throw new Exception('Instance is not of correct type');
        }
    }

    private function test_initialization() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test that configuration is loaded
        $config = $monitor->get_current_stats();
        if (!is_array($config)) {
            throw new Exception('Configuration not properly initialized');
        }

        // Test integrated systems detection
        $integrated = $monitor->get_integrated_systems_status();
        if (!is_array($integrated)) {
            throw new Exception('Integrated systems status not available');
        }
    }

    private function test_memory_collection() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test system memory collection
        $system_memory = $this->invoke_private_method($monitor, 'collect_system_memory_usage');
        $required_keys = ['current_usage', 'peak_usage', 'memory_limit', 'usage_percentage'];

        foreach ($required_keys as $key) {
            if (!isset($system_memory[$key])) {
                throw new Exception("System memory collection missing key: $key");
            }
        }

        // Test integrated systems memory collection
        $integrated_memory = $this->invoke_private_method($monitor, 'collect_integrated_systems_memory');
        if (!is_array($integrated_memory)) {
            throw new Exception('Integrated systems memory collection failed');
        }
    }

    private function test_data_storage() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test memory data storage
        $test_data = [
            'timestamp' => time(),
            'total_usage' => 67108864,
            'usage_percentage' => 65.5,
            'status' => 'normal',
            'efficiency_score' => 75.0
        ];

        $this->invoke_private_method($monitor, 'store_memory_data', [$test_data]);

        // Test that data was stored in history
        $history = $monitor->get_memory_history();
        if (empty($history)) {
            throw new Exception('Memory data not stored in history');
        }

        // Test current stats
        $current = $monitor->get_current_stats();
        if (empty($current)) {
            throw new Exception('Current stats not updated');
        }
    }

    private function test_alert_system() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test alert checking with normal usage
        $normal_data = [
            'usage_percentage' => 50,
            'status' => 'normal'
        ];
        $this->invoke_private_method($monitor, 'check_memory_alerts', [$normal_data]);

        // Test alert checking with warning usage
        $warning_data = [
            'usage_percentage' => 75,
            'status' => 'warning'
        ];
        $this->invoke_private_method($monitor, 'check_memory_alerts', [$warning_data]);

        // Test alert checking with critical usage
        $critical_data = [
            'usage_percentage' => 95,
            'status' => 'critical'
        ];
        $this->invoke_private_method($monitor, 'check_memory_alerts', [$critical_data]);
    }

    private function test_historical_data() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test database memory history retrieval
        $history = $monitor->get_database_memory_history(10);
        if (!is_array($history)) {
            throw new Exception('Database memory history retrieval failed');
        }

        // Test historical metrics
        $metrics = $monitor->get_historical_metrics(10);
        if (!is_array($metrics)) {
            throw new Exception('Historical metrics retrieval failed');
        }
    }

    private function test_trend_analysis() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test trend analysis
        $trends = $monitor->get_trend_analysis(24);
        if (!is_array($trends)) {
            throw new Exception('Trend analysis failed');
        }

        // Test memory efficiency analysis
        $efficiency = $monitor->get_memory_efficiency_analysis();
        if (!is_array($efficiency)) {
            throw new Exception('Memory efficiency analysis failed');
        }

        $required_keys = ['average_usage', 'peak_usage', 'average_efficiency', 'trend'];
        foreach ($required_keys as $key) {
            if (!isset($efficiency[$key])) {
                throw new Exception("Efficiency analysis missing key: $key");
            }
        }
    }

    private function test_memory_leak_detection() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test memory leak pattern detection
        $leaks = $monitor->get_memory_leak_patterns(24);
        if (!is_array($leaks)) {
            throw new Exception('Memory leak pattern detection failed');
        }
    }

    private function test_usage_patterns() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test usage pattern analysis
        $patterns = $monitor->get_usage_patterns('all');
        if (!is_array($patterns)) {
            throw new Exception('Usage pattern analysis failed');
        }
    }

    private function test_forecasting() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test memory forecasting
        $forecast = $monitor->generate_memory_forecast(24);
        if (!is_array($forecast)) {
            throw new Exception('Memory forecasting failed');
        }

        $required_keys = ['forecast_period_hours', 'predictions', 'risk_assessment'];
        foreach ($required_keys as $key) {
            if (!isset($forecast[$key])) {
                throw new Exception("Forecast missing key: $key");
            }
        }
    }

    private function test_optimization_recommendations() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test optimization recommendations
        $recommendations = $monitor->generate_optimization_recommendations();
        if (!is_array($recommendations)) {
            throw new Exception('Optimization recommendations failed');
        }
    }

    private function test_component_breakdown() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test component breakdown analysis
        $breakdown = $monitor->get_memory_usage_by_component();
        if (!is_array($breakdown)) {
            throw new Exception('Component breakdown analysis failed');
        }
    }

    private function test_diagnostics() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test monitoring diagnostics
        $diagnostics = $monitor->get_monitoring_diagnostics();
        if (!is_array($diagnostics)) {
            throw new Exception('Monitoring diagnostics failed');
        }

        $required_keys = ['timestamp', 'system_status', 'issues', 'metrics', 'configuration'];
        foreach ($required_keys as $key) {
            if (!isset($diagnostics[$key])) {
                throw new Exception("Diagnostics missing key: $key");
            }
        }
    }

    private function test_real_time_monitoring() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test real-time monitoring status
        $rt_status = $monitor->get_real_time_status();
        if (!is_array($rt_status)) {
            throw new Exception('Real-time monitoring status failed');
        }

        $required_keys = ['enabled', 'interval', 'current_interval', 'last_monitor_time'];
        foreach ($required_keys as $key) {
            if (!isset($rt_status[$key])) {
                throw new Exception("Real-time status missing key: $key");
            }
        }

        // Test real-time memory status
        $rt_memory = $monitor->get_real_time_memory_status();
        if (!is_array($rt_memory)) {
            throw new Exception('Real-time memory status failed');
        }
    }

    private function test_configuration_updates() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test configuration updates
        $original_config = $monitor->get_current_stats();

        $new_config = ['monitoring_interval' => 15];
        $result = $monitor->update_config($new_config);

        if (!$result) {
            throw new Exception('Configuration update failed');
        }

        // Test real-time monitoring toggle
        $rt_result = $monitor->set_real_time_monitoring(true);
        if (!$rt_result) {
            throw new Exception('Real-time monitoring toggle failed');
        }

        $rt_result = $monitor->set_real_time_monitoring(false);
        if (!$rt_result) {
            throw new Exception('Real-time monitoring toggle back failed');
        }
    }

    private function test_error_handling() {
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();

        // Test with invalid configuration
        try {
            $monitor->update_config(['monitoring_interval' => 'invalid']);
            // Should not throw exception but handle gracefully
        } catch (Exception $e) {
            // This is expected for invalid config
        }

        // Test force monitoring
        $monitor->force_memory_monitoring();

        // Test with empty history
        $empty_history = $monitor->get_memory_history(0);
        if (!is_array($empty_history)) {
            throw new Exception('Empty history retrieval failed');
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
    $test = new MemoryMonitorUnitTest();
    $test->run_all_tests();
} else {
    echo "This test should be run from the command line.";
}