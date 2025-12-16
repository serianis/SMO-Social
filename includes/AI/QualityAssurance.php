<?php
namespace SMO_Social\AI;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * Quality Assurance System
 * Ensures content quality, compliance, and optimization standards
 */
class QualityAssurance {
    private $ai_manager;
    private $cache_manager;
    private $quality_standards = [];

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
        $this->initialize_quality_standards();
    }

    /**
     * Initialize quality standards
     */
    private function initialize_quality_standards() {
        $this->quality_standards = [
            'readability' => [
                'minimum_score' => 0.6,
                'maximum_flesch_kincaid' => 12,
                'preferred_sentence_length' => 20
            ],
            'engagement' => [
                'minimum_engagement_score' => 0.7,
                'required_elements' => ['hook', 'value_proposition', 'call_to_action']
            ],
            'compliance' => [
                'brand_guidelines' => true,
                'platform_specific' => true,
                'legal_compliance' => true
            ]
        ];
    }

    /**
     * Score content quality
     */
    public function score_content_quality($content, $platform = 'general') {
        $cache_key = "quality_score_" . md5($content . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Score the quality of this {$platform} content on a scale of 0-1: \"{$content}\". Evaluate readability, engagement potential, clarity, structure, and platform appropriateness. Return as JSON with structure: {overall_score, readability_score, engagement_score, clarity_score, structure_score, platform_appropriateness, detailed_feedback}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content quality assurance specialist.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("QualityAssurance scoring error: " . $e->getMessage());
            return ['overall_score' => 0.5, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check brand compliance
     */
    public function check_brand_compliance($content, $context = []) {
        $cache_key = "brand_compliance_" . md5($content . serialize($context));
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $brand_guidelines = $context['brand_guidelines'] ?? 'standard brand guidelines';
        $prompt = "Check brand compliance for this content against {$brand_guidelines}: \"{$content}\". Analyze tone consistency, messaging alignment, visual guidelines, and brand values representation. Return as JSON with structure: {compliance_score, compliance_issues: [], brand_alignment_score, tone_consistency, messaging_alignment}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a brand compliance officer.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("QualityAssurance brand compliance error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'compliance_score' => 0.5, 'issues' => []];
        }
    }

    /**
     * Generate accessibility report
     */
    public function generate_accessibility_report($image_url) {
        $cache_key = "accessibility_report_" . md5($image_url);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Generate an accessibility report for this image: {$image_url}. Analyze alt text appropriateness, color contrast, text readability, visual clarity, and screen reader compatibility. Return as JSON with structure: {accessibility_score, alt_text_suggestions, color_contrast_analysis, text_readability, visual_clarity, screen_reader_compatibility, improvement_recommendations}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an accessibility expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("QualityAssurance accessibility report error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'accessibility_score' => 0.5, 'recommendations' => []];
        }
    }

    /**
     * Analyze image content
     */
    public function analyze_image_content($image_url) {
        $cache_key = "image_analysis_" . md5($image_url);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Analyze the content of this image for social media use: {$image_url}. Identify main elements, visual hierarchy, focal points, text content, brand elements, and content appropriateness. Return as JSON with structure: {content_analysis: {main_elements, visual_hierarchy, focal_points, text_content, brand_elements}, appropriateness_score, content_warnings, optimization_suggestions}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a visual content analyst.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("QualityAssurance image analysis error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'analysis' => [], 'appropriateness_score' => 0.5];
        }
    }

    /**
     * Analyze visual sentiment
     */
    public function analyze_visual_sentiment($image_url) {
        $cache_key = "visual_sentiment_" . md5($image_url);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Analyze the visual sentiment and emotional impact of this image: {$image_url}. Assess mood, emotional tone, color psychology, visual impact, and audience emotional response. Return as JSON with structure: {sentiment_analysis: {emotional_tone, mood_score, color_psychology, visual_impact}, audience_response_prediction, emotional_engagement_score}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert in visual sentiment analysis.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("QualityAssurance visual sentiment error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'sentiment' => 'neutral', 'mood_score' => 0.5];
        }
    }

    /**
     * Optimize visual for platform
     */
    public function optimize_visual_for_platform($image_url, $platform) {
        $cache_key = "visual_optimization_" . md5($image_url . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Optimize this visual content for {$platform}: {$image_url}. Recommend format adjustments, size optimization, color adjustments, text overlays, and platform-specific enhancements. Return as JSON with structure: {optimization_recommendations: {format_adjustments, size_optimization, color_adjustments, text_overlays}, platform_specific_requirements, enhancement_suggestions}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a social media visual optimization expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 3600);
            return $result;
        } catch (\Exception $e) {
            error_log("QualityAssurance visual optimization error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'recommendations' => []];
        }
    }

    /**
     * Validate content structure
     */
    public function validate_content_structure($content, $content_type = 'post') {
        $cache_key = "content_validation_" . md5($content . $content_type);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Validate the structure of this {$content_type} content: \"{$content}\". Check for proper introduction, body structure, conclusion, logical flow, and completeness. Return as JSON with structure: {structure_score, introduction_quality, body_structure, conclusion_quality, logical_flow, completeness_check, structural_improvements}";

        try {
            $messages = [
                ['role' => 'system', 'content' => 'You are a content editor.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]);

            $this->cache_manager->set($cache_key, $result, 1800);
            return $result;
        } catch (\Exception $e) {
            error_log("QualityAssurance content validation error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'structure_score' => 0.5];
        }
    }

    /**
     * Check content compliance
     */
    public function check_content_compliance($content, $platform = 'general') {
        $cache_key = "content_compliance_" . md5($content . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $prompt = "Check content compliance for {$platform}: \"{$content}\". Analyze for policy violations, inappropriate content, copyright issues, and platform-specific restrictions. Return as JSON with structure: {compliance_score, policy_violations: [], content_warnings: [], copyright_issues: [], platform_restrictions_compliance}";

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
            error_log("QualityAssurance content compliance error: " . $e->getMessage());
            return ['error' => $e->getMessage(), 'compliance_score' => 0.8, 'violations' => []];
        }
    }

    /**
     * Get quality metrics
     */
    public function get_quality_metrics($content_type = 'all') {
        return [
            'standards' => $this->quality_standards,
            'compliance_thresholds' => [
                'minimum_quality_score' => 0.7,
                'minimum_compliance_score' => 0.8,
                'maximum_readability_flesch' => 12
            ],
            'content_type_requirements' => [
                'post' => ['minimum_length' => 50, 'maximum_length' => 280],
                'article' => ['minimum_length' => 300, 'maximum_length' => 2000],
                'caption' => ['minimum_length' => 10, 'maximum_length' => 150]
            ]
        ];
    }
}
