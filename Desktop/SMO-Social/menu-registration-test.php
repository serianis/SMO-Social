<?php
/**
 * SMO Social - Menu Registration Test Script
 * 
 * This script tests the menu registration process to identify the issue
 */

// Test menu registration directly
function test_menu_registration() {
    echo "=== TESTING MENU REGISTRATION ===\n\n";
    
    // Test 1: Direct add_menu_page call
    echo "1. Testing direct add_menu_page call...\n";
    if (function_exists('add_menu_page')) {
        $result = add_menu_page(
            'Test White Label',
            'White Label',
            'manage_options',
            'smo-test-branding',
            function() { echo "<h1>Test Branding Page</h1>"; },
            'dashicons-admin-generic',
            30
        );
        echo "   Result: " . ($result ? "SUCCESS - $result" : "FAILED") . "\n";
    } else {
        echo "   add_menu_page function not available\n";
    }
    
    // Test 2: Check global submenu
    global $submenu;
    echo "2. Checking global \$submenu...\n";
    if (isset($submenu)) {
        echo "   Current submenu keys: " . implode(', ', array_keys($submenu)) . "\n";
        if (isset($submenu['smo-test-branding'])) {
            echo "   Test menu found in submenu!\n";
        } else {
            echo "   Test menu NOT found in submenu\n";
        }
    } else {
        echo "   \$submenu not available\n";
    }
    
    // Test 3: Check user capabilities
    echo "3. Testing user capabilities...\n";
    if (function_exists('current_user_can')) {
        $can_manage = current_user_can('manage_options');
        echo "   User can manage_options: " . ($can_manage ? "YES" : "NO") . "\n";
        if (!$can_manage) {
            echo "   *** THIS IS THE ISSUE! User lacks manage_options capability ***\n";
        }
    } else {
        echo "   current_user_can function not available\n";
    }
    
    // Test 4: Check hook system
    echo "4. Testing hook system...\n";
    if (function_exists('has_action')) {
        $admin_menu_hooks = has_action('admin_menu');
        echo "   admin_menu hooks registered: " . ($admin_menu_hooks ? "YES" : "NO") . "\n";
    } else {
        echo "   has_action function not available\n";
    }
    
    echo "\n=== MENU REGISTRATION TEST COMPLETE ===\n";
}

// Load WordPress if available
if (defined('ABSPATH')) {
    add_action('admin_init', 'test_menu_registration');
} else {
    // Standalone mode - simulate test
    echo "Running in standalone mode - simulating tests...\n\n";
    test_menu_registration();
    
    echo "\n=== RECOMMENDATION ===\n";
    echo "Based on the analysis, the most likely cause is:\n";
    echo "1. Menu registration conflict between MenuManager and BrandingManager\n";
    echo "2. User permissions issue (lack of manage_options capability)\n";
    echo "\nTo fix this, we need to:\n";
    echo "A) Convert BrandingManager to use submenu instead of main menu\n";
    echo "B) Ensure proper user permissions\n";
    echo "C) Fix hook priority conflicts\n";
}