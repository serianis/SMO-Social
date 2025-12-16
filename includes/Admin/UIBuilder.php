<?php
namespace SMO_Social\Admin;

if (!defined('ABSPATH')) {
    exit; // Security check
}

// Import security components
require_once __DIR__ . '/../Security/InputValidator.php';
require_once __DIR__ . '/../Security/CSRFManager.php';

/**
 * SMO_UI_Builder - Universal UI Builder for Dynamic Platform Settings
 * 
 * This class automatically generates admin interface forms based on JSON driver
 * configurations. It creates platform-specific settings without hardcoded HTML.
 */
class UIBuilder {

    private $options_slug = 'smo_social_credentials';
    private $platform_slug = '';
    private $driver_config = array();
    private $saved_values = array();

    /**
     * Render platform settings interface
     * 
     * @param array $driver_json Decoded JSON driver configuration
     */
    public function render_platform_settings($driver_json) {
        $this->driver_config = $driver_json;
        $this->platform_slug = $driver_json['slug'];
        
        // Load saved values
        $this->load_saved_values();
        
        // Start rendering
        echo '<div class="smo-platform-card">';
        $this->render_platform_header();
        $this->render_platform_form();
        echo '</div>';
    }

    /**
     * Render individual platform card in overview
     * 
     * @param array $driver_json Driver configuration
     * @param bool  $enabled     Whether platform is enabled
     */
    public function render_platform_card($driver_json, $enabled = false) {
        $this->driver_config = $driver_json;
        $this->platform_slug = $driver_json['slug'];
        $this->load_saved_values();
        
        $status_class = $enabled ? 'enabled' : 'disabled';
        $status_text = $enabled ? __('Enabled', 'smo-social') : __('Disabled', 'smo-social');
        
        echo '<div class="smo-platform-card ' . $status_class . '" data-platform="' . esc_attr($this->platform_slug) . '">';
        echo '<div class="smo-platform-header">';
        echo '<h3><img src="' . esc_url($this->get_platform_icon()) . '" alt="' . esc_attr($driver_json['name']) . '"> ' . esc_html($driver_json['name']) . '</h3>';
        echo '<span class="smo-platform-status">' . esc_html($status_text) . '</span>';
        echo '</div>';
        
        if ($enabled) {
            $this->render_platform_status();
        }
        
        echo '<div class="smo-platform-actions">';
        if ($enabled) {
            echo '<button type="button" class="button button-secondary smo-edit-platform" data-platform="' . esc_attr($this->platform_slug) . '">' . __('Configure', 'smo-social') . '</button>';
        } else {
            echo '<button type="button" class="button button-primary smo-enable-platform" data-platform="' . esc_attr($this->platform_slug) . '">' . __('Enable', 'smo-social') . '</button>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render platform header with icon, description, and capabilities
     */
    private function render_platform_header() {
        echo '<div class="smo-platform-header">';
        
        // Platform icon and name
        echo '<div class="smo-platform-title">';
        echo '<img src="' . esc_url($this->get_platform_icon()) . '" alt="' . esc_attr($this->driver_config['name']) . '" class="smo-platform-icon">';
        echo '<h3>' . esc_html($this->driver_config['name']) . ' ' . __('Settings', 'smo-social') . '</h3>';
        echo '</div>';
        
        // Platform description
        if (isset($this->driver_config['description'])) {
            echo '<p class="smo-platform-description">' . esc_html($this->driver_config['description']) . '</p>';
        }
        
        // Capabilities overview
        $this->render_capabilities_overview();
        
        echo '</div>';
    }

    /**
     * Render capabilities overview
     */
    private function render_capabilities_overview() {
        if (!isset($this->driver_config['capabilities'])) {
            return;
        }
        
        echo '<div class="smo-capabilities">';
        echo '<h4>' . __('Platform Capabilities', 'smo-social') . '</h4>';
        echo '<div class="smo-capability-list">';
        
        $capabilities = $this->driver_config['capabilities'];
        
        if (isset($capabilities['posting']) && $capabilities['posting']) {
            echo '<span class="smo-capability posting">' . __('Posting', 'smo-social') . '</span>';
        }
        
        if (isset($capabilities['analytics']) && $capabilities['analytics']) {
            echo '<span class="smo-capability analytics">' . __('Analytics', 'smo-social') . '</span>';
        }
        
        if (isset($capabilities['media']) && $capabilities['media']) {
            echo '<span class="smo-capability media">' . __('Media', 'smo-social') . '</span>';
        }
        
        if (isset($capabilities['groups']) && $capabilities['groups']) {
            echo '<span class="smo-capability groups">' . __('Groups', 'smo-social') . '</span>';
        }
        
        if (isset($capabilities['stories']) && $capabilities['stories']) {
            echo '<span class="smo-capability stories">' . __('Stories', 'smo-social') . '</span>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render the main configuration form
     */
    private function render_platform_form() {
        echo '<form method="post" action="options.php" class="smo-platform-form">';
        
        // Security nonce
        wp_nonce_field('smo_platform_settings_' . $this->platform_slug, 'smo_platform_nonce');
        
        // Hidden fields
        echo '<input type="hidden" name="smo_current_platform" value="' . esc_attr($this->platform_slug) . '">';
        echo '<input type="hidden" name="smo_action" value="save_platform_settings">';
        
        // Settings fields
        settings_fields('smo_platform_group_' . $this->platform_slug);
        do_settings_sections('smo_platform_group_' . $this->platform_slug);
        
        // Render configuration fields
        if (isset($this->driver_config['config_ui']) && is_array($this->driver_config['config_ui'])) {
            echo '<div class="smo-config-fields">';
            foreach ($this->driver_config['config_ui'] as $field) {
                $this->render_config_field($field);
            }
            echo '</div>';
        }
        
        // Validation rules section
        $this->render_validation_info();
        
        // Test connection button
        $this->render_test_connection();
        
        // Submit button
        submit_button(__('Save Platform Settings', 'smo-social'), 'primary', 'smo_save_platform');
        
        echo '</form>';
    }

    /**
     * Render individual configuration field
     * 
     * @param array $field Field configuration
     */
    private function render_config_field($field) {
        $field_id = 'smo_' . $this->platform_slug . '_' . $field['key'];
        $field_name = 'smo_data[' . $this->platform_slug . '][' . $field['key'] . ']';
        $field_value = $this->get_field_value($field['key']);
        $required = isset($field['required']) && $field['required'] ? ' <span class="required">*</span>' : '';
        
        echo '<div class="smo-field-wrapper">';
        echo '<label for="' . esc_attr($field_id) . '" class="smo-field-label">';
        echo esc_html($field['label']) . $required;
        echo '</label>';
        
        // Render field based on type
        $this->render_field_input($field, $field_id, $field_name, $field_value);
        
        // Help text
        if (isset($field['help_text']) && !empty($field['help_text'])) {
            echo '<p class="smo-field-help">' . esc_html($field['help_text']) . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Render field input based on type
     * 
     * @param array  $field       Field configuration
     * @param string $field_id    Field ID
     * @param string $field_name  Field name
     * @param mixed  $field_value Field value
     */
    private function render_field_input($field, $field_id, $field_name, $field_value) {
        $placeholder = isset($field['placeholder']) ? esc_attr($field['placeholder']) : '';
        $class = isset($field['class']) ? esc_attr($field['class']) : 'regular-text';
        
        switch ($field['type']) {
            case 'text':
            case 'url':
            case 'email':
                echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="' . esc_attr($class) . '" placeholder="' . $placeholder . '">';
                break;
                
            case 'password':
                // For encrypted fields, show masked value or placeholder
                $display_value = (isset($field['encrypt']) && $field['encrypt']) ? '' : $field_value;
                $placeholder = (isset($field['encrypt']) && $field['encrypt']) ? __('Saved (Leave empty to keep)', 'smo-social') : $placeholder;
                echo '<input type="password" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($display_value) . '" class="' . esc_attr($class) . '" placeholder="' . $placeholder . '" autocomplete="new-password">';
                break;
                
            case 'textarea':
                $rows = isset($field['rows']) ? $field['rows'] : 4;
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" rows="' . esc_attr($rows) . '" class="large-text" placeholder="' . $placeholder . '">' . esc_textarea($field_value) . '</textarea>';
                break;
                
            case 'checkbox':
                echo '<label><input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="1" ' . checked('1', $field_value, false) . '> ' . esc_html($field['checkbox_label'] ?? __('Enable', 'smo-social')) . '</label>';
                break;
                
            case 'select':
                if (isset($field['options']) && is_array($field['options'])) {
                    echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="' . esc_attr($class) . '">';
                    foreach ($field['options'] as $option_value => $option_label) {
                        echo '<option value="' . esc_attr($option_value) . '" ' . selected($field_value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                    }
                    echo '</select>';
                }
                break;
                
            case 'radio':
                if (isset($field['options']) && is_array($field['options'])) {
                    echo '<div class="smo-radio-group">';
                    foreach ($field['options'] as $option_value => $option_label) {
                        echo '<label><input type="radio" name="' . esc_attr($field_name) . '" value="' . esc_attr($option_value) . '" ' . checked($field_value, $option_value, false) . '> ' . esc_html($option_label) . '</label><br>';
                    }
                    echo '</div>';
                }
                break;
                
            default:
                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="' . esc_attr($class) . '" placeholder="' . $placeholder . '">';
        }
    }

    /**
     * Render validation information
     */
    private function render_validation_info() {
        if (!isset($this->driver_config['validation_rules'])) {
            return;
        }
        
        echo '<div class="smo-validation-info">';
        echo '<h4>' . __('Platform Requirements', 'smo-social') . '</h4>';
        
        // Character limits
        if (isset($this->driver_config['capabilities']['max_chars'])) {
            echo '<div class="smo-requirement">';
            echo '<strong>' . __('Maximum Characters:', 'smo-social') . '</strong> ' . number_format($this->driver_config['capabilities']['max_chars']);
            echo '</div>';
        }
        
        // Image requirements
        if (isset($this->driver_config['posting_specs']['image_formats'])) {
            echo '<div class="smo-requirement">';
            echo '<strong>' . __('Supported Image Formats:', 'smo-social') . '</strong> ' . implode(', ', $this->driver_config['posting_specs']['image_formats']);
            echo '</div>';
        }
        
        // Platform notes
        if (isset($this->driver_config['notes'])) {
            echo '<div class="smo-platform-notes">';
            echo '<strong>' . __('Notes:', 'smo-social') . '</strong> ' . esc_html($this->driver_config['notes']);
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Render test connection button and status
     */
    private function render_test_connection() {
        echo '<div class="smo-test-connection">';
        echo '<h4>' . __('Connection Test', 'smo-social') . '</h4>';
        echo '<p class="description">' . __('Test your connection to ensure the credentials are working correctly.', 'smo-social') . '</p>';
        echo '<button type="button" class="button secondary smo-test-connection-btn" data-platform="' . esc_attr($this->platform_slug) . '">' . __('Test Connection', 'smo-social') . '</button>';
        echo '<div class="smo-test-result" style="margin-top: 10px;"></div>';
        echo '</div>';
    }

    /**
     * Render platform status information
     */
    private function render_platform_status() {
        $last_post = get_option('smo_last_post_' . $this->platform_slug);
        $post_count = get_option('smo_post_count_' . $this->platform_slug, 0);
        
        echo '<div class="smo-platform-status">';
        echo '<h4>' . __('Platform Status', 'smo-social') . '</h4>';
        
        if ($last_post) {
            echo '<div class="smo-status-item">';
            echo '<strong>' . __('Last Post:', 'smo-social') . '</strong> ' . \human_time_diff(strtotime($last_post), \current_time('timestamp')) . ' ' . __('ago', 'smo-social');
            echo '</div>';
        }
        
        echo '<div class="smo-status-item">';
        echo '<strong>' . __('Total Posts:', 'smo-social') . '</strong> ' . number_format($post_count);
        echo '</div>';
        
        // Connection status
        $connection_status = get_transient('smo_connection_test_' . $this->platform_slug);
        if ($connection_status) {
            $status_class = $connection_status['success'] ? 'success' : 'error';
            $status_text = $connection_status['success'] ? __('Connected', 'smo-social') : __('Failed', 'smo-social');
            echo '<div class="smo-status-item">';
            echo '<strong>' . __('Connection:', 'smo-social') . '</strong> <span class="smo-status-' . $status_class . '">' . $status_text . '</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Get field value from saved data
     * 
     * @param string $field_key Field key
     * @return mixed Field value
     */
    private function get_field_value($field_key) {
        return isset($this->saved_values[$this->platform_slug][$field_key]) 
            ? $this->saved_values[$this->platform_slug][$field_key] 
            : '';
    }

    /**
     * Load saved configuration values
     */
    private function load_saved_values() {
        $this->saved_values = get_option($this->options_slug, array());
    }

    /**
     * Get platform icon URL
     * 
     * @return string Icon URL
     */
    private function get_platform_icon() {
        // Try to get icon from driver config
        if (isset($this->driver_config['icon'])) {
            $icon_path = SMO_SOCIAL_PLUGIN_DIR . 'assets/icons/' . basename($this->driver_config['icon']);
            if (file_exists($icon_path)) {
                return SMO_SOCIAL_PLUGIN_URL . 'assets/icons/' . basename($this->driver_config['icon']);
            }
        }
        
        // Fallback to default icon
        return SMO_SOCIAL_PLUGIN_URL . 'assets/icons/default-platform.svg';
    }

    /**
     * Process form submission with enhanced security
     * 
     * @param array $submitted_data Submitted form data
     * @return array|WP_Error Processing result
     */
    public function process_form_submission($submitted_data) {
        // Enhanced CSRF validation using new security component
        if (class_exists('\\SMO_Social\\Security\\CSRFManager')) {
            $csrf_token = $submitted_data['csrf_token'] ?? '';
            $action = 'platform_settings_' . ($submitted_data['smo_current_platform'] ?? 'unknown');
            
            if (!\SMO_Social\Security\CSRFManager::validateToken($csrf_token, $action)) {
                return new \WP_Error('invalid_csrf', __('CSRF validation failed', 'smo-social'));
            }
        } else {
            // Fallback to WordPress nonce
            if (!wp_verify_nonce($submitted_data['smo_platform_nonce'], 'smo_platform_settings_' . $submitted_data['smo_current_platform'])) {
                return new \WP_Error('invalid_nonce', __('Security check failed', 'smo-social'));
            }
        }
        
        // Enhanced input validation
        $platform = sanitize_text_field($submitted_data['smo_current_platform'] ?? '');
        
        // Validate platform name
        if (empty($platform) || !preg_match('/^[a-z0-9_-]+$/', $platform)) {
            return new \WP_Error('invalid_platform', __('Invalid platform name specified', 'smo-social'));
        }
        
        $platform_data = isset($submitted_data['smo_data'][$platform]) ? $submitted_data['smo_data'][$platform] : array();
        
        // Validate required fields using enhanced validation
        $driver_config = $this->get_driver_config($platform);
        if (!$driver_config) {
            return new \WP_Error('invalid_platform', __('Invalid platform configuration', 'smo-social'));
        }
        
        // Apply enhanced field validation
        $validation_result = $this->validate_platform_data($platform_data, $driver_config);
        if (!$validation_result['valid']) {
            return new \WP_Error('validation_failed', 
                __('Form validation failed:', 'smo-social') . ' ' . implode(', ', $validation_result['errors'])
            );
        }
        
        // Encrypt sensitive fields
        $processed_data = $this->process_sensitive_data($validation_result['sanitized_data'], $driver_config);
        
        // Save to options with enhanced security
        $all_credentials = get_option($this->options_slug, array());
        $all_credentials[$platform] = $processed_data;
        
        // Update with better security practices
        $updated = update_option($this->options_slug, $all_credentials, false); // Don't autoload
        
        if (!$updated) {
            return new \WP_Error('save_failed', __('Failed to save platform settings', 'smo-social'));
        }
        
        return array('success' => true, 'platform' => $platform, 'message' => __('Platform settings saved successfully', 'smo-social'));
    }
    
    /**
     * Enhanced field validation using InputValidator
     * 
     * @param array $data Form data
     * @param array $driver_config Driver configuration
     * @return array Validation result
     */
    private function validate_platform_data($data, $driver_config) {
        if (!class_exists('\\SMO_Social\\Security\\InputValidator')) {
            // Fallback validation
            return $this->basic_validation($data, $driver_config);
        }
        
        $validator = new \SMO_Social\Security\InputValidator();
        $validation_rules = array();
        
        // Build validation rules from driver config
        if (isset($driver_config['config_ui']) && is_array($driver_config['config_ui'])) {
            foreach ($driver_config['config_ui'] as $field) {
                $field_key = $field['key'];
                $rule = array();
                
                // Required field
                if (isset($field['required']) && $field['required']) {
                    $rule['required'] = true;
                }
                
                // Field type
                switch ($field['type']) {
                    case 'email':
                        $rule['type'] = 'email';
                        break;
                    case 'url':
                        $rule['type'] = 'url';
                        break;
                    case 'text':
                        $rule['type'] = 'string';
                        if (isset($field['min_length'])) {
                            $rule['min_length'] = $field['min_length'];
                        }
                        if (isset($field['max_length'])) {
                            $rule['max_length'] = $field['max_length'];
                        }
                        break;
                    case 'password':
                        if (isset($field['required']) && $field['required']) {
                            $rule['type'] = 'string';
                            $rule['min_length'] = 1;
                        }
                        break;
                    default:
                        $rule['type'] = 'string';
                        break;
                }
                
                // Custom validation callback
                if (isset($field['validation_callback'])) {
                    $rule['callback'] = $field['validation_callback'];
                }
                
                $validation_rules[$field_key] = $rule;
            }
        }
        
        return $validator->validateForm($data, $validation_rules);
    }
    
    /**
     * Basic validation fallback
     * 
     * @param array $data Form data
     * @param array $driver_config Driver configuration
     * @return array Validation result
     */
    private function basic_validation($data, $driver_config) {
        $errors = array();
        $sanitized_data = array();
        
        if (isset($driver_config['config_ui']) && is_array($driver_config['config_ui'])) {
            foreach ($driver_config['config_ui'] as $field) {
                $field_key = $field['key'];
                $field_value = $data[$field_key] ?? '';
                
                // Required field validation
                if (isset($field['required']) && $field['required'] && empty($field_value)) {
                    $errors[] = sprintf(__('Field %s is required', 'smo-social'), $field['label']);
                    continue;
                }
                
                // Basic sanitization
                switch ($field['type']) {
                    case 'email':
                        $sanitized_data[$field_key] = sanitize_email($field_value);
                        break;
                    case 'url':
                        $sanitized_data[$field_key] = esc_url_raw($field_value);
                        break;
                    case 'text':
                    case 'password':
                        $sanitized_data[$field_key] = sanitize_text_field($field_value);
                        break;
                    case 'textarea':
                        $sanitized_data[$field_key] = sanitize_textarea_field($field_value);
                        break;
                    default:
                        $sanitized_data[$field_key] = sanitize_text_field($field_value);
                        break;
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'sanitized_data' => $sanitized_data,
            'errors' => $errors
        );
    }

    /**
     * Process sensitive data (encryption)
     * 
     * @param array $data          Form data
     * @param array $driver_config Driver configuration
     * @return array Processed data
     */
    private function process_sensitive_data($data, $driver_config) {
        if (!isset($driver_config['config_ui'])) {
            return $data;
        }
        
        foreach ($driver_config['config_ui'] as $field) {
            if (isset($field['encrypt']) && $field['encrypt'] && !empty($data[$field['key']])) {
                // Only encrypt if field is marked as encrypt and has value
                $data[$field['key']] = $this->encrypt_value($data[$field['key']]);
            }
        }
        
        return $data;
    }

    /**
     * Encrypt sensitive value
     * 
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    public function encrypt_value($value) {
        $key = hash('sha256', AUTH_KEY . AUTH_SALT, true);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive value
     * 
     * @param string $encrypted_value Encrypted value
     * @return string Decrypted value
     */
    public function decrypt_value($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }
        
        $key = hash('sha256', AUTH_KEY . AUTH_SALT, true);
        $data = base64_decode($encrypted_value);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get driver configuration for platform
     * 
     * @param string $platform Platform slug
     * @return array|null Driver configuration
     */
    private function get_driver_config($platform) {
        $driver_file = SMO_SOCIAL_PLUGIN_DIR . 'drivers/' . $platform . '.json';
        if (!file_exists($driver_file)) {
            return null;
        }
        
        return json_decode(file_get_contents($driver_file), true);
    }
}
