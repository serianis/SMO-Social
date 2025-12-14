<?php
/**
 * AI Provider Configuration Interface
 * Handles user-friendly configuration forms for all AI providers
 */
class SMO_AI_Provider_Configurator {

    private $marketplace;
    private $external_ai_configurator;

    public function __construct() {
        $this->marketplace = new \SMO_Social\AI\ProviderMarketplace();
        $this->external_ai_configurator = new \SMO_Social\AI\ExternalAIConfigurator();
    }
    
    public function render_configuration_interface($provider_key = '') {
        if (!$provider_key || $provider_key === 'custom') {
            return $this->render_custom_provider_form();
        }
        
        return $this->render_prebuilt_provider_form($provider_key);
    }
    
    private function render_prebuilt_provider_form($provider_key) {
        $providers = $this->external_ai_configurator->get_prebuilt_providers();
        
        if (!isset($providers[$provider_key])) {
            return '<div class="error">Provider not found</div>';
        }
        
        $provider = $providers[$provider_key];
        $config = get_option('smo_ai_provider_config_' . $provider_key, []);
        $is_configured = !empty($config['api_key']);
        
        ?>
        <div class="smo-ai-config-interface">
            <div class="config-header">
                <div class="provider-breadcrumb">
                    <a href="<?php echo admin_url('options-general.php?page=smo-ai-settings&tab=marketplace'); ?>" class="back-link">
                        ← Back to Marketplace
                    </a>
                </div>
                <div class="provider-title">
                    <div class="provider-icon"><?php echo $this->get_provider_icon($provider_key); ?></div>
                    <div>
                        <h2>Configure <?php echo $provider['name']; ?></h2>
                        <p class="provider-subtitle"><?php echo $provider['description']; ?></p>
                    </div>
                    <div class="config-status">
                        <?php if ($is_configured): ?>
                            <span class="status-configured">✓ Configured</span>
                        <?php else: ?>
                            <span class="status-new">New Configuration</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <form method="post" class="provider-config-form" id="provider-config-form">
                <?php wp_nonce_field('smo_ai_provider_config', 'smo_ai_nonce'); ?>
                <input type="hidden" name="provider_key" value="<?php echo esc_attr($provider_key); ?>">
                <input type="hidden" name="action" value="save_provider_config">
                
                <div class="config-sections">
                    <!-- API Configuration Section -->
                    <section class="config-section">
                        <h3>🔑 API Configuration</h3>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="api_key">API Key</label>
                                    <div class="input-with-button">
                                        <input type="password" 
                                               id="api_key" 
                                               name="api_key" 
                                               value="<?php echo esc_attr($config['api_key'] ?? ''); ?>" 
                                               class="api-key-input" 
                                               placeholder="Enter your API key"
                                               autocomplete="off">
                                        <button type="button" class="button-toggle-visibility" onclick="togglePasswordVisibility('api_key')">
                                            👁️
                                        </button>
                                    </div>
                                    <div class="field-help">
                                        <p>Get your API key from <a href="<?php echo $provider['signup_url']; ?>" target="_blank"><?php echo $provider['name']; ?></a></p>
                                        <?php if ($provider_key === 'openrouter'): ?>
                                            <p><strong>OpenRouter:</strong> Sign up at <a href="https://openrouter.ai/" target="_blank">openrouter.ai</a> → API Keys → Create Key</p>
                                        <?php elseif ($provider_key === 'openai'): ?>
                                            <p><strong>OpenAI:</strong> Go to <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a> → Create new secret key</p>
                                        <?php elseif ($provider_key === 'anthropic'): ?>
                                            <p><strong>Anthropic:</strong> Visit <a href="https://console.anthropic.com/account/keys" target="_blank">console.anthropic.com</a> → API Keys → Create Key</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="base_url">API Endpoint</label>
                                    <input type="url" 
                                           id="base_url" 
                                           name="base_url" 
                                           value="<?php echo esc_attr($config['base_url'] ?? $provider['base_url']); ?>" 
                                           class="readonly-field" 
                                           readonly>
                                    <p class="field-help">Auto-configured endpoint for <?php echo $provider['name']; ?></p>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Model Selection Section -->
                    <section class="config-section">
                        <h3>🎯 Model Configuration</h3>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="default_model">Default Model</label>
                                    <select id="default_model" name="default_model" class="model-select">
                                        <?php foreach ($provider['models'] as $model_id => $model_name): ?>
                                        <option value="<?php echo esc_attr($model_id); ?>" 
                                                <?php selected($config['default_model'] ?? '', $model_id); ?>>
                                            <?php echo esc_html($model_name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="field-help">Choose your preferred model for content generation</p>
                                </div>
                            </div>
                            
                            <!-- Model-specific recommendations -->
                            <div class="model-recommendations">
                                <h4>Recommended Settings for Selected Model</h4>
                                <div id="model-settings">
                                    <?php echo $this->get_model_recommendations($provider_key, $config['default_model'] ?? ''); ?>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Generation Parameters Section -->
                    <section class="config-section">
                        <h3>⚙️ Generation Parameters</h3>
                        <div class="section-content">
                            <div class="parameters-grid">
                                <div class="form-group">
                                    <label for="max_tokens">Max Tokens</label>
                                    <input type="number" 
                                           id="max_tokens" 
                                           name="max_tokens" 
                                           value="<?php echo esc_attr($config['max_tokens'] ?? 150); ?>" 
                                           min="10" 
                                           max="4000" 
                                           class="parameter-input">
                                    <p class="field-help">Maximum tokens to generate (10-4000)</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="temperature">Temperature</label>
                                    <input type="number" 
                                           id="temperature" 
                                           name="temperature" 
                                           value="<?php echo esc_attr($config['temperature'] ?? 0.7); ?>" 
                                           step="0.1" 
                                           min="0" 
                                           max="2" 
                                           class="parameter-input">
                                    <p class="field-help">Randomness (0.0 = focused, 1.0 = balanced, 2.0 = creative)</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="top_p">Top P</label>
                                    <input type="number" 
                                           id="top_p" 
                                           name="top_p" 
                                           value="<?php echo esc_attr($config['top_p'] ?? 0.9); ?>" 
                                           step="0.1" 
                                           min="0" 
                                           max="1" 
                                           class="parameter-input">
                                    <p class="field-help">Nucleus sampling (0.1-1.0)</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="frequency_penalty">Frequency Penalty</label>
                                    <input type="number" 
                                           id="frequency_penalty" 
                                           name="frequency_penalty" 
                                           value="<?php echo esc_attr($config['frequency_penalty'] ?? 0); ?>" 
                                           step="0.1" 
                                           min="-2" 
                                           max="2" 
                                           class="parameter-input">
                                    <p class="field-help">Reduce repetition (-2.0 to 2.0)</p>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Usage Limits Section -->
                    <section class="config-section">
                        <h3>📊 Usage & Limits</h3>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="rate_limit_hourly">Hourly Rate Limit</label>
                                    <input type="number" 
                                           id="rate_limit_hourly" 
                                           name="rate_limit_hourly" 
                                           value="<?php echo esc_attr($config['rate_limit_hourly'] ?? 1000); ?>" 
                                           min="10" 
                                           max="10000" 
                                           class="parameter-input">
                                    <p class="field-help">API call limit per hour to prevent overuse</p>
                                </div>
                            </div>
                            
                            <div class="usage-info">
                                <h4>Provider Pricing Reference</h4>
                                <div class="pricing-info">
                                    <?php echo $this->get_pricing_info($provider_key); ?>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Content Optimization Section -->
                    <section class="config-section">
                        <h3>🎨 Content Optimization</h3>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="primary_use_case">Primary Use Case</label>
                                    <select id="primary_use_case" name="primary_use_case">
                                        <option value="social_media" <?php selected($config['primary_use_case'] ?? '', 'social_media'); ?>>
                                            📱 Social Media Posts
                                        </option>
                                        <option value="marketing" <?php selected($config['primary_use_case'] ?? '', 'marketing'); ?>>
                                            📈 Marketing Content
                                        </option>
                                        <option value="blog" <?php selected($config['primary_use_case'] ?? '', 'blog'); ?>>
                                            📝 Blog Content
                                        </option>
                                        <option value="general" <?php selected($config['primary_use_case'] ?? '', 'general'); ?>>
                                            🎯 General Content
                                        </option>
                                    </select>
                                    <p class="field-help">Optimize parameters for your main content type</p>
                                </div>
                            </div>
                            
                            <div class="optimization-presets">
                                <h4>Quick Presets</h4>
                                <div class="preset-buttons">
                                    <button type="button" class="preset-btn" data-preset="balanced">Balanced</button>
                                    <button type="button" class="preset-btn" data-preset="creative">Creative</button>
                                    <button type="button" class="preset-btn" data-preset="focused">Focused</button>
                                    <button type="button" class="preset-btn" data-preset="viral">Viral Social</button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                
                <div class="config-actions">
                    <div class="action-group primary">
                        <button type="submit" name="save_provider_config" class="button button-primary button-large">
                            <span class="button-text">💾 Save Configuration</span>
                            <span class="button-loading" style="display: none;">Saving...</span>
                        </button>
                    </div>
                    
                    <div class="action-group secondary">
                        <button type="button" class="button button-secondary test-connection-btn" 
                                data-provider="<?php echo $provider_key; ?>">
                            🔗 Test Connection
                        </button>
                        
                        <button type="button" class="button button-secondary sample-generation-btn" 
                                data-provider="<?php echo $provider_key; ?>">
                            ✨ Sample Generation
                        </button>
                        
                        <?php if ($is_configured): ?>
                        <button type="button" class="button button-secondary reset-config-btn" 
                                data-provider="<?php echo $provider_key; ?>">
                            🔄 Reset
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <!-- Test Results Area -->
            <div id="test-results-area" class="test-results" style="display: none;">
                <h4>Test Results</h4>
                <div id="test-output"></div>
            </div>
        </div>
        
        <style>
        .smo-ai-config-interface {
            max-width: 900px;
            margin: 20px 0;
        }
        
        .config-header {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .provider-breadcrumb {
            margin-bottom: 16px;
        }
        
        .back-link {
            color: #0073aa;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .provider-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .provider-icon {
            font-size: 32px;
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f1;
            border-radius: 12px;
        }
        
        .provider-title h2 {
            margin: 0;
            font-size: 24px;
            color: #1d2327;
        }
        
        .provider-subtitle {
            margin: 4px 0 0 0;
            color: #646970;
            font-size: 14px;
        }
        
        .config-status {
            margin-left: auto;
        }
        
        .status-configured {
            background: #d1e7dd;
            color: #0f5132;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-new {
            background: #e7f3ff;
            color: #084298;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .config-sections {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .config-section {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 24px;
        }
        
        .config-section h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: #1d2327;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1d2327;
        }
        
        .input-with-button {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        
        .api-key-input {
            flex: 1;
        }
        
        .button-toggle-visibility {
            padding: 8px 12px;
            border: 1px solid #c3c4c7;
            background: #f6f7f7;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .readonly-field {
            background: #f9f9f9;
            border: 1px solid #dcdcde;
        }
        
        .field-help {
            margin: 6px 0 0 0;
            color: #646970;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .field-help a {
            color: #0073aa;
        }
        
        .parameters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .parameter-input {
            width: 100%;
        }
        
        .model-select {
            width: 100%;
            min-width: 250px;
        }
        
        .model-recommendations {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }
        
        .model-recommendations h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #495057;
        }
        
        .usage-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 16px;
        }
        
        .usage-info h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #856404;
        }
        
        .pricing-info {
            font-size: 13px;
            color: #856404;
        }
        
        .optimization-presets {
            background: #f0f6fc;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 16px;
        }
        
        .optimization-presets h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #084298;
        }
        
        .preset-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .preset-btn {
            padding: 6px 12px;
            border: 1px solid #b3d7ff;
            background: #fff;
            color: #084298;
            border-radius: 16px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .preset-btn:hover {
            background: #b3d7ff;
            color: #fff;
        }
        
        .config-actions {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
        }
        
        .action-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .button-large {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .button-loading {
            display: inline-block;
        }
        
        .test-results {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .test-results h4 {
            margin: 0 0 16px 0;
            color: #1d2327;
        }
        
        .test-output {
            font-family: monospace;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 16px;
            white-space: pre-wrap;
        }
        
        @media (max-width: 768px) {
            .provider-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .config-actions {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .action-group {
                justify-content: center;
            }
            
            .parameters-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Model selection change handler
            $('#default_model').change(function() {
                updateModelRecommendations();
            });
            
            // Preset buttons
            $('.preset-btn').click(function() {
                const preset = $(this).data('preset');
                applyPreset(preset);
            });
            
            // Form submission with loading state
            $('#provider-config-form').submit(function() {
                const $submitBtn = $(this).find('button[type="submit"]');
                $submitBtn.find('.button-text').hide();
                $submitBtn.find('.button-loading').show();
                $submitBtn.prop('disabled', true);
            });
            
            // Test connection
            $('.test-connection-btn').click(function() {
                testConnection($(this).data('provider'));
            });
            
            // Sample generation
            $('.sample-generation-btn').click(function() {
                sampleGeneration($(this).data('provider'));
            });
            
            // Reset configuration
            $('.reset-config-btn').click(function() {
                if (confirm('Are you sure you want to reset this configuration?')) {
                    resetConfiguration($(this).data('provider'));
                }
            });
        });
        
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁️';
            }
        }
        
        function updateModelRecommendations() {
            const model = $('#default_model').val();
            const provider = $('input[name="provider_key"]').val();
            
            // Update recommended settings based on model
            const recommendations = getModelRecommendations(provider, model);
            
            if (recommendations.temperature) $('#temperature').val(recommendations.temperature);
            if (recommendations.max_tokens) $('#max_tokens').val(recommendations.max_tokens);
        }
        
        function applyPreset(preset) {
            const presets = {
                balanced: { temperature: 0.7, top_p: 0.9, frequency_penalty: 0 },
                creative: { temperature: 1.2, top_p: 0.95, frequency_penalty: 0.3 },
                focused: { temperature: 0.3, top_p: 0.8, frequency_penalty: 0.1 },
                viral: { temperature: 0.9, top_p: 0.92, frequency_penalty: 0.2 }
            };
            
            if (presets[preset]) {
                const settings = presets[preset];
                Object.keys(settings).forEach(key => {
                    $('#' + key).val(settings[key]);
                });
                
                // Show feedback
                showNotification('Applied ' + preset + ' preset!', 'success');
            }
        }
        
        function testConnection(provider) {
            const $btn = $('.test-connection-btn[data-provider="' + provider + '"]');
            const originalText = $btn.text();
            
            $btn.text('Testing...').prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'test_ai_provider',
                provider: provider,
                nonce: smoAI.nonce
            })
            .done(function(response) {
                showTestResults(response);
                if (response.status === 'success') {
                    $btn.text('✓ Connected');
                } else {
                    $btn.text(originalText);
                }
            })
            .fail(function() {
                showTestResults({status: 'error', message: 'Connection test failed'});
                $btn.text(originalText);
            })
            .always(function() {
                $btn.prop('disabled', false);
            });
        }
        
        function sampleGeneration(provider) {
            const $btn = $('.sample-generation-btn[data-provider="' + provider + '"]');
            const originalText = $btn.text();
            
            $btn.text('Generating...').prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'sample_ai_generation',
                provider: provider,
                nonce: smoAI.nonce
            })
            .done(function(response) {
                showTestResults(response);
            })
            .always(function() {
                $btn.text(originalText).prop('disabled', false);
            });
        }
        
        function showTestResults(result) {
            const $resultsArea = $('#test-results-area');
            const $output = $('#test-output');
            
            const resultClass = result.status === 'success' ? 'success' : 'error';
            const icon = result.status === 'success' ? '✅' : '❌';
            
            $output.html(icon + ' ' + result.message + (result.output ? '\n\n' + result.output : ''));
            $resultsArea.show();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: $resultsArea.offset().top - 100
            }, 500);
        }
        
        function showNotification(message, type) {
            const notification = $('<div class="notification ' + type + '">' + message + '</div>');
            $('body').append(notification);
            
            notification.fadeIn();
            setTimeout(function() {
                notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
        </script>
        <?php
    }
    
    private function render_custom_provider_form() {
        $config = get_option('smo_ai_provider_config_custom', []);
        
        ?>
        <div class="smo-ai-config-interface">
            <div class="config-header">
                <div class="provider-breadcrumb">
                    <a href="<?php echo admin_url('options-general.php?page=smo-ai-settings&tab=marketplace'); ?>" class="back-link">
                        ← Back to Marketplace
                    </a>
                </div>
                <div class="provider-title">
                    <div class="provider-icon">🔧</div>
                    <div>
                        <h2>Configure Custom Provider</h2>
                        <p class="provider-subtitle">Connect any OpenAI-compatible API endpoint</p>
                    </div>
                </div>
            </div>
            
            <form method="post" class="provider-config-form" id="custom-provider-form">
                <?php wp_nonce_field('smo_ai_provider_config', 'smo_ai_nonce'); ?>
                <input type="hidden" name="provider_key" value="custom">
                <input type="hidden" name="action" value="save_provider_config">
                
                <div class="config-sections">
                    <section class="config-section">
                        <h3>🔧 Provider Details</h3>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="provider_name">Provider Name</label>
                                    <input type="text" 
                                           id="provider_name" 
                                           name="provider_name" 
                                           value="<?php echo esc_attr($config['provider_name'] ?? ''); ?>" 
                                           class="regular-text" 
                                           placeholder="e.g., My LocalAI Server">
                                    <p class="field-help">A friendly name to identify your custom provider</p>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="base_url">API Endpoint URL</label>
                                    <input type="url" 
                                           id="base_url" 
                                           name="base_url" 
                                           value="<?php echo esc_attr($config['base_url'] ?? ''); ?>" 
                                           class="regular-text" 
                                           placeholder="https://api.my-provider.com/v1/chat/completions">
                                    <p class="field-help">Must be OpenAI-compatible API endpoint</p>
                                </div>
                            </div>
                            
                            <div class="api-examples">
                                <h4>Common API Examples</h4>
                                <div class="example-list">
                                    <div class="api-example" onclick="fillExampleUrl('localai')">
                                        <strong>LocalAI</strong>
                                        <code>http://localhost:8080/v1/chat/completions</code>
                                    </div>
                                    <div class="api-example" onclick="fillExampleUrl('lmstudio')">
                                        <strong>LM Studio</strong>
                                        <code>http://localhost:1234/v1/chat/completions</code>
                                    </div>
                                    <div class="api-example" onclick="fillExampleUrl('ollama')">
                                        <strong>Ollama</strong>
                                        <code>http://localhost:11434/v1/chat/completions</code>
                                    </div>
                                    <div class="api-example" onclick="fillExampleUrl('vllm')">
                                        <strong>vLLM</strong>
                                        <code>http://localhost:8000/v1/chat/completions</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <section class="config-section">
                        <h3>🔑 Authentication</h3>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="api_key">API Key</label>
                                    <input type="password" 
                                           id="api_key" 
                                           name="api_key" 
                                           value="<?php echo esc_attr($config['api_key'] ?? ''); ?>" 
                                           class="regular-text" 
                                           placeholder="Enter your API key (if required)">
                                    <p class="field-help">Leave empty if your API doesn't require authentication</p>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <section class="config-section">
                        <h3>🎯 Model Configuration</h3>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="supported_models">Available Models</label>
                                    <textarea id="supported_models" 
                                              name="supported_models" 
                                              rows="4" 
                                              class="large-text" 
                                              placeholder="model-id-1, model-id-2, model-id-3"><?php 
                                        echo esc_textarea($config['supported_models'] ?? '');
                                    ?></textarea>
                                    <p class="field-help">Comma-separated list of available model IDs</p>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="default_model">Default Model</label>
                                    <input type="text" 
                                           id="default_model" 
                                           name="default_model" 
                                           value="<?php echo esc_attr($config['default_model'] ?? ''); ?>" 
                                           class="regular-text" 
                                           placeholder="model-id-1">
                                    <p class="field-help">Model to use by default (must be in the list above)</p>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Same parameter sections as prebuilt providers -->
                    <section class="config-section">
                        <h3>⚙️ Generation Parameters</h3>
                        <div class="section-content">
                            <?php echo $this->render_parameter_fields($config); ?>
                        </div>
                    </section>
                </div>
                
                <div class="config-actions">
                    <div class="action-group primary">
                        <button type="submit" name="save_provider_config" class="button button-primary button-large">
                            💾 Save Custom Provider
                        </button>
                    </div>
                    
                    <div class="action-group secondary">
                        <button type="button" class="button button-secondary test-connection-btn" data-provider="custom">
                            🔗 Test Connection
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <style>
        .api-examples {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }
        
        .api-examples h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #495057;
        }
        
        .example-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }
        
        .api-example {
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            background: #fff;
        }
        
        .api-example:hover {
            background: #e7f3ff;
            border-color: #b3d7ff;
        }
        
        .api-example strong {
            display: block;
            margin-bottom: 4px;
            color: #0073aa;
        }
        
        .api-example code {
            font-size: 12px;
            color: #495057;
            background: #f1f3f4;
            padding: 2px 4px;
            border-radius: 3px;
        }
        </style>
        
        <script>
        function fillExampleUrl(type) {
            const examples = {
                localai: 'http://localhost:8080/v1/chat/completions',
                lmstudio: 'http://localhost:1234/v1/chat/completions',
                ollama: 'http://localhost:11434/v1/chat/completions',
                vllm: 'http://localhost:8000/v1/chat/completions'
            };
            
            if (examples[type]) {
                $('#base_url').val(examples[type]);
                showNotification('Filled in ' + type + ' example URL', 'success');
            }
        }
        
        // Include all the same JavaScript functionality as the prebuilt form
        jQuery(document).ready(function($) {
            // Same event handlers as prebuilt provider form
            $('.preset-btn').click(function() {
                const preset = $(this).data('preset');
                applyPreset(preset);
            });
            
            $('#custom-provider-form').submit(function() {
                const $submitBtn = $(this).find('button[type="submit"]');
                const originalText = $submitBtn.text();
                $submitBtn.text('Saving...').prop('disabled', true);
            });
            
            $('.test-connection-btn').click(function() {
                testConnection('custom');
            });
        });
        </script>
        <?php
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
        
        return $icons[$provider_key] ?? '🤖';
    }
    
    private function get_model_recommendations($provider_key, $model_id) {
        $recommendations = [
            'openai' => [
                'gpt-4' => ['temperature' => 0.7, 'max_tokens' => 150],
                'gpt-4-turbo' => ['temperature' => 0.7, 'max_tokens' => 200],
                'gpt-3.5-turbo' => ['temperature' => 0.8, 'max_tokens' => 150]
            ],
            'anthropic' => [
                'claude-3-sonnet-20240229' => ['temperature' => 0.7, 'max_tokens' => 150],
                'claude-3-haiku-20240307' => ['temperature' => 0.8, 'max_tokens' => 120],
                'claude-3-opus-20240229' => ['temperature' => 0.6, 'max_tokens' => 200]
            ]
        ];
        
        if (isset($recommendations[$provider_key][$model_id])) {
            $rec = $recommendations[$provider_key][$model_id];
            return "<div class='recommendation'><strong>Recommended:</strong> Temperature {$rec['temperature']}, Max Tokens {$rec['max_tokens']}</div>";
        }
        
        return "<div class='recommendation'>Using default settings for this model</div>";
    }
    
    private function get_pricing_info($provider_key) {
        $pricing = [
            'openrouter' => 'Competitive pricing across 100+ models. Check <a href="https://openrouter.ai/pricing" target="_blank">pricing page</a>',
            'openai' => 'GPT-4: $0.03/1K input tokens, GPT-3.5: $0.0015/1K input tokens',
            'anthropic' => 'Claude-3 Sonnet: $0.015/1K input tokens, Claude-3 Haiku: $0.00025/1K input tokens',
            'together' => 'Varies by model. Generally cost-effective for open source models',
            'groq' => 'Free tier available, then pay-per-use',
            'huggingface' => 'Free tier: 1K requests/month, then $0.002 per 1K tokens'
        ];
        
        return $pricing[$provider_key] ?? 'Check provider website for current pricing';
    }
    
    private function render_parameter_fields($config) {
        return '
        <div class="parameters-grid">
            <div class="form-group">
                <label for="max_tokens">Max Tokens</label>
                <input type="number" 
                       id="max_tokens" 
                       name="max_tokens" 
                       value="' . esc_attr($config['max_tokens'] ?? 150) . '" 
                       min="10" 
                       max="4000" 
                       class="parameter-input">
            </div>
            
            <div class="form-group">
                <label for="temperature">Temperature</label>
                <input type="number" 
                       id="temperature" 
                       name="temperature" 
                       value="' . esc_attr($config['temperature'] ?? 0.7) . '" 
                       step="0.1" 
                       min="0" 
                       max="2" 
                       class="parameter-input">
            </div>
            
            <div class="form-group">
                <label for="top_p">Top P</label>
                <input type="number" 
                       id="top_p" 
                       name="top_p" 
                       value="' . esc_attr($config['top_p'] ?? 0.9) . '" 
                       step="0.1" 
                       min="0" 
                       max="1" 
                       class="parameter-input">
            </div>
            
            <div class="form-group">
                <label for="frequency_penalty">Frequency Penalty</label>
                <input type="number" 
                       id="frequency_penalty" 
                       name="frequency_penalty" 
                       value="' . esc_attr($config['frequency_penalty'] ?? 0) . '" 
                       step="0.1" 
                       min="-2" 
                       max="2" 
                       class="parameter-input">
            </div>
        </div>';
    }
}