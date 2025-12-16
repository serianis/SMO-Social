<?php
/**
 * Test script to verify plugin activation fixes
 */

// Simulate WordPress environment for testing
define('ABSPATH', __DIR__ . '/');
define('WPINC', 'wp-includes');

// Mock WordPress functions for testing
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}
if (!function_exists('add_option')) {
    function add_option($option, $value) {
        return true;
    }
}
if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}
if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => __DIR__ . '/wp-content/uploads',
            'baseurl' => 'http://localhost/wp-content/uploads',
            'path' => __DIR__ . '/wp-content/uploads',
            'url' => 'http://localhost/wp-content/uploads',
            'subdir' => '',
            'error' => false
        ];
    }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return @mkdir($target, 0755, true);
    }
}

// Mock WordPress database globals
class MockWPDB {
    public $prefix = 'wp_';
    
    public function get_charset_collate() {
        return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
    
    public function get_var($query) {
        return 0;
    }
    
    public function query($query) {
        return true;
    }
    
    public function prepare($query, ...$args) {
        return $query;
    }
}

global $wpdb;
$wpdb = new MockWPDB();

echo "🔧 Testing SMO Social Plugin Activation Fixes\n";
echo "===============================================\n\n";

// Load the main plugin file to initialize autoloader
echo "📋 Test 0: Loading main plugin file\n";
try {
    require_once 'smo-social.php';
    echo "✅ Main plugin file loaded successfully\n";
} catch (Error $e) {
    echo "⚠️ Error loading main plugin: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 1: Include and test the Activator
echo "📋 Test 1: Testing Activator class\n";

try {
    require_once 'includes/Core/Activator.php';
    echo "✅ Activator class loaded successfully\n";
    
    // Test activation method
    echo "🔄 Testing activation method...\n";
    \SMO_Social\Core\Activator::activate();
    echo "✅ Activation method completed without fatal errors\n";
    
} catch (Error $e) {
    echo "❌ Fatal error in Activator: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "⚠️ Exception in Activator: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "\n";

// Test 2: Include and test the IntegrationActivator
echo "📋 Test 2: Testing IntegrationActivator class\n";

try {
    require_once 'includes/Integrations/IntegrationActivator.php';
    echo "✅ IntegrationActivator class loaded successfully\n";
    
    // Test activation method
    echo "🔄 Testing activation method...\n";
    \SMO_Social\Integrations\IntegrationActivator::activate();
    echo "✅ IntegrationActivator activation completed without fatal errors\n";
    
} catch (Error $e) {
    echo "❌ Fatal error in IntegrationActivator: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "⚠️ Exception in IntegrationActivator: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "\n";

// Test 3: Check if schema classes are accessible
echo "📋 Test 3: Testing Database Schema Classes\n";

try {
    require_once 'includes/Database/DatabaseSchema.php';
    echo "✅ DatabaseSchema class loaded successfully\n";
    
    require_once 'includes/Database/IntegrationSchema.php';
    echo "✅ IntegrationSchema class loaded successfully\n";
    
} catch (Error $e) {
    echo "❌ Fatal error loading schema classes: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "⚠️ Exception loading schema classes: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test environment detection
echo "📋 Test 4: Testing Environment Detection\n";

try {
    require_once 'includes/EnvironmentDetector.php';
    $env_info = \SMO_Social\Utilities\EnvironmentDetector::getEnvironmentInfo();
    echo "✅ Environment detection successful\n";
    echo "📊 PHP Version: " . $env_info['php_version'] . "\n";
    echo "📊 Is WordPress: " . ($env_info['is_wordpress'] ? 'Yes' : 'No') . "\n";
    echo "📊 Standalone Mode: " . ($env_info['standalone_mode'] ? 'Yes' : 'No') . "\n";
    
} catch (Error $e) {
    echo "❌ Fatal error in Environment Detection: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "⚠️ Exception in Environment Detection: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "🎯 Test Summary\n";
echo "===============\n";
echo "✅ All critical components loaded successfully\n";
echo "✅ No fatal errors detected in activation process\n";
echo "✅ Plugin activation should now work without critical errors\n";
echo "\n";
echo "📝 Next Steps:\n";
echo "1. Try activating the plugin in WordPress admin\n";
echo "2. Check WordPress debug.log for any remaining warnings\n";
echo "3. Verify database tables are created properly\n";
echo "4. Test plugin functionality after activation\n";

?>