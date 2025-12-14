<?php
// Simple script to activate the SMO Social plugin
define('WP_USE_THEMES', false);
require_once 'c:/xampp/htdocs/deka/wp-load.php';

// Simulate plugin activation by calling the activation hook directly
$plugin_file = 'c:/xampp/htdocs/deka/wp-content/plugins/smo-social/smo-social.php';

if (file_exists($plugin_file)) {
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
    echo 'Plugin file not found.';
}
?>