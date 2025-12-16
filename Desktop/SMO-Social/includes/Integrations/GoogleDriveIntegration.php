<?php
/**
 * Google Drive Integration
 * 
 * Import files from Google Drive
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

class GoogleDriveIntegration extends BaseIntegration {
    
    protected $integration_id = 'google_drive';
    protected $name = 'Google Drive';
    protected $api_base_url = 'https://www.googleapis.com/drive/v3/';
    
    public function connect($credentials) {
        $client_id = $credentials['client_id'] ?? get_option('smo_google_client_id');
        $client_secret = $credentials['client_secret'] ?? get_option('smo_google_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            return [
                'success' => false,
                'message' => __('Please provide Google API credentials', 'smo-social')
            ];
        }
        
        update_option('smo_google_client_id', $client_id);
        update_option('smo_google_client_secret', $client_secret);
        
        $redirect_uri = admin_url('admin-ajax.php?action=smo_google_oauth_callback');
        $state = wp_create_nonce('smo_google_oauth');
        
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);
        
        return [
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please authorize Google Drive access', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $result = $this->make_request('about?fields=user');
            
            return [
                'success' => true,
                'message' => __('Connection successful', 'smo-social'),
                'user' => $result['user']['displayName'] ?? ''
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
                return $this->list_files($params);
            case 'search':
                return $this->search_files($params['query'] ?? '');
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function list_files($params = []) {
        $query_params = [
            'pageSize' => $params['per_page'] ?? 20,
            'fields' => 'files(id,name,mimeType,thumbnailLink,webContentLink,size,createdTime)',
            'q' => "mimeType contains 'image/' or mimeType contains 'video/'"
        ];
        
        if (!empty($params['page_token'])) {
            $query_params['pageToken'] = $params['page_token'];
        }
        
        $result = $this->make_request('files?' . http_build_query($query_params));
        
        return [
            'files' => $result['files'] ?? [],
            'next_page_token' => $result['nextPageToken'] ?? null
        ];
    }
    
    private function search_files($query) {
        $query_params = [
            'pageSize' => 20,
            'fields' => 'files(id,name,mimeType,thumbnailLink,webContentLink,size)',
            'q' => "name contains '{$query}' and (mimeType contains 'image/' or mimeType contains 'video/')"
        ];
        
        $result = $this->make_request('files?' . http_build_query($query_params));
        
        return [
            'files' => $result['files'] ?? []
        ];
    }
    
    public function import_item($item_id) {
        try {
            // Get file metadata
            $file = $this->make_request('files/' . $item_id . '?fields=id,name,mimeType,webContentLink');
            
            // Download file
            $download_url = 'https://www.googleapis.com/drive/v3/files/' . $item_id . '?alt=media';
            
            $response = wp_remote_get($download_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token
                ],
                'timeout' => 60
            ]);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            $file_content = wp_remote_retrieve_body($response);
            
            // Save to temp file
            $temp_file = \wp_tempnam($file['name']);
            file_put_contents($temp_file, $file_content);
            
            // Upload to media library
            $file_array = [
                'name' => $file['name'],
                'tmp_name' => $temp_file
            ];
            
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $attachment_id = \media_handle_sideload($file_array, 0);
            
            if (\is_wp_error($attachment_id)) {
                @unlink($temp_file);
                throw new \Exception($attachment_id->get_error_message());
            }
            
            update_post_meta($attachment_id, '_smo_import_source', 'google_drive');
            update_post_meta($attachment_id, '_smo_google_drive_id', $item_id);
            
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
        $client_id = get_option('smo_google_client_id');
        $client_secret = get_option('smo_google_client_secret');
        $redirect_uri = admin_url('admin-ajax.php?action=smo_google_oauth_callback');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
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
