<?php
/**
 * SMO Social Plugin Activation Troubleshooter
 * 
 * This script helps identify and fix common activation issues
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Auto-detect WordPress installation
    $possible_paths = [
        './wp-load.php',                    // Current directory
        '../wp-load.php',                   // Parent directory
        '../../wp-load.php',                // Two levels up
        dirname(__FILE__) . '/wp-load.php', // Same directory as this script
    ];

    $wordpress_loaded = false;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            define('WP_USE_THEMES', false);
            require_once $path;
            $wordpress_loaded = true;
            break;
        }
    }

    if (!$wordpress_loaded) {
        echo '<h2>WordPress Not Found</h2>';
        echo '<p>Could not find WordPress installation. Please run this script from your WordPress root directory.</p>';
        exit;
    }
}

echo '<!DOCTYPE html>';
echo '<html><head><title>SMO Social Plugin Activation Troubleshooter</title>';
echo '<style>';
echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
echo '.success { color: green; }';
echo '.error { color: red; }';
echo '.warning { color: orange; }';
echo '.info { color: blue; }';
echo '.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }';
echo '.code { background: #f5f5f5; padding: 10px; font-family: monospace; }';
echo '</style>';
echo '</head><body>';

echo '<h1>SMO Social Plugin Activation Troubleshooter</h1>';

// Check if plugin file exists - Auto-detect plugin location
$possible_plugin_paths = [
    './wp-content/plugins/smo-social/smo-social.php',
    '../wp-content/plugins/smo-social/smo-social.php',
    '../../wp-content/plugins/smo-social/smo-social.php',
    dirname(__FILE__) . '/wp-content/plugins/smo-social/smo-social.php',
    ABSPATH . 'wp-content/plugins/smo-social/smo-social.php' // WordPress auto-detected path
];

$plugin_file = null;
foreach ($possible_plugin_paths as $path) {
    if (file_exists($path)) {
        $plugin_file = $path;
        break;
    }
}

echo '<div class="section">';
echo '<h2>1. Plugin File Check</h2>';

if ($plugin_file) {
    echo '<p class="success">✓ Plugin file found at: ' . $plugin_file . '</p>';
} else {
    echo '<p class="error">✗ Plugin file not found</p>';
    echo '<p>Please ensure the plugin is uploaded to wp-content/plugins/smo-social/ directory.</p>';
    echo '<p>Searched paths:</p><ul>';
    foreach ($possible_plugin_paths as $path) {
        echo '<li>' . htmlspecialchars($path) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
    exit;
}
echo '</div>';

// Check WordPress environment
echo '<div class="section">';
echo '<h2>2. WordPress Environment Check</h2>';
echo '<p>WordPress Version: ' . get_bloginfo('version') . '</p>';
echo '<p>PHP Version: ' . PHP_VERSION . '</p>';
echo '<p>Current User: ' . (function_exists('wp_get_current_user') ? wp_get_current_user()->user_login : 'Unknown') . '</p>';

// Check PHP requirements
$php_ok = version_compare(PHP_VERSION, '7.4', '>=');
echo '<p>' . ($php_ok ? '✓' : '✗') . ' PHP Version Check: ' . PHP_VERSION . ' (Required: 7.4+)</p>';

// Check required extensions
$extensions = ['curl', 'json', 'mbstring', 'mysqli'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo '<p>' . ($loaded ? '✓' : '✗') . ' Extension: ' . $ext . ($loaded ? ' (Loaded)' : ' (Not Loaded)') . '</p>';
}
echo '</div>';

// Check database connection
echo '<div class="section">';
echo '<h2>3. Database Connection Check</h2>';
global $wpdb;
if ($wpdb->get_var('SELECT 1')) {
    echo '<p class="success">✓ Database connection successful</p>';
    echo '<p>Database Name: ' . ($wpdb->dbname ?? 'Unknown') . '</p>';
    echo '<p>Database Prefix: ' . $wpdb->prefix . '</p>';
} else {
    echo '<p class="error">✗ Database connection failed</p>';
}
echo '</div>';

// Check plugin activation status
echo '<div class="section">';
echo '<h2>4. Plugin Status Check</h2>';

$active_plugins = get_option('active_plugins') ?: array();
$plugin_basename = 'smo-social/smo-social.php';

if (is_array($active_plugins) && in_array($plugin_basename, $active_plugins)) {
    echo '<p class="success">✓ Plugin is currently active</p>';
} else {
    echo '<p class="warning">⚠ Plugin is not active</p>';
}

// Check if plugin tables exist
$tables = [
    'smo_scheduled_posts',
    'smo_platform_tokens', 
    'smo_analytics',
    'smo_content_variants',
    'smo_activity_logs',
    'smo_content_templates',
    'smo_queue',
    'smo_post_platforms'
];

echo '<h3>Database Tables Check:</h3>';
foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
    echo '<p>' . ($exists ? '✓' : '✗') . ' Table: ' . $full_table . ($exists ? ' (Exists)' : ' (Missing)') . '</p>';
}
echo '</div>';

// Try manual activation
echo '<div class="section">';
echo '<h2>5. Manual Activation Attempt</h2>';

if (file_exists($plugin_file)) {
    try {
        echo '<p>Attempting to load plugin file...</p>';
        require_once $plugin_file;
        
        if (function_exists('smo_social_activate')) {
            echo '<p>Activation function found, calling it...</p>';
            smo_social_activate();
            echo '<p class="success">✓ Plugin activation function executed successfully</p>';
            
            // Verify activation
            $active_plugins = get_option('active_plugins') ?: array();
            if (is_array($active_plugins) && in_array($plugin_basename, $active_plugins)) {
                echo '<p class="success">✓ Plugin is now active!</p>';
            } else {
                echo '<p class="warning">⚠ Plugin function ran but plugin may not be active. Please check WordPress plugins page.</p>';
            }
        } else {
            echo '<p class="error">✗ Activation function not found</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ Plugin activation failed: ' . $e->getMessage() . '</p>';
        echo '<p class="error">Stack trace: ' . $e->getTraceAsString() . '</p>';
    }
} else {
    echo '<p class="error">✗ Plugin file not found</p>';
}
echo '</div>';

// Check file permissions
echo '<div class="section">';
echo '<h2>6. File Permissions Check</h2>';

$plugin_dir = dirname($plugin_file);
echo '<p>Plugin directory: ' . $plugin_dir . '</p>';
echo '<p>Directory writable: ' . (is_writable($plugin_dir) ? 'Yes' : 'No') . '</p>';

$upload_dir = wp_upload_dir();
echo '<p>Upload directory: ' . $upload_dir['basedir'] . '</p>';
echo '<p>Upload directory writable: ' . (is_writable($upload_dir['basedir']) ? 'Yes' : 'No') . '</p>';
echo '</div>';

// Recommendations
echo '<div class="section">';
echo '<h2>7. Recommendations</h2>';

// Check for common issues
$issues = [];

if (!function_exists('smo_social_activate')) {
    $issues[] = 'Activation function not available - Plugin file may not be loading correctly';
}

if (!is_writable($upload_dir['basedir'])) {
    $issues[] = 'Upload directory not writable - Plugin may not be able to create necessary folders';
}

if (!$php_ok) {
    $issues[] = 'PHP version too old - Update to PHP 7.4 or higher';
}

if (!empty($issues)) {
    echo '<h3>Issues Found:</h3>';
    echo '<ul>';
    foreach ($issues as $issue) {
        echo '<li class="error">✗ ' . $issue . '</li>';
    }
    echo '</ul>';
} else {
    echo '<p class="success">✓ No major issues found. Plugin should activate normally.</p>';
}
echo '</div>';

echo '<div class="section">';
echo '<h2>8. Next Steps</h2>';
echo '<p>If the plugin is still not working after this troubleshooter:</p>';
echo '<ol>';
echo '<li>Check WordPress error logs for any PHP errors</li>';
echo '<li>Try deactivating and reactivating the plugin from WordPress admin</li>';
echo '<li>Clear any caching plugins or server cache</li>';
echo '<li>Check if there are any conflicting plugins</li>';
echo '<li>Verify the plugin files are complete and not corrupted</li>';
echo '</ol>';
echo '</div>';

echo '</body></html>';
