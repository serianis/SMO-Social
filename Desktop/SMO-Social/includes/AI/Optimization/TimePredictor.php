<?php
namespace SMO_Social\AI\Optimization;

use SMO_Social\AI\Models\UniversalManager;
use SMO_Social\AI\CacheManager;

/**
 * AI-powered optimal posting time prediction
 */
class TimePredictor {
    private $ai_manager;
    private $cache_manager;

    public function __construct(UniversalManager $ai_manager, CacheManager $cache_manager) {
        $this->ai_manager = $ai_manager;
        $this->cache_manager = $cache_manager;
    }

    /**
     * Predict best posting times for platforms
     */
    public function predict($platform, $content_type = 'general', $options = []) {
        $cache_key = "times_" . md5($platform . $content_type . serialize($options));
        
        // Check cache first
        $cached = $this->cache_manager->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            // Get historical data or use AI to predict optimal times
            $historical_data = $this->get_historical_data($platform, $content_type);
            
            if (!empty($historical_data)) {
                $times = $this->analyze_historical_patterns($historical_data, $platform);
            } else {
                $times = $this->generate_default_times($platform, $content_type);
            }
            
            // Cache the result
            $this->cache_manager->set($cache_key, $times, 3600);
            
            return $times;
            
        } catch (\Exception $e) {
            error_log("SMO Social TimePredictor Error: " . $e->getMessage());
            return $this->generate_default_times($platform, $content_type);
        }
    }

    private function get_historical_data($platform, $content_type) {
        // This would typically query actual post performance data
        // For now, return mock data structure
        return [
            ['hour' => 9, 'engagement_rate' => 0.05, 'posts' => 10],
            ['hour' => 12, 'engagement_rate' => 0.08, 'posts' => 15],
            ['hour' => 18, 'engagement_rate' => 0.12, 'posts' => 20],
            ['hour' => 20, 'engagement_rate' => 0.15, 'posts' => 25]
        ];
    }

    private function analyze_historical_patterns($data, $platform) {
        // Sort by engagement rate and return top times
        usort($data, function($a, $b) {
            return $b['engagement_rate'] <=> $a['engagement_rate'];
        });

        $top_times = array_slice($data, 0, 5);
        
        $formatted_times = [];
        foreach ($top_times as $time_data) {
            $formatted_times[] = sprintf('%02d:00', $time_data['hour']);
        }
        
        return $formatted_times;
    }

    private function generate_default_times($platform, $content_type) {
        // Platform-specific default optimal times
        $defaults = [
            'twitter' => ['09:00', '12:00', '15:00', '18:00'],
            'facebook' => ['09:00', '13:00', '19:00'],
            'instagram' => ['11:00', '14:00', '17:00'],
            'linkedin' => ['08:00', '12:00', '17:00'],
            'tiktok' => ['18:00', '19:00', '20:00']
        ];

        return $defaults[$platform] ?? ['12:00', '15:00', '18:00'];
    }
}