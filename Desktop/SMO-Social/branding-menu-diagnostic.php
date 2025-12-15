<?php
/**
 * SMO Social - White Label Settings Menu Diagnostic Script
 * 
 * This script diagnoses why the White Label Settings tab is missing from the menu
 */

// If running in WordPress context
if (defined('ABSPATH')) {
    // WordPress environment
    add_action('admin_init', 'smo_diagnostic_branding_menu');
} else {
    // Standalone mode - simulate WordPress functions
    echo "=== SMO Social White Label Settings Menu Diagnostic ===\n";
    echo "Running in standalone mode\n";
    smo_diagnostic_branding_menu();
}

function smo_diagnostic_branding_menu() {
    echo "\n=== DIAGNOSTIC RESULTS ===\n";
    
    // 1. Check if BrandingManager class exists
    $branding_manager_exists = class_exists('\SMO_Social\WhiteLabel\BrandingManager');
    echo "1. BrandingManager class exists: " . ($branding_manager_exists ? "YES" : "NO") . "\n";
    
    if (!$branding_manager_exists) {
        echo "   ERROR: BrandingManager class not found!\n";
        return;
    }
    
    // 2. Check if BrandingManager is instantiated
    global $wpdb;
    $branding_instantiated = false;
    try {
        // Try to get existing instance or create new one
        if (class_exists('\SMO_Social\WhiteLabel\BrandingManager')) {
            // Check if already instantiated by looking for the admin_menu hook
            $hooks = array();
            if (function_exists('has_action')) {
                $hooks['admin_menu'] = has_action('admin_menu', array('\SMO_Social\WhiteLabel\BrandingManager', 'add_branding_menu'));
            }
            
            echo "2. BrandingManager admin_menu hook registered: " . ($hooks['admin_menu'] ? "YES (priority: " . $hooks['admin_menu'] . ")" : "NO") . "\n";
            
            if (!$hooks['admin_menu']) {
                echo "   ISSUE: BrandingManager admin_menu hook not registered!\n";
                echo "   This means the BrandingManager was not properly instantiated.\n";
            }
        }
    } catch (Exception $e) {
        echo "   ERROR checking instantiation: " . $e->getMessage() . "\n";
    }
    
    // 3. Check MenuManager registration
    $menu_manager_exists = class_exists('\SMO_Social\Admin\MenuManager');
    echo "3. MenuManager class exists: " . ($menu_manager_exists ? "YES" : "NO") . "\n";
    
    if ($menu_manager_exists) {
        $menu_manager_hook = has_action('admin_menu', array('\SMO_Social\Admin\MenuManager', 'register_menus'));
        echo "4. MenuManager admin_menu hook registered: " . ($menu_manager_hook ? "YES (priority: " . $menu_manager_hook . ")" : "NO") . "\n";
    }
    
    // 5. Check for menu conflicts
    echo "5. Menu Structure Analysis:\n";
    echo "   - MenuManager creates submenu pages under 'smo-social'\n";
    echo "   - BrandingManager creates main menu page with slug 'smo-branding'\n";
    echo "   - This creates a structural conflict!\n";
    
    // 6. Check user capabilities
    if (function_exists('current_user_can')) {
        $user_can_manage_options = current_user_can('manage_options');
        echo "6. Current user can manage_options: " . ($user_can_manage_options ? "YES" : "NO") . "\n";
        
        if (!$user_can_manage_options) {
            echo "   ISSUE: User doesn't have manage_options capability!\n";
        }
    }
    
    // 7. Check if menu page already exists
    if (function_exists('get_option')) {
        $existing_menu = get_option('smo_branding_menu_registered');
        echo "7. Branding menu previously registered: " . ($existing_menu ? "YES" : "NO") . "\n";
    }
    
    echo "\n=== ROOT CAUSE ANALYSIS ===\n";
    echo "The BrandingManager is designed to create a standalone main menu page,\n";
    echo "but the MenuManager creates all other pages as submenus under 'smo-social'.\n";
    echo "This inconsistency causes the White Label Settings to appear as a separate\n";
    echo "main menu item instead of a submenu under SMO Social.\n";
    
    echo "\n=== RECOMMENDED FIX ===\n";
    echo "Convert BrandingManager to create a submenu page under 'smo-social' instead\n";
    echo "of a standalone main menu page.\n";
    
    echo "\n=== DIAGNOSTIC COMPLETE ===\n";
}