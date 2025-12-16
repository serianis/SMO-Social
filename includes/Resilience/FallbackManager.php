<?php
namespace SMO_Social\Resilience;

/**
 * Platform Resilience Engine with Fallback Management
 * Handles API failures, endpoint switching, and platform health monitoring
 */
class FallbackManager {
    private $platform_slug;
    private $endpoints_health;
    private $failure_threshold;
    private $recovery_time;
    private $health_check_interval;

    public function __construct($platform_slug) {
        $this->platform_slug = $platform_slug;
        $this->failure_threshold = 3; // Failures before marking endpoint as unhealthy
        $this->recovery_time = 300; // 5 minutes before retrying failed endpoints
        $this->health_check_interval = 600; // 10 minutes between health checks
        
        $this->endpoints_health = get_option("smo_social_{$platform_slug}_endpoint_health", []);
    }

    /**
     * Record successful endpoint usage
     */
    public function record_successful_endpoint($endpoint_url) {
        if (!isset($this->endpoints_health[$endpoint_url])) {
            $this->endpoints_health[$endpoint_url] = [];
        }

        $this->endpoints_health[$endpoint_url]['last_success'] = time();
        $this->endpoints_health[$endpoint_url]['success_count'] = 
            ($this->endpoints_health[$endpoint_url]['success_count'] ?? 0) + 1;
        $this->endpoints_health[$endpoint_url]['failure_count'] = 0;
        $this->endpoints_health[$endpoint_url]['status'] = 'healthy';

        $this->save_endpoint_health();
    }

    /**
     * Record failed endpoint usage
     */
    public function record_failed_endpoint($endpoint_url, $error_message) {
        if (!isset($this->endpoints_health[$endpoint_url])) {
            $this->endpoints_health[$endpoint_url] = [];
        }

        $this->endpoints_health[$endpoint_url]['last_failure'] = time();
        $this->endpoints_health[$endpoint_url]['failure_count'] = 
            ($this->endpoints_health[$endpoint_url]['failure_count'] ?? 0) + 1;
        $this->endpoints_health[$endpoint_url]['last_error'] = $error_message;

        // Mark as unhealthy if failure threshold exceeded
        if ($this->endpoints_health[$endpoint_url]['failure_count'] >= $this->failure_threshold) {
            $this->endpoints_health[$endpoint_url]['status'] = 'unhealthy';
            $this->endpoints_health[$endpoint_url]['unhealthy_since'] = time();
        }

        $this->save_endpoint_health();
    }

    /**
     * Get best available endpoint for platform operations
     */
    public function get_best_endpoint($operation_type = 'default') {
        $available_endpoints = $this->get_platform_endpoints($operation_type);
        
        // Filter healthy endpoints
        $healthy_endpoints = [];
        $unhealthy_endpoints = [];

        foreach ($available_endpoints as $endpoint) {
            $health = $this->endpoints_health[$endpoint] ?? [];
            
            if ($this->is_endpoint_healthy($health)) {
                $healthy_endpoints[] = $endpoint;
            } else {
                $unhealthy_endpoints[] = $endpoint;
            }
        }

        // Return best healthy endpoint or null if all are unhealthy
        if (!empty($healthy_endpoints)) {
            return $this->select_best_endpoint($healthy_endpoints);
        }

        // If all endpoints are unhealthy, try to recover the least bad one
        if (!empty($unhealthy_endpoints)) {
            return $this->attempt_endpoint_recovery($unhealthy_endpoints);
        }

        return null;
    }

    /**
     * Check if an endpoint is considered healthy
     */
    private function is_endpoint_healthy($health) {
        if (empty($health)) {
            return true; // Never tested, assume healthy
        }

        // Check status
        if (isset($health['status']) && $health['status'] === 'unhealthy') {
            // Check if enough time has passed for recovery
            if (isset($health['unhealthy_since'])) {
                return (time() - $health['unhealthy_since']) > $this->recovery_time;
            }
            return false;
        }

        // Check if recently failed
        if (isset($health['last_failure'])) {
            $time_since_failure = time() - $health['last_failure'];
            if ($time_since_failure < 60) { // 1 minute cooldown after failure
                return false;
            }
        }

        return true;
    }

    /**
     * Select the best endpoint from available options
     */
    private function select_best_endpoint($endpoints) {
        if (count($endpoints) === 1) {
            return $endpoints[0];
        }

        // Score endpoints based on performance metrics
        $scored_endpoints = [];
        foreach ($endpoints as $endpoint) {
            $health = $this->endpoints_health[$endpoint] ?? [];
            $score = $this->calculate_endpoint_score($health);
            $scored_endpoints[] = ['endpoint' => $endpoint, 'score' => $score];
        }

        // Sort by score (highest first)
        usort($scored_endpoints, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $scored_endpoints[0]['endpoint'];
    }

    /**
     * Calculate endpoint performance score
     */
    private function calculate_endpoint_score($health) {
        $score = 100; // Base score

        // Penalize for recent failures
        if (isset($health['failure_count'])) {
            $score -= ($health['failure_count'] * 10);
        }

        // Bonus for recent successes
        if (isset($health['success_count'])) {
            $score += min($health['success_count'] * 2, 20);
        }

        // Penalize for old last success
        if (isset($health['last_success'])) {
            $time_since_success = time() - $health['last_success'];
            if ($time_since_success > 3600) { // 1 hour
                $score -= 20;
            }
        }

        return max($score, 0);
    }

    /**
     * Attempt to recover an unhealthy endpoint
     */
    private function attempt_endpoint_recovery($unhealthy_endpoints) {
        // Sort by recovery priority (least failed, oldest failure first)
        usort($unhealthy_endpoints, function($a, $b) {
            $health_a = $this->endpoints_health[$a] ?? [];
            $health_b = $this->endpoints_health[$b] ?? [];
            
            $failures_a = $health_a['failure_count'] ?? 0;
            $failures_b = $health_b['failure_count'] ?? 0;
            
            if ($failures_a !== $failures_b) {
                return $failures_a - $failures_b;
            }
            
            // If same failure count, pick oldest failure
            $failure_time_a = $health_a['last_failure'] ?? 0;
            $failure_time_b = $health_b['last_failure'] ?? 0;
            
            return $failure_time_a - $failure_time_b;
        });

        // Try the endpoint with best recovery chance
        $endpoint_to_retry = $unhealthy_endpoints[0];
        
        // Perform health check
        if ($this->perform_health_check($endpoint_to_retry)) {
            $this->endpoints_health[$endpoint_to_retry]['status'] = 'healthy';
            $this->endpoints_health[$endpoint_to_retry]['failure_count'] = 0;
            $this->save_endpoint_health();
            
            return $endpoint_to_retry;
        }

        return null;
    }

    /**
     * Perform health check on specific endpoint
     */
    private function perform_health_check($endpoint) {
        // Implementation would make a lightweight test request
        // For now, simulate a 70% success rate for unhealthy endpoints
        return (rand(1, 100) <= 70);
    }

    /**
     * Get platform-specific endpoints
     */
    private function get_platform_endpoints($operation_type) {
        $base_endpoints = [
            'https://api.twitter.com/2',
            'https://api.twitter.com/1.1',
            'https://graph.facebook.com/v18.0',
            'https://graph.facebook.com/v17.0',
            'https://graph.facebook.com/v16.0'
        ];

        // Platform-specific endpoint configurations
        $platform_endpoints = [
            'twitter' => [
                'https://api.twitter.com/2',
                'https://api.twitter.com/1.1'
            ],
            'facebook' => [
                'https://graph.facebook.com/v18.0',
                'https://graph.facebook.com/v17.0',
                'https://graph.facebook.com/v16.0'
            ],
            'instagram' => [
                'https://graph.facebook.com/v18.0', // Instagram uses Facebook Graph API
                'https://graph.facebook.com/v17.0'
            ],
            'linkedin' => [
                'https://api.linkedin.com/v2',
                'https://api.linkedin.com/v1'
            ]
        ];

        return $platform_endpoints[$this->platform_slug] ?? $base_endpoints;
    }

    /**
     * Handle authentication failures with graceful degradation
     */
    public function handle_auth_failure($exception) {
        // Log the failure
        error_log("SMO Social - Auth failure for {$this->platform_slug}: " . $exception->getMessage());
        
        // Clear potentially corrupted tokens
        $this->clear_auth_tokens();
        
        // Attempt to use alternative authentication method if available
        if ($this->has_alternative_auth()) {
            return $this->try_alternative_auth();
        }
        
        // Return auth URL for manual retry
        return [
            'status' => 'auth_failed',
            'message' => 'Authentication failed. Please re-authenticate.',
            'retry_url' => $this->generate_auth_url(),
            'fallback_available' => $this->has_alternative_auth()
        ];
    }

    /**
     * Clear authentication tokens
     */
    private function clear_auth_tokens() {
        delete_option("smo_social_{$this->platform_slug}_tokens");
        delete_option("smo_social_{$this->platform_slug}_auth_state");
        delete_option("smo_social_{$this->platform_slug}_code_verifier");
    }

    /**
     * Check if alternative authentication methods are available
     */
    private function has_alternative_auth() {
        $platform_config = $this->get_platform_config();
        return !empty($platform_config['alternative_auth_methods']);
    }

    /**
     * Try alternative authentication method
     */
    private function try_alternative_auth() {
        $config = $this->get_platform_config();
        
        foreach ($config['alternative_auth_methods'] as $method) {
            switch ($method) {
                case 'api_key':
                    return $this->api_key_auth();
                case 'app_secret':
                    return $this->app_secret_auth();
                case 'manual_token':
                    return $this->manual_token_auth();
            }
        }
        
        return null;
    }

    /**
     * Get platform configuration
     */
    private function get_platform_config() {
        // This would load from the platform's JSON config file
        $config_file = SMO_SOCIAL_PLUGIN_DIR . "drivers/{$this->platform_slug}.json";
        
        if (file_exists($config_file)) {
            return json_decode(file_get_contents($config_file), true);
        }
        
        return [];
    }

    /**
     * Generate authentication URL
     */
    private function generate_auth_url() {
        $config = $this->get_platform_config();
        
        if (!empty($config['auth_url'])) {
            return add_query_arg([
                'client_id' => $config['client_id'] ?? '',
                'redirect_uri' => $config['redirect_uri'] ?? '',
                'scope' => implode(' ', $config['scopes'] ?? []),
                'response_type' => 'code',
                'state' => wp_generate_uuid4()
            ], $config['auth_url']);
        }
        
        return null;
    }

    /**
     * Get platform health status
     */
    public function get_platform_health() {
        $health_data = [
            'platform' => $this->platform_slug,
            'overall_status' => 'healthy',
            'endpoints' => [],
            'last_check' => current_time('mysql')
        ];

        foreach ($this->endpoints_health as $endpoint => $health) {
            $health_data['endpoints'][$endpoint] = [
                'status' => $health['status'] ?? 'unknown',
                'success_count' => $health['success_count'] ?? 0,
                'failure_count' => $health['failure_count'] ?? 0,
                'last_success' => $health['last_success'] ?? null,
                'last_failure' => $health['last_failure'] ?? null
            ];
        }

        // Determine overall platform health
        $unhealthy_endpoints = array_filter($this->endpoints_health, function($health) {
            return ($health['status'] ?? 'unknown') === 'unhealthy';
        });

        if (count($unhealthy_endpoints) > 0) {
            $health_data['overall_status'] = 'degraded';
        }

        if (count($unhealthy_endpoints) === count($this->endpoints_health)) {
            $health_data['overall_status'] = 'unhealthy';
        }

        return $health_data;
    }

    /**
     * Reset endpoint health (for testing or manual recovery)
     */
    public function reset_endpoint_health($endpoint = null) {
        if ($endpoint) {
            unset($this->endpoints_health[$endpoint]);
        } else {
            $this->endpoints_health = [];
        }
        
        $this->save_endpoint_health();
    }

    /**
     * Save endpoint health data to database
     */
    private function save_endpoint_health() {
        update_option("smo_social_{$this->platform_slug}_endpoint_health", $this->endpoints_health, false);
    }

    /**
     * Perform comprehensive platform health check
     */
    public function perform_comprehensive_health_check() {
        $health_report = [
            'platform' => $this->platform_slug,
            'check_time' => current_time('mysql'),
            'results' => []
        ];

        $endpoints = $this->get_platform_endpoints('comprehensive_check');

        foreach ($endpoints as $endpoint) {
            $result = $this->check_endpoint_detailed($endpoint);
            $health_report['results'][$endpoint] = $result;
        }

        // Save comprehensive health report
        update_option("smo_social_{$this->platform_slug}_comprehensive_health", $health_report, false);

        return $health_report;
    }

    /**
     * Detailed endpoint health check
     */
    private function check_endpoint_detailed($endpoint) {
        $start_time = microtime(true);
        
        try {
            // Make a lightweight test request
            $response = wp_remote_get($endpoint . '/me', [
                'timeout' => 10,
                'user-agent' => 'SMO Social Health Check'
            ]);

            $response_time = microtime(true) - $start_time;
            $response_code = wp_remote_retrieve_response_code($response);

            if (is_wp_error($response)) {
                return [
                    'status' => 'error',
                    'response_time' => $response_time,
                    'error' => $response->get_error_message()
                ];
            }

            return [
                'status' => ($response_code >= 200 && $response_code < 300) ? 'healthy' : 'degraded',
                'response_time' => $response_time,
                'http_code' => $response_code
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'response_time' => microtime(true) - $start_time,
                'error' => $e->getMessage()
            ];
        }
    }
}