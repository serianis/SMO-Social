<?php
/**
 * Zapier Integration
 * 
 * Automate workflows with Zapier webhooks
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

class ZapierIntegration extends BaseIntegration {
    
    protected $integration_id = 'zapier';
    protected $name = 'Zapier';
    protected $api_base_url = 'https://hooks.zapier.com/hooks/catch/';
    
    public function connect($credentials) {
        $webhook_url = $credentials['webhook_url'] ?? '';
        
        if (empty($webhook_url)) {
            return [
                'success' => false,
                'message' => __('Please provide Zapier webhook URL', 'smo-social')
            ];
        }
        
        $this->store_credentials([
            'webhook_url' => $webhook_url,
            'connected_at' => current_time('mysql')
        ]);
        
        return [
            'success' => true,
            'message' => __('Zapier webhook configured successfully', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $connections = get_option('smo_integration_connections', []);
            $webhook_url = $connections[$this->integration_id]['webhook_url'] ?? '';
            
            if (empty($webhook_url)) {
                throw new \Exception(__('Webhook URL not configured', 'smo-social'));
            }
            
            // Send test payload
            $response = wp_remote_post($webhook_url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'test' => true,
                    'message' => 'SMO Social test connection',
                    'timestamp' => current_time('mysql')
                ]),
                'timeout' => 15
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 400) {
                throw new \Exception(__('Webhook returned error status', 'smo-social'));
            }
            
            return [
                'success' => true,
                'message' => __('Connection successful', 'smo-social')
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
            case 'get_triggers':
                return $this->get_available_triggers();
            case 'get_actions':
                return $this->get_available_actions();
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function get_available_triggers() {
        return [
            'triggers' => [
                ['id' => 'new_post', 'name' => 'New Post Created'],
                ['id' => 'post_published', 'name' => 'Post Published'],
                ['id' => 'post_failed', 'name' => 'Post Failed'],
                ['id' => 'new_comment', 'name' => 'New Comment Received']
            ]
        ];
    }
    
    private function get_available_actions() {
        return [
            'actions' => [
                ['id' => 'create_post', 'name' => 'Create Social Post'],
                ['id' => 'schedule_post', 'name' => 'Schedule Social Post'],
                ['id' => 'import_content', 'name' => 'Import Content']
            ]
        ];
    }
    
    public function import_item($item_id) {
        // Zapier is webhook-based, import happens via incoming webhooks
        return [
            'success' => false,
            'message' => __('Import not applicable for Zapier. Use incoming webhooks instead.', 'smo-social')
        ];
    }
    
    /**
     * Send data to Zapier webhook
     */
    public function send_to_zapier($data) {
        $connections = get_option('smo_integration_connections', []);
        $webhook_url = $connections[$this->integration_id]['webhook_url'] ?? '';
        
        if (empty($webhook_url)) {
            return false;
        }
        
        $response = wp_remote_post($webhook_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data),
            'timeout' => 15
        ]);
        
        return !is_wp_error($response);
    }
    
    /**
     * Handle incoming webhook from Zapier
     */
    public static function handle_incoming_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        if (empty($data)) {
            wp_send_json_error(['message' => 'Invalid payload']);
        }
        
        // Process based on action type
        $action = $data['action'] ?? 'create_post';
        
        switch ($action) {
            case 'create_post':
                return self::create_post_from_webhook($data);
            case 'schedule_post':
                return self::schedule_post_from_webhook($data);
            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }
    }
    
    private static function create_post_from_webhook($data) {
        // Create post logic here
        wp_send_json_success([
            'message' => 'Post created successfully',
            'post_id' => 0 // Would be actual post ID
        ]);
    }
    
    private static function schedule_post_from_webhook($data) {
        // Schedule post logic here
        wp_send_json_success([
            'message' => 'Post scheduled successfully',
            'post_id' => 0 // Would be actual post ID
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
        $signature = $_SERVER['HTTP_X_ZAPIER_SIGNATURE'] ?? '';
        $secret = get_option('smo_zapier_webhook_secret', '');
        
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
            case 'schedule_post':
                return self::schedule_post_from_webhook($data);
            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }
    }
}
