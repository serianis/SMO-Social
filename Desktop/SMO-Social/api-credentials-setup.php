<?php
/**
 * SMO Social - API Credentials Setup Guide
 * 
 * This script provides a comprehensive guide for configuring API credentials
 * for all SMO Social integrations in production.
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
 * API Credentials Setup Class
 */
class SMOCredentialsSetup {
    
    /**
     * Configuration guide for each service
     */
    public static $service_configs = [
        'canva' => [
            'name' => 'Canva',
            'type' => 'OAuth2',
            'required_fields' => [
                'client_id' => 'Canva OAuth2 Client ID',
                'client_secret' => 'Canva OAuth2 Client Secret'
            ],
            'setup_url' => 'https://www.canva.com/developers/apps/',
            'redirect_uri' => '',
            'documentation' => 'https://www.canva.com/developers/api/',
            'scopes' => ['content:read', 'content:write']
        ],
        'unsplash' => [
            'name' => 'Unsplash',
            'type' => 'API Key',
            'required_fields' => [
                'api_key' => 'Unsplash Access Key'
            ],
            'setup_url' => 'https://unsplash.com/developers',
            'redirect_uri' => '',
            'documentation' => 'https://unsplash.com/documentation',
            'scopes' => ['public']
        ],
        'pixabay' => [
            'name' => 'Pixabay',
            'type' => 'API Key',
            'required_fields' => [
                'api_key' => 'Pixabay API Key'
            ],
            'setup_url' => 'https://pixabay.com/api/docs/',
            'redirect_uri' => '',
            'documentation' => 'https://pixabay.com/api/docs/',
            'scopes' => []
        ],
        'dropbox' => [
            'name' => 'Dropbox',
            'type' => 'OAuth2',
            'required_fields' => [
                'app_key' => 'Dropbox App Key',
                'app_secret' => 'Dropbox App Secret'
            ],
            'setup_url' => 'https://www.dropbox.com/developers/apps',
            'redirect_uri' => '',
            'documentation' => 'https://www.dropbox.com/developers/documentation',
            'scopes' => ['files.content.read', 'files.content.write']
        ],
        'google_drive' => [
            'name' => 'Google Drive',
            'type' => 'OAuth2',
            'required_fields' => [
                'client_id' => 'Google OAuth2 Client ID',
                'client_secret' => 'Google OAuth2 Client Secret'
            ],
            'setup_url' => 'https://console.developers.google.com/',
            'redirect_uri' => '',
            'documentation' => 'https://developers.google.com/drive/api',
            'scopes' => ['https://www.googleapis.com/auth/drive.readonly']
        ],
        'google_photos' => [
            'name' => 'Google Photos',
            'type' => 'OAuth2',
            'required_fields' => [
                'client_id' => 'Google OAuth2 Client ID',
                'client_secret' => 'Google OAuth2 Client Secret'
            ],
            'setup_url' => 'https://console.developers.google.com/',
            'redirect_uri' => '',
            'documentation' => 'https://developers.google.com/photos/library/api',
            'scopes' => ['https://www.googleapis.com/auth/photoslibrary.readonly']
        ],
        'onedrive' => [
            'name' => 'OneDrive',
            'type' => 'OAuth2',
            'required_fields' => [
                'client_id' => 'Microsoft App Client ID',
                'client_secret' => 'Microsoft App Client Secret'
            ],
            'setup_url' => 'https://portal.azure.com/',
            'redirect_uri' => '',
            'documentation' => 'https://docs.microsoft.com/en-us/onedrive/developer/rest-api/',
            'scopes' => ['Files.Read', 'Files.ReadWrite']
        ],
        'zapier' => [
            'name' => 'Zapier',
            'type' => 'Webhook',
            'required_fields' => [
                'webhook_secret' => 'Zapier Webhook Secret'
            ],
            'setup_url' => 'https://zapier.com/developer/documentation/',
            'redirect_uri' => '',
            'documentation' => 'https://zapier.com/developer/documentation/',
            'scopes' => []
        ],
        'ifttt' => [
            'name' => 'IFTTT',
            'type' => 'Webhook',
            'required_fields' => [
                'webhook_secret' => 'IFTTT Webhook Secret'
            ],
            'setup_url' => 'https://ifttt.com/maker_webhooks',
            'redirect_uri' => '',
            'documentation' => 'https://ifttt.com/maker_webhooks',
            'scopes' => []
        ],
        'feedly' => [
            'name' => 'Feedly',
            'type' => 'OAuth2',
            'required_fields' => [
                'client_id' => 'Feedly OAuth2 Client ID',
                'client_secret' => 'Feedly OAuth2 Client Secret'
            ],
            'setup_url' => 'https://developer.feedly.com/',
            'redirect_uri' => '',
            'documentation' => 'https://developer.feedly.com/docs/',
            'scopes' => ['https://cloud.feedly.com/subscriptions']
        ],
        'pocket' => [
            'name' => 'Pocket',
            'type' => 'OAuth2',
            'required_fields' => [
                'consumer_key' => 'Pocket Consumer Key'
            ],
            'setup_url' => 'https://getpocket.com/developer/',
            'redirect_uri' => '',
            'documentation' => 'https://getpocket.com/developer/docs/',
            'scopes' => []
        ]
    ];

    /**
     * Generate setup guide
     */
    public static function generate_setup_guide() {
        $guide = "<!-- SMO Social API Credentials Setup Guide -->\n";
        $guide .= "<div class='smo-credentials-guide'>\n";
        $guide .= "<h2>🔑 SMO Social API Credentials Setup</h2>\n";
        $guide .= "<p>Follow these steps to configure API credentials for all integrations:</p>\n";
        
        foreach (self::$service_configs as $service_id => $config) {
            $guide .= self::generate_service_guide($service_id, $config);
        }
        
        $guide .= "</div>\n";
        
        return $guide;
    }
    
    /**
     * Generate service-specific guide
     */
    private static function generate_service_guide($service_id, $config) {
        $guide = "\n<!-- {$config['name']} Setup Guide -->\n";
        $guide .= "<div class='smo-service-guide' data-service='{$service_id}'>\n";
        $guide .= "<h3>📌 {$config['name']} Configuration</h3>\n";
        $guide .= "<div class='smo-setup-steps'>\n";
        
        // Step 1: Visit developer console
        $guide .= "<div class='smo-step'>\n";
        $guide .= "<h4>Step 1: Register Application</h4>\n";
        $guide .= "<p>Visit the developer console and create an application:</p>\n";
        $guide .= "<p><a href='{$config['setup_url']}' target='_blank' class='smo-btn-link'>🔗 {$config['setup_url']}</a></p>\n";
        $guide .= "</div>\n";
        
        // Step 2: Configure redirect URI
        $redirect_uri = admin_url('admin-ajax.php?action=smo_' . str_replace('_', '_', $service_id) . '_oauth_callback');
        $guide .= "<div class='smo-step'>\n";
        $guide .= "<h4>Step 2: Configure Redirect URI</h4>\n";
        $guide .= "<p>Add this redirect URI to your application settings:</p>\n";
        $guide .= "<div class='smo-redirect-uri'>{$redirect_uri}</div>\n";
        $guide .= "</div>\n";
        
        // Step 3: Required fields
        $guide .= "<div class='smo-step'>\n";
        $guide .= "<h4>Step 3: Get Credentials</h4>\n";
        $guide .= "<p>Obtain the following credentials from your application:</p>\n";
        $guide .= "<ul>\n";
        foreach ($config['required_fields'] as $field => $label) {
            $wp_option = self::get_wp_option_name($service_id, $field);
            $guide .= "<li><strong>{$label}:</strong> <code>{$wp_option}</code></li>\n";
        }
        $guide .= "</ul>\n";
        $guide .= "</div>\n";
        
        // Step 4: Configure WordPress
        $guide .= "<div class='smo-step'>\n";
        $guide .= "<h4>Step 4: Configure WordPress</h4>\n";
        $guide .= "<p>Add credentials in WordPress admin or use the setup script:</p>\n";
        $guide .= "<div class='smo-wp-config'>\n";
        foreach ($config['required_fields'] as $field => $label) {
            $wp_option = self::get_wp_option_name($service_id, $field);
            $guide .= "<code>update_option('{$wp_option}', 'YOUR_{$field}_HERE');</code><br>\n";
        }
        $guide .= "</div>\n";
        $guide .= "</div>\n";
        
        $guide .= "</div>\n"; // .smo-setup-steps
        $guide .= "</div>\n"; // .smo-service-guide
        
        return $guide;
    }
    
    /**
     * Get WordPress option name for a service field
     */
    public static function get_wp_option_name($service_id, $field) {
        $option_mapping = [
            'canva' => [
                'client_id' => 'smo_canva_client_id',
                'client_secret' => 'smo_canva_client_secret'
            ],
            'unsplash' => [
                'api_key' => 'smo_unsplash_access_token'
            ],
            'pixabay' => [
                'api_key' => 'smo_pixabay_api_key'
            ],
            'dropbox' => [
                'app_key' => 'smo_dropbox_app_key',
                'app_secret' => 'smo_dropbox_app_secret'
            ],
            'google_drive' => [
                'client_id' => 'smo_google_client_id',
                'client_secret' => 'smo_google_client_secret'
            ],
            'google_photos' => [
                'client_id' => 'smo_google_client_id',
                'client_secret' => 'smo_google_client_secret'
            ],
            'onedrive' => [
                'client_id' => 'smo_onedrive_client_id',
                'client_secret' => 'smo_onedrive_client_secret'
            ],
            'zapier' => [
                'webhook_secret' => 'smo_zapier_webhook_secret'
            ],
            'ifttt' => [
                'webhook_secret' => 'smo_ifttt_webhook_secret'
            ],
            'feedly' => [
                'client_id' => 'smo_feedly_client_id',
                'client_secret' => 'smo_feedly_client_secret'
            ],
            'pocket' => [
                'consumer_key' => 'smo_pocket_consumer_key'
            ]
        ];
        
        return $option_mapping[$service_id][$field] ?? "smo_{$service_id}_{$field}";
    }
    
    /**
     * Generate WordPress configuration helper
     */
    public static function generate_wp_config() {
        $config = "<?php\n";
        $config .= "/**\n";
        $config .= " * SMO Social API Credentials Configuration\n";
        $config .= " * Add these values to your wp-config.php file\n";
        $config .= " */\n\n";
        
        foreach (self::$service_configs as $service_id => $service_config) {
            $config .= "// {$service_config['name']} Credentials\n";
            foreach ($service_config['required_fields'] as $field => $label) {
                $option_name = self::get_wp_option_name($service_id, $field);
                $config .= "define('SMO_{$service_id}_{$field}', 'YOUR_{$field}_HERE');\n";
                $config .= "// WordPress Option: {$option_name}\n";
            }
            $config .= "\n";
        }
        
        return $config;
    }
    
    /**
     * Generate setup validation script
     */
    public static function generate_validation_script() {
        $script = "<?php\n";
        $script .= "/**\n";
        $script .= " * SMO Social API Credentials Validation Script\n";
        $script .= " * Run this to validate all API credentials are configured\n";
        $script .= " */\n\n";
        
        $script .= "function smo_validate_credentials() {\n";
        $script .= "    \$results = [];\n";
        $script .= "    \$required_options = [\n";
        
        foreach (self::$service_configs as $service_id => $config) {
            foreach ($config['required_fields'] as $field => $label) {
                $option_name = self::get_wp_option_name($service_id, $field);
                $script .= "        '{$option_name}',\n";
            }
        }
        
        $script .= "    ];\n\n";
        $script .= "    foreach (\$required_options as \$option) {\n";
        $script .= "        \$value = get_option(\$option);\n";
        $script .= "        \$results[\$option] = [\n";
        $script .= "            'configured' => !empty(\$value),\n";
        $script .= "            'value' => \$value ? 'Set' : 'Not Set'\n";
        $script .= "        ];\n";
        $script .= "    }\n\n";
        $script .= "    return \$results;\n";
        $script .= "}\n\n";
        $script .= "// Run validation\n";
        $script .= "\$validation = smo_validate_credentials();\n";
        $script .= "echo \"SMO Social API Credentials Validation Results:\\n\";\n";
        $script .= "echo \"=========================================\\n\";\n";
        $script .= "foreach (\$validation as \$option => \$status) {\n";
        $script .= "    \$icon = \$status['configured'] ? '✅' : '❌';\n";
        $script .= "    echo \"\$icon \$option: {\$status['value']}\\n\";\n";
        $script .= "}\n";
        
        return $script;
    }
}

// Generate and output the setup guide
if (isset($_GET['smo_setup_guide'])) {
    echo SMOCredentialsSetup::generate_setup_guide();
    exit;
}

if (isset($_GET['smo_wp_config'])) {
    header('Content-Type: text/plain');
    echo SMOCredentialsSetup::generate_wp_config();
    exit;
}

if (isset($_GET['smo_validation_script'])) {
    header('Content-Type: text/plain');
    echo SMOCredentialsSetup::generate_validation_script();
    exit;
}

/**
 * Create the setup guide in WordPress admin
 */
function smo_create_credentials_admin_page() {
    add_submenu_page(
        'smo-social',
        'API Credentials Setup',
        '🔑 API Setup',
        'manage_options',
        'smo-api-credentials',
        'smo_render_credentials_setup_page'
    );
}
add_action('admin_menu', 'smo_create_credentials_admin_page');

/**
 * Render the credentials setup page
 */
function smo_render_credentials_setup_page() {
    ?>
    <div class="wrap">
        <h1>🔑 SMO Social API Credentials Setup</h1>
        
        <div class="smo-setup-summary">
            <h2>Setup Summary</h2>
            <p>Configure API credentials for all SMO Social integrations. Each integration requires specific API credentials from their respective developer platforms.</p>
            
            <div class="smo-quick-actions">
                <h3>Quick Actions</h3>
                <p>
                    <a href="?smo_setup_guide=1" class="button button-primary" target="_blank">📋 View Detailed Setup Guide</a>
                    <a href="?smo_wp_config=1" class="button" target="_blank">📝 Generate wp-config.php Config</a>
                    <a href="?smo_validation_script=1" class="button" target="_blank">✅ Generate Validation Script</a>
                </p>
            </div>
        </div>
        
        <div class="smo-services-overview">
            <h2>Service Overview</h2>
            <div class="smo-service-grid">
                <?php foreach (SMOCredentialsSetup::$service_configs as $service_id => $config): ?>
                    <div class="smo-service-card">
                        <h3><?php echo esc_html($config['name']); ?></h3>
                        <p class="smo-service-type">Type: <?php echo esc_html($config['type']); ?></p>
                        
                        <div class="smo-required-fields">
                            <h4>Required Fields:</h4>
                            <ul>
                                <?php foreach ($config['required_fields'] as $field => $label): ?>
                                    <?php $option_name = SMOCredentialsSetup::get_wp_option_name($service_id, $field); ?>
                                    <?php $current_value = get_option($option_name); ?>
                                    <li>
                                        <strong><?php echo esc_html($label); ?></strong>
                                        <code><?php echo esc_html($option_name); ?></code>
                                        <span class="smo-status <?php echo !empty($current_value) ? 'configured' : 'missing'; ?>">
                                            <?php echo !empty($current_value) ? '✅ Configured' : '❌ Missing'; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="smo-setup-links">
                            <a href="<?php echo esc_url($config['setup_url']); ?>" target="_blank" class="button button-small">🔗 Developer Console</a>
                            <a href="<?php echo esc_url($config['documentation']); ?>" target="_blank" class="button button-small">📚 Documentation</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <style>
    .smo-service-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .smo-service-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .smo-service-type {
        color: #666;
        font-size: 14px;
        margin-bottom: 15px;
    }
    
    .smo-required-fields ul {
        list-style: none;
        padding: 0;
        margin: 10px 0;
    }
    
    .smo-required-fields li {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .smo-status.configured {
        color: #46b450;
        font-weight: bold;
    }
    
    .smo-status.missing {
        color: #dc3232;
        font-weight: bold;
    }
    
    .smo-setup-links {
        margin-top: 15px;
    }
    
    .smo-quick-actions {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
    }
    </style>
    <?php
}