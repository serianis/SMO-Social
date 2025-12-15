<?php
namespace SMO_Social\Admin;

use SMO_Social\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AssetManager
 * Handles enqueuing of admin assets (CSS/JS).
 */
class AssetManager
{
    /**
     * Plugin URL
     * @var string
     */
    private $plugin_url;

    /**
     * Integration Manager instance
     * @var \SMO_Social\Integrations\IntegrationManager
     */
    private $integration_manager;

    /**
     * AssetManager constructor.
     *
     * @param string $plugin_url The plugin URL.
     * @param \SMO_Social\Integrations\IntegrationManager $integration_manager The integration manager instance.
     */
    public function __construct($plugin_url, $integration_manager)
    {
        $this->plugin_url = $plugin_url;
        $this->integration_manager = $integration_manager;
        
        // Hook into admin_enqueue_scripts to load assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue admin assets
     * 
     * @param string $hook The current admin page hook.
     */
    public function enqueue_assets($hook)
    {
        Logger::debug('enqueue_admin_assets called for hook: ' . $hook);

        // Only load on SMO Social pages
        if (strpos($hook, 'smo-social') === false) {
            Logger::debug('Not loading assets - hook does not contain smo-social');
            return;
        }

        // Check if we're in WordPress environment
        if (!\SMO_Social\Utilities\EnvironmentDetector::isWordPress()) {
            Logger::debug('Not in WordPress environment - skipping asset enqueuing');
            return;
        }

        // Check if wp is available
        if (!isset($GLOBALS['wp'])) {
            Logger::error('$wp global not available during asset enqueuing in WordPress mode');
            return;
        }

        Logger::debug('Loading assets for SMO Social page in WordPress environment');

        // Verify WordPress functions are available
        if (!function_exists('wp_enqueue_style') || !function_exists('wp_enqueue_script') || !function_exists('wp_localize_script')) {
            Logger::error('WordPress enqueuing functions not available');
            return;
        }

        // CSS - Core Foundation Styles (Loaded on all SMO pages)
        $this->enqueue_core_styles();
        
        // CSS - Dashboard and Widget Styles
        if ($hook === 'toplevel_page_smo-social') {
            $this->enqueue_dashboard_styles();
        }
        
        // CSS - API Management
        if (strpos($hook, 'smo-social-api') !== false) {
            $this->enqueue_api_management_styles();
        }
        
        // CSS - Health and Memory Monitoring
        if (strpos($hook, 'smo-social-health-monitoring') !== false ||
            strpos($hook, 'smo-social-memory-monitoring') !== false) {
            $this->enqueue_monitoring_styles();
        }
        
        // CSS - Media Library
        if (strpos($hook, 'smo-social-media') !== false) {
            $this->enqueue_media_library_styles();
        }
        
        // CSS - Enhanced Create Post
        if (strpos($hook, 'smo-social-create') !== false) {
            $this->enqueue_create_post_styles();
        }

        // Page-specific CSS/JS (legacy support - these should be moved to individual methods)
        $this->enqueue_page_specific_assets($hook);

        // JavaScript - ensure WordPress core scripts are loaded first
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-util');
        
        // Enqueue jQuery UI Sortable for Content Organizer Kanban board
        if (strpos($hook, 'smo-social-content-organizer') !== false) {
            wp_enqueue_script('jquery-ui-sortable');
        }
        
        // Enqueue Chart.js for analytics and Content Import
        if (strpos($hook, 'smo-social-analytics') !== false || 
            strpos($hook, 'smo-social-content-import') !== false || 
            $hook === 'toplevel_page_smo-social') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        }

        $admin_js_url = $this->plugin_url . 'assets/js/admin.js';
        Logger::debug('Enqueuing admin JS: ' . $admin_js_url);
        wp_enqueue_script(
            'smo-social-admin',
            $admin_js_url,
            array('jquery', 'wp-util'),
            SMO_SOCIAL_VERSION,
            true
        );

        // Enqueue unified forms JavaScript
        $forms_js_url = $this->plugin_url . 'assets/js/components/forms.js';
        Logger::debug('Enqueuing forms JS: ' . $forms_js_url);
        wp_enqueue_script(
            'smo-social-forms',
            $forms_js_url,
            array('jquery', 'wp-util'),
            SMO_SOCIAL_VERSION,
            true
        );

        // Localize script for AJAX
        $ajax_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_social_nonce'),
            'strings' => array(
                'error' => __('Error', 'smo-social'),
                'success' => __('Success', 'smo-social'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'smo-social')
            )
        );
        wp_localize_script('smo-social-admin', 'smo_social_ajax', $ajax_data);

        // Image Editor Assets (loaded for all SMO pages)
        if (strpos($hook, 'smo-social') !== false) {
            wp_enqueue_style(
                'smo-image-editor',
                $this->plugin_url . 'assets/css/smo-image-editor.css',
                array('smo-social-admin'),
                SMO_SOCIAL_VERSION
            );

            wp_enqueue_script(
                'smo-image-editor',
                $this->plugin_url . 'assets/js/smo-image-editor.js',
                array('jquery', 'smo-social-admin'),
                SMO_SOCIAL_VERSION,
                true
            );
        }

        // For platforms page, ensure scripts are properly loaded
        if (strpos($hook, 'smo-social-platforms') !== false) {
            // Ensure wp-i18n is loaded for translations
            wp_enqueue_script('wp-i18n');
        }
    }

    /**
     * Enqueue core foundation styles (loaded on all SMO pages)
     */
    private function enqueue_core_styles() {
        // Enqueue unified design system first (design tokens) - foundation for all SMO styles
        $design_system_url = $this->plugin_url . 'assets/css/smo-unified-design-system.css';
        Logger::debug('Enqueuing unified design system CSS: ' . $design_system_url);
        wp_enqueue_style(
            'smo-social-design-system',
            $design_system_url,
            array(),
            $this->get_css_version('smo-unified-design-system.css')
        );

        // Enqueue unified forms CSS (form components)
        $forms_url = $this->plugin_url . 'assets/css/smo-forms.css';
        Logger::debug('Enqueuing unified forms CSS: ' . $forms_url);
        wp_enqueue_style(
            'smo-social-forms',
            $forms_url,
            array('smo-social-design-system'),
            $this->get_css_version('smo-forms.css')
        );
        
        // Enqueue unified tabs CSS (base styling for all pages)
        $unified_tabs_url = $this->plugin_url . 'assets/css/smo-unified-tabs.css';
        Logger::debug('Enqueuing unified tabs CSS: ' . $unified_tabs_url);
        wp_enqueue_style(
            'smo-social-unified-tabs',
            $unified_tabs_url,
            array('smo-social-forms'),
            $this->get_css_version('smo-unified-tabs.css')
        );
        
        // Then admin CSS (additional/legacy styles)
        $admin_css_url = $this->plugin_url . 'assets/css/admin.css';
        Logger::debug('Enqueuing admin CSS: ' . $admin_css_url);
        wp_enqueue_style(
            'smo-social-admin',
            $admin_css_url,
            array('smo-social-unified-tabs'),
            $this->get_css_version('admin.css')
        );
    }

    /**
     * Enqueue dashboard and widget styles
     */
    private function enqueue_dashboard_styles() {
        // Dashboard widgets CSS
        $dashboard_widgets_url = $this->plugin_url . 'assets/css/dashboard-widgets.css';
        Logger::debug('Enqueuing dashboard widgets CSS: ' . $dashboard_widgets_url);
        wp_enqueue_style(
            'smo-social-dashboard-widgets',
            $dashboard_widgets_url,
            array('smo-social-admin'),
            $this->get_css_version('dashboard-widgets.css')
        );
        
        // Dashboard redesign CSS (legacy support)
        $dashboard_css_url = $this->plugin_url . 'assets/css/dashboard-redesign.css';
        Logger::debug('Enqueuing dashboard CSS: ' . $dashboard_css_url);
        wp_enqueue_style(
            'smo-social-dashboard-redesign',
            $dashboard_css_url,
            array('smo-social-admin'),
            $this->get_css_version('dashboard-redesign.css')
        );
        
        // Also enqueue dashboard JS
        $dashboard_js_url = $this->plugin_url . 'assets/js/dashboard-redesign.js';
        wp_enqueue_script(
            'smo-social-dashboard-redesign',
            $dashboard_js_url,
            array('jquery'),
            SMO_SOCIAL_VERSION,
            true
        );
        
        wp_localize_script('smo-social-dashboard-redesign', 'smo_dashboard_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smo_social_nonce'),
            'create_post_url' => admin_url('admin.php?page=smo-social-create')
        ));
    }

    /**
     * Enqueue API management styles
     */
    private function enqueue_api_management_styles() {
        $api_management_url = $this->plugin_url . 'assets/css/smo-api-management.css';
        Logger::debug('Enqueuing API management CSS: ' . $api_management_url);
        wp_enqueue_style(
            'smo-social-api-management',
            $api_management_url,
            array('smo-social-admin'),
            $this->get_css_version('smo-api-management.css')
        );
    }

    /**
     * Enqueue monitoring interfaces styles
     */
    private function enqueue_monitoring_styles() {
        $monitoring_url = $this->plugin_url . 'assets/css/smo-monitoring-interfaces.css';
        Logger::debug('Enqueuing monitoring interfaces CSS: ' . $monitoring_url);
        wp_enqueue_style(
            'smo-social-monitoring-interfaces',
            $monitoring_url,
            array('smo-social-admin'),
            $this->get_css_version('smo-monitoring-interfaces.css')
        );
    }

    /**
     * Enqueue media library styles
     */
    private function enqueue_media_library_styles() {
        $media_library_url = $this->plugin_url . 'assets/css/smo-media-library.css';
        Logger::debug('Enqueuing media library CSS: ' . $media_library_url);
        wp_enqueue_style(
            'smo-social-media-library',
            $media_library_url,
            array('smo-social-admin'),
            $this->get_css_version('smo-media-library.css')
        );
        
        // Also enqueue media library JS
        wp_enqueue_script(
            'smo-media-library',
            $this->plugin_url . 'assets/js/smo-media-library.js',
            array('jquery', 'jquery-ui-sortable'),
            SMO_SOCIAL_VERSION,
            true
        );
    }

    /**
     * Enqueue enhanced create post styles
     */
    private function enqueue_create_post_styles() {
        $enhanced_create_post_url = $this->plugin_url . 'assets/css/smo-enhanced-create-post.css';
        Logger::debug('Enqueuing enhanced create post CSS: ' . $enhanced_create_post_url);
        wp_enqueue_style(
            'smo-social-enhanced-create-post',
            $enhanced_create_post_url,
            array('smo-social-admin'),
            $this->get_css_version('smo-enhanced-create-post.css')
        );
        
        // Also enqueue enhanced create post JS
        wp_enqueue_script(
            'smo-enhanced-create-post',
            $this->plugin_url . 'assets/js/smo-enhanced-create-post.js',
            array('jquery', 'jquery-validate', 'wp-api'),
            SMO_SOCIAL_VERSION,
            true
        );
    }

    /**
     * Enqueue page-specific assets (legacy support)
     */
    private function enqueue_page_specific_assets($hook) {
        // Platforms CSS (if on platforms page)
        if (strpos($hook, 'smo-social-platforms') !== false) {
            $platforms_css_url = $this->plugin_url . 'assets/css/platforms.css';
            Logger::debug('Enqueuing platforms CSS: ' . $platforms_css_url);
            wp_enqueue_style(
                'smo-social-platforms',
                $platforms_css_url,
                array('smo-social-admin'),
                $this->get_css_version('platforms.css')
            );
        }

        // Settings CSS (if on settings page)
        if (strpos($hook, 'smo-social-settings') !== false) {
            $settings_css_url = $this->plugin_url . 'assets/css/smo-settings-modern.css';
            Logger::debug('Enqueuing settings CSS: ' . $settings_css_url);
            wp_enqueue_style(
                'smo-settings-modern',
                $settings_css_url,
                array('smo-social-admin'),
                $this->get_css_version('smo-settings-modern.css')
            );
        }

        // Integrations CSS/JS (if on integrations page)
        if (strpos($hook, 'smo-social-integrations') !== false) {
            // CSS
            wp_enqueue_style(
                'smo-integrations',
                $this->plugin_url . 'assets/css/smo-integrations.css',
                array('smo-social-admin'),
                $this->get_css_version('smo-integrations.css')
            );
            
            // JS
            wp_enqueue_script(
                'smo-integrations',
                $this->plugin_url . 'assets/js/smo-integrations.js',
                array('jquery', 'wp-util', 'smo-social-admin'),
                SMO_SOCIAL_VERSION,
                true
            );
            
            wp_localize_script('smo-integrations', 'smoIntegrations', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('smo_integrations'),
                'integrations' => $this->integration_manager ? $this->integration_manager->get_all_integrations() : []
            ));
        }
        
        // For platforms page, ensure scripts are properly loaded
        if (strpos($hook, 'smo-social-platforms') !== false) {
            // Ensure wp-i18n is loaded for translations
            wp_enqueue_script('wp-i18n');
        }
    }

    /**
     * Get CSS version for cache busting
     * Uses filemtime for automatic cache busting when files are modified
     * Falls back to plugin version for core files
     *
     * @param string $css_file The CSS filename
     * @return string The version string
     */
    private function get_css_version($css_file) {
        $css_path = $this->plugin_url . 'assets/css/' . $css_file;
        $local_path = plugin_dir_path(__FILE__) . '../../assets/css/' . $css_file;
        
        // Try to use filemtime for automatic cache busting
        if (file_exists($local_path)) {
            $file_time = filemtime($local_path);
            if ($file_time !== false) {
                return $file_time;
            }
        }
        
        // Fallback to plugin version with increment for migrated files
        $base_version = SMO_SOCIAL_VERSION;
        
        // Increment version for files that were modified in this migration
        $migrated_files = array(
            'smo-unified-design-system.css',
            'smo-api-management.css',
            'smo-monitoring-interfaces.css',
            'dashboard-widgets.css',
            'smo-media-library.css',
            'smo-enhanced-create-post.css'
        );
        
        if (in_array($css_file, $migrated_files)) {
            return $base_version . '.1'; // Increment for migrated files
        }
        
        return $base_version;
    }
}
