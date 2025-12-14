<?php
/**
 * Setup script for memory monitoring database tables
 */

// Include WordPress test stubs
require_once 'includes/wordpress-test-stubs.php';
require_once 'includes/wordpress-functions.php';

// Mock database functions
class MockWPDB {
    public $prefix = 'wp_';
    public $charset_collate = 'utf8mb4_unicode_ci';

    public function get_charset_collate() {
        return 'utf8mb4_unicode_ci';
    }
}

global $wpdb;
$wpdb = new MockWPDB();

// Mock dbDelta function
if (!function_exists('dbDelta')) {
    function dbDelta($sql) {
        echo "Creating table with SQL: " . substr($sql, 0, 100) . "...\n";
        return array();
    }
}

// Mock WordPress functions
if (!function_exists('add_action')) {
    function add_action($hook, $callback) {
        // Mock implementation
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        // Mock implementation
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook) {
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook) {
        // Mock implementation
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook) {
        // Mock implementation
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value) {
        // Mock implementation
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return array(
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.com/uploads'
        );
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($path) {
        return mkdir($path, 0755, true);
    }
}

// Include required classes
require_once 'includes/Core/DatabaseSchema.php';
require_once 'includes/Core/MemoryMonitorConfig.php';
require_once 'includes/Core/MemoryAlertSystem.php';
require_once 'includes/Core/Logger.php';

echo "Setting up memory monitoring database tables...\n";

// Create the enhanced tables
try {
    SMO_Social\Core\DatabaseSchema::create_enhanced_tables();
    echo "Memory monitoring database tables created successfully!\n";
} catch (Exception $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}

echo "Setup complete.\n";