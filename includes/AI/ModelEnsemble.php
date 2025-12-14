<?php
namespace SMO_Social\AI;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * AI Model Ensemble Manager
 * Manages multiple AI models and selects optimal ones for different tasks
 */
class ModelEnsemble {
    private $ai_manager;
    private $cache_manager;
    private $model_registry = [];
    private $model_performance = [];

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
        $this->initialize_model_registry();
    }

    /**
     * Initialize available models
     */
    private function initialize_model_registry() {
        $this->model_registry = [
            'text_generation' => [
                'gpt-4' => ['confidence' => 0.95, 'speed' => 0.7, 'cost' => 0.9, 'creativity' => 0.9],
                'gpt-3.5-turbo' => ['confidence' => 0.85, 'speed' => 0.9, 'cost' => 0.7, 'creativity' => 0.8],
                'claude-3-sonnet' => ['confidence' => 0.92, 'speed' => 0.8, 'cost' => 0.8, 'creativity' => 0.85],
                'llama2' => ['confidence' => 0.75, 'speed' => 0.95, 'cost' => 0.95, 'creativity' => 0.7],
                'mistral' => ['confidence' => 0.8, 'speed' => 0.9, 'cost' => 0.85, 'creativity' => 0.75]
            ],
            'analysis' => [
                'gpt-4' => ['confidence' => 0.96, 'analytical' => 0.98, 'detail' => 0.95],
                'claude-3-opus' => ['confidence' => 0.94, 'analytical' => 0.96, 'detail' => 0.97],
                'gpt-3.5-turbo' => ['confidence' => 0.82, 'analytical' => 0.85, 'detail' => 0.8]
            ],
            'optimization' => [
                'gpt-4' => ['confidence' => 0.94, 'strategic' => 0.95, 'practical' => 0.9],
                'claude-3-sonnet' => ['confidence' => 0.9, 'strategic' => 0.92, 'practical' => 0.93],
                'gpt-3.5-turbo' => ['confidence' => 0.8, 'strategic' => 0.82, 'practical' => 0.85]
            ]
        ];
    }

    /**
     * Get optimal models for a specific task
     */
    public function get_optimal_models($task_type) {
        $cache_key = "optimal_models_" . md5($task_type);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        if (isset($this->model_registry[$task_type])) {
            $models = $this->model_registry[$task_type];
            
            // Sort by confidence score
            uasort($models, function($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            
            $result = array_keys($models);
            
            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        }
        
        return ['gpt-3.5-turbo']; // Default fallback
    }

    /**
     * Get model confidence score
     */
    public function get_model_confidence($model) {
        foreach ($this->model_registry as $task_type => $models) {
            if (isset($models[$model])) {
                return $models[$model]['confidence'] ?? 0.8;
            }
        }
        return 0.8; // Default confidence
    }

    /**
     * Get model capabilities
     */
    public function get_model_capabilities($model) {
        foreach ($this->model_registry as $task_type => $models) {
            if (isset($models[$model])) {
                return $models[$model];
            }
        }
        return ['confidence' => 0.8, 'speed' => 0.8, 'cost' => 0.8];
    }

    /**
     * Generate optimization suggestions
     */
    public function generate_optimization_suggestions($content, $options) {
        $cache_key = "optimization_suggestions_" . md5($content . serialize($options));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $platform = $options['platform'] ?? 'general';
        $content_type = $options['content_type'] ?? 'post';
        
        $prompt = "Generate optimization suggestions for this {$content_type} content on {$platform}: \"{$content}\". Focus on engagement improvements, platform optimization, content structure, and audience appeal. Return as JSON with structure: {optimization_suggestions: [{category, suggestion, priority, implementation_difficulty, expected_impact}], suggestion_analysis, optimization_priority}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert content strategist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("ModelEnsemble optimization suggestions error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'suggestions' => []];
        }
    }

    /**
     * Apply platform optimizations
     */
    public function apply_platform_optimizations($content, $platform) {
        $cache_key = "platform_optimizations_" . md5($content . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Apply platform-specific optimizations to this content for {$platform}: \"{$content}\". Consider platform algorithms, formatting requirements, engagement tactics, and content best practices. Return as JSON with structure: {optimized_content, platform_adjustments: {formatting_changes, algorithm_optimizations, engagement_tactics}, optimization_explanation}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a social media optimization expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("ModelEnsemble platform optimizations error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'optimized_content' => $content];
        }
    }

    /**
     * Apply trending optimizations
     */
    public function apply_trending_optimizations($content, $platform) {
        $cache_key = "trending_optimizations_" . md5($content . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Apply trending topic optimizations to make this content more relevant for {$platform}: \"{$content}\". Incorporate trending themes, hashtags, topics, and cultural references. Return as JSON with structure: {trend_optimized_content, trending_elements: {hashtags, topics, cultural_references}, trend_alignment_score}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a trend analyst and content creator.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.8,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("ModelEnsemble trending optimizations error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'optimized_content' => $content];
        }
    }

    /**
     * Adapt content for platform
     */
    public function adapt_content_for_platform($source_content, $source_platform, $target_platform) {
        $cache_key = "content_adaptation_" . md5($source_content . $source_platform . $target_platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Adapt this content from {$source_platform} to {$target_platform}: \"{$source_content}\". Consider platform-specific formatting, character limits, content types, and audience expectations. Return as JSON with structure: {adapted_content, adaptation_changes: {formatting_changes, content_modifications, platform_specific_optimizations}, adaptation_notes}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert in cross-platform content adaptation.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("ModelEnsemble content adaptation error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'adapted_content' => $source_content];
        }
    }

    /**
     * Optimize for platform algorithm
     */
    public function optimize_for_platform_algorithm($content, $platform) {
        $cache_key = "algorithm_optimization_" . md5($content . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Optimize this content for {$platform}'s algorithm: \"{$content}\". Focus on algorithm preferences, engagement signals, content quality indicators, and ranking factors. Return as JSON with structure: {algorithm_optimized_content, algorithm_factors: {engagement_signals, quality_indicators, ranking_factors}, optimization_strategy}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert in social media algorithms.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("ModelEnsemble algorithm optimization error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'optimized_content' => $content];
        }
    }

    /**
     * Optimize cross-platform timing
     */
    public function optimize_cross_platform_timing($source_platform, $target_platform) {
        $cache_key = "cross_platform_timing_" . md5($source_platform . $target_platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Optimize timing strategy for cross-platform content distribution from {$source_platform} to {$target_platform}. Consider audience overlap, platform peak times, content lifecycle, and engagement patterns. Return as JSON with structure: {timing_optimization: {best_posting_times, audience_overlap_analysis, cross_platform_strategy}, timing_recommendations}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a social media timing strategist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("ModelEnsemble cross-platform timing error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'timing_strategy' => []];
        }
    }

    /**
     * Check platform compliance
     */
    public function check_platform_compliance($content, $platform) {
        $cache_key = "platform_compliance_" . md5($content . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Check if this content complies with {$platform}'s guidelines and best practices: \"{$content}\". Analyze formatting, content appropriateness, engagement optimization, and platform-specific requirements. Return as JSON with structure: {compliance_score, compliance_issues: [], compliance_suggestions, platform_guidelines_adherence}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content compliance officer.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("ModelEnsemble platform compliance error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'compliance_score' => 0.5];
        }
    }
}
