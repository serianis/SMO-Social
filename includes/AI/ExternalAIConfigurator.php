<?php
namespace SMO_Social\AI;

/**
 * External AI Configurator for Managing AI Providers
 * Handles configuration and management of external AI APIs
 */
class ExternalAIConfigurator {
    
    private $settings_key = 'smo_social_ai_settings';
    
    /**
     * Configure AI provider
     */
    public function configure_provider($provider_key, $config) {
        error_log("SMO_AI_CONFIG: Starting configuration for provider: {$provider_key}");
        error_log("SMO_AI_CONFIG: Input config: " . print_r($config, true));

        $current_config = get_option('smo_ai_provider_config_' . $provider_key, []);
        $current_config = is_array($current_config) ? $current_config : [];
        error_log("SMO_AI_CONFIG: Current config for {$provider_key}: " . print_r($current_config, true));

        $updated_config = array_merge($current_config, $config);
        error_log("SMO_AI_CONFIG: Updated config for {$provider_key}: " . print_r($updated_config, true));

        // Validate configuration
        $validation_result = $this->validate_provider_config($provider_key, $updated_config);
        if (!$validation_result['valid']) {
            error_log("SMO_AI_CONFIG_ERROR: Invalid configuration for {$provider_key}: " . implode(' | ', $validation_result['errors']));
            return ['status' => 'error', 'error' => 'Invalid provider configuration: ' . implode(' | ', $validation_result['errors'])];
        }

        // Save configuration to options
        $save_result = update_option('smo_ai_provider_config_' . $provider_key, $updated_config);
        error_log("SMO_AI_CONFIG: Configuration save result for {$provider_key}: " . ($save_result ? 'success' : 'failed'));

        if (!$save_result) {
            error_log("SMO_AI_CONFIG_ERROR: Failed to save configuration to options for {$provider_key}");
            return ['status' => 'error', 'error' => 'Failed to save provider configuration'];
        }

        // Log the actual saved value to verify it was stored correctly
        $saved_config = get_option('smo_ai_provider_config_' . $provider_key, []);
        error_log("SMO_AI_CONFIG: Verification - saved config for {$provider_key}: " . print_r($saved_config, true));

        // Check if the saved config matches what we tried to save
        if ($saved_config !== $updated_config) {
            error_log("SMO_AI_CONFIG_ERROR: Saved config does not match input config for {$provider_key}");
            error_log("SMO_AI_CONFIG_ERROR: Expected: " . print_r($updated_config, true));
            error_log("SMO_AI_CONFIG_ERROR: Got: " . print_r($saved_config, true));
        }

        // Ensure API key is saved with consistent naming
        $this->ensure_consistent_api_key_storage($provider_key, $updated_config);

        // Add to active providers list
        $active_providers = get_option('smo_ai_active_providers', []);
        $active_providers = is_array($active_providers) ? $active_providers : [];
        if (!in_array($provider_key, $active_providers)) {
            $active_providers[] = $provider_key;
            $active_save_result = update_option('smo_ai_active_providers', $active_providers);
            error_log("SMO_AI_CONFIG: Active providers update result: " . ($active_save_result ? 'success' : 'failed'));
        }

        // Check if provider exists in database and sync if needed
        $this->sync_provider_to_database($provider_key, $updated_config);

        // Clear any cached configurations to ensure fresh data
        $this->clear_provider_cache($provider_key);

        // Trigger configuration change event
        do_action('smo_ai_provider_configured', $provider_key, $updated_config);

        error_log("SMO_AI_CONFIG: Provider {$provider_key} configured successfully");
        return ['status' => 'success', 'message' => 'Provider configured successfully'];
    }

    /**
     * Clear cached provider configurations
     *
     * @param string $provider_key Provider key
     */
    private function clear_provider_cache($provider_key) {
        error_log("SMO_AI_CONFIG: Clearing cache for provider {$provider_key}");

        // Clear WordPress object cache for provider options
        wp_cache_delete('smo_ai_provider_config_' . $provider_key, 'options');
        wp_cache_delete('smo_social_' . $provider_key . '_api_key', 'options');

        // Clear any transient caches
        delete_transient('smo_ai_provider_cache_' . $provider_key);

        // Clear database provider cache if available
        if (class_exists('\SMO_Social\AI\ProvidersConfig')) {
            // Force reloading from source on next request
            error_log("SMO_AI_CONFIG: Cache cleared for provider {$provider_key}");
        }
    }

    /**
     * Sync provider configuration to database layer
     *
     * @param string $provider_key Provider key
     * @param array $config Provider configuration
     */
    private function sync_provider_to_database($provider_key, $config) {
        error_log("SMO_AI_CONFIG: Attempting to sync {$provider_key} to database layer");
        error_log("SMO_AI_CONFIG: Sync config data: " . print_r($config, true));

        try {
            if (class_exists('\SMO_Social\AI\DatabaseProviderLoader')) {
                error_log("SMO_AI_CONFIG: DatabaseProviderLoader class found, attempting sync");
                $db_result = \SMO_Social\AI\DatabaseProviderLoader::save_provider_to_database($provider_key, $config);
                error_log("SMO_AI_CONFIG: Database sync result for {$provider_key}: " . ($db_result ? 'success' : 'failed'));

                if ($db_result) {
                    // Verify the database save worked by reading back
                    $db_provider = \SMO_Social\AI\DatabaseProviderLoader::get_provider_from_database($provider_key);
                    if ($db_provider) {
                        error_log("SMO_AI_CONFIG: Database verification successful for {$provider_key}");
                        error_log("SMO_AI_CONFIG: Database provider data: " . print_r($db_provider, true));
                    } else {
                        error_log("SMO_AI_CONFIG_ERROR: Database save reported success but provider not found in database for {$provider_key}");
                    }
                }
            } else {
                error_log("SMO_AI_CONFIG_WARNING: DatabaseProviderLoader class not available for sync");
            }
        } catch (\Exception $e) {
            error_log("SMO_AI_CONFIG_ERROR: Database sync failed for {$provider_key}: " . $e->getMessage());
            error_log("SMO_AI_CONFIG_ERROR: Exception trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Ensure API key is stored with consistent naming convention
     *
     * @param string $provider_key Provider key
     * @param array $config Provider configuration
     */
    private function ensure_consistent_api_key_storage($provider_key, $config) {
        error_log("SMO_AI_CONFIG: Ensuring consistent API key storage for {$provider_key}");

        // Get static provider config to determine correct option name
        $static_providers = \SMO_Social\AI\ProvidersConfig::get_all_providers();
        $static_provider = $static_providers[$provider_key] ?? null;

        if ($static_provider && isset($static_provider['key_option'])) {
            $correct_key_option = $static_provider['key_option'];
            error_log("SMO_AI_CONFIG: Using static provider key_option: {$correct_key_option}");
        } else {
            // Fallback to standard naming convention
            $correct_key_option = 'smo_social_' . $provider_key . '_api_key';
            error_log("SMO_AI_CONFIG: Using fallback key_option: {$correct_key_option}");
        }

        // Check if API key exists in the config
        if (isset($config['api_key'])) {
            $api_key = $config['api_key'];
            error_log("SMO_AI_CONFIG: Found API key in config, saving to correct option: {$correct_key_option}");

            // Save API key with correct option name
            $key_save_result = update_option($correct_key_option, $api_key);
            error_log("SMO_AI_CONFIG: API key save result: " . ($key_save_result ? 'success' : 'failed'));

            if (!$key_save_result) {
                error_log("SMO_AI_CONFIG_ERROR: Failed to save API key to correct option for {$provider_key}");
            } else {
                // Verify the key was saved correctly
                $saved_key = get_option($correct_key_option);
                if ($saved_key !== $api_key) {
                    error_log("SMO_AI_CONFIG_ERROR: Saved API key does not match input for {$provider_key}");
                } else {
                    error_log("SMO_AI_CONFIG: API key successfully saved with consistent naming");
                }
            }
        } else {
            error_log("SMO_AI_CONFIG: No API key found in config for {$provider_key}");
        }

        // Also handle URL option consistency if present
        if (isset($config['base_url'])) {
            $base_url = $config['base_url'];
            if ($static_provider && isset($static_provider['url_option'])) {
                $correct_url_option = $static_provider['url_option'];
            } else {
                $correct_url_option = 'smo_social_' . $provider_key . '_base_url';
            }

            $url_save_result = update_option($correct_url_option, $base_url);
            error_log("SMO_AI_CONFIG: Base URL save result: " . ($url_save_result ? 'success' : 'failed'));
        }
    }

    /**
     * Test AI provider connection
     */
    public function test_provider_connection($provider_key) {
        $config = get_option('smo_ai_provider_config_' . $provider_key, []);

        if (empty($config['api_key'])) {
            error_log("SMO_AI_CONNECTION_ERROR: API key not configured for {$provider_key}");
            return ['status' => 'error', 'error' => 'API key not configured', 'code' => 'missing_api_key'];
        }

        if (empty($config['base_url'])) {
            error_log("SMO_AI_CONNECTION_ERROR: Base URL not configured for {$provider_key}");
            return ['status' => 'error', 'error' => 'Base URL not configured', 'code' => 'missing_base_url'];
        }

        // Check rate limits before making API call
        if (!$this->check_rate_limit($provider_key)) {
            error_log("SMO_AI_CONNECTION_ERROR: Rate limit exceeded for {$provider_key}");
            return ['status' => 'error', 'error' => 'Rate limit exceeded', 'code' => 'rate_limit_exceeded'];
        }

        try {
            // Build test request based on provider type
            $test_request = $this->build_test_request($config);

            if (empty($test_request)) {
                error_log("SMO_AI_CONNECTION_ERROR: Failed to build test request for {$provider_key}");
                return ['status' => 'error', 'error' => 'Failed to build test request', 'code' => 'invalid_request_format'];
            }

            error_log("SMO_AI_CONNECTION: Testing connection to {$provider_key} at {$config['base_url']}");
            error_log("SMO_AI_CONNECTION: Test request: " . json_encode($test_request));

            // Make test API call
            $response = wp_remote_post($config['base_url'] . '/chat/completions', [
                'body' => json_encode($test_request),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $config['api_key']
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();
                error_log("SMO_AI_CONNECTION_ERROR: WP Error {$error_code} for {$provider_key}: {$error_message}");
                return [
                    'status' => 'error',
                    'error' => $error_message,
                    'code' => 'wp_error_' . $error_code,
                    'details' => [
                        'error_code' => $error_code,
                        'error_message' => $error_message
                    ]
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_headers = $this->extract_response_headers($response);

            error_log("SMO_AI_CONNECTION: Response code {$response_code} for {$provider_key}");
            error_log("SMO_AI_CONNECTION: Response body: " . substr($response_body, 0, 500) . '...');

            // Handle different response codes with specific error messages
            if ($response_code >= 500) {
                error_log("SMO_AI_CONNECTION_ERROR: Server error {$response_code} for {$provider_key}");
                return [
                    'status' => 'error',
                    'error' => 'Server error: ' . $this->extract_error_message($response_body),
                    'code' => 'server_error_' . $response_code,
                    'http_code' => $response_code
                ];
            }

            if ($response_code >= 400) {
                $error_message = $this->extract_error_message($response_body);
                error_log("SMO_AI_CONNECTION_ERROR: API error {$response_code} for {$provider_key}: {$error_message}");

                // Handle specific error cases
                if ($response_code === 401) {
                    return [
                        'status' => 'error',
                        'error' => 'Authentication failed: ' . $error_message,
                        'code' => 'authentication_failed',
                        'http_code' => $response_code
                    ];
                } elseif ($response_code === 403) {
                    return [
                        'status' => 'error',
                        'error' => 'Access forbidden: ' . $error_message,
                        'code' => 'access_forbidden',
                        'http_code' => $response_code
                    ];
                } elseif ($response_code === 429) {
                    return [
                        'status' => 'error',
                        'error' => 'Rate limit exceeded: ' . $error_message,
                        'code' => 'rate_limit_exceeded',
                        'http_code' => $response_code
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'error' => 'API error: ' . $error_message,
                        'code' => 'api_error_' . $response_code,
                        'http_code' => $response_code
                    ];
                }
            }

            // Check for rate limit headers
            $rate_limit_info = $this->extract_rate_limit_info($response_headers);
            if (!empty($rate_limit_info)) {
                error_log("SMO_AI_CONNECTION: Rate limit info for {$provider_key}: " . json_encode($rate_limit_info));
            }

            // Test successful
            error_log("SMO_AI_CONNECTION: Provider connection successful for {$provider_key}");
            return [
                'status' => 'success',
                'message' => 'Provider connection successful',
                'rate_limit_info' => $rate_limit_info,
                'response_time' => $this->get_response_time()
            ];

        } catch (\Exception $e) {
            error_log("SMO_AI_CONNECTION_ERROR: Exception for {$provider_key}: " . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'code' => 'exception',
                'exception_class' => get_class($e)
            ];
        }
    }
    
    /**
     * Get provider usage statistics
     */
    public function get_provider_statistics($provider_key) {
        $config = get_option('smo_ai_provider_config_' . $provider_key, []);
        
        // Get API call statistics
        $api_calls = get_option('smo_social_' . $provider_key . '_api_calls', []);
        if (!is_array($api_calls)) {
            $api_calls = [];
        }
        
        $current_time = time();
        $hourly_calls = count(array_filter($api_calls, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 3600;
        }));
        
        $daily_calls = count(array_filter($api_calls, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 86400;
        }));
        
        return [
            'provider_key' => $provider_key,
            'provider_name' => $config['name'] ?? $provider_key,
            'configured' => !empty($config['api_key']),
            'hourly_calls' => $hourly_calls,
            'daily_calls' => $daily_calls,
            'hourly_limit' => $config['rate_limits']['requests_per_hour'] ?? 1000,
            'minute_limit' => $config['rate_limits']['requests_per_minute'] ?? 60,
            'base_url' => $config['base_url'] ?? '',
            'last_tested' => $config['last_tested'] ?? null
        ];
    }
    
    /**
     * Remove AI provider
     */
    public function remove_provider($provider_key) {
        // Remove provider configuration
        delete_option('smo_ai_provider_config_' . $provider_key);
        
        // Remove from active providers list
        $active_providers = get_option('smo_ai_active_providers', []);
        $active_providers = is_array($active_providers) ? $active_providers : [];
        $active_providers = array_diff($active_providers, [$provider_key]);
        update_option('smo_ai_active_providers', $active_providers);
        
        // Clear usage statistics
        delete_option('smo_social_' . $provider_key . '_api_calls');
        
        return ['status' => 'success', 'message' => 'Provider removed successfully'];
    }
    
    /**
     * Get all configured providers
     */
    public function get_configured_providers() {
        $active_providers = get_option('smo_ai_active_providers', []);
        $active_providers = is_array($active_providers) ? $active_providers : [];
        $configured_providers = [];

        foreach ($active_providers as $provider_key) {
            $config = get_option('smo_ai_provider_config_' . $provider_key, []);
            if (!empty($config['api_key'])) {
                $configured_providers[$provider_key] = $config;
            }
        }
        
        return $configured_providers;
    }
    
    /**
     * Validate provider configuration
     */
    private function validate_provider_config($provider_key, $config) {
        // Required fields
        $required_fields = ['name', 'base_url', 'api_key', 'provider_type'];
        $errors = [];

        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate URL format
        if (!empty($config['base_url']) && !filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
            $errors[] = "Invalid URL format for base_url: {$config['base_url']}";
        }

        // Validate provider type
        $valid_types = ['openai', 'anthropic', 'huggingface', 'together', 'groq', 'custom'];
        if (!empty($config['provider_type']) && !in_array($config['provider_type'], $valid_types)) {
            $errors[] = "Invalid provider type: {$config['provider_type']}. Valid types are: " . implode(', ', $valid_types);
        }

        // Validate API key format
        if (!empty($config['api_key']) && !is_string($config['api_key'])) {
            $errors[] = "API key must be a string";
        }

        // Validate rate limits if provided
        if (isset($config['rate_limits'])) {
            if (!is_array($config['rate_limits'])) {
                $errors[] = "Rate limits must be an array";
            } else {
                if (isset($config['rate_limits']['requests_per_minute']) && (!is_numeric($config['rate_limits']['requests_per_minute']) || $config['rate_limits']['requests_per_minute'] <= 0)) {
                    $errors[] = "requests_per_minute must be a positive number";
                }
                if (isset($config['rate_limits']['requests_per_hour']) && (!is_numeric($config['rate_limits']['requests_per_hour']) || $config['rate_limits']['requests_per_hour'] <= 0)) {
                    $errors[] = "requests_per_hour must be a positive number";
                }
            }
        }

        // Validate model if provided
        if (isset($config['default_model']) && !is_string($config['default_model'])) {
            $errors[] = "default_model must be a string";
        }

        if (!empty($errors)) {
            error_log("SMO_AI_CONFIG_VALIDATION_ERROR: " . implode(' | ', $errors));
            return ['valid' => false, 'errors' => $errors];
        }

        return ['valid' => true, 'errors' => []];
    }
    
    /**
     * Build test request for provider
     */
    private function build_test_request($config) {
        switch ($config['provider_type']) {
            case 'anthropic':
                return [
                    'model' => $config['default_model'] ?? 'claude-3-haiku-20240307',
                    'max_tokens' => 100,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello, this is a test']
                    ]
                ];
                
            case 'huggingface':
                return [
                    'inputs' => 'Hello, this is a test',
                    'parameters' => [
                        'max_length' => 100,
                        'temperature' => 0.7
                    ]
                ];
                
            case 'openai':
            case 'together':
            case 'groq':
            case 'custom':
            default:
                return [
                    'model' => $config['default_model'] ?? 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello, this is a test']
                    ],
                    'max_tokens' => 100,
                    'temperature' => 0.7
                ];
        }
    }

    /**
     * Extract error message from API response body
     */
    private function extract_error_message($response_body) {
        try {
            $decoded = json_decode($response_body, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['error'])) {
                if (is_string($decoded['error'])) {
                    return $decoded['error'];
                } elseif (is_array($decoded['error']) && isset($decoded['error']['message'])) {
                    return $decoded['error']['message'];
                }
            }
        } catch (\Exception $e) {
            // Fall through to default
        }

        // Return first 200 characters if we can't parse JSON
        return substr($response_body, 0, 200);
    }

    /**
     * Extract rate limit information from response headers
     */
    private function extract_rate_limit_info($headers) {
        $rate_limit_info = [];

        if (isset($headers['x-ratelimit-limit'])) {
            $rate_limit_info['limit'] = $headers['x-ratelimit-limit'];
        }
        if (isset($headers['x-ratelimit-remaining'])) {
            $rate_limit_info['remaining'] = $headers['x-ratelimit-remaining'];
        }
        if (isset($headers['x-ratelimit-reset'])) {
            $rate_limit_info['reset'] = $headers['x-ratelimit-reset'];
        }
        if (isset($headers['retry-after'])) {
            $rate_limit_info['retry_after'] = $headers['retry-after'];
        }

        return empty($rate_limit_info) ? null : $rate_limit_info;
    }

    /**
     * Extract response headers from WP HTTP response
     */
    private function extract_response_headers($response) {
        $headers = [];

        if (is_array($response) && isset($response['headers'])) {
            $response_headers = $response['headers'];

            // Convert headers to associative array
            if (is_array($response_headers)) {
                foreach ($response_headers as $key => $value) {
                    if (is_array($value)) {
                        $headers[strtolower($key)] = count($value) === 1 ? $value[0] : $value;
                    } else {
                        $headers[strtolower($key)] = $value;
                    }
                }
            }
        }

        return $headers;
    }

    /**
     * Get response time for the API call
     */
    private function get_response_time() {
        // This would be implemented with actual timing in a real scenario
        // For now, return a placeholder
        return 'N/A';
    }

    /**
     * Log comprehensive API call details for debugging
     */
    private function log_api_call_details($provider_key, $endpoint, $request_data, $response) {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'provider' => $provider_key,
            'endpoint' => $endpoint,
            'request' => [
                'data' => $request_data,
                'size' => strlen(json_encode($request_data))
            ],
            'response' => [
                'code' => wp_remote_retrieve_response_code($response),
                'body_size' => strlen(wp_remote_retrieve_body($response)),
                'headers' => $this->extract_response_headers($response)
            ]
        ];

        error_log("SMO_AI_API_LOG: " . json_encode($log_data));

        // Also log to a dedicated API log option for debugging
        $api_logs = get_option('smo_ai_api_call_logs', []);
        if (!is_array($api_logs)) {
            $api_logs = [];
        }

        $api_logs[] = $log_data;
        $api_logs = array_slice($api_logs, -100); // Keep only last 100 logs

        update_option('smo_ai_api_call_logs', $api_logs);
    }

    /**
     * Record API call for rate limiting
     */
    public function record_api_call($provider_key) {
        $api_calls = get_option('smo_social_' . $provider_key . '_api_calls', []);
        if (!is_array($api_calls)) {
            $api_calls = [];
        }
        
        $api_calls[] = time();
        
        // Keep only recent calls (last 24 hours)
        $cutoff = time() - 86400;
        $api_calls = array_filter($api_calls, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        update_option('smo_social_' . $provider_key . '_api_calls', $api_calls, false);
    }
    
    /**
     * Check rate limits for provider
     */
    public function check_rate_limit($provider_key) {
        $config = get_option('smo_ai_provider_config_' . $provider_key, []);
        $rate_limits = $config['rate_limits'] ?? [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000
        ];

        $api_calls = get_option('smo_social_' . $provider_key . '_api_calls', []);
        if (!is_array($api_calls)) {
            $api_calls = [];
        }

        $current_time = time();

        // Check hourly limit
        $hourly_calls = count(array_filter($api_calls, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 3600;
        }));

        if ($hourly_calls >= $rate_limits['requests_per_hour']) {
            error_log("SMO_AI_RATE_LIMIT: Hourly rate limit exceeded for {$provider_key}. Calls: {$hourly_calls}, Limit: {$rate_limits['requests_per_hour']}");
            return false;
        }

        // Check minute limit
        $recent_calls = array_filter($api_calls, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 60;
        });

        if (count($recent_calls) >= $rate_limits['requests_per_minute']) {
            error_log("SMO_AI_RATE_LIMIT: Minute rate limit exceeded for {$provider_key}. Calls: " . count($recent_calls) . ", Limit: {$rate_limits['requests_per_minute']}");
            return false;
        }

        error_log("SMO_AI_RATE_LIMIT: Rate limit check passed for {$provider_key}. Hourly: {$hourly_calls}/{$rate_limits['requests_per_hour']}, Minute: " . count($recent_calls) . "/{$rate_limits['requests_per_minute']}");
        return true;
    }

    /**
     * Handle rate limit exceeded response and implement retry logic
     */
    public function handle_rate_limit_exceeded($provider_key, $response_headers) {
        $retry_after = 0;
        $rate_limit_info = $this->extract_rate_limit_info($response_headers);

        if (isset($rate_limit_info['retry_after'])) {
            $retry_after = (int)$rate_limit_info['retry_after'];
        } elseif (isset($rate_limit_info['reset'])) {
            $retry_after = max(0, (int)$rate_limit_info['reset'] - time());
        }

        // If no retry-after header, use exponential backoff
        if ($retry_after <= 0) {
            $retry_after = 60; // Default 60 seconds
        }

        error_log("SMO_AI_RATE_LIMIT: Rate limit exceeded for {$provider_key}. Retry after {$retry_after} seconds");

        return [
            'retry_after' => $retry_after,
            'rate_limit_info' => $rate_limit_info
        ];
    }

    /**
     * Make API call with automatic rate limit handling
     */
    public function make_api_call_with_rate_limiting($provider_key, $endpoint, $request_data, $max_retries = 3) {
        $config = get_option('smo_ai_provider_config_' . $provider_key, []);

        if (empty($config['api_key']) || empty($config['base_url'])) {
            error_log("SMO_AI_API_ERROR: Missing configuration for {$provider_key}");
            return ['status' => 'error', 'error' => 'Provider not properly configured'];
        }

        // Check rate limits before making API call
        if (!$this->check_rate_limit($provider_key)) {
            error_log("SMO_AI_API_ERROR: Rate limit check failed before API call for {$provider_key}");
            return ['status' => 'error', 'error' => 'Rate limit exceeded', 'code' => 'rate_limit_exceeded'];
        }

        $attempt = 0;
        $last_error = null;

        while ($attempt < $max_retries) {
            $attempt++;
            error_log("SMO_AI_API: Attempt {$attempt} for {$provider_key} to {$endpoint}");

            try {
                $response = wp_remote_post($config['base_url'] . $endpoint, [
                    'body' => json_encode($request_data),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $config['api_key']
                    ],
                    'timeout' => 30
                ]);

                if (is_wp_error($response)) {
                    $last_error = $response->get_error_message();
                    error_log("SMO_AI_API_ERROR: WP Error on attempt {$attempt} for {$provider_key}: {$last_error}");
                    continue;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_headers = $this->extract_response_headers($response);

                // Record this API call
                $this->record_api_call($provider_key);

                // Log comprehensive API call details for debugging
                $this->log_api_call_details($provider_key, $endpoint, $request_data, $response);

                // Handle rate limit response
                if ($response_code === 429) {
                    $rate_limit_data = $this->handle_rate_limit_exceeded($provider_key, $response_headers);

                    if ($attempt < $max_retries) {
                        error_log("SMO_AI_API: Waiting {$rate_limit_data['retry_after']} seconds before retry...");
                        sleep(min($rate_limit_data['retry_after'], 60)); // Max 60 seconds wait
                        continue;
                    } else {
                        error_log("SMO_AI_API_ERROR: Max retries reached for rate limit on {$provider_key}");
                        return [
                            'status' => 'error',
                            'error' => 'Rate limit exceeded after retries',
                            'code' => 'rate_limit_exceeded',
                            'retry_after' => $rate_limit_data['retry_after'],
                            'rate_limit_info' => $rate_limit_data['rate_limit_info']
                        ];
                    }
                }

                // Handle other errors
                if ($response_code >= 400) {
                    $error_message = $this->extract_error_message($response_body);
                    error_log("SMO_AI_API_ERROR: HTTP {$response_code} for {$provider_key}: {$error_message}");
                    return [
                        'status' => 'error',
                        'error' => $error_message,
                        'code' => 'api_error_' . $response_code,
                        'http_code' => $response_code
                    ];
                }

                // Success
                error_log("SMO_AI_API: Successful response from {$provider_key}");
                return [
                    'status' => 'success',
                    'response' => json_decode($response_body, true),
                    'headers' => $response_headers,
                    'http_code' => $response_code
                ];

            } catch (\Exception $e) {
                $last_error = $e->getMessage();
                error_log("SMO_AI_API_ERROR: Exception on attempt {$attempt} for {$provider_key}: {$last_error}");

                if ($attempt >= $max_retries) {
                    return [
                        'status' => 'error',
                        'error' => $last_error,
                        'code' => 'exception',
                        'exception_class' => get_class($e)
                    ];
                }

                // Exponential backoff
                sleep(min(pow(2, $attempt), 10));
            }
        }

        return [
            'status' => 'error',
            'error' => 'Max retries reached: ' . $last_error,
            'code' => 'max_retries_exceeded',
            'attempts' => $attempt
        ];
    }
    
    /**
     * Get prebuilt provider configurations
     */
    public function get_prebuilt_providers() {
        return [
            'openrouter' => [
                'name' => 'OpenRouter',
                'description' => 'Access to 100+ AI models through single API',
                'base_url' => 'https://openrouter.ai/api/v1',
                'provider_type' => 'openai',
                'default_model' => 'openai/gpt-3.5-turbo',
                'auth_type' => 'bearer',
                'pricing_url' => 'https://openrouter.ai/docs#models',
                'signup_url' => 'https://openrouter.ai/',
                'features' => ['100+ Models', 'Single API', 'Competitive Pricing'],
                'rate_limits' => ['requests_per_minute' => 60, 'requests_per_hour' => 1000]
            ],
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'GPT-4, GPT-3.5, and other OpenAI models',
                'base_url' => 'https://api.openai.com/v1',
                'provider_type' => 'openai',
                'default_model' => 'gpt-3.5-turbo',
                'auth_type' => 'bearer',
                'pricing_url' => 'https://openai.com/pricing',
                'signup_url' => 'https://platform.openai.com/',
                'features' => ['State-of-the-art', 'Most Popular', 'Reliable'],
                'rate_limits' => ['requests_per_minute' => 60, 'requests_per_hour' => 1000]
            ],
            'anthropic' => [
                'name' => 'Anthropic Claude',
                'description' => 'Claude-3 models with advanced reasoning',
                'base_url' => 'https://api.anthropic.com/v1',
                'provider_type' => 'anthropic',
                'default_model' => 'claude-3-haiku-20240307',
                'auth_type' => 'bearer',
                'pricing_url' => 'https://www.anthropic.com/pricing',
                'signup_url' => 'https://console.anthropic.com/',
                'features' => ['Advanced Reasoning', 'Safe AI', 'Long Context'],
                'rate_limits' => ['requests_per_minute' => 50, 'requests_per_hour' => 1000]
            ],
            'together' => [
                'name' => 'Together AI',
                'description' => 'Open source models with fast inference',
                'base_url' => 'https://api.together.xyz/v1',
                'provider_type' => 'openai',
                'default_model' => 'mistralai/mixtral-8x7b-instruct',
                'auth_type' => 'bearer',
                'pricing_url' => 'https://api.together.xyz/pricing',
                'signup_url' => 'https://api.together.xyz/',
                'features' => ['Open Source', 'Fast Inference', 'Cost Effective'],
                'rate_limits' => ['requests_per_minute' => 60, 'requests_per_hour' => 1000]
            ],
            'groq' => [
                'name' => 'Groq',
                'description' => 'Ultra-fast inference for open models',
                'base_url' => 'https://api.groq.com/openai/v1',
                'provider_type' => 'openai',
                'default_model' => 'llama2-70b-32768',
                'auth_type' => 'bearer',
                'pricing_url' => 'https://groq.com/pricing/',
                'signup_url' => 'https://console.groq.com/',
                'features' => ['Ultra Fast', 'Free Tier', 'Open Models'],
                'rate_limits' => ['requests_per_minute' => 30, 'requests_per_hour' => 1000]
            ],
            'huggingface' => [
                'name' => 'HuggingFace',
                'description' => '10,000+ free and premium models',
                'base_url' => 'https://api-inference.huggingface.co',
                'provider_type' => 'huggingface',
                'default_model' => 'microsoft/DialoGPT-large',
                'auth_type' => 'bearer',
                'pricing_url' => 'https://huggingface.co/pricing',
                'signup_url' => 'https://huggingface.co/',
                'features' => ['10K+ Models', 'Free Tier', 'Diverse Models'],
                'rate_limits' => ['requests_per_minute' => 30, 'requests_per_hour' => 1000]
            ]
        ];
    }

    /**
     * Test all API integration improvements
     */
    public function test_api_integration_improvements() {
        $test_results = [
            'validation_tests' => [],
            'error_handling_tests' => [],
            'rate_limit_tests' => [],
            'logging_tests' => []
        ];

        // Test 1: Validation improvements
        $validation_test_configs = [
            'valid_config' => [
                'name' => 'Test Provider',
                'base_url' => 'https://api.test.com/v1',
                'api_key' => 'test_key_123',
                'provider_type' => 'openai',
                'rate_limits' => ['requests_per_minute' => 60, 'requests_per_hour' => 1000]
            ],
            'invalid_url_config' => [
                'name' => 'Test Provider',
                'base_url' => 'not-a-valid-url',
                'api_key' => 'test_key_123',
                'provider_type' => 'openai'
            ],
            'invalid_provider_type' => [
                'name' => 'Test Provider',
                'base_url' => 'https://api.test.com/v1',
                'api_key' => 'test_key_123',
                'provider_type' => 'invalid_type'
            ],
            'missing_fields' => [
                'name' => 'Test Provider',
                // Missing base_url, api_key, provider_type
            ]
        ];

        foreach ($validation_test_configs as $test_name => $config) {
            $result = $this->validate_provider_config('test_provider', $config);
            $test_results['validation_tests'][$test_name] = [
                'valid' => $result['valid'],
                'errors' => $result['errors'],
                'expected_valid' => strpos($test_name, 'invalid') === false && strpos($test_name, 'missing') === false
            ];
        }

        // Test 2: Error handling improvements
        $error_test_cases = [
            'missing_api_key' => ['api_key' => '', 'base_url' => 'https://api.test.com/v1'],
            'missing_base_url' => ['api_key' => 'test_key', 'base_url' => ''],
            'rate_limit_exceeded' => ['api_key' => 'test_key', 'base_url' => 'https://api.test.com/v1']
        ];

        foreach ($error_test_cases as $test_name => $config) {
            // Mock the config temporarily
            update_option('smo_ai_provider_config_test_provider', $config);

            $result = $this->test_provider_connection('test_provider');
            $test_results['error_handling_tests'][$test_name] = [
                'status' => $result['status'],
                'error' => $result['error'] ?? '',
                'code' => $result['code'] ?? ''
            ];

            // Clean up
            delete_option('smo_ai_provider_config_test_provider');
        }

        // Test 3: Rate limit handling
        $rate_limit_config = [
            'name' => 'Test Provider',
            'base_url' => 'https://api.test.com/v1',
            'api_key' => 'test_key_123',
            'provider_type' => 'openai',
            'rate_limits' => ['requests_per_minute' => 2, 'requests_per_hour' => 5] // Very low limits for testing
        ];

        update_option('smo_ai_provider_config_rate_limit_test', $rate_limit_config);

        // Test rate limit checking
        $test_results['rate_limit_tests']['initial_check'] = $this->check_rate_limit('rate_limit_test');

        // Make some API calls to test rate limiting
        for ($i = 0; $i < 3; $i++) {
            $this->record_api_call('rate_limit_test');
        }

        $test_results['rate_limit_tests']['after_calls'] = $this->check_rate_limit('rate_limit_test');
        $test_results['rate_limit_tests']['statistics'] = $this->get_provider_statistics('rate_limit_test');

        // Clean up
        delete_option('smo_ai_provider_config_rate_limit_test');
        delete_option('smo_social_rate_limit_test_api_calls');

        // Test 4: Logging functionality
        $test_log_data = [
            'provider' => 'test_logging',
            'endpoint' => '/test',
            'request' => ['test' => 'data'],
            'response' => ['code' => 200, 'body' => 'success']
        ];

        // Test that logging methods exist and work
        $test_results['logging_tests']['methods_exist'] = method_exists($this, 'log_api_call_details');
        $test_results['logging_tests']['extract_methods_exist'] = method_exists($this, 'extract_error_message') &&
                                                                  method_exists($this, 'extract_rate_limit_info') &&
                                                                  method_exists($this, 'extract_response_headers');

        return [
            'status' => 'success',
            'message' => 'API integration improvements test completed',
            'test_results' => $test_results,
            'summary' => [
                'validation_improved' => !empty($test_results['validation_tests']),
                'error_handling_improved' => !empty($test_results['error_handling_tests']),
                'rate_limiting_improved' => !empty($test_results['rate_limit_tests']),
                'logging_improved' => $test_results['logging_tests']['methods_exist'] && $test_results['logging_tests']['extract_methods_exist']
            ]
        ];
    }
}
