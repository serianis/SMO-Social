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
        // Priority is intentionally early so the foundation styles are enqueued before feature-specific CSS.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 5);
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

        // CSS
        // Foundations (tokens + primitives) must load before everything else.
        $foundations_url = $this->plugin_url . 'assets/css/smo-design-foundations.css';
        Logger::debug('Enqueuing design foundations CSS: ' . $foundations_url);
        wp_enqueue_style(
            'smo-social-design-foundations',
            $foundations_url,
            array(),
            SMO_SOCIAL_VERSION
        );

        // Unified design system (components)
        $design_system_url = $this->plugin_url . 'assets/css/smo-unified-design-system.css';
        Logger::debug('Enqueuing unified design system CSS: ' . $design_system_url);
        wp_enqueue_style(
            'smo-social-unified-design-system',
            $design_system_url,
            array('smo-social-design-foundations'),
            SMO_SOCIAL_VERSION
        );

        // Unified tabs/navigation
        $unified_tabs_url = $this->plugin_url . 'assets/css/smo-unified-tabs.css';
        Logger::debug('Enqueuing unified tabs CSS: ' . $unified_tabs_url);
        wp_enqueue_style(
            'smo-social-unified-tabs',
            $unified_tabs_url,
            array('smo-social-unified-design-system'),
            SMO_SOCIAL_VERSION
        );

        // Styles migrated from inline <style> blocks inside views/widgets
        // (legacy extraction kept separate so curated component styles can override it cleanly).
        $inline_migrated_url = $this->plugin_url . 'assets/css/smo-admin-inline-migrated.css';
        Logger::debug('Enqueuing migrated inline CSS: ' . $inline_migrated_url);
        wp_enqueue_style(
            'smo-social-admin-inline-migrated',
            $inline_migrated_url,
            array('smo-social-unified-design-system'),
            SMO_SOCIAL_VERSION
        );

        $admin_components_url = $this->plugin_url . 'assets/css/smo-admin-components.css';
        Logger::debug('Enqueuing admin components CSS: ' . $admin_components_url);
        wp_enqueue_style(
            'smo-social-admin-components',
            $admin_components_url,
            array('smo-social-admin-inline-migrated'),
            SMO_SOCIAL_VERSION
        );

        // Then admin CSS (additional/legacy styles)
        $admin_css_url = $this->plugin_url . 'assets/css/admin.css';
        Logger::debug('Enqueuing admin CSS: ' . $admin_css_url);
        wp_enqueue_style(
            'smo-social-admin',
            $admin_css_url,
            array('smo-social-unified-tabs', 'smo-social-admin-components'),
            SMO_SOCIAL_VERSION
        );

        // Dashboard CSS (if on main dashboard page)
        if ($hook === 'toplevel_page_smo-social') {
            $dashboard_css_url = $this->plugin_url . 'assets/css/dashboard-redesign.css';
            Logger::debug('Enqueuing dashboard CSS: ' . $dashboard_css_url);
            wp_enqueue_style(
                'smo-social-dashboard-redesign',
                $dashboard_css_url,
                array('smo-social-admin'),
                SMO_SOCIAL_VERSION
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

        // Platforms CSS (if on platforms page)
        if (strpos($hook, 'smo-social-platforms') !== false) {
            $platforms_css_url = $this->plugin_url . 'assets/css/platforms.css';
            Logger::debug('Enqueuing platforms CSS: ' . $platforms_css_url);
            wp_enqueue_style(
                'smo-social-platforms',
                $platforms_css_url,
                array('smo-social-admin'),
                SMO_SOCIAL_VERSION
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
                SMO_SOCIAL_VERSION
            );
        }

        // Integrations CSS/JS (if on integrations page)
        if (strpos($hook, 'smo-social-integrations') !== false) {
            // CSS
            wp_enqueue_style(
                'smo-integrations',
                $this->plugin_url . 'assets/css/smo-integrations.css',
                array('smo-social-admin'),
                SMO_SOCIAL_VERSION
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
}
