<?php
/**
 * Test script for memory monitoring system integration
 */

require_once 'includes/Core/MemoryMonitor.php';
require_once 'includes/Core/MemoryAlertSystem.php';
require_once 'includes/Core/MemoryMonitorConfig.php';

echo "Testing Memory Monitoring System Integration\n";
echo "==========================================\n\n";

// Test MemoryMonitorConfig
echo "1. Testing MemoryMonitorConfig...\n";
try {
    $config = SMO_Social\Core\MemoryMonitorConfig::get_instance();
    $monitoring_config = $config->get_monitoring_config();
    echo "✓ MemoryMonitorConfig loaded successfully\n";
    echo "  - Monitoring enabled: " . ($monitoring_config['monitoring_enabled'] ? 'Yes' : 'No') . "\n";
    echo "  - Interval: {$monitoring_config['monitoring_interval']} seconds\n";
} catch (Exception $e) {
    echo "✗ MemoryMonitorConfig failed: " . $e->getMessage() . "\n";
}

// Test MemoryAlertSystem
echo "\n2. Testing MemoryAlertSystem...\n";
try {
    $alert_system = SMO_Social\Core\MemoryAlertSystem::get_instance();
    $stats = $alert_system->get_alert_statistics();
    echo "✓ MemoryAlertSystem loaded successfully\n";
    echo "  - Active alerts: {$stats['active_alerts']}\n";
    echo "  - Total history: {$stats['total_history']}\n";
} catch (Exception $e) {
    echo "✗ MemoryAlertSystem failed: " . $e->getMessage() . "\n";
}

// Test MemoryMonitor
echo "\n3. Testing MemoryMonitor...\n";
try {
    $monitor = SMO_Social\Core\MemoryMonitor::get_instance();
    $integrated_systems = $monitor->get_integrated_systems_status();
    echo "✓ MemoryMonitor loaded successfully\n";
    echo "  - Integrated systems: " . count($integrated_systems) . "\n";

    foreach ($integrated_systems as $system_name => $system_info) {
        echo "    - {$system_name}: " . ($system_info['status'] === 'integrated' ? '✓' : 'Available') . "\n";
    }

    // Force a monitoring cycle
    $monitor->force_memory_monitoring();
    $current_stats = $monitor->get_current_stats();
    echo "  - Current memory usage: " . ($current_stats['total_usage_formatted'] ?? 'N/A') . "\n";
    echo "  - Usage percentage: " . ($current_stats['usage_percentage'] ?? 0) . "%\n";
    echo "  - Status: " . ($current_stats['status'] ?? 'unknown') . "\n";

} catch (Exception $e) {
    echo "✗ MemoryMonitor failed: " . $e->getMessage() . "\n";
}

// Test alert triggering
echo "\n4. Testing alert triggering...\n";
try {
    $alert_system = SMO_Social\Core\MemoryAlertSystem::get_instance();
    $result = $alert_system->trigger_alert(
        'test_integration',
        'Integration Test Alert',
        'This is a test alert to verify the integration works correctly.',
        'info',
        ['test_data' => 'integration_test']
    );

    if ($result) {
        echo "✓ Test alert triggered successfully\n";
    } else {
        echo "✗ Test alert failed to trigger\n";
    }

    // Check active alerts
    $active_alerts = $alert_system->get_active_alerts();
    echo "  - Active alerts after test: " . count($active_alerts) . "\n";

} catch (Exception $e) {
    echo "✗ Alert triggering test failed: " . $e->getMessage() . "\n";
}

echo "\n5. Testing configuration sharing...\n";
try {
    $config = SMO_Social\Core\MemoryMonitorConfig::get_instance();
    $monitor = SMO_Social\Core\MemoryMonitor::get_instance();

    // Test updating config
    $original_config = $config->get_config();
    $test_config = ['monitoring_interval' => 15]; // Test value
    $config->update_config($test_config);

    // Check if monitor picked up the change
    $monitor->update_config($test_config);

    echo "✓ Configuration sharing works\n";
    echo "  - Config updated and propagated to monitor\n";

    // Restore original config
    $config->update_config(['monitoring_interval' => $original_config['monitoring_interval'] ?? 10]);

} catch (Exception $e) {
    echo "✗ Configuration sharing test failed: " . $e->getMessage() . "\n";
}

echo "\nMemory Monitoring Integration Test Complete\n";
echo "==========================================\n";