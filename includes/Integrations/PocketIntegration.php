<?php
/**
 * Pocket Integration
 * 
 * Import and share content from Pocket
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

class PocketIntegration extends BaseIntegration {
    
    protected $integration_id = 'pocket';
    protected $name = 'Pocket';
    protected $api_base_url = 'https://getpocket.com/v3/';
    
    public function connect($credentials) {
        $consumer_key = $credentials['consumer_key'] ?? get_option('smo_pocket_consumer_key');
        
        if (empty($consumer_key)) {
            return [
                'success' => false,
                'message' => __('Please provide Pocket Consumer Key', 'smo-social')
            ];
        }
        
        update_option('smo_pocket_consumer_key', $consumer_key);
        
        // Step 1: Obtain request token
        $redirect_uri = admin_url('admin-ajax.php?action=smo_pocket_oauth_callback&code=authorized');
        
        $response = wp_remote_post('https://getpocket.com/v3/oauth/request', [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Accept' => 'application/json'
            ],
            'body' => json_encode([
                'consumer_key' => $consumer_key,
                'redirect_uri' => $redirect_uri
            ])
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $request_token = $body['code'] ?? '';
        
        if (empty($request_token)) {
            return [
                'success' => false,
                'message' => __('Failed to obtain request token', 'smo-social')
            ];
        }
        
        // Store request token temporarily
        set_transient('smo_pocket_request_token', $request_token, 300);
        
        // Step 2: Redirect to authorization
        $auth_url = 'https://getpocket.com/auth/authorize?' . http_build_query([
            'request_token' => $request_token,
            'redirect_uri' => $redirect_uri
        ]);
        
        return [
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please authorize Pocket access', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $result = $this->get_items(['count' => 1]);
            
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
    
    protected function make_request($endpoint, $method = 'POST', $data = [], $headers = []) {
        $url = $this->api_base_url . $endpoint;
        
        $consumer_key = get_option('smo_pocket_consumer_key');
        
        $default_data = [
            'consumer_key' => $consumer_key,
            'access_token' => $this->access_token
        ];
        
        $data = array_merge($default_data, $data);
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Accept' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($status_code >= 400) {
            throw new \Exception($result['error'] ?? 'API request failed');
        }
        
        return $result;
    }
    
    public function get_data($action, $params = []) {
        switch ($action) {
            case 'get_items':
                return $this->get_items($params);
            case 'search':
                return $this->search_items($params['query'] ?? '');
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function get_items($params = []) {
        $data = [
            'count' => $params['count'] ?? 20,
            'detailType' => 'complete',
            'state' => $params['state'] ?? 'unread',
            'sort' => $params['sort'] ?? 'newest'
        ];
        
        if (!empty($params['tag'])) {
            $data['tag'] = $params['tag'];
        }
        
        if (!empty($params['offset'])) {
            $data['offset'] = $params['offset'];
        }
        
        $result = $this->make_request('get', 'POST', $data);
        
        return [
            'items' => $result['list'] ?? [],
            'status' => $result['status'] ?? 1
        ];
    }
    
    private function search_items($query) {
        $result = $this->make_request('get', 'POST', [
            'search' => $query,
            'count' => 20,
            'detailType' => 'complete'
        ]);
        
        return [
            'items' => $result['list'] ?? []
        ];
    }
    
    public function import_item($item_id) {
        try {
            // Get item details
            $result = $this->make_request('get', 'POST', [
                'item_id' => $item_id,
                'detailType' => 'complete'
            ]);
            
            $items = $result['list'] ?? [];
            $item = $items[$item_id] ?? null;
            
            if (!$item) {
                throw new \Exception(__('Item not found', 'smo-social'));
            }
            
            // Extract content
            $title = $item['resolved_title'] ?? $item['given_title'] ?? '';
            $excerpt = $item['excerpt'] ?? '';
            $url = $item['resolved_url'] ?? $item['given_url'] ?? '';
            
            // Get image if available
            $image_url = '';
            if (!empty($item['top_image_url'])) {
                $image_url = $item['top_image_url'];
            } elseif (!empty($item['image']['src'])) {
                $image_url = $item['image']['src'];
            }
            
            // Download image
            $attachment_id = 0;
            if (!empty($image_url)) {
                $attachment_id = $this->download_to_media_library($image_url, sanitize_file_name($title) . '.jpg');
                if (is_wp_error($attachment_id)) {
                    $attachment_id = 0;
                }
            }
            
            // Store in imported content
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'smo_imported_content',
                [
                    'title' => $title,
                    'content' => $excerpt,
                    'source_url' => $url,
                    'source_type' => 'pocket',
                    'published_date' => date('Y-m-d H:i:s', $item['time_added'] ?? time()),
                    'featured_image_id' => $attachment_id,
                    'user_id' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
            );
            
            $imported_id = $wpdb->insert_id;
            
            // Archive item in Pocket
            $this->archive_item($item_id);
            
            return [
                'success' => true,
                'imported_id' => $imported_id,
                'attachment_id' => $attachment_id,
                'title' => $title,
                'message' => __('Article imported successfully', 'smo-social')
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Archive item in Pocket
     */
    private function archive_item($item_id) {
        try {
            $this->make_request('send', 'POST', [
                'actions' => [
                    [
                        'action' => 'archive',
                        'item_id' => $item_id
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback($code) {
        $consumer_key = get_option('smo_pocket_consumer_key');
        $request_token = get_transient('smo_pocket_request_token');
        
        if (empty($request_token)) {
            return ['success' => false, 'message' => __('Request token expired. Please try again.', 'smo-social')];
        }
        
        $response = wp_remote_post('https://getpocket.com/v3/oauth/authorize', [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Accept' => 'application/json'
            ],
            'body' => json_encode([
                'consumer_key' => $consumer_key,
                'code' => $request_token
            ])
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            return ['success' => false, 'message' => __('Failed to obtain access token', 'smo-social')];
        }
        
        $this->store_credentials([
            'access_token' => $body['access_token'],
            'username' => $body['username'] ?? '',
            'connected_at' => current_time('mysql')
        ]);
        
        delete_transient('smo_pocket_request_token');
        
        return ['success' => true, 'message' => __('Connected successfully', 'smo-social')];
    }
}
