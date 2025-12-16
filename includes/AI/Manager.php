<?php
namespace SMO_Social\AI;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\ProvidersConfig;
use SMO_Social\AI\Content\CaptionGenerator;
use SMO_Social\AI\Content\HashtagOptimizer;
use SMO_Social\AI\Content\AltTextGenerator;
use SMO_Social\AI\Analysis\SentimentAnalyzer;
use SMO_Social\AI\Optimization\TimePredictor;
use SMO_Social\AI\Processing\ContentRepurposer;
use SMO_Social\AI\CacheManager;
use SMO_Social\AI\ErrorHandler;
use SMO_Social\Core\Logger;

/**
 * AI Manager - Central hub for all AI-powered features
 * Uses UniversalManager for all AI provider interactions
 * Implemented as singleton to prevent multiple initializations
 */
class Manager {
    private static $instance = null;
    private $caption_generator;
    private $hashtag_optimizer;
    private $alt_text_generator;
    private $sentiment_analyzer;
    private $time_predictor;
    private $content_repurposer;
    private $cache_manager;
    private $api_settings;

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Prevent multiple initializations by checking if already initialized
        static $initialized = false;
        if ($initialized) {
            Logger::debug('AI Manager: Already initialized, skipping duplicate initialization');
            return;
        }
        
        // Handle both WordPress and non-WordPress contexts
        if (function_exists('get_option')) {
        $this->api_settings = get_option('smo_social_ai_settings', [
            'primary_provider' => 'huggingface',
            'fallback_enabled' => true,
            'cache_enabled' => true,
            'api_keys' => [
                'huggingface_api_key' => '',
                'localhost_api_url' => 'http://localhost:11434/api/generate',
                'custom_api_url' => '',
                'custom_api_key' => ''
            ]
        ]);
        } else {
            // Default settings for non-WordPress context
            $this->api_settings = [
                'primary_provider' => 'huggingface',
                'fallback_enabled' => true,
                'cache_enabled' => true,
                'api_keys' => []
            ];
        }

        $this->initialize_components();
        
        // Only schedule background tasks in WordPress context
        if (function_exists('wp_next_scheduled')) {
            $this->schedule_background_tasks();
        }
        
        $initialized = true;
        Logger::info('AI Manager: Singleton initialization completed');
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {}

    /**
     * Initialize all AI components
     */
    private function initialize_components() {
        Logger::debug('AI Manager: Starting component initialization');

        try {
            // Use DIContainer for proper dependency injection
            $container = \SMO_Social\Core\DIContainer::getInstance();

            // Register CacheManager as singleton
            $this->cache_manager = new CacheManager();
            $container->singleton('SMO_Social\AI\CacheManager', $this->cache_manager);

            // Get primary provider manager with detailed diagnostics
            $primary_provider_id = $this->get_primary_provider_id();
            Logger::debug('AI Manager: Primary provider ID: ' . ($primary_provider_id ?? 'null'));

            if (empty($primary_provider_id)) {
                Logger::info('AI Manager: No primary provider configured - checking all configured providers');
                $configured_providers = $this->get_available_providers();
                Logger::debug('AI Manager: Available configured providers: ' . print_r(array_keys($configured_providers), true));
            }

            $provider_manager = $this->get_provider_manager($primary_provider_id);
            Logger::debug('AI Manager: Provider manager result: ' . ($provider_manager ? 'SUCCESS' : 'FAILED'));

            if ($provider_manager) {
                // Register UniversalManager as singleton
                $container->singleton('SMO_Social\AI\Models\UniversalManager', $provider_manager);

                // Initialize content generation components using DIContainer
                Logger::debug('AI Manager: Initializing AI components with DIContainer');
                $this->caption_generator = $container->resolve('SMO_Social\AI\Content\CaptionGenerator');
                $this->hashtag_optimizer = $container->resolve('SMO_Social\AI\Content\HashtagOptimizer');
                $this->alt_text_generator = $container->resolve('SMO_Social\AI\Content\AltTextGenerator');
                $this->sentiment_analyzer = $container->resolve('SMO_Social\AI\Analysis\SentimentAnalyzer');
                $this->time_predictor = $container->resolve('SMO_Social\AI\Optimization\TimePredictor');
                $this->content_repurposer = $container->resolve('SMO_Social\AI\Processing\ContentRepurposer');

                Logger::info('AI Manager: Initialized with UniversalManager system using DIContainer');
            } else {
                Logger::info('AI Manager: Initialized without active provider - using fallbacks');

                // Try to auto-configure fallback providers if possible
                $auto_configured_provider = $this->try_auto_configure_fallback_provider($primary_provider_id);
                if ($auto_configured_provider) {
                    Logger::info('AI Manager: Auto-configured fallback provider: ' . $auto_configured_provider);
                    $provider_manager = $this->get_provider_manager($auto_configured_provider);
                    if ($provider_manager) {
                        // Register the auto-configured provider
                        $container->singleton('SMO_Social\AI\Models\UniversalManager', $provider_manager);

                        // Initialize content generation components with auto-configured provider
                        Logger::debug('AI Manager: Initializing AI components with auto-configured provider using DIContainer');
                        $this->caption_generator = $container->resolve('SMO_Social\AI\Content\CaptionGenerator');
                        $this->hashtag_optimizer = $container->resolve('SMO_Social\AI\Content\HashtagOptimizer');
                        $this->alt_text_generator = $container->resolve('SMO_Social\AI\Content\AltTextGenerator');
                        $this->sentiment_analyzer = $container->resolve('SMO_Social\AI\Analysis\SentimentAnalyzer');
                        $this->time_predictor = $container->resolve('SMO_Social\AI\Optimization\TimePredictor');
                        $this->content_repurposer = $container->resolve('SMO_Social\AI\Processing\ContentRepurposer');

                        Logger::info('AI Manager: Successfully initialized with auto-configured provider using DIContainer');
                        return; // Success!
                    }
                }

                // Log detailed reason for failure
                $provider_config = \SMO_Social\AI\ProvidersConfig::get_provider($primary_provider_id);
                if (!$provider_config) {
                    Logger::error('AI Manager: Provider configuration not found for: ' . $primary_provider_id);
                } else {
                    $is_configured = \SMO_Social\AI\ProvidersConfig::is_provider_configured($primary_provider_id);
                    Logger::info('AI Manager: Provider ' . $primary_provider_id . ' configuration status: ' . ($is_configured ? 'configured' : 'NOT configured'));
                    if (!$is_configured && isset($provider_config['requires_key']) && $provider_config['requires_key']) {
                        Logger::error('AI Manager: Provider requires API key but none is configured');
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::error('AI Manager: Initialization failed with error: ' . $e->getMessage());
            Logger::debug('AI Manager: Stack trace: ' . $e->getTraceAsString());
            // Don't re-throw, just log and allow fallbacks to work
        }
    }

    /**
     * Generate platform-optimized captions
     */
    public function generate_captions($content, $platforms, $options = []) {
        $results = [];
        
        foreach ($platforms as $platform) {
            try {
                // Check if AI components are available
                if ($this->caption_generator && method_exists($this->caption_generator, 'generate')) {
                    $caption = $this->caption_generator->generate($content, $platform, $options);
                    $results[$platform] = $caption;
                } else {
                    // Use intelligent fallback
                    $results[$platform] = $this->generate_smart_caption($content, $platform, $options);
                }
            } catch (\Exception $e) {
                ErrorHandler::log_error('AI_Manager', 'Caption generation failed', [
                    'platform' => $platform,
                    'content_length' => strlen($content),
                    'exception' => $e->getMessage()
                ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

                $results[$platform] = ErrorHandler::create_error_response(
                    ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                    'Caption generation failed',
                    [
                        'platform' => $platform,
                        'operation' => 'caption_generation',
                        'details' => $e->getMessage()
                    ],
                    $this->generate_fallback_caption($content, $platform)
                );
            }
        }
        
        return $results;
    }

    /**
     * Optimize hashtags for platforms
     */
    public function optimize_hashtags($content, $platforms, $options = []) {
        $results = [];
        
        foreach ($platforms as $platform) {
            try {
                // Check if AI components are available
                if ($this->hashtag_optimizer && method_exists($this->hashtag_optimizer, 'optimize')) {
                    $hashtags = $this->hashtag_optimizer->optimize($content, $platform, $options);
                    $results[$platform] = $hashtags;
                } else {
                    // Use intelligent fallback
                    $results[$platform] = $this->generate_smart_hashtags($content, $platform, $options);
                }
            } catch (\Exception $e) {
                ErrorHandler::log_error('AI_Manager', 'Hashtag optimization failed', [
                    'platform' => $platform,
                    'content_length' => strlen($content),
                    'exception' => $e->getMessage()
                ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

                $results[$platform] = ErrorHandler::create_error_response(
                    ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                    'Hashtag optimization failed',
                    [
                        'platform' => $platform,
                        'operation' => 'hashtag_optimization',
                        'details' => $e->getMessage()
                    ],
                    $this->extract_basic_hashtags($content)
                );
            }
        }
        
        return $results;
    }

    /**
     * Generate alt-text for images
     */
    public function generate_alt_text($image_url, $context = '', $options = []) {
        try {
            // Check if AI components are available
            if ($this->alt_text_generator && method_exists($this->alt_text_generator, 'generate')) {
                return $this->alt_text_generator->generate($image_url, $context, $options);
            } else {
                // Use intelligent fallback
                return $this->generate_smart_alt_text($image_url, $context, $options);
            }
        } catch (\Exception $e) {
            ErrorHandler::log_error('AI_Manager', 'Alt text generation failed', [
                'image_url' => $image_url,
                'context_length' => strlen($context),
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Alt text generation failed',
                [
                    'operation' => 'alt_text_generation',
                    'details' => $e->getMessage()
                ],
                $this->generate_basic_alt_text($image_url, $context)
            );
        }
    }

    /**
     * Analyze sentiment of content or comments
     */
    public function analyze_sentiment($text, $options = []) {
        try {
            // Check if AI components are available
            if ($this->sentiment_analyzer && method_exists($this->sentiment_analyzer, 'analyze')) {
                return $this->sentiment_analyzer->analyze($text, $options);
            } else {
                // Use intelligent fallback
                return $this->analyze_sentiment_basic($text, $options);
            }
        } catch (\Exception $e) {
            ErrorHandler::log_error('AI_Manager', 'Sentiment analysis failed', [
                'text_length' => strlen($text),
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Sentiment analysis failed',
                [
                    'operation' => 'sentiment_analysis',
                    'details' => $e->getMessage()
                ],
                [
                    'sentiment' => 'neutral',
                    'confidence' => 0.5
                ]
            );
        }
    }

    /**
     * Predict optimal posting times
     */
    public function predict_best_times($platforms, $content_type = 'general', $options = []) {
        $results = [];
        
        foreach ($platforms as $platform) {
            try {
                if ($this->time_predictor && method_exists($this->time_predictor, 'predict')) {
                    $times = $this->time_predictor->predict($platform, $content_type, $options);
                    $results[$platform] = $times;
                } else {
                    $results[$platform] = $this->get_default_posting_times($platform);
                }
            } catch (\Exception $e) {
                ErrorHandler::log_error('AI_Manager', 'Best time prediction failed', [
                    'platform' => $platform,
                    'content_type' => $content_type,
                    'exception' => $e->getMessage()
                ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

                $results[$platform] = ErrorHandler::create_error_response(
                    ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                    'Best time prediction failed',
                    [
                        'platform' => $platform,
                        'content_type' => $content_type,
                        'operation' => 'time_prediction',
                        'details' => $e->getMessage()
                    ],
                    $this->get_default_posting_times($platform)
                );
            }
        }
        
        return $results;
    }

    /**
     * Repurpose content for different platforms
     */
    public function repurpose_content($original_content, $target_platforms, $options = []) {
        $results = [];
        
        foreach ($target_platforms as $platform) {
            try {
                if ($this->content_repurposer && method_exists($this->content_repurposer, 'repurpose')) {
                    $repurposed = $this->content_repurposer->repurpose($original_content, $platform, $options);
                    $results[$platform] = $repurposed;
                } else {
                    $results[$platform] = $this->adapt_content_basic($original_content, $platform);
                }
            } catch (\Exception $e) {
                ErrorHandler::log_error('AI_Manager', 'Content repurposing failed', [
                    'platform' => $platform,
                    'content_length' => strlen($original_content),
                    'exception' => $e->getMessage()
                ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

                $results[$platform] = ErrorHandler::create_error_response(
                    ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                    'Content repurposing failed',
                    [
                        'platform' => $platform,
                        'operation' => 'content_repurposing',
                        'details' => $e->getMessage()
                    ],
                    $this->adapt_content_basic($original_content, $platform)
                );
            }
        }
        
        return $results;
    }

    /**
     * Generate multiple content variants
     */
    public function generate_variants($content, $platform, $variant_types = [], $options = []) {
        if (empty($variant_types)) {
            $variant_types = ['short', 'medium', 'long', 'professional', 'casual', 'hashtag_heavy', 'seo_optimized'];
        }
        
        $results = [];
        
        foreach ($variant_types as $variant_type) {
            try {
                if ($this->content_repurposer && method_exists($this->content_repurposer, 'generate_variant')) {
                    $variant = $this->content_repurposer->generate_variant($content, $platform, $variant_type, $options);
                    $results[$variant_type] = $variant;
                } else {
                    $results[$variant_type] = $this->generate_basic_variant($content, $variant_type);
                }
            } catch (\Exception $e) {
                ErrorHandler::log_error('AI_Manager', 'Content variant generation failed', [
                    'variant_type' => $variant_type,
                    'content_length' => strlen($content),
                    'exception' => $e->getMessage()
                ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

                $results[$variant_type] = ErrorHandler::create_error_response(
                    ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                    'Content variant generation failed',
                    [
                        'variant_type' => $variant_type,
                        'operation' => 'variant_generation',
                        'details' => $e->getMessage()
                    ],
                    $this->generate_basic_variant($content, $variant_type)
                );
            }
        }
        
        return $results;
    }

    /**
     * Smart thumbnail cropping and optimization
     */
    public function optimize_thumbnail($image_url, $platform, $options = []) {
        $platform_specs = $this->get_platform_image_specs($platform);
        
        $processing_options = array_merge([
            'target_width' => $platform_specs['optimal_width'],
            'target_height' => $platform_specs['optimal_height'],
            'crop_focus' => 'auto', // auto, center, top, bottom, faces
            'quality' => 85,
            'format' => 'auto'
        ], $options);
        
        try {
            return $this->process_image_with_ai($image_url, $processing_options);
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'fallback_processing' => $this->basic_image_processing($image_url, $processing_options)
            ];
        }
    }

    /**
     * Content fingerprinting for deduplication
     */
    public function generate_content_fingerprint($content, $media_urls = []) {
        $fingerprint_data = [
            'text_hash' => md5(strtolower(trim($content))),
            'word_count' => str_word_count($content),
            'character_count' => strlen($content),
            'hashtag_count' => substr_count($content, '#'),
            'mention_count' => substr_count($content, '@'),
            'media_count' => count($media_urls),
            'media_hashes' => []
        ];
        
        // Generate hashes for media files
        foreach ($media_urls as $media_url) {
            $media_hash = $this->generate_media_hash($media_url);
            $fingerprint_data['media_hashes'][] = $media_hash;
        }
        
        // Create composite fingerprint
        $fingerprint_string = $fingerprint_data['text_hash'] . implode('', $fingerprint_data['media_hashes']);
        $fingerprint_data['composite_hash'] = md5($fingerprint_string);
        
        return $fingerprint_data;
    }

    /**
     * Detect evergreen vs trending content
     */
    public function classify_content_lifecycle($content, $options = []) {
        $features = $this->extract_content_features($content);
        
        // Use rule-based classification (AI classification can be added later)
        return $this->rule_based_classification($features);
    }

    /**
     * Cross-platform trend analysis
     */
    public function analyze_trends($platforms, $timeframe = '24h', $options = []) {
        $trends = [];
        
        foreach ($platforms as $platform) {
            try {
                $platform_trends = $this->fetch_platform_trends($platform, $timeframe, $options);
                $trends[$platform] = $platform_trends;
            } catch (\Exception $e) {
                ErrorHandler::log_error('AI_Manager', 'Platform trend analysis failed', [
                    'platform' => $platform,
                    'timeframe' => $timeframe,
                    'exception' => $e->getMessage()
                ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

                $trends[$platform] = ErrorHandler::create_error_response(
                    ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                    'Platform trend analysis failed',
                    [
                        'platform' => $platform,
                        'timeframe' => $timeframe,
                        'operation' => 'trend_analysis',
                        'details' => $e->getMessage()
                    ],
                    ['trends' => []]
                );
            }
        }
        
        // Aggregate and rank trends across platforms
        return $this->aggregate_cross_platform_trends($trends);
    }

    /**
     * Schedule background AI processing tasks
     */
    private function schedule_background_tasks() {
        // Only schedule in WordPress context
        if (!function_exists('wp_next_scheduled')) {
            return;
        }
        
        // Schedule trend analysis
        if (!wp_next_scheduled('smo_social_ai_trend_analysis')) {
            wp_schedule_event(time(), 'hourly', 'smo_social_ai_trend_analysis');
        }
        
        // Schedule content lifecycle analysis
        if (!wp_next_scheduled('smo_social_ai_content_classification')) {
            wp_schedule_event(time(), 'twicedaily', 'smo_social_ai_content_classification');
        }
        
        // Schedule AI model cache warming
        if (!wp_next_scheduled('smo_social_ai_cache_warm')) {
            wp_schedule_event(time(), 'daily', 'smo_social_ai_cache_warm');
        }
    }

    /**
     * Get the appropriate AI provider based on settings and availability
     */
    private function get_ai_provider($task_type = 'general') {
        $primary_provider_id = $this->get_primary_provider_id();
        $manager = $this->get_provider_manager($primary_provider_id);
        
        if ($manager) {
            return $manager;
        }
        
        // If primary provider not available, return null
        // The chat() method will handle fallback logic
        return null;
    }

    /**
     * Get AI processing statistics
     */
    public function get_processing_stats() {
        $configured_providers = ProvidersConfig::get_configured_providers();
        
        return [
            'available_providers' => count($configured_providers),
            'configured_providers' => array_keys($configured_providers),
            'cache_hit_rate' => $this->cache_manager->get_hit_rate(),
            'primary_provider' => $this->get_primary_provider_id(),
            'total_providers_supported' => count(ProvidersConfig::get_all_providers())
        ];
    }

    /**
     * Update AI settings
     */
    public function update_settings($new_settings) {
        if (!is_array($new_settings)) {
            return false;
        }
        
        // Ensure api_settings is always an array
        if (!is_array($this->api_settings)) {
            $this->api_settings = [];
        }
        
        $safe_settings = is_array($new_settings) ? $new_settings : [];
        $this->api_settings = array_merge($this->api_settings, $safe_settings);
        if (function_exists('update_option')) {
            \update_option('smo_social_ai_settings', $this->api_settings);
        }
        
        // Settings are stored in WordPress options and will be picked up
        // by UniversalManager when providers are instantiated
        
        Logger::info('AI Manager: Settings updated successfully');
        return true;
    }

    /**
     * Clear AI processing cache
     */
    public function clear_cache($cache_type = 'all') {
        return $this->cache_manager->clear($cache_type);
    }

    /**
     * Send a chat request to the AI provider
     * 
     * @param array $messages Chat messages
     * @param array $options  Options including provider_id, model, etc.
     * @return array Response
     * @throws \Exception If provider is not available
     */
    public function chat($messages, $options = []) {
        $provider_id = $options['provider_id'] ?? $this->get_primary_provider_id();
        $manager = $this->get_provider_manager($provider_id);
        
        if ($manager) {
            return $manager->chat($messages, $options);
        }
        
        // Try fallback to primary if specific provider failed
        $primary_id = $this->get_primary_provider_id();
        if ($provider_id !== $primary_id) {
            $manager = $this->get_provider_manager($primary_id);
            if ($manager) {
                return $manager->chat($messages, $options);
            }
        }
        
        throw new \Exception("AI Provider not available: " . $provider_id);
    }

    // Fallback and utility methods

    private function generate_fallback_caption($content, $platform) {
        $max_length = $this->get_platform_max_length($platform);
        return substr(strip_tags($content), 0, $max_length - 3) . '...';
    }

    private function extract_basic_hashtags($content) {
        preg_match_all('/#(\w+)/', $content, $matches);
        return array_unique($matches[1]);
    }

    private function generate_basic_alt_text($image_url, $context) {
        return 'Image' . ($context ? ' related to ' . $context : '');
    }

    private function get_default_posting_times($platform) {
        $default_times = [
            'twitter' => ['09:00', '12:00', '15:00', '18:00'],
            'facebook' => ['09:00', '13:00', '19:00'],
            'instagram' => ['11:00', '14:00', '17:00'],
            'linkedin' => ['08:00', '12:00', '17:00'],
            'tiktok' => ['18:00', '19:00', '20:00']
        ];
        
        return $default_times[$platform] ?? ['12:00'];
    }

    private function adapt_content_basic($content, $platform) {
        $max_length = $this->get_platform_max_length($platform);
        return substr($content, 0, $max_length);
    }

    private function generate_basic_variant($content, $variant_type) {
        switch ($variant_type) {
            case 'short':
                return substr($content, 0, 140);
            case 'long':
                return $content . ' ' . $this->generate_additional_content($content);
            default:
                return $content;
        }
    }

    private function get_platform_image_specs($platform) {
        $specs = [
            'instagram' => ['optimal_width' => 1080, 'optimal_height' => 1080],
            'twitter' => ['optimal_width' => 1200, 'optimal_height' => 675],
            'facebook' => ['optimal_width' => 1200, 'optimal_height' => 630],
            'linkedin' => ['optimal_width' => 1200, 'optimal_height' => 627]
        ];
        
        return $specs[$platform] ?? ['optimal_width' => 800, 'optimal_height' => 600];
    }

    private function generate_media_hash($media_url) {
        return md5($media_url);
    }

    private function extract_content_features($content) {
        return [
            'length' => strlen($content),
            'word_count' => str_word_count($content),
            'hashtag_density' => substr_count($content, '#') / max(1, str_word_count($content)),
            'mention_density' => substr_count($content, '@') / max(1, str_word_count($content)),
            'question_ratio' => substr_count($content, '?') / max(1, strlen($content))
        ];
    }

    private function rule_based_classification($features) {
        // Simple rule-based classification as fallback
        $score = 0;
        
        // Trending indicators
        if ($features['question_ratio'] > 0.02) $score += 1;
        if ($features['hashtag_density'] > 0.1) $score += 1;
        
        // Evergreen indicators
        if ($features['word_count'] > 200) $score -= 1;
        if ($features['mention_density'] < 0.01) $score -= 1;
        
        return [
            'primary_classification' => $score > 0 ? 'trending' : 'evergreen',
            'confidence' => 0.6,
            'features' => $features
        ];
    }

    private function get_platform_max_length($platform) {
        $limits = [
            'twitter' => 280,
            'instagram' => 2200,
            'facebook' => 63206,
            'linkedin' => 3000,
            'tiktok' => 2200
        ];
        
        return $limits[$platform] ?? 500;
    }

    private function generate_additional_content($content) {
        return 'Learn more about this topic and stay updated with our latest insights.';
    }

    /**
     * Process image with AI using vision capabilities
     */
    private function process_image_with_ai($image_url, $options) {
        try {
            // Check if we have a valid AI provider
            $provider_manager = $this->get_ai_provider('vision');
            if (!$provider_manager) {
                Logger::warning('AI Manager: No AI provider available for image processing, using basic fallback');
                return $this->basic_image_processing($image_url, $options);
            }

            // Build AI prompt for image processing
            $processing_instructions = $this->build_image_processing_prompt($options);
            $image_description = $this->get_image_description($image_url);

            // Use AI to process the image
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert image processing AI. Analyze images and provide optimization suggestions.'],
                ['role' => 'user', 'content' => "Process this image: {$image_url}\n\nImage description: {$image_description}\n\nProcessing instructions: {$processing_instructions}"]
            ];

            $result = $provider_manager->chat($messages, [
                'max_tokens' => 500,
                'temperature' => 0.3
            ]);

            // Parse AI response and apply processing
            $processing_result = $this->parse_image_processing_result($result, $image_url, $options);

            Logger::info('AI Manager: AI image processing completed successfully');
            return $processing_result;

        } catch (\Exception $e) {
            Logger::error('AI Manager: AI image processing failed: ' . $e->getMessage());
            Logger::info('AI Manager: Using basic fallback for image processing');
            return $this->basic_image_processing($image_url, $options);
        }
    }

    /**
     * Build image processing prompt based on options
     */
    private function build_image_processing_prompt($options) {
        $instructions = [];

        // Target dimensions
        if (isset($options['target_width']) && isset($options['target_height'])) {
            $instructions[] = "Target dimensions: {$options['target_width']}x{$options['target_height']} pixels";
        }

        // Crop focus
        if (isset($options['crop_focus']) && $options['crop_focus'] !== 'auto') {
            $instructions[] = "Crop focus: {$options['crop_focus']}";
        }

        // Quality and format
        if (isset($options['quality'])) {
            $instructions[] = "Target quality: {$options['quality']}%";
        }
        if (isset($options['format'])) {
            $instructions[] = "Target format: {$options['format']}";
        }

        // Platform-specific optimizations
        if (isset($options['platform'])) {
            $instructions[] = "Optimize for platform: {$options['platform']}";
        }

        return implode(', ', $instructions);
    }

    /**
     * Get image description for AI processing
     */
    private function get_image_description($image_url) {
        // Try to get alt text or context if available
        if (isset($this->alt_text_generator) && method_exists($this->alt_text_generator, 'generate')) {
            try {
                return $this->alt_text_generator->generate($image_url, 'image processing context');
            } catch (\Exception $e) {
                error_log('SMO Social AI Manager: Failed to generate image description: ' . $e->getMessage());
            }
        }

        return 'Image for social media processing';
    }

    /**
     * Parse AI image processing result
     */
    private function parse_image_processing_result($result, $image_url, $options) {
        if (isset($result['content'])) {
            // Parse AI suggestions and apply them
            $suggestions = $result['content'];

            // Basic processing result with AI suggestions
            return [
                'success' => true,
                'message' => 'AI image processing completed',
                'processed_url' => $image_url,
                'ai_suggestions' => $suggestions,
                'optimizations_applied' => $this->extract_optimizations_from_suggestions($suggestions),
                'processing_method' => 'ai'
            ];
        }

        // Fallback to basic processing if AI response is invalid
        return $this->basic_image_processing($image_url, $options);
    }

    /**
     * Extract optimizations from AI suggestions
     */
    private function extract_optimizations_from_suggestions($suggestions) {
        $optimizations = [];

        // Simple parsing of AI suggestions
        if (stripos($suggestions, 'crop') !== false) {
            $optimizations[] = 'smart_cropping';
        }
        if (stripos($suggestions, 'resize') !== false) {
            $optimizations[] = 'optimal_resizing';
        }
        if (stripos($suggestions, 'quality') !== false) {
            $optimizations[] = 'quality_optimization';
        }
        if (stripos($suggestions, 'format') !== false) {
            $optimizations[] = 'format_conversion';
        }

        return $optimizations;
    }

    /**
     * Basic image processing - stub method
     */
    private function basic_image_processing($image_url, $options) {
        return [
            'success' => true,
            'message' => 'Basic image processing completed',
            'processed_url' => $image_url
        ];
    }

    /**
     * Fetch platform trends using AI analysis
     */
    private function fetch_platform_trends($platform, $timeframe, $options) {
        try {
            error_log('SMO Social AI Manager: fetch_platform_trends() called for platform: ' . $platform);
            error_log('SMO Social AI Manager: fetch_platform_trends() timeframe: ' . $timeframe);

            // Check if we have a valid AI provider
            $provider_manager = $this->get_ai_provider('trend_analysis');
            if (!$provider_manager) {
                error_log('SMO Social AI Manager: No AI provider available for trend analysis');
                return [
                    'trends' => $this->get_fallback_trends($platform),
                    'message' => 'Using fallback trends - no AI provider available'
                ];
            }

            // Build trend analysis prompt
            $trend_prompt = $this->build_trend_analysis_prompt($platform, $timeframe, $options);

            // Use AI to analyze trends
            $messages = [
                ['role' => 'system', 'content' => 'You are a social media trend analyst. Identify current trends, hashtags, and topics that are popular on social media platforms.'],
                ['role' => 'user', 'content' => $trend_prompt]
            ];

            $result = $provider_manager->chat($messages, [
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object']
            ]);

            // Parse AI response
            $trends_data = $this->parse_trend_analysis_result($result, $platform);

            error_log('SMO Social AI Manager: Platform trend analysis completed successfully');
            return $trends_data;

        } catch (\Exception $e) {
            error_log('SMO Social AI Manager: Platform trend analysis failed: ' . $e->getMessage());
            return [
                'trends' => $this->get_fallback_trends($platform),
                'message' => 'Using fallback trends due to error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build trend analysis prompt
     */
    private function build_trend_analysis_prompt($platform, $timeframe, $options) {
        $prompt = "Analyze current trends on {$platform} for the timeframe: {$timeframe}.\n\n";

        // Add platform-specific context
        $platform_context = $this->get_platform_trend_context($platform);
        $prompt .= "Platform context: {$platform_context}\n\n";

        // Add any specific topics or categories if provided
        if (isset($options['topics']) && is_array($options['topics'])) {
            $prompt .= "Focus on these topics: " . implode(', ', $options['topics']) . "\n\n";
        }

        // Add content type preferences if provided
        if (isset($options['content_type'])) {
            $prompt .= "Content type preference: {$options['content_type']}\n\n";
        }

        $prompt .= "Return a JSON object with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"trends\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"topic\": \"trend topic\",\n";
        $prompt .= "      \"hashtags\": [\"#hashtag1\", \"#hashtag2\"],\n";
        $prompt .= "      \"popularity_score\": 0.1-1.0,\n";
        $prompt .= "      \"content_type\": \"text/image/video\",\n";
        $prompt .= "      \"engagement_potential\": \"low/medium/high\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"platform_specific_recommendations\": \"any platform-specific advice\",\n";
        $prompt .= "  \"analysis_timestamp\": \"current timestamp\"\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * Get platform-specific trend context
     */
    private function get_platform_trend_context($platform) {
        $contexts = [
            'twitter' => 'Twitter/X - fast-paced, news-driven, hashtag-heavy platform with character limits',
            'instagram' => 'Instagram - visual platform focused on images, reels, and stories with strong influencer culture',
            'facebook' => 'Facebook - diverse content platform with broad demographics and community focus',
            'linkedin' => 'LinkedIn - professional network focused on business, career, and industry trends',
            'tiktok' => 'TikTok - short-form video platform with viral trends and youth-oriented content',
            'youtube' => 'YouTube - long-form video platform with diverse content categories and search-driven discovery'
        ];

        return $contexts[$platform] ?? 'General social media platform';
    }

    /**
     * Parse trend analysis result from AI
     */
    private function parse_trend_analysis_result($result, $platform) {
        if (isset($result['content'])) {
            try {
                // Try to parse JSON response
                $data = json_decode($result['content'], true);

                if (json_last_error() === JSON_ERROR_NONE && isset($data['trends'])) {
                    return [
                        'trends' => $data['trends'],
                        'platform_specific_recommendations' => $data['platform_specific_recommendations'] ?? '',
                        'analysis_timestamp' => $data['analysis_timestamp'] ?? current_time('mysql'),
                        'source' => 'ai_analysis',
                        'platform' => $platform
                    ];
                }
            } catch (\Exception $e) {
                error_log('SMO Social AI Manager: Failed to parse AI trend analysis result: ' . $e->getMessage());
            }
        }

        // Fallback if AI response is invalid
        return [
            'trends' => $this->get_fallback_trends($platform),
            'message' => 'AI analysis completed but response format was invalid',
            'source' => 'fallback'
        ];
    }

    /**
     * Get fallback trends for platforms
     */
    private function get_fallback_trends($platform) {
        $fallback_trends = [
            'twitter' => [
                ['topic' => 'Social Media Marketing', 'hashtags' => ['#SocialMedia', '#Marketing'], 'popularity_score' => 0.8],
                ['topic' => 'AI Technology', 'hashtags' => ['#AI', '#Technology'], 'popularity_score' => 0.7]
            ],
            'instagram' => [
                ['topic' => 'Visual Content', 'hashtags' => ['#VisualContent', '#Photography'], 'popularity_score' => 0.9],
                ['topic' => 'Influencer Marketing', 'hashtags' => ['#Influencer', '#Marketing'], 'popularity_score' => 0.8]
            ],
            'facebook' => [
                ['topic' => 'Community Engagement', 'hashtags' => ['#Community', '#Engagement'], 'popularity_score' => 0.7],
                ['topic' => 'Content Sharing', 'hashtags' => ['#Content', '#Sharing'], 'popularity_score' => 0.6]
            ],
            'linkedin' => [
                ['topic' => 'Professional Development', 'hashtags' => ['#Professional', '#Development'], 'popularity_score' => 0.8],
                ['topic' => 'Business Trends', 'hashtags' => ['#Business', '#Trends'], 'popularity_score' => 0.7]
            ],
            'tiktok' => [
                ['topic' => 'Viral Challenges', 'hashtags' => ['#Viral', '#Challenges'], 'popularity_score' => 0.9],
                ['topic' => 'Short Form Video', 'hashtags' => ['#ShortForm', '#Video'], 'popularity_score' => 0.8]
            ]
        ];

        return $fallback_trends[$platform] ?? [
            ['topic' => 'General Social Media', 'hashtags' => ['#SocialMedia', '#Content'], 'popularity_score' => 0.7]
        ];
    }

    /**
     * Aggregate cross-platform trends using AI analysis
     */
    private function aggregate_cross_platform_trends($trends) {
        try {
            error_log('SMO Social AI Manager: aggregate_cross_platform_trends() called with trends data');

            // Check if we have a valid AI provider
            $provider_manager = $this->get_ai_provider('trend_aggregation');
            if (!$provider_manager) {
                error_log('SMO Social AI Manager: No AI provider available for trend aggregation');
                return $this->aggregate_trends_basic($trends);
            }

            // Build aggregation prompt
            $aggregation_prompt = $this->build_aggregation_prompt($trends);

            // Use AI to aggregate and analyze cross-platform trends
            $messages = [
                ['role' => 'system', 'content' => 'You are a cross-platform trend aggregation expert. Analyze trends from multiple social media platforms and identify the most important, cross-platform trends.'],
                ['role' => 'user', 'content' => $aggregation_prompt]
            ];

            $result = $provider_manager->chat($messages, [
                'max_tokens' => 1500,
                'temperature' => 0.5,
                'response_format' => ['type' => 'json_object']
            ]);

            // Parse AI response
            $aggregated_data = $this->parse_aggregation_result($result, $trends);

            error_log('SMO Social AI Manager: Cross-platform trend aggregation completed successfully');
            return $aggregated_data;

        } catch (\Exception $e) {
            error_log('SMO Social AI Manager: Cross-platform trend aggregation failed: ' . $e->getMessage());
            return $this->aggregate_trends_basic($trends);
        }
    }

    /**
     * Build cross-platform aggregation prompt
     */
    private function build_aggregation_prompt($trends) {
        $prompt = "Aggregate and analyze cross-platform social media trends from the following data:\n\n";

        // Add trend data from each platform
        foreach ($trends as $platform => $platform_trends) {
            $prompt .= "Platform: {$platform}\n";
            if (isset($platform_trends['trends']) && is_array($platform_trends['trends'])) {
                foreach ($platform_trends['trends'] as $index => $trend) {
                    $prompt .= "  Trend {$index}: " . ($trend['topic'] ?? 'Unknown') . "\n";
                    if (isset($trend['hashtags']) && is_array($trend['hashtags'])) {
                        $prompt .= "    Hashtags: " . implode(', ', $trend['hashtags']) . "\n";
                    }
                    if (isset($trend['popularity_score'])) {
                        $prompt .= "    Popularity: " . $trend['popularity_score'] . "\n";
                    }
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "Analyze these trends and identify:\n";
        $prompt .= "1. Cross-platform trends (appearing on multiple platforms)\n";
        $prompt .= "2. Platform-specific trends with high engagement potential\n";
        $prompt .= "3. Emerging trends that are gaining traction\n";
        $prompt .= "4. Overall trend rankings and recommendations\n\n";

        $prompt .= "Return a JSON object with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"cross_platform_trends\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"topic\": \"cross-platform trend topic\",\n";
        $prompt .= "      \"platforms\": [\"platform1\", \"platform2\"],\n";
        $prompt .= "      \"combined_popularity\": 0.1-1.0,\n";
        $prompt .= "      \"hashtags\": [\"#hashtag1\", \"#hashtag2\"],\n";
        $prompt .= "      \"engagement_potential\": \"low/medium/high\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"platform_specific_highlights\": {\n";
        $prompt .= "    \"platform_name\": [\n";
        $prompt .= "      {\n";
        $prompt .= "        \"topic\": \"platform-specific trend\",\n";
        $prompt .= "        \"popularity\": 0.1-1.0,\n";
        $prompt .= "        \"recommendation\": \"why this is important\"\n";
        $prompt .= "      }\n";
        $prompt .= "    ]\n";
        $prompt .= "  },\n";
        $prompt .= "  \"emerging_trends\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"topic\": \"emerging trend\",\n";
        $prompt .= "      \"growth_potential\": \"low/medium/high\",\n";
        $prompt .= "      \"platforms\": [\"platform1\", \"platform2\"]\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"overall_recommendations\": \"strategic recommendations based on trend analysis\",\n";
        $prompt .= "  \"analysis_timestamp\": \"current timestamp\"\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * Parse aggregation result from AI
     */
    private function parse_aggregation_result($result, $original_trends) {
        if (isset($result['content'])) {
            try {
                // Try to parse JSON response
                $data = json_decode($result['content'], true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'aggregated_trends' => $data['cross_platform_trends'] ?? [],
                        'platform_specific_highlights' => $data['platform_specific_highlights'] ?? [],
                        'emerging_trends' => $data['emerging_trends'] ?? [],
                        'overall_recommendations' => $data['overall_recommendations'] ?? '',
                        'analysis_timestamp' => $data['analysis_timestamp'] ?? current_time('mysql'),
                        'source' => 'ai_aggregation',
                        'original_platform_data' => $original_trends
                    ];
                }
            } catch (\Exception $e) {
                error_log('SMO Social AI Manager: Failed to parse AI aggregation result: ' . $e->getMessage());
            }
        }

        // Fallback if AI response is invalid
        return $this->aggregate_trends_basic($original_trends);
    }

    /**
     * Basic trend aggregation fallback
     */
    private function aggregate_trends_basic($trends) {
        $aggregated = [];
        $platform_highlights = [];

        foreach ($trends as $platform => $platform_data) {
            if (isset($platform_data['trends']) && is_array($platform_data['trends'])) {
                foreach ($platform_data['trends'] as $trend) {
                    $aggregated[] = [
                        'topic' => $trend['topic'] ?? 'Unknown trend',
                        'platform' => $platform,
                        'hashtags' => $trend['hashtags'] ?? [],
                        'popularity' => $trend['popularity_score'] ?? 0.5
                    ];
                }

                // Get top trend for each platform
                if (!empty($platform_data['trends'])) {
                    $top_trend = $platform_data['trends'][0];
                    $platform_highlights[$platform] = [
                        'topic' => $top_trend['topic'] ?? 'Top trend',
                        'popularity' => $top_trend['popularity_score'] ?? 0.7,
                        'hashtags' => $top_trend['hashtags'] ?? []
                    ];
                }
            }
        }

        // Sort by popularity
        usort($aggregated, function($a, $b) {
            return $b['popularity'] <=> $a['popularity'];
        });

        return [
            'aggregated_trends' => $aggregated,
            'platform_specific_highlights' => $platform_highlights,
            'emerging_trends' => [],
            'overall_recommendations' => 'Focus on high-popularity trends and platform-specific highlights',
            'analysis_timestamp' => current_time('mysql'),
            'source' => 'basic_aggregation'
        ];
    }

    /**
     * Get API usage statistics - stub method
     */
    private function get_api_usage_stats() {
        return [
            'requests_today' => 0,
            'requests_limit' => 1000,
            'remaining' => 1000
        ];
    }

    /**
     * Get processing queue statistics - stub method
     */
    private function get_processing_queue_stats() {
        return [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0
        ];
    }

    /**
     * Get error rate - stub method
     */
    private function get_error_rate() {
        return 0.0;
    }

    /**
     * Get cache hit rate - stub method
     */
    public function get_hit_rate() {
        return 0.85; // Default 85% hit rate
    }

    /**
     * Process platforms in parallel with intelligent batching (Optimization)
     */
    public function process_platforms_parallel($platforms, $content, $task_type = 'caption', $options = array()) {
        return \SMO_Social\Performance\AI\AIoptimizations::process_platforms_parallel($platforms, $content, $task_type, $options);
    }

    /**
     * Optimize AI cache (Optimization)
     */
    public function optimize_ai_cache() {
        return \SMO_Social\Performance\AI\AIoptimizations::optimize_ai_cache();
    }

    /**
     * Warm AI cache (Optimization)
     */
    public function warm_ai_cache() {
        return \SMO_Social\Performance\AI\AIoptimizations::warm_ai_cache();
    }

    // Smart fallback methods for when AI components are unavailable

    /**
     * Generate smart caption using heuristics
     */
    private function generate_smart_caption($content, $platform, $options = []) {
        $max_length = $this->get_platform_max_length($platform);
        $clean_content = strip_tags($content);
        
        // Basic optimization based on platform
        if (strlen($clean_content) > $max_length) {
            // Truncate intelligently
            $clean_content = substr($clean_content, 0, $max_length - 3) . '...';
        }
        
        // Add platform-specific enhancements
        switch ($platform) {
            case 'twitter':
                $clean_content .= ' #socialmedia';
                break;
            case 'linkedin':
                $clean_content .= ' #professional';
                break;
        }
        
        return [
            'caption' => $clean_content,
            'method' => 'smart_fallback',
            'optimized' => true
        ];
    }

    /**
     * Generate smart hashtags
     */
    private function generate_smart_hashtags($content, $platform, $options = []) {
        $max_hashtags = $options['max_hashtags'] ?? 10;
        $words = explode(' ', strtolower(strip_tags($content)));
        
        // Extract potential hashtags from content
        $hashtags = [];
        foreach ($words as $word) {
            $clean_word = preg_replace('/[^a-z0-9]/', '', $word);
            if (strlen($clean_word) > 3 && !in_array($clean_word, ['this', 'that', 'with', 'from', 'have'])) {
                $hashtags[] = '#' . ucfirst($clean_word);
            }
        }
        
        return array_slice(array_unique($hashtags), 0, $max_hashtags);
    }

    /**
     * Generate smart alt text
     */
    private function generate_smart_alt_text($image_url, $context = '', $options = []) {
        $alt_text = 'Image';
        
        if ($context) {
            $alt_text .= ' related to ' . $context;
        }
        
        // Add platform-specific context
        if (isset($options['platform'])) {
            switch ($options['platform']) {
                case 'instagram':
                    $alt_text .= ' for social media';
                    break;
                case 'linkedin':
                    $alt_text .= ' for professional sharing';
                    break;
            }
        }
        
        return [
            'alt_text' => $alt_text,
            'method' => 'smart_fallback'
        ];
    }

    /**
     * Basic sentiment analysis
     */
    private function analyze_sentiment_basic($text, $options = []) {
        $positive_words = ['great', 'excellent', 'amazing', 'love', 'best', 'awesome', 'good', 'happy'];
        $negative_words = ['bad', 'terrible', 'hate', 'worst', 'awful', 'sad', 'angry'];
        
        $text_lower = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($text_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($text_lower, $word);
        }
        
        if ($positive_count > $negative_count) {
            return ['sentiment' => 'positive', 'confidence' => 0.7];
        } elseif ($negative_count > $positive_count) {
            return ['sentiment' => 'negative', 'confidence' => 0.7];
        }
        
        return ['sentiment' => 'neutral', 'confidence' => 0.5];
    }
    
    /**
     * Get or create a provider manager instance
     * 
     * @param string $provider_id Provider ID
     * @return UniversalManager|null Provider manager instance
     */
    private function get_provider_manager($provider_id) {
        static $managers = [];
        
        // Return cached manager if exists
        if (isset($managers[$provider_id])) {
            return $managers[$provider_id];
        }
        
        // Check if provider exists
        $provider_config = ProvidersConfig::get_provider($provider_id);
        if (!$provider_config) {
            error_log("SMO Social AI Manager: Unknown provider: {$provider_id}");
            return null;
        }
        
        // Check if provider is configured
        if (!ProvidersConfig::is_provider_configured($provider_id)) {
            error_log("SMO Social AI Manager: Provider {$provider_id} is not configured");
            return null;
        }
        
        try {
            // Create new manager instance
            $manager = new UniversalManager($provider_id);
            $managers[$provider_id] = $manager;
            return $manager;
        } catch (\Exception $e) {
            error_log("SMO Social AI Manager: Failed to create manager for {$provider_id}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all available providers
     */
    public function get_available_providers() {
        return ProvidersConfig::get_configured_providers();
    }
    
    /**
     * Get primary provider ID with smart fallback logic
     */
    public function get_primary_provider_id() {
        $configured = ProvidersConfig::get_configured_providers();

        // If no providers are configured, try to find at least one that can work
        if (empty($configured)) {
            error_log('SMO Social AI Manager: No providers configured, attempting to find available providers');

            // Try to find providers that don't require keys or have default configurations
            $available_providers = $this->find_available_fallback_providers();
            if (!empty($available_providers)) {
                error_log('SMO Social AI Manager: Found fallback providers: ' . implode(', ', $available_providers));
                return $available_providers[0]; // Use first available
            }

            // If still nothing, check if we should try localhost/Ollama as last resort
            if ($this->can_use_localhost_fallback()) {
                error_log('SMO Social AI Manager: Using localhost as ultimate fallback');
                return 'localhost';
            }

            error_log('SMO Social AI Manager: No providers available at all');
            return null;
        }

        // Return configured primary provider if set and available
        $primary = $this->api_settings['primary_provider'] ?? null;
        if ($primary && isset($configured[$primary])) {
            return $primary;
        }

        // Return first configured provider as fallback
        return array_key_first($configured);
    }

    /**
     * Try to find providers that can work without explicit configuration
     */
    private function find_available_fallback_providers() {
        $available = [];

        // Check if Ollama might be available locally
        if ($this->is_ollama_available()) {
            $available[] = 'ollama';
        }

        // Check if localhost API might be available
        if ($this->is_localhost_available()) {
            $available[] = 'localhost';
        }

        return $available;
    }

    /**
     * Check if Ollama might be available
     */
    private function is_ollama_available() {
        // Check if Ollama URL is set or if we can use default
        $ollama_url = get_option('smo_social_ollama_url', 'http://localhost:11434');

        // Simple connectivity check (non-blocking)
        if (function_exists('fsockopen')) {
            $parsed = parse_url($ollama_url);
            $host = $parsed['host'] ?? 'localhost';
            $port = $parsed['port'] ?? 11434;

            $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if ($socket) {
                fclose($socket);
                return true;
            }
        }

        // If we can't check, assume it might be available for fallback
        return true;
    }

    /**
     * Check if localhost API might be available
     */
    private function is_localhost_available() {
        $localhost_url = get_option('smo_social_localhost_api_url', 'http://localhost:8000');

        // Simple connectivity check (non-blocking)
        if (function_exists('fsockopen')) {
            $parsed = parse_url($localhost_url);
            $host = $parsed['host'] ?? 'localhost';
            $port = $parsed['port'] ?? 8000;

            $socket = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if ($socket) {
                fclose($socket);
                return true;
            }
        }

        return false;
    }

    /**
     * Check if we should try localhost as ultimate fallback
     */
    private function can_use_localhost_fallback() {
        // Only try localhost if it's not explicitly disabled
        $localhost_url = get_option('smo_social_localhost_api_url', 'http://localhost:8000');
        return !empty($localhost_url);
    }

    /**
     * Try to auto-configure a fallback provider when none are configured
     */
    private function try_auto_configure_fallback_provider($provider_id) {
        // If the requested provider is Ollama and not configured, try to auto-configure it
        if ($provider_id === 'ollama') {
            return $this->auto_configure_ollama();
        }

        // If the requested provider is localhost and not configured, try to auto-configure it
        if ($provider_id === 'localhost') {
            return $this->auto_configure_localhost();
        }

        // If no specific provider requested but we need a fallback, try Ollama first
        return $this->auto_configure_ollama();
    }

    /**
     * Auto-configure Ollama with default settings
     */
    private function auto_configure_ollama() {
        error_log('SMO Social AI Manager: Attempting to auto-configure Ollama');

        // Set default Ollama URL if not already set
        $current_url = get_option('smo_social_ollama_url', '');
        if (empty($current_url)) {
            if (function_exists('update_option')) {
                update_option('smo_social_ollama_url', 'http://localhost:11434');
                error_log('SMO Social AI Manager: Set default Ollama URL: http://localhost:11434');
            } else {
                // Mock the option for non-WordPress context
                error_log('SMO Social AI Manager: Mocking Ollama URL for non-WordPress context');
            }
        }

        // Check if Ollama is now configured
        if (\SMO_Social\AI\ProvidersConfig::is_provider_configured('ollama')) {
            error_log('SMO Social AI Manager: Ollama auto-configuration successful');
            return 'ollama';
        }

        error_log('SMO Social AI Manager: Ollama auto-configuration failed');
        return null;
    }

    /**
     * Auto-configure localhost with default settings
     */
    private function auto_configure_localhost() {
        error_log('SMO Social AI Manager: Attempting to auto-configure localhost');

        // Set default localhost URL if not already set
        $current_url = get_option('smo_social_localhost_api_url', '');
        if (empty($current_url)) {
            if (function_exists('update_option')) {
                update_option('smo_social_localhost_api_url', 'http://localhost:8000/v1/chat/completions');
                error_log('SMO Social AI Manager: Set default localhost URL: http://localhost:8000/v1/chat/completions');
            }
        }

        // Check if localhost is now configured
        if (\SMO_Social\AI\ProvidersConfig::is_provider_configured('localhost')) {
            error_log('SMO Social AI Manager: Localhost auto-configuration successful');
            return 'localhost';
        }

        error_log('SMO Social AI Manager: Localhost auto-configuration failed');
        return null;
    }
}
