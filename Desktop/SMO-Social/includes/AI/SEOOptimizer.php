<?php
namespace SMO_Social\AI;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * SEO Optimization Manager
 * Handles SEO analysis and optimization for social media content
 */
class SEOOptimizer {
    private $ai_manager;
    private $cache_manager;
    private $seo_cache = [];

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Optimize keyword density for content
     */
    public function optimize_keyword_density($topic, $target_keywords, $content_type) {
        $cache_key = "keyword_optimization_" . md5($topic . serialize($target_keywords) . $content_type);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $keywords_list = implode(', ', $target_keywords);
        $prompt = "Optimize keyword density for {$content_type} about \"{$topic}\" with target keywords: {$keywords_list}. Analyze optimal keyword placement, density percentages, and semantic variations. Return as JSON with structure: {keyword_analysis: {primary_keywords, secondary_keywords, optimal_density, placement_strategy}, content_optimization: {intro_optimization, body_optimization, conclusion_optimization}, semantic_variations}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an SEO specialist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer keyword density error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'optimization' => []];
        }
    }

    /**
     * Generate meta data for content
     */
    public function generate_meta_data($topic, $target_keywords, $platform) {
        $cache_key = "meta_data_" . md5($topic . serialize($target_keywords) . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $keywords_list = implode(', ', $target_keywords);
        $prompt = "Generate SEO meta data for {$platform} content about \"{$topic}\" with keywords: {$keywords_list}. Include meta title, meta description, meta keywords, OpenGraph tags, and Twitter Card tags. Consider platform character limits and best practices. Return as JSON with structure: {meta_title, meta_description, meta_keywords, opengraph_tags: {og_title, og_description, og_image}, twitter_tags: {twitter_title, twitter_description, twitter_image}, schema_markup}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an SEO expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer meta data error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'meta_data' => []];
        }
    }

    /**
     * Optimize content structure for SEO
     */
    public function optimize_content_structure($topic, $content_type) {
        $cache_key = "content_structure_" . md5($topic . $content_type);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Optimize content structure for {$content_type} about \"{$topic}\" for maximum SEO performance. Include optimal heading hierarchy, paragraph structure, keyword placement, and readability improvements. Return as JSON with structure: {structure_optimization: {heading_hierarchy, paragraph_structure, keyword_placement, readability_improvements}, content_outline, seo_elements}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content structure expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer content structure error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'optimization' => []];
        }
    }

    /**
     * Suggest internal links
     */
    public function suggest_internal_links($topic) {
        $cache_key = "internal_links_" . md5($topic);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Suggest internal linking opportunities for content about \"{$topic}\". Recommend existing content that should be linked to, anchor text suggestions, and link placement strategy. Return as JSON with structure: {internal_links: [{target_page, anchor_text, link_position, context}, suggested_anchor_texts, linking_strategy}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an SEO strategist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer internal links error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'links' => []];
        }
    }

    /**
     * Analyze readability
     */
    public function analyze_readability($content) {
        $cache_key = "readability_analysis_" . md5($content);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Analyze the readability of this content and provide suggestions for improvement: \"{$content}\". Consider Flesch-Kincaid score, sentence length, vocabulary complexity, and engagement factors. Return as JSON with structure: {readability_score, grade_level, suggestions: {sentence_improvements, vocabulary_suggestions, structure_improvements}, readability_breakdown}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a readability expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer readability analysis error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'readability' => []];
        }
    }

    /**
     * Optimize semantic content
     */
    public function optimize_semantic_content($topic, $target_keywords) {
        $cache_key = "semantic_optimization_" . md5($topic . serialize($target_keywords));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $keywords_list = implode(', ', $target_keywords);
        $prompt = "Optimize semantic content for \"{$topic}\" with target keywords: {$keywords_list}. Suggest related terms, LSI keywords, entity relationships, and topical authority signals. Return as JSON with structure: {semantic_keywords: {lsi_keywords, related_terms, entity_relationships}, topical_authority: {content_clusters, entity_coverage, semantic_density}, semantic_optimization_strategy}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a semantic SEO expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer semantic optimization error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'semantic_optimization' => []];
        }
    }

    /**
     * Define success metrics
     */
    public function define_success_metrics($objectives) {
        $cache_key = "success_metrics_" . md5(serialize($objectives));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Define success metrics and KPIs for social media objectives: " . implode(', ', $objectives) . ". Include quantitative and qualitative metrics, measurement methodologies, and benchmark targets. Return as JSON with structure: {success_metrics: [{metric, type, measurement_method, target_value, benchmark}, kpi_framework, measurement_strategy}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a performance analyst.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer success metrics error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'metrics' => []];
        }
    }

    /**
     * Create optimization plan
     */
    public function create_optimization_plan($platforms) {
        $cache_key = "optimization_plan_" . md5(serialize($platforms));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $platform_list = implode(', ', $platforms);
        $prompt = "Create a comprehensive optimization plan for social media content across these platforms: {$platform_list}. Include platform-specific strategies, content optimization techniques, timing optimizations, and performance improvement tactics. Return as JSON with structure: {optimization_plan: {platform_strategies: [{platform, optimization_focus, tactics, tools}, cross_platform_optimizations, implementation_timeline}, performance_improvements}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a social media optimization strategist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("SEOOptimizer optimization plan error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'plan' => []];
        }
    }
}
