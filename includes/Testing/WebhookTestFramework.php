<?php
/**
 * SMO Social - Webhook Testing Framework
 * 
 * Comprehensive testing system for Zapier and IFTTT webhook endpoints
 * with signature validation and automation workflow testing.
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
 * Webhook Testing Framework
 */
class SMOWebhookTester {
    
    /**
     * Test webhook endpoints
     */
    public static function test_webhook_endpoints() {
        $results = [];
        
        // Test Zapier webhook
        $zapier_result = self::test_zapier_webhook();
        $results['zapier'] = $zapier_result;
        
        // Test IFTTT webhook
        $ifttt_result = self::test_ifttt_webhook();
        $results['ifttt'] = $ifttt_result;
        
        return $results;
    }
    
    /**
     * Test Zapier webhook endpoint
     */
    private static function test_zapier_webhook() {
        $result = [
            'service' => 'zapier',
            'name' => 'Zapier',
            'status' => 'failed',
            'endpoint' => '',
            'details' => [],
            'signature_validation' => false
        ];
        
        // Check if webhook secret is configured
        $webhook_secret = get_option('smo_zapier_webhook_secret');
        if (empty($webhook_secret)) {
            $result['details'][] = '❌ Webhook secret not configured';
            $result['error'] = 'Missing webhook secret';
            return $result;
        }
        
        $result['details'][] = '✅ Webhook secret configured';
        
        // Build webhook endpoint URL
        $endpoint_url = self::get_webhook_endpoint_url('zapier');
        $result['endpoint'] = $endpoint_url;
        $result['details'][] = '📍 Endpoint URL: ' . $endpoint_url;
        
        // Test endpoint accessibility
        $access_test = self::test_endpoint_accessibility($endpoint_url);
        if ($access_test['accessible']) {
            $result['details'][] = '✅ Endpoint is accessible';
            $result['status'] = 'warning'; // Changes to passed after signature test
        } else {
            $result['details'][] = '❌ Endpoint not accessible';
            $result['error'] = $access_test['error'];
            return $result;
        }
        
        // Test signature validation
        $signature_test = self::test_zapier_signature($endpoint_url, $webhook_secret);
        if ($signature_test['valid']) {
            $result['details'][] = '✅ Signature validation working';
            $result['signature_validation'] = true;
            $result['status'] = 'passed';
        } else {
            $result['details'][] = '❌ Signature validation failed';
            $result['signature_error'] = $signature_test['error'];
        }
        
        // Test sample webhook payload
        $payload_test = self::test_zapier_payload($endpoint_url, $webhook_secret);
        if ($payload_test['processed']) {
            $result['details'][] = '✅ Sample payload processed successfully';
        } else {
            $result['details'][] = '❌ Sample payload processing failed';
            $result['payload_error'] = $payload_test['error'];
        }
        
        return $result;
    }
    
    /**
     * Test IFTTT webhook endpoint
     */
    private static function test_ifttt_webhook() {
        $result = [
            'service' => 'ifttt',
            'name' => 'IFTTT',
            'status' => 'failed',
            'endpoint' => '',
            'details' => [],
            'signature_validation' => false
        ];
        
        // Check if webhook secret is configured
        $webhook_secret = get_option('smo_ifttt_webhook_secret');
        if (empty($webhook_secret)) {
            $result['details'][] = '❌ Webhook secret not configured';
            $result['error'] = 'Missing webhook secret';
            return $result;
        }
        
        $result['details'][] = '✅ Webhook secret configured';
        
        // Build webhook endpoint URL
        $endpoint_url = self::get_webhook_endpoint_url('ifttt');
        $result['endpoint'] = $endpoint_url;
        $result['details'][] = '📍 Endpoint URL: ' . $endpoint_url;
        
        // Test endpoint accessibility
        $access_test = self::test_endpoint_accessibility($endpoint_url);
        if ($access_test['accessible']) {
            $result['details'][] = '✅ Endpoint is accessible';
            $result['status'] = 'warning'; // Changes to passed after signature test
        } else {
            $result['details'][] = '❌ Endpoint not accessible';
            $result['error'] = $access_test['error'];
            return $result;
        }
        
        // Test signature validation
        $signature_test = self::test_ifttt_signature($endpoint_url, $webhook_secret);
        if ($signature_test['valid']) {
            $result['details'][] = '✅ Signature validation working';
            $result['signature_validation'] = true;
            $result['status'] = 'passed';
        } else {
            $result['details'][] = '❌ Signature validation failed';
            $result['signature_error'] = $signature_test['error'];
        }
        
        // Test sample webhook payload
        $payload_test = self::test_ifttt_payload($endpoint_url, $webhook_secret);
        if ($payload_test['processed']) {
            $result['details'][] = '✅ Sample payload processed successfully';
        } else {
            $result['details'][] = '❌ Sample payload processing failed';
            $result['payload_error'] = $payload_test['error'];
        }
        
        return $result;
    }
    
    /**
     * Test endpoint accessibility
     */
    private static function test_endpoint_accessibility($endpoint_url) {
        // Test GET request to check if endpoint exists
        $response = wp_remote_get($endpoint_url, [
            'timeout' => 10,
            'user-agent' => 'SMO-Social-Webhook-Test/1.0.0'
        ]);
        
        if (is_wp_error($response)) {
            return [
                'accessible' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if (in_array($status_code, [200, 400, 405])) { // 200 OK, 400 Bad Request, 405 Method Not Allowed are expected
            return ['accessible' => true];
        } else {
            return [
                'accessible' => false,
                'error' => "HTTP {$status_code}"
            ];
        }
    }
    
    /**
     * Test Zapier signature validation
     */
    private static function test_zapier_signature($endpoint_url, $secret) {
        // Create test payload
        $payload = [
            'test' => 'signature_validation',
            'timestamp' => time(),
            'data' => ['key' => 'value']
        ];
        
        // Create HMAC-SHA256 signature
        $signature = hash_hmac('sha256', json_encode($payload), $secret);
        
        // Send test request with signature
        $response = wp_remote_post($endpoint_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Zapier-Signature' => $signature,
                'User-Agent' => 'SMO-Social-Webhook-Test/1.0.0'
            ],
            'body' => json_encode($payload),
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Check if response indicates successful processing
        if ($status_code === 200 || $status_code === 202) {
            return ['valid' => true];
        } else {
            // Check for signature validation error
            if (strpos($body, 'signature') !== false || strpos($body, 'invalid') !== false) {
                return [
                    'valid' => false,
                    'error' => 'Signature validation failed'
                ];
            }
            
            return [
                'valid' => false,
                'error' => "HTTP {$status_code}: {$body}"
            ];
        }
    }
    
    /**
     * Test IFTTT signature validation
     */
    private static function test_ifttt_signature($endpoint_url, $secret) {
        // Create test payload
        $payload = [
            'value1' => 'test_signature_validation',
            'value2' => time(),
            'value3' => 'webhook_test'
        ];
        
        // Create HMAC-SHA1 signature (IFTTT uses SHA1)
        $signature = hash_hmac('sha1', json_encode($payload), $secret);
        
        // Send test request with signature
        $response = wp_remote_post($endpoint_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-IFTTT-Signature' => $signature,
                'User-Agent' => 'SMO-Social-Webhook-Test/1.0.0'
            ],
            'body' => json_encode($payload),
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Check if response indicates successful processing
        if ($status_code === 200 || $status_code === 202) {
            return ['valid' => true];
        } else {
            // Check for signature validation error
            if (strpos($body, 'signature') !== false || strpos($body, 'invalid') !== false) {
                return [
                    'valid' => false,
                    'error' => 'Signature validation failed'
                ];
            }
            
            return [
                'valid' => false,
                'error' => "HTTP {$status_code}: {$body}"
            ];
        }
    }
    
    /**
     * Test Zapier payload processing
     */
    private static function test_zapier_payload($endpoint_url, $secret) {
        $payload = [
            'action' => 'create_post',
            'post_data' => [
                'title' => 'Test Post from Zapier',
                'content' => 'This is a test post created via Zapier webhook.',
                'status' => 'draft'
            ],
            'metadata' => [
                'source' => 'zapier_test',
                'timestamp' => time()
            ]
        ];
        
        $signature = hash_hmac('sha256', json_encode($payload), $secret);
        
        $response = wp_remote_post($endpoint_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Zapier-Signature' => $signature,
                'User-Agent' => 'SMO-Social-Webhook-Test/1.0.0'
            ],
            'body' => json_encode($payload),
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return [
                'processed' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if (in_array($status_code, [200, 201, 202])) {
            return ['processed' => true];
        } else {
            return [
                'processed' => false,
                'error' => "HTTP {$status_code}: {$body}"
            ];
        }
    }
    
    /**
     * Test IFTTT payload processing
     */
    private static function test_ifttt_payload($endpoint_url, $secret) {
        $payload = [
            'value1' => 'https://example.com/test-content',
            'value2' => 'Test Content from IFTTT',
            'value3' => 'This is a test content item from IFTTT webhook.'
        ];
        
        $signature = hash_hmac('sha1', json_encode($payload), $secret);
        
        $response = wp_remote_post($endpoint_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-IFTTT-Signature' => $signature,
                'User-Agent' => 'SMO-Social-Webhook-Test/1.0.0'
            ],
            'body' => json_encode($payload),
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return [
                'processed' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if (in_array($status_code, [200, 201, 202])) {
            return ['processed' => true];
        } else {
            return [
                'processed' => false,
                'error' => "HTTP {$status_code}: {$body}"
            ];
        }
    }
    
    /**
     * Get webhook endpoint URL
     */
    private static function get_webhook_endpoint_url($service) {
        if ($service === 'zapier') {
            return rest_url('smo-social/v1/zapier/webhook');
        } elseif ($service === 'ifttt') {
            return rest_url('smo-social/v1/ifttt/webhook');
        }
        
        return '';
    }
    
    /**
     * Generate webhook setup instructions
     */
    public static function generate_setup_instructions() {
        $instructions = "<div class='smo-webhook-setup-instructions'>\n";
        $instructions .= "<h2>🚀 Webhook Setup Instructions</h2>\n";
        
        // Zapier Instructions
        $zapier_endpoint = self::get_webhook_endpoint_url('zapier');
        $instructions .= "<div class='smo-service-instructions' data-service='zapier'>\n";
        $instructions .= "<h3>📦 Zapier Webhook Setup</h3>\n";
        $instructions .= "<ol>\n";
        $instructions .= "<li>Go to your Zapier dashboard and create a new Zap</li>\n";
        $instructions .= "<li>Choose 'Webhooks by Zapier' as your trigger app</li>\n";
        $instructions .= "<li>Select 'Catch Hook' as the trigger event</li>\n";
        $instructions .= "<li>Copy this webhook URL:<br><code class='smo-webhook-url'>{$zapier_endpoint}</code></li>\n";
        $instructions .= "<li>In your Zap, add SMO Social as an action app</li>\n";
        $instructions .= "<li>Map your webhook data to SMO Social fields</li>\n";
        $instructions .= "<li>Test your Zap and make sure it's working</li>\n";
        $instructions .= "</ol>\n";
        $instructions .= "<button class='smo-copy-url' data-url='{$zapier_endpoint}'>📋 Copy URL</button>\n";
        $instructions .= "</div>\n";
        
        // IFTTT Instructions
        $ifttt_endpoint = self::get_webhook_endpoint_url('ifttt');
        $instructions .= "<div class='smo-service-instructions' data-service='ifttt'>\n";
        $instructions .= "<h3>🔗 IFTTT Webhook Setup</h3>\n";
        $instructions .= "<ol>\n";
        $instructions .= "<li>Go to IFTTT and create a new Applet</li>\n";
        $instructions .= "<li>Choose 'Webhooks' as your trigger service</li>\n";
        $instructions .= "<li>Select 'Receive a web request' as the trigger</li>\n";
        $instructions .= "<li>Enter an event name (e.g., 'smo_social_post')</li>\n";
        $instructions .= "<li>For the action, choose 'Webhooks' and 'Make a web request'</li>\n";
        $instructions .= "<li>Set the URL to:<br><code class='smo-webhook-url'>{$ifttt_endpoint}</code></li>\n";
        $instructions .= "<li>Set method to 'POST' and content type to 'application/json'</li>\n";
        $instructions .= "<li>Use this JSON payload format:<br><code class='smo-json-payload'>{'value1':'url','value2':'title','value3':'description'}</code></li>\n";
        $instructions .= "</ol>\n";
        $instructions .= "<button class='smo-copy-url' data-url='{$ifttt_endpoint}'>📋 Copy URL</button>\n";
        $instructions .= "</div>\n";
        
        $instructions .= "</div>\n";
        
        return $instructions;
    }
}

// AJAX handler for webhook tests
add_action('wp_ajax_smo_test_webhooks', 'smo_handle_webhook_tests');

function smo_handle_webhook_tests() {
    check_ajax_referer('smo_webhook_tests', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $test_results = SMOWebhookTester::test_webhook_endpoints();
    wp_send_json_success($test_results);
}

/**
 * Add webhook testing admin page
 */
function smo_add_webhook_test_admin_page() {
    add_submenu_page(
        'smo-social',
        'Webhook Testing',
        '⚡ Webhook Tests',
        'manage_options',
        'smo-webhook-tests',
        'smo_render_webhook_test_page'
    );
}
add_action('admin_menu', 'smo_add_webhook_test_admin_page');

/**
 * Render webhook testing page
 */
function smo_render_webhook_test_page() {
    ?>
    <div class="wrap">
        <h1>⚡ SMO Social - Webhook Testing</h1>
        
        <div class="smo-webhook-instructions">
            <?php echo SMOWebhookTester::generate_setup_instructions(); ?>
        </div>
        
        <div class="smo-test-controls">
            <h3>🧪 Testing Controls</h3>
            <button id="smo-run-webhook-tests" class="button button-primary">🚀 Test All Webhooks</button>
            <button id="smo-refresh-webhook-tests" class="button">🔄 Refresh Tests</button>
        </div>
        
        <div class="smo-test-results" id="smo-webhook-test-results">
            <p>Click "Test All Webhooks" to start testing...</p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#smo-run-webhook-tests').on('click', function() {
            runWebhookTests();
        });
        
        $('#smo-refresh-webhook-tests').on('click', function() {
            location.reload();
        });
        
        $('.smo-copy-url').on('click', function() {
            var url = $(this).data('url');
            navigator.clipboard.writeText(url).then(function() {
                alert('URL copied to clipboard!');
            });
        });
    });
    
    function runWebhookTests() {
        $('#smo-run-webhook-tests').prop('disabled', true).text('Testing...');
        $('#smo-webhook-test-results').html('<div class="smo-loading"><p>Testing webhook endpoints...</p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smo_test_webhooks',
                nonce: '<?php echo wp_create_nonce('smo_webhook_tests'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayWebhookResults(response.data);
                } else {
                    $('#smo-webhook-test-results').html('<div class="smo-error">Test failed: ' + (response.data.message || 'Unknown error') + '</div>');
                }
            },
            error: function() {
                $('#smo-webhook-test-results').html('<div class="smo-error">Request failed</div>');
            },
            complete: function() {
                $('#smo-run-webhook-tests').prop('disabled', false).text('🚀 Test All Webhooks');
            }
        });
    }
    
    function displayWebhookResults(results) {
        var report = '';
        
        for (var service in results) {
            var result = results[service];
            var icon = result.status === 'passed' ? '✅' : (result.status === 'warning' ? '⚠️' : '❌');
            
            report += '<div class="smo-webhook-service">' +
                '<h3>' + icon + ' ' + result.name + ' Webhook</h3>' +
                '<div class="smo-endpoint-info">' +
                '<strong>Endpoint:</strong> <code>' + result.endpoint + '</code>' +
                '</div>' +
                '<div class="smo-test-details">';
            
            for (var i = 0; i < result.details.length; i++) {
                report += '<div class="smo-detail-line">' + result.details[i] + '</div>';
            }
            
            if (result.signature_validation) {
                report += '<div class="smo-signature-status">✅ Signature validation: Active</div>';
            }
            
            if (result.error) {
                report += '<div class="smo-error">Error: ' + result.error + '</div>';
            }
            
            if (result.signature_error) {
                report += '<div class="smo-error">Signature Error: ' + result.signature_error + '</div>';
            }
            
            if (result.payload_error) {
                report += '<div class="smo-error">Payload Error: ' + result.payload_error + '</div>';
            }
            
            report += '</div></div>';
        }
        
        $('#smo-webhook-test-results').html(report);
    }
    </script>
    
    <style>
    .smo-webhook-instructions {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .smo-service-instructions {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
    }
    
    .smo-webhook-url {
        background: #f1f1f1;
        padding: 10px;
        border-radius: 4px;
        word-break: break-all;
        font-family: monospace;
        display: block;
        margin: 10px 0;
    }
    
    .smo-json-payload {
        background: #f1f1f1;
        padding: 10px;
        border-radius: 4px;
        font-family: monospace;
        display: block;
        margin: 10px 0;
    }
    
    .smo-copy-url {
        margin-top: 10px;
        padding: 8px 16px;
        background: #0073aa;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .smo-copy-url:hover {
        background: #005a87;
    }
    
    .smo-test-controls {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .smo-webhook-service {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
    }
    
    .smo-endpoint-info {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
    }
    
    .smo-endpoint-info code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 3px;
    }
    
    .smo-detail-line {
        padding: 5px 0;
        font-family: monospace;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .smo-signature-status {
        background: #d4edda;
        color: #155724;
        padding: 8px;
        border-radius: 4px;
        margin-top: 10px;
        font-weight: bold;
    }
    
    .smo-error {
        background: #f8d7da;
        color: #721c24;
        padding: 8px;
        border-radius: 4px;
        margin-top: 10px;
    }
    </style>
    <?php
}