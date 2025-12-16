<?php
namespace SMO_Social\AI;

use SMO_Social\AI\CacheManager;

/**
 * Continuous Learning System
 * Implements machine learning feedback loops and adaptive optimization
 */
class LearningSystem {
    private $cache_manager;
    private $learning_data = [];
    private $feedback_history = [];

    public function __construct($cache_manager = null) {
        $this->cache_manager = $cache_manager ?: new CacheManager();
        $this->initialize_learning_system();
    }

    /**
     * Initialize learning system
     */
    private function initialize_learning_system() {
        // Set up learning parameters and feedback mechanisms
        $this->learning_data = [
            'model_performance' => [],
            'content_effectiveness' => [],
            'user_engagement_patterns' => [],
            'optimization_results' => []
        ];
    }

    /**
     * Process feedback and update learning model
     */
    public function process_feedback($content_id, $performance_data, $user_engagement) {
        $feedback_key = "learning_feedback_" . md5($content_id);
        
        $feedback_data = [
            'content_id' => $content_id,
            'performance' => $performance_data,
            'engagement' => $user_engagement,
            'timestamp' => time(),
            'improvements' => $this->analyze_improvements($performance_data, $user_engagement)
        ];

        // Store feedback for analysis
        $this->cache_manager->set($feedback_key, $feedback_data, 86400); // 24 hours
        
        // Update learning patterns
        $this->update_learning_patterns($feedback_data);
        
        return $feedback_data;
    }

    /**
     * Analyze what improvements can be learned
     */
    private function analyze_improvements($performance_data, $user_engagement) {
        $improvements = [];
        
        // Analyze engagement patterns
        if (isset($user_engagement['engagement_rate'])) {
            if ($user_engagement['engagement_rate'] > 0.05) {
                $improvements[] = 'high_engagement_pattern';
            } elseif ($user_engagement['engagement_rate'] < 0.02) {
                $improvements[] = 'low_engagement_pattern';
            }
        }
        
        // Analyze content performance
        if (isset($performance_data['reach'])) {
            if ($performance_data['reach'] > 1000) {
                $improvements[] = 'high_reach_strategy';
            }
        }
        
        return $improvements;
    }

    /**
     * Update learning patterns based on feedback
     */
    private function update_learning_patterns($feedback_data) {
        $cache_key = "learning_patterns";
        
        $existing_patterns = $this->cache_manager->get($cache_key) ?: [];
        
        // Add new feedback to patterns
        $existing_patterns[] = $feedback_data;
        
        // Keep only recent patterns (last 1000 entries)
        if (count($existing_patterns) > 1000) {
            $existing_patterns = array_slice($existing_patterns, -1000);
        }
        
        $this->cache_manager->set($cache_key, $existing_patterns, 604800); // 7 days
    }

    /**
     * Get adaptive recommendations
     */
    public function get_adaptive_recommendations($content_type, $platform) {
        $cache_key = "adaptive_recommendations_" . md5($content_type . $platform);
        
        $cached_result = $this->cache_manager->get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }

        $patterns = $this->cache_manager->get("learning_patterns") ?: [];
        
        $recommendations = [
            'content_optimizations' => $this->analyze_content_patterns($patterns, $content_type),
            'timing_optimizations' => $this->analyze_timing_patterns($patterns, $platform),
            'format_optimizations' => $this->analyze_format_patterns($patterns, $content_type),
            'engagement_optimizations' => $this->analyze_engagement_patterns($patterns)
        ];

        $this->cache_manager->set($cache_key, $recommendations, 3600);
        return $recommendations;
    }

    /**
     * Analyze content patterns
     */
    private function analyze_content_patterns($patterns, $content_type) {
        $successful_patterns = array_filter($patterns, function($pattern) {
            return isset($pattern['engagement']['engagement_rate']) && 
                   $pattern['engagement']['engagement_rate'] > 0.05;
        });

        $optimizations = [];
        
        foreach ($successful_patterns as $pattern) {
            if (isset($pattern['improvements'])) {
                $optimizations = array_merge($optimizations, $pattern['improvements']);
            }
        }

        return array_unique($optimizations);
    }

    /**
     * Analyze timing patterns
     */
    private function analyze_timing_patterns($patterns, $platform) {
        $timing_data = [];
        
        foreach ($patterns as $pattern) {
            if (isset($pattern['engagement']['engagement_rate']) && 
                $pattern['engagement']['engagement_rate'] > 0.05) {
                $timestamp = $pattern['timestamp'];
                $hour = date('G', $timestamp);
                $day = date('w', $timestamp);
                
                if (!isset($timing_data[$day])) {
                    $timing_data[$day] = [];
                }
                if (!isset($timing_data[$day][$hour])) {
                    $timing_data[$day][$hour] = 0;
                }
                $timing_data[$day][$hour]++;
            }
        }

        return $timing_data;
    }

    /**
     * Analyze format patterns
     */
    private function analyze_format_patterns($patterns, $content_type) {
        $format_analysis = [];
        
        foreach ($patterns as $pattern) {
            if (isset($pattern['content_id'])) {
                // Extract format insights from content_id or performance
                $format_analysis[] = [
                    'content_type' => $content_type,
                    'success_indicators' => $this->extract_success_indicators($pattern)
                ];
            }
        }

        return $format_analysis;
    }

    /**
     * Analyze engagement patterns
     */
    private function analyze_engagement_patterns($patterns) {
        $engagement_patterns = [];
        
        foreach ($patterns as $pattern) {
            if (isset($pattern['engagement'])) {
                $engagement_patterns[] = $pattern['engagement'];
            }
        }

        return $engagement_patterns;
    }

    /**
     * Extract success indicators from pattern
     */
    private function extract_success_indicators($pattern) {
        $indicators = [];
        
        if (isset($pattern['performance']['reach']) && $pattern['performance']['reach'] > 1000) {
            $indicators[] = 'high_reach';
        }
        
        if (isset($pattern['engagement']['engagement_rate']) && $pattern['engagement']['engagement_rate'] > 0.05) {
            $indicators[] = 'high_engagement';
        }
        
        return $indicators;
    }

    /**
     * Continuous learning update
     */
    public function continuous_learning_update() {
        $patterns = $this->cache_manager->get("learning_patterns") ?: [];
        
        if (empty($patterns)) {
            return ['status' => 'no_data', 'message' => 'No learning data available'];
        }

        $analysis = [
            'total_patterns' => count($patterns),
            'learning_insights' => $this->generate_learning_insights($patterns),
            'optimization_opportunities' => $this->identify_optimization_opportunities($patterns),
            'performance_trends' => $this->analyze_performance_trends($patterns)
        ];

        return $analysis;
    }

    /**
     * Generate learning insights
     */
    private function generate_learning_insights($patterns) {
        $insights = [];
        
        // Analyze most successful patterns
        $successful_patterns = array_filter($patterns, function($pattern) {
            return isset($pattern['engagement']['engagement_rate']) && 
                   $pattern['engagement']['engagement_rate'] > 0.05;
        });

        $insights['success_rate'] = count($successful_patterns) / count($patterns);
        $insights['common_improvements'] = $this->find_common_improvements($patterns);
        
        return $insights;
    }

    /**
     * Find common improvements across patterns
     */
    private function find_common_improvements($patterns) {
        $all_improvements = [];
        
        foreach ($patterns as $pattern) {
            if (isset($pattern['improvements'])) {
                $all_improvements = array_merge($all_improvements, $pattern['improvements']);
            }
        }

        return array_count_values($all_improvements);
    }

    /**
     * Identify optimization opportunities
     */
    private function identify_optimization_opportunities($patterns) {
        $opportunities = [];
        
        // Look for patterns with low performance
        $low_performing = array_filter($patterns, function($pattern) {
            return isset($pattern['engagement']['engagement_rate']) && 
                   $pattern['engagement']['engagement_rate'] < 0.02;
        });

        $opportunities['low_performing_count'] = count($low_performing);
        $opportunities['common_issues'] = $this->identify_common_issues($low_performing);
        
        return $opportunities;
    }

    /**
     * Identify common issues in low performing content
     */
    private function identify_common_issues($low_performing) {
        $issues = [];
        
        foreach ($low_performing as $pattern) {
            if (isset($pattern['improvements'])) {
                foreach ($pattern['improvements'] as $improvement) {
                    if (!isset($issues[$improvement])) {
                        $issues[$improvement] = 0;
                    }
                    $issues[$improvement]++;
                }
            }
        }

        return $issues;
    }

    /**
     * Analyze performance trends
     */
    private function analyze_performance_trends($patterns) {
        $trends = [];
        
        // Sort patterns by timestamp
        usort($patterns, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        // Analyze trends over time
        $recent_patterns = array_slice($patterns, -50); // Last 50 patterns
        $older_patterns = array_slice($patterns, 0, -50); // All but last 50

        $recent_avg_engagement = $this->calculate_average_engagement($recent_patterns);
        $older_avg_engagement = $this->calculate_average_engagement($older_patterns);

        $trends['engagement_trend'] = $recent_avg_engagement - $older_avg_engagement;
        $trends['recent_performance'] = $recent_avg_engagement;
        $trends['historical_performance'] = $older_avg_engagement;

        return $trends;
    }

    /**
     * Calculate average engagement rate
     */
    private function calculate_average_engagement($patterns) {
        $total_engagement = 0;
        $count = 0;
        
        foreach ($patterns as $pattern) {
            if (isset($pattern['engagement']['engagement_rate'])) {
                $total_engagement += $pattern['engagement']['engagement_rate'];
                $count++;
            }
        }

        return $count > 0 ? $total_engagement / $count : 0;
    }
}
