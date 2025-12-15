<?php
namespace SMO_Social\AI;

/**
 * Smart Content Repurposer - Advanced AI-powered content transformation
 * Converts existing content into platform-optimized variations while maintaining core message
 * 
 * Refactored to use UniversalManager through AI\Manager singleton
 */
class SmartContentRepurposer {
    private $ai_manager;
    private $cache_manager;
    
    public function __construct() {
        $this->ai_manager = Manager::getInstance();
        $this->cache_manager = new CacheManager();
        
        $this->schedule_content_analysis();
    }
    
    /**
     * Repurpose content for multiple platforms
     */
    public function repurpose_content($original_content, $target_platforms, $options = []) {
        $cache_key = "content_repurposing_" . md5($original_content . implode(',', $target_platforms));
        $cached = $this->cache_manager->get($cache_key);
        
        if ($cached) {
            return $cached;
        }
        
        try {
            // Use AI Manager to analyze and repurpose content
            $repurposed_content = [];
            
            foreach ($target_platforms as $platform) {
                // Use the AI Manager's repurpose_content method
                $platform_content = $this->ai_manager->repurpose_content(
                    $original_content,
                    [$platform],
                    $options
                );
                
                if (isset($platform_content[$platform])) {
                    $repurposed_content[$platform] = array_merge(
                        $platform_content[$platform],
                        [
                            'quality_score' => $this->assess_quality($platform_content[$platform], $platform),
                            'optimization_level' => $this->calculate_optimization_level($platform_content[$platform], $platform)
                        ]
                    );
                }
            }
            
            $result = [
                'original_content' => $original_content,
                'repurposed_content' => $repurposed_content,
                'transformation_metadata' => [
                    'generated_at' => current_time('mysql'),
                    'transformation_type' => $options['transformation_type'] ?? 'standard',
                    'quality_threshold' => $options['quality_threshold'] ?? 0.7
                ]
            ];
            
            $this->cache_manager->set($cache_key, $result, 7200); // Cache for 2 hours
            
            return $result;
            
        } catch (\Exception $e) {
            error_log('SMO Social SmartContentRepurposer: ' . $e->getMessage());
            return $this->generate_fallback_repurposing($original_content, $target_platforms);
        }
    }
    
    /**
     * Create content series from single piece
     */
    public function create_content_series($original_content, $series_config) {
        try {
            // Analyze content for series potential
            $series_potential = $this->analyze_series_potential($original_content);
            
            if (!$series_potential['has_series_potential']) {
                return [
                    'error' => 'Content not suitable for series',
                    'reason' => 'Content too short or lacks structure for series',
                    'fallback' => $this->generate_fallback_series($original_content, $series_config)
                ];
            }
            
            // Generate series structure using AI
            $series_content = $this->create_series_pieces($original_content, $series_potential, $series_config);
            
            return [
                'series_title' => $series_config['title'] ?? 'Content Series',
                'series_description' => $series_config['description'] ?? 'Multi-part content series',
                'total_pieces' => count($series_content),
                'series_content' => $series_content,
                'publishing_schedule' => $this->generate_publishing_schedule(count($series_content)),
                'cross_promotion_links' => $this->create_cross_promotion_links($series_content)
            ];
            
        } catch (\Exception $e) {
            error_log('SMO Social SmartContentRepurposer series: ' . $e->getMessage());
            return $this->generate_fallback_series($original_content, $series_config);
        }
    }
    
    /**
     * Evergreen content detection and optimization
     */
    public function optimize_evergreen_content($content, $options = []) {
        try {
            // Analyze content for evergreen potential
            $evergreen_analysis = $this->analyze_evergreen_potential($content);
            
            if (!$evergreen_analysis['is_evergreen']) {
                return [
                    'is_evergreen' => false,
                    'reason' => $evergreen_analysis['reason'],
                    'suggestions' => $evergreen_analysis['suggestions']
                ];
            }
            
            // Use AI to optimize for long-term performance
            $optimized_content = $this->optimize_for_evergreen($content, $evergreen_analysis, $options);
            
            return [
                'is_evergreen' => true,
                'original_content' => $content,
                'optimized_content' => $optimized_content,
                'lifecycle_prediction' => $evergreen_analysis['lifecycle_prediction'],
                'refresh_schedule' => $this->calculate_refresh_schedule($optimized_content)
            ];
            
        } catch (\Exception $e) {
            error_log('SMO Social SmartContentRepurposer evergreen: ' . $e->getMessage());
            return $this->generate_fallback_evergreen($content);
        }
    }
    
    /**
     * Trend-based content adaptation
     */
    public function adapt_content_to_trends($content, $trend_data, $options = []) {
        try {
            // Analyze content relevance to current trends
            $trend_relevance = $this->analyze_trend_relevance($content, $trend_data);
            
            // Generate trend-aligned variations using AI
            $trend_adaptations = $this->adapt_to_trends($content, $trend_relevance, $trend_data);
            
            return [
                'original_content' => $content,
                'trend_relevance_score' => $trend_relevance['relevance_score'],
                'trend_adaptations' => $trend_adaptations,
                'trend_alignment_strategy' => $trend_relevance['alignment_strategy'],
                'viral_potential_increase' => $trend_relevance['viral_potential_increase']
            ];
            
        } catch (\Exception $e) {
            error_log('SMO Social SmartContentRepurposer trends: ' . $e->getMessage());
            return $this->generate_fallback_trend_adaptation($content, $trend_data);
        }
    }
    
    /**
     * Calculate optimization level for platform-specific content
     */
    private function calculate_optimization_level($content, $platform) {
        $content_text = is_array($content) && isset($content['content']) ? $content['content'] : $content;
        
        $optimization_factors = [
            'platform_compliance' => $this->check_platform_compliance($content_text, $platform),
            'engagement_optimization' => $this->check_engagement_optimization($content_text, $platform),
            'format_optimization' => $this->check_format_optimization($content, $platform),
            'seo_optimization' => $this->check_seo_optimization($content_text, $platform)
        ];
        
        $total_score = array_sum($optimization_factors);
        $max_score = count($optimization_factors) * 10;
        
        return [
            'overall_score' => round(($total_score / $max_score) * 100, 1),
            'factors' => $optimization_factors,
            'recommendations' => $this->generate_optimization_recommendations($optimization_factors, $platform)
        ];
    }
    
    /**
     * Assess quality of repurposed content
     */
    private function assess_quality($content, $platform) {
        $base_score = 7.0;
        $content_text = is_array($content) && isset($content['content']) ? $content['content'] : $content;
        
        // Check content length appropriateness
        if (strlen($content_text) > 50 && strlen($content_text) < 500) {
            $base_score += 1.0;
        }
        
        // Check for engagement elements
        if (strpos($content_text, '#') !== false) {
            $base_score += 0.5;
        }
        
        if (strpos($content_text, '?') !== false) {
            $base_score += 0.5;
        }
        
        return min(10.0, $base_score);
    }
    
    /**
     * Analyze series potential
     */
    private function analyze_series_potential($content) {
        $word_count = str_word_count(strip_tags($content));
        
        return [
            'has_series_potential' => $word_count > 300,
            'suggested_parts' => max(2, min(5, ceil($word_count / 200))),
            'series_type' => $this->detect_series_type($content)
        ];
    }
    
    /**
     * Detect series type
     */
    private function detect_series_type($content) {
        if (preg_match('/(step|guide|tutorial)/i', $content)) {
            return 'how_to_series';
        } elseif (preg_match('/(list|top|best)/i', $content)) {
            return 'list_series';
        } elseif (preg_match('/(case study|example|story)/i', $content)) {
            return 'story_series';
        }
        return 'general_series';
    }
    
    /**
     * Create series pieces
     */
    private function create_series_pieces($content, $series_potential, $series_config) {
        $num_pieces = $series_potential['suggested_parts'];
        $content_clean = strip_tags($content);
        $piece_length = ceil(strlen($content_clean) / $num_pieces);
        
        $series_content = [];
        for ($i = 0; $i < $num_pieces; $i++) {
            $start = $i * $piece_length;
            $piece_content = substr($content_clean, $start, $piece_length);
            
            $series_content[] = [
                'title' => sprintf('Part %d: %s', $i + 1, $this->generate_piece_title($piece_content)),
                'content' => $piece_content,
                'order' => $i + 1,
                'total_parts' => $num_pieces
            ];
        }
        
        return $series_content;
    }
    
    /**
     * Generate piece title
     */
    private function generate_piece_title($content) {
        $words = explode(' ', $content);
        $title_words = array_slice($words, 0, 5);
        return implode(' ', $title_words) . '...';
    }
    
    /**
     * Generate publishing schedule
     */
    private function generate_publishing_schedule($num_pieces) {
        $schedule = [];
        $base_time = time();
        
        for ($i = 0; $i < $num_pieces; $i++) {
            $schedule[] = [
                'piece' => $i + 1,
                'suggested_date' => date('Y-m-d H:i:s', $base_time + ($i * 86400)) // One day apart
            ];
        }
        
        return $schedule;
    }
    
    /**
     * Analyze evergreen potential
     */
    private function analyze_evergreen_potential($content) {
        $evergreen_keywords = ['how to', 'guide', 'tips', 'strategies', 'best practices', 'tutorial'];
        $time_sensitive_keywords = ['this week', 'today', 'breaking', 'now', '2024', '2025', 'new'];
        
        $content_lower = strtolower($content);
        
        $evergreen_score = 0;
        $time_sensitive_score = 0;
        
        foreach ($evergreen_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $evergreen_score += 20;
            }
        }
        
        foreach ($time_sensitive_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $time_sensitive_score += 25;
            }
        }
        
        $is_evergreen = $evergreen_score > $time_sensitive_score && $evergreen_score > 40;
        
        return [
            'is_evergreen' => $is_evergreen,
            'evergreen_score' => $evergreen_score,
            'time_sensitive_score' => $time_sensitive_score,
            'reason' => $is_evergreen ? 'Content has evergreen qualities' : 'Content appears time-sensitive',
            'suggestions' => $this->generate_evergreen_suggestions($content),
            'lifecycle_prediction' => $is_evergreen ? '12-24 months' : '1-3 months'
        ];
    }
    
    /**
     * Generate evergreen suggestions
     */
    private function generate_evergreen_suggestions($content) {
        return ['Remove time references', 'Focus on timeless principles', 'Add foundational concepts'];
    }
    
    /**
     * Optimize for evergreen
     */
    private function optimize_for_evergreen($content, $evergreen_analysis, $options) {
        // Remove time-sensitive references
        $optimized = preg_replace('/(this week|today|yesterday|tomorrow)/i', '', $content);
        $optimized = preg_replace('/\b(20\d{2})\b/', '', $optimized);
        
        return trim($optimized);
    }
    
    /**
     * Analyze trend relevance
     */
    private function analyze_trend_relevance($content, $trend_data) {
        $content_lower = strtolower($content);
        
        $relevance_score = 0;
        $matching_trends = [];
        
        if (isset($trend_data['trends']) && is_array($trend_data['trends'])) {
            foreach ($trend_data['trends'] as $trend) {
                if (strpos($content_lower, strtolower($trend)) !== false) {
                    $relevance_score += 25;
                    $matching_trends[] = $trend;
                }
            }
        }
        
        return [
            'relevance_score' => min(100, $relevance_score),
            'matching_trends' => $matching_trends,
            'alignment_strategy' => $this->generate_alignment_strategy($matching_trends),
            'viral_potential_increase' => $relevance_score / 200
        ];
    }
    
    /**
     * Generate alignment strategy
     */
    private function generate_alignment_strategy($matching_trends) {
        if (empty($matching_trends)) {
            return 'Add trending hashtags and keywords';
        }
        return 'Leverage existing trend alignment with enhanced messaging';
    }
    
    /**
     * Adapt to trends
     */
    private function adapt_to_trends($content, $trend_relevance, $trend_data) {
        $adaptations = [];
        
        if (!empty($trend_relevance['matching_trends'])) {
            foreach ($trend_relevance['matching_trends'] as $trend) {
                $adaptations[] = [
                    'trend' => $trend,
                    'suggested_hashtag' => '#' . str_replace(' ', '', ucwords($trend)),
                    'integration_suggestion' => "Emphasize connection to {$trend}"
                ];
            }
        }
        
        return $adaptations;
    }
    
    /**
     * Check platform compliance
     */
    private function check_platform_compliance($content, $platform) {
        $max_chars = $this->get_platform_max_chars($platform);
        $score = min(10, (strlen($content) / $max_chars) * 10);
        return max(0, min(10, $score));
    }
    
    /**
     * Check engagement optimization
     */
    private function check_engagement_optimization($content, $platform) {
        $engagement_score = 5; // Base score
        
        if (substr_count($content, '#') >= 3 && substr_count($content, '#') <= 8) {
            $engagement_score += 2;
        }
        
        if (substr_count($content, '@') > 0) {
            $engagement_score += 1;
        }
        
        if (substr_count($content, '?') > 0) {
            $engagement_score += 1;
        }
        
        if (preg_match('/[\x{1F600}-\x{1F64F}]/u', $content)) {
            $engagement_score += 1;
        }
        
        return $engagement_score;
    }
    
    /**
     * Check format optimization
     */
    private function check_format_optimization($content, $platform) {
        $score = 5; // Base score
        
        if (is_array($content)) {
            if (isset($content['media_type']) && $content['media_type']) {
                $score += 2;
            }
            
            if (isset($content['call_to_action']) && $content['call_to_action']) {
                $score += 2;
            }
            
            if (isset($content['hashtags']) && count($content['hashtags']) > 0) {
                $score += 1;
            }
        }
        
        return min(10, $score);
    }
    
    /**
     * Check SEO optimization
     */
    private function check_seo_optimization($content, $platform) {
        $score = 5; // Base score
        
        if (preg_match('/\b(digital|marketing|social|media|content|strategy)\b/i', $content)) {
            $score += 2;
        }
        
        if (strlen($content) > 50) {
            $score += 1;
        }
        
        if (substr_count($content, ' ') > 10) {
            $score += 1;
        }
        
        if ($platform === 'linkedin' && $this->contains_professional_keywords($content)) {
            $score += 1;
        }
        
        return min(10, $score);
    }
    
    /**
     * Generate optimization recommendations
     */
    private function generate_optimization_recommendations($optimization_factors, $platform) {
        $recommendations = [];
        
        if ($optimization_factors['platform_compliance'] < 7) {
            $recommendations[] = "Consider shortening content for {$platform} character limits";
        }
        
        if ($optimization_factors['engagement_optimization'] < 7) {
            $recommendations[] = "Add more engagement elements (questions, emojis, mentions)";
        }
        
        if ($optimization_factors['format_optimization'] < 7) {
            $recommendations[] = "Include media and clear call-to-action";
        }
        
        return $recommendations;
    }
    
    /**
     * Create cross-promotion links for content series
     */
    private function create_cross_promotion_links($series_content) {
        $links = [];
        
        for ($i = 0; $i < count($series_content); $i++) {
            for ($j = 0; $j < count($series_content); $j++) {
                if ($i !== $j) {
                    $links[] = [
                        'from_piece' => $i + 1,
                        'to_piece' => $j + 1,
                        'link_text' => "Read part " . ($j + 1) . " of this series",
                        'link_type' => $this->determine_link_type($i, $j)
                    ];
                }
            }
        }
        
        return $links;
    }
    
    /**
     * Determine link type for cross-promotion
     */
    private function determine_link_type($from_piece, $to_piece) {
        if ($to_piece > $from_piece) {
            return 'forward_reference';
        } elseif ($to_piece < $from_piece) {
            return 'backward_reference';
        }
        return 'related_content';
    }
    
    /**
     * Calculate refresh schedule for evergreen content
     */
    private function calculate_refresh_schedule($optimized_content) {
        return [
            'first_refresh' => date('Y-m-d', strtotime('+3 months')),
            'second_refresh' => date('Y-m-d', strtotime('+6 months')),
            'annual_refresh' => date('Y-m-d', strtotime('+1 year')),
            'refresh_reminders' => true
        ];
    }
    
    /**
     * Generate fallback repurposing when AI fails
     */
    private function generate_fallback_repurposing($original_content, $target_platforms) {
        $fallback_content = [];
        
        foreach ($target_platforms as $platform) {
            $fallback_content[$platform] = [
                'content' => $this->create_basic_platform_content($original_content, $platform),
                'format' => $this->get_platform_format($platform),
                'media_suggestions' => $this->get_media_suggestions($platform),
                'hashtag_suggestions' => $this->get_hashtag_suggestions($original_content),
                'quality_score' => 6.0,
                'optimization_level' => [
                    'overall_score' => 65,
                    'factors' => ['fallback' => true],
                    'recommendations' => ['Enable AI features for advanced optimization']
                ]
            ];
        }
        
        return [
            'original_content' => $original_content,
            'repurposed_content' => $fallback_content,
            'transformation_metadata' => [
                'generated_at' => current_time('mysql'),
                'fallback' => true,
                'message' => 'Using basic content transformation'
            ]
        ];
    }
    
    /**
     * Create basic platform-specific content
     */
    private function create_basic_platform_content($original_content, $platform) {
        $content = substr(strip_tags($original_content), 0, 280);
        
        switch ($platform) {
            case 'twitter':
                return $content . ' #SocialMedia #Content';
            case 'linkedin':
                return $content . ' - Professional insights for your network.';
            case 'instagram':
                return $content . ' 📸✨ #InstaGood';
            case 'facebook':
                return $content . ' What do you think?';
            default:
                return $content;
        }
    }
    
    /**
     * Get platform format requirements
     */
    private function get_platform_format($platform) {
        $formats = [
            'twitter' => ['max_chars' => 280, 'media' => ['image', 'video', 'link']],
            'linkedin' => ['max_chars' => 3000, 'media' => ['image', 'video', 'document']],
            'instagram' => ['max_chars' => 2200, 'media' => ['image', 'video', 'story']],
            'facebook' => ['max_chars' => 63206, 'media' => ['image', 'video', 'link']]
        ];
        
        return $formats[$platform] ?? ['max_chars' => 280, 'media' => ['text']];
    }
    
    /**
     * Get media suggestions for platform
     */
    private function get_media_suggestions($platform) {
        $suggestions = [
            'twitter' => ['infographic', 'quote_image', 'short_video'],
            'linkedin' => ['professional_photo', 'presentation_slide', 'article_thumbnail'],
            'instagram' => ['high_quality_photo', 'reel', 'carousel'],
            'facebook' => ['photo', 'video', 'live_stream']
        ];
        
        return $suggestions[$platform] ?? ['image'];
    }
    
    /**
     * Get hashtag suggestions from content
     */
    private function get_hashtag_suggestions($content) {
        preg_match_all('/\b[a-zA-Z][a-zA-Z0-9]{2,}\b/', $content, $matches);
        $words = array_unique($matches[0]);
        
        $hashtags = [];
        foreach ($words as $word) {
            $hashtags[] = '#' . ucfirst(strtolower($word));
        }
        
        return array_slice($hashtags, 0, 5);
    }
    
    /**
     * Schedule content analysis jobs
     */
    private function schedule_content_analysis() {
        if (!function_exists('wp_schedule_event')) {
            return;
        }
        
        // Schedule daily content optimization analysis
        if (!wp_next_scheduled('smo_content_analysis_daily')) {
            wp_schedule_event(time(), 'daily', 'smo_content_analysis_daily');
        }
        
        // Schedule weekly evergreen content review
        if (!wp_next_scheduled('smo_evergreen_review_weekly')) {
            wp_schedule_event(time(), 'weekly', 'smo_evergreen_review_weekly');
        }
    }
    
    /**
     * Generate fallback series
     */
    private function generate_fallback_series($original_content, $series_config) {
        return [
            'series_title' => 'Content Series from Original Post',
            'series_description' => 'Basic content series generated from original content',
            'total_pieces' => 3,
            'series_content' => [
                ['title' => 'Part 1: Introduction', 'content' => substr($original_content, 0, 300) . '...'],
                ['title' => 'Part 2: Main Points', 'content' => substr($original_content, 300, 300) . '...'],
                ['title' => 'Part 3: Conclusion', 'content' => substr($original_content, 600, 300) . '...']
            ],
            'publishing_schedule' => [],
            'cross_promotion_links' => []
        ];
    }
    
    /**
     * Generate fallback evergreen content
     */
    private function generate_fallback_evergreen($content) {
        return [
            'is_evergreen' => true,
            'original_content' => $content,
            'optimized_content' => $content,
            'lifecycle_prediction' => '6-12 months',
            'refresh_schedule' => ['manual_review_needed' => true],
            'fallback' => true
        ];
    }
    
    /**
     * Generate fallback trend adaptation
     */
    private function generate_fallback_trend_adaptation($content, $trend_data) {
        return [
            'original_content' => $content,
            'trend_relevance_score' => 5.0,
            'trend_adaptations' => ['basic_trend_hashtags' => ['#Trending', '#Viral']],
            'trend_alignment_strategy' => 'basic_hashtag_addition',
            'viral_potential_increase' => 0.1,
            'fallback' => true
        ];
    }
    
    /**
     * Helper function to check for professional keywords
     */
    private function contains_professional_keywords($content) {
        $professional_keywords = ['business', 'professional', 'industry', 'expert', 'career', 'leadership'];
        $content_lower = strtolower($content);
        
        foreach ($professional_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get platform max chars
     */
    private function get_platform_max_chars($platform) {
        $limits = [
            'twitter' => 280,
            'instagram' => 2200,
            'facebook' => 63206,
            'linkedin' => 3000,
            'tiktok' => 2200
        ];
        
        return $limits[$platform] ?? 500;
    }
}
