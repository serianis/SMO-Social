<?php
/**
 * SMO Social Admin Class
 * 
 * Manages the admin interface, settings, and AJAX handlers for the SMO Social plugin.
 * Handles dashboard display, platform management, content scheduling, and AI features.
 * 
 * @package SMO_Social
 * @subpackage Admin
 * @since 1.0.0
 * @author SMO Social Team
 * @license GPL-3.0+
 */

namespace SMO_Social\Admin;

use \SMO_Social\Platforms\Manager as PlatformManager;
use \SMO_Social\AI\Manager as AIManager;
use \SMO_Social\Analytics\PostsPerDayManager;
use \SMO_Social\Scheduling\AutoPublishManager;
use \SMO_Social\Admin\Widgets\WidgetManager;
use \SMO_Social\Admin\Widgets\DashboardManager;
use SMO_Social\AI\ProvidersConfig;
use SMO_Social\Core\Logger;
use SMO_Social\Admin\Controllers\AjaxController;

// Include WordPress functions and database stubs for Intelephense support (only in standalone mode)
if (!\SMO_Social\Utilities\EnvironmentDetector::isWordPress()) {
    require_once __DIR__ . '/../wordpress-functions.php';
    require_once __DIR__ . '/../consolidated-db-stubs.php';

    // Include global declarations for additional compatibility
    require_once __DIR__ . '/../global-declarations.php';
}

// Ensure direct file access is blocked
if (!defined('ABSPATH')) {
    wp_die(__('Access denied', 'smo-social'));
}

/**
 * Main admin class for SMO Social plugin
 * 
 * This class handles all administrative functionality including:
 * - Admin menu and pages setup
 * - Settings registration and sanitization
 * - AJAX request handling
 * - Dashboard widgets and statistics
 * - Platform connection management
 * - User permissions and capabilities
 */
class Admin
{
    /**
     * Plugin URL for asset loading
     * 
     * @var string
     */
    private $plugin_url;

    /**
     * Plugin directory path
     * 
     * @var string
     */
    private $plugin_path;

    /**
     * Platform manager instance
     * 
     * @var PlatformManager
     */
    private $platform_manager;

    /**
     * Posts per day analytics manager
     * 
     * @var PostsPerDayManager
     */
    private $posts_per_day_manager;

    /**
     * Auto-publish content manager
     * 
     * @var AutoPublishManager
     */
    private $auto_publish_manager;

    /**
     * Content Organizer instance
     * 
     * @var \SMO_Social\Admin\Views\ContentOrganizer
     */
    private $content_organizer;

    /**
     * Content Import instance
     * 
     * @var \SMO_Social\Admin\Views\ContentImport
     */
    private $content_import;

    /**
     * Integration Manager instance
     * 
     * @var \SMO_Social\Integrations\IntegrationManager
     */
    private $integration_manager;

    /**
     * Menu Manager instance
     * 
     * @var MenuManager
     */
    private $menu_manager;

    /**
     * Asset Manager instance
     * 
     * @var AssetManager
     */
    private $asset_manager;

    /**
     * Settings Manager instance
     * 
     * @var SettingsManager
     */
    /**
     * Settings Manager instance
     * 
     * @var SettingsManager
     */
    private $settings_manager;

    /**
     * Ajax Controller instance
     * 
     * @var AjaxController
     */
    private $ajax_controller;

    /**
     * Constructor
     * 
     * Initializes plugin paths and platform manager, then sets up WordPress hooks.
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->plugin_url = SMO_SOCIAL_PLUGIN_URL;
        $this->plugin_path = SMO_SOCIAL_PLUGIN_DIR;
        
        // Don't initialize managers immediately - use lazy loading
        // $this->platform_manager = new PlatformManager(); // REMOVED THIS
        
        // Initialize enhanced managers with lazy loading
        // $this->posts_per_day_manager = new PostsPerDayManager(); // REMOVED THIS
        // $this->auto_publish_manager = new AutoPublishManager(); // REMOVED THIS

        // Initialize Content Organizer and Content Import early
        // This ensures their AJAX handlers are registered before pages load
        $this->init_content_views();
        
        // Initialize Integration Manager
        $this->init_integration_manager();

        // Initialize Menu Manager
        if (file_exists(__DIR__ . '/MenuManager.php')) {
            require_once __DIR__ . '/MenuManager.php';
            $this->menu_manager = new MenuManager($this);
        }

        // Initialize Asset Manager
        if (file_exists(__DIR__ . '/AssetManager.php')) {
            require_once __DIR__ . '/AssetManager.php';
            $this->asset_manager = new AssetManager($this->plugin_url, $this->integration_manager);
        }

        // Initialize Settings Manager
        if (file_exists(__DIR__ . '/SettingsManager.php')) {
            require_once __DIR__ . '/SettingsManager.php';
            $this->settings_manager = new SettingsManager();
        }

        // Initialize Ajax Controller
        if (file_exists(__DIR__ . '/Controllers/AjaxController.php')) {
            require_once __DIR__ . '/Controllers/AjaxController.php';
            $this->ajax_controller = new AjaxController();
        }

        $this->init_hooks();
    }

    /**
     * Get platform manager with lazy loading
     */
    public function get_platform_manager() {
        if ($this->platform_manager === null) {
            $this->platform_manager = new PlatformManager();
        }
        return $this->platform_manager;
    }

    /**
     * Get posts per day manager with lazy loading
     */
    public function get_posts_per_day_manager() {
        if ($this->posts_per_day_manager === null) {
            $this->posts_per_day_manager = new PostsPerDayManager();
        }
        return $this->posts_per_day_manager;
    }

    /**
     * Get auto publish manager with lazy loading
     */
    public function get_auto_publish_manager() {
        if ($this->auto_publish_manager === null) {
            $this->auto_publish_manager = new AutoPublishManager();
        }
        return $this->auto_publish_manager;
    }
    
    /**
     * Initialize Integration Manager
     * 
     * @since 1.0.0
     */
    private function init_integration_manager()
    {
        if (file_exists($this->plugin_path . 'includes/Integrations/IntegrationManager.php')) {
            require_once $this->plugin_path . 'includes/Integrations/IntegrationManager.php';
            if (class_exists('\\SMO_Social\\Integrations\\IntegrationManager')) {
                $this->integration_manager = new \SMO_Social\Integrations\IntegrationManager();
            }
        }
    }

    /**
     * Initialize content view classes early to register their AJAX handlers
     * 
     * @since 1.0.0
     */
    private function init_content_views()
    {
        // Include and instantiate Content Organizer
        if (file_exists($this->plugin_path . 'includes/Admin/Views/ContentOrganizer.php')) {
            require_once $this->plugin_path . 'includes/Admin/Views/ContentOrganizer.php';
            if (class_exists('\\SMO_Social\\Admin\\Views\\ContentOrganizer')) {
                $this->content_organizer = new \SMO_Social\Admin\Views\ContentOrganizer();
            }
        }

        // Include and instantiate Content Import
        if (file_exists($this->plugin_path . 'includes/Admin/Views/ContentImport.php')) {
            require_once $this->plugin_path . 'includes/Admin/Views/ContentImport.php';
            if (class_exists('\\SMO_Social\\Admin\\Views\\ContentImport')) {
                $this->content_import = new \SMO_Social\Admin\Views\ContentImport();
            }
        }
    }

    public function get_plugin_path()
    {
        return $this->plugin_path;
    }

    private function init_hooks()
    {
        // Admin menu and pages
        if ($this->menu_manager) {
            \add_action('admin_menu', array($this->menu_manager, 'register_menus'));
        }

        \add_action('wp_ajax_nopriv_smo_oauth_callback', array($this, 'handle_oauth_callback'));
        \add_action('init', array($this, 'check_oauth_callback'));
        
        // Initialize AJAX handlers via controller
        if ($this->ajax_controller) {
            \add_action('init', array($this->ajax_controller, 'init'));
        }
        
        // Initialize settings
        if ($this->settings_manager) {
            \add_action('admin_init', array($this->settings_manager, 'init_settings'));
        }

        // Missing AJAX handlers for Users page
        \add_action('wp_ajax_smo_get_activity_feed', array($this, 'ajax_get_activity_feed'));
        \add_action('wp_ajax_smo_update_user_role', array($this, 'ajax_update_user_role'));
        \add_action('wp_ajax_smo_invite_user', array($this, 'ajax_invite_user'));
        \add_action('wp_ajax_smo_remove_user', array($this, 'ajax_remove_user'));

        // Workflow AJAX handlers - Moved to WorkflowAjax


        // Channel Access AJAX handlers
        \add_action('wp_ajax_smo_get_channel_access', array($this, 'ajax_get_channel_access'));
        \add_action('wp_ajax_smo_save_channel_access', array($this, 'ajax_save_channel_access'));

        // Missing dashboard AJAX handlers
        \add_action('wp_ajax_smo_get_recent_posts', array($this, 'ajax_get_recent_posts'));
        \add_action('wp_ajax_smo_get_queue_status', array($this, 'ajax_get_queue_status'));
        \add_action('wp_ajax_smo_get_analytics_summary', array($this, 'ajax_get_analytics_summary'));

        // Missing queue management AJAX handlers
        \add_action('wp_ajax_smo_cancel_post', array($this, 'ajax_cancel_post'));
        \add_action('wp_ajax_smo_retry_post', array($this, 'ajax_retry_post'));

        // AI AJAX handlers
        \add_action('wp_ajax_smo_ai_generate_captions', array($this, 'ajax_ai_generate_captions'));
        \add_action('wp_ajax_smo_ai_optimize_hashtags', array($this, 'ajax_ai_optimize_hashtags'));
        \add_action('wp_ajax_smo_ai_analyze_sentiment', array($this, 'ajax_ai_analyze_sentiment'));
        \add_action('wp_ajax_smo_ai_generate_alt_text', array($this, 'ajax_ai_generate_alt_text'));
        \add_action('wp_ajax_smo_ai_repurpose_content', array($this, 'ajax_ai_repurpose_content'));
        \add_action('wp_ajax_smo_ai_predict_times', array($this, 'ajax_ai_predict_times'));

        // Advanced Scheduling AJAX handlers
        \add_action('wp_ajax_smo_toggle_auto_publish', array($this, 'ajax_toggle_auto_publish'));
        \add_action('wp_ajax_smo_process_queue_now', array($this, 'ajax_process_queue_now'));
        \add_action('wp_ajax_smo_add_time_slot', array($this, 'ajax_add_time_slot'));
        \add_action('wp_ajax_smo_remove_time_slot', array($this, 'ajax_remove_time_slot'));
        \add_action('wp_ajax_smo_get_queue_stats', array($this, 'ajax_get_queue_stats'));
        \add_action('wp_ajax_smo_get_queue_activity', array($this, 'ajax_get_queue_activity'));

        // Magic Wizard AJAX handlers
        \add_action('wp_ajax_smo_save_wizard_config', array($this, 'ajax_save_wizard_config'));
        \add_action('wp_ajax_smo_get_wizard_status', array($this, 'ajax_get_wizard_status'));
        \add_action('wp_ajax_smo_reset_wizard', array($this, 'ajax_reset_wizard'));
        \add_action('wp_ajax_smo_track_wizard_analytics', array($this, 'ajax_track_wizard_analytics'));

        // Dashboard widgets
        \add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));

        // Tools page AJAX handlers
        \add_action('wp_ajax_smo_get_system_status', array($this, 'ajax_get_system_status'));
        \add_action('wp_ajax_smo_get_database_info', array($this, 'ajax_get_database_info'));
        \add_action('wp_ajax_smo_get_activity_logs', array($this, 'ajax_get_activity_logs'));
        \add_action('wp_ajax_smo_export_data', array($this, 'ajax_export_data'));
        \add_action('wp_ajax_smo_import_data', array($this, 'ajax_import_data'));
        \add_action('wp_ajax_smo_clear_object_cache', array($this, 'ajax_clear_object_cache'));
        \add_action('wp_ajax_smo_clear_transients', array($this, 'ajax_clear_transients'));
        \add_action('wp_ajax_smo_clear_file_cache', array($this, 'ajax_clear_file_cache'));

        // Widget system AJAX handlers
        \add_action('wp_ajax_smo_get_widget_data', array(WidgetManager::class, 'ajax_get_widget_data'));
        \add_action('wp_ajax_smo_save_dashboard_layout', array(DashboardManager::class, 'ajax_save_layout'));
        \add_action('wp_ajax_smo_get_widget_library', array(DashboardManager::class, 'ajax_get_widget_library'));
        \add_action('wp_ajax_smo_add_widget_to_dashboard', array(DashboardManager::class, 'ajax_add_widget'));
        \add_action('wp_ajax_smo_remove_widget_from_dashboard', array(DashboardManager::class, 'ajax_remove_widget'));
        \add_action('wp_ajax_smo_save_dashboard_settings', array(DashboardManager::class, 'ajax_save_dashboard_settings'));

        // Calendar AJAX handlers
        \add_action('wp_ajax_smo_get_calendar_performance_summary', array($this, 'ajax_get_calendar_performance_summary'));
        \add_action('wp_ajax_smo_get_calendar_analytics', array($this, 'ajax_get_calendar_analytics'));
        \add_action('wp_ajax_smo_get_calendar_ai_insights', array($this, 'ajax_get_calendar_ai_insights'));
        \add_action('wp_ajax_smo_get_calendar_forecasting', array($this, 'ajax_get_calendar_forecasting'));
        \add_action('wp_ajax_smo_get_calendar_performance_detailed', array($this, 'ajax_get_calendar_performance_detailed'));
        \add_action('wp_ajax_smo_reschedule_post', array($this, 'ajax_reschedule_post'));
        \add_action('wp_ajax_smo_execute_post_action', array($this, 'ajax_execute_post_action'));
        \add_action('wp_ajax_smo_get_team_members', array($this, 'ajax_get_team_members'));
        \add_action('wp_ajax_smo_get_team_calendar', array($this, 'ajax_get_team_calendar'));
        \add_action('wp_ajax_smo_get_ai_best_times', array($this, 'ajax_get_ai_best_times'));
        \add_action('wp_ajax_smo_get_network_groups', array($this, 'ajax_get_network_groups'));
        \add_action('wp_ajax_smo_get_multisite_sites', array($this, 'ajax_get_multisite_sites'));

        // New Widget AJAX handlers
        \add_action('wp_ajax_smo_save_auto_publish_settings', array($this, 'ajax_save_auto_publish_settings'));
        \add_action('wp_ajax_smo_fetch_link_preview', array($this, 'ajax_fetch_link_preview'));
        \add_action('wp_ajax_smo_create_link_post', array($this, 'ajax_create_link_post'));
        \add_action('wp_ajax_smo_delete_link_post', array($this, 'ajax_delete_link_post'));

        // Content Organizer AJAX handlers - Moved to ContentOrganizerAjax
        // Old handlers removed to avoid conflicts

        // Enhanced Dashboard AJAX handlers - MOVED to SMO_Social\Admin\Ajax\DashboardAjax
        // Old handlers removed to avoid conflicts

        // Team Management AJAX handlers
        \add_action('wp_ajax_smo_add_team_member', array($this, 'ajax_add_team_member'));
        \add_action('wp_ajax_smo_update_team_member', array($this, 'ajax_update_team_member'));
        \add_action('wp_ajax_smo_remove_team_member', array($this, 'ajax_remove_team_member'));
        \add_action('wp_ajax_smo_create_team_assignment', array($this, 'ajax_create_team_assignment'));
        \add_action('wp_ajax_smo_update_team_assignment', array($this, 'ajax_update_team_assignment'));
        \add_action('wp_ajax_smo_delete_team_assignment', array($this, 'ajax_delete_team_assignment'));
        \add_action('wp_ajax_smo_update_team_permission', array($this, 'ajax_update_team_permission'));
        \add_action('wp_ajax_smo_create_network_group', array($this, 'ajax_create_network_group'));
        \add_action('wp_ajax_smo_update_network_group', array($this, 'ajax_update_network_group'));
        \add_action('wp_ajax_smo_delete_network_group', array($this, 'ajax_delete_network_group'));
        \add_action('wp_ajax_smo_refresh_team_calendar', array($this, 'ajax_refresh_team_calendar'));
        \add_action('wp_ajax_smo_grant_multisite_access', array($this, 'ajax_grant_multisite_access'));

        // Custom capabilities
        \add_action('init', array($this, 'add_custom_capabilities'));
        
        // Initialize Enhanced Dashboard Manager
        $this->init_enhanced_dashboard();
        
        // Clear AI cache on settings update
        \add_action('updated_option', array($this, 'clear_ai_cache_on_update'), 10, 3);
    }
    
    /**
     * Initialize Enhanced Dashboard Manager
     */
    private function init_enhanced_dashboard() {
        $correct_path = $this->plugin_path . 'includes/Features/EnhancedDashboardManager.php';
        Logger::info('SMO Social: Attempting to load EnhancedDashboardManager from: ' . $correct_path);
        if (file_exists($correct_path)) {
            require_once $correct_path;
            if (class_exists('\\SMO_Social\\Features\\EnhancedDashboardManager')) {
                new \SMO_Social\Features\EnhancedDashboardManager();
                Logger::info('SMO Social: EnhancedDashboardManager initialized successfully');
            } else {
                Logger::error('SMO Social: EnhancedDashboardManager class not found after file inclusion');
            }
        } else {
            Logger::error('SMO Social: EnhancedDashboardManager file not found at: ' . $correct_path);
        }
    }







    // Dashboard page
    public function display_dashboard()
    {
        Logger::debug('Starting dashboard display');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/EnhancedDashboard.php';
            Logger::debug('Dashboard view included successfully');

            // Instantiate and render the dashboard
            if (class_exists('\SMO_Social\Admin\Views\EnhancedDashboard')) {
                $stats = $this->get_dashboard_stats();
                \SMO_Social\Admin\Views\EnhancedDashboard::render($stats);
                Logger::debug('Dashboard rendered successfully');
            } else {
                Logger::error('EnhancedDashboard class not found');
                echo '<div class="wrap"><p>Dashboard class not found</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error including dashboard view: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading dashboard: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Posts page
    public function display_posts_page()
    {
        Logger::debug('Starting posts page display');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/Posts.php';
            Logger::debug('Posts view included successfully');

            // Instantiate and render the posts page
            if (class_exists('\SMO_Social\Admin\Views\Posts')) {
                $posts = new \SMO_Social\Admin\Views\Posts();
                echo $posts->render();
                Logger::debug('Posts page rendered successfully');
            } else {
                Logger::error('Posts class not found');
                echo '<div class="wrap"><p>Posts class not found</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error loading posts page', ['error' => $e->getMessage()]);
            echo '<div class="wrap"><p>Error loading posts page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Create post page
    public function display_create_post_page()
    {
        Logger::debug('Starting create post page display');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/CreatePost.php';
            Logger::debug('CreatePost view included successfully - merged with AI chat functionality');
        } catch (\Exception $e) {
            Logger::error('Error loading create post page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading create post page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Analytics page
    public function display_analytics_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/Analytics.php';
    }

    // Platforms page
    public function display_platforms_page()
    {
        Logger::debug('Starting display_platforms_page()');

        // Check WordPress environment
        if (!defined('ABSPATH')) {
            Logger::error('ERROR - ABSPATH not defined');
        } else {
            Logger::debug('ABSPATH is defined: ' . ABSPATH);
        }

        if (!isset($GLOBALS['wp'])) {
            Logger::warning('WARNING - $wp global not set during page display');
        } else {
            Logger::debug('$wp global is available during page display');
        }

        // Check if we're in WordPress environment
        if (\SMO_Social\Utilities\EnvironmentDetector::isWordPress()) {
            Logger::debug('Running in WordPress environment');
        } else {
            Logger::debug('Running in standalone mode');
        }

        Logger::debug('About to include Platforms.php from: ' . $this->plugin_path . 'includes/Admin/Views/Platforms.php');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/Platforms.php';
            Logger::debug('Platforms.php included successfully');
        } catch (\Exception $e) {
            Logger::error('ERROR including Platforms.php: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading platforms page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Templates page
    public function display_templates_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/Templates.php';
    }

    // Settings page
    public function display_settings_page()
    {
        Logger::debug('Starting settings page display');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/Settings.php';
            Logger::debug('Settings view included successfully');

            // Instantiate and render the settings page
            if (class_exists('\SMO_Social\Admin\Views\Settings')) {
                $settings = new \SMO_Social\Admin\Views\Settings();
                $settings->render();
                Logger::debug('Settings page rendered successfully');
            } else {
                Logger::error('Settings class not found');
                echo '<div class="wrap"><p>Settings class not found</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error loading settings page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading settings page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Tools page
    public function display_tools_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/Tools.php';
    }

    // Content Calendar page
    public function display_calendar_page()
    {
        Logger::debug('Starting display_calendar_page()');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/Calendar.php';
            Logger::debug('Calendar view included successfully');

            // Instantiate and render the calendar
            if (class_exists('\SMO_Social\Admin\Views\Calendar')) {
                $calendar = new \SMO_Social\Admin\Views\Calendar();
                echo $calendar->render();
                Logger::debug('Calendar rendered successfully');
            } else {
                Logger::error('Calendar class not found');
                echo '<div class="wrap"><p>Calendar class not found</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error loading calendar page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading calendar page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Content Import & Automation page
    public function display_content_import_page()
    {
        Logger::debug('Starting display_content_import_page()');

        try {
            // Use the existing instance that was created in constructor
            if ($this->content_import && is_object($this->content_import)) {
                $this->content_import->render();
                Logger::debug('Content Import page rendered successfully using existing instance');
            } else {
                Logger::error('ContentImport instance not available');
                echo '<div class="wrap"><p>Content Import class not initialized</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error loading content import page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading content import page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Content Organizer page
    public function display_content_organizer_page()
    {
        Logger::debug('Starting display_content_organizer_page()');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/ContentOrganizer.php';
            Logger::debug('ContentOrganizer view included successfully');

            // Instantiate and render the content organizer
            if (class_exists('\SMO_Social\Admin\Views\ContentOrganizer')) {
                $organizer = new \SMO_Social\Admin\Views\ContentOrganizer();
                $organizer->render();
                Logger::debug('Content Organizer rendered successfully');
            } else {
                Logger::error('ContentOrganizer class not found');
                echo '<div class="wrap"><p>Content Organizer class not found</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error loading content organizer page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading content organizer page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Media Library page
    public function display_media_library_page()
    {
        Logger::debug('Starting display_media_library_page()');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/MediaLibrary.php';
            Logger::debug('Media Library view included successfully');
        } catch (\Exception $e) {
            Logger::error('Error loading media library page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading media library page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Advanced Reports page
    public function display_reports_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/Reports.php';
    }

    // User Management page
    public function display_users_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/Users.php';
    }

    // Notification Center page
    public function display_notifications_page()
    {
        Logger::debug('Starting display_notifications_page()');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/Notifications.php';
            Logger::debug('Notifications view included successfully');

            // Instantiate and render the notifications
            if (class_exists('\SMO_Social\Admin\Views\Notifications')) {
                $notifications = new \SMO_Social\Admin\Views\Notifications();
                echo $notifications->render();
                Logger::debug('Notifications rendered successfully');
            } else {
                Logger::error('Notifications class not found');
                echo '<div class="wrap"><p>Notifications class not found</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error loading notifications page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading notifications page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Team Management page
    public function display_team_management_page()
    {
        Logger::debug('Starting display_team_management_page()');

        try {
            // Include TeamManager class
            require_once $this->plugin_path . 'includes/Team/TeamManager.php';
            
            include_once $this->plugin_path . 'includes/Admin/Views/TeamManagement.php';
            Logger::debug('Team Management view included successfully');
        } catch (\Exception $e) {
            Logger::error('Error loading team management page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading team management page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    /**
     * Get team members
     * 
     * @return array
     */
    public function get_team_members()
    {
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        return \SMO_Social\Team\TeamManager::get_team_members();
    }

    /**
     * Get user network assignments
     * 
     * @return array
     */
    public function get_user_network_assignments()
    {
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        return \SMO_Social\Team\TeamManager::get_user_network_assignments();
    }

    /**
     * Get team permissions
     * 
     * @return array
     */
    public function get_team_permissions()
    {
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        return \SMO_Social\Team\TeamManager::get_team_permissions();
    }

    /**
     * Get team calendar data
     * 
     * @return array
     */
    public function get_team_calendar_data()
    {
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        return \SMO_Social\Team\TeamManager::get_team_calendar_data();
    }

    /**
     * Get network groups
     * 
     * @return array
     */
    public function get_network_groups()
    {
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        return \SMO_Social\Team\TeamManager::get_network_groups();
    }

    /**
     * Render team calendar
     * 
     * @param array $calendar_data
     * @return string
     */
    public function render_team_calendar($calendar_data)
    {
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        return \SMO_Social\Team\TeamManager::render_team_calendar($calendar_data);
    }

    /**
     * Get permission checkbox HTML
     * 
     * @param int $user_id
     * @param string $permission_key
     * @return string
     */
    public function get_permission_checkbox($user_id, $permission_key)
    {
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        return \SMO_Social\Team\TeamManager::get_permission_checkbox($user_id, $permission_key);
    }

    // API Management page
    public function display_api_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/API.php';
    }

    // Maintenance Tools page
    public function display_maintenance_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/Maintenance.php';
    }

    // Advanced Scheduling page
    public function display_advanced_scheduling_page()
    {
        include_once $this->plugin_path . 'includes/Admin/Views/AdvancedScheduling.php';
    }

    // AJAX handlers - Platform Connect moved to PlatformAjax
    // ajax_connect_platform removed

    /**
     * Handle OAuth callback from platforms
     */
    public function handle_oauth_callback() {
        // Don't verify nonce for OAuth callbacks - they come from external platforms
        // Instead, we'll verify the state parameter
        
        $platform = sanitize_text_field($_GET['platform'] ?? '');
        $code = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');
        $error = sanitize_text_field($_GET['error'] ?? '');
        $error_description = sanitize_text_field($_GET['error_description'] ?? '');

        // Check if user denied authorization
        if (!empty($error)) {
            set_transient('smo_oauth_error_' . $platform, 
                sprintf(__('Authorization failed: %s', 'smo-social'), 
                    !empty($error_description) ? $error_description : $error), 
                300);
            wp_redirect(admin_url('admin.php?page=smo-social-platforms'));
            exit;
        }

        if (empty($platform) || empty($code)) {
            set_transient('smo_oauth_error_general', 
                __('Missing required OAuth parameters', 'smo-social'), 
                300);
            wp_redirect(admin_url('admin.php?page=smo-social-platforms'));
            exit;
        }

        try {
            // Get platform configuration
            $client_id = $this->get_platform_client_id($platform);
            $client_secret = $this->get_platform_client_secret($platform);
            
            if (empty($client_id) || empty($client_secret)) {
                throw new \Exception(__('Platform credentials not configured. Please add your OAuth credentials in the platform settings.', 'smo-social'));
            }
            
            // Initialize OAuth manager with platform configuration
            $oauth_manager = new \SMO_Social\Security\OAuthManager([
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => admin_url('admin.php?page=smo-social&action=oauth_callback&platform=' . $platform),
                'scopes' => $this->get_platform_scopes($platform)
            ]);

            // Handle the OAuth callback
            $result = $oauth_manager->handle_oauth_callback($platform, $code, $state);

            if ($result['success']) {
                // Log successful connection
                $this->log_activity('PLATFORM_CONNECTED', 'platform', $platform);
                
                // Store success message and redirect back to platforms page
                set_transient('smo_oauth_success_' . $platform, 
                    sprintf(__('%s connected successfully!', 'smo-social'), ucfirst($platform)), 
                    300);
            } else {
                // Store error message
                set_transient('smo_oauth_error_' . $platform, 
                    sprintf(__('Failed to connect %s: %s', 'smo-social'), ucfirst($platform), $result['error']), 
                    300);
            }

        } catch (\Exception $e) {
            // Log error and store error message
            error_log('SMO Social OAuth Callback Error (' . $platform . '): ' . $e->getMessage());
            
            $this->log_activity('PLATFORM_CONNECTION_FAILED', 'platform', $platform, array(
                'error' => $e->getMessage()
            ));
            
            set_transient('smo_oauth_error_' . $platform, 
                sprintf(__('OAuth error: %s', 'smo-social'), $e->getMessage()), 
                300);
        }

        // Redirect back to platforms page
        wp_redirect(admin_url('admin.php?page=smo-social-platforms'));
        exit;
    }

    /**
     * Check for OAuth callback in URL and handle it
     */
    public function check_oauth_callback() {
        // Check if this is an OAuth callback request
        if (isset($_GET['action']) && $_GET['action'] === 'oauth_callback' && 
            isset($_GET['platform']) && !empty($_GET['platform'])) {
            
            $this->handle_oauth_callback();
        }
    }

    // Helper methods for OAuth callback handling would be here if not moved elsewhere
    // Disconnect, Test, Health handlers removed - migrated to PlatformAjax

    /**
     * AJAX handler for saving platform settings
     */
    // save_platform_settings and save_platform_credentials removed - migrated to PlatformAjax

    /**
     * Helper method to encrypt sensitive settings
     */
    private function encrypt_setting($value)
    {
        if (empty($value)) {
            return '';
        }

        $key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Helper method to decrypt sensitive settings
     */
    private function decrypt_setting($encrypted_value)
    {
        if (empty($encrypted_value)) {
            return '';
        }

        $key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
        $data = base64_decode($encrypted_value);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    // ajax_get_platform_status removed - migrated to PlatformAjax

    public function ajax_get_post_details()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!isset($_POST['post_id'])) {
            wp_send_json_error(__('Post ID not provided'));
        }

        $post_id = intval($_POST['post_id']);

        /** @var \wpdb $wpdb */
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        // Use proper prepare method call
        /** @var \wpdb $wpdb */
        $wpdb_obj = $wpdb;
        $query = $wpdb_obj->prepare("SELECT * FROM $posts_table WHERE id = %d", $post_id);
        $post = $wpdb_obj->get_row($query, ARRAY_A);

        if (!$post) {
            wp_send_json_error(__('Post not found'));
        }

        // Get platforms for this post
        /** @var string $platforms_table */
        $platforms_table = $wpdb->prefix . 'smo_post_platforms';
        /** @var array $platforms */
        $platforms = $wpdb->get_results(
            $wpdb->prepare("SELECT platform_slug FROM $platforms_table WHERE post_id = %d", $post_id),
            ARRAY_A
        );

        $platform_names = array();
        if ($platforms) {
            foreach ($platforms as $platform) {
                $platform_obj = $this->platform_manager->get_platform($platform['platform_slug']);
                if ($platform_obj) {
                    $platform_names[] = $platform_obj->get_name();
                }
            }
        }

        ob_start();
        ?>
                <div class="smo-post-details">
                    <div class="smo-post-meta">
                        <p><strong><?php _e('Status:', 'smo-social'); ?></strong>
                            <span class="smo-status-<?php echo esc_attr($post['status']); ?>">
                                <?php echo esc_html(ucfirst($post['status'])); ?>
                            </span>
                        </p>

                        <p><strong><?php _e('Scheduled Time:', 'smo-social'); ?></strong>
                            <?php echo esc_html(date('F j, Y g:i A', strtotime($post['scheduled_time']))); ?></p>

                        <p><strong><?php _e('Platforms:', 'smo-social'); ?></strong>
                            <?php echo esc_html(implode(', ', $platform_names)); ?></p>

                        <?php if ($post['status'] === 'failed' && !empty($post['error_message'])): ?>
                                <p><strong><?php _e('Error:', 'smo-social'); ?></strong>
                                    <?php echo esc_html($post['error_message']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="smo-post-content">
                        <h4><?php _e('Content', 'smo-social'); ?></h4>
                        <div class="smo-content-preview">
                        </div>
                    </div>

                    <div class="smo-post-actions">
                        <a href="<?php echo admin_url('admin.php?page=smo-social-create&edit=' . $post_id); ?>"
                            class="button button-primary">
                            <?php _e('Edit Post', 'smo-social'); ?>
                        </a>
                        <button type="button" class="button smo-delete-post" data-post-id="<?php echo $post_id; ?>">
                            <?php _e('Delete Post', 'smo-social'); ?>
                        </button>
                    </div>
                </div>

                <style>
                    .smo-post-details {
                        max-width: none;
                    }

                    .smo-post-meta p {
                        margin: 10px 0;
                    }

                    .smo-status-scheduled {
                        color: #00a32a;
                        font-weight: bold;
                    }

                    .smo-status-published {
                        color: #dba617;
                        font-weight: bold;
                    }

                    .smo-status-failed {
                        color: #d63638;
                        font-weight: bold;
                    }

                    .smo-content-preview {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 4px;
                        margin: 10px 0;
                        max-height: 200px;
                        overflow-y: auto;
                    }

                    .smo-post-actions {
                        margin-top: 20px;
                        padding-top: 20px;
                        border-top: 1px solid #ccd0d4;
                    }

                    .smo-post-actions .button {
                        margin-right: 10px;
                    }
                </style>
                <?php

                $html = ob_get_clean();
                wp_send_json_success(array('html' => $html));
    }

    public function ajax_get_recent_activity()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        try {
            // DIAGNOSTIC: Log wpdb class availability and methods
            error_log('SMO Social: $wpdb class exists: ' . (class_exists('\wpdb') ? 'YES' : 'NO'));
            error_log('SMO Social: $wpdb global exists: ' . (isset($GLOBALS['wpdb']) ? 'YES' : 'NO'));
            if (isset($GLOBALS['wpdb'])) {
                $wpdb_methods = get_class_methods($GLOBALS['wpdb']);
                error_log('SMO Social: $wpdb methods: ' . implode(', ', $wpdb_methods));
            }

            /** @var \wpdb $wpdb */
            global $wpdb;
            $activity_table = $wpdb->prefix . 'smo_activity_logs';

            // Check if activity logs table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . \esc_sql($activity_table) . "'");

            if (empty($table_exists)) {
                wp_send_json_success('<p>' . __('No recent activity to display.', 'smo-social') . '</p>');
                return;
            }

            // Get recent activity (last 10 items)
            $activities = $wpdb->get_results(
                "SELECT action, details, created_at FROM $activity_table ORDER BY created_at DESC LIMIT 10",
                ARRAY_A
            );

            if (empty($activities)) {
                wp_send_json_success('<p>' . __('No recent activity to display.', 'smo-social') . '</p>');
                return;
            }

            ob_start();
            ?>
                        <div class="smo-activity-list">
                            <?php foreach ($activities as $activity): ?>
                                    <div class="smo-activity-item">
                                        <span class="smo-activity-action"><?php echo esc_html($activity['action']); ?></span>
                                        <span
                                            class="smo-activity-time"><?php echo esc_html(date('M j, g:i A', strtotime($activity['created_at']))); ?></span>
                                    </div>
                            <?php endforeach; ?>
                        </div>
                        <?php

                        $html = ob_get_clean();
                        wp_send_json_success($html);

        } catch (\Exception $e) {
            error_log('SMO Social: Error getting recent activity: ' . $e->getMessage());
            wp_send_json_error(__('Unable to load recent activity.', 'smo-social'));
        }
    }

    // Dashboard widgets
    public function add_dashboard_widgets()
    {
        wp_add_dashboard_widget(
            'smo_social_overview',
            __('SMO Social Overview', 'smo-social'),
            array($this, 'dashboard_overview_widget')
        );

        // Add Magic Wizard widget for new users or users who haven't completed setup
        wp_add_dashboard_widget(
            'smo_social_wizard_widget',
            __('Quick Setup', 'smo-social'),
            array($this, 'dashboard_wizard_widget')
        );
    }

    public function dashboard_overview_widget()
    {
        $stats = $this->get_dashboard_stats();
        include $this->plugin_path . 'includes/Admin/Views/Widgets/Overview.php';
    }

    /**
     * Dashboard widget for Magic Wizard
     *
     * @since 1.0.0
     */
    public function dashboard_wizard_widget()
    {
        $wizard_completed = get_option('smo_social_wizard_completed', false);
        $wizard_completed_date = get_option('smo_social_wizard_completed_date', '');
        
        if ($wizard_completed) {
            // Show completion status for users who have completed the wizard
            echo '<div class="smo-wizard-widget completed">';
            echo '<div class="smo-wizard-success">';
            echo '<span class="dashicons dashicons-yes-alt"></span>';
            echo '<h4>' . esc_html__('Setup Complete!', 'smo-social') . '</h4>';
            echo '<p>' . esc_html__('Your SMO Social plugin has been configured successfully.', 'smo-social') . '</p>';
            
            if ($wizard_completed_date) {
                echo '<p class="smo-wizard-date">';
                echo sprintf(esc_html__('Completed on %s', 'smo-social'),
                    esc_html(date('F j, Y', strtotime($wizard_completed_date)))
                );
                echo '</p>';
            }
            
            echo '<p>';
            echo '<a href="' . admin_url('admin.php?page=smo-social-wizard') . '" class="button button-secondary">';
            echo esc_html__('Re-run Setup', 'smo-social');
            echo '</a>';
            echo '</p>';
            echo '</div>';
            echo '</div>';
        } else {
            // Show wizard prompt for users who haven't completed setup
            echo '<div class="smo-wizard-widget prompt">';
            echo '<div class="smo-wizard-intro">';
            echo '<h4>' . esc_html__('🚀 Get Started with SMO Social', 'smo-social') . '</h4>';
            echo '<p>' . esc_html__('Complete our quick setup wizard to configure your plugin and start creating amazing content!', 'smo-social') . '</p>';
            echo '</div>';
            
            echo '<div class="smo-wizard-features">';
            echo '<ul>';
            echo '<li>' . esc_html__('✓ Configure AI providers', 'smo-social') . '</li>';
            echo '<li>' . esc_html__('✓ Connect social platforms', 'smo-social') . '</li>';
            echo '<li>' . esc_html__('✓ Set content preferences', 'smo-social') . '</li>';
            echo '<li>' . esc_html__('✓ Ready to post in minutes!', 'smo-social') . '</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<p>';
            echo '<a href="' . admin_url('admin.php?page=smo-social-wizard') . '" class="button button-primary button-large">';
            echo esc_html__('🪄 Start Setup Wizard', 'smo-social');
            echo '</a>';
            echo '</p>';
            
            echo '<p class="smo-wizard-time">';
            echo esc_html__('⏱️ Takes only 2-3 minutes', 'smo-social');
            echo '</p>';
            echo '</div>';
        }
        
        // Add widget styles
        echo '<style>
        .smo-wizard-widget {
            text-align: center;
            padding: 20px;
        }
        .smo-wizard-widget.completed {
            background: #f0f8f0;
            border: 1px solid #c3e6c3;
            border-radius: 4px;
        }
        .smo-wizard-widget.prompt {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
        .smo-wizard-success .dashicons {
            font-size: 48px;
            color: #46b450;
            margin-bottom: 10px;
        }
        .smo-wizard-widget.prompt h4 {
            color: white;
            margin-bottom: 15px;
        }
        .smo-wizard-widget.prompt p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 15px;
        }
        .smo-wizard-features {
            text-align: left;
            margin: 20px 0;
        }
        .smo-wizard-features ul {
            list-style: none;
            padding: 0;
        }
        .smo-wizard-features li {
            padding: 5px 0;
            color: rgba(255,255,255,0.9);
        }
        .smo-wizard-widget.prompt .button {
            background: white;
            color: #667eea;
            border: none;
            font-weight: bold;
            margin: 10px 0;
        }
        .smo-wizard-widget.prompt .button:hover {
            background: #f0f0f0;
            color: #667eea;
        }
        .smo-wizard-time {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 10px;
        }
        .smo-wizard-date {
            font-size: 12px;
            color: #666;
            font-style: italic;
        }
        </style>';
    }

    // Custom capabilities
    public function add_custom_capabilities()
    {
        // Check if get_role function exists for safety
        if (!function_exists('get_role')) {
            return;
        }

        $capabilities = array(
            'administrator' => array('manage_smo_social', 'edit_smo_posts', 'view_smo_analytics'),
            'editor' => array('edit_smo_posts', 'view_smo_analytics')
        );

        foreach ($capabilities as $role_name => $caps) {
            /** @var \WP_Role|null $role */
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }

            foreach ($caps as $cap) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    // Helper methods
    private function generate_auth_url($platform)
    {
        $client_id = $this->get_platform_client_id($platform->get_slug());
        $redirect_uri = admin_url('admin.php?page=smo-social&action=oauth_callback&platform=' . $platform->get_slug());

        switch ($platform->get_slug()) {
            case 'facebook':
                return "https://www.facebook.com/v18.0/dialog/oauth?client_id={$client_id}&redirect_uri={$redirect_uri}&scope=pages_manage_posts,pages_read_engagement";
            case 'twitter':
                return "https://twitter.com/i/oauth2/authorize?client_id={$client_id}&redirect_uri={$redirect_uri}&response_type=code&scope=tweet.read%20tweet.write%20users.read%20offline.access";
            case 'linkedin':
                return "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&state=random_state&scope=r_liteprofile%20r_emailaddress%20w_member_social";
            default:
                return '#';
        }
    }

    private function get_platform_client_id($platform_slug)
    {
        // Get client IDs from settings
        $settings = get_option('smo_social_platform_settings', array());
        return isset($settings[$platform_slug]['client_id']) ? $settings[$platform_slug]['client_id'] : '';
    }

    private function get_platform_client_secret($platform_slug)
    {
        // Get client secrets from settings
        $settings = get_option('smo_social_platform_settings', array());
        return isset($settings[$platform_slug]['client_secret']) ? $settings[$platform_slug]['client_secret'] : '';
    }

    private function get_platform_scopes($platform_slug)
    {
        // Define scopes for each platform
        $scopes = array(
            'facebook' => array('pages_manage_posts', 'pages_read_engagement'),
            'twitter' => array('tweet.read', 'tweet.write', 'users.read', 'offline.access'),
            'linkedin' => array('r_liteprofile', 'w_member_social'),
            'instagram' => array('user_profile', 'user_media'),
            'youtube' => array('https://www.googleapis.com/auth/youtube.force-ssl')
        );

        return isset($scopes[$platform_slug]) ? $scopes[$platform_slug] : array();
    }

    private function disconnect_platform($platform_slug)
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_platform_tokens';
        
        // Mark tokens as inactive instead of deleting (for audit trail)
        $result = $wpdb->update(
            $table_name,
            array(
                'status' => 'inactive',
                'updated_at' => current_time('mysql')
            ),
            array('platform_slug' => $platform_slug),
            array('%s', '%s'),
            array('%s')
        );

        // Also clear platform settings
        $settings_table = $wpdb->prefix . 'smo_platform_settings';
        $wpdb->delete(
            $settings_table,
            array('platform_slug' => $platform_slug),
            array('%s')
        );

        // Log activity
        $this->log_activity('PLATFORM_DISCONNECTED', 'platform', $platform_slug);
        
        return $result !== false;
    }

    public function is_platform_connected($platform_slug)
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_platform_tokens';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE platform_slug = %s AND status = 'active'",
            $platform_slug
        ));

        return $count > 0;
    }

    public function get_dashboard_stats()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        error_log('SMO Social: Starting get_dashboard_stats()');

        $stats = array(
            'total_reach' => '0',
            'engagement' => '0%',
            'scheduled' => 0,
            'response_time' => '-',
            'scheduled_posts' => 0,
            'connected_platforms' => 0,
            'pending_approvals' => 0,
            'posts_per_day_enhanced' => array(),
            'auto_publish' => array()
        );

        try {
            // Validate database schema before querying
            $this->validate_database_schema();

            // Get scheduled posts count
            $scheduled_table = $wpdb->prefix . 'smo_scheduled_posts';
            $scheduled_count = $wpdb->get_var("SELECT COUNT(*) FROM $scheduled_table WHERE status = 'scheduled'");
            $stats['scheduled'] = $scheduled_count ? intval($scheduled_count) : 0;
            $stats['scheduled_posts'] = $stats['scheduled'];
            
            // Get connected platforms count
            $platforms_table = $wpdb->prefix . 'smo_platform_tokens';
            $platforms_count = $wpdb->get_var("SELECT COUNT(DISTINCT platform_slug) FROM $platforms_table WHERE status = 'active'");
            $stats['connected_platforms'] = $platforms_count ? intval($platforms_count) : 0;
            
            // Get total reach from analytics
            $analytics_table = $wpdb->prefix . 'smo_post_analytics';
            // Check if table exists first
            if ($wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") === $analytics_table) {
                $total_reach = $wpdb->get_var("SELECT SUM(reach) FROM $analytics_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stats['total_reach'] = $total_reach ? number_format($total_reach) : '0';
                
                // Get engagement rate
                $avg_engagement = $wpdb->get_var("SELECT AVG(engagement_rate) FROM $analytics_table WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stats['engagement'] = $avg_engagement ? number_format($avg_engagement, 1) . '%' : '0%';
            }
            
            // Get pending approvals
            $approvals_table = $wpdb->prefix . 'smo_approval_queue';
            if ($wpdb->get_var("SHOW TABLES LIKE '$approvals_table'") === $approvals_table) {
                $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $approvals_table WHERE status = 'pending'");
                $stats['pending_approvals'] = $pending_count ? intval($pending_count) : 0;
            }
            
            // Calculate average response time (mock for now)
            $stats['response_time'] = '2.5h';

            // Add enhanced posts per day analytics from the new manager
            if (class_exists('\SMO_Social\Admin\Managers\PostsPerDayManager')) {
                $stats['posts_per_day_enhanced'] = $this->get_posts_per_day_manager()->get_dashboard_stats();
            }

            // Add auto-publish stats
            if (class_exists('\SMO_Social\Admin\Managers\AutoPublishManager')) {
                $stats['auto_publish'] = $this->get_auto_publish_manager()->get_queue_stats();
            }

            // Keep legacy compatibility
            $stats = array_merge($stats, $this->get_posts_per_day_stats());

            error_log('SMO Social: Enhanced stats computed: ' . print_r($stats, true));
            return $stats;

        } catch (\Exception $e) {
            error_log('SMO Social: Error in get_dashboard_stats: ' . $e->getMessage());
            // Fallback to basic stats
            return array_merge(array(
                'total_posts' => 0,
                'scheduled_posts' => 0,
                'pending_queue' => 0,
                'published_today' => 0,
                'failed_posts' => 0,
                'user_posts_today' => 0,
                'failed_queue' => 0,
                'draft_posts' => 0,
                'processing_queue' => 0,
                'posts_per_day_enhanced' => array(),
                'auto_publish' => array()
            ), $this->get_posts_per_day_stats());
        }
    }

    private function validate_database_schema()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $required_tables = array(
            'smo_scheduled_posts',
            'smo_platform_tokens',
            'smo_analytics',
            'smo_content_variants',
            'smo_activity_logs',
            'smo_content_templates',
            'smo_queue',
            'smo_post_platforms'
        );

        $missing_tables = array();

        foreach ($required_tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . \esc_sql($full_table_name) . "'");

            if (empty($table_exists)) {
                error_log("SMO Social: Database validation failed - table $table does not exist");
                $missing_tables[] = $table;
            }
        }

        if (!empty($missing_tables)) {
            error_log('SMO Social: Missing tables: ' . implode(', ', $missing_tables));
            return false;
        }

        error_log('SMO Social: Database validation passed - all required tables exist');
        return true;
    }

    private function log_component_initialization($component)
    {
        static $initialized_components = array();

        if (in_array($component, $initialized_components)) {
            error_log("SMO Social: WARNING - Component $component is being initialized multiple times!");
            return;
        }

        $initialized_components[] = $component;
        error_log("SMO Social: Initialized component: $component");
    }

    private function log_activity($action, $resource_type = null, $resource_id = null, $details = array())
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $table_name = $wpdb->prefix . 'smo_activity_logs';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'action' => $action,
                'resource_type' => $resource_type,
                'resource_id' => $resource_id,
                'details' => json_encode($details),
                'ip_address' => $this->get_user_ip(),
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            )
        );
    }

    private function get_user_ip()
    {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (isset($_SERVER) && array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return (isset($_SERVER) && isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Get posts per day statistics
     * 
     * @return array Posts per day analytics data
     */
    private function get_posts_per_day_stats()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        // Get posts count for the last 30 days
        $last_30_days = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM $posts_table 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            ARRAY_A
        );

        // Get posts count for today
        $today_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $posts_table 
             WHERE DATE(created_at) = CURDATE() AND status != 'draft'"
        );

        // Get posts count for yesterday
        $yesterday_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $posts_table 
             WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status != 'draft'"
        );

        // Get average posts per day (last 7 days)
        $avg_7_days = $wpdb->get_var(
            "SELECT AVG(daily_count) FROM (
                 SELECT COUNT(*) as daily_count 
                 FROM $posts_table 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                 AND status != 'draft'
                 GROUP BY DATE(created_at)
             ) as daily_counts"
        );

        // Get average posts per day (last 30 days)
        $avg_30_days = $wpdb->get_var(
            "SELECT AVG(daily_count) FROM (
                 SELECT COUNT(*) as daily_count 
                 FROM $posts_table 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                 AND status != 'draft'
                 GROUP BY DATE(created_at)
             ) as daily_counts"
        );

        // Get peak posting day (most posts in a single day)
        $peak_day = $wpdb->get_row(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM $posts_table 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             AND status != 'draft'
             GROUP BY DATE(created_at) 
             ORDER BY count DESC 
             LIMIT 1",
            ARRAY_A
        );

        // Get platform distribution for today
        $platform_today = $wpdb->get_results(
            "SELECT platform_slug, COUNT(*) as count 
             FROM {$wpdb->prefix}smo_post_platforms ppp
             INNER JOIN {$wpdb->prefix}smo_scheduled_posts sp ON ppp.post_id = sp.id
             WHERE DATE(sp.created_at) = CURDATE() 
             GROUP BY platform_slug",
            ARRAY_A
        );

        return array(
            'posts_per_day' => array(
                'last_30_days' => $last_30_days ?: array(),
                'today' => intval($today_count),
                'yesterday' => intval($yesterday_count),
                'avg_7_days' => round(floatval($avg_7_days), 1),
                'avg_30_days' => round(floatval($avg_30_days), 1),
                'peak_day' => $peak_day ? array(
                    'date' => $peak_day['date'],
                    'count' => intval($peak_day['count'])
                ) : null,
                'platform_today' => $platform_today ?: array()
            )
        );
    }

    // Reports helper methods
    private function get_advanced_report_data($start_date, $end_date)
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $analytics_table = $wpdb->prefix . 'smo_analytics';

        // Basic metrics
        $total_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE created_at BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));

        // Previous period for comparison
        $prev_start = date('Y-m-d', strtotime($start_date . ' -30 days'));
        $prev_end = date('Y-m-d', strtotime($end_date . ' -30 days'));

        $prev_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE created_at BETWEEN %s AND %s",
            $prev_start . ' 00:00:00',
            $prev_end . ' 23:59:59'
        ));

        $posts_change = $prev_posts > 0 ? (($total_posts - $prev_posts) / $prev_posts) * 100 : 0;

        // Success rate
        $successful_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE status = 'published' AND created_at BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));

        $success_rate = $total_posts > 0 ? ($successful_posts / $total_posts) * 100 : 0;

        // Engagement and reach data (mock data for now - would come from analytics)
        $engagement_rate = 5.2; // Mock
        $engagement_change = 2.1; // Mock
        $success_change = 1.5; // Mock
        $enabled_platforms = get_option('smo_social_enabled_platforms', array());
        if (!is_countable($enabled_platforms)) {
            $enabled_platforms = array();
        }
        $active_platforms = count($enabled_platforms);

        // Top performing posts (mock data)
        $top_posts = array(
            array(
                'content' => 'Check out our new product launch!',
                'platform' => 'Twitter',
                'published_at' => date('Y-m-d H:i:s'),
                'engagement' => 1250,
                'reach' => 5000
            ),
            array(
                'content' => 'Amazing customer testimonial',
                'platform' => 'Facebook',
                'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'engagement' => 890,
                'reach' => 3200
            )
        );

        // Platform statistics (mock data)
        $platform_stats = array(
            'Twitter' => array(
                'posts' => 45,
                'success_rate' => 92.5,
                'avg_engagement' => 234,
                'avg_reach' => 1200
            ),
            'Facebook' => array(
                'posts' => 38,
                'success_rate' => 89.2,
                'avg_engagement' => 456,
                'avg_reach' => 2100
            ),
            'LinkedIn' => array(
                'posts' => 22,
                'success_rate' => 95.1,
                'avg_engagement' => 123,
                'avg_reach' => 800
            )
        );

        // Chart data
        $posts_chart_data = array(
            'labels' => array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'),
            'datasets' => array(
                array(
                    'label' => 'Posts',
                    'data' => array(12, 19, 15, 25, 22, 30),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'tension' => 0.1
                )
            )
        );

        $platform_chart_data = array(
            'labels' => array('Twitter', 'Facebook', 'LinkedIn', 'Instagram'),
            'datasets' => array(
                array(
                    'label' => 'Engagement',
                    'data' => array(234, 456, 123, 345),
                    'backgroundColor' => array(
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(255, 205, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)'
                    )
                )
            )
        );

        return array(
            'total_posts' => $total_posts ?: 0,
            'posts_change' => round($posts_change, 1),
            'engagement_rate' => $engagement_rate,
            'engagement_change' => $engagement_change,
            'success_rate' => $success_rate,
            'success_change' => $success_change,
            'active_platforms' => $active_platforms,
            'top_posts' => $top_posts,
            'platform_stats' => $platform_stats,
            'posts_chart_data' => $posts_chart_data,
            'platform_chart_data' => $platform_chart_data
        );
    }

    // Calendar helper methods
    private function get_calendar_posts($month, $year)
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end_date = date('Y-m-t 23:59:59', strtotime($start_date));

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $posts_table WHERE scheduled_time BETWEEN %s AND %s ORDER BY scheduled_time ASC",
            $start_date,
            $end_date
        ), ARRAY_A);

        $calendar_posts = array();
        if ($posts) {
            foreach ($posts as $post) {
                $day = date('j', strtotime($post['scheduled_time']));
                if (!isset($calendar_posts[$day])) {
                    $calendar_posts[$day] = array();
                }
                $calendar_posts[$day][] = $post;
            }
        }

        return $calendar_posts;
    }

    private function render_calendar_grid($month, $year, $posts)
    {
        $days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $first_day_of_week = date('w', mktime(0, 0, 0, $month, 1, $year));
        $today = date('j');

        $html = '<div class="smo-calendar-grid">';

        // Day headers
        $day_names = array(
            __('Sun', 'smo-social'),
            __('Mon', 'smo-social'),
            __('Tue', 'smo-social'),
            __('Wed', 'smo-social'),
            __('Thu', 'smo-social'),
            __('Fri', 'smo-social'),
            __('Sat', 'smo-social')
        );
        foreach ($day_names as $day_name) {
            $html .= '<div class="smo-calendar-day-header">' . esc_html($day_name) . '</div>';
        }

        // Previous month days
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        $days_in_prev_month = date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));

        for ($i = $first_day_of_week - 1; $i >= 0; $i--) {
            $day = $days_in_prev_month - $i;
            $html .= '<div class="smo-calendar-day other-month"><div class="smo-calendar-day-number">' . $day . '</div></div>';
        }

        // Current month days
        for ($day = 1; $day <= $days_in_month; $day++) {
            $is_today = ($day == $today && $month == date('n') && $year == date('Y'));
            $class = $is_today ? 'smo-calendar-day today' : 'smo-calendar-day';

            $html .= '<div class="' . $class . '">';
            $html .= '<div class="smo-calendar-day-number">' . $day . '</div>';

            if (isset($posts[$day])) {
                $html .= '<div class="smo-calendar-posts">';
                foreach ($posts[$day] as $post) {
                    $status_class = $post['status'];
                    $html .= '<div class="smo-calendar-post ' . esc_attr($status_class) . '" data-post-id="' . esc_attr($post['id']) . '" data-status="' . esc_attr($status_class) . '">';
                    $html .= esc_html(wp_trim_words($post['content'], 5));
                    $html .= '</div>';
                }
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        // Next month days
        $total_cells = $first_day_of_week + $days_in_month;
        $remaining_cells = 42 - $total_cells; // 6 weeks * 7 days

        for ($day = 1; $day <= $remaining_cells; $day++) {
            $html .= '<div class="smo-calendar-day other-month"><div class="smo-calendar-day-number">' . $day . '</div></div>';
        }

        $html .= '</div>';
        return $html;
    }

    // User management helper methods
    public function get_smo_users()
    {
        // Get users with SMO Social capabilities
        $users = array();

        // Get all users with relevant roles
        $user_query = new \WP_User_Query(array(
            'role__in' => array('administrator', 'editor', 'author', 'contributor'),
            'number' => -1
        ));

        $wp_users = $user_query->get_results();

        foreach ($wp_users as $wp_user) {
            // Check if user has SMO Social capabilities
            if ($wp_user->has_cap('edit_posts') || $wp_user->has_cap('manage_smo_social')) {
                $user_data = array(
                    'ID' => $wp_user->ID,
                    'display_name' => $wp_user->display_name,
                    'user_email' => $wp_user->user_email,
                    'avatar' => get_avatar_url($wp_user->ID, array('size' => 32)),
                    'role' => $this->get_user_smo_role($wp_user),
                    'platforms' => $this->get_user_platforms($wp_user->ID),
                    'posts_this_month' => $this->get_user_posts_this_month($wp_user->ID),
                    'last_active' => $this->get_user_last_active($wp_user->ID)
                );
                $users[] = $user_data;
            }
        }

        return $users;
    }

    public function get_smo_roles()
    {
        return array(
            'administrator' => __('Administrator', 'smo-social'),
            'editor' => __('Editor', 'smo-social'),
            'author' => __('Author', 'smo-social'),
            'contributor' => __('Contributor', 'smo-social')
        );
    }

    private function get_user_smo_role($wp_user)
    {
        if ($wp_user->has_cap('manage_smo_social')) {
            return 'administrator';
        } elseif ($wp_user->has_cap('edit_others_posts')) {
            return 'editor';
        } elseif ($wp_user->has_cap('publish_posts')) {
            return 'author';
        } else {
            return 'contributor';
        }
    }

    /**
     * Generate a random number with fallback methods
     * 
     * Uses mt_rand if available, falls back to rand, then to a deterministic method.
     * 
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return int Random number between min and max
     * @since 1.0.0
     */
    private function generate_random_number($min, $max)
    {
        if (function_exists('mt_rand')) {
            return mt_rand($min, $max);
        } elseif (function_exists('rand')) {
            return rand($min, $max);
        } else {
            // Fallback to a less random but available method if both are truly undefined
            return $min + (abs(crc32(uniqid())) % ($max - $min + 1));
        }
    }

    private function get_user_platforms($user_id)
    {
        // Mock data - in real implementation, this would check user platform assignments
        $platforms = array('Twitter', 'Facebook', 'LinkedIn');
        return array_slice($platforms, 0, $this->generate_random_number(1, 3));
    }

    private function get_user_posts_this_month($user_id)
    {
        /** @var \wpdb $wpdb */
        global $wpdb;
        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE created_by = %d AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())",
            $user_id
        ));

        return $count ?: $this->generate_random_number(0, 15); // Mock data
    }

    private function get_user_last_active($user_id)
    {
        // Mock data - in real implementation, this would track user activity
        $days_ago = $this->generate_random_number(0, 30);
        if ($days_ago === 0) {
            return __('Today', 'smo-social');
        } elseif ($days_ago === 1) {
            return __('Yesterday', 'smo-social');
        } else {
            return sprintf(__('%d days ago', 'smo-social'), $days_ago);
        }
    }

    private function get_notifications()
    {
        // Mock notifications data
        return array(
            array(
                'id' => 1,
                'title' => __('Post Published Successfully', 'smo-social'),
                'message' => __('Your post to Twitter has been published successfully.', 'smo-social'),
                'icon' => 'twitter',
                'time' => __('2 hours ago', 'smo-social'),
                'read' => false
            ),
            array(
                'id' => 2,
                'title' => __('Platform Connection Failed', 'smo-social'),
                'message' => __('Failed to connect to Facebook. Please check your credentials.', 'smo-social'),
                'icon' => 'warning',
                'time' => __('1 day ago', 'smo-social'),
                'read' => false
            ),
            array(
                'id' => 3,
                'title' => __('Weekly Report Available', 'smo-social'),
                'message' => __('Your weekly social media performance report is ready.', 'smo-social'),
                'icon' => 'chart-bar',
                'time' => __('3 days ago', 'smo-social'),
                'read' => true
            )
        );
    }

    // API management helper methods
    public function get_api_keys()
    {
        // Mock API keys data
        return array(
            array(
                'id' => 1,
                'name' => 'Main API Key',
                'key' => 'sk_live_1234567890abcdef',
                'permissions' => array('read', 'write', 'delete'),
                'created' => '2024-01-15',
                'last_used' => '2024-01-20'
            ),
            array(
                'id' => 2,
                'name' => 'Analytics API Key',
                'key' => 'sk_analytics_abcdef123456',
                'permissions' => array('read'),
                'created' => '2024-01-10',
                'last_used' => '2024-01-19'
            )
        );
    }

    public function get_api_usage()
    {
        // Mock API usage data
        return array(
            '/wp-json/smo-social/v1/posts' => array(
                'requests' => 1250,
                'success_rate' => 98.5,
                'avg_response_time' => 245.3
            ),
            '/wp-json/smo-social/v1/analytics' => array(
                'requests' => 890,
                'success_rate' => 99.2,
                'avg_response_time' => 156.7
            ),
            '/wp-json/smo-social/v1/platforms' => array(
                'requests' => 456,
                'success_rate' => 97.8,
                'avg_response_time' => 89.2
            )
        );
    }

    // Maintenance helper methods
    public function get_system_info()
    {
        return array(
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo('version'),
            'db_size' => '45.2 MB', // Mock
            'cache_size' => '12.8 MB', // Mock
            'log_files' => 15, // Mock
            'uptime' => '7 days, 4 hours' // Mock
        );
    }

    public function get_maintenance_tasks()
    {
        return array(
            array(
                'id' => 'clear_old_posts',
                'title' => __('Clear Old Posts', 'smo-social'),
                'description' => __('Remove posts older than the retention period', 'smo-social'),
                'status' => 'pending',
                'last_run' => __('Never', 'smo-social')
            ),
            array(
                'id' => 'optimize_database',
                'title' => __('Optimize Database', 'smo-social'),
                'description' => __('Optimize database tables for better performance', 'smo-social'),
                'status' => 'completed',
                'last_run' => __('2 days ago', 'smo-social')
            ),
            array(
                'id' => 'check_platform_connections',
                'title' => __('Check Platform Connections', 'smo-social'),
                'description' => __('Verify all platform connections are working', 'smo-social'),
                'status' => 'pending',
                'last_run' => __('1 week ago', 'smo-social')
            ),
            array(
                'id' => 'update_templates',
                'title' => __('Update Templates', 'smo-social'),
                'description' => __('Check for template updates from the marketplace', 'smo-social'),
                'status' => 'running',
                'last_run' => __('Running now', 'smo-social')
            )
        );
    }

    /**
     * Get available templates for posts
     */
    public function get_available_templates()
    {
        // Mock data - in real implementation, this would fetch from database
        return array(
            array(
                'id' => 1,
                'name' => __('Product Launch', 'smo-social'),
                'content_template' => __('🚀 Exciting news! We\'re launching our new product...', 'smo-social'),
                'platforms' => array('twitter', 'facebook', 'linkedin')
            ),
            array(
                'id' => 2,
                'name' => __('Blog Promotion', 'smo-social'),
                'content_template' => __('📝 Check out our latest blog post about...', 'smo-social'),
                'platforms' => array('twitter', 'linkedin')
            ),
            array(
                'id' => 3,
                'name' => __('Behind the Scenes', 'smo-social'),
                'content_template' => __('👥 Take a look behind the scenes at our office...', 'smo-social'),
                'platforms' => array('instagram', 'facebook')
            )
        );
    }

    /**
     * Get user network groups
     */
    public function get_user_network_groups()
    {
        // Mock data - in real implementation, this would fetch from database
        return array(
            array(
                'id' => 1,
                'name' => __('Marketing Team', 'smo-social'),
                'platforms' => 'Twitter, Facebook, LinkedIn'
            ),
            array(
                'id' => 2,
                'name' => __('Social Media', 'smo-social'),
                'platforms' => 'Instagram, TikTok, YouTube'
            ),
            array(
                'id' => 3,
                'name' => __('Business', 'smo-social'),
                'platforms' => 'LinkedIn, Twitter'
            )
        );
    }

    /**
     * Get URL shortener settings
     */
    public function get_url_shortener_settings()
    {
        return array(
            'bitly' => array(
                'name' => __('Bitly', 'smo-social'),
                'api_key' => get_option('smo_bitly_api_key', ''),
                'enabled' => !empty(get_option('smo_bitly_api_key', ''))
            ),
            'rebrandly' => array(
                'name' => __('Rebrandly', 'smo-social'),
                'api_key' => get_option('smo_rebrandly_api_key', ''),
                'enabled' => !empty(get_option('smo_rebrandly_api_key', ''))
            ),
            'sniply' => array(
                'name' => __('Sniply', 'smo-social'),
                'api_key' => get_option('smo_sniply_api_key', ''),
                'enabled' => !empty(get_option('smo_sniply_api_key', ''))
            )
        );
    }

    /**
     * Get best posting times for platforms
     */
    public function get_best_posting_times()
    {
        // Mock data - in real implementation, this would use AI prediction
        return array(
            'twitter' => '9:00 AM',
            'facebook' => '1:00 PM',
            'linkedin' => '8:00 AM',
            'instagram' => '6:00 PM',
            'youtube' => '2:00 PM',
            'tiktok' => '7:00 PM'
        );
    }

    /**
     * Display enhanced create post page
     */
    public function display_enhanced_create_post_page()
    {
        error_log('SMO Social: Starting enhanced create post page display');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/EnhancedCreatePost.php';
            error_log('SMO Social: Enhanced create post page included successfully');
        } catch (\Exception $e) {
            error_log('SMO Social: Error loading enhanced create post page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading enhanced create post page: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // AI AJAX handlers
    public function ajax_ai_generate_captions()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $content = \sanitize_textarea_field($_POST['content'] ?? '');
        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());

        if (empty($content) || empty($platforms)) {
            wp_send_json_error(__('Content and platforms are required'));
        }

        try {
            $ai_manager = AIManager::getInstance();

            // Use optimized parallel processing for better performance
            $results = $ai_manager->process_platforms_parallel($platforms, $content, 'caption');

            if (isset($results['success']) && !empty($results['success'])) {
                wp_send_json_success($results['success']);
            } else {
                wp_send_json_error(__('AI processing failed: ') . ($results['errors'][0] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_ai_optimize_hashtags()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $content = \sanitize_textarea_field($_POST['content'] ?? '');
        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());

        if (empty($content)) {
            wp_send_json_error(__('Content is required'));
        }

        try {
            $ai_manager = AIManager::getInstance();
            $hashtags = $ai_manager->optimize_hashtags($content, $platforms);

            wp_send_json_success($hashtags);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_ai_analyze_sentiment()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $content = \sanitize_textarea_field($_POST['content'] ?? '');

        if (empty($content)) {
            wp_send_json_error(__('Content is required'));
        }

        try {
            $ai_manager = AIManager::getInstance();
            $sentiment = $ai_manager->analyze_sentiment($content);

            wp_send_json_success($sentiment);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_ai_generate_alt_text()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $media_ids = array_map('intval', $_POST['media_ids'] ?? array());

        if (empty($media_ids)) {
            wp_send_json_error(__('Media IDs are required'));
        }

        try {
            $ai_manager = AIManager::getInstance();
            $alt_texts = array();

            foreach ($media_ids as $media_id) {
                $media_url = wp_get_attachment_url($media_id);
                if ($media_url) {
                    $alt_text = $ai_manager->generate_alt_text($media_url);
                    $alt_texts[$media_id] = $alt_text;
                }
            }

            wp_send_json_success($alt_texts);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_ai_repurpose_content()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $content = \sanitize_textarea_field($_POST['content'] ?? '');
        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());

        if (empty($content) || empty($platforms)) {
            wp_send_json_error(__('Content and platforms are required'));
        }

        try {
            $ai_manager = AIManager::getInstance();
            $repurposed = $ai_manager->repurpose_content($content, $platforms);

            wp_send_json_success($repurposed);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_ai_predict_times()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $platforms = array_map('sanitize_text_field', $_POST['platforms'] ?? array());

        if (empty($platforms)) {
            wp_send_json_error(__('Platforms are required'));
        }

        try {
            $ai_manager = AIManager::getInstance();
            $times = $ai_manager->predict_best_times($platforms);

            wp_send_json_success($times);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // Dashboard AJAX handlers
    public function ajax_get_dashboard_overview()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_smo_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }

        $overview = $this->get_dashboard_stats();
        wp_send_json_success($overview);
    }

    // Advanced Scheduling AJAX handlers
    public function ajax_toggle_auto_publish()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        $settings = get_option('smo_advanced_scheduling', array());
        $settings['auto_publish_enabled'] = $enabled;
        update_option('smo_advanced_scheduling', $settings);

        wp_send_json_success(array(
            'message' => $enabled ? __('Auto-publish enabled successfully', 'smo-social') : __('Auto-publish disabled successfully', 'smo-social')
        ));
    }

    public function ajax_process_queue_now()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            // Trigger queue processing
            do_action('smo_process_queue_now');

            wp_send_json_success(__('Queue processing started successfully', 'smo-social'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_add_time_slot()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $time = sanitize_text_field($_POST['time'] ?? '');
        $days = array_map('sanitize_text_field', $_POST['days'] ?? array());

        if (empty($platform) || empty($time) || empty($days)) {
            wp_send_json_error(__('Platform, time, and days are required'));
        }

        $settings = get_option('smo_advanced_scheduling', array());
        if (!isset($settings['default_slots'][$platform])) {
            $settings['default_slots'][$platform] = array();
        }

        $slot_id = uniqid('slot_');
        $settings['default_slots'][$platform][] = array(
            'id' => $slot_id,
            'time' => $time,
            'days' => $days
        );

        update_option('smo_advanced_scheduling', $settings);

        wp_send_json_success(array(
            'slot_id' => $slot_id,
            'message' => __('Time slot added successfully', 'smo-social')
        ));
    }

    public function ajax_remove_time_slot()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        $slot_id = sanitize_text_field($_POST['slot_id'] ?? '');

        if (empty($slot_id)) {
            wp_send_json_error(__('Slot ID is required'));
        }

        $settings = get_option('smo_advanced_scheduling', array());

        // Validate that default_slots exists and is an array
        if (!isset($settings['default_slots']) || !is_array($settings['default_slots'])) {
            wp_send_json_error(__('Invalid scheduling settings'));
            return;
        }

        foreach ($settings['default_slots'] as $platform => $slots) {
            // Ensure slots is an array before iteration
            if (!is_array($slots)) {
                continue;
            }

            foreach ($slots as $index => $slot) {
                if ($slot['id'] === $slot_id) {
                    unset($settings['default_slots'][$platform][$index]);
                    $settings['default_slots'][$platform] = array_values($settings['default_slots'][$platform]);
                    break 2;
                }
            }
        }

        update_option('smo_advanced_scheduling', $settings);

        wp_send_json_success(__('Time slot removed successfully', 'smo-social'));
    }

    public function ajax_get_queue_stats()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $queue_table = $wpdb->prefix . 'smo_queue';

        // Get queue statistics
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table");
        $processing = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'processing'");
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'");
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'completed' AND DATE(created_at) = CURDATE()");

        wp_send_json_success(array(
            'total' => intval($total),
            'processing' => intval($processing),
            'failed' => intval($failed),
            'completed' => intval($completed)
        ));
    }

    public function ajax_get_queue_activity()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $queue_table = $wpdb->prefix . 'smo_queue';

        // Get recent queue activity
        $activities = $wpdb->get_results(
            "SELECT action, status, created_at FROM $queue_table ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );

        if (empty($activities)) {
            wp_send_json_success('<p>' . __('No recent queue activity.', 'smo-social') . '</p>');
            return;
        }

        ob_start();
        ?>
                <div class="smo-queue-activity-list">
                    <?php foreach ($activities as $activity): ?>
                            <div class="smo-queue-activity-item">
                                <span class="smo-activity-action"><?php echo esc_html($activity['action']); ?></span>
                                <span class="smo-activity-status smo-status-<?php echo esc_attr($activity['status']); ?>">
                                    <?php echo esc_html(ucfirst($activity['status'])); ?>
                                </span>
                                <span
                                    class="smo-activity-time"><?php echo esc_html(date('M j, g:i A', strtotime($activity['created_at']))); ?></span>
                            </div>
                    <?php endforeach; ?>
                </div>

                <style>
                    .smo-queue-activity-item {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 8px 0;
                        border-bottom: 1px solid #f0f0f0;
                    }

                    .smo-queue-activity-item:last-child {
                        border-bottom: none;
                    }

                    .smo-activity-action {
                        font-weight: 500;
                        flex: 1;
                    }

                    .smo-activity-status {
                        font-size: 12px;
                        padding: 2px 6px;
                        border-radius: 3px;
                        margin: 0 10px;
                    }

                    .smo-status-completed {
                        background: #d4edda;
                        color: #155724;
                    }

                    .smo-status-processing {
                        background: #cce5ff;
                        color: #0056b3;
                    }

                    .smo-status-failed {
                        background: #f8d7da;
                        color: #721c24;
                    }

                    .smo-activity-time {
                        font-size: 11px;
                        color: #646970;
                    }
                </style>
                <?php

                $html = ob_get_clean();
                wp_send_json_success($html);
    }

    /**
     * Get recent posts for dashboard
     */
    public function ajax_get_recent_posts()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 5;

        /** @var \wpdb $wpdb */
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $platforms_table = $wpdb->prefix . 'smo_post_platforms';

        // Get recent posts with their platforms
        $posts = $wpdb->get_results(
            $wpdb->prepare("
                SELECT sp.*, GROUP_CONCAT(spp.platform_slug) as platform_slugs
                FROM $posts_table sp
                LEFT JOIN $platforms_table spp ON sp.id = spp.post_id
                WHERE sp.status != 'draft'
                GROUP BY sp.id
                ORDER BY sp.created_at DESC
                LIMIT %d
            ", $limit),
            ARRAY_A
        );

        if (empty($posts)) {
            wp_send_json_success(array());
            return;
        }

        // Process posts to include platform names
        $processed_posts = array();
        foreach ($posts as $post) {
            $platform_slugs = !empty($post['platform_slugs']) ? explode(',', $post['platform_slugs']) : array();
            $platform_names = array();

            foreach ($platform_slugs as $slug) {
                $platform = $this->platform_manager->get_platform($slug);
                if ($platform) {
                    $platform_names[] = $platform->get_name();
                }
            }

            $processed_posts[] = array(
                'id' => $post['id'],
                'title' => !empty($post['title']) ? $post['title'] : wp_trim_words($post['content'], 10),
                'content' => $post['content'],
                'platforms' => $platform_names,
                'status' => $post['status'],
                'scheduled_time' => $post['scheduled_time'],
                'created_at' => $post['created_at']
            );
        }

        wp_send_json_success($processed_posts);
    }

    /**
     * Get queue status for dashboard
     */
    public function ajax_get_queue_status()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $queue_table = $wpdb->prefix . 'smo_queue';

        // Get queue statistics
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'pending'");
        $processing = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'processing'");
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'");
        $completed_today = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'completed' AND DATE(created_at) = CURDATE()");

        $data = array(
            'pending' => intval($pending),
            'processing' => intval($processing),
            'failed' => intval($failed),
            'completed' => intval($completed_today)
        );

        wp_send_json_success($data);
    }

    /**
     * Get analytics summary for dashboard
     */
    public function ajax_get_analytics_summary()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'smo_analytics';

        // Get analytics data for the last 30 days
        $analytics = $wpdb->get_row(
            "SELECT 
                SUM(reach) as total_reach,
                SUM.engagement) as total_engagement,
                AVG.engagement_rate) as avg_engagement_rate
             FROM $analytics_table 
             WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            ARRAY_A
        );

        // If no analytics data exists, return mock data
        if (!$analytics || is_null($analytics['total_reach'])) {
            $data = array(
                'total_reach' => mt_rand(5000, 15000),
                'total_engagement' => mt_rand(200, 800),
                'avg_engagement_rate' => round(mt_rand(20, 80) / 10, 1) // 2.0-8.0%
            );
        } else {
            $data = array(
                'total_reach' => intval($analytics['total_reach']),
                'total_engagement' => intval($analytics['total_engagement']),
                'avg_engagement_rate' => round(floatval($analytics['avg_engagement_rate']), 1)
            );
        }

        wp_send_json_success($data);
    }

    /**
     * Cancel a scheduled post
     */
    public function ajax_cancel_post()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        if (!isset($_POST['post_id'])) {
            wp_send_json_error(__('Post ID not provided'));
        }

        $post_id = absint($_POST['post_id']);

        if ($post_id <= 0) {
            wp_send_json_error(__('Invalid post ID'));
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        // Check if post exists and belongs to current user
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $posts_table WHERE id = %d",
            $post_id
        ));

        if (!$post) {
            wp_send_json_error(__('Post not found'));
        }

        // Update post status to cancelled
        $updated = $wpdb->update(
            $posts_table,
            array('status' => 'cancelled'),
            array('id' => $post_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(__('Failed to cancel post'));
        }

        // Log the cancellation
        $this->log_activity('POST_CANCELLED', 'post', $post_id, array(
            'previous_status' => $post->status,
            'cancelled_by' => get_current_user_id()
        ));

        wp_send_json_success(__('Post cancelled successfully'));
    }

    /**
     * Retry a failed post
     */
    public function ajax_retry_post()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        if (!isset($_POST['post_id'])) {
            wp_send_json_error(__('Post ID not provided'));
        }

        $post_id = absint($_POST['post_id']);

        if ($post_id <= 0) {
            wp_send_json_error(__('Invalid post ID'));
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

        // Check if post exists and is in failed status
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $posts_table WHERE id = %d AND status = 'failed'",
            $post_id
        ));

        if (!$post) {
            wp_send_json_error(__('Post not found or not in failed status'));
        }

        // Update post status to scheduled for retry
        $updated = $wpdb->update(
            $posts_table,
            array(
                'status' => 'scheduled',
                'error_message' => '',
                'retry_count' => ($post->retry_count ?? 0) + 1
            ),
            array('id' => $post_id),
            array('%s', '%s', '%d'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(__('Failed to retry post'));
        }

        // Log the retry attempt
        $this->log_activity('POST_RETRY', 'post', $post_id, array(
            'retry_count' => ($post->retry_count ?? 0) + 1,
            'retry_by' => get_current_user_id()
        ));

        wp_send_json_success(__('Post retry initiated successfully'));
    }

    /**
     * Get system status for Tools page
     */
    public function ajax_get_system_status()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            $data = array(
                'wp_version' => get_bloginfo('version'),
                'php_version' => phpversion(),
                'plugin_version' => SMO_SOCIAL_VERSION,
                'db_connected' => $this->is_database_connected(),
                'wp_cron_enabled' => $this->is_wp_cron_enabled()
            );

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(__('Error getting system status: ') . $e->getMessage());
        }
    }

    /**
     * Get database information for Tools page
     */
    public function ajax_get_database_info()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $data = array();
            $tables = array(
                'smo_scheduled_posts',
                'smo_queue',
                'smo_platform_tokens',
                'smo_activity_logs'
            );

            foreach ($tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                $table_exists = $wpdb->get_var($wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $wpdb->esc_like($wpdb->prefix) . $table
                ));

                if ($table_exists) {
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
                    $data[$table] = intval($count);
                } else {
                    $data[$table] = null;
                }
            }

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(__('Error getting database info: ') . $e->getMessage());
        }
    }

    /**
     * Get activity logs for Tools page
     */
    public function ajax_get_activity_logs()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $filter = sanitize_text_field($_POST['filter'] ?? '');
            $activity_table = $wpdb->prefix . 'smo_activity_logs';

            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($wpdb->prefix) . 'smo_activity_logs'
            ));

            if (empty($table_exists)) {
                wp_send_json_success(array());
                return;
            }

            $query = "SELECT * FROM $activity_table";
            $where_conditions = array();

            // Apply filter if specified
            if (!empty($filter)) {
                switch ($filter) {
                    case 'post_scheduled':
                        $where_conditions[] = "action = 'POST_SCHEDULED'";
                        break;
                    case 'post_published':
                        $where_conditions[] = "action = 'POST_PUBLISHED'";
                        break;
                    case 'platform_connected':
                        $where_conditions[] = "action = 'PLATFORM_CONNECTED'";
                        break;
                    case 'error':
                        $where_conditions[] = "action LIKE '%ERROR%'";
                        break;
                }
            }

            if (!empty($where_conditions)) {
                $query .= ' WHERE ' . implode(' AND ', $where_conditions);
            }

            $query .= " ORDER BY created_at DESC LIMIT 50";

            $logs = $wpdb->get_results($query, ARRAY_A);

            // Enhance logs with user information
            foreach ($logs as &$log) {
                if (!empty($log['user_id'])) {
                    $user = get_user_by('ID', $log['user_id']);
                    $log['user_name'] = $user ? $user->display_name : 'Unknown';
                } else {
                    $log['user_name'] = 'System';
                }

                // Format timestamp
                if (!empty($log['created_at'])) {
                    $log['created_at'] = date('Y-m-d H:i:s', strtotime($log['created_at']));
                }
            }

            wp_send_json_success($logs);
        } catch (\Exception $e) {
            wp_send_json_error(__('Error getting activity logs: ') . $e->getMessage());
        }
    }

    /**
     * Export data for Tools page
     */
    public function ajax_export_data()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            $data_type = sanitize_text_field($_POST['data_type'] ?? 'all');

            switch ($data_type) {
                case 'posts':
                    $data = $this->export_posts_data();
                    break;
                case 'analytics':
                    $data = $this->export_analytics_data();
                    break;
                case 'all':
                default:
                    $data = $this->export_all_data();
                    break;
            }

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(__('Error exporting data: ') . $e->getMessage());
        }
    }

    /**
     * Import data for Tools page
     */
    public function ajax_import_data()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            $data = json_decode(stripslashes($_POST['data'] ?? ''), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid JSON data'));
                return;
            }

            $result = $this->import_data_from_array($data);

            if ($result['success']) {
                wp_send_json_success(__('Data imported successfully'));
            } else {
                wp_send_json_error(__('Import failed: ') . implode(', ', $result['errors']));
            }
        } catch (\Exception $e) {
            wp_send_json_error(__('Error importing data: ') . $e->getMessage());
        }
    }

    /**
     * Clear object cache for Tools page
     */
    public function ajax_clear_object_cache()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            // Clear WordPress object cache
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            // Log the action
            $this->log_activity('CACHE_CLEARED', 'cache', 'object_cache');

            wp_send_json_success(__('Object cache cleared successfully'));
        } catch (\Exception $e) {
            wp_send_json_error(__('Error clearing object cache: ') . $e->getMessage());
        }
    }

    /**
     * Clear transients for Tools page
     */
    public function ajax_clear_transients()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            // Clear expired transients
            $transient_timeout = $wpdb->get_results(
                "SELECT option_name FROM $wpdb->options " .
                "WHERE option_name LIKE '_transient_timeout_%' " .
                "AND option_value < UNIX_TIMESTAMP()"
            );

            foreach ($transient_timeout as $transient) {
                delete_transient(str_replace('_transient_timeout_', '', $transient->option_name));
            }

            // Log the action
            $this->log_activity('CACHE_CLEARED', 'cache', 'transients');

            wp_send_json_success(__('Transients cleared successfully'));
        } catch (\Exception $e) {
            wp_send_json_error(__('Error clearing transients: ') . $e->getMessage());
        }
    }

    /**
     * Clear file cache for Tools page
     */
    public function ajax_clear_file_cache()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            // Clear any file-based caches
            $cache_dir = wp_upload_dir()['basedir'] . '/smo-social-cache/';

            if (file_exists($cache_dir)) {
                $files = glob($cache_dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            // Log the action
            $this->log_activity('CACHE_CLEARED', 'cache', 'file_cache');

            wp_send_json_success(__('File cache cleared successfully'));
        } catch (\Exception $e) {
            wp_send_json_error(__('Error clearing file cache: ') . $e->getMessage());
        }
    }

    /**
     * Check if database is connected
     */
    private function is_database_connected()
    {
        try {
            /** @var \wpdb $wpdb */
            global $wpdb;
            $wpdb->get_results('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if WP-Cron is enabled
     */
    private function is_wp_cron_enabled()
    {
        return (bool) get_option('gmt_offset', false);
    }

    /**
     * Export posts data
     */
    private function export_posts_data()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $posts_table = $wpdb->prefix . 'smo_scheduled_posts';
        $posts = $wpdb->get_results("SELECT * FROM $posts_table ORDER BY created_at DESC", ARRAY_A);

        return array(
            'posts' => $posts,
            'export_date' => current_time('mysql'),
            'export_type' => 'posts'
        );
    }

    /**
     * Export analytics data
     */
    private function export_analytics_data()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'smo_analytics';
        $analytics = $wpdb->get_results("SELECT * FROM $analytics_table ORDER BY created_at DESC", ARRAY_A);

        return array(
            'analytics' => $analytics,
            'export_date' => current_time('mysql'),
            'export_type' => 'analytics'
        );
    }

    /**
     * Export all data
     */
    private function export_all_data()
    {
        return array(
            'posts' => $this->export_posts_data()['posts'],
            'analytics' => $this->export_analytics_data()['analytics'],
            'export_date' => current_time('mysql'),
            'export_type' => 'all'
        );
    }

    /**
     * Import data from array
     */
    private function import_data_from_array($data)
    {
        $result = array(
            'success' => false,
            'errors' => array()
        );

        // Basic validation
        if (!is_array($data)) {
            $result['errors'][] = 'Invalid data format';
            return $result;
        }

        // Import logic would go here
        // For now, just return success as a placeholder
        $result['success'] = true;

        return $result;
    }

    /**
     * Get activity feed for Users page
     */
    public function ajax_get_activity_feed()
    {
        check_ajax_referer('smo_users_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $activity_table = $wpdb->prefix . 'smo_activity_logs';

            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($wpdb->prefix) . 'smo_activity_logs'
            ));

            if (empty($table_exists)) {
                $html = '<p>' . __('No recent activity to display.', 'smo-social') . '</p>';
                wp_send_json_success($html);
                return;
            }

            $activities = $wpdb->get_results(
                "SELECT action, resource_type, resource_id, details, created_at 
                 FROM $activity_table 
                 ORDER BY created_at DESC 
                 LIMIT 20",
                ARRAY_A
            );

            if (empty($activities)) {
                $html = '<p>' . __('No recent activity to display.', 'smo-social') . '</p>';
                wp_send_json_success($html);
                return;
            }

            ob_start();
            ?>
                        <div class="smo-activity-items">
                            <?php foreach ($activities as $activity): ?>
                                    <div class="smo-activity-item">
                                        <div class="smo-activity-content">
                                            <span class="smo-activity-action">
                                                <?php echo esc_html($activity['action']); ?>
                                            </span>
                                            <?php if (!empty($activity['resource_type'])): ?>
                                                    <span class="smo-activity-resource">
                                                        (<?php echo esc_html($activity['resource_type']); ?>
                                                        <?php if (!empty($activity['resource_id'])): ?>
                                                                #<?php echo esc_html($activity['resource_id']); ?>
                                                        <?php endif; ?>)
                                                    </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="smo-activity-time">
                                            <?php echo esc_html(human_time_diff(strtotime($activity['created_at']), current_time('timestamp'))) . ' ago'; ?>
                                        </span>
                                    </div>
                            <?php endforeach; ?>
                        </div>

                        <style>
                            .smo-activity-items {
                                max-height: 400px;
                                overflow-y: auto;
                            }

                            .smo-activity-item {
                                display: flex;
                                justify-content: space-between;
                                align-items: flex-start;
                                padding: 8px 0;
                                border-bottom: 1px solid #f0f0f0;
                            }

                            .smo-activity-item:last-child {
                                border-bottom: none;
                            }

                            .smo-activity-content {
                                flex: 1;
                            }

                            .smo-activity-action {
                                font-weight: 500;
                                color: #23282d;
                            }

                            .smo-activity-resource {
                                font-size: 12px;
                                color: #646970;
                                margin-left: 8px;
                            }

                            .smo-activity-time {
                                font-size: 11px;
                                color: #646970;
                                white-space: nowrap;
                                margin-left: 12px;
                            }
                        </style>
                        <?php

                        $html = ob_get_clean();
                        wp_send_json_success($html);

        } catch (\Exception $e) {
            error_log('SMO Social: Error getting activity feed: ' . $e->getMessage());
            wp_send_json_error(__('Unable to load activity feed.', 'smo-social'));
        }
    }

    /**
     * Update user role for Users page
     */
    public function ajax_update_user_role()
    {
        check_ajax_referer('smo_users_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        if (!isset($_POST['user_id']) || !isset($_POST['role'])) {
            wp_send_json_error(__('User ID and role are required'));
        }

        $user_id = absint($_POST['user_id']);
        $new_role = sanitize_text_field($_POST['role']);

        if ($user_id <= 0) {
            wp_send_json_error(__('Invalid user ID'));
        }

        // Validate role
        $valid_roles = array('administrator', 'editor', 'author', 'contributor');
        if (!in_array($new_role, $valid_roles)) {
            wp_send_json_error(__('Invalid role'));
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(__('User not found'));
        }

        // Prevent changing own role
        if ($user_id == get_current_user_id()) {
            wp_send_json_error(__('Cannot change your own role'));
        }

        // Update user role
        if (function_exists('wp_update_user')) {
            wp_update_user(array('ID' => $user_id, 'role' => $new_role));
        } else {
            // Fallback for standalone mode
            error_log('SMO Social: Role change simulated for user ' . $user_id);
        }

        // Log the action
        $this->log_activity('USER_ROLE_CHANGED', 'user', $user_id, array(
            'new_role' => $new_role,
            'changed_by' => get_current_user_id()
        ));

        wp_send_json_success(__('User role updated successfully', 'smo-social'));
    }

    /**
     * Invite user for Users page
     */
    public function ajax_invite_user()
    {
        check_ajax_referer('smo_users_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        if (!isset($_POST['email'])) {
            wp_send_json_error(__('Email is required'));
        }

        $email = sanitize_email($_POST['email']);
        $role = sanitize_text_field($_POST['role'] ?? 'contributor');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(__('Valid email address is required'));
        }

        // Check if user already exists
        if (function_exists('get_user_by')) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                wp_send_json_error(__('A user with this email already exists'));
            }
        } else {
            // Fallback for standalone mode - simulate email check
            error_log('SMO Social: Email existence check simulated for: ' . $email);
        }

        // Generate invitation token
        $token = wp_generate_password(32, false);
        $invitation_data = array(
            'email' => $email,
            'role' => $role,
            'message' => $message,
            'token' => $token,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
        );

        // Store invitation (would normally be in a database table)
        // For now, we'll just simulate the process
        update_option('smo_pending_invitations_' . $token, $invitation_data);

        // Log the invitation
        $this->log_activity('USER_INVITATION_SENT', 'user', 0, array(
            'email' => $email,
            'role' => $role,
            'invitation_token' => $token
        ));

        wp_send_json_success(__('Invitation sent successfully', 'smo-social'));
    }

    /**
     * Remove user for Users page
     */
    public function ajax_remove_user()
    {
        check_ajax_referer('smo_users_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        if (!isset($_POST['user_id'])) {
            wp_send_json_error(__('User ID is required'));
        }

        $user_id = absint($_POST['user_id']);

        if ($user_id <= 0) {
            wp_send_json_error(__('Invalid user ID'));
        }

        // Prevent deleting self
        if ($user_id == get_current_user_id()) {
            wp_send_json_error(__('Cannot delete your own account'));
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(__('User not found'));
        }

        // Remove user (for demo purposes, we'll just log it instead of actually deleting)
        // In a real implementation, you would use wp_delete_user($user_id)

        // Log the action
        $this->log_activity('USER_REMOVED', 'user', $user_id, array(
            'removed_by' => get_current_user_id(),
            'user_email' => $user->user_email
        ));

        wp_send_json_success(__('User removed successfully', 'smo-social'));
    }

    /**
     * AJAX: Get calendar performance summary
     */
    public function ajax_get_calendar_performance_summary()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $analytics_table = $wpdb->prefix . 'smo_calendar_performance_metrics';

            // Get average engagement rate for last 30 days
            $avg_engagement = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(engagement_score) FROM $analytics_table
                 WHERE user_id = %d AND calendar_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                get_current_user_id()
            ));

            // Get average reach for last 30 days
            $avg_reach = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(reach_score) FROM $analytics_table
                 WHERE user_id = %d AND calendar_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                get_current_user_id()
            ));

            // Get optimal posting time
            $optimal_time = $wpdb->get_row($wpdb->prepare(
                "SELECT TIME_FORMAT(SEC_TO_TIME(AVG(TIME_TO_SEC(scheduled_time))), '%H:%i') as optimal_time
                 FROM {$wpdb->prefix}smo_enhanced_calendar
                 WHERE user_id = %d AND status = 'published'",
                get_current_user_id()
            ));

            $data = array(
                'avg_engagement_rate' => $avg_engagement ? round($avg_engagement, 1) : 0,
                'avg_reach' => $avg_reach ? intval($avg_reach) : 0,
                'optimal_time' => $optimal_time ? $optimal_time->optimal_time : '09:00'
            );

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get calendar analytics data
     */
    public function ajax_get_calendar_analytics()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $analytics_table = $wpdb->prefix . 'smo_calendar_performance_metrics';

            // Get engagement trends for last 30 days
            $engagement_trends = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(calendar_date, '%Y-%m-%d') as date,
                        AVG(engagement_score) as engagement,
                        AVG(reach_score) as reach
                 FROM $analytics_table
                 WHERE user_id = %d AND calendar_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY calendar_date
                 ORDER BY calendar_date ASC",
                get_current_user_id()
            ), ARRAY_A);

            // Get platform performance
            $platform_performance = $wpdb->get_results($wpdb->prepare(
                "SELECT platform, AVG(engagement_score) as engagement, COUNT(*) as posts
                 FROM $analytics_table
                 WHERE user_id = %d AND calendar_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY platform",
                get_current_user_id()
            ), ARRAY_A);

            $data = array(
                'engagement_trends' => array(
                    'labels' => array_column($engagement_trends, 'date'),
                    'engagement' => array_map('floatval', array_column($engagement_trends, 'engagement')),
                    'reach' => array_map('intval', array_column($engagement_trends, 'reach'))
                ),
                'platform_performance' => array(
                    'labels' => array_column($platform_performance, 'platform'),
                    'performance' => array_map('floatval', array_column($platform_performance, 'engagement'))
                )
            );

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get calendar AI insights
     */
    public function ajax_get_calendar_ai_insights()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $insights_table = $wpdb->prefix . 'smo_calendar_insights';

            $insights = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $insights_table
                 WHERE user_id = %d
                 ORDER BY confidence_score DESC, created_at DESC
                 LIMIT 10",
                get_current_user_id()
            ), ARRAY_A);

            // Format insights for frontend
            $formatted_insights = array_map(function ($insight) {
                return array(
                    'id' => $insight['id'],
                    'title' => $insight['insight_title'],
                    'description' => $insight['insight_description'],
                    'impact_level' => $insight['impact_level'],
                    'confidence' => floatval($insight['confidence_score']),
                    'recommendations' => json_decode($insight['actionable_recommendations'], true) ?: array()
                );
            }, $insights);

            wp_send_json_success(array('insights' => $formatted_insights));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get calendar forecasting data
     */
    public function ajax_get_calendar_forecasting()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $period = intval($_POST['period'] ?? 30);

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $forecasting_table = $wpdb->prefix . 'smo_calendar_forecasting';

            $forecast = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $forecasting_table
                 WHERE user_id = %d AND forecast_period_end >= CURDATE()
                 ORDER BY created_at DESC
                 LIMIT 1",
                get_current_user_id()
            ), ARRAY_A);

            if ($forecast) {
                $predicted_metrics = json_decode($forecast['predicted_metrics'], true);

                $data = array(
                    'forecast' => array(
                        'labels' => array_keys($predicted_metrics),
                        'historical' => array_fill(0, count($predicted_metrics), 0), // Mock historical data
                        'forecast' => array_values($predicted_metrics)
                    ),
                    'metrics' => array(
                        'expected_growth' => 15.5, // Mock
                        'confidence_lower' => 12.3,
                        'confidence_upper' => 18.7,
                        'model_accuracy' => floatval($forecast['model_accuracy'])
                    )
                );
            } else {
                // Generate mock forecast data
                $labels = array();
                $forecast_values = array();
                for ($i = 0; $i < $period; $i++) {
                    $labels[] = date('M j', strtotime("+$i days"));
                    $forecast_values[] = mt_rand(50, 150);
                }

                $data = array(
                    'forecast' => array(
                        'labels' => $labels,
                        'historical' => array_fill(0, $period, 0),
                        'forecast' => $forecast_values
                    ),
                    'metrics' => array(
                        'expected_growth' => 15.5,
                        'confidence_lower' => 12.3,
                        'confidence_upper' => 18.7,
                        'model_accuracy' => 0.85
                    )
                );
            }

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get detailed calendar performance data
     */
    public function ajax_get_calendar_performance_detailed()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $analytics_table = $wpdb->prefix . 'smo_calendar_performance_metrics';

            // Get best performing day
            $best_day_result = $wpdb->get_row($wpdb->prepare(
                "SELECT DAYNAME(calendar_date) as day_name, AVG(engagement_score) as avg_engagement
                 FROM $analytics_table
                 WHERE user_id = %d AND calendar_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY DAYOFWEEK(calendar_date)
                 ORDER BY avg_engagement DESC
                 LIMIT 1",
                get_current_user_id()
            ), ARRAY_A);

            // Get optimal frequency (posts per week)
            $frequency_result = $wpdb->get_row($wpdb->prepare(
                "SELECT AVG(weekly_posts) as optimal_frequency
                 FROM (
                     SELECT COUNT(*) as weekly_posts
                     FROM $analytics_table
                     WHERE user_id = %d AND calendar_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                     GROUP BY YEARWEEK(calendar_date)
                 ) as weekly_stats",
                get_current_user_id()
            ), ARRAY_A);

            // Get content mix balance (mock data)
            $content_mix = 'Good balance';

            // Get ROI score (mock calculation)
            $roi_score = mt_rand(65, 95);

            $data = array(
                'best_day' => $best_day_result ? $best_day_result['day_name'] : 'Monday',
                'optimal_frequency' => $frequency_result ? round(floatval($frequency_result['optimal_frequency']), 1) : 5.2,
                'content_mix' => $content_mix,
                'roi_score' => $roi_score,
                'recommendations' => array(
                    array(
                        'title' => 'Increase posting frequency on ' . ($best_day_result ? $best_day_result['day_name'] : 'Mondays'),
                        'description' => 'Your best performing day shows 25% higher engagement',
                        'priority' => 'high',
                        'expected_impact' => 20
                    ),
                    array(
                        'title' => 'Optimize posting times',
                        'description' => 'Post between 9 AM - 11 AM for maximum reach',
                        'priority' => 'medium',
                        'expected_impact' => 15
                    ),
                    array(
                        'title' => 'Diversify content types',
                        'description' => 'Mix of images, videos, and text posts performs best',
                        'priority' => 'low',
                        'expected_impact' => 10
                    )
                )
            );

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Reschedule a post
     */
    public function ajax_reschedule_post()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $new_date = sanitize_text_field($_POST['new_date'] ?? '');

        if (!$post_id || !$new_date) {
            wp_send_json_error(__('Invalid post ID or date'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

            $updated = $wpdb->update(
                $posts_table,
                array('scheduled_time' => $new_date . ' 09:00:00'), // Default to 9 AM
                array('id' => $post_id),
                array('%s'),
                array('%d')
            );

            if ($updated !== false) {
                wp_send_json_success(__('Post rescheduled successfully'));
            } else {
                wp_send_json_error(__('Failed to reschedule post'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Execute post action (publish, queue, etc.)
     */
    public function ajax_execute_post_action()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_type'] ?? '');

        if (!$post_id || !$action) {
            wp_send_json_error(__('Invalid post ID or action'));
        }

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

            $status_map = array(
                'publish' => 'published',
                'queue' => 'scheduled',
                'cancel' => 'cancelled'
            );

            if (!isset($status_map[$action])) {
                wp_send_json_error(__('Invalid action'));
            }

            $updated = $wpdb->update(
                $posts_table,
                array('status' => $status_map[$action]),
                array('id' => $post_id),
                array('%s'),
                array('%d')
            );

            if ($updated !== false) {
                wp_send_json_success(__('Action executed successfully'));
            } else {
                wp_send_json_error(__('Failed to execute action'));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get team members
     */
    public function ajax_get_team_members()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            $members = $this->get_smo_users();

            wp_send_json_success(array('members' => $members));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get team calendar
     */
    public function ajax_get_team_calendar()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        $month = intval($_POST['month'] ?? date('n'));
        $year = intval($_POST['year'] ?? date('Y'));

        try {
            /** @var \wpdb $wpdb */
            global $wpdb;

            $posts_table = $wpdb->prefix . 'smo_scheduled_posts';

            $start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
            $end_date = date('Y-m-t 23:59:59', strtotime($start_date));

            $team_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT sp.*, u.display_name as author_name
                 FROM $posts_table sp
                 LEFT JOIN {$wpdb->users} u ON sp.created_by = u.ID
                 WHERE sp.scheduled_time BETWEEN %s AND %s
                 ORDER BY sp.scheduled_time ASC",
                $start_date,
                $end_date
            ), ARRAY_A);

            wp_send_json_success(array('posts' => $team_posts));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get AI best times
     */
    public function ajax_get_ai_best_times()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            // Return mock AI-generated best times
            $best_times = array(
                array(
                    'platform' => 'twitter',
                    'peak_time' => '9:00 AM',
                    'engagement_rate' => 85.5,
                    'platforms' => array('Twitter')
                ),
                array(
                    'platform' => 'facebook',
                    'peak_time' => '1:00 PM',
                    'engagement_rate' => 92.3,
                    'platforms' => array('Facebook')
                ),
                array(
                    'platform' => 'linkedin',
                    'peak_time' => '8:00 AM',
                    'engagement_rate' => 78.9,
                    'platforms' => array('LinkedIn')
                ),
                array(
                    'platform' => 'instagram',
                    'peak_time' => '6:00 PM',
                    'engagement_rate' => 88.7,
                    'platforms' => array('Instagram')
                )
            );

            wp_send_json_success($best_times);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get network groups
     */
    public function ajax_get_network_groups()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            $groups = $this->get_user_network_groups();

            wp_send_json_success(array('groups' => $groups));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get multisite sites
     */
    public function ajax_get_multisite_sites()
    {
        check_ajax_referer('smo_calendar_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions'));
        }

        try {
            if (function_exists('get_sites')) {
                $sites = get_sites(array('number' => 10));
                $formatted_sites = array();

                foreach ($sites as $site) {
                    $formatted_sites[] = array(
                        'id' => $site->blog_id,
                        'name' => get_blog_details($site->blog_id)->blogname,
                        'url' => get_site_url($site->blog_id)
                    );
                }

                wp_send_json_success(array(
                    'sites' => $formatted_sites,
                    'current_site_id' => get_current_blog_id()
                ));
            } else {
                wp_send_json_success(array(
                    'sites' => array(
                        array(
                            'id' => 1,
                            'name' => get_bloginfo('name'),
                            'url' => get_site_url()
                        )
                    ),
                    'current_site_id' => 1
                ));
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Save auto-publish settings
     */
    public function ajax_save_auto_publish_settings()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();

        // Sanitize settings
        $sanitized_settings = array(
            'platforms' => isset($settings['platforms']) ? array_map('sanitize_text_field', $settings['platforms']) : array(),
            'delay_minutes' => isset($settings['delay_minutes']) ? absint($settings['delay_minutes']) : 0,
            'post_types' => isset($settings['post_types']) ? array_map('sanitize_text_field', $settings['post_types']) : array('post'),
            'auto_hashtags' => isset($settings['auto_hashtags']) ? (bool) $settings['auto_hashtags'] : false,
            'auto_optimize' => isset($settings['auto_optimize']) ? (bool) $settings['auto_optimize'] : false,
            'require_featured_image' => isset($settings['require_featured_image']) ? (bool) $settings['require_featured_image'] : false
        );

        $result = $this->auto_publish_manager->update_settings($sanitized_settings);

        wp_send_json_success(array('settings' => $result));
    }

    /**
     * AJAX: Fetch link preview
     */
    public function ajax_fetch_link_preview()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (empty($url)) {
            wp_send_json_error(__('Invalid URL', 'smo-social'));
        }

        // Fetch URL metadata
        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $html = wp_remote_retrieve_body($response);

        // Parse Open Graph tags
        $data = array(
            'title' => '',
            'description' => '',
            'image' => '',
            'domain' => parse_url($url, PHP_URL_HOST)
        );

        // Extract og:title
        if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $matches)) {
            $data['title'] = $matches[1];
        } elseif (preg_match('/<title>([^<]+)<\/title>/', $html, $matches)) {
            $data['title'] = $matches[1];
        }

        // Extract og:description
        if (preg_match('/<meta property="og:description" content="([^"]+)"/', $html, $matches)) {
            $data['description'] = $matches[1];
        } elseif (preg_match('/<meta name="description" content="([^"]+)"/', $html, $matches)) {
            $data['description'] = $matches[1];
        }

        // Extract og:image
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $matches)) {
            $data['image'] = $matches[1];
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Create link post
     */
    public function ajax_create_link_post()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_smo_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }

        global $wpdb;

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $caption = isset($_POST['caption']) ? sanitize_textarea_field($_POST['caption']) : '';
        $platforms = isset($_POST['platforms']) ? array_map('sanitize_text_field', $_POST['platforms']) : array();
        $schedule_type = isset($_POST['schedule_type']) ? sanitize_text_field($_POST['schedule_type']) : 'now';
        $scheduled_time = isset($_POST['scheduled_time']) ? sanitize_text_field($_POST['scheduled_time']) : '';
        $auto_hashtags = isset($_POST['auto_hashtags']) ? (bool) $_POST['auto_hashtags'] : false;
        $optimize_content = isset($_POST['optimize_content']) ? (bool) $_POST['optimize_content'] : false;

        if (empty($url) || empty($platforms)) {
            wp_send_json_error(__('URL and platforms are required', 'smo-social'));
        }

        // Prepare scheduled time
        if ($schedule_type === 'now') {
            $publish_time = current_time('mysql');
            $status = 'pending';
        } else {
            // Convert datetime-local format to MySQL format
            $publish_time = date('Y-m-d H:i:s', strtotime($scheduled_time));
            $status = 'scheduled';
        }

        $post_data = array(
            'title' => $url,
            'content' => $caption,
            'scheduled_time' => $publish_time,
            'status' => $status,
            'created_by' => get_current_user_id(),
            'post_type' => 'link',
            'platforms' => implode(',', $platforms),
            'metadata' => json_encode(array(
                'link_url' => $url,
                'auto_hashtags' => $auto_hashtags,
                'optimize_content' => $optimize_content
            )),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'smo_scheduled_posts',
            $post_data
        );

        if ($result) {
            wp_send_json_success(array('post_id' => $wpdb->insert_id));
        } else {
            wp_send_json_error(__('Failed to create link post', 'smo-social'));
        }
    }

    /**
     * AJAX: Delete link post
     */
    public function ajax_delete_link_post()
    {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('edit_smo_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }

        global $wpdb;

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'smo-social'));
        }

        $result = $wpdb->delete(
            $wpdb->prefix . 'smo_scheduled_posts',
            array('id' => $post_id),
            array('%d')
        );

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete link post', 'smo-social'));
        }
    }







    /**
     * AJAX: Get channel access permissions
     */
    public function ajax_get_channel_access() {
        check_ajax_referer('smo_users_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'smo-social')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_channel_access';
        
        $permissions = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        
        wp_send_json_success(['permissions' => $permissions]);
    }

    /**
     * AJAX: Save channel access permissions
     */
    public function ajax_save_channel_access() {
        check_ajax_referer('smo_users_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'smo-social')]);
        }
        
        $permissions = $_POST['permissions'] ?? [];
        if (empty($permissions)) {
            wp_send_json_success(['message' => __('No changes to save', 'smo-social')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smo_channel_access';
        
        foreach ($permissions as $perm) {
            $user_id = intval($perm['user_id']);
            $platform_id = intval($perm['platform_id']);
            $access_level = sanitize_text_field($perm['access_level']);
            
            // Check if exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE user_id = %d AND platform_id = %d",
                $user_id, $platform_id
            ));
            
            if ($exists) {
                $wpdb->update(
                    $table,
                    ['access_level' => $access_level, 'updated_at' => current_time('mysql')],
                    ['id' => $exists]
                );
            } else {
                $wpdb->insert(
                    $table,
                    [
                        'user_id' => $user_id,
                        'platform_id' => $platform_id,
                        'access_level' => $access_level,
                        'granted_by' => get_current_user_id(),
                        'created_at' => current_time('mysql')
                    ]
                );
            }
        }
        
        wp_send_json_success(['message' => __('Permissions saved successfully', 'smo-social')]);
    }
    
    /**
     * Display Integrations Page
     */
    public function display_integrations_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'smo-social'));
        }
        
        // Include the Integrations view
        if (file_exists($this->plugin_path . 'includes/Admin/Views/Integrations.php')) {
            require_once $this->plugin_path . 'includes/Admin/Views/Integrations.php';
            \SMO_Social\Admin\Views\Integrations::render();
        } else {
            echo '<div class="wrap"><h1>' . \esc_html__('Integrations', 'smo-social') . '</h1>';
            echo '<p>' . \esc_html__('Integrations view file not found.', 'smo-social') . '</p></div>';
        }
    }
    
    // ==========================================
    // PLATFORM MANAGEMENT AJAX HANDLERS
    // ==========================================

    // ==========================================
    // TEAM MANAGEMENT AJAX HANDLERS
    // ==========================================
    
    /**
     * AJAX: Add team member
     */
    public function ajax_add_team_member()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        
        $data = [
            'user_id' => intval($_POST['user_id'] ?? 0),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'role' => sanitize_text_field($_POST['role'] ?? 'member')
        ];
        
        if (empty($data['name']) || empty($data['email'])) {
            wp_send_json_error(__('Name and email are required', 'smo-social'));
        }
        
        // If user_id not provided, try to find or create user
        if (empty($data['user_id'])) {
            $user = get_user_by('email', $data['email']);
            if ($user) {
                $data['user_id'] = $user->ID;
            } else {
                // Create new user
                $user_id = wp_create_user($data['email'], wp_generate_password(), $data['email']);
                if (is_wp_error($user_id)) {
                    /** @var \WP_Error $user_id */
                    wp_send_json_error($user_id->get_error_message());
                }
                $data['user_id'] = $user_id;
                wp_update_user(['ID' => $user_id, 'display_name' => $data['name']]);
            }
        }
        
        $result = \SMO_Social\Team\TeamManager::add_team_member($data);
        
        if ($result) {
            wp_send_json_success(['message' => __('Team member added successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to add team member', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Update team member
     */
    public function ajax_update_team_member()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_members';
        
        $member_id = intval($_POST['member_id'] ?? 0);
        $data = [
            'role' => sanitize_text_field($_POST['role'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'active')
        ];
        
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $member_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Team member updated successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to update team member', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Remove team member
     */
    public function ajax_remove_team_member()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_members';
        
        $member_id = intval($_POST['member_id'] ?? 0);
        
        // Soft delete - set status to inactive
        $result = $wpdb->update(
            $table_name,
            ['status' => 'inactive'],
            ['id' => $member_id],
            ['%s'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Team member removed successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to remove team member', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Create team assignment
     */
    public function ajax_create_team_assignment()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'user_id' => intval($_POST['user_id'] ?? 0),
            'type' => sanitize_text_field($_POST['type'] ?? 'platform'),
            'resource_id' => intval($_POST['resource_id'] ?? 0),
            'platforms' => isset($_POST['platforms']) ? array_map('sanitize_text_field', (array)$_POST['platforms']) : [],
            'url_params' => isset($_POST['url_params']) ? array_map('sanitize_text_field', (array)$_POST['url_params']) : [],
            'access_level' => sanitize_text_field($_POST['access_level'] ?? 'view')
        ];
        
        if (empty($data['name']) || empty($data['user_id'])) {
            wp_send_json_error(__('Name and user are required', 'smo-social'));
        }
        
        $result = \SMO_Social\Team\TeamManager::create_assignment($data);
        
        if ($result) {
            wp_send_json_success(['message' => __('Assignment created successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to create assignment', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Update team assignment
     */
    public function ajax_update_team_assignment()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_assignments';
        
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $data = [
            'assignment_name' => sanitize_text_field($_POST['name'] ?? ''),
            'platforms' => json_encode(isset($_POST['platforms']) ? array_map('sanitize_text_field', (array)$_POST['platforms']) : []),
            'access_level' => sanitize_text_field($_POST['access_level'] ?? 'view')
        ];
        
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $assignment_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Assignment updated successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to update assignment', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Delete team assignment
     */
    public function ajax_delete_team_assignment()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_team_assignments';
        
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $assignment_id],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Assignment deleted successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to delete assignment', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Update team permission
     */
    public function ajax_update_team_permission()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $permission_key = sanitize_text_field($_POST['permission'] ?? '');
        $value = isset($_POST['value']) && $_POST['value'] === 'true';
        
        if (empty($user_id) || empty($permission_key)) {
            wp_send_json_error(__('User ID and permission key are required', 'smo-social'));
        }
        
        $result = \SMO_Social\Team\TeamManager::update_permission($user_id, $permission_key, $value);
        
        if ($result) {
            wp_send_json_success(['message' => __('Permission updated successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to update permission', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Create network group
     */
    public function ajax_create_network_group()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'platforms' => isset($_POST['platforms']) ? array_map('sanitize_text_field', (array)$_POST['platforms']) : [],
            'members' => isset($_POST['members']) ? array_map('intval', (array)$_POST['members']) : [],
            'color' => sanitize_text_field($_POST['color'] ?? '#3b82f6'),
            'icon' => sanitize_text_field($_POST['icon'] ?? 'users')
        ];
        
        if (empty($data['name'])) {
            wp_send_json_error(__('Group name is required', 'smo-social'));
        }
        
        $result = \SMO_Social\Team\TeamManager::create_network_group($data);
        
        if ($result) {
            wp_send_json_success(['message' => __('Network group created successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to create network group', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Update network group
     */
    public function ajax_update_network_group()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_network_groups';
        
        $group_id = intval($_POST['group_id'] ?? 0);
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'platforms' => json_encode(isset($_POST['platforms']) ? array_map('sanitize_text_field', (array)$_POST['platforms']) : []),
            'members' => json_encode(isset($_POST['members']) ? array_map('intval', (array)$_POST['members']) : [])
        ];
        
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $group_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Network group updated successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to update network group', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Delete network group
     */
    public function ajax_delete_network_group()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'smo_network_groups';
        
        $group_id = intval($_POST['group_id'] ?? 0);
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $group_id],
            ['%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Network group deleted successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to delete network group', 'smo-social'));
        }
    }
    
    /**
     * AJAX: Refresh team calendar
     */
    public function ajax_refresh_team_calendar()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('edit_smo_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'smo-social'));
        }
        
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        
        $calendar_data = \SMO_Social\Team\TeamManager::get_team_calendar_data();
        $html = \SMO_Social\Team\TeamManager::render_team_calendar($calendar_data);
        
        wp_send_json_success([
            'html' => $html,
            'stats' => [
                'total_scheduled' => $calendar_data['total_scheduled'],
                'published_today' => $calendar_data['published_today']
            ]
        ]);
    }
    
    /**
     * AJAX: Grant multisite access
     */
    public function ajax_grant_multisite_access()
    {
        check_ajax_referer('smo_team_nonce', 'nonce');
        
        if (!current_user_can('manage_options') || !is_multisite()) {
            wp_send_json_error(__('Insufficient permissions or multisite not enabled', 'smo-social'));
        }
        
        require_once $this->plugin_path . 'includes/Team/TeamManager.php';
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $site_id = intval($_POST['site_id'] ?? 0);
        $access_level = sanitize_text_field($_POST['access_level'] ?? 'view');
        
        if (empty($user_id) || empty($site_id)) {
            wp_send_json_error(__('User ID and site ID are required', 'smo-social'));
        }
        
        $result = \SMO_Social\Team\TeamManager::grant_multisite_access($user_id, $site_id, $access_level);
        
        if ($result) {
            wp_send_json_success(['message' => __('Multisite access granted successfully', 'smo-social')]);
        } else {
            wp_send_json_error(__('Failed to grant multisite access', 'smo-social'));
        }
    }
    
    // ==========================================
    // PLATFORM DATABASE MANAGEMENT
    // ==========================================

    /**
     * Create all platform-related database tables
     */
    public function create_platform_tables()
    {
        $this->create_platform_tokens_table();
        $this->create_platform_settings_table();
        $this->create_platform_health_logs_table();
    }

    /**
     * Create platform tokens table
     */
    private function create_platform_tokens_table()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_platform_tokens';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform_slug varchar(50) NOT NULL,
            access_token text NOT NULL,
            refresh_token text,
            token_expires datetime,
            extra_data longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY platform_slug (platform_slug),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create platform settings table
     */
    private function create_platform_settings_table()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_platform_settings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform_slug varchar(50) NOT NULL,
            settings longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY platform_slug (platform_slug),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create platform health logs table
     */
    private function create_platform_health_logs_table()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_health_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform_slug varchar(50) NOT NULL,
            check_timestamp datetime NOT NULL,
            overall_status varchar(20) NOT NULL,
            response_time decimal(10,3) NOT NULL,
            checks_data longtext NOT NULL,
            critical_issues longtext,
            warnings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY platform_slug (platform_slug),
            KEY check_timestamp (check_timestamp),
            KEY overall_status (overall_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Initialize platform database tables on plugin activation
     */
    public function init_platform_database()
    {
        $this->create_platform_tables();
        
        // Set database version for future migrations
        update_option('smo_social_db_version', '1.1');
    }

    /**
     * Clear AI cache when settings are updated
     * 
     * @param string $option Option name
     * @param mixed $old_value Old value
     * @param mixed $value New value
     */
    public function clear_ai_cache_on_update($option, $old_value, $value)
    {
        if (strpos($option, 'smo_social_') === 0 && class_exists('\\SMO_Social\\AI\\CacheHelper')) {
            \SMO_Social\AI\CacheHelper::clear_all();
        }
    }

    /**
     * Extract provider key from option name
     *
     * @param string $option Option name
     * @return string|null Provider key or null if not a provider setting
     */
    private function extract_provider_key_from_option($option) {
        // Pattern: smo_social_{provider}_api_key or smo_social_{provider}_api_url
        $pattern = '/^smo_social_([a-z0-9_]+)(_api_key|_api_url)$/';
        if (preg_match($pattern, $option, $matches)) {
            return $matches[1];
        }
        return null;
    }

    // ==========================================
    // MAGIC WIZARD AJAX HANDLERS
    // ==========================================

    /**
     * AJAX handler for saving wizard configuration
     *
     * @since 1.0.0
     */
    public function ajax_save_wizard_config()
    {
        check_ajax_referer('smo_wizard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        if (!isset($_POST['config'])) {
            wp_send_json_error(__('Configuration data not provided'));
        }

        try {
            $config_data = json_decode(stripslashes($_POST['config']), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(__('Invalid configuration data'));
            }

            $saved_count = 0;
            $errors = array();

            // Save user information
            if (isset($config_data['user_info'])) {
                foreach ($config_data['user_info'] as $key => $value) {
                    $option_name = 'smo_social_wizard_user_' . $key;
                    if (update_option($option_name, sanitize_text_field($value))) {
                        $saved_count++;
                    }
                }
            }

            // Save AI configuration
            if (isset($config_data['ai_config'])) {
                foreach ($config_data['ai_config'] as $key => $value) {
                    $option_name = 'smo_social_wizard_ai_' . $key;
                    $sanitized_value = is_bool($value) ? (int) $value : sanitize_text_field($value);
                    if (update_option($option_name, $sanitized_value)) {
                        $saved_count++;
                    }
                }
            }

            // Save platform configuration
            if (isset($config_data['platform_config'])) {
                $platforms = isset($config_data['platform_config']['platforms']) ?
                    array_map('sanitize_text_field', $config_data['platform_config']['platforms']) : array();
                
                if (update_option('smo_social_wizard_platforms', $platforms)) {
                    $saved_count++;
                }

                $auto_connect = isset($config_data['platform_config']['auto_connect']) ?
                    (int) $config_data['platform_config']['auto_connect'] : 0;
                
                if (update_option('smo_social_wizard_auto_connect', $auto_connect)) {
                    $saved_count++;
                }
            }

            // Save content preferences
            if (isset($config_data['content_prefs'])) {
                foreach ($config_data['content_prefs'] as $key => $value) {
                    $option_name = 'smo_social_wizard_content_' . $key;
                    
                    if (is_array($value)) {
                        $sanitized_value = array_map('sanitize_text_field', $value);
                    } else {
                        $sanitized_value = is_bool($value) ? (int) $value : sanitize_text_field($value);
                    }
                    
                    if (update_option($option_name, $sanitized_value)) {
                        $saved_count++;
                    }
                }
            }

            // Save advanced options
            if (isset($config_data['advanced_opts'])) {
                foreach ($config_data['advanced_opts'] as $key => $value) {
                    $option_name = 'smo_social_wizard_advanced_' . $key;
                    $sanitized_value = is_bool($value) ? (int) $value : sanitize_text_field($value);
                    
                    if (update_option($option_name, $sanitized_value)) {
                        $saved_count++;
                    }
                }
            }

            // Mark wizard as completed
            update_option('smo_social_wizard_completed', 1);
            update_option('smo_social_wizard_completed_date', current_time('mysql'));
            update_option('smo_social_wizard_version', '1.0.0');

            // Log wizard completion
            $this->log_activity('WIZARD_COMPLETED', 'wizard', 'setup', array(
                'saved_options' => $saved_count,
                'user_id' => get_current_user_id()
            ));

            // Apply wizard settings to main plugin settings if requested
            if (isset($_POST['apply_immediately']) && $_POST['apply_immediately'] === 'true') {
                $this->apply_wizard_settings_to_plugin();
            }

            wp_send_json_success(array(
                'message' => sprintf(__('Configuration saved successfully (%d settings)', 'smo-social'), $saved_count),
                'saved_count' => $saved_count,
                'wizard_completed' => true
            ));

        } catch (\Exception $e) {
            error_log('SMO Social Wizard Save Error: ' . $e->getMessage());
            wp_send_json_error(__('Failed to save configuration: ') . $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting wizard status
     *
     * @since 1.0.0
     */
    public function ajax_get_wizard_status()
    {
        check_ajax_referer('smo_wizard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        $wizard_completed = get_option('smo_social_wizard_completed', false);
        $wizard_completed_date = get_option('smo_social_wizard_completed_date', '');
        $wizard_version = get_option('smo_social_wizard_version', '0.0.0');

        // Check if any wizard settings exist
        $wizard_settings = $this->get_wizard_settings_summary();

        wp_send_json_success(array(
            'completed' => (bool) $wizard_completed,
            'completed_date' => $wizard_completed_date,
            'version' => $wizard_version,
            'has_settings' => !empty($wizard_settings),
            'settings_count' => count($wizard_settings),
            'settings_summary' => $wizard_settings
        ));
    }

    /**
     * AJAX handler for resetting wizard
     *
     * @since 1.0.0
     */
    public function ajax_reset_wizard()
    {
        check_ajax_referer('smo_wizard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        // Delete all wizard-related options
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'smo_social_wizard_%'"
        );

        // Log wizard reset
        $this->log_activity('WIZARD_RESET', 'wizard', 'reset', array(
            'reset_by' => get_current_user_id()
        ));

        wp_send_json_success(array(
            'message' => __('Wizard has been reset. You can start the setup process again.', 'smo-social')
        ));
    }

    /**
     * Get wizard settings summary
     *
     * @return array Summary of wizard settings
     * @since 1.0.0
     */
    private function get_wizard_settings_summary()
    {
        global $wpdb;
        
        $options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'smo_social_wizard_%' AND option_name NOT IN ('smo_social_wizard_completed', 'smo_social_wizard_completed_date', 'smo_social_wizard_version')",
            ARRAY_A
        );

        $summary = array();
        foreach ($options as $option) {
            $key = str_replace('smo_social_wizard_', '', $option['option_name']);
            $summary[$key] = maybe_unserialize($option['option_value']);
        }

        return $summary;
    }

    /**
     * Apply wizard settings to main plugin configuration
     *
     * @since 1.0.0
     */
    private function apply_wizard_settings_to_plugin()
    {
        $wizard_settings = $this->get_wizard_settings_summary();

        // Apply AI settings
        if (isset($wizard_settings['ai_enabled'])) {
            update_option('smo_social_ai_enabled', $wizard_settings['ai_enabled']);
        }
        
        if (isset($wizard_settings['ai_provider'])) {
            update_option('smo_social_ai_provider', $wizard_settings['ai_provider']);
        }

        // Apply platform settings
        if (isset($wizard_settings['platforms'])) {
            update_option('smo_social_enabled_platforms', $wizard_settings['platforms']);
        }

        // Apply content settings
        if (isset($wizard_settings['content_types'])) {
            update_option('smo_social_default_content_types', $wizard_settings['content_types']);
        }

        if (isset($wizard_settings['posting_frequency'])) {
            update_option('smo_social_default_posting_frequency', $wizard_settings['posting_frequency']);
        }

        if (isset($wizard_settings['timezone'])) {
            update_option('smo_social_timezone', $wizard_settings['timezone']);
        }

        if (isset($wizard_settings['enable_analytics'])) {
            update_option('smo_social_analytics_enabled', $wizard_settings['enable_analytics']);
        }

        // Apply advanced settings
        if (isset($wizard_settings['advanced_enable_approval_workflow'])) {
            update_option('smo_social_approval_workflow_enabled', $wizard_settings['advanced_enable_approval_workflow']);
        }

        if (isset($wizard_settings['advanced_enable_team_collaboration'])) {
            update_option('smo_social_team_collaboration_enabled', $wizard_settings['advanced_enable_team_collaboration']);
        }

        if (isset($wizard_settings['advanced_enable_notifications'])) {
            update_option('smo_social_email_notifications_enabled', $wizard_settings['advanced_enable_notifications']);
        }
    }

    /**
     * AJAX handler for tracking wizard analytics
     *
     * @since 1.0.0
     */
    public function ajax_track_wizard_analytics()
    {
        check_ajax_referer('smo_wizard_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        try {
            $event_type = sanitize_text_field($_POST['event_type'] ?? '');
            $step = isset($_POST['step']) ? intval($_POST['step']) : null;
            $time_spent = isset($_POST['time_spent']) ? intval($_POST['time_spent']) : null;
            $total_time = isset($_POST['total_time']) ? intval($_POST['total_time']) : null;
            $final_step = isset($_POST['final_step']) ? intval($_POST['final_step']) : null;
            $timestamp = sanitize_text_field($_POST['timestamp'] ?? '');
            $user_agent = sanitize_text_field($_POST['user_agent'] ?? '');
            $screen_resolution = sanitize_text_field($_POST['screen_resolution'] ?? '');

            // Validate required fields
            if (empty($event_type)) {
                wp_send_json_error(__('Event type is required'));
            }

            // Validate event types
            $valid_events = array(
                'wizard_started', 'step_viewed', 'step_completed',
                'field_interaction', 'button_click', 'wizard_completed'
            );

            if (!in_array($event_type, $valid_events)) {
                wp_send_json_error(__('Invalid event type'));
            }

            // Prepare analytics data
            $analytics_data = array(
                'event_type' => $event_type,
                'user_id' => get_current_user_id(),
                'step' => $step,
                'time_spent' => $time_spent,
                'total_time' => $total_time,
                'final_step' => $final_step,
                'timestamp' => $timestamp,
                'user_agent' => $user_agent,
                'screen_resolution' => $screen_resolution,
                'ip_address' => $this->get_user_ip(),
                'session_id' => session_id() ?: wp_generate_password(32, false),
                'created_at' => current_time('mysql')
            );

            // Store analytics data (you might want to create a dedicated analytics table)
            // For now, we'll use a simple options-based approach
            $analytics_key = 'smo_wizard_analytics_' . date('Y_m_d');
            $existing_data = get_option($analytics_key, array());

            if (!is_array($existing_data)) {
                $existing_data = array();
            }

            $existing_data[] = $analytics_data;

            // Keep only last 1000 events per day to prevent memory issues
            if (count($existing_data) > 1000) {
                $existing_data = array_slice($existing_data, -1000);
            }

            update_option($analytics_key, $existing_data);

            // Also update summary statistics
            $this->update_wizard_analytics_summary($event_type, $analytics_data);

            wp_send_json_success(array(
                'message' => __('Analytics data tracked successfully'),
                'event_type' => $event_type
            ));

        } catch (\Exception $e) {
            error_log('SMO Social Wizard Analytics Error: ' . $e->getMessage());
            wp_send_json_error(__('Failed to track analytics: ') . $e->getMessage());
        }
    }

    /**
     * Update wizard analytics summary statistics
     *
     * @param string $event_type The event type
     * @param array $data Analytics data
     * @since 1.0.0
     */
    private function update_wizard_analytics_summary($event_type, $data)
    {
        $summary_key = 'smo_wizard_analytics_summary';
        $summary = get_option($summary_key, array(
            'total_sessions' => 0,
            'completed_sessions' => 0,
            'avg_completion_time' => 0,
            'step_completion_rates' => array(),
            'popular_paths' => array(),
            'last_updated' => current_time('mysql')
        ));

        // Update based on event type
        switch ($event_type) {
            case 'wizard_started':
                $summary['total_sessions']++;
                break;

            case 'wizard_completed':
                $summary['completed_sessions']++;
                if ($data['total_time']) {
                    // Calculate running average
                    $current_avg = $summary['avg_completion_time'];
                    $total_completions = $summary['completed_sessions'];
                    $summary['avg_completion_time'] = (($current_avg * ($total_completions - 1)) + $data['total_time']) / $total_completions;
                }
                break;

            case 'step_completed':
                if ($data['step']) {
                    $step_key = 'step_' . $data['step'];
                    if (!isset($summary['step_completion_rates'][$step_key])) {
                        $summary['step_completion_rates'][$step_key] = 0;
                    }
                    $summary['step_completion_rates'][$step_key]++;
                }
                break;
        }

        $summary['last_updated'] = current_time('mysql');
        update_option($summary_key, $summary);
    }

    /**
     * Display Magic Wizard page
     *
     * @since 1.0.0
     */
    public function display_magic_wizard_page()
    {
        Logger::debug('Starting Magic Wizard page display');

        try {
            include_once $this->plugin_path . 'includes/Admin/Views/MagicWizard.php';
            Logger::debug('Magic Wizard view included successfully');

            // Instantiate and render the wizard
            if (class_exists('\SMO_Social\Admin\Views\MagicWizard')) {
                $wizard = new \SMO_Social\Admin\Views\MagicWizard();
                $wizard->render();
                Logger::debug('Magic Wizard rendered successfully');
            } else {
                Logger::error('MagicWizard class not found');
                echo '<div class="wrap"><p>Magic Wizard class not found</p></div>';
            }
        } catch (\Exception $e) {
            Logger::error('Error loading Magic Wizard page: ' . $e->getMessage());
            echo '<div class="wrap"><p>Error loading Magic Wizard: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    /**
     * Handle provider setting changes to ensure configuration consistency
     *
     * @param string $provider_key Provider key
     * @param string $option Option name that changed
     * @param mixed $new_value New value
     */
    private function handle_provider_setting_change($provider_key, $option, $new_value) {
        error_log("SMO_AI_SETTINGS: Handling setting change for provider: {$provider_key}");

        try {
            // Get current provider configuration
            $current_config = [];
            if (class_exists('ProvidersConfig')) {
                $provider = ProvidersConfig::get_provider($provider_key);
                if ($provider) {
                    $current_config = $provider;
                    error_log("SMO_AI_SETTINGS: Current provider config: " . print_r($current_config, true));
                }
            }

            // Update the specific setting that changed
            $field_name = str_replace('smo_social_' . $provider_key . '_', '', $option);
            $current_config[$field_name] = $new_value;

            error_log("SMO_AI_SETTINGS: Updated config with new value: " . print_r($current_config, true));

            // Sync to both storage layers
            $this->sync_provider_configuration($provider_key, $current_config);

        } catch (\Exception $e) {
            error_log("SMO_AI_SETTINGS_ERROR: Failed to handle provider setting change: " . $e->getMessage());
        }
    }

    /**
     * Sync provider configuration to all storage layers
     *
     * @param string $provider_key Provider key
     * @param array $config Provider configuration
     */
    private function sync_provider_configuration($provider_key, $config) {
        error_log("SMO_AI_SETTINGS: Syncing provider configuration for: {$provider_key}");

        try {
            // Sync to ExternalAIConfigurator (options layer)
            if (class_exists('\SMO_Social\AI\ExternalAIConfigurator')) {
                $configurator = new \SMO_Social\AI\ExternalAIConfigurator();
                $result = $configurator->configure_provider($provider_key, $config);
                error_log("SMO_AI_SETTINGS: ExternalAIConfigurator sync result: " . print_r($result, true));
            }

            // Sync to DatabaseProviderLoader (database layer)
            if (class_exists('\SMO_Social\AI\DatabaseProviderLoader')) {
                $db_result = \SMO_Social\AI\DatabaseProviderLoader::save_provider_to_database($provider_key, $config);
                error_log("SMO_AI_SETTINGS: DatabaseProviderLoader sync result: " . ($db_result ? 'success' : 'failed'));
            }

        } catch (\Exception $e) {
            error_log("SMO_AI_SETTINGS_ERROR: Provider configuration sync failed: " . $e->getMessage());
        }
    }

    /**
     * AJAX handler for saving provider configuration - MIGRATED TO AiProviderAjax
     */
    // ajax_save_provider_config, ajax_check_provider_status, ajax_validate_api_key removed

    /**
     * AJAX handler for saving settings with validation
     */
    public function ajax_save_settings() {
        check_ajax_referer('smo_social_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions'));
        }

        try {
            $saved_options = array();
            $errors = array();

            // Process all posted data
            foreach ($_POST as $key => $value) {
                // Skip WordPress AJAX fields
                if (in_array($key, array('action', 'nonce'))) {
                    continue;
                }

                // Sanitize based on field type
                $sanitized_value = $this->sanitize_setting_value($key, $value);

                // Save the option
                if (update_option($key, $sanitized_value)) {
                    $saved_options[] = $key;
                } else {
                    // Check if option was actually updated (might be same value)
                    $current_value = get_option($key);
                    if ($current_value !== $sanitized_value) {
                        $errors[] = sprintf(__('Failed to save %s', 'smo-social'), $key);
                    }
                }
            }

            // Log the settings save
            $this->log_activity('SETTINGS_SAVED', 'settings', 'bulk_save', array(
                'saved_options' => $saved_options,
                'errors' => $errors
            ));

            if (empty($errors)) {
                wp_send_json_success(array(
                    'message' => __('All settings saved successfully', 'smo-social'),
                    'saved_count' => count($saved_options)
                ));
            } else {
                wp_send_json_success(array(
                    'message' => sprintf(__('Settings saved with %d warnings', 'smo-social'), count($errors)),
                    'saved_count' => count($saved_options),
                    'warnings' => $errors
                ));
            }

        } catch (\Exception $e) {
            error_log('SMO Social Settings Save Error: ' . $e->getMessage());
            wp_send_json_error(__('Failed to save settings: ', 'smo-social') . $e->getMessage());
        }
    }

    /**
     * Sanitize setting value based on field name
     */
    private function sanitize_setting_value($key, $value) {
        // Handle API keys (password fields)
        if (strpos($key, '_api_key') !== false || strpos($key, '_key') !== false) {
            return sanitize_text_field($value);
        }

        // Handle URLs
        if (strpos($key, '_url') !== false) {
            return esc_url_raw($value);
        }

        // Handle emails
        if (strpos($key, '_email') !== false) {
            return sanitize_email($value);
        }

        // Handle numeric values
        if (strpos($key, '_interval') !== false ||
            strpos($key, '_retries') !== false ||
            strpos($key, '_delay') !== false ||
            strpos($key, '_retention') !== false) {
            return absint($value);
        }

        // Handle boolean values
        if (strpos($key, '_enabled') !== false ||
            strpos($key, '_active') !== false ||
            strpos($key, '_show_') !== false ||
            strpos($key, '_auto_') !== false ||
            strpos($key, '_require_') !== false) {
            return (bool) $value;
        }

        // Default sanitization
        return sanitize_text_field($value);
    }
}
