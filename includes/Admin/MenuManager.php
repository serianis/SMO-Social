<?php
namespace SMO_Social\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MenuManager
 * Handles the registration of admin menu pages and subpages.
 */
class MenuManager
{
    /**
     * @var Admin
     */
    private $admin;

    /**
     * MenuManager constructor.
     *
     * @param Admin $admin The main Admin instance.
     */
    public function __construct(Admin $admin)
    {
        $this->admin = $admin;
    }

    /**
     * Registers all admin menu pages.
     */
    public function register_menus()
    {
        // Main menu page
        \add_menu_page(
            __('SMO Social', 'smo-social'),
            __('SMO Social', 'smo-social'),
            'manage_options',
            'smo-social',
            array($this->admin, 'display_dashboard'),
            'dashicons-share',
            30
        );

        // Dashboard submenu
        \add_submenu_page(
            'smo-social',
            __('Dashboard', 'smo-social'),
            __('Dashboard', 'smo-social'),
            'manage_options',
            'smo-social',
            array($this->admin, 'display_dashboard')
        );

        // Magic Wizard (prominently placed for new users)
        \add_submenu_page(
            'smo-social',
            __('Setup Wizard', 'smo-social'),
            __('🪄 Setup Wizard', 'smo-social'),
            'manage_options',
            'smo-social-wizard',
            array($this->admin, 'display_magic_wizard_page')
        );

        // Create Post
        \add_submenu_page(
            'smo-social',
            __('Create Post', 'smo-social'),
            __('Create Post', 'smo-social'),
            'edit_smo_posts',
            'smo-social-create',
            array($this->admin, 'display_create_post_page')
        );

        // All Posts
        \add_submenu_page(
            'smo-social',
            __('All Posts', 'smo-social'),
            __('All Posts', 'smo-social'),
            'edit_smo_posts',
            'smo-social-posts',
            array($this->admin, 'display_posts_page')
        );

        // Calendar
        \add_submenu_page(
            'smo-social',
            __('Calendar', 'smo-social'),
            __('Calendar', 'smo-social'),
            'edit_smo_posts',
            'smo-social-calendar',
            array($this->admin, 'display_calendar_page')
        );

        // Content Import & Automation
        \add_submenu_page(
            'smo-social',
            __('Content Import & Automation', 'smo-social'),
            __('Content Import', 'smo-social'),
            'edit_posts',
            'smo-social-content-import',
            array($this->admin, 'display_content_import_page')
        );

        // Content Organizer
        \add_submenu_page(
            'smo-social',
            __('Content Organizer', 'smo-social'),
            __('Content Organizer', 'smo-social'),
            'edit_posts',
            'smo-social-content-organizer',
            array($this->admin, 'display_content_organizer_page')
        );

        // Platforms
        \add_submenu_page(
            'smo-social',
            __('Platforms', 'smo-social'),
            __('Platforms', 'smo-social'),
            'manage_options',
            'smo-social-platforms',
            array($this->admin, 'display_platforms_page')
        );

        // Advanced Reports
        \add_submenu_page(
            'smo-social',
            __('Reports', 'smo-social'),
            __('Reports', 'smo-social'),
            'view_smo_analytics',
            'smo-social-reports',
            array($this->admin, 'display_reports_page')
        );

        // Templates
        \add_submenu_page(
            'smo-social',
            __('Templates', 'smo-social'),
            __('Templates', 'smo-social'),
            'edit_smo_posts',
            'smo-social-templates',
            array($this->admin, 'display_templates_page')
        );

        // Advanced Scheduling
        \add_submenu_page(
            'smo-social',
            __('Scheduling', 'smo-social'),
            __('Scheduling', 'smo-social'),
            'manage_options',
            'smo-social-scheduling',
            array($this->admin, 'display_advanced_scheduling_page')
        );

        // Users
        \add_submenu_page(
            'smo-social',
            __('Users', 'smo-social'),
            __('Users', 'smo-social'),
            'manage_options',
            'smo-social-users',
            array($this->admin, 'display_users_page')
        );

        // Team Management
        \add_submenu_page(
            'smo-social',
            __('Team Management', 'smo-social'),
            __('Team Management', 'smo-social'),
            'manage_options',
            'smo-social-team-management',
            array($this->admin, 'display_team_management_page')
        );

        // Notifications
        \add_submenu_page(
            'smo-social',
            __('Notifications', 'smo-social'),
            __('Notifications', 'smo-social'),
            'manage_options',
            'smo-social-notifications',
            array($this->admin, 'display_notifications_page')
        );

        // API
        \add_submenu_page(
            'smo-social',
            __('API', 'smo-social'),
            __('API', 'smo-social'),
            'manage_options',
            'smo-social-api',
            array($this->admin, 'display_api_page')
        );

        // Integrations
        \add_submenu_page(
            'smo-social',
            __('Integrations', 'smo-social'),
            __('Integrations', 'smo-social'),
            'manage_options',
            'smo-social-integrations',
            array($this->admin, 'display_integrations_page')
        );

        // Tools
        \add_submenu_page(
            'smo-social',
            __('Tools', 'smo-social'),
            __('Tools', 'smo-social'),
            'manage_options',
            'smo-social-tools',
            array($this->admin, 'display_tools_page')
        );

        // Maintenance
        \add_submenu_page(
            'smo-social',
            __('Maintenance', 'smo-social'),
            __('Maintenance', 'smo-social'),
            'manage_options',
            'smo-social-maintenance',
            array($this->admin, 'display_maintenance_page')
        );

        // Settings
        \add_submenu_page(
            'smo-social',
            __('Settings', 'smo-social'),
            __('Settings', 'smo-social'),
            'manage_options',
            'smo-social-settings',
            array($this->admin, 'display_settings_page')
        );
    }
}
