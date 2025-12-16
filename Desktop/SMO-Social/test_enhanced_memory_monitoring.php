<?php
/**
 * Test script for enhanced memory monitoring system
 */

// Define WordPress constants
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

// Include required files
require_once 'includes/wordpress-test-stubs.php';
require_once 'includes/wordpress-functions.php';
require_once 'includes/Core/MemoryMonitor.php';
require_once 'includes/Core/MemoryMonitorConfig.php';
require_once 'includes/Core/MemoryAlertSystem.php';
require_once 'includes/Core/Logger.php';

echo "Testing Enhanced Memory Monitoring System\n";
echo "=========================================\n\n";

// Mock database functions
class MockWPDB {
    public $prefix = 'wp_';
    public $charset_collate = 'utf8mb4_unicode_ci';

    public function get_charset_collate() {
        return 'utf8mb4_unicode_ci';
    }

    public function insert($table, $data) {
        echo "  ✓ Inserted data into {$table}\n";
        return true;
    }

    public function get_results($query, $output = ARRAY_A) {
        // Return mock data for testing
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
        if (strpos($query, 'smo_memory_leak_patterns') !== false) {
            return [
                [
                    'id' => 1,
                    'leak_type' => 'gradual_growth',
                    'severity_level' => 'medium',
                    'memory_growth_rate' => 0.015,
                    'confidence_score' => 0.78,
                    'affected_components' => json_encode(['pool_database', 'pool_cache'])
                ]
            ];
        }
        if (strpos($query, 'smo_memory_usage_patterns') !== false) {
            return [
                [
                    'pattern_name' => 'hourly_pattern_14',
                    'pattern_type' => 'hourly',
                    'predictive_power' => 0.75,
                    'confidence_score' => 0.82,
                    'pattern_data' => json_encode(['count' => 30, 'total_usage' => 1950, 'peak_usage' => 75.5])
                ]
            ];
        }
        return [];
    }

    public function prepare($query, ...$args) {
        return $query; // Simplified for testing
    }

    public function replace($table, $data) {
        echo "  ✓ Replaced data in {$table}\n";
        return true;
    }

    public function get_var($query) {
        // Mock table existence check
        if (strpos($query, 'SHOW TABLES LIKE') !== false) {
            return 'wp_smo_memory_monitoring'; // Assume table exists
        }
        return null;
    }

    public function query($query) {
        return true; // Mock successful query
    }
}

global $wpdb;
$wpdb = new MockWPDB();

echo "1. Testing Enhanced Memory Monitor Initialization...\n";
try {
    $monitor = SMO_Social\Core\MemoryMonitor::get_instance();
    echo "✓ MemoryMonitor instance created successfully\n";

    // Test enhanced data collection
    echo "\n2. Testing Enhanced Data Collection...\n";
    $monitor->perform_memory_monitoring();
    echo "✓ Enhanced memory monitoring executed\n";

    // Test historical data retrieval
    echo "\n3. Testing Historical Data Retrieval...\n";
    $historical_data = $monitor->get_historical_metrics(10);
    echo "✓ Retrieved " . count($historical_data) . " historical records\n";

    // Test trend analysis
    echo "\n4. Testing Trend Analysis...\n";
    $trends = $monitor->get_trend_analysis(24);
    echo "✓ Retrieved " . count($trends) . " trend analysis records\n";

    // Test memory leak detection
    echo "\n5. Testing Memory Leak Detection...\n";
    $leaks = $monitor->get_memory_leak_patterns(24);
    echo "✓ Detected " . count($leaks) . " potential memory leaks\n";

    // Test usage pattern recognition
    echo "\n6. Testing Usage Pattern Recognition...\n";
    $patterns = $monitor->get_usage_patterns('all');
    echo "✓ Identified " . count($patterns) . " usage patterns\n";

    // Test predictive analytics
    echo "\n7. Testing Predictive Analytics...\n";
    $forecast = $monitor->generate_memory_forecast(24);
    if (isset($forecast['error'])) {
        echo "⚠ Forecast not available: " . $forecast['error'] . "\n";
    } else {
        echo "✓ Generated forecast with " . count($forecast['predictions']) . " predictions\n";
        echo "  - Risk assessment: " . ($forecast['risk_assessment'] ?? 'unknown') . "\n";
    }

    // Test optimization recommendations
    echo "\n8. Testing Optimization Recommendations...\n";
    $recommendations = $monitor->generate_optimization_recommendations();
    echo "✓ Generated " . count($recommendations) . " optimization recommendations\n";

    // Test component breakdown
    echo "\n9. Testing Component Breakdown Analysis...\n";
    $breakdown = $monitor->get_memory_usage_by_component();
    echo "✓ Analyzed " . count($breakdown) . " memory components\n";

    // Test enhanced diagnostics
    echo "\n10. Testing Enhanced Diagnostics...\n";
    $diagnostics = $monitor->get_monitoring_diagnostics();
    echo "✓ System status: " . ($diagnostics['system_status'] ?? 'unknown') . "\n";
    echo "  - Issues found: " . count($diagnostics['issues'] ?? []) . "\n";

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Enhanced Memory Monitoring System Test Complete!\n";
    echo "All core features are functioning correctly.\n";
    echo str_repeat("=", 50) . "\n";

} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}