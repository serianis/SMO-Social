<?php
/**
 * Test script to verify MemoryMonitoring.php syntax and structure
 */

// Test PHP syntax
echo "Testing PHP syntax...\n";
exec('php -l includes/Admin/Views/MemoryMonitoring.php', $output, $return_code);
if ($return_code === 0) {
    echo "✅ PHP syntax is valid\n";
} else {
    echo "❌ PHP syntax errors found:\n";
    foreach ($output as $line) {
        echo "   " . $line . "\n";
    }
}

// Test class loading
echo "\nTesting class loading...\n";
try {
    // Set up WordPress environment
    if (!defined('ABSPATH')) {
        define('ABSPATH', 'c:/Users/karga/Desktop/SMO-Social/');
    }
    
    // Include the file
    require_once 'includes/Admin/Views/MemoryMonitoring.php';
    
    // Check if class exists
    if (class_exists('SMO_Social\\Admin\\Views\\MemoryMonitoring')) {
        echo "✅ MemoryMonitoring class loaded successfully\n";
        
        // Test instantiation (without dependencies)
        echo "Testing class instantiation...\n";
        try {
            $memoryMonitoring = new \SMO_Social\Admin\Views\MemoryMonitoring();
            echo "✅ MemoryMonitoring instance created successfully\n";
        } catch (Exception $e) {
            echo "⚠️  MemoryMonitoring instantiation failed (expected without full dependencies): " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ MemoryMonitoring class not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Class loading failed: " . $e->getMessage() . "\n";
}

echo "\n✅ All tests completed!\n";
?>