<?php
namespace SMO_Social\AI;

/**
 * AI Provider Marketplace for User Configuration
 * Provides user-friendly interface for configuring AI providers
 */
class ProviderMarketplace {
    
    private $external_ai_configurator;
    
    public function __construct() {
        $this->external_ai_configurator = new ExternalAIConfigurator();
    }
    
    /**
     * Render the AI provider marketplace interface
     */
    public function render_marketplace() {
        ?>
        <div class="smo-ai-marketplace">
            <div class="marketplace-header">
                <h2>🤖 AI Provider Marketplace</h2>
                <p>Choose from our curated selection of AI providers, or add your own custom provider.</p>
                
                <div class="marketplace-stats">
                    <div class="stat">
                <strong><?php echo count($this->get_prebuilt_providers()); ?></strong>
                        <span>Pre-built Providers</span>
                    </div>
                    <div class="stat">
                        <strong><?php echo $this->count_total_models(); ?></strong>
                        <span>Available Models</span>
                    </div>
                    <div class="stat">
                        <strong><?php echo count($this->external_ai_configurator->get_configured_providers()); ?></strong>
                        <span>Your Providers</span>
                    </div>
                </div>
            </div>
            
            <div class="provider-categories">
                <div class="category-tabs">
                    <button class="category-tab active" data-category="all">All Providers</button>
                    <button class="category-tab" data-category="free">Free Options</button>
                    <button class="category-tab" data-category="premium">Premium</button>
                    <button class="category-tab" data-category="custom">Custom</button>
                </div>
            </div>
            
            <div class="providers-grid">
                <?php foreach ($this->get_prebuilt_providers() as $key => $provider): ?>
                <div class="provider-card" data-provider="<?php echo $key; ?>" 
                     data-category="<?php echo $this->get_provider_category($key); ?>">
                    <div class="provider-header">
                        <div class="provider-logo">
                            <?php echo $this->get_provider_icon($key); ?>
                        </div>
                        <div class="provider-info">
                            <h3><?php echo $provider['name']; ?></h3>
                            <p class="provider-description"><?php echo $provider['description']; ?></p>
                        </div>
                        <div class="provider-status">
                            <?php if ($this->is_provider_configured($key)): ?>
                                <span class="status-badge configured">✓ Configured</span>
                            <?php else: ?>
                                <span class="status-badge available">Available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="provider-features">
                        <?php foreach ($provider['features'] as $feature): ?>
                        <span class="feature-tag"><?php echo esc_html($feature); ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="provider-models">
                        <h4>Popular Models (<?php echo $this->get_model_count($key); ?>)</h4>
                        <div class="models-list">
                            <?php 
                            $popular_models = $this->get_popular_models($key);
                            foreach ($popular_models as $model_id => $model_name): ?>
                            <span class="model-tag"><?php echo esc_html($model_name); ?></span>
                            <?php endforeach; ?>
                            <?php if ($this->get_model_count($key) > 3): ?>
                            <span class="more-models">+<?php echo $this->get_model_count($key) - 3; ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="provider-actions">
                        <?php if ($this->is_provider_configured($key)): ?>
                            <button class="button button-primary test-provider" 
                                    data-provider="<?php echo $key; ?>">
                                Test Connection
                            </button>
                            <button class="button edit-provider" 
                                    data-provider="<?php echo $key; ?>">
                                Edit
                            </button>
                            <a href="<?php echo $provider['pricing_url']; ?>" 
                               target="_blank" class="button-link">
                                Pricing
                            </a>
                        <?php else: ?>
                            <button class="button button-primary configure-provider" 
                                    data-provider="<?php echo $key; ?>">
                                Configure Provider
                            </button>
                            <a href="<?php echo $provider['signup_url']; ?>" 
                               target="_blank" class="button-link">
                                Get API Key
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Custom Provider Card -->
                <div class="provider-card custom-provider" data-category="custom">
                    <div class="provider-header">
                        <div class="provider-logo">
                            <span class="custom-icon">🔧</span>
                        </div>
                        <div class="provider-info">
                            <h3>Custom Provider</h3>
                            <p class="provider-description">Connect any OpenAI-compatible API endpoint</p>
                        </div>
                        <div class="provider-status">
                            <span class="status-badge custom">Flexible</span>
                        </div>
                    </div>
                    
                    <div class="provider-features">
                        <span class="feature-tag">Any API</span>
                        <span class="feature-tag">OpenAI Compatible</span>
                        <span class="feature-tag">Full Control</span>
                    </div>
                    
                    <div class="provider-models">
                        <h4>Supported APIs</h4>
                        <div class="models-list">
                            <span class="model-tag">OpenAI Format</span>
                            <span class="model-tag">LocalAI</span>
                            <span class="model-tag">LM Studio</span>
                            <span class="more-models">+ Any compatible</span>
                        </div>
                    </div>
                    
                    <div class="provider-actions">
                        <button class="button button-secondary configure-custom-provider">
                            Add Custom Provider
                        </button>
                        <span class="button-help">Support for LocalAI, vLLM, and more</span>
                    </div>
                </div>
            </div>
            
            <div class="marketplace-footer">
                <h3>💡 Tips for Choosing an AI Provider</h3>
                <div class="tips-grid">
                    <div class="tip">
                        <strong>🎯 For Social Media:</strong> OpenRouter offers great variety at competitive prices
                    </div>
                    <div class="tip">
                        <strong>💰 For Budget-Conscious:</strong> HuggingFace and Groq have generous free tiers
                    </div>
                    <div class="tip">
                        <strong>🚀 For Performance:</strong> OpenAI GPT-4 and Anthropic Claude are top-tier
                    </div>
                    <div class="tip">
                        <strong>🔒 For Privacy:</strong> Use Together AI or setup a local provider
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .smo-ai-marketplace {
            max-width: 1200px;
            margin: 20px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .marketplace-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .marketplace-header h2 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #1d2327;
        }
        
        .marketplace-header p {
            font-size: 16px;
            color: #646970;
            margin-bottom: 30px;
        }
        
        .marketplace-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat strong {
            display: block;
            font-size: 24px;
            color: #0073aa;
            font-weight: 600;
        }
        
        .stat span {
            font-size: 14px;
            color: #646970;
        }
        
        .category-tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 1px solid #c3c4c7;
            padding-bottom: 20px;
        }
        
        .category-tab {
            padding: 10px 20px;
            border: 1px solid #c3c4c7;
            background: #fff;
            color: #646970;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .category-tab:hover,
        .category-tab.active {
            background: #0073aa;
            color: #fff;
            border-color: #0073aa;
        }
        
        .providers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .provider-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .provider-card:hover {
            border-color: #0073aa;
            box-shadow: 0 4px 12px rgba(0, 115, 170, 0.1);
            transform: translateY(-2px);
        }
        
        .provider-card.custom-provider {
            border-style: dashed;
            background: #fafafa;
        }
        
        .provider-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .provider-logo {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: #f0f0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .provider-info {
            flex: 1;
        }
        
        .provider-info h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: #1d2327;
        }
        
        .provider-description {
            margin: 0;
            font-size: 14px;
            color: #646970;
            line-height: 1.5;
        }
        
        .provider-status {
            flex-shrink: 0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-badge.configured {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .status-badge.available {
            background: #e7f3ff;
            color: #084298;
        }
        
        .status-badge.custom {
            background: #fff3cd;
            color: #664d03;
        }
        
        .provider-features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .feature-tag {
            background: #f6f7f7;
            color: #646970;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .provider-models h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #1d2327;
        }
        
        .models-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .model-tag {
            background: #e7f3ff;
            color: #084298;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .more-models {
            color: #646970;
            font-size: 12px;
            padding: 4px 0;
        }
        
        .provider-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .button-link {
            color: #0073aa;
            text-decoration: none;
            font-size: 13px;
        }
        
        .button-link:hover {
            text-decoration: underline;
        }
        
        .button-help {
            font-size: 12px;
            color: #646970;
            font-style: italic;
        }
        
        .marketplace-footer {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 24px;
            margin-top: 40px;
        }
        
        .marketplace-footer h3 {
            margin: 0 0 20px 0;
            color: #1d2327;
        }
        
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .tip {
            padding: 16px;
            background: #fff;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
        }
        
        .tip strong {
            display: block;
            margin-bottom: 8px;
            color: #0073aa;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .providers-grid {
                grid-template-columns: 1fr;
            }
            
            .marketplace-stats {
                flex-direction: column;
                gap: 20px;
            }
            
            .category-tabs {
                flex-wrap: wrap;
            }
            
            .provider-actions {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        /* Hidden by category filter */
        .provider-card.hidden {
            display: none;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Category filtering
            $('.category-tab').click(function() {
                const category = $(this).data('category');
                
                // Update active tab
                $('.category-tab').removeClass('active');
                $(this).addClass('active');
                
                // Filter providers
                $('.provider-card').removeClass('hidden');
                if (category !== 'all') {
                    $('.provider-card').not('[data-category="' + category + '"]').addClass('hidden');
                }
            });
            
            // Provider actions
            $('.configure-provider').click(function() {
                const provider = $(this).data('provider');
                window.location.href = '<?php echo admin_url('options-general.php?page=smo-ai-settings&tab=configure&provider='); ?>' + provider;
            });
            
            $('.configure-custom-provider').click(function() {
                window.location.href = '<?php echo admin_url('options-general.php?page=smo-ai-settings&tab=configure&provider=custom'); ?>';
            });
            
            $('.test-provider').click(function() {
                const provider = $(this).data('provider');
                testProvider(provider, $(this));
            });
            
            $('.edit-provider').click(function() {
                const provider = $(this).data('provider');
                window.location.href = '<?php echo admin_url('options-general.php?page=smo-ai-settings&tab=configure&provider='); ?>' + provider;
            });
        });
        
        function testProvider(provider, $button) {
            const originalText = $button.text();
            $button.text('Testing...').prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'test_ai_provider',
                provider: provider,
                nonce: smoAI.nonce
            })
            .done(function(response) {
                showTestResult(response);
                if (response.status === 'success') {
                    $button.text('Connected ✓');
                } else {
                    $button.text(originalText);
                }
            })
            .fail(function() {
                showTestResult({status: 'error', message: 'Test failed'});
                $button.text(originalText);
            })
            .always(function() {
                $button.prop('disabled', false);
            });
        }
        
        function showTestResult(result) {
            const resultClass = result.status === 'success' ? 'success' : 'error';
            const message = result.status === 'success' ? '✅ ' + result.message : '❌ ' + result.message;
            
            // Create temporary notification
            const notification = $('<div class="test-notification ' + resultClass + '">' + message + '</div>');
            $('body').append(notification);
            
            // Show notification
            notification.fadeIn();
            
            // Auto remove after 3 seconds
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
        </script>
        <?php
    }
    
    /**
     * Get prebuilt provider configurations
     */
    private function get_prebuilt_providers() {
        return $this->external_ai_configurator->get_prebuilt_providers();
    }
    
    private function count_total_models() {
        $total = 0;
        $providers = $this->get_prebuilt_providers();
        foreach ($providers as $provider) {
            $total += $this->get_model_count_by_provider($provider);
        }
        return $total;
    }
    
    private function get_model_count_by_provider($provider) {
        // Count models for different provider types
        if (isset($provider['models']) && is_array($provider['models'])) {
            return count($provider['models']);
        }
        return 5; // Default estimate
    }
    
    private function get_model_count($provider_key) {
        $providers = $this->get_prebuilt_providers();
        return $this->get_model_count_by_provider($providers[$provider_key] ?? []);
    }
    
    private function get_popular_models($provider_key) {
        $providers = $this->get_prebuilt_providers();
        $provider = $providers[$provider_key] ?? [];
        
        if (isset($provider['models']) && is_array($provider['models'])) {
            return array_slice($provider['models'], 0, 3, true);
        }
        
        // Default popular models
        $default_models = [
            'openai' => ['gpt-3.5-turbo' => 'GPT-3.5 Turbo', 'gpt-4' => 'GPT-4'],
            'anthropic' => ['claude-3-haiku-20240307' => 'Claude-3 Haiku', 'claude-3-sonnet-20240229' => 'Claude-3 Sonnet'],
            'huggingface' => ['microsoft/DialoGPT-large' => 'DialoGPT Large', 'cardiffnlp/twitter-roberta-base-sentiment-latest' => 'RoBERTa Sentiment']
        ];
        
        return $default_models[$provider_key] ?? ['gpt-3.5-turbo' => 'Default Model'];
    }
    
    private function get_provider_category($provider_key) {
        $categories = [
            'openrouter' => 'premium',
            'openai' => 'premium', 
            'anthropic' => 'premium',
            'together' => 'free',
            'groq' => 'free',
            'huggingface' => 'free'
        ];
        
        return $categories[$provider_key] ?? 'premium';
    }
    
    private function get_provider_icon($provider_key) {
        $icons = [
            'openrouter' => '🌐',
            'openai' => '🤖',
            'anthropic' => '🧠',
            'together' => '🚀',
            'groq' => '⚡',
            'huggingface' => '🤗'
        ];
        
        return '<span class="provider-icon">' . ($icons[$provider_key] ?? '🤖') . '</span>';
    }
    
    private function is_provider_configured($provider_key) {
        $configured_providers = $this->external_ai_configurator->get_configured_providers();
        return isset($configured_providers[$provider_key]);
    }
}
