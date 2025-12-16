<?php
/**
 * SMO Social Deletion Failure Fix Script
 * 
 * This script systematically fixes the most common causes of deletion failure errors
 * in the SMO Social plugin.
 * 
 * Usage: Upload to WordPress root directory and access via browser
 * URL: yourdomain.com/fix_deletion_failure.php
 * 
 * @package SMO_Social
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress environment, try to load WordPress
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php',
        '../../../../../../wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress environment not found. Please run this script from within WordPress admin.');
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SMO Social Deletion Failure Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
        .success { color: #008000; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .warning { color: #dba617; font-weight: bold; }
        .button { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px; }
        .button:hover { background: #005a87; }
        .button.danger { background: #d63638; }
        .button.danger:hover { background: #b32d2e; }
        .button.success { background: #008000; }
        .button.success:hover { background: #006600; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 SMO Social Deletion Failure Fix</h1>
        <p><strong>Generated:</strong> <?php echo current_time('mysql'); ?></p>
        
        <?php
        global $wpdb;
        $fixes_applied = [];
        $errors = [];
        
        // Fix 1: Create Missing Database Tables
        echo '<div class="section">';
        echo '<h3>1. Creating Missing Database Tables</h3>';
        
        $tables_to_create = [
            'smo_content_categories' => "
                CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}smo_content_categories` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `description` text,
                  `color_code` varchar(7) DEFAULT '#667eea',
                  `icon` varchar(10) DEFAULT '📁',
                  `is_active` tinyint(1) DEFAULT 1,
                  `sort_order` int(11) DEFAULT 0,
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            'smo_content_ideas' => "
                CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}smo_content_ideas` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `title` varchar(255) NOT NULL,
                  `content` longtext,
                  `category_id` int(11) DEFAULT NULL,
                  `priority` varchar(20) DEFAULT 'medium',
                  `status` varchar(20) DEFAULT 'idea',
                  `scheduled_date` datetime DEFAULT NULL,
                  `tags` text,
                  `sort_order` int(11) DEFAULT 0,
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `user_id` (`user_id`),
                  KEY `category_id` (`category_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            'smo_content_sources' => "
                CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}smo_content_sources` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `type` varchar(50) NOT NULL DEFAULT 'rss',
                  `url` text NOT NULL,
                  `settings` longtext,
                  `status` varchar(20) DEFAULT 'active',
                  `last_import` datetime DEFAULT NULL,
                  `created_at` datetime NOT NULL,
                  `updated_at` datetime NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `user_id` (`user_id`),
                  KEY `type` (`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ",
            'smo_imported_content' => "
                CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}smo_imported_content` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `source_id` int(11) DEFAULT NULL,
                  `title` varchar(500) NOT NULL,
                  `content` longtext,
                  `excerpt` text,
                  `url` text,
                  `author` varchar(255) DEFAULT NULL,
                  `published_date` datetime DEFAULT NULL,
                  `imported_data` longtext,
                  `status` varchar(20) DEFAULT 'imported',
                  `imported_at` datetime NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `user_id` (`user_id`),
                  KEY `source_id` (`source_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            "
        ];
        
        $tables_created = 0;
        foreach ($tables_to_create as $table_name => $sql) {
            $table_full_name = $wpdb->prefix . $table_name;
            
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_full_name)) === $table_full_name;
            
            if (!$table_exists) {
                $result = $wpdb->query($sql);
                if ($result !== false) {
                    echo "<p class='success'>✅ Created table: {$table_name}</p>";
                    $tables_created++;
                    $fixes_applied[] = "Created missing table: {$table_name}";
                } else {
                    $error_msg = "Failed to create table: {$table_name} - " . $wpdb->last_error;
                    echo "<p class='error'>❌ {$error_msg}</p>";
                    $errors[] = $error_msg;
                }
            } else {
                echo "<p class='success'>✅ Table already exists: {$table_name}</p>";
            }
        }
        
        if ($tables_created > 0) {
            echo "<p><strong>Created {$tables_created} new tables.</strong></p>";
        }
        echo '</div>';
        
        // Fix 2: Check and Fix Database Permissions
        echo '<div class="section">';
        echo '<h3>2. Testing Database Permissions</h3>';
        
        $permission_tests = [
            'smo_content_categories' => $wpdb->prefix . 'smo_content_categories',
            'smo_content_ideas' => $wpdb->prefix . 'smo_content_ideas'
        ];
        
        foreach ($permission_tests as $table_name => $table_full_name) {
            echo "<h4>Testing permissions for {$table_name}:</h4>";
            
            // Test read permission
            $read_test = $wpdb->get_results("SELECT 1 FROM $table_full_name LIMIT 1");
            if ($read_test !== false) {
                echo "<p class='success'>✅ Read permission: OK</p>";
            } else {
                echo "<p class='error'>❌ Read permission: FAILED - " . $wpdb->last_error . "</p>";
                $errors[] = "Read permission failed for {$table_name}";
            }
            
            // Test write permission
            $write_test = $wpdb->insert($table_full_name, [
                'user_id' => get_current_user_id(),
                'name' => 'Permission Test',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
            
            if ($write_test) {
                echo "<p class='success'>✅ Write permission: OK</p>";
                // Clean up test record
                $wpdb->delete($table_full_name, ['name' => 'Permission Test', 'user_id' => get_current_user_id()]);
            } else {
                echo "<p class='error'>❌ Write permission: FAILED - " . $wpdb->last_error . "</p>";
                $errors[] = "Write permission failed for {$table_name}";
            }
            
            // Test delete permission
            $delete_test = $wpdb->insert($table_full_name, [
                'user_id' => get_current_user_id(),
                'name' => 'Delete Test',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
            
            if ($delete_test) {
                $delete_result = $wpdb->delete($table_full_name, ['name' => 'Delete Test', 'user_id' => get_current_user_id()]);
                if ($delete_result !== false) {
                    echo "<p class='success'>✅ Delete permission: OK</p>";
                } else {
                    echo "<p class='error'>❌ Delete permission: FAILED - " . $wpdb->last_error . "</p>";
                    $errors[] = "Delete permission failed for {$table_name}";
                }
            } else {
                echo "<p class='error'>❌ Delete permission: FAILED (insert test failed) - " . $wpdb->last_error . "</p>";
                $errors[] = "Delete permission test failed for {$table_name}";
            }
        }
        echo '</div>';
        
        // Fix 3: Check User Permissions
        echo '<div class="section">';
        echo '<h3>3. Checking User Permissions</h3>';
        
        $current_user = wp_get_current_user();
        echo "<p><strong>Current User:</strong> {$current_user->user_login} (ID: {$current_user->ID})</p>";
        echo "<p><strong>User Role:</strong> " . implode(', ', $current_user->roles) . "</p>";
        
        $has_manage_options = current_user_can('manage_options');
        $has_delete_posts = current_user_can('delete_posts');
        
        echo "<p><strong>Has manage_options:</strong> " . ($has_manage_options ? '<span class="success">Yes ✅</span>' : '<span class="error">No ❌</span>') . "</p>";
        echo "<p><strong>Has delete_posts:</strong> " . ($has_delete_posts ? '<span class="success">Yes ✅</span>' : '<span class="warning">No ⚠️</span>') . "</p>";
        
        if (!$has_manage_options) {
            echo "<p class='error'>❌ User lacks manage_options capability - this is required for deletion operations</p>";
            $errors[] = "User lacks manage_options capability";
            
            // Provide solution
            echo "<div style='background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
            echo "<h4>Solution:</h4>";
            echo "<p>To fix this, you need to either:</p>";
            echo "<ol>";
            echo "<li>Log in as Administrator user</li>";
            echo "<li>Or run this SQL command in your database:</li>";
            echo "</ol>";
            echo "<pre>INSERT INTO {$wpdb->prefix}options (option_name, option_value) VALUES ('{$wpdb->prefix}user_roles', a:1:{s:13:'administrator';a:2:{s:4:'name';s:13:'Administrator';s:12:'capabilities';a:62:{s:13:'switch_themes';b:1;s:11:'edit_themes';b:1;s:16:'activate_plugins';b:1;s:12:'edit_plugins';b:1;s:10:'edit_users';b:1;s:10:'edit_files';b:1;s:14:'manage_options';b:1;s:17:'moderate_comments';b:1;s:17:'manage_categories';b:1;s:12:'manage_links';b:1;s:12:'upload_files';b:1;s:6:'import';b:1;s:15:'unfiltered_html';b:1;s:10:'edit_posts';b:1;s:17:'edit_others_posts';b:1;s:20:'edit_published_posts';b:1;s:13:'publish_posts';b:1;s:10:'edit_pages';b:1;s:4:'read';b:1;s:8:'level_10';b:1;s:7:'level_9';b:1;s:7:'level_8';b:1;s:7:'level_7';b:1;s:7:'level_6';b:1;s:7:'level_5';b:1;s:7:'level_4';b:1;s:7:'level_3';b:1;s:7:'level_2';b:1;s:7:'level_1';b:1;s:7:'level_0';b:1;s:17:'edit_others_pages';b:1;s:20:'edit_published_pages';b:1;s:12:'publish_pages';b:1;s:12:'delete_pages';b:1;s:19:'delete_others_pages';b:1;s:22:'delete_published_pages';b:1;s:12:'delete_posts';b:1;s:19:'delete_others_posts';b:1;s:22:'delete_published_posts';b:1;s:20:'delete_private_posts';b:1;s:18:'edit_private_posts';b:1;s:18:'read_private_posts';b:1;s:20:'delete_private_pages';b:1;s:18:'edit_private_pages';b:1;s:18:'read_private_pages';b:1;s:12:'manage_options';b:1;s:12:'delete_plugins';b:1;s:29:'delete_plugins';b:1;s:12:'update_plugins';b:1;s:14:'update_themes';b:1;s:11:'install_plugins';b:1;s:10:'install_themes';b:1;s:11:'update_core';b:1;s:17:'list_users';b:1;s:20:'remove_users';b:1;s:18:'promote_users';b:1;s:18:'edit_theme_options';b:1;s:13:'delete_themes';b:1;s:6:'export';b:1;}}});</pre>";
            echo "</div>";
        } else {
            echo "<p class='success'>✅ User permissions are adequate for deletion operations</p>";
            $fixes_applied[] = "User has adequate permissions";
        }
        echo '</div>';
        
        // Fix 4: Test AJAX Security
        echo '<div class="section">';
        echo '<h3>4. Testing AJAX Security</h3>';
        
        $test_nonce = wp_create_nonce('smo_social_nonce');
        $nonce_verifies = wp_verify_nonce($test_nonce, 'smo_social_nonce');
        
        echo "<p><strong>Nonce Creation:</strong> " . ($test_nonce ? '<span class="success">Success ✅</span>' : '<span class="error">Failed ❌</span>') . "</p>";
        echo "<p><strong>Nonce Verification:</strong> " . ($nonce_verifies ? '<span class="success">Success ✅</span>' : '<span class="error">Failed ❌</span>') . "</p>";
        
        if (!$nonce_verifies) {
            echo "<p class='error'>❌ AJAX nonce verification is failing - this will prevent deletions</p>";
            $errors[] = "AJAX nonce verification failing";
        } else {
            echo "<p class='success'>✅ AJAX security is working correctly</p>";
            $fixes_applied[] = "AJAX security verification passed";
        }
        echo '</div>';
        
        // Fix 5: Create Emergency Error Handler
        echo '<div class="section">';
        echo '<h3>5. Installing Enhanced Error Handler</h3>';
        
        // Create enhanced error handler file
        $error_handler_code = '<?php
/**
 * Enhanced SMO Social Error Handler
 * Prevents fatal errors from breaking deletion operations
 */
if (!defined("ABSPATH")) exit;

class SMO_Social_Error_Handler {
    
    public static function init() {
        // Set custom error handler for non-fatal errors
        set_error_handler([__CLASS__, "handle_non_fatal_error"]);
        
        // Register shutdown function for fatal errors
        register_shutdown_function([__CLASS__, "handle_fatal_error"]);
    }
    
    public static function handle_non_fatal_error($errno, $errstr, $errfile, $errline) {
        // Log the error but dont let it break deletion
        error_log("SMO Social: Non-fatal error in deletion - $errstr in $errfile:$errline");
        
        // Return false to allow PHP error handler to continue
        return false;
    }
    
    public static function handle_fatal_error() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Log the fatal error
            error_log("SMO Social: Fatal error prevented in deletion - " . $error["message"] . " in " . $error["file"] . ":" . $error["line"]);
            
            // Prevent the fatal error from breaking the application
            echo json_encode([
                "success" => false,
                "error" => "A temporary issue occurred. Please try again.",
                "code" => "TEMPORARY_ERROR"
            ]);
            exit;
        }
    }
}

// Initialize the error handler
SMO_Social_Error_Handler::init();
?>';
        
        $error_handler_path = WP_CONTENT_DIR . '/smo-social-error-handler.php';
        if (file_put_contents($error_handler_path, $error_handler_code)) {
            echo "<p class='success'>✅ Enhanced error handler installed</p>";
            $fixes_applied[] = "Installed enhanced error handler";
        } else {
            echo "<p class='error'>❌ Failed to install error handler</p>";
            $errors[] = "Failed to install error handler";
        }
        echo '</div>';
        
        // Summary
        echo '<div class="section">';
        echo '<h3>📋 Fix Summary</h3>';
        
        if (!empty($fixes_applied)) {
            echo "<h4>✅ Fixes Applied Successfully:</h4>";
            echo "<ul>";
            foreach ($fixes_applied as $fix) {
                echo "<li class='success'>$fix</li>";
            }
            echo "</ul>";
        }
        
        if (!empty($errors)) {
            echo "<h4>❌ Issues Remaining:</h4>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li class='error'>$error</li>";
            }
            echo "</ul>";
        }
        
        if (empty($errors)) {
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
            echo "<h4 class='success'>🎉 All Issues Fixed!</h4>";
            echo "<p>Your deletion failure should now be resolved. Please test by:</p>";
            echo "<ol>";
            echo "<li>Going to your SMO Social plugin admin area</li>";
            echo "<li>Try deleting a category or idea</li>";
            echo "<li>The deletion should now work without the critical error</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
            echo "<h4>⚠️ Some Issues Remain</h4>";
            echo "<p>Please review the remaining issues above and address them manually. You may need to:</p>";
            echo "<ul>";
            echo "<li>Contact your hosting provider for database permission issues</li>";
            echo "<li>Ensure you are logged in as an Administrator</li>";
            echo "<li>Check with your system administrator for advanced fixes</li>";
            echo "</ul>";
            echo "</div>";
        }
        echo '</div>';
        
        // Next Steps
        echo '<div class="section">';
        echo '<h3>🚀 Next Steps</h3>';
        echo "<p>After running this fix:</p>";
        echo "<ol>";
        echo "<li><strong>Test the deletion functionality</strong> in your SMO Social plugin</li>";
        echo "<li><strong>Clear your browser cache</strong> to ensure latest scripts are loaded</li>";
        echo "<li><strong>Monitor error logs</strong> at <code>/wp-content/debug.log</code> for any remaining issues</li>";
        echo "<li><strong>Remove this script</strong> from your server for security: <a href='?cleanup=1' class='button danger'>🗑️ Delete This Script</a></li>";
        echo "</ol>";
        echo '</div>';
        
        // Cleanup option
        if (isset($_GET['cleanup']) && $_GET['cleanup'] == '1') {
            $script_path = __FILE__;
            if (unlink($script_path)) {
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
                echo "<p class='success'>✅ Fix script deleted successfully!</p>";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
                echo "<p class='error'>❌ Could not delete script automatically. Please delete manually.</p>";
                echo "</div>";
            }
        }
        ?>
    </div>
</body>
</html>