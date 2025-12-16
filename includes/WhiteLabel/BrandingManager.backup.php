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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin interface hooks
        \add_action('admin_menu', array($this, 'add_branding_menu'), 99);
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
     * Add branding menu to admin
     */
    public function add_branding_menu() {
        $custom_label = $this->get_setting('custom_menu_label', 'Social Media');
        $custom_icon = $this->get_setting('custom_menu_icon', 'dashicons-share');
        
        \add_menu_page(
            \__($custom_label . ' Branding', 'smo-social'),
            \__($custom_label, 'smo-social'),
            'manage_options',
            'smo-branding',
            array($this, 'branding_settings_page'),
            $custom_icon,
            30
        );
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

                <!-- Branding Settings -->
                <div id="branding" class="tab-content">
                    <h2><?php _e('Logo & Branding', 'smo-social'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="logo_upload"><?php _e('Logo Upload', 'smo-social'); ?></label></th>
                            <td>
                                <div class="logo-upload-container">
                                    <?php if ($settings['logo_url']): ?>
                                        <div class="current-logo">
                                            <img src="<?php echo \esc_url($settings['logo_url']); ?>" style="max-width: 200px; height: auto;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" id="logo_upload" name="logo_upload" accept="image/*">
                                    <input type="hidden" id="logo_url" name="logo_url" value="<?php echo \esc_attr($settings['logo_url']); ?>">
                                    <p class="description"><?php _e('Upload your logo (PNG, JPG, SVG recommended)', 'smo-social'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="logo_width"><?php _e('Logo Dimensions', 'smo-social'); ?></label></th>
                            <td>
                                <input type="number" id="logo_width" name="logo_width" value="<?php echo \esc_attr($settings['logo_width']); ?>" min="50" max="500" style="width: 80px;"> px × 
                                <input type="number" id="logo_height" name="logo_height" value="<?php echo \esc_attr($settings['logo_height']); ?>" min="30" max="300" style="width: 80px;"> px
                                <p class="description"><?php _e('Maximum dimensions for your logo', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="hide_smo_branding"><?php _e('Hide SMO Social Branding', 'smo-social'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="hide_smo_branding" name="hide_smo_branding" value="1" <?php \checked($settings['hide_smo_branding']); ?>>
                                    <?php _e('Remove all SMO Social references and branding', 'smo-social'); ?>
                                </label>
                                <p class="description"><?php _e('When enabled, SMO Social branding will be replaced with your custom branding', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="footer_text"><?php _e('Footer Text', 'smo-social'); ?></label></th>
                            <td>
                                <input type="text" id="footer_text" name="footer_text" value="<?php echo \esc_attr($settings['footer_text']); ?>" class="large-text">
                                <p class="description"><?php _e('Text shown in the admin footer', 'smo-social'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Color Settings -->
                <div id="colors" class="tab-content">
                    <h2><?php _e('Color Scheme', 'smo-social'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="primary_color"><?php _e('Primary Color', 'smo-social'); ?></label></th>
                            <td>
                                <input type="color" id="primary_color" name="primary_color" value="<?php echo \esc_attr($settings['primary_color']); ?>" class="color-picker">
                                <label for="primary_color_text" style="display: none;">Primary Color Text</label>
                                <input type="text" id="primary_color_text" value="<?php echo \esc_attr($settings['primary_color']); ?>" class="small-text">
                                <p class="description"><?php _e('Main accent color for buttons and highlights', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="secondary_color"><?php _e('Secondary Color', 'smo-social'); ?></label></th>
                            <td>
                                <input type="color" id="secondary_color" name="secondary_color" value="<?php echo \esc_attr($settings['secondary_color']); ?>" class="color-picker">
                                <label for="secondary_color_text" style="display: none;">Secondary Color Text</label>
                                <input type="text" id="secondary_color_text" value="<?php echo \esc_attr($settings['secondary_color']); ?>" class="small-text">
                                <p class="description"><?php _e('Secondary color for hovers and secondary elements', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="accent_color"><?php _e('Accent Color', 'smo-social'); ?></label></th>
                            <td>
                                <input type="color" id="accent_color" name="accent_color" value="<?php echo \esc_attr($settings['accent_color']); ?>" class="color-picker">
                                <label for="accent_color_text" style="display: none;">Accent Color Text</label>
                                <input type="text" id="accent_color_text" value="<?php echo \esc_attr($settings['accent_color']); ?>" class="small-text">
                                <p class="description"><?php _e('Accent color for links and interactive elements', 'smo-social'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div class="color-preview">
                        <h3><?php _e('Live Preview', 'smo-social'); ?></h3>
                        <div id="color-preview-container" class="color-preview-container">
                            <div class="preview-button"><?php _e('Primary Button', 'smo-social'); ?></div>
                            <div class="preview-link"><a href="#"><?php _e('Sample Link', 'smo-social'); ?></a></div>
                            <div class="preview-header"><?php _e('Sample Header', 'smo-social'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Menu Settings -->
                <div id="menu" class="tab-content">
                    <h2><?php _e('Menu & Interface Customization', 'smo-social'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_menu_label"><?php _e('Custom Menu Label', 'smo-social'); ?></label></th>
                            <td>
                                <input type="text" id="custom_menu_label" name="custom_menu_label" value="<?php echo \esc_attr($settings['custom_menu_label']); ?>" class="regular-text">
                                <p class="description"><?php _e('Text shown in the admin menu', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="custom_menu_icon"><?php _e('Menu Icon', 'smo-social'); ?></label></th>
                            <td>
                                <input type="text" id="custom_menu_icon" name="custom_menu_icon" value="<?php echo \esc_attr($settings['custom_menu_icon']); ?>" class="regular-text">
                                <p class="description"><?php _e('Dashicons class name (e.g., dashicons-share, dashicons-megaphone)', 'smo-social'); ?></p>
                                <div class="icon-grid">
                                    <?php 
                                    $common_icons = array('dashicons-share', 'dashicons-megaphone', 'dashicons-groups', 'dashicons-admin-users', 'dashicons-networking', 'dashicons-share-alt');
                                    foreach ($common_icons as $icon): ?>
                                        <span class="dashicons <?php echo $icon; ?> icon-option <?php echo $settings['custom_menu_icon'] === $icon ? 'selected' : ''; ?>" data-icon="<?php echo $icon; ?>"></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- License Settings -->
                <div id="license" class="tab-content">
                    <h2><?php _e('License & Support', 'smo-social'); ?></h2>
                    <div class="license-status">
                        <h3><?php _e('License Information', 'smo-social'); ?></h3>
                        <div class="license-info">
                            <p><strong><?php _e('License Type:', 'smo-social'); ?></strong> <?php echo \esc_html(ucfirst($license_info['type'] ?? 'Free')); ?></p>
                            <p><strong><?php _e('Status:', 'smo-social'); ?></strong> 
                                <span class="license-status-badge status-<?php echo \esc_attr($license_info['status'] ?? 'free'); ?>">
                                    <?php echo \esc_html(ucfirst($license_info['status'] ?? 'Free')); ?>
                                </span>
                            </p>
                            <?php if (!empty($license_info['expires_at'])): ?>
                                <p><strong><?php _e('Expires:', 'smo-social'); ?></strong> <?php echo \esc_html(date('F j, Y', strtotime($license_info['expires_at']))); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th><label for="license_key"><?php _e('License Key', 'smo-social'); ?></label></th>
                            <td>
                                <input type="text" id="license_key" name="license_key" value="<?php echo \esc_attr($settings['license_key']); ?>" class="regular-text">
                                <button type="button" id="activate_license" class="button"><?php _e('Activate License', 'smo-social'); ?></button>
                                <button type="button" id="check_license" class="button"><?php _e('Check Status', 'smo-social'); ?></button>
                                <p class="description"><?php _e('Enter your license key for white-label features', 'smo-social'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Advanced Settings -->
                <div id="advanced" class="tab-content">
                    <h2><?php _e('Advanced Customization', 'smo-social'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_css"><?php _e('Custom CSS', 'smo-social'); ?></label></th>
                            <td>
                                <textarea id="custom_css" name="custom_css" rows="10" class="large-text code"><?php echo \esc_textarea($settings['custom_css']); ?></textarea>
                                <p class="description"><?php _e('Custom CSS to further customize the appearance', 'smo-social'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="white_label_mode"><?php _e('Enable White Label Mode', 'smo-social'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="white_label_mode" name="white_label_mode" value="1" <?php \checked($settings['white_label_mode']); ?>>
                                    <?php _e('Enable complete white-label mode', 'smo-social'); ?>
                                </label>
                                <p class="description"><?php _e('When enabled, all SMO Social references will be hidden', 'smo-social'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Save Changes', 'smo-social'); ?>">
                    <button type="button" id="preview_branding" class="button"><?php _e('Preview Changes', 'smo-social'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue branding scripts and styles
     */
    public function enqueue_branding_scripts($hook) {
        if (strpos($hook, 'smo-branding') === false) {
            return;
        }

        \wp_enqueue_media();
        \wp_enqueue_script('wp-color-picker');
        \wp_enqueue_style('wp-color-picker');
        
        \wp_enqueue_script('smo-branding-admin', \SMO_SOCIAL_PLUGIN_URL . 'assets/js/branding-admin.js', array('jquery', 'wp-color-picker'), \SMO_SOCIAL_VERSION, true);
        \wp_enqueue_style('smo-branding-admin', \SMO_SOCIAL_PLUGIN_URL . 'assets/css/branding-admin.css', array(), \SMO_SOCIAL_VERSION);

        \wp_localize_script('smo-branding-admin', 'smoBranding', array(
            'ajaxurl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('smo_branding_nonce'),
            'uploadNonce' => \wp_create_nonce('smo_media_upload'),
            'settings' => $this->get_all_settings()
        ));
    }

    /**
     * Handle branding form submission
     */
    public function handle_branding_actions() {
        if (!isset($_POST['submit']) || !isset($_POST['branding_nonce'])) {
            return;
        }

        if (!\wp_verify_nonce($_POST['branding_nonce'], 'smo_branding_nonce') || !\current_user_can('manage_options')) {
            \wp_die('Security check failed');
        }

        // Save all settings
        foreach ($this->default_settings as $key => $default_value) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                
                // Sanitize based on field type
                switch ($key) {
                    case 'plugin_name':
                    case 'company_name':
                    case 'custom_menu_label':
                        $value = \sanitize_text_field($value);
                        break;
                    case 'company_url':
                    case 'support_url':
                        $value = \esc_url_raw($value);
                        break;
                    case 'plugin_description':
                    case 'custom_css':
                        $value = \sanitize_textarea_field($value);
                        break;
                    case 'hide_smo_branding':
                    case 'white_label_mode':
                        $value = isset($_POST[$key]) ? 1 : 0;
                        break;
                    default:
                        $value = \sanitize_text_field($value);
                }
                
                $this->set_setting($key, $value);
            }
        }

        \add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . \__('Branding settings saved successfully!', 'smo-social') . '</p></div>';
        });
    }

    /**
     * Inject custom branding into admin head
     */
    public function inject_custom_branding() {
        $settings = $this->get_all_settings();
        
        // Skip if white label mode is disabled
        if (!$settings['white_label_mode'] && !$settings['hide_smo_branding']) {
            return;
        }

        ?>
        <style type="text/css">
        :root {
            --smo-primary-color: <?php echo \esc_attr($settings['primary_color']); ?>;
            --smo-secondary-color: <?php echo \esc_attr($settings['secondary_color']); ?>;
            --smo-accent-color: <?php echo \esc_attr($settings['accent_color']); ?>;
        }
        
        .smo-logo {
            max-width: <?php echo \esc_attr($settings['logo_width']); ?>px;
            height: <?php echo \esc_attr($settings['logo_height']); ?>px;
        }
        </style>
        <?php
    }

    /**
     * Inject custom branding into login page
     */
    public function inject_login_branding() {
        $settings = $this->get_all_settings();
        
        if (!$settings['white_label_mode'] && !$settings['hide_smo_branding']) {
            return;
        }

        $logo_url = $settings['logo_url'];
        $custom_css = $settings['custom_css'];
        
        ?>
        <style type="text/css">
        #login h1 a {
            background-image: url('<?php echo \esc_url($logo_url); ?>');
            background-size: contain;
            width: 200px;
            height: 60px;
        }
        <?php if (!empty($custom_css)): ?>
        <?php echo $custom_css; ?>
        <?php endif; ?>
        </style>
        <?php
    }

    /**
     * Inject custom CSS into admin footer
     */
    public function inject_custom_css() {
        $settings = $this->get_all_settings();
        $custom_css = $settings['custom_css'];
        
        if (!empty($custom_css)) {
            echo '<style type="text/css">' . $custom_css . '</style>';
        }
    }

    /**
     * AJAX: Save branding settings
     */
    public function ajax_save_branding() {
        \check_ajax_referer('smo_branding_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        $settings = $_POST['settings'] ?? array();
        
        foreach ($settings as $key => $value) {
            if (array_key_exists($key, $this->default_settings)) {
                $this->set_setting($key, $value);
            }
        }

        \wp_send_json_success('Branding settings saved');
    }

    /**
     * AJAX: Upload logo
     */
    public function ajax_upload_logo() {
        \check_ajax_referer('smo_media_upload', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        if (!function_exists('wp_handle_upload')) {
            require_once(\ABSPATH . 'wp-admin/includes/file.php');
        }

        $uploadedfile = $_FILES['logo'];
        $upload_overrides = array('test_form' => false);
        $movefile = \wp_handle_upload($uploadedfile, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            \wp_send_json_success(array(
                'url' => $movefile['url'],
                'file' => $movefile['file']
            ));
        } else {
            \wp_send_json_error($movefile['error'] ?? 'Upload failed');
        }
    }

    /**
     * AJAX: Preview color scheme
     */
    public function ajax_preview_color_scheme() {
        \check_ajax_referer('smo_branding_nonce', 'nonce');

        $colors = $_POST['colors'] ?? array();
        
        echo '<style>';
        echo '.preview-button { background-color: ' . \esc_attr($colors['primary'] ?? '#0073aa') . '; }';
        echo '.preview-link a { color: ' . \esc_attr($colors['accent'] ?? '#00a0d2') . '; }';
        echo '.preview-header { background-color: ' . \esc_attr($colors['secondary'] ?? '#005177') . '; }';
        echo '</style>';
        
        \wp_die();
    }

    /**
     * AJAX: Activate license
     */
    public function ajax_activate_license() {
        \check_ajax_referer('smo_branding_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        $license_key = \sanitize_text_field($_POST['license_key'] ?? '');
        
        if (empty($license_key)) {
            \wp_send_json_error('License key is required');
        }

        // Simulate license activation (in real implementation, would validate with license server)
        $activation_result = $this->activate_license_key($license_key);
        
        if ($activation_result['success']) {
            $this->set_setting('license_key', $license_key);
            $this->set_setting('license_type', $activation_result['type']);
            $this->set_setting('license_status', 'active');
            
            \wp_send_json_success($activation_result);
        } else {
            \wp_send_json_error($activation_result['message']);
        }
    }

    /**
     * AJAX: Check license
     */
    public function ajax_check_license() {
        \check_ajax_referer('smo_branding_nonce', 'nonce');

        if (!\current_user_can('manage_options')) {
            \wp_send_json_error('Insufficient permissions');
        }

        $license_key = $this->get_setting('license_key');
        
        if (empty($license_key)) {
            \wp_send_json_error('No license key found');
        }

        $check_result = $this->check_license_status($license_key);
        \wp_send_json_success($check_result);
    }

    /**
     * Helper methods
     */
    
    private function activate_license_key($license_key) {
        // This would integrate with a real license server
        // For now, simulate different license types
        
        if (strpos($license_key, 'WL-') === 0) {
            return array(
                'success' => true,
                'type' => 'white_label',
                'message' => 'White-label license activated'
            );
        } elseif (strpos($license_key, 'PRO-') === 0) {
            return array(
                'success' => true,
                'type' => 'professional',
                'message' => 'Professional license activated'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Invalid license key'
            );
        }
    }

    private function check_license_status($license_key) {
        // Simulate license status check
        return array(
            'status' => 'active',
            'type' => $this->get_setting('license_type', 'free'),
            'expires_at' => null,
            'domain' => $_SERVER['HTTP_HOST']
        );
    }

    private function get_license_info() {
        return array(
            'key' => $this->get_setting('license_key'),
            'type' => $this->get_setting('license_type', 'free'),
            'status' => $this->get_setting('license_status', 'free'),
            'expires_at' => $this->get_setting('license_expires')
        );
    }

    /**
     * Customize admin page titles
     */
    public function customize_admin_titles($title) {
        $settings = $this->get_all_settings();
        $plugin_name = $settings['plugin_name'];
        
        if (strpos($title, 'SMO Social') !== false) {
            $title = str_replace('SMO Social', $plugin_name, $title);
        }
        
        return $title;
    }

    /**
     * Customize plugin action links
     */
    public function customize_plugin_action_links($links) {
        $settings = $this->get_all_settings();
        $plugin_name = $settings['plugin_name'];
        
        $custom_links = array();
        foreach ($links as $link) {
            $custom_links[] = str_replace('SMO Social', $plugin_name, $link);
        }
        
        return $custom_links;
    }
}
