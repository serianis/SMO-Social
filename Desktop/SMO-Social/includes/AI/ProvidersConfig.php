<?php
namespace SMO_Social\AI;

use SMO_Social\AI\DatabaseProviderLoader;

/**
 * AI Providers Configuration
 * Central registry for all supported AI providers
 */
class ProvidersConfig {
    
    /**
     * Get all available AI providers configuration
     * 
     * @return array Array of provider configurations
     */
    public static function get_all_providers() {
        return [
            // ========================================
            // 🌐 MAJOR CLOUD AI PROVIDERS
            // ========================================
            
            'openai' => [
                'id' => 'openai',
                'name' => 'OpenAI',
                'type' => 'cloud',
                'auth_type' => 'bearer',
                'base_url' => 'https://api.openai.com/v1',
                'models' => ['gpt-4.1', 'gpt-4o', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo', 'o3-mini', 'o3'],
                'capabilities' => ['chat', 'completion', 'embedding', 'vision', 'function-calling'],
                'requires_key' => true,
                'key_option' => 'smo_social_openai_api_key',
                'docs_url' => 'https://platform.openai.com/docs',
                'icon' => 'openai.svg',
                'description' => 'GPT-4o, GPT-4.1, o3 and more'
            ],
            
            'anthropic' => [
                'id' => 'anthropic',
                'name' => 'Anthropic',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.anthropic.com',
                'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku', 'claude-3.5-sonnet', 'claude-3.5-haiku'],
                'capabilities' => ['chat', 'analysis', 'long-context', 'vision'],
                'requires_key' => true,
                'key_option' => 'smo_social_anthropic_api_key',
                'docs_url' => 'https://docs.anthropic.com',
                'icon' => 'anthropic.svg',
                'description' => 'Claude 3.x family'
            ],
            
            'google' => [
                'id' => 'google',
                'name' => 'Google Gemini',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://generativelanguage.googleapis.com',
                'models' => ['gemini-2.0-flash-exp', 'gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro'],
                'capabilities' => ['chat', 'multimodal', 'vision', 'long-context'],
                'requires_key' => true,
                'key_option' => 'smo_social_gemini_api_key',
                'docs_url' => 'https://ai.google.dev/docs',
                'icon' => 'google.svg',
                'description' => 'Gemini 1.x / 2.x models'
            ],
            
            'vertex-ai' => [
                'id' => 'vertex-ai',
                'name' => 'Google Vertex AI',
                'type' => 'cloud',
                'auth_type' => 'service_account',
                'base_url' => 'https://{region}-aiplatform.googleapis.com',
                'models' => ['gemini-pro', 'gemini-ultra', 'claude-3-sonnet@vertex'],
                'capabilities' => ['chat', 'enterprise', 'multimodal'],
                'requires_key' => true,
                'key_option' => 'smo_social_vertex_credentials',
                'docs_url' => 'https://cloud.google.com/vertex-ai/docs',
                'icon' => 'vertex.svg',
                'description' => 'Enterprise-grade AI platform'
            ],
            
            'bedrock' => [
                'id' => 'bedrock',
                'name' => 'AWS Bedrock',
                'type' => 'cloud',
                'auth_type' => 'aws_credentials',
                'base_url' => 'https://bedrock-runtime.{region}.amazonaws.com',
                'models' => ['claude-3-opus', 'claude-3-sonnet', 'llama-3-70b', 'mistral-large', 'titan'],
                'capabilities' => ['chat', 'enterprise', 'multi-model'],
                'requires_key' => true,
                'key_option' => 'smo_social_bedrock_credentials',
                'docs_url' => 'https://docs.aws.amazon.com/bedrock',
                'icon' => 'aws.svg',
                'description' => 'Claude, Llama, Mistral via AWS'
            ],
            
            // ========================================
            // 🚀 EMERGING & SPECIALIZED PROVIDERS
            // ========================================
            
            'xai' => [
                'id' => 'xai',
                'name' => 'XAI (Grok)',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.x.ai',
                'models' => ['grok-1', 'grok-1.5', 'grok-2', 'grok-beta'],
                'capabilities' => ['chat', 'real-time'],
                'requires_key' => true,
                'key_option' => 'smo_social_xai_api_key',
                'docs_url' => 'https://docs.x.ai',
                'icon' => 'xai.svg',
                'description' => 'Grok-1, Grok-1.5, Grok-2'
            ],
            
            'qwen' => [
                'id' => 'qwen',
                'name' => 'Alibaba Qwen',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://dashscope.aliyuncs.com',
                'models' => ['qwen-turbo', 'qwen-plus', 'qwen-max', 'qwen-2.5'],
                'capabilities' => ['chat', 'multilingual'],
                'requires_key' => true,
                'key_option' => 'smo_social_qwen_api_key',
                'docs_url' => 'https://help.aliyun.com/zh/dashscope',
                'icon' => 'qwen.svg',
                'description' => 'Qwen models from Alibaba'
            ],
            
            'deepseek' => [
                'id' => 'deepseek',
                'name' => 'DeepSeek',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.deepseek.com',
                'models' => ['deepseek-chat', 'deepseek-coder', 'deepseek-v2'],
                'capabilities' => ['chat', 'coding'],
                'requires_key' => true,
                'key_option' => 'smo_social_deepseek_api_key',
                'docs_url' => 'https://platform.deepseek.com/docs',
                'icon' => 'deepseek.svg',
                'description' => 'Strong coding models'
            ],
            
            // ========================================
            // ⚡ OPTIMIZED INFERENCE PROVIDERS
            // ========================================
            
            'fireworks' => [
                'id' => 'fireworks',
                'name' => 'Fireworks AI',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.fireworks.ai',
                'models' => ['llama-3-70b', 'llama-3-8b', 'mixtral-8x7b', 'mixtral-8x22b', 'mistral-7b'],
                'capabilities' => ['chat', 'fast-inference', 'open-models'],
                'requires_key' => true,
                'key_option' => 'smo_social_fireworks_api_key',
                'docs_url' => 'https://docs.fireworks.ai',
                'icon' => 'fireworks.svg',
                'description' => 'Llama, Mistral, Mixtral optimized'
            ],
            
            'cerebras' => [
                'id' => 'cerebras',
                'name' => 'Cerebras',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.cerebras.ai',
                'models' => ['llama-3.3-70b', 'llama-3.1-70b', 'llama-3.1-8b'],
                'capabilities' => ['chat', 'ultra-fast'],
                'requires_key' => true,
                'key_option' => 'smo_social_cerebras_api_key',
                'docs_url' => 'https://docs.cerebras.ai',
                'icon' => 'cerebras.svg',
                'description' => 'Ultra-fast inference'
            ],
            
            'sambanova' => [
                'id' => 'sambanova',
                'name' => 'SambaNova',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.sambanova.ai',
                'models' => ['llama-3-70b', 'llama-3-8b', 'llama-3.2-90b'],
                'capabilities' => ['chat', 'enterprise'],
                'requires_key' => true,
                'key_option' => 'smo_social_sambanova_api_key',
                'docs_url' => 'https://docs.sambanova.ai',
                'icon' => 'sambanova.svg',
                'description' => 'Enterprise inference'
            ],
            
            'together' => [
                'id' => 'together',
                'name' => 'Together AI',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.together.xyz',
                'models' => ['llama-3-70b', 'llama-3-8b', 'mixtral-8x22b', 'qwen-72b', 'deepseek-coder'],
                'capabilities' => ['chat', 'multi-model', 'open-source'],
                'requires_key' => true,
                'key_option' => 'smo_social_together_api_key',
                'docs_url' => 'https://docs.together.ai',
                'icon' => 'together.svg',
                'description' => 'Llama, Mixtral, Qwen and more'
            ],
            
            // ========================================
            // 🔄 ALTERNATIVE PROVIDERS
            // ========================================
            
            'glama' => [
                'id' => 'glama',
                'name' => 'Glama',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.glama.ai',
                'models' => ['glama-1', 'glama-2'],
                'capabilities' => ['chat'],
                'requires_key' => true,
                'key_option' => 'smo_social_glama_api_key',
                'docs_url' => 'https://docs.glama.ai',
                'icon' => 'glama.svg',
                'description' => 'Alternative LLM provider'
            ],
            
            'moonshot' => [
                'id' => 'moonshot',
                'name' => 'Moonshot AI',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.moonshot.cn',
                'models' => ['moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k'],
                'capabilities' => ['chat', 'long-context'],
                'requires_key' => true,
                'key_option' => 'smo_social_moonshot_api_key',
                'docs_url' => 'https://platform.moonshot.cn/docs',
                'icon' => 'moonshot.svg',
                'description' => 'Alternative LLM models'
            ],
            
            // ========================================
            // 🔀 ROUTER / AGGREGATOR PROVIDERS
            // ========================================
            
            'openrouter' => [
                'id' => 'openrouter',
                'name' => 'OpenRouter',
                'type' => 'router',
                'auth_type' => 'api_key',
                'base_url' => 'https://openrouter.ai/api/v1',
                'models' => ['auto', 'gpt-4', 'claude-3-opus', 'llama-3-70b', 'gemini-pro'],
                'capabilities' => ['chat', 'multi-provider', 'routing'],
                'requires_key' => true,
                'key_option' => 'smo_social_openrouter_api_key',
                'docs_url' => 'https://openrouter.ai/docs',
                'icon' => 'openrouter.svg',
                'description' => 'Router for dozens of models'
            ],
            
            'requesty' => [
                'id' => 'requesty',
                'name' => 'Requesty',
                'type' => 'marketplace',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.requesty.ai',
                'models' => ['marketplace-models'],
                'capabilities' => ['chat', 'marketplace'],
                'requires_key' => true,
                'key_option' => 'smo_social_requesty_api_key',
                'docs_url' => 'https://docs.requesty.ai',
                'icon' => 'requesty.svg',
                'description' => 'Marketplace LLMs'
            ],
            
            'synthetic' => [
                'id' => 'synthetic',
                'name' => 'Synthetic',
                'type' => 'cluster',
                'auth_type' => 'api_key',
                'base_url' => 'https://api.synthetic.ai',
                'models' => ['cluster-models'],
                'capabilities' => ['chat', 'open-source'],
                'requires_key' => true,
                'key_option' => 'smo_social_synthetic_api_key',
                'docs_url' => 'https://docs.synthetic.ai',
                'icon' => 'synthetic.svg',
                'description' => 'Open-source LLM cluster'
            ],
            
            // ========================================
            // 🖥️ LOCAL / SELF-HOSTED PROVIDERS
            // ========================================
            
            'ollama' => [
                'id' => 'ollama',
                'name' => 'Ollama',
                'type' => 'local',
                'auth_type' => 'none',
                'base_url' => 'http://localhost:11434',
                'models' => ['llama3', 'llama3.1', 'llama3.2', 'mistral', 'mixtral', 'phi', 'gemma', 'qwen'],
                'capabilities' => ['chat', 'local', 'privacy'],
                'requires_key' => false,
                'url_option' => 'smo_social_ollama_url',
                'docs_url' => 'https://ollama.ai/docs',
                'icon' => 'ollama.svg',
                'description' => 'Self-hosted local models'
            ],
            
            'lm-studio' => [
                'id' => 'lm-studio',
                'name' => 'LM Studio',
                'type' => 'local',
                'auth_type' => 'none',
                'base_url' => 'http://localhost:1234',
                'models' => ['local-models'],
                'capabilities' => ['chat', 'local', 'desktop'],
                'requires_key' => false,
                'url_option' => 'smo_social_lmstudio_url',
                'docs_url' => 'https://lmstudio.ai/docs',
                'icon' => 'lmstudio.svg',
                'description' => 'Desktop local inference'
            ],
            
            // ========================================
            // 🔧 LEGACY / EXISTING PROVIDERS
            // ========================================
            
            'huggingface' => [
                'id' => 'huggingface',
                'name' => 'HuggingFace',
                'type' => 'cloud',
                'auth_type' => 'api_key',
                'base_url' => 'https://api-inference.huggingface.co',
                'models' => ['mistral-7b', 'llama-2-7b', 'falcon-7b', 'zephyr-7b'],
                'capabilities' => ['chat', 'inference'],
                'requires_key' => true,
                'key_option' => 'smo_social_huggingface_api_key',
                'docs_url' => 'https://huggingface.co/docs',
                'icon' => 'huggingface.svg',
                'description' => 'Open-source model inference'
            ],
            
            'localhost' => [
                'id' => 'localhost',
                'name' => 'Localhost',
                'type' => 'local',
                'auth_type' => 'none',
                'base_url' => 'http://localhost:8000',
                'models' => ['custom'],
                'capabilities' => ['chat', 'custom'],
                'requires_key' => false,
                'url_option' => 'smo_social_localhost_api_url',
                'docs_url' => '',
                'icon' => 'localhost.svg',
                'description' => 'Custom localhost server'
            ],
            
            'custom' => [
                'id' => 'custom',
                'name' => 'Custom API',
                'type' => 'custom',
                'auth_type' => 'api_key',
                'base_url' => '',
                'models' => ['custom'],
                'capabilities' => ['chat', 'custom'],
                'requires_key' => false,
                'url_option' => 'smo_social_custom_api_url',
                'key_option' => 'smo_social_custom_api_key',
                'docs_url' => '',
                'icon' => 'custom.svg',
                'description' => 'Custom API endpoint'
            ]
        ];
    }
    
    /**
     * Get provider configuration by ID
     *
     * @param string $provider_id Provider ID
     * @return array|null Provider configuration or null if not found
     */
    public static function get_provider($provider_id) {
        error_log("SMO_AI_CONFIG: Requesting provider configuration for {$provider_id}");
        // Use unified configuration system
        $unified_provider = self::get_unified_provider($provider_id);

        if ($unified_provider) {
            error_log("SMO_AI_CONFIG: Using unified provider configuration for {$provider_id}");
            error_log("SMO_AI_CONFIG: Provider config: " . print_r($unified_provider, true));
            return $unified_provider;
        }

        error_log("SMO_AI_CONFIG_ERROR: Provider {$provider_id} not found in unified configuration");
        return null;
    }

    /**
     * Check consistency between database and static provider configurations
     *
     * @param array $db_provider Database provider configuration
     * @param array $static_provider Static provider configuration
     * @return array List of inconsistency messages
     */
    private static function check_provider_consistency($db_provider, $static_provider) {
        $mismatches = [];

        // Check critical fields
        $critical_fields = ['auth_type', 'base_url', 'type'];
        foreach ($critical_fields as $field) {
            if (isset($db_provider[$field]) && isset($static_provider[$field]) &&
                $db_provider[$field] !== $static_provider[$field]) {
                $mismatches[] = "{$field} mismatch: db='{$db_provider[$field]}', static='{$static_provider[$field]}'";
            }
        }

        // Check if database provider is missing required fields from static
        $required_fields = ['id', 'name', 'type', 'auth_type', 'base_url', 'models', 'capabilities'];
        foreach ($required_fields as $field) {
            if (!isset($db_provider[$field]) && isset($static_provider[$field])) {
                $mismatches[] = "Missing required field in db provider: {$field}";
            }
        }

        // Check for field type mismatches
        foreach ($static_provider as $field => $value) {
            if (isset($db_provider[$field])) {
                if (is_array($value) && !is_array($db_provider[$field])) {
                    $mismatches[] = "Field type mismatch for {$field}: expected array, got " . gettype($db_provider[$field]);
                } elseif (!is_array($value) && is_array($db_provider[$field])) {
                    $mismatches[] = "Field type mismatch for {$field}: expected " . gettype($value) . ", got array";
                }
            }
        }

        return $mismatches;
    }
    
    /**
     * Get all provider IDs
     * 
     * @return array Array of provider IDs
     */
    public static function get_provider_ids() {
        return array_keys(self::get_all_providers());
    }
    
    /**
     * Get providers by type
     * 
     * @param string $type Provider type (cloud, local, router, etc.)
     * @return array Array of providers matching the type
     */
    public static function get_providers_by_type($type) {
        $all_providers = self::get_all_providers();
        return array_filter($all_providers, function($provider) use ($type) {
            return $provider['type'] === $type;
        });
    }
    
    /**
     * Check if provider is configured
     *
     * @param string $provider_id Provider ID
     * @return bool True if provider has required credentials
     */
    public static function is_provider_configured($provider_id) {
        return DatabaseProviderLoader::is_provider_configured($provider_id);
    }
    
    /**
     * Get unified provider configuration (database + static fallback)
     *
     * @param string $provider_id Provider ID
     * @return array|null Unified provider configuration
     */
    private static function get_unified_provider($provider_id) {
        error_log("SMO_AI_CONFIG: Attempting unified provider lookup for {$provider_id}");

        // First try database
        $db_provider = DatabaseProviderLoader::get_provider_from_database($provider_id);
        error_log("SMO_AI_CONFIG: Database provider result for {$provider_id}: " . ($db_provider ? 'found' : 'not found'));

        if ($db_provider) {
            error_log("SMO_AI_CONFIG: Database provider data: " . print_r($db_provider, true));

            // Validate database provider against static schema
            $static_provider = self::get_all_providers()[$provider_id] ?? null;
            if ($static_provider) {
                error_log("SMO_AI_CONFIG: Static provider found for {$provider_id}, checking consistency");
                $mismatches = self::check_provider_consistency($db_provider, $static_provider);
                if (!empty($mismatches)) {
                    error_log("SMO_AI_CONFIG_WARNING: Configuration mismatches detected for {$provider_id}: " . implode(', ', $mismatches));
                    error_log("SMO_AI_CONFIG: Static provider data: " . print_r($static_provider, true));
                    // Merge to resolve inconsistencies
                    $merged_provider = self::merge_provider_configurations($db_provider, $static_provider);
                    error_log("SMO_AI_CONFIG: Merged provider data: " . print_r($merged_provider, true));
                    return $merged_provider;
                } else {
                    error_log("SMO_AI_CONFIG: No mismatches found, using database provider as-is");
                }
            } else {
                error_log("SMO_AI_CONFIG_WARNING: No static provider found for {$provider_id}, using database provider only");
            }
            return $db_provider;
        }

        // Fall back to static config
        $static_provider = self::get_all_providers()[$provider_id] ?? null;
        if ($static_provider) {
            error_log("SMO_AI_CONFIG: Using static config for {$provider_id} (database provider not found)");
            return $static_provider;
        }

        error_log("SMO_AI_CONFIG_ERROR: No provider found for {$provider_id} in either database or static config");
        return null;
    }

    /**
     * Merge provider configurations to resolve inconsistencies
     *
     * @param array $db_provider Database provider configuration
     * @param array $static_provider Static provider configuration
     * @return array Merged configuration
     */
    private static function merge_provider_configurations($db_provider, $static_provider) {
        // Start with database config as base
        $merged = $db_provider;

        // Ensure critical fields from static config are present
        $critical_fields = ['id', 'name', 'type', 'auth_type', 'base_url', 'models', 'capabilities'];
        foreach ($critical_fields as $field) {
            if (!isset($merged[$field]) && isset($static_provider[$field])) {
                $merged[$field] = $static_provider[$field];
                error_log("SMO_AI_CONFIG: Merged missing critical field {$field} from static config");
            }
        }

        // Handle field conflicts - static config takes precedence for schema fields
        $schema_fields = ['key_option', 'url_option', 'docs_url', 'icon', 'description'];
        foreach ($schema_fields as $field) {
            if (isset($static_provider[$field]) && (!isset($merged[$field]) || empty($merged[$field]))) {
                $merged[$field] = $static_provider[$field];
                error_log("SMO_AI_CONFIG: Using static config value for schema field {$field}");
            }
        }

        // Merge models and capabilities intelligently
        if (isset($static_provider['models']) && !empty($static_provider['models'])) {
            $db_models = $merged['models'] ?? [];
            $static_models = $static_provider['models'];

            // Only merge if database models are missing or different
            if (empty($db_models) || $db_models !== $static_models) {
                $merged['models'] = array_unique(array_merge($db_models, $static_models));
                error_log("SMO_AI_CONFIG: Merged models from static config");
            }
        }

        if (isset($static_provider['capabilities']) && !empty($static_provider['capabilities'])) {
            $db_capabilities = $merged['capabilities'] ?? [];
            $static_capabilities = $static_provider['capabilities'];

            // Only merge if database capabilities are missing or different
            if (empty($db_capabilities) || $db_capabilities !== $static_capabilities) {
                $merged['capabilities'] = array_unique(array_merge($db_capabilities, $static_capabilities));
                error_log("SMO_AI_CONFIG: Merged capabilities from static config");
            }
        }

        // Ensure requires_key is consistent with auth_type
        if (isset($merged['auth_type'])) {
            $merged['requires_key'] = self::determine_requires_key_from_auth_type($merged['auth_type']);
        }

        return $merged;
    }

    /**
     * Determine if provider requires key based on auth type (static method for reusability)
     *
     * @param string $auth_type Authentication type
     * @return bool True if requires key
     */
    private static function determine_requires_key_from_auth_type($auth_type) {
        return !in_array($auth_type, ['none', '']);
    }

    /**
     * Get all configured providers
     *
     * @return array Array of configured providers
     */
    public static function get_configured_providers() {
        return DatabaseProviderLoader::get_configured_providers();
    }
}
