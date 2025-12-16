<?php
/**
 * Test file to verify the PHP function signature fix
 * Tests the process_content_in_chunks method with the corrected parameter order
 */

// Mock WordPress functions for testing
if (!function_exists('error_log')) {
    function error_log($message) {
        echo "LOG: " . $message . "\n";
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url) {
        return array('body' => 'mock response');
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($var) {
        return false;
    }
}

// Include the fixed file
require_once 'includes/Content/ContentImportStream.php';

echo "Testing PHP function signature fix...\n";

// Test 1: Basic instantiation
try {
    $contentStream = new SMO_Social\Content\ContentImportStream();
    echo "✓ ContentImportStream instantiated successfully\n";
} catch (Exception $e) {
    echo "✗ Failed to instantiate ContentImportStream: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Method exists with correct signature
try {
    $reflection = new ReflectionClass('SMO_Social\\Content\\ContentImportStream');
    $method = $reflection->getMethod('process_content_in_chunks');
    
    $params = $method->getParameters();
    echo "✓ Method process_content_in_chunks exists\n";
    
    // Check parameter order
    if (count($params) === 3) {
        $param1 = $params[0]->getName(); // $content_items
        $param2 = $params[1]->getName(); // $processor_callback  
        $param3 = $params[2]->getName(); // $chunk_size
        
        if ($param1 === 'content_items' && $param2 === 'processor_callback' && $param3 === 'chunk_size') {
            echo "✓ Parameter order is correct: ($param1, $param2, $param3)\n";
        } else {
            echo "✗ Parameter order is incorrect: ($param1, $param2, $param3)\n";
            exit(1);
        }
        
        // Check that chunk_size has a default value
        if ($params[2]->isOptional()) {
            echo "✓ chunk_size parameter is optional (has default value)\n";
        } else {
            echo "✗ chunk_size parameter should be optional\n";
            exit(1);
        }
    } else {
        echo "✗ Method should have 3 parameters, got " . count($params) . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Failed to inspect method: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Method can be called with correct parameter order
try {
    $test_content = array('item1', 'item2', 'item3');
    $test_callback = function($item) { return true; };
    
    $result = $contentStream->process_content_in_chunks($test_content, $test_callback);
    echo "✓ Method can be called with correct parameter order\n";
    echo "✓ Method returned result: " . print_r($result, true);
} catch (Exception $e) {
    echo "✗ Failed to call method: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 All tests passed! PHP function signature fix is working correctly.\n";
echo "The deprecated warning should now be resolved.\n";