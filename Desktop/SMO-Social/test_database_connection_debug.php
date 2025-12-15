<?php
/**
 * Database Connection Debug Test Script
 * 
 * This script tests the DatabaseConnectionPool to identify the root causes
 * of the undefined constant and type compatibility issues.
 */

// Start output buffering to capture any debug logs
ob_start();

// Load WordPress if available
if (file_exists('wp-config.php')) {
    require_once('wp-config.php');
    echo "✅ WordPress loaded successfully\n";
} else {
    echo "⚠️ WordPress not found, using fallback values\n";
}

// Set fallback values for testing - use environment variables
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'wordpress');

echo "🔍 Testing Database Configuration:\n";
echo "=================================\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
echo "DB_PASSWORD: " . (defined('DB_PASSWORD') ? '***' : 'NOT DEFINED') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";

// Test mysqli version and type compatibility
echo "\n🔍 Testing MySQLi Compatibility:\n";
echo "=================================\n";

$test_connection = mysqli_init();
echo "mysqli_init() returns: " . gettype($test_connection) . "\n";
if (is_object($test_connection)) {
    echo "mysqli_init() returns object of class: " . get_class($test_connection) . "\n";
} elseif (is_resource($test_connection)) {
    echo "mysqli_init() returns resource of type: " . get_resource_type($test_connection) . "\n";
}

// Test connection with fallback values
try {
    $test_conn = mysqli_init();
    mysqli_options($test_conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
    $connected = mysqli_real_connect(
        $test_conn,
        DB_HOST,
        DB_USER,
        DB_PASSWORD,
        DB_NAME
    );
    
    echo "Test connection result: " . ($connected ? 'SUCCESS' : 'FAILED') . "\n";
    if (!$connected) {
        echo "Connection error: " . mysqli_connect_error() . "\n";
    }
    
    if ($connected) {
        echo "Connection type after connect: " . gettype($test_conn) . "\n";
        if (is_object($test_conn)) {
            echo "Object class: " . get_class($test_conn) . "\n";
        } elseif (is_resource($test_conn)) {
            echo "Resource type: " . get_resource_type($test_conn) . "\n";
        }
        
        // Test connection using modern approach (mysqli_ping deprecated in PHP 8.1+)
        if (is_object($test_conn)) {
            // Use SELECT 1 query instead of deprecated ping() method
            $connection_test = mysqli_query($test_conn, "SELECT 1");
            $ping_result = ($connection_test !== false);
            echo "Connection test (SELECT 1) result: " . ($ping_result ? 'SUCCESS' : 'FAILED') . "\n";
            if (!$ping_result) {
                echo "Connection test error: " . mysqli_error($test_conn) . "\n";
            }
            // Clean up the result set
            if ($connection_test) {
                mysqli_free_result($connection_test);
            }
        } else {
            echo "Connection test skipped - invalid connection object\n";
            $ping_result = false;
        }
        
        mysqli_close($test_conn);
    }
    
} catch (Exception $e) {
    echo "Connection test failed: " . $e->getMessage() . "\n";
}

// Test DatabaseConnectionPool instantiation
echo "\n🔍 Testing DatabaseConnectionPool:\n";
echo "===================================\n";

try {
    require_once('includes/Core/DatabaseConnectionPool.php');
    
    $pool = new \SMO_Social\Core\DatabaseConnectionPool();
    echo "✅ DatabaseConnectionPool instantiated successfully\n";
    
    $stats = $pool->get_stats();
    echo "Pool stats: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "❌ DatabaseConnectionPool test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Get captured debug logs
$debug_output = ob_get_clean();
echo "\n📋 CAPTURED DEBUG OUTPUT:\n";
echo "=========================\n";
echo $debug_output;

// Save debug output to file
file_put_contents('database_connection_debug.log', $debug_output);
echo "\n💾 Debug output saved to: database_connection_debug.log\n";
?>