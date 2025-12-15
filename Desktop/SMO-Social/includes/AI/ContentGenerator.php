<?php
namespace SMO_Social\AI;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;
use SMO_Social\AI\ErrorHandler;

/**
 * Advanced Content Generator
 * Generates various types of content using AI models
 */
class ContentGenerator {
    private $ai_manager;
    private $cache_manager;
    private $generation_cache = [];

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Generate content with specific model
     */
    public function generate_with_model($brief, $model, $options = []) {
        $cache_key = "content_gen_" . md5($brief . $model . serialize($options));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $platform = $options['platform'] ?? 'general';
        $content_type = $options['content_type'] ?? 'post';
        $tone = $options['tone'] ?? 'professional';
        $length = $options['length'] ?? 'medium';

        $prompt = "Generate {$content_type} content for {$platform} platform with {$tone} tone and {$length} length. Brief: \"{$brief}\". Consider platform-specific requirements, optimal formatting, and engagement strategies. Return as JSON with structure: {content, headline, hashtags, tags, platform_optimizations, engagement_tips}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a professional content creator.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800); // 30 minutes cache
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to generate content with model', [
                'model' => $model,
                'brief_length' => strlen($brief),
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Content generation failed',
                [
                    'model' => $model,
                    'operation' => 'content_generation',
                    'details' => $e->getMessage()
                ],
                ['content' => $brief]
            );
        }
    }

    /**
     * Generate content pillars
     */
    public function generate_content_pillars($business_info, $target_audience) {
        $cache_key = "content_pillars_" . md5(serialize($business_info) . serialize($target_audience));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Generate 4-6 content pillars for a business in {$business_info['industry']} targeting {$target_audience['demographics']}. Consider brand values: " . ($business_info['values'] ?? 'not specified') . ". Return as JSON with structure: {pillars: [{name, description, content_examples, target_outcome}], pillar_strategy}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content strategist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to generate content pillars', [
                'business_industry' => $business_info['industry'] ?? 'unknown',
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Content pillars generation failed',
                [
                    'operation' => 'content_pillars_generation',
                    'details' => $e->getMessage()
                ],
                ['pillars' => []]
            );
        }
    }

    /**
     * Generate content themes
     */
    public function generate_content_themes($business_info, $objectives) {
        $cache_key = "content_themes_" . md5(serialize($business_info) . serialize($objectives));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Generate monthly content themes for {$business_info['industry']} business with objectives: " . implode(', ', $objectives) . ". Consider seasonal trends, industry events, and audience interests. Return as JSON with structure: {themes: [{month, theme, rationale, content_ideas, key_messages}], theme_calendar}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a social media manager.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to generate content themes', [
                'business_industry' => $business_info['industry'] ?? 'unknown',
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Content themes generation failed',
                [
                    'operation' => 'content_themes_generation',
                    'details' => $e->getMessage()
                ],
                ['themes' => []]
            );
        }
    }

    /**
     * Generate content calendar
     */
    public function generate_content_calendar($business_info, $platforms, $objectives) {
        $cache_key = "content_calendar_" . md5(serialize($business_info) . serialize($platforms) . serialize($objectives));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Create a 4-week content calendar for {$business_info['industry']} business targeting platforms: " . implode(', ', $platforms) . " with objectives: " . implode(', ', $objectives) . ". Include content topics, formats, posting dates, and platform-specific optimizations. Return as JSON with structure: {weekly_calendar: [{week, focus_theme, daily_posts: [{day, platform, content_topic, format, headline, content_preview}]}], calendar_strategy}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content calendar planner.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 2000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to generate content calendar', [
                'business_industry' => $business_info['industry'] ?? 'unknown',
                'platforms' => implode(', ', $platforms),
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Content calendar generation failed',
                [
                    'operation' => 'content_calendar_generation',
                    'details' => $e->getMessage()
                ],
                ['calendar' => []]
            );
        }
    }

    /**
     * Generate weekly content structure
     */
    public function create_weekly_content_structure($objectives) {
        $cache_key = "weekly_structure_" . md5(serialize($objectives));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Design a weekly content structure for social media with objectives: " . implode(', ', $objectives) . ". Include optimal posting days, content types, engagement strategies, and platform distribution. Return as JSON with structure: {weekly_structure: {posting_schedule: [{day, platform, content_type, optimal_time}], content_distribution, engagement_strategy}, weekly_rhythm}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a social media strategist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to create weekly content structure', [
                'objectives' => implode(', ', $objectives),
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Weekly content structure creation failed',
                [
                    'operation' => 'weekly_structure_creation',
                    'details' => $e->getMessage()
                ],
                ['structure' => []]
            );
        }
    }

    /**
     * Generate monthly themes
     */
    public function define_monthly_themes($business_info) {
        $cache_key = "monthly_themes_" . md5(serialize($business_info));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Define monthly content themes for {$business_info['industry']} business. Consider seasonal trends, industry events, holidays, and audience behavior patterns. Return as JSON with structure: {monthly_themes: [{month, theme, focus_areas, content_opportunities, key_messages}], theme_overview}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a marketing planner.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to define monthly themes', [
                'business_industry' => $business_info['industry'] ?? 'unknown',
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Monthly themes definition failed',
                [
                    'operation' => 'monthly_themes_definition',
                    'details' => $e->getMessage()
                ],
                ['themes' => []]
            );
        }
    }

    /**
     * Optimize platform distribution
     */
    public function optimize_platform_distribution($platforms) {
        $cache_key = "platform_distribution_" . md5(serialize($platforms));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Optimize content distribution strategy across these platforms: " . implode(', ', $platforms) . ". Consider platform strengths, audience demographics, content format preferences, and engagement patterns. Return as JSON with structure: {distribution_strategy: [{platform, content_focus, optimal_formats, audience_target, posting_frequency}], cross_platform_strategy}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content distribution expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to optimize platform distribution', [
                'platforms' => implode(', ', $platforms),
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Platform distribution optimization failed',
                [
                    'operation' => 'platform_distribution_optimization',
                    'details' => $e->getMessage()
                ],
                ['distribution' => []]
            );
        }
    }

    /**
     * Calculate optimal content mix
     */
    public function calculate_optimal_content_mix($objectives) {
        $cache_key = "content_mix_" . md5(serialize($objectives));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Calculate optimal content mix based on these objectives: " . implode(', ', $objectives) . ". Determine percentages for educational, promotional, entertaining, and community content types. Consider audience preferences and platform algorithms. Return as JSON with structure: {content_mix: {educational_percentage, promotional_percentage, entertaining_percentage, community_percentage}, mix_strategy, content_types_breakdown}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content mix strategist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            ErrorHandler::log_error('ContentGenerator', 'Failed to calculate optimal content mix', [
                'objectives' => implode(', ', $objectives),
                'exception' => $e->getMessage()
            ], ErrorHandler::ERROR_CODE_PROCESSING_FAILED);

            return ErrorHandler::create_error_response(
                ErrorHandler::ERROR_CODE_PROCESSING_FAILED,
                'Optimal content mix calculation failed',
                [
                    'operation' => 'content_mix_calculation',
                    'details' => $e->getMessage()
                ],
                ['mix' => []]
            );
        }
    }
}
