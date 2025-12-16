<?php
// Simple script to activate the SMO Social plugin
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
    die('Error: WordPress not found. Please run this script from your WordPress root directory.');
}

// Simulate plugin activation by calling the activation hook directly
$possible_plugin_paths = [
    './wp-content/plugins/smo-social/smo-social.php',
    '../wp-content/plugins/smo-social/smo-social.php',
    '../../wp-content/plugins/smo-social/smo-social.php',
    dirname(__FILE__) . '/wp-content/plugins/smo-social/smo-social.php',
    ABSPATH . 'wp-content/plugins/smo-social/smo-social.php'
];

$plugin_file = null;
foreach ($possible_plugin_paths as $path) {
    if (file_exists($path)) {
        $plugin_file = $path;
        break;
    }
}

if ($plugin_file && file_exists($plugin_file)) {
    try {
        // Include the plugin file
        require_once $plugin_file;

        // Call the activation function if it exists
        if (function_exists('smo_social_activate')) {
            smo_social_activate();
            echo 'Plugin activated successfully!';
        } else {
            echo 'Activation function not found.';
        }
    } catch (Exception $e) {
        echo 'Plugin activation failed: ' . $e->getMessage();
    }
} else {
    echo 'Plugin file not found. Please ensure the plugin is uploaded to wp-content/plugins/smo-social/ directory.';
}
?>