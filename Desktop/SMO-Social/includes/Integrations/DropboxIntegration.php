<?php
/**
 * Dropbox Integration
 * 
 * Import files from Dropbox
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

class DropboxIntegration extends BaseIntegration {
    
    protected $integration_id = 'dropbox';
    protected $name = 'Dropbox';
    protected $api_base_url = 'https://api.dropboxapi.com/2/';
    
    public function connect($credentials) {
        $app_key = $credentials['app_key'] ?? get_option('smo_dropbox_app_key');
        $app_secret = $credentials['app_secret'] ?? get_option('smo_dropbox_app_secret');
        
        if (empty($app_key) || empty($app_secret)) {
            return [
                'success' => false,
                'message' => __('Please provide Dropbox App credentials', 'smo-social')
            ];
        }
        
        update_option('smo_dropbox_app_key', $app_key);
        update_option('smo_dropbox_app_secret', $app_secret);
        
        $redirect_uri = admin_url('admin-ajax.php?action=smo_dropbox_oauth_callback');
        $state = wp_create_nonce('smo_dropbox_oauth');
        
        $auth_url = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
            'client_id' => $app_key,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'state' => $state,
            'token_access_type' => 'offline'
        ]);
        
        return [
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please authorize Dropbox access', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $result = $this->make_request('users/get_current_account', 'POST');
            
            return [
                'success' => true,
                'message' => __('Connection successful', 'smo-social'),
                'user' => $result['name']['display_name'] ?? ''
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
            case 'list_folder':
                return $this->list_folder($params['path'] ?? '');
            case 'search':
                return $this->search_files($params['query'] ?? '');
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function list_folder($path = '') {
        $result = $this->make_request('files/list_folder', 'POST', [
            'path' => $path ?: '',
            'recursive' => false,
            'include_media_info' => true
        ]);
        
        return [
            'entries' => $result['entries'] ?? [],
            'has_more' => $result['has_more'] ?? false
        ];
    }
    
    private function search_files($query) {
        $result = $this->make_request('files/search_v2', 'POST', [
            'query' => $query,
            'options' => [
                'max_results' => 20,
                'file_status' => 'active'
            ]
        ]);
        
        return [
            'matches' => $result['matches'] ?? []
        ];
    }
    
    public function import_item($item_id) {
        try {
            // Get temporary link
            $link_result = $this->make_request('files/get_temporary_link', 'POST', [
                'path' => $item_id
            ]);
            
            $download_url = $link_result['link'] ?? '';
            
            if (empty($download_url)) {
                throw new \Exception(__('Failed to get download link', 'smo-social'));
            }
            
            $filename = basename($item_id);
            $attachment_id = $this->download_to_media_library($download_url, $filename);
            
            if (is_wp_error($attachment_id)) {
                throw new \Exception($attachment_id->get_error_message());
            }
            
            update_post_meta($attachment_id, '_smo_import_source', 'dropbox');
            update_post_meta($attachment_id, '_smo_dropbox_path', $item_id);
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'message' => __('File imported successfully', 'smo-social')
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
        $client_id = get_option('smo_dropbox_app_key');
        $client_secret = get_option('smo_dropbox_app_secret');
        $redirect_uri = admin_url('admin-ajax.php?action=smo_dropbox_oauth_callback');
        
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', [
            'body' => [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
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
            'refresh_token' => $body['refresh_token'] ?? '', // Dropbox might not return refresh token unless requested
            'expires_at' => time() + ($body['expires_in'] ?? 14400),
            'connected_at' => current_time('mysql')
        ]);
        
        return ['success' => true, 'message' => __('Connected successfully', 'smo-social')];
    }
}
