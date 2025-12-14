<?php
/**
 * Plugin Name: SMO Social
 * Plugin URI: https://github.com/yourusername/smo-social
 * Description: A comprehensive social media optimization plugin supporting 30+ platforms with AI-powered features, scheduling, analytics, and more. Completely free and modular.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smo-social
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('SMO_SOCIAL_STANDALONE')) {
    exit;
}

// Environment detection using the new utility
require_once __DIR__ . '/includes/EnvironmentDetector.php';

// Define plugin constants first
if (!defined('SMO_SOCIAL_VERSION')) {
    define('SMO_SOCIAL_VERSION', '1.0.0');
}
if (!defined('SMO_SOCIAL_PLUGIN_DIR')) {
    define('SMO_SOCIAL_PLUGIN_DIR', __DIR__ . '/');
}
if (!defined('SMO_SOCIAL_PLUGIN_BASENAME')) {
    define('SMO_SOCIAL_PLUGIN_BASENAME', 'smo-social/smo-social.php');
}
if (!defined('SMO_SOCIAL_PLUGIN_FILE')) {
    define('SMO_SOCIAL_PLUGIN_FILE', __FILE__);
}

// Load compatibility layer and global declarations for standalone mode
if (!\SMO_Social\Utilities\EnvironmentDetector::isWordPress()) {
    if (!defined('SMO_SOCIAL_STANDALONE')) {
        define('SMO_SOCIAL_STANDALONE', true);
    }

    // Define ABSPATH for compatibility
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__FILE__) . '/');
    }

    // Load WordPress function compatibility layer
    require_once __DIR__ . '/includes/wordpress-functions.php';
    require_once __DIR__ . '/includes/global-declarations.php';
    require_once __DIR__ . '/includes/type-stubs.php';
}

if (!function_exists('smo_social_is_wordpress')) {
    function smo_social_is_wordpress() {
        return \SMO_Social\Utilities\EnvironmentDetector::isWordPress();
    }
}

// Define plugin URL based on environment
if (!defined('SMO_SOCIAL_PLUGIN_URL')) {
    if (smo_social_is_wordpress() && function_exists('plugin_dir_url')) {
        define('SMO_SOCIAL_PLUGIN_URL', plugin_dir_url(__FILE__));
    } else {
        define('SMO_SOCIAL_PLUGIN_URL', 'http://localhost/smo-social/');
    }
}

// Update basename for WordPress mode
if (smo_social_is_wordpress() && function_exists('plugin_basename') && !defined('SMO_SOCIAL_PLUGIN_BASENAME')) {
    define('SMO_SOCIAL_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Validate environment and display any issues
$validation_results = \SMO_Social\Utilities\EnvironmentDetector::validateEnvironment();
if (!$validation_results['valid']) {
    $error_message = 'SMO Social Plugin Environment Errors:' . PHP_EOL;
    foreach ($validation_results['errors'] as $error) {
        $error_message .= '- ' . $error . PHP_EOL;
    }
    
    if (smo_social_is_wordpress()) {
        add_action('admin_notices', function() use ($validation_results) {
            foreach ($validation_results['errors'] as $error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            }
        });
    } else {
        echo "<!-- SMO Social Environment Errors -->" . PHP_EOL;
        echo $error_message;
        \SMO_Social\Utilities\EnvironmentDetector::log($error_message, 'error');
    }
}

// Autoload classes
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'SMO_Social\\') === 0) {
        $class_name = str_replace('SMO_Social\\', '', $class_name);
        $class_name = str_replace('\\', '/', $class_name);
        $file_path = SMO_SOCIAL_PLUGIN_DIR . 'includes/' . $class_name . '.php';
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

// Initialize the plugin
function smo_social_init() {
    // Error handling for missing dependencies
    $errors = array();
    
    // Validate WordPress dependencies if in WordPress mode
    if (smo_social_is_wordpress()) {
        // Load text domain
        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('smo-social', false, dirname(SMO_SOCIAL_PLUGIN_BASENAME) . '/languages/');
        }
        
        // Validate WordPress version
        if (function_exists('get_bloginfo')) {
            $wp_version = get_bloginfo('version');
            if (version_compare($wp_version, '5.0', '<')) {
                $errors[] = sprintf(__('SMO Social requires WordPress 5.0 or higher. Current version: %s'), $wp_version);
            }
        }
    } else {
        // Standalone mode initialization
        echo "<!-- SMO Social Plugin - Standalone Mode -->\n";
        
        // Set up standalone mode environment
        if (!defined('WPINC')) {
            define('WPINC', 'wp-includes');
        }
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
        }
    }
    
    // Display errors if any
    if (!empty($errors)) {
        if (smo_social_is_wordpress() && function_exists('add_action')) {
            add_action('admin_notices', function() use ($errors) {
                foreach ($errors as $error) {
                    echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
                }
            });
        } else {
            foreach ($errors as $error) {
                echo "Error: " . $error . "\n";
            }
        }
        return;
    }
    
    // Initialize enhanced security components
    $security_files = array(
        SMO_SOCIAL_PLUGIN_DIR . 'includes/Security/CSRFManager.php',
        SMO_SOCIAL_PLUGIN_DIR . 'includes/Security/InputValidator.php',
        SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/EnhancedCacheManager.php'
    );
    
    foreach ($security_files as $file) {
        if (file_exists($file)) {
            try {
                require_once $file;
            } catch (Exception $e) {
                error_log("SMO Social: Error loading security file: " . $file . " - " . $e->getMessage());
            }
        } else {
            error_log("SMO Social: Missing security file: " . $file);
        }
    }
    
    // Initialize security systems
    if (class_exists('SMO_Social\\Security\\CSRFManager')) {
        try {
            \SMO_Social\Security\CSRFManager::init();
        } catch (Exception $e) {
            error_log("SMO Social: Error initializing CSRF Manager: " . $e->getMessage());
        }
    }
    
    // Load performance optimizations
    $perf_files = array(
        SMO_SOCIAL_PLUGIN_DIR . 'performance-optimizations/database-optimizations.php',
        SMO_SOCIAL_PLUGIN_DIR . 'performance-optimizations/ai-optimizations.php',
        SMO_SOCIAL_PLUGIN_DIR . 'performance-optimizations/platform-optimizations.php'
    );
    
    foreach ($perf_files as $file) {
        if (file_exists($file)) {
            try {
                require_once $file;
            } catch (Exception $e) {
                error_log("SMO Social: Error loading performance file: " . $file . " - " . $e->getMessage());
            }
        }
    }
    
    // Initialize Integration System
    if (file_exists(SMO_SOCIAL_PLUGIN_DIR . 'includes/Integrations/IntegrationActivator.php')) {
        try {
            require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/Integrations/IntegrationActivator.php';
            if (class_exists('SMO_Social\\Integrations\\IntegrationActivator')) {
                \SMO_Social\Integrations\IntegrationActivator::init();
            }
        } catch (Exception $e) {
            error_log("SMO Social: Error initializing integration system: " . $e->getMessage());
        }
    }
    
    // Initialize core class with error handling
    if (file_exists(SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/Plugin.php')) {
        try {
            require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/Plugin.php';
            if (class_exists('SMO_Social\\Core\\Plugin')) {
                $plugin = new SMO_Social\Core\Plugin();
                $plugin->init();
            }
        } catch (Exception $e) {
            error_log("SMO Social: Error initializing core plugin: " . $e->getMessage());
            // In standalone mode, we can continue without full functionality
            if (!smo_social_is_wordpress()) {
                error_log("SMO Social: Continuing in limited standalone mode");
            } else {
                return;
            }
        }
    }
    
    // Schedule AI optimizations if class exists
    if (class_exists('SMO_Social\\Performance\\AI\\AIoptimizations')) {
        try {
            \SMO_Social\Performance\AI\AIoptimizations::schedule_ai_optimizations();
        } catch (Exception $e) {
            error_log("SMO Social: Error scheduling AI optimizations: " . $e->getMessage());
        }
    }
}

// Hook initialization based on environment
if (smo_social_is_wordpress()) {
    if (function_exists('add_action')) {
        add_action('plugins_loaded', 'smo_social_init');
    }
} else {
    // In standalone mode, initialize immediately
    smo_social_init();
}

// Activation hook
if (smo_social_is_wordpress()) {
    register_activation_hook(__FILE__, 'smo_social_activate');
}
function smo_social_activate() {
    // Initialize Integration System
    if (file_exists(SMO_SOCIAL_PLUGIN_DIR . 'includes/Integrations/IntegrationActivator.php')) {
        require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/Integrations/IntegrationActivator.php';
        if (class_exists('SMO_Social\\Integrations\\IntegrationActivator')) {
            \SMO_Social\Integrations\IntegrationActivator::activate();
        }
    }
    
    // Run the activator for database setup and default options
    if (file_exists(SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/Activator.php')) {
        require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/Activator.php';
        if (class_exists('SMO_Social\\Core\\Activator')) {
            \SMO_Social\Core\Activator::activate();
        }
    }
    
    // Log activation in standalone mode
    if (!smo_social_is_wordpress()) {
        error_log("SMO Social Plugin activated in standalone mode");
    }
}

// Deactivation hook
if (smo_social_is_wordpress()) {
    register_deactivation_hook(__FILE__, 'smo_social_deactivate');
}
function smo_social_deactivate() {
    // Run the deactivator for cleanup
    if (file_exists(SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/Deactivator.php')) {
        require_once SMO_SOCIAL_PLUGIN_DIR . 'includes/Core/Deactivator.php';
        if (class_exists('SMO_Social\\Core\\Deactivator')) {
            \SMO_Social\Core\Deactivator::deactivate();
        }
    }
    
    // Log deactivation in standalone mode
    if (!smo_social_is_wordpress()) {
        error_log("SMO Social Plugin deactivated in standalone mode");
    }
}

// Uninstall hook
if (smo_social_is_wordpress()) {
    register_uninstall_hook(__FILE__, 'smo_social_uninstall');
}
function smo_social_uninstall() {
    // Complete uninstall process
    // Note: Database cleanup would typically be handled by the uninstall procedure
    // This is called when the plugin is completely removed from WordPress
    
    // Log uninstall in standalone mode
    if (!smo_social_is_wordpress()) {
        error_log("SMO Social Plugin uninstalled in standalone mode");
    }
}
