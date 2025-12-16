<?php
namespace SMO_Social\WhiteLabel;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_BrandingManager - White-label Capabilities System
 * 
 * Handles complete customization of SMO Social branding,
 * logos, colors, and licensing for white-label deployment.
 * 
 * DEBUG VERSION - Enhanced logging for menu issues
 */
class BrandingManager {

    private $wpdb;
    private $settings_table;
    private $license_table;
    private $cache_duration = 3600; // 1 hour

    // Default branding settings
    private $default_settings = array(
        'plugin_name' => 'SMO Social',
        'plugin_description' => 'A comprehensive social media management plugin',
        'company_name' => '',
        'company_url' => '',
        'support_url' => '',
        'logo_url' => '',
        'logo_width' => 200,
        'logo_height' => 60,
        'primary_color' => '#0073aa',
        'secondary_color' => '#005177',
        'accent_color' => '#00a0d2',
        'admin_color_scheme' => 'default',
        'custom_css' => '',
        'hide_smo_branding' => false,
        'custom_menu_label' => 'Social Media',
        'custom_menu_icon' => 'dashicons-share',
        'welcome_message' => 'Welcome to your social media management dashboard',
        'footer_text' => 'Powered by SMO Social',
        'custom_css_classes' => array(),
        'license_key' => '',
        'license_type' => 'free',
        'license_status' => 'valid',
        'white_label_mode' => false
    );

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->settings_table = $wpdb->prefix . 'smo_branding_settings';
        $this->license_table = $wpdb->prefix . 'smo_licenses';
        
        // DEBUG: Log BrandingManager initialization
        error_log('SMO DEBUG: BrandingManager::__construct() called');
        error_log('SMO DEBUG: Database tables - settings: ' . $this->settings_table . ', license: ' . $this->license_table);
        
        $this->init_hooks();
        
        // DEBUG: Verify hooks are registered
        $this->debug_verify_hooks();
    }

    /**
     * DEBUG: Verify that hooks are properly registered
     */
    private function debug_verify_hooks() {
        if (function_exists('has_action')) {
            $admin_menu_priority = has_action('admin_menu', array($this, 'add_branding_menu'));
            error_log('SMO DEBUG: admin_menu hook registered with priority: ' . ($admin_menu_priority ? $admin_menu_priority : 'NOT REGISTERED'));
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        error_log('SMO DEBUG: BrandingManager::init_hooks() called');
        
        // Admin interface hooks
        \add_action('admin_menu', array($this, 'add_branding_menu'), 99);
        error_log('SMO DEBUG: admin_menu hook added with priority 99');
        
        \add_action('admin_enqueue_scripts', array($this, 'enqueue_branding_scripts'));
        \add_action('admin_init', array($this, 'handle_branding_actions'));

        // Customization hooks
        \add_action('admin_head', array($this, 'inject_custom_branding'));
        \add_action('login_head', array($this, 'inject_login_branding'));
        \add_action('admin_footer', array($this, 'inject_custom_css'));
        \add_filter('admin_page_title', array($this, 'customize_admin_titles'));
        \add_filter('plugin_action_links_' . \SMO_SOCIAL_PLUGIN_BASENAME, array($this, 'customize_plugin_action_links'));
        
        // AJAX handlers
        \add_action('wp_ajax_smo_save_branding', array($this, 'ajax_save_branding'));
        \add_action('wp_ajax_smo_upload_logo', array($this, 'ajax_upload_logo'));
        \add_action('wp_ajax_smo_preview_color_scheme', array($this, 'ajax_preview_color_scheme'));
        \add_action('wp_ajax_smo_activate_license', array($this, 'ajax_activate_license'));
        \add_action('wp_ajax_smo_check_license', array($this, 'ajax_check_license'));

        // Plugin initialization
        \add_action('init', array($this, 'ensure_branding_tables'));
        
        error_log('SMO DEBUG: All hooks initialized successfully');
    }

    /**
     * Add branding menu to admin
     * DEBUG VERSION with enhanced logging
     */
    public function add_branding_menu() {
        error_log('SMO DEBUG: BrandingManager::add_branding_menu() called');
        
        // Check user permissions
        if (!\current_user_can('manage_options')) {
            error_log('SMO DEBUG: User does not have manage_options capability - skipping menu registration');
            return;
        }
        
        error_log('SMO DEBUG: User has manage_options capability - proceeding with menu registration');

        $custom_label = $this->get_setting('custom_menu_label', 'Social Media');
        $custom_icon = $this->get_setting('custom_menu_icon', 'dashicons-share');
        
        error_log('SMO DEBUG: Menu settings - label: ' . $custom_label . ', icon: ' . $custom_icon);
        
        // DEBUG: Check if menu already exists
        global $submenu;
        if (isset($submenu['smo-branding'])) {
            error_log('SMO DEBUG: Menu sno-branding already exists in $submenu');
        }
        
        // Add the menu page
        $result = \add_menu_page(
            \__($custom_label . ' Branding', 'smo-social'),
            \__($custom_label, 'smo-social'),
            'manage_options',
            'smo-branding',
            array($this, 'branding_settings_page'),
            $custom_icon,
            30
        );
        
        error_log('SMO DEBUG: add_menu_page result: ' . ($result ? 'SUCCESS - ' . $result : 'FAILED'));
        
        // DEBUG: Check global submenu after registration
        global $submenu;
        if (isset($submenu['smo-branding'])) {
            error_log('SMO DEBUG: Menu successfully added to $submenu[\'smo-branding\']');
            error_log('SMO DEBUG: Submenu contents: ' . print_r($submenu['smo-branding'], true));
        } else {
            error_log('SMO DEBUG: Menu NOT found in $submenu after registration');
        }
        
        // Store registration flag
        update_option('smo_branding_menu_registered', true);
        error_log('SMO DEBUG: Branding menu registration flag set');
    }

    /**
     * Ensure branding tables exist
     */
    public function ensure_branding_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Branding settings table
        $settings_table_sql = "CREATE TABLE IF NOT EXISTS {$this->settings_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext DEFAULT '',
            setting_type varchar(20) NOT NULL DEFAULT 'string',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        // License table
        $license_table_sql = "CREATE TABLE IF NOT EXISTS {$this->license_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            license_key varchar(255) NOT NULL,
            license_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            domain varchar(255) NOT NULL,
            expires_at datetime DEFAULT NULL,
            activated_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_checked datetime DEFAULT CURRENT_TIMESTAMP,
            metadata longtext DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY domain_status (domain, status)
        ) $charset_collate;";

        require_once(\ABSPATH . 'wp-admin/includes/upgrade.php');
        \dbDelta($settings_table_sql);
        \dbDelta($license_table_sql);

        // Initialize default settings if not exist
        $this->initialize_default_settings();
    }

    /**
     * Initialize default settings
     */
    private function initialize_default_settings() {
        foreach ($this->default_settings as $key => $value) {
            $existing = $this->get_setting($key);
            if ($existing === null) {
                $this->set_setting($key, $value);
            }
        }
    }

    /**
     * Get branding setting
     */
    public function get_setting($key, $default = null) {
        $cached = \wp_cache_get('smo_branding_' . $key, 'smo_social');
        if ($cached !== false) {
            return $cached;
        }

        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_value, setting_type FROM {$this->settings_table} WHERE setting_key = %s",
            $key
        ));

        if ($result === null) {
            return $default ?? $this->default_settings[$key] ?? null;
        }

        // Decode based on type
        $value = $result;
        $stored_type = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_type FROM {$this->settings_table} WHERE setting_key = %s",
            $key
        ));

        switch ($stored_type) {
            case 'json':
                $value = json_decode($result, true);
                break;
            case 'boolean':
                $value = (bool) $result;
                break;
            case 'integer':
                $value = (int) $result;
                break;
        }

        \wp_cache_set('smo_branding_' . $key, $value, 'smo_social', $this->cache_duration);
        return $value;
    }

    /**
     * Set branding setting
     */
    public function set_setting($key, $value, $type = null) {
        // Determine type if not specified
        if ($type === null) {
            if (is_array($value)) {
                $type = 'json';
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $type = 'boolean';
                $value = $value ? '1' : '0';
            } elseif (is_int($value)) {
                $type = 'integer';
            } else {
                $type = 'string';
            }
        }

        // Insert or update
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->settings_table} WHERE setting_key = %s",
            $key
        ));

        if ($existing) {
            $result = $this->wpdb->update(
                $this->settings_table,
                array(
                    'setting_value' => $value,
                    'setting_type' => $type
                ),
                array('setting_key' => $key),
                array('%s', '%s'),
                array('%s')
            );
        } else {
            $result = $this->wpdb->insert(
                $this->settings_table,
                array(
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_type' => $type
                ),
                array('%s', '%s', '%s')
            );
        }

        if ($result !== false) {
            // Clear cache
            \wp_cache_delete('smo_branding_' . $key, 'smo_social');
            
            // Fire action for external integrations
            \do_action('smo_branding_setting_updated', $key, $value);
        }

        return $result !== false;
    }

    /**
     * Get all branding settings
     */
    public function get_all_settings() {
        $settings = array();
        $results = $this->wpdb->get_results(
            "SELECT setting_key, setting_value, setting_type FROM {$this->settings_table}"
        );

        foreach ($results as $result) {
            $value = $result->setting_value;
            
            switch ($result->setting_type) {
                case 'json':
                    $value = json_decode($value, true);
                    break;
                case 'boolean':
                    $value = (bool) $value;
                    break;
                case 'integer':
                    $value = (int) $value;
                    break;
            }

            $settings[$result->setting_key] = $value;
        }

        // Fill in defaults for missing settings
        foreach ($this->default_settings as $key => $default_value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $default_value;
            }
        }

        return $settings;
    }

    /**
     * Branding settings admin page
     */
    public function branding_settings_page() {
        if (!\current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_all_settings();
        $license_info = $this->get_license_info();
        
        ?>
        <div class="wrap smo-branding-settings">
            <h1><?php echo \esc_html($settings['plugin_name']); ?> - White Label Settings</h1>
            
            <div class="notice notice-info">
                <p><strong>DEBUG INFO:</strong> BrandingManager is working correctly! This page was loaded via the admin_menu hook.</p>
            </div>
            
            <form id="smo-branding-form" method="post" enctype="multipart/form-data">
                <?php \wp_nonce_field('smo_branding_nonce', 'branding_nonce'); ?>
                
                <!-- Tab Navigation -->
                <nav class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'smo-social'); ?></a>
                    <a href="#branding" class="nav-tab"><?php _e('Branding', 'smo-social'); ?></a>
                    <a href="#colors" class="nav-tab"><?php _e('Colors & Styles', 'smo-social'); ?></a>
                    <a href="#menu" class="nav-tab"><?php _e('Menu & Interface', 'smo-social'); ?></a>
                    <a href="#license" class="nav-tab"><?php _e('License & Support', 'smo-social'); ?></a>
                    <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'smo-social'); ?></a>
                </nav>

                <!-- General Settings -->
                <div id="general" class="tab-content active">
                    <h2><?php _e('General Settings', 'smo-social'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="plugin_name"><?php _e('Plugin Name', 'smo-social'); ?></label></th>
                            <td>
                                <input type="text" id="plugin_name" name="plugin_name" value="<?php echo \esc_attr($settings['plugin_name']); ?>" class="regular-text">
                                <p class="description"><?php _e('Name displayed in admin and public areas', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="plugin_description"><?php _e('Plugin Description', 'smo-social'); ?></label></th>
                            <td>
                                <textarea id="plugin_description" name="plugin_description" rows="3" class="large-text"><?php echo \esc_textarea($settings['plugin_description']); ?></textarea>
                                <p class="description"><?php _e('Description shown in plugin listings', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="company_name"><?php _e('Company/Brand Name', 'smo-social'); ?></label></th>
                            <td>
                                <input type="text" id="company_name" name="company_name" value="<?php echo \esc_attr($settings['company_name']); ?>" class="regular-text">
                                <p class="description"><?php _e('Your company or brand name', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="company_url"><?php _e('Company URL', 'smo-social'); ?></label></th>
                            <td>
                                <input type="url" id="company_url" name="company_url" value="<?php echo \esc_url($settings['company_url']); ?>" class="regular-text">
                                <p class="description"><?php _e('URL to your company website', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="support_url"><?php _e('Support URL', 'smo-social'); ?></label></th>
                            <td>
                                <input type="url" id="support_url" name="support_url" value="<?php echo \esc_url($settings['support_url']); ?>" class="regular-text">
                                <p class="description"><?php _e('URL for support/help documentation', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="welcome_message"><?php _e('Welcome Message', 'smo-social'); ?></label></th>
                            <td>
                                <textarea id="welcome_message" name="welcome_message" rows="2" class="large-text"><?php echo \esc_textarea($settings['welcome_message']); ?></textarea>
                                <p class="description"><?php _e('Message shown on the main dashboard', 'smo-social'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Placeholder for other tabs -->
                <div id="branding" class="tab-content" style="display:none;">
                    <h2><?php _e('Logo & Branding', 'smo-social'); ?></h2>
                    <p>Branding settings would go here...</p>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Save Changes', 'smo-social'); ?>">
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Simple tab functionality
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
        });
        </script>
        <?php
    }

    // Stub methods for remaining functionality
    public function enqueue_branding_scripts($hook) {}
    public function handle_branding_actions() {}
    public function inject_custom_branding() {}
    public function inject_login_branding() {}
    public function inject_custom_css() {}
    public function customize_admin_titles($title) { return $title; }
    public function customize_plugin_action_links($links) { return $links; }
    public function ajax_save_branding() {}
    public function ajax_upload_logo() {}
    public function ajax_preview_color_scheme() {}
    public function ajax_activate_license() {}
    public function ajax_check_license() {}
    
    private function get_license_info() {
        return array(
            'key' => $this->get_setting('license_key'),
            'type' => $this->get_setting('license_type', 'free'),
            'status' => $this->get_setting('license_status', 'free'),
            'expires_at' => $this->get_setting('license_expires')
        );
    }
}