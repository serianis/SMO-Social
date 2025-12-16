<?php
/**
 * Canva Integration
 * 
 * Import designs and templates from Canva
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

/**
 * Canva Integration Class
 */
class CanvaIntegration extends BaseIntegration {
    
    protected $integration_id = 'canva';
    protected $name = 'Canva';
    protected $api_base_url = 'https://api.canva.com/v1/';
    
    /**
     * Connect to Canva
     */
    public function connect($credentials) {
        $client_id = $credentials['client_id'] ?? get_option('smo_canva_client_id');
        $client_secret = $credentials['client_secret'] ?? get_option('smo_canva_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            return [
                'success' => false,
                'message' => __('Please provide Canva API credentials', 'smo-social')
            ];
        }
        
        // Store credentials
        update_option('smo_canva_client_id', $client_id);
        update_option('smo_canva_client_secret', $client_secret);
        
        // Generate OAuth URL
        $redirect_uri = admin_url('admin-ajax.php?action=smo_canva_oauth_callback');
        $state = wp_create_nonce('smo_canva_oauth');
        
        $auth_url = 'https://www.canva.com/api/oauth/authorize?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'design:read design:content:read asset:read'
        ]);
        
        return [
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please authorize Canva access', 'smo-social')
        ];
    }
    
    /**
     * Test connection
     */
    public function test_connection() {
        try {
            $result = $this->make_request('users/me');
            
            return [
                'success' => true,
                'message' => __('Connection successful', 'smo-social'),
                'user' => $result['user'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get data from Canva
     */
    public function get_data($action, $params = []) {
        switch ($action) {
            case 'list_designs':
                return $this->list_designs($params);
            case 'get_design':
                return $this->get_design($params['design_id'] ?? '');
            case 'search_templates':
                return $this->search_templates($params['query'] ?? '');
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    /**
     * List user designs
     */
    private function list_designs($params = []) {
        $query_params = [
            'limit' => $params['limit'] ?? 20,
            'offset' => $params['offset'] ?? 0
        ];
        
        $result = $this->make_request('designs?' . http_build_query($query_params));
        
        return [
            'designs' => $result['items'] ?? [],
            'total' => $result['total'] ?? 0
        ];
    }
    
    /**
     * Get specific design
     */
    private function get_design($design_id) {
        return $this->make_request('designs/' . $design_id);
    }
    
    /**
     * Search templates
     */
    private function search_templates($query) {
        $result = $this->make_request('templates/search?' . http_build_query([
            'query' => $query,
            'limit' => 20
        ]));
        
        return [
            'templates' => $result['items'] ?? []
        ];
    }
    
    /**
     * Import design from Canva
     */
    public function import_item($item_id) {
        try {
            // Get design details
            $design = $this->get_design($item_id);
            
            // Export design as image
            $export_result = $this->make_request('designs/' . $item_id . '/export', 'POST', [
                'format' => 'png'
            ]);
            
            $export_url = $export_result['url'] ?? '';
            
            if (empty($export_url)) {
                throw new \Exception(__('Failed to export design', 'smo-social'));
            }
            
            // Download to media library
            $filename = sanitize_file_name($design['title'] ?? 'canva-design') . '.png';
            $attachment_id = $this->download_to_media_library($export_url, $filename);
            
            if (is_wp_error($attachment_id)) {
                throw new \Exception($attachment_id->get_error_message());
            }
            
            // Store metadata
            update_post_meta($attachment_id, '_smo_import_source', 'canva');
            update_post_meta($attachment_id, '_smo_canva_design_id', $item_id);
            update_post_meta($attachment_id, '_smo_canva_design_title', $design['title'] ?? '');
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'message' => __('Design imported successfully', 'smo-social')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback($code) {
        $client_id = get_option('smo_canva_client_id');
        $client_secret = get_option('smo_canva_client_secret');
        $redirect_uri = admin_url('admin-ajax.php?action=smo_canva_oauth_callback');
        
        $response = wp_remote_post('https://api.canva.com/rest/v1/oauth/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri
            ]
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error_description'] ?? $body['error']];
        }
        
        $this->store_credentials([
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'],
            'expires_at' => time() + $body['expires_in'],
            'connected_at' => current_time('mysql')
        ]);
        
        return ['success' => true, 'message' => __('Connected successfully', 'smo-social')];
    }
}
