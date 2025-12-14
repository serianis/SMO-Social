<?php
namespace SMO_Social\Admin;

use SMO_Social\AI\ProvidersConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsManager
 * Handles registration and rendering of plugin settings.
 */
class SettingsManager
{
    /**
     * Register settings and fields
     */
    public function init_settings()
    {
        // Register main settings group with sanitization callback
        \register_setting('smo_social_settings', 'smo_social_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default' => array()
        ));

        // Register individual settings for backward compatibility
        // Register individual settings with proper sanitization callbacks
        \register_setting('smo_social_settings', 'smo_social_enabled', array('sanitize_callback' => 'absint', 'default' => 1));
        \register_setting('smo_social_settings', 'smo_social_timezone', array('sanitize_callback' => 'sanitize_text_field', 'default' => 'UTC'));
        \register_setting('smo_social_settings', 'smo_social_date_format', array('sanitize_callback' => 'sanitize_text_field', 'default' => 'Y-m-d H:i:s'));
        \register_setting('smo_social_settings', 'smo_social_queue_interval', array('sanitize_callback' => 'absint', 'default' => 5));
        \register_setting('smo_social_settings', 'smo_social_max_retries', array('sanitize_callback' => 'absint', 'default' => 3));
        \register_setting('smo_social_settings', 'smo_social_retry_delay', array('sanitize_callback' => 'absint', 'default' => 300));
        \register_setting('smo_social_settings', 'smo_social_ai_enabled', array('sanitize_callback' => 'absint', 'default' => 1));
        \register_setting('smo_social_settings', 'smo_social_ai_tone', array('sanitize_callback' => 'sanitize_text_field', 'default' => 'professional'));
        \register_setting('smo_social_settings', 'smo_social_ai_variants', array('sanitize_callback' => 'absint', 'default' => 0));
        \register_setting('smo_social_settings', 'smo_social_data_retention', array('sanitize_callback' => 'absint', 'default' => 365));
        \register_setting('smo_social_settings', 'smo_social_log_level', array('sanitize_callback' => 'sanitize_text_field', 'default' => 'info'));

        // Add Settings Sections
        \add_settings_section(
            'smo_social_general_section',
            __('General Settings', 'smo-social'),
            array($this, 'general_section_callback'),
            'smo_social_settings'
        );

        \add_settings_section(
            'smo_social_queue_section',
            __('Queue Settings', 'smo-social'),
            array($this, 'queue_section_callback'),
            'smo_social_settings'
        );

        \add_settings_section(
            'smo_social_ai_section',
            __('AI Features', 'smo-social'),
            array($this, 'ai_section_callback'),
            'smo_social_settings'
        );

        \add_settings_section(
            'smo_social_privacy_section',
            __('Privacy & Data', 'smo-social'),
            array($this, 'privacy_section_callback'),
            'smo_social_settings'
        );

        // Add Settings Fields
        \add_settings_field(
            'smo_social_enabled',
            __('Enable Plugin', 'smo-social'),
            array($this, 'checkbox_field_callback'),
            'smo_social_settings',
            'smo_social_general_section',
            array('field' => 'smo_social_enabled')
        );

        \add_settings_field(
            'smo_social_timezone',
            __('Default Timezone', 'smo-social'),
            array($this, 'timezone_field_callback'),
            'smo_social_settings',
            'smo_social_general_section',
            array('field' => 'smo_social_timezone')
        );

        \add_settings_field(
            'smo_social_date_format',
            __('Date Format', 'smo-social'),
            array($this, 'date_format_field_callback'),
            'smo_social_settings',
            'smo_social_general_section',
            array('field' => 'smo_social_date_format')
        );

        \add_settings_field(
            'smo_social_queue_interval',
            __('Processing Interval', 'smo-social'),
            array($this, 'select_field_callback'),
            'smo_social_settings',
            'smo_social_queue_section',
            array(
                'field' => 'smo_social_queue_interval',
                'options' => array(
                    '1' => __('Every minute', 'smo-social'),
                    '5' => __('Every 5 minutes', 'smo-social'),
                    '10' => __('Every 10 minutes', 'smo-social'),
                    '15' => __('Every 15 minutes', 'smo-social')
                )
            )
        );

        \add_settings_field(
            'smo_social_max_retries',
            __('Max Retries', 'smo-social'),
            array($this, 'number_field_callback'),
            'smo_social_settings',
            'smo_social_queue_section',
            array('field' => 'smo_social_max_retries', 'min' => 1, 'max' => 10)
        );

        \add_settings_field(
            'smo_social_retry_delay',
            __('Retry Delay', 'smo-social'),
            array($this, 'select_field_callback'),
            'smo_social_settings',
            'smo_social_queue_section',
            array(
                'field' => 'smo_social_retry_delay',
                'options' => array(
                    '300' => __('5 minutes', 'smo-social'),
                    '600' => __('10 minutes', 'smo-social'),
                    '900' => __('15 minutes', 'smo-social'),
                    '1800' => __('30 minutes', 'smo-social')
                )
            )
        );

        \add_settings_field(
            'smo_social_ai_enabled',
            __('Enable AI Content Optimization', 'smo-social'),
            array($this, 'checkbox_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array('field' => 'smo_social_ai_enabled')
        );

        \add_settings_field(
            'smo_social_ai_tone',
            __('AI Content Tone', 'smo-social'),
            array($this, 'select_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array(
                'field' => 'smo_social_ai_tone',
                'options' => array(
                    'professional' => __('Professional', 'smo-social'),
                    'casual' => __('Casual', 'smo-social'),
                    'friendly' => __('Friendly', 'smo-social'),
                    'authoritative' => __('Authoritative', 'smo-social'),
                    'humorous' => __('Humorous', 'smo-social')
                )
            )
        );

        \add_settings_field(
            'smo_social_ai_variants',
            __('Generate Content Variants', 'smo-social'),
            array($this, 'checkbox_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array('field' => 'smo_social_ai_variants')
        );

        \add_settings_field(
            'smo_social_primary_provider',
            __('Primary AI Provider', 'smo-social'),
            array($this, 'select_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array(
                'field' => 'smo_social_primary_provider',
                'options' => array(
                    'huggingface' => __('HuggingFace (Free)', 'smo-social'),
                    'localhost' => __('Localhost AI (Ollama, LM Studio)', 'smo-social'),
                    'custom' => __('Custom AI API', 'smo-social')
                )
            )
        );

        \add_settings_field(
            'smo_social_huggingface_api_key',
            __('HuggingFace API Key', 'smo-social'),
            array($this, 'text_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array('field' => 'smo_social_huggingface_api_key', 'type' => 'password')
        );

        \add_settings_field(
            'smo_social_localhost_api_url',
            __('Localhost AI URL', 'smo-social'),
            array($this, 'text_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array('field' => 'smo_social_localhost_api_url')
        );

        \add_settings_field(
            'smo_social_custom_api_url',
            __('Custom AI API URL', 'smo-social'),
            array($this, 'text_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array('field' => 'smo_social_custom_api_url')
        );

        \add_settings_field(
            'smo_social_custom_api_key',
            __('Custom AI API Key', 'smo-social'),
            array($this, 'text_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array('field' => 'smo_social_custom_api_key', 'type' => 'password')
        );

        // Register additional AI Provider settings dynamically
        if (class_exists('\\SMO_Social\\AI\\ProvidersConfig')) {
            $providers = ProvidersConfig::get_all_providers();
            foreach ($providers as $id => $provider) {
                // Register API key option
                if (isset($provider['key_option'])) {
                    \register_setting('smo_social_settings', $provider['key_option'], array('sanitize_callback' => 'sanitize_text_field'));
                }
                // Register URL option
                if (isset($provider['url_option'])) {
                    \register_setting('smo_social_settings', $provider['url_option'], array('sanitize_callback' => 'esc_url_raw'));
                }
                // Register model option
                if (!empty($provider['models'])) {
                    $model_option = 'smo_social_' . $id . '_model';
                    \register_setting('smo_social_settings', $model_option, array('sanitize_callback' => 'sanitize_text_field'));
                }
            }
        }

        // Register primary provider option
        \register_setting('smo_social_settings', 'smo_social_primary_provider', array('sanitize_callback' => 'sanitize_text_field'));

        \add_settings_field(
            'smo_social_fallback_enabled',
            __('Enable AI Fallback', 'smo-social'),
            array($this, 'checkbox_field_callback'),
            'smo_social_settings',
            'smo_social_ai_section',
            array('field' => 'smo_social_fallback_enabled')
        );

        \add_settings_field(
            'smo_social_data_retention',
            __('Data Retention', 'smo-social'),
            array($this, 'select_field_callback'),
            'smo_social_settings',
            'smo_social_privacy_section',
            array(
                'field' => 'smo_social_data_retention',
                'options' => array(
                    '30' => __('30 days', 'smo-social'),
                    '90' => __('90 days', 'smo-social'),
                    '180' => __('180 days', 'smo-social'),
                    '365' => __('1 year', 'smo-social'),
                    '0' => __('Forever', 'smo-social')
                )
            )
        );

        \add_settings_field(
            'smo_social_log_level',
            __('Log Level', 'smo-social'),
            array($this, 'select_field_callback'),
            'smo_social_settings',
            'smo_social_privacy_section',
            array(
                'field' => 'smo_social_log_level',
                'options' => array(
                    'error' => __('Errors only', 'smo-social'),
                    'warning' => __('Warnings and errors', 'smo-social'),
                    'info' => __('Information, warnings, and errors', 'smo-social'),
                    'debug' => __('Debug mode (very verbose)', 'smo-social')
                )
            )
        );
    }

    // Settings API Callbacks
    public function general_section_callback()
    {
        echo '<p>' . __('Configure basic plugin settings.', 'smo-social') . '</p>';
    }

    public function queue_section_callback()
    {
        echo '<p>' . __('Configure post queue processing settings.', 'smo-social') . '</p>';
    }

    public function ai_section_callback()
    {
        echo '<p>' . __('Configure AI-powered content optimization features.', 'smo-social') . '</p>';
    }

    public function privacy_section_callback()
    {
        echo '<p>' . __('Configure data retention and privacy settings.', 'smo-social') . '</p>';
    }

    public function checkbox_field_callback($args)
    {
        $field = $args['field'];
        $value = get_option($field, 0);
        echo '<label>';
        echo '<input type="checkbox" name="' . \esc_attr($field) . '" value="1" ' . \checked($value, 1, false) . '>';
        echo '</label>';
    }

    public function select_field_callback($args)
    {
        $field = $args['field'];
        $options = $args['options'];
        $value = get_option($field, '');

        echo '<select name="' . \esc_attr($field) . '">';
        foreach ($options as $option_value => $option_label) {
            echo '<option value="' . \esc_attr($option_value) . '"' . \selected($value, $option_value, false) . '>' . \esc_html($option_label) . '</option>';
        }
        echo '</select>';
    }

    public function number_field_callback($args)
    {
        $field = $args['field'];
        $value = get_option($field, 1);
        $min = isset($args['min']) ? $args['min'] : 0;
        $max = isset($args['max']) ? $args['max'] : 999;

        echo '<input type="number" name="' . \esc_attr($field) . '" value="' . \esc_attr($value) . '" min="' . \esc_attr($min) . '" max="' . \esc_attr($max) . '">';
    }

    public function timezone_field_callback($args)
    {
        $field = $args['field'];
        $timezones = timezone_identifiers_list();
        $current_tz = get_option($field, 'UTC');

        echo '<select name="' . \esc_attr($field) . '">';
        foreach ($timezones as $tz) {
            echo '<option value="' . \esc_attr($tz) . '"' . \selected($current_tz, $tz, false) . '>' . \esc_html($tz) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Default timezone for scheduling posts', 'smo-social') . '</p>';
    }

    public function date_format_field_callback($args)
    {
        $field = $args['field'];
        $value = get_option($field, 'Y-m-d H:i:s');

        $formats = array(
            'Y-m-d H:i:s' => '2024-11-15 14:30:00',
            'm/d/Y H:i' => '11/15/2024 14:30',
            'd/m/Y H:i' => '15/11/2024 14:30',
            'F j, Y g:i A' => 'November 15, 2024 2:30 PM'
        );

        echo '<select name="' . \esc_attr($field) . '">';
        foreach ($formats as $format_value => $display) {
            echo '<option value="' . \esc_attr($format_value) . '"' . \selected($value, $format_value, false) . '>' . \esc_html($display) . '</option>';
        }
        echo '</select>';
    }

    public function text_field_callback($args)
    {
        $field = $args['field'];
        $value = get_option($field, '');
        $type = $args['type'] ?? 'text';

        echo '<input type="' . \esc_attr($type) . '" name="' . \esc_attr($field) . '" value="' . \esc_attr($value) . '">';
    }

    public function sanitize_settings($input)
    {
        // Guard clause: if input is not an array, it might be an individual setting value passed by mistake
        if (!is_array($input)) {
             return is_string($input) ? sanitize_text_field($input) : $input;
        }

        $sanitized = array();

        if (isset($input['smo_social_enabled'])) {
            $sanitized['smo_social_enabled'] = (bool) $input['smo_social_enabled'];
        }

        if (isset($input['smo_social_timezone'])) {
            $sanitized['smo_social_timezone'] = \sanitize_text_field($input['smo_social_timezone']);
        }

        if (isset($input['smo_social_date_format'])) {
            $sanitized['smo_social_date_format'] = \sanitize_text_field($input['smo_social_date_format']);
        }

        if (isset($input['smo_social_queue_interval'])) {
            $sanitized['smo_social_queue_interval'] = absint($input['smo_social_queue_interval']);
        }

        if (isset($input['smo_social_max_retries'])) {
            $sanitized['smo_social_max_retries'] = absint($input['smo_social_max_retries']);
        }

        if (isset($input['smo_social_retry_delay'])) {
            $sanitized['smo_social_retry_delay'] = absint($input['smo_social_retry_delay']);
        }

        if (isset($input['smo_social_ai_enabled'])) {
            $sanitized['smo_social_ai_enabled'] = (bool) $input['smo_social_ai_enabled'];
        }

        if (isset($input['smo_social_ai_tone'])) {
            $sanitized['smo_social_ai_tone'] = \sanitize_text_field($input['smo_social_ai_tone']);
        }

        if (isset($input['smo_social_ai_variants'])) {
            $sanitized['smo_social_ai_variants'] = (bool) $input['smo_social_ai_variants'];
        }

        if (isset($input['smo_social_data_retention'])) {
            $sanitized['smo_social_data_retention'] = absint($input['smo_social_data_retention']);
        }

        if (isset($input['smo_social_log_level'])) {
            $sanitized['smo_social_log_level'] = \sanitize_text_field($input['smo_social_log_level']);
        }

        return $sanitized;
    }
}
