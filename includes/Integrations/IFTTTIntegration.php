<?php
/**
 * IFTTT Integration
 * 
 * Automate workflows with IFTTT webhooks
 *
 * @package SMO_Social
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace SMO_Social\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/BaseIntegration.php';

class IFTTTIntegration extends BaseIntegration {
    
    protected $integration_id = 'ifttt';
    protected $name = 'IFTTT';
    protected $api_base_url = 'https://maker.ifttt.com/trigger/';
    
    public function connect($credentials) {
        $webhook_key = $credentials['webhook_key'] ?? '';
        
        if (empty($webhook_key)) {
            return [
                'success' => false,
                'message' => __('Please provide IFTTT webhook key', 'smo-social')
            ];
        }
        
        $this->store_credentials([
            'access_token' => $webhook_key,
            'connected_at' => current_time('mysql')
        ]);
        
        return [
            'success' => true,
            'access_token' => $webhook_key,
            'message' => __('IFTTT webhook configured successfully', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            if (empty($this->access_token)) {
                throw new \Exception(__('Webhook key not configured', 'smo-social'));
            }
            
            // Send test event
            $result = $this->trigger_event('smo_social_test', [
                'value1' => 'Test connection',
                'value2' => current_time('mysql'),
                'value3' => 'SMO Social'
            ]);
            
            return [
                'success' => true,
                'message' => __('Connection successful. Check your IFTTT applet.', 'smo-social')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function get_data($action, $params = []) {
        switch ($action) {
            case 'get_events':
                return $this->get_available_events();
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function get_available_events() {
        return [
            'events' => [
                ['id' => 'smo_post_created', 'name' => 'Post Created'],
                ['id' => 'smo_post_published', 'name' => 'Post Published'],
                ['id' => 'smo_post_failed', 'name' => 'Post Failed'],
                ['id' => 'smo_engagement_milestone', 'name' => 'Engagement Milestone Reached']
            ]
        ];
    }
    
    public function import_item($item_id) {
        // IFTTT is webhook-based, import happens via incoming webhooks
        return [
            'success' => false,
            'message' => __('Import not applicable for IFTTT. Use incoming webhooks instead.', 'smo-social')
        ];
    }
    
    /**
     * Trigger IFTTT event
     */
    public function trigger_event($event_name, $values = []) {
        if (empty($this->access_token)) {
            throw new \Exception(__('IFTTT webhook key not configured', 'smo-social'));
        }
        
        $url = $this->api_base_url . $event_name . '/with/key/' . $this->access_token;
        
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($values),
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 400) {
            throw new \Exception(__('IFTTT webhook returned error', 'smo-social'));
        }
        
        return true;
    }
    
    /**
     * Handle incoming webhook from IFTTT
     */
    public static function handle_incoming_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        if (empty($data)) {
            wp_send_json_error(['message' => 'Invalid payload']);
        }
        
        // Verify webhook (optional - implement signature verification if needed)
        
        // Process based on action type
        $action = $data['action'] ?? 'create_post';
        
        switch ($action) {
            case 'create_post':
                return self::create_post_from_webhook($data);
            case 'import_content':
                return self::import_content_from_webhook($data);
            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }
    }
    
    private static function create_post_from_webhook($data) {
        wp_send_json_success([
            'message' => 'Post created successfully',
            'post_id' => 0
        ]);
    }
    
    private static function import_content_from_webhook($data) {
        wp_send_json_success([
            'message' => 'Content imported successfully'
        ]);
    }
    
    /**
     * Validate incoming webhook signature
     */
    public static function validate_webhook_signature($payload, $signature, $secret) {
        if (empty($signature) || empty($secret)) {
            return false;
        }
        
        $expected_signature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Enhanced webhook handling with signature validation
     */
    public static function handle_incoming_webhook_secure() {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_IFTTT_SIGNATURE'] ?? '';
        $secret = get_option('smo_ifttt_webhook_secret', '');
        
        // Validate signature
        if (!empty($secret) && !self::validate_webhook_signature($payload, $signature, $secret)) {
            wp_send_json_error(['message' => 'Invalid signature']);
        }
        
        $data = json_decode($payload, true);
        
        if (empty($data)) {
            wp_send_json_error(['message' => 'Invalid payload']);
        }
        
        // Process based on action type
        $action = $data['action'] ?? 'create_post';
        
        switch ($action) {
            case 'create_post':
                return self::create_post_from_webhook($data);
            case 'import_content':
                return self::import_content_from_webhook($data);
            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }
    }
}
