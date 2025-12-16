<?php
/**
 * SMO Social - OAuth Flow Testing Framework
 * 
 * Comprehensive testing system for OAuth2 flows and validation
 * of all authentication mechanisms for SMO Social integrations.
 *
 * @package SMO_Social
 * @subpackage Testing
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OAuth Testing Framework
 */
class SMOOAuthTester {
    
    /**
     * OAuth2 services configuration
     */
    private static $oauth2_services = [
        'canva' => [
            'name' => 'Canva',
            'client_id_field' => 'smo_canva_client_id',
            'client_secret_field' => 'smo_canva_client_secret',
            'auth_url' => 'https://www.canva.com/api/oauth/authorize',
            'token_url' => 'https://www.canva.com/api/oauth/token',
            'scope' => 'content:read content:write',
            'test_endpoint' => 'https://www.canva.com/api/v1/me'
        ],
        'dropbox' => [
            'name' => 'Dropbox',
            'client_id_field' => 'smo_dropbox_app_key',
            'client_secret_field' => 'smo_dropbox_app_secret',
            'auth_url' => 'https://www.dropbox.com/oauth2/authorize',
            'token_url' => 'https://api.dropbox.com/oauth2/token',
            'scope' => 'files.content.read files.content.write',
            'test_endpoint' => 'https://api.dropboxapi.com/2/users/get_current_account'
        ],
        'google_drive' => [
            'name' => 'Google Drive',
            'client_id_field' => 'smo_google_client_id',
            'client_secret_field' => 'smo_google_client_secret',
            'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'test_endpoint' => 'https://www.googleapis.com/drive/v3/about?fields=user'
        ],
        'google_photos' => [
            'name' => 'Google Photos',
            'client_id_field' => 'smo_google_client_id',
            'client_secret_field' => 'smo_google_client_secret',
            'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'https://www.googleapis.com/auth/photoslibrary.readonly',
            'test_endpoint' => 'https://photoslibrary.googleapis.com/v1/libraries'
        ],
        'onedrive' => [
            'name' => 'OneDrive',
            'client_id_field' => 'smo_onedrive_client_id',
            'client_secret_field' => 'smo_onedrive_client_secret',
            'auth_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'scope' => 'Files.Read Files.ReadWrite',
            'test_endpoint' => 'https://graph.microsoft.com/v1.0/me'
        ],
        'feedly' => [
            'name' => 'Feedly',
            'client_id_field' => 'smo_feedly_client_id',
            'client_secret_field' => 'smo_feedly_client_secret',
            'auth_url' => 'https://cloud.feedly.com/v3/auth/auth',
            'token_url' => 'https://cloud.feedly.com/v3/auth/token',
            'scope' => 'https://cloud.feedly.com/subscriptions',
            'test_endpoint' => 'https://cloud.feedly.com/v3/profile'
        ],
        'pocket' => [
            'name' => 'Pocket',
            'client_id_field' => 'smo_pocket_consumer_key',
            'client_secret_field' => null,
            'auth_url' => 'https://getpocket.com/v3/oauth/authorize',
            'token_url' => 'https://getpocket.com/v3/oauth/authorize',
            'scope' => '',
            'test_endpoint' => 'https://getpocket.com/v3/get'
        ]
    ];

    /**
     * API Key services
     */
    private static $api_key_services = [
        'unsplash' => [
            'name' => 'Unsplash',
            'api_key_field' => 'smo_unsplash_access_token',
            'test_endpoint' => 'https://api.unsplash.com/me',
            'test_header' => 'Authorization'
        ],
        'pixabay' => [
            'name' => 'Pixabay',
            'api_key_field' => 'smo_pixabay_api_key',
            'test_endpoint' => 'https://pixabay.com/api/',
            'test_header' => 'X-Pixabay-API-Key'
        ]
    ];

    /**
     * Run comprehensive OAuth tests
     */
    public static function run_oauth_tests() {
        $results = [];
        $summary = [
            'total_tests' => 0,
            'passed_tests' => 0,
            'failed_tests' => 0,
            'warning_tests' => 0
        ];

        // Test OAuth2 services
        foreach (self::$oauth2_services as $service_id => $config) {
            $summary['total_tests']++;
            $test_result = self::test_oauth2_service($service_id, $config);
            $results['oauth2'][$service_id] = $test_result;
            
            if ($test_result['status'] === 'passed') {
                $summary['passed_tests']++;
            } elseif ($test_result['status'] === 'warning') {
                $summary['warning_tests']++;
            } else {
                $summary['failed_tests']++;
            }
        }

        // Test API Key services
        foreach (self::$api_key_services as $service_id => $config) {
            $summary['total_tests']++;
            $test_result = self::test_api_key_service($service_id, $config);
            $results['api_key'][$service_id] = $test_result;
            
            if ($test_result['status'] === 'passed') {
                $summary['passed_tests']++;
            } elseif ($test_result['status'] === 'warning') {
                $summary['warning_tests']++;
            } else {
                $summary['failed_tests']++;
            }
        }

        return [
            'results' => $results,
            'summary' => $summary
        ];
    }

    /**
     * Test OAuth2 service configuration
     */
    private static function test_oauth2_service($service_id, $config) {
        $result = [
            'service' => $service_id,
            'name' => $config['name'],
            'status' => 'failed',
            'details' => []
        ];

        // Check if credentials are configured
        $client_id = get_option($config['client_id_field']);
        $client_secret = get_option($config['client_secret_field']);

        if (empty($client_id) || empty($client_secret)) {
            $result['details'][] = '❌ Credentials not configured';
            $result['error'] = 'Missing client credentials';
            return $result;
        }

        $result['details'][] = '✅ Credentials configured';

        // Test authorization URL
        $auth_url = self::build_oauth_url($config['auth_url'], $config, $client_id);
        $result['details'][] = '🔗 OAuth URL: ' . $auth_url;

        // Check if token exists (already authenticated)
        $connections = get_option('smo_integration_connections', []);
        if (isset($connections[$service_id]['access_token'])) {
            $access_token = $connections[$service_id]['access_token'];
            
            // Test token validity
            $token_test = self::test_access_token($config['test_endpoint'], $access_token);
            if ($token_test['valid']) {
                $result['status'] = 'passed';
                $result['details'][] = '✅ Token is valid';
                $result['token_expires_at'] = $connections[$service_id]['expires_at'] ?? 'Unknown';
            } else {
                $result['status'] = 'warning';
                $result['details'][] = '⚠️ Token may be expired or invalid';
                $result['token_error'] = $token_test['error'];
            }
        } else {
            $result['status'] = 'warning';
            $result['details'][] = '⚠️ No active token found - OAuth flow needed';
            $result['next_step'] = 'Complete OAuth authorization';
        }

        return $result;
    }

    /**
     * Test API Key service configuration
     */
    private static function test_api_key_service($service_id, $config) {
        $result = [
            'service' => $service_id,
            'name' => $config['name'],
            'status' => 'failed',
            'details' => []
        ];

        // Check if API key is configured
        $api_key = get_option($config['api_key_field']);

        if (empty($api_key)) {
            $result['details'][] = '❌ API Key not configured';
            $result['error'] = 'Missing API key';
            return $result;
        }

        $result['details'][] = '✅ API Key configured';

        // Test API key validity
        $api_test = self::test_api_key($config['test_endpoint'], $api_key, $config['test_header']);
        if ($api_test['valid']) {
            $result['status'] = 'passed';
            $result['details'][] = '✅ API Key is valid';
        } else {
            $result['status'] = 'warning';
            $result['details'][] = '⚠️ API Key validation failed';
            $result['api_error'] = $api_test['error'];
        }

        return $result;
    }

    /**
     * Test access token validity
     */
    private static function test_access_token($endpoint, $token) {
        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => 'SMO-Social/1.0.0'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            return ['valid' => true];
        } else {
            return [
                'valid' => false,
                'error' => "HTTP {$status_code}: {$body}"
            ];
        }
    }

    /**
     * Test API key validity
     */
    private static function test_api_key($endpoint, $api_key, $header) {
        $response = wp_remote_get($endpoint, [
            'headers' => [
                $header => $api_key
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            return ['valid' => true];
        } else {
            return [
                'valid' => false,
                'error' => "HTTP {$status_code}: {$body}"
            ];
        }
    }

    /**
     * Build OAuth authorization URL
     */
    private static function build_oauth_url($auth_url, $config, $client_id) {
        $state = wp_generate_uuid4();
        $redirect_uri = admin_url('admin-ajax.php?action=smo_' . $config['name'] . '_oauth_callback');
        $redirect_uri = strtolower(str_replace(' ', '_', $redirect_uri));
        
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state
        ];

        return $auth_url . '?' . http_build_query($params);
    }

    /**
     * Generate OAuth test report
     */
    public static function generate_test_report($test_results) {
        $report = "<div class='smo-oauth-test-report'>\n";
        $report .= "<h2>🔍 OAuth Flow Testing Report</h2>\n";
        
        $summary = $test_results['summary'];
        $report .= "<div class='smo-test-summary'>\n";
        $report .= "<h3>📊 Test Summary</h3>\n";
        $report .= "<div class='smo-summary-stats'>\n";
        $report .= "<span class='smo-stat'>Total: {$summary['total_tests']}</span>\n";
        $report .= "<span class='smo-stat passed'>✅ Passed: {$summary['passed_tests']}</span>\n";
        $report .= "<span class='smo-stat warning'>⚠️ Warnings: {$summary['warning_tests']}</span>\n";
        $report .= "<span class='smo-stat failed'>❌ Failed: {$summary['failed_tests']}</span>\n";
        $report .= "</div>\n";
        $report .= "</div>\n";

        // OAuth2 Services Results
        if (isset($test_results['results']['oauth2'])) {
            $report .= "<h3>🔐 OAuth2 Services</h3>\n";
            foreach ($test_results['results']['oauth2'] as $service_id => $result) {
                $status_icon = $result['status'] === 'passed' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌');
                $report .= "<div class='smo-service-test' data-service='{$service_id}'>\n";
                $report .= "<h4>{$status_icon} {$result['name']}</h4>\n";
                $report .= "<div class='smo-test-details'>\n";
                foreach ($result['details'] as $detail) {
                    $report .= "<div class='smo-detail-line'>{$detail}</div>\n";
                }
                if (isset($result['next_step'])) {
                    $report .= "<div class='smo-next-step'>{$result['next_step']}</div>\n";
                }
                if (isset($result['error'])) {
                    $report .= "<div class='smo-error'>Error: {$result['error']}</div>\n";
                }
                $report .= "</div>\n";
                $report .= "</div>\n";
            }
        }

        // API Key Services Results
        if (isset($test_results['results']['api_key'])) {
            $report .= "<h3>🔑 API Key Services</h3>\n";
            foreach ($test_results['results']['api_key'] as $service_id => $result) {
                $status_icon = $result['status'] === 'passed' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌');
                $report .= "<div class='smo-service-test' data-service='{$service_id}'>\n";
                $report .= "<h4>{$status_icon} {$result['name']}</h4>\n";
                $report .= "<div class='smo-test-details'>\n";
                foreach ($result['details'] as $detail) {
                    $report .= "<div class='smo-detail-line'>{$detail}</div>\n";
                }
                if (isset($result['error'])) {
                    $report .= "<div class='smo-error'>Error: {$result['error']}</div>\n";
                }
                $report .= "</div>\n";
                $report .= "</div>\n";
            }
        }

        $report .= "</div>\n";
        return $report;
    }
}

// AJAX handler for OAuth tests
add_action('wp_ajax_smo_run_oauth_tests', 'smo_handle_oauth_tests');

function smo_handle_oauth_tests() {
    check_ajax_referer('smo_oauth_tests', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $test_results = SMOOAuthTester::run_oauth_tests();
    wp_send_json_success($test_results);
}

/**
 * Add OAuth testing admin page
 */
function smo_add_oauth_test_admin_page() {
    add_submenu_page(
        'smo-social',
        'OAuth Testing',
        '🧪 OAuth Tests',
        'manage_options',
        'smo-oauth-tests',
        'smo_render_oauth_test_page'
    );
}
add_action('admin_menu', 'smo_add_oauth_test_admin_page');

/**
 * Render OAuth testing page
 */
function smo_render_oauth_test_page() {
    ?>
    <div class="wrap">
        <h1>🧪 SMO Social - OAuth Flow Testing</h1>
        
        <div class="smo-test-controls">
            <button id="smo-run-oauth-tests" class="button button-primary">🚀 Run All OAuth Tests</button>
            <button id="smo-refresh-oauth-tests" class="button">🔄 Refresh Tests</button>
        </div>
        
        <div class="smo-test-results" id="smo-oauth-test-results">
            <p>Click "Run All OAuth Tests" to start testing...</p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#smo-run-oauth-tests').on('click', function() {
            runOAuthTests();
        });
        
        $('#smo-refresh-oauth-tests').on('click', function() {
            location.reload();
        });
    });
    
    function runOAuthTests() {
        $('#smo-run-oauth-tests').prop('disabled', true).text('Testing...');
        $('#smo-oauth-test-results').html('<div class="smo-loading"><p>Running OAuth tests...</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_run_oauth_tests',
                nonce: '<?php echo wp_create_nonce('smo_oauth_tests'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayTestResults(response.data);
                } else {
                    $('#smo-oauth-test-results').html('<div class="smo-error">Test failed: ' + (response.data.message || 'Unknown error') + '</div>');
                }
            },
            error: function() {
                $('#smo-oauth-test-results').html('<div class="smo-error">Request failed</div>');
            },
            complete: function() {
                $('#smo-run-oauth-tests').prop('disabled', false).text('🚀 Run All OAuth Tests');
            }
        });
    }
    
    function displayTestResults(results) {
        var summary = results.summary;
        var report = '<div class="smo-test-summary">' +
            '<h3>📊 Test Summary</h3>' +
            '<div class="smo-summary-stats">' +
            '<span class="smo-stat">Total: ' + summary.total_tests + '</span>' +
            '<span class="smo-stat passed">✅ Passed: ' + summary.passed_tests + '</span>' +
            '<span class="smo-stat warning">⚠️ Warnings: ' + summary.warning_tests + '</span>' +
            '<span class="smo-stat failed">❌ Failed: ' + summary.failed_tests + '</span>' +
            '</div>' +
            '</div>';
        
        // OAuth2 Results
        if (results.results.oauth2) {
            report += '<h3>🔐 OAuth2 Services</h3>';
            for (var service in results.results.oauth2) {
                var result = results.results.oauth2[service];
                var icon = result.status === 'passed' ? '✅' : (result.status === 'warning' ? '⚠️' : '❌');
                report += '<div class="smo-service-test">' +
                    '<h4>' + icon + ' ' + result.name + '</h4>' +
                    '<div class="smo-test-details">';
                for (var i = 0; i < result.details.length; i++) {
                    report += '<div class="smo-detail-line">' + result.details[i] + '</div>';
                }
                report += '</div></div>';
            }
        }
        
        // API Key Results
        if (results.results.api_key) {
            report += '<h3>🔑 API Key Services</h3>';
            for (var service in results.results.api_key) {
                var result = results.results.api_key[service];
                var icon = result.status === 'passed' ? '✅' : (result.status === 'warning' ? '⚠️' : '❌');
                report += '<div class="smo-service-test">' +
                    '<h4>' + icon + ' ' + result.name + '</h4>' +
                    '<div class="smo-test-details">';
                for (var i = 0; i < result.details.length; i++) {
                    report += '<div class="smo-detail-line">' + result.details[i] + '</div>';
                }
                report += '</div></div>';
            }
        }
        
        $('#smo-oauth-test-results').html(report);
    }
    </script>
    
    <style>
    .smo-test-controls {
        margin: 20px 0;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 8px;
    }
    
    .smo-test-results {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .smo-summary-stats {
        display: flex;
        gap: 15px;
        margin: 15px 0;
    }
    
    .smo-stat {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: bold;
    }
    
    .smo-stat.passed {
        background: #d4edda;
        color: #155724;
    }
    
    .smo-stat.warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .smo-stat.failed {
        background: #f8d7da;
        color: #721c24;
    }
    
    .smo-service-test {
        margin: 15px 0;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #fafafa;
    }
    
    .smo-detail-line {
        padding: 3px 0;
        font-family: monospace;
    }
    
    .smo-error {
        color: #dc3232;
        font-weight: bold;
        margin-top: 10px;
    }
    
    .smo-next-step {
        color: #0073aa;
        font-style: italic;
        margin-top: 8px;
    }
    </style>
    <?php
}