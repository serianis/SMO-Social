<?php
/**
 * Unsplash Integration
 * 
 * Search and import images from Unsplash
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
 * Unsplash Integration Class
 */
class UnsplashIntegration extends BaseIntegration {
    
    protected $integration_id = 'unsplash';
    protected $name = 'Unsplash';
    protected $api_base_url = 'https://api.unsplash.com/';
    
    /**
     * Connect to Unsplash
     */
    public function connect($credentials) {
        $api_key = $credentials['api_key'] ?? '';
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('Please provide Unsplash API key', 'smo-social')
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
            'message' => __('Connected to Unsplash successfully', 'smo-social')
        ];
    }
    
    /**
     * Test connection
     */
    public function test_connection() {
        try {
            $result = $this->make_request('photos/random');
            
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
    
    /**
     * Override make_request for Unsplash
     */
    protected function make_request($endpoint, $method = 'GET', $data = [], $headers = []) {
        $url = $this->api_base_url . $endpoint;
        
        $default_headers = [
            'Authorization' => 'Client-ID ' . $this->access_token,
            'Accept-Version' => 'v1'
        ];
        
        $headers = array_merge($default_headers, $headers);
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code >= 400) {
            throw new \Exception($data['errors'][0] ?? 'API request failed');
        }
        
        return $data;
    }
    
    /**
     * Get data from Unsplash
     */
    public function get_data($action, $params = []) {
        switch ($action) {
            case 'search':
                return $this->search_photos($params);
            case 'random':
                return $this->get_random_photos($params);
            case 'collections':
                return $this->get_collections();
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    /**
     * Search photos
     */
    private function search_photos($params) {
        $query_params = [
            'query' => $params['query'] ?? '',
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 20,
            'orientation' => $params['orientation'] ?? 'landscape'
        ];
        
        $result = $this->make_request('search/photos?' . http_build_query($query_params));
        
        return [
            'photos' => $result['results'] ?? [],
            'total' => $result['total'] ?? 0,
            'total_pages' => $result['total_pages'] ?? 0
        ];
    }
    
    /**
     * Get random photos
     */
    private function get_random_photos($params) {
        $query_params = [
            'count' => $params['count'] ?? 10,
            'orientation' => $params['orientation'] ?? 'landscape'
        ];
        
        if (!empty($params['query'])) {
            $query_params['query'] = $params['query'];
        }
        
        $result = $this->make_request('photos/random?' . http_build_query($query_params));
        
        return [
            'photos' => is_array($result) ? $result : [$result]
        ];
    }
    
    /**
     * Get collections
     */
    private function get_collections() {
        $result = $this->make_request('collections?per_page=20');
        
        return [
            'collections' => $result
        ];
    }
    
    /**
     * Import photo from Unsplash
     */
    public function import_item($item_id) {
        try {
            // Get photo details
            $photo = $this->make_request('photos/' . $item_id);
            
            // Trigger download endpoint (required by Unsplash API)
            if (!empty($photo['links']['download_location'])) {
                $this->make_request(str_replace($this->api_base_url, '', $photo['links']['download_location']));
            }
            
            // Download high-quality image
            $download_url = $photo['urls']['full'] ?? $photo['urls']['regular'];
            $filename = sanitize_file_name($photo['slug'] ?? $item_id) . '.jpg';
            
            $attachment_id = $this->download_to_media_library($download_url, $filename);
            
            if (is_wp_error($attachment_id)) {
                throw new \Exception($attachment_id->get_error_message());
            }
            
            // Store metadata
            update_post_meta($attachment_id, '_smo_import_source', 'unsplash');
            update_post_meta($attachment_id, '_smo_unsplash_id', $item_id);
            update_post_meta($attachment_id, '_smo_unsplash_author', $photo['user']['name'] ?? '');
            update_post_meta($attachment_id, '_smo_unsplash_author_url', $photo['user']['links']['html'] ?? '');
            
            // Set alt text
            if (!empty($photo['alt_description'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $photo['alt_description']);
            }
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'author' => $photo['user']['name'] ?? '',
                'author_url' => $photo['user']['links']['html'] ?? '',
                'message' => __('Photo imported successfully', 'smo-social')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
