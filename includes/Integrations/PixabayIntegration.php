<?php
/**
 * Pixabay Integration
 * 
 * Search and import images and videos from Pixabay
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

class PixabayIntegration extends BaseIntegration {
    
    protected $integration_id = 'pixabay';
    protected $name = 'Pixabay';
    protected $api_base_url = 'https://pixabay.com/api/';
    
    public function connect($credentials) {
        $api_key = $credentials['api_key'] ?? '';
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Please provide Pixabay API key', 'smo-social')
            ];
        }
        
        $this->access_token = $api_key;
        $this->store_credentials([
            'access_token' => $api_key,
            'connected_at' => current_time('mysql')
        ]);
        
        return [
            'success' => true,
            'access_token' => $api_key,
            'message' => __('Connected to Pixabay successfully', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $result = $this->make_request('?key=' . $this->access_token . '&q=test&per_page=3');
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
    
    protected function make_request($endpoint, $method = 'GET', $data = [], $headers = []) {
        $url = $this->api_base_url . $endpoint;
        
        $response = wp_remote_get($url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    public function get_data($action, $params = []) {
        switch ($action) {
            case 'search_images':
                return $this->search_images($params);
            case 'search_videos':
                return $this->search_videos($params);
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function search_images($params) {
        $query_params = [
            'key' => $this->access_token,
            'q' => $params['query'] ?? '',
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 20,
            'image_type' => $params['image_type'] ?? 'photo'
        ];
        
        $result = $this->make_request('?' . http_build_query($query_params));
        
        return [
            'images' => $result['hits'] ?? [],
            'total' => $result['totalHits'] ?? 0
        ];
    }
    
    private function search_videos($params) {
        $query_params = [
            'key' => $this->access_token,
            'q' => $params['query'] ?? '',
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 20
        ];
        
        $result = $this->make_request('videos/?' . http_build_query($query_params));
        
        return [
            'videos' => $result['hits'] ?? [],
            'total' => $result['totalHits'] ?? 0
        ];
    }
    
    public function import_item($item_id) {
        try {
            // Item ID format: type:id:url
            list($type, $id, $url) = explode(':', $item_id, 3);
            
            $filename = sanitize_file_name('pixabay-' . $id) . ($type === 'video' ? '.mp4' : '.jpg');
            $attachment_id = $this->download_to_media_library($url, $filename);
            
            if (is_wp_error($attachment_id)) {
                throw new \Exception($attachment_id->get_error_message());
            }
            
            update_post_meta($attachment_id, '_smo_import_source', 'pixabay');
            update_post_meta($attachment_id, '_smo_pixabay_id', $id);
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'message' => __('Media imported successfully', 'smo-social')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
