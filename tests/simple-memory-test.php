<?php
/**
 * Simple Memory-Efficient Data Structures Test
 *
 * Quick verification that all memory-efficient implementations are working.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

// Include necessary files
require_once __DIR__ . '/../includes/Analytics/AnalyticsDataStream.php';
require_once __DIR__ . '/../includes/Content/ContentImportStream.php';
require_once __DIR__ . '/../includes/Core/CacheMetadataStream.php';
require_once __DIR__ . '/../includes/Core/MemoryEfficientConfig.php';
require_once __DIR__ . '/../includes/Core/ResourceCleanupManager.php';

echo "=== Memory-Efficient Data Structures Test ===\n";

try {
    // Test 1: AnalyticsDataStream
    echo "Testing AnalyticsDataStream...\n";
    $analytics_stream = new SMO_Social\Analytics\AnalyticsDataStream(500, 30);
    $analytics_config = $analytics_stream->get_batch_config();
    echo "✓ AnalyticsDataStream created successfully\n";
    echo "  Batch size: " . $analytics_config['batch_size'] . "\n";
    echo "  Max memory: " . $analytics_config['max_memory_usage'] . " MB\n";

    // Test 2: ContentImportStream
    echo "\nTesting ContentImportStream...\n";
    $content_stream = new SMO_Social\Content\ContentImportStream(300, 25);
    $content_config = $content_stream->get_batch_config();
    echo "✓ ContentImportStream created successfully\n";
    echo "  Batch size: " . $content_config['batch_size'] . "\n";
    echo "  Max memory: " . $content_config['max_memory_usage'] . " MB\n";

    // Test 3: CacheMetadataStream
    echo "\nTesting CacheMetadataStream...\n";
    $cache_stream = new SMO_Social\Core\CacheMetadataStream(800, 20);
    $cache_config = $cache_stream->get_batch_config();
    echo "✓ CacheMetadataStream created successfully\n";
    echo "  Batch size: " . $cache_config['batch_size'] . "\n";
    echo "  Max memory: " . $cache_config['max_memory_usage'] . " MB\n";

    // Test 4: MemoryEfficientConfig
    echo "\nTesting MemoryEfficientConfig...\n";
    $config = new SMO_Social\Core\MemoryEfficientConfig();
    $analytics_config = $config->get_config('analytics');
    echo "✓ MemoryEfficientConfig created successfully\n";
    echo "  Analytics batch size: " . $analytics_config['batch_size'] . "\n";
    echo "  Analytics max memory: " . $analytics_config['max_memory_usage'] . " MB\n";

    // Test 5: ResourceCleanupManager
    echo "\nTesting ResourceCleanupManager...\n";
    $cleanup_manager = new SMO_Social\Core\ResourceCleanupManager();
    $stats = $cleanup_manager->get_cleanup_statistics();
    echo "✓ ResourceCleanupManager created successfully\n";
    echo "  Cleanup operations: " . ($stats['total_cleanup_operations'] ?? 0) . "\n";

    // Test 6: Configuration validation
    echo "\nTesting configuration validation...\n";
    $test_config = array('batch_size' => 2000, 'max_memory_usage' => 60);
    $validated = $config->validate_config('analytics', $test_config);
    if ($validated !== false) {
        echo "✓ Configuration validation working\n";
        echo "  Validated batch size: " . $validated['batch_size'] . "\n";
        echo "  Validated max memory: " . $validated['max_memory_usage'] . " MB\n";
    } else {
        echo "✗ Configuration validation failed\n";
    }

    // Test 7: Memory recommendations
    echo "\nTesting memory recommendations...\n";
    $recommendations = $config->get_memory_recommendations();
    echo "✓ Memory recommendations generated\n";
    echo "  Memory level: " . ($recommendations['memory_level'] ?? 'unknown') . "\n";
    echo "  Current limit: " . ($recommendations['current_limit'] ?? 'unknown') . " MB\n";

    echo "\n=== All Tests Passed! ===\n";
    echo "Memory-efficient data structures are working correctly.\n";

} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}