<?php
namespace SMO_Social\Security;

class OAuthManager {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $scopes;
    
    public function __construct($config = array()) {
        $this->client_id = $config['client_id'] ?? '';
        $this->client_secret = $config['client_secret'] ?? '';
        $this->redirect_uri = $config['redirect_uri'] ?? '';
        $this->scopes = $config['scopes'] ?? array();
    }
    
    public function initiate_oauth_flow($platform, $state = '') {
        if (!$state) {
            $state = bin2hex(\random_bytes(16));
        }
        
        $auth_url = $this->build_auth_url($platform, $state);
        
        // Store state in session for validation
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_platform'] = $platform;
        
        return array(
            'auth_url' => $auth_url,
            'state' => $state,
            'success' => true
        );
    }
    
    public function handle_oauth_callback($platform, $code, $state) {
        // Validate state
        if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
            return array(
                'success' => false,
                'error' => 'Invalid state parameter'
            );
        }
        
        // Exchange code for token
        $token_response = $this->exchange_code_for_token($platform, $code);
        
        if (!$token_response['success']) {
            return $token_response;
        }
        
        // Store token securely
        $store_result = $this->store_oauth_token($platform, $token_response['token']);
        
        return $store_result;
    }
    
    private function build_auth_url($platform, $state) {
        $platform_configs = array(
            'facebook' => array(
                'auth_url' => 'https://facebook.com/v18.0/dialog/oauth',
                'scope' => 'pages_read_engagement,pages_manage_posts,pages_manage_engagement'
            ),
            'twitter' => array(
                'auth_url' => 'https://twitter.com/i/oauth2/authorize',
                'scope' => 'tweet.read,tweet.write,users.read,offline.access'
            ),
            'instagram' => array(
                'auth_url' => 'https://api.instagram.com/oauth/authorize',
                'scope' => 'user_profile,user_media'
            ),
            'linkedin' => array(
                'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'scope' => 'r_liteprofile,w_member_social,rw_organization_admin'
            ),
            'youtube' => array(
                'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
                'scope' => 'https://www.googleapis.com/auth/youtube.force-ssl'
            )
        );
        
        if (!isset($platform_configs[$platform])) {
            return '';
        }
        
        $config = $platform_configs[$platform];
        
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $config['scope'],
            'response_type' => 'code',
            'state' => $state
        );
        
        return $config['auth_url'] . '?' . http_build_query($params);
    }
    
    private function exchange_code_for_token($platform, $code) {
        $token_endpoint = $this->get_token_endpoint($platform);
        
        $data = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect_uri
        );
        
        $response = wp_remote_post($token_endpoint, array(
            'body' => $data,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($token_data['access_token'])) {
            return array(
                'success' => true,
                'token' => $token_data
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Invalid token response'
            );
        }
    }
    
    private function get_token_endpoint($platform) {
        $endpoints = array(
            'facebook' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'twitter' => 'https://api.twitter.com/2/oauth2/token',
            'instagram' => 'https://api.instagram.com/oauth/access_token',
            'linkedin' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'youtube' => 'https://oauth2.googleapis.com/token'
        );
        
        return $endpoints[$platform] ?? '';
    }
    
    private function store_oauth_token($platform, $token_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_platform_tokens';
        
        // Encrypt sensitive data
        $encrypted_token = $this->encrypt_data($token_data['access_token']);
        $encrypted_refresh = isset($token_data['refresh_token']) ? $this->encrypt_data($token_data['refresh_token']) : '';
        
        $expires_at = null;
        if (isset($token_data['expires_in'])) {
            $expires_at = date('Y-m-d H:i:s', time() + $token_data['expires_in']);
        }
        
        $data = array(
            'platform_slug' => $platform,
            'user_id' => get_current_user_id(),
            'access_token' => $encrypted_token,
            'refresh_token' => $encrypted_refresh,
            'token_expires' => $expires_at,
            'extra_data' => json_encode($token_data),
            'status' => 'active',
            'updated_at' => current_time('mysql')
        );
        
        // Check if record exists for this user and platform
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE platform_slug = %s AND user_id = %d",
            $platform,
            get_current_user_id()
        ));
        
        if ($existing) {
            $result = $wpdb->update($table_name, $data, array('id' => $existing->id));
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table_name, $data);
        }
        
        if ($result !== false) {
            return array('success' => true);
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to store token'
            );
        }
    }
    
    private function encrypt_data($data) {
        $key = hash('sha256', \AUTH_KEY . $this->client_secret);
        $iv = \openssl_random_pseudo_bytes(16);
        $encrypted = \openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function refresh_token($platform) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_platform_tokens';
        $token_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE platform_slug = %s",
            $platform
        ));
        
        if (!$token_record || !$token_record->refresh_token) {
            return array(
                'success' => false,
                'error' => 'No refresh token available'
            );
        }
        
        $refresh_token = $this->decrypt_data($token_record->refresh_token);
        $token_endpoint = $this->get_token_endpoint($platform);
        
        $data = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        );
        
        $response = wp_remote_post($token_endpoint, array(
            'body' => $data,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $new_token_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($new_token_data['access_token'])) {
            // Update stored token
            $this->store_oauth_token($platform, $new_token_data);
            return array(
                'success' => true,
                'token' => $new_token_data
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Token refresh failed'
            );
        }
    }
    
    private function decrypt_data($encrypted_data) {
        $key = hash('sha256', \AUTH_KEY . $this->client_secret);
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return \openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
