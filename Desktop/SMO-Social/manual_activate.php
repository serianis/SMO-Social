<?php
/**
 * Manual Plugin Activation Script
 * 
 * Run this script to manually activate the SMO Social plugin
 */

// WordPress path configuration - Auto-detect WordPress installation
$possible_paths = [
    './wp-load.php',                    // Current directory
    '../wp-load.php',                   // Parent directory
    '../../wp-load.php',                // Two levels up
    dirname(__FILE__) . '/wp-load.php', // Same directory as this script
];

$wordpress_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $wordpress_path = dirname($path);
        break;
    }
}

if (!$wordpress_path) {
    die('Error: WordPress not found. Please run this script from your WordPress root directory or ensure wp-load.php is accessible.');
}

$plugin_path = $wordpress_path . '/wp-content/plugins/smo-social/smo-social.php';

// Load WordPress
define('WP_USE_THEMES', false);
require_once($wordpress_path . '/wp-load.php');

// Check if user is logged in and has admin privileges
if (!is_user_logged_in() || !current_user_can('activate_plugins')) {
    die('Error: You must be logged in as an administrator to activate this plugin');
}

echo '<!DOCTYPE html>';
echo '<html><head><title>Manual Plugin Activation</title>';
echo '<style>';
echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
echo '.success { color: green; font-weight: bold; }';
echo '.error { color: red; font-weight: bold; }';
echo '.info { color: blue; }';
echo '.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }';
echo 'pre { background: white; padding: 10px; overflow-x: auto; }';
echo '</style>';
echo '</head><body>';

echo '<h1>SMO Social Plugin Manual Activation</h1>';

// Check if plugin file exists
echo '<div class="section">';
echo '<h2>Step 1: Plugin File Check</h2>';
if (file_exists($plugin_path)) {
    echo '<p class="success">✓ Plugin file found at: ' . $plugin_path . '</p>';
} else {
    echo '<p class="error">✗ Plugin file not found at: ' . $plugin_path . '</p>';
    echo '<p>Please upload the plugin files to the correct location.</p>';
    echo '</div>';
    exit;
}
echo '</div>';

// Load the plugin
echo '<div class="section">';
echo '<h2>Step 2: Loading Plugin</h2>';
try {
    echo '<p>Loading plugin file...</p>';
    require_once($plugin_path);
    echo '<p class="success">✓ Plugin loaded successfully</p>';
} catch (Exception $e) {
    echo '<p class="error">✗ Failed to load plugin: ' . $e->getMessage() . '</p>';
    echo '<p>Stack trace:</p>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
    echo '</div>';
    exit;
}
echo '</div>';

// Check for activation function
echo '<div class="section">';
echo '<h2>Step 3: Activation Function Check</h2>';
if (function_exists('smo_social_activate')) {
    echo '<p class="success">✓ Activation function found</p>';
} else {
    echo '<p class="error">✗ Activation function not found</p>';
    echo '<p>Available functions:</p>';
    $functions = get_defined_functions();
    echo '<pre>' . print_r($functions['user'], true) . '</pre>';
    echo '</div>';
    exit;
}
echo '</div>';

// Execute activation
echo '<div class="section">';
echo '<h2>Step 4: Executing Activation</h2>';
try {
    echo '<p>Calling smo_social_activate()...</p>';
    smo_social_activate();
    echo '<p class="success">✓ Activation function executed successfully</p>';
} catch (Exception $e) {
    echo '<p class="error">✗ Activation failed: ' . $e->getMessage() . '</p>';
    echo '<p>Stack trace:</p>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
    echo '</div>';
    exit;
}
echo '</div>';

// Verify activation
echo '<div class="section">';
echo '<h2>Step 5: Verification</h2>';

$plugin_basename = 'smo-social/smo-social.php';
$active_plugins = get_option('active_plugins') ?: array();

if (is_array($active_plugins) && in_array($plugin_basename, $active_plugins)) {
    echo '<p class="success">✓ Plugin is now ACTIVE!</p>';
    echo '<p>The SMO Social plugin has been successfully activated.</p>';
    echo '<p>You can now configure it from your WordPress admin panel under the SMO Social menu.</p>';
} else {
    echo '<p class="warning">⚠ Plugin activation function ran but plugin may not be active</p>';
    echo '<p>Please check your WordPress plugins page to verify the activation status.</p>';
    echo '<p>Plugin basename: ' . $plugin_basename . '</p>';
    echo '<p>Active plugins: ' . print_r($active_plugins, true) . '</p>';
}

// Check if plugin tables were created
global $wpdb;
$tables = [
    'smo_scheduled_posts',
    'smo_platform_tokens', 
    'smo_analytics'
];

echo '<h3>Database Tables Check:</h3>';
foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
    echo '<p>' . ($exists ? '✓' : '✗') . ' Table: ' . $full_table . '</p>';
}
echo '</div>';

echo '<div class="section">';
echo '<h2>Next Steps</h2>';
echo '<p>1. Visit your WordPress admin dashboard</p>';
echo '<p>2. Navigate to the SMO Social plugin menu</p>';
echo '<p>3. Configure your social media platform connections</p>';
echo '<p>4. Start creating and scheduling posts</p>';
echo '</div>';

echo '</body></html>';
