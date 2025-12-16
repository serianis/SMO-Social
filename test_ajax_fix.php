<?php
/**
 * Test script to verify the AJAX fix for 403 Forbidden error
 * This script tests the smo_get_platform_status AJAX endpoint
 */

// Include WordPress environment
if (!defined('ABSPATH')) {
    require_once 'wp-load.php';
}

// Test the AJAX endpoint
function test_platform_status_ajax() {
    // Simulate the AJAX request that was failing
    $_POST['action'] = 'smo_get_platform_status';
    $_POST['nonce'] = wp_create_nonce('smo_social_nonce');
    
    // Check if user has proper permissions
    if (!current_user_can('manage_options')) {
        echo "❌ Test Failed: User does not have manage_options capability\n";
        return false;
    }
    
    // Include the AJAX handler
    require_once 'includes/Admin/Ajax/PlatformAjax.php';
    require_once 'includes/Admin/Ajax/BaseAjaxHandler.php';
    
    $platform_ajax = new \SMO_Social\Admin\Ajax\PlatformAjax();
    $platform_ajax->register();
    
    // Test nonce verification using the new public test method
    // This eliminates the deprecated setAccessible usage
    $result = $platform_ajax->test_verify_request(true);
    
    if ($result) {
        echo "✅ Test Passed: Nonce verification successful\n";
        echo "✅ Test Passed: smo_get_platform_status AJAX endpoint should now work\n";
        return true;
    } else {
        echo "❌ Test Failed: Nonce verification failed\n";
        return false;
    }
}

// Run the test
echo "Testing AJAX fix for 403 Forbidden error...\n";
echo "==========================================\n\n";

if (test_platform_status_ajax()) {
    echo "\n🎉 Fix verified! The 403 Forbidden error should be resolved.\n";
    echo "\nWhat was fixed:\n";
    echo "- Changed nonce action in BaseAjaxHandler from 'smo-social-admin-nonce' to 'smo_social_nonce'\n";
    echo "- This matches the nonce being sent from the JavaScript (admin.js)\n";
    echo "- All AJAX endpoints extending BaseAjaxHandler now use the correct nonce\n";
} else {
    echo "\n❌ Fix verification failed. Please check the implementation.\n";
}
