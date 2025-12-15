<?php
/**
 * Integration Activator
 * 
 * Handles activation and deactivation of integration system
 *
 * @package SMO_Social
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace SMO_Social\Integrations;

use SMO_Social\Database\IntegrationSchema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration Activator Class
 */
class IntegrationActivator {
    
    /**
     * Initialize integration system on plugin activation
     */
    public static function activate() {
        // Create database tables
        IntegrationSchema::create_tables();
        
        // Add default options
        self::set_default_options();
        
        // Register custom post types if needed (only in WordPress)
        if (self::isWordPressAvailable()) {
            self::register_post_types();
            
            // Schedule events
            self::schedule_events();
            
            // Flush rewrite rules
            flush_rewrite_rules();
        }
        
        // Log activation
        self::log_activation();
    }
    
    /**
     * Check if WordPress functions are available
     */
    private static function isWordPressAvailable() {
        return function_exists('register_post_type') && function_exists('wp_schedule_event') && function_exists('flush_rewrite_rules');
    }

    /**
     * Clean up on deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        if (self::isWordPressAvailable()) {
            wp_clear_scheduled_hook('smo_integrations_cleanup');
        }
        
        // Clear any temporary transients
        self::cleanup_transients();
        
        // Log deactivation
        self::log_deactivation();
    }
    
    /**
     * Initialize integration system
     */
    public static function init() {
        // Include required files
        self::include_files();
        
        // Initialize components
        self::init_components();
        
        // Register hooks
        self::register_hooks();
    }
    
    /**
     * Include required files
     */
    private static function include_files() {
        require_once dirname(__FILE__) . '/IntegrationManager.php';
        require_once dirname(__FILE__) . '/WebhooksHandler.php';
        require_once dirname(__FILE__) . '/BaseIntegration.php';
        
        // Include all integration classes
        $integration_files = [
            'CanvaIntegration.php',
            'UnsplashIntegration.php',
            'PixabayIntegration.php',
            'DropboxIntegration.php',
            'GoogleDriveIntegration.php',
            'GooglePhotosIntegration.php',
            'OneDriveIntegration.php',
            'ZapierIntegration.php',
            'IFTTTIntegration.php',
            'FeedlyIntegration.php',
            'PocketIntegration.php'
        ];
        
        foreach ($integration_files as $file) {
            $file_path = dirname(__FILE__) . '/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Initialize components
     */
    private static function init_components() {
        // Initialize Integration Manager
        if (class_exists('\\SMO_Social\\Integrations\\IntegrationManager')) {
            new \SMO_Social\Integrations\IntegrationManager();
        }
        
        // Initialize Webhooks Handler
        if (class_exists('\\SMO_Social\\Integrations\\WebhooksHandler')) {
            \SMO_Social\Integrations\WebhooksHandler::init();
        }
    }
    
    /**
     * Register WordPress hooks
     */
    private static function register_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(SMO_SOCIAL_PLUGIN_FILE, [self::class, 'activate']);
        register_deactivation_hook(SMO_SOCIAL_PLUGIN_FILE, [self::class, 'deactivate']);
        
        // Database upgrade check
        add_action('plugins_loaded', [self::class, 'check_database_version']);
        
        // Cleanup events
        add_action('smo_integrations_cleanup', [self::class, 'cleanup_old_data']);
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_options = [
            'smo_integrations_enabled' => true,
            'smo_integrations_rate_limit' => 60, // 60 requests per hour
            'smo_integrations_log_retention' => 30, // 30 days
            'smo_integrations_auto_cleanup' => true,
            'smo_integrations_webhook_enabled' => true
        ];
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Register custom post types
     */
    private static function register_post_types() {
        // Register imported content post type if needed
        register_post_type('smo_imported_content', [
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'label' => 'Imported Content',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'edit_posts',
                'edit_posts' => 'edit_posts',
                'edit_others_posts' => 'edit_others_posts',
                'publish_posts' => 'publish_posts',
                'read_private_posts' => 'read_private_posts',
                'delete_posts' => 'delete_posts',
                'delete_private_posts' => 'delete_private_posts',
                'delete_published_posts' => 'delete_published_posts',
                'delete_others_posts' => 'delete_others_posts',
                'edit_private_posts' => 'edit_private_posts',
                'edit_published_posts' => 'edit_published_posts'
            ]
        ]);
    }
    
    /**
     * Schedule background events
     */
    private static function schedule_events() {
        if (!self::isWordPressAvailable()) {
            return;
        }
        
        // Schedule cleanup event
        if (!wp_next_scheduled('smo_integrations_cleanup')) {
            wp_schedule_event(time(), 'daily', 'smo_integrations_cleanup');
        }
    }
    
    /**
     * Check database version and update if needed
     */
    public static function check_database_version() {
        IntegrationSchema::maybe_create_tables();
    }
    
    /**
     * Clean up old integration data
     */
    public static function cleanup_old_data() {
        if (!self::isWordPressAvailable()) {
            return;
        }
        
        global $wpdb;
        
        $log_retention = get_option('smo_integrations_log_retention', 30);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$log_retention} days"));
        
        // Clean up old logs
        $table = $wpdb->prefix . 'smo_integration_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Clean up old rate limiting transients
        self::cleanup_transients();
        
        // Log cleanup
        error_log('SMO Social: Integration data cleanup completed');
    }
    
    /**
     * Clean up transients
     */
    private static function cleanup_transients() {
        global $wpdb;
        
        // Clean up rate limiting transients older than 24 hours
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_smo_integration_rate_limit_%' 
             AND option_name NOT LIKE '_transient_timeout_smo_integration_rate_limit_%'
             AND option_value < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        // Clean up timeout transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_smo_integration_rate_limit_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
    }
    
    /**
     * Log activation event
     */
    private static function log_activation() {
        global $wpdb;
        
        // Log to WordPress options for now
        $activation_log = get_option('smo_integrations_activation_log', []);
        $activation_log[] = [
            'timestamp' => current_time('mysql'),
            'version' => SMO_SOCIAL_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        ];
        
        // Keep only last 10 activation logs
        if (count($activation_log) > 10) {
            $activation_log = array_slice($activation_log, -10);
        }
        
        update_option('smo_integrations_activation_log', $activation_log);
    }
    
    /**
     * Log deactivation event
     */
    private static function log_deactivation() {
        global $wpdb;
        
        $deactivation_log = get_option('smo_integrations_deactivation_log', []);
        $deactivation_log[] = [
            'timestamp' => current_time('mysql'),
            'version' => SMO_SOCIAL_VERSION
        ];
        
        // Keep only last 10 deactivation logs
        if (count($deactivation_log) > 10) {
            $deactivation_log = array_slice($deactivation_log, -10);
        }
        
        update_option('smo_integrations_deactivation_log', $deactivation_log);
    }
    
    /**
     * Get integration statistics
     */
    public static function get_integration_stats() {
        global $wpdb;
        
        $stats = [];
        
        // Total integrations connected
        $table = $wpdb->prefix . 'smo_integrations';
        $stats['total_connections'] = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'connected'");
        
        // Total imported content
        $content_table = $wpdb->prefix . 'smo_imported_content';
        $stats['total_imported'] = $wpdb->get_var("SELECT COUNT(*) FROM $content_table");
        
        // Recent activity (last 7 days)
        $logs_table = $wpdb->prefix . 'smo_integration_logs';
        $stats['recent_activity'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs_table WHERE created_at > %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        // Active integrations
        $stats['active_integrations'] = $wpdb->get_var("SELECT COUNT(DISTINCT integration_id) FROM $table WHERE status = 'connected'");
        
        return $stats;
    }
}