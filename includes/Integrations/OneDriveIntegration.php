<?php
/**
 * OneDrive Integration
 * 
 * Import files from Microsoft OneDrive
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

class OneDriveIntegration extends BaseIntegration {
    
    protected $integration_id = 'onedrive';
    protected $name = 'OneDrive';
    protected $api_base_url = 'https://graph.microsoft.com/v1.0/';
    
    public function connect($credentials) {
        $client_id = $credentials['client_id'] ?? get_option('smo_onedrive_client_id');
        $client_secret = $credentials['client_secret'] ?? get_option('smo_onedrive_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            return [
                'success' => false,
                'message' => __('Please provide Microsoft App credentials', 'smo-social')
            ];
        }
        
        update_option('smo_onedrive_client_id', $client_id);
        update_option('smo_onedrive_client_secret', $client_secret);
        
        $redirect_uri = admin_url('admin-ajax.php?action=smo_onedrive_oauth_callback');
        $state = wp_create_nonce('smo_onedrive_oauth');
        
        $auth_url = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'Files.Read offline_access',
            'response_mode' => 'query'
        ]);
        
        return [
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please authorize OneDrive access', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $result = $this->make_request('me/drive');
            
            return [
                'success' => true,
                'message' => __('Connection successful', 'smo-social'),
                'drive' => $result['name'] ?? ''
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
            case 'list_files':
                return $this->list_files($params['path'] ?? '');
            case 'search':
                return $this->search_files($params['query'] ?? '');
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function list_files($path = '') {
        $endpoint = $path ? "me/drive/root:/{$path}:/children" : 'me/drive/root/children';
        $result = $this->make_request($endpoint . '?$top=20&$filter=file ne null');
        
        return [
            'files' => $result['value'] ?? []
        ];
    }
    
    private function search_files($query) {
        $result = $this->make_request('me/drive/root/search(q=\'' . urlencode($query) . '\')');
        
        return [
            'files' => $result['value'] ?? []
        ];
    }
    
    public function import_item($item_id) {
        try {
            // Get file metadata
            $file = $this->make_request('me/drive/items/' . $item_id);
            
            // Get download URL
            $download_url = $file['@microsoft.graph.downloadUrl'] ?? '';
            
            if (empty($download_url)) {
                throw new \Exception(__('Failed to get download link', 'smo-social'));
            }
            
            $filename = $file['name'] ?? 'onedrive-file';
            $attachment_id = $this->download_to_media_library($download_url, $filename);
            
            if (is_wp_error($attachment_id)) {
                throw new \Exception($attachment_id->get_error_message());
            }
            
            update_post_meta($attachment_id, '_smo_import_source', 'onedrive');
            update_post_meta($attachment_id, '_smo_onedrive_id', $item_id);
            
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
        $client_id = get_option('smo_onedrive_client_id');
        $client_secret = get_option('smo_onedrive_client_secret');
        $redirect_uri = admin_url('admin-ajax.php?action=smo_onedrive_oauth_callback');
        
        $response = wp_remote_post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
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
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at' => time() + $body['expires_in'],
            'connected_at' => current_time('mysql')
        ]);
        
        return ['success' => true, 'message' => __('Connected successfully', 'smo-social')];
    }
}
