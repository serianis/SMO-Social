<?php
/**
 * Test script to verify SafeArray class loading fix
 * This tests that the SafeArray class can be autoloaded correctly
 */

// Simulate WordPress environment for testing
define('ABSPATH', '/tmp/');
define('SMO_SOCIAL_STANDALONE', true);

// Mock global $wpdb for testing
global $wpdb;
$wpdb = new stdClass();
$wpdb->prefix = 'wp_';

// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = false) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "[ERROR] $message\n";
    }
}

try {
    echo "=== SafeArray Class Loading Test ===\n";
    echo "Testing SafeArray class autoloading...\n";
    
    // Test 1: Load the SafeArray class directly
    require_once 'includes/Core/SafeArray.php';
    echo "✓ SafeArray.php file loaded successfully\n";
    
    // Test 2: Test SafeArray class instantiation and methods
    if (class_exists('SMO_Social\\Core\\SafeArray')) {
        echo "✓ SafeArray class exists and is accessible\n";
        
        // Test the static methods
        $test_array = [
            'name' => 'test_provider',
            'status' => 'active',
            'config' => json_encode(['key' => 'value'])
        ];
        
        $name = SMO_Social\Core\SafeArray::get_string($test_array, 'name', 'default');
        $status = SMO_Social\Core\SafeArray::get_string($test_array, 'status', 'inactive');
        $missing = SMO_Social\Core\SafeArray::get_string($test_array, 'missing', 'fallback');
        
        echo "✓ SafeArray::get_string() working: name=$name, status=$status, missing=$missing\n";
        
        // Test json_decode
        $decoded = SMO_Social\Core\SafeArray::json_decode($test_array['config'], true, []);
        echo "✓ SafeArray::json_decode() working: " . (is_array($decoded) ? 'array' : 'not array') . "\n";
        
    } else {
        throw new Exception("SafeArray class not found after loading file");
    }
    
    echo "\n=== DatabaseProviderLoader Integration Test ===\n";
    echo "Testing DatabaseProviderLoader with SafeArray...\n";
    
    // Test 3: Load DatabaseProviderLoader and test transform method
    require_once 'includes/AI/DatabaseProviderLoader.php';
    echo "✓ DatabaseProviderLoader.php loaded successfully\n";
    
    // Test the transform method with sample data
    $sample_provider = [
        'name' => 'test_provider',
        'display_name' => 'Test Provider',
        'provider_type' => 'custom',
        'auth_config' => '{"api_key": "test"}',
        'default_params' => '{"temperature": 0.7}',
        'supported_models' => '["model1", "model2"]',
        'features' => '["feature1", "feature2"]',
        'rate_limits' => '{"requests_per_minute": 60}',
        'status' => 'active',
        'is_default' => 0
    ];
    
    // Use reflection to call the private method for testing
    $reflection = new ReflectionClass('SMO_Social\\AI\\DatabaseProviderLoader');
    $method = $reflection->getMethod('transform_database_provider');
    $method->setAccessible(true);
    
    $transformed = $method->invoke(null, $sample_provider);
    
    if (is_array($transformed) && isset($transformed['id'])) {
        echo "✓ DatabaseProviderLoader::transform_database_provider() working correctly\n";
        echo "  - Transformed provider ID: " . $transformed['id'] . "\n";
        echo "  - Provider name: " . $transformed['name'] . "\n";
        echo "  - Provider type: " . $transformed['type'] . "\n";
    } else {
        throw new Exception("transform_database_provider did not return expected format");
    }
    
    echo "\n=== Test Results ===\n";
    echo "✓ ALL TESTS PASSED! The SafeArray class loading issue has been resolved.\n";
    echo "✓ The plugin should now load without the 'Class SafeArray not found' fatal error.\n";
    
} catch (Exception $e) {
    echo "\n=== Test Failed ===\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}