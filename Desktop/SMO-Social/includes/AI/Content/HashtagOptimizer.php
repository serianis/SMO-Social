<?php
namespace SMO_Social\AI\Content;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * AI-powered hashtag optimization for social media posts
 */
class HashtagOptimizer {
    private $ai_manager;
    private $cache_manager;

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Generate optimized hashtags for specific platforms
     */
    public function optimize($content, $platform, $options = []) {
        $cache_key = "hashtags_" . md5($content . $platform . serialize($options));
        
        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $max_hashtags = $options['max_hashtags'] ?? $this->get_platform_limit($platform);
            $prompt = $this->build_hashtag_prompt($content, $platform, $max_hashtags);
            
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert social media manager specialized in hashtags.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'max_tokens' => 200,
                'temperature' => 0.6
            ]);
            
            $hashtags = $this->extract_hashtags_from_result($result);
            
            // Cache the result
            $this->cache_manager->set($cache_key, $hashtags, 3600);
            
            return $hashtags;
            
        } catch (\Exception $e) {
            error_log("SMO Social HashtagOptimizer Error: " . $e->getMessage());
            return $this->generate_fallback_hashtags($content);
        }
    }

    private function build_hashtag_prompt($content, $platform, $max_hashtags) {
        return "Generate {$max_hashtags} relevant hashtags for a {$platform} post. Content: {$content}\n\nReturn only hashtags separated by spaces:";
    }

    private function extract_hashtags_from_result($result) {
        if (isset($result['content'])) {
            $text = $result['content'];
            preg_match_all('/#\w+/', $text, $matches);
            return array_unique($matches[0]);
        }
        
        if (isset($result['error'])) {
            throw new \Exception($result['error']);
        }
        
        return [];
    }

    private function get_platform_limit($platform) {
        $limits = [
            'twitter' => 2,
            'instagram' => 30,
            'facebook' => 3,
            'linkedin' => 5,
            'tiktok' => 10
        ];
        
        return $limits[$platform] ?? 5;
    }

    private function generate_fallback_hashtags($content) {
        // Extract words and create simple hashtags
        $words = explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $content)));
        $hashtags = [];
        
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, ['this', 'that', 'with', 'from', 'have', 'been'])) {
                $hashtags[] = '#' . ucfirst($word);
            }
        }
        
        return array_slice(array_unique($hashtags), 0, 10);
    }
}