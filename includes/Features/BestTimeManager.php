<?php
/**
 * Best Time Manager
 * 
 * Manages AI-powered best posting time predictions and recommendations
 * 
 * @package SMO_Social
 */

namespace SMO_Social\Features;

if (!defined('ABSPATH')) {
    exit;
}

class BestTimeManager {
    
    /**
     * Get best posting times for all platforms
     */
    public static function get_best_times($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'smo_best_time_predictions';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY confidence_score DESC",
            $user_id
        ), ARRAY_A);
        
        if (empty($results)) {
            return self::get_default_best_times();
        }
        
        return $results;
    }
    
    /**
     * Get best time for specific platform
     */
    public static function get_platform_best_time($platform, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'smo_best_time_predictions';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND platform_slug = %s ORDER BY confidence_score DESC LIMIT 1",
            $user_id,
            $platform
        ), ARRAY_A);
        
        if (!$result) {
            $defaults = self::get_default_best_times();
            return $defaults[$platform] ?? null;
        }
        
        return $result;
    }
    
    /**
     * Save best time prediction
     */
    public static function save_best_time($platform, $time_slot, $confidence_score, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'smo_best_time_predictions';
        
        return $wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'platform_slug' => $platform,
                'time_slot' => $time_slot,
                'confidence_score' => $confidence_score,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%f', '%s')
        );
    }
    
    /**
     * Get default best times (industry standards)
     */
    private static function get_default_best_times() {
        return array(
            'twitter' => array(
                'platform_slug' => 'twitter',
                'time_slot' => '09:00-11:00',
                'confidence_score' => 0.75,
                'engagement_rate' => 'High',
                'description' => 'Morning engagement peak'
            ),
            'facebook' => array(
                'platform_slug' => 'facebook',
                'time_slot' => '13:00-15:00',
                'confidence_score' => 0.80,
                'engagement_rate' => 'High',
                'description' => 'Lunch break activity'
            ),
            'linkedin' => array(
                'platform_slug' => 'linkedin',
                'time_slot' => '08:00-10:00',
                'confidence_score' => 0.85,
                'engagement_rate' => 'Very High',
                'description' => 'Professional morning hours'
            ),
            'instagram' => array(
                'platform_slug' => 'instagram',
                'time_slot' => '18:00-20:00',
                'confidence_score' => 0.78,
                'engagement_rate' => 'High',
                'description' => 'Evening leisure time'
            ),
            'youtube' => array(
                'platform_slug' => 'youtube',
                'time_slot' => '14:00-16:00',
                'confidence_score' => 0.72,
                'engagement_rate' => 'Medium-High',
                'description' => 'Afternoon viewing peak'
            ),
            'tiktok' => array(
                'platform_slug' => 'tiktok',
                'time_slot' => '19:00-21:00',
                'confidence_score' => 0.82,
                'engagement_rate' => 'Very High',
                'description' => 'Prime evening hours'
            ),
            'pinterest' => array(
                'platform_slug' => 'pinterest',
                'time_slot' => '20:00-22:00',
                'confidence_score' => 0.70,
                'engagement_rate' => 'Medium-High',
                'description' => 'Evening browsing time'
            )
        );
    }
    
    /**
     * Analyze historical data and update best times
     */
    public static function analyze_and_update($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $analytics_table = $wpdb->prefix . 'smo_post_analytics';
        
        // Get engagement data grouped by platform and hour
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                platform_slug,
                HOUR(created_at) as hour,
                AVG(engagement_rate) as avg_engagement,
                COUNT(*) as post_count
            FROM $analytics_table
            WHERE user_id = %d
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY platform_slug, HOUR(created_at)
            HAVING post_count >= 3
            ORDER BY platform_slug, avg_engagement DESC",
            $user_id
        ), ARRAY_A);
        
        $platform_best_times = array();
        
        foreach ($results as $row) {
            $platform = $row['platform_slug'];
            
            if (!isset($platform_best_times[$platform])) {
                $hour = intval($row['hour']);
                $time_slot = sprintf('%02d:00-%02d:00', $hour, ($hour + 2) % 24);
                
                // Calculate confidence score based on data points
                $confidence = min(0.95, 0.5 + ($row['post_count'] * 0.05));
                
                self::save_best_time(
                    $platform,
                    $time_slot,
                    $confidence,
                    $user_id
                );
                
                $platform_best_times[$platform] = true;
            }
        }
        
        return count($platform_best_times);
    }
    
    /**
     * Get engagement prediction for a specific time
     */
    public static function predict_engagement($platform, $datetime) {
        $hour = intval(date('H', strtotime($datetime)));
        $best_time = self::get_platform_best_time($platform);
        
        if (!$best_time) {
            return array(
                'score' => 0.5,
                'level' => 'Medium',
                'recommendation' => 'No historical data available'
            );
        }
        
        // Parse time slot
        $time_parts = explode('-', $best_time['time_slot']);
        $best_hour = intval(substr($time_parts[0], 0, 2));
        
        // Calculate proximity to best time
        $hour_diff = abs($hour - $best_hour);
        if ($hour_diff > 12) {
            $hour_diff = 24 - $hour_diff;
        }
        
        // Score decreases with distance from best time
        $score = max(0.2, $best_time['confidence_score'] - ($hour_diff * 0.05));
        
        $level = 'Low';
        $recommendation = 'Consider rescheduling';
        
        if ($score >= 0.7) {
            $level = 'High';
            $recommendation = 'Excellent time to post';
        } elseif ($score >= 0.5) {
            $level = 'Medium';
            $recommendation = 'Good time to post';
        }
        
        return array(
            'score' => round($score, 2),
            'level' => $level,
            'recommendation' => $recommendation,
            'best_time' => $best_time['time_slot']
        );
    }
}
