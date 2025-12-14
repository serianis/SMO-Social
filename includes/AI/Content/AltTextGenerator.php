<?php
namespace SMO_Social\AI\Content;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * AI-powered alt text generation for images
 */
class AltTextGenerator {
    private $ai_manager;
    private $cache_manager;

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Generate alt text for images
     */
    public function generate($image_url, $context = '', $options = []) {
        $cache_key = "alttext_" . md5($image_url . $context . serialize($options));
        
        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $prompt = $this->build_alt_text_prompt($context, $options);
            
            // Use chat interface with vision capabilities if available, or just context
            // Note: Full vision support would require passing image URL in a specific format
            // For now, we'll assume the provider can handle image URLs in text or we rely on context
            
            $messages = [
                ['role' => 'system', 'content' => 'You are an accessibility expert. Generate descriptive alt text for images.'],
                ['role' => 'user', 'content' => $prompt . "\n\nImage URL: " . $image_url]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'max_tokens' => 100,
                'temperature' => 0.5
            ]);
            
            $alt_text = $this->extract_alt_text_from_result($result, $context);
            
            // Cache the result
            $this->cache_manager->set($cache_key, $alt_text, 3600);
            
            return $alt_text;
            
        } catch (\Exception $e) {
            error_log("SMO Social AltTextGenerator Error: " . $e->getMessage());
            return $this->generate_fallback_alt_text($image_url, $context);
        }
    }

    private function build_alt_text_prompt($context, $options) {
        $style = $options['style'] ?? 'descriptive';
        
        return "Generate descriptive alt text for an image. Context: {$context}. Style: {$style}. Keep it concise and accessible:";
    }

    private function extract_alt_text_from_result($result, $context) {
        if (isset($result['content'])) {
            return trim($result['content']);
        }
        
        if (isset($result['error'])) {
            throw new \Exception($result['error']);
        }
        
        return $this->generate_fallback_alt_text('', $context);
    }

    private function generate_fallback_alt_text($image_url, $context) {
        if (!empty($context)) {
            return "Image related to {$context}";
        }
        
        return "Image";
    }
}