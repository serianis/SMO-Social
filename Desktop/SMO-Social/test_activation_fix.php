<?php
/**
 * Test script to verify the plugin activation fix
 * This simulates the plugin activation process without needing WordPress
 */

// Define constants to simulate WordPress environment
define('ABSPATH', __DIR__ . '/');
define('SMO_SOCIAL_STANDALONE', true);

// Capture any errors or exceptions
$errors = [];
set_error_handler(function($severity, $message, $file, $line) use (&$errors) {
    $errors[] = "Error: $message in $file on line $line";
});

set_exception_handler(function($exception) use (&$errors) {
    $errors[] = "Exception: " . $exception->getMessage();
});

try {
    echo "Testing SMO Social Plugin Activation Fix\n";
    echo "==========================================\n\n";
    
    // Include the main plugin file
    echo "1. Loading main plugin file...\n";
    require_once __DIR__ . '/smo-social.php';
    
    echo "✓ Plugin file loaded successfully\n\n";
    
    // Test if the activation function exists
    echo "2. Testing activation function existence...\n";
    if (function_exists('smo_social_activate')) {
        echo "✓ smo_social_activate() function exists\n\n";
        
        // Test calling the activation function (with error handling)
        echo "3. Testing activation function execution...\n";
        try {
            // Mock WordPress functions that might be needed
            if (!function_exists('add_option')) {
                function add_option($name, $value) {
                    echo "Mock: add_option($name, " . print_r($value, true) . ")\n";
                    return true;
                }
            }
            
            if (!function_exists('wp_mkdir_p')) {
                function wp_mkdir_p($path) {
                    echo "Mock: wp_mkdir_p($path)\n";
                    return true;
                }
            }
            
            if (!function_exists('current_time')) {
                function current_time($type) {
                    return date('Y-m-d H:i:s');
                }
            }
            
            if (!function_exists('flush_rewrite_rules')) {
                function flush_rewrite_rules() {
                    echo "Mock: flush_rewrite_rules()\n";
                }
            }
            
            if (!function_exists('wp_clear_scheduled_hook')) {
                function wp_clear_scheduled_hook($hook) {
                    echo "Mock: wp_clear_scheduled_hook($hook)\n";
                }
            }
            
            // Call the activation function
            smo_social_activate();
            echo "✓ Activation function executed without fatal errors\n\n";
            
        } catch (Exception $e) {
            echo "✗ Exception during activation: " . $e->getMessage() . "\n";
            $errors[] = "Activation exception: " . $e->getMessage();
        }
        
    } else {
        echo "✗ smo_social_activate() function not found\n";
        $errors[] = "Activation function missing";
    }
    
    // Test deactivation function
    echo "4. Testing deactivation function...\n";
    if (function_exists('smo_social_deactivate')) {
        try {
            smo_social_deactivate();
            echo "✓ Deactivation function executed without fatal errors\n\n";
        } catch (Exception $e) {
            echo "✗ Exception during deactivation: " . $e->getMessage() . "\n";
            $errors[] = "Deactivation exception: " . $e->getMessage();
        }
    } else {
        echo "✗ smo_social_deactivate() function not found\n";
    }
    
    // Test uninstall function
    echo "5. Testing uninstall function...\n";
    if (function_exists('smo_social_uninstall')) {
        try {
            smo_social_uninstall();
            echo "✓ Uninstall function executed without fatal errors\n\n";
        } catch (Exception $e) {
            echo "✗ Exception during uninstall: " . $e->getMessage() . "\n";
            $errors[] = "Uninstall exception: " . $e->getMessage();
        }
    } else {
        echo "✗ smo_social_uninstall() function not found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Fatal error during plugin loading: " . $e->getMessage() . "\n";
    $errors[] = "Plugin loading fatal error: " . $e->getMessage();
}

// Report results
echo "==========================================\n";
if (empty($errors)) {
    echo "🎉 SUCCESS: All tests passed! The plugin activation fix is working correctly.\n";
    echo "The fatal error 'Call to undefined method DatabaseManager::activate()' has been resolved.\n";
} else {
    echo "❌ ERRORS DETECTED:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";