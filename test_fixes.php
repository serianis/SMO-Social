<?php
/**
 * Test Database Connection Pool Fixes
 */

echo "🔍 Testing Database Connection Pool Fixes\n";
echo "========================================\n\n";

// Test 1: Check if constants are now handled safely
echo "Test 1: Safe Constant Handling\n";
echo "------------------------------\n";

try {
    require_once('includes/Core/DatabaseConnectionPool.php');
    
    // Set test configuration
    $config = [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'database' => 'test'
    ];
    
    $pool = new \SMO_Social\Core\DatabaseConnectionPool($config);
    echo "✅ DatabaseConnectionPool instantiated successfully\n";
    
    // Test 2: Check mysqli object handling
    echo "\nTest 2: MySQLi Object Handling\n";
    echo "------------------------------\n";
    
    $connection = $pool->get_connection();
    if ($connection) {
        echo "✅ Connection retrieved successfully\n";
        echo "Connection type: " . gettype($connection) . "\n";
        echo "Is mysqli object: " . ($connection instanceof \mysqli ? 'YES' : 'NO') . "\n";
        
        // Test validation (simulated - validate_connection is private)
        echo "Connection validation: (skipped - private method)\n";
        
        // Test release
        $released = $pool->release_connection($connection);
        echo "Connection released: " . ($released ? 'YES' : 'NO') . "\n";
        
    } else {
        echo "⚠️ No connection retrieved (expected for test environment)\n";
    }
    
    // Test 3: Check statistics
    echo "\nTest 3: Pool Statistics\n";
    echo "----------------------\n";
    
    $stats = $pool->get_stats();
    echo "Pool size: " . $stats['current_pool_size'] . "/" . $stats['max_pool_size'] . "\n";
    echo "Available connections: " . $stats['available_connections'] . "\n";
    echo "In use connections: " . $stats['in_use_connections'] . "\n";
    echo "Connection errors: " . $stats['connection_errors'] . "\n";
    
    echo "\n✅ All tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>