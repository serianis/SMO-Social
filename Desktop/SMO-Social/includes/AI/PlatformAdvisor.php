<?php
namespace SMO_Social\AI;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_Platform_Advisor - Intelligent Platform Selection & Strategy
 * 
 * Analyzes content and recommends the best platforms for publishing,
 * along with platform-specific strategies for maximum engagement.
 */
class PlatformAdvisor {

    private $content_optimizer;
    private $platform_specs = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->content_optimizer = new ContentOptimizer();
        $this->load_platform_specs();
    }

    /**
     * Load all platform specifications
     */
    private function load_platform_specs() {
        $driver_dir = SMO_SOCIAL_PLUGIN_DIR . 'drivers/';
        $driver_files = glob($driver_dir . '*.json');
        
        foreach ($driver_files as $file) {
            $driver_data = json_decode(file_get_contents($file), true);
            if ($driver_data && isset($driver_data['slug'])) {
                $this->platform_specs[$driver_data['slug']] = $driver_data;
            }
        }
    }

    /**
     * Recommend best platforms for content
     * 
     * @param int   $post_id           WordPress post ID
     * @param array $enabled_platforms Array of enabled platform slugs
     * @return array|\WP_Error Platform recommendations with scores or error
     */
    public function recommend_platforms($post_id, $enabled_platforms = array()) {
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('invalid_post', __('Post not found', 'smo-social'));
        }

        // If no enabled platforms specified, use all
        if (empty($enabled_platforms)) {
            $enabled_platforms = array_keys($this->platform_specs);
        }

        $recommendations = array();

        foreach ($enabled_platforms as $platform) {
            if (!isset($this->platform_specs[$platform])) {
                continue;
            }

            $score = $this->calculate_platform_score($post, $this->platform_specs[$platform]);
            $recommendations[] = array(
                'platform' => $platform,
                'platform_name' => $this->platform_specs[$platform]['name'],
                'score' => $score['total'],
                'suitability' => $this->get_suitability_level($score['total']),
                'factors' => $score['factors'],
                'strategy' => $this->generate_platform_strategy($post, $this->platform_specs[$platform], $score)
            );
        }

        // Sort by score (highest first)
        usort($recommendations, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $recommendations;
    }

    /**
     * Calculate platform suitability score
     * 
     * @param \WP_Post $post          WordPress post
     * @param array    $platform_spec Platform specifications
     * @return array Score breakdown
     */
    private function calculate_platform_score($post, $platform_spec) {
        $factors = array();
        $total_score = 0;

        // Content length compatibility (0-25 points)
        $content_length = mb_strlen(strip_tags($post->post_content));
        $max_chars = $platform_spec['capabilities']['max_chars'] ?? 280;
        
        if ($content_length <= $max_chars) {
            $length_score = 25;
        } else {
            $overflow = $content_length - $max_chars;
            $length_score = max(0, 25 - ($overflow / 10));
        }
        $factors['content_length'] = round($length_score);
        $total_score += $length_score;

        // Media compatibility (0-25 points)
        $has_image = has_post_thumbnail($post->ID);
        $has_video = $this->has_video_content($post);
        $media_score = 0;

        if (isset($platform_spec['capabilities']['media']) && $platform_spec['capabilities']['media']) {
            if ($has_image || $has_video) {
                $media_score = 25;
            } else {
                $media_score = 10; // Can post without media but not optimal
            }
        } else {
            $media_score = 15; // Text-focused platform
        }
        $factors['media'] = $media_score;
        $total_score += $media_score;

        // Content type match (0-20 points)
        $content_type_score = $this->calculate_content_type_match($post, $platform_spec);
        $factors['content_type'] = $content_type_score;
        $total_score += $content_type_score;

        // Audience match (0-15 points)
        $audience_score = $this->calculate_audience_match($post, $platform_spec);
        $factors['audience'] = $audience_score;
        $total_score += $audience_score;

        // Engagement potential (0-15 points)
        $engagement_score = $this->calculate_engagement_potential($post, $platform_spec);
        $factors['engagement'] = $engagement_score;
        $total_score += $engagement_score;

        return array(
            'total' => round($total_score),
            'factors' => $factors
        );
    }

    /**
     * Calculate content type match score
     * 
     * @param \WP_Post $post          WordPress post
     * @param array    $platform_spec Platform specifications
     * @return int Score (0-20)
     */
    private function calculate_content_type_match($post, $platform_spec) {
        $score = 10; // Base score
        $platform = $platform_spec['slug'];

        // Video content platforms
        if ($this->has_video_content($post)) {
            if (in_array($platform, array('youtube', 'tiktok', 'instagram', 'facebook'))) {
                $score += 10;
            }
        }

        // Image-heavy platforms
        if (has_post_thumbnail($post->ID)) {
            if (in_array($platform, array('instagram', 'pinterest', 'tumblr'))) {
                $score += 10;
            }
        }

        // Long-form content platforms
        $word_count = str_word_count(strip_tags($post->post_content));
        if ($word_count > 500) {
            if (in_array($platform, array('medium', 'linkedin', 'facebook', 'tumblr'))) {
                $score += 10;
            } elseif (in_array($platform, array('twitter', 'threads'))) {
                $score -= 5; // Penalize for long content on short-form platforms
            }
        }

        // Short-form content platforms
        if ($word_count < 100) {
            if (in_array($platform, array('twitter', 'threads', 'mastodon'))) {
                $score += 10;
            }
        }

        return min(20, max(0, $score));
    }

    /**
     * Calculate audience match score
     * 
     * @param \WP_Post $post          WordPress post
     * @param array    $platform_spec Platform specifications
     * @return int Score (0-15)
     */
    private function calculate_audience_match($post, $platform_spec) {
        $score = 10; // Base score
        $platform = $platform_spec['slug'];
        $categories = get_the_category($post->ID);

        if (empty($categories)) {
            return $score;
        }

        $category_names = array_map(function($cat) {
            return strtolower($cat->name);
        }, $categories);

        // Professional/Business content
        $professional_keywords = array('business', 'professional', 'career', 'industry', 'corporate', 'b2b');
        if (array_intersect($category_names, $professional_keywords)) {
            if (in_array($platform, array('linkedin', 'linkedin_groups'))) {
                $score += 5;
            }
        }

        // Creative/Visual content
        $creative_keywords = array('art', 'design', 'photography', 'creative', 'visual');
        if (array_intersect($category_names, $creative_keywords)) {
            if (in_array($platform, array('instagram', 'pinterest', 'tumblr', 'behance'))) {
                $score += 5;
            }
        }

        // Tech/Gaming content
        $tech_keywords = array('tech', 'technology', 'gaming', 'software', 'developer');
        if (array_intersect($category_names, $tech_keywords)) {
            if (in_array($platform, array('twitter', 'reddit', 'discord', 'mastodon'))) {
                $score += 5;
            }
        }

        // Entertainment content
        $entertainment_keywords = array('entertainment', 'music', 'video', 'fun', 'viral');
        if (array_intersect($category_names, $entertainment_keywords)) {
            if (in_array($platform, array('tiktok', 'youtube', 'instagram', 'snapchat'))) {
                $score += 5;
            }
        }

        return min(15, $score);
    }

    /**
     * Calculate engagement potential
     * 
     * @param \WP_Post $post          WordPress post
     * @param array    $platform_spec Platform specifications
     * @return int Score (0-15)
     */
    private function calculate_engagement_potential($post, $platform_spec) {
        $score = 5; // Base score

        // Has call to action
        if ($this->has_call_to_action($post)) {
            $score += 3;
        }

        // Has hashtags
        $tags = get_the_tags($post->ID);
        if ($tags && count($tags) > 0) {
            $score += 3;
        }

        // Has media
        if (has_post_thumbnail($post->ID)) {
            $score += 3;
        }

        // Optimal posting time consideration
        if (isset($platform_spec['engagement_factors']['optimal_posting_times'])) {
            $score += 1;
        }

        return min(15, $score);
    }

    /**
     * Generate platform-specific strategy
     * 
     * @param \WP_Post $post          WordPress post
     * @param array    $platform_spec Platform specifications
     * @param array    $score         Score breakdown
     * @return array Strategy recommendations
     */
    private function generate_platform_strategy($post, $platform_spec, $score) {
        $strategy = array(
            'recommended_actions' => array(),
            'content_adjustments' => array(),
            'timing' => array(),
            'engagement_tips' => array()
        );

        $platform = $platform_spec['slug'];

        // Content adjustments based on score factors
        if ($score['factors']['content_length'] < 20) {
            $strategy['content_adjustments'][] = __('Shorten content to fit platform limits', 'smo-social');
        }

        if ($score['factors']['media'] < 20 && isset($platform_spec['capabilities']['media'])) {
            $strategy['content_adjustments'][] = __('Add visual content for better engagement', 'smo-social');
        }

        // Platform-specific strategies
        switch ($platform) {
            case 'twitter':
                $strategy['recommended_actions'][] = __('Use 1-2 relevant hashtags', 'smo-social');
                $strategy['recommended_actions'][] = __('Include a question or call-to-action', 'smo-social');
                $strategy['engagement_tips'][] = __('Tweet during peak hours (9AM-3PM weekdays)', 'smo-social');
                break;

            case 'instagram':
                $strategy['recommended_actions'][] = __('Use 5-10 relevant hashtags', 'smo-social');
                $strategy['recommended_actions'][] = __('Post high-quality square or vertical images', 'smo-social');
                $strategy['engagement_tips'][] = __('Post during lunch (11AM-1PM) or evening (7-9PM)', 'smo-social');
                break;

            case 'linkedin':
                $strategy['recommended_actions'][] = __('Keep it professional and value-focused', 'smo-social');
                $strategy['recommended_actions'][] = __('Use 3-5 industry-specific hashtags', 'smo-social');
                $strategy['engagement_tips'][] = __('Post Tuesday-Thursday, 8AM-10AM', 'smo-social');
                break;

            case 'facebook':
                $strategy['recommended_actions'][] = __('Include engaging visuals or videos', 'smo-social');
                $strategy['recommended_actions'][] = __('Ask questions to encourage comments', 'smo-social');
                $strategy['engagement_tips'][] = __('Post Wednesday-Friday, 1-3PM', 'smo-social');
                break;

            case 'pinterest':
                $strategy['recommended_actions'][] = __('Use vertical images (2:3 ratio)', 'smo-social');
                $strategy['recommended_actions'][] = __('Include keyword-rich descriptions', 'smo-social');
                $strategy['engagement_tips'][] = __('Pin during evenings and weekends', 'smo-social');
                break;

            case 'tiktok':
                $strategy['recommended_actions'][] = __('Create short, engaging video content', 'smo-social');
                $strategy['recommended_actions'][] = __('Use trending sounds and hashtags', 'smo-social');
                $strategy['engagement_tips'][] = __('Post 6-10PM when users are most active', 'smo-social');
                break;

            case 'reddit':
                $strategy['recommended_actions'][] = __('Provide value, avoid self-promotion', 'smo-social');
                $strategy['recommended_actions'][] = __('Engage in comments authentically', 'smo-social');
                $strategy['engagement_tips'][] = __('Post Monday-Friday, 6-8AM EST', 'smo-social');
                break;
        }

        // Timing recommendations
        if (isset($platform_spec['engagement_factors']['optimal_posting_times'])) {
            $strategy['timing'] = $platform_spec['engagement_factors']['optimal_posting_times'];
        }

        return $strategy;
    }

    /**
     * Get suitability level from score
     * 
     * @param int $score Platform score
     * @return string Suitability level
     */
    private function get_suitability_level($score) {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    /**
     * Check if post has video content
     * 
     * @param \WP_Post $post WordPress post
     * @return bool Has video
     */
    private function has_video_content($post) {
        return has_post_format('video', $post) || 
               preg_match('/<video|<iframe.*?(youtube|vimeo)/i', $post->post_content);
    }

    /**
     * Check if content has call to action
     * 
     * @param \WP_Post $post WordPress post
     * @return bool Has CTA
     */
    private function has_call_to_action($post) {
        $content = strtolower(strip_tags($post->post_content));
        $cta_patterns = array('click', 'learn more', 'read more', 'check out', 'visit', 'download', 'subscribe', 'follow', 'share', 'comment', 'what do you think', '?');
        
        foreach ($cta_patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate cross-platform strategy
     * 
     * @param int   $post_id           WordPress post ID
     * @param array $selected_platforms Selected platforms for posting
     * @return array|\WP_Error Cross-platform strategy or error
     */
    public function generate_cross_platform_strategy($post_id, $selected_platforms) {
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('invalid_post', __('Post not found', 'smo-social'));
        }

        $strategy = array(
            'posting_order' => array(),
            'timing_strategy' => array(),
            'content_variations' => array(),
            'overall_tips' => array()
        );

        // Determine optimal posting order
        $platform_priorities = array();
        foreach ($selected_platforms as $platform) {
            if (!isset($this->platform_specs[$platform])) continue;
            
            $score = $this->calculate_platform_score($post, $this->platform_specs[$platform]);
            $platform_priorities[] = array(
                'platform' => $platform,
                'score' => $score['total'],
                'name' => $this->platform_specs[$platform]['name']
            );
        }

        usort($platform_priorities, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $strategy['posting_order'] = $platform_priorities;

        // Timing strategy
        $strategy['timing_strategy'] = array(
            'immediate' => array(),
            'scheduled' => array()
        );

        foreach ($platform_priorities as $priority) {
            $platform_spec = $this->platform_specs[$priority['platform']];
            if (isset($platform_spec['engagement_factors']['optimal_posting_times'])) {
                $strategy['timing_strategy']['scheduled'][] = array(
                    'platform' => $priority['platform'],
                    'times' => $platform_spec['engagement_factors']['optimal_posting_times']
                );
            } else {
                $strategy['timing_strategy']['immediate'][] = $priority['platform'];
            }
        }

        // Content variations
        foreach ($selected_platforms as $platform) {
            if (!isset($this->platform_specs[$platform])) continue;
            
            $optimization = $this->content_optimizer->optimize_for_platform($post_id, $platform);
            if (!is_wp_error($optimization)) {
                $strategy['content_variations'][$platform] = $optimization['optimized_content'];
            }
        }

        // Overall tips
        $strategy['overall_tips'] = array(
            __('Post to highest-scoring platforms first', 'smo-social'),
            __('Customize content for each platform', 'smo-social'),
            __('Monitor engagement and adjust strategy', 'smo-social'),
            __('Use platform-specific features (Stories, Reels, etc.)', 'smo-social')
        );

        return $strategy;
    }
}
