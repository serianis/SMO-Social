<?php
/**
 * Integration Webhooks Handler
 * 
 * Handles webhook endpoints for Zapier and IFTTT integrations
 *
 * @package SMO_Social
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace SMO_Social\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhooks Handler Class
 */
class WebhooksHandler {
    
    /**
     * Initialize webhook endpoints
     */
    public static function init() {
        add_action('rest_api_init', [self::class, 'register_webhook_endpoints']);
    }
    
    /**
     * Register REST API endpoints for webhooks
     */
    public static function register_webhook_endpoints() {
        // Zapier webhooks
        register_rest_route('smo-social/v1', '/webhooks/zapier/(?P<integration_id>[^/]+)', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_zapier_webhook'],
            'permission_callback' => [self::class, 'verify_webhook_signature'],
            'args' => [
                'integration_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // IFTTT webhooks
        register_rest_route('smo-social/v1', '/webhooks/ifttt/(?P<integration_id>[^/]+)', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_ifttt_webhook'],
            'permission_callback' => [self::class, 'verify_webhook_signature'],
            'args' => [
                'integration_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Test webhook endpoint
        register_rest_route('smo-social/v1', '/webhooks/test', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_test_webhook'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Handle Zapier webhook
     */
    public static function handle_zapier_webhook($request) {
        $integration_id = $request->get_param('integration_id');
        $payload = $request->get_json_params();
        
        // Validate integration
        if (!self::validate_integration($integration_id, 'zapier')) {
            return new \WP_Error('invalid_integration', 'Invalid integration ID', ['status' => 400]);
        }
        
        // Process webhook data
        $result = self::process_zapier_data($integration_id, $payload);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return \rest_ensure_response([
            'success' => true,
            'message' => 'Webhook processed successfully',
            'data' => $result
        ]);
    }
    
    /**
     * Handle IFTTT webhook
     */
    public static function handle_ifttt_webhook($request) {
        $integration_id = $request->get_param('integration_id');
        $payload = $request->get_json_params();
        
        // Validate integration
        if (!self::validate_integration($integration_id, 'ifttt')) {
            return new \WP_Error('invalid_integration', 'Invalid integration ID', ['status' => 400]);
        }
        
        // Process webhook data
        $result = self::process_ifttt_data($integration_id, $payload);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return \rest_ensure_response([
            'success' => true,
            'message' => 'Webhook processed successfully',
            'data' => $result
        ]);
    }
    
    /**
     * Handle test webhook
     */
    public static function handle_test_webhook($request) {
        $payload = $request->get_json_params();
        
        return \rest_ensure_response([
            'success' => true,
            'message' => 'Test webhook received',
            'timestamp' => current_time('mysql'),
            'payload' => $payload
        ]);
    }
    
    /**
     * Verify webhook signature
     */
    public static function verify_webhook_signature($request) {
        $integration_id = $request->get_param('integration_id');
        
        // Get webhook signature from headers
        $signature = $request->get_header('x-webhook-signature') ?: $request->get_header('x-zapier-signature') ?: $request->get_header('x-ifttt-signature');
        
        if (empty($signature)) {
            return new \WP_Error('missing_signature', 'Webhook signature is required', ['status' => 401]);
        }
        
        // Validate signature based on integration type
        if (strpos($request->get_route(), 'zapier') !== false) {
            return self::verify_zapier_signature($integration_id, $signature, $request);
        } elseif (strpos($request->get_route(), 'ifttt') !== false) {
            return self::verify_ifttt_signature($integration_id, $signature, $request);
        }
        
        return new \WP_Error('invalid_signature', 'Invalid webhook signature', ['status' => 401]);
    }
    
    /**
     * Verify Zapier signature
     */
    private static function verify_zapier_signature($integration_id, $signature, $request) {
        $payload = $request->get_body();
        $webhook_key = self::get_integration_webhook_key($integration_id, 'zapier');
        
        if (empty($webhook_key)) {
            return new \WP_Error('missing_key', 'Webhook key not found', ['status' => 401]);
        }
        
        // Verify HMAC signature
        $expected_signature = hash_hmac('sha256', $payload, $webhook_key);
        
        if (!hash_equals($expected_signature, $signature)) {
            return new \WP_Error('invalid_signature', 'Signature verification failed', ['status' => 401]);
        }
        
        return true;
    }
    
    /**
     * Verify IFTTT signature
     */
    private static function verify_ifttt_signature($integration_id, $signature, $request) {
        $payload = $request->get_body();
        $webhook_key = self::get_integration_webhook_key($integration_id, 'ifttt');
        
        if (empty($webhook_key)) {
            return new \WP_Error('missing_key', 'Webhook key not found', ['status' => 401]);
        }
        
        // Verify HMAC signature (IFTTT uses SHA1)
        $expected_signature = hash_hmac('sha1', $payload, $webhook_key);
        
        if (!hash_equals($expected_signature, $signature)) {
            return new \WP_Error('invalid_signature', 'Signature verification failed', ['status' => 401]);
        }
        
        return true;
    }
    
    /**
     * Process Zapier webhook data
     */
    private static function process_zapier_data($integration_id, $payload) {
        // Log the webhook event
        self::log_webhook_event($integration_id, 'zapier_webhook', $payload);
        
        // Process different types of Zapier events
        $action = $payload['action'] ?? 'unknown';
        
        switch ($action) {
            case 'create_post':
                return self::create_post_from_zapier($integration_id, $payload);
            case 'schedule_post':
                return self::schedule_post_from_zapier($integration_id, $payload);
            case 'import_content':
                return self::import_content_from_zapier($integration_id, $payload);
            default:
                return new \WP_Error('unknown_action', 'Unknown Zapier action', ['status' => 400]);
        }
    }
    
    /**
     * Process IFTTT webhook data
     */
    private static function process_ifttt_data($integration_id, $payload) {
        // Log the webhook event
        self::log_webhook_event($integration_id, 'ifttt_webhook', $payload);
        
        // IFTTT typically sends simpler payloads
        $value1 = $payload['value1'] ?? '';
        $value2 = $payload['value2'] ?? '';
        $value3 = $payload['value3'] ?? '';
        
        // Process based on value1 (action type)
        switch (strtolower($value1)) {
            case 'create_post':
                return self::create_post_from_ifttt($integration_id, $payload);
            case 'import_url':
                return self::import_url_from_ifttt($integration_id, $value2);
            default:
                return new \WP_Error('unknown_action', 'Unknown IFTTT action', ['status' => 400]);
        }
    }
    
    /**
     * Create post from Zapier data
     */
    private static function create_post_from_zapier($integration_id, $data) {
        $post_data = [
            'post_title' => \sanitize_text_field($data['title'] ?? 'Imported from Zapier'),
            'post_content' => \wp_kses_post($data['content'] ?? $data['description'] ?? ''),
            'post_status' => 'draft',
            'post_type' => 'post',
            'meta_input' => [
                'imported_from_integration' => $integration_id,
                'import_method' => 'zapier_webhook',
                'import_timestamp' => current_time('mysql')
            ]
        ];
        
        $post_id = \wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Handle media if provided
        if (!empty($data['media_url'])) {
            $attachment_id = self::download_media_from_url($data['media_url'], $post_id);
            if (!is_wp_error($attachment_id)) {
                \set_post_thumbnail($post_id, $attachment_id);
            }
        }
        
        return ['post_id' => $post_id, 'post_url' => \get_permalink($post_id)];
    }
    
    /**
     * Schedule post from Zapier data
     */
    private static function schedule_post_from_zapier($integration_id, $data) {
        $post_data = [
            'post_title' => \sanitize_text_field($data['title'] ?? 'Scheduled from Zapier'),
            'post_content' => \wp_kses_post($data['content'] ?? $data['description'] ?? ''),
            'post_status' => 'future',
            'post_type' => 'post',
            'post_date' => $data['schedule_time'] ?? current_time('mysql'),
            'meta_input' => [
                'imported_from_integration' => $integration_id,
                'import_method' => 'zapier_webhook',
                'import_timestamp' => current_time('mysql')
            ]
        ];
        
        return \wp_insert_post($post_data);
    }
    
    /**
     * Import content from Zapier data
     */
    private static function import_content_from_zapier($integration_id, $data) {
        // Simple content import - could be enhanced
        return [
            'action' => 'content_import',
            'content_type' => $data['content_type'] ?? 'unknown',
            'source_url' => $data['source_url'] ?? '',
            'integration_id' => $integration_id
        ];
    }
    
    /**
     * Create post from IFTTT data
     */
    private static function create_post_from_ifttt($integration_id, $data) {
        $post_data = [
            'post_title' => \sanitize_text_field($data['value1'] ?? 'Imported from IFTTT'),
            'post_content' => \wp_kses_post($data['value2'] ?? $data['value3'] ?? ''),
            'post_status' => 'draft',
            'post_type' => 'post',
            'meta_input' => [
                'imported_from_integration' => $integration_id,
                'import_method' => 'ifttt_webhook',
                'import_timestamp' => current_time('mysql')
            ]
        ];
        
        return \wp_insert_post($post_data);
    }
    
    /**
     * Import URL content from IFTTT
     */
    private static function import_url_from_ifttt($integration_id, $url) {
        // Simple URL import - could be enhanced with content extraction
        return [
            'action' => 'url_import',
            'url' => \esc_url_raw($url),
            'integration_id' => $integration_id
        ];
    }
    
    /**
     * Validate integration exists and is active
     */
    private static function validate_integration($integration_id, $expected_type) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'smo_integrations';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE integration_id = %s AND status = 'connected'",
            $integration_id
        ));
        
        return !empty($result);
    }
    
    /**
     * Get integration webhook key
     */
    private static function get_integration_webhook_key($integration_id, $type) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'smo_integrations';
        $credentials = $wpdb->get_var($wpdb->prepare(
            "SELECT credentials FROM $table WHERE integration_id = %s",
            $integration_id
        ));
        
        if (empty($credentials)) {
            return '';
        }
        
        $credentials = json_decode($credentials, true);
        return $credentials['webhook_key'] ?? $credentials['webhook_url'] ?? '';
    }
    
    /**
     * Log webhook event
     */
    private static function log_webhook_event($integration_id, $action, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'smo_integration_logs';
        $wpdb->insert($table, [
            'integration_id' => $integration_id,
            'user_id' => get_current_user_id(),
            'action' => $action,
            'details' => json_encode($data),
            'status' => 'success',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Download media from URL and attach to post
     */
    private static function download_media_from_url($url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = \media_sideload_image($url, $post_id);
        
        return is_wp_error($attachment_id) ? $attachment_id : $attachment_id;
    }
}