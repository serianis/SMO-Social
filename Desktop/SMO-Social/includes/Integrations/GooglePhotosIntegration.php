<?php
/**
 * Google Photos Integration
 * 
 * Import photos from Google Photos
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

class GooglePhotosIntegration extends BaseIntegration {
    
    protected $integration_id = 'google_photos';
    protected $name = 'Google Photos';
    protected $api_base_url = 'https://photoslibrary.googleapis.com/v1/';
    
    public function connect($credentials) {
        // Uses same Google OAuth as Drive
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
        
        $redirect_uri = admin_url('admin-ajax.php?action=smo_google_photos_oauth_callback');
        $state = wp_create_nonce('smo_google_photos_oauth');
        
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'https://www.googleapis.com/auth/photoslibrary.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);
        
        return [
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please authorize Google Photos access', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $result = $this->make_request('albums?pageSize=1');
            
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
            case 'list_albums':
                return $this->list_albums();
            case 'list_photos':
                return $this->list_photos($params);
            case 'search':
                return $this->search_photos($params);
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function list_albums() {
        $result = $this->make_request('albums?pageSize=50');
        
        return [
            'albums' => $result['albums'] ?? []
        ];
    }
    
    private function list_photos($params = []) {
        $data = [
            'pageSize' => $params['per_page'] ?? 20
        ];
        
        if (!empty($params['album_id'])) {
            $data['albumId'] = $params['album_id'];
        }
        
        if (!empty($params['page_token'])) {
            $data['pageToken'] = $params['page_token'];
        }
        
        $result = $this->make_request('mediaItems:search', 'POST', $data);
        
        return [
            'photos' => $result['mediaItems'] ?? [],
            'next_page_token' => $result['nextPageToken'] ?? null
        ];
    }
    
    private function search_photos($params) {
        $filters = [];
        
        if (!empty($params['date_from']) || !empty($params['date_to'])) {
            $filters['dateFilter'] = [
                'ranges' => [[
                    'startDate' => $params['date_from'] ?? date('Y-m-d', strtotime('-1 year')),
                    'endDate' => $params['date_to'] ?? date('Y-m-d')
                ]]
            ];
        }
        
        $data = [
            'pageSize' => 20,
            'filters' => $filters
        ];
        
        $result = $this->make_request('mediaItems:search', 'POST', $data);
        
        return [
            'photos' => $result['mediaItems'] ?? []
        ];
    }
    
    public function import_item($item_id) {
        try {
            // Get media item
            $media = $this->make_request('mediaItems/' . $item_id);
            
            // Download photo (add =d to download)
            $download_url = $media['baseUrl'] . '=d';
            $filename = sanitize_file_name($media['filename'] ?? 'google-photo-' . $item_id);
            
            $attachment_id = $this->download_to_media_library($download_url, $filename);
            
            if (is_wp_error($attachment_id)) {
                throw new \Exception($attachment_id->get_error_message());
            }
            
            update_post_meta($attachment_id, '_smo_import_source', 'google_photos');
            update_post_meta($attachment_id, '_smo_google_photos_id', $item_id);
            
            if (!empty($media['description'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $media['description']);
            }
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'message' => __('Photo imported successfully', 'smo-social')
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
        $redirect_uri = admin_url('admin-ajax.php?action=smo_google_photos_oauth_callback');
        
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
