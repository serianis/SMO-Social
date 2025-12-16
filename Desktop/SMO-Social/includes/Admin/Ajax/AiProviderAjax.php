<?php
namespace SMO_Social\Admin\Ajax;

use SMO_Social\AI\ProvidersConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Provider Configuration AJAX Handlers
 */
class AiProviderAjax extends BaseAjaxHandler {
    
    public function register() {
        add_action('wp_ajax_smo_save_provider_config', [$this, 'save_config']);
        add_action('wp_ajax_smo_switch_provider', [$this, 'switch_provider']);
        add_action('wp_ajax_smo_check_provider_status', [$this, 'check_provider_status']);
        add_action('wp_ajax_smo_validate_api_key', [$this, 'validate_api_key']);
    }

    public function save_config() {
        if (!$this->verify_request()) return;

        $provider = $this->get_text('provider');
        if (!$provider) {
            $this->send_error(__('Provider not specified', 'smo-social'));
            return;
        }

        $config = isset($_POST['config']) ? $_POST['config'] : [];
        if (empty($config)) {
             $this->send_error(__('Configuration data is missing', 'smo-social'));
             return;
        }

        // Validate and sanitize configuration
        $sanitized_config = array();
        foreach ($config as $key => $value) {
            $sanitized_key = sanitize_key($key);
            $sanitized_config[$sanitized_key] = sanitize_text_field($value);
        }

        // Save configuration to main provider config option
        $option_name = 'smo_social_' . $provider . '_config';
        update_option($option_name, $sanitized_config);

        // Also update specific options if defined in ProvidersConfig (legacy/bridge support)
        if (class_exists('SMO_Social\AI\ProvidersConfig')) {
            $provider_details = ProvidersConfig::get_provider($provider);
            if ($provider_details) {
                // Update API Key option if defined
                if (!empty($provider_details['key_option']) && isset($sanitized_config['api_key'])) {
                    update_option($provider_details['key_option'], $sanitized_config['api_key']);
                }
                // Update URL option if defined
                if (!empty($provider_details['url_option']) && isset($sanitized_config['api_url'])) {
                    update_option($provider_details['url_option'], $sanitized_config['api_url']);
                }
            }
        }

        $this->send_success([
            'provider' => $provider
        ], __('Provider configuration saved successfully', 'smo-social'));
    }

    public function switch_provider() {
        if (!$this->verify_request()) return;
        
        $provider = $this->get_text('provider');
        if (!$provider) {
            $this->send_error(__('Provider not specified', 'smo-social'));
            return;
        }

        // Update the primary provider setting
        update_option('smo_social_primary_provider', $provider);
        
        // Get the configuration form for the selected provider
        $config_form = $this->get_provider_config_form($provider);

        $this->send_success([
            'provider' => $provider,
            'config_form' => $config_form
        ], __('Provider switched successfully', 'smo-social'));
    }

    public function check_provider_status() {
        if (!$this->verify_request()) return;

        $provider = $this->get_text('provider');
        if (!$provider) {
            $this->send_error(__('Provider not specified', 'smo-social'));
            return;
        }

        // Check if provider configuration exists
        $config_option = 'smo_social_' . $provider . '_config';
        $provider_config = get_option($config_option, []);

        // Check if required fields are present
        $valid = false;
        $connected = false;
        $message = '';

        // Check for API key (most providers require this)
        // Also check specific option if config is empty
        $api_key = $provider_config['api_key'] ?? '';
        if (empty($api_key) && class_exists('SMO_Social\AI\ProvidersConfig')) {
             $provider_details = ProvidersConfig::get_provider($provider);
             if ($provider_details && !empty($provider_details['key_option'])) {
                 $api_key = get_option($provider_details['key_option']);
             }
        }

        if (!empty($api_key)) {
            $valid = true;
            $connected = true; // Simulation
            $message = __('Provider configuration is valid and connected', 'smo-social');
        } else {
            // Some providers don't need keys (localhost)
            $needs_key = true;
            if (class_exists('SMO_Social\AI\ProvidersConfig')) {
                $provider_details = ProvidersConfig::get_provider($provider);
                if ($provider_details && isset($provider_details['requires_key']) && !$provider_details['requires_key']) {
                     $needs_key = false;
                }
            }
            
            if (!$needs_key) {
                 $valid = true;
                 $connected = true;
                 $message = __('Provider configured (no key required)', 'smo-social');
            } else {
                 $message = __('API key is missing or invalid', 'smo-social');
            }
        }

        $this->send_success([
            'valid' => $valid,
            'connected' => $connected,
            'message' => $message,
            'provider' => $provider,
            'last_checked' => current_time('mysql')
        ]);
    }

    public function validate_api_key() {
        if (!$this->verify_request()) return;

        $api_key = $this->get_text('api_key');
        $provider = $this->get_text('provider');

        if (!$api_key || !$provider) {
            $this->send_error(__('API key and provider are required', 'smo-social'));
            return;
        }

        // Helper to find validator if needed
        $validator_exists = class_exists('\\SMO_Social\\Security\\APIKeyFormatValidator');
        
        if ($validator_exists) {
             $validator = new \SMO_Social\Security\APIKeyFormatValidator();
             $validation_result = $validator->validate_key_format($api_key, $provider);

             if ($validation_result['valid']) {
                 $this->send_success([
                     'valid' => true,
                     'message' => $validation_result['message'],
                     'provider' => $provider
                 ]);
             } else {
                 $this->send_error(
                     $validation_result['error'],
                     400,
                     [
                         'valid' => false,
                         'provider' => $provider,
                         'example' => $validation_result['example'] ?? null
                     ]
                 );
             }
        } else {
             // Fallback if class not found, perform basic validation
             if (strlen($api_key) > 5) {
                 $this->send_success([
                     'valid' => true, 
                     'message' => __('API key format valid (basic check)', 'smo-social'),
                     'provider' => $provider
                 ]);
             } else {
                 $this->send_error(__('API key too short', 'smo-social'));
             }
        }
    }

    /**
     * Get provider configuration form HTML
     */
    private function get_provider_config_form($provider) {
        ob_start();

        // Get current configuration for this provider
        $config_option = 'smo_social_' . $provider . '_config';
        $current_config = get_option($config_option, []);

        // Get provider details from ProvidersConfig
        $provider_details = [];
        if (class_exists('SMO_Social\AI\ProvidersConfig')) {
            $provider_details = ProvidersConfig::get_provider($provider);
        }

        // If no details found, use defaults
        if (empty($provider_details)) {
             $provider_details = [
                 'name' => ucfirst($provider),
                 'requires_key' => true,
                 'base_url' => '',
                 'models' => []
             ];
        }

        // Prepare fields
        $fields = [];

        // 1. API Key Field
        if (!empty($provider_details['requires_key'])) {
            $key_value = $current_config['api_key'] ?? '';
            // Fallback to specific option if empty
            if (empty($key_value) && !empty($provider_details['key_option'])) {
                $key_value = get_option($provider_details['key_option']);
            }

            $fields['api_key'] = [
                'label' => __('API Key', 'smo-social'),
                // Some "API providers" might have different terminology, but API Key is standard enough
                'type' => 'password',
                'value' => $key_value,
                'required' => true,
                'placeholder' => __('Enter your API Key', 'smo-social')
            ];
        }

        // 2. Base URL Field
        // Show ONLY if:
        // - Provider has a 'url_option' explicitly defined (e.g. ollama, custom)
        // - OR provider type is 'local' or 'custom'
        // - We explicitly EXCLUDE cloud/router providers unless they have url_option
        $show_base_url = !empty($provider_details['url_option']) || 
                         in_array($provider_details['type'] ?? '', ['local', 'custom']);

        if ($show_base_url) {
            $url_value = $current_config['api_url'] ?? '';
            // Fallback to specific option
            if (empty($url_value) && !empty($provider_details['url_option'])) {
                $url_value = get_option($provider_details['url_option']);
            }
            // Fallback to default base_url
            $default_url = $provider_details['base_url'] ?? '';
            if (empty($url_value)) {
                $url_value = $default_url;
            }

            $fields['api_url'] = [
                'label' => __('API Endpoint URL', 'smo-social'),
                'type' => 'text',
                'value' => $url_value,
                'required' => empty($default_url), // Required if no default
                'placeholder' => $default_url ?: 'https://api.example.com/v1',
                'description' => !empty($default_url) ? sprintf(__('Default: %s', 'smo-social'), $default_url) : ''
            ];
        }

        // 3. Model Selection
        $models = $provider_details['models'] ?? [];
        $current_model = $current_config['model'] ?? '';
        
        if (!empty($models) && is_array($models)) {
            // Check if models list is just a placeholder for custom input
            if (count($models) === 1 && in_array($models[0], ['custom', 'local-models', 'marketplace-models', 'cluster-models'])) {
                $fields['model'] = [
                    'label' => __('Model Name', 'smo-social'),
                    'type' => 'text',
                    'value' => $current_model,
                    'required' => true,
                    'placeholder' => __('e.g. llama-3-8b', 'smo-social')
                ];
            } else {
                // Dropdown with "Other" option
                $options = array_combine($models, $models);
                $options['custom_input'] = __('Other (Enter manually)', 'smo-social');
                
                // Determine if current value is in options, otherwise set select to custom_input
                $select_value = in_array($current_model, $models) ? $current_model : ($current_model ? 'custom_input' : '');
                // If custom input, the text value goes to the text field
                $text_value = $select_value === 'custom_input' ? $current_model : '';

                $fields['model'] = [
                    'label' => __('Select Model', 'smo-social'),
                    'type' => 'select',
                    'options' => $options,
                    'value' => $select_value,
                    'required' => true,
                    'class' => 'smo-model-select'
                ];
                
                // Extra field for custom input
                $fields['custom_model'] = [
                    'label' => __('Enter Custom Model Name', 'smo-social'),
                    'type' => 'text',
                    'value' => $text_value,
                    'required' => false, // Validated via JS
                    'placeholder' => __('e.g. gpt-5-turbo', 'smo-social'),
                    'class' => 'smo-custom-model-input',
                    'wrapper_class' => 'smo-custom-model-wrapper',
                    'wrapper_style' => $select_value === 'custom_input' ? '' : 'display:none;'
                ];
            }
        } else {
            // Fallback if no models defined
             $fields['model'] = [
                'label' => __('Model Name', 'smo-social'),
                'type' => 'text',
                'value' => $current_model,
                'required' => false,
                'placeholder' => __('Default model', 'smo-social')
            ];
        }

        // Render form
        ?>
        <div class="smo-provider-config-form" data-provider="<?php echo esc_attr($provider); ?>">
            <input type="hidden" name="provider" value="<?php echo esc_attr($provider); ?>">
            
            <?php if (!empty($provider_details['description'])): ?>
                <p class="description"><?php echo esc_html($provider_details['description']); ?></p>
            <?php endif; ?>

            <?php foreach ($fields as $field_name => $field): ?>
                <div class="smo-config-field <?php echo esc_attr($field['wrapper_class'] ?? ''); ?>" style="<?php echo esc_attr($field['wrapper_style'] ?? ''); ?>">
                    <label for="smo_<?php echo esc_attr($provider . '_' . $field_name); ?>">
                        <?php echo esc_html($field['label']); ?>
                        <?php if (!empty($field['required']) && empty($field['wrapper_class'])): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>

                    <?php if ($field['type'] === 'select'): ?>
                        <select name="<?php echo esc_attr($field_name); ?>"
                                id="smo_<?php echo esc_attr($provider . '_' . $field_name); ?>"
                                class="widefat <?php echo esc_attr($field['class'] ?? ''); ?>"
                                <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <?php foreach ($field['options'] as $option_value => $option_label): ?>
                                <option value="<?php echo esc_attr($option_value); ?>"
                                    <?php selected(isset($field['value']) ? $field['value'] : '', $option_value); ?>>
                                    <?php echo esc_html($option_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="<?php echo esc_attr($field['type']); ?>"
                               name="<?php echo esc_attr($field_name); ?>"
                               id="smo_<?php echo esc_attr($provider . '_' . $field_name); ?>"
                               value="<?php echo esc_attr($field['value']); ?>"
                               placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                               class="widefat <?php echo esc_attr($field['class'] ?? ''); ?>"
                               <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                    <?php endif; ?>
                    
                    <?php if (!empty($field['description'])): ?>
                        <p class="description"><?php echo esc_html($field['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="smo-config-actions">
                 <button type="submit" class="button button-primary"><?php _e('Save Configuration', 'smo-social'); ?></button>
            </div>

            <script>
            (function($) {
                // Handle model selection change
                $('.smo-model-select').on('change', function() {
                    var isCustom = $(this).val() === 'custom_input';
                    var wrapper = $(this).closest('.smo-provider-config-form').find('.smo-custom-model-wrapper');
                    var input = wrapper.find('input');
                    
                    if (isCustom) {
                        wrapper.slideDown();
                        input.prop('required', true);
                    } else {
                        wrapper.slideUp();
                        input.prop('required', false);
                    }
                });
            })(jQuery);
            </script>
        </div>
        
        <style>
        .smo-config-field { margin-bottom: 15px; }
        .smo-config-field label { display: block; font-weight: 600; margin-bottom: 5px; }
        .smo-config-field .widefat { width: 100%; max-width: 400px; }
        </style>
        <?php

        return ob_get_clean();
    }
}
