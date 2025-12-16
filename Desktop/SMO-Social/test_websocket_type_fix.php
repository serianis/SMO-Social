<?php
/**
 * Test file to validate WebSocket type fix
 * This file tests the Socket type compatibility fixes
 */

// Include the type stubs to make Socket class available
require_once 'includes/type-stubs.php';

// Test Socket class instantiation
echo "Testing Socket class availability...\n";
if (class_exists('Socket')) {
    echo "✓ Socket class is available\n";
    $socket = new Socket();
    echo "✓ Socket object created successfully\n";
    
    // Test close method
    if (method_exists($socket, 'close')) {
        echo "✓ Socket::close() method is available\n";
        $result = $socket->close();
        echo "✓ Socket::close() executed, result: " . ($result ? 'true' : 'false') . "\n";
    } else {
        echo "✗ Socket::close() method not found\n";
    }
} else {
    echo "✗ Socket class not found\n";
}

// Test resource socket handling
echo "\nTesting resource socket handling...\n";
if (function_exists('socket_create')) {
    $resource_socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($resource_socket) {
        echo "✓ Resource socket created successfully\n";
        echo "✓ Resource type: " . gettype($resource_socket) . "\n";
        echo "✓ Is resource: " . (is_resource($resource_socket) ? 'true' : 'false') . "\n";
        
        // Test socket_close
        if (function_exists('socket_close')) {
            echo "✓ socket_close function available\n";
            // Note: We won't actually close it to avoid issues
        }
    } else {
        echo "✗ Failed to create resource socket\n";
    }
} else {
    echo "✗ socket_create function not available\n";
}

echo "\nTest completed.\n";