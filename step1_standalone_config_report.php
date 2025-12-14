<?php
/**
 * Standalone Batch API Credentials Configuration Report
 * This script shows the configuration process and generates templates
 * without requiring WordPress environment
 */

echo "=== SMO Social - Batch API Credentials Setup Report ===\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

// Service configurations that need to be set up
$service_configs = [
    'canva' => [
        'name' => 'Canva',
        'type' => 'OAuth2',
        'required_fields' => [
            'client_id' => 'Canva OAuth2 Client ID',
            'client_secret' => 'Canva OAuth2 Client Secret'
        ],
        'setup_url' => 'https://www.canva.com/developers/apps/',
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
        'documentation' => 'https://getpocket.com/developer/docs/',
        'scopes' => []
    ]
];

// Option name mapping
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

echo "🔧 SERVICES TO CONFIGURE:\n";
echo "=========================\n\n";

foreach ($service_configs as $service_id => $config) {
    echo "📌 {$config['name']} ({$config['type']})\n";
    echo "   Setup URL: {$config['setup_url']}\n";
    echo "   Documentation: {$config['documentation']}\n";
    echo "   Required Fields:\n";
    
    foreach ($config['required_fields'] as $field => $label) {
        $option_name = $option_mapping[$service_id][$field] ?? "smo_{$service_id}_{$field}";
        echo "   - {$label}: {$option_name}\n";
    }
    echo "\n";
}

// Generate wp-config.php template
echo "📝 GENERATING wp-config.php TEMPLATE:\n";
echo "====================================\n";

$wp_config = "<?php\n";
$wp_config .= "/**\n";
$wp_config .= " * SMO Social API Credentials Configuration\n";
$wp_config .= " * Add these values to your wp-config.php file\n";
$wp_config .= " */\n\n";

foreach ($service_configs as $service_id => $service_config) {
    $wp_config .= "// {$service_config['name']} Credentials\n";
    foreach ($service_config['required_fields'] as $field => $label) {
        $option_name = $option_mapping[$service_id][$field] ?? "smo_{$service_id}_{$field}";
        $wp_config .= "define('SMO_{$service_id}_{$field}', 'YOUR_{$field}_HERE');\n";
        $wp_config .= "// WordPress Option: {$option_name}\n";
    }
    $wp_config .= "\n";
}

file_put_contents('generated_wp_config_template.php', $wp_config);
echo "✅ wp-config.php template saved to: generated_wp_config_template.php\n\n";

// Generate validation script
echo "🧪 GENERATING VALIDATION SCRIPT:\n";
echo "================================\n";

$validation_script = "<?php\n";
$validation_script .= "/**\n";
$validation_script .= " * SMO Social API Credentials Validation Script\n";
$validation_script .= " * Run this to validate all API credentials are configured\n";
$validation_script .= " */\n\n";

$validation_script .= "function smo_validate_credentials() {\n";
$validation_script .= "    \$results = [];\n";
$validation_script .= "    \$required_options = [\n";

foreach ($service_configs as $service_id => $config) {
    foreach ($config['required_fields'] as $field => $label) {
        $option_name = $option_mapping[$service_id][$field] ?? "smo_{$service_id}_{$field}";
        $validation_script .= "        '{$option_name}',\n";
    }
}

$validation_script .= "    ];\n\n";
$validation_script .= "    foreach (\$required_options as \$option) {\n";
$validation_script .= "        \$value = get_option(\$option);\n";
$validation_script .= "        \$results[\$option] = [\n";
$validation_script .= "            'configured' => !empty(\$value),\n";
$validation_script .= "            'value' => \$value ? 'Set' : 'Not Set'\n";
$validation_script .= "        ];\n";
$validation_script .= "    }\n\n";
$validation_script .= "    return \$results;\n";
$validation_script .= "}\n\n";
$validation_script .= "// Run validation\n";
$validation_script .= "\$validation = smo_validate_credentials();\n";
$validation_script .= "echo \"SMO Social API Credentials Validation Results:\\n\";\n";
$validation_script .= "echo \"=========================================\\n\";\n";
$validation_script .= "foreach (\$validation as \$option => \$status) {\n";
$validation_script .= "    \$icon = \$status['configured'] ? '✅' : '❌';\n";
$validation_script .= "    echo \"\$icon \$option: {\$status['value']}\\n\";\n";
$validation_script .= "}\n";

file_put_contents('credentials_validation_script.php', $validation_script);
echo "✅ Validation script saved to: credentials_validation_script.php\n\n";

// Generate setup guide
echo "📋 GENERATING SETUP GUIDE:\n";
echo "==========================\n";

$setup_guide = "<!DOCTYPE html>\n";
$setup_guide .= "<html>\n<head>\n";
$setup_guide .= "<title>SMO Social API Credentials Setup Guide</title>\n";
$setup_guide .= "<style>\n";
$setup_guide .= "body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }\n";
$setup_guide .= ".service-guide { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }\n";
$setup_guide .= ".step { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px; }\n";
$setup_guide .= "code { background: #f1f1f1; padding: 2px 4px; border-radius: 3px; }\n";
$setup_guide .= ".btn { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }\n";
$setup_guide .= "</style>\n";
$setup_guide .= "</head>\n<body>\n";

$setup_guide .= "<h1>🔑 SMO Social API Credentials Setup Guide</h1>\n";
$setup_guide .= "<p>Follow these steps to configure API credentials for all SMO Social integrations:</p>\n";

foreach ($service_configs as $service_id => $config) {
    $setup_guide .= "<div class='service-guide'>\n";
    $setup_guide .= "<h2>📌 {$config['name']} Configuration</h2>\n";
    
    $setup_guide .= "<div class='step'>\n";
    $setup_guide .= "<h3>Step 1: Register Application</h3>\n";
    $setup_guide .= "<p>Visit the developer console and create an application:</p>\n";
    $setup_guide .= "<a href='{$config['setup_url']}' target='_blank' class='btn'>🔗 Open Developer Console</a>\n";
    $setup_guide .= "</div>\n";
    
    $setup_guide .= "<div class='step'>\n";
    $setup_guide .= "<h3>Step 2: Get Credentials</h3>\n";
    $setup_guide .= "<p>Obtain the following credentials from your application:</p>\n";
    $setup_guide .= "<ul>\n";
    foreach ($config['required_fields'] as $field => $label) {
        $option_name = $option_mapping[$service_id][$field] ?? "smo_{$service_id}_{$field}";
        $setup_guide .= "<li><strong>{$label}:</strong> <code>{$option_name}</code></li>\n";
    }
    $setup_guide .= "</ul>\n";
    $setup_guide .= "</div>\n";
    
    $setup_guide .= "</div>\n";
}

$setup_guide .= "<h2>🚀 Next Steps</h2>\n";
$setup_guide .= "<ol>\n";
$setup_guide .= "<li>Configure credentials in WordPress admin (SMO Social → Batch Config)</li>\n";
$setup_guide .= "<li>Or add credentials directly to wp-config.php using the generated template</li>\n";
$setup_guide .= "<li>Run the validation script to verify configuration</li>\n";
$setup_guide .= "<li>Proceed to OAuth testing</li>\n";
$setup_guide .= "</ol>\n";

$setup_guide .= "</body>\n</html>\n";

file_put_contents('api_credentials_setup_guide.html', $setup_guide);
echo "✅ Setup guide saved to: api_credentials_setup_guide.html\n\n";

echo "🚀 CONFIGURATION COMPLETE!\n";
echo "===========================\n\n";

echo "📁 Generated Files:\n";
echo "- generated_wp_config_template.php\n";
echo "- credentials_validation_script.php\n";
echo "- api_credentials_setup_guide.html\n\n";

echo "📋 Next Actions:\n";
echo "1. Review the generated wp-config.php template\n";
echo "2. Obtain API credentials from each service provider\n";
echo "3. Configure credentials in WordPress admin or via wp-config.php\n";
echo "4. Run the validation script to verify configuration\n";
echo "5. Proceed to Step 2: OAuth Testing\n\n";

echo "🔗 WordPress Admin Pages (when plugin is active):\n";
echo "- Batch Configuration: /wp-admin/admin.php?page=smo-batch-config\n";
echo "- API Credentials Setup: /wp-admin/admin.php?page=smo-api-credentials\n";
echo "- OAuth Tests: /wp-admin/admin.php?page=smo-oauth-tests\n";
echo "- Webhook Tests: /wp-admin/admin.php?page=smo-webhook-tests\n";
echo "- Performance & Security: /wp-admin/admin.php?page=smo-performance-security\n";
?>