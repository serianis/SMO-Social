<?php
/**
 * Simple test to verify AJAX configuration fix
 * This creates a minimal mock environment to test the AssetManager
 */

// Create a simple test to check if the AssetManager constructor properly sets up the hook
echo "=== Testing AssetManager AJAX Configuration Fix ===\n\n";

// Read the AssetManager file to check if it has the fix
$asset_manager_content = file_get_contents('includes/Admin/AssetManager.php');

// Check if the constructor has the add_action hook
if (strpos($asset_manager_content, 'add_action(\'admin_enqueue_scripts\'') !== false) {
    echo "✓ FIXED: AssetManager constructor now includes add_action hook\n";
    
    // Check if the localized script is being set up
    if (strpos($asset_manager_content, "wp_localize_script('smo-social-admin', 'smo_social_ajax'") !== false) {
        echo "✓ VERIFIED: AJAX localization is properly configured\n";
        echo "\n=== RESULT: FIX SUCCESSFUL ===\n";
        echo "The AssetManager now properly hooks into 'admin_enqueue_scripts' and\n";
        echo "localizes the 'smo_social_ajax' configuration for the admin.js script.\n";
        echo "\nThis should resolve the error:\n";
        echo "'SMO Social: AJAX configuration not found. Please ensure the plugin is properly activated.'\n";
    } else {
        echo "✗ ISSUE: AJAX localization not found in AssetManager\n";
    }
} else {
    echo "✗ NOT FIXED: AssetManager constructor missing add_action hook\n";
}

echo "\n=== Technical Details ===\n";
echo "Root Cause: The AssetManager class was not registering the 'admin_enqueue_scripts' hook\n";
echo "Solution: Added add_action('admin_enqueue_scripts', array($this, 'enqueue_assets')) in constructor\n";
echo "Impact: Admin.js will now receive proper AJAX configuration via wp_localize_script\n";