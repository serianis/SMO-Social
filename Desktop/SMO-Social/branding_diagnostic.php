<?php
/**
 * SMO Social Branding Manager Diagnostic Script
 * 
 * This script diagnoses and fixes issues with the BrandingManager 403 Forbidden error.
 * 
 * Usage: Upload to your WordPress root directory and access via browser
 * URL: https://yoursite.com/branding_diagnostic.php
 */

// Prevent direct access in WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Load WordPress
require_once(ABSPATH . 'wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>SMO Social Branding Manager Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #f0f8f0; padding: 10px; border-radius: 5px; }
        .error { color: red; background: #f8f0f0; padding: 10px; border-radius: 5px; }
        .warning { color: orange; background: #fff8f0; padding: 10px; border-radius: 5px; }
        .info { color: blue; background: #f0f8ff; padding: 10px; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>SMO Social Branding Manager Diagnostic</h1>
    <p>Running comprehensive diagnostics for the BrandingManager 403 Forbidden error...</p>
    
    <?php
    // Start diagnostics
    $results = array();
    
    // 1. Check if SMO Social plugin is active
    echo "<h2>1. Plugin Status Check</h2>";
    if (class_exists('SMO_Social\\Core\\Plugin')) {
        echo '<div class="success">✓ SMO Social plugin is loaded</div>';
        $results['plugin_loaded'] = true;
    } else {
        echo '<div class="error">✗ SMO Social plugin is not loaded</div>';
        $results['plugin_loaded'] = false;
    }
    
    // 2. Check if BrandingManager class exists
    echo "<h2>2. BrandingManager Class Check</h2>";
    if (class_exists('SMO_Social\\WhiteLabel\\BrandingManager')) {
        echo '<div class="success">✓ BrandingManager class exists</div>';
        $results['branding_class_exists'] = true;
        
        // Try to instantiate
        try {
            $branding_manager = new \SMO_Social\WhiteLabel\BrandingManager();
            echo '<div class="success">✓ BrandingManager instantiated successfully</div>';
            $results['branding_instantiated'] = true;
        } catch (Exception $e) {
            echo '<div class="error">✗ Failed to instantiate BrandingManager: ' . $e->getMessage() . '</div>';
            $results['branding_instantiated'] = false;
        }
    } else {
        echo '<div class="error">✗ BrandingManager class not found</div>';
        $results['branding_class_exists'] = false;
    }
    
    // 3. Check admin menu registration
    echo "<h2>3. Admin Menu Registration Check</h2>";
    global $submenu;
    
    if (isset($submenu['smo-social'])) {
        echo '<div class="info">Found SMO Social submenu</div>';
        $smo_submenu = $submenu['smo-social'];
        
        $branding_found = false;
        foreach ($smo_submenu as $item) {
            if (strpos($item[2], 'smo-branding') !== false) {
                echo '<div class="success">✓ Branding menu found: ' . $item[0] . '</div>';
                echo '<div class="info">Menu slug: ' . $item[2] . '</div>';
                $branding_found = true;
                $results['branding_menu_registered'] = true;
                break;
            }
        }
        
        if (!$branding_found) {
            echo '<div class="warning">⚠ Branding menu not found in submenu</div>';
            echo '<div class="info">Available submenu items:</div>';
            echo '<ul>';
            foreach ($smo_submenu as $item) {
                echo '<li>' . $item[0] . ' (' . $item[2] . ')</li>';
            }
            echo '</ul>';
            $results['branding_menu_registered'] = false;
        }
    } else {
        echo '<div class="warning">⚠ SMO Social main menu not found</div>';
        $results['branding_menu_registered'] = false;
    }
    
    // 4. Check database tables
    echo "<h2>4. Database Tables Check</h2>";
    global $wpdb;
    
    $branding_table = $wpdb->prefix . 'smo_branding_settings';
    $license_table = $wpdb->prefix . 'smo_licenses';
    
    $branding_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$branding_table'") == $branding_table;
    $license_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$license_table'") == $license_table;
    
    if ($branding_table_exists) {
        echo '<div class="success">✓ Branding settings table exists: ' . $branding_table . '</div>';
        $results['branding_table_exists'] = true;
    } else {
        echo '<div class="error">✗ Branding settings table missing: ' . $branding_table . '</div>';
        $results['branding_table_exists'] = false;
    }
    
    if ($license_table_exists) {
        echo '<div class="success">✓ License table exists: ' . $license_table . '</div>';
        $results['license_table_exists'] = true;
    } else {
        echo '<div class="warning">⚠ License table missing: ' . $license_table . '</div>';
        $results['license_table_exists'] = false;
    }
    
    // 5. Check user permissions
    echo "<h2>5. User Permissions Check</h2>";
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        echo '<div class="info">Current user: ' . $current_user->user_login . ' (' . $current_user->user_email . ')</div>';
        
        if (current_user_can('manage_options')) {
            echo '<div class="success">✓ User has manage_options capability</div>';
            $results['user_can_manage_options'] = true;
        } else {
            echo '<div class="error">✗ User lacks manage_options capability</div>';
            $results['user_can_manage_options'] = false;
        }
        
        // Check all user capabilities
        echo '<div class="info">User capabilities:</div>';
        echo '<ul>';
        foreach ($current_user->allcaps as $cap => $granted) {
            if ($granted && strpos($cap, 'manage') !== false) {
                echo '<li>' . $cap . ': ' . ($granted ? 'Yes' : 'No') . '</li>';
            }
        }
        echo '</ul>';
    } else {
        echo '<div class="error">✗ No user logged in</div>';
        $results['user_logged_in'] = false;
    }
    
    // 6. Check WordPress version and environment
    echo "<h2>6. WordPress Environment Check</h2>";
    $wp_version = get_bloginfo('version');
    echo '<div class="info">WordPress version: ' . $wp_version . '</div>';
    
    if (version_compare($wp_version, '5.0', '>=')) {
        echo '<div class="success">✓ WordPress version meets requirements (5.0+)</div>';
        $results['wp_version_ok'] = true;
    } else {
        echo '<div class="error">✗ WordPress version too old (requires 5.0+)</div>';
        $results['wp_version_ok'] = false;
    }
    
    // 7. Generate action plan
    echo "<h2>7. Action Plan & Fixes</h2>";
    
    if (!$results['branding_class_exists']) {
        echo '<div class="error">Fix: BrandingManager class missing. Check if includes/WhiteLabel/BrandingManager.php exists.</div>';
    }
    
    if (!$results['branding_instantiated']) {
        echo '<div class="error">Fix: BrandingManager not instantiated. Ensure it\'s initialized in Plugin.php</div>';
    }
    
    if (!$results['branding_menu_registered']) {
        echo '<div class="error">Fix: Branding menu not registered. Check BrandingManager constructor and admin_menu hook.</div>';
    }
    
    if (!$results['branding_table_exists']) {
        echo '<div class="warning">Fix: Create missing database tables. Run ensure_branding_tables() method.</div>';
        echo '<form method="post" style="margin: 10px 0;">
                <input type="hidden" name="action" value="create_tables">
                <input type="submit" value="Create Missing Tables" style="padding: 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;">
              </form>';
    }
    
    if (!$results['user_can_manage_options']) {
        echo '<div class="error">Fix: User needs administrator role or manage_options capability.</div>';
    }
    
    // Handle table creation
    if (isset($_POST['action']) && $_POST['action'] === 'create_tables') {
        echo "<h3>Creating Database Tables...</h3>";
        
        if (class_exists('SMO_Social\\WhiteLabel\\BrandingManager')) {
            try {
                $branding_manager = new \SMO_Social\WhiteLabel\BrandingManager();
                $branding_manager->ensure_branding_tables();
                echo '<div class="success">✓ Database tables created successfully!</div>';
            } catch (Exception $e) {
                echo '<div class="error">✗ Failed to create tables: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="error">✗ Cannot create tables - BrandingManager not available</div>';
        }
    }
    
    // 8. Quick Fix Button
    if (!$results['branding_instantiated'] || !$results['branding_menu_registered']) {
        echo "<h2>8. Quick Fix</h2>";
        echo '<div class="warning">If issues found, try reinitializing the plugin...</div>';
        echo '<form method="post" style="margin: 10px 0;">
                <input type="hidden" name="action" value="reinit_plugin">
                <input type="submit" value="Reinitialize Plugin" style="padding: 10px; background: #d63638; color: white; border: none; border-radius: 3px; cursor: pointer;">
              </form>';
        
        if (isset($_POST['action']) && $_POST['action'] === 'reinit_plugin') {
            echo "<h3>Reinitializing Plugin...</h3>";
            
            // Force reinitialization
            if (class_exists('SMO_Social\\Core\\Plugin')) {
                try {
                    $plugin = new \SMO_Social\Core\Plugin();
                    $plugin->init();
                    echo '<div class="success">✓ Plugin reinitialized! Refresh this page to check results.</div>';
                } catch (Exception $e) {
                    echo '<div class="error">✗ Reinitialization failed: ' . $e->getMessage() . '</div>';
                }
            }
        }
    }
    
    // Summary
    echo "<h2>Diagnostic Summary</h2>";
    echo '<table>';
    echo '<tr><th>Component</th><th>Status</th></tr>';
    foreach ($results as $key => $value) {
        $status = $value ? '✓ OK' : '✗ FAIL';
        $class = $value ? 'success' : 'error';
        echo '<tr><td>' . ucwords(str_replace('_', ' ', $key)) . '</td><td class="' . $class . '">' . $status . '</td></tr>';
    }
    echo '</table>';
    
    // Next steps
    echo "<h2>Next Steps</h2>";
    if (array_filter($results)) {
        echo '<div class="success">Most components are working. Try accessing the branding page again:</div>';
        echo '<p><a href="' . admin_url('admin.php?page=smo-branding') . '" target="_blank" style="padding: 10px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">Access Branding Page</a></p>';
    } else {
        echo '<div class="error">Multiple issues found. Please resolve the above problems and run this diagnostic again.</div>';
    }
    ?>
    
    <hr>
    <p><small>Diagnostic completed at <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>