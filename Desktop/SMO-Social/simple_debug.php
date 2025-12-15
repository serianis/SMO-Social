<?php
/**
 * Simple Database Configuration Debug
 */

echo "🔍 PHP and MySQLi Version Test\n";
echo "==============================\n";
echo "PHP Version: " . PHP_VERSION . "\n";

// Test mysqli functions availability
echo "MySQLi functions available:\n";
echo "- mysqli_init: " . (function_exists('mysqli_init') ? 'YES' : 'NO') . "\n";
echo "- mysqli_real_connect: " . (function_exists('mysqli_real_connect') ? 'YES' : 'NO') . "\n";
echo "- mysqli->ping() (modern): " . (version_compare(PHP_VERSION, '8.1', '>=') ? 'RECOMMENDED' : 'AVAILABLE') . "\n";
echo "- mysqli_ping (deprecated): " . (version_compare(PHP_VERSION, '8.1', '<') ? 'YES' : 'NO') . "\n";
echo "- mysqli_close: " . (function_exists('mysqli_close') ? 'YES' : 'NO') . "\n";

// Test mysqli_init return type
echo "\n🔍 MySQLi Init Return Type:\n";
echo "===========================\n";
$test_conn = mysqli_init();
echo "mysqli_init() returns: " . gettype($test_conn) . "\n";
if (is_object($test_conn)) {
    echo "Class: " . get_class($test_conn) . "\n";
    echo "Is mysqli instance: " . ($test_conn instanceof mysqli ? 'YES' : 'NO') . "\n";
} elseif (is_resource($test_conn)) {
    echo "Resource type: " . get_resource_type($test_conn) . "\n";
}

// Test constants
echo "\n🔍 Database Constants:\n";
echo "======================\n";
echo "DB_HOST defined: " . (defined('DB_HOST') ? 'YES (' . DB_HOST . ')' : 'NO') . "\n";
echo "DB_USER defined: " . (defined('DB_USER') ? 'YES (' . DB_USER . ')' : 'NO') . "\n";
echo "DB_PASSWORD defined: " . (defined('DB_PASSWORD') ? 'YES' : 'NO') . "\n";
echo "DB_NAME defined: " . (defined('DB_NAME') ? 'YES (' . DB_NAME . ')' : 'NO') . "\n";

// Test WordPress $wpdb
echo "\n🔍 WordPress \$wpdb:\n";
echo "===================\n";
if (isset($wpdb)) {
    echo "\$wpdb available: YES\n";
    echo "\$wpdb->dbhost: " . ($wpdb->dbhost ?? 'NOT SET') . "\n";
    echo "\$wpdb->dbuser: " . ($wpdb->dbuser ?? 'NOT SET') . "\n";
    echo "\$wpdb->dbpassword: " . (empty($wpdb->dbpassword) ? 'EMPTY' : 'SET') . "\n";
    echo "\$wpdb->dbname: " . ($wpdb->dbname ?? 'NOT SET') . "\n";
} else {
    echo "\$wpdb available: NO\n";
}

echo "\n✅ Debug complete!\n";
?>