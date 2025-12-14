<?php
namespace SMO_Social\AI\Analysis;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * AI-powered sentiment analysis for content and comments
 */
class SentimentAnalyzer {
    private $ai_manager;
    private $cache_manager;

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Analyze sentiment of text
     */
    public function analyze($text, $options = []) {
        $cache_key = "sentiment_" . md5($text . serialize($options));
        
        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            // Use chat interface for sentiment analysis
            $messages = [
                ['role' => 'system', 'content' => 'Analyze the sentiment of the following text. Return a JSON object with keys: "sentiment" (positive, neutral, or negative) and "confidence" (float between 0.0 and 1.0).'],
                ['role' => 'user', 'content' => $text]
            ];
            
            $result = $this->ai_manager->chat($messages, [
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object']
            ]);
            
            $sentiment = $this->extract_sentiment_from_result($result);
            
            // Cache the result
            $this->cache_manager->set($cache_key, $sentiment, 3600);
            
            return $sentiment;
            
        } catch (\Exception $e) {
            error_log("SMO Social SentimentAnalyzer Error: " . $e->getMessage());
            return $this->analyze_sentiment_basic($text);
        }
    }

    private function extract_sentiment_from_result($result) {
        if (isset($result['content'])) {
            $content = $result['content'];
            
            // Try to parse JSON
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'sentiment' => $data['sentiment'] ?? 'neutral',
                    'confidence' => $data['confidence'] ?? 0.5
                ];
            }
            
            // Fallback parsing if not valid JSON
            if (stripos($content, 'positive') !== false) return ['sentiment' => 'positive', 'confidence' => 0.8];
            if (stripos($content, 'negative') !== false) return ['sentiment' => 'negative', 'confidence' => 0.8];
        }
        
        return [
            'sentiment' => 'neutral',
            'confidence' => 0.5
        ];
    }

    private function analyze_sentiment_basic($text) {
        // Simple rule-based sentiment analysis as fallback
        $positive_words = ['great', 'excellent', 'amazing', 'love', 'best', 'awesome', 'good', 'happy', 'wonderful'];
        $negative_words = ['bad', 'terrible', 'hate', 'worst', 'awful', 'sad', 'angry', 'disappointing', 'frustrating'];
        
        $text_lower = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($text_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($text_lower, $word);
        }
        
        if ($positive_count > $negative_count) {
            return ['sentiment' => 'positive', 'confidence' => 0.7];
        } elseif ($negative_count > $positive_count) {
            return ['sentiment' => 'negative', 'confidence' => 0.7];
        }
        
        return ['sentiment' => 'neutral', 'confidence' => 0.5];
    }
}