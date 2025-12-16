<?php
namespace SMO_Social\Platforms;

use SMO_Social\Core\SafeArray;

class Platform {
    private $config;
    private $logger;
    
    public function __construct($config) {
        $this->config = $config;
        $this->logger = new PlatformLogger();
    }

    // Basic getters
    public function get_slug() {
        return $this->config['slug'] ?? '';
    }

    public function get_name() {
        return $this->config['name'] ?? '';
    }

    public function get_api_base() {
        return $this->config['api_base'] ?? '';
    }

    public function get_auth_type() {
        return $this->config['auth_type'] ?? 'none';
    }

    public function get_max_chars() {
        return $this->config['max_chars'] ?? 280;
    }

    public function supports_images() {
        return $this->config['supports_images'] ?? false;
    }

    public function supports_videos() {
        return $this->config['supports_videos'] ?? false;
    }

    public function get_rate_limit() {
        return $this->config['rate_limit'] ?? 300;
    }

    public function get_features() {
        return $this->config['features'] ?? array();
    }

    // Enhanced posting with platform-specific validation
    public function post($content, $options = array()) {
        // Validate content
        $validation = $this->validate_content($content, $options);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'error' => $validation['error'],
                'code' => 'VALIDATION_FAILED'
            );
        }

        // Check rate limiting
        if (!$this->check_rate_limit()) {
            return array(
                'success' => false,
                'error' => 'Rate limit exceeded',
                'code' => 'RATE_LIMITED',
                'retry_after' => $this->get_rate_limit_reset_time()
            );
        }

        try {
            // Prepare content for platform
            $formatted_content = $this->format_content($content, $options);
            
            // Get authentication
            $auth_headers = $this->get_auth_headers();
            if (!$auth_headers['valid']) {
                return array(
                    'success' => false,
                    'error' => $auth_headers['error'],
                    'code' => 'AUTH_FAILED'
                );
            }

            // Make API call
            $response = $this->make_api_call('POST', '/posts', $formatted_content, $auth_headers['headers']);
            
            if (\is_wp_error($response)) {
                $this->logger->log_error($this->get_slug(), 'API_POST_FAILED', $response->get_error_message());
                return array(
                    'success' => false,
                    'error' => $response->get_error_message(),
                    'code' => 'API_ERROR'
                );
            }

            $result = json_decode(\wp_remote_retrieve_body($response), true);
            
            if (\wp_remote_retrieve_response_code($response) >= 200 && \wp_remote_retrieve_response_code($response) < 300) {
                $this->logger->log_success($this->get_slug(), 'POST_CREATED', $result);
                return array(
                    'success' => true,
                    'data' => $result,
                    'post_id' => $this->extract_post_id($result)
                );
            } else {
                $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown API error';
                $this->logger->log_error($this->get_slug(), 'API_ERROR', $error_message);
                return array(
                    'success' => false,
                    'error' => $error_message,
                    'code' => 'API_ERROR',
                    'details' => $result
                );
            }
            
        } catch (\Exception $e) {
            $this->logger->log_error($this->get_slug(), 'EXCEPTION', $e->getMessage());
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'EXCEPTION'
            );
        }
    }

    // Content validation specific to platform
    private function validate_content($content, $options = array()) {
        // Character limit check
        if (strlen($content) > $this->get_max_chars()) {
            return array(
                'valid' => false,
                'error' => 'Content exceeds character limit of ' . $this->get_max_chars()
            );
        }

        // Media validation
        if (isset($options['media']) && !empty($options['media'])) {
            foreach ($options['media'] as $media) {
                if (!$this->validate_media($media)) {
                    return array(
                        'valid' => false,
                        'error' => 'Invalid media format for ' . $this->get_name()
                    );
                }
            }
        }

        return array('valid' => true);
    }

    // Format content based on platform rules
    private function format_content($content, $options = array()) {
        $formatted = array(
            'content' => $content
        );

        // Add media if supported
        if ($this->supports_images() && isset($options['images'])) {
            $formatted['images'] = $options['images'];
        }

        if ($this->supports_videos() && isset($options['videos'])) {
            $formatted['videos'] = $options['videos'];
        }

        // Platform-specific formatting
        switch ($this->get_slug()) {
            case 'twitter':
                // Handle @mentions, #hashtags, links
                $formatted['content'] = $this->format_twitter_content($content);
                break;
            case 'linkedin':
                // Professional tone, proper formatting
                $formatted['content'] = $this->format_linkedin_content($content);
                break;
            case 'facebook':
                // Longer content, emoji support
                $formatted['content'] = $this->format_facebook_content($content);
                break;
        }

        return $formatted;
    }

    // Platform-specific content formatters
    private function format_twitter_content($content) {
        // URL shortening, mention validation, hashtag formatting
        $content = preg_replace('/\b(https?:\/\/[^\s]+)/', '<$1>', $content);
        return $content;
    }

    private function format_linkedin_content($content) {
        // Professional tone, line breaks for readability
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        return $content;
    }

    private function format_facebook_content($content) {
        // Preserve formatting, handle emoji
        return $content;
    }

    // Rate limiting check
    private function check_rate_limit() {
        $transient_key = 'smo_rate_limit_' . $this->get_slug();
        $current_requests = \get_transient($transient_key);
        
        if ($current_requests === false) {
            \set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($current_requests >= $this->get_rate_limit()) {
            return false;
        }
        
        \set_transient($transient_key, $current_requests + 1, HOUR_IN_SECONDS);
        return true;
    }

    private function get_rate_limit_reset_time() {
        $transient_key = 'smo_rate_limit_' . $this->get_slug();
        return \get_option('_transient_timeout_' . $transient_key, time() + HOUR_IN_SECONDS);
    }

    // Enhanced authentication
    private function get_auth_headers() {
        $token_data = $this->get_stored_token();
        
        if (!$token_data) {
            return array(
                'valid' => false,
                'error' => 'No authentication token found. Please connect your account.',
                'needs_auth' => true
            );
        }

        // Check if token needs refresh
        if ($this->needs_token_refresh($token_data)) {
            $refresh_result = $this->refresh_token($token_data);
            if (!$refresh_result['success']) {
                return array(
                    'valid' => false,
                    'error' => 'Token refresh failed: ' . $refresh_result['error'],
                    'needs_auth' => true
                );
            }
            $token_data = $refresh_result['token'];
        }

        return array(
            'valid' => true,
            'headers' => $this->build_auth_headers($token_data)
        );
    }

    protected function get_stored_token() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_platform_tokens';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE platform_slug = %s LIMIT 1",
            $this->get_slug()
        ));

        if ($result) {
            $extra_data = SafeArray::json_decode($result->extra_data ?? null, true, array());
            
            return array(
                'access_token' => $this->decrypt_token($result->access_token ?? ''),
                'refresh_token' => !empty($result->refresh_token) ? $this->decrypt_token($result->refresh_token) : null,
                'expires' => $result->token_expires ?? null,
                'extra_data' => $extra_data
            );
        }

        return null;
    }

    private function needs_token_refresh($token_data) {
        $expires = SafeArray::get_string($token_data, 'expires');
        
        if (empty($expires)) {
            return false;
        }

        // Refresh if token expires within 5 minutes
        $expires_timestamp = strtotime($expires);
        if ($expires_timestamp === false) {
            return false;
        }
        
        return $expires_timestamp < (time() + 300);
    }

    private function refresh_token($token_data) {
        $refresh_token = SafeArray::get_string($token_data, 'refresh_token');
        
        if (empty($refresh_token)) {
            return array('success' => false, 'error' => 'No refresh token available');
        }

        // Platform-specific refresh logic
        switch ($this->get_slug()) {
            case 'twitter':
                return $this->refresh_twitter_token($refresh_token);
            case 'facebook':
                return $this->refresh_facebook_token($refresh_token);
            // Add more platforms as needed
            default:
                return array('success' => false, 'error' => 'Refresh not supported for this platform');
        }
    }

    private function refresh_twitter_token($refresh_token) {
        // Implement Twitter token refresh logic
        return array('success' => false, 'error' => 'Twitter refresh not yet implemented');
    }

    private function refresh_facebook_token($refresh_token) {
        // Implement Facebook token refresh logic
        return array('success' => false, 'error' => 'Facebook refresh not yet implemented');
    }

    // Token encryption/decryption
    private function encrypt_token($token) {
        $key = $this->get_encryption_key();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt_token($encrypted_token) {
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted_token);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    private function get_encryption_key() {
        // Use WordPress auth key for encryption
        return \wp_salt('auth');
    }

    // Build auth headers based on platform
    private function build_auth_headers($token_data) {
        switch ($this->get_auth_type()) {
            case 'bearer':
                return array('Authorization' => 'Bearer ' . $token_data['access_token']);
            case 'oauth':
                return $this->build_oauth_headers($token_data);
            case 'api_key':
                return array('X-API-Key' => $token_data['access_token']);
            default:
                return array();
        }
    }

    private function build_oauth_headers($token_data) {
        // OAuth 1.0 or 2.0 implementation based on platform
        // This is a simplified version - real implementation would be more complex
        return array(
            'Authorization' => 'Bearer ' . $token_data['access_token'],
            'Content-Type' => 'application/json'
        );
    }

    // Generic API call method
    private function make_api_call($method, $endpoint, $data = array(), $headers = array()) {
        $url = $this->get_api_base() . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array_merge(array(
                'User-Agent' => 'SMO-Social/1.0',
                'Accept' => 'application/json'
            ), $headers),
            'timeout' => 30
        );

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }

        return \wp_remote_request($url, $args);
    }

    // Extract post ID from API response
    private function extract_post_id($response) {
        // Common patterns for different platforms
        $post_id = SafeArray::get($response, 'id');
        if ($post_id !== null) {
            return $post_id;
        }
        
        $post_id = SafeArray::get($response, 'data.id');
        if ($post_id !== null) {
            return $post_id;
        }
        
        $post_id = SafeArray::get($response, 'post.id');
        if ($post_id !== null) {
            return $post_id;
        }
        
        return null;
    }

    // Media validation
    private function validate_media($media) {
        // Platform-specific media validation
        switch ($this->get_slug()) {
            case 'twitter':
                return $this->validate_twitter_media($media);
            case 'instagram':
                return $this->validate_instagram_media($media);
            default:
                return true;
        }
    }

    private function validate_twitter_media($media) {
        // Twitter media validation rules
        return true; // Simplified for now
    }

    private function validate_instagram_media($media) {
        // Instagram media validation rules
        return true; // Simplified for now
    }

    // Analytics methods
    public function get_analytics($platform_post_id) {
        // Get analytics for a specific post
        $endpoint = '/posts/' . $platform_post_id . '/analytics';
        $response = $this->make_api_call('GET', $endpoint);
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function get_engagement_metrics($platform_post_id) {
        // Get engagement metrics
        $analytics = $this->get_analytics($platform_post_id);
        if (isset($analytics['error'])) {
            return $analytics;
        }

        return array(
            'likes' => $analytics['likes'] ?? 0,
            'shares' => $analytics['shares'] ?? 0,
            'comments' => $analytics['comments'] ?? 0,
            'reach' => $analytics['reach'] ?? 0,
            'impressions' => $analytics['impressions'] ?? 0
        );
    }

    // Enhanced comprehensive health check
    public function health_check() {
        $start_time = microtime(true);
        $health_report = array(
            'platform' => $this->get_slug(),
            'timestamp' => current_time('mysql'),
            'checks' => array(),
            'overall_status' => 'unknown',
            'response_time' => 0,
            'critical_issues' => array(),
            'warnings' => array()
        );

        // 1. API Connectivity Check
        $api_check = $this->check_api_connectivity();
        $health_report['checks']['api_connectivity'] = $api_check;
        
        // 2. Authentication Status Check
        $auth_check = $this->check_authentication_status();
        $health_report['checks']['authentication'] = $auth_check;
        
        // 3. Token Validity Check
        $token_check = $this->check_token_validity();
        $health_report['checks']['token_validity'] = $token_check;
        
        // 4. Rate Limit Status Check
        $rate_limit_check = $this->check_rate_limit_status();
        $health_report['checks']['rate_limit'] = $rate_limit_check;
        
        // 5. Platform-Specific Health Checks
        $platform_check = $this->perform_platform_specific_checks();
        $health_report['checks']['platform_specific'] = $platform_check;

        // Calculate response time
        $health_report['response_time'] = round((microtime(true) - $start_time) * 1000, 2);

        // Determine overall status
        $health_report = $this->determine_overall_status($health_report);
        
        // Log health check results
        $this->log_health_check_results($health_report);
        
        // Store health status for monitoring dashboard
        $this->update_health_status_cache($health_report);
        
        return $health_report;
    }

    /**
     * Check API connectivity with timeout and detailed response analysis
     */
    private function check_api_connectivity() {
        $check_start = microtime(true);
        
        try {
            // Use different endpoints for different platforms
            $health_endpoints = $this->get_health_endpoints();
            $successful_endpoints = 0;
            $total_endpoints = count($health_endpoints);
            $errors = array();

            foreach ($health_endpoints as $endpoint => $description) {
                $response = $this->make_health_check_call($endpoint);
                
                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    $response_time = round((microtime(true) - $check_start) * 1000, 2);
                    
                    if ($status_code >= 200 && $status_code < 300) {
                        $successful_endpoints++;
                    } else {
                        $errors[] = array(
                            'endpoint' => $endpoint,
                            'description' => $description,
                            'status_code' => $status_code,
                            'response_time' => $response_time
                        );
                    }
                } else {
                    $errors[] = array(
                        'endpoint' => $endpoint,
                        'description' => $description,
                        'error' => $response->get_error_message()
                    );
                }
            }

            $success_rate = ($successful_endpoints / $total_endpoints) * 100;
            
            if ($success_rate === 100) {
                return array(
                    'status' => 'healthy',
                    'message' => 'All API endpoints responding successfully',
                    'success_rate' => $success_rate,
                    'response_time' => round((microtime(true) - $check_start) * 1000, 2)
                );
            } elseif ($success_rate > 50) {
                return array(
                    'status' => 'degraded',
                    'message' => 'Some API endpoints experiencing issues',
                    'success_rate' => $success_rate,
                    'errors' => $errors
                );
            } else {
                return array(
                    'status' => 'unhealthy',
                    'message' => 'Majority of API endpoints failing',
                    'success_rate' => $success_rate,
                    'errors' => $errors
                );
            }
        } catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'API connectivity check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check authentication token status
     */
    private function check_authentication_status() {
        $token_data = $this->get_stored_token();
        
        if (!$token_data) {
            return array(
                'status' => 'critical',
                'message' => 'No authentication token found',
                'requires_auth' => true
            );
        }

        // Check token expiration
        $expires_at = strtotime($token_data['expires']);
        $now = time();
        $time_remaining = $expires_at - $now;
        
        if ($time_remaining <= 0) {
            return array(
                'status' => 'critical',
                'message' => 'Token has expired',
                'expires_at' => $token_data['expires'],
                'time_remaining' => 0
            );
        } elseif ($time_remaining < 300) { // Less than 5 minutes
            return array(
                'status' => 'warning',
                'message' => 'Token expires soon',
                'expires_at' => $token_data['expires'],
                'time_remaining' => $time_remaining,
                'auto_refresh' => true
            );
        }

        // Test token validity
        $token_test = $this->test_token_validity($token_data);
        
        return array(
            'status' => $token_test['valid'] ? 'healthy' : 'critical',
            'message' => $token_test['valid'] ? 'Authentication token is valid' : 'Token validation failed',
            'expires_at' => $token_data['expires'],
            'time_remaining' => $time_remaining,
            'token_test' => $token_test
        );
    }

    /**
     * Check token validity - wrapper for test_token_validity
     */
    private function check_token_validity() {
        $token_data = $this->get_stored_token();
        if (!$token_data) {
            return array(
                'status' => 'critical',
                'message' => 'No token found',
                'valid' => false
            );
        }
        
        return $this->test_token_validity($token_data);
    }

    /**
     * Check token validity with a lightweight API call
     */
    private function test_token_validity($token_data) {
        try {
            // Make a lightweight authenticated request to test token
            $test_endpoint = $this->get_token_test_endpoint();
            $headers = $this->build_auth_headers($token_data);
            
            $response = wp_remote_get($test_endpoint, array(
                'headers' => $headers,
                'timeout' => 10,
                'sslverify' => true
            ));

            if (is_wp_error($response)) {
                return array(
                    'valid' => false,
                    'error' => $response->get_error_message()
                );
            }

            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code === 200) {
                return array('valid' => true);
            } else {
                return array(
                    'valid' => false,
                    'status_code' => $status_code,
                    'error' => 'Token validation failed with status: ' . $status_code
                );
            }
        } catch (\Exception $e) {
            return array(
                'valid' => false,
                'error' => 'Token test failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check current rate limit status
     */
    private function check_rate_limit_status() {
        $transient_key = 'smo_rate_limit_' . $this->get_slug();
        $current_requests = get_transient($transient_key);
        $rate_limit = $this->get_rate_limit();
        
        if ($current_requests === false) {
            return array(
                'status' => 'healthy',
                'message' => 'No rate limit restrictions',
                'current_requests' => 0,
                'limit' => $rate_limit,
                'reset_time' => time() + HOUR_IN_SECONDS
            );
        }
        
        $usage_percentage = ($current_requests / $rate_limit) * 100;
        $reset_time = get_option('_transient_timeout_' . $transient_key, time() + HOUR_IN_SECONDS);
        
        if ($usage_percentage >= 100) {
            return array(
                'status' => 'critical',
                'message' => 'Rate limit exceeded',
                'current_requests' => $current_requests,
                'limit' => $rate_limit,
                'usage_percentage' => $usage_percentage,
                'reset_time' => $reset_time
            );
        } elseif ($usage_percentage >= 80) {
            return array(
                'status' => 'warning',
                'message' => 'Approaching rate limit',
                'current_requests' => $current_requests,
                'limit' => $rate_limit,
                'usage_percentage' => $usage_percentage,
                'reset_time' => $reset_time
            );
        }
        
        return array(
            'status' => 'healthy',
            'message' => 'Rate limit status normal',
            'current_requests' => $current_requests,
            'limit' => $rate_limit,
            'usage_percentage' => $usage_percentage,
            'reset_time' => $reset_time
        );
    }

    /**
     * Perform platform-specific health checks
     */
    private function perform_platform_specific_checks() {
        $platform_slug = $this->get_slug();
        $checks = array();
        
        switch ($platform_slug) {
            case 'twitter':
                $checks = $this->perform_twitter_health_checks();
                break;
            case 'facebook':
                $checks = $this->perform_facebook_health_checks();
                break;
            case 'instagram':
                $checks = $this->perform_instagram_health_checks();
                break;
            case 'linkedin':
                $checks = $this->perform_linkedin_health_checks();
                break;
            default:
                $checks = array(
                    'status' => 'healthy',
                    'message' => 'No platform-specific checks defined'
                );
        }
        
        return $checks;
    }

    /**
     * Twitter-specific health checks
     */
    private function perform_twitter_health_checks() {
        try {
            // Check API v2 endpoint availability
            $response = $this->make_health_check_call('/2/tweets/search/recent?query=test&max_results=1');
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                return array(
                    'status' => ($status_code === 200) ? 'healthy' : 'degraded',
                    'api_version' => 'v2',
                    'endpoints_working' => array('tweets', 'search')
                );
            }
            
            return array(
                'status' => 'unhealthy',
                'message' => 'Twitter API v2 endpoints not responding'
            );
        } catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'Twitter health check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Facebook-specific health checks
     */
    private function perform_facebook_health_checks() {
        try {
            // Check Graph API and Business API availability
            $graph_response = $this->make_health_check_call('/me?fields=id,name');
            
            if (!is_wp_error($graph_response)) {
                $status_code = wp_remote_retrieve_response_code($graph_response);
                return array(
                    'status' => ($status_code === 200) ? 'healthy' : 'degraded',
                    'api_version' => 'graph',
                    'graph_api_working' => true
                );
            }
            
            return array(
                'status' => 'unhealthy',
                'message' => 'Facebook Graph API not responding'
            );
        } catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'Facebook health check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Instagram-specific health checks
     */
    private function perform_instagram_health_checks() {
        try {
            // Check Instagram Basic Display API
            $response = $this->make_health_check_call('/me?fields=id,username');
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                return array(
                    'status' => ($status_code === 200) ? 'healthy' : 'degraded',
                    'api_version' => 'basic',
                    'endpoints_working' => array('user_info')
                );
            }
            
            return array(
                'status' => 'unhealthy',
                'message' => 'Instagram API not responding'
            );
        } catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'Instagram health check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * LinkedIn-specific health checks
     */
    private function perform_linkedin_health_checks() {
        try {
            // Check LinkedIn API v2
            $response = $this->make_health_check_call('/v2/people/~');
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                return array(
                    'status' => ($status_code === 200) ? 'healthy' : 'degraded',
                    'api_version' => 'v2',
                    'endpoints_working' => array('people', 'profile')
                );
            }
            
            return array(
                'status' => 'unhealthy',
                'message' => 'LinkedIn API not responding'
            );
        } catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'LinkedIn health check failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Make a health check API call with proper timeout and error handling
     */
    private function make_health_check_call($endpoint) {
        $auth_headers = $this->get_auth_headers();
        
        if (!$auth_headers['valid']) {
            return new \WP_Error('auth_failed', 'Authentication required for health check');
        }

        $args = array(
            'headers' => array_merge(
                $auth_headers['headers'],
                array(
                    'User-Agent' => 'SMO-Social-HealthCheck/1.0',
                    'Accept' => 'application/json'
                )
            ),
            'timeout' => 15,
            'sslverify' => true
        );

        $url = $this->get_api_base() . $endpoint;
        return wp_remote_get($url, $args);
    }

    /**
     * Get health check endpoints for the platform
     */
    private function get_health_endpoints() {
        $platform_slug = $this->get_slug();
        
        switch ($platform_slug) {
            case 'twitter':
                return array(
                    '/2/tweets/search/recent?query=test&max_results=1' => 'Recent tweets endpoint',
                    '/2/users/me?user.fields=id,name,username' => 'User info endpoint'
                );
            case 'facebook':
                return array(
                    '/me?fields=id,name' => 'Graph API user endpoint',
                    '/me/accounts?fields=id,name,access_token' => 'Pages endpoint'
                );
            case 'instagram':
                return array(
                    '/me?fields=id,username' => 'User profile endpoint',
                    '/me/media?fields=id,caption' => 'Media endpoint'
                );
            case 'linkedin':
                return array(
                    '/v2/people/~' => 'User profile endpoint',
                    '/v2/organizationalEntityAcls?q=roleAssignee' => 'Organization access endpoint'
                );
            default:
                return array('/me' => 'Basic user endpoint');
        }
    }

    /**
     * Get token test endpoint for the platform
     */
    private function get_token_test_endpoint() {
        $platform_slug = $this->get_slug();
        
        switch ($platform_slug) {
            case 'twitter':
                return $this->get_api_base() . '/2/users/me';
            case 'facebook':
                return $this->get_api_base() . '/me';
            case 'instagram':
                return $this->get_api_base() . '/me';
            case 'linkedin':
                return $this->get_api_base() . '/v2/people/~';
            default:
                return $this->get_api_base() . '/me';
        }
    }

    /**
     * Determine overall health status from individual checks
     */
    private function determine_overall_status($health_report) {
        $critical_count = 0;
        $warning_count = 0;
        $total_checks = count($health_report['checks']);

        foreach ($health_report['checks'] as $check_name => $check_result) {
            switch ($check_result['status']) {
                case 'critical':
                    $critical_count++;
                    $health_report['critical_issues'][] = $check_name . ': ' . $check_result['message'];
                    break;
                case 'warning':
                case 'degraded':
                    $warning_count++;
                    $health_report['warnings'][] = $check_name . ': ' . $check_result['message'];
                    break;
            }
        }

        if ($critical_count > 0) {
            $health_report['overall_status'] = 'critical';
        } elseif ($warning_count > 0) {
            $health_report['overall_status'] = 'warning';
        } else {
            $health_report['overall_status'] = 'healthy';
        }

        return $health_report;
    }

    /**
     * Log health check results for monitoring and debugging
     */
    private function log_health_check_results($health_report) {
        $log_entry = array(
            'platform' => $this->get_slug(),
            'status' => $health_report['overall_status'],
            'response_time' => $health_report['response_time'],
            'checks_performed' => count($health_report['checks']),
            'critical_issues' => count($health_report['critical_issues']),
            'warnings' => count($health_report['warnings'])
        );

        // Use the existing logger
        if ($health_report['overall_status'] === 'critical') {
            $this->logger->log_error($this->get_slug(), 'HEALTH_CHECK_CRITICAL', 
                json_encode($health_report['critical_issues']));
        } elseif ($health_report['overall_status'] === 'warning') {
            $this->logger->log_error($this->get_slug(), 'HEALTH_CHECK_WARNING', 
                json_encode($health_report['warnings']));
        }

        // Store detailed results for monitoring dashboard
        $this->store_detailed_health_data($health_report);
    }

    /**
     * Store detailed health data for monitoring dashboard
     */
    private function update_health_status_cache($health_report) {
        $cache_key = 'smo_health_status_' . $this->get_slug();
        $cache_data = array(
            'last_check' => $health_report['timestamp'],
            'status' => $health_report['overall_status'],
            'response_time' => $health_report['response_time'],
            'summary' => array(
                'total_checks' => count($health_report['checks']),
                'critical_issues' => count($health_report['critical_issues']),
                'warnings' => count($health_report['warnings'])
            )
        );

        set_transient($cache_key, $cache_data, 300); // Cache for 5 minutes
    }

    /**
     * Store detailed health data for trend analysis
     */
    private function store_detailed_health_data($health_report) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_health_logs';
        
        // Create table if it doesn't exist
        $this->create_health_logs_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'platform_slug' => $this->get_slug(),
                'check_timestamp' => current_time('mysql'),
                'overall_status' => $health_report['overall_status'],
                'response_time' => $health_report['response_time'],
                'checks_data' => json_encode($health_report['checks']),
                'critical_issues' => json_encode($health_report['critical_issues']),
                'warnings' => json_encode($health_report['warnings'])
            ),
            array('%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
    }

    /**
     * Create health logs table if it doesn't exist
     */
    private function create_health_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_health_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform_slug varchar(50) NOT NULL,
            check_timestamp datetime NOT NULL,
            overall_status varchar(20) NOT NULL,
            response_time decimal(10,3) NOT NULL,
            checks_data longtext NOT NULL,
            critical_issues longtext,
            warnings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY platform_slug (platform_slug),
            KEY check_timestamp (check_timestamp),
            KEY overall_status (overall_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if the current operation is in test mode
     *
     * @param array $data Data array to check for test_mode flag
     * @return bool True if test mode is enabled
     */
    protected function is_test_mode($data = array()) {
        return isset($data['test_mode']) && $data['test_mode'] === true;
    }

    /**
     * Get standardized test response for mock data
     *
     * @param string $key The key for the test response (e.g., 'media_id', 'post_id')
     * @param string $prefix Optional prefix for the test ID (defaults to platform slug)
     * @return array Standardized test response
     */
    protected function get_test_response($key, $prefix = null) {
        if ($prefix === null) {
            $prefix = $this->get_slug();
        }
        
        return array(
            $key => 'test_' . $prefix . '_' . time(),
            'test_mode' => true,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Get standardized not implemented error response
     *
     * @param string $feature Name of the feature that's not implemented
     * @return array Standardized error response
     */
    protected function get_not_implemented_response($feature) {
        return array(
            'success' => false,
            'error' => $feature . ' functionality not yet implemented for ' . $this->get_name(),
            'code' => 'FEATURE_NOT_IMPLEMENTED',
            'suggestion' => 'This feature will be available in a future update'
        );
    }
}

// Platform logger class
class PlatformLogger {
    public function log_error($platform, $code, $message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'smo_activity_logs';
        $wpdb->insert(
            $table_name,
            array(
                'action' => 'PLATFORM_ERROR',
                'resource_type' => 'platform',
                'resource_id' => $platform,
                'details' => json_encode(array(
                    'code' => $code,
                    'message' => $message,
                    'timestamp' => current_time('mysql')
                )),
                'created_at' => current_time('mysql')
            )
        );
    }

    public function log_success($platform, $action, $data) {
        // Log successful operations (optional, for debugging)
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log("SMO-Social [$platform] $action: " . json_encode($data));
        }
    }
}