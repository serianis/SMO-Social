<?php
/**
 * SMO Social API Credentials Validation Script
 * Run this to validate all API credentials are configured
 */

function smo_validate_credentials() {
    $results = [];
    $required_options = [
        'smo_canva_client_id',
        'smo_canva_client_secret',
        'smo_unsplash_access_token',
        'smo_pixabay_api_key',
        'smo_dropbox_app_key',
        'smo_dropbox_app_secret',
        'smo_google_client_id',
        'smo_google_client_secret',
        'smo_google_client_id',
        'smo_google_client_secret',
        'smo_onedrive_client_id',
        'smo_onedrive_client_secret',
        'smo_zapier_webhook_secret',
        'smo_ifttt_webhook_secret',
        'smo_feedly_client_id',
        'smo_feedly_client_secret',
        'smo_pocket_consumer_key',
    ];

    foreach ($required_options as $option) {
        $value = get_option($option);
        $results[$option] = [
            'configured' => !empty($value),
            'value' => $value ? 'Set' : 'Not Set'
        ];
    }

    return $results;
}

// Run validation
$validation = smo_validate_credentials();
echo "SMO Social API Credentials Validation Results:\n";
echo "=========================================\n";
foreach ($validation as $option => $status) {
    $icon = $status['configured'] ? '✅' : '❌';
    echo "$icon $option: {$status['value']}\n";
}
