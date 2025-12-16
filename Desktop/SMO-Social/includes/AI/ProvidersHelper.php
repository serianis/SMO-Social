<?php
namespace SMO_Social\AI;

/**
 * AI Providers Helper
 * Utility functions for working with AI providers
 */
class ProvidersHelper {
    
    /**
     * Get providers grouped by type
     * 
     * @return array Providers grouped by type
     */
    public static function get_providers_by_category() {
        $all_providers = ProvidersConfig::get_all_providers();
        
        $categorized = [
            'cloud' => [
                'name' => '🌐 Cloud AI Providers',
                'description' => 'Professional cloud-based AI services',
                'providers' => []
            ],
            'router' => [
                'name' => '🔀 Router / Aggregator',
                'description' => 'Access multiple models through one API',
                'providers' => []
            ],
            'local' => [
                'name' => '🖥️ Local / Self-Hosted',
                'description' => 'Run AI models locally for privacy',
                'providers' => []
            ],
            'marketplace' => [
                'name' => '🛒 Marketplace',
                'description' => 'Marketplace LLM providers',
                'providers' => []
            ],
            'cluster' => [
                'name' => '🔗 Cluster',
                'description' => 'Open-source LLM clusters',
                'providers' => []
            ],
            'custom' => [
                'name' => '🔧 Custom',
                'description' => 'Custom API endpoints',
                'providers' => []
            ]
        ];
        
        foreach ($all_providers as $id => $provider) {
            $type = $provider['type'] ?? 'custom';
            if (isset($categorized[$type])) {
                $categorized[$type]['providers'][$id] = $provider;
            }
        }
        
        // Remove empty categories
        foreach ($categorized as $type => $category) {
            if (empty($category['providers'])) {
                unset($categorized[$type]);
            }
        }
        
        return $categorized;
    }
    
    /**
     * Get provider display name with icon
     * 
     * @param string $provider_id Provider ID
     * @return string Display name
     */
    public static function get_provider_display_name($provider_id) {
        $provider = ProvidersConfig::get_provider($provider_id);
        if (!$provider) {
            return ucfirst($provider_id);
        }
        
        $emoji = self::get_provider_emoji($provider['type']);
        return $emoji . ' ' . $provider['name'];
    }
    
    /**
     * Get emoji for provider type
     * 
     * @param string $type Provider type
     * @return string Emoji
     */
    private static function get_provider_emoji($type) {
        $emojis = [
            'cloud' => '☁️',
            'router' => '🔀',
            'local' => '🖥️',
            'marketplace' => '🛒',
            'cluster' => '🔗',
            'custom' => '🔧'
        ];
        
        return $emojis[$type] ?? '🤖';
    }
    
    /**
     * Get provider status (configured or not)
     * 
     * @param string $provider_id Provider ID
     * @return array Status information
     */
    public static function get_provider_status($provider_id) {
        $provider = ProvidersConfig::get_provider($provider_id);
        if (!$provider) {
            return [
                'configured' => false,
                'status' => 'unknown',
                'message' => 'Provider not found'
            ];
        }
        
        $is_configured = ProvidersConfig::is_provider_configured($provider_id);
        
        if ($is_configured) {
            return [
                'configured' => true,
                'status' => 'ready',
                'message' => 'Ready to use',
                'icon' => '✅'
            ];
        }
        
        // Check what's missing
        $missing = [];
        if ($provider['requires_key'] && isset($provider['key_option'])) {
            $key = get_option($provider['key_option']);
            if (empty($key)) {
                $missing[] = 'API Key';
            }
        }
        
        if (isset($provider['url_option'])) {
            $url = get_option($provider['url_option']);
            if (empty($url)) {
                $missing[] = 'Base URL';
            }
        }
        
        return [
            'configured' => false,
            'status' => 'not_configured',
            'message' => 'Missing: ' . implode(', ', $missing),
            'missing' => $missing,
            'icon' => '⚠️'
        ];
    }
    
    /**
     * Get recommended providers based on use case
     * 
     * @param string $use_case Use case (beginner, professional, privacy, budget, developer)
     * @return array Recommended provider IDs
     */
    public static function get_recommended_providers($use_case = 'beginner') {
        $recommendations = [
            'beginner' => [
                'primary' => ['openai', 'google'],
                'alternative' => ['anthropic', 'openrouter']
            ],
            'professional' => [
                'primary' => ['openai', 'anthropic'],
                'alternative' => ['google', 'vertex-ai']
            ],
            'privacy' => [
                'primary' => ['ollama', 'lm-studio'],
                'alternative' => ['localhost']
            ],
            'budget' => [
                'primary' => ['openrouter', 'together', 'fireworks'],
                'alternative' => ['cerebras', 'ollama']
            ],
            'developer' => [
                'primary' => ['deepseek', 'openai'],
                'alternative' => ['anthropic', 'together']
            ],
            'fast' => [
                'primary' => ['cerebras', 'fireworks'],
                'alternative' => ['together', 'google']
            ],
            'multilingual' => [
                'primary' => ['qwen', 'google'],
                'alternative' => ['openai', 'anthropic']
            ]
        ];
        
        return $recommendations[$use_case] ?? $recommendations['beginner'];
    }
    
    /**
     * Get provider comparison data
     * 
     * @param array $provider_ids Provider IDs to compare
     * @return array Comparison data
     */
    public static function compare_providers($provider_ids) {
        $comparison = [];
        
        foreach ($provider_ids as $provider_id) {
            $provider = ProvidersConfig::get_provider($provider_id);
            if (!$provider) {
                continue;
            }
            
            $comparison[$provider_id] = [
                'name' => $provider['name'],
                'type' => $provider['type'],
                'models_count' => count($provider['models']),
                'capabilities' => $provider['capabilities'],
                'requires_key' => $provider['requires_key'],
                'speed_rating' => self::get_speed_rating($provider_id),
                'cost_rating' => self::get_cost_rating($provider_id),
                'quality_rating' => self::get_quality_rating($provider_id),
                'privacy_rating' => self::get_privacy_rating($provider['type'])
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get speed rating (1-5)
     */
    private static function get_speed_rating($provider_id) {
        $ratings = [
            'cerebras' => 5,
            'fireworks' => 5,
            'together' => 4,
            'google' => 4,
            'openai' => 3,
            'anthropic' => 3,
            'ollama' => 2,
            'lm-studio' => 2
        ];
        
        return $ratings[$provider_id] ?? 3;
    }
    
    /**
     * Get cost rating (1-5, 1=expensive, 5=cheap)
     */
    private static function get_cost_rating($provider_id) {
        $ratings = [
            'ollama' => 5,
            'lm-studio' => 5,
            'fireworks' => 4,
            'together' => 4,
            'cerebras' => 4,
            'google' => 3,
            'openai' => 2,
            'anthropic' => 2
        ];
        
        return $ratings[$provider_id] ?? 3;
    }
    
    /**
     * Get quality rating (1-5)
     */
    private static function get_quality_rating($provider_id) {
        $ratings = [
            'openai' => 5,
            'anthropic' => 5,
            'google' => 4,
            'deepseek' => 4,
            'together' => 3,
            'ollama' => 3
        ];
        
        return $ratings[$provider_id] ?? 3;
    }
    
    /**
     * Get privacy rating (1-5)
     */
    private static function get_privacy_rating($type) {
        $ratings = [
            'local' => 5,
            'custom' => 4,
            'cloud' => 2,
            'router' => 2,
            'marketplace' => 2
        ];
        
        return $ratings[$type] ?? 3;
    }
    
    /**
     * Get setup instructions for a provider
     * 
     * @param string $provider_id Provider ID
     * @return array Setup instructions
     */
    public static function get_setup_instructions($provider_id) {
        $provider = ProvidersConfig::get_provider($provider_id);
        if (!$provider) {
            return [];
        }
        
        $instructions = [
            'provider_name' => $provider['name'],
            'steps' => []
        ];
        
        // Add steps based on provider type
        if ($provider['requires_key']) {
            $instructions['steps'][] = [
                'title' => 'Get API Key',
                'description' => 'Visit ' . $provider['docs_url'] . ' to create an account and get your API key',
                'url' => $provider['docs_url']
            ];
            
            $instructions['steps'][] = [
                'title' => 'Enter API Key',
                'description' => 'Paste your API key in the field below',
                'field' => $provider['key_option']
            ];
        }
        
        if (isset($provider['url_option'])) {
            $instructions['steps'][] = [
                'title' => 'Configure URL',
                'description' => 'Enter the base URL for your ' . $provider['name'] . ' instance',
                'field' => $provider['url_option'],
                'default' => $provider['base_url']
            ];
        }
        
        if ($provider['type'] === 'local') {
            $instructions['steps'][] = [
                'title' => 'Start Local Server',
                'description' => 'Make sure ' . $provider['name'] . ' is running on your machine',
                'command' => self::get_start_command($provider_id)
            ];
        }
        
        $instructions['steps'][] = [
            'title' => 'Test Connection',
            'description' => 'Click the "Test Connection" button to verify everything is working',
            'action' => 'test_connection'
        ];
        
        return $instructions;
    }
    
    /**
     * Get start command for local providers
     */
    private static function get_start_command($provider_id) {
        $commands = [
            'ollama' => 'ollama serve',
            'lm-studio' => 'Start LM Studio and enable Local Server'
        ];
        
        return $commands[$provider_id] ?? '';
    }
    
    /**
     * Get available models for a provider
     * 
     * @param string $provider_id Provider ID
     * @return array Models with metadata
     */
    public static function get_provider_models($provider_id) {
        $provider = ProvidersConfig::get_provider($provider_id);
        if (!$provider) {
            return [];
        }
        
        $models = [];
        foreach ($provider['models'] as $model) {
            $models[] = [
                'id' => $model,
                'name' => self::format_model_name($model),
                'provider' => $provider['name']
            ];
        }
        
        return $models;
    }
    
    /**
     * Format model name for display
     */
    private static function format_model_name($model) {
        // Convert model ID to readable name
        $name = str_replace(['-', '_'], ' ', $model);
        return ucwords($name);
    }
    
    /**
     * Get provider statistics
     * 
     * @return array Statistics
     */
    public static function get_statistics() {
        $all_providers = ProvidersConfig::get_all_providers();
        $configured = ProvidersConfig::get_configured_providers();
        
        $stats = [
            'total_providers' => count($all_providers),
            'configured_providers' => count($configured),
            'by_type' => [],
            'total_models' => 0
        ];
        
        foreach ($all_providers as $provider) {
            $type = $provider['type'];
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
            $stats['total_models'] += count($provider['models']);
        }
        
        return $stats;
    }
    
    /**
     * Search providers by capability
     * 
     * @param string $capability Capability to search for
     * @return array Provider IDs with this capability
     */
    public static function search_by_capability($capability) {
        $all_providers = ProvidersConfig::get_all_providers();
        $results = [];
        
        foreach ($all_providers as $id => $provider) {
            if (in_array($capability, $provider['capabilities'])) {
                $results[] = $id;
            }
        }
        
        return $results;
    }
    
    /**
     * Get provider health check
     * 
     * @param string $provider_id Provider ID
     * @return array Health status
     */
    public static function health_check($provider_id) {
        $provider = ProvidersConfig::get_provider($provider_id);
        if (!$provider) {
            return [
                'healthy' => false,
                'message' => 'Provider not found'
            ];
        }
        
        // Check if configured
        if (!ProvidersConfig::is_provider_configured($provider_id)) {
            return [
                'healthy' => false,
                'message' => 'Provider not configured'
            ];
        }
        
        // For local providers, check if server is running
        if ($provider['type'] === 'local') {
            $url = get_option($provider['url_option'] ?? '', $provider['base_url']);
            $response = wp_remote_get($url, ['timeout' => 5]);
            
            if (is_wp_error($response)) {
                return [
                    'healthy' => false,
                    'message' => 'Server not responding: ' . $response->get_error_message()
                ];
            }
        }
        
        return [
            'healthy' => true,
            'message' => 'Provider is healthy and ready'
        ];
    }
}
