<?php
namespace SMO_Social\AI\Content;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * AI-powered caption generation for social media posts
 */
class CaptionGenerator {
    private $ai_manager;
    private $cache_manager;

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Generate optimized captions for specific platforms
     */
    public function generate($content, $platform, $options = []) {
        $cache_key = "caption_" . md5($content . $platform . serialize($options));
        
        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Generate using AI
        $prompt = $this->build_caption_prompt($content, $platform, $options);
        
        try {
            // Use chat interface for generation
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert social media manager.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'max_tokens' => $this->get_platform_limit($platform),
                'temperature' => 0.7
            ]);
            
            $caption = $this->extract_caption_from_result($result);
            
            // Cache the result
            $this->cache_manager->set($cache_key, $caption, 3600);
            
            return $caption;
            
        } catch (\Exception $e) {
            error_log("SMO Social CaptionGenerator Error: " . $e->getMessage());
            return $this->generate_fallback_caption($content, $platform);
        }
    }

    private function build_caption_prompt($content, $platform, $options) {
        $platform_specs = [
            'twitter' => 'Keep it concise, engaging, and under 280 characters. Use relevant hashtags.',
            'instagram' => 'Create an engaging, visual caption with relevant hashtags and emojis.',
            'linkedin' => 'Make it professional, thought-provoking, and industry-relevant.',
            'facebook' => 'Create a conversational, shareable caption that encourages engagement.',
            'tiktok' => 'Make it trendy, attention-grabbing, and youth-oriented.'
        ];

        $spec = $platform_specs[$platform] ?? 'Create an engaging, platform-appropriate caption.';
        
        return "Generate a social media caption for {$platform}: {$spec}\n\nContent: {$content}\n\nGenerate the caption:";
    }

    private function extract_caption_from_result($result) {
        if (isset($result['content'])) {
            return trim($result['content']);
        }
        
        if (isset($result['error'])) {
            throw new \Exception($result['error']);
        }
        
        return 'Generated caption unavailable';
    }

    private function get_platform_limit($platform) {
        $limits = [
            'twitter' => 280,
            'instagram' => 2200,
            'facebook' => 63206,
            'linkedin' => 3000,
            'tiktok' => 2200
        ];
        
        return $limits[$platform] ?? 500;
    }

    private function generate_fallback_caption($content, $platform) {
        // Simple fallback - truncate content and add platform-appropriate ending
        $max_length = $this->get_platform_limit($platform);
        $caption = substr(strip_tags($content), 0, $max_length - 10);
        
        switch ($platform) {
            case 'twitter':
                $caption .= ' #socialmedia';
                break;
            case 'linkedin':
                $caption .= ' #professional';
                break;
            case 'instagram':
                $caption .= ' ✨ #social';
                break;
        }
        
        return $caption;
    }
}