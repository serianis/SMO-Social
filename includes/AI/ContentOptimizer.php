<?php
namespace SMO_Social\AI;

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * Global variables declaration for Intelephense compatibility
 */
if (!isset($GLOBALS['wpdb'])) {
    global $wpdb;
}

if (!defined('ABSPATH')) {
    exit; // Security check
}

/**
 * SMO_Content_Optimizer - AI-Powered Content Optimization
 * 
 * Analyzes content and provides platform-specific recommendations for
 * optimal engagement across all 28 supported platforms.
 */
class ContentOptimizer {

    private $ai_manager;
    private $platform_specs = array();
    private $cache_duration = 3600; // 1 hour

    /**
     * Constructor
     */
    public function __construct() {
        $this->ai_manager = Manager::getInstance();
        $this->load_platform_specs();
    }

    /**
     * Load all platform specifications from JSON drivers
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
     * Optimize content for specific platform
     * 
     * @param int    $post_id  WordPress post ID
     * @param string $platform Platform slug
     * @return array|\WP_Error Optimization recommendations or error
     */
    public function optimize_for_platform($post_id, $platform) {
        // Check cache first
        $cache_key = 'smo_optimize_' . $post_id . '_' . $platform;
        $cached = \get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $post = \get_post($post_id);
        if (!$post) {
            return new \WP_Error('invalid_post', \__('Post not found', 'smo-social'));
        }

        $platform_spec = $this->platform_specs[$platform] ?? null;
        if (!$platform_spec) {
            return new \WP_Error('invalid_platform', \__('Platform not found', 'smo-social'));
        }

        // Generate comprehensive optimization
        $optimization = array(
            'platform' => $platform,
            'platform_name' => $platform_spec['name'],
            'content_analysis' => $this->analyze_content($post, $platform_spec),
            'recommendations' => $this->generate_recommendations($post, $platform_spec),
            'optimized_content' => $this->generate_optimized_content($post, $platform_spec),
            'hashtag_suggestions' => $this->suggest_hashtags($post, $platform_spec),
            'best_posting_time' => $this->get_best_posting_time($platform_spec),
            'engagement_prediction' => $this->predict_engagement($post, $platform_spec),
            'warnings' => $this->check_warnings($post, $platform_spec),
            'timestamp' => \current_time('mysql')
        );

        // Cache the result
        \set_transient($cache_key, $optimization, $this->cache_duration);

        return $optimization;
    }

    /**
     * Optimize content for multiple platforms at once
     * 
     * @param int   $post_id   WordPress post ID
     * @param array $platforms Array of platform slugs
     * @return array Multi-platform optimization results
     */
    public function optimize_for_multiple_platforms($post_id, $platforms) {
        $results = array();
        
        foreach ($platforms as $platform) {
            $results[$platform] = $this->optimize_for_platform($post_id, $platform);
        }

        return $results;
    }

    /**
     * Analyze content characteristics
     * 
     * @param \WP_Post $post          WordPress post
     * @param array   $platform_spec Platform specifications
     * @return array Content analysis
     */
    private function analyze_content($post, $platform_spec) {
        $content = strip_tags($post->post_content);
        $title = $post->post_title;
        $excerpt = $post->post_excerpt;
        
        $max_chars = $platform_spec['capabilities']['max_chars'] ?? 280;
        $content_length = \mb_strlen($content);
        $title_length = \mb_strlen($title);
        
        // Word count and reading time
        $word_count = str_word_count($content);
        $reading_time = ceil($word_count / 200); // Average reading speed
        
        // Sentiment analysis (basic)
        $sentiment = $this->analyze_sentiment($content);
        
        // Media analysis
        $has_image = \has_post_thumbnail($post->ID);
        $has_video = $this->has_video_content($post);
        
        // Link analysis
        $links = $this->extract_links($content);
        
        return array(
            'content_length' => $content_length,
            'title_length' => $title_length,
            'word_count' => $word_count,
            'reading_time' => $reading_time,
            'sentiment' => $sentiment,
            'has_image' => $has_image,
            'has_video' => $has_video,
            'link_count' => count($links),
            'fits_platform_limit' => $content_length <= $max_chars,
            'truncation_needed' => $content_length > $max_chars,
            'truncation_amount' => max(0, $content_length - $max_chars)
        );
    }

    /**
     * Generate platform-specific recommendations
     * 
     * @param \WP_Post $post          WordPress post
     * @param array   $platform_spec Platform specifications
     * @return array Recommendations
     */
    private function generate_recommendations($post, $platform_spec) {
        $recommendations = array();
        $content = strip_tags($post->post_content);
        $max_chars = $platform_spec['capabilities']['max_chars'] ?? 280;
        
        // Content length recommendations
        if (\mb_strlen($content) > $max_chars) {
            $recommendations[] = array(
                'type' => 'warning',
                'category' => 'content_length',
                'message' => sprintf(
                    \__('Content exceeds %s character limit (%d chars). Consider shortening or using a thread.', 'smo-social'),
                    $platform_spec['name'],
                    $max_chars
                ),
                'priority' => 'high'
            );
        } elseif (\mb_strlen($content) < ($max_chars * 0.3)) {
            $recommendations[] = array(
                'type' => 'info',
                'category' => 'content_length',
                'message' => \__('Content is quite short. Consider adding more context for better engagement.', 'smo-social'),
                'priority' => 'low'
            );
        }
        
        // Media recommendations
        if (!\has_post_thumbnail($post->ID) && isset($platform_spec['capabilities']['media']) && $platform_spec['capabilities']['media']) {
            $recommendations[] = array(
                'type' => 'suggestion',
                'category' => 'media',
                'message' => sprintf(
                    \__('%s posts with images get 2-3x more engagement. Consider adding a featured image.', 'smo-social'),
                    $platform_spec['name']
                ),
                'priority' => 'medium'
            );
        }
        
        // Hashtag recommendations
        $tags = \get_the_tags($post->ID);
        $tag_count = $tags ? count($tags) : 0;
        $optimal_hashtags = $platform_spec['engagement_factors']['optimal_hashtags'] ?? 5;
        
        if ($tag_count === 0) {
            $recommendations[] = array(
                'type' => 'suggestion',
                'category' => 'hashtags',
                'message' => sprintf(
                    \__('Add %d-%d relevant hashtags to increase discoverability on %s.', 'smo-social'),
                    max(1, $optimal_hashtags - 2),
                    $optimal_hashtags + 2,
                    $platform_spec['name']
                ),
                'priority' => 'medium'
            );
        } elseif ($tag_count > ($optimal_hashtags * 2)) {
            $recommendations[] = array(
                'type' => 'warning',
                'category' => 'hashtags',
                'message' => sprintf(
                    \__('Too many hashtags (%d). Optimal range is %d-%d for %s.', 'smo-social'),
                    $tag_count,
                    max(1, $optimal_hashtags - 2),
                    $optimal_hashtags + 2,
                    $platform_spec['name']
                ),
                'priority' => 'medium'
            );
        }
        
        // Platform-specific recommendations
        $recommendations = array_merge($recommendations, $this->get_platform_specific_recommendations($post, $platform_spec));
        
        return $recommendations;
    }

    /**
     * Get platform-specific recommendations
     * 
     * @param \WP_Post $post          WordPress post
     * @param array   $platform_spec Platform specifications
     * @return array Platform-specific recommendations
     */
    private function get_platform_specific_recommendations($post, $platform_spec) {
        $recommendations = array();
        $platform = $platform_spec['slug'];
        
        switch ($platform) {
            case 'twitter':
                if (!$this->has_call_to_action($post)) {
                    $recommendations[] = array(
                        'type' => 'suggestion',
                        'category' => 'engagement',
                        'message' => \__('Twitter posts with questions or calls-to-action get 3x more replies.', 'smo-social'),
                        'priority' => 'medium'
                    );
                }
                break;
                
            case 'instagram':
                if (!\has_post_thumbnail($post->ID)) {
                    $recommendations[] = array(
                        'type' => 'error',
                        'category' => 'media',
                        'message' => \__('Instagram requires at least one image. Please add a featured image.', 'smo-social'),
                        'priority' => 'critical'
                    );
                }
                break;
                
            case 'linkedin':
                $content_length = \mb_strlen(strip_tags($post->post_content));
                if ($content_length < 150) {
                    $recommendations[] = array(
                        'type' => 'suggestion',
                        'category' => 'content_length',
                        'message' => \__('LinkedIn posts between 150-300 characters get the most engagement.', 'smo-social'),
                        'priority' => 'medium'
                    );
                }
                break;
                
            case 'tiktok':
            case 'youtube':
                if (!$this->has_video_content($post)) {
                    $recommendations[] = array(
                        'type' => 'error',
                        'category' => 'media',
                        'message' => sprintf(\__('%s requires video content.', 'smo-social'), $platform_spec['name']),
                        'priority' => 'critical'
                    );
                }
                break;
                
            case 'pinterest':
                if (\has_post_thumbnail($post->ID)) {
                    $image_meta = \wp_get_attachment_metadata(\get_post_thumbnail_id($post->ID));
                    if ($image_meta && isset($image_meta['width'], $image_meta['height'])) {
                        $ratio = $image_meta['height'] / $image_meta['width'];
                        if ($ratio < 1.5) {
                            $recommendations[] = array(
                                'type' => 'warning',
                                'category' => 'media',
                                'message' => \__('Pinterest performs best with vertical images (2:3 or 1:2 ratio).', 'smo-social'),
                                'priority' => 'medium'
                            );
                        }
                    }
                }
                break;
        }
        
        return $recommendations;
    }

    /**
     * Generate optimized content for platform
     * 
     * @param \WP_Post $post          WordPress post
     * @param array   $platform_spec Platform specifications
     * @return array Optimized content variations
     */
    private function generate_optimized_content($post, $platform_spec) {
        $original_content = strip_tags($post->post_content);
        $max_chars = $platform_spec['capabilities']['max_chars'] ?? 280;
        
        $variations = array();
        
        // Original (possibly truncated)
        if (\mb_strlen($original_content) <= $max_chars) {
            $variations['original'] = $original_content;
        } else {
            $variations['original_truncated'] = \mb_substr($original_content, 0, $max_chars - 3) . '...';
        }
        
        // Short version (50% of limit)
        $short_length = intval($max_chars * 0.5);
        $variations['short'] = $this->create_summary($original_content, $short_length);
        
        // Optimal version (80% of limit)
        $optimal_length = intval($max_chars * 0.8);
        $variations['optimal'] = $this->create_summary($original_content, $optimal_length);
        
        // With call-to-action
        $cta_phrases = array(
            'Learn more:',
            'Read the full article:',
            'Check it out:',
            'Discover more:',
            'Find out how:'
        );
        $cta = $cta_phrases[array_rand($cta_phrases)];
        $cta_length = $optimal_length - \mb_strlen($cta) - \mb_strlen(\get_permalink($post->ID)) - 5;
        $variations['with_cta'] = $this->create_summary($original_content, $cta_length) . "\n\n" . $cta . ' ' . \get_permalink($post->ID);
        
        // With emoji (for platforms that support it)
        if ($this->platform_supports_emoji($platform_spec)) {
            $variations['with_emoji'] = $this->add_contextual_emoji($variations['optimal']);
        }
        
        return $variations;
    }

    /**
     * Create content summary
     * 
     * @param string $content Content to summarize
     * @param int    $length  Target length
     * @return string Summary
     */
    private function create_summary($content, $length) {
        if (\mb_strlen($content) <= $length) {
            return $content;
        }
        
        // Try to break at sentence boundary
        $summary = \mb_substr($content, 0, $length);
        $last_period = \mb_strrpos($summary, '.');
        $last_exclamation = \mb_strrpos($summary, '!');
        $last_question = \mb_strrpos($summary, '?');
        
        $last_sentence = max($last_period, $last_exclamation, $last_question);
        
        if ($last_sentence !== false && $last_sentence > ($length * 0.7)) {
            return \mb_substr($summary, 0, $last_sentence + 1);
        }
        
        // Break at word boundary
        $last_space = \mb_strrpos($summary, ' ');
        if ($last_space !== false) {
            return \mb_substr($summary, 0, $last_space) . '...';
        }
        
        return $summary . '...';
    }

    /**
     * Suggest hashtags for platform
     * 
     * @param \WP_Post $post          WordPress post
     * @param array   $platform_spec Platform specifications
     * @return array Hashtag suggestions
     */
    private function suggest_hashtags($post, $platform_spec) {
        $suggestions = array();
        
        // Get existing tags
        $existing_tags = \get_the_tags($post->ID);
        if ($existing_tags) {
            foreach ($existing_tags as $tag) {
                $suggestions[] = array(
                    'hashtag' => '#' . str_replace(' ', '', $tag->name),
                    'source' => 'post_tags',
                    'confidence' => 0.9
                );
            }
        }
        
        // Get category-based hashtags
        $categories = \get_the_category($post->ID);
        if ($categories) {
            foreach ($categories as $category) {
                $suggestions[] = array(
                    'hashtag' => '#' . str_replace(' ', '', $category->name),
                    'source' => 'category',
                    'confidence' => 0.8
                );
            }
        }
        
        // AI-generated hashtags (using content analysis)
        $content = strip_tags($post->post_content);
        $ai_hashtags = $this->generate_ai_hashtags($content, $platform_spec);
        $suggestions = array_merge($suggestions, $ai_hashtags);
        
        // Remove duplicates and limit to optimal count
        $unique_suggestions = array();
        $seen = array();
        foreach ($suggestions as $suggestion) {
            $hashtag_lower = strtolower($suggestion['hashtag']);
            if (!isset($seen[$hashtag_lower])) {
                $unique_suggestions[] = $suggestion;
                $seen[$hashtag_lower] = true;
            }
        }
        
        // Sort by confidence
        usort($unique_suggestions, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        // Limit to optimal count for platform
        $optimal_count = $platform_spec['engagement_factors']['optimal_hashtags'] ?? 5;
        return array_slice($unique_suggestions, 0, $optimal_count * 2); // Return 2x optimal for user choice
    }

    /**
     * Generate AI-powered hashtags
     * 
     * @param string $content       Content to analyze
     * @param array  $platform_spec Platform specifications
     * @return array AI-generated hashtags
     */
    private function generate_ai_hashtags($content, $platform_spec) {
        // Extract key phrases (simple implementation)
        $words = str_word_count(strtolower($content), 1);
        $word_freq = array_count_values($words);
        
        // Remove common words
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these', 'those');
        foreach ($stop_words as $stop_word) {
            unset($word_freq[$stop_word]);
        }
        
        // Sort by frequency
        arsort($word_freq);
        
        // Generate hashtags from top words
        $ai_hashtags = array();
        $count = 0;
        foreach ($word_freq as $word => $freq) {
            if ($count >= 5 || strlen($word) < 4) continue;
            
            $ai_hashtags[] = array(
                'hashtag' => '#' . ucfirst($word),
                'source' => 'ai_content_analysis',
                'confidence' => min(0.7, 0.4 + ($freq * 0.1))
            );
            $count++;
        }
        
        return $ai_hashtags;
    }

    /**
     * Get best posting time for platform
     * 
     * @param array $platform_spec Platform specifications
     * @return array Best posting times
     */
    private function get_best_posting_time($platform_spec) {
        $optimal_times = $platform_spec['engagement_factors']['optimal_posting_times'] ?? array();
        
        if (empty($optimal_times)) {
            return array(
                'recommended' => 'Weekdays 9AM-5PM',
                'timezone' => 'User timezone',
                'confidence' => 0.5
            );
        }
        
        return array(
            'times' => $optimal_times,
            'timezone' => 'User timezone',
            'confidence' => 0.8
        );
    }

    /**
     * Predict engagement for post
     * 
     * @param \WP_Post $post          WordPress post
     * @param array   $platform_spec Platform specifications
     * @return array Engagement prediction
     */
    private function predict_engagement($post, $platform_spec) {
        $score = 50; // Base score
        
        // Content length factor
        $content_length = \mb_strlen(strip_tags($post->post_content));
        $max_chars = $platform_spec['capabilities']['max_chars'] ?? 280;
        $optimal_length = $max_chars * 0.7;
        
        if ($content_length >= ($optimal_length * 0.8) && $content_length <= ($optimal_length * 1.2)) {
            $score += 15;
        }
        
        // Media factor
        if (\has_post_thumbnail($post->ID)) {
            $score += 20;
        }
        
        // Hashtag factor
        $tags = \get_the_tags($post->ID);
        $tag_count = $tags ? count($tags) : 0;
        $optimal_hashtags = $platform_spec['engagement_factors']['optimal_hashtags'] ?? 5;
        
        if ($tag_count >= ($optimal_hashtags * 0.8) && $tag_count <= ($optimal_hashtags * 1.5)) {
            $score += 10;
        }
        
        // Call to action factor
        if ($this->has_call_to_action($post)) {
            $score += 5;
        }
        
        // Cap at 100
        $score = min(100, $score);
        
        return array(
            'score' => $score,
            'level' => $this->get_engagement_level($score),
            'factors' => array(
                'content_length' => $content_length >= ($optimal_length * 0.8) && $content_length <= ($optimal_length * 1.2),
                'has_media' => \has_post_thumbnail($post->ID),
                'optimal_hashtags' => $tag_count >= ($optimal_hashtags * 0.8) && $tag_count <= ($optimal_hashtags * 1.5),
                'has_cta' => $this->has_call_to_action($post)
            )
        );
    }

    /**
     * Get engagement level from score
     * 
     * @param int $score Engagement score
     * @return string Engagement level
     */
    private function get_engagement_level($score) {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'average';
        return 'needs_improvement';
    }

    /**
     * Check for warnings
     * 
     * @param \WP_Post $post          WordPress post
     * @param array   $platform_spec Platform specifications
     * @return array Warnings
     */
    private function check_warnings($post, $platform_spec) {
        $warnings = array();
        
        // Check content length
        $content_length = \mb_strlen(strip_tags($post->post_content));
        $max_chars = $platform_spec['capabilities']['max_chars'] ?? 280;
        
        if ($content_length > $max_chars) {
            $warnings[] = array(
                'type' => 'content_truncation',
                'severity' => 'high',
                'message' => sprintf(\__('Content will be truncated by %d characters', 'smo-social'), $content_length - $max_chars)
            );
        }
        
        // Check required media
        if (in_array($platform_spec['slug'], array('instagram', 'tiktok', 'pinterest')) && !\has_post_thumbnail($post->ID)) {
            $warnings[] = array(
                'type' => 'missing_media',
                'severity' => 'critical',
                'message' => sprintf(\__('%s requires media content', 'smo-social'), $platform_spec['name'])
            );
        }
        
        return $warnings;
    }

    /**
     * Analyze sentiment of content
     * 
     * @param string $content Content to analyze
     * @return array Sentiment analysis
     */
    private function analyze_sentiment($content) {
        // Simple sentiment analysis based on keywords
        $positive_words = array('great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'love', 'best', 'awesome', 'perfect', 'happy');
        $negative_words = array('bad', 'terrible', 'awful', 'hate', 'worst', 'poor', 'disappointing', 'sad', 'angry', 'frustrated');
        
        $content_lower = strtolower($content);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($content_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($content_lower, $word);
        }
        
        $total = $positive_count + $negative_count;
        if ($total === 0) {
            return array('type' => 'neutral', 'confidence' => 0.5);
        }
        
        $positive_ratio = $positive_count / $total;
        
        if ($positive_ratio > 0.6) {
            return array('type' => 'positive', 'confidence' => $positive_ratio);
        } elseif ($positive_ratio < 0.4) {
            return array('type' => 'negative', 'confidence' => 1 - $positive_ratio);
        }
        
        return array('type' => 'neutral', 'confidence' => 0.5);
    }

    /**
     * Check if post has video content
     * 
     * @param \WP_Post $post WordPress post
     * @return bool Has video
     */
    private function has_video_content($post) {
        // Check for video embeds or video post format
        return \has_post_format('video', $post) || 
               preg_match('/<video|<iframe.*?(youtube|vimeo)/i', $post->post_content);
    }

    /**
     * Extract links from content
     * 
     * @param string $content Content to analyze
     * @return array Links found
     */
    private function extract_links($content) {
        preg_match_all('/(https?:\/\/[^\s]+)/i', $content, $matches);
        return $matches[0] ?? array();
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
     * Check if platform supports emoji
     * 
     * @param array $platform_spec Platform specifications
     * @return bool Supports emoji
     */
    private function platform_supports_emoji($platform_spec) {
        // Most modern platforms support emoji
        $no_emoji_platforms = array('linkedin', 'medium', 'quora');
        return !in_array($platform_spec['slug'], $no_emoji_platforms);
    }

    /**
     * Add contextual emoji to content
     * 
     * @param string $content Content to enhance
     * @return string Content with emoji
     */
    private function add_contextual_emoji($content) {
        // Simple emoji addition based on content sentiment
        $sentiment = $this->analyze_sentiment($content);
        
        $emoji_map = array(
            'positive' => array('✨', '🎉', '💡', '🚀', '⭐', '👍', '💪'),
            'negative' => array('😔', '💭', '🤔'),
            'neutral' => array('📌', '💬', '📝', '🔍')
        );
        
        $emoji_set = $emoji_map[$sentiment['type']] ?? $emoji_map['neutral'];
        $emoji = $emoji_set[array_rand($emoji_set)];
        
        return $emoji . ' ' . $content;
    }

    /**
     * Clear optimization cache for post
     * 
     * @param int $post_id WordPress post ID
     */
    public function clear_cache($post_id) {
        foreach ($this->platform_specs as $slug => $spec) {
            \delete_transient('smo_optimize_' . $post_id . '_' . $slug);
        }
    }
}
