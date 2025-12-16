<?php
namespace SMO_Social\AI\Models;

use SMO_Social\AI\ProvidersConfig;
use SMO_Social\AI\DatabaseProviderLoader;

/**
 * Universal AI Provider Manager
 * Handles communication with any AI provider using a unified interface
 */
class UniversalManager {
    
    private $provider_id;
    private $provider_config;
    private $api_key;
    private $base_url;
    private $cache_manager;
    
    /**
     * Constructor
     * 
     * @param string $provider_id Provider ID from ProvidersConfig
     */
    public function __construct($provider_id) {
        $this->provider_id = $provider_id;

        // Try database first, then fall back to static config
        $this->provider_config = DatabaseProviderLoader::get_provider_from_database($provider_id);

        // Debug logging to understand configuration flow
        error_log("UniversalManager: Attempting to load provider: {$provider_id}");
        error_log("UniversalManager: Database config found: " . ($this->provider_config ? 'YES' : 'NO'));

        // If not found in database, try static config
        if (!$this->provider_config) {
            $this->provider_config = ProvidersConfig::get_provider($provider_id);
            error_log("UniversalManager: Static config found: " . ($this->provider_config ? 'YES' : 'NO'));

            if (!$this->provider_config) {
                throw new \Exception("Unknown provider: {$provider_id}");
            }
        }

        // Log the configuration being used
        error_log("UniversalManager: Using config: " . print_r($this->provider_config, true));

        // Initialize cache manager
        $this->cache_manager = new \SMO_Social\AI\CacheManager();

        // Load credentials
        $this->load_credentials();
    }
    
    /**
     * Load provider credentials from WordPress options
     */
    private function load_credentials() {
        // Debug logging for credentials loading
        error_log("UniversalManager: Loading credentials for provider: {$this->provider_id}");
        error_log("UniversalManager: Provider config requires_key: " . ($this->provider_config['requires_key'] ?? 'not set'));
        error_log("UniversalManager: Provider config key_option: " . ($this->provider_config['key_option'] ?? 'not set'));

        // Load API key if required
        if ($this->provider_config['requires_key'] && isset($this->provider_config['key_option'])) {
            $this->api_key = get_option($this->provider_config['key_option']);
            error_log("UniversalManager: Loaded API key from option: " . $this->provider_config['key_option']);
            error_log("UniversalManager: API key present: " . (!empty($this->api_key) ? 'YES' : 'NO'));
        }

        // Load base URL (use custom URL if available, otherwise use default)
        if (isset($this->provider_config['url_option'])) {
            $custom_url = get_option($this->provider_config['url_option']);
            $this->base_url = !empty($custom_url) ? $custom_url : $this->provider_config['base_url'];
            error_log("UniversalManager: Using custom URL option: " . $this->provider_config['url_option']);
        } else {
            $this->base_url = $this->provider_config['base_url'];
            error_log("UniversalManager: Using default base URL: " . $this->base_url);
        }

        error_log("UniversalManager: Final base URL: " . $this->base_url);
    }
    
    /**
     * Update provider settings
     * 
     * @param array $settings New settings
     */
    public function update_settings($settings) {
        if (isset($settings['api_key'])) {
            $this->api_key = $settings['api_key'];
        }
        
        if (isset($settings['base_url'])) {
            $this->base_url = $settings['base_url'];
        }
    }
    
    /**
     * Send a chat completion request
     * 
     * @param array $messages Array of messages
     * @param array $options Additional options
     * @return array Response from the AI provider
     */
    public function chat($messages, $options = []) {
        // Validate configuration
        if (!$this->is_configured()) {
            throw new \Exception("Provider {$this->provider_id} is not properly configured");
        }
        
        // Check cache first
        $cache_key = $this->generate_cache_key($messages, $options);
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Prepare request based on provider type
        $response = $this->send_request($messages, $options);
        
        // Cache the response
        $this->cache_manager->set($cache_key, $response, 3600); // 1 hour cache
        
        return $response;
    }
    
    /**
     * Send request to the AI provider
     * 
     * @param array $messages Messages array
     * @param array $options Request options
     * @return array Response
     */
    private function send_request($messages, $options = []) {
        $endpoint = $this->get_chat_endpoint();
        $headers = $this->get_request_headers();
        $body = $this->format_request_body($messages, $options);
        
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60,
            'sslverify' => false // Disabled for local testing compatibility
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception("API request failed: " . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            throw new \Exception("API returned error {$status_code}: {$body}");
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse API response");
        }
        
        return $this->normalize_response($data);
    }
    
    /**
     * Get chat endpoint URL
     * 
     * @return string Endpoint URL
     */
    private function get_chat_endpoint() {
        $base = rtrim($this->base_url, '/');
        
        // Provider-specific endpoint paths
        switch ($this->provider_id) {
            case 'openai':
            case 'openrouter':
            case 'together':
            case 'fireworks':
                return $base . '/chat/completions';
                
            case 'anthropic':
                return $base . '/v1/messages';
                
            case 'google':
            case 'gemini':
                return $base . '/v1beta/models/gemini-pro:generateContent';
                
            case 'ollama':
                return $base . '/api/chat';
                
            case 'lm-studio':
                return $base . '/v1/chat/completions';
                
            default:
                // Default to OpenAI-compatible endpoint
                return $base . '/chat/completions';
        }
    }
    
    /**
     * Get request headers
     * 
     * @return array Headers array
     */
    private function get_request_headers() {
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        // Add authentication based on provider type
        switch ($this->provider_config['auth_type']) {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $this->api_key;
                break;
                
            case 'api_key':
                if ($this->provider_id === 'anthropic') {
                    $headers['x-api-key'] = $this->api_key;
                    $headers['anthropic-version'] = '2023-06-01';
                } else {
                    $headers['Authorization'] = 'Bearer ' . $this->api_key;
                }
                break;
                
            case 'none':
                // No authentication needed
                break;
        }
        
        // OpenRouter specific headers
        if ($this->provider_id === 'openrouter') {
            $headers['HTTP-Referer'] = get_site_url();
            $headers['X-Title'] = get_bloginfo('name');
        }
        
        return $headers;
    }
    
    /**
     * Format request body based on provider
     * 
     * @param array $messages Messages array
     * @param array $options Options
     * @return array Request body
     */
    private function format_request_body($messages, $options) {
        $model = $options['model'] ?? $this->get_default_model();
        
        // Base request body (OpenAI-compatible format)
        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1024
        ];
        
        // Provider-specific adjustments
        switch ($this->provider_id) {
            case 'anthropic':
                // Anthropic uses different format
                $body = [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $options['max_tokens'] ?? 1024,
                    'temperature' => $options['temperature'] ?? 0.7
                ];
                break;
                
            case 'google':
            case 'gemini':
                // Google Gemini format
                $body = [
                    'contents' => array_map(function($msg) {
                        return [
                            'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                            'parts' => [['text' => $msg['content']]]
                        ];
                    }, $messages),
                    'generationConfig' => [
                        'temperature' => $options['temperature'] ?? 0.7,
                        'maxOutputTokens' => $options['max_tokens'] ?? 1024
                    ]
                ];
                break;
        }
        
        return $body;
    }
    
    /**
     * Normalize response from different providers to a common format
     * 
     * @param array $response Raw response
     * @return array Normalized response
     */
    private function normalize_response($response) {
        $normalized = [
            'content' => '',
            'finish_reason' => 'stop',
            'usage' => [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0
            ],
            'raw_response' => $response
        ];
        
        // Extract content based on provider
        switch ($this->provider_id) {
            case 'anthropic':
                if (isset($response['content'][0]['text'])) {
                    $normalized['content'] = $response['content'][0]['text'];
                }
                if (isset($response['stop_reason'])) {
                    $normalized['finish_reason'] = $response['stop_reason'];
                }
                if (isset($response['usage'])) {
                    $normalized['usage'] = [
                        'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
                        'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
                        'total_tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0)
                    ];
                }
                break;
                
            case 'google':
            case 'gemini':
                if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                    $normalized['content'] = $response['candidates'][0]['content']['parts'][0]['text'];
                }
                if (isset($response['candidates'][0]['finishReason'])) {
                    $normalized['finish_reason'] = strtolower($response['candidates'][0]['finishReason']);
                }
                break;
                
            default:
                // OpenAI-compatible format
                if (isset($response['choices'][0]['message']['content'])) {
                    $normalized['content'] = $response['choices'][0]['message']['content'];
                }
                if (isset($response['choices'][0]['finish_reason'])) {
                    $normalized['finish_reason'] = $response['choices'][0]['finish_reason'];
                }
                if (isset($response['usage'])) {
                    $normalized['usage'] = $response['usage'];
                }
                break;
        }
        
        return $normalized;
    }
    
    /**
     * Get default model for the provider
     * 
     * @return string Default model name
     */
    private function get_default_model() {
        if (!empty($this->provider_config['models'])) {
            return $this->provider_config['models'][0];
        }
        return 'default';
    }
    
    /**
     * Check if provider is properly configured
     * 
     * @return bool True if configured
     */
    public function is_configured() {
        if ($this->provider_config['requires_key'] && empty($this->api_key)) {
            return false;
        }
        
        if (empty($this->base_url)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate cache key for request
     * 
     * @param array $messages Messages
     * @param array $options Options
     * @return string Cache key
     */
    private function generate_cache_key($messages, $options) {
        $data = [
            'provider' => $this->provider_id,
            'messages' => $messages,
            'options' => $options
        ];
        return 'ai_chat_' . md5(json_encode($data));
    }
    
    /**
     * Get available models for this provider
     * 
     * @return array Array of model names
     */
    public function get_available_models() {
        return $this->provider_config['models'] ?? [];
    }
    
    /**
     * Get provider capabilities
     * 
     * @return array Array of capabilities
     */
    public function get_capabilities() {
        return $this->provider_config['capabilities'] ?? [];
    }

    /**
     * Check if provider exists in database (for debugging)
     */
    private function check_database_provider($provider_id) {
        global $wpdb;

        $providers_table = $wpdb->prefix . 'smo_ai_providers';
        $provider = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $providers_table WHERE name = %s LIMIT 1",
            $provider_id
        ), ARRAY_A);

        if ($provider) {
            error_log("UniversalManager: Provider found in DATABASE: " . print_r($provider, true));
            error_log("UniversalManager: Database config fields: " . implode(', ', array_keys($provider)));
        } else {
            error_log("UniversalManager: Provider NOT found in database table");
        }
    }
}
