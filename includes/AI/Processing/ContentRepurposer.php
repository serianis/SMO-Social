<?php
namespace SMO_Social\AI\Processing;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * AI-powered content repurposing for different platforms
 */
class ContentRepurposer {
    private $ai_manager;
    private $cache_manager;

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Repurpose content for different platforms
     */
    public function repurpose($original_content, $platform, $options = []) {
        $cache_key = "repurpose_" . md5($original_content . $platform . serialize($options));
        
        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $prompt = $this->build_repurpose_prompt($original_content, $platform, $options);
            
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert social media manager specialized in content repurposing.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'max_tokens' => $this->get_platform_limit($platform),
                'temperature' => 0.8
            ]);
            
            $repurposed_content = $this->extract_repurposed_content($result);
            
            // Cache the result
            $this->cache_manager->set($cache_key, $repurposed_content, 3600);
            
            return $repurposed_content;
            
        } catch (\Exception $e) {
            error_log("SMO Social ContentRepurposer Error: " . $e->getMessage());
            return $this->repurpose_basic($original_content, $platform);
        }
    }

    /**
     * Generate content variants
     */
    public function generate_variant($original_content, $platform, $variant_type, $options = []) {
        $cache_key = "variant_" . md5($original_content . $platform . $variant_type . serialize($options));
        
        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $prompt = $this->build_variant_prompt($original_content, $platform, $variant_type, $options);
            
            $messages = [
                ['role' => 'system', 'content' => 'You are an expert social media manager specialized in creating content variants.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'max_tokens' => $this->get_platform_limit($platform),
                'temperature' => 0.8
            ]);
            
            $variant_content = $this->extract_repurposed_content($result);
            
            // Cache the result
            $this->cache_manager->set($cache_key, $variant_content, 3600);
            
            return $variant_content;
            
        } catch (\Exception $e) {
            error_log("SMO Social ContentRepurposer Error: " . $e->getMessage());
            return $this->generate_basic_variant($original_content, $variant_type);
        }
    }

    private function build_repurpose_prompt($content, $platform, $options) {
        $platform_requirements = [
            'twitter' => 'Transform this into a concise, engaging Twitter post (max 280 chars)',
            'instagram' => 'Create an Instagram-friendly version with visual elements and hashtags',
            'linkedin' => 'Repurpose this for LinkedIn - make it professional and thought-provoking',
            'facebook' => 'Transform for Facebook - make it conversational and shareable',
            'tiktok' => 'Adapt for TikTok - make it trendy and attention-grabbing'
        ];

        $requirement = $platform_requirements[$platform] ?? 'Adapt this content for the platform';
        
        return "{$requirement}:\n\nOriginal content: {$content}\n\nRepurposed version:";
    }

    private function build_variant_prompt($content, $platform, $variant_type, $options) {
        $variant_instructions = [
            'short' => 'Create a short, punchy version',
            'long' => 'Expand with more details and examples',
            'professional' => 'Make it formal and business-appropriate',
            'casual' => 'Make it conversational and friendly',
            'question' => 'Turn it into an engaging question',
            'call_to_action' => 'Add a compelling call to action'
        ];

        $instruction = $variant_instructions[$variant_type] ?? 'Adapt appropriately';
        
        return "Create a {$variant_type} version of this content for {$platform}. {$instruction}:\n\nContent: {$content}\n\nAdapted version:";
    }

    private function extract_repurposed_content($result) {
        if (isset($result['content'])) {
            return trim($result['content']);
        }
        
        if (isset($result['error'])) {
            throw new \Exception($result['error']);
        }
        
        return 'Content repurposing unavailable';
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

    private function repurpose_basic($content, $platform) {
        // Simple fallback - truncate and add platform-specific elements
        $max_length = $this->get_platform_limit($platform);
        $repurposed = substr(strip_tags($content), 0, $max_length - 20);
        
        switch ($platform) {
            case 'twitter':
                $repurposed .= ' #socialmedia';
                break;
            case 'linkedin':
                $repurposed .= ' #professional';
                break;
            case 'instagram':
                $repurposed .= ' ✨ #social #lifestyle';
                break;
        }
        
        return $repurposed;
    }

    private function generate_basic_variant($content, $variant_type) {
        switch ($variant_type) {
            case 'short':
                return substr(strip_tags($content), 0, 140) . '...';
            case 'long':
                return $content . ' Learn more about this topic and stay updated with our latest insights.';
            case 'question':
                return 'What do you think about this? ' . $content;
            case 'call_to_action':
                return $content . ' What\'s your experience? Share your thoughts below!';
            default:
                return $content;
        }
    }
}