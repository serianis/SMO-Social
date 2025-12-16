<?php
/**
 * Feedly Integration
 * 
 * Import and share content from Feedly feeds
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

class FeedlyIntegration extends BaseIntegration {
    
    protected $integration_id = 'feedly';
    protected $name = 'Feedly';
    protected $api_base_url = 'https://cloud.feedly.com/v3/';
    
    public function connect($credentials) {
        $client_id = $credentials['client_id'] ?? get_option('smo_feedly_client_id');
        $client_secret = $credentials['client_secret'] ?? get_option('smo_feedly_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            return [
                'success' => false,
                'message' => __('Please provide Feedly API credentials', 'smo-social')
            ];
        }
        
        update_option('smo_feedly_client_id', $client_id);
        update_option('smo_feedly_client_secret', $client_secret);
        
        $redirect_uri = admin_url('admin-ajax.php?action=smo_feedly_oauth_callback');
        $state = wp_create_nonce('smo_feedly_oauth');
        
        $auth_url = 'https://cloud.feedly.com/v3/auth/auth?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'https://cloud.feedly.com/subscriptions'
        ]);
        
        return [
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please authorize Feedly access', 'smo-social')
        ];
    }
    
    public function test_connection() {
        try {
            $result = $this->make_request('profile');
            
            return [
                'success' => true,
                'message' => __('Connection successful', 'smo-social'),
                'user' => $result['fullName'] ?? $result['email'] ?? ''
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
            case 'list_feeds':
                return $this->list_feeds();
            case 'get_stream':
                return $this->get_stream($params);
            case 'search_feeds':
                return $this->search_feeds($params['query'] ?? '');
            default:
                throw new \Exception(__('Invalid action', 'smo-social'));
        }
    }
    
    private function list_feeds() {
        $result = $this->make_request('subscriptions');
        
        return [
            'feeds' => $result ?? []
        ];
    }
    
    private function get_stream($params) {
        $stream_id = $params['stream_id'] ?? 'user/' . $this->get_user_id() . '/category/global.all';
        
        $query_params = [
            'count' => $params['count'] ?? 20,
            'ranked' => $params['ranked'] ?? 'newest',
            'unreadOnly' => $params['unread_only'] ?? false
        ];
        
        if (!empty($params['continuation'])) {
            $query_params['continuation'] = $params['continuation'];
        }
        
        $result = $this->make_request('streams/' . urlencode($stream_id) . '/contents?' . http_build_query($query_params));
        
        return [
            'items' => $result['items'] ?? [],
            'continuation' => $result['continuation'] ?? null
        ];
    }
    
    private function search_feeds($query) {
        $result = $this->make_request('search/feeds?query=' . urlencode($query));
        
        return [
            'feeds' => $result['results'] ?? []
        ];
    }
    
    private function get_user_id() {
        try {
            $profile = $this->make_request('profile');
            return $profile['id'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }
    
    public function import_item($item_id) {
        try {
            // Get entry details
            $result = $this->make_request('entries/' . urlencode($item_id));
            
            $entry = $result[0] ?? $result;
            
            // Extract content
            $title = $entry['title'] ?? '';
            $content = $entry['summary']['content'] ?? $entry['content']['content'] ?? '';
            $url = $entry['canonicalUrl'] ?? $entry['alternate'][0]['href'] ?? '';
            $author = $entry['author'] ?? '';
            $published = $entry['published'] ?? time() * 1000;
            
            // Get featured image if available
            $image_url = '';
            if (!empty($entry['visual']['url'])) {
                $image_url = $entry['visual']['url'];
            } elseif (!empty($entry['enclosure'])) {
                foreach ($entry['enclosure'] as $enclosure) {
                    if (strpos($enclosure['type'], 'image/') === 0) {
                        $image_url = $enclosure['href'];
                        break;
                    }
                }
            }
            
            // Download image if available
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
                    'content' => $content,
                    'source_url' => $url,
                    'source_type' => 'feedly',
                    'author' => $author,
                    'published_date' => date('Y-m-d H:i:s', $published / 1000),
                    'featured_image_id' => $attachment_id,
                    'user_id' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
            );
            
            $imported_id = $wpdb->insert_id;
            
            // Mark as read in Feedly
            $this->mark_as_read($item_id);
            
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
     * Mark entry as read
     */
    private function mark_as_read($entry_id) {
        try {
            $this->make_request('markers', 'POST', [
                'action' => 'markAsRead',
                'type' => 'entries',
                'entryIds' => [$entry_id]
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback($code) {
        $client_id = get_option('smo_feedly_client_id');
        $client_secret = get_option('smo_feedly_client_secret');
        $redirect_uri = admin_url('admin-ajax.php?action=smo_feedly_oauth_callback');
        
        $response = wp_remote_post('https://cloud.feedly.com/v3/auth/token', [
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
            'expires_at' => time() + ($body['expires_in'] ?? 0),
            'connected_at' => current_time('mysql'),
            'user_id' => $body['id'] ?? ''
        ]);
        
        return ['success' => true, 'message' => __('Connected successfully', 'smo-social')];
    }
}
