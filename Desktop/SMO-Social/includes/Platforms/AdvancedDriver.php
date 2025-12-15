<?php
namespace SMO_Social\Platforms;

use SMO_Social\Security\TokenStorage;
use SMO_Social\Resilience\FallbackManager;

/**
 * Advanced Platform Driver with resilience and fallback mechanisms
 * Supports 25+ social media platforms with modular architecture
 */
class AdvancedDriver {
    private $config;
    private $api_base;
    private $platform_slug;
    private $token_storage;
    private $fallback_manager;
    private $rate_limit_handler;
    private $endpoints;
    private $capabilities;

    public function __construct($platform_config) {
        $this->config = $platform_config;
        $this->platform_slug = $platform_config['slug'];
        $this->api_base = $platform_config['api_base'];
        $this->endpoints = $this->build_endpoints();
        $this->capabilities = $platform_config['capabilities'] ?? [];
        
        // Initialize security and resilience components
        $this->token_storage = new TokenStorage($this->platform_slug);
        $this->fallback_manager = new FallbackManager($this->platform_slug);
        $this->rate_limit_handler = new RateLimitHandler($this->platform_slug);
    }

    /**
     * Build platform-specific API endpoints with fallback mechanisms
     */
    private function build_endpoints() {
        $base_endpoints = [
            'auth' => '/oauth2/authorize',
            'token' => '/oauth2/token',
            'post' => '/posts',
            'media' => '/media',
            'analytics' => '/analytics',
            'me' => '/me'
        ];

        // Platform-specific endpoint overrides
        $platform_overrides = [
            'twitter' => [
                'auth' => '/i/oauth2/authorize',
                'token' => '/2/oauth2/token',
                'post' => '/2/tweets',
                'media' => '/1.1/media/upload.json'
            ],
            'facebook' => [
                'post' => '/feed',
                'analytics' => '/insights'
            ],
            'instagram' => [
                'post' => '/media',
                'analytics' => '/insights'
            ],
            'linkedin' => [
                'post' => '/ugcPosts',
                'analytics' => '/socialActions'
            ],
            'mastodon' => [
                'auth' => '/oauth/authorize',
                'token' => '/oauth/token',
                'post' => '/statuses',
                'analytics' => '/timelines/home'
            ]
        ];

        $endpoints = array_merge($base_endpoints, $platform_overrides[$this->platform_slug] ?? []);
        
        // Add fallback endpoints for each primary endpoint
        foreach ($endpoints as $name => $endpoint) {
            $fallback_key = $name . '_fallback';
            $endpoints[$fallback_key] = $this->generate_fallback_endpoint($name, $endpoint);
        }

        return $endpoints;
    }

    /**
     * Generate fallback endpoint with version alternatives
     */
    private function generate_fallback_endpoint($name, $primary) {
        // Extract version from primary endpoint
        if (preg_match('/v(\d+)/', $primary, $matches)) {
            $version = intval($matches[1]);
            $fallback_versions = range($version - 1, max(1, $version - 2));
            
            foreach ($fallback_versions as $fallback_version) {
                $fallback_endpoint = str_replace("v{$version}", "v{$fallback_version}", $primary);
                if ($this->validate_endpoint($fallback_endpoint)) {
                    return $fallback_endpoint;
                }
            }
        }
        
        return $primary; // Fallback to primary if no alternatives
    }

    /**
     * Validate endpoint availability
     */
    private function validate_endpoint($endpoint) {
        // Implementation would test endpoint availability
        // For now, return true - in production, this would make a test request
        return true;
    }

    /**
     * Authenticate with the platform using multiple methods
     */
    public function authenticate($auth_method = 'oauth2') {
        try {
            switch ($auth_method) {
                case 'oauth2':
                    return $this->oauth2_authenticate();
                case 'api_key':
                    return $this->api_key_authenticate();
                case 'bearer':
                    return $this->bearer_authenticate();
                default:
                    throw new \Exception("Unsupported authentication method: {$auth_method}");
            }
        } catch (\Exception $e) {
            return $this->fallback_manager->handle_auth_failure($e);
        }
    }

    /**
     * OAuth2 authentication with PKCE support
     */
    private function oauth2_authenticate() {
        $state = wp_generate_uuid4();
        $code_verifier = $this->generate_code_verifier();
        $code_challenge = $this->generate_code_challenge($code_verifier);

        $auth_url = add_query_arg([
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => implode(' ', $this->config['scopes']),
            'state' => $state,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256'
        ], $this->api_base . $this->endpoints['auth']);

        // Store state and verifier for validation
        update_option("smo_social_{$this->platform_slug}_auth_state", $state);
        update_option("smo_social_{$this->platform_slug}_code_verifier", $code_verifier);

        return [
            'auth_url' => $auth_url,
            'state' => $state,
            'method' => 'oauth2_pkce'
        ];
    }

    /**
     * API Key authentication method
     */
    private function api_key_authenticate() {
        if (empty($this->config['api_key'])) {
            throw new \Exception('API key not configured');
        }

        // Store API key for later use
        $this->token_storage->store_tokens([
            'access_token' => $this->config['api_key'],
            'token_type' => 'API_KEY',
            'expires' => null // API keys typically don't expire
        ]);

        return [
            'status' => 'authenticated',
            'method' => 'api_key',
            'token_type' => 'API_KEY'
        ];
    }

    /**
     * Bearer token authentication method
     */
    private function bearer_authenticate() {
        if (empty($this->config['bearer_token'])) {
            throw new \Exception('Bearer token not configured');
        }

        // Store bearer token for later use
        $this->token_storage->store_tokens([
            'access_token' => $this->config['bearer_token'],
            'token_type' => 'Bearer',
            'expires' => null // Token expiry would be handled separately
        ]);

        return [
            'status' => 'authenticated',
            'method' => 'bearer',
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchange_code_for_token($code, $state) {
        // Validate state
        $stored_state = get_option("smo_social_{$this->platform_slug}_auth_state");
        if ($state !== $stored_state) {
            throw new \Exception('Invalid state parameter');
        }

        // Get stored code verifier
        $code_verifier = get_option("smo_social_{$this->platform_slug}_code_verifier");

        $token_data = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'],
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
            'code_verifier' => $code_verifier
        ];

        $response = $this->make_authenticated_request('POST', $this->endpoints['token'], $token_data, true);

        if ($response && isset($response['access_token'])) {
            // Store encrypted tokens
            $this->token_storage->store_tokens([
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'] ?? null,
                'expires_in' => $response['expires_in'] ?? 3600,
                'scope' => $response['scope'] ?? '',
                'token_type' => $response['token_type'] ?? 'Bearer'
            ]);

            // Clean up temporary auth data
            delete_option("smo_social_{$this->platform_slug}_auth_state");
            delete_option("smo_social_{$this->platform_slug}_code_verifier");

            return true;
        }

        return false;
    }

    /**
     * Make authenticated API request with fallback handling
     */
    public function make_authenticated_request($method, $endpoint, $data = null, $is_auth = false) {
        $token_data = $this->token_storage->get_tokens();
        
        if (!$token_data && !$is_auth) {
            throw new \Exception('No valid authentication tokens found');
        }

        // Check rate limits
        if (!$this->rate_limit_handler->can_make_request()) {
            throw new \Exception('Rate limit exceeded');
        }

        $headers = [
            'User-Agent' => 'SMO Social Plugin',
            'Accept' => 'application/json'
        ];

        if ($token_data && !$is_auth) {
            $headers['Authorization'] = "{$token_data['token_type']} {$token_data['access_token']}";
        }

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        ];

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
            $headers['Content-Type'] = 'application/json';
        }

        // Make request with fallback endpoints
        return $this->make_request_with_fallback($endpoint, $args);
    }

    /**
     * Make request with automatic fallback to alternative endpoints
     */
    private function make_request_with_fallback($endpoint, $args) {
        $endpoints_to_try = [
            $this->api_base . $endpoint,
            $this->api_base . $this->endpoints[$endpoint . '_fallback'] ?? $endpoint
        ];

        $last_error = null;

        foreach ($endpoints_to_try as $endpoint_url) {
            try {
                $response = wp_remote_request($endpoint_url, $args);
                
                if (is_wp_error($response)) {
                    $error_message = method_exists($response, 'get_error_message') ? $response->get_error_message() : 'Unknown error';
                    throw new \Exception($error_message);
                }
                
                // Handle array response (from stub functions)
                if (is_array($response) && isset($response['error'])) {
                    throw new \Exception($response['error']);
                }

                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $decoded_response = json_decode($response_body, true);

                if ($response_code >= 200 && $response_code < 300) {
                    // Success - record successful endpoint
                    $this->fallback_manager->record_successful_endpoint($endpoint_url);
                    return $decoded_response;
                } elseif ($response_code === 401) {
                    // Token expired - try refresh
                    if ($this->refresh_token()) {
                        // Retry with new token
                        $args['headers']['Authorization'] = "Bearer " . $this->token_storage->get_tokens()['access_token'];
                        continue;
                    }
                }

                throw new \Exception("HTTP {$response_code}: {$response_body}");

            } catch (\Exception $e) {
                $last_error = $e;
                // Record failed endpoint
                $this->fallback_manager->record_failed_endpoint($endpoint_url, $e->getMessage());
                continue;
            }
        }

        // All endpoints failed
        throw $last_error ?: new \Exception('All endpoints failed');
    }

    /**
     * Refresh access token when expired
     */
    private function refresh_token() {
        $token_data = $this->token_storage->get_tokens();
        
        if (!$token_data['refresh_token']) {
            return false;
        }

        $refresh_data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token_data['refresh_token'],
            'client_id' => $this->config['client_id']
        ];

        try {
            $response = $this->make_authenticated_request('POST', $this->endpoints['token'], $refresh_data, true);
            
            if ($response && isset($response['access_token'])) {
                $this->token_storage->update_tokens([
                    'access_token' => $response['access_token'],
                    'expires_in' => $response['expires_in'] ?? 3600
                ]);
                return true;
            }
        } catch (\Exception $e) {
            // Refresh failed - tokens may be invalid
            $this->token_storage->clear_tokens();
        }

        return false;
    }

    /**
     * Post content to the platform
     */
    public function post_content($content_data) {
        $endpoint = $this->map_posting_endpoint($content_data['type']);
        
        $post_data = $this->format_post_data($content_data);
        
        try {
            $response = $this->make_authenticated_request('POST', $endpoint, $post_data);
            
            // Track posting success
            $this->rate_limit_handler->record_success();
            
            return $this->normalize_post_response($response);
            
        } catch (\Exception $e) {
            $this->rate_limit_handler->record_failure();
            throw $e;
        }
    }

    /**
     * Get analytics data from the platform
     */
    public function get_analytics($post_id = null, $timeframe = '30days') {
        $endpoint = $this->map_analytics_endpoint();
        
        $params = ['timeframe' => $timeframe];
        if ($post_id) {
            $params['post_id'] = $post_id;
        }

        try {
            $response = $this->make_authenticated_request('GET', $endpoint . '?' . http_build_query($params));
            return $this->normalize_analytics_response($response);
            
        } catch (\Exception $e) {
            // Return empty analytics if platform doesn't support it or request fails
            return [];
        }
    }

    /**
     * Platform-specific endpoint mapping
     */
    private function map_posting_endpoint($content_type) {
        $endpoint_map = [
            'text' => 'post',
            'image' => 'media',
            'video' => 'media',
            'link' => 'post',
            'story' => 'stories'
        ];

        return $endpoint_map[$content_type] ?? 'post';
    }

    private function map_analytics_endpoint() {
        return $this->endpoints['analytics'] ?? '/analytics';
    }

    /**
     * Format post data according to platform specifications
     */
    private function format_post_data($content_data) {
        $formatted = [
            'text' => $content_data['text'],
            'created_at' => current_time('mysql')
        ];

        // Platform-specific formatting
        switch ($this->platform_slug) {
            case 'twitter':
                $formatted = $this->format_twitter_post($content_data);
                break;
            case 'facebook':
                $formatted = $this->format_facebook_post($content_data);
                break;
            case 'instagram':
                $formatted = $this->format_instagram_post($content_data);
                break;
            case 'linkedin':
                $formatted = $this->format_linkedin_post($content_data);
                break;
        }

        return $formatted;
    }

    /**
     * Platform-specific post formatting
     */
    private function format_twitter_post($content_data) {
        return [
            'text' => substr($content_data['text'], 0, 280),
            'possibly_sensitive' => false,
            'coordinates' => null,
            'place_id' => null
        ];
    }

    private function format_facebook_post($content_data) {
        $formatted = ['message' => $content_data['text']];
        
        if (!empty($content_data['link'])) {
            $formatted['link'] = $content_data['link'];
        }
        
        if (!empty($content_data['media'])) {
            $formatted['attached_media'] = $content_data['media'];
        }

        return $formatted;
    }

    private function format_instagram_post($content_data) {
        return [
            'caption' => $content_data['text'],
            'media_type' => $content_data['type'] === 'video' ? 'VIDEO' : 'IMAGE',
            'media_url' => $content_data['media'][0]['url'] ?? null
        ];
    }

    private function format_linkedin_post($content_data) {
        return [
            'author' => 'urn:li:person:' . $this->get_platform_user_id(),
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $content_data['text']],
                    'shareMediaCategory' => 'NONE'
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];
    }

    /**
     * Normalize post response to common format
     */
    private function normalize_post_response($response) {
        return [
            'platform_id' => $this->platform_slug,
            'post_id' => $response['id'] ?? $response['post_id'] ?? null,
            'url' => $response['url'] ?? null,
            'status' => 'published',
            'published_at' => current_time('mysql'),
            'platform_response' => $response
        ];
    }

    /**
     * Normalize analytics response to common format
     */
    private function normalize_analytics_response($response) {
        // Platform-agnostic analytics normalization
        $normalized = [];
        
        if (isset($response['data'])) {
            foreach ($response['data'] as $metric) {
                $normalized[$metric['name']] = [
                    'value' => $metric['values'][0]['value'] ?? 0,
                    'period' => $metric['values'][0]['end_time'] ?? null
                ];
            }
        }

        return $normalized;
    }

    /**
     * Utility methods
     */
    private function generate_code_verifier() {
        return bin2hex(random_bytes(32));
    }

    private function generate_code_challenge($verifier) {
        return base64_encode(hash('sha256', $verifier, true));
    }

    private function get_platform_user_id() {
        $token_data = $this->token_storage->get_tokens();
        return $token_data['user_id'] ?? 'current_user';
    }

    /**
     * Get platform capabilities
     */
    public function get_capabilities() {
        return [
            'can_post' => in_array('posting', $this->capabilities),
            'can_analytics' => in_array('analytics', $this->capabilities),
            'can_media' => in_array('media', $this->capabilities),
            'max_text_length' => $this->config['max_chars'] ?? 500,
            'supports_images' => $this->config['supports_images'] ?? false,
            'supports_videos' => $this->config['supports_videos'] ?? false,
            'rate_limit' => $this->config['rate_limit'] ?? 100
        ];
    }

    /**
     * Check platform health
     */
    public function check_health() {
        try {
            $response = $this->make_authenticated_request('GET', $this->endpoints['me']);
            return [
                'status' => 'healthy',
                'last_check' => current_time('mysql'),
                'response_time' => 0 // Would be measured in production
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => current_time('mysql')
            ];
        }
    }

    /**
     * Get platform-specific preview data
     */
    public function get_preview_data($content_data) {
        $capabilities = $this->get_capabilities();
        
        return [
            'platform' => $this->platform_slug,
            'character_count' => strlen($content_data['text']),
            'character_limit' => $capabilities['max_text_length'],
            'within_limit' => strlen($content_data['text']) <= $capabilities['max_text_length'],
            'media_count' => count($content_data['media'] ?? []),
            'supports_media' => $capabilities['supports_images'] || $capabilities['supports_videos'],
            'preview_html' => $this->generate_preview_html($content_data)
        ];
    }

    /**
     * Generate platform-specific preview HTML
     */
    private function generate_preview_html($content_data) {
        $preview_class = "smo-preview smo-preview-{$this->platform_slug}";
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($preview_class); ?>">
            <div class="preview-header">
                <div class="platform-icon"><?php echo esc_html($this->config['name']); ?></div>
            </div>
            <div class="preview-content">
                <p class="preview-text"><?php echo esc_html($content_data['text']); ?></p>
                <?php if (!empty($content_data['media'])): ?>
                    <div class="preview-media">
                        <img src="<?php echo esc_url($content_data['media'][0]['url']); ?>" alt="Preview">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}