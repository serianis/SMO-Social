<?php
/**
 * SMO Social - Batch API Credentials Configuration Script
 * 
 * This script helps configure multiple API credentials at once
 * for efficient production deployment.
 *
 * @package SMO_Social
 * @subpackage Setup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Batch Configuration Class
 */
class SMOBatchConfigurator {
    
    /**
     * Configuration mapping for all services
     */
    public static $config_mapping = [
        'canva' => [
            'fields' => ['client_id', 'client_secret'],
            'options' => ['smo_canva_client_id', 'smo_canva_client_secret']
        ],
        'unsplash' => [
            'fields' => ['api_key'],
            'options' => ['smo_unsplash_access_token']
        ],
        'pixabay' => [
            'fields' => ['api_key'],
            'options' => ['smo_pixabay_api_key']
        ],
        'dropbox' => [
            'fields' => ['app_key', 'app_secret'],
            'options' => ['smo_dropbox_app_key', 'smo_dropbox_app_secret']
        ],
        'google_drive' => [
            'fields' => ['client_id', 'client_secret'],
            'options' => ['smo_google_client_id', 'smo_google_client_secret']
        ],
        'google_photos' => [
            'fields' => ['client_id', 'client_secret'],
            'options' => ['smo_google_client_id', 'smo_google_client_secret']
        ],
        'onedrive' => [
            'fields' => ['client_id', 'client_secret'],
            'options' => ['smo_onedrive_client_id', 'smo_onedrive_client_secret']
        ],
        'zapier' => [
            'fields' => ['webhook_secret'],
            'options' => ['smo_zapier_webhook_secret']
        ],
        'ifttt' => [
            'fields' => ['webhook_secret'],
            'options' => ['smo_ifttt_webhook_secret']
        ],
        'feedly' => [
            'fields' => ['client_id', 'client_secret'],
            'options' => ['smo_feedly_client_id', 'smo_feedly_client_secret']
        ],
        'pocket' => [
            'fields' => ['consumer_key'],
            'options' => ['smo_pocket_consumer_key']
        ]
    ];

    /**
     * Process batch configuration
     */
    public static function process_batch_config($config_data) {
        $results = [];
        $processed_count = 0;
        $error_count = 0;

        foreach ($config_data as $service_id => $service_config) {
            try {
                if (!isset(self::$config_mapping[$service_id])) {
                    throw new Exception("Unknown service: {$service_id}");
                }

                $result = self::configure_service($service_id, $service_config);
                $results[$service_id] = $result;

                if ($result['success']) {
                    $processed_count++;
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $results[$service_id] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $error_count++;
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total' => count($config_data),
                'processed' => $processed_count,
                'errors' => $error_count,
                'success_rate' => $processed_count > 0 ? round(($processed_count / count($config_data)) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Configure individual service
     */
    private static function configure_service($service_id, $config) {
        $mapping = self::$config_mapping[$service_id];
        $service_results = [];

        foreach ($mapping['options'] as $index => $option_name) {
            $field_name = $mapping['fields'][$index];
            $value = $config[$field_name] ?? '';

            if (empty($value)) {
                $service_results[] = [
                    'field' => $field_name,
                    'option' => $option_name,
                    'success' => false,
                    'error' => 'Empty value provided'
                ];
                continue;
            }

            // Store in WordPress options
            $update_result = update_option($option_name, $value);
            
            $service_results[] = [
                'field' => $field_name,
                'option' => $option_name,
                'success' => $update_result !== false,
                'value' => $value
            ];
        }

        $all_successful = !array_filter($service_results, function($r) { return !$r['success']; });
        
        return [
            'success' => $all_successful,
            'service' => $service_id,
            'details' => $service_results
        ];
    }

    /**
     * Generate configuration form
     */
    public static function generate_config_form() {
        $form = "<div class='smo-batch-config-form'>\n";
        $form .= "<h2>🔧 Batch API Configuration</h2>\n";
        $form .= "<p>Configure multiple services at once by filling in the values below:</p>\n";
        $form .= "<form method='post' id='smo-batch-config-form'>\n";
        $form .= wp_nonce_field('smo_batch_config', 'smo_batch_nonce', true, false);
        
        foreach (self::$config_mapping as $service_id => $mapping) {
            $service_name = ucwords(str_replace('_', ' ', $service_id));
            $form .= "<div class='smo-service-config' data-service='{$service_id}'>\n";
            $form .= "<h3>📌 {$service_name}</h3>\n";
            
            foreach ($mapping['fields'] as $index => $field) {
                $option_name = $mapping['options'][$index];
                $current_value = get_option($option_name);
                $placeholder = ucwords(str_replace('_', ' ', $field));
                
                $form .= "<div class='smo-field-group'>\n";
                $form .= "<label for='{$service_id}_{$field}'>{$placeholder}:</label>\n";
                $form .= "<input type='text' id='{$service_id}_{$field}' name='config[{$service_id}][{$field}]' ";
                $form .= "value='" . esc_attr($current_value) . "' placeholder='Enter {$placeholder}' />\n";
                $form .= "<small>WordPress Option: {$option_name}</small>\n";
                $form .= "</div>\n";
            }
            
            $form .= "</div>\n";
        }
        
        $form .= "<div class='smo-form-actions'>\n";
        $form .= "<button type='submit' class='button button-primary'>🚀 Apply Configuration</button>\n";
        $form .= "<button type='button' class='button' id='smo-validate-config'>✅ Validate Configuration</button>\n";
        $form .= "<button type='button' class='button' id='smo-clear-config'>🗑️ Clear All Values</button>\n";
        $form .= "</div>\n";
        $form .= "</form>\n";
        $form .= "</div>\n";
        
        return $form;
    }

    /**
     * Handle form submission
     */
    public static function handle_form_submission() {
        if (!isset($_POST['smo_batch_nonce']) || !wp_verify_nonce($_POST['smo_batch_nonce'], 'smo_batch_config')) {
            return ['success' => false, 'error' => 'Invalid nonce'];
        }

        if (!current_user_can('manage_options')) {
            return ['success' => false, 'error' => 'Insufficient permissions'];
        }

        $config_data = $_POST['config'] ?? [];
        
        if (empty($config_data)) {
            return ['success' => false, 'error' => 'No configuration data provided'];
        }

        return self::process_batch_config($config_data);
    }

    /**
     * Validate current configuration
     */
    public static function validate_configuration() {
        $validation_results = [];
        $configured_count = 0;
        $total_count = 0;

        foreach (self::$config_mapping as $service_id => $mapping) {
            $service_results = [];
            $service_configured = true;

            foreach ($mapping['options'] as $index => $option_name) {
                $field_name = $mapping['fields'][$index];
                $value = get_option($option_name);
                $is_configured = !empty($value);

                $service_results[] = [
                    'field' => $field_name,
                    'option' => $option_name,
                    'configured' => $is_configured,
                    'current_value' => $is_configured ? 'Set' : 'Not Set'
                ];

                if (!$is_configured) {
                    $service_configured = false;
                }

                $total_count++;
                if ($is_configured) {
                    $configured_count++;
                }
            }

            $validation_results[$service_id] = [
                'service' => $service_id,
                'configured' => $service_configured,
                'completion_rate' => round((count(array_filter($service_results, function($r) { return $r['configured']; })) / count($service_results)) * 100, 2),
                'fields' => $service_results
            ];
        }

        $overall_completion = $total_count > 0 ? round(($configured_count / $total_count) * 100, 2) : 0;

        return [
            'validation_results' => $validation_results,
            'summary' => [
                'overall_completion' => $overall_completion,
                'services_configured' => count(array_filter($validation_results, function($r) { return $r['configured']; })),
                'total_services' => count($validation_results),
                'total_fields_configured' => $configured_count,
                'total_fields' => $total_count
            ]
        ];
    }
}

// AJAX Handlers
add_action('wp_ajax_smo_batch_config', 'smo_handle_batch_config');
add_action('wp_ajax_smo_validate_batch_config', 'smo_handle_batch_validation');
add_action('wp_ajax_smo_clear_batch_config', 'smo_handle_batch_clear');

function smo_handle_batch_config() {
    check_ajax_referer('smo_batch_config', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $result = SMOBatchConfigurator::handle_form_submission();
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

function smo_handle_batch_validation() {
    check_ajax_referer('smo_batch_validation', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $validation = SMOBatchConfigurator::validate_configuration();
    wp_send_json_success($validation);
}

function smo_handle_batch_clear() {
    check_ajax_referer('smo_batch_clear', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $cleared_count = 0;
    $total_count = 0;

    foreach (SMOBatchConfigurator::$config_mapping as $service_id => $mapping) {
        foreach ($mapping['options'] as $option_name) {
            $total_count++;
            $deleted = delete_option($option_name);
            if ($deleted) {
                $cleared_count++;
            }
        }
    }

    wp_send_json_success([
        'message' => "Cleared {$cleared_count} of {$total_count} configuration options",
        'cleared_count' => $cleared_count,
        'total_count' => $total_count
    ]);
}

/**
 * Add batch configuration to admin menu
 */
function smo_add_batch_config_admin_page() {
    add_submenu_page(
        'smo-social',
        'Batch Configuration',
        '🔧 Batch Config',
        'manage_options',
        'smo-batch-config',
        'smo_render_batch_config_page'
    );
}
add_action('admin_menu', 'smo_add_batch_config_admin_page');

/**
 * Render batch configuration page
 */
function smo_render_batch_config_page() {
    ?>
    <div class="wrap">
        <h1>🔧 SMO Social - Batch API Configuration</h1>
        
        <div class="smo-config-overview">
            <h2>Configuration Overview</h2>
            <div id="smo-config-status">
                <p>Loading configuration status...</p>
            </div>
        </div>
        
        <div class="smo-batch-form-container">
            <?php echo SMOBatchConfigurator::generate_config_form(); ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Load configuration status
        loadConfigStatus();
        
        // Form submission
        $('#smo-batch-config-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=smo_batch_config&nonce=' + $('#smo_batch_nonce').val(),
                beforeSend: function() {
                    $('#smo-batch-config-form button[type="submit"]').prop('disabled', true).text('Configuring...');
                },
                success: function(response) {
                    if (response.success) {
                        alert('Configuration updated successfully!');
                        loadConfigStatus();
                    } else {
                        alert('Configuration failed: ' + (response.data.error || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Configuration request failed');
                },
                complete: function() {
                    $('#smo-batch-config-form button[type="submit"]').prop('disabled', false).text('🚀 Apply Configuration');
                }
            });
        });
        
        // Validate configuration
        $('#smo-validate-config').on('click', function() {
            validateConfiguration();
        });
        
        // Clear configuration
        $('#smo-clear-config').on('click', function() {
            if (confirm('Are you sure you want to clear all configuration values?')) {
                clearConfiguration();
            }
        });
    });
    
    function loadConfigStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_validate_batch_config',
                nonce: '<?php echo wp_create_nonce('smo_batch_validation'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayConfigStatus(response.data);
                }
            }
        });
    }
    
    function displayConfigStatus(data) {
        var summary = data.summary;
        var results = data.validation_results;
        
        var html = '<div class="smo-status-summary">';
        html += '<h3>Configuration Status</h3>';
        html += '<div class="smo-progress-bar">';
        html += '<div class="smo-progress-fill" style="width: ' + summary.overall_completion + '%"></div>';
        html += '</div>';
        html += '<p><strong>' + summary.services_configured + '</strong> of <strong>' + summary.total_services + '</strong> services configured (' + summary.overall_completion + '% complete)</p>';
        html += '</div>';
        
        html += '<div class="smo-service-status">';
        for (var service in results) {
            var serviceData = results[service];
            var icon = serviceData.configured ? '✅' : '❌';
            html += '<div class="smo-service-item ' + (serviceData.configured ? 'configured' : 'missing') + '">';
            html += icon + ' ' + service + ' (' + serviceData.completion_rate + '%)';
            html += '</div>';
        }
        html += '</div>';
        
        $('#smo-config-status').html(html);
    }
    
    function validateConfiguration() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_validate_batch_config',
                nonce: '<?php echo wp_create_nonce('smo_batch_validation'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Validation completed. Check the overview section for details.');
                    displayConfigStatus(response.data);
                }
            }
        });
    }
    
    function clearConfiguration() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_clear_batch_config',
                nonce: '<?php echo wp_create_nonce('smo_batch_clear'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Configuration cleared successfully!');
                    loadConfigStatus();
                    // Clear form fields
                    $('#smo-batch-config-form input[type="text"]').val('');
                }
            }
        });
    }
    </script>
    
    <style>
    .smo-batch-config-form {
        max-width: 800px;
        margin-top: 20px;
    }
    
    .smo-service-config {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .smo-field-group {
        margin-bottom: 15px;
    }
    
    .smo-field-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .smo-field-group input {
        width: 100%;
        max-width: 400px;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .smo-field-group small {
        display: block;
        color: #666;
        margin-top: 3px;
    }
    
    .smo-form-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }
    
    .smo-form-actions button {
        margin-right: 10px;
    }
    
    .smo-progress-bar {
        background: #e0e0e0;
        height: 20px;
        border-radius: 10px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .smo-progress-fill {
        background: linear-gradient(90deg, #4CAF50, #45a049);
        height: 100%;
        transition: width 0.3s ease;
    }
    
    .smo-service-status {
        margin-top: 15px;
    }
    
    .smo-service-item {
        padding: 5px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .smo-service-item.configured {
        color: #46b450;
    }
    
    .smo-service-item.missing {
        color: #dc3232;
    }
    
    .smo-config-overview {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    </style>
    <?php
}

/**
 * Create deployment validation function
 */
function smo_validate_deployment_readiness() {
    $validation = [
        'database_tables' => [
            'smo_integrations' => 'CREATE TABLE IF NOT EXISTS `smo_integrations`',
            'smo_imported_content' => 'CREATE TABLE IF NOT EXISTS `smo_imported_content`',
            'smo_integration_logs' => 'CREATE TABLE IF NOT EXISTS `smo_integration_logs`'
        ],
        'required_files' => [
            'includes/Integrations/IntegrationManager.php',
            'includes/Integrations/WebhooksHandler.php',
            'includes/Database/IntegrationSchema.php',
            'assets/js/smo-integrations.js',
            'assets/css/smo-integrations.css'
        ],
        'wordPress_hooks' => [
            'smo_get_integrations',
            'smo_connect_integration',
            'smo_disconnect_integration',
            'smo_test_integration'
        ],
        'required_options' => []
    ];
    
    // Check database tables
    global $wpdb;
    $tables_ok = true;
    foreach ($validation['database_tables'] as $table => $sql) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'");
        $validation['database_tables'][$table] = !empty($exists);
        if (!$exists) $tables_ok = false;
    }
    
    // Check required files
    foreach ($validation['required_files'] as $file) {
        $validation['required_files'][$file] = file_exists(plugin_dir_path(__FILE__) . $file);
    }
    
    // Check WordPress hooks
    foreach ($validation['wordPress_hooks'] as $hook) {
        $validation['wordPress_hooks'][$hook] = has_action("wp_ajax_{$hook}") !== false;
    }
    
    // Check critical options
    $validation['required_options']['smo_integrations_enabled'] = get_option('smo_integrations_enabled', false);
    
    return $validation;
}