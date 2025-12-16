<?php
/**
 * Performance Tests for Memory Monitoring System
 *
 * Tests performance, memory usage, and scalability of the memory monitoring system
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

class PerformanceMetrics {
    private $start_time;
    private $start_memory;
    private $measurements = [];

    public function start_measurement($name) {
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage(true);
        $this->measurements[$name] = [
            'start_time' => $this->start_time,
            'start_memory' => $this->start_memory
        ];
    }

    public function end_measurement($name) {
        if (!isset($this->measurements[$name])) {
            return false;
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);

        $this->measurements[$name]['end_time'] = $end_time;
        $this->measurements[$name]['end_memory'] = $end_memory;
        $this->measurements[$name]['duration'] = $end_time - $this->measurements[$name]['start_time'];
        $this->measurements[$name]['memory_used'] = $end_memory - $this->measurements[$name]['start_memory'];

        return $this->measurements[$name];
    }

    public function get_measurement($name) {
        return $this->measurements[$name] ?? null;
    }

    public function get_all_measurements() {
        return $this->measurements;
    }

    public function print_report() {
        echo "\nPerformance Report:\n";
        echo "==================\n";

        foreach ($this->measurements as $name => $data) {
            if (isset($data['duration'])) {
                $duration_ms = round($data['duration'] * 1000, 2);
                $memory_kb = round($data['memory_used'] / 1024, 2);
                echo sprintf("%-30s: %8.2f ms, %10.2f KB\n", $name, $duration_ms, $memory_kb);
            }
        }
    }
}

class MemoryMonitoringPerformanceTest {
    private $monitor;
    private $alert_system;
    private $config;
    private $metrics;

    public function __construct() {
        $this->metrics = new PerformanceMetrics();
        $this->config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();
        $this->monitor = \SMO_Social\Core\MemoryMonitor::get_instance();
        $this->alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();
    }

    public function run_all_tests() {
        echo "Starting Memory Monitoring System Performance Tests...\n";
        echo "======================================================\n\n";

        $tests = [
            'test_initialization_performance',
            'test_monitoring_cycle_performance',
            'test_alert_system_performance',
            'test_configuration_performance',
            'test_concurrent_monitoring_performance',
            'test_memory_efficiency_under_load',
            'test_large_dataset_performance',
            'test_real_time_monitoring_performance',
            'test_error_handling_performance',
            'test_cleanup_performance'
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

        // Print performance report
        $this->metrics->print_report();

        echo "\nTest Results: $passed/$total tests passed\n";

        if ($passed === $total) {
            echo "🎉 All Memory Monitoring performance tests PASSED!\n";
        } else {
            echo "⚠️  Some tests failed. Please review the performance.\n";
        }
    }

    private function test_initialization_performance() {
        // Test component initialization performance
        $this->metrics->start_measurement('component_initialization');

        // Re-initialize components to test performance
        $config = \SMO_Social\Core\MemoryMonitorConfig::get_instance();
        $monitor = \SMO_Social\Core\MemoryMonitor::get_instance();
        $alert_system = \SMO_Social\Core\MemoryAlertSystem::get_instance();

        $measurement = $this->metrics->end_measurement('component_initialization');

        // Assert reasonable performance (< 100ms, < 1MB)
        if ($measurement['duration'] > 0.1) {
            throw new Exception('Component initialization too slow: ' . round($measurement['duration'] * 1000, 2) . 'ms');
        }

        if ($measurement['memory_used'] > 1024 * 1024) {
            throw new Exception('Component initialization uses too much memory: ' . round($measurement['memory_used'] / 1024, 2) . 'KB');
        }
    }

    private function test_monitoring_cycle_performance() {
        // Test monitoring cycle performance
        $this->metrics->start_measurement('monitoring_cycle');

        // Run multiple monitoring cycles
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->force_memory_monitoring();
        }

        $measurement = $this->metrics->end_measurement('monitoring_cycle');

        // Calculate per-cycle metrics
        $avg_duration = $measurement['duration'] / 10;
        $avg_memory = $measurement['memory_used'] / 10;

        // Assert reasonable performance (< 50ms per cycle, < 500KB per cycle)
        if ($avg_duration > 0.05) {
            throw new Exception('Monitoring cycle too slow: ' . round($avg_duration * 1000, 2) . 'ms average');
        }

        if ($avg_memory > 512 * 1024) {
            throw new Exception('Monitoring cycle uses too much memory: ' . round($avg_memory / 1024, 2) . 'KB average');
        }
    }

    private function test_alert_system_performance() {
        // Test alert system performance under load
        $this->alert_system->clear_all_alerts();

        $this->metrics->start_measurement('alert_system_operations');

        // Create multiple alerts
        for ($i = 0; $i < 50; $i++) {
            $this->alert_system->trigger_alert(
                "perf_test_alert_$i",
                "Performance Test Alert $i",
                "Testing alert system performance",
                'info'
            );
        }

        // Query alerts
        $active_alerts = $this->alert_system->get_active_alerts();
        $stats = $this->alert_system->get_alert_statistics();
        $history = $this->alert_system->get_alert_history(25);

        // Resolve alerts
        for ($i = 0; $i < 25; $i++) {
            $this->alert_system->resolve_alert("perf_test_alert_$i");
        }

        $measurement = $this->metrics->end_measurement('alert_system_operations');

        // Assert reasonable performance (< 200ms for 50 operations, < 2MB)
        if ($measurement['duration'] > 0.2) {
            throw new Exception('Alert system operations too slow: ' . round($measurement['duration'] * 1000, 2) . 'ms');
        }

        if ($measurement['memory_used'] > 2 * 1024 * 1024) {
            throw new Exception('Alert system uses too much memory: ' . round($measurement['memory_used'] / 1024, 2) . 'KB');
        }

        // Verify operations completed
        if (count($active_alerts) !== 50) {
            throw new Exception('Not all alerts were created');
        }
    }

    private function test_configuration_performance() {
        // Test configuration operations performance
        $this->metrics->start_measurement('configuration_operations');

        $original_config = $this->config->get_config();

        // Perform multiple configuration updates
        for ($i = 0; $i < 20; $i++) {
            $this->config->update_config([
                'monitoring_interval' => 10 + $i,
                'warning_threshold' => 70 + ($i % 10)
            ]);
        }

        // Test preset application
        $this->config->apply_preset('development');
        $this->config->apply_preset('production');

        // Test backup operations
        $this->config->create_backup('perf_test_backup');
        $this->config->restore_backup('perf_test_backup');

        $measurement = $this->metrics->end_measurement('configuration_operations');

        // Assert reasonable performance (< 150ms, < 1MB)
        if ($measurement['duration'] > 0.15) {
            throw new Exception('Configuration operations too slow: ' . round($measurement['duration'] * 1000, 2) . 'ms');
        }

        if ($measurement['memory_used'] > 1024 * 1024) {
            throw new Exception('Configuration operations use too much memory: ' . round($measurement['memory_used'] / 1024, 2) . 'KB');
        }

        // Restore original config
        $this->config->update_config($original_config);
    }

    private function test_concurrent_monitoring_performance() {
        // Test performance under simulated concurrent load
        $this->metrics->start_measurement('concurrent_monitoring');

        $processes = [];

        // Simulate concurrent monitoring by running multiple cycles rapidly
        for ($i = 0; $i < 5; $i++) {
            $start_time = microtime(true);

            // Run monitoring cycle
            $this->monitor->force_memory_monitoring();

            // Get various data
            $this->monitor->get_current_stats();
            $this->monitor->get_memory_usage_by_component();
            $this->monitor->get_memory_efficiency_analysis();

            $end_time = microtime(true);
            $processes[] = $end_time - $start_time;
        }

        $measurement = $this->metrics->end_measurement('concurrent_monitoring');

        // Calculate average response time
        $avg_response_time = array_sum($processes) / count($processes);

        // Assert reasonable concurrent performance (< 30ms average response time)
        if ($avg_response_time > 0.03) {
            throw new Exception('Concurrent monitoring too slow: ' . round($avg_response_time * 1000, 2) . 'ms average');
        }
    }

    private function test_memory_efficiency_under_load() {
        // Test memory efficiency when system is under load
        $initial_memory = memory_get_usage(true);

        $this->metrics->start_measurement('memory_efficiency_test');

        // Create memory load by storing large amounts of data
        $test_data = [];
        for ($i = 0; $i < 1000; $i++) {
            $test_data[] = str_repeat('x', 1000); // 1KB per item
        }

        // Run monitoring during memory load
        for ($i = 0; $i < 5; $i++) {
            $this->monitor->force_memory_monitoring();
            $stats = $this->monitor->get_current_stats();
            $efficiency = $this->monitor->get_memory_efficiency_analysis();
        }

        // Clean up test data
        unset($test_data);

        $measurement = $this->metrics->end_measurement('memory_efficiency_test');

        $final_memory = memory_get_usage(true);
        $memory_increase = $final_memory - $initial_memory;

        // Assert memory efficiency (< 5MB total increase during test)
        if ($memory_increase > 5 * 1024 * 1024) {
            throw new Exception('Memory usage increased too much during load test: ' . round($memory_increase / 1024 / 1024, 2) . 'MB');
        }

        // Force garbage collection if available
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    private function test_large_dataset_performance() {
        // Test performance with large datasets
        $this->metrics->start_measurement('large_dataset_handling');

        // Simulate large historical dataset
        $large_history = [];
        for ($i = 0; $i < 1000; $i++) {
            $large_history[] = [
                'timestamp' => date('Y-m-d H:i:s', time() - ($i * 60)),
                'usage_percentage' => 50 + rand(-10, 10),
                'efficiency_score' => 70 + rand(-20, 20)
            ];
        }

        // Test analysis on large dataset
        $trends = $this->monitor->get_trend_analysis(24);
        $patterns = $this->monitor->get_usage_patterns('all');
        $forecast = $this->monitor->generate_memory_forecast(24);

        $measurement = $this->metrics->end_measurement('large_dataset_handling');

        // Assert reasonable performance with large data (< 100ms, < 2MB)
        if ($measurement['duration'] > 0.1) {
            throw new Exception('Large dataset processing too slow: ' . round($measurement['duration'] * 1000, 2) . 'ms');
        }

        if ($measurement['memory_used'] > 2 * 1024 * 1024) {
            throw new Exception('Large dataset processing uses too much memory: ' . round($measurement['memory_used'] / 1024, 2) . 'KB');
        }
    }

    private function test_real_time_monitoring_performance() {
        // Test real-time monitoring performance
        $this->metrics->start_measurement('real_time_monitoring');

        // Enable real-time monitoring
        $this->monitor->set_real_time_monitoring(true);

        // Run real-time monitoring cycles
        for ($i = 0; $i < 20; $i++) {
            $rt_status = $this->monitor->get_real_time_status();
            $rt_memory = $this->monitor->get_real_time_memory_status();

            // Small delay to simulate real-time intervals
            usleep(1000); // 1ms
        }

        // Disable real-time monitoring
        $this->monitor->set_real_time_monitoring(false);

        $measurement = $this->metrics->end_measurement('real_time_monitoring');

        // Assert real-time performance (< 50ms for 20 cycles, < 1MB)
        if ($measurement['duration'] > 0.05) {
            throw new Exception('Real-time monitoring too slow: ' . round($measurement['duration'] * 1000, 2) . 'ms');
        }

        if ($measurement['memory_used'] > 1024 * 1024) {
            throw new Exception('Real-time monitoring uses too much memory: ' . round($measurement['memory_used'] / 1024, 2) . 'KB');
        }
    }

    private function test_error_handling_performance() {
        // Test error handling performance
        $this->metrics->start_measurement('error_handling');

        // Test various error conditions
        $error_scenarios = [
            function() { $this->config->update_config(['invalid_key' => 'invalid_value']); },
            function() { $this->alert_system->resolve_alert('nonexistent_alert'); },
            function() { $this->monitor->update_config(['monitoring_interval' => -1]); },
            function() { $this->config->apply_preset('nonexistent_preset'); },
            function() { $this->alert_system->trigger_alert('', '', '', ''); }
        ];

        foreach ($error_scenarios as $scenario) {
            try {
                $scenario();
            } catch (Exception $e) {
                // Expected errors - continue
            }
        }

        // Test system remains functional after errors
        $stats = $this->monitor->get_current_stats();
        $alerts = $this->alert_system->get_active_alerts();
        $config = $this->config->get_config();

        $measurement = $this->metrics->end_measurement('error_handling');

        // Assert error handling performance (< 50ms, < 500KB)
        if ($measurement['duration'] > 0.05) {
            throw new Exception('Error handling too slow: ' . round($measurement['duration'] * 1000, 2) . 'ms');
        }

        if ($measurement['memory_used'] > 512 * 1024) {
            throw new Exception('Error handling uses too much memory: ' . round($measurement['memory_used'] / 1024, 2) . 'KB');
        }

        // Verify system still functional
        if (!is_array($stats) || !is_array($alerts) || !is_array($config)) {
            throw new Exception('System not functional after error handling');
        }
    }

    private function test_cleanup_performance() {
        // Test cleanup operations performance
        $this->metrics->start_measurement('cleanup_operations');

        // Create test data to clean up
        for ($i = 0; $i < 100; $i++) {
            $this->alert_system->trigger_alert(
                "cleanup_test_$i",
                "Cleanup Test $i",
                "Test alert for cleanup",
                'info'
            );
        }

        // Perform cleanup operations
        $this->alert_system->cleanup_old_alerts();

        // Clear remaining alerts
        $cleared = $this->alert_system->clear_all_alerts();

        // Test configuration cleanup
        $this->config->create_backup('cleanup_test');
        $this->config->delete_backup('cleanup_test');

        $measurement = $this->metrics->end_measurement('cleanup_operations');

        // Assert cleanup performance (< 100ms, < 1MB)
        if ($measurement['duration'] > 0.1) {
            throw new Exception('Cleanup operations too slow: ' . round($measurement['duration'] * 1000, 2) . 'ms');
        }

        if ($measurement['memory_used'] > 1024 * 1024) {
            throw new Exception('Cleanup operations use too much memory: ' . round($measurement['memory_used'] / 1024, 2) . 'KB');
        }

        // Verify cleanup worked
        if ($cleared < 100) {
            throw new Exception('Cleanup did not clear all test alerts');
        }
    }
}

// Run the tests
if (php_sapi_name() === 'cli') {
    $test = new MemoryMonitoringPerformanceTest();
    $test->run_all_tests();
} else {
    echo "This test should be run from the command line.";
}